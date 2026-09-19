<?php

declare(strict_types=1);

/**
 * Stream parsing tests.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Unit;

use Erseco\MimeMailParser\Message;
use Erseco\MimeMailParser\ParserLimitExceededException;
use Erseco\MimeMailParser\ParserOptions;

it(
    'parses a message from a stream without closing it',
    function () {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create temporary stream.');
        }

        fwrite($stream, "Subject: Stream\r\nContent-Type: text/plain\r\n\r\nBody");
        rewind($stream);

        $message = Message::fromStream($stream);

        expect($message->getSubject())->toBe('Stream')
            ->and($message->getTextPart()?->getContent())->toBe('Body')
            ->and(is_resource($stream))->toBeTrue();

        fclose($stream);
    }
);

it(
    'reads from the current stream position',
    function () {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create temporary stream.');
        }

        fwrite($stream, "ignored\nSubject: Positioned\r\n\r\nBody");
        fseek($stream, 8);

        $message = Message::fromStream($stream);

        expect($message->getSubject())->toBe('Positioned');

        fclose($stream);
    }
);

it(
    'rejects non stream resources',
    function () {
        Message::fromStream('not-a-stream');
    }
)->throws(\InvalidArgumentException::class, 'Expected a readable stream resource.');

it(
    'enforces message limits while reading streams',
    function () {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create temporary stream.');
        }

        fwrite($stream, str_repeat('A', 64));
        rewind($stream);

        try {
            Message::fromStream(
                $stream,
                false,
                new ParserOptions(maxMessageBytes: 32)
            );
        } finally {
            fclose($stream);
        }
    }
)->throws(ParserLimitExceededException::class);
