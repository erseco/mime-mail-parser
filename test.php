<?php

require "vendor/autoload.php";

use Erseco\MimeMailParser;

// Parse a message from a string
$rawEmail = file_get_contents(__DIR__ . '/tests/Fixtures/multi_attachment_email.eml');
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
#$firstPart->filename;                // name of the file, in case this is an attachment part

// echo "\n----- PARTS ----\n";
// print_r($parts);

echo "\n----- HTML PART ----\n";
print_r($parser->getHtmlPart());

echo "\n----- TEXT PART ----\n";
print_r($parser->getTextPart());

echo "\n----- HEADERS ----\n";
print_r($parser->getHeaders());

echo "\n----- ATTACHMENTS ----\n";
// print_r($parser->getAttachments());

foreach ($parts as $part) {
	if ($part->isAttachment) {
		echo $part->filename . "\n";
	}
}
