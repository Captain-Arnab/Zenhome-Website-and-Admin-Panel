// Fallback image for cart/checkout thumbnails: Site Settings logo (set in header.php), else the static file.
const ZCS_FALLBACK_IMAGE = (typeof window !== "undefined" && window.ZCS_LOGO) || "images/logo.png";

document.addEventListener("DOMContentLoaded", function () {

    const mobileToggle = document.getElementById("zcsMobileToggle");
    const mobileMenu = document.getElementById("zcsMobileMenu");

    const servicesBtn = document.getElementById("zcsMobileServicesBtn");
    const servicesMenu = document.getElementById("zcsMobileSubmenu");


    // MAIN MOBILE MENU
    if (mobileToggle && mobileMenu) {

        mobileToggle.addEventListener("click", function () {

            mobileToggle.classList.toggle("active");
            mobileMenu.classList.toggle("active");

        });

    }


    // MOBILE SERVICES DROPDOWN
    if (servicesBtn && servicesMenu) {

        servicesBtn.addEventListener("click", function () {

            servicesBtn.classList.toggle("active");
            servicesMenu.classList.toggle("active");

        });

    }


    // CLOSE MOBILE MENU AFTER CLICKING NORMAL LINKS
    document.querySelectorAll(".zcs-mobile-menu a").forEach(function (link) {

        link.addEventListener("click", function () {

            if (mobileToggle && mobileMenu) {
                mobileToggle.classList.remove("active");
                mobileMenu.classList.remove("active");
            }

        });

    });


    // RESET MENU ON DESKTOP
    window.addEventListener("resize", function () {

        if (window.innerWidth > 1050) {

            if (mobileToggle) {
                mobileToggle.classList.remove("active");
            }

            if (mobileMenu) {
                mobileMenu.classList.remove("active");
            }

            if (servicesBtn) {
                servicesBtn.classList.remove("active");
            }

            if (servicesMenu) {
                servicesMenu.classList.remove("active");
            }

        }

    });

});




document.addEventListener("DOMContentLoaded", function () {

    const serviceCards =
        document.querySelectorAll(".zcs-welcome-service-card");


    serviceCards.forEach(function (card, index) {

        card.style.opacity = "0";
        card.style.transform = "translateY(12px)";

        setTimeout(function () {

            card.style.transition =
                "opacity .4s ease, transform .4s ease, " +
                "box-shadow .28s ease, border-color .28s ease";

            card.style.opacity = "1";
            card.style.transform = "translateY(0)";

        }, 70 * index);

    });

});




document.addEventListener("DOMContentLoaded", function () {

    const counters = document.querySelectorAll(".zcs-home-counter");

    if (!counters.length) return;


    const startCounter = (counter) => {

        const target = parseFloat(counter.dataset.target);

        const decimal =
            parseInt(counter.dataset.decimal || "0");

        const suffix =
            counter.dataset.suffix || "";

        const duration = 1300;

        const startTime = performance.now();


        function updateCounter(currentTime) {

            const progress =
                Math.min((currentTime - startTime) / duration, 1);

            const ease =
                1 - Math.pow(1 - progress, 3);

            const value =
                target * ease;


            counter.textContent =
                value.toFixed(decimal) + suffix;


            if (progress < 1) {

                requestAnimationFrame(updateCounter);

            } else {

                counter.textContent =
                    target.toFixed(decimal) + suffix;

            }

        }


        requestAnimationFrame(updateCounter);

    };


    const observer =
        new IntersectionObserver(function (entries, observer) {

            entries.forEach(function (entry) {

                if (entry.isIntersecting) {

                    startCounter(entry.target);

                    observer.unobserve(entry.target);

                }

            });

        }, {
            threshold: 0.4
        });


    counters.forEach(function (counter) {

        observer.observe(counter);

    });

});




document.addEventListener("DOMContentLoaded", function () {

    const items = document.querySelectorAll(
        ".zcs-extra-package-card, .zcs-extra-area-list a"
    );

    if (!items.length) return;


    const observer = new IntersectionObserver(function (entries, observer) {

        entries.forEach(function (entry) {

            if (entry.isIntersecting) {

                entry.target.classList.add("zcs-extra-show");

                observer.unobserve(entry.target);
            }

        });

    }, {
        threshold: 0.15
    });


    items.forEach(function (item) {

        item.classList.add("zcs-extra-animate");

        observer.observe(item);

    });

});






document.addEventListener("DOMContentLoaded", function () {

    const CART_KEY = "zenCareCart";

    // Cart quantity limit per service (book_appointment.php caps at 20 too)
    const MAX_QUANTITY = 20;

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }


    /* =====================================================
       GET CART
    ===================================================== */

    function getCart() {

        try {

            const savedCart =
                JSON.parse(localStorage.getItem(CART_KEY));

            return Array.isArray(savedCart)
                ? savedCart
                : [];

        } catch (error) {

            return [];

        }

    }


    /* =====================================================
       SAVE CART
    ===================================================== */

    function saveCart(cart) {

        localStorage.setItem(
            CART_KEY,
            JSON.stringify(cart)
        );


        /* zen-api.js saves it to the account */

        document.dispatchEvent(
            new CustomEvent("zen:cart-changed", { detail: cart })
        );

    }


    /* =====================================================
       UPDATE CART COUNT
    ===================================================== */

    function updateCartCount() {

        const cart = getCart();

        const count = cart.length;


        const counters =
            document.querySelectorAll(
                ".zcs-cart-count, .zcs-mobile-cart-count"
            );


        counters.forEach(function (counter) {

            counter.textContent = count;

        });


        const miniCount =
            document.querySelector(
                ".zcs-mini-cart-total-count"
            );


        if (miniCount) {

            miniCount.textContent =
                count +
                (count === 1 ? " Item" : " Items");

        }

    }


    /* =====================================================
       UPDATE ADD TO CART BUTTONS
    ===================================================== */

    function updateCartButtons() {

        const cart = getCart();


        const buttons =
            document.querySelectorAll(
                ".zcs-add-cart-btn"
            );


        buttons.forEach(function (button) {

            const serviceId =
                button.dataset.id;


            const exists =
                cart.some(function (item) {

                    return item.id === serviceId;

                });


            if (exists) {

                button.classList.add(
                    "zcs-cart-added"
                );

                button.disabled = true;

                button.innerHTML = `
                    <i class="fa-solid fa-check"></i>
                    <span>Added to Cart</span>
                `;

            } else {

                button.classList.remove(
                    "zcs-cart-added"
                );

                button.disabled = false;

                button.innerHTML = `
                    <i class="fa-solid fa-cart-plus"></i>
                    <span>Add to Cart</span>
                `;

            }

        });

    }


    /* =====================================================
       RENDER MINI CART
    ===================================================== */

    function renderMiniCart() {

        const cart = getCart();


        const itemsBox =
            document.querySelector(
                ".zcs-mini-cart-items"
            );


        const emptyBox =
            document.querySelector(
                ".zcs-mini-cart-empty"
            );


        const footer =
            document.querySelector(
                ".zcs-mini-cart-footer"
            );


        const subtotalPrice =
            document.querySelector(
                ".zcs-mini-cart-subtotal-price"
            );


        if (!itemsBox) {

            console.warn(
                "Mini cart items container not found."
            );

            return;

        }


        itemsBox.innerHTML = "";


        /* EMPTY CART */

        if (cart.length === 0) {

            if (emptyBox) {

                emptyBox.style.display = "flex";

            }


            if (footer) {

                footer.style.display = "none";

            }


            if (subtotalPrice) {

                subtotalPrice.textContent = "₹0";

            }


            updateCartCount();

            return;

        }


        /* CART HAS ITEMS */

        if (emptyBox) {

            emptyBox.style.display = "none";

        }


        if (footer) {

            footer.style.display = "block";

        }


        let subtotal = 0;


        cart.forEach(function (item) {

            const quantity =
                Number(item.quantity) || 1;

            const price =
                Number(item.price || 0) * quantity;


            subtotal += price;


            const row =
                document.createElement("div");


            row.className =
                "zcs-mini-cart-item";


            const safeImage =
                escapeHtml(item.image || ZCS_FALLBACK_IMAGE);

            const safeName =
                escapeHtml(item.name);


            row.innerHTML = `

                <div class="zcs-mini-cart-item-image">

                    <img
                        src="${safeImage}"
                        onerror="this.onerror=null;this.src='${escapeHtml(ZCS_FALLBACK_IMAGE)}'"
                        alt="${safeName}"
                    >

                </div>


                <div class="zcs-mini-cart-item-content">

                    <strong>
                        ${safeName}
                    </strong>

                    <span>
                        ₹${price.toLocaleString("en-IN")}${quantity > 1 ? " (" + quantity + " × ₹" + Number(item.price || 0).toLocaleString("en-IN") + ")" : ""}
                    </span>

                </div>


                <button
                    type="button"
                    class="zcs-mini-cart-remove"
                    data-remove-id="${escapeHtml(item.id)}"
                    aria-label="Remove ${safeName}"
                >

                    <i class="fa-solid fa-xmark"></i>

                </button>

            `;


            itemsBox.appendChild(row);

        });


        if (subtotalPrice) {

            subtotalPrice.textContent =
                "₹" +
                subtotal.toLocaleString("en-IN");

        }


        updateCartCount();

    }


    /* =====================================================
       SHOW TOAST
    ===================================================== */

    function showCartToast(message, type) {

        const oldToast =
            document.querySelector(
                ".zcs-cart-toast"
            );


        if (oldToast) {

            oldToast.remove();

        }


        const toast =
            document.createElement("div");


        toast.className =
            "zcs-cart-toast";


        if (type === "warning") {

            toast.classList.add(
                "zcs-cart-toast-warning"
            );

        }


        const icon =
            type === "warning"
                ? "fa-circle-exclamation"
                : "fa-circle-check";


        toast.innerHTML = `

            <span class="zcs-cart-toast-icon">

                <i class="fa-solid ${icon}"></i>

            </span>

            <span>
                ${message}
            </span>

        `;


        document.body.appendChild(toast);


        setTimeout(function () {

            toast.classList.add(
                "zcs-cart-toast-show"
            );

        }, 20);


        setTimeout(function () {

            toast.classList.remove(
                "zcs-cart-toast-show"
            );


            setTimeout(function () {

                toast.remove();

            }, 300);

        }, 2400);

    }


    /* =====================================================
       ADD ITEM TO CART
    ===================================================== */

    function addToCart(button) {

        const id =
            button.dataset.id;


        const name =
            button.dataset.name;


        const price =
            Number(
                button.dataset.price || 0
            );


        const image =
            button.dataset.image || "";


        const url =
            button.dataset.url || "";


        // saverpacks id: the server prices the booking from it
        const packId =
            parseInt(button.dataset.packId, 10) || null;


        if (!id || !name) {

            console.error(
                "Missing data-id or data-name on Add to Cart button."
            );

            return;

        }


        let cart = getCart();


        const alreadyExists =
            cart.some(function (item) {

                return item.id === id;

            });


        if (alreadyExists) {

            showCartToast(
                "This service is already in your cart.",
                "warning"
            );

            return;

        }


        cart.push({

            id: id,

            pack_id: packId,

            name: name,

            price: price,

            image: image,

            url: url,

            category: button.dataset.category || (window.ZenAPI
                ? window.ZenAPI.categoryOf({ url: url })
                : ""),

            quantity: 1

        });


        saveCart(cart);


        updateCartCount();

        updateCartButtons();

        renderMiniCart();


        showCartToast(
            name + " added to cart.",
            "success"
        );

    }


    /* =====================================================
       REMOVE ITEM FROM CART
    ===================================================== */

    function removeFromCart(id) {

        let cart =
            getCart();


        cart =
            cart.filter(function (item) {

                return item.id !== id;

            });


        saveCart(cart);


        updateCartCount();

        updateCartButtons();

        renderMiniCart();


        showCartToast(
            "Service removed from cart.",
            "success"
        );

    }


    /* =====================================================
       CHANGE QUANTITY (cart page)
    ===================================================== */

    function setQuantity(id, quantity) {

        const cart = getCart();

        const item = cart.find(function (entry) {
            return entry.id === id;
        });

        if (!item) return;

        item.quantity = Math.max(1, Math.min(MAX_QUANTITY, parseInt(quantity, 10) || 1));

        saveCart(cart);

        updateCartCount();

        renderMiniCart();

    }


    /* =====================================================
       GLOBAL CLICK EVENT
    ===================================================== */

    document.addEventListener(
        "click",
        function (event) {


            /* -----------------------------
               ADD TO CART
            ----------------------------- */

            const addButton =
                event.target.closest(
                    ".zcs-add-cart-btn"
                );


            if (addButton) {

                event.preventDefault();

                addToCart(addButton);

                return;

            }


            /* -----------------------------
               SERVICE CARD "MORE DETAILS"
            ----------------------------- */

            const detailsLink =
                event.target.closest("[data-zcs-details]");

            if (detailsLink) {

                event.preventDefault();

                const panel = document.getElementById(
                    detailsLink.getAttribute("aria-controls")
                );

                if (panel) {

                    const open = panel.hidden;

                    panel.hidden = !open;

                    detailsLink.setAttribute("aria-expanded", open ? "true" : "false");

                    const label = detailsLink.querySelector("span");

                    if (label) label.textContent = open ? "Less Details" : "More Details";

                }

                return;

            }


            /* -----------------------------
               REMOVE FROM MINI CART
            ----------------------------- */

            const removeButton =
                event.target.closest(
                    ".zcs-mini-cart-remove"
                );


            if (removeButton) {

                event.preventDefault();


                const removeId =
                    removeButton.dataset.removeId;


                removeFromCart(
                    removeId
                );

                return;

            }

        }
    );


    /* =====================================================
       SYNC IF CART CHANGES IN ANOTHER TAB
    ===================================================== */

    window.addEventListener(
        "storage",
        function (event) {

            if (event.key === CART_KEY) {

                updateCartCount();

                updateCartButtons();

                renderMiniCart();

            }

        }
    );


    /* =====================================================
       REFRESH AFTER ACCOUNT CART SYNC (zen-api.js)
    ===================================================== */

    document.addEventListener(
        "zen:cart-refresh",
        function () {

            updateCartCount();

            updateCartButtons();

            renderMiniCart();

        }
    );


    /* =====================================================
       SHARED WITH CART / CHECKOUT PAGES
    ===================================================== */

    window.ZenCart = {

        getCart: getCart,

        removeFromCart: removeFromCart,

        setQuantity: setQuantity,

        MAX_QUANTITY: MAX_QUANTITY

    };


    /* =====================================================
       INITIAL LOAD
    ===================================================== */

    updateCartCount();

    updateCartButtons();

    renderMiniCart();

});


/* =========================================================
   BANNER SLIDER  ([data-zcs-slider], homepage offers)
   Auto-advances every 5s, pauses on hover/focus and when
   the tab is hidden; supports arrows, dots and swipe.
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll("[data-zcs-slider]").forEach(function (slider) {

        const slides = Array.prototype.slice.call(slider.querySelectorAll(".zcs-banner-slide"));
        const dots = Array.prototype.slice.call(slider.querySelectorAll(".zcs-banner-dots button"));

        if (slides.length < 2) return;

        let current = 0;
        let timer = null;
        let paused = false;
        let touchX = null;

        function show(index) {
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                slide.classList.toggle("is-active", i === current);
                slide.setAttribute("aria-hidden", i === current ? "false" : "true");
                slide.tabIndex = i === current ? 0 : -1;
            });
            dots.forEach(function (dot, i) {
                dot.classList.toggle("is-active", i === current);
                if (i === current) {
                    dot.setAttribute("aria-current", "true");
                } else {
                    dot.removeAttribute("aria-current");
                }
            });
        }

        function stop() {
            if (timer) clearInterval(timer);
            timer = null;
        }

        function start() {
            stop();
            if (paused || document.hidden) return;
            if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
            timer = setInterval(function () { show(current + 1); }, 5000);
        }

        const prev = slider.querySelector(".zcs-banner-prev");
        const next = slider.querySelector(".zcs-banner-next");

        if (prev) prev.addEventListener("click", function () { show(current - 1); start(); });
        if (next) next.addEventListener("click", function () { show(current + 1); start(); });

        dots.forEach(function (dot, i) {
            dot.addEventListener("click", function () { show(i); start(); });
        });

        slider.addEventListener("mouseenter", function () { paused = true; stop(); });
        slider.addEventListener("mouseleave", function () { paused = false; start(); });
        slider.addEventListener("focusin", function () { paused = true; stop(); });
        slider.addEventListener("focusout", function () { paused = false; start(); });

        slider.addEventListener("touchstart", function (event) {
            touchX = event.touches[0].clientX;
        }, { passive: true });

        slider.addEventListener("touchend", function (event) {
            if (touchX === null) return;
            const dx = event.changedTouches[0].clientX - touchX;
            touchX = null;
            if (Math.abs(dx) > 40) {
                show(current + (dx < 0 ? 1 : -1));
                start();
            }
        });

        document.addEventListener("visibilitychange", start);

        show(0);
        start();
    });

});







