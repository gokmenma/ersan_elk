#!/usr/bin/env bash
set -euo pipefail

project_dir="$(cd "$(dirname "$0")" && pwd)"
repo_dir="$(cd "$project_dir/.." && pwd)"
javac_bin="${JAVA_HOME:-/usr/lib/jvm/java-21-openjdk}/bin/javac"
jar_bin="${JAVA_HOME:-/usr/lib/jvm/java-21-openjdk}/bin/jar"

rm -rf "$project_dir/bin"
mkdir -p "$project_dir/bin"

"$javac_bin" --release 8 -encoding UTF-8 -cp "$project_dir/lib/pdfbox-app-3.0.8.jar" -d "$project_dir/bin" \
  $(find "$project_dir/src" -type f -name '*.java' -print)

tmp_lib_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_lib_dir"' EXIT
(cd "$tmp_lib_dir" && "$jar_bin" -xf "$project_dir/lib/pdfbox-app-3.0.8.jar")
rm -f "$tmp_lib_dir/META-INF/MANIFEST.MF" "$tmp_lib_dir/META-INF/INDEX.LIST"

"$jar_bin" --create \
  --file "$repo_dir/downloads/ErsanEImza.jar" \
  --main-class com.ersan.eimza.ErsanEImzaApp \
  -C "$project_dir/bin" . \
  -C "$tmp_lib_dir" . \
  -C "$project_dir/resources" .

cp "$repo_dir/downloads/ErsanEImza.jar" "$repo_dir/scripts/ErsanEImza.jar"
sha256sum "$repo_dir/downloads/ErsanEImza.jar"
