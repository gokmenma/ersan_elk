$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$modelPath = Join-Path $root 'app/Model/BordroPersonelModel.php'
$docPath = Join-Path $root 'docs/BORDRO_HESAPLAMA_KURALLARI.md'

$model = Get-Content -Raw -Path $modelPath
$doc = Get-Content -Raw -Path $docPath

$checks = @(
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'private function getMaasHesapGunu'; Label = 'maas hesap gunu fonksiyonu korunmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'private function getYemekYardimiGunlukLimit'; Label = 'yemek yardimi limit fonksiyonu korunmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'private function hesaplaMaasaDahilYardimDagilimi'; Label = 'maasa dahil yardim dagilimi fonksiyonu korunmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'public function hesaplaOrtakGosterimDegerleri'; Label = 'liste/detay ortak gosterim fonksiyonu korunmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'public function hesaplaMaas'; Label = 'ana maas hesaplama fonksiyonu korunmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'yemek_yardimi_dahil'; Label = 'yemek yardimi dahil alani hesaplamada bulunmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'es_yardimi_dahil'; Label = 'es yardimi dahil alani hesaplamada bulunmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'getPuantajXGunSayisi'; Label = 'maasa dahil yemekte puantaj X gunu korunmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = '$yemekGunluk = min($yemekGunluk, $yemekLimit);'; Label = 'yemek yardimi gunluk limiti asmamali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = '$yemekTutari = round($yemekGunluk * $calcFiiliGun, 2);'; Label = 'yemek yardimi toplam tutari gunluk x fiili gun olmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = '$asgariYatacak + $roundedIncludedMeal + $hesaplananEsToplam'; Label = 'maasa dahil banka dagilimi asgari + yemek + es kuralini korumali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = '$sodexoOdemesi = 0;'; Label = 'maasa dahil modda otomatik Sodexo sifirlanmali' },
    @{ File = 'BordroPersonelModel.php'; Text = $model; Pattern = 'stripos((string) ($kayit->sgk_yapilan_firma ?? ""), "KUR")'; Label = 'KUR personel banka sifirlama kurali korunmali' },
    @{ File = 'docs/BORDRO_HESAPLAMA_KURALLARI.md'; Text = $doc; Pattern = 'yemekGunluk = min(yemekGunluk, yemekYardimiGunlukLimit)'; Label = 'dokuman yemek limit kuralini anlatmali' },
    @{ File = 'docs/BORDRO_HESAPLAMA_KURALLARI.md'; Text = $doc; Pattern = 'bankaOdemesi = asgariHakedis + yemekYardimiToplam + esYardimiToplam'; Label = 'dokuman banka dagilim kuralini anlatmali' },
    @{ File = 'docs/BORDRO_HESAPLAMA_KURALLARI.md'; Text = $doc; Pattern = 'dagitim_manuel = 1'; Label = 'dokuman manuel dagilim kuralini anlatmali' }
)

$failed = @()

foreach ($check in $checks) {
    if ($check.Text.IndexOf($check.Pattern, [StringComparison]::Ordinal) -lt 0) {
        $failed += "$($check.File): $($check.Label) [$($check.Pattern)]"
    }
}

if ($failed.Count -gt 0) {
    Write-Host 'Bordro hesaplama kurallari kontrolu BASARISIZ:' -ForegroundColor Red
    $failed | ForEach-Object { Write-Host " - $_" -ForegroundColor Red }
    exit 1
}

Write-Host 'Bordro hesaplama kurallari kontrolu OK.' -ForegroundColor Green
