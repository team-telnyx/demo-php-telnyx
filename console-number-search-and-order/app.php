<?php

use Telnyx\AvailablePhoneNumber;
use Telnyx\NumberOrder;
use Telnyx\PhoneNumber;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$TELNYX_API_KEY = getenv("TELNYX_API_KEY");
Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);

/**
 * Prompts user for input from the console.
 * @param string $prompt Sentence to display to user
 * @return string Trimmed response in *lowercase* from user.
 */
function getUserInput(string $prompt) : string {
    echo $prompt;
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    $input = trim($line);
    fclose($handle);
    return strtolower($input);
}

/**
 * Filter function to parse from an object containing a "phone_number" element
 *
 * @param object $elem Inner object from Telnyx Response
 * @return string The e164 "+19198675309" style phone number
 */
function filterPhoneNumber(object $elem) : string {
    $phoneNumber = (string) $elem["phone_number"];
    return $phoneNumber;
}

/**
 * Search the available numbers by area code. Will die on error.
 * @param string $areaCode The area code to search Telnyx inventory
 * @return array An array of e164 "+19198675309" formatted phone_number strings from inventory
 */
function searchAvailableNumbersByAreaCode(string $areaCode) : array {
   try {
       $telnyxResponse = AvailablePhoneNumber::All([
           "filter[national_destination_code]" => $areaCode,
           "filter[best_effort]" => true,
           "filter[limit]" => 2
       ]);
       $availableNumbers = array_map("filterPhoneNumber", $telnyxResponse["data"]);
       if (empty($availableNumbers)) {
           echo "No results found for {$areaCode}, quitting\n";
           exit;
       }
       return $availableNumbers;
   } catch (Exception $e){
       echo "Caught exception: ",  $e->getMessage(), "\n";
       exit;
   }
}

/**
 * Prompt user via console to order a phone number and orders or passes
 * @param string $phoneNumber e164 formatted phone number "+19198675309" to display and order
 * @return PhoneNumberOrder object containing the order id and phone number
 */
function promptAndOrder(string $phoneNumber) : ?PhoneNumberOrder {
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


/**
 * Class orderResponse holds information about a Telnyx Order
 */
class PhoneNumberOrder {
    public string $orderId;
    public string $phoneNumber;
    public string $phoneNumberStatus;
    public string $phoneNumberId;

    /**
     * orderResponse constructor.
     * @param string $orderId *orderId* from the Telnyx order
     * @param string $phoneNumber the e164 formatted "+19198675309" phone number
     */
    function __construct(string $orderId, string $phoneNumber){
        $this->orderId = $orderId;
        $this->phoneNumber = $phoneNumber;
    }
}

/**
 * Order a single phone number from Telynx.
 * @param string $phoneNumber The desired phone number to order in e164 format "+19198675309"
 * @return PhoneNumberOrder An object with "orderId" and "phoneNumber" in e164 format "+19198675309" from the order
 */
function orderNumber(string $phoneNumber) : ?PhoneNumberOrder {
    try {
        $requestBody = [
            "phone_numbers" => [
                ["phone_number" => $phoneNumber]
            ]];
        $telnyxResponse = NumberOrder::Create($requestBody);
        $orderInformation = new PhoneNumberOrder(
            $telnyxResponse["id"],
            $telnyxResponse["phone_numbers"][0]["phone_number"]);
        echo "Order for phone number: {$orderInformation->phoneNumber} is {$telnyxResponse["status"]}\n";
        return $orderInformation;
   } catch (Exception $e){
        if ($e->getTelnyxCode() === "10015") {
            echo "Number {$phoneNumber} is not available for ordering\n";
            return null;
        }
        else {
            echo "Caught exception: ", $e->getMessage(), "\n";
            exit;
        }
   }
}

/**
 * Fetches the status and telnyx-id of the phone number by e164 string "+19198675309"
 * @param PhoneNumberOrder $orderInformation Response from the orderNumber function
 * @return PhoneNumberOrder updates the phone number status and the phone number id
 */
function getPhoneNumberStatus(PhoneNumberOrder $orderInformation): PhoneNumberOrder{
    try {
        $telnyxResponse = PhoneNumber::Retrieve($orderInformation->phoneNumber);
        $orderInformation->phoneNumberStatus = (string) $telnyxResponse["status"];
        $orderInformation->phoneNumberId = (string) $telnyxResponse["id"];
        echo "Phone Number: {$orderInformation->phoneNumber} with id: {$orderInformation->phoneNumberId} status is: {$orderInformation->phoneNumberStatus}\n";
        return $orderInformation;
    } catch (Exception $e){
        echo "Caught exception: ",  $e->getMessage(), "\n";
        exit;
   }
}

/**
 * Entry point for the program
 */
function Main(){
    $areaCode = getUserInput("Which NPA (area code) would you like to search?: ");
    $availableNumbers = searchAvailableNumbersByAreaCode($areaCode);
    $ordersInformation =  array_filter(array_map("promptAndOrder", $availableNumbers));
    $updatedOrdersInformation = array_map("getPhoneNumberStatus", $ordersInformation);
    print_r($updatedOrdersInformation);
}

Main();
