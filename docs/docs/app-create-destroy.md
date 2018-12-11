# Creating/ destroying an app from existing configuration

Phabalicious can create or delete a complete app with two commands:

  * `phab --config=<config> app:create --copy-from=<other-config>`
  * `phab --config=<config> app:destroy`

Both commands executes a list of stages, which can be influenced via configuration

## the standard stages

These are the standard stages. You can override them by adding them to the global section of your fabfile:

```yaml  
appStages:
  create:
    - stage: installCode
    - stage: spinUp
    - stage: installDependencies
    - stage: install
  createCode:
    - stage: installCode
    - stage: installDependencies
  deploy:
    - stage: spinUp
  destroy:
    - stage: spinDown
    - stage: deleteContainers
```

`createCode` is only used by the `ftp-sync`-method, to create a complete code version of an app.

## Creating a new app

Run the phab-command as usual. If you want to copy from an existing installation, add the `--copy-from`-option.

```shell
phab --config=<your-config> app:create
```

If the app is already created (there's an existing `.projectCreated`-file in the root of the installation), only the `deploy`-stage will be executed, and afterwards the deploy-task will be executed.

If the app is not created yet, all `create`-stages will be executed. If nothing went wrong you should have a running installation of the given configuration

## Destroying an app

```shell
phab --config=<your-config> app:destroy
```

This will execute all `destroy`-stages and delete the project-folder afterwards.

## Blueprints

Both commands work best when using blueprints. This will allow to create a config and an app from a single string like a name of a feature.

Some examples:

```shell
phab --config=some-config --blueprint=feature/<some-feature> create:app
```

Will create a new app from a blueprinted config.