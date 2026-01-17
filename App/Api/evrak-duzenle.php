<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;

use App\Model\EvrakModel;

$Evrak = new EvrakModel();

//action = evrak-kaydet
if ($_POST['action'] == 'evrak-kaydet') {
    $id = $_POST["evrak_id"] != 0 ? Security::decrypt($_POST['evrak_id']) : 0;


    try {
        $data = [
            'id' => $id,
            'evrak_tanimi' => 1,
            'konu' => $_POST['evrak_konu'],
            'sayi' => $_POST['dosya_kodu'],
            'icerik' => $_POST['icerik'],
        ];

        $lastInsertId = $Evrak->saveWithAttr($data) ?? $_POST['evrak_id'];

        $status = "success";
        $message = "Evrak kaydedildi.";

    } catch (PDOException $ex) {
        $status = "error";
        $message = $ex->getMessage();
    }
    $res = [
        "status" => $status,
        "message" => $message,
        "data" => $data
    ];

    echo json_encode($res);

}