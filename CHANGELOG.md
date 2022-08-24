# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

### [3.8.4](https://github.com/factorial-io/phabalicious/compare/3.8.3...3.8.4) (2022-08-24)


### Bug Fixes

* Show deprecation message for deprecated *RunContext value dockerHost ([7199452](https://github.com/factorial-io/phabalicious/commit/7199452c167f770a887cf0931efec33896ff361e))

### [3.8.3](https://github.com/factorial-io/phabalicious/compare/3.8.2...3.8.3) (2022-08-23)

### [3.8.2](https://github.com/factorial-io/phabalicious/compare/3.8.1...3.8.2) (2022-08-23)

### [3.8.1](https://github.com/factorial-io/phabalicious/compare/3.8.0...3.8.1) (2022-08-23)


### Bug Fixes

* Fix edge-cases where 1password cli is not installed ([35830b4](https://github.com/factorial-io/phabalicious/commit/35830b48a4cfdd51eadc288883465f847294fc54))
* shell-completion runs without warnings on PHP 8.1 ([452d4bc](https://github.com/factorial-io/phabalicious/commit/452d4bc544c56518fdd5be1aef1cce097997f214))

## [3.8.0](https://github.com/factorial-io/phabalicious/compare/3.7.18...3.8.0) (2022-08-22)

* Official release of 3.8.0 beta, an overview of the changes can be found at https://docs.phab.io/blog/whats-new-in-phab-3-8.html
* See the dedicated release notes of the beta version for detailed infos

### [3.7.18](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.16...3.7.18) (2022-08-16)


### Bug Fixes

* Fix updates when requesting beta versions ([9a5c269](https://github.com/factorial-io/phabalicious/commit/9a5c26938a9251efb601a31c76f64f2ee248b610))

## [3.8.0-beta.17](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.16...3.8.0-beta.17) (2022-08-19)


### Features

* Allow methods to declare deprecation mappings ([ab37bd4](https://github.com/factorial-io/phabalicious/commit/ab37bd4216147bfe0a1fce078db804fde183d841))
* Make runScriptImpl public ([4c15d5e](https://github.com/factorial-io/phabalicious/commit/4c15d5ec02c70f393eefca1dc341e737919be231))
* Methods based on RunCommandBaseMethod can use docker-image as run context ([91b59b6](https://github.com/factorial-io/phabalicious/commit/91b59b6ebe9528fd4f3d9110897ea93e5adc3e26))
* **Validation:** Add support for hierarchical keys using dot-notation ([70c0b80](https://github.com/factorial-io/phabalicious/commit/70c0b80ca5a8c51a2f84cf7d81e67dafdb4f8f86))
* yarn, npm and composer methods can use docker-image as run-contexts now ([894fcf6](https://github.com/factorial-io/phabalicious/commit/894fcf6f4de3d66f1367c79724fc71684cc09f2e))


### Bug Fixes

* Add a warning for deprecated properties ([0956a98](https://github.com/factorial-io/phabalicious/commit/0956a98bbfbda0c54e49a854821357b4eab029a1))
* **configuration:** Move deprecation logic before handling defaults ([19193e8](https://github.com/factorial-io/phabalicious/commit/19193e8e82e83e019eb59c5d43c68525e6a3d0ca))
* Handle run context better for yarn, npm and others ([bede679](https://github.com/factorial-io/phabalicious/commit/bede6793580ab13e71b190f155328d958de3d720))
* Node::setProperty will create nested properties if needed. ([058dfef](https://github.com/factorial-io/phabalicious/commit/058dfef03f8d0af6ca7cc118ae2e773c37e047a6))
* **ScriptExecutionContext:** Use absolute path for working directory ([76d777c](https://github.com/factorial-io/phabalicious/commit/76d777c4e99503953a68cdf1af519a1bb87942e2))
* Shortcut for Node based data ([00b8dcb](https://github.com/factorial-io/phabalicious/commit/00b8dcb22ee3dd904d161b79033230174147804e))

## [3.8.0-beta.16](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.15...3.8.0-beta.16) (2022-08-11)


## [3.8.0-beta.15](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.14...3.8.0-beta.15) (2022-08-10)


### Bug Fixes

* Code cleanup, enable debug mode with `-vvvv` ([3b253d9](https://github.com/factorial-io/phabalicious/commit/3b253d9fe79acf77c34cbd32bfd90dd91908a18c))
* Do not run update check on self:update ([55f9b13](https://github.com/factorial-io/phabalicious/commit/55f9b130673615b8b16f67f8f8c9688fc1ce26b2))
* Don't try to apply empty environment variables ([623179b](https://github.com/factorial-io/phabalicious/commit/623179b9e9ee72642fde1f02c83c87fdf0d7e29d))

## [3.8.0-beta.14](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.13...3.8.0-beta.14) (2022-08-03)


### Features

* Add new command restic to interact with your backups ([e054444](https://github.com/factorial-io/phabalicious/commit/e054444813b0627d189be828cfef105ba7b9e82b))
* Add new command restic to interact with your backups ([800927c](https://github.com/factorial-io/phabalicious/commit/800927cc447b9fdac7c6d81037d77633582a822c))


### Bug Fixes

* Normalize version number before testing for available update ([7ca6032](https://github.com/factorial-io/phabalicious/commit/7ca6032757af2379f2b4e11e7f2b3a06c8b2b497))

## [3.8.0-beta.13](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.10...3.8.0-beta.13) (2022-07-28)


### Bug Fixes

* **1password:** fix version detection ([6a50b44](https://github.com/factorial-io/phabalicious/commit/6a50b44526398726b6cdf6c7ac848edce8a81f26))

## [3.8.0-beta.12](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.10...3.8.0-beta.12) (2022-07-28)


### Bug Fixes

* **1password:** fix version detection ([6a50b44](https://github.com/factorial-io/phabalicious/commit/6a50b44526398726b6cdf6c7ac848edce8a81f26))

## [3.8.0-beta.11](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.10...3.8.0-beta.11) (2022-07-28)


### Bug Fixes

* **1password:** fix version detection ([6a50b44](https://github.com/factorial-io/phabalicious/commit/6a50b44526398726b6cdf6c7ac848edce8a81f26))

## [3.8.0-beta.10](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.9...3.8.0-beta.10) (2022-07-27)


### Bug Fixes

* **ddev:** Fix docker integration, compute right docker container name, replace patterns in info and docker ([2cf445e](https://github.com/factorial-io/phabalicious/commit/2cf445efb805da4a8f36e5c6265722bcf598dd04))

## [3.8.0-beta.9](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.8...3.8.0-beta.9) (2022-07-23)


### Bug Fixes

* **ddev:** fix bug when no ddev is used ([b744070](https://github.com/factorial-io/phabalicious/commit/b744070c2324e02d16d2dd146232d17c7f349650))

## [3.8.0-beta.8](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.7...3.8.0-beta.8) (2022-07-16)


### Features

* **configuration:** allow `additionalNeeds` for host-configurations to declare additional needs ([5edacb2](https://github.com/factorial-io/phabalicious/commit/5edacb2264b9c88012881616b0e5bd64cf281877))
* **ddev:** add missing ddev method implementation ([11bf097](https://github.com/factorial-io/phabalicious/commit/11bf0976ec6fa94f774dfe7b1b28cd74cad7d38f))
* **ddev:** experimental lightweight integration with ddev ([fcb8162](https://github.com/factorial-io/phabalicious/commit/fcb8162bc7ec78a4bce26ba3dcdf427801625ad3))


### Bug Fixes

* fix regression not calling reset after copy-from ([bdfe66d](https://github.com/factorial-io/phabalicious/commit/bdfe66d192b5a6d854d70ae90b1e7d14a54e2284))

## [3.8.0-beta.7](https://github.com/factorial-io/phabalicious/compare/3.7.15...3.8.0-beta.7) (2022-07-14)


### Bug Fixes

* return a default config name if none set ([30dfffa](https://github.com/factorial-io/phabalicious/commit/30dfffa1bd1740cfcb997ab1071978563f4c4af7))


## [3.8.0-beta.6](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.5...3.8.0-beta.6) (2022-06-09)


### Bug Fixes

* Allow arguments containing = ([7e9eb94](https://github.com/factorial-io/phabalicious/commit/7e9eb9448f0a5b2408efaca8407296ce6ef8a984))

## [3.8.0-beta.5](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta4...3.8.0-beta.5) (2022-06-06)


### Bug Fixes

* fix failing tests ([2c7bc3a](https://github.com/factorial-io/phabalicious/commit/2c7bc3a2843b82c04ae4146ee4a82049584e0ab6))
* fix misc erros in database tests ([e178be1](https://github.com/factorial-io/phabalicious/commit/e178be1d079247d96eedb5c2a5b3fe0cc25f2641))
* **script-exection-context:** Update docs. ([89086d4](https://github.com/factorial-io/phabalicious/commit/89086d47e4b65b38d21ba4eaaf05e6162ca83c1c))

## [3.8.0-beta4](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta3...3.8.0-beta4) (2022-05-29)


### Bug Fixes

* Fix stupid error when checking base-url ([c3d2cc7](https://github.com/factorial-io/phabalicious/commit/c3d2cc7c31bacc54f1ed8221da5b34d179bb856f))
* Fix wrong usage of runtime-exceopion ([5ec2788](https://github.com/factorial-io/phabalicious/commit/5ec2788773b76a976f1877bddb7a6a28d3b0556a))

## [3.8.0-beta3](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta2...3.8.0-beta3) (2022-05-26)


### Bug Fixes

* Expose global settings as replacement patterns for k8s parameters ([3fd09e4](https://github.com/factorial-io/phabalicious/commit/3fd09e45886c56841d60036c5e1b96689151bfa0))
* Introduce new utility function to check if a string is a url ([d8d753a](https://github.com/factorial-io/phabalicious/commit/d8d753a41597830965a35762239fb521b528e450))
* **k8s:** Fix bug in cleanup code for the temporary folder ([cb9acca](https://github.com/factorial-io/phabalicious/commit/cb9accaf3c224c7537acfacbd4d1046e8a0e86f6))
* Make sure db is installed when trying to import sql ([129c42a](https://github.com/factorial-io/phabalicious/commit/129c42a7c2a5bdce07582bfa7a72e6785e4d4c25))
* Pass absolute paths to scaffolder to prevent ambiguities when resolving relative paths ([a13a360](https://github.com/factorial-io/phabalicious/commit/a13a360a1bf0c95c34c72419900e5ceb9a9e4f78))
* Show warning if tables cant be dropped ([58c89b3](https://github.com/factorial-io/phabalicious/commit/58c89b30deb1c557ccfefcf73c2e9d642d14deca))
* Use absolute path for base urls ([b6b1616](https://github.com/factorial-io/phabalicious/commit/b6b161646e4c671da8821ea1cd8cd138be08d1f3))

## [3.8.0-beta2](https://github.com/factorial-io/phabalicious/compare/3.8.0-beta.1...3.8.0-beta2) (2022-04-11)


### Bug Fixes

* **k8s:** Apply replacements for `parameters` before doing the scaffold ([468317f](https://github.com/factorial-io/phabalicious/commit/468317ff6001d8f0ab2afb9975f53c8ff06c6e6c))
* Limit when to check for updates again ([e5fb8af](https://github.com/factorial-io/phabalicious/commit/e5fb8afed6bf2e295e47c245bdae01ebca0bcc24))

## [3.8.0-beta.1](https://github.com/factorial-io/phabalicious/compare/3.7.12...3.8.0-beta.1) (2022-04-02)


### Features

* Add support for database credentials from 1password cli ([1903ec5](https://github.com/factorial-io/phabalicious/commit/1903ec5e1488284be70aeddaeb77993ba846ac3c))
* Allow global docker config which gets inherited by host-specific docker config ([d1221d4](https://github.com/factorial-io/phabalicious/commit/d1221d40e81f5d274531090820ac68aed72e3121))
* new command `db:query` to run custom queries against a db configuration ([21a2048](https://github.com/factorial-io/phabalicious/commit/21a204839295ca8974ab1a267ff99ed2a9a54ebc))


### Bug Fixes

* Better error reporting for missing scaffolding source files ([7a2f675](https://github.com/factorial-io/phabalicious/commit/7a2f6750c1fc59e486ba9498f34b2206b12522b7))
* command output left information when using blueprint ([9b1bab9](https://github.com/factorial-io/phabalicious/commit/9b1bab9b7175b63dbed606fbe6a5d12f35169b69))
* confirm returns now 0 instead of an empty result (Fixes [#219](https://github.com/factorial-io/phabalicious/issues/219)) ([bfab90b](https://github.com/factorial-io/phabalicious/commit/bfab90bfb032914f20e000d420bcdf56d008508e))
* fix error in password extraction from 1p client, add test-coverage ([3b0730e](https://github.com/factorial-io/phabalicious/commit/3b0730e857fab062f5fd87df6d841e199460954b))
* Get parent folder for a specific data-item directly from the source ([513df49](https://github.com/factorial-io/phabalicious/commit/513df49249e644ae7bbf4a8a218ee8f3e8220fc5))
* handle another race-condition when merging arrays ([2588478](https://github.com/factorial-io/phabalicious/commit/25884781cd3fb1e6dbdede284538687cc06efe56))
* Handle empty results from op with more grace ([32c0307](https://github.com/factorial-io/phabalicious/commit/32c0307a8ba232d40fac8013aecf1c9926757271))
* Handle missing data with more grace ([751d4e8](https://github.com/factorial-io/phabalicious/commit/751d4e8ae65ee265584bc35cf9aac8439df40601))
* Handle protected properties better ([ef3885c](https://github.com/factorial-io/phabalicious/commit/ef3885ca3ba5bcfa95e791809b8e3b8c114645ac))
* Hide warning when using PHP 8.1 ([7020ece](https://github.com/factorial-io/phabalicious/commit/7020ecedc9f348a05c630acc5ebbb87062d41cfe))
* inheritFromBlueprint did not work correctly with new node-based data-retrieval ([b1b18db](https://github.com/factorial-io/phabalicious/commit/b1b18db66132c5278c6c11c5ed920a241bb78b12))
* **jira:** Change jql to support also jira-cloud ([b5f89b4](https://github.com/factorial-io/phabalicious/commit/b5f89b46610e0d8f7b99f12ad492de050d7ced53))
* race-condition in new data handling method ([e5f1e99](https://github.com/factorial-io/phabalicious/commit/e5f1e99b1acaa87431a60d30c9e010c4d8b28dc2))
* resolveRelativeInheritance handles parent folder now for all cases ([5185b57](https://github.com/factorial-io/phabalicious/commit/5185b57f21bb175f9e2deeda31a948f9fc89adf7))
* show log-messages and app-prompts on stderr if the output is not decorated, e.g. when using pipes (Fixes [#250](https://github.com/factorial-io/phabalicious/issues/250)) ([8e1ee2b](https://github.com/factorial-io/phabalicious/commit/8e1ee2bf20d16785d82b10904a5cc9327c0d9ccc))
* throw an exception if tables cant be dropped ([9f3d9c8](https://github.com/factorial-io/phabalicious/commit/9f3d9c80755d7e3e4786c2d1f0009608f7f54bcc))
* Use absolute paths when scaffolding from a relative path ([26fdfcb](https://github.com/factorial-io/phabalicious/commit/26fdfcb7d4fc7f9b0d38c3fc1ee8857dd8f2b758))
* Wrap mysql password in quotes ([cea6466](https://github.com/factorial-io/phabalicious/commit/cea64661ced95725078dae636a884b23cffd2270))


## 3.8.0-beta.0 / 2022-04-02

### Changed:

* Minimum PHP requirement: 7.3

### New:

  * Added script- and scaffold-callbacks for encryption and decryption using `defuse/php-encryption`

    You can encrypt files in a script with

    ```yaml
    secrets:
      name-of-secret:
        question: What is the password

    scripts:
      encryt:
        - encrypt_files(path/to/files/or/folders/to/encrypt/*.ext, path/to/folder/to/store/encrypted/files, name-of-secret)
      decryt:
        - decrypt_files(path/to/files/or/folders/to/decrypt/*.enc, path/to/folder/to/store/decrypted/files, name-of-secret)
    ```

    The scaffolder has a new callback called `decrypt_assets` which works the same as `copy_assets` but with a preliminary decryption step

    ```yaml
    scaffold:
      - decrypt_assets(targetFolder, dataKey, secretName, twigExtension)
    ```

  * Refactored data is read and stored, which allows now to introspect a configuration mor thoroughly than before:

    * Added a new command `find:property` which will promt the user for a propery-name, and display from where the value got inherited and other useful information. If the property cant be found, a list of possible candidates is shown, the input supports autocomplete.
    * The command `about` will output from where the data got inherited, when the `-v` was passed.
    * Relative inheritance is now fully supported, that means you can inherit from a file/ http ressource via a relative path

  * Added new callbacks for getting a file from 1password-cli / -connect
  * Obfuscate passwords in log-outputs
  * new command `db:query` to run custom queries against a db configuration
  * Add support for database credentials from 1password cli
  * Add support for nested fields by 1password
  * Add new command `install:from-sql-file` which will stream-line the installation process when installing from a sql-file (Fixes #223)
  * Add options for mac-arm to workspace commands, ignore saved existing scaffold-tokens
  * Add feature-flag to use rsync implementation of get/put:file on k8s

### Fixed

  * Do not run reset when only running copy-from files (Fixes Do not run reset after phab copy-from <xxx> files #181)
  * Change jql to support also jira-cloud
  * `confirm`-question returns now 0 instead of an empty result (Fixes #219)
  * Hide warning when using PHP 8.1
  * Use absolute paths when scaffolding from a relative path
  * show log-messages and app-prompts on stderr if the output is not decorated, e.g. when using pipes (Fixes #250)
  * command output left information when using blueprint
  * fix error in password extraction from 1p client, add test-coverage
  * throw an exception if tables cant be dropped
  * Handle empty results from op with more grace
  * Wrap mysql password in quotes
  * feat: Allow global docker config which gets inherited by host-specific docker config
  * Better error reporting for missing scaffolding source files
  * Pass context down the lane, when running multiple commands serially
  * Fix warnings when no passwords are cached
  * Fix version check, do not check for version when running `self:update` (Fixes #174)
  * Use mysql port information when running install
  * Report deprecation messages after all inheritance is resolved
  * Show proper error message if script could not be located in the fabfile. (Fixes #220)
  * Better file names with date and time (Fixes #214)
  * Fix problems with tilde in file path in mysql method
  * Reset admin password after drupal is deployed completely (Fixes #211)
  * Do not run reset when only running `copy-from files` (Fixes #181)
  * Update grumphp so it works under PHP 8.x
  * Switch to consolidation/self-update as the other used lib is abandonded. Fixed some warnings under php 8.1
  * Pass shellProviderOptions to rsync
  * dump database structure and data separately to prevent missing table structures for ignored tables
  * Refactor copyAssets-callback to be more flexible

## 3.7.17 / 2022-07-28

### Fixes:

  * fix(1password): fix version detection

## 3.7.16 / 2022-07-23

### New:

* feature(1password): Add support for 1password cli 2.x

## 3.7.15 / 2022-07-14

### Fixes:

  * fix(secrets): Fix replacement-patterns validation, exclude secrets from validation

## 3.7.14 / 2022-07-05

### Fixes:

  * fix(script-execution-context): Create unique project-name for docker-compose-run

## 3.7.13 / 2022-06-08

### Fixes:

  * Changed name of overridden private class property. (Fixes #272)

## 3.7.12 / 2022-05-30

### Fixes:

  * fix(k8s): Add test coverage for k8s scaffold, fixed some bugs because of race-conditions

## 3.7.11 / 2022-05-30

### Fixes:
  * fix(k8s): Fix the real bug which prevents scaffolding k8s files introduced with 3.7.10

## 3.7.10 / 2022-04-19

### Fixes:

  * fix(k8s): Fix bug in cleanup code for the temporary scaffold folder

## 3.7.9 / 2022-04-02

### Fixes:

  * fix: [#260] self-update --allow-unstable=1 toggles between stable and unstable version
  * Updated dependencies

## 3.7.8 / 2022-03-15

### Fixes:

  * notify: Handle `--channel` option correctly.

## 3.7.7 / 2022-03-02

### Fixes:

  * Check only params for globally used parameter options (Fixes #254)
  * Remove accidentally added composer mirror
  * Use mysql port information when running install

### Chore:

  * Introduce renovate bot to update dependencies automatically
  * Update depencencies

## 3.7.6 / 2022-01-18

### Fixes:

  * Show warnings always

## 3.7.5 / 2021-12-17

### Fixes:

  * Use `--no-defaults` for mysql commands
  * Use same mysql dump options as drush
  * Add error handling when drush tries to get the db credentials
  * Fix problems with tilde in file path in mysql method
  * Reset admin password after drupal is deployed completely (Fixes #211)

## 3.7.4 / 2021-12-08

### Fixes:

  * When getting a dump from a database mimic the behavior of drush, get the full
    structure but ignore data from certain tables (defined in `sqlSkipTables`)
  * When copying files do not interact with the database

### New:

 * New `db`-subcommands:
   * `db:install` will install a new database
   * `db:drop` will drop all tables in the database
   * `db:shell` will run a database cli to work directly with the DB (similar to `drush sql-cli`)
   * `db:shell:command` will print out the command necessary to run a the db cli


## 3.7.3 / 2021-12-02

### Fixes:

  * Get credentials before trying to wait for the database
  * Add output-option to `get:property`-command to save the output to a file

## 3.7.2 / 2021-11-30

### Fixes:

  * Resolve secrets for `get:property` and add options to get output as yaml or json
  * Introduce useNfs for multibasebox scaffolder
  * Install db before running copy-from
  * Plugin discovery works when phab is started from a subfolder. Fixes #207

## 3.7.1 / 2021-11-11

### Fixes:

  * Add support for protected properties which wont be overridden by override-files.

## 3.7.0

### Breaking changes:

  * phabalicious now requires PHP 7.2
  * failing drush commands will now exit phab with an error code, instead of
    continuing. This might break your deployments.  To switch back to the old
    behaviour set `drushErrorHandling` to `lax`

### New

  * Introduction of script execution contexts. Scripts can now be executed in a
    docker image of your choice, or inside an service of a docker-compose setup

    For scripts using the `docker-image`-script-context

    ```yaml
    scripts:
      build-frontend:
        script:
          - npm install -g gulp-cli
          - npm install
          - gulp run
        finally:
          - rm -rf node_modules
        context: docker-image
        image: node:12
        user: node # Optional user, if not specified, the current uid:gid will be used
    ```

    The current folder is mounted to `/app` in the container, and the current user-
    and group will be used inside the running container. If you need to persist any
    files after the container got killed, make sure to copy/ move them into the
    `/app`-folder.

    The container will be removed after the script finishes. Before the script is
    executed, phabalicious will pull the docker-image.

    The `finally`-step will executed after the script, it allows to cleanup any
    leftovers, regardless of the result of the script-execution (e.g. returned
    early because of an error).

    `script`-actions for scaffolders are supporting this too, e.g.

    ```yaml
    hosts:
      scaffold:
        actions:
          - action: script
            arguments:
              context: docker-image
              image: node:14
              script:
                - npm install -g gulp-cli
                - npm install
                - gulp run
    ````

    For scripts using the `docker-compose-run` script-context:

    ```yaml
    scripts:
      test:backend:
        script:
          - composer install
          - php artisan db:wipe --force
          - php artisan migrate
          - php artisan db:seed
          - vendor/bin/phpunit
        context: docker-compose-run
        rootFolder: ./hosting/tests
        service: php
        workingDir: /app #working dir in the php service
    ```

    Corresponding `docker-compose.yml`:

    ```yaml
    version: '2.1'
    services:
      php:
        depends_on:
          db:
            condition: service_healthy
        build:
          context: ../../
          dockerfile: ./hosting/builder/Dockerfile

        environment:
          DB_PASSWORD: root
          DB_USERNAME: root
          DB_DATABASE: tests
          DB_HOST: db
          APP_ENV: local
      db:
        image: mysql:8
        environment:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: tests
        healthcheck:
            test: "mysqladmin -u root -proot ping"

    ```

    This will use the `docker-compose.yml` from `hosting/tests` and run
    `docker-compose run php` and exetute the script inside the `php`-service.
    This works very well for scenarios where your app need other services to
    function, like in this case a mysql database. In contrast to the `docker-image`-
    script-context no folders are mounted into the service. You need to set this up
    via your docker-compose.yml


  * Add `md5`-twig-filter to scaffolder

    ```twig
    aValue: "{{ "Hello world" | md5 }}"
    ```
    will be scaffolded to

    ```yaml
    aValue: "f0ef7081e1539ac00ef5b761b4fb01b3"
    ```
  * Add `secret`-twig-function to the scaffolder. It will return the value for a given secret.

    ```twig
    The mysql-password is {{ secret("mysql-password" }}
    ```

  * Host-configs can be hidden from `list:hosts` by setting `hidden` to `true`

    ```yaml
    hosts:
      hiddenHost:
        hidden: true # host config will not be shownn on `list:hsots`
    ```

  * Add a new `info`-section to `host`-configs and a project specific `description`. This allows the user to add a short
    description and one or more urls which will be displayed on `phab list:hosts`

    ```yaml
    description: |-
      A multiline global project description which will be outputted on
      list:hosts

    hosts:
      local:
        info:
          description: A local installation aimed for development
          publicUrl: https://localhost
          category:
            id: local
            label: Local installations
       someDevInstance:
        info:
          description: |-
            A multiline string describing someDevInstance which has multiple public
            urls
          publicUrls:
            - https://web.example.com
            - https://bo.example.com
            - https://search.example.com
          category:
            id: dev
            label: Develop installations
    ```

    Note, that `list:hosts` will show only the first `publicUrl`. But you can run
    `phab list:hosts -v` to get a more verbose output with all urls and descriptions

    Example output:

    ```shell
    $ phab list:hosts

    List of found host-configurations:
    ==================================

    Local installations
    -------------------
    * local  https://localhost

    Develop installations
    ---------------------
    * someDevInstance  https://web.example.com
    ```

  * New methods for handling database tasks (importing or exporting a dump) added:

      * `mysql`
      * `sqlite`

    The functionality was moved out from the `drush`-method and replaced by the new
    methods.

    Database credentials will be obtained automatically if not part of the cofiguration
    e.g. from drush or environment-variables/ the .env-file for laravel-based projects

  * New command `artisan` and new method `laravel` for laravel-based projects. Just
    run e.g. `phab -cyourconfig artisan db:seed`

  * Add support for global `artisanTasks`

  * Methods can declare dependencies to other methods, e.g. using the method `drush`
    will implicitely use method `mysql` if not stated differently in `needs`.

  * Allow users to override rsync options via the `rsyncOptions` settings, e.g.

      ```yaml
      rsyncOptions:
        - --delete
      ```
  * You can now scaffold your docker configuration before running any docker-related
    command e.g. `docker` or `docker-compose`. That means you can scaffold the
    corresponding `docker-compose.yml` or `docker-compose.override.yml` before running
    a command against the config. An example:

      ```yaml
      hosts:
        example:
          docker:
            scaffold:
              assets:
                - templates/docker-compose.yml
                - templates/docker-compose.override.yml
      ```

    This will copy the two files in `templates` into the root-folder and apply any
    configuration from the host `example` before copying it to the destination.

    * new `db`-command with subcommands `install` and `drop`, allows you to create
      or drop a database.
    * option `--skip-drop-db` for `copy-from` and `restore:sql-from-file` to not
      drop the table before running the import
    * option `--skip-reset` for `copy-from` which will not run the reset-task after
      the import.

### Changed

  * Refactor how script-callbacks are handled internally, use a more oo-style
  * Moved all db related functinality out of `drush` into the methods `mysql`
    and `sqlite`
  * Tests do not depend on the current working directory anymore and clean up after themselves.
  * Refactor script execution to allow lazy validated replacements
  * Fix unresolved replacement patterns in DockerMethod
  * Allow artisan tasks to be configured


## 3.6.16 / 2021-09-23

### Fixed:

  * Fix stuck drush deploy:hook

## 3.6.15 / 2021-08-04

### Fixed:

* Harden the way how phab determines the current running pod

## 3.6.14 / 2021-07-01

### Fixed:

  * Fix `copy-from db` for `dockerExecOverSsh`-shells, fixes #175
  * Fix `get:file` and `put:file` for `dockerExecOverSsh` on Mac OS

## 3.6.13 / 2021-07-01

### New:

* Enable copy-from with kubernetes pod as target as long the source is reachable via SSH
  and a ssh-connection can be established between the pod and the data-source.

## 3.6.12 / 2021-06-30

### Fixed:

  * Fix getFile for docker-exec-over-ssh shell provider
  * Update multibasebox setup for workspace command
  * Cleanup kube-folder before scaffolding. Fixes #161

## 3.6.11 / 2021-06-01

### Fixed:

  * Fix blueprint template variable `slug.with-hyphens.without-prefix`
  * Bump dns-packet from 1.3.1 to 1.3.4
  * Bump browserslist from 4.16.3 to 4.16.6
  * Update copy action docs

## 3.6.10 / 2021-05-20

### Fixed:

  * Better error handling for version check
  * Fix broken `isTerminated()`
  * Use github actions for creating automatic releases
  * Run php unit tests in github actions


## 3.6.9 / 2021-05-19

### Fixed:

  * Fix missing method in DryRunShellProvider, add test coverage

## 3.6.8 / 2021-05-19

### Fixed:

* Terminate current shell, when new app gets deployed via k8s

## 3.6.7 / 2021-05-19

### Changed:

  * Make secret-gathering more verbose, support for multiple 1password-connect-tokens (Fixes #156)

## 3.6.6 / 2021-05-13

### New:

  * Allow passing of 1password JWT token via environment variable
  * Add support for hidden questions (e.g. for passwords)
  * Add option `forceConfigurationManagement` for cases where phab cant detect it properly

### Changed:

  * Set new default for configurationManagement
  * If there's an error with 1password cli or connect, display error, but continue

## 3.6.5 / 2021-05-11

### New:

  * Support for [1password connect](https://support.1password.com/secrets-automation/), see updated docs.

### Fixed:

  * Fix jira command (fixes #155)
  * Use the pod-template-hash to get the actual running pod, instead of any pod (Fixes #153)
  * Better error messages and throw an exception if a secret could not be retreived

## 3.6.4 / 2021-04-30

###Fixed:

  * Fix relative inherits from remote and using `@` (Fixes #150)
  * Update app:scaffold docs

## 3.6.3 / 2021-04-15

### Changed:

  * Set hostconfig for subsequent commands in artifact based deployments
  * Use dot-notation to define the data to use when altering files
  * Add configBaseFolder configuration option to override the config base folder name
  * Add support for storing secrets in .env file
  * Add support for named scripts (from the scripts section) in artifact based deployment

### Fixed:

  * Fix bug in escaping replacement patterns
  * Pass secrets to subsequent calls via execute callback
  * Save cloned hostconfig when running artifact based deployment, so execute callback works on latest data

## 3.6.2 / 2021-04-14

### Fixes

  * Allow multiple secrets in one line

## 3.6.1 / 2021-04-14

### Fixes

  * Fixing parsing error #145
  * Set base-url from scaffold-file as a fallback (fixes #144)

## 3.6.0 / 2021-04-07

The easter relase 3.6.0

```
      (\(\
     (='.')
    o(_")")
```

### New in this release (from the changelog of the beta releases):

  * offsite backups via new `restic`-method
  * Introduce new secrets mechanims to retrieve secrets from the outside
  * Refactor app:create and app:destroy, add implementation for k8s
  * Allow prefixing paths for inheritsFrom with `@` and introduce `inheritanceBaseUrl` to set a common base path
  * Implement base url mechanism for scaffolds
  * Allow inheritance in blueprints using `blueprintInheritsFrom`


## 3.6.0-beta.9 / 2021-04-06

### Fixed:

  * Add restic sftp host to known hosts automatically
  * Throw an error instead of asking for a ssh password, can be overriden, if needed (Fixes #142)
  * Update dependencies

## 3.6.0-beta.8 / 2021-04-06

### New:

  * Allow user to provide tar-options for `get:files-dump` (Fixes #138)
  * Add new option for `put:file` to set the destination for the file copy. (Fixes #137)
  * Allow `computedValues` also for scaffold scripts

## 3.6.0-beta.7 / 2021-03-29

### New:

  * Allow user to provide tar-options for `get:files-dump` (Fixes #138)
  * Add new option for `put:file` to set the destination for the file copy. (Fixes #137)
  * Allow `computedValues` also for scaffold scripts

## 3.6.0-beta.6 / 2021-03-25

### Fixes:

  * Try to replace patterns in strings only, fixes #141
  * Remove double --namespace from k8s methods

## 3.6.0-beta.5 / 2021-03-24

### Fixed:

  * Allow replacements for `alter_file_*` callbacks

## 3.6.0-beta.4 / 2021-03-24

### Fixes:

  * Fix overridden data when executing scaffolds in scaffolds, pass options down the lane
  * Resolve secrets before executing a command

## 3.6.0-beta.3 / 2021-03-24

### Fixed:

  * Disable quiet for scaffold callback to allow questions to be shown

## 3.6.0-beta.2 / 2021-03-24

### Fixed:

  * Remove dead code
  * Use release version when checking `requires`
  * Fix fatal on startup, fixes #136
  * Fix for backups w/o git hash

## 3.6.0-beta.1 / 2021-03-23

### New:

  * Initial implementation of doing backups via restic
  * Implement list:backups and restore for restic. Initial support for other backup methods implemented
  * Introduce new secrets mechanims to retrieve secrets from the outside
  * Refactor app:create and app:destroy, add implementation for k8s
  * Add new way of handling secrets via new replacement pattern
  * Support for password-only fields from 1password
  * Resolve secrets for scripts
  * Resolve secrets on demand, prevents prompts for secrets for unrelated commands
  * Allow prefixing paths for inheritsFrom with `@` and introduce `inheritanceBaseUrl` to set a common base path
  * Implement base url mechanism for scaffolds
  * Add option to scaffold-commands to provide a base url for scaffolders
  * Allow inheritance in blueprints using `blueprintInheritsFrom`

### Changed:

  * Update dependencies, support composer 2
  * Refactor backup related code, move common code into base class
  * Show an error message for `copy-from files` where source or destination does not support rsync (e.g. docker-exec, kubectl)
  * Add deprecation message for ftp-passwords

### Fixed:

  * Add missing license to composer.json, fixes #132
  * Fix getSQLDump and restoreSqlFromFile tasks
  * When checking for a mysql connection do not consume default config files
  * Enhance error message when inheritsFrom/blueprintInheritsFrom fails
  * Throw an exception when the name of the docker container is missing
  * Require rinnung app for shell-task
  * Fix check for existing app in k8s, better error messages

## 3.5.36 / 2021-03-15

### Fixed:

  * Fix shell command for docker-exec and k8s. Fixes #130

## 3.5.35 / 2021-03-14

### Fixed:

  * Fix `getSQLDump` and `restoreSqlFromFile` tasks

## 3.5.34 / 2021-03-11

## New

  * new `scaffold` internal command to run scaffolders from within a scaffolder

## Changed:

  * Percentages for replacement patterns can be escaped via `\` in scripts
  * webhook-urls can contain replacement-patterns

## 3.5.33 / 2021-03-04

## Fixed:

  * Run install from-config only for drush >=9  (fixes #121)

## 3.5.32 / 2021-03-01

### Fixed:

  * Fix regression when restarting containers with cached container name

## 3.5.31 / 2021-03-01

### New

  * Initial implementation of new command `docker-compose`
  * Rework docker-compose command so it can be run interactively
  * Introduce isRunningAppRequired, to exit early if not the case
  * Add support for `deploy`-command from drush 10.3 (fixes #106)
  * Check target repository for new commits and show a warning (fixes  #124)

### Changed

  * Use same deploy strategy as drush
  * Add drush cr after updb to mimic drush deploy

### Fixed

  * Fix broken caching of remote resources
  * Show error message for `phab shell`, fixes #122

## 3.5.30 / 2021-01-27

### Added:
  * Add gitOptions to git-artifact deployment to change the actual git clone command

## 3.5.29 / 2021-01-21

### Fixed:

  * Throw an exception when drush cim fails

## 3.5.28 / 2021-01-15

### New:

  * New callbacks for scripts and scaffolders, `set_directory` and `alter_yaml_file`

### Changed:

  * Switch to github branching strategy
  * `install` will try to use existing configuration when available
  * Bump ini from 1.3.5 to 1.3.8
  * Make getting data over http more robust, add tests for it
  * Scaffolder: Allow merge of numerical and associative arrays
  * Scaffolder: Sort actions by key before processing them
  * Scaffolder: Remove default actions
  * Install: Fix bug in detection of correctly setup configuration management
  * Install: Fix install without config by setting context result.
  * Install: Add --existing-config command option to drush site-install if config sync dir contains existing configuration.

## 3.5.27 / 2020-11-12

### Fixed:

  * Allow merge of numerical and associative arrays

## 3.5.26 / 2020-11-12

### Fixed:

  * Revert change to mergeData, add test coverage for it

## 3.5.25 / 2020-11-12

### Changed:

  * Sort actions by key before processing them
  * Remove default actions

## 3.5.24 / 2020-11-06

### Fixed:
  * Provide default value for `alterSettingsFile` property

## 3.5.23 / 2020-11-06

### Fixed:
  * Fix file copying for kubectl shells
  * Enhance docs for git based artifact deployment
  * Apply shellProviderOptions also to other ssh commands
  * Fix shell creation for ssh when executed as a sub process, typos

### New:
  * Add new artifact actions `log` and `message` (Fixes #109)

## 3.5.22 / 2020-10-18

### Fixed:
  * Fix error in scaffolder which prevented to ask for the project name
  * Try to prevent timeouts when using k8s
  * Set tty flag for new shells

### Changed:
  * Refactor how scripts and scaffolders handle callbacks
  * Enhance argument parsing, updated test-coverage
  * Allow `inheritsFrom` with absolute file names
  * Update vuepress
  * Refactor how options are passed to the shell-provider.

## 3.5.21 / 2020-10-11

### Fixed:
  * Fix regression with `get:file`, `put:file` and `copy-from`

## 3.5.20 / 2020-10-11

### New:
  * Add new option `alterSettingsFile` which defaults to true

### Changed:
  * Allow inheritance to merge arrays if one of them is associative

## 3.5.19 / 2020-10-09

### Fixed:
  * Reset auto-discovered docker name after docker-tasks to prevent outdated information
  * Show proper error-message if a remote asset cant be loaded

## 3.5.18 / 2020-10-07

### Fixes:
  * Provide sensible defaults for `podSelector`, fix a warning
  * Add test-coverage for most shell-providers, fixes #104

## 3.5.17 / 2020-10-01

### Breaking change:

  * Provide a more secure password for dev-instances. This will change the admin
    password of your dev-instances as the default passwords got replaced with a more secure variant.

    You can get the admin-password for a given instance via `phab -c<config> get:property adminPass`.

    Or you can override the `adminPass` in the fabfile globally or on a per host-basis. Another option
    for local development is creating a `fabfile.local.override.yaml` in your multibasebox folder and
    change its content to sth like this

    ```
    hosts:
      mbb:
        adminPass: admin
    ```

## 3.5.16 / 2020-09-27

### Fixes:
  * Fix kubernetes get-file and put-file task.
  * Update readme and document how to create a release and publish the docs
  * Update vuepress

## 3.5.15 / 2020-09-18

### Fixes:
  * Remove `-q` option from ssh command as this prevents sshs error reporting

### New:
  * Provide computed property `kubectlOptionsCombined` for use in scripts to use the same cli-options as phab does

## 3.5.14 / 2020-09-16

### Fixed:

  * `execute()` will now properly respect breakOnFirstError which got ignored in the past
  * Fix docs regarding `log_message` (Fixes #100)

## 3.5.13 / 2020-09-15

### New:

  * Allow script question values to be overridden via `--arguments`
  * Add tests for scaffold command
  * Allow questions for scripts
  * Allow computedValues for scripts, this will allow the user to eecute external commands and reuse their results.
  * `log_message` supported for scripts
  * `confirm(message)` supported for scaffolder and scripts.
  * Enhance parsing of arguments for internal commands when used in the scaffolder or script. This is now possible: `log_message("hello, dear user! Welcome!", "success")`

### Changed

  * HostConfigs with `inheritOnly` wont be listed when running `list:hosts`
  * The scaffolder will use/ create a token-cache file only when requested with `--use-cached-tokens`

## 3.5.12 / 2020-09-09

### New:

  * Implement `start-remote-access` for k8s

### Fixed:
  * Remove fragile context handling in k8s, instead use dedicated command-line argument

## 3.5.11 / 2020-09-08

### New:

  * Add support for .env files on the same level as the .fabfile. Will be included in global scope under the key `environment`

### Fixed:

  * Forget cached `podForCli` after deployment to acquire a new one, add `settings` to the replaceents
  * Update dependencies

## 3.5.10 / 2020-09-08

### New:

  * Add `assert_file` internal command for scaffold-scripts to check if a specific file exists.

### Fixed:

  * Silence ssh process a bit more, might fix #98
  * Proper parsing and applying of single quotes to commands. Fixes #81
  * If no tty is requested do not attach stdin to process, so it wont wait for input. Fixes #98
  * Bump symfony/http-kernel from 4.4.7 to 4.4.13

## 3.5.9 / 2020-08-31

### Fixed:

  * Fix wrong npmRootFolder when using artifact based deployment, modularize the logic

## 3.5.8 / 2020-08-26

## Fixed:

  * Revert newly introduced idle-prevention when running scripts as it breaks execution under certain circumstances

## 3.5.7 / 2020-08-25

### New:

  * Do pattern replacement dynamically for k8s
  * Rework kubectl execution, you can now add options to the command and set a dedicated kubeconfig in the fabfile

### Fixed:

  * Fix broken workspace:create and workspace:update commands due to recent refactoring. Add test coverage for both commands
  * Refactor tunnel creation into dedicated classes and helpers

## 3.5.6 / 2020-08-22

### New:

  * Allow to set environment variables in the kubectl context, e.g for KUBECONFIG

### Fixes:

  * Make sure that drush operates in the correct sitefolder when pulling or pushing variables
  * Smaller doc fixes

## 3.5.5 / 2020-08-18

### New:

  * Add support for switching context before running kubectl

## 3.5.4 / 2020-08-17

### New:

  * Allow optional script context definition of a script, this will allow to execute the script in a different context, eg in the context of the kubectl shell

## 3.5.3 / 2020-08-11

## Fixed:

  * Fix broken workspace:create and workspace:update commands due to recent refactoring. Add test coverage for both commands

## 3.5.2 / 2020-08-07

### Fixed:

  * Chunk regex patterns to prevent warning and failing replacements
  * Provide default command result

## 3.5.1 / 2020-08-04

### Fixed:

  * Fix a warning when running scaffold without scripts
  * Force-include twig dependencies when building phar

## 3.5.0 / 2020-08-04

### Fixed:

  * Fix error in fallback version check, fixes #93

### New:

  * Support for kubernetes, see documentation for more details. Some features still missing.
  * Add describe subcommand for k8s
  * Add logs subcommand for k8s
  * Implement copy operation for k8s
  * Add docs for kubernetes, fix test
  * Allow the passing of a shellprovider to the scaffolder, reorganize code a little bit
  * Add new k8s subcommand rollout, wait for deployments to finish before continuing
  * Apply kubernetes config on deploy, even when scaffolder is not used, smaller code enhancements
  * Rename deployCommand to applyCommand, add delete subcommand to k8s method
  * Add new option `set` which allows to set a certain value in the configuration
  * Provide host data and timestamp for scaffolder
  * Add kubectl shellprovider, fix some bugs in K8sMethod
  * Add k8s subcommands
  * Implement initial deploy command for k8s
  * Fix replacements in k8s
  * Start working on k8s method, refactoring scaffold functionality into dedicated class with dedicated options class
  * Bump elliptic from 6.5.2 to 6.5.3
  * Bump lodash from 4.17.15 to 4.17.19
  * Disable symfony recipes
  * Show available update even on linux

## 3.4.9 / 2020-07-24

### Fixed:

  * Harden the extraction of return codes for executed commands

## 3.4.8 / 2020-07-16

### Fixed:

  * Clean up any cached docker container name after a task got executed. Fixes #89
  * Add php codesniffer as dev dependency
  * Show available update even on linux

## 3.4.7 / 2020-06-29

### Fixed:

  * Fix ensureKnownHosts

## 3.4.6 / 2020-06-29

### Fixed:

  * Ensure known hosts for ssh shells, some refactoring
  * Fix smaller bugs in scaffolder
  * Bump websocket-extensions from 0.1.3 to 0.1.4
  * Throw an exception with the filename if transform plugin throws an exception, improve logging

### New:

  * Refactor Scaffoldbase to use a QuestionFactory with new types of questions
  * Enhance validation service
  * Pass files parameter directly to transformer

## 3.4.5 / 2020-05-18

### Fixed:

  * Fix docker-exec-over-ssh

## 3.4.4 / 2020-05-18

### New:
  * New shellprovider: docker-exec-over-ssh, its a concatenated shell running docker-exec on a remote instance

### Fixed:
  * Prevent folder name collision if two artifact deployements are runing at the same time
  * Fix app:create when using a service for docker ip gathering
  * Fix for inherit loops, fixes #78
  * Allow per host repository settings

## 3.4.3 / 2020-04-29

##Fixed

 * Previous versions did not check the version constraints of imported yaml files.

## 3.4.2 / 2020-04-28

### New

  * Allow per host repository settings

## 3.4.1 / 2020-04-25

### Fixed

  * Fix for non-working plugin autoloader when using bundled as phar

## 3.4.0

### New

  * Support override-mechanism. If a yaml-file with the extension `override.yml` or `override.yaml` exists, its data will be merged with the original file.
  * Add ip option to start-remote-access command to connect to a specific host
  * Initial implementation of `variable:pull` and `variable:push` for D7 via drush
  * add new command `scaffold` which allows to scaffold and transform not only apps but also other types of files.
  * Introducing plugin mechanism to add functionality via external php files for the scaffolder. Will be used by `phab-entity-scaffolder`
	  * Use static array of discovered transformers, to fix unit testing, as php does not allow to discover a php-class multiple times
	  * Add logging to plugin discovery
	  * Fix options with multiple values
	  * Implement better approach to find vendor-folder
	  * Expose target_path to transformers
	  * Make sure, that only yaml files get transformed
	  * Use global autoloader, so registering new namespaces are permanent
	  * Use autoloader to register plugin classes
	  * Refactor scaffold callbacks in dedicated classes, so they can be externally loaded
	  * First draft of a plugin mechanism to decouple scaffolding from phabalicious, as an effort to port entityscaffolder to d8
  * Support for knownHosts. Setting `knownHosts` for a host- or dockerHost-configurtion will make sure, that the keys for that hosts are added to the known_hosts-file.
  * Update known_hosts before specific commands, needs possibly more work #70

### Changed

  * Remove entity-updates option during drush reset
  * Better error message for missing arguments
  * Refactor task context creation to allow arguments for all commands
  * Upgrade vuepress dependencies
  * Update dependencies
  * Update docs

### Fixed

  * Satisfy PHP 7.2
  * Document yarnRunContext and npmRunContext
  * Fix regression with script default arguments, added test-case. Fixes  #77
  * Fix exclude action for git artifact deployments #73
  * Fix race condition on app:create
  * Remove deprecated code
  * If the underlying shell terminates with an exit code, throw an exception
  * Use a different name for the target filename to prevent name-clashes
  * Fix version command, nicer output


## 3.3.5 / 2020-03-02

### Fixed

  * limit amount of commit messages to 20 when doing artifacts deployment

## 3.3.4 / 2020-01-21

### Fixed

  * Skip precommit hooks for committing artifacts
  * Silence a warning, if shell exits unexpectedly throw exception only on error

## 3.3.3 / 2020-01-15

### Fixed

  * Add new internal method to modify a json file when scaffolding.
  * New option `skipSubfolder`, so that scaffold wont create a subfolder.
  * Update vuepress


## 3.3.1 / 2020-01-06

### Fixed:

  * Fixed a bug inheriting the target branch from the source branch


## 3.3.0 / 2020-01-03

### New:

  * New command `workspace:create`, which will run the multibasebox scaffolder
  * Add `workspace:update` command and refactor scaffold code
  * scaffolder will store tokens in `.phab-scaffold-tokens` in the scaffolded folder. Subsequent scaffold-runs will load and use these tokens (and do not ask for them)
  * when scaffolding a project the warning that the target folder exists can be suppressed by adding `allowOverride: 1` to the variables-section.
  * Add coding-style standards config file, apply them.

## 3.2.15 / 2019-12-30

### Changed/ New

  * Refactor composer command to a base class, so functionality can be shared with new commands yarn and npm
  * Drush will try to run an install, even when no database-settings were found in the current host-config.

## 3.2.14 / 2019-12-19

### Fixed:

  * Add support for Drupal 8.8 and changed behavior regarding config-sync

## 3.2.13 / 2019-12-15

### Fixed:

  * Pass arguments to subsequent commands
  * If a replacement can't be parsed, throw an exception

## 3.2.12 / 2019-12-14

### Fixed:

  * Fix php declaration error

## 3.2.11 / 2019-12-14

### Fixed:

  * Allow arguments for the docker command, merge scripts variables instead of replacing them

## 3.2.10 / 2019-12-12

### Fixed:

* Fix error when deleting existing files in copyAction

## 3.2.9 / 2019-12-11

### Fixed:

  * Harden handling of getting actual container name from a service

## 3.2.8 / 2019-12-10

### Fixed

  * Harden behavior of committing artifacts.

## 3.2.7 / 2019-12-06

### New/Fixed:

  * Ignore ssl errors when running tests'
  * Better way of handling relative paths in LocalShell
  * Refactor RunCommandBase to define a runContext. Could be host, or dockerHost
  * Bump symfony/http-foundation from 4.2.3 to 4.4.1

## 3.2.6 / 2019-12-01

### Fixed:

  * Fix branch handling in deploy command

## 3.2.5 / 2019-12-01

### New:

  * Add preliminary documentation for webhooks
  * Allow methods to alter script callbacks
  * Add test for task-specific webhooks
  * Implement new command `webhook` which allows the user to invoke a webhook defined in a fabfile.

### Fixed:

  * Fix non-working branch argument-handling, old code did not respect the argument at all

## 3.2.4 / 2019-11-23

### New:

  * Add dedicated `npm` method, similar to yarn-method.

### Fixed:

  * Issue #62: Switch to using single -m for commit message

## 3.2.3 / 2019-11-18

### Fixed:

  * Fixed a security alert in a symfony dependency

## 3.2.2 / 2019-11-14

### New:

  * new method `yarn`, which will run yarn install on install/reset task and a custom build command when running the reset-task
  * new custom build artifact available, with full control over the stages

### Fixed:

  * Require grumphp only for dev
  * Use latest grumphp
  * Update precommit config
  * Report script errors early
  * Delete target file/folder before copying
  * Tag build artifact with existing tag of source repository
  * Disregard confirm action if --force option is set
  * Fix tests and keep option `override`
  * Fix possible exception, when using -v show possible tokens
  * Allow multiple options '--arguments'
  * Allow --arguments for deploy command
  * Refactor actions, add new action installScript
  * Set the type as `installation_type` as state in drupal 8

## 3.2.1 / 2019-10-14

### Fixed:

  * Include version number for temporary folder

## 3.2.0 / 2019-10-14

### New:

  * `rootFolder` is set by default now to the folder where the fabfile is located.
  * All context variables are exposed as replacement patterns for using in scripts.
  * new method `artifacts--git` to build an artifact and push it to a git repository, see new documentation about artifacts.
  * Update documentation regarding the new artifact workflow

### Changed

  * Refactored and renamed method `ftp-sync` to `artifacts--ftp` in preparation of artifacts--git. Be aware that you might need to change existing configuration!

## 3.1.0 / 2019-09-27

### New

  * Switched to vuepress as documentation tool

## 3.1.0-beta.1 / 2019-09-14

### New

  * Get drush command to dump sql from configuration
  * Allow environment for host-configs, fixes #56
  * Support replacements for host-environment variables

### Fixed

  * Refactor tests, so they can be run from root foldergit
  * Push and restore working dir
  * Fix build script regarding not enough file handles

## 3.0.22 / 2019-09-12

### New

  * Add support sql-sanitize for reset task

### Fixed

  * Fix for exception after certain docker commands.

## 3.0.21 / 2019-08-21

### Fixed

  * Document `skipCreateDatabase`
  * Add chmod to the list of executables, fixes #57

## 3.0.20 / 2019-07-07

### Fixed

  * Show error-message if shell could not be initialized. Fixes #54
  * Satisfy phpstan and add it as a new precommit-hook
  * Fix drush and other commands when using local shell provider
  * Prevent filename collision when running docker copySSHKeys, fixes #52
  * Fix issue with special characters in the pw
  * Use latest version of stecman/symfony-console-completion

## 3.0.19 / 2019-06-25

### Fixed

  * Fix warning
  * Better errorhandling

## 3.0.18 / 2019-06-18

### Fixed

  * Smaller enhancements regarding scaffolding
  * Sleep only when shell did not produce any output

## 3.0.16 / 2019-06-09

### Fixed

  * Fix introduced regression when running a drush command

## 3.0.15 / 2019-06-07

### Changed

  * Enhance support for docker shells
  * Rework shell execution for docker-exec
  * Allow relative paths for docker rootFolder. Add sleep to reduce processor drain

### Fixed:

  * Fix default values

## 3.0.14 / 2019-05-28

### Fixed

  * Fix bug with variants overriding existing configuration

## 3.0.12 / 2019-05-27

### Fixed

  * Run composer install on `installPrepare`

## 3.0.11 / 2019-05-19

### Fixed

  * Fix bug when using inheritsFromBlueprint

## 3.0.10 / 2019-05-19

### Added

  * Add new inheritFromBlueprint config, so a host-config can inherit from a blueprinted config

				hosts:
				  local:
				    inheritFromBlueprint:
				      config: <config-which-contains-blueprint>
				      variant: <the-variant>
	  Thats roughly the same as calling `phab --config=<config-which-contains-blueprint> --blueprint=<the-variant>` but using it in the fabfile allows you to override the config if needed.
  * Introduce deprecated-flag for inherited data. Will display a warning, when found inside data
  * Enhance output-command to support output of docker-, host- and applied blueprint config, and also of all global data.

        phab -clocal output --what host # will output the complete host-config
        phab -cremote output --what docker # will output the complete docker-host config
        phab output --what global # will output all global settings

## 3.0.9 / 2019-05-11

### Fixed

  * Add validation for foldernames
  * Harmonize option copy-from

## 3.0.8 / 2019-05-10

### Fixed

  * Use correct port range, previous code might have used ephemeral ports, which are reserved -- should fix sporadically failing ssh-connections, fixes #49


## 3.0.7 / 2019-05-01

### Fixed

  * Show a warning if local users' keyagent does not have a key when running `docker copySSHKeys`

## 3.0.6 / 2019-04-25

### Fixed

  * rename setting `dockerAuthorizedKeyFile` to `dockerAuthorizedKeysFile`, keep the old one for backwards compatibility
  * if no dockerAuthorizedKeysFile is set, use the public-keys of the ssh-agent instead
  * Cd into siteFolder before restoring a db-dump. (Fixes #48)
  * Ask before scaffolding into an existing directory, can be overridden by `--force`. Fixes #43
  * Allow --force and --force 1
  * Report errors and stop the execution when errors happen while scaffolding
  * Use latest version of stecman/symfony-console-completion
  * Enable/ disable modules one by one, fixes #39
  * Better error-reporting for inherited files from local and remote
  * Handle variants and error output better
  * Allow the phab binary to called from a regular Composer installation
  * Add the "phab" binary to composer.json explicitly
  * Update passwords documentation

## 3.0.5 / 2019-04-17

### Fixed

  * Cd into siteFolder before restoring a db-dump. (Fixes #48)

## 3.0.4 / 2019-03-18

### New

  * Support for variants and parallel execution for a set of variants

### Fixed

  * Document mattermost integration, fixes #29
  * Fix broken shell autocompletion
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

``` bash
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
