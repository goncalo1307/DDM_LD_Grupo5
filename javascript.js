    const productCards = document.querySelectorAll(".product-card");
    const searchInputs = document.querySelectorAll(".search-input");
    const emptyMessage = document.getElementById("emptyMessage");
    const menuButton = document.getElementById("menuButton");
    const mobileNav = document.getElementById("mobileNav");
    const initialQuery = new URLSearchParams(window.location.search).get("q") || "";

    searchInputs.forEach(input => { input.value = initialQuery; });

    function filterProducts() {
      const query = (searchInputs[0].value || searchInputs[1].value || "").trim().toLocaleLowerCase("pt");
      let visibleCount = 0;
      productCards.forEach(card => {
        const matchesSearch = card.dataset.name.toLocaleLowerCase("pt").includes(query);
        card.style.display = matchesSearch ? "" : "none";
        if (matchesSearch) visibleCount++;
      });
      emptyMessage.style.display = productCards.length > 0 && visibleCount === 0 ? "block" : "none";
    }

    searchInputs.forEach(input => {
      input.addEventListener("input", () => {
        searchInputs.forEach(other => { if (other !== input) other.value = input.value; });
        filterProducts();
      });
    });

    filterProducts();

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
