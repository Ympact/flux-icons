<?php

namespace Ympact\FluxIcons\Services;

use RuntimeException;
use Symfony\Component\Process\Process;
use Ympact\FluxIcons\Contracts\Vendor;

/**
 * Installs and updates the npm packages vendors draw their icons from.
 *
 * npm runs in the application's directory, so its package.json and .npmrc apply: a private
 * registry, such as Font Awesome Pro's, is configured there, with its token in the
 * environment. Arguments are passed to npm as a list, never through a shell.
 */
class NpmManager
{
    /** How long one npm call may take, in seconds. */
    public const TIMEOUT = 600;

    /**
     * @param  string|null  $directory  Where npm runs; the application's base path when null.
     * @param  string  $binary  The npm executable.
     */
    public function __construct(
        protected ?string $directory = null,
        protected string $binary = 'npm',
    ) {}

    /**
     * Install the vendor's package unless node_modules already holds it.
     *
     * @return bool Whether it had to be installed.
     *
     * @throws RuntimeException When npm fails or times out.
     */
    public function require(Vendor $vendor, bool $verbose = false): bool
    {
        if (is_file($this->directory()."/node_modules/{$vendor->package()}/package.json")) {
            return false;
        }

        $this->run(['install', $vendor->package(), '--save'], $verbose);

        return true;
    }

    /**
     * @throws RuntimeException When npm fails or times out.
     */
    public function update(Vendor $vendor, bool $verbose = false): void
    {
        $this->run(['update', $vendor->package()], $verbose);
    }

    /**
     * @param  array{0: string, 1: string}|list<string>  $arguments  The npm command and the package first.
     *
     * @throws RuntimeException When npm fails or times out.
     */
    protected function run(array $arguments, bool $verbose): void
    {
        $process = new Process(
            [$this->binary, ...$arguments, ...($verbose ? [] : ['--silent'])],
            $this->directory(),
            timeout: self::TIMEOUT,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $output = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            throw new RuntimeException("npm could not {$arguments[0]} {$arguments[1]}: {$output}");
        }
    }

    protected function directory(): string
    {
        return $this->directory ?? base_path();
    }
}
