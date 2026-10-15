(function ($) {
    'use strict';
    let table = null, result = null, statusFilter = null;
    const statusInfo = {farkli:['Farklı','danger'],eslesen:['Eşleşen','success'],yalniz_muhasebe:['Yalnız Muhasebede','warning'],yalniz_sistem:['Yalnız Sistemde','info']};
    const esc = value => $('<div>').text(value == null ? '' : String(value)).html();
    const format = (value, type) => type === 'para' ? (Number(value || 0) / 100).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' ₺' : Number(value || 0).toLocaleString('tr-TR');
    function detail(row) {
        if (!row.farklar.length) return '<div class="p-3 text-muted font-size-12">Alan farkı bulunmadı.</div>';
        return `<div class="p-2"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Mutabakat Alanı</th><th class="text-end">Muhasebe</th><th class="text-end">Gönderilen</th><th class="text-end">Fark</th></tr></thead><tbody>${row.farklar.map(f=>`<tr><td>${esc(f.etiket)}</td><td class="text-end">${esc(format(f.muhasebe,f.tur))}</td><td class="text-end">${esc(format(f.sistem,f.tur))}</td><td class="text-end fw-semibold text-danger">${esc(format(f.fark,f.tur))}</td></tr>`).join('')}</tbody></table></div>`;
    }
    function render(data) {
        result=data;
        if(statusFilter){$.fn.dataTable.ext.search=$.fn.dataTable.ext.search.filter(fn=>fn!==statusFilter);statusFilter=null;}
        const cards=[['toplam','Toplam Sonuç','primary'],['farkli','Farklı','danger'],['yalniz_muhasebe','Yalnız Muhasebede','warning'],['yalniz_sistem','Yalnız Sistemde','info'],['eslesen','Eşleşen','success']];
        $('#muhasebeKarsilastirmaOzet').html(cards.map(([k,l,c])=>`<div class="col-6 col-lg"><div class="border rounded-3 p-2 h-100 bg-${c}-subtle"><div class="font-size-11 text-muted">${l}</div><div class="font-size-18 fw-bold text-${c}">${data.sayilar[k]}</div></div></div>`).join(''));
        $('#muhasebeDurumFiltreleri').html(`<button type="button" class="btn btn-sm btn-dark muhasebe-filter active" data-status="">Tümü</button>${cards.slice(1).map(([k,l,c])=>`<button type="button" class="btn btn-sm btn-subtle-${c} muhasebe-filter" data-status="${k}">${l} (${data.sayilar[k]})</button>`).join('')}`);
        if(table){table.destroy();$('#muhasebeKarsilastirmaTable tbody').empty();}
        data.sonuclar.forEach((row,index)=>{const [label,color]=statusInfo[row.durum];$('#muhasebeKarsilastirmaTable tbody').append(`<tr data-index="${index}" data-status="${row.durum}"><td class="fw-semibold">${esc(row.personel)}</td><td>${esc(row.tc||'—')}</td><td><span class="badge bg-${color}-subtle text-${color} border border-${color}-subtle rounded-pill">${label}</span></td><td class="text-center">${row.fark_sayisi}</td><td class="font-size-12">${esc(row.aciklama)}</td><td class="text-center"><button type="button" class="table-action-btn btn-subtle-primary btn-karsilastirma-detay" aria-label="Fark detayını aç"><i class="bx bx-chevron-down"></i></button></td></tr>`);});
        table=$('#muhasebeKarsilastirmaTable').DataTable(applyLengthStateSave({...getDatatableOptions(),responsive:false,pageLength:25,order:[[2,'asc'],[0,'asc']],columnDefs:[{targets:5,orderable:false,searchable:false}]}));
        $('#muhasebeKarsilastirmaSonuc').removeClass('d-none');
    }
    $(document).on('submit','#muhasebeKarsilastirmaForm',function(e){e.preventDefault();const b=$('#btnMuhasebeKarsilastir'),html=b.html();$('#muhasebeKarsilastirmaHata').addClass('d-none').text('');b.prop('disabled',true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Analiz ediliyor...');$.ajax({url:'views/bordro/bordro-karsilastir.php',method:'POST',data:new FormData(this),processData:false,contentType:false,dataType:'json'}).done(r=>{if(r.status!=='success')throw new Error(r.message||'Karşılaştırma tamamlanamadı.');render(r.data);}).fail(xhr=>$('#muhasebeKarsilastirmaHata').removeClass('d-none').text(xhr.responseJSON?.message||'Excel karşılaştırması tamamlanamadı.')).always(()=>b.prop('disabled',false).html(html));});
    $(document).on('click','.muhasebe-filter',function(){$('.muhasebe-filter').removeClass('active');$(this).addClass('active');const wanted=this.dataset.status;if(statusFilter)$.fn.dataTable.ext.search=$.fn.dataTable.ext.search.filter(fn=>fn!==statusFilter);statusFilter=(settings,data,index)=>settings.nTable.id!=='muhasebeKarsilastirmaTable'||!wanted||$(settings.aoData[index].nTr).data('status')===wanted;$.fn.dataTable.ext.search.push(statusFilter);table.draw();});
    $(document).on('click','#muhasebeKarsilastirmaTable .btn-karsilastirma-detay',function(){const tr=$(this).closest('tr'),row=table.row(tr),index=Number(tr.data('index'));if(row.child.isShown()){row.child.hide();$(this).find('i').attr('class','bx bx-chevron-down');}else{row.child(detail(result.sonuclar[index]),'bg-light').show();$(this).find('i').attr('class','bx bx-chevron-up');}});
})(jQuery);
