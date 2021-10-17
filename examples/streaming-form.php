#!/usr/bin/env php
<?php

require \dirname(__DIR__) . "/vendor/autoload.php";

use Amp\Http\Server\FormParser\StreamedField;
use Amp\Http\Server\FormParser\StreamingParser;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Options;
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

// Run this script, then visit http://localhost:1337/ in your browser.

$cert = new Socket\Certificate(__DIR__ . '/server.pem');

$context = (new Socket\BindContext)
        ->withTlsContext((new Socket\ServerTlsContext)->withDefaultCertificate($cert));

$servers = [
        Socket\Server::listen("0.0.0.0:1337"),
        Socket\Server::listen("[::]:1337"),
        Socket\Server::listen("0.0.0.0:1338", $context),
        Socket\Server::listen("[::]:1338", $context),
];

$logHandler = new StreamHandler(getStdout());
$logHandler->setFormatter(new ConsoleFormatter);
$logHandler->pushProcessor(new PsrLogMessageProcessor);

$logger = new Logger('server');
$logger->pushHandler($logHandler);

$server = new HttpServer($servers, new CallableRequestHandler(static function (Request $request): Response {
    if ($request->getUri()->getPath() === '/') {
        $html = "<html lang='en'><form action='/form' method='POST' enctype='multipart/form-data'><input type='file' name='test'><button type='submit'>submit</button></form>";

        return new Response(Status::OK, [
            "content-type" => "text/html; charset=utf-8",
        ], $html);
    }

    $request->getBody()->increaseSizeLimit(120 * 1024 * 1024);

    $parser = new StreamingParser;
    $fields = $parser->parseForm($request);

    /** @var StreamedField $field */
    while ($field = $fields->continue()) {
        if ($field->getName() === 'test') {
            $html = "<html lang='en'><a href='/'>← back</a><br>sha1: " . \sha1($field->buffer()) . "</html>";

            return new Response(Status::OK, [
                "content-type" => "text/html; charset=utf-8",
            ], $html);
        }
    }

    $html = "<html lang='en'><a href='/'>← back</a><br>Not found...</html>";

    return new Response(Status::OK, [
        "content-type" => "text/html; charset=utf-8",
    ], $html);
}), $logger, (new Options)->withRequestLogContext());

$server->start();

// Await SIGINT, SIGTERM, or SIGSTOP to be received.
$signal = Amp\trapSignal([\SIGINT, \SIGTERM, \SIGSTOP]);

$logger->info(\sprintf("Received signal %d, stopping HTTP server", $signal));

$server->stop();
