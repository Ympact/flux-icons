<?php

use Illuminate\Support\Facades\Config;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Support\VendorRegistry;
use Ympact\FluxIcons\Variants\VariantSet;
use Ympact\FluxIcons\Vendors\Phosphor;
use Ympact\FluxIcons\Vendors\Tabler;

class RegistryFixtureVendor extends Vendor
{
    public function package(): string
    {
        return 'fixture-icons';
    }

    public function namespace(): string
    {
        return 'fixture-ns';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()->variant('outline', fn ($v) => $v->source(Source::dir('icons')));
    }
}

/**
 * Counts its own construction, so a lookup can assert which vendors it had to build.
 */
class ProbeVendorA extends Vendor
{
    public static int $constructed = 0;

    public function __construct()
    {
        static::$constructed++;
    }

    public function package(): string
    {
        return 'probe-a-icons';
    }

    public function namespace(): string
    {
        return 'probe-a';
    }

    public function variants(): VariantSet
    {
        return VariantSet::make()->variant('outline', fn ($v) => $v->source(Source::dir('icons')));
    }
}

class ProbeVendorB extends ProbeVendorA
{
    public static int $constructed = 0;

    public function package(): string
    {
        return 'probe-b-icons';
    }

    public function namespace(): string
    {
        return 'probe-b';
    }
}

it('resolves a registered vendor to an instance', function () {
    $registry = new VendorRegistry(['tabler' => Tabler::class]);

    expect($registry->get('tabler'))->toBeInstanceOf(Tabler::class);
});

it('returns the same instance on repeated lookups', function () {
    $registry = new VendorRegistry(['tabler' => Tabler::class]);

    expect($registry->get('tabler'))->toBe($registry->get('tabler'));
});

it('reports which vendors are registered', function () {
    $registry = new VendorRegistry(['tabler' => Tabler::class, 'phosphor' => Phosphor::class]);

    expect($registry->keys())->toBe(['tabler', 'phosphor'])
        ->and($registry->has('tabler'))->toBeTrue()
        ->and($registry->has('lucide'))->toBeFalse();
});

it('throws a helpful error for an unregistered vendor', function () {
    (new VendorRegistry(['tabler' => Tabler::class]))->get('lucide');
})->throws(InvalidArgumentException::class, 'Vendor [lucide] is not registered. Available vendors: tabler.');

it('rejects a class that is not a vendor', function () {
    new VendorRegistry(['broken' => stdClass::class]);
})->throws(InvalidArgumentException::class, 'must be registered as a subclass');

it('accepts an already constructed vendor instance', function () {
    $vendor = new RegistryFixtureVendor;
    $registry = new VendorRegistry(['fixture' => $vendor]);

    expect($registry->get('fixture'))->toBe($vendor);
});

it('hands out the replacement when a key is registered again', function () {
    $registry = new VendorRegistry(['icons' => new RegistryFixtureVendor]);

    // resolve it once so the instance is cached, then replace it
    $registry->get('icons');
    $registry->register('icons', Tabler::class);

    expect($registry->get('icons'))->toBeInstanceOf(Tabler::class);
});

it('builds itself from the vendors config', function () {
    Config::set('flux-icons.vendors', ['tabler' => Tabler::class]);

    expect(VendorRegistry::fromConfig()->get('tabler'))->toBeInstanceOf(Tabler::class);
});

it('finds a vendor by the namespace its icons are published under', function () {
    $registry = new VendorRegistry(['fixture' => RegistryFixtureVendor::class]);

    expect($registry->findByNamespace('fixture-ns'))->toBeInstanceOf(RegistryFixtureVendor::class);
});

it('finds a vendor by its key when the namespace matches it', function () {
    $registry = new VendorRegistry(['tabler' => Tabler::class]);

    expect($registry->findByNamespace('tabler'))->toBeInstanceOf(Tabler::class);
});

it('returns null for a namespace no vendor publishes to', function () {
    $registry = new VendorRegistry(['tabler' => Tabler::class]);

    expect($registry->findByNamespace('flux-icons'))->toBeNull();
});

it('does not match a vendor whose key looks like the namespace but publishes elsewhere', function () {
    // keyed 'tabler', but RegistryFixtureVendor publishes its icons to 'fixture-ns',
    // so nothing is written to a 'tabler' directory for it to be found by
    $registry = new VendorRegistry(['tabler' => RegistryFixtureVendor::class]);

    expect($registry->findByNamespace('tabler'))->toBeNull()
        ->and($registry->findByNamespace('fixture-ns'))->toBeInstanceOf(RegistryFixtureVendor::class);
});

it('stops resolving vendors once the namespace matches', function () {
    ProbeVendorA::$constructed = 0;
    ProbeVendorB::$constructed = 0;

    $registry = new VendorRegistry([
        'a' => ProbeVendorA::class,
        'b' => ProbeVendorB::class,
    ]);

    $registry->findByNamespace('probe-a');

    // B sits after the match and must not be built just to answer the lookup
    expect(ProbeVendorA::$constructed)->toBe(1)
        ->and(ProbeVendorB::$constructed)->toBe(0);
});
