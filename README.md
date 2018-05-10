# http-server-form-parser

This package is an add-on to [`amphp/http-server`](https://github.com/amphp/http-server), which allows parsing request bodies as forms in either `x-www-form-urlencoded` or `multipart/form-data` format.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/http-server-form-parser
```

## Usage

Basic usage works by calling `parseForm($request)`, which will buffer the request body and parse it.

```php
<?php

use Amp\Http\Server\FormParser;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Status;

new CallableRequestHandler(function (Request $request) {
    /** @var FormParser\Form $form */
    $form = yield FormParser\parseForm($request);

    return new Response(Status::OK, [
        "content-type" => "text/plain; charset=utf-8"
    ], $form->getValue("text") ?? "Hello, World!");
});
```

There's also an advanced streaming parser included, which can be used to stream uploaded files to disk or other locations.