/* =========================================================
   ZEN HOME EXPERTS - PAGE LOGIC
   Login, register, cart and checkout pages.
   Needs zen-api.js (window.ZenAPI) and common.js (window.ZenCart).
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const API = window.ZenAPI;

    if (!API) return;

    const params = new URLSearchParams(window.location.search);


    /* =====================================================
       SHARED HELPERS
    ===================================================== */

    function showAlert(box, message, type, prefix, allowHtml) {

        if (!box) return;

        if (!message) {
            box.innerHTML = "";
            return;
        }

        const icon = type === "success" ? "fa-circle-check" : "fa-circle-exclamation";

        box.innerHTML =
            '<div class="' + prefix + "-alert " + prefix + "-alert-" + (type === "success" ? "success" : "error") + '" role="alert">' +
            '<i class="fa-solid ' + icon + '"></i>' +
            "<span>" + (allowHtml ? message : API.escapeHtml(message)) + "</span>" +
            "</div>";
    }

    function setBusy(button, busy, label) {

        if (!button) return;

        const span = button.querySelector("span");

        if (busy) {
            button.dataset.label = span ? span.textContent : "";
            button.disabled = true;
            if (span) span.textContent = "Please wait...";
        } else {
            button.disabled = false;
            if (span) span.textContent = label || button.dataset.label || span.textContent;
        }
    }

    function getCart() {
        return window.ZenCart ? window.ZenCart.getCart() : API.readLocalCart();
    }

    // After API.refreshCart(): tell the customer which services were dropped
    function notifyRemoved(result) {
        if (result && result.removed && result.removed.length) {
            API.notify(
                result.removed.join(", ") + (result.removed.length === 1 ? " is" : " are") + " no longer available and was removed from your cart.",
                "warning",
                7000
            );
        }
        return result;
    }

    function cartTotal(cart) {
        return cart.reduce(function (sum, item) {
            return sum + Number(item.price || 0) * (Number(item.quantity) || 1);
        }, 0);
    }


    /* =====================================================
       LOGIN  (phone + password -> SMS OTP -> token)
    ===================================================== */

    const loginForm = document.getElementById("zcsLoginForm");

    if (loginForm) {

        const alertBox = document.getElementById("zcsLoginAlert");
        const phoneInput = document.getElementById("zcs-login-phone");
        const passwordField = document.getElementById("zcsLoginPasswordField");
        const passwordInput = document.getElementById("zcs-login-password");
        const otpField = document.getElementById("zcsLoginOtpField");
        const otpInput = document.getElementById("zcs-login-otp");
        const rememberInput = document.getElementById("zcs-login-remember");
        const submitBtn = document.getElementById("zcsLoginSubmit");
        const changeNumber = document.getElementById("zcsLoginChangeNumber");

        const redirectTo = API.safeRedirect(params.get("redirect"), "index.php");

        let otpStep = false;

        if (API.isLoggedIn()) {
            window.location.replace(redirectTo);
            return;
        }

        if (params.get("redirect") === "checkout.php") {
            showAlert(alertBox, "Please sign in to complete your booking.", "error", "zcs-login");
        }

        function showOtpStep(on) {
            otpStep = on;
            otpField.hidden = !on;
            passwordField.hidden = on;
            phoneInput.readOnly = on;
            submitBtn.querySelector("span").textContent = on ? "Verify OTP & Sign In" : "Sign In";
            if (on) {
                otpInput.value = "";
                otpInput.focus();
            }
        }

        changeNumber.addEventListener("click", function (event) {
            event.preventDefault();
            showAlert(alertBox, "");
            showOtpStep(false);
            phoneInput.focus();
        });

        loginForm.addEventListener("submit", function (event) {

            event.preventDefault();

            const phone = API.normalizePhone(phoneInput.value);

            if (phone.length !== 10) {
                showAlert(alertBox, "Enter a valid 10-digit mobile number.", "error", "zcs-login");
                phoneInput.focus();
                return;
            }

            /* STEP 1: password -> send OTP */

            if (!otpStep) {

                if (!passwordInput.value) {
                    showAlert(alertBox, "Enter your password.", "error", "zcs-login");
                    passwordInput.focus();
                    return;
                }

                setBusy(submitBtn, true);

                API.request("login.php", {
                    body: { phone: phone, password: passwordInput.value }
                }).then(function (data) {
                    setBusy(submitBtn, false);
                    showAlert(alertBox, data.message || "OTP sent to your mobile number.", "success", "zcs-login");
                    showOtpStep(true);
                }).catch(function (error) {
                    setBusy(submitBtn, false);
                    showAlert(alertBox, error.message, "error", "zcs-login");
                });

                return;
            }

            /* STEP 2: verify OTP -> session */

            const otp = otpInput.value.trim();

            if (!/^\d{6}$/.test(otp)) {
                showAlert(alertBox, "Enter the 6-digit OTP sent to your phone.", "error", "zcs-login");
                otpInput.focus();
                return;
            }

            setBusy(submitBtn, true);

            API.request("login.php", {
                body: { phone: phone, otp: otp }
            }).then(function (data) {

                API.setSession(data.token, data.user, rememberInput && rememberInput.checked);
                showAlert(alertBox, "Signed in. Redirecting...", "success", "zcs-login");

                // Bring any saved cart from the account into this browser first
                return API.pullCart().then(function () {
                    window.location.href = redirectTo;
                });

            }).catch(function (error) {
                setBusy(submitBtn, false, "Verify OTP & Sign In");
                showAlert(alertBox, error.message, "error", "zcs-login");
            });
        });
    }


    /* =====================================================
       REGISTER
    ===================================================== */

    const signupForm = document.getElementById("zcsSignupForm");

    if (signupForm) {

        const alertBox = document.getElementById("zcsSignupAlert");
        const submitBtn = signupForm.querySelector(".zcs-signup-submit");

        signupForm.addEventListener("submit", function (event) {

            event.preventDefault();

            if (!signupForm.reportValidity()) return;

            const value = function (id) {
                return document.getElementById(id).value.trim();
            };

            const nameParts = value("zcs-signup-name").split(/\s+/);
            const phone = API.normalizePhone(value("zcs-signup-phone"));
            const password = document.getElementById("zcs-signup-password").value;
            const confirm = document.getElementById("zcs-signup-confirm-password").value;
            const pincode = value("zcs-signup-pincode");

            if (phone.length !== 10) {
                showAlert(alertBox, "Enter a valid 10-digit mobile number.", "error", "zcs-signup");
                return;
            }
            if (password.length < 6) {
                showAlert(alertBox, "Password must be at least 6 characters.", "error", "zcs-signup");
                return;
            }
            if (password !== confirm) {
                showAlert(alertBox, "Passwords do not match.", "error", "zcs-signup");
                return;
            }
            if (!/^\d{6}$/.test(pincode)) {
                showAlert(alertBox, "Enter a valid 6-digit pincode.", "error", "zcs-signup");
                return;
            }

            setBusy(submitBtn, true);

            API.request("register.php", {
                body: {
                    firstname: nameParts[0],
                    lastname: nameParts.slice(1).join(" ") || " ",
                    email: value("zcs-signup-email"),
                    phone: phone,
                    address: value("zcs-signup-address") + ", " + value("zcs-signup-city") + " - " + pincode,
                    password: password
                }
            }).then(function () {
                window.location.href = "login.php?success=1";
            }).catch(function (error) {
                setBusy(submitBtn, false);
                showAlert(alertBox, error.message, "error", "zcs-signup");
                alertBox.scrollIntoView({ behavior: "smooth", block: "center" });
            });
        });
    }


    /* =====================================================
       FORGOT PASSWORD  (phone -> SMS OTP -> new password)
    ===================================================== */

    const forgotForm = document.getElementById("zcsForgotForm");

    if (forgotForm) {

        const alertBox = document.getElementById("zcsForgotAlert");
        const phoneInput = document.getElementById("zcs-forgot-phone");
        const resetFields = document.getElementById("zcsForgotResetFields");
        const otpInput = document.getElementById("zcs-forgot-otp");
        const passwordInput = document.getElementById("zcs-forgot-password");
        const confirmInput = document.getElementById("zcs-forgot-confirm");
        const submitBtn = document.getElementById("zcsForgotSubmit");
        const resendLink = document.getElementById("zcsForgotResend");

        let otpStep = false;

        function sendOtp(phone) {
            setBusy(submitBtn, true);
            return API.request("password_reset.php?action=request", { body: { phone: phone } })
                .then(function (data) {
                    setBusy(submitBtn, false, "Reset Password");
                    showAlert(alertBox, data.message, "success", "zcs-login");
                    otpStep = true;
                    resetFields.hidden = false;
                    phoneInput.readOnly = true;
                    otpInput.value = "";
                    otpInput.focus();
                })
                .catch(function (error) {
                    setBusy(submitBtn, false);
                    showAlert(alertBox, error.message, "error", "zcs-login");
                });
        }

        resendLink.addEventListener("click", function (event) {
            event.preventDefault();
            sendOtp(API.normalizePhone(phoneInput.value));
        });

        forgotForm.addEventListener("submit", function (event) {

            event.preventDefault();

            const phone = API.normalizePhone(phoneInput.value);

            if (phone.length !== 10) {
                showAlert(alertBox, "Enter a valid 10-digit mobile number.", "error", "zcs-login");
                phoneInput.focus();
                return;
            }

            if (!otpStep) {
                sendOtp(phone);
                return;
            }

            const otp = otpInput.value.trim();

            if (!/^\d{6}$/.test(otp)) {
                showAlert(alertBox, "Enter the 6-digit OTP sent to your phone.", "error", "zcs-login");
                otpInput.focus();
                return;
            }
            if (passwordInput.value.length < 6) {
                showAlert(alertBox, "Password must be at least 6 characters.", "error", "zcs-login");
                passwordInput.focus();
                return;
            }
            if (passwordInput.value !== confirmInput.value) {
                showAlert(alertBox, "Passwords do not match.", "error", "zcs-login");
                confirmInput.focus();
                return;
            }

            setBusy(submitBtn, true);

            API.request("password_reset.php?action=reset", {
                body: { phone: phone, otp: otp, password: passwordInput.value }
            }).then(function () {
                API.clearSession();
                window.location.href = "login.php?reset=1";
            }).catch(function (error) {
                setBusy(submitBtn, false, "Reset Password");
                showAlert(alertBox, error.message, "error", "zcs-login");
            });
        });
    }


    /* =====================================================
       CONTACT ENQUIRY
    ===================================================== */

    const contactForm = document.getElementById("zcsContactForm");

    if (contactForm) {

        const alertBox = document.getElementById("zcsContactAlert");
        const submitBtn = document.getElementById("zcsContactSubmit");

        contactForm.addEventListener("submit", function (event) {

            event.preventDefault();

            const field = function (name) {
                return contactForm.elements[name] ? contactForm.elements[name].value.trim() : "";
            };
            const body = {
                name: field("name"),
                phone: API.normalizePhone(field("phone")),
                email: field("email"),
                service: field("service"),
                address: field("address"),
                message: field("message"),
                website: field("website")
            };
            const fail = function (message, name) {
                showAlert(alertBox, message, "error", "zcs-login");
                if (name && contactForm.elements[name]) contactForm.elements[name].focus();
            };

            if (!body.name) return fail("Enter your name.", "name");
            if (body.phone.length !== 10) return fail("Enter a valid 10-digit mobile number.", "phone");
            if (body.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(body.email)) return fail("Enter a valid email address.", "email");
            if (!body.service) return fail("Choose a service.", "service");
            if (!body.message) return fail("Tell us about your service requirement.", "message");

            setBusy(submitBtn, true);

            API.request("contact_enquiry.php", { body: body })
                .then(function (data) {
                    setBusy(submitBtn, false);
                    contactForm.reset();
                    showAlert(alertBox, data.message, "success", "zcs-login");
                    alertBox.scrollIntoView({ behavior: "smooth", block: "center" });
                })
                .catch(function (error) {
                    setBusy(submitBtn, false);
                    const errors = error.data && error.data.errors;
                    const first = errors ? Object.keys(errors)[0] : null;
                    fail(first ? errors[first] : error.message, first);
                });
        });
    }


    /* =====================================================
       CART PAGE
    ===================================================== */

    const cartItemsBox = document.getElementById("zcsCartItems");

    if (cartItemsBox) {

        const emptyBox = document.getElementById("zcsCartEmpty");
        const countText = document.getElementById("zcsCartCountText");
        const subtotalEl = document.getElementById("zcsCartSubtotal");
        const totalEl = document.getElementById("zcsCartTotal");
        const clearBtn = document.getElementById("zcsClearCart");
        const checkoutBtn = document.getElementById("zcsCartCheckout");

        function renderCartPage() {

            const cart = getCart();
            const total = cartTotal(cart);

            const maxQty = window.ZenCart ? window.ZenCart.MAX_QUANTITY : 20;

            cartItemsBox.innerHTML = cart.map(function (item) {

                const category = API.categoryOf(item);
                const qty = Number(item.quantity) || 1;
                const id = API.escapeHtml(item.id);

                return (
                    '<article class="zcs-cart-item">' +
                        '<div class="zcs-cart-item-image">' +
                            '<img src="' + API.escapeHtml(item.image || ZCS_FALLBACK_IMAGE) + '" onerror="this.onerror=null;this.src=\'' + API.escapeHtml(ZCS_FALLBACK_IMAGE) + '\'" alt="' + API.escapeHtml(item.name) + '">' +
                        "</div>" +
                        '<div class="zcs-cart-item-content">' +
                            '<div class="zcs-cart-item-top">' +
                                "<div>" +
                                    '<span class="zcs-cart-item-category">' + API.escapeHtml(category.toUpperCase()) + "</span>" +
                                    "<h3>" + API.escapeHtml(item.name) + "</h3>" +
                                "</div>" +
                                '<button type="button" class="zcs-cart-remove-btn" data-cart-remove="' + API.escapeHtml(item.id) + '" aria-label="Remove ' + API.escapeHtml(item.name) + '">' +
                                    '<i class="fa-solid fa-trash"></i>' +
                                "</button>" +
                            "</div>" +
                            '<p class="zcs-cart-item-description">' +
                                "Professional " + API.escapeHtml(category.toLowerCase()) + " at your doorstep by experienced Zen Home Experts technicians." +
                            "</p>" +
                            '<div class="zcs-cart-item-bottom">' +
                                '<div class="zcs-cart-item-price">' +
                                    "<small>" + (qty > 1 ? qty + " × " + API.formatPrice(item.price) : "Service Price") + "</small>" +
                                    "<strong>" + API.formatPrice(Number(item.price || 0) * qty) + "</strong>" +
                                "</div>" +
                                '<div class="zcs-cart-qty" role="group" aria-label="Quantity for ' + API.escapeHtml(item.name) + '">' +
                                    '<button type="button" data-cart-qty="' + id + '" data-step="-1" aria-label="Decrease quantity"' + (qty <= 1 ? " disabled" : "") + '><i class="fa-solid fa-minus"></i></button>' +
                                    '<span aria-live="polite">' + qty + "</span>" +
                                    '<button type="button" data-cart-qty="' + id + '" data-step="1" aria-label="Increase quantity"' + (qty >= maxQty ? " disabled" : "") + '><i class="fa-solid fa-plus"></i></button>' +
                                "</div>" +
                            "</div>" +
                        "</div>" +
                    "</article>"
                );
            }).join("");

            if (emptyBox) emptyBox.style.display = cart.length ? "none" : "";
            if (clearBtn) clearBtn.style.display = cart.length ? "" : "none";
            if (subtotalEl) subtotalEl.textContent = API.formatPrice(total);
            if (totalEl) totalEl.textContent = API.formatPrice(total);

            if (countText) {
                countText.textContent = cart.length
                    ? cart.length + (cart.length === 1 ? " service" : " services") + " selected."
                    : "Your cart is empty.";
            }
        }

        cartItemsBox.addEventListener("click", function (event) {
            const btn = event.target.closest("[data-cart-remove]");
            if (btn && window.ZenCart) {
                window.ZenCart.removeFromCart(btn.getAttribute("data-cart-remove"));
                return;
            }
            const qtyBtn = event.target.closest("[data-cart-qty]");
            if (qtyBtn && window.ZenCart) {
                const id = qtyBtn.getAttribute("data-cart-qty");
                const item = getCart().find(function (entry) { return entry.id === id; });
                if (item) {
                    window.ZenCart.setQuantity(id, (Number(item.quantity) || 1) + Number(qtyBtn.getAttribute("data-step")));
                }
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener("click", function () {
                if (getCart().length && window.confirm("Remove all services from your cart?")) {
                    API.clearCart();
                }
            });
        }

        if (checkoutBtn) {
            checkoutBtn.addEventListener("click", function (event) {
                if (!getCart().length) {
                    event.preventDefault();
                    API.notify("Add a service to your cart first.", "warning");
                }
            });
        }

        document.addEventListener("zen:cart-changed", renderCartPage);
        document.addEventListener("zen:cart-refresh", renderCartPage);

        renderCartPage();
        API.refreshCart().then(notifyRemoved);
    }


    /* =====================================================
       CHECKOUT PAGE
    ===================================================== */

    const checkoutForm = document.getElementById("zcsCheckoutForm");

    if (checkoutForm) {

        const itemsBox = document.getElementById("zcsCheckoutItems");
        const subtotalEl = document.getElementById("zcsCheckoutSubtotal");
        const totalEl = document.getElementById("zcsCheckoutTotal");
        const cartDataInput = document.getElementById("zcsCheckoutCartData");
        const alertBox = document.getElementById("zcsCheckoutAlert");
        const submitBtn = document.getElementById("zcsCheckoutSubmit");
        const dateInput = document.getElementById("checkout_date");
        const timeSelect = document.getElementById("checkout_time");
        const couponInput = document.getElementById("zcsCouponCode");
        const couponBtn = document.getElementById("zcsCouponApply");
        const couponMsg = document.getElementById("zcsCouponMsg");
        const discountRow = document.getElementById("zcsCheckoutDiscountRow");
        const discountLabel = document.getElementById("zcsCheckoutDiscountLabel");
        const discountEl = document.getElementById("zcsCheckoutDiscount");

        const field = function (id) {
            return document.getElementById(id);
        };

        /* Coupon: previewed by validate_coupon.php, applied for real by book_appointment.php */

        let coupon = null;   // {code, amount, discount, final_amount}

        // saverpacks ids for server-side pricing (null if an old cart item has none yet)
        function packItems(cart) {
            const items = cart.map(function (item) {
                return { pack_id: parseInt(item.pack_id, 10) || 0, quantity: Number(item.quantity) || 1 };
            });
            return items.length && items.every(function (item) { return item.pack_id > 0; }) ? items : null;
        }

        function couponNote(message, type) {
            if (!couponMsg) return;
            couponMsg.className = "zcs-checkout-coupon-msg" + (type ? " is-" + type : "");
            couponMsg.innerHTML = message
                ? API.escapeHtml(message) + (coupon ? ' <button type="button" class="zcs-checkout-coupon-remove">Remove</button>' : "")
                : "";
        }

        function renderTotals(total) {
            const discount = coupon ? coupon.discount : 0;
            subtotalEl.textContent = API.formatPrice(total);
            totalEl.textContent = API.formatPrice(Math.max(total - discount, 0));
            if (discountRow) {
                discountRow.hidden = !coupon;
                if (coupon) {
                    discountLabel.textContent = "Coupon (" + coupon.code + ")";
                    discountEl.textContent = "-" + API.formatPrice(discount);
                }
            }
        }

        function removeCoupon(message, type) {
            coupon = null;
            renderTotals(cartTotal(getCart()));
            couponNote(message || "", type);
        }

        function applyCoupon(code, quiet) {

            const total = cartTotal(getCart());
            code = String(code || "").trim().toUpperCase();

            if (!code) {
                removeCoupon("Enter a coupon code.", "error");
                return Promise.resolve();
            }
            if (total <= 0) {
                removeCoupon("Add a service to your cart first.", "error");
                return Promise.resolve();
            }

            if (couponBtn) couponBtn.disabled = true;
            if (!quiet) couponNote("Checking coupon...");

            const couponBody = { coupon_code: code, amount: total, user_id: (API.getUser() || {}).id };
            const items = packItems(getCart());
            if (items) couponBody.items = items;

            return API.request("validate_coupon.php", {
                body: couponBody
            }).then(function (res) {
                const d = res.data || {};
                coupon = { code: d.code || code, amount: total, discount: Number(d.discount) || 0, final_amount: Number(d.final_amount) || total };
                if (couponInput) couponInput.value = coupon.code;
                renderTotals(total);
                couponNote(res.message || "Coupon applied.", "success");
            }).catch(function (error) {
                removeCoupon(error.message, "error");
            }).then(function () {
                if (couponBtn) couponBtn.disabled = false;
            });
        }

        if (couponBtn && couponInput) {
            couponBtn.addEventListener("click", function () {
                applyCoupon(couponInput.value);
            });
            couponInput.addEventListener("keydown", function (event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    applyCoupon(couponInput.value);
                }
            });
            couponMsg.addEventListener("click", function (event) {
                if (event.target.closest(".zcs-checkout-coupon-remove")) {
                    couponInput.value = "";
                    removeCoupon();
                }
            });
        }

        function renderCheckoutItems() {

            const cart = getCart();
            const total = cartTotal(cart);

            // Cart changed after a coupon was applied: re-check it against the new total
            if (coupon && coupon.amount !== total) {
                const code = coupon.code;
                coupon = null;
                applyCoupon(code, true);
            }

            itemsBox.innerHTML = cart.length
                ? cart.map(function (item) {
                    return (
                        '<div class="zcs-checkout-service-item">' +
                            '<div class="zcs-checkout-service-image">' +
                                '<img src="' + API.escapeHtml(item.image || ZCS_FALLBACK_IMAGE) + '" onerror="this.onerror=null;this.src=\'' + API.escapeHtml(ZCS_FALLBACK_IMAGE) + '\'" alt="' + API.escapeHtml(item.name) + '">' +
                            "</div>" +
                            '<div class="zcs-checkout-service-info">' +
                                "<span>" + API.escapeHtml(API.categoryOf(item).toUpperCase()) + "</span>" +
                                "<h4>" + API.escapeHtml(item.name) + ((Number(item.quantity) || 1) > 1 ? " × " + Number(item.quantity) : "") + "</h4>" +
                                "<strong>" + API.formatPrice(Number(item.price || 0) * (Number(item.quantity) || 1)) + "</strong>" +
                            "</div>" +
                        "</div>"
                    );
                }).join("")
                : '<p class="zcs-cart-item-description">Your cart is empty. <a href="services.php">Browse services</a></p>';

            renderTotals(total);
            cartDataInput.value = JSON.stringify(cart);
            submitBtn.disabled = !cart.length;
        }

        /* Prefill from the signed-in account */

        const user = API.getUser();

        if (API.isLoggedIn() && user) {
            const fullName = [user.first_name, user.last_name].filter(function (v) { return v && v.trim(); }).join(" ");
            if (fullName && !field("checkout_name").value) field("checkout_name").value = fullName;
            if (user.phone && !field("checkout_phone").value) field("checkout_phone").value = user.phone;
            if (user.email && !field("checkout_email").value) field("checkout_email").value = user.email;
            if (user.address && !field("checkout_address").value) field("checkout_address").value = user.address;
        } else {
            showAlert(
                alertBox,
                'Please <a href="login.php?redirect=checkout.php">sign in</a> to confirm your booking.',
                "error",
                "zcs-login",
                true
            );
        }

        /* Dates: from today; slots come from the backend */

        const today = new Date();
        const pad = function (n) { return String(n).padStart(2, "0"); };
        dateInput.min = today.getFullYear() + "-" + pad(today.getMonth() + 1) + "-" + pad(today.getDate());

        const defaultSlots = timeSelect.innerHTML;

        dateInput.addEventListener("change", function () {

            if (!dateInput.value) return;

            timeSelect.innerHTML = '<option value="">Loading slots...</option>';

            API.request("checkSlot.php", { body: { date: dateInput.value } })
                .then(function (data) {

                    const slots = Array.isArray(data.data) ? data.data : [];

                    timeSelect.innerHTML = slots.length
                        ? '<option value="">Select Time</option>' + slots.map(function (slot) {
                            return '<option value="' + API.escapeHtml(slot) + '">' + API.escapeHtml(slot) + "</option>";
                        }).join("")
                        : '<option value="">No slots left on this date</option>';
                })
                .catch(function () {
                    timeSelect.innerHTML = defaultSlots;
                });
        });

        /* Submit: booking -> (optional) PhonePe */

        function showDone(bookingId, note) {

            const section = checkoutForm.closest(".zcs-checkout-container");

            checkoutForm.remove();

            // Reuses the cart page's "empty cart" box styles
            const done = document.createElement("div");
            done.className = "zcs-cart-empty";
            done.innerHTML =
                '<div class="zcs-cart-empty-icon"><i class="fa-solid fa-circle-check"></i></div>' +
                "<h3>Booking Received</h3>" +
                "<p><strong>Booking ID: " + API.escapeHtml(bookingId) + "</strong></p>" +
                "<p>" + API.escapeHtml(note) + "</p>" +
                '<a href="my-bookings.php" class="zcs-cart-browse-btn">View My Bookings <i class="fa-solid fa-arrow-right"></i></a>';

            section.appendChild(done);
            done.scrollIntoView({ behavior: "smooth", block: "center" });
        }

        checkoutForm.addEventListener("submit", function (event) {

            event.preventDefault();

            if (!API.isLoggedIn()) {
                window.location.href = "login.php?redirect=checkout.php";
                return;
            }

            if (!checkoutForm.reportValidity()) return;

            if (!getCart().length) {
                showAlert(alertBox, "Your cart is empty.", "error", "zcs-login");
                return;
            }

            showAlert(alertBox, "");
            setBusy(submitBtn, true);
            const shownTotal = cartTotal(getCart());

            // Latest prices / availability before booking
            API.refreshCart().then(function (result) {
                if (!result.removed.length && cartTotal(result.cart) !== shownTotal) {
                    setBusy(submitBtn, false);
                    showAlert(alertBox, "Some prices were updated. Please check the new total and confirm again.", "error", "zcs-login");
                    return;
                }
                if (result.removed.length) {
                    setBusy(submitBtn, false);
                    notifyRemoved(result);
                    showAlert(alertBox, "Some services are no longer available and were removed from your cart. Please review your booking and confirm again.", "error", "zcs-login");
                    return;
                }
                if (!result.cart.length) {
                    setBusy(submitBtn, false);
                    showAlert(alertBox, "Your cart is empty.", "error", "zcs-login");
                    return;
                }
                submitBooking(result.cart);
            });
        });

        function submitBooking(cart) {

            const total = cartTotal(cart);
            const paymentMethod = (checkoutForm.querySelector('input[name="payment_method"]:checked') || {}).value || "after_service";

            const categories = [];
            cart.forEach(function (item) {
                const category = API.categoryOf(item);
                if (categories.indexOf(category) === -1) categories.push(category);
            });

            const val = function (id) { return field(id).value.trim(); };

            const landmark = [
                val("checkout_landmark"),
                val("checkout_notes") ? "Note: " + val("checkout_notes") : "",
                "Contact: " + val("checkout_name") + " " + API.normalizePhone(val("checkout_phone"))
            ].filter(Boolean).join(" | ");

            const booking = {
                category: categories.join(", "),
                subcategories: cart.map(function (item) {
                    const qty = Number(item.quantity) || 1;
                    return item.name + (qty > 1 ? " x" + qty : "") + " (" + API.formatPrice(Number(item.price || 0) * qty) + ")";
                }).join(", "),
                date: dateInput.value,
                service_slot: timeSelect.value,
                location: val("checkout_address") + ", " + val("checkout_area") + ", " + val("checkout_city") + " - " + val("checkout_pincode"),
                landmark: landmark,
                user_id: (API.getUser() || {}).id,
                amount: total,
                payment_method: (paymentMethod === "online" && total > 0) ? "online" : "cash"
            };

            // The server prices the booking from these saverpacks ids ("amount" is only a fallback)
            const items = packItems(cart);
            if (items) booking.items = items;

            if (coupon) booking.coupon_code = coupon.code;

            API.request("book_appointment.php", { body: booking })
                .then(function (data) {

                    const bookingId = data.unique_booking_id;
                    const payable = data.amount_payable != null ? Number(data.amount_payable) : total;
                    const saved = data.coupon && Number(data.discount) > 0
                        ? " Coupon " + data.coupon.code + " saved you " + API.formatPrice(data.discount) + "."
                        : "";

                    if (paymentMethod !== "online" || payable <= 0) {
                        API.clearCart();
                        showDone(bookingId, "Our team will call you to confirm the technician and timing." + saved + (payable > 0 ? " You can pay " + API.formatPrice(payable) + " after the service is done." : ""));
                        return;
                    }

                    return API.request("payments.php", {
                        body: {
                            amount: payable,
                            order_id: "ZC-" + bookingId,
                            message: "Zen Home Experts booking " + bookingId
                        }
                    }).then(function (pay) {

                        try {
                            localStorage.setItem(API.PENDING_PAYMENT_KEY, JSON.stringify({
                                transactionId: pay.transaction_id,
                                bookingId: bookingId
                            }));
                        } catch (e) { /* ignore */ }

                        window.location.href = pay.payment_url;

                    }).catch(function (error) {
                        API.clearCart();
                        showDone(
                            bookingId,
                            "Your booking is saved, but online payment could not be started (" + error.message + "). You can pay after the service."
                        );
                    });
                })
                .catch(function (error) {
                    setBusy(submitBtn, false);
                    const errors = (error.data && error.data.errors) || {};
                    if (errors.coupon_code) {
                        removeCoupon(errors.coupon_code, "error");
                        showAlert(alertBox, "Coupon not applied: " + errors.coupon_code + " It has been removed. Confirm again to book without it, or try another code.", "error", "zcs-login");
                        return;
                    }
                    if (errors.items) {
                        API.refreshCart().then(notifyRemoved);
                    }
                    showAlert(alertBox, error.message, "error", "zcs-login");
                });
        }

        document.addEventListener("zen:cart-changed", renderCheckoutItems);
        document.addEventListener("zen:cart-refresh", renderCheckoutItems);

        renderCheckoutItems();
        API.refreshCart().then(notifyRemoved);
    }


    /* =====================================================
       MY BOOKINGS
    ===================================================== */

    const bookingsList = document.getElementById("zcsBookingsList");

    if (bookingsList) {

        if (!API.isLoggedIn()) {
            window.location.replace("login.php?redirect=my-bookings.php");
            return;
        }

        const alertBox = document.getElementById("zcsBookingsAlert");
        const emptyBox = document.getElementById("zcsBookingsEmpty");
        const moreBtn = document.getElementById("zcsBookingsMore");
        const filterBox = document.getElementById("zcsBookingsFilter");

        let page = 1;
        let status = "";

        function formatDate(value) {
            if (!value) return "";
            const d = new Date(String(value).replace(" ", "T"));
            return isNaN(d) ? value : d.toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
        }

        function row(label, value) {
            return value ? "<div><dt>" + label + "</dt><dd>" + API.escapeHtml(value) + "</dd></div>" : "";
        }

        function bookingCard(b) {
            const services = String(b.services || "").split(/,\s*(?![^()]*\))/).filter(Boolean);
            const tech = b.technician
                ? row("Technician", b.technician.name + (b.technician.phone ? " (" + b.technician.phone + ")" : ""))
                : "";
            const discount = b.discount > 0
                ? row("Coupon", (b.coupon_code || "Discount") + " (-" + API.formatPrice(b.discount) + ")")
                : "";

            return '<article class="zcs-booking-card">' +
                '<header class="zcs-booking-head">' +
                    "<div><span>Booking ID</span><strong>" + API.escapeHtml(b.booking_id) + "</strong></div>" +
                    '<em class="zcs-booking-status zcs-booking-status-' + API.escapeHtml(String(b.status).toLowerCase()) + '">' +
                        API.escapeHtml(b.status_label) + "</em>" +
                "</header>" +
                "<h3>" + API.escapeHtml(b.category) + "</h3>" +
                (services.length ? "<ul>" + services.map(function (s) { return "<li>" + API.escapeHtml(s) + "</li>"; }).join("") + "</ul>" : "") +
                "<dl>" +
                    row("Service date", formatDate(b.date) + (b.slot ? ", " + b.slot : "")) +
                    row("Address", b.address) +
                    tech +
                    discount +
                    row("Payment", b.payment_method + " - " + b.payment_status) +
                    row("Booked on", formatDate(b.created_at)) +
                    (b.cancel_reason ? row("Cancel reason", b.cancel_reason) : "") +
                "</dl>" +
                '<footer class="zcs-booking-foot"><span>Amount</span><strong>' +
                    (b.amount > 0 ? API.formatPrice(b.amount) : "After inspection") + "</strong></footer>" +
            "</article>";
        }

        function load(reset) {
            if (reset) {
                page = 1;
                bookingsList.innerHTML = '<p class="zcs-bookings-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading your bookings...</p>';
            }
            emptyBox.hidden = true;
            moreBtn.hidden = true;
            showAlert(alertBox, "");

            API.request("my_bookings.php?limit=10&page=" + page + (status ? "&status=" + status : ""))
                .then(function (res) {
                    const data = res.data || {};
                    const items = data.items || [];
                    const pages = (data.pagination || {}).total_pages || 1;
                    if (reset) bookingsList.innerHTML = "";
                    bookingsList.insertAdjacentHTML("beforeend", items.map(bookingCard).join(""));
                    emptyBox.hidden = bookingsList.children.length > 0;
                    moreBtn.hidden = page >= pages;
                })
                .catch(function (error) {
                    if (error.status === 401) {
                        window.location.replace("login.php?redirect=my-bookings.php");
                        return;
                    }
                    if (reset) bookingsList.innerHTML = "";
                    showAlert(alertBox, error.message, "error", "zcs-login");
                });
        }

        moreBtn.addEventListener("click", function () {
            page += 1;
            load(false);
        });

        filterBox.addEventListener("click", function (event) {
            const btn = event.target.closest("button[data-status]");
            if (!btn) return;
            filterBox.querySelectorAll("button").forEach(function (b) { b.classList.toggle("is-active", b === btn); });
            status = btn.dataset.status;
            load(true);
        });

        load(true);
    }

});
