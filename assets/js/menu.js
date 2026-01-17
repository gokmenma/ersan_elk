// Add this after menu initialization
$(document).ready(function() {
    // Get p parameter from URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('p');

    if (currentPage) {
        // Find the menu link that matches the page
        $('a[href*="' + currentPage + '"]').each(function() {
            // Get parent li
            const $menuItem = $(this).parent('li');
            
            // Expand parent ul if exists
            const $parentUl = $menuItem.closest('ul.submenu');
            if ($parentUl.length) {
                $parentUl.addClass('mm-show');
                $parentUl.parent('li').addClass('mm-active');
            }
            
            // Add active class to current menu item
            $menuItem.addClass('mm-active');
        });
    }
});