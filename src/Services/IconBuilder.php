<?php

namespace Ympact\FluxIcons\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Ympact\FluxIcons\DataTypes\Icon;

class IconBuilder
{
    
    protected $vendor;

    protected $icons;
    
    protected $sourceDirs;

    protected $outputDir;

    protected $files;

    protected $config = 'flux-icons';
    protected $vendorConfig;

    public function __construct($vendor, Filesystem $files, $icons = null)
    {
        $this->vendor = $vendor;
        if(config()->has("{$this->config}.vendors.{$vendor}")){
            $this->vendorConfig = "{$this->config}.vendors.{$vendor}";
        }
        else{
            throw new \Exception("Vendor $vendor not found in config file");
        }
        $this->icons = $icons;
        $this->files = $files;
    }

    public function installPackage()
    {
        $packageName = config("{$this->vendorConfig}.package_name");
        exec("npm install $packageName --save");
    }

    public function buildIcons()
    {
        $output = new ConsoleOutput();

        $this->setupDirs();

        $files = is_string($this->sourceDirs['outline']) 
                        ? $this->files->files(base_path($this->sourceDirs['outline'])) 
                        : $this->files->files(base_path($this->sourceDirs['outline']['dir']));
       
        // intersect the outlineFiles with the icons argument if it was passed. The icons argument can be a comma separated list of icon names without the file extension or the prefix/suffix
        if ($this->icons) {
            $icons = explode(',', $this->icons);
            
            // map the files into a new collection as Icon() and by intersecting with $icons
            $outlineIcons = collect($files)->map(function($file){
                return new Icon(config($this->vendorConfig), $file);
            })->filter(function(Icon $icon) use ($icons){
                return in_array($icon->getName(), $icons);
            });
        }

        $progressBar = new ProgressBar($output, count( $outlineIcons));
        $progressBar->start();
        $infoCredits = $this->getPackageCredits();
        $infoUsage = config("{$this->config}.usage");
        $infoFluxVersion = $this->getPackageCredits(true);

        foreach ($outlineIcons as $outlineIcon) {
            // in case there are no solid icons, use the preprocessed outline icon
            $baseIcon = $outlineIcon;

            $basename = $outlineIcon->process()->getName();
            
            // in case there is a transform_svg_path function in the vendor config file, apply it
            $outlineIcon->transform('outline');

            $outlineIcon->changeStrokeWidth(config("{$this->config}.default_stroke_wdith", null));

            $outlinePath = $outlineIcon->getMergedD();

            // solid icons
            // in case there are different sizes for solid icons, $sourceDirs['solid'] is an array, otherwise it's a string
            if (is_string($this->sourceDirs['solid'])) {
                $solidFile = base_path($this->sourceDirs['solid']) . "/$basename.svg";
                $solidIcon = new Icon(config($this->vendorConfig), $solidFile);
                
                if(! $solidIcon->fileExists()){
                    $solidIcon = $baseIcon;
                }
                
                $solidIcon->process()->transform('solid');

                $solidPath[24] = $solidIcon->getMergedD();
                $solidPath[20] = $solidIcon->getMergedD();
                $solidPath[16] = $solidIcon->getMergedD();

            } else {
                // sizes 24,20,26
                foreach([24, 20, 16] as $size) {
                    // add prefix or suffic to iconName in case set in config ($sourceDirs['solid'][$size]['prefix'] or $sourceDirs['solid'][$size]['suffix'])
                    $solidFile = $this->getSizedFile($basename, $size, 'solid');
                   
                    $solidIcon = new Icon(config($this->vendorConfig), $solidFile, );
                    if(!$solidIcon->fileExists()){
                        $solidIcon = $outlineIcon;
                    }
                    
                    $solidIcon->process()->transform('solid');
    
                    $solidPath[$size] = $solidIcon->resize($size)->getMergedD();
                }
            }

            $bladeTemplate = Str::of(File::get(__DIR__.'/../../stubs/icon.blade.stub'))
                ->replace('{INFO_ICON_NAME}', $basename)
                ->replace('{INFO_USAGE}', $infoUsage)
                ->replace('{INFO_BUILD_DATE}', now()->format('Y-m-d H:i:s'))
                ->replace('{INFO_CREDITS}', $infoCredits)
                ->replace('{INFO_FLUX_VERSION}', $infoFluxVersion)
                
                ->replace('{SVG_OUTLINE_STROKE}', $outlineIcon->getStrokeWidth())
                ->replace('{SVG_PATH_OUTLINE_24}', $outlinePath)
                ->replace('{SVG_PATH_SOLID_24}', $solidPath[24])
                ->replace('{SVG_PATH_SOLID_20}', $solidPath[20])
                ->replace('{SVG_PATH_SOLID_16}', $solidPath[16])
                ->replace('{SVG_OUTLINE_24_SIZE}', 24)
                ->replace('{SVG_SOLID_24_SIZE}', 24)
                ->replace('{SVG_SOLID_20_SIZE}', 20)
                ->replace('{SVG_SOLID_16_SIZE}', 16);

            $put = File::put("{$this->outputDir}/{$basename}.blade.php", $bladeTemplate);
            if (!$put) {
                $output->writeln("<error>Failed to write {$basename}.blade.php</error>");
            } else {
                $output->writeln("<info>Wrote {$basename}.blade.php</info>");
            }
            // Update the progress bar
            $progressBar->advance();
        }

        // Finish the progress bar
        $progressBar->finish();
    }

    public function setupDirs(){
        $this->sourceDirs = config("{$this->vendorConfig}.source_directories");
        $this->outputDir = resource_path("views/flux/icon/{$this->vendor}");

        if (!File::exists($this->outputDir)) {
            File::makeDirectory($this->outputDir, 0755, true);
        }
    }

    public function getPackageCredits($flux = false){
        $packageDetails = null;

        // if we want to get the credits for the flux package
        if($flux){
            // get datails from composer.lock
            $composerLock = json_decode(File::get(base_path('composer.lock')), true);
            $packageDetails = collect($composerLock['packages'])->firstWhere('name', 'livewire/flux');
            return $packageDetails 
                    ? $packageDetails['name'] . ' (v'.$packageDetails['version'].') by ' . $packageDetails['authors'][0]['name']
                    : '';
        }
        else{
            // get npm package details
            $packageDir = config("{$this->vendorConfig}.package_name");
            $packageFile = base_path("node_modules/{$packageDir}/package.json");
            if(File::exists($packageFile)){
                $packageDetails = json_decode(File::get($packageFile), true);
            }

            return $packageDetails 
                    ? $packageDetails['name'] . ' (v'.$packageDetails['version'].') by ' . $packageDetails['author']
                    : '';
        }
    }

    public function getSizedFile($basename, $size, $variant = 'solid'): string
    {       
        if($prefix = Arr::get($this->sourceDirs, "{$variant}.{$size}.prefix")){
            $iconName = $prefix . $basename;
        }
        if($suffix = Arr::get($this->sourceDirs, "{$variant}.{$size}.suffix")){
            $iconName = $suffix . $basename;
        }

        $file = base_path(Arr::get($this->sourceDirs, "{$variant}.{$size}.dir")) . "/$iconName.svg";
       
        // if the icon doesn't exist in the current size,
        if (!File::exists($file)) {
            // then try a size larger than the current size if that exists,
            if ($size + 4 > 24) {
                $file = base_path($this->sourceDirs[$variant][24]) . "/$iconName.svg";
            } else {
                $file = base_path($this->sourceDirs[$variant][$size + 4]) . "/$iconName.svg";
            }
            //  otherwise use the outline icon
            if (!File::exists($file)) {
                $file = base_path($this->sourceDirs[$variant]) . "/$iconName.svg";
            }
        }
        return $file;
    }


    public static function getAvailableVendors(): array
    {
        return array_keys( config("flux-icons.vendors" ));
    }


    /*
    public static function getDom($content): Fluent
    {
        $dom = new DOMDocument();
        // Suppress errors due to malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        return fluent([
            'dom' => $dom,
            'xpath' => $xpath
        ]);
    }


    public function extractPaths($content, $tagName = 'path')
    {
        $dom = $this->getDom($content);
        $tags = $dom->xpath->query("//{$tagName}");

        $result = [];
        foreach ($tags as $tag) {
            $result[] = $dom->dom->saveHTML($tag);
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
*/
}