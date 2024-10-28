<?php
require 'vendor/autoload.php';

use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\Client\Connector as WsConnector;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Ratchet\WebSocket\WsServer;
use Ratchet\WebSocket\MessageComponentInterface;
use React\EventLoop\Factory as EventLoopFactory;
use React\Socket\SocketServer;
use Dotenv\Dotenv;

// Load environment variables from .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$OPENAI_API_KEY = getenv('OPENAI_API_KEY');
if (!$OPENAI_API_KEY) {
    echo 'Missing OpenAI API key. Please set it in the .env file.' . PHP_EOL;
    exit(1);
}

$SYSTEM_MESSAGE = 'You are a helpful and bubbly AI assistant who loves to chat about anything the user is interested about and is prepared to offer them facts.';
$VOICE = 'alloy';
$PORT = getenv('PORT') ? intval(getenv('PORT')) : 8000;

$LOG_EVENT_TYPES = [
    'response.content.done',
    'rate_limits.updated',
    'response.done',
    'input_audio_buffer.committed',
    'input_audio_buffer.speech_stopped',
    'input_audio_buffer.speech_started',
    'session.created'
];

// Create the event loop
$loop = EventLoopFactory::create();

// Create the HTTP server
$server = new HttpServer(function (ServerRequestInterface $request) {
    global $OPENAI_API_KEY, $SYSTEM_MESSAGE, $VOICE, $LOG_EVENT_TYPES;

    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    if ($path === '/' && $method === 'GET') {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['message' => 'Telnyx Media Stream Server is running!'])
        );
    }

    if ($path === '/inbound' && $method === 'POST') {
        echo "Incoming call received" . PHP_EOL;
        $headers = $request->getHeaders();

        $texml_path = __DIR__ . '/texml.xml';

        if (!file_exists($texml_path)) {
            echo "File not found at: {$texml_path}" . PHP_EOL;
            return new Response(
                500,
                ['Content-Type' => 'text/plain'],
                'TeXML file not found'
            );
        }

        $texml_response = file_get_contents($texml_path);
        $host = isset($headers['Host']) ? $headers['Host'][0] : 'localhost';
        $texml_response = str_replace('{host}', $host, $texml_response);
        echo "TeXML Response: {$texml_response}" . PHP_EOL;

        return new Response(
            200,
            ['Content-Type' => 'text/xml'],
            $texml_response
        );
    }

    return new Response(
        404,
        ['Content-Type' => 'text/plain'],
        'Not Found'
    );
});

$socket = new SocketServer("0.0.0.0:{$PORT}", $loop);
$server->listen($socket);

echo "HTTP server running at http://0.0.0.0:{$PORT}" . PHP_EOL;

// WebSocket Server
class MediaStream implements MessageComponentInterface
{
    protected $clients;
    protected $OPENAI_API_KEY;
    protected $SYSTEM_MESSAGE;
    protected $VOICE;
    protected $LOG_EVENT_TYPES;
    protected $loop;

    public function __construct($OPENAI_API_KEY, $SYSTEM_MESSAGE, $VOICE, $LOG_EVENT_TYPES, $loop)
    {
        $this->clients = new \SplObjectStorage;
        $this->OPENAI_API_KEY = $OPENAI_API_KEY;
        $this->SYSTEM_MESSAGE = $SYSTEM_MESSAGE;
        $this->VOICE = $VOICE;
        $this->LOG_EVENT_TYPES = $LOG_EVENT_TYPES;
        $this->loop = $loop;
    }

    public function onOpen(\Ratchet\ConnectionInterface $conn)
    {
        echo "Client connected" . PHP_EOL;
        $this->clients->attach($conn);

        // Start OpenAI websocket connection
        $connector = new WsConnector($this->loop);

        $headers = [
            'Authorization' => "Bearer {$this->OPENAI_API_KEY}",
            'OpenAI-Beta' => 'realtime=v1'
        ];

        $connector('wss://api.openai.com/v1/realtime?model=gpt-4o-realtime-preview-2024-10-01', [], $headers)->then(function (\Ratchet\Client\WebSocket $openai_ws) use ($conn) {
            $conn->openai_ws = $openai_ws;

            // Send session update
            $session_update = [
                "type" => "session.update",
                "session" => [
                    "turn_detection" => ["type" => "server_vad"],
                    "input_audio_format" => "g711_ulaw",
                    "output_audio_format" => "g711_ulaw",
                    "voice" => $this->VOICE,
                    "instructions" => $this->SYSTEM_MESSAGE,
                    "modalities" => ["text", "audio"],
                    "temperature" => 0.8,
                ]
            ];
            echo "Sending session update: " . json_encode($session_update) . PHP_EOL;
            $openai_ws->send(json_encode($session_update));

            // Receive messages from OpenAI
            $openai_ws->on('message', function (MessageInterface $message) use ($conn) {
                $data = $message->getPayload();
                $response = json_decode($data, true);
                if (isset($response['type']) && in_array($response['type'], $this->LOG_EVENT_TYPES)) {
                    echo "Received event: {$response['type']}" . PHP_EOL;
                }
                if (isset($response['type']) && $response['type'] === 'session.updated') {
                    echo "Session updated successfully: " . print_r($response, true) . PHP_EOL;
                }
                if (isset($response['type']) && $response['type'] === 'response.audio.delta' && isset($response['delta'])) {
                    $audio_delta = [
                        "event" => "media",
                        "media" => [
                            "payload" => $response["delta"]
                        ]
                    ];
                    $conn->send(json_encode($audio_delta));
                }
            });

            $openai_ws->on('close', function ($code = null, $reason = null) use ($conn) {
                echo "OpenAI websocket connection closed: {$code} - {$reason}" . PHP_EOL;
            });

        }, function (\Exception $e) {
            echo "Could not connect to OpenAI websocket: {$e->getMessage()}" . PHP_EOL;
        });
    }

    public function onMessage(\Ratchet\ConnectionInterface $from, $msg)
    {
        $message = json_decode($msg, true);
        $event_type = isset($message['event']) ? $message['event'] : null;

        if ($event_type === 'media') {
            if (isset($from->openai_ws) && $from->openai_ws->readyState === \Ratchet\Client\WebSocket::STATE_OPEN) {
                $base64_audio = $message['media']['payload'];
                $audio_append = [
                    "type" => "input_audio_buffer.append",
                    "audio" => $base64_audio
                ];
                $from->openai_ws->send(json_encode($audio_append));
            }
        } elseif ($event_type === 'start') {
            $stream_sid = $message['stream_id'];
            echo "Incoming stream has started: {$stream_sid}" . PHP_EOL;
        } else {
            echo "Received non-media event: {$event_type}" . PHP_EOL;
        }
    }

    public function onClose(\Ratchet\ConnectionInterface $conn)
    {
        echo "Client disconnected" . PHP_EOL;
        $this->clients->detach($conn);
        if (isset($conn->openai_ws)) {
            $conn->openai_ws->close();
        }
    }

    public function onError(\Ratchet\ConnectionInterface $conn, \Exception $e)
    {
        echo "WebSocket error: {$e->getMessage()}" . PHP_EOL;
        $conn->close();
    }
}

// WebSocket server setup
$ws_port = $PORT + 1; // Run WebSocket server on a different port
$mediaStream = new MediaStream($OPENAI_API_KEY, $SYSTEM_MESSAGE, $VOICE, $LOG_EVENT_TYPES, $loop);

$webSock = new SocketServer("0.0.0.0:{$ws_port}", $loop);

$webServer = new \Ratchet\Server\IoServer(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\Http\Router(function (Psr\Http\Message\RequestInterface $request) use ($mediaStream) {
            $path = $request->getUri()->getPath();
            if ($path === '/media-stream') {
                return new WsServer($mediaStream);
            } else {
                return new \Ratchet\Http\HttpServer(
                    new \Ratchet\Http\Middleware\CloseResponseMiddleware(
                        new Response(
                            404,
                            ['Content-Type' => 'text/plain'],
                            'Not Found'
                        )
                    )
                );
            }
        })
    ),
    $webSock,
    $loop
);

echo "WebSocket server running at ws://0.0.0.0:{$ws_port}/media-stream" . PHP_EOL;

// Run the event loop
$loop->run();