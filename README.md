# Flux Icons

This is a laravel package to customize the icons for [Livewire Flux](https://github.com/livewire/flux). It builds the icons from various vendors into a `flux:icon` component.

## Installation

Generally you want to install this package only in your local development environment and build and publish the icons you need.

```cmd
composer require --dev ympact/flux-icons
```

## Building icons

You will need to build the icons yourself once the package is installed. This can be done using the artisan command `flux-icons:build` you can optionally pass the vendor name as the first argument. 
In case you did not provide this, the script will ask you.

```cmd
php artisan flux-icons:build tabler --icons=confetti
```

### Options

| Option          | Description                                                                                        |
|-----------------|----------------------------------------------------------------------------------------------------|
| `--icons=`      | The icons to build (single or comma separated list). . Cannot be used in combination with `--all`. |
| `-m\|--merge`   | Merge the icons listed in the `--icons` options with the icons defined in the config. Cannot be used in combination with `--all`. |
| `-a\|--all`     | Build all icons from the vendor. Note: this might generate over thousands of files.                |
| `-v\|--verbose` | Show additional messages during build |

The artisan script will try to install the icon package using `npm install`. Right after it will try to convert all icons into a flux icon blade component.

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
For the first version of this Flux Icons package, it treats the source icon as follows:

- In case there is only one solid size variant in the source package, it will use the same svg for all three size variants. Generally the svg will be scaled by the browser.
- In case there is no solid variant, it will use the outline variant as the solid variant. 
- In case the solid variant does not have an outline variant, the icon is not exported at all.

## Publish config

You can publish the config file to adjust settings for a specific vendor or add your own vendor. In case you add your own vendo, please share so others can use it too!

```cmd
php artisan vendor:publish --tag=flux-icons-config
```

## Support

- Tabler Icons (smaller icons are scaled, in case a solid verion is not available, it is not exported)

## Advanced configuration

| Option     | Valaue     | Description                                                                 |
|-------------------------|-----------------------------------------------------------------------------|
| `icons`    |  `null` or `['vendorName' => ['icon-name', ...] ]` | A list of icons that will be build/updated by default in case no icons are passed to `flux-icons:build` command.  |
| `default_stroke_wdith` | `float` | For outline icons a default stroke width can be configured. The default Flux Heroicons uses a width of 1.5. |

### Vendor specific configuration

The vendor specific configuration sits within the `vendors` key. Each vendor should have a key. That key will be used as directory name when exporting the icons.

```php
'vendors' => [
    'tabler' => [
        'vendor_name' => 'Tabler',
        'package_name' => '@tabler/icons',
        'source_directories' => [ 
            //...
        ]
    ]
 ]
```

| Option     | Value     | Description                                                                 |
|-------------------------|-----------------------------------------------------------------------------|
| `vendor_name`    |  `string` | Human readable name of the vendor.  |
| `package_name` | `string` | The npm package that should be installed to retrieve the icons. |
| `source_directories.outline` | `array\|string` | The directory in which the vendors outline icons reside. For specific options see below. |
| `source_directories.solid` | `array\|string` | The directory in which the vendors solid icons reside. For specific options see below. |
| `transform_svg_path`    |  `callable` | A callback to transform the SVG path data. Takes a single parameter: the SVG path string. |
| `change_stroke_width`   |  `callable` | A callback to determine the whether the stroke width should be changed on this icon. |

#### Source directories

In case the vendor uses a prefix or suffix for the icons, we want to configure it here to determine the basename of the icon and make them more accessible in flux.

```php
[
    'dir' => 'node_modules/vendor/icons/...',
    'prefix' => null,
    'suffix' => null 
]
```

For the **solid** icons, optionally directories and suffix/prefices per icon size can be defined:

```php
'solid' => [ 
    // in case there are different sizes of solid icons or they have a prefix or suffix in the name
    '24' => ['dir' => 'node_modules/vendor/icons/icons/filled', 'prefix' => null, 'suffix' => '-24'],
    '20' => ['dir' => 'node_modules/vendor/icons/icons/filled', 'prefix' => null, 'suffix' => '-20'],
    '16' => ['dir' => 'node_modules/vendor/icons/icons/filled/sm', 'prefix' => null, 'suffix' => null],
],
```

#### Transform icons

The configuration file provides two callbacks to allow for adjustments on the paths and stroke width of specific icons.
See the configuration for the Tabler icons as example how to use this.

```php
/**
 * @param string $variant (solid, outline)
 * @param string $iconName
 * @param collection<SvgPath> $svgPaths
 */
'transform_svg_path' => function($variant, $iconName, $svgPaths) {
    // Your transformation logic here
},

/**
 * @param string $iconName
 * @param float $defaultStrokeWidth
 * @param collection<SvgPath> $svgPaths
 */
'change_stroke_width' => function($iconName, $defaultStrokeWidth, $svgPaths) {
    // Your filtering logic here
},
```

## Roadmap

- Add command for updating/rebuilding icons
- Adding more vendors
