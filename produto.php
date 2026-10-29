<?php
require __DIR__ . '/conta/ligacao.php';
require __DIR__ . '/produto_imagens.php';
require __DIR__ . '/conta/listas_compras.php';

$idProduto = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$produto = null;

if ($idProduto !== false && $idProduto !== null) {
    $stmt = $pdo->prepare(
        'SELECT IDProduto, SKU, Nome, Descricao, Marca, Preco, PrecoPromocional,
                TaxaIVA, Unidade, StockQuantidade, StockMinimo, Alergenios,
                Imagem, Destaque
         FROM Produto
         WHERE IDProduto = ? AND Ativo = 1'
    );
    $stmt->execute([$idProduto]);
    $produto = $stmt->fetch() ?: null;
}

if (!$produto) {
    http_response_code(404);
}

$logado = !empty($_SESSION['utilizador_id']);
$nomeUtilizador = $_SESSION['utilizador_nome'] ?? '';
$primeiroNome = $nomeUtilizador !== '' ? explode(' ', trim($nomeUtilizador))[0] : '';
$inicial = $primeiroNome !== '' ? mb_strtoupper(mb_substr($primeiroNome, 0, 1)) : '';
$tipoUtilizador = $_SESSION['utilizador_tipo'] ?? 'cliente';
$listasCompra = [];
if ($logado) {
    listas_compras_preparar($pdo);
    $listasCompra = listas_compras_do_utilizador($pdo, (int) $_SESSION['utilizador_id']);
}

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

$imagensProduto = $produto ? produto_imagens((string) $produto['Nome']) : ['principal' => '', 'galeria' => []];
$imagemPrincipal = $produto && !empty($produto['Imagem']) ? (string) $produto['Imagem'] : $imagensProduto['principal'];
$galeriaGuardada = $produto ? produto_galeria_listar($pdo, (int) $produto['IDProduto']) : [];
$galeriaProduto = $galeriaGuardada
    ? array_column($galeriaGuardada, 'Caminho')
    : $imagensProduto['galeria'];
if ($imagemPrincipal !== '' && !in_array($imagemPrincipal, $galeriaProduto, true)) {
    array_unshift($galeriaProduto, $imagemPrincipal);
}
if ($imagemPrincipal !== '' && in_array($imagemPrincipal, $galeriaProduto, true)) {
    $galeriaProduto = array_values(array_unique(array_merge([$imagemPrincipal], $galeriaProduto)));
}

$titulo = $produto ? (string) $produto['Nome'] . ' | Cantinho Mercado' : 'Produto não encontrado | Cantinho Mercado';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titulo) ?></title>
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

  <main class="product-detail-main">
    <?php if ($produto): ?>
      <?php
        $preco = (float) $produto['Preco'];
        $precoPromocional = (float) ($produto['PrecoPromocional'] ?? 0);
        $emPromocao = $precoPromocional > 0 && $precoPromocional < $preco;
        $precoAtual = $emPromocao ? $precoPromocional : $preco;
        $stock = (int) $produto['StockQuantidade'];
        $stockMinimo = (int) $produto['StockMinimo'];
      ?>
      <nav class="breadcrumbs" aria-label="Navegação estrutural">
        <a href="index.php">Início</a>
        <span aria-hidden="true">/</span>
        <a href="index.php#categorias">Produtos</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e((string) $produto['Nome']) ?></span>
      </nav>

      <section class="product-detail">
        <div class="detail-media">
          <div class="detail-image">
            <?php if ($imagemPrincipal !== ''): ?>
              <img id="productMainImage" src="<?= e($imagemPrincipal) ?>" alt="<?= e((string) $produto['Nome']) ?>">
            <?php else: ?>
              <span class="image-placeholder">Imagem indisponível</span>
            <?php endif; ?>
            <?php if (!empty($produto['Destaque'])): ?>
              <span class="tag">Destaque</span>
            <?php endif; ?>
          </div>
          <?php if (count($galeriaProduto) > 1): ?>
            <div class="product-gallery" aria-label="Outras imagens de <?= e((string) $produto['Nome']) ?>">
              <?php foreach ($galeriaProduto as $indiceImagem => $imagemGaleria): ?>
                <button class="product-gallery-thumb" type="button"
                        data-gallery-src="<?= e($imagemGaleria) ?>"
                        aria-label="Ver imagem <?= $indiceImagem + 1 ?> de <?= e((string) $produto['Nome']) ?>"
                        aria-pressed="<?= $imagemGaleria === $imagemPrincipal ? 'true' : 'false' ?>">
                  <img src="<?= e($imagemGaleria) ?>" alt="">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="detail-copy">
          <p class="section-kicker"><?= $emPromocao ? 'Oferta especial' : 'Do nosso mercado para si' ?></p>
          <h1><?= e((string) $produto['Nome']) ?></h1>
          <?php if (!empty($produto['Marca'])): ?>
            <p class="detail-brand"><?= e((string) $produto['Marca']) ?></p>
          <?php endif; ?>

          <div class="detail-price">
            <?php if ($emPromocao): ?>
              <span class="old-price"><?= number_format($preco, 2, ',', '.') ?> €</span>
            <?php endif; ?>
            <strong><?= number_format($precoAtual, 2, ',', '.') ?> €</strong>
            <?php if (!empty($produto['Unidade'])): ?>
              <span class="detail-unit">/ <?= e((string) $produto['Unidade']) ?></span>
            <?php endif; ?>
          </div>

          <p class="stock-status <?= $stock > 0 ? 'in-stock' : 'out-of-stock' ?>">
            <span aria-hidden="true"></span>
            <?php if ($stock <= 0): ?>
              Temporariamente indisponível
            <?php elseif ($stockMinimo > 0 && $stock <= $stockMinimo): ?>
              Últimas <?= $stock ?> unidades disponíveis
            <?php else: ?>
              Disponível para encomenda
            <?php endif; ?>
          </p>

          <form class="detail-cart-form" method="post" action="conta/carrinho.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="adicionar">
            <input type="hidden" name="origem" value="produto">
            <input type="hidden" name="id_produto" value="<?= (int) $produto['IDProduto'] ?>">
            <?php if ($listasCompra): ?>
              <label for="lista-produto">Adicionar também a uma lista</label>
              <select name="id_lista" id="lista-produto">
                <option value="">Só no carrinho</option>
                <?php foreach ($listasCompra as $listaCompra): ?>
                  <option value="<?= (int) $listaCompra['IDListaCompra'] ?>">+ <?= e((string) $listaCompra['Nome']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
            <label for="quantidade-produto">Quantidade</label>
            <input id="quantidade-produto" name="quantidade" type="number" min="1"
                   max="<?= min(99, max(1, $stock)) ?>" value="1" <?= $stock <= 0 ? 'disabled' : '' ?> required>
            <button class="primary-link" type="submit" <?= $stock <= 0 ? 'disabled' : '' ?>>
              Adicionar ao carrinho
              <svg class="svg" viewBox="0 0 24 24"><path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
            </button>
          </form>

          <?php if (!empty($produto['Descricao'])): ?>
            <div class="detail-description">
              <h2>Sobre este produto</h2>
              <p><?= nl2br(e((string) $produto['Descricao'])) ?></p>
            </div>
          <?php endif; ?>

          <dl class="product-facts">
            <?php if (!empty($produto['Alergenios'])): ?>
              <div>
                <dt>Informação sobre alergénios</dt>
                <dd><?= e((string) $produto['Alergenios']) ?></dd>
              </div>
            <?php endif; ?>
            <?php if ($produto['TaxaIVA'] !== null): ?>
              <div>
                <dt>Taxa de IVA</dt>
                <dd><?= number_format((float) $produto['TaxaIVA'], 2, ',', '.') ?>%</dd>
              </div>
            <?php endif; ?>
            <?php if (!empty($produto['SKU'])): ?>
              <div>
                <dt>Referência</dt>
                <dd><?= e((string) $produto['SKU']) ?></dd>
              </div>
            <?php endif; ?>
          </dl>

          <a href="index.php#categorias" class="primary-link detail-back-link">
            Continuar a comprar
            <svg class="svg" viewBox="0 0 24 24"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
          </a>
        </div>
      </section>
    <?php else: ?>
      <section class="product-not-found">
        <p class="section-kicker">Produto indisponível</p>
        <h1>Não encontrámos este produto.</h1>
        <p>O produto pode ter sido removido ou o endereço pode estar incorreto.</p>
        <a href="index.php#categorias" class="primary-link">Ver produtos</a>
      </section>
    <?php endif; ?>
  </main>

  <?php require __DIR__ . '/componentes/footer.php'; ?>

  <script>
    const cartFeedback = document.getElementById('cartFeedback');
    if (cartFeedback) {
      window.setTimeout(() => cartFeedback.remove(), 3000);
    }

    document.addEventListener('click', event => {
      const userMenu = document.getElementById('userMenu');
      if (userMenu && userMenu.open && !userMenu.contains(event.target)) userMenu.open = false;
    });
  </script>
  <script src="javascript.js"></script>
</body>
</html>
