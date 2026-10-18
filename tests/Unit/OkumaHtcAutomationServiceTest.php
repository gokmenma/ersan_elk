<?php

use App\Service\OkumaHtcAutomationService;

test('HTÇ otomasyonu yalnız pazar tarihini kabul eder', function () {
    expect(OkumaHtcAutomationService::isSunday('2026-10-18'))->toBeTrue()
        ->and(OkumaHtcAutomationService::isSunday('2026-10-19'))->toBeFalse()
        ->and(OkumaHtcAutomationService::isSunday('hatalı'))->toBeFalse();
});

test('geliştirme öncesindeki tek günlük HT kaydı dönüştürülebilir', function () {
    $result = OkumaHtcAutomationService::classifyRecords([[
        'id' => 1,
        'izin_tipi_id' => 650,
        'kisa_kod' => 'HT',
        'baslangic_tarihi' => '2026-10-18',
        'bitis_tarihi' => '2026-10-18',
        'kaynak' => null,
        'manuel_kilit' => 0,
    ]], '2026-10-18', 2862);

    expect($result['convertible_ht'])->toHaveCount(1)
        ->and($result['blocking'])->toBeNull();
});

test('otomasyondan sonra manuel seçilen HT dahil tüm manuel kodlar korunur', function () {
    $manualHt = OkumaHtcAutomationService::classifyRecords([[
        'id' => 2,
        'izin_tipi_id' => 650,
        'kisa_kod' => 'HT',
        'baslangic_tarihi' => '2026-10-18',
        'bitis_tarihi' => '2026-10-18',
        'kaynak' => 'manuel',
        'manuel_kilit' => 1,
    ]], '2026-10-18', 2862);

    $otherCode = OkumaHtcAutomationService::classifyRecords([[
        'id' => 3,
        'izin_tipi_id' => 50,
        'kisa_kod' => 'RP',
        'baslangic_tarihi' => '2026-10-18',
        'bitis_tarihi' => '2026-10-18',
        'kaynak' => 'manuel',
        'manuel_kilit' => 1,
    ]], '2026-10-18', 2862);

    expect($manualHt['blocking']['id'])->toBe(2)
        ->and($otherCode['blocking']['id'])->toBe(3);
});

test('mevcut otomatik HTÇ mükerrer kayıt yerine güncellenir', function () {
    $result = OkumaHtcAutomationService::classifyRecords([[
        'id' => 4,
        'izin_tipi_id' => 2862,
        'kisa_kod' => 'HTÇ',
        'baslangic_tarihi' => '2026-10-18',
        'bitis_tarihi' => '2026-10-18',
        'kaynak' => 'otomatik',
        'manuel_kilit' => 0,
    ]], '2026-10-18', 2862);

    expect($result['automatic_htc']['id'])->toBe(4)
        ->and($result['blocking'])->toBeNull();
});

test('operasyon türleri kendi alt limitleriyle bağımsız değerlendirilir', function () {
    $minimums = ['okuma' => 10, 'kacak' => 2, 'sokme_takma' => 10, 'muhurleme' => 10];
    expect(OkumaHtcAutomationService::qualifyingTotals(['kacak' => 2], $minimums))->toBe(['kacak' => 2.0])
        ->and(OkumaHtcAutomationService::qualifyingTotals(['kacak' => 1], $minimums))->toBe([])
        ->and(OkumaHtcAutomationService::qualifyingTotals(['sokme_takma' => 10], $minimums))->toBe(['sokme_takma' => 10.0])
        ->and(OkumaHtcAutomationService::qualifyingTotals(['muhurleme' => 9], $minimums))->toBe([]);
});
