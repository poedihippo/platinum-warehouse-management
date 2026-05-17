# Platinum Adi Sentosa — Loyalty Program Spec (v1)

**Status:** Draft, ready for build
**Last updated:** 2026-05-17
**Owner:** Poedi (poedihippo)

---

## 1. Summary

A points-and-rewards loyalty program for end customers of Platinum Adi Sentosa products. Customers upload receipts + product photos after purchase. An admin reviews each submission, identifies the Platinum products, and awards points. Customers can spend points on physical prizes which are shipped by the marketing team.

This is **separate** from the QR verification system. Anonymous QR scanning on `verify.platinumadisentosa.com` (or current Vercel URL) continues unchanged. Loyalty adds new routes to the same app for logged-in users.

---

## 2. Glossary

| Term | Definition |
|---|---|
| **Customer** | End consumer who buys Platinum products and signs up for loyalty. |
| **Admin** | Single person (you, for v1) reviewing claims via bejo CMS. |
| **Claim** | One invoice upload by a customer. Contains 1 invoice photo, 1+ product photos, 1 invoice number. |
| **Line item** | A product the admin identified in a claim. Linked to a `product_unit` with a qty. |
| **Points** | Numeric unit of value. Each `product_unit` has a `points_per_unit`. Claim total = sum of (qty × points_per_unit) across line items. |
| **Pending points** | Points from claims not yet approved. Visible to customer but unspendable. |
| **Approved points** | Points from approved claims. Spendable on prizes. |
| **Prize** | A physical reward with a point cost. Limited stock. |
| **Redemption** | A customer's request to exchange points for a prize. Has a shipping address and status. |
| **Transaction** | A row in the points history log. Earned (from approval) or spent (from redemption). |

---

## 3. Scope

### In scope for v1

- Customer email signup + email verification + password reset
- Anonymous QR verification (existing, untouched)
- Claim submission (invoice photo + product photos + invoice number)
- Admin claim review queue in bejo CMS
- Points balance (pending + approved separately)
- Points transaction log
- Prize catalog (admin manages)
- Prize redemption with shipping address
- Redemption status tracking (pending → shipped → delivered)

### Explicitly NOT in v1

- OAuth (Google, Facebook, etc.)
- Mobile push notifications
- SMS notifications
- Multiple admin reviewers / role management
- Tier system (gold/silver/bronze)
- Referral bonuses
- Birthday bonuses, event-based bonus campaigns
- Public leaderboards
- Social sharing
- In-app chat or support tickets
- Receipt OCR / auto-detection
- Auto-rejection rules (all rejections manual)
- Point expiry
- Refund/clawback of points after approval
- Partial line-item approval

Anything not in "in scope" gets pushed to v1.1 or beyond.

---

## 4. User stories (concrete walkthroughs)

### 4.1 Customer first-time signup and claim

> Budi buys a 5kg bag of Mizuho Wheatgerm at a pet store for Rp 480,000. He sees a Platinum Adi Sentosa "Earn Points" sticker on the bag pointing to `verify.platinumadisentosa.com`.
>
> He opens the URL on his phone. Sees the verify app, taps "Login / Daftar untuk Poin" (Login / Register for Points). Fills out: name, email, password. Submits. Gets a "check your email" message. Opens his Gmail, taps the verification link, comes back to the app, logged in.
>
> Sees a "Submit Bukti Pembelian" (Submit Proof of Purchase) button on the home page. Taps it. New page:
>
> - Upload invoice photo (taps camera, snaps the receipt) ✅
> - Upload product photos (taps camera, snaps the bag) ✅
> - Invoice number text field — types "INV-2026-04-1234" from the receipt
> - Submit
>
> Sees confirmation: "Pengajuan diterima. Akan ditinjau dalam 2x24 jam."
>
> Goes to "Riwayat Poin" page. Sees one row: "Pending — Invoice INV-2026-04-1234 — submitted 2026-04-26 09:15."
>
> 18 hours later, gets an email: "Pengajuan Anda telah disetujui. Anda mendapatkan 200 poin."
>
> Opens the app, sees: Approved balance: 200, Pending: 0. Transaction log shows: "+200 — Invoice INV-2026-04-1234 — Approved 2026-04-27 03:42."

### 4.2 Customer redeems a prize

> Budi accumulates 1,500 points over 3 months. Browses the "Hadiah" (Prizes) page. Sees a Platinum-branded tote bag for 1,200 points. Taps it.
>
> Sees prize detail: photo, description, point cost, stock available. Taps "Tukar Sekarang" (Redeem Now).
>
> Form: Recipient name, phone, address. Submits.
>
> Sees confirmation: "Penukaran dikirim. Anda akan menerima update via email."
>
> Approved balance is now 300. Transaction log adds: "-1,200 — Redeemed: Platinum Tote Bag — 2026-07-30 14:20."
>
> Two days later: "Hadiah Anda telah dikirim. Resi: JNE-1234567890." Status on redemption page: "Dikirim."
>
> Five days later: gets the bag in the mail. Doesn't bother marking delivered. Admin marks it delivered.

### 4.3 Admin reviews a claim

> Tika opens bejo CMS, sees "Antrian Klaim" (Claim Queue) in the sidebar with a badge showing "3 menunggu."
>
> Clicks. List of pending claims, oldest first. Each row: customer name, submitted-at, invoice number.
>
> Opens the oldest: Budi's claim from yesterday.
>
> Sees: invoice photo (zoomable), product photos (zoomable), invoice number, customer name + email.
>
> Reviews invoice: it's a real-looking receipt from PetShop Surabaya, dated 2026-04-25, total Rp 480,000, lists "MIZUHO WHEATGERM 5KG" as one line item. Other lines are non-Platinum brands.
>
> Reviews product photo: clearly a Mizuho 5kg bag, intact, real packaging.
>
> Admin UI shows an empty "Line Items" section with an "Add Product" button.
>
> Clicks "Add Product." Dropdown with all product_units. Searches "mizuho wheat 5kg." Picks the right variant. Sets qty=1. The form auto-calculates points (e.g., 200 if Mizuho Wheatgerm 5kg = 200 points per unit).
>
> No other Platinum products visible in the photo. Done.
>
> Clicks "Approve." Confirmation modal: "Approve claim with 200 points?" Confirms.
>
> Claim disappears from queue. Budi gets an email. His points balance updates.
>
> Total time: ~90 seconds per claim. At 30 claims/day, ~45 min of admin work.

### 4.4 Admin rejects a fraudulent claim

> Same flow, but admin opens a claim where the invoice photo is clearly photoshopped (font misaligned, total doesn't add up).
>
> Or the products are competitor brands not Platinum.
>
> Admin clicks "Reject." Required field: rejection reason (free text, shown to customer).
>
> Types: "Bukti tidak valid. Produk pada foto bukan merek Platinum Adi Sentosa."
>
> Confirms. Claim moves to "Rejected." Customer gets an email with the reason.

---

## 5. Data model

New tables (Laravel migrations in `platinum-warehouse-alba`):

### 5.1 `loyalty_users`

The customer account. Separate from the existing `users` table (which is for warehouse staff / admin).

| Column | Type | Notes |
|---|---|---|
| `id` | ULID PK | |
| `email` | string, unique | |
| `name` | string | |
| `password` | string (bcrypt) | |
| `email_verified_at` | timestamp nullable | NULL = pending verification |
| `phone` | string nullable | Optional, for shipping |
| `created_at`, `updated_at` | timestamps | |

**Why separate from `users`:** loyalty customers ≠ warehouse staff. Different auth model (email verification vs. invite-only), different fields, different abuse potential. Conflating them invites accidents.

### 5.2 `product_units.points_per_unit`

Add a column to the existing `product_units` table.

```php
$table->integer('points_per_unit')->default(0);
```

Default 0 = no points (so existing products don't accidentally award points until admin sets a value).

### 5.3 `claims`

A single invoice submission by a customer.

| Column | Type | Notes |
|---|---|---|
| `id` | ULID PK | |
| `loyalty_user_id` | FK → loyalty_users | |
| `invoice_number` | string | Customer-entered, used for duplicate detection |
| `invoice_photo_path` | string | S3 / local storage path |
| `status` | enum | `pending`, `approved`, `rejected` |
| `submitted_at` | timestamp | |
| `reviewed_at` | timestamp nullable | |
| `reviewed_by` | FK → users nullable | The admin who reviewed |
| `rejection_reason` | text nullable | Free text shown to customer |
| `total_points` | integer | Sum of line items, set on approval. Denormalized for display. |
| `created_at`, `updated_at` | timestamps | |

Index: `(status, submitted_at)` for the admin queue.

### 5.4 `claim_photos`

Many-to-one with claims. A claim has 1+ product photos.

| Column | Type | Notes |
|---|---|---|
| `id` | ULID PK | |
| `claim_id` | FK → claims | |
| `photo_path` | string | |
| `position` | integer | Display order |
| `created_at` | timestamp | |

### 5.5 `claim_line_items`

Set by admin during review. Each row = one Platinum product the admin identified.

| Column | Type | Notes |
|---|---|---|
| `id` | ULID PK | |
| `claim_id` | FK → claims | |
| `product_unit_id` | FK → product_units | |
| `quantity` | integer | |
| `points_awarded` | integer | Captured at approval time. `quantity × product_units.points_per_unit` at that moment. |
| `created_at`, `updated_at` | timestamps | |

**Why capture `points_awarded` at approval:** if admin later changes `product_units.points_per_unit`, historical claims keep their original value. No retroactive changes.

### 5.6 `points_transactions`

Append-only log. Every points movement.

| Column | Type | Notes |
|---|---|---|
| `id` | ULID PK | |
| `loyalty_user_id` | FK → loyalty_users | |
| `direction` | enum | `earn`, `spend` |
| `amount` | integer | Always positive; direction tells sign |
| `source_type` | string | `claim` or `redemption` |
| `source_id` | ULID | Polymorphic ID of the source row |
| `description` | string | Pre-rendered for display, e.g. "Invoice INV-2026-04-1234" |
| `created_at` | timestamp | |

Index: `(loyalty_user_id, created_at desc)`.

**Why polymorphic source:** earned points come from claim approval; spent points come from redemption. Same table, same query, different source.

### 5.7 `prizes`

The catalog.

| Column | Type | Notes |
|---|---|---|
| `id` | ULID PK | |
| `name` | string | |
| `description` | text | |
| `photo_path` | string | |
| `point_cost` | integer | |
| `stock` | integer | Decrements on redemption |
| `is_active` | boolean | Admin can hide without deleting |
| `created_at`, `updated_at` | timestamps | |

### 5.8 `redemptions`

| Column | Type | Notes |
|---|---|---|
| `id` | ULID PK | |
| `loyalty_user_id` | FK → loyalty_users | |
| `prize_id` | FK → prizes | |
| `point_cost` | integer | Captured at redemption time |
| `status` | enum | `pending`, `shipped`, `delivered`, `cancelled` |
| `recipient_name` | string | |
| `recipient_phone` | string | |
| `recipient_address` | text | |
| `tracking_number` | string nullable | Filled in when admin marks shipped |
| `shipped_at` | timestamp nullable | |
| `delivered_at` | timestamp nullable | |
| `created_at`, `updated_at` | timestamps | |

### 5.9 Computed: points balance

**Don't store balance as a column.** Compute it from `points_transactions`:

```sql
SELECT
  SUM(CASE WHEN direction = 'earn' THEN amount ELSE 0 END) AS earned,
  SUM(CASE WHEN direction = 'spend' THEN amount ELSE 0 END) AS spent
FROM points_transactions
WHERE loyalty_user_id = ?;

-- Balance = earned - spent
```

Pending balance = sum of `claims.total_points` where status='pending' for the user. (Pending claims don't have transactions yet; transactions are written on approval.)

**Why computed:** balance can never drift from transaction history. A denormalized column will eventually get out of sync from a buggy migration or partial failure. Computing it on read is cheap with the index above.

For high-traffic optimization later, cache balance with an event-sourced model. Don't bother in v1.

---

## 6. State machines

### 6.1 Claim status

```
[pending] --approve--> [approved]
[pending] --reject--> [rejected]
```

Terminal states: `approved`, `rejected`. No transitions out. No undo. Admin gets one chance per claim.

(If admin rejects by mistake, customer has to submit again. By design — keeps the audit trail clean. Edge case is rare enough that the UX cost is acceptable.)

### 6.2 Redemption status

```
[pending] --admin marks shipped--> [shipped]
[shipped] --admin marks delivered--> [delivered]
[pending] --admin cancels--> [cancelled]   (refunds points)
```

Terminal: `delivered`, `cancelled`. `cancelled` writes a refund transaction (`direction=earn, source_type=redemption, source_id=this redemption`).

No customer-initiated cancellation in v1. If a customer wants to cancel, they message admin (Instagram, email), admin handles manually.

---

## 7. API endpoints (Laravel backend)

All loyalty endpoints under `/api/loyalty/` prefix, separate from `/api/public/stocks/{ulid}` (which stays anonymous).

### 7.1 Customer auth

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `POST` | `/api/loyalty/auth/register` | none | Email + password + name → sends verification email |
| `GET` | `/api/loyalty/auth/verify-email/{token}` | none | Verify email token |
| `POST` | `/api/loyalty/auth/login` | none | Email + password → returns Sanctum token |
| `POST` | `/api/loyalty/auth/logout` | Sanctum | Revoke current token |
| `POST` | `/api/loyalty/auth/password-reset/request` | none | Email → send reset link |
| `POST` | `/api/loyalty/auth/password-reset/confirm` | none | Token + new password |
| `GET` | `/api/loyalty/me` | Sanctum | Current user info |

### 7.2 Customer claims & points

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `POST` | `/api/loyalty/claims` | Sanctum | Submit a claim (multipart: invoice photo + product photos + invoice number) |
| `GET` | `/api/loyalty/claims` | Sanctum | List own claims (paginated) |
| `GET` | `/api/loyalty/claims/{id}` | Sanctum | One claim detail |
| `GET` | `/api/loyalty/points/balance` | Sanctum | `{ pending, approved }` |
| `GET` | `/api/loyalty/points/transactions` | Sanctum | Transaction log (paginated) |

### 7.3 Customer prizes & redemptions

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/api/loyalty/prizes` | Sanctum | List active prizes |
| `GET` | `/api/loyalty/prizes/{id}` | Sanctum | Prize detail |
| `POST` | `/api/loyalty/redemptions` | Sanctum | Redeem (prize_id + shipping info). Fails if insufficient points or out of stock. |
| `GET` | `/api/loyalty/redemptions` | Sanctum | List own redemptions |
| `GET` | `/api/loyalty/redemptions/{id}` | Sanctum | One redemption detail |

### 7.4 Admin (bejo CMS calls these)

Use existing admin auth (whatever bejo uses today — likely Sanctum on the `users` table).

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/admin/loyalty/claims?status=pending` | Review queue |
| `GET` | `/api/admin/loyalty/claims/{id}` | Claim detail with photos |
| `POST` | `/api/admin/loyalty/claims/{id}/line-items` | Add a line item (product_unit_id + qty) |
| `DELETE` | `/api/admin/loyalty/claims/{id}/line-items/{lineId}` | Remove a line item |
| `POST` | `/api/admin/loyalty/claims/{id}/approve` | Approve. Writes transactions, sends email. |
| `POST` | `/api/admin/loyalty/claims/{id}/reject` | Reject with reason. Sends email. |
| `GET/POST/PATCH/DELETE` | `/api/admin/loyalty/prizes` | CRUD prize catalog |
| `GET` | `/api/admin/loyalty/redemptions?status=...` | Redemption queue |
| `POST` | `/api/admin/loyalty/redemptions/{id}/ship` | Mark shipped, set tracking number |
| `POST` | `/api/admin/loyalty/redemptions/{id}/deliver` | Mark delivered |
| `POST` | `/api/admin/loyalty/redemptions/{id}/cancel` | Cancel + refund points |

All admin endpoints require admin auth + audit logging (who did what, when).

---

## 8. Frontend pages

### 8.1 platinum-verify-frontend (existing app, new routes)

| Route | Auth | Purpose |
|---|---|---|
| `/` | optional | Landing — anonymous "Scan QR" button + "Login / Daftar" button |
| `/scan` | optional | Existing scan flow |
| `/manual` | optional | Existing manual entry |
| `/result/[ulid]` | optional | Existing result page. If logged in, show "Earn Points" CTA below result. |
| **`/register`** | none | Signup form |
| **`/verify-email/[token]`** | none | Email verification handler |
| **`/login`** | none | Login form |
| **`/forgot-password`** | none | Request reset |
| **`/reset-password/[token]`** | none | Set new password |
| **`/dashboard`** | required | Customer home: points balance, recent claims, "Submit Claim" CTA |
| **`/submit-claim`** | required | The single-page submission form |
| **`/claims`** | required | List own claims |
| **`/claims/[id]`** | required | One claim with status |
| **`/points`** | required | Transaction log |
| **`/prizes`** | required | Catalog |
| **`/prizes/[id]`** | required | Prize detail + "Redeem" button |
| **`/redeem/[id]`** | required | Shipping form |
| **`/redemptions`** | required | List own redemptions |
| **`/redemptions/[id]`** | required | One redemption with status & tracking |

Auth state lives in a React context. Anonymous flows keep working. Logged-in users get an additional nav.

### 8.2 bejo CMS (new admin section)

| Route | Purpose |
|---|---|
| `/loyalty/claims` | Review queue (filter by status) |
| `/loyalty/claims/[id]` | Claim review (photos, line items, approve/reject) |
| `/loyalty/prizes` | Prize catalog CRUD |
| `/loyalty/redemptions` | Redemption queue (filter by status) |
| `/loyalty/redemptions/[id]` | Mark shipped/delivered/cancel |

---

## 9. Fraud rules

### 9.1 Enforced in code (automatic)

- **Email verification required** before submitting claims.
- **Invoice number per user must be unique.** If same `loyalty_user_id` + `invoice_number` already exists in claims (any status), reject the submission. This stops a customer from submitting the same receipt twice.
- **Invoice number across users — soft warning to admin.** If a different user submitted the same invoice number, surface this on the admin review page: "Invoice number INV-2026-04-1234 was also submitted by user X on date Y." Don't auto-block — could be legitimate (e.g., a family sharing).
- **Rate limit submissions.** 5 claim submissions per user per day. Stops abuse loops.
- **Rate limit redemptions.** 3 redemptions per user per day.
- **Point balance check before redemption.** Atomic transaction: deduct points + insert redemption + decrement prize stock, all in one DB transaction. If anything fails, roll back.
- **Prize stock check.** Reject redemption if stock = 0.
- **File upload limits.** Max 5MB per photo, max 6 photos per claim, only JPG/PNG/HEIC accepted.

### 9.2 Enforced by admin (manual)

- Photoshopped / fake invoices
- Photos of non-Platinum products being claimed as Platinum
- Photos taken from internet (Google image search)
- Same physical receipt being submitted by different users (the soft warning above helps but admin decides)
- Suspicious patterns (e.g. same address shipped 10 prizes in a month)

### 9.3 Things explicitly NOT prevented

- **Buying a single product and photographing it 10 times for 10 receipts.** If admin can detect this from photos, fine. Otherwise the invoice number uniqueness check catches it.
- **A customer disputing a rejection.** No appeals process in v1. They can email/Instagram us.
- **Customers using friends' accounts.** Not a real fraud vector for points loyalty.

---

## 10. Notifications

Email only in v1. No SMS, no push.

### Emails the system sends

| Trigger | Subject | Recipient |
|---|---|---|
| Signup | "Verifikasi email Anda" | Customer |
| Password reset request | "Reset kata sandi Anda" | Customer |
| Claim submitted | "Pengajuan diterima" (auto-confirmation) | Customer |
| Claim approved | "Pengajuan disetujui — Anda mendapat X poin" | Customer |
| Claim rejected | "Pengajuan tidak disetujui — alasan: ..." | Customer |
| Redemption created | "Penukaran berhasil — Hadiah sedang disiapkan" | Customer |
| Redemption shipped | "Hadiah dikirim — Resi: ..." | Customer |
| Redemption delivered | "Hadiah telah diterima" | Customer (optional, auto from admin action) |

**Don't:** send admin notifications for every claim submission. Admin opens the queue when they're ready. Notification spam kills review velocity.

Use Laravel's built-in Mailable + queue (Redis already exists). Use a service like Resend, Postmark, or SES for delivery. Don't use raw Gmail SMTP — gets flagged as spam.

---

## 11. File storage

Invoice photos and product photos are user-uploaded files. Three options:

**Option A — Local disk on Forge server** (cheapest, simplest).
- Path: `/storage/loyalty/claims/{claim_id}/invoice.jpg`
- Served via Laravel's `Storage::url()`
- Pros: zero config, free
- Cons: backup is your problem; doesn't scale past one server; no CDN

**Option B — S3 (or DigitalOcean Spaces)** (production-grade).
- Path: `s3://platinum-loyalty/claims/{claim_id}/invoice.jpg`
- Served via signed URLs
- Pros: scalable, durable, integrates with backups
- Cons: ~$5/mo, more config

**v1 recommendation: Option B** — only $5/mo, way safer than tying user uploads to a single server's disk. If the Forge server crashes / gets re-imaged, no data lost.

If you absolutely want Option A for now, plan migration to S3 before user count hits ~1000.

---

## 12. Phasing

### Phase 1 — Backend foundation (1-2 weeks)

- Migrations for all tables in §5
- Models + relationships
- Customer auth endpoints (§7.1)
- Customer claim submission (§7.2)
- Admin claim review (§7.4 — claims only)
- Points transactions logic
- Email sending setup
- File storage setup

**Ship criterion:** can complete the end-to-end "submit a claim, admin approves, customer sees points" flow via curl/Postman. No UI yet.

### Phase 2 — Frontend customer flow (1-2 weeks)

- platinum-verify-frontend adds: register, login, email verification, password reset
- Customer dashboard
- Submit claim page
- Claims history
- Points balance + transaction log
- Email templates

**Ship criterion:** a customer can sign up, submit a claim, see status, see points after approval (admin still does approval via Postman in this phase).

### Phase 3 — Bejo admin UI (1-2 weeks)

- bejo CMS new section: claim queue
- Claim review page (photos, line items, approve/reject)
- Test end-to-end with real customer-side submissions

**Ship criterion:** admin can review claims entirely in bejo without touching curl.

### Phase 4 — Prizes & redemption (1 week)

- Prize catalog CRUD in bejo
- Customer prize browse + redeem
- Redemption queue + shipping flow

**Ship criterion:** customer can redeem, admin can fulfill, prize stock decrements.

### Phase 5 — Polish (0.5-1 week)

- Email template polish
- Error states everywhere
- Pre-launch security review (similar to verify launch)
- Soft launch to 10 friendly users
- Iterate based on feedback
- Public launch

**Total: 5-8 weeks of focused work.** Adjust based on how dedicated the time is.

---

## 13. Open questions

These need answers before or during the relevant phase. Not blockers for Phase 1.

1. **Storage:** S3 or local disk? (Recommendation: S3.)
2. **Email provider:** Resend / Postmark / SES / Mailgun? (Recommendation: Resend — easiest setup, $20/mo for 50k emails.)
3. **Prize sourcing & fulfillment:** who buys prizes, who packs, who ships? Need a name and a process.
4. **Customer support:** Instagram DMs continue, or add a contact form? Email to a specific address?
5. **Privacy policy + Terms of Service:** required before launch under Indonesian PDP law. Need a lawyer or template.
6. **Tax implications:** points-as-currency may have implications for both Platinum and customers under Indonesian tax law. Worth a consultation before launch.
7. **What about the existing Vercel verify URL** (`bejo-platinum-product-verify.vercel.app`)? Still relevant or abandoned?

---

## 14. Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Admin burnout from review workload | Medium | High (program stalls) | Set SLA realistically; consider second reviewer at 200+ claims/week |
| Fake invoices not caught by admin | Medium | Low (small per-incident cost) | Accept as cost of doing business; tighten if pattern emerges |
| Prize fulfillment delays | High | High (customer trust loss) | Communicate honestly; never promise faster than you can deliver |
| Stock depletion of popular prizes | High | Medium | Restock or rotate catalog |
| User uploads inappropriate content (NSFW etc.) | Low | Medium (admin discomfort, legal) | Terms of Service prohibits; admin can soft-delete and ban |
| Migration loses data | Low | Catastrophic | Backup DB before every Phase 1 migration; test on staging |
| User signs up with fake email, can't recover account | Medium | Low | Email verification flow; password reset; accept loss |

---

## 15. Decisions log (for future reference)

- **Auth:** email + password (NOT OAuth) — simpler, no Google dependency. v1.5 may add OAuth.
- **Points value:** per `product_units.points_per_unit` — variant-level granularity. Admin sets values in bejo.
- **User flow:** single-page submission form. No QR scanning required for points.
- **Brand check:** strict, manual. Admin verifies products are Platinum brand from photos.
- **Retailer restriction:** none. Any store with a valid receipt.
- **Architecture:** loyalty UI lives in existing `platinum-verify-frontend`. Anonymous scan stays anonymous.
- **Approval:** all-or-nothing per claim. No partial approval.
- **History:** transaction log, not just balance.
- **Admin reviewers:** one person for v1.
- **SLA:** 2x24 hours (48h), soft target.
- **Volume:** few hundred users in Q1.

---

End of spec.
