# Flux Icons v2 architecture

v2 replaces the v1 combination of a config array plus loose `[Class::class, 'method']`
callbacks with a **vendor class** as the single source of truth for an icon package.

This document describes the definition layer that is in place today. The build
pipeline still runs on the v1 `IconBuilder`; migrating it onto these classes is the
next step and is tracked on the v2 milestone.

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

Everything else — per-icon path surgery, stroke width tweaks, attribute changes,
the published name — is an overridable method (`transform()`, `strokeWidth()`,
`attributes()`, `iconName()`) instead of a callback tuple in the config.

Users add a vendor, or adjust a built-in one, by extending the class and
registering it:

```php
// config/flux-icons.php
'vendors' => [
    'tabler' => Ympact\FluxIcons\Vendors\Tabler::class,
    'house-style' => App\FluxIcons\HouseStyle::class,
],
```

`VendorRegistry` resolves those keys to instances. Because a variant can be
redefined by name, a subclass can adjust one variant without restating the set:

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
`mini` get nothing — `VariantSet::missingFluxVariants()` reports the gap.

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
```

Unknown option names and values are rejected with a message listing what is
allowed. Wiring these into the build command and the config defaults is still to
be done.

## Status

Present and tested:

- `Contracts\Vendor`, `Support\VendorRegistry`
- `Variants\{Variant, VariantSet, Template, FluxVariant, Stroke, Fill, Fallback}`
- `Sources\Source`
- `Vendors\Tabler` (reference vendor), `Vendors\Phosphor` (duotone + weights)

Not yet migrated, tracked on the v2 milestone:

- the build pipeline (`IconBuilder` is still the v1 one and still reads the v1
  config array, so `config/flux-icons.php` is unchanged for now)
- the blade stub, which still has fixed `{OUTLINE}`/`{SOLID}`/`{MINI}`/`{MICRO}`
  placeholders and has to be generated from the variant set before vendor
  variants can actually be emitted
- porting the remaining v1 vendors to vendor classes
- the artisan commands
