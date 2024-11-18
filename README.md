# Flux Icons

This is a laravel package to customize the icons for [Livewire Flux](https://github.com/livewire/flux). It builds the icons from various vendors into a `flux:icon` component.

> [!NOTE]  
> This package is still work in progress. Hence icons might not turn out to be as they should and the config scheme might still change in the next updates to account for different folder and file structures of icon vendors.

## Installation

Generally you want to install this package only in your local development environment and build and publish the icons you need.

```cmd
composer require --dev ympact/flux-icons
```

## Icon Vendor Support

Initial support:

- [Tabler Icons](https://tabler.io/icons)
- [Flowbite](https://flowbite.com/icons/)
- [Fluent UI Icons](https://github.com/microsoft/fluentui-system-icons) and [unofficial viewer](https://fluenticons.co/)
- [Google Material Design Icons](https://fonts.google.com/icons)
- [MDI](https://pictogrammers.com/library/mdi/)

> [!NOTE]  
> In the current version of this package, the original svg paths of an icon are merged into a single path.
> It can therefore happen that some icons may not look like the original. Especially when Flux tries to show a solid variant of an icon that originally does not have a solid or filled version.

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
| `-a\|--all`     | Build all icons from the vendor. **Note:** this might generate over thousands of files and cause `npm run dev` to crash due to memory issues. |
| `-v\|--verbose` | Show additional messages during build. |

The artisan script will try to install the vendor's icon package using `npm install`.

### Usage

Since this package publishes all icons to `resources/views/flux/icon/{vendor}/` you can simply use the Blade convention of referencing the icons within your flux:icon component. So for example:

```html
<flux:icon.tabler.confetti />
```

or

```html
<flux:icon name="tabler.confetti-off"/>
```


## Publish config

You can publish the config file to adjust settings for a specific vendor or add your own vendor. In case you add your own vendor, please share or make a PR so others can use it too!

```cmd
php artisan vendor:publish --tag=config
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
| `default_stroke_width` | `float` | For outline icons a default stroke width can be configured. The default Flux Heroicons uses a width of 1.5. |

### Vendor specific configuration

The vendor specific configuration sits within the `vendors` key. Each vendor should have a key. That key will be used as directory name when exporting the icons.

```php
'vendors' => [
    'tabler' => [
        'vendor' => 'Tabler',
        'namespace' => 'tabler',
        'package' => '@tabler/icons',
        'variants' => [ 
            //...
        ]
    ]
 ]
```

| Option     | Value     | Default  |Description                                                                 |
|------------|-----------|----------|----------------------------------------------------------------------------|
| `vendor`    | `string` |         | Human readable name of the vendor. |
| `namespace` | `string`  |         | The namespace for the Flux icon, in case omitted, the vendor name will be used. |
| `package`   | `string` |          | The npm package that should be installed to retrieve the icons. |
| `baseVariant` | `string` | `outline` | The default variant to use as basis. Generally the vendors variant that has the most icons. |
| `variants`  | `array` |           | The configuration for each of the variants (outline, solid, mini, micro). |
| `attributes` | `[class, method]` | `null` | A callback to adjust the attributes on the SVG. |
| `transform` | `[class, method]` | `null` | A callback to transform the SVG path data. |
| `stroke_width`| `[class, method]` | `null` | A callback to determine the whether the stroke width should be changed on this icon. |

#### Variants

```php
    'variants' => [
        'outline' => [
            'source' => [],
            'template' => 'outline',
            'fallback' => 'default', 
            'stroke_width' => false, 
            'size' => 24,
            'attributes' = []
        ],
        'solid' => [
            'source' => [],

        ],
        'mini' => [
            'base' => 'solid'
        ],
        'micro' => [
            'base' => 'solid'
        ],
    ]
```

| Option     | Value     | Default  |Description                                                                 |
|------------|-----------|----------|----------------------------------------------------------------------------|
| `source`    |  `string\|callable array` |         |  |
| `template` | `string`  |         | |
| `fallback`   | `string\|callable array` |          | |
| `stroke_width` | `int\|float` |  |  |
| `size`  | `int` |           | |
| `attributes` |  `array` | `` |  |
| `base` |  `string` | `null` | |

#### Source

The source directories specify where the script can find the outline and solid/filled versions of the icons you want to build.
In case the vendor uses a prefix or suffix for the icons, we want to configure it here to determine the basename of the icon and make them more accessible in flux.
For both source directories (outline and solid), an optional `filter` callback can be defined to indicate whether a file in the directory should be used as outline or solid respectively.

```php
[
    'dir' => 'node_modules/vendor/icons/...',
    'prefix' => null,
    'suffix' => null 
    'filter' => [ Ympact\FluxIcons\Services\Vendor\VendorName::class, 'filter']
]
```

For the **outline** icons, the function passes two params `file` and `icons`. The file is the actual filename that should be filtered out or not. The `icons` is an array of icons that the user requested to build. This is passed by reference in case this array needs to be adjusted. See the [Mdi class](src/Services/Vendors/Mdi.php) as example.

For the **solid** icons, the filter callback passes a single param `file`. Optionally callbacks can be defined on `dir`, `prefix` and `suffix` to adjust these according to the icon size. The sizes passed to these callbacks are 24, 20 and 16 (the defaults of the Flux icon component).

```php
'solid' => [ 
    [
        'dir' => 'node_modules/vendor/icons/icons/filled', 
        'prefix' => null, 
        'suffix' => [ Ympact\FluxIcons\Services\Vendor\VendorName::class, 'sourceSolidSuffix']
    ],
],
```

#### Fallbacks for icon variants

Due to the way the flux icon component is made, it requires 4 variants: an outline and a solid of three sizes:

- solid - 24px
- mini - 20px
- micro - 16px

Using the configuration of the vendor, you can determine how to handle the building of the icon when there is no source file for a certain variant. Thhe options are

| Value     |Description                                                                 |
|-----------|----------------------------------------------------------------------------|
| `false`   |  |
| `default`  |  |
| `{variant}` |  |
| `callback array` |  |

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

- A placeholder avatar icon, using an icon or initials

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
- Adding more vendors
- Helper script to create configurations for new vendors
- Improving testing
- Documentation
