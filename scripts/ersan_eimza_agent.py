#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Ersan E-İmza Yerel Ajanı (Local E-Signature Bridge Service)
Kullanıcının bilgisayarında (127.0.0.1:9191) çalışarak web tarayıcısı ile
takılı olan USB E-İmza (SafeNet, E-Tuğra, TÜRKKEP, E-GÜVEN, KamuSM/Akis) donanımı
arasında güvenli CAdES-BES imza köprüsü kurar.
"""

import sys
import os
import re
import json
import base64
import hashlib
import platform
import subprocess
import tempfile
from datetime import datetime, timezone
from http.server import HTTPServer, BaseHTTPRequestHandler

AGENT_PORT = 9191
AGENT_VERSION = "1.0.0"

# İşletim sistemine göre PKCS#11 sürücü adayları
def get_candidate_modules():
    os_name = platform.system().lower()
    if os_name == "windows":
        sys32 = os.environ.get("SystemRoot", "C:\\Windows") + "\\System32\\"
        syswow = os.environ.get("SystemRoot", "C:\\Windows") + "\\SysWOW64\\"
        return [
            sys32 + "eTPKCS11.dll",           # SafeNet / TÜRKKEP / E-GÜVEN
            sys32 + "akisp11.dll",            # TÜBİTAK AKİS / KamuSM / E-Tuğra
            sys32 + "aetpkss1.dll",           # SafeSign / E-Tuğra
            sys32 + "OcsCryptolib_pkcs11.dll",# E-Tuğra OCS
            sys32 + "opensc-pkcs11.dll",      # OpenSC
            syswow + "eTPKCS11.dll",
            syswow + "akisp11.dll",
            syswow + "aetpkss1.dll",
        ]
    elif os_name == "darwin": # macOS
        return [
            "/Library/Frameworks/eToken.framework/Versions/A/libeToken.dylib",
            "/usr/local/lib/libakisp11.dylib",
            "/usr/local/lib/opensc-pkcs11.so",
        ]
    else: # Linux
        return [
            "/usr/lib/libeToken.so",           # SafeNet / TÜRKKEP / E-GÜVEN
            "/usr/lib64/libeToken.so",
            "/usr/lib/x86_64-linux-gnu/libeToken.so",
            "/usr/lib/libakisp11.so",          # TÜBİTAK AKİS (KamuSM, E-Tuğra)
            "/usr/lib64/libakisp11.so",
            "/usr/lib/opensc-pkcs11.so",       # OpenSC Genel Sürücü
            "/usr/lib/libaetpkss.so",          # E-Tuğra SafeSign
            "/usr/lib/libetpkcs11.so",         # Feitian / ePass
        ]

def get_pkcs11_tool_bin():
    candidates = [
        "pkcs11-tool",
        r"C:\Program Files\OpenSC Project\OpenSC\tools\pkcs11-tool.exe",
        r"C:\Program Files (x86)\OpenSC Project\OpenSC\tools\pkcs11-tool.exe",
        "/usr/bin/pkcs11-tool",
        "/usr/local/bin/pkcs11-tool",
    ]
    for c in candidates:
        if os.path.isabs(c):
            if os.path.exists(c):
                return c
        else:
            try:
                res = subprocess.run([c, "-h"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=2)
                return c
            except Exception:
                continue
    return "pkcs11-tool"

def detect_active_token():
    pkcs11_bin = get_pkcs11_tool_bin()
    modules = get_candidate_modules()
    for mod in modules:
        if not os.path.exists(mod):
            continue
        try:
            res_slots = subprocess.run(
                [pkcs11_bin, "--module", mod, "-T"],
                stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5
            )
            out_slots = res_slots.stdout
            if "token model" in out_slots or "token label" in out_slots:
                label_match = re.search(r"token label\s*:\s*(.+)", out_slots)
                token_label = label_match.group(1).strip() if label_match else "E-İmza Kartı"

                res_objs = subprocess.run(
                    [pkcs11_bin, "--module", mod, "-O"],
                    stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=6
                )
                out_objs = res_objs.stdout

                id_match = re.search(r"ID:\s*([0-9a-fA-F:]+)", out_objs)
                cert_id = id_match.group(1).strip() if id_match else None

                subject_match = re.search(r"subject:\s*DN:\s*(.+)", out_objs)
                subject = subject_match.group(1).strip() if subject_match else ""

                cn_match = re.search(r"CN=([^,\n]+)", subject)
                owner_name = cn_match.group(1).replace(r"\C4\B0", "İ").replace(r"\C3\96", "Ö").replace(r"\C3\9C", "Ü").strip() if cn_match else ""

                tc_match = re.search(r"serialNumber=([0-9]{11})", subject)
                tcno = tc_match.group(1).strip() if tc_match else ""

                device_type = "Akıllı Kart / Token"
                m_low = mod.lower()
                if "etoken" in m_low:
                    device_type = "SafeNet (TÜRKKEP / E-GÜVEN)"
                elif "akis" in m_low:
                    device_type = "AKİS (KamuSM / E-Tuğra)"
                elif "aetpkss" in m_low or "ocscrypto" in m_low:
                    device_type = "E-Tuğra Token"
                elif "opensc" in m_low:
                    device_type = "OpenSC Kart"

                return {
                    "status": "success",
                    "module": mod,
                    "token_label": token_label,
                    "device_type": device_type,
                    "cert_id": cert_id,
                    "owner_name": owner_name,
                    "tcno": tcno,
                    "subject": subject
                }
        except Exception:
            continue

    return {
        "status": "error",
        "message": "Sistemde takılı aktif bir e-imza cihazı bulunamadı. Lütfen USB kartınızı takınız."
    }

# ASN.1 DER & CAdES-BES Encoder
def encode_length(length: int) -> bytes:
    if length < 128:
        return bytes([length])
    len_bytes = []
    temp = length
    while temp > 0:
        len_bytes.insert(0, temp & 0xFF)
        temp >>= 8
    return bytes([0x80 | len(len_bytes)]) + bytes(len_bytes)

def encode_tlv(tag: int, value: bytes) -> bytes:
    return bytes([tag]) + encode_length(len(value)) + value

def asn1_int(val: int) -> bytes:
    if val == 0:
        return encode_tlv(0x02, b'\x00')
    res = []
    temp = abs(val)
    while temp > 0:
        res.insert(0, temp & 0xFF)
        temp >>= 8
    if res[0] & 0x80:
        res.insert(0, 0x00)
    return encode_tlv(0x02, bytes(res))

def asn1_null() -> bytes:
    return b'\x05\x00'

def asn1_octet_string(data: bytes) -> bytes:
    return encode_tlv(0x04, data)

def asn1_oid(oid_str: str) -> bytes:
    parts = [int(p) for p in oid_str.split('.')]
    first_byte = 40 * parts[0] + parts[1]
    enc = [first_byte]
    for part in parts[2:]:
        if part == 0:
            enc.append(0)
        else:
            vlq = []
            v = part
            while v > 0:
                vlq.insert(0, (v & 0x7F) | (0x80 if len(vlq) > 0 else 0x00))
                v >>= 7
            enc.extend(vlq)
    return encode_tlv(0x06, bytes(enc))

def asn1_sequence(elements: list) -> bytes:
    return encode_tlv(0x30, b''.join(elements))

def asn1_set(elements: list) -> bytes:
    return encode_tlv(0x31, b''.join(sorted(elements)))

def asn1_utc_time(dt: datetime) -> bytes:
    return encode_tlv(0x17, dt.strftime('%y%m%d%H%M%SZ').encode('ascii'))

def asn1_context(tag_num: int, value: bytes, explicit: bool = True) -> bytes:
    tag = 0xA0 | tag_num if explicit else (0x80 | tag_num)
    return encode_tlv(tag, value)

OID_ID_DATA = "1.2.840.113549.1.7.1"
OID_ID_SIGNED_DATA = "1.2.840.113549.1.7.2"
OID_SHA256 = "2.16.840.1.101.3.4.2.1"
OID_RSA_ENCRYPTION = "1.2.840.113549.1.1.1"

OID_CONTENT_TYPE = "1.2.840.113549.1.9.3"
OID_MESSAGE_DIGEST = "1.2.840.113549.1.9.4"
OID_SIGNING_TIME = "1.2.840.113549.1.9.5"
OID_SIGNING_CERTIFICATE_V2 = "1.2.840.113549.1.9.16.2.47"

def extract_cert_info(cert_der: bytes):
    cert_hash = hashlib.sha256(cert_der).digest()
    assert cert_der[0] == 0x30
    idx = 2 if cert_der[1] < 128 else 2 + (cert_der[1] & 0x7F)
    assert cert_der[idx] == 0x30
    tbs_idx = idx + (2 if cert_der[idx+1] < 128 else 2 + (cert_der[idx+1] & 0x7F))
    
    if cert_der[tbs_idx] == 0xA0:
        v_len = cert_der[tbs_idx+1] if cert_der[tbs_idx+1] < 128 else cert_der[tbs_idx+1] & 0x7F
        v_full_len = 2 + (v_len if cert_der[tbs_idx+1] < 128 else (cert_der[tbs_idx+1] & 0x7F) + v_len)
        tbs_idx += v_full_len
        
    assert cert_der[tbs_idx] == 0x02
    s_len = cert_der[tbs_idx+1] if cert_der[tbs_idx+1] < 128 else cert_der[tbs_idx+1] & 0x7F
    s_full_len = 2 + (s_len if cert_der[tbs_idx+1] < 128 else (cert_der[tbs_idx+1] & 0x7F) + s_len)
    serial_der = cert_der[tbs_idx:tbs_idx+s_full_len]
    tbs_idx += s_full_len
    
    assert cert_der[tbs_idx] == 0x30
    sig_len = cert_der[tbs_idx+1] if cert_der[tbs_idx+1] < 128 else cert_der[tbs_idx+1] & 0x7F
    sig_full_len = 2 + (sig_len if cert_der[tbs_idx+1] < 128 else (cert_der[tbs_idx+1] & 0x7F) + sig_len)
    tbs_idx += sig_full_len
    
    assert cert_der[tbs_idx] == 0x30
    iss_len_byte = cert_der[tbs_idx+1]
    if iss_len_byte < 128:
        iss_len = iss_len_byte
        iss_head_len = 2
    else:
        num_len_bytes = iss_len_byte & 0x7F
        iss_len = int.from_bytes(cert_der[tbs_idx+2:tbs_idx+2+num_len_bytes], 'big')
        iss_head_len = 2 + num_len_bytes
    issuer_der = cert_der[tbs_idx:tbs_idx+iss_head_len+iss_len]
    
    return cert_hash, issuer_der, serial_der

def build_cades_signed_attrs(raw_digest: bytes, cert_der: bytes, now_utc: datetime):
    cert_hash, issuer_der, serial_der = extract_cert_info(cert_der)
    
    attr_content_type = asn1_sequence([asn1_oid(OID_CONTENT_TYPE), asn1_set([asn1_oid(OID_ID_DATA)])])
    attr_signing_time = asn1_sequence([asn1_oid(OID_SIGNING_TIME), asn1_set([asn1_utc_time(now_utc)])])
    attr_message_digest = asn1_sequence([asn1_oid(OID_MESSAGE_DIGEST), asn1_set([asn1_octet_string(raw_digest)])])
    
    general_name = asn1_context(4, issuer_der, explicit=True)
    general_names = asn1_sequence([general_name])
    issuer_serial = asn1_sequence([general_names, serial_der])
    alg_sha256 = asn1_sequence([asn1_oid(OID_SHA256)])
    
    ess_cert_id_v2 = asn1_sequence([alg_sha256, asn1_octet_string(cert_hash), issuer_serial])
    signing_cert_v2_val = asn1_sequence([asn1_sequence([ess_cert_id_v2])])
    attr_signing_cert = asn1_sequence([asn1_oid(OID_SIGNING_CERTIFICATE_V2), asn1_set([signing_cert_v2_val])])
    
    raw_attrs = [attr_content_type, attr_signing_time, attr_message_digest, attr_signing_cert]
    sorted_attrs = sorted(raw_attrs)
    signed_attrs_der = encode_tlv(0x31, b''.join(sorted_attrs))
    return signed_attrs_der, issuer_der, serial_der

def sign_with_token(pin: str, data_to_sign: bytes, module_path: str, cert_id: str = None) -> bytes:
    with tempfile.NamedTemporaryFile("wb", delete=False) as f_in, \
         tempfile.NamedTemporaryFile("rb", delete=False) as f_out:
        in_path = f_in.name
        out_path = f_out.name
        f_in.write(data_to_sign)
        
    try:
        pkcs11_bin = get_pkcs11_tool_bin()
        cmd = [
            pkcs11_bin,
            "--module", module_path,
            "--login",
            "--pin", pin,
            "--sign",
            "-m", "SHA256-RSA-PKCS",
            "--input-file", in_path,
            "--output-file", out_path
        ]
        if cert_id:
            cmd.extend(["--id", cert_id.replace(":", "")])

        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=20)
        if res.returncode != 0:
            err_msg = res.stderr.decode("utf-8", errors="replace").strip()
            raise RuntimeError(f"Token imzalama hatası: {err_msg}")
            
        with open(out_path, "rb") as f_res:
            sig = f_res.read()
        if not sig:
            raise RuntimeError("Token boş imza döndürdü.")
        return sig
    finally:
        if os.path.exists(in_path): os.unlink(in_path)
        if os.path.exists(out_path): os.unlink(out_path)

def read_cert_from_token(module_path: str, cert_id: str = None) -> bytes:
    with tempfile.NamedTemporaryFile("rb", delete=False) as f_out:
        out_path = f_out.name
    try:
        pkcs11_bin = get_pkcs11_tool_bin()
        cmd = [
            pkcs11_bin,
            "--module", module_path,
            "--read-object",
            "--type", "cert",
            "--output-file", out_path
        ]
        if cert_id:
            cmd.extend(["--id", cert_id.replace(":", "")])

        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=10)
        if res.returncode != 0:
            raise RuntimeError("Sertifika okunamadı.")
            
        with open(out_path, "rb") as f_c:
            cert_data = f_c.read()
        return cert_data
    finally:
        if os.path.exists(out_path): os.unlink(out_path)

def build_cms_signed_data(raw_digest: bytes, cert_der: bytes, pin: str, module_path: str, cert_id: str = None) -> bytes:
    now_utc = datetime.now(timezone.utc)
    signed_attrs_der, issuer_der, serial_der = build_cades_signed_attrs(raw_digest, cert_der, now_utc)
    signature_bytes = sign_with_token(pin, signed_attrs_der, module_path, cert_id)
    
    digest_alg = asn1_sequence([asn1_oid(OID_SHA256), asn1_null()])
    digest_algs = asn1_set([digest_alg])
    encap_content = asn1_sequence([asn1_oid(OID_ID_DATA)])
    certificates = asn1_context(0, cert_der, explicit=False)
    
    sid = asn1_sequence([issuer_der, serial_der])
    sig_alg = asn1_sequence([asn1_oid(OID_RSA_ENCRYPTION), asn1_null()])
    sig_val = asn1_octet_string(signature_bytes)
    
    auth_attrs = asn1_context(0, signed_attrs_der[encode_length(len(signed_attrs_der))[0] == 0x80 and 2 or 1:], explicit=False)
    signer_info = asn1_sequence([
        asn1_int(1), sid, digest_alg, auth_attrs, sig_alg, sig_val
    ])
    signer_infos = asn1_set([signer_info])
    
    signed_data = asn1_sequence([
        asn1_int(1), digest_algs, encap_content, certificates, signer_infos
    ])
    
    content_info = asn1_sequence([
        asn1_oid(OID_ID_SIGNED_DATA),
        asn1_context(0, signed_data, explicit=True)
    ])
    return content_info


# HTTP İstek İşleyici (CORS & Private Network Access Destekli)
class EImzaAgentHandler(BaseHTTPRequestHandler):
    def _send_cors_headers(self, status=200):
        self.send_response(status)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Requested-With")
        self.send_header("Access-Control-Allow-Private-Network", "true")
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Cache-Control", "no-cache, no-store, must-revalidate")
        self.end_headers()

    def do_OPTIONS(self):
        self._send_cors_headers(200)

    def do_GET(self):
        if self.path in ["/", "/status", "/detect"]:
            token_info = detect_active_token()
            token_info["agent_version"] = AGENT_VERSION
            token_info["system"] = platform.system()
            self._send_cors_headers(200)
            self.wfile.write(json.dumps(token_info, ensure_ascii=False).encode("utf-8"))
        else:
            self._send_cors_headers(404)
            self.wfile.write(json.dumps({"status": "error", "message": "Bulunamadı"}, ensure_ascii=False).encode("utf-8"))

    def do_POST(self):
        if self.path == "/sign":
            content_length = int(self.headers.get("Content-Length", 0))
            body = self.rfile.read(content_length)
            try:
                data = json.loads(body.decode("utf-8"))
            except Exception:
                self._send_cors_headers(400)
                self.wfile.write(json.dumps({"status": "error", "message": "Geçersiz JSON verisi"}, ensure_ascii=False).encode("utf-8"))
                return

            e_hash = data.get("hash", "")
            pin = str(data.get("pin", "")).strip()

            if not e_hash or not pin:
                self._send_cors_headers(400)
                self.wfile.write(json.dumps({"status": "error", "message": "Özet (hash) ve PIN zorunludur."}, ensure_ascii=False).encode("utf-8"))
                return

            # Aktif token'ı algıla
            token_info = detect_active_token()
            if token_info["status"] != "success":
                self._send_cors_headers(400)
                self.wfile.write(json.dumps(token_info, ensure_ascii=False).encode("utf-8"))
                return

            mod = token_info["module"]
            cert_id = token_info["cert_id"]

            try:
                # Sertifikayı oku
                cert_der = read_cert_from_token(mod, cert_id)
                
                # Base64 hash'i decode et
                try:
                    raw_digest = base64.b64decode(e_hash)
                    if len(raw_digest) != 32:
                        raw_digest = hashlib.sha256(e_hash.encode("utf-8")).digest()
                except Exception:
                    raw_digest = hashlib.sha256(e_hash.encode("utf-8")).digest()

                # CAdES-BES imzasını oluştur
                cades_der = build_cms_signed_data(raw_digest, cert_der, pin, mod, cert_id)
                cades_b64 = base64.b64encode(cades_der).decode("ascii")

                self._send_cors_headers(200)
                self.wfile.write(json.dumps({
                    "status": "success",
                    "signature": cades_b64,
                    "signer_name": token_info.get("owner_name", ""),
                    "signer_tcno": token_info.get("tcno", ""),
                    "device": token_info.get("device_type", "")
                }, ensure_ascii=False).encode("utf-8"))
            except Exception as e:
                err_msg = str(e)
                if "PIN_INCORRECT" in err_msg or "CKR_PIN_INCORRECT" in err_msg:
                    err_msg = "Girdiğiniz e-imza PIN kodu hatalıdır."
                elif "PIN_LOCKED" in err_msg or "CKR_PIN_LOCKED" in err_msg:
                    err_msg = "E-İmza kartınız hatalı PIN denemeleri nedeniyle kilitlenmiştir."
                self._send_cors_headers(400)
                self.wfile.write(json.dumps({"status": "error", "message": err_msg}, ensure_ascii=False).encode("utf-8"))
        else:
            self._send_cors_headers(404)
            self.wfile.write(json.dumps({"status": "error", "message": "Geçersiz servis"}, ensure_ascii=False).encode("utf-8"))

def run_agent():
    server_address = ("127.0.0.1", AGENT_PORT)
    httpd = HTTPServer(server_address, EImzaAgentHandler)
    print(f"=====================================================")
    print(f" Ersan E-İmza Yerel Ajanı v{AGENT_VERSION} Başlatıldı ")
    print(f" Adres: http://127.0.0.1:{AGENT_PORT}")
    print(f" Durum: E-İmza donanımı dinleniyor...")
    print(f" Çıkış için Ctrl+C tuşlayınız.")
    print(f"=====================================================")
    
    # Başlangıçta kart kontrolü yap
    init_check = detect_active_token()
    if init_check["status"] == "success":
        print(f" [✓] Algılanan Cihaz: {init_check['device_type']}")
        print(f" [✓] İmza Sahibi: {init_check['owner_name']} (TC: {init_check['tcno']})")
    else:
        print(f" [!] {init_check['message']}")
    
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nErsan E-İmza Ajanı durduruldu.")
        httpd.server_close()

if __name__ == "__main__":
    run_agent()
