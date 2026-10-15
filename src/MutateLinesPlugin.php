<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

namespace Evankford\PestPluginMutateLines;

use Pest\Contracts\Plugins\HandlesArguments;
use Symfony\Component\Console\Output\OutputInterface;

// Pest loads plugins before the mutation stream wrapper starts, so logic lives in ApplyMutateLineOptions.
class MutateLinesPlugin implements HandlesArguments
{
    public function __construct(private readonly OutputInterface $output) {}

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function handleArguments(array $arguments): array
    {
        return (new ApplyMutateLineOptions($this->output))($arguments);
    }
}
