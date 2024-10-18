<?php

// class that is a representation of an icon using DomDocument and XPath
namespace Ympact\FluxIcons\DataTypes;

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

    protected string|null $mergedD = null;

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


    public function setFile($file):static
    {
        $this->readFile($file);
        return $this;
    }

    public function setContent($content, $filename): static
    {
        $this->readRawContent($content, $filename);
        return $this;
    }

    public function process(): static
    {
        $this->parseDom();
        if($this->dom){
            if($this->xpath){
                $this->paths = new Collection();
                $this->getIconSize();
                $this->extractTags();
            }
        }
        return $this;
    }

    public function determineBaseName($variant = 'outline'): static
    {
        //$this->filename = pathinfo($this->file, PATHINFO_FILENAME);
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

    // transform icon and return new instance of the transformed icon
    // we merge paths and execute the transformation as set in the config
    public function transform($variant = 'outline'): static
    {
        if($transform = Arr::get($this->config, "transform_svg_path")){
            $this->paths = $transform($variant, $this->basename, $this->paths);
        }

        return $this;
    }

    // change the stroke width of the outline icon
    public function changeStrokeWidth(float $default = null): static
    {
        $this->strokeWidth = $default ?? $this->strokeWidth;

        // if there is a change_stroke_width function in the config file, apply it
        if($changeStrokeWidth = Arr::get($this->config, "change_stroke_width")){
            $this->strokeWidth = $changeStrokeWidth($this->basename, $this->strokeWidth , $this->paths);
        }

        $this->strokeWidth = round($this->strokeWidth, 2);

        return $this;
    }

    public function merge(): static  {
        $this->mergedD = $this->getD()->implode(' ');
        return $this;
    }

    public function getD(): Collection{
        return $this->paths->map(function(SvgPath $path){
            return $path->getD();
        });
    }

    public function getMergedD(){
        if(!$this->mergedD){
            $this->merge();
        }
        return $this->mergedD;
    }

    // get name of the icon
    public function getName(){
        return $this->filename;
    }

    public function getBaseName(){
        return $this->basename;
    }

    // get the size of the icon
    public function getSize(){
        return $this->size;
    }

    // get the stroke width of the icon
    public function getStrokeWidth(){
        return $this->strokeWidth;
    }

    public function exists(){
        return $this->rawContent ? true : false;
    }

    public function fileExists(){
        return $this->file ? true : false;
    }

    protected function parseDom(){
        $this->dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $this->dom->loadHTML($this->rawContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $this->xpath = new DOMXPath($this->dom);
    }

    protected function readFile($file)
    {
        if(File::exists($file)){
            $this->file = $file;
            $this->filename = pathinfo($file, PATHINFO_FILENAME);
            $this->rawContent = File::get($this->file);
            $this->determineBaseName();
        }
    }

    protected function readRawContent($content, $filename)
    {      
        $this->filename = $filename;
        $this->rawContent = $content;
        $this->determineBaseName();
    }


    protected function getIconSize(){
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

    protected function extractTags(){
        // path, circle, rect, tags
        $tags = $this->getPathDefinitions();

        foreach ($tags as $tag) {
            $this->paths->push(new SvgPath($tag));
        }
    }

    protected function getPathDefinitions()
    {
        $tags = $this->xpath->query("//path | //circle | //rect | //line | //polyline | //polygon");
        return $tags;
    }

}