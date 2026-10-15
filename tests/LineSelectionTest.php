<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

use Evankford\PestPluginMutateLines\LineSelection;
use Pest\Exceptions\InvalidOption;

// 100% mutation
covers(LineSelection::class);

beforeEach(function () {
    $this->dir = temporaryDirectory('line-selection');
    writeFile($this->dir.'/src/Alpha.php', "<?php\n\n\$a = 1;\n\$b = 2;\n\$c = 3;\n");
    writeFile($this->dir.'/src/Beta.php', "<?php\n\$z = 1;");
    $this->alpha = realpath($this->dir.'/src/Alpha.php');
    $this->beta = realpath($this->dir.'/src/Beta.php');
});

afterEach(function () {
    removeDirectory($this->dir);
});

it('parses single lines and ranges from relative and absolute specs', function () {
    $selection = LineSelection::fromSpecs(['src/Alpha.php:7,2-4', $this->beta.':5'], $this->dir);

    expect($selection->linesFor($this->alpha))->toBe([2, 3, 4, 7])
        ->and($selection->linesFor($this->beta))->toBe([5])
        ->and($selection->lineCount())->toBe(5)
        ->and($selection->fileCount())->toBe(2);
});

it('unions repeated specs for the same file', function () {
    $selection = LineSelection::fromSpecs(['src/Alpha.php:3-5', 'src/Alpha.php:1, 4'], $this->dir);

    expect($selection->linesFor($this->alpha))->toBe([1, 3, 4, 5])
        ->and($selection->lineCount())->toBe(4);
});

it('returns null for a file outside the selection', function () {
    $selection = LineSelection::fromSpecs(['src/Alpha.php:3'], $this->dir);

    expect($selection->linesFor($this->beta))->toBeNull();
});

it('rejects malformed specs', function (string $spec, string $message) {
    expect(fn () => LineSelection::fromSpecs([$spec], $this->dir))
        ->toThrow(InvalidOption::class, $message);
})->with([
    'no line list' => ['src/Alpha.php', 'Expected --mutate-lines=path:ranges, got "src/Alpha.php".'],
    'non-numeric range' => ['src/Alpha.php:3-x', 'Invalid line range "3-x" in "src/Alpha.php:3-x".'],
    'trailing junk' => ['src/Alpha.php:3x', 'Invalid line range "3x" in "src/Alpha.php:3x".'],
    'leading junk' => ['src/Alpha.php:x3', 'Invalid line range "x3" in "src/Alpha.php:x3".'],
    'empty range' => ['src/Alpha.php:', 'Invalid line range "" in "src/Alpha.php:".'],
    'reversed range' => ['src/Alpha.php:9-4', 'Invalid line range "9-4" in "src/Alpha.php:9-4".'],
    'missing file' => ['src/Gamma.php:3', 'File not found: "src/Gamma.php".'],
]);

it('reads added lines from unified diff hunks', function () {
    $diff = <<<'DIFF'
        diff --git a/src/Alpha.php b/src/Alpha.php
        --- a/src/Alpha.php
        +++ b/src/Alpha.php
        @@ -3 +3 @@
        -$a = 0;
        +$a = 1;
        @@ -9,2 +10,3 @@ class Alpha
        @@ -20,4 +22,0 @@
        diff --git a/src/Beta.php b/src/Beta.php
        --- a/src/Beta.php
        +++ b/src/Beta.php
        @@ -1,0 +2,1 @@
        +$z = 1;
        diff --git a/src/Gone.php b/src/Gone.php
        --- a/src/Gone.php
        +++ /dev/null
        @@ -1,3 +0,0 @@
        diff --git a/src/Missing.php b/src/Missing.php
        --- a/src/Missing.php
        +++ b/src/Missing.php
        @@ -1 +1 @@
        DIFF;

    $selection = LineSelection::fromUnifiedDiff($diff, $this->dir);

    expect($selection->linesFor($this->alpha))->toBe([3, 10, 11, 12])
        ->and($selection->linesFor($this->beta))->toBe([2])
        ->and($selection->fileCount())->toBe(2);
});

it('ignores hunks that do not follow a surviving file header', function () {
    $diff = <<<'DIFF'
        @@ -1 +1 @@
        +++ b/src/Alpha.php
        @@ -1 +1 @@
        +++ /dev/null
        @@ -1,3 +5,3 @@
        DIFF;

    $selection = LineSelection::fromUnifiedDiff($diff, $this->dir);

    expect($selection->linesByFile)->toBe([$this->alpha => [1]]);
});

it('selects every line of whole files', function () {
    $selection = LineSelection::wholeFiles(['src/Alpha.php', 'src/Beta.php'], $this->dir);

    expect($selection->linesFor($this->alpha))->toBe([1, 2, 3, 4, 5])
        ->and($selection->linesFor($this->beta))->toBe([1, 2]);
});

it('skips missing and empty whole files', function () {
    writeFile($this->dir.'/src/Empty.php', '');

    $selection = LineSelection::wholeFiles(['src/Empty.php', 'src/Missing.php', 'src/Beta.php'], $this->dir);

    expect($selection->linesByFile)->toBe([$this->beta => [1, 2]]);
});

it('merges two selections', function () {
    $selection = LineSelection::fromSpecs(['src/Alpha.php:1-2'], $this->dir)
        ->merge(LineSelection::fromSpecs(['src/Alpha.php:2-3', 'src/Beta.php:1'], $this->dir));

    expect($selection->linesFor($this->alpha))->toBe([1, 2, 3])
        ->and($selection->linesFor($this->beta))->toBe([1]);
});

it('is empty by default', function () {
    $selection = new LineSelection;

    expect($selection->lineCount())->toBe(0)
        ->and($selection->fileCount())->toBe(0);
});
