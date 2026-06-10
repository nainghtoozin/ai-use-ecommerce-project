# Onboarding Completion

After the store owner verifies their email and the tenant is activated, they are redirected to an onboarding completion page that celebrates the activation and provides quick-access links.

## Flow

```
User clicks verification link (signed URL)
  │
  ├─ VerifyEmailController
  │    ├─ markEmailAsVerified()
  │    ├─ fires Verified event (→ ActivateTenantOnVerified activates tenant)
  │    └─ redirect to storefront.onboarding.complete
  │
  └─ OnboardingComplete page (/store/{slug}/onboarding/complete)
       ├─ Store Name
       ├─ Store URL (link, opens new tab)
       ├─ Admin Login URL (link)
       ├─ Subscription Plan + Status
       ├─ [Visit Store] button
       └─ [Login to Admin] button
```

## Route

- **URL:** `/store/{store_slug}/onboarding/complete`
- **Name:** `storefront.onboarding.complete`
- **Middleware:** `['storefront', 'tenant.binding']` (inherited from storefront group)
- **Controller:** `CreateStoreController@onboarding`
- **Auth:** No authentication required (public page)

## Controller

`CreateStoreController@onboarding($store_slug)`

```php
public function onboarding($store_slug)
{
    $tenant = Tenant::where('slug', $store_slug)->firstOrFail();

    $subscription = $tenant->subscription;
    $plan = $subscription?->plan;

    return Inertia::render('Public/OnboardingComplete', [
        'storeName'        => $tenant->name,
        'storeSlug'        => $tenant->slug,
        'storeUrl'         => url('/store/' . $tenant->slug),
        'adminLoginUrl'    => route('storefront.admin.login', ['store_slug' => $tenant->slug]),
        'subscriptionPlan' => $plan ? $plan->name : 'Free',
        'status'           => $tenant->status,
    ]);
}
```

## Page Component

`resources/js/Pages/Public/OnboardingComplete.jsx`

Displays:
- Success icon (green checkmark in circle)
- "Your Store is Live!" heading
- Store name
- Info card with:
  - Store Name
  - Store URL (clickable link to storefront)
  - Admin Login URL (clickable link to admin login)
  - Subscription Plan name + Status badge
- "Visit Store" button (opens new tab)
- "Login to Admin" button

## Update to VerifyEmailController

After email verification, the controller now redirects to the onboarding page instead of the login page:

```php
return redirect()->route('storefront.onboarding.complete', [
    'store_slug' => $user->tenant->slug,
]);
```

This redirect is used for both:
- First-time verification (fires `Verified` event → activates tenant)
- Already verified user clicking the link again

## Test Checklist

### Positive
- [ ] After clicking verification link, user is redirected to `/store/{slug}/onboarding/complete`
- [ ] Page displays correct store name
- [ ] Store URL links to `/store/{slug}`
- [ ] Admin Login URL links to `/store/{slug}/admin/login`
- [ ] Subscription plan name is shown
- [ ] Status badge shows "active"
- [ ] "Visit Store" button opens storefront in new tab
- [ ] "Login to Admin" navigates to admin login page

### Edge Cases
- [ ] Already verified user clicking verification link → onboarding page (not an error)
- [ ] Invalid store slug → 404
- [ ] Page is publicly accessible (no auth required)
- [ ] Plan name falls back to "Free" if no subscription exists
