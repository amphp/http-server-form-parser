<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\FormParser\ParsingMiddleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use League\Uri;
use function Amp\Http\Server\Middleware\stack;

class ParsingMiddlewareTest extends AsyncTestCase
{
    public function testWwwFormUrlencoded(): Promise
    {
        $callback = $this->createCallback(1);

        $handler = stack(new CallableRequestHandler(function (Request $request) use ($callback) {
            if ($request->hasAttribute(Form::class)) {
                $callback();

                $form = $request->getAttribute(Form::class);

                $this->assertSame('bar', $form->getValue('foo'));
                $this->assertSame('y', $form->getValue('x'));
            }
        }), new ParsingMiddleware);

        $request = new Request($this->createMock(Client::class), 'GET', Uri\Http::createFromString('/'), [
            'content-type' => 'application/x-www-form-urlencoded',
        ], 'foo=bar&x=y');

        return $handler->handleRequest($request);
    }

    public function testNonForm(): Promise
    {
        $handler = stack(new CallableRequestHandler(function (Request $request) {
            $this->assertTrue($request->hasAttribute(Form::class)); // attribute is set either way
            $this->assertSame('{}', yield $request->getBody()->buffer());
        }), new ParsingMiddleware);

        $request = new Request($this->createMock(Client::class), 'GET', Uri\Http::createFromString('/'), [
            'content-type' => 'application/json',
        ], '{}');

        return $handler->handleRequest($request);
    }

    public function testNone(): Promise
    {
        $handler = stack(new CallableRequestHandler(function (Request $request) {
            $this->assertTrue($request->hasAttribute(Form::class)); // attribute is set either way
            $this->assertSame('{}', yield $request->getBody()->buffer());
        }), new ParsingMiddleware);

        $request = new Request($this->createMock(Client::class), 'GET', Uri\Http::createFromString('/'), [], '{}');

        return $handler->handleRequest($request);
    }
}
