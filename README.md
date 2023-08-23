# http-server-form-parser

This package is an add-on to [`amphp/http-server`](https://github.com/amphp/http-server), which allows parsing request bodies as forms in either `x-www-form-urlencoded` or `multipart/form-data` format.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/http-server-form-parser
```

## Usage

Basic usage works by calling `Form::fromRequest($request)`, which will buffer the request body and parse it. This method may be called multiple times, so both a [middleware](https://github.com/amphp/http-server#middleware) and [request handler](https://github.com/amphp/http-server#requesthandler) may access the form body.

```php
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Status;

$requestHandler = new ClosureRequestHandler(function (Request $request) {
    $form = Form::fromRequest($request);

    return new Response(Status::OK, [
        "content-type" => "text/plain; charset=utf-8"
    ], $form->getValue("text") ?? "Hello, World!");
});
```

There's also an advanced streaming parser included, `StreamingFormParser`, which can be used to stream uploaded files to disk or other locations.
