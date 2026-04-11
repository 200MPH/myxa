#!/bin/sh
set -eu

CERT_DIR="/etc/nginx/certs"
CERT_FILE="${CERT_DIR}/local.crt"
KEY_FILE="${CERT_DIR}/local.key"
APP_HOST="${APP_HOST:-myxa.localhost}"

mkdir -p "${CERT_DIR}"

if [ ! -f "${CERT_FILE}" ] || [ ! -f "${KEY_FILE}" ]; then
    openssl req \
        -x509 \
        -nodes \
        -newkey rsa:2048 \
        -days 3650 \
        -subj "/CN=${APP_HOST}" \
        -addext "subjectAltName=DNS:${APP_HOST},DNS:localhost" \
        -keyout "${KEY_FILE}" \
        -out "${CERT_FILE}"
fi
