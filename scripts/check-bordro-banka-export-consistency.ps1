$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$modelPath = Join-Path $root 'App/Model/BordroPersonelModel.php'
$exportPath = Join-Path $root 'views/bordro/excel-banka-export.php'
$reportPath = Join-Path $root 'views/bordro/raporlar/banka-listesi.php'
$jsPath = Join-Path $root 'views/bordro/js/bordro.js'

$model = Get-Content -Raw -Path $modelPath
$export = Get-Content -Raw -Path $exportPath
$report = Get-Content -Raw -Path $reportPath
$js = Get-Content -Raw -Path $jsPath

$failed = @()

function Assert-Contains($file, $text, $pattern, $label) {
    if ($text.IndexOf($pattern, [StringComparison]::Ordinal) -lt 0) {
        $script:failed += "${file}: $label [$pattern]"
    }
}

function Assert-NotContains($file, $text, $pattern, $label) {
    if ($text.IndexOf($pattern, [StringComparison]::Ordinal) -ge 0) {
        $script:failed += "${file}: $label [$pattern]"
    }
}

Assert-Contains 'BordroPersonelModel.php' $model 'public function hesaplaOrtakGosterimDegerleri' 'ortak banka gosterim hesabi korunmali'
Assert-Contains 'BordroPersonelModel.php' $model 'p.iban_numarasi' 'ortak donem sorgusu banka export icin IBAN getirmeli'
Assert-Contains 'BordroPersonelModel.php' $model '$bankayaYatmayacak' 'bankaya yatmayacak personel kurali ortak hesapta olmali'
Assert-Contains 'BordroPersonelModel.php' $model "'Sigortal'" 'Sigortal personel banka toplamindan ortak hesapta dusmeli'
Assert-Contains 'BordroPersonelModel.php' $model "'KUR'" 'KUR personel banka toplamindan ortak hesapta dusmeli'

Assert-Contains 'excel-banka-export.php' $export '$ids = null;' 'banka export liste filtresiyle daralmamali'
Assert-Contains 'excel-banka-export.php' $export 'getPersonellerByDonem($donemId, $idArray)' 'banka export liste ekraniyla ayni personel sorgusunu kullanmali'
Assert-Contains 'excel-banka-export.php' $export 'hesaplaOrtakGosterimDegerleri($p, $donem' 'banka export toplam kartla ayni ortak hesabi kullanmali'
Assert-NotContains 'excel-banka-export.php' $export 'getPersonellerByDonemDetayli($donemId, $idArray)' 'banka export eski veritabani banka_odemesi sorgusuna donmemeli'
Assert-NotContains 'excel-banka-export.php' $export '$toplamBankaOdemesi += (float) ($p->banka_odemesi ?? 0);' 'banka export toplam dogrudan bp.banka_odemesi ile hesaplanmamali'

Assert-Contains 'banka-listesi.php' $report 'hesaplaOrtakGosterimDegerleri($personel, $selectedDonem' 'banka raporu liste/export ile ayni ortak hesabi kullanmali'
Assert-Contains 'banka-listesi.php' $report '$bankaOdemeleri[$personel->id]' 'banka raporu satir ve toplam icin ortak hesap cache kullanmali'

$bankButtonMatch = [regex]::Match($js, '\$\("#btnExportExcelBanka"\)[\s\S]*?\n\s*\}\);')
if (-not $bankButtonMatch.Success) {
    $failed += 'bordro.js: banka export buton handler bulunamadi'
} elseif ($bankButtonMatch.Value.IndexOf('getFilteredIds()', [StringComparison]::Ordinal) -ge 0) {
    $failed += 'bordro.js: banka export DataTables filtresiyle ids eklememeli'
}

if ($failed.Count -gt 0) {
    Write-Host 'Bordro banka export tutarlilik kontrolu BASARISIZ:' -ForegroundColor Red
    $failed | ForEach-Object { Write-Host " - $_" -ForegroundColor Red }
    exit 1
}

Write-Host 'Bordro banka export tutarlilik kontrolu OK.' -ForegroundColor Green
