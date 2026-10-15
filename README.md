# Pest Plugin: Mutate Lines

Scope [Pest mutation testing](https://pestphp.com/docs/mutation-testing) to the lines you actually changed, instead of every line of every covered class.

Long-lived classes can take minutes to mutate in full. While you iterate on one method, this plugin limits `--mutate` to your diff or to explicit line ranges.

## Installation

```bash
composer require evankford/pest-plugin-mutate-lines --dev
```

Pest discovers the plugin automatically. Requires Pest 5 and `pestphp/pest-plugin-mutate` 5.

## Usage

```bash
# Lines changed vs HEAD: uncommitted edits plus every line of untracked .php files
vendor/bin/pest --mutate --mutate-diff tests/Unit/FooTest.php

# Lines changed since the merge base with a ref (committed + uncommitted)
vendor/bin/pest --mutate --mutate-diff=origin/main tests/Unit/FooTest.php

# Explicit ranges, relative to the project root; repeat the flag for more files
vendor/bin/pest --mutate --mutate-lines=src/Foo.php:12,40-55 tests/Unit/FooTest.php
```

`--mutate-diff` and `--mutate-lines` can be combined; the selections are merged.

### Notes

- Flags need `=`. `--mutate-lines src/Foo.php:12` (with a space) falls through to PHPUnit and errors.
- Selected lines are intersected with what `--mutate` would normally mutate (your `covers()` / `--class` / `--path` scope), never widened.
- Nodes that span a selected line (e.g. the enclosing method's visibility) are mutated too. Pure deletions select nothing.
- The `INFO Mutating only N selected lines across M files` banner confirms the flag took effect. `0 Mutations` means none of the selected lines belong to the classes being mutated.
- A scoped 100% says nothing about the untouched lines of a class. Iterate with these flags, then run one plain `--mutate` before calling the work done.

## Compatibility

`LineRangeMutationGenerator` extends `pest-plugin-mutate`'s internal `MutationGenerator`, so a minor release of that package could break this one. CI runs against both the lowest and the latest supported versions.

## Development

```bash
composer test         # rector, pint, phpstan, unit tests
composer test:mutate  # mutation tests (needs pcov or xdebug), must stay at 100%
```

## License

MIT. See [LICENSE.md](LICENSE.md).
