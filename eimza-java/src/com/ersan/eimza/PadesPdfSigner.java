package com.ersan.eimza;

import org.apache.pdfbox.Loader;
import org.apache.pdfbox.pdmodel.PDDocument;
import org.apache.pdfbox.pdmodel.interactive.digitalsignature.ExternalSigningSupport;
import org.apache.pdfbox.pdmodel.interactive.digitalsignature.PDSignature;
import org.apache.pdfbox.pdmodel.interactive.digitalsignature.SignatureOptions;

import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.security.MessageDigest;
import java.util.Calendar;

/** Creates an incremental PAdES/CAdES detached signature without exposing the private key. */
public final class PadesPdfSigner {
    private PadesPdfSigner() {}

    public static byte[] sign(byte[] sourcePdf, String pin, String signerName, String reason, String location) throws Exception {
        if (sourcePdf == null || sourcePdf.length < 5 || sourcePdf[0] != '%' || sourcePdf[1] != 'P') {
            throw new IllegalArgumentException("İmzalanacak PDF geçersizdir.");
        }

        try (PDDocument document = Loader.loadPDF(sourcePdf);
             ByteArrayOutputStream signedOutput = new ByteArrayOutputStream(sourcePdf.length + 32768);
             SignatureOptions options = new SignatureOptions()) {
            options.setPreferredSignatureSize(32768);

            PDSignature signature = new PDSignature();
            signature.setFilter(PDSignature.FILTER_ADOBE_PPKLITE);
            signature.setSubFilter(PDSignature.SUBFILTER_ETSI_CADES_DETACHED);
            signature.setName(clean(signerName, 200));
            signature.setReason(clean(reason, 200));
            signature.setLocation(clean(location, 200));
            signature.setSignDate(Calendar.getInstance());
            document.addSignature(signature, options);

            ExternalSigningSupport external = document.saveIncrementalForExternalSigning(signedOutput);
            byte[] digest;
            try (InputStream content = external.getContent()) {
                digest = sha256(content);
            }
            byte[] cms = TokenManager.sign(pin, digest);
            external.setSignature(cms);
            return signedOutput.toByteArray();
        }
    }

    private static byte[] sha256(InputStream input) throws Exception {
        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        byte[] buffer = new byte[8192];
        int read;
        while ((read = input.read(buffer)) >= 0) {
            if (read > 0) digest.update(buffer, 0, read);
        }
        return digest.digest();
    }

    private static String clean(String value, int max) {
        String clean = value == null ? "" : value.trim();
        return clean.length() <= max ? clean : clean.substring(0, max);
    }
}
