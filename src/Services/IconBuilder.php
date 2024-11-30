<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Ympact\FluxIcons\Types\Icon;
use function Ympact\FluxIcons\arrayMergeRecursive;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;


class IconBuilder
{
    protected string $vendor;

    protected string $namespace;

    protected array|null $icons;
    
    protected string $outputDir;

    protected string $config = 'flux-icons';

    protected string $vendorConfig;

    protected bool $verbose;

    protected Collection|null $variants = null;

    protected $baseVariant = 'outline';

    protected array $variantDefaults = [
        'outline' => [
            'template' => 'outline', // default stub for the icon, not necessary to specify
            'stroke_width' => 1.5,
            'size' => 24, // default size for the icon, not necessary to specify
            'attributes' => [],
            'source' => 'node_modules/@tabler/icons/icons/outline',
            // source can also be an array with dir, prefix, suffix and filter settings
            //'filter' => null,
        ],
        'solid' => [
            'template' => 'solid',
            'fallback' => 'default',
            'stroke_width' => false, // there is no stroke width for solid icons
            'size' => 24,
            'attributes' => [],
            'source' => 'node_modules/@tabler/icons/icons/filled',
            //'filter' => null,	
        ],
        // inherits the settings from solid variant
        'mini' => [
            'base' => 'solid', // inherit solid settings
            'size' => 20
        ], 
        // inherits the settings from solid variant
        'micro' => [
            'base' => 'solid', // inherit solid settings
            'size' => 16
        ]
    ];

    protected ConsoleOutput $output;

    protected $progressBar;

    protected $chunckSize = 50;

    protected $timeout = 1000000/3; // in microseconds

    public function __construct(string $vendor = null, array $icons = null, $verbose = false)
    {
        if($verbose){
            $this->setVerbose($verbose);
        }

        $this->output = new ConsoleOutput();

        if($vendor){
            $this->setVendor($vendor);
        }

        if($icons){
            $this->setIcons($icons);
        }
    }

    /**
     * set the vendor to build the icons for
     * @param mixed $vendor
     * @throws \Exception
     * @return IconBuilder
     * @todo create tests for this method
     */
    public function setVendor($vendor): static{
        $this->vendor = $vendor;
        if(config()->has("{$this->config}.vendors.{$vendor}")){
            $this->vendorConfig = "{$this->config}.vendors.{$vendor}";
            $this->baseVariant = config("{$this->vendorConfig}.baseVariant", 'outline');
            $this->namespace = Str::slug(config("{$this->vendorConfig}.namespace") ?? $this->vendor);

            $this->determineDefaults();
        }
        else{
            throw new \Exception("Vendor $vendor not found in config file");
        }
        return $this;
    }

    /**
     * set the icons that need to be build
     * @param string|array $icons
     * @return IconBuilder
     * @todo create tests for this method
     */
    public function setIcons($icons): static
    {
        // make sure that the icons are an array
        if(is_string($icons)){
            $icons = explode(',', $icons);
        }

        $this->icons = $icons;
        return $this;
    }

    /**
     * setVerbose
     * @param mixed $verbose
     * @return IconBuilder
     * @todo create tests for this method
     */
    public function setVerbose($verbose): static{
        $this->verbose = $verbose;
        return $this;
    }

    /**
     * installPackage
     * @return void
     * @todo create tests for this method
     */
    public function requirePackage(): static
    {
        $packageName = config("{$this->vendorConfig}.package");
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

        return $this;
    }

    /**
     * buildIcons
     * @return void
     * @todo create tests for this method
     */
    public function buildIcons()
    {
        // only setup the dirs once we're sure we're gonna build some icons
        $this->setupDirs();

        // get all files that match the base variant icons config
        $files = $this->getAvailableIcons($this->baseVariant);

        // if source.filter has a icon key, then filter the files using this callback
        if ($this->variantProp($this->baseVariant, 'source.filter')) {
            $files = $files->filter(function($file) {
                $icons = &$this->icons;
                $res = call_user_func_array(
                    $this->variantProp($this->baseVariant, 'source.filter'),
                    [$file, &$icons, $this->baseVariant]
                );
                return $res;
            });
        }

        // map the files into a new collection as Icon() and by intersecting with $icons
        $baseIcons = $files->map(function($file){
            return  $this->buildIcon($this->baseVariant, $file);
            //return new Icon(config($this->vendorConfig), $file);
        });

        // intersect the base variant icons with the icons argument if it was passed. The icons argument can be a comma separated list of icon names without the file extension or the prefix/suffix
        if ($this->icons) {
            $icons = $this->icons;
                
            $baseIcons = $baseIcons->filter(function(Icon $icon) use ($icons){
                return in_array($icon->getName(), $icons) || in_array($icon->getBaseName(), $icons);
            });

            if($this->verbose){
                // get the difference between the icons argument and the baseIcons and output which icons are not found
                $diff = collect($icons)->diff($baseIcons->map(function($icon){
                    return $icon->getName();
                }));
                if($diff->count() > 0){
                    $this->output->writeln("<error> Icons not found: ". $diff->implode(', ') . " </error>");
                }
            }
        }

        $infoCredits = $this->getPackageCredits();
        $infoFluxVersion = $this->getPackageCredits(true);
     
        $npmRunning = false;
        if($baseIcons->count() > 100){
            $time = round($baseIcons->count() / 3);

            info("In case npm run dev is running, we'll introduce a timeout between processing icons to prevent memory issues. \nThis will make this script take $time seconds. \nIt is better to stop `npm run dev` first before continuing.");
            $npmRunning = confirm('Is `npm run dev` running?');
        }

        $this->progressBar = new ProgressBar($this->output, count( $baseIcons));
        $this->progressBar->start();

        // chunk the baseIcons collection into smaller collections to prevent memory issues
        $baseIcons->chunk($this->chunckSize)->each(function($chunk) use ($infoCredits, $infoFluxVersion, $npmRunning){
             // Process each chunk of icons
            foreach ($chunk as $icon) {
                $this->buildIconCollection($icon, $infoCredits, $infoFluxVersion);
                
                // Introduce a timeout between processing chunks
                if($npmRunning){   
                    usleep($this->timeout);
                }   
            }
        });

        // Finish the progress bar
        $this->progressBar->finish();

        // write empty line
        $this->output->writeln('');

        // ask to start running npm run dev again
        if(confirm('Do you want to start `npm run dev`?')){
            exec('npm run dev', $output, $result);
        }
        
    }

    /**
     * buildIconCollection
     * @param Collection $icons
     * @param ProgressBar $progressBar
     * @param string $infoCredits
     * @param string $infoFluxVersion
     * @return void
     * @todo create tests for this method
     */
    public function buildIconCollection(Icon $baseIcon, string $infoCredits, string $infoFluxVersion): void
    {
        $build = true;
        $iconBladeFile = Str::of(File::get(__DIR__.'/../../resources/stubs/icon.blade.stub'));

        // in case there are no solid icons, use the preprocessed bse variant icon
        $basename = $baseIcon->process()->getBaseName();
        $infoUsage = "<flux:icon.{$this->namespace}.{$basename} /> or <flux:icon name=\"{$this->namespace}.{$basename}\" />";

        // loop through the variants collection build the icons
        foreach($this->variants as $variant => $variantConfig)
        {
            // in case the variant is the baseVariant, we use the baseIcon
            $template = $this->variantProp($variant, 'template');
            if($variant == $this->baseVariant){
                $icon = $baseIcon;
            }
            else{
                // get the icon file for the variant
                $file = $this->getVariantIconFile($variant, $basename, $baseIcon);
                if(!$file){
                    $fallback = $this->determineFallback($variant, $baseIcon);
                    if(!$fallback){
                        $this->verbose ? error("No source found for $basename $variant. No fallback either, so we're not building this icon.") : null;
                        $build = false;
                        continue; 
                    }

                    $file = $this->getVariantIconFile($fallback, $basename, $baseIcon);
                    if(!$file){
                        $this->verbose ? error("No source found for $basename $fallback. We cannot build this icon.") : null;
                        $build = false;
                        continue; 
                    }
                    $template = $this->variantProp($fallback, 'template');
                }
                $icon = $this->buildIcon($variant, $file);
                
                $icon->setTemplate($template);
                $icon->process();
            }

            $icon->transform()
                ->setPathAttributes();

            if(config("{$this->vendorConfig}.stroke_width") || $this->variantProp($template, 'stroke_width')){
                $icon->setStrokeWidth($this->variantProp($template, 'stroke_width', null) ?? config("{$this->config}.default_stroke_width"));
            }

            $svg = $icon->toHtml();
            $svg = Str::of($svg)->replace('<svg', '<svg {{ $attributes->class($classes) }}');

            $iconBladeFile = $iconBladeFile->replace('{'.Str::upper($variant).'}',$svg);
        }
        
        if($build){
            $iconBladeFile = $iconBladeFile
                ->replace('{INFO_ICON_NAME}', $basename)
                ->replace('{INFO_ICON_USAGE}', $infoUsage)
                ->replace('{INFO_CREDITS}', $infoCredits)
                ->replace('{INFO_FLUX_VERSION}', $infoFluxVersion)
                ->replace('{INFO_BUILD_DATE}', now()->format('Y-m-d H:i:s'));

            $put = File::put("{$this->outputDir}/{$basename}.blade.php", $iconBladeFile);
            if($this->verbose){
                if (!$put) {
                    $this->output->writeln("<error>Failed to write {$basename}.blade.php</error>");
                } else {
                    $this->output->writeln("<info>Wrote {$basename}.blade.php</info>");
                }
            }
        }
        // Update the progress bar
        $this->progressBar->advance();
    
    }

    /**
     * determineDefaults
     * @return void
     * @todo create tests for this method
     */
    public function determineDefaults(): void
    {
        // for each variant, we determine the defaults by recursively merging the variantDefaults with the settings in the config into $this->variants
        $settings = config("{$this->vendorConfig}.variants");
        $this->variants = collect($this->variantDefaults)->except(['mini', 'micro'])->map(function($variant, $key) use ($settings){
            return arrayMergeRecursive(
                $variant, 
                Arr::get($settings, $key, [])
            );
        });

        $this->variants = collect($this->variantDefaults)->map(function($variant, $key) use ($settings){
            // for the mini and micro variants, we merge the settings with the base variant settings    
            // the mini and micro variants inherit the settings from the variant listed in the base key
            if($key == 'mini' || $key == 'micro'){
                $base = key_exists('base', $settings) ? $this->variants[$settings['base']] : $this->variants[$variant['base']];
                $variant = arrayMergeRecursive(
                    $variant, 
                    $base
                );
            }  
            return arrayMergeRecursive(
                $variant, 
                Arr::get($settings, $key, [])
            );
        });     
    }

    /**
     * buildIcon
     * TODO: better fallback handling
     * @param string $variant
     * @param string $file
     * @param Icon $baseIcon
     * @return Icon
     * @todo create test for this method
     */
    public function buildIcon(string $variant, string $file): Icon
    {
        $conf = config($this->vendorConfig);
        $conf['variants'] = $this->variants;

        $icon = new Icon($conf, $variant, $file);

        return $icon;
    }


    /**
     * setupDirs
     * @return void
     * @todo create tests for this method
     */
    public function setupDirs(): void
    {
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
     * @todo create tests for this method
     */
    public function getPackageCredits($flux = false)
    {
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
            $packageDir = config("{$this->vendorConfig}.package");
            $packageFile = base_path("node_modules/{$packageDir}/package.json");
            if(File::exists($packageFile)){
                $packageDetails = json_decode(File::get($packageFile), true);
                
                $name = Arr::get($packageDetails, 'name');
                $version = Arr::get($packageDetails, 'version');
                $author = Arr::get($packageDetails, 'author', null);
            
                $name = is_array($name) ? $name['name'] : $name;
                $author = is_array($author) ? $author['name'] : $author;

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
     * determineFallback
     * @return string|bool
     */
    public function determineFallback(string $variant, Icon $baseIcon): string|bool
    {
        $fallback = $this->variantProp($variant, 'fallback', false);
        // if fallback is set to false, we don't have a fallback
        if(!$fallback){
            return false;
        }
        // if fallback is set to 'variant', we use the variant as the fallback
        if($fallback == 'default'){
            return $this->baseVariant;
        }

        // in case an array is passed, we use as a callback
        if(is_callable($fallback)){
            return call_user_func_array($fallback, [$baseIcon, $variant, $this->baseVariant]);
        }

        // if it is a string, check if the string is a variant
        if(is_string($fallback) && $this->variants->has($fallback)){
            return $fallback;
        }
       
        //
        return false;
    }


    /**
     * getVariantFile
     * Determine the file for the icon in the specified variant
     * @param string $variant
     * @param string $basename
     * @return string|null
     * @todo create tests for this method
     */
    public function getVariantIconFile(string $variant, string $basename, Icon $icon): string|null
    {       
        $iconName = $basename;

        if(is_array($this->variantProp($variant, 'source'))){
            $size = $this->variantProp($variant, 'size', 24);
            if($prefix = $this->variantProp($variant, 'source.prefix')){
                // prefix can be either a string or a function
                $prefix = is_callable($prefix) ? call_user_func_array($prefix, [$variant]) : $prefix;
                $iconName = $prefix . $iconName;
            }
            if($suffix = $this->variantProp($variant, 'source.suffix')){
                // suffix can be either a string or a function
                $suffix = is_callable($suffix) ? call_user_func_array($suffix, [$variant]) : $suffix;
                $iconName = $iconName . $suffix;
            }

            $dir = $this->variantProp($variant, 'source.dir');
            // $dir can be a string or a callable and should finish with a slash
            $dir = is_callable($dir) ? call_user_func_array($dir, [$variant]) : $dir;
        }
        else{
            $dir = $this->variantProp($variant, 'source');
        }

        $file = File::glob(
            Str::of(base_path($dir))->finish('/'). "{$iconName}.svg"
        ); 

        if(empty($file)){
            return null;
        }
        else{
            $file = $file[0];
        }

        // check if the file is actually the variant file 
        if ($filter = $this->variantProp($variant, 'source.filter', false)) {
            $icons = $this->icons;
            if(!call_user_func_array(
                $filter, 
                [$file, &$icons, $variant]
            )){
                $file = null;
            }
        }

        // check if file exists
        if(!File::exists($file)){
            return null;
        }

        return $file;
    }

    /**
     * get a property from the variant settings
     * @param string $variant
     * @param string $prop
     * @param mixed $default
     * @return mixed
     */
    public function variantProp(string $variant, string $prop, mixed $default = null)
    {
        return Arr::get($this->variants, "{$variant}.{$prop}", $default);
    }
    
    /**
     * getAvailableVendors
     * @return Collection
     * @todo create tests for this method
     */
    public static function getAvailableVendors(): Collection
    {
        return collect(config("flux-icons.vendors" ));
    }

    /**
     * Get the Available Icons for a specific variant, constrained by the config for that variant
     * @param string $variant
     * @return \Illuminate\Support\Collection
     * @todo create tests for this method
     */
    public function getAvailableIcons($variant = 'outline'): Collection
    {
        $files = [];
        if(is_string($this->variantProp($variant, 'source'))){
            $files = File::glob(
                Str::of(base_path($this->variantProp($variant, 'source')))->finish('/'). '*.svg'
            ); 
        }
        else{
            $dir = $this->variantProp($variant, 'source.dir');
            // $dir can be a string or a callable and should finish with a slash
            $dir = is_callable($dir) ? call_user_func_array($dir, [$variant]) : $dir;

            // if a filter is passed in the config, don't use the prefix and suffix to prefilter the files
            // the prefix and suffix are still used to determine the basename
            if($this->variantProp($variant, 'source.filter')) {

                $files = File::glob(
                    Str::of(base_path($dir))->finish('/') . '*' . '.svg'
                );
            }
            else{
                $files = File::glob(
                    Str::of(base_path($dir))->finish('/') .
                    ($this->variantProp($variant, 'source.prefix') ?? '') . 
                    '*' . 
                    ($this->variantProp($variant, 'source.suffix') ?? '') . 
                    '.svg'
                );
            }
        }
        return collect($files);
    }
}