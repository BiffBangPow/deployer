<?php
namespace Deployer;

require 'recipe/common.php';

use Symfony\Component\Yaml\Yaml;

// Project root (not vendor dir)
$projectRoot = getcwd();

// Load deploy.yml
$configFile = $projectRoot . '/deploy.yml';
if (!file_exists($configFile)) {
    throw new \RuntimeException("Missing deploy.yml file in project root.");
}

set('ssh_multiplexing', true);

$config = Yaml::parseFile($configFile);

// Merge excludes
$exclude = $config['exclude'] ?? [];
$deployIgnoreFile = $projectRoot . '/.deployignore';

if (file_exists($deployIgnoreFile)) {
    $lines = file($deployIgnoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $deployIgnore = array_filter($lines, fn ($line) => strpos(trim($line), '#') !== 0);
    $exclude = array_merge($exclude, $deployIgnore);
}

$exclude = array_unique($exclude);

// Disable remote git clone, since we upload built files
set('repository', '');

// Basic settings from YAML config
set('application', $config['project']['name']);
set('deploy_path', $config['server']['deploy_path']);
set('shared_dirs', $config['shared_dirs'] ?? []);
set('shared_files', $config['shared_files'] ?? []);
set('writable_dirs', $config['writable'] ?? []);
set('keep_releases', 2);
set('git_tty', false);


// Define host
host($config['project']['name'])
    ->setHostname($config['server']['host'])
    ->setRemoteUser($config['server']['user'])
    ->setPort($config['server']['port'] ?? 22)
    ->setDeployPath($config['server']['deploy_path']);

/**
 * Interactive confirmation before deployment
 */
task('deploy:confirm', function () {
    $branch = trim(runLocally('git rev-parse --abbrev-ref HEAD'));
    
    writeln("<comment>You are about to deploy branch: <info>$branch</info></comment>");
    writeln("<comment>Server: <info>{{hostname}}</info></comment>");
    writeln("<comment>Deploy path: <info>{{deploy_path}}</info></comment>");
    writeln("");
    
    if (!askConfirmation('Do you wish to continue?', false)) {
        throw new \RuntimeException('Deployment cancelled by user.');
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

// Local build task
task('build:local', function () use ($config) {
    foreach ($config['build']['local'] ?? [] as $command) {
        writeln("<info>Running locally:</info> $command");
        runLocally($command);
    }
});

// Rsync task with excludes
task('deploy:rsync', function () use ($exclude, $config, $projectRoot) {
    $src  = rtrim(realpath($projectRoot), '/') . '/';
    $dest = '{{release_path}}';

    $excludeArgs = '';
    foreach ($exclude as $item) {
        $excludeArgs .= " --exclude='$item'";
    }

    $host = $config['server']['host'];
    $user = $config['server']['user'];
    $port = $config['server']['port'] ?? 22;

    runLocally(
        "rsync -az --delete $excludeArgs -e 'ssh -p $port' $src $user@$host:$dest"
    );
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

task('deploy:update_code', function () {
    writeln('Skipping deploy:update_code - deploying local build via rsync.');
});

desc('Install composer dependencies inside PHP container');
task('deploy:composer', function () use ($config) {

    $composerConfig = $config['composer'] ?? [];
    $shouldInstall  = $composerConfig['install'] ?? true;

    if (!$shouldInstall) {
        writeln('<comment>Skipping composer install (disabled in deploy.yml)</comment>');
        return;
    }

    $installDev  = $composerConfig['install_dev'] ?? false;
    $showOutput  = $composerConfig['show_output'] ?? false;

    $releasePath = get('release_path');

    $phpImage = sprintf(
        '%s.dkr.ecr.%s.amazonaws.com/php-fpm-%s',
        $config['aws']['aws_account_id'],
        $config['aws']['aws_region'],
        $config['php']['version']
    );

    $cacheDir = '/home/ubuntu/.composer/cache';
    $devFlag  = $installDev ? '' : '--no-dev';

    $exec = function (string $cmd) use ($showOutput): void {
        $output = run($cmd);
        if ($showOutput && $output !== '') {
            writeln($output);
        }
    };

    $exec("mkdir -p {$cacheDir}");
    $exec("docker pull {$phpImage}");

    $exec("
        docker run --rm \
            --user 1000:1000 \
            -v {$releasePath}:/app \
            -v {$cacheDir}:/tmp/composer-cache \
            -e COMPOSER_CACHE_DIR=/tmp/composer-cache \
            -w /app \
            {$phpImage} \
            composer install \
                {$devFlag} \
                --prefer-dist \
                --no-interaction \
                --no-progress \
                --optimize-autoloader \
                --classmap-authoritative
    ");
});


desc('Run SilverStripe dev/build inside PHP container');
task('deploy:silverstripe_build', function () use ($config) {

    $ssConfig  = $config['silverstripe'] ?? [];
    $devBuild  = $ssConfig['dev_build'] ?? false;

    if (!$devBuild) {
        writeln('<comment>Skipping SilverStripe dev/build (disabled in deploy.yml)</comment>');
        return;
    }

    $showOutput  = $ssConfig['show_output'] ?? false;

    $releasePath = get('release_path');

    $phpImage = sprintf(
        '%s.dkr.ecr.%s.amazonaws.com/php-fpm-%s',
        $config['aws']['aws_account_id'],
        $config['aws']['aws_region'],
        $config['php']['version']
    );

    $cacheDir = '/home/ubuntu/.composer/cache';

    $exec = function (string $cmd) use ($showOutput): void {
        $output = run($cmd);
        if ($showOutput && $output !== '') {
            writeln($output);
        }
    };

    $exec("
        docker run --rm \
            --user 1000:1000 \
            -v {$releasePath}:/app \
            -v {$cacheDir}:/tmp/composer-cache \
            -e COMPOSER_CACHE_DIR=/tmp/composer-cache \
            -w /app \
            {$phpImage} \
            vendor/bin/sake dev/build 'flush=1'
    ");
});


// Hooks wiring
before('deploy', 'deploy:confirm');
before('build:local', 'git:check_clean');
before('deploy:prepare', 'build:local');
before('deploy:shared', 'deploy:rsync');
before('deploy:symlink', 'deploy:before_symlink');
after('deploy:symlink', 'deploy:after_symlink');
after('deploy:shared', 'deploy:composer');
after('deploy:composer', 'deploy:silverstripe_build');

// Cleanup on failure
after('deploy:failed', 'deploy:unlock');
