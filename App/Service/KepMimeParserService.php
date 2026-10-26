<?php

namespace App\Service;

class KepMimeParserService
{
    public function parse(string $raw): array
    {
        $result = ['html' => '', 'text' => '', 'ekler' => [], 'headers' => []];
        $raw = ltrim($raw);

        if (str_starts_with($raw, "PK\x03\x04")) {
            return $this->parseZip($raw, $result);
        }

        $this->walk($raw, $result, true);
        return $result;
    }

    private function parseZip(string $raw, array $result): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kepzip_');
        file_put_contents($tmp, $raw);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = basename((string) $zip->getNameIndex($i));
                if ($name === '' || str_ends_with((string) $zip->getNameIndex($i), '/')) {
                    continue;
                }
                $content = (string) $zip->getFromIndex($i);
                $result['ekler'][] = ['name' => $name, 'mime' => 'application/octet-stream', 'content' => $content];
            }
            $zip->close();
        }
        @unlink($tmp);
        return $result;
    }

    private function splitHeaders(string $part): array
    {
        $part = ltrim($part, "\r\n");
        $pos = strpos($part, "\r\n\r\n");
        $sepLen = 4;
        $posLf = strpos($part, "\n\n");
        if ($pos === false || ($posLf !== false && $posLf < $pos)) {
            $pos = $posLf;
            $sepLen = 2;
        }
        if ($pos === false) {
            return [[], $part];
        }

        $headerBlock = preg_replace('/\r?\n[ \t]+/', ' ', substr($part, 0, $pos));
        $headers = [];
        foreach (preg_split('/\r?\n/', (string) $headerBlock) as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $headers[strtolower(trim(substr($line, 0, $colon)))] = trim(substr($line, $colon + 1));
        }

        return [$headers, substr($part, $pos + $sepLen)];
    }

    private function headerParam(string $header, string $param): ?string
    {
        if (preg_match('/\b' . preg_quote($param, '/') . '\*?=(?:"([^"]*)"|([^;\s]+))/i', $header, $m)) {
            return $m[1] !== '' ? $m[1] : ($m[2] ?? null);
        }
        return null;
    }

    private function decodeHeaderValue(string $value): string
    {
        if (str_starts_with($value, "UTF-8''")) {
            return rawurldecode(substr($value, 7));
        }
        $decoded = function_exists('iconv_mime_decode') ? @iconv_mime_decode($value, 0, 'UTF-8') : false;
        return $decoded !== false ? $decoded : $value;
    }

    private function decodeBody(string $body, string $encoding, string $charset): string
    {
        $encoding = strtolower(trim($encoding));
        if ($encoding === 'base64') {
            $body = (string) base64_decode(preg_replace('/\s+/', '', $body), false);
        } elseif ($encoding === 'quoted-printable') {
            $body = quoted_printable_decode($body);
        }

        $charset = strtoupper(trim($charset));
        if ($charset !== '' && $charset !== 'UTF-8' && $charset !== 'US-ASCII') {
            $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
            if ($converted !== false) {
                $body = $converted;
            }
        }
        return $body;
    }

    private function walk(string $raw, array &$result, bool $isRoot = false): void
    {
        [$headers, $body] = $this->splitHeaders($raw);
        if ($isRoot) {
            $result['headers'] = $headers;
        }

        $contentType = $headers['content-type'] ?? 'text/plain';
        $type = strtolower(trim(explode(';', $contentType)[0]));
        $encoding = $headers['content-transfer-encoding'] ?? '7bit';
        $disposition = $headers['content-disposition'] ?? '';

        if (str_starts_with($type, 'multipart/')) {
            $boundary = $this->headerParam($contentType, 'boundary');
            if ($boundary === null || $boundary === '') {
                return;
            }
            $segments = preg_split('/\r?\n?--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*\r?\n?/', $body);
            foreach ($segments as $idx => $segment) {
                if ($idx === 0 || trim($segment) === '') {
                    continue;
                }
                $this->walk($segment, $result);
            }
            return;
        }

        if ($type === 'message/rfc822') {
            $this->walk($this->decodeBody($body, $encoding, ''), $result);
            return;
        }

        if ($type === 'application/pkcs7-signature' || $type === 'application/x-pkcs7-signature') {
            return;
        }

        $filename = $this->headerParam($disposition, 'filename') ?? $this->headerParam($contentType, 'name');
        $isAttachment = $filename !== null || stripos($disposition, 'attachment') === 0;

        if (!$isAttachment && ($type === 'text/html' || $type === 'text/plain')) {
            $text = $this->decodeBody($body, $encoding, (string) $this->headerParam($contentType, 'charset'));
            $key = $type === 'text/html' ? 'html' : 'text';
            $result[$key] .= ($result[$key] !== '' ? "\n" : '') . $text;
            return;
        }

        if ($type === 'application/pkcs7-mime' || $type === 'application/x-pkcs7-mime') {
            $this->extractOpaqueSigned($this->decodeBody($body, $encoding, ''), $result);
            return;
        }

        $content = $this->decodeBody($body, $encoding, '');
        if ($content === '') {
            return;
        }
        $name = $filename !== null ? $this->decodeHeaderValue($filename) : 'ek_' . (count($result['ekler']) + 1);
        $result['ekler'][] = ['name' => $name, 'mime' => $type, 'content' => $content];
    }

    private function extractOpaqueSigned(string $der, array &$result): void
    {
        $in = tempnam(sys_get_temp_dir(), 'kepp7_');
        $out = tempnam(sys_get_temp_dir(), 'kepp7o_');
        file_put_contents($in, "-----BEGIN PKCS7-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PKCS7-----\n");
        if (@openssl_pkcs7_verify($in, PKCS7_NOVERIFY | PKCS7_NOSIGS, null, [], null, $out) !== -1) {
            $inner = (string) file_get_contents($out);
            if ($inner !== '') {
                $this->walk($inner, $result);
            }
        }
        @unlink($in);
        @unlink($out);
    }
}
