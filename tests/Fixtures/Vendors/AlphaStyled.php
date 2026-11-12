<?php

namespace Ympact\FluxIcons\Tests\Fixtures\Vendors;

use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Variants\Variant;
use Ympact\FluxIcons\Variants\VariantSet;

/**
 * Alpha with a build option: `style` picks which drawing the outline variant is built from.
 */
class AlphaStyled extends Alpha
{
    /**
     * @return array<string, array<int, string|int>>
     */
    public function options(): array
    {
        return ['style' => ['outline', 'filled']];
    }

    /**
     * @return array<string, string|int>
     */
    public function optionDefaults(): array
    {
        return ['style' => 'outline'];
    }

    public function variants(): VariantSet
    {
        $directory = $this->option('style') === 'filled' ? 'filled' : 'outline';

        return parent::variants()->variant('outline', fn (Variant $variant) => $variant->source(Source::dir($directory)));
    }
}
