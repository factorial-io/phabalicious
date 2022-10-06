---
parent: documentation
---
# Structure of the configuration file

The configuration is fetched from the file `fabfile.yaml` and should have the following structure:

```yaml
name: <the project name>

needs:
  - list of methods

requires: 2.0

dockerHosts:
  docker1:
    ...

hosts:
  host1:
    ...
```

Here's the documentation of the supported and used keys:

## name

The name of the project, it's only used for output.

## needs

List here all needed methods for that type of project. Available methods are:

  * `local` runs all commands for that configuration locally.
  * `git` for deployments via git
  * `ssh`
  * `drush` for support of drupal installations
  * `files`
  * `mattermost` for slack-notifications
  * `webhook` to invoke webhooks when running specific tasks
  * `docker` for docker-support
  * `composer` for composer support
  * `drupalconsole` for drupal-concole support
  * `platform` for deploying to platform.sh
  * `artifacts--ftp` to deploy to a ftp-server
  * `artifacts--git` to deploy an artifact to a git repository
  * `yarn` to run yarn tasks when doing a deploy/reset
  * `npm` to run npm tasks when doing a deploy/reset
  * `mysql` to add support for a mysql database
  * `sqlite` to add support for a sqlite database
  * `laravel` for laravel-based applications
  * `k8s` to interact with an application hosted in a Kubernetes cluster

Needs can be set also per `host`. You can also declare `additionalNeeds` per host. If you need to "remove" a need on a per host-bases you need to completely override `needs`.

**Example for drupal 7**

```yaml
needs:
  - ssh
  - git
  - drush
  - files
```

**Example for drupal 8/9 composer based and dockerized**

```yaml
needs:
  - ssh
  - git
  - drush
  - composer
  - docker
  - files
```

**Example for a local installed laravel application with sqlite**

```yaml
needs:
  - local
  - git
  - composer
  - sqlite
  - laravel
  - files
```

## requires

The file-format of phabalicious changed over time. Set this to the lowest version of phabalicious which can handle the file. Should bei `2.0`

## hosts

Hosts is a list of host-definitions which contain all needed data to connect to a remote host. Here's an example

```yaml
hosts:
  exampleHost:
    host: example.host.tld
    user: example_user
    port: 2233
    type: dev
    info:
      description: An example of a configuration
      publicUrl: http://www.example.com
      category:
        id: 01-example
        label: An example category
    rootFolder: /var/www/public
    gitRootFolder: /var/www
    siteFolder: /sites/default
    filesFolder: /sites/default/files
    backupFolder: /var/www/backups
    supportsInstalls: true|false
    supportsCopyFrom: true|false
    branch: develop
    docker:
      ...
    database:
      ...
    scripts:
      ...
    sshTunnel:
      ..

```

You can get all host-information including the default values using the phabalicious command `about`:

``` bash
phab --config=staging about
```

This will print all host configuration for the host `staging`.

### General keys
* `hidden` hide this configuration from `list:hosts`, but can still be used
* `inheritOnly` this configuration is just for inheritance, it wont be validated, and hidden for `list:hosts`
* `info` additional human readable information for this config (supports replacemnt-patterns)
    * `description`: a human readable description outputted by `list:hosts -v`
    * `publicUrl`/`publicUrls`: One or many urls for this configuration, will be displayed to the user on certain occasions. Make sure, that the most important url is the first one
    * `category`: A category to group the output of `list:hosts` -- can be a string or:
      * `id`: the id of the category, used for sorting
      * `label`: the label of the category
* `type` defines the type of installation. Currently there are four types available:
    * `dev` for dev-installations, they won't backup the databases on deployment
    * `test` for test-installations, similar than `dev`, no backups on deployments
    * `stage` for staging-installations.
    * `prod` for live-installations. Some tasks can not be run on live-installations as `install` or as a target for `copyFrom`
    The main use-case is to run different scripts per type, see the `common`-section.
* `rootFolder`  the web-root-folder of the installation, typically exposed to the public.
* `needs` a list of needed methods, if not set, the globally set `needs` gets used.
* `additionalNeeds` a list of additional needs. The list will be appended to the global or per-host `needs`-declaration.
* `configName` is set by phabalicious, it's the name of the configuration
* `supportsInstalls`, default is true for `types` != `prod`. If sent to true you can run the `install`-task
* `supportsCopyFrom`, default is true. If set to true, you can use that configuration as a source for the `copy-from`-task.
* `backupBeforeDeploy` is set to true for `types` `stage` and `prod`, if set to true, a backup of the DB is made before a deployment.
* `tmpFolder`, default is `/tmp`.
* `environment` contains a list of environment variables to set before running any command
* `shellProvider` defines how to run a shell, where commands are executed, current values are
    * `local`: all commands are run locally
    * `ssh`: all commands are run via a ssh-shell
    * `docker-exec` all commands are run via docker-exec.
    * `docker-exec-over-ssh` all commands are run via docker-exec on a remote instance
    * `kubectl` all commands are handled via kubectl inside a dedicated pod
* `inheritFromBlueprint` this will apply the blueprint to the current configuration. This makes it easy to base the common configuration on a blueprint and just override some parts of it.
    * `config` this is the blueprint-configuration used as a base.
    * `variant` this is the variant to pass to the blueprint
* `knownHosts` a list of hosts which should be added to the known-hosts file before running a ssh-/git-command. Here's an example:
  ```yaml
  knownHosts:
    - github.com
    - source.factorial.io:2222
  ```
  They can be overridden on a per host-basis.
* `protectedProperties` a list of properties which wont be affected by a override.yaml file. See [local overrides](local-overrides.md)

### Configuration for the local-method

* `shellProvider` default is `local`, see above.
* `shellExecutable` default is `/bin/bash` The executable for running a shell. Please note, that phabalicious requires a sh-compatible shell.
* `shellProviderExecutable`, the command, which will create the process for a shell, here `/bin/bash`

### Configuration for the ssh-method

* `host`, `user`, `port` are used to connect via SSH to the remote machine. Please make sure SSH key forwarding is enabled on your installation.
* `disableKnownHosts`, default is false, set to true to ignore the known_hosts-file.
* `sshTunnel` phabalicious supports SSH-Tunnels, that means it can log in into another machine and forward the access to the real host. This is handy for dockerized installations, where the ssh-port of the docker-instance is not public. `sshTunnel` needs the following informations
    * `bridgeHost`: the host acting as a bridge.
    * `bridgeUser`: the ssh-user on the bridge-host
    * `bridgePort`: the port to connect to on the bridge-host
    * `localPort`: the local port which gets forwarded to the `destPort`. If `localPort` is omitted, the ssh-port of the host-configuration is used. If the host-configuration does not have a port-property a random port is used.
    * `destHost`: the destination host to forward to
    * `destHostFromDockerContainer`: if set, the docker's Ip address is used for destHost. This is automatically set when using a `docker`-configuration, see there.
    * `destPort`: the destination port to forward to
  * `shellProviderExecutable`, default is `/usr/bin/ssh`, the executable to establish the connection.


### Configuration for the git-method

* `gitRootFolder`  the folder, where the git-repository is located. Defaults to `rootFolder`
* `branch` the name of the branch to use for deployments, they get usually checked out and pulled from origin.
* `ignoreSubmodules` default is false, set to false, if you don't want to update a projects' submodule on deploy.
* `gitOptions` a keyed list of options to apply to a git command. Currently only pull is supported. If your git-version does not support `--rebase` you can disable it via an empty array: `pull: []`

### Configuration for the composer-method

* `composer.rootFolder` the folder where the composer.json for the project is stored, defaults to `git.rootFolder`.

### Configuration for the drush-method

* `uuid` of the drupal 8 site (D8 only) Needed for proper config imports
* `siteFolder` is a drupal-specific folder, where the settings.php resides for the given installation. This allows to interact with multisites etc.
* `filesFolder` the path to the files-folder, where user-assets get stored and which should be backed up by the `files`-method
* `revertFeatures`, defaults to `True`, when set all features will be reverted when running a reset (drush only)
* `configurationManagement`, an array of configuration-labels to import on `reset`, defaults to `['staging']`. You can add command arguments for drush, e.g.

      configurationManagement:
        sync:
          - drush cim -y
          - echo "do sth else"

* `configBaseFolder`, where all the configurations are stored, it defaults to `../config` -- the folder to the actual configuration gets computed, e.g. with the above example it would be `../config/sync`
* `forceConfigurationManagement` defaults to false. If set to true, phab will not try to autodetect if a config import is doable and force the config importsync
* `adminUser`, default is `admin`, the name of the admin-user to set when running the reset-task on `dev`-instances
* `adminPass`, default is empty, will be computed on request. You can get it via `phab -c<config> get:property adminPass` -- this can be overridden per host or globally.
* `deploymentModule` name of the deployment-module the drush-method enables when doing a deploy
* `replaceSettingsFile`, default is true. If set to false, the settings.php file will not be replaced when running an install.
* `alterSettingsFile`, default is true. If set to false, the settings.php file wont be touched by phabalicious.
* `installOptions` default is `distribution: minimal, locale: en, options: ''`. You can change the distribution to install and/ or the locale.
* `drupalVersion` set the drupal-version to use. If not set phabalicious is trying to guess it from the `needs`-configuration.
* `drushVersion` set the used crush-version, default is `8`. Drush is not 100% backwards-compatible, for phabalicious needs to know its version.
* `drushErrorHandling` defaults to `lax`. If set to `strict` phab will terminate with an error if it detects a failed drush-execution.

### Configuration of the mysql-method

* `supportsZippedBackups` default is true, set to false, when zipped backups are not supported
* `database` the database-credentials the `install`-tasks uses when installing a new installation.
    * `name` the database name
    * `host` the database host
    * `user` the database user
    * `pass` the password for the database user
    * `prefix` the optional table-prefix to use
    * `skipCreateDatabase` do not create a database when running the install task.
    * `driver`, which method should handle the database-interactions, `mysql` when using the `mysql` as a need.
* `mysqlOptions`, `mysqlDumpOptions`, `mysqlAdminOptions`, arrays with cli-options for these commands to apply when running them.

### Configuration of the sqlite-method

* `supportsZippedBackups` default is true, set to false, when zipped backups are not supported
* `database` the database-credentials the `install`-tasks uses when installing a new installation.
    * `prefix` the optional table-prefix to use
    * `skipCreateDatabase` do not create a database when running the install task.
    * `driver`, which method should handle the database-interactions, `sqlite` when using the `sqlite` as a need.

### Configuration of the laravel-method

* `laravel.rootFolder` folder where the package.json is located, defaults to the (git-) root-folder.
* `artisanTasks`:
  * `reset`: (array) A list of aritsan tasks to execute for the `reset`-command. Default is
    - `config:cache`
    - `migrate`
    - `cache:clear`
  * `install`: (array) A list of aritsan tasks to execute for the `install`-command. Default is
    - `db:wipe --force`
    - `migrate`
    - `db:seed`

### Configuration of the yarn-method

```yaml
  yarn:
    buildCommand: ...
    context: ...
    rootFolder: ...
```

* `yarn.rootFolder` folder where the package.json is located.
* `yarn.buildCommand` build-command for yarn to execute when running the install- or reset-task. If the value is an
  array, then the value is handled as a script block, so please add the name of the executable
* `yarn.context` in which context should the command be executed. Defaults to `host`. Possible values are:
  * `host` yarn is executed on the host, where the app is also running
  * `docker-host` is executed on the parent host, which controls docker execution
  * `docker-image` is executed in a dedicated docker-image, see script execution context for possible config options
  * `docker-image-on-docker-host` a mixture of `docker-image` and `docker-host` (still experimental)

### Configuration of the npm-method

```yaml
  npm:
    buildCommand: ...
    context: ...
    rootFolder: ...
```

* `npm.rootFolder` folder where the package.json is located.
* `npm.buildCommand` build-command for npm to execute when running the install- or reset-task.
* `npm.context` in which context should the command be executed. See the configuration of the yarn-method for possible options

### Configuration of the artifacts--ftp-method

* `target` keeps all configuration bundled:
  * `user` the ftp-user
  * `password` the ftp password
  * `host` the ftp host
  * `port`, default is 21, the port to connect to
  * `rootFolder` the folder to copy the app into on the ftp-host.
  * `lftpOptions`, an array of options to pass when executing `lftp`
  * `actions` a list of actions to perform. See detailed documentation for more info.


### Configuration of the artifacts--git method

* `target` contains the following options
  * `repository` the url of the target repository
  * `branch` the branch to use for commits
  * `baseBranch` if phab needs to create a new branch on the target repository, which should be the branch to branch off from.
  * `useLocalRepository` if set to true, phab will use the current directory as a source for the artifact, if set to false, phab will create a new app in a temporary folder and use that as a artifact
  * `actions` a list of actions to perform. See detailed documentation for more info.

### Configuration of the artifacts--custom method

* `target` contains the following options
  * `actions` a list of actions to perform. See detailed documentation for more info.
  * `stages` a list of custom stages to perform. A combination of these values:

    ```yaml
    - installCode
    - installDependencies
    - runActions
    - runDeployScript
    ```

### Configuration of the docker-method

* `docker` for all docker-relevant configuration. `configuration` and `name`/`service` are the only required keys, all other are optional and used by the docker-tasks.
    * `configuration` should contain the key of the dockerHost-configuration in `dockerHosts`
    * `name` contains the name of the docker-container. This is needed to get the IP-address of the particular docker-container when using ssh-tunnels (see above).
    * for docker-compose-base setups you can provide the `service` instead the name, phabalicious will get the docker name automatically from the service.

### Configuration of the mattermost-method

* `notifyOn`: a list of all tasks where to send a message to a Mattermost channel. Have a look at the global Mattermost-configuration-example below.

### Configuration of the k8s-method

The Kubernetes integration is documented [here](kubernetes.md).

## dockerHosts

`dockerHosts` is similar structured as the `hosts`-entry. It's a keyed lists of hosts containing all necessary information to create a ssh-connection to the host, controlling the docker-instances, and a list of tasks, the user might call via the `docker`-command. See the `docker`-entry for a more birds-eye-view of the concepts.

Here's an example `dockerHosts`-entry:

```yaml
dockerHosts:
  mbb:
    runLocally: false
    host: multibasebox.dev
    user: vagrant
    password: vagrant
    port: 22
    rootFolder: /vagrant
    environment:
      VHOST: %host.host%
      WEBROOT: %host.rootFolder%
    tasks:
      logs:
        - docker logs %host.docker.name%
```

Here's a list of all possible entries of a dockerHosts-entry:
* `shellProvider`, the shell-provider to use, currently `local` or `ssh`.
* `runLocally`: if set to true, the `local`-shell-provider will be used.
* `host`, `user` and `port`: when using the `ssh`-shell-provicer.
* `environment` a keyed list of environment-variables to set, when running one of the tasks. The replacement-patterns of `scripts` are supported, see there for more information.
* `tasks` a keyed list of commands to run for a given docker-subtask (similar to `scripts`). Note: these commands are running on the docker-host, not on the host. All replacement-patterns do work, and you can call even other tasks via `execute(<task>, <subtask>)` e.g. `execute(docker, stop)` See the `scripts`-section for more info.

You can use `inheritsFrom` to base your configuration on an existing one. You can add any configuration you may need and reference to that information from within your tasks via the replacement-pattern `%dockerHost.keyName%` e.g. `%dockerHost.host%`.

You can reference a specific docker-host-configuration from your host-configuration via

```yaml
hosts:
  test:
    docker:
      configuration: mbb
```

## common

common contains a list of commands, keyed by task and type which gets executed when the task is executed.

Example:
```yaml
common:
  reset:
    dev:
      - echo "running reset on a dev-instance"
    stage:
      - echo "running reset on a stage-instance"
    prod:
      - echo "running reset on a prod-instance"
  deployPrepare:
    dev:
      - echo "preparing deploy on a dev instance"
  deploy:
    dev:
      - echo "deploying on a dev instance"
  deployFinished:
    dev:
      - echo "finished deployment on a dev instance"
```

The first key is the task-name (`reset`, `deploy`, ...), the second key is the type of the installation (`dev`, `stage`, `prod`, `test`). Every task is prepended by a prepare-stage and appended by a finished-stage, so you can call scripts before and after an actual task. You can even run other scripts via the `execute`-command, see the `scripts`-section.

## scripts

A keyed list of available scripts. This scripts may be defined globally (on the root level) or on a per host-level. The key is the name of the script and can be executed via

``` bash
phab --config=<configuration> script <key>
```

A script consists of an array of commands which gets executed sequentially.

An example:

```yaml
scripts:
  test:
    - echo "Running script test"
  test2:
    - echo "Running script test2 on %host.config_name%
    - execute(script, test)
```

Scripts can be defined on a global level, but also on a per host-level.

You can declare default-values for arguments via a slightly modified syntax:

```yaml
scripts:
  defaultArgumentTest:
    defaults:
      name: Bob
    script:
      - echo "Hello %arguments.name%"
```

Running the script via `phab config:mbb script:defaultArgumentTest,name="Julia"` will show `Hello Julia`. Running `phab config:mbb script:defaultArgumentTest` will show `Hello Bob`.

For more information see the main scripts section below.

## jira

The jira-command needs some configuration. It is advised to store this configuration in your user folder (`~/.fabfile.local.yaml`) or somewhere upstream of your project folder, as it might contain sensitive information.

```yaml
jira:
  host: <jira-host>
  user: <jira-user>
  pass: <jira-password>
```

The command will use the global `key` as project-key, you can override that via the following configuration:

```yaml
jira:
  projectKey: <jira project-key>
```

## mattermost

Phabalicious can send notifications to a running Mattermost instance. You need to create an incoming webhook in your instance and pass this to your configuration. Here's an example

```yaml
mattermost:
  username: phabalicious
  webhook: https://chat.your.server.tld/hooks/...
  Channel: "my-channel"

hosts:
  test:
    needs:
      - mattermost
    notifyOn:
      - deploy
      - reset
```

* `mattermost` contains all global mattermost config.
    * `username` the username to post messages as
    * `webhook` the address of the web-hook
    * `channel` the channel to post the message to
* `notifyOn` is a list of tasks which should send a notification

You can test the Mattermost config via

```bash
phab notify "hello world" --config <your-config>
```
## restic

Provides integration with the restic command. Restic is used as a backup command if configured correctly and enabled via `needs`. Restic will be executed in the host-context, that means phab will create a shell for the given host-config and executes the restic-command there. It will try to install restic if it can't find an executable.

You can configure how restic is executed by adding the following snippet either in the global scope, or in a host-configuration. Use a secret to prevent storing sensitive data in the fabfile.

```yaml
secrets:
  restic-password:
    question: Password for offsite restic-repository?
    onePasswordId: xxx
    onePasswordVaultId: xxx
    tokenId: default

restic:
  # defaults:
  allowInstallation: 1
  downloadUrl: https://github.com/restic/restic/releases/download/v0.12.0/restic_0.12.0_linux_amd64.bz2
  options:
    - --verbose
  # required:
  repository: <url-to-your-repo>
  environment:
    RESTIC_PASSWORD: "%secret.restic-password%"
```

Phab will include the repository, any options ir environment variables when executing restic, so no need to add them by yourself. All command line arguments will be passed to restic.


## webhooks

Phabalicious provides a command to invoke webhooks from the command line, but also integrates invoking webhooks when running a specific task or as a callback for scripts.

Webhooks are declared in the global namespace:

```yaml
webhooks:
  nameOfWebhook:
    url: <url-of-webhook>
    method: <get|post|delete>
    format: <json|form_params>
    payload:
      prop1: value1
      prop2: value2
    # a list of options passed directly to guzzle.
    options: []
  ...
```

The data in payload get submitted depending on the chosen format, e.g. as JSON or as form-values. You can use the replacement patterns for entries in the payload known similar to scripts, e.g.

```yaml
webhooks:
  demoWebhook:
    url: <url>
    method: get
    payload:
      branch: "%host.branch%"
      token: "%settings.token%"
      someOtherValue: "%arguments.foo%"
```

`webhooks` has a special entry called `defaults` where you can add common defaults for all webhook invokations like special headers, etc

The current defaults are:
```yaml
webhooks:
  defaults:
    format: json
    options:
      headers:
        User-Agent: phabalicous
        Accept: application/json

```

To invoke a webhook from a script-section, use the built-in function `webhook(name-of-webhook, arguments)`:

```yaml
host:
  demo:
    needs:
      - git
      - artifacts--git
      - webhook
    deployFinished:
      - echo "Invoking webhook ..."
      - webhook(myWebhook, foo=bar)
```

Alternatively you can use the `webhooks`-keyword to provide a mapping of task and webhook to invoke:

```yaml
host:
  demo:
    needs:
      - git
      - artifacts--git
      - webhook
    webhooks:
      deployPrepare: myWebhook1
      deployFinished: myWebhook2
```
## `executables`

Phab supports that host-configurations can override the path to specific executables. These overrides can be done on a global level (e.g. the root level of the fabfile) and/ or on a per host-basis. Phab detects an executable name by the hash-bang notation (`#!`) or when `$$` prefixes the executable-name. Here are some examples:

```shell
$$echo "hello world"
#!git pull origin main
#!tar -xzvf files.tgz
#!cat dump.sql | #!mysql -u root -p root db
```

Phab will lookup the actual executable from the global `executables`-setting or from the actual host-configuration, here's an example fabfile:

```yaml
executables:
  echo: echo
  git: /usr/local/bin/git --quiet
  tar: /usr/bin/tar

hosts:
  a:
    executables:
      mysql: /usr/local/bin/mariadb
```

As you can see, this allows you to specify the exact path to the executable without relying on rc scripts, env-vars, etc. It allows you also to use completely different executables in certain scenarios, e.g. some hosting providers do have dedicated php executables per version, e.g. php74 php8, etc.

You can use this mechanism also in your scripts, by using the above mentioned prefixes, e.g.

```yaml
executables:
  git: /usr/local/bin/git
  chmod: /bin/true
  php: /usr/local/bin/php74
  drush: /usr/local/bin/php80 /var/www/vendor/bin/drush
script:
  git-pull:
    # Please note, that the next line is quoted, so yaml wont
    # interpret the hash as a comment. That's actually also
    # the reason, why there is the second prefix $$
    - "#!git pull origin main"
    - $$chmod -R 777 .
```

This mechanism allows you to solve some complicated setup stuff (e.g. like disabling the chmod in the above example by replacing it with `/bin/true` or by specifying a dedicated php executable for drush) by keeping your script code relatively sane, which allows that scripts can be reused more often.

## other

* `sqlSkipTables` a list of table-names drush should omit when doing a backup.
* `configurationManagement` a list of configuration-labels to import on `reset`. This defaults to `['sync']` and may be overridden on a per-host basis. You can add command arguments to the the configuration label.
* `rsyncOptions` additional options to pass to rsync when doing a copyFrom.

Example:
```yaml
deploymentModule: my_deployment_module
usePty: false
useShell: false
gitOptions:
  pull:
    - --rebase
    - --quiet
sqlSkipTables:
  - cache
  - watchdog
  - session
configurationManagement:
   staging:
     - drush config-import -y staging
   dev:
     - drush config-import -y dev --partial
rsyncOptions:
  - --delete
```


