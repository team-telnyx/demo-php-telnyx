<?php

use Telnyx\Verification;

require __DIR__ . '/vendor/autoload.php';

// loading dotenv package
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// set Telnyx API Key
$TELNYX_API_KEY = $_ENV["TELNYX_API_KEY"];
Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);

$TELNYX_VERIFY_ID = $_ENV["TELNYX_VERIFY_PROFILE_ID"];

function sendVerification($numberInput, $TELNYX_VERIFY_ID){
    // constructing verification payload
    $createVerification = array (
        "verify_profile_id" => $TELNYX_VERIFY_ID,
        "phone_number" => $numberInput,
        "type" => "sms",
        "timeout_secs" => 300
    );
    try {
        // trigger verification API request
        $resource = Verification::create($createVerification);
    } catch (Exception $e){
        echo "Caught exception: ", $e->getMessage(), "\n";
        exit;
        }
}

function codeVerify($numberInput){
    // Gives max attempts
    $attempts = 0;
    $maxAttempts = 5;
    while ($attempts < $maxAttempts) {
        $codeInput = readline("Verification code? "); // adding EOL breaks the verify, since it's a new line heh
        $attempts++;
        try {
            $telnyxResponse = Verification::submit_verification($numberInput, $codeInput);
            if ($telnyxResponse["data"]["response_code"] == "accepted"){
                echo('Code accepted!');
                break;
            }
            else {
                echo('Code rejected, try again!') . PHP_EOL;
                if ($attempts >= $maxAttempts) {
                    echo('Verification max attempts reached, goodbye...') . PHP_EOL;
                }
            }
        } catch (Exception $e){
            echo "Caught exception: ", $e->getMessage(), "\n";
            exit;
            }
    }
}

function Main ($TELNYX_VERIFY_ID) {
    $numberInput = readline("What number(+E164) to Verify? ") . PHP_EOL;
    sendVerification($numberInput, $TELNYX_VERIFY_ID);
    codeVerify($numberInput);
}

Main($TELNYX_VERIFY_ID);
