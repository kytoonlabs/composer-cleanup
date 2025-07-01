<?php

namespace KytoonLabs\ComposerCleanup;

class Config
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'scan_directories' => [
                'app',
                'config',
                'database',
                'resources',
                'routes',
                'tests',
            ],
            'exclude_directories' => [
                'vendor',
                'node_modules',
                'storage',
                'bootstrap/cache',
            ],
            'exclude_packages' => [
                'laravel/framework',
                'laravel/tinker',
                'laravel/sanctum',
                'laravel/telescope',
                'laravel/horizon',
                'laravel/nova',
            ],
            'exclude_package_types' => [
                'composer-plugin',
                'metapackage',
            ],
            'dry_run' => false,
            'verbose' => false,
        ], $config);
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    public function isDryRun(): bool
    {
        return $this->config['dry_run'];
    }

    public function isVerbose(): bool
    {
        return $this->config['verbose'];
    }

    public function getScanDirectories(): array
    {
        return $this->config['scan_directories'];
    }

    public function getExcludeDirectories(): array
    {
        return $this->config['exclude_directories'];
    }

    public function getExcludePackages(): array
    {
        return $this->config['exclude_packages'];
    }

    public function getExcludePackageTypes(): array
    {
        return $this->config['exclude_package_types'];
    }
} 