<?php

use PHPUnit\Framework\TestCase;

final class BordroEskiDetayGosterimTest extends TestCase
{
    public function testEskiDetayIkiHesabiVePuantajAdediniGosterir(): void
    {
        $source = file_get_contents(__DIR__ . '/../../views/bordro/api.php');

        self::assertIsString($source);
        self::assertStringContainsString('SÖZLEŞME ALACAĞI HESABI', $source);
        self::assertStringContainsString('RESMÎ ALACAĞI HESABI', $source);
        self::assertStringContainsString('KESİNTİ ÖNCESİ RESMÎ ALACAK', $source);
        self::assertStringContainsString('ÖDENECEK NET MAAŞ', $source);
        self::assertStringContainsString('$puantajToplamIslemSayisi . \' Adet</span>\'', $source);
        self::assertStringContainsString("' . \$puantajBaslikDetay . '<span class=\"badge bg-light text-dark fw-normal ms-2\">' . \$calismaGunu . ' Gün", $source);
        self::assertStringContainsString('$puantajIslemSatirlariHtml .= \'<tr class="child-row collapse \' . $collBaseId', $source);
        self::assertStringContainsString('$html .= $puantajIslemSatirlariHtml;', $source);
        self::assertStringNotContainsString('cPuantajBase_', $source);
        self::assertStringContainsString("'kendiAlacakPanel_'", $source);
        self::assertStringContainsString("'resmiAlacakPanel_'", $source);
        self::assertStringContainsString('calculation-toggle', $source);
        self::assertStringContainsString('Resmî + gayriresmî, kesinti öncesi', $source);
        self::assertStringContainsString("number_format(\$bankaDetayDagilimi['brut_banka']", $source);
        self::assertStringContainsString('KESİNTİ ÖNCESİ TOPLAM ALACAĞI', $source);
        self::assertStringContainsString('<i class="bx bx-minus-circle me-2"></i>KESİNTİLER', $source);
        self::assertStringContainsString('col-lg-8', $source);
        self::assertStringContainsString('col-lg-4', $source);
        self::assertStringContainsString('btn-toggle-old-detail-layout', $source);
        self::assertStringContainsString('calculation-panels', $source);
        self::assertStringContainsString('data-panel-key="contract"', $source);
        self::assertStringContainsString('data-panel-key="official"', $source);
        self::assertStringContainsString('tr.footer-row { margin-top:auto; }', $source);
        self::assertStringContainsString('grid-template-columns:repeat(3,minmax(0,1fr)) minmax(220px,.62fr)', $source);
        self::assertStringContainsString('bordro-net-result-column', $source);
        self::assertStringContainsString('background:#f2fbf6; color:#334155', $source);
        self::assertStringContainsString('data-collapse-target="#', $source);
        self::assertStringContainsString('$turParametre->etiket', $source);
        self::assertStringNotContainsString('Sözleşme İçindeki Yemek Payı', $source);
        self::assertStringNotContainsString('Yemek yardımına aktarılan', $source);
        self::assertStringNotContainsString("'resmiYemekDetay_'", $source);
        self::assertStringContainsString("'resmiCalismaDetay_'", $source);
        self::assertStringContainsString('Toplam Fazla Mesai', $source);
        self::assertStringContainsString('Hafta Tatili Çalışması', $source);
        self::assertStringContainsString('tbody > tr.collapse.show { display:table;', $source);
        self::assertStringContainsString('Puantaj Hakedişi', $source);
        self::assertStringContainsString('Dönem puantaj kazancı', $source);
        self::assertStringContainsString('$htcGunModal > 0 && $nominalMaas > 0', $source);
        self::assertStringContainsString("\$personelKesintiOzeti['toplam'] > 0 ? '-' : ''", $source);
        self::assertStringContainsString("\$bankaKesinti['aciklama']", $source);
    }
}
