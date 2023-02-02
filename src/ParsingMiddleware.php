<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

use Amp\Http\HttpStatus;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;

final class ParsingMiddleware implements Middleware
{
    private readonly BufferingParser $parser;

    public function __construct(
        private readonly ErrorHandler $errorHandler,
        private readonly ?int $bodySizeLimit = null,
        ?int $fieldCountLimit = null,
    ) {
        $this->parser = new BufferingParser($fieldCountLimit);
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            $request->setAttribute(Form::class, $this->parser->parseForm($request, $this->bodySizeLimit));
        } catch (ParseException) {
            return $this->errorHandler->handleError(HttpStatus::BAD_REQUEST, request: $request);
        }

        return $requestHandler->handleRequest($request);
    }
}
