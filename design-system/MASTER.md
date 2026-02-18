# Ersan Elk Design System (MASTER)

## Project Overview
- **Product Name:** Ersan Elk Dashboard
- **Type:** Personnel and Vehicle Tracking System (Logistics/Service Hybrid)
- **Target Audience:** Administrative staff and operation managers.

## Design Identity
- **Primary Style:** **Minimalism + Flat Design**
- **Secondary Styles:** Glassmorphism (for cards), Micro-interactions (for status updates).
- **Core Aesthetic:** "Trust & Authority" - Professional, clean, and data-driven.

## Color Palette
- **Primary Blue:** `#2563EB` (Trust, Logistics, Authority)
- **Secondary Orange:** `#F97316` (Alerts, Tracking, Energy)
- **Success Green:** `#22C55E` (Safe, Active, Delivered)
- **Background:** 
  - Light: `#F8FAFC` (Slate 50)
  - Dark: `#0F172A` (Slate 900)
- **Card Background:** 
  - Light: `rgba(255, 255, 255, 0.8)` (Glass effect)
  - Dark: `rgba(30, 41, 59, 0.7)` (Glass effect)

## Typography (Google Fonts)
- **Primary:** `Inter` (Sans-serif, modern, readable)
- **Headings:** `Outfit` (Clean, professional, high-end feel)

## UI Guidelines
1. **Glassmorphism:** Use `backdrop-filter: blur(12px)` for all dashboard widgets to create depth.
2. **Bento Grid:** Organize widgets in a cohesive grid with consistent `gap: 1.5rem`.
3. **Micro-animations:** 
   - Hover on cards should have a subtle `translateY(-4px)` and `box-shadow` increase.
   - Status indicators should have a subtle pulse animation.
4. **Icons:** Use `Lucide` or `Boxicons` consistently. No emojis as UI icons.
5. **Interactive Elements:** All clickable cards must have `cursor: pointer`.

## Implementation Checklist
- [x] Floating Slider with Gradient & Animation
- [ ] Refine Widget Typography (Outfit font)
- [ ] Implement Glassmorphism on Summary Cards
- [ ] Add Hover Scale Transitions (Smooth 0.3s)
- [ ] Ensure WCAG AA Contrast for all status text
