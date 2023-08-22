<?php declare(strict_types=1);

namespace Amp\Http\Server\FormParser;

/**
 * Parse the given content-type and returns the boundary if parsing is supported,
 * an empty string content-type is url-encoded mode or null if not supported.
 */
function parseContentBoundary(string $contentType): ?string
{
    if (\strncmp(
        $contentType,
        "application/x-www-form-urlencoded",
        \strlen("application/x-www-form-urlencoded"),
    ) === 0) {
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
