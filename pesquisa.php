<?php
require __DIR__ . '/conta/ligacao.php';
require __DIR__ . '/produto_imagens.php';

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

$feedbackCarrinho = $_SESSION['feedback_carrinho'] ?? null;
unset($_SESSION['feedback_carrinho']);

if ($query !== '') {
    $stmt = $pdo->prepare(
        'SELECT IDProduto, Nome, Marca, Preco, PrecoPromocional,
                Unidade, Imagem, Destaque, StockQuantidade
         FROM Produto
         WHERE Ativo = 1 AND (Nome LIKE ? OR Marca LIKE ?)
         ORDER BY Destaque DESC, Nome ASC'
    );
    $termoPesquisa = '%' . $query . '%';
    $stmt->execute([$termoPesquisa, $termoPesquisa]);
} else {
    $stmt = $pdo->query(
        'SELECT IDProduto, Nome, Marca, Preco, PrecoPromocional,
                Unidade, Imagem, Destaque, StockQuantidade
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
  <?php require __DIR__ . '/componentes/navbar.php'; ?>

  <?php if (is_array($feedbackCarrinho) && isset($feedbackCarrinho['mensagem'], $feedbackCarrinho['tipo'])): ?>
    <div class="cart-feedback <?= $feedbackCarrinho['tipo'] === 'ok' ? 'cart-feedback-ok' : 'cart-feedback-error' ?>"
         id="cartFeedback"
         role="<?= $feedbackCarrinho['tipo'] === 'ok' ? 'status' : 'alert' ?>">
      <?= e((string) $feedbackCarrinho['mensagem']) ?>
    </div>
  <?php endif; ?>

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
            $stock = (int) $produto['StockQuantidade'];
            $imagemProduto = (string) ($produto['Imagem'] ?? '') ?: produto_imagens($nomeProduto)['principal'];
        ?>
          <article class="product-card">
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
                    <input type="hidden" name="origem" value="pesquisa">
                    <input type="hidden" name="origem_query" value="<?= e($query) ?>">
                    <input type="hidden" name="id_produto" value="<?= (int) $produto['IDProduto'] ?>">
                    <div class="quantity-control" aria-label="Selecionar quantidade">
                      <button type="button" data-quantity-step="-1" aria-label="Diminuir quantidade de <?= e($nomeProduto) ?>" <?= $stock <= 0 ? 'disabled' : '' ?>>−</button>
                      <input name="quantidade" type="number" min="1" max="<?= min(99, max(1, $stock)) ?>" step="1" value="1"
                             aria-label="Quantidade de <?= e($nomeProduto) ?>" <?= $stock <= 0 ? 'disabled' : '' ?> required>
                      <button type="button" data-quantity-step="1" aria-label="Aumentar quantidade de <?= e($nomeProduto) ?>" <?= $stock <= 0 ? 'disabled' : '' ?>>+</button>
                    </div>
                    <button class="add-button cart-add-button" type="submit" aria-label="Adicionar <?= e($nomeProduto) ?> ao carrinho" <?= $stock <= 0 ? 'disabled' : '' ?>>
                      <svg class="svg" viewBox="0 0 24 24"><path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                    </button>
                  </form>
                </div>
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

  <?php require __DIR__ . '/componentes/footer.php'; ?>

  <script src="javascript.js"></script>
  <script>
    var cartFeedback = document.getElementById('cartFeedback');
    if (cartFeedback) {
      window.setTimeout(function () {
        cartFeedback.remove();
      }, 3000);
    }

    document.addEventListener('click', function (event) {
      var menu = document.getElementById('userMenu');
      if (menu && menu.open && !menu.contains(event.target)) menu.open = false;
    });
  </script>
</body>
</html>
