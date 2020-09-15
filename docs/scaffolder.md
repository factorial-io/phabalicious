---
sidebarDepth: 3
---
# Scaffolding arbitrary files

Phabalicious supports not only scaffolding new applications, but arbitrary files. The command allows to be extended by socalled plugins which implements `Phabalicious\Utilities\PluginInterface`. The following plugin types are currently supported:

* `transformers`, they take a yaml file as input, transform them to something different which will be written to the filesystem. transformer-plugins need to implement `Phabalicious\Scaffolder\Transformers\DataTransformerInterface`.


## Running the command

```bash
phab scaffold path/to/scaffold-file.yaml
```

## the scaffold-file

The scaffold-file has the same structure as used for scaffolding applications. Here's an example:

```yaml
requires: 3.4

plugins:
  - vendor/factorial-io/phab-entity-scaffolder/src/transformers

image_styles:
  - image_style

block_content:
  - block_content

scaffold:
   - transform(block_content, block_content, config/sync)
   - transform(imagestyles, image_styles, config/sync)
```

* `requires` set the minimal phabalicious version this file works with.
* the `plugins`-section reference paths to phabalicious-plugins. Every php file in that folder will be tried to be included and checked if they implement the required interfaces.
* `scaffold` contains a list of script lines where replacement patterns a]get replaced and run via bash, if not an internal command is used.
* It may also contain `assets` and/or `questions` as described in [here](app-scaffold.md), but they are not required.

### List of supported internal commands:

* `log_message` to print a message with a severity
* `copy_assets` to copy assets and apply replacement patterns
* `alter_json_file` which will alter an existing json file and change some data
* `assert_file` throws an exception if the file does not exist

### List of commands which needs one or more plugin implementations

* `transform` to transform a bunch of yml files to sth different. This command needs an implementation via plugin


## `copy_assets`

`copy_assets` can be used in the scaffold-section to copy assets  into a specific location. The syntax is

```
copy_assets(<targetFolder>, <assetsKey=assets>, <fileExtensionForTwigParsing>)
```

Phabalicious will load the asset-file, apply the replacement-patterns to the file-name ([see](app-scaffold.md) the `deploymentAssetsfor` an example) and parse the content via twig. The result will be stored inside the `<targetFolder>`. If `<fileExtensionForTwigParsing>` is set, then only files with that extension will be handled by twig.

## `log_message(severity, message)`

`log_message` will log a string to the output of phabalicious. It supports several notification levels, e.g.

```yaml
scaffold:
  - log_message(info|warning|error|success, the message to display)
```

## `alter_json_file(file_path, data_ref)`

This internal command can alter a json-file. It will merge the data from a yaml section into the json file. Here's an example:

```yaml

dataToInject:
  one: foo
  two: bar
  dict:
    one: boo
    two: far

scaffold:
  alter_json_file(package.json, dataToInject)
```

## `assert_file(file_path, error_message)`

This internal command will throw an exception if the specified file does not exist. Useful to check if the user is in the right directory.

```yaml
scaffold:
  - assert_file(<file_path>, <error_message>)
```

## `confirm(message)`

This internal command will ask the user for confirmation before continuing showing `message`.

```yaml
scaffold:
  - confirm(<message>)
```

## `transform`

This internal command will transform a list of yml files to sth different with the help of plugins. THe plugins need to be declared in the `plugins`-section.

```yaml
scaffold:
  - transform(<nameOfPlugin>, <yamlKeyToGetListOfFilesFrom>, <targetPath>)
```

* `<nameOfPlugin>` is the plugin-name to use for the trnasforming. Depends on the plugin implementation
* `<yamlKeyToGetListOfFilesFrom>` Similar to `copy_assets`, its a reference in the yaml-file which contains a list of files/ directories where phabalicious will try to load yaml files from.
* `<targetPath>` the target directory where the resulting files should be saved to.
