<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\EvrakTakipModel;
use App\Model\PersonelModel;
use App\Model\UserModel;
use App\Model\BildirimModel;
use App\Service\MailGonderService;
use App\Helper\EmailTemplateHelper;
use App\Helper\Date;
use App\Helper\Security;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $Model = new EvrakTakipModel();

    try {
        switch ($action) {
            case 'evrak-kaydet':
                $data = $_POST;
                $id = isset($data['id']) ? intval($data['id']) : 0;

                unset($data['action']);
                $data['firma_id'] = $_SESSION['firma_id'];

                if (!empty($data['tarih'])) {
                    $data['tarih'] = Date::Ymd($data['tarih']);
                } else {
                    $data['tarih'] = date('Y-m-d');
                }

                // Dosya yükleme işlemi
                if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] == 0) {
                    $upload_dir = dirname(__DIR__, 2) . '/uploads/evrak-takip/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_ext = pathinfo($_FILES['dosya']['name'], PATHINFO_EXTENSION);
                    $file_name = time() . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['dosya']['tmp_name'], $upload_path)) {
                        $data['dosya_yolu'] = 'uploads/evrak-takip/' . $file_name;
                    }
                }

                // Checkboxlar için varsayılan değerler
                $data['personel_bildirim_durumu'] = isset($data['personel_bildirim_durumu']) ? 1 : 0;
                $data['cevap_verildi_mi'] = isset($data['cevap_verildi_mi']) ? 1 : 0;

                if (!empty($data['cevap_tarihi'])) {
                    $data['cevap_tarihi'] = Date::Ymd($data['cevap_tarihi']);
                } else {
                    $data['cevap_tarihi'] = null;
                }

                // İlişkili evrak ID
                $data['ilgili_evrak_id'] = !empty($data['ilgili_evrak_id']) ? intval($data['ilgili_evrak_id']) : null;

                // Boş değerleri null yap
                foreach ($data as $key => $value) {
                    if ($value === '' && $key != 'id' && $key != 'action') {
                        $data[$key] = null;
                    }
                }

                if ($id > 0) {
                    $Model->saveWithAttr($data);
                    $message = "Evrak başarıyla güncellendi.";
                } else {
                    $data['olusturan_kullanici_id'] = $_SESSION['user_id'] ?? null;
                    $id = $Model->saveWithAttr($data);
                    $message = "Evrak başarıyla kaydedildi.";
                }

                // Otomatik bildirim gönderimi
                if ($data['personel_bildirim_durumu'] == 1 && !empty($data['ilgili_personel_id'])) {
                    $evrak = $Model->getById($id);
                    $Personel = new PersonelModel();
                    $personelData = $Personel->find($data['ilgili_personel_id']);
                    
                    if ($evrak && $personelData) {
                        $email = $personelData->email_adresi ?? '';
                        $adi_soyadi = $personelData->adi_soyadi;

                        // Uygulama içi bildirim
                        if (!empty($email)) {
                            $User = new UserModel();
                            $user = $User->checkUser($email);
                            if ($user) {
                                $Bildirim = new BildirimModel();
                                $Bildirim->createNotification(
                                    $user->id,
                                    "Yeni Evrak Zimmetlendi",
                                    "Tarafınıza '{$evrak->konu}' konulu bir evrak zimmetlenmiştir.",
                                    "index.php?p=evrak-takip/list",
                                    "file",
                                    "info"
                                );
                            }
                        }

                        // E-Posta Bildirimi
                        if (!empty($email)) {
                            $evrak_tipi_label = $evrak->evrak_tipi == 'gelen' ? 'GELEN EVRAK' : 'GİDEN EVRAK';
                            $icerik = "
                                <p style='color: #020617; font-size: 16px; margin-bottom: 24px;'>Merhaba <b>{$adi_soyadi}</b>,</p>
                                <p style='color: #475569; margin-bottom: 24px;'>Sistem üzerinden tarafınıza yeni bir evrak zimmetlenmiştir. Detaylar aşağıdadır:</p>
                                <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px;'>
                                    <table style='width: 100%; border-collapse: collapse;'>
                                        <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; width: 35%; border-bottom: 1px solid #EDF2F7;'>EVRAK TİPİ</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak_tipi_label}</td></tr>
                                        <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>TARİH</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>" . date('d.m.Y', strtotime($evrak->tarih)) . "</td></tr>
                                        <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>EVRAK NO</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>#{$evrak->evrak_no}</td></tr>
                                        <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>KONU</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak->konu}</td></tr>
                                        <tr><td style='padding: 8px 0; color: #64748B; font-size: 13px;'>KURUM / FİRMA</td><td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px;'>{$evrak->kurum_adi}</td></tr>
                                    </table>
                                </div>
                                <p style='color: #475569;'>Evrak detaylarını görüntülemek ve diğer işlemler için sisteme giriş yapabilirsiniz.</p>";
                            $html = EmailTemplateHelper::getTemplate("Evrak Bildirimi", $icerik, "Sisteme Giriş Yap", "https://" . $_SERVER['HTTP_HOST'] . "/index.php?p=evrak-takip/list");
                            
                            // Bildirim tarihini güncelle
                            $Model->saveWithAttr([
                                'id' => $id,
                                'son_bildirim_tarihi_ilgili' => date('Y-m-d H:i:s')
                            ]);

                            MailGonderService::gonder([$email], "Evrak Bildirimi: " . $evrak->konu, $html);
                        }
                    }
                }

                // Eğer giden evrak ise ve ilgili bir gelen evrak seçildiyse 
                // o gelen evrakı "cevap verildi" olarak işaretle
                if ($data['evrak_tipi'] == 'giden' && !empty($data['ilgili_evrak_id'])) {
                    $Model->markAsReplied($data['ilgili_evrak_id'], $data['tarih']);
                }

                echo json_encode(['status' => 'success', 'message' => $message]);
                break;

            case 'evrak-sil':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception("Geçersiz evrak ID.");
                }

                $Model->softDelete($id);
                echo json_encode(['status' => 'success', 'message' => 'Evrak başarıyla silindi.']);
                break;

            case 'evrak-detay':
                $id = intval($_POST['id'] ?? 0);
                $evrak = $Model->getById($id);

                if (!$evrak) {
                    throw new Exception("Evrak bulunamadı.");
                }

                echo json_encode(['status' => 'success', 'data' => $evrak]);
                break;

            case 'evrak-listesi':
                $evraklar = $Model->all();
                echo json_encode(['status' => 'success', 'data' => $evraklar]);
                break;

            case 'evrak-istatistik':
                $stats = $Model->getStats();
                echo json_encode(['status' => 'success', 'data' => $stats]);
                break;

            case 'get-next-evrak-no':
                $tip = $_POST['tip'] ?? 'gelen';
                $next_no = $Model->getMaxEvrakNo($tip);
                echo json_encode(['status' => 'success', 'next_no' => $next_no]);
                break;

            case 'get-konular':
                $konular = $Model->getDistinctKonular();
                echo json_encode(['status' => 'success', 'data' => $konular]);
                break;

            case 'evrak-bildir':
                $id = intval($_POST['id'] ?? 0);
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $type = $_POST['type'] ?? 'ilgili'; // 'personel' veya 'ilgili'

                if ($id <= 0 || $personel_id <= 0) {
                    throw new Exception("Geçersiz veri.");
                }

                $evrak = $Model->getById($id);
                if (!$evrak) {
                    throw new Exception("Evrak bulunamadı.");
                }

                $Personel = new PersonelModel();
                $personelData = $Personel->find($personel_id);
                if (!$personelData) {
                    throw new Exception("Personel bulunamadı.");
                }

                $email = $personelData->email_adresi ?? '';
                $adi_soyadi = $personelData->adi_soyadi;

                // 1. Uygulama içi bildirim (Eğer email ile eşleşen bir kullanıcı varsa)
                if (!empty($email)) {
                    $User = new UserModel();
                    $user = $User->checkUser($email);
                    
                    if ($user) {
                        $Bildirim = new BildirimModel();
                        $Bildirim->createNotification(
                            $user->id,
                            "Yeni Evrak Zimmetlendi",
                            "Tarafınıza '{$evrak->konu}' konulu bir evrak zimmetlenmiştir.",
                            "index.php?p=evrak-takip/list",
                            "file",
                            "info"
                        );
                    }
                }

                // 2. E-Posta Bildirimi
                if (!empty($email)) {
                    $evrak_tipi_label = $evrak->evrak_tipi == 'gelen' ? 'GELEN EVRAK' : 'GİDEN EVRAK';
                    
                    $icerik = "
                        <p style='color: #020617; font-size: 16px; margin-bottom: 24px;'>Merhaba <b>{$adi_soyadi}</b>,</p>
                        <p style='color: #475569; margin-bottom: 24px;'>Sistem üzerinden tarafınıza yeni bir evrak zimmetlenmiştir. Detaylar aşağıdadır:</p>
                        
                        <div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px;'>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px; width: 35%; border-bottom: 1px solid #EDF2F7;'>EVRAK TİPİ</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak_tipi_label}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>TARİH</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>" . date('d.m.Y', strtotime($evrak->tarih)) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>EVRAK NO</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>#{$evrak->evrak_no}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px; border-bottom: 1px solid #EDF2F7;'>KONU</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$evrak->konu}</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748B; font-size: 13px;'>KURUM / FİRMA</td>
                                    <td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px;'>{$evrak->kurum_adi}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <p style='color: #475569;'>Evrak detaylarını görüntülemek ve diğer işlemler için sisteme giriş yapabilirsiniz.</p>";
                    
                    $html = EmailTemplateHelper::getTemplate(
                        "Evrak Bildirimi",
                        $icerik,
                        "Sisteme Giriş Yap",
                        "https://" . $_SERVER['HTTP_HOST'] . "/index.php?p=evrak-takip/list"
                    );

                    // Bildirim tarihini güncelle
                    $column = ($type == 'personel') ? 'son_bildirim_tarihi_personel' : 'son_bildirim_tarihi_ilgili';
                    $Model->saveWithAttr([
                        'id' => $id,
                        $column => date('Y-m-d H:i:s')
                    ]);

                    MailGonderService::gonder([$email], "Evrak Bildirimi: " . $evrak->konu, $html);
                    
                    $msg = "Bildirim ve mail başarıyla gönderildi.";
                } else {
                    $msg = "Personelin e-posta adresi bulunmadığı için sadece sistem bildirimi gönderildi (Eğer kullanıcı hesabı varsa).";
                }

                echo json_encode(['status' => 'success', 'message' => $msg]);
                break;

            default:
                throw new Exception("Geçersiz işlem.");
        }

    } catch (\Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>