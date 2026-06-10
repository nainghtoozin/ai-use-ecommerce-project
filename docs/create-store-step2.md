# Self-Service Store Creation — Step 2 (Tenant Creation Logic)

**Date:** 2026-06-09  
**Scope:** Backend tenant creation on `POST /create-store`

---

## Routes Added / Modified

| Method | URI | Name | Controller Method |
|--------|-----|------|-------------------|
| `POST` | `/create-store` | `create-store.store` | `CreateStoreController@store` |
| `GET` | `/store-registration/success` | `create-store.success` | `CreateStoreController@success` |

---

## Files Changed

| File | Action | Purpose |
|------|--------|---------|
| `routes/web.php` | Modified | Added `POST /create-store` + `GET /store-registration/success` |
| `app/Http/Controllers/CreateStoreController.php` | Modified | Added `store()` and `success()` methods |
| `resources/js/Pages/Public/CreateStore.jsx` | Modified | Replaced local `useState` form with Inertia `useForm` + form submission |
| `resources/js/Pages/Public/StoreRegistrationSuccess.jsx` | **Created** | Success page after store creation |

---

## Transaction Flow (CreateStoreController::store)

All operations run inside a single `DB::transaction()`:

```
POST /create-store
  │
  ├─ Validate input
  │    ├─ name        (required, string, max:255)
  │    ├─ slug        (required, unique:tenants, regex:/^[a-z0-9\-]+$/)
  │    ├─ description (nullable, string, max:500)
  │    ├─ domain      (nullable, string, max:255, unique:tenants)
  │    ├─ owner_name  (required, string, max:255)
  │    ├─ owner_email (required, email, unique:users)
  │    └─ password    (required, string, min:8)
  │
  ├─ DB::transaction()
  │    │
  │    ├─ 1. Create Tenant
  │    │    ├─ name        = validated.name
  │    │    ├─ slug        = validated.slug
  │    │    ├─ domain      = validated.domain ?? null
  │    │    ├─ store_url   = '/store/' . slug
  │    │    ├─ status      = 'pending'
  │    │    └─ settings    = { description } if provided
  │    │
  │    ├─ 2. Clear tenant cache (Tenant::clearDefaultCache)
  │    │
  │    ├─ 3. Create Subscription (Free Plan)
  │    │    ├─ plan_id          = Plan::free()->id
  │    │    ├─ billing_interval = plan.defaultInterval()
  │    │    ├─ status           = 'pending'
  │    │    ├─ starts_at        = null
  │    │    └─ expires_at       = null
  │    │
  │    ├─ 4. Create tenant-scoped roles (admin + customer)
  │    │    ├─ Copy permissions from global roles (where tenant_id IS NULL)
  │    │    └─ Only creates if not already exists for this tenant
  │    │
  │    ├─ 5. Create Admin User
  │    │    ├─ name             = validated.owner_name
  │    │    ├─ email            = validated.owner_email
  │    │    ├─ password         = Hash::make(validated.password)
  │    │    ├─ status           = 'active'
  │    │    ├─ email_verified_at = now()
  │    │    ├─ tenant_id        = tenant->id (set after create, not mass-assignable)
  │    │    └─ is_owner         = true
  │    │
  │    └─ 6. Assign admin role to user
  │         └─ $admin->assignRole($adminRole)
  │
  └─ Redirect → route('create-store.success', ['store' => $tenant->slug])
```

---

## Models Used

| Model | Purpose | Key Fields Set |
|-------|---------|----------------|
| `App\Models\Tenant` | Tenant/store record | `name`, `slug`, `domain`, `store_url`, `status=pending`, `settings` |
| `App\Models\Plan` | Free plan lookup | `Plan::free()` finds by `slug='free'` + `status='active'` |
| `App\Models\Subscription` | Free subscription | `plan_id`, `billing_interval`, `status=pending`, `starts_at=null`, `expires_at=null` |
| `App\Models\Role` | Tenant-scoped admin/customer roles | Created with `tenant_id` if not existing; permissions synced from global roles |
| `App\Models\User` | Owner admin user | `name`, `email`, `password`, `status=active`, `tenant_id` (direct assignment), `is_owner=true` |

---

## Validation Rules

| Field | Rule | Error Message |
|-------|------|---------------|
| `name` | `required\|string\|max:255` | Standard Laravel validation messages |
| `slug` | `required\|string\|max:255\|unique:tenants,slug\|regex:/^[a-z0-9\-]+$/` | "The slug has already been taken." |
| `domain` | `nullable\|string\|max:255\|unique:tenants,domain` | "The domain has already been taken." |
| `owner_name` | `required\|string\|max:255` | |
| `owner_email` | `required\|email\|max:255\|unique:users,email` | "The email has already been taken." |
| `password` | `required\|string\|min:8` | |

### Client-side (React)

| Field | Rule |
|-------|------|
| `slug` | `^[a-z0-9-]+$` + min 3 chars |
| `owner_email` | regex `/\S+@\S+\.\S+/` |
| `password` | min 8 chars |
| `password_confirmation` | must match password |

---

## Rollback Behavior

Since the entire creation logic runs inside `DB::transaction()`, any failure automatically triggers a full rollback:

| Failure Scenario | Rollback Effect |
|-----------------|-----------------|
| Slug already taken (validation) | No DB writes — rejected before transaction |
| Email already taken (validation) | No DB writes — rejected before transaction |
| Admin user creation fails (DB error) | Tenant, roles, subscription all rolled back |
| Role assignment fails | All prior writes rolled back |
| Free plan doesn't exist | `Plan::free()` returns null, `$plan?->id` is null, subscription created with `plan_id=null` (soft fail, no exception) |

**Note:** If `Plan::free()` returns null, the subscription is still created with `plan_id=null`. This is a soft edge case — if no Free plan exists, the admin should create one in the superadmin panel.

---

## State After Creation

| Entity | Status | Notes |
|--------|--------|-------|
| Tenant | `pending` | Not active — admin must activate |
| Subscription | `pending` | Linked to Free plan |
| Admin User | `active` | Email verified, assigned to tenant |
| Roles | created | `admin` + `customer`, tenant-scoped |
| Custom Domain | stored on Tenant | Optional — only if provided |

---

## Security Considerations

- **User not logged in** after creation — redirects to success page
- **Password hashed** with `Hash::make()` (bcrypt via Laravel default)
- **Email verified** immediately (`email_verified_at = now()`) — no email confirmation for self-service
- **Tenant status = pending** — store won't be accessible until admin activates it
- **Validation on server-side** — slug regex (`/^[a-z0-9\-]+$/`) prevents injection in URL
- **Mass assignment protected** — `tenant_id` and `is_owner` set via direct property assignment (not in `$fillable`)

---

## Verification

- `npx vite build` — passes
- `php artisan route:list --name=create-store` — 3 routes confirmed
- `php artisan test --filter=Storefront` — 43/43 pass
- Manual flow: fill form → submit → redirect to `/store-registration/success?store={slug}`
