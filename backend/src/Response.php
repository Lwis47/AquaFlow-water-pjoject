<?php

declare(strict_types=1);

namespace AquaFlow;

use JsonException;

final class Response
{
    public static function send(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        self::send(['ok' => false, 'error' => $message], $status);
    }

    public static function input(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::error('The request body must contain valid JSON.');
        }

        if (!is_array($input)) {
            self::error('The JSON request body must be an object.');
        }

        return $input;
    }
}
