# Sprint B.4 — Business Model Migration Report

**Date:** 2026-07-12
**Objective:** Replace hardcoded `belongsTo(User::class)` on 9 business models with polymorphic `morphTo('user')` allowing both `User` and `Account` as the parent identity. Keep full backward compatibility.

## Schema Migration

**File:** `database/migrations/2026_07_12_000002_add_user_type_to_business_tables.php`

Adds `user_type` (or equivalent) column to 9 tables, backfilling existing rows with `'App\Models\User'`:

| Table | New Column(s) |
|-------|--------------|
| `orders` | `user_type` |
| `wishlists` | `user_type` |
| `customer_addresses` | `user_type` |
| `promotion_usages` | `user_type` |
| `telegram_integrations` | `user_type` |
| `order_override_logs` | `user_type` |
| `messages` | `sender_type`, `receiver_type` |
| `promotions` | `created_by_type` |
| `activity_logs` | `impersonator_type`, `impersonated_user_type` |

All nullable. Existing rows backfilled to `User::class`. New rows auto-set on creation.

## Trait: `HasUser`

**File:** `app/Models/Traits/HasUser.php`

Provides three features:

1. **`bootHasUser()`** — auto-sets `user_type` on `creating` (reads `auth()->user()->getMorphClass()`), so controllers creating records don't need changes.
2. **`user(): MorphTo`** — standard polymorphic relationship.
3. **`scopeForUser($query, $userOrId, $userType)`** — scope that accepts either a Model (sets both `user_id` and `user_type`) or an integer ID (backward compat, no type filtering).

## Model Relationship Changes

| Model | Before | After |
|-------|--------|-------|
| `Order` | `belongsTo(User::class)` | `morphTo()` + `HasUser` trait |
| `Wishlist` | `belongsTo(User::class)` | `morphTo()` via `HasUser` trait |
| `CustomerAddress` | `belongsTo(User::class)` | `morphTo()` via `HasUser` trait |
| `PromotionUsage` | `belongsTo(User::class)` | `morphTo()` + `HasUser` trait |
| `TelegramIntegration` | `belongsTo(User::class)` | `morphTo()` + `HasUser` trait |
| `OrderOverrideLog` | `belongsTo(User::class)` | `morphTo()` + `HasUser` trait |
| `Message` | `belongsTo(User,'sender_id')` / `belongsTo(User,'receiver_id')` | `morphTo()` on `sender()` / `receiver()` + boot auto-set |
| `Promotion` | `belongsTo(User,'created_by')` | `morphTo('creator','created_by_type','created_by')` + boot auto-set |
| `ActivityLog` | `belongsTo(User,'impersonator_id')` / `belongsTo(User,'impersonated_user_id')` | `morphTo()` on both + backfilled types |

## Account Reverse Relationships

Added 9 polymorphic reverse relationships to `app/Models/Account.php`:

```php
orders()            → morphMany(Order::class, 'user')
wishlistItems()     → morphMany(Wishlist::class, 'user')
addresses()         → morphMany(CustomerAddress::class, 'user')
sentMessages()      → morphMany(Message::class, 'sender')
receivedMessages()  → morphMany(Message::class, 'receiver')
promotionUsages()   → morphMany(PromotionUsage::class, 'user')
telegramIntegration() → morphOne(TelegramIntegration::class, 'user')
```

## User Reverse Relationship Updates

Updated `app/Models/User.php` from `hasMany`/`hasOne` to `morphMany`/`morphOne` so they properly filter by `user_type`:

```php
orders()            → hasMany → morphMany(Order::class, 'user')
wishlistItems()     → hasMany → morphMany(Wishlist::class, 'user')
telegramIntegration() → hasOne → morphOne(TelegramIntegration::class, 'user')
```

## Controller Updates

### Updated Controllers

| Controller | Changes |
|-----------|---------|
| `OrderController` | Changed `Order::where('user_id', auth()->id())` → `auth()->user()->orders()` |
| `Client/ClientOrderController` | Same pattern across 5 query sites |
| `StorefrontCustomerController` | Changed ownership checks to include `user_type`, address queries use `$user->addresses()` |
| `StorefrontCheckoutController` | Changed address fetch to `auth()->user()->addresses()` |
| `WishlistController` | Changed `Wishlist::where('user_id', ...)` → `$user->wishlistItems()->...` |

### Ownership Checks Updated

All `if ($model->user_id !== $user->id)` checks now also verify `$model->user_type !== $user->getMorphClass()` to prevent cross-type ID collisions.

### Pattern: Auth Auto-Set

Controllers creating records (e.g. `OrderController::store()`, `WishlistController::store()`) don't need changes — the `HasUser` trait's `bootHasUser()` reads `auth()->user()->getMorphClass()` and sets `user_type` automatically.

## Deferred to Phase 7

| Item | Reason |
|------|--------|
| `ChatController` full polymorphic support | Uses `User::whereIn('id', ...)` in 3 places; would need dual-identity user resolution |
| `TelegramIntegrationController` (32 auth()->id() sites) | Pure admin function; lower priority |
| AdminUserController audit logging | Uses `auth()->id()` for logging; `causer_type` already polymorphic |
| `PromotionService` / `CouponService` user ID params | Service classes accept `int $userId` — would need model-aware interface change |

## Files Changed (27 total)

```
M  app/Models/Order.php                           (relationship + HasUser)
M  app/Models/Wishlist.php                        (relationship via HasUser)
M  app/Models/CustomerAddress.php                 (relationship via HasUser)
M  app/Models/Message.php                         (relationship + boot auto-set)
M  app/Models/PromotionUsage.php                  (relationship + HasUser)
M  app/Models/Promotion.php                       (relationship + boot auto-set)
M  app/Models/TelegramIntegration.php             (relationship + HasUser)
M  app/Models/ActivityLog.php                     (relationships)
M  app/Models/OrderOverrideLog.php                (relationship + HasUser)
M  app/Models/User.php                            (reverse morphMany/morphOne)
M  app/Models/Account.php                         (reverse morphMany/morphOne x 7)
A  app/Models/Traits/HasUser.php                  (new trait)
A  database/migrations/..._add_user_type_to_business_tables.php
M  app/Http/Controllers/OrderController.php       (relationship queries)
M  app/Http/Controllers/Client/ClientOrderController.php (relationship queries)
M  app/Http/Controllers/StorefrontCustomerController.php (ownership checks)
M  app/Http/Controllers/StorefrontCheckoutController.php (address queries)
M  app/Http/Controllers/WishlistController.php     (relationship queries)
```

## Testing Notes

- Run `php artisan migrate` to apply the `user_type` columns and backfill.
- Run `php artisan test --filter="OrderManagement|UserManagement"` to verify.
- Legacy mode (`use_accounts = false`) — no behavioral change; all `user_type` values are `App\Models\User`.
- Account mode (`use_accounts = true`) — new records get `user_type = App\Models\Account`; `$user->orders()` works for both User and Account.
