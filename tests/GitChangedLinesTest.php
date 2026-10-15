<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

use Evankford\PestPluginMutateLines\GitChangedLines;
use Pest\Exceptions\InvalidOption;
use Symfony\Component\Process\Process;

// 100% mutation
covers(GitChangedLines::class);

beforeEach(function () {
    $this->dir = temporaryDirectory('git-changed-lines');

    $this->git = function (string ...$arguments): void {
        $config = ['-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false'];
        (new Process(['git', ...$config, ...$arguments], $this->dir))->mustRun();
    };

    ($this->git)('init', '--initial-branch=main');
    writeFile($this->dir.'/src/Alpha.php', "<?php\n\$a = 1;\n\$b = 2;\n\$c = 3;\n");
    writeFile($this->dir.'/src/Beta.php', "<?php\n\$z = 1;\n");
    writeFile($this->dir.'/notes.txt', "one\n");
    ($this->git)('add', '.');
    ($this->git)('commit', '-m', 'base');

    $this->alpha = realpath($this->dir.'/src/Alpha.php');
    $this->beta = realpath($this->dir.'/src/Beta.php');
});

afterEach(function () {
    removeDirectory($this->dir);
});

it('selects uncommitted and untracked php lines against HEAD by default', function () {
    writeFile($this->dir.'/src/Alpha.php', "<?php\n\$a = 1;\n\$b = 20;\n\$c = 3;\n");
    writeFile($this->dir.'/src/Gamma.php', "<?php\n\$g = 1;\n");
    writeFile($this->dir.'/notes.txt', "two\n");

    $selection = (new GitChangedLines)($this->dir.'/src', 'HEAD');

    expect($selection->linesByFile)->toBe([
        $this->alpha => [3],
        realpath($this->dir.'/src/Gamma.php') => [1, 2],
    ]);
});

it('includes committed changes since the merge base with the given ref', function () {
    ($this->git)('checkout', '-b', 'feature');
    writeFile($this->dir.'/src/Beta.php', "<?php\n\$z = 1;\n\$y = 2;\n");
    ($this->git)('commit', '-am', 'feature work');
    ($this->git)('checkout', 'main');
    writeFile($this->dir.'/src/Alpha.php', "<?php\n\$a = 10;\n\$b = 2;\n\$c = 3;\n");
    ($this->git)('commit', '-am', 'main moved on');
    ($this->git)('checkout', 'feature');

    $selection = (new GitChangedLines)($this->dir, 'main');

    expect($selection->linesByFile)->toBe([$this->beta => [3]]);
});

it('reports a failing git command', function () {
    expect(fn () => (new GitChangedLines)($this->dir, 'no-such-ref'))
        ->toThrow(InvalidOption::class, '--mutate-diff: `git merge-base no-such-ref HEAD` failed: ');
});
