# Flux icons

This is a laravel package to use icons from different vendors than the default Heroicons.

## Installation


## Building icons

You will need to build the icons yourself once installed. This can be done using the artisan command `flux-icons:build` you can optionally pass the vendor name. In case you did not provide this, the script will ask you.

```cmd
php artisan flux-icons:build tabler
```

The artisan script will try to install the icon package using `npm install`. Right after it will try to convert all icons into a flux icon blade component. 
Due to the way the flux icon component is made, it requires 4 variants: an outline and a solid of three sizes (24, 20, 16). 
For the first version of this Flux Icons package, it treats the source icons as follows:
- In case there is only one solid size variant in the source package, it will use the same svg for all three size variants. 
- In case there is no solid variant, it will use the outline variant. 
- In case the solid variant does not have an outline variant, the icon is not exported at all.


## Usage

Since this package publishes all icons to `resources/views/flux/icon/{vendor}/` you can simply use the Blade convention of referencing the icons within your flux:icon component. So for example:

```html
<flux:icon.tabler.confetti />
```

or

```html
<flux:icon name="tabler.confetti-off"/>
```

## Publish config

You can publish the config file to adjust settings for a specific vendor or add your own vendor. In case you add your own vendo, please share so others can use it too!

```cmd
php artisan vendor:publish --tag=flux-icons-config
```

## Support

- Tabler Icons (smaller icons are scaled, in case a solid verion is not available, it is not exported)
- Fluent Icons
- Material Design

## Advanced configuration

The configuration file provides two callbacks to allow for adjustments on the paths of specific icons.
For example for the tabler outline icons, an outline path should be removed.

| Callback Method         | Description                                                                 |
|-------------------------|-----------------------------------------------------------------------------|
| `transform_svg_path`    | A callback to transform the SVG path data. Takes a single parameter: the SVG path string. |
| `change_stroke_width`   | A callback to determine the whether the stroke width should be changed on this icon. |

```php
'transform_svg_path' => function($variant, $iconName, $svgPaths) {
    // Your transformation logic here
},

'change_stroke_width' => function($iconName, $defaultStrokeWidth, $svgPaths) {
    // Your filtering logic here
},
```

```
'transform_svg_path' => function()

```