<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\Alpha;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\Epsilon;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Variant;
use Ympact\FluxIcons\Variants\VariantSet;
use Ympact\FluxIcons\Vendors\Mdi;

beforeEach(function () {
    Event::fake([IconsBuilt::class]);

    $this->output = sys_get_temp_dir().'/flux-icons-build-'.Str::random(8);
    $this->builder = new Builder(base_path('tests/vendor'), $this->output);
});

afterEach(function () {
    File::deleteDirectory($this->output);
});

it('announces the components it wrote', function () {
    $result = $this->builder->build(new Alpha, ['home', 'dots']);

    expect($result->built)->toBe(['dots', 'home']);

    Event::assertDispatched(IconsBuilt::class, fn (IconsBuilt $event) => $event->vendor === 'alpha'
        && $event->namespace === 'alpha'
        && $event->directory === "{$this->output}/alpha"
        && $event->icons === ['dots', 'home']);
});

it('announces nothing when it wrote nothing', function () {
    $result = $this->builder->build(new Alpha, ['does-not-exist']);

    expect($result->built)->toBe([])
        ->and($result->missing)->toBe(['does-not-exist']);

    Event::assertNotDispatched(IconsBuilt::class);
});

it('reports each icon to the progress callback once it is handled', function () {
    $handled = [];

    $this->builder->build(new Alpha, null, function (string $name, int $done, int $total) use (&$handled) {
        $handled[] = "{$name} {$done}/{$total}";
    });

    expect($handled)->toBe(['arrow-left 1/3', 'dots 2/3', 'home 3/3']);
});

it('selects icons by the name they are published under', function () {
    $vendor = new class extends Alpha
    {
        public function key(): string
        {
            return 'alpha';
        }

        public function iconName(string $name): string
        {
            return "a-{$name}";
        }
    };

    $result = $this->builder->build($vendor, ['a-home', 'a-missing']);

    expect($result->built)->toBe(['a-home'])
        ->and($result->missing)->toBe(['a-missing'])
        ->and(File::exists("{$this->output}/alpha/a-home.blade.php"))->toBeTrue();
});

it('skips an icon a variant has no source for, and says why', function () {
    $vendor = new class extends Alpha
    {
        public function key(): string
        {
            return 'alpha';
        }

        public function variants(): VariantSet
        {
            return parent::variants()->variant('solid', fn (Variant $variant) => $variant->fallback(Fallback::skip()));
        }
    };

    $result = $this->builder->build($vendor);

    expect($result->built)->toBe(['arrow-left', 'home'])
        ->and($result->skipped)->toBe(['dots' => 'no source for its solid variant'])
        ->and(File::exists("{$this->output}/alpha/dots.blade.php"))->toBeFalse();
});

it('builds an MDI icon without an outline file from the one file it has', function () {
    $result = $this->builder->build(new Mdi);

    $abacus = (string) file_get_contents("{$this->output}/mdi/abacus.blade.php");
    $account = (string) file_get_contents("{$this->output}/mdi/account.blade.php");

    expect($result->built)->toBe(['abacus', 'account'])
        ->and($result->skipped)->toBe([])
        ->and(substr_count($abacus, 'd="M1 1h2v2H1z"'))->toBe(4)
        ->and(substr_count($account, 'd="M3 3h6v6H3z"'))->toBe(1)
        ->and(substr_count($account, 'd="M2 2h4v4H2z"'))->toBe(3);
});

it('draws a variant that falls back exactly as the variant it falls back to', function () {
    $vendor = new class extends Alpha
    {
        public function key(): string
        {
            return 'alpha';
        }

        public function variants(): VariantSet
        {
            return parent::variants()
                ->variant('outline', fn (Variant $variant) => $variant->stroke(new Stroke(1.5)))
                ->variant('solid', fn (Variant $variant) => $variant->attributes(['fill-rule' => 'evenodd']));
        }
    };

    $this->builder->build($vendor, ['dots']);

    preg_match_all("/case \\('(\\w+)'\\): \\?>\\s*(.*?)\\s*<\\?php break; \\?>/s", (string) file_get_contents("{$this->output}/alpha/dots.blade.php"), $slots);
    $slots = array_combine($slots[1], $slots[2]);

    expect($slots['outline'])->toContain('stroke-linecap="round"')
        ->and($slots['solid'])->toBe($slots['outline'])
        ->and($slots['micro'])->toBe($slots['outline']);
});

it('lists the icons a vendor ships by name', function () {
    expect(array_keys($this->builder->available(new Epsilon)))->toBe(['arrow-left', 'home']);
});
