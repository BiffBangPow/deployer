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
            'post-update-cmd'  => 'notifyDeployScript',
        ];
    }

    public function notifyDeployScript(): void
    {
        $message = <<<MSG
===========================
BiffBangPow Deployer Notice
===========================

To enable deployment, please add the following script to your project's composer.json:

"scripts": {
    "deploy": "dep -f vendor/biffbangpow/deployer/deploy.php deploy"
}

You can then deploy with:

composer deploy

===========================

MSG;

        $this->io->write($message);
    }
}
