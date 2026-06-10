# Create Store Navigation Fix

## Problem

The "Create Store" buttons in the navbar and mobile menu were pointing to `/register`, which shows the customer registration form ("Register at Default Store") instead of the self-service store creation form (`/create-store`).

## Changes

### Files Modified

| File | Line | Old Route | New Route | Context |
|------|------|-----------|-----------|---------|
| `resources/js/Components/ShopNavbar.jsx` | 217 | `href="/register"` | `href="/create-store"` | Desktop nav — **Create Store** button (no store context) |
| `resources/js/Components/ShopNavbar.jsx` | 316 | `href="/register"` | `href="/create-store"` | Mobile menu — **Create Store** button (no store context) |

### What Did NOT Change (intentionally)

These links remain pointing to `/register` because they are **customer registration** links within a specific store context:

| File | Line | Route | Context |
|------|------|-------|---------|
| `ShopNavbar.jsx` | 205 | `href={storeUrl('/register')}` | Desktop nav — **Register** link (inside store context) |
| `ShopNavbar.jsx` | 302 | `href={storeUrl('/register')}` | Mobile nav — **Register** link (inside store context) |
| `AppLayout.jsx` | 318 | `href="/register"` | App layout — **Register** link (customer registration) |
| `Login.jsx` | 96 | `href="/register"` | Login page — link to customer registration |
| `Storefront/Register.jsx` | 23 | `route('storefront.register')` | Storefront customer registration form |
| `Storefront/Login.jsx` | 105 | `route('storefront.register')` | Storefront login — link to register |
| `ziggy.js` | 7 | `storefront.register` | Storefront customer registration route |

## Navigation Flow

### Before (broken)
```
Landing Page / Navbar
      │
      ├─ [Create Store]
      │    └─ href="/register" ← WRONG
      │         └─ Shows "Register at Default Store" (customer reg form)
      │
      ├─ [Merchant Login]
      │
      └─ [Register] (inside store context)
           └─ href="/store/{slug}/register" ← correct
```

### After (fixed)
```
Landing Page / Navbar
      │
      ├─ [Create Store]
      │    └─ href="/create-store" ← FIXED
      │         └─ Self-Service Store Creation form
      │
      ├─ [Merchant Login]
      │
      └─ [Register] (inside store context)
           └─ href="/store/{slug}/register" ← unchanged, still works
```

## Route Map

| Action | Route | Controller | Purpose |
|--------|-------|------------|---------|
| Create Store (form) | `GET /create-store` | `CreateStoreController@index` | Self-service store creation |
| Create Store (submit) | `POST /create-store` | `CreateStoreController@store` | Submit new store |
| Customer Register | `GET /store/{slug}/register` | `RegisteredUserController@create` | Customer registration at a store |
| Customer Register | `POST /store/{slug}/register` | `RegisteredUserController@store` | Submit customer registration |
| Customer Register (global) | `GET /register` | `RegisteredUserController@create` | Fallback customer registration |

## Verification Checklist

- [ ] Home page **Create Store** button → navigates to `/create-store`
- [ ] Desktop navbar **Create Store** button → navigates to `/create-store`
- [ ] Mobile menu **Create Store** button → navigates to `/create-store`
- [ ] `/create-store` → shows self-service store creation form
- [ ] Inside store context (`/store/{slug}`), **Register** link → navigates to `/store/{slug}/register`
- [ ] `/store/{slug}/register` → shows customer registration form
- [ ] `/register` (global) → still works as fallback customer registration
- [ ] Customer registration at `/store/{slug}/register` is unaffected
- [ ] Vite build passes (no broken imports or references)
