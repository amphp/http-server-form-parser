<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\FormParser\ParsingMiddleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\PHPUnit\AsyncTestCase;
use League\Uri;
use function Amp\Http\Server\Middleware\stack;

class ParsingMiddlewareTest extends AsyncTestCase
{
    public function testWwwFormUrlencoded(): void
    {
        $callback = $this->createCallback(1);

        $handler = stack(new CallableRequestHandler(function (Request $request) use ($callback): Response {
            if ($request->hasAttribute(Form::class)) {
                $callback();

                $form = $request->getAttribute(Form::class);

                $this->assertSame('bar', $form->getValue('foo'));
                $this->assertSame('y', $form->getValue('x'));
            }

            return new Response;
        }), new ParsingMiddleware);

        $request = new Request($this->createMock(Client::class), 'GET', Uri\Http::createFromString('/'), [
            'content-type' => 'application/x-www-form-urlencoded',
        ], 'foo=bar&x=y');

        $handler->handleRequest($request);
    }

    public function testNonForm(): void
    {
        $handler = stack(new CallableRequestHandler(function (Request $request): Response {
            $this->assertTrue($request->hasAttribute(Form::class)); // attribute is set either way
            $this->assertSame('{}', $request->getBody()->buffer());
            return new Response;
        }), new ParsingMiddleware);

        $request = new Request($this->createMock(Client::class), 'GET', Uri\Http::createFromString('/'), [
            'content-type' => 'application/json',
        ], '{}');

        $handler->handleRequest($request);
    }

    public function testNone(): void
    {
        $handler = stack(new CallableRequestHandler(function (Request $request): Response {
            $this->assertTrue($request->hasAttribute(Form::class)); // attribute is set either way
            $this->assertSame('{}', $request->getBody()->buffer());
            return new Response;
        }), new ParsingMiddleware);

        $request = new Request($this->createMock(Client::class), 'GET', Uri\Http::createFromString('/'), [], '{}');

        $handler->handleRequest($request);
    }
}
