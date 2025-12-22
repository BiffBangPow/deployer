<?php
namespace Deployer;

require 'vendor/autoload.php';
require 'recipe/common.php';

use Symfony\Component\Yaml\Yaml;

// Load deploy.yml config
$configFile = __DIR__ . '/deploy.yml';
if (!file_exists($configFile)) {
    throw new \RuntimeException("Missing deploy.yml file in project root.");
}
$config = Yaml::parseFile($configFile);

// Merge excludes from deploy.yml and optional .deployignore
$exclude = $config['exclude'] ?? [];
$deployIgnoreFile = __DIR__ . '/.deployignore';
if (file_exists($deployIgnoreFile)) {
    $deployIgnore = file($deployIgnoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $exclude = array_merge($exclude, $deployIgnore);
}
$exclude = array_unique($exclude);

// Disable remote git clone, since we upload built files
set('repository', '');

// Basic settings from YAML config
set('application', $config['project']['name']);
set('deploy_path', $config['server']['deploy_path']);
set('shared_dirs', $config['shared'] ?? []);
set('writable_dirs', $config['writable'] ?? []);
set('keep_releases', 2);
set('git_tty', false);

// Define host
host($config['project']['name'])
    ->setHostname($config['server']['host'])
    ->setRemoteUser($config['server']['user'])
    ->setPort($config['server']['port'] ?? 22)
    ->setDeployPath($config['server']['deploy_path']);

// Local build task
task('build:local', function () use ($config) {
    foreach ($config['build']['local'] ?? [] as $command) {
        writeln("<info>Running locally:</info> $command");
        runLocally($command);
    }
});

// Rsync task with excludes
task('deploy:rsync', function () use ($exclude, $config) {
    $src = rtrim(realpath(__DIR__), '/') . '/';
    $dest = '{{release_path}}';

    // Build exclude arguments for rsync
    $excludeArgs = '';
    foreach ($exclude as $item) {
        $excludeArgs .= " --exclude='$item'";
    }

    $host = $config['server']['host'];
    $user = $config['server']['user'];
    $port = $config['server']['port'] ?? 22;

    // Compose SSH command with port
    $sshCmd = "-e 'ssh -p $port'";

    // Run rsync locally to remote
    runLocally("rsync -az --delete $excludeArgs $sshCmd $src $user@$host:$dest");
});

// Production commands before symlink switch
task('deploy:before_symlink', function () use ($config) {
    foreach ($config['deploy']['before_symlink'] ?? [] as $cmd) {
        run("cd {{release_path}} && $cmd");
    }
});

// Production commands after symlink switch
task('deploy:after_symlink', function () use ($config) {
    foreach ($config['deploy']['after_symlink'] ?? [] as $cmd) {
        run("cd {{release_path}} && $cmd");
    }
});

/**
 * Ensure git working tree is clean before deploying
 */
task('git:check_clean', function () {
    $status = trim(runLocally('git status --porcelain'));
    if ($status !== '') {
        throw new \RuntimeException(
            "Working tree is not clean.\nCommit or stash changes before deploying."
        );
    }
});

task('deploy:update_code', function () {
    writeln('Skipping deploy:update_code - deploying local build via rsync.');
});

// Hooks wiring
before('build:local', 'git:check_clean');
before('deploy:prepare', 'build:local');
before('deploy:shared', 'deploy:rsync');
before('deploy:symlink', 'deploy:before_symlink');
after('deploy:symlink', 'deploy:after_symlink');

// Cleanup on failure
after('deploy:failed', 'deploy:unlock');
