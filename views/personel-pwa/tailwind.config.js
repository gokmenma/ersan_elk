module.exports = {
  darkMode: "class",
  content: [
    "./index.php",
    "./pages/**/*.php",
    "./includes/**/*.php",
    "./assets/js/**/*.js",
  ],
  // icralar.php, zimmetler.php, km-bildirimleri.php durum rengini backend'den
  // gelen değere göre `bg-${renk}-100` şeklinde çalışma zamanında oluşturuyor;
  // içerik taraması bu class isimlerini metin olarak göremiyor, bu yüzden elle listeleniyor.
  safelist: ["amber", "emerald", "rose", "slate", "red"].flatMap((c) => [
    `bg-${c}-100`,
    `bg-${c}-500/10`,
    `dark:bg-${c}-900/30`,
    `text-${c}-600`,
    `text-${c}-700`,
    `dark:text-${c}-300`,
    `dark:text-${c}-400`,
    `border-${c}-500/10`,
  ]),
  theme: {
    extend: {
      colors: {
        primary: "var(--primary)",
        "primary-dark": "var(--primary-dark)",
        "background-light": "#f6f6f8",
        "background-dark": "#121212",
        "card-dark": "#1e1e1e",
      },
      fontFamily: {
        display: ["Roboto Condensed", "sans-serif"],
      },
      borderRadius: {
        DEFAULT: "0.25rem",
        lg: "0.5rem",
        xl: "0.75rem",
        "2xl": "1rem",
        "3xl": "1.5rem",
        full: "9999px",
      },
    },
  },
  plugins: [require("@tailwindcss/forms")],
};
