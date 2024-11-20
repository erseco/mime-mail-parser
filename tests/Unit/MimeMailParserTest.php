<?php

/**
 * Unit tests for MimeMailParser class
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Erseco\MimeMailParser\Tests\Unit;

require_once __DIR__ . '/../../src/MimeMailParser.php';


use Erseco\MimeMailParser;



it(
    'can parse a simple mail message', function () {
        $messageString = <<<EOF
From: Sender <no-reply@example.com>
To: Receiver <receiver@example.com>
Subject: Test Subject
Message-ID: <6e30b164904cf01158c7cc58f144b9ca@example.com>
MIME-Version: 1.0
Date: Fri, 25 Aug 2023 15:36:13 +0200
Content-Type: text/html; charset=utf-8
Content-Transfer-Encoding: quoted-printable

Email content goes here.
EOF;

        $message = new MimeMailParser($messageString);

        expect($message->getFrom())->toBe('Sender <no-reply@example.com>')
        ->and($message->getTo())->toBe('Receiver <receiver@example.com>')
        ->and($message->getSubject())->toBe('Test Subject')
        ->and($message->getId())->toBe('<6e30b164904cf01158c7cc58f144b9ca@example.com>')
        ->and($message->getDate()->format('Y-m-d H:i:s'))->toBe('2023-08-25 15:36:13')
        ->and($message->getContentType())->toBe('text/html; charset=utf-8')
        ->and($message->getHtmlPart())->toBe('Email content goes here.')
        ->and($message->getHeaders())->toBe([
            'from' => 'Sender <no-reply@example.com>',
            'to' => 'Receiver <receiver@example.com>',
            'subject' => 'Test Subject',
            'message-id' => '<6e30b164904cf01158c7cc58f144b9ca@example.com>',
            'mime-version' => '1.0',
            'date' => 'Fri, 25 Aug 2023 15:36:13 +0200',
            'content-type' => 'text/html; charset=utf-8',
        ]);
    }
);

it(
    'can parse lowercase headers', function () {
        $messageString = <<<EOF
from: Sender <no-reply@example.com>
to: Receiver <receiver@example.com>
subject: Test Subject
message-id: <6e30b164904cf01158c7cc58f144b9ca@example.com>
mime-version: 1.0
date: Fri, 25 Aug 2023 15:36:13 +0200
content-type: text/html; charset=utf-8
content-transfer-encoding: quoted-printable

Email content goes here.
EOF;

        $message = new MimeMailParser($messageString);

        expect($message->getHeaders())->toBe(
            [
            'from' => 'Sender <no-reply@example.com>',
            'to' => 'Receiver <receiver@example.com>',
            'subject' => 'Test Subject',
            'message-id' => '<6e30b164904cf01158c7cc58f144b9ca@example.com>',
            'mime-version' => '1.0',
            'date' => 'Fri, 25 Aug 2023 15:36:13 +0200',
            'content-type' => 'text/html; charset=utf-8',
            ]
        )
        ->and($message->getFrom())->toBe('Sender <no-reply@example.com>')
        ->and($message->getHeader('content-type'))->toBe('text/html; charset=utf-8');
    }
);

it(
    'can parse a mail message with boundaries', function () {
        $messageString = <<<EOF
From: sender@example.com
To: recipient@example.com
Cc: cc@example.com
Bcc: bcc@example.com
Subject: This is an email with common headers
Date: Thu, 24 Aug 2023 21:15:01 PST
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="----=_Part_1_1234567890"

------=_Part_1_1234567890
Content-Type: text/plain; charset="utf-8"

This is the text version of the email.

------=_Part_1_1234567890
Content-Type: text/html; charset="utf-8"

<html>
<head>
<title>This is an HTML email</title>
</head>
<body>
<h1>This is the HTML version of the email</h1>
</body>
</html>

------=_Part_1_1234567890--
EOF;

        $message = new MimeMailParser($messageString);

        expect($message->getHeaders())->toBe(
            [
            'from' => 'sender@example.com',
            'to' => 'recipient@example.com',
            'cc' => 'cc@example.com',
            'bcc' => 'bcc@example.com',
            'subject' => 'This is an email with common headers',
            'date' => 'Thu, 24 Aug 2023 21:15:01 PST',
            'mime-version' => '1.0',
            'content-type' => 'multipart/mixed; boundary="----=_Part_1_1234567890"',
            ]
        )
        ->and($message->getSubject())->toBe('This is an email with common headers')
        ->and($message->getFrom())->toBe('sender@example.com')
        ->and($message->getTo())->toBe('recipient@example.com')
        ->and($message->getDate()->format('Y-m-d H:i:s'))->toBe('2023-08-24 21:15:01');

        $parts = $message->getParts();

        expect($parts)->toHaveCount(2)
            ->and($parts[0]->contentType)->toBe('text/plain')
            ->and($parts[0]->content)->toBe('This is the text version of the email.')
            ->and($parts[1]->contentType)->toBe('text/html')
            ->and($parts[1]->content)->toBe(
                <<<EOF
                <html>
                <head>
                <title>This is an HTML email</title>
                </head>
                <body>
                <h1>This is the HTML version of the email</h1>
                </body>
                </html>
                EOF
            );
    }
);

it(
    'can parse a multi-format mail message', function () {
        $messageString = <<<EOF
From: sender@example.com
To: recipient@example.com
Subject: Multi-format test
MIME-Version: 1.0
Content-Type: multipart/alternative; boundary="boundary1"

--boundary1
Content-Type: text/plain; charset="utf-8"

This is a plain text version.

--boundary1
Content-Type: text/html; charset="utf-8"

<html><body><p>This is the HTML version.</p></body></html>

--boundary1--
EOF;

        $message = new MimeMailParser($messageString);

        expect($message->getParts())->toHaveCount(2)
        ->and($message->getParts()[0]->contentType)->toBe('text/plain')
        ->and($message->getParts()[0]->content)->toBe('This is a plain text version.')
        ->and($message->getParts()[1]->contentType)->toBe('text/html')
        ->and($message->getParts()[1]->content)->toBe('<html><body><p>This is the HTML version.</p></body></html>');
    }
);


it('can parse a complex mail message', function () {

    $rawEmail = file_get_contents(__DIR__ . '/../Fixtures/complex_email.eml');
    $message = new MimeMailParser($rawEmail);

    expect($message->getFrom())->toBe('Service <no-reply@example.com>')
        ->and($message->getTo())->toBe('John Smith <john@example.com>')
        ->and($message->getReplyTo())->toBe('Service <service@example.com>')
        ->and($message->getSubject())->toBe('Appointment confirmation')
        ->and($message->getId())->toBe('<fddff4779513441c3f0c1811193f5b12@example.com>')
        ->and($message->getDate()->format('Y-m-d H:i:s'))->toBe('2023-08-24 14:51:14')
        ->and($message->getBoundary())->toBe('lGiKDww4');

    $parts = $message->getParts();

    expect($parts)->toHaveCount(2)
        ->and($parts[0]->contentType)->toBe('text/html; charset=utf-8')
        ->and($parts[0]->headers)->toBe([
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ])
        ->and($parts[1]->contentType)->toBe('text/calendar; name=Appointment.ics')
        ->and($parts[1]->headers)->toBe([
            'Content-Type' => 'text/calendar; name=Appointment.ics',
            'Content-Transfer-Encoding' => 'base64',
            'Content-Disposition' => 'attachment; name=Appointment.ics;
 filename=Appointment.ics',
        ]);
});

it('can parse a multi-format mail message from file', function () {

    $rawEmail = file_get_contents(__DIR__ . '/../Fixtures/multiformat_email.eml');
    $message = new MimeMailParser($rawEmail);

    expect($message->getFrom())->toBe('Service <no-reply@example.com>')
        ->and($message->getTo())->toBe('John Smith <john@example.com>')
        ->and($message->getReplyTo())->toBe('Service <service@example.com>')
        ->and($message->getSubject())->toBe('Appointment confirmation')
        ->and($message->getId())->toBe('<fddff4779513441c3f0c1811193f5b12@example.com>')
        ->and($message->getDate()->format('Y-m-d H:i:s'))->toBe('2023-08-24 14:51:14')
        ->and($message->getBoundary())->toBe('s1NCDW_3');

    $parts = $message->getParts();

    expect($parts)->toHaveCount(2)
        ->and($parts[0]->contentType)->toBe('text/plain; charset=utf-8')
        ->and($parts[0]->headers)->toBe([
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ])
        ->and($parts[1]->contentType)->toBe('text/html; charset=utf-8')
        ->and($parts[1]->headers)->toBe([
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ])->and($message->getTextPart())->toBe(<<<EOF
Hi Arunas Skirius,
This is a confirmation of your appointment.
EOF);

});

it('can parse a multi-format mail message from file with attachments', function () {

    $rawEmail = file_get_contents(__DIR__ . '/../Fixtures/attachment_email.eml');
    $message = new MimeMailParser($rawEmail);


    expect($message->getFrom())->toBe('Service <no-reply@example.com>')
        ->and($message->getTo())->toBe('John Smith <john@example.com>')
        ->and($message->getSubject())->toBe('test')
        ->and($message->getDate()->format('Y-m-d H:i:s'))->toBe('2024-11-18 22:11:51')
        ->and($message->getBoundary())->toBe('=_ZW4EMn2xKVeeM2hY-wks98q');

    $parts = $message->getParts();

    expect($parts)->toHaveCount(2)
        ->and($parts[0]->contentType)->toBe('text/plain; charset=utf-8')
        ->and($parts[0]->headers)->toBe([
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ])
        ->and($parts[1]->contentType)->toBe('text/html; charset=utf-8')
        ->and($parts[1]->headers)->toBe([
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ])->and($message->getTextPart()?->getContent())->toBe(<<<EOF
Hi Arunas Skirius,
This is a confirmation of your appointment.
EOF);

});


