<?php

use Flux\FluxServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Blaze\BlazeServiceProvider;
use Livewire\LivewireServiceProvider;
use Ympact\FluxIcons\Build\ComponentWriter;
use Ympact\FluxIcons\Variants\Variant;
use Ympact\FluxIcons\Variants\VariantSet;

use function Orchestra\Testbench\default_skeleton_path;

beforeEach(function () {
    foreach ([LivewireServiceProvider::class, FluxServiceProvider::class, BlazeServiceProvider::class] as $provider) {
        $this->app->register($provider);
    }

    // Blade resolves an <x-...> tag against the application's namespace, which the package root lacks
    $this->app->setBasePath(default_skeleton_path());

    $this->directory = sys_get_temp_dir().'/flux-icons-render-'.Str::random(8);
    $this->prefix = 'icons'.Str::lower(Str::random(8));
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

/**
 * Write a component with one path per variant, its d naming the variant, and register it.
 *
 * @param  array<int, string>  $built
 */
function writeRenderable(object $test, array $built, string $default = 'outline', bool $blaze = false): void
{
    $variants = VariantSet::make();
    $markup = [];

    foreach ($built as $name) {
        $variants->variant($name, fn (Variant $variant) => $variant->default($name === $default));
        $markup[$name] = "<svg viewBox=\"0 0 24 24\"><path d=\"{$name}\"></path></svg>";
    }

    (new ComponentWriter)->write($test->directory, 'probe', 'icon', $variants->resolve(), $markup, '', '', $blaze);
    Blade::anonymousComponentPath($test->directory, $test->prefix);
}

/**
 * @param  array<string, mixed>  $data
 */
function renderIcon(object $test, string $attributes = '', array $data = []): string
{
    // compiled views are keyed by their template, so a cached one could point at another test's component
    return trim(Blade::render("<x-{$test->prefix}::icon {$attributes} />", $data, deleteCachedView: true));
}

it('draws the variant asked for, a vendor variant included', function (bool $blaze) {
    writeRenderable($this, ['outline', 'solid', 'mini', 'micro', 'duotone'], blaze: $blaze);

    expect(renderIcon($this, 'variant="duotone"'))->toContain('<path d="duotone">')->not->toContain('data-flux-icon-fallback')
        ->and(renderIcon($this, 'variant="mini"'))->toContain('<path d="mini">')->toContain('size-5');
})->with(['plain' => false, 'blaze' => true]);

it('draws the vendor default variant when none is asked for', function (bool $blaze) {
    writeRenderable($this, ['outline', 'solid', 'mini', 'micro'], default: 'solid', blaze: $blaze);

    expect(renderIcon($this))->toContain('<path d="solid">')->not->toContain('data-flux-icon-fallback');
})->with(['plain' => false, 'blaze' => true]);

it('draws the default variant for a variant it was not built with, and marks it', function (bool $blaze) {
    writeRenderable($this, ['outline', 'solid', 'mini', 'micro'], blaze: $blaze);

    expect(renderIcon($this, 'variant="duotone"'))
        ->toContain('<path d="outline">')
        ->toContain('data-flux-icon-fallback="duotone"')
        ->toContain('size-6');
})->with(['plain' => false, 'blaze' => true]);

it('escapes the variant it marks', function (bool $blaze) {
    writeRenderable($this, ['outline', 'solid', 'mini', 'micro'], blaze: $blaze);

    expect(renderIcon($this, ':variant="$variant"', ['variant' => '"><script>']))
        ->toContain('data-flux-icon-fallback="&quot;&gt;&lt;script&gt;"')
        ->not->toContain('<script>');
})->with(['plain' => false, 'blaze' => true]);

// Font Awesome's licence asks that the attribution comment in its icons is kept
it('keeps a vendor\'s attribution comment in the page', function (bool $blaze) {
    $variants = VariantSet::make()->variant('solid', fn (Variant $variant) => $variant->default());
    $markup = ['solid' => '<svg viewBox="0 0 640 640"><!--! Font Awesome Free 7.3.1 by @fontawesome --><path d="M1 0h1"></path></svg>'];

    (new ComponentWriter)->write($this->directory, 'probe', 'icon', $variants->resolve(), $markup, '', '', $blaze);
    Blade::anonymousComponentPath($this->directory, $this->prefix);

    expect(renderIcon($this))->toContain('<!--! Font Awesome Free 7.3.1 by @fontawesome -->');
})->with(['plain' => false, 'blaze' => true]);

/**
 * Write a component whose one variant draws the given markup, and render it.
 */
function renderMarkup(object $test, string $markup, bool $blaze, string $credits = ''): string
{
    $variants = VariantSet::make()->variant('solid', fn (Variant $variant) => $variant->default());

    (new ComponentWriter)->write($test->directory, 'probe', 'icon', $variants->resolve(), ['solid' => $markup], $credits, '', $blaze);
    Blade::anonymousComponentPath($test->directory, $test->prefix);

    return renderIcon($test);
}

// a compromised or careless icon package must not run code on every render
it('renders what an icon package wrote as text, never as blade or php', function (bool $blaze) {
    Blade::directive('fontawesome', fn () => '<script src="https://evil.example/x.js"></script>');

    $rendered = renderMarkup($this, '<svg viewBox="0 0 24 24" data-sum="{{ 6*7 }}" data-raw="{!! 6*7 !!}" data-gone="{{--x--}}" data-mail="team@example.com">'
        .'<!--! by @fontawesome <?php echo strrev(\'denwp\'); ?> @php echo strrev(\'denwp\'); @endphp -->'
        .'<g><x-probe-missing /><flux:icon.probe /></g><path d="M1 0h1"></path></svg>', $blaze);

    expect($rendered)->not->toContain('evil.example')
        ->and($rendered)->not->toContain('pwned')
        ->and($rendered)->not->toContain('42')
        ->and($rendered)->toContain('by @fontawesome')
        ->and($rendered)->toContain('data-sum="{&#123; 6*7 }}"')
        ->and($rendered)->toContain('data-raw="{&#33;! 6*7 !!}"')
        ->and($rendered)->toContain('data-gone="{&#123;--x--}}"')
        ->and($rendered)->toContain('data-mail="team@example.com"')
        ->and($rendered)->toContain('&lt;x-probe-missing');
})->with(['plain' => false, 'blaze' => true]);

it('keeps the credits inside the header comment, whatever the package calls itself', function (bool $blaze) {
    $rendered = renderMarkup($this, '<svg viewBox="0 0 24 24"><path d="M1 0h1"></path></svg>', $blaze, credits: 'evil (1.0.0) by --}} @php echo strrev(\'denwp\'); @endphp {{--');

    expect($rendered)->not->toContain('pwned')
        ->and($rendered)->not->toContain('evil');
})->with(['plain' => false, 'blaze' => true]);
