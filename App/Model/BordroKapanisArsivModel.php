<?php

namespace App\Model;

use PDO;
use RuntimeException;

final class BordroKapanisArsivModel extends Model
{
    protected $table = 'bordro_kapanis_arsiv';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function canonicalJson(array $data): string
    {
        $normalize = function (&$value) use (&$normalize): void {
            if (!is_array($value)) return;
            if (!array_is_list($value)) ksort($value);
            foreach ($value as &$item) $normalize($item);
        };
        $normalize($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) throw new RuntimeException('Bordro arşiv JSON verisi üretilemedi.');
        return $json;
    }

    /** Doğrulanmış açık dönemi arşivler ve aynı transaction içinde kapatır. */
    public function arsivleVeKapat(int $firmaId, int $donemId, int $kullaniciId, string $kullaniciAdi): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id, donem_adi, baslangic_tarihi, bitis_tarihi, kapali_mi FROM bordro_donemi WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL FOR UPDATE');
            $stmt->execute(['id' => $donemId, 'firma_id' => $firmaId]);
            $donem = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$donem) throw new RuntimeException('Dönem bulunamadı veya bu firmaya ait değil.');
            if ((int) $donem['kapali_mi'] === 1) throw new RuntimeException('Dönem zaten kapalı.');

            $stmt = $this->db->prepare('SELECT id, personel_id, brut_maas, net_maas, sgk_isci, issizlik_isci, gelir_vergisi, damga_vergisi, sgk_isveren, issizlik_isveren, toplam_maliyet, kumulatif_matrah, calisan_gun, fazla_mesai_saat, fazla_mesai_tutar, kesinti_tutar, prim_tutar, banka_odemesi, sodexo_odemesi, diger_odeme, elden_odeme, dagitim_manuel, hesaplama_tarihi, hesaplayan_id, hesaplayan_ad_soyad, hesaplama_detay FROM bordro_personel WHERE donem_id = :donem_id AND silinme_tarihi IS NULL ORDER BY personel_id, id FOR UPDATE');
            $stmt->execute(['donem_id' => $donemId]);
            $bordrolar = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($bordrolar)) throw new RuntimeException('Arşivlenecek bordro kaydı bulunamadı.');

            $stmt = $this->db->prepare('SELECT surum_no, manifest_hash FROM bordro_kapanis_arsiv WHERE firma_id = :firma_id AND donem_id = :donem_id ORDER BY surum_no DESC LIMIT 1 FOR UPDATE');
            $stmt->execute(['firma_id' => $firmaId, 'donem_id' => $donemId]);
            $onceki = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $surum = (int) ($onceki['surum_no'] ?? 0) + 1;

            $dokumler = [];
            foreach ($bordrolar as $bordro) {
                $snapshot = $bordro;
                $snapshot['hesaplama_detay'] = json_decode((string) ($bordro['hesaplama_detay'] ?? ''), true);
                $snapshotJson = $this->canonicalJson($snapshot);
                $dokumler[] = [
                    'bordro_personel_id' => (int) $bordro['id'],
                    'personel_id' => (int) $bordro['personel_id'],
                    'dokum_hash' => hash('sha256', $snapshotJson),
                    'snapshot_json' => $snapshotJson,
                ];
            }

            $manifest = [
                'schema' => 'bordro-kapanis-arsiv-v1',
                'firma_id' => $firmaId,
                'donem' => ['id' => $donemId, 'adi' => $donem['donem_adi'], 'baslangic' => $donem['baslangic_tarihi'], 'bitis' => $donem['bitis_tarihi']],
                'surum_no' => $surum,
                'onceki_arsiv_hash' => $onceki['manifest_hash'] ?? null,
                'kapatan' => ['id' => $kullaniciId, 'adi' => $kullaniciAdi],
                'dokumler' => array_map(static fn ($d) => ['bordro_personel_id' => $d['bordro_personel_id'], 'personel_id' => $d['personel_id'], 'dokum_hash' => $d['dokum_hash']], $dokumler),
            ];
            $manifestJson = $this->canonicalJson($manifest);
            $manifestHash = hash('sha256', $manifestJson);

            $stmt = $this->db->prepare('INSERT INTO bordro_kapanis_arsiv (firma_id, donem_id, surum_no, kapatan_kullanici_id, kapatan_adi, onceki_arsiv_hash, manifest_hash, manifest_json) VALUES (:firma_id, :donem_id, :surum_no, :kullanici_id, :kullanici_adi, :onceki_hash, :manifest_hash, :manifest_json)');
            $stmt->execute(['firma_id' => $firmaId, 'donem_id' => $donemId, 'surum_no' => $surum, 'kullanici_id' => $kullaniciId, 'kullanici_adi' => $kullaniciAdi, 'onceki_hash' => $onceki['manifest_hash'] ?? null, 'manifest_hash' => $manifestHash, 'manifest_json' => $manifestJson]);
            $arsivId = (int) $this->db->lastInsertId();

            $dokumStmt = $this->db->prepare('INSERT INTO bordro_kapanis_arsiv_dokum (arsiv_id, bordro_personel_id, personel_id, dokum_hash, snapshot_json) VALUES (:arsiv_id, :bordro_id, :personel_id, :dokum_hash, :snapshot_json)');
            foreach ($dokumler as $dokum) {
                $dokumStmt->execute(['arsiv_id' => $arsivId, 'bordro_id' => $dokum['bordro_personel_id'], 'personel_id' => $dokum['personel_id'], 'dokum_hash' => $dokum['dokum_hash'], 'snapshot_json' => $dokum['snapshot_json']]);
            }

            $stmt = $this->db->prepare('UPDATE bordro_donemi SET kapali_mi = 1 WHERE id = :id AND firma_id = :firma_id AND kapali_mi = 0');
            $stmt->execute(['id' => $donemId, 'firma_id' => $firmaId]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Dönem kapatma sırasında eşzamanlı değişiklik algılandı.');

            $this->db->commit();
            return ['arsiv_id' => $arsivId, 'surum_no' => $surum, 'manifest_hash' => $manifestHash, 'dokum_sayisi' => count($dokumler)];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function getSurumler(int $firmaId, int $donemId): array
    {
        $stmt = $this->db->prepare('SELECT id, surum_no, kapatan_kullanici_id, kapatan_adi, onceki_arsiv_hash, manifest_hash, created_at FROM bordro_kapanis_arsiv WHERE firma_id = :firma_id AND donem_id = :donem_id ORDER BY surum_no DESC');
        $stmt->execute(['firma_id' => $firmaId, 'donem_id' => $donemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verifyArchive(int $firmaId, int $arsivId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM bordro_kapanis_arsiv WHERE id = :id AND firma_id = :firma_id');
        $stmt->execute(['id' => $arsivId, 'firma_id' => $firmaId]);
        $arsiv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$arsiv) return ['valid' => false, 'message' => 'Arşiv bulunamadı.'];

        $manifestValid = hash_equals((string) $arsiv['manifest_hash'], hash('sha256', (string) $arsiv['manifest_json']));
        $stmt = $this->db->prepare('SELECT dokum_hash, snapshot_json FROM bordro_kapanis_arsiv_dokum WHERE arsiv_id = :arsiv_id ORDER BY id');
        $stmt->execute(['arsiv_id' => $arsivId]);
        $invalidDocuments = 0;
        $documentCount = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $dokum) {
            $documentCount++;
            if (!hash_equals((string) $dokum['dokum_hash'], hash('sha256', (string) $dokum['snapshot_json']))) $invalidDocuments++;
        }

        return [
            'valid' => $manifestValid && $invalidDocuments === 0,
            'manifest_valid' => $manifestValid,
            'document_count' => $documentCount,
            'invalid_document_count' => $invalidDocuments,
            'manifest_hash' => (string) $arsiv['manifest_hash'],
            'version' => (int) $arsiv['surum_no'],
        ];
    }

    /** Dışa aktarılabilecek mühürlü veya kapalı legacy dönemleri listeler. */
    public function getExportPeriods(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT bd.id, bd.donem_adi, bd.baslangic_tarihi, bd.bitis_tarihi,
                   CASE WHEN EXISTS (
                       SELECT 1 FROM bordro_kapanis_arsiv a
                       WHERE a.firma_id = bd.firma_id AND a.donem_id = bd.id
                   ) THEN 1 ELSE 0 END AS muhurlu
            FROM bordro_donemi bd
            WHERE bd.firma_id = :firma_id AND bd.silinme_tarihi IS NULL
              AND (
                  EXISTS (SELECT 1 FROM bordro_kapanis_arsiv a WHERE a.firma_id = bd.firma_id AND a.donem_id = bd.id)
                  OR (bd.kapali_mi = 1 AND EXISTS (
                      SELECT 1 FROM bordro_personel bp
                      WHERE bp.donem_id = bd.id AND bp.silinme_tarihi IS NULL
                  ))
              )
            ORDER BY bd.baslangic_tarihi DESC, bd.id DESC");
        $stmt->execute(['firma_id' => $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mühürlü sürümler ile henüz arşivlenmemiş kapalı legacy kayıtları dışa aktarır. */
    public function getExportDocuments(int $firmaId, array $donemIds = []): array
    {
        $donemIds = array_values(array_unique(array_filter(array_map('intval', $donemIds), static fn (int $id): bool => $id > 0)));
        $donemFilter = '';
        $params = ['firma_id' => $firmaId];
        if ($donemIds) {
            $placeholders = [];
            foreach ($donemIds as $index => $donemId) {
                $key = 'donem_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $donemId;
            }
            $donemFilter = ' AND bd.id IN (' . implode(',', $placeholders) . ')';
        }
        $stmt = $this->db->prepare("SELECT a.id arsiv_id, a.donem_id, a.surum_no, a.kapatan_adi, a.manifest_hash,
                   a.onceki_arsiv_hash, a.created_at kapanis_tarihi, d.bordro_personel_id, d.personel_id,
                   d.dokum_hash, d.snapshot_json,
                   bd.donem_adi, bd.baslangic_tarihi, bd.bitis_tarihi, p.adi_soyadi
            FROM bordro_kapanis_arsiv a
            INNER JOIN bordro_kapanis_arsiv_dokum d ON d.arsiv_id = a.id
            INNER JOIN bordro_donemi bd ON bd.id = a.donem_id
            INNER JOIN personel p ON p.id = d.personel_id
            WHERE a.firma_id = :firma_id" . $donemFilter . "
            ORDER BY bd.baslangic_tarihi, a.surum_no, p.adi_soyadi");
        $stmt->execute($params);
        $documents = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['archive_status'] = 'Mühürlü Arşiv';
            $documents[] = $row;
        }

        $stmt = $this->db->prepare("SELECT NULL arsiv_id, bd.id donem_id, NULL surum_no, NULL kapatan_adi,
                   NULL manifest_hash, NULL onceki_arsiv_hash, NULL kapanis_tarihi, NULL dokum_hash,
                   bp.id bordro_personel_id, bp.personel_id,
                   JSON_OBJECT('id', bp.id, 'personel_id', bp.personel_id, 'brut_maas', bp.brut_maas,
                     'net_maas', bp.net_maas, 'sgk_isci', bp.sgk_isci, 'issizlik_isci', bp.issizlik_isci,
                     'gelir_vergisi', bp.gelir_vergisi, 'damga_vergisi', bp.damga_vergisi,
                     'sgk_isveren', bp.sgk_isveren, 'issizlik_isveren', bp.issizlik_isveren,
                     'toplam_maliyet', bp.toplam_maliyet, 'kumulatif_matrah', bp.kumulatif_matrah,
                     'calisan_gun', bp.calisan_gun, 'fazla_mesai_saat', bp.fazla_mesai_saat,
                     'fazla_mesai_tutar', bp.fazla_mesai_tutar, 'kesinti_tutar', bp.kesinti_tutar,
                     'prim_tutar', bp.prim_tutar,
                     'banka_odemesi', bp.banka_odemesi, 'sodexo_odemesi', bp.sodexo_odemesi,
                     'diger_odeme', bp.diger_odeme, 'elden_odeme', bp.elden_odeme,
                     'dagitim_manuel', bp.dagitim_manuel, 'hesaplama_tarihi', bp.hesaplama_tarihi,
                     'hesaplayan_id', bp.hesaplayan_id,
                     'hesaplayan_ad_soyad', bp.hesaplayan_ad_soyad, 'hesaplama_detay', bp.hesaplama_detay) snapshot_json,
                   bd.donem_adi, bd.baslangic_tarihi, bd.bitis_tarihi, p.adi_soyadi
            FROM bordro_personel bp
            INNER JOIN bordro_donemi bd ON bd.id = bp.donem_id
            INNER JOIN personel p ON p.id = bp.personel_id
            WHERE bd.firma_id = :firma_id AND bd.kapali_mi = 1 AND bd.silinme_tarihi IS NULL" . $donemFilter . "
              AND bp.silinme_tarihi IS NULL
              AND NOT EXISTS (SELECT 1 FROM bordro_kapanis_arsiv a WHERE a.firma_id = bd.firma_id AND a.donem_id = bd.id)
            ORDER BY bd.baslangic_tarihi, p.adi_soyadi");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['archive_status'] = 'Eski / Mühürsüz Kayıt';
            $documents[] = $row;
        }
        return $documents;
    }
}
