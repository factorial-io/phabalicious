# Available commands

## list

``` bash
phab list
```

This command will list all available commands. You can get specific help for a command with the next command:

## help
``` bash
phab help:<command>
```

Will display all available arguments and options for that given `<command>` and some explanatory text.

## list:hosts

``` bash
phab list:hosts
phab list:hosts -v
```

This command will list all your hosts defined in your `hosts`-section of your `fabfile.yaml`. If you increase verbosity via `-v` phab will output the descriptions and list of public urls for every found configuration.

## list:blueprints

``` bash
Phab list:blueprints
```

This command will list all found blueprint configurations.

## about

``` bash
phab --config=<your-config> about
phab --config=<your-config> about -v
```

will display the configuration of host `<your-config>` and the corresponding dockerHost-config, if any. If you increase the verbosity `about` will also show from where the values are consumed.

## find:property

``` bash
phab --config=<your-config> find:property
```

Provides an interactive way to search for a specific property whithout knowing its exact name or location. Just type parts of the property after the prompt, phab will try to autocomplete your input. If the autocomplete does not reveal what you are looking for, just hit enter, phab will show a list of possible candidates, from which you can choose one.

You can limit the search to the current (docker-)host-configuration by prefixing your input with `host.` or `dockerHost.`.

Phab will output the current value of the searched property and from which resource it was inherited. Please note, if your searched property is a branch (means it has child values) then the inheritance source does only reflect the source of the property itself, not necessarily for its enclosed children.

## output

``` bash
Phab config=<your-config> --blueprint=<your-blueprint-config> output
```

This command will print the computed configuration from a blueprint as yams. You can copy it and paste it back to the fabfile to make it permanent.

## get:property

``` bash
phab --config=<your-config> get:property <name-of-property>
phab --config=<your-config> get:property <name-of-property> --format json
phab --config=<your-config> get:property <name-of-property> --format yaml
```

This will print the property-value to the console. Suitable if you want to use phabalicious from within other scripts. You can use the `format`-option to get the results formatted in a specific way so its easier to reuse and parse the values.

**Examples**

* `phab --config=mbb get:property host` will print the hostname of configuration `mbb`.
* `phab -cmbb get:property docker.service` will print the service of the docker-configuration of `mbb`.


## version

``` bash
phab --config=<your-config> version
```

This command will display the installed version of the code on the installation `<your-config>`.

**Available methods**:

* `git`. The command will get the installed version via `git describe`, so if you tag your source properly (.e.g. by using  git flow), you'll get a nice version-number.


## shell

```shell
phab --config=<your-config> shell
```
Will open an interactive shell with `your-config`. It will utilize all necessary steps to connect to the remote or local instance and provide a shell.

## deploy

``` bash
phab --config=<your-config> deploy
phab --config=<your-config> deploy <branch-to-deploy>
```

This command will deploy the latest code to the given installation. If the installation-type is not `dev` or `test` the `backupDB`-command is run before the deployment starts. If `<branch-to-deploy>` is stated the specific branch gets deployed.

After a successfull deployment the `reset`-command will be run.

**Available methods:**

* `git` will deploy to the latest commit for the given branch defined in the host-configuration. Submodules will be synced, and updated.
* `platform` will push the current branch to the `platform` remote, which will start the deployment-process on platform.sh
* `artifacts--ftpc` will create a copy of the app in a temporary folder and syncs this folder with the help of `lftp` with a remote ftp-server.
* `artifacts--git` will create a copy of the app in a temporary folder and push it to another git-repository

**Examples:**

* `phab --config=mbb deploy` will deploy the app via the config found in `mob`
* `phab --config=mbb deploy feature/some-feature` will deploy the branch `feature/some-feature` regardless the setting in the fabfile.

## reset

``` bash
phab config=<your-config> reset
```

This command will reset your installation

**Available methods:**

* `composer` will run `composer install` to update any dependencies before doing the reset
* `drush` will
  * set the site-uuid from fabfile.yaml (drupal 8)
  * enable a deployment-module if any stated in the fabfile.yaml
  * enable modules listed in file `modules_enabled.txt`
  * disable modules listed in file `modules_disabled.txt`
  * revert features (drupal 7) if `revertFeatures` is true / import the configuration (drupal 8),
  * run update-hooks
  * and does a cache-clear.
  * if your host-type is `dev` the password gets reset to admin/admin

**Examples:**

* `phab --config=mbb reset` will reset the installation and will not reset the password.

## install

``` bash
phab config=<your-config> install
```

This command will install a new Drupal installation with the minimal-distribution. You can install different distributions, see the examples.

**Available methods:**

*  `drush`

**Configuration:**

You can add a `installOptions`-section to your fabfile.yaml. Here's an example:

```yaml
installOptions:
  distribution: thunder
  locale: es
```

**Examples:**

* `phab --config=mbb install` will install a new Drupal installation
* `phab --config=mbb install --skip-reset=1` will install a new Drupal installation and will not run the reset-command afterwards.



## install:from

``` bash
phab --config=<your-config> install:from <source-config> <what>
```

This command will install a new installation (see the `install`-command) and afterwards will do a `copyFrom`. The `reset`-command after the `install`-command will be skipped and executed after the `copyFrom`-command. You can limit, what should be copied from: `db` or `files`. If `<what>` is omitted, then everything is copied from.

**See also:**

* install
* copyFrom

## backup

``` bash
phab --config=<your-config> backup <what>
```

This command will backup your files and database into the specified `backup`-directory. The file-names will include configuration-name, a timestamp and the git-SHA1. Every backup can be referenced by its filename (w/o extension) or, when git is abailable via the git-commit-hash.

If `<what>` is omitted, files and db gets backupped, you can limit this by providing `db` and/ or `files`.

**Available methods:**

* `git` will prepend the file-names with a hash of the current revision.
* `files` will tar all files in the `filesFolder` and save it into the `backupFolder`
* `drush` will dump the databases and save it to the `backupFolder`
* `restic` will backup the files into a restic repository instead. See [offsite backups](offsite-backups.md) for more infos.

**Configuration:**

* your host-configuration will need a `backupFolder` and a `filesFolder`

**Examples**

* `phab -cmbb backup` will backup everything
* `phab -cmbb backup files` will backup only public and private files.
* `phan -cmbb backup db` will backup the database only.


## list:backups

``` bash
phab --config=<your-config> list:backups
```

This command will print all available backups to the console.

**Available methods**

* `restic` the restic implementation will filter the list of snapshots by configuration and project name.

## restore

``` bash
phab --config=<your-config> restore <commit-hash|file-name>
```

This will restore a backup-set. A backup-set consists typically of a database-dump and a gzipped file-archive. You can a list of candidates via `phab --config=<config> list:backups`

**Available methods**

* `git` git will checkout the given hash encoded in the filename.
* `files` all files will be restored. An existing files-folder will be renamed for safety reasons.
* `mysql`, `sqlite` will import the database-dump.
* `restic` will restore the files and saved db dumps (You might need to run another restore to restore a sql-dump)

## get:backup

``` bash
phab --config:<config> get:backup <commit-hash|file-name>
```

This command will copy a remote backup-set to your local computer into the current working-directory.

**See also:**

* restore
* backup


## copy-from

``` bash
phab --config=<dest-config> copy-from <source-config> <what>
phab --config=<dest-config> copy-from <source-config> <what> --skip-reset
phab --config=<dest-config> copy-from <source-config> <what> --skip-drop-db
```

This command will copy all files via rsync from `source-config` to `dest-config` and will dump the database from `source-config` and restore it to `dest-config` when `<what>` is omitted.

After that the `reset`-command gets executed (if `--skip-reset` is not specified). This is the ideal command to copy a complete installation from one host to another.

You can limit what to copy by adding `db` or `files`  as arguments. `--skip-drop-db` will instrcut phab to not drop the db before the import.

**Available methods**

* `ssh` will create all necessary tunnels to access the hosts.
* `files` will rsync all new and changed files from source to dest
* `mysql`, `sqlite` will dump the database and restore it on the dest-host.

**Examples**

* `phab -cmbb copy-from remote-host` will copy db and files from `remote-host` to `mbb`
* `phab -cmbb copy-from remote-host db` will copy only the db  from `remote-host` to `mbb`
* `phab -cmbb copy-from remote-host db --skip-reset --skip-drop-db` will copy only the db  from `remote-host` to `mbb`, without dropping the db and without running the reset task afterwards.
* `phab -cmbb copy-from remote-host files` will copy only the files from `remote-host` to `mbb`


## drush

``` bash
phab --config=<config> drush "<drush-command>"
```

This command will execute the `drush-command` on the remote host specified in `<config>`. Please note, that you'll have to quote the drush-command when it contains spaces.

**Available methods**

* Only available for the `drush`-method

**Examples**

* `phab --config=staging drush "cc all -y"`
* `phab --config=local drush fra`


## drupal

This command will execute a drupal-console command on the remote host. Please note, that you'll have to quote the command when it contains spaces.

**Available methods**

* Only available for the `drupal`-method

**Examples**

* `phab --config=local drupal cache:rebuild`
* `phab --config=local drupal "generate:module --module helloworld"`

## platform

``` bash
phab --config=<config> platform <command>
```

Runs a specific platform-command.

## get:file

``` bash
phab --config=<config> get:file <path-to-remote-file>
```

Copy a remote file to the current working directory of your current machine.

## put:file

``` bash
phab --config=<config> put:file <path-to-local-file>
phab --config=<config> put:file <path-to-local-file> --destination <remote-destination>
```

Copy a local file to the configured root-folder of a remote configuration, if `remote-destination` is not given as an option.

**Configuration**

* this command will use the `rootFolder`-host-setting for the destination directory, if no destination is given.


## get:files-dump

``` bash
phab --config=<config> get:files-dump
```

This command will tar all files in `filesFolder` and `privateFilesFolder` and download it to the local computer.

**Available methods**

* currently only implemented for the `files`-method


## get:sql-dump

``` bash
phab --config=<config> get:sql-dump
```

Get a current dump of the remote database and copy it to the local machine into the current working directory.

**Available methods**

* `mysql`
* `sqlite`


## restore:sql-from-file

``` bash
phab --config=<config> restore:sql-from-file <path-to-local-sql-dump>
phab --config=<config> restore:sql-from-file <path-to-local-sql-dump> --skip-drop-db
```

This command will copy the dump-file `path-to-local-sql-dump` to the remote machine, drop the database (if the `--skip-drop-db`-option is not passed) and import it into the database.

**Available methods**

* `mysql`
* `sqlite`


## script

``` bash
phab --config=<config> script <script-name>
```

This command will run custom scripts on a remote machine. You can declare scripts globally or per host. If the `script-name` can't be found in the fabfile.yaml you'll get a list of all available scripts.

Additional arguments get passed to the script. See the examples.

**Examples**

* `phab --config=mbb script`. List all available scripts for configuration `mbb`
* `phab --config=mbb script behat` Run the `behat`-script
* `phab --config=mbb script behat "--name="Login feature" --format=pretty"` Run the behat-test, apply `--name` and `--format` parameters to the script

The `script`-command is rather powerful, have a read about it in the extra section.

## docker

``` bash
phab --config=<config> docker <docker-subcommand>
```

The docker command is suitable for orchestrating and administering remote instances of docker-containers. The basic setup is that your host-configuration has a `docker`-section, which contains a `configuration`-key. The `dockerHosts`-section of your fabfile.yaml has a list of commands which are executed on the "parent-host" of the configuration. Please have a look at the docker-section for more information.

Most of the time the docker-container do not have a public or known ip-address. phabalicious tries to find out the ip-address of a given instance and use that for communicating with its services.

There are three implicit subcommands available:

### copySSHKeys

``` bash
phab --config=mbb docker copySSHKeys
```

This will copy the ssh-keys into the docker-instance. You'll need to provide the paths to the files via the three configurations:
* `dockerKeyFile`, the path to the private ssh-key to use.
* `dockerAuthorizedKeysFile`, the path to the file for `authoried_keys` or a url.
* `dockerKnownHostsFile`, the path to the file for `known_hosts`
* `dockerNetRcFile`, the path to a `.netrc`-file to copy into the container. This is helpful if you are using https-repositories and want to authenticate against them.

As docker-container do not have any state, this command is used to copy any necessary ssh-configuration into the docker-container, so communication per ssh does not need any passwords.

### waitForServices

This command will try to run `supervisorctl status` in the container and  waits until all services are running. This is useful in scripts to wait for any services that need some time to start up. This command depends on `supervisorctl`.


## start-remote-access

``` bash
phab --config=<config> start-remote-access
phab --config=<config> start-remote-access \
  --port=<port> \
  --public-port=<public-port> \
  --public-ip=<public-ip>
```

This command will run a command to forward a local port to a remote port. It starts a new ssh-session which will do the forwarding. When finished, type `exit`.

**Examples**

* `phab --config=mbb start-remote-access` will forward `localhost:8888` to port `80` of the docker-container
* `phab --config=mbb start-remote-access --port=3306 --public-port=33060` will forward `localhost:33060`to port `3306`


## docker-compose

``` bash
phab --config=<config> docker-compose <docker-compose-arguments>
```

The docker-compose-command will run `docker-compose` in the mentioned environment, passing all given arguments to it. Phab will also set all environment variables beforehand.

Some examples:

```bash
phab -cmbb docker-compose ps
phab -cmbb docker-compose -- exec web ls -la
phab -clive docker-compose run e2e
```

## notify

``` bash
phab --config=<config> notify <message> <channel>
```

This command will send the notification `<message>` to Mattermosts channel `<channel>`. For a detailed description have a look into the dedicated documentation.

**Examples**

* `phab config:mbb notify "hello world" "off-topic"`: sends `hello world` to `#off-topic`

## app:scaffold

``` bash
phab app:scaffold <path/url-to-scaffold-files> \
  --name=<name of app> \
  --short-name="short name of app" \
  --output=<path to output> \
  --override="1|0"
```

This command will scaffold a new project from a set of scaffold-files. See the dedicated documentation for how to create these files.

**Examples**
* `phab app:scaffold path/to/scaffold.yml` will scaffold the app in the current folder. Phab will ask for the name and the short-name
* `phab app:scaffold path/to/scaffold.yml --name="Hello World" --short-name="HW"` will scaffold the app with name "Hello World and short-name "HW"
* `phab app:scaffold https://config.factorial.io/scaffold/drupal/d8.yml` will scaffold a Drupal app from the remote configuration.

## app:create

``` bash
phab --config=<config> app:create --config-from=<other-config>
```

This command will create a new app instance from a given config. Most useful with the usage of blueprints.

The creation is done in several steps which can be customized. If you apply the `--config-from`-option an additional copyFrom is done afterwards.

For a deeper explanation please have a look into the dedicated documentation

## app:update

``` bash
phab --config=<config> app:update
```

This command will update the code-base to the latest changes. When using the crush-method, drupal core will be updated to the latest version, if using `composer` then composer will be used to update the existing code.

**Available methods**

* `drush` will update Drupal-core, but only if `composer` is not used
* `composer` will update the codebase by running `composer update`

## app:destroy

``` bash
phab --config=<config> app:destroy
```

This command will destroy an app from a given configuration. The process has several steps. Caution: there will be no backup!

## self-update

``` bash
phab self-update
phab self-update --allow-unstable=1
```

This will download the latest version of phab and replace the current installed one with the downloaded version. If `allow-unstable` is set, the latest-dev-version will be downloaded.

## jira

``` bash
phab jira
```

This command will display your open command for that given project. For this to work, the command needs some configuration-options.

## webhook

```bash
phab webhook --config hostA
phab webhook nameOfWebhook --config hostA
phab webhook nameOfWebhook --arguments foo=bar --arguments token=my-token --config hostA
```

This command will invoke the webhook named `nameOfWebhook` and pass the optional arguments to it. Omitting the name of the webhook will list all available webhooks. Have a look into the webhook-documentation.

## npm

```bash
phab npm run build:css --config hostA
phab npm run lint --config hostB
```

This will run an npm command on the given configuration. Make sure, that your host config has `npm` as a need a `npmRootFolder` points to the folder containing package.json.

## yarn

```bash
phab npm yarn build:css --config hostA
phab npm yarn lint --config hostB
```

This will run a yarn command on the given configuration. Make sure, that your host config has `yarn` as a need a `yarnRootFolder` points to the folder containing package.json.

##variable:pull

```bash
phab -chost variable:pull path/to/yaml.file
```

This will pull all variables listed in `path/to/yaml.file` and put the values into the yaml file and store it again. (Works currently only for D7). `variable:pull` and `variable:push` are usefull to retrieve a list of variables and restore them at some point in the future.

##variable:push

```bash
phab -chost variable:push path/to/yaml.file
```

This will push all variables listed in `path/to/yaml.file` and set them on the remote instance. (Works currently only for D7)

## k8s

Runs a command against a kubernetes cluster. More info [here](/kubernetes.html)

## artisan

Runs a laravel artisan command

```bash
phab -chost artisan db:seed
phab -chost artisan migrate
```

## db:install

```bash
phab -chost db:install
```

This command will install a new database.

## db:drop

```bash
phab -chost db:install
```

This command will drop all tables in a database.

## db:shell

```bash
phab -chost db:shell
```

This command will connect the user to a running database client (e.g. mysql) so the user can run interactive commands against the db.

```bash
phab -chost db:shell:command
```

This command will print the shell commands necessary to run so that the user can connect to the db client directly.

