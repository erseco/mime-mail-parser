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
<a href="https://packagist.org/packages/opcodesio/mail-parser"><img src="https://img.shields.io/packagist/v/opcodesio/mail-parser.svg?style=flat-square" alt="Packagist"></a>
<a href="https://packagist.org/packages/opcodesio/mail-parser"><img src="https://img.shields.io/packagist/dm/opcodesio/mail-parser.svg?style=flat-square" alt="Packagist"></a>
<a href="https://packagist.org/packages/opcodesio/mail-parser"><img src="https://img.shields.io/packagist/php-v/opcodesio/mail-parser.svg?style=flat-square" alt="PHP from Packagist"></a>
</p>

## Features

[OPcodes's](https://www.opcodes.io/) **Mail Parser** has a very simple API to parse emails and their MIME contents. Unlike many other parsers out there, this package does not require the [mailparse](https://www.php.net/manual/en/book.mailparse.php) PHP extension.

Has not been fully tested against RFC 5322.

## Get Started

### Requirements

- **PHP 8.0+**

### Installation

To install the package via composer, Run:

```bash
composer require opcodesio/mail-parser
```

### Usage

```php
use Erseco\MimeMailParser\Mime_Mail_Parser;

// Parse a message from a string
$rawEmail = file_get_contents('/path/to/email.eml');
$parser = new Mime_Mail_Parser($rawEmail);

$parser->getHeaders();                 // get all headers
$parser->getContentType();             // 'multipart/mixed; boundary="----=_Part_1_1234567890"'
$parser->getFrom();                    // 'Arunas <arunas@example.com>'
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

## Contributing

A guide for contributing is in progress...

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Arunas Skirius](https://github.com/arukompas)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
