<?php

namespace FluxIcons\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class IconBuilder
{
    protected $vendorConfig;
    
    protected $files;

    public function __construct(protected $vendor, Filesystem $files, protected $icons = null)
    {
        $this->vendorConfig = config("flux-icons.$vendor");
        $this->files = $files;
    }

    public function installPackage()
    {
        $packageName = $this->vendorConfig['package_name'];
        exec("npm install $packageName --save");
    }

    public function buildIcons()
    {
        $output = new ConsoleOutput();


        $sourceDirs = $this->vendorConfig['source_directories'];
        $outputDir = resource_path("views/flux/icon/{$this->vendor}");

        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }
        $outlineFiles = is_string($sourceDirs['outline']) ? $this->files->files(base_path($sourceDirs['outline'])) : $this->files->files(base_path($sourceDirs['outline']['dir']));
        // intersect the outlineFiles with the icons argument if it was passed. The icons argument can be a comma separated list of icon names without the file extension or the prefix/suffix
        if ($this->icons) {
            $icons = explode(',', $this->icons);
            $outlineFiles = array_filter($outlineFiles, function ($file) use ($icons, $sourceDirs) {
                // remove the file extension and the prefix/suffix from the icon name to be ableto compare it with the icons argument
                $iconName = $this->baseIconName(pathinfo($file, PATHINFO_FILENAME),$sourceDirs);
                //$iconName = isset($sourceDirs['outline']['prefix']) ? Str::after($filename, $sourceDirs['outline']['prefix']) : $filename;
                //$iconName = isset($sourceDirs['outline']['suffix']) ? Str::before($iconName, $sourceDirs['outline']['suffix']) : $iconName;

                return in_array($iconName, $icons);
            });
        }

        $progressBar = new ProgressBar($output, count($outlineFiles));
        $progressBar->start();

        foreach ($outlineFiles as $file) {
            $baseIconName = $this->baseIconName(pathinfo($file, PATHINFO_FILENAME),$sourceDirs);
            // set baseIconName by removing prefix or suffic from iconName in case that was set in config ($sourceDirs['outline']['prefix'] or $sourceDirs['outline']['suffix'])
            //$baseIconName = isset($sourceDirs['outline']['prefix']) ? Str::after($iconName, $sourceDirs['outline']['prefix']) : $iconName;
            //$baseIconName = isset($sourceDirs['outline']['suffix']) ? Str::before($baseIconName, $sourceDirs['outline']['suffix']) : $baseIconName;

            // outline icons
            $outlineSvgPaths = $this->extractPaths(File::get($file));
            
            // in case there is a transform_svg_path function in the vendor config file, apply it
            if (isset($this->vendorConfig['transform_svg_path'])) {
                $outlineSvgPaths = $this->vendorConfig['transform_svg_path']('outline', $baseIconName, $outlineSvgPaths);
            }
            // in case there is a change_stroke_width function in the vendor config file, apply it
            // get all path tags from the svg content set it to $svgPaths
            $outlineStroke = 1.5;

            if (isset($this->vendorConfig['change_stroke_width'])) {
                $outlineStroke = $this->vendorConfig['change_stroke_width']($baseIconName, $outlineStroke, $outlineSvgPaths );
            }
            $outlinePath = $this->mergeSvgPaths($outlineSvgPaths);

            // solid icons
            // in case there are different sizes for solid icons, $sourceDirs['solid'] is an array, otherwise it's a string
            if (is_string($sourceDirs['solid'])) {
                $filledFile = base_path($sourceDirs['solid']) . "/$baseIconName.svg";
                $filledSvgPaths = File::exists($filledFile) ? $this->extractPaths(File::get($filledFile)) : $outlineSvgPaths;
                
                if (isset($this->vendorConfig['transform_svg_path'])) {
                    $filledSvgPaths = $this->vendorConfig['transform_svg_path']('solid', $baseIconName, $filledSvgPaths);
                }
                $filledMergedPath = $this->mergeSvgPaths($filledSvgPaths);
                $filledPath[24] = $filledMergedPath;
                $filledPath[20] = $filledMergedPath;
                $filledPath[16] = $filledMergedPath;

            } else {
                // sizes 24,20,26
                foreach([24, 20, 16] as $size) {
                    // add prefix or suffic to iconName in case set in config ($sourceDirs['solid'][$size]['prefix'] or $sourceDirs['solid'][$size]['suffix'])

                    $iconName = isset($sourceDirs['solid'][$size]['prefix']) ? $sourceDirs['solid'][$size]['prefix'] . $baseIconName : $baseIconName;
                    $iconName = isset($sourceDirs['solid'][$size]['suffix']) ? $baseIconName . $sourceDirs['solid'][$size]['suffix'] : $iconName;

                    $filledFile = base_path($sourceDirs['solid'][$size]['dir']) . "/$iconName.svg";
                    // if the icon doesn't exist in the current size,
                    if (!File::exists($filledFile)) {
                        // first try a size larger than the current size if that exists,
                        if ($size + 4 > 24) {
                            $filledFile = base_path($sourceDirs['solid'][24]) . "/$iconName.svg";
                        } else {
                            $filledFile = base_path($sourceDirs['solid'][$size + 4]) . "/$iconName.svg";
                        }
                        //  otherwise use the outline icon
                        if (!File::exists($filledFile)) {
                            $filledFile = base_path($sourceDirs['outline']) . "/$iconName.svg";
                        }
                    }

                    if(File::exists($filledFile)){
                        $filledSvgPaths = $this->extractPaths(File::get($filledFile));

                        if (isset($this->vendorConfig['transform_svg_path'])) {
                            $filledSvgPaths = $this->vendorConfig['transform_svg_path']('solid', $baseIconName, $filledSvgPaths);
                        }
                        $filledPath[$size] = $this->mergeSvgPaths($filledSvgPaths);
                    }
                    else{
                        $filledPath[$size] = $outlineSvgPaths;
                    }
                }
            }

            // get npm package details
            $packageDetails = json_decode(Storage::get("node_modules/{$this->vendorConfig['package_name']}/package.json"), true);
            $infoCredits = $packageDetails 
                        ? $packageDetails['name'] . ' (v'.$packageDetails['version'].') by ' . $packageDetails['author']
                        : '';

            $bladeTemplate = Str::of(File::get(resource_path('stubs/icon.blade.stub')))
                ->replace('{INFO_CREDITS}', $infoCredits)
                ->replace('{SVG_OUTLINE_STROKE}', $outlineStroke)
                ->replace('{SVG_PATH_OUTLINE_24}', $outlinePath)
                ->replace('{SVG_PATH_SOLID_24}', $filledPath[24])
                ->replace('{SVG_PATH_SOLID_20}', $filledPath[20])
                ->replace('{SVG_PATH_SOLID_16}', $filledPath[16]);

            File::put("$outputDir/$baseIconName.blade.php", $bladeTemplate);
            // Update the progress bar
            $progressBar->advance();
        }

        // Finish the progress bar
        $progressBar->finish();
    }

    public function extractPaths($content, $tagName = 'path')
    {
        $dom = new DOMDocument();
        // Suppress errors due to malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
    
        $xpath = new DOMXPath($dom);
        $tags = $xpath->query("//{$tagName}");
    
        $result = [];
        foreach ($tags as $tag) {
            $result[] = $dom->saveHTML($tag);
        }
    
        return $result;
    }

    protected function mergeSvgPaths(array $svgPaths): string
    {
        // extract the d attribute from each path tag and merge them into a single string
        return implode(' ', array_map(function ($svgPath) {
            return isset($svgPath['d']) ? $svgPath['d'] : '';
        }, $svgPaths));

    }

    protected function baseIconName($iconName, $sourceDirs): string
    {
        $baseIconName = $iconName;
        if (isset($sourceDirs['outline']['prefix'])) {
            $baseIconName = Str::after($iconName, $sourceDirs['outline']['prefix']);
        }
        if (isset($sourceDirs['outline']['suffix'])) {
            $baseIconName = Str::before($baseIconName, $sourceDirs['outline']['suffix']);
        }
        return $baseIconName;
    }

}