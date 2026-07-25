#!/usr/bin/env bash
# Runs Plugin Check against the plugin, scoped to what the release ZIP
# actually ships.
#
# Plugin Check has no ignore-file support — only --exclude-directories and
# --exclude-files, which just feed the wp_plugin_check_ignore_* filters. So the
# flags are derived here from .distignore, which already defines exactly what
# stays out of the ZIP. Add an entry there and this check follows it; there is
# no second list to keep in sync.
#
# Every entry is passed to both flags. Plugin Check matches them as plain
# strings against file paths, never touching the filesystem:
#
#   directories  strpos( $path, "/$entry/" )
#   files        str_ends_with( $path, "/$entry" )
#
# so an entry only ever matches in the sense it was meant to, and the script
# does not have to guess which entries are directories (which would otherwise
# depend on what happens to exist on disk at the time).
#
# Usage: bin/plugin-check.sh [extra wp plugin check flags...]
set -euo pipefail

cd "$(dirname "$0")/.."

entries=()

while IFS= read -r entry || [ -n "$entry" ]; do
	# Strip a trailing CR (in case .distignore is ever saved CRLF) and
	# surrounding whitespace, then skip blanks and comments.
	entry="${entry%$'\r'}"
	entry="${entry#"${entry%%[![:space:]]*}"}"
	entry="${entry%"${entry##*[![:space:]]}"}"
	case "$entry" in
		'' | '#'*) continue ;;
	esac
	entries+=("$entry")
done < .distignore

if [ ${#entries[@]} -eq 0 ]; then
	echo "bin/plugin-check.sh: no entries parsed from .distignore" >&2
	exit 1
fi

excludes=$(
	IFS=,
	echo "${entries[*]}"
)

# Always the project-local wp-env. A globally installed wp-env is a different
# version with a different state directory, so it reports "Environment not
# initialized" against containers this project started — invoking it by bare
# name would make the script depend on the caller's PATH.
wp_env=node_modules/.bin/wp-env

if [ ! -x "$wp_env" ]; then
	echo "bin/plugin-check.sh: $wp_env not found — run 'pnpm install' first" >&2
	exit 1
fi

exec env COMPOSE_PROJECT_NAME=reinventx-forms \
	"$wp_env" run cli wp plugin check reinventx-forms \
	--exclude-directories="$excludes" \
	--exclude-files="$excludes" \
	"$@"
