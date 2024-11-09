<?php

namespace Ympact\FluxIcons\Services;

use Illuminate\Support\Collection;
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
    
    protected string $outputDir;

    protected string $config = 'flux-icons';

    protected string $vendorConfig;

    protected bool $verbose;

    protected Collection $variants;

    protected $baseVariant = 'outline';

    protected array $variantDefaults = [
        'outline' => [
            'stub' => 'outline', // default stub for the icon, not necessary to specify
            'stroke_width' => 1.5,
            'size' => 24, // default size for the icon, not necessary to specify
            'path_attributes' => [
                'stroke-linecap' => 'round',
                'stroke-linejoin' => 'round',
            ],
            'source' => 'node_modules/@tabler/icons/icons/outline',
            // source can also be an array with dir, prefix, suffix and filter settings
            //'filter' => null,
        ],
        'solid' => [
            'stub' => 'solid',
            'stroke_width' => false, // there is no stroke width for solid icons
            'size' => 24,
            'path_attributes' => [
                'fill-rule' => 'evenodd',
                'clip-rule' => 'evenodd',
            ],
            'source' => 'node_modules/@tabler/icons/icons/filled',
            //'filter' => null,	
        ],
        // inherits the settings from solid variant
        'mini' => [
            'size' => 20
        ], 
        // inherits the settings from solid variant
        'micro' => [
            'size' => 16
        ]
    ];

    protected ConsoleOutput $output;

    public function __construct(string $vendor, array $icons = null, $verbose = false)
    {
        if($verbose){
            $this->setVerbose($verbose);
        }

        $this->output = new ConsoleOutput();

        $this->setVendor($vendor);

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
        }
        else{
            throw new \Exception("Vendor $vendor not found in config file");
        }
        return $this;
    }

    /**
     * set the icons that need to be build
     * @param mixed $icons
     * @return IconBuilder
     * @todo create tests for this method
     */
    public function setIcons($icons): static{
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

        return $this;
    }

    /**
     * buildIcons
     * @return void
     * @todo create tests for this method
     */
    public function buildIcons()
    {
        $this->determineDefaults();
        //dump($this->variants);

        $this->setupDirs();

        // get all files that match the base variant icons config
        $files = $this->getAvailableIcons($this->baseVariant);

        // if source.filter has a icon key, then filter the files using this callback
        if ($this->variantProp($this->baseVariant, 'source.filter')) {
            $files = $files->filter(function($file) {
                $icons = &$this->icons;
                $res = call_user_func_array($this->variantProp($this->baseVariant, 'source.filter'),[
                    $file,
                    &$icons
                ]);
                return $res;
            });
        }

        // map the files into a new collection as Icon() and by intersecting with $icons
        $baseIcons = $files->map(function($file){
            return new Icon(config($this->vendorConfig), $file);
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

        $progressBar = new ProgressBar($this->output, count( $baseIcons));
        $progressBar->start();

        $infoCredits = $this->getPackageCredits();
        $infoFluxVersion = $this->getPackageCredits(true);

        $iconBladeFile = Str::of(File::get(__DIR__.'/../../resources/stubs/icon.blade.stub'));

        foreach ($baseIcons as $baseIcon)
        {

            // in case there are no solid icons, use the preprocessed bse variant icon
            $basename = $baseIcon->process()->getBaseName();
            $infoUsage = "<flux:icon.{$this->namespace}.{$basename} /> or <flux:icon name=\"{$this->namespace}.{$basename}\" />";

            // loop through the variants collection build the icons
            foreach($this->variants as $variant => $config)
            {
                $variantBladeFile = Str::of(File::get(__DIR__.'/../../resources/stubs/variants/'.$variant.'.blade.stub'));

                // in case the variant is the baseVariant, we use the baseIcon
                if($variant == $this->baseVariant){
                    $icon = $baseIcon;
                }
                else{
                    // get the icon file for the variant
                    $file = $this->getVariantIconFile($variant,$basename);
                    $icon = $this->buildIcon($variant, $file, $baseIcon);
                }
                $icon->process()
                    ->transform($variant)
                    ->setAttributes($variant);

                if($config['stroke_width']){
                    $icon->setStrokeWidth(config("{$this->config}.default_stroke_width", null));
                }
                $variantBladeFile->replace('{SVG_PATHS}', $icon->toHtml())
                    ->replace('{SVG_SIZE}', $icon->getSize())
                    ->replace('{SVG_STROKE_WIDTH}', $icon->getStrokeWidth());
                
                $iconBladeFile->replace('{'.Str::upper($variant).'}', $variantBladeFile);
            }
            
            $iconBladeFile
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
            // Update the progress bar
            $progressBar->advance();
        }

        // Finish the progress bar
        $progressBar->finish();
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
        $this->variants = collect($this->variantDefaults)->map(function($variant, $key) use ($settings){
            // the mini and micro variants inherit the settings from the solid variant
            if($key == 'mini' || $key == 'micro'){
                $variant = Arr::merge($variant, Arr::get($settings, 'solid', []));
            }
            return Arr::merge($variant, Arr::get($settings, $key, []));
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
    public function buildIcon(string $variant, string $file, $baseIcon): Icon
    {
        $icon = new Icon(config($this->vendorConfig), $file );

        if ($filter = $this->variantProp($variant, 'source.filter', false)) {
            if(!call_user_func_array(
                $filter, 
                [$file]
            )){
                $icon = $baseIcon;
            }
        }

        // if the icon doesn't exist, we use the baseIcon
        if(!$icon->fileExists()){
            $icon = $baseIcon;
        }

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
     * getVariantFile
     * Determine the file for the icon in the specified variant
     * @param string $variant
     * @param string $basename
     * @return string
     * @todo create tests for this method
     */
    public function getVariantIconFile(string $variant, string $basename): string
    {       
        $iconName = $basename;
        if (is_string($this->variantProp($variant, 'source'))) {
            $file = base_path($this->variantProp($variant, 'source')) . "/$basename.svg";
        }
        else{
            // allow for passing the size to the callbacks
            $size = Arr::get($this->variants, "{$variant}.size", 24);

            if($prefix = $this->variantProp($variant, 'source.prefix')){
                // prefix can be either a string or a function
                $iconName = is_callable($prefix) ? $prefix($variant, $size) : $prefix . $iconName;
            }
            if($suffix = $this->variantProp($variant, 'source.suffix')){
                // suffix can be either a string or a function
                $iconName = is_callable($suffix) ? $suffix($variant, $size) : $iconName . $suffix;
            }

            $dir = $this->variantProp($variant, 'source.dir');
            // $dir can be a string or a callable and should finish with a slash
            $dir = is_callable($dir) ? $dir($variant, $size) : $dir;
            $file = Str::of(base_path($dir))->finish('/') . "{$iconName}.svg";
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
            // if a filter is passed in the config, don't use the prefix and suffix to prefilter the files
            // the prefix and suffix are still used to determine the basename
            if($this->variantProp($variant, 'source.filter')) {
                $files = File::glob(
                    Str::of(base_path($this->variantProp($variant, 'source.dir')))->finish('/') . '*' . '.svg'
                );
            }
            else{
                $files = File::glob(
                    Str::of(base_path($this->variantProp($variant, 'source.dir')))->finish('/') .
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