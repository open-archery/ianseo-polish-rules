#!/bin/sh
# macOS/Linux/Docker runner. Requires php on PATH (true in the ianseo-docker
# container and any standard PHP install) and tools/phpunit.phar downloaded.
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
PHP="${PHP:-php}"
exec "$PHP" -d display_startup_errors=0 "$DIR/phpunit.phar" "$@"
