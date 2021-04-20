<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '../.env');
$dotenv->load();

$TELNYX_API_KEY       = $_ENV['TELNYX_API_KEY'];
$TELNYX_PUBLIC_KEY    = $_ENV['TELNYX_PUBLIC_KEY'];


Telnyx\Telnyx::setApiKey($TELNYX_API_KEY);
Telnyx\Telnyx::setPublicKey($TELNYX_PUBLIC_KEY);
// Instantiate App
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Hello World');
    $response = $response->withStatus(200);
    return $response;
});

function handleCallInit(Telnyx\Call $call, Array $body){
    try {
        $call->answer();
    }
    catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), '\n';
    }
};

function handleCallAnswer(Telnyx\Call $call, Array $body){
    $GATHER_INTRO_FILE_URI = 'https://telnyx-mms-demo.s3.us-east-2.amazonaws.com/audio_clips/joke_intro.wav';
    try {
        $call->gather_using_audio([
            'audio_url' => $GATHER_INTRO_FILE_URI,
            'maximum_digits' => 1
        ]);
    }
    catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), '\n';
    }
};

function handleCallGatherEnded(Telnyx\Call $call, Array $body){
    $GATHER_PRESS_1_FILE_URI = 'https://telnyx-mms-demo.s3.us-east-2.amazonaws.com/audio_clips/clock.wav';
    $GATHER_PRESS_2_FILE_URI = 'https://telnyx-mms-demo.s3.us-east-2.amazonaws.com/audio_clips/timing.wav';
    $GATHER_INVALID_FILE_URI = 'https://telnyx-mms-demo.s3.us-east-2.amazonaws.com/audio_clips/invalid.wav';
    try {
        $digits = $body['data']['payload']['digits'];

        if ($digits == '1'){
            $call->playback_start([
                'audio_url' => $GATHER_PRESS_1_FILE_URI,
                'client_state' => base64_encode('Gather-Finished')
            ]);
        }
        elseif ($digits == '2'){
            $call->playback_start([
                'audio_url' => $GATHER_PRESS_2_FILE_URI,
                'client_state' => base64_encode('Gather-Finished')
            ]);
        }
        else {
            $call->playback_start([
                'audio_url' => $GATHER_INVALID_FILE_URI,
                'client_state' => base64_encode('Gather-Finished')
            ]);
        }
    }
    catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), '\n';
    }
};

function handleCallPlaybackEnded(Telnyx\Call $call, Array $body){
    try {
        $client_state = base64_decode($body['data']['payload']['client_state']);
        if ($client_state == 'Gather-Finished') {
            $call->hangup();
        }
    }
    catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), '\n';
    }
};

function handleCallOther(Telnyx\Call $call, Array $body){
    echo $body['data']['event_type'];
};

$app->post('/call-control/inbound', function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    $call_control_id = $body['data']['payload']['call_control_id'];
    $event_type = $body['data']['event_type'];
    $call = new Telnyx\Call($call_control_id);
    switch ($event_type) {
        case 'call.initiated':
            handleCallInit($call, $body);
            break;
        case 'call.answered':
            handleCallAnswer($call, $body);
            break;
        case 'call.gather.ended':
            handleCallGatherEnded($call, $body);
            break;
        case 'call.playback.ended':
            handleCallPlaybackEnded($call, $body);
            break;
        default:
            handleCallOther($call, $body);
            break;
    }
    return $response->withStatus(200);
});

$app->run();
