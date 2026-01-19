$(document).ready(function() {
    // DataTable başlatma
    $('#logTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[ 0, "desc" ]], // ID'ye göre azalan sıralama (en yeni en üstte)
        "pageLength": 25
    });

    // Detay butonuna tıklama işlemi
    $(document).on('click', '.view-details', function() {
        var btn = $(this);
        var type = btn.data('type');
        var sender = btn.data('sender');
        var recipients = btn.data('recipients'); // JSON string olabilir veya olmayabilir, kontrol edeceğiz
        var subject = btn.data('subject');
        var message = btn.data('message');
        var attachments = btn.data('attachments');
        var date = btn.data('date');

        // Modal içeriğini doldur
        $('#modalDate').text(date);
        $('#modalSender').text(sender);
        
        // Alıcıları listele
        var recipientsHtml = '';
        if (typeof recipients === 'string') {
            try {
                var recipientsArr = JSON.parse(recipients);
                if (Array.isArray(recipientsArr)) {
                    recipientsArr.forEach(function(r) {
                        recipientsHtml += '<span class="badge bg-secondary me-1 mb-1">' + r + '</span>';
                    });
                } else {
                    recipientsHtml = recipients;
                }
            } catch (e) {
                recipientsHtml = recipients;
            }
        } else if (Array.isArray(recipients)) {
             recipients.forEach(function(r) {
                recipientsHtml += '<span class="badge bg-secondary me-1 mb-1">' + r + '</span>';
            });
        } else {
            recipientsHtml = recipients;
        }
        $('#modalRecipients').html(recipientsHtml);

        // Tip kontrolü (Email vs SMS)
        if (type === 'sms') {
            $('#modalSubjectRow').hide();
            $('#modalAttachmentsRow').hide();
            $('#modalMessage').text(message); // SMS için düz metin
        } else {
            $('#modalSubjectRow').show();
            $('#modalSubject').text(subject);
            $('#modalMessage').html(message); // Email için HTML
            
            // Ekler
            var attachmentsHtml = '';
            if (attachments) {
                try {
                    var attArr = typeof attachments === 'string' ? JSON.parse(attachments) : attachments;
                    if (Array.isArray(attArr) && attArr.length > 0) {
                        attArr.forEach(function(att) {
                            var name = att.name || att.path || 'Dosya';
                            attachmentsHtml += '<div class="mb-1"><i class="fas fa-paperclip me-2"></i>' + name + '</div>';
                        });
                    } else {
                        attachmentsHtml = '<span class="text-muted">Ek yok</span>';
                    }
                } catch (e) {
                    attachmentsHtml = '<span class="text-muted">Ek bilgisi okunamadı</span>';
                }
            } else {
                attachmentsHtml = '<span class="text-muted">Ek yok</span>';
            }
            $('#modalAttachments').html(attachmentsHtml);
            $('#modalAttachmentsRow').show();
        }

        // Modalı göster
        var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
        myModal.show();
    });
});
