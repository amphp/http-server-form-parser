#!/usr/bin/env php
<?php

require \dirname(__DIR__) . "/vendor/autoload.php";

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Status;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use function Amp\ByteStream\getStdout;
use function Amp\Http\Server\FormParser\parseForm;

// Run this script, then visit http://localhost:1337/ in your browser.

Amp\Loop::run(static function () {
    $servers = [
        Socket\Server::listen("0.0.0.0:1337"),
        Socket\Server::listen("[::]:1337"),
    ];

    $logHandler = new StreamHandler(getStdout());
    $logHandler->setFormatter(new ConsoleFormatter);
    $logHandler->pushProcessor(new PsrLogMessageProcessor);

    $logger = new Logger('server');
    $logger->pushHandler($logHandler);

    $server = new HttpServer($servers, new CallableRequestHandler(static function (Request $request) {
        if ($request->getUri()->getPath() === '/') {
            $html = "<html lang='en'><form action='/form' method='POST'><input type='text' name='test'><button type='submit'>submit</button></form>";

            return new Response(Status::OK, [
                "content-type" => "text/html; charset=utf-8",
            ], $html);
        }

        /** @var Form $form */
        $form = yield parseForm($request);
        $html = "<html lang='en'><a href='/'>← back</a><br>" . \htmlspecialchars($form->getValue("test") ?? "Hello, World!") . '</html>';

        return new Response(Status::OK, [
            "content-type" => "text/html; charset=utf-8",
        ], $html);
    }), $logger);

    yield $server->start();

    // Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
    Amp\Loop::onSignal(\SIGINT, static function (string $watcherId) use ($server) {
        Amp\Loop::cancel($watcherId);
        yield $server->stop();
    });
});
