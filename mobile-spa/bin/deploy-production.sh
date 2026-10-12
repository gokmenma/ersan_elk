#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SPA_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PROJECT_DIR="$(cd "$SPA_DIR/.." && pwd)"
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
HTTPD_BIN="${HTTPD_BIN:-/opt/lampp/bin/httpd}"
LAMPP_BIN="${LAMPP_BIN:-/opt/lampp/lampp}"

if [[ ! -f "$SPA_DIR/dist/index.html" ]]; then
    echo "Build çıktısı bulunamadı: $SPA_DIR/dist/index.html" >&2
    echo "Geliştirme ortamında npm run build çalıştırıp dist klasörünü commit edin." >&2
    exit 1
fi

"$PHP_BIN" -l "$SPA_DIR/public/api/auth.php"
"$PHP_BIN" -l "$SPA_DIR/public/api/personnel.php"
"$PHP_BIN" -l "$PROJECT_DIR/App/Model/MobilePersonelModel.php"
"$HTTPD_BIN" -t

if [[ "${EUID}" -eq 0 ]]; then
    "$LAMPP_BIN" reloadapache
    echo "Deploy tamamlandı ve Apache yenilendi."
else
    echo "Dosya kontrolleri tamamlandı. PHP OPcache yenilemesi için şu komutu çalıştırın:"
    echo "sudo /opt/lampp/lampp reloadapache"
fi
