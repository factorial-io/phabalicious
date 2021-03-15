# Offsite backups

Phab supports onsite- and offsite-backups. Onsite means, the backups are stored on the same host as the application is run. Depending on the hosting setup this might not be enough as the backups might not be stored forever, e.g. if the backup folder is not mapped to a persistent volume in kubernetes.

That's why phab supports offsite-backups beginning with 3.6 using [restic](https://restic.readthedocs.io/en/latest/index.html). Restic is a powerful backup application, with no dependencies, easy to install and supports multiple hosts and encrypted backups. Restic itself supports different storage-providers.

Phabalicious will try to install restic if it cant find an executable on the host.

Example configuration:

```yaml

secrets:
  restic:
    question: What is the password for restic

restic:
  environment:
    RESTIC_PASSWORD: %secrets.restic%
  repository: sftp://myuser@myhost/offsite-backups
  options:
    - --verbose
  downloadUrl: 'https://github.com/restic/restic/releases/download/v0.12.0/restic_0.12.0_linux_amd64.bz2'
```

| key           | Description                                                     |
|---------------|-----------------------------------------------------------------|
| `environment` | a dicitionary with all environment variables to pass to restic. |
| `repository`  | The target repository to store the backup into.                 |
| `options`     | A list of additional options to pass to restic                  |
| `downloadUrl` | In case restic is not found for a particular host, this settings contains the download-url. Phab will use `curl` and `bunzip2` to download and install the binary |


When running `phab -cconfig backup` this will backup the database dump and the file directories to the `offsite-backups` repository.


Support for `list-backups` and `restore` is missing and might be implemented later.
