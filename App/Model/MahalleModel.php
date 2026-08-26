<?php

namespace App\Model;

use PDO;

class MahalleModel extends Model
{
    protected $table = 'mahalle';

    const ILCELER = [
        'onikisubat' => 'Onikişubat',
        'dulkadiroglu' => 'Dulkadiroğlu',
    ];

    const DURUMLAR = [
        'atanabilir' => 'Atanabilir',
        'bekliyor' => 'Bekliyor',
        'mesajsiz' => 'Mesaj atılmadı',
        'sahada' => 'Ekip sahada',
        'girilmiyor' => 'Girilmiyor',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function mesajBeklemeGun(): int
    {
        $stmt = $this->db->prepare("SELECT deger FROM kesme_acma_kural_degeri
            WHERE firma_id = ? AND kural_kodu = 'mahalle_mesaj_bekleme' LIMIT 1");
        $stmt->execute([$this->firmaId()]);
        $deger = json_decode((string) $stmt->fetchColumn(), true);
        if (!is_numeric($deger) || (int) $deger < 0) {
            throw new \RuntimeException('Mahalle mesaj bekleme kuralı tanımlı değil.');
        }
        return (int) $deger;
    }

    public function listele(): array
    {
        $stmt = $this->db->prepare("SELECT m.id, m.ad, m.ilce, m.kod_araligi, m.havuzda,
                mes.mesaj_tarihi, mes.hazir_tarihi,
                a.id AS atama_id, a.ekip_id AS aktif_ekip_id, a.baslangic AS atama_baslangic,
                t.tur_adi AS aktif_ekip_adi
            FROM {$this->table} m
            LEFT JOIN (
                SELECT x.mahalle_id, x.mesaj_tarihi, x.hazir_tarihi
                FROM mahalle_mesaj x
                INNER JOIN (
                    SELECT mahalle_id, MAX(id) AS son_id
                    FROM mahalle_mesaj WHERE firma_id = ? GROUP BY mahalle_id
                ) s ON s.son_id = x.id
            ) mes ON mes.mahalle_id = m.id
            LEFT JOIN ekip_mahalle_atama a
                ON a.mahalle_id = m.id AND a.firma_id = m.firma_id AND a.durum = 'aktif'
            LEFT JOIN tanimlamalar t ON t.id = a.ekip_id
            WHERE m.firma_id = ?
            ORDER BY m.ilce ASC, m.ad ASC");
        $stmt->execute([$this->firmaId(), $this->firmaId()]);

        $bugun = date('Y-m-d');
        $liste = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $satir['id'] = (int) $satir['id'];
            $satir['havuzda'] = (int) $satir['havuzda'];
            $satir['aktif_ekip_id'] = $satir['aktif_ekip_id'] !== null ? (int) $satir['aktif_ekip_id'] : null;
            $satir['durum'] = $this->durumHesapla($satir, $bugun);
            $liste[] = $satir;
        }
        return $liste;
    }

    private function durumHesapla(array $satir, string $bugun): string
    {
        if ((int) $satir['havuzda'] === 0) {
            return 'girilmiyor';
        }
        if (!empty($satir['atama_id'])) {
            return 'sahada';
        }
        if (empty($satir['mesaj_tarihi'])) {
            return 'mesajsiz';
        }
        return $satir['hazir_tarihi'] <= $bugun ? 'atanabilir' : 'bekliyor';
    }

    public function bul(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND firma_id = ?");
        $stmt->execute([$id, $this->firmaId()]);
        $satir = $stmt->fetch(PDO::FETCH_ASSOC);
        return $satir ?: null;
    }

    public function ekle(array $veri): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table} (firma_id, ad, ilce, kod_araligi, havuzda)
            VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $this->firmaId(),
            $veri['ad'],
            $veri['ilce'],
            $veri['kod_araligi'] ?: null,
            (int) ($veri['havuzda'] ?? 1),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function guncelle(int $id, array $veri): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET ad = ?, ilce = ?, kod_araligi = ?, havuzda = ?
            WHERE id = ? AND firma_id = ?");
        return $stmt->execute([
            $veri['ad'],
            $veri['ilce'],
            $veri['kod_araligi'] ?: null,
            (int) ($veri['havuzda'] ?? 1),
            $id,
            $this->firmaId(),
        ]);
    }

    public function havuzDurumu(int $id, int $havuzda): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET havuzda = ? WHERE id = ? AND firma_id = ?");
        return $stmt->execute([$havuzda, $id, $this->firmaId()]);
    }

    public function mesajKaydet(int $mahalleId, string $mesajTarihi, ?int $kaydedenId): array
    {
        $hazir = date('Y-m-d', strtotime($mesajTarihi . ' +' . $this->mesajBeklemeGun() . ' day'));

        $stmt = $this->db->prepare("INSERT INTO mahalle_mesaj
            (firma_id, mahalle_id, mesaj_tarihi, hazir_tarihi, kaydeden_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$this->firmaId(), $mahalleId, $mesajTarihi, $hazir, $kaydedenId]);

        return ['mesaj_tarihi' => $mesajTarihi, 'hazir_tarihi' => $hazir];
    }

    public function mesajGecmisi(int $mahalleId): array
    {
        $stmt = $this->db->prepare("SELECT mm.mesaj_tarihi, mm.hazir_tarihi, mm.created_at, p.adi_soyadi AS kaydeden
            FROM mahalle_mesaj mm
            LEFT JOIN personel p ON p.id = mm.kaydeden_id
            WHERE mm.mahalle_id = ? AND mm.firma_id = ?
            ORDER BY mm.mesaj_tarihi DESC, mm.id DESC");
        $stmt->execute([$mahalleId, $this->firmaId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
