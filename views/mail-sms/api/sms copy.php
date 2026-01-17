<?php
// Betiğin çıktısının JSON olacağını en başta belirtin.
header('Content-Type: application/json; charset=utf-8');

use App\Model\SettingsModel;

// Yanıt için standart bir yapı oluşturun.
$apiResponse = [
    'status' => 'error', // Varsayılan durum
    'message' => 'Bilinmeyen bir hata oluştu.',
    'data' => null
];

try {
    // 1. Adım: JavaScript'ten gönderilen JSON verisini alın.
    $postData = json_decode(file_get_contents('php://input'), true);

    // Gelen verileri değişkenlere ata ve doğrula
    $messageText = $postData['message'] ?? '';
    $recipients = $postData['recipients'] ?? [];
    $msgheader = $postData['senderID'] ?? ''; // Varsayılan başlık
    
    // 2. Adım: Netgsm API kimlik bilgilerini ayarla.
    $username = $postData['username']; // Varsayılan kullanıcı adı
    $password = $postData['password'] ; // Varsayılan şifre


    
    if (empty($username) || empty($password)) {
        //eğer passsword ve username boş ise ayarlardan al
        

        throw new Exception("Netgsm API kimlik bilgileri eksik veya geçersiz.");
    }

    if (empty($messageText) || !is_string($messageText)) {
        throw new Exception("Geçerli bir mesaj metni gönderilmedi.");
    }
    if (empty($recipients) || !is_array($recipients)) {
        throw new Exception("Alıcı listesi boş veya geçersiz formatta.");
    }

    // 2. Adım: Netgsm'in istediği "messages" dizisini dinamik olarak oluşturun.
    $messagesPayload = [];
    foreach ($recipients as $recipientNumber) {
        // Her bir alıcı numarası için bir mesaj nesnesi oluşturup diziye ekle
        $messagesPayload[] = [
            'msg' => $messageText,
            'no' => (string)$recipientNumber // Numaranın string olduğundan emin ol
        ];
    }
    
    // 3. Adım: Netgsm'e gönderilecek ana veri yapısını oluşturun.
    $data = [
        "msgheader" => $msgheader, // Gönderici başlığı
        "messages" => $messagesPayload, // Dinamik olarak oluşturulan diziyi burada kullan
        "encoding" => "TR",
        "iysfilter" => "",
        "partnercode" => ""
    ];

    // API URL'si ve kimlik bilgileri
    $url = "https://api.netgsm.com.tr/sms/rest/v2/send";
  

    // cURL işlemleri...
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($username . ':' . $password)
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception('cURL Hatası: ' . curl_error($ch));
    }

    $netgsmResult = json_decode($response, true);

    if (isset($netgsmResult['code']) && $netgsmResult['code'] == '00') {
        $apiResponse['status'] = 'success';
        $apiResponse['message'] = count($recipients) . ' alıcıya SMS gönderim kuyruğuna alındı.';
        $apiResponse['data'] = $netgsmResult;
        $apiResponse["postdata"] = $data; // Gönderilen veriyi de yanıt olarak ekle
    } else {
        $apiResponse['message'] = 'Netgsm API Hatası: ' . ($netgsmResult['description'] ?? 'Bilinmeyen hata.');
        $apiResponse['data'] = $netgsmResult;
    }

} catch (Exception $e) {
    // Hataları yakala ve JSON olarak döndür
    $apiResponse['message'] = $e->getMessage();
} finally {
    if (isset($ch) && is_resource($ch)) {
        curl_close($ch);
    }
}

// Son olarak, standart API yanıtını JSON formatında yazdır.
echo json_encode($apiResponse, JSON_UNESCAPED_UNICODE);