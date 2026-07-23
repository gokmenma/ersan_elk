/**
 * Yapay Zeka İş Ajanı (AI Work Agent) Frontend JS
 */
document.addEventListener('DOMContentLoaded', function () {
    const aiForm = document.getElementById('aiAgentForm');
    const promptInput = document.getElementById('aiPromptInput');
    const submitBtn = document.getElementById('aiSubmitBtn');
    const responseContainer = document.getElementById('aiResponseContainer');
    const loadingSpinner = document.getElementById('aiLoadingSpinner');
    const resultContent = document.getElementById('aiResultContent');
    const chipBtns = document.querySelectorAll('.ai-chip-btn');

    if (!aiForm || !promptInput) return;

    // Hızlı Chip Butonları Tıklama Eventi
    chipBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const promptText = this.getAttribute('data-prompt');
            promptInput.value = promptText;
            aiForm.dispatchEvent(new Event('submit'));
        });
    });

    // Form Gönderim Eventi
    aiForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const prompt = promptInput.value.trim();
        if (!prompt) return;

        // Akordiyon kapalıysa otomatik aç
        const collapseEl = document.getElementById('aiAgentCollapse');
        if (collapseEl && !collapseEl.classList.contains('show')) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl);
                bsCollapse.show();
            } else {
                collapseEl.classList.add('show');
            }
        }

        // UI Yükleniyor Durumu
        responseContainer.classList.remove('d-none');
        loadingSpinner.classList.remove('d-none');
        resultContent.innerHTML = '';
        submitBtn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'ai_agent_query');
        formData.append('prompt', prompt);

        fetch('views/arac-takip/api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loadingSpinner.classList.add('d-none');
            submitBtn.disabled = false;

            if (data.success) {
                resultContent.innerHTML = renderMarkdown(data.response);
            } else {
                resultContent.innerHTML = `<div class="alert alert-danger mb-0">${data.message || 'Analiz oluşturulurken bir hata meydana geldi.'}</div>`;
            }
        })
        .catch(error => {
            console.error('AI Agent Error:', error);
            loadingSpinner.classList.add('d-none');
            submitBtn.disabled = false;
            resultContent.innerHTML = `<div class="alert alert-danger mb-0">Sunucu ile iletişim kurulurken bağlantı hatası oluştu.</div>`;
        });
    });

    /**
     * Markdown Çıktısını Güvenli HTML Metnine Dönüştürür (Hızlı Parser)
     */
    function renderMarkdown(text) {
        if (!text) return '';

        let html = text;

        // HTML escaping for safety
        html = html.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

        // Code badges / Inline code `[RİSK: YÜKSEK 🚨]`
        html = html.replace(/`\[RİSK:\s*YÜKSEK\s*([^\]]*)\]`/gi, '<span class="badge bg-danger text-white px-3 py-2 font-size-13 shadow-sm rounded-pill"><i class="bx bx-error-circle me-1"></i> RİSK: YÜKSEK $1</span>');
        html = html.replace(/`\[RİSK:\s*ORTA\s*([^\]]*)\]`/gi, '<span class="badge bg-warning text-dark px-3 py-2 font-size-13 shadow-sm rounded-pill"><i class="bx bx-error me-1"></i> RİSK: ORTA $1</span>');
        html = html.replace(/`\[BİLGİ\s*([^\]]*)\]`/gi, '<span class="badge bg-info text-white px-3 py-2 font-size-13 shadow-sm rounded-pill"><i class="bx bx-info-circle me-1"></i> BİLGİ $1</span>');
        
        // General inline code `text`
        html = html.replace(/`([^`]+)`/g, '<code class="px-2 py-1 bg-dark rounded text-info border border-secondary border-opacity-25 font-size-13">$1</code>');

        // Headers ### Header
        html = html.replace(/^### (.*$)/gim, '<h5 class="text-white mt-3 mb-2 font-weight-bold" style="color: #60a5fa !important;">$1</h5>');
        html = html.replace(/^## (.*$)/gim, '<h4 class="text-white mt-3 mb-2 font-weight-bold" style="color: #93c5fd !important;">$1</h4>');
        html = html.replace(/^# (.*$)/gim, '<h3 class="text-white mt-3 mb-2 font-weight-bold" style="color: #bfdbfe !important;">$1</h3>');

        // Bold **text**
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong class="text-white font-weight-bold" style="color: #ffffff !important;">$1</strong>');

        // Blockquotes > text
        html = html.replace(/^&gt;\s?(.*$)/gim, '<blockquote class="blockquote font-size-14 p-3 my-2 border-start border-4 border-warning rounded" style="background: rgba(245, 158, 11, 0.15); color: #fde68a !important;">$1</blockquote>');

        // Unordered lists - text
        html = html.replace(/^\-\s?(.*$)/gim, '<li class="ms-3 font-size-14" style="color: #f1f5f9 !important; margin-bottom: 6px;">$1</li>');

        // Numbered lists 1. text
        html = html.replace(/^(\d+)\.\s?(.*$)/gim, '<div class="ms-3 font-size-14 mb-1" style="color: #f1f5f9 !important;"><span class="badge bg-secondary me-2">$1</span>$2</div>');

        // Convert newlines to breaks
        html = html.replace(/\n/g, '<br>');

        return html;
    }
});
