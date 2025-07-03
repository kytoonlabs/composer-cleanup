<?php

namespace KytoonLabs\ComposerCleanup;

class Config
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'scan_directories' => [],
            'exclude_directories' => [],
            'exclude_packages' => [
                'kytoonlabs/composer-cleanup',
            ],
            'exclude_package_types' => [
                'composer-plugin',
                'metapackage',
            ],
            'dry_run' => true,
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

    public function getConfig(): array
    {
        return $this->config;
    }

    public static function loadConfiguration($dir): Config
    {
        $projectRoot = dirname($dir);
        $configFile = $projectRoot . '/composer-cleanup.json';
        
        if (file_exists($configFile)) {
            //$this->io->write('<info>Loading configuration from composer-cleanup.json</info>');
            $configData = json_decode(file_get_contents($configFile), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                //$this->io->writeError('<error>Invalid JSON in composer-cleanup.json: ' . json_last_error_msg() . '</error>');
                return new Config();
            }
            
            return new Config($configData);
        }
        
        //$this->io->write('<comment>No composer-cleanup.json found, using default configuration</comment>');
        return new Config();
    }
} 