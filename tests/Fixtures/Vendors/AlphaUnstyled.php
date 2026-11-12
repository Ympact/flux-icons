<?php

namespace Ympact\FluxIcons\Tests\Fixtures\Vendors;

/**
 * A vendor that declares an option without a default.
 */
class AlphaUnstyled extends AlphaStyled
{
    /**
     * @return array<string, string|int>
     */
    public function optionDefaults(): array
    {
        return [];
    }
}
