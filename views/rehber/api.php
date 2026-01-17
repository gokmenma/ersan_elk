<?php
require_once '../../vendor/autoload.php';
//require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\RehberModel;

$Rehber = new RehberModel();

if ($_POST['action'] == 'kaydet') {
    $id = Security::decrypt($_POST['id']);

    try {
        $data = [
            'id' => $id,
            'adi_soyadi' => $_POST['adi_soyadi'],
            'kurum_adi' => $_POST['kurum_adi'],
            'telefon' => $_POST['telefon'],
            'email' => $_POST['email'],
            'adres' => $_POST['adres'],
            'aciklama' => $_POST['aciklama']
        ];
        $lastInsertedId = $Rehber->saveWithAttr($data) ?? $_POST['id'];
        $rowData = $Rehber->getTableRow($lastInsertedId);
        $status = 'success';
        $message = 'Kişi başarıyla kaydedildi.';
    } catch (PDOException $ex) {
        $status = 'error';
        $message = $ex->getMessage();
    }
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'rowData' => $rowData
    ]);
}

if($_POST['action'] == 'kayitSil') {
    $id = ($_POST['id']);
    try {
        $Rehber->delete($id);
        $status = 'success';
        $message = 'Kişi başarıyla silindi.';
    } catch (PDOException $ex) {
        $status = 'error';
        $message = $ex->getMessage();
    }
    echo json_encode([
        'status' => $status,
        'message' => $message
    ]);
}

//Güncelleme için kayıt bilgilerini getir
if ($_POST['action'] == 'kayitGetir') {
    $id = Security::decrypt($_POST['id']);
    $kisi = $Rehber->find($id);
    echo json_encode($kisi);
}