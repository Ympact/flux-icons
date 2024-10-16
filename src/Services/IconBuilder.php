<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Ympact\FluxIcons\DataTypes\Icon;

class IconBuilder
{
    protected string $vendor;

    protected array|null $icons;
    
    protected string|array $sourceDirs;

    protected string $outputDir;

    protected $files;

    protected string $config = 'flux-icons';

    protected string $vendorConfig;

    protected bool $verbose;

    protected ConsoleOutput $output;

    public function __construct(string $vendor, Filesystem $files, array $icons = null, $verbose = false)
    {
        $this->verbose = $verbose;
        $this->output = new ConsoleOutput();

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

    /**
     * installPackage
     * @return void
     */
    public function installPackage()
    {
        $packageName = config("{$this->vendorConfig}.package_name");
        exec("npm install $packageName --save");
    }

    /**
     * buildIcons
     * @return void
     */
    public function buildIcons()
    {
        $this->setupDirs();

        $files = is_string($this->sourceDirs['outline']) 
                ? $this->files->files(base_path($this->sourceDirs['outline'])) 
                : $this->files->files(base_path($this->sourceDirs['outline']['dir']));
       
        // intersect the outlineFiles with the icons argument if it was passed. The icons argument can be a comma separated list of icon names without the file extension or the prefix/suffix
        if ($this->icons) {
            $icons = $this->icons;
             
            // map the files into a new collection as Icon() and by intersecting with $icons
            $outlineIcons = collect($files)->map(function($file){
                return new Icon(config($this->vendorConfig), $file);
            })->filter(function(Icon $icon) use ($icons){
                return in_array($icon->getName(), $icons);
            });
        }

        $progressBar = new ProgressBar($this->output, count( $outlineIcons));
        $progressBar->start();

        $infoCredits = $this->getPackageCredits();
        $infoFluxVersion = $this->getPackageCredits(true);

        foreach ($outlineIcons as $outlineIcon) {
            // in case there are no solid icons, use the preprocessed outline icon
            $baseIcon = $outlineIcon;

            $basename = $outlineIcon->process()->getName();
            $infoUsage = "<flux:icon.{$this->vendor}.{$basename} /> or <flux:icon name=\"{$this->vendor}.{$basename}\" />";

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
                ->replace('{INFO_CREDITS}', $infoCredits)
                ->replace('{INFO_FLUX_VERSION}', $infoFluxVersion)
                ->replace('{INFO_BUILD_DATE}', now()->format('Y-m-d H:i:s'))
                
                ->replace('{SVG_OUTLINE_STROKE}', $outlineIcon->getStrokeWidth())
                ->replace('{SVG_PATH_OUTLINE_24}', $outlinePath)
                ->replace('{SVG_PATH_SOLID_24}', $solidPath[24])
                ->replace('{SVG_PATH_SOLID_20}', $solidPath[20])
                ->replace('{SVG_PATH_SOLID_16}', $solidPath[16]);
                //->replace('{SVG_OUTLINE_24_SIZE}', 24)
                //->replace('{SVG_SOLID_24_SIZE}', 24)
                //->replace('{SVG_SOLID_20_SIZE}', 20)
                //->replace('{SVG_SOLID_16_SIZE}', 16);

            $put = File::put("{$this->outputDir}/{$basename}.blade.php", $bladeTemplate);
            if($this->verbose){
                if (!$put) {
                    $this->output->writeln("<error>Failed to write {$basename}.blade.php</error>");
                } else {
                    $this->output->writeln("<info>Wrote {$basename}.blade.php</info>");
                }
            }
            // Update the progress bar
            $progressBar->advance();
        }

        // Finish the progress bar
        $progressBar->finish();
    }

    /**
     * setupDirs
     * @return void
     */
    public function setupDirs(){
        $this->sourceDirs = config("{$this->vendorConfig}.source_directories");
        $this->outputDir = resource_path("views/flux/icon/{$this->vendor}");

        if (!File::exists($this->outputDir)) {
            if($this->verbose){
                $this->output->writeln("<info>Creating directory {$this->outputDir}</info>");
            }
            File::makeDirectory($this->outputDir, 0755, true);
        }
    }

    /**
     * getPackageCredits
     * get the details for the package and its authors to make a credits string
     * @param mixed $flux
     * @return string
     */
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

    /**
     * getSizedFile
     * Determine the file for the icon in the specified size
     * @param mixed $basename
     * @param mixed $size
     * @param mixed $variant
     * @return string
     */
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

    /**
     * getAvailableVendors
     * @return array
     */
    public static function getAvailableVendors(): array
    {
        return array_keys( config("flux-icons.vendors" ));
    }
}