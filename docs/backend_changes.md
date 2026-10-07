# Backend changes for the mobile app (2026-10-07)

Fixes for the gaps in `docs/backend_gaps.md` (gap ids P1-x / P2-x / P3-x below). Current request/response contracts with examples: `docs/app_backend_contract.md` section 8.

- **Rule followed:** additive and backward-compatible. Old response keys are kept; new keys added (`statusCode` = HTTP code, `data`). The website (`zen-api.js`, `zen-pages.js`) needed no change.
- **DB backup before any change:** `database/backups/backup_20261007_105507.sql` (local).
- **Not done on purpose:** `LEGACY_STRICT_AUTH` left as is; no push delivery; no reschedule.
- **Secrets:** none in code or docs. The hard-coded SMS gateway credentials were removed from `api/sms_sender.php`; they still exist in git history, so **rotate the gateway password**.

## How it was tested (local XAMPP, PHP 8.2, MariaDB)

- `php -l` on every touched PHP file: no syntax errors.
- HTTP tests against `http://localhost/VGS/ZenCare/zen/api/...` with throw-away scripts (outside the repo) that create a test session, call the endpoints, assert HTTP code == `statusCode` and the response fields, then delete the rows they created:
  - auth: login steps / limits, register, update_profile, logout, check_session, fetch_user, password_reset;
  - booking: checkSlot, book_appointment, my_bookings, rating, cancel_booking;
  - payment (non-live paths): payments, paymentConfirmation, payment_verify, payment_callback, save_transaction;
  - public: cart, contact_enquiry, catalog, site_settings, app_config, device_token, legacy endpoints.
- `php tests/phonepe_webhook_test.php`: 23/23 pass (new case 7: late FAILED after success is ignored).
- Admin modules called in-process against the same tables: `bookings_get/list/counts/history`, `reviews_list`, `payments_list/summary`, `dashboard_overview`, `customers_get`, `tickets_list`, `settings_get/save` (incl. the new App group and version validation).
- Website in a real browser (session seeded, since login needs a real SMS):
  - add to cart on `ac-service.php` (synced to `cart.php`);
  - `checkout.php`: date, slots from `checkSlot`, pay-after-service booking, "Booking Received" shown;
  - the booked slot then disappears from `checkSlot`;
  - `my-bookings.php` lists the booking;
  - `contact.php` enquiry gets a ticket number and is linked to the signed-in user.
  - Test rows were deleted afterwards.
- Website pages return 200 with no PHP errors: index, services, checkout, cart, contact, my-bookings, login, register, forgot-password.
- **Not tested:**
  - live PhonePe pay / status calls (the local `.env` points at PRODUCTION);
  - real SMS delivery (no gateway credentials locally);
  - admin pages over HTTP (needs an admin login). The admin modules were tested in-process instead.

---

## Database

### `database/migrations/2026_10_07_011_mobile_app_api.sql` (new, applied locally)
- **What:**
  - new tables `login_otps` (hashed login OTP, attempts, send counters), `api_rate_limits` (per-IP limits, e.g. register) and `device_tokens` (`user_id` FK to `users.ID`, unique `fcm_token`, `platform`, `app_version`);
  - no column changes: `service_booking.payment_method` already existed and is now filled by `book_appointment.php`;
  - `site_settings` rows for the new "App" group (`app_min_version_android`, `app_min_version_ios`, `app_latest_version`, `app_force_update`, `app_maintenance`, `app_maintenance_message`).
  - All table names are lowercase; every statement is idempotent (`IF NOT EXISTS` / `INSERT IGNORE`).
- **Why:** P1-8, P2-3, P2-23 (device tokens, app config).
- **Backward-compat:** additive only; no existing column changed.
- **Tested:** `php database/migrate.php` locally; re-run is a no-op.

## Core / shared

### `api/db.php`, `api/runtime.php`, `api/public_helper.php`, `api/admin/core/bootstrap.php`, `api/site_content.php`, `api/legacy_access.php`
- **What:**
  - `db.php` answers a DB connection failure with JSON 503 `{statusCode,status,message}` and logs the real error. Callers that handle the failure themselves (`public_db`, `site_content_db`, `legacy_db`, admin `db()`) set a local soft-fail flag.
  - `runtime.php` adds `zc_json_exit()` and, for scripts under `/api/` only, an exception handler returning JSON 500 "Server error. Please try again.".
  - `public_helper.php`: `public_site_url()` builds absolute URLs from `APP_URL`, and `public_legacy_error()` gives the legacy files generic errors.
- **Why:** P2-5, P2-6, P2-11.
- **Backward-compat:** success paths unchanged. Website pages (not under `/api/`) keep normal PHP error handling.
- **Tested:**
  - MySQL stopped: `my_bookings`, `catalog_services`, `checkSlot`, `app_config`, `fetchCategory` and `login` all returned JSON 503 (`statusCode` 503), while `index.php` still rendered (200).
  - A temporary throwing script under `api/` returned JSON 500 (then deleted).
  - Page smoke test.

### `api/auth_helper.php`
- **What:** new helpers:
  - `zc_normalize_phone()`: one rule `^[6-9]\d{9}$`, strips +91 / 0;
  - `zc_photo_url()` and `zc_user_profile()` (adds `photo_url`);
  - `zc_rate_limit_hit()` and `zc_client_ip_key()`;
  - `zc_login_token_lifetime()` (`LOGIN_TOKEN_DAYS`, default 30).
  - `getUserIdFromRequest()` user payload includes `photo_url`.
- **Why:** P2-2, P2-4, P2-11.
- **Backward-compat:** existing functions and token transport order unchanged; `photo` still returned raw.
- **Tested:** auth suite; `check_session.php` / `fetch_user.php` return `photo_url`.

### `api/site_settings_helper.php`, `api/admin/modules/settings.php`
- **What:**
  - new "App" settings group;
  - `Admin Alerts` and `App` groups excluded from public `site_settings.php`;
  - new `version` field type validated on save (`^\d{1,4}(\.\d{1,4}){0,3}$` or empty, 422 otherwise).
- **Why:** P1-10, P2-23.
- **Backward-compat:** public settings lose only `admin_alert_*` (staff data) and the new `app_*` keys. Admin sees and edits everything.
- **Tested:** `site_settings.php` output; admin `settings_get/save` (invalid version 422, valid save restored).

## Auth (P1-8, P1-9, P2-2, P2-3, P2-4, P2-19, P2-22, P3-1, P3-5)

### `api/sms_sender.php` (rewritten)
- **What:**
  - gateway settings from env: `SMS_GATEWAY_URL` (optional), `SMS_GATEWAY_USER`, `SMS_GATEWAY_PASSWORD`, `SMS_GATEWAY_SENDER`, `SMS_GATEWAY_PEID`, `SMS_OTP_TEMPLATE_ID`, `SMS_OTP_ENABLED` (default true);
  - query built with `http_build_query`; cURL timeouts 5 s connect / 10 s total;
  - fails on a cURL error, HTTP >= 400, an empty body or an error word in the body; failures logged without credentials;
  - same `sendOtpSms($phone,$otp,$purpose)` signature and `{success,error}` result.
- **Why:** P1-9, P2-22.
- **Backward-compat:**
  - callers unchanged (`login.php`, `password_reset.php`, `admin/assignTechnician.php`).
  - **The live `.env` must have the `SMS_GATEWAY_*` keys and `SMS_OTP_TEMPLATE_ID` before this file is deployed**, or OTP SMS stop. `SMS_ENABLED` (admin notifications) does not affect OTPs.
- **Tested:** missing config returns a failure (login then answers 502). Real sending was not tested.

### `api/login.php` (rewritten)
- **What:**
  - POST only (405);
  - step 1: OTP from `random_int`, stored hashed in `login_otps`, valid 300 s; 60 s resend gap (429 + `Retry-After`); max 3 sends/hour; a failed SMS returns 502 and the code is voided;
  - step 2: 5 wrong tries delete the code; a wrong OTP returns 400 with `data.attempts_left`; "User not found" returns 401;
  - token lifetime `LOGIN_TOKEN_DAYS`.
- **Why:** P1-8, P2-2, P3-1, P3-5.
- **Backward-compat:**
  - step 1 keeps `status:"otp_sent"`; step 2 keeps top-level `token`, `user`, `expires_in_hours`.
  - New: `data{expires_in,resend_after}` / `data{token,user,expires_in,expires_at}`, and `user.photo_url`.
- **Tested:** GET 405, bad phone 400, wrong password 401, SMS failure 502, resend within 60 s 429, voided OTP 400, right OTP after 5 wrong tries rejected (code deleted), right OTP 200 with the new fields. The 3-per-hour cap was checked by code review only.

### `api/register.php` (rewritten)
- **What:**
  - accepts `firstname`/`first_name` and `lastname`/`last_name` (last name may be blank);
  - validates email, password 6-72 and the phone rule; 10 attempts/hour per IP (429);
  - success is 201 with `statusCode` 201, `user_id` and `data.user_id`.
- **Why:** P2-3, P2-4.
- **Backward-compat:**
  - HTTP 201 and the message as before; still no token.
  - Validation errors stay HTTP 400 (with `errors`), which the website shows.
- **Tested:** GET 405, missing fields, invalid email / phone / long password (400), duplicate (400), valid (201); website register page loads. The per-IP limit was checked by code review only.

### `api/update_profile.php` (rewritten)
- **What:**
  - POST/PUT/PATCH (405 otherwise);
  - empty `first_name`/`email`/`phone` rejected; type guards; password 6-72;
  - a password change deletes the user's other sessions;
  - response has `user` and `data` (profile with `photo_url`).
- **Why:** P2-19, P3-6.
- **Backward-compat:** same fields and `user` key; PUT/PATCH kept (they were accepted before).
- **Tested:** field updates, empty-field rejection, password change (other session 401, current still valid).

### `api/logout.php`
- **What:** POST only (405); no longer deletes the server cart; optional `fcm_token` removes that device token; always 200.
- **Why:** P2-18, P3-1.
- **Backward-compat:** the website logs out with POST (`zen-api.js`). A GET logout now gets 405 (that was the CSRF-style risk).
- **Tested:** logout keeps the cart rows; GET 405; token rejected (401) afterwards. `fcm_token` removal on logout was checked by code review only (the same delete is tested through `device_token.php`).

### `api/password_reset.php`
- **What:** uses `zc_normalize_phone()`.
- **Why:** P2-4.
- **Backward-compat:** same rule as before for reset (`^[6-9]\d{9}$`).
- **Tested:** request with valid / invalid phone.

## Booking (P1-1, P1-2, P1-7, P2-15, P2-16, P2-20)

### `api/booking_helper.php` (new)
- **What:** shared slot list / labels, cancellable statuses, date and elapsed-slot checks, booked-slot query (non-cancelled), the `my_bookings` SELECT (joins transaction + latest review) and the booking item builder.
- **Why:** one source for `my_bookings`, `book_appointment`, `cancel_booking`, `checkSlot`, `rating`.
- **Backward-compat:** item keys identical to the old `my_bookings` output, plus new keys.
- **Tested:** via the endpoints below.

### `api/book_appointment.php` (rewritten)
- **What:**
  - `requireAuth`, token user only; required fields;
  - real `Y-m-d` date, today or later; slot not already started;
  - `items[]` with `pack_id` on every line (422 `errors.items`); price from `saverpacks` only, client `amount` ignored; coupon logic unchanged;
  - `GET_LOCK` on date + slot plus a re-check: 409 if taken by a non-cancelled booking;
  - stores `payment_method` (`Online` when online and payable > 0, else `Cash`);
  - errors use real HTTP codes.
  - Response adds `statusCode`, numeric `id`, `payment_method`, `data{..., booking}`.
- **Why:** P1-1, P1-2, P2-7, P2-15, P2-16.
- **Backward-compat:**
  - the website already sends the token, `items` with `pack_id` and `payment_method` (checked in `zen-pages.js`);
  - `unique_booking_id`, `amount`, `discount`, `amount_payable`, `coupon` and the message are unchanged; success stays HTTP 200.
  - The stored status is still the raw "Pending Confirmation", which admin maps to New.
- **Tested:**
  - API: GET 405, no token 401, missing items / `pack_id` 0 / unknown pack 422, past and invalid date 422, taken slot 409, cash and online bookings 200 with the server price. Coupon handling is unchanged code and was not re-tested.
  - Browser: checkout booking; DB row checked (`price` 1400 from the server, `payment_method` Cash).

### `api/checkSlot.php` (rewritten)
- **What:** GET or POST (`date` in the body or `?date`); missing 400, invalid 422; excludes cancelled bookings and today's started slots; one DB connection; response `{statusCode,status,message,data:[...]}`.
- **Why:** P2-16, P3-9.
- **Backward-compat:** `data` is still the list of free slot strings; `status`/`message` kept.
- **Tested:** API cases, plus the browser checkout slot dropdown (booked slot removed).

### `api/my_bookings.php`
- **What:** uses the helper; items add `refund_status`, `can_cancel`, `can_review`, `review{rating,feedback,status}` and `cancelled_at`.
- **Why:** P2-20, P2-23.
- **Backward-compat:** all old keys unchanged.
- **Tested:** API, plus the browser `my-bookings.php`.

### `api/rating.php` (rewritten)
- **What:**
  - `requireAuth`, token user only;
  - unknown or other user's booking returns 404; not Completed returns 409;
  - feedback optional (max 2000);
  - 201 with `statusCode` 201, `review_id`, `review_status`, `data.review`.
- **Why:** P1-7, P2-20.
- **Backward-compat:** same request body; `status`/`message` kept; old body `user_id` ignored.
- **Tested:** no token 401, non-completed 409, other user 404, bad rating 422, create 201, `my_bookings` shows the review, admin `reviews_list` sees it.

### `api/cancel_booking.php` (new)
- **What:**
  - `POST {id}` with a token; own booking only (404); status New/Pending/Assigned (409 otherwise);
  - same effect as admin `bookings_cancel`: conditional update to Cancelled with reason "Cancelled by customer" and `cancelled_at`; coupon use released; `Refund Pending` for paid online bookings; history row (actor "Customer"); cancellation notification;
  - returns the booking item.
- **Why:** P2-23 (C18).
- **Backward-compat:** new endpoint.
- **Tested:** cancel own booking, repeat 409, other user 404, Completed 409; admin `bookings_history` shows the row.

## Payment (P1-1, P1-3, P1-4, P2-1, P2-12, P2-13, P2-14, P3-10, P3-11, P3-13)

### `api/payment_helper.php` (new)
- **What:**
  - `zc_phonepe_status()` maps PhonePe states;
  - `zc_txn_transition()` is a conditional update that never leaves `success`/`refunded` and reports `became_success`;
  - `zc_payment_success_effects()` clears the cart and sends the booking-confirmed SMS.
- **Why:** one set of rules for confirmation, callback and webhook.
- **Backward-compat:** n/a (internal).
- **Tested:** webhook test suite, confirmation tests.

### `api/payments.php` (rewritten POST, hardened GET)
- **What:**
  - POST: token; `order_id` must be `ZC-<own unique_booking_id>` (422 / 404); cancelled 409; amount from the booking only; transaction of another user 409; already success/refunded 409;
  - redirect URL gets `?transactionId=`; any `Throwable` gives 502 with a generic message;
  - the pending upsert never downgrades success;
  - all responses add `statusCode`; success adds `data`.
  - GET (admin): limit clamped 1-100; generic 500.
- **Why:** P1-1, P2-1, P2-13, P3-13.
- **Backward-compat:**
  - success keeps `success`, `payment_url`, `transaction_id`; errors keep `error` + `message`.
  - The website sends `ZC-` ids with the token.
  - Customers can no longer pay `TXN_`/custom ids here (old app: `payementInitiate.php` is unchanged).
- **Tested:** no token 401, non-`ZC-` / missing order 422, other user's booking 404, cancelled 409, zero price 422, already paid 409, other user's transaction 409, PUT 405, admin GET without token 401, admin GET `limit=0` 200. The live PhonePe call was not made.

### `api/paymentConfirmation.php` (rewritten)
- **What:**
  - GET or POST, JSON or form; `transactionId` / `transaction_id` / `merchantOrderId`;
  - token + ownership (404); PhonePe error 502;
  - side effects only on the transition to success.
  - Adds `statusCode`, `status`, `message`, `merchantOrderId`, `amount_rupees`, `payment_status`, `data`.
- **Why:** P1-4, P2-7, P2-12, P3-11.
- **Backward-compat:**
  - `success`, `merchantTransactionId`, `transactionId`, `state`, `amount` (paise) and `paymentDetails` are kept.
  - The website polls with the token (`zen-api.js` sends it).
  - Still listed in `legacy_access` logging.
- **Tested:** no token 401, other user 404, missing id 400. Live status call not made.

### `api/payment_verify.php` (rewritten)
- **What:** token + ownership; read-only; adds `statusCode`, `message`, `payment_status`, `data.merchantOrderId`, `data.amount_rupees`.
- **Why:** P1-4, P2-12.
- **Backward-compat:** `status` = success|failed|pending, `state`, `data.transaction_id`, `data.amount` (rupees) and `data.payment_details` are kept. Callers now need a token.
- **Tested:** 401 / 404 / 400 branches.

### `api/payment_callback.php` (rewritten)
- **What:**
  - reads `transactionId` (strict charset); for a known transaction asks PhonePe (`getOrderStatus`) and applies transition + side effects;
  - redirects to `PAYMENT_CALLBACK_BASE_URL?payment=success|failed|pending&txn=<id>`; unknown id or error gives `pending`.
- **Why:** P2-1.
- **Backward-compat:** same redirect target and `payment` param; `txn` added; `pending` is new (the website polls anyway).
- **Tested:** no id, unknown id and junk id all redirect with `payment=pending` (a `status=success` query parameter is ignored). Live status call not made.

### `api/phonepe_webhook_handler.php`, `tests/phonepe_webhook_test.php`
- **What:** uses the helper; a late FAILED after success returns `ignored_final` and the row stays success; side effects only on the change to success. New test case 7.
- **Why:** P3-10.
- **Backward-compat:** same HTTP answers to PhonePe.
- **Tested:** 23/23.

### `api/phonepe_client.php`
- **What:** `PHONEPE_ENV`: empty / PRODUCTION / PROD gives PRODUCTION; UAT / SANDBOX gives UAT; STAGE; anything else throws a clear error (payments answer 502 / 500).
- **Why:** P2-14.
- **Backward-compat:** PRODUCTION unchanged. **A live `.env` with `PHONEPE_ENV=SANDBOX` now really uses UAT**; check it before deploying.
- **Tested:** CLI: PRODUCTION / PROD / unset map to PRODUCTION, SANDBOX / UAT to UAT, STAGE to STAGE, TEST throws `RuntimeException`.

### `api/save_transaction.php`
- **What:** always 410 Gone (`statusCode` 410); still writes the `legacy_access` log.
- **Why:** P1-3, P3-12.
- **Backward-compat:**
  - the website does not call it;
  - the old app's call now fails with 410 instead of writing a client-chosen status;
  - transactions are still recorded by `payments.php`, confirmation, callback and webhook.
- **Tested:** GET/POST 410.

## Public / content

### `api/contact_enquiry.php`
- **What:** `$auth = getUserIdFromRequest($conn); $userId = $auth ? (int) $auth['user_id'] : null;` and the phone via `zc_normalize_phone()`.
- **Why:** P1-6, P2-4.
- **Backward-compat:**
  - same request/response;
  - guests unchanged;
  - existing tickets wrongly filed under user 1 are not changed.
- **Tested:** API, plus a browser enquiry (ticket linked to the signed-in user, then deleted).

### `api/cart.php`
- **What:** POST requires `items` as a list of objects (max 100, 60 KB), else 422; `items: []` still clears.
- **Why:** P2-18.
- **Backward-compat:** the website always sends a list (browser add-to-cart synced fine).
- **Tested:** invalid bodies 422 with the cart untouched; valid save; clear.

### `api/catalog_helper.php`
- **What:** `price`, `mrp` and visibility come from the group's first pack row (`MIN(packId)`); `slug=service-<id>` is accepted.
- **Why:** P1-13, P2-17.
- **Backward-compat:** same keys. A price can change only where group rows had different prices; it now equals the price charged.
- **Tested:** first catalog item's `price` equals its `pack_id` row price; `slug=service-<id>` finds it.

### `api/uploads/partners/.htaccess` (new), `.gitignore`
- **What:** deny-all for uploaded partner KYC files; `.gitignore` re-includes `api/uploads/**/` so the `.htaccess` is tracked.
- **Why:** P1-11.
- **Backward-compat:** no page or admin screen links to these files (grep).
- **Tested:** HTTP 403 on a file in that folder.

### Legacy endpoints

Files: `api/fetchCategory.php`, `fetchCategoryDetails.php`, `serviceComplete.php`, `serviceBooking.php`, `category.php`, `user_transaction.php`, `order_details.php`, `user_orders.php`, `fetch-transaction.php`, `payementInitiate.php`, `partner_registration.php`.

- **What:**
  - `public_cors()` (CORS, OPTIONS, JSON Content-Type);
  - raw exception text replaced by generic messages (logged server-side);
  - `fetchCategory` / `fetchCategoryDetails`: missing id 400, not found 404 (were HTTP 200), `statusCode` added;
  - `partner_registration` success body `statusCode` 201 (HTTP was already 201);
  - `user_orders` clamps `page`/`limit` (no division by zero).
- **Why:** P2-5, P2-7, P2-9, P2-10, P3-13.
- **Backward-compat:**
  - success bodies unchanged except the added `statusCode`;
  - error bodies keep `status`/`message` (or `success`/`error`);
  - `LEGACY_STRICT_AUTH` behaviour untouched.
- **Tested:** HTTP calls for success and error branches; `user_orders?limit=0` gives 200.

## New endpoints

### `api/app_config.php` (new)
- **What:**
  - GET: min Android/iOS version, latest version, force_update, maintenance + message, support phone/email, store URLs;
  - optional `?platform&version` adds `update_required` / `update_available`;
  - ETag revalidation.
- **Why:** P2-23 (C17).
- **Backward-compat:** new.
- **Tested:** defaults 200, `platform=android&version=0.9.0` (update flags), bad platform 422, POST 405, repeat with `If-None-Match` gives 304.

### `api/device_token.php` (new)
- **What:**
  - POST register (upsert by FCM token, moves to the current user, max 10 devices per user);
  - DELETE or `action=remove` unregisters;
  - login token in a header (body `token` is the FCM token).
- **Why:** P2-23 (C16).
- **Backward-compat:** new; push delivery is not wired.
- **Tested:** no token 401, bad platform / short token 422, register, re-register (one row, version updated), move to another user, 12 registrations leave 10 rows (newest kept), `action=remove`, DELETE, GET 405.

## Config

### `.env.example`
- **What:** new keys documented (names only):
  - `LOGIN_TOKEN_DAYS`;
  - `SMS_GATEWAY_*` now marked as required for OTPs, plus `SMS_OTP_TEMPLATE_ID` and `SMS_OTP_ENABLED`;
  - a note that `SMS_ENABLED` is admin notifications only;
  - `PHONEPE_ENV` allowed values;
  - `PHONEPE_REDIRECT_URL` note (`transactionId` is appended automatically).
- **Why:** env keys for the changes above.
- **Backward-compat:** documentation only.
