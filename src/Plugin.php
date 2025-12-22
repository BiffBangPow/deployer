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

        // Files to update
        $ignoreFiles = ['.gitignore', '.deployignore'];

        foreach ($ignoreFiles as $ignoreFile) {
            $ignorePath = $projectRoot . '/' . $ignoreFile;

            // Create file if it doesn't exist
            if (!file_exists($ignorePath)) {
                file_put_contents($ignorePath, "deployer\n");
                $this->io->write("Created $ignoreFile and added 'deployer'");
                continue;
            }

            // Read existing content
            $content = file_get_contents($ignorePath);
            $lines = preg_split('/\R/', $content);

            // Add 'deployer' if not present
            if (!in_array('deployer', $lines, true)) {
                // Append with newline if file doesn't end with newline
                $content = rtrim($content) . "\n" . "deployer\n";
                file_put_contents($ignorePath, $content);
                $this->io->write("Added 'deployer' to $ignoreFile");
            } else {
                $this->io->write("'deployer' already exists in $ignoreFile");
            }
        }
        
        
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
