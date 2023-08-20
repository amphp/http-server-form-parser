#!/usr/bin/env php
<?php

require \dirname(__DIR__) . "/vendor/autoload.php";

use Amp\ByteStream;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\FormParser;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

// Run this script, then visit http://localhost:1337/ in your browser.

$logHandler = new StreamHandler(ByteStream\getStdout());
$logHandler->pushProcessor(new PsrLogMessageProcessor());
$logHandler->setFormatter(new ConsoleFormatter());
$logger = new Logger('server');
$logger->pushHandler($logHandler);

$server = SocketHttpServer::createForDirectAccess($logger);

$server->expose(new Socket\InternetAddress("0.0.0.0", 1337));
$server->expose(new Socket\InternetAddress("[::]", 1337));

$server->start(new ClosureRequestHandler(static function (Request $request): Response {
    if ($request->getUri()->getPath() === '/') {
        $html = <<<HTML
        <html lang="en">
            <body>
                <form action="/form" method="POST">
                    <input type="text" name="test" placeholder="Name">
                    <button type="submit">submit</button>
                </form>
            </body>
        </html>
        HTML;

        return new Response(
            status: HttpStatus::OK,
            headers: ["content-type" => "text/html; charset=utf-8"],
            body: $html,
        );
    }

    $form = FormParser\parseForm($request);
    $html = <<<HTML
    <html lang="en">
        <body>
            <div><a href="/">← back</a><br>Hello, {input}!</div>
        </body>
    </html>
    HTML;

    return new Response(
        status: HttpStatus::OK,
        headers: ["content-type" => "text/html; charset=utf-8"],
        body: \str_replace('{input}', \htmlspecialchars($form->getValue("test") ?? "World"), $html)
    );
}), new DefaultErrorHandler());

// Await SIGINT, SIGTERM, or SIGSTOP to be received.
$signal = Amp\trapSignal([\SIGINT, \SIGTERM]);

$logger->info(\sprintf("Received signal %d, stopping HTTP server", $signal));

$server->stop();
