<?php
require __DIR__ . '/conta/ligacao.php';
require __DIR__ . '/produto_imagens.php';
require __DIR__ . '/conta/listas_compras.php';

if (isset($_GET['q']) && is_string($_GET['q']) && trim($_GET['q']) !== '') {
    header('Location: pesquisa.php?q=' . rawurlencode(trim($_GET['q'])));
    exit;
}

$logado         = !empty($_SESSION['utilizador_id']);
$nomeUtilizador = $_SESSION['utilizador_nome'] ?? '';
$primeiroNome   = $nomeUtilizador !== '' ? explode(' ', trim($nomeUtilizador))[0] : '';
$inicial        = $primeiroNome !== '' ? mb_strtoupper(mb_substr($primeiroNome, 0, 1)) : '';
$tipoUtilizador = $_SESSION['utilizador_tipo'] ?? 'cliente';
$listasCompra = [];
if ($logado) {
    listas_compras_preparar($pdo);
    $listasCompra = listas_compras_do_utilizador($pdo, (int) $_SESSION['utilizador_id']);
}

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

$feedbackCarrinho = $_SESSION['feedback_carrinho'] ?? null;
unset($_SESSION['feedback_carrinho']);

$categoriasFiltro = $pdo->query(
    'SELECT IDCategoria, IDCategoriaPai, Nome
     FROM Categoria
     WHERE Ativo = 1
     ORDER BY Nome ASC, IDCategoria ASC'
)->fetchAll();
$categoriasPorPai = [];
foreach ($categoriasFiltro as $categoriaFiltro) {
    $categoriasPorPai[$categoriaFiltro['IDCategoriaPai'] ?? 0][] = $categoriaFiltro;
}

$categoriaSelecionada = null;
$idCategoria = null;
if (isset($_GET['categoria'])) {
    $idCategoriaRaw = is_string($_GET['categoria']) ? $_GET['categoria'] : '';
    if (!ctype_digit($idCategoriaRaw) || (int) $idCategoriaRaw < 1) {
        http_response_code(400);
    } else {
        $stmt = $pdo->prepare('SELECT IDCategoria, Nome FROM Categoria WHERE IDCategoria = ? AND Ativo = 1');
        $stmt->execute([(int) $idCategoriaRaw]);
        $categoriaSelecionada = $stmt->fetch() ?: null;
        if ($categoriaSelecionada) {
            $idCategoria = (int) $categoriaSelecionada['IDCategoria'];
        } else {
            http_response_code(404);
        }
    }
}

$sqlProdutos = 'SELECT p.IDProduto, p.Nome, p.Marca, p.Preco, p.PrecoPromocional,
                       p.Unidade, p.Imagem, p.Destaque
                FROM Produto p
                JOIN Categoria c ON c.IDCategoria = p.IDCategoria
                WHERE p.Ativo = 1 AND c.Ativo = 1';
if ($idCategoria !== null) {
    $stmt = $pdo->prepare($sqlProdutos . ' AND p.IDCategoria = ? ORDER BY p.Destaque DESC, p.Nome ASC');
    $stmt->execute([$idCategoria]);
    $produtos = $stmt->fetchAll();
} elseif (isset($_GET['categoria'])) {
    $produtos = [];
} else {
    $stmt = $pdo->query($sqlProdutos . ' ORDER BY p.Destaque DESC, p.Nome ASC');
    $produtos = $stmt->fetchAll();
}
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
  <?php require __DIR__ . '/componentes/navbar.php'; ?>

  <?php if (is_array($feedbackCarrinho) && isset($feedbackCarrinho['mensagem'], $feedbackCarrinho['tipo'])): ?>
    <div class="cart-feedback <?= $feedbackCarrinho['tipo'] === 'ok' ? 'cart-feedback-ok' : 'cart-feedback-error' ?>"
         id="cartFeedback"
         role="<?= $feedbackCarrinho['tipo'] === 'ok' ? 'status' : 'alert' ?>">
      <?= e((string) $feedbackCarrinho['mensagem']) ?>
    </div>
  <?php endif; ?>

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
          <h2><?= $categoriaSelecionada ? e((string) $categoriaSelecionada['Nome']) : 'Tudo o que precisa' ?></h2>
        </div>
        <?php if ($categoriaSelecionada): ?>
          <a class="category-filter-reset" href="index.php#categorias">Ver todos os produtos</a>
        <?php endif; ?>
      </div>
      <div class="catalog-filter">
        <div class="catalog-filter-copy">
          <span class="catalog-filter-icon" aria-hidden="true">
            <svg class="svg" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10m-7 6h4"/><circle cx="7" cy="6" r="1"/><circle cx="17" cy="12" r="1"/><circle cx="10" cy="18" r="1"/></svg>
          </span>
          <span><strong>À procura de algo específico?</strong><small>Explore o catálogo por categoria</small></span>
        </div>
        <details class="catalog-filter-menu">
          <summary>
            <span>Produtos</span>
            <svg class="svg" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </summary>
          <div class="catalog-filter-panel">
            <a class="catalog-filter-all" href="index.php#categorias">Ver todos os produtos</a>
            <?php foreach ($categoriasPorPai[0] ?? [] as $categoriaFiltro): ?>
              <?php $subcategoriasFiltro = $categoriasPorPai[$categoriaFiltro['IDCategoria']] ?? []; ?>
              <?php if ($subcategoriasFiltro): ?>
                <details class="catalog-filter-category">
                  <summary><?= e($categoriaFiltro['Nome']) ?><span aria-hidden="true">+</span></summary>
                  <div class="catalog-filter-subcategories">
                    <?php foreach ($subcategoriasFiltro as $subcategoriaFiltro): ?>
                      <a href="index.php?categoria=<?= (int) $subcategoriaFiltro['IDCategoria'] ?>#categorias">
                        <?= e($subcategoriaFiltro['Nome']) ?><span aria-hidden="true">›</span>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php else: ?>
                <a class="catalog-filter-category-link" href="index.php?categoria=<?= (int) $categoriaFiltro['IDCategoria'] ?>#categorias">
                  <?= e($categoriaFiltro['Nome']) ?><span aria-hidden="true">›</span>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </details>
      </div>
      <div class="product-grid" id="productGrid">
        <?php foreach ($produtos as $produto):
            $preco = (float) $produto['Preco'];
            $precoPromocional = (float) ($produto['PrecoPromocional'] ?? 0);
            $emPromocao = $precoPromocional > 0 && $precoPromocional < $preco;
            $precoAtual = $emPromocao ? $precoPromocional : $preco;
            $nomeProduto = (string) $produto['Nome'];
            $imagemProduto = (string) ($produto['Imagem'] ?? '') ?: produto_imagens($nomeProduto)['principal'];
        ?>
          <article class="product-card" data-name="<?= e($nomeProduto) ?>">
            <a class="product-image product-image-link" href="produto.php?id=<?= (int) $produto['IDProduto'] ?>" aria-label="Ver <?= e($nomeProduto) ?>">
              <?php if ($imagemProduto !== ''): ?>
                <img src="<?= e($imagemProduto) ?>" alt="<?= e($nomeProduto) ?>">
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
                <div class="product-actions">
                  <a class="add-button details-button" href="produto.php?id=<?= (int) $produto['IDProduto'] ?>" aria-label="Ver detalhes de <?= e($nomeProduto) ?>">
                    <svg class="svg" viewBox="0 0 24 24"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                  </a>
                  <form class="cart-add-form" method="post" action="conta/carrinho.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="adicionar">
                    <input type="hidden" name="origem" value="loja">
                    <input type="hidden" name="id_produto" value="<?= (int) $produto['IDProduto'] ?>">
                    <div class="quantity-control" aria-label="Selecionar quantidade">
                      <button type="button" data-quantity-step="-1" aria-label="Diminuir quantidade de <?= e($nomeProduto) ?>">−</button>
                      <input name="quantidade" type="number" min="1" max="99" step="1" value="1"
                             aria-label="Quantidade de <?= e($nomeProduto) ?>" required>
                      <button type="button" data-quantity-step="1" aria-label="Aumentar quantidade de <?= e($nomeProduto) ?>">+</button>
                    </div>
                    <button class="add-button cart-add-button" type="submit" aria-label="Adicionar <?= e($nomeProduto) ?> ao carrinho">
                      <svg class="svg" viewBox="0 0 24 24"><path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="empty" id="emptyMessage">Não foram encontrados produtos. Experimente outra pesquisa.</div>
      <?php if (!$produtos): ?>
        <div class="empty empty-visible">
          <?= $categoriaSelecionada ? 'Ainda não existem produtos nesta categoria.' : 'Ainda não existem produtos disponíveis.' ?>
        </div>
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

  <?php require __DIR__ . '/componentes/footer.php'; ?>

  <script src="javascript.js"></script>
  <script>
    var cartFeedback = document.getElementById('cartFeedback');
    if (cartFeedback) {
      window.setTimeout(function () {
        cartFeedback.remove();
      }, 3000);
    }

    // Fecha o menu da conta ao clicar fora
    document.addEventListener('click', function (ev) {
      var menu = document.getElementById('userMenu');
      if (menu && menu.open && !menu.contains(ev.target)) menu.open = false;
    });
  </script>
</body>
</html>