$(document).on('click', '#saveSettingsBtn', function(e) {
    e.preventDefault();

    let settings = {};
    $('.setting-input').each(function() {
        settings[$(this).attr('name')] = $(this).val();
    });

    $.post('views/ayarlar/api.php', { action: 'save', settings: settings }, function(response) {
        if (response.status === 'success') {
            alert(response.message);
        } else {
            alert(response.message);
        }
    }, 'json');
});
