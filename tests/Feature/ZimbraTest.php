<?php

namespace Tests\Feature;

use Erseco\Message;

test('can parse a zimbra message', function () {
    $message = Message::fromFile(__DIR__ . '/../Fixtures/raw_email_from_zimbra.eml');

    expect($message->getFrom())->toBe('Test User 2 <test2@example.com>')
        ->and($message->getTo())->toBe('taskwp <receiver@example.com>')
        ->and($message->getSubject())->toBe('test from zimbra')
        ->and($message->getContentType())->toBe('text/html; charset=utf-8');

    $parts = $message->getParts();
    expect($parts)->toHaveCount(1)
        ->and($parts[0]->getContentType())->toContain('text/html');
});

test('can parse a zimbra message with attachments', function () {
    $message = Message::fromFile(__DIR__ . '/../Fixtures/raw_email_from_zimbra_attachments.eml');

    expect($message->getFrom())->toBe('Test User 2 <test2@example.com>')
        ->and($message->getTo())->toBe('taskwp <receiver@example.com>')
        ->and($message->getSubject())->toBe('test from zimbra with attachments');

    $attachments = $message->getAttachments();
    expect($attachments)->toHaveCount(2)
        ->and($attachments[0]->getFilename())->toBe('sample-2.jpg')
        ->and($attachments[0]->getContentType())->toContain('application/pdf');
});

test('can parse a zimbra message with embedded content', function () {
    $message = Message::fromFile(__DIR__ . '/../Fixtures/raw_email_from_zimbra_embedded.eml');

    expect($message->getFrom())->toBe('Test User 2 <test2@example.com>')
        ->and($message->getTo())->toBe('taskwp <receiver@example.com>')
        ->and($message->getSubject())->toBe('test from zimbra with embedded image');

    $parts = $message->getParts();
    expect($parts)->toHaveCount(1)
        ->and($parts[0]->getContentType())->toContain('image/jpeg');
});
