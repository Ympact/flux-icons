<?php

namespace Ympact\Typesense\Values;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use Stringable;

class CsvString implements Stringable, Arrayable, Castable
{
    protected array $csv;

    protected function __construct(array $csv)
    {
        $this->csv = $csv;
    }

    public static function from(array|string $values): self
    {
        return new self(is_array($values) 
            ? $values 
            : self::fromString($values)
        );
    }

    public function get(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        return implode(',', $this->csv);
    }

    public function toArray(): array
    {
        return $this->csv;
    }

    /**
     * @method fromString
     * @param string $csv
     * @return array<string>
     */
    private static function fromString(string $csv): array
    {
        return  array_map('trim', explode(',', $csv));
    }

    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(\Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes): CsvString 
            {
                return CsvString::from($value);
            }
 
            public function set(\Illuminate\Database\Eloquent\Model $model, string $key, mixed $value, array $attributes): array
            {
                if(! $value instanceof CsvString) {
                    throw new InvalidArgumentException('The given value is not a CsvString instance.');
                }
                return CsvString::from($value)->toArray();
            }
        };
    }
}
