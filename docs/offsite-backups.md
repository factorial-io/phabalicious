# Offsite backups

Phab supports onsite- and offsite-backups. Onsite means, the backups are stored on the same host as the application is run. Depending on the hosting setup this might not be enough as the backups might not be stored forever, e.g. if the backup folder is not mapped to a persistent volume in kubernetes.

That's why phab supports offsite-backups beginning with 3.6 using [restic](https://restic.readthedocs.io/en/latest/index.html). Restic is a powerful backup application, with no dependencies, easy to install and supports multiple hosts and encrypted backups. Restic itself supports different storage-providers.

Phabalicious will try to install restic if it cant find an executable on the host.

## An example configuration

```yaml

secrets:
  restic:
    question: What is the password for restic

knownHosts:
    - myhost:myport

restic:
  environment:
    RESTIC_PASSWORD: %secrets.restic%
  repository: sftp://myuser@myhost:myport/offsite-backups
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

Make sure to add an entry to `knownHosts` when you are using a sftp repository, otherwise the backups might fail because the host is unknown to the local ssh.

## How phabalicious stores metadata into the repository

Phabalicious encodes the project- and the config-name as the hostname when doing backups. That means you can reuse a repository for multiple configurations and even projects. This might be handy if they share files etc as restic support deduplication. When listing backups or restoring backups, phabalicious will filter the snapshots in the repository by config- and project-name. But you can interact with the repo just using restic.


## List backups

List backups will list all snapshots for that particular config and project.

```bash
❯ phab list:backups


List of backups
===============

 ------------ ---------- -------- ---------------------------------------------- ----------------------------------------------------------------------
  Date         Time       Type     Hash                                           File
 ------------ ---------- -------- ---------------------------------------------- ----------------------------------------------------------------------
  2021-03-16   05:03:55   restic   projectname--mbb--cf421674                     /var/www/backups/mbb--0.1.41-6-g8bf90d5--2021-03-16--17-45-53.sql.gz
                                                                                  /var/www/web/sites/default/files
  2021-03-15   21-45-40   db       mbb--0.1.41-6-g8bf90d5--2021-03-15--21-45-40   mbb--0.1.41-6-g8bf90d5--2021-03-15--21-45-40.sql.gz
  2021-03-15   09:03:43   restic   projectname--mbb--26a61863                     /var/www/backups/mbb--0.1.41-6-g8bf90d5--2021-03-15--21-45-40.sql.gz
                                                                                  /var/www/web/sites/default/files
 ------------ ---------- -------- ---------------------------------------------- ----------------------------------------------------------------------
```

Note the duplication of the backupd database dumps. To restore a database dump, you need to run 2 restores: one, to restore the actual sql file then another one to restore the sql back into the database.

## Restoring a backup

It's the same as ususal, just run `phab -cconfig restore <backup-hash>` e.g. `phab -cmbb restore projectname--mbb--26a61863`. But use that with caution, as it might override exsiting files! If you want to restore the files to a different destination, use restic directly.


