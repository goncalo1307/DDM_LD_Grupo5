<?php
require __DIR__ . '/ligacao.php';
require __DIR__ . '/../produto_imagens.php';
require __DIR__ . '/gastos.php';

if (empty($_SESSION['utilizador_id'])) {
    header('Location: conta.php?motivo=carrinho');
    exit;
}

gastos_compras_preparar($pdo);
$idUtilizador = (int) $_SESSION['utilizador_id'];
$erro = '';
$checkout = [
    'nome' => (string) ($_SESSION['utilizador_nome'] ?? ''),
    'telemovel' => '',
    'pais' => 'Portugal',
    'distrito' => '',
    'concelho' => '',
    'rua' => '',
    'numero_porta' => '',
    'numero_lote' => '',
    'codigo_postal' => '',
    'localidade' => '',
    'nif' => '',
    'metodo_pagamento' => 'pagamento_entrega',
    'notas' => '',
];
$errosCheckout = [];
$encomendaCriada = null;

$referenciaEncomenda = $_GET['encomenda'] ?? '';
if (is_string($referenciaEncomenda) && $referenciaEncomenda !== '') {
    $stmt = $pdo->prepare(
        'SELECT NumeroEncomenda, Total
         FROM Encomenda
         WHERE NumeroEncomenda = ? AND IDUtilizador = ?'
    );
    $stmt->execute([$referenciaEncomenda, $idUtilizador]);
    $encomendaCriada = $stmt->fetch() ?: null;
}

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
        } elseif ($acao === 'checkout') {
            foreach ($checkout as $campo => $valorInicial) {
                if ($campo === 'metodo_pagamento') {
                    continue;
                }
                $checkout[$campo] = isset($_POST[$campo]) && is_string($_POST[$campo])
                    ? trim($_POST[$campo])
                    : '';
            }
            $checkout['metodo_pagamento'] = isset($_POST['metodo_pagamento']) && is_string($_POST['metodo_pagamento'])
                ? $_POST['metodo_pagamento']
                : '';

            $regrasCheckout = [
                'nome' => ['Nome do destinatário', 100],
                'pais' => ['País', 60],
                'distrito' => ['Distrito', 60],
                'concelho' => ['Concelho/Região', 60],
                'rua' => ['Rua', 150],
                'numero_porta' => ['Número de porta', 10],
                'localidade' => ['Localidade', 80],
            ];
            foreach ($regrasCheckout as $campo => [$etiqueta, $maximo]) {
                if ($checkout[$campo] === '' || mb_strlen($checkout[$campo]) > $maximo) {
                    $errosCheckout[] = $etiqueta . ' é obrigatório e não pode exceder ' . $maximo . ' caracteres.';
                }
            }
            if ($checkout['telemovel'] !== '' && !preg_match('/^\+?[0-9 ]{9,20}$/', $checkout['telemovel'])) {
                $errosCheckout[] = 'Indique um número de telemóvel válido.';
            }
            if ($checkout['numero_lote'] !== '' && mb_strlen($checkout['numero_lote']) > 30) {
                $errosCheckout[] = 'Lote/andar não pode exceder 30 caracteres.';
            }
            if (!preg_match('/^\d{4}-\d{3}$/', $checkout['codigo_postal'])) {
                $errosCheckout[] = 'Indique um código postal válido (0000-000).';
            }
            if ($checkout['nif'] !== '' && !preg_match('/^\d{9}$/', $checkout['nif'])) {
                $errosCheckout[] = 'O NIF deve ter 9 dígitos.';
            }
            if (mb_strlen($checkout['notas']) > 500) {
                $errosCheckout[] = 'As notas não podem exceder 500 caracteres.';
            }
            if (!in_array($checkout['metodo_pagamento'], ['mbway', 'multibanco', 'cartao', 'paypal', 'pagamento_entrega'], true)) {
                $errosCheckout[] = 'Selecione um método de pagamento válido.';
            }
            if (!$idCarrinho) {
                $errosCheckout[] = 'O carrinho está vazio.';
            }

            $stmt = $pdo->prepare(
                'SELECT ic.Quantidade, p.IDProduto, p.SKU, p.Nome, p.Preco, p.PrecoPromocional,
                        p.TaxaIVA, p.StockQuantidade, p.Ativo
                 FROM ItemCarrinho ic
                 JOIN Produto p ON p.IDProduto = ic.IDProduto
                 WHERE ic.IDCarrinho = ?
                 ORDER BY p.IDProduto
                 FOR UPDATE'
            );
            $stmt->execute([$idCarrinho]);
            $itensCheckout = $stmt->fetchAll();
            if (!$itensCheckout) {
                $errosCheckout[] = 'O carrinho está vazio.';
            }

            $subtotalCheckout = 0.0;
            foreach ($itensCheckout as &$itemCheckout) {
                $quantidade = (int) $itemCheckout['Quantidade'];
                if (!(int) $itemCheckout['Ativo'] || $quantidade < 1 || $quantidade > 99
                    || $quantidade > (int) $itemCheckout['StockQuantidade']) {
                    $errosCheckout[] = 'Há produtos indisponíveis ou sem stock suficiente. Atualize o carrinho antes de continuar.';
                    break;
                }
                $precoBase = (float) $itemCheckout['Preco'];
                $precoPromocional = (float) ($itemCheckout['PrecoPromocional'] ?? 0);
                $itemCheckout['PrecoAtual'] = $precoPromocional > 0 && $precoPromocional < $precoBase
                    ? $precoPromocional
                    : $precoBase;
                $subtotalCheckout += round($itemCheckout['PrecoAtual'] * $quantidade, 2);
            }
            unset($itemCheckout);

            if (!$errosCheckout) {
                $custoEnvioCheckout = $subtotalCheckout > 35 ? 0.0 : 3.99;
                $totalCheckout = round($subtotalCheckout + $custoEnvioCheckout, 2);
                $morada = trim($checkout['rua'] . ', ' . $checkout['numero_porta']
                    . ($checkout['numero_lote'] !== '' ? ', ' . $checkout['numero_lote'] : ''));
                $numeroEncomenda = 'OC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));

                $pdo->prepare(
                    'INSERT INTO Encomenda
                        (NumeroEncomenda, IDUtilizador, Estado, EntregaNome, EntregaTelemovel,
                         EntregaMorada, EntregaCodigoPostal, EntregaLocalidade, EntregaConcelhoRegiao,
                         EntregaDistrito, EntregaPais, NifFatura, Subtotal, CustoEnvio, Total,
                         MetodoPagamento, EstadoPagamento, DataPagamento, NotasCliente)
                     VALUES (?, ?, \'pedido\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pendente\', NULL, ?)'
                )->execute([
                    $numeroEncomenda,
                    $idUtilizador,
                    $checkout['nome'],
                    $checkout['telemovel'] !== '' ? $checkout['telemovel'] : null,
                    $morada,
                    $checkout['codigo_postal'],
                    $checkout['localidade'],
                    $checkout['concelho'],
                    $checkout['distrito'],
                    $checkout['pais'],
                    $checkout['nif'] !== '' ? $checkout['nif'] : null,
                    number_format($subtotalCheckout, 2, '.', ''),
                    number_format($custoEnvioCheckout, 2, '.', ''),
                    number_format($totalCheckout, 2, '.', ''),
                    $checkout['metodo_pagamento'],
                    $checkout['notas'] !== '' ? $checkout['notas'] : null,
                ]);
                $idEncomenda = (int) $pdo->lastInsertId();

                $stmtItem = $pdo->prepare(
                    'INSERT INTO ItemEncomenda
                        (IDEncomenda, IDProduto, SKU, NomeProduto, Quantidade, PrecoUnitario, TaxaIVA)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmtStock = $pdo->prepare(
                    'UPDATE Produto
                     SET StockQuantidade = StockQuantidade - ?
                     WHERE IDProduto = ? AND Ativo = 1 AND StockQuantidade >= ?'
                );
                $stmtMovimento = $pdo->prepare(
                    'INSERT INTO MovimentoStock
                        (IDProduto, Tipo, Quantidade, StockApos, IDEncomenda, IDUtilizador, Nota)
                     VALUES (?, \'venda\', ?, ?, ?, ?, ?)'
                );

                foreach ($itensCheckout as $itemCheckout) {
                    $quantidade = (int) $itemCheckout['Quantidade'];
                    $stmtStock->execute([$quantidade, $itemCheckout['IDProduto'], $quantidade]);
                    if ($stmtStock->rowCount() !== 1) {
                        $erro = 'O stock de um produto mudou. Atualize o carrinho e tente novamente.';
                        break;
                    }

                    $stmtItem->execute([
                        $idEncomenda,
                        $itemCheckout['IDProduto'],
                        $itemCheckout['SKU'],
                        $itemCheckout['Nome'],
                        $quantidade,
                        number_format($itemCheckout['PrecoAtual'], 2, '.', ''),
                        number_format((float) $itemCheckout['TaxaIVA'], 2, '.', ''),
                    ]);
                    $stockApos = (int) $itemCheckout['StockQuantidade'] - $quantidade;
                    $stmtMovimento->execute([
                        $itemCheckout['IDProduto'],
                        -$quantidade,
                        $stockApos,
                        $idEncomenda,
                        $idUtilizador,
                        'Venda na encomenda ' . $numeroEncomenda,
                    ]);
                }

                if ($erro === '') {
                    $pdo->prepare(
                        'INSERT INTO HistoricoEstadoEncomenda
                            (IDEncomenda, EstadoAnterior, EstadoNovo, IDUtilizador, Nota)
                         VALUES (?, NULL, \'pedido\', ?, ?)'
                    )->execute([$idEncomenda, $idUtilizador, 'Encomenda criada pelo checkout.']);

                    $pdo->prepare(
                        'INSERT INTO GastoCompra (IDUtilizador, ReferenciaCompra, ValorTotal, DataCompra)
                         VALUES (?, ?, ?, NOW())'
                    )->execute([$idUtilizador, $numeroEncomenda, number_format($totalCheckout, 2, '.', '')]);

                    $pdo->prepare('DELETE FROM ItemCarrinho WHERE IDCarrinho = ?')->execute([$idCarrinho]);
                    $numeroEncomendaCriada = $numeroEncomenda;
                }
            }
        } else {
            $erro = 'Ação inválida.';
        }

        if ($erro === '' && !$errosCheckout) {
            if ($idCarrinho) {
                $pdo->prepare('UPDATE Carrinho SET DataAlterado = NOW() WHERE IDCarrinho = ? AND IDUtilizador = ?')
                    ->execute([$idCarrinho, $idUtilizador]);
            }
            $pdo->commit();
            if ($acao === 'checkout') {
                header('Location: carrinho.php?encomenda=' . rawurlencode($numeroEncomendaCriada));
                exit;
            }
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
$carrinhoCheckoutValido = (bool) $itens;
foreach ($itens as &$item) {
    $preco = (float) $item['Preco'];
    $precoPromocional = (float) ($item['PrecoPromocional'] ?? 0);
    $item['PrecoAtual'] = $precoPromocional > 0 && $precoPromocional < $preco ? $precoPromocional : $preco;
    $item['TotalLinha'] = $item['PrecoAtual'] * (int) $item['Quantidade'];
    $totalQuantidade += (int) $item['Quantidade'];
    $subtotal += $item['TotalLinha'];
    if (!(int) $item['Ativo'] || (int) $item['Quantidade'] > min(99, (int) $item['StockQuantidade'])) {
        $carrinhoCheckoutValido = false;
    }
}
unset($item);
$custoEnvio = $subtotal > 35 ? 0.0 : 3.99;
$totalCarrinho = round($subtotal + $custoEnvio, 2);

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
  <link rel="stylesheet" href="css/conta.css?v=checkout-20261026">
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
    <?php if ($encomendaCriada): ?>
      <div class="alert alert-ok" role="status">
        Encomenda <?= e($encomendaCriada['NumeroEncomenda']) ?> registada com sucesso.
        Total: <?= number_format((float) $encomendaCriada['Total'], 2, ',', '.') ?> €.
        O pagamento está pendente; não foi efetuada nenhuma cobrança.
      </div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert alert-error" role="alert"><?= e($erro) ?></div>
    <?php endif; ?>
    <?php if ($errosCheckout): ?>
      <div class="alert alert-error" role="alert">
        <ul><?php foreach ($errosCheckout as $erroCheckout): ?><li><?= e($erroCheckout) ?></li><?php endforeach; ?></ul>
      </div>
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
          <p><span>Entrega</span><strong><?= $custoEnvio > 0 ? number_format($custoEnvio, 2, ',', '.') . ' €' : 'Grátis' ?></strong></p>
          <p class="cart-summary-total"><span>Total</span><strong><?= number_format($totalCarrinho, 2, ',', '.') ?> €</strong></p>
          <p class="cart-summary-note">
            <?= $subtotal > 35 ? 'Entrega gratuita aplicada.' : 'Entrega gratuita em compras superiores a 35 €.' ?>
            Os preços e o stock são confirmados ao finalizar.
          </p>
          <?php if ($carrinhoCheckoutValido): ?>
            <a class="btn-small btn-cart-primary" href="#checkout">Finalizar encomenda</a>
          <?php else: ?>
            <p class="cart-stock-warning">Corrija os artigos sem stock para poder finalizar.</p>
          <?php endif; ?>
          <a class="btn-small btn-cart-primary" href="../index.php#categorias">Continuar a comprar</a>
        </aside>
      </div>

      <?php if ($carrinhoCheckoutValido): ?>
        <section class="account-card checkout-card" id="checkout">
          <h2>Finalizar encomenda</h2>
          <p>Indique a morada de entrega e o método de pagamento pretendido.</p>
          <p class="checkout-payment-note">O checkout regista a encomenda como pendente. Não processa pagamentos nem guarda dados de cartão.</p>

          <form method="post" action="carrinho.php#checkout" class="checkout-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="checkout">

            <div class="checkout-fields">
              <div>
                <label for="checkout-nome">Nome do destinatário *</label>
                <input id="checkout-nome" name="nome" type="text" maxlength="100" value="<?= e($checkout['nome']) ?>" autocomplete="name" required>
              </div>
              <div>
                <label for="checkout-telemovel">Telemóvel</label>
                <input id="checkout-telemovel" name="telemovel" type="tel" maxlength="20" value="<?= e($checkout['telemovel']) ?>" autocomplete="tel">
              </div>
              <div class="checkout-field-wide">
                <label for="checkout-rua">Rua *</label>
                <input id="checkout-rua" name="rua" type="text" maxlength="150" value="<?= e($checkout['rua']) ?>" autocomplete="street-address" required>
              </div>
              <div>
                <label for="checkout-porta">N.º de porta *</label>
                <input id="checkout-porta" name="numero_porta" type="text" maxlength="10" value="<?= e($checkout['numero_porta']) ?>" required>
              </div>
              <div>
                <label for="checkout-lote">Lote/andar</label>
                <input id="checkout-lote" name="numero_lote" type="text" maxlength="30" value="<?= e($checkout['numero_lote']) ?>">
              </div>
              <div>
                <label for="checkout-cp">Código postal *</label>
                <input id="checkout-cp" name="codigo_postal" type="text" maxlength="8" placeholder="0000-000" value="<?= e($checkout['codigo_postal']) ?>" autocomplete="postal-code" required>
              </div>
              <div>
                <label for="checkout-localidade">Localidade *</label>
                <input id="checkout-localidade" name="localidade" type="text" maxlength="80" value="<?= e($checkout['localidade']) ?>" autocomplete="address-level2" required>
              </div>
              <div>
                <label for="checkout-concelho">Concelho/Região *</label>
                <input id="checkout-concelho" name="concelho" type="text" maxlength="60" value="<?= e($checkout['concelho']) ?>" required>
              </div>
              <div>
                <label for="checkout-distrito">Distrito *</label>
                <input id="checkout-distrito" name="distrito" type="text" maxlength="60" value="<?= e($checkout['distrito']) ?>" required>
              </div>
              <div>
                <label for="checkout-pais">País *</label>
                <input id="checkout-pais" name="pais" type="text" maxlength="60" value="<?= e($checkout['pais']) ?>" autocomplete="country-name" required>
              </div>
              <div>
                <label for="checkout-nif">NIF (opcional)</label>
                <input id="checkout-nif" name="nif" type="text" inputmode="numeric" maxlength="9" value="<?= e($checkout['nif']) ?>">
              </div>
              <div class="checkout-field-wide">
                <label for="checkout-pagamento">Método de pagamento *</label>
                <select id="checkout-pagamento" name="metodo_pagamento" required>
                  <?php foreach ([
                      'pagamento_entrega' => 'Pagamento na entrega',
                      'mbway' => 'MB Way',
                      'multibanco' => 'Multibanco',
                      'cartao' => 'Cartão',
                      'paypal' => 'PayPal',
                  ] as $valorMetodo => $nomeMetodo): ?>
                    <option value="<?= e($valorMetodo) ?>" <?= $checkout['metodo_pagamento'] === $valorMetodo ? 'selected' : '' ?>><?= e($nomeMetodo) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="checkout-field-wide">
                <label for="checkout-notas">Notas para a entrega</label>
                <textarea id="checkout-notas" name="notas" maxlength="500" rows="3"><?= e($checkout['notas']) ?></textarea>
              </div>
            </div>

            <div class="checkout-confirmacao">
              <span>Total da encomenda</span>
              <strong><?= number_format($totalCarrinho, 2, ',', '.') ?> €</strong>
            </div>
            <button type="submit" class="checkout-submit">Confirmar encomenda</button>
          </form>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
