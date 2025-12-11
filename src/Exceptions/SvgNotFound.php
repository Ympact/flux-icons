<?php

declare(strict_types=1);

namespace Ympact\FluxIcons\Exceptions;

use Exception;

final class SvgNotFound extends Exception
{
    public static function missing(string $set, string $name): self
    {
        return new self("Svg by name \"$name\" from set \"$set\" not found.");
    }
}