# Structure of the configuration file

## Overview

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

### name

The name of the project, it's only used for output.

### needs

List here all needed methods for that type of project. Available methods are:

  * `local` runs all commands for that configuration locally.
  * `git` for deployments via git
  * `ssh`
  * `drush` for support of drupal installations
  * `files`
  * `mattermost` for slack-notifications
  * `docker` for docker-support
  * `composer` for composer support
  * `drupalconsole` for drupal-concole support
  * `platform` for deploying to platform.sh
  * `ftp-sync` to deploy to a ftp-server

**Example for drupal 7**

```yaml
needs:
  - ssh
  - git
  - drush
  - files
```

**Example for drupal 8 composer based and dockerized**

```yaml
needs:
  - ssh
  - git
  - drush
  - composer
  - docker
  - files
```


### requires

The file-format of phabalicious changed over time. Set this to the lowest version of phabalicious which can handle the file. Should bei `2.0`

### hosts

Hosts is a list of host-definitions which contain all needed data to connect to a remote host. Here's an example

```yaml
hosts:
  exampleHost:
    host: example.host.tld
    user: example_user
    port: 2233
    type: dev
    rootFolder: /var/www/public
    gitRootFolder: /var/www
    siteFolder: /sites/default
    filesFolder: /sites/default/files
    backupFolder: /var/www/backups
    supportsInstalls: true|false
    supportsCopyFrom: true|false
    type: dev
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

```shell
phab --config=staging about
```

This will print all host configuration for the host `staging`.

#### General keys

* `type` defines the type of installation. Currently there are four types available:
    * `dev` for dev-installations, they won't backup the databases on deployment
    * `test` for test-installations, similar than `dev`, no backups on deployments
    * `stage` for staging-installations.
    * `prod` for live-installations. Some tasks can not be run on live-installations as `install` or as a target for `copyFrom`
    The main use-case is to run different scripts per type, see the `common`-section.
* `rootFolder`  the web-root-folder of the installation, typically exposed to the public.
* `needs` a list of needed methods, if not set, the globally set `needs` gets used.
* `configName` is set by phabalicious, it's the name of the configuration
* `supportsInstalls`, default is true for `types` != `prod`. If sent to true you can run the `install`-task
* `supportsCopyFrom`, default is true. If set to true, you can use that configuration as a source for the `copy-from`-task.
* `backupBeforeDeploy` is set to true for `types` `stage` and `prod`, if set to true, a backup of the DB is made before a deployment.
* `tmpFolder`, default is `/tmp`.
* `shellProvider` defines how to run a shell, where commands are executed, current values are
    * `local`: all commands are run locally
    * `ssh`: all commands are run via a ssh-shell
    * `docker-exec` all commands are run via docker-exec.
* `inheritFromBlueprint` this will apply the blueprint to the current configuration. This makes it easy to base the common configuration on a blueprint and just override some parts of it.
    * `config` this is the blueprint-configuration used as a base.
    * `variant` this is the variant to pass to the blueprint

#### Configuration for the local-method

* `shellProvider` default is `local`, see above.
* `shellExecutable` default is `/bin/bash` The executable for running a shell. Please note, that phabalicious requires a sh-compatible shell.
* `shellProviderExecutable`, the command, which will create the process for a shell, here `/bin/bash`

#### Configuration for the ssh-method

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


#### Configuration for the git-method

* `gitRootFolder`  the folder, where the git-repository lies. Defaults to `rootFolder`
* `branch` the name of the branch to use for deployments, they get usually checked out and pulled from origin.
* `ignoreSubmodules` default is false, set to false, if you don't want to update a projects' submodule on deploy.
* `gitOptions` a keyed list of options to apply to a git command. Currently only pull is supported. If your git-version does not support `--rebase` you can disable it via an empty array: `pull: []`

#### Configuration for the composer-method

* `composerRootFolder` the folder where the composer.json for the project is stored, defaults to `gitRootFolder`.

#### Configuration for the drush-method

* `siteFolder` is a drupal-specific folder, where the settings.php resides for the given installation. This allows to interact with multisites etc.
* `filesFolder` the path to the files-folder, where user-assets get stored and which should be backed up by the `files`-method
* `revertFeatures`, defaults to `True`, when set all features will be reverted when running a reset (drush only)
* `configurationManagement`, an array of configuration-labels to import on `reset`, defaults to `['staging']`. You can add command arguments for drush, e.g. `['staging', 'dev --partial']`
* `database` the database-credentials the `install`-tasks uses when installing a new installation.
    * `name` the database name
    * `host` the database host
    * `user` the database user
    * `pass` the password for the database user
    * `prefix` the optional table-prefix to use
    * `skipCreateDatabase` do not create a database when running the install task.
* `adminUser`, default is `admin`, the name of the admin-user to set when running the reset-task on `dev`-instances
* `replaceSettingsFile`, default is true. If set to false, the settings.php file will not be replaced when running an install.
* `installOptions` default is `distribution: minimal, locale: en, options: ''`. You can change the distribution to install and/ or the locale.
* `drupalVersion` set the drupal-version to use. If not set phabalicious is trying to guess it from the `needs`-configuration.
* `drushVersion` set the used crush-version, default is `8`. Drush is not 100% backwards-compatible, for phabalicious needs to know its version.
* `supportsZippedBackups` default is true, set to false, when zipped backups are not supported

#### Configuration of the ftp-sync-method

* `ftp` keeps all configuration bundled:
  * `user` the ftp-user
  * `password` the ftp password
  * `host` the ftp host
  * `port`, default is 21, the port to connect to
  * `rootFolder` the folder to copy the app into on the ftp-host.
  * `lftpOptions`, an array of options to pass when executing `lftp`

#### Configuration of the docker-method

* `docker` for all docker-relevant configuration. `configuration` and `name`/`service` are the only required keys, all other are optional and used by the docker-tasks.
    * `configuration` should contain the key of the dockerHost-configuration in `dockerHosts`
    * `name` contains the name of the docker-container. This is needed to get the IP-address of the particular docker-container when using ssh-tunnels (see above).
    * for docker-compose-base setups you can provide the `service` instead the name, phabalicious will get the docker name automatically from the service.

### Configuration of the mattermost-method

* `notifyOn`: a list of all tasks where to send a message to a Mattermost channel. Have a look at the global Mattermost-configuration-example below.

### dockerHosts

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

### common

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

### scripts

A keyed list of available scripts. This scripts may be defined globally (on the root level) or on a per host-level. The key is the name of the script and can be executed via

```shell
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

### jira

The jira-command needs some configuration. It is advised to store this configuration in your user folder (`~/.fabfile.local.yaml`) or somewhere upstream of your project folder, as it might contain sensitive information.

```
jira:
  host: <jira-host>
  user: <jira-user>
  pass: <jira-password>
```

The command will use the global `key` as project-key, you can override that via the following configuration:

```
jira:
  projectKey: <jira project-key>
```

### mattermost

Phabalicious can send notifications to a running Mattermost instance. You need to create an incoming web hook in your instance and pass this to your configuration. Here's an example

```
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

```
phab notify "hello world" --config <your-config>
```

### other

* `deploymentModule` name of the deployment-module the drush-method enables when doing a deploy
* `sqlSkipTables` a list of table-names drush should omit when doing a backup.
* `configurationManagement` a list of configuration-labels to import on `reset`. This defaults to `['staging']` and may be overridden on a per-host basis. You can add command arguments to the the configuration label.

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
	   - Drusch config-import -y dev --partial
```


## Inheritance

Sometimes it make sense to extend an existing configuration or to include configuration from other places from the file-system or from remote locations. There's a special key `inheritsFrom` which will include the yaml found at the location and merge it with the data. This is supported for entries in `hosts` and `dockerHosts` and for the fabfile itself.

If a `host`, a `dockerHost` or the fabfile itself has the key `inheritsFrom`, then the given key is used as a base-configuration. Here's a simple example:

```yaml
hosts:
  default:
    port: 22
    host: localhost
    user: default
  example1:
    inheritsFrom: default
    port: 23
  example2:
    inheritsFrom: example1
    user: example2
```

`example1` will store the merged configuration from `default` with the configuration of `example1`. `example2` is a merge of all three configurations: `example2` with `example1` with `default`.

```yaml
hosts:
  example1:
    port: 23
    host: localhost
    user: default
  example2:
    port: 23
    host: localhost
    user: example2
```

You can even reference external files to inherit from:

```yaml
hosts:
  fileExample:
    inheritsFrom: ./path/to/config/file.yaml
  httpExample:
    inheritsFrom: http://my.tld/path/to/config_file.yaml
```

This mechanism works also for the fabfile.yaml / index.yaml itself, and is not limited to one entry:

```yaml
name: test fabfile

inheritsFrom:
  - ./mbb.yaml
  - ./drupal.yaml
```

### Inherit from a blueprint

You can even inherit from a blueprint configuration for a host-config. This host-config can then override specific parts.

```
host:
  demo:
    inheritsFromBlueprint:
      config: my-blueprint-config
      varian: the-variant
```

