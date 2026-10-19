#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Çoklu E-İmza Donanımı (E-Tuğra, TÜRKKEP, E-GÜVEN, Kamu SM vb.) PKCS#11 CAdES-BES İmzacı
Sistemde takılı kartı otomatik algılar ve PTT KEP şartnamesine uygun resmi CAdES-BES üretir.
"""

import sys
import os
import re
import json
import argparse
import base64
import hashlib
import subprocess
import tempfile
from datetime import datetime, timezone

# Türkiye'de yaygın kullanılan PKCS#11 akıllı kart / token kütüphaneleri
CANDIDATE_MODULES = [
    "/usr/lib/libakisp11.so",          # TÜBİTAK AKİS (E-Tuğra, KamuSM, EDM vb.)
    "/usr/lib64/libakisp11.so",
    "/usr/lib/libeToken.so",           # SafeNet / TürkKep / E-Güven
    "/usr/lib/opensc-pkcs11.so",       # OpenSC Genel Sürücü
    "/usr/lib/libetpkcs11.so",         # Feitian / ePass
    "/usr/lib/libIDPrimeTokenEngine.so", # IDEMIA / Gemalto IDPrime
    "/usr/lib/libaetpkss.so",          # SafeSign (E-Tuğra eski kartlar)
]

def detect_active_token():
    """Sistemde takılı olan e-imza cihazını otomatik tarar ve sertifika detaylarını döner"""
    for mod in CANDIDATE_MODULES:
        if not os.path.exists(mod):
            continue
        try:
            res_slots = subprocess.run(
                ["pkcs11-tool", "--module", mod, "-T"],
                stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=5
            )
            # Slotlarda kart var mı?
            out_slots = res_slots.stdout
            if "token model" in out_slots or "token label" in out_slots:
                # Token etiketini çek
                label_match = re.search(r"token label\s*:\s*(.+)", out_slots)
                token_label = label_match.group(1).strip() if label_match else "Bilinmeyen Kart"
                
                # Nesneleri tara (Sertifika)
                res_objs = subprocess.run(
                    ["pkcs11-tool", "--module", mod, "-O"],
                    stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=6
                )
                out_objs = res_objs.stdout
                
                # ID yakala
                id_match = re.search(r"ID:\s*([0-9a-fA-F:]+)", out_objs)
                cert_id = id_match.group(1).strip() if id_match else None
                
                # Subject yakala
                subject_match = re.search(r"subject:\s*DN:\s*(.+)", out_objs)
                subject = subject_match.group(1).strip() if subject_match else ""
                
                # CN ve serialNumber (TCKN) ayıkla
                cn_match = re.search(r"CN=([^,\n]+)", subject)
                owner_name = cn_match.group(1).replace(r"\C4\B0", "İ").replace(r"\C3\96", "Ö").replace(r"\C3\9C", "Ü").strip() if cn_match else ""
                
                tc_match = re.search(r"serialNumber=([0-9]{11})", subject)
                tcno = tc_match.group(1).strip() if tc_match else ""
                
                # ESHS Tipi Tahmini
                device_type = "Akıllı Kart / Token"
                if "akis" in mod.lower():
                    device_type = "E-Tuğra / AKİS"
                elif "etoken" in mod.lower():
                    device_type = "SafeNet (TÜRKKEP / E-GÜVEN)"
                elif "opensc" in mod.lower():
                    device_type = "OpenSC Akıllı Kart"

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
        "message": "Sistemde takılı aktif bir e-imza cihazı veya sertifika bulunamadı."
    }

# --- ASN.1 DER ENCODER ---

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
        cmd = [
            "pkcs11-tool",
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
            raise RuntimeError(f"Token imzalama hatası (kod {res.returncode}): {err_msg}")
            
        with open(out_path, "rb") as f:
            signature = f.read()
            
        if not signature:
            raise RuntimeError("Token boş imza döndürdü.")
            
        return signature
    finally:
        if os.path.exists(in_path): os.unlink(in_path)
        if os.path.exists(out_path): os.unlink(out_path)

def build_cms_signed_data(raw_digest: bytes, cert_der: bytes, pin: str, module_path: str, cert_id: str = None, now_utc: datetime = None) -> bytes:
    if now_utc is None:
        now_utc = datetime.now(timezone.utc)
        
    signed_attrs_der, issuer_der, serial_der = build_cades_signed_attrs(raw_digest, cert_der, now_utc)
    signature_bytes = sign_with_token(pin, signed_attrs_der, module_path, cert_id)
    
    issuer_and_serial = asn1_sequence([issuer_der, serial_der])
    digest_alg = asn1_sequence([asn1_oid(OID_SHA256), asn1_null()])
    sig_alg = asn1_sequence([asn1_oid(OID_RSA_ENCRYPTION), asn1_null()])
    signed_attrs_context = bytes([0xA0]) + signed_attrs_der[1:]
    
    signer_info = asn1_sequence([
        asn1_int(1),
        issuer_and_serial,
        digest_alg,
        signed_attrs_context,
        sig_alg,
        asn1_octet_string(signature_bytes)
    ])
    
    signer_infos = asn1_set([signer_info])
    encap_content_info = asn1_sequence([asn1_oid(OID_ID_DATA)])
    cert_set = encode_tlv(0xA0, cert_der)
    digest_algorithms = asn1_set([asn1_sequence([asn1_oid(OID_SHA256), asn1_null()])])
    
    signed_data = asn1_sequence([
        asn1_int(1),
        digest_algorithms,
        encap_content_info,
        cert_set,
        signer_infos
    ])
    
    content_info = asn1_sequence([
        asn1_oid(OID_ID_SIGNED_DATA),
        asn1_context(0, signed_data, explicit=True)
    ])
    
    return content_info

def main():
    parser = argparse.ArgumentParser(description="Çoklu E-İmza (E-Tuğra, SafeNet vb.) CAdES-BES İmzalayıcı")
    parser.add_argument("--detect", action="store_true", help="Takılı e-imza kartını otomatik algıla ve JSON dön")
    parser.add_argument("--pin", help="E-İmza kart PIN kodu")
    parser.add_argument("--hash", help="İmzalanacak SHA-256 hash (Base64 veya Hex)")
    parser.add_argument("--module", help="PKCS#11 kütüphanesi (opsiyonel, verilmezse otomatik algılar)")
    parser.add_argument("--cert", help="Sertifika DER dosyası (opsiyonel, verilmezse karttan okur)")
    parser.add_argument("--out", help="Çıktı CAdES-BES DER dosyası yolu")
    args = parser.parse_args()
    
    if args.detect:
        token_info = detect_active_token()
        print(json.dumps(token_info, ensure_ascii=False, indent=2))
        sys.exit(0 if token_info["status"] == "success" else 1)
        
    if not args.pin or not args.hash or not args.out:
        parser.error("--pin, --hash ve --out parametreleri zorunludur.")
        
    # Aktif cihazı otomatik algıla veya belirtileni kullan
    active_token = detect_active_token()
    if active_token["status"] != "success":
        sys.exit(f"HATA: {active_token.get('message', 'E-İmza kartı bulunamadı.')}")
        
    module_path = args.module or active_token["module"]
    cert_id = active_token.get("cert_id")
    
    # Hash decode
    raw_hash_str = args.hash.strip()
    try:
        if len(raw_hash_str) == 64 and all(c in '0123456789abcdefABCDEF' for c in raw_hash_str):
            raw_digest = bytes.fromhex(raw_hash_str)
        else:
            raw_digest = base64.b64decode(raw_hash_str)
            if len(raw_digest) != 32:
                raw_digest = hashlib.sha256(raw_hash_str.encode('utf-8')).digest()
    except Exception:
        raw_digest = hashlib.sha256(raw_hash_str.encode('utf-8')).digest()
        
    # Sertifika dosyasını oku veya token'dan oku
    cert_path = args.cert
    if not cert_path or not os.path.exists(cert_path) or os.path.getsize(cert_path) == 0:
        with tempfile.NamedTemporaryFile("wb", delete=False) as tmp_cert:
            cert_path = tmp_cert.name
            
        read_cmd = ["pkcs11-tool", "--module", module_path, "--read-object", "--type", "cert", "--output-file", cert_path]
        if cert_id:
            read_cmd.extend(["-d", cert_id.replace(":", "")])
        subprocess.run(read_cmd, check=True)
        
    with open(cert_path, "rb") as f:
        cert_der = f.read()
        
    cades_der = build_cms_signed_data(raw_digest, cert_der, args.pin, module_path, cert_id)
    
    with open(args.out, "wb") as f:
        f.write(cades_der)
        
    print(f"SUCCESS: CAdES-BES DER başarıyla oluşturuldu: {args.out} ({len(cades_der)} bytes) - Cihaz: {active_token.get('device_type')}")

if __name__ == "__main__":
    main()
