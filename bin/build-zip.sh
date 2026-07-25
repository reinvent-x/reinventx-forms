#!/usr/bin/env bash
# Assembles the release ZIP honoring .distignore. Expects production
# artifacts to exist already:
#   composer install --no-dev --optimize-autoloader
#   pnpm install --frozen-lockfile && pnpm run build
#
# Output: dist/reinventx-forms.zip (contains a single reinventx-forms/ directory).
set -euo pipefail

cd "$(dirname "$0")/.."

test -f build/admin.js || { echo "build/admin.js missing — run pnpm run build"; exit 1; }
test -f vendor/autoload.php || { echo "vendor/autoload.php missing — run composer install"; exit 1; }

if [ -d vendor/phpunit ]; then
	echo "vendor/ contains dev dependencies — run composer install --no-dev"
	exit 1
fi

rm -rf dist
mkdir -p dist/reinventx-forms

rsync -a --exclude-from=.distignore --exclude=dist ./ dist/reinventx-forms/

( cd dist && zip -rq reinventx-forms.zip reinventx-forms )

echo "dist/reinventx-forms.zip:"
unzip -l dist/reinventx-forms.zip | tail -3

# The ZIP must never contain sources, tests, CI config, or the wp.org
# directory assets (those go to SVN assets/, not into the plugin).
for forbidden in client/ tests/ .github/ node_modules/ assets/ package-lock.json composer.lock; do
	if unzip -l dist/reinventx-forms.zip | grep -q "reinventx-forms/${forbidden}"; then
		echo "FAIL: ${forbidden} leaked into the ZIP"
		exit 1
	fi
done

echo "ZIP contents verified clean."
