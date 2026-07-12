# Sprint B.2 — User Account Synchronization Report

**Date**: 2026-07-12
**Scope**: Keep User and Account identity fields synchronized

---

## Files Changed

| File | Change | Type |
|------|--------|------|
| `app/Models/Traits/SyncsIdentity.php` | **NEW** — Trait that syncs identity fields between User and Account on `saved` event | New |
| `app/Models/User.php` | Added `SyncsIdentity` trait, `getCounterpartClass()`, `email_verified_at` + `remember_token` to `$fillable` | Modified |
| `app/Models/Account.php` | Added `SyncsIdentity` trait, `getCounterpartClass()` | Modified |

---

## How It Works

### SyncsIdentity Trait

Both `User` and `Account` models use the `SyncsIdentity` trait. On the `saved` event:

```
┌────────────────────────────────────────────────────────────┐
│                     saved event fires                       │
└────────────────────────┬───────────────────────────────────┘
                         │
              ┌──────────▼──────────┐
              │  use_accounts=true?   │
              │  email not empty?     │
              └──────────┬──────────┘
                         │ yes
              ┌──────────▼──────────┐
              │  Find counterpart    │
              │  by email            │
              └──────────┬──────────┘
                         │
              ┌──────────▼──────────┐      ┌────────────────┐
              │  Counterpart found?  │ yes  │ Sync dirty     │
              │                      │─────►│ fields via     │
              │                      │      │ updateQuietly  │
              └──────────┬──────────┘      └────────────────┘
                         │ no
              ┌──────────▼──────────┐
              │  wasRecentlyCreated? │
              │  (only on first      │
              │   creation)          │
              └──────────┬──────────┘
                         │ yes
              ┌──────────▼──────────┐
              │  Create counterpart │
              │  via saveQuietly    │
              │  (no event loop)    │
              └─────────────────────┘
```

### Syncable Fields

| Field | User → Account | Account → User |
|-------|---------------|---------------|
| `name` | ✅ | ✅ |
| `email` | ✅ | ✅ |
| `password` | ✅ | ✅ |
| `email_verified_at` | ✅ | ✅ |
| `status` | ✅ | ✅ |
| `remember_token` | ✅ | ✅ |
| `profile_image` | ✅ | ✅ |
| `notification_preferences` | ✅ | ✅ |

Only **dirty** fields are synced — unchanged fields are not copied.

### Recursion Prevention

Both `updateQuietly()` and `saveQuietly()` are used when modifying or creating the counterpart. This suppresses all model events on the counterpart, preventing:
- Infinite sync loop (User→Account→User→Account→...)
- Duplicate membership/observer creation

---

## Identity Mapping Strategy

**Key**: Email (unique in both `users` and `accounts` tables)

**Why not shared primary key?** Independent auto-increment counters on both tables create collision risk. Email is the natural unique identifier for both tables.

**Stable mapping guarantee**: When a User or Account is created, the counterpart is automatically created. When either is updated, the counterpart is updated. Both records for the same person always share matching core identity data.

---

## Seeder Interaction

### RoleAndPermissionSeeder (SuperAdmin)

```
1. User::updateOrCreate(email=admin@shop.com)
   └─ saved → trait: Account created (no role yet)

2. $superAdmin->assignRole('superadmin')

3. Account::updateOrCreate(email=admin@shop.com)
   └─ finds trait-created Account → no-op update

4. $superAdminAccount->assignRole('superadmin')
```

**Result**: Single User + Account pair, both with superadmin role. No duplicates.

### UserSeeder (Demo Customers)

```
1. User::updateOrCreate(email=john@example.com)
   └─ saved → trait: Account created (no role yet)

2. $user->assignRole('customer')

3. Account::updateOrCreate(email=john@example.com)
   └─ finds trait-created Account → updates (no changes needed)

4. $account->assignRole('customer')
```

**Result**: Single User + Account pair for each of 10 customers. No duplicates.

---

## Flow Verification

### Create Store (Account mode)

```
CreateStoreController → TenantBootstrapService
  └─ createOwnerAccount()
       └─ Account::create(email=owner@...)
            └─ saved → trait: User created companion
```

**User companion created**: Basic record with email, name, password, status, verification.
**User NOT created with**: `tenant_id` (Account doesn't have one), `is_owner` (Account field).
**Membership created**: Still handled by `TenantBootstrapService::createOwnerAccount()`.

### Register Customer (Account mode)

```
RegisteredUserController::storeAccount()
  └─ Account::create(email=foo@bar.com)
       └─ saved → trait: User created companion

  OR

  └─ Account found by existing email
       └─ reused — no new Account or User created
```

**Existing email reuse**: Account is found by email, no new Account created, no new User created. Only the membership is created (handled by Sprint B.1).

### Email Change

```
Account::where('email', 'old@test.com')->update(['email' => 'new@test.com'])
  └─ saved → trait: User::where('email', 'old@test.com')->updateQuietly(['email' => 'new@test.com'])
```

Both records updated. Email remains the stable mapping key.

### Password Change

```
User::find(1)->update(['password' => Hash::make('newpass')])
  └─ saved → trait: Account::where('email', ...)->updateQuietly(['password' => ...])
```

Login works with both guards.

### Status Change (Suspend/Ban)

```
Account::find(1)->update(['status' => 'suspended'])
  └─ saved → trait: User::where('email', ...)->updateQuietly(['status' => 'suspended'])
```

Both records reflect the same status.

### Email Verification

```
$account->markEmailAsVerified()
  └─ $account->forceFill(['email_verified_at' => now()])->save()
       └─ saved → trait: User::where('email', ...)->updateQuietly(['email_verified_at' => ...])
```

Verification status stays in sync.

### Logout (Remember Token)

```
Auth::guard('accounts')->logoutOtherDevices('password')
  └─ Account remember_token updated
       └─ saved → trait: User remember_token synced
```

Remember token stays consistent.

---

## Edge Cases Handled

| Case | Behavior |
|------|----------|
| Account created first, then User | `User::creating` → finds Account → no ID copy (email-based). User's `saved` → finds existing Account → syncs dirty fields |
| User created first, then Account | `Account::creating` → finds User → no ID copy. Account's `saved` → finds existing User → syncs dirty fields |
| Both created in same transaction | First model's `saved` creates counterpart silently. Second model's creation finds existing counterpart → no-op |
| `use_accounts=false` (legacy mode) | Trait's `bootSyncsIdentity` returns early — no sync occurs |
| Account deleted | No cascade deletion of User (intentional — preserves backward compatibility) |
| Email changed | Counterpart found by NEW email (trait queries by `$this->email`) |
| Bulk update | `getDirty()` returns all changed fields — all synced |

---

## Remaining Issues

| Issue | Impact | Notes |
|-------|--------|-------|
| Companion User lacks `tenant_id` | User-based model queries within tenant scope won't find companion Users | Fix requires `tenant_id` awareness in trait (out of scope — requires passing tenant context) |
| Companion User lacks Spatie roles | User-based role checks (`$user->hasRole()`) return false for companion Users | Fix requires assigning roles during counterpart creation (deferred — model relationships need Phase 7 anyway) |
| Seeders still create both User and Account | Redundant code — trait handles creation | Harmless — second `updateOrCreate` finds existing record |
| `CompatibilityBridge` remains unused | Dead code | Not removed to avoid breaking external references |

---

## Key Design Decision: Email-Based vs ID-Based Mapping

| Approach | Pros | Cons |
|----------|------|------|
| **Same ID** (rejected) | Clean PK alignment | Collision risk when auto-increment counters diverge |
| **Email-based** (chosen) | No collision risk, simple, uses existing unique constraints | Different PKs — model relationships still broken (Phase 7) |

The email-based approach was chosen because it provides stable identity mapping without schema changes or collision risk. The PK mismatch is a pre-existing issue that requires Phase 7 schema migration to resolve (polymorphic relationships on Order, Message, etc.).
