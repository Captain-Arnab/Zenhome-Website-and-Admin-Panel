# Zen Home Experts - Mobile app ⇄ backend API contract

- **Base URL:** `https://zenhomeexperts.com` (all endpoints below are relative to it, e.g. `https://zenhomeexperts.com/api/login.php`).
- **Source of truth:** derived only by reading the PHP code in this repo (no endpoint was called). Each claim cites `file:line`. "NOT SUPPORTED" = confirmed absent by grep.
- **No secrets:** env values are never shown; only key names. All example values are dummies.
- **Known bugs / gaps:** see `docs/backend_gaps.md` (ranked P1/P2/P3). The per-fragment gap lists were merged there.
- **Updated 2026-10-07 (mobile-app backend fixes):** many gaps were fixed and three endpoints were added. **Section 8 is authoritative** for every endpoint it lists (new request rules, response fields, status codes, examples); older per-endpoint text below is kept for reference and marked "Updated" where it changed. Line numbers in older sections refer to the pre-fix code. Change log: `docs/backend_changes.md`.

## 0. Conventions every app call must handle

| Topic | Contract | Source |
|---|---|---|
| Customer token transport | Send `Authorization: Bearer <token>`. The server also accepts `?token=`, a form `token`, `X-Auth-Token` / `X-Authorization` headers, or a JSON body `token`, checked in that order (query first). Some hosts strip `Authorization`, so the website sends both the header and `?token=`. | `api/auth_helper.php:16-65`, `zen-api.js:114-119` |
| Token format / lifetime | 64 hex characters. **Updated:** login tokens now last `LOGIN_TOKEN_DAYS` (default **30 days**); login returns `expires_in` (seconds) and `expires_at`, and still `expires_in_hours` (720 by default). Changing the password deletes the user's other sessions. | `api/login.php`, `api/auth_helper.php` (`zc_login_token_lifetime`) |
| Invalid / expired token | HTTP 401 `{"statusCode":401,"status":"error","message":"Unauthorized or session expired. Please log in again."}`. Legacy strict mode uses the same message plus `success:false,error`. `payments.php` returns 401 `{"error":"Unauthorized","message":"Please log in again."}`. | `api/auth_helper.php:109-121`, `api/legacy_access.php:140-147`, `api/payments.php:30-34` |
| Refresh token | **NOT SUPPORTED**. On 401, re-login (password, then SMS OTP). | `api/auth_helper.php` (no refresh function) |
| Cookie / session fallback (customer) | **NOT SUPPORTED** (token only). Sessions and cookies exist only for the admin panel. | `api/admin/core/auth.php:21-37` |
| Response envelopes | Mixed (see gaps P2-8). Newer endpoints use `{statusCode,status:"success"\|"error",message,data?,errors?}`. Legacy endpoints use `{success,data\|error}` or `{status,message}`. **App rule: decide on the HTTP status code first, then read `message ?? error`.** **Updated:** every endpoint changed on 2026-10-07 (section 8) now sends `statusCode` equal to the HTTP code and puts its payload in `data`; old top-level fields were kept for the website / old app. A DB outage answers JSON **503**, an uncaught server error answers JSON **500** `{"statusCode":500,"status":"error","message":"Server error. Please try again."}` (no more empty bodies or raw DB errors). | `api/public_helper.php`, `api/runtime.php`, `api/db.php` |
| Validation errors | Newer endpoints: 422 with `errors:{field:"msg"}`. | `api/public_helper.php:28-40` |
| Caching | Public catalog / content endpoints send an ETag and may answer **304 with an empty body**. Send `If-None-Match` and reuse the cached body on 304. | `api/public_helper.php:47-61` |
| CORS | Irrelevant for native apps. **Updated:** all app endpoints, including the legacy ones listed in gaps P2-10, now send CORS headers, answer `OPTIONS` and send `Content-Type: application/json`. | `api/public_helper.php` (`public_cors`) |
| Image URLs | `catalog_*`, banners and settings return **absolute** URLs. **Updated:** they are now built from `APP_URL` (`app_url()`), not the request host. The user object keeps the raw `photo` and adds **`photo_url`** (absolute URL or `null`). Legacy `category.php` `IMAGE` is still raw. | `api/public_helper.php` (`public_site_url`), `api/auth_helper.php` (`zc_photo_url`) |
| Phone numbers | Normalised to 10 digits (`+91` / leading `0` stripped). **Updated:** one rule everywhere (register, login, update_profile, password_reset, contact): `^[6-9]\d{9}$`. | `api/auth_helper.php` (`zc_normalize_phone`) |
| HTTP methods | **Updated:** `login.php` and `logout.php` accept POST only; `update_profile.php` accepts POST/PUT/PATCH; anything else gets **405**. | section 8 |

## Quick answers

- **Login flow:** two steps. (1) `POST api/login.php {phone,password}` validates the password and sends a 6-digit SMS OTP (valid 5 min), returning `status:"otp_sent"` and `data{expires_in,resend_after}`. (2) `POST api/login.php {phone,otp}` returns `token`, `user{id,first_name,last_name,email,phone,address,photo,photo_url,status}`, `expires_in_hours` and the same in `data{token,user,expires_in,expires_at}`. **Updated:** tokens last 30 days; 5 wrong OTPs kill the code; resend needs 60 s (429 + `Retry-After`) and max 3 OTP SMS/hour; SMS failure = 502. Phone + OTP *without* a password, email login, and refresh are **NOT SUPPORTED**. Sections 1 and 8.
- **Register:** `POST api/register.php`. It returns HTTP 201 (`statusCode:201`) with **no token**, and there is no OTP or email verification; the app must call login next. **Updated:** email format, password 6-72, phone `^[6-9]\d{9}$`, 10 sign-ups/hour per IP. Section 8.
- **Logout:** `POST` only. Deletes the token row server-side. **Updated:** the server cart is kept; send `fcm_token` to also unregister this device's push token. Section 8.
- **Forgot password:** `POST api/password_reset.php?action=request {phone}` sends an SMS OTP, then `?action=reset {phone,otp,password}`. Section 1.
- **Payment:** web checkout only. Native PhonePe SDK is **NOT SUPPORTED**. The flow is: `book_appointment.php`, then `POST payments.php {amount (rupees), order_id:"ZC-<unique_booking_id>"}`, which returns `{success,payment_url,transaction_id}`. Open `payment_url`; on return **ignore `?payment=`** and poll `GET paymentConfirmation.php?transactionId=<transaction_id>`. That endpoint asks PhonePe directly, so it is safe to call before the webhook arrives. It returns `state` `COMPLETED`/`SUCCESS` = paid, `FAILED` = failed, anything else = pending. Amount in the request is rupees; the server sends paise to PhonePe. Section 4.
- **Keep `paymentConfirmation.php` for the final status.** `payment_verify.php` is read-only: it does not update the DB, cart or SMS. **Updated:** both now require the customer token and only answer for your own transaction (404 otherwise); both accept a JSON body and add `merchantOrderId` (our `ZC-...` id) and `amount_rupees`. `paymentConfirmation.amount` is still paise. The return URL now carries `?transactionId=`, and `payment_callback.php` verifies with PhonePe before redirecting with `payment=success|failed|pending&txn=`. Section 8.
- **Booking (Updated):** `book_appointment.php` now needs the login token and `items[]` with a `pack_id` on every line (the server prices them; client `amount` is ignored), validates the date/slot, returns **409** if the slot was just taken, and returns a numeric `id`. Section 8.
- **New endpoints (2026-10-07):** `POST api/cancel_booking.php` (customer cancel), `POST|DELETE api/device_token.php` (FCM token register/unregister; nothing is pushed yet), `GET api/app_config.php` (min/latest app version, force update, maintenance, support contacts). Section 8.
- **Not supported:** reschedule, address CRUD (only a single `address` profile field), notification inbox, push delivery, profile photo upload, token refresh, native PhonePe SDK.

## Contents

1. Auth, session, profile
2. Catalog & content
3. Booking, cart, coupons, orders
4. Payment
5. Other: ratings, enquiries, support tickets, partner sign-up, NOT SUPPORTED list
6. Backend gaps → `docs/backend_gaps.md`
7. Legacy endpoint map
8. Changes on 2026-10-07: new endpoints and updated contracts (authoritative)

---

## 1. Auth, session, profile

Common to the legacy auth files (`api/login.php`, `api/register.php`, `api/check_session.php`, `api/logout.php`, `api/fetch_user.php`, `api/update_profile.php`):
- CORS: `Access-Control-Allow-Origin: *`, `Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With`, `Allow-Methods: GET, POST, OPTIONS` (update_profile adds `PUT, PATCH`). OPTIONS answers 200 with empty body before DB is touched (`api/login.php:3-9`, `api/register.php:3-9`, `api/check_session.php:3-9`, `api/logout.php:2-8`, `api/fetch_user.php:2-8`, `api/update_profile.php:2-8`).
- `Content-Type: application/json` (no charset) set before `include 'db.php'` (`api/login.php:14-16`). Bodies are `json_encode` without flags (slashes escaped as `\/`).
- No HTTP method check in any of these six files: GET/PUT/etc. are processed the same as POST.
- Envelope keys: `statusCode` (int), `status` (`"success"` / `"error"`, plus `"otp_sent"` in login step 1), `message`. Payload key varies: `token`+`user` (login), `user_id` (register), `user`+`user_id`+`logged_in` (check_session), `data` (fetch_user), `user` (update_profile).
- `api/password_reset.php` instead uses `api/public_helper.php` (`public_cors`, `public_json`): `Content-Type: application/json; charset=utf-8`, `X-Content-Type-Options: nosniff`, envelope `{statusCode,status,message,data?,errors?}` (`api/public_helper.php:15-40`).
- Phone normalisation (identical in login/register/update_profile/password_reset/sms_sender): strip all non-digits; if 11 digits starting `0` drop it; if 12 digits starting `91` drop it; result must be exactly 10 digits (`api/login.php:30-41`, `api/register.php:36-50`). Only `password_reset` additionally requires `^[6-9]\d{9}$` (`api/password_reset.php:38`). So `"+91 98765 43210"`, `"09876543210"`, `"9876543210"` all become `9876543210`. `"+91-0-9876543210"` (13 digits) is rejected.
- Session/cookie fallback: NONE. Grep for `session_start|$_SESSION|$_COOKIE|setcookie` in `api/` matches only `api/admin/core/auth.php` (admin panel). Customer auth is token-only.
- Refresh token: NOT SUPPORTED. Grep for `refresh_token` in `*.php` returns no matches; `api/auth_helper.php` has no refresh function (`api/auth_helper.php:1-155`).
- Other OTP/forgot files: grep for `send_otp|verify_otp|forgot|refresh_token|sendOtpSms` and file globs `*otp*|*forgot*|*refresh*|*verify*|*reset*` find only: `api/password_reset.php` (customer forgot-password API), `forgot-password.php` (website page, HTML form), `api/payment_verify.php` (payments, not auth), `api/admin/assignTechnician.php:72` (calls `sendOtpSms` for service-completion OTP, admin). No standalone `send_otp.php` / `verify_otp.php` / `refresh_token.php` exist. Login OTP is built into `api/login.php`.

---

#### POST /api/login.php  (step 1 - phone + password -> send OTP)

> **Updated 2026-10-07:** POST only (405). Adds `data{expires_in:300,resend_after:60}`. 60 s resend gap and max 3 OTP SMS per hour per phone (429 + `Retry-After`, `data.retry_after`); SMS gateway failure = 502 (no "otp_sent" unless the gateway accepted it). Section 8.1.
- **Auth:** None
- **Content-Type:** JSON body only, via `json_decode(file_get_contents("php://input"))` as object (`api/login.php:20`). Form posts are NOT parsed.
- **Source:** `api/login.php:110-179`
- **Mode switch:** step 2 (verify) runs when BOTH `phone` and `otp` are `isset` (`api/login.php:29`); otherwise step 1 runs. Sending `otp` together with `password` goes to step 2 (password is ignored). Email login: NOT SUPPORTED (users are looked up only by `phone`, `api/login.php:66`, `api/login.php:129`). Passwordless OTP login (OTP without password): NOT SUPPORTED. An OTP is only issued after a correct password (`api/login.php:139-163`).

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| phone | body (JSON) | string (number also works) | yes | `empty()` check, then normalised to 10 digits (see common notes) | `api/login.php:110,116-127` |
| password | body (JSON) | string | yes | `empty()` check; checked with `password_verify` against `users.password`; not trimmed; no length rule | `api/login.php:110,139` |

**What happens on success:** OTP = `(string) rand(100000, 999999)` (6 digits, never leading zero) (`api/login.php:153`). Expiry = `time() + 300` (5 min, unix int) (`api/login.php:154`). Stored in plaintext in table `otp_verification` (phone, otp, expiry, other columns NULL), upserted per phone with `ON DUPLICATE KEY UPDATE`, so a new request replaces the previous OTP (`api/login.php:156-162`). SMS sent via `sendOtpSms($phone, $otp, 'login')` (`api/login.php:164`).

**Success response** (HTTP 200)
```json
{
  "statusCode": 200,
  "status": "otp_sent",
  "message": "OTP sent to your mobile number. Enter it to complete login."
}
```
(`api/login.php:175-179`). Note `status` is `"otp_sent"`, not `"success"`.

**Error responses**
| HTTP | When | message | Source |
|---|---|---|---|
| 400 | body missing / not JSON / JSON `null` | "Invalid request body." | `api/login.php:22-26` |
| 400 | phone or password empty | "Phone and password are required." | `api/login.php:110-114` |
| 400 | phone not 10 digits after normalising | "Invalid phone number." | `api/login.php:123-127` |
| 401 | phone not registered | "Invalid phone or password." | `api/login.php:133-137` |
| 401 | wrong password | "Invalid phone or password." (same as unregistered) | `api/login.php:139-143` |
| 403 | `users.status` (lowercased, trimmed) is `inactive`, `blocked` or `disabled` | "Your account has been deactivated. Please contact support." | `api/login.php:147-151` |
| 500 | SMS gateway call failed (curl error only) | "Failed to send OTP. Please try again." | `api/login.php:164-173` |
| 500 | DB connection failure | plain text `Connection error: ...` then PHP fatal (non-JSON) | `api/db.php:22-27` |

**Notes**
- Rate limiting: NONE. No resend cooldown, no per-hour cap, no captcha (`api/login.php:153-173`). Legacy status `'0'` is deliberately allowed (`api/login.php:145-146`).

---

#### POST /api/login.php  (step 2 - phone + OTP -> token)

> **Updated 2026-10-07:** token lasts `LOGIN_TOKEN_DAYS` (30 days default); adds `data{token,user,expires_in,expires_at}` and `user.photo_url`. A wrong OTP is 400 with `data.attempts_left`; after 5 wrong tries the code is deleted. "User not found" is now 401. Section 8.1.
- **Auth:** None
- **Content-Type:** JSON body (`api/login.php:20`)
- **Source:** `api/login.php:29-107`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| phone | body (JSON) | string | yes (`isset`) | normalised to 10 digits | `api/login.php:29-41` |
| otp | body (JSON) | string (int also works) | yes (`isset`) | `trim`, compared with strict `!==` to stored OTP string; no format check | `api/login.php:43,60` |

**OTP rules:** looked up in `otp_verification WHERE phone = ? AND first_name IS NULL` (`api/login.php:45-47`). Expired (`time() > expiry`) -> row deleted (`api/login.php:54-59`). Wrong OTP -> row kept, NO attempt counter, NO lockout (`api/login.php:60-64`). On success the OTP row is deleted (`api/login.php:81-82`).

**Session:** `createSession($conn, user_id, 12 * 3600)`: 64-hex token (`bin2hex(random_bytes(32))`) inserted into `user_sessions` with `expires_at` = now + 12 h (`api/login.php:84-85`, `api/auth_helper.php:131-137`). Login overrides the helper's 30-day default. Each login adds a new row; older sessions remain valid (multi-device).

**Success response** (HTTP 200)
```json
{
  "statusCode": 200,
  "status": "success",
  "message": "Login successful.",
  "token": "<64-hex-token>",
  "user": {
    "id": 123,
    "first_name": "Test",
    "last_name": "User",
    "email": "test@example.com",
    "phone": "9876543210",
    "address": "12 Test Street, Hyderabad",
    "photo": null,
    "status": "Active"
  },
  "expires_in_hours": 12
}
```
(`api/login.php:87-106`). `id` is int; all other user fields are raw DB strings or null. No absolute expiry timestamp is returned.

**Error responses**
| HTTP | When | message | Source |
|---|---|---|---|
| 400 | body not JSON | "Invalid request body." | `api/login.php:22-26` |
| 400 | phone not 10 digits | "Invalid phone number." | `api/login.php:37-41` |
| 400 | no OTP row (never sent / already used / deleted on expiry) | "OTP not found or expired. Please request a new OTP." | `api/login.php:49-53` |
| 400 | OTP past expiry | "OTP expired. Please request a new OTP." | `api/login.php:54-59` |
| 400 | OTP mismatch | "Invalid OTP. Please try again." | `api/login.php:60-64` |
| 400 | user row missing for phone | "User not found." | `api/login.php:69-73` |
| 403 | account deactivated (same rule as step 1) | "Your account has been deactivated. Please contact support." | `api/login.php:75-79` |

**Notes**
- `photo` is returned exactly as stored in `users.photo` (no URL building). Stored values can be a bare filename (legacy `uploads/` dir), a relative path, an absolute URL or a `data:` URI. The admin side normalises these with `image_path()` (`api/admin/core/helpers.php:159-173`, `api/admin/core/repo.php:447`), but the customer API does not.

---

#### POST /api/register.php

> **Updated 2026-10-07:** validates email, password 6-72 and phone `^[6-9]\d{9}$` (400 with `errors`); 10 attempts/hour per IP (429); success is HTTP 201 with `statusCode:201`, `user_id` and `data.user_id`. Still no token. Section 8.1.
- **Auth:** None
- **Content-Type:** JSON body only (`api/register.php:18`). Invalid or missing JSON gives the "All fields are required." 400 below.
- **Source:** `api/register.php:18-96`

**Request fields** (all required, each checked with `empty()`, `api/register.php:20-31`)
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| firstname | body | string | yes | trimmed, no length/format rule. Note key is `firstname` (profile uses `first_name`) | `api/register.php:21,33` |
| lastname | body | string | yes | trimmed | `api/register.php:22,34` |
| email | body | string | yes | trimmed, NO format validation | `api/register.php:23,35` |
| phone | body | string | yes | normalised to 10 digits, no `[6-9]` prefix rule | `api/register.php:24,36-50` |
| address | body | string | yes | trimmed, free text (single field) | `api/register.php:25,43` |
| password | body | string | yes | non-empty only, no minimum length; stored with `password_hash(PASSWORD_DEFAULT)` | `api/register.php:26,44` |

There are no optional fields. The account is inserted with `status = 'Active'` (`api/register.php:64-70`).

**Success response** (HTTP **201**, but body `statusCode` is 200)
```json
{
  "statusCode": 200,
  "status": "success",
  "message": "Registration successful. You can now log in with your phone and password.",
  "user_id": 123
}
```
(`api/register.php:90-96`). No token is returned: the app must then call login step 1 and step 2. There is no phone or email verification step at registration. `sms_sender.php` mentions register in a comment (`api/sms_sender.php:4`), but `register.php` does not include it (`api/register.php:16`).

**Error responses**
| HTTP | When | message | Source |
|---|---|---|---|
| 400 | any field empty / body not JSON | "All fields are required." | `api/register.php:20-31` |
| 400 | phone not 10 digits | "Invalid phone number." | `api/register.php:46-50` |
| 400 | email OR phone already in `users` (pre-check or DB unique error 23000) | "User with this email or phone already exists." | `api/register.php:52-62`, `api/register.php:72-78` |
| 500 | other PDO error on insert | "Registration failed. Please try again." | `api/register.php:79-85` |

**Notes:** no rate limit and no captcha.

---

#### GET|POST /api/check_session.php
- **Auth:** Customer Bearer token (required). Any transport accepted by `getBearerToken()` (see auth_helper).
- **Content-Type:** none needed; token can come from query, form, header or JSON body.
- **Source:** `api/check_session.php:18-41`

**Request fields**
| Field | In | Type | Required | Rules | Source |
|---|---|---|---|---|---|
| token | query `?token=` / form / `Authorization` / `X-Auth-Token` / `X-Authorization` / JSON body | string | yes | must match `user_sessions.token` with `expires_at > NOW()` | `api/check_session.php:18-19`, `api/auth_helper.php:16-65,73-83` |

**Success response** (HTTP 200)
```json
{
  "statusCode": 200,
  "status": "success",
  "logged_in": true,
  "user": {"id": 123, "first_name": "Test", "last_name": "User", "email": "test@example.com", "phone": "9876543210", "address": "12 Test Street", "photo": null, "status": "Active"},
  "user_id": 123
}
```
(`api/check_session.php:35-41`, user shape `api/auth_helper.php:89-101`). There is no `message` key on success, and no expiry info is returned.

**Error responses**
| HTTP | When | message | Source |
|---|---|---|---|
| 401 | no token found | "No token sent. For GET use: check_session.php?token=YOUR_TOKEN (use the token from login response)." + `"logged_in": false` | `api/check_session.php:21-33` |
| 401 | token unknown or expired | "Session expired or invalid. Please log in again." + `"logged_in": false` | `api/check_session.php:21-33` |

**Notes:** this check does NOT look at `users.status`. Deactivation works only because the admin deletes the user's sessions (`api/admin/deactivate_user.php:51`, `api/admin/modules/customers.php:203`, `api/admin/modules/customers.php:222`). The session is not extended on use (no sliding expiry).

---

#### GET|POST /api/logout.php

> **Updated 2026-10-07:** POST only (405 for GET). The server cart is **no longer deleted**. Optional `fcm_token` in the body removes that device's push token. Always 200. Section 8.1.
- **Auth:** Customer Bearer token (optional; the response is the same without one)
- **Content-Type:** any (token via `getBearerToken()`)
- **Source:** `api/logout.php:17-32`

**Behaviour:** if a token is present: looks up `user_sessions.user_id` for it, and if found **deletes all rows in `cart` for that user** (`api/logout.php:19-24`). It then deletes the session row for that token server-side via `invalidateSession($conn, $token, null)` (`api/logout.php:25`, `api/auth_helper.php:146-149`). Other devices' sessions are NOT deleted.

**Success response** (HTTP 200, always, even with a missing or invalid token)
```json
{"statusCode": 200, "status": "success", "message": "Logged out successfully."}
```
(`api/logout.php:28-32`)

**Error responses:** none emitted by code (DB exceptions would surface as an empty-body 500, see GAPS).

---

#### GET|POST /api/fetch_user.php

> **Updated 2026-10-07:** the profile (here and in `check_session.php`) also has `photo_url` (absolute URL or `null`).
- **Auth:** Customer Bearer token (required)
- **Content-Type:** any
- **Source:** `api/fetch_user.php:17-33`

**Success response** (HTTP 200)
```json
{
  "statusCode": 200,
  "status": "success",
  "data": {"id": 123, "first_name": "Test", "last_name": "User", "email": "test@example.com", "phone": "9876543210", "address": "12 Test Street", "photo": null, "status": "Active"}
}
```
(`api/fetch_user.php:29-33`). The profile is under `data`. In login, check_session and update_profile it is under `user`.

**Error responses**
| HTTP | When | message | Source |
|---|---|---|---|
| 401 | missing / invalid / expired token | "Unauthorized or session expired. Please log in again." | `api/fetch_user.php:17-26` |

**Notes:** `photo` is raw, as described under login step 2.

---

#### POST|PUT|PATCH /api/update_profile.php  (profile update)

> **Updated 2026-10-07:** other methods get 405. Empty `first_name`, `email` or `phone` is rejected (400); new password must be 6-72 characters; a password change signs out every other session (the current token stays valid). Response has `user` **and** `data` (profile with `photo_url`). Section 8.1.
- **Auth:** Customer Bearer token (required, `requireAuth`) (`api/update_profile.php:17-18`)
- **Content-Type:** JSON body only, as an associative array (`api/update_profile.php:20`). multipart/form-data is NOT parsed.
- **Source:** `api/update_profile.php:20-159`
- Profile GET is done by `api/fetch_user.php` (or `check_session.php`). This file has no read-only mode: an empty body returns 400.

**Request fields** (all optional, but at least one must be present; `null` values are skipped, `api/update_profile.php:63-66`)
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| first_name | body | string | no | trimmed; no length rule; empty string IS saved | `api/update_profile.php:59,67` |
| last_name | body | string | no | same | `api/update_profile.php:59,67` |
| email | body | string | no | `FILTER_VALIDATE_EMAIL` if non-empty; must be unique among other users; empty string is saved | `api/update_profile.php:68-72,107-115` |
| phone | body | string | no | normalised to 10 digits if non-empty; unique among other users; empty string is saved | `api/update_profile.php:73-86,116-131` |
| address | body | string | no | free text, single field | `api/update_profile.php:59` |
| photo | body | string | no | stored verbatim (no upload, no URL/format check). File upload: NOT SUPPORTED (no `$_FILES`/base64 handling in customer API; grep matches only `api/admin/core/upload.php`, `api/admin/addService.php`, `api/partner_registration.php`) | `api/update_profile.php:59` |
| password / new_password | body | string | no | if present, `current_password` is required and is verified with `password_verify`; no min length; hashed | `api/update_profile.php:32-57,91-94` |
| current_password | body | string | with password | see above | `api/update_profile.php:33` |

**Success response** (HTTP 200)
```json
{
  "statusCode": 200,
  "status": "success",
  "message": "Profile updated successfully.",
  "user": {"id": 123, "first_name": "Test", "last_name": "User", "email": "test@example.com", "phone": "9876543210", "address": "12 Test Street", "photo": "uploads/example.jpg", "status": "Active"}
}
```
(`api/update_profile.php:139-159`)

**Error responses**
| HTTP | When | message | Source |
|---|---|---|---|
| 401 | bad token | "Unauthorized or session expired. Please log in again." | `api/auth_helper.php:109-122` |
| 400 | body empty / not a JSON object | "No fields to update. Send at least one of: first_name, ... or password (with current_password)." | `api/update_profile.php:21-29` |
| 400 | password given without current_password | "To change password, send current_password and password (or new_password)." | `api/update_profile.php:35-43` |
| 400 | current_password wrong | "Current password is incorrect." | `api/update_profile.php:47-55` |
| 400 | invalid email | "Invalid email address." | `api/update_profile.php:68-72` |
| 400 | phone not 10 digits | "Invalid phone number." | `api/update_profile.php:81-85` |
| 400 | only unknown/null keys | "No valid fields to update." | `api/update_profile.php:96-104` |
| 400 | email used by another user | "This email is already registered." | `api/update_profile.php:107-114` |
| 400 | phone used by another user | "This phone number is already registered." | `api/update_profile.php:116-130` |

**Notes:** changing the password does not invalidate other sessions (`api/update_profile.php:91-94`). Changing the phone takes effect immediately with no OTP re-verification, and the new number becomes the login identifier.

---

#### POST /api/password_reset.php?action=request
- **Auth:** None
- **Content-Type:** query string merged with the JSON body (if `Content-Type` contains `application/json`), otherwise with `$_POST` form fields (`api/public_helper.php:71-86`). A POST with a JSON content type and an invalid body returns 400 "Invalid JSON body." (`api/public_helper.php:78-80`).
- **Method:** POST only, otherwise 405 "Use POST for this request." (`api/password_reset.php:23`, `api/public_helper.php:63-68`). OPTIONS gives 200 (`api/public_helper.php:20-23`).
- **Source:** `api/password_reset.php:59-95`
- **Default-action logic:** `action` from query or body. If empty: `reset` when `otp` is non-empty, else `request` (`api/password_reset.php:26`). Any other value returns 422 "Unknown action." with `errors.action = "Use request or reset."` (`api/password_reset.php:27-29`).

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| action | query or body | string | no | `request` / `reset` / default logic above | `api/password_reset.php:26` |
| phone | body or query | string | yes | normalised, then must match `^[6-9]\d{9}$` | `api/password_reset.php:31-40` |

**OTP rules:** `random_int(100000, 999999)` (6 digits) (`api/password_reset.php:77`); stored as a `password_hash` in table `password_resets` (one row per user_id) (`api/password_reset.php:78-85`); TTL 600 s (10 min) (`api/password_reset.php:16`); wrong-try limit 5 (`api/password_reset.php:17`); max 3 sends per rolling 1-hour window (`api/password_reset.php:18,65-66,72-74`); minimum 60 s between sends (`api/password_reset.php:19,67-71`). Each new send resets the attempt counter to 0 (`api/password_reset.php:81`). SMS purpose text is `'password reset'` (`api/password_reset.php:88`).

**Success response** (HTTP 200). Same body for unregistered, deactivated and registered numbers (`api/password_reset.php:60-63,94`):
```json
{
  "statusCode": 200,
  "status": "success",
  "message": "If this number is registered, an OTP has been sent to it. It is valid for 10 minutes.",
  "data": {"expires_in": 600}
}
```

**Error responses**
| HTTP | When | message | Source |
|---|---|---|---|
| 405 | not POST | "Use POST for this request." | `api/public_helper.php:63-68` |
| 400 | invalid JSON body | "Invalid JSON body." | `api/public_helper.php:78-80` |
| 422 | bad action | "Unknown action." (`errors.action`) | `api/password_reset.php:27-29` |
| 422 | bad phone | "Enter a valid 10-digit mobile number." (`errors.phone`) | `api/password_reset.php:38-40` |
| 429 | under 60 s since the last send | "Please wait {N} seconds before requesting another OTP." `data.retry_after` = N | `api/password_reset.php:68-71` |
| 429 | 3 sends already in the window | "Too many OTP requests. Please try again in an hour." | `api/password_reset.php:72-74` |
| 502 | SMS failed (row deleted, error logged) | "We could not send the OTP right now. Please try again in a few minutes." | `api/password_reset.php:88-93` |
| 500 | DB connection failed | "Service temporarily unavailable. Please try again." | `api/public_helper.php:114-120` |

---

#### POST /api/password_reset.php?action=reset
- **Auth:** None
- **Content-Type / Method:** same as action=request
- **Source:** `api/password_reset.php:97-133`

**Request fields**
| Field | In | Type | Required | Rules | Source |
|---|---|---|---|---|---|
| action | query or body | string | no | `reset`, or omit and send `otp` | `api/password_reset.php:26` |
| phone | body | string | yes | same as request | `api/password_reset.php:31-40` |
| otp | body | string | yes | `^\d{6}$` | `api/password_reset.php:98,101-103` |
| password | body | string | yes | 6-72 chars (`strlen`); no confirm field on the server | `api/password_reset.php:99,104-108` |

**Success response** (HTTP 200). The password is updated, the reset row is deleted and **all sessions of the user are deleted** in one transaction (`api/password_reset.php:127-131`). The app must then log in again (password + SMS OTP).
```json
{"statusCode": 200, "status": "success", "message": "Your password has been changed. Please sign in with your new password."}
```
(`api/password_reset.php:133`)

**Error responses** (all 422; the message is repeated in `errors.<field>`)
| HTTP | When | message | Source |
|---|---|---|---|
| 422 | otp not 6 digits | "Enter the 6-digit OTP." (`errors.otp`) | `api/password_reset.php:101-103,109-111` |
| 422 | password < 6 / > 72 | "Password must be at least 6 characters." / "Password must be 72 characters or fewer." (`errors.password`) | `api/password_reset.php:104-108` |
| 422 | unregistered, deactivated or no reset row | "OTP not found or expired. Please request a new OTP." | `api/password_reset.php:113-116` |
| 422 | expired (row deleted) | "OTP expired. Please request a new OTP." | `api/password_reset.php:117-120` |
| 422 | 5 wrong tries reached (row deleted) | "Too many wrong tries. Please request a new OTP." | `api/password_reset.php:117-120,124` |
| 422 | wrong OTP | "Incorrect OTP. {N} tries left." / "... 1 try left." | `api/password_reset.php:121-125` |
| 405 / 400 / 500 | as in action=request | | |

**Notes:** times are stored as `Y-m-d H:i:s` in `APP_TIMEZONE` (default `Asia/Kolkata`) (`api/password_reset.php:48-50`).

---

#### (helper) api/auth_helper.php - token transport and validation
- **Token transport order** (`getBearerToken`, `api/auth_helper.php:16-65`):
  1. `$_GET['token']` (`api/auth_helper.php:18-21`)
  2. `$_POST['token']` (form) (`api/auth_helper.php:22-25`)
  3. `Authorization` header via `getallheaders()` or `$_SERVER['HTTP_AUTHORIZATION']`: `Bearer <t>` or the raw value (`api/auth_helper.php:28-46`)
  4. `X-Auth-Token` or `X-Authorization` header, optional `Bearer ` prefix (`api/auth_helper.php:47-55`)
  5. JSON body `"token"` (`api/auth_helper.php:58-62`)
  The query string takes precedence over the header. No `.htaccess` rule forwards `Authorization` (grep for `Authorization` in `.htaccess` finds nothing), which is why the web client also appends `?token=` (`zen-api.js:118-119`). Recommended for the app: send both `Authorization: Bearer <token>` and `X-Auth-Token: <token>`.
- **Validation:** `user_sessions.token = ? AND expires_at > NOW()` JOIN `users` (`api/auth_helper.php:78-83`). Returns `['user_id', 'user' => {id, first_name, last_name, email, phone, address, photo, status}]` (`api/auth_helper.php:89-101`). `users.status` is not checked.
- **Invalid/expired token:** `requireAuth` returns 401 `{"statusCode":401,"status":"error","message":"Unauthorized or session expired. Please log in again."}` and sets the content type (`api/auth_helper.php:109-122`). `check_session.php` and `fetch_user.php` use their own 401 bodies (see above).
- **Token format / lifetime:** 64 lowercase hex chars; default lifetime 2,592,000 s (30 days), but `login.php` passes 12 h (`api/auth_helper.php:131-137`, `api/login.php:84`).
- **Refresh mechanism:** NOT SUPPORTED. Sliding expiry: NOT SUPPORTED.
- **Cookie / PHP-session fallback:** NOT SUPPORTED (no `session_start`/`$_SESSION`/`$_COOKIE` in customer API files).
- **Invalidation:** `invalidateSession($conn, $token, $userId)` deletes by token and/or all of a user's sessions (`api/auth_helper.php:145-154`). Expired rows are never purged.

#### (helper) api/sms_sender.php - behaviour only

> **Updated 2026-10-07:** gateway credentials now come from env keys (`SMS_GATEWAY_*`, `SMS_OTP_TEMPLATE_ID`, `SMS_OTP_ENABLED`), 5 s connect / 10 s total timeout, and the gateway's HTTP code and body are checked; callers get a failure instead of a false "sent". OTPs use `random_int` and are stored hashed.
- `sendOtpSms($mobile, $otp, $purpose = 'login')` (`api/sms_sender.php:10`). Normalises the number the same way, returning `['success'=>false,'error'=>'Invalid mobile number']` unless it is 10 digits (`api/sms_sender.php:12-21`).
- Message template: "Your OTP is {otp} for {purpose}. Please do not share this code with anyone. - ZEN HOME EXPERTS ..." (`api/sms_sender.php:29`), sent through a DLT-registered template. `purpose` values used: `login` (`api/login.php:164`), `password reset` (`api/password_reset.php:88`), `service completion` (`api/admin/assignTechnician.php:72`).
- Uses an HTTP GET to a third-party bulk-SMS gateway with credentials in the query string, via curl (`api/sms_sender.php:32-46`). Credentials are hard-coded in the file (values intentionally not reproduced) (`api/sms_sender.php:6-7,23-27`). No env keys are used.
- Success = no curl error. The gateway response body and HTTP code are NOT checked, and no timeout is set (`api/sms_sender.php:42-57`). Returns `['success'=>true,'response'=>...]` or `['success'=>false,'error'=>'CURL error: ...']`.

#### Customer address CRUD
**NOT SUPPORTED.** Grep in `api/` for `addresses|user_address|customer_address|address_id|saved_address` matches only admin code. `api/admin/modules/customers.php:136-157` states "No saved-address table exists" and derives the list from the profile address plus booking locations (admin-only `customers_addresses`). What the customer app does have:
- A single free-text `users.address`: set at register (required, `api/register.php:25`), returned by login/check_session/fetch_user (`api/auth_helper.php:97`), updated via `update_profile` `address` (`api/update_profile.php:59`).
- Per-booking address: bookings expose `address` from `location` (`api/my_bookings.php:62`). Orders store `shipping_address` JSON (`api/order_details.php:85`, `api/order_history.php:55`, `api/user_orders.php:70`). These are documented in their own fragments.
The app must keep a local address book or reuse the single profile address.

---

---

## 2. Catalog & content

Shared behaviour of the "public_*" endpoints (catalog_categories, catalog_subcategories, catalog_services, banners, cms_pages, home_packages, site_settings):

- **CORS / preflight:** `public_cors()` sends `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With`, `Access-Control-Allow-Methods: GET, OPTIONS`; OPTIONS -> empty 200 and exit; otherwise `Content-Type: application/json; charset=utf-8` + `X-Content-Type-Options: nosniff` (`api/public_helper.php:15-26`).
- **Method:** only GET; anything else -> 405 `{"statusCode":405,"status":"error","message":"Use GET for this request."}` (`api/public_helper.php:63-68`).
- **Input:** `public_input()` = `$_GET` merged with a JSON body (if `Content-Type: application/json`) or `$_POST`; `token` is removed (`api/public_helper.php:71-86`). In practice: send query-string params. Scalars only (`public_str`, `api/public_helper.php:102-105`).
- **Paging:** `page` (int, min 1, default 1), `limit` (int, clamped 1..max) (`api/public_helper.php:89-94`). `pagination` object = `{"page","limit","total_items","total_pages"}` (`api/public_helper.php:96-99`).
- **Envelope:** `{"statusCode":<int>,"status":"success|error","message":"...","data"?:{...},"errors"?:{field:msg}}` (`api/public_helper.php:28-40`).
- **Caching (ETag/304):** `public_json_revalidate()` emits `Cache-Control: no-cache` + `ETag: "<md5 of body>"`; if the request's `If-None-Match` (W/ prefix stripped, comma list allowed) contains that ETag -> **304 with empty body** (`api/public_helper.php:47-61`). App: store ETag + body per URL, send `If-None-Match`, reuse cached body on 304.
- **DB failure:** `public_db()` buffers db.php output; on failure 500 `"Service temporarily unavailable. Please try again."` (`api/public_helper.php:108-126`). Each endpoint catches `PDOException` -> 500 with its own message.
- **Errors displayed?** `api/runtime.php` (loaded via `catalog_helper.php` -> `site_content.php:9`, or db.php) sets `display_errors=0` unless `APP_DEBUG` is truthy (`api/runtime.php:28-31`).
- **Image URLs:** helpers return a **relative** path from the site root, no leading slash (e.g. `images/ac.webp`, `uploads/...`), only if the file exists on disk and matches `^[A-Za-z0-9_\-./]+\.(png|jpe?g|webp|gif)$` (`api/site_content.php:55-62`). Endpoints add an **absolute** `*_url` built by `public_site_url()` = `http(s)://<HTTP_HOST><dir of site root>/` (`api/public_helper.php:129-134`) - NOT `app_url()`/`APP_URL` (`api/runtime.php:44-49`). App rule: use `image_url` when present; otherwise `https://zenhomeexperts.com/` + `image`.
- **Auth:** none of these read a token (no `auth_helper.php` include). Public.

---

#### GET /api/catalog_categories.php
- **Auth:** None
- **Content-Type:** query string (`public_input`, `api/catalog_categories.php:20`)
- **Source:** `api/catalog_categories.php:17-51`, data from `api/catalog_helper.php:59-119`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| category | query | string (id or slug) | no | if set -> single-category mode. Digits = id; else must match `^[a-z0-9]+(?:-[a-z0-9]+)*$`, otherwise 404 | `api/catalog_categories.php:21`, `api/catalog_helper.php:106-119`, `:25` |
| slug | query | string | no | alias of `category` (used if `category` empty) | `api/catalog_categories.php:21` |
| id | query | string/int | no | alias of `category` (used if `category`,`slug` empty) | `api/catalog_categories.php:21` |
| page | query | int | no | default 1 | `api/catalog_categories.php:22` |
| limit | query | int | no | default 50, max 100 | `api/catalog_categories.php:22` |
| search / q | - | - | - | **NOT SUPPORTED** (no such key in catalog_*.php) | - |

**Success response** (200, list mode; ordered by `sort_order, NAME`, `api/catalog_helper.php:101`)
```json
{
  "statusCode": 200, "status": "success", "message": "OK",
  "data": {
    "items": [{
      "id": 3,
      "slug": "ac-service",
      "name": "AC Service",
      "title": "AC Service & Repair",
      "description": "Short description",
      "tagline": "Cool comfort",
      "label": "Popular",
      "icon": "fa-solid fa-snowflake",
      "highlights": ["Trained experts", "30-day warranty"],
      "long_description": ["Paragraph one.", "Paragraph two."],
      "faqs": [{"question": "How long?", "answer": "About an hour."}],
      "why_html": "", "process_html": "", "cta_html": "",
      "image": "images/ac.webp",
      "cover_image": "images/site/ac-cover.webp",
      "url": "https://zenhomeexperts.com/ac-service.php",
      "subcategory_count": 2,
      "service_count": 7,
      "image_url": "https://zenhomeexperts.com/images/ac.webp",
      "cover_image_url": "https://zenhomeexperts.com/images/site/ac-cover.webp"
    }],
    "pagination": {"page": 1, "limit": 50, "total_items": 9, "total_pages": 1}
  }
}
```
Single mode (`?category=ac-service`): `{"statusCode":200,"status":"success","message":"OK","data":{"item":{...same fields...}}}` (`api/catalog_categories.php:40`) - note key `item`, not `items`.

Field types (`api/catalog_helper.php:59-85`): `id` int; `slug` string|null; `name`,`title` (page_title or NAME), `description`,`tagline`,`label` string (may be ""); `icon` string, validated `^fa-(solid|regular|brands) fa-[a-z0-9-]+$` else `fa-solid fa-screwdriver-wrench` (`:71`); `highlights` string[] (max 6, `:72`); `long_description` string[] (blank-line paragraphs, `:305-309`); `faqs` [{question,answer}] max 20 (`:287-302`); `why_html`/`process_html`/`cta_html` string (comment says plain-text override, `:75-78`); `image` string|null = first existing of web_image, IMAGE, cover_image (`:79`); `cover_image` string|null = first existing of cover_image, web_image, IMAGE (`:80`); `url` absolute website page (`<slug>.php` for 9 built-in pages else `service-category.php?slug=`/`?id=`, `:20-23`, `:50-57`; base prepended at `api/catalog_categories.php:29`); `subcategory_count` int (active subcategories); `service_count` int (distinct enabled service names) (`:91-92`); `image_url`/`cover_image_url` absolute|null (`api/catalog_categories.php:27-28`).

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 404 | single mode, category missing/disabled/invalid key | "Category not found." | `api/catalog_categories.php:37-39` |
| 405 | non-GET | "Use GET for this request." | `api/public_helper.php:66` |
| 500 | DB error | "Could not load categories. Please try again." | `api/catalog_categories.php:43-45` |
| 500 | DB connect failure | "Service temporarily unavailable. Please try again." | `api/public_helper.php:119` |

**Notes**
- Only `status = 1` categories (`api/catalog_helper.php:94`, `:100`). ETag/304 caching (both modes).
- The docblock (`api/catalog_categories.php:7-9`) omits `long_description`, `faqs`, `why_html`, `process_html`, `cta_html`, which are emitted.

---

#### GET /api/catalog_subcategories.php
- **Auth:** None
- **Content-Type:** query string
- **Source:** `api/catalog_subcategories.php:12-37`, `api/catalog_helper.php:122-137`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| category | query | string (id or slug) | yes (or `category_id`) | digits = id; else slug pattern; invalid -> 404 | `api/catalog_subcategories.php:16`, `api/catalog_helper.php:106-119` |
| category_id | query | string/int | alt. | alias used when `category` empty | `api/catalog_subcategories.php:16` |
| page / limit | - | - | - | ignored; all rows returned | `api/catalog_subcategories.php:36` |

**Success response** (200)
```json
{
  "statusCode": 200, "status": "success", "message": "OK",
  "data": {
    "category": {"id": 3, "slug": "ac-service", "name": "AC Service"},
    "items": [{"id": 11, "category_id": 3, "slug": "split-ac", "name": "Split AC", "service_count": 4}],
    "pagination": {"page": 1, "limit": 1, "total_items": 1, "total_pages": 1}
  }
}
```
Types: `id`,`category_id`,`service_count` int; `slug` string|null; `name` string (`api/catalog_helper.php:130-136`). Ordered `sort_order, name`; only `status = 1` subcategories (`:124-128`). `pagination` is synthetic: `limit = max(1,count)` (`api/catalog_subcategories.php:36`). No images.

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 422 | no category sent | "Choose a category." errors `{"category":"Send a category id or slug."}` | `api/catalog_subcategories.php:17-19` |
| 404 | category missing/disabled | "Category not found." | `api/catalog_subcategories.php:24-26` |
| 500 | DB error | "Could not load subcategories. Please try again." | `api/catalog_subcategories.php:28-31` |

**Notes:** ETag/304 caching (`api/catalog_subcategories.php:33`).

---

#### GET /api/catalog_services.php

> **Updated 2026-10-07:** `price`, `mrp` and visibility now come from the group's first pack row (`MIN(packId)`), i.e. the same row whose `pack_id` you send to `book_appointment.php`, so the listed price is the charged price and a group whose first pack is disabled is hidden. `?slug=service-<id>` now works. Image URLs are built from `APP_URL`.
- **Auth:** None
- **Content-Type:** query string (arrays also accepted for `ids`/`slugs` via JSON/form, `api/catalog_services.php:72`)
- **Source:** `api/catalog_services.php:18-100`, `api/catalog_helper.php:143-236`

**Request fields** (all filters combine with AND; `ids` and `slugs` combine with each other by OR, `api/catalog_helper.php:172-184`)
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| category | query | id or slug | no | alias `category_id`; unknown/disabled -> 404 | `api/catalog_services.php:29-36` |
| subcategory | query | id or slug | no | alias `subcategory_id`. With `category`: matched against that category's active subs by id or slug. Without `category`: digits only (active sub in active category), a slug -> 422. No match -> 404 | `api/catalog_services.php:38-58` |
| slug | query | string | no | `^[a-z0-9]+(?:-[a-z0-9]+)*$` else 422; no result -> 404 "Service not found." | `api/catalog_services.php:60-66`, `:89-91` |
| ids | query | comma list of ints (or array) | no | each `^\d{1,10}$`, max 100 | `api/catalog_services.php:67-78` |
| slugs | query | comma list (or array) | no | each slug pattern, max 100; `service-<id>` also matches by id | `api/catalog_services.php:67-78`, `api/catalog_helper.php:161-169` |
| page | query | int | no | default 1 | `api/catalog_services.php:22` |
| limit | query | int | no | default 50, max 100 (send `limit=100` for cart refresh with >50 ids) | `api/catalog_services.php:22` |
| search / q | - | - | - | **NOT SUPPORTED** | - |

**Success response** (200; same shape for list, `slug`, `ids`/`slugs` modes - always `items[]`)
```json
{
  "statusCode": 200, "status": "success", "message": "OK",
  "data": {
    "items": [{
      "id": 12,
      "pack_id": 12,
      "slug": "split-ac-deep-clean",
      "name": "Split AC Deep Cleaning",
      "category_id": 3,
      "category": "AC Service",
      "category_slug": "ac-service",
      "subcategory_id": 11,
      "subcategory": "Split AC",
      "price": 599.0,
      "mrp": 799.0,
      "duration": "60 mins",
      "description": "Why choose this pack text",
      "tag": "Bestseller",
      "highlights": ["Jet pump wash", "Gas check"],
      "included": ["Indoor unit cleaning", "Outdoor unit cleaning"],
      "ideal_for": "Homes with split ACs",
      "image": "images/split-ac.webp",
      "url": "https://zenhomeexperts.com/ac-service.php#service-split-ac-deep-clean",
      "image_url": "https://zenhomeexperts.com/images/split-ac.webp"
    }],
    "pagination": {"page": 1, "limit": 50, "total_items": 1, "total_pages": 1}
  }
}
```
Field derivation (`api/catalog_helper.php:185-233`). A "service" is a `saverpacks` GROUP BY `(category_Id, subcategory)` (`:193`):
| Field | Type | Meaning / source |
|---|---|---|
| id, pack_id | int | `MIN(sp.packId)` of the group (`:185`, `:214-215`). Send `pack_id` as `items[{pack_id,quantity}]` to book_appointment / validate_coupon (`api/catalog_services.php:11-12`). |
| slug | string | `MAX(sp.slug)`, or `"service-<id>"` when empty (`:216`) |
| name | string | `sp.subcategory` (group name) (`:185`, `:217`) |
| category_id / category / category_slug | int / string / string\|null | (`:218-220`) |
| subcategory_id / subcategory | int\|null / string\|null | (`:221-222`) |
| price | float | `MAX(sp.price)` in the group (`:187`, `:223`). `<= 0` is rendered on the website as "Price after inspection" (`api/catalog_helper.php:377-382`) |
| mrp | float\|null | `MAX(sp.mrp)`, only if > price, else null (`:224`) |
| discount | - | **NOT SUPPORTED** - compute `mrp - price` / `%` client-side |
| duration | string | `MAX(sp.serviceTime)`, free text, "" if null (`:187`, `:225`); format unknown (see `api/catalog_helper.php:187`) |
| description | string | `MAX(sp.whyChooseThisPack)` (`:188`, `:226`) |
| tag | string | `MAX(sp.web_tag)` (`:189`, `:227`) |
| highlights | string[] | `sp.highlights` lines (max 6), else first 3 of `included` (`:211`) |
| included | string[] | `whatsIncluded` of every row in group (one line per row, CR/LF flattened), max 50 (`:191`, `:210`) |
| ideal_for | string | `MAX(sp.idealFor)` (`:189`, `:230`) |
| image | string\|null | relative path of `MAX(sp.image)` if file exists; bare names looked up under `images/`; no category fallback (`:28-41`, `:231`) |
| url | string | absolute website anchor (`:232`, `api/catalog_services.php:96`) |
| image_url | string\|null | absolute (`api/catalog_services.php:95`) |

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 404 | category unknown/disabled | "Category not found." | `api/catalog_services.php:32-34` |
| 404 | subcategory unknown/not in category | "Subcategory not found." | `api/catalog_services.php:54-56` |
| 404 | `slug` given and no item | "Service not found." | `api/catalog_services.php:89-91` |
| 422 | bad slug / ids / slugs / sub slug without category | "Please check the filters." + `errors.{slug\|ids\|slugs\|subcategory}` e.g. "Invalid service slug.", "Send up to 100 comma-separated service ids.", "Send the category too when filtering by subcategory slug." | `api/catalog_services.php:52`, `:63`, `:74`, `:79-81` |
| 500 | DB error | "Could not load services. Please try again." | `api/catalog_services.php:84-87` |

**Notes**
- Visible only if group `MAX(status) = 1`, category `status = 1`, and subcategory null or active (`api/catalog_helper.php:146`, `:188`, `:194`).
- Order: category sort_order/name, then service sort_order (0 last), then id (`api/catalog_helper.php:204`).
- `ids`/`slugs` mode with no valid values returns empty list (`api/catalog_helper.php:181-183`). The website cart uses `catalog_services.php?limit=100&slugs=...` (`zen-api.js:401`).
- ETag/304 caching (`api/catalog_services.php:100`).

---

#### GET /api/banners.php
- **Auth:** None
- **Content-Type:** query string
- **Source:** `api/banners.php:14-42`, `api/site_content.php:78-110`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| placement | query | string | no | exactly one of `Homepage Hero`, `Homepage Slider`, `Promotional`; default all | `api/banners.php:18-21`, `api/site_content.php:11` |
| page | query | int | no | default 1 | `api/banners.php:22` |
| limit | query | int | no | default 10, max 50 | `api/banners.php:22` |

**Success response** (200)
```json
{
  "statusCode": 200, "status": "success", "message": "OK",
  "data": {
    "items": [{
      "id": 4,
      "title": "Monsoon offer",
      "placement": "Homepage Slider",
      "image": "uploads/banners/monsoon.webp",
      "link": "ac-service.php",
      "order": 1,
      "valid_to": "2026-10-31",
      "image_url": "https://zenhomeexperts.com/uploads/banners/monsoon.webp",
      "link_url": "https://zenhomeexperts.com/ac-service.php"
    }],
    "pagination": {"page": 1, "limit": 10, "total_items": 1, "total_pages": 1}
  }
}
```
Types: `id`,`order` int; `title` string|null (raw column); `placement` string; `image` relative string (never null - rows whose file is missing are skipped, `api/site_content.php:95-98`); `link` string ("" if empty/unsafe; only `http(s)://` or `[A-Za-z0-9_\-./?=&#]+`, `api/site_content.php:65-72`); `valid_to` raw column value or null; `image_url` absolute (`api/banners.php:33`); `link_url` absolute for relative links, as-is for http(s), "" when no link (`api/banners.php:34-38`).

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 422 | unknown placement | "Unknown placement." errors `{"placement":"Use one of: Homepage Hero, Homepage Slider, Promotional."}` | `api/banners.php:19-21` |
| 500 | DB error | "Could not load banners. Please try again." | `api/banners.php:26-29` |

**Notes:** only `status = 1`, `valid_from <= today <= valid_to` (nulls open-ended), non-empty image; today in `APP_TIMEZONE` (default Asia/Kolkata) (`api/site_content.php:38-46`, `:80-81`). Order `placement, sort_order, id` (`:91`). ETag/304 (`api/banners.php:42`).

---

#### GET /api/cms_pages.php?slug=x (single page)
- **Auth:** None
- **Content-Type:** query string
- **Source:** `api/cms_pages.php:19-32`, `api/site_content.php:113-138`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| slug | query | string | yes for this mode | lowercased, `^[a-z0-9-]{1,80}$` | `api/cms_pages.php:19`, `:23-25` |

**Success response** (200)
```json
{
  "statusCode": 200, "status": "success", "message": "OK",
  "data": {
    "slug": "privacy-policy",
    "title": "Privacy Policy",
    "group": "Legal",
    "url": "privacy-policy.php",
    "content": "<h2>Privacy</h2><p>Sanitized HTML...</p>",
    "meta_title": "Privacy Policy",
    "meta_description": "How we use your data",
    "updated": "2026-09-01 10:00:00"
  }
}
```
`content` = HTML sanitized by `sanitize_html()` (`api/site_content.php:121-124`) - render in a WebView/HTML widget; may contain relative `<img src>` (unknown, depends on admin content). `url` raw stored string (form unknown, see `api/site_content.php:132`). `updated` = updated_at or created_at (`:136`).

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 422 | slug fails regex | "Invalid page slug." errors `{"slug":"Use lowercase letters, digits and hyphens."}` | `api/cms_pages.php:23-25` |
| 404 | not found / not Published / empty content | "Page not found or not published." | `api/cms_pages.php:27-29`, `api/site_content.php:125-127` |
| 500 | DB error | "Could not load pages. Please try again." | `api/cms_pages.php:36-39` |

**Notes:** NO ETag; `Cache-Control: public, max-age=300` (`api/cms_pages.php:30`) - edits may be stale up to 5 min in HTTP caches.

#### GET /api/cms_pages.php (list)
- **Auth:** None
- **Source:** `api/cms_pages.php:34-42`, `api/site_content.php:141-154`

**Request fields:** `page` (default 1), `limit` (default 20, max 50) (`api/cms_pages.php:34`).

**Success response** (200)
```json
{"statusCode":200,"status":"success","message":"OK","data":{"items":[{"slug":"about-us","title":"About Us","group":"Pages","url":"about-us.php","updated":"2026-09-01 10:00:00"}],"pagination":{"page":1,"limit":20,"total_items":6,"total_pages":1}}}
```
Only Published with non-empty content; ordered by group (Pages, Legal, Service Content) then title (`api/site_content.php:143-145`). No content in list. `Cache-Control: public, max-age=300`, no ETag (`api/cms_pages.php:41-42`).

---

#### GET /api/home_packages.php
- **Auth:** None
- **Content-Type:** query string
- **Source:** `api/home_packages.php:14-32`, `api/catalog_helper.php:244-284`

**Request fields:** `page` (default 1), `limit` (default 8, max 50) (`api/home_packages.php:17`). Paging done in PHP after filtering (`api/catalog_helper.php:283`).

**Success response** (200)
```json
{
  "statusCode": 200, "status": "success", "message": "OK",
  "data": {
    "items": [{
      "id": 2,
      "title": "AC Care Pack",
      "label": "Save 20%",
      "icon": "fa-solid fa-box-open",
      "description": "Two services a year",
      "highlights": ["2 deep cleans", "Free gas check"],
      "url": "ac-service.php#service-split-ac-deep-clean",
      "button_text": "Book Package",
      "featured": true,
      "category_id": 3,
      "pack_id": 12,
      "price": 599.0,
      "order": 1,
      "url_full": "https://zenhomeexperts.com/ac-service.php#service-split-ac-deep-clean"
    }],
    "pagination": {"page": 1, "limit": 8, "total_items": 1, "total_pages": 1}
  }
}
```
Types (`api/catalog_helper.php:267-281`): `id`,`order` int; `title`,`label`,`description`,`button_text` (default "Book Package") string; `icon` string (validated, default `fa-solid fa-box-open`); `highlights` string[] max 5; `featured` bool; `category_id` int|null; `pack_id` int|null (live service's pack_id); `price` float|null (live `catalog_services` price); `url` relative website path (service url > category url, both relative from the helpers, `:232`, `:81`) or the card's safe link (may be http(s)) or `services.php` (`:266`); `url_full` absolute (`api/home_packages.php:27-29`). **No image field.**

**Error responses:** 500 "Could not load packages. Please try again." (`api/home_packages.php:21-24`).

**Notes:** cards linked to a disabled category/service are hidden (`api/catalog_helper.php:263-265`). ETag/304 (`api/home_packages.php:32`).

---

#### GET /api/site_settings.php

> **Updated 2026-10-07:** the `Admin Alerts` and `App` groups are no longer returned (staff phone numbers were exposed). App version / maintenance values are served by `api/app_config.php` (section 8.3).
- **Auth:** None
- **Content-Type:** no params
- **Source:** `api/site_settings.php:18-28`, `api/site_settings_helper.php:18-54`, `:64-78`, `:184-208`

**Request fields:** none.

**Success response** (200) - `data.settings` contains only keys whose resolved value is non-empty; all values strings except `address_lines` (string[]).
```json
{
  "statusCode": 200, "status": "success", "message": "OK",
  "data": {"settings": {
    "hero_tag": "HOME SERVICES MADE SIMPLE",
    "company_name": "Zen Home Experts",
    "phone": "9876543210", "phone_display": "+91 98765 43210",
    "support_phone": "9876543210", "support_phone_display": "+91 98765 43210",
    "email": "support@example.com",
    "whatsapp": "9876543210", "whatsapp_display": "+91 98765 43210", "whatsapp_url": "https://wa.me/919876543210",
    "address": "Line 1\nLine 2", "address_lines": ["Line 1", "Line 2"],
    "play_store_url": "https://play.google.com/store/apps/details?id=example",
    "logo": "https://zenhomeexperts.com/images/logo.png",
    "admin_alert_enabled": "0"
  }}
}
```
**All possible public keys** (every key of `SITE_SETTING_DEFS`, `api/site_settings_helper.php:18-54`, iterated without filtering at `:187`):
`hero_tag`, `hero_subtitle`, `hero_rating_value`, `hero_bookings_text`, `footer_cta_tag`, `footer_cta_heading`, `footer_cta_text`, `footer_service_area_text`, `trust_item_1`..`trust_item_4` (two lines: title\ntext), `company_name`, `phone`, `support_phone`, `email`, `whatsapp`, `address`, `map_query`, `working_hours`, `facebook_url`, `instagram_url`, `youtube_url`, `play_store_url`, `app_store_url`, `app_qr_link`, `app_qr_image`, `footer_text`, `logo`, `favicon`, `about_image_1`, `about_image_2`, `contact_image`, **`admin_alert_enabled`**, **`admin_alert_mobiles`**.
Derived: `phone_display`, `support_phone_display`, `whatsapp_display` (for type `phone`, `+91 XXXXX XXXXX` when 10 digits, `:197-199`, `:109-112`), `whatsapp_url` (`:201-203`), `address_lines` (`:204-206`).

- Image keys (`app_qr_image`, `logo`, `favicon`, `about_image_1`, `about_image_2`, `contact_image`) are **absolute** (`rtrim(base,'/') . '/' . value`, `:192-195`) and only if the file exists (`:69-71`).
- Keys flagged `fallback` (5th element true) always have a value (default used when empty) (`:72-74`).
- **App version / force update / min_version: NOT SUPPORTED** (no such key in `SITE_SETTING_DEFS`; repo-wide grep for `min_version|force_update|app_version|latest_version` finds nothing).

**Error responses:** 500 "Could not load settings. Please try again." (`api/site_settings.php:23-26`).

**Notes:** ETag/304 (`api/site_settings.php:28`). Stored keys not in DEFS are ignored (`api/site_settings_helper.php:66-67`).

---

#### GET /api/fetch_serviceable_areas.php
- **Auth:** None
- **Content-Type:** query string (`$_GET`)
- **Source:** `api/fetch_serviceable_areas.php:1-37`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| include_disabled | query | any | no | non-empty -> also returns disabled areas / areas of disabled cities | `api/fetch_serviceable_areas.php:17` |

**Success response** (200; no `message`, no `data`; no pagination - all rows)
```json
{"statusCode":200,"status":"success","serviceable_areas":[{"id":5,"name":"YSR Nagar","pincode":"518222","label":"YSR Nagar - 518222","value":"518222"}]}
```
Types: `id` int (`:25`); `name` string; `pincode` raw column (string or null); `label` `"<name> - <pincode>"`; `value` = pincode (`:24-30`). Ordered `sort_order, name` (`:18`).

**Error responses:** none handled. DB failure -> db.php echoes `Connection error: <PDO message>` (`api/db.php:25-27`) and then `$conn->query()` on undefined -> PHP fatal (empty/HTML 500) (`api/fetch_serviceable_areas.php:18`). Query errors uncaught.

**Notes:** CORS + OPTIONS handled (`:2-11`); `Content-Type: application/json` (`:12`); no method check; no caching headers.

---

#### GET /api/category.php (legacy category list)
- **Auth:** None
- **Content-Type:** query string
- **Source:** `api/category.php:1-38`

**Request fields:** `include_disabled` (query, any non-empty -> includes disabled categories) (`api/category.php:11`).

**Success response** (200)
```json
{"status":"success","categories":[{"CATEGORY_ID":3,"NAME":"AC Service","IMAGE":"ac.png"}]}
```
UPPERCASE raw column keys (`api/category.php:12`, `:18-21`); no `statusCode`/`message`/`data`. `CATEGORY_ID` int or numeric string depending on the PDO driver (no casting in code). `IMAGE` = raw DB value, not existence-checked; bare legacy names live under `images/` (`api/catalog_helper.php:27-35`) -> app would have to build `https://zenhomeexperts.com/images/<IMAGE>` (or `/<IMAGE>` if it contains `/`).

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 500 | Exception / JSON encode error | `{"status":"error","message":"Failed to fetch categories.","error":"<exception text>"}` | `api/category.php:29-37` |

**Notes:** `Content-Type: application/json`, `Access-Control-Allow-Origin: *`, no OPTIONS/method handling (`:4-7`); db.php included before headers without buffering (`:2`). No caching. **App should use `catalog_categories.php` instead** (absolute image URLs, slugs, counts, ETag, only active rows).

---

#### GET /api/fetchCategoryDetails.php (legacy single pack)
- **Auth:** None (not wrapped by `legacy_access`)
- **Content-Type:** query string
- **Source:** `api/fetchCategoryDetails.php:1-50`

**Request fields:** `packid` (query, int, required; bound as PARAM_INT) (`api/fetchCategoryDetails.php:14`, `:18-20`).

**Success response** (200) - raw `SELECT *` row of `saverpacks` (all columns, original casing; e.g. `packId`, `category_Id`, `subcategory`, `price`, `mrp`, `serviceTime`, `image`, `status`, `subcategory_id`, `whyChooseThisPack`, `idealFor`, `whatsIncluded`, `slug`, `web_tag`, `highlights`, `sort_order` as referenced in `api/catalog_helper.php:185-191`; full column list unknown):
```json
{"status":"success","data":{"packId":12,"category_Id":3,"subcategory":"Split AC Deep Cleaning","price":"599.00","image":"split-ac.webp","status":1}}
```
**Error responses** (all HTTP 200)
| HTTP | When | Message | Source |
|---|---|---|---|
| 200 | no row | `{"status":"error","message":"User not found."}` | `api/fetchCategoryDetails.php:34-37` |
| 200 | packid missing | "Invalid or missing ID." | `api/fetchCategoryDetails.php:40-43` |
| 200 | DB error | "Database error: <PDO message>" | `api/fetchCategoryDetails.php:45-49` |

**Notes:** no `Content-Type` header (served as text/html), no CORS, disabled packs are returned, image raw relative. **App should use `catalog_services.php?ids=<pack_id>` instead.**

---

#### GET /api/fetchCategory.php (legacy)
- **Auth:** `legacy_access('fetchCategory')` - none by default; with `LEGACY_STRICT_AUTH=true` a valid customer token is required (`api/fetchCategory.php:2-3`, `api/legacy_access.php:130-132`)
- **Content-Type:** query string
- **Source:** `api/fetchCategory.php:1-54`

**Request fields:** `category_id` (query, int, required) (`api/fetchCategory.php:17`, `:21-23`); token optional (see shared token rules).

**Success response** (200) - only the FIRST matching row (`fetch()`, `:29`):
```json
{"status":"success","data":{"subcategory":"Split AC Deep Cleaning"}}
```
**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 200 | no row | `{"status":"error","message":"User not found."}` | `api/fetchCategory.php:37-40` |
| 200 | category_id missing | "Invalid or missing ID." | `api/fetchCategory.php:43-46` |
| 200 | DB error | "Database error: <PDO message>" | `api/fetchCategory.php:48-53` |
| 401 | strict mode, no/invalid token | `{"statusCode":401,"status":"error","success":false,"message":"Unauthorized or session expired. Please log in again.","error":"..."}` | `api/legacy_access.php:130-132`, `:140-147` |

**Notes:** no `Content-Type` (except strict-mode deny), no CORS, ignores status. **App should use `catalog_services.php?category=<id>` (or `catalog_subcategories.php`).**

---

#### POST /api/checkSlot.php (legacy slot availability)

> **Updated 2026-10-07:** GET or POST; date from the JSON body or `?date=`. Missing date 400, invalid date 422. Cancelled bookings no longer block a slot, and today's already-started slots are hidden. Response `{statusCode,status,message,data:[slot strings]}` (`data` unchanged). Section 8.2.
- **Auth:** `legacy_access('checkSlot')` - none by default; `LEGACY_STRICT_AUTH=true` -> valid customer token required, else 401 (`api/checkSlot.php:6-7`, `api/legacy_access.php:106-134`). No user/ownership check.
- **Content-Type:** JSON body via `php://input` only (`api/checkSlot.php:10`). Method not checked (any method with a JSON body works). The website POSTs `{date}` (`zen-pages.js:715`).
- **Source:** `api/checkSlot.php:1-61`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| date | JSON body | string | yes | no format validation; compared as exact string to `service_booking.date`. Website sends `YYYY-MM-DD` (HTML date input, min = today) | `api/checkSlot.php:11-16`, `:20-22`, `zen-pages.js:705`, `:715` |
| token | query/header/body | string | strict mode only | see shared token rules | `api/legacy_access.php:110` |

**Success response** (200)
```json
{"status":"success","message":"Available slots fetched successfully","data":["10:00 AM - 11:00 AM","11:00 AM - 12:00 PM","02:00 PM - 03:00 PM"]}
```
- `data` = string[] of the remaining fixed slots, re-indexed (`api/checkSlot.php:46-51`). Slot format: 12-hour, zero-padded `"hh:mm AM - hh:mm PM"`, 8 hourly slots 10:00 AM -> 06:00 PM (`api/checkSlot.php:27-36`). Send the chosen string unchanged as `service_slot` to book_appointment (`zen-pages.js:821`, stored raw at `api/book_appointment.php:44`, `:112`).
- **How computed:** `SELECT service_slot FROM service_booking WHERE date = :date` (`api/checkSlot.php:20`), then each booked string removes the identical slot (`:39-43`).
- **What makes a slot unavailable:** any `service_booking` row with the same `date` string and identical `service_slot` string - regardless of booking status (cancelled included), category, location or technician; i.e. capacity = 1 booking per slot business-wide. Past times on today are NOT removed; past dates are NOT rejected. A date in a different format than stored (book_appointment stores `date` unvalidated, `api/book_appointment.php:40`) matches nothing -> all 8 slots.
- No `statusCode` key.

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 400 | `date` missing/empty or body not JSON | "Date parameter is missing" | `api/checkSlot.php:12-16` |
| 500 | PDOException | "Database error: <PDO message>" | `api/checkSlot.php:53-56` |
| 401 | strict mode, no/invalid token | "Unauthorized or session expired. Please log in again." | `api/legacy_access.php:130-132` |

**Notes:** `Content-Type: application/json` set first (`:3`); no CORS/OPTIONS; no caching; trailing `?>` + blank line append whitespace after the JSON (`:61-62`). book_appointment does not re-check slot availability (only references to `service_slot` are `api/book_appointment.php:32`, `:44`, `:112`, `:117`).

---

---

## 3. Booking, cart, coupons, orders

Covers: `api/book_appointment.php`, `api/cart.php`, `api/validate_coupon.php` (+ `api/coupon_helper.php`), `api/my_bookings.php`, `api/order_history.php`, legacy `api/order_details.php`, `api/user_orders.php`, `api/serviceBooking.php`, `api/serviceComplete.php`; customer cancel/reschedule; status vocabularies; booking -> payment ID links; website checkout flow (`zen-pages.js`).

---

#### POST /api/book_appointment.php

> **Updated 2026-10-07:** customer token **required** (body `user_id` ignored); `items[]` with `pack_id` on every line required (422 `errors.items`); client `amount` ignored; date must be a real `Y-m-d` today or later and the slot not already started (422); slot taken by another non-cancelled booking = **409**; `payment_method` (`online`/`cash`) is stored; errors use real HTTP codes. Success adds `statusCode`, numeric `id`, `payment_method` and `data{..., booking}`. Section 8.2.

- **Auth:** Legacy guard (`api/book_appointment.php:5-6`). Default (`LEGACY_STRICT_AUTH` false): **no auth**; a Bearer token is optional. If a token is sent *and valid*, its user must equal `user_id`, else 403 (`api/book_appointment.php:55-62`). An **invalid/expired token is silently ignored** (`getUserIdFromRequest()` returns null and the `$auth && ...` check passes, `api/book_appointment.php:56-57`). With `LEGACY_STRICT_AUTH=true`: token required (401) and `user_id` must equal the token's user (403 "You can only access your own account.") (`api/legacy_access.php:130-132`, `api/legacy_access.php:150-158`, called at `api/book_appointment.php:52`).
- **Content-Type:** JSON body via `php://input` only (`api/book_appointment.php:29`). Form posts are not parsed. Response `Content-Type: application/json` (`api/book_appointment.php:9`), **no CORS headers, no OPTIONS handling, no method check** (any method with a valid JSON body works).
- **Source:** `api/book_appointment.php:1-179`

**Request fields**

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| `user_id` | body | int (any scalar accepted) | yes (`isset`) | Not type-checked in default mode; strict mode requires digits equal to token user | `api/book_appointment.php:32`, `api/book_appointment.php:43`, `api/legacy_access.php:155` |
| `category` | body | string | yes (`isset`) | Free text, stored as-is in `service_booking.category`. Website sends distinct categories joined by `", "` | `api/book_appointment.php:32`, `api/book_appointment.php:38`, `zen-pages.js:800-804`, `zen-pages.js:816` |
| `subcategories` | body | string | no (default null) | Free-text **display list of booked services** (the server stores no item ids). Website format: `"Name x2 (₹1,198), Other (₹499)"` - admin parses `Name (₹price)` back out | `api/book_appointment.php:39`, `zen-pages.js:817-819`, `api/admin/core/repo.php:40-76` |
| `date` | body | string | yes (`isset`) | **Not validated** (no format/past-date check). Website sends `YYYY-MM-DD` from `<input type=date>` with `min`=today | `api/book_appointment.php:32`, `api/book_appointment.php:40`, `zen-pages.js:703-705`, `zen-pages.js:820` |
| `service_slot` | body | string | yes (`isset`) | **Not validated / availability not checked.** Website uses a value from `checkSlot.php`, e.g. `"10:00 AM - 11:00 AM"` (8 hourly slots 10 AM-6 PM) | `api/book_appointment.php:32`, `api/book_appointment.php:44`, `zen-pages.js:715`, `zen-pages.js:821`, `api/checkSlot.php:27-36` |
| `location` | body | string | yes (`isset`) | Single free-text address. Website: `"<address>, <area>, <city> - <pincode>"` (admin derives area = 2nd-last comma part, pincode = 6-digit match) | `api/book_appointment.php:32`, `api/book_appointment.php:41`, `zen-pages.js:822`, `api/admin/core/repo.php:105-107`, `api/admin/core/repo.php:149` |
| `landmark` | body | string | no (null) | Free text. Website packs `"<landmark> \| Note: <notes> \| Contact: <name> <phone>"`; admin parses `Contact:` / `Note:` out of it | `api/book_appointment.php:42`, `zen-pages.js:808-812`, `api/admin/core/repo.php:90-97`, `api/admin/core/repo.php:111-116` |
| `items` | body | array of `{pack_id:int, quantity:int}` (`packId` also accepted) | no | When present the subtotal is **recomputed server-side** from `saverpacks.price` (pack `status=1` and category `status=1`). quantity clamped to 1..20; duplicate ids summed; max 50 distinct ids. Lines with `pack_id<=0` are **skipped** (not rejected). If no usable id, >50 ids, or any id unknown/disabled -> 422 | `api/book_appointment.php:65-70`, `api/coupon_helper.php:143-172` |
| `amount` | body | number | no | Fallback subtotal **only when `items` is absent/empty**: numeric, >0, <=1000000, rounded to 2 dp. Otherwise ignored | `api/book_appointment.php:71-73` |
| `coupon_code` | body | string | no | Trimmed; normalised upper-case `^[A-Z0-9]{4,20}$`; rules in coupon_helper (see validate_coupon). Requires an amount (from items or `amount`) | `api/book_appointment.php:76-85`, `api/coupon_helper.php:27-35`, `api/coupon_helper.php:55-101` |
| `payment_method` | body | string | no (default `"cash"`) | Only the exact value `"online"` means online; anything else = pay after service. **Not stored in the DB** (only decides whether the confirmation SMS is sent now) | `api/book_appointment.php:50`, `api/book_appointment.php:112-123`, `api/book_appointment.php:152` |
| `token` | query/body/header | string | no | See shared auth facts | `api/auth_helper.php:16-64` |

Price: **server-computed when every cart line carries a `pack_id`**; client `amount` is trusted otherwise (see GAPS). Discount and payable: `discount = coupon discount (whole rupees)`, `payable = amount - discount` (`api/book_appointment.php:87-88`). Stored: `price = round(payable)` as int, `gross_amount = amount`, `discount_amount`, `coupon_id`, `coupon_code` (`api/book_appointment.php:112-123`). Status written: `"Pending Confirmation"` (`api/book_appointment.php:45`).

**Success response** (HTTP 200, `api/book_appointment.php:162-175`)

```json
{
  "status": "success",
  "message": "Service booking added successfully",
  "unique_booking_id": "123-4567",
  "amount": 1198,
  "discount": 100,
  "amount_payable": 1098,
  "coupon": {
    "code": "SAVE100", "description": "Flat 100 off", "type": "flat", "value": 100,
    "max_discount": null, "min_order": 500, "valid_to": "2026-12-31",
    "amount": 1198, "discount": 100, "final_amount": 1098
  }
}
```

- `amount`, `discount`, `amount_payable` only present when an amount was known (items or `amount` sent) (`api/book_appointment.php:167-171`); `coupon` only when a coupon was applied (`api/book_appointment.php:172-174`). No `statusCode` key, no numeric `id` returned.
- `unique_booking_id` format: `"<user_id>-<service_booking.ID>"` (`api/book_appointment.php:129`).

**Error responses**

| HTTP | When | message | Source |
|---|---|---|---|
| **200** | Body not JSON or missing `category`/`service_slot`/`date`/`location`/`user_id` | `Invalid input` (status:error, but HTTP 200) | `api/book_appointment.php:32-35` |
| 401 | Strict mode, no/invalid token | `Unauthorized or session expired. Please log in again.` (legacy_deny shape: `statusCode,status,success:false,message,error`) | `api/legacy_access.php:130-132`, `api/legacy_access.php:140-147` |
| 403 | Strict mode, `user_id` != token user | `You can only access your own account.` | `api/legacy_access.php:150-158` |
| 403 | Valid token belongs to another user | `You can only book for your own account.` | `api/book_appointment.php:55-62` |
| 422 | `items` sent but unusable/unknown/disabled | `Some services in your cart are no longer available. Please review your cart.` + `errors.items` | `api/book_appointment.php:66-70` |
| 422 | `coupon_code` but no amount | `The booking amount is required to apply a coupon.` + `errors.coupon_code` | `api/book_appointment.php:78-80` |
| 422 | Coupon invalid (any coupon_evaluate reason) | e.g. `This coupon has expired.` + `errors.coupon_code` | `api/book_appointment.php:81-84`, `api/coupon_helper.php:55-101` |
| 409 | Coupon usage limit hit at redeem time (race) | `This coupon has reached its usage limit.` | `api/book_appointment.php:99-102` |
| 409 | Per-customer limit hit inside the transaction | `You have already used this coupon.` | `api/book_appointment.php:103-108` |
| 500 | PDOException on insert | `Error: could not save the booking. Please try again.` | `api/book_appointment.php:137-145` |
| (text) | DB connect failure | plain text `Connection error: <PDO message>` then PHP fatal (non-JSON) | `api/db.php:22-27`, `api/book_appointment.php:2` |

Coupon error shape: `{"status":"error","message":"...","errors":{"coupon_code":"..."}}` (`api/book_appointment.php:21-27`).

**Notes**
- SMS: the code does **not** call `notify_admin_new_booking` directly. It calls `notify_booking_confirmed($id)` (customer `booking_confirmation` template + admin `admin_new_booking` alert, once per booking via atomic `confirm_sms_sent_at` claim) when `payment_method != "online"` OR payable is null OR payable <= 0 (`api/book_appointment.php:152-159`, `api/admin/core/notify.php:349-371`). For online bookings SMS is sent on payment success by `paymentConfirmation.php` / webhook via `notify_booking_confirmed_for_transaction("ZC-...")` (`api/paymentConfirmation.php:69-75`, `api/admin/core/notify.php:380-393`). SMS failure never fails the booking (`api/book_appointment.php:153-158`). Note: the SMS's "payment mode" text comes from `booking_find()` -> `payment_method` = `Online` only if a `ZC-` transaction exists, else `Cash` (`api/admin/core/repo.php:137`, `api/admin/core/notify.php:360`).
- Coupon consumption is inside the same DB transaction as the insert (`coupon_redeem` atomic `UPDATE ... used_count+1`) (`api/book_appointment.php:93-108`, `api/coupon_helper.php:125-130`). Cancelled bookings don't count towards per-user limit (`api/coupon_helper.php:85-90`).
- The cart is **not** cleared server-side by this endpoint; the website calls `DELETE cart.php` (cash path) or the payment success path deletes it (`zen-pages.js:845`, `zen-api.js:354-360`, `api/paymentConfirmation.php:67`, `api/phonepe_webhook_handler.php:61-62`).
- No idempotency key: a retried request creates a second booking.

---

#### GET /api/cart.php

- **Auth:** Customer Bearer token **required** (`api/cart.php:17`) -> 401 standard requireAuth body.
- **Content-Type:** none parsed (GET). CORS `*`, OPTIONS -> 200 (`api/cart.php:2-12`).
- **Source:** `api/cart.php:71-89`

**Request fields:** only `token` (query/header).

**Success response** (200)

```json
{
  "statusCode": 200,
  "status": "success",
  "items": [
    {
      "id": "foam-jet-ac-service",
      "pack_id": 12,
      "name": "Foam Jet AC Service",
      "price": 599,
      "image": "images/services/ac.webp",
      "url": "ac-service.php#service-foam-jet-ac-service",
      "category": "AC Service",
      "quantity": 1
    }
  ]
}
```

- `items` is whatever JSON array was last POSTed (no server schema). The shape above is what the website stores (`common.js:746-766`, refreshed from catalog in `zen-api.js:421-430`). No `message` key on GET. No `data` wrapper (items is top-level).
- Missing local `image`/`url` files are replaced from the live catalog (matched by `pack_id` or slug `id`), else `image: ""`, `url: "services.php"`; stored cart not modified (`api/cart.php:28-68`). External `http(s)://` values are left untouched (`api/cart.php:32`).
- **Image/URL form: relative site paths** (as stored, or `site_image_path()` relative path from the catalog) (`api/cart.php:60-63`, `api/catalog_helper.php:28-41`, `api/site_content.php:55-62`). App must prefix the site base URL.

#### POST /api/cart.php

> **Updated 2026-10-07:** `items` must be a list of objects (max 100 entries, 60 KB) or the call is **422** and the saved cart is untouched; `items: []` still clears the cart.

- **Auth:** Bearer required. **Content-Type:** JSON body via `php://input` (`api/cart.php:93`).
- **Source:** `api/cart.php:92-108`

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| `items` | body | array | effectively yes | **Replaces** the whole cart. If missing / not an array / body not JSON -> stored as `[]` (silently clears cart). No validation of item shape, count or size | `api/cart.php:94-100` |

**Success** (200): `{"statusCode":200,"status":"success","message":"Cart updated.","items":[...echo of input...]}` (`api/cart.php:101-106`). Upsert into `cart(user_id, items)` (`api/cart.php:96-100`).

#### DELETE /api/cart.php

- **Auth:** Bearer required. No body. **Source:** `api/cart.php:111-121`
- **Success** (200): `{"statusCode":200,"status":"success","message":"Cart cleared.","items":[]}`.

**Error responses (all cart modes)**

| HTTP | When | message | Source |
|---|---|---|---|
| 401 | No/invalid token | `Unauthorized or session expired. Please log in again.` | `api/auth_helper.php:109-122` |
| 405 | Method not GET/POST/DELETE/OPTIONS | `Method not allowed.` | `api/cart.php:123-124` |
| (text) | DB connect failure | `Connection error: ...` non-JSON | `api/db.php:22-27` |

**Notes:** Cart is also deleted server-side on successful online payment (`api/paymentConfirmation.php:67`, `api/phonepe_webhook_handler.php:61-62`). The website merges server cart into local by `id` after login (`zen-api.js:364-386`). For booking, the app must send `pack_id` for **every** line (website `packItems()` sends `items` only when all lines have `pack_id>0`) (`zen-pages.js:559-564`).

---

#### POST /api/validate_coupon.php

- **Auth:** Optional. If any token is sent it **must** be valid (else 401). Without token, a body/query `user_id` (digits) is accepted for the per-user check (`api/validate_coupon.php:40-49`).
- **Content-Type:** `public_input()`: query string merged with JSON body (when `Content-Type: application/json`) or `$_POST` form (`api/public_helper.php:71-86`). Invalid JSON with JSON content-type -> 400 `Invalid JSON body.` CORS `*`; OPTIONS 200 (`api/validate_coupon.php:18`).
- **Source:** `api/validate_coupon.php:1-61`. Preview only: coupon is **not** consumed (`api/validate_coupon.php:3-4`).

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| `coupon_code` (alias `code`) | body/query | string | yes | Trimmed; then normalised `^[A-Z0-9]{4,20}$` (case-insensitive input) | `api/validate_coupon.php:23-26`, `api/coupon_helper.php:27-35` |
| `items` | body | `[{pack_id, quantity}]` | one of items/amount | Server-priced from saverpacks (same rules as booking) | `api/validate_coupon.php:28-31`, `api/coupon_helper.php:143-172` |
| `amount` | body/query | number | if no items | numeric, >0, <=1000000 | `api/validate_coupon.php:32-38` |
| `user_id` | body/query | digits | no | Used only when no token sent | `api/validate_coupon.php:47-49` |

Coupon rules (`api/coupon_helper.php:55-101`): exists and `status=1`; `valid_from <= today <= valid_to` (today in `APP_TIMEZONE`, default Asia/Kolkata, `api/coupon_helper.php:16-24`); `amount >= min_order`; `used_count < usage_limit` (null/0 = unlimited); per-customer bookings with this coupon (non-cancelled) `< max(1, per_user_limit)` when user known. Discount: percentage -> `amount*value/100` capped by `max_discount` (if >0); flat -> `value`; rounded to whole rupees and never above amount (`api/coupon_helper.php:37-48`).

**Success response** (200, `api/validate_coupon.php:61`, `api/coupon_helper.php:104-119`)

```json
{
  "statusCode": 200,
  "status": "success",
  "message": "Coupon applied. You save Rs 120.",
  "data": {
    "code": "SAVE10",
    "description": "10% off up to Rs 150",
    "type": "percentage",
    "value": 10,
    "max_discount": 150,
    "min_order": 500,
    "valid_to": "2026-12-31",
    "amount": 1198,
    "discount": 120,
    "final_amount": 1078
  }
}
```

`type` is the raw `coupons.type` (`percentage` or anything else = flat; `api/coupon_helper.php:39`).

**Error responses**

| HTTP | When | message | Source |
|---|---|---|---|
| 400 | JSON content-type but unparsable body | `Invalid JSON body.` | `api/public_helper.php:78-80` |
| 405 | Not POST | `Use POST for this request.` | `api/public_helper.php:63-68` |
| 422 | No code | `Enter a coupon code.` (`errors.coupon_code`) | `api/validate_coupon.php:24-26` |
| 422 | Bad items | `Some services in your cart are no longer available. Please review your cart.` (`errors.items`) | `api/validate_coupon.php:29-31` |
| 422 | No/invalid amount | `A valid booking amount is required.` (`errors.amount`: `Send the booking subtotal.`) | `api/validate_coupon.php:33-36` |
| 401 | Token sent but invalid | `Unauthorized or session expired. Please log in again.` | `api/validate_coupon.php:41-45` |
| 422 | Coupon rule failed (`errors.coupon_code` = same text) | `Enter a valid coupon code.` / `This coupon code is not valid.` / `This coupon is valid from DD Mon YYYY.` / `This coupon has expired.` / `Add services worth Rs N or more to use this coupon.` / `This coupon has reached its usage limit.` / `You have already used this coupon.` | `api/validate_coupon.php:58-60`, `api/coupon_helper.php:58-91` |
| 500 | DB error | `Could not check the coupon. Please try again.` | `api/validate_coupon.php:51-56`, `api/public_helper.php:117-120` |

---

#### GET /api/my_bookings.php

> **Updated 2026-10-07:** every item also has `refund_status`, `can_cancel`, `can_review`, `review` (`{rating,feedback,status}` or `null`) and `cancelled_at`; `payment_method` reflects the stored method. Section 8.2.

- **Auth:** Customer Bearer token **required** (`api/my_bookings.php:31`).
- **Content-Type:** `public_input()` (query merged with JSON/form) (`api/my_bookings.php:29`). GET only (405 otherwise) (`api/my_bookings.php:26-27`). CORS `*`.
- **Source:** `api/my_bookings.php:1-114`

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| `id` | query | string | no | `^\d+(-\d+)?$`; matches `unique_booking_id` (preferred) or numeric `ID`; always scoped to own user | `api/my_bookings.php:79-91` |
| `page` | query | int | no | default 1, min 1 | `api/my_bookings.php:93`, `api/public_helper.php:89-94` |
| `limit` | query | int | no | default 10, 1..50 | `api/my_bookings.php:93` |
| `status` | query | string | no | `active` (canonical not in Completed/Cancelled) or `past` (Completed/Cancelled), case-insensitive | `api/my_bookings.php:96-102` |

Ordering: `created_at DESC, ID DESC` (`api/my_bookings.php:108`).

**Success - list** (200, `api/my_bookings.php:111-114`)

```json
{
  "statusCode": 200,
  "status": "success",
  "message": "Your bookings.",
  "data": {
    "items": [
      {
        "id": 4567,
        "booking_id": "123-4567",
        "category": "AC Service",
        "services": "Foam Jet AC Service (₹599)",
        "date": "2026-10-10",
        "slot": "10:00 AM - 11:00 AM",
        "address": "12 Test Street, Test Area, Kolkata - 700001",
        "landmark": "Near park | Contact: Test User 9876543210",
        "status": "New",
        "status_label": "Booking Received",
        "amount": 499,
        "gross_amount": 599,
        "discount": 100,
        "coupon_code": "SAVE100",
        "payment_method": "Online",
        "payment_status": "Paid",
        "technician": null,
        "cancel_reason": null,
        "created_at": "2026-10-07 14:12:00",
        "completed_at": null
      }
    ],
    "pagination": { "page": 1, "limit": 10, "total_items": 1, "total_pages": 1 }
  }
}
```

**Success - single** (`?id=`): `{"statusCode":200,"status":"success","message":"Booking details.","data":{...one item...}}` (`api/my_bookings.php:90`).

Item field derivation (`api/my_bookings.php:41-77`):
- `amount` = `service_booking.price` (payable, int) or 0; `gross_amount` = stored gross or `amount+discount`.
- `status` = canonical (New/Pending/Assigned/Ongoing/Completed/Cancelled), `status_label` from `MY_BOOKING_LABELS` (`api/my_bookings.php:17-24`).
- `payment_status`: `Refunded` if txn `refund_status='Processed'` or booking `payment_status='refunded'`; else if a `ZC-` transaction exists: `success`->`Paid`, `failed`->`Failed`, other->`Pending`; else booking `payment_status='paid'`->`Paid`, else `Pending` (`api/my_bookings.php:44-50`).
- `payment_method`: `Online` if booking column = online **or** a `ZC-` transaction exists, else `Pay after service` (`api/my_bookings.php:70`).
- `technician` `{name, phone}` only when status Assigned/Ongoing and name set (`api/my_bookings.php:51`, `api/my_bookings.php:72`). Only the legacy `technician_name/phone` columns are read (not `professional_id`).
- `cancel_reason` only when Cancelled. `services` is the raw free-text `subcategories` (app/legacy rows may be JSON like `[["Gas Filling"]]`) (`api/admin/core/repo.php:41-43`).
- No images.

**Error responses**

| HTTP | When | message | Source |
|---|---|---|---|
| 401 | No/invalid token | `Unauthorized or session expired. Please log in again.` | `api/auth_helper.php:109-122` |
| 405 | Not GET | `Use GET for this request.` | `api/public_helper.php:63-68` |
| 422 | Bad `id` | `Invalid booking id.` | `api/my_bookings.php:81-83` |
| 404 | `id` not found / other user's | `Booking not found.` | `api/my_bookings.php:86-89` |
| 422 | Bad `status` | `Invalid status filter.` (`errors.status`: `Use active or past.`) | `api/my_bookings.php:98-100` |
| 500 | DB connect failed | `Service temporarily unavailable. Please try again.` | `api/public_helper.php:117-120` |

**Notes:** This is the endpoint the app should use for booking history/detail (website uses it, `zen-pages.js:899-905`).

---

#### GET /api/order_history.php

- **Auth:** Customer Bearer token **required** (`api/order_history.php:17`).
- **Content-Type:** `$_GET` only. CORS `*`, OPTIONS 200 (`api/order_history.php:2-12`). No method check (any method is treated as GET).
- **Source:** `api/order_history.php:1-71`

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| `page` | query | int | no | default 1, min 1 | `api/order_history.php:20` |
| `limit` | query | int | no | default 20, 1..50 | `api/order_history.php:21` |

Lists **payment transactions** (`transactions` LEFT JOIN `orders`) for the user, newest first (`api/order_history.php:24-38`).

**Success** (200, `api/order_history.php:59-71`)

```json
{
  "statusCode": 200,
  "status": "success",
  "data": {
    "orders": [
      {
        "order_id": "ZC-123-4567",
        "amount": 499,
        "status": "success",
        "payment_mode": "PHONEPE",
        "order_date": "2026-10-07 14:15:00",
        "items": [],
        "shipping_address": null
      }
    ],
    "pagination": { "page": 1, "limit": 20, "total_items": 1, "total_pages": 1 }
  }
}
```

- `status` is raw `transactions.status` (`pending|success|failed`, legacy may also have `refunded`) (`api/order_history.php:51`, `api/save_transaction.php:43-45`). No `message` key.
- `items`/`shipping_address` come from the `orders` table, which **no code in the repo writes** (grep `INTO orders` = no matches), so they are `[]`/`null` in practice (`api/order_history.php:54-55`).

**Errors:** 401 (requireAuth); DB connect failure -> non-JSON text (`api/db.php:22-27`).

---

#### GET /api/order_details.php (legacy)

- **Auth:** Legacy guard. Default: **none** - any caller can read any order by id. Strict: token required, order must belong to the user (404 `Order not found.` otherwise) (`api/order_details.php:3-5`, `api/legacy_access.php:166-180`).
- **Content-Type:** `$_GET`. Header `Content-Type: application/json` (`api/order_details.php:2`). No CORS, no method check. Uses its own mysqli connection (`api/order_details.php:13-23`).
- **Source:** `api/order_details.php:1-108`

| Field | In | Type | Required | Rules | Source |
|---|---|---|---|---|---|
| `order_id` | query | string | yes | `^TXN_[A-Z0-9]{10}$` only (legacy `payementInitiate.php` ids). **`ZC-...` website ids are rejected** | `api/order_details.php:27-30`, `api/payementInitiate.php:30` |

**Success** (200, `api/order_details.php:69-98`)

```json
{
  "success": true,
  "data": {
    "order_id": "TXN_ABCDEF1234",
    "user_id": "123",
    "amount": 499,
    "status": "success",
    "payment_method": "PHONEPE",
    "order_date": "2026-10-07 14:15:00",
    "service": { "type": "Standard Cleaning", "schedule": { "date": null, "cleaner_id": null, "status": "pending" } },
    "items": [],
    "shipping_address": null,
    "payment_details": null
  }
}
```

`service.type` = first `orders.items[].type|name|category` else `"Standard Cleaning"` (`api/order_details.php:64-66`). `payment_details` = raw decoded `transactions.phonepe_data`, only `cardNumber` masked (`api/order_details.php:86-93`). Joins `service_schedule` by `order_id` (`api/order_details.php:49`).

**Errors:** 400 `{"success":false,"error":"Valid order ID is required (format: TXN_XXXXXXXXXX)"}` / `"Order not found"` (note: not-found is **400**, not 404) (`api/order_details.php:28-30`, `api/order_details.php:57-59`, `api/order_details.php:100-105`); 500 `{"error":"Database connection failed"}` (`api/order_details.php:20-23`); strict-mode 401/404 legacy_deny shape.

---

#### GET /api/user_orders.php (legacy)

- **Auth:** Legacy guard. Default: **none** - `?user_id=` lists **any** user's orders. Strict: token required and `user_id` must equal token user (403) (`api/user_orders.php:3-5`).
- **Content-Type:** `$_GET`. JSON header only; no CORS; no method check; own mysqli connection (`api/user_orders.php:13-23`).
- **Source:** `api/user_orders.php:1-107`

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| `user_id` | query | string | yes | non-empty | `api/user_orders.php:27-30` |
| `page` | query | int | no | `max(1, page)` - **not cast** | `api/user_orders.php:33` |
| `limit` | query | int | no | `min(50, limit)` default 10 - **no lower bound** | `api/user_orders.php:34` |

**Success** (200, `api/user_orders.php:85-96`)

```json
{
  "success": true,
  "data": {
    "user_id": "123",
    "orders": [
      { "order_id": "ZC-123-4567", "amount": 499, "status": "success", "payment_method": "PHONEPE",
        "date": "2026-10-07 14:15:00", "items": [], "shipping": null }
    ],
    "pagination": { "page": 1, "limit": 10, "total_items": 1, "total_pages": 1 }
  }
}
```

Same data as `order_history.php` but different keys (`payment_method`/`date`/`shipping` vs `payment_mode`/`order_date`/`shipping_address`) and `total_pages` is a float-typed `ceil()` (`api/user_orders.php:93`).

**Errors:** 400 `{"success":false,"error":"User ID parameter is required"}` (`api/user_orders.php:28-30`, `api/user_orders.php:98-104`); 500 `{"error":"Database connection failed"}`; `limit=0` -> `DivisionByZeroError` (uncaught Error -> empty 500) (`api/user_orders.php:93`); non-numeric `page` -> TypeError (uncaught) (`api/user_orders.php:33-35`).

---

#### POST /api/serviceBooking.php (legacy)

- **What it does:** old app booking insert. Writes a `service_booking` row with **client-supplied** `status`, `price`, `technician_name`, `created_at`; sets `unique_booking_id = "SERVICY-" + zero-padded ID (5)` (`api/serviceBooking.php:54-77`). No coupon, no catalog pricing, no SMS, no slot check.
- **Auth:** Legacy guard; default none; strict: token + `user_id` match (`api/serviceBooking.php:3-6`).
- **Content-Type:** JSON via `php://input` (`api/serviceBooking.php:12`). No CORS, no method check.
- **Source:** `api/serviceBooking.php:1-90`

| Field | In | Type | Required | Rules | Source |
|---|---|---|---|---|---|
| `category`, `date`, `location`, `user_id`, `service_slot`, `created_at`, `status`, `price`, `technician_name` | body | scalar | yes (`isset`) | none | `api/serviceBooking.php:15-21` |
| `subcategories` | body | array of `{subcategory:string}` | yes | each needs `subcategory`; stored as JSON `[["A"],["B"]]` | `api/serviceBooking.php:24-30`, `api/serviceBooking.php:45-51` |
| `landmark` | body | string | no | | `api/serviceBooking.php:36` |

**Success** (**201**): `{"status":"success","message":"Service booking added successfully","unique_booking_id":"SERVICY-04567","subcategories":[["Gas Filling"]]}` (`api/serviceBooking.php:80-86`).

**Errors:** 400 `Missing required fields` / `Invalid subcategory format`; 500 `Database error: <raw PDO message>` (`api/serviceBooking.php:18-19`, `api/serviceBooking.php:26-27`, `api/serviceBooking.php:62-65`).

**Notes:** `SERVICY-xxxxx` ids do not match the `ZC-(\d+-\d+)` pattern, so these bookings cannot use the website online-payment link (`api/payments.php:40`). **App must not use this; use book_appointment.php.**

---

#### POST /api/serviceComplete.php (legacy)

- **What it does:** marks a booking completed by verifying the 6-digit completion OTP (generated and SMS'd **to the technician** when assigned, stored as `password_hash` in `service_booking.otp`) (`api/admin/assignTechnician.php:60-72`, `api/admin/assignTechnician.php:151-155`). On match sets `status='Service Complete'` (canonical Completed) (`api/serviceComplete.php:40-47`). Does **not** set `completed_at`.
- **Auth:** Legacy guard; default none; strict: token + `user_id` match + booking owned (404) (`api/serviceComplete.php:5`, `api/serviceComplete.php:12-13`).
- **Content-Type:** JSON via `php://input` (`api/serviceComplete.php:10`). **No `Content-Type` response header is set** (served as text/html). No CORS, no method check.
- **Source:** `api/serviceComplete.php:1-65`

| Field | In | Type | Required | Source |
|---|---|---|---|---|
| `otp` | body | string (6 digits) | yes | `api/serviceComplete.php:15` |
| `user_id` | body | int | yes | `api/serviceComplete.php:16` |
| `id` | body | int (`service_booking.ID`, not unique_booking_id) | yes | `api/serviceComplete.php:17` |

**Success** (200): `{"status":"success","message":"Service Completed, please rate your experience."}` (`api/serviceComplete.php:46-47`).

**Errors:** 400 `Missing or incorrect parameters (otp, user_id, service_id).`; 401 `Incorrect OTP.`; 404 `Booking not found or OTP is invalid for this user and service.`; 500 `Database error: <raw message>` (`api/serviceComplete.php:21-25`, `api/serviceComplete.php:50-55`, `api/serviceComplete.php:58-61`). No attempt limit.

---

#### Customer cancel / reschedule

> **Updated 2026-10-07:** customer cancel is now `POST api/cancel_booking.php` (section 8.4). Reschedule is still not supported.

- **Customer cancel: NOT SUPPORTED.** No `api/*.php` customer endpoint cancels or reschedules (grep `cancel|reschedul` over `api/*.php` only hits read/coupon logic: `api/my_bookings.php:36`, `api/book_appointment.php:103`, `api/coupon_helper.php:86`; glob `api/*cancel*|*reschedul*|*update_booking*` = 0 files).
- Cancel exists **admin-only**: `bookings_cancel` sets raw status `Cancelled`, `cancel_reason` (`reason - details`, max 255), `cancelled_at`, releases the coupon use, and if paid online queues `transactions.refund_status='Refund Pending'` (no automatic PhonePe refund - TODO) (`api/admin/modules/bookings.php:281-330`).
- **Reschedule: NOT SUPPORTED** anywhere (grep `reschedul` in `api/` = no matches).

---

### Status vocabularies

#### Booking status (`service_booking.status`, free text)

Canonical mapping (`api/admin/core/status.php:12-25`), raw value written per canonical (`api/admin/core/status.php:28-38`), customer label (`api/my_bookings.php:17-24`):

| Canonical | Raw aliases accepted (lower-cased, trimmed) | Raw value written | Customer `status_label` |
|---|---|---|---|
| New | `pending confirmation`, `new`, `placed`, `booked` | `Pending Confirmation` (book_appointment) | Booking Received |
| Pending | `pending`, `booking confirmed`, `confirmed` (+ **any unknown value**) | `booking confirmed` | Confirmed |
| Assigned | `technician_assigned`, `technician assigned`, `assigned` | `technician_assigned` | Technician Assigned |
| Ongoing | `ongoing`, `in progress`, `in_progress`, `started` | `Ongoing` | In Progress |
| Completed | `service complete`, `completed`, `complete` | `Service Complete` (serviceComplete) | Completed |
| Cancelled | `cancelled`, `canceled` | `Cancelled` | Cancelled |

Unknown raw values map to `Pending` (`api/admin/core/status.php:48`, `api/admin/core/status.php:60`). Legacy `serviceBooking.php` can store any client string (`api/serviceBooking.php:40`). `my_bookings.php` always returns canonical values; legacy endpoints return raw.

#### Payment status

- `transactions.status`: `pending` (on initiation, `api/payments.php:78-83`), `success` / `failed` / `pending` (status check `api/paymentConfirmation.php:60-81`; webhook maps `SUCCESS|COMPLETED`->`success`, `FAILED`->`failed`, else `pending`, `api/phonepe_webhook_handler.php:35-40`). Legacy `save_transaction.php` also allows `refunded` (`api/save_transaction.php:43-45`).
- `transactions.refund_status`: `NULL` | `Refund Pending` | `Processed` (admin) (`api/admin/modules/payments.php:94`, `api/admin/modules/payments.php:110-114`, `api/admin/modules/bookings.php:310`).
- `transactions.payment_mode`: `PHONEPE` for website/app initiations (`api/payments.php:80`).
- `service_booking.payment_status`: `NULL` or `Paid` (admin "cash received") (`api/admin/modules/bookings.php:366`); `refunded` is read but not written by any code found (`api/my_bookings.php:44`).
- `service_booking.payment_method`: `NULL` or `Cash` (admin) (`api/admin/modules/bookings.php:366`); `online` is read (`api/my_bookings.php:70`) but **book_appointment never writes it**.
- Canonical (customer & admin): `Paid` / `Pending` / `Failed` / `Refunded` (`api/admin/core/repo.php:14-22`, `api/my_bookings.php:42-50`).

---

### ID links: booking -> order -> transaction

```
service_booking.ID (auto-inc, e.g. 4567)                         api/book_appointment.php:126
  └─ service_booking.unique_booking_id = "<user_id>-<ID>"  ("123-4567")   api/book_appointment.php:129-134
       └─ PhonePe merchantOrderId = transactions.transaction_id = "ZC-" + unique_booking_id ("ZC-123-4567")
            set by client in payments.php body.order_id                    zen-pages.js:850-856, api/payments.php:37, api/payments.php:57, api/payments.php:78-83
            joined back with CONCAT('ZC-', b.unique_booking_id)            api/my_bookings.php:39, api/admin/core/repo.php:11
            parsed back by /^ZC-(\d+-\d+)$/                                api/payments.php:40, api/admin/core/notify.php:382
       └─ orders.transaction_id (orders table never written -> no rows)    api/order_history.php:34
```

- There is no separate "order id" for bookings: the **order id == `ZC-<unique_booking_id>`**, which is also what `order_history.php` returns as `order_id` and what `payments.php` returns as `transaction_id` (`api/payments.php:85-89`).
- `payments.php` (POST, Bearer required): for a `ZC-` order id it looks up the booking by `unique_booking_id` **and** token user (404 `Booking not found` otherwise) and charges `service_booking.price` (the server-computed payable, int) when > 0, ignoring the client `amount` (`api/payments.php:40-51`). If price is 0/NULL it falls back to the client `amount` (`api/payments.php:36`, `api/payments.php:48-55`). Response `{"success":true,"payment_url":"https://...","transaction_id":"ZC-123-4567"}`.
- Legacy app payments (`payementInitiate.php`) use `TXN_XXXXXXXXXX` ids with **no booking link** (`api/payementInitiate.php:30`); `notify_booking_confirmed_for_transaction` is a no-op for them (`api/admin/core/notify.php:382-384`).
- Caveats affecting the link:
  - Legacy admin `assignTechnician.php` (non-strict path) overwrites `unique_booking_id` with a client value for **all** of a user's bookings, which breaks the `ZC-` join (`api/admin/assignTechnician.php:101-105`).
  - `payments.php` upsert resets an existing transaction to `pending` (`ON DUPLICATE KEY UPDATE status='pending'`) even if it was `success` (`api/payments.php:81`).
  - Legacy `save_transaction.php` upserts any `transaction_id` with a client-supplied `status` (incl. `success`), so in default (non-strict) legacy mode a `ZC-` booking can be marked Paid without paying (`api/save_transaction.php:31-68`).

---

### Website checkout flow to mirror (`zen-pages.js`)

1. Date `min` = today; on date change, POST `checkSlot.php {date}` -> `data` = array of free slot strings (`zen-pages.js:703-729`).
2. On submit: require login; re-price cart from the live catalog (`API.refreshCart()` -> `catalog_services.php?slugs=`); abort if any price changed or item removed (`zen-pages.js:753-793`, `zen-api.js:392-430`).
3. Build body (`zen-pages.js:795-833`):
   - `category`: unique categories joined `", "`; `subcategories`: `"Name xQty (₹price*qty)"` joined `", "`; `date`; `service_slot`; `location`: `"address, area, city - pincode"`; `landmark`: `"landmark | Note: notes | Contact: name phone"`; `user_id`: logged-in user id; `amount`: client cart total; `payment_method`: `"online"` if online radio and total > 0, else `"cash"`.
   - `items: [{pack_id, quantity}]` only if every line has `pack_id>0` (`zen-pages.js:559-564`, `zen-pages.js:830-831`); `coupon_code` if a previewed coupon is applied (`zen-pages.js:833`).
   - Token sent as both `Authorization: Bearer` and `?token=` (`zen-api.js:115-120`).
4. POST `book_appointment.php`; read `unique_booking_id`, `amount_payable` (fallback cart total), `coupon`, `discount` (`zen-pages.js:835-842`).
5. If not online **or** `payable <= 0`: clear cart (`DELETE cart.php`), show "Booking Received" (`zen-pages.js:844-848`).
6. Else POST `payments.php {amount: payable, order_id: "ZC-"+unique_booking_id, message: "Zen Home Experts booking <id>"}` (`zen-pages.js:850-856`); store `{transactionId, bookingId}` in localStorage `zenPendingPayment` and redirect to `payment_url` (`zen-pages.js:858-865`). Return handled by `zen-api.js:461-478` (paymentConfirmation). If payment start fails: booking stays (pay after service), cart cleared (`zen-pages.js:867-873`).
7. Errors: `errors.coupon_code` -> drop coupon and ask to confirm again; `errors.items` -> refresh cart (`zen-pages.js:875-887`). Any response with `status:"error"` or `success:false` or non-2xx is treated as failure (`zen-api.js:138-143`) - needed because `Invalid input` comes back with HTTP 200.

---

---

## 4. Payment (PhonePe v2 Standard Checkout)

### End-to-end flow (how the website does it; the app should copy it)

1. **Create the booking.** The customer books through `POST /api/book_appointment.php` (covered in another fragment). The response includes `unique_booking_id` (format `<user_id>-<booking ID>`, `api/book_appointment.php:129`) and `amount_payable` (`zen-pages.js:835-839`). Cash bookings, or bookings where payable <= 0, stop here (`zen-pages.js:844-847`).
2. **Start the payment.** The client sends `POST /api/payments.php` with a JSON body `{"amount": <payable, rupees>, "order_id": "ZC-<unique_booking_id>", "message": "Zen Home Experts booking <id>"}` plus the customer token (`zen-pages.js:850-856`; `zen-api.js:115-119` sends both `Authorization: Bearer` and `?token=`).
   - The server checks the token (`api/payments.php:30-34`). For `ZC-` orders it loads `service_booking.price` for that booking and that user, and uses it instead of the client's amount when it is > 0 (`api/payments.php:40-51`). It converts rupees to paise (`api/payments.php:58`) and calls PhonePe `POST /checkout/v2/pay` through the SDK, with `merchantOrderId = order_id` and the fixed `redirectUrl = PHONEPE_REDIRECT_URL` (`api/payments.php:57-74`).
   - If PhonePe answers `state=PENDING` with a redirect URL, the server upserts a `transactions` row (`transaction_id=merchantOrderId`, `status='pending'`, `payment_mode='PHONEPE'`, amount in rupees) (`api/payments.php:76-83`). It returns `{"success":true,"payment_url":...,"transaction_id":...}` (`api/payments.php:85-89`).
3. **Remember the order.** Before leaving the site, the client stores `{transactionId, bookingId}` in localStorage under the key `zenPendingPayment` (`zen-pages.js:858-863`, `zen-api.js:14`). The reason, per the code comment, is that PhonePe sends the customer back *without* the order id (`zen-api.js:450-454`).
4. **Pay on PhonePe.** The client navigates to `payment_url`, which is PhonePe's hosted checkout page, absolute URL (`zen-pages.js:865`). If starting the payment fails, the booking stays and is treated as pay-after-service (`zen-pages.js:867-873`).
5. **PhonePe redirects back.** After payment, PhonePe sends the browser to `PHONEPE_REDIRECT_URL`, exactly as configured. Nothing is appended by our code (`api/payments.php:59,70`). If that env value points to `/api/payment_callback.php` (which `.env.example:12` suggests), the callback sends a 302 to `PAYMENT_CALLBACK_BASE_URL` (or `APP_URL`) with `?payment=success|failed[&txn=<id>]` (`api/payment_callback.php:12-25`). The `payment=` value is **not reliable**: with no query params, `status` defaults to `failed` (`api/payment_callback.php:5`). No website code reads `?payment=` (grep: no consumer).
6. **PhonePe webhook (server to server, asynchronous).** PhonePe calls `POST /api/payment_webhook.php`. The server checks `Authorization == sha256(PHONEPE_WEBHOOK_USERNAME:PHONEPE_WEBHOOK_PASSWORD)` and maps state `SUCCESS/COMPLETED` to `success`, `FAILED` to `failed`, anything else to `pending`. It updates `transactions.status`. On success it also clears the user's `cart` and sends the booking-confirmed SMS through `notify_booking_confirmed_for_transaction()` (`api/phonepe_webhook_handler.php:33-70`).
7. **Confirm the result.** On every page load the website reads `zenPendingPayment` and calls `GET /api/paymentConfirmation.php?transactionId=<merchantOrderId>` (`zen-api.js:456-484, 520-523`). The server calls PhonePe order status (`/checkout/v2/order/{id}/status?details=true`) and syncs the database: success sets `success`, clears the cart and sends the SMS once; `FAILED` sets `failed`; `PENDING` sets `pending` (`api/paymentConfirmation.php:44-81`). The client treats `state` `COMPLETED`/`SUCCESS` as success (clear the pending key and the cart), `FAILED` as failed (clear the pending key; the booking stays and can be paid after service), and anything else as "still processing" (keep the key and retry on the next load) (`zen-api.js:471-482`).
8. **Booking payment status.** No code writes `service_booking.payment_status` for online payments. "Paid/Failed/Pending" is derived by joining `transactions.transaction_id = CONCAT('ZC-', unique_booking_id)` (`api/my_bookings.php:39,44-50`, `api/admin/core/repo.php:11-21`). **The app must use `order_id = "ZC-<unique_booking_id>"`.** Otherwise the booking never shows Paid, the server-side price override is skipped, and no confirmation SMS is sent (`api/admin/core/notify.php:382-384`).

**Recommended app mirror:** Steps 1, 2 and 3 are the same (save `transaction_id` locally). For step 4, open `payment_url` in a Custom Tab/WebView. Treat navigation to `PHONEPE_REDIRECT_URL` or the `?payment=` landing URL, or the user closing the tab, as "returned". Ignore the `payment=` value and poll `paymentConfirmation.php?transactionId=` with backoff until `state` is `COMPLETED`/`SUCCESS`/`FAILED`. The PhonePe native SDK flow is **NOT SUPPORTED** (see the `api/phonepe_client.php` section below).

### Env KEY NAMES used (values never shown)

| Key | Used at | Purpose |
|---|---|---|
| `PHONEPE_CLIENT_ID` | `api/phonepe_client.php:13` | SDK OAuth client id |
| `PHONEPE_CLIENT_VERSION` | `api/phonepe_client.php:14` | int, default 1 |
| `PHONEPE_CLIENT_SECRET` | `api/phonepe_client.php:15` | SDK OAuth secret |
| `PHONEPE_ENV` | `api/phonepe_client.php:16-22` | `STAGE`/`UAT`/`PRODUCTION`; **anything else (e.g. `SANDBOX` in `.env.example:10`) silently becomes PRODUCTION** |
| `PHONEPE_REDIRECT_URL` | `api/payments.php:59`, `api/payementInitiate.php:25` | Static browser return URL passed to PhonePe pay |
| `PAYMENT_CALLBACK_BASE_URL` | `api/payment_callback.php:12` | Where `payment_callback.php` redirects (fallback `APP_URL`) |
| `APP_URL` | `api/runtime.php:47` | Fallback base; default `https://zenhomeexperts.com` |
| `PHONEPE_WEBHOOK_USERNAME`, `PHONEPE_WEBHOOK_PASSWORD` | `api/payment_webhook.php:24-25` | Webhook auth hash |
| `ADMIN_SECRET` | `api/payments.php:102` | `Admin-Token` header for GET listing |
| `LEGACY_STRICT_AUTH` | `api/legacy_access.php:52` | Legacy endpoints auth flag |
| `RECEIPT_BASE_URL` | `api/user_transaction.php:49` | Receipt link prefix |
| `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` | `api/payments.php:108-112`, `api/db.php:16-19` | DB |
| `PHONEPE_MERCHANT_ID` | `.env.example:11` only | **Not read by any code** (grep) |

---

#### POST /api/payments.php  (payment initiation - the endpoint the app should use)

> **Updated 2026-10-07:** `order_id` must be `ZC-<your unique_booking_id>` (422); the amount always comes from the booking (client `amount` ignored); someone else's / cancelled booking 404 / 409; already paid 409; gateway errors 502 with a generic message. The redirect URL now ends with `?transactionId=<order_id>`. All responses carry `statusCode`; success adds `data`. Section 8.5.
- **Auth:** Customer Bearer token (required), via `getUserIdFromRequest()` (`api/payments.php:30`). Token sources: `?token=`, POST form `token`, `Authorization`, `X-Auth-Token`/`X-Authorization`, JSON `token` (`api/auth_helper.php:16-65`).
- **Content-Type:** JSON body via `php://input` only (`api/payments.php:35`). Form-encoded fields are ignored, so `amount` is null and the response is 400 unless the price override applies.
- **Source:** `api/payments.php:25-98`
- **CORS:** `Access-Control-Allow-Origin: *`; OPTIONS answers 200 before anything else (`api/payments.php:3-16`).

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| token | query/header/body | string | yes | 64-hex session token | `api/payments.php:30` |
| amount | body | number (**rupees**, float) | yes, unless a `ZC-` booking has price > 0 | cast `(float)`; must be > 0; sent to PhonePe as `round(amount*100)` paise | `api/payments.php:36,52-58` |
| order_id (alias `orderId`) | body | string | no (but use `ZC-<unique_booking_id>`) | becomes `merchantOrderId`. If empty, `ORDER_` + 16 uppercase hex is generated. No length or format check (DB column is varchar(50)) | `api/payments.php:37,57` |
| message | body | string | no | default `"Order payment"`; passed to PhonePe | `api/payments.php:38,71` |

**Server-side price override:** if `order_id` matches `/^ZC-(\d+-\d+)$/`, the server runs `SELECT price FROM service_booking WHERE unique_booking_id=? AND user_id=<token user>`. If no row: 404. If `price > 0`: amount = price (rupees). If price is 0 or NULL, the client amount is used (`api/payments.php:40-51`). Booking status (cancelled, already paid) is not checked.

**Success response** (200)
```json
{"success": true, "payment_url": "https://mercury-t2.phonepe.com/transact/...", "transaction_id": "ZC-12-345"}
```
`transaction_id` is the **merchantOrderId** (our id, also `transactions.transaction_id`). The response does **not** include PhonePe's own `orderId`, `state`, `expireAt` or a `redirectUrl` key, even though the SDK has them (`vendor/phonepe/pg-php-sdk-v2/src/phonepe/sdk/pg/payments/v2/standardCheckout/StandardCheckoutClient.php:109`). Only `payment_url` is returned (`api/payments.php:85-89`).

**DB side effect:** `INSERT INTO transactions (transaction_id,user_id,amount,status,payment_mode) VALUES (merchantOrderId, user_id, amount_rupees, 'pending', 'PHONEPE') ON DUPLICATE KEY UPDATE status='pending'` (`api/payments.php:78-83`). On a duplicate key, the amount and user_id are **not** updated. `transaction_id` is UNIQUE (`schema dump (.sql):1448`).

**Error responses**
| HTTP | When | Body | Source |
|---|---|---|---|
| 401 | No or invalid token | `{"error":"Unauthorized","message":"Please log in again."}` | `api/payments.php:31-34` |
| 404 | `ZC-` booking not found for this user | `{"error":"Booking not found","message":"This booking was not found for your account."}` | `api/payments.php:44-47` |
| 400 | amount missing or <= 0 | `{"error":"Invalid amount"}` | `api/payments.php:52-55` |
| 500 | `PHONEPE_REDIRECT_URL` empty | `{"error":"PHONEPE_REDIRECT_URL not configured"}` | `api/payments.php:60-63` |
| 500 | PhonePe state not PENDING or no redirect URL | `{"error":"Payment initiation failed","state":"<state>"}` | `api/payments.php:90-93` |
| 400 | PhonePeException (gateway/auth error) | `{"error":"Payment gateway error","message":"<sdk msg>"}` | `api/payments.php:94-96` |
| 500 (blank or non-JSON) | PDOException on insert, DB down (`api/db.php:25-27` echoes plain text) | not JSON | `api/payments.php:78-83` |

**Notes**
- Envelope is `success`/`error`, not the `public_json` `statusCode/status/message` envelope. Errors have no `success:false` key. Use the HTTP status code.
- No rate limit and no idempotency check. Calling again with the same `ZC-` id re-calls PhonePe pay with the same merchantOrderId. If PhonePe accepts, the row is reset to `pending`. Whether PhonePe rejects a reused merchantOrderId: unknown (SDK passthrough).
- `user_id` stored as the token user (int into varchar(50)).

#### GET /api/payments.php  (legacy admin listing - not for the app)
- **Auth:** `Admin-Token` header must `hash_equals` `ADMIN_SECRET`. If `ADMIN_SECRET` is empty the listing is disabled (`api/payments.php:102-105`).
- **Content-Type:** query string. **Any non-POST method** (GET/PUT/DELETE...) lands here (`api/payments.php:25,101`).
- **Source:** `api/payments.php:101-239` (mysqli connection, `api/payments.php:108-118`)

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| Admin-Token | header | string | yes | equals `ADMIN_SECRET` | `api/payments.php:102` |
| page | query | int | no | `max(1, page)`, default 1 | `api/payments.php:123` |
| limit | query | int | no | `min(100, limit)`, default 20 (no lower bound) | `api/payments.php:124` |
| status | query | string | no | exact match on `status` | `api/payments.php:136-140` |
| date_from / date_to | query | date | no | `created_at >= from`, `<= to 23:59:59` | `api/payments.php:142-152` |
| search | query | string | no | LIKE on transaction_id/user_id/payment_mode | `api/payments.php:154-161` |

**Success response** (200)
```json
{"success": true, "data": {"meta": {"page": 1, "limit": 20, "total": 1, "pages": 1},
 "transactions": [{"id": "ZC-12-345", "user": "12", "amount": 499.0, "status": "success", "method": "PHONEPE", "date": "2026-10-01 10:00:00", "last_updated": null}]}}
```
**Error responses:** 401 `{"error":"Unauthorized"}` (`api/payments.php:103-104`); 500 `{"error":"Database connection failed"}` (`api/payments.php:115-118`); 400 `{"success":false,"error":"<msg>"}` (`api/payments.php:230-235`). With `limit=0` there is a division by zero at `api/payments.php:224`, a DivisionByZeroError that `catch (Exception)` does not catch, so the response is a 500 that is not JSON.
**Notes:** `api/admin/payments.php` uses the same `ADMIN_SECRET` check (`api/admin/payments.php:29`).

---

#### api/phonepe_client.php  (shared SDK factory, not an endpoint)
- `get_phonepe_client()` returns the `StandardCheckoutClient` singleton built from `PHONEPE_CLIENT_ID`, `PHONEPE_CLIENT_VERSION` (int, default 1), `PHONEPE_CLIENT_SECRET` and `PHONEPE_ENV` (`api/phonepe_client.php:12-24`). The env mapping is `STAGE`, `UAT`, `PRODUCTION`, with **default PRODUCTION** (`api/phonepe_client.php:17-22`).
- PhonePe APIs used, all through the vendored SDK `vendor/phonepe/pg-php-sdk-v2`:
  - **OAuth token:** `/identity-manager/v1/oauth/token`, fetched and refreshed internally by the SDK token service (`.../common/configs/Constants.php:18`; refresh on 401 at `.../standardCheckout/StandardCheckoutClient.php:111-113`). No endpoint of ours exposes it.
  - **Standard Checkout v2 pay:** `POST /checkout/v2/pay` (`.../standardCheckout/StandardCheckoutConstants.php:7`, `StandardCheckoutClient.php:95-116`). Used by `api/payments.php:74` and `api/payementInitiate.php:42`.
  - **Order status:** `GET /checkout/v2/order/{merchantOrderId}/status?details=true` (`.../common/configs/Constants.php:19`, `StandardCheckoutClient.php:137-157`). Used by `api/paymentConfirmation.php:44`, `api/payment_verify.php:35` and `api/fetch-transaction.php:27`.
  - **Webhook verify:** `verifyCallbackResponse()` compares the `authorization` header to `sha256(username:password)` with `!==` (`StandardCheckoutClient.php:165-176`).
  - Refund and refund status exist in the SDK (`StandardCheckoutClient.php:184,205`) but are not used by these endpoints.
- **PhonePe mobile SDK "create SDK order" (orderId + token): NOT SUPPORTED.** Grepping `createSdkOrder|sdk_order|sdkorder` finds nothing in the repo or `vendor/`. `StandardCheckoutClient` has only pay, getOrderStatus, verifyCallbackResponse, refund and getRefundStatus. No endpoint returns a PhonePe order token. The app must use the web `payment_url` (redirect flow).

---

#### GET /api/payment_callback.php  (browser return URL - HTML redirect, not JSON)

> **Updated 2026-10-07:** reads `transactionId`, asks PhonePe for the real status, records it (same rules as `paymentConfirmation.php`) and redirects with `payment=success|failed|pending&txn=<id>`; unknown id or gateway error = `pending`. Section 8.5.
- **Auth:** None.
- **Content-Type:** query string `$_GET`. Output is a `Location:` header only (PHP default 302), with no body and no CORS.
- **Source:** `api/payment_callback.php:1-26`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| status (alias `state`) | query | string | no | default `"failed"`; counts as success if `success`, `COMPLETED` or `SUCCESS` | `api/payment_callback.php:5,17` |
| transactionId (aliases `merchantTransactionId`, `orderId`) | query | string | no | echoed back as `txn` | `api/payment_callback.php:6,20-22` |

**Success response:** `302 Location: <PAYMENT_CALLBACK_BASE_URL or app_url()>?payment=success|failed[&txn=<id>]` (`api/payment_callback.php:12-25`).
**Notes**
- How `PHONEPE_REDIRECT_URL` is built: it is the raw env value passed to PhonePe for every order. **Nothing is appended**, so there is no `?transactionId=` (`api/payments.php:59,70`; `api/payementInitiate.php:25,39`). Whether it points at `payment_callback.php` is deployment config (`.env.example:12` comment), which I can't confirm from the code.
- Because no status or id is appended, the callback normally receives neither. It then redirects to `?payment=failed` with no `txn`, even after a successful payment (`api/payment_callback.php:5-6,17-18`). The website ignores `?payment=` and uses localStorage + `paymentConfirmation.php` instead (`zen-api.js:450-484`).
- The status is read from the query string and never verified with PhonePe.

---

#### POST /api/payment_webhook.php  (PhonePe server-to-server; not called by the app)

> **Updated 2026-10-07:** a row in `success`/`refunded` is never moved back (late FAILED events are ignored), and cart clear + confirmation SMS run only on the change to success.
- **Auth:** the `Authorization` header (from `HTTP_AUTHORIZATION` or `REDIRECT_HTTP_AUTHORIZATION`) must equal `sha256(PHONEPE_WEBHOOK_USERNAME + ":" + PHONEPE_WEBHOOK_PASSWORD)` (`api/payment_webhook.php:18-27`; SDK `StandardCheckoutClient.php:167-170`).
- **Content-Type:** raw JSON body via `php://input`, mapped with JsonMapper into `CallbackResponse{type, payload{merchantId, merchantOrderId, orderId, state, amount, expireAt, paymentDetails}}` (`api/payment_webhook.php:17`; `vendor/phonepe/pg-php-sdk-v2/src/phonepe/sdk/pg/payments/v2/models/response/CallbackResponse.php:10-11`).
- **Source:** `api/payment_webhook.php:1-45`, logic in `api/phonepe_webhook_handler.php:25-71`.

**Events handled:** the callback `type` is **not inspected**. Every event is handled by `payload.merchantOrderId` + `payload.state` (`api/phonepe_webhook_handler.php:33-40`). State mapping: `SUCCESS`/`COMPLETED` to `success`, `FAILED` to `failed`, anything else to `pending`.

**Responses** (JSON; HTTP code from the handler)
| HTTP | When | Body | Source |
|---|---|---|---|
| 401 | Signature mismatch or bad body | `{"status":"invalid_signature","message":"Invalid callback"}` | `api/phonepe_webhook_handler.php:27-31` |
| 200 | No merchantOrderId | `{"status":"ignored","message":"Callback had no merchantOrderId."}` | `api/phonepe_webhook_handler.php:42-44` |
| 200 | No `transactions` row | `{"status":"unknown_order","transaction_id":"..."}` | `api/phonepe_webhook_handler.php:50-53` |
| 200 | Status unchanged | `{"status":"duplicate","transaction_id":"...","current_status":"success"}` | `api/phonepe_webhook_handler.php:55-58` |
| 200 | Applied | `{"status":"ok","transaction_id":"ZC-12-345","applied_status":"success","previous_status":"pending"}` | `api/phonepe_webhook_handler.php:70` |
| 500 | Any Throwable | `{"status":"error","message":"Webhook processing failed."}`, sent **without** a Content-Type header | `api/payment_webhook.php:30-37` |

**DB updates:** `UPDATE transactions SET status=?` (`api/phonepe_webhook_handler.php:60`). On success: `DELETE FROM cart WHERE user_id=?` (`:62`), then `notify_booking_confirmed_for_transaction($merchantOrderId)` (`:63-67`, errors are only logged).
**SMS:** `notify_booking_confirmed_for_transaction()` acts only for ids matching `/^ZC-(\d+-\d+)$/` (`api/admin/core/notify.php:380-384`). It looks up `service_booking.ID` by `unique_booking_id` (`:386`), atomically claims `confirm_sms_sent_at IS NULL` so the SMS goes out once per booking (`:352-355`), then sends the customer `booking_confirmation` template ("Paid online"/"Cash on service") and an admin new-booking alert (`:360-366`). It never throws (`:367-370,390-392`).
**Notes:** no method check. The status change is not monotonic: a later non-success state can overwrite `success` (`api/phonepe_webhook_handler.php:55-60`). The paid amount is not compared with `transactions.amount`.

---

#### GET|POST /api/payment_verify.php  (stateless PhonePe status lookup)

> **Updated 2026-10-07:** customer token required, own transactions only (404); JSON body accepted; adds `statusCode`, `message`, `payment_status`, `data.merchantOrderId`, `data.amount_rupees`. Section 8.5.
- **Auth:** None. No DB access, no legacy guard.
- **Content-Type:** POST reads a JSON body (`php://input`); any other method reads `$_GET` (`api/payment_verify.php:20-25`).
- **Source:** `api/payment_verify.php:1-56`; CORS + OPTIONS at `:3-12`.

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| transaction_id (alias `transactionId`) | JSON body (POST) / query (GET) | string | yes | merchantOrderId | `api/payment_verify.php:22,24` |

**Success response** (200)
```json
{"status": "success", "state": "COMPLETED",
 "data": {"transaction_id": "OMO2510071212345678", "amount": 499.0, "payment_details": [{"paymentMode": "UPI_QR", "timestamp": 1759820000000, "amount": 49900, "transactionId": "OM2510071212345678", "state": "COMPLETED"}]}}
```
- `status` mapping: `COMPLETED`/`SUCCESS` to `success`; `FAILED`/`CANCELLED` to `failed`; otherwise `pending` (`api/payment_verify.php:38`).
- `data.transaction_id` is **PhonePe's orderId** (falls back to the input), not our merchantOrderId (`api/payment_verify.php:47`). `amount` is in **rupees** (`:40-41`). `payment_details` is the SDK's PaymentDetail objects serialized: public props paymentMode, timestamp, amount (paise), transactionId, state, errorCode, detailedErrorCode, rail, instrument (`vendor/phonepe/pg-php-sdk-v2/src/phonepe/sdk/pg/payments/v2/models/response/ResponseComponents/PaymentDetail.php:9-18`).

**Error responses**
| HTTP | When | Body | Source |
|---|---|---|---|
| 400 | Missing id | `{"status":"error","message":"Transaction ID is required"}` | `api/payment_verify.php:27-31` |
| 400 | PhonePeException (unknown order etc.) | `{"status":"error","message":"<sdk msg>"}` | `api/payment_verify.php:52-55` |

**Notes:** read-only and does **not** update the DB. Top-level `status` holds the payment status, not the envelope status. The website does not use it (grep: no caller in `zen-*.js`).

---

#### GET|POST /api/paymentConfirmation.php  (status endpoint the website polls - app should poll this)

> **Updated 2026-10-07:** customer token required, own transactions only (404); JSON body accepted (`transactionId` / `transaction_id` / `merchantOrderId`); missing id 400, gateway error 502. Adds `statusCode`, `status`, `message`, `payment_status`, `merchantOrderId`, `amount_rupees`, `data`. Cart clear + SMS only when this call moves the row to success. Section 8.5.
- **Auth:** Legacy guard `legacy_access('paymentConfirmation')` (`api/paymentConfirmation.php:18-19`). Default (`LEGACY_STRICT_AUTH=false`): **no auth**. Strict mode: token required (401), and the transaction must exist and belong to the token user, otherwise 404 `"Transaction not found."` (`api/paymentConfirmation.php:38`; `api/legacy_access.php:130-132,166-179`). Send the token anyway.
- **Content-Type:** `$_POST['transactionId']` (form) or `$_GET['transactionId']`. **A JSON body is NOT read** (`api/paymentConfirmation.php:28`).
- **Source:** `api/paymentConfirmation.php:1-90`; CORS + OPTIONS at `:4-17`.

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| transactionId | query or form | string | yes | merchantOrderId (`transaction_id` from `payments.php`) | `api/paymentConfirmation.php:28` |
| token | query/header | string | only in strict mode | customer token | `api/legacy_access.php:110-132` |

**Success response** (200)
```json
{"success": true, "merchantTransactionId": "OMO2510071212345678", "transactionId": "OMO2510071212345678",
 "state": "COMPLETED", "amount": 49900, "paymentDetails": [{"paymentMode": "UPI_INTENT", "timestamp": 1759820000000, "amount": 49900, "transactionId": "OM2510071212345678", "state": "COMPLETED"}]}
```
- `state` is the raw PhonePe order state: `PENDING`, `COMPLETED`, `FAILED` (the code also accepts `SUCCESS` as success) (`api/paymentConfirmation.php:46,60,77,79`).
- `amount` is in **paise** (raw `getAmount()`), unlike `payment_verify`/`fetch-transaction`, which return rupees (`api/paymentConfirmation.php:55`).
- Despite their names, `merchantTransactionId` and `transactionId` both hold **PhonePe's orderId** (`getOrderId()`), not our merchantOrderId (`api/paymentConfirmation.php:47,52-53`).

**DB sync (yes, it calls PhonePe and updates the DB)** (`api/paymentConfirmation.php:44,59-81`):
- `SUCCESS`/`COMPLETED`: only if a `transactions` row exists, sets `status='success'`, runs `DELETE FROM cart WHERE user_id=<row user>`, then `notify_booking_confirmed_for_transaction()` (SMS once per booking, guarded by `confirm_sms_sent_at`).
- `FAILED`: `status='failed'`. `PENDING`: `status='pending'`. Other states (e.g. a cancelled order) leave the DB unchanged.
- **Safe to call before the webhook:** yes. It reads PhonePe as the source of truth and the SMS is idempotent (`api/admin/core/notify.php:352-355`). But every call with a success state clears the user's cart again (`api/paymentConfirmation.php:67`), so stop polling once a final state arrives.

**Error responses**
| HTTP | When | Body | Source |
|---|---|---|---|
| **200** | Missing transactionId | `{"success":false,"message":"Missing transactionId"}` | `api/paymentConfirmation.php:30-36` |
| **200** | PhonePeException (unknown order, gateway down) | `{"success":false,"message":"<sdk msg>"}` | `api/paymentConfirmation.php:85-89` |
| 401 / 404 | Strict mode: no token / not the owner or no row | `{"statusCode":401,"status":"error","success":false,"message":"...","error":"..."}` | `api/legacy_access.php:140-147` |
| 500 (not JSON) | PDOException in the sync block (uncaught; only PhonePeException is caught) | - | `api/paymentConfirmation.php:61-81` |

**Website handling to mirror** (`zen-api.js:456-484`): it calls `paymentConfirmation.php?transactionId=` (plus `?token=` and Bearer). `state` `COMPLETED`/`SUCCESS` means paid (remove the pending key, clear the cart, show "Payment successful. Booking <id> is confirmed."). `FAILED` means remove the pending key and say the booking is saved and can be paid after service. Anything else means "still processing" (keep the key). On any request error it retries on the next page load. `success:false` counts as an error in `request()` (`zen-api.js:138-143`).

---

#### Legacy payment endpoints (all go through `api/legacy_access.php`; no CORS headers or OPTIONS handling)

> **Updated 2026-10-07:** these now send CORS headers and generic error messages (no raw exception text). `save_transaction.php` answers **410 Gone** for every call. `user_orders.php` clamps `limit` to 1-50.

##### POST /api/payementInitiate.php  (replaced by `POST /api/payments.php`)
- **Auth:** legacy guard (`api/payementInitiate.php:3-6`). Default: none. Strict mode: token required, and JSON `user_id` must equal the token user (403).
- **Content-Type:** JSON body. **Source:** `api/payementInitiate.php:16-75`.
- **Fields:** `amount` (required, numeric, rupees) (`:21-23`); `user_id` (optional; if missing the row is stored as `GUEST_<4 digits>`) (`:33,54`). merchantOrderId is always `TXN_` + 10 uppercase hex (`:30`). No `ZC-` price override, no booking link, no message.
- **Success 200:** `{"success":true,"payment_url":"https://...","transaction_id":"TXN_A1B2C3D4E5"}` (`:57-61`). It inserts a pending `transactions` row (`:49-54`).
- **Errors:** 400 `{"success":false,"error":"<msg>"}` for a bad amount, missing redirect env, failed initiation or a PhonePe error (`:63-75`).

##### GET|POST /api/fetch-transaction.php  (replaced by `GET /api/paymentConfirmation.php`)
- **Auth:** legacy guard. Strict mode: the transaction must exist and be owned by the token user (`api/fetch-transaction.php:3-7`).
- **Fields:** `transaction_id` (JSON body or query) (`:19`).
- **Success 200:** `{"success":true,"data":{"transaction_id","amount" (rupees),"status":"success|failed|pending","payment_mode","timestamp" (ISO-8601),"user_reference":null,"raw_response":{...full PhonePe status...}}}` (`:34-47`).
- **Errors:** 400 `{"success":false,"error":"<msg>"}` (`:49-61`). It calls PhonePe status but **does not update the DB**.

##### GET|POST /api/user_transaction.php  (replaced by `GET /api/order_history.php` (requireAuth) / `GET /api/my_bookings.php` payment_status)
- **Auth:** legacy guard. Default: **none**. Strict mode: `user_id` must equal the token user (`api/user_transaction.php:3-6`).
- **Fields:** `user_id` (JSON body or query, required) (`:29-33`).
- **Success 200:** `{"success":true,"data":{"user_id","count","transactions":[{"transaction_id","amount","status","payment_mode","date","receipt_url"}]}}`, limited to 100 rows (`:36-75`). `receipt_url` is **absolute**: `RECEIPT_BASE_URL` + id, or `<scheme>://<HTTP_HOST>/api/order_details.php?order_id=` + id (`:49-54,64`).
- **Errors:** 500 `{"error":"Database connection failed"}` (`:21-24`); 400 `{"success":false,"error":"User ID is required"}` (`:77-82`).
- Replacement evidence: `api/order_history.php:17-35` lists the token user's `transactions` with requireAuth.

##### POST /api/save_transaction.php  (no replacement needed - the server writes transactions itself in `payments.php` and the webhook/`paymentConfirmation.php`)
- **Auth:** legacy guard. Default: **none**. Strict mode: `user_id` must equal the token user, and an existing row must be owned by them (`api/save_transaction.php:3-7`).
- **Fields (JSON):** `transaction_id`, `user_id`, `amount`, `status` (all required and non-empty) (`:31-36`). `status` must be one of `pending|success|failed|refunded`, otherwise `pending` (`:43-45`). Optional: `payment_mode` (default `UNKNOWN`), `phonepe_response`, `metadata` (`:46-48`).
- **Effect:** upserts a `transactions` row, overwriting `status`/`payment_mode`/`phonepe_data`/`metadata` (`:52-68`).
- **Success 200:** `{"success":true,"data":{"transaction_id","status","amount","payment_mode","timestamp"}}` (`:86-97`). **Errors:** 400 `{"success":false,"error":"..."}` (`:99-104`), 500 DB connect (`:22-25`).

---

---

## 5. Other: ratings, enquiries, support tickets, partner sign-up, NOT SUPPORTED

Shared envelope used below: `public_json()` = `{"statusCode":<int>,"status":"success|error","message":"...","data"?:{...},"errors"?:{field:msg}}` (`api/public_helper.php:28-40`, status is `success` when code < 400). `public_input()` = `$_GET` merged with the JSON body when the request `Content-Type` contains `application/json`, else merged with `$_POST`; an invalid JSON body on POST -> 400 `"Invalid JSON body."`; the `token` key is removed from the input (`api/public_helper.php:71-86`). `public_cors()` sends `Access-Control-Allow-Origin: *`, `Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With`, answers OPTIONS with an empty 200, then sets `Content-Type: application/json; charset=utf-8` and `X-Content-Type-Options: nosniff` (`api/public_helper.php:15-26`). `public_require_method()` -> 405 `"Use GET for this request."` / `"Use POST for this request."` (`api/public_helper.php:63-68`). `public_db()` buffers `db.php` output, so a DB outage gives 500 `"Service temporarily unavailable. Please try again."` (`api/public_helper.php:108-125`).

---

#### POST /api/rating.php

> **Updated 2026-10-07:** customer token required (body `user_id` ignored); someone else's or unknown booking 404; booking not Completed 409; `feedback` optional; success HTTP 201 with `statusCode:201`, `review_id`, `review_status`, `data.review`. Section 8.6.
- **Auth:** Customer Bearer token **optional**. If any token is found by `getBearerToken()` (query `token`, form `token`, `Authorization`, `X-Auth-Token`/`X-Authorization`, or JSON body `"token"`) it must be valid and belong to `user_id`; with no token the request is accepted on the body `user_id` alone (`api/rating.php:57-66`).
- **Content-Type:** JSON body only (`json_decode(file_get_contents("php://input"), true)`, `api/rating.php:28`). Form posts are not parsed. No HTTP method check (any method with a JSON body works).
- **Source:** `api/rating.php:14-110`

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| user_id | body | int (scalar) | yes | cast `(int)`, must be > 0; must equal the token's user when a token is sent | `api/rating.php:30-31,42,47,63` |
| unique_booking_id | body | string or int (scalar) | yes | trimmed of spaces and `"`; matched against `service_booking.unique_booking_id` (e.g. `"12-38"`, `"SERVICY-00038"`) **or** numeric `service_booking.ID`; the code match wins (`ORDER BY (unique_booking_id = ?) DESC`) | `api/rating.php:43,69-70` |
| rating | body | number (scalar) | yes | must be a whole number 1-5 (`"4"`, `4`, `4.0` OK; `4.5`, `0`, `6` -> 422); stored as int | `api/rating.php:44,50-52` |
| feedback | body | string (scalar) | yes (key must exist; empty string allowed) | `strip_tags` + trim; max 2000 chars (mb) | `api/rating.php:45,53-55` |
| token | query/header/body | string | no | see Auth | `api/auth_helper.php:16-65` |

**Success response** (HTTP 201 - both for a new review and for an update)
```json
{"message":"Data saved successfully","status":"success","review_id":57,"review_status":"Pending"}
```
`review_status` is `"Approved"` when `.env` `REVIEWS_AUTO_APPROVE` is `1|true|yes|on`, else `"Pending"` (`api/rating.php:79-80,100-107`). No `statusCode` key.

**Error responses** (all except the first use `{"status":"error","error":"<msg>","message":"<msg>"}`, `api/rating.php:20-25`)
| HTTP | When | Message | Source |
|---|---|---|---|
| 400 | body not a JSON object, any of the 4 keys missing, or a non-scalar value | `{"error":"Missing or invalid data.  Please provide user_id, booking_id, rating, and feedback."}` (**only** the `error` key) | `api/rating.php:30-35` |
| 400 | `user_id` <= 0 or booking ref empty after trim | same text, rating_fail shape | `api/rating.php:47-49` |
| 422 | rating not a whole number 1-5 | "Rating must be a whole number from 1 to 5." | `api/rating.php:50-52` |
| 422 | feedback > 2000 chars | "Feedback can be at most 2000 characters." | `api/rating.php:53-55` |
| 401 | a token was sent but is invalid/expired | "Unauthorized or session expired. Please log in again." | `api/rating.php:58-62` |
| 403 | token user != `user_id` | "You can only rate your own bookings." | `api/rating.php:63-65` |
| 404 | no booking by code or ID | "Booking not found." | `api/rating.php:72-74` |
| 403 | booking has a non-NULL `user_id` different from `user_id` | "You can only rate your own bookings." | `api/rating.php:75-77` |
| 500 | `$conn` not a PDO | "Error saving data: database connection failed." | `api/rating.php:38-40` |
| 500 | any PDOException | "Error saving data. Please try again." | `api/rating.php:95-98` |

**Notes**
- **Overwrite behaviour:** existing review lookup is `SELECT id FROM ratings_feedback WHERE user_id = ? AND unique_booking_id = ? ORDER BY id DESC LIMIT 1` with the booking's numeric `ID` (`api/rating.php:83-85`). If found: `UPDATE ratings_feedback SET rating, feedback, booking_ref, status (= Pending|Approved), moderated_by = NULL, moderated_at = NULL, updated_at` (`api/rating.php:87-89`) - i.e. a re-rating re-queues moderation; `created_at` and `featured` are **not** touched. Otherwise INSERT (`api/rating.php:90-93`). `ratings_feedback.unique_booking_id` stores `service_booking.ID`, `booking_ref` stores the raw ref (max 50 chars) (`api/admin/modules/reviews.php:4`).
- **Ownership check:** only `booking.user_id` vs body `user_id` (`api/rating.php:75`); bookings with `user_id = NULL` can be rated by any `user_id`. No check of booking status (a New/Cancelled booking can be rated) (`api/rating.php:69-77`).
- Approved + `featured = 1` reviews are shown as homepage testimonials (`api/site_content.php:220-226`).
- No CORS headers and no OPTIONS handling (file only sets `Content-Type: application/json` at `api/rating.php:18`, after `include 'db.php'` at `api/rating.php:14`).
- No rate limit. No endpoint returns a customer's existing review (see NOT SUPPORTED).
- No images.

---

#### POST /api/contact_enquiry.php

> **Updated 2026-10-07:** a signed-in enquiry is now filed under the real customer (it was filed under customer #1). Phone uses the shared `^[6-9]\d{9}$` rule.
- **Auth:** None. A customer Bearer token, if valid, is meant to link the ticket to the customer (but see bug below) (`api/contact_enquiry.php:63-64`).
- **Content-Type:** `public_input()` - JSON body (when `Content-Type: application/json`) or form `$_POST`, merged over query (`api/contact_enquiry.php:19`).
- **Source:** `api/contact_enquiry.php:11-99`. CORS via `public_cors('POST, OPTIONS')`, method enforced POST (`api/contact_enquiry.php:16-17`).

**Request fields**
| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| name | body | string | yes | 1-100 chars | `api/contact_enquiry.php:20,35-37` |
| phone | body | string | yes | all non-digits removed; 12 digits starting `91` -> last 10; must match `^[6-9]\d{9}$` | `api/contact_enquiry.php:21-24,38-40` |
| email | body | string | no | if sent: <= 150 chars and valid email | `api/contact_enquiry.php:25,41-43` |
| service | body | string | yes | 1-100 chars | `api/contact_enquiry.php:26,44-46` |
| address | body | string | no | <= 255 chars | `api/contact_enquiry.php:27,47-49` |
| message | body | string | yes | 1-2000 chars | `api/contact_enquiry.php:28,50-52` |
| website | body | string | no | honeypot - must be empty; if non-empty the request is answered 201 and **nothing is saved** | `api/contact_enquiry.php:30-32` |

**Success response** (HTTP 201)
```json
{"statusCode":201,"status":"success","message":"Thank you. Your enquiry TK-1012 has been received and our team will call you soon.","data":{"ticket_no":"TK-1012"}}
```
Honeypot hit: `{"statusCode":201,"status":"success","message":"Thank you. Our team will call you soon."}` (no `data`).
Stored as a `support_tickets` row: subject `"Website enquiry: <service> - <name>"` (max 200), category `General`, priority `Medium`, status `Open`, message = `Name:/Phone:/Email:/Service:/Address:` lines + blank line + message (tags stripped); `ticket_no = "TK-" . (1000 + id)` (`api/contact_enquiry.php:72-90`).

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 405 | not POST | "Use POST for this request." | `api/public_helper.php:63-68` |
| 400 | JSON content-type with invalid body | "Invalid JSON body." | `api/public_helper.php:78-80` |
| 422 | validation | "Please check the highlighted fields." + `errors.{name,phone,email,service,address,message}` (e.g. "Enter a valid 10-digit mobile number.") | `api/contact_enquiry.php:34-55` |
| 429 | >= 3 enquiries with the same phone in the last 24 h | "We have already received your enquiries today. Our team will call you soon." | `api/contact_enquiry.php:14,66-70` |
| 500 | DB unavailable / PDOException | "Service temporarily unavailable. Please try again." / "Could not send your enquiry. Please try again or call us." | `api/public_helper.php:117-120`, `api/contact_enquiry.php:93-99` |

**Notes**
- **Daily limit:** `ENQUIRY_DAILY_LIMIT = 3` (`api/contact_enquiry.php:14`), counted as tickets with `subject LIKE 'Website enquiry:%' AND message LIKE '%Phone: <10 digits>%' AND created_at >= now - 1 day` (`api/contact_enquiry.php:66-67`). Per phone only - no IP limit.
- **BUG (user_id):** `getUserIdFromRequest()` returns an array `['user_id'=>..,'user'=>..]` (`api/auth_helper.php:89-101`); `api/contact_enquiry.php:63-64` does `$userId ? (int) $userId : null`, and `(int)` of a non-empty array is `1`, so every enquiry sent with a valid token is saved with `user_id = 1`. The website always sends the token when logged in (`zen-api.js:114-119`). Customer #1 then sees these tickets (other people's name/phone/email/address) in `support_tickets.php?action=list|get` (`api/support_tickets.php:57-60,94`), and the real customer does not.
- Timestamps use `.env` `APP_TIMEZONE` (default `Asia/Kolkata`) (`api/contact_enquiry.php:58-59`).

---

#### /api/support_tickets.php (common)
- **Auth:** Customer Bearer token **required** - `requireAuth()` -> 401 `{"statusCode":401,"status":"error","message":"Unauthorized or session expired. Please log in again."}` (`api/support_tickets.php:25`, `api/auth_helper.php:109-122`). Accounts with `users.status` in `inactive|blocked|disabled` -> 403 `"Your account is disabled. Please contact support by phone."` (`api/support_tickets.php:28-30`).
- **Content-Type:** `public_input()` (query + JSON or form body) (`api/support_tickets.php:23`).
- **CORS:** `public_cors('GET, POST, OPTIONS')` (`api/support_tickets.php:21`).
- **Default-action logic:** `action` = `?action=` / body `action`; if empty: POST -> `create`; GET with non-empty `id` -> `get`; otherwise `list` (`api/support_tickets.php:32-33`). Unknown action -> **404** `"Unknown action. Use list, get, create or reply."` (`api/support_tickets.php:184-185`). Each action enforces its method -> 405 (`api/support_tickets.php:92,119,124,168`).
- Any PDOException -> 500 `"Could not process the request. Please try again."` (`api/support_tickets.php:187-193`).
- **Ticket object** (`support_ticket_out`, `api/support_tickets.php:39-54`): `{id:int, ticket_no:string ("TK-"+1000+id fallback), subject, category, status ("Open"|"Closed"), booking_id:int|null (service_booking.ID), booking:string|null (booking code, or "#<ID>" when the code is empty), created_at, updated_at (falls back to created_at), closed_at|null}`.
- **Message object** (`support_conversation`, `api/support_tickets.php:68-87`): first item is the ticket's own message `{id:null, from:"customer", name:<customer full name or "Customer">, message, created_at}`, then `support_ticket_messages` rows with `sender_type IN ('customer','admin')` ordered by `created_at, id` as `{id:int, from:"customer"|"support", name ("Zen Home Experts Support" for admin), message, created_at}`. Internal admin remarks are never returned.
- Ticket statuses are only `Open` / `Closed` (admin side: `api/admin/modules/tickets.php:124-127,164`).

#### GET /api/support_tickets.php?action=list
- **Source:** `api/support_tickets.php:91-117`

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| action | query | string | no | `list` (default for GET without `id`) | `api/support_tickets.php:33` |
| status | query | string | no | case-insensitive `Open` / `Closed`; any other value is silently ignored (no filter) | `api/support_tickets.php:96-100` |
| page | query | int | no | default 1, min 1 | `api/public_helper.php:89-94` |
| limit | query | int | no | default 20, clamped 1-50 | `api/support_tickets.php:93` |

**Success** (200) - ordered by `COALESCE(updated_at, created_at) DESC, id DESC` (`api/support_tickets.php:109`)
```json
{"statusCode":200,"status":"success","message":"OK","data":{"items":[{"id":12,"ticket_no":"TK-1012","subject":"Technician did not arrive","category":"Booking","status":"Open","booking_id":38,"booking":"12-38","created_at":"2026-10-05 10:15:00","updated_at":"2026-10-05 12:00:00","closed_at":null,"admin_replies":1,"last_admin_reply":"2026-10-05 12:00:00"}],"pagination":{"page":1,"limit":20,"total_items":1,"total_pages":1}}}
```

#### GET /api/support_tickets.php?action=get&id=12
- **Source:** `api/support_tickets.php:118-121`

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| id | query (or body) | int | yes | `(int)` cast; must be the caller's own ticket | `api/support_tickets.php:120`, `api/support_tickets.php:56-66` |

**Success** (200)
```json
{"statusCode":200,"status":"success","message":"OK","data":{"ticket":{"id":12,"ticket_no":"TK-1012","subject":"Technician did not arrive","category":"Booking","status":"Open","booking_id":38,"booking":"12-38","created_at":"2026-10-05 10:15:00","updated_at":"2026-10-05 12:00:00","closed_at":null},"messages":[{"id":null,"from":"customer","name":"Test User","message":"Nobody came for my 10 AM slot.","created_at":"2026-10-05 10:15:00"},{"id":31,"from":"support","name":"Zen Home Experts Support","message":"Sorry, rescheduling now.","created_at":"2026-10-05 12:00:00"}]}}
```
**Errors:** 404 `"Ticket not found."` for missing/other customer's/invalid id (`api/support_tickets.php:62-64`).

#### POST /api/support_tickets.php?action=create
- **Source:** `api/support_tickets.php:123-165` (also the default for any POST without `action`)

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| subject | body | string | yes | 1-200 chars (checked before `strip_tags`) | `api/support_tickets.php:125,130-132` |
| message | body | string | yes | 1-2000 chars | `api/support_tickets.php:126,133-135` |
| category | body | string | no | case-insensitive, normalised `ucfirst(strtolower())`; one of `Booking, Payment, Service, Account, General`; default `General` | `api/support_tickets.php:18,127,136-138` |
| booking_id | body | string/int | no | booking code (`"12-38"`) or numeric ID, trimmed of space `"` `#`; alias key `unique_booking_id`; must belong to the caller | `api/support_tickets.php:128,140-147` |

**Success** (201) - message `"Ticket TK-1013 created. Our team will reply soon."`, `data` = `{ticket, messages}` as in `get` (`api/support_tickets.php:164-165`). Saved with priority `Medium`, status `Open`, tags stripped (`api/support_tickets.php:157-162`).
**Errors**
| HTTP | When | Message | Source |
|---|---|---|---|
| 422 | validation | "Please check the highlighted fields." + `errors.subject` / `errors.message` / `errors.category` ("Use one of: Booking, Payment, Service, Account, General.") / `errors.booking_id` ("This booking was not found in your account.") | `api/support_tickets.php:129-150` |
| 429 | >= 5 tickets by this user in the last 24 h (all tickets, incl. website enquiries linked to the user) | "You have raised several tickets today. Please wait for our reply or call support." | `api/support_tickets.php:19,151-155` |

#### POST /api/support_tickets.php?action=reply
- **Source:** `api/support_tickets.php:167-182`

| Field | In | Type | Required | Rules / default | Source |
|---|---|---|---|---|---|
| id | body (or query) | int | yes | own ticket, else 404 | `api/support_tickets.php:169` |
| message | body | string | yes | 1-2000 chars | `api/support_tickets.php:170-173` |

**Success** (201) - message `"Reply sent."`, `data` = `{ticket, messages}`. Inserts a `customer` message and **re-opens** the ticket (`status = 'Open', closed_at = NULL`) (`api/support_tickets.php:174-182`).
**Errors:** 404 "Ticket not found."; 422 `errors.message` ("Message is required." / "Message can be at most 2000 characters.") (`api/support_tickets.php:171-173`). No rate limit on replies.

---

#### POST /api/partner_registration.php

> **Updated 2026-10-07:** uploaded KYC files under `api/uploads/partners/` can no longer be fetched over HTTP (deny-all). Errors are generic (no DB text). Validation / rate limiting were not changed.
- **Auth:** None.
- **Content-Type:** JSON body only (`php://input`, `api/partner_registration.php:49-55`). Files are sent **inside the JSON as base64 data URLs** (`"data:<mime>;base64,<data>"`), not multipart.
- **Source:** `api/partner_registration.php:8-189`. OPTIONS -> 200 with CORS (`api/partner_registration.php:8-14`); CORS on all responses: `Allow-Origin: *`, `Allow-Headers: Content-Type, Authorization, X-Requested-With`, `Allow-Methods: POST, OPTIONS`; `Content-Type: application/json` (`api/partner_registration.php:16-19`).

**Request fields** (all body; DB column = same name, `api/partner_registration.php:85-139`)
| Field | Type | Required | Rules / default | Source |
|---|---|---|---|---|
| full_name, mobile, email | string | **yes** | must be non-empty after trim; **no format validation** | `api/partner_registration.php:58-65` |
| alternate_mobile, dob, gender, current_address, permanent_address, city, district, state, pincode, landmark, experience, training_institute, previous_company, time_slots, gst_number, account_holder_name, bank_name, account_number, ifsc, digital_signature, emergency_name, emergency_mobile, reference_name, reference_mobile | string | no | stored trimmed as sent; `""`/missing -> NULL | `api/partner_registration.php:145-161` |
| serviceable_areas, primary_category, sub_services, working_days | array or string | no | arrays are `json_encode`d; strings stored as-is | `api/partner_registration.php:153-154` |
| own_tools, purchase_kit, commission_accept, tds_accept, weekly_payout_accept, agreement_accept, rating_accept, cancellation_policy_accept, non_circumvention_accept, code_of_conduct_accept, criminal_record, competitor_platform, self_employment_declaration, background_verification_consent, final_consent | bool/int/string | no | `true`, `1`, `"1"`, `"yes"` (case-insens.) -> 1, anything else -> 0; missing/`""` -> NULL | `api/partner_registration.php:155-158` |
| photo, aadhaar_front, aadhaar_back, pan_file, police_verification, cancelled_cheque, certification_file | string | no | if it starts with `data:` and matches `^data:([^;]+);base64,(.+)$` it is decoded and written to `api/uploads/partners/<photo|aadhaar|kyc|bank|certification>/<field>_<uniqid>.<ext>` (ext `jpg/png/gif/webp/pdf`, else `bin`) and the DB stores the **relative** path `uploads/partners/<subdir>/<file>` (relative to `/api/`, i.e. public URL `https://zenhomeexperts.com/api/uploads/partners/...`); a non-`data:` value is stored as-is; no size limit | `api/partner_registration.php:23-41,68-82` |

**Success response** (HTTP **201**, but body says 200)
```json
{"statusCode":200,"status":"success","message":"Partner registration submitted successfully.","partner_id":41}
```
Row inserted into `service_partners` with `status = 'Pending'` (`api/partner_registration.php:164-180`); shows in Admin > Professionals (`DEPLOY.md:242`).

**Error responses**
| HTTP | When | Message | Source |
|---|---|---|---|
| 405 | not POST/OPTIONS | `{"statusCode":405,"status":"error","message":"Method not allowed. Use POST."}` | `api/partner_registration.php:43-47` |
| 400 | body not a JSON object | "Invalid JSON body." | `api/partner_registration.php:50-55` |
| 400 | required field empty | "Required field missing: <field>" | `api/partner_registration.php:58-65` |
| 500 | PDOException (e.g. unknown column) | "Registration failed." **plus `"error": <raw PDO message>`** | `api/partner_registration.php:181-189` |

**Notes**
- No rate limit, no duplicate-mobile check, no validation of mobile/email/IFSC/pincode.
- If a `data:` value fails base64 decoding, `saveBase64File` returns null and the original (possibly multi-MB) data URL is stored in the DB column (`api/partner_registration.php:35-40,79-80`).
- An array sent for a required field makes `trim()` throw a TypeError (uncaught 500) (`api/partner_registration.php:60`); an array for a non-JSON field (e.g. `time_slots`) is passed to PDO as-is (`api/partner_registration.php:160`).
- `api/uploads/.htaccess:1-17` only blocks script extensions and indexes; uploaded jpg/png/pdf KYC files are publicly downloadable by URL. Admin panel never displays these stored paths (no `photo`/`aadhaar_front` reference in `api/admin/modules/professionals.php` or `admin/professional-view.php`).
- `db.php` is included without output buffering (`api/partner_registration.php:21`); on DB failure `db.php` echoes `"Connection error: ..."` (`api/db.php:26`) and `$conn->prepare` at `api/partner_registration.php:171` throws an uncaught Error -> non-JSON 500.

---

#### POST /save_service_partner.php (site root, legacy duplicate of partner registration)
- **Auth:** None. Rate limited per IP.
- **Content-Type:** JSON body only (`save_service_partner.php:55-59`).
- **Source:** `save_service_partner.php:11-220`. Headers: `Content-Type: application/json`, `Allow-Origin: *`, `Allow-Headers: Content-Type`, `Allow-Methods: POST`; OPTIONS -> 204 (`save_service_partner.php:11-21`). No caller found in the repo (grep `save_service_partner` matches only the file itself).

**Request fields** (body; legacy names -> DB column)
| Field | Type | Required | Rules / default | Source |
|---|---|---|---|---|
| full_name | string | yes | tags stripped, >= 2 chars, truncated to 150 | `save_service_partner.php:97-100` |
| mobile | string | yes | digits only, `91` prefix (12 digits) removed, `^[6-9]\d{9}$` | `save_service_partner.php:84-94,101-104` |
| email | string | no | valid email, max 150 | `save_service_partner.php:105-108` |
| dob | string | no | formats `Y-m-d`, `d-m-Y`, `d/m/Y`, `Y/m/d`; age 16-100; stored `Y-m-d` | `save_service_partner.php:109-124` |
| pincode | string | no | `^\d{6}$` | `save_service_partner.php:125-128` |
| emergency_mobile | string | no | same rule as mobile | `save_service_partner.php:129-132` |
| ifsc | string | no | uppercased, `^[A-Z]{4}0[A-Z0-9]{6}$` | `save_service_partner.php:133-139` |
| aadhaar | string | no | digits, 12 -> `aadhaar_number` | `save_service_partner.php:140-146,183` |
| pan | string | no | uppercased, `^[A-Z]{5}\d{4}[A-Z]$` -> `pan_number` | `save_service_partner.php:147-153,184` |
| account | string | no | `^\d{6,20}$` -> `account_number` | `save_service_partner.php:154-157,186` |
| selectedCategories | array | no | joined with `,` -> `primary_category` (max 2000) | `save_service_partner.php:163-167,179` |
| gender, address (-> current_address), city, state, experience, institute (-> training_institute), bank (-> bank_name), emergency_name | string | no | tags stripped, truncated | `save_service_partner.php:174-192` |
| tools (-> own_tools), commission_accept, tds_accept, weekly_payout_accept, agreement_accept, criminal_record, competitor (-> competitor_platform) | bool/string | no | `true/1/"true"/"yes"/"y"/"on"` -> 1, else 0, missing -> NULL | `save_service_partner.php:72-82,182-195` |

Only columns that exist in `service_partners` are written (`SHOW COLUMNS`, `save_service_partner.php:200-201`). No file uploads.

**Success response** (HTTP 200): `{"status":"success"}` - no id (`save_service_partner.php:220`).

**Error responses** (`{"status":"error","message":"..."}`, `save_service_partner.php:23-28`)
| HTTP | When | Message | Source |
|---|---|---|---|
| 500 | DB unavailable | "DB Connection Failed" | `save_service_partner.php:31-34` |
| 405 | not POST | "Use POST" | `save_service_partner.php:36-38` |
| 429 | >= 10 calls from this IP in the last hour (every call is logged first in `legacy_access_log`) | "Too many requests. Please try again later." | `save_service_partner.php:16,41-53` |
| 400 | body not a JSON object | "Invalid JSON Data" | `save_service_partner.php:55-59` |
| 422 | validation | all messages joined with a space, e.g. "Mobile number is required. Enter a valid IFSC code." | `save_service_partner.php:158-161` |
| 409 | mobile already in `service_partners` | "This mobile number is already registered." | `save_service_partner.php:203-208` |
| 500 | PDOException | "Could not save your details. Please try again." | `save_service_partner.php:213-217` |

**Notes:** Different field names and response shape from `api/partner_registration.php`; Aadhaar/PAN/account numbers are stored in plain text when those columns exist (existence of `aadhaar_number`/`pan_number` is unknown - no schema in repo; grep finds them only at `save_service_partner.php:183-184`).

---

### NOT SUPPORTED

> **Updated 2026-10-07:** FCM / device token registration (`api/device_token.php`), app version / force-update (`api/app_config.php`), reading back your own review (`review` on every `my_bookings` item) and customer cancel (`api/cancel_booking.php`) **are now supported** - see section 8. Push *delivery* is still not wired (`push_send()` remains a stub), and reschedule, the notification inbox and address CRUD are still not supported. The rows below describe the code before that change.

| Feature | Verdict | Where I looked |
|---|---|---|
| Customer notifications list (in-app inbox) | **NOT SUPPORTED** | `customer_notifications` is written/read only by admin code: `api/admin/core/notify.php:256`, `api/admin/modules/notifications.php:60,94-95`, `api/admin/modules/tickets.php:152`, `api/admin/modules/bookings.php:96`, `cron/send_scheduled.php:43-45,109`. Grep `(?i)notification` over `api/**/*.php` matches only `api/admin/*` files - no customer endpoint. |
| FCM / device token registration | **NOT SUPPORTED** | `push_send()` is a stub that always returns `{"status":"Not sent","detail":"Push provider not configured (no FCM credentials / device tokens)."}` (`api/admin/core/notify.php:133-144`); its comment says "The customer apps do not register device tokens yet (no table for them)" (`api/admin/core/notify.php:135-137`). Grep `(?i)fcm|device_token|push_token|firebase|onesignal|apns` (excluding vendor) matches only `api/admin/core/notify.php` and `api/admin/modules/tickets.php:150`. The full table list in `database/check_table_names.php:18-27` has no token table, so a new table (e.g. `user_id, token, platform, updated_at`) plus a register endpoint would be needed. |
| App version / force-update | **NOT SUPPORTED** | `api/site_settings.php` returns `site_settings_public()` keys defined at `api/site_settings_helper.php:19-53` (`play_store_url`, `app_store_url`, `app_qr_link`, ... ) - no version keys. Grep `(?i)app_version|min_version|force_update|latest_version|version_code|build_number|minimum_version` -> no matches; `(?i)version` in `api/` only hits `api/phonepe_client.php:4,14` and a comment in `api/admin/fetch_users.php:27`. |
| Customer saved-address CRUD | **NOT SUPPORTED** (note only; profile covered elsewhere) | No address table: `api/admin/modules/customers.php:136-157` derives "addresses" from `users.address` + distinct `service_booking.location`. Customer side has only the single `address` field in `api/update_profile.php:59` and per-booking `location`/`landmark` in `api/book_appointment.php:41-42`. Grep `user_addresses|customer_addresses|address_book` -> no matches. |
| Read back own review / "already rated" flag | **NOT SUPPORTED** | `ratings_feedback` is used by customers only in `api/rating.php`; `api/my_bookings.php:34-39` selects no review columns. |

---

---

## 6. Backend gaps

See `docs/backend_gaps.md` (ranked P1/P2/P3, with file:line and suggested minimal fixes).

---

## 7. Legacy endpoint map

> Note: for the final payment status the app should keep `paymentConfirmation.php` (it syncs the DB/cart/SMS). `payment_verify.php` is read-only - see section 4 and the quick answers.
>
> **Updated 2026-10-07:** where this table and section 8 disagree, section 8 wins. In short: login tokens last 30 days; register sends `statusCode` 201; `book_appointment.php` always needs the token, answers errors with real 4xx codes and ignores the client amount; `payments.php` ignores `amount`, needs a `ZC-` order id and returns `{statusCode,status,message,success,payment_url,transaction_id,data}` with errors also carrying `statusCode`/`message`; `payment_verify.php` and `paymentConfirmation.php` need the token (own transactions only); `save_transaction.php` now answers **410 Gone**; `update_profile.php` also returns `data`; `cart.php` POST with a bad body answers 422 instead of wiping the cart. `LEGACY_STRICT_AUTH` was not changed (still `false` by default).

Intended replacements are barely documented: `api/legacy_access.php:3-6` lists the guarded legacy endpoints (user_orders, order_details, serviceBooking, serviceComplete, checkSlot, save_transaction, fetch-transaction, user_transaction, paymentConfirmation, payementInitiate, fetchCategory, book_appointment); `DEPLOY.md:56,170-191` and `.env.example:37-39` describe `LEGACY_STRICT_AUTH` (default false = no auth; true = token 401 / user_id 403 / other users' rows 404). No file contains "replaces"/"deprecated" mappings for these endpoints; the only explicit hints are `api/catalog_categories.php:12` ("api/category.php is unchanged and still serves the apps' list") and `api/catalog_services.php:11-12` (send `pack_id` to `book_appointment.php`). Mappings below are inferred from code.

| Legacy endpoint | What it is today | New endpoint | Safe to switch | Reason |
|---|---|---|---|---|
| `api/login.php` | **Current** (not legacy): password -> SMS OTP -> token via `createSession()`; not in the legacy list | keep `login.php` | n/a (keep) | Token lifetime is **12 h** (`api/login.php:84-85,104`), not the 30-day default, so the app must handle 401 re-login. Step 1 success uses `"status":"otp_sent"` (`api/login.php:175-179`). |
| `api/register.php` | **Current**; no token returned | keep `register.php` | n/a (keep) | HTTP 201 with body `statusCode` 200 (`api/register.php:90-96`); app must then call login. |
| `api/book_appointment.php` | Legacy-guarded but **still the only booking-create endpoint** (website uses it) | keep `book_appointment.php` (send token + `items[{pack_id,quantity}]` + optional `coupon_code`, `payment_method`) | n/a (keep) | With `LEGACY_STRICT_AUTH=true` a token is required (`api/book_appointment.php:5-6,52`). Missing fields return HTTP 200 `{"status":"error","message":"Invalid input"}` (`api/book_appointment.php:32-35`). |
| `api/serviceBooking.php` | Old booking create (client sets status, price, technician; `SERVICY-00038` codes) | `book_appointment.php` | **Partly** | Different request (`subcategories` string vs array of `{subcategory}`; no `created_at/status/price/technician_name`; price from `items` server-side) and response (`unique_booking_id` `"<user_id>-<ID>"` vs `"SERVICY-xxxxx"`, HTTP 200 vs 201, no `subcategories` echo) (`api/serviceBooking.php:15-20,72,80-86` vs `api/book_appointment.php:32,129,162-175`). |
| `api/serviceComplete.php` | Customer enters technician OTP to complete a booking | none | **No** | No customer replacement; completion is admin-only (`api/admin/bookings.php?action=update_status`, status `Completed` = raw `Service Complete`, `api/admin/core/status.php:22,35`). Keep using it if the OTP flow is needed. |
| `api/checkSlot.php` | Free slots for a date (8 hard-coded 1-hour slots) | none | **No** | Grep `(?i)slot` in `api/*.php` finds no new availability endpoint (`api/checkSlot.php:26-35`). |
| `api/user_orders.php` | Transactions + `orders` items by `user_id` (no auth by default) | `order_history.php` (token) | **Partly** | Same query, but token required (`api/order_history.php:17-18`); keys differ: `payment_method`->`payment_mode`, `date`->`order_date`, `shipping`->`shipping_address`; no `data.user_id`; envelope `success` -> `statusCode/status` (`api/user_orders.php:63-96` vs `api/order_history.php:47-71`). For service bookings use `my_bookings.php`. |
| `api/order_details.php` | One `TXN_XXXXXXXXXX` transaction with items, schedule, PhonePe data | `my_bookings.php?id=<booking code>` (bookings only) | **No** | No transaction-detail endpoint; `ZC-` website orders are rejected by the id regex (`api/order_details.php:28-30`); `my_bookings.php` returns a booking shape (status, payment_status, technician) not order/payment details (`api/my_bookings.php:55-76`). |
| `api/order_history.php` | **Current** (token required) | keep | n/a (keep) | Lists `transactions`, not bookings (`api/order_history.php:24-38`). |
| `api/fetchCategory.php` | Returns only the **first** `saverpacks.subcategory` for `category_id` | `catalog_subcategories.php?category=` or `catalog_services.php?category=` | **Yes** (shape change) | New endpoints return full lists `data.items[]` with proper 404/422 codes; old returns `{"status","data":{"subcategory":..}}` with HTTP 200 errors (`api/fetchCategory.php:21-46`). Both public. |
| `api/category.php` | Category list `{status, categories:[{CATEGORY_ID,NAME,IMAGE}]}` | `catalog_categories.php` | **Partly** | Keys become lowercase (`id, slug, name, image, image_url...`) under `data.items` + `pagination`; images: old `IMAGE` is the raw stored value (relative/bare filename), new `image_url` is absolute (`api/catalog_categories.php:25-30`); responses carry ETag/304 (`api/public_helper.php:47-61`) - app must handle 304. `?include_disabled=1` has no equivalent (`api/category.php:11`). |
| `api/fetchCategoryDetails.php` | `SELECT *` from `saverpacks` by `packid` (incl. disabled) | `catalog_services.php?ids=<pack_id>` | **Partly** | New returns `data.items[]` with mapped keys (`pack_id, name, price, mrp, image_url...`), excludes disabled services (`api/catalog_services.php:7-13`); old returns raw DB columns as `data` object (`api/fetchCategoryDetails.php:18-31`). |
| `api/payementInitiate.php` | PhonePe order for a client-supplied amount, `TXN_` id, any `user_id` | `payments.php` POST `{amount, order_id:"ZC-<booking code>", message?}` | **Partly** | Token now required (401 `{"error":"Unauthorized","message":"Please log in again."}`, `api/payments.php:30-34`); success shape identical `{success, payment_url, transaction_id}` (`api/payments.php:85-89`); for `ZC-` orders the amount comes from the booking (`api/payments.php:40-51`); `transaction_id` = the `order_id` sent; errors use `{error[,message]}` not `{success:false,error}`. |
| `api/paymentConfirmation.php` | Checks PhonePe status **and** updates `transactions`, clears cart, sends confirmation SMS | `payment_verify.php` (read-only) + `payment_webhook.php` (server sync) | **Partly** | `payment_verify.php` does **not** update the DB/cart/SMS (`api/payment_verify.php:33-51`) - relies on the PhonePe webhook; shape differs: `{status:"success|failed|pending", state, data{transaction_id, amount (rupees), payment_details}}` vs `{success, merchantTransactionId, transactionId, state, amount (paise), paymentDetails}` (`api/paymentConfirmation.php:50-57`). `payment_verify.php` has no auth at all. |
| `api/save_transaction.php` | Client upserts a transaction with any status | none (stop calling) | **Yes** (drop it) | `payments.php` already inserts the pending row (`api/payments.php:78-83`) and the webhook / confirmation set the final status; client-set status is unsafe (`api/save_transaction.php:43-45`). |
| `api/fetch-transaction.php` | PhonePe status read | `payment_verify.php` | **Partly** | Same status values (`success/failed/pending`) but envelope `{success,data}` -> `{status,state,data}`; `payment_mode`, `timestamp`, `raw_response` not returned; `payment_details` instead (`api/fetch-transaction.php:34-47` vs `api/payment_verify.php:43-51`). |
| `api/user_transaction.php` | Last 100 transactions by `user_id` + `receipt_url` | `order_history.php` | **Partly** | Token required; `date` -> `order_date`; no `count`, no `receipt_url` (receipt links point to legacy `order_details.php`, `api/user_transaction.php:49-54`); paginated (max 50) instead of 100 rows. |
| `api/fetch_user.php` | **Current** (token) | keep | n/a (keep) | `{statusCode,status,data:{id,first_name,...}}` (`api/fetch_user.php:17-33`). |
| `api/update_profile.php` | **Current** (token) | keep | n/a (keep) | Returns `user` (not `data`) (`api/update_profile.php:154-159`). |
| `api/cart.php` | **Current** (token, GET/POST/DELETE) | keep | n/a (keep) | `items` at top level, not under `data` (`api/cart.php:83-87`). |

---

## 8. Changes on 2026-10-07: new endpoints and updated contracts (authoritative)

All examples use dummy values. "Token" = the customer login token from `login.php`, sent as `Authorization: Bearer <token>` (or `X-Auth-Token`). Every response below has HTTP code == `statusCode`. Old top-level fields are kept for the website and the old app; new clients should read `data`. Common errors on every token endpoint: 401 (missing / expired token), 405 (wrong method), 500 / 503 (generic JSON server / DB error).

### 8.1 Auth

**Login step 1** - `POST /api/login.php` `{"phone":"9876543210","password":"secret12"}`
```json
{"statusCode":200,"status":"otp_sent","message":"OTP sent to your mobile number. Enter it to complete login.","data":{"expires_in":300,"resend_after":60}}
```
Errors: 400 (missing phone/password, invalid phone), 401 `Invalid phone or password.`, 403 (deactivated), 429 `Please wait 42 seconds before requesting another OTP.` / `Too many OTP requests. Please try again in an hour.` (+ `Retry-After` header and `data.retry_after`), 502 `Failed to send OTP. Please try again.`

**Login step 2** - `POST /api/login.php` `{"phone":"9876543210","otp":"123456"}`
```json
{"statusCode":200,"status":"success","message":"Login successful.",
 "token":"<64 hex>","user":{"id":17,"first_name":"Test","last_name":"User","email":"test@example.com","phone":"9876543210","address":"12 Test Street","photo":"","photo_url":null,"status":"0"},"expires_in_hours":720,
 "data":{"token":"<64 hex>","user":{"...":"same"},"expires_in":2592000,"expires_at":"2026-11-06T10:00:00+05:30"}}
```
Errors: 400 `OTP not found or expired...` / `OTP expired...` / `Too many wrong tries. Please request a new OTP.` / wrong OTP with `data.attempts_left` (5 tries per code), 401 `User not found.`, 403 deactivated.

**Register** - `POST /api/register.php` `{"firstname":"Test","lastname":"User","email":"test@example.com","phone":"9876543210","password":"secret12"}` (`first_name`/`last_name` also accepted; last name may be blank)
```json
{"statusCode":201,"status":"success","message":"Registration successful. You can now log in with your phone and password.","user_id":42,"data":{"user_id":42}}
```
Errors: 400 with `errors` (`email`, `password` 6-72, `phone` `^[6-9]\d{9}$`) or `User with this email or phone already exists.`, 429 (10 attempts/hour per IP). No token: call login next.

**Update profile** - `POST|PUT|PATCH /api/update_profile.php` (token) `{"first_name":"Test","address":"New address"}`; password change: `{"current_password":"old","password":"newpass1"}`.
Response: `{"statusCode":200,"status":"success","message":"...","user":{profile},"data":{profile}}`. Errors 400 with `errors` (empty first_name/email/phone, invalid/duplicate email or phone, password 6-72, wrong current password). A password change deletes every other session of the user.

**Logout** - `POST /api/logout.php` (token) `{"fcm_token":"<optional FCM token of this device>"}` -> `{"statusCode":200,"status":"success","message":"Logged out successfully."}`. Cart is kept.

### 8.2 Booking

**Slots** - `GET /api/checkSlot.php?date=2026-11-11` or `POST {"date":"2026-11-11"}` (no token)
```json
{"statusCode":200,"status":"success","message":"Available slots fetched successfully","data":["10:00 AM - 11:00 AM","12:00 PM - 01:00 PM","05:00 PM - 06:00 PM"]}
```
400 missing date, 422 invalid date. Slots held by non-cancelled bookings and (for today) slots that already started are left out.

**Create booking** - `POST /api/book_appointment.php` (token)
```json
{"category":"AC Service","subcategories":"Foam-Jet AC Service","date":"2026-11-11","service_slot":"11:00 AM - 12:00 PM",
 "location":"12 Test Street, Test Area, Bengaluru 560001","landmark":"Near test park",
 "items":[{"pack_id":1,"quantity":1}],"coupon_code":"","payment_method":"cash"}
```
`payment_method`: `online` (then call `payments.php`) or anything else = cash / pay after service.
```json
{"statusCode":200,"status":"success","message":"Service booking added successfully",
 "id":81,"unique_booking_id":"17-81","amount":1400,"discount":0,"amount_payable":1400,"payment_method":"cash",
 "data":{"id":81,"unique_booking_id":"17-81","amount":1400,"discount":0,"amount_payable":1400,"payment_method":"cash","booking":{"...":"my_bookings item"}}}
```
Errors: 400 body not JSON; 422 with `errors` (`category`, `service_slot`, `location`, `date` invalid/past, `service_slot` already started, `items` missing / no `pack_id` / unknown pack, `coupon_code` invalid); 409 `Sorry, this time slot was just booked. Please pick another slot.`; 500 generic. (HTTP 200, not 201, is kept for the website.)

**My bookings item** (`GET /api/my_bookings.php`, also returned by `book_appointment` and `cancel_booking`)
```json
{"id":81,"booking_id":"17-81","category":"AC Service","services":"Foam-Jet AC Service","date":"2026-11-11","slot":"11:00 AM - 12:00 PM",
 "address":"12 Test Street","landmark":"Near test park","status":"New","status_label":"Booking Received",
 "amount":1400,"gross_amount":1400,"discount":0,"coupon_code":null,"payment_method":"Pay after service","payment_status":"Pending",
 "refund_status":null,"technician":null,"cancel_reason":null,"can_cancel":true,"can_review":false,
 "review":null,"created_at":"2026-10-07 11:12:00","completed_at":null,"cancelled_at":null}
```
`review` when rated: `{"rating":5,"feedback":"Great","status":"Pending|Approved|Rejected"}`. `can_cancel` = status New/Pending/Assigned; `can_review` = Completed. `status_label` text comes from the server; show it as-is.

### 8.3 NEW - App config

`GET /api/app_config.php[?platform=android|ios&version=1.2.0]` (no token; ETag / 304 supported)
```json
{"statusCode":200,"status":"success","message":"OK","data":{
 "min_version_android":"1.0.0","min_version_ios":"1.0.0","latest_version":"1.0.0","force_update":false,
 "maintenance":false,"maintenance_message":"","support_phone":"+910000000000","support_email":"support@example.com",
 "play_store_url":null,"app_store_url":null,
 "update_required":false,"update_available":false}}
```
`update_required` / `update_available` only appear when `platform` and `version` are sent (422 if either is invalid). `update_required` = `force_update` is on and `version` < that platform's minimum. App rule: if `maintenance` show `maintenance_message`; if `update_required` block and open the store URL; if `update_available` offer an update. Admin edits the values in **Site Settings > App** (keys `app_min_version_android`, `app_min_version_ios`, `app_latest_version`, `app_force_update`, `app_maintenance`, `app_maintenance_message`); they are not returned by `site_settings.php`.

### 8.4 NEW - Cancel booking

`POST /api/cancel_booking.php` (token) `{"id":"17-81"}` (`unique_booking_id`, or the numeric `ID`, also accepted)
```json
{"statusCode":200,"status":"success","message":"Booking cancelled.","data":{"...":"my_bookings item with status Cancelled, cancel_reason \"Cancelled by customer\", can_cancel false"}}
```
For a paid online booking the message ends with ` Your refund will be processed shortly.` and `refund_status` becomes `Refund Pending` (an admin refunds it). Errors: 422 bad id, 404 not your booking, 409 `This booking is already cancelled.` / `This booking can no longer be cancelled online. Please contact support.` (status Ongoing / Completed) / `This booking was just updated...`. Same effect as Admin > Bookings > Cancel: coupon use released, history row (actor "Customer"), cancellation notification. **Reschedule is not supported.**

### 8.5 Payment

**Start** - `POST /api/payments.php` (token) `{"order_id":"ZC-17-81"}` (`amount` is ignored; the booking price is charged)
```json
{"statusCode":200,"status":"success","message":"Payment started.","success":true,
 "payment_url":"https://mercury.phonepe.com/...","transaction_id":"ZC-17-81","amount":1400,
 "data":{"payment_url":"https://mercury.phonepe.com/...","transaction_id":"ZC-17-81","amount":1400}}
```
Errors (all `{statusCode,status:"error",success:false,error,message}`): 401, 422 `order_id must be "ZC-" followed by your booking ID.` / `Nothing to pay for this booking.`, 404 booking not yours, 409 `This booking is cancelled...` / `This booking has already been paid.` / `This payment belongs to another account.`, 500 payment not configured, 502 gateway error.
PhonePe returns the browser to `PHONEPE_REDIRECT_URL?transactionId=ZC-17-81`; `payment_callback.php` checks PhonePe, records the result and redirects to `PAYMENT_CALLBACK_BASE_URL?payment=success|failed|pending&txn=ZC-17-81`. Treat that only as "user came back" and confirm with:

**Confirm (records the result)** - `GET /api/paymentConfirmation.php?transactionId=ZC-17-81` or `POST {"transactionId":"ZC-17-81"}` (token, own transaction)
```json
{"statusCode":200,"status":"success","message":"Payment status: success.","success":true,
 "payment_status":"success","merchantOrderId":"ZC-17-81","merchantTransactionId":"OMO123...","transactionId":"OMO123...",
 "state":"COMPLETED","amount":140000,"amount_rupees":1400,"paymentDetails":[],
 "data":{"...":"same fields"}}
```
`payment_status`: `success` | `failed` | `pending` (a success / refunded row never goes back). `amount` is paise. Errors: 400 missing id, 404 not yours / unknown, 502 PhonePe unreachable.

**Read-only check** - `GET|POST /api/payment_verify.php` `transaction_id=ZC-17-81` (token, own transaction) -> `{"statusCode":200,"status":"success|failed|pending","message":"...","payment_status":"...","state":"COMPLETED","data":{"transaction_id":"OMO123...","merchantOrderId":"ZC-17-81","amount":1400,"amount_rupees":1400,"payment_details":[]}}`. Does not change the database.

**Removed** - `POST /api/save_transaction.php` -> `410 {"statusCode":410,"status":"error","success":false,"message":"This endpoint has been retired. Payments are confirmed with paymentConfirmation.php.","error":"Gone"}`. The server owns transaction status.

### 8.6 Rating

`POST /api/rating.php` (token) `{"unique_booking_id":"17-81","rating":5,"feedback":"Great service"}` (`feedback` optional, max 2000)
```json
{"statusCode":201,"status":"success","message":"Data saved successfully","review_id":9,"review_status":"Pending",
 "data":{"review_id":9,"review":{"rating":5,"feedback":"Great service","status":"Pending"}}}
```
Errors: 400 missing fields, 422 rating not 1-5 / feedback too long, 404 not your booking, 409 `You can rate a booking once the service is completed.` Rating again updates the review and puts it back to Pending (unless `REVIEWS_AUTO_APPROVE=true`).

### 8.7 NEW - Device (FCM) token

`POST /api/device_token.php` (token **in a header**: the body `token` field here is the FCM token)
```json
{"token":"<FCM registration token>","platform":"android","app_version":"1.0.0"}
```
(`fcm_token` is accepted instead of `token`.) -> `{"statusCode":200,"status":"success","message":"Device registered.","data":{"platform":"android","app_version":"1.0.0"}}`
Call it after login and whenever FCM rotates the token. A token already registered to another account moves to this one; each customer keeps at most 10 devices (oldest dropped).
Unregister: `DELETE /api/device_token.php` `{"token":"<FCM token>"}` (or `?fcm_token=`), or `POST {"action":"remove","token":"<FCM token>"}` -> `{"statusCode":200,"status":"success","message":"Device unregistered.","data":{"removed":1}}`. `logout.php` with `fcm_token` does the same.
Errors: 422 `token` (20-255 chars of `A-Z a-z 0-9 : _ - .`), `platform` (android/ios), `app_version`. **No push is sent yet** (delivery is not wired).

