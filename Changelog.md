# Changelog

## 3.0.4 / 2019-03-18

### New

  * Support for variants and parallel execution for a set of variants

### Fixed

  * Document mattermost integration, fixes #29
  * Fix broken shell autocomplete
  * Limit output when using phab with pipes
  * Include jump-host when running ssh:command if needed (fixes #36)
  * Display destination for put:file (Fixes #37)

## 3.0.3 / 2019-03-07

### Fixed

  * Use progressbar when scaffolding more then 3 asset-files
  * FIx a regression for task-specific scripts. (Fixes #31)
  * Make sure, that task-specific scripts are run. (Fixes #31)
  * Add a notification before starting a db dump (Fixes #30 and #33)
  * If no unstable update is available, try the stable branch (Fixes #34)

## 3.0.2 / 2019-03-01

### Fixed

  * Fix scaffolding of empty files via http
  * Add support to limit files handled by twig by an extension as third parameter to copy_assets
  * Add support for a dedicated projectFolder, add support for dependent variables, so you can compose variables from other variables
  * strip first subfolder from filenames to copy when running app:scaffold, keep folder hierarchy for subsequents folders
  * Refactor TaskContext::getStyle to TaskContext::io for clearer code
  * Fix a bug on copyFrom for specific multi.site setups
  * Fix bug when running app:scaffold where stages do not fire existing docker-tasks

## 3.0.1 / 2019-02-25

### Fixed

  * Fix a bug in docker:getIpAddress when using the service keyword and the container is not running.

### New

  * Add a new stage `prepareDestination` for `app:create`

## 3.0.0 / 2019-02-14

### Fixed

  * Increase timeout for non-interactive processes.
  * `restore:sql-from-file`: Run a preparation method so tunnels are in place before running the actual scp
  * `copy-from files`: Fix for "too many arguments" error message of rsync

## 3.0.0-beta.6 / 2019-02-08

### Fixed

  * .netrc is optional, show a warning if not found, instead of breaking the flow (Fixes #27)

## 3.0.0-beta.5 / 2019-02-05

### Fixed

  * fixes a bug resolving remote assets for app:scaffold

## 3.0.0-beta.4 / 2019-01-28

### Fixed

  * Exit early after app-update to prevent php exception because of missing files. (Fixes #24)
  * Make update-check more robust

## 3.0.0-beta.3 / 2019-01-26

### New

  * Add transform to questions, update documentation, fix tests
  * Refactor questions in `app:scaffold` questions are now part of the scaffold.yml
  * Add support for copying a .netrc file to the docker container
  * New command `jira`which will show all open tickets for the given project and user. (#22)

## 3.0.0-beta.2 / 2019-01-19

### New

  * Add support for .fabfile.local.yaml in user-folder
  * Show a message when a new version of phabalicious is available.

### Fixed

  * Documentation for the new jira-command (#22)
  * Remove trailing semicolon (Fixes #23)
  * Report a proper error message when handling modules_enabled.txt or modules_disabled.txt is failing
  * Fix shell-completion

## 3.0.0-beta.1 / 2019-01-10

### fixed

  * Fix logic error in InstallCommand, add testcases (Fixes #21)
  * Wrap interactive shell with bash only if we have a command to execute
  * Try up to 5 parent folders to find suitable fabfiles (Fixes #18)
  * Use paralell uploads for ftp-deployments
  * Use a login-shell when running drush or drupalconsole interactively. (Fixes #20)
  * Add autocompletion for `install-from`

## 3.0.0-alpha.8 / 2018-12-20

### fixed

  * Call overridden methods only one time, add missin reset-implementation to platform-method (fixes #14)
  * Increase verbosity of app:scaffold
  * Add missing twig-dependency to phar-creation (fixes #17)
  * Fix handling of relative paths in app:scaffold (Fixes #16)
  * Fix parsing of multiple IPs from a docker-container (Fixes #15)
  * Pass available arguments to autocompletion for command copy-from (Fixes #13)
  * Run drupalconsole in an interactive shell

## 3.0.0-alpha.7 / 2018-12-14

### fixed

  * Handle app-options correctly when doing shell-autocompletion (Fixes #12)
  * Silent warnings when doing autocompletion (fixes #11)
  * Better command output for start-remote-access (fixes #10)
  * Throw exception if docker task fails
  * Fix command output parsing
  * Source /etc/profile and .bashrc
  * Better defaults for lftp

## 3.0.0-alpha.6 / 2018-12-11

### fixed

  * Some bugfixes for ftp-deployments
  * Nicer output
  * Add docs for shell-autcompletion
  * Fix fish autocompletion (sort of)
  * Set version number, when not bundling as phar

## 3.0.0-alpha.5 / 2018-12-08

### fixed

  * Use real version number
  * Fix phar-build

## 3.0.0-alpha.4 / 2018-12-08

### new

  * New command `self-update`, will download and install the latest available version
  * New method `ftp-sync` to deploy code-bases to a remote ftp-instance
  * Introduction of a password-manager for retrieving passwords from the user or a special file

### changed

  * Switch to box for building phars

### fixed

  * Do not run empty script lines (Fixes #8)
  * Set folder for script-phase
  * Set rootFolder fot task-specific scripts
  * Support legacy host-types

## 3.0.0 develop

Fabalicious is now rewritten in PHP, so we changed the name to make the separation more clear. Phabalicious is now a symfony console app and uses a more unix-style approach to arguments and options. E.g. instead of `config:<name-of-config>` use `--config=<name-of-config>`

### Why the rewrite

Python on Mac OS X is hard, multiple versions, multiple locations etc. Every machine needed some magic hands to get fabalicious working on it. Fabalicious itself is written in python 2.x, but the world is moving on to python 3. Fabric, the underlying lib we used for fabalicious is also moving forward to version 2 which is not backwards compatible yet with fabric 1. On the other side we are now maintaining more and more containerized setups where you do not need ssh to run commands in. A popular example is docker and its whole universe. Fabric couldn't help us here, and fabric is moving into a different direction.

And as a specialized Drupal boutique we write PHP all day long. To make it easier for our team to improve the toolset by ourselves and get help from the rest of the community, using PHP/ Symfony as a base for the rewrite was a no-brainer.

Why not use existing tools, like [robo](https://robo.li/), [deployer](https://deployer.org/) or other tools? These tools are valuable instruments in our tool-belt, but none of them fit our requirements completely. We see phabalicious as a meta-tool integrating with all of them in nice and easy way. We need a lot of flexibility as we need to support a lot of different tech- and hosting-stacks, so we decided to port fabalicious to phabalicious.

There's a lot of change going on here, but the structure of the fabfile.yaml is still the same.

### Changed command line options and arguments

As fabric (the underlying lib we used for fabalicious) is quite different to symfony console apps there are more subtle changes. For example you can invoke only one task per run. With fabalicious it was easy to run multiple commands:

```shell
fab config:mbb docker:run reset ssh
```

This is not possible anymore with phabalicious, you need to run the commands in sequence. If you need that on a regular basis, a `script` might be a good workaround.

Most notably the handling of arguments and options has changed a lot. Fabric gave us a lot of flexibility here, symfony is more strict, but has on the other side some advantages for example self-documenting all possible arguments and options for a given task.


#### Some examples

| Old syntax | New syntax |
|---|---|
| `fab config:mbb about` | `phab about --config mbb` |
| `fab config:mbb about` | `phab --config=mbb about` |
| `fab config:mbb blueprint:de deploy` | `phab deploy --config mbb --blueprint de` |
| `fab config:mbb blueprint:de deploy` | `phab --config=mbb --blueprint=de mbb` |

### New features

* Introduction of ShellProviders, they will provide a shell, to run scripts into. Currently implemented are

    * `local`, run the shell-commands on your local host
    * `ssh`, runs the shell-commands on a remote host.

    Every shell-provider can have different required options. Currently add the needed shell-provider to your list of needs, e.g.

          needs:
            - local
            - git
            - drush

* new global settings `disableScripts` which will not add the `script`-method to the needs.
* there's a new command to list all blueprints: `list:blueprints`
* new shell-provider `dockerExec` which will start a shell with the help of `docker exec` instead of ssh.
* new config-option `shellProvider`, where you can override the shell-provider to your liking.

        hosts:
          mbb:
            shellProvider: docker-exec
* You can get help for a specific task via `phab help <task>`. It will show all possible options and some help.
* docker-compose version 23 changes the schema how names of docker-containers are constructed. To support this change we can now declare the needed service to compute the correct container-name from.

        hosts:
          testHost:
            docker:
              service: web
   The `name` will be discarded, if a `service`-entry is set.

* new method `ftp-sync`, it's a bit special. This method creates the app into a temporary folder, and syncs it via `lftp` to a remote instance. Here's a complete example (most of them are provided via sensible defaults):

        excludeFiles:
          ftp-sync:
            - .git/
            - node_modules
        hosts:
          ftpSyncSample:
            needs:
              - git
              - ftp-sync
              - local
            ftp:
              user: <ftp-user>
              password: <ftp-password> #
              host: <ftp-host>
              port: 21
              lftpOptions:
                - --ignoreTime
                - --verbose=3
                - --no-perms

    You can add your password to the file `.phabalicious-credentials` (see passwords.md) so phabalicious pick it up.


### Changed

* `docker:startRemoteAccess` is now the task `start-remote-access` as it makes more sense.
* the `list`-task needed to be renamed to `list:hosts`.
* the `--list` task (which was built into fabric) is now `list`.
* the `offline`-task got removed, instead add the `-offline`-option and set it to 1, e.g.

      phab --offline=1 --config=mbb about

* the task `logLevel` is replaced by the builtin `-v`-option
* autocompletion works now differently than before, but now bash and zsh are supported. Please have a look into the documentation how to install it.

  * for fish-shells

        phab _completion --generate-hook --shell-type fish | source

  * for zsh/bash-shells

        source <(phab _completion --generate-hook)

* `listBackups` got renamed to `list:backups`
* `backupDB` and `backupFiles` got removed, use `phab backup files` or `phab backup db`, the same mechanism works for restoring a backup.
* `getFile` got renamed to `get:file`
* `putFile` got renamed to `put:file`
* `getBackup` got renamed to `get:backup`
* `getFilesDump` got renamed to `get:files-backup`
* `getProperty` got renamed to `get:property`
* `getSQLDump` got renamed to `get:sql-dump`
* `restoreSQLFromFile` got renamed to `restore:sql-from-file`
* `copyDBFrom` got renamed to `copy-from <config> db`
* `copyFilesFrom` got renamed to `copy-from <config> files`
* `installFrom` got renamed to `install:from`

### Deprecated

* script-function `fail_on_error` is deprecated, use `breakOnFirstError(<bool>)`
* `runLocally` is deprecated, add a new need `local` to the lists of needs.
* `strictHostKeyChecking` is deprecated, use `disableKnownHosts` instead.
* `getProperty` is deprecated and got renamed to `get-property`
* `ssh` is deprecated and got renamed to `shell` as some implementations might not use ssh.
* `sshCommand` is deprecated and got renamed to `shell:command` and will return the command to run a shell with the given configuration
* the needs `drush7`, `drush8` and `drush9` are deprecated, use the need `drush` and the newly introduced options `drupalVersion` and `drushVersion` instead,
* the `slack`-configuration got removed and got replaced by a general notification solution, currently only with a mattermost implementation.

