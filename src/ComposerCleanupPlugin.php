<?php

namespace KytoonLabs\ComposerCleanup;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class ComposerCleanupPlugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;
    private VendorCleaner $cleaner;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->cleaner = new VendorCleaner($composer, $io);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Cleanup when plugin is deactivated
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Cleanup when plugin is uninstalled
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'onPreAutoloadDump',
        ];
    }

    public function onPreAutoloadDump(Event $event): void
    {
        $this->io->write('<info>Starting Laravel vendor cleanup...</info>');
        
        try {
            $this->cleaner->cleanup();
            $this->io->write('<info>Vendor cleanup completed successfully!</info>');
        } catch (\Exception $e) {
            $this->io->writeError('<error>Vendor cleanup failed: ' . $e->getMessage() . '</error>');
        }
    }
} 