# AdminSidebar Runtime Fix

**Date:** 2026-06-09  
**Scope:** Restore missing variable definitions in `AdminSidebar.jsx`

---

## Root Cause

Commit `19805ad` ("test") removed the following variable definitions from `AdminSidebar.jsx` while keeping the JSX render code that references them:

| Deleted Symbol | Used At |
|----------------|---------|
| `menuSections` | `.filter().map()` at line 137 |
| `isActive` | `section.items.some(isActive)` and `item => isActive(item.href)` |
| `openSections` | `openSections[section.title]` |
| `toggleSection` | `onClick={() => toggleSection(section.title)}` |
| `useEffect` (open section init) | Persists section open state to `localStorage` |

The commit intended to:
- Add `tenant` extraction from `props`
- Add `storeSlug` derivation
- Update logout to include `context` and `store_slug`
- Add `adminUrl()` wrapper in render links

But the deletion was too aggressive — the entire `useMemo` block for `menuSections`, the `isActive` function, `openSections` state, `useEffect`, and `toggleSection` were all removed in one hunk, causing the `ReferenceError: menuSections is not defined` at runtime.

---

## File Modified

`resources/js/Components/AdminSidebar.jsx`

---

## Before / After

### Before (broken)

```
const isSuperAdmin = auth?.user?.is_superadmin;

const storeSlug = tenant?.slug;            ← jumped straight to storeSlug
const logout = () => router.post(...);

return (
  ...
  {menuSections.filter(...)}                ← ReferenceError
  ...
);
```

### After (fixed)

```
const isSuperAdmin = auth?.user?.is_superadmin;

const menuSections = useMemo(() => {        ← RESTORED
    ...full menu tree...
}, [userPermissions, isSuperAdmin]);

function isActive(href) { ... }             ← RESTORED

const [openSections, setOpenSections] = useState({});  ← RESTORED

useEffect(() => { ... }, [url, menuSections]);         ← RESTORED

const toggleSection = (title) => { ... };              ← RESTORED

const storeSlug = tenant?.slug;            ← preserved
const logout = () => router.post(...);     ← preserved
```

---

## Verification

- `npx vite build` — passes, bundle changed from `app-BgcYGXRd.js` → `app-DsGMVVdW.js`
- All SaaS menu groups restored: Main, Catalog, Orders, Reports, Locations, System, Configuration
- SuperAdmin menu groups restored: Main, Merchant Management, Subscription Management, System Management, Logs
- New features preserved: `adminUrl()` links, store-aware logout, `tenant` prop
- Section open/close state persists in `localStorage` as before
