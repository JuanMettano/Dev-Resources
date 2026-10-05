# 🔌 WordPress Standard Plugin Stack & Requirements
> Extracted from **Web Development SOP: Project Standards, UX Principles & Launch Requirements**

This document outlines the **approved plugin stack** and core technical requirements that must be configured across all website projects before building pages.

---

## 📋 Standard Plugin Stack

Only approved plugins may be used. Avoid plugin bloat to ensure maximum site performance and stability.

| Category | Approved Plugin(s) | Notes / Usage Rules |
| :--- | :--- | :--- |
| **Forms** | • **Elementor Pro Form** *(if using Elementor)*<br>• **WPForms** *(if not using Elementor)* | Required fields: Full Name, Phone, Email, Zip Code, Services Dropdown. Must redirect to a `noindex` Thank-You page. |
| **Sliders / Carousels** | • **Elementor Pro** | Avoid heavy custom slider plugins to protect page load speed. |
| **Popups** | • **Elementor Pro** | No immediate pop-ups. Trigger after 10–30s or 50% scroll depth. Exit intent on desktop only. |
| **SEO** | • **Rank Math Premium** | Schema required: `LocalBusiness`, `FAQ`, and `Review`. FAQ schema must be enabled. |
| **Caching / Speed** | • **LiteSpeed** | Enable **only if required** by server architecture. |
| **Image Compression** | • **Elementor Pro**<br>• **Imagify**<br>• **EWWW Image Optimizer** | All images must be served in **WebP** format and compressed. |
| **Security** | • **Wordfence** | Required on all live and staging environments. |
| **Backups** | • **All-in-One WP Migration**<br>• **UpDraftPlus** | Use UpDraftPlus if the site backup is too large for All-in-One. |

---

## ⚡ Pre-Build & Maintenance Rules

1. **Pre-Build Verification:**
   - Confirm all required plugins from the stack above are installed and configured **before** building pages.
   - Confirm staging is set to `noindex, nofollow`.
   - Create and save a complete site backup before starting development.

2. **Monthly Update Procedure:**
   - Create a full backup prior to updating.
   - Plugins **must be updated every month** (do this before creating new pages when assigned a project).
   - Schedule backups according to the client's renewal date and verify completion.
   - Test site functionality immediately after updates (Forms, Nav, CTAs).

3. **Performance Target:**
   - Total site load time must remain **under 2.5 seconds**.
   - Lazy loading must be enabled for images and media.
