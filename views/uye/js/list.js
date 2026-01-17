let url="views/uye/api.php";

//Üye Sil
$(document).on("click", ".uye-sil", function () {
    let id = $(this).data("id");
    let uyeAdi = $(this).data("name");
    let buttonElement = $(this); // Store reference to the clicked button
  
    swal
      .fire({
        title: "Emin misiniz?",
        html: `${uyeAdi} <br> <br> adlı üyeyi silmek istediğinize emin misiniz?`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Evet",
        cancelButtonText: "Hayır"
      })
      .then((result) => {
        if (result.isConfirmed) {
          var formData = new FormData();
          formData.append("action", "uye_sil");
          formData.append("id", id);
          
          //Preloader
          Pace.restart();

          fetch(url, {
            method: "POST",
            body: formData
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.status == "success") {
                let table = $("#membersTable").DataTable();
                table.row(buttonElement.closest("tr")).remove().draw(false);
                swal.fire(
                  "Üye Silindi",
                  `${uyeAdi} adlı üye başarıyla silindi.`,
                  "success"
                );
              }
            });
        }
      });
  });
  