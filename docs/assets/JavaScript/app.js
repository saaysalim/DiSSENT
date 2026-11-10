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

        // Close the language list when clicking anywhere outside it.
        document.addEventListener("click", function (event) {
            if (languages && languages.open && !languages.contains(event.target)) {
                closeLanguages(languages.contains(document.activeElement));
            }
        });

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

    function initialise() {
        initialiseNavigation();
        initialiseHeaderSearch();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialise, { once: true });
    } else {
        initialise();
    }
})();
