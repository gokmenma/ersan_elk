#!/bin/bash
# PWA sayfalarına yeni Tailwind class'ı eklendiğinde bu script ile CSS yeniden derlenir.
# Standalone CLI: https://github.com/tailwindlabs/tailwindcss/releases (v3.4.17)
set -e
cd "$(dirname "$0")"

if [ ! -f ./tailwindcss ]; then
    echo "tailwindcss standalone CLI bulunamadı. İndirmek için:"
    echo "curl -sL -o tailwindcss https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-linux-x64 && chmod +x tailwindcss"
    exit 1
fi

./tailwindcss -c tailwind.config.js -i assets/css/tailwind-src.css -o assets/css/tailwind-build.css --minify
