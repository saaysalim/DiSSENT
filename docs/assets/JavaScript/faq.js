/*
 * DiSSENT FAQ: filters the questions as you type.
 * Without JavaScript the search box stays hidden and every question is
 * still available (the <details> accordion works on its own).
 */
(function () {
    "use strict";

    function initialise() {
        var input = document.getElementById("faqSearch");
        var status = document.getElementById("faqSearchStatus");
        var empty = document.getElementById("faqNoResults");
        var topics = document.querySelector(".faq-topics");
        var sections = Array.prototype.slice.call(document.querySelectorAll(".faq-section"));

        if (!input || !status || sections.length === 0) {
            return;
        }

        input.hidden = false;

        var items = [];

        sections.forEach(function (section) {
            section.querySelectorAll(".faq-item").forEach(function (item) {
                items.push({
                    element: item,
                    section: section,
                    text: item.textContent.toLowerCase().replace(/\s+/g, " ")
                });
            });
        });

        var timer = null;

        function filter() {
            var words = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
            var shown = 0;

            items.forEach(function (item) {
                var match = words.every(function (word) {
                    return item.text.indexOf(word) !== -1;
                });

                item.element.hidden = !match;

                if (match) {
                    shown += 1;
                }
            });

            sections.forEach(function (section) {
                section.hidden = section.querySelector(".faq-item:not([hidden])") === null;
            });

            if (topics) {
                topics.hidden = words.length > 0;
            }

            if (empty) {
                empty.hidden = shown !== 0;
            }

            if (words.length === 0) {
                status.textContent = "";
            } else if (shown === 0) {
                status.textContent = "No questions match.";
            } else if (shown === 1) {
                status.textContent = "1 question matches.";
            } else {
                status.textContent = shown + " questions match.";
            }
        }

        input.addEventListener("input", function () {
            // Small delay so screen readers are not flooded while typing.
            window.clearTimeout(timer);
            timer = window.setTimeout(filter, 150);
        });

        input.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && input.value !== "") {
                event.preventDefault();
                event.stopPropagation();
                input.value = "";
                filter();
            }
        });

        // Apply any text the browser restored into the box (back/forward).
        filter();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialise, { once: true });
    } else {
        initialise();
    }
})();
