(function () {
    "use strict";

    function initialiseNavigation() {
        const header = document.getElementById("siteHeader");

        if (!header || header.dataset.navigationReady === "true") {
            return;
        }

        const toggle = header.querySelector(".nav-toggle");
        const navigation = document.getElementById("primaryNavigation");
        const tools = document.getElementById("headerTools");

        if (!toggle || !navigation || !tools) {
            return;
        }

        // Prevent duplicate handlers if this file is accidentally loaded twice.
        header.dataset.navigationReady = "true";

        const mobile = window.matchMedia("(max-width: 1400px)");

        function focusIsInsideMenu() {
            return (
                navigation.contains(document.activeElement) ||
                tools.contains(document.activeElement)
            );
        }

        function setMenu(open, restoreFocus = false) {
            header.classList.toggle("nav-open", open);

            toggle.setAttribute("aria-expanded", String(open));
            if (restoreFocus) {
                toggle.focus();
            }
        }

        toggle.addEventListener("click", function () {
            if (!mobile.matches) {
                return;
            }

            setMenu(!header.classList.contains("nav-open"));
        });

        header.addEventListener("keydown", function (event) {
            if (
                event.key === "Escape" &&
                mobile.matches &&
                header.classList.contains("nav-open")
            ) {
                event.preventDefault();
                setMenu(false, true);
            }
        });

        document.addEventListener("click", function (event) {
            if (
                mobile.matches &&
                header.classList.contains("nav-open") &&
                !header.contains(event.target)
            ) {
                setMenu(false, focusIsInsideMenu());
            }
        });

        function handleScreenChange() {
            const toggleHadFocus = document.activeElement === toggle;

            setMenu(false, mobile.matches && focusIsInsideMenu());

            if (!mobile.matches && toggleHadFocus) {
                const homeLink = header.querySelector(".logo a");

                if (homeLink) {
                    homeLink.focus();
                }
            }
        }

        if (typeof mobile.addEventListener === "function") {
            mobile.addEventListener("change", handleScreenChange);
        } else {
            mobile.addListener(handleScreenChange);
        }

        // Hide the mobile menu only after its controls are ready.
        const preserveFocus = mobile.matches && focusIsInsideMenu();

        setMenu(false);
        header.classList.add("menu-ready");

        if (preserveFocus) {
            toggle.focus();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener(
            "DOMContentLoaded",
            initialiseNavigation,
            { once: true }
        );
    } else {
        initialiseNavigation();
    }
})();