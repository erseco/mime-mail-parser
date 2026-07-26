<?php

/**
 * Tests for configurable parser safety limits.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Unit;

use Erseco\Message;
use Erseco\ParserLimitExceededException;
use Erseco\ParserOptions;

it(
    'parses normal messages with default limits',
    function () {
        $message = Message::fromString("Subject: Hello\r\nContent-Type: text/plain\r\n\r\nBody");

        expect($message->getSubject())->toBe('Hello')
            ->and($message->getTextPart()?->getContent())->toBe('Body')
            ->and($message->getOptions()->maxParts)->toBe(ParserOptions::DEFAULT_MAX_PARTS);
    }
);

it(
    'allows message size exactly at the limit',
    function () {
        $body = 'X';
        $raw = "Content-Type: text/plain\r\n\r\n" . $body;
        $options = new ParserOptions(maxMessageBytes: strlen($raw));

        $message = Message::fromString($raw, false, $options);

        expect($message->getTextPart()?->getContent())->toBe($body);
    }
);

it(
    'rejects messages one byte over maxMessageBytes',
    function () {
        $raw = "Content-Type: text/plain\r\n\r\nBody";
        $options = new ParserOptions(maxMessageBytes: strlen($raw) - 1);

        expect(fn () => Message::fromString($raw, false, $options))
            ->toThrow(ParserLimitExceededException::class);

        try {
            Message::fromString($raw, false, $options);
        } catch (ParserLimitExceededException $exception) {
            expect($exception->getLimitName())->toBe('maxMessageBytes')
                ->and($exception->getLimitValue())->toBe(strlen($raw) - 1)
                ->and($exception->getActualValue())->toBe(strlen($raw));
        }
    }
);

it(
    'enforces maxParts globally including nested multiparts',
    function () {
        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n"
            . "--b1\r\nContent-Type: text/plain\r\n\r\nOne\r\n"
            . "--b1\r\nContent-Type: multipart/alternative; boundary=\"b2\"\r\n\r\n"
            . "--b2\r\nContent-Type: text/plain\r\n\r\nTwo\r\n"
            . "--b2\r\nContent-Type: text/html\r\n\r\n<html>Three</html>\r\n"
            . "--b2--\r\n"
            . "--b1--\r\n";

        $ok = new ParserOptions(maxParts: 3);
        expect(Message::fromString($raw, false, $ok)->getParts())->toHaveCount(3);

        $tooLow = new ParserOptions(maxParts: 2);
        expect(fn () => Message::fromString($raw, false, $tooLow))
            ->toThrow(ParserLimitExceededException::class);

        try {
            Message::fromString($raw, false, $tooLow);
        } catch (ParserLimitExceededException $exception) {
            expect($exception->getLimitName())->toBe('maxParts');
        }
    }
);

it(
    'enforces multipart nesting depth',
    function () {
        $raw = "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n"
            . "--b1\r\nContent-Type: multipart/mixed; boundary=\"b2\"\r\n\r\n"
            . "--b2\r\nContent-Type: text/plain\r\n\r\nDeep\r\n"
            . "--b2--\r\n"
            . "--b1--\r\n";

        $ok = new ParserOptions(maxDepth: 1);
        expect(Message::fromString($raw, false, $ok)->getTextPart()?->getContent())->toBe('Deep');

        $tooLow = new ParserOptions(maxDepth: 0);
        expect(fn () => Message::fromString($raw, false, $tooLow))
            ->toThrow(ParserLimitExceededException::class);

        try {
            Message::fromString($raw, false, $tooLow);
        } catch (ParserLimitExceededException $exception) {
            expect($exception->getLimitName())->toBe('maxDepth');
        }
    }
);

it(
    'enforces maximum headers per header block',
    function () {
        $raw = "H1: a\r\nH2: b\r\nH3: c\r\nContent-Type: text/plain\r\n\r\nBody";
        $options = new ParserOptions(maxHeaders: 3);

        expect(fn () => Message::fromString($raw, false, $options))
            ->toThrow(ParserLimitExceededException::class);

        try {
            Message::fromString($raw, false, $options);
        } catch (ParserLimitExceededException $exception) {
            expect($exception->getLimitName())->toBe('maxHeaders');
        }
    }
);

it(
    'enforces maximum header line length',
    function () {
        $long = str_repeat('A', 50);
        $raw = "Subject: {$long}\r\nContent-Type: text/plain\r\n\r\nBody";
        $options = new ParserOptions(maxHeaderLineLength: 40);

        expect(fn () => Message::fromString($raw, false, $options))
            ->toThrow(ParserLimitExceededException::class);

        try {
            Message::fromString($raw, false, $options);
        } catch (ParserLimitExceededException $exception) {
            expect($exception->getLimitName())->toBe('maxHeaderLineLength');
        }
    }
);

it(
    'enforces maximum decoded part size',
    function () {
        $payload = str_repeat('x', 20);
        $raw = "Content-Type: text/plain\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . base64_encode($payload);
        $options = new ParserOptions(maxDecodedPartBytes: 19);

        expect(fn () => Message::fromString($raw, false, $options))
            ->toThrow(ParserLimitExceededException::class);

        try {
            Message::fromString($raw, false, $options);
        } catch (ParserLimitExceededException $exception) {
            expect($exception->getLimitName())->toBe('maxDecodedPartBytes')
                ->and($exception->getActualValue())->toBe(20);
        }
    }
);

it(
    'applies limits through fromFile',
    function () {
        $path = sys_get_temp_dir() . '/mime-mail-parser-limit-' . uniqid('', true) . '.eml';
        $raw = "Content-Type: text/plain\r\n\r\nBody that is long enough";
        file_put_contents($path, $raw);

        try {
            $options = new ParserOptions(maxMessageBytes: 10);
            expect(fn () => Message::fromFile($path, false, $options))
                ->toThrow(ParserLimitExceededException::class);
        } finally {
            @unlink($path);
        }
    }
);

it(
    'still parses all fixture emails with default limits',
    function () {
        $fixtures = glob(__DIR__ . '/../Fixtures/*.eml') ?: [];

        expect($fixtures)->not->toBeEmpty();

        foreach ($fixtures as $fixture) {
            $message = Message::fromFile($fixture);
            expect($message->getParts())->not->toBeEmpty();
        }
    }
);
