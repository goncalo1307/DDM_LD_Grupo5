<?php
require __DIR__ . '/conta/ligacao.php';

$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$logado = !empty($_SESSION['utilizador_id']);
$nomeUtilizador = $_SESSION['utilizador_nome'] ?? '';
$primeiroNome = $nomeUtilizador !== '' ? explode(' ', trim($nomeUtilizador))[0] : '';
$inicial = $primeiroNome !== '' ? mb_strtoupper(mb_substr($primeiroNome, 0, 1)) : '';
$tipoUtilizador = $_SESSION['utilizador_tipo'] ?? 'cliente';

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

if ($query !== '') {
    $stmt = $pdo->prepare(
        'SELECT IDProduto, Nome, Marca, Preco, PrecoPromocional,
                Unidade, Imagem, Destaque
         FROM Produto
         WHERE Ativo = 1 AND (Nome LIKE ? OR Marca LIKE ?)
         ORDER BY Destaque DESC, Nome ASC'
    );
    $termoPesquisa = '%' . $query . '%';
    $stmt->execute([$termoPesquisa, $termoPesquisa]);
} else {
    $stmt = $pdo->query(
        'SELECT IDProduto, Nome, Marca, Preco, PrecoPromocional,
                Unidade, Imagem, Destaque
         FROM Produto
         WHERE Ativo = 1
         ORDER BY Destaque DESC, Nome ASC'
    );
}
$produtos = $stmt->fetchAll();
$titulo = $query !== '' ? 'Pesquisa: ' . $query . ' | Cantinho Mercado' : 'Pesquisar produtos | Cantinho Mercado';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titulo) ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="search-page-body">
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
      <form class="search desktop-search" action="pesquisa.php" method="get">
        <svg class="svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input name="q" type="search" value="<?= e($query) ?>" placeholder="O que procura?" aria-label="Pesquisar produtos" autofocus>
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
            <?php if ($tipoUtilizador === 'admin'): ?>
              <a href="admin.php">Administração</a>
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
        <span class="cart-count"><?= $totalCarrinho ?></span>
      </button>
    </div>
    <nav class="mobile-nav" id="mobileNav">
      <a href="index.php#ofertas">Ofertas</a>
      <a href="index.php#categorias">Produtos</a>
      <a href="index.php#sobre">Quem somos</a>
      <?php if ($logado): ?>
        <a href="conta/perfil.php">O meu perfil (<?= e($primeiroNome) ?>)</a>
        <a href="conta/logout.php">Terminar sessão</a>
      <?php else: ?>
        <a href="conta/conta.php">Iniciar sessão</a>
        <a href="conta/criar-conta.php">Criar conta</a>
      <?php endif; ?>
    </nav>
    <form class="search mobile-search" action="pesquisa.php" method="get">
      <svg class="svg" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
      <input name="q" type="search" value="<?= e($query) ?>" placeholder="Pesquisar produtos" aria-label="Pesquisar produtos">
    </form>
  </header>

  <main class="search-results-main">
    <section class="search-intro">
      <a href="index.php" class="search-back-link">← Voltar à página inicial</a>
      <p class="section-kicker">Encontre o que precisa</p>
      <h1><?= $query !== '' ? 'Resultados da pesquisa' : 'O que procura hoje?' ?></h1>
      <p class="search-summary">
        <?php if ($query !== ''): ?>
          <?= count($produtos) ?> <?= count($produtos) === 1 ? 'produto encontrado' : 'produtos encontrados' ?> para <strong>“<?= e($query) ?>”</strong>
        <?php else: ?>
          Explore os nossos produtos frescos e selecionados.
        <?php endif; ?>
      </p>
    </section>

    <?php if ($produtos): ?>
      <div class="product-grid">
        <?php foreach ($produtos as $produto):
            $preco = (float) $produto['Preco'];
            $precoPromocional = (float) ($produto['PrecoPromocional'] ?? 0);
            $emPromocao = $precoPromocional > 0 && $precoPromocional < $preco;
            $precoAtual = $emPromocao ? $precoPromocional : $preco;
            $nomeProduto = (string) $produto['Nome'];
        ?>
          <article class="product-card">
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
    <?php else: ?>
      <div class="empty empty-visible search-empty">
        Não encontrámos produtos<?= $query !== '' ? ' para “' . e($query) . '”' : '' ?>. Experimente outra pesquisa.
      </div>
    <?php endif; ?>
  </main>

  <footer>
    <div class="footer-inner">
      <p>© 2026 O Cantinho Mercado. Todos os direitos reservados.</p>
      <p>Feito com cuidado para si e para a sua casa.</p>
    </div>
  </footer>

  <script src="javascript.js"></script>
  <script>
    document.addEventListener('click', function (event) {
      var menu = document.getElementById('userMenu');
      if (menu && menu.open && !menu.contains(event.target)) menu.open = false;
    });
  </script>
</body>
</html>
