<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

namespace Evankford\PestPluginMutateLines;

use Pest\Exceptions\InvalidOption;

readonly class LineSelection
{
    /** @var array<string, list<int>> */
    public array $linesByFile;

    /**
     * @param  array<string, array<int, int>>  $linesByFile  keyed by real path
     */
    public function __construct(array $linesByFile = [])
    {
        $this->linesByFile = array_map(function (array $lines): array {
            $lines = array_unique($lines);
            sort($lines);

            return $lines;
        }, $linesByFile);
    }

    /**
     * @param  array<int, string>  $specs  e.g. `app/Foo.php:12,40-55`
     */
    public static function fromSpecs(array $specs, string $basePath): self
    {
        $selection = new self;

        foreach ($specs as $spec) {
            $separator = strrpos($spec, ':');

            if ($separator === false) {
                throw new InvalidOption(sprintf('Expected --mutate-lines=path:ranges, got "%s".', $spec));
            }

            $path = substr($spec, 0, $separator);
            $realPath = realpath(str_starts_with($path, '/') ? $path : $basePath.'/'.$path);

            if ($realPath === false) {
                throw new InvalidOption(sprintf('File not found: "%s".', $path));
            }

            $lines = [];

            foreach (explode(',', substr($spec, $separator + 1)) as $range) {
                $range = trim($range);

                if (preg_match('/^(\d+)(?:-(\d+))?$/', $range, $matches) !== 1) {
                    throw self::invalidRange($range, $spec);
                }

                $start = (int) $matches[1]; // @pest-mutate-ignore
                $end = (int) ($matches[2] ?? $start); // @pest-mutate-ignore

                if ($end < $start) {
                    throw self::invalidRange($range, $spec);
                }

                $lines = [...$lines, ...range($start, $end)];
            }

            $selection = $selection->merge(new self([$realPath => $lines]));
        }

        return $selection;
    }

    public static function fromUnifiedDiff(string $diff, string $repositoryRoot): self
    {
        $linesByFile = [];
        $file = false;

        foreach (explode("\n", $diff) as $line) {
            if (str_starts_with($line, '+++ ')) {
                $file = preg_match('#^\+\+\+ b/(.+)$#', $line, $path) === 1
                    ? realpath($repositoryRoot.'/'.$path[1])
                    : false;

                continue;
            }

            if ($file !== false && preg_match('/^@@ -\S+ \+(\d+)(?:,(\d+))? @@/', $line, $matches) === 1) {
                $start = (int) $matches[1]; // @pest-mutate-ignore
                $count = (int) ($matches[2] ?? 1); // @pest-mutate-ignore

                if ($count > 0) {
                    $linesByFile[$file] = [
                        ...$linesByFile[$file] ?? [],
                        ...range($start, $start + $count - 1),
                    ];
                }
            }
        }

        return new self($linesByFile);
    }

    /**
     * @param  array<int, string>  $relativePaths
     */
    public static function wholeFiles(array $relativePaths, string $repositoryRoot): self
    {
        $linesByFile = [];

        foreach ($relativePaths as $path) {
            $realPath = realpath($repositoryRoot.'/'.$path);

            if ($realPath === false) {
                continue;
            }

            $lineCount = count(file($realPath) ?: []); // @pest-mutate-ignore

            if ($lineCount > 0) {
                $linesByFile[$realPath] = range(1, $lineCount);
            }
        }

        return new self($linesByFile);
    }

    private static function invalidRange(string $range, string $spec): InvalidOption
    {
        return new InvalidOption(sprintf('Invalid line range "%s" in "%s".', $range, $spec));
    }

    public function merge(self $other): self
    {
        $linesByFile = $this->linesByFile;

        foreach ($other->linesByFile as $file => $lines) {
            $linesByFile[$file] = [...$linesByFile[$file] ?? [], ...$lines];
        }

        return new self($linesByFile);
    }

    /**
     * @return list<int>|null
     */
    public function linesFor(string $realPath): ?array
    {
        return $this->linesByFile[$realPath] ?? null;
    }

    public function lineCount(): int
    {
        return array_sum(array_map(count(...), $this->linesByFile));
    }

    public function fileCount(): int
    {
        return count($this->linesByFile);
    }
}
