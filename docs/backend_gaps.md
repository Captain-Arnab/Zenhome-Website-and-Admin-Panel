# Backend gaps that affect the mobile app

Static code review only (nothing was executed or called). Each item gives `file:line`, the problem and a suggested minimal fix. Line numbers refer to the code **before** the 2026-10-07 fixes.
Priority: **P1** = blocks the app, or is a security / data-exposure / money risk. **P2** = wrong or inconsistent behaviour the app must work around. **P3** = cleanup / minor.
Full endpoint details: `docs/app_backend_contract.md` (section 8 = current contracts). What changed and how it was tested: `docs/backend_changes.md`.

## Status after the 2026-10-07 fixes

**FIXED** = done and tested locally. **PARTLY** = the app-blocking part is fixed, the rest is noted. **OPEN** = not changed (reason given).

| Gap | Status | Note |
|---|---|---|
| P1-1 client sets price | **FIXED** | `items[].pack_id` required (422), server price only; `payments.php` charges the booking price only. |
| P1-2 book as anyone | **FIXED** | `requireAuth`, token user only. |
| P1-3 mark "Paid" without paying | **FIXED** | `save_transaction.php` returns 410 (still logged). |
| P1-4 payment status without login | **PARTLY** | `payment_verify` + `paymentConfirmation`: token + ownership. `user_transaction.php` stays behind `LEGACY_STRICT_AUTH` (not enabled, as instructed). |
| P1-5 legacy endpoints open | **OPEN** | `LEGACY_STRICT_AUTH` deliberately left `false` (instruction). Legacy files now send CORS + generic errors only. Turn it on once the old app is retired. |
| P1-6 enquiries filed under customer #1 | **FIXED** | Verified in the browser: enquiry linked to the signed-in user. Older tickets wrongly linked to user 1 are **not** re-assigned (data, not code). |
| P1-7 overwrite any review | **FIXED** | `requireAuth`, own Completed bookings only. |
| P1-8 OTP brute force / SMS bombing | **FIXED** | 5 tries, 60 s gap, 3/hour, `random_int`, hashed OTP (`login_otps` table). |
| P1-9 SMS credentials in code | **PARTLY** | Moved to env keys. **Rotate the gateway password** (it is in git history). HTTPS not switched: gateway HTTPS support unverified (set `SMS_GATEWAY_URL` to an https URL if it works). |
| P1-10 public settings expose staff phones | **FIXED** | `Admin Alerts` and `App` groups are private. |
| P1-11 partner KYC files public | **PARTLY** | `api/uploads/partners` is deny-all (verified 403; nothing in admin links to these files). No size / rate limit added to `partner_registration.php`. |
| P1-12 no native PhonePe SDK flow | **OPEN** | Out of scope; app uses web checkout (`payment_url`), which now returns `?transactionId=` and a verified callback. |
| P1-13 catalog price != charged price | **FIXED** | Price, mrp and status from the `MIN(packId)` row. |
| P2-1 return URL without order id | **FIXED** | `?transactionId=` appended; callback verifies with PhonePe. |
| P2-2 12 h session | **FIXED** | `LOGIN_TOKEN_DAYS`, default 30. |
| P2-3 register validation | **PARTLY** | Email, password 6-72, phone rule, 10/hour per IP, `statusCode` 201. No phone OTP verification at sign-up (not requested). Validation errors stay 400 (website reads them). |
| P2-4 phone rule differs | **FIXED** | `zc_normalize_phone()` everywhere. |
| P2-5 non-JSON DB failure / leaked errors | **FIXED** | JSON 503; raw exception text removed from the listed files. |
| P2-6 empty-body 500 | **FIXED** | API-scoped exception handler returns JSON 500. |
| P2-7 HTTP 200 on errors | **FIXED** | `book_appointment`, `paymentConfirmation`, `fetchCategory`, `fetchCategoryDetails` (400/404 now); `partner_registration` body `statusCode` now 201. |
| P2-8 mixed envelopes | **PARTLY** | Every touched endpoint has `statusCode` (= HTTP) and `data`; legacy `{success,...}` keys kept for old clients. |
| P2-9 no JSON Content-Type | **PARTLY** | Fixed for `serviceComplete`, `fetchCategory`, `fetchCategoryDetails` (via `public_cors`). `payment_webhook.php` 500 path untouched (server-to-server only). |
| P2-10 missing CORS | **FIXED** | `public_cors()` on every listed endpoint. |
| P2-11 image URLs | **PARTLY** | URLs built from `APP_URL`; `photo_url` added to the user object. No photo upload; `home_packages` unchanged. |
| P2-12 misleading payment status | **FIXED** | `merchantOrderId`, `amount_rupees`, JSON body accepted. |
| P2-13 payments.php re-initiation | **PARTLY** | 409 already-paid / other user, `ZC-` only, booking price only, `Throwable` caught, no downgrade of success. Paid amount is still not compared with `transactions.amount` in webhook / confirmation. |
| P2-14 SANDBOX runs PRODUCTION | **FIXED** | SANDBOX/UAT -> UAT; unknown value throws. |
| P2-15 payment method not stored | **FIXED** | `service_booking.payment_method` = `Online` / `Cash`. |
| P2-16 slot validation | **FIXED** | Date validated, cancelled excluded, elapsed slots hidden, re-check under a lock (409). Capacity still 1 booking per slot. |
| P2-17 listed service fails / slug 404 | **FIXED** | First-pack status; `slug=service-<id>`. |
| P2-18 cart / orders quirks | **PARTLY** | Bad cart POST = 422; logout keeps the cart. `order_history` / `order_details` unchanged (app should use `my_bookings`). |
| P2-19 update_profile lockout | **FIXED** | Empty required fields rejected, password 6-72, other sessions deleted. |
| P2-20 rating gaps | **FIXED** | Completed only; `review` on `my_bookings` items. |
| P2-21 partner sign-up | **OPEN** | Only generic errors + deny-all uploads; validation port not done. |
| P2-22 SMS result unchecked | **FIXED** | HTTP code + body checked, timeouts, `random_int`, hashed OTP. |
| P2-23 missing app features | **PARTLY** | Added `cancel_booking.php`, `device_token.php`, `app_config.php`. Still missing: reschedule (not now, as instructed), notification inbox, push delivery (not wired, as instructed), address CRUD, token refresh, photo upload. |
| P3-1 no method checks | **FIXED** | 405 on login / logout (POST only) and update_profile (POST/PUT/PATCH). |
| P3-5 login step 2 "User not found" 400 | **FIXED** | 401. |
| P3-6 array input TypeError | **PARTLY** | `update_profile` guarded; `partner_registration` unchanged. |
| P3-9 checkSlot two connections / trailing output | **FIXED** | Rewritten. |
| P3-10 webhook not monotonic | **FIXED** | Final states never left; test case added (23/23 pass). |
| P3-11 cart cleared on every poll | **FIXED** | Only on the transition to success. |
| P3-12 `refunded` via save_transaction | **FIXED** | Endpoint retired (410). |
| P3-13 `limit=0` division by zero | **FIXED** | `payments.php` GET and `user_orders.php` clamp to >= 1. |
| P3-2, P3-3, P3-4, P3-7, P3-8, P3-14 to P3-20 | **OPEN** | Not app-blocking; unchanged. |


## P1

1. **Client can set the booking price.** `api/book_appointment.php:64-73`, `api/coupon_helper.php:150-157`, `api/payments.php:40-51`.
   - Problem: when `items` is omitted, the client `amount` becomes `service_booking.price`. Lines with a missing or zero `pack_id` are skipped, so the server prices only part of the cart. `payments.php` then charges this stored price, so a ₹1 online payment for any service is possible.
   - Fix: require `items` with a valid `pack_id` on every line, and return 422 for amount-only requests or lines with a zero id.
2. **Anyone can book as any customer.** `api/book_appointment.php:52-62`.
   - Problem: the token is optional and an invalid or expired token is ignored. Only a *valid* token for another user gets a 403. Anyone can create bookings, and use up per-user coupon allowances, for any `user_id`.
   - Fix: `requireAuth($conn)` and use the token's `user_id`, ignoring the body value.
3. **A booking can be marked "Paid" without paying.** `api/save_transaction.php:43-68`, `api/my_bookings.php:46-47`, `api/admin/core/repo.php:18`.
   - Problem: anyone (no auth by default, or the owner in strict mode) can POST `{"transaction_id":"ZC-<id>","status":"success"}`. Customer and admin screens derive "Paid" from `transactions.status`.
   - Fix: disable `save_transaction.php` (return 410), or ignore the client `status` so it stays server-controlled.
4. **Payment status can be read without login.** `api/payment_verify.php:20-50` (no guard at all), `api/paymentConfirmation.php:28-38`, `api/user_transaction.php:29-33`.
   - Problem: with `LEGACY_STRICT_AUTH=false` (the default) anyone can query the guessable `ZC-<user_id>-<booking ID>` and get the amount and PhonePe `paymentDetails`, or list any user's transactions.
   - Fix: `requireAuth()` plus an ownership check on `payment_verify.php` and `paymentConfirmation.php`, then turn on `LEGACY_STRICT_AUTH=true` once the apps send tokens.
5. **Legacy endpoints are open while `LEGACY_STRICT_AUTH=false`.** `api/legacy_access.php:130-133`.
   - Problem: anyone can read or write customer data:
     - `api/user_orders.php:27` lists any user's orders and addresses.
     - `api/order_details.php:86-93` returns any `TXN_` order, with raw PhonePe data where only the card number is masked.
     - `api/fetch-transaction.php` returns the raw gateway response.
     - `api/serviceBooking.php:15-61` inserts a booking with any client-set status, price and technician.
     - `api/serviceComplete.php:27-51` accepts unlimited 6-digit OTP guesses to complete any booking.
     - `api/payementInitiate.php` creates PhonePe orders for any amount and any user.
   - Fix: move the app to the replacement endpoints (see the legacy map in the contract), then set `LEGACY_STRICT_AUTH=true`. Retire `serviceBooking.php` and `payementInitiate.php`.
6. **Logged-in website enquiries are filed under customer #1.** `api/contact_enquiry.php:63-64`.
   - Problem: `getUserIdFromRequest()` returns an array, and `(int)` of a non-empty array is `1`. The website always sends the token when logged in (`zen-api.js:114-119`). So customer #1 sees other people's name, phone, email and address through `support_tickets.php`, and the real customer never sees their own ticket.
   - Fix: `$auth = getUserIdFromRequest($conn); $userId = $auth ? (int) $auth['user_id'] : null;`
7. **Anyone can overwrite any customer's review.** `api/rating.php:57-66, 75, 83-89`.
   - Problem: the token is optional, so `user_id` plus a guessable booking code (`"<user_id>-<ID>"`, `api/book_appointment.php:129`) overwrites the review. This resets moderation, hiding an approved or featured review. With `REVIEWS_AUTO_APPROVE=true` it publishes the text at once while `featured` stays 1 (homepage: `api/site_content.php:225`).
   - Fix: `requireAuth()` and use the token's `user_id`.
8. **Login OTP can be brute-forced, and OTP SMS sends are unlimited.** `api/login.php:60-64` (a wrong OTP is neither counted nor invalidated), `api/login.php:153-173` (every step-1 call sends a paid SMS: no cooldown, no hourly cap).
   - Problem: the 6-digit code can be guessed within its 5-minute window, and the SMS send can be abused (SMS bombing).
   - Fix: reuse the attempt counter from `password_reset.php` (`api/password_reset.php:117-125`) and its 60-second gap / 3-per-hour limit (`api/password_reset.php:65-74`).
9. **SMS gateway credentials are hard-coded and sent over plain HTTP.** `api/sms_sender.php:6-7, 23-39`.
   - Problem: the username, password and DLT ids are in source control and travel in a cleartext query string.
   - Fix: move them to env keys, rotate the password, and use HTTPS if the gateway supports it.
10. **Public settings expose staff phone numbers.** `api/site_settings_helper.php:52-53, 184-207`.
    - Problem: `site_settings_public()` returns every key, including `admin_alert_mobiles` and `admin_alert_enabled`, from the unauthenticated `api/site_settings.php`.
    - Fix: skip the `Admin Alerts` group in `site_settings_public()`.
11. **Partner KYC uploads are public and unlimited.** `api/partner_registration.php:23-41, 68-82`.
    - Problem: base64 Aadhaar, PAN and cheque images are accepted with no auth, size limit or rate limit. They are saved under web-readable `api/uploads/partners/` (the `.htaccess` there blocks scripts only) with predictable `uniqid()` names.
    - Fix: add a deny-all rule for `api/uploads/partners` and serve the files through an admin-only script. Add a per-IP rate limit and a maximum decoded size.
12. **No PhonePe native-SDK flow.** `api/phonepe_client.php:12-24`, `api/payments.php:85-89`.
    - Problem: there is no "create SDK order" endpoint that returns orderId + token, and the vendored SDK has no such method. `payments.php` returns only `payment_url` and `transaction_id`.
    - Fix: the app opens `payment_url` in a Custom Tab or WebView (web checkout). Adding native SDK support needs an SDK upgrade plus a new endpoint.
13. **The price shown in the catalog can differ from the price charged.** `api/catalog_helper.php:185-187` vs `api/coupon_helper.php:162-168`.
    - Problem: catalog `price` and `mrp` are the `MAX()` across a service group, while booking charges the price of the single `MIN(packId)` row sent as `pack_id`.
    - Fix: read price and mrp from the `MIN(packId)` row.

## P2

1. **The PhonePe return URL has no order id, so the callback always reports failure.** `api/payments.php:59,70`, `api/payment_callback.php:5-6,17`.
   - Problem: `PHONEPE_REDIRECT_URL` (configured to point at `payment_callback.php`) is sent to PhonePe unchanged. The user always lands on `?payment=failed` with no `txn`. The website ignores this and polls `paymentConfirmation.php` with the stored id (`zen-api.js:468`).
   - App workaround: keep `transaction_id` from `payments.php`, treat the return URL only as "the user came back", then poll `paymentConfirmation.php`.
   - Fix: append `?transactionId=<merchantOrderId>` per order, and have the callback check status with PhonePe instead of trusting query parameters.
2. **12-hour session with no refresh.** `api/login.php:84-85, 104` (`expires_in_hours: 12`).
   - Problem: there is no refresh endpoint, so the app forces password + SMS OTP again every 12 h, and each re-login costs an SMS.
   - Fix: a longer lifetime for app logins (the helper default is 30 days) or sliding expiry in `getUserIdFromRequest()`.
3. **Register: weak validation and no verification.** `api/register.php:20-44`.
   - Problem: no rate limit, no phone OTP verification, no email format check and no password minimum (reset enforces 6-72). Anyone can register someone else's number. HTTP 201 is sent with body `statusCode: 200` (`api/register.php:90-92`).
   - Fix: `FILTER_VALIDATE_EMAIL`, a 6-72 password length, the `^[6-9]\d{9}$` phone rule, a per-IP limit and `statusCode: 201`.
4. **Phone rule differs between endpoints.** `api/register.php:46`, `api/login.php:37`, `api/update_profile.php:81` accept any 10 digits; `api/password_reset.php:38` requires `^[6-9]\d{9}$`.
   - Problem: some registered users can never reset their password.
   - Fix: use one rule everywhere.
5. **Non-JSON output on DB failure, and DB error text leaked.** `api/db.php:25-26`.
   - Problem: `db.php` echoes `Connection error: <PDO message>` (DB host and user) as plain text, leaves `$conn` unset, and the endpoint then dies with a fatal error. About 20 endpoints include it unbuffered (login, register, cart, fetch_user, update_profile, check_session, logout, order_history, book_appointment, rating, payments, paymentConfirmation, partner_registration...). Raw exception text is also returned at `api/serviceBooking.php:64`, `api/serviceComplete.php:60`, `api/checkSlot.php:55`, `api/category.php:34`, `api/partner_registration.php:187`, `api/payment_verify.php:54` and `api/paymentConfirmation.php:88`.
   - Fix: `error_log()` the message and return a generic `{"statusCode":503,"status":"error",...}` JSON response, then exit.
6. **Uncaught exceptions return an empty-body 500.** `api/runtime.php:28-31`.
   - Problem: there is no exception handler, and `display_errors` is off. For example a unique-key race in `api/update_profile.php:134-136`, or `limit=0` in `api/user_orders.php:35,93` (`DivisionByZeroError`).
   - Fix: `set_exception_handler()` in `runtime.php` that outputs the JSON error envelope.
7. **HTTP 200 returned on errors.**
   - Problem: affected endpoints:
     - `api/book_appointment.php:32-35`: "Invalid input".
     - `api/paymentConfirmation.php:28-36, 85-89`: missing id or gateway error.
     - `api/fetchCategory.php:37-52` and `api/fetchCategoryDetails.php:34-50`.
     - Body/HTTP mismatch: `api/partner_registration.php:174-177` sends HTTP 201 with `statusCode: 200`.
   - Fix: send 4xx/5xx codes and align `statusCode` with the HTTP code.
8. **Mixed response envelopes.**
   - Problem: the app must branch per endpoint:
     - `{statusCode,status,message,data}` from `public_json` (`api/public_helper.php:31`), but with top-level `token`/`user` in `api/login.php:102-103`, `items` in `api/cart.php:86` and `user` in `api/update_profile.php:158`.
     - `status:"otp_sent"` in `api/login.php:175-179`.
     - `{success,data|error}` in the legacy files (`api/user_orders.php`, `api/order_details.php`, `api/save_transaction.php`, `api/user_transaction.php`, `api/fetch-transaction.php`).
     - `{success,message}` in `api/paymentConfirmation.php`.
     - `{error[,message]}` for errors in `api/payments.php:33,46,54,62,92,96`.
     - `{status,message}` without `statusCode` in `api/book_appointment.php`, `api/rating.php`, `api/checkSlot.php` and `api/category.php`.
     - `status` used for the *payment* state in `api/payment_verify.php:43-45`.
     - `fetch_user` returns the profile under `data`, not `user` (`api/fetch_user.php:29-33`).
   - App rule: trust the HTTP code first, then read `message ?? error`.
   - Fix: migrate the endpoints to `public_json()`.
9. **No JSON Content-Type.** `api/serviceComplete.php`, `api/fetchCategory.php`, `api/fetchCategoryDetails.php` (served as `text/html`); `api/payment_webhook.php:34-35` on 500.
   - Fix: `header('Content-Type: application/json')`.
10. **Missing CORS / OPTIONS handling.** `api/rating.php`, `api/book_appointment.php:9`, `api/serviceBooking.php`, `api/serviceComplete.php`, `api/checkSlot.php`, `api/user_orders.php`, `api/order_details.php`, `api/payementInitiate.php`, `api/save_transaction.php`, `api/fetch-transaction.php`, `api/user_transaction.php`, `api/fetchCategory.php`, `api/fetchCategoryDetails.php`; `api/category.php:5` sends only Allow-Origin.
    - Problem: native apps are unaffected; Flutter Web and WebView callers on another origin break.
    - Fix: `public_cors()` at the top of each.
11. **Image URLs are inconsistent.**
    - Problem:
      - Catalog, banner and settings images are absolute, but built from the request `Host` header and `HTTPS` / `X-Forwarded-Proto` (`api/public_helper.php:129-134`). Behind a proxy that doesn't set that header they become `http://`, which iOS ATS and Android cleartext rules block.
      - The user `photo` is returned raw: a bare filename, relative path, `data:` URI or URL (`api/auth_helper.php:98`, `api/update_profile.php:59,150`).
      - There is no photo upload.
      - `home_packages` `url` is relative (use `url_full`) and has no image (`api/catalog_helper.php:266-281`).
    - Fix: build URLs from `app_url()` (`api/runtime.php:44-49`), normalise `photo`, and add a multipart photo upload.
12. **Payment status response is misleading.** `api/paymentConfirmation.php:52-55`, `api/payment_verify.php:41,47`.
    - Problem: `merchantTransactionId` / `transactionId` hold PhonePe's order id, not ours. `amount` is in **paise** in `paymentConfirmation` but **rupees** in `payment_verify` and `fetch-transaction`. `paymentConfirmation` ignores a JSON body; send `transactionId` as a query or form field.
    - Fix: return the requested id as `merchantOrderId`, add `amount_rupees`, and also parse JSON input.
13. **payments.php: weak re-initiation and amount rules.** `api/payments.php:40-57, 78-83`.
    - Problem: an already-paid order can be initiated again (`ON DUPLICATE KEY UPDATE status='pending'` can downgrade the row). Non-`ZC-` order ids, or bookings with price 0/NULL, take the client amount. Neither the webhook nor the confirmation compares the paid amount with `transactions.amount`. Only `PhonePeException` is caught.
    - Fix: 409 when the row is already `success` or belongs to another user; reject non-`ZC-` ids from customers; catch `Throwable`.
14. **`PHONEPE_ENV=SANDBOX` silently runs against PRODUCTION.** `api/phonepe_client.php:17-22`, `.env.example:10`.
    - Fix: map `SANDBOX` to UAT and fail on unknown values.
15. **The payment method is not stored.** `api/book_appointment.php:50, 112-123`.
    - Problem: online bookings show "Pay after service" (`api/my_bookings.php:70`) until `payments.php` creates the transaction, and an abandoned online checkout looks the same as a cash booking.
    - Fix: store `payment_method`.
16. **No slot validation, so double booking is possible.** `api/checkSlot.php:20, 39-43`, `api/book_appointment.php:112-123`.
    - Problem: the date and slot are not validated. `checkSlot` counts cancelled bookings, uses a global capacity of 1 per slot and still shows elapsed times for today. `book_appointment` never re-checks the slot.
    - Fix: validate `Y-m-d` >= today, exclude cancelled bookings, and re-check the slot on booking.
17. **A listed service can fail to book, and some slugs return 404.** `api/catalog_helper.php:188` vs `api/coupon_helper.php:162-163`; `api/catalog_helper.php:156-159`.
    - Problem: a group whose first pack row is disabled is listed, but booking returns 422. `?slug=service-<id>` returns 404; only `slugs=` understands that alias.
    - Fix: use the `MIN(packId)` row's status, and map `service-<id>` in the `slug` filter.
18. **Cart and orders data quirks.**
    - `api/cart.php:93-100`: invalid or missing `items` on POST stores `[]`, wiping the cart.
    - `api/logout.php:22-24`: logout deletes the server cart for all devices.
    - `api/order_history.php:34`: `items` and `shipping_address` are always empty, because nothing writes `orders`.
    - `api/order_details.php:28`: rejects website `ZC-` ids.
    - Fix: 422 on a bad cart body; the app should use `my_bookings.php` for orders.
19. **update_profile can lock the user out.** `api/update_profile.php:63-88`, `api/update_profile.php:32-57, 91-94`.
    - Problem: `"phone": ""` passes validation and is saved, removing the login identifier. A password change has no length rule and keeps other sessions alive.
    - Fix: reject empty required fields, enforce 6-72 characters, and delete the other `user_sessions` rows.
20. **Rating and review gaps.** `api/rating.php:69-77`, `api/my_bookings.php:34-39`.
    - Problem: bookings that are not completed (New, Cancelled) can be rated, and the app cannot read back an existing review.
    - Fix: require the Completed status, and add `review{rating,feedback,status}` to `my_bookings`.
21. **Partner sign-up issues.** `api/partner_registration.php:35-40, 58-65, 79-80, 187` vs `save_service_partner.php:96-161`.
    - Problem: the newer endpoint has no validation or duplicate-mobile check, stores the raw data URL when decoding fails, and returns the DB error text. The older root endpoint validates and rate-limits.
    - Fix: port that validation into `partner_registration.php` and retire `save_service_partner.php`.
22. **SMS send result is not checked.** `api/sms_sender.php:42-57`.
    - Problem: "otp_sent" is returned even when the gateway rejects the message, and there is no cURL timeout. The login OTP comes from `rand()` and is stored in plaintext (`api/login.php:153-162`).
    - Fix: check the HTTP code and body, set timeouts, use `random_int`, and hash the OTP.
23. **Features the app may expect are NOT SUPPORTED:** customer cancel or reschedule, a notification list, FCM/device token registration (`push_send` in `api/admin/core/notify.php:139` is a stub with no token table), an app version / force-update setting, saved-address CRUD (only a single `users.address` field via `update_profile.php`), token refresh, and profile photo upload.
    - Fix: add the endpoints before the app depends on them.

## P3

1. **No method checks.** `api/login.php:20`, `api/logout.php:17`, `api/update_profile.php:17`.
   - Problem: GET is processed too; `GET logout.php?token=...` logs out and empties the cart.
   - Fix: return 405 unless POST.
2. **Tokens in the query string take priority over headers.** `api/auth_helper.php:18-21`.
   - Problem: tokens end up in logs and referrers.
   - Fix: check headers first.
3. **Session validation ignores `users.status`, and expired sessions are never purged.** `api/auth_helper.php:78-83`.
   - Fix: add a status filter and delete expired rows in `createSession`.
4. **Password-reset request reveals registered numbers.** `api/password_reset.php:61-74`.
   - Problem: only registered numbers can ever get a 429.
   - Fix: rate-limit unknown numbers too.
5. **Login step 2 "User not found." returns 400, while step 1 returns 401 for the same case.** `api/login.php:69-73`.
   - Fix: use 401.
6. **Array input causes a TypeError (empty 500).** `api/update_profile.php:107,116`, `api/partner_registration.php:60,160`.
   - Fix: add `is_string` guards.
7. **`?include_disabled=1` works publicly.** `api/category.php:11`, `api/fetch_serviceable_areas.php:17`.
   - Fix: allow it for admins only.
8. **`cms_pages` uses a 5-minute public cache with no ETag.** `api/cms_pages.php:30,41`.
   - Problem: banners `total_items` also over-counts (`api/site_content.php:87-98`).
   - Fix: use `public_json_revalidate()`.
9. **`checkSlot` opens two DB connections and has trailing output after `?>`.** `api/checkSlot.php:5-7, 61-62`.
10. **Webhook status is not monotonic.** `api/phonepe_webhook_handler.php:33-60`.
    - Problem: a late failure event can overwrite `success`.
    - Fix: never move a row out of `success`.
11. **`paymentConfirmation` clears the cart on every successful poll.** `api/paymentConfirmation.php:60-67`.
    - Fix: clear it only when the row changes to success.
12. **`save_transaction` allows `refunded`, which is not in the `transactions.status` enum.** `api/save_transaction.php:43`.
13. **`limit=0` causes a `DivisionByZeroError`.** `api/payments.php:124,224`, `api/user_orders.php:35,93`.
    - Fix: clamp to at least 1.
14. **`PHONEPE_MERCHANT_ID` is documented but unused.** `.env.example:11`.
15. **Support tickets:** an unknown `status` filter is silently ignored (`api/support_tickets.php:96-100`); an unknown action returns 404 (`:185`); replies are unlimited (`:167-182`).
16. **The contact enquiry limit is per phone only.** `api/contact_enquiry.php:66-70`.
    - Fix: add a per-IP cap.
17. **`validate_coupon` accepts an unauthenticated `user_id` and has no rate limit.** `api/validate_coupon.php:47-49`.
    - Fix: use only the token's user.
18. **Price rounding mismatch.** `api/book_appointment.php:118`, `api/payments.php:49`.
    - Problem: the stored `price` is an int, while `amount_payable` in the response is a float.
19. **Pack quantity is silently clamped to 1-20, and more than 50 ids returns a misleading 422.** `api/coupon_helper.php:155-159`.
20. **The allowed CORS headers are inconsistent.** `api/auth_helper.php:47` accepts `X-Authorization`, but no endpoint lists it; `api/partner_registration.php:10,17` omits `X-Auth-Token`.
