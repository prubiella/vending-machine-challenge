#!/bin/bash -e

if [[ ! -f .env ]]; then
  echo "No .env file: run 'make first-run' before committing." >&2
  exit 1
fi

source .env

docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e HOME=/tmp \
  -w /var/www \
  -v "$(pwd):/var/www" \
  "${PROJECT_HOSTNAME}-php-fpm" \
  sh -c 'git config --global --add safe.directory /var/www && "$@"' -- "$@"
