<?php

require_once __DIR__ . '/../Autoloader.php';

use App\Helper\IsbankPosmatikXml;

$sample = <<<XML
<XMLEXBAT>
<Tarih>10/01/2026</Tarih>
<Hesaplar>
<Hesap>
<Tanimlamalar>
<HesapTuru>Mevduat Hesabı</HesapTuru>
<HesapNo>2498640</HesapNo>
<MusteriNo>537514449</MusteriNo>
<SubeKodu>4320</SubeKodu>
<SubeAdi>DÜZCE</SubeAdi>
<DovizTuru>TL </DovizTuru>
<HesapAcilisTarihi>06/09/2025</HesapAcilisTarihi>
<SonHareketTarihi>22/12/2025</SonHareketTarihi>
<Bakiye>3466.05</Bakiye>
</Tanimlamalar>
<Hareketler>
<Hareket>
<Tarih>01/12/2025</Tarih>
<Miktar>200.00</Miktar>
<Bakiye>2950.85</Bakiye>
<Aciklama>X etkinlik ucreti</Aciklama>
<IslTurGrup>FST</IslTurGrup>
<IslTurAcik>FA</IslTurAcik>
<VKN>0000000000</VKN>
<Kaynak>X etkinlik ucreti</Kaynak>
<ISL_Id>251201220330620543</ISL_Id>
</Hareket>
</Hareketler>
</Hesap>
</Hesaplar>
</XMLEXBAT>
XML;

try {
    $parsed = IsbankPosmatikXml::parse($sample);

    if (($parsed['generated_date'] ?? null) !== '2026-01-10') {
        throw new RuntimeException('generated_date beklenmiyor: ' . var_export($parsed['generated_date'] ?? null, true));
    }

    $acc0 = $parsed['accounts'][0] ?? null;
    if (!$acc0 || ($acc0['account_no'] ?? null) !== '2498640') {
        throw new RuntimeException('account_no parse edilemedi');
    }

    $tx0 = $acc0['transactions'][0] ?? null;
    if (!$tx0 || ($tx0['isl_id'] ?? null) !== '251201220330620543') {
        throw new RuntimeException('isl_id parse edilemedi');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
