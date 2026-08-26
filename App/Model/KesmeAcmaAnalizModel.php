<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class KesmeAcmaAnalizModel extends Model
{
    private const OLUMLU = [
        'ÖDEME YAPTIRILDI', 'APARATLA KESİM YAPILDI', 'MÜHÜR VE CONTA',
        'APARAT KIRMA ÜCRETİ', 'SAYAÇ KULLANIMA AÇILDI',
    ];

    private const KESIM = [
        'APARATLA KESİM YAPILDI', 'DİLEKÇELİ KESİM', 'ABONESİZ KESİM',
        'MÜHÜR VE CONTA', 'MÜHÜR VE TAPA', 'MÜHÜR TAKILDI', 'YOLDAN KAPAMA',
    ];

    private const ANALIZ_KURAL_KODLARI = [
        'odeme_ust','odeme_alt','odeme_min_temas','kapali_ust','kapali_alt','kapali_min_beklenen',
        'toplu_kat','toplu_min','pazar_esik','ani_yuzde','ani_min','sonucsuz_yuzde','eksik_gun',
        'uyari_gun','mahalle_min_is','mahalle_min_temas',
    ];

    private const KURAL_TIPLERI = [
        'nobet_havuz_kodlari'=>'liste', 'nobet_yeni_bekleme'=>'sayi', 'nobet_ay_basi_min'=>'sayi',
        'nobet_telefon_personelleri'=>'liste', 'nobet_telefon_sabit'=>'harita', 'nobet_telefon_dongu'=>'sayi',
        'nobet_ilce_secim'=>'metin', 'nobet_arac_kisitli_ekipler'=>'liste', 'nobet_ilce_merkez'=>'metin',
        'mahalle_mesaj_bekleme'=>'sayi', 'mahalle_ilce_dongu'=>'metin', 'mahalle_ust_uste'=>'metin',
        'odeme_gecerlilik_gun'=>'sayi', 'odeme_agir_suphe_dk'=>'sayi', 'odeme_acan_ayrimi'=>'metin',
        'odeme_ust'=>'sayi', 'odeme_alt'=>'sayi', 'odeme_min_temas'=>'sayi', 'kapali_ust'=>'sayi',
        'kapali_alt'=>'sayi', 'kapali_min_beklenen'=>'sayi', 'toplu_kat'=>'sayi', 'toplu_min'=>'sayi',
        'pazar_esik'=>'sayi', 'ani_yuzde'=>'sayi', 'ani_min'=>'sayi', 'sonucsuz_yuzde'=>'sayi',
        'eksik_gun'=>'sayi', 'uyari_gun'=>'sayi', 'mahalle_min_is'=>'sayi', 'mahalle_min_temas'=>'sayi',
    ];

    public function __construct()
    {
        parent::__construct('yapilan_isler');
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    private function sonuc(string $deger): string
    {
        $deger = mb_strtoupper(trim($deger), 'UTF-8');
        return rtrim($deger, ". \t\n\r\0\x0B");
    }

    public function kurallar(): array
    {
        $kurallar = [];
        $stmt = $this->db->prepare('SELECT kural_kodu, deger FROM kesme_acma_kural_degeri WHERE firma_id = ?');
        $stmt->execute([$this->firmaId()]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            if (!isset(self::KURAL_TIPLERI[$satir['kural_kodu']])) {
                continue;
            }
            $cozulmus = json_decode($satir['deger'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException($satir['kural_kodu'] . ' kuralının değeri geçersiz JSON biçiminde.');
            }
            $kurallar[$satir['kural_kodu']] = is_numeric($cozulmus) ? (float) $cozulmus : $cozulmus;
        }
        $eksik = array_diff(array_keys(self::KURAL_TIPLERI), array_keys($kurallar));
        if ($eksik) {
            throw new \RuntimeException('Kesme/açma kuralları eksik (' . implode(', ', $eksik) . '); sql/kesme_acma_analiz.sql uygulanmalıdır.');
        }
        return $kurallar;
    }

    public function kuralKaydet(string $kod, $deger, int $userId): void
    {
        $tip = self::KURAL_TIPLERI[$kod] ?? null;
        if ($tip === null) {
            throw new \InvalidArgumentException('Geçersiz kural veya değer.');
        }
        if ($tip === 'sayi') {
            if (!is_numeric($deger) || (float) $deger < 0) throw new \InvalidArgumentException('Kural değeri sıfırdan küçük olamaz.');
            $deger = (float) $deger;
        } else {
            if (is_string($deger)) {
                $cozulmus = json_decode($deger, true);
                if (json_last_error() === JSON_ERROR_NONE) $deger = $cozulmus;
            }
            if ($tip === 'liste' && !is_array($deger)) throw new \InvalidArgumentException('Kural değeri liste olmalıdır.');
            if ($tip === 'harita' && (!is_array($deger) || array_is_list($deger))) throw new \InvalidArgumentException('Sabit gün dağılımı geçersiz.');
            if ($tip === 'metin' && (!is_string($deger) || trim($deger) === '')) throw new \InvalidArgumentException('Kural değeri boş bırakılamaz.');
        }
        $stmt = $this->db->prepare('INSERT INTO kesme_acma_kural_degeri
            (firma_id,kural_kodu,deger,guncelleyen_id,guncelleme_ts) VALUES (?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE deger=VALUES(deger),guncelleyen_id=VALUES(guncelleyen_id),guncelleme_ts=NOW()');
        $json = json_encode($deger, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt->execute([$this->firmaId(), $kod, $json, $userId]);
        $this->gunlukYaz('kural', "$kod değeri $json olarak güncellendi", $userId);
    }

    private function hamGunluk(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT y.personel_id, COALESCE(NULLIF(p.adi_soyadi,''),'Personel belirtilmemiş') personel,
                y.ekip_kodu_id, y.tarih, TRIM(y.is_emri_sonucu) sonuc,
                SUM(y.sonuclanmis) adet, SUM(y.acik_olanlar) acik
            FROM yapilan_isler y
            LEFT JOIN personel p ON p.id=y.personel_id
            WHERE y.firma_id=? AND y.tarih BETWEEN ? AND ? AND y.silinme_tarihi IS NULL
            GROUP BY y.personel_id,p.adi_soyadi,y.ekip_kodu_id,y.tarih,TRIM(y.is_emri_sonucu)
            ORDER BY y.tarih");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function atamalar(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT a.ekip_id,a.baslangic,COALESCE(a.bitis,?) bitis,m.id mahalle_id,m.ad mahalle
            FROM ekip_mahalle_atama a INNER JOIN mahalle m ON m.id=a.mahalle_id AND m.firma_id=a.firma_id
            WHERE a.firma_id=? AND a.baslangic<=? AND COALESCE(a.bitis,?)>=?
            ORDER BY a.baslangic");
        $stmt->execute([$bitis, $this->firmaId(), $bitis, $bitis, $baslangic]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function mahalleBul(array $atamalar, int $ekipId, string $tarih): ?array
    {
        $bulunan = null;
        foreach ($atamalar as $atama) {
            if ((int) $atama['ekip_id'] === $ekipId && $atama['baslangic'] <= $tarih && $atama['bitis'] >= $tarih) {
                $bulunan = $atama;
            }
        }
        return $bulunan;
    }

    private function ozetle(array $satirlar, array $atamalar): array
    {
        $gunler = $kisiler = $mahalleler = $sonuclar = [];
        foreach ($satirlar as $satir) {
            $pid = (int) $satir['personel_id'];
            $tarih = $satir['tarih'];
            $sonuc = $this->sonuc((string) $satir['sonuc']);
            $adet = (int) $satir['adet'];
            $acik = (int) $satir['acik'];
            $olumlu = in_array($sonuc, self::OLUMLU, true) ? $adet : 0;
            $odeme = $sonuc === 'ÖDEME YAPTIRILDI' ? $adet : 0;
            $kesim = in_array($sonuc, self::KESIM, true) ? $adet : 0;
            $kapali = str_contains($sonuc, 'KAPALI') ? $adet : 0;

            if (!isset($kisiler[$pid])) {
                $kisiler[$pid] = ['personel_id'=>$pid,'personel'=>$satir['personel'],'toplam'=>0,'olumlu'=>0,
                    'odeme'=>0,'kesim'=>0,'kapali'=>0,'acik'=>0,'gunler'=>[],'olumlu_gunler'=>[],'odeme_gunler'=>[],
                    'gun_detay'=>[],'gun_mahalleler'=>[],
                    'sonuclar'=>[],'mahalleler'=>[]];
            }
            foreach ([&$kisiler[$pid], &$gunler[$tarih]] as &$hedef) {
                if (!$hedef) $hedef = ['toplam'=>0,'olumlu'=>0,'odeme'=>0,'kesim'=>0,'kapali'=>0,'acik'=>0];
                $hedef['toplam'] += $adet; $hedef['olumlu'] += $olumlu; $hedef['odeme'] += $odeme;
                $hedef['kesim'] += $kesim; $hedef['kapali'] += $kapali; $hedef['acik'] += $acik;
            }
            unset($hedef);
            $kisiler[$pid]['gunler'][$tarih] = ($kisiler[$pid]['gunler'][$tarih] ?? 0) + $adet;
            $kisiler[$pid]['olumlu_gunler'][$tarih] = ($kisiler[$pid]['olumlu_gunler'][$tarih] ?? 0) + $olumlu;
            $kisiler[$pid]['odeme_gunler'][$tarih] = ($kisiler[$pid]['odeme_gunler'][$tarih] ?? 0) + $odeme;
            if (!isset($kisiler[$pid]['gun_detay'][$tarih])) $kisiler[$pid]['gun_detay'][$tarih]=['odeme'=>0,'kesim'=>0];
            $kisiler[$pid]['gun_detay'][$tarih]['odeme'] += $odeme;
            $kisiler[$pid]['gun_detay'][$tarih]['kesim'] += $kesim;
            $kisiler[$pid]['sonuclar'][$sonuc] = ($kisiler[$pid]['sonuclar'][$sonuc] ?? 0) + $adet;
            $sonuclar[$sonuc] = ($sonuclar[$sonuc] ?? 0) + $adet;

            $mahalle = $this->mahalleBul($atamalar, (int) $satir['ekip_kodu_id'], $tarih);
            $mid = $mahalle ? (int) $mahalle['mahalle_id'] : 0;
            $mad = $mahalle['mahalle'] ?? 'Eşleşmeyen';
            if (!isset($mahalleler[$mid])) $mahalleler[$mid] = ['mahalle_id'=>$mid,'mahalle'=>$mad,'toplam'=>0,'odeme'=>0,'kesim'=>0,'kapali'=>0];
            $mahalleler[$mid]['toplam'] += $adet; $mahalleler[$mid]['odeme'] += $odeme;
            $mahalleler[$mid]['kesim'] += $kesim; $mahalleler[$mid]['kapali'] += $kapali;
            if ($mid) {
                $kisiler[$pid]['mahalleler'][$mid] = $mad;
                $kisiler[$pid]['gun_mahalleler'][$tarih][$mid] = $mad;
            }
        }
        ksort($gunler); arsort($sonuclar);
        return compact('gunler','kisiler','mahalleler','sonuclar');
    }

    private function q(array $degerler, float $oran): float
    {
        sort($degerler, SORT_NUMERIC);
        if (!$degerler) return 0;
        $pos = (count($degerler)-1)*$oran; $alt=(int)floor($pos); $ust=(int)ceil($pos);
        return $degerler[$alt] + ($degerler[$ust]-$degerler[$alt])*($pos-$alt);
    }

    public function dashboard(string $ay, string $baz = 'olumlu'): array
    {
        $baslangic = $ay . '-01';
        $bitis = min(date('Y-m-t', strtotime($baslangic)), date('Y-m-d'));
        if ($bitis < $baslangic) $bitis = date('Y-m-t', strtotime($baslangic));
        $gecmisBaslangic = date('Y-m-01', strtotime($baslangic . ' -8 months'));
        $oncekiBaslangic = date('Y-m-01', strtotime($baslangic . ' -1 month'));
        $oncekiBitis = date('Y-m-t', strtotime($oncekiBaslangic));
        $atamalar = $this->atamalar($gecmisBaslangic, $bitis);
        $gecmis = $this->ozetle($this->hamGunluk($gecmisBaslangic, $bitis), $atamalar);
        $secili = $this->ozetle($this->hamGunluk($baslangic, $bitis), $atamalar);
        $onceki = $this->ozetle($this->hamGunluk($oncekiBaslangic, $oncekiBitis), $atamalar);
        $kurallar = $this->kurallar();

        $genelToplam=$genelKapali=$genelOdeme=$genelTemas=0;
        foreach ($gecmis['mahalleler'] as &$m) {
            $temas=$m['odeme']+$m['kesim']; $m['kapali_oran']=$m['toplam'] ? $m['kapali']/$m['toplam'] : 0;
            $m['odeme_oran']=$temas ? $m['odeme']/$temas : 0;
            $genelToplam += $m['toplam']; $genelKapali += $m['kapali']; $genelOdeme += $m['odeme']; $genelTemas += $temas;
        }
        unset($m);
        $genelKapOran=$genelToplam?$genelKapali/$genelToplam:0; $genelOdOran=$genelTemas?$genelOdeme/$genelTemas:0;

        $personel=[]; $sinyaller=[];
        foreach ($secili['kisiler'] as $pid=>$k) {
            $calisilan=count(array_filter($k['gunler'])); $temas=$k['odeme']+$k['kesim'];
            $bekKap=0; $bekOd=0;
            foreach ($k['gunler'] as $tarih=>$gunToplam) {
                $gOranKap=$genelKapOran; $gOranOd=$genelOdOran; $oranSayisi=0; $kapToplam=0; $odToplam=0;
                foreach (array_keys($k['gun_mahalleler'][$tarih] ?? []) as $mid) {
                    $mn=$gecmis['mahalleler'][$mid] ?? null;
                    $kapToplam += ($mn && $mn['toplam'] >= $kurallar['mahalle_min_is']) ? $mn['kapali_oran'] : $genelKapOran;
                    $odToplam += ($mn && ($mn['odeme']+$mn['kesim']) >= $kurallar['mahalle_min_temas']) ? $mn['odeme_oran'] : $genelOdOran;
                    $oranSayisi++;
                }
                if($oranSayisi){$gOranKap=$kapToplam/$oranSayisi;$gOranOd=$odToplam/$oranSayisi;}
                $bekKap += $gunToplam*$gOranKap;
                $gunTemas=($k['gun_detay'][$tarih]['odeme']??0)+($k['gun_detay'][$tarih]['kesim']??0);
                $bekOd += $gunTemas*$gOranOd;
            }
            $odSap=$bekOd?$k['odeme']/$bekOd:0; $kapSap=$bekKap?$k['kapali']/$bekKap:0;
            $normalGun=[]; $normalOd=[];
            foreach (($gecmis['kisiler'][$pid]['gunler'] ?? []) as $t=>$v) if ($t<$baslangic && $v>0) $normalGun[]=$v;
            $med=$this->q($normalGun,.5);
            foreach ($k['gunler'] as $t=>$v) if ($v >= $kurallar['toplu_min'] && $med>0 && $v >= $med*$kurallar['toplu_kat'])
                $sinyaller[$pid]['toplu'][]="$t tarihinde $v iş (normal gün " . round($med,1) . ')';
            if ($temas >= $kurallar['odeme_min_temas'] && ($odSap >= $kurallar['odeme_ust'] || $odSap <= $kurallar['odeme_alt']))
                $sinyaller[$pid]['odeme'][]='Gerçekleşen '.$k['odeme'].', mahalle normaline göre beklenen '.round($bekOd);
            if ($bekKap >= $kurallar['kapali_min_beklenen'] && ($kapSap >= $kurallar['kapali_ust'] || $kapSap <= $kurallar['kapali_alt']))
                $sinyaller[$pid]['kapali'][]='Gerçekleşen '.$k['kapali'].', mahalle normaline göre beklenen '.round($bekKap);
            $sonucsuzPay=($k['toplam']+$k['acik']) ? $k['acik']/($k['toplam']+$k['acik'])*100 : 0;
            if ($sonucsuzPay >= $kurallar['sonucsuz_yuzde']) $sinyaller[$pid]['sonucsuz'][]=round($sonucsuzPay).'% sonuçlandırılamamış iş';
            $enUzun=0;$seri=0;for($ts=strtotime($baslangic);$ts<=strtotime($bitis);$ts+=86400){if(date('w',$ts)==0)continue;$t=date('Y-m-d',$ts);$seri=empty($k['gunler'][$t])?$seri+1:0;$enUzun=max($enUzun,$seri);}
            if($enUzun >= $kurallar['eksik_gun']) $sinyaller[$pid]['eksik'][]="$enUzun ardışık iş günü kayıt yok";
            foreach ($k['sonuclar'] as $sonuc=>$adet) {
                $evvel=$onceki['kisiler'][$pid]['sonuclar'][$sonuc] ?? 0;
                if(max($adet,$evvel) >= $kurallar['ani_min'] && $evvel>0 && abs(($adet-$evvel)/$evvel*100) >= $kurallar['ani_yuzde'] && !str_contains($sonuc,'KAPALI'))
                    $sinyaller[$pid]['ani'][]="$sonuc: önceki ay $evvel, bu ay $adet";
            }
            $evvelKisi=$onceki['kisiler'][$pid]??[];$evvelGun=count(array_filter($evvelKisi['gunler']??[]));
            $simdikiBaz=$baz==='olumlu'?$k['olumlu']:$k['toplam'];$evvelBaz=$baz==='olumlu'?($evvelKisi['olumlu']??0):($evvelKisi['toplam']??0);
            $personel[]=['personel_id'=>$pid,'personel'=>$k['personel'],'gun'=>$calisilan,'toplam'=>$k['toplam'],'olumlu'=>$k['olumlu'],
                'gunluk_ortalama'=>$calisilan?round(($baz==='olumlu'?$k['olumlu']:$k['toplam'])/$calisilan,1):0,
                'delta_toplam'=>$evvelBaz?round(($simdikiBaz-$evvelBaz)/$evvelBaz*100,1):null,
                'delta_ortalama'=>$evvelBaz&&$evvelGun?round((($simdikiBaz/max(1,$calisilan))-($evvelBaz/$evvelGun))/($evvelBaz/$evvelGun)*100,1):null,
                'odeme'=>$k['odeme'],'kesim'=>$k['kesim'],'kapali'=>$k['kapali'],'beklenen_odeme'=>round($bekOd,1),'odeme_sapma'=>round($odSap,2),
                'beklenen_kapali'=>round($bekKap,1),'kapali_sapma'=>round($kapSap,2),'sonuc_orani'=>$k['toplam']?round($k['olumlu']/$k['toplam']*100,1):0];
        }
        usort($personel, fn($a,$b)=>($b[$baz==='olumlu'?'olumlu':'toplam']<=>$a[$baz==='olumlu'?'olumlu':'toplam']));
        $uyarilar=$this->uyarilariEsitle($ay,$secili['kisiler'],$sinyaller,$kurallar);
        $toplam=array_sum(array_column($personel,$baz==='olumlu'?'olumlu':'toplam')); $gunSay=count($secili['gunler']);
        $negatif=0;foreach($secili['kisiler'] as $vk)foreach($vk['sonuclar'] as $adet)if($adet<0)$negatif++;
        return ['ay'=>$ay,'baz'=>$baz,'baslangic'=>$baslangic,'bitis'=>$bitis,'metrikler'=>[
            'toplam'=>$toplam,'gunluk_ortalama'=>$gunSay?round($toplam/$gunSay,1):0,
            'sonuc_orani'=>array_sum(array_column($personel,'toplam'))?round(array_sum(array_column($personel,'olumlu'))/array_sum(array_column($personel,'toplam'))*100,1):0,
            'personel'=>count($personel)],'gunluk'=>$secili['gunler'],'personel'=>$personel,'uyarilar'=>$uyarilar,
            'mahalleler'=>array_values($gecmis['mahalleler']),'sonuclar'=>$secili['sonuclar'],'kontrol'=>$this->kontrolListesi($ay),
            'veri_kalitesi'=>['isimsiz_is'=>(int)($secili['kisiler'][0]['toplam']??0),'negatif_sonuc'=>$negatif]];
    }

    private function uyarilariEsitle(string $ay, array $kisiler, array $sinyaller, array $kurallar): array
    {
        $agirlik=['odeme'=>3,'kapali'=>3,'toplu'=>3,'ani'=>2,'sonucsuz'=>2,'eksik'=>1];
        foreach($sinyaller as $pid=>$turler) foreach($turler as $tur=>$detaylar){
            $ozet=$kisiler[$pid]['personel'].' — '.implode(' · ',array_slice($detaylar,0,3));
            $stmt=$this->db->prepare("INSERT INTO kesme_acma_uyari (firma_id,personel_id,ay,tur,puan,ozet,detay)
                VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE puan=VALUES(puan),ozet=VALUES(ozet),detay=VALUES(detay),is_active=1,deleted_at=NULL");
            $stmt->execute([$this->firmaId(),$pid,$ay,$tur,$agirlik[$tur],$ozet,json_encode($detaylar,JSON_UNESCAPED_UNICODE)]);
        }
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM kesme_acma_uyari WHERE firma_id=? AND ay=? AND durum='acik'
            AND yukseltildi_ts IS NULL AND DATEDIFF(NOW(),dogum_ts)>? AND is_active=1 AND deleted_at IS NULL");
        $stmt->execute([$this->firmaId(),$ay,(int)$kurallar['uyari_gun']]);$yeniYukselen=(int)$stmt->fetchColumn();
        if($yeniYukselen>0){
            $stmt=$this->db->prepare("UPDATE kesme_acma_uyari SET yukseltildi_ts=NOW() WHERE firma_id=? AND ay=? AND durum='acik'
                AND yukseltildi_ts IS NULL AND DATEDIFF(NOW(),dogum_ts)>? AND is_active=1 AND deleted_at IS NULL");
            $stmt->execute([$this->firmaId(),$ay,(int)$kurallar['uyari_gun']]);
            (new BildirimModel())->broadcastByPermission('kesme_analiz_yonetim','Kesme/Açma açık uyarıları',
                "$ay döneminde $yeniYukselen uyarı yönetim eşiğini aştı.",'index.php?p=kesme-acma/list','error','danger');
        }
        $stmt=$this->db->prepare("SELECT u.*,p.adi_soyadi,DATEDIFF(NOW(),u.dogum_ts) acik_gun
            FROM kesme_acma_uyari u LEFT JOIN personel p ON p.id=u.personel_id
            WHERE u.firma_id=? AND u.ay=? AND u.is_active=1 AND u.deleted_at IS NULL ORDER BY u.puan DESC,u.dogum_ts");
        $stmt->execute([$this->firmaId(),$ay]); $liste=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $grup=[];foreach($liste as $u){$pid=(int)$u['personel_id'];$grup[$pid]['personel']=$u['adi_soyadi'];if($u['durum']==='acik')$grup[$pid]['puan']=($grup[$pid]['puan']??0)+(float)$u['puan'];$u['key']=Security::encrypt($u['id']);unset($u['id']);$grup[$pid]['bulgular'][]=$u;}
        $stmt=$this->db->prepare("SELECT w.personel_id,w.ay,g.ts,g.gerekce,COALESCE(us.adi_soyadi,'Sistem') yazan
            FROM kesme_acma_uyari_gerekce g INNER JOIN kesme_acma_uyari w ON w.id=g.uyari_id
            LEFT JOIN users us ON us.id=g.yazan_id WHERE g.firma_id=? AND g.is_active=1 AND g.deleted_at IS NULL
            ORDER BY g.ts DESC");$stmt->execute([$this->firmaId()]);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $arsiv){$pid=(int)$arsiv['personel_id'];if(isset($grup[$pid])&&count($grup[$pid]['arsiv']??[])<3)$grup[$pid]['arsiv'][]=$arsiv;}
        usort($grup,fn($a,$b)=>$b['puan']<=>$a['puan']);return array_values($grup);
    }

    public function normalDegerler(string $ay): array
    {
        $son=$ay.'-01';$ilk=date('Y-m-01',strtotime($son.' -8 months'));$atama=$this->atamalar($ilk,date('Y-m-t',strtotime($son)));
        $ozet=$this->ozetle($this->hamGunluk($ilk,date('Y-m-t',strtotime($son.' -1 month'))),$atama);
        $secili=$this->ozetle($this->hamGunluk($son,date('Y-m-t',strtotime($son))),$atama);$out=[];
        foreach($ozet['kisiler'] as $pid=>$k){
            $ol=array_values(array_filter($k['olumlu_gunler']));$od=array_values(array_filter($k['odeme_gunler']));$oran=[];
            foreach($k['gun_detay'] as $g){$temas=$g['odeme']+$g['kesim'];if($temas)$oran[]=$g['odeme']/$temas*100;}
            $sk=$secili['kisiler'][$pid]??[];$sg=max(1,count(array_filter($sk['gunler']??[])));$st=($sk['odeme']??0)+($sk['kesim']??0);
            $out[]=['personel'=>$k['personel'],
                'olumlu_med'=>round($this->q($ol,.5),1),'olumlu_q1'=>round($this->q($ol,.25),1),'olumlu_q3'=>round($this->q($ol,.75),1),'secili_olumlu'=>round(($sk['olumlu']??0)/$sg,1),
                'odeme_med'=>round($this->q($od,.5),1),'odeme_q1'=>round($this->q($od,.25),1),'odeme_q3'=>round($this->q($od,.75),1),'secili_odeme'=>round(($sk['odeme']??0)/$sg,1),
                'oran_med'=>round($this->q($oran,.5),1),'oran_q1'=>round($this->q($oran,.25),1),'oran_q3'=>round($this->q($oran,.75),1),'secili_oran'=>$st?round(($sk['odeme']??0)/$st*100,1):0];
        }
        usort($out,fn($a,$b)=>strcmp($a['personel'],$b['personel']));return $out;
    }

    public function uyariKapat(string $key, string $gerekce, int $userId): void
    {
        $id=(int)Security::decrypt($key);if($id<=0||mb_strlen(trim($gerekce))<5)throw new \InvalidArgumentException('Gerekçe zorunludur.');
        $stmt=$this->db->prepare("UPDATE kesme_acma_uyari SET durum='kapali' WHERE id=? AND firma_id=? AND is_active=1 AND deleted_at IS NULL");$stmt->execute([$id,$this->firmaId()]);
        if(!$stmt->rowCount())throw new \RuntimeException('Uyarı bulunamadı.');
        $stmt=$this->db->prepare('INSERT INTO kesme_acma_uyari_gerekce (firma_id,uyari_id,yazan_id,gerekce) VALUES (?,?,?,?)');$stmt->execute([$this->firmaId(),$id,$userId,trim($gerekce)]);
        $this->gunlukYaz('uyari',"#$id uyarısı gerekçeyle kontrol edildi",$userId);
    }

    public function kontrolListesi(string $ay): array
    {
        $stmt=$this->db->prepare("SELECT m.id,m.ad,COALESCE(d.isaretli,0) isaretli,d.ts,u.adi_soyadi
            FROM kesme_acma_kontrol_madde m LEFT JOIN kesme_acma_kontrol_durum d ON d.firma_id=m.firma_id AND d.madde_id=m.id AND d.ay=?
            LEFT JOIN users u ON u.id=d.isaretleyen_id WHERE m.firma_id=? AND m.is_active=1 AND m.deleted_at IS NULL ORDER BY m.sira,m.id");$stmt->execute([$ay,$this->firmaId()]);
        return array_map(function($r){$r['key']=Security::encrypt($r['id']);unset($r['id']);return $r;},$stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function kontrolIsaretle(string $key,string $ay,bool $isaretli,int $userId): void
    {
        $id=(int)Security::decrypt($key);$stmt=$this->db->prepare('INSERT INTO kesme_acma_kontrol_durum (firma_id,ay,madde_id,isaretli,isaretleyen_id,ts) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE isaretli=VALUES(isaretli),isaretleyen_id=VALUES(isaretleyen_id),ts=NOW()');$stmt->execute([$this->firmaId(),$ay,$id,$isaretli?1:0,$userId]);
        $this->gunlukYaz('kontrol',"#$id kontrol maddesi ".($isaretli?'işaretlendi':'geri alındı'),$userId);
    }

    public function kontrolMaddeEkle(string $ad,int $userId): void
    {
        $ad=trim($ad);if(mb_strlen($ad)<3||mb_strlen($ad)>120)throw new \InvalidArgumentException('Kontrol maddesi 3–120 karakter olmalıdır.');
        $stmt=$this->db->prepare('INSERT INTO kesme_acma_kontrol_madde (firma_id,ad,sira) SELECT ?,?,COALESCE(MAX(sira),0)+10 FROM kesme_acma_kontrol_madde WHERE firma_id=?');
        $stmt->execute([$this->firmaId(),$ad,$this->firmaId()]);$this->gunlukYaz('kontrol',"Kontrol maddesi eklendi: $ad",$userId);
    }

    public function kontrolMaddeSil(string $key,int $userId): void
    {
        $id=(int)Security::decrypt($key);$stmt=$this->db->prepare('UPDATE kesme_acma_kontrol_madde SET is_active=0,deleted_at=NOW() WHERE id=? AND firma_id=? AND is_active=1 AND deleted_at IS NULL');$stmt->execute([$id,$this->firmaId()]);
        if(!$stmt->rowCount())throw new \RuntimeException('Kontrol maddesi bulunamadı.');$this->gunlukYaz('kontrol',"#$id kontrol maddesi kaldırıldı",$userId);
    }

    public function gunluk(): array
    {
        $stmt=$this->db->prepare("SELECT l.ts,l.kategori,l.aciklama,COALESCE(u.adi_soyadi,'Sistem') kullanici FROM kesme_acma_islem_gunlugu l LEFT JOIN users u ON u.id=l.kullanici_id WHERE l.firma_id=? ORDER BY l.ts DESC LIMIT 300");$stmt->execute([$this->firmaId()]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function gunlukYaz(string $kategori,string $aciklama,int $userId): void
    {
        $stmt=$this->db->prepare('INSERT INTO kesme_acma_islem_gunlugu (firma_id,kullanici_id,kategori,aciklama) VALUES (?,?,?,?)');$stmt->execute([$this->firmaId(),$userId,$kategori,$aciklama]);
    }
}
