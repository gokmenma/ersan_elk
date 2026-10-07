<?php
namespace App\Service;

final class InvoiceValidationService
{
    public static function codes(string $name): array
    {
        static $lists = null;
        if ($lists === null) {
            $xml = simplexml_load_file(dirname(__DIR__, 2) . '/resources/efatura/schematron/UBL-TR_Codelist.xml');
            $xml->registerXPathNamespace('sch', 'http://purl.oclc.org/dsdl/schematron');
            $lists = [];
            foreach ($xml->xpath('//sch:let') as $node) $lists[(string)$node['name']] = array_values(array_filter(explode(',', trim((string)$node['value'], "'"))));
        }
        return $lists[$name] ?? [];
    }

    public function validateDraft(array $header, array $lines): void
    {
        $profile = $header['fatura_profili'] ?? '';
        $type = $header['fatura_tipi'] ?? '';
        $document = $header['belge_turu'] ?? '';
        if (!in_array($type, ['SATIS','IADE','TEVKIFAT','ISTISNA'], true) || !in_array($profile, $document === 'EARSIV' ? ['EARSIVFATURA'] : ['TEMELFATURA','TICARIFATURA'], true) || !in_array($document, ['EFATURA','EARSIV'], true)) throw new \InvalidArgumentException('Bu belge türü, profil veya fatura tipi henüz desteklenmiyor.');
        if (!empty($header['seri_no']) && !preg_match('/^[A-Z0-9]{3}$/D', strtoupper(trim((string)$header['seri_no'])))) throw new \InvalidArgumentException('Fatura serisi üç harf/rakamdan oluşmalıdır.');
        if (!preg_match('/^\d{10,11}$/D', $header['alici_vkn_tckn'] ?? '') || trim($header['alici_unvan'] ?? '') === '') throw new \InvalidArgumentException('Geçerli alıcı VKN/TCKN ve unvan gereklidir.');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $header['fatura_tarihi'] ?? '');
        if (!$date || $date->format('Y-m-d') !== ($header['fatura_tarihi'] ?? '')) throw new \InvalidArgumentException('Fatura tarihi geçersiz.');
        if ($type === 'IADE') {
            if (!in_array($profile, ['TEMELFATURA', 'EARSIVFATURA'], true)) throw new \InvalidArgumentException('İade faturası temel veya e-Arşiv profili ile düzenlenmelidir.');
            $returnDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $header['iade_fatura_tarihi'] ?? '');
            if (trim($header['iade_fatura_no'] ?? '') === '' || !$returnDate || $returnDate->format('Y-m-d') !== $header['iade_fatura_tarihi'] || $returnDate > $date) throw new \InvalidArgumentException('İade edilen fatura numarası ve geçerli tarihi gereklidir.');
        }
        if (!in_array($header['para_birimi'] ?? 'TRY', self::codes('CurrencyCodeList'), true)) throw new \InvalidArgumentException('Geçersiz para birimi.');
        if (($header['para_birimi'] ?? 'TRY') !== 'TRY' && bccomp(InvoiceCalculationService::decimal($header['doviz_kuru'] ?? '0'), '0', 4) <= 0) throw new \InvalidArgumentException('Döviz kuru sıfırdan büyük olmalıdır.');
        $hasWithholding = false;
        foreach ($lines as $line) {
            if (!in_array($line['birim'] ?? 'C62', self::codes('UnitCodeList'), true)) throw new \InvalidArgumentException('Geçersiz ölçü birimi.');
            $withholding = (float)($line['tevkifat_orani'] ?? 0);
            $vat = (float)($line['kdv_orani'] ?? 20);
            if ($withholding > 0) {
                $hasWithholding = true;
                $code = (string)($line['tevkifat_kodu'] ?? '');
                if ($type !== 'TEVKIFAT' || !in_array($code, self::codes('WithholdingTaxType'), true) || !in_array($code . (string)(int)$withholding, self::codes('WithholdingTaxTypeWithPercent'), true) || $withholding !== (float)(int)$withholding || $vat <= 0) throw new \InvalidArgumentException('Tevkifat kodu/oranı ve fatura tipi uyumsuz.');
            }
            if ($vat === 0.0) {
                $codes = $type === 'ISTISNA' ? array_values(array_diff(self::codes('istisnaTaxExemptionReasonCodeType'), self::codes('YatirimTesvikTaxExemptionReasonCodeType'))) : ['351'];
                if (!in_array((string)($line['istisna_kodu'] ?? ''), $codes, true) || trim($line['istisna_aciklama'] ?? '') === '') throw new \InvalidArgumentException('Sıfır KDV için geçerli istisna/işlem kodu ve açıklaması gereklidir.');
            }
            if ($type === 'ISTISNA' && $vat !== 0.0) throw new \InvalidArgumentException('İstisna faturası kalemlerinin KDV oranı sıfır olmalıdır.');
        }
        if ($type === 'TEVKIFAT' && !$hasWithholding) throw new \InvalidArgumentException('Tevkifat faturasında tevkifatlı kalem gereklidir.');
        (new InvoiceCalculationService())->calculate($lines);
    }

    public function validateXml(string $xml, array $header, array $lines): void
    {
        $this->validateDraft($header, $lines);
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml) || !$dom->loadXML($xml, LIBXML_NONET) ) {
                $errors = array_map(static fn($e) => trim($e->message), libxml_get_errors());
                throw new \InvalidArgumentException('UBL XSD doğrulaması başarısız: ' . implode(' ', array_slice($errors, 0, 3)));
            }
            // EDM signs the document. Official XSD requires a child in ExtensionContent;
            // validate the unsigned body with a transient extension only in this DOM.
            $extension = $dom->getElementsByTagNameNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ExtensionContent')->item(0);
            if ($extension && !$extension->hasChildNodes()) $extension->appendChild($dom->createElementNS('urn:ersan:validation', 'validation:Unsigned'));
            if (!$dom->schemaValidate(dirname(__DIR__, 2) . '/resources/efatura/xsd/maindoc/UBL-Invoice-2.1.xsd')) {
                $errors = array_map(static fn($e) => trim($e->message), libxml_get_errors());
                throw new \InvalidArgumentException('UBL XSD doğrulaması başarısız: ' . implode(' ', array_slice($errors, 0, 3)));
            }
            (new UblBusinessRules())->validate($dom, $header['belge_turu']);
            $source = (new UblReaderService())->read($xml, 'GIDEN');
            $calculated = (new InvoiceCalculationService())->calculate($lines)['header'];
            foreach (['kdv_matrahi','hesaplanan_kdv','tevkifat_tutari','odenecek_tutar'] as $key) {
                if (bccomp((string)$header[$key], $calculated[$key], 2) !== 0 || bccomp($source['header'][$key], $calculated[$key], 2) !== 0) throw new \InvalidArgumentException('XML ve kayıt tutarları uyuşmuyor: ' . $key);
            }
            if (!preg_match('/^[A-Z0-9]{3}20\d{2}\d{9}$/D', $header['fatura_no'] ?? '') || substr($header['fatura_no'], 3, 4) !== substr($header['fatura_tarihi'], 0, 4)) throw new \InvalidArgumentException('Fatura numarası biçimi geçersiz.');
            if (!in_array($header['para_birimi'] ?? 'TRY', self::codes('CurrencyCodeList'), true)) throw new \InvalidArgumentException('Para birimi geçersiz.');
            foreach ($lines as $line) if (!in_array($line['birim'] ?? 'C62', self::codes('UnitCodeList'), true)) throw new \InvalidArgumentException('Ölçü birimi kodu geçersiz.');
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
}
