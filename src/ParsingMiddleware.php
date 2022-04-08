<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Status;

final class ParsingMiddleware implements Middleware
{
    private BufferingParser $parser;

    public function __construct(
        private ErrorHandler $errorHandler,
        ?int $fieldCountLimit = null,
    ) {
        $this->parser = new BufferingParser($fieldCountLimit);
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            $request->setAttribute(Form::class, $this->parser->parseForm($request));
        } catch (ParseException) {
            return $this->errorHandler->handleError(Status::BAD_REQUEST, request: $request);
        }

        return $requestHandler->handleRequest($request);
    }
}
