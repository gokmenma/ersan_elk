 <footer class="footer">
     <div class="container-fluid">
         <div class="row">
             <div class="col-sm-6">
                 <script>
                     document.write(new Date().getFullYear())
                 </script> © Ersan Elektrik.
             </div>
             <div class="col-sm-6">
                 <div class="text-sm-end d-none d-sm-block">
                     Dizayn ve geliştirme <a href="#!" class="text-decoration-underline">mbeyazilim</a>
                 </div>
             </div>
         </div>
     </div>
  </footer>
  <button class="orientation-toggle-btn"
      onclick="document.documentElement.removeAttribute('data-orientation'); localStorage.setItem('data-orientation', 'portrait'); window.location.reload();"
      style="display: none; position: fixed; bottom: 85px; right: 25px; z-index: 1000; width: 50px; height: 50px; border-radius: 50%; background: var(--bs-primary); color: #fff; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.3); align-items: center; justify-content: center;">
      <i class="mdi mdi-phone-rotate-portrait" style="font-size: 24px;"></i>
  </button>

  <script>
      // Show/hide based on orientation
      function updateOrientationBtn() {
          const btn = document.querySelector('.orientation-toggle-btn');
          if (btn) {
              btn.style.display = document.documentElement.hasAttribute('data-orientation') ? 'flex' : 'none';
          }
      }
      // Check on load
      document.addEventListener('DOMContentLoaded', updateOrientationBtn);
  </script>