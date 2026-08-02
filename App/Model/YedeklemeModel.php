<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class YedeklemeModel extends Model
{
    private $kesifOnbellek = null;
    private $kolonOnbellek = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function veritabaniAdi(): string
    {
        return (string) $this->db->query("SELECT DATABASE()")->fetchColumn();
    }

    public function maxPaket(): int
    {
        return (int) $this->db->query("SELECT @@max_allowed_packet")->fetchColumn();
    }

    public function sunucuZamani(): string
    {
        return (string) $this->db->query("SELECT NOW()")->fetchColumn();
    }

    public function veritabaniSec(string $ad): void
    {
        $stmt = $this->db->prepare(
            "SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?"
        );
        $stmt->execute([$ad]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException('Hedef veritabani bulunamadi.');
        }
        $this->db->exec("USE `" . str_replace('`', '``', $ad) . "`");
        $this->kesifOnbellek = null;
        $this->kolonOnbellek = [];
    }

    private function kesif(): array
    {
        if ($this->kesifOnbellek === null) {
            $stmt = $this->db->prepare(
                "SELECT table_name, table_type FROM information_schema.tables
                 WHERE table_schema = DATABASE() ORDER BY table_name"
            );
            $stmt->execute();
            $this->kesifOnbellek = [];
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $satir) {
                $this->kesifOnbellek[$satir[0]] = $satir[1];
            }
        }
        return $this->kesifOnbellek;
    }

    public function tabloListesi(): array
    {
        $liste = [];
        foreach ($this->kesif() as $ad => $tip) {
            if ($tip === 'BASE TABLE') {
                $liste[] = $ad;
            }
        }
        return $liste;
    }

    public function viewListesi(): array
    {
        $liste = [];
        foreach ($this->kesif() as $ad => $tip) {
            if ($tip !== 'BASE TABLE') {
                $liste[] = $ad;
            }
        }
        return $liste;
    }

    public function tabloVarMi(string $tablo): bool
    {
        return isset($this->kesif()[$tablo]);
    }

    private function guvenliTablo(string $tablo): string
    {
        if (!$this->tabloVarMi($tablo)) {
            throw new \InvalidArgumentException('Yedeklemede bilinmeyen tablo adi.');
        }
        return '`' . str_replace('`', '``', $tablo) . '`';
    }

    private function guvenliKolon(string $tablo, string $kolon): string
    {
        if (!in_array($kolon, $this->kolonlar($tablo), true)) {
            throw new \InvalidArgumentException('Yedeklemede bilinmeyen kolon adi.');
        }
        return '`' . str_replace('`', '``', $kolon) . '`';
    }

    public function kolonlar(string $tablo): array
    {
        if (!isset($this->kolonOnbellek[$tablo])) {
            $stmt = $this->db->prepare(
                "SELECT column_name FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position"
            );
            $stmt->execute([$tablo]);
            $this->kolonOnbellek[$tablo] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        return $this->kolonOnbellek[$tablo];
    }

    public function otoArtanKolon(string $tablo): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND extra LIKE '%auto_increment%' LIMIT 1"
        );
        $stmt->execute([$tablo]);
        $kolon = $stmt->fetchColumn();
        return $kolon === false ? null : $kolon;
    }

    public function degisimKolonlari(string $tablo): array
    {
        $adaylar = ['updated_at', 'guncelleme_tarihi', 'silinme_tarihi', 'deleted_at'];
        return array_values(array_intersect($adaylar, $this->kolonlar($tablo)));
    }

    public function olusturmaKodu(string $tablo): string
    {
        $satir = $this->db->query("SHOW CREATE TABLE " . $this->guvenliTablo($tablo))->fetch(PDO::FETCH_NUM);
        return $satir[1] ?? '';
    }

    public function semaOzeti(): string
    {
        $parcalar = [];
        foreach ($this->tabloListesi() as $tablo) {
            $kod = $this->olusturmaKodu($tablo);
            $parcalar[] = preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $kod);
        }
        return md5(implode("\n", $parcalar));
    }

    public function triggerListesi(): array
    {
        $liste = [];
        try {
            $stmt = $this->db->query("SHOW TRIGGERS");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
                $ad = $satir['Trigger'] ?? '';
                if ($ad === '') {
                    continue;
                }
                $kod = $this->db->query("SHOW CREATE TRIGGER `" . str_replace('`', '``', $ad) . "`")
                    ->fetch(PDO::FETCH_ASSOC);
                $liste[] = [
                    'ad'    => $ad,
                    'tablo' => $satir['Table'] ?? '',
                    'sql'   => $kod['SQL Original Statement'] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            error_log('[yedekleme] Trigger listesi alinamadi: ' . $e->getMessage());
        }
        return $liste;
    }

    public function rutinListesi(): array
    {
        $liste = [];
        foreach (['PROCEDURE', 'FUNCTION'] as $tip) {
            try {
                $stmt = $this->db->prepare(
                    "SELECT routine_name FROM information_schema.routines
                     WHERE routine_schema = DATABASE() AND routine_type = ?"
                );
                $stmt->execute([$tip]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $ad) {
                    $kod = $this->db->query("SHOW CREATE $tip `" . str_replace('`', '``', $ad) . "`")
                        ->fetch(PDO::FETCH_NUM);
                    if (!empty($kod[2])) {
                        $liste[] = ['ad' => $ad, 'tip' => $tip, 'sql' => $kod[2]];
                    }
                }
            } catch (\Throwable $e) {
                error_log('[yedekleme] Rutin listesi alinamadi (' . $tip . '): ' . $e->getMessage());
            }
        }
        return $liste;
    }

    public function enBuyukDeger(string $tablo, string $kolon)
    {
        $sql = "SELECT MAX(" . $this->guvenliKolon($tablo, $kolon) . ") FROM " . $this->guvenliTablo($tablo);
        $deger = $this->db->query($sql)->fetchColumn();
        return $deger === false ? null : $deger;
    }

    private function kosulKur(string $tablo, array $kosul): array
    {
        $parcalar = [];
        $parametreler = [];

        $idKolon = $kosul['id_kolon'] ?? null;
        if ($idKolon !== null && ($kosul['id_deger'] ?? null) !== null) {
            $parcalar[] = $this->guvenliKolon($tablo, $idKolon) . " > ?";
            $parametreler[] = $kosul['id_deger'];
        }

        $zamanDeger = $kosul['zaman_deger'] ?? null;
        if ($zamanDeger !== null) {
            foreach (($kosul['zaman_kolonlari'] ?? []) as $zamanKolon) {
                $parcalar[] = $this->guvenliKolon($tablo, $zamanKolon) . " > ?";
                $parametreler[] = $zamanDeger;
            }
        }

        if (empty($parcalar)) {
            return ['', []];
        }
        return [' WHERE ' . implode(' OR ', $parcalar), $parametreler];
    }

    public function satirSayisi(string $tablo, array $kosul = []): int
    {
        [$where, $parametreler] = $this->kosulKur($tablo, $kosul);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM " . $this->guvenliTablo($tablo) . $where);
        $stmt->execute($parametreler);
        return (int) $stmt->fetchColumn();
    }

    public function satirAkisi(string $tablo, array $kosul = []): \Generator
    {
        [$where, $parametreler] = $this->kosulKur($tablo, $kosul);

        $kolonlar = $this->kolonlar($tablo);
        $secim = implode(', ', array_map(function ($k) use ($tablo) {
            return $this->guvenliKolon($tablo, $k);
        }, $kolonlar));

        $sql = "SELECT $secim FROM " . $this->guvenliTablo($tablo) . $where;

        $stmt = $this->db->prepare($sql, [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]);
        $stmt->execute($parametreler);

        try {
            while ($satir = $stmt->fetch(PDO::FETCH_NUM)) {
                yield $satir;
            }
        } finally {
            $stmt->closeCursor();
        }
    }

    public function tirnakla($deger): string
    {
        if ($deger === null) {
            return 'NULL';
        }
        return $this->db->quote((string) $deger);
    }

    public function calistir(string $sql): void
    {
        $this->db->exec($sql);
    }

    public function yedekOturumuAc(): void
    {
        $this->db->exec("SET SESSION FOREIGN_KEY_CHECKS = 0");
        $this->db->exec("SET SESSION UNIQUE_CHECKS = 0");
        $this->db->exec("SET SESSION SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    }

    public function yedekOturumuKapat(): void
    {
        $this->db->exec("SET SESSION UNIQUE_CHECKS = 1");
        $this->db->exec("SET SESSION FOREIGN_KEY_CHECKS = 1");
    }

    public function islemBaslat(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function islemOnayla(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function islemGeriAl(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function tutarliOkumaBaslat(): void
    {
        $this->db->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
        $this->db->exec("START TRANSACTION WITH CONSISTENT SNAPSHOT");
    }

    public function tutarliOkumaBitir(): void
    {
        $this->db->exec("COMMIT");
    }
}
