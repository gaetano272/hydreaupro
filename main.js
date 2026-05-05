document.addEventListener("DOMContentLoaded", function () {
  function setupMenu(toggleSelector, menuSelector) {
    const toggle = document.querySelector(toggleSelector);
    const menu = document.querySelector(menuSelector);

    if (!toggle || !menu) return;

    toggle.addEventListener("click", function () {
      const isOpen = menu.classList.toggle("open");
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    menu.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        menu.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  setupMenu(".nav-toggle", ".nav-menu");
  setupMenu(".service-nav__toggle", ".service-nav__list");
});
