<?php declare(strict_types=1);

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
        $request = new Request(
            client: $this->createMock(Client::class),
            method: 'GET',
            uri: Http::createFromString('/'),
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            body: $body,
        );
        $form = (new BufferingParser())->parseForm($request);
        \assert($form instanceof Form);

        $this->assertSame('&', $form->getValue('foobar'));
    }
}
