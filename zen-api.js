/* =========================================================
   ZEN HOME EXPERTS - BACKEND CONNECTION
   Talks to the PHP API in /api: login session, header state,
   cart sync and PhonePe payment confirmation.
========================================================= */

(function () {

    const API_BASE = "api/";

    const TOKEN_KEY = "zenToken";
    const USER_KEY = "zenUser";
    const CART_KEY = "zenCareCart";
    const PENDING_PAYMENT_KEY = "zenPendingPayment";


    /* =====================================================
       STORAGE (localStorage can throw in private mode)
    ===================================================== */

    function storeGet(store, key) {
        try { return store.getItem(key); } catch (e) { return null; }
    }

    function storeSet(store, key, value) {
        try { store.setItem(key, value); } catch (e) { /* ignore */ }
    }

    function storeRemove(store, key) {
        try { store.removeItem(key); } catch (e) { /* ignore */ }
    }


    /* =====================================================
       SESSION
    ===================================================== */

    function getToken() {
        return storeGet(localStorage, TOKEN_KEY) || storeGet(sessionStorage, TOKEN_KEY);
    }

    function getUser() {
        try {
            return JSON.parse(storeGet(localStorage, USER_KEY) || storeGet(sessionStorage, USER_KEY));
        } catch (e) {
            return null;
        }
    }

    function setSession(token, user, remember) {
        clearSession();
        const store = remember ? localStorage : sessionStorage;
        storeSet(store, TOKEN_KEY, token);
        storeSet(store, USER_KEY, JSON.stringify(user || {}));
    }

    function clearSession() {
        [localStorage, sessionStorage].forEach(function (store) {
            storeRemove(store, TOKEN_KEY);
            storeRemove(store, USER_KEY);
        });
    }

    function isLoggedIn() {
        return !!getToken();
    }


    /* =====================================================
       JSON PARSE
       Some older endpoints can print PHP notices around their
       JSON, so fall back to the first parseable JSON object.
    ===================================================== */

    function parseJson(text) {

        if (!text) return {};

        try {
            return JSON.parse(text);
        } catch (e) { /* try the leading JSON object below */ }

        const start = text.indexOf("{");
        if (start === -1) return null;

        let end = text.indexOf("}", start);

        while (end !== -1) {
            try {
                return JSON.parse(text.slice(start, end + 1));
            } catch (e) {
                end = text.indexOf("}", end + 1);
            }
        }

        return null;
    }


    /* =====================================================
       REQUEST
       Resolves with the JSON body; rejects with an Error whose
       message is the API's message and .status the HTTP code.
    ===================================================== */

    function request(path, options) {

        options = options || {};

        const headers = { "Accept": "application/json" };
        const token = getToken();

        let url = API_BASE + path;

        if (token) {
            headers["Authorization"] = "Bearer " + token;

            // Some hosts drop the Authorization header; auth_helper.php also reads ?token=
            url += (url.indexOf("?") === -1 ? "?" : "&") + "token=" + encodeURIComponent(token);
        }

        const init = {
            method: options.method || (options.body ? "POST" : "GET"),
            headers: headers
        };

        if (options.body !== undefined) {
            headers["Content-Type"] = "application/json";
            init.body = JSON.stringify(options.body);
        }

        return fetch(url, init).then(function (response) {

            return response.text().then(function (text) {

                const data = parseJson(text);

                const failed =
                    !response.ok ||
                    !data ||
                    data.status === "error" ||
                    data.success === false;

                if (failed) {

                    if (response.status === 401 && token) {
                        clearSession();
                        updateHeader();
                    }

                    const error = new Error(
                        (data && (data.message || data.error)) ||
                        "Something went wrong. Please try again."
                    );
                    error.status = response.status;
                    error.data = data;
                    throw error;
                }

                return data;
            });
        });
    }


    /* =====================================================
       HELPERS
    ===================================================== */

    function normalizePhone(value) {
        let phone = String(value || "").replace(/[^0-9]/g, "");
        if (phone.length === 11 && phone.charAt(0) === "0") phone = phone.slice(1);
        if (phone.length === 12 && phone.slice(0, 2) === "91") phone = phone.slice(2);
        return phone;
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatPrice(amount) {
        return "₹" + Number(amount || 0).toLocaleString("en-IN");
    }

    // Only allow redirects to pages on this site
    function safeRedirect(target, fallback) {
        if (target && /^[a-z0-9\-_]+\.php([?#].*)?$/i.test(target)) {
            return target;
        }
        return fallback;
    }

    const CATEGORIES = [
        ["ac-service", "AC Service"],
        ["carpenter", "Carpenter Service"],
        ["chimney", "Chimney Repair"],
        ["cleaning", "Home Cleaning"],
        ["pest", "Pest Control"],
        ["refrigerator", "Refrigerator Repair"],
        ["facial", "Salon"],
        ["salon", "Salon"],
        ["washing", "Washing Machine Repair"],
        ["water-purifier", "Water Purifier"]
    ];

    function categoryOf(item) {
        if (item && item.category) return item.category;
        const url = String((item && item.url) || "").toLowerCase();
        for (let i = 0; i < CATEGORIES.length; i++) {
            if (url.indexOf(CATEGORIES[i][0]) > -1) {
                return CATEGORIES[i][1];
            }
        }
        return "Home Service";
    }

    function notify(message, type, duration) {

        const old = document.querySelector(".zcs-cart-toast");
        if (old) old.remove();

        const toast = document.createElement("div");
        toast.className = "zcs-cart-toast";
        if (type === "warning") toast.classList.add("zcs-cart-toast-warning");

        const icon = type === "warning" ? "fa-circle-exclamation" : "fa-circle-check";

        toast.innerHTML =
            '<span class="zcs-cart-toast-icon"><i class="fa-solid ' + icon + '"></i></span>' +
            "<span>" + escapeHtml(message) + "</span>";

        document.body.appendChild(toast);

        setTimeout(function () { toast.classList.add("zcs-cart-toast-show"); }, 20);
        setTimeout(function () {
            toast.classList.remove("zcs-cart-toast-show");
            setTimeout(function () { toast.remove(); }, 300);
        }, duration || 2400);
    }


    /* =====================================================
       HEADER: SIGN IN / SIGN OUT
    ===================================================== */

    function updateHeader() {

        const user = isLoggedIn() ? getUser() : null;
        const name = user && user.first_name ? user.first_name : "My Account";

        const signIn = document.querySelector(".zcs-signin-btn");
        const signUp = document.querySelector(".zcs-signup-btn");
        const mobileSignIn = document.querySelector('.zcs-mobile-account a[href="login.php"], .zcs-mobile-account a[data-zen-account]');
        const mobileRegister = document.querySelector(".zcs-mobile-register");

        if (user) {

            if (signIn) {
                signIn.setAttribute("href", "my-bookings.php");
                signIn.setAttribute("title", "My Bookings");
                signIn.innerHTML = '<i class="fa-regular fa-user"></i><span>' + escapeHtml(name) + "</span>";
            }
            if (signUp) {
                signUp.setAttribute("href", "#");
                signUp.setAttribute("data-zen-logout", "");
                signUp.innerHTML = 'Sign Out <i class="fa-solid fa-arrow-right-from-bracket"></i>';
            }
            if (mobileSignIn) {
                mobileSignIn.setAttribute("href", "my-bookings.php");
                mobileSignIn.setAttribute("data-zen-account", "");
                mobileSignIn.innerHTML = '<i class="fa-regular fa-user"></i> ' + escapeHtml(name) + " - My Bookings";
            }
            if (mobileRegister) {
                mobileRegister.setAttribute("href", "#");
                mobileRegister.setAttribute("data-zen-logout", "");
                mobileRegister.innerHTML = 'Sign Out <i class="fa-solid fa-arrow-right-from-bracket"></i>';
            }

        } else {

            if (signIn) {
                signIn.setAttribute("href", "login.php");
                signIn.removeAttribute("title");
                signIn.innerHTML = '<i class="fa-regular fa-user"></i><span>Sign In</span>';
            }
            if (signUp) {
                signUp.setAttribute("href", "register.php");
                signUp.removeAttribute("data-zen-logout");
                signUp.innerHTML = 'Sign Up <i class="fa-solid fa-arrow-right"></i>';
            }
            if (mobileSignIn) {
                mobileSignIn.setAttribute("href", "login.php");
                mobileSignIn.innerHTML = '<i class="fa-regular fa-user"></i> Sign In';
            }
            if (mobileRegister) {
                mobileRegister.setAttribute("href", "register.php");
                mobileRegister.removeAttribute("data-zen-logout");
                mobileRegister.innerHTML = 'Create Account <i class="fa-solid fa-arrow-right"></i>';
            }
        }
    }

    function logout() {

        const done = function () {
            clearSession();
            storeRemove(localStorage, CART_KEY);
            window.location.href = "index.php";
        };

        request("logout.php", { method: "POST", body: {} }).then(done, done);
    }

    document.addEventListener("click", function (event) {
        const link = event.target.closest("[data-zen-logout]");
        if (link) {
            event.preventDefault();
            logout();
        }
    });


    /* =====================================================
       CART SYNC
       The cart lives in localStorage (works for guests);
       when signed in it is also saved to api/cart.php so it
       follows the customer across devices.
    ===================================================== */

    function readLocalCart() {
        try {
            const cart = JSON.parse(storeGet(localStorage, CART_KEY));
            return Array.isArray(cart) ? cart : [];
        } catch (e) {
            return [];
        }
    }

    function writeLocalCart(cart) {
        storeSet(localStorage, CART_KEY, JSON.stringify(cart));
        document.dispatchEvent(new CustomEvent("zen:cart-refresh"));
    }

    function pushCart(cart) {
        if (!isLoggedIn()) return Promise.resolve();
        return request("cart.php", { method: "POST", body: { items: cart } }).catch(function () { /* keep local copy */ });
    }

    function clearCart() {
        writeLocalCart([]);
        if (isLoggedIn()) {
            return request("cart.php", { method: "DELETE" }).catch(function () { /* ignore */ });
        }
        return Promise.resolve();
    }

    // Merge the server cart into the local one after sign-in / on page load
    function pullCart() {

        if (!isLoggedIn()) return Promise.resolve();

        return request("cart.php").then(function (data) {

            const local = readLocalCart();
            const merged = local.slice();

            (data.items || []).forEach(function (item) {
                if (item && item.id && !merged.some(function (m) { return m.id === item.id; })) {
                    merged.push(item);
                }
            });

            if (merged.length !== local.length) {
                writeLocalCart(merged);
            }
            if (merged.length !== (data.items || []).length) {
                pushCart(merged);
            }

        }).catch(function () { /* offline: keep local cart */ });
    }

    /* Re-reads the cart from the live catalog (api/catalog_services.php):
       current name, price and saverpacks id for each service, and drops
       services that were disabled or removed in the admin panel.
       Resolves with {cart, removed: [names]}; keeps the cart when offline. */
    function refreshCart() {

        const cart = readLocalCart();
        if (!cart.length) return Promise.resolve({ cart: cart, removed: [] });

        const slugPattern = /^[a-z0-9]+(-[a-z0-9]+)*$/;
        const slugs = cart.map(function (item) { return String(item.id || ""); }).filter(function (id) { return slugPattern.test(id); });

        const lookup = slugs.length
            ? request("catalog_services.php?limit=100&slugs=" + encodeURIComponent(slugs.join(",")))
            : Promise.resolve({ data: { items: [] } });

        return lookup.then(function (res) {

            const live = {};
            ((res.data && res.data.items) || []).forEach(function (service) {
                live[service.slug] = service;
                live["service-" + service.id] = service;
            });

            const removed = [];
            const next = [];

            cart.forEach(function (item) {
                const service = live[String(item.id || "")];
                if (!service) {
                    removed.push(item.name || String(item.id || "Service"));
                    return;
                }
                next.push({
                    id: service.slug,
                    pack_id: service.pack_id,
                    name: service.name,
                    price: Number(service.price) || 0,
                    image: service.image || "",
                    url: service.url || item.url || "",
                    category: service.category,
                    quantity: Math.max(1, Math.min(20, parseInt(item.quantity, 10) || 1))
                });
            });

            if (JSON.stringify(next) !== JSON.stringify(cart)) {
                writeLocalCart(next);
                pushCart(next);
            }
            return { cart: next, removed: removed };

        }).catch(function () {
            return { cart: cart, removed: [], offline: true };
        });
    }

    // common.js fires this whenever the customer adds/removes items
    document.addEventListener("zen:cart-changed", function (event) {
        pushCart(event.detail || readLocalCart());
    });


    /* =====================================================
       PAYMENT RETURN
       PhonePe sends the customer back to the site without the
       order id, so the id is kept in localStorage before leaving.
    ===================================================== */

    function checkPendingPayment() {

        let pending = null;

        try {
            pending = JSON.parse(storeGet(localStorage, PENDING_PAYMENT_KEY));
        } catch (e) {
            pending = null;
        }

        if (!pending || !pending.transactionId) return;

        request("paymentConfirmation.php?transactionId=" + encodeURIComponent(pending.transactionId))
            .then(function (data) {

                const state = String(data.state || "").toUpperCase();

                if (state === "COMPLETED" || state === "SUCCESS") {
                    storeRemove(localStorage, PENDING_PAYMENT_KEY);
                    clearCart();
                    notify("Payment successful. Booking " + pending.bookingId + " is confirmed.", "success", 7000);
                } else if (state === "FAILED") {
                    storeRemove(localStorage, PENDING_PAYMENT_KEY);
                    notify("Payment failed. Your booking " + pending.bookingId + " is saved — you can pay after the service.", "warning", 8000);
                } else {
                    notify("Your payment is still processing. We'll update your booking once it completes.", "warning", 6000);
                }
            })
            .catch(function () { /* try again on next page load */ });
    }


    /* =====================================================
       PUBLIC API
    ===================================================== */

    window.ZenAPI = {
        request: request,
        getToken: getToken,
        getUser: getUser,
        setSession: setSession,
        clearSession: clearSession,
        isLoggedIn: isLoggedIn,
        logout: logout,
        updateHeader: updateHeader,
        pullCart: pullCart,
        pushCart: pushCart,
        refreshCart: refreshCart,
        clearCart: clearCart,
        readLocalCart: readLocalCart,
        normalizePhone: normalizePhone,
        escapeHtml: escapeHtml,
        formatPrice: formatPrice,
        safeRedirect: safeRedirect,
        categoryOf: categoryOf,
        notify: notify,
        PENDING_PAYMENT_KEY: PENDING_PAYMENT_KEY
    };


    /* =====================================================
       ON EVERY PAGE
    ===================================================== */

    document.addEventListener("DOMContentLoaded", function () {

        updateHeader();
        checkPendingPayment();

        if (!isLoggedIn()) return;

        // Confirm the saved token is still valid, refresh the user, then sync cart
        request("check_session.php").then(function (data) {

            const store = storeGet(localStorage, TOKEN_KEY) ? localStorage : sessionStorage;
            if (data.user) storeSet(store, USER_KEY, JSON.stringify(data.user));
            updateHeader();

            pullCart();

        }).catch(function () { /* 401 already cleared the session */ });
    });

})();
