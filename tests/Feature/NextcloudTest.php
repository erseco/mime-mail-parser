<?php

namespace Tests\Feature;

use Erseco\Message;

test('can parse a nextcloud message', function () {
    $message = Message::fromFile(__DIR__ . '/../Fixtures/raw_email_from_nextcloud.eml');

    expect($message->getFrom())->toBe('Nextcloud <no-reply@example.com>')
        ->and($message->getTo())->toBe('receiver@example.com')
        ->and($message->getSubject())->toBe('Test Subject')
        ->and($message->getContentType())->toContain('multipart/alternative');

    $parts = $message->getParts();
    expect($parts)->toHaveCount(2)
        ->and($parts[0]->getContentType())->toContain('text/plain')
        ->and($parts[1]->getContentType())->toContain('text/html');
});

test('can parse a nextcloud message with attachments', function () {
    $message = Message::fromFile(__DIR__ . '/../Fixtures/raw_email_from_nextcloud_attachments.eml');

    expect($message->getFrom())->toBe('Nextcloud <no-reply@example.com>')
        ->and($message->getTo())->toBe('receiver@example.com')
        ->and($message->getSubject())->toBe('Test Subject with Attachments');

    $attachments = $message->getAttachments();
    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]->getFilename())->toBe('document.pdf')
        ->and($attachments[0]->getContentType())->toContain('application/pdf');
});
