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
    $response->getBody()->write("🌊 Hello world!");
    return $response;
});

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
    return $response->withStatus(200);
});

$app->run();