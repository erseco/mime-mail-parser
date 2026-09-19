<?php

declare(strict_types=1);

/**
 * Deterministic parser fuzz and property tests.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Unit;

use Erseco\MimeMailParser\Message;
use Erseco\MimeMailParser\ParserOptions;

it(
    'parses deterministic generated messages consistently',
    function () {
        mt_srand(20260919);

        for ($case = 0; $case < 100; $case++) {
            $headerCount = mt_rand(0, 8);
            $headers = [
                'From: sender' . $case . '@example.com',
                'To: receiver@example.com',
                'Subject: Fuzz case ' . $case,
            ];

            for ($index = 0; $index < $headerCount; $index++) {
                $headers[] = sprintf(
                    'X-Fuzz-%d: %s',
                    $index,
                    bin2hex(pack('N', mt_rand()))
                );
            }

            $separator = match ($case % 3) {
                0 => "\r\n",
                1 => "\n",
                default => "\r",
            };
            $body = str_repeat(chr(65 + ($case % 26)), mt_rand(0, 256));
            $raw = implode($separator, $headers)
                . $separator . $separator
                . $body;

            $first = Message::fromString($raw);
            $second = Message::fromString($raw);

            expect($first->toArray())->toBe($second->toArray())
                ->and($first->getSize())->toBe(strlen($raw))
                ->and(count($first->getParts()))->toBeLessThanOrEqual(1);
        }
    }
);

it(
    'remains deterministic for malformed corpus mutations',
    function (string $fixture) {
        $raw = file_get_contents(__DIR__ . '/../Fixtures/' . $fixture);

        if ($raw === false) {
            throw new \RuntimeException('Unable to load fixture.');
        }

        $variants = [
            str_replace("\r\n", "\n", $raw),
            "garbage before headers\n\n" . $raw,
            preg_replace('/--([^\r\n]+)--\s*$/', '--$1', $raw) ?? $raw,
            str_replace("Subject:", "Broken header\r\nSubject:", $raw),
            substr($raw, 0, max(0, strlen($raw) - 12)),
        ];

        foreach ($variants as $variant) {
            $options = new ParserOptions(
                maxMessageBytes: max(1024, strlen($variant) + 1),
                maxParts: 100,
                maxDepth: 10,
                maxHeaders: 200,
                maxHeaderLineLength: 4096,
                maxDecodedPartBytes: 1024 * 1024
            );

            $first = Message::fromString($variant, false, $options);
            $second = Message::fromString($variant, false, $options);

            expect($first->toArray())->toBe($second->toArray())
                ->and(count($first->getParts()))->toBeLessThanOrEqual(100);
        }
    }
)->with([
    'outlook_email.eml',
    'apple_mail_email.eml',
    'thunderbird_email.eml',
]);
