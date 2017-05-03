<?php

namespace ProblemDetails;

use Spatie\ArrayToXml\ArrayToXml;
use Zend\Diactoros\Response\TextResponse;

class ProblemDetailsXmlResponse extends TextResponse implements ProblemDetailsResponse
{
    use CommonProblemDetails;

    private static $contentType = 'application/problem+xml';

    protected static function generatePayload(array $payload)
    {
        // Ensure any objects are flattened to arrays first
        $payload = json_decode(json_encode($payload), true);

        $converter = new ArrayToXml($payload, 'problem');
        $dom = $converter->toDom();
        $root = $dom->firstChild;
        $root->setAttribute('xmlns', 'urn:ietf:rfc:7807');

        return $dom->saveXML();
    }
}
