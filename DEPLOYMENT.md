# Automatic deployment

Pushes to `main` deploy to `/var/www/Hotel-Order-Management` using
`.github/workflows/deploy.yml`. You can also run it from GitHub's Actions tab.

## One-time server setup

The application must already be installed at that path, with a production `.env`,
an existing `APP_KEY`, and working database credentials. The server needs PHP 8.2+
with the application's required extensions, Composer 2, rsync, and SSH.
The web server document root must be `/var/www/Hotel-Order-Management/public`.

The workflow connects with an SSH username and password, including `root` if
configured. The server must permit password login for that account. The SSH user
needs write access to the application folder, and the web server user needs write
access to `storage` and `bootstrap/cache` (configure a shared group or ACLs).

## GitHub repository secrets

Open the repository's **Settings > Secrets and variables > Actions** and add:

| Secret | Value |
| --- | --- |
| `DEPLOY_HOST` | Server IP address or hostname |
| `DEPLOY_USER` | SSH username, e.g. `root` |
| `DEPLOY_PASSWORD` | SSH login password |
| `DEPLOY_PORT` | Optional SSH port; defaults to `22` |

No SSH key or known_hosts secret is required. The runner obtains the server host
key with `ssh-keyscan` at deployment time. This trusts the network response rather
than an independently verified fingerprint.

## Deployment behavior

The runner builds frontend assets with Node 22. It puts the existing app into
maintenance mode, syncs code and built assets, installs production PHP dependencies,
runs migrations, refreshes Laravel caches, restarts queue workers, and restores access.
The server's `.env`, uploads/storage, dependencies, SQLite databases, and runtime
cache directory are excluded from synchronization. Other files removed from the
repository are also removed from the deployment folder.

Deployments run one at a time. This is an in-place deployment with a short maintenance
window. A failed deployment attempts to restore access but does not roll back code
or database migrations; inspect the Actions log and fix or redeploy. Back up the
production database before deploying schema changes. If PHP OPcache timestamp
validation is disabled, arrange a PHP-FPM reload through your server management.
