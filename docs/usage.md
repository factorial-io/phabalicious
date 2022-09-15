---
parent: documentation
---
# Running phabalicious

To execute a command with the help of phabalicious, just

``` bash
cd <your-project-folder>
phab --config=<your-config-key> <command>
```

This will read your fabfile.yaml, look for `<your-config-key>` in the host-section and run the command `<command>`

Add `-v` ... `-vvv` to increase the verbosity of phabalicious. You can get a list of all available options by running

```shell
phab help <command>
```


## List of available commands

You can get a list of [available commands](./commands.md) with

``` bash
phab list
```

## Command line options

### -v/ -vv/ -vvv/ -vvvv

Setting this option will increase the verbosity of phabalicious. Without this settings you'll get only warnings and errors and some informational stuff. If you encounter a problem try increasing the verbosity-level.

### --config

``` bash
phab --config=<your-config>
```

Most of the phabalicious commands need the option `config`. Setting the option will lookup `<your-config>` in the `hosts`-section of your `fabfile.yaml` and the data will be used to run your command with the correct environment.

### --offline

``` bash
phab --offline=1 --config=<your-config> <command>
```

This command will disable remote configuration files. As phabalicious keeps copies of remote configuration-files in `~/.phabalicious` it will try to load the configuration-file from there.

### --fabfile
``` bash
Phab --fabfile=<path-to-your-fabfile ...
```

This will try to load the `fabfile.yaml` from a different location: `path-to-your-fabfile`

### --blueprint

``` bash
phab --config=<your-config> --blueprint=<branch-name> <command>
```

`blueprint` will try to load a blueprint-template from the fabfile.yaml and apply the input given as `<branch-name>` to the template. This is helpful if you want to create/ use a new configuration which has some dynamic parts like the name of the database, the name of the docker-container, etc.

The command will look first in the host-config for the property `blueprint`, afterwards in the dockerHost-configuration `<config-name>` and eventually in the global namespace. If you want to print the generated configuration as yaml, then use the `output`-command. The computed configuration is used as the current configuration, that means, you can run other commands against the generated configuration.

**Available replacement-patterns** and what they do.

_Input is `feature/XY-123-my_Branch-name`, the project-name is `Example project`_

|  Replacement Pattern                    | value                         |
|-----------------------------------------|-------------------------------|
| **%slug.with-hyphens.without-feature%** | xy-123-my-branch-name         |
| **%slug.with-hyphens%**                 | feature-xy-123-my-branch-name |
| **%project-slug.with-hypens%**          | example-project               |
| **%slug%**                              | featurexy123mybranchname      |
| **%project-slug%**                      | exampleproject                |
| **%project-identifier%**                | Example project               |
| **%identifier%**                        | feature/XY-123-my_Branch-name |
| **%slug.without-feature%**              | xy123mybranchname             |


Here's an example blueprint:

```yaml
blueprint:
  inheritsFrom: http://some.host/data.yaml
  configName: '%project-slug%-%slug.with-hyphens.without-feature%.some.host.tld'
  branch: '%identifier%'
  database:
    name: '%slug.without-feature%_mysql'
  docker:
    projectFolder: '%project-slug%--%slug.with-hyphens.without-feature%'
    vhost: '%project-slug%-%slug.without-feature%.some.host.tld'
    name: '%project-slug%%slug.without-feature%_web_1'
```

And the output of `phab --blueprint=feature/XY-123-my_Branch-name --config=<config-name> output` is

```yaml
  phbackend-xy-123-my-branch-name.some.host.tld:
    branch: feature/XY-123-my_Branch-name
    configName: phbackend-xy-123-my-branch-name.some.host.tld
    database:
      name: xy123mybranchname_mysql
    docker:
      name: phbackendxy123mybranchname_web_1
      projectFolder: phbackend--xy-123-my-branch-name
      vhost: phbackend-xy123mybranchname.some.host.tld
    inheritsFrom: http://some.host/data.yaml
```

**Note**

You can create new configurations via the global `blueprints`-settings:

```
blueprints:
  - configName: mbb
    variants:
      - de
      - en
      - it
      - fr
```

will create 4 new configurations using the blueprint-config `mbb`.

**Note**

You can even create a new host-config from a blueprint and override some of its setting:

```
hosts:
  myHost:
    inheritFromBluePrint:
      config: my-blueprint-config
      variant: my-variable
    otherSettings...
```

### --variants

When using `blueprint`, you can run one command on multiple variants Just pass the `--variants`-flag with the wanted variants.

```
phab --config=my-blueprinted-config --variants=all  about
phab --config=my-blueprinted-config --variants=de,it,fr  about

```

This will prompt you with a list of commands which phab will run and ask for confirmation.

### -s / --set

You can override existing configuration using the dot notation and pass arbitrary values to phabalicious. Currently this is supported for host configs and dockerhost configs.

```yaml
hosts:
  example:
    host: example.test
    port: 2222
    ...
```

To set a value from command line just pass it via the `--set`-option:

```bash
phab -cexample about --set host.host=overriden.test --set host.port=22
```

### -a / --arguments

Pass arbitrary arguments to scripts or other parts. Passed arguments can be consumed by scripts using `%arguments.<name>%` syntax. An example:

```
scripts:
  test-arguments:
    - echo %arguments.message%
```

```bash
phab -c<yourconfig> script test-arguments --arguments message="hello world"
```

### --offline

Prevent loading of additional data from remote. Helpful when you do not have an internet connection.

### --skip-cache

Phab caches remote files in `~/.phabalicious` and will use the cached version if it not older than an hour. If you think you get outdated information, pass this flag. It makes sure, that all remote data is read from remote and updates the file-cache.


## Used environment variables:

* `PHABALICIOUS_EXECUTABLE` allows to override the phab executable when using variants
* `PHABALICIOUS_DEFAULT_CONFIG` sets the default config name to use, when no config name was given via the `--config` flag
* `PHABALICIOUS_FORCE_GIT_ARTIFACT_DEPLOYMENT` forces the git artifact deployment.
* `PHAB_OP_FILE_PATH` file path to [1password](./passwords.md) executable.
* `PHAB_OP_JWT_TOKEN__<TOKEN_ID>` token for [1password](./password.md) connect
