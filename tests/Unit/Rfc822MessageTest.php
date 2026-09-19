<?php

declare(strict_types=1);

/**
 * Attached RFC 822 message tests.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Unit;

use Erseco\MimeMailParser\Message;
use Erseco\MimeMailParser\MessagePart;
use Erseco\MimeMailParser\ParserLimitExceededException;
use Erseco\MimeMailParser\ParserOptions;

it(
    'parses message rfc822 parts as nested messages',
    function () {
        $nested = "From: nested@example.com\r\n"
            . "To: receiver@example.com\r\n"
            . "Subject: Nested message\r\n"
            . "Content-Type: text/plain\r\n\r\n"
            . "Nested body";

        $raw = "From: sender@example.com\r\n"
            . "Subject: Forwarded\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=outer\r\n\r\n"
            . "--outer\r\n"
            . "Content-Type: text/plain\r\n\r\n"
            . "See attached\r\n"
            . "--outer\r\n"
            . "Content-Type: message/rfc822\r\n"
            . "Content-Disposition: attachment\r\n\r\n"
            . $nested . "\r\n"
            . "--outer--\r\n";

        $message = Message::fromString($raw);
        $messagePart = $message->getParts()[1];

        expect($messagePart->isMessage())->toBeTrue()
            ->and($messagePart->getMessage()?->getSubject())->toBe('Nested message')
            ->and($messagePart->getMessage()?->getTextPart()?->getContent())->toBe('Nested body')
            ->and($message->getAttachedMessages())->toHaveCount(1)
            ->and($message->getAttachedMessages()[0]->getFrom())->toBe('nested@example.com');
    }
);

it(
    'decodes transfer encoding before parsing an attached message',
    function () {
        $nested = "Subject: Encoded nested\r\nContent-Type: text/plain\r\n\r\nBody";
        $part = new MessagePart(
            base64_encode($nested),
            [
                'Content-Type' => 'message/rfc822',
                'Content-Transfer-Encoding' => 'base64',
            ]
        );

        expect($part->getMessage()?->getSubject())->toBe('Encoded nested');
    }
);

it(
    'returns null when a part is not an attached message',
    function () {
        $part = new MessagePart('Body', ['Content-Type' => 'text/plain']);

        expect($part->isMessage())->toBeFalse()
            ->and($part->getMessage())->toBeNull();
    }
);

it(
    'reuses parser limits for attached messages',
    function () {
        $part = new MessagePart(
            "Subject: Nested\r\n\r\n" . str_repeat('A', 64),
            ['Content-Type' => 'message/rfc822'],
            new ParserOptions(maxMessageBytes: 32)
        );

        $part->getMessage();
    }
)->throws(ParserLimitExceededException::class);
