---
name: Glacier Alpine Optics
colors:
  surface: '#f7fafc'
  surface-dim: '#d7dadc'
  surface-bright: '#f7fafc'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f1f4f6'
  surface-container: '#ebeef0'
  surface-container-high: '#e5e9eb'
  surface-container-highest: '#e0e3e5'
  on-surface: '#181c1e'
  on-surface-variant: '#44474a'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eef1f3'
  outline: '#75777b'
  outline-variant: '#c5c6cb'
  surface-tint: '#5a5f65'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#171c21'
  on-primary-container: '#7f848b'
  inverse-primary: '#c2c7ce'
  secondary: '#516071'
  on-secondary: '#ffffff'
  secondary-container: '#d5e4f9'
  on-secondary-container: '#576677'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#081d2f'
  on-tertiary-container: '#72869c'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dee3ea'
  primary-fixed-dim: '#c2c7ce'
  on-primary-fixed: '#171c21'
  on-primary-fixed-variant: '#42474d'
  secondary-fixed: '#d5e4f9'
  secondary-fixed-dim: '#b9c8dc'
  on-secondary-fixed: '#0d1d2b'
  on-secondary-fixed-variant: '#3a4859'
  tertiary-fixed: '#d1e4fd'
  tertiary-fixed-dim: '#b5c8e0'
  on-tertiary-fixed: '#081d2f'
  on-tertiary-fixed-variant: '#36485c'
  background: '#f7fafc'
  on-background: '#181c1e'
  surface-variant: '#e0e3e5'
typography:
  display-hero:
    fontFamily: Plus Jakarta Sans
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 52px
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 38px
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 26px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 22px
    fontWeight: '600'
    lineHeight: 28px
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Space Grotesk
    fontSize: 13px
    fontWeight: '600'
    lineHeight: 16px
  label-md:
    fontFamily: Space Grotesk
    fontSize: 11px
    fontWeight: '500'
    lineHeight: 14px
  label-caps:
    fontFamily: Space Grotesk
    fontSize: 10px
    fontWeight: '700'
    lineHeight: 12px
rounded:
  sm: 0.5rem
  DEFAULT: 1rem
  md: 1.5rem
  lg: 2rem
  xl: 3rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-sm: 0.75rem
  margin: 1.25rem
  margin-lg: 2rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2.25rem
---

## Brand & Style

This design system embodies the crystalline clarity, technical precision, and quiet luxury of high-altitude alpine exploration. Crafted for high-performance outdoor athletes and style-conscious winter adventurers, the aesthetic evokes the tranquil calm of an untouched powder field paired with the high-tech precision of engineered optical glass. 

The visual style blends **Nordic minimalism** with **subtle frosted glassmorphism**. Clean, expansive white space emulates snowfields, while cool atmospheric slates, deep charcoal contrasts, and pristine icy highlights mirror alpine environments. Key interactions prioritize fluid movement, tactile pill-shaped triggers, crisp line-drawn iconography, and floating docked surfaces that elevate the mobile shopping experience into a serene, gallery-like journey.

## Colors

The palette draws directly from sub-zero atmospheres: cold mist, slate shadows, deep crevasse blacks, and glacial white reflection.

- **Primary (`#11161B`)**: An obsidian charcoal-slate used for dominant headlines, primary interaction fills, floating docks, and high-impact structural accents. It offers stark, refined contrast against frosted backdrops.
- **Secondary (`#8C9BAE`)**: A cool glacial mist mid-tone representing alpine haze and optical anti-reflective coating reflections. Used for subtle icon fills, secondary copy, inactive states, and delicate borders.
- **Tertiary (`#5A6D82`)**: Deep slate blue utilized for secondary typography, feature badges, price sub-labels, and focused component framing.
- **Neutral (`#F4F7F9`)**: Crisp snow-white neutral tone serving as the foundational canvas, softly preventing pure `#FFFFFF` eye strain while ensuring high legibility under varying light conditions. Pure white (`#FFFFFF`) is reserved for high-elevation foreground cards, floating glass overlays, and primary pill buttons over dark backdrops.

## Typography

The typographic hierarchy balances modern geometric proportions with technical alpine gear clarity:

- **Headlines & Body (`Plus Jakarta Sans`)**: Delivers crisp readability, balanced geometric curves, and friendly modernity. Hero banners and screen titles leverage bold weights with tight negative letter tracking (`-0.02em` to `-0.03em`) for a premium editorial presence.
- **Labels & Spec Badges (`Space Grotesk`)**: Provides an engineered, technical optical instrument feel. Utilized across micro-labels, technical feature badges (e.g., `UV400 PROTECTION`, `ANTI-FOG`), pricing tags, and navigation metadata.
- **Uppercase Tracking**: All category caps, brand tags, and metadata titles must use uppercase styling paired with expanded tracking (`+0.06em` to `+0.12em`) to maintain breathable legibility against light-gray backgrounds.

## Layout & Spacing

The mobile layout relies on an uninterrupted fluid 4-column structure designed for thumb-driven e-commerce navigation:

- **Canvas Margins**: Set to a standard `1.25rem` (20px) on mobile viewport sides, expanding to `2rem` (32px) on larger handheld views or tablet viewports.
- **Internal Gutter Grid**: A consistent `1rem` (16px) gutter spaces multi-card horizontal shelves, dual-column product catalog grids, and thumbnail galleries.
- **Safe Area Insets**: Top app bars maintain a minimum `48px` dynamic buffer below hardware status cutouts. Bottom content includes an exaggerated `80px` padding offset to accommodate the dark floating bottom navigation dock without content occlusion.
- **Rhythm & Padding**: Vertical rhythm flows through multiples of 4px/8px, prioritizing generous top and bottom spacing across hero units to mirror expansive winter horizons.

## Elevation & Depth

Visual hierarchy leverages a crystalline layering system rather than heavy, dirty drop shadows:

- **Glassmorphism & Frost Surfaces**: Floating sheets and interactive overlays use `rgba(255, 255, 255, 0.72)` paired with `backdrop-filter: blur(16px)` and a micro 1px boundary stroke of `rgba(255, 255, 255, 0.9)`.
- **Alpine Ambient Shadows**: Cards hover with whisper-soft, cold-tinted ambient shadows (`box-shadow: 0 12px 32px -8px rgba(17, 22, 27, 0.06), 0 4px 12px -2px rgba(140, 155, 174, 0.08)`).
- **Floating Dark Dock**: The bottom navigation dock acts as a grounding anchor, utilizing deep charcoal `rgba(17, 22, 27, 0.92)` frosted glass with `backdrop-filter: blur(20px)` and an ambient elevation spread of `0 16px 40px -10px rgba(17, 22, 27, 0.35)`.
- **Low-Contrast Separation**: Subtle hairline dividers and card boundaries utilize `rgba(140, 155, 174, 0.15)` to segment zones without creating visual visual clutter.

## Shapes

The design system embraces generous, organic curves reminiscent of high-end aerodynamic ski goggle frames and sculpted snowbanks:

- **Pill Geometry**: Interactive actions, promotional CTA tags, indicator pills, and bottom navigation pods strictly conform to full circular pill radii (`rounded-full` or `9999px`).
- **Cards & Sheets**: Product cards, technical spec containers, and hero preview modules employ deep curvature (`rounded-2xl` to `rounded-3xl` / 20px to 28px).
- **Collection Thumbnails**: Minor square collection items and color-way selectors feature smooth `rounded-xl` (16px) bounds.

## Components

### Buttons & Interactive Triggers
- **Primary Pill Action**: High-contrast pill container with background `#FFFFFF` or `#11161B`, accompanied by a dark or white label and an optional circular trailing arrow badge (`28px x 28px`). Hover/pressed state scales subtly (`scale: 0.98`) with reduced opacity.
- **Icon Quick-Actions**: Perfectly circular 36px–44px buttons encased in high-opacity frost (`rgba(255, 255, 255, 0.85)`) or solid `#11161B` with 1.5px stroke weight icons.

### Product & Showcase Cards
- **Hero Showcase**: Unbounded full-bleed photography card nested within 24px margins, featuring crisp product renders over snowy peaks, complete with three-dot pagination indicators.
- **Horizontal Product Feature Card**: Split composition with product imagery anchored on the left and typography, price (`$189.00`), and a circular black add-to-cart arrow button on the right. Encased in a pure white or frosted rounded-2xl shell with subtle ambient shadow.
- **Collection Swatches**: Square aspect ratio rounded-xl image boxes with subtle border rings depicting different lens tints (polarised mirror, obsidian, ember, glacier ice).

### Feature Spec Chips & Badges
- Four-column horizontal feature matrix presenting minimalist line icons (UV400 Protection, Anti-Fog Technology, Lightweight Comfort, Helmet Compatible) paired with centered two-line caption labels. Icons feature uniform 1.25px stroke weights in `#11161B`.

### Floating Navigation Dock
- Centered floating bottom pill (`margin: 0 1.5rem 1.25rem 1.5rem`), crafted with a dark charcoal glassmorphic background, displaying minimal line-art iconography (Home, Shop, Favorites, Cart with badge counter, Profile). The active indicator is denoted by a clean white illumination dot beneath the respective icon.

### Form Inputs & Selectors
- Search and quantity inputs leverage rounded-full or rounded-2xl geometry, light snow-gray fill (`#EDF2F6`), subtle inward transition states, and placeholder text set in `secondary` (`#8C9BAE`).