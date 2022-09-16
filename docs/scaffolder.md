---
parent: documentation
---
# Scaffolding arbitrary files

Phabalicious supports not only scaffolding new applications, but arbitrary files. The command allows to be extended by socalled plugins which implements `Phabalicious\Utilities\PluginInterface`. The following plugin types are currently supported:

* `transformers`, they take a yaml file as input, transform them to something different which will be written to the filesystem. transformer-plugins need to implement `Phabalicious\Scaffolder\Transformers\DataTransformerInterface`.


## Running the command

```bash
phab scaffold path/to/scaffold-file.yaml
```

If you want to preview the command, add the `--dry-run`-option, this will output all commands to the console instead of executing them.


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
* `decrypt_assets` to decrypt and copy assets and apply replacement patterns
* `alter_json_file` which will alter an existing json file and change some data
* `alter_yaml_file` which will alter an existing yaml file and change some data
* `assert_file` throws an exception if the file does not exist
* `assert_zero` will stop the execution if the argument is not zero
* `assert_non_zero` will stop the execution if the argument is zero
* `assert_contains` will stop the execution if the argument does nt contain given string
* `set_directory` sets the working directory  to the argument
* `encrypt_files` encrypt a bunch of files with a password and store them in a different directory
* `decrypt_files` decrypt a bunch of files with a password and store the encrypted content in a different directory

### List of commands which needs one or more plugin implementations

* `transform` to transform a bunch of yml files to sth different. This command needs an implementation via plugin

## Computed values

Similar to scripts, scaffolders do support computed values, by adding a `computedValues`-block to the yaml-file. Computed values get evaluated before the scaffold starts and store the results of the executed commands as variables which can be consumed later on in the script.

An example:

```yaml
computedValues:
    usingOutdatedScaffolder: cd %rootFolder% && grep -q drupal-composer/drupal-scaffold composer.json

scaffold:
    - assert_nonzero(%computed.usingOutdatedScaffolder%, "The project is using the outdated drupal scaffolder from contrib, please upgrade first!")
```

The value for `usingOutdatedScaffolder` gets evaluated before the scaffolder starts and get injected as `%computed.usingOutdatedScaffolder%` which can be used in the script if needed.

If the command (in this example `grep`) produces an output, then the output is used for the stored value. If no output is created, then the exitcode will be used for the value. See the documentation about [scripts](/scripts.md) for more info.

## Callbacks provided by phabalicious


### `copy_assets`

`copy_assets` can be used in the scaffold-section to copy assets  into a specific location. The syntax is

```
copy_assets(<targetFolder>, <assetsKey=assets>, <fileExtensionForTwigParsing>)
```

Phabalicious will load the asset-file, apply the replacement-patterns to the file-name ([see](app-scaffold.md) the `deploymentAssetsfor` an example) and parse the content via twig. The result will be stored inside the `<targetFolder>`. If `<fileExtensionForTwigParsing>` is set, then only files with that extension will be handled by twig.

### `decrypt_assets`

`decrypt_assets` can be used in the scaffold-section to decrypt and copy assets into a specific location. The syntax is

```
decrypt_assets(<targetFolder>, <assetsKey=assets>, <secretName>, <fileExtensionForTwigParsing>)
```

Phabalicious will load the asset-file, decrypt it and apply the replacement-patterns to the file-name ([see](app-scaffold.md) the `deploymentAssetsfor` an example) and parse the content via twig. The result will be stored inside the `<targetFolder>`. If `<fileExtensionForTwigParsing>` is set, then only files with that extension will be handled by twig.

### `log_message(severity, message)`

`log_message` will log a string to the output of phabalicious. It supports several notification levels, e.g.

```yaml
scaffold:
  - log_message(info|warning|error|success, the message to display)
```

### `alter_json_file(file_path, data_ref)`

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

### `alter_yaml_file(file_path, data_ref)`

This internal command can alter a yaml-file. It will merge the data from a yaml section into the yaml file. Note that the order in the resulting yaml file might be different, also comments might get removed. Here's an example:

```yaml

dataToInject:
  one: foo
  two: bar
  dict:
    one: boo
    two: far

scaffold:
  alter_yaml_file(config.yaml, dataToInject)
```

### `assert_zero(variable, error_message)`

This internal command will throw an exception if the specified argument is not zero.

```yaml
variables:
    foo: 1
scaffold:
  - assert_zero(%foo%, foo is not zero)
```

### `assert_non_zero(variable, error_message)`

This internal command will throw an exception if the specified argument is zero.

```yaml
variables:
    foo: 0
scaffold:
  - assert_non_zero(%foo%, foo is zero)
```

### `assert_contains(needle, haystack)`

This internal command will throw an exception if `needle` is not found in `haystack`

```yaml
scaffold:
  - assert_contains(foo, bar, Could not find foo)
-
```

### `assert_file(file_path, error_message)`

This internal command will throw an exception if the specified file does not exist. Useful to check if the user is in the right directory.

```yaml
scaffold:
  - assert_file(<file_path>, <error_message>)
```

### `assert_file(file_path, error_message)`

This internal command will throw an exception if the specified file does not exist. Useful to check if the user is in the right directory.

```yaml
scaffold:
  - assert_file(<file_path>, <error_message>)
```

### `assert_file(file_path, error_message)`

This internal command will throw an exception if the specified file does not exist. Useful to check if the user is in the right directory.

```yaml
scaffold:
  - assert_file(<file_path>, <error_message>)
```

### `set_directory(directory)`

This internal command will change the current directory for the following commands to `directory`.

```yaml
scaffold:
  - set_directory(<directory>)
  - echo $PWD # will output <directory>
```

### `confirm(message)`

This internal command will ask the user for confirmation before continuing showing `message`.

```yaml
scaffold:
  - confirm(<message>)
```

### `scaffold(url, rootFolder)`

This internal command will run another scaffolder from the given `url` or filepath into the given `rootFolder`. Additional arguments in the form of `key=value` will be passed to the scaffolder.

```yaml
questions: []
assets: []
variables:
themeFolder: "%rootFolder%/web/themes/custom/some_frontend"

scaffold:
    - scaffold("http://foo.bar/d8.yml", "%rootFolder%")
    - scaffold("http://foo.bar/d8-theme.yml", "%themeFolder%")
    - scaffold("http://foo.bar/d8-module.yml", "%rootFolder%/web/modules/custom/d8-module", "key1=value1", "key2=value2")
```

### `transform`

This internal command will transform a list of yml files to sth different with the help of plugins. THe plugins need to be declared in the `plugins`-section.

```yaml
scaffold:
  - transform(<nameOfPlugin>, <yamlKeyToGetListOfFilesFrom>, <targetPath>)
```

* `<nameOfPlugin>` is the plugin-name to use for the trnasforming. Depends on the plugin implementation
* `<yamlKeyToGetListOfFilesFrom>` Similar to `copy_assets`, its a reference in the yaml-file which contains a list of files/ directories where phabalicious will try to load yaml files from.
* `<targetPath>` the target directory where the resulting files should be saved to.


## `encrypt_files`

This internal command will encrypt a given set of files, encrypt their contents and write it to a target folder.

```yaml
scaffold:
  - encrypt_files(path/to/files/or/folders/to/encrypt/*.ext, path/to/folder/to/store/encrypted/files, name-of-secret)
```

It gets the password from the secret and use it for encryption. The encrypted files are stored in the target folder with the new extension `.enc`.

## `decrypt_files`

This internal command will decrypt a given set of files, decrypt their contents and write it to a target folder.

```yaml
scaffold:
  - decrypt_files(path/to/files/or/folders/to/decrypt/*.enc, path/to/folder/to/store/decrypted/files, name-of-secret)
```

It gets the password from the secret and use it for descryption. The decrypted files are stored in the target folder, the extension `.enc` will be removed if necessary.



## Twig extensions

`copy_assets` will use twig to replace any configuration values inside the files with their values. You can use all functions and filters, available with twig.

Additionally these filters are available:

* `md5` e.g. `{{ "hello world" | md5 }}` will result in `f0ef7081e1539ac00ef5b761b4fb01b3`
* `slug` e.g. `{{ "a string with ümlauts" }}` will result in `a-string-with-umlauts`
* `decrypt` e.g. `{{ "def50200e2013402c4c4440412f94d68044078577b07d67d32d59e5befb2870335de767120081a3be2dc03ddc9781b1e61a7b8ba39f25f7dad88c5be653526a17b6c2dc35ed53397da12d84c327b0f05fb7ac66600fa057b72c5084684aeea" | decrypt('my-secret-name') }} will result in a decrypted string.
* `encrypt` e.g. `{{ "hello world" | encrypt('my-secret-name') }} will result in an encrypted string.
