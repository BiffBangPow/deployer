<?php
namespace BiffBangPow\DeployPlugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // No-op
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // No-op
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'notifyDeployScript',
            'post-update-cmd' => 'notifyDeployScript'
        ];
    }

    public function notifyDeployScript(): void
    {
        $projectRoot = getcwd();
        $scriptPath = $projectRoot . '/deployer';

        $scriptContent = <<<'EOD'
#!/bin/sh
./vendor/deployer/deployer/bin/dep -f vendor/biffbangpow/deployer/deploy.php "$@"
EOD;

        file_put_contents($scriptPath, $scriptContent);
        chmod($scriptPath, 0755);
        
        $message = <<<MSG
===========================
BiffBangPow Deployer Notice
===========================
A new script has been added to the project root to enable quick deployments.  
            It can be accessed by running:  "./deployer deploy"  (for deployments)
            and "./deployer rollback"  (for rollbacks)
===========================

MSG;

        $this->io->write($message);
    }
}
