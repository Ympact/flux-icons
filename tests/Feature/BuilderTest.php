<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Ympact\FluxIcons\Build\Builder;
use Ympact\FluxIcons\Events\IconsBuilt;
use Ympact\FluxIcons\Sources\Source;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\Alpha;
use Ympact\FluxIcons\Tests\Fixtures\Vendors\Epsilon;
use Ympact\FluxIcons\Variants\Fallback;
use Ympact\FluxIcons\Variants\Fill;
use Ympact\FluxIcons\Variants\Stroke;
use Ympact\FluxIcons\Variants\Template;
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

/**
 * The components in a directory by file name, with the build time masked.
 *
 * @return array<string, string>
 */
function componentsIn(string $directory): array
{
    return collect(File::files($directory))
        ->mapWithKeys(fn (SplFileInfo $file) => [
            $file->getFilename() => (string) preg_replace('/^(\s*Built:).*$/m', '$1', (string) file_get_contents($file->getPathname())),
        ])
        ->sortKeys()
        ->all();
}

/**
 * The <svg> markup of each variant case in a built component, by variant.
 *
 * @return array<string, string>
 */
function slotsIn(string $component): array
{
    preg_match_all("/case \\('(\\w+)'\\): \\?>\\s*(.*?)\\s*<\\?php break; \\?>/s", (string) file_get_contents($component), $slots);

    return array_combine($slots[1], $slots[2]);
}

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

    $slots = slotsIn("{$this->output}/alpha/dots.blade.php");

    expect($slots['outline'])->toContain('stroke-linecap="round"')
        ->and($slots['solid'])->toBe($slots['outline'])
        ->and($slots['micro'])->toBe($slots['outline']);
});

/**
 * Alpha with a duotone variant, its foreground layer listed before the background it must not take,
 * and the background drawn at another opacity than the one it is matched on.
 */
function alphaWithDuotone(?Fallback $fallback = null): Alpha
{
    return new class($fallback) extends Alpha
    {
        public function __construct(protected ?Fallback $duotoneFallback) {}

        public function key(): string
        {
            return 'alpha';
        }

        public function variants(): VariantSet
        {
            return parent::variants()->variant('duotone', function (Variant $variant) {
                $variant->template(Template::Solid)
                    ->source(Source::dir('duotone'))
                    ->fill(new Fill('foreground'))
                    ->fill(new Fill('background', matchOpacity: 0.2, opacity: 0.4));

                if ($this->duotoneFallback !== null) {
                    $variant->fallback($this->duotoneFallback);
                }
            });
        }
    };
}

it('builds a vendor variant and paints each of its fill layers', function () {
    $this->builder->build(alphaWithDuotone(), ['home']);

    $slots = slotsIn("{$this->output}/alpha/home.blade.php");

    expect(array_keys($slots))->toBe(['outline', 'solid', 'mini', 'micro', 'duotone'])
        ->and($slots['duotone'])->toContain('<path d="M4 4h16v16H4z" opacity="0.4" fill="currentColor"></path>')
        ->and($slots['duotone'])->toContain('<path d="M2 2h20v20H2z" fill="currentColor"></path>');
});

it('leaves a vendor variant out of an icon that has no file for it, and builds the icon', function () {
    $result = $this->builder->build(alphaWithDuotone(), ['arrow-left']);

    expect($result->built)->toBe(['arrow-left'])
        ->and(array_keys(slotsIn("{$this->output}/alpha/arrow-left.blade.php")))->toBe(['outline', 'solid', 'mini', 'micro'])
        ->and((string) file_get_contents("{$this->output}/alpha/arrow-left.blade.php"))->toContain('Variants: outline, solid, mini, micro ');
});

it('draws a vendor variant without a file as the variant it falls back to', function () {
    $this->builder->build(alphaWithDuotone(Fallback::defaultVariant()), ['arrow-left']);

    $slots = slotsIn("{$this->output}/alpha/arrow-left.blade.php");

    expect($slots['duotone'])->toBe($slots['outline']);
});

it('lists the icons a vendor ships by name', function () {
    expect(array_keys($this->builder->available(new Epsilon)))->toBe(['arrow-left', 'home']);
});

it('follows a fallback that itself falls back', function () {
    $vendor = new class extends Alpha
    {
        public function key(): string
        {
            return 'alpha';
        }

        public function variants(): VariantSet
        {
            return parent::variants()->variant('mini', fn (Variant $variant) => $variant
                ->source(Source::dir('mini'))
                ->fallback(Fallback::variant('solid'))
            );
        }
    };

    $result = $this->builder->build($vendor, ['dots']);

    expect($result->built)->toBe(['dots']);

    $slots = slotsIn("{$this->output}/alpha/dots.blade.php");

    expect($slots['mini'])->toBe($slots['outline']);
});

it('skips an icon whose fallbacks lead back to a variant already tried', function () {
    $vendor = new class extends Alpha
    {
        public function key(): string
        {
            return 'alpha';
        }

        public function variants(): VariantSet
        {
            return parent::variants()
                ->variant('solid', fn (Variant $variant) => $variant->fallback(Fallback::variant('mini')))
                ->variant('mini', fn (Variant $variant) => $variant
                    ->source(Source::dir('mini'))
                    ->fallback(Fallback::variant('solid'))
                );
        }
    };

    $result = $this->builder->build($vendor, ['dots']);

    expect($result->built)->toBe([])
        ->and($result->skipped)->toBe(['dots' => 'no source for its solid variant']);
});

it('keeps the first icon when two are published under one name', function () {
    $vendor = new class extends Alpha
    {
        public function key(): string
        {
            return 'alpha';
        }

        public function iconName(string $name): string
        {
            return $name === 'dots' ? 'home' : $name;
        }
    };

    $result = $this->builder->build($vendor);

    expect($result->built)->toBe(['arrow-left', 'home'])
        ->and($result->skipped)->toBe(['home' => 'dots is already published as home'])
        ->and((string) file_get_contents("{$this->output}/alpha/home.blade.php"))
        ->toContain('cx="5"')
        ->not->toContain('M3 9.5');
});

it('finds the icons of a package whose path holds glob characters', function () {
    $packages = sys_get_temp_dir().'/flux-icons [packages] '.Str::random(8);
    File::copyDirectory(base_path('tests/vendor/alpha'), "{$packages}/alpha");

    try {
        $result = (new Builder($packages, "{$this->output}/glob"))->build(new Alpha);
    } finally {
        File::deleteDirectory($packages);
    }

    $this->builder->build(new Alpha);

    expect($result->built)->toBe(['arrow-left', 'dots', 'home'])
        ->and(componentsIn("{$this->output}/glob/alpha"))->toBe(componentsIn("{$this->output}/alpha"));
});

it('skips an icon whose source is not valid SVG, and builds the rest', function () {
    $packages = sys_get_temp_dir().'/flux-icons-invalid-'.Str::random(8);
    File::copyDirectory(base_path('tests/vendor/alpha'), "{$packages}/alpha");
    File::put("{$packages}/alpha/icons/outline/dots.svg", '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"></svg>');

    try {
        $result = (new Builder($packages, $this->output))->build(new Alpha);
    } finally {
        File::deleteDirectory($packages);
    }

    expect($result->built)->toBe(['arrow-left', 'home'])
        ->and(array_keys($result->skipped))->toBe(['dots'])
        ->and($result->skipped['dots'])->toStartWith('dots is not a valid SVG: ')
        ->and(File::exists("{$this->output}/alpha/dots.blade.php"))->toBeFalse();
});

it('refuses a vendor whose package is not installed, rather than building nothing', function () {
    $vendor = new class extends Alpha
    {
        public function package(): string
        {
            return 'not-installed';
        }
    };

    $this->builder->build($vendor);
})->throws(RuntimeException::class, 'The npm package not-installed is not installed');
