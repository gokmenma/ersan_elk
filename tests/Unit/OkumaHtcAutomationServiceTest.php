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
