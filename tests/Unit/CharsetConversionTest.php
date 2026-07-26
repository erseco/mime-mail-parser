<?php

/**
 * Tests for optional text content conversion to UTF-8.
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
    'converts quoted-printable ISO-8859-1 text to UTF-8',
    function () {
        $part = new MessagePart(
            'Caf=E9',
            [
                'Content-Type' => 'text/plain; charset=ISO-8859-1',
                'Content-Transfer-Encoding' => 'quoted-printable',
            ]
        );

        expect($part->getContent())->toBe("Caf\xE9")
            ->and($part->getContentAsUtf8())->toBe('Café')
            ->and($part->getCharset())->toBe('ISO-8859-1');
    }
);

it(
    'converts base64 Windows-1252 text to UTF-8',
    function () {
        // "café" in Windows-1252
        $part = new MessagePart(
            base64_encode("caf\xE9"),
            [
                'Content-Type' => 'text/plain; charset="Windows-1252"',
                'Content-Transfer-Encoding' => 'base64',
            ]
        );

        expect($part->getContentAsUtf8())->toBe('café');
    }
);

it(
    'returns UTF-8 text unchanged',
    function () {
        $part = new MessagePart(
            'Reunión',
            ['Content-Type' => 'text/plain; charset=utf-8']
        );

        expect($part->getContentAsUtf8())->toBe('Reunión')
            ->and($part->getContent())->toBe('Reunión');
    }
);

it(
    'treats US-ASCII as already compatible',
    function () {
        $part = new MessagePart(
            'Hello ASCII',
            ['Content-Type' => 'text/plain; charset=us-ascii']
        );

        expect($part->getContentAsUtf8())->toBe('Hello ASCII');
    }
);

it(
    'returns transfer-decoded content for unknown charset',
    function () {
        $part = new MessagePart(
            "Caf\xE9",
            ['Content-Type' => 'text/plain; charset=x-unknown-charset']
        );

        expect($part->getContentAsUtf8())->toBe("Caf\xE9")
            ->and($part->getContent())->toBe("Caf\xE9");
    }
);

it(
    'returns content as-is when charset is missing',
    function () {
        $part = new MessagePart(
            'No charset here',
            ['Content-Type' => 'text/plain']
        );

        expect($part->getCharset())->toBeNull()
            ->and($part->getContentAsUtf8())->toBe('No charset here');
    }
);

it(
    'converts HTML text parts',
    function () {
        $part = new MessagePart(
            'Hola =E1',
            [
                'Content-Type' => 'text/html; charset=iso-8859-1',
                'Content-Transfer-Encoding' => 'quoted-printable',
            ]
        );

        expect($part->getContentAsUtf8())->toBe("Hola \xC3\xA1");
    }
);

it(
    'does not convert binary attachments',
    function () {
        $binary = "\x00\x01\xFF\xE9";
        $part = new MessagePart(
            base64_encode($binary),
            [
                'Content-Type' => 'application/octet-stream; charset=ISO-8859-1',
                'Content-Transfer-Encoding' => 'base64',
                'Content-Disposition' => 'attachment; filename="bin.dat"',
            ]
        );

        expect($part->getContentAsUtf8())->toBe($binary)
            ->and($part->getContent())->toBe($binary);
    }
);

it(
    'handles mixed-case charset names',
    function () {
        $part = new MessagePart(
            "Caf\xE9",
            ['Content-Type' => 'text/plain; Charset=Iso-8859-1']
        );

        expect($part->getContentAsUtf8())->toBe('Café');
    }
);

it(
    'does not throw on malformed sequences for declared UTF-8',
    function () {
        $invalid = "a\xC3"; // truncated multi-byte sequence
        $part = new MessagePart(
            $invalid,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );

        expect($part->getContentAsUtf8())->toBe($invalid);
    }
);

it(
    'works through Message::fromString text parts',
    function () {
        $raw = "Content-Type: text/plain; charset=ISO-8859-1\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . "Se=F1or";

        $message = Message::fromString($raw);

        expect($message->getTextPart()?->getContentAsUtf8())->toBe('Señor')
            ->and($message->getTextPart()?->getContent())->toBe("Se\xF1or");
    }
);
