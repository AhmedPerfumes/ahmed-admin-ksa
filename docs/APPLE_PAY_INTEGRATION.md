# Direct Apple Pay on Checkout (PayTabs) — Implementation Guide

Reusable guide for adding **Apple Pay directly on the checkout page** (no redirect to a hosted payment page), with the card charge done through the **PayTabs Direct Payment API**.

It documents what was built for **ksa.ahmedalmaghribi.com (Saudi Arabia)** and what to change to repeat it for **UAE**.

| | |
|---|---|
| Frontend | Next.js 14 (`Ahmed-Website-KSA`) |
| Backend | Laravel 10 + Botble CMS (`ahmed-admin-ksa`), Sanctum auth |
| Payment provider | PayTabs (Direct Payment API) |
| KSA Merchant ID | `merchant.ksa.ahmedalmaghribi.com` |
| KSA site / API | `https://ksa.ahmedalmaghribi.com` / `https://adminksa.ahmedalmaghribi.com` |
| Status (KSA) | Built and tested end to end in the Apple sandbox. First live payment still to be verified. |

> Never put real keys, tokens or certificates in this file or in git. Values below are placeholders.

---

## 1. How it works (overview)

Apple Pay has three roles:

- **Browser (Safari)** — shows the Apple Pay button and sheet, and produces an **encrypted payment token** after Face ID / Touch ID. Our servers never see the card number.
- **Our Laravel server** — proves to Apple that we are the merchant (*merchant validation*), creates and prices the order, and sends the token to PayTabs.
- **PayTabs** — decrypts the token (using the *Payment Processing certificate* we uploaded to them) and charges the card.

### Architecture

```
 ┌──────────────────────── Customer's iPhone / Mac (Safari) ───────────────────────┐
 │  Next.js checkout                                                               │
 │   ApplePayWidget.jsx  ── <apple-pay-button> + ApplePaySession                   │
 └───────┬───────────────────────────┬─────────────────────────────┬───────────────┘
         │ (1) GET /applePay/config  │ (3) POST /applePay/          │ (5) POST /storeOrder
         │     (Bearer token)        │     validateMerchant         │ (6) POST /applePay/charge
         ▼                           ▼                              ▼
 ┌──────────────────────────── Laravel API (adminksa…) ────────────────────────────┐
 │  ApplePayController ──► ApplePayService                                         │
 │    config / validateMerchant / charge     isEligible · validateMerchant · charge│
 │  OrderController::storeOrder  (payment_method = "applepay")                     │
 └───────┬──────────────────────────────────────────────┬──────────────────────────┘
         │ (4) mutual-TLS POST with Merchant Identity    │ (7) POST /payment/request
         │     certificate to Apple's validationURL      │     with apple_pay_token
         ▼                                               ▼
   Apple Pay servers                                 PayTabs  ──► card network / bank
```

### Two different Apple certificates (the most confusing part)

| Certificate | Used for | Who holds the private key | Where it ends up |
|---|---|---|---|
| **Payment Processing Certificate** | PayTabs decrypts the payment token | PayTabs (their CSR) | Uploaded in Apple Developer, then installed in the **PayTabs dashboard** |
| **Merchant Identity Certificate** | **Our server** proves to Apple that it is the merchant | **We** generate it (OpenSSL) | `storage/app/applepay/merchant-cert.crt` + `.key` on the **Laravel server** |

### End-to-end payment flow

1. Checkout loads. The widget calls `GET api/applePay/config` with the customer's bearer token. Laravel decides eligibility from the token (never from anything the browser sends) and answers `{eligible:true,…}` or `{eligible:false}`.
2. If eligible, the widget loads Apple's SDK (`apple-pay-sdk.js`) and asks the device `canMakePayments()`. If yes, the **Apple Pay radio** is shown as the first payment option.
3. Customer selects Apple Pay → the *Place order* button (inline and mobile sticky bar) is replaced by the black **Buy with Apple Pay** button.
4. Customer taps it. Pre-checks run (cart, address, OTP, terms). Then, **synchronously inside the tap**, `new ApplePaySession(...)` is created and `begin()` is called.
5. `onvalidatemerchant` → `POST api/applePay/validateMerchant {validation_url}`. Laravel checks eligibility, checks the URL is a real Apple host, then calls Apple with the Merchant Identity certificate (mutual TLS) and returns Apple's `merchant_session`. The widget calls `completeMerchantValidation`.
6. Customer authorises (Face ID). Apple returns the encrypted `payment.token`.
7. `onpaymentauthorized`:
   a. `POST api/storeOrder` with `payment_method:"applepay"` → Laravel recalculates all prices, creates the order in `TRASH` (unpaid) status and answers `"ApplePay order created"` + order code.
   b. `POST api/applePay/charge {order_code, display_total, apple_pay_token}` → Laravel verifies owner / status / age / amount, takes a lock, calls PayTabs `POST /payment/request`, verifies the answer, records the payment and sets the order to `processing`.
8. Widget completes the sheet (success / failure) and redirects to the existing `shop-order-payment-complete` page.

---

## 2. Prerequisites

- Apple Developer Program membership (Account Holder / Admin to create Merchant IDs and certificates).
- A PayTabs account/profile for the target country with **Apple Pay enabled**, plus a **test profile**.
- The site must be served over **HTTPS** on the domain you verify with Apple.
- Safari on iPhone / iPad / Mac with a card in Wallet to test (Apple Pay does not work in Chrome/Firefox, or on Windows/Android).
- Server access to put certificate files under `storage/app/applepay/` and edit `.env`.
- OpenSSL (Git Bash includes it; XAMPP has `C:\xampp\apache\bin\openssl.exe`).

---

## 3. Apple Developer setup

### 3.1 Create the Merchant ID
1. [developer.apple.com/account](https://developer.apple.com/account) → **Certificates, Identifiers & Profiles → Identifiers**.
2. Click **+** → **Merchant IDs** → Continue.
3. Description: e.g. `Ahmed Al Maghribi KSA`. Identifier: `merchant.<country>.<yourdomain>` (KSA: `merchant.ksa.ahmedalmaghribi.com`).
4. Register.

### 3.2 Verify the domain
1. Open the Merchant ID → **Merchant Domains → Add Domain** → enter the **storefront** domain (e.g. `ksa.ahmedalmaghribi.com`, no `https://`).
2. Download `apple-developer-merchantid-domain-association.txt`.
3. Place it in the Next.js project at `public/.well-known/apple-developer-merchantid-domain-association.txt` (must be reachable at `https://<domain>/.well-known/apple-developer-merchantid-domain-association.txt`). Deploy the frontend.
4. Click **Verify**. The domain must show **Verified**.

> This file is domain-specific. A new domain (UAE) needs its own file from Apple. If the file is regenerated, replace it on the site.

### 3.3 Payment Processing Certificate (so PayTabs can decrypt)
1. In the PayTabs dashboard, open the Apple Pay section for the profile and get/download the **CSR** (PayTabs creates and keeps the key). *Confirm the exact menu name in PayTabs' current docs.*
2. In Apple Developer → your Merchant ID → **Apple Pay Payment Processing Certificate → Create Certificate** → upload that CSR → download the `.cer`.
3. Upload the `.cer` back to the PayTabs dashboard for that profile.
4. Make sure the profile has Apple Pay enabled.

### 3.4 Merchant Identity Certificate (our server ↔ Apple)
Generate a key and CSR **on your machine** — Apple never sends you the private key.

```bash
# 1. Private key (no password, hence -nodes) + CSR
openssl req -new -newkey rsa:2048 -nodes \
  -keyout merchant-cert.key -out merchant-cert.csr \
  -subj "/CN=merchant.<country>.<yourdomain>/C=<AE|SA>"
```

2. Apple Developer → Merchant ID → **Apple Pay Merchant Identity Certificate → Create Certificate** → upload `merchant-cert.csr` → download `merchant_id.cer`.

```bash
# 3. Convert Apple's DER .cer to PEM
openssl x509 -inform der -in merchant_id.cer -out merchant-cert.crt

# 4. Verify certificate and key are a matching pair — both lines must print the same hash
openssl x509 -noout -modulus -in merchant-cert.crt | openssl md5
openssl rsa  -noout -modulus -in merchant-cert.key | openssl md5
```

If the certificate was created on a Mac and exported from Keychain as `.p12`:

```bash
openssl pkcs12 -in cert.p12 -clcerts -nokeys -out merchant-cert.crt
openssl pkcs12 -in cert.p12 -nocerts -nodes  -out merchant-cert.key
# add -legacy to both commands if you get an "unsupported algorithm" error
```

Rules:
- The key's first line should be `-----BEGIN PRIVATE KEY-----` (no password). If it says `ENCRYPTED`, set `APPLE_PAY_MERCHANT_KEY_PASSPHRASE`.
- Upload both files to the Laravel server at `storage/app/applepay/merchant-cert.crt` and `merchant-cert.key`. Keep them **out of git** and outside the web root. Do not leave a copy inside the repository (an untracked `app/applepay/` folder once held a private key — `git add .` would have committed it).
- The Merchant Identity Certificate expires after about **25 months**. Set a calendar reminder to renew it (repeat 3.4).

---

## 4. PayTabs setup

1. Have a **test profile** and a **live profile**, each with Apple Pay enabled and the Payment Processing certificate installed (3.3).
2. For each profile note the **Profile ID** and the **Server Key** (dashboard → Developers → Key management). Use the **Server Key**, not the Client Key.
3. Know your **region endpoint**: KSA is `https://secure.paytabs.sa`. Credentials only work on their own region's URL.
4. The Direct API call used (`POST {base}/payment/request`, header `authorization: <server key>`):

```json
{
  "profile_id": 12345,
  "tran_type": "sale",
  "tran_class": "ecom",
  "cart_id": "10026105",
  "cart_description": "Marj (2)",
  "cart_currency": "SAR",
  "cart_amount": 500.00,
  "return": "none",
  "customer_details": { "name": "...", "email": "...", "phone": "...", "street1": "...", "city": "...", "state": "...", "country": "SA", "ip": "..." },
  "apple_pay_token": { "paymentData": {…}, "paymentMethod": {…}, "transactionIdentifier": "…" }
}
```

Success = `payment_result.response_status === "A"`. No `callback` URL is sent (the existing hosted-page callback is not idempotent and would create a duplicate payment).

---

## 5. Backend (Laravel) implementation

### 5.1 Files

| File | Purpose |
|---|---|
| `config/applepay.php` (new) | All settings, read from `.env` |
| `app/Services/ApplePayService.php` (new) | Eligibility, merchant validation, charge, payment recording |
| `app/Http/Controllers/Api/ApplePayController.php` (new) | `config`, `validateMerchant`, `charge` endpoints |
| `routes/api.php` (edit) | Three routes inside the existing `customLogs` group |
| `app/Http/Controllers/Api/OrderController.php` (edit) | `storeOrder`: Apple Pay branch |
| `app/Http/Middleware/LogRequestsAndResponses.php` (edit) | Redacts secrets from logs |

### 5.2 `.env`

```env
APPLE_PAY_ENABLED=true
APPLE_PAY_TEST_CUSTOMER_IDS=902,12345        # allowlist, comma separated
APPLE_PAY_MERCHANT_ID=merchant.ksa.ahmedalmaghribi.com
APPLE_PAY_DISPLAY_NAME="Ahmed Al Maghribi"
APPLE_PAY_DOMAIN=ksa.ahmedalmaghribi.com
APPLE_PAY_MERCHANT_KEY_PASSPHRASE=            # empty if the key has no password
# optional: APPLE_PAY_MERCHANT_CERT_PATH / APPLE_PAY_MERCHANT_KEY_PATH (default storage/app/applepay/merchant-cert.crt|.key)
# optional: APPLE_PAY_ORDER_MAX_AGE_MINUTES=30

PAYTABS_PROFILE_ID=<profile id>
PAYTABS_SERVER_KEY=<server key>
PAYTABS_BASE_URL=https://secure.paytabs.sa
```

After every `.env` change run `php artisan config:clear` (or `config:cache` if you cache). Next.js needs **no** new env vars: everything comes from `applePay/config`.

Fixed values in `config/applepay.php` (edit in code, not env): `country_code`, `currency_code`, `supported_networks`, `merchant_capabilities`.

### 5.3 Routes

```
GET  /api/applePay/config            → ApplePayController@config
POST /api/applePay/validateMerchant  → ApplePayController@validateMerchant   (throttle 20/min)
POST /api/applePay/charge            → ApplePayController@charge             (throttle 10/min)
```

### 5.4 `storeOrder` changes (OrderController)

- Right after the 401 check: if `payment_method === 'applepay'` → require a logged-in customer (401), require eligibility (403), then **overwrite `customer_id` from the token** (the body value is never trusted).
- `applepay` is added to the list of online methods that create the order as `TRASH` (unpaid).
- Before the PayTabs-redirect branch: for `applepay` return `{message:"ApplePay order created", order_id, payment_method, total, sub_total, shipping_amount}` and do **not** record a payment.

> If this file is not deployed, `storeOrder` treats `applepay` like cash on delivery, creates the order as paid-looking and returns `"Order created successfully"`, and the widget shows "payment failed". Always deploy all six backend files together.

### 5.5 Security controls (what `ApplePayService` enforces)

- **Allowlist / switch** (`isEligible`): `APPLE_PAY_ENABLED` must be true and the customer id must be in `APPLE_PAY_TEST_CUSTOMER_IDS`. Checked on `config`, `validateMerchant`, `charge` and `storeOrder` — never only on the frontend. Not eligible → **403**; not logged in → **401**.
- **SSRF guard**: the `validation_url` must be `https`, no user/pass/port, and the host must match `apple-pay-gateway*.apple.com` (includes the sandbox `-cert` hosts).
- **Server-side amount**: the charge amount is always `$order->amount` from the database. If the displayed total differs by more than 0.01 → 422, no PayTabs call.
- **Ownership / state / age**: the order must belong to the caller, be unpaid (`TRASH`) and younger than 30 minutes.
- **No double charge**: `Cache::lock` per order, an "already paid" check, and an `applepay:approved:{id}` cache key stored *before* the payment row is written.
- **Result verification**: on status `A`, `cart_id`, `tran_total` and currency must match the order, otherwise the order is left pending and a critical log is written.
- **Unknown outcomes** (timeout, 5xx, `P`/`H` statuses) return `pending`, never `failed`, and the result page keeps checking.
- **Order success**: payment recorded through the existing `CreatePaymentForOrderService` (coupon redemption, SMS, email), then the order moves to `processing`. Declined statuses `D`, `E`, `X`, `V` record a failed payment and leave the order unpaid.
- **No secrets in logs**: `LogRequestsAndResponses` redacts `apple_pay_token`, `password`, the `authorization` / `cookie` headers and the `validateMerchant` response body. The service logs only order code, tran_ref and PayTabs status/code. **Deploy this middleware before any real card is used.**

### 5.6 Login tokens (important when testing)
Each login does `$customer->tokens()->delete()`, so a customer has only one valid token at a time. If the same account logs in on another device or browser, the first device's token is deleted and its Apple Pay calls return **401**. Test with one device per account.

---

## 6. Frontend (Next.js) implementation

### 6.1 Files

| File | Change |
|---|---|
| `components/shopCartandCheckout/ApplePayWidget.jsx` (new) | Eligibility fetch, SDK loading, radio card, Apple Pay button (portalled), full session lifecycle |
| `components/shopCartandCheckout/Checkout.jsx` | `buildOrderPayload()` and `validateBeforePay()` helpers, widget as first payment option, button swap in the form and the mobile sticky bar |
| `messages/en.json`, `messages/ar.json` | `CheckoutPage.applePay*` strings |
| `public/assets/sass/sections/ShopCheckout/_cart-checkout-ui.scss` | `.cc-applepay` and sticky-bar styles |
| `public/.well-known/apple-developer-merchantid-domain-association.txt` | Domain verification file |

### 6.2 How the widget behaves
- Fetches `applePay/config` only if the customer is logged in **and** has a token. The customer id is never read or compared in JavaScript; any failure or `eligible !== true` leaves everything hidden.
- Loads `https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js` through `next/script` only for eligible customers.
- Radio: `<label class="cc-payment-card">` with `value="applepay"`, rendered first in the payment list when eligible and `ApplePaySession.canMakePayments()` is true.
- Button: `<apple-pay-button buttonstyle="black" type="buy" locale="ar-SA|en-US">`. While the radio is selected, the widget portals this button into a slot that replaces *Place order* (inline) and another slot in the mobile sticky bar.
- The click handler is attached to each `apple-pay-button` with a **native `addEventListener('click')`**. Wrapping the button in a `div onClick` made taps unreliable.
- Picks the highest supported Apple Pay JS version (`mada` needs version 5+).
- The `ApplePaySession` must be created **synchronously** inside the tap, so all pre-checks (`validateBeforePay`) are synchronous.
- `busyRef` blocks double taps; `storeOrder` is called once per sheet even if the customer retries a card.
- If Apple Pay becomes unavailable while selected (e.g. logout) Checkout falls back to the card option. Pressing Enter with Apple Pay selected does nothing (`onOrder` returns early).

### 6.3 Shared order payload
`buildOrderPayload(paymentMethod)` in `Checkout.jsx` is the single place that builds the `storeOrder` body. Both *Place order* and Apple Pay use it, so prices, COD fee rules, gift-address fallback and coupon data cannot drift apart. The COD fee is added **only** for `cod`.

> After merging branches, re-check that `buildOrderPayload` and `validateBeforePay` still match the current checkout (price formula, field validation, no references to removed state). A merge once reverted them to an old version.

---

## 7. Testing

### 7.1 Local backend checks (no real charge)
```
php artisan route:list | grep applePay
curl -i https://<api>/api/applePay/config                         # no token → eligible:false / 401 on others
```
Expected: no token → 401; logged-in but not allowlisted → `config` returns `eligible:false`, `validateMerchant`/`charge` return 403; bad URL → 422; unknown order → 404; someone else's order → 403.

### 7.2 Sandbox end to end
1. App Store Connect → **Users and Access → Sandbox → Test Accounts**: create a sandbox tester (needs the developer account).
2. On the iPhone sign in with it (Settings → Developer → Sandbox Apple Account) and add one of Apple's test cards (list: *Apple Pay Sandbox Testing* on developer.apple.com).
3. Use the **PayTabs test profile** in `.env`. Confirm with PayTabs that the test profile accepts Apple sandbox tokens.
4. Log in as an allowlisted customer on the phone only, open checkout in Safari, choose Apple Pay, pay.

### 7.3 Checklist
- [ ] Apple Pay radio is first; selecting it swaps Place order for *Buy with Apple Pay* (inline and sticky bar, EN and AR/RTL).
- [ ] Sheet opens (merchant validation OK), payment succeeds, result page shows success.
- [ ] Order is `processing`, exactly one `completed` payment row, amount equals the order total, payment visible in the PayTabs dashboard, SMS/email sent, coupon redeemed once.
- [ ] Cancel the sheet → no charge, no payment row.
- [ ] Declined card → payment `failed`, order unpaid, clear message.
- [ ] Double tap → one charge only.
- [ ] Non-allowlisted customer sees no Apple Pay; a direct `charge` call returns 403.
- [ ] Regression: COD, PayTabs card redirect, Tabby, Tamara still work.
- [ ] Logs contain no token, no bearer header, no merchant session.

---

## 8. Go-live checklist

1. Deploy **all** backend files together (6 files) and the frontend (including the `.well-known` file).
2. Deploy `LogRequestsAndResponses.php` and confirm new log lines show `[redacted]`.
3. Switch `.env` to the **live** `PAYTABS_PROFILE_ID` + `PAYTABS_SERVER_KEY` (same profile, server key, correct region URL). `php artisan config:clear`.
4. Confirm Apple Pay and the Payment Processing certificate are active on the **live** PayTabs profile.
5. Make a real, small payment with a real card on an allowlisted account; verify order, payment row, PayTabs dashboard; refund it.
6. Widen access gradually: add a few more customer ids. To open to everyone, make an empty allowlist mean "everyone" in `ApplePayService::isEligible()` (keep `APPLE_PAY_ENABLED` as the emergency switch).
7. Delete leftover test orders; make sure no certificate/key copy sits inside the repository.

### Adding or removing a customer
Edit `APPLE_PAY_TEST_CUSTOMER_IDS` (comma separated, e.g. `902,12345`) and run `php artisan config:clear`. The id is `ec_customers.id` (same as `localStorage.user.id`).

### Removing the customer allowlist entirely later
Backend only: delete the `in_array(...)` check in `isEligible()`, remove `$ids` and `allowed_customer_ids` from `config/applepay.php`, remove `APPLE_PAY_TEST_CUSTOMER_IDS` from `.env`, clear config. Keep `APPLE_PAY_ENABLED`, the login requirement and all order checks. The frontend needs no change.

---

## 9. Troubleshooting (real issues we hit)

| Symptom | Cause | Fix |
|---|---|---|
| Log: `merchant identity certificate or key is missing/unreadable` | Files not at `storage/app/applepay/…`, wrong names, wrong permissions, or custom path wrong | Fix location/names; files readable by PHP; clear config cache |
| `validateMerchant` → 401 | Customer's login token was deleted (they logged in elsewhere), or no token | Log in again, only one device per account |
| `validateMerchant` → 403 | Customer not allowlisted, or `APPLE_PAY_ENABLED` false | Check `.env` list, clear config |
| `validateMerchant` → 422 | URL is not an Apple gateway host | Real Apple URLs only |
| `validateMerchant` → 502 | Certificate/key mismatch, wrong passphrase, cert not for this Merchant ID, expired | Re-run the modulus check (3.4); regenerate if needed |
| Widget shows "payment failed" right after Face ID; `storeOrder` replies `Order created successfully` | Old `OrderController.php` on the server (no Apple Pay branch). The order may also look paid via the COD path | Deploy the updated file; check/cancel the test order |
| `ApplePay: PayTabs response … http 401 "Authentication failed. Check authentication header."` | Wrong `PAYTABS_SERVER_KEY` (client key used, key from a different profile, wrong region URL, formatting) | Use the profile's **Server Key**, matching `PAYTABS_PROFILE_ID`, correct `PAYTABS_BASE_URL`; quote values containing `#`/`$`; clear config |
| `charge` → 503 | PayTabs profile/key empty or config cache not refreshed | Set `.env`, clear config |
| `charge` → 422 "total has changed" | Displayed total ≠ server total (COD fee, coupon, stale cart) | Make sure `buildOrderPayload` uses the same price formula as the displayed total |
| Apple Pay option never appears | Not Safari / no card in Wallet / not HTTPS / `config` not eligible / no token | Check `applePay/config` in the Network tab and `ApplePaySession.canMakePayments()` in the console |
| Button does nothing on tap | Click listener not bound natively, or pre-check silently failing | Native listener on `apple-pay-button`; see the message under the button (scrolls into view) |
| Secrets visible in `storage/logs` | Redacting middleware not deployed | Deploy `LogRequestsAndResponses.php`; clear/restrict old logs; rotate exposed tokens |

---

## 10. Adapting this for the UAE

Same architecture and code; the changes are configuration and a few hardcoded values.

### 10.1 Accounts and Apple setup (repeat sections 3 and 4)
- Decide: **new Merchant ID** (e.g. `merchant.uae.<domain>`, recommended — one per country/PayTabs profile) or add the UAE domain to the existing Merchant ID. Apple allows several domains per Merchant ID, but the Payment Processing certificate is tied to the Merchant ID / PayTabs profile, so a separate one per country avoids conflicts. *Confirm the preferred setup with PayTabs.*
- Verify the **UAE storefront domain** with Apple and deploy its own `.well-known/apple-developer-merchantid-domain-association.txt` on that site.
- Create new certificates for the UAE Merchant ID: Payment Processing (via the **UAE PayTabs profile**, 3.3) and a new Merchant Identity cert/key (3.4, use `/C=AE`). Do not reuse the KSA ones.
- Use the **UAE PayTabs profile** (profile id + server key) and the **UAE PayTabs region URL**. UAE merchants are typically on `https://secure.paytabs.com` — confirm in your PayTabs dashboard/docs before use.

### 10.2 Values to change in code/config

| Where | KSA value | UAE value |
|---|---|---|
| `.env` `APPLE_PAY_MERCHANT_ID` | `merchant.ksa.ahmedalmaghribi.com` | your UAE Merchant ID |
| `.env` `APPLE_PAY_DOMAIN` | `ksa.ahmedalmaghribi.com` | UAE storefront domain |
| `.env` `APPLE_PAY_DISPLAY_NAME` | Ahmed Al Maghribi | brand name shown on the sheet |
| `.env` `PAYTABS_*` | KSA profile / key / `secure.paytabs.sa` | UAE profile / key / UAE region URL |
| `config/applepay.php` `country_code` | `SA` | `AE` |
| `config/applepay.php` `currency_code` | `SAR` | `AED` |
| `config/applepay.php` `supported_networks` | `mada`, `visa`, `masterCard` | remove `mada` (KSA-only); keep `visa`, `masterCard`; add `amex` only if PayTabs/your profile supports it |
| `ApplePayService::customerDetails()` | `'country' => 'SA'` (hardcoded) | `'AE'` — better: take it from the order's address |
| `ApplePayService` currency fallbacks | `'SAR'` defaults | use `config('applepay.currency_code')` only; fine once the config is `AED` |
| Frontend | currency symbol, VAT %, locale | Follow the UAE site's own settings; the widget gets country/currency/networks from `applePay/config` |

### 10.3 UAE-specific things to verify
- The UAE checkout's own price formula, VAT (5%) and shipping/COD fee logic — `buildOrderPayload` must match what is displayed, or the amount check (422) will fail.
- Order statuses, payment method names and `CreatePaymentForOrderService` behaviour if the UAE site uses a different Botble setup.
- PayTabs test-profile behaviour for Apple sandbox tokens in the UAE region.
- If the UAE site is a separate codebase, copy the six backend files and the frontend files listed in sections 5.1 and 6.1, then apply the `storeOrder` and middleware edits.

---

## 11. Operations and maintenance

- **Certificate renewal**: Merchant Identity certificate (~25 months). Payment Processing certificate also expires — renew in Apple Developer and re-upload to PayTabs. Put both dates in a calendar.
- **Monitoring**: search logs for `ApplePay:` — `PayTabs response`, `amount mismatch`, `ownership mismatch`, and especially `CRITICAL … does not match order` and `connection error — outcome unknown`.
- **Reconciliation**: orders left `pending` after a timeout need a manual check in the PayTabs dashboard (look up by `cart_id` = order code without `#`).
- **Refunds**: done manually in the PayTabs dashboard (no refund code in this integration).
- **Emergency switch**: `APPLE_PAY_ENABLED=false` + `php artisan config:clear` hides Apple Pay for everyone immediately.

---

## 12. Known limitations / follow-ups

- No automatic reconciliation job for `pending` payments.
- No refund flow in the app.
- The existing PayTabs hosted-page integration still has the live server key **hardcoded in `OrderController`** and an unverified, non-idempotent callback. Move the key to `.env` and rotate it if the source was shared.
- Other `customer*` endpoints trust the `customer_id` in the request body (not part of Apple Pay, but worth fixing).
- Apple Pay is tied to the allowlist until the empty-list change in section 8 is made.
