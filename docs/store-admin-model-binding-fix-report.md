# Store Admin Model Binding Fix Report

## Problem

Extra `{store_slug}` route prefix parameter shifts all positional controller arguments by one position, breaking Laravel's implicit route model binding for storefront admin routes.

### Root Cause

Laravel's `ControllerDispatcher::dispatch()` calls the controller method using:
```php
$controller->{$method}(...array_values($parameters));
```

When `resolveClassMethodDependencies()` resolves dependencies, it splices container-resolved instances into the parameter array but does NOT remove already-resolved route parameters. Since `{store_slug}` is an extra route parameter not declared in the controller method, it remains in the array and is included when `array_values()` re-indexes.

**Example:** For route `/store/{store_slug}/admin/products/{product}` bound to `ProductController@edit(Product $product)`:
- Route parameters: `['store_slug' => 'store-x', 'product' => Product#1]`
- `array_values(...)` → `['store-x', Product#1]`
- `$this->edit('store-x', Product#1)` → **WRONG**: `$product` receives the string `'store-x'`, not the Product model

## Solution

Override `Controller::callAction()` to resolve method arguments by **name first**, then by **positional index**, preserving correct model binding.

### Implementation

File: `app/Http/Controllers/Controller.php`

```php
use ReflectionMethod;

abstract class Controller
{
    public function callAction($method, $parameters)
    {
        $rm = new ReflectionMethod($this, $method);
        $args = [];
        foreach ($rm->getParameters() as $i => $param) {
            $name = $param->getName();
            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
            } elseif (array_key_exists($i, $parameters)) {
                $args[] = $parameters[$i];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        return $this->{$method}(...$args);
    }
}
```

### How It Works

1. Uses `ReflectionMethod` to inspect the controller method's parameter list
2. For each parameter, tries to match by **name** (e.g., `$product` matches `$parameters['product']`)
3. Falls back to **numeric index** for container-resolved dependencies (e.g., `LoginRequest` at key 0)
4. Falls back to default values or null for optional/missing parameters
5. Calls the method with the correctly ordered argument array

### Key Difference from Default Behavior

| Aspect | Default (`dispatch`) | Fixed (`callAction`) |
|--------|---------------------|----------------------|
| Resolution | Positional via `array_values()` | Name-first, then positional |
| Extra route params | Passed as extra args (silently ignored by PHP) | Filtered out when name not matched |
| Model binding | Shifts when extra prefix params exist | Works correctly regardless of prefix params |

### Bug Encountered

Initial implementation used `$method` as the variable name for the `ReflectionMethod` object, overwriting the original string `$method` parameter:

```php
// BROKEN: variable collision
$method = new ReflectionMethod($this, $method);  // $method is now an object
// ...
return $this->{$method}(...$args);  // Error: Method name must be a string
```

PHP 8.2 rejects non-string objects as dynamic method names. Fixed by using `$rm` for the reflection instance.

## Verification

- **Frontend build:** `npx vite build` succeeds
- **Storefront tests:** 43/43 pass
- **Model binding:** Admin CRUD routes with `{store_slug}` prefix now correctly resolve model-bound parameters

## Related Files

- `app/Http/Controllers/Controller.php` — `callAction()` override
- `routes/storefront-admin.php` — Storefront admin route definitions
