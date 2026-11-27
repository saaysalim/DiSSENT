/*
 * DiSSENT shared site script (loaded on every page by includes/footer.php).
 *
 * Header navigation: at MOBILE_QUERY widths (phones and iPads) the menu links,
 * search, language and account links live in a sidebar (#siteMenu) that slides
 * in from the left. Keep MOBILE_QUERY identical to the matching media query in
 * assets/css/base.css ("HEADER — PHONES AND TABLETS").
 */
(function () {
    "use strict";

    var MOBILE_QUERY = "(max-width: 1024px)";

    function initialiseNavigation() {
        var header = document.getElementById("siteHeader");

        if (!header || header.dataset.navigationReady === "true") {
            return;
        }

        var toggle = header.querySelector(".nav-toggle");
        var menu = document.getElementById("siteMenu");
        var closeButton = menu ? menu.querySelector(".nav-close") : null;
        var backdrop = document.querySelector(".site-menu-backdrop");
        var languages = header.querySelector(".header-language-menu");
        var languageToggle = languages ? languages.querySelector("summary") : null;

        if (!toggle || !menu || !closeButton) {
            return;
        }

        // Prevent duplicate handlers if this file is loaded twice.
        header.dataset.navigationReady = "true";

        var mobile = window.matchMedia(MOBILE_QUERY);
        var isOpen = false;

        function closeLanguages(restoreFocus) {
            if (!languages || !languages.open) {
                return;
            }

            languages.open = false;

            if (restoreFocus && languageToggle) {
                languageToggle.focus();
            }
        }

        function focusableItems() {
            return Array.prototype.filter.call(
                menu.querySelectorAll(
                    "a[href], button:not([disabled]), input:not([disabled]), summary"
                ),
                function (item) {
                    return item.offsetParent !== null;
                }
            );
        }

        // Dialog semantics only apply while the sidebar is open on small screens.
        function setDialogSemantics(on) {
            if (on) {
                menu.setAttribute("role", "dialog");
                menu.setAttribute("aria-modal", "true");
                menu.setAttribute("aria-label", "Site menu");
            } else {
                menu.removeAttribute("role");
                menu.removeAttribute("aria-modal");
                menu.removeAttribute("aria-label");
            }
        }

        function openMenu() {
            if (!mobile.matches || isOpen) {
                return;
            }

            isOpen = true;
            header.classList.add("nav-open");
            document.documentElement.classList.add("site-menu-open");
            toggle.setAttribute("aria-expanded", "true");
            setDialogSemantics(true);

            if (backdrop) {
                backdrop.hidden = false;
            }

            closeButton.focus();
        }

        function closeMenu(restoreFocus) {
            if (!isOpen) {
                return;
            }

            isOpen = false;
            header.classList.remove("nav-open");
            document.documentElement.classList.remove("site-menu-open");
            toggle.setAttribute("aria-expanded", "false");
            setDialogSemantics(false);
            closeLanguages(false);

            if (backdrop) {
                backdrop.hidden = true;
            }

            if (restoreFocus) {
                toggle.focus();
            }
        }

        toggle.addEventListener("click", function () {
            if (isOpen) {
                closeMenu(true);
            } else {
                openMenu();
            }
        });

        closeButton.addEventListener("click", function () {
            closeMenu(true);
        });

        if (backdrop) {
            backdrop.addEventListener("click", function () {
                closeMenu(true);
            });
        }

        // Close when a menu link is chosen (the page then navigates).
        menu.addEventListener("click", function (event) {
            var link = event.target.closest ? event.target.closest("a[href]") : null;

            if (link && isOpen) {
                closeMenu(false);
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" || event.key === "Esc") {
                if (languages && languages.open) {
                    event.preventDefault();
                    closeLanguages(true);
                    return;
                }

                if (isOpen) {
                    event.preventDefault();
                    closeMenu(true);
                }

                return;
            }

            // Keep keyboard focus inside the open sidebar.
            if (event.key === "Tab" && isOpen) {
                var items = focusableItems();

                if (items.length === 0) {
                    return;
                }

                var first = items[0];
                var last = items[items.length - 1];

                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                } else if (!menu.contains(document.activeElement)) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        // Close the language list when tapping or clicking anywhere outside it.
        // "pointerdown" is used as well as "click": iPhone Safari does not send a
        // click for taps on empty areas, so the list stayed open there.
        function closeIfOutside(event) {
            if (languages && languages.open && !languages.contains(event.target)) {
                closeLanguages(languages.contains(document.activeElement));
            }
        }

        document.addEventListener("pointerdown", closeIfOutside);
        document.addEventListener("click", closeIfOutside);

        function handleScreenChange() {
            if (!mobile.matches && isOpen) {
                closeMenu(false);
            }
        }

        if (typeof mobile.addEventListener === "function") {
            mobile.addEventListener("change", handleScreenChange);
        } else if (typeof mobile.addListener === "function") {
            mobile.addListener(handleScreenChange);
        }

        // Restore a clean state when the page comes back from the back/forward cache.
        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                closeMenu(false);
            }
        });

        header.classList.add("menu-ready");
    }

    function initialiseHeaderSearch() {
        var form = document.querySelector("#siteHeader .header-search");
        var input = form ? form.querySelector('input[name="q"]') : null;

        if (!form || !input || form.dataset.searchReady === "true") {
            return;
        }

        form.dataset.searchReady = "true";

        input.addEventListener("input", function () {
            input.setCustomValidity("");
        });

        form.addEventListener("submit", function (event) {
            input.value = input.value.trim();

            if (input.value === "") {
                event.preventDefault();
                input.setCustomValidity("Enter something to search for.");
                input.reportValidity();
            }
        });
    }

    // Category lists with "Other" (Register, Profile, Reports): the text box is
    // shown only while "Other" is chosen. Without JavaScript it is always shown.
    function initialiseOtherFields() {
        Array.prototype.forEach.call(
            document.querySelectorAll("select[data-other-select]"),
            function (select) {
                var field = document.getElementById(select.getAttribute("data-other-select"));

                if (!field) {
                    return;
                }

                function update() {
                    var isOther = select.value === "Other";
                    field.classList.toggle("is-hidden", !isOther);
                }

                select.addEventListener("change", function () {
                    update();

                    if (select.value === "Other") {
                        var input = field.querySelector("input");
                        if (input) {
                            input.focus();
                        }
                    }
                });
                update();
            }
        );
    }

    // "Back to top" (footer.php, long pages only): appears after about one and a
    // half screens of scrolling, scrolls to the top and moves the keyboard focus to
    // the page heading so keyboard and screen-reader users start again from the top.
    function initialiseBackToTop() {
        var button = document.getElementById("backToTop");

        if (!button) {
            return;
        }

        var target = document.querySelector("main h1") || document.getElementById("main-content");
        var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)");

        var footer = document.querySelector("footer");

        button.classList.add("is-floating");

        function update() {
            button.classList.toggle("is-visible", window.scrollY > window.innerHeight * 1.5);

            // Over the dark footer: switch to white with navy text so it stays visible.
            if (footer) {
                var box = button.getBoundingClientRect();
                var middle = box.top + box.height / 2;
                button.classList.toggle("is-over-dark", footer.getBoundingClientRect().top < middle);
            }
        }

        window.addEventListener("scroll", update, { passive: true });
        window.addEventListener("resize", update, { passive: true });
        update();

        button.addEventListener("click", function (event) {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: reduce && reduce.matches ? "auto" : "smooth" });

            if (target) {
                if (!target.hasAttribute("tabindex")) {
                    target.setAttribute("tabindex", "-1");
                }
                target.focus({ preventScroll: true });
            }
        });
    }

    function initialise() {
        initialiseNavigation();
        initialiseHeaderSearch();
        initialiseOtherFields();
        initialiseBackToTop();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialise, { once: true });
    } else {
        initialise();
    }
})();
