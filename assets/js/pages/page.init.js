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
$(".select2").select2();



$(".modal .select2").each(function () {
  $(this).select2({ dropdownParent: $(this).parent() });
});
}
$(".flatpickr:not(.time-input)").flatpickr({
  locale: "tr",
  dateFormat: "d.m.Y"
});

document.addEventListener("DOMContentLoaded", function () {
  // Pattern (Money)
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
});

$("#finansalIslemModal").on("hidden.bs.modal", function () {
  // Focus'u güvenli bir elemente taşı
  document.querySelector("body").focus();
});
