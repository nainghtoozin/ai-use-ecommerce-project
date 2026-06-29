# Root Cause Trace: TypeError Cannot convert undefined or null to object

**Date**: 2026-06-30  
**Status**: RESOLVED

## Symptom
`TypeError: Cannot convert undefined or null to object` in browser console on landing page load.

## Trace Method
1. Installed `puppeteer-core`, created `trace-error.cjs` script
2. Script launches headless Chrome, navigates to `http://127.0.0.1:8000/`
3. Captures all `console.error` and `pageerror` events with full stack traces

## Stack Trace
```
at d (app-Cv2lINRx.js:138:50565)   // renderTagStart
at p (app-Cv2lINRx.js:138:50912)   // renderTag
at <anonymous>                      // reduce callback
at u (app-Cv2lINRx.js:138:50864)   // renderTagChildren
at p (app-Cv2lINRx.js:138:50946)   // renderTag
at f (app-Cv2lINRx.js:138:51195)   // renderNode
at <anonymous>
at y (app-Cv2lINRx.js:138:51261)   // renderNodes
at <anonymous>
at mi                              // React internals
```

## Root Cause

### Inertia Head Component (3rd-party bug)
In `@inertiajs/react/dist/index.js:1131`, the `renderTagStart` function does:
```javascript
function renderTagStart(node) {
    const attrs = Object.keys(node.props).reduce(...) // line 1131
}
```

### The Call Chain
1. `Landing.jsx` renders `<Head>` with child `<title>{siteName || 'My Store'} — Launch Your Online Store</title>`
2. JSX compiles this to `React.createElement('title', null, siteName || 'My Store', ' — Launch Your Online Store')` — **two arguments** → two children
3. Inertia `renderNodes()` calls `renderNode()` for each child of `<Head>`
4. `renderNode()` calls `renderTag()` → `renderTagStart()` (works, node is a valid element)
5. `renderTag()` sees `node.props.children` is an **array** of strings (from multiple text children)
6. `renderTagChildren()` calls `renderTag()` for each string in the array  
7. `renderTag()` → `renderTagStart(' — Launch Your Online Store')`  
8. `Object.keys(' — Launch Your Online Store'.props)` → `Object.keys(undefined)` → **TypeError!**

### Why Previous Fix Failed
Previous defensive guards (null filtering, optional chaining, Array.isArray coercion) addressed the `plans`/`features` data, but the error was not in the component data — it was in **how JSX compiles mixed text/expression children** in the `<title>` element, which triggered a bug in Inertia's own `renderTagChildren` that doesn't guard against string children in the array.

## Fix

**File**: `resources/js/Pages/Public/Landing.jsx:21`

**Before** (mixed expression + text node = 2 children):
```jsx
<title>{siteName || 'My Store'} — Launch Your Online Store</title>
```

**After** (single template literal = 1 string child):
```jsx
<title>{`${siteName || 'My Store'} — Launch Your Online Store`}</title>
```

## Verification
- `npx vite build` — 2491 modules, 0 errors
- Headless Chrome trace — **0 page errors** (was 1 `TypeError`)
- 2 remaining `ERR_CONNECTION_RESET` are unrelated resource fetch failures (external fonts/images)

## Lesson
When Inertia `<Head>` receives a `<title>` element with multiple children (from mixed JSX expression + text nodes), `React.Children.toArray()` produces an array of strings. Inertia's `renderTagChildren` passes each string to `renderTag`, which calls `renderTagStart` → `Object.keys(node.props)` on a string (`undefined.props`). Always use a single template literal for dynamic `<title>` content inside Inertia `<Head>`.
