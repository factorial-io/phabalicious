---
parent: documentation
---
# Creating/ destroying an app from existing configuration

Phabalicious can create or delete a complete app with two commands:

  * `phab --config=<config> app:create --copy-from=<other-config>`
  * `phab --config=<config> app:destroy`

Both commands executes a list of stages, which can be influenced via configuration. Every method can react to the different stages and run some tasks if needed.

## the standard stages

These are the standard stages. You can override them by adding them to the global section of your fabfile:

```yaml
appStages:
  create:
    - prepareDestination
    - installCode
    - spinUp
    - installDependencies
    - install
  deploy:
    - spinUp
  destroy:
    - spinDown
    - deleteContainers
  # ftpSync and gitSync are only used for deploying artifacts.
  artifacts.ftp:
    - installCode
    - installDependencies
    - runActions
    - runDeployScript
    - syncToFtp
  artifacts.git:
    - installCode
    - installDependencies
    - getSourceCommitInfo
    - pullTargetRepository
    - runActions
    - runDeployScript
    - pushToTargetRepository
```

## Creating a new app

Run the phab-command as usual. If you want to copy from an existing installation, add the `--copy-from`-option.

``` bash
phab --config=<your-config> app:create
```

If the app is already created (there's an existing `.projectCreated`-file in the root of the installation), only the `deploy`-stage will be executed, and afterwards the deploy-task will be executed.

If the app is not created yet, all `create`-stages will be executed. If nothing went wrong you should have a running installation of the given configuration

## Destroying an app

``` bash
phab --config=<your-config> app:destroy
```

This will execute all `destroy`-stages and delete the project-folder afterwards.

## Blueprints

Both commands work best when using blueprints. This will allow to create a config and an app from a single string like a name of a feature.

Some examples:

``` bash
phab --config=some-config --blueprint=feature/<some-feature> create:app
```

Will create a new app from a blueprinted config.

## Customizing via scripts

* You can add custom scripts to your host-configuration which will be run before a stage is entered or after a stage is finished. They follow a naming-convention:
    * `appCreate<NameOfStage>Before`
    * `appCreate<NameOfStage>Finished`
    
   e.g. `appCreateInstallDependenciesBefore` or `appCreateSpinDownFinished`. All context variables are exposed to scripts as `context.data.*` or `context.results.*`
