<?php

namespace KytoonLabs\Composer;

use Composer\Script\Event;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use PhpParser\ParserFactory;
use PhpParser\Node;
use Symfony\Component\Finder\Finder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Cleaner
{
    public static function cleanup(Event $event): void
    {
        $composer = $event->getComposer();
        $io = $event->getIO();

        if (Config::hasConfigFile($composer)) {
            $config = Config::loadConfiguration($composer, $io);
            $usedNamespaces = [];
            $usedClasses = [];
        
            $io->write('Analyzing Laravel application for used classes...');
            // Scan Laravel application files
            if ($config->isVerbose()) {
                $io->write('<comment>Config</comment>:'.json_encode($config->getConfig(), JSON_PRETTY_PRINT));
            }
            self::scanLaravelApplication($composer, $io, $config, $usedClasses, $usedNamespaces);

            if ($config->isVerbose()) {
                $io->write('<comment>Used namespaces</comment>:'.json_encode($usedNamespaces, JSON_PRETTY_PRINT));
            }
        
            // Get installed packages
            $installedPackages = $composer->getRepositoryManager()
                ->getLocalRepository()
                ->getPackages();
            
            $unusedPackages = self::findUnusedPackages($installedPackages, $config, $io, $usedNamespaces);
        
            if (empty($unusedPackages)) {
                $io->write('No unused packages found.');
                return;
            }
        
            $io->write(sprintf('Found %d potentially unused packages:', count($unusedPackages)));
            
            foreach ($unusedPackages as $package) {
                $io->write(sprintf('  - %s', $package->getName()));
            }
            
            // Remove unused packages
            self::removeUnusedPackages($unusedPackages, $composer, $io, $config);
        } else {
            $io->write('No composer-cleanup.json found, skipping cleanup');
        }
    }

    private static function scanLaravelApplication(Composer $composer, IOInterface $io, Config $config, array &$usedClasses, array &$usedNamespaces): void
    {
        $usedNamespaces = [];

        $projectRoot = dirname($composer->getConfig()->get('vendor-dir'));
        $parser = (new ParserFactory)->createForHostVersion();
        
        $finder = new Finder();
        $finder->files()
            ->name('*.php');
        
        // Add scan directories
        $scanDirs = array_map(function($dir) use ($projectRoot) {
            return $projectRoot . '/' . $dir;
        }, $config->getScanDirectories());
        
        $finder->in($scanDirs);
        
        // Add exclude directories
        foreach ($config->getExcludeDirectories() as $excludeDir) {
            $finder->exclude($excludeDir);
        }
        
        foreach ($finder as $file) {
            try {
                $ast = $parser->parse($file->getContents());
                self::extractUsedClasses($ast, $usedClasses, $usedNamespaces);
            } catch (\Exception $e) {
                if ($config->isVerbose()) {
                    $io->writeError(sprintf('Error parsing %s: %s', $file->getPathname(), $e->getMessage()));
                }
            }
        }
    }

    private static function extractUsedClasses(array $ast, array &$usedClasses, array &$usedNamespaces): void
    {
        foreach ($ast as $node) {
            self::processNode($node, $usedClasses, $usedNamespaces);
        }
    }

    private static function processNode(Node $node, array &$usedClasses, array &$usedNamespaces): void
    {
        // Handle use statements
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $usedNamespaces[] = $use->name->toString();
            }
        }
        // Handle group use statements
        elseif ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $node->prefix->toString();
            foreach ($node->uses as $use) {
                $usedNamespaces[] = $prefix . '\\' . $use->name->toString();
            }
        }
        // Handle class instantiation
        elseif ($node instanceof Node\Expr\New_) {
            if ($node->class instanceof Node\Name) {
                $usedClasses[] = $node->class->toString();
            }
        }
        // Handle static method calls
        elseif ($node instanceof Node\Expr\StaticCall) {
            if ($node->class instanceof Node\Name) {
                $usedClasses[] = $node->class->toString();
            }
        }
        // Handle class constant access
        elseif ($node instanceof Node\Expr\ClassConstFetch) {
            if ($node->class instanceof Node\Name) {
                $usedClasses[] = $node->class->toString();
            }
        }
        // Handle static property access
        elseif ($node instanceof Node\Expr\StaticPropertyFetch) {
            if ($node->class instanceof Node\Name) {
                $usedClasses[] = $node->class->toString();
            }
        }
        // Handle instanceof checks
        elseif ($node instanceof Node\Expr\Instanceof_) {
            if ($node->class instanceof Node\Name) {
                $usedClasses[] = $node->class->toString();
            }
        }
        // Handle catch blocks
        elseif ($node instanceof Node\Stmt\Catch_) {
            foreach ($node->types as $type) {
                $usedClasses[] = $type->toString();
            }
        }
        // Handle function calls (for global functions)
        elseif ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Name) {
                $usedClasses[] = $node->name->toString();
            }
        }
        // Handle type hints in function/method parameters
        elseif ($node instanceof Node\Param) {
            if ($node->type instanceof Node\Name) {
                $usedClasses[] = $node->type->toString();
            }
        }
        // Handle return type hints
        elseif ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod) {
            if ($node->returnType instanceof Node\Name) {
                $usedClasses[] = $node->returnType->toString();
            }
        }
        // Handle property type hints - fixed the undefined property issue
        elseif ($node instanceof Node\Stmt\PropertyProperty) {
            // PropertyProperty doesn't have a direct type property, it's on the parent Property node
            // This case is handled by the parent Property node processing
        }
        // Handle class extends
        elseif ($node instanceof Node\Stmt\Class_) {
            if ($node->extends instanceof Node\Name) {
                $usedClasses[] = $node->extends->toString();
            }
            // Handle implements
            foreach ($node->implements as $interface) {
                $usedClasses[] = $interface->toString();
            }
        }
        // Handle trait use
        elseif ($node instanceof Node\Stmt\TraitUse) {
            foreach ($node->traits as $trait) {
                $usedClasses[] = $trait->toString();
            }
        }
        // Handle property declarations with type hints
        elseif ($node instanceof Node\Stmt\Property) {
            if ($node->type instanceof Node\Name) {
                $usedClasses[] = $node->type->toString();
            }
        }

        // Recursively process child nodes
        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->$name;
            if (is_array($subNode)) {
                foreach ($subNode as $childNode) {
                    if ($childNode instanceof Node) {
                        self::processNode($childNode, $usedClasses, $usedNamespaces);
                    }
                }
            } elseif ($subNode instanceof Node) {
                self::processNode($subNode, $usedClasses, $usedNamespaces);
            }
        }
    }

    private static function findUnusedPackages(array $packages, Config $config, IOInterface $io, array $usedNamespaces): array
    {
        if ($config->isVerbose()) {
            $io->write('<comment>Analyzing package dependencies...</comment>');
        }

        // First, identify used and excluded packages
        $usedPackages = [];
        $excludedPackages = [];
        
        foreach ($packages as $package) {
            if (!self::isPackageUnused($package, $config, $io, $usedNamespaces)) {
                $usedPackages[] = $package->getName();
            }
            
            // Check if package is explicitly excluded
            foreach ($config->getExcludePackages() as $excludePackage) {
                if (strpos($package->getName(), $excludePackage) === 0) {
                    $excludedPackages[] = $package->getName();
                    break;
                }
            }
            
            // Check if package type is excluded
            foreach ($config->getExcludePackageTypes() as $excludeType) {
                if ($package->getType() === $excludeType) {
                    $excludedPackages[] = $package->getName();
                    break;
                }
            }
        }
        
        // Find packages that depend on used or excluded packages
        $dependentPackages = self::findDependentPackages($packages, array_merge($usedPackages, $excludedPackages), $config, $io);
        
        // Combine all packages that should not be removed
        $protectedPackages = array_merge($usedPackages, $excludedPackages, $dependentPackages);
        
        if ($config->isVerbose()) {
            $io->write('<comment>Used packages:</comment> ' . json_encode($usedPackages, JSON_PRETTY_PRINT));
            $io->write('<comment>Excluded packages:</comment> ' . json_encode($excludedPackages, JSON_PRETTY_PRINT));
            $io->write('<comment>Dependent packages:</comment> ' . json_encode($dependentPackages, JSON_PRETTY_PRINT));
        }
        
        // Find packages that are safe to remove
        $unusedPackages = [];
        foreach ($packages as $package) {
            if (!in_array($package->getName(), $protectedPackages)) {
                $unusedPackages[] = $package;
            }
        }
        
        if ($config->isVerbose()) {
            $io->write('<comment>...Done</comment>');
            $io->write('');
        }

        return $unusedPackages;
    }

    private static function findDependentPackages(array $packages, array $protectedPackages, Config $config, IOInterface $io): array
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
                            
                            if ($config->isVerbose()) {
                                $io->write("Package {$package->getName()} depends on {$requiredPackage} - marking as dependent");
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        return $dependentPackages;
    }

    private static function isPackageUnused(PackageInterface $package, Config $config, IOInterface $io, array $usedNamespaces): bool
    {
        // Skip excluded packages
        foreach ($config->getExcludePackages() as $excludePackage) {
            if (strpos($package->getName(), $excludePackage) === 0) {
                return false;
            }
        }
        
        // Skip excluded package types
        foreach ($config->getExcludePackageTypes() as $excludeType) {
            if ($package->getType() === $excludeType) {
                return false;
            }
        }
        
        $autoload = $package->getAutoload();
        //$io->write(json_encode($autoload, JSON_PRETTY_PRINT));
        
        foreach (['psr-0', 'psr-4', 'classmap'] as $type) {
            if (isset($autoload[$type])) {
                if (self::hasUsedClasses($autoload[$type], $type, $package->getName(), $io, $config, $usedNamespaces)) {
                    return false;
                }
            }
        }
        
        return true;
    }

    private static function hasUsedClasses(array $autoload, string $type, string $packageName, IOInterface $io, Config $config, array $usedNamespaces): bool
    {
        foreach ($autoload as $namespace => $path) {
            if ($type === 'psr-4' || $type === 'psr-0') {
                foreach ($usedNamespaces as $usedNamespace) {
                    if (strpos($usedNamespace, $namespace) === 0) {
                        if ($config->isVerbose()) {
                            $io->write("Detected Namespace: " . $usedNamespace . ", Composer Namespace: " . $namespace . ", Package Name: " . $packageName);
                        }
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    private static function removeUnusedPackages(array $packages, Composer $composer, IOInterface $io, Config $config): void
    {
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        
        foreach ($packages as $package) {
            $packageDir = $vendorDir . '/' . $package->getName();
            
            if (is_dir($packageDir)) {
                if ($config->isDryRun()) {
                    $io->write(sprintf('[<comment>DRY RUN</comment>] Would remove unused package: %s', $package->getName()));
                } else {
                    self::removeDirectory($packageDir);
                    $io->write(sprintf('<comment>Removed unused package</comment>: %s', $package->getName()));
                }
            }
        }
    }

    private static function removeDirectory(string $dir): void
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