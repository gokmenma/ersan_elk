<?php
require_once dirname(__DIR__, 3) . "/bootstrap.php";

use App\Model\PersonelModel;
use App\Model\RehberModel;

header('Content-Type: application/json');

try {
    $type = $_GET['type'] ?? 'sms'; // 'sms' veya 'mail'
    $q = mb_strtolower(trim($_GET['q'] ?? ''), 'UTF-8');

    $Personel = new PersonelModel();
    $personeller = $Personel->all(false, 'mail');

    $Rehber = new RehberModel();
    // Assuming ->get() returns array or object for RehberModel
    $rehberResult = $Rehber->all();
    if (method_exists($rehberResult, 'get')) {
        $rehberListesi = $rehberResult->get();
    } else {
        $rehberListesi = $rehberResult;
    }

    $results = [];

    if (is_array($personeller) || is_object($personeller)) {
        foreach ($personeller as $contact) {
            if ($type === 'mail') {
                $val = $contact->email_adresi ?? '';
                if (empty($val) || !filter_var($val, FILTER_VALIDATE_EMAIL))
                    continue;
            } else {
                $val = str_replace([' ', '(', ')', '-', '.'], '', $contact->cep_telefonu ?? '');
                if (empty($val))
                    continue;
                if (str_starts_with($val, '+90')) {
                    $val = substr($val, 3);
                }
            }

            $name = $contact->adi_soyadi ?? '';
            $searchString = mb_strtolower($name . ' ' . $val, 'UTF-8');

            if (empty($q) || mb_strpos($searchString, $q, 0, 'UTF-8') !== false) {
                // To avoid duplicate values
                $results[$val] = [
                    'name' => $name,
                    'value' => $val,
                    'desc' => !empty($contact->gorev) ? $contact->gorev : 'Personel'
                ];
            }
        }
    }

    if (is_array($rehberListesi) || is_object($rehberListesi)) {
        foreach ($rehberListesi as $contact) {
            if ($type === 'mail') {
                $val = $contact->email ?? '';
                if (empty($val) || !filter_var($val, FILTER_VALIDATE_EMAIL))
                    continue;
            } else {
                $val = str_replace([' ', '(', ')', '-', '.'], '', $contact->telefon ?? '');
                if (empty($val))
                    continue;
                if (str_starts_with($val, '+90')) {
                    $val = substr($val, 3);
                }
            }

            $name = $contact->adi_soyadi ?? '';
            $searchString = mb_strtolower($name . ' ' . $val, 'UTF-8');

            if (empty($q) || mb_strpos($searchString, $q, 0, 'UTF-8') !== false) {
                $results[$val] = [
                    'name' => $name,
                    'value' => $val,
                    'desc' => !empty($contact->kurum_adi) ? $contact->kurum_adi : 'Rehber'
                ];
            }
        }
    }

    // Limit to 20 results for performance
    $results = array_values($results);
    echo json_encode(array_slice($results, 0, 20));

} catch (\Exception $e) {
    echo json_encode([]);
}
