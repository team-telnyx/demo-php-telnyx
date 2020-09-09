<?php

use Telnyx\AvailablePhoneNumber;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$TELNYX_API_KEY = getenv('TELNYX_API_KEY');
Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);

function extractNxx(object $telnyxAvailablePhoneNumberResponse){
    $phoneNumber = $telnyxAvailablePhoneNumberResponse['phone_number'];
    $npa = substr($phoneNumber, 5, 3);
    return $npa;
}

function searchAvailableNumbers(string $areaCode) {
   try {
       $telnyxResponse = AvailablePhoneNumber::All([
           "filter[national_destination_code]" => $areaCode,
           "filter[best_effort]" => false,
           "filter[limit]" => 100
       ]);
       return $telnyxResponse->data;
   } catch (Exception $e){
       echo 'Caught exception: ',  $e->getMessage(), "\n";
       exit;
   }
}

function findUniqueCount(array $telnyxResponseData){
    $npas = array_map("extractNxx", $telnyxResponseData);
    $uniqueValues = array_count_values($npas);
    return $uniqueValues;
}

function getAreaCodeFromUser(){
    echo "Which NPA (area code) would you like to get NXX counts?: ";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    $npa = trim($line);
    if(strlen($npa) != 3){
        echo "Invalid NPA (area code), NPAs are 3 digits\n";
        exit;
    }
    fclose($handle);
    return $npa;
}

$areaCode = getAreaCodeFromUser();
$telnyxResponse = searchAvailableNumbers($areaCode);
$nxxCount = findUniqueCount($telnyxResponse);
print_r($nxxCount);




