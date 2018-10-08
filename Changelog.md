# Changelog

## 3.0.0

### New

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

### Changed

* Phabalicious is a symfony console app and uses more unix-style arguments and options. E.g. instead of `config:<name-of-config>` use `--config=<name-of-config>`
* `docker:startRemoteAccess` is now the command `start-remote-access` as it makes more sense.
* the `list`-command needed to be renamed to `list:hosts`.

### Deprecated

* script-function `fail_on_error` is deprecated, use `breakOnFirstError(<bool>)`
* `runLocally` is deprecated, add a new need `local` to the lists of needs.
* `strictHostKeyChecking` is deprecated, use `disableKnownHosts` instead. 
