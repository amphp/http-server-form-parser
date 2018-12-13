<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Server;
use Amp\Http\Server\ServerObserver;
use Amp\Http\Status;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

class ParsingMiddleware implements Middleware, ServerObserver
{
    /** @var BufferingParser */
    private $parser;

    /** @var ErrorHandler */
    private $errorHandler;

    public function __construct(int $fieldCountLimit = null)
    {
        $this->parser = new BufferingParser($fieldCountLimit);
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Promise
    {
        return call(function () use ($request, $requestHandler) {
            try {
                $request->setAttribute(Form::class, yield $this->parser->parseForm($request));
            } catch (ParseException $exception) {
                return yield $this->errorHandler->handleError(Status::BAD_REQUEST, null, $request);
            }

            return yield $requestHandler->handleRequest($request);
        });
    }

    public function onStart(Server $server): Promise
    {
        $this->errorHandler = $server->getErrorHandler();
        return new Success;
    }

    public function onStop(Server $server): Promise
    {
        return new Success;
    }
}
