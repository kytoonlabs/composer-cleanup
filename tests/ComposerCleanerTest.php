<?php

namespace KytoonLabs\Composer\Tests;

use PHPUnit\Framework\TestCase;
use KytoonLabs\Composer\Cleaner;
use KytoonLabs\Composer\Config;
use Composer\Composer;
use Composer\IO\NullIO;
use Composer\Config as ComposerConfig;
use Composer\Repository\RepositoryManager;
use Composer\Repository\InstalledRepositoryInterface;

class ComposerCleanerTest extends TestCase
{
    private Config $config;
    private Composer $composer;
    private NullIO $io;

    protected function setUp(): void
    {
        $this->config = new Config(['dry_run' => true]);
        $this->io = new NullIO();
        
        // Create a proper Composer mock with all required dependencies
        $this->composer = $this->createComposerMock();
    }

    private function createComposerMock(): Composer
    {
        $composerConfig = $this->createConfiguredMock(ComposerConfig::class, [
            'get' => '/path/to/vendor'
        ]);

        $localRepository = $this->createConfiguredMock(InstalledRepositoryInterface::class, [
            'getPackages' => []
        ]);
        
        $repositoryManager = $this->createConfiguredMock(RepositoryManager::class, [
            'getLocalRepository' => $localRepository
        ]);

        /** @var Composer&\PHPUnit\Framework\MockObject\MockObject $composer */
        $composer = $this->createConfiguredMock(Composer::class, [
            'getConfig' => $composerConfig,
            'getRepositoryManager' => $repositoryManager
        ]);
        
        return $composer;
    }

    public function testConfigInitialization(): void
    {
        $this->assertTrue($this->config->isDryRun());
        $this->assertIsArray($this->config->getScanDirectories());
        $this->assertIsArray($this->config->getExcludePackages());
        $this->assertIsArray($this->config->getExcludeDirectories());
        $this->assertIsArray($this->config->getExcludePackageTypes());
    }

    public function testConfigCustomization(): void
    {
        $customConfig = new Config([
            'scan_directories' => ['custom'],
            'exclude_packages' => ['custom/package'],
            'exclude_directories' => ['custom/exclude'],
            'exclude_package_types' => ['custom-type'],
            'dry_run' => true,
            'verbose' => true
        ]);

        $this->assertEquals(['custom'], $customConfig->getScanDirectories());
        $this->assertEquals(['custom/package'], $customConfig->getExcludePackages());
        $this->assertEquals(['custom/exclude'], $customConfig->getExcludeDirectories());
        $this->assertEquals(['custom-type'], $customConfig->getExcludePackageTypes());
        $this->assertTrue($customConfig->isDryRun());
        $this->assertTrue($customConfig->isVerbose());
    }

    public function testConfigDefaultValues(): void
    {
        $defaultConfig = new Config();
        
        $this->assertTrue($defaultConfig->isDryRun());
        $this->assertFalse($defaultConfig->isVerbose());
        $this->assertEmpty($defaultConfig->getScanDirectories());
        $this->assertEmpty($defaultConfig->getExcludeDirectories());
        $this->assertContains('kytoonlabs/composer-cleanup', $defaultConfig->getExcludePackages());
        $this->assertContains('composer-plugin', $defaultConfig->getExcludePackageTypes());
        $this->assertContains('metapackage', $defaultConfig->getExcludePackageTypes());
    }

    public function testConfigGetAndSet(): void
    {
        $config = new Config();
        
        $this->assertNull($config->get('non_existent_key'));
        $this->assertEquals('default', $config->get('non_existent_key', 'default'));
        
        $config->set('custom_key', 'custom_value');
        $this->assertEquals('custom_value', $config->get('custom_key'));
    }

    public function testCleanerClassExists(): void
    {
        $this->assertTrue(class_exists(Cleaner::class));
        $this->assertTrue(method_exists(Cleaner::class, 'cleanup'));
    }

    public function testCleanerStaticMethod(): void
    {
        // Test that the cleanup method is static and accessible
        $reflection = new \ReflectionClass(Cleaner::class);
        $method = $reflection->getMethod('cleanup');
        $this->assertTrue($method->isStatic());
        $this->assertTrue($method->isPublic());
    }
} 