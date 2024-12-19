<?php

declare(strict_types=1);

namespace App\Helpers;

class JsonDecoder
{
    /**
     * @param string|null $json
     * @param bool $toArray
     *
     * @throws \JsonException
     *
     * @return array|object|null
     */
    public static function decode(string|null $json, bool $toArray = true): array|object|null
    {
        if (is_null($json)) {
            return null;
        }

        return json_decode($json, $toArray, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param mixed $value
     *
     * @throws \JsonException
     *
     * @return string
     */
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function isValidJsonString(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return json_validate($value);
    }
}
