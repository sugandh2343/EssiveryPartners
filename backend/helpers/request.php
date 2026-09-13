<?php

function getJsonBody(): array
{
    $rawBody = file_get_contents('php://input');

    if (!$rawBody) {
        return [];
    }

    $decoded = json_decode($rawBody, true);

    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

function getBearerToken(): ?string
{
    $authHeader =
        $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? null;

    if (!$authHeader) {
        return null;
    }

    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }

    return null;
}