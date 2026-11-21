# Developer 4 Implementation Plan: Orders, Payments, Messaging, Reviews, Complaints, Admin Reports

Owner: Developer 4 (account owner)
Branch: `feature/payments` (created from `main`, because `dev` does not exist yet; rebase onto `dev` once Developer 1 creates it)
Status: draft for team review. Builds on the agreed Technical Architecture & Development Agreement. Anything marked **[GUESS]** is where the agreement is silent and needs team confirmation.

---

## 1. Scope

Developer 4 owns:

1. Buyer dashboard
2. Buyer-seller messaging and negotiation (conversations, messages, offers, counter-offers)
3. Offers and agreements (accepted terms become an `order_agreement`)
4. Order management (creation, status transitions, seller fulfilment, buyer confirmation, cancellation, disputes)
5. MTN MoMo integration (`MtnMomoService`), payment verification, callbacks, reconciliation
6. Transaction records (`payments`, `payment_events`)
7. Reviews and complaints
8. Admin reports, analytics and transaction monitoring

Developer 4 does **not** own (and will not implement): users, roles, role switching, auth, profiles, verification, admin user management (Dev 1); collection and traceability (Dev 2); listings, material lots, inventory ledger (Dev 3).

---

## 2. Current repository state

- `main` has one commit containing only `README.md`. Laravel, Inertia, React and Tailwind are **not initialized yet** (Developer 1's Day 1 task).
- There is no `dev` branch yet.
- Consequence: Dev 4 code can be written and tested now against a private local Laravel skeleton, but should only be merged after Dev 1's foundation lands on `dev`. Only Dev 4's own files get committed (see Section 11).

---

## 3. Tables owned by Developer 4

Conventions: `id` is `bigint unsigned` auto-increment, all tables have `created_at`/`updated_at`, money is `decimal(12,2)`, quantities are `decimal(12,3)`, currency is `char(3)` default `GHS`, enums are stored as `string` columns backed by PHP enums in `app/Enums` (easier migrations than MySQL `ENUM`). **[GUESS]** on the string-vs-ENUM choice; it should match whatever Dev 1 uses.

### 3.1 `conversations`
One negotiation thread between a buyer and a seller about a listing.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| listing_id | FK `listings.id`, nullable, `nullOnDelete` | null for general enquiries **[GUESS]** |
| buyer_id | FK `users.id` | |
| seller_id | FK `users.id` | copied from `listings.seller_id` |
| status | string | `open`, `closed` |
| last_message_at | timestamp, nullable | for inbox sorting |

Indexes: unique (`listing_id`, `buyer_id`) so a buyer has one thread per listing; index (`seller_id`, `last_message_at`), index (`buyer_id`, `last_message_at`).

### 3.2 `messages`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| conversation_id | FK `conversations.id`, `cascadeOnDelete` | |
| sender_id | FK `users.id` | must be buyer or seller of the conversation |
| body | text | max 2000 chars (validated) |
| read_at | timestamp, nullable | |

Index: (`conversation_id`, `created_at`).

### 3.3 `offers`
Structured price/quantity proposals inside a conversation. Counter-offers point to the offer they answer.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| conversation_id | FK `conversations.id`, `cascadeOnDelete` | |
| listing_id | FK `listings.id` | |
| made_by | FK `users.id` | |
| parent_offer_id | FK `offers.id`, nullable | the offer this counters |
| unit_price | decimal(12,2) | > 0 |
| quantity | decimal(12,3) | > 0 and ≤ available quantity |
| message | string(500), nullable | |
| status | string | `pending`, `accepted`, `rejected`, `countered`, `withdrawn`, `expired` |
| expires_at | timestamp, nullable | default 72h **[GUESS]** |
| responded_at | timestamp, nullable | |

Rules: at most one `pending` offer per conversation (enforced in the service under a row lock); only the other party can accept/reject; making a counter marks the parent `countered`.

### 3.4 `orders`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| code | string(30), unique | `ORD-2026-000123` |
| buyer_id | FK `users.id` | |
| seller_id | FK `users.id` | |
| conversation_id | FK `conversations.id`, nullable | null for "buy at listed price" |
| status | string | see 3.5 |
| subtotal | decimal(12,2) | sum of items |
| total_amount | decimal(12,2) | = subtotal for v1 (no fees/delivery charge) **[GUESS]** |
| currency | char(3) | `GHS` |
| delivery_address | string(255), nullable | required before payment; set at "buy now" or on the payment page after an accepted offer |
| delivery_notes | string(500), nullable | |
| paid_at, shipped_at, delivered_at, completed_at, cancelled_at | timestamp, nullable | |
| cancellation_reason | string(255), nullable | |

Indexes: (`buyer_id`, `status`), (`seller_id`, `status`), (`status`, `created_at`).

### 3.5 Order status (`App\Enums\OrderStatus`)

```
awaiting_payment → paid → shipped → delivered → completed
awaiting_payment → cancelled   (buyer or seller, or payment window expired)
paid | shipped | delivered → disputed → (admin) completed | cancelled
```

Negotiation lives in `conversations`/`offers`, so an order only exists once terms are agreed. This differs from the older draft plan, which had a `negotiating` order status. **[GUESS]**, confirm with the team. Any other transition is rejected by `OrderService` (HTTP 409 / Inertia error). Every transition is written to `order_status_histories`.

### 3.6 `order_items`
Snapshot of what was bought, so later listing edits do not change past orders.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| order_id | FK `orders.id`, `cascadeOnDelete` | |
| listing_id | FK `listings.id` | |
| listing_type | string | `raw_material` or `finished_product` (snapshot) |
| title | string(150) | snapshot |
| material_lot_id | FK `material_lots.id`, nullable | snapshot for raw-material traceability |
| quantity | decimal(12,3) | |
| unit | string(20) | snapshot |
| unit_price | decimal(12,2) | agreed price |
| line_total | decimal(12,2) | quantity × unit_price, computed server-side |

v1 creates one item per order (one listing per order). **[GUESS]**; the table supports more later.

### 3.7 `order_agreements`
The terms both parties agreed to. One per order.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| order_id | FK `orders.id`, unique, `cascadeOnDelete` | |
| offer_id | FK `offers.id`, nullable | null when bought at listed price |
| unit_price | decimal(12,2) | |
| quantity | decimal(12,3) | |
| total_amount | decimal(12,2) | |
| delivery_terms | string(500), nullable | |
| payment_arrangement | string | `momo_collection` for v1 (see Section 7.6) |
| buyer_accepted_at | timestamp | |
| seller_accepted_at | timestamp | |

### 3.8 `order_status_histories` **[GUESS: added by Dev 4]**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| order_id | FK `orders.id`, `cascadeOnDelete` | |
| from_status | string, nullable | |
| to_status | string | |
| changed_by | FK `users.id`, nullable | null when the system changed it (e.g. payment verified) |
| note | string(255), nullable | |
| created_at | timestamp | |

### 3.9 `payments` (as agreed, plus Dev 4 support columns)

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| order_id | FK `orders.id` | |
| payer_id | FK `users.id` | the buyer |
| payee_id | FK `users.id` | the seller |
| provider | string(30) | `mtn_momo` (later `hubtel`) |
| transaction_reference | uuid/char(36), **unique** | our UUID v4, sent to MTN as `X-Reference-Id` |
| provider_reference | string(100), nullable | MTN `financialTransactionId` |
| amount | decimal(12,2) | must equal `orders.total_amount` |
| currency | char(3) | `GHS` |
| status | string | `pending`, `successful`, `failed`, `cancelled`, `reversed` |
| payer_msisdn | string(20) | **[added]** buyer's MoMo number, `233XXXXXXXXX` |
| external_id | string(64) | **[added]** sent as `externalId` (order code) |
| failure_reason | string(255), nullable | **[added]** MTN `reason` |
| successful_order_id | FK `orders.id`, nullable, **unique** | **[added]** set only when status becomes `successful`; DB-level guarantee that an order can never have two successful payments |
| last_checked_at | timestamp, nullable | **[added]** for reconciliation |
| check_attempts | unsigned smallint, default 0 | **[added]** |
| verified_at | timestamp, nullable | **[added]** when a final status was confirmed by a status query |

Indexes: unique `transaction_reference`, unique `successful_order_id`, index (`order_id`, `status`), index (`status`, `created_at`) for reconciliation and admin monitoring.

### 3.10 `payment_events` **[GUESS: added by Dev 4]**
Append-only log of everything we sent to and received from MTN, for audit and dispute handling.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| payment_id | FK `payments.id`, `cascadeOnDelete` | |
| type | string | `request_to_pay`, `callback_received`, `status_check`, `status_changed`, `error` |
| http_status | smallint, nullable | |
| payload | json | redacted (no tokens or keys) |
| created_at | timestamp | |

### 3.11 `reviews`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| order_id | FK `orders.id` | |
| reviewer_id | FK `users.id` | |
| reviewee_id | FK `users.id` | the other party |
| rating | tinyint unsigned | 1–5 |
| comment | string(1000), nullable | |
| is_hidden | boolean, default false | admin moderation **[GUESS]** |

Unique (`order_id`, `reviewer_id`). Allowed only when the order is `completed`. Index (`reviewee_id`).

### 3.12 `complaints`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| complainant_id | FK `users.id` | |
| order_id | FK `orders.id`, nullable | |
| against_user_id | FK `users.id`, nullable | |
| category | string | `order_issue`, `payment_issue`, `product_quality`, `user_conduct`, `other` |
| subject | string(150) | |
| description | text | |
| status | string | `open`, `under_review`, `resolved`, `rejected` |
| resolution | text, nullable | |
| resolved_by | FK `users.id`, nullable | admin |
| resolved_at | timestamp, nullable | |

Raising a complaint on a `paid`/`shipped`/`delivered` order also moves it to `disputed`. **[GUESS]**

### 3.13 Enums Dev 4 adds to `app/Enums`
`OrderStatus` and `PaymentStatus` (already listed in the agreement), plus **[added]** `OfferStatus`, `ConversationStatus`, `ComplaintStatus`, `ComplaintCategory`, `PaymentProvider`, `PaymentEventType`.

---

## 4. Dependencies on other developers' tables and code

| Needed from | What | How Dev 4 uses it |
|---|---|---|
| Dev 1 | `users`, `roles`, `role_user`, `User::roles()`, `User::hasRole()`, `RoleName` enum, `EnsureUserHasRole` middleware, `AuthenticatedLayout`, `HandleInertiaRequests` | FK targets, policies (`$user->hasRole(RoleName::Buyer)`), route middleware `role:buyer`, page layout |
| Dev 1 | `AuditLog` model + `AuditService` **[GUESS on owner]** | Dev 4 calls `AuditService::record(...)` for payment and order actions; Dev 4 will not write a second audit logger |
| Dev 3 | `listings`, `raw_material_listing_details`, `product_listing_details`, `material_lots`, `Listing` model | FK targets, price/quantity lookups, order item snapshots |
| Dev 3 | `InventoryService` (owns `inventory_movements`) | Dev 4 **never writes `inventory_movements` directly**. It calls Dev 3's service to reserve on order creation, release on cancel, and convert reservation to sale on payment success |
| Dev 2 | `collection_records`, `material_batches` (read only) | Admin analytics totals (kg collected) |

### 4.1 How to work before those exist, without competing implementations

1. **Interface contracts, not copies.** Dev 4 proposes small interfaces that Dev 1/Dev 3 implement in their own modules:
   - `App\Contracts\InventoryReservations` with `availableQuantity(Listing $listing): string`, `reserve(Order $order): void`, `release(Order $order): void`, `commitSale(Order $order): void` (see `app/Contracts/InventoryReservations.php`). Dev 3's `InventoryService` implements it and binds it in a service provider.
   - Dev 1's `User` exposes `hasRole(RoleName|string $role): bool`.
   These signatures go into the shared database/contract doc for sign-off. **[GUESS]**, needs Dev 3 and Dev 1 agreement.
2. **Fakes only in tests.** `tests/Fakes/FakeInventoryReservations.php` is bound inside Dev 4 feature tests only. No production stub is committed.
3. **Factories.** Dev 4 tests use `User::factory()` and `Listing::factory()` from Dev 1/Dev 3. Until they exist, Dev 4 uses them from the private local skeleton and does not commit them.
4. **Migration order.** Dev 4 migrations are timestamped after Dev 1 (users, roles) and Dev 3 (listings, material_lots) migrations. Before merging, Dev 4 re-checks timestamps against `dev`.

---

## 5. Backend structure (Dev 4 files)

### 5.1 Models and relationships
- `Conversation`: belongsTo `listing`, `buyer` (User), `seller` (User); hasMany `messages`, `offers`, `orders` (via `orders.conversation_id`, which avoids a circular foreign key); hasOne `latestOffer`.
- `Message`: belongsTo `conversation`, `sender`.
- `Offer`: belongsTo `conversation`, `listing`, `maker` (User, `made_by`), `parent` (Offer); hasMany `counters`.
- `Order`: belongsTo `buyer`, `seller`, `conversation`; hasMany `items`, `payments`, `statusHistories`, `reviews`, `complaints`; hasOne `agreement`, `successfulPayment`.
- `OrderItem`: belongsTo `order`, `listing`, `materialLot`.
- `OrderAgreement`: belongsTo `order`, `offer`.
- `OrderStatusHistory`: belongsTo `order`, `changedBy`.
- `Payment`: belongsTo `order`, `payer`, `payee`; hasMany `events`.
- `PaymentEvent`: belongsTo `payment`.
- `Review`: belongsTo `order`, `reviewer`, `reviewee`.
- `Complaint`: belongsTo `complainant`, `order`, `againstUser`, `resolver`.
- Additions requested on other devs' models (they add them, not Dev 4): `User::ordersAsBuyer()`, `User::ordersAsSeller()`, `User::reviewsReceived()`, `Listing::orderItems()`.

### 5.2 Services
- `MessagingService`: open/find conversation, send message, mark read, make offer, counter, accept, reject, withdraw, expire.
- `OrderService`: create order from accepted offer or at listed price (inside a DB transaction with `lockForUpdate` on the listing detail row, calls `InventoryReservations::reserve`), the single `transition(Order, OrderStatus, ?User, ?note)` method enforcing the state machine, cancel (releases inventory), ship, confirm delivery, complete, dispute.
- `Payments/MtnMomoService`: provider client only (token caching, RequestToPay, status query, response normalisation). No business logic.
- `Payments/PaymentService` **[added]**: initiate payment for an order, apply a verified provider status idempotently, mark order paid, fire `PaymentSucceeded`/`PaymentFailed`.
- `ReportService` **[added]**: admin analytics and report queries.
- Reviews and complaints are small enough to live in their controllers + FormRequests, using `OrderService` for the dispute transition.

### 5.3 Controllers
- `Orders/BuyerDashboardController`
- `Orders/OrderController` (index for buyer/seller, show, store "buy now")
- `Orders/OrderFulfilmentController` (ship, confirm-delivery, cancel)
- `Orders/ReviewController`, `Orders/ComplaintController`
- `Messaging/ConversationController` (index, show, store)
- `Messaging/MessageController` (store)
- `Messaging/OfferController` (store, counter, accept, reject, withdraw)
- `Payments/PaymentController` (store = initiate, show = status page, status = JSON for polling)
- `Payments/MomoCallbackController` (API route, MTN callback)
- `Admin/ReportController`, `Admin/TransactionController` (payments monitoring, manual re-check), `Admin/ComplaintController` (review and resolve), `Admin/DisputeController` (resolve disputed orders)

### 5.4 Form requests (one per write action)
`StoreConversationRequest`, `StoreMessageRequest`, `StoreOfferRequest`, `CounterOfferRequest`, `StoreOrderRequest`, `CancelOrderRequest`, `InitiatePaymentRequest` (MSISDN format `233[0-9]{9}`), `StoreReviewRequest`, `StoreComplaintRequest`, `ResolveComplaintRequest`, `ResolveDisputeRequest`.

### 5.5 Policies
- `ConversationPolicy`: view/send only if buyer or seller of the conversation; admin view-only. Offers authorised here too (`makeOffer`, `respondToOffer` = the other party only).
- `OrderPolicy`: view (buyer, seller, admin), ship (seller, status `paid`), confirmDelivery (buyer, status `shipped`), cancel (participant, status `awaiting_payment`), review (participant, `completed`, not already reviewed), dispute.
- `PaymentPolicy`: create (order buyer, order `awaiting_payment`), view (payer, payee, admin), recheck (admin).
- `ComplaintPolicy`: create (any authenticated user), view (complainant, admin), resolve (admin).
- Admin abilities rely on Dev 1's admin role checks; Dev 4 does not add a separate admin permission system.

### 5.6 Events, listeners, jobs
- `PaymentSucceeded` → `MarkOrderPaid` (transition to `paid`, `InventoryReservations::commitSale`), `NotifyPaymentParties`.
- `PaymentFailed` → `NotifyPaymentFailed`.
- `OrderStatusChanged` → `RecordOrderAudit`.
- `VerifyMomoPayment` job (queued): queries MTN status for one payment and applies it.
- `ReconcilePendingPayments` job (scheduled every 5 minutes).
- `ExpireStaleOffers` and `CancelUnpaidOrders` jobs (scheduled hourly) **[GUESS: unpaid orders auto-cancel after 48h]**.

### 5.7 Routes
- `routes/orders.php`: buyer dashboard, orders, conversations, messages, offers, reviews, complaints. Middleware `auth`, `verified` **[if Dev 1 uses email verification]**.
- `routes/payments.php`: payment initiation, payment status page, polling endpoint.
- `routes/admin.php`: shared with Dev 1. Dev 4 adds only a clearly separated `// Developer 4: reports, transactions, complaints, disputes` group. Coordinate edits to avoid conflicts.
- `routes/api.php`: only `POST /api/payments/momo/callback/{token}` (and `PUT`, since MTN may use either), no session, throttled.

### 5.8 React pages (Inertia)
- `Pages/Buyer/Dashboard.jsx`: active orders, awaiting payment, recent conversations, quick links to the marketplace.
- `Pages/Orders/Index.jsx` (tabs: "Buying" and "Selling", the selling tab shown for recycler/artisan roles), `Pages/Orders/Show.jsx` (items, agreement, status timeline, actions, payment panel, review form, complaint link).
- `Pages/Messages/Index.jsx` (inbox), `Pages/Messages/Show.jsx` (thread with offers inline, make/counter/accept/reject).
- `Pages/Orders/Pay.jsx`: enter MoMo number, start payment, then a waiting screen that polls status and shows "Approve the prompt on your phone". Shows success only when the backend reports `successful`.
- `Pages/Admin/Reports/Index.jsx`, `Pages/Admin/Transactions/Index.jsx` + `Show.jsx`, `Pages/Admin/Complaints/Index.jsx` + `Show.jsx`, `Pages/Admin/Disputes/Index.jsx`.
- Components: `OrderStatusBadge`, `PaymentStatusBadge`, `OrderTimeline`, `OfferCard`, `OfferForm`, `MessageComposer`, `StarRating`, `MoneyAmount`.
- Seller pages for recycler/artisan dashboards (Dev 3) link into `Orders/Index?tab=selling`; Dev 4 does not edit Dev 3's dashboards, but supplies a small `SellerOrdersWidget` component Dev 3 may drop in.

---

## 6. Key workflows

### 6.1 Negotiate and agree
1. Buyer opens a listing (Dev 3 page) and clicks "Message seller" or "Make an offer" → `ConversationController@store` finds or creates the conversation.
2. Either party sends messages; buyer or seller makes an offer (price, quantity). Validation checks quantity ≤ available and listing is `active`.
3. The other party accepts, rejects or counters.
4. On accept (`MessagingService::acceptOffer`, one DB transaction): lock the offer and listing detail row, re-check availability, mark offer `accepted`, create `order` (with `conversation_id`) + `order_item` + `order_agreement`, reserve inventory via Dev 3's service, record status history.
5. "Buy now at listed price" follows the same order-creation path without an offer.

### 6.2 Pay (MTN MoMo, Collection API)
1. Buyer on `Orders/Pay` enters their MoMo number → `PaymentController@store`.
2. `PaymentService::initiate` (DB transaction, `lockForUpdate` on the order): order must be `awaiting_payment`; if a `pending` payment already exists and is younger than the retry window, return it instead of creating another (idempotent double-click protection); else create a `payments` row with a fresh UUID `transaction_reference`.
3. After commit, `MtnMomoService::requestToPay` is called. HTTP 202 means **accepted for processing, not paid**. Payment stays `pending`. Any non-202 response → `failed` with reason, logged to `payment_events`.
4. The page polls `GET /payments/{payment}/status` every 3s for up to 2 minutes; after that it says "Still waiting, we'll update this order when MTN confirms".
5. MTN calls our callback URL. The callback is **not trusted**: the controller logs it to `payment_events`, looks up the payment by `transaction_reference`, and dispatches `VerifyMomoPayment`. It returns 200 quickly.
6. `VerifyMomoPayment` calls `GET /collection/v1_0/requesttopay/{referenceId}`. Only that response can change our status.
7. `PaymentService::applyProviderStatus` (DB transaction, `lockForUpdate` on payment and order): if the payment is already final, do nothing (idempotent). If MTN says `SUCCESSFUL` **and** amount and currency match, set `successful`, `provider_reference`, `verified_at`, `successful_order_id`, then fire `PaymentSucceeded` after commit. If `FAILED`/`REJECTED`/`TIMEOUT`, set `failed` with reason. If `PENDING`, leave it.
8. `ReconcilePendingPayments` re-checks every `pending` payment older than 1 minute (backoff by `check_attempts`). After 24h still pending, it flags it for admin attention instead of guessing. **[GUESS: 24h]**

### 6.3 Fulfil and complete
Seller marks `shipped` → buyer confirms delivery (`delivered`) → order becomes `completed` immediately on buyer confirmation, or automatically 7 days after `delivered` with no dispute **[GUESS]**. Either party can then leave one review. Complaints can be raised any time; on an active paid order they move it to `disputed` and an admin resolves it.

---

## 7. MTN MoMo integration details

### 7.1 Configuration (`config/mtn_momo.php`, a Dev 4 file so the shared `config/services.php` is untouched; values from `.env`, never committed)
```
MTN_MOMO_BASE_URL=https://sandbox.momodeveloper.mtn.com
MTN_MOMO_ENVIRONMENT=sandbox          # production: mtnghana
MTN_MOMO_COLLECTION_SUBSCRIPTION_KEY=
MTN_MOMO_API_USER=                    # UUID
MTN_MOMO_API_KEY=
MTN_MOMO_CALLBACK_HOST=https://reloop.example.com
MTN_MOMO_CALLBACK_TOKEN=              # random secret placed in the callback path
MTN_MOMO_CURRENCY=EUR                 # sandbox only accepts EUR; production GHS
```

### 7.2 Sandbox first
- Sandbox API user and key are created via the Provisioning API (`POST /v1_0/apiuser`, `POST /v1_0/apiuser/{id}/apikey`). Dev 4 adds an artisan command `php artisan momo:sandbox-user` to do this.
- Sandbox uses currency `EUR` and test numbers. Our `payments.currency` stays `GHS`; the service sends the configured provider currency. The admin UI labels sandbox payments clearly.
- Production needs MTN Ghana merchant onboarding (Day 1 action, it can take time), `X-Target-Environment: mtnghana`, and a public HTTPS callback host registered with MTN.

### 7.3 `MtnMomoService` responsibilities
- `getAccessToken()`: `POST /collection/token/` with Basic auth (api user:key) + `Ocp-Apim-Subscription-Key`; cache the token for `expires_in - 60` seconds.
- `requestToPay(Payment $payment): MomoResponse`: `POST /collection/v1_0/requesttopay` with headers `X-Reference-Id` (= `transaction_reference`), `X-Target-Environment`, `X-Callback-Url`, body `amount`, `currency`, `externalId` (order code), `payer { partyIdType: MSISDN, partyId }`, `payerMessage`, `payeeNote`.
- `getTransactionStatus(Payment $payment): MomoStatus`: `GET /collection/v1_0/requesttopay/{referenceId}`, normalised to `pending | successful | failed` + amount, currency, `financialTransactionId`, reason.
- Timeouts (10s), retries only on connection errors for GET (never blindly re-POST a RequestToPay; a retry reuses the same reference ID, which MTN treats as a duplicate).
- Redacts tokens/keys before anything is logged.

### 7.4 Security rules (from the agreement)
- Never mark a payment successful from the frontend, from a callback body, or from a 202 response. Only from a status query that returns `SUCCESSFUL` with matching amount/currency.
- Callbacks: secret token in the path, constant-time compare, throttled, unknown references ignored and logged; MTN does not sign callbacks, so the status query is the verification.
- Idempotency: unique `transaction_reference`, unique `successful_order_id`, row locks, "already final = no-op".
- All money and status changes happen inside DB transactions; events fire after commit.

### 7.5 Reconciliation and admin monitoring
- Scheduled `ReconcilePendingPayments` (needs `php artisan schedule:run` cron and a queue worker on the Linux server, coordinate with Dev 1's deployment).
- Admin transactions page: filter by status/provider/date, see `payment_events` per payment, "re-check with MTN" button (runs the same verification job), list of payments pending > 24h, failed-rate, totals.

### 7.6 Open question: who receives the money **[NEEDS DECISION]**
The agreement prefers buyers paying sellers directly. MTN's Collection API `RequestToPay` pays into **the merchant account that owns the API credentials**, not into an arbitrary seller's wallet. Options:
1. **Platform collects, then pays out** sellers via MTN Disbursement API (needs MTN approval for disbursements; `reversed` status covers refunds). Simplest technically.
2. **Each seller has their own MTN merchant credentials** stored encrypted per seller. Truly direct, but every seller must onboard with MTN.
3. Hubtel as aggregator with split settlement, if MTN approval is slow.

v1 is built for option 1 (collection only; payout recorded by admin until disbursement is approved), with `payee_id` recording the seller so payout reports are accurate. The service is written so option 2 only changes where credentials come from.

---

## 8. Admin reports and analytics
- Summary cards: total GMV (successful payments), orders by status, payments by status, failed-payment rate, open complaints, open disputes.
- Sales over time (daily/weekly), split by `listing_type` (raw material vs finished product).
- Top sellers, top materials sold (kg), average rating per seller.
- Platform impact: total kg collected (Dev 2 tables, read-only queries), kg processed and sold (Dev 3 tables, read-only).
- CSV export for transactions and orders.
- All queries in `ReportService`, cached for 5 minutes, admin-only routes.

---

## 9. Tests (`tests/Feature/Orders`, `tests/Feature/Messaging`, `tests/Feature/Payments`, `tests/Feature/Admin`, plus `tests/Unit`)

Critical tests (must pass before any merge):
- Offer flow: make, counter, accept creates order + agreement + reservation; non-participant gets 403; the offer maker cannot accept their own offer; quantity over available is rejected.
- Order state machine: every allowed transition works; every disallowed one is rejected; history rows written.
- Payment initiation: only the buyer, only `awaiting_payment`, double submit returns the same pending payment.
- MoMo client (`Http::fake`): token request + caching, RequestToPay headers/body, status normalisation, error handling.
- Callback: wrong token → 404/403; callback alone does not change status; with a faked `SUCCESSFUL` status query the payment becomes `successful` and the order `paid`; duplicate callbacks are a no-op; amount mismatch is not accepted.
- Reconciliation job: pending → successful/failed via faked status queries; final payments untouched.
- Reviews: only after `completed`, one per participant per order.
- Complaints: creation, dispute transition, admin-only resolution.
- Admin reports: non-admin 403; totals match seeded data.

Plus one manual end-to-end test against the real MTN sandbox (documented steps, not part of CI).

---

## 10. Day-by-day order (aligned with the 7-day plan)

| Day | Team focus | Developer 4 work |
|---|---|---|
| D1 | Foundation | Contribute Dev 4 tables (Section 3) to the shared database spec and ER diagram. Agree the `InventoryReservations` and `hasRole` contracts with Dev 3/Dev 1. Register on the MTN MoMo developer portal, subscribe to Collection, create sandbox user/key. Start MTN Ghana merchant onboarding and get an answer on Section 7.6. |
| D2 | Collection | Dev 4 enums, migrations, models, factories. `MtnMomoService` with `Http::fake` unit tests (needs no other module). |
| D3 | Traceability & recycling | Messaging: conversations, messages, offers (`MessagingService`, controllers, policies, `Messages/*` pages) + tests. |
| D4 | Marketplace & negotiation | Orders and agreements: `OrderService`, state machine, order pages, buyer dashboard; integrate with Dev 3's listings and `InventoryService`. |
| D5 | Payments & admin | `PaymentService`, payment pages, callback, verification job, reconciliation; reviews, complaints; admin reports, transactions, complaints, disputes. |
| D6 | Integration, testing, deploy | Full test suite, sandbox end-to-end run on the deployed HTTPS server (callbacks need a public URL), queue worker + scheduler on the server, fix integration issues. |
| D7 | Final review, docs, demo | Payment flow docs, admin guide, demo script (sandbox), code review. |

Because Laravel is not initialized yet, D2–D3 work can be built and tested against a private local skeleton and merged once Dev 1's foundation is on `dev`.

---

## 11. Git workflow for Developer 4
- Work on `feature/payments` (one branch for all Dev 4 work, per the agreed branch list). Pull `dev` often once it exists.
- Commit only Dev 4 files: the files listed in Section 5 plus Dev 4 migrations, factories, tests and `docs/developer-4-plan.md`.
- Shared files (`routes/web.php` includes, `routes/admin.php`, `app/Providers/*`, `.env.example`, `bootstrap/app.php`, `routes/console.php` for the scheduler) are touched only with small, clearly marked additions, and called out in the PR description.
- PRs into `dev`, reviewed by at least one other developer. Migrations announced in the team chat before merge.

---

## 11a. What Developer 4 expects from Developer 1's foundation
- Inertia page path `resources/js/Pages` (capital P, as in the agreed structure) and Ziggy's `route()` helper in React.
- `AuthenticatedLayout` accepting a `header` prop, and `auth.user` shared by `HandleInertiaRequests`.
- `routes/web.php` requiring `routes/orders.php`, `routes/payments.php`; `bootstrap/app.php` loading `routes/api.php`; `role` middleware alias for `EnsureUserHasRole`.
- A named `login` route (guests hitting Dev 4 pages are redirected there).

## 12. Decisions needed from the team
1. Section 7.6: who receives MoMo money (platform collect + payout vs per-seller merchant accounts vs Hubtel).
2. No `negotiating` order status: orders exist only after agreement (Section 3.5).
3. `InventoryReservations` interface signatures with Dev 3.
4. Owner of `AuditLog`/`AuditService` (proposed: Dev 1, since it is platform foundation).
5. String columns + PHP enums vs MySQL `ENUM` (match Dev 1).
6. Timeouts: offer expiry 72h, unpaid order auto-cancel 48h, auto-complete 7 days after delivery, pending payment escalation 24h.
7. Role names in `RoleName` (e.g. `recycler` vs `recycling_company`).
