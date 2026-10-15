<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

use Evankford\PestPluginMutateLines\ApplyMutateLineOptions;
use Evankford\PestPluginMutateLines\GitChangedLines;
use Evankford\PestPluginMutateLines\LineRangeMutationGenerator;
use Evankford\PestPluginMutateLines\LineSelection;
use Evankford\PestPluginMutateLines\MutateLinesPlugin;
use Pest\Mutate\Plugins\Mutate;
use Pest\Mutate\Support\MutationGenerator;
use Pest\Support\Container;
use Pest\TestSuite;
use Symfony\Component\Console\Output\BufferedOutput;

// 100% mutation (5 mutants time out rather than fail: they break this action's own argument handling in the child)
covers(ApplyMutateLineOptions::class);

beforeEach(function () {
    $this->alpha = realpath(__DIR__.'/../src/LineSelection.php');

    // Mutant child processes run with this set; the tests below choose it explicitly.
    $this->childEnvironment = getenv(Mutate::ENV_MUTATION_TESTING);
    putenv(Mutate::ENV_MUTATION_TESTING);

    $this->container = Container::getInstance();
    $this->previousGenerator = $this->container->get(MutationGenerator::class);
    $this->previousGitChangedLines = $this->container->get(GitChangedLines::class);
    $this->gitChangedLines = Mockery::mock(GitChangedLines::class);
    $this->container->add(GitChangedLines::class, $this->gitChangedLines);

    $this->output = new BufferedOutput;
    $this->apply = new ApplyMutateLineOptions($this->output);
});

afterEach(function () {
    // The container is process-global: a leaked generator would filter this run's own --mutate.
    $this->container
        ->add(MutationGenerator::class, $this->previousGenerator)
        ->add(GitChangedLines::class, $this->previousGitChangedLines);

    if ($this->childEnvironment !== false) {
        putenv(Mutate::ENV_MUTATION_TESTING.'='.$this->childEnvironment);
    }
});

it('strips its options and binds a generator restricted to the requested lines', function () {
    $this->gitChangedLines->expects('__invoke')
        ->with(TestSuite::getInstance()->rootPath, 'origin/next/main')
        ->andReturns(new LineSelection(['/repo/Beta.php' => [4]]));

    $arguments = ($this->apply)([
        'vendor/bin/pest',
        '--mutate',
        "--mutate-lines={$this->alpha}:2-3",
        '--mutate-diff=origin/next/main',
        'tests/SomeTest.php',
    ]);
    $generator = $this->container->get(MutationGenerator::class);

    expect($arguments)->toBe(['vendor/bin/pest', '--mutate', 'tests/SomeTest.php'])
        ->and($generator)->toBeInstanceOf(LineRangeMutationGenerator::class)
        ->and($generator->selection->linesByFile)->toBe([$this->alpha => [2, 3], '/repo/Beta.php' => [4]])
        ->and($this->output->fetch())->toContain('Mutating only 3 selected lines across 2 files.');
});

it('diffs against HEAD when --mutate-diff has no ref', function () {
    $this->gitChangedLines->expects('__invoke')
        ->with(TestSuite::getInstance()->rootPath, 'HEAD')
        ->andReturns(new LineSelection(['/repo/Beta.php' => [4]]));

    $arguments = ($this->apply)(['vendor/bin/pest', '--mutate-diff']);

    expect($arguments)->toBe(['vendor/bin/pest'])
        ->and($this->container->get(MutationGenerator::class)->selection->linesByFile)
        ->toBe(['/repo/Beta.php' => [4]]);
});

it('resolves relative --mutate-lines paths from the project root', function () {
    $relativePath = substr($this->alpha, strlen(TestSuite::getInstance()->rootPath) + 1);

    ($this->apply)(['vendor/bin/pest', "--mutate-lines={$relativePath}:2"]);

    expect($this->container->get(MutationGenerator::class)->selection->linesByFile)->toBe([$this->alpha => [2]]);
});

it('leaves arguments and the generator alone without its options', function () {
    $this->gitChangedLines->expects('__invoke')->never();

    expect(($this->apply)(['vendor/bin/pest', '--mutate']))->toBe(['vendor/bin/pest', '--mutate'])
        ->and($this->container->get(MutationGenerator::class))->toBe($this->previousGenerator)
        ->and($this->output->fetch())->toBe('');
});

it('passes through other options that share its prefixes', function () {
    $this->gitChangedLines->expects('__invoke')->never();

    $arguments = ['vendor/bin/pest', '--mutate-lines-other', '--mutate-diff-other'];

    expect(($this->apply)($arguments))->toBe($arguments)
        ->and($this->container->get(MutationGenerator::class))->toBe($this->previousGenerator);
});

it('only strips its options inside a mutant child process', function () {
    putenv(Mutate::ENV_MUTATION_TESTING.'=/some/file.php');
    $this->gitChangedLines->expects('__invoke')->never();

    $arguments = ($this->apply)([
        'vendor/bin/pest',
        "--mutate-lines={$this->alpha}:2",
        '--mutate-diff',
        '--filter=x',
    ]);

    expect($arguments)->toBe(['vendor/bin/pest', '--filter=x'])
        ->and($this->container->get(MutationGenerator::class))->toBe($this->previousGenerator)
        ->and($this->output->fetch())->toBe('');
});

it('is wired into pest through MutateLinesPlugin', function () {
    $plugin = new MutateLinesPlugin($this->output);

    expect($plugin->handleArguments(['vendor/bin/pest', "--mutate-lines={$this->alpha}:2"]))->toBe(['vendor/bin/pest'])
        ->and($this->container->get(MutationGenerator::class)->selection->linesByFile)->toBe([$this->alpha => [2]]);
});
