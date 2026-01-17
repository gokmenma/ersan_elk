/*
Template Name: Dason - Admin & Dashboard Template
Author: Themesbrand
Website: https://themesbrand.com/
Contact: themesbrand@gmail.com
File: Form editor Init Js File
*/

let editor;
ClassicEditor.create(document.querySelector("#icerik"))
  .then((newEditor) => {
    editor = newEditor;
    editor.ui.view.editable.element.style.height = "200px";
  })
  .catch((error) => {
    console.error(error);
  });
