# Deploying artifacts

If you want to deploy artifacts, phabalicious can help you here, currently there are two methods supported:

* artifacts--git-sync
* artifacts--ftp-sync

Both methods create a new application from scratch and deploy all or parts of it to a ftp server or store the artifacts in a git repository. Both methods are using the same stage mechanisms as in `app:create`. You can exclude files and folders by adding the names to the global `excludeFiles`-configuration:

```yaml
excludeFiles:
  ftpSync:
    - .gitignore
  gitSync:
    - .fabfile.yaml
    - .env.*
```

Both methods needs the global `repository`-config, so they can pull the app into a new temporay folder.

## ftp-sync

ftp-sync will create a new application in a temporary folder, install all needed dependencies, and run the deploy script of the host-config. After that is finished, phab will mirror all changed files via `lftp` to the server. `lftp` supports multiple protocols, not only ftp, but it needs to be installed on the machine running phab.

ftp-sync use the following stages:

* `installCode` will most of the time clone the source repository
* `installDependencies` will install all needed dependencies
* `runDeployScript` run the deploy-script of the host to apply some custom changes

### Example config

```yaml
excludeFiles:
  ftpSync:
    - ".gitignore"
    - ".gitattributes"
    - ".fabfile.yaml"
    - ".gitlab-ci.yml"
    - ".env.*"
    - "*.sql"
    - "composer.*"
    - "docker-compose*.yml"
    - "yarn.lock"

hosts:
  ftp-sync:
    deploy:
      - cd %context.data.installDir%; cp .env.example .env
    ftp:
      user: stephan
      host: sftp://localhost
      port: 22
      password: my-secret
      rootFolder: /home/stephan/tmp/test-git-sync
    needs:
      - git
      - composer
      - artifacts--ftp-sync
      - script

```

## git-sync

git-sync will create the artifact, pull the target repository, copy all necessary files over to the target repository, commit any changes to the target repository and push the changes again. A CI listening to commits can do the actual deployment

It is using the following stages:

* `installCode`, creates a temporary folder and pulls the source repository. (only when `useLocalRepository` is set to false)
* `installDependencies` to install the dependencies
* `getSourceCommitInfo` get the commit hash from the source repo.
* `pullTargetRepository` pulls the target repository into an temporary folder
* `copyFilesToTargetDirectory`, copy specified `files` to the target directory, removes all files listed in `excludeFiles.gitSync`
* `runDeployScript` run the deploy script of the host-config.
* `pushToTargetRepository` commit and push all changes, using the changelog as a commit-message

If you run phabalicious as part of a CI-setup, it might make sense to set `useLocalRepository` to true, as this will instruct phab to use the current folder as a base for the artifact and won't create a new application in another temporary folder.

### Example config

```yaml
excludeFiles:
  gitSync:
    - ".fabfile.yaml"
    - ".gitlab-ci.yml"
    - "composer.*"
    - "docker-compose*.yml"
    - "yarn.lock"

hosts:
  git-sync:
    appCreateCopyFilesToTargetDirectoryPrepare:
      - cd %context.data.installDir%; cp .env.example .env

    gitSync:
      targetBranch: master
      targetRepository: ssh://my.git.server/my-repository.git
      useLocalRepository: false
      files:
        - sites/all/modules
        - sites/all/libraries
        - sites/all/themes
    needs:
      - git
      - composer
      - artifacts--git-sync
      - script
```

## Customizing the artifacts

* Use `excludeFiles` to remove files from your artifact, use `files` to define a list of files you want to copy over. 
* You can add custom scripts to your host-configuration which will be run before a stage is entered or after a stage is finished. They follow a naming-convention:
    * `appCreate<NameOfStage>Before`
    * `appCreate<NameOfStage>Finished`
    
   e.g. `appCreateInstallDependenciesBefore` or `appCreatePushToTargetRepositoryFinished`. All context variables are exposed to scripts as `context.data.*` or `context.results.*`


