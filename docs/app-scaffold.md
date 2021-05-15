---
sidebarDepth: 3
---
# Scaffolding a new app

Phabalicious has a simple but powerful app:scaffold-command. It will read a yml-file and interpret the contents. It will use the existing pattern-replacement used for scripts and twig for changing file-contents. The scaffolding and fixture-files can live on a remote server or on your file-system.

Here's an example of an app scaffold-file:

```yaml
questions:
  name:
    question: "The Name of this project"
  shortName:
    question: "The short-name of this project"
    validation: "/^[A-Za-z0-9]{3,5}$/"
    error: "The shortname may consist of 3 to 5 letters (A-Z) or digits (0-9)"
    # Questions can have a default:
    default: "SP"
    transform: lowercase

variables:
  composerProject: drupal-composer/drupal-project:8.x-dev
  webRoot: web
  allowOverride: 0 # Setting it to 1 will not show a warning if the target folder exists.
  skipSubfolder: 0 # Setting it to 1 will instruct phabalicious to not create a subfolder.

assets:
  - .fabfile.yaml
  - docker-compose.yml
  - docker-compose-mbb.yml

deployModuleAssets:
  - deployModule/%shortName%_deploy.info.yml
  - deployModule/%shortName%_deploy.module
  - deployModule/%shortName%_deploy.install

scaffold:
  - rm -rf %rootFolder%
  - composer create-project %composerProject% %rootFolder% --stability dev --no-interaction
  - cd %rootFolder%; composer require drupal/coffee
  - cd %rootFolder%; composer require drupal/devel
  - copy_assets(%rootFolder%)
  - copy_assets(%rootFolder%/%webRoot%/modules/custom/%shortName%_deploy, deployModuleAssets)
  - cd %rootFolder%; git init .
  - cd %rootFolder%; git add .
  - cd %rootFolder%; git commit -m "Initial commit by phabalicious"
```

The fabfile needs at least the `questions`and the `scaffold`-section. The `scaffold`-section is a list of commands, executed by phabalicious one by one. It will use the pattern-replacement known for scripts.

## The `questions`-section

This section contains a list of questions to be asked when running the scaffold-command. The yaml-key will get the value inputted by the user and can be used as a replacement pattern or in twig-templates. Answers can be validated against a regex and/or transformed to lower- or uppercase.

The following question-types are supported:


### `question`

A regular question, with optional default, validation and/ or autocomplete values. If `hidden` is true then the user wont see his input, ideal for sensitive data like passwords.

```yaml
key:
  question: the prompot to show
  type: question
  validation: the regex to use to validate the input
  error: the message to display, if the validation fails
  default: the default-value, when the user hits return
  transform: [lowercase|uppercase]
  hidden: [true|false]
  autocomplete:
    - Option 1
    - Option 2
    - Option 3
```

### `array`

A question, where the use can supply multiple values. The question gets repeated until the user provides an empty answer. For possible options see `question`.


### `confirm`

A question which can be answered with yes or no. The value will be `1` or `0`. The default value can be `true` or `false`.

```yaml
key:
  question: Should we continue?
  type: confirm
  default: true
```

### `choices`

Choices provides a list of options, where the user can pick one or many answers from:

```yaml
key:
  question: What fruits do you want to buy
  type: choices
  multiselect: true
  choices:
    - Apples
    - Oranges
    - Lemons
    - Papayas
    - Coconuts

```
### Non-interactive usage

For non-interactive usage you can pass the values via commandline-options, where the option-name is dash-cased version of the key, for the above example:

|command-line option|Question key|
|------|------|
| --[key] | [key] |
| --name | name |
| --short-name | shortName |

The scaffolder stores the answers in a hidden file called `.phab-scaffold-tokens` in the newly created folder and will reuse if the scaffolder runs aagain.

## The `scaffold`-section
Phabalicious will provide the following replacement-patterns out of the box:

|Pattern|Value|
|--------|-------|
|%rootFolder%|The folder the app will be installed into|
|%name%|The name of the app|
|%projectFolder%|a cleaned version of the app-name, suitable for machine-names.|
|%shortName%|The short-name|
|%uuid%|a random-uuid|

## the `variables`-section
You can add own variables via the `variables`-section. In the above example, the variable `composerProject` will be available in the `scaffold`-section via the pattern `%composerProject%`

## the `assets`-section
You can have multiple assets-sections, `assets` is a default one.  It contains a list if template files which will be processed by twig and placed at a specific location. You'll have all variables available as twig-variables inside the template, to use them, just use `{{ theNameOfVariable }}` e.g. `{{ name }}`
To add a new asset-section, just use a new root-level key (in the above example this would be `deploymentModuleAssets`

The assets-paths must be relative to the yaml-file containing the scaffold-commands.

## List of supported internal commands

* `copy_assets`
* `alter_json_file`
* `log_message`

These are documented [here](scaffolder.md).


## Inheritance

Similar to other parts of Phabalicious, scaffold-files can use inheritance, for example to use the above scaffold-file, but install drupal commerce:

```yaml
inheritsFrom:
  - drupal-8.yml
  -
variables:
  composerProject: drupalcommerce/project-base
```

This will inherit all content from the drupal-8.yml-file and merged with this content. This means, the `composerProject´-variable will be overridden, but everything else will be inherited. This makes it easy to reuse existing scaffold-files

## Examples

### Drupal 8 (d8.yml)

```yaml
variables:
  composerProject: drupal-composer/drupal-project:8.x-dev
  webRoot: web
assets:
  - .fabfile.yaml
  - docker-compose.yml
  - docker-compose-mbb.yml

deployModuleAssets:
  - deployModule/%shortName%_deploy.info.yml
  - deployModule/%shortName%_deploy.module
  - deployModule/%shortName%_deploy.install

sshKeys:
  - ssh-keys/docker-root-key
  - ssh-keys/docker-root-key.pub
  - ssh-keys/known_hosts

scaffold:
  - rm -rf %rootFolder%
  - composer create-project %composerProject% %rootFolder% --stability dev --no-interaction
  - cd %rootFolder%; composer require drupal/coffee
  - cd %rootFolder%; composer require drupal/devel
  - copy_assets(%rootFolder%)
  - copy_assets(%rootFolder%/%webRoot%/modules/custom/%shortName%_deploy, deployModuleAssets)
  - copy_assets(%rootFolder%/ssh-keys, sshKeys)
  - cd %rootFolder%; git init .
  - cd %rootFolder%; git add .
  - cd %rootFolder%; git commit -m "Initial commit by phabalicious"
```

### drupal commerce

```yaml
inheritsFrom:
  - d8.yml

variables:
  composerProject: drupalcommerce/project-base
```

### thunder

```yaml
inheritsFrom:
  - d8.yml

variables:
  composerProject: burdamagazinorg/thunder-project
  webRoot: docroot
```

### Laravel

#### the scaffold-file:
```yaml
variables:
  composerProject: laravel/laravel:5.4
  webRoot: public

assets:
  - .fabfile.yaml
  - docker-compose.yml
  - docker-compose-mbb.yml

scaffold:
  - rm -rf %rootFolder%
  - composer create-project %composerProject% %rootFolder% --stability dev --no-interaction
  - copy_assets(%rootFolder%)
  - cd %rootFolder%; git init .
  - cd %rootFolder%; git add .
  - cd %rootFolder%; git commit -m "Initial commit by phabalicious"
```

#### the fabfile-template

```yaml
name: {{ name }}
key: {{ shortName }}
deploymentModule: {{ shortName }}_deploy

requires: 2.0.0

needs:
  - ssh
  - composer
  - docker
  - git
  - files

hosts:
  mbb:
    host: {{ projectFolder }}.test
    user: root
    port: {{ 1024 + random(20000) }}
    type: dev
    rootFolder: /var/www/{{ webRoot }}
    gitRootFolder: /var/www
    backupFolder: /var/www/backups
    branch: develop
    database:
      name: {{ projectFolder|replace({'-': '_'}) }}_db
      user: root
      pass: admin
      host: mysql
```

