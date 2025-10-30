<?php

namespace Ympact\FluxIcons\Types;

class Fill
{
    public $name = 'primary';

    public $source = '#000000';

    public $target = '#000000';

    public $opacity = 1.0;

    public function __construct(
        string $name = 'primary',
        string $source = '#000000',
        string $target = '#000000',
        float|int $opacity = 1.0
    ) {
        $this->name = $name;
        $this->source = $source;
        $this->target = $target;
        $this->opacity = $opacity;
    }
}