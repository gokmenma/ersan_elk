document.addEventListener('DOMContentLoaded', function() {
    // --- ELEMENTLERİ SEÇME ---
    const verificationForm = document.getElementById('verificationForm');
    const verificationCodeInput = document.getElementById('verificationCode');
    
    const successResultDiv = document.getElementById('successResult');
    const errorResultDiv = document.getElementById('errorResult');
    
    const docTypeSpan = document.getElementById('docType');
    const docDateSpan = document.getElementById('docDate');
    const docRecipientSpan = document.getElementById('docRecipient');
    const downloadLink = document.getElementById('downloadLink');
    const errorMessageP = document.getElementById('errorMessage');

    const resetButton = document.getElementById('resetButton');
    const tryAgainButton = document.getElementById('tryAgainButton');

    const submitButton = verificationForm.querySelector('button[type="submit"]');
    const spinner = submitButton.querySelector('.spinner-border');
    const btnText = submitButton.querySelector('.btn-text');

    // --- OLAY DİNLEYİCİLERİ ---
    verificationForm.addEventListener('submit', handleVerification);
    resetButton.addEventListener('click', resetPage);
    tryAgainButton.addEventListener('click', resetPage);

    /**
     * Form gönderildiğinde doğrulama işlemini yönetir.
     * @param {Event} e - Form gönderme olayı
     */
    async function handleVerification(e) {
        e.preventDefault();
        const code = verificationCodeInput.value.trim();

        if (!code) {
            alert('Lütfen bir doğrulama kodu girin.');
            return;
        }

        // Buton durumunu "Yükleniyor" yap
        toggleLoading(true);

        try {
            // SUNUCU İLE İLETİŞİM KISMI
            // Gerçek bir API çağrısı bu fonksiyon ile simüle ediliyor.
            const response = await fakeApiCall(code);
            
            // Sonuç başarılıysa
            showSuccess(response);

        } catch (error) {
            // Sonuç hatalıysa
            showError(error.message);
        } finally {
            // Her durumda buton durumunu eski haline getir
            toggleLoading(false);
        }
    }

    /**
     * API çağrısını simüle eden sahte bir fonksiyon.
     * GERÇEK PROJEDE BU KISMI KENDİ API'NİZE GÖRE DEĞİŞTİRMELİSİNİZ.
     * @param {string} code - Doğrulama kodu
     * @returns {Promise<object>} - Başarılı olursa evrak verilerini döndürür.
     */
    function fakeApiCall(code) {
        console.log(`Sunucuya gönderilen kod: ${code}`);
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                // Örnek başarılı kod: 'DOGRULA-12345'
                if (code.toUpperCase() === 'DOGRULA-12345') {
                    resolve({
                        docType: 'Üyelik Belgesi',
                        docDate: '21.10.2023',
                        docRecipient: 'Ahmet Yılmaz',
                        downloadUrl: '/path/to/your/document.pdf' // GERÇEK İNDİRME LİNKİ
                    });
                } 
                // Örnek başka başarılı kod
                else if (code.toUpperCase() === 'TEST-ABC') {
                     resolve({
                        docType: 'Faaliyet Raporu',
                        docDate: '15.09.2023',
                        docRecipient: 'Genel Kurul',
                        downloadUrl: '/path/to/another/document.docx'
                    });
                }
                else {
                    // Kod bulunamadıysa hata döndür
                    reject(new Error('Geçersiz kod. Bu koda ait bir evrak bulunamadı.'));
                }
            }, 1500); // 1.5 saniyelik bir gecikme simülasyonu
        });
    }

    /**
     * Başarılı doğrulama ekranını gösterir.
     * @param {object} data - API'den gelen evrak verileri
     */
    function showSuccess(data) {
        verificationForm.classList.add('d-none');
        errorResultDiv.classList.add('d-none');

        docTypeSpan.textContent = data.docType;
        docDateSpan.textContent = data.docDate;
        docRecipientSpan.textContent = data.docRecipient;
        downloadLink.href = data.downloadUrl;
        
        successResultDiv.classList.remove('d-none');
    }

    /**
     * Hata ekranını gösterir.
     * @param {string} message - Gösterilecek hata mesajı
     */
    function showError(message) {
        verificationForm.classList.add('d-none');
        successResultDiv.classList.add('d-none');

        errorMessageP.textContent = message;
        errorResultDiv.classList.remove('d-none');
    }

    /**
     * Sayfayı başlangıç durumuna döndürür.
     */
    function resetPage() {
        successResultDiv.classList.add('d-none');
        errorResultDiv.classList.add('d-none');
        
        verificationCodeInput.value = '';
        verificationForm.classList.remove('d-none');
        verificationCodeInput.focus();
    }

    /**
     * Butondaki yükleme animasyonunu açıp kapatır.
     * @param {boolean} isLoading - Yükleniyor durumu aktif mi?
     */
    function toggleLoading(isLoading) {
        if (isLoading) {
            spinner.classList.remove('d-none');
            btnText.textContent = 'Doğrulanıyor...';
            submitButton.disabled = true;
        } else {
            spinner.classList.add('d-none');
            btnText.textContent = 'Doğrula';
            submitButton.disabled = false;
        }
    }
});