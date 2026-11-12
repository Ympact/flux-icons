# Upgrading from 1.x to 2.0

Version 2 supports Flux 2 only. To keep using Flux 1, stay on the 1.x releases of this package.

In version 1, each vendor was an array in `config/flux-icons.php` plus callbacks in a separate class. In version 2, each vendor is one class, and the config only maps keys to those classes. Icon references in your views stay as they are: an icon is still `<flux:icon.tabler.confetti />`. What one draws can change; [What looks different](#what-looks-different) lists how.

## Steps

1. Update the package:

   ```cmd
   composer require --dev ympact/flux-icons:^2.0
   ```

2. If you published `config/flux-icons.php`, keep a copy of it, publish it again, and copy your own settings back in from the copy. `--force` overwrites the file. A version 1 config stops the build, update and check commands, and `make-vendor --extends`, with a message naming the first vendor array it finds.

   ```cmd
   php artisan vendor:publish --tag=flux-icons-config --force
   ```

3. Move each adjusted or custom vendor into a class, as described [below](#vendors). Register it in the `vendors` config.

4. Rebuild the icons. The built components are generated files, and the version 1 ones do not have what version 2 adds, such as the fallback for an unknown variant.

   ```cmd
   php artisan flux-icons:update
   ```

   This updates the npm package of every vendor that has built icons, then rebuilds those icons.

5. Optionally, list the icon references in your views and PHP that have not been built:

   ```cmd
   php artisan flux-icons:check
   ```

## Config

| Version 1 | Version 2 |
|-----------|-----------|
| `vendors.{key}` as an array | `vendors.{key}` as a class name: `'tabler' => Ympact\FluxIcons\Vendors\Tabler::class` |
| `default_stroke_width` | Removed. The stroke width belongs to each vendor's outline variant. To change it, extend the vendor, as in the [README](README.md#adjusting-a-vendor). |
| `icons`, `request_npm_dev` | Unchanged. |
| | New: `defaults.vendor`, the vendor `flux-icons:build` uses when none is named. |

## Vendors

To change a bundled vendor, extend its class:

```cmd
php artisan flux-icons:make-vendor Tabler --extends=tabler
```

For a vendor of your own, generate a class to fill in:

```cmd
php artisan flux-icons:make-vendor HouseStyle
```

`flux-icons:make-vendor` replaces `flux-icons:publish {vendor}`, which copied a callback class.

### Before and after

A custom vendor in version 1:

```php
// config/flux-icons.php
'house-style' => [
    'vendor_name' => 'House Style',
    'namespace' => 'house',
    'package' => '@acme/icons',
    'variants' => [
        'outline' => [
            'source' => 'node_modules/@acme/icons/svg/line',
            'stroke_width' => 2,
        ],
        'solid' => [
            'source' => [
                'dir' => 'node_modules/@acme/icons/svg/fill',
                'suffix' => '-fill',
            ],
            'fallback' => 'default',
        ],
    ],
    'icon_name' => [App\FluxIcons\HouseStyle::class, 'name'],
],
```

The same vendor in version 2:

```php
// config/flux-icons.php
'house-style' => App\FluxIcons\Vendors\HouseStyle::class,
```

```php
namespace App\FluxIcons\Vendors;

use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
use Ympact\FluxIcons\Variants\VariantSet;

class HouseStyle extends Vendor
{
    public function package(): string
    {
        return '@acme/icons';
    }

    // sources are relative to this directory inside the package
    public function basePath(): string
    {
        return 'svg';
    }

    public function namespace(): string
    {
        return 'house';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()
            ->variant('outline', fn ($variant) => $variant
                ->template(Template::Outline)
                ->source(Source::dir('line'))
                ->stroke(new Stroke(2, linecap: null, linejoin: null))
                ->default()
            )
            ->variant('solid', fn ($variant) => $variant
                ->template(Template::Solid)
                ->source(Source::dir('fill')->suffix('-fill'))
                ->fallback(Fallback::defaultVariant())
            )
            ->variant('mini', fn ($variant) => $variant->basedOn('solid'))
            ->variant('micro', fn ($variant) => $variant->basedOn('solid'));
    }

    public function iconName(string $name): string
    {
        return str_replace('house-', '', $name);
    }
}
```

### Vendor settings

| Version 1 | Version 2 |
|-----------|-----------|
| `package` | `package()` |
| `vendor_name` | `name()`, which defaults to the class name as words |
| `namespace` | `namespace()`, which defaults to the registered key as a slug |
| `baseVariant` | `->default()` on that variant. It is also what an icon draws without a `variant`: version 1 drew `outline` then, whatever `baseVariant` said, so a vendor whose `baseVariant` was `solid` now draws solid. |
| `icon_name` callback, given the `Icon` | `iconName(string $name): string`, given the name |
| `attributes` callback | `attributes(Icon $icon): array` |
| `transform` callback | `transform(Collection $paths, Icon $icon): Collection` |
| `stroke_width` callback | `strokeWidth(Icon $icon, float $default): float` |

### Variant settings

| Version 1 | Version 2 |
|-----------|-----------|
| `template` `'outline'` or `'solid'` | `->template(Template::Outline)` or `->template(Template::Solid)` |
| `raw => true` | `->template(Template::Raw)` |
| `stroke_width` as a number, or `false` | `->stroke(new Stroke(1.5, linecap: null, linejoin: null))`, or `->stroke(false)`. Without the two `null`s, a `Stroke` draws round line ends and corners; with them, the vendor's own stay, as in version 1. |
| `size` | Nothing to port: Flux sizes an icon with classes, which each Flux variant sets to Flux's own. `->classes('[:where(&)]:size-5')` changes them. |
| `attributes` | `->attributes([...])`; a `null` value still removes an attribute |
| `path_attributes.{variant}` | None. Change a path's attributes in the vendor's `transform()`. |
| `base` | `->basedOn('solid')` |
| `fallback` `'default'`, a variant name, or `false` | `->fallback(Fallback::defaultVariant())`, `Fallback::variant('outline')`, or `Fallback::skip()` |
| `fallback` callback | None. Pick one fallback per variant. |

Version 1 filled in settings a vendor did not write, and version 2 fills in none of them. Write each one a vendor relied on, as the example above does and as `flux-icons:make-vendor` does for a new vendor:

- outline drew a stroke of 1.5: `->stroke(new Stroke(1.5, linecap: null, linejoin: null))`
- solid, mini and micro used the solid template: `->template(Template::Solid)` on solid, and `->basedOn('solid')` on mini and micro
- solid, mini and micro fell back to the default variant: `->fallback(Fallback::defaultVariant())` on solid, which mini and micro inherit

A component has only the variants its vendor declares. Without mini and micro, `variant="mini"` draws the default variant, marked as a fallback.

### Sources

| Version 1 | Version 2 |
|-----------|-----------|
| `'dir' => 'node_modules/@acme/icons/svg/line'` | `basePath()` returns `'svg'`, and the variant uses `Source::dir('line')`. The package path is no longer repeated. |
| A glob such as `'.../outline/*/'` | `Source::dir('outline/*')` |
| `prefix`, `suffix` | `Source::dir(...)->prefix('...')->suffix('...')` |
| `filter` callback, given `$file`, `&$icons` and `$variant` | `Source::dir(...)->filter(fn (string $file): bool => ...)`, given the file only. It takes a closure; a `[Class::class, 'method']` array no longer works. |
| `dir`, `prefix` or `suffix` as a callback per variant | A `Source` of its own on each variant |

## What looks different

A rebuild changes some icons on purpose: version 2 draws an icon the way its vendor does. For example, Lucide keeps its round line ends, and MDI icons that version 1 skipped are built. The [architecture notes](docs/v2-architecture.md#how-an-icon-is-rendered) list each change.

Built components also behave differently in a few ways:

- A component asked for a variant it was not built with draws its default variant and marks itself with `data-flux-icon-fallback`. Version 1 threw an error.
- A vendor can add variants beyond Flux's four, such as Phosphor's `duotone`, and choose its default variant: Font Awesome's is `solid`.
- The header of each component lists its variants, and names the licence of its icons when the vendor's `license()` returns one.

## New in version 2

- `flux-icons:check` lists the icon references in your views and PHP that have not been built, or that ask for a variant an icon lacks.
- `flux-icons:build --options=` picks a vendor's build options for one build, such as `ratio=1x1` for square flags. A vendor subclass's `optionDefaults()` keeps them for `flux-icons:update`.
- A package can declare the icons it requires and the ones it can do without in its `composer.json`, and the build and check commands include them. The `packages` config leaves out what you do not want.
- The `IconsBuilt` event fires after each build that writes an icon, for packages that keep a registry of icons.
- Font Awesome Free and Phosphor are available as vendors. Phosphor is not registered in the default config.
