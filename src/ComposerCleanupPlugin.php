<?php

namespace KytoonLabs\ComposerCleanup;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class ComposerCleanupPlugin implements PluginInterface
{
    private Composer $composer;
    private IOInterface $io;
    private VendorCleaner $cleaner;

    public function activate(Composer $composer, IOInterface $io): void
    {
        // $this->composer = $composer;
        // $this->io = $io;
        
        // // Check for composer-cleanup.json configuration file
        // $config = $this->loadConfiguration();
        // $this->cleaner = new VendorCleaner($composer, $io, $config);
    }

    // private function loadConfiguration(): Config
    // {
    //     $projectRoot = dirname($this->composer->getConfig()->get('vendor-dir'));
    //     $configFile = $projectRoot . '/composer-cleanup.json';
        
    //     if (file_exists($configFile)) {
    //         $this->io->write('<info>Loading configuration from composer-cleanup.json</info>');
    //         $configData = json_decode(file_get_contents($configFile), true);
            
    //         if (json_last_error() !== JSON_ERROR_NONE) {
    //             $this->io->writeError('<error>Invalid JSON in composer-cleanup.json: ' . json_last_error_msg() . '</error>');
    //             return new Config();
    //         }
            
    //         return new Config($configData);
    //     }
        
    //     $this->io->write('<comment>No composer-cleanup.json found, using default configuration</comment>');
    //     return new Config();
    // }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Cleanup when plugin is deactivated
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Cleanup when plugin is uninstalled
    }


} 