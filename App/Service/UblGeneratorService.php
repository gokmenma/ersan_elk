<?php
namespace App\Service;

use DOMDocument;
use DOMElement;

class UblGeneratorService
{
    /**
     * GİB UBL-TR 1.2.1 Standart E-Fatura / E-Arşiv XML'i Üretir
     */
    public function generateInvoiceXml(array $invoice, array $supplier, array $lines): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Root Element: Invoice
        $root = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $dom->appendChild($root);

        // UBL Extensions (Placeholder for Signature)
        $exts = $dom->createElement('ext:UBLExtensions');
        $ext = $dom->createElement('ext:UBLExtension');
        $extContent = $dom->createElement('ext:ExtensionContent');
        $ext->appendChild($extContent);
        $exts->appendChild($ext);
        $root->appendChild($exts);

        // Header Meta
        $root->appendChild($dom->createElement('cbc:UBLVersionID', '2.1'));
        $root->appendChild($dom->createElement('cbc:CustomizationID', 'TR1.2'));
        $root->appendChild($dom->createElement('cbc:ProfileID', $invoice['fatura_profili'] ?? 'TICARIFATURA'));
        $root->appendChild($dom->createElement('cbc:ID', $invoice['fatura_no'] ?? ''));
        $root->appendChild($dom->createElement('cbc:CopyIndicator', 'false'));
        $root->appendChild($dom->createElement('cbc:UUID', $invoice['ettn']));
        $root->appendChild($dom->createElement('cbc:IssueDate', $invoice['fatura_tarihi']));
        $root->appendChild($dom->createElement('cbc:IssueTime', $invoice['duzenleme_saati'] ?? date('H:i:s')));
        $root->appendChild($dom->createElement('cbc:InvoiceTypeCode', $invoice['fatura_tipi'] ?? 'SATIS'));

        // Notlar
        if (!empty($invoice['notlar'])) {
            $root->appendChild($dom->createElement('cbc:Note', htmlspecialchars($invoice['notlar'], ENT_XML1, 'UTF-8')));
        }
        if (!empty($invoice['yaziyla_tutar'])) {
            $root->appendChild($dom->createElement('cbc:Note', 'Yalnız: ' . htmlspecialchars($invoice['yaziyla_tutar'], ENT_XML1, 'UTF-8')));
        }

        $currency = $invoice['para_birimi'] ?? 'TRY';
        $root->appendChild($dom->createElement('cbc:DocumentCurrencyCode', $currency));

        // Sipariş Referansı
        if (!empty($invoice['siparis_no'])) {
            $orderRef = $dom->createElement('cac:OrderReference');
            $orderRef->appendChild($dom->createElement('cbc:ID', $invoice['siparis_no']));
            if (!empty($invoice['siparis_tarihi'])) {
                $orderRef->appendChild($dom->createElement('cbc:IssueDate', $invoice['siparis_tarihi']));
            }
            $root->appendChild($orderRef);
        }

        // İrsaliye Referansı
        if (!empty($invoice['irsaliye_no'])) {
            $despatchRef = $dom->createElement('cac:DespatchDocumentReference');
            $despatchRef->appendChild($dom->createElement('cbc:ID', $invoice['irsaliye_no']));
            if (!empty($invoice['irsaliye_tarihi'])) {
                $despatchRef->appendChild($dom->createElement('cbc:IssueDate', $invoice['irsaliye_tarihi']));
            }
            $root->appendChild($despatchRef);
        }

        // 1. Satıcı / Gönderici Firma (AccountingSupplierParty)
        $supplierParty = $dom->createElement('cac:AccountingSupplierParty');
        $party = $dom->createElement('cac:Party');

        $partyId = $dom->createElement('cac:PartyIdentification');
        $idElem = $dom->createElement('cbc:ID', $supplier['vkn_tckn'] ?? '');
        $idElem->setAttribute('schemeID', strlen($supplier['vkn_tckn'] ?? '') === 11 ? 'TCKN' : 'VKN');
        $partyId->appendChild($idElem);
        $party->appendChild($partyId);

        $partyName = $dom->createElement('cac:PartyName');
        $partyName->appendChild($dom->createElement('cbc:Name', htmlspecialchars($supplier['unvan'] ?? '', ENT_XML1, 'UTF-8')));
        $party->appendChild($partyName);

        $postalAddress = $dom->createElement('cac:PostalAddress');
        $postalAddress->appendChild($dom->createElement('cbc:StreetName', htmlspecialchars($supplier['adres'] ?? '', ENT_XML1, 'UTF-8')));
        $postalAddress->appendChild($dom->createElement('cbc:CitySubdivisionName', htmlspecialchars($supplier['ilce'] ?? '', ENT_XML1, 'UTF-8')));
        $postalAddress->appendChild($dom->createElement('cbc:CityName', htmlspecialchars($supplier['il'] ?? '', ENT_XML1, 'UTF-8')));
        $country = $dom->createElement('cac:Country');
        $country->appendChild($dom->createElement('cbc:Name', 'Türkiye'));
        $postalAddress->appendChild($country);
        $party->appendChild($postalAddress);

        if (!empty($supplier['vergi_dairesi'])) {
            $partyTaxScheme = $dom->createElement('cac:PartyTaxScheme');
            $taxScheme = $dom->createElement('cac:TaxScheme');
            $taxScheme->appendChild($dom->createElement('cbc:Name', htmlspecialchars($supplier['vergi_dairesi'], ENT_XML1, 'UTF-8')));
            $partyTaxScheme->appendChild($taxScheme);
            $party->appendChild($partyTaxScheme);
        }

        $supplierParty->appendChild($party);
        $root->appendChild($supplierParty);

        // 2. Alıcı Müşteri (AccountingCustomerParty)
        $customerParty = $dom->createElement('cac:AccountingCustomerParty');
        $cParty = $dom->createElement('cac:Party');

        $cPartyId = $dom->createElement('cac:PartyIdentification');
        $cIdElem = $dom->createElement('cbc:ID', $invoice['alici_vkn_tckn'] ?? '');
        $cIdElem->setAttribute('schemeID', strlen($invoice['alici_vkn_tckn'] ?? '') === 11 ? 'TCKN' : 'VKN');
        $cPartyId->appendChild($cIdElem);
        $cParty->appendChild($cPartyId);

        $cPartyName = $dom->createElement('cac:PartyName');
        $cPartyName->appendChild($dom->createElement('cbc:Name', htmlspecialchars($invoice['alici_unvan'] ?? '', ENT_XML1, 'UTF-8')));
        $cParty->appendChild($cPartyName);

        $cPostalAddress = $dom->createElement('cac:PostalAddress');
        $cPostalAddress->appendChild($dom->createElement('cbc:StreetName', htmlspecialchars($invoice['alici_adres'] ?? '', ENT_XML1, 'UTF-8')));
        $cPostalAddress->appendChild($dom->createElement('cbc:CitySubdivisionName', htmlspecialchars($invoice['alici_ilce'] ?? '', ENT_XML1, 'UTF-8')));
        $cPostalAddress->appendChild($dom->createElement('cbc:CityName', htmlspecialchars($invoice['alici_il'] ?? '', ENT_XML1, 'UTF-8')));
        $cCountry = $dom->createElement('cac:Country');
        $cCountry->appendChild($dom->createElement('cbc:Name', $invoice['alici_ulke'] ?? 'Türkiye'));
        $cPostalAddress->appendChild($cCountry);
        $cParty->appendChild($cPostalAddress);

        if (!empty($invoice['alici_vergi_dairesi'])) {
            $cPartyTaxScheme = $dom->createElement('cac:PartyTaxScheme');
            $cTaxScheme = $dom->createElement('cac:TaxScheme');
            $cTaxScheme->appendChild($dom->createElement('cbc:Name', htmlspecialchars($invoice['alici_vergi_dairesi'], ENT_XML1, 'UTF-8')));
            $cPartyTaxScheme->appendChild($cTaxScheme);
            $cParty->appendChild($cPartyTaxScheme);
        }

        $customerParty->appendChild($cParty);
        $root->appendChild($customerParty);

        // 3. Genel Vergi Toplamları (TaxTotal)
        $taxTotal = $dom->createElement('cac:TaxTotal');
        $taxAmount = $dom->createElement('cbc:TaxAmount', number_format((float)($invoice['hesaplanan_kdv'] ?? 0), 2, '.', ''));
        $taxAmount->setAttribute('currencyID', $currency);
        $taxTotal->appendChild($taxAmount);

        // Vergi Alt Grupları (KDV Oranlarına Göre)
        $kdvGruplari = [];
        foreach ($lines as $line) {
            $oran = (float)($line['kdv_orani'] ?? 20);
            $matrah = (float)($line['satir_toplami'] ?? 0) - (float)($line['kdv_tutari'] ?? 0);
            $tutar = (float)($line['kdv_tutari'] ?? 0);

            if (!isset($kdvGruplari[$oran])) {
                $kdvGruplari[$oran] = ['matrah' => 0.0, 'tutar' => 0.0];
            }
            $kdvGruplari[$oran]['matrah'] += $matrah;
            $kdvGruplari[$oran]['tutar'] += $tutar;
        }

        foreach ($kdvGruplari as $oran => $grup) {
            $taxSubtotal = $dom->createElement('cac:TaxSubtotal');
            $taxableAmount = $dom->createElement('cbc:TaxableAmount', number_format($grup['matrah'], 2, '.', ''));
            $taxableAmount->setAttribute('currencyID', $currency);
            $taxSubtotal->appendChild($taxableAmount);

            $subTaxAmount = $dom->createElement('cbc:TaxAmount', number_format($grup['tutar'], 2, '.', ''));
            $subTaxAmount->setAttribute('currencyID', $currency);
            $taxSubtotal->appendChild($subTaxAmount);

            $taxSubtotal->appendChild($dom->createElement('cbc:Percent', number_format($oran, 2, '.', '')));

            $taxCategory = $dom->createElement('cac:TaxCategory');
            $taxScheme = $dom->createElement('cac:TaxScheme');
            $taxScheme->appendChild($dom->createElement('cbc:Name', 'KDV'));
            $taxScheme->appendChild($dom->createElement('cbc:TaxTypeCode', '0015'));
            $taxCategory->appendChild($taxScheme);
            $taxSubtotal->appendChild($taxCategory);

            $taxTotal->appendChild($taxSubtotal);
        }
        $root->appendChild($taxTotal);

        // 4. Parasal Toplamlar (LegalMonetaryTotal)
        $monetaryTotal = $dom->createElement('cac:LegalMonetaryTotal');
        
        $lineExtAmount = $dom->createElement('cbc:LineExtensionAmount', number_format((float)($invoice['kdv_matrahi'] ?? 0), 2, '.', ''));
        $lineExtAmount->setAttribute('currencyID', $currency);
        $monetaryTotal->appendChild($lineExtAmount);

        $taxExAmount = $dom->createElement('cbc:TaxExclusiveAmount', number_format((float)($invoice['kdv_matrahi'] ?? 0), 2, '.', ''));
        $taxExAmount->setAttribute('currencyID', $currency);
        $monetaryTotal->appendChild($taxExAmount);

        $taxIncAmount = $dom->createElement('cbc:TaxInclusiveAmount', number_format((float)($invoice['odenecek_tutar'] ?? 0), 2, '.', ''));
        $taxIncAmount->setAttribute('currencyID', $currency);
        $monetaryTotal->appendChild($taxIncAmount);

        $payableAmount = $dom->createElement('cbc:PayableAmount', number_format((float)($invoice['odenecek_tutar'] ?? 0), 2, '.', ''));
        $payableAmount->setAttribute('currencyID', $currency);
        $monetaryTotal->appendChild($payableAmount);

        $root->appendChild($monetaryTotal);

        // 5. Satırlar (InvoiceLine)
        $sira = 1;
        foreach ($lines as $line) {
            $invoiceLine = $dom->createElement('cac:InvoiceLine');
            $invoiceLine->appendChild($dom->createElement('cbc:ID', (string)$sira++));

            $invQty = $dom->createElement('cbc:InvoicedQuantity', number_format((float)($line['miktar'] ?? 1), 4, '.', ''));
            $invQty->setAttribute('unitCode', $line['birim'] ?? 'C62');
            $invoiceLine->appendChild($invQty);

            $lineAmount = (float)($line['miktar'] ?? 1) * (float)($line['birim_fiyat'] ?? 0) - (float)($line['iskonto_tutari'] ?? 0);
            $lineExt = $dom->createElement('cbc:LineExtensionAmount', number_format($lineAmount, 2, '.', ''));
            $lineExt->setAttribute('currencyID', $currency);
            $invoiceLine->appendChild($lineExt);

            // Satır KDV TaxTotal
            $lineTaxTotal = $dom->createElement('cac:TaxTotal');
            $lTaxAmount = $dom->createElement('cbc:TaxAmount', number_format((float)($line['kdv_tutari'] ?? 0), 2, '.', ''));
            $lTaxAmount->setAttribute('currencyID', $currency);
            $lineTaxTotal->appendChild($lTaxAmount);

            $lTaxSubtotal = $dom->createElement('cac:TaxSubtotal');
            $lTaxable = $dom->createElement('cbc:TaxableAmount', number_format($lineAmount, 2, '.', ''));
            $lTaxable->setAttribute('currencyID', $currency);
            $lTaxSubtotal->appendChild($lTaxable);
            $lSubTaxAmount = $dom->createElement('cbc:TaxAmount', number_format((float)($line['kdv_tutari'] ?? 0), 2, '.', ''));
            $lSubTaxAmount->setAttribute('currencyID', $currency);
            $lTaxSubtotal->appendChild($lSubTaxAmount);
            $lTaxSubtotal->appendChild($dom->createElement('cbc:Percent', number_format((float)($line['kdv_orani'] ?? 20), 2, '.', '')));

            $lTaxCategory = $dom->createElement('cac:TaxCategory');
            $lTaxScheme = $dom->createElement('cac:TaxScheme');
            $lTaxScheme->appendChild($dom->createElement('cbc:Name', 'KDV'));
            $lTaxScheme->appendChild($dom->createElement('cbc:TaxTypeCode', '0015'));
            $lTaxCategory->appendChild($lTaxScheme);
            $lTaxSubtotal->appendChild($lTaxCategory);
            $lineTaxTotal->appendChild($lTaxSubtotal);
            $invoiceLine->appendChild($lineTaxTotal);

            // Ürün / Hizmet (Item)
            $item = $dom->createElement('cac:Item');
            $item->appendChild($dom->createElement('cbc:Name', htmlspecialchars($line['urun_hizmet_adi'] ?? '', ENT_XML1, 'UTF-8')));
            $invoiceLine->appendChild($item);

            // Fiyat (Price)
            $price = $dom->createElement('cac:Price');
            $priceAmount = $dom->createElement('cbc:PriceAmount', number_format((float)($line['birim_fiyat'] ?? 0), 4, '.', ''));
            $priceAmount->setAttribute('currencyID', $currency);
            $price->appendChild($priceAmount);
            $invoiceLine->appendChild($price);

            $root->appendChild($invoiceLine);
        }

        return $dom->saveXML();
    }
}
