# Title

⏱ **30 minutes build time **

## Introduction

Telnyx's phone number inventory API supports a rich feature set of searching [filters](docs/v2/numbers/number-lookup) and [ordering](docs/v2/numbers/quickstarts/number-orders) API. This tutorial walks through searching then ordering phone numbers.

## What you can do

At the end of this tutorial you'll have an application that:

* Prompts user for Area Code input (US Only)
* Searches the inventory API for phone numbers in that area code
* Prompts user to order numbers
* Orders the number
* Check the number order status based on the phone number

## Pre-reqs & technologies

* Completed or familiar with the [Portal Setup Guide](docs/v2/numbers/quickstarts/portal-setup)
* Familiar with [searching](docs/v2/numbers/quickstarts/number-search) and [ordering](docs/v2/numbers/quickstarts/number-orders) phone numbers.
* [PHP](docs/v2/development/dev-env-setup?lang=php) installed with [Composer](https://getcomposer.org/)

## Setup


### Telnyx Portal configuration

Be sure to have you [portal](https://portal.telnyx.com/#/app/messaging) active with a v2 [API Key](https://portal.telnyx.com/#/app/api-keys).

### Install packages via composer


```shell
composer require vlucas/phpdotenv
composer require telnyx/telnyx-php
```

This will create `composer.json` file with the packages needed to run the application.

### Setting environment variables

The following environmental variables need to be set

| Variable               | Description                                                                                                                                              |
|:-----------------------|:---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `TELNYX_API_KEY`       | Your [Telnyx API Key](https://portal.telnyx.com/#/app/api-keys)              |
| `TELNYX_PUBLIC_KEY`    | Your [Telnyx Public Key](https://portal.telnyx.com/#/app/account/public-key) |

### .env file

This app uses the excellent [phpenv](https://github.com/vlucas/phpdotenv) package to manage environment variables.

Make a copy of the file below, add your credentials, and save as `.env` in the root directory.

```
TELNYX_API_KEY=
TELNYX_PUBLIC_KEY=
```

## Code-along

Now create a file in the folder named `app.php`, then write the following to setup the telnyx library.

```shell
$ touch app.php
```

The high level order of operations:

1. Prompt user for area code input
2. Search Telnyx inventory based on input
3. Filter the Telnyx response object for "phone_number"
4. Prompt the user to order each phone number returned from Telnyx
5. Attempt to order each phone number and check for error-ed response
6. Check the "phone number" status and get the phone number uuid to use for [Configuration](docs/api/v2/numbers/Number-Configurations)
7. Print response object to console.

### Setup app.php and instantiate Telnyx

```php
<?php

use Telnyx\AvailablePhoneNumber;
use Telnyx\NumberOrder;
use Telnyx\PhoneNumber;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$TELNYX_API_KEY = getenv("TELNYX_API_KEY");
Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);
```

## Gathering User Input

This application runs in the console and has basic user input and output. The function `getUserInput` captures the input.

```php
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
```

## Searching & filtering phone number response

We'll create a function `searchAvailableNumbersByAreaCode` to abstract away the searching from the "Main method". This should lend itself to be easily replaced with other search filters.

The response object contains an object "data" with an array of phone number responses. The `filterPhoneNumber` function takes one of the data elements and returns the `phone_number` in e164 (+19198675309) format.

The [Number Search](docs/v2/numbers/quickstarts/number-search) guide covers searching in more detail.


```php
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
```


## Ordering the Phone Number

To better supplement the ordering process, we'll create a few helper class and functions

### Get user input function

Function `promptAndOrder` will prompt the user and create the order

```php
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
```


### PhoneNumberOrder class

Class `PhoneNumberOrder` will hold the  relevant phone number information and uuids associated with ordering a phone number. The `PhoneNumberOrder` class is a good model for the important data to save for later inventory management such as deleting numbers, moving applications, or changing voice settings.

```php
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
```

### Sending order request to Telnyx

The Function `orderNumber` submits the order for a single number to Telnyx.

Note that a successful response from the API **does not** necessarily indicate that the order will be successful. This application will check the phone number status for updates.

Once the order has been successfully created, the function returns a new `PhoneNumberOrder` with the relevant information parsed from the response.

See more information about ordering numbers in the [Number Orders Guide](docs/v2/numbers/quickstarts/number-orders).

The error code `"10015"` indicates that the number is not available for ordering. This typically happens when another user has ordered the phone number between searching and submitting the order.

```php
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
```

## Check phone number status

We'll use the ability to fetch [phone number settings](docs/api/v2/numbers/Number-Configurations#getPhoneNumber) by using the e164 string returned from the order response to check the status of the phone number and pull the uuid.

We'll update the `PhoneNumberOrder` class with the information from the API to save away for future configuration updates.

```php
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
```

## Putting all together

Phone number searching in Telnyx will always return an array (even for a singular response). Your application should expect to deal with iterating over empty response.

The "Main" function will call the various functions to build a console application to search and order phone numbers.

```php
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
```

## Final app.php

All together the application should look something like:

```php
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

```

## Usage

Ensure your environment variables are set in the `.env` file and run the application from your console:

```shell
$ php app.php

Which NPA (area code) would you like to search?: 989
(y/n) Would you like to order +19892035115?
y
Order for phone number: +19892035115 is pending
(y/n) Would you like to order +19892035116?
y
Order for phone number: +19892035116 is pending
Phone Number: +19892035115 with id: 1466051994811630657 status is: active
Phone Number: +19892035116 with id: 1466052015145616451 status is: purchase-pending
Array
(
    [0] => PhoneNumberOrder Object
        (
            [orderId] => c33fa3e5-2a23-49d6-b0c8-98fa5e596dd4
            [phoneNumber] => +19892035115
            [phoneNumberStatus] => active
            [phoneNumberId] => 1466051994811630657
        )

    [1] => PhoneNumberOrder Object
        (
            [orderId] => 0f6cdce1-e259-41a5-ad92-8c16cf4a192b
            [phoneNumber] => +19892035116
            [phoneNumberStatus] => purchase-pending
            [phoneNumberId] => 1466052015145616451
        )

)
```


Once everything is setup, you should now be able to:
* Search for phone numbers
* Order those numbers (if you'd like)
* Get the phone number status

