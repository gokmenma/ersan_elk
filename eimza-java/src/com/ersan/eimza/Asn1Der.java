package com.ersan.eimza;

import java.io.ByteArrayOutputStream;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.text.SimpleDateFormat;
import java.util.*;

/**
 * PTT KEP uyumlu CAdES-BES (PKCS#7 SignedData) ASN.1 DER Kodlayıcı.
 * Dış bağımlılık gerektirmeden standart Java ile çalışır.
 */
public class Asn1Der {

    public static final String OID_ID_DATA = "1.2.840.113549.1.7.1";
    public static final String OID_ID_SIGNED_DATA = "1.2.840.113549.1.7.2";
    public static final String OID_SHA256 = "2.16.840.1.101.3.4.2.1";
    public static final String OID_RSA_ENCRYPTION = "1.2.840.113549.1.1.1";

    public static final String OID_CONTENT_TYPE = "1.2.840.113549.1.9.3";
    public static final String OID_MESSAGE_DIGEST = "1.2.840.113549.1.9.4";
    public static final String OID_SIGNING_TIME = "1.2.840.113549.1.9.5";
    public static final String OID_SIGNING_CERTIFICATE_V2 = "1.2.840.113549.1.9.16.2.47";

    public static byte[] encodeLength(int length) {
        if (length < 128) {
            return new byte[]{(byte) length};
        }
        ByteArrayOutputStream baos = new ByteArrayOutputStream();
        int temp = length;
        while (temp > 0) {
            baos.write(temp & 0xFF);
            temp >>= 8;
        }
        byte[] lenBytes = baos.toByteArray();
        // reverse bytes
        byte[] reversed = new byte[lenBytes.length];
        for (int i = 0; i < lenBytes.length; i++) {
            reversed[i] = lenBytes[lenBytes.length - 1 - i];
        }
        byte[] res = new byte[1 + reversed.length];
        res[0] = (byte) (0x80 | reversed.length);
        System.arraycopy(reversed, 0, res, 1, reversed.length);
        return res;
    }

    public static byte[] encodeTlv(int tag, byte[] value) {
        byte[] lenBytes = encodeLength(value.length);
        byte[] res = new byte[1 + lenBytes.length + value.length];
        res[0] = (byte) tag;
        System.arraycopy(lenBytes, 0, res, 1, lenBytes.length);
        System.arraycopy(value, 0, res, 1 + lenBytes.length, value.length);
        return res;
    }

    public static byte[] integer(long val) {
        if (val == 0) return encodeTlv(0x02, new byte[]{0x00});
        ByteArrayOutputStream baos = new ByteArrayOutputStream();
        long temp = Math.abs(val);
        while (temp > 0) {
            baos.write((int) (temp & 0xFF));
            temp >>= 8;
        }
        byte[] b = baos.toByteArray();
        byte[] rev = new byte[b.length];
        for (int i = 0; i < b.length; i++) rev[i] = b[b.length - 1 - i];
        if ((rev[0] & 0x80) != 0) {
            byte[] padded = new byte[rev.length + 1];
            padded[0] = 0x00;
            System.arraycopy(rev, 0, padded, 1, rev.length);
            return encodeTlv(0x02, padded);
        }
        return encodeTlv(0x02, rev);
    }

    public static byte[] nullVal() {
        return new byte[]{0x05, 0x00};
    }

    public static byte[] octetString(byte[] data) {
        return encodeTlv(0x04, data);
    }

    public static byte[] oid(String oidStr) {
        String[] parts = oidStr.split("\\.");
        int firstByte = 40 * Integer.parseInt(parts[0]) + Integer.parseInt(parts[1]);
        ByteArrayOutputStream baos = new ByteArrayOutputStream();
        baos.write(firstByte);
        for (int i = 2; i < parts.length; i++) {
            long v = Long.parseLong(parts[i]);
            if (v == 0) {
                baos.write(0);
            } else {
                List<Byte> vlq = new ArrayList<>();
                while (v > 0) {
                    byte b = (byte) ((v & 0x7F) | (!vlq.isEmpty() ? 0x80 : 0x00));
                    vlq.add(0, b);
                    v >>= 7;
                }
                for (byte b : vlq) baos.write(b);
            }
        }
        return encodeTlv(0x06, baos.toByteArray());
    }

    public static byte[] sequence(byte[]... elements) {
        ByteArrayOutputStream baos = new ByteArrayOutputStream();
        for (byte[] el : elements) {
            if (el != null) baos.write(el, 0, el.length);
        }
        return encodeTlv(0x30, baos.toByteArray());
    }

    public static byte[] set(byte[]... elements) {
        List<byte[]> list = new ArrayList<>(Arrays.asList(elements));
        // DER requires sorting set elements lexicographically by encoded bytes
        list.sort((a, b) -> {
            int len = Math.min(a.length, b.length);
            for (int i = 0; i < len; i++) {
                int cmp = (a[i] & 0xFF) - (b[i] & 0xFF);
                if (cmp != 0) return cmp;
            }
            return a.length - b.length;
        });
        ByteArrayOutputStream baos = new ByteArrayOutputStream();
        for (byte[] el : list) {
            if (el != null) baos.write(el, 0, el.length);
        }
        return encodeTlv(0x31, baos.toByteArray());
    }

    public static byte[] utcTime(Date dt) {
        SimpleDateFormat sdf = new SimpleDateFormat("yyMMddHHmmss'Z'");
        sdf.setTimeZone(TimeZone.getTimeZone("UTC"));
        byte[] timeBytes = sdf.format(dt).getBytes(StandardCharsets.US_ASCII);
        return encodeTlv(0x17, timeBytes);
    }

    public static byte[] context(int tagNum, byte[] value, boolean explicit) {
        int tag = explicit ? (0xA0 | tagNum) : (0x80 | tagNum);
        return encodeTlv(tag, value);
    }

    public static class CertInfo {
        public byte[] certHash;
        public byte[] issuerDer;
        public byte[] serialDer;
    }

    public static CertInfo extractCertInfo(byte[] certDer) throws Exception {
        MessageDigest md = MessageDigest.getInstance("SHA-256");
        byte[] certHash = md.digest(certDer);

        // Parse X.509 ASN.1 DER to find issuer and serialNumber
        if ((certDer[0] & 0xFF) != 0x30) throw new IllegalArgumentException("Invalid certificate DER");
        int idx = (certDer[1] & 0x80) == 0 ? 2 : 2 + (certDer[1] & 0x7F);
        if ((certDer[idx] & 0xFF) != 0x30) throw new IllegalArgumentException("Invalid TBSCertificate");

        int tbsIdx = (certDer[idx + 1] & 0x80) == 0 ? idx + 2 : idx + 2 + (certDer[idx + 1] & 0x7F);

        // If version [0] EXPLICIT exists
        if ((certDer[tbsIdx] & 0xFF) == 0xA0) {
            int vLen = (certDer[tbsIdx + 1] & 0x80) == 0 ? (certDer[tbsIdx + 1] & 0xFF) : (certDer[tbsIdx + 1] & 0x7F);
            int vFullLen = (certDer[tbsIdx + 1] & 0x80) == 0 ? 2 + vLen : 2 + (certDer[tbsIdx + 1] & 0x7F) + vLen;
            tbsIdx += vFullLen;
        }

        // Serial number (INTEGER tag 0x02)
        if ((certDer[tbsIdx] & 0xFF) != 0x02) throw new IllegalArgumentException("Expected SerialNumber INTEGER");
        int sLen = (certDer[tbsIdx + 1] & 0x80) == 0 ? (certDer[tbsIdx + 1] & 0xFF) : (certDer[tbsIdx + 1] & 0x7F);
        int sFullLen = (certDer[tbsIdx + 1] & 0x80) == 0 ? 2 + sLen : 2 + (certDer[tbsIdx + 1] & 0x7F) + sLen;
        byte[] serialDer = Arrays.copyOfRange(certDer, tbsIdx, tbsIdx + sFullLen);
        tbsIdx += sFullLen;

        // Signature algorithm (SEQUENCE tag 0x30)
        if ((certDer[tbsIdx] & 0xFF) != 0x30) throw new IllegalArgumentException("Expected SignatureAlgorithm SEQUENCE");
        int sigLen = (certDer[tbsIdx + 1] & 0x80) == 0 ? (certDer[tbsIdx + 1] & 0xFF) : (certDer[tbsIdx + 1] & 0x7F);
        int sigFullLen = (certDer[tbsIdx + 1] & 0x80) == 0 ? 2 + sigLen : 2 + (certDer[tbsIdx + 1] & 0x7F) + sigLen;
        tbsIdx += sigFullLen;

        // Issuer (SEQUENCE tag 0x30)
        if ((certDer[tbsIdx] & 0xFF) != 0x30) throw new IllegalArgumentException("Expected Issuer SEQUENCE");
        int issLenByte = certDer[tbsIdx + 1] & 0xFF;
        int issLen;
        int issHeadLen;
        if (issLenByte < 128) {
            issLen = issLenByte;
            issHeadLen = 2;
        } else {
            int numLenBytes = issLenByte & 0x7F;
            int l = 0;
            for (int i = 0; i < numLenBytes; i++) {
                l = (l << 8) | (certDer[tbsIdx + 2 + i] & 0xFF);
            }
            issLen = l;
            issHeadLen = 2 + numLenBytes;
        }
        byte[] issuerDer = Arrays.copyOfRange(certDer, tbsIdx, tbsIdx + issHeadLen + issLen);

        CertInfo info = new CertInfo();
        info.certHash = certHash;
        info.issuerDer = issuerDer;
        info.serialDer = serialDer;
        return info;
    }

    public static byte[] buildCadesSignedAttrs(byte[] rawDigest, byte[] certDer, Date signingTime) throws Exception {
        CertInfo ci = extractCertInfo(certDer);

        byte[] attrContentType = sequence(oid(OID_CONTENT_TYPE), set(oid(OID_ID_DATA)));
        byte[] attrSigningTime = sequence(oid(OID_SIGNING_TIME), set(utcTime(signingTime)));
        byte[] attrMessageDigest = sequence(oid(OID_MESSAGE_DIGEST), set(octetString(rawDigest)));

        byte[] generalName = context(4, ci.issuerDer, true);
        byte[] generalNames = sequence(generalName);
        byte[] issuerSerial = sequence(generalNames, ci.serialDer);
        byte[] algSha256 = sequence(oid(OID_SHA256));

        byte[] essCertIdV2 = sequence(algSha256, octetString(ci.certHash), issuerSerial);
        byte[] signingCertV2Val = sequence(sequence(essCertIdV2));
        byte[] attrSigningCert = sequence(oid(OID_SIGNING_CERTIFICATE_V2), set(signingCertV2Val));

        return set(attrContentType, attrSigningTime, attrMessageDigest, attrSigningCert);
    }

    public static byte[] buildCmsSignedData(byte[] rawDigest, byte[] certDer, byte[] signedAttrsDer, byte[] rsaSigBytes) throws Exception {
        CertInfo ci = extractCertInfo(certDer);

        byte[] digestAlg = sequence(oid(OID_SHA256), nullVal());
        byte[] digestAlgs = set(digestAlg);
        byte[] encapContent = sequence(oid(OID_ID_DATA));
        // CertificateSet, SignedData icinde [0] IMPLICIT ve CONSTRUCTED alandir.
        // 0x80 primitive etiketi DER/CMS yapisini gecersiz kilar; dogru etiket A0'dir.
        byte[] certificates = encodeTlv(0xA0, certDer);

        byte[] sid = sequence(ci.issuerDer, ci.serialDer);
        byte[] sigAlg = sequence(oid(OID_RSA_ENCRYPTION), nullVal());
        byte[] sigVal = octetString(rsaSigBytes);

        // AuthenticatedAttributes in SignerInfo is tagged [0] IMPLICIT
        // Skip the initial SET tag (0x31) and length header
        int offset = 2;
        if ((signedAttrsDer[1] & 0x80) != 0) {
            offset = 2 + (signedAttrsDer[1] & 0x7F);
        }
        byte[] attrsBody = Arrays.copyOfRange(signedAttrsDer, offset, signedAttrsDer.length);
        byte[] authAttrs = encodeTlv(0xA0, attrsBody);

        byte[] signerInfo = sequence(
                integer(1), // version
                sid,
                digestAlg,
                authAttrs,
                sigAlg,
                sigVal
        );
        byte[] signerInfos = set(signerInfo);

        byte[] signedData = sequence(
                integer(1), // version
                digestAlgs,
                encapContent,
                certificates,
                signerInfos
        );

        return sequence(
                oid(OID_ID_SIGNED_DATA),
                context(0, signedData, true)
        );
    }
}
