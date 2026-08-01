<?php

use Ympact\FluxIcons\Console\BuildFluxIconsCommand;

/**
 * Exposes the protected icon list helpers so the --merge behaviour can be tested
 * without running a full build (which would require an installed npm package).
 */
function iconListHelper(): object
{
    return new class extends BuildFluxIconsCommand
    {
        /**
         * @param  string|array<int,string>|null  $icons
         * @param  array<int,string>|null  $configVendorIcons
         * @return array<int,string>
         */
        public function mergeIcons($icons, $configVendorIcons): array
        {
            return $this->mergeWithConfiguredIcons($icons, $configVendorIcons);
        }

        /**
         * @param  string|array<int,string>|null  $icons
         * @return array<int,string>
         */
        public function normalize($icons): array
        {
            return $this->normalizeIcons($icons);
        }
    };
}

it('merges icons from the option with the icons configured for the vendor', function () {
    expect(iconListHelper()->mergeIcons('confetti,confetti-off', ['home', 'star']))
        ->toBe(['confetti', 'confetti-off', 'home', 'star']);
});

it('does not repeat icons that are both requested and configured', function () {
    expect(iconListHelper()->mergeIcons('home,confetti', ['home', 'star']))
        ->toBe(['home', 'confetti', 'star']);
});

it('returns only the configured icons when no icons are requested', function () {
    expect(iconListHelper()->mergeIcons(null, ['home', 'star']))
        ->toBe(['home', 'star']);
});

it('returns only the requested icons when the vendor has no configured icons', function () {
    expect(iconListHelper()->mergeIcons('home,star', null))
        ->toBe(['home', 'star']);
});

it('accepts an array of icons as well as a comma separated list', function () {
    expect(iconListHelper()->mergeIcons(['home'], ['star']))
        ->toBe(['home', 'star']);
});

it('trims whitespace and drops empty entries from an icon list', function () {
    expect(iconListHelper()->normalize(' home , , star '))
        ->toBe(['home', 'star']);
});
