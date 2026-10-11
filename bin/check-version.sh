#!/usr/bin/env bash
# Prüft, ob Plugin-Header, Konstante, readme.txt und (optional) der Git-Tag dieselbe Version tragen.
set -euo pipefail

cd "$(dirname "$0")/.."

header=$(sed -n "s/^ \* Version: *//p" mlab-forms.php | tr -d '[:space:]')
constant=$(sed -n "s/^define( 'MLAB_FORMS_VERSION', '\(.*\)' );/\1/p" mlab-forms.php)
stable=$(sed -n "s/^Stable tag: *//p" readme.txt | tr -d '[:space:]')
tag="${1:-}"

echo "Header ${header}, Konstante ${constant}, readme ${stable}${tag:+, Tag ${tag}}"

if [ "$header" != "$constant" ] || [ "$header" != "$stable" ]; then
	echo "Versionen stimmen nicht überein." >&2
	exit 1
fi

if [ -n "$tag" ] && [ "v${header}" != "$tag" ]; then
	echo "Tag ${tag} passt nicht zur Version ${header}." >&2
	exit 1
fi
