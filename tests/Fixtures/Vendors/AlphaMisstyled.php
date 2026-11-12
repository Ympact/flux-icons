<?php

namespace Ympact\FluxIcons\Tests\Fixtures\Vendors;

/**
 * A subclass whose option default is not a value the option takes, registered by class name
 * so the registry only notices once it resolves the vendor.
 */
class AlphaMisstyled extends AlphaStyled
{
    /**
     * @return array<string, string|int>
     */
    public function optionDefaults(): array
    {
        return ['style' => 'bold'];
    }
}
