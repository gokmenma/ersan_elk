let url ="views/uye/api.php"

$(document).on("click", ".uyelik_onayla", function () {
    let id = $(this).data("id");

    var formData = new FormData();
  
    formData.append("id", id);
    formData.append("action", "uyelik_onayla");
  
    fetch(url, {
      method: "POST",
      body: formData
    })
      .then((response) => response.json())
      .then((data) => {
        console.log(data);
        title = data.status == "success" ? "Başarılı" : "Hata";
        swal
          .fire({
            title: title,
            text: data.message,
            icon: data.status,
            confirmButtonText: "Tamam"
          })
          .then((result) => {
            if (result.isConfirmed) {
              location.reload();
            }
          });
      });
  });
  
  
  $(document).on("click", ".uyelik_talep_sil", function () {
    let id = $(this).data("id");
  
    swal.fire({
      title: "Emin misiniz?",
      text: "Bu işlem geri alınamaz!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Evet, sil!",
      cancelButtonText: "İptal"
    }).then((result) => {
      if (result.isConfirmed) {
        var formData = new FormData();
  
        formData.append("id", id);
        formData.append("action", "uyelik_talep_sil");
  
        fetch(url, {
          method: "POST",
          body: formData
        })
          .then((response) => response.json())
          .then((data) => {
            console.log(data);
            title = data.status == "success" ? "Başarılı" : "Hata";
            swal
              .fire({
                title: title,
                text: data.message,
                icon: data.status,
                confirmButtonText: "Tamam"
              })
              .then((result) => {
                if (result.isConfirmed) {
                  location.reload();
                }
              });
          });
      }
    });
  
  });