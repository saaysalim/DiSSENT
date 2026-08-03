document.addEventListener("DOMContentLoaded", function(){

    var toggle = document.querySelector(".nav-toggle");
    var header = document.querySelector("header");

    if(toggle && header){

        toggle.addEventListener("click", function(){

            var isOpen = header.classList.toggle("nav-open");
            toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");

        });

        var navLinks = header.querySelectorAll("nav a");

        navLinks.forEach(function(link){

            link.addEventListener("click", function(){
                header.classList.remove("nav-open");
                toggle.setAttribute("aria-expanded", "false");
            });

        });

    }

});
