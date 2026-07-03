# UI-5 Manual Payment Experience — Audit Report

## 1. Executive Summary

**Step:** UI-5 — Manual Payment Experience
**Status:** Complete
**Objective:** Replace the payment placeholder with a full manual payment page that lets merchants select a payment method, view account details, upload evidence, submit for review, and see the waiting-review confirmation — using existing backend services without modifying payment logic.

**Backend changes:** Updated `payment()` to load intent + payment methods; added `paymentSubmit()` for evidence upload + state transition.

---

## 2. Payment Flow

```
Checkout (UI-4) → Manual Payment (UI-5) → Evidence Upload → Submit → Waiting Review
                                                        │
                                                        ├─ Select bank account
                                                        ├─ Copy account details
                                                        ├─ Upload screenshot/receipt
                                                        ├─ Add optional note
                                                        └─ Submit → WAITING_REVIEW state
```

State machine integration:
- `WAITING_PAYMENT` → Upload form shown
- `WAITING_REVIEW` → Success/confirmation view
- `REJECTED` → Rejection view with contact support
- `COMPLETED` / `APPROVED` / `PAID` → Completed view
- No intent → Error state

---

## 3. Payment Method Cards

`PaymentMethodCard` renders each active `bank_transfer` payment method:

| Element | Details |
|---------|---------|
| Bank Icon | Generic bank icon in blue rounded square |
| Bank Name | `bank_name` from PaymentMethod config |
| Method Name | `name` field fallback |
| Account Name | Displayed with copy button |
| Account Number | Monospace font, displayed with copy button |
| QR Code | Shown when `qr_image_url` is set |
| Reference Reminder | Shows intent reference with copy button |
| Selection | Radio-style: blue border + ring when selected |

Methods loaded from `PaymentMethod::active()->where('type', 'bank_transfer')`.

---

## 4. Upload Experience

`UploadArea` component:

- **Click to upload** — file picker dialog
- **Drag & drop** — visual feedback on dragover (blue border + background)
- **Image preview** — thumbnail with file name + size
- **Remove button** — red circle X overlay on preview
- **Validation** — accepts JPEG, PNG, GIF; max 5MB
- **No existing upload component** — built from scratch with standard HTML5 drag/drop + FileReader preview

---

## 5. Reference Experience

- Reference number shown in 3 places:
  1. Each payment method card footer ("Reference to include")
  2. Payment Summary sidebar
  3. Success/Waiting Review page header
- Each instance has an independent `CopyButton` with:
  - Clipboard API (`navigator.clipboard.writeText`) with fallback
  - Copy → "Copied" (Check icon) feedback for 2.5 seconds
  - `aria-label="Copy {text}"`

---

## 6. Payment Instructions

Numbered 6-step instruction card:

1. Transfer the exact amount (dynamic amount shown)
2. Use your reference number (reference displayed)
3. Upload payment evidence
4. Submit for review
5. Wait for admin review (~24 hours)
6. Subscription activated

---

## 7. Submission & Success

**Submit:**
- Form with `useForm`-style POST via `router.post()`
- File upload via `FormData`
- Validates: evidence required, method selected
- Disable + loading spinner during submission
- Idempotency via backend `PaymentExecutionGuard`

**Success (Waiting Review) view:**
- Purple success card with Clock icon
- Reference number displayed + copyable
- "Waiting Review" status badge
- Next Steps timeline: Submitted → Awaiting Review → Admin Verification → Subscription Activated
- Amber info card: "Your store remains unchanged until approval"
- Actions: Back to Billing, View Payment History

---

## 8. Backend Integration

| Operation | Service/Method | Record Created | State Transition |
|-----------|---------------|----------------|-----------------|
| Page load `GET /payment?intent={ref}` | `PaymentIntentService::findByReferenceForTenant()` | — | — |
| Load payment methods | `PaymentMethod::active()->where('type', 'bank_transfer')` | — | — |
| Upload evidence | `ImageService::upload()` | File on disk (`payment-evidence/`) | — |
| Store evidence record | `PaymentEvidenceService::store()` | `PaymentEvidence` row + timeline event `evidence_uploaded` | — |
| Confirm payment | `ManualPaymentService::confirmPayment()` via `PaymentExecutionGuard` | — | `WAITING_PAYMENT` → `WAITING_REVIEW` |

---

## 9. Status Handling

| Intent Status | UI Shown |
|---------------|----------|
| `null` (no intent) | Error view: "Payment Intent Not Found" + Back to Plans |
| `waiting_payment` | Upload form with all payment details |
| `waiting_review` | Success/Waiting Review view |
| `completed`, `approved`, `paid` | Completed view with green check |
| `rejected` | Rejected view with support contact |
| `cancelled`, `expired`, `failed` | Terminal state message |

---

## 10. Trust & Security

Two trust displays:
1. **Sidebar card** — "Trust & Security" with checkmarked list:
   - Your payment is reviewed manually
   - Your reference number is unique
   - Your payment history is permanently recorded
   - Your subscription activates only after approval
2. **Blue info card** on submit form — "Why is manual payment safe?" with same points

---

## 11. Components Reused

| Component | Usage |
|-----------|-------|
| AdminLayout | Layout wrapper |
| CURRENCY_SYMBOL | Price formatting |
| ImageService | File upload handler |
| PaymentIntentService | Intent lookup |
| PaymentEvidenceService | Evidence record + timeline |
| ManualPaymentService | State transition to WAITING_REVIEW |
| PaymentExecutionGuard | Idempotency protection |

---

## 12. Components Added

**Frontend (inline in Payment.jsx):**
- `CopyButton` — reusable text copy with feedback
- `StatusBadge` — payment intent status badge (8 variants)
- `PaymentMethodCard` — bank account card with copy, QR, reference
- `UploadArea` — drag & drop image upload with preview

**Backend:**
- `AdminBillingController@paymentSubmit()` — evidence upload + state transition

---

## 13. Files Modified

| File | Change |
|------|--------|
| `routes/storefront-admin.php` | Added `POST /billing/payment/submit` route |
| `app/Http/Controllers/Admin/AdminBillingController.php` | Replaced placeholder `payment()` with full intent/method loader; added `paymentSubmit()` with validation, upload, evidence storage, state transition |
| `resources/js/Pages/Admin/Billing/Payment.jsx` | Complete redesign: upload form, method cards, instructions, success view, trust cards, status handling |

---

## 14. Regression Results

**Test suites:**

| Suite | Tests | Result |
|-------|-------|--------|
| `AdminBillingPageTest` | 13 (116 assertions) | ✅ All pass |
| `SubscriptionLimitTest` | 14 | ✅ All pass |
| `SubscriptionLimitServiceTest` | 9 | ✅ All pass |
| `SubscriptionLockModeTest` | 19 | ✅ All pass |
| **Total** | **55 tests, 292 assertions** | **✅ All pass** |
| **Frontend build** | 2501 modules | **✅ 0 errors** |

**Route verification:**
- `GET /store/{slug}/admin/billing/payment` → `payment()` ✅
- `POST /store/{slug}/admin/billing/payment/submit` → `paymentSubmit()` ✅

---

## 15. Manual QA Checklist

- [x] Payment page loads with intent reference from checkout
- [x] Status badge matches payment intent state
- [x] Active bank transfer payment methods displayed as cards
- [x] Each card shows bank name, account name, account number
- [x] Account name copy button works
- [x] Account number copy button works
- [x] Reference number copy on each card works
- [x] QR code image displayed when present
- [x] Radio selection works (click + keyboard)
- [x] Payment summary shows plan, billing, currency, amount, reference
- [x] Plan change indicator shows current → selected
- [x] Payment instructions show 6 numbered steps with dynamic amount/reference
- [x] Upload area: click to open file picker
- [x] Upload area: drag & drop works with visual feedback
- [x] Upload area: image preview shown after selection
- [x] Upload area: remove button works
- [x] Note textarea with 500 char counter
- [x] Submit button disabled when no file or method selected
- [x] Submit shows loading spinner
- [x] On successful submit, redirects to waiting_review view
- [x] Waiting Review shows success card, reference, status badge
- [x] Waiting Review shows next steps timeline
- [x] Waiting Review shows "store remains unchanged" notice
- [x] Waiting Review has Back to Billing + View Payment History buttons
- [x] Terminal states (completed/paid/approved) show green completed view
- [x] Rejected state shows rejection view with support link
- [x] No intent shows error state with Back to Plans
- [x] Trust & Security card displayed in sidebar
- [x] Safety info card displayed on submit form
- [x] Empty payment methods shows "No payment methods available"
- [x] Contact Support button on footer
- [x] Responsive: stacks to single column on mobile
- [x] Frontend builds with zero errors

---

## 16. Remaining UI Sprint Roadmap

| Step | Feature | Status |
|------|---------|--------|
| UI-1 | Billing Navigation & IA | ✅ Complete |
| UI-2 | Merchant Billing Dashboard | ✅ Complete |
| UI-3 | Plan Selection & Upgrade Experience | ✅ Complete |
| UI-4 | Checkout Experience | ✅ Complete |
| UI-5 | Manual Payment Experience | ✅ Complete |
| UI-6 | Payment History UI | Pending |
| UI-7 | Transaction UI | Pending |
| UI-8 | SuperAdmin Billing | Pending |
| UI-9 | Webhook UI | Pending |
