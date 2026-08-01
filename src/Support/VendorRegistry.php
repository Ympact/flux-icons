<?php

namespace Ympact\FluxIcons\Support;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Ympact\FluxIcons\Contracts\Vendor;

/**
 * Resolves vendor keys to vendor instances.
 *
 * The config maps a key to a vendor class, so a user registers their own vendor
 * (or a subclass of a built-in one) by adding a single line:
 *
 *     'vendors' => [
 *         'tabler' => Tabler::class,
 *         'house-style' => App\FluxIcons\HouseStyle::class,
 *     ]
 */
class VendorRegistry
{
    /** @var array<string, class-string<Vendor>> */
    protected array $vendors = [];

    /** @var array<string, Vendor> */
    protected array $resolved = [];

    /**
     * @param  array<string, class-string<Vendor>|Vendor>  $vendors
     */
    final public function __construct(array $vendors = [])
    {
        foreach ($vendors as $key => $vendor) {
            $this->register((string) $key, $vendor);
        }
    }

    public static function fromConfig(): static
    {
        /** @var array<string, class-string<Vendor>> $vendors */
        $vendors = (array) config('flux-icons.vendors', []);

        return new static($vendors);
    }

    /**
     * @param  class-string<Vendor>|Vendor  $vendor
     */
    public function register(string $key, string|Vendor $vendor): static
    {
        if ($vendor instanceof Vendor) {
            $this->resolved[$key] = $vendor;
            $this->vendors[$key] = $vendor::class;

            return $this;
        }

        if (! is_subclass_of($vendor, Vendor::class)) {
            throw new InvalidArgumentException(
                "Vendor [{$key}] must be registered as a subclass of ".Vendor::class.", [{$vendor}] given."
            );
        }

        $this->vendors[$key] = $vendor;

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->vendors[$key]);
    }

    public function get(string $key): Vendor
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException(
                "Vendor [{$key}] is not registered. Available vendors: ".implode(', ', $this->keys()).'.'
            );
        }

        return $this->resolved[$key] ??= new $this->vendors[$key];
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->vendors);
    }

    /**
     * @return Collection<string, Vendor>
     */
    public function all(): Collection
    {
        return (new Collection($this->keys()))
            ->mapWithKeys(fn (string $key) => [$key => $this->get($key)]);
    }

    /**
     * Find the vendor that publishes to the given Flux namespace.
     *
     * Built icons live in a directory named after the namespace, which is not
     * necessarily the key the vendor is registered under.
     */
    public function findByNamespace(string $namespace): ?Vendor
    {
        if ($this->has($namespace)) {
            return $this->get($namespace);
        }

        return $this->all()->first(fn (Vendor $vendor) => $vendor->namespace() === $namespace);
    }
}
