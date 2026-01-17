let url = "views/uye/api.php";

import { tableRowAddOrUpdate } from "../../../App/utils/tableUtils.js";
import { sweatalert } from "../../../App/utils/alertUtils.js";

//Yeni not eklenecekse formu temizle
$(document).on("click", "#notEkle", function () {
  $("#note_id").val(0);
  $("#note_title").val("");
  $("#note").val("");
  $("#notesModal").modal("show");
});

//note kaydet
$(document).on("click", "#notesKaydet", function () {
  var form = $("#notesModalForm");
  var note_id = $("#note_id").val();
  var uye_id = $("#uye_id").val();

  if (uye_id == "" || uye_id == 0) {
    Swal.fire({
      title: "Hata",
      text: "Üye bilgileri kaydedilmemiş",
      icon: "error",
      confirmButtonText: "Tamam",
    });
    return false;
  }

  let formData = new FormData(form[0]);
  formData.append("action", "notes-kaydet");
  formData.append("uye_id", uye_id);

  // for (var pair of formData.entries()) {
  // console.log(pair[0] + ", " + pair[1]);
  // }
  //Preloader
  Pace.restart();
  
    fetch(url, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        console.log(data);
        tableRowAddOrUpdate("#notesTable", data, note_id);
        sweatalert(data);
      });
  
});

//not bilgisi getir
$(document).on("click", ".note-duzenle", function () {
  var note_id = $(this).data("id");
  var form = $("#notesModalForm");

  var formData = new FormData(form[0]);
  formData.append("action", "not-bilgisi-getir");
  formData.append("note_id", note_id);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.status == "success") {
        $("#note_id").val(note_id);
        $("#note_title").val(data.note_title);
        $("#note").val(data.not_aciklama);

        $("#notesModal").modal("show");
      }
    });
});

//note sil
$(document).on("click", ".note-sil", function () {
  let note_id = $(this).data("id");
  let buttonElement = $(this); // Store reference to the clicked button

  Swal.fire({
    title: "Emin misiniz?",
    html: `Notu silmek istediğinize emin misiniz?`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Evet",
    cancelButtonText: "Hayır",
  }).then((result) => {
    if (result.isConfirmed) {
      var formData = new FormData();
      formData.append("action", "note-sil");
      formData.append("note_id", note_id);

      fetch(url, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status == "success") {
            let table = $("#notesTable").DataTable();
            table.row(buttonElement.closest("tr")).remove().draw(false);
            Swal.fire("Başarılı!", `Not başarıyla silindi.`, "success");
          }
        });
    }
  });
});
