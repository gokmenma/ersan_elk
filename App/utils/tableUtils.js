

/**
 * Belirtilen tabloya bir satır ekler veya mevcut bir satırı günceller.
 *
 * @function
 * @param {string} tableID - Güncelleme yapılacak veya satır eklenecek tablonun ID'si.
 * @param {Object} data - İşlem sonucunu ve eklenecek/güncellenecek satır verilerini içeren nesne.
 * @param {string} data.status - İşlem durumunu belirten değer ("success" beklenir).
 * @param {string} data.rowData - Eklenecek veya güncellenecek satırın HTML içeriği.
 * @param {number} [id=0] - Güncellenecek satırın ID'si. Yeni bir satır eklemek için 0 gönderilebilir.
 *
 * @description
 * Eğer `id` parametresi 0 değilse, belirtilen ID'ye sahip mevcut satır kaldırılır ve yerine yeni satır eklenir.
 * Eğer `id` 0 ise, doğrudan yeni bir satır eklenir.
 *
 * @requires jQuery
 * @requires DataTables
 */
export function tableRowAddOrUpdate(tableID, data, id = 0) {
    if (data.status == "success") {
      var table = $(tableID).DataTable();
      if (id != 0) {
        let rowMode = table.row(`tr[data-id="${id}"]`)[0];
        if (rowMode) {
          table.row(rowMode).remove().draw();
        }
      }
      table.row.add($(data.rowData)).draw(false);
    }
  }
  