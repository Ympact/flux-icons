# Changelog

All notable changes to this package are documented here. Releases before 1.2.1 are described on the [GitHub releases page](https://github.com/Ympact/flux-icons/releases).

## [1.2.1] - 2026-11-11

### Changed

- The fallback Blaze directives are not registered when a `@blaze` directive already exists. Blaze, and Flux 2 itself, register directives with the same effect, so rendering does not change.

### Fixed

- **MDI icons that are their own outline are built.** An MDI icon without an `-outline` sibling serves as its own outline, but its fallback looked for `{name}-outline.svg` and skipped the icon, with a message only under `-v`: 3,618 of the 5,558 icons in `@mdi/svg` 7.4.47. A fallback to the base variant now reuses the file the icon was read from. ([#38](https://github.com/Ympact/flux-icons/issues/38), [#39](https://github.com/Ympact/flux-icons/pull/39))
- `flux-icons:build --merge` adds the icons configured for the vendor to the ones passed with `--icons`. The option was declared but never read.
- `flux-icons:update --vendor=` limits the update to the given vendors, by config key or by the directory their icons were built into. It was declared as a flag and passed to `Collection::slice()`, so it could not select a vendor.
- `flux-icons:update` exits with status 1 when a package could not be updated or a vendor's icons could not be rebuilt. It exited 0, and on Laravel 13 labelled the failed package update as done. ([#44](https://github.com/Ympact/flux-icons/pull/44))
- `flux-icons:update` rebuilds icons that the vendor's `icon_name` callback renames, such as the Healthicons names with an underscore (`blood-a_n`, built as `blood-a-n`). It asked for the built name, which matched no source file, so those icons were never rebuilt. `flux-icons:build --icons=` accepts the built name as well. ([#45](https://github.com/Ympact/flux-icons/issues/45), [#44](https://github.com/Ympact/flux-icons/pull/44))
- `flux-icons:update --vendor=` reports a named directory that belongs to no configured vendor and exits with status 1. It skipped the directory with a message only under `-v`. ([#46](https://github.com/Ympact/flux-icons/issues/46), [#44](https://github.com/Ympact/flux-icons/pull/44))
- `flux-icons:update` no longer crashes on a vendor whose namespace differs from its config key (`material-icons` builds into `material/`). It skips the `flux-icons` directory of published helper icons instead of failing on it.
- `request_npm_dev` is read from the package config. It was read without the `flux-icons.` prefix, so the `npm run dev` prompt and the pause between icons never ran. The prompt defaults to no, so a build without a terminal is not paused, and the build does not start `npm run dev` itself. ([#44](https://github.com/Ympact/flux-icons/pull/44))
- Built components get `@blaze` only on Flux 2.2.6 and later. `composer.lock` records Flux with a `v` prefix (`v2.13.1`), so comparing that string with `2.2.6` was always true, and Flux 1.x builds got the directive too. Branch versions such as `dev-main` and `2.x-dev` count as current.
- A variant built from its fallback takes the fallback's attributes, as it already took its template. Tabler's solid, mini and micro variants of an icon without a filled file lost the outline's round line caps, so dots drew nothing and line ends turned square. ([#49](https://github.com/Ympact/flux-icons/issues/49), [#50](https://github.com/Ympact/flux-icons/pull/50))
- An SVG without a `viewBox` that sets only `width` or only `height` is built square. The missing attribute was read as an empty string, so that dimension became 0.
- `IconBuilder` works outside the artisan commands: two of its typed properties were read before they were initialised.

### Changes an existing install will notice

- Rebuilding MDI icons writes many more components: building every MDI icon now writes all 5,558 instead of 1,940.
- With `request_npm_dev` enabled, which is the default, a build of more than 100 icons asks whether `npm run dev` is running, and if so pauses a third of a second between icons. The answer defaults to no; a build without a terminal gets that answer and runs as before.
- Rebuilt Tabler icons without a filled version get round line caps in their solid, mini and micro variants.
- `flux-icons:build --merge` builds the icons configured for the vendor as well as those passed with `--icons`. Before, the option was accepted and ignored.
- `flux-icons:build --merge --all` stops with an error, as `--icons` with `--all` already did.
- `flux-icons:update --vendor` needs a value, and stops with an error without one or with an empty one. Before, it skipped the first vendor with built icons.
- `flux-icons:update` exits with status 1 when a package update or a rebuild fails, or when a directory named with `--vendor` belongs to no configured vendor. Before, it exited 0.
- `flux-icons:update` rebuilds renamed icons, so the first update of a Healthicons build rewrites components it used to leave alone.
- `flux-icons:update --vendor=` exits with status 1 when none of the named vendors has built icons.

[1.2.1]: https://github.com/Ympact/flux-icons/compare/v1.2.0...v1.2.1
