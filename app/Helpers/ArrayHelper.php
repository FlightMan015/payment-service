<?php

declare(strict_types=1);

namespace App\Helpers;

class ArrayHelper
{
    /**
     * @param array $array1
     * @param array $array2
     *
     * @return bool
     */
    public static function arraysEquals(array $array1, array $array2): bool
    {
        sort(array: $array1);
        sort(array: $array2);

        return $array1 === $array2;
    }

    /**
     * @param string $rawResponseLog
     *
     * @return array
     */
    public static function parseGatewayResponseXmlToArray(string $rawResponseLog): array
    {
        $xmlString = data_get(JsonDecoder::decode($rawResponseLog), 1);  // 0: Old response, 1: New response. AbstractGateway::addResponse() for more context

        if ($xmlString) {
            return JsonDecoder::decode(JsonDecoder::encode(simplexml_load_string($xmlString)));
        }

        return [];
    }
}
