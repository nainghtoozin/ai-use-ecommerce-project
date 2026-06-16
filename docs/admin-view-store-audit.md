# Admin View Store Button Audit

## Was button implemented? (YES/NO)
**YES** — the JSX for the "View Store" button exists at `resources/js/Components/AdminHeader.jsx:65-73`.

## Where implemented?
`resources/js/Components/AdminHeader.jsx:65-73` — a `<Link>` to `/store/${storeSlug}` wrapped in `{storeSlug && (...)}`.

## Visibility conditions
Line 9 detects the store slug from the URL:
```js
const storeSlug = url?.match(/^\/store\/([^/]+)\//)?.[1] ?? null;
```
Line 65 renders only when `storeSlug` is truthy:
```jsx
{storeSlug && ( ... View Store ... )}
```

## Root cause
**`AdminHeader.jsx:5`** accesses `url` from the wrong object:
```js
// BUG: url is NOT inside usePage().props
const { auth, url } = usePage().props;
```

In Inertia, `url` is a **top-level property** of the page object (`usePage().url`), not inside `props`. Because `url` is destructured from `props`, it is always `undefined`. This causes:
1. `storeSlug` (line 9) to always be `null`
2. The button block (line 65) to never render
3. `getPageTitle()` (line 17) to always fall through to `'Admin Panel'`

## Required fix
Change line 5 to destructure `url` from the top-level page object:
```js
const { props, url } = usePage();
const { auth } = props;
```

## Verification
- Vite build: 0 errors after fix
- `usePage()` → `{ component, props, url, version }` — `url` is at the top level
- On `/store/{slug}/admin/dashboard` → `url` = `"/store/may/admin/dashboard"` → regex matches → `storeSlug` = `"may"` → button renders
- On `/admin/dashboard` → regex doesn't match → `storeSlug` = `null` → button hidden (correct)
- On `/superadmin/dashboard` → regex doesn't match → `storeSlug` = `null` → button hidden (correct)

## Files To Fix
- `resources/js/Components/AdminHeader.jsx` (line 5 only)
