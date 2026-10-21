<?php
require __DIR__ . '/conta/ligacao.php';

$logado         = !empty($_SESSION['utilizador_id']);
$nomeUtilizador = $_SESSION['utilizador_nome'] ?? '';
$primeiroNome   = $nomeUtilizador !== '' ? explode(' ', trim($nomeUtilizador))[0] : '';
$inicial        = $primeiroNome !== '' ? mb_strtoupper(mb_substr($primeiroNome, 0, 1)) : '';
$tipoUtilizador = $_SESSION['utilizador_tipo'] ?? 'cliente';

// Nº de artigos no carrinho do utilizador
$totalCarrinho = 0;
if ($logado) {
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(ic.Quantidade), 0)
         FROM ItemCarrinho ic
         JOIN Carrinho c ON c.IDCarrinho = ic.IDCarrinho
         WHERE c.IDUtilizador = ?'
    );
    $stmt->execute([(int) $_SESSION['utilizador_id']]);
    $totalCarrinho = (int) $stmt->fetchColumn();
}

$stmt = $pdo->query(
    'SELECT IDProduto, Nome, Marca, Preco, PrecoPromocional,
            Unidade, Imagem, Destaque
     FROM Produto
     WHERE Ativo = 1
     ORDER BY Destaque DESC, Nome ASC'
);
$produtos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cantinho Mercado</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
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
        <a href="#ofertas">Ofertas</a>
        <a href="#categorias">Categorias</a>
        <a href="#sobre">Quem somos</a>
      </nav>
      <label class="search desktop-search">
        <svg class="svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input class="search-input" type="search" placeholder="O que procura?" aria-label="Pesquisar produtos">
      </label>

      <?php if ($logado): ?>
        <details class="user-menu account-button" id="userMenu">
          <summary class="icon-button" aria-label="Menu da conta de <?= e($nomeUtilizador) ?>">
            <span class="avatar"><?= e($inicial) ?></span>
          </summary>
          <div class="user-menu-panel">
            <div class="user-menu-head">
              Olá, <strong><?= e($primeiroNome) ?></strong>
            </div>
            <a href="conta/perfil.php">O meu perfil</a>
            <a href="conta/perfil.php#moradas">As minhas moradas</a>
            <?php if ($tipoUtilizador === 'admin'): ?>
              <a href="admin/index.php">Administração</a>
            <?php endif; ?>
            <a href="conta/logout.php" class="logout-link">Terminar sessão</a>
          </div>
        </details>
      <?php else: ?>
        <a class="icon-button account-button" href="conta/conta.php" aria-label="Iniciar sessão">
          <svg class="svg" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        </a>
      <?php endif; ?>

      <button class="icon-button cart-button" aria-label="Carrinho com <?= $totalCarrinho ?> artigos">
        <svg class="svg" viewBox="0 0 24 24"><path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        <span class="cart-count" id="cartCount"><?= $totalCarrinho ?></span>
      </button>
    </div>
    <nav class="mobile-nav" id="mobileNav">
      <a href="#ofertas">Ofertas</a>
      <a href="#categorias">Categorias</a>
      <a href="#sobre">Quem somos</a>
      <?php if ($logado): ?>
        <a href="conta/perfil.php">O meu perfil (<?= e($primeiroNome) ?>)</a>
        <a href="conta/logout.php">Terminar sessão</a>
      <?php else: ?>
        <a href="conta/conta.php">Iniciar sessão</a>
        <a href="conta/criar-conta.php">Criar conta</a>
      <?php endif; ?>
    </nav>
    <label class="search mobile-search">
      <svg class="svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input class="search-input" type="search" placeholder="Pesquisar produtos" aria-label="Pesquisar produtos">
    </label>
  </header>

  <main>
    <section class="hero">
      <div class="hero-copy">
        <span class="eyebrow">Do produtor à sua mesa</span>
        <h1><?= $logado ? 'Olá, ' . e($primeiroNome) . '. ' : '' ?>Frescura que cabe no seu dia a dia.</h1>
        <p>Produtos escolhidos diariamente, preços justos e entregas à hora que mais lhe convém.</p>
        <a href="#categorias" class="primary-link">Encomendar agora
          <svg class="svg" viewBox="0 0 24 24"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
        </a>
      </div>
      <div class="hero-image">
        <img src="https://images.unsplash.com/photo-1714386893630-b7a148f77efd?auto=format&fit=crop&w=1200&q=85" alt="Cabaz com frutas e legumes frescos">
        <div class="fresh-label"><small>Frescos do dia</small><strong>100% fresco</strong></div>
      </div>
    </section>

    <section class="products-section" id="categorias">
      <div class="section-heading">
        <div>
          <p class="section-kicker">Produtos selecionados para si</p>
          <h2>Tudo o que precisa</h2>
        </div>
      </div>
      <div class="product-grid" id="productGrid">
        <?php foreach ($produtos as $produto):
            $preco = (float) $produto['Preco'];
            $precoPromocional = (float) ($produto['PrecoPromocional'] ?? 0);
            $emPromocao = $precoPromocional > 0 && $precoPromocional < $preco;
            $precoAtual = $emPromocao ? $precoPromocional : $preco;
            $nomeProduto = (string) $produto['Nome'];
        ?>
          <article class="product-card" data-name="<?= e($nomeProduto) ?>">
            <a class="product-image product-image-link" href="produto.php?id=<?= (int) $produto['IDProduto'] ?>" aria-label="Ver <?= e($nomeProduto) ?>">
              <?php if (!empty($produto['Imagem'])): ?>
                <img src="<?= e((string) $produto['Imagem']) ?>" alt="<?= e($nomeProduto) ?>">
              <?php else: ?>
                <span class="image-placeholder">Imagem indisponível</span>
              <?php endif; ?>
              <?php if ($emPromocao): ?>
                <span class="tag">Promoção</span>
              <?php elseif (!empty($produto['Destaque'])): ?>
                <span class="tag">Destaque</span>
              <?php endif; ?>
            </a>
            <div class="product-info">
              <?php if (!empty($produto['Unidade'])): ?>
                <p class="unit"><?= e((string) $produto['Unidade']) ?></p>
              <?php elseif (!empty($produto['Marca'])): ?>
                <p class="unit"><?= e((string) $produto['Marca']) ?></p>
              <?php endif; ?>
              <h3><a href="produto.php?id=<?= (int) $produto['IDProduto'] ?>"><?= e($nomeProduto) ?></a></h3>
              <div class="product-bottom">
                <p class="price">
                  <?php if ($emPromocao): ?>
                    <span class="old-price"><?= number_format($preco, 2, ',', '.') ?> €</span>
                  <?php endif; ?>
                  <?= number_format($precoAtual, 2, ',', '.') ?> €
                </p>
                <a class="add-button details-button" href="produto.php?id=<?= (int) $produto['IDProduto'] ?>" aria-label="Ver detalhes de <?= e($nomeProduto) ?>">
                  <svg class="svg" viewBox="0 0 24 24"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="empty" id="emptyMessage">Não foram encontrados produtos. Experimente outra pesquisa.</div>
      <?php if (!$produtos): ?>
        <div class="empty empty-visible">Ainda não existem produtos disponíveis.</div>
      <?php endif; ?>
    </section>
  </main>

  <section class="about" id="sobre">
    <div class="about-inner">
      <div>
        <span class="delivery-pill">
          <svg class="svg" viewBox="0 0 24 24"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
          Entrega no próprio dia
        </span>
        <h2>Um mercado simples, como deve ser.</h2>
      </div>
      <div class="about-copy">
        <p>Escolhemos cada produto com cuidado e trabalhamos com produtores locais para levar mais sabor à sua mesa.</p>
        <a href="#">Saiba mais sobre O Cantinho
          <svg class="svg" viewBox="0 0 24 24"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
        </a>
      </div>
    </div>
  </section>

  <footer>
    <div class="footer-inner">
      <p>© 2026 O Cantinho Mercado. Todos os direitos reservados.</p>
      <p>Feito com cuidado para si e para a sua casa.</p>
    </div>
  </footer>

  <script src="javascript.js"></script>
  <script>
    // Fecha o menu da conta ao clicar fora
    document.addEventListener('click', function (ev) {
      var menu = document.getElementById('userMenu');
      if (menu && menu.open && !menu.contains(ev.target)) menu.open = false;
    });
  </script>
</body>
</html>