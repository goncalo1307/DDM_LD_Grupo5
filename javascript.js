const searchForms = document.querySelectorAll(".search-to-page");
let searchTransitionStarted = false;

function openSearchPage(form) {
  if (searchTransitionStarted) return;
  searchTransitionStarted = true;

  const destination = new URL(form.action, window.location.href);
  document.body.classList.add("search-transition");
  window.setTimeout(() => {
    const input = form.querySelector('input[name="q"]');
    if (input && input.value.trim()) {
      destination.searchParams.set("q", input.value.trim());
    }
    window.location.href = destination.toString();
  }, 360);
}

searchForms.forEach(form => {
  form.addEventListener("click", () => openSearchPage(form));
  form.addEventListener("submit", event => {
    event.preventDefault();
    openSearchPage(form);
  });
});

if (document.body.classList.contains("search-page-body")) {
  const searchSelector = window.matchMedia("(max-width: 700px)").matches
    ? ".mobile-search input[name='q']"
    : ".desktop-search input[name='q']";
  const activeSearchInput = document.querySelector(searchSelector);
  if (activeSearchInput) activeSearchInput.focus({ preventScroll: true });
}

const menuButton = document.getElementById("menuButton");
const mobileNav = document.getElementById("mobileNav");

if (menuButton && mobileNav) {
  menuButton.addEventListener("click", () => {
    const isOpen = mobileNav.classList.toggle("open");
    menuButton.setAttribute("aria-expanded", String(isOpen));
    menuButton.setAttribute("aria-label", isOpen ? "Fechar menu" : "Abrir menu");
  });

  mobileNav.querySelectorAll("a").forEach(link => {
    link.addEventListener("click", () => {
      mobileNav.classList.remove("open");
      menuButton.setAttribute("aria-expanded", "false");
    });
  });
}
