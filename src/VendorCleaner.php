<?php

namespace KytoonLabs\ComposerCleanup;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\PhpVersion;
use Symfony\Component\Finder\Finder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class VendorCleaner
{
    private Composer $composer;
    private IOInterface $io;
    private Config $config;
    private array $usedClasses = [];
    private array $usedNamespaces = [];
    private string $projectRoot;

    public function __construct(Composer $composer, IOInterface $io, ?Config $config = null)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->config = $config ?? new Config();
        $this->projectRoot = dirname($composer->getConfig()->get('vendor-dir'));
    }

    public function cleanup(): void
    {
        $this->io->write('Analyzing Laravel application for used classes...');
        
        // Scan Laravel application files
        $this->scanLaravelApplication();
        
        // // Get installed packages
        // $installedPackages = $this->composer->getRepositoryManager()
        //     ->getLocalRepository()
        //     ->getPackages();
        
        // $unusedPackages = $this->findUnusedPackages($installedPackages);
        
        // if (empty($unusedPackages)) {
        //     $this->io->write('No unused packages found.');
        //     return;
        // }
        
        // $this->io->write(sprintf('Found %d potentially unused packages:', count($unusedPackages)));
        
        // foreach ($unusedPackages as $package) {
        //     $this->io->write(sprintf('  - %s', $package->getName()));
        // }
        
        // // Remove unused packages
        // $this->removeUnusedPackages($unusedPackages);
    }

    private function scanLaravelApplication(): void
    {
        $parser = (new ParserFactory)->createForHostVersion();
        
        $finder = new Finder();
        $finder->files()
            ->name('*.php');
        
        // Add scan directories
        $scanDirs = array_map(function($dir) {
            return $this->projectRoot . '/' . $dir;
        }, $this->config->getScanDirectories());
        
        $finder->in($scanDirs);
        
        // Add exclude directories
        foreach ($this->config->getExcludeDirectories() as $excludeDir) {
            $finder->exclude($excludeDir);
        }
        
        foreach ($finder as $file) {
            try {
                $ast = $parser->parse($file->getContents());
                $this->extractUsedClasses($ast);
            } catch (\Exception $e) {
                if ($this->config->isVerbose()) {
                    $this->io->writeError(sprintf('Error parsing %s: %s', $file->getPathname(), $e->getMessage()));
                }
            }
        }
    }

    private function extractUsedClasses(array $ast): void
    {
        foreach ($ast as $node) {
            if ($node instanceof Node\Stmt\Use_) {
                foreach ($node->uses as $use) {
                    $this->usedNamespaces[] = $use->name->toString();
                }
            } elseif ($node instanceof Node\Expr\New_) {
                if ($node->class instanceof Node\Name) {
                    $this->usedClasses[] = $node->class->toString();
                }
            } elseif ($node instanceof Node\Expr\StaticCall) {
                if ($node->class instanceof Node\Name) {
                    $this->usedClasses[] = $node->class->toString();
                }
            } elseif ($node instanceof Node\Expr\ClassConstFetch) {
                if ($node->class instanceof Node\Name) {
                    $this->usedClasses[] = $node->class->toString();
                }
            }
        }
    }

    private function findUnusedPackages(array $packages): array
    {
        $unusedPackages = [];
        
        foreach ($packages as $package) {
            if ($this->isPackageUnused($package)) {
                $unusedPackages[] = $package;
            }
        }
        
        return $unusedPackages;
    }

    private function isPackageUnused(PackageInterface $package): bool
    {
        // Skip excluded packages
        foreach ($this->config->getExcludePackages() as $excludePackage) {
            if (strpos($package->getName(), $excludePackage) === 0) {
                return false;
            }
        }
        
        // Skip excluded package types
        foreach ($this->config->getExcludePackageTypes() as $excludeType) {
            if ($package->getType() === $excludeType) {
                return false;
            }
        }
        
        $autoload = $package->getAutoload();
        
        foreach (['psr-0', 'psr-4', 'classmap'] as $type) {
            if (isset($autoload[$type])) {
                if ($this->hasUsedClasses($autoload[$type], $type)) {
                    return false;
                }
            }
        }
        
        return true;
    }

    private function hasUsedClasses(array $autoload, string $type): bool
    {
        foreach ($autoload as $namespace => $path) {
            if ($type === 'psr-4' || $type === 'psr-0') {
                foreach ($this->usedNamespaces as $usedNamespace) {
                    if (strpos($usedNamespace, $namespace) === 0) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    private function removeUnusedPackages(array $packages): void
    {
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        
        foreach ($packages as $package) {
            $packageDir = $vendorDir . '/' . $package->getName();
            
            if (is_dir($packageDir)) {
                if ($this->config->isDryRun()) {
                    $this->io->write(sprintf('[DRY RUN] Would remove unused package: %s', $package->getName()));
                } else {
                    $this->removeDirectory($packageDir);
                    $this->io->write(sprintf('Removed unused package: %s', $package->getName()));
                }
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        
        rmdir($dir);
    }
} 