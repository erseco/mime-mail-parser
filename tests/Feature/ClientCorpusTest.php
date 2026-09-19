<?php

declare(strict_types=1);

/**
 * Representative mail-client corpus tests.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Feature;

use Erseco\MimeMailParser\Message;

it(
    'parses representative desktop mail client messages',
    function (string $fixture, string $subject, int $parts) {
        $message = Message::fromFile(__DIR__ . '/../Fixtures/' . $fixture);

        expect($message->getSubject())->toContain($subject)
            ->and($message->getParts())->toHaveCount($parts)
            ->and($message->getFromAddresses())->not->toBeEmpty()
            ->and($message->getToAddresses())->not->toBeEmpty();
    }
)->with([
    ['outlook_email.eml', 'Outlook', 2],
    ['apple_mail_email.eml', 'Apple Mail', 2],
    ['thunderbird_email.eml', 'Thunderbird', 2],
]);

it(
    'parses the expected client-specific content',
    function () {
        $outlook = Message::fromFile(__DIR__ . '/../Fixtures/outlook_email.eml');
        $apple = Message::fromFile(__DIR__ . '/../Fixtures/apple_mail_email.eml');
        $thunderbird = Message::fromFile(__DIR__ . '/../Fixtures/thunderbird_email.eml');

        expect($outlook->getTextPart()?->getContent())->toContain('Plain Outlook body')
            ->and($outlook->getHtmlPart()?->getContent())->toContain('HTML Outlook body')
            ->and($apple->getAttachments())->toHaveCount(1)
            ->and($apple->getAttachments()[0]->getFilename())->toBe('notes.txt')
            ->and($apple->getAttachments()[0]->getContent())->toBe('Apple Mail attachment')
            ->and($thunderbird->getToAddresses()[0]->name)->toBe('Doe, Jane')
            ->and($thunderbird->getHtmlPart()?->getContent())->toContain('Thunderbird');
    }
);
