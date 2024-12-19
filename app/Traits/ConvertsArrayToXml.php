<?php

declare(strict_types=1);

namespace App\Traits;

trait ConvertsArrayToXml
{
    /**
     * @param array $array
     * @param string|null $rootElement
     * @param \SimpleXMLElement|null $xml
     *
     * @throws \Exception
     *
     * @return \SimpleXMLElement
     */
    private function arrayToXml(
        array $array,
        string|null $rootElement = null,
        \SimpleXMLElement|null $xml = null
    ): \SimpleXMLElement {
        $_xml = $xml;

        if ($_xml === null) {
            $_xml = new \SimpleXMLElement(data: $rootElement ?? '<root/>');
        }

        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $this->arrayToXml(array: $v, rootElement: $k, xml: $_xml->addChild($k));
            } else {
                $v = !is_null($v) ? (string)$v : null;
                $_xml->addChild(qualifiedName: $k, value: htmlspecialchars($v ?? '') ?: $v);
            }
        }

        return $_xml;
    }
}
