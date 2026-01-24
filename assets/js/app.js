function confirmAndDelete(url, formData, buttonElement, tableId) {
  swal
    .fire({
      title: "Emin misiniz?",
      text: "Bu işlem geri alınamaz!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Evet",
      cancelButtonText: "Hayır",
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
    })
    .then((result) => {
      if (result.isConfirmed) {
        fetch(url, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            const title = data.status == "success" ? "Başarılı" : "Hata";
            const table = $(`#${tableId}`).DataTable();
            table.row(buttonElement.closest("tr")).remove().draw(false);

            swal.fire({
              title: title,
              text: data.message,
              icon: data.status,
              confirmButtonText: "Tamam",
            });
          });
      }
    });
}

/**firma_id'de değişikliği dinle */
$(document).on('change', '#firma_id', function() {
  var firma_id = $(this).val();
 const params = new URLSearchParams(window.location.search);
const p = params.get('p');
  window.location.href = '/set-session.php?firma_id=' + firma_id + '&p=' + p;

});

//number classına sahip inputlara sadece sayısal değer girilmesini sağlar
//Örnek kullanım: <input type="text" class="number">
var numberInputs = document.querySelectorAll(".number");
numberInputs.forEach(function (input) {
  input.addEventListener("input", function () {
    this.value = this.value.replace(/[^0-9]/g, "");
  });
});

//text classına sahip inputlara sadece harf ve boşluk girilmesini sağlar
//Örnek kullanım: <input type="text" class="text">
var textInputs = document.querySelectorAll(".text");
textInputs.forEach(function (input) {
  input.addEventListener("input", function () {
    this.value = this.value.replace(/[^a-zA-ZğüşöçıİĞÜŞÖÇ\s]/g, "");
  });
});

$.validator.setDefaults({
  errorPlacement: function (error, element) {
    // Hata mesajını input grubunun altına ekle
    error.addClass("text-danger"); // Hata mesajına stil ekleyin
    if (element.closest(".form-floating").length) {
      element.closest(".form-floating").after(error); // Input grubunun altına ekle
    } else {
      element.after(error); // Diğer durumlarda input'un altına ekle
    }
  },
  highlight: function (element) {
    // Hatalı input alanına kırmızı border ekle
    $(element).addClass("is-invalid");
    // Input'un en yakın form-floating kapsayıcısına is-invalid sınıfını ekle
    $(element).closest(".form-floating").addClass("is-invalid");
  },
  unhighlight: function (element) {
    // Hatalı input alanından kırmızı border'ı kaldır
    $(element).removeClass("is-invalid");
    // Input'un en yakın input-group kapsayıcısından is-invalid sınıfını kaldır
    $(element).closest(".form-floating").removeClass("is-invalid");
  },
});

// $(".select2").on("change", function () {
//   $(this).valid(); // Sadece bu alanı tekrar valide eder
// });


/**
 * Başlangıç tarihine göre aynı ayın son gününü hesaplar (DD.MM.YYYY)
 * @param {string} baslangicTarihi
 * @returns {string} DD.MM.YYYY
 */
function ayinSonGununuGetir(baslangicTarihi) {
    if (!baslangicTarihi) return '';

    let parts = baslangicTarihi.split('.');
    if (parts.length !== 3) return '';

    let gun = parseInt(parts[0], 10);
    let ay  = parseInt(parts[1], 10) - 1;
    let yil = parseInt(parts[2], 10);

    let lastDay = new Date(yil, ay + 1, 0);

    let bitisGun = String(lastDay.getDate()).padStart(2, '0');
    let bitisAy  = String(lastDay.getMonth() + 1).padStart(2, '0');
    let bitisYil = lastDay.getFullYear();

    return `${bitisGun}.${bitisAy}.${bitisYil}`;
}
