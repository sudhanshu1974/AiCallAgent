<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Keep this for debugging
ini_set('display_startup_errors', 1); // Also display startup errors

header("Connection: keep-alive");
header("Keep-alive: timeout=5, max=1000");

// Set up logging
$logFolder = __DIR__ . '/log';
if (!file_exists($logFolder)) {
    mkdir($logFolder, 0777, true);
}
$errorLog = $logFolder . '/error.log';

// Function to log messages
function logMessage($logFile, $message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage($errorLog, 'twilio_webhook.php: Script started.');
// echo "Script started."; exit; // Debug point 1

// Require the Composer autoloader and the configuration file
require __DIR__ . '/vendor/autoload.php';
logMessage($errorLog, 'twilio_webhook.php: vendor/autoload.php loaded.');
// echo "vendor/autoload.php loaded."; exit; // Debug point 2

require __DIR__ . '/config.php';
logMessage($errorLog, 'twilio_webhook.php: config.php loaded.');
// echo "config.php loaded."; exit; // Debug point 3

require_once __DIR__ . '/Database.php'; // Ensure Database class is loaded
logMessage($errorLog, 'twilio_webhook.php: Database.php loaded.');
// echo "Database.php loaded."; exit; // Debug point 4

// Database setup
try {
    logMessage($errorLog, 'twilio_webhook.php: Attempting database connection.');
    // echo "Attempting database connection."; exit; // Debug point 5 - Removed
    $db = Database::getInstance();
    logMessage($errorLog, 'twilio_webhook.php: Database connection successful.');
    // echo "Database connection successful."; exit; // Debug point 6
} catch (Exception $e) {
    logMessage($errorLog, 'Database connection failed in twilio_webhook.php: ' . $e->getMessage());
    // Fallback TwiML or hangup
    $response = new VoiceResponse();
    $response->say('I am sorry, but I am experiencing technical difficulties. Please try again later.', ['voice' => 'Polly.Joanna-Neural', 'language' => 'en-US']);
    $response->hangup();
    header('Content-Type: text/xml');
    echo $response;
    exit;
}

// Use the Twilio TwiML VoiceResponse class
use Twilio\TwiML\VoiceResponse;
logMessage($errorLog, 'twilio_webhook.php: Twilio TwiML VoiceResponse class loaded.');
// echo "Twilio TwiML VoiceResponse class loaded."; exit; // Debug point 7

// Create a new TwiML response
$response = new VoiceResponse();
logMessage($errorLog, 'twilio_webhook.php: TwiML response object created.');
// echo "TwiML response object created."; exit; // Debug point 8

// Get leadsid, callscript, and commid from the URL
$leadsid = $_GET['leadsid'] ?? '';
$callid_from_make_call = $_GET['callid'] ?? ''; // Get callid from URL
logMessage($errorLog, 'twilio_webhook.php: Received leadsid: ' . $leadsid . ', callid: ' . $callid_from_make_call);
// echo "Received leadsid and callid."; exit; // Debug point 9

logMessage($errorLog, 'twilio_webhook.php: Initializing callscript variable.');
$callscript = 'Hello, how can I help you today?'; // Default callscript

if (!empty($callid_from_make_call)) {
    logMessage($errorLog, 'twilio_webhook.php: Attempting to fetch callscript for callid: ' . $callid_from_make_call);
    // echo "Attempting to fetch callscript."; exit; // Debug point 10
    $sql = 'SELECT callscript FROM calls WHERE callid = ?';
    $params = [$callid_from_make_call];
    $stmt = $db->query($sql, $params);
    $callData = $db->fetch($stmt);
    if ($callData && isset($callData['callscript'])) {
        $callscript = $callData['callscript'];
        logMessage($errorLog, 'twilio_webhook.php: Callscript fetched: ' . $callscript);
        // echo "Callscript fetched."; exit; // Debug point 11
    } else {
        logMessage($errorLog, "twilio_webhook.php: Callscript not found for callid: " . $callid_from_make_call . ". Using default.");
        // echo "Callscript not found."; exit; // Debug point 12
    }
} else {
    logMessage($errorLog, "twilio_webhook.php: callid_from_make_call is empty. Using default callscript.");
    // echo "callid_from_make_call is empty."; exit; // Debug point 13
}

logMessage($errorLog, 'twilio_webhook.php: Callscript to use: ' . $callscript);
// echo "Callscript to use."; exit; // Debug point 14

// Greet the user and gather their speech input
    $response->say($callscript, ['voice' => 'Polly.Joanna-Neural', 'language' => 'en-US']); // Use the dynamic callscript
    logMessage($errorLog, 'twilio_webhook.php: Response say added.');
    // echo "Response say added."; exit; // Debug point 15

    $actionUrl = BASE_URL . '/process_industry.php';
    $params = [];
    if (!empty($leadsid)) {
        $params['leadsid'] = urlencode($leadsid);
    }
    if (!empty($callscript)) {
        $params['callscript'] = urlencode($callscript);
    }
    if (!empty($callid_from_make_call)) {
        $params['callid'] = urlencode($callid_from_make_call); // Pass callid to process_industry.php
    }
    if (!empty($params)) {
        $actionUrl .= '?' . http_build_query($params);
    }
    logMessage($errorLog, 'twilio_webhook.php: Action URL: ' . $actionUrl);
    // echo "Action URL constructed."; exit; // Debug point 16

    $response->gather(['input' => 'speech', 'action' => $actionUrl, 'speechModel' => 'phone_call', 'enhanced' => true,  'speechTimeout' => 'auto']);
    logMessage($errorLog, 'twilio_webhook.php: Response gather added.');
    // echo "Response gather added."; exit; // Debug point 17

logMessage($errorLog, 'twilio_webhook.php: Rendering TwiML response.');
// Render the TwiML response
header('Content-Type: text/xml');
echo $response;

?>