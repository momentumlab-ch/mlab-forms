#!/usr/bin/env bash
# Baut dist/mlab-forms-<version>.zip, das ohne Composer oder Node auf dem Server läuft.
set -euo pipefail

cd "$(dirname "$0")/.."

version=$(sed -n "s/^ \* Version: *//p" mlab-forms.php | tr -d '[:space:]')
if [ -z "$version" ]; then
	echo "Version im Plugin-Header nicht gefunden." >&2
	exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction --quiet

rm -rf build dist
mkdir -p build/mlab-forms dist
cp -R mlab-forms.php uninstall.php readme.txt LICENSE src languages vendor build/mlab-forms/

# Der Ordner im ZIP muss «mlab-forms» heissen, sonst legt WordPress beim Hochladen ein zweites Plugin an.
(cd build && zip -rq "../dist/mlab-forms-${version}.zip" mlab-forms)

echo "dist/mlab-forms-${version}.zip"
