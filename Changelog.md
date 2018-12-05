# Changelog

## 3.0.0

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

* new method `ftp-sync`, it's a bit special. This method creates the app into a temporary folder, and syncs it via `lftp` to a remote instance. Here's a complete example:

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
                password: <ftp-password>
                host: <ftp-host>
                port: 21
                lftpOptions:
                  - --ignoreTime
                  - --verbose=3
                  - --no-perms
           
        
  
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
