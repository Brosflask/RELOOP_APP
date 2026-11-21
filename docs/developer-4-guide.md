# Developer 4 Module Guide: Orders, Payments, Messaging, Admin

How to set up, run, test and integrate Developer 4's module. The design and reasoning are in `docs/developer-4-plan.md`.

---

## 1. What the module does

| Area | Where | Who uses it |
|---|---|---|
| Conversations, messages, offers and counter-offers | `/messages` | buyers and sellers |
| Orders (buy now or from an accepted offer), fulfilment, cancel | `/orders` | buyers and sellers |
| MTN MoMo payment | `/orders/{order}/pay`, `/payments/{payment}` | buyers |
| Reviews | order page, `/users/{user}/reviews` | participants, public |
| Complaints | `/complaints` | everyone |
| Buyer dashboard | `/buyer/dashboard` | `buyer` role |
| Admin reports, transactions, complaints, disputes, reviews | `/admin/...` | `admin` role |

Order lifecycle: `awaiting_payment → paid → shipped → delivered → completed`, with `cancelled` (before payment) and `disputed` (after payment, admin settles).

---

## 2. Integration checklist for other developers

### Developer 1 (foundation)
- [ ] `routes/web.php` requires `routes/orders.php`, `routes/payments.php` and `routes/admin.php`.
- [ ] `bootstrap/app.php` loads `routes/api.php` (`->withRouting(api: __DIR__.'/../routes/api.php', ...)`). This is where MTN's callback lands.
- [ ] `role` middleware alias for `EnsureUserHasRole` (used as `role:buyer`, `role:admin`).
- [ ] `User::hasRole(RoleName|string): bool` and a `RoleName` enum with `buyer` and `admin`.
- [ ] Named `login` route.
- [ ] Inertia page path `resources/js/Pages`, Ziggy `route()` in React, `AuthenticatedLayout` with a `header` prop, `auth.user` shared by `HandleInertiaRequests`.
- [ ] In `routes/console.php`: `App\Console\Dev4Schedule::register(app(Illuminate\Console\Scheduling\Schedule::class));`
- [ ] Add `admin` routes for user management inside the marked section of `routes/admin.php`.
- [ ] Optional: `App\Services\AuditService::record(?int $userId, string $action, object $subject, array $metadata)`. Dev 4's `RecordAuditTrail` listener uses it automatically once it exists.

### Developer 3 (listings and inventory)
- [ ] Implement `App\Contracts\InventoryReservations` in `InventoryService` and bind it:
  `$this->app->bind(\App\Contracts\InventoryReservations::class, \App\Services\InventoryService::class);`
  - `availableQuantity(Listing)`: what can still be ordered.
  - `reserve(Order)`: write `reservation` movements for each order item. Throw if stock is short.
  - `release(Order)`: write `reversal` movements (order cancelled before payment).
  - `commitSale(Order)`: turn the reservation into a `sale` (payment verified).
  All four run inside Dev 4's DB transaction.
- [ ] `ListingFactory` (Dev 4's tests use it).
- [ ] Listing status value for "can be ordered" is assumed to be `active` (`App\Services\Orders\ListingTerms::ORDERABLE_STATUS`).
- [ ] Listing page: link to `route('messages.store')` (POST `listing_id`) for "Message seller" and to `route('orders.store')` (POST `listing_id`, `quantity`, `delivery_address`) for "Buy now".
- [ ] Optional: show `SellerOrdersWidget` on recycler/artisan dashboards with `OrderSummary::forSeller($user)`.

### Developer 2 (collection)
- [ ] Admin "kg collected" reads `collection_records` (read-only). It looks for a column named `actual_weight_kg`, `weight_kg` or `quantity_kg`. Tell Dev 4 the real column name.

---

## 3. MTN MoMo setup

### 3.1 Sandbox (development and demo)
1. Sign up at the MTN MoMo developer portal, subscribe to the **Collections** product and copy the primary subscription key.
2. In `.env`:
   ```
   MTN_MOMO_BASE_URL=https://sandbox.momodeveloper.mtn.com
   MTN_MOMO_ENVIRONMENT=sandbox
   MTN_MOMO_CURRENCY=EUR
   MTN_MOMO_COLLECTION_SUBSCRIPTION_KEY=<primary key>
   MTN_MOMO_CALLBACK_HOST=https://<your public host>
   MTN_MOMO_CALLBACK_TOKEN=<long random string, e.g. php -r "echo bin2hex(random_bytes(24));">
   ```
3. Create the sandbox API user and key:
   ```
   php artisan momo:sandbox-user --callback-host=<your public host without https://>
   ```
   Copy the printed `MTN_MOMO_API_USER` and `MTN_MOMO_API_KEY` into `.env`.
4. `php artisan config:clear`.

The sandbox only accepts EUR, so the amount is sent to MTN as EUR while Reloop records stay in GHS. The pay page shows a "Test mode" banner.

### 3.2 Production (Ghana)
- `MTN_MOMO_BASE_URL=https://proxy.momoapi.mtn.com` (confirm with MTN during onboarding), `MTN_MOMO_ENVIRONMENT=mtnghana`, `MTN_MOMO_CURRENCY=GHS`.
- API user/key come from MTN's partner portal after merchant onboarding (not from the artisan command).
- The callback host must be public HTTPS and registered with MTN.
- Open decision: whether the platform collects and pays sellers out (planned), or each seller has their own merchant account. See plan Section 7.6.

### 3.3 Server requirements
- Queue worker: `php artisan queue:work --tries=3` (supervisor or systemd).
- Scheduler cron: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
- HTTPS everywhere. Never commit `.env`.

---

## 4. How payments are kept safe
- A payment is marked **successful only** after MTN's status endpoint says `SUCCESSFUL` with the same amount and currency. Callbacks, the 202 "accepted" reply and the browser can never mark anything paid.
- Callbacks use `/api/payments/momo/callback/{token}/{reference}`. A wrong token gets 404. The body is only logged; the payment is then verified with MTN.
- Final payments (successful, failed) never change again. Duplicate callbacks and overlapping jobs are harmless.
- A double-click on "Pay" reuses the pending payment. A new attempt is blocked while an older one is still open at MTN.
- `payments.successful_order_id` is unique, so the database itself refuses two successful payments linked to one order. A genuine second charge is recorded and flagged for a refund.
- Every request and reply is logged in `payment_events` (no tokens or keys).
- `ReconcilePendingPayments` re-checks pending payments every 5 minutes with back-off. MTN "not found" after 15 minutes becomes failed. Still pending after 24 hours gets flagged.

---

## 5. Manual end-to-end test (sandbox)
Run on the deployed HTTPS server so MTN can reach the callback.

1. Log in as a buyer, open an active listing, click "Message seller", make an offer.
2. Log in as the seller, accept it. An order appears with status "Awaiting payment".
3. As the buyer, open the order, click "Pay with MTN MoMo", enter a sandbox test number (e.g. `46733123453`).
4. The waiting screen polls. Within about a minute it should show "Payment confirmed by MTN".
5. Check `/admin/transactions`: the payment shows `successful`, with `request_to_pay`, `callback_received` (if MTN called back) and `status_check` entries in its log.
6. As the seller, mark shipped. As the buyer, confirm receipt. Leave reviews on both sides.
7. Repeat with a number MTN's sandbox documentation lists for failed or rejected payments, and confirm the page shows "did not go through" with "Try again".
8. Raise a complaint on a paid order and settle it from `/admin/disputes`.

---

## 6. Admin guide
- **Reports** (`/admin/reports`): pick a date range. Sales count only MTN-verified payments. "Export transactions (CSV)" for accounting.
- **Transactions** (`/admin/transactions`): filter by status, "Needs attention" or "Pending over 1 hour". Open a payment to see its MTN log, click **Re-check with MTN**, or **Mark as handled** after dealing with a flagged payment (e.g. after a manual refund).
- **Complaints** (`/admin/complaints`): set "Under review", then "Resolved" or "Rejected" with a response the complainant can read.
- **Disputes** (`/admin/disputes`): "Complete" releases the order to the seller. "Cancel" ends it and flags the payment for a refund. Refunds happen outside the platform until MTN approves disbursements.
- **Reviews** (`/admin/reviews`): hide abusive reviews. Hidden reviews drop out of ratings.

---

## 7. Tests
`php artisan test tests/Feature/Messaging tests/Feature/Orders tests/Feature/Payments tests/Feature/Admin tests/Unit/Enums`

Tests use `Http::fake` for MTN and `tests/Fakes/FakeInventoryReservations` in place of Dev 3's inventory service. They have been run on SQLite; run them on MySQL once the foundation is in place.

### Timings that need team sign-off ([GUESS])
| Rule | Value | Where |
|---|---|---|
| Offer expiry | 72 hours | `MessagingService::OFFER_LIFETIME_HOURS` |
| Unpaid order auto-cancel | 48 hours | `CancelUnpaidOrders::AFTER_HOURS` |
| Auto-complete after delivery | 7 days | `CompleteDeliveredOrders::AFTER_DAYS` |
| Pending payment flagged | 24 hours | `PaymentService::ATTENTION_AFTER_HOURS` |
