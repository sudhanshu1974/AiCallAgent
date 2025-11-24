<?php
// Version 2.0 - Added comment to force refresh

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/log/error.log');

header("Connection: keep-alive");
header("Keep-alive: timeout=5, max=1000");

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php'; // Ensure Database class is loaded

use Twilio\TwiML\VoiceResponse;

$logFolder = __DIR__ . '/log';
if (!file_exists($logFolder)) {
    mkdir($logFolder, 0777, true);
}
$activityLog = $logFolder . '/activity.log';
$errorLog = $logFolder . '/error.log';

function logMessage($logFile, $message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

global $geminiApiKey;
$geminiApiKey = GEMINI_API_KEY;

// Database setup
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    logMessage($errorLog, 'Database connection failed in process_industry.php: ' . $e->getMessage());
    // Fallback TwiML or hangup
    $response = new VoiceResponse();
    $response->say('I am sorry, but I am experiencing technical difficulties. Please try again later.', ['voice' => 'Polly.Joanna']);
    $response->hangup();
    header('Content-Type: text/xml');
    echo $response;
    exit;
}

function callGemini(string $prompt, string $activityLog, string $errorLog): string
{
    logMessage($activityLog, "Entered callGemini function.");
    global $geminiApiKey;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $geminiApiKey;
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];

    try {
        logMessage($activityLog, 'Gemini API URL: ' . $url);
        logMessage($activityLog, 'Gemini API Request Payload: ' . json_encode($data));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Ensure SSL verification is on


        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            logMessage($errorLog, 'cURL Error: ' . curl_error($ch));
            curl_close($ch);
            return 'An error occurred while processing your request.';
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpcode >= 400) {
            logMessage($errorLog, "Gemini API call failed with HTTP code: $httpcode. Response: " . $result);
            curl_close($ch);
            return 'An error occurred while processing your request.';
        }

        curl_close($ch);

        logMessage($activityLog, 'Raw Gemini API Response: ' . $result);
        $response = json_decode($result, true);
        logMessage($activityLog, 'Decoded Gemini API Response: ' . json_encode($response));

        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $response['candidates'][0]['content']['parts'][0]['text'];
        } else {
            logMessage($errorLog, 'Invalid Gemini API response. Missing expected keys. Full response: ' . json_encode($response));
            return 'An error occurred while processing your request.';
        }
    } catch (Exception $e) {
        logMessage($errorLog, 'Gemini API call failed: ' . $e->getMessage());
        return 'An error occurred while processing your request.';
    }
}

$response = new VoiceResponse();
$userInput = $_REQUEST['SpeechResult'] ?? '';
$leadsid = $_GET['leadsid'] ?? '';
$callid = $_GET['callid'] ?? '';

if (empty($callid)) {
    logMessage($errorLog, "process_industry.php: callid is empty.");
    $response->say('I am sorry, but there was an error processing the call.', ['voice' => 'Polly.Joanna-Neural', 'language' => 'en-US']);
    $response->hangup();
    header('Content-Type: text/xml');
    echo $response;
    exit;
}

// --- Start of new logic ---

// 1. Load the master prompt/instructions
$callFlowPath = __DIR__ . '/Prompt/P_CallFlow_Summary.txt';
if (file_exists($callFlowPath)) {
    $masterPrompt = file_get_contents($callFlowPath);
} else {
    logMessage($errorLog, "process_industry.php: Call flow file not found at " . $callFlowPath);
    $response->say('I am sorry, but there was an error with my configuration.', ['voice' => 'Polly.Joanna-Neural', 'language' => 'en-US']);
    $response->hangup();
    header('Content-Type: text/xml');
    echo $response;
    exit;
}

// 2. Fetch conversation history
$historySql = 'SELECT user_response, ai_response FROM call_details WHERE callid = ? ORDER BY timestamp ASC';
$historyStmt = $db->query($historySql, [$callid]);
$conversationHistory = $db->fetchAll($historyStmt);

// 3. Construct the prompt for the Gemini API
$geminiPrompt = $masterPrompt . "\n\n--- Conversation History ---\n";

// Add the initial script from the `calls` table as the first AI message
$initialSql = 'SELECT callscript FROM calls WHERE callid = ?';
$initialStmt = $db->query($initialSql, [$callid]);
$initialCall = $db->fetch($initialStmt);
if ($initialCall && !empty($initialCall['callscript'])) {
    $geminiPrompt .= "AI: " . $initialCall['callscript'] . "\n";
}

foreach ($conversationHistory as $turn) {
    if (!empty($turn['user_response'])) {
        $geminiPrompt .= "User: " . $turn['user_response'] . "\n";
    }
    if (!empty($turn['ai_response'])) {
        $geminiPrompt .= "AI: " . $turn['ai_response'] . "\n";
    }
}

if (!empty($userInput)) {
    logMessage($activityLog, "process_industry.php: User input: " . $userInput);
    $geminiPrompt .= "User: " . $userInput . "\n";
    $geminiPrompt .= "\n\nBased on the conversation history and the instructions above, provide only the NEXT SINGLE response for the 'Agent'. Do not include any previous 'Agent' or 'User' dialogue. Start directly with your response.\nAI:"; // Prompt the AI for its next response

    // 4. Call the Gemini API
    $aiResponse = callGemini($geminiPrompt, $activityLog, $errorLog);
    logMessage($activityLog, "process_industry.php: AI response: " . $aiResponse);

    // 5. Save the current turn to the database
    $db->query('INSERT INTO call_details (callid, user_response, ai_response, timestamp) VALUES (?, ?, ?, ?)', [$callid, $userInput, $aiResponse, date('Y-m-d H:i:s')]);
    $db->query("UPDATE calls SET status = 'in-progress' WHERE callid = ?", [$callid]);

    // 6. Generate TwiML response
    $response->say($aiResponse, ['voice' => 'Polly.Joanna-Neural', 'language' => 'en-US']);
} else {
    // This handles the case where the user didn't say anything.
    // We can either repeat the last AI response or ask them to speak up.
    $lastAiResponse = "I'm sorry, I didn't catch that. Could you please repeat what you said?";
    if (count($conversationHistory) > 0) {
        $lastTurn = end($conversationHistory);
        if (!empty($lastTurn['ai_response'])) {
            $lastAiResponse = $lastTurn['ai_response'];
        }
    }
    logMessage($activityLog, "process_industry.php: No user input. Repeating last message or asking again.");
    $response->say($lastAiResponse, ['voice' => 'Polly.Joanna-Neural', 'language' => 'en-US']);
}

// 7. Loop the conversation
$actionUrl = BASE_URL . '/process_industry.php?callid=' . urlencode($callid) . '&leadsid=' . urlencode($leadsid);
$response->gather(['input' => 'speech', 'action' => $actionUrl, 'speechModel' => 'phone_call', 'enhanced' => true, 'speechTimeout' => 'auto', 'bargeIn' => true, 'timeout' => 4]);

// --- End of new logic ---

header('Content-Type: text/xml');
echo $response;

?>
