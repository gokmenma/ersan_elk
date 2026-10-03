<?php
namespace App\Service;

use DOMDocument;
use DOMElement;

class UblGeneratorService
{
    public function generateInvoiceXml(array $invoice, array $supplier, array $lines): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8'); $dom->formatOutput = true;
        $root = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice'); $dom->appendChild($root);
        foreach (['cac' => 'CommonAggregateComponents', 'cbc' => 'CommonBasicComponents', 'ext' => 'CommonExtensionComponents'] as $prefix => $name) $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, 'urn:oasis:names:specification:ubl:schema:xsd:' . $name . '-2');
        $add = static function (DOMElement $parent, string $tag, mixed $value = null, array $attributes = []) use ($dom): DOMElement {
            $node = $dom->createElement($tag);
            if ($value !== null) $node->appendChild($dom->createTextNode((string)$value));
            foreach ($attributes as $key => $attr) $node->setAttribute($key, (string)$attr);
            $parent->appendChild($node); return $node;
        };
        $currency = $invoice['para_birimi'] ?? 'TRY';
        $amount = static fn(DOMElement $node, string $tag, mixed $value) => $add($node, $tag, bcadd((string)$value, '0', 2), ['currencyID' => $currency]);
        // Empty extension is reserved for EDM server signing; no fabricated signature.
        $add($add($add($root, 'ext:UBLExtensions'), 'ext:UBLExtension'), 'ext:ExtensionContent');
        foreach (['UBLVersionID' => '2.1', 'CustomizationID' => 'TR1.2', 'ProfileID' => $invoice['fatura_profili'], 'ID' => $invoice['fatura_no'] ?? '', 'CopyIndicator' => 'false', 'UUID' => $invoice['ettn'], 'IssueDate' => $invoice['fatura_tarihi'], 'IssueTime' => $invoice['duzenleme_saati'] ?? '00:00:00', 'InvoiceTypeCode' => $invoice['fatura_tipi']] as $tag => $value) $add($root, 'cbc:' . $tag, $value);
        foreach (array_filter([$invoice['notlar'] ?? '', $invoice['yaziyla_tutar'] ?? '']) as $note) $add($root, 'cbc:Note', $note);
        $add($root, 'cbc:DocumentCurrencyCode', $currency); $add($root, 'cbc:LineCountNumeric', count($lines));
        if (!empty($invoice['siparis_no'])) {
            $ref = $add($root, 'cac:OrderReference'); $add($ref, 'cbc:ID', $invoice['siparis_no']);
            if (!empty($invoice['siparis_tarihi'])) $add($ref, 'cbc:IssueDate', $invoice['siparis_tarihi']);
        }
        if ($invoice['fatura_tipi'] === 'IADE') {
            $ref = $add($add($root, 'cac:BillingReference'), 'cac:InvoiceDocumentReference');
            $add($ref, 'cbc:ID', $invoice['iade_fatura_no']); $add($ref, 'cbc:IssueDate', $invoice['iade_fatura_tarihi']); $add($ref, 'cbc:DocumentTypeCode', 'IADE');
        }
        if (!empty($invoice['irsaliye_no'])) {
            $ref = $add($root, 'cac:DespatchDocumentReference'); $add($ref, 'cbc:ID', $invoice['irsaliye_no']);
            if (!empty($invoice['irsaliye_tarihi'])) $add($ref, 'cbc:IssueDate', $invoice['irsaliye_tarihi']);
        }
        if (($invoice['belge_turu'] ?? '') === 'EARSIV') {
            $ref = $add($root, 'cac:AdditionalDocumentReference');
            $add($ref, 'cbc:ID', 'ELEKTRONIK'); $add($ref, 'cbc:IssueDate', $invoice['fatura_tarihi']); $add($ref, 'cbc:DocumentTypeCode', 'EREPSENDT');
        }
        $signature = $add($root, 'cac:Signature');
        $add($signature, 'cbc:ID', $supplier['vkn_tckn'], ['schemeID' => 'VKN_TCKN']);
        $signParty = $add($signature, 'cac:SignatoryParty');
        $add($add($signParty, 'cac:PartyIdentification'), 'cbc:ID', $supplier['vkn_tckn'], ['schemeID' => strlen($supplier['vkn_tckn']) === 11 ? 'TCKN' : 'VKN']);
        $signAddress = $add($signParty, 'cac:PostalAddress');
        $add($signAddress, 'cbc:CitySubdivisionName', $supplier['ilce']); $add($signAddress, 'cbc:CityName', $supplier['il']); $add($add($signAddress, 'cac:Country'), 'cbc:Name', $supplier['ulke'] ?? 'Türkiye');
        $add($add($add($signature, 'cac:DigitalSignatureAttachment'), 'cac:ExternalReference'), 'cbc:URI', '#Signature_' . ($invoice['fatura_no'] ?? ''));
        $customer = [];
        foreach (['vkn_tckn','unvan','adres','ilce','il','ulke','vergi_dairesi','eposta','telefon'] as $key) $customer[$key] = $invoice['alici_' . $key] ?? '';
        foreach (['AccountingSupplierParty' => $supplier, 'AccountingCustomerParty' => $customer] as $tag => $data) {
            $party = $add($add($root, 'cac:' . $tag), 'cac:Party');
            if (!empty($data['web'])) $add($party, 'cbc:WebsiteURI', $data['web']);
            $person = strlen($data['vkn_tckn']) === 11;
            $add($add($party, 'cac:PartyIdentification'), 'cbc:ID', $data['vkn_tckn'], ['schemeID' => $person ? 'TCKN' : 'VKN']);
            if (!$person) $add($add($party, 'cac:PartyName'), 'cbc:Name', $data['unvan']);
            $address = $add($party, 'cac:PostalAddress');
            $add($address, 'cbc:StreetName', $data['adres']); $add($address, 'cbc:CitySubdivisionName', $data['ilce']); $add($address, 'cbc:CityName', $data['il']);
            $add($add($address, 'cac:Country'), 'cbc:Name', $data['ulke'] ?: 'Türkiye');
            if (!empty($data['vergi_dairesi'])) $add($add($add($party, 'cac:PartyTaxScheme'), 'cac:TaxScheme'), 'cbc:Name', $data['vergi_dairesi']);
            if (!empty($data['telefon']) || !empty($data['eposta'])) {
                $contact = $add($party, 'cac:Contact');
                if (!empty($data['telefon'])) $add($contact, 'cbc:Telephone', $data['telefon']);
                if (!empty($data['eposta'])) $add($contact, 'cbc:ElectronicMail', $data['eposta']);
            }
            if ($person) {
                $parts = preg_split('/\s+/', trim($data['unvan'])); $family = count($parts) > 1 ? array_pop($parts) : '';
                $node = $add($party, 'cac:Person'); $add($node, 'cbc:FirstName', implode(' ', $parts)); $add($node, 'cbc:FamilyName', $family);
            }
        }
        if (!empty($invoice['vade_tarihi'])) {
            $payment = $add($root, 'cac:PaymentMeans'); $add($payment, 'cbc:PaymentMeansCode', '1'); $add($payment, 'cbc:PaymentDueDate', $invoice['vade_tarihi']);
        }
        if ($currency !== 'TRY') {
            $exchange = $add($root, 'cac:PricingExchangeRate'); $add($exchange, 'cbc:SourceCurrencyCode', $currency); $add($exchange, 'cbc:TargetCurrencyCode', 'TRY'); $add($exchange, 'cbc:CalculationRate', $invoice['doviz_kuru']);
        }
        $subtax = static function(DOMElement $parent, array $line, bool $withholding = false) use ($add, $amount): void {
            $node = $add($parent, 'cac:TaxSubtotal');
            $base = $withholding ? $line['kdv_tutari'] : bcsub(bcsub((string)$line['satir_toplami'], (string)$line['kdv_tutari'], 2), '-' . ($line['tevkifat_tutari'] ?? '0'), 2);
            $amount($node, 'cbc:TaxableAmount', $base); $amount($node, 'cbc:TaxAmount', $line[$withholding ? 'tevkifat_tutari' : 'kdv_tutari']);
            $percent = rtrim(rtrim(bcadd((string)$line[$withholding ? 'tevkifat_orani' : 'kdv_orani'], '0', 2), '0'), '.');
            $add($node, 'cbc:Percent', $percent ?: '0');
            $category = $add($node, 'cac:TaxCategory');
            if (!$withholding && !empty($line['istisna_kodu'])) {
                $add($category, 'cbc:TaxExemptionReasonCode', $line['istisna_kodu']); $add($category, 'cbc:TaxExemptionReason', $line['istisna_aciklama']);
            }
            $scheme = $add($category, 'cac:TaxScheme'); $add($scheme, 'cbc:Name', $withholding ? 'KDV TEVKIFAT' : 'KDV'); $add($scheme, 'cbc:TaxTypeCode', $withholding ? $line['tevkifat_kodu'] : '0015');
        };
        $tax = $add($root, 'cac:TaxTotal'); $amount($tax, 'cbc:TaxAmount', $invoice['hesaplanan_kdv']);
        foreach ($lines as $line) $subtax($tax, $line);
        if (bccomp((string)($invoice['tevkifat_tutari'] ?? '0'), '0', 2) > 0) {
            $tax = $add($root, 'cac:WithholdingTaxTotal'); $amount($tax, 'cbc:TaxAmount', $invoice['tevkifat_tutari']);
            foreach ($lines as $line) if (bccomp((string)$line['tevkifat_tutari'], '0', 2) > 0) $subtax($tax, $line, true);
        }
        $totals = $add($root, 'cac:LegalMonetaryTotal');
        $amount($totals, 'cbc:LineExtensionAmount', $invoice['kdv_matrahi']); $amount($totals, 'cbc:TaxExclusiveAmount', $invoice['kdv_matrahi']);
        $amount($totals, 'cbc:TaxInclusiveAmount', bcadd((string)$invoice['kdv_matrahi'], (string)$invoice['hesaplanan_kdv'], 2));
        // Discounts are represented at line level, already included in line extensions.
        $amount($totals, 'cbc:PayableAmount', $invoice['odenecek_tutar']);
        foreach ($lines as $index => $line) {
            $node = $add($root, 'cac:InvoiceLine'); $add($node, 'cbc:ID', $index + 1); $add($node, 'cbc:InvoicedQuantity', $line['miktar'], ['unitCode' => $line['birim'] ?? 'C62']);
            $base = bcadd(bcsub((string)$line['satir_toplami'], (string)$line['kdv_tutari'], 2), (string)($line['tevkifat_tutari'] ?? '0'), 2);
            $amount($node, 'cbc:LineExtensionAmount', $base);
            if (bccomp((string)$line['iskonto_tutari'], '0', 2) > 0) {
                $discount = $add($node, 'cac:AllowanceCharge'); $add($discount, 'cbc:ChargeIndicator', 'false');
                $add($discount, 'cbc:MultiplierFactorNumeric', bcdiv((string)$line['iskonto_orani'], '100', 6)); $amount($discount, 'cbc:Amount', $line['iskonto_tutari']);
                $amount($discount, 'cbc:BaseAmount', bcadd($base, (string)$line['iskonto_tutari'], 2));
            }
            $tax = $add($node, 'cac:TaxTotal'); $amount($tax, 'cbc:TaxAmount', $line['kdv_tutari']); $subtax($tax, $line);
            if (bccomp((string)($line['tevkifat_tutari'] ?? '0'), '0', 2) > 0) {
                $tax = $add($node, 'cac:WithholdingTaxTotal'); $amount($tax, 'cbc:TaxAmount', $line['tevkifat_tutari']); $subtax($tax, $line, true);
            }
            $item = $add($node, 'cac:Item'); $add($item, 'cbc:Name', $line['urun_hizmet_adi']);
            if (!empty($line['urun_kodu'])) $add($add($item, 'cac:SellersItemIdentification'), 'cbc:ID', $line['urun_kodu']);
            $add($add($node, 'cac:Price'), 'cbc:PriceAmount', $line['birim_fiyat'], ['currencyID' => $currency]);
        }
        return $dom->saveXML();
    }
}
