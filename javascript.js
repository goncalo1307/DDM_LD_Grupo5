    const categoryButtons = document.querySelectorAll(".category");
    const productCards = document.querySelectorAll(".product-card");
    const searchInputs = document.querySelectorAll(".search-input");
    const emptyMessage = document.getElementById("emptyMessage");
    const cartCount = document.getElementById("cartCount");
    const cartButton = document.querySelector(".cart-button");
    const menuButton = document.getElementById("menuButton");
    const mobileNav = document.getElementById("mobileNav");

    let selectedCategory = "Todos";
    let cartItems = 2;

    function filterProducts() {
      const query = (searchInputs[0].value || searchInputs[1].value || "").trim().toLocaleLowerCase("pt");
      let visibleCount = 0;
      productCards.forEach(card => {
        const matchesCategory = selectedCategory === "Todos" || card.dataset.category === selectedCategory;
        const matchesSearch = card.dataset.name.toLocaleLowerCase("pt").includes(query);
        const visible = matchesCategory && matchesSearch;
        card.style.display = visible ? "" : "none";
        if (visible) visibleCount++;
      });
      emptyMessage.style.display = visibleCount === 0 ? "block" : "none";
    }

    categoryButtons.forEach(button => {
      button.addEventListener("click", () => {
        selectedCategory = button.dataset.category;
        categoryButtons.forEach(item => item.classList.toggle("active", item === button));
        filterProducts();
      });
    });

    searchInputs.forEach(input => {
      input.addEventListener("input", () => {
        searchInputs.forEach(other => { if (other !== input) other.value = input.value; });
        filterProducts();
      });
    });

    document.querySelectorAll(".add-button").forEach(button => {
      button.addEventListener("click", () => {
        cartItems++;
        cartCount.textContent = cartItems;
        cartButton.setAttribute("aria-label", `Carrinho com ${cartItems} artigos`);
      });
    });

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
