<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

namespace Evankford\PestPluginMutateLines;

use Pest\Mutate\Contracts\Mutator;
use Pest\Mutate\Mutation;
use Pest\Mutate\Support\MutationGenerator;
use Symfony\Component\Finder\SplFileInfo;

class LineRangeMutationGenerator extends MutationGenerator
{
    public function __construct(public readonly LineSelection $selection) {}

    /**
     * @param  array<int, class-string<Mutator>>  $mutators
     * @param  array<int, int>  $linesToMutate
     * @param  array<int, string>  $classesToMutate
     * @return array<int, Mutation>
     */
    public function generate(
        SplFileInfo $file,
        array $mutators,
        array $linesToMutate = [],
        array $classesToMutate = [],
    ): array {
        $selectedLines = $this->selection->linesFor($file->getRealPath()) ?? [];

        if ($linesToMutate !== []) {
            $selectedLines = array_intersect($selectedLines, $linesToMutate);
        }

        // The parent treats an empty line list as "mutate every line".
        if ($selectedLines === []) {
            return [];
        }

        return parent::generate($file, $mutators, $selectedLines, $classesToMutate);
    }
}
