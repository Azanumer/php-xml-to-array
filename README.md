# XML to Array

Dependency-free PHP library to convert XML to a nested array and back — for config files, API responses and feeds, without pulling in a full XML library.

## Features

- **Parse** — `XmlConverter::toArray($xml)` returns a nested array. Leaf elements become plain strings; attributes live under `@attributes`; mixed text + children stores the text under `#text`; repeated siblings become lists; CDATA is merged into the text.
- **Build** — `XmlConverter::fromArray($data, 'root')` returns a pretty-printed XML document. `@attributes` become attributes, `#text` becomes text, numerically-indexed arrays repeat the element, `null` makes an empty element.
- **Round-trip safe** — parse → build → parse gives back the same structure (see the demo).
- **Readable errors** — malformed XML throws `InvalidArgumentException` with the libxml line and message instead of a PHP warning.
- **No dependencies** — SimpleXML for parsing, DOMDocument for building. PHP 7.4+.

## Usage

```php
require 'src/XmlConverter.php';

// XML → array
$data = XmlConverter::toArray('<site name="Blog"><title>Hello</title></site>');
// $data = ['@attributes' => ['name' => 'Blog'], 'title' => 'Hello'];

// Array → XML
$xml = XmlConverter::fromArray([
    'post' => [
        '@attributes' => ['id' => '42'],
        'title' => 'Hello World',
        'tag'   => ['php', 'wordpress'],
    ],
], 'blog');
```

Run the demo:

```bash
php examples/xml-example.php
```

## License

MIT — see [LICENSE](LICENSE).
