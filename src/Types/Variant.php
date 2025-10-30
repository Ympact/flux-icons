<?php

namespace Ympact\FluxIcons\Types;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Ympact\FluxIcons\Types\Fill;
use Ympact\FluxIcons\Types\Stroke;

use function PHPUnit\Framework\isBool;

class Variant implements Arrayable
{
    public ?string $name = null;

    // a class to define variants
    public ?Stroke $stroke = null;

    /**
     * None, one or multiple fills can be defined for duotone/multitone icons
     * @var null|Collection<Fill>
     */
    public ?Collection $fills = null;

    public bool $isFullColor = false;

    public $isDefault = false;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    // set stroke width, linecap, linejoin
    public function stroke(bool|Stroke|Closure $stroke): self
    {
        if(isBool($stroke) && $stroke === false) {
            $this->stroke = null;
            return $this;
        }

        if($stroke instanceof Closure) {
            $newStroke = new Stroke;
            $stroke($newStroke);
            $stroke = $newStroke;
        }

        $this->stroke = $stroke;
        return $this;
    }

    public function addFill(bool|Fill|Closure $fill): self
    {
        if(isBool($fill) && $fill === false) {
            $this->fills = null;
            return $this;
        }

        if($fill instanceof Closure) {
            $newFill = new Fill;
            $fill($newFill);
            $fill = $newFill;
        }

        if (is_null($this->fills)) {
            $this->fills = new Collection();
        }

        $this->fills->push($fill);
        return $this;
    }

    public function fullColor(): self
    {
        // in case of full color icons disable stroke and fills
        $this->stroke = null;
        $this->fills = null;
        $this->isFullColor = true;
        return $this;
    }

    public function default(): self
    {
        $this->isDefault = true;
        return $this;
    }

    /**
     * Implementing Arrayable interface
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stroke' => $this->stroke,
            'fills' => $this->fills,
        ];
    }

}