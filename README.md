<div align="center">
    <p>
        <h1>Mime Mail Parser for PHP<br/>Simple, fast, no extensions required</h1>
    </p>
</div>

<p align="center">
    <a href="#features">Features</a> |
    <a href="#installation">Installation</a> |
    <a href="#credits">Credits</a>
</p>

<p align="center">
<a href="https://packagist.org/packages/erseco/mime-mail-parser"><img src="https://img.shields.io/packagist/v/erseco/mime-mail-parser.svg?style=flat-square" alt="Packagist"></a>
<a href="https://packagist.org/packages/erseco/mime-mail-parser"><img src="https://img.shields.io/packagist/dm/erseco/mime-mail-parser.svg?style=flat-square" alt="Packagist"></a>
<a href="https://packagist.org/packages/erseco/mime-mail-parser"><img src="https://img.shields.io/packagist/php-v/erseco/mime-mail-parser.svg?style=flat-square" alt="PHP from Packagist"></a>
</p>

## Features

**Mime Mail Parser** has a very simple API to parse emails and their MIME contents. Unlike many other parsers out there, this package does not require the [mailparse](https://www.php.net/manual/en/book.mailparse.php) PHP extension.

Has not been fully tested against RFC 5322.

## Get Started

### Requirements

- **PHP 8.0+**

### Installation

To install the package via composer, Run:

```bash
composer require erseco/mime-mail-parser
```

### Usage

```php
use Erseco\MimeMailParser;

// Parse a message from a string
$rawEmail = file_get_contents('/path/to/email.eml');
$parser = new MimeMailParser($rawEmail);

$parser->getHeaders();                 // get all headers
$parser->getContentType();             // 'multipart/mixed; boundary="----=_Part_1_1234567890"'
$parser->getFrom();                    // 'Service <service@example.com>'
$parser->getTo();                      // 'John Doe <johndoe@example.com>'
$parser->getSubject();                 // 'Subject line'
$parser->getDate();                    // DateTime object when the email was sent

$parser->getParts();       // Returns an array of parts, which can be html parts, text parts, attachments, etc.
$parser->getHtmlPart();    // Returns the HTML content
$parser->getTextPart();    // Returns the Text content
$parser->getAttachments(); // Returns an array of attachments

$parts = $parser->getParts();
$firstPart = $parts[0];

$firstPart->headers;                 // array of all headers for this message part
$firstPart->contentType;             // 'text/html; charset="utf-8"'
$firstPart->content;                 // '<html><body>....'
$firstPart->isHtml;                  // true if it's an HTML part
$firstPart->isAttachment;            // true if it's an attachment
$firstPart->filename;                // name of the file, in case this is an attachment part
```

## Credits

- [opcodesio/mail-parser](https://github.com/opcodesio/mail-parser)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
