<?php

namespace Erseco\MimeMailParser\Tests;

use Erseco\MimeMailParser\Tests\TestCase;
use Erseco\MimeMailParser\Mime_Mail_Parser;

class MimeMailParserTest extends TestCase
{
    public function testMimeMailParser()
    {
        $rawEmail = "Content-Type: text/plain\r\n\r\nThis is a test email.";
        $parser = new Mime_Mail_Parser($rawEmail);

        $this->assertEquals('text/plain', $parser->getHeaders()['Content-Type']);
        $this->assertEquals('This is a test email.', $parser->getTextPart());
    }
}
