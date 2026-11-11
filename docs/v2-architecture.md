# Flux Icons v2 architecture

v2 replaces the v1 combination of a config array plus loose `[Class::class, 'method']`
callbacks with a **vendor class** as the single source of truth for an icon package.

This document describes the vendor classes and the build pipeline that renders from
them, which the artisan commands run.

## Baseline

v2 targets the same baseline as `livewire/flux` v2:

| | |
|---|---|
| PHP | `^8.1` |
| Illuminate | `^10.0 \| ^11.0 \| ^12.0 \| ^13.0` |
| Flux | `^2.0` |

Flux v1 is no longer supported; use the 1.x releases of this package for it.

## Defining a vendor

A vendor extends `Ympact\FluxIcons\Contracts\Vendor` and answers two questions:
which npm package the icons come from, and which variants it can produce.

```php
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\{Fallback, Stroke, Template, VariantSet};

class Tabler extends Vendor
{
    public function package(): string { return '@tabler/icons'; }
    public function basePath(): string { return 'icons'; }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($v) => $v
                ->template(Template::Outline)
                ->source(Source::dir('outline'))
                ->stroke(new Stroke(1.5))
                ->default()
            )
            ->variant('solid', fn ($v) => $v
                ->template(Template::Solid)
                ->source(Source::dir('filled'))
                ->stroke(false)
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($v) => $v->basedOn('solid'))
            ->variant('micro', fn ($v) => $v->basedOn('solid'));
    }
}
```

Everything else is an overridable method instead of a callback tuple in the config.
Per-icon path surgery, stroke width tweaks, attribute changes and the published name
live in `transform()`, `strokeWidth()`, `attributes()` and `iconName()`.

Users add a vendor, or adjust a built-in one, by extending the class and
registering it:

```php
// config/flux-icons.php
'vendors' => [
    'tabler' => Ympact\FluxIcons\Vendors\Tabler::class,
    'house-style' => App\FluxIcons\HouseStyle::class,
],
```

`VendorRegistry` resolves those keys to instances. A vendor is addressed by the key
it is registered under, and publishes its icons under that key as a slug unless it
overrides `namespace()`: a subclass registered as `tabler` still builds into
`tabler`, and one registered as `tabler_icons` into `tabler-icons`, as in v1. An
entry that is not a class, such as a vendor array from a v1 config, is rejected
with a message saying so. `php artisan flux-icons:make-vendor HouseStyle
--extends=tabler` generates such a subclass.

Because a variant can be redefined by name, a subclass can adjust one variant
without restating the set:

```php
class HouseStyle extends Tabler
{
    public function variants(): VariantSet
    {
        return parent::variants()
            ->variant('outline', fn ($v) => $v->stroke(new Stroke(2)));
    }
}
```

## Variants

v1 hard-coded four variants. In v2 a `VariantSet` holds any number of them, and
variant names are free-form.

`FluxVariant` names the four the Flux component itself requests (`outline`,
`solid`, `mini`, `micro`) and carries the size and classes Flux renders them at.
Those four should always resolve, otherwise Flux components that ask for, say,
`mini` get nothing. `VariantSet::missingFluxVariants()` reports the gap.

Anything beyond them is a **vendor variant**: it is emitted into the built
component alongside the Flux four and becomes usable directly.

```php
->variant('duotone', fn ($v) => $v
    ->template(Template::Solid)
    ->source(Source::dir('duotone')->suffix('-duotone'))
    ->fill(new Fill('background', matchOpacity: 0.2, opacity: 0.2))
    ->fill(new Fill('foreground'))
)
```

```blade
<flux:icon.phosphor.heart variant="duotone" />
```

A `Fill` does double duty: it selects the source paths belonging to a layer
(by opacity or colour) and describes how that layer is rendered. Phosphor's
duotone icons draw their background layer at `opacity="0.2"`, which is exactly
what the example above matches on. A variant with more than one fill is
multitone (`Variant::isMultitone()`).

### Supporting pieces

| Class | Role |
|---|---|
| `Variants\Template` | how the SVG root renders: `Outline`, `Solid` or `Raw`. Kept separate from the variant name because vendors such as Bootstrap and MDI ship their "outline" icons as closed filled shapes. |
| `Variants\Stroke` | width, linecap and linejoin. Automatically dropped for variants that are not rendered as outlines. |
| `Variants\Fallback` | `variant('outline')`, `defaultVariant()` or `skip()`, replacing v1's `string\|false\|callable` union. |
| `Sources\Source` | directory, prefix, suffix and an optional filter, resolved relative to the package root so a vendor names its package once. |

`basedOn()` replaces v1's `base` config key. Inheritance copies template, source,
stroke and fallback, and merges attributes. It deliberately leaves size and classes
alone, so `mini` stays 20px even when it derives from a solid variant that was
resized. A variant that sets its own stroke keeps it even when its parent has the
stroke disabled. Fills are inherited all or nothing: a parent's layers have no safe
position in a child's layer list, so a variant that defines any fill replaces the
inherited set rather than adding to it.

`VariantSet::resolve()` applies that inheritance. Each variant is resolved after
whatever it is based on, in any definition order and through chains of any depth;
cycles are rejected. It returns copies, so the set it was called on is left
unresolved and untouched.

## Options

Vendors that ship several weights or aspect ratios expose them as options, which
is what [#13](https://github.com/Ympact/flux-icons/issues/13) asks for:

```php
public function options(): array
{
    return ['weight' => ['thin', 'light', 'regular', 'bold']];
}
```

```php
(new Phosphor)->withOptions(['weight' => 'bold']);
(new Flags)->withOptions(['ratio' => '1x1']);
```

Unknown option names and values are rejected with a message listing what is
allowed. Wiring these into the build command and the config defaults is still to
be done.

## Building

`Build\Builder` builds the components of one vendor from its installed npm package:

```php
$result = (new Builder)->build(new Tabler, ['home', 'confetti']);

$result->built;   // the names the components were published under
$result->skipped; // icon => why it was not built
$result->missing; // requested icons the vendor does not ship
```

Every icon of the default variant becomes one component. For each Flux variant
the builder looks for the icon's own file, then follows the variant's fallbacks
until one has it; a fallback that leads back to a variant already tried ends the
search. Reaching the default variant reuses the file the icon was listed from:
an MDI icon without an `-outline` sibling is its own outline, so there is no
outline file to look up by name. A variant that falls back is drawn with the
template, stroke and attributes of the variant it falls back to. The vendor's hooks
(`transform()`, `strokeWidth()`, `attributes()`) are still told the slot being
built, as in v1: Tabler's `refresh` and `arrow-*` icons get stroke width 2 in the
solid, mini and micro slots that fall back to their outline. An icon
with a Flux variant that has neither a file nor a fallback is skipped, and the
result says which variant was missing. When `iconName()` publishes two icons under
one name, the first keeps it and the second is skipped. A vendor whose package is
not installed, or whose default variant lists no icons (a wrong `basePath()` or
source directory), throws a `RuntimeException` instead of building nothing.
`IconsBuilt` is dispatched once per build, with the published names, when
anything was written.

Fills and vendor variants are not rendered yet; that is
[#18](https://github.com/Ympact/flux-icons/issues/18).

### How an icon is rendered

v2 draws an icon the way its vendor does. The attributes of a built icon's root
are taken, each overriding the one before, from:

1. Flux's own: `data-flux-icon`, `xmlns`, `aria-hidden` and `data-slot`
2. the template: `fill="none" stroke="currentColor"` for `Outline`,
   `fill="currentColor"` for `Solid`, nothing for `Raw`
3. the variant's stroke: width, linecap and linejoin
4. the vendor's own root, for `stroke-linecap`, `stroke-linejoin`, `fill-rule` and
   `clip-rule`, where nothing above set them
5. the variant's `attributes()`, then the vendor class's `attributes()` hook

A `null` value in step 5 removes an attribute. A stroke without a linecap or
linejoin sets none, which leaves the vendor's own from step 4 in place: that is how
Lucide keeps its round caps. Beyond those four, only the `viewBox` is
taken from the vendor's root. Its width, height, fill, stroke and stroke width give
way to Flux's size classes, the template and the stroke, so the icon follows
`currentColor` and the variant's stroke width.

Where this departs from v1 output:

- Lucide keeps the round caps and joins it declares on its root; v1 drew butt caps.
- Flags are drawn as flag-icons draws them; v1 also set `fill="currentColor"` on
  the root, which filled any shape that sets no fill of its own in the text colour.
- A variant that falls back is drawn as the variant it falls back to. A Tabler icon
  without a filled version gets the round caps and joins of its outline in the
  solid, mini and micro slots too; v1 drew butt caps there.
- MDI icons without an `-outline` file are built; v1 skipped them.
- Fallbacks are followed to the end of the chain: mini falls back to solid, and on
  to outline when solid has no file either. v1 followed one fallback and skipped
  the icon when that variant had no file, such as a Healthicons icon with only an
  outline file.
- Only the outermost `<svg>` receives the component's `$attributes`; v1 also gave
  them to the vendor's own `<svg>` nested inside a `Raw` icon.

## Status

Present and tested:

- `Contracts\Vendor`, `Support\VendorRegistry`
- `Variants\{Variant, VariantSet, Template, FluxVariant, Stroke, Fill, Fallback}`
- `Sources\Source`
- every v1 vendor as a class: `Vendors\{Bootstrap, Codicons, Flags, Flowbite,
  Fluent, Healthicons, Lucide, MaterialIcons, MaterialSymbols, Mdi, Tabler}`, plus
  `Vendors\Phosphor` (duotone + weights)
- `Build\{Builder, SourceResolver, IconRenderer, ComponentWriter, Credits,
  BuildResult}`, which build from those classes
- the artisan commands and `config/flux-icons.php`, which map vendor keys to classes

Not yet done, tracked on the v2 milestone:

- the blade stub, which still has fixed `{OUTLINE}`/`{SOLID}`/`{MINI}`/`{MICRO}`
  placeholders and has to be generated from the variant set before vendor
  variants can actually be emitted ([#18](https://github.com/Ympact/flux-icons/issues/18))
- vendor options and a default vendor on the command line
  ([#21](https://github.com/Ympact/flux-icons/issues/21))
- `Types\Icon` still reads a v1 config array, which the build no longer passes it;
  parsing is reworked in [#22](https://github.com/Ympact/flux-icons/issues/22)
- npm is still driven with `exec()` ([#23](https://github.com/Ympact/flux-icons/issues/23))
- the upgrade guide ([#20](https://github.com/Ympact/flux-icons/issues/20))
