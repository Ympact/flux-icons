<?php

// class that is a representation of an icon using DomDocument and XPath
namespace Ympact\FluxIcons\Types;

use DOMDocument;
use DOMXPath;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;


class Icon{

    protected string $filename;

    protected string $basename;

    protected DOMDocument $dom;

    protected DOMXPath $xpath;

    protected Collection $paths;

    protected string $rawContent;

    protected int $size;

    protected float $strokeWidth = 1.5;

    protected string|null $file = null;

    protected array $config;

    public function __construct($config, $filename = null)
    {
        $this->config = $config;
        if($filename){
            $this->setFile($filename);
        }
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
     * Summary of process
     * @return Icon
     */
    public function process(): static
    {
        $this->parseDom();
        if($this->dom){
            if($this->xpath){
                $this->determineIconSize();
                $this->extractTags();
            }
        }
        return $this;
    }

    /**
     * Summary of determineBaseName
     * @param mixed $variant
     * @return Icon
     */
    public function determineBaseName($variant = 'outline'): static
    {
        $baseIconName = $this->filename;
        
        if ($prefix = Arr::get($this->config, "source_directories.{$variant}.prefix")) {
            //if string contains $prefix, remove it
            if(Str::contains($baseIconName, $prefix, true)){
                $baseIconName = Str::after($baseIconName, $prefix );
            }
        }
        if ($suffix = Arr::get($this->config, "source_directories.{$variant}.suffix")) {
            //if string contains $suffix, remove it
            if(Str::contains($baseIconName, $suffix)){
                $baseIconName = Str::before($baseIconName, $suffix);
            }
        }
        $this->basename = $baseIconName;

        return $this;
    }

    /**
     * transform icon and return new instance of the transformed icon
     * we merge paths and execute the transformation as set in the config
     * @param string $variant
     * @return Icon
     */
    public function transform($variant = 'outline'): static
    {
        if($callback = Arr::get($this->config, "transform_svg_path")){
            $this->paths = call_user_func_array($callback, [$variant, $this->basename, $this->paths]);
        }

        return $this;
    }

    /**
     * change the stroke width of the icon
     * @param float|null $default
     * @return Icon
     */
    public function setStrokeWidth(float $default = null): static
    {
        $this->strokeWidth = $default ?? $this->strokeWidth;

        // if there is a change_stroke_width function in the config file, apply it
        if($callback = Arr::get($this->config, "change_stroke_width")){
            $this->strokeWidth = call_user_func_array($callback, [$this->basename, $this->strokeWidth , $this->paths]);
        }

        $this->strokeWidth = round($this->strokeWidth, 2);

        return $this;
    }

    /**
     * make sure the paths have the correct attributes
     * @param string $variant
     * @return Icon
     */
    public function setAttributes($variant = 'outline'): static
    {
        $attributes = Arr::get($this->config, "path_attributes.{$variant}");
        $this->paths = $this->paths->map(function(SvgPath $path) use ($attributes){
            return $path->setAttributes($attributes);
        });

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
     * get the final html paths
     * @return string
     */
    public function toHtml(): string
    {
        return $this->paths->map(function(SvgPath $path){
            return $path->toHtml();
        })->implode('');
    }

    /**
     * get filename of the icon
     */
    public function getName(){
        return $this->filename;
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
    public function getStrokeWidth(){
        return $this->strokeWidth;
    }

    /**
     * Summary of exists
     * @return bool
     */
    public function exists(){
        return $this->rawContent ? true : false;
    }

    /**
     * Summary of fileExists
     * @return bool
     */
    public function fileExists(){
        return $this->file ? true : false;
    }

    /**
     * Summary of parseDom
     * @return void
     */
    protected function parseDom(){
        $this->dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $this->dom->loadHTML($this->rawContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($this->dom);
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
     * determine the size of the original icon
     * @return void
     */
    private function determineIconSize()
    {
        $svg = $this->xpath->query('//svg')->item(0);
        // first try using viewBox attribute, otherwise use height
        if($svg->hasAttribute('viewBox')){
            $viewBox = $svg->getAttribute('viewBox');
            $viewBox = explode(' ', $viewBox);
            if(count($viewBox) == 4){
                // convert string $viewBox[3] to int
                $this->size = (int)$viewBox[3];
                return;
            }
        }

        $height = $svg->getAttribute('height');
        $this->size = (int)$height;
    }

    /**
     * extract all paths from the SVG as SvgPath instances
     * @return void
     */
    private function extractTags()
    {
        $this->paths = $this->paths ?? new Collection();
        $tags = $this->getPathDefinitions();

        foreach ($tags as $tag) {
            $this->paths->push(new SvgPath($tag));
        }
    }

    /**
     * get all path definitions from the svg
     * @return mixed
     */
    private function getPathDefinitions()
    {
        $tags = $this->xpath->query("//path | //circle | //rect | //line | //polyline | //polygon");
        return $tags;
    }

}