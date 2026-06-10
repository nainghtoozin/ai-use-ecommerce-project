# Self-Service Store Creation — Step 1 (UI)

**Date:** 2026-06-09  
**Scope:** SaaS onboarding landing page — UI only, no tenant creation yet

---

## Routes Added

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/create-store` | `create-store` | `CreateStoreController@index` |

---

## Files Created

| File | Purpose |
|------|---------|
| `app/Http/Controllers/CreateStoreController.php` | Returns the Inertia page with `appUrl`, `siteName`, `logoUrl` props |
| `resources/js/Pages/Public/CreateStore.jsx` | Full-page React onboarding form |

### Modified Files

| File | Change |
|------|--------|
| `routes/web.php` | Added `use CreateStoreController` import (line 4) + `GET /create-store` route (line 43) |

---

## Page Structure

```
CreateStore.jsx
├── Head (page title)
├── Top nav bar (logo + site name + "Sign In →")
├── Hero section ("Launch Your Online Store")
├── Two-column grid
│   ├── LEFT (3/5 width)
│   │   ├── Section 1: Store Information
│   │   │   ├── Store Name *        — text, auto-generates slug
│   │   │   ├── Store Slug *        — text (font-mono), live URL preview below
│   │   │   └── Store Description   — textarea
│   │   ├── Section 2: Domain
│   │   │   └── Custom Domain       — text (optional), subdomain hint
│   │   ├── Section 3: Owner Account
│   │   │   ├── Owner Name *        — text
│   │   │   ├── Owner Email *       — email
│   │   │   ├── Password *          — password (min 8 chars)
│   │   │   └── Confirm Password *  — password (match check)
│   │   └── Submit button (disabled until valid)
│   └── RIGHT (2/5 width, sticky)
│       └── Preview Card
│           ├── Store URL (live, updates as slug changes)
│           ├── Store Name
│           ├── Slug
│           ├── Description (shown if filled)
│           ├── Custom Domain (shown if filled)
│           └── Owner name + email
└── Footer (copyright + privacy/terms links)
```

---

## Validation Rules (client-side)

| Field | Rule | Error Message |
|-------|------|---------------|
| `name` | Required, non-empty | "Store name is required." |
| `slug` | Required, min 3 chars, matches `^[a-z0-9-]+$` | "Only lowercase letters, numbers, and hyphens allowed." / "Slug must be at least 3 characters." |
| `owner_name` | Required, non-empty | "Owner name is required." |
| `owner_email` | Required, valid email regex | "Email is required." / "Invalid email format." |
| `password` | Required, min 8 chars | "Password is required." / "Password must be at least 8 characters." |
| `password_confirmation` | Must match `password` | "Passwords do not match." |

---

## Live Preview Behavior

- **Auto-slug:** Typing the store name auto-fills the slug field (slug is auto-generated from the name using `toLowerCase().replace(...)`) unless the user has manually edited the slug.
- **URL preview:** Updates in real-time below the slug input and in the right preview card.
- **Preview card:** All sections update live — empty fields show placeholders (`—` or `{slug}`).

---

## UI Checklist

- [ ] Page renders at `GET /create-store` with correct layout
- [ ] Store Name field auto-generates slug when slug is untouched
- [ ] Slug field shows live URL preview: `http://localhost:8000/store/{slug}`
- [ ] Slug validation rejects uppercase letters, spaces, special chars
- [ ] All required fields show inline error messages on blur/change
- [ ] Password confirmation validates match
- [ ] Submit button disabled when form has errors or required fields empty
- [ ] Preview card updates in real-time as user types
- [ ] "Sign In →" link navigates to `/login`
- [ ] Sticky preview card scrolls with page
- [ ] Mobile responsive: single column, stacked layout

---

## Security Notes (Step 1)

- **No backend validation or tenant creation yet** — this is a pure UI phase
- Slug validation uses client-side regex only; backend validation will be added in Step 2
- No API calls or data persistence
- `config('app.url')` is passed to the frontend for the URL preview

## Verification

- `npx vite build` — passes
- `php artisan route:list --name=create-store` — single route confirmed
- Navigate to `http://localhost:8000/create-store` to preview
