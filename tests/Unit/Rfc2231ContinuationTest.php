<?php

/**
 * Tests for RFC 2231 continued filename parameters.
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
    'assembles UTF-8 filename continuations',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment;\r\n"
                    . " filename*0*=UTF-8''quarterly%20;\r\n"
                    . " filename*1*=report%20;\r\n"
                    . " filename*2*=2026.pdf",
            ]
        );

        expect($part->getFilename())->toBe('quarterly report 2026.pdf')
            ->and($part->isAttachment())->toBeTrue();
    }
);

it(
    'supports ISO-8859-1 continued filenames',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => "attachment; "
                    . "filename*0*=ISO-8859-1''caf%E9%20; "
                    . "filename*1*=menu.pdf",
            ]
        );

        expect($part->getFilename())->toBe('café menu.pdf');
    }
);

it(
    'supports quoted continuation segments and punctuation',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => 'attachment; '
                    . 'filename*0="invoice (final)_"; '
                    . 'filename*1="2026-Q1.pdf"',
            ]
        );

        expect($part->getFilename())->toBe('invoice (final)_2026-Q1.pdf');
    }
);

it(
    'sorts out-of-order continuation segments',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => "attachment; "
                    . "filename*2*=c.pdf; "
                    . "filename*0*=UTF-8''a%20; "
                    . "filename*1*=b%20",
            ]
        );

        expect($part->getFilename())->toBe('a b c.pdf');
    }
);

it(
    'skips missing middle segments deterministically',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => "attachment; "
                    . "filename*0*=UTF-8''start-; "
                    . "filename*2*=end.pdf",
            ]
        );

        expect($part->getFilename())->toBe('start-end.pdf');
    }
);

it(
    'keeps the last value for duplicate continuation indexes',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => "attachment; "
                    . "filename*0*=UTF-8''old; "
                    . "filename*0*=UTF-8''new; "
                    . "filename*1*=.txt",
            ]
        );

        expect($part->getFilename())->toBe('new.txt');
    }
);

it(
    'falls back to plain filename parameter',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => 'attachment; filename="plain.txt"',
            ]
        );

        expect($part->getFilename())->toBe('plain.txt');
    }
);

it(
    'keeps single filename* extended parameter behaviour',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => "attachment; filename*=UTF-8''caf%C3%A9.txt",
            ]
        );

        expect($part->getFilename())->toBe('café.txt');
    }
);

it(
    'prefers Content-Disposition filename over Content-Type name',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Type' => 'application/octet-stream; name="from-type.bin"',
                'Content-Disposition' => "attachment; "
                    . "filename*0*=UTF-8''from-; "
                    . "filename*1*=disposition.bin",
            ]
        );

        expect($part->getFilename())->toBe('from-disposition.bin');
    }
);

it(
    'supports name* continuations on Content-Type when disposition lacks a filename',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Type' => "application/pdf; "
                    . "name*0*=UTF-8''type-; "
                    . "name*1*=name.pdf",
            ]
        );

        expect($part->getFilename())->toBe('type-name.pdf');
    }
);

it(
    'handles mixed-case parameter names',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => "Attachment; "
                    . "FileName*0*=UTF-8''Case; "
                    . "FILENAME*1*=.pdf",
            ]
        );

        expect($part->getFilename())->toBe('Case.pdf');
    }
);

it(
    'parses continued filenames from a full message string',
    function () {
        $raw = "From: a@example.com\r\n"
            . "To: b@example.com\r\n"
            . "Subject: attachment\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n"
            . "--b1\r\n"
            . "Content-Type: text/plain\r\n\r\n"
            . "Hello\r\n"
            . "--b1\r\n"
            . "Content-Type: application/pdf\r\n"
            . "Content-Disposition: attachment;\r\n"
            . " filename*0*=UTF-8''quarterly%20;\r\n"
            . " filename*1*=report%20;\r\n"
            . " filename*2*=2026.pdf\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . "AA==\r\n"
            . "--b1--\r\n";

        $message = Message::fromString($raw);

        expect($message->getAttachments())->toHaveCount(1)
            ->and($message->getAttachments()[0]->getFilename())
            ->toBe('quarterly report 2026.pdf');
    }
);
