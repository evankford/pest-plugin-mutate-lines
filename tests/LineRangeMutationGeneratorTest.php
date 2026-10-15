<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

use Evankford\PestPluginMutateLines\LineRangeMutationGenerator;
use Evankford\PestPluginMutateLines\LineSelection;
use Pest\Mutate\Mutators\Equality\GreaterToSmallerOrEqual;
use Pest\Mutate\Mutators\Equality\SmallerToGreaterOrEqual;
use Symfony\Component\Finder\SplFileInfo;

// 100% mutation
covers(LineRangeMutationGenerator::class);

beforeEach(function () {
    $this->dir = temporaryDirectory('line-range-generator');

    $path = $this->dir.'/Sample.php';
    writeFile($path, <<<'PHP'
        <?php

        class Sample
        {
            public function above(int $x): bool
            {
                return $x > 1;
            }

            public function below(int $x): bool
            {
                return $x < 2;
            }
        }
        PHP);

    $this->file = new SplFileInfo($path, '', 'Sample.php');
    $this->realPath = realpath($path);
    $this->mutators = [GreaterToSmallerOrEqual::class, SmallerToGreaterOrEqual::class];
});

afterEach(function () {
    removeDirectory($this->dir);
});

it('only generates mutations on selected lines', function () {
    $generator = new LineRangeMutationGenerator(new LineSelection([$this->realPath => [12]]));

    expect(array_column($generator->generate($this->file, $this->mutators), 'startLine'))->toBe([12]);
});

it('generates nothing for a file outside the selection', function () {
    $generator = new LineRangeMutationGenerator(new LineSelection(['/elsewhere/Other.php' => [7, 12]]));

    expect($generator->generate($this->file, $this->mutators))->toBe([]);
});

it('intersects the selection with the covered lines it is given', function () {
    $generator = new LineRangeMutationGenerator(new LineSelection([$this->realPath => [7, 12]]));

    $mutations = $generator->generate($this->file, $this->mutators, linesToMutate: [12, 20]);

    expect(array_column($mutations, 'startLine'))->toBe([12]);
});

it('generates nothing when the selection and covered lines do not overlap', function () {
    $generator = new LineRangeMutationGenerator(new LineSelection([$this->realPath => [7]]));

    expect($generator->generate($this->file, $this->mutators, linesToMutate: [12]))->toBe([]);
});

it('honours the class filter', function () {
    $generator = new LineRangeMutationGenerator(new LineSelection([$this->realPath => [7, 12]]));

    expect($generator->generate($this->file, $this->mutators, classesToMutate: ['Other']))->toBe([]);
});
