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

    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => 'notifyDeployScript'
        ];
    }

    public function notifyDeployScript(): void
    {
        $message = <<<MSG
===========================
MyOrg Deploy Plugin Notice
===========================

To enable deployment, please add the following script to your project's composer.json:

"scripts": {
    "deploy": "dep -f vendor/biffbangow/deployer/deploy.php deploy"
}

You can then deploy with:

composer deploy

===========================

MSG;
        $this->io->write($message);
    }
}
