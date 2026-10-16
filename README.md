# BiffBangPow Deployer

BiffBangPow's compatibility package for [DorsetDigital Silverstripe Deploy](https://github.com/DorsetDigital/silverstripe-deploy).

The deployment functionality is maintained in the DorsetDigital package. This package allows existing BiffBangPow projects to retain their Composer dependency while using the new deployment system.

## Installation

Install the package in your Silverstripe project:

```bash
composer require --dev biffbangpow/deployer
```

## Initial setup

From the project root, run:

```bash
vendor/bin/ssdeploy init
```

This creates the following files if they don't already exist:

- `deploy.yml` — deployment configuration
- `.deployignore` — files excluded from deployment
- `deploy` — executable deployment wrapper

Edit `deploy.yml` to configure the deployment server, target sites and build settings.

## Deploying

Deploy all configured sites:

```bash
./deploy
```

Deploy a specific site:

```bash
./deploy website
```

Deploy selected sites:

```bash
./deploy website another-site
```

Deploy to production when using stage-specific paths:

```bash
./deploy website --stage=prod
```

Deployments run with a maximum of two concurrent workers by default. This can be changed in `deploy.yml`.

## Useful commands

| Command | Purpose |
|---|---|
| `vendor/bin/ssdeploy check` | Validate configuration and local prerequisites |
| `vendor/bin/ssdeploy sites` | List configured sites |
| `vendor/bin/ssdeploy test-connection` | Test SSH/SSM connectivity |
| `./deploy --dry-run` | Preview deployment targets |
| `./deploy website --verbose` | Deploy with full console output |
| `vendor/bin/ssdeploy unlock website` | Remove a stale deployment lock |
| `vendor/bin/ssdeploy rollback website` | Roll back a deployment |

Only unlock a site once you've confirmed its previous deployment has stopped.

## Logging

Deployment task progress is shown by default.

Detailed Composer, Silverstripe and Webpack output can be enabled individually using their `show_output` settings in `deploy.yml`.

Failed deployments generate diagnostic logs in `.deploy-logs/` within the project directory.

## Documentation

For full configuration details, AWS SSM setup, deployment options and troubleshooting, refer to the [Silverstripe Deploy documentation](https://github.com/DorsetDigital/silverstripe-deploy#readme).

**Migrating an existing project?** The new deployment system uses `deploy.yml` and `ssdeploy`. Existing project deployment scripts and configuration may need updating.