<div align="center">
    <p>
        <h1>Mime Mail Parser for PHP<br/>Simple, fast, no extensions required, based on [opcodesio/mail-parser](https://github.com/opcodesio/mail-parser)</h1>
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
use Erseco\Message;

// Parse a message from a string
$rawEmail = file_get_contents('/path/to/email.eml');
$message = Message::fromString($rawEmail);

// Or parse from a file directly
$message = Message::fromFile('/path/to/email.eml');

$message->getHeaders();                 // get all headers as array
$message->getHeader('Content-Type');    // get specific header (raw unfolded value)
$message->getContentType();             // 'multipart/mixed; boundary="----=_Part_1_1234567890"'
$message->getFrom();                    // 'Service <service@example.com>'
$message->getTo();                      // 'John Doe <johndoe@example.com>'
$message->getSubject();                 // raw subject (may still contain RFC 2047 encoded words)
$message->getDate();                    // DateTime object when the email was sent

// RFC 2047: raw vs decoded headers
// getHeader() / getSubject() keep the raw unfolded value, including encoded-words.
// getDecodedHeader() and the convenience helpers decode B/Q encoded-words to text.
$message->getHeader('Subject');         // '=?UTF-8?B?UmV1bmnDs24gZGUgcHJveWVjdG8=?='
$message->getDecodedHeader('Subject');  // 'Reunión de proyecto'
$message->getDecodedSubject();          // same as getDecodedHeader('Subject')
$message->getDecodedFrom();
$message->getDecodedTo();
$message->getDecodedReplyTo();


$message->getParts();       // Returns array of MessagePart objects
$message->getHtmlPart();    // Returns MessagePart with HTML content
$message->getTextPart();    // Returns MessagePart with Text content
$message->getAttachments(); // Returns array of attachment MessageParts

// Working with message parts
$parts = $message->getParts();
$firstPart = $parts[0];

$firstPart->getHeaders();                 // array of all headers for this part
$firstPart->getHeader('Content-Type');    // get specific header
$firstPart->getContentType();             // 'text/html; charset="utf-8"'
$firstPart->getContent();                 // '<html><body>....'
$firstPart->isHtml();                     // true if it's an HTML part
$firstPart->isText();                     // true if it's a text part
$firstPart->isAttachment();               // true if it's an attachment
$firstPart->getFilename();                // name of the file if attachment
                                          // supports filename=, filename*=, and RFC 2231
                                          // continuations (filename*0*=, filename*1*=, …)
$firstPart->getSize();                    // size of content in bytes
```


## Credits

- [opcodesio/mail-parser](https://github.com/opcodesio/mail-parser)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
