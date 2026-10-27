<?php

namespace App\Service;

final class CmsSignedDataBuilder
{
    private const OID_DATA = '1.2.840.113549.1.7.1';
    private const OID_SIGNED_DATA = '1.2.840.113549.1.7.2';
    private const OID_CONTENT_TYPE = '1.2.840.113549.1.9.3';
    private const OID_MESSAGE_DIGEST = '1.2.840.113549.1.9.4';
    private const OID_SIGNING_TIME = '1.2.840.113549.1.9.5';
    private const OID_SIGNING_CERTIFICATE_V2 = '1.2.840.113549.1.9.16.2.47';
    private const OID_SHA256 = '2.16.840.1.101.3.4.2.1';
    private const OID_RSA = '1.2.840.113549.1.1.1';
    private const OID_ECDSA_SHA256 = '1.2.840.10045.4.3.2';

    public static function signedAttributes(string $contentDigest, string $certHash, int $signingTime, ?string $certificate = null): string
    {
        if ($certificate !== null) {
            [$issuer, $serial] = self::issuerAndSerial($certificate);
            $essCertId = self::sequence(
                self::sequence(self::oid(self::OID_SHA256))
                . self::tlv(0x04, $certHash)
                . self::sequence(self::sequence(self::tlv(0xA4, $issuer)) . $serial)
            );
        } else {
            $essCertId = self::sequence(self::tlv(0x04, $certHash));
        }
        $attributes = [
            self::sequence(self::oid(self::OID_CONTENT_TYPE) . self::set([self::oid(self::OID_DATA)])),
            self::sequence(self::oid(self::OID_SIGNING_TIME) . self::set([self::tlv(0x17, gmdate('ymdHis', $signingTime) . 'Z')])),
            self::sequence(self::oid(self::OID_MESSAGE_DIGEST) . self::set([self::tlv(0x04, $contentDigest)])),
            self::sequence(self::oid(self::OID_SIGNING_CERTIFICATE_V2) . self::set([
                self::sequence(self::sequence($essCertId)),
            ])),
        ];
        return self::set($attributes);
    }

    private static function issuerAndSerial(string $certificate): array
    {
        $tbs = self::children(self::children($certificate)[0]['raw']);
        $offset = ($tbs[0]['tag'] === 0xA0) ? 1 : 0;
        return [$tbs[$offset + 2]['raw'], $tbs[$offset]['raw']];
    }

    public static function signedData(string $signedAttributes, string $signature, string $signerCertificate, array $extraCertificates = []): string
    {
        if ($signedAttributes === '' || ord($signedAttributes[0]) !== 0x31) {
            throw new \RuntimeException('İmzalı öznitelik kümesi geçersiz.');
        }
        $tbs = self::children(self::children($signerCertificate)[0]['raw']);
        $offset = ($tbs[0]['tag'] === 0xA0) ? 1 : 0;
        $serial = $tbs[$offset]['raw'];
        $issuer = $tbs[$offset + 2]['raw'];

        $publicKey = openssl_pkey_get_public(self::pem($signerCertificate));
        $details = $publicKey ? openssl_pkey_get_details($publicKey) : false;
        $signatureAlgorithm = ($details && ($details['type'] ?? null) === OPENSSL_KEYTYPE_EC)
            ? self::sequence(self::oid(self::OID_ECDSA_SHA256))
            : self::sequence(self::oid(self::OID_RSA) . self::tlv(0x05, ''));
        $digestAlgorithm = self::sequence(self::oid(self::OID_SHA256) . self::tlv(0x05, ''));

        $signerInfo = self::sequence(
            self::integer(1)
            . self::sequence($issuer . $serial)
            . $digestAlgorithm
            . "\xA0" . self::length(strlen($signedAttributes) - self::headerLength($signedAttributes)) . substr($signedAttributes, self::headerLength($signedAttributes))
            . $signatureAlgorithm
            . self::tlv(0x04, $signature)
        );

        $certificates = $signerCertificate;
        foreach ($extraCertificates as $certificate) {
            if ($certificate !== $signerCertificate) {
                $certificates .= $certificate;
            }
        }

        $signedData = self::sequence(
            self::integer(1)
            . self::set([$digestAlgorithm])
            . self::sequence(self::oid(self::OID_DATA))
            . "\xA0" . self::length(strlen($certificates)) . $certificates
            . self::set([$signerInfo])
        );

        return self::sequence(self::oid(self::OID_SIGNED_DATA) . "\xA0" . self::length(strlen($signedData)) . $signedData);
    }

    public static function parsePkcs7(string $der): array
    {
        $contentInfo = self::children($der);
        if (count($contentInfo) < 2 || $contentInfo[0]['raw'] !== self::oid(self::OID_SIGNED_DATA)) {
            throw new \RuntimeException('Mobil imza yanıtı PKCS#7 SignedData değil.');
        }
        $signedData = self::children(self::children($contentInfo[1]['raw'])[0]['raw']);
        $certificates = [];
        $signerInfos = null;
        foreach ($signedData as $node) {
            if ($node['tag'] === 0xA0) {
                foreach (self::children($node['raw']) as $certificate) {
                    $certificates[] = $certificate['raw'];
                }
            } elseif ($node['tag'] === 0x31) {
                $signerInfos = $node;
            }
        }
        $signerInfoList = $signerInfos ? self::children($signerInfos['raw']) : [];
        if ($signerInfoList === [] || $certificates === []) {
            throw new \RuntimeException('Mobil imza yanıtında imza veya sertifika bulunamadı.');
        }
        $signerInfo = self::children($signerInfoList[0]['raw']);
        $signature = null;
        $signerIssuerSerial = $signerInfo[1]['raw'] ?? '';
        foreach (array_reverse($signerInfo) as $node) {
            if ($node['tag'] === 0x04) {
                $signature = $node['value'];
                break;
            }
        }
        if ($signature === null || $signature === '') {
            throw new \RuntimeException('Mobil imza değeri okunamadı.');
        }

        $signerCertificate = $certificates[0];
        foreach ($certificates as $certificate) {
            $tbs = self::children(self::children($certificate)[0]['raw']);
            $offset = ($tbs[0]['tag'] === 0xA0) ? 1 : 0;
            if (self::sequence($tbs[$offset + 2]['raw'] . $tbs[$offset]['raw']) === $signerIssuerSerial) {
                $signerCertificate = $certificate;
                break;
            }
        }

        return [
            'signature' => $signature,
            'certificate' => $signerCertificate,
            'certificates' => $certificates,
            'has_signed_attributes' => (bool) array_filter($signerInfo, static fn(array $node): bool => $node['tag'] === 0xA0),
        ];
    }

    public static function pem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";
    }

    public static function der(string $pem): string
    {
        $body = preg_replace('/-----[^-]+-----|\s+/', '', $pem);
        $der = base64_decode((string) $body, true);
        if ($der === false || $der === '') {
            throw new \RuntimeException('Sertifika çözülemedi.');
        }
        return $der;
    }

    public static function children(string $der): array
    {
        $header = self::headerLength($der);
        $end = strlen($der);
        $position = $header;
        $nodes = [];
        while ($position < $end) {
            $node = self::read($der, $position);
            $nodes[] = $node;
            $position += strlen($node['raw']);
        }
        return $nodes;
    }

    public static function read(string $der, int $position = 0): array
    {
        if ($position + 2 > strlen($der)) {
            throw new \RuntimeException('DER verisi eksik.');
        }
        $tag = ord($der[$position]);
        $first = ord($der[$position + 1]);
        $headerLength = 2;
        if ($first & 0x80) {
            $count = $first & 0x7f;
            if ($count < 1 || $count > 4) {
                throw new \RuntimeException('DER uzunluğu desteklenmiyor.');
            }
            $length = 0;
            for ($i = 0; $i < $count; $i++) {
                $length = ($length << 8) | ord($der[$position + 2 + $i]);
            }
            $headerLength += $count;
        } else {
            $length = $first;
        }
        if ($position + $headerLength + $length > strlen($der)) {
            throw new \RuntimeException('DER verisi kesik.');
        }
        return [
            'tag' => $tag,
            'value' => substr($der, $position + $headerLength, $length),
            'raw' => substr($der, $position, $headerLength + $length),
        ];
    }

    private static function headerLength(string $der): int
    {
        $node = self::read($der);
        return strlen($node['raw']) - strlen($node['value']);
    }

    private static function tlv(int $tag, string $value): string
    {
        return chr($tag) . self::length(strlen($value)) . $value;
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function sequence(string $content): string
    {
        return self::tlv(0x30, $content);
    }

    private static function set(array $elements): string
    {
        sort($elements, SORT_STRING);
        return self::tlv(0x31, implode('', $elements));
    }

    private static function integer(int $value): string
    {
        $bytes = ltrim(pack('J', $value), "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00" . $bytes;
        }
        return self::tlv(0x02, $bytes);
    }

    private static function oid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        $body = chr(40 * $parts[0] + $parts[1]);
        foreach (array_slice($parts, 2) as $part) {
            $chunk = chr($part & 0x7f);
            $part >>= 7;
            while ($part > 0) {
                $chunk = chr(0x80 | ($part & 0x7f)) . $chunk;
                $part >>= 7;
            }
            $body .= $chunk;
        }
        return self::tlv(0x06, $body);
    }
}
