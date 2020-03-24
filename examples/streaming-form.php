#!/usr/bin/env php
<?php

require \dirname(__DIR__) . "/vendor/autoload.php";

use Amp\Http\Server\FormParser\StreamedField;
use Amp\Http\Server\FormParser\StreamingParser;
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
            $html = "<html lang='en'><form action='/form' method='POST' enctype='multipart/form-data'><input type='file' name='test'><button type='submit'>submit</button></form>";

            return new Response(Status::OK, [
                "content-type" => "text/html; charset=utf-8",
            ], $html);
        }

        $request->getBody()->increaseSizeLimit(120 * 1024 * 1024);

        $parser = new StreamingParser;
        $fields = $parser->parseForm($request);

        while (yield $fields->advance()) {
            /** @var StreamedField $field */
            $field = $fields->getCurrent();
            $bytes = yield $field->buffer();

            if ($field->getName() === 'test') {
                $html = "<html lang='en'><a href='/'>← back</a><br>sha1: " . \sha1($bytes) . "<html>";

                return new Response(Status::OK, [
                    "content-type" => "text/html; charset=utf-8",
                ], $html);
            }
        }

        $html = "<html lang='en'><a href='/'>← back</a><br>Not found...</html>";

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
