$(document).ready(function () {
  $(".summernote").summernote({
    height: 425,
    lang :"tr-TR", // Set the language to Turkish
    placeholder: "İçeriği buraya yazın...",
    //fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New','Times New Roman', 'Verdana'],
    toolbar: [
      // [groupName, [list of button]]
      ['style', ['bold', 'italic', 'underline', 'clear']],
      ['font', ['strikethrough', 'superscript', 'subscript']],
      ['fontsize', ['fontsize']],
      ['color', ['color']],
      ['para', ['ul', 'ol', 'paragraph']],
      ['table', ['table']],
      ['height', ['height']],
      ['insert', ['link', 'picture', 'video']],
      ['view', ['fullscreen', 'codeview', 'help']],
     
      
    ],
 
    callbacks: {
      onInit: function () {
        
        $(".summernote-loader").hide();
        
      },
    
      onImageUpload: function(files) {
        var data = new FormData();
        data.append("file", files[0]);
        $.ajax({
          url: '/upload.php', // kendi upload endpoint'iniz
          cache: false,
          contentType: false,
          processData: false,
          data: data,
          type: "POST",
          success: function(url) {
            $('.summernote').summernote('insertImage', url);
          }
        });
      },
      onMediaDelete: function(target) {
        var imageUrl = target[0].src;
        $.ajax({
          url: '/delete-media.php',
          method: 'POST',
          data: { url: imageUrl },
          success: function(resp) {

          }
        });
      },
    },
  });
});
