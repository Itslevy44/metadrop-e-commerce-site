<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if(isset($_POST['submit'])){
    date_default_timezone_set('Africa/Nairobi');
    
    # access token
    $consumerKey = 'C6W5OcNHxunnIgtGEAWUA3o9TlIfkLAb';
    $consumerSecret = '3coRAVLjOwjG0Hts';
    
    $BusinessShortCode = '174379';
    $Passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    
    // Ensure we have the phone number and amount
    if(!isset($_POST['phonenumber']) || !isset($_POST['amount'])) {
        $_SESSION['payment_status'] = 'error';
        $_SESSION['payment_error'] = 'Missing required parameters';
        header("Location: checkout.php?payment=error&message=missing_parameters");
        exit();
    }
    
    $PartyA = $_POST['phonenumber']; // Phone number
    $AccountReference = 'Pio Spices East Africa';
    $TransactionDesc = 'Test lipa na mpesa stk push initiation';
    $Amount = $_POST['amount'];
    
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode.$Passkey.$Timestamp);
    
    # M-PESA endpoint urls
    $access_token_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $initiate_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    # callback url
    $CallBackURL = 'https://stk-push-php.herokuapp.com/callback.php';
    
    try {
        // Get access token
        $curl = curl_init($access_token_url);
        $credentials = base64_encode($consumerKey.":".$consumerSecret);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic '.$credentials));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // For testing only - remove in production
        
        $result = curl_exec($curl);
        
        if($result === false) {
            throw new Exception('Curl error: ' . curl_error($curl));
        }
        
        $result = json_decode($result);
        
        if(!isset($result->access_token)) {
            throw new Exception("Failed to get access token: " . json_encode($result));
        }
        
        $accessToken = $result->access_token;
        curl_close($curl);
        
        # header for stk push
        $stkheader = ['Content-Type:application/json', 'Authorization:Bearer '.$accessToken];
        
        # initiating the transaction
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $initiate_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $stkheader);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // For testing only - remove in production
        
        $curl_post_data = array(
            'BusinessShortCode' => $BusinessShortCode,
            'Password' => $Password,
            'Timestamp' => $Timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $Amount,
            'PartyA' => $PartyA,
            'PartyB' => $BusinessShortCode,
            'PhoneNumber' => $PartyA,
            'CallBackURL' => $CallBackURL,
            'AccountReference' => $AccountReference,
            'TransactionDesc' => $TransactionDesc
        );
        
        $data_string = json_encode($curl_post_data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        $curl_response = curl_exec($curl);
        
        if($curl_response === false) {
            throw new Exception('Curl error: ' . curl_error($curl));
        }
        
        // Log the response for debugging
        $log = fopen("stk_push_response.log", "a");
        fwrite($log, date('Y-m-d H:i:s') . ": " . $curl_response . "\n");
        fclose($log);
        
        $response_data = json_decode($curl_response);
        curl_close($curl);
        
        // Store the response in session for checkout.php to access
        $_SESSION['mpesa_response'] = $curl_response;
        
        if(isset($response_data->ResponseCode) && $response_data->ResponseCode == "0") {
            // Success - STK push initiated
            $_SESSION['payment_status'] = 'initiated';
            $_SESSION['checkout_request_id'] = $response_data->CheckoutRequestID;
            
            // Redirect back to checkout page with success message
            header("Location: checkout.php?payment=initiated");
            exit();
        } else {
            // Failed to initiate STK push
            $_SESSION['payment_status'] = 'failed';
            $_SESSION['payment_error'] = isset($response_data->errorMessage) ? $response_data->errorMessage : 'Unknown error occurred';
            
            // Redirect back to checkout page with error
            header("Location: checkout.php?payment=failed");
            exit();
        }
    } catch(Exception $e) {
        // Log the error
        $log = fopen("stk_push_error.log", "a");
        fwrite($log, date('Y-m-d H:i:s') . ": " . $e->getMessage() . "\n");
        fclose($log);
        
        $_SESSION['payment_status'] = 'error';
        $_SESSION['payment_error'] = $e->getMessage();
        
        // Redirect back to checkout page with system error
        header("Location: checkout.php?payment=error&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // If accessed directly without POST data
    header("Location: checkout.php?error=invalid_request");
    exit();
}
?>