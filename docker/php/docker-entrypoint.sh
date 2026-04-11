#!/bin/sh
set -eu

PHP_CONFIG_DIR="/usr/local/etc/php/conf.d"
TARGET_INI="${PHP_CONFIG_DIR}/zz-myxa.ini"
APP_ENV="${APP_ENV:-local}"

case "${APP_ENV}" in
    production|prod)
        ENV_INI="/usr/local/etc/php/myxa/php.prod.ini"
        ;;
    *)
        ENV_INI="/usr/local/etc/php/myxa/php.dev.ini"
        ;;
esac

cat /usr/local/etc/php/myxa/php.common.ini "${ENV_INI}" > "${TARGET_INI}"

exec docker-php-entrypoint "$@"
