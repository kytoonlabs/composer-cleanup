#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use KytoonLabs\ComposerCleanup\VendorCleaner;
use Composer\Factory;
use Composer\IO\ConsoleIO;

// Create Composer instance
$composer = Factory::create(new ConsoleIO(), null, true);
$io = new ConsoleIO();

// Create and run cleaner
$cleaner = new VendorCleaner($composer, $io);

try {
    $cleaner->cleanup();
    echo "Cleanup completed successfully!\n";
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 