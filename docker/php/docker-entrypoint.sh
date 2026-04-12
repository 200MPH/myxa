#!/bin/sh
set -eu

PHP_CONFIG_DIR="/usr/local/etc/php/conf.d"
TARGET_INI="${PHP_CONFIG_DIR}/zz-myxa.ini"
APP_ENV="${APP_ENV:-local}"
APP_ROOT="/var/www/html"
STORAGE_DIR="${APP_ROOT}/storage"

case "${APP_ENV}" in
    production|prod)
        ENV_INI="/usr/local/etc/php/myxa/php.prod.ini"
        ;;
    *)
        ENV_INI="/usr/local/etc/php/myxa/php.dev.ini"
        ;;
esac

cat /usr/local/etc/php/myxa/php.common.ini "${ENV_INI}" > "${TARGET_INI}"

if [ -d "${APP_ROOT}" ]; then
    mkdir -p "${STORAGE_DIR}/data/logs"
    chmod -R a+rwX "${STORAGE_DIR}" 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
