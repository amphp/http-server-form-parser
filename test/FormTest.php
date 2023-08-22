<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser\Test;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\FormParser\BufferedFile;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\PHPUnit\AsyncTestCase;
use League\Uri\Http;

class FormTest extends AsyncTestCase
{
    public function testFormWithNumericFieldNames(): void
    {
        $form = new Form([
            12 => ["21"],
            "foo" => ["bar"],
        ]);

        $this->assertSame(["12", "foo"], $form->getNames());
        $this->assertSame("bar", $form->getValue("foo"));
        $this->assertSame(["bar"], $form->getValueArray("foo"));
        $this->assertNull($form->getValue("not_found_key"));
        $this->assertSame([
            12 => ["21"],
            "foo" => ["bar"],
        ], $form->getValues());
    }

    public function testFormWithFiles(): void
    {
        $file = new BufferedFile("file_path", "contents");

        $form = new Form([
            12 => ["12"],
        ], [
            "file" => [$file],
        ]);

        $this->assertSame(["file" => [$file]], $form->getFiles());
        $this->assertSame($file, $form->getFile("file"));
        $this->assertSame([$file], $form->getFileArray("file"));
        $this->assertNull($form->getFile("file_not_found"));
        $this->assertTrue($form->hasFile("file"));
    }

    public function testWwwFormUrlencoded(): void
    {
        $callback = $this->createCallback(1);

        $handler = new ClosureRequestHandler(function (Request $request) use ($callback): Response {
            $callback();

            $form = Form::fromRequest($request);

            $this->assertSame('bar', $form->getValue('foo'));
            $this->assertSame('y', $form->getValue('x'));

            return new Response;
        });

        $request = new Request($this->createMock(Client::class), 'GET', Http::createFromString('/'), [
            'content-type' => 'application/x-www-form-urlencoded',
        ], 'foo=bar&x=y');

        $handler->handleRequest($request);
    }

    public function testNonForm(): void
    {
        $handler = new ClosureRequestHandler(function (Request $request): Response {
            Form::fromRequest($request);

            $this->assertTrue($request->hasAttribute(Form::class)); // attribute is set either way
            $this->assertSame('{}', $request->getBody()->buffer());

            return new Response;
        });

        $request = new Request($this->createMock(Client::class), 'GET', Http::createFromString('/'), [
            'content-type' => 'application/json',
        ], '{}');

        $handler->handleRequest($request);
    }

    public function testNone(): void
    {
        $handler = new ClosureRequestHandler(function (Request $request): Response {
            $this->assertFalse($request->hasAttribute(Form::class));

            $form = Form::fromRequest($request);
            self::assertSame([], $form->getNames());

            $this->assertTrue($request->hasAttribute(Form::class)); // attribute is set either way

            return new Response;
        });

        $request = new Request($this->createMock(Client::class), 'GET', Http::createFromString('/'), [], '{}');

        $handler->handleRequest($request);
    }
}
