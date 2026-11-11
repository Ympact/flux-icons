<?php

// class that is a representation of an icon using DomDocument and XPath

namespace Ympact\FluxIcons\Types;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Ympact\FluxIcons\arrayMergeRecursive;

class Icon
{
    protected string $filename;

    protected ?string $basename = null;

    protected DOMDocument $dom;

    protected DOMXPath $xpath;

    protected DOMDocument $domSource;

    protected DOMXPath $xpathSource;

    protected Collection $paths;

    protected string $rawContent;

    /**
     * The size of the icon
     *
     * @var array<int>
     */
    protected array $size;

    /**
     * xy origin of the icon for custom viewBox positioning
     *
     * @var array<int>
     */
    protected array $origin = [0, 0];

    protected float $strokeWidth = 1.5;

    protected ?string $file = null;

    protected string $template = 'outline';

    protected bool $raw = false;

    protected array $variantAttributes = [
        'default' => [
            'data-flux-icon' => true,
            'xmlns' => 'http://www.w3.org/2000/svg',
            'aria-hidden' => 'true',
            'data-slot' => 'icon',
        ],
        'outline' => [
            'fill' => 'none',
            'stroke' => 'currentColor',
            'stroke-width' => [self::class, 'getStrokeWidth'],
        ],
        'solid' => [
            'fill' => 'currentColor',
        ],
    ];

    protected array $svgAttributes = [];

    protected array $config;

    protected string $variant;

    protected array $attributes = [];

    public function __construct($config, $variant, $filename)
    {
        $this->config = $config;
        $this->variant = $variant;
        $this->determineTemplate();
        $this->setFile($filename);
    }

    /**
     * An icon read for the vendor-class build, which applies templates, strokes and
     * attributes itself rather than reading them from a v1 config array.
     */
    public static function fromFile(string $file, string $variant, string $template, string $name): self
    {
        $icon = new self([], $variant, $file);

        return $icon->setTemplate($template)->setBaseName($name);
    }

    public function getVariant()
    {
        return $this->variant;
    }

    /**
     * Summary of setFile
     *
     * @param  mixed  $file
     * @return Icon
     */
    public function setFile($file): static
    {
        $this->readFile($file);

        return $this;
    }

    /**
     * Summary of setContent
     *
     * @param  string  $filename
     * @return Icon
     */
    public function setContent(string $content, string $iconName): static
    {
        $this->readRawContent($content, $iconName);

        return $this;
    }

    /**
     * set template
     */
    public function setTemplate(string $template): static
    {
        $this->template = $template;

        return $this;
    }

    /**
     * set raw
     */
    public function setRaw(bool $raw = true): static
    {
        $this->raw = $raw;

        return $this;
    }

    /**
     * get template
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Summary of process
     *
     * @return Icon
     */
    public function process(): static
    {
        $this->domSource = $this->parseDom();
        if ($this->domSource) {
            if ($this->xpathSource) {
                $this->determineIconSize();
                $this->extractTags();
                $this->determineSvgAttributes();
            }
        }

        return $this;
    }

    /**
     * Summary of determineBaseName
     *
     * @return Icon
     */
    public function determineBaseName($force = false): static
    {
        if (! $this->basename || $force) {
            $baseIconName = $this->filename;
            if ($prefix = Arr::get($this->config, "variants.{$this->variant}.source.prefix")) {
                // if string contains $prefix, remove it
                if (is_array($prefix) && is_callable($prefix)) {
                    // we have a callable
                    $prefix = call_user_func_array($prefix, [$this->variant]);
                }
                if (Str::contains($baseIconName, $prefix, true)) {
                    $baseIconName = Str::after($baseIconName, $prefix);
                }
            }
            if ($suffix = Arr::get($this->config, "variants.{$this->variant}.source.suffix")) {
                if (is_array($suffix) && is_callable($suffix)) {
                    // we have a callable
                    $suffix = call_user_func_array($suffix, [$this->variant]);
                }
                // if string contains $suffix, remove it
                if (Str::contains($baseIconName, $suffix)) {
                    $baseIconName = Str::before($baseIconName, $suffix);
                }
            }

            $this->basename = $baseIconName;
        }

        return $this;
    }

    /**
     * Determine what template (svg attributes) to use for the icon
     *
     * @return void
     */
    private function determineTemplate(): static
    {
        $this->template = Arr::get($this->config, "variants.{$this->variant}.template", $this->template);

        return $this;
    }

    public function setDefaultAttributes($attributes): static
    {
        $this->variantAttributes = arrayMergeRecursive($this->variantAttributes, $attributes);

        return $this;
    }

    public function getDefaultAttributes(?string $variant = null): array
    {
        if ($variant) {
            return array_key_exists($variant, $this->variantAttributes) ? $this->variantAttributes[$variant] : [];
        }

        return $this->variantAttributes;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * transform icon and return new instance of the transformed icon
     * we merge paths and execute the transformation as set in the config
     *
     * @return Icon
     */
    public function transform(): static
    {
        if ($callback = Arr::get($this->config, 'transform')) {
            $this->paths = call_user_func_array($callback, [$this->paths, $this]);
        }

        return $this;
    }

    public function getPaths(): Collection
    {
        return $this->paths;
    }

    /**
     * @param  Collection<int, SvgPath>  $paths
     */
    public function setPaths(Collection $paths): static
    {
        $this->paths = $paths;

        return $this;
    }

    /**
     * Replace the attributes the <svg> element is written with.
     *
     * @param  array<string, string>  $attributes
     */
    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * The attributes on the <svg> element of the source file, which carry how the vendor
     * means the icon to be drawn. Available once the icon is processed.
     *
     * @return array<string, string>
     */
    public function getSourceAttributes(): array
    {
        $svg = $this->sourceSvg();
        $attributes = [];

        foreach ($svg instanceof DOMElement ? $svg->attributes : [] as $attribute) {
            $attributes[$attribute->nodeName] = (string) $attribute->nodeValue;
        }

        return $attributes;
    }

    /**
     * change the stroke width of the icon
     *
     * @param  bool  $force  force the passed argument and ignore the config
     * @return Icon
     */
    public function setStrokeWidth(?float $default = null, bool $force = false): static
    {
        $this->strokeWidth = $default ?? $this->strokeWidth;
        // if there is a strokeWidth function in the config file, apply it
        if (! $force && $callback = Arr::get($this->config, 'stroke_width')) {
            $this->strokeWidth = call_user_func_array($callback, [$this]);
        }

        $this->strokeWidth = round($this->strokeWidth, 2);

        return $this;
    }

    /**
     * make sure the paths have the correct attributes
     *
     * @return Icon
     */
    public function setPathAttributes(?array $attributes = null): static
    {
        $attributes = $attributes ?? Arr::get($this->config, "path_attributes.{$this->variant}");
        if ($attributes) {
            $this->paths = $this->paths->map(function (SvgPath $path) use ($attributes) {
                return $path->setAttributes($attributes);
            });
        }

        return $this;
    }

    /**
     * Summary of getD
     */
    public function getD(): Collection
    {
        return $this->paths->map(function (SvgPath $path) {
            return $path->getD();
        });
    }

    /**
     * get the final svg as a string
     */
    public function toHtml(): string
    {
        $this->createSvg();
        $svg = $this->dom->getElementsByTagName('svg')->item(0);
        $svg->setAttribute('viewBox', "{$this->origin[0]} {$this->origin[1]} {$this->size[0]} {$this->size[1]}");

        // a raw icon keeps the source's own <svg> intact, nested inside the component's
        if ($this->raw) {
            $svg->appendChild($this->dom->importNode($this->sourceSvg(), true));
        } else {
            $svgPathNodes = $this->paths->map(function (SvgPath $path) {
                return $path->getNode();
            });

            // insert the svgPaths nodes into the svg node
            foreach ($svgPathNodes as $node) {
                $importedNode = $this->dom->importNode($node, true);
                $svg->appendChild($importedNode);
            }
        }

        return $this->dom->saveHTML();
    }

    /**
     * get filename of the icon
     */
    public function getName()
    {
        return $this->filename;
    }

    /**
     * set basename
     */
    public function setBaseName(string $basename): static
    {
        $this->basename = $basename;

        return $this;
    }

    /**
     * Summary of getBaseName
     *
     * @return string|null
     */
    public function getBaseName()
    {
        return $this->basename;
    }

    /**
     * Summary of getSize
     *
     * @return array
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * get the stroke width of the icon
     */
    public function getStrokeWidth()
    {
        return $this->strokeWidth;
    }

    /**
     * Summary of exists
     *
     * @return bool
    public function exists(){
        return $this->rawContent ? true : false;
    }
     */

    /**
     * Summary of fileExists
     *
     * @return bool
    public function fileExists(){
        return $this->file ? true : false;
    }
     */

    /**
     * Parse the source as XML, which keeps the case of names such as viewBox and clipPath.
     *
     * @throws InvalidSvg When the source is not an XML document with an <svg> root, or declares an
     *                    external entity.
     */
    protected function parseDom(): DOMDocument
    {
        // anything before an XML declaration makes the file invalid, and a stray newline is common
        $content = ltrim($this->rawContent);
        $dom = $this->loadXml($content);
        $entities = (string) $dom->doctype?->internalSubset;

        // expanding one would read a file or URL named by the source, so it is never read
        if (preg_match('/<!ENTITY\s+(?:%\s+)?\S+\s+(?:SYSTEM|PUBLIC)\b/i', $entities)) {
            throw new InvalidSvg("{$this->filename} is not a valid SVG: it declares an external entity, which is not read");
        }

        // an Illustrator export declares the SVG namespace as an entity; expanding the entities a
        // file defines itself reads nothing from outside it
        if (str_contains($entities, '<!ENTITY')) {
            $dom = $this->loadXml($content, LIBXML_NOENT | LIBXML_NONET);
        }

        if ($dom->documentElement?->localName !== 'svg') {
            throw new InvalidSvg("{$this->filename} is not a valid SVG: it has no <svg> root");
        }

        $this->xpathSource = new DOMXPath($dom);

        return $dom;
    }

    /**
     * @throws InvalidSvg With the first error the parser met, which is usually the cause.
     */
    private function loadXml(string $content, int $options = 0): DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = $content !== '' && $dom->loadXML($content, $options);
        $error = libxml_get_errors()[0] ?? null;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $reason = $error !== null ? trim($error->message) : 'the file is empty';

            throw new InvalidSvg("{$this->filename} is not a valid SVG: {$reason}");
        }

        return $dom;
    }

    /**
     * The root <svg> element of the source, with or without the SVG namespace declared.
     */
    protected function sourceSvg(): ?DOMElement
    {
        $svg = $this->xpathSource->query("//*[local-name()='svg']")->item(0);

        return $svg instanceof DOMElement ? $svg : null;
    }

    /**
     * Summary of readFile
     *
     * @param  mixed  $file
     * @return void
     */
    private function readFile($file)
    {
        if (File::exists($file)) {
            $this->file = $file;
            $this->filename = pathinfo($file, PATHINFO_FILENAME);
            $this->rawContent = File::get($this->file);
            $this->determineBaseName();
        }
    }

    /**
     * Summary of readRawContent
     *
     * @param  mixed  $content
     * @param  mixed  $iconName
     * @return void
     */
    private function readRawContent(string $content, string $iconName)
    {
        $this->filename = $iconName;
        $this->rawContent = $content;
        $this->determineBaseName();
    }

    /**
     * Create new SVG dom
     */
    private function createSvg()
    {
        $this->dom = new DOMDocument;
        $this->dom->loadXML('<svg></svg>');

        $svg = $this->dom->getElementsByTagName('svg')->item(0);
        foreach ($this->attributes as $key => $value) {
            // if value is an array, call the function
            if (is_array($value) && is_callable($value)) {
                $value = call_user_func_array($value, [$this]);
            }
            $svg->setAttribute($key, $value);

        }
    }

    public function determineSvgAttributes(): void
    {
        $attributes = [];
        $attributes = Arr::get($this->config, "variants.{$this->variant}.attributes", []);
        if ($callback = Arr::get($this->config, 'attributes')) {
            $attributes = arrayMergeRecursive($attributes, call_user_func_array($callback, [$this]));
        }

        $this->attributes = arrayMergeRecursive(
            $this->variantAttributes['default'],
            $this->variantAttributes[$this->template] ?? [],
            $attributes
        );
    }

    /**
     * determine the size of the original icon
     *
     * @return void
     */
    private function determineIconSize()
    {
        $svg = $this->sourceSvg();
        // first try using viewBox attribute, otherwise use height
        if ($svg->hasAttribute('viewBox')) {
            $viewBox = $svg->getAttribute('viewBox');
            $viewBox = explode(' ', $viewBox);
            if (count($viewBox) == 4) {

                $this->origin = [(int) $viewBox[0], (int) $viewBox[1]];

                $this->size = [(int) $viewBox[2], (int) $viewBox[3]];

                return;
            }
        }

        // getAttribute() returns an empty string for missing attributes, so fall back on
        // the other dimension only when the requested one is actually absent.
        $height = $svg->getAttribute('height') ?: $svg->getAttribute('width');
        $width = $svg->getAttribute('width') ?: $svg->getAttribute('height');
        $this->size = [(int) $width, (int) $height];
    }

    /**
     * extract all paths from the SVG as SvgPath instances
     *
     * @return void
     */
    private function extractTags()
    {
        $this->paths = $this->paths ?? new Collection;
        $tags = $this->xpathSource->query($this->getPathDefinitions());

        foreach ($tags as $tag) {
            $this->paths->push(new SvgPath($tag));
        }
    }

    /**
     * The elements the icon is drawn from: the root's own children, so a group carries
     * its shapes along rather than having them copied out of it a second time.
     *
     * @return string
     */
    private function getPathDefinitions()
    {
        $names = ['path', 'g', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'use', 'defs'];

        return '/*/*['.implode(' or ', array_map(fn (string $name) => "local-name()='{$name}'", $names)).']';
    }
}
