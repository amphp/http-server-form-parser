#!/usr/bin/env php
<?php

require \dirname(__DIR__) . "/vendor/autoload.php";

use Amp\ByteStream;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\FormParser\StreamedField;
use Amp\Http\Server\FormParser\StreamingParser;
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

$server = new SocketHttpServer($logger);

$server->expose(new Socket\InternetAddress("0.0.0.0", 1337));
$server->expose(new Socket\InternetAddress("[::]", 1337));

$server->start(new ClosureRequestHandler(static function (Request $request): Response {
    if ($request->getUri()->getPath() === '/') {
        $html = <<<HTML
        <html lang="en">
            <body>
                <form action="/form" method="POST" enctype="multipart/form-data">
                    <input type="file" name="test">
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

    $request->getBody()->increaseSizeLimit(120 * 1024 * 1024);

    $parser = new StreamingParser;
    $fields = $parser->parseForm($request);

    /** @var StreamedField $field */
    while ($fields->continue()) {
        $field = $fields->getValue();
        if ($field->getName() === 'test') {
            $html = '<html lang="en"><body><a href="/">← back</a><br>sha1: ' . \sha1($field->buffer()) . '</body></html>';

            return new Response(
                status: HttpStatus::OK,
                headers: ["content-type" => "text/html; charset=utf-8"],
                body: $html,
            );
        }
    }

    $html = '<html lang="en"><body><a href="/">← back</a><br>Uploaded file not found...</body></html>';

    return new Response(
        status: HttpStatus::NOT_FOUND,
        headers: ["content-type" => "text/html; charset=utf-8"],
        body: $html,
    );
}), new DefaultErrorHandler());

// Await SIGINT, SIGTERM, or SIGSTOP to be received.
$signal = Amp\trapSignal([\SIGINT, \SIGTERM, \SIGSTOP]);

$logger->info(\sprintf("Received signal %d, stopping HTTP server", $signal));

$server->stop();
