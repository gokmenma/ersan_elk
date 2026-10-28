<?php

use App\Service\OkumaHtcAutomationService;
use App\Service\PuantajOzelGunOtomasyonService;

$minimums = ['okuma' => 10, 'kacak' => 2, 'sokme_takma' => 10, 'muhurleme' => 10];
$keywords = ['okuma', 'kaçak', 'sökme', 'mühür'];

test('iş sayılan departman anahtar kelimeyle Türkçe karakter duyarlı eşleşir', function () use ($keywords) {
    expect(OkumaHtcAutomationService::isCountedDepartment('Endeks Okuma', $keywords))->toBeTrue()
        ->and(OkumaHtcAutomationService::isCountedDepartment('KAÇAK KONTROL', $keywords))->toBeTrue()
        ->and(OkumaHtcAutomationService::isCountedDepartment('Kesme Açma,Mühürleme', $keywords))->toBeTrue()
        ->and(OkumaHtcAutomationService::isCountedDepartment('Kesme Açma', $keywords))->toBeFalse()
        ->and(OkumaHtcAutomationService::isCountedDepartment('BÜRO', $keywords))->toBeFalse();
});

test('iş personeli görev kaydı ve iş eşiğini birlikte sağlamalıdır', function () use ($minimums) {
    $withTask = OkumaHtcAutomationService::decide(['okuma' => 25], $minimums, true, true, true);
    $noTask = OkumaHtcAutomationService::decide(['okuma' => 25], $minimums, false, true, true);
    $belowLimit = OkumaHtcAutomationService::decide(['okuma' => 3], $minimums, true, true, true);

    expect($withTask['qualified'])->toBeTrue()
        ->and($withTask['basis'])->toBe('is')
        ->and($noTask['qualified'])->toBeFalse()
        ->and($belowLimit['qualified'])->toBeFalse();
});

test('diğer personel yalnız görev kaydıyla hak kazanır', function () use ($minimums) {
    $withTask = OkumaHtcAutomationService::decide([], $minimums, true, false, true);
    $noTask = OkumaHtcAutomationService::decide([], $minimums, false, false, true);

    expect($withTask['qualified'])->toBeTrue()
        ->and($withTask['basis'])->toBe('gorev')
        ->and($noTask['qualified'])->toBeFalse();
});

test('görev zorunluluğu kapalıyken eski iş sayısı kuralı aynen uygulanır', function () use ($minimums) {
    expect(OkumaHtcAutomationService::decide(['kacak' => 2], $minimums, false, true, false)['qualified'])->toBeTrue()
        ->and(OkumaHtcAutomationService::decide([], $minimums, true, false, false)['qualified'])->toBeFalse();
});

test('RTÇ için hafta içi varsayılan X kaydı dönüştürülür, manuel kilitli kayıt korunur', function () {
    $x = ['id' => 5, 'izin_tipi_id' => 1, 'kisa_kod' => 'X', 'baslangic_tarihi' => '2026-10-29',
        'bitis_tarihi' => '2026-10-29', 'kaynak' => null, 'manuel_kilit' => 0];
    $converted = OkumaHtcAutomationService::classifyRecords([$x], '2026-10-29', 2861, ['HT', 'X']);
    $locked = OkumaHtcAutomationService::classifyRecords([array_merge($x, ['manuel_kilit' => 1])], '2026-10-29', 2861, ['HT', 'X']);
    $htcDefault = OkumaHtcAutomationService::classifyRecords([$x], '2026-10-29', 2862);

    expect($converted['convertible_ht'])->toHaveCount(1)
        ->and($locked['blocking'])->not->toBeNull()
        ->and($htcDefault['blocking'])->not->toBeNull();
});

test('arife mesaisi kesim saatinden sonra elle bitirilen veya başlatılan görevde verilir', function () {
    $date = '2026-10-28';
    $manualEnd = ['son_basla' => "$date 08:05:00", 'son_manuel_bitis' => "$date 17:30:00", 'acik' => false];
    $earlyEnd = ['son_basla' => "$date 08:05:00", 'son_manuel_bitis' => "$date 12:40:00", 'acik' => false];
    $lateStart = ['son_basla' => "$date 14:00:00", 'son_manuel_bitis' => null, 'acik' => true];

    expect(PuantajOzelGunOtomasyonService::arifeDecision($manualEnd, $date, '13:00:00', 'sabah'))->toBe('yes')
        ->and(PuantajOzelGunOtomasyonService::arifeDecision($earlyEnd, $date, '13:00:00', 'sabah'))->toBe('no')
        ->and(PuantajOzelGunOtomasyonService::arifeDecision($lateStart, $date, '13:00:00', 'aksam'))->toBe('yes')
        ->and(PuantajOzelGunOtomasyonService::arifeDecision(null, $date, '13:00:00', 'sabah'))->toBe('no');
});

test('23:50 kuralıyla otomatik kapanan arife görevi mesai kazandırmaz, akşam karar bekletilir', function () {
    $date = '2026-10-28';
    $autoClosed = ['son_basla' => "$date 08:00:00", 'son_manuel_bitis' => null, 'acik' => false];
    $open = ['son_basla' => "$date 08:00:00", 'son_manuel_bitis' => null, 'acik' => true];

    expect(PuantajOzelGunOtomasyonService::arifeDecision($autoClosed, $date, '13:00:00', 'sabah'))->toBe('no')
        ->and(PuantajOzelGunOtomasyonService::arifeDecision($open, $date, '13:00:00', 'aksam'))->toBe('pending')
        ->and(PuantajOzelGunOtomasyonService::arifeDecision($open, $date, '13:00:00', 'sabah'))->toBe('no');
});

test('ayar okuyucu varsayılanları ve departman anahtarlarını normalize eder', function () {
    $options = OkumaHtcAutomationService::optionsFromSettings(['ozel_gun_sayili_departmanlar' => ' Okuma , ,KAÇAK', 'htc_gorev_kaydi_zorunlu' => '0']);

    expect($options['counted_departments'])->toBe(['okuma', 'kaçak'])
        ->and($options['require_task'])->toBeFalse()
        ->and($options['ozel_gun_enabled'])->toBeTrue()
        ->and($options['minimum_okuma'])->toBe(10);
});
