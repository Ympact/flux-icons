<?php

// class that is a representation of an icon using DomDocument and XPath

namespace Ympact\FluxIcons\Types;

class Stroke
{
    public $width = 1;

    public $linecap = 'round';

    public $linejoin = 'round';

    public function __construct(
        float|int $width = 1, string $linecap = 'round', string $linejoin = 'round')
    {
        $this->width = $width;
        $this->linecap = $linecap;
        $this->linejoin = $linejoin;
    }
}
