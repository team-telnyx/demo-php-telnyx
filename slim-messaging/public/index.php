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

// Add route

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Hello World');
    $response = $response->withStatus(200);
    return $response;
});

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
}); //->add($telnyxWebhookVerify)

$app->post('/messaging/outbound', function (Request $request, Response $response) {

    return $response->withStatus(200);
}); //->add($telnyxWebhookVerify);
$app->run();