<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\FormParser\StreamingParser;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestBody;
use Amp\Iterator;
use League\Uri;
use PHPUnit\Framework\TestCase;
use function Amp\call;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Promise\wait;

class ParsingTest extends TestCase
{
    /**
     * @param string $header
     * @param string $data
     * @param array  $fields
     * @param array  $files
     *
     * @dataProvider requestBodies
     */
    public function testBufferedDecoding(string $header, string $data, array $fields, array $files)
    {
        $headers = [];
        $headers["content-type"] = [$header];
        $body = new RequestBody(new InMemoryStream($data));

        $client = $this->createMock(Client::class);
        $request = new Request($client, "POST", Uri\Http::createFromString("/"), $headers, $body);

        wait(call(function () use ($request, $fields, $files) {
            /** @var Form $form */
            $form = yield parseForm($request);

            foreach ($fields as $key => $value) {
                $this->assertSame($form->getValueArray($key), $value);
            }

            foreach ($files as $fieldName => $expectedFiles) {
                $this->assertTrue($form->hasFile($fieldName));
                $parsedFiles = $form->getFileArray($fieldName);
                $this->assertCount(\count($expectedFiles), $parsedFiles);

                foreach ($parsedFiles as $key => $parsedFile) {
                    $this->assertSame($expectedFiles[$key]["filename"], $parsedFile->getName());
                    $this->assertSame($expectedFiles[$key]["mime"], $parsedFile->getMimeType());
                    $this->assertSame($expectedFiles[$key]["content"], $parsedFile->getContents());
                }
            }
        }));
    }

    public function requestBodies(): array
    {
        $return = [];

        // 0 --- basic request -------------------------------------------------------------------->

        $input = "a=b&c=d&e=f&e=g";

        $return[] = ["application/x-www-form-urlencoded", $input, ["a" => ["b"], "c" => ["d"], "e" => ["f", "g"]], []];

        // 1 --- basic multipart request ---------------------------------------------------------->

        $input = <<<MULTIPART
--unique-boundary-1\r
Content-Disposition: form-data; name="a"\r
\r
... Some text appears here ... including a blank line at the end
\r
--unique-boundary-1\r
Content-Disposition: form-data; name="b"\r
\r
And yet another field\r
--unique-boundary-1\r
Content-Disposition: form-data; name="b"\r
Content-type: text/plain; charset=US-ASCII\r
\r
Hey, number b2!\r
--unique-boundary-1--\r\n
MULTIPART;

        $fields = [
            "a" => ["... Some text appears here ... including a blank line at the end\n"],
            "b" => [
                "And yet another field",
                "Hey, number b2!",
            ],
        ];

        $return[] = ["multipart/mixed; boundary=unique-boundary-1", $input, $fields, []];

        // 2 --- multipart request with file ------------------------------------------------------>

        $input = <<<MULTIPART
-----------------------------9051914041544843365972754266\r
Content-Disposition: form-data; name="text"\r
\r
text default\r
-----------------------------9051914041544843365972754266\r
Content-Disposition: form-data; name="file"; filename="a.txt"\r
Content-Type: text/plain\r
\r
Content of a.txt.
\r
-----------------------------9051914041544843365972754266\r
Content-Disposition: form-data; name="file"; filename="a.html"\r
Content-Type: text/html\r
\r
<!DOCTYPE html><title>Content of a.html.</title>
\r
-----------------------------9051914041544843365972754266--\r\n
MULTIPART;

        $fields = [
            "text" => [
                "text default",
            ],
        ];

        $files = [
            "file" => [
                ["content" => "Content of a.txt.\n", "mime" => "text/plain", "filename" => "a.txt"],
                ["content" => "<!DOCTYPE html><title>Content of a.html.</title>\n", "mime" => "text/html", "filename" => "a.html"],
            ],
        ];

        $return[] = ["multipart/form-data; boundary=---------------------------9051914041544843365972754266", $input, $fields, $files];

        // x -------------------------------------------------------------------------------------->

        return $return;
    }

    /**
     * @param string $header
     * @param string $data
     * @param array  $fields
     *
     * @dataProvider streamedRequestBodies
     */
    public function testStreamedDecoding(string $header, string $data, array $fields)
    {
        $headers = [];
        $headers["content-type"] = [$header];
        $body = new RequestBody(new IteratorStream(Iterator\fromIterable([$data])));

        $client = $this->createMock(Client::class);
        $request = new Request($client, "POST", Uri\Http::createFromString("/"), $headers, $body);
        $key = 0;

        wait(call(function () use ($request, $fields, &$key) {
            $iterator = (new StreamingParser)->parseForm($request);

            while (yield $iterator->advance()) {
                $parsedField = $iterator->getCurrent();
                $expectedField = $fields[$key++];
                $this->assertSame($expectedField["name"], $parsedField->getName());
                $this->assertSame($expectedField["mime_type"], $parsedField->getMimeType());
                $this->assertSame($expectedField["filename"], $parsedField->getFilename());
                $this->assertSame($expectedField["content"], yield $parsedField->buffer());
            }

            $this->assertFalse(yield $iterator->advance());
        }));

        $this->assertSame(\count($fields), $key);
    }

    public function streamedRequestBodies(): array
    {
        $return = [];

        // 0 --- basic request -------------------------------------------------------------------->

        $input = "a=b&c=d&e=f&e=g";

        $return[] = ["application/x-www-form-urlencoded", $input, [
            ["name" => "a", "content" => "b", "mime_type" => "text/plain", "filename" => null],
            ["name" => "c", "content" => "d", "mime_type" => "text/plain", "filename" => null],
            ["name" => "e", "content" => "f", "mime_type" => "text/plain", "filename" => null],
            ["name" => "e", "content" => "g", "mime_type" => "text/plain", "filename" => null],
        ]];

        // 1 --- basic multipart request ---------------------------------------------------------->

        $input = <<<MULTIPART
--unique-boundary-1\r
Content-Disposition: form-data; name="a"\r
\r
... Some text appears here ... including a blank line at the end
\r
--unique-boundary-1\r
Content-Disposition: form-data; name="b"\r
\r
And yet another field\r
--unique-boundary-1\r
Content-Disposition: form-data; name="b"\r
Content-type: text/plain; charset=US-ASCII\r
\r
Hey, number b2!\r
--unique-boundary-1--\r\n
MULTIPART;

        $return[] = ["multipart/mixed; boundary=unique-boundary-1", $input, [
            ["name" => "a", "content" => "... Some text appears here ... including a blank line at the end\n", "mime_type" => "text/plain", "filename" => null],
            ["name" => "b", "content" => "And yet another field", "mime_type" => "text/plain", "filename" => null],
            ["name" => "b", "content" => "Hey, number b2!", "mime_type" => "text/plain; charset=US-ASCII", "filename" => null],
        ]];

        // 2 --- multipart request with file ------------------------------------------------------>

        $input = <<<MULTIPART
-----------------------------9051914041544843365972754266\r
Content-Disposition: form-data; name="text"\r
\r
text default\r
-----------------------------9051914041544843365972754266\r
Content-Disposition: form-data; name="file"; filename="a.txt"\r
Content-Type: text/plain\r
\r
Content of a.txt.
\r
-----------------------------9051914041544843365972754266\r
Content-Disposition: form-data; name="file"; filename="a.html"\r
Content-Type: text/html\r
\r
<!DOCTYPE html><title>Content of a.html.</title>
\r
-----------------------------9051914041544843365972754266--\r\n
MULTIPART;

        $return[] = ["multipart/form-data; boundary=---------------------------9051914041544843365972754266", $input, [
            ["name" => "text", "content" => "text default", "mime_type" => "text/plain", "filename" => null],
            ["name" => "file", "content" => "Content of a.txt.\n", "mime_type" => "text/plain", "filename" => "a.txt"],
            ["name" => "file", "content" => "<!DOCTYPE html><title>Content of a.html.</title>\n", "mime_type" => "text/html", "filename" => "a.html"],
        ]];

        // x -------------------------------------------------------------------------------------->

        return $return;
    }
}
