<?php
/**
 * XmlConverter — dependency-free XML <-> array conversion.
 *
 * Conventions (documented so a round-trip is predictable):
 *
 *   toArray():
 *     - An element with no children and no attributes becomes its text
 *       content as a plain string (empty string for an empty element).
 *     - Attributes live under the '@attributes' key.
 *     - An element with both text and children stores the text under '#text'.
 *     - Repeated sibling elements become a numerically-indexed array.
 *     - CDATA sections are merged into the text content.
 *
 *   fromArray():
 *     - The inverse: '@attributes' keys become attributes, '#text' becomes
 *       the element's text, numerically-indexed arrays repeat the element.
 *     - A value of null produces an empty (self-closing) element.
 *
 * No external libraries: SimpleXML for parsing, DOMDocument for building.
 */

class XmlConverter
{
    /**
     * Parse an XML string into a nested array.
     *
     * @param string $xml XML document.
     * @return array The root element's children, keyed by tag name.
     * @throws InvalidArgumentException When the XML is malformed.
     */
    public static function toArray($xml)
    {
        if (!is_string($xml) || '' === trim($xml)) {
            throw new InvalidArgumentException('XmlConverter::toArray() expects a non-empty XML string.');
        }

        $previous = libxml_use_internal_errors(true);
        $element  = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $errors   = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (false === $element) {
            $message = 'Malformed XML';
            if (!empty($errors)) {
                $first   = $errors[0];
                $message .= sprintf(' (line %d: %s)', $first->line, trim($first->message));
            }
            throw new InvalidArgumentException($message);
        }

        return self::elementToArray($element);
    }

    /**
     * Build an XML string from a nested array.
     *
     * @param array  $data     Nested array (see class docblock).
     * @param string $rootName Name of the root element.
     * @return string XML document (with declaration, pretty-printed).
     */
    public static function fromArray(array $data, $rootName = 'root')
    {
        $doc  = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElement($rootName);
        $doc->appendChild($root);
        self::arrayToElement($doc, $root, $data);
        $xml = $doc->saveXML();
        return false === $xml ? '' : $xml;
    }

    /**
     * Convert one SimpleXMLElement (and its subtree) to an array.
     *
     * @param SimpleXMLElement $element
     * @return mixed String for leaf nodes, array otherwise.
     */
    private static function elementToArray(SimpleXMLElement $element)
    {
        $result = array();

        // Attributes first, so they never collide with child names.
        foreach ($element->attributes() as $name => $value) {
            $result['@attributes'][(string) $name] = (string) $value;
        }

        // Namespaced attributes (e.g. xml:lang) are not covered by
        // attributes() — include them so nothing is silently dropped.
        foreach ($element->attributes('http://www.w3.org/XML/1998/namespace') as $name => $value) {
            $result['@attributes']['xml:' . $name] = (string) $value;
        }

        $children = $element->children();
        $text     = trim((string) $element);

        if (0 === count($children)) {
            if (empty($result)) {
                return $text;
            }
            if ('' !== $text) {
                $result['#text'] = $text;
            }
            return $result;
        }

        // Group repeated siblings under one key.
        foreach ($children as $name => $child) {
            $value = self::elementToArray($child);
            if (array_key_exists($name, $result)) {
                // Second occurrence: convert the existing value to a list.
                if (!is_array($result[$name]) || !array_key_exists(0, $result[$name])) {
                    $result[$name] = array($result[$name]);
                }
                $result[$name][] = $value;
            } else {
                $result[$name] = $value;
            }
        }

        if ('' !== $text) {
            $result['#text'] = $text;
        }

        return $result;
    }

    /**
     * Append an array subtree to a DOM element.
     *
     * @param DOMDocument $doc
     * @param DOMElement  $parent
     * @param array       $data
     */
    private static function arrayToElement(DOMDocument $doc, DOMElement $parent, array $data)
    {
        foreach ($data as $key => $value) {
            if ('@attributes' === $key) {
                if (is_array($value)) {
                    foreach ($value as $attrName => $attrValue) {
                        $parent->setAttribute((string) $attrName, (string) $attrValue);
                    }
                }
                continue;
            }

            if ('#text' === $key) {
                $parent->appendChild($doc->createTextNode((string) $value));
                continue;
            }

            $tag = self::sanitizeTagName((string) $key);

            if (null === $value) {
                $parent->appendChild($doc->createElement($tag));
                continue;
            }

            if (is_array($value) && self::isList($value)) {
                // Numerically-indexed array: repeat the element.
                foreach ($value as $item) {
                    $child = $doc->createElement($tag);
                    $parent->appendChild($child);
                    self::appendValue($doc, $child, $item);
                }
                continue;
            }

            $child = $doc->createElement($tag);
            $parent->appendChild($child);
            self::appendValue($doc, $child, $value);
        }
    }

    /**
     * Append a single value (scalar or nested array) to an element.
     *
     * @param DOMDocument $doc
     * @param DOMElement  $element
     * @param mixed       $value
     */
    private static function appendValue(DOMDocument $doc, DOMElement $element, $value)
    {
        if (is_array($value)) {
            self::arrayToElement($doc, $element, $value);
            return;
        }
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        $element->appendChild($doc->createTextNode((string) $value));
    }

    /**
     * Is this array a list (0, 1, 2, ... keys) rather than a map?
     *
     * @param array $array
     * @return bool
     */
    private static function isList(array $array)
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Make a string safe as an XML tag name. Invalid characters are
     * replaced with underscores; a leading digit gets a "_" prefix.
     *
     * @param string $name
     * @return string
     */
    private static function sanitizeTagName($name)
    {
        $name = preg_replace('/[^A-Za-z0-9_\-\.:]/', '_', $name);
        if ('' === $name) {
            return 'item';
        }
        if (preg_match('/^[0-9.\-]/', $name)) {
            $name = '_' . $name;
        }
        return $name;
    }
}
