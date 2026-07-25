#!/usr/bin/env bash
# Verifies that a release tag matches every place the version is recorded,
# so header, constant, and readme can never drift (ARCHITECTURE §9).
#
# Usage: bin/check-version.sh v0.1.0
set -euo pipefail

TAG="${1:?usage: bin/check-version.sh vX.Y.Z}"
VERSION="${TAG#v}"

cd "$(dirname "$0")/.."

fail=0

check() {
	local label="$1" actual="$2"

	if [ "$actual" = "$VERSION" ]; then
		echo "ok   ${label}: ${actual}"
	else
		echo "FAIL ${label}: '${actual}' != '${VERSION}'"
		fail=1
	fi
}

header=$(sed -n 's/^ \* Version:[[:space:]]*//p' reinventx-forms.php | tr -d '[:space:]')
constant=$(sed -n "s/.*REINVENTX_VERSION'[[:space:]]*,[[:space:]]*'\([^']*\)'.*/\1/p" reinventx-forms.php)
stable=$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | tr -d '[:space:]')
# Parsed as JSON, not as text: a reformat (tabs vs spaces, key order) must
# never silently blank this out and turn a real mismatch into a pass.
package=$(node -p "require('./package.json').version")

check "plugin header"      "$header"
check "REINVENTX_VERSION"  "$constant"
check "readme stable tag"  "$stable"
check "package.json"       "$package"

exit "$fail"
