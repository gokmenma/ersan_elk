<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class YillikIzinHakedisModel extends Model
{
    protected $table = 'personel';

    private PersonelIzinleriModel $izinModel;

    public function __construct()
    {
        parent::__construct($this->table);
        $this->izinModel = new PersonelIzinleriModel();
    }

    private function parseDate($value): ?\DateTime
    {
        if (empty($value) || $value === '0000-00-00') {
            return null;
        }
        try {
            return new \DateTime($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function personelSatirlari(int $firma_id, bool $pasifDahil = false): array
    {
        $aktifKosul = $pasifDahil
            ? ''
            : ' AND aktif_mi = 1 AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi > CURDATE())';
        $stmt = $this->db->prepare(
            "SELECT id, adi_soyadi, departman, gorev, ise_giris_tarihi, dogum_tarihi, isten_cikis_tarihi,
                    (aktif_mi = 0 OR (isten_cikis_tarihi IS NOT NULL AND isten_cikis_tarihi <= CURDATE())) AS pasif
             FROM personel
             WHERE firma_id = ? AND silinme_tarihi IS NULL AND personel_tipi = 'standart'{$aktifKosul}"
        );
        $stmt->execute([$firma_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function kullanilanIzinler(array $personelIds): array
    {
        if (!$personelIds) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($personelIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT pi.personel_id, SUM(pi.toplam_gun) AS gun
             FROM personel_izinleri pi
             INNER JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
             WHERE pi.personel_id IN ({$ph})
               AND pi.silinme_tarihi IS NULL
               AND pi.yillik_izne_etki = 1
               AND LOWER(pi.onay_durumu) IN ('onaylandı', 'onaylandi', 'kabuledildi')
               AND (t.tur_adi LIKE '%Yıllık%' OR t.tur_adi LIKE '%Yillik%')
             GROUP BY pi.personel_id"
        );
        $stmt->execute(array_values($personelIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $out[(int) $row->personel_id] = (float) $row->gun;
        }
        return $out;
    }

    private function manuelToplamlar(array $personelIds): array
    {
        if (!$personelIds) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($personelIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT personel_id, SUM(gun) AS gun
             FROM personel_yillik_izin_manuel_hakedis
             WHERE personel_id IN ({$ph}) AND is_active = 1 AND deleted_at IS NULL
             GROUP BY personel_id"
        );
        $stmt->execute(array_values($personelIds));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $out[(int) $row->personel_id] = (float) $row->gun;
        }
        return $out;
    }

    private function hakedisSatirlari(int $firma_id, bool $pasifDahil = false): array
    {
        $personeller = $this->personelSatirlari($firma_id, $pasifDahil);
        $personelIds = array_map(fn($p) => (int) $p->id, $personeller);
        $kullanilan = $this->kullanilanIzinler($personelIds);
        $manuel = $this->manuelToplamlar($personelIds);
        $bugun = new \DateTime('today');

        $rows = [];
        foreach ($personeller as $p) {
            $giris = $this->parseDate($p->ise_giris_tarihi);
            $dogum = $this->parseDate($p->dogum_tarihi);
            $cikis = $this->parseDate($p->isten_cikis_tarihi);
            $hesapTarihi = $cikis && $cikis < $bugun ? $cikis : $bugun;
            $hesap = $this->izinModel->hakedisDetayHesapla($giris, $dogum, $hesapTarihi);

            $manuelGun = $manuel[(int) $p->id] ?? 0.0;
            $toplam = (float) $hesap['toplam_hakedis'] + $manuelGun;
            $kullanilanGun = $kullanilan[(int) $p->id] ?? 0.0;
            $kalan = max(0.0, $toplam - $kullanilanGun);
            $kidemYil = $giris && $giris <= $hesapTarihi ? (int) $giris->diff($hesapTarihi)->y : 0;

            $sonraki = null;
            $sonrakiGun = null;
            if ($giris) {
                $sonrakiYil = $giris <= $bugun ? $kidemYil + 1 : 1;
                $sonrakiTarih = (clone $giris)->modify("+{$sonrakiYil} years");
                $sonrakiHesap = $this->izinModel->hakedisDetayHesapla($giris, $dogum, (clone $sonrakiTarih));
                $son = end($sonrakiHesap['detay']);
                $sonraki = $sonrakiTarih->format('Y-m-d');
                $sonrakiGun = $son ? (int) $son['hakedis_gun'] : null;
            }

            $rows[] = (object) [
                'id' => (int) $p->id,
                'adi_soyadi' => (string) $p->adi_soyadi,
                'departman' => (string) $p->departman,
                'gorev' => (string) $p->gorev,
                'ise_giris_tarihi' => $giris ? $giris->format('Y-m-d') : null,
                'kidem_yil' => $kidemYil,
                'manuel' => $manuelGun,
                'toplam' => $toplam,
                'kullanilan' => $kullanilanGun,
                'kalan' => $kalan,
                'sonraki_tarih' => $sonraki,
                'sonraki_gun' => $sonrakiGun,
                'pasif' => (bool) $p->pasif,
                'durum' => $toplam <= 0 ? 'hakedis_yok' : ($kalan > 0 ? 'kullanilabilir' : 'tukendi'),
            ];
        }

        return $rows;
    }

    public function summary(int $firma_id, bool $pasifDahil = false): object
    {
        $rows = $this->hakedisSatirlari($firma_id, $pasifDahil);

        $s = (object) [
            'toplam_personel' => count($rows),
            'hakedisi_olan' => 0,
            'hakedis_yok' => 0,
            'tukendi' => 0,
            'toplam_hakedis' => 0.0,
            'toplam_kullanilan' => 0.0,
            'toplam_kalan' => 0.0,
        ];
        foreach ($rows as $r) {
            $s->toplam_hakedis += $r->toplam;
            $s->toplam_kullanilan += $r->kullanilan;
            $s->toplam_kalan += $r->kalan;
            if ($r->durum === 'hakedis_yok') {
                $s->hakedis_yok++;
            } else {
                $s->hakedisi_olan++;
            }
            if ($r->durum === 'tukendi') {
                $s->tukendi++;
            }
        }
        $s->kullanilabilir = $s->toplam_personel - $s->hakedis_yok - $s->tukendi;

        return $s;
    }

    private static function gunMetni(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');
    }

    private static function tarihMetni(?string $ymd): string
    {
        return $ymd ? date('d.m.Y', strtotime($ymd)) : '-';
    }

    public function ajaxList(array $params, int $firma_id): array
    {
        $draw = (int) ($params['draw'] ?? 0);
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = (int) ($params['length'] ?? 25);
        $length = $length < 1 ? 25 : min($length, 500);
        $search = mb_strtolower(trim((string) ($params['search']['value'] ?? '')), 'UTF-8');
        $statusFilter = (string) ($params['status_filter'] ?? 'all');
        $columns = $params['columns'] ?? [];
        $orders = $params['order'] ?? [];

        $all = $this->hakedisSatirlari($firma_id, !empty($params['pasif_dahil']));
        $recordsTotal = count($all);

        $statusMap = [
            'kullanilabilir' => 'kullanilabilir',
            'tukendi' => 'tukendi',
            'hakedis_yok' => 'hakedis_yok',
        ];
        $rows = $all;
        if (isset($statusMap[$statusFilter])) {
            $rows = array_values(array_filter($rows, fn($r) => $r->durum === $statusMap[$statusFilter]));
        }

        if ($search !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($search) {
                $hay = mb_strtolower($r->adi_soyadi . ' ' . $r->departman . ' ' . $r->gorev, 'UTF-8');
                return mb_strpos($hay, $search) !== false;
            }));
        }

        $textCols = [1 => 'adi_soyadi', 2 => 'departman', 3 => 'gorev'];
        $durumEtiket = ['kullanilabilir' => 'kullanılabilir', 'tukendi' => 'tükendi', 'hakedis_yok' => 'hakediş yok'];

        foreach ($columns as $i => $column) {
            $raw = trim((string) ($column['search']['value'] ?? ''));
            if ($raw === '') {
                continue;
            }
            $i = (int) $i;
            $mode = 'contains';
            $val = $raw;
            if (strpos($raw, ':') !== false) {
                [$mode, $val] = explode(':', $raw, 2);
            }
            $valLower = mb_strtolower($val, 'UTF-8');

            if ($i === 9) {
                $parts = array_map(fn($p) => mb_strtolower(trim($p), 'UTF-8'), explode('|', $val));
                $rows = array_values(array_filter($rows, fn($r) => in_array($durumEtiket[$r->durum], $parts, true)));
                continue;
            }

            if (!isset($textCols[$i])) {
                continue;
            }
            $field = $textCols[$i];
            $rows = array_values(array_filter($rows, function ($r) use ($field, $mode, $val, $valLower) {
                $cell = mb_strtolower((string) $r->$field, 'UTF-8');
                switch ($mode) {
                    case 'equals':
                        return $cell === $valLower;
                    case 'multi':
                        $items = array_filter(array_map(fn($v) => mb_strtolower($v, 'UTF-8'), explode('|', $val)), fn($v) => $v !== '');
                        return !$items || in_array($cell, $items, true);
                    case 'startswith':
                        return mb_strpos($cell, $valLower) === 0;
                    case 'endswith':
                        return $valLower === '' || mb_substr($cell, -mb_strlen($valLower)) === $valLower;
                    default:
                        return mb_strpos($cell, $valLower) !== false;
                }
            }));
        }

        $recordsFiltered = count($rows);

        $orderMap = [
            1 => 'adi_soyadi', 2 => 'departman', 3 => 'gorev', 4 => 'ise_giris_tarihi', 5 => 'kidem_yil',
            6 => 'toplam', 7 => 'kullanilan', 8 => 'kalan', 9 => 'durum', 10 => 'sonraki_tarih',
        ];
        $orderField = 'adi_soyadi';
        $dir = 1;
        if (!empty($orders)) {
            $idx = (int) ($orders[0]['column'] ?? -1);
            if (isset($orderMap[$idx])) {
                $orderField = $orderMap[$idx];
                $dir = strtoupper((string) ($orders[0]['dir'] ?? '')) === 'DESC' ? -1 : 1;
            }
        }
        usort($rows, function ($a, $b) use ($orderField, $dir) {
            $x = $a->$orderField;
            $y = $b->$orderField;
            $cmp = is_string($x) || is_string($y) || $x === null || $y === null
                ? strcoll((string) $x, (string) $y)
                : $x <=> $y;
            return $cmp === 0 ? $a->id <=> $b->id : $cmp * $dir;
        });

        $rows = array_slice($rows, $start, $length);

        $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $badges = [
            'kullanilabilir' => ['bg-success-subtle text-success border-success-subtle', 'Kullanılabilir'],
            'tukendi' => ['bg-warning-subtle text-warning border-warning-subtle', 'Tükendi'],
            'hakedis_yok' => ['bg-secondary-subtle text-secondary border-secondary-subtle', 'Hakediş Yok'],
        ];

        $data = [];
        $sira = $start + 1;
        foreach ($rows as $r) {
            $encId = Security::encrypt($r->id);
            [$cls, $txt] = $badges[$r->durum];
            $kalanCls = $r->kalan > 0 ? 'text-success' : 'text-muted';
            $sonrakiMetin = $r->sonraki_tarih
                ? '<span class="fw-medium">' . $esc(self::tarihMetni($r->sonraki_tarih)) . '</span>'
                    . ($r->sonraki_gun ? ' <span class="text-muted font-size-11">(+' . (int) $r->sonraki_gun . ' gün)</span>' : '')
                : '-';

            $data[] = [
                'sira' => '<div class="text-center font-size-12 fw-medium text-muted">' . $sira++ . '</div>',
                'adi_soyadi' => '<span class="fw-bold text-dark font-size-13">' . $esc($r->adi_soyadi) . '</span>'
                    . ($r->pasif ? ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-size-10">Pasif</span>' : ''),
                'departman' => '<span class="font-size-12">' . $esc($r->departman) . '</span>',
                'gorev' => '<span class="font-size-12">' . $esc($r->gorev) . '</span>',
                'ise_giris_tarihi' => '<div class="text-center">' . $esc(self::tarihMetni($r->ise_giris_tarihi)) . '</div>',
                'kidem_yil' => '<div class="text-center">' . (int) $r->kidem_yil . ' yıl</div>',
                'toplam' => '<div class="text-center fw-semibold">' . $esc(self::gunMetni($r->toplam))
                    . ($r->manuel > 0 ? ' <i class="bx bx-edit-alt text-primary" title="Manuel hakediş dahil: ' . $esc(self::gunMetni($r->manuel)) . ' gün"></i>' : '') . '</div>',
                'kullanilan' => '<div class="text-center">' . $esc(self::gunMetni($r->kullanilan)) . '</div>',
                'kalan' => '<div class="text-center fw-bold ' . $kalanCls . '">' . $esc(self::gunMetni($r->kalan)) . '</div>',
                'durum' => '<div class="text-center"><span class="badge ' . $cls . ' border rounded-pill px-2 py-1 font-size-11 fw-semibold">' . $txt . '</span></div>',
                'sonraki' => $sonrakiMetin,
                'actions' => '<div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">'
                    . '<button type="button" class="btn btn-sm btn-subtle-primary table-action-btn detay" data-id="' . $encId . '" title="Hakediş Detayı"><i class="bx bx-show"></i></button>'
                    . '</div>',
                'enc_id' => $encId,
            ];
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    public function detay(int $personel_id, int $firma_id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, adi_soyadi, ise_giris_tarihi, dogum_tarihi
             FROM personel
             WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL"
        );
        $stmt->execute([$personel_id, $firma_id]);
        $p = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$p) {
            return null;
        }

        $hesap = $this->izinModel->hakedisDetayHesapla(
            $this->parseDate($p->ise_giris_tarihi),
            $this->parseDate($p->dogum_tarihi),
            new \DateTime('today')
        );

        $stmt = $this->db->prepare(
            "SELECT pi.baslangic_tarihi, pi.bitis_tarihi, pi.toplam_gun, pi.yillik_izne_etki, pi.onay_durumu
             FROM personel_izinleri pi
             INNER JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
             WHERE pi.personel_id = ?
               AND pi.silinme_tarihi IS NULL
               AND LOWER(pi.onay_durumu) IN ('onaylandı', 'onaylandi', 'kabuledildi')
               AND (t.tur_adi LIKE '%Yıllık%' OR t.tur_adi LIKE '%Yillik%')
             ORDER BY pi.baslangic_tarihi DESC"
        );
        $stmt->execute([$personel_id]);
        $izinler = $stmt->fetchAll(PDO::FETCH_OBJ);

        $kullanilan = 0.0;
        $izinSatirlari = [];
        foreach ($izinler as $izin) {
            $dusuyor = (int) $izin->yillik_izne_etki === 1;
            if ($dusuyor) {
                $kullanilan += (float) $izin->toplam_gun;
            }
            $izinSatirlari[] = [
                'baslangic' => self::tarihMetni($izin->baslangic_tarihi),
                'bitis' => self::tarihMetni($izin->bitis_tarihi),
                'gun' => (float) $izin->toplam_gun,
                'dusuyor' => $dusuyor,
            ];
        }

        $stmt = $this->db->prepare(
            "SELECT id, hakedis_tarihi, gun, aciklama, kaynak
             FROM personel_yillik_izin_manuel_hakedis
             WHERE personel_id = ? AND is_active = 1 AND deleted_at IS NULL
             ORDER BY hakedis_tarihi DESC, id DESC"
        );
        $stmt->execute([$personel_id]);
        $manuelSatirlar = [];
        $manuelToplam = 0.0;
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $m) {
            $manuelToplam += (float) $m->gun;
            $manuelSatirlar[] = [
                'id' => Security::encrypt($m->id),
                'tarih' => self::tarihMetni($m->hakedis_tarihi),
                'gun' => (float) $m->gun,
                'aciklama' => (string) $m->aciklama,
                'kaynak' => (string) $m->kaynak,
            ];
        }
        $toplamHakedis = (float) $hesap['toplam_hakedis'] + $manuelToplam;

        $yillar = [];
        foreach ($hesap['detay'] as $d) {
            $yillar[] = [
                'yil' => (int) $d['yil'],
                'tarih' => self::tarihMetni($d['hakedis_tarihi']),
                'gun' => (int) $d['hakedis_gun'],
            ];
        }

        return [
            'adi_soyadi' => (string) $p->adi_soyadi,
            'ise_giris_tarihi' => self::tarihMetni($p->ise_giris_tarihi),
            'toplam' => $toplamHakedis,
            'otomatik' => (float) $hesap['toplam_hakedis'],
            'manuel_toplam' => $manuelToplam,
            'kullanilan' => $kullanilan,
            'kalan' => max(0.0, $toplamHakedis - $kullanilan),
            'yillar' => $yillar,
            'manuel' => $manuelSatirlar,
            'izinler' => $izinSatirlari,
        ];
    }

    public function aktifPersoneller(int $firma_id): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, adi_soyadi, departman
             FROM personel
             WHERE firma_id = ? AND silinme_tarihi IS NULL AND aktif_mi = 1 AND personel_tipi = 'standart'
               AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi > CURDATE())
             ORDER BY adi_soyadi ASC"
        );
        $stmt->execute([$firma_id]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $p) {
            $out[Security::encrypt($p->id)] = $p->adi_soyadi;
        }
        return $out;
    }

    public function sablonPersoneller(int $firma_id): array
    {
        $personelModel = new PersonelModel();
        $stmt = $this->db->prepare(
            "SELECT * FROM personel
             WHERE firma_id = ? AND silinme_tarihi IS NULL AND aktif_mi = 1 AND personel_tipi = 'standart'
               AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi > CURDATE())
             ORDER BY adi_soyadi ASC"
        );
        $stmt->execute([$firma_id]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $p) {
            $dec = $personelModel->decryptFields($p);
            $out[] = ['tc' => trim((string) ($dec->tc_kimlik_no ?? '')), 'ad' => (string) $p->adi_soyadi];
        }
        return $out;
    }

    private function personelFirmadaMi(int $personel_id, int $firma_id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM personel WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$personel_id, $firma_id]);
        return (bool) $stmt->fetchColumn();
    }

    public function manuelEkle(int $firma_id, int $personel_id, string $tarihYmd, float $gun, string $aciklama, string $kaynak, int $userId): int
    {
        if (!$this->personelFirmadaMi($personel_id, $firma_id)) {
            throw new \InvalidArgumentException('Personel bulunamadı.');
        }
        if ($gun <= 0 || $gun > 100) {
            throw new \InvalidArgumentException('Gün değeri 0 ile 100 arasında olmalıdır.');
        }
        $d = \DateTime::createFromFormat('Y-m-d', $tarihYmd);
        if (!$d || $d->format('Y-m-d') !== $tarihYmd) {
            throw new \InvalidArgumentException('Hakediş tarihi geçersiz.');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO personel_yillik_izin_manuel_hakedis
                (firma_id, personel_id, hakedis_tarihi, gun, aciklama, kaynak, is_active, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())"
        );
        $stmt->execute([
            $firma_id, $personel_id, $tarihYmd, round($gun, 1),
            mb_substr(trim($aciklama), 0, 255, 'UTF-8'), $kaynak === 'excel' ? 'excel' : 'manuel', $userId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function manuelBul(int $id, int $firma_id): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT id, personel_id, hakedis_tarihi, gun FROM personel_yillik_izin_manuel_hakedis
             WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$id, $firma_id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function manuelSil(int $id, int $firma_id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE personel_yillik_izin_manuel_hakedis
             SET deleted_at = NOW(), is_active = 0, deleted_by = ?
             WHERE id = ? AND firma_id = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$userId, $id, $firma_id]);
        return $stmt->rowCount() > 0;
    }

    private function manuelKayitVarMi(int $personel_id, string $tarihYmd, float $gun): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM personel_yillik_izin_manuel_hakedis
             WHERE personel_id = ? AND hakedis_tarihi = ? AND gun = ? AND is_active = 1 AND deleted_at IS NULL"
        );
        $stmt->execute([$personel_id, $tarihYmd, round($gun, 1)]);
        return (bool) $stmt->fetchColumn();
    }

    public function excelIceAktar(array $rows, int $firma_id, int $userId): array
    {
        $personelModel = new PersonelModel();
        $eklenen = 0;
        $atlanan = 0;
        $hatalar = [];

        foreach ($rows as $no => $row) {
            $tc = preg_replace('/\D/', '', (string) ($row['tc'] ?? ''));
            $gunHam = trim((string) ($row['gun'] ?? ''));
            $tarihHam = $row['tarih'] ?? '';
            if ($tc === '' && $gunHam === '' && trim((string) $tarihHam) === '') {
                continue;
            }
            if ($gunHam === '') {
                continue;
            }

            try {
                if (strlen($tc) !== 11) {
                    throw new \InvalidArgumentException('TC Kimlik No geçersiz.');
                }
                $bulunan = $personelModel->findByTc($tc);
                $bulunan = array_values(array_filter($bulunan, fn($p) => (int) $p->firma_id === $firma_id));
                if (count($bulunan) !== 1) {
                    throw new \InvalidArgumentException(count($bulunan) === 0 ? 'Personel bulunamadı.' : 'TC ile birden fazla personel eşleşti.');
                }

                $gun = (float) str_replace(',', '.', $gunHam);
                $tarih = self::tarihNormalize($tarihHam);
                if ($tarih === null) {
                    throw new \InvalidArgumentException('Hakediş tarihi okunamadı (GG.AA.YYYY).');
                }
                $personelId = (int) $bulunan[0]->id;
                if ($this->manuelKayitVarMi($personelId, $tarih, $gun)) {
                    $atlanan++;
                    continue;
                }
                $this->manuelEkle($firma_id, $personelId, $tarih, $gun, (string) ($row['aciklama'] ?? ''), 'excel', $userId);
                $eklenen++;
            } catch (\InvalidArgumentException $e) {
                $hatalar[] = 'Satır ' . $no . ': ' . $e->getMessage();
            }
        }

        return ['eklenen' => $eklenen, 'atlanan' => $atlanan, 'hatalar' => $hatalar];
    }

    private static function tarihNormalize($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        $v = trim((string) $v);
        foreach (['d.m.Y', 'd/m/Y', 'Y-m-d'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $v);
            if ($d && $d->format($fmt) === $v) {
                return $d->format('Y-m-d');
            }
        }
        return null;
    }
}
