<?php

declare(strict_types=1);

/**
 * Verify that distribution archives contain only runtime package files.
 */

namespace Tests\Unit;

it('exports only runtime sources, composer metadata and the license', function () {
    $root = dirname(__DIR__, 2);
    $output = [];
    $status = 0;
    exec(
        'git -C ' . escapeshellarg($root)
        . ' archive --worktree-attributes --format=tar HEAD | tar -tf -',
        $output,
        $status
    );

    expect($status)->toBe(0);

    $files = array_values(array_filter($output, fn ($path) => !str_ends_with($path, '/')));
    $expected = ['LICENSE.md', 'composer.json'];
    foreach (glob($root . '/src/*.php') as $source) {
        $expected[] = 'src/' . basename($source);
    }
    sort($files);
    sort($expected);

    expect($files)->toBe($expected);
});
