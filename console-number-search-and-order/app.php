<?php

use Telnyx\AvailablePhoneNumber;
use Telnyx\NumberOrder;
use Telnyx\PhoneNumber;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$TELNYX_API_KEY = getenv("TELNYX_API_KEY");
Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);

function getUserInput(string $prompt){
    echo $prompt;
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    $input = trim($line);
    fclose($handle);
    return $input;
}

function filterPhoneNumber(object $elem) {
    $phoneNumber = (string) $elem["phone_number"];
    return $phoneNumber;
}

function searchAvailableNumbers(string $areaCode) {
   try {
       $telnyxResponse = AvailablePhoneNumber::All([
           "filter[national_destination_code]" => $areaCode,
           "filter[best_effort]" => true,
           "filter[limit]" => 2
       ]);
       $availableNumbers = array_map("filterPhoneNumber", $telnyxResponse["data"]);
       return $availableNumbers;
   } catch (Exception $e){
       echo "Caught exception: ",  $e->getMessage(), "\n";
       exit;
   }
}

function orderNumber(string $phoneNumber){
    try {
        $requestBody = [
            "phone_numbers" => [
                ["phone_number" => $phoneNumber]
            ]];
        $telnyxResponse = NumberOrder::Create($requestBody);
        $orderInformation = (object) [
            "orderId" => $telnyxResponse["id"],
            "phoneNumber" => $telnyxResponse["phone_numbers"][0]["phone_number"]
        ];
        echo "Order for phone number: {$orderInformation->phoneNumber} is {$telnyxResponse["status"]}\n";
        return $orderInformation;
   } catch (Exception $e){
        if ($e->getTelnyxCode() === "10015") {
            echo "Number {$phoneNumber} is not available for ordering\n";
        }
        else {
            echo "Caught exception: ", $e->getMessage(), "\n";
            exit;
        }
   }
}

function getPhoneNumberStatus(object $orderInformation){
    try {
        $telnyxResponse = PhoneNumber::Retrieve($orderInformation->phoneNumber);
        $orderInformation->phoneNumberStatus = $telnyxResponse["status"];
        $orderInformation->phoneNumberId = $telnyxResponse["id"];
        echo "Phone Number: {$orderInformation->phoneNumber} with id: {$orderInformation->phoneNumberId} status is: {$orderInformation->phoneNumberStatus}\n";
        return $orderInformation;
    } catch (Exception $e){
        echo "Caught exception: ",  $e->getMessage(), "\n";
        exit;
   }
}

function promptAndOrder(string $phoneNumber){
    $shouldOrder = getUserInput("(y/n) Would you like to order {$phoneNumber}?\n");
    if ($shouldOrder != "y"){
        echo "Ok, not ordering \n";
        return null;
    }
    else {
        $orderInformation = orderNumber($phoneNumber);
        return $orderInformation;
    }
}

function Main(){
    $areaCode = getUserInput("Which NPA (area code) would you like to get NXX counts?: ");
    $availableNumbers = searchAvailableNumbers($areaCode);
    $ordersInformation =  array_filter(array_map("promptAndOrder", $availableNumbers));
    $updatedOrdersInformation = array_map("getPhoneNumberStatus", $ordersInformation);
    print_r($updatedOrdersInformation);
}

Main();


