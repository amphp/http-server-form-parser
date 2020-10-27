<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\FormParser\BufferingParser;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\PHPUnit\AsyncTestCase;
use League\Uri\Http;

class BufferingParserTest extends AsyncTestCase
{
    public function testIssue6(): void
    {
        $body = "foobar=" . \urlencode("&");
        $request = new Request($this->createMock(Client::class), 'GET', Http::createFromString('/'), [], $body);
        $form = (new BufferingParser())->parseForm($request);
        \assert($form instanceof Form);

        $this->assertSame('&', $form->getValue('foobar'));
    }
}
