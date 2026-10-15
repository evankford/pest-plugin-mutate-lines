<?php

declare(strict_types=1);

// Created by Claude - 2026-10-08

namespace Evankford\PestPluginMutateLines;

use Pest\Mutate\Plugins\Mutate;
use Pest\Mutate\Support\MutationGenerator;
use Pest\Support\Container;
use Pest\TestSuite;
use Symfony\Component\Console\Output\OutputInterface;

class ApplyMutateLineOptions
{
    private const string LINES_OPTION = '--mutate-lines';

    private const string DIFF_OPTION = '--mutate-diff';

    public function __construct(private readonly OutputInterface $output) {}

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function __invoke(array $arguments): array
    {
        $specs = [];
        $diffRef = null;
        $remaining = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, self::LINES_OPTION.'=')) {
                $specs[] = $this->value($argument);
            } elseif ($argument === self::DIFF_OPTION) {
                $diffRef = 'HEAD';
            } elseif (str_starts_with($argument, self::DIFF_OPTION.'=')) {
                $diffRef = $this->value($argument);
            } else {
                $remaining[] = $argument;
            }
        }

        // Mutant child processes inherit the parent's arguments but never generate mutations.
        if (getenv(Mutate::ENV_MUTATION_TESTING) !== false || ($specs === [] && $diffRef === null)) {
            return $remaining;
        }

        $workingDirectory = TestSuite::getInstance()->rootPath;
        $selection = LineSelection::fromSpecs($specs, $workingDirectory);

        if ($diffRef !== null) {
            /** @var GitChangedLines $gitChangedLines */
            $gitChangedLines = Container::getInstance()->get(GitChangedLines::class);
            $selection = $selection->merge($gitChangedLines($workingDirectory, $diffRef));
        }

        Container::getInstance()->add(MutationGenerator::class, new LineRangeMutationGenerator($selection));

        $this->output->writeln(sprintf(
            '  <bg=cyan;fg=black> INFO </> Mutating only %d selected lines across %d files.',
            $selection->lineCount(),
            $selection->fileCount(),
        ));

        return $remaining;
    }

    private function value(string $argument): string
    {
        return substr($argument, strpos($argument, '=') + 1);
    }
}
