# Title

⏱ **30 minutes build time || [Github Repo]()**

## Introduction

Telnyx's messaging API supports both MMS and SMS messsages. Inbound multimedia messaging (MMS) messages include an attachment link in the webhook. The link and corresponding media should be treated as ephemeral and you should save any important media to a media storage (such as AWS S3) of your own.


## What you can do

At the end of this tutorial you'll have an application that:

* Receives an inbound message (SMS or MMS)
* Iterates over any media attachments and downloads the remote attachment locally
* Uploads the same attachment to AWS S3
* Sends the attachments back to the same phone number that originally sent the message


## Pre-reqs & technologies


* Completed or familiar with the [Receiving SMS & MMS Quickstart](docs/v2/messaging/quickstarts/receiving-sms-and-mms)
* A working [Messaging Profile](https://portal.telnyx.com/#/app/messaging) with a phone number enabled for SMS & MMS.
* [PHP](https://developers.telnyx.com/docs/v2/development/dev-env-setup?lang=php) installed with [Composer](https://getcomposer.org/)
* [Familiarity with Slim](http://www.slimframework.com/)
* Ability to receive webhooks (with something like [ngrok](docs/v2/development/ngrok))
* AWS Account setup with proper profiles and groups with IAM for S3. See the [Quickstart](https://aws.amazon.com/sdk-for-php/) for more information.
* Previously created S3 bucket with public permissions available.

## Setup


### Telnyx Portal configuration

Be sure to have a [Messaging Profile](https://portal.telnyx.com/#/app/messaging) with a phone number enabled for SMS & MMS and webhook URL pointing to your service (using ngrok or similar)

### Install packages via composer


```shell
composer require vlucas/phpdotenv
composer require telnyx/telnyx-php
composer require slim/http
composer require slim/psr7
composer require slim/slim
composer require aws/aws-sdk-php
composer require jakeasmith/http_build_url
```

This will create `composer.json` file with the packages needed to run the application.

### Setting environment variables

The following environmental variables need to be set

| Variable               | Description                                                                                                                                              |
|:-----------------------|:---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `TELNYX_API_KEY`       | Your [Telnyx API Key](https://portal.telnyx.com/#/app/api-keys?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link)              |
| `TELNYX_PUBLIC_KEY`    | Your [Telnyx Public Key](https://portal.telnyx.com/#/app/account/public-key?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link) |
| `TELNYX_APP_PORT`      | **Defaults to `8000`** The port the app will be served                                                                                                   |
| `AWS_PROFILE`          | Your AWS profile as set in `~/.aws`                                                                                                                      |
| `AWS_REGION`           | The region of your S3 bucket                                                                                                                             |
| `TELNYX_MMS_S3_BUCKET` | The name of the bucket to upload the media attachments                                                                                                   |

### .env file

This app uses the excellent [phpenv](https://github.com/vlucas/phpdotenv) package to manage environment variables.

Make a copy of the file below, add your credentials, and save as `.env` in the root directory.

```
TELNYX_API_KEY=
TELNYX_PUBLIC_KEY=
TENYX_APP_PORT=8000
AWS_PROFILE=
AWS_REGION=
TELNYX_MMS_S3_BUCKET=
```

## Code-along

Now create a folder public and a file in the public folderindex.php, then write the following to setup the telnyx library.

```
mkdir public
touch public/index.php
```

### Setup Slim Server and instantiate Telnyx

```php
<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Telnyx\Message;
use Telnyx\Webhook;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
$dotenv->load();

$TELNYX_API_KEY       = $_ENV['TELNYX_API_KEY'];
$TELNYX_PUBLIC_KEY    = $_ENV['TELNYX_PUBLIC_KEY'];
$AWS_REGION           = $_ENV['AWS_REGION'];
$TELNYX_MMS_S3_BUCKET = $_ENV['TELNYX_MMS_S3_BUCKET'];
$AWS_PROFILE          = $_ENV['AWS_PROFILE'];

Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);
Telnyx\Telnyx::setPublicKey($TELNYX_PUBLIC_KEY);
// Instantiate App
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(true, true, true);
```

## Receiving Webhooks

Now that you have setup your auth token, phone number, and connection, you can begin to use the API Library to send/receive SMS & MMS messages. First, you will need to setup an endpoint to receive webhooks for inbound messages & outbound message Delivery Receipts (DLR).

### Basic Routing & Functions

The basic overview of the application is as follows:

1. Verify webhook & create TelnyxEvent
2. Extract information from the webhook
3. Iterate over any media and download/re-upload to S3 for each attachment
4. Send the message back to the phone number from which it came
5. Acknowledge the status update (DLR) of the outbound message

#### Webhook validation middleware

Telnyx signs each webhook that can be validated by checking the signature with your public key. This example adds the verification step as middleware to be included on all Telnyx endpoints.  The [Webhooks Doc](docs/api/v2/overview#webhook-signing) elaborates more on how to check the headers and signature.

```php
//Callback signature verification
$telnyxWebhookVerify = function (Request $request, RequestHandler $handler) {
    //Extract the raw contents
    $payload = $request->getBody()->getContents();
    //Grab the signature
    $sigHeader = $request->getHeader('HTTP_TELNYX_SIGNATURE_ED25519')[0];
    //Grab the timestamp
    $timeStampHeader = $request->getHeader('HTTP_TELNYX_TIMESTAMP')[0];
    //Construct the Telnyx event which will validate the signature and timestamp
    $telnyxEvent = \Telnyx\Webhook::constructEvent($payload, $sigHeader, $timeStampHeader);
    //Add the event object to the request to keep context for future middleware
    $request = $request->withAttribute('telnyxEvent', $telnyxEvent);
    //Send to next middleware
    $response = $handler->handle($request);
    //return response back to Telnyx
    return $response;
};
```

ℹ️ For more details on middleware see [Slim's documentation on Route Middleware](http://www.slimframework.com/docs/v4/objects/routing.html#route-middleware)


### Media Download & Upload Functions

Before diving into the inbound message handler, first we'll create a few functions to manage our attachments.

* `downloadMedia` saves the content from a URL to disk
* `uploadMedia` uploads the file passed to AWS S3 (and makes object public)
* `downloadUpload` accepts an object and calls both the `downloadMedia` & `uploadMedia` returning the final S3 URL


```php
function downloadMedia(String $url){
    $fileName = basename($url);
    file_put_contents($fileName,file_get_contents($url));
    return $fileName;
}

function uploadMedia(String $fileLocation){
    global $AWS_REGION, $TELNYX_MMS_S3_BUCKET;
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $AWS_REGION
    ]);
    $keyName = basename($fileLocation);
    try {
        // Upload data.
        $result = $s3->putObject([
            'Bucket' => $TELNYX_MMS_S3_BUCKET,
            'Key'    => $keyName,
            'SourceFile' => $fileLocation,
            'ACL'    => 'public-read'
        ]);

        // The URL to the object.
        $url =  $result['ObjectURL'];
        return $url;
    } catch (S3Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
}

function downloadUpload($media) {
    $fileLocation = downloadMedia($media['url']);
    $mediaUrl = uploadMedia($fileLocation);
    return $mediaUrl;
}
```

### Inbound Message Handling

Now that we have the functions to manage the media, we can start receiving inbound MMS's

The flow of our function is (at a high level):
1. Extract relevant information from the webhook
2. Build the `webhook_url` to direct the DLR to a new endpoint
3. Iterate over any attachments/media and call our `downloadUpload` function
4. Send the outbound message back to the original sender with the media attachments


```php
$app->post('/messaging/inbound', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    $payload = $body['data']['payload'];
    $toNumber = $payload['to'][0]['phone_number'];
    $fromNumber = $payload['from']['phone_number'];
    $medias = $payload['media'];
    $dlrUrl = http_build_url([
        'scheme' => $request->getUri()->getScheme(),
        'host' => $request->getUri()->getHost(),
        'path' => '/messaging/outbound'
    ]);
    $mediaUrls = array_map('downloadUpload', $medias);
    try {
        $new_message = Message::Create([
            'from' => $toNumber,
            'to' => $fromNumber,
            'text' => 'Hello, world!',
            'media_urls' => $mediaUrls,
            'use_profile_webhooks' => false,
            'webhook_url' => $dlrUrl
            ]);
        $messageId = $new_message->id;
        echo 'Sent message with ID: ', $messageId;
    }
    catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
    return $response->withStatus(200);
})->add($telnyxWebhookVerify);
```

### Inbound Message Handling

As we defined our `webhook_url` path to be `/messaging/outbound` we'll need to create a function that accepts a POST request to that path.

```php
$app->post('/messaging/outbound', function (Request $request, Response $response) {
    // Handle outbound DLR
    return $response->withStatus(200);
})->add($telnyxWebhookVerify);
```

### Final index.php

All together the PHP samples should look something like:

```php
<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Telnyx\Message;
use Telnyx\Webhook;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
$dotenv->load();

$TELNYX_API_KEY       = $_ENV['TELNYX_API_KEY'];
$TELNYX_PUBLIC_KEY    = $_ENV['TELNYX_PUBLIC_KEY'];
$AWS_REGION           = $_ENV['AWS_REGION'];
$TELNYX_MMS_S3_BUCKET = $_ENV['TELNYX_MMS_S3_BUCKET'];
$AWS_PROFILE          = $_ENV['AWS_PROFILE'];

Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);
Telnyx\Telnyx::setPublicKey($TELNYX_PUBLIC_KEY);
// Instantiate App
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

//Callback signature verification
$telnyxWebhookVerify = function (Request $request, RequestHandler $handler) {
    $payload = $request->getBody()->getContents();
    $sigHeader = $request->getHeader('HTTP_TELNYX_SIGNATURE_ED25519')[0];
    $timeStampHeader = $request->getHeader('HTTP_TELNYX_TIMESTAMP')[0];
    $telnyxEvent = Webhook::constructEvent($payload, $sigHeader, $timeStampHeader);
    $request = $request->withAttribute('telnyxEvent', $telnyxEvent);
    $response = $handler->handle($request);
    return $response;
};

function downloadMedia(String $url){
    $fileName = basename($url);
    file_put_contents($fileName,file_get_contents($url));
    return $fileName;
}

function uploadMedia(String $fileLocation){
    global $AWS_REGION, $TELNYX_MMS_S3_BUCKET;
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $AWS_REGION
    ]);
    $keyName = basename($fileLocation);
    try {
        // Upload data.
        $result = $s3->putObject([
            'Bucket' => $TELNYX_MMS_S3_BUCKET,
            'Key'    => $keyName,
            'SourceFile' => $fileLocation,
            'ACL'    => 'public-read'
        ]);

        // Print the URL to the object.
        $url =  $result['ObjectURL'];
        return $url;
    } catch (S3Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
}

function downloadUpload($media) {
    $fileLocation = downloadMedia($media['url']);
    $mediaUrl = uploadMedia($fileLocation);
    return $mediaUrl;
}

// Add routes
$app->post('/messaging/inbound', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    $payload = $body['data']['payload'];
    $toNumber = $payload['to'][0]['phone_number'];
    $fromNumber = $payload['from']['phone_number'];
    $medias = $payload['media'];
    $dlrUrl = http_build_url([
        'scheme' => $request->getUri()->getScheme(),
        'host' => $request->getUri()->getHost(),
        'path' => '/messaging/outbound'
    ]);
    $mediaUrls = array_map('downloadUpload', $medias);
    try {
        $new_message = Message::Create([
            'from' => $toNumber,
            'to' => $fromNumber,
            'text' => 'Hello, world!',
            'media_urls' => $mediaUrls,
            'use_profile_webhooks' => false,
            'webhook_url' => $dlrUrl
            ]);
        $messageId = $new_message->id;
        echo 'Sent message with ID: ', $messageId;
    }
    catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
    return $response->withStatus(200);
})->add($telnyxWebhookVerify);

$app->post('/messaging/outbound', function (Request $request, Response $response) {
    // Handle outbound DLR
    return $response->withStatus(200);
})->add($telnyxWebhookVerify);
$app->run();
```

## Usage


Start the server `php -S localhost:8000 -t public`

When you are able to run the server locally, the final step involves making your application accessible from the internet. So far, we've set up a local web server. This is typically not accessible from the public internet, making testing inbound requests to web applications difficult.

The best workaround is a tunneling service. They come with client software that runs on your computer and opens an outgoing permanent connection to a publicly available server in a data center. Then, they assign a public URL (typically on a random or custom subdomain) on that server to your account. The public server acts as a proxy that accepts incoming connections to your URL, forwards (tunnels) them through the already established connection and sends them to the local web server as if they originated from the same machine. The most popular tunneling tool is `ngrok`. Check out the [ngrok setup](/docs/v2/development/ngrok) walkthrough to set it up on your computer and start receiving webhooks from inbound messages to your newly created application.

Once you've set up `ngrok` or another tunneling service you can add the public proxy URL to your Inbound Settings  in the Mission Control Portal. To do this, click  the edit symbol [✎] next to your Messaging Profile. In the "Inbound Settings" > "Webhook URL" field, paste the forwarding address from ngrok into the Webhook URL field. Add `messaging/inbound` to the end of the URL to direct the request to the webhook endpoint in your slim-php server.

For now you'll leave “Failover URL” blank, but if you'd like to have Telnyx resend the webhook in the case where sending to the Webhook URL fails, you can specify an alternate address in this field.

Once everything is setup, you should now be able to:
* Text your phone number and receive a response!
* Send a picture to your phone number and get that same picture right back!

