# Flux Icons

This is a laravel package to customize the icons for [Livewire Flux](https://github.com/livewire/flux). It builds the icons from various vendors into a `flux:icon` component.

[![Packagist Version](https://img.shields.io/packagist/v/ympact/flux-icons)](https://packagist.org/packages/ympact/flux-icons)

## Installation

Generally you want to install this package only in your local development environment and build and publish the icons you need.

```cmd
composer require --dev ympact/flux-icons
```

## Icon Vendor Support

| Vendor       | Namespace   | Preview / Repo | Notes |
|--------------|-------------|----------------|-------|
| Bootstrap    | `bootstrap` | [Preview](https://icons.getbootstrap.com/#icons) | Stroke width of outline icons cannot be adjusted |
| VSC Codicons | `codicons`  | [Repo](https://github.com/microsoft/vscode-codicons/) | Only outline icons available, but stroke width cannot be adjusted |
| Flowbite     | `flowbite`  | [Preview](https://flowbite.com/icons/) |  |
| Flags        | `flags`     |  [Repo](https://github.com/lipis/flag-icons/) | There is only one variant (solid and outline show the same flag). The 4x3 version is built; for the square version build with `--options=ratio=1x1`, or register a subclass whose `optionDefaults()` returns `['ratio' => '1x1']`. |
| Fluent UI    | `fluent`    | [Preview (unofficial)](https://fluenticons.co/) - [Repo](https://github.com/microsoft/fluentui-system-icons) | Stroke width of outline icons cannot be adjusted |
| Google Material Design Icons | `material` | [Preview](https://fonts.google.com/icons) | Stroke width of outline icons cannot be adjusted |
| Google Material Symbols (300) | `material-symbols` | [Preview](https://fonts.google.com/icons?icon.set=Material+Symbols) | Stroke width of outline icons cannot be adjusted |
| Healthicons  | `healthicons` | [Preview](https://healthicons.org) | Stroke width cannot be adjusted. |
| Lucide       | `lucide`    | [Preview](https://lucide.dev/icons/) | Only outline icons available |
| MDI          | `mdi`       | [Preview](https://pictogrammers.com/library/mdi/) | Stroke width of outline icons cannot be adjusted |
| Tabler       | `tabler`    | [Preview](https://tabler.io/icons) | |

## Building icons

You will need to build the icons yourself once the package is installed. This can be done using the artisan command `flux-icons:build`. You can optionally pass the vendor name as the first argument; without one it builds from `defaults.vendor` in the config, or asks.

```cmd
php artisan flux-icons:build tabler --icons=confetti,confetti-off
```

The artisan script installs the vendor's icon package for you with `npm install` when `node_modules` does not hold it yet. npm runs in your application's directory, so your `package.json` and `.npmrc` apply, which is where a private registry is configured. When npm fails, the command stops and shows what npm said.
In case you did not provide any arguments, the script will walk you through all options

![php artisan flux-icons:build](screenshot-cli.png)

### Options

| Option          | Description                                                                                        |
|-----------------|----------------------------------------------------------------------------------------------------|
| `-I\|--icons=`  | The icons to build (single or comma separated list). Cannot be used in combination with `--all`. |
| `-M\|--merge`   | Merge the icons listed in the `--icons` option with the icons defined in the config. Cannot be used in combination with `--all`. |
| `-O\|--options=` | Options of the vendor for this build, as `name=value`, repeatable or comma separated: `--options=ratio=1x1`. They override the values the vendor class builds with. Without it, an interactive build asks for each option. |
| `-A\|--all`     | Build all icons from the vendor. **Note:** this might generate over thousands of files and cause `npm run dev` to crash due to memory issues. |
| `-v\|--verbose` | Show additional messages during build. |

### Usage

Since this package publishes all icons to `resources/views/flux/icon/{vendor}/` you can simply use the Blade convention of referencing the icons within your `flux:icon` component. So for example:

```html
<flux:icon.tabler.confetti />
or
<flux:icon name="tabler.confetti-off"/>
```

An icon takes Flux's variants, `outline`, `solid`, `mini` and `micro`, and any variant its vendor adds, such as Phosphor's `duotone` (`Ympact\FluxIcons\Vendors\Phosphor`, which the default config does not register). The header of each built component lists the variants it has. Without a `variant`, an icon draws its vendor's default variant, which is `outline` for every bundled vendor.

```html
<flux:icon.tabler.confetti variant="mini" />
<flux:icon.phosphor.heart variant="duotone" />
```

Asked for a variant it was not built with, an icon draws its default variant and marks the `<svg>` with `data-flux-icon-fallback="duotone"` (the variant asked for), so you can spot it in your browser's devtools instead of getting an error.

## Checking references

Flux resolves an icon to a Blade component and throws when it does not exist, so a template
that asks for an icon you never built is a 500 on whichever page renders it, and nothing
says so until that page is rendered. Whether a reference resolves is a static question, so
you can answer it without running the application:

```bash
php artisan flux-icons:check
```

It walks every registered view path (packages and modules included, not only
`resources/views`) and reports the references that have nothing behind them:

```
  Modules/Tools/resources/views/components/ambitie/transformaties.blade.php
    402  tabler.chart-histogram has not been built

   ERROR  1 icon reference does not resolve.
```

It exits non-zero, which makes it usable as a CI step next to your other static checks.

Only namespaced references are examined. A bare `icon="check"` is a Flux built-in and none
of this package's business; `icon="tabler.check"` is. References are found wherever they are
written: the tag form, the `name` attribute, any `icon`-like attribute, and inside a bound
expression. They all fail the same way.

A doubled namespace such as `tabler.tabler.check` is reported as malformed rather than as a
missing icon, since no build will ever produce it.

### Options

| Option | Description |
| --- | --- |
| `vendor` | Only check icons of this vendor, given by its config key |
| `--path=` | Directories to scan, repeatable. Defaults to the registered view paths |
| `--build` | Also print the `flux-icons:build` command that would add the missing icons |

```bash
php artisan flux-icons:check tabler --build
```

## Configuration

Publish the config file to choose the icons that are built by default or to register vendors:

```cmd
php artisan vendor:publish --tag=flux-icons-config
```

| Option            | Value | Description |
|-------------------|-------|-------------|
| `defaults.vendor` | `null` or a vendor key | The vendor `flux-icons:build` builds from when none is passed to it. |
| `icons`           | `null` or `['vendor-key' => ['icon-name', ...]]` | The icons `flux-icons:build` builds when no icons are passed to it. |
| `request_npm_dev` | `bool` | For a build of more than 100 icons, ask whether `npm run dev` is running, and if so pause a third of a second between icons and remind you to start it again afterwards. Without a terminal the answer is no. |
| `vendors`         | `['vendor-key' => VendorClass::class]` | The vendors icons can be built from. The key is what you pass to the commands. The icons are published under the key as a slug (`tabler_icons` to `tabler-icons`), unless the class names another namespace (Material Icons publishes to `material`). |

A published config from version 1 describes vendors as arrays. Version 2 stops with a message naming the vendor. Replace the config with `php artisan vendor:publish --tag=flux-icons-config --force`, which overwrites `config/flux-icons.php`, then copy your changes over.

### Adjusting a vendor

Each vendor is a class. To change how its icons are built, extend it:

```cmd
php artisan flux-icons:make-vendor Tabler --extends=tabler
```

This writes `app/FluxIcons/Vendors/Tabler.php`, a subclass of the registered vendor in your application's namespace, and prints the line that registers it in your published config: `'tabler' => \App\FluxIcons\Vendors\Tabler::class`. It leaves an existing class alone unless you pass `--force`. Override any of these methods:

| Method | Adjusts |
|--------|---------|
| `variants()` | The variants: their source files, template, stroke, fallback and attributes. |
| `transform()` | The paths of an icon before it is written. |
| `attributes()` | The attributes on an icon's `<svg>` element. |
| `strokeWidth()` | The stroke width of a single icon. |
| `iconName()` | The name an icon is published under. |
| `optionDefaults()` | The option values the vendor builds with, such as `['ratio' => '1x1']` for the square flags. `options()` lists the values each option takes. |
| `namespace()` | The directory and Flux namespace the icons are published under, `<flux:icon.{namespace}.home />`. Defaults to the registered key as a slug; Material Icons returns `material`. A v1 `namespace` setting moves here. |

For example, a heavier stroke for Tabler's outline icons:

```php
namespace App\FluxIcons\Vendors;

use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\VariantSet;
use Ympact\FluxIcons\Vendors\Tabler as BaseVendor;

class Tabler extends BaseVendor
{
    public function variants(): VariantSet
    {
        return parent::variants()
            ->variant('outline', fn ($variant) => $variant->stroke(new Stroke(2)));
    }
}
```

### Adding a vendor

A vendor of your own extends `Ympact\FluxIcons\Contracts\Vendor`, names its npm package and defines its variants, and is registered under a key in the `vendors` config. `php artisan flux-icons:make-vendor HouseStyle` generates one to fill in. [The architecture notes](docs/v2-architecture.md) describe variants, sources, fallbacks and how an icon is rendered. In case you add a vendor, please share it or make a PR so others can use it too!

## Additional icons

This package also provide some custom icons that can be published:

They can be published using

```cmd
php artisan vendor:publish --tag=flux-icons-icons
```

- An empty icon, can be useful for simple logic in your blade or components:
  
  ```html
  <flux:icon name="{{ $icon ?? 'flux-icons.empty' }}" />
  ```

- A placeholder avatar icon, using an icon or initials

  ```html
  <flux:icon.flux-icons.avatar-placeholder name="Maurits Korse" color="green" />
  <flux:icon.flux-icons.avatar-placeholder icon color="green" />
  ```

  This icon has additional properties:
  - **icon** `(void|string)`: uses the Heroicon user icon as image.
  - **name** `(string)`: instead of an icon two initials of a name will be shown. You can pass the full name (Maurits Korse) or just the initials (MK)
  - **color** `(string)`: colorizing the icon using the same as Flux badges

## Events

After a build has written at least one icon, `Ympact\FluxIcons\Events\IconsBuilt` is dispatched. Listen for it to refresh anything that catalogues your icons, such as a cache.

| Property | Description |
| --- | --- |
| `vendor` | The vendor's key in the config, e.g. `tabler`. |
| `namespace` | The directory the icons were written to, and their prefix: `<flux:icon.{namespace}.{name} />`. |
| `directory` | The absolute path of that directory. |
| `icons` | The names of the icons written. |

A build that writes nothing, for example because no requested icon exists, dispatches nothing.

## Roadmap

- Add/Improve command for updating/rebuilding icons
- Adding more vendors
- Helper script to create configurations for new vendors
- Improving testing
- Documentation
