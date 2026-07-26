<?php

/**
 * Compatibility and edge-case tests for the MIME mail parser.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Unit;

use Erseco\Message;
use Erseco\MessagePart;

it(
    'loads the message class through composer autoload',
    function () {
        expect(class_exists(Message::class))->toBeTrue();
    }
);

it(
    'parses headers without whitespace and unfolds continuations',
    function () {
        $message = Message::fromString(
            "Subject:First line\r\n\tsecond line\r\n"
            . "Content-Type:text/plain; charset=utf-8\r\n\r\nBody"
        );

        expect($message->getSubject())->toBe('First line second line')
            ->and($message->getContentType())->toBe('text/plain; charset=utf-8')
            ->and($message->getTextPart()?->getContent())->toBe('Body');
    }
);

it(
    'preserves repeated header values',
    function () {
        $message = Message::fromString(
            "Received: by first.example\r\n"
            . "Received: by second.example\r\n"
            . "Content-Type: text/plain\r\n\r\nBody"
        );

        expect($message->getHeaderValues('Received'))->toBe(
            [
                'by first.example',
                'by second.example',
            ]
        );
    }
);

it(
    'returns a null boundary for a single-part message',
    function () {
        $message = Message::fromString("Content-Type: text/plain\r\n\r\nBody");

        expect($message->getBoundary())->toBeNull();
    }
);

it(
    'throws a clear exception when an email file cannot be read',
    function () {
        Message::fromFile(__DIR__ . '/missing-message.eml');
    }
)->throws(\RuntimeException::class, 'Unable to read email message');

it(
    'supports case-insensitive part headers and RFC 2231 filenames',
    function () {
        $part = new MessagePart(
            'SGVsbG8=',
            [
                'content-type' => 'application/octet-stream',
                'CONTENT-TRANSFER-ENCODING' => 'base64',
                'Content-Disposition' => "attachment; filename*=UTF-8''caf%C3%A9.txt",
            ]
        );

        expect($part->getContent())->toBe('Hello')
            ->and($part->getFilename())->toBe('café.txt')
            ->and($part->isAttachment())->toBeTrue();
    }
);

it(
    'identifies inline resources and exposes their content id',
    function () {
        $part = new MessagePart(
            'image-data',
            [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="image.png"',
                'Content-ID' => '<image-1@example.com>',
            ]
        );

        expect($part->isInline())->toBeTrue()
            ->and($part->isAttachment())->toBeFalse()
            ->and($part->getContentId())->toBe('image-1@example.com');
    }
);

it(
    'does not discard invalid base64 content',
    function () {
        $part = new MessagePart(
            'not valid base64 %',
            ['Content-Transfer-Encoding' => 'base64']
        );

        expect($part->getContent())->toBe('not valid base64 %');
    }
);

it(
    'preserves trailing spaces in text content',
    function () {
        $message = Message::fromString(
            "Content-Type: text/plain\r\n\r\nLine with spaces  \r\n"
        );

        expect($message->getTextPart()?->getContent())->toBe('Line with spaces  ');
    }
);

it(
    'infers a boundary when the content type parameter is malformed',
    function () {
        $message = Message::fromString(
            "Content-Type: multipart/mixed; boundary¨broken\r\n\r\n"
            . "--actual-boundary\r\n"
            . "Content-Type: text/plain\r\n\r\n"
            . "Body\r\n"
            . "--actual-boundary--\r\n"
        );

        expect($message->getBoundary())->toBe('actual-boundary')
            ->and($message->getTextPart()?->getContent())->toBe('Body');
    }
);
