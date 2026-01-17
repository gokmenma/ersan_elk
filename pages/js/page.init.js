if (typeof $ == "undefined") {
  throw new Error("This application's JavaScript requires jQuery");
}
if (typeof swal == "undefined") {
  throw new Error("This application's JavaScript requires Sweet Alert");
}
if (typeof flatpickr == "undefined") {
  throw new Error("This application's JavaScript requires Flatpickr");
}

// APP START
// -----------------------------------

if ($(".select2").length > 0) {
 
  $(".select2").select2({
  });

  $(".modal .select2").each(function () {
    $(this).select2({ 
      dropdownParent: $(this).parent() ,
      tags : true,
      language: "tr"
    });
  });
}


if($(".flatpickr").length > 0) {
  //.flatpickr sınıfına sahip alanlarda tarih formatına izin verir
  $(document).on("focus", ".flatpickr:not(.time-input)", function () {
   $(this).inputmask("datetime", {
    alias: "datetime",
    // inputFormat: "dd.MM.yyyy",
    dateFormat: "d.m.Y",
    placeholder: "gg.aa.yyyy",
    showMaskOnHover: false,
    showMaskOnFocus: false,
    length: 10,
    regex: "[0-9.]", // sadece rakam ve nokta karakterine izin verir
  });
 });
 }
 


document.addEventListener("DOMContentLoaded", function () {
  // Pattern (Money)
  if($(".money").length > 0){
  var moneyInputs = document.querySelectorAll(".money");
  moneyInputs.forEach(function (input) {
    var currencyMask = IMask(input, {
      mask: "₺num",
      blocks: {
        num: {
          mask: Number,
          thousandsSeparator: "."
        }
      }
    });
  });
}
  // Pattern (Phone)
  if($(".phone").length > 0){
  var phoneInputs = document.querySelectorAll(".phone");
  phoneInputs.forEach(function (input) {
    var dynamicMask = IMask(input, {
      mask: [
        {
          mask: "+{90}(000)000 00 00"
        },
        {
          mask: /^\S*@?\S*$/
        }
      ]
    });
  });
}
});

$("#finansalIslemModal").on("hidden.bs.modal", function () {
  // Focus'u güvenli bir elemente taşı
  document.querySelector("body").focus();
});

if($("#ckeditor-classic").length > 0){
let editor;
ClassicEditor.create(document.querySelector("#ckeditor-classic"))
  .then((newEditor) => {
    editor = newEditor;
    editor.ui.view.editable.element.style.height = "200px";
  })
  .catch((error) => {
    console.error(error);
  });
}



// jQuery Validate için birleştirilmiş özel tarih formatı metodu (gg.aa.yyyy)
$.validator.addMethod("dateFormatDMY", function(value, element) {
  // jQuery Validate'in kendi optional kontrolü
  if (this.optional(element)) {
      return true;
  }

  // Boş string veya null/undefined ise geçersiz say
  // this.optional(element) zaten boş değerleri ele alır,
  // ancak ek bir kontrol olarak kalabilir veya kaldırılabilir.
  if (!value) { 
      return false;
  }

  // dd.mm.yyyy formatını regex ile kontrol et
  if (!/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
      return false;
  }

  // Tarihi parçala
  var parts = value.split(".");
  var day = parseInt(parts[0], 10);
  var month = parseInt(parts[1], 10);
  var year = parseInt(parts[2], 10);

  // Temel geçerlilik kontrolleri (yıl ve ay için)
  if (year < 1000 || year > 3000 || month === 0 || month > 12) {
      return false;
  }

  var monthLength = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];

  // Artık yılı (leap year) kontrol et
  if (year % 400 === 0 || (year % 100 !== 0 && year % 4 === 0)) {
      monthLength[1] = 29; // Şubat ayını 29 gün yap
  }

  // Günün, ilgili ay için geçerli bir gün olup olmadığını kontrol et
  return day > 0 && day <= monthLength[month - 1];
}, "Lütfen gg.aa.yyyy formatında geçerli bir tarih giriniz.");
