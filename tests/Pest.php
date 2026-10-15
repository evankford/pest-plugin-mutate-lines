<?php

declare(strict_types=1);

// Created by Claude - 2026-10-15

function temporaryDirectory(string $name): string
{
    $directory = sys_get_temp_dir().'/'.$name.'-'.getmypid();
    removeDirectory($directory);
    mkdir($directory, recursive: true);

    return $directory;
}

function writeFile(string $path, string $contents): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), recursive: true);
    }

    file_put_contents($path, $contents);
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($directory);
}
