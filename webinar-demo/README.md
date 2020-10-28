<div align="center">

# Telnyx-PHP Webinar Example

![Telnyx](../logo-dark.png)

Sample application demonstrating Telnyx-PHP SMS

</div>

## Documentation & Tutorial

The full API documentation and tutorial is available on [developers.telnyx.com](https://developers.telnyx.com)

## Pre-Reqs

You will need to set up:

* [Telnyx Account](https://telnyx.com/sign-up?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link)
* [Telnyx Phone Number](https://portal.telnyx.com/#/app/numbers/my-numbers?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link) enabled with:
* Ability to receive webhooks (with something like [ngrok](https://developers.telnyx.com/docs/v2/development/ngrok?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link))
* [PHP & Composer](https://developers.telnyx.com/docs/v2/development/dev-env-setup?lang=php&utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link) installed

### Ngrok

This application is served on the port defined in the runtime environment (or in the `.env` file). Be sure to launch [ngrok](https://developers.telnyx.com/docs/v2/development/ngrok?utm_source=referral&utm_medium=github_referral&utm_campaign=cross-site-link) for that port

```
./ngrok http 8000
```

> Terminal should look _something_ like

```
ngrok by @inconshreveable                                                                                                                               (Ctrl+C to quit)

Session Status                online
Account                       Little Bobby Tables (Plan: Free)
Version                       2.3.35
Region                        United States (us)
Web Interface                 http://127.0.0.1:4040
Forwarding                    http://your-url.ngrok.io -> http://localhost:8000
Forwarding                    https://your-url.ngrok.io -> http://localhost:8000

Connections                   ttl     opn     rt1     rt5     p50     p90
                              0       0       0.00    0.00    0.00    0.00
```

At this point you can point your application to generated ngrok URL + path  (Example: `http://{your-url}.ngrok.io/messaging/inbound`).

### Create Messaging Profile

* Create and assign [messaging profile](https://portal.telnyx.com/#/app/messaging) to your Phone Number.
* Set the Webhook URL to your ngrok domain (Example: `http://{your-url}.ngrok.io/messaging/inbound`).
## Directory Setup

```
mkdir webinar-demo
cd webinar-demo
mkdir public
touch public/index.php
```

## Composer

```
composer require slim/slim
composer require slim/psr7
composer require telnyx/telnyx-php
composer require vlucas/phpdotenv
composer require jakeasmith/http_build_url
```

## Code

### Hello World Server up

```php
<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->run();
```

### Add Telnyx Routes

```php
$app->post('/messaging/inbound', function (Request $request, Response $response) {

    return $response->withStatus(200);
});

$app->post('/messaging/outbound', function (Request $request, Response $response) {

    return $response->withStatus(200);
});
```

* Send SMS to phone number

### Inbound Handler

#### Add Telnyx Creds

* Get API Key
* Copy .env File

```bash
TELNYX_PUBLIC_KEY="+/m2S/IoXv+AlI1/5a0="
TELNYX_API_KEY="KEY0.loremipsumttodothing"
TELNYX_APP_PORT=8000
```

#### Read .ENV and instansiate Tlenyx

```php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
$dotenv->load();

$TELNYX_API_KEY = $_ENV['TELNYX_API_KEY'];
$TELNYX_PUBLIC_KEY = $_ENV['TELNYX_PUBLIC_KEY'];
Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);
Telnyx\Telnyx::setPublicKey($TELNYX_PUBLIC_KEY);
```

#### Fill in request handler

```php
$requestStream = $request->getBody()->getContents();
$body = json_decode($requestStream);
$payload = $body->data->payload;
$toNumber = $payload->to[0]->phone_number;
$fromNumber = $payload->from->phone_number;
$dlrUrl = http_build_url([
    'scheme' => $request->getUri()->getScheme(),
    'host' => $request->getUri()->getHost(),
    'path' => '/messaging/outbound'
]);
try {
    $messageRequest = [
        'from' => $toNumber,
        'to' => $fromNumber,
        'text' => 'Hello, world!',
        'use_profile_webhooks' => false,
        'webhook_url' => $dlrUrl
    ];
    if (strtolower($payload->text) === 'dog') {
        $messageRequest['media_urls'] = ['https://telnyx-mms-demo.s3.us-east-2.amazonaws.com/small_dog.JPG'];
//            $messageRequest['media_urls'] = ['https://telnyx-mms-demo.s3.us-east-2.amazonaws.com/big_dog.JPG'];
    }
    $newMessage = Message::Create($messageRequest);
    $messageId = $newMessage->id;
    echo 'Sent message with ID: ', $messageId;
}
catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}
return $response->withStatus(200);
```

### Fill in outbound

```php
$requestStream = $request->getBody()->getContents();
$body = json_decode($requestStream);
$status = $body->data->payload->to[0]->status;
$messageId = $body->data->payload->id;
echo 'Received id: ', $messageId, ' Status: ', $status;
return $response->withStatus(200);
```

#### Final

```php
<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Telnyx\Message;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
$dotenv->load();

$TELNYX_API_KEY = $_ENV['TELNYX_API_KEY'];
$TELNYX_PUBLIC_KEY = $_ENV['TELNYX_PUBLIC_KEY'];
Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);
Telnyx\Telnyx::setPublicKey($TELNYX_PUBLIC_KEY);

$app = AppFactory::create();

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->addErrorMiddleware(true, true, true);


$app->post('/messaging/inbound', function (Request $request, Response $response) {
    $requestStream = $request->getBody()->getContents();
    $body = json_decode($requestStream);
    $payload = $body->data->payload;
    $toNumber = $payload->to[0]->phone_number;
    $fromNumber = $payload->from->phone_number;
    $dlrUrl = http_build_url([
        'scheme' => $request->getUri()->getScheme(),
        'host' => $request->getUri()->getHost(),
        'path' => '/messaging/outbound'
    ]);
    try {
        $messageRequest = [
            'from' => $toNumber,
            'to' => $fromNumber,
            'text' => 'Hello, world!',
            'use_profile_webhooks' => false,
            'webhook_url' => $dlrUrl
        ];
        if (strtolower($payload->text) === 'dog') {
            $messageRequest['media_urls'] = ['https://telnyx-mms-demo.s3.us-east-2.amazonaws.com/small_dog.JPG'];
//            $messageRequest['media_urls'] = ['https://telnyx-mms-demo.s3.us-east-2.amazonaws.com/big_dog.JPG'];
        }
        $newMessage = Message::Create($messageRequest);
        $messageId = $newMessage->id;
        echo 'Sent message with ID: ', $messageId;
    }
    catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
    return $response->withStatus(200);
});

$app->post('/messaging/outbound', function (Request $request, Response $response) {
    $requestStream = $request->getBody()->getContents();
    $body = json_decode($requestStream);
    $status = $body->data->payload->to[0]->status;
    $messageId = $body->data->payload->id;
    echo 'Received id: ', $messageId, ' Status: ', $status;
    return $response;
});


$app->run();
```


### Run

Start the server `php -S localhost:8000 -t public`

Once everything is setup, you should now be able to:
* Text your phone number and receive a response!
* Send a "dog" command to your phone number and get a picture right back!