<?php

use Illuminate\Support\Facades\Config;
use Ympact\FluxIcons\Contracts\Vendor;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Support\VendorRegistry;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\AlphaStyled;
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

it('explains that a v1 vendor array has to become a vendor class', function () {
    new VendorRegistry(['tabler' => ['package' => '@tabler/icons']]);
})->throws(InvalidArgumentException::class, 'Vendor [tabler] must map to a vendor class');

it('accepts an already constructed vendor instance', function () {
    $vendor = new RegistryFixtureVendor;
    $registry = new VendorRegistry(['fixture' => $vendor]);

    expect($registry->get('fixture'))->toBeInstanceOf(RegistryFixtureVendor::class)
        ->and($registry->get('fixture')->key())->toBe('fixture');
});

it('registers a copy of an instance, so one instance can sit under two keys', function () {
    $vendor = new Tabler;
    $registry = new VendorRegistry(['first' => $vendor, 'second' => $vendor]);

    expect($registry->get('first')->namespace())->toBe('first')
        ->and($registry->get('second')->namespace())->toBe('second')
        ->and($vendor->key())->toBe('tabler');
});

it('addresses a vendor by the key it is registered under', function (string|Vendor $vendor) {
    $registered = (new VendorRegistry(['icons' => $vendor]))->get('icons');

    expect($registered->key())->toBe('icons')
        ->and($registered->namespace())->toBe('icons');
})->with([
    'as a class' => [Tabler::class],
    'as an instance' => [fn () => new Tabler],
]);

it('publishes under the key as a slug, as v1 did, and is found by that namespace', function () {
    $registry = new VendorRegistry(['tabler_icons' => Tabler::class]);

    expect($registry->get('tabler_icons')->namespace())->toBe('tabler-icons')
        ->and($registry->findByNamespace('tabler-icons')?->key())->toBe('tabler_icons');
});

it('keeps the namespace a vendor publishes to when it is registered under another key', function () {
    $registered = (new VendorRegistry(['fixture' => RegistryFixtureVendor::class]))->get('fixture');

    expect($registered->key())->toBe('fixture')
        ->and($registered->namespace())->toBe('fixture-ns');
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

it('tells a v1 user the publish command that actually replaces the config', function () {
    new VendorRegistry(['tabler' => ['package' => '@tabler/icons']]);
})->throws(InvalidArgumentException::class, 'php artisan vendor:publish --tag=flux-icons-config --force');

it('applies the options configured for a vendor to the vendor it hands out', function () {
    $registry = new VendorRegistry(['alpha' => AlphaStyled::class], ['alpha' => ['style' => 'filled']]);

    expect($registry->get('alpha')->option('style'))->toBe('filled')
        ->and($registry->get('alpha')->selectedOptions())->toBe(['style' => 'filled']);
});

it('applies the configured options to a vendor registered as an instance', function () {
    $registry = new VendorRegistry(['alpha' => new AlphaStyled], ['alpha' => ['style' => 'filled']]);

    expect($registry->get('alpha')->option('style'))->toBe('filled');
});

it('refuses options for a vendor that is not registered', function () {
    new VendorRegistry(['alpha' => AlphaStyled::class], ['alhpa' => ['style' => 'filled']]);
})->throws(InvalidArgumentException::class, 'Options are configured for vendor [alhpa], which is not registered. Registered vendors: alpha.');

it('refuses a configured option value the vendor does not take, as it is read', function () {
    new VendorRegistry(['alpha' => AlphaStyled::class], ['alpha' => ['style' => 'bold']]);
})->throws(InvalidArgumentException::class, '[bold] is not a valid value for option [style] of vendor [alpha]');

it('refuses options that are not a map of names to values', function () {
    new VendorRegistry(['alpha' => AlphaStyled::class], ['alpha' => 'filled']);
})->throws(InvalidArgumentException::class, 'The options of vendor [alpha] must map option names to values');
