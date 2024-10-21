# Flux Icons

This is a laravel package to customize the icons for [Livewire Flux](https://github.com/livewire/flux). It builds the icons from various vendors into a `flux:icon` component.

> [!NOTE]  
> This package is not in version 1 yet and the config scheme might still change in the next updates to account for different folder and file structures of icon vendors.

## Installation

Generally you want to install this package only in your local development environment and build and publish the icons you need.

```cmd
composer require --dev ympact/flux-icons
```

## Icon Vendor Support

- Tabler Icons
- Flowbite
- Fluent UI Icons
- Google Material Design Icons
- MDI

> [!NOTE]  
> In the current version of this package, the original svg paths of an icon are merged into a single path.
> It can therefore happen that some icons may not look like the original. Especially when Flux tries to show a solid variant of an icon that originally does not have a solid or filled version.

### Known issues

Tabler

- Solid variant for icons that originally do not have a filled version are often not rendered properly.

## Building icons

You will need to build the icons yourself once the package is installed. This can be done using the artisan command `flux-icons:build` you can optionally pass the vendor name as the first argument.
In case you did not provide this, the script will ask you.

```cmd
php artisan flux-icons:build tabler --icons=confetti,confetti-off
```

### Options

| Option          | Description                                                                                        |
|-----------------|----------------------------------------------------------------------------------------------------|
| `--icons=`      | The icons to build (single or comma separated list). Cannot be used in combination with `--all`. |
| `-m\|--merge`   | Merge the icons listed in the `--icons` options with the icons defined in the config. Cannot be used in combination with `--all`. |
| `-a\|--all`     | Build all icons from the vendor. |
| `-v\|--verbose` | Show additional messages during build. |

The artisan script will try to install the icon package using `npm install`.

> [!WARNING]  
> The `--all` option might generate over thousands of files and cause `npm run dev` to crash due to memory issues.

### Usage

Since this package publishes all icons to `resources/views/flux/icon/{vendor}/` you can simply use the Blade convention of referencing the icons within your flux:icon component. So for example:

```html
<flux:icon.tabler.confetti />
```

or

```html
<flux:icon name="tabler.confetti-off"/>
```

## Note on icon variants

Due to the way the flux icon component is made, it requires 4 variants: an outline and a solid of three sizes (24, 20, 16).
For the first version of this Flux Icons package the source icons are treated as follows:

- In case there is only one solid size variant in the source package, it will use the same svg for all three size variants. Generally the svg will be scaled by the browser.
- In case there is no solid variant, it will use the outline variant as the solid variant.
- In case the solid variant does not have an outline variant, the icon is not exported at all.

If you have suggestions on how to improve this, please join the [discussion](https://github.com/Ympact/flux-icons/discussions/2).

## Publish config

You can publish the config file to adjust settings for a specific vendor or add your own vendor. In case you add your own vendor, please share or make a PR so others can use it too!

```cmd
php artisan vendor:publish --tag=flux-icons-config
```

### Publish specific vendor callbacks

```cmd
php artisan flux-icons:publish {vendor}
```

When adjusting the callback for an vendor, make sure you also publish the config file and reference the correct class.

## Advanced configuration

| Option     | Valaue     | Description                                                                 |
|------------|------------|-----------------------------------------------------------------------------|
| `icons`    |  `null` or `['vendorName' => ['icon-name', ...] ]` | A list of icons that will be build/updated by default in case no icons are passed to `flux-icons:build` command.  |
| `default_stroke_wdith` | `float` | For outline icons a default stroke width can be configured. The default Flux Heroicons uses a width of 1.5. |

### Vendor specific configuration

The vendor specific configuration sits within the `vendors` key. Each vendor should have a key. That key will be used as directory name when exporting the icons.

```php
'vendors' => [
    'tabler' => [
        'vendor_name' => 'Tabler',
        'namespace' => 'tabler',
        'package_name' => '@tabler/icons',
        'source_directories' => [ 
            //...
        ]
    ]
 ]
```

| Option     | Value     | Description                                                                 |
|------------|-----------|-----------------------------------------------------------------------------|
| `vendor_name`    |  `string` | Human readable name of the vendor.  |
| `namespace`      | `string`  | The namespace for the Flux icon, in case omitted, the vendor name will be used. |
| `package_name` | `string` | The npm package that should be installed to retrieve the icons. |
| `source_directories.outline` | `array\|string` | The directory in which the vendors outline icons reside. For specific options see below. |
| `source_directories.solid` | `array\|string` | The directory in which the vendors solid icons reside. For specific options see below. |
| `transform_svg_path`    |  `[class, method]` | A callback to transform the SVG path data. Takes a single parameter: the SVG path string. |
| `change_stroke_width`   |  `[class, method]` | A callback to determine the whether the stroke width should be changed on this icon. |

#### Source directories

The source directories specify where the script can find the outline and solid/filled versions of the icons you want to build.
In case the vendor uses a prefix or suffix for the icons, we want to configure it here to determine the basename of the icon and make them more accessible in flux.
For both source directories (outline and solid), an optional `filter` callback can be defined to indicate whether a file in the directory should be used as outline or solid respectively.

```php
[
    'dir' => 'node_modules/vendor/icons/...',
    'prefix' => null,
    'suffix' => null 
    'filter' => [ Ympact\FluxIcons\Services\Vendors\VendorName::class, 'filter']
]
```

For the **outline** icons, the function passes two params `file` and `icons`. The file is the actual filename that should be filtered out or not. The `icons` is an array of icons that the user requested to build. This is passed by reference in case this array needs to be adjusted. See the [Mdi class](src/Services/Vendors/Mdi.php) as example.

For the **solid** icons, the filter callback passes a single param `file`. Optionally callbacks can be defined on `dir`, `prefix` and `suffix` to adjust these according to the icon size. The sizes passed to these callbacks are 24, 20 and 16 (the defaults of the Flux icon component).

```php
'solid' => [ 
    [
        'dir' => 'node_modules/vendor/icons/icons/filled', 
        'prefix' => null, 
        'suffix' => [ Ympact\FluxIcons\Services\Vendors\VendorName::class, 'sourceSolidSuffix']
    ],
],
```

#### Callbacks

The configuration file provides various options on which callback can be defined. To keep the config file serializable, the callbacks should be defined in a separate class and referenced as above. See [vendor.php.stub](resources/stubs/vendor.php.stub) for reference of the available callbacks.

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

- A placeholder avatar icon, usin an icon or initials

  ```html
  <flux:icon.flux-icons.avatar-placeholder name="Maurits Korse" color="green" />
  <flux:icon.flux-icons.avatar-placeholder icon color="green" />
  ```

  This icon has additional properties:
  - **icon** `(void|string)`: uses the Heroicon user icon as image.
  - **name** `(string)`: instead of an icon two initials of a name will be shown. You can pass the full name (Maurits Korse) or just the initials (MK)
  - **color** `(string)`: colorizing the icon using the same as Flux badges

## Roadmap

- Add/Improve command for updating/rebuilding icons
- Improve fallback handling for non-existing solid icons
- Adding more vendors
