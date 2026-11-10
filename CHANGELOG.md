# Changelog

All notable changes to this package are documented here. Releases before 1.2.1 are described on the [GitHub releases page](https://github.com/Ympact/flux-icons/releases).

## [1.2.1] - 2026-11-10

### Fixed

- **MDI icons that are their own outline are built.** An MDI icon without an `-outline` sibling serves as its own outline, but its fallback looked for `{name}-outline.svg` and skipped the icon without a message: 3,618 of the 5,558 icons in `@mdi/svg` 7.4.47. A fallback to the base variant now reuses the file the icon was read from. ([#38](https://github.com/Ympact/flux-icons/issues/38), [#39](https://github.com/Ympact/flux-icons/pull/39))
- `flux-icons:build --merge` adds the icons configured for the vendor to the ones passed with `--icons`. The option was declared but never read.
- `flux-icons:update --vendor=` limits the update to the given vendors, by config key or by the directory their icons were built into. It was declared as a flag and passed to `Collection::slice()`, so it could not select a vendor.
- `flux-icons:update` no longer crashes on a vendor whose namespace differs from its config key (`material-icons` builds into `material/`). It skips the `flux-icons` directory of published helper icons instead of failing on it.
- `request_npm_dev` is read from the package config. It was read without the `flux-icons.` prefix, so the `npm run dev` prompt and the pause between icons never ran.
- Whether a built component gets `@blaze` now depends on a numeric version comparison. Comparing as strings placed Flux 2.10.0 below 2.2.6. Branch versions such as `dev-main` and `2.x-dev` count as current.
- The Blaze fallback directives are registered only when no `@blaze` directive exists yet. Registering them unconditionally could replace Blaze's own directives and turn off Blaze compilation, depending on the order in which service providers boot.
- An SVG without a `viewBox` takes its size from `width` and `height`. A missing attribute was read as an empty string, so such an icon got a size of 0 by 0.
- `IconBuilder` works outside the artisan commands: two of its typed properties were read before they were initialised.

### Changes an existing install will notice

- Rebuilding MDI icons writes many more components: building every MDI icon now writes all 5,558 instead of 1,940.
- With `request_npm_dev` enabled, which is the default, a build of more than 100 icons asks whether `npm run dev` is running, and if so pauses a third of a second between icons.

[1.2.1]: https://github.com/Ympact/flux-icons/compare/v1.2.0...v1.2.1
