package com.ersan.eimza;

import java.io.*;
import java.lang.reflect.Method;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.StandardCopyOption;
import java.security.*;
import java.security.cert.CertificateFactory;
import java.security.cert.X509Certificate;
import java.util.*;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class TokenManager {

    private static final String BUNDLED_OPENSC_VERSION = "0.27.1";
    private static final String BUNDLED_OPENSC_RESOURCE_ROOT = "/native/windows-x64/";
    private static final Map<String, String> BUNDLED_OPENSC_FILES = createBundledOpenScFiles();

    private static TokenStatus cachedStatus = null;
    private static long lastCacheTime = 0;
    private static final long CACHE_DURATION_MS = 5 * 60 * 1000L;
    private static final long LAST_GOOD_GRACE_MS = 30 * 60 * 1000L;

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
            list.add(s32 + "akia_pkcs11.dll");         // Yeni AKİA / E-Tuğra paketleri
            list.add(s32 + "aetpkss1.dll");            // SafeSign / E-Tuğra
            list.add(s32 + "OcsCryptolib_pkcs11.dll"); // E-Tuğra OCS
            list.add(s32 + "opensc-pkcs11.dll");       // OpenSC
            list.add(wow64 + "eTPKCS11.dll");
            list.add(wow64 + "akisp11.dll");
            list.add(wow64 + "akia_pkcs11.dll");
            list.add(wow64 + "aetpkss1.dll");

            String programFiles = System.getenv("ProgramFiles");
            String programFilesX86 = System.getenv("ProgramFiles(x86)");
            addWindowsAkiaCandidates(list, programFiles);
            addWindowsAkiaCandidates(list, programFilesX86);
        } else if (os.contains("mac")) {
            list.add("/Library/Frameworks/eToken.framework/Versions/A/libeToken.dylib");
            list.add("/usr/local/lib/libakisp11.dylib");
            list.add("/usr/local/lib/opensc-pkcs11.so");
        } else {
            // Linux
            list.add("/usr/lib/libeToken.so");           // SafeNet
            list.add("/usr/lib64/libeToken.so");
            list.add("/usr/lib/x86_64-linux-gnu/libeToken.so");
            list.add("/opt/Akia/libakisp11.so");             // AKİS akia-bin varsayılan kurulumu
            list.add("/opt/arksigner/drivers/akis/x64/libakisp11.so");
            list.add("/usr/lib/jvm/java-8-openjdk/jre/lib/amd64/libakisp11.so");
            list.add("/usr/lib/libakisp11.so");          // AKİS
            list.add("/usr/lib64/libakisp11.so");
            list.add("/usr/lib/opensc-pkcs11.so");
            list.add("/usr/lib/libaetpkss.so");
            list.add("/usr/lib/libetpkcs11.so");
        }
        return list;
    }

    private static void addWindowsAkiaCandidates(List<String> list, String root) {
        if (root == null || root.trim().isEmpty()) return;
        String[] relativePaths = {
                "Akia\\akisp11.dll", "AKIA\\akisp11.dll",
                "Akia\\akia_pkcs11.dll", "AKIA\\akia_pkcs11.dll",
                "E-Tugra\\akisp11.dll", "E-Tuğra\\akisp11.dll"
        };
        for (String relativePath : relativePaths) {
            list.add(root + "\\" + relativePath);
        }
    }

    public static String getPkcs11ToolBin() {
        String bundledTool = extractBundledOpenScRuntime();
        String[] candidates = {
                bundledTool,
                "C:\\Program Files\\OpenSC Project\\OpenSC\\tools\\pkcs11-tool.exe",
                "C:\\Program Files (x86)\\OpenSC Project\\OpenSC\\tools\\pkcs11-tool.exe",
                "/usr/bin/pkcs11-tool",
                "/usr/local/bin/pkcs11-tool",
                "pkcs11-tool"
        };
        for (String c : candidates) {
            if (c == null || c.isEmpty()) continue;
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
        return null;
    }

    private static Map<String, String> createBundledOpenScFiles() {
        Map<String, String> files = new LinkedHashMap<>();
        files.put("pkcs11-tool.exe", "cd5c6df2679d0bce69695bdabd977bbb7cfe4e25b8d7eefb397799b7f5a7bef6");
        files.put("opensc.dll", "b2f256bbdfda59fe97f899200e215097d243e89e28adeae85e35497347063332");
        files.put("LICENSE-OpenSC-LGPL-2.1.txt", "376b54d4c5f4aa99421823fa4da93e3ab73096fce2400e89858632aa7da24a14");
        return Collections.unmodifiableMap(files);
    }

    private static String extractBundledOpenScRuntime() {
        if (!System.getProperty("os.name", "").toLowerCase().contains("win")) return null;

        String localAppData = System.getenv("LOCALAPPDATA");
        File runtimeDir = localAppData != null && !localAppData.trim().isEmpty()
                ? new File(localAppData, "ErsanEImza\\runtime\\opensc-" + BUNDLED_OPENSC_VERSION + "-x64")
                : new File(System.getProperty("user.home", "."), ".ersan-eimza/runtime/opensc-" + BUNDLED_OPENSC_VERSION + "-x64");

        try {
            if (!runtimeDir.isDirectory() && !runtimeDir.mkdirs()) return null;
            for (Map.Entry<String, String> bundledFile : BUNDLED_OPENSC_FILES.entrySet()) {
                File target = new File(runtimeDir, bundledFile.getKey());
                if (target.isFile() && bundledFile.getValue().equalsIgnoreCase(sha256(target))) continue;

                try (InputStream input = TokenManager.class.getResourceAsStream(BUNDLED_OPENSC_RESOURCE_ROOT + bundledFile.getKey())) {
                    if (input == null) return null;
                    File temporary = File.createTempFile("ersan-eimza-", ".tmp", runtimeDir);
                    try {
                        Files.copy(input, temporary.toPath(), StandardCopyOption.REPLACE_EXISTING);
                        if (!bundledFile.getValue().equalsIgnoreCase(sha256(temporary))) return null;
                        Files.move(temporary.toPath(), target.toPath(), StandardCopyOption.REPLACE_EXISTING);
                    } finally {
                        if (temporary.exists()) temporary.delete();
                    }
                }
            }
            File tool = new File(runtimeDir, "pkcs11-tool.exe");
            return tool.isFile() ? tool.getAbsolutePath() : null;
        } catch (Throwable ignored) {
            return null;
        }
    }

    private static String sha256(File file) throws Exception {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        try (InputStream input = new BufferedInputStream(new FileInputStream(file))) {
            byte[] buffer = new byte[8192];
            int read;
            while ((read = input.read(buffer)) >= 0) {
                if (read > 0) digest.update(buffer, 0, read);
            }
        }
        StringBuilder value = new StringBuilder();
        for (byte item : digest.digest()) value.append(String.format("%02x", item & 0xff));
        return value.toString();
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
        boolean windows = System.getProperty("os.name", "").toLowerCase(Locale.ROOT).contains("win");

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
            if (pkcsTool != null) try {
                ToolResult objectResult = runTool(new String[]{pkcsTool, "--module", mod, "-O"});
                BufferedReader r = new BufferedReader(new StringReader(objectResult.output));
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
                if (objectResult.exitCode == 0 && (!owner.isEmpty() || !tc.isEmpty())) {
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

            // Taşınabilir pkcs11-tool 64-bit'tir. Kurum bilgisayarında yalnız
            // 32-bit AKİS sürücüsü varsa çalışan Java'nın kendi mimarisiyle
            // sertifikayı okuyarak güvenli fallback uygula. Yalnız sağlayıcının
            // yüklenmesi başarı sayılmaz; gerçek X.509 sertifika zorunludur.
            if (windows) {
                TokenStatus javaDetected = detectWithJavaPkcs11(mod, devType);
                if (javaDetected != null) {
                    cachedStatus = javaDetected;
                    lastCacheTime = now;
                    return javaDetected;
                }
            }

            // Sadece PKCS#11 sağlayıcısının yüklenebilmesi kartın gerçekten takılı
            // olduğunu göstermez. Özellikle AKİS'te ikinci sağlayıcı oturumu mevcut
            // WSM oturumunu kapatabildiği için algılama sırasında SunPKCS11 açılmaz.
        }

        // AKİS ara katmanı kısa süreli sessionDeactivated döndürürse son ayrıntılı
        // sonucu koru. Kullanıcının "Kartı Yeniden Tara" eylemi clearCache ile bu
        // güvenli geri dönüşü de temizler ve fiziksel kart değişimini zorla sorgular.
        if (cachedStatus != null && cachedStatus.success && (now - lastCacheTime < LAST_GOOD_GRACE_MS)) {
            return cachedStatus;
        }

        status.success = false;
        status.message = "Sistemde takılı aktif bir e-imza cihazı bulunamadı. Lütfen USB kartınızı takınız.";
        return status;
    }

    private static TokenStatus detectWithJavaPkcs11(String modulePath, String deviceType) {
        Provider provider = null;
        try {
            String safeName = "WinDetect_" + Math.abs((modulePath + System.nanoTime()).hashCode());
            provider = createSunPKCS11(safeName, modulePath);
            Security.addProvider(provider);
            KeyStore keyStore = KeyStore.getInstance("PKCS11", provider);
            keyStore.load(null, null);
            Enumeration<String> aliases = keyStore.aliases();
            while (aliases.hasMoreElements()) {
                java.security.cert.Certificate cert = keyStore.getCertificate(aliases.nextElement());
                if (!(cert instanceof X509Certificate)) continue;
                X509Certificate x509 = (X509Certificate) cert;
                String subject = x509.getSubjectX500Principal().getName();
                String owner = extractSubjectValue(subject, "CN");
                String tc = extractSubjectValue(subject, "SERIALNUMBER").replaceAll("[^0-9]", "");

                TokenStatus found = new TokenStatus();
                found.success = true;
                found.modulePath = modulePath;
                found.deviceType = deviceType;
                found.tokenLabel = "E-İmza Donanımı";
                found.ownerName = owner.isEmpty() ? "E-İmza Kartı Takılı" : owner;
                found.tcno = tc.length() == 11 ? tc : "";
                found.subject = subject;
                return found;
            }
        } catch (Throwable ignored) {
            // Aday DLL yanlış mimaride veya kart bu sürücüye ait değil.
        } finally {
            if (provider != null) Security.removeProvider(provider.getName());
        }
        return null;
    }

    private static String extractSubjectValue(String subject, String key) {
        Matcher matcher = Pattern.compile("(?:^|,)" + Pattern.quote(key) + "=([^,]+)", Pattern.CASE_INSENSITIVE).matcher(subject);
        return matcher.find() ? cleanTurkishChars(matcher.group(1).trim()) : "";
    }

    public static byte[] sign(String pin, byte[] rawDigest) throws Exception {
        TokenStatus token = detectActiveToken();
        if (!token.success || token.modulePath == null) {
            throw new RuntimeException("E-İmza cihazı bulunamadı. Lütfen USB kartınızı takınız.");
        }

        // Windows kart suruculerinin bazilari SunPKCS11 cagrisinda JVM'i native olarak
        // sonlandirabiliyor. Gomulu OpenSC araci ayri bir surecte calistigi icin bir
        // surucu hatasi yerel HTTP ajanini ve 9191 baglantisini dusurmez.
        if (System.getProperty("os.name", "").toLowerCase().contains("win")) {
            return signWithPkcs11Tool(pin, rawDigest, token.modulePath);
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

                String keyAlgorithm = signerCert.getPublicKey().getAlgorithm();
                Signature signer = Signature.getInstance(signatureName(keyAlgorithm), p);
                signer.initSign(privKey);
                signer.update(signedAttrsDer);
                byte[] signatureBytes = signer.sign();

                verifyCardSignature(certDer, signedAttrsDer, signatureBytes);

                return Asn1Der.buildCmsSignedData(rawDigest, certDer, signedAttrsDer, signatureBytes, keyAlgorithm);
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
        if (toolBin == null) {
            throw new RuntimeException("Taşınabilir OpenSC imza motoru hazırlanamadı. E-İmza uygulamasını yeniden indirip çalıştırınız.");
        }
        SigningObjects signingObjects = findSigningObjects(toolBin, modulePath, pin);
        File certFile = File.createTempFile("cert_", ".der");
        certFile.deleteOnExit();

        // 1. Imza anahtariyla ayni CKA_ID degerine sahip sertifikayi oku.
        ToolResult certResult = runTool(new String[]{
                toolBin, "--module", modulePath, "--login", "--pin", pin,
                "--read-object", "--type", "cert", "--id", signingObjects.certificateId,
                "--output-file", certFile.getAbsolutePath()
        });
        if (certResult.exitCode != 0 || !certFile.isFile() || certFile.length() == 0) {
            throw new RuntimeException("Karttan imza sertifikası okunamadı: " + summarizeToolError(certResult.output));
        }
        byte[] certDer = Files.readAllBytes(certFile.toPath());
        certFile.delete();
        X509Certificate signingCertificate = readCertificate(certDer);
        String keyAlgorithm = signingCertificate.getPublicKey().getAlgorithm();

        // 2. CAdES SignedAttributes hazırla
        Date now = new Date();
        byte[] signedAttrsDer = Asn1Der.buildCadesSignedAttrs(rawDigest, certDer, now);

        File inData = File.createTempFile("sign_in_", ".bin");
        File outData = File.createTempFile("sign_out_", ".bin");
        inData.deleteOnExit();
        outData.deleteOnExit();
        byte[] tokenInput = isEcAlgorithm(keyAlgorithm)
                ? MessageDigest.getInstance("SHA-256").digest(signedAttrsDer)
                : signedAttrsDer;
        Files.write(inData.toPath(), tokenInput);

        // 3. İmzala
        ToolResult signResult = runTool(new String[]{
                toolBin, "--module", modulePath, "--login", "--pin", pin,
                "--sign", "--id", signingObjects.privateKeyId, "-m",
                isEcAlgorithm(keyAlgorithm) ? "ECDSA" : "SHA256-RSA-PKCS",
                "--input-file", inData.getAbsolutePath(),
                "--output-file", outData.getAbsolutePath()
        });

        inData.delete();

        if (signResult.exitCode != 0) {
            String err = signResult.output;
            if (err.contains("PIN_INCORRECT") || err.contains("CKR_PIN_INCORRECT")) {
                throw new RuntimeException("Girdiğiniz e-imza PIN kodu hatalıdır.");
            } else if (err.contains("PIN_LOCKED") || err.contains("CKR_PIN_LOCKED")) {
                throw new RuntimeException("E-İmza kartınız hatalı denemeler nedeniyle kilitlenmiştir.");
            }
            throw new RuntimeException("Token imzalama hatası: " + summarizeToolError(err));
        }

        byte[] signatureBytes = Files.readAllBytes(outData.toPath());
        outData.delete();

        if (isEcAlgorithm(keyAlgorithm)) {
            signatureBytes = ecdsaRawToDer(signatureBytes);
        }

        verifyCardSignature(certDer, signedAttrsDer, signatureBytes);

        return Asn1Der.buildCmsSignedData(rawDigest, certDer, signedAttrsDer, signatureBytes, keyAlgorithm);
    }

    private static void verifyCardSignature(byte[] certDer, byte[] signedAttrsDer, byte[] rsaSigBytes) throws Exception {
        X509Certificate certificate = readCertificate(certDer);
        Signature verifier = Signature.getInstance(signatureName(certificate.getPublicKey().getAlgorithm()));
        verifier.initVerify(certificate.getPublicKey());
        verifier.update(signedAttrsDer);
        if (!verifier.verify(rsaSigBytes)) {
            throw new RuntimeException("Kartın ürettiği elektronik imza doğrulanamadı. Lütfen kartı çıkarıp yeniden takınız.");
        }
    }

    private static X509Certificate readCertificate(byte[] certDer) throws Exception {
        CertificateFactory factory = CertificateFactory.getInstance("X.509");
        return (X509Certificate) factory.generateCertificate(new ByteArrayInputStream(certDer));
    }

    private static boolean isEcAlgorithm(String algorithm) {
        return algorithm != null && (algorithm.equalsIgnoreCase("EC") || algorithm.toUpperCase(Locale.ROOT).contains("ECDSA"));
    }

    private static String signatureName(String keyAlgorithm) {
        return isEcAlgorithm(keyAlgorithm) ? "SHA256withECDSA" : "SHA256withRSA";
    }

    /** PKCS#11 CKM_ECDSA r||s çıktısını CMS'in beklediği ASN.1 DER SEQUENCE biçimine çevirir. */
    private static byte[] ecdsaRawToDer(byte[] raw) throws IOException {
        if (raw == null || raw.length == 0 || (raw.length & 1) != 0) {
            throw new IOException("Kart geçersiz ECDSA imza verisi üretti.");
        }
        int half = raw.length / 2;
        byte[] r = unsignedDerInteger(Arrays.copyOfRange(raw, 0, half));
        byte[] s = unsignedDerInteger(Arrays.copyOfRange(raw, half, raw.length));
        return Asn1Der.sequence(Asn1Der.encodeTlv(0x02, r), Asn1Der.encodeTlv(0x02, s));
    }

    private static byte[] unsignedDerInteger(byte[] value) {
        int start = 0;
        while (start < value.length - 1 && value[start] == 0) start++;
        byte[] normalized = Arrays.copyOfRange(value, start, value.length);
        if ((normalized[0] & 0x80) == 0) return normalized;
        byte[] positive = new byte[normalized.length + 1];
        System.arraycopy(normalized, 0, positive, 1, normalized.length);
        return positive;
    }

    private static SigningObjects findSigningObjects(String toolBin, String modulePath, String pin) throws Exception {
        ToolResult listResult = runTool(new String[]{
                toolBin, "--module", modulePath, "--login", "--pin", pin, "--list-objects"
        });
        String output = listResult.output != null ? listResult.output : "";
        if (listResult.exitCode != 0) {
            String upper = output.toUpperCase(Locale.ROOT);
            if (upper.contains("CKR_PIN_INCORRECT") || upper.contains("PIN_INCORRECT")) {
                throw new RuntimeException("Girdiğiniz e-imza PIN kodu hatalıdır.");
            }
            if (upper.contains("CKR_PIN_LOCKED") || upper.contains("PIN_LOCKED")) {
                throw new RuntimeException("E-İmza kartınız hatalı denemeler nedeniyle kilitlenmiştir.");
            }
            throw new RuntimeException("Kart nesneleri okunamadı: " + summarizeToolError(output));
        }

        Set<String> certificateIds = extractObjectIds(output, "Certificate Object");
        Set<String> privateKeyIds = extractObjectIds(output, "Private Key Object");
        for (String privateKeyId : privateKeyIds) {
            if (certificateIds.contains(privateKeyId)) {
                return new SigningObjects(privateKeyId, privateKeyId);
            }
        }

        // Bazı Linux PKCS#11 sürücülerinde sertifika ile özel anahtarın CKA_ID
        // değerleri farklıdır. Sabit bir deneme verisini her özel anahtarla
        // imzalayıp karttaki sertifikaların açık anahtarıyla doğrulayarak gerçek
        // anahtar/sertifika çiftini güvenli biçimde bul.
        SigningObjects verifiedPair = findPairByCryptographicMatch(
                toolBin, modulePath, pin, privateKeyIds, certificateIds
        );
        if (verifiedPair != null) return verifiedPair;

        if (certificateIds.isEmpty()) {
            throw new RuntimeException("Kartta okunabilir imza sertifikası bulunamadı.");
        }
        if (privateKeyIds.isEmpty()) {
            throw new RuntimeException("Kartta kullanılabilir özel imza anahtarı bulunamadı.");
        }
        throw new RuntimeException("Kartta özel imza anahtarıyla eşleşen sertifika bulunamadı.");
    }

    private static SigningObjects findPairByCryptographicMatch(
            String toolBin,
            String modulePath,
            String pin,
            Set<String> privateKeyIds,
            Set<String> certificateIds
    ) {
        byte[] probe = "Ersan-EImza-Key-Match-v1".getBytes(StandardCharsets.UTF_8);
        for (String privateKeyId : privateKeyIds) {
            File probeInput = null;
            File probeSignature = null;
            try {
                probeInput = File.createTempFile("ersan_key_probe_", ".bin");
                probeSignature = File.createTempFile("ersan_key_sig_", ".bin");
                Files.write(probeInput.toPath(), probe);
                ToolResult signResult = runTool(new String[]{
                        toolBin, "--module", modulePath, "--login", "--pin", pin,
                        "--sign", "--id", privateKeyId, "-m", "SHA256-RSA-PKCS",
                        "--input-file", probeInput.getAbsolutePath(),
                        "--output-file", probeSignature.getAbsolutePath()
                });
                if (signResult.exitCode != 0 || probeSignature.length() == 0) continue;
                byte[] signature = Files.readAllBytes(probeSignature.toPath());

                for (String certificateId : certificateIds) {
                    File certificateFile = null;
                    try {
                        certificateFile = File.createTempFile("ersan_cert_probe_", ".der");
                        ToolResult certResult = runTool(new String[]{
                                toolBin, "--module", modulePath, "--login", "--pin", pin,
                                "--read-object", "--type", "cert", "--id", certificateId,
                                "--output-file", certificateFile.getAbsolutePath()
                        });
                        if (certResult.exitCode != 0 || certificateFile.length() == 0) continue;
                        byte[] certDer = Files.readAllBytes(certificateFile.toPath());
                        verifyCardSignature(certDer, probe, signature);
                        return new SigningObjects(privateKeyId, certificateId);
                    } catch (Exception ignored) {
                        // Bu sertifika ilgili özel anahtara ait değil; sıradakini dene.
                    } finally {
                        if (certificateFile != null) certificateFile.delete();
                    }
                }
            } catch (Exception ignored) {
                // Bu özel anahtar imzalama için kullanılamıyor; sıradakini dene.
            } finally {
                if (probeInput != null) probeInput.delete();
                if (probeSignature != null) probeSignature.delete();
            }
        }
        return null;
    }

    private static Set<String> extractObjectIds(String output, String objectHeading) {
        Set<String> ids = new LinkedHashSet<>();
        boolean matchingObject = false;
        Pattern idPattern = Pattern.compile("^ID\\s*:\\s*(?:0x)?([0-9a-fA-F][0-9a-fA-F :.-]*)$", Pattern.CASE_INSENSITIVE);
        for (String rawLine : output.split("\\r?\\n")) {
            String line = rawLine.trim();
            if (line.toLowerCase(Locale.ROOT).contains(" object")) {
                matchingObject = line.toLowerCase(Locale.ROOT).startsWith(objectHeading.toLowerCase(Locale.ROOT));
                continue;
            }
            if (!matchingObject) continue;
            Matcher idMatcher = idPattern.matcher(line);
            if (!idMatcher.matches()) continue;
            String id = idMatcher.group(1).replaceAll("[^0-9a-fA-F]", "").toLowerCase(Locale.ROOT);
            if (!id.isEmpty()) ids.add(id);
        }
        return ids;
    }

    private static class SigningObjects {
        final String privateKeyId;
        final String certificateId;

        SigningObjects(String privateKeyId, String certificateId) {
            this.privateKeyId = privateKeyId;
            this.certificateId = certificateId;
        }
    }

    private static ToolResult runTool(String[] command) throws Exception {
        ProcessBuilder builder = new ProcessBuilder(command);
        builder.redirectErrorStream(true);
        Process process = builder.start();
        ByteArrayOutputStream output = new ByteArrayOutputStream();
        try (InputStream input = process.getInputStream()) {
            byte[] buffer = new byte[4096];
            int read;
            while ((read = input.read(buffer)) >= 0) {
                if (read > 0) output.write(buffer, 0, read);
            }
        }
        int exitCode = process.waitFor();
        return new ToolResult(exitCode, new String(output.toByteArray(), StandardCharsets.UTF_8));
    }

    private static String summarizeToolError(String output) {
        String message = output == null ? "" : output.replaceAll("[\\r\\n]+", " ").trim();
        if (message.length() > 300) message = message.substring(0, 300) + "…";
        return message.isEmpty() ? "Bilinmeyen kart hatası" : message;
    }

    private static class ToolResult {
        final int exitCode;
        final String output;

        ToolResult(int exitCode, String output) {
            this.exitCode = exitCode;
            this.output = output;
        }
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
