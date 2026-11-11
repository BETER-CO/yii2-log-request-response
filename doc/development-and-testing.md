# Development and testing

## PHP 8.5 runtime checks

The `php-runtime` Docker target provides PHP 8.5, Composer 2, Xdebug 3.5+, Intl, and ZIP
without installing the example application. It uses PHP's development configuration with
`E_ALL`, including deprecation reporting.

Run these commands from the repository root:

```bash
docker build --target php-runtime -f php.dev.Dockerfile \
  -t yii2-log-request-response-php:8.5-runtime .

docker run --rm --network none -e XDEBUG_MODE=off \
  -v "$PWD:/package-src:ro" -w /package-src \
  yii2-log-request-response-php:8.5-runtime \
  sh -ec 'php -v; composer --version; php-fpm -t; find src -name "*.php" -exec php -l {} \;; composer validate --no-check-publish'
```

The repository is mounted read-only. These checks validate the runtime, PHP-FPM configuration,
source syntax, and Composer metadata; they do not exercise application logging or install
package dependencies. `XDEBUG_MODE=off` disables debugging for these command-line checks.

## Run the example application

Install Docker with the Compose v2 plugin. PHP, Composer, and Nginx run in containers.
From the repository root:

```bash
docker compose -f docker-compose.dev.yml up --build -d
docker compose -f docker-compose.dev.yml logs -f
```

Open <http://localhost:8080/test/index>. Submit the form to exercise POST body masking.
The CSRF token, the `csrf-demo` value, and `user[password]` should be logged as
`[Masked]`; `user[username]` should remain visible. The root URL opens the same
test page. Requests are logged as guests; the example has no authentication flow.

To change the local port, use
`HOST_NGINX_PORT=8280 docker compose -f docker-compose.dev.yml up -d`.

Run the CLI example:

```bash
docker compose -f docker-compose.dev.yml exec -e XDEBUG_MODE=off \
  yii2-log-request-response-php php yii test/index
```

Look for `Incoming request` / `Outgoing response` in the PHP service logs and
`CLI command start` / `CLI command end` in the command output. These are manual
examples, with no automated test suite.

## Source and dependencies

The image copies the self-contained example from `deploy/example`
and installs that directory's committed Composer lock file. It does not generate a
Yii Basic application or install template dependencies.

Stable releases are used except for this package, which uses the local Composer path repository.
The library itself still supports PHP 7.4 and later.

During the build, `composer.json` and `src/` from this checkout are copied into
`/package-src`. At runtime, Compose mounts this repository read-only at that path.
The example uses a Composer symlink to it, so edits to `src/` take effect immediately.
It does not download a published release of this package.

The PHP service initializes a shared `test-app` Docker volume with the example app,
including its dependencies. Nginx mounts the same volume read-only. No manual
container creation or copy to `test-app-src` is required. An old `test-app-src`
directory from the previous setup is no longer used and can be kept or removed.

To update example dependencies and its tracked lock file:

```bash
docker compose -f docker-compose.dev.yml run --rm --no-deps \
  --user "$(id -u):$(id -g)" -e XDEBUG_MODE=off -e COMPOSER_HOME=/tmp/composer \
  -v "$PWD/deploy/example:/example" -w /example \
  yii2-log-request-response-php \
  composer update --no-install --no-scripts --no-interaction --prefer-dist
```

Review the lock-file changes, then rebuild and recreate the example volume as below.

## Rebuild and stop

Package source edits take effect through the bind mount. After changing the example
files, its dependencies, or the Dockerfile, recreate the example volume so it receives
the rebuilt image's application:

```bash
docker compose -f docker-compose.dev.yml down --volumes
docker compose -f docker-compose.dev.yml up --build -d
```

`down --volumes` deletes the generated example application and its runtime files.
It does not delete the repository. To stop the environment while preserving those
files, omit `--volumes`.

## Debugging

Xdebug is available for web and CLI debugging. Its settings are in
`deploy/php/conf.d/docker-php-ext-xdebug.ini`.
It connects to `host.docker.internal:9000`. For a CLI session:

```bash
docker compose -f docker-compose.dev.yml exec \
  -e PHP_IDE_CONFIG=serverName=localhost \
  yii2-log-request-response-php php yii test/index
```

PHP uses its development defaults with `E_ALL`. Keep deprecation reporting enabled
when checking compatibility.
