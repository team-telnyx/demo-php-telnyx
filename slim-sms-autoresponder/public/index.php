<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;

require '../vendor/autoload.php';

$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);

$container = $app->getContainer();
$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('telnyx-demo-logger');
    $stdout_handler = new \Monolog\Handler\StreamHandler('php://stdout');
    $logger->pushHandler($stdout_handler);
    return $logger;
};

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
$dotenv->load();

$TELNYX_API_KEY = $_ENV['TELNYX_API_KEY'];
\Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);

function replyToSMS($preparedReply, $replyToTN, $thisRouter) {
    $telnyxSMSNumber = $_ENV['TELNYX_SMS_NUMBER'];
    $smsResponse = \Telnyx\Message::Create(['from' => $telnyxSMSNumber, 'to' => $replyToTN, 'text' => $preparedReply]);
    $thisRouter->logger->info($smsResponse);
}

function getPreparedReply($message) {
    switch($message) {
        case "ice cream":
            return "I prefer gelato";
            break;
        case "pizza":
            return "Chicago pizza is the best";
            break;
        default:
            return "Please send either the word 'pizza' or 'ice cream' for a different response";
    }
}

function processWebhook($data, $thisRouter) {
    $eventType = $data['event_type'];

    if ($eventType === 'message.received') {
        $text = $data['payload']['text'];
        $thisRouter->logger->info("Message received is \"" . $text ."\"");

        $text = preg_replace('/\s+/', ' ', $text);
        $text = strtolower($text);
        $text = trim($text);

        $preparedReply = getPreparedReply($text);
        $thisRouter->logger->info("Prepared reply is \"" . $preparedReply ."\"");
        
        $replyToTN = $data['payload']['from']['phone_number'];
        replyToSMS($preparedReply, $replyToTN, $thisRouter);
    }
}

$app->post('/webhooks', function (Request $request, Response $response) {
    try {
        $data = $request->getParsedBody()['data'];
        processWebhook($data, $this);
    } catch(Exception $e) {
        $this->logger->error($e->getMessage());
    } finally {
        return $response->withStatus(200);
    }
});

$app->run();
