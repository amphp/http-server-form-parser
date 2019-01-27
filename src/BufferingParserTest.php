<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\Request;
use function Amp\Promise\wait;
use League\Uri\Http;
use PHPUnit\Framework\TestCase;

class BufferingParserTest extends TestCase
{
    public function testIssue6()
    {
        $body = "foobar=" . \urlencode("&");
        $request = new Request($this->createMock(Client::class), 'GET', Http::createFromString('/'), [], $body);
        $form = wait((new BufferingParser)->parseForm($request));

        $this->assertSame('&', $form->getValue('foobar'));
    }

}
