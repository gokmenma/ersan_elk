<?php
use App\Helper\Form; // Form helper'ınız
use App\Helper\Security; // Güvenlik helper'ınız

$isEditing = isset($kasa) && !empty($kasa->id);

$kasa_id = $kasa->id ?? null;
$enc_kasa_id = $kasa_id ? Security::encrypt($kasa_id)  : 0;
$kasa_kodu = $kasa->kasa_kodu ?? '';
$kasa_adi = $kasa->kasa_adi ?? '';
$kasa_tipi = $kasa->kasa_tipi ?? 'nakit';
$para_birimi = $kasa->para_birimi ?? 'TRY';
$baslangic_bakiyesi = $kasa->baslangic_bakiyesi ?? '0.00';
$aciklama = $kasa->aciklama ?? '';
$aktif = $kasa->aktif ?? 1;




// Form başlığı ve buton metnini moda göre ayarla
$formTitle = $isEditing ? "Kasayı Düzenle" : "Yeni Kasa Tanımla";

?>


        <form class="p-3" action="" id="kasaForm">
            <!-- Düzenleme için şifrelenmiş ID veya yeni kayıt için boş ID alanı -->
            <input type="hidden" name="enc_kasa_id" value="<?php echo htmlspecialchars($enc_kasa_id, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="varsayilan_kasa" name="varsayilan_kasa" value="1" <?php echo isset($kasa->varsayilan_mi) && $kasa->varsayilan_mi ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="varsayilan_kasa">Bu kasa varsayılan kasa olarak ayarlansın</label>
                        <div class="form-text">Varsayılan kasa, yeni işlemler için otomatik olarak seçilir.</div>
                    </div>
                </div>
                </div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <?php
                    echo Form::FormFloatInput(
                        "text", // type
                        "kasa_adi", // name
                        $kasa_adi, // value
                        "", // error
                        "Kasa / Hesap Adı", // label
                        "archive", // icon
                        "form-control", // class
                        ['required' => true] // attributes
                    );
                    ?>
                    <div class="form-text">Kasanın veya banka hesabının tanınabilir adı (örn: Merkez Kasa, Garanti Bankası Vadesiz).</div>
                </div>
                <div class="col-md-4 mb-3">
                    <?php
                    echo Form::FormFloatInput(
                        "text",
                        "hesap_no",
                        $hesap_no ?? '',
                        "",
                        "Hesap Numarası / Kodu",
                        "hash",
                        "form-control"
                    );
                    ?>
                    <div class="form-text">Hesap numarası(Online Hesap hareketlerinde kullanılır)</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <?php
                    $kasa_tipleri = [
                        'nakit' => 'Nakit Kasa',
                        'banka' => 'Banka Hesabı',
                        'kredi_karti' => 'Kredi Kartı Hesabı',
                        'sanal_pos' => 'Sanal POS Hesabı',
                        'diger' => 'Diğer'
                    ];
                    echo Form::FormSelect2(
                        "kasa_tipi",
                        $kasa_tipleri,
                        $kasa_tipi,
                        "Kasa Tipi",
                        "tag",
                        "", // error
                        "", // id
                        "form-control select2" // class
                    );
                    ?>
                </div>
                <div class="col-md-4 mb-3">
                    <?php
                    // Para birimleri listesini bir helper veya config dosyasından almak daha iyidir.
                    $para_birimleri = [
                        'TRY' => 'Türk Lirası (TRY)',
                        'USD' => 'ABD Doları (USD)',
                        'EUR' => 'Euro (EUR)',
                        'GBP' => 'İngiliz Sterlini (GBP)'
                    ];
                    echo Form::FormSelect2(
                        "para_birimi",
                        $para_birimleri,
                        $para_birimi,
                        "Para Birimi",
                        "dollar-sign", // İkon
                        "", "", "form-control select2"
                    );
                    ?>
                </div>
                 <div class="col-md-4 mb-3">
                    <?php
                    echo Form::FormFloatInput(
                        "text", // number olarak da kullanılabilir ama formatlama için text daha esnek olabilir
                        "baslangic_bakiyesi",
                        number_format($baslangic_bakiyesi, 2, ',', '.'), // Değeri formatlayarak gösterelim
                        "",
                        "Açılış Bakiyesi",
                        "credit-card", // İkon
                        "form-control" // Sağa hizalı
                    );
                    ?>
                    <div class="form-text">Kasa ilk oluşturulduğundaki devir veya açılış miktarı.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 mb-3">
                    <?php
                    echo Form::FormFloatTextarea(
                        "aciklama",
                        $aciklama,
                        "",
                        "Açıklama / Notlar",
                        "file-text", // İkon
                        "form-control",
                        ['rows' => 3]
                    );
                    ?>
                    <div class="form-text">Bu kasa/hesap ile ilgili ek bilgiler (örn: Banka IBAN, Şube Adı vb.).</div>
                </div>
            </div>

            <hr>
            
            <div class="row align-items-center">
                <div class="col-md-6">
                     <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="kasa_aktif" name="aktif" value="1" <?php echo $aktif ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="kasa_aktif">Kasa Aktif</label>
                        <div class="form-text">Pasif kasalar yeni işlemlerde listelenmez.</div>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" id="kasaKaydetBtn" class="btn btn-success waves-effect btn-label waves-light">
                        <i class="bx bx-save label-icon me-1"></i> Kaydet
                    </button>
                </div>
            </div>

        </form>

