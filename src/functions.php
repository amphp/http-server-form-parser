<?php

namespace Amp\Http\Server\FormParser;

use Amp\Http\Server\Request;

/**
 * Try parsing the request's body with either application/x-www-form-urlencoded or multipart/form-data.
 *
 * @param Request $request
 *
 * @return Form
 */
function parseForm(Request $request): Form
{
    static $parser;

    if ($parser === null) {
        $parser = new BufferingParser;
    }

    return $parser->parseForm($request);
}

/**
 * Parse the given content-type and returns the boundary if parsing is supported,
 * an empty string content-type is url-encoded mode or null if not supported.
 *
 * @param string $contentType
 *
 * @return null|string
 */
function parseContentBoundary(string $contentType): ?string
{
    if (\strncmp(
            $contentType,
            "application/x-www-form-urlencoded",
            \strlen("application/x-www-form-urlencoded"),
        ) === 0
    ) {
        return '';
    }

    if (!\preg_match(
        '#^\s*multipart/(?:form-data|mixed)(?:\s*;\s*boundary\s*=\s*("?)([^"]*)\1)?$#',
        $contentType,
        $matches,
    )) {
        return null;
    }

    return $matches[2];
}
