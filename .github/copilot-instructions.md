# Copilot instructions

## Review output

- Report every finding you have, including low-confidence ones. Prefer posting a
  finding as an inline comment, stating your confidence, over suppressing it —
  suppressed comments do not show up on the Files changed tab and get missed.
- Skip formatting and code-style comments. Pint runs with the Laravel preset and
  is blocking in CI, so style is already settled.
- Skip what PHPStan already catches. It runs at level 7 over `src/` with larastan
  and is blocking in CI.
- State a concrete scenario in which the code actually misbehaves. Prefer
  correctness, shared mutable state, and broken API contracts over speculation.

## What this package is

A Laravel package that builds icons from npm icon packages into Blade components
for Livewire Flux, so they can be used as `<flux:icon.tabler.confetti />`.

It is a build-time tool, normally installed with `--dev`: the artisan commands
read SVG files out of `node_modules` and write Blade components into
`resources/views/flux/icon/{namespace}/`. Those built components are artifacts —
users rebuild them rather than upgrading them in place.

Tests run on Pest (`composer test`).

## Branches

- `main` / `v1` — the 1.x line, supporting Flux v1 and v2.
- `v2` — the next major, Flux v2 only. Introduces class-based vendor definitions
  (`Contracts\Vendor`, `Variants\*`, `Sources\Source`, `Support\VendorRegistry`).
  See `docs/v2-architecture.md` for the design and its current boundaries.

## Deliberate decisions, not oversights

Please do not report these as problems.

- `php ^8.1` and `illuminate/* ^10|^11|^12|^13` intentionally mirror what
  `livewire/flux` v2 itself declares. Do not suggest raising or narrowing them.
- Laravel 11 is absent from the CI matrix on purpose: it is past its security
  support window, so every 11.x release carries unpatched advisories that
  Composer refuses to install. The composer constraint still allows `^11` so the
  package stays in step with Flux.
- On the v2 line the build pipeline (`Services\IconBuilder`) and
  `config/flux-icons.php` are still the v1 array-based versions. The vendor
  definition layer is deliberately not wired into them yet; that migration is
  tracked on the v2.0.0 milestone. Do not report the two as inconsistent with
  each other.
- The icon Blade stub still has fixed `{OUTLINE}`/`{SOLID}`/`{MINI}`/`{MICRO}`
  placeholders. Generating it from the variant set is tracked separately.

## Worth scrutinising

- Shared mutable state: the copy semantics of `Variant` and `VariantSet`, and any
  method handing out an internal `Collection` that a caller could mutate.
- Vendor definitions: a vendor has to be able to answer all four variants Flux
  requests (`outline`, `solid`, `mini`, `micro`), otherwise Flux components that
  ask for one render nothing.
- SVG handling in `Types\Icon` and `Types\SvgPath` — DOM parsing, attribute
  access, and the difference between a missing attribute and an empty one.
- Version, path and name handling that only misbehaves for specific inputs
  (prefixes or suffixes appearing mid-name, branch versions, pre-releases).
