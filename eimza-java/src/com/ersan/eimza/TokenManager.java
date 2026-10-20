package com.ersan.eimza;

import java.io.*;
import java.lang.reflect.Method;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.security.*;
import java.security.cert.X509Certificate;
import java.util.*;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class TokenManager {

    private static TokenStatus cachedStatus = null;
    private static long lastCacheTime = 0;
    private static final long CACHE_DURATION_MS = 3000; // 3 saniye

    public static synchronized void clearCache() {
        cachedStatus = null;
        lastCacheTime = 0;
    }

    public static class TokenStatus {
        public boolean success;
        public String modulePath;
        public String deviceType;
        public String tokenLabel;
        public String ownerName;
        public String tcno;
        public String subject;
        public String message;
    }

    public static List<String> getCandidateModules() {
        String os = System.getProperty("os.name", "").toLowerCase();
        List<String> list = new ArrayList<>();

        if (os.contains("win")) {
            String sys32 = System.getenv("SystemRoot");
            if (sys32 == null || sys32.isEmpty()) sys32 = "C:\\Windows";
            String s32 = sys32 + "\\System32\\";
            String wow64 = sys32 + "\\SysWOW64\\";

            list.add(s32 + "eTPKCS11.dll");            // SafeNet / TÜRKKEP / E-GÜVEN
            list.add(s32 + "akisp11.dll");             // AKİS / KamuSM / E-Tuğra
            list.add(s32 + "aetpkss1.dll");            // SafeSign / E-Tuğra
            list.add(s32 + "OcsCryptolib_pkcs11.dll"); // E-Tuğra OCS
            list.add(s32 + "opensc-pkcs11.dll");       // OpenSC
            list.add(wow64 + "eTPKCS11.dll");
            list.add(wow64 + "akisp11.dll");
            list.add(wow64 + "aetpkss1.dll");
        } else if (os.contains("mac")) {
            list.add("/Library/Frameworks/eToken.framework/Versions/A/libeToken.dylib");
            list.add("/usr/local/lib/libakisp11.dylib");
            list.add("/usr/local/lib/opensc-pkcs11.so");
        } else {
            // Linux
            list.add("/usr/lib/libeToken.so");           // SafeNet
            list.add("/usr/lib64/libeToken.so");
            list.add("/usr/lib/x86_64-linux-gnu/libeToken.so");
            list.add("/usr/lib/libakisp11.so");          // AKİS
            list.add("/usr/lib64/libakisp11.so");
            list.add("/usr/lib/opensc-pkcs11.so");
            list.add("/usr/lib/libaetpkss.so");
            list.add("/usr/lib/libetpkcs11.so");
        }
        return list;
    }

    public static String getPkcs11ToolBin() {
        String[] candidates = {
                "pkcs11-tool",
                "C:\\Program Files\\OpenSC Project\\OpenSC\\tools\\pkcs11-tool.exe",
                "C:\\Program Files (x86)\\OpenSC Project\\OpenSC\\tools\\pkcs11-tool.exe",
                "/usr/bin/pkcs11-tool",
                "/usr/local/bin/pkcs11-tool"
        };
        for (String c : candidates) {
            File f = new File(c);
            if (f.isAbsolute()) {
                if (f.exists()) return c;
            } else {
                try {
                    Process p = Runtime.getRuntime().exec(new String[]{c, "-h"});
                    p.waitFor();
                    return c;
                } catch (Throwable ignored) {}
            }
        }
        return "pkcs11-tool";
    }

    public static Provider createSunPKCS11(String name, String modulePath) throws Exception {
        String config = "name = " + name + "\nlibrary = " + modulePath + "\n";
        File cfgFile = File.createTempFile("pkcs11_", ".cfg");
        cfgFile.deleteOnExit();
        Files.write(cfgFile.toPath(), config.getBytes(StandardCharsets.UTF_8));

        // 1. Try Java 9+ Provider.configure
        try {
            Provider proto = Security.getProvider("SunPKCS11");
            if (proto != null) {
                Method m = proto.getClass().getMethod("configure", String.class);
                return (Provider) m.invoke(proto, cfgFile.getAbsolutePath());
            }
        } catch (Throwable ignored) {}

        // 2. Try Java 8 constructor
        try {
            Class<?> cls = Class.forName("sun.security.pkcs11.SunPKCS11");
            return (Provider) cls.getConstructor(String.class).newInstance(cfgFile.getAbsolutePath());
        } catch (Throwable t) {
            throw new RuntimeException("SunPKCS11 başlatılamadı: " + t.getMessage(), t);
        }
    }

    public static synchronized TokenStatus detectActiveToken() {
        long now = System.currentTimeMillis();
        if (cachedStatus != null && cachedStatus.success && (now - lastCacheTime < CACHE_DURATION_MS)) {
            return cachedStatus;
        }

        TokenStatus status = new TokenStatus();
        List<String> modules = getCandidateModules();
        String pkcsTool = getPkcs11ToolBin();

        for (String mod : modules) {
            File f = new File(mod);
            if (!f.exists()) continue;

            String devType = "Akıllı Kart / Token";
            String mLow = mod.toLowerCase();
            if (mLow.contains("etoken")) devType = "SafeNet (TÜRKKEP / E-GÜVEN)";
            else if (mLow.contains("akis")) devType = "AKİS (KamuSM / E-Tuğra)";
            else if (mLow.contains("aetpkss") || mLow.contains("ocscrypto")) devType = "E-Tuğra Token";
            else if (mLow.contains("opensc")) devType = "OpenSC Akıllı Kart";

            // YÖNTEM A: pkcs11-tool ile nesneleri sorgula (hızlı ve PIN sormaz)
            try {
                Process p = Runtime.getRuntime().exec(new String[]{pkcsTool, "--module", mod, "-O"});
                BufferedReader r = new BufferedReader(new InputStreamReader(p.getInputStream(), StandardCharsets.UTF_8));
                String line;
                String owner = "";
                String tc = "";
                String fullSub = "";

                while ((line = r.readLine()) != null) {
                    if (line.contains("CN=")) {
                        Matcher m = Pattern.compile("CN=([^,\n]+)").matcher(line);
                        if (m.find()) owner = cleanTurkishChars(m.group(1).trim());
                    }
                    if (line.contains("serialNumber=")) {
                        Matcher m = Pattern.compile("serialNumber=([0-9]{11})").matcher(line);
                        if (m.find()) tc = m.group(1).trim();
                    }
                    if (line.contains("DN:")) {
                        fullSub = line.trim();
                    }
                }
                int exitCode = p.waitFor();
                if (exitCode == 0 && (!owner.isEmpty() || !tc.isEmpty())) {
                    status.success = true;
                    status.modulePath = mod;
                    status.deviceType = devType;
                    status.tokenLabel = "E-İmza Donanımı";
                    status.ownerName = owner.isEmpty() ? "E-İmza Kartı Takılı" : owner;
                    status.tcno = tc;
                    status.subject = fullSub;
                    cachedStatus = status;
                    lastCacheTime = now;
                    return status;
                }
            } catch (Throwable ignored) {}

            // YÖNTEM B: SunPKCS11 sağlayıcısını doğrudan kontrol et
            try {
                String safeName = "Detect_" + Math.abs(mod.hashCode() % 10000);
                Provider p = createSunPKCS11(safeName, mod);
                Security.addProvider(p);

                status.success = true;
                status.modulePath = mod;
                status.deviceType = devType;
                status.tokenLabel = "E-İmza Donanımı";
                status.ownerName = "E-İmza Kartı Takılı";
                status.tcno = "";
                status.subject = "";
                cachedStatus = status;
                lastCacheTime = now;
                return status;
            } catch (Throwable ignored) {}
        }

        status.success = false;
        status.message = "Sistemde takılı aktif bir e-imza cihazı bulunamadı. Lütfen USB kartınızı takınız.";
        cachedStatus = null;
        lastCacheTime = 0;
        return status;
    }

    public static byte[] sign(String pin, byte[] rawDigest) throws Exception {
        TokenStatus token = detectActiveToken();
        if (!token.success || token.modulePath == null) {
            throw new RuntimeException("E-İmza cihazı bulunamadı. Lütfen USB kartınızı takınız.");
        }

        // 1. Önce SunPKCS11 Java motoru ile dene
        try {
            String safeName = "Sign_" + System.currentTimeMillis();
            Provider p = createSunPKCS11(safeName, token.modulePath);
            Security.addProvider(p);

            KeyStore ks = KeyStore.getInstance("PKCS11", p);
            ks.load(null, pin.toCharArray());

            Enumeration<String> aliases = ks.aliases();
            PrivateKey privKey = null;
            X509Certificate signerCert = null;

            while (aliases.hasMoreElements()) {
                String alias = aliases.nextElement();
                if (ks.isKeyEntry(alias)) {
                    privKey = (PrivateKey) ks.getKey(alias, null);
                    signerCert = (X509Certificate) ks.getCertificate(alias);
                    if (privKey != null && signerCert != null) break;
                }
            }

            if (privKey != null && signerCert != null) {
                byte[] certDer = signerCert.getEncoded();
                Date now = new Date();
                byte[] signedAttrsDer = Asn1Der.buildCadesSignedAttrs(rawDigest, certDer, now);

                Signature rsa = Signature.getInstance("SHA256withRSA", p);
                rsa.initSign(privKey);
                rsa.update(signedAttrsDer);
                byte[] rsaSigBytes = rsa.sign();

                return Asn1Der.buildCmsSignedData(rawDigest, certDer, signedAttrsDer, rsaSigBytes);
            }
        } catch (Throwable t) {
            String err = t.getMessage() != null ? t.getMessage() : "";
            if (err.toLowerCase().contains("pin") || err.contains("CKR_PIN_INCORRECT") || err.contains("incorrect")) {
                throw new RuntimeException("Girdiğiniz e-imza PIN kodu hatalıdır.");
            } else if (err.contains("CKR_PIN_LOCKED") || err.contains("locked")) {
                throw new RuntimeException("E-İmza kartınız hatalı denemeler nedeniyle kilitlenmiştir.");
            }
            // SunPKCS11 başarısız olursa pkcs11-tool fallback'e geç
        }

        // 2. Yedek Motor: pkcs11-tool ile doğrudan imzalama
        return signWithPkcs11Tool(pin, rawDigest, token.modulePath);
    }

    private static byte[] signWithPkcs11Tool(String pin, byte[] rawDigest, String modulePath) throws Exception {
        String toolBin = getPkcs11ToolBin();
        File certFile = File.createTempFile("cert_", ".der");
        certFile.deleteOnExit();

        // 1. Sertifikayı oku
        Process pCert = Runtime.getRuntime().exec(new String[]{
                toolBin, "--module", modulePath, "--read-object", "--type", "cert", "--output-file", certFile.getAbsolutePath()
        });
        if (pCert.waitFor() != 0) {
            throw new RuntimeException("Karttan sertifika okunamadı.");
        }
        byte[] certDer = Files.readAllBytes(certFile.toPath());
        certFile.delete();

        // 2. CAdES SignedAttributes hazırla
        Date now = new Date();
        byte[] signedAttrsDer = Asn1Der.buildCadesSignedAttrs(rawDigest, certDer, now);

        File inData = File.createTempFile("sign_in_", ".bin");
        File outData = File.createTempFile("sign_out_", ".bin");
        inData.deleteOnExit();
        outData.deleteOnExit();
        Files.write(inData.toPath(), signedAttrsDer);

        // 3. İmzala
        Process pSign = Runtime.getRuntime().exec(new String[]{
                toolBin, "--module", modulePath, "--login", "--pin", pin,
                "--sign", "-m", "SHA256-RSA-PKCS",
                "--input-file", inData.getAbsolutePath(),
                "--output-file", outData.getAbsolutePath()
        });

        int code = pSign.waitFor();
        inData.delete();

        if (code != 0) {
            BufferedReader r = new BufferedReader(new InputStreamReader(pSign.getErrorStream(), StandardCharsets.UTF_8));
            StringBuilder sb = new StringBuilder();
            String l;
            while ((l = r.readLine()) != null) sb.append(l).append(" ");
            String err = sb.toString();
            if (err.contains("PIN_INCORRECT") || err.contains("CKR_PIN_INCORRECT")) {
                throw new RuntimeException("Girdiğiniz e-imza PIN kodu hatalıdır.");
            } else if (err.contains("PIN_LOCKED") || err.contains("CKR_PIN_LOCKED")) {
                throw new RuntimeException("E-İmza kartınız hatalı denemeler nedeniyle kilitlenmiştir.");
            }
            throw new RuntimeException("Token imzalama hatası: " + err);
        }

        byte[] rsaSigBytes = Files.readAllBytes(outData.toPath());
        outData.delete();

        return Asn1Der.buildCmsSignedData(rawDigest, certDer, signedAttrsDer, rsaSigBytes);
    }

    private static String cleanTurkishChars(String s) {
        if (s == null) return "";
        return s.replace("\\C4\\B0", "İ")
                .replace("\\C3\\96", "Ö")
                .replace("\\C3\\9C", "Ü")
                .replace("\\C3\\87", "Ç")
                .replace("\\C4\\9E", "Ğ")
                .replace("\\C5\\9E", "Ş")
                .replace("\\C4\\B1", "ı")
                .replace("\\C3\\B6", "ö")
                .replace("\\C3\\BC", "ü")
                .replace("\\C3\\A7", "ç")
                .replace("\\C4\\9F", "ğ")
                .replace("\\C5\\9F", "ş")
                .replace("\"", "");
    }
}
