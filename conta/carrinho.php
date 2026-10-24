<?php
require __DIR__ . '/ligacao.php';
require __DIR__ . '/../produto_imagens.php';

if (empty($_SESSION['utilizador_id'])) {
    header('Location: conta.php?motivo=carrinho');
    exit;
}

$idUtilizador = (int) $_SESSION['utilizador_id'];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        http_response_code(400);
        $erro = 'Pedido inválido. Atualize a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';
        $origem = $_POST['origem'] ?? '';
        $paginaOrigem = null;
        if ($origem === 'loja') {
            $paginaOrigem = '../index.php#categorias';
        } elseif (
            $origem === 'produto'
            && isset($_POST['id_produto'])
            && is_string($_POST['id_produto'])
            && ctype_digit($_POST['id_produto'])
            && (int) $_POST['id_produto'] > 0
        ) {
            $paginaOrigem = '../produto.php?id=' . (int) $_POST['id_produto'];
        } elseif ($origem === 'pesquisa') {
            $paginaOrigem = '../pesquisa.php';
            if (isset($_POST['origem_query']) && is_string($_POST['origem_query']) && trim($_POST['origem_query']) !== '') {
                $paginaOrigem .= '?q=' . rawurlencode(trim($_POST['origem_query']));
            }
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT IDUtilizador FROM Utilizador WHERE IDUtilizador = ? AND Ativo = 1 FOR UPDATE');
        $stmt->execute([$idUtilizador]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            header('Location: logout.php');
            exit;
        }

        $stmt = $pdo->prepare('SELECT IDCarrinho FROM Carrinho WHERE IDUtilizador = ? ORDER BY IDCarrinho DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$idUtilizador]);
        $idCarrinho = (int) ($stmt->fetchColumn() ?: 0);

        if ($acao === 'adicionar') {
            $idProduto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            $quantidadeAdicionada = 0;

            if (!$idProduto || !$quantidade) {
                $erro = 'Produto ou quantidade inválidos.';
            } else {
                $stmt = $pdo->prepare('SELECT StockQuantidade FROM Produto WHERE IDProduto = ? AND Ativo = 1 FOR UPDATE');
                $stmt->execute([$idProduto]);
                $stock = $stmt->fetchColumn();

                if ($stock === false) {
                    $erro = 'Este produto já não está disponível.';
                } elseif ((int) $stock < 1) {
                    $erro = 'Este produto está sem stock.';
                } elseif (!$idCarrinho) {
                    $pdo->prepare('INSERT INTO Carrinho (IDUtilizador, DataCriacao, DataAlterado) VALUES (?, NOW(), NOW())')
                        ->execute([$idUtilizador]);
                    $idCarrinho = (int) $pdo->lastInsertId();
                }

                if ($erro === '' && $idCarrinho) {
                    $stmt = $pdo->prepare('SELECT IDItemCarrinho, Quantidade FROM ItemCarrinho WHERE IDCarrinho = ? AND IDProduto = ? FOR UPDATE');
                    $stmt->execute([$idCarrinho, $idProduto]);
                    $itemExistente = $stmt->fetch();
                    $quantidadeFinal = $quantidade + ($itemExistente ? (int) $itemExistente['Quantidade'] : 0);

                    if ($quantidadeFinal > 99 || $quantidadeFinal > (int) $stock) {
                        $erro = 'O limite é 99 unidades por produto e não pode exceder o stock disponível (' . (int) $stock . ').';
                    } elseif ($itemExistente) {
                        $pdo->prepare('UPDATE ItemCarrinho SET Quantidade = ? WHERE IDItemCarrinho = ? AND IDCarrinho = ?')
                            ->execute([$quantidadeFinal, $itemExistente['IDItemCarrinho'], $idCarrinho]);
                        $quantidadeAdicionada = $quantidade;
                    } else {
                        $pdo->prepare('INSERT INTO ItemCarrinho (IDCarrinho, IDProduto, Quantidade, DataAdicionado) VALUES (?, ?, ?, NOW())')
                            ->execute([$idCarrinho, $idProduto, $quantidadeFinal]);
                        $quantidadeAdicionada = $quantidade;
                    }
                }
            }
        } elseif ($acao === 'atualizar') {
            $idItem = filter_input(INPUT_POST, 'id_item', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);

            if (!$idCarrinho || !$idItem || !$quantidade) {
                $erro = 'Artigo ou quantidade inválidos.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT ic.IDItemCarrinho, p.StockQuantidade, p.Ativo
                     FROM ItemCarrinho ic
                     JOIN Produto p ON p.IDProduto = ic.IDProduto
                     WHERE ic.IDItemCarrinho = ? AND ic.IDCarrinho = ?
                     FOR UPDATE'
                );
                $stmt->execute([$idItem, $idCarrinho]);
                $item = $stmt->fetch();

                if (!$item) {
                    $erro = 'Este artigo já não está no carrinho.';
                } elseif (!(int) $item['Ativo']) {
                    $erro = 'Este produto já não está disponível. Remova-o do carrinho.';
                } elseif ($quantidade > 99 || $quantidade > (int) $item['StockQuantidade']) {
                    $erro = 'O limite é 99 unidades por produto e não pode exceder o stock disponível (' . (int) $item['StockQuantidade'] . ').';
                } else {
                    $pdo->prepare('UPDATE ItemCarrinho SET Quantidade = ? WHERE IDItemCarrinho = ? AND IDCarrinho = ?')
                        ->execute([$quantidade, $idItem, $idCarrinho]);
                }
            }
        } elseif ($acao === 'remover') {
            $idItem = filter_input(INPUT_POST, 'id_item', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if (!$idCarrinho || !$idItem) {
                $erro = 'Artigo inválido.';
            } else {
                $pdo->prepare('DELETE FROM ItemCarrinho WHERE IDItemCarrinho = ? AND IDCarrinho = ?')
                    ->execute([$idItem, $idCarrinho]);
            }
        } else {
            $erro = 'Ação inválida.';
        }

        if ($erro === '') {
            if ($idCarrinho) {
                $pdo->prepare('UPDATE Carrinho SET DataAlterado = NOW() WHERE IDCarrinho = ? AND IDUtilizador = ?')
                    ->execute([$idCarrinho, $idUtilizador]);
            }
            $pdo->commit();
            if ($acao === 'adicionar' && $paginaOrigem !== null) {
                $_SESSION['feedback_carrinho'] = [
                    'tipo' => 'ok',
                    'mensagem' => 'Foi inserido ' . $quantidadeAdicionada . ' '
                        . ($quantidadeAdicionada === 1 ? 'produto' : 'produtos') . ' no seu carrinho.',
                ];
                header('Location: ' . $paginaOrigem);
                exit;
            }
            $mensagem = $acao === 'adicionar' ? 'adicionado' : ($acao === 'atualizar' ? 'atualizado' : 'removido');
            header('Location: carrinho.php?ok=' . $mensagem);
            exit;
        }

        $pdo->rollBack();
        if ($acao === 'adicionar' && $paginaOrigem !== null) {
            $_SESSION['feedback_carrinho'] = ['tipo' => 'erro', 'mensagem' => $erro];
            header('Location: ' . $paginaOrigem);
            exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT IDCarrinho FROM Carrinho WHERE IDUtilizador = ? ORDER BY IDCarrinho DESC LIMIT 1');
$stmt->execute([$idUtilizador]);
$idCarrinho = (int) ($stmt->fetchColumn() ?: 0);
$itens = [];

if ($idCarrinho) {
    $stmt = $pdo->prepare(
        'SELECT ic.IDItemCarrinho, ic.Quantidade, p.IDProduto, p.Nome, p.Imagem,
                p.Preco, p.PrecoPromocional, p.Unidade, p.StockQuantidade, p.Ativo
         FROM ItemCarrinho ic
         JOIN Produto p ON p.IDProduto = ic.IDProduto
         WHERE ic.IDCarrinho = ?
         ORDER BY ic.DataAdicionado DESC, ic.IDItemCarrinho DESC'
    );
    $stmt->execute([$idCarrinho]);
    $itens = $stmt->fetchAll();
}

$totalQuantidade = 0;
$subtotal = 0.0;
foreach ($itens as &$item) {
    $preco = (float) $item['Preco'];
    $precoPromocional = (float) ($item['PrecoPromocional'] ?? 0);
    $item['PrecoAtual'] = $precoPromocional > 0 && $precoPromocional < $preco ? $precoPromocional : $preco;
    $item['TotalLinha'] = $item['PrecoAtual'] * (int) $item['Quantidade'];
    $totalQuantidade += (int) $item['Quantidade'];
    $subtotal += $item['TotalLinha'];
}
unset($item);

$mensagens = [
    'adicionado' => 'Produto adicionado ao carrinho.',
    'atualizado' => 'Quantidade atualizada.',
    'removido' => 'Produto removido do carrinho.',
];
$ok = $mensagens[$_GET['ok'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>O meu carrinho | Cantinho</title>
  <link rel="stylesheet" href="css/conta.css">
</head>
<body>
  <header class="account-header">
    <a class="brand" href="../index.php" aria-label="Cantinho - página inicial">
      <span class="brand-mark" aria-hidden="true">
        <svg class="svg" viewBox="0 0 24 24"><path d="M20 4C12 4 5 8 5 15c0 2 1 4 3 5 1-5 4-8 9-11-4 4-6 7-7 11 6 0 10-4 10-16Z"/></svg>
      </span>
      <span>Cantinho</span>
    </a>
    <a class="back-link" href="../index.php">Continuar a comprar</a>
  </header>

  <main class="account-main cart-main">
    <div class="intro perfil-top">
      <div>
        <p class="eyebrow">A sua conta Cantinho</p>
        <h1>O meu carrinho</h1>
        <p><?= $totalQuantidade ?> <?= $totalQuantidade === 1 ? 'artigo guardado' : 'artigos guardados' ?> na sua conta.</p>
      </div>
      <a class="btn-small btn-secondary" href="perfil.php">Voltar à minha conta</a>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-ok" role="status"><?= e($ok) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert alert-error" role="alert"><?= e($erro) ?></div>
    <?php endif; ?>

    <?php if (!$itens): ?>
      <section class="account-card cart-empty">
        <h2>O seu carrinho está vazio</h2>
        <p>Adicione os seus produtos favoritos e eles ficam guardados na sua conta, mesmo depois de terminar sessão.</p>
        <a class="btn-small btn-cart-primary" href="../index.php#categorias">Explorar produtos</a>
      </section>
    <?php else: ?>
      <div class="cart-layout">
        <section class="account-card cart-card" aria-label="Artigos no carrinho">
          <?php foreach ($itens as $item): ?>
            <?php
              $disponivel = (int) $item['Ativo'] === 1 && (int) $item['StockQuantidade'] > 0;
              $quantidadeValida = (int) $item['Quantidade'] <= min(99, (int) $item['StockQuantidade']);
              $imagemCarrinho = (string) ($item['Imagem'] ?? '') ?: produto_imagens((string) $item['Nome'])['principal'];
              if ($imagemCarrinho !== '' && !preg_match('~^(?:[a-z][a-z0-9+.-]*:|/)~i', $imagemCarrinho)) {
                $imagemCarrinho = '../' . $imagemCarrinho;
              }
            ?>
            <article class="cart-item">
              <a class="cart-item-image" href="../produto.php?id=<?= (int) $item['IDProduto'] ?>">
                <?php if ($imagemCarrinho !== ''): ?>
                  <img src="<?= e($imagemCarrinho) ?>" alt="<?= e((string) $item['Nome']) ?>">
                <?php else: ?>
                  <span>Imagem indisponível</span>
                <?php endif; ?>
              </a>
              <div class="cart-item-info">
                <h2><a href="../produto.php?id=<?= (int) $item['IDProduto'] ?>"><?= e((string) $item['Nome']) ?></a></h2>
                <?php if (!empty($item['Unidade'])): ?><p><?= e((string) $item['Unidade']) ?></p><?php endif; ?>
                <strong><?= number_format($item['PrecoAtual'], 2, ',', '.') ?> €</strong>
                <?php if (!$disponivel): ?>
                  <p class="cart-stock-warning">Indisponível de momento. Remova este artigo para continuar.</p>
                <?php elseif (!$quantidadeValida): ?>
                  <p class="cart-stock-warning">Stock disponível: <?= (int) $item['StockQuantidade'] ?>.</p>
                <?php endif; ?>
              </div>
              <div class="cart-item-actions">
                <?php if ($disponivel): ?>
                  <form method="post" action="carrinho.php" class="cart-quantity-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="atualizar">
                    <input type="hidden" name="id_item" value="<?= (int) $item['IDItemCarrinho'] ?>">
                    <label class="visually-hidden" for="quantidade-<?= (int) $item['IDItemCarrinho'] ?>">Quantidade de <?= e((string) $item['Nome']) ?></label>
                    <input id="quantidade-<?= (int) $item['IDItemCarrinho'] ?>" name="quantidade" type="number" min="1" max="<?= min(99, (int) $item['StockQuantidade']) ?>" value="<?= (int) $item['Quantidade'] ?>" required>
                    <button class="btn-small btn-secondary" type="submit">Atualizar</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="carrinho.php">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="remover">
                  <input type="hidden" name="id_item" value="<?= (int) $item['IDItemCarrinho'] ?>">
                  <button class="cart-remove-button" type="submit">Remover</button>
                </form>
              </div>
              <strong class="cart-line-total"><?= number_format($item['TotalLinha'], 2, ',', '.') ?> €</strong>
            </article>
          <?php endforeach; ?>
        </section>

        <aside class="account-card cart-summary">
          <h2>Resumo</h2>
          <p><span>Subtotal</span><strong><?= number_format($subtotal, 2, ',', '.') ?> €</strong></p>
          <p class="cart-summary-note">O preço e a disponibilidade dos produtos podem mudar até à confirmação da encomenda.</p>
          <a class="btn-small btn-cart-primary" href="../index.php#categorias">Continuar a comprar</a>
        </aside>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
