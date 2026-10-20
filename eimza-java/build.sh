#!/usr/bin/env bash
set -euo pipefail

project_dir="$(cd "$(dirname "$0")" && pwd)"
repo_dir="$(cd "$project_dir/.." && pwd)"
javac_bin="${JAVA_HOME:-/usr/lib/jvm/java-21-openjdk}/bin/javac"
jar_bin="${JAVA_HOME:-/usr/lib/jvm/java-21-openjdk}/bin/jar"

"$javac_bin" --release 8 -encoding UTF-8 -d "$project_dir/bin" \
  $(find "$project_dir/src" -type f -name '*.java' -print)

"$jar_bin" --create \
  --file "$repo_dir/downloads/ErsanEImza.jar" \
  --main-class com.ersan.eimza.ErsanEImzaApp \
  -C "$project_dir/bin" . \
  -C "$project_dir/resources" .

cp "$repo_dir/downloads/ErsanEImza.jar" "$repo_dir/scripts/ErsanEImza.jar"
sha256sum "$repo_dir/downloads/ErsanEImza.jar"
