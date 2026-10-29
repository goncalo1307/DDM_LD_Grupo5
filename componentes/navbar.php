<?php
$paginaPesquisa = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'pesquisa.php';
$classePesquisa = $paginaPesquisa ? '' : ' search-to-page';
$valorPesquisa = $paginaPesquisa && isset($query) ? (string) $query : '';
?>
  <div class="announcement">Entrega gratuita em compras superiores a 35 €</div>
  <header>
    <div class="header-inner">
      <button class="icon-button mobile-menu" id="menuButton" aria-label="Abrir menu" aria-expanded="false">
        <svg class="svg" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <a href="index.php" class="brand">
        <span class="brand-mark"><svg class="svg" viewBox="0 0 24 24"><path d="M20 4C12 4 5 8 5 15c0 2 1 4 3 5 1-5 4-8 9-11-4 4-6 7-7 11 6 0 10-4 10-16Z"/></svg></span>
        <span>Cantinho</span>
      </a>
      <nav class="desktop">
        <a href="index.php#ofertas">Ofertas</a>
        <a href="index.php#categorias">Produtos</a>
        <a href="index.php#sobre">Quem somos</a>
      </nav>
      <form class="search desktop-search<?= $classePesquisa ?>" action="pesquisa.php" method="get">
        <svg class="svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input name="q" type="search" value="<?= e($valorPesquisa) ?>" placeholder="O que procura?" aria-label="Pesquisar produtos">
      </form>

      <?php if ($logado): ?>
        <details class="user-menu account-button" id="userMenu">
          <summary class="icon-button" aria-label="Menu da conta de <?= e($nomeUtilizador) ?>">
            <span class="avatar"><?= e($inicial) ?></span>
          </summary>
          <div class="user-menu-panel">
            <div class="user-menu-head">Olá, <strong><?= e($primeiroNome) ?></strong></div>
            <a href="conta/perfil.php">O meu perfil</a>
            <a href="conta/perfil.php#moradas">As minhas moradas</a>
            <a href="conta/carrinho.php">O meu carrinho</a>
            <a href="conta/logout.php" class="logout-link">Terminar sessão</a>
          </div>
        </details>
      <?php else: ?>
        <a class="icon-button account-button" href="conta/conta.php" aria-label="Iniciar sessão">
          <svg class="svg" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        </a>
      <?php endif; ?>

      <a class="icon-button cart-button" href="conta/carrinho.php" aria-label="Carrinho com <?= $totalCarrinho ?> artigos">
        <svg class="svg" viewBox="0 0 24 24"><path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        <?php if ($totalCarrinho > 0): ?>
          <span class="cart-count" id="cartCount"><?= $totalCarrinho ?></span>
        <?php endif; ?>
      </a>
      <?php if ($logado && $tipoUtilizador === 'admin'): ?>
        <a class="admin-access-button" href="admin.php">Admin</a>
      <?php endif; ?>
    </div>
    <nav class="mobile-nav" id="mobileNav">
      <a href="index.php#ofertas">Ofertas</a>
      <a href="index.php#categorias">Produtos</a>
      <a href="index.php#sobre">Quem somos</a>
      <?php if ($logado): ?>
        <a href="conta/perfil.php">O meu perfil (<?= e($primeiroNome) ?>)</a>
        <a href="conta/carrinho.php">O meu carrinho (<?= $totalCarrinho ?>)</a>
        <a href="conta/logout.php">Terminar sessão</a>
      <?php else: ?>
        <a href="conta/conta.php">Iniciar sessão</a>
        <a href="conta/criar-conta.php">Criar conta</a>
      <?php endif; ?>
    </nav>
    <form class="search mobile-search<?= $classePesquisa ?>" action="pesquisa.php" method="get">
      <svg class="svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input name="q" type="search" value="<?= e($valorPesquisa) ?>" placeholder="Pesquisar produtos" aria-label="Pesquisar produtos">
    </form>
  </header>
