#!/usr/bin/env bash
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "========================================================"
echo "        Ersan E-İmza Yerel Ajanı Başlatılıyor...        "
echo "========================================================"
echo ""

if command -v python3 &>/dev/null; then
    python3 "${SCRIPT_DIR}/ersan_eimza_agent.py"
elif command -v python &>/dev/null; then
    python "${SCRIPT_DIR}/ersan_eimza_agent.py"
else
    echo "[HATA] Sisteminizde Python 3 bulunamadı."
    echo "Lütfen 'sudo apt install python3' komutu ile Python kurunuz."
    exit 1
fi
