<?php

declare(strict_types=1);

/**
 * MIME metadata API tests.
 *
 * @category Tests
 * @package  MimeMailParser
 * @author   Ernesto Serrano <info@ernesto.es>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/erseco/mime-mail-parser
 */

namespace Tests\Unit;

use Erseco\MimeMailParser\MessagePart;

it(
    'separates media types from content type parameters',
    function () {
        $part = new MessagePart(
            'Body',
            [
                'Content-Type' => 'Text/Plain; charset="utf-8"; format=flowed',
            ]
        );

        expect($part->getMediaType())->toBe('text/plain')
            ->and($part->getContentTypeParameters())->toBe([
                'charset' => 'utf-8',
                'format' => 'flowed',
            ])
            ->and($part->isText())->toBeTrue();
    }
);

it(
    'exposes normalized disposition and RFC 2231 parameters',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Type' => 'application/pdf; name="fallback.pdf"',
                'Content-Disposition' => "Attachment; filename*=UTF-8''caf%C3%A9.pdf",
            ]
        );

        expect($part->getDisposition())->toBe('attachment')
            ->and($part->getDispositionParameters())->toBe([
                'filename' => 'café.pdf',
            ])
            ->and($part->getFilename())->toBe('café.pdf')
            ->and($part->isAttachment())->toBeTrue();
    }
);

it(
    'collapses continued parameters to their base name',
    function () {
        $part = new MessagePart(
            'data',
            [
                'Content-Disposition' => "attachment; "
                    . "filename*0*=UTF-8''quarterly%20; "
                    . "filename*1*=report.pdf",
            ]
        );

        expect($part->getDispositionParameters())->toBe([
            'filename' => 'quarterly report.pdf',
        ]);
    }
);

it(
    'returns empty metadata when headers are absent',
    function () {
        $part = new MessagePart('Body');

        expect($part->getMediaType())->toBe('')
            ->and($part->getContentTypeParameters())->toBe([])
            ->and($part->getDisposition())->toBeNull()
            ->and($part->getDispositionParameters())->toBe([]);
    }
);
