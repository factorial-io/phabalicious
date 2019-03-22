# Scaffolding a new app

Phabalicious has a simple but powerful scaffold-command. It will read a yml-file and interpret the contents. It will use the existing pattern-replacement used for scripts and twig for changing file-contents. The scaffolding and fixture-files can live remotely or on your file-system.

Here's an example of a scaffold-file:

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

An example:

```
questions:
  <key>: 
    question: <the text to display>
    validation: <the regex to check the input against to>
    error: <the error message to display, when validation fails>
    default: <optional default-value>
    transform: <lowercase|uppercase>
```

For non-interactive usage you can pass the values via commandline-options, where the option-name is the same as the key, for the above example: 

|command-line option|Question key|
|------|------|
| --\<key\> | \<key\> |
| --name | name |
| --short-name | shortName |

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

## the internal command `copy_assets`

`copy_assets` can be used in the scaffold-section to copy assets  into a specific location. The syntax is

```
copyAssets(<targetFolder>, <assetsKey=assets>)
```

Phabalicious will load the asset-file, apply the replacement-patterns to the file-name (see the deploymentAssets for an example) and parse the content via twig. The result will bee stored inside the `<targetFolder>`

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
  - cd %rootFolder%; composer require factorial-io/fabalicious:dev-develop
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
  
