<?php

declare(strict_types=1);

/**
 * Structured address parsing tests.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Unit;

use Erseco\MimeMailParser\Address;
use Erseco\MimeMailParser\Message;

it(
    'parses structured mailbox lists',
    function () {
        $addresses = Address::parseList(
            '"Doe, John" <john@example.com>, =?UTF-8?Q?Mar=C3=ADa?= <maria@example.com>, bare@example.com'
        );

        expect($addresses)->toHaveCount(3)
            ->and($addresses[0]->name)->toBe('Doe, John')
            ->and($addresses[0]->email)->toBe('john@example.com')
            ->and((string) $addresses[0])->toBe('Doe, John <john@example.com>')
            ->and($addresses[1]->name)->toBe('María')
            ->and($addresses[1]->email)->toBe('maria@example.com')
            ->and($addresses[2]->name)->toBeNull()
            ->and($addresses[2]->email)->toBe('bare@example.com');
    }
);

it(
    'exposes common address headers and structured variants',
    function () {
        $message = Message::fromString(
            "From: Sender <sender@example.com>\r\n"
            . "To: One <one@example.com>, two@example.com\r\n"
            . "Cc: =?UTF-8?Q?Mar=C3=ADa?= <maria@example.com>\r\n"
            . "Bcc: hidden@example.com\r\n"
            . "Reply-To: replies@example.com\r\n"
            . "Message-ID: <message@example.com>\r\n\r\n"
            . "Body"
        );

        expect($message->getCc())->toContain('Mar')
            ->and($message->getBcc())->toBe('hidden@example.com')
            ->and($message->getDecodedCc())->toBe('María <maria@example.com>')
            ->and($message->getDecodedBcc())->toBe('hidden@example.com')
            ->and($message->getMessageId())->toBe('message@example.com')
            ->and($message->getFromAddresses()[0]->email)->toBe('sender@example.com')
            ->and($message->getToAddresses())->toHaveCount(2)
            ->and($message->getCcAddresses()[0]->name)->toBe('María')
            ->and($message->getBccAddresses()[0]->email)->toBe('hidden@example.com')
            ->and($message->getReplyToAddresses()[0]->email)->toBe('replies@example.com');
    }
);

it(
    'serializes addresses predictably',
    function () {
        $address = new Address('user@example.com', 'User');

        expect($address->toArray())->toBe([
            'email' => 'user@example.com',
            'name' => 'User',
        ])->and($address->jsonSerialize())->toBe($address->toArray());
    }
);
