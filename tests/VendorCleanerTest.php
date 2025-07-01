<?php

namespace KytoonLabs\ComposerCleanup\Tests;

use PHPUnit\Framework\TestCase;
use KytoonLabs\ComposerCleanup\VendorCleaner;
use KytoonLabs\ComposerCleanup\Config;
use Composer\Composer;
use Composer\IO\NullIO;

class VendorCleanerTest extends TestCase
{
    private VendorCleaner $cleaner;
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config(['dry_run' => true]);
        $this->cleaner = new VendorCleaner(
            $this->createMock(Composer::class),
            new NullIO(),
            $this->config
        );
    }

    public function testConfigInitialization(): void
    {
        $this->assertTrue($this->config->isDryRun());
        $this->assertIsArray($this->config->getScanDirectories());
        $this->assertIsArray($this->config->getExcludePackages());
    }

    public function testConfigCustomization(): void
    {
        $customConfig = new Config([
            'scan_directories' => ['custom'],
            'exclude_packages' => ['custom/package'],
            'dry_run' => true,
            'verbose' => true
        ]);

        $this->assertEquals(['custom'], $customConfig->getScanDirectories());
        $this->assertEquals(['custom/package'], $customConfig->getExcludePackages());
        $this->assertTrue($customConfig->isDryRun());
        $this->assertTrue($customConfig->isVerbose());
    }
} 