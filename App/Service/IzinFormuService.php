<?php

namespace App\Service;

use App\Helper\Security;
use App\Model\Model;
use App\Model\PersonelIzinleriModel;
use App\Model\FirmaModel;
use App\Model\PersonelModel;
use PDO;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table as TableStyle;

class IzinFormuService
{
    protected $db;

    public function __construct()
    {
        $model = new Model();
        $this->db = $model->db;
    }

    /**
     * İzin formu için gerekli tüm verileri toplar ve formatlar
     */
    public function getFormData(int $izinId, int $firmaId, ?int $currentUserId = null): array
    {
        // 1. İzin Bilgileri
        $sqlIzin = "
            SELECT pi.*, t.tur_adi as izin_tipi_adi,
                   io.onay_tarihi, io.aciklama as onay_aciklama, u.adi_soyadi as onaylayan_adi_soyadi
            FROM personel_izinleri pi
            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            LEFT JOIN (
                SELECT io1.izin_id, io1.onaylayan_id, io1.onay_tarihi, io1.aciklama
                FROM izin_onaylari io1
                INNER JOIN (
                    SELECT MAX(id) as max_id FROM izin_onaylari GROUP BY izin_id
                ) io2 ON io1.id = io2.max_id
            ) io ON io.izin_id = pi.id
            LEFT JOIN users u ON io.onaylayan_id = u.id
            WHERE pi.id = ?
        ";
        $stmtIzin = $this->db->prepare($sqlIzin);
        $stmtIzin->execute([$izinId]);
        $izin = $stmtIzin->fetch(PDO::FETCH_OBJ);

        if (!$izin) {
            throw new \Exception('İzin talebi bulunamadı.');
        }

        // 2. Personel Bilgileri
        $stmtPersonel = $this->db->prepare("SELECT * FROM personel WHERE id = ?");
        $stmtPersonel->execute([$izin->personel_id]);
        $personel = $stmtPersonel->fetch(PDO::FETCH_OBJ);

        if (!$personel) {
            throw new \Exception('Personele ait kayıt bulunamadı.');
        }

        // 3. Firma Bilgileri
        $targetFirmaId = !empty($personel->firma_id) ? (int)$personel->firma_id : $firmaId;
        $stmtFirma = $this->db->prepare("SELECT * FROM firmalar WHERE id = ?");
        $stmtFirma->execute([$targetFirmaId]);
        $firma = $stmtFirma->fetch(PDO::FETCH_OBJ);

        // 4. Düzenleyen Kullanıcı Adı
        $duzenleyenAdi = '';
        if ($currentUserId) {
            $stmtUser = $this->db->prepare("SELECT adi_soyadi, user_name FROM users WHERE id = ?");
            $stmtUser->execute([$currentUserId]);
            $userObj = $stmtUser->fetch(PDO::FETCH_OBJ);
            if ($userObj) {
                $duzenleyenAdi = !empty($userObj->adi_soyadi) ? $userObj->adi_soyadi : ($userObj->user_name ?? '');
            }
        }
        if (empty($duzenleyenAdi)) {
            $duzenleyenAdi = $_SESSION['user_name'] ?? ($_SESSION['ad_soyad'] ?? 'Sistem Kullanıcısı');
        }

        // Formatlamalar
        $personelModel = new PersonelIzinleriModel();
        $gunSayisi = $personelModel->hesaplaIzinGunu($izin->baslangic_tarihi, $izin->bitis_tarihi);
        if ($gunSayisi <= 0 && !empty($izin->toplam_gun)) {
            $gunSayisi = (int)$izin->toplam_gun;
        }

        // TC Kimlik No Çözme
        $rawTc = $personel->tc_kimlik_no ?? '';
        $tcNo = '';
        if (!empty($rawTc)) {
            $decryptedTc = Security::decrypt($rawTc);
            $tcNo = ($decryptedTc !== 0 && $decryptedTc !== '0' && !empty($decryptedTc)) ? $decryptedTc : $rawTc;
        }

        // İşe Başlama Tarihi (İzin bitişinden sonraki gün)
        $iseBaslamaDate = !empty($izin->bitis_tarihi) ? date('d.m.Y', strtotime($izin->bitis_tarihi . ' +1 day')) : '';
        $baslangicFmt = !empty($izin->baslangic_tarihi) ? date('d.m.Y', strtotime($izin->baslangic_tarihi)) : '';
        $bitisFmt = !empty($izin->bitis_tarihi) ? date('d.m.Y', strtotime($izin->bitis_tarihi)) : '';
        $iseGirisFmt = !empty($personel->ise_giris_tarihi) ? date('d.m.Y', strtotime($personel->ise_giris_tarihi)) : '';
        $talepTarihiFmt = !empty($izin->talep_tarihi) ? date('d.m.Y', strtotime($izin->talep_tarihi)) : (!empty($izin->olusturma_tarihi) ? date('d.m.Y', strtotime($izin->olusturma_tarihi)) : date('d.m.Y'));
        $onayTarihiFmt = !empty($izin->onay_tarihi) ? date('d.m.Y', strtotime($izin->onay_tarihi)) : '';

        // SGK Sicil No
        $sgkSicilNo = !empty($personel->sgk_no) ? $personel->sgk_no : (!empty($firma->vergi_no) && $firma->vergi_no != '0' ? $firma->vergi_no : '48299010111140800461139000');

        return [
            'baslik' => 'İZİN TALEP FORMU',
            'firma_unvan' => !empty($firma->firma_unvan) ? $firma->firma_unvan : ($firma->firma_adi ?? 'ER-SAN ELEKTRİK İNŞAAT TAAHHÜT TİCARET LİMİTED ŞİRKETİ'),
            'firma_adres' => !empty($firma->adres) && $firma->adres != '0' ? $firma->adres : 'Yavuz Selim Mahallesi Recep Tayyip Erdoğan Bulvarı No:79 Dulkadiroğlu / KAHRAMANMARAŞ',
            'sgk_sicil_no' => $sgkSicilNo,
            'personel_adi' => trim($personel->adi_soyadi ?? ''),
            'tc_kimlik' => $tcNo,
            'ise_giris' => $iseGirisFmt,
            'unvan' => $personel->gorev ?? 'Personel',
            'departman' => !empty($personel->departman) ? $personel->departman : 'Merkez',
            'adres' => !empty($personel->ev_adresi) ? $personel->ev_adresi : ($personel->adres ?? '-'),
            'telefon' => $personel->cep_telefonu ?? '',
            'tur' => $izin->izin_tipi_adi ?? 'Mazeret İzni',
            'izin_gun' => $gunSayisi . ' GÜN',
            'izin_gun_sayi' => $gunSayisi,
            'izin_baslangic' => $baslangicFmt,
            'izin_bitis' => $bitisFmt,
            'ise_baslama' => $iseBaslamaDate,
            'izin_avansi' => 'YOK',
            'izin_nedeni' => !empty($izin->aciklama) ? $izin->aciklama : (!empty($izin->izin_tipi_adi) ? $izin->izin_tipi_adi : 'ÖZEL'),
            'talep_tarihi' => $talepTarihiFmt,
            'duzenleyen' => $duzenleyenAdi,
            'onay_veren' => !empty($izin->onaylayan_adi_soyadi) ? $izin->onaylayan_adi_soyadi : 'EMEL ÇİNER',
            'onay_tarihi' => !empty($onayTarihiFmt) ? $onayTarihiFmt : $talepTarihiFmt,
            'onay_durumu' => $izin->onay_durumu ?? 'Beklemede'
        ];
    }

    /**
     * Modal ve yazdırma için A4 uyumlu HTML önizleme çıktısı üretir
     */
    public function renderHtml(array $d): string
    {
        $hBaslik = htmlspecialchars($d['baslik'], ENT_QUOTES, 'UTF-8');
        $hFirmaUnvan = htmlspecialchars($d['firma_unvan'], ENT_QUOTES, 'UTF-8');
        $hFirmaAdres = htmlspecialchars($d['firma_adres'], ENT_QUOTES, 'UTF-8');
        $hSgkSicil = htmlspecialchars($d['sgk_sicil_no'], ENT_QUOTES, 'UTF-8');
        $hPersonelAdi = htmlspecialchars($d['personel_adi'], ENT_QUOTES, 'UTF-8');
        $hTcKimlik = htmlspecialchars($d['tc_kimlik'], ENT_QUOTES, 'UTF-8');
        $hIseGiris = htmlspecialchars($d['ise_giris'], ENT_QUOTES, 'UTF-8');
        $hUnvan = htmlspecialchars($d['unvan'], ENT_QUOTES, 'UTF-8');
        $hDepartman = htmlspecialchars($d['departman'], ENT_QUOTES, 'UTF-8');
        $hAdres = htmlspecialchars($d['adres'], ENT_QUOTES, 'UTF-8');
        $hTelefon = htmlspecialchars($d['telefon'], ENT_QUOTES, 'UTF-8');
        $hTur = htmlspecialchars($d['tur'], ENT_QUOTES, 'UTF-8');
        $hIzinGun = htmlspecialchars($d['izin_gun'], ENT_QUOTES, 'UTF-8');
        $hBaslangic = htmlspecialchars($d['izin_baslangic'], ENT_QUOTES, 'UTF-8');
        $hBitis = htmlspecialchars($d['izin_bitis'], ENT_QUOTES, 'UTF-8');
        $hIseBaslama = htmlspecialchars($d['ise_baslama'], ENT_QUOTES, 'UTF-8');
        $hIzinAvansi = htmlspecialchars($d['izin_avansi'], ENT_QUOTES, 'UTF-8');
        $hIzinNedeni = htmlspecialchars($d['izin_nedeni'], ENT_QUOTES, 'UTF-8');
        $hTalepTarihi = htmlspecialchars($d['talep_tarihi'], ENT_QUOTES, 'UTF-8');
        $hDuzenleyen = htmlspecialchars($d['duzenleyen'], ENT_QUOTES, 'UTF-8');
        $hOnayVeren = htmlspecialchars($d['onay_veren'], ENT_QUOTES, 'UTF-8');
        $hOnayTarihi = htmlspecialchars($d['onay_tarihi'], ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<div class="izin-form-print-wrapper" id="izinFormPrintArea">
    <style>
        .izin-form-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            color: #000000;
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.4;
            padding: 24px 30px;
            box-sizing: border-box;
        }
        .izin-form-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 18px;
            color: #000000;
            text-transform: uppercase;
        }
        .izin-table-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .izin-table-section td {
            border: 1px solid #222222;
            padding: 5px 10px;
            vertical-align: middle;
            font-size: 12.5px;
        }
        .izin-tbl-hdr {
            font-weight: 700;
            background-color: #f0f0f0;
            text-transform: uppercase;
            font-size: 13px;
            padding: 6px 10px !important;
            border: 1px solid #222222;
        }
        .izin-lbl-col {
            width: 29%;
            font-weight: 700;
            color: #111111;
            background-color: #ffffff;
        }
        .izin-sep-col {
            width: 22px;
            min-width: 22px;
            max-width: 22px;
            text-align: center;
            font-weight: 700;
            color: #111111;
            padding: 5px 0 !important;
        }
        .izin-val-col {
            width: auto;
            color: #111111;
        }
        .izin-signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            table-layout: fixed;
        }
        .izin-signatures-table td {
            border: 1px solid #222222;
            padding: 8px;
            vertical-align: top;
            font-size: 11.5px;
            text-align: center;
            width: 33.33%;
        }
        .sig-header {
            font-weight: 700;
            font-size: 12px;
            text-decoration: underline;
            margin-bottom: 6px;
        }
        .sig-name {
            font-weight: 600;
            margin-bottom: 18px;
            min-height: 18px;
        }
        .sig-imza {
            font-style: italic;
            font-weight: 700;
            margin-bottom: 24px;
        }
        .sig-date {
            font-size: 11px;
            color: #333333;
            text-align: left;
            padding-left: 4px;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #izinFormPrintArea, #izinFormPrintArea * {
                visibility: visible;
            }
            #izinFormPrintArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 10mm 15mm;
            }
            .izin-form-container {
                max-width: 100% !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm 10mm;
            }
        }
    </style>

    <div class="izin-form-container border shadow-xs rounded-2">
        <div class="izin-form-title">{$hBaslik}</div>

        <!-- 1. İŞVERENİN -->
        <table class="izin-table-section">
            <tr>
                <td colspan="3" class="izin-tbl-hdr">İŞVERENİN</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Adı Ünvanı</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hFirmaUnvan}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Adresi</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hFirmaAdres}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">SGK İşyeri Sicil Nosu</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hSgkSicil}</td>
            </tr>
        </table>

        <!-- 2. PERSONEL BİLGİLERİ -->
        <table class="izin-table-section">
            <tr>
                <td class="izin-lbl-col">Adı Soyadı</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col fw-bold">{$hPersonelAdi}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">SGK Sicil No / T.C. Kimlik No</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hTcKimlik}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">İşe Giriş Tarihi</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hIseGiris}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Görevi</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hUnvan}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Departmanı</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hDepartman}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Adres ve İletişim Bilgileri</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">
                    <div>{$hAdres}</div>
                    <div class="text-muted mt-0.5">{$hTelefon}</div>
                </td>
            </tr>
        </table>

        <!-- 3. İZNİN -->
        <table class="izin-table-section">
            <tr>
                <td colspan="3" class="izin-tbl-hdr">İZNİN</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Türü</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col fw-bold">{$hTur}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Süresi (Gün)</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col fw-bold">{$hIzinGun}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Başlangıç Tarihi</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hBaslangic}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">Bitiş Tarihi</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hBitis}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">İşe Başlama Tarihi</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hIseBaslama}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">İzin Avansı (TL)</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hIzinAvansi}</td>
            </tr>
            <tr>
                <td class="izin-lbl-col">İzin Talep Nedeni</td>
                <td class="izin-sep-col">:</td>
                <td class="izin-val-col">{$hIzinNedeni}</td>
            </tr>
        </table>

        <!-- 4. İMZALAR -->
        <table class="izin-signatures-table">
            <tr>
                <td>
                    <div class="sig-header">Personel Adı - Soyadı</div>
                    <div class="sig-name">{$hPersonelAdi}</div>
                    <div class="sig-imza">İmza</div>
                    <div class="sig-date"><strong>Talep Tarihi :</strong> {$hTalepTarihi}</div>
                </td>
                <td>
                    <div class="sig-header">Düzenleyen</div>
                    <div class="sig-name">{$hDuzenleyen}</div>
                    <div class="sig-imza">İmza</div>
                    <div class="sig-date">&nbsp;</div>
                </td>
                <td>
                    <div class="sig-header">Onay Veren</div>
                    <div class="sig-name">{$hOnayVeren}</div>
                    <div class="sig-imza">İmza</div>
                    <div class="sig-date"><strong>Onay Tarihi :</strong> {$hOnayTarihi}</div>
                </td>
            </tr>
        </table>
    </div>
</div>
HTML;

        return $html;
    }

    /**
     * files/izin_formu.pdf ile birebir aynı yapıda modern bir DOCX (Word) dokümanı üretir
     */
    public function generateDocx(array $d, ?string $outputPath = null)
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Segoe UI');
        $phpWord->setDefaultFontSize(10);

        // Sayfa ayarları (A4, dar kenar boşlukları)
        $section = $phpWord->addSection([
            'paperSize' => 'A4',
            'marginTop' => 700,
            'marginRight' => 800,
            'marginBottom' => 700,
            'marginLeft' => 800
        ]);

        // Stiller
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '222222',
            'cellMarginTop' => 60,
            'cellMarginRight' => 100,
            'cellMarginBottom' => 60,
            'cellMarginLeft' => 100,
            'alignment' => JcTable::CENTER
        ];
        $headerCellStyle = ['bgColor' => 'F2F2F2'];

        $titleFont = ['name' => 'Segoe UI', 'size' => 14, 'bold' => true, 'color' => '000000'];
        $secHdrFont = ['name' => 'Segoe UI', 'size' => 10, 'bold' => true, 'color' => '000000'];
        $lblFont = ['name' => 'Segoe UI', 'size' => 9.5, 'bold' => true, 'color' => '222222'];
        $valFont = ['name' => 'Segoe UI', 'size' => 9.5, 'color' => '000000'];
        $valBoldFont = ['name' => 'Segoe UI', 'size' => 9.5, 'bold' => true, 'color' => '000000'];
        $pTight = ['spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 1.15];
        $pCenter = ['spaceAfter' => 0, 'spaceBefore' => 0, 'alignment' => Jc::CENTER];

        // 1. BAŞLIK
        $section->addText($d['baslik'], $titleFont, ['alignment' => Jc::CENTER, 'spaceAfter' => 180]);

        $wCol1 = 2800;
        $wCol2 = 250;
        $wCol3 = 6450;
        $totalW = 9500;

        // 2. İŞVERENİN TABLOSU
        $table1 = $section->addTable($tableStyle);
        $row0 = $table1->addRow(300);
        $cellHdr1 = $row0->addCell($totalW, array_merge($headerCellStyle, ['gridSpan' => 3]));
        $cellHdr1->addText('İŞVERENİN', $secHdrFont, $pTight);

        $row1 = $table1->addRow();
        $row1->addCell($wCol1)->addText('Adı Ünvanı', $lblFont, $pTight);
        $row1->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $row1->addCell($wCol3)->addText($d['firma_unvan'], $valFont, $pTight);

        $row2 = $table1->addRow();
        $row2->addCell($wCol1)->addText('Adresi', $lblFont, $pTight);
        $row2->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $row2->addCell($wCol3)->addText($d['firma_adres'], $valFont, $pTight);

        $row3 = $table1->addRow();
        $row3->addCell($wCol1)->addText('SGK İşyeri Sicil Nosu', $lblFont, $pTight);
        $row3->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $row3->addCell($wCol3)->addText($d['sgk_sicil_no'], $valFont, $pTight);

        $section->addTextBreak(1, null, ['spaceAfter' => 60]);

        // 3. PERSONEL BİLGİLERİ TABLOSU
        $table2 = $section->addTable($tableStyle);
        
        $r = $table2->addRow();
        $r->addCell($wCol1)->addText('Adı Soyadı', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['personel_adi'], $valBoldFont, $pTight);

        $r = $table2->addRow();
        $r->addCell($wCol1)->addText('SGK Sicil No / T.C. Kimlik No', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['tc_kimlik'], $valFont, $pTight);

        $r = $table2->addRow();
        $r->addCell($wCol1)->addText('İşe Giriş Tarihi', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['ise_giris'], $valFont, $pTight);

        $r = $table2->addRow();
        $r->addCell($wCol1)->addText('Görevi', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['unvan'], $valFont, $pTight);

        $r = $table2->addRow();
        $r->addCell($wCol1)->addText('Departmanı', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['departman'], $valFont, $pTight);

        $r = $table2->addRow();
        $r->addCell($wCol1)->addText('Adres ve İletişim Bilgileri', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $cAdres = $r->addCell($wCol3);
        $cAdres->addText($d['adres'], $valFont, $pTight);
        if (!empty($d['telefon'])) {
            $cAdres->addText($d['telefon'], ['name' => 'Segoe UI', 'size' => 9, 'color' => '555555'], $pTight);
        }

        $section->addTextBreak(1, null, ['spaceAfter' => 60]);

        // 4. İZNİN TABLOSU
        $table3 = $section->addTable($tableStyle);
        $rowHdr3 = $table3->addRow(300);
        $cellHdr3 = $rowHdr3->addCell($totalW, array_merge($headerCellStyle, ['gridSpan' => 3]));
        $cellHdr3->addText('İZNİN', $secHdrFont, $pTight);

        $r = $table3->addRow();
        $r->addCell($wCol1)->addText('Türü', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['tur'], $valBoldFont, $pTight);

        $r = $table3->addRow();
        $r->addCell($wCol1)->addText('Süresi (Gün)', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['izin_gun'], $valBoldFont, $pTight);

        $r = $table3->addRow();
        $r->addCell($wCol1)->addText('Başlangıç Tarihi', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['izin_baslangic'], $valFont, $pTight);

        $r = $table3->addRow();
        $r->addCell($wCol1)->addText('Bitiş Tarihi', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['izin_bitis'], $valFont, $pTight);

        $r = $table3->addRow();
        $r->addCell($wCol1)->addText('İşe Başlama Tarihi', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['ise_baslama'], $valFont, $pTight);

        $r = $table3->addRow();
        $r->addCell($wCol1)->addText('İzin Avansı (TL)', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['izin_avansi'], $valFont, $pTight);

        $r = $table3->addRow();
        $r->addCell($wCol1)->addText('İzin Talep Nedeni', $lblFont, $pTight);
        $r->addCell($wCol2)->addText(':', $lblFont, $pTight);
        $r->addCell($wCol3)->addText($d['izin_nedeni'], $valFont, $pTight);

        $section->addTextBreak(1, null, ['spaceAfter' => 80]);

        // 5. İMZALAR TABLOSU (3 Sütunlu)
        $wSig = (int)($totalW / 3);
        $tableSig = $section->addTable($tableStyle);
        
        $rSig1 = $tableSig->addRow();
        
        // Sütun 1: Personel
        $c1 = $rSig1->addCell($wSig);
        $c1->addText('Personel Adı - Soyadı', ['name' => 'Segoe UI', 'size' => 9.5, 'bold' => true, 'underline' => 'single'], $pCenter);
        $c1->addTextBreak(1, null, ['spaceAfter' => 20]);
        $c1->addText($d['personel_adi'], $valBoldFont, $pCenter);
        $c1->addTextBreak(1, null, ['spaceAfter' => 40]);
        $c1->addText('İmza', ['name' => 'Segoe UI', 'size' => 9, 'italic' => true, 'bold' => true], $pCenter);
        $c1->addTextBreak(2, null, ['spaceAfter' => 40]);
        $c1->addText('Talep Tarihi : ' . $d['talep_tarihi'], ['name' => 'Segoe UI', 'size' => 8.5, 'italic' => true], $pTight);

        // Sütun 2: Düzenleyen
        $c2 = $rSig1->addCell($wSig);
        $c2->addText('Düzenleyen', ['name' => 'Segoe UI', 'size' => 9.5, 'bold' => true, 'underline' => 'single'], $pCenter);
        $c2->addTextBreak(1, null, ['spaceAfter' => 20]);
        $c2->addText($d['duzenleyen'], $valBoldFont, $pCenter);
        $c2->addTextBreak(1, null, ['spaceAfter' => 40]);
        $c2->addText('İmza', ['name' => 'Segoe UI', 'size' => 9, 'italic' => true, 'bold' => true], $pCenter);
        $c2->addTextBreak(2, null, ['spaceAfter' => 40]);
        $c2->addText('', $valFont, $pTight);

        // Sütun 3: Onay Veren
        $c3 = $rSig1->addCell($wSig);
        $c3->addText('Onay Veren', ['name' => 'Segoe UI', 'size' => 9.5, 'bold' => true, 'underline' => 'single'], $pCenter);
        $c3->addTextBreak(1, null, ['spaceAfter' => 20]);
        $c3->addText($d['onay_veren'], $valBoldFont, $pCenter);
        $c3->addTextBreak(1, null, ['spaceAfter' => 40]);
        $c3->addText('İmza', ['name' => 'Segoe UI', 'size' => 9, 'italic' => true, 'bold' => true], $pCenter);
        $c3->addTextBreak(2, null, ['spaceAfter' => 40]);
        $c3->addText('Onay Tarihi : ' . $d['onay_tarihi'], ['name' => 'Segoe UI', 'size' => 8.5, 'italic' => true], $pTight);

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

        if ($outputPath !== null) {
            $objWriter->save($outputPath);
            return $outputPath;
        } else {
            $filename = 'Izin_Talep_Formu_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $d['personel_adi']) . '.docx';
            header("Content-Description: File Transfer");
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');
            $objWriter->save('php://output');
            exit;
        }
    }
}
