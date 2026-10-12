#!/usr/bin/env bash
set -euo pipefail

VHOST_FILE="/opt/lampp/etc/extra/httpd-vhosts.conf"
VHOST_SOURCE="/opt/lampp/htdocs/ersan_elk/deploy/apache/mobile.ersanelektrik.com.conf"
HOSTS_FILE="/etc/hosts"
DOMAIN="mobile.ersanelektrik.com"

if [[ ! -f "$VHOST_SOURCE" ]]; then
    echo "Vhost şablonu bulunamadı: $VHOST_SOURCE" >&2
    exit 1
fi

if ! grep -q "ServerName $DOMAIN" "$VHOST_FILE"; then
    cp "$VHOST_FILE" "${VHOST_FILE}.bak.$(date +%Y%m%d%H%M%S)"
    {
        printf '\n# >>> %s (Ersan Mobile SPA) >>>\n' "$DOMAIN"
        cat "$VHOST_SOURCE"
        printf '# <<< %s (Ersan Mobile SPA) <<<\n' "$DOMAIN"
    } >> "$VHOST_FILE"
fi

if ! grep -qE "(^|[[:space:]])${DOMAIN//./\.}([[:space:]]|$)" "$HOSTS_FILE"; then
    cp "$HOSTS_FILE" "${HOSTS_FILE}.bak.$(date +%Y%m%d%H%M%S)"
    printf '127.0.0.1    %s\n' "$DOMAIN" >> "$HOSTS_FILE"
fi

/opt/lampp/bin/httpd -t
/opt/lampp/lampp reloadapache

echo "Hazır: http://$DOMAIN"
