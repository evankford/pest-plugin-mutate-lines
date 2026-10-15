<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

namespace Evankford\PestPluginMutateLines;

use Pest\Exceptions\InvalidOption;
use Symfony\Component\Process\Process;

class GitChangedLines
{
    public function __invoke(string $workingDirectory, string $ref): LineSelection
    {
        $root = $this->git($workingDirectory, 'rev-parse', '--show-toplevel');
        $base = $this->git($root, 'merge-base', $ref, 'HEAD');

        $diff = $this->git(
            $root,
            'diff',
            '-U0',
            '--no-color',
            '--no-ext-diff',
            '--src-prefix=a/',
            '--dst-prefix=b/',
            $base,
            '--',
            '*.php',
        );
        $untracked = $this->git($root, 'ls-files', '--others', '--exclude-standard', '--', '*.php');

        return LineSelection::fromUnifiedDiff($diff, $root)
            ->merge(LineSelection::wholeFiles(array_filter(explode("\n", $untracked)), $root));
    }

    private function git(string $workingDirectory, string ...$arguments): string
    {
        $process = new Process(['git', ...$arguments], $workingDirectory);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new InvalidOption(sprintf(
                '--mutate-diff: `git %s` failed: %s',
                implode(' ', $arguments),
                $process->getErrorOutput(),
            ));
        }

        return trim($process->getOutput());
    }
}
