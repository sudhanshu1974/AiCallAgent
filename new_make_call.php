<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to log messages
function logMessage($logFile, $message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Set up logging
$logFolder = __DIR__ . '/log';
if (!file_exists($logFolder)) {
    mkdir($logFolder, 0777, true);
}
$activityLog = $logFolder . '/activity.log';
$errorLog = $logFolder . '/error.log';
ini_set('error_log', $errorLog);

// Require necessary files
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php'; // Ensure Database class is loaded

// Database setup
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    logMessage($errorLog, 'Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Database connection failed']);
    exit;
}


// Use the Twilio REST client
use Twilio\Rest\Client;

// Set default response headers
header('Content-Type: application/json');

// Get the phone number to call and leadsid from the request
$phoneNumber = $_POST['phoneNumber'] ?? '';
$leadsid = $_POST['leadsid'] ?? '';
// $callscript = ''; // Callscript is no longer sent from frontend - Removed

logMessage($activityLog, "new_make_call.php: Script started. Received phone number: " . $phoneNumber . ", Leadsid: " . $leadsid);

// Fetch lead details to generate callscript
$sqlLead = 'SELECT first_name FROM leads WHERE leadsid = ?';
$paramsLead = [$leadsid];
$stmtLead = $db->query($sqlLead, $paramsLead);
$leadDetails = $db->fetch($stmtLead);

$firstName = $leadDetails['first_name'] ?? 'there'; // Default to 'there' if first_name not found

// This is the initial greeting. The rest of the flow is handled by the webhook.
$callscript = "Hi, may I please speak with " . htmlspecialchars($firstName) . "?";
logMessage($activityLog, "new_make_call.php: Generated initial callscript: " . $callscript);

// Validate the phone number
if (empty($phoneNumber)) {
    logMessage($errorLog, "new_make_call.php: Phone number is required.");
    http_response_code(400);
    echo json_encode(['message' => 'Phone number is required.']);
    exit;
}

// Validate leadsid
if (empty($leadsid)) {
    logMessage($errorLog, "new_make_call.php: Leadsid is required.");
    http_response_code(400);
    echo json_encode(['message' => 'Leadsid is required.']);
    exit;
}

try {
    // Insert into Calls table and get the inserted ID
    $sql = 'INSERT INTO calls (leadsid, callscript) OUTPUT INSERTED.callid VALUES (?, ?)';
    $params = [$leadsid, $callscript];
    logMessage($activityLog, "new_make_call.php: Executing INSERT query with OUTPUT: " . $sql . " with params: " . print_r($params, true));
    // echo json_encode(['message'] => 'Executing INSERT query.'); exit; // Debug point 6 - Removed
    $stmt = $db->query($sql, $params);
    $insertedRow = $db->fetch($stmt); // Fetch the output
    logMessage($activityLog, "new_make_call.php: Raw inserted row from DB: " . print_r($insertedRow, true));
    $callid = $insertedRow['callid'] ?? null; // Get the ID from the fetched row

    logMessage($activityLog, "new_make_call.php: Call record inserted. Call ID from OUTPUT: " . var_export($callid, true));
    // echo json_encode(['message'] => 'Call record inserted.'); exit; // Debug point 7 - Removed

    // Create a new Twilio REST client
    $client = new Client(TWILIO_SID, TWILIO_TOKEN);
    logMessage($activityLog, "new_make_call.php: Twilio Client created.");
    // echo json_encode(['message' => 'Twilio Client created.']); exit; // Debug point 8 - Removed

    // Construct the webhook URL
    $webhookUrl = BASE_URL . '/twilio_webhook.php?leadsid=' . urlencode($leadsid) . '&callid=' . urlencode($callid);
    logMessage($activityLog, "new_make_call.php: Webhook URL: " . $webhookUrl);

    logMessage($activityLog, "new_make_call.php: Attempting to make call to: " . $phoneNumber . " from: " . TWILIO_PHONE_NUMBER . " with webhook: " . $webhookUrl);
    // echo json_encode(['message' => 'Attempting to make Twilio call.']); exit; // Debug point 9 - Removed

    // Make the outbound call
    $call = $client->calls->create(
        $phoneNumber, // To
        TWILIO_PHONE_NUMBER, // From
        [
            'url' => $webhookUrl
        ]
    );

    // Return a success message
    logMessage($activityLog, "new_make_call.php: Call initiated successfully! SID: " . $call->sid);
    echo json_encode(['message' => 'Call initiated successfully!', 'sid' => $call->sid]);

} catch (Exception $e) {
    logMessage($errorLog, "new_make_call.php: Error initiating call: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Error initiating call: ' . $e->getMessage()]);
}?>