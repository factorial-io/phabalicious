---
sidebarDepth: 3
---
# Deploying artifacts

If you want to deploy artifacts, phabalicious can help you here, currently there are two methods supported:

* artifacts--git
* artifacts--ftp

Both methods create a new application from scratch and deploy all or parts of it to a ftp server or store the artifacts in a git repository. Both methods are using the same stage mechanisms as in `app:create`.

Both methods needs the global `repository`-config, so they can pull the app into a new temporary folder.

## artifacts--ftp

ftp will create a new application in a temporary folder, install all needed dependencies, copies the data into a new temporary folder and run the deploy script of the host-config. After that is finished, phab will mirror all changed files via `lftp` to the server. `lftp` supports multiple protocols, not only ftp, but it needs to be installed on the machine running phab.

ftp-sync use the following stages:

* `installCode` will most of the time clone the source repository
* `installDependencies` will install all needed dependencies
* `runActions` will run all defined actions (see below)
* `runDeployScript` run the deploy-script of the host to apply some custom changes
* `syncToFtp` sync all changed files with the remote ftp server.

### Example config

```yaml

hosts:
  ftp-artifacts:
    needs:
      - git
      - composer
      - artifacts--ftp
      - script
    artifact:
      user: stephan
      host: sftp://localhost
      port: 22
      password: my-secret
      rootFolder: /home/stephan/somewhere
      actions:
        - action: copy
          arguments:
            from: "*"
            to: .
        - action: script
          arguments:
            - cp .env.example .env
        - action: delete
          arguments:
            - .git/
            - .fabfile
            - .editorconfig
            - .env.example
            - .gitattributes
            - .gitignore
            - composer.lock
            - composer.json
            - docker-compose.yml
        - action: script
          arguments:
            - ls -la
        - action: confirm
          arguments:
            question: Do you want to continue?

```

The default actions for the ftp-artifact-method will copy all files to the target repo and remove the `.git`-folder and the fabfile.

## artifacts--git

This method will create the artifact, pull the target repository, copy all necessary files over to the target repository, commit any changes to the target repository and push the changes again. A CI listening to commits can do the actual deployment

It is using the following stages:

* `installCode`, creates a temporary folder and pulls the source repository. (only when `useLocalRepository` is set to false)
* `installDependencies` to install the dependencies
* `getSourceCommitInfo` get the commit hash from the source repo.
* `runActions` will run all defined actions (see below)
* `copyFilesToTargetDirectory`, copy specified `files` to the target directory, removes all files listed in `excludeFiles.gitSync`
* `runDeployScript` run the deploy script of the host-config.
* `pushToTargetRepository` commit and push all changes, using the changelog as a commit-message

If you run phabalicious as part of a CI-setup, it might make sense to set `useLocalRepository` to true, as this will instruct phab to use the current folder as a base for the artifact and won't create a new application in another temporary folder.

### Example config

```yaml
hosts:
  git-artifact:
    branch: develop
    needs:
      - git
      - composer
      - artifacts--git
      - script
    artifact:
      gitOptions:
        clone:
          - --depth 10
      branch: develop
      baseBranch: master
      repository: ssh://somewhere/repository.git
      useLocalRepository: false
      actions:
        - action: copy
          arguments:
            from: '*'
            to: .
        - action: script
          arguments:
            - cp .env.example .env
        - action: delete
          arguments:
            - .env.example
            - composer.json
            - composer.lock
            - docker-compose.yml
            - docker-compose-mbb.yml
            - .projectCreated
```

the default actions for the git-artifact-method will copy all files to the target repo and remove the fabfile.

| property              | default value | description                               |
|-----------------------|---------------|-------------------------------------------|
| `artifact.branch`     | `false`       | if set to false, the name of the source-branch is used, otherwise the value |
| `artifact.baseBranch` | `master`      | If phabalicious needs to create a new branch, because it is missing from the target-repository, then start the branch from `masterBranch` |
| `artifact.repository` |               | The url to the target-repository          |
| `artifact.actions`    |               | Actions to perform                        |


Phab will use a shallow clone for the target repository to keep resources low. If you need to use a deep copy for the target repository, you can adapt the gitOptions like:
```
hosts:
  git-artifacts:
    artifact:
      gitOptions:
        clone: []
```

If the target repository contains commits not added by the artifact deployment, a warning will be shown to the user, with a list of commits and changed files. The user can confirm that he wants to continue, or stop the execution.

You can force the artifact based deployment by adding the `--force`-option, or by setting the `PHABALICIOUS_FORCE_GIT_ARTIFACT_DEPLOYMENT` environment variable to 1.

### artifacts--custom

@TODO

## Available actions

You can customize the list of actions be run when deploying an artifact. Here's a list of available actions

### copy

```yaml
- action: copy
  argumnents:
    from:
      - file1
      - folder2
      - subfolder/file3
    to: targetsubfolder
```

This will copy the three mentioned files and folders into the subfolder `targetsubfolder` of the target folder. Please be aware, that you might need to create subdirectories beforehand manually via the `script`-method. Also be aware that copy action deletes existing files and folders from target before doing the copy, if you want to combine files from multiple sources it is better to also use the `script`-method for that.

### delete

```yaml
- action: delete
  arguments:
    - file1
    - folder2
    - subfolder/file3
```

This action will delete the list of files and folders in the target folder. Here you can clean up the target and get rid of unneeded files.

### exclude

```yaml
- action: exclude
  arguments:
    - file1
    - folder2
    - subfolder/file3
```

Similar to `delete` this will exclude the list of file and folders from be transferred to the target. For `ftp` the list of files get excluded from transferring, for `git` they will get resetted from the target repository.

### confirm

```yaml
- action: confirm
  arguments:
    question: Do you want to continue?
```

This action comes handy when degugging the build process, as it will stop the execution and asks the user the questions and wait for `yes` before continuing. Answering sth different will cancel the further execution.

### script

```yaml
- action: script
  arguments:
    - echo "Hello world"
    - cp .env.production .env
- action: script
  arguments:
    name: compile:vue
- action: script
  arguments:
    context: docker-image
    image: node:12
    script:
        - npm install
        - npm run build
```

The `script`-action will run the script from the arguments section line by line. You can use the usual replacement patterns as for other scripts. Most helpful are:

| Pattern | Description |
|---------|-------------|
| `%context.data.installDir%` | The installation dir, where the app got installed into |
| `%context.data.targetDir%` | The targetdir, where the app got copied to, which gets committed or synced |

If `arguments` contains a name, then this named script will be executed. It should be available under the global `scripts`-section or on the hosts' scripts section.

Since 3.7 phab supports executing a script in a different context. If you set `arguments.context` to `docker-image` the lines in `script` will be executed in a docker-container started from the named docker-image provided via the `image`-argument.

Note, that the container will be removed after the script finishes. Also the current user and group will be used inside the container to reduce issues with permissions. The current host-directory is available at `/app` inside the container. If you want to keep files after the script finishes, make sure they are inside `/app`.

### message

```yaml
- action: message
  arguments:
    message: Hello world!
    type: (comment|note|warning|error|success)
```

Prints out a message regardless of the log-level using symfony styles.

### log

```yaml
- action: log
  arguments:
    message: Hello world!
    severity: (debug|info|notice|warning|error)
```

Logs a message, will be visible according to the verbosity-level and the used severity.

