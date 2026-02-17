<?php
use App\Helper\Route;
?>
<div class="row mb-3">
    <div class="col-md-12">
        <div id="spinner" class="text-center p-3" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden"></span>
            </div>
            <p class="">Veriler yüklenirken lütfen bekleyin...</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Puantaj Excel Yükleme</h3>
        <a href="index.php?p=puantaj/list" class="btn btn-secondary">
            <i class="bx bx-arrow-back"></i> Listeye Dön
        </a>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bx bx-info-circle me-2"></i>
            Lütfen "Ekip Bazında İş Emri Sonuçları Raporu" formatındaki Excel dosyasını yükleyiniz.
        </div>

        <form id="uploadForm" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tarih Seçin</label>
                    <input type="date" name="upload_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    <div class="form-text">Bu tarih, yüklenen verilerin tarihi olarak kaydedilecektir.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Excel Dosyası (.xlsx, .xls)</label>
                    <input type="file" name="excel_file" class="form-control" accept=".xlsx, .xls" required>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary" id="btnUpload">
                    <i class="bx bx-upload me-1"></i> Yükle
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#uploadForm').on('submit', function (e) {
            e.preventDefault();

            var formData = new FormData(this);
            formData.append('action', 'puantaj-excel-kaydet');

            // Show spinner
            $('#spinner').show();
            $('#btnUpload').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    $('#spinner').hide();
                    $('#btnUpload').prop('disabled', false);

                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı',
                                text: res.message,
                                confirmButtonText: 'Tamam'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'index.php?p=puantaj/list';
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata',
                                text: res.message
                            });
                        }
                    } catch (err) {
                        console.error('JSON Parse Error:', err, response);
                        Swal.fire({
                            icon: 'error',
                            title: 'Sistem Hatası',
                            text: 'Sunucudan geçersiz yanıt alındı.'
                        });
                    }
                },
                error: function (xhr, status, error) {
                    $('#spinner').hide();
                    $('#btnUpload').prop('disabled', false);
                    console.error('AJAX Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Bağlantı Hatası',
                        text: 'Bir hata oluştu: ' + error
                    });
                }
            });
        });
    });
</script>