<?php

use App\Helper\Route;

?>

<div class="row">
	<div class="col-12">
		<div class="page-title-box d-sm-flex align-items-center justify-content-between">
			<h4 class="mb-sm-0">Online Hesap Hareketleri (İşBank Posmatik)</h4>
		</div>
	</div>
</div>


<div class="card">
	<div class="card-header">
		<div class="d-flex align-items-center justify-content-between">
			<h5 class="mb-0">Sorgulama</h5>
			<a href="<?php Route::get('gelir-gider/list') ?>" class="btn btn-secondary btn-sm">Listeye Dön</a>
		</div>
	</div>
	<div class="card-body">
		<form id="isbankQueryForm">
			<div class="row g-3">
				<div class="col-md-3">
					<label class="form-label">Kullanıcı Adı (uid)</label>
					<input type="text" class="form-control" value="" name="uid" autocomplete="off" required>
                    <div class="form-check form-switch mt-1">
								<input class="form-check-input" type="checkbox" role="switch" id="rememberIsbankCreds">
								<label class="form-check-label" for="rememberIsbankCreds">Beni hatırla</label>
                                
							</div>
                            <small class="text-muted d-block">Beni hatırla seçilirse bilgiler bu tarayıcıda kaydedilir.</small>
				</div>
				<div class="col-md-3">
					<label class="form-label">Şifre (pwd)</label>
					<input type="password" class="form-control" name="pwd" value="" autocomplete="off" required>
				</div>
				<div class="col-md-3">
					<label class="form-label">Başlangıç Tarihi (GG.AA.YYYY HH:MM:SS)</label>
					<input type="text" class="form-control flatpickr time-input" name="BeginDate" value="03.01.2026 00:00:01" placeholder="03.01.2026 00:00:01">
							<small class="text-muted d-block">Not: Banka kısıtları gereği tarih aralığını dar tutun (default 1 hafta).</small>
				
                </div>
				<div class="col-md-3">
					<label class="form-label">Bitiş Tarihi (GG.AA.YYYY HH:MM:SS)</label>
					<input type="text" class="form-control flatpickr time-input" name="EndDate" value="10.01.2026 23:59:59" placeholder="10.01.2026 23:59:59">
				</div>

				<div class="col-12">
					<div class="row g-2 align-items-center">
												<div class="col-12 col-lg-auto">
							<button type="button" id="btnFetchIsbank" class="btn btn-primary w-100 w-lg-auto">
								Sorgula
							</button>
						</div>
						<div class="col-12 col-lg-auto">
							<button type="button" id="btnImportIsbank" class="btn btn-success w-100 w-lg-auto" disabled>
								Veritabanına Aktar
							</button>
						</div>
                    </div>
						<div class="row">
						</div>
				</div>
			</div>
		</form>
	</div>
</div>


<div class="card">
	<div class="card-header">
		<h5 class="mb-0">Sonuç</h5>
	</div>
	<div class="card-body overflow-auto">
		<style>
			/* Bu sayfaya özel sıkı tablo görünümü */
			#isbankTxTable.table > :not(caption) > * > * { padding: .25rem .35rem; }
			#isbankTxTable .isbank-desc { min-height: 34px; resize: vertical; }
			#isbankTxTable td { vertical-align: top; }
		</style>
		<table id="isbankTxTable" class="dttable table table-bordered nowrap w-100 table-hover">
			<thead>
				<tr>
					<th class="text-center" style="width:40px;">
						<input type="checkbox" class="form-check-input" id="isbankSelectAll" title="Tümünü seç/kaldır">
					</th>
					<th class="text-center">Tarih</th>
					<th class="text-end">Tutar</th>
					<th class="text-end">Bakiye</th>
					<th>Hesap No</th>
					<th>Açıklama</th>
					<th>ISL_Id</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>


<script>
	console.log('online-hesap-hareketleri.js inline loaded v=2026-01-10-1');
	// /views/... dizini prod'da public olmayabildiği için, public proxy endpoint kullanıyoruz.
	// Bu sayfa /admin/index.php?p=... içinde render edildiğinden, relatif olarak /admin/online-api.php güvenli.
	const ISBANK_ONLINE_API_URL = 'views/gelir-gider/online-api.php';
	console.log('ISBANK_ONLINE_API_URL:', ISBANK_ONLINE_API_URL);
	let isbankRows = [];
	let isbankRawXmlB64 = null;
	const CREDS_KEY = 'isbank_posmatik_creds_v1';

    console.log('ISBANK_ONLINE_API_URL:', ISBANK_ONLINE_API_URL);

	function toMoney(v) {
		if (v === null || v === undefined || v === '') return '';
		// basit format
		const n = Number(v);
		if (Number.isNaN(n)) return v;
		return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	$(document).ready(function() {
		// Kaydedilmiş bilgileri yükle
		try {
			const saved = localStorage.getItem(CREDS_KEY);
			if (saved) {
				const obj = JSON.parse(saved);
				if (obj && obj.uid) $('input[name="uid"]').val(obj.uid);
				if (obj && obj.pwd) $('input[name="pwd"]').val(obj.pwd);
				$('#rememberIsbankCreds').prop('checked', true);
			}
		} catch (e) {
			console.warn('remember creds load failed', e);
		}

		function persistCredsIfNeeded() {
			const remember = $('#rememberIsbankCreds').is(':checked');
			if (!remember) {
				localStorage.removeItem(CREDS_KEY);
				return;
			}
			const uid = String($('input[name="uid"]').val() || '').trim();
			const pwd = String($('input[name="pwd"]').val() || '');
			localStorage.setItem(CREDS_KEY, JSON.stringify({ uid, pwd }));
		}

		$('#rememberIsbankCreds').on('change', persistCredsIfNeeded);
		$('input[name="uid"], input[name="pwd"]').on('change', persistCredsIfNeeded);
   $(".flatpickr").flatpickr({
        locale: "tr",
        enableTime: true,
        dateFormat: "d.m.Y H:i:S",
     });


		function stripTagsNoRegex(s) {
			// Regex yerine basit bir state-machine ile tag silme.
			let out = '';
			let inTag = false;
			for (let i = 0; i < s.length; i++) {
				const ch = s[i];
				if (ch === '<') { inTag = true; continue; }
				if (ch === '>') { inTag = false; continue; }
				if (!inTag) out += ch;
			}
			return out;
		}

		function stripHtml(input) {
			const s = String(input || '');
			try {
				const doc = new DOMParser().parseFromString(s, 'text/html');
				return (doc.body && doc.body.textContent ? doc.body.textContent : '').trim();
			} catch (e) {
				return stripTagsNoRegex(s).trim();
			}
		}

		const table = $('#isbankTxTable').DataTable({
			pageLength: 25,
			ordering: true,
			order: [[0, 'desc']],
		});

		function escapeHtml(str) {
			return String(str ?? '')
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		function updateImportButtonState() {
			const selectedCount = $('#isbankTxTable tbody input.isbank-row-select:checked').length;
			$('#btnImportIsbank').prop('disabled', selectedCount === 0);
			// master checkbox state
			const total = $('#isbankTxTable tbody input.isbank-row-select').length;
			const master = $('#isbankSelectAll');
			if (total === 0) {
				master.prop('checked', false).prop('indeterminate', false);
				return;
			}
			if (selectedCount === 0) {
				master.prop('checked', false).prop('indeterminate', false);
			} else if (selectedCount === total) {
				master.prop('checked', true).prop('indeterminate', false);
			} else {
				master.prop('checked', false).prop('indeterminate', true);
			}
		}

		function autosizeTextarea(el) {
			if (!el) return;
			el.style.height = 'auto';
			el.style.height = (el.scrollHeight) + 'px';
		}

		// Tümünü seç/kaldır
		$('#isbankSelectAll').on('change', function() {
			const checked = $(this).is(':checked');
			$('#isbankTxTable tbody input.isbank-row-select').prop('checked', checked);
			updateImportButtonState();
		});

		// satır seçimi değişince import butonunu güncelle
		$(document).on('change', '#isbankTxTable tbody input.isbank-row-select', function() {
			updateImportButtonState();
		});

		// açıklama değişince state'i güncelle
		$(document).on('input', '#isbankTxTable tbody textarea.isbank-desc', function() {
			const islId = String($(this).data('isl-id') || '').trim();
			if (!islId) return;
			const val = String($(this).val() || '');
			autosizeTextarea(this);
			// isbankRows içinde eşle
			const idx = isbankRows.findIndex(r => String(r.isl_id) === islId);
			if (idx >= 0) {
				isbankRows[idx].description = val;
			}
		});

		$('#btnFetchIsbank').on('click', async function() {
			const btn = $(this);
			$btnHtml = btn.html();

            btn.prop('disabled', true);
            btn.html('<i class="fa fa-spinner fa-spin"></i> Yükleniyor...');

			$('#btnImportIsbank').prop('disabled', true);

			try {
				const form = document.getElementById('isbankQueryForm');
				const fd = new FormData(form);
				fd.append('action', 'isbank-online-cek');
				persistCredsIfNeeded();

				// Bu view /admin/index.php?p=... içinde render ediliyor.
				// API çağrısını router üzerinden yapmak için admin index'e relative gidiyoruz.
				const httpResp = await fetch(ISBANK_ONLINE_API_URL, {
					method: 'POST',
					body: fd
				});
				const rawText = await httpResp.text();
				let resp;
                
				try {
					resp = JSON.parse(rawText);
				} catch (e) {
					throw new Error(`Sunucudan JSON gelmedi (HTTP ${httpResp.status}, len ${rawText.length}). Yanıt (ilk 300): ${stripHtml(rawText).substring(0, 300)}`);
				}
				console.log('online-api fetch response:', {
					status: resp.status,
					message: resp.message,
					data: resp.data,
				});
				isbankRows = (resp.data && resp.data.rows) ? resp.data.rows : [];
				isbankRawXmlB64 = (resp.data && resp.data.raw_xml_b64) ? resp.data.raw_xml_b64 : null;

				table.clear();
				isbankRows.forEach(r => {
					const islId = String(r.isl_id || '').trim();
					const descVal = String(r.description || '');
					const descInput = `<textarea class="form-control form-control-sm isbank-desc" rows="1" data-isl-id="${escapeHtml(islId)}">${escapeHtml(descVal)}</textarea>`;
					const selectCb = `<input type="checkbox" class="form-check-input isbank-row-select" data-isl-id="${escapeHtml(islId)}" ${islId ? 'checked' : ''} />`;
					table.row.add([
						selectCb,
						r.date || '',
						toMoney(r.amount),
						toMoney(r.balance),
						r.account_no || '',
						descInput,
						islId
					]);
				});
				table.draw();
				// textarea autosize
				$('#isbankTxTable tbody textarea.isbank-desc').each(function() { autosizeTextarea(this); });
				updateImportButtonState();
                btn.html($btnHtml);

				swal.fire({
					icon: 'success',
					title: 'Başarılı',
					text: `Toplam ${isbankRows.length} hareket çekildi.`
				});
			} catch (e) {
				console.error('Isbank fetch error:', e);
				swal.fire({
					icon: 'error',
					title: 'Hata',
					text: e.message
				});
			} finally {
				btn.prop('disabled', false);
                btn.html($btnHtml);
			}
		});

		$('#btnImportIsbank').on('click', function() {
			if (!isbankRawXmlB64) {
				swal.fire({
					icon: 'warning',
					title: 'Uyarı',
					text: 'Önce sorgulama yapmalısınız.'
				});
				return;
			}

			swal.fire({
				title: 'Emin misiniz?',
				html: 'Kayıtlar veritabanına aktarılacak <br>(işlem id bazlı tekrarlar atlanır).',
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Aktar',
				cancelButtonText: 'Vazgeç'
			}).then(async (res) => {
				if (!res.isConfirmed) return;

				// seçili satırları topla
				const selectedIslIds = [];
				$('#isbankTxTable tbody input.isbank-row-select:checked').each(function() {
					const islId = String($(this).data('isl-id') || '').trim();
					if (islId) selectedIslIds.push(islId);
				});
				if (selectedIslIds.length === 0) {
					swal.fire({
						icon: 'warning',
						title: 'Uyarı',
						text: 'Aktarmak için en az bir satır seçmelisiniz.'
					});
					return;
				}

				// açıklama override map'i hazırla (isl_id => description)
				const descOverrides = {};
				selectedIslIds.forEach(id => {
					const row = isbankRows.find(r => String(r.isl_id) === id);
					if (row) {
						descOverrides[id] = String(row.description || '');
					}
				});

				const btn = $('#btnImportIsbank');
				btn.prop('disabled', true);

				try {
					const fd = new FormData();
					fd.append('action', 'isbank-online-import');
					fd.append('raw_xml_b64', isbankRawXmlB64);
					fd.append('selected_isl_ids', JSON.stringify(selectedIslIds));
					fd.append('desc_overrides', JSON.stringify(descOverrides));

					const resp = await fetch(ISBANK_ONLINE_API_URL, {
						method: 'POST',
						body: fd
					});
					console.log('online-api import response:', {
						status: resp.status,
						statusText: resp.statusText,
						url: resp.url,
						redirected: resp.redirected,
					});
					const rawText = await resp.text();
					if (!rawText) {
						console.warn('online-api import empty body');
					}
					let data;
					try {
						data = JSON.parse(rawText);
					} catch (parseErr) {
						const cleaned = stripHtml(rawText);
						throw new Error(`Sunucudan JSON gelmedi (HTTP ${resp.status}, len ${rawText.length}). Yanıt (ilk 300): ${cleaned.substring(0, 300)}`);
					}
					if (data.status !== 'success') {
						throw new Error(data.message || 'Aktarım başarısız.');
					}

					// Başarılı aktarım sonrası tabloyu temizle ve state'i sıfırla
					table.clear().draw();
					isbankRows = [];
					isbankRawXmlB64 = null;
					$('#btnImportIsbank').prop('disabled', true);

					swal.fire({
						icon: 'success',
						title: 'Aktarım tamamlandı',
						text: `Toplam: ${data.data.total} | Aktarılan: ${data.data.inserted} | Atlanan: ${data.data.skipped}`
					});
				} catch (e) {
					console.error('Isbank import error:', e);
					swal.fire({
						icon: 'error',
						title: 'Hata',
						text: e.message
					});
				} finally {
					btn.prop('disabled', false);
				}
			});
		});
	});
</script>
