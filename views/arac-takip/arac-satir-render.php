<?php

if (!function_exists('getInitials')) {
    function getInitials($name)
    {
        $name = trim((string) $name);
        if (empty($name)) return '';
        $parts = preg_split('/\s+/', $name);
        $first = mb_substr($parts[0], 0, 1, 'UTF-8');
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1, 'UTF-8') : '';
        return mb_strtoupper($first . $last, 'UTF-8');
    }
}

if (!function_exists('getAvatarColor')) {
    function getAvatarColor($key)
    {
        $colors = [
            '#556ee6', '#34c38f', '#50a5f1', '#f1b44c',
            '#e83e8c', '#6f42c1', '#20c997', '#00b4d8',
            '#3a86ff', '#8338ec', '#ff006e', '#fd7e14'
        ];
        $hash = abs(crc32((string) $key));
        return $colors[$hash % count($colors)];
    }
}

if (!function_exists('getEvrakBadgeStatus')) {
    function getEvrakBadgeStatus(?string $dateStr): array
    {
        if (empty($dateStr) || $dateStr === '0000-00-00') {
            return ['class' => 'evrak-badge-muted', 'date_formatted' => '—', 'kalan_gun' => null];
        }

        try {
            $today = new DateTime('today');
            $targetDate = new DateTime($dateStr);
            $diff = (int) $today->diff($targetDate)->format('%r%a');
            $class = $diff < 0 ? 'evrak-badge-danger' : ($diff <= 30 ? 'evrak-badge-warning' : 'evrak-badge-success');
            return ['class' => $class, 'date_formatted' => $targetDate->format('d.m.y'), 'kalan_gun' => $diff];
        } catch (\Exception $e) {
            return ['class' => 'evrak-badge-muted', 'date_formatted' => '—', 'kalan_gun' => null];
        }
    }
}

if (!function_exists('aracListeSatiriOlustur')) {
    function aracListeSatiriOlustur(object $arac, int $sira): array
    {
        $h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $tipLabels = [
            'binek' => 'Binek', 'kamyonet' => 'Kamyonet', 'kamyon' => 'Kamyon', 'minibus' => 'Minibüs',
            'otobus' => 'Otobüs', 'motosiklet' => 'Motosiklet', 'diger' => 'Diğer', 'ikame' => 'İkame'
        ];
        $yakitLabels = [
            'benzin' => '<span class="badge bg-danger">Benzin</span>',
            'dizel' => '<span class="badge bg-dark">Dizel</span>',
            'lpg' => '<span class="badge bg-info">LPG</span>',
            'elektrik' => '<span class="badge bg-success">Elektrik</span>',
            'hibrit' => '<span class="badge bg-warning text-dark">Hibrit</span>'
        ];

        $id = (int) $arac->id;
        $plaka = $h($arac->plaka);
        $km = $h($arac->guncel_km);
        $zimmetId = (int) ($arac->zimmet_id ?? 0);
        $zimmetli = !empty($arac->zimmetli_personel_id);

        $tip = '<span class="badge bg-light text-dark border">' . $h($tipLabels[$arac->arac_tipi] ?? '-') . '</span>';

        $plakaHtml = '<div class="arac-info-box"><a href="javascript:void(0)" class="plaka-label text-primary arac-duzenle" data-id="' . $id . '">' . $plaka . '</a>';
        if (!empty($arac->ikame_mi)) {
            $plakaHtml .= '<span class="badge bg-warning text-dark ms-1" style="font-size: 9px; vertical-align: middle;">İkame</span>';
        }
        $plakaHtml .= '<span class="model-label">' . $h(($arac->marka ?? '-') . ' ' . ($arac->model ?? '')) . '</span>';
        if (!empty($arac->olusturan_kullanici_adi)) {
            $baslik = 'Ekleyen: ' . $arac->olusturan_kullanici_adi . (!empty($arac->olusturma_tarihi) ? ' • ' . date('d.m.Y H:i', strtotime($arac->olusturma_tarihi)) : '');
            $plakaHtml .= '<span class="d-block text-muted arac-ekleyen-label" style="font-size: 10.5px;" title="' . $h($baslik) . '"><i class="bx bx-user-plus"></i> ' . $h($arac->olusturan_kullanici_adi) . '</span>';
        }
        $plakaHtml .= '</div>';

        if ($zimmetli) {
            $pAdi = (string) ($arac->zimmetli_personel_adi ?? '');
            $pResim = (string) ($arac->zimmetli_personel_resim ?? '');
            $hasImage = $pResim !== '' && file_exists(dirname(__DIR__, 2) . '/' . ltrim($pResim, '/'));
            $avatar = $hasImage
                ? '<img src="' . $h($pResim) . '" class="zimmetli-avatar-img" alt="' . $h($pAdi) . '">'
                : '<div class="zimmetli-avatar-circle" style="background-color: ' . getAvatarColor($arac->zimmetli_personel_id) . ';">' . $h(getInitials($pAdi)) . '</div>';
            $zimmetliHtml = '<div class="zimmetli-personel-container">' . $avatar . '<div class="zimmetli-info">'
                . '<a href="javascript:void(0);" class="zimmetli-name zimmet-iade" data-id="' . $zimmetId . '" data-plaka="' . $plaka . '" title="Zimmeti İade Al">' . $h($pAdi) . '</a>'
                . '<span class="zimmetli-dept">' . $h(($arac->zimmetli_personel_departman ?? '') ?: '—') . '</span></div></div>';

            $zt = (!empty($arac->zimmet_tarihi) && $arac->zimmet_tarihi !== '0000-00-00') ? date('d.m.Y', strtotime($arac->zimmet_tarihi)) : null;
            $zimmetTarihHtml = '<div class="d-flex flex-column align-items-center justify-content-center gap-1">'
                . ($zt
                    ? '<span class="fw-semibold text-dark small" style="font-size: 0.82rem;"><i class="bx bx-calendar me-1 text-primary"></i>' . $zt . '</span>'
                    : '<span class="text-muted small">—</span>')
                . '<button type="button" class="btn btn-sm btn-soft-danger zimmet-iade py-0 px-2 border-0" data-id="' . $zimmetId . '" data-plaka="' . $plaka . '" style="font-size: 0.7rem; height: 20px; line-height: 18px;" title="Zimmeti İade Al"><i class="bx bx-undo me-1"></i>İade Al</button></div>';
        } else {
            $zimmetliHtml = '<div class="text-center text-muted fw-bold" style="font-size: 1.1rem;">—</div>';
            $zimmetTarihHtml = '<div class="d-flex flex-column align-items-center justify-content-center gap-1"><span class="text-muted small">—</span>'
                . '<button type="button" class="btn btn-sm btn-soft-warning zimmet-hizli py-1 px-2 border-0" data-id="' . $id . '" data-plaka="' . $plaka . '" data-km="' . $km . '" style="font-size: 0.72rem;"><i class="bx bx-plus-circle me-1"></i>Zimmet Ver</button></div>';
        }

        if (!empty($arac->serviste_mi)) {
            $durum = '<span class="badge bg-danger">Serviste</span>';
        } else {
            $durum = !empty($arac->aktif_mi) ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>';
        }

        $evrak = static function (?string $tarih, string $baslik, string $etiket) use ($h): string {
            $s = getEvrakBadgeStatus($tarih);
            $tam = $tarih ? date('d.m.Y', strtotime($tarih)) : 'Yok';
            return '<div class="evrak-badge-box ' . $s['class'] . '" title="' . $h($etiket . ' Bitiş: ' . $tam) . '"><span class="evrak-badge-title">' . $baslik . '</span><span class="evrak-badge-date">' . $s['date_formatted'] . '</span></div>';
        };

        $menu = '<div class="dropdown"><button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bx bx-dots-vertical-rounded"></i></button><ul class="dropdown-menu dropdown-menu-end">';
        if (!$zimmetli) {
            $menu .= '<li><a class="dropdown-item text-warning zimmet-hizli" href="javascript:void(0);" data-id="' . $id . '" data-plaka="' . $plaka . '" data-km="' . $km . '"><i class="bx bx-transfer me-2"></i> Zimmet Ver</a></li>';
        } else {
            $menu .= '<li><a class="dropdown-item text-danger zimmet-iade" href="javascript:void(0);" data-id="' . $zimmetId . '" data-plaka="' . $plaka . '"><i class="bx bx-undo me-2"></i> Zimmeti İade Al</a></li>'
                . '<li><a class="dropdown-item text-primary zimmet-form-yazdir" href="javascript:void(0);" data-id="' . $zimmetId . '"><i class="bx bx-printer me-2"></i> Zimmet Formu Yazdır</a></li>'
                . '<li><a class="dropdown-item text-warning zimmet-hizli" href="javascript:void(0);" data-id="' . $id . '" data-plaka="' . $plaka . '" data-km="' . $km . '"><i class="bx bx-transfer me-2"></i> Yeniden Zimmetle</a></li>';
        }
        $menu .= '<li><a class="dropdown-item text-info arac-zimmet-gecmisi" href="javascript:void(0);" data-id="' . $id . '" data-plaka="' . $plaka . '"><i class="bx bx-history me-2"></i> Zimmet Geçmişi</a></li>'
            . '<li><a class="dropdown-item text-secondary arac-islem-gecmisi" href="javascript:void(0);" data-id="' . $id . '" data-plaka="' . $plaka . '"><i class="bx bx-list-check me-2"></i> İşlem Geçmişi</a></li>'
            . '<li><a class="dropdown-item text-primary arac-duzenle" href="javascript:void(0);" data-id="' . $id . '"><i class="bx bx-edit me-2"></i> Düzenle</a></li>'
            . '<li><hr class="dropdown-divider"></li>'
            . '<li><a class="dropdown-item text-danger arac-sil" href="javascript:void(0);" data-id="' . $id . '" data-plaka="' . $plaka . '"><i class="bx bx-trash me-2"></i> Sil</a></li>'
            . '</ul></div>';

        return [
            'sira' => $sira,
            'tip' => $tip,
            'plaka' => $plakaHtml,
            'departmani' => '<span class="small">' . $h($arac->departmani ?: '-') . '</span>',
            'mulkiyet' => '<span class="small">' . $h($arac->mulkiyet ?: '-') . '</span>',
            'zimmetli' => $zimmetliHtml,
            'zimmet_tarihi' => $zimmetTarihHtml,
            'durum' => $durum,
            'yakit' => $yakitLabels[$arac->yakit_tipi] ?? '-',
            'guncel_km' => number_format((float) ($arac->guncel_km ?? 0), 0, ',', '.'),
            'muayene' => $evrak($arac->muayene_bitis_tarihi ?? null, 'MUAYENE', 'Muayene'),
            'sigorta' => $evrak($arac->sigorta_bitis_tarihi ?? null, 'SİGORTA', 'Sigorta'),
            'kasko' => $evrak($arac->kasko_bitis_tarihi ?? null, 'KASKO', 'Kasko'),
            'actions' => $menu,
            'created_by' => (string) ($arac->olusturan_kullanici_adi ?? ''),
            'created_at' => !empty($arac->olusturma_tarihi) ? date('d.m.Y H:i', strtotime($arac->olusturma_tarihi)) : '',
        ];
    }
}
