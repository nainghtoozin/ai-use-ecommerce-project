# Customer Address Book + Checkout Auto Fill Report

## Summary
Implemented city→township dropdown chaining in the Addresses page, added checkout auto-fill from the customer's default address, added a Change Address feature at checkout, and made city/township required in address validation.

## Changes

### 1. Fix City→Township Dropdown Chaining (`resources/js/Pages/Storefront/Addresses.jsx`)
- Added `townships`, `loadingTownships` state and `fetchTownships(cityId)` function that calls `GET /api/townships/{cityId}`
- Townhip select renders dynamic options (with "Loading..." state) instead of static empty list
- City `<select>` onChange calls `fetchTownships(e.target.value)` and clears township selection
- `openEdit` pre-loads townships when opening an existing address with a city
- `openCreate` / `closeForm` reset `townships` state
- Removed duplicate function definitions that existed from previous iteration

### 2. Make City/Township Required (`app/Http/Controllers/StorefrontCustomerController.php`)
- `storeAddress`: `city_id` and `township_id` changed from `nullable` to `required`
- `updateAddress`: same change — both are now required in address validation

### 3. Checkout Auto Fill (`app/Http/Controllers/StorefrontCheckoutController.php`)
- Added `CustomerAddress` import
- When user is authenticated: queries `CustomerAddress::forUser()`, ordered by `is_default DESC, created_at DESC`
- Sets `defaultAddress` to the first default address (or the most recent address if none is default)
- Passes `addresses` (collection) and `defaultAddress` (model or null) to Inertia page

### 4. Checkout Address Selection UI (`resources/js/Pages/Storefront/Checkout.jsx`)
- Added `addresses` and `defaultAddress` props
- Added `showAddressPicker` and `selectedAddress` state
- `useEffect` auto-fills form (`first_name`, `last_name`, `phone`, `address`, `city_id`, `township_id`, `postal_code`) from `defaultAddress` on mount, and fetches townships
- `selectAddress(address)` function fills form fields and closes the picker
- UI section at top of shipping step shows:
  - "Delivering to: [name] — [address]" with a "Change" button
  - When "Change" is clicked, toggles a list of saved address cards
  - Each card shows label, address, name, phone, with "Default" badge
  - Selected address is highlighted with blue border
  - Clicking an address calls `selectAddress` to update form

## Files Modified
- `resources/js/Pages/Storefront/Addresses.jsx` — township API call, dropdown chaining, dedup
- `app/Http/Controllers/StorefrontCustomerController.php` — city/township required validation
- `app/Http/Controllers/StorefrontCheckoutController.php` — pass addresses + defaultAddress props
- `resources/js/Pages/Storefront/Checkout.jsx` — address auto-fill, change address picker

## Verification
- Vite build: 0 errors
- Storefront Cart/Checkout tests: 15/15 pass (110 assertions)
- No new routes or API endpoints required (reuses existing `/api/townships/{cityId}`)
- Address form works same as checkout for city→township chaining
