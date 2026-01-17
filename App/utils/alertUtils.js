// Bu fonksiyon, verilen veriye göre bir SweetAlert bildirimi gösterir.
/**
 * Displays a SweetAlert notification with a title, message, and icon
 * based on the provided data.
 *
 * @param {Object} data - The data object containing information for the alert.
 * @param {string} data.status - The status of the alert, either "success" or "error".
 * @param {string} data.message - The message to display in the alert.
 *
 * @example
 * sweatalert({ status: "success", message: "Operation completed successfully!" });
 * sweatalert({ status: "error", message: "An error occurred during the operation." });
 */
export function sweatalert(data) {
    // Başlık, durumun başarılı ya da hata olmasına göre belirlenir.
    var title = data.status == "success" ? "Başarılı!" : "Hata!";
    
    // SweetAlert bildirimi oluşturulur.
    swal.fire({
        title: title, // Başlık
        text: data.message, // Mesaj
        icon: data.status == "success" ? "success" : "error", // İkon duruma göre ayarlanır.
        confirmButtonText: "Tamam", // Onay butonu metni
    });
}


