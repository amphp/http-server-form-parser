<?php

namespace Amp\Http\Server\FormParser\Test;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\Http\Server\Driver\Client;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\FormParser\StreamedField;
use Amp\Http\Server\FormParser\StreamingParser;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestBody;
use Amp\Iterator;
use League\Uri;
use PHPUnit\Framework\TestCase;
use function Amp\call;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Promise\wait;

class ParsingTest extends TestCase {
    /**
     * @param string $header
     * @param string $data
     * @param array  $fields
     * @param array  $metadata
     *
     * @dataProvider requestBodies
     */
    public function testBufferedDecoding(string $header, string $data, array $fields, array $metadata) {
        $headers = [];
        $headers["content-type"] = [$header];
        $body = new RequestBody(new InMemoryStream($data));

        $client = $this->createMock(Client::class);
        $request = new Request($client, "POST", Uri\Http::createFromString("/"), $headers, $body);

        wait(call(function () use ($request, $fields, $metadata) {
            /** @var Form $form */
            $form = yield parseForm($request);

            foreach ($fields as $key => $value) {
                $this->assertSame($form->getValueArray($key), $value);
            }

            foreach ($metadata as $fieldName => $metaArray) {
                foreach ($metaArray as $key => $data) {
                    $formFields = $form->getFieldArray($fieldName);
                    $this->assertArrayHasKey($key, $formFields);

                    $formField = $formFields[$key];
                    $this->assertSame($formField->getAttributes()->getMimeType(), $data["mime"]);
                    $this->assertSame($formField->getAttributes()->hasFilename(), isset($data["filename"]));
                    $this->assertSame($formField->getAttributes()->getFilename(), $data["filename"] ?? null);
                }
            }
        }));
    }

    /**
     * @param string $header
     * @param string $data
     * @param array  $fields
     * @param array  $metadata
     *
     * @dataProvider requestBodies
     */
    public function testStreamedDecoding(string $header, string $data, array $fields, array $metadata) {
        $headers = [];
        $headers["content-type"] = [$header];
        $body = new RequestBody(new IteratorStream(Iterator\fromIterable([$data])));

        $client = $this->createMock(Client::class);
        $request = new Request($client, "POST", Uri\Http::createFromString("/"), $headers, $body);

        wait(call(function () use ($request, $fields) {
            $iterator = (new StreamingParser($request))->parseForm();

            foreach ($fields as $key => $values) {
                foreach ($values as $value) {
                    $this->assertTrue(yield $iterator->advance());

                    /** @var StreamedField $parsedField */
                    $parsedField = $iterator->getCurrent();
                    $this->assertSame($key, $parsedField->getName());
                    $this->assertSame($value, yield $parsedField->buffer());
                }
            }

            $this->assertFalse(yield $iterator->advance());
        }));
    }

    public function requestBodies(): array {
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

        $metadata = [
            "b" => [
                1 => ["mime" => "text/plain; charset=US-ASCII"],
            ],
        ];

        $return[] = ["multipart/mixed; boundary=unique-boundary-1", $input, $fields, $metadata];

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
            "file" => [
                "Content of a.txt.\n",
                "<!DOCTYPE html><title>Content of a.html.</title>\n",
            ],
        ];

        $metadata = [
            "file" => [
                ["mime" => "text/plain", "filename" => "a.txt"],
                ["mime" => "text/html", "filename" => "a.html"],
            ],
        ];

        $return[] = ["multipart/form-data; boundary=---------------------------9051914041544843365972754266", $input, $fields, $metadata];

        // x -------------------------------------------------------------------------------------->

        return $return;
    }
}
