/*
Template Name: Dason - Bootstrap 5 Admin & Dashboard Template
Author: Themesbrand
Version: 1.4.0.
Website: https://themesbrand.com/
Contact: themesbrand@gmail.com
File: Main Js File
*/

(function ($) {
  "use strict";

  var language = localStorage.getItem("Dason-language");
  // Default Language
  var default_lang = "en";

  function setLanguage(lang) {
    if (document.getElementById("header-lang-img")) {
      if (lang == "en") {
        document.getElementById("header-lang-img").src =
          "assets/images/flags/us.jpg";
      } else if (lang == "sp") {
        document.getElementById("header-lang-img").src =
          "assets/images/flags/spain.jpg";
      } else if (lang == "gr") {
        document.getElementById("header-lang-img").src =
          "assets/images/flags/germany.jpg";
      } else if (lang == "it") {
        document.getElementById("header-lang-img").src =
          "assets/images/flags/italy.jpg";
      } else if (lang == "ru") {
        document.getElementById("header-lang-img").src =
          "assets/images/flags/russia.jpg";
      }
      localStorage.setItem("Dason-language", lang);
      language = localStorage.getItem("Dason-language");
      getLanguage();
    }
  }

  // Multi language setting
  function getLanguage() {
    language == null ? setLanguage(default_lang) : false;
    $.getJSON("assets/lang/" + language + ".json", function (lang) {
      $("html").attr("lang", language);
      $.each(lang, function (index, val) {
        index === "head" ? $(document).attr("title", val["title"]) : false;
        $("[data-key='" + index + "']").text(val);
      });
    });
  }

  function initMetisMenu() {
    //metis menu
    $("#side-menu").metisMenu();
  }

  function initCounterNumber() {
    var counter = document.querySelectorAll(".counter-value");
    var speed = 250; // The lower the slower
    counter.forEach(function (counter_value) {
      function updateCount() {
        var target = +counter_value.getAttribute("data-target");
        var count = +counter_value.innerText;
        var inc = target / speed;
        if (inc < 1) {
          inc = 1;
        }
        // Check if target is reached
        if (count < target) {
          // Add inc to count and output in counter_value
          counter_value.innerText = (count + inc).toFixed(0);
          // Call function every ms
          setTimeout(updateCount, 1);
        } else {
          counter_value.innerText = target;
        }
      }
      updateCount();
    });
  }

  function initLeftMenuCollapse() {
    var currentSIdebarSize = document.body.getAttribute("data-sidebar-size");
    $(window).on("load", function () {
      $(".switch").on("switch-change", function () {
        toggleWeather();
      });

      if (window.innerWidth >= 1024 && window.innerWidth <= 1366) {
        document.body.setAttribute("data-sidebar-size", "sm");
        updateRadio("sidebar-size-small");
      }
    });

    $("#vertical-menu-btn").on("click", function (event) {
      event.preventDefault();
      $("body").toggleClass("sidebar-enable");
      if ($(window).width() >= 992) {
        if (currentSIdebarSize == null) {
          document.body.getAttribute("data-sidebar-size") == null ||
          document.body.getAttribute("data-sidebar-size") == "lg"
            ? document.body.setAttribute("data-sidebar-size", "sm")
            : document.body.setAttribute("data-sidebar-size", "lg");
        } else if (currentSIdebarSize == "md") {
          document.body.getAttribute("data-sidebar-size") == "md"
            ? document.body.setAttribute("data-sidebar-size", "sm")
            : document.body.setAttribute("data-sidebar-size", "md");
        } else {
          document.body.getAttribute("data-sidebar-size") == "sm"
            ? document.body.setAttribute("data-sidebar-size", "lg")
            : document.body.setAttribute("data-sidebar-size", "sm");
        }
      }
    });
  }

  function initActiveMenu() {
    // === following js will activate the menu in left side bar based on url ====
    $("#sidebar-menu a").each(function () {
      var pageUrl = window.location.href.split(/[?#]/)[0];
      if (this.href == pageUrl) {
        $(this).addClass("active");
        $(this).parent().addClass("mm-active"); // add active to li of the current link
        $(this).parent().parent().addClass("mm-show");
        $(this).parent().parent().prev().addClass("mm-active"); // add active class to an anchor
        $(this).parent().parent().parent().addClass("mm-active");
        $(this).parent().parent().parent().parent().addClass("mm-show"); // add active to li of the current link
        $(this)
          .parent()
          .parent()
          .parent()
          .parent()
          .parent()
          .addClass("mm-active");
      }
    });
  }

  function initMenuItemScroll() {
    // focus active menu in left sidebar
    $(document).ready(function () {
      if (
        $("#sidebar-menu").length > 0 &&
        $("#sidebar-menu .mm-active .active").length > 0
      ) {
        var activeMenu = $("#sidebar-menu .mm-active .active").offset().top;
        if (activeMenu > 300) {
          activeMenu = activeMenu - 300;
          $(".vertical-menu .simplebar-content-wrapper").animate(
            {
              scrollTop: activeMenu,
            },
            "slow",
          );
        }
      }
    });
  }

  function initHoriMenuActive() {
    $(".navbar-nav a").each(function () {
      var pageUrl = window.location.href.split(/[?#]/)[0];
      if (this.href == pageUrl) {
        $(this).addClass("active");
        $(this).parent().addClass("active");
        $(this).parent().parent().addClass("active");
        $(this).parent().parent().parent().addClass("active");
        $(this).parent().parent().parent().parent().addClass("active");
        $(this).parent().parent().parent().parent().parent().addClass("active");
        $(this)
          .parent()
          .parent()
          .parent()
          .parent()
          .parent()
          .parent()
          .addClass("active");
      }
    });
  }

  function initFullScreen() {
    $('[data-toggle="fullscreen"]').on("click", function (e) {
      e.preventDefault();
      $("body").toggleClass("fullscreen-enable");
      if (
        !document.fullscreenElement &&
        /* alternative standard method */ !document.mozFullScreenElement &&
        !document.webkitFullscreenElement
      ) {
        // current working methods
        if (document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
          document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
          document.documentElement.webkitRequestFullscreen(
            Element.ALLOW_KEYBOARD_INPUT,
          );
        }
      } else {
        if (document.cancelFullScreen) {
          document.cancelFullScreen();
        } else if (document.mozCancelFullScreen) {
          document.mozCancelFullScreen();
        } else if (document.webkitCancelFullScreen) {
          document.webkitCancelFullScreen();
        }
      }
    });
    document.addEventListener("fullscreenchange", exitHandler);
    document.addEventListener("webkitfullscreenchange", exitHandler);
    document.addEventListener("mozfullscreenchange", exitHandler);

    function exitHandler() {
      if (
        !document.webkitIsFullScreen &&
        !document.mozFullScreen &&
        !document.msFullscreenElement
      ) {
        $("body").removeClass("fullscreen-enable");
      }
    }
  }

  function initDropdownMenu() {
    if (document.getElementById("topnav-menu-content")) {
      var elements = document
        .getElementById("topnav-menu-content")
        .getElementsByTagName("a");
      for (var i = 0, len = elements.length; i < len; i++) {
        elements[i].onclick = function (elem) {
          if (elem && elem.target && elem.target.getAttribute("href") === "#") {
            elem.target.parentElement.classList.toggle("active");
            if (elem.target.nextElementSibling)
              elem.target.nextElementSibling.classList.toggle("show");
          }
        };
      }
      window.addEventListener("resize", updateMenu);
    }
  }

  function updateMenu() {
    var elements = document
      .getElementById("topnav-menu-content")
      .getElementsByTagName("a");
    for (var i = 0, len = elements.length; i < len; i++) {
      if (
        elements[i] &&
        elements[i].parentElement &&
        elements[i].parentElement.getAttribute("class") ===
          "nav-item dropdown active"
      ) {
        elements[i].parentElement.classList.remove("active");
        if (elements[i].nextElementSibling)
          elements[i].nextElementSibling.classList.remove("show");
      }
    }
  }

  function initComponents() {
    // tooltip
    var tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]'),
    );
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // popover
    var popoverTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="popover"]'),
    );
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl);
    });

    // toast
    var toastElList = [].slice.call(document.querySelectorAll(".toast"));
    var toastList = toastElList.map(function (toastEl) {
      return new bootstrap.Toast(toastEl);
    });
  }

  function initPreloader() {
    $(window).on("load", function () {
      $("#status").fadeOut();
      $("#preloader").delay(350).fadeOut("slow");
    });
  }

  function initSettings() {
    if (window.sessionStorage) {
      var alreadyVisited = sessionStorage.getItem("is_visited");
      if (!alreadyVisited) {
        sessionStorage.setItem("is_visited", "layout-ltr");
      } else {
        $("#" + alreadyVisited).prop("checked", true);
        // changeDirection(alreadyVisited);
      }
    }
    // show password input value
    $("#password-addon").on("click", function () {
      if ($(this).siblings("input").length > 0) {
        $(this).siblings("input").attr("type") == "password"
          ? $(this).siblings("input").attr("type", "input")
          : $(this).siblings("input").attr("type", "password");
      }
    });
  }

  function initLanguage() {
    // Auto Loader
    if (language && language != "null" && language !== default_lang)
      setLanguage(language);
    $(".language").on("click", function (e) {
      setLanguage($(this).attr("data-lang"));
    });
  }

  function initFeathericon() {
    feather.replace();
  }

  function initCheckAll() {
    $("#checkAll").on("change", function () {
      $(".table-check .form-check-input").prop(
        "checked",
        $(this).prop("checked"),
      );
    });
    $(".table-check .form-check-input").change(function () {
      if (
        $(".table-check .form-check-input:checked").length ==
        $(".table-check .form-check-input").length
      ) {
        $("#checkAll").prop("checked", true);
      } else {
        $("#checkAll").prop("checked", false);
      }
    });
  }

  function updateRadio(radioId) {
    if (radioId !== null) document.getElementById(radioId).checked = true;
  }

  function layoutSetting() {
    var html = document.getElementsByTagName("html")[0];
    var body = document.getElementsByTagName("body")[0];

    // Re-apply settings from localStorage to ensure they are active
    const bodyAttrs = [
      "data-layout",
      "data-layout-size",
      "data-layout-scrollable",
      "data-topbar",
      "data-sidebar-size",
      "data-sidebar",
    ];
    bodyAttrs.forEach((name) => {
      const value = localStorage.getItem(name);
      if (value) {
        body.setAttribute(name, value);
      }
    });

    // Apply orientation
    const orientation = localStorage.getItem("data-orientation");
    if (orientation === "landscape") {
      document.documentElement.setAttribute("data-orientation", "landscape");
    } else {
      document.documentElement.removeAttribute("data-orientation");
    }

    // Apply font-family to html element (CSS selectors target html)
    const savedFont = localStorage.getItem("data-font-family");
    if (savedFont) {
      html.setAttribute("data-font-family", savedFont);
    }

    // right side-bar toggle
    $(".right-bar-toggle").on("click", function (e) {
      $("body").toggleClass("right-bar-enabled");
    });

    $("#mode-setting-btn").on("click", function (e) {
      if (
        html.hasAttribute("data-bs-theme") &&
        html.getAttribute("data-bs-theme") == "dark"
      ) {
        html.setAttribute("data-bs-theme", "light");
        localStorage.setItem("data-bs-theme", "light");
        updateRadio("layout-mode-light");

        // Only set topbar to light if it was forced to dark by dark mode toggle
        if (
          localStorage.getItem("data-topbar") == "dark" ||
          !localStorage.getItem("data-topbar")
        ) {
          document.body.setAttribute("data-topbar", "light");
          localStorage.setItem("data-topbar", "light");
          updateRadio("topbar-color-light");
        }
      } else {
        html.setAttribute("data-bs-theme", "dark");
        localStorage.setItem("data-bs-theme", "dark");
        updateRadio("layout-mode-dark");

        // Keep existing topbar if it's not simply 'light'
        if (
          body.getAttribute("data-topbar") == "light" ||
          !body.getAttribute("data-topbar")
        ) {
          document.body.setAttribute("data-topbar", "dark");
          localStorage.setItem("data-topbar", "dark");
          updateRadio("topbar-color-dark");
        }

        // Dark mode sidebar defaults to dark
        if (
          body.getAttribute("data-sidebar") == "light" ||
          !body.getAttribute("data-sidebar")
        ) {
          document.body.setAttribute("data-sidebar", "dark");
          localStorage.setItem("data-sidebar", "dark");
          updateRadio("sidebar-color-dark");
        }
      }
    });

    $(document).on("click", "body", function (e) {
      if ($(e.target).closest(".right-bar-toggle, .right-bar").length > 0) {
        return;
      }
      $("body").removeClass("right-bar-enabled");
      return;
    });

    if (
      (body.hasAttribute("data-layout") &&
        body.getAttribute("data-layout") == "horizontal") ||
      localStorage.getItem("data-orientation") === "landscape"
    ) {
      updateRadio("layout-horizontal");
      $(".sidebar-setting").hide();
    } else {
      updateRadio("layout-vertical");
    }

    // (document.documentElement.hasAttribute("data-theme-mode") == "purple") ? updateRadio('theme-purple') : document.documentElement.getAttribute("data-theme-mode") == "red" ? updateRadio('theme-red') : updateRadio('theme-default');
    document.documentElement.hasAttribute("data-theme-mode") &&
    document.documentElement.getAttribute("data-theme-mode") == "purple"
      ? updateRadio("theme-purple")
      : document.documentElement.hasAttribute("data-theme-mode") &&
          document.documentElement.getAttribute("data-theme-mode") == "red"
        ? updateRadio("theme-red")
        : document.documentElement.hasAttribute("data-theme-mode") &&
            document.documentElement.getAttribute("data-theme-mode") == "slate"
          ? updateRadio("theme-slate")
          : document.documentElement.hasAttribute("data-theme-mode") &&
              document.documentElement.getAttribute("data-theme-mode") ==
                "emerald"
            ? updateRadio("theme-emerald")
            : document.documentElement.hasAttribute("data-theme-mode") &&
                document.documentElement.getAttribute("data-theme-mode") ==
                  "orange"
              ? updateRadio("theme-orange")
              : document.documentElement.hasAttribute("data-theme-mode") &&
                  document.documentElement.getAttribute("data-theme-mode") ==
                    "rose"
                ? updateRadio("theme-rose")
                : document.documentElement.hasAttribute("data-theme-mode") &&
                    document.documentElement.getAttribute("data-theme-mode") ==
                      "ersan"
                  ? updateRadio("theme-ersan")
                  : document.documentElement.hasAttribute("data-theme-mode") &&
                      document.documentElement.getAttribute(
                        "data-theme-mode",
                      ) == "teal"
                    ? updateRadio("theme-teal")
                    : document.documentElement.hasAttribute(
                          "data-theme-mode",
                        ) &&
                        document.documentElement.getAttribute(
                          "data-theme-mode",
                        ) == "cyan"
                      ? updateRadio("theme-cyan")
                      : updateRadio("theme-default");

    if (html.getAttribute("data-font-family") == "Outfit") {
      updateRadio("font-outfit");
    } else if (html.getAttribute("data-font-family") == "Poppins") {
      updateRadio("font-poppins");
    } else if (html.getAttribute("data-font-family") == "Plus Jakarta Sans") {
      updateRadio("font-jakarta");
    } else if (html.getAttribute("data-font-family") == "Lexend") {
      updateRadio("font-lexend");
    } else if (html.getAttribute("data-font-family") == "Inter") {
      updateRadio("font-inter");
    } else {
      updateRadio("font-geist");
    }

    html.hasAttribute("data-bs-theme") &&
    html.getAttribute("data-bs-theme") == "dark"
      ? updateRadio("layout-mode-dark")
      : updateRadio("layout-mode-light");
    body.hasAttribute("data-layout-size") &&
    body.getAttribute("data-layout-size") == "boxed"
      ? updateRadio("layout-width-boxed")
      : updateRadio("layout-width-fuild");
    body.hasAttribute("data-layout-scrollable") &&
    body.getAttribute("data-layout-scrollable") == "true"
      ? updateRadio("layout-position-scrollable")
      : updateRadio("layout-position-fixed");

    if (body.getAttribute("data-topbar") == "light") {
      updateRadio("topbar-color-light");
    } else if (body.getAttribute("data-topbar") == "dark") {
      updateRadio("topbar-color-dark");
    } else if (body.getAttribute("data-topbar") == "brand") {
      updateRadio("topbar-color-brand");
    } else if (body.getAttribute("data-topbar") == "red") {
      updateRadio("topbar-red");
    } else if (body.getAttribute("data-topbar") == "purple") {
      updateRadio("topbar-purple");
    } else if (body.getAttribute("data-topbar") == "slate") {
      updateRadio("topbar-slate");
    } else if (body.getAttribute("data-topbar") == "emerald") {
      updateRadio("topbar-emerald");
    } else if (body.getAttribute("data-topbar") == "orange") {
      updateRadio("topbar-orange");
    } else if (body.getAttribute("data-topbar") == "rose") {
      updateRadio("topbar-rose");
    } else if (body.getAttribute("data-topbar") == "ersan") {
      updateRadio("topbar-ersan");
    } else if (body.getAttribute("data-topbar") == "teal") {
      updateRadio("topbar-teal");
    } else if (body.getAttribute("data-topbar") == "cyan") {
      updateRadio("topbar-cyan");
    } else if (body.getAttribute("data-topbar") == "default") {
      updateRadio("topbar-default");
    } else {
      updateRadio("topbar-color-light");
    }

    body.hasAttribute("data-sidebar-size") &&
    body.getAttribute("data-sidebar-size") == "sm"
      ? updateRadio("sidebar-size-small")
      : body.hasAttribute("data-sidebar-size") &&
          body.getAttribute("data-sidebar-size") == "md"
        ? updateRadio("sidebar-size-compact")
        : updateRadio("sidebar-size-default");
    body.hasAttribute("data-sidebar") &&
    body.getAttribute("data-sidebar") == "brand"
      ? updateRadio("sidebar-color-brand")
      : body.hasAttribute("data-sidebar") &&
          body.getAttribute("data-sidebar") == "dark"
        ? updateRadio("sidebar-color-dark")
        : body.hasAttribute("data-sidebar") &&
            body.getAttribute("data-sidebar") == "red"
          ? updateRadio("sidebar-red")
          : body.hasAttribute("data-sidebar") &&
              body.getAttribute("data-sidebar") == "purple"
            ? updateRadio("sidebar-purple")
            : body.hasAttribute("data-sidebar") &&
                body.getAttribute("data-sidebar") == "slate"
              ? updateRadio("sidebar-slate")
              : body.hasAttribute("data-sidebar") &&
                  body.getAttribute("data-sidebar") == "emerald"
                ? updateRadio("sidebar-emerald")
                : body.hasAttribute("data-sidebar") &&
                    body.getAttribute("data-sidebar") == "orange"
                  ? updateRadio("sidebar-orange")
                  : body.hasAttribute("data-sidebar") &&
                      body.getAttribute("data-sidebar") == "rose"
                    ? updateRadio("sidebar-rose")
                    : body.hasAttribute("data-sidebar") &&
                        body.getAttribute("data-sidebar") == "ersan"
                      ? updateRadio("sidebar-ersan")
                      : body.hasAttribute("data-sidebar") &&
                          body.getAttribute("data-sidebar") == "teal"
                        ? updateRadio("sidebar-teal")
                        : body.hasAttribute("data-sidebar") &&
                            body.getAttribute("data-sidebar") == "cyan"
                          ? updateRadio("sidebar-cyan")
                          : body.hasAttribute("data-sidebar") &&
                              body.getAttribute("data-sidebar") == "default"
                            ? updateRadio("sidebar-default")
                            : updateRadio("sidebar-color-light");
    document.getElementsByTagName("html")[0].hasAttribute("dir") &&
    document.getElementsByTagName("html")[0].getAttribute("dir") == "rtl"
      ? updateRadio("layout-direction-rtl")
      : updateRadio("layout-direction-ltr");

    // on theme mode change
    $("input[name='theme-mode']").on("change", function () {
      var val = $(this).val();
      document.documentElement.setAttribute("data-theme-mode", val);
      localStorage.setItem("data-theme-mode", val);
      // Clear custom color override when selecting a predefined theme
      localStorage.removeItem("custom-primary-color");
      document.documentElement.style.removeProperty("--bs-primary");
      document.documentElement.style.removeProperty("--bs-primary-rgb");
    });

    // on layou change
    $("input[name='layout']").on("change", function () {
      var val = $(this).val();

      // Mobile orientation handling
      if (window.innerWidth < 992) {
        if (val === "horizontal") {
          document.documentElement.setAttribute("data-orientation", "landscape");
          localStorage.setItem("data-orientation", "landscape");
          // If we are rotating on mobile, we might not want to reload the page
          // as it can be jarring. However, the template usually expects a reload for layout change.
          // For a better experience, we'll just toggle the orientation class.
          return;
        } else {
          document.documentElement.removeAttribute("data-orientation");
          localStorage.setItem("data-orientation", "portrait");
        }
      }

      const orientationValue = val === "horizontal" ? "landscape" : "portrait";
      document.documentElement.setAttribute("data-orientation", orientationValue);
      localStorage.setItem("data-orientation", orientationValue);
      
      // We'll also update the data-layout attribute on body to reflect the menu style choice
      // without reloading, even if the full menu structure won't change immediately.
      document.body.setAttribute("data-layout", val === "vertical" ? "vertical" : "horizontal");
    });

    // on layout mode change
    $("input[name='layout-mode']").on("change", function () {
      var val = $(this).val();
      if (val == "light") {
        html.setAttribute("data-bs-theme", "light");
        localStorage.setItem("data-bs-theme", "light");

        // Preserve specialized topbar/sidebar if they aren't 'dark' default
        if (
          localStorage.getItem("data-topbar") == "dark" ||
          !localStorage.getItem("data-topbar")
        ) {
          document.body.setAttribute("data-topbar", "light");
          localStorage.setItem("data-topbar", "light");
          updateRadio("topbar-color-light");
        }

        if (localStorage.getItem("data-sidebar") == "light") {
          // Keep it light
        } else {
          document.body.setAttribute("data-sidebar", "dark");
          localStorage.setItem("data-sidebar", "dark");
          updateRadio("sidebar-color-dark");
        }
      } else {
        html.setAttribute("data-bs-theme", "dark");
        localStorage.setItem("data-bs-theme", "dark");

        // Force dark topbar/sidebar for dark theme unless they are colored custom
        const currentTopbar = localStorage.getItem("data-topbar");
        if (currentTopbar == "light" || !currentTopbar) {
          document.body.setAttribute("data-topbar", "dark");
          localStorage.setItem("data-topbar", "dark");
          updateRadio("topbar-color-dark");
        }

        const currentSidebar = localStorage.getItem("data-sidebar");
        if (currentSidebar == "light" || !currentSidebar) {
          document.body.setAttribute("data-sidebar", "dark");
          localStorage.setItem("data-sidebar", "dark");
          updateRadio("sidebar-color-dark");
        }
      }
    });

    // on layout width change
    $("input[name='layout-width']").on("change", function () {
      var val = $(this).val();
      if (val == "boxed") {
        document.body.setAttribute("data-layout-size", "boxed");
        document.body.setAttribute("data-sidebar-size", "sm");
        localStorage.setItem("data-layout-size", "boxed");
        localStorage.setItem("data-sidebar-size", "sm");
      } else {
        document.body.setAttribute("data-layout-size", "fluid");
        localStorage.setItem("data-layout-size", "fluid");
      }
    });

    // on layout position change
    $("input[name='layout-position']").on("change", function () {
      var val = $(this).val();
      if (val == "scrollable") {
        document.body.setAttribute("data-layout-scrollable", "true");
        localStorage.setItem("data-layout-scrollable", "true");
      } else {
        document.body.setAttribute("data-layout-scrollable", "false");
        localStorage.setItem("data-layout-scrollable", "false");
      }
    });

    // on topbar color change
    $("input[name='topbar-color']").on("change", function () {
      var val = $(this).val();
      document.body.setAttribute("data-topbar", val);
      localStorage.setItem("data-topbar", val);
      // Clear custom topbar if predefined color selected
      localStorage.removeItem("custom-topbar-color");
      $("#custom-topbar-style").remove();
    });

    // on sidebar size change
    $("input[name='sidebar-size']").on("change", function () {
      var val = $(this).val();
      var size = "lg";
      if (val == "compact") size = "md";
      if (val == "small") size = "sm";
      document.body.setAttribute("data-sidebar-size", size);
      localStorage.setItem("data-sidebar-size", size);
    });

    // on sidebar color change
    $("input[name='sidebar-color']").on("change", function () {
      var val = $(this).val();
      document.body.setAttribute("data-sidebar", val);
      localStorage.setItem("data-sidebar", val);
      // Clear custom sidebar if predefined color selected
      localStorage.removeItem("custom-sidebar-color");
      $("#custom-sidebar-style").remove();
    });

    // on font family change
    $("input[name='font-family']").on("change", function () {
      var val = $(this).val();
      document.documentElement.setAttribute("data-font-family", val);
      localStorage.setItem("data-font-family", val);
    });

    // on custom theme picker change
    $("#custom-theme-picker").on("input", function () {
      var val = $(this).val();
      document.documentElement.style.setProperty("--bs-primary", val);
      const r = parseInt(val.slice(1, 3), 16),
        g = parseInt(val.slice(3, 5), 16),
        b = parseInt(val.slice(5, 7), 16);
      document.documentElement.style.setProperty(
        "--bs-primary-rgb",
        `${r}, ${g}, ${b}`,
      );
      localStorage.setItem("custom-primary-color", val);
      // Deselect standard theme radios
      $("input[name='theme-mode']").prop("checked", false);
    });

    // on custom topbar picker change
    $("#custom-topbar-picker").on("input", function () {
      var val = $(this).val();
      applyCustomTopbar(val);
      localStorage.setItem("custom-topbar-color", val);
      document.body.removeAttribute("data-topbar");
      localStorage.removeItem("data-topbar");
      $("input[name='topbar-color']").prop("checked", false);
    });

    // on custom sidebar picker change
    $("#custom-sidebar-picker").on("input", function () {
      var val = $(this).val();
      applyCustomSidebar(val);
      localStorage.setItem("custom-sidebar-color", val);
      document.body.removeAttribute("data-sidebar");
      localStorage.removeItem("data-sidebar");
      $("input[name='sidebar-color']").prop("checked", false);
    });

    function applyCustomTopbar(color) {
      $("#custom-topbar-style").remove();
      $(
        "<style id='custom-topbar-style'>body #page-topbar, body .navbar-brand-box { background-color: " +
          color +
          " !important; border-color: " +
          color +
          " !important; } body #page-topbar .header-item, body #page-topbar .logo-txt { color: #fff !important; } body #page-topbar .logo-dark { display: none !important; } body #page-topbar .logo-light { display: block !important; }</style>",
      ).appendTo("head");
    }

    function applyCustomSidebar(color) {
      $("#custom-sidebar-style").remove();
      $(
        "<style id='custom-sidebar-style'>body .vertical-menu { background-color: " +
          color +
          " !important; } body #sidebar-menu ul li a { color: rgba(255, 255, 255, 0.7) !important; } body #sidebar-menu ul li a i { color: rgba(255, 255, 255, 0.7) !important; } body #sidebar-menu ul li a:hover, body #sidebar-menu ul li a.active, body #sidebar-menu ul li.mm-active > a { color: #fff !important; } body #sidebar-menu .menu-title { color: rgba(255, 255, 255, 0.4) !important; } body .vertical-menu .logo-dark { display: none !important; } body .vertical-menu .logo-light { display: block !important; }</style>",
      ).appendTo("head");
    }

    // Initialize custom colors
    const savedTheme = localStorage.getItem("custom-primary-color");
    if (savedTheme) {
      $("#custom-theme-picker").val(savedTheme);
      document.documentElement.style.setProperty("--bs-primary", savedTheme);
      const r = parseInt(savedTheme.slice(1, 3), 16),
        g = parseInt(savedTheme.slice(3, 5), 16),
        b = parseInt(savedTheme.slice(5, 7), 16);
      document.documentElement.style.setProperty(
        "--bs-primary-rgb",
        `${r}, ${g}, ${b}`,
      );
    }

    const savedTopbar = localStorage.getItem("custom-topbar-color");
    if (savedTopbar) {
      $("#custom-topbar-picker").val(savedTopbar);
      applyCustomTopbar(savedTopbar);
    }

    const savedSidebar = localStorage.getItem("custom-sidebar-color");
    if (savedSidebar) {
      $("#custom-sidebar-picker").val(savedSidebar);
      applyCustomSidebar(savedSidebar);
    }

    // on RTL-LTR mode change
    $("input[name='layout-direction']").on("change", function () {
      if ($(this).val() == "ltr") {
        document.getElementsByTagName("html")[0].removeAttribute("dir");
        document
          .getElementById("bootstrap-style")
          .setAttribute("href", "assets/css/bootstrap.min.css");
        document
          .getElementById("app-style")
          .setAttribute("href", "assets/css/app.min.css");
        localStorage.setItem("dir", "ltr");
      } else {
        document
          .getElementById("bootstrap-style")
          .setAttribute("href", "assets/css/bootstrap-rtl.min.css");
        document
          .getElementById("app-style")
          .setAttribute("href", "assets/css/app-rtl.min.css");
        document.getElementsByTagName("html")[0].setAttribute("dir", "rtl");
        localStorage.setItem("dir", "rtl");
      }
    });
  }

  function init() {
    initMetisMenu();
    initCounterNumber();
    initLeftMenuCollapse();
    initActiveMenu();
    initMenuItemScroll();
    initHoriMenuActive();
    initFullScreen();
    initDropdownMenu();
    initComponents();
    initSettings();
    initLanguage();
    initFeathericon();
    initPreloader();
    layoutSetting();
    Waves.init();
    initCheckAll();
  }

  init();
})(jQuery);
