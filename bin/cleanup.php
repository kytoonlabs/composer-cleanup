#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use KytoonLabs\ComposerCleanup\VendorCleaner;
use KytoonLabs\ComposerCleanup\Config;
use Composer\Factory;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\HelperSet;

// Create Composer instance
$input = new ArgvInput();
$output = new ConsoleOutput();
$helperSet = new HelperSet();
$io = new ConsoleIO($input, $output, $helperSet);
$composer = Factory::create($io, null, true);

// Create and run cleaner
$cleaner = new VendorCleaner($composer, $io, Config::loadConfiguration($composer->getConfig()->get('vendor-dir')));

try {
    $cleaner->cleanup();
    echo "Cleanup completed successfully!\n";
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 