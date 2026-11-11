<?php

use Ympact\FluxIcons\Services\NpmManager;
use Ympact\FluxIcons\Vendors\Tabler;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/flux-icons-npm-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->app->setBasePath($this->tempDir);
});

afterEach(function () {
    removeDirectory($this->tempDir);
});

it('leaves a package alone that node_modules already holds', function () {
    mkdir($this->tempDir.'/node_modules/@tabler/icons', 0755, true);
    file_put_contents($this->tempDir.'/node_modules/@tabler/icons/package.json', '{}');

    $npm = new NpmManager(binary: fakeNpm($this->tempDir));

    expect($npm->require(new Tabler))->toBeFalse()
        ->and(fakeNpmCalls($this->tempDir))->toBe([]);
});

it('installs a missing package from the application directory, quietly unless verbose', function () {
    $npm = new NpmManager(binary: fakeNpm($this->tempDir));

    expect($npm->require(new Tabler))->toBeTrue();
    $npm->require(new Tabler, verbose: true);

    expect(fakeNpmCalls($this->tempDir))->toBe([
        "{$this->tempDir} install @tabler/icons --save --silent",
        "{$this->tempDir} install @tabler/icons --save",
    ]);
});

it('updates the package in the directory it was given', function () {
    $project = $this->tempDir.'/project';
    mkdir($project);

    (new NpmManager($project, fakeNpm($this->tempDir)))->update(new Tabler);

    expect(fakeNpmCalls($this->tempDir))->toBe(["{$project} update @tabler/icons --silent"]);
});

it('passes the package to npm as one argument, without a shell', function () {
    $vendor = new class extends Tabler
    {
        public function package(): string
        {
            return 'icons; touch pwned';
        }
    };

    (new NpmManager(binary: fakeNpm($this->tempDir)))->update($vendor);

    expect(fakeNpmCalls($this->tempDir))->toBe(["{$this->tempDir} update icons; touch pwned --silent"])
        ->and(file_exists($this->tempDir.'/pwned'))->toBeFalse();
});

it('reports what npm said when it fails', function () {
    $npm = new NpmManager(binary: fakeNpm($this->tempDir, exitCode: 1, stderr: 'E404 Not Found'));

    expect(fn () => $npm->update(new Tabler))
        ->toThrow(RuntimeException::class, 'npm could not update @tabler/icons: E404 Not Found');
});

it('reports a missing npm executable', function () {
    $npm = new NpmManager(binary: $this->tempDir.'/no-npm-here');

    expect(fn () => $npm->require(new Tabler))
        ->toThrow(RuntimeException::class, 'npm could not install @tabler/icons');
});
