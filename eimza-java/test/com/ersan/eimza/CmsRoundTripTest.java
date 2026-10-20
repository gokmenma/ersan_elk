package com.ersan.eimza;

import java.nio.file.Files;
import java.nio.file.Paths;
import java.security.KeyFactory;
import java.security.MessageDigest;
import java.security.PrivateKey;
import java.security.Signature;
import java.security.spec.PKCS8EncodedKeySpec;
import java.util.Date;

/** OpenSSL ile birlikte calistirilan, detached CAdES-BES yapisal/imza testi. */
public final class CmsRoundTripTest {
    public static void main(String[] args) throws Exception {
        if (args.length != 4) {
            throw new IllegalArgumentException("Kullanim: <content> <cert.der> <key.pk8> <output.p7s>");
        }

        byte[] content = Files.readAllBytes(Paths.get(args[0]));
        byte[] certDer = Files.readAllBytes(Paths.get(args[1]));
        byte[] keyDer = Files.readAllBytes(Paths.get(args[2]));
        byte[] digest = MessageDigest.getInstance("SHA-256").digest(content);
        byte[] signedAttrs = Asn1Der.buildCadesSignedAttrs(digest, certDer, new Date());

        PrivateKey key = KeyFactory.getInstance("RSA")
                .generatePrivate(new PKCS8EncodedKeySpec(keyDer));
        Signature signer = Signature.getInstance("SHA256withRSA");
        signer.initSign(key);
        signer.update(signedAttrs);

        byte[] cms = Asn1Der.buildCmsSignedData(digest, certDer, signedAttrs, signer.sign());
        Files.write(Paths.get(args[3]), cms);
        System.out.println("CMS bytes: " + cms.length);
    }
}
