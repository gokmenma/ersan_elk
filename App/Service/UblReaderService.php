<?php
namespace App\Service;

use DOMDocument;
use DOMXPath;

final class UblReaderService
{
    /** Embedded supplier pictures stay in the original XML, not in textual notes. */
    public static function compactNotes(string $notes): string
    {
        return preg_replace_callback('/^Variable_Picture:([^\r\n]+)$/m', static function(array $match): string {
            $image = base64_decode(trim($match[1]), true);
            if ($image !== false && (str_starts_with($image, "\xFF\xD8\xFF") || str_starts_with($image, "\x89PNG\r\n\x1a\n"))) {
                return '[Faturaya gömülü görsel özgün XML içinde saklanır.]';
            }
            return $match[0];
        }, $notes);
    }

    public function read(string $xml, string $direction, bool $allowUnnumberedDraft = false): array
    {
        if ($xml === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) throw new \InvalidArgumentException('Fatura XML içeriği boş veya güvenli değil.');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            if (!$dom->loadXML($xml, LIBXML_NONET) || $dom->documentElement?->localName !== 'Invoice' || $dom->documentElement?->namespaceURI !== 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2') throw new \InvalidArgumentException('Geçerli UBL fatura XML’i alınamadı.');
            $xp = new DOMXPath($dom);
            $xp->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
            $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
            $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
            $text = static fn(string $path, ?\DOMNode $node = null): string => trim((string)$xp->evaluate('string(' . $path . ')', $node));
            $decimal = static function (string $value, string $default = '0'): string {
                if ($value === '') return $default;
                if (strlen($value) > 256 || !preg_match('/^-?\d{1,12}(?:\.\d+)?$/D', $value)) throw new \InvalidArgumentException('XML parasal alanı geçersiz.');
                return $value;
            };
            $party = static function(string $name) use ($text): array {
                $path = '/i:Invoice/cac:' . $name . '/cac:Party';
                return ['vkn_tckn' => $text($path . '/cac:PartyIdentification/cbc:ID[@schemeID="VKN" or @schemeID="TCKN"]'),
                    'unvan' => $text($path . '/cac:PartyName/cbc:Name') ?: trim($text($path . '/cac:Person/cbc:FirstName') . ' ' . $text($path . '/cac:Person/cbc:FamilyName')),
                    'adres' => $text($path . '/cac:PostalAddress/cbc:StreetName'), 'il' => $text($path . '/cac:PostalAddress/cbc:CityName'),
                    'ilce' => $text($path . '/cac:PostalAddress/cbc:CitySubdivisionName'), 'ulke' => $text($path . '/cac:PostalAddress/cac:Country/cbc:Name'),
                    'vergi_dairesi' => $text($path . '/cac:PartyTaxScheme/cac:TaxScheme/cbc:Name'),
                    'eposta' => $text($path . '/cac:Contact/cbc:ElectronicMail'), 'telefon' => $text($path . '/cac:Contact/cbc:Telephone')];
            };
            $supplier = $party('AccountingSupplierParty'); $customer = $party('AccountingCustomerParty');
            $other = $direction === 'GELEN' ? $supplier : $customer;
            $profile = $text('/i:Invoice/cbc:ProfileID');
            $uuid = $text('/i:Invoice/cbc:UUID');
            if (!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD', $uuid) || !$other['vkn_tckn'] || !$other['unvan']) throw new \InvalidArgumentException('XML ETTN veya taraf bilgileri eksik.');
            if (!in_array($profile, array_merge(InvoiceValidationService::codes('ProfileIDType'), ['EARSIVFATURA']), true) || !in_array($text('/i:Invoice/cbc:InvoiceTypeCode'), InvoiceValidationService::codes('InvoiceTypeCodeList'), true) || !in_array($text('/i:Invoice/cbc:DocumentCurrencyCode'), InvoiceValidationService::codes('CurrencyCodeList'), true)) throw new \InvalidArgumentException('XML profil, fatura tipi veya para birimi kodu geçersiz.');
            $number = $text('/i:Invoice/cbc:ID');
            if (!(($allowUnnumberedDraft && $direction === 'GIDEN') && $number === '') && !preg_match('/^[A-Z0-9]{3}20\d{2}\d{9}$/D', $number)) {
                throw new \InvalidArgumentException('XML fatura numarası geçersiz.');
            }
            $taxableTotal = '0.00';
            $taxableNodes = $xp->query('/i:Invoice/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount');
            foreach ($taxableNodes as $taxableNode) $taxableTotal = bcadd($taxableTotal, $decimal($taxableNode->textContent), 2);
            if ($taxableNodes->length === 0) $taxableTotal = $decimal($text('/i:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'));
            $header = ['yon' => $direction, 'ettn' => $uuid, 'fatura_no' => $number !== '' ? $number : null,
                'fatura_profili' => $profile, 'belge_turu' => $profile === 'EARSIVFATURA' ? 'EARSIV' : 'EFATURA',
                'fatura_tipi' => $text('/i:Invoice/cbc:InvoiceTypeCode'), 'fatura_tarihi' => $text('/i:Invoice/cbc:IssueDate'),
                'duzenleme_saati' => substr($text('/i:Invoice/cbc:IssueTime') ?: '00:00:00', 0, 8),
                'para_birimi' => $text('/i:Invoice/cbc:DocumentCurrencyCode'), 'doviz_kuru' => $decimal($text('/i:Invoice/cac:PricingExchangeRate/cbc:CalculationRate'), '1'),
                'kdv_matrahi' => $taxableTotal,
                'hesaplanan_kdv' => $decimal($text('/i:Invoice/cac:TaxTotal/cbc:TaxAmount')),
                'tevkifat_tutari' => $decimal($text('/i:Invoice/cac:WithholdingTaxTotal/cbc:TaxAmount')),
                'odenecek_tutar' => $decimal($text('/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount')),
                'iskonto_toplami' => $decimal($text('/i:Invoice/cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount')),
                'satir_toplami' => $decimal($text('/i:Invoice/cac:LegalMonetaryTotal/cbc:LineExtensionAmount')),
                'iade_fatura_no' => $text('/i:Invoice/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID') ?: null,
                'iade_fatura_tarihi' => $text('/i:Invoice/cac:BillingReference/cac:InvoiceDocumentReference/cbc:IssueDate') ?: null,
                'kaynak_xml' => $xml, 'notlar' => self::compactNotes(implode("\n", array_map(static fn($node) => $node->textContent, iterator_to_array($xp->query('/i:Invoice/cbc:Note')))))];
            $headerKdvSubtotals = [];
            foreach ($xp->query('/i:Invoice/cac:TaxTotal/cac:TaxSubtotal') as $tNode) {
                $taxCode = $text('cac:TaxCategory/cac:TaxScheme/cbc:TaxTypeCode', $tNode);
                $taxName = $text('cac:TaxCategory/cac:TaxScheme/cbc:Name', $tNode);
                $percent = $decimal($text('cbc:Percent', $tNode));
                $taxable = $decimal($text('cbc:TaxableAmount', $tNode));
                $taxAmt = $decimal($text('cbc:TaxAmount', $tNode));

                if ($taxCode === '0015' || strcasecmp($taxName, 'KDV') === 0 || ($taxCode === '' && $taxName === '')) {
                    $headerKdvSubtotals[] = [
                        'percent' => $percent,
                        'taxable' => $taxable,
                        'tax_amount' => $taxAmt
                    ];
                }
            }

            $lines = [];
            foreach ($xp->query('/i:Invoice/cac:InvoiceLine') as $node) {
                $base = $decimal($text('cbc:LineExtensionAmount', $node));
                $vat = $decimal($text('cac:TaxTotal/cbc:TaxAmount', $node));
                $withheld = $decimal($text('cac:WithholdingTaxTotal/cbc:TaxAmount', $node));
                $lineNotes = array_map(static fn($n) => trim($n->textContent), iterator_to_array($xp->query('cbc:Note', $node)));
                $lineNote = implode(' ', array_filter($lineNotes));
                $rawName = trim($text('cac:Item/cbc:Name', $node) ?: '');
                $rawDesc = trim($text('cac:Item/cbc:Description', $node) ?: '');
                $rawKeyword = trim($text('cac:Item/cbc:Keyword', $node) ?: '');
                $itemCode = trim($text('cac:Item/cac:SellersItemIdentification/cbc:ID', $node) ?: $text('cac:Item/cac:BuyersItemIdentification/cbc:ID', $node) ?: $text('cac:Item/cac:StandardItemIdentification/cbc:ID', $node) ?: '');

                if (empty($itemCode) && !empty($rawName) && (!empty($rawDesc) || !empty($lineNote))) {
                    $itemCode = $rawName;
                }

                $baseName = $rawDesc ?: $rawName ?: $rawKeyword;
                if (!empty($itemCode) && $baseName === $itemCode && !empty($rawDesc)) {
                    $baseName = $rawDesc;
                }

                if (!empty($lineNote)) {
                    if (!empty($baseName) && stripos($lineNote, $baseName) === false && stripos($baseName, $lineNote) === false) {
                        $itemName = $baseName . ' - ' . $lineNote;
                    } else {
                        $itemName = $lineNote ?: $baseName;
                    }
                } else {
                    $itemName = $baseName;
                }

                $exemptionReason = $text('cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReason', $node) ?: null;
                $rowDesc = $exemptionReason ? ($exemptionReason . ($lineNote !== '' ? ' | ' . $lineNote : '')) : ($lineNote ?: null);

                // KDV Oranı & Tutarı Çıkarma
                $linePercent = $text('cac:TaxTotal/cac:TaxSubtotal[cac:TaxCategory/cac:TaxScheme/cbc:TaxTypeCode="0015"]/cbc:Percent', $node);
                if ($linePercent === '') {
                    $linePercent = $text('cac:TaxTotal/cac:TaxSubtotal/cbc:Percent', $node);
                }
                if ($linePercent === '') {
                    $linePercent = $text('cac:Item/cac:ClassifiedTaxCategory/cbc:Percent', $node);
                }
                if ($linePercent === '' && !empty($lineNote)) {
                    if (preg_match('/(?:!#KDV:|KDV\s*[:%]?\s*|%\s*)(\d+(?:\.\d+)?)/i', $lineNote, $m)) {
                        $linePercent = $m[1];
                    }
                }
                if ($linePercent === '' && count($headerKdvSubtotals) === 1) {
                    $linePercent = (string)$headerKdvSubtotals[0]['percent'];
                }

                $kdvOrani = $linePercent !== '' ? $decimal($linePercent) : '0.00';
                if ((float)$vat == 0 && (float)$kdvOrani > 0 && (float)$base != 0) {
                    $vat = bcmul((string)$base, bcdiv((string)$kdvOrani, '100', 4), 2);
                }

                $lines[] = ['urun_hizmet_adi' => mb_substr($itemName, 0, 255), 'urun_kodu' => mb_substr($itemCode, 0, 50),
                    'miktar' => $decimal($text('cbc:InvoicedQuantity', $node)), 'birim' => $text('cbc:InvoicedQuantity/@unitCode', $node),
                    'birim_fiyat' => $decimal($text('cac:Price/cbc:PriceAmount', $node)),
                    'iskonto_tutari' => bcadd((string)$xp->evaluate('sum(cac:AllowanceCharge[cbc:ChargeIndicator="false"]/cbc:Amount)', $node), '0', 2),
                    'iskonto_orani' => bcmul($decimal($text('cac:AllowanceCharge[cbc:ChargeIndicator="false"]/cbc:MultiplierFactorNumeric', $node)), '100', 2),
                    'kdv_orani' => $kdvOrani,
                    'kdv_tutari' => $vat, 'tevkifat_tutari' => $withheld,
                    'tevkifat_kodu' => $text('cac:WithholdingTaxTotal/cac:TaxSubtotal/cac:TaxCategory/cac:TaxScheme/cbc:TaxTypeCode', $node) ?: null,
                    'tevkifat_orani' => $decimal($text('cac:WithholdingTaxTotal/cac:TaxSubtotal/cbc:Percent', $node)),
                    'istisna_kodu' => $text('cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode', $node) ?: null,
                    'istisna_aciklama' => $rowDesc,
                    'satir_toplami' => bcsub(bcadd($base, $vat, 2), $withheld, 2)];
            }
            if (!$lines) throw new \InvalidArgumentException('XML’de fatura kalemi bulunamadı.');
            return ['header' => $header, 'lines' => $lines, 'supplier' => $supplier, 'customer' => $customer];
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
}
