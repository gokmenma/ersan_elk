<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelCalismaSaatleriModel extends Model
{
    protected $table = 'personel_calisma_saatleri';
    protected $auditEnabled = true;
    protected $auditLabel = 'Personel Çalışma Saatleri';

    private const SAAT_ALANLARI = [
        'gorev_baslama', 'gorev_bitis',
        'yemek_baslangic', 'yemek_bitis',
        'mola1_baslangic', 'mola1_bitis',
        'mola2_baslangic', 'mola2_bitis',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public static function normalizeSaat($deger): ?string
    {
        $deger = trim((string) $deger);
        if ($deger === '' || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $deger)) {
            return null;
        }
        return substr($deger, 0, 5);
    }

    private static function dakika(?string $saat): ?int
    {
        if ($saat === null || $saat === '') {
            return null;
        }
        return ((int) substr($saat, 0, 2)) * 60 + (int) substr($saat, 3, 2);
    }

    private static function aralik(?string $bas, ?string $bit): int
    {
        $b = self::dakika($bas);
        $e = self::dakika($bit);
        if ($b === null || $e === null) {
            return 0;
        }
        return $e >= $b ? $e - $b : $e + 1440 - $b;
    }

    public static function netHesapla(array $v): array
    {
        $br = self::aralik($v['gorev_baslama'] ?? null, $v['gorev_bitis'] ?? null);
        $yemek = self::aralik($v['yemek_baslangic'] ?? null, $v['yemek_bitis'] ?? null);
        $mola = self::aralik($v['mola1_baslangic'] ?? null, $v['mola1_bitis'] ?? null)
            + self::aralik($v['mola2_baslangic'] ?? null, $v['mola2_bitis'] ?? null);
        $diger = max(0, (int) ($v['diger_dusulecek_dk'] ?? 0));
        $net = max(0, $br - $yemek - $mola - $diger);
        return ['brut' => $br, 'yemek' => $yemek, 'mola' => $mola, 'diger' => $diger, 'net' => $net];
    }

    public static function dkMetin(int $dk): string
    {
        return intdiv($dk, 60) . ' sa ' . ($dk % 60) . ' dk';
    }

    public function getByPersonel(int $personelId): ?object
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE personel_id = :pid AND silinme_tarihi IS NULL");
        $stmt->execute([':pid' => $personelId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function getAllMap(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE silinme_tarihi IS NULL");
        $stmt->execute();
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $map[(int) $row->personel_id] = $row;
        }
        return $map;
    }

    public function saveForPersonel(int $personelId, array $input): array
    {
        $veri = [];
        foreach (self::SAAT_ALANLARI as $alan) {
            $veri[$alan] = self::normalizeSaat($input[$alan] ?? '');
        }
        $veri['diger_dusulecek_dk'] = max(0, min(1440, (int) ($input['diger_dusulecek_dk'] ?? 0)));
        $hesap = self::netHesapla($veri);
        $veri['net_calisma_dk'] = $veri['gorev_baslama'] && $veri['gorev_bitis'] ? $hesap['net'] : null;

        $eski = $this->getByPersonel($personelId);

        if ($eski) {
            $set = [];
            $params = [':id' => $eski->id];
            foreach ($veri as $k => $v) {
                $set[] = "$k = :$k";
                $params[":$k"] = $v;
            }
            $stmt = $this->db->prepare("UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE id = :id");
            $stmt->execute($params);
            $this->auditChange('update', (int) $eski->id, $eski, $veri);
        } else {
            $kolonlar = array_merge(['personel_id'], array_keys($veri));
            $params = [':personel_id' => $personelId];
            foreach ($veri as $k => $v) {
                $params[":$k"] = $v;
            }
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (" . implode(', ', $kolonlar) . ") VALUES (" . implode(', ', array_keys($params)) . ")"
            );
            $stmt->execute($params);
            $this->auditChange('insert', (int) $this->db->lastInsertId(), null, $veri + ['personel_id' => $personelId]);
        }

        return $hesap;
    }
}
