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
`mini` get the default variant instead. `VariantSet::missingFluxVariants()`
reports the gap.

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

public function optionDefaults(): array
{
    return ['weight' => 'regular'];
}
```

`optionDefaults()` gives each option the value the vendor builds with. Like
everything else about a vendor it lives in the class, not in the config: to build
the square flags everywhere, updates included, register a subclass whose
`optionDefaults()` returns `['ratio' => '1x1']`. `VendorRegistry` applies those
defaults as it hands a vendor out, which also checks them.

For one build, `flux-icons:build --options=ratio=1x1` overrides them, and an
interactive build asks for each option, offering the vendor's value. In code:

```php
(new Phosphor)->withOptions(['weight' => 'bold']);
```

Unknown option names and values are rejected with a message listing what is
allowed. An update rebuilds with the class's values, not with the `--options` an
icon was last built with.

## Building

`Build\Builder` builds the components of one vendor from its installed npm package:

```php
$result = (new Builder)->build(new Tabler, ['home', 'confetti']);

$result->built;   // the names the components were published under
$result->skipped; // icon => why it was not built
$result->missing; // requested icons the vendor does not ship
```

The builder never runs npm. `flux-icons:build` and `flux-icons:update` install or
update the package first through `Services\NpmManager`, which runs npm in the
application's directory, so its `.npmrc` applies, and throws with npm's own output
when npm fails. Both commands resolve it and the `Builder` from the container: an
application whose packages live elsewhere, such as a workspace root, binds both.

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
one name, the first keeps it and the second is skipped. A source file that does not
parse as XML with an `<svg>` root skips its icon, and the reason names the file and
the parser's first error; HTML entities such as `&nbsp;` are not XML. Entities the
file declares itself are expanded, which is how an Illustrator export names the SVG
namespace. A file declaring an external entity is skipped without reading it. A vendor whose package is not installed, or whose default
variant lists no icons (a wrong `basePath()` or source directory), throws a
`RuntimeException` instead of building nothing.
`IconsBuilt` is dispatched once per build, with the published names, when
anything was written.

Vendor variants are built after the four Flux variants, the same way. An icon
with no file for a vendor variant, and no fallback that has one, is still built;
only that variant is left out of its component. A variant with fills gives each
shape the attributes of the layer it belongs to: a layer that matches on opacity
or colour claims its shapes first, and a catch-all layer takes the rest, except a
shape the vendor drew with `fill="none"`. A layer's opacity is written to the
attribute the source marked it with, `opacity` or `fill-opacity`.

### The built component

Each component has one case per variant it was built with, and the vendor's
default variant as the default of its `variant` prop. A variant's size classes are
its own `classes()`, else Flux's for the four Flux variants and the default
variant's for the rest. The header lists the variants the icon has.

Asked for a variant it was not built with, a component draws its default variant
and marks the `<svg>` with `data-flux-icon-fallback="duotone"`, so the fallback is
visible in the browser's devtools.

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
- A component asked for a variant it was not built with draws its default variant;
  v1's threw an `UnhandledMatchError` from its size class lookup.
- Only the outermost `<svg>` receives the component's `$attributes`; v1 also gave
  them to the vendor's own `<svg>` nested inside a `Raw` icon.
- Sources are parsed as XML, not HTML, so a `Raw` icon keeps the case of names such
  as `viewBox`, `clipPath` and `linearGradient`; v1 wrote them in lower case. The
  vendor's `<svg>` is nested as markup rather than as a CDATA section, with its
  attributes in the order the vendor wrote them.
- Every shape is drawn once, from the children of the source's root. v1 also copied
  the shapes inside a `<g>` out of it a second time, without the group's attributes;
  dropped top-level `<ellipse>` elements (Lucide's `database`); and drew the shape
  inside a `<clipPath>` as part of the icon while leaving out the `<defs>` it lives in.

## Status

Present and tested:

- `Contracts\Vendor`, `Support\VendorRegistry`
- `Variants\{Variant, VariantSet, Template, FluxVariant, Stroke, Fill, Fallback}`
- `Sources\Source`
- every v1 vendor as a class: `Vendors\{Bootstrap, Codicons, Flags, Flowbite,
  Fluent, Healthicons, Lucide, MaterialIcons, MaterialSymbols, Mdi, Tabler}`, plus
  `Vendors\Phosphor` (duotone + weights) and `Vendors\{FontAwesome,
  FontAwesomeBrands}` (Font Awesome Free, [#14](https://github.com/Ympact/flux-icons/issues/14))
- `Build\{Builder, SourceResolver, IconRenderer, ComponentWriter, Credits,
  BuildResult}`, which build from those classes
- the artisan commands and `config/flux-icons.php`, which map vendor keys to classes
- `Services\NpmManager`, which installs and updates vendor packages through
  Symfony Process ([#23](https://github.com/Ympact/flux-icons/issues/23))
- the upgrade guide from 1.x, [`UPGRADE.md`](../UPGRADE.md)
  ([#20](https://github.com/Ympact/flux-icons/issues/20))

Not yet done, tracked on the v2 milestone:

- `Types\Icon` still reads a v1 config array, which the build no longer passes it
