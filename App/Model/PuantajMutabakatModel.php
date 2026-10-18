<?php

namespace App\Model;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use App\Helper\EkipHelper;

class PuantajMutabakatModel extends Model
{
    public const START_PERIOD = '2026-10';
    protected $table = 'puantaj_mutabakat_donem';

    public function activeCompanyIds(): array
    {
        $stmt=$this->db->prepare('SELECT id FROM firmalar WHERE silinme_tarihi IS NULL ORDER BY id');
        $stmt->execute();
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function acquireLock(string $name): bool
    {
        $stmt=$this->db->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$name]);
        return (int)$stmt->fetchColumn()===1;
    }

    public function releaseLock(string $name): void
    {
        $stmt=$this->db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$name]);
    }

    public function isCheckComplete(int $periodId,int $checkDay): bool
    {
        $period=$this->db->prepare('SELECT donem FROM puantaj_mutabakat_donem WHERE id=?');
        $period->execute([$periodId]); $value=(string)$period->fetchColumn();
        if ($value==='') return false;
        [, $end]=$this->periodRange($value);
        $expected=(int)(new DateTimeImmutable($end))->format('j')*4;
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM puantaj_mutabakat_calisma WHERE donem_id=? AND kontrol_gunu=? AND durum='basarili'");
        $stmt->execute([$periodId,$checkDay]);
        return (int)$stmt->fetchColumn()===$expected;
    }

    public function getOrCreatePeriod(int $firmaId, string $period): int
    {
        $this->assertPeriod($period);
        $stmt = $this->db->prepare(
            "INSERT INTO puantaj_mutabakat_donem (firma_id, donem) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), updated_at=updated_at"
        );
        $stmt->execute([$firmaId, $period]);
        return (int) $this->db->lastInsertId();
    }

    public function createSnapshot(int $firmaId, string $period): array
    {
        if ($period < self::START_PERIOD) throw new RuntimeException('Mutabakat başlangıcı Ekim 2026 dönemidir.');
        $periodId = $this->getOrCreatePeriod($firmaId, $period);
        $existing = $this->db->prepare('SELECT snapshot_hash FROM puantaj_mutabakat_donem WHERE id=?');
        $existing->execute([$periodId]);
        $hash = (string) $existing->fetchColumn();
        if ($hash !== '') {
            return ['period_id' => $periodId, 'manifest_hash' => $hash, 'created' => false];
        }

        [$start, $end] = $this->periodRange($period);
        $items = array_merge(
            $this->localReadingItems($firmaId, $start, $end),
            $this->localCutItems($firmaId, $start, $end),
            $this->localMeterItems($firmaId, $start, $end)
        );
        usort($items, static fn(array $a, array $b): int => strcmp($a['kaynak'].'|'.$a['tarih'].'|'.$a['anahtar'], $b['kaynak'].'|'.$b['tarih'].'|'.$b['anahtar']));
        $manifest = hash('sha256', json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT snapshot_hash FROM puantaj_mutabakat_donem WHERE id=? FOR UPDATE');
            $lock->execute([$periodId]);
            if ((string) $lock->fetchColumn() !== '') {
                $this->db->rollBack();
                return $this->createSnapshot($firmaId, $period);
            }
            $insert = $this->db->prepare(
                'INSERT INTO puantaj_mutabakat_snapshot
                 (donem_id,kaynak,islem_tarihi,kaynak_anahtar,miktar,acik_miktar,veri_hash,payload_json)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            foreach ($items as $item) {
                $payload = json_encode($item['payload'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
                $insert->execute([$periodId, $item['kaynak'], $item['tarih'], $item['anahtar'], $item['miktar'], $item['acik'], hash('sha256', $payload), $payload]);
            }
            $update = $this->db->prepare(
                "UPDATE puantaj_mutabakat_donem SET snapshot_hash=?, snapshot_tarihi=NOW(), durum='on_kontrol' WHERE id=?"
            );
            $update->execute([$manifest, $periodId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return ['period_id' => $periodId, 'manifest_hash' => $manifest, 'created' => true, 'item_count' => count($items)];
    }

    public function beginRun(int $periodId, int $checkDay, string $source, string $date): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO puantaj_mutabakat_calisma
             (donem_id,kontrol_gunu,kaynak,islem_tarihi,durum,baslama_tarihi)
             VALUES (?,?,?,?, 'calisiyor', NOW())
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), durum='calisiyor', hata_mesaji=NULL, baslama_tarihi=NOW(), bitis_tarihi=NULL"
        );
        $stmt->execute([$periodId, $checkDay, $source, $date]);
        $runId = (int) $this->db->lastInsertId();
        $clear = $this->db->prepare('UPDATE puantaj_mutabakat_guncel SET is_active=0,deleted_at=NOW() WHERE calisma_id=? AND is_active=1');
        $clear->execute([$runId]);
        return $runId;
    }

    public function snapshotItemCount(int $periodId,string $source,string $date): int
    {
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM puantaj_mutabakat_snapshot WHERE donem_id=? AND kaynak=? AND islem_tarihi=?');
        $stmt->execute([$periodId,$source,$date]);
        return (int)$stmt->fetchColumn();
    }

    public function storeCurrentItems(int $runId, array $items): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO puantaj_mutabakat_guncel
             (calisma_id,kaynak_anahtar,miktar,acik_miktar,veri_hash,payload_json)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE miktar=VALUES(miktar),acik_miktar=VALUES(acik_miktar),veri_hash=VALUES(veri_hash),payload_json=VALUES(payload_json),is_active=1,deleted_at=NULL'
        );
        foreach ($items as $item) {
            $payload = json_encode($item['payload'], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            $stmt->execute([$runId, $item['anahtar'], $item['miktar'], $item['acik'], hash('sha256', $payload), $payload]);
        }
    }

    public function finishRun(int $runId, int $count): void
    {
        $stmt = $this->db->prepare("UPDATE puantaj_mutabakat_calisma SET durum='basarili', kayit_sayisi=?, bitis_tarihi=NOW() WHERE id=?");
        $stmt->execute([$count, $runId]);
    }

    public function failRun(int $runId, string $message, int $offset = 0): void
    {
        $stmt = $this->db->prepare("UPDATE puantaj_mutabakat_calisma SET durum='hata', son_offset=?, hata_mesaji=?, bitis_tarihi=NOW() WHERE id=?");
        $stmt->execute([$offset, mb_substr($message, 0, 60000), $runId]);
    }

    public function compareRun(int $runId): int
    {
        $meta = $this->db->prepare('SELECT donem_id,kontrol_gunu,kaynak,islem_tarihi FROM puantaj_mutabakat_calisma WHERE id=? AND durum=?');
        $meta->execute([$runId, 'basarili']);
        $run = $meta->fetch(PDO::FETCH_ASSOC);
        if (!$run) throw new RuntimeException('Başarılı mutabakat çalışması bulunamadı.');

        $snap = $this->fetchMap(
            'SELECT kaynak_anahtar,miktar,acik_miktar,veri_hash,payload_json FROM puantaj_mutabakat_snapshot WHERE donem_id=? AND kaynak=? AND islem_tarihi=?',
            [(int)$run['donem_id'], $run['kaynak'], $run['islem_tarihi']]
        );
        $current = $this->fetchMap(
            'SELECT kaynak_anahtar,miktar,acik_miktar,veri_hash,payload_json FROM puantaj_mutabakat_guncel WHERE calisma_id=? AND is_active=1',
            [$runId]
        );
        $keys = array_unique(array_merge(array_keys($snap), array_keys($current)));

        $this->db->beginTransaction();
        try {
            $clear = $this->db->prepare("UPDATE puantaj_mutabakat_fark SET is_active=0,deleted_at=NOW() WHERE donem_id=? AND kontrol_gunu=? AND kaynak=? AND islem_tarihi=? AND durum='acik' AND is_active=1");
            $clear->execute([(int)$run['donem_id'], (int)$run['kontrol_gunu'], $run['kaynak'], $run['islem_tarihi']]);
            $insert = $this->db->prepare(
                'INSERT INTO puantaj_mutabakat_fark
                 (donem_id,kontrol_gunu,kaynak,islem_tarihi,kaynak_anahtar,fark_tipi,eski_miktar,yeni_miktar,eski_payload_json,yeni_payload_json)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   fark_tipi=IF(durum="uygulandi",fark_tipi,VALUES(fark_tipi)),
                   eski_miktar=IF(durum="uygulandi",eski_miktar,VALUES(eski_miktar)),
                   yeni_miktar=IF(durum="uygulandi",yeni_miktar,VALUES(yeni_miktar)),
                   eski_payload_json=IF(durum="uygulandi",eski_payload_json,VALUES(eski_payload_json)),
                   yeni_payload_json=IF(durum="uygulandi",yeni_payload_json,VALUES(yeni_payload_json)),
                   is_active=IF(durum="uygulandi",is_active,1),deleted_at=IF(durum="uygulandi",deleted_at,NULL)'
            );
            $count = 0;
            foreach ($keys as $key) {
                $old = $snap[$key] ?? null;
                $new = $current[$key] ?? null;
                $type = self::classifyDifference($old,$new);
                if (!$type) continue;
                $insert->execute([(int)$run['donem_id'], (int)$run['kontrol_gunu'], $run['kaynak'], $run['islem_tarihi'], $key, $type,
                    (float)($old['miktar'] ?? 0), (float)($new['miktar'] ?? 0), $old['payload_json'] ?? null, $new['payload_json'] ?? null]);
                $count++;
            }
            $this->db->commit();
            return $count;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function finalizePeriod(int $periodId, int $checkDay): array
    {
        $periodStmt = $this->db->prepare('SELECT donem FROM puantaj_mutabakat_donem WHERE id=?');
        $periodStmt->execute([$periodId]);
        $period = (string)$periodStmt->fetchColumn();
        [$start, $end] = $this->periodRange($period);
        $expectedDays = (int)(new DateTimeImmutable($end))->format('j') * 4;
        $ok = $this->db->prepare("SELECT COUNT(*) FROM puantaj_mutabakat_calisma WHERE donem_id=? AND kontrol_gunu=? AND durum='basarili'");
        $ok->execute([$periodId, $checkDay]);
        $missing = max(0, $expectedDays - (int)$ok->fetchColumn());
        $diff = $this->db->prepare("SELECT COUNT(*) FROM puantaj_mutabakat_fark WHERE donem_id=? AND kontrol_gunu=? AND durum='acik' AND is_active=1");
        $diff->execute([$periodId, $checkDay]);
        $open = (int)$diff->fetchColumn();
        $isFinal = $checkDay === 3;
        $status = $missing > 0 ? 'hata' : ($open > 0 ? 'fark_var' : ($isFinal ? 'mutabik' : 'on_kontrol'));
        $stmt = $this->db->prepare(
            'UPDATE puantaj_mutabakat_donem SET durum=?, acik_fark_sayisi=?, eksik_gun_sayisi=?, son_kontrol_tarihi=NOW(), final_kontrol_tarihi=IF(?,NOW(),final_kontrol_tarihi) WHERE id=?'
        );
        $stmt->execute([$status, $open, $missing, $isFinal ? 1 : 0, $periodId]);
        return ['status'=>$status, 'open_differences'=>$open, 'missing_runs'=>$missing, 'final'=>$isFinal];
    }

    public static function classifyDifference(?array $old,?array $new): ?string
    {
        if ($old && !$new) return 'silinmis';
        if (!$old && $new) return 'eklenmis';
        if (!$old || !$new) return null;
        if ((float)$new['miktar'] < (float)$old['miktar']) return 'miktar_azalmis';
        if ((float)$new['miktar'] > (float)$old['miktar']) return 'miktar_artmis';
        if ((string)$new['veri_hash'] !== (string)$old['veri_hash']) return 'alan_degismis';
        return null;
    }

    public function closureBlockReason(int $firmaId, string $startDate, string $endDate): ?string
    {
        $period = substr($startDate, 0, 7);
        if ($period < self::START_PERIOD) return null;
        $stmt = $this->db->prepare('SELECT durum,acik_fark_sayisi,eksik_gun_sayisi,final_kontrol_tarihi,yeniden_hesaplama_gerekli FROM puantaj_mutabakat_donem WHERE firma_id=? AND donem=?');
        $stmt->execute([$firmaId, $period]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 'Bu bordro dönemi için puantaj mutabakat snapshotı bulunmuyor.';
        if ($row['durum'] !== 'mutabik' || empty($row['final_kontrol_tarihi'])) {
            return sprintf('Puantaj mutabakatı tamamlanmadı (durum: %s, açık fark: %d, eksik kontrol: %d).', $row['durum'], $row['acik_fark_sayisi'], $row['eksik_gun_sayisi']);
        }
        if ((int)$row['yeniden_hesaplama_gerekli']===1) return 'Mutabakat farkı uygulandı; dönemdeki tüm personel bordroları yeniden hesaplanmalıdır.';
        return null;
    }

    public function refreshRecalculationState(int $firmaId,int $bordroPeriodId,string $period): void
    {
        $stmt=$this->db->prepare(
            "SELECT COUNT(*) FROM bordro_personel bp
             WHERE bp.donem_id=? AND bp.silinme_tarihi IS NULL AND
             (bp.hesaplama_tarihi IS NULL OR bp.hesaplama_tarihi < COALESCE((SELECT MAX(f.cozum_tarihi) FROM puantaj_mutabakat_fark f INNER JOIN puantaj_mutabakat_donem d ON d.id=f.donem_id WHERE d.firma_id=? AND d.donem=? AND f.durum='uygulandi'),'1000-01-01'))"
        );
        $stmt->execute([$bordroPeriodId,$firmaId,$period]);
        if ((int)$stmt->fetchColumn()===0) {
            $update=$this->db->prepare('UPDATE puantaj_mutabakat_donem SET yeniden_hesaplama_gerekli=0 WHERE firma_id=? AND donem=?');
            $update->execute([$firmaId,$period]);
        }
    }

    public function ajaxList(array $params, int $firmaId): array
    {
        $periodId = (int)($params['period_id'] ?? 0);
        $start=max(0,(int)($params['start']??0)); $length=min(100,max(10,(int)($params['length']??25)));
        $search=trim((string)($params['search']['value']??''));
        $where='d.firma_id=? AND f.is_active=1 AND (?=0 OR f.donem_id=?)'; $bind=[$firmaId,$periodId,$periodId];
        if ($search!=='') { $where.=' AND (f.fark_tipi LIKE ? OR f.kaynak LIKE ? OR f.islem_tarihi LIKE ? OR f.durum LIKE ?)'; $like='%'.$search.'%'; array_push($bind,$like,$like,$like,$like); }
        $count=$this->db->prepare("SELECT COUNT(*) FROM puantaj_mutabakat_fark f INNER JOIN puantaj_mutabakat_donem d ON d.id=f.donem_id WHERE $where");
        $count->execute($bind); $filtered=(int)$count->fetchColumn();
        $stmt = $this->db->prepare(
            "SELECT f.*, d.donem FROM puantaj_mutabakat_fark f
             INNER JOIN puantaj_mutabakat_donem d ON d.id=f.donem_id
             WHERE $where ORDER BY f.islem_tarihi DESC,f.id DESC LIMIT $start,$length"
        );
        $stmt->execute($bind);
        $total=$this->db->prepare('SELECT COUNT(*) FROM puantaj_mutabakat_fark f INNER JOIN puantaj_mutabakat_donem d ON d.id=f.donem_id WHERE d.firma_id=? AND f.is_active=1');
        $total->execute([$firmaId]);
        return ['draw'=>(int)($params['draw']??0),'recordsTotal'=>(int)$total->fetchColumn(),'recordsFiltered'=>$filtered,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function summary(int $firmaId): object
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) toplam, SUM(durum='mutabik') mutabik, SUM(durum='fark_var') farkli, COALESCE(SUM(acik_fark_sayisi),0) acik_fark FROM puantaj_mutabakat_donem WHERE firma_id=?");
        $stmt->execute([$firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: (object)[];
    }

    public function reportRows(int $periodId,int $checkDay): array
    {
        $stmt=$this->db->prepare("SELECT kaynak,islem_tarihi,fark_tipi,eski_miktar,yeni_miktar,durum FROM puantaj_mutabakat_fark WHERE donem_id=? AND kontrol_gunu=? AND is_active=1 ORDER BY islem_tarihi,kaynak,id");
        $stmt->execute([$periodId,$checkDay]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function applyDifference(int $differenceId, int $firmaId, int $userId, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 5) throw new RuntimeException('Çözüm gerekçesi en az 5 karakter olmalıdır.');
        $stmt=$this->db->prepare(
            "SELECT f.*,d.firma_id FROM puantaj_mutabakat_fark f
             INNER JOIN puantaj_mutabakat_donem d ON d.id=f.donem_id
             WHERE f.id=? AND d.firma_id=? AND f.durum='acik' AND f.is_active=1"
        );
        $stmt->execute([$differenceId,$firmaId]);
        $diff=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$diff) throw new RuntimeException('Açık mutabakat farkı bulunamadı.');
        $payload=json_decode((string)($diff['yeni_payload_json']??''),true) ?: [];

        $this->db->beginTransaction();
        try {
            if ($diff['kaynak']==='okuma') $this->applyReading($diff,$payload,$firmaId);
            elseif ($diff['kaynak']==='sayac') $this->applyMeter($diff,$payload,$firmaId);
            else $this->applyCut($diff,$payload,$firmaId);
            $done=$this->db->prepare("UPDATE puantaj_mutabakat_fark SET durum='uygulandi',cozen_kullanici_id=?,cozum_gerekcesi=?,cozum_tarihi=NOW() WHERE id=?");
            $done->execute([$userId,trim($reason),$differenceId]);
            $refresh=$this->db->prepare(
                "UPDATE puantaj_mutabakat_donem d SET
                 d.acik_fark_sayisi=(SELECT COUNT(*) FROM puantaj_mutabakat_fark f WHERE f.donem_id=d.id AND f.durum='acik' AND f.is_active=1),
                 d.yeniden_hesaplama_gerekli=1,
                 d.durum=IF((SELECT COUNT(*) FROM puantaj_mutabakat_fark f WHERE f.donem_id=d.id AND f.durum='acik' AND f.is_active=1)=0 AND d.final_kontrol_tarihi IS NOT NULL,'mutabik','fark_var')
                 WHERE d.id=?"
            );
            $refresh->execute([(int)$diff['donem_id']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function applyReading(array $diff,array $payload,int $firmaId): void
    {
        $key=$diff['kaynak_anahtar']; $date=$diff['islem_tarihi'];
        $del=$this->db->prepare("UPDATE endeks_okuma SET silinme_tarihi=NOW() WHERE firma_id=? AND tarih=? AND SUBSTRING_INDEX(islem_id,'_',1)=? AND silinme_tarihi IS NULL");
        $del->execute([$firmaId,$date,$key]);
        $amount=(float)$diff['yeni_miktar'];
        if ($amount<=0) return;
        [$personIds,$teamId]=$this->resolvePeople($firmaId,$date,(string)($payload['kullanici_adi']??''),true);
        if (!$personIds) $personIds=[0];
        $share=$amount/count($personIds);
        $ins=$this->db->prepare(
            'INSERT INTO endeks_okuma (islem_id,personel_id,ekip_kodu_id,firma_id,bolge,kullanici_adi,okunan_gun_sayisi,okunan_abone_sayisi,ort_okunan_abone_sayisi_gunluk,okuma_performansi,tarih,defter,sayac_durum,aciklama)
             VALUES (?,?,?,?,?,?,1,?,?,100,?,?,?,?)'
        );
        foreach ($personIds as $pid) $ins->execute([$key.'_'.$pid,$pid,$teamId,$firmaId,$payload['bolge']??'', $payload['kullanici_adi']??'', $share,$share,$date,$payload['defter']??'', $payload['sayac_durum']??'', 'Mutabakat güncel verisi']);
    }

    private function applyCut(array $diff,array $payload,int $firmaId): void
    {
        $key=$diff['kaynak_anahtar']; $date=$diff['islem_tarihi'];
        $del=$this->db->prepare("UPDATE yapilan_isler SET silinme_tarihi=NOW() WHERE firma_id=? AND tarih=? AND SUBSTRING_INDEX(islem_id,'_',1)=? AND silinme_tarihi IS NULL");
        $del->execute([$firmaId,$date,$key]);
        $amount=(float)$diff['yeni_miktar'];
        if ($amount<=0) return;
        [$personIds,$teamId]=$this->resolvePeople($firmaId,$date,(string)($payload['ekip']??''),false);
        if (!$teamId || !$personIds) throw new RuntimeException('Güncel kesme verisi için ekip/personel eşleşmesi bulunamadı.');
        $type=(string)($payload['is_emri_tipi']??''); $result=(string)($payload['is_emri_sonucu']??'');
        $resultStmt=$this->db->prepare("SELECT id FROM tanimlamalar WHERE firma_id=? AND grup='is_turu' AND tur_adi=? AND is_emri_sonucu=? AND silinme_tarihi IS NULL LIMIT 1");
        $resultStmt->execute([$firmaId,$type,$result]);
        $resultId=(int)$resultStmt->fetchColumn();
        if (!$resultId) throw new RuntimeException('Güncel kesme verisinin iş sonucu tanımı bulunamadı.');
        $share=$amount/count($personIds); $open=(float)($diff['yeni_miktar']>0 ? 0 : 0);
        $newPayload=json_decode((string)$diff['yeni_payload_json'],true)?:[];
        $open=(float)($newPayload['acik']??0)/count($personIds);
        $ins=$this->db->prepare('INSERT INTO yapilan_isler (islem_id,personel_id,ekip_kodu_id,firma_id,is_emri_sonucu_id,is_emri_tipi,ekip_kodu,is_emri_sonucu,sonuclanmis,acik_olanlar,tarih,aciklama) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($personIds as $pid) $ins->execute([$key.'_'.$pid,$pid,$teamId,$firmaId,$resultId,$type,$payload['ekip']??'',$result,$share,$open,$date,'Mutabakat güncel verisi']);
    }

    private function applyMeter(array $diff,array $payload,int $firmaId): void
    {
        $key=$diff['kaynak_anahtar']; $date=$diff['islem_tarihi'];
        $del=$this->db->prepare("UPDATE sayac_degisim SET silinme_tarihi=NOW() WHERE firma_id=? AND tarih=? AND SUBSTRING_INDEX(islem_id,'_',1)=? AND silinme_tarihi IS NULL");
        $del->execute([$firmaId,$date,$key]);
        $amount=(float)$diff['yeni_miktar'];
        if ($amount<=0) return;
        [$personIds,$teamId]=$this->resolvePeople($firmaId,$date,(string)($payload['ekip']??''),false);
        if (!$teamId || !$personIds) throw new RuntimeException('Güncel sayaç verisi için ekip/personel eşleşmesi bulunamadı.');
        $share=$amount/count($personIds);
        $ins=$this->db->prepare('INSERT INTO sayac_degisim (islem_id,firma_id,personel_id,is_sayisi,ekip_kodu_id,isemri_no,abone_no,isemri_sebep,ekip,memur,sonuclandiran_kullanici,bolge,isemri_sonucu,sonuc_aciklama,takilan_sayacno,zimmet_dusuldu,kayit_tarihi,tarih) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($personIds as $pid) $ins->execute([$key.'_'.$pid,$firmaId,$pid,$share,$teamId,$payload['isemri_no']??'',$payload['abone_no']??'',$payload['isemri_sebep']??'',$payload['ekip']??'',$payload['memur']??'',$payload['sonuclandiran_kullanici']??'',$payload['bolge']??'',$payload['isemri_sonucu']??'',$payload['sonuc_aciklama']??'',$payload['takilan_sayacno']??'',0,$payload['kayit_tarihi']??null,$date]);
    }

    private function resolvePeople(int $firmaId,string $date,string $label,bool $allowName): array
    {
        if ($allowName && $label!=='') {
            $p=$this->db->prepare('SELECT id,ekip_no FROM personel WHERE firma_id=? AND adi_soyadi=? AND silinme_tarihi IS NULL');
            $p->execute([$firmaId,$label]); $row=$p->fetch(PDO::FETCH_ASSOC);
            if ($row) return [[(int)$row['id']],(int)$row['ekip_no']];
        }
        $teamNo=EkipHelper::extractTeamNo($label);
        if ($teamNo<=0) return [[],0];
        $team=$this->db->prepare("SELECT id FROM tanimlamalar WHERE firma_id=? AND grup='ekip_kodu' AND silinme_tarihi IS NULL AND tur_adi REGEXP ? LIMIT 1");
        $team->execute([$firmaId,'[İI]?P-?[[:space:]]*'.$teamNo.'([^0-9]|$)']);
        $teamId=(int)$team->fetchColumn();
        if (!$teamId) return [[],0];
        $hist=$this->db->prepare('SELECT personel_id FROM personel_ekip_gecmisi WHERE firma_id=? AND ekip_kodu_id=? AND baslangic_tarihi<=? AND (bitis_tarihi IS NULL OR bitis_tarihi>=?)');
        $hist->execute([$firmaId,$teamId,$date,$date]);
        $ids=array_map('intval',$hist->fetchAll(PDO::FETCH_COLUMN));
        if (!$ids) {
            $fallback=$this->db->prepare('SELECT id FROM personel WHERE firma_id=? AND ekip_no=? AND silinme_tarihi IS NULL');
            $fallback->execute([$firmaId,$teamId]); $ids=array_map('intval',$fallback->fetchAll(PDO::FETCH_COLUMN));
        }
        return [array_values(array_unique($ids)),$teamId];
    }

    private function localReadingItems(int $firmaId, string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT tarih, SUBSTRING_INDEX(islem_id,'_',1) anahtar, SUM(okunan_abone_sayisi) miktar,
                    MAX(bolge) bolge,MAX(kullanici_adi) kullanici_adi,MAX(defter) defter,MAX(sayac_durum) sayac_durum,
                    MAX(abone_no) abone_no,MAX(is_emri_no) is_emri_no
             FROM endeks_okuma WHERE firma_id=? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL
             GROUP BY tarih,SUBSTRING_INDEX(islem_id,'_',1)"
        );
        $stmt->execute([$firmaId,$start,$end]);
        $out=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $payload=['bolge'=>$r['bolge'],'kullanici_adi'=>$r['kullanici_adi'],'defter'=>$r['defter'],'sayac_durum'=>$r['sayac_durum']];
            $out[]=['kaynak'=>'okuma','tarih'=>$r['tarih'],'anahtar'=>$r['anahtar'],'miktar'=>(float)$r['miktar'],'acik'=>0.0,'payload'=>$payload];
        }
        return $out;
    }

    private function localCutItems(int $firmaId, string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT y.tarih,SUBSTRING_INDEX(y.islem_id,'_',1) anahtar,SUM(y.sonuclanmis) miktar,SUM(y.acik_olanlar) acik,
                    MAX(y.ekip_kodu) ekip,MAX(y.is_emri_tipi) is_emri_tipi,MAX(y.is_emri_sonucu) is_emri_sonucu,
                    MAX(CASE WHEN tn.rapor_sekmesi='muhurleme' OR y.is_emri_tipi LIKE '%MÜHÜR%' OR y.is_emri_tipi LIKE '%MUHUR%' OR y.is_emri_sonucu LIKE '%MÜHÜR%' OR y.is_emri_sonucu LIKE '%MUHUR%' THEN 1 ELSE 0 END) muhurleme
             FROM yapilan_isler y LEFT JOIN tanimlamalar tn ON tn.id=y.is_emri_sonucu_id
             WHERE y.firma_id=? AND y.tarih BETWEEN ? AND ? AND y.silinme_tarihi IS NULL
             GROUP BY y.tarih,SUBSTRING_INDEX(y.islem_id,'_',1)"
        );
        $stmt->execute([$firmaId,$start,$end]);
        $out=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $payload=['ekip'=>$r['ekip'],'is_emri_tipi'=>$r['is_emri_tipi'],'is_emri_sonucu'=>$r['is_emri_sonucu'],'acik'=>(float)$r['acik']];
            $source=(int)$r['muhurleme']===1?'muhurleme':'kesme';
            $out[]=['kaynak'=>$source,'tarih'=>$r['tarih'],'anahtar'=>$r['anahtar'],'miktar'=>(float)$r['miktar'],'acik'=>(float)$r['acik'],'payload'=>$payload];
        }
        return $out;
    }

    private function localMeterItems(int $firmaId,string $start,string $end): array
    {
        $stmt=$this->db->prepare("SELECT tarih,SUBSTRING_INDEX(islem_id,'_',1) anahtar,SUM(is_sayisi) miktar,MAX(ekip) ekip,MAX(isemri_no) isemri_no,MAX(abone_no) abone_no,MAX(isemri_sebep) isemri_sebep,MAX(memur) memur,MAX(sonuclandiran_kullanici) sonuclandiran_kullanici,MAX(bolge) bolge,MAX(isemri_sonucu) isemri_sonucu,MAX(sonuc_aciklama) sonuc_aciklama,MAX(takilan_sayacno) takilan_sayacno,MAX(kayit_tarihi) kayit_tarihi FROM sayac_degisim WHERE firma_id=? AND tarih BETWEEN ? AND ? AND silinme_tarihi IS NULL GROUP BY tarih,SUBSTRING_INDEX(islem_id,'_',1)");
        $stmt->execute([$firmaId,$start,$end]); $out=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){$payload=$r;unset($payload['tarih'],$payload['anahtar'],$payload['miktar']);$out[]=['kaynak'=>'sayac','tarih'=>$r['tarih'],'anahtar'=>$r['anahtar'],'miktar'=>(float)$r['miktar'],'acik'=>0.0,'payload'=>$payload];}
        return $out;
    }

    private function fetchMap(string $sql, array $params): array
    {
        $stmt=$this->db->prepare($sql); $stmt->execute($params); $map=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $map[$row['kaynak_anahtar']]=$row;
        return $map;
    }

    private function periodRange(string $period): array
    {
        $this->assertPeriod($period);
        $start=$period.'-01';
        return [$start,(new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d')];
    }

    private function assertPeriod(string $period): void
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$period)) throw new RuntimeException('Geçersiz mutabakat dönemi.');
    }
}
