#!/usr/bin/env bash
# Baut dist/mlab-forms.zip mit den Ausschlüssen aus .distignore und prüft das Ergebnis.
# Wird lokal, in der CI und im Release-Workflow verwendet.
set -euo pipefail

cd "$(dirname "$0")/.."

slug=mlab-forms
zip="dist/$slug.zip"
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT

mkdir -p "$stage/$slug" dist
tar --exclude-from=.distignore -cf - . | tar -xf - -C "$stage/$slug"
rm -f "$zip"
(cd "$stage" && zip -qr "$OLDPWD/$zip" "$slug")

if unzip -Z1 "$zip" | grep -qE '(^|/)(wp-config[^/]*\.php|\.env[^/]*)$'; then
	echo "ZIP enthält eine Konfigurations- oder Secret-Datei (wp-config, .env)." >&2
	exit 1
fi

top=$(unzip -Z1 "$zip" | cut -d/ -f1 | sort -u)
if [ "$top" != "$slug" ]; then
	echo "ZIP hat nicht genau einen Ordner oben namens $slug: $top" >&2
	exit 1
fi

size=$(stat -c%s "$zip")
if [ "$size" -gt 8388608 ]; then
	echo "Warnung: ZIP ist grösser als 8 MB ($size Bytes). Viele Hostings haben dort ein Upload-Limit." >&2
fi

echo "$zip"
