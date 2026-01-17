let url = "views/kullanici/api.php";
let row;

$(document).on("click", "#userAddBtn", function () {
  row = null; // Yeni kullanıcı eklerken row'u sıfırla
  getUserModal();
});

$(document).on("click", ".kullanici-duzenle", function () {
  var id = $(this).data("id");
  table = $("#usersTable").DataTable();
  row = table.row($(this).closest("tr"));
  getUserModal(id);
});

function getUserModal(id = 0) {
  var url = "views/kullanici/modal/user-modal.php";

  $.get(
    url,
    {
      id: id,
    },
    function (data) {
      $(".user-modal-content").html(data);
      feather.replace();
      $(".select2").select2({
        dropdownParent: $("#userModal .modal-content"),
      });
    }
  ).fail(function () {
    $(".user-modal-content").html(
      "<div class='alert alert-danger'>Modal içeriği yüklenemedi.</div>"
    );
  });
  $("#userModal").modal("show");
}

$(document).on("click", "#userSaveBtn", function () {
  var form = $("#userForm");
  var userId = form.find("input[name='user_id']").val();
  var isUpdateMode = userId && userId != 0;
  var passwordField = form.find("input[name='password']");
  var user_roles = $("#roles option:selected").val();



  form.validate({
    rules: {
      user_name: {
        required: true,
        minlength: 3,
        maxlength: 50,
      },
      adi_soyadi: {
        required: true,
        minlength: 3,
        maxlength: 50,
      },
      email_adresi: {
        required: true,
        email: true,
      },
      telefon: {
        required: true,
      },
          password: {
        // 'required' kuralını bir fonksiyon olarak tanımla
        required: function(element) {
          // Eğer yeni kullanıcı ekleniyorsa (güncelleme modunda değilsek) parola zorunludur.
          if (!isUpdateMode) {
            return true;
          }
          // Eğer güncelleme modundaysak VE parola alanı doluysa, zorunludur.
          // Boş bırakılmışsa zorunlu değildir.
          return $(element).val().trim() !== "";
        },
        minlength: 8, // Bu kural, eğer parola girilmişse her zaman geçerli olur
      },
      roles: {
        required: true,
      },
      gorevi: {
        required: true,
      },
      unvani: {
        required: true,
      },
    },
    messages: {
      user_name: {
        required: "Kullanıcı adı zorunludur.",
        minlength: "Kullanıcı adı en az 3 karakter olmalıdır.",
        maxlength: "Kullanıcı adı en fazla 50 karakter olmalıdır.",
      },
      adi_soyadi: {
        required: "Adı ve soyadı zorunludur.",
        minlength: "Adı ve soyadı en az 3 karakter olmalıdır.",
        maxlength: "Adı ve soyadı en fazla 50 karakter olmalıdır.",
      },
      email_adresi: {
        required: "E-posta adresi zorunludur.",
        email: "Lütfen geçerli bir e-posta adresi giriniz.",
      },
      telefon: {
        required: "Telefon numarası zorunludur.",
      },
      password: {
        required: "Parola zorunludur.",
        minlength: "Parola en az 8 karakter olmalıdır.",
      },
      roles: {
        required: "Rol seçimi zorunludur.",
      },
      gorevi: {
        required: "Görevi zorunludur.",
      },
      unvani: {
        required: "Unvanı zorunludur.",
      },
    },
  });

  if (!form.valid()) {
    return false;
  }

  var formData = new FormData(form[0]);
  formData.append("action", "kullanici-kaydet");
  formData.append("roles", user_roles);

  fetch(url, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      //console.log(data);
      
      var title = data.status == "success" ? "Başarılı" : "Hata";
      
      //tabloya yeni bir satır ekle
      if (data.status == "success") {
        if (row && row.node()) {
          $(row.node()).html(data.rowData);
        } else {
          table.row.add($(data.rowData)).draw(false);
        }
      }

      swal.fire({
        title: title,
        text: data.message,
        icon: data.status,
        confirmButtonText: "Tamam",
      });
    });
});

$(document).on("click", ".kullanici-sil", function () {
  var id = $(this).data("id");
  var row = $(this).closest("tr");
  swal
    .fire({
      title: "Silmek istediğinize emin misiniz?",
      text: "Bu işlem geri alınamaz ve kullanıcı silinecektir!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet, sil",
      cancelButtonText: "Hayır, iptal",
    })
    .then((result) => {
      if (result.isConfirmed) {
        $.post(
          url,
          {
            action: "kullanici-sil",
            id: id,
          },
          function (data) {
            if (data.status == "success") {
              table.row(row).remove().draw();
              swal.fire("Silindi!", data.message, "success");
            } else {
              swal.fire("Hata!", data.message, "error");
            }
          },
          "json"
        );
      }
    });
});
