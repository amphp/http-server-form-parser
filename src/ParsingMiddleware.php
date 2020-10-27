<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Server;
use Amp\Http\Server\ServerObserver;
use Amp\Http\Status;

final class ParsingMiddleware implements Middleware, ServerObserver
{
    private BufferingParser $parser;

    private ErrorHandler $errorHandler;

    public function __construct(int $fieldCountLimit = null)
    {
        $this->parser = new BufferingParser($fieldCountLimit);
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            $request->setAttribute(Form::class, $this->parser->parseForm($request));
        } catch (ParseException $exception) {
            return $this->errorHandler->handleError(Status::BAD_REQUEST, null, $request);
        }

        return $requestHandler->handleRequest($request);
    }

    public function onStart(Server $server): void
    {
        $this->errorHandler = $server->getErrorHandler();
    }

    public function onStop(Server $server): void
    {
        // Nothing to do.
    }
}
