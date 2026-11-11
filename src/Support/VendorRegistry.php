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
 *
 * Options configured for a vendor are applied to every instance it hands out.
 */
class VendorRegistry
{
    /** @var array<string, class-string<Vendor>> */
    protected array $vendors = [];

    /** @var array<string, Vendor> */
    protected array $resolved = [];

    /** @var array<string, array<string, string|int>> */
    protected array $options = [];

    /**
     * @param  array<string, class-string<Vendor>|Vendor|mixed>  $vendors
     * @param  array<string, mixed>  $options  Option values by vendor key, such as ['flags' => ['ratio' => '1x1']].
     *
     * @throws InvalidArgumentException When an entry is not a vendor class, such as a v1 array, or
     *                                  options name a vendor that is not registered or a value it
     *                                  does not take.
     */
    final public function __construct(array $vendors = [], array $options = [])
    {
        // stored before registering, since an instance is configured as it is registered
        $this->options = array_filter($options, 'is_array');

        foreach ($vendors as $key => $vendor) {
            if (! is_string($vendor) && ! $vendor instanceof Vendor) {
                throw new InvalidArgumentException(
                    "Vendor [{$key}] must map to a vendor class, such as Ympact\\FluxIcons\\Vendors\\Tabler::class. ".
                    'A published v1 config describes vendors as arrays. Replace it with `php artisan vendor:publish --tag=flux-icons-config --force`, '.
                    'which overwrites config/flux-icons.php, then copy your changes over.'
                );
            }

            $this->register((string) $key, $vendor);
        }

        foreach ($options as $key => $values) {
            if (! $this->has((string) $key)) {
                throw new InvalidArgumentException(
                    "Options are configured for vendor [{$key}], which is not registered. Registered vendors: ".implode(', ', $this->keys()).'.'
                );
            }

            if (! is_array($values)) {
                throw new InvalidArgumentException("The options of vendor [{$key}] must map option names to values, such as ['ratio' => '1x1'].");
            }

            // resolved now, so a value the vendor does not take fails here rather than mid-command
            $this->get((string) $key);
        }
    }

    public static function fromConfig(): static
    {
        /** @var array<string, class-string<Vendor>> $vendors */
        $vendors = (array) config('flux-icons.vendors', []);

        return new static($vendors, (array) config('flux-icons.options', []));
    }

    /**
     * @param  string|Vendor  $vendor  A vendor class name or instance; any other class is rejected.
     *
     * @throws InvalidArgumentException When the class is not a vendor.
     */
    public function register(string $key, string|Vendor $vendor): static
    {
        if ($vendor instanceof Vendor) {
            // a copy, so one instance registered under two keys answers to each
            $this->resolved[$key] = $this->configure($key, clone $vendor);
            $this->vendors[$key] = $vendor::class;

            return $this;
        }

        if (! is_subclass_of($vendor, Vendor::class)) {
            throw new InvalidArgumentException(
                "Vendor [{$key}] must be registered as a subclass of ".Vendor::class.", [{$vendor}] given."
            );
        }

        $this->vendors[$key] = $vendor;

        // drop any instance resolved for a previously registered class, otherwise
        // replacing a vendor would silently keep handing out the old one
        unset($this->resolved[$key]);

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

        return $this->resolved[$key] ??= $this->configure($key, new $this->vendors[$key]);
    }

    /**
     * @throws InvalidArgumentException When a configured option is not one the vendor takes.
     */
    protected function configure(string $key, Vendor $vendor): Vendor
    {
        return $vendor->registerAs($key)->withOptions($this->options[$key] ?? []);
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
        // most vendors publish under their own key, so try that before looking further
        if ($this->has($namespace) && $this->get($namespace)->namespace() === $namespace) {
            return $this->get($namespace);
        }

        // resolve one vendor at a time and stop at the first match, rather than
        // instantiating every registered vendor to answer the question
        foreach ($this->keys() as $key) {
            $vendor = $this->get($key);

            if ($vendor->namespace() === $namespace) {
                return $vendor;
            }
        }

        return null;
    }
}
