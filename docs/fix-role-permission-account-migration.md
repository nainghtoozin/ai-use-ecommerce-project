# Fix: Role & Permission Account Migration

## Bug

The Roles page crashed with:

```
Unknown column 'users.tenant_id'
```

Root cause: `RoleController::index()` used `->withCount(['users' => fn($q) => $q->where('users.tenant_id', ...)])`. In Account mode (`IDENTITY_USE_ACCOUNTS=true`), roles are assigned to `Account` models (through Spatie's polymorphic `model_has_roles` pivot), not `User` models. The `users` table is empty of tenant-specific data. The query against `users.tenant_id` either:

1. **Crashes** if the `users` table has been dropped or no longer has `tenant_id` column
2. **Returns 0** silently, making it impossible to see how many accounts have a given role
3. **Fails to validate** whether accounts are still assigned before deleting a role

## Root Cause

The authorization layer was hardcoded to the Legacy `User` model:

- `->withCount(['users' => ...])` — Spatie's `users()` relationship queries `model_has_roles` with `model_type = 'App\Models\User'`
- When `IDENTITY_USE_ACCOUNTS=true`, all new assignments are stored with `model_type = 'App\Models\Account'` — the `users()` relation misses them entirely
- The `users.tenant_id` filter in `withCount` requires a column that doesn't exist on the `accounts` table or in Account mode

## Fix

### Files Modified

1. **`app/Models/Role.php`**
   - Added `accounts()` relationship — mirrors Spatie's `users()` but targets `Account`:
     ```php
     public function accounts(): MorphToMany
     {
         return $this->morphedByMany(
             Account::class,
             'model',
             config('permission.table_names.model_has_roles', 'model_has_roles'),
             app(PermissionRegistrar::class)->pivotRole,
             config('permission.column_names.model_morph_key', 'model_id')
         );
     }
     ```
   - This queries `model_has_roles` with `model_type = 'App\Models\Account'`

2. **`app/Http/Controllers/Admin/RoleController.php`**
   - Injected `IdentityResolver`
   - `index()` — mode-aware `withCount`:
     - Account mode: `->withCount(['accounts' => fn($q) => $q->whereHas('memberships', fn($q) => $q->where('tenant_id', $tenantId))])`
     - Legacy mode: `->withCount(['users' => fn($q) => $q->where('users.tenant_id', $tenantId)])` (unchanged)
     - Output always uses `users_count` key so no frontend changes needed
   - `show()` — same mode-aware `withCount` pattern
   - `destroy()` — checks account assignments in Account mode (instead of user assignments)
   - Error message uses "account(s)" in Account mode vs "user(s)" in Legacy mode

### Data Flow (Account Mode)

```
RoleController::getTenantFilter()
  → Tenant::getCurrent()
    → Tenant object with ->id

RoleController::index()
  → Role::withCount(['accounts' => fn($q) => $q
      ->whereHas('memberships', fn($q) => $q->where('tenant_id', $tenant->id))
    ])
  → model_has_roles WHERE model_type = 'App\Models\Account'
    → accounts → memberships WHERE tenant_id = ?
```

### PermissionController

`PermissionController` was already clean — it queries `Permission` (no `tenant_id` dependency) and has no User/Account relationship queries. No changes needed.

## Testing

1. **Legacy Mode** (`IDENTITY_USE_ACCOUNTS=false`):
   - Roles index shows `users_count` from `users` table with `tenant_id` filter — unchanged
   - Role show shows same count — unchanged
   - Role delete checks user assignments — unchanged

2. **Account Mode** (`IDENTITY_USE_ACCOUNTS=true`):
   - Roles index shows `users_count` from `accounts` table through `model_has_roles` + `tenant_memberships`
   - Accounts from other tenants are excluded (via `memberships.where('tenant_id', ...)`)
   - Role delete checks account assignments with membership filter
   - SuperAdmin sees all accounts (no tenant filter)

## Known Limitations

1. **`PermissionController`** was already clean and remains unchanged.
2. **Frontend labels** still say "Assigned Users" (Roles/Show.jsx:66) and `{role.users_count} users` (Roles/Index.jsx:97). These are cosmetic — the count is correct for both modes.
3. **Role creation** (`store()`) auto-sets `guard_name = 'web'`. The `Account` model also uses `$guard_name = 'web'`, so role assignment via Spatie's `syncRoles()` works correctly without changes.
