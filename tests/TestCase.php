<?php

namespace Erseco\MimeMailParser\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Erseco\MimeMailParser\Mime_Mail_Parser;

abstract class TestCase extends BaseTestCase
{
    public function testMimeMailParser()
    {
        $rawEmail = "Content-Type: text/plain\r\n\r\nThis is a test email.";
        $parser = new Mime_Mail_Parser($rawEmail);

        $this->assertEquals('text/plain', $parser->getHeaders()['Content-Type']);
        $this->assertEquals('This is a test email.', $parser->getTextPart());
    }
}
