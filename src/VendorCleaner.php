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

        $this->listUsedClasses();
        $this->listUsedNamespaces();
        
        // // Get installed packages
        $installedPackages = $this->composer->getRepositoryManager()
            ->getLocalRepository()
            ->getPackages();
        
        $unusedPackages = $this->findUnusedPackages($installedPackages);
        
        if (empty($unusedPackages)) {
            $this->io->write('No unused packages found.');
            return;
        }
        
        $this->io->write(sprintf('Found %d potentially unused packages:', count($unusedPackages)));
        
        foreach ($unusedPackages as $package) {
            $this->io->write(sprintf('  - %s', $package->getName()));
        }
        
        // // Remove unused packages
        $this->removeUnusedPackages($unusedPackages);
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

    private function listUsedClasses(): void
    {
        $this->io->write(json_encode($this->usedClasses, JSON_PRETTY_PRINT));
    }

    private function listUsedNamespaces(): void
    {
        $this->io->write(json_encode($this->usedNamespaces, JSON_PRETTY_PRINT));
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
        elseif ($node instanceof Node\Stmt\PropertyProperty) {
            if (isset($node->type) && $node->type instanceof Node\Name) {
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
                        $this->io->write("usedNamespace: " . $usedNamespace . ", namespace: " . $namespace . ", packageName: " . $packageName);
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