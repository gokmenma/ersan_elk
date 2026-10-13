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
        self::assertStringContainsString("'kendiAlacakPanel_'", $source);
        self::assertStringContainsString("'resmiAlacakPanel_'", $source);
        self::assertStringContainsString('calculation-toggle', $source);
        self::assertStringContainsString('Resmî + gayriresmî, kesinti öncesi', $source);
        self::assertStringContainsString("number_format(\$bankaDetayDagilimi['brut_banka']", $source);
        self::assertStringContainsString('KESİNTİ ÖNCESİ TOPLAM ALACAĞI', $source);
        self::assertStringContainsString('<i class="bx bx-minus-circle me-2"></i>KESİNTİLER', $source);
        self::assertStringContainsString('col-lg-8', $source);
        self::assertStringContainsString('col-lg-4', $source);
    }
}
