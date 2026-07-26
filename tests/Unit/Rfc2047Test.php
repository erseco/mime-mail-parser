<?php

/**
 * Tests for RFC 2047 encoded header decoding.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Unit;

use Erseco\Message;

it(
    'keeps getHeader raw while decoding UTF-8 base64 subjects',
    function () {
        $raw = '=?UTF-8?B?UmV1bmnDs24gZGUgcHJveWVjdG8=?=';
        $message = Message::fromString("Subject: {$raw}\r\n\r\nBody");

        expect($message->getHeader('Subject'))->toBe($raw)
            ->and($message->getSubject())->toBe($raw)
            ->and($message->getDecodedHeader('Subject'))->toBe('Reunión de proyecto')
            ->and($message->getDecodedSubject())->toBe('Reunión de proyecto');
    }
);

it(
    'decodes ISO-8859-1 quoted-printable encoded words',
    function () {
        $raw = '=?ISO-8859-1?Q?Jos=E9_P=E9rez?=';
        $message = Message::fromString("From: {$raw} <jose@example.com>\r\n\r\nBody");

        expect($message->getFrom())->toBe($raw . ' <jose@example.com>')
            ->and($message->getDecodedFrom())->toBe('José Pérez <jose@example.com>');
    }
);

it(
    'joins multiple adjacent UTF-8 Q encoded words',
    function () {
        $raw = '=?UTF-8?Q?Factura_?= =?UTF-8?Q?electr=C3=B3nica?=';
        $message = Message::fromString("Subject: {$raw}\r\n\r\nBody");

        expect($message->getDecodedSubject())->toBe('Factura electrónica');
    }
);

it(
    'preserves plain text mixed with encoded words',
    function () {
        $raw = 'Normal text =?UTF-8?B?Y2Fmw6k=?=';
        $message = Message::fromString("Subject: {$raw}\r\n\r\nBody");

        expect($message->getDecodedSubject())->toBe('Normal text café');
    }
);

it(
    'decodes case-insensitive encoding identifiers',
    function () {
        $raw = '=?utf-8?b?Y2Fmw6k=?=';
        $message = Message::fromString("Subject: {$raw}\r\n\r\nBody");

        expect($message->getDecodedSubject())->toBe('café');
    }
);

it(
    'decodes windows-1252 encoded words',
    function () {
        // "café" in Windows-1252 is 63 61 66 E9 → base64: Y2Fm6Q==
        $raw = '=?Windows-1252?B?Y2Fm6Q==?=';
        $message = Message::fromString("Subject: {$raw}\r\n\r\nBody");

        expect($message->getDecodedSubject())->toBe('café');
    }
);

it(
    'decodes folded encoded headers',
    function () {
        $message = Message::fromString(
            "Subject: =?UTF-8?B?UmV1bmk=?=\r\n"
            . " =?UTF-8?B?w7Nu?= de proyecto\r\n\r\nBody"
        );

        expect($message->getDecodedSubject())->toBe('Reunión de proyecto');
    }
);

it(
    'leaves malformed encoded words without data loss',
    function () {
        $raw = '=?UTF-8?B?not-valid-base64!!!?= plain =?broken?= end';
        $message = Message::fromString("Subject: {$raw}\r\n\r\nBody");

        expect($message->getDecodedSubject())->toContain('plain')
            ->and($message->getDecodedSubject())->toContain('end')
            ->and($message->getDecodedHeader('Subject'))->not->toBeEmpty();
    }
);

it(
    'decodes To and Reply-To convenience accessors',
    function () {
        $encoded = '=?UTF-8?Q?Ana?= <ana@example.com>';
        $message = Message::fromString(
            "To: {$encoded}\r\n"
            . "Reply-To: {$encoded}\r\n\r\nBody"
        );

        expect($message->getDecodedTo())->toBe('Ana <ana@example.com>')
            ->and($message->getDecodedReplyTo())->toBe('Ana <ana@example.com>');
    }
);

it(
    'returns default when decoded header is missing',
    function () {
        $message = Message::fromString("Subject: Hello\r\n\r\nBody");

        expect($message->getDecodedHeader('X-Missing', 'fallback'))->toBe('fallback');
    }
);
