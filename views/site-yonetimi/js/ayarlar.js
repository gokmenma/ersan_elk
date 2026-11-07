$(document).ready(function () {
    const API = 'views/site-yonetimi/api.php';
    const form = $('#ayarForm');

    const yukle = () => {
        $.post(API, { action: 'ayar-getir' }, function (res) {
            if (res.status !== 'success') { Swal.fire('Hata', res.message || 'Ayarlar alınamadı.', 'error'); return; }
            form.find('[name]').not('[name="action"]').each(function () {
                const deger = res.data[this.name];
                $(this).val(deger == null ? '' : deger);
            });
        }, 'json').fail(() => Swal.fire('Hata', 'Ayarlar alınamadı.', 'error'));
    };
    yukle();

    form.on('submit', function (e) {
        e.preventDefault();
        form.find('.is-invalid').removeClass('is-invalid');
        const btn = $('#btnAyarKaydet').prop('disabled', true);
        $.post(API, form.serialize(), function (res) {
            if (res.status === 'success') {
                showToast(res.message, 'success');
            } else {
                Swal.fire('Hata', res.message || 'Ayarlar kaydedilemedi.', 'error');
            }
        }, 'json').fail(function (xhr) {
            const hatalar = (xhr.responseJSON && xhr.responseJSON.hatalar) || null;
            if (hatalar) {
                Object.keys(hatalar).forEach((k) => form.find(`[name="${k}"]`).addClass('is-invalid'));
                const ilk = Object.keys(hatalar)[0];
                const sekme = form.find(`[name="${ilk}"]`).closest('.tab-pane').attr('id');
                if (sekme) { $(`[data-bs-target="#${sekme}"]`).tab('show'); }
                Swal.fire('Kontrol edin', Object.values(hatalar).join('\n'), 'warning');
            } else {
                Swal.fire('Hata', 'Ayarlar kaydedilemedi.', 'error');
            }
        }).always(() => btn.prop('disabled', false));
    });
});
