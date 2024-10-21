<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Ympact\FluxIcons\Types\Icon;

class IconBuilder
{
    protected string $vendor;

    protected string $namespace;

    protected array|null $icons;
    
    protected string|array $sourceDirs;

    protected string $outputDir;

    protected $files;

    protected string $config = 'flux-icons';

    protected string $vendorConfig;

    protected bool $verbose;

    protected ConsoleOutput $output;

    public function __construct(string $vendor, array $icons = null, $verbose = false)
    {
        $this->verbose = $verbose;
        $this->output = new ConsoleOutput();

        $this->vendor = $vendor;
        if(config()->has("{$this->config}.vendors.{$vendor}")){
            $this->vendorConfig = "{$this->config}.vendors.{$vendor}";
            $this->namespace = Str::slug(config("{$this->vendorConfig}.namespace") ?? $this->vendor);
        }
        else{
            throw new \Exception("Vendor $vendor not found in config file");
        }
        $this->icons = $icons;
    }

    /**
     * installPackage
     * @return void
     */
    public function requirePackage()
    {
        $packageName = config("{$this->vendorConfig}.package_name");
        // check if package is not yet installed using package-lock.json
        $packageFile = base_path('package-lock.json');
        if(!File::exists($packageFile)){
            $this->verbose ? $this->output->writeln("<info>package-lock.json not found. Running npm install</info>") : null;
            exec("npm install");
        }
        $packageLock = json_decode(File::get($packageFile), true);
        $packages = collect($packageLock['packages']);

        if(!($packages->has('node_modules/'.$packageName) && File::exists('node_modules/'.$packageName.'/package.json'))){
            $this->verbose ? $this->output->writeln("<info>Package not found. Installing package $packageName</info>") : null;
            // in case !$verbose keep npm install silent
            $arg = $this->verbose ? '' : '-s';
            exec("npm install $packageName --save {$arg}", $output, $result);

            if($result == 128){
                $this->output->writeln("<error>Failed to install package $packageName. Please check if it is set correctly in the config.</error>");
                // finish the process
                exit(1);
            }
        }
        else{
            $this->verbose ? $this->output->writeln("<info>Package $packageName already installed</info>") : null;
        }

    }

    /**
     * buildIcons
     * @return void
     */
    public function buildIcons()
    {
        $this->setupDirs();

        // get all files that match the outline icons config
        $files = [];
        if(is_string($this->sourceDirs['outline'])){
           $files = File::glob(
                Str::of(base_path($this->sourceDirs['outline']))->finish('/'). '*.svg'
           ); 
        }
        else{
            // if a filter is passed in the config, don't use the prefix and suffix to prefilter the files
            // the prefix and suffix are still used to determine the basename
            if(Arr::has($this->sourceDirs, 'outline.filter')) {
                $files = File::glob(
                    Str::of(base_path($this->sourceDirs['outline']['dir']))->finish('/') . '*' . '.svg'
                );
            }
            else{
                $files = File::glob(
                    Str::of(base_path($this->sourceDirs['outline']['dir']))->finish('/') .
                    ($this->sourceDirs['outline']['prefix'] ?? '') . 
                    '*' . 
                    ($this->sourceDirs['outline']['suffix'] ?? '') . 
                    '.svg'
                );
            }
        }

        // if $this->sourceDirs['outline'] has a icon key, then filter the files using this callback
        if (Arr::has($this->sourceDirs, 'outline.filter')) {
            $files = collect($files)->filter(function($file) {
                $icons = &$this->icons;
                $res = call_user_func_array($this->sourceDirs['outline']['filter'],[
                    $file,
                    &$icons
                ]);
                return $res;
            });
        }

        // map the files into a new collection as Icon() and by intersecting with $icons
        $outlineIcons = collect($files)->map(function($file){
            return new Icon(config($this->vendorConfig), $file);
        });

        // intersect the outlineFiles with the icons argument if it was passed. The icons argument can be a comma separated list of icon names without the file extension or the prefix/suffix
        if ($this->icons) {
            $icons = $this->icons;
                
            $outlineIcons->filter(function(Icon $icon) use ($icons){
                return in_array($icon->getName(), $icons) || in_array($icon->getBaseName(), $icons);
            });

            if($this->verbose){
                // get the difference between the icons argument and the outlineIcons and output which icons are not found
                $diff = collect($icons)->diff($outlineIcons->map(function($icon){
                    return $icon->getName();
                }));
                if($diff->count() > 0){
                    $this->output->writeln("<error> Icons not found: ". $diff->implode(', ') . " </error>");
                }
            }
        }

        $progressBar = new ProgressBar($this->output, count( $outlineIcons));
        $progressBar->start();

        $infoCredits = $this->getPackageCredits();
        $infoFluxVersion = $this->getPackageCredits(true);

        foreach ($outlineIcons as $outlineIcon) {
            // in case there are no solid icons, use the preprocessed outline icon
            $baseIcon = $outlineIcon;
            $basename = $outlineIcon->process()->getBaseName();
            $infoUsage = "<flux:icon.{$this->namespace}.{$basename} /> or <flux:icon name=\"{$this->namespace}.{$basename}\" />";

            // in case there is a transform_svg_path function in the vendor config file, apply it
            $outlineIcon->transform('outline');

            $outlineIcon->changeStrokeWidth(config("{$this->config}.default_stroke_wdith", null));

            // solid icons
            // in case there are different sizes for solid icons, $sourceDirs['solid'] is an array, otherwise it's a string
            if (is_string($this->sourceDirs['solid'])) {
                $solidFile = base_path($this->sourceDirs['solid']) . "/$basename.svg";
                $solidIcon = $this->buildSolidIcon($solidFile, $baseIcon);
 
                $solidIcons[24] = $solidIcon;
                $solidIcons[20] = $solidIcon;
                $solidIcons[16] = $solidIcon;

            } else {
                // we have an array to that specifies how to get the solid icons
                foreach([24, 20, 16] as $size) {
                    // add prefix or suffic to iconName in case set in config ($sourceDirs['solid'][$size]['prefix'] or $sourceDirs['solid'][$size]['suffix'])
                    $solidFile = $this->getSizedFile($basename, $size, 'solid');
                    $solidIcon = $this->buildSolidIcon($solidFile, $baseIcon);

                    $solidIcons[$size] = $solidIcon; //->resize($size)
                }
            }

            $bladeTemplate = Str::of(File::get(__DIR__.'/../../resources/stubs/icon.blade.stub'))
                ->replace('{INFO_ICON_NAME}', $basename)
                ->replace('{INFO_ICON_USAGE}', $infoUsage)
                ->replace('{INFO_CREDITS}', $infoCredits)
                ->replace('{INFO_FLUX_VERSION}', $infoFluxVersion)
                ->replace('{INFO_BUILD_DATE}', now()->format('Y-m-d H:i:s'))
                
                ->replace('{SVG_OUTLINE_STROKE}', $outlineIcon->getStrokeWidth())
                ->replace('{SVG_PATH_OUTLINE_24}', $outlineIcon->getMergedD())
                ->replace('{SVG_PATH_SOLID_24}', $solidIcons[24]->getMergedD())
                ->replace('{SVG_PATH_SOLID_20}', $solidIcons[20]->getMergedD())
                ->replace('{SVG_PATH_SOLID_16}', $solidIcons[16]->getMergedD())
                ->replace('{SVG_OUTLINE_24_SIZE}', $outlineIcon->getSize())
                ->replace('{SVG_SOLID_24_SIZE}', $solidIcons[24]->getSize())
                ->replace('{SVG_SOLID_20_SIZE}', $solidIcons[20]->getSize())
                ->replace('{SVG_SOLID_16_SIZE}', $solidIcons[16]->getSize());

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
     * buildSolidIcon
     * TODO: better fallback handling
     * @param mixed $solidFile
     * @param mixed $baseIcon
     * @return mixed
     */
    public function buildSolidIcon($solidFile, $baseIcon){

        $solidIcon = new Icon(config($this->vendorConfig), $solidFile );

        if (Arr::has($this->sourceDirs, 'solid.filter')) {
            if(!call_user_func_array(
                $this->sourceDirs['solid']['filter'], 
                [$solidFile]
            )){
                // the iconFile is not a solid icon, so we use the outline icon
                $solidIcon = $baseIcon;
            }
        }

        if(!$solidIcon->fileExists()){
            $solidIcon = $baseIcon;
        }
        
        $solidIcon->process()->transform('solid');
        return $solidIcon;
    }


    /**
     * setupDirs
     * @return void
     */
    public function setupDirs(){
        $this->sourceDirs = config("{$this->vendorConfig}.source_directories");
        // if we have a namespace in the vendor config, we use that as the output directory, otherwise we use the vendor name
        $this->outputDir = resource_path("views/flux/icon/{$this->namespace}");

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
            
            $name = Arr::get($packageDetails, 'name');
            $version = Arr::get($packageDetails, 'version');

            return $packageDetails 
                    ? $name. ' ('.$version.') by ' . $packageDetails['authors'][0]['name']
                    : '';
        }
        else{
            // get npm package details
            $packageDir = config("{$this->vendorConfig}.package_name");
            $packageFile = base_path("node_modules/{$packageDir}/package.json");
            if(File::exists($packageFile)){
                $packageDetails = json_decode(File::get($packageFile), true);
                
                $name = Arr::get($packageDetails, 'name');
                $version = Arr::get($packageDetails, 'version');
                $author = Arr::get($packageDetails, 'author', null);
            
                return $packageDetails 
                    ? $name . ' ('.$version.') '. ( $author ? 'by ' . $author : '')
                    : '';
            }
            else{
                return '-- Package details not found --';
            }
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
        $iconName = $basename;
        if($prefix = Arr::get($this->sourceDirs, "{$variant}.prefix")){
            // prefix can be either a string or a function
            $iconName = is_callable($prefix) ? $prefix($size) : $prefix . $iconName;
        }
        if($suffix = Arr::get($this->sourceDirs, "{$variant}.suffix")){
            // suffix can be either a string or a function
            $iconName = is_callable($suffix) ? $suffix($size) : $suffix . $iconName;
        }

        $dir = Arr::get($this->sourceDirs, "{$variant}.dir");
        // $dir can be a string or a callable and should finish with a slash
        $dir = is_callable($dir) ? $dir($size) : $dir;
        $file = Str::of(base_path($dir))->finish('/') . "{$iconName}.svg";

        return $file;
    }

    public static function solidFallback($file, $iconName, $size){
        // the icon doesn't exist in the current size, so we try to find a larger size
        // 
        /*
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
        */
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