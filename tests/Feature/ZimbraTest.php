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
    expect($parts)->toHaveCount(2)
        ->and($parts[0]->getContentType())->toContain('text/plain')
        ->and($parts[1]->getContentType())->toContain('text/html');
});

test('can parse a zimbra message with attachments', function () {
    $message = Message::fromFile(__DIR__ . '/../Fixtures/raw_email_from_zimbra_attachments.eml');

    expect($message->getFrom())->toBe('Test User 2 <test2@example.com>')
        ->and($message->getTo())->toBe('taskwp <receiver@example.com>')
        ->and($message->getSubject())->toBe('test from zimbra with attachments');

    $attachments = $message->getAttachments();
    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]->getFilename())->toBe('document.pdf')
        ->and($attachments[0]->getContentType())->toContain('application/pdf');
});

test('can parse a zimbra message with embedded content', function () {
    $message = Message::fromFile(__DIR__ . '/../Fixtures/raw_email_from_zimbra_embedded.eml');

    expect($message->getFrom())->toBe('Test User 2 <test2@example.com>')
        ->and($message->getTo())->toBe('taskwp <receiver@example.com>')
        ->and($message->getSubject())->toBe('test from zimbra with embedded image');

    $parts = $message->getParts();
    expect($parts)->toHaveCount(3)
        ->and($parts[0]->getContentType())->toContain('text/plain')
        ->and($parts[1]->getContentType())->toContain('text/html')
        ->and($parts[2]->getContentType())->toContain('image/');
});
