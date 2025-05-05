<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Receive the callback response from M-Pesa
$stkCallbackResponse = file_get_contents('php://input');

// Log the callback
$logFile = "stkPushCallbackResponse.json";
$log = fopen($logFile, "a");
fwrite($log, date('Y-m-d H:i:s') . ": " . $stkCallbackResponse . "\n");
fclose($log);

// Decode the response
$callbackData = json_decode($stkCallbackResponse);

// Check if we have a valid response
if (isset($callbackData) && isset($callbackData->Body) && isset($callbackData->Body->stkCallback)) {
    $resultCode = $callbackData->Body->stkCallback->ResultCode;
    $checkoutRequestID = $callbackData->Body->stkCallback->CheckoutRequestID;
    
    // Store the callback information in a database or session
    // For simplicity, we'll use a file-based approach here
    $transactionStatusFile = "transaction_status.json";
    
    // Read existing transactions
    $transactions = [];
    if (file_exists($transactionStatusFile)) {
        $fileContents = file_get_contents($transactionStatusFile);
        if (!empty($fileContents)) {
            $transactions = json_decode($fileContents, true);
        }
    }
    
    // Update transaction status
    $transactions[$checkoutRequestID] = [
        'resultCode' => $resultCode,
        'resultDesc' => $callbackData->Body->stkCallback->ResultDesc ?? 'No description',
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $callbackData
    ];
    
    // Save updated transactions
    file_put_contents($transactionStatusFile, json_encode($transactions));
}

// M-Pesa expects a response
header('Content-Type: application/json');
echo json_encode(['ResponseCode' => '0', 'ResponseDesc' => 'Success']);
?>