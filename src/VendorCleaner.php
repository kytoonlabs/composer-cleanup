<?php

namespace KytoonLabs\ComposerCleanup;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Script\ScriptEvents;
use PhpParser\ParserFactory;
use PhpParser\Node;
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

        if ($this->config->isVerbose()) {
            $this->io->write('<comment>Composer Cleanup: Verbose Mode Enabled</comment>');
            $this->io->write('<comment>Config</comment>: ' . json_encode($this->config->getConfig(), JSON_PRETTY_PRINT) );
        }
        
        // Scan Laravel application files
        if ($this->scanLaravelApplication()) {

            $this->listUsedNamespaces();
            
            // Get installed packages
            $installedPackages = $this->composer->getRepositoryManager()
                ->getLocalRepository()
                ->getPackages();
            
            $unusedPackages = $this->findUnusedPackages($installedPackages);
            
            if (empty($unusedPackages)) {
                $this->io->write('No unused packages found.');
                return;
            }
            
            $this->io->write(sprintf('<comment>Found %d potentially unused packages</comment>:', count($unusedPackages)));
            
            foreach ($unusedPackages as $package) {
                $this->io->write(sprintf('  - %s', $package->getName()));
            }
            
            // Remove unused packages
            $this->removeUnusedPackages($unusedPackages);
        }
    }

    private function scanLaravelApplication(): bool
    {
        if (empty($this->config->getScanDirectories())) {
            $this->io->write('No scan directories configured, skipping Laravel application scan.');
            return false;
        }
        
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
        return true;
    }

    private function listUsedNamespaces(): void
    {
        if ($this->config->isVerbose()) {
            $this->io->write('<comment>Detected Namespaces</comment>: '. json_encode($this->usedNamespaces, JSON_PRETTY_PRINT));
        }
    }

    private function extractUsedClasses(array $ast): void
    {
        foreach ($ast as $node) {
            $this->processNode($node);
        }
    }

    private function processNode(Node $node): void
    {
        // Handle use statements
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->usedNamespaces[] = $use->name->toString();
            }
        }
        // Handle group use statements
        elseif ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $node->prefix->toString();
            foreach ($node->uses as $use) {
                $this->usedNamespaces[] = $prefix . '\\' . $use->name->toString();
            }
        }
        // Handle class instantiation
        elseif ($node instanceof Node\Expr\New_) {
            if ($node->class instanceof Node\Name) {
                $this->usedClasses[] = $node->class->toString();
            }
        }
        // Handle static method calls
        elseif ($node instanceof Node\Expr\StaticCall) {
            if ($node->class instanceof Node\Name) {
                $this->usedClasses[] = $node->class->toString();
            }
        }
        // Handle class constant access
        elseif ($node instanceof Node\Expr\ClassConstFetch) {
            if ($node->class instanceof Node\Name) {
                $this->usedClasses[] = $node->class->toString();
            }
        }
        // Handle static property access
        elseif ($node instanceof Node\Expr\StaticPropertyFetch) {
            if ($node->class instanceof Node\Name) {
                $this->usedClasses[] = $node->class->toString();
            }
        }
        // Handle instanceof checks
        elseif ($node instanceof Node\Expr\Instanceof_) {
            if ($node->class instanceof Node\Name) {
                $this->usedClasses[] = $node->class->toString();
            }
        }
        // Handle catch blocks
        elseif ($node instanceof Node\Stmt\Catch_) {
            foreach ($node->types as $type) {
                $this->usedClasses[] = $type->toString();
            }
        }
        // Handle function calls (for global functions)
        elseif ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Name) {
                $this->usedClasses[] = $node->name->toString();
            }
        }
        // Handle type hints in function/method parameters
        elseif ($node instanceof Node\Param) {
            if ($node->type instanceof Node\Name) {
                $this->usedClasses[] = $node->type->toString();
            }
        }
        // Handle return type hints
        elseif ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod) {
            if ($node->returnType instanceof Node\Name) {
                $this->usedClasses[] = $node->returnType->toString();
            }
        }
        // Handle property type hints
        elseif ($node instanceof Node\Stmt\Property) {
            if ($node->type instanceof Node\Name) {
                $this->usedClasses[] = $node->type->toString();
            }
        }
        // Handle class extends
        elseif ($node instanceof Node\Stmt\Class_) {
            if ($node->extends instanceof Node\Name) {
                $this->usedClasses[] = $node->extends->toString();
            }
            // Handle implements
            foreach ($node->implements as $interface) {
                $this->usedClasses[] = $interface->toString();
            }
        }
        // Handle trait use
        elseif ($node instanceof Node\Stmt\TraitUse) {
            foreach ($node->traits as $trait) {
                $this->usedClasses[] = $trait->toString();
            }
        }

        // Recursively process child nodes
        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->$name;
            if (is_array($subNode)) {
                foreach ($subNode as $childNode) {
                    if ($childNode instanceof Node) {
                        $this->processNode($childNode);
                    }
                }
            } elseif ($subNode instanceof Node) {
                $this->processNode($subNode);
            }
        }
    }

    private function findUnusedPackages(array $packages): array
    {
        if ($this->config->isVerbose()) {
            $this->io->write('<comment>Analyzing package dependencies...</comment>');
        }

        // First, identify used and excluded packages
        $usedPackages = [];
        $excludedPackages = [];
        
        foreach ($packages as $package) {
            if (!$this->isPackageUnused($package)) {
                $usedPackages[] = $package->getName();
            }
            
            // Check if package is explicitly excluded
            foreach ($this->config->getExcludePackages() as $excludePackage) {
                if (strpos($package->getName(), $excludePackage) === 0) {
                    $excludedPackages[] = $package->getName();
                    break;
                }
            }
            
            // Check if package type is excluded
            foreach ($this->config->getExcludePackageTypes() as $excludeType) {
                if ($package->getType() === $excludeType) {
                    $excludedPackages[] = $package->getName();
                    break;
                }
            }
        }
        
        // Find packages that depend on used or excluded packages
        $dependentPackages = $this->findDependentPackages($packages, array_merge($usedPackages, $excludedPackages));
        
        // Combine all packages that should not be removed
        $protectedPackages = array_merge($usedPackages, $excludedPackages, $dependentPackages);
        
        if ($this->config->isVerbose()) {
            $this->io->write('<comment>Used packages:</comment> ' . implode(', ', $usedPackages));
            $this->io->write('<comment>Excluded packages:</comment> ' . implode(', ', $excludedPackages));
            $this->io->write('<comment>Dependent packages:</comment> ' . implode(', ', $dependentPackages));
        }
        
        // Find packages that are safe to remove
        $unusedPackages = [];
        foreach ($packages as $package) {
            if (!in_array($package->getName(), $protectedPackages)) {
                $unusedPackages[] = $package;
            }
        }
        
        if ($this->config->isVerbose()) {
            $this->io->write('<comment>...Done</comment>');
            $this->io->write('');
        }

        return $unusedPackages;
    }

    private function findDependentPackages(array $packages, array $protectedPackages): array
    {
        $dependentPackages = [];
        $changed = true;
        
        // Keep iterating until no new dependent packages are found
        while ($changed) {
            $changed = false;
            
            foreach ($packages as $package) {
                if (in_array($package->getName(), $dependentPackages)) {
                    continue; // Already marked as dependent
                }
                
                $requires = $package->getRequires();
                foreach ($requires as $require) {
                    $requiredPackage = $require->getTarget();
                    if (in_array($requiredPackage, $protectedPackages) || in_array($requiredPackage, $dependentPackages)) {
                        if (!in_array($package->getName(), $dependentPackages)) {
                            $dependentPackages[] = $package->getName();
                            $changed = true;
                            
                            if ($this->config->isVerbose()) {
                                $this->io->write("Package {$package->getName()} depends on {$requiredPackage} - marking as dependent");
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        return $dependentPackages;
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
        //$this->io->write(json_encode($autoload, JSON_PRETTY_PRINT));
        
        foreach (['psr-0', 'psr-4', 'classmap'] as $type) {
            if (isset($autoload[$type])) {
                if ($this->hasUsedClasses($autoload[$type], $type, $package->getName())) {
                    return false;
                }
            }
        }
        
        return true;
    }

    private function hasUsedClasses(array $autoload, string $type, string $packageName): bool
    {
        foreach ($autoload as $namespace => $path) {
            if ($type === 'psr-4' || $type === 'psr-0') {
                foreach ($this->usedNamespaces as $usedNamespace) {
                    if (strpos($usedNamespace, $namespace) === 0) {
                        if ($this->config->isVerbose()) {
                            $this->io->write("Detected Namespace: " . $usedNamespace . ", Composer Namespace: " . $namespace . ", Package Name: " . $packageName);
                        }
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
                    $this->io->write(sprintf('[<comment>DRY RUN</comment>] Would remove unused package: %s', $package->getName()));
                } else {
                    $this->removeDirectory($packageDir);
                    $this->io->write(sprintf('<comment>Removed unused package</comment>: %s', $package->getName()));
                }
            }
        }
        
        // // Since we're running as a manual command, we need to regenerate autoload files
        // if (!$this->config->isDryRun() && !empty($packages)) {
        //     $this->regenerateAutoload();
        // }
    }

    // private function regenerateAutoload(): void
    // {
    //     $this->io->write('<info>Regenerating Composer autoload files...</info>');
        
    //     try {
    //         // Clear Composer cache to avoid circular issues
    //         $this->clearComposerCache();
            
    //         // Use the autoload generator to regenerate autoload files
    //         $autoloadGenerator = $this->composer->getAutoloadGenerator();
    //         $autoloadGenerator->dump(
    //             $this->composer->getConfig(),
    //             $this->composer->getRepositoryManager()->getLocalRepository(),
    //             $this->composer->getPackage(),
    //             $this->composer->getInstallationManager(),
    //             'composer',
    //             true
    //         );
            
    //         $this->io->write('<info>Autoload files regenerated successfully!</info>');
    //     } catch (\Exception $e) {
    //         $this->io->writeError('<error>Failed to regenerate autoload files: ' . $e->getMessage() . '</error>');
    //     }
    // }

    // private function clearComposerCache(): void
    // {
    //     try {
    //         $cacheDir = $this->composer->getConfig()->get('cache-dir');
    //         if (is_dir($cacheDir)) {
    //             $this->removeDirectory($cacheDir);
    //             mkdir($cacheDir, 0755, true);
    //             if ($this->config->isVerbose()) {
    //                 $this->io->write('<comment>Composer cache cleared</comment>');
    //             }
    //         }
    //     } catch (\Exception $e) {
    //         if ($this->config->isVerbose()) {
    //             $this->io->writeError('<comment>Warning: Could not clear Composer cache: ' . $e->getMessage() . '</comment>');
    //         }
    //     }
    // }

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