<?php

declare(strict_types=1);

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\CariHareketleriModel;
use App\Model\CariModel;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__, 3) . '/Autoloader.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function cariApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cariInitials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($parts === []) {
        return '?';
    }
    $selected = count($parts) > 1 ? [$parts[0], $parts[count($parts) - 1]] : [$parts[0]];
    return implode('', array_map(static fn(string $part): string => mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8'), $selected));
}

function checkCariAuth(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        cariApiResponse(['success' => false, 'message' => 'Oturumunuz sona ermiş.'], 401);
    }
    if (!Gate::allows('cari_takibi') && !Gate::allows('cari/list')) {
        cariApiResponse(['success' => false, 'message' => 'Cari takip modülünü görüntüleme yetkiniz bulunmuyor.'], 403);
    }
}

function verifyCsrfToken(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($token) || empty($sessionToken) || !hash_equals((string) $sessionToken, (string) $token)) {
        cariApiResponse(['success' => false, 'message' => 'Geçersiz güvenlik doğrulaması (CSRF). Lütfen sayfayı yenileyin.'], 419);
    }
}

checkCariAuth();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$currentUserId = (int) ($_SESSION['id'] ?? $_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));

$Cari = new CariModel();
$CariHareket = new CariHareketleriModel();
$db = $Cari->getDb();

try {
    // 1. CARI LISTESI
    if ($method === 'GET' && $action === 'list') {
        $search = trim((string) ($_GET['search'] ?? ''));
        $balanceFilter = trim((string) ($_GET['filter'] ?? 'all')); // all, borclu, alacakli
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(10, (int) ($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $where = "c.silinme_tarihi IS NULL";
        $bind = [];

        if ($balanceFilter === 'borclu') {
            $where .= " AND (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) > 0";
        } elseif ($balanceFilter === 'alacakli') {
            $where .= " AND (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) < 0";
        }

        if ($search !== '') {
            $where .= " AND (c.CariAdi LIKE :search OR c.firma LIKE :search OR c.vkn_tckn LIKE :search OR c.Telefon LIKE :search OR c.Email LIKE :search OR c.il LIKE :search OR c.ilce LIKE :search)";
            $bind['search'] = "%{$search}%";
        }

        // Toplam Sayı
        $countSql = "SELECT COUNT(*) FROM cari c WHERE {$where}";
        $countStmt = $db->prepare($countSql);
        foreach ($bind as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $totalItems = (int) $countStmt->fetchColumn();

        // Cari Kayıtları
        $sql = "SELECT c.*, 
                (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as bakiye
                FROM cari c 
                WHERE {$where} 
                ORDER BY c.CariAdi ASC 
                LIMIT :offset, :limit";
        $stmt = $db->prepare($sql);
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $items = [];
        foreach ($rows as $row) {
            $bakiye = (float) ($row->bakiye ?? 0);
            $items[] = [
                'id' => Security::encrypt((string) $row->id),
                'name' => (string) ($row->CariAdi ?? ''),
                'initials' => cariInitials((string) ($row->CariAdi ?? '')),
                'company' => (string) ($row->firma ?? ''),
                'phone' => (string) ($row->Telefon ?? ''),
                'email' => (string) ($row->Email ?? ''),
                'address' => (string) ($row->Adres ?? ''),
                'vkn' => (string) ($row->vkn_tckn ?? ''),
                'city' => (string) ($row->il ?? ''),
                'district' => (string) ($row->ilce ?? ''),
                'bakiye' => $bakiye,
                'bakiye_status' => $bakiye > 0 ? 'borclu' : ($bakiye < 0 ? 'alacakli' : 'sifir'),
                'bakiye_label' => $bakiye > 0 ? 'Borçlu' : ($bakiye < 0 ? 'Alacaklı' : 'Bakiye Yok'),
            ];
        }

        // Özet
        $summaryInfo = $Cari->summary();
        $summary = [
            'total_cariler' => (int) ($summaryInfo->toplam_cari ?? 0),
            'toplam_borc' => (float) ($summaryInfo->toplam_borc ?? 0),
            'toplam_alacak' => (float) ($summaryInfo->toplam_alacak ?? 0),
            'genel_bakiye' => (float) ($summaryInfo->genel_bakiye ?? 0),
            'borclu_sayisi' => (int) ($summaryInfo->borclu_cari_sayisi ?? 0),
            'alacakli_sayisi' => (int) ($summaryInfo->alacakli_cari_sayisi ?? 0),
        ];

        cariApiResponse([
            'success' => true,
            'items' => $items,
            'summary' => $summary,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalItems,
                'has_more' => ($offset + count($items)) < $totalItems,
            ],
            'can_edit' => Gate::allows('cari_takibi') || Gate::allows('cari/duzenle'),
            'can_export_statements' => Gate::allows('cari_hesap_hareketleri'),
        ]);
    }

    // 2. CARI DETAY VE HAREKETLER
    if ($method === 'GET' && $action === 'detail') {
        $encId = (string) ($_GET['id'] ?? '');
        $id = (int) Security::decrypt($encId);
        if ($id <= 0) {
            cariApiResponse(['success' => false, 'message' => 'Geçersiz cari kaydı.'], 422);
        }

        $cari = $Cari->find($id);
        if (!$cari) {
            cariApiResponse(['success' => false, 'message' => 'Cari kaydı bulunamadı veya silinmiş.'], 404);
        }

        // Özet Bilgiler
        $stmtOzet = $db->prepare("SELECT SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmtOzet->execute(['cari_id' => $id]);
        $ozet = $stmtOzet->fetch(PDO::FETCH_OBJ);
        $toplamBorc = (float) ($ozet->toplam_borc ?? 0);
        $toplamAlacak = (float) ($ozet->toplam_alacak ?? 0);
        $bakiye = (float) ($ozet->bakiye ?? 0);

        // Hareketler
        $sql = "SELECT h.*, u.adi_soyadi as ekleyen_adi,
                (SELECT ROUND(SUM(alacak - borc), 2) FROM cari_hareketleri 
                 WHERE cari_id = :cari_id AND silinme_tarihi IS NULL 
                   AND (islem_tarihi < h.islem_tarihi OR (islem_tarihi = h.islem_tarihi AND id <= h.id))) as yuruyen_bakiye
                FROM cari_hareketleri h
                LEFT JOIN users u ON u.id = h.ekleyen_kullanici
                WHERE h.cari_id = :cari_id AND h.silinme_tarihi IS NULL
                ORDER BY h.islem_tarihi DESC, h.id DESC";
        $stmtH = $db->prepare($sql);
        $stmtH->execute(['cari_id' => $id]);
        $hareketRows = $stmtH->fetchAll(PDO::FETCH_OBJ);

        $hareketler = [];
        foreach ($hareketRows as $h) {
            $isBorc = (float) $h->borc > 0;
            $amt = $isBorc ? (float) $h->borc : (float) $h->alacak;
            $yuruyen = (float) ($h->yuruyen_bakiye ?? ($h->alacak - $h->borc));
            $hareketler[] = [
                'id' => Security::encrypt((string) $h->id),
                'type' => $isBorc ? 'aldim' : 'verdim',
                'amount' => $amt,
                'borc' => (float) $h->borc,
                'alacak' => (float) $h->alacak,
                'date' => (string) $h->islem_tarihi,
                'date_formatted' => date('d.m.Y H:i', strtotime($h->islem_tarihi)),
                'belge_no' => (string) ($h->belge_no ?? ''),
                'aciklama' => (string) ($h->aciklama ?? ''),
                'has_file' => !empty($h->dosya),
                'file_url' => !empty($h->dosya) ? '/uploads/cari_belgeler/' . rawurlencode($h->dosya) : null,
                'yuruyen_bakiye' => $yuruyen,
                'ekleyen_adi' => (string) ($h->ekleyen_adi ?? ''),
            ];
        }

        cariApiResponse([
            'success' => true,
            'cari' => [
                'id' => $encId,
                'name' => (string) ($cari->CariAdi ?? ''),
                'initials' => cariInitials((string) ($cari->CariAdi ?? '')),
                'company' => (string) ($cari->firma ?? ''),
                'phone' => (string) ($cari->Telefon ?? ''),
                'email' => (string) ($cari->Email ?? ''),
                'address' => (string) ($cari->Adres ?? ''),
                'notes' => (string) ($cari->notlar ?? ''),
                'vkn' => (string) ($cari->vkn_tckn ?? ''),
                'tax_office' => (string) ($cari->vergi_dairesi ?? ''),
                'city' => (string) ($cari->il ?? ''),
                'district' => (string) ($cari->ilce ?? ''),
                'bakiye' => $bakiye,
                'toplam_borc' => $toplamBorc,
                'toplam_alacak' => $toplamAlacak,
                'ekstre_pdf_url' => Gate::allows('cari_hesap_hareketleri')
                    ? '/api/cari-ekstre.php?id=' . rawurlencode($encId)
                    : null,
            ],
            'hareketler' => $hareketler,
            'can_edit' => Gate::allows('cari_takibi') || Gate::allows('cari/duzenle'),
        ]);
    }

    // 3. TÜM HAREKETLER (Geçmiş listesi ve modal)
    if ($method === 'GET' && $action === 'tum_hareketler') {
        $search = trim((string) ($_GET['search'] ?? ''));
        $type = trim((string) ($_GET['type'] ?? 'all')); // all, aldim, verdim
        $baslangic = trim((string) ($_GET['baslangic'] ?? ''));
        $bitis = trim((string) ($_GET['bitis'] ?? ''));
        $encCariId = trim((string) ($_GET['cari_id'] ?? ''));
        $cariId = $encCariId !== '' ? (int) Security::decrypt($encCariId) : 0;

        $where = "h.silinme_tarihi IS NULL AND c.silinme_tarihi IS NULL";
        $bind = [];

        if ($cariId > 0) {
            $where .= " AND h.cari_id = :cari_id";
            $bind['cari_id'] = $cariId;
        }
        if ($search !== '') {
            $where .= " AND (c.CariAdi LIKE :search OR c.firma LIKE :search OR h.aciklama LIKE :search OR h.belge_no LIKE :search)";
            $bind['search'] = "%{$search}%";
        }
        if ($type === 'aldim') {
            $where .= " AND h.borc > 0";
        } elseif ($type === 'verdim') {
            $where .= " AND h.alacak > 0";
        }
        if ($baslangic !== '') {
            $where .= " AND DATE(h.islem_tarihi) >= :baslangic";
            $bind['baslangic'] = $baslangic;
        }
        if ($bitis !== '') {
            $where .= " AND DATE(h.islem_tarihi) <= :bitis";
            $bind['bitis'] = $bitis;
        }

        $sql = "SELECT h.*, c.CariAdi, c.firma, u.adi_soyadi as ekleyen_adi
                FROM cari_hareketleri h
                LEFT JOIN cari c ON h.cari_id = c.id
                LEFT JOIN users u ON u.id = h.ekleyen_kullanici
                WHERE {$where}
                ORDER BY h.islem_tarihi DESC, h.id DESC LIMIT 100";
        $stmt = $db->prepare($sql);
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $results = [];
        foreach ($rows as $r) {
            $isBorc = (float) $r->borc > 0;
            $amt = $isBorc ? (float) $r->borc : (float) $r->alacak;
            $results[] = [
                'id' => Security::encrypt((string) $r->id),
                'cari_id' => Security::encrypt((string) $r->cari_id),
                'cari_name' => (string) ($r->CariAdi ?? ''),
                'cari_company' => (string) ($r->firma ?? ''),
                'type' => $isBorc ? 'aldim' : 'verdim',
                'type_label' => $isBorc ? 'Aldım' : 'Verdim',
                'amount' => $amt,
                'date' => (string) $r->islem_tarihi,
                'date_formatted' => date('d.m.Y H:i', strtotime($r->islem_tarihi)),
                'date_short' => date('d.m.Y', strtotime($r->islem_tarihi)),
                'time_short' => date('H:i', strtotime($r->islem_tarihi)),
                'belge_no' => (string) ($r->belge_no ?? ''),
                'aciklama' => (string) ($r->aciklama ?? ''),
                'has_file' => !empty($r->dosya),
                'file_url' => !empty($r->dosya) ? '/uploads/cari_belgeler/' . rawurlencode($r->dosya) : null,
                'ekleyen_adi' => (string) ($r->ekleyen_adi ?? ''),
            ];
        }

        cariApiResponse([
            'success' => true,
            'items' => $results,
            'export_pdf_url' => $cariId > 0
                ? '/api/cari-ekstre.php?id=' . rawurlencode($encCariId)
                : '/views/cari/export-tum-hareketler-pdf.php',
        ]);
    }

    // 4. CARI KAYDET / GÜNCELLE
    if ($method === 'POST' && $action === 'save_cari') {
        verifyCsrfToken();
        if (!Gate::allows('cari_takibi') && !Gate::allows('cari/duzenle')) {
            cariApiResponse(['success' => false, 'message' => 'Cari kaydetme yetkiniz bulunmuyor.'], 403);
        }

        $inputData = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($inputData)) {
            $inputData = $_POST;
        }

        $rawCariId = (string) ($inputData['id'] ?? $inputData['cari_id'] ?? '');
        $id = $rawCariId !== '' ? (int) Security::decrypt($rawCariId) : 0;
        $cariAdi = trim((string) ($inputData['CariAdi'] ?? $inputData['name'] ?? ''));

        if ($cariAdi === '') {
            cariApiResponse(['success' => false, 'message' => 'Cari adı zorunludur.'], 422);
        }

        $payload = [
            'id' => $id > 0 ? $id : 0,
            'CariAdi' => $cariAdi,
            'firma' => trim((string) ($inputData['firma'] ?? $inputData['company'] ?? '')) ?: null,
            'vkn_tckn' => trim((string) ($inputData['vkn_tckn'] ?? $inputData['vkn'] ?? '')) ?: null,
            'vergi_dairesi' => trim((string) ($inputData['vergi_dairesi'] ?? $inputData['tax_office'] ?? '')) ?: null,
            'Telefon' => trim((string) ($inputData['Telefon'] ?? $inputData['phone'] ?? '')) ?: null,
            'Email' => trim((string) ($inputData['Email'] ?? $inputData['email'] ?? '')) ?: null,
            'il' => trim((string) ($inputData['il'] ?? $inputData['city'] ?? '')) ?: null,
            'ilce' => trim((string) ($inputData['ilce'] ?? $inputData['district'] ?? '')) ?: null,
            'Adres' => trim((string) ($inputData['Adres'] ?? $inputData['address'] ?? '')) ?: null,
            'notlar' => trim((string) ($inputData['notlar'] ?? $inputData['notes'] ?? '')) ?: null,
            'Aktif' => 1,
        ];

        $savedId = $Cari->saveWithAttr($payload);
        $finalId = $id > 0 ? $id : (int) $savedId;

        cariApiResponse([
            'success' => true,
            'message' => $id > 0 ? 'Cari başarıyla güncellendi.' : 'Yeni cari başarıyla eklendi.',
            'id' => Security::encrypt((string) $finalId),
        ]);
    }

    // 5. CARI NOT GÜNCELLE
    if ($method === 'POST' && $action === 'save_note') {
        verifyCsrfToken();
        $inputData = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($inputData)) {
            $inputData = $_POST;
        }

        $encId = (string) ($inputData['cari_id'] ?? '');
        $id = (int) Security::decrypt($encId);
        if ($id <= 0) {
            cariApiResponse(['success' => false, 'message' => 'Geçersiz cari kaydı.'], 422);
        }

        $notlar = trim((string) ($inputData['notlar'] ?? ''));
        $stmt = $db->prepare("UPDATE cari SET notlar = :notlar WHERE id = :id AND silinme_tarihi IS NULL");
        $stmt->execute(['notlar' => $notlar ?: null, 'id' => $id]);

        cariApiResponse(['success' => true, 'message' => 'Cari notu kaydedildi.']);
    }

    // 6. CARI SIL
    if ($method === 'POST' && $action === 'delete_cari') {
        verifyCsrfToken();
        if (!Gate::allows('cari_takibi') && !Gate::allows('cari/sil')) {
            cariApiResponse(['success' => false, 'message' => 'Cari silme yetkiniz bulunmuyor.'], 403);
        }

        $inputData = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($inputData)) {
            $inputData = $_POST;
        }

        $encId = (string) ($inputData['id'] ?? $inputData['cari_id'] ?? '');
        $id = (int) Security::decrypt($encId);
        if ($id <= 0) {
            cariApiResponse(['success' => false, 'message' => 'Geçersiz cari kaydı.'], 422);
        }

        $deleteMovements = !empty($inputData['delete_movements']);
        $Cari->softDelete($id);

        if ($deleteMovements) {
            $stmt = $db->prepare("UPDATE cari_hareketleri SET silinme_tarihi = NOW(), silen_kullanici = :user_id WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
            $stmt->execute(['user_id' => $currentUserId, 'cari_id' => $id]);
        }

        cariApiResponse(['success' => true, 'message' => 'Cari kaydı başarıyla silindi.']);
    }

    // 7. HIZLI HAREKET KAYDET (Aldım / Verdim)
    if ($method === 'POST' && $action === 'save_hareket') {
        verifyCsrfToken();
        if (!Gate::allows('cari_takibi') && !Gate::allows('cari_hesap_hareketleri')) {
            cariApiResponse(['success' => false, 'message' => 'Hareket ekleme yetkiniz bulunmuyor.'], 403);
        }

        $rawHareketId = (string) ($_POST['hareket_id'] ?? '');
        $hareketId = $rawHareketId !== '' ? (int) Security::decrypt($rawHareketId) : 0;

        $encCariId = (string) ($_POST['cari_id'] ?? '');
        $cariId = (int) Security::decrypt($encCariId);
        if ($cariId <= 0) {
            cariApiResponse(['success' => false, 'message' => 'Lütfen geçerli bir cari seçin.'], 422);
        }

        $type = (string) ($_POST['type'] ?? 'aldim'); // aldim | verdim
        $tutarRaw = str_replace([' ', '₺', 'TL'], '', (string) ($_POST['tutar'] ?? '0'));
        $tutar = Helper::formattedMoneyToNumber($tutarRaw);
        if ($tutar <= 0) {
            cariApiResponse(['success' => false, 'message' => 'Geçerli bir işlem tutarı giriniz.'], 422);
        }

        $tarihRaw = trim((string) ($_POST['islem_tarihi'] ?? date('Y-m-d H:i')));
        $tarih = date('Y-m-d H:i:s', strtotime($tarihRaw));
        $belgeNo = trim((string) ($_POST['belge_no'] ?? '')) ?: null;
        $aciklama = trim((string) ($_POST['aciklama'] ?? '')) ?: null;

        // Dosya Yükleme
        $dosyaAdi = null;
        if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__, 3) . '/uploads/cari_belgeler/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['dosya']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
            if (in_array($ext, $allowedExts, true)) {
                $dosyaAdi = uniqid('cari_', true) . '.' . $ext;
                if (!move_uploaded_file($_FILES['dosya']['tmp_name'], $uploadDir . $dosyaAdi)) {
                    $dosyaAdi = null;
                }
            }
        }

        $hareketData = [
            'id' => $hareketId > 0 ? $hareketId : 0,
            'cari_id' => $cariId,
            'islem_tarihi' => $tarih,
            'belge_no' => $belgeNo,
            'aciklama' => $aciklama,
            'borc' => $type === 'aldim' ? $tutar : 0,
            'alacak' => $type === 'verdim' ? $tutar : 0,
            'ekleyen_kullanici' => $currentUserId ?: null,
        ];
        if ($dosyaAdi !== null) {
            $hareketData['dosya'] = $dosyaAdi;
        }

        $CariHareket->saveWithAttr($hareketData);

        // Güncel bakiye özeti
        $stmtOzet = $db->prepare("SELECT SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
        $stmtOzet->execute(['cari_id' => $cariId]);
        $ozet = $stmtOzet->fetch(PDO::FETCH_OBJ);

        cariApiResponse([
            'success' => true,
            'message' => $hareketId > 0 ? 'İşlem güncellendi.' : 'İşlem başarıyla kaydedildi.',
            'summary' => [
                'toplam_borc' => (float) ($ozet->toplam_borc ?? 0),
                'toplam_alacak' => (float) ($ozet->toplam_alacak ?? 0),
                'bakiye' => (float) ($ozet->bakiye ?? 0),
            ],
        ]);
    }

    // 8. HAREKET GETİR / DETAY
    if ($method === 'GET' && $action === 'hareket_detail') {
        $encId = (string) ($_GET['id'] ?? '');
        $id = (int) Security::decrypt($encId);
        if ($id <= 0) {
            cariApiResponse(['success' => false, 'message' => 'Geçersiz hareket kaydı.'], 422);
        }

        $sql = "SELECT h.*, c.CariAdi, c.firma, u.adi_soyadi as ekleyen_adi
                FROM cari_hareketleri h
                LEFT JOIN cari c ON h.cari_id = c.id
                LEFT JOIN users u ON u.id = h.ekleyen_kullanici
                WHERE h.id = :id AND h.silinme_tarihi IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $h = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$h) {
            cariApiResponse(['success' => false, 'message' => 'Hareket bulunamadı.'], 404);
        }

        $isBorc = (float) $h->borc > 0;
        $amt = $isBorc ? (float) $h->borc : (float) $h->alacak;

        cariApiResponse([
            'success' => true,
            'hareket' => [
                'id' => $encId,
                'cari_id' => Security::encrypt((string) $h->cari_id),
                'cari_name' => (string) ($h->CariAdi ?? ''),
                'cari_company' => (string) ($h->firma ?? ''),
                'type' => $isBorc ? 'aldim' : 'verdim',
                'type_label' => $isBorc ? 'Aldım' : 'Verdim',
                'amount' => $amt,
                'date' => (string) $h->islem_tarihi,
                'date_input' => date('Y-m-d\TH:i', strtotime($h->islem_tarihi)),
                'date_formatted' => date('d.m.Y H:i', strtotime($h->islem_tarihi)),
                'belge_no' => (string) ($h->belge_no ?? ''),
                'aciklama' => (string) ($h->aciklama ?? ''),
                'has_file' => !empty($h->dosya),
                'file_url' => !empty($h->dosya) ? '/uploads/cari_belgeler/' . rawurlencode($h->dosya) : null,
                'ekleyen_adi' => (string) ($h->ekleyen_adi ?? ''),
            ],
        ]);
    }

    // 9. HAREKET SİL
    if ($method === 'POST' && $action === 'delete_hareket') {
        verifyCsrfToken();
        if (!Gate::allows('cari_takibi') && !Gate::allows('cari_hesap_hareketleri')) {
            cariApiResponse(['success' => false, 'message' => 'Hareket silme yetkiniz bulunmuyor.'], 403);
        }

        $inputData = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($inputData)) {
            $inputData = $_POST;
        }

        $encId = (string) ($inputData['id'] ?? $inputData['hareket_id'] ?? '');
        $id = (int) Security::decrypt($encId);
        if ($id <= 0) {
            cariApiResponse(['success' => false, 'message' => 'Geçersiz hareket kaydı.'], 422);
        }

        $h = $CariHareket->find($id);
        if (!$h) {
            cariApiResponse(['success' => false, 'message' => 'Hareket kaydı bulunamadı.'], 404);
        }

        $CariHareket->softDelete($id);

        cariApiResponse(['success' => true, 'message' => 'İşlem kaydı başarıyla silindi.']);
    }

    cariApiResponse(['success' => false, 'message' => 'Bilinmeyen işlem türü.'], 400);

} catch (Throwable $e) {
    error_log('Cari API Hatası: ' . $e->getMessage());
    cariApiResponse(['success' => false, 'message' => 'Sunucu hatası: ' . $e->getMessage()], 500);
}
