<?php
/**
 * XmlConverter demo: parse XML to an array, build XML from an array,
 * and round-trip one back into the other.
 *
 * Usage: php examples/xml-example.php
 */

require __DIR__ . '/../src/XmlConverter.php';

// --- 1. Parse an XML config file into an array. -------------------------
$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<site name="Example Blog" version="2">
    <title><![CDATA[My <b>Blog</b> & Friends]]></title>
    <owner email="admin@example.com">Azan Umer</owner>
    <features>
        <feature enabled="true">comments</feature>
        <feature enabled="false">ratings</feature>
        <feature enabled="true">newsletter</feature>
    </features>
    <empty-note />
</site>
XML;

$data = XmlConverter::toArray($xml);
echo "Parsed array:\n";
print_r($data);

// --- 2. Build XML from an array. ----------------------------------------
$built = XmlConverter::fromArray(
    array(
        'post' => array(
            '@attributes' => array('id' => '42', 'status' => 'publish'),
            'title'   => 'Hello World',
            'author'  => 'Azan Umer',
            'tag'     => array('php', 'wordpress', 'xml'),
            'content' => 'A short <b>example</b> post.',
        ),
    ),
    'blog'
);
echo "\nBuilt XML:\n";
echo $built;

// --- 3. Round-trip: parse the built XML back and compare. ---------------
$roundTripped = XmlConverter::toArray($built);
echo "\nRound-tripped title: " . $roundTripped['post']['title'] . "\n";
echo "Round-tripped tags: " . implode(', ', $roundTripped['post']['tag']) . "\n";
echo "Round-tripped id attribute: " . $roundTripped['post']['@attributes']['id'] . "\n";

// --- 4. Malformed XML throws a readable exception. ----------------------
try {
    XmlConverter::toArray('<broken><unclosed>');
} catch (InvalidArgumentException $e) {
    echo "\nCaught expected error: " . $e->getMessage() . "\n";
}
