<?php

// class that is a representation of an icon using DomDocument and XPath
namespace Ympact\FluxIcons\Types;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use function Ympact\FluxIcons\arrayMergeRecursive;

class Icon{

    protected string $filename;

    protected ?string $basename = null;

    protected DOMDocument $dom;

    protected DOMXPath $xpath;

    protected DOMDocument $domSource;

    protected DOMXPath $xpathSource;

    protected Collection $paths;

    protected string $rawContent;

    protected int $size;

    protected float $strokeWidth = 1.5;

    protected string|null $file = null;

    protected string $template = 'outline';

    protected array $variantAttributes = [
        "default" => [
            "data-flux-icon" => true,
            "xmlns" => "http://www.w3.org/2000/svg",
            "aria-hidden" => "true",
            "data-slot" => "icon"
        ],
        "outline" => [
            "fill" => "none", 
            "stroke" => "currentColor",
            "stroke-width" => [ self::class, 'getStrokeWidth'],
        ],
        "solid" => [
            "fill" => "currentColor"
        ]
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

    public function getVariant(){
        return $this->variant;
    }

    /**
     * Summary of setFile
     * @param mixed $file
     * @return Icon
     */
    public function setFile($file): static
    {
        $this->readFile($file);
        return $this;
    }

    /**
     * Summary of setContent
     * @param string $content
     * @param string $filename
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
     * get template
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Summary of process
     * @return Icon
     */
    public function process(): static
    {
        $this->domSource = $this->parseDom();
        if($this->domSource){
            if($this->xpathSource){
                $this->determineIconSize();
                $this->extractTags();
                $this->determineSvgAttributes();
            }
        }
        return $this;
    }

    /**
     * Summary of determineBaseName
     * @return Icon
     */
    public function determineBaseName($force = false): static
    {
        if(!$this->basename || $force){
            $baseIconName = $this->filename;
            if ($prefix = Arr::get($this->config, "variants.{$this->variant}.source.prefix")) {
                //if string contains $prefix, remove it
                if(is_array($prefix) && is_callable($prefix)){
                    // we have a callable
                    $prefix = call_user_func_array($prefix, [$this->variant]);
                }
                if(Str::contains($baseIconName, $prefix, true)){
                    $baseIconName = Str::after($baseIconName, $prefix );
                }
            }
            if ($suffix = Arr::get($this->config, "variants.{$this->variant}.source.suffix")) {
                if(is_array($suffix) && is_callable($suffix)){
                    // we have a callable
                    $suffix = call_user_func_array($suffix, [$this->variant]);
                }
                //if string contains $suffix, remove it
                if(Str::contains($baseIconName, $suffix)){
                    $baseIconName = Str::before($baseIconName, $suffix);
                }
            }

            $this->basename = $baseIconName;
        }
        return $this;
    }


    /**
     * Determine what template (svg attributes) to use for the icon
     * @return void
     */
    private function determineTemplate(): static
    {
        $this->template = Arr::get($this->config, "variants.{$this->variant}.template");
        return $this;
    }

    public function setDefaultAttributes($attributes): static
    {
        $this->variantAttributes = arrayMergeRecursive($this->variantAttributes, $attributes);
        return $this;
    }

    public function getDefaultAttributes(string $variant = null): array
    {
        if($variant){
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
     * @return Icon
     */
    public function transform(): static
    {
        if($callback = Arr::get($this->config, 'transform')){
            $this->paths = call_user_func_array($callback, [$this->paths, $this]);
        }

        return $this;
    }

    public function getPaths(): Collection
    {
        return $this->paths;
    }


    /**
     * change the stroke width of the icon
     * @param float|null $default
     * @param bool $force force the passed argument and ignore the config
     * @return Icon
     */
    public function setStrokeWidth(float $default = null, bool $force = false): static
    {
        $this->strokeWidth = $default ?? $this->strokeWidth;
        // if there is a strokeWidth function in the config file, apply it
        if(!$force && $callback = Arr::get($this->config, "stroke_width")){
            $this->strokeWidth = call_user_func_array($callback, [$this]);
        }

        $this->strokeWidth = round($this->strokeWidth, 2);

        return $this;
    }

    /**
     * make sure the paths have the correct attributes
     * @param array $attributes
     * @return Icon
     */
    public function setPathAttributes(array $attributes = null): static
    {
        $attributes = $attributes ?? Arr::get($this->config, "path_attributes.{$this->variant}");
        if($attributes){
            $this->paths = $this->paths->map(function(SvgPath $path) use ($attributes){
                return $path->setAttributes($attributes);
            });
        }

        return $this;
    }


    /**
     * Summary of getD
     * @return \Illuminate\Support\Collection
     */
    public function getD(): Collection{
        return $this->paths->map(function(SvgPath $path){
            return $path->getD();
        });
    }

    
    /**
     * get the final svg as a string
     * @return string
     */
    public function toHtml(): string
    {
        $this->createSvg();
        $svg = $this->dom->getElementsByTagName('svg')->item(0);
        $svg->setAttribute('viewBox', "0 0 {$this->size} {$this->size}");

        $svgPathNodes = $this->paths->map(function(SvgPath $path){
            return $path->getNode();
        });

        // insert the svgPaths nodes into the svg node
        foreach ($svgPathNodes as $node) {
            $importedNode = $this->dom->importNode($node, true);
            $svg->appendChild($importedNode);
        }

        return $this->dom->saveHTML();
    }

    /**
     * get filename of the icon
     */
    public function getName(){
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
     * @return string
     */
    public function getBaseName(){
        return $this->basename;
    }

    /**
     * Summary of getSize
     * @return int
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
     * @return bool

    public function exists(){
        return $this->rawContent ? true : false;
    }
     */

    /**
     * Summary of fileExists
     * @return bool

    public function fileExists(){
        return $this->file ? true : false;
    }
     */

    /**
     * Summary of parseDom
     * @return DOMDocument
     */
    protected function parseDom(): DOMDocument
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML($this->rawContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $this->xpathSource = new DOMXPath($dom);

        return $dom;
    }

    /**
     * Summary of readFile
     * @param mixed $file
     * @return void
     */
    private function readFile($file)
    {
        if(File::exists($file)){
            $this->file = $file;
            $this->filename = pathinfo($file, PATHINFO_FILENAME);
            $this->rawContent = File::get($this->file);
            $this->determineBaseName();
        }
    }

    /**
     * Summary of readRawContent
     * @param mixed $content
     * @param mixed $iconName
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
        $this->dom = new DOMDocument();
        $this->dom->loadXML('<svg></svg>'); 

        $svg = $this->dom->getElementsByTagName('svg')->item(0);
        foreach($this->attributes as $key => $value){
            // if value is an array, call the function
            if(is_array($value) && is_callable($value)){
                $value = call_user_func_array($value, [$this]);
            }
            $svg->setAttribute($key, $value);
            
        }
    }

    public function determineSvgAttributes():void{
        $attributes = [];
        $attributes = Arr::get($this->config, "variants.{$this->variant}.attributes", []);
        if($callback = Arr::get($this->config, 'attributes')){
            $attributes = arrayMergeRecursive($attributes, call_user_func_array($callback, [$this]));
        }

        $this->attributes = arrayMergeRecursive(
            $this->variantAttributes['default'],
            $this->variantAttributes[$this->template], 
            $attributes
        );
    }

    /**
     * determine the size of the original icon
     * @return void
     */
    private function determineIconSize()
    {
        $svg = $this->xpathSource->query('//svg')->item(0);
        // first try using viewBox attribute, otherwise use height
        if($svg->hasAttribute('viewbox')){
            $viewBox = $svg->getAttribute('viewbox');
            $viewBox = explode(' ', $viewBox);
            if(count($viewBox) == 4){
                // convert string $viewBox[3] to in
                $this->size = (int)$viewBox[3];
                return;
            }
        }

        $size = $svg->getAttribute('height') ?? $svg->getAttribute('width');
        $this->size = (int)$size;
    }

    /**
     * extract all paths from the SVG as SvgPath instances
     * @return void
     */
    private function extractTags()
    {
        $this->paths = $this->paths ?? new Collection();
        $tags = $this->xpathSource->query($this->getPathDefinitions());

        foreach ($tags as $tag) {
            $this->paths->push(new SvgPath($tag));
        }
    }

    /**
     * get all path definitions from the svg
     * @return string
     */
    private function getPathDefinitions()
    {
        return "//path | //circle | //rect | //line | //polyline | //polygon";
    }



}