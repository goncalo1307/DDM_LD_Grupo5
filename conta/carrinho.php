<?php
require __DIR__ . '/ligacao.php';
require __DIR__ . '/../produto_imagens.php';
require __DIR__ . '/gastos.php';
require __DIR__ . '/listas_compras.php';
$indicativosTelefone = require __DIR__ . '/indicativos.php';

if (empty($_SESSION['utilizador_id'])) {
    header('Location: conta.php?motivo=carrinho');
    exit;
}

gastos_compras_preparar($pdo);
listas_compras_preparar($pdo);
$idUtilizador = (int) $_SESSION['utilizador_id'];
$listasCompra = listas_compras_do_utilizador($pdo, $idUtilizador);
$erro = '';
$nomeListaCheckout = '';
$listaCompraCheckoutGuardada = '';
$checkout = [
    'nome' => (string) ($_SESSION['utilizador_nome'] ?? ''),
    'telemovel' => '',
    'indicativo' => 'pt',
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
$stmt = $pdo->prepare(
    'SELECT IDMorada, Designacao, NomeDestinatario, Telemovel, Pais, Distrito, ConcelhoRegiao,
            Rua, NumeroPorta, NumeroLote, CodigoPostal, Localidade, Predefinida
     FROM Morada
     WHERE IDUtilizador = ?
     ORDER BY Predefinida DESC, IDMorada DESC'
);
$stmt->execute([$idUtilizador]);
$moradasGuardadas = $stmt->fetchAll();
$moradaPredefinida = '';
foreach ($moradasGuardadas as $moradaGuardada) {
    if ((int) $moradaGuardada['Predefinida'] === 1) {
        $moradaPredefinida = (string) $moradaGuardada['IDMorada'];
        break;
    }
}
$moradaCheckoutSelecionada = $moradaPredefinida !== '' ? $moradaPredefinida : 'nova';
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

        if ($acao === 'adicionar_lista') {
            $idProduto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            $valorLista = $_POST['id_lista'] ?? '';
            $nomeNovaLista = isset($_POST['nome_nova_lista']) && is_string($_POST['nome_nova_lista'])
                ? trim($_POST['nome_nova_lista'])
                : '';
            $idListaSelecionada = null;
            $listaNovaCriada = false;

            if (!$idProduto || !$quantidade) {
                $erro = 'Produto ou quantidade inválidos.';
            } elseif (!is_string($valorLista) || $valorLista !== '' && !ctype_digit($valorLista)) {
                $erro = 'Lista de compras inválida.';
            } elseif ($valorLista !== '') {
                $idListaSelecionada = (int) $valorLista;
                $stmt = $pdo->prepare(
                    'SELECT IDListaCompra FROM ListaCompra
                     WHERE IDListaCompra = ? AND IDUtilizador = ?
                     FOR UPDATE'
                );
                $stmt->execute([$idListaSelecionada, $idUtilizador]);
                if (!$stmt->fetch()) {
                    $erro = 'A lista selecionada já não existe.';
                }
            } elseif ($nomeNovaLista === '' || mb_strlen($nomeNovaLista) > 80) {
                $erro = 'Indique um nome para a nova lista (até 80 caracteres).';
            }

            if ($erro === '') {
                $stmt = $pdo->prepare('SELECT IDProduto FROM Produto WHERE IDProduto = ? AND Ativo = 1 FOR UPDATE');
                $stmt->execute([$idProduto]);
                if (!$stmt->fetch()) {
                    $erro = 'Este produto já não está disponível.';
                }
            }

            if ($erro === '') {
                if ($idListaSelecionada === null) {
                    $pdo->prepare('INSERT INTO ListaCompra (IDUtilizador, Nome) VALUES (?, ?)')
                        ->execute([$idUtilizador, $nomeNovaLista]);
                    $idListaSelecionada = (int) $pdo->lastInsertId();
                    $listaNovaCriada = true;
                }

                $stmt = $pdo->prepare(
                    'SELECT IDItemListaCompra, Quantidade
                     FROM ItemListaCompra
                     WHERE IDListaCompra = ? AND IDProduto = ?
                     FOR UPDATE'
                );
                $stmt->execute([$idListaSelecionada, $idProduto]);
                $itemListaExistente = $stmt->fetch();
                $quantidadeLista = $quantidade + ($itemListaExistente ? (int) $itemListaExistente['Quantidade'] : 0);
                if ($quantidadeLista > 99) {
                    $erro = 'O limite é 99 unidades por produto numa lista.';
                } elseif ($itemListaExistente) {
                    $pdo->prepare(
                        'UPDATE ItemListaCompra SET Quantidade = ?
                         WHERE IDItemListaCompra = ? AND IDListaCompra = ?'
                    )->execute([$quantidadeLista, $itemListaExistente['IDItemListaCompra'], $idListaSelecionada]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO ItemListaCompra (IDListaCompra, IDProduto, Quantidade)
                         VALUES (?, ?, ?)'
                    )->execute([$idListaSelecionada, $idProduto, $quantidade]);
                }
                if ($erro === '') {
                    $pdo->prepare('UPDATE ListaCompra SET DataAlterado = NOW() WHERE IDListaCompra = ?')
                        ->execute([$idListaSelecionada]);
                }
            }
        } elseif ($acao === 'adicionar') {
            $idProduto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            $quantidadeAdicionada = 0;
            $idListaSelecionada = null;
            $valorLista = $_POST['id_lista'] ?? '';

            if (!is_string($valorLista) || $valorLista !== '' && !ctype_digit($valorLista)) {
                $erro = 'Lista de compras inválida.';
            } elseif ($valorLista !== '') {
                $idListaSelecionada = (int) $valorLista;
                $stmt = $pdo->prepare(
                    'SELECT IDListaCompra FROM ListaCompra
                     WHERE IDListaCompra = ? AND IDUtilizador = ?
                     FOR UPDATE'
                );
                $stmt->execute([$idListaSelecionada, $idUtilizador]);
                if (!$stmt->fetch()) {
                    $erro = 'A lista selecionada já não existe.';
                }
            }

            if ($erro === '' && (!$idProduto || !$quantidade)) {
                $erro = 'Produto ou quantidade inválidos.';
            } elseif ($erro === '') {
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

                if ($erro === '' && $idListaSelecionada !== null) {
                    $stmt = $pdo->prepare(
                        'SELECT IDItemListaCompra, Quantidade
                         FROM ItemListaCompra
                         WHERE IDListaCompra = ? AND IDProduto = ?
                         FOR UPDATE'
                    );
                    $stmt->execute([$idListaSelecionada, $idProduto]);
                    $itemListaExistente = $stmt->fetch();
                    $quantidadeLista = $quantidade + ($itemListaExistente ? (int) $itemListaExistente['Quantidade'] : 0);
                    if ($quantidadeLista > 99) {
                        $erro = 'O limite é 99 unidades por produto numa lista.';
                    } elseif ($itemListaExistente) {
                        $pdo->prepare(
                            'UPDATE ItemListaCompra SET Quantidade = ?
                             WHERE IDItemListaCompra = ? AND IDListaCompra = ?'
                        )->execute([$quantidadeLista, $itemListaExistente['IDItemListaCompra'], $idListaSelecionada]);
                    } else {
                        $pdo->prepare(
                            'INSERT INTO ItemListaCompra (IDListaCompra, IDProduto, Quantidade)
                             VALUES (?, ?, ?)'
                        )->execute([$idListaSelecionada, $idProduto, $quantidade]);
                    }
                    if ($erro === '') {
                        $pdo->prepare('UPDATE ListaCompra SET DataAlterado = NOW() WHERE IDListaCompra = ?')
                            ->execute([$idListaSelecionada]);
                    }
                }
            }
        } elseif ($acao === 'carregar_lista') {
            $idLista = filter_input(INPUT_POST, 'id_lista', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$idLista) {
                $erro = 'Selecione uma lista válida.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT IDListaCompra FROM ListaCompra
                     WHERE IDListaCompra = ? AND IDUtilizador = ?
                     FOR UPDATE'
                );
                $stmt->execute([$idLista, $idUtilizador]);
                if (!$stmt->fetch()) {
                    $erro = 'Esta lista já não existe.';
                } else {
                    $stmt = $pdo->prepare(
                        'SELECT il.IDProduto, il.Quantidade, p.Ativo, p.StockQuantidade, p.Nome
                         FROM ItemListaCompra il
                         JOIN Produto p ON p.IDProduto = il.IDProduto
                         WHERE il.IDListaCompra = ?
                         ORDER BY p.Nome
                         FOR UPDATE'
                    );
                    $stmt->execute([$idLista]);
                    $itensLista = $stmt->fetchAll();
                    if (!$itensLista) {
                        $erro = 'Esta lista ainda não tem produtos.';
                    }

                    foreach ($itensLista as $itemLista) {
                        $stmt = $pdo->prepare(
                            'SELECT Quantidade FROM ItemCarrinho
                             WHERE IDCarrinho = ? AND IDProduto = ?
                             FOR UPDATE'
                        );
                        $stmt->execute([$idCarrinho, $itemLista['IDProduto']]);
                        $quantidadeCarrinho = (int) ($stmt->fetchColumn() ?: 0);
                        $quantidadeFinal = $quantidadeCarrinho + (int) $itemLista['Quantidade'];
                        if (!(int) $itemLista['Ativo'] || $quantidadeFinal > 99
                            || $quantidadeFinal > (int) $itemLista['StockQuantidade']) {
                            $erro = 'Não foi possível carregar a lista: "' . $itemLista['Nome']
                                . '" está indisponível ou excede o stock. Ajuste a lista ou o carrinho e tente novamente.';
                            break;
                        }
                    }

                    if ($erro === '' && !$idCarrinho) {
                        $pdo->prepare('INSERT INTO Carrinho (IDUtilizador, DataCriacao, DataAlterado) VALUES (?, NOW(), NOW())')
                            ->execute([$idUtilizador]);
                        $idCarrinho = (int) $pdo->lastInsertId();
                    }
                    if ($erro === '') {
                        foreach ($itensLista as $itemLista) {
                            $stmt = $pdo->prepare(
                                'SELECT IDItemCarrinho, Quantidade FROM ItemCarrinho
                                 WHERE IDCarrinho = ? AND IDProduto = ?
                                 FOR UPDATE'
                            );
                            $stmt->execute([$idCarrinho, $itemLista['IDProduto']]);
                            $itemCarrinhoExistente = $stmt->fetch();
                            if ($itemCarrinhoExistente) {
                                $pdo->prepare(
                                    'UPDATE ItemCarrinho SET Quantidade = ?
                                     WHERE IDItemCarrinho = ? AND IDCarrinho = ?'
                                )->execute([
                                    (int) $itemCarrinhoExistente['Quantidade'] + (int) $itemLista['Quantidade'],
                                    $itemCarrinhoExistente['IDItemCarrinho'],
                                    $idCarrinho,
                                ]);
                            } else {
                                $pdo->prepare(
                                    'INSERT INTO ItemCarrinho (IDCarrinho, IDProduto, Quantidade, DataAdicionado)
                                     VALUES (?, ?, ?, NOW())'
                                )->execute([$idCarrinho, $itemLista['IDProduto'], $itemLista['Quantidade']]);
                            }
                        }
                    }
                }
            }
        } elseif ($acao === 'atualizar_item_lista') {
            $idItemLista = filter_input(INPUT_POST, 'id_item_lista', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $idLista = filter_input(INPUT_POST, 'id_lista', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            if (!$idItemLista || !$idLista || !$quantidade) {
                $erro = 'Artigo ou quantidade inválidos.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT il.IDItemListaCompra
                     FROM ItemListaCompra il
                     JOIN ListaCompra l ON l.IDListaCompra = il.IDListaCompra
                     WHERE il.IDItemListaCompra = ? AND l.IDListaCompra = ? AND l.IDUtilizador = ?
                     FOR UPDATE'
                );
                $stmt->execute([$idItemLista, $idLista, $idUtilizador]);
                if (!$stmt->fetch()) {
                    $erro = 'Este artigo já não está na lista.';
                } else {
                    $pdo->prepare(
                        'UPDATE ItemListaCompra SET Quantidade = ?
                         WHERE IDItemListaCompra = ? AND IDListaCompra = ?'
                    )->execute([$quantidade, $idItemLista, $idLista]);
                    $pdo->prepare('UPDATE ListaCompra SET DataAlterado = NOW() WHERE IDListaCompra = ?')
                        ->execute([$idLista]);
                }
            }
        } elseif ($acao === 'remover_item_lista') {
            $idItemLista = filter_input(INPUT_POST, 'id_item_lista', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $idLista = filter_input(INPUT_POST, 'id_lista', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$idItemLista || !$idLista) {
                $erro = 'Artigo inválido.';
            } else {
                $stmt = $pdo->prepare(
                    'DELETE il FROM ItemListaCompra il
                     JOIN ListaCompra l ON l.IDListaCompra = il.IDListaCompra
                     WHERE il.IDItemListaCompra = ? AND l.IDListaCompra = ? AND l.IDUtilizador = ?'
                );
                $stmt->execute([$idItemLista, $idLista, $idUtilizador]);
                if ($stmt->rowCount() === 0) {
                    $erro = 'Este artigo já não está na lista.';
                } else {
                    $pdo->prepare('UPDATE ListaCompra SET DataAlterado = NOW() WHERE IDListaCompra = ?')
                        ->execute([$idLista]);
                }
            }
        } elseif ($acao === 'eliminar_lista') {
            $idLista = filter_input(INPUT_POST, 'id_lista', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$idLista) {
                $erro = 'Lista inválida.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM ListaCompra WHERE IDListaCompra = ? AND IDUtilizador = ?');
                $stmt->execute([$idLista, $idUtilizador]);
                if ($stmt->rowCount() === 0) {
                    $erro = 'Esta lista já não existe.';
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
            $nomeListaCheckout = isset($_POST['nome_lista_compra']) && is_string($_POST['nome_lista_compra'])
                ? trim($_POST['nome_lista_compra'])
                : '';
            $guardarCompraComoLista = isset($_POST['guardar_compra_lista']);
            if ($guardarCompraComoLista && mb_strlen($nomeListaCheckout) > 80) {
                $errosCheckout[] = 'O nome da lista não pode exceder 80 caracteres.';
            }
            $moradaCheckoutSelecionada = 'nova';
            $idMoradaSubmetida = 'nova';
            if ($moradasGuardadas) {
                $idMoradaSubmetida = isset($_POST['morada_guardada']) && is_string($_POST['morada_guardada'])
                    ? $_POST['morada_guardada']
                    : '';
            }
            $moradaSelecionadaCheckout = null;
            foreach ($moradasGuardadas as $moradaGuardada) {
                if ((string) $moradaGuardada['IDMorada'] === $idMoradaSubmetida) {
                    $moradaCheckoutSelecionada = $idMoradaSubmetida;
                    $moradaSelecionadaCheckout = $moradaGuardada;
                    break;
                }
            }
            if ($idMoradaSubmetida !== 'nova' && $moradaSelecionadaCheckout === null) {
                $errosCheckout[] = 'Selecione uma morada válida da sua conta.';
            } elseif ($idMoradaSubmetida === 'nova') {
                $moradaCheckoutSelecionada = 'nova';
            }
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

            $telefoneParaEncomenda = null;
            if ($moradaSelecionadaCheckout !== null) {
                $checkout['nome'] = $moradaSelecionadaCheckout['NomeDestinatario'];
                $checkout['telemovel'] = (string) ($moradaSelecionadaCheckout['Telemovel'] ?? '');
                $checkout['pais'] = $moradaSelecionadaCheckout['Pais'];
                $checkout['distrito'] = $moradaSelecionadaCheckout['Distrito'];
                $checkout['concelho'] = $moradaSelecionadaCheckout['ConcelhoRegiao'];
                $checkout['rua'] = $moradaSelecionadaCheckout['Rua'];
                $checkout['numero_porta'] = $moradaSelecionadaCheckout['NumeroPorta'];
                $checkout['numero_lote'] = (string) ($moradaSelecionadaCheckout['NumeroLote'] ?? '');
                $checkout['codigo_postal'] = $moradaSelecionadaCheckout['CodigoPostal'];
                $checkout['localidade'] = $moradaSelecionadaCheckout['Localidade'];
                $telefoneGuardado = trim($checkout['telemovel']);
                if ($telefoneGuardado !== '' && substr($telefoneGuardado, 0, 1) !== '+') {
                    $digitosGuardados = preg_replace('/\D/', '', $telefoneGuardado);
                    $paisGuardado = mb_strtolower(trim($checkout['pais']));
                    foreach ($indicativosTelefone as $configTelefoneGuardado) {
                        $aliasesPais = array_merge([$configTelefoneGuardado['pais']], $configTelefoneGuardado['aliases']);
                        $correspondePais = false;
                        foreach ($aliasesPais as $aliasPais) {
                            if (mb_strtolower($aliasPais) === $paisGuardado) {
                                $correspondePais = true;
                                break;
                            }
                        }
                        if (!$correspondePais) {
                            continue;
                        }
                        if (substr($digitosGuardados, 0, strlen($configTelefoneGuardado['codigo'])) === $configTelefoneGuardado['codigo']
                            && strlen($digitosGuardados) > $configTelefoneGuardado['max']) {
                            $digitosGuardados = substr($digitosGuardados, strlen($configTelefoneGuardado['codigo']));
                        }
                        if (strlen($digitosGuardados) >= $configTelefoneGuardado['min']
                            && strlen($digitosGuardados) <= $configTelefoneGuardado['max']) {
                            $telefoneGuardado = '+' . $configTelefoneGuardado['codigo'] . $digitosGuardados;
                        }
                        break;
                    }
                }
                $telefoneParaEncomenda = $telefoneGuardado !== '' ? $telefoneGuardado : null;
            } else {
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
                $configTelefone = $indicativosTelefone[$checkout['indicativo']] ?? null;
                if ($configTelefone === null) {
                    $errosCheckout[] = 'Selecione um indicativo telefónico válido.';
                } elseif ($checkout['telemovel'] !== '') {
                    if (!preg_match('/^[0-9 ]+$/', $checkout['telemovel'])) {
                        $errosCheckout[] = 'Indique um número de telemóvel válido.';
                    } else {
                        $digitosTelefone = preg_replace('/\D/', '', $checkout['telemovel']);
                        $totalDigitosTelefone = strlen($digitosTelefone);
                        if ($totalDigitosTelefone < $configTelefone['min'] || $totalDigitosTelefone > $configTelefone['max']) {
                            $errosCheckout[] = 'O número de telemóvel não corresponde ao indicativo selecionado.';
                        } else {
                            $telefoneParaEncomenda = '+' . $configTelefone['codigo'] . $digitosTelefone;
                        }
                    }
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
                    $telefoneParaEncomenda,
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

                    if ($guardarCompraComoLista) {
                        if ($nomeListaCheckout === '') {
                            $nomeListaCheckout = 'Compra ' . date('d/m/Y');
                        }
                        $pdo->prepare('INSERT INTO ListaCompra (IDUtilizador, Nome) VALUES (?, ?)')
                            ->execute([$idUtilizador, $nomeListaCheckout]);
                        $idListaCompraCheckout = (int) $pdo->lastInsertId();
                        $stmtItemLista = $pdo->prepare(
                            'INSERT INTO ItemListaCompra (IDListaCompra, IDProduto, Quantidade)
                             VALUES (?, ?, ?)'
                        );
                        foreach ($itensCheckout as $itemCheckout) {
                            $stmtItemLista->execute([
                                $idListaCompraCheckout,
                                $itemCheckout['IDProduto'],
                                $itemCheckout['Quantidade'],
                            ]);
                        }
                        $listaCompraCheckoutGuardada = $nomeListaCheckout;
                    }

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
                if ($listaCompraCheckoutGuardada !== '') {
                    $_SESSION['lista_compra_flash'] = $listaCompraCheckoutGuardada;
                }
                header('Location: carrinho.php?encomenda=' . rawurlencode($numeroEncomendaCriada));
                exit;
            }
            if ($acao === 'adicionar_lista' && $paginaOrigem !== null) {
                $_SESSION['feedback_carrinho'] = [
                    'tipo' => 'ok',
                    'mensagem' => $listaNovaCriada
                        ? 'Lista criada e produto adicionado com sucesso.'
                        : 'Produto adicionado à lista selecionada.',
                ];
                header('Location: ' . $paginaOrigem);
                exit;
            }
            if ($acao === 'adicionar' && $paginaOrigem !== null) {
                $_SESSION['feedback_carrinho'] = [
                    'tipo' => 'ok',
                    'mensagem' => 'Foi inserido ' . $quantidadeAdicionada . ' '
                        . ($quantidadeAdicionada === 1 ? 'produto' : 'produtos')
                        . ($idListaSelecionada !== null
                            ? ' no seu carrinho e na lista selecionada.'
                            : ' no seu carrinho.'),
                ];
                header('Location: ' . $paginaOrigem);
                exit;
            }
            $mensagensAcao = [
                'adicionar' => 'adicionado',
                'atualizar' => 'atualizado',
                'remover' => 'removido',
                'carregar_lista' => 'lista_carregada',
                'atualizar_item_lista' => 'lista_atualizada',
                'remover_item_lista' => 'lista_atualizada',
                'eliminar_lista' => 'lista_eliminada',
            ];
            $mensagem = $mensagensAcao[$acao] ?? '';
            header('Location: carrinho.php?ok=' . $mensagem);
            exit;
        }

        $pdo->rollBack();
        if (in_array($acao, ['adicionar', 'adicionar_lista'], true) && $paginaOrigem !== null) {
            $_SESSION['feedback_carrinho'] = ['tipo' => 'erro', 'mensagem' => $erro];
            header('Location: ' . $paginaOrigem);
            exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT IDCarrinho FROM Carrinho WHERE IDUtilizador = ? ORDER BY IDCarrinho DESC LIMIT 1');
$stmt->execute([$idUtilizador]);
$idCarrinho = (int) ($stmt->fetchColumn() ?: 0);
$stmt = $pdo->prepare(
    'SELECT l.IDListaCompra, l.Nome, COUNT(il.IDItemListaCompra) AS TotalItens,
            COALESCE(SUM(
                CASE
                    WHEN p.PrecoPromocional > 0 AND p.PrecoPromocional < p.Preco
                    THEN p.PrecoPromocional
                    ELSE p.Preco
                END * il.Quantidade
            ), 0) AS TotalLista
     FROM ListaCompra l
     LEFT JOIN ItemListaCompra il ON il.IDListaCompra = l.IDListaCompra
     LEFT JOIN Produto p ON p.IDProduto = il.IDProduto
     WHERE l.IDUtilizador = ?
     GROUP BY l.IDListaCompra, l.Nome, l.DataAlterado
     ORDER BY l.DataAlterado DESC, l.IDListaCompra DESC'
);
$stmt->execute([$idUtilizador]);
$listasCompra = $stmt->fetchAll();
$itensPorLista = [];
if ($listasCompra) {
    $stmt = $pdo->prepare(
        'SELECT il.IDListaCompra, il.IDItemListaCompra, il.Quantidade,
                p.IDProduto, p.Nome, p.Unidade, p.Ativo, p.StockQuantidade
         FROM ItemListaCompra il
         JOIN ListaCompra l ON l.IDListaCompra = il.IDListaCompra
         JOIN Produto p ON p.IDProduto = il.IDProduto
         WHERE l.IDUtilizador = ?
         ORDER BY l.IDListaCompra, p.Nome'
    );
    $stmt->execute([$idUtilizador]);
    foreach ($stmt->fetchAll() as $itemLista) {
        $itensPorLista[(int) $itemLista['IDListaCompra']][] = $itemLista;
    }
}
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
    'lista_carregada' => 'Produtos da lista adicionados ao carrinho.',
    'lista_atualizada' => 'Lista de compras atualizada.',
    'lista_eliminada' => 'Lista de compras eliminada.',
];
$ok = $mensagens[$_GET['ok'] ?? ''] ?? '';
$listaCompraFlash = $_SESSION['lista_compra_flash'] ?? '';
unset($_SESSION['lista_compra_flash']);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>O meu carrinho | Cantinho</title>
  <link rel="stylesheet" href="css/conta.css?v=checkout-radio-20261029">
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
    <?php if ($listaCompraFlash !== ''): ?>
      <div class="alert alert-ok" role="status">A compra foi guardada na lista «<?= e((string) $listaCompraFlash) ?>».</div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert alert-error" role="alert"><?= e($erro) ?></div>
    <?php endif; ?>
    <?php if ($errosCheckout): ?>
      <div class="alert alert-error" role="alert">
        <ul><?php foreach ($errosCheckout as $erroCheckout): ?><li><?= e($erroCheckout) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <section class="account-card shopping-lists" aria-labelledby="shopping-lists-title">
      <h2 id="shopping-lists-title">As minhas listas predefinidas</h2>
      <p>Guarde compras habituais e volte a adicioná-las ao carrinho quando precisar.</p>
      <?php if ($listasCompra): ?>
        <div class="shopping-list-grid">
          <?php foreach ($listasCompra as $listaCompra): ?>
            <?php $idListaAtual = (int) $listaCompra['IDListaCompra']; ?>
            <article class="shopping-list">
              <div class="shopping-list-heading">
                <h3><?= e((string) $listaCompra['Nome']) ?></h3>
                <span><?= (int) $listaCompra['TotalItens'] ?> <?= (int) $listaCompra['TotalItens'] === 1 ? 'produto' : 'produtos' ?></span>
              </div>
              <p class="shopping-list-total">
                Total dos produtos: <strong><?= number_format((float) $listaCompra['TotalLista'], 2, ',', '.') ?> €</strong>
              </p>
              <?php if (!empty($itensPorLista[$idListaAtual])): ?>
                <ul class="shopping-list-items">
                  <?php foreach ($itensPorLista[$idListaAtual] as $itemLista): ?>
                    <li>
                      <a href="../produto.php?id=<?= (int) $itemLista['IDProduto'] ?>"><?= e((string) $itemLista['Nome']) ?></a>
                      <?php if (!empty($itemLista['Unidade'])): ?><span><?= e((string) $itemLista['Unidade']) ?></span><?php endif; ?>
                      <?php if (!(int) $itemLista['Ativo'] || (int) $itemLista['StockQuantidade'] < 1): ?>
                        <span class="cart-stock-warning">Indisponível</span>
                      <?php endif; ?>
                      <div class="shopping-list-item-actions">
                        <form method="post" action="carrinho.php">
                          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="acao" value="atualizar_item_lista">
                          <input type="hidden" name="id_lista" value="<?= $idListaAtual ?>">
                          <input type="hidden" name="id_item_lista" value="<?= (int) $itemLista['IDItemListaCompra'] ?>">
                          <label class="visually-hidden" for="quantidade-lista-<?= (int) $itemLista['IDItemListaCompra'] ?>">Quantidade de <?= e((string) $itemLista['Nome']) ?></label>
                          <input id="quantidade-lista-<?= (int) $itemLista['IDItemListaCompra'] ?>" name="quantidade"
                                 type="number" min="1" max="99" value="<?= (int) $itemLista['Quantidade'] ?>" required>
                          <button type="submit">Atualizar</button>
                        </form>
                        <form method="post" action="carrinho.php">
                          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="acao" value="remover_item_lista">
                          <input type="hidden" name="id_lista" value="<?= $idListaAtual ?>">
                          <input type="hidden" name="id_item_lista" value="<?= (int) $itemLista['IDItemListaCompra'] ?>">
                          <button class="cart-remove-button" type="submit">Remover</button>
                        </form>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="shopping-list-empty">Ainda sem produtos. Escolha uma lista no formulário ao adicionar um produto ao carrinho.</p>
              <?php endif; ?>
              <div class="shopping-list-actions">
                <form method="post" action="carrinho.php">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="carregar_lista">
                  <input type="hidden" name="id_lista" value="<?= $idListaAtual ?>">
                  <button type="submit" <?= (int) $listaCompra['TotalItens'] === 0 ? 'disabled' : '' ?>>Adicionar ao carrinho</button>
                </form>
                <form method="post" action="carrinho.php">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="eliminar_lista">
                  <input type="hidden" name="id_lista" value="<?= $idListaAtual ?>">
                  <button class="cart-remove-button" type="submit">Eliminar lista</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="shopping-list-empty">Ainda não tem listas. Pode guardar esta compra como lista ao finalizar a encomenda.</p>
      <?php endif; ?>
    </section>

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
            <div class="checkout-save-list">
              <label for="guardar-compra-lista">
                <input id="guardar-compra-lista" type="checkbox" name="guardar_compra_lista" value="1"
                       form="checkout-form" <?= isset($_POST['guardar_compra_lista']) ? 'checked' : '' ?>>
                Guardar esta compra como lista predefinida
              </label>
              <label class="visually-hidden" for="nome-lista-compra">Nome da lista</label>
              <input id="nome-lista-compra" name="nome_lista_compra" type="text" maxlength="80"
                     placeholder="Ex.: Compras semanais" form="checkout-form" value="<?= e($nomeListaCheckout) ?>">
            </div>
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

          <form id="checkout-form" method="post" action="carrinho.php#checkout" class="checkout-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="checkout">

            <div class="checkout-fields">
              <?php if ($moradasGuardadas): ?>
                <fieldset class="checkout-address-picker checkout-field-wide">
                  <legend>Escolha a morada de entrega</legend>
                  <div class="checkout-address-options">
                    <?php foreach ($moradasGuardadas as $moradaGuardada): ?>
                      <label class="checkout-address-option">
                        <input type="radio" name="morada_guardada" value="<?= (int) $moradaGuardada['IDMorada'] ?>"
                               <?= $moradaCheckoutSelecionada === (string) $moradaGuardada['IDMorada'] ? 'checked' : '' ?> required>
                        <span class="checkout-address-option-content">
                          <strong><?= e($moradaGuardada['Designacao']) ?></strong>
                          <?php if ((int) $moradaGuardada['Predefinida'] === 1): ?><span class="checkout-address-badge">Predefinida</span><?php endif; ?>
                          <span><?= e($moradaGuardada['NomeDestinatario']) ?></span>
                          <span><?= e($moradaGuardada['Rua']) ?>, <?= e($moradaGuardada['NumeroPorta']) ?><?= $moradaGuardada['NumeroLote'] ? ', ' . e($moradaGuardada['NumeroLote']) : '' ?></span>
                          <span><?= e($moradaGuardada['CodigoPostal']) ?> <?= e($moradaGuardada['Localidade']) ?></span>
                          <span><?= e($moradaGuardada['ConcelhoRegiao']) ?>, <?= e($moradaGuardada['Distrito']) ?> · <?= e($moradaGuardada['Pais']) ?></span>
                          <?php if ($moradaGuardada['Telemovel']): ?><span>Tel. <?= e($moradaGuardada['Telemovel']) ?></span><?php endif; ?>
                          <span class="checkout-address-select-hint">Escolher esta morada</span>
                        </span>
                      </label>
                    <?php endforeach; ?>
                    <label class="checkout-address-option checkout-address-new">
                      <input type="radio" name="morada_guardada" value="nova"
                             <?= $moradaCheckoutSelecionada === 'nova' ? 'checked' : '' ?> required>
                      <span class="checkout-address-option-content"><strong>Usar outra morada</strong><span>Introduzir os dados de uma nova morada</span></span>
                    </label>
                  </div>
                </fieldset>
              <?php endif; ?>
              <fieldset id="checkout-manual-fields" class="checkout-manual-fields checkout-field-wide"
                        <?= $moradasGuardadas && $moradaCheckoutSelecionada !== 'nova' ? 'disabled hidden' : '' ?>>
                <div>
                <label for="checkout-nome">Nome do destinatário *</label>
                <input id="checkout-nome" name="nome" type="text" maxlength="100" value="<?= e($checkout['nome']) ?>" autocomplete="name" required>
                </div>
              <div>
                <label for="checkout-telemovel">Telemóvel</label>
                <div class="checkout-phone-fields">
                  <select id="checkout-indicativo" name="indicativo" aria-label="Indicativo telefónico">
                    <?php foreach ($indicativosTelefone as $codigoPais => $configTelefone): ?>
                      <option value="<?= e($codigoPais) ?>"
                              data-min="<?= (int) $configTelefone['min'] ?>"
                              data-max="<?= (int) $configTelefone['max'] ?>"
                              data-grupos="<?= e(implode(',', $configTelefone['grupos'])) ?>"
                              data-aliases="<?= e(implode('|', $configTelefone['aliases'])) ?>"
                              <?= $checkout['indicativo'] === $codigoPais ? 'selected' : '' ?>>
                        +<?= e($configTelefone['codigo']) ?> (<?= e($configTelefone['pais']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <input id="checkout-telemovel" name="telemovel" type="tel" inputmode="numeric" maxlength="20"
                         placeholder="912 345 678" value="<?= e($checkout['telemovel']) ?>"
                         autocomplete="tel-national" aria-label="Número de telemóvel sem indicativo">
                  <small class="checkout-phone-hint">Introduza o número sem o indicativo do país.</small>
                </div>
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
                <input id="checkout-cp" name="codigo_postal" type="text" inputmode="numeric" maxlength="8"
                       placeholder="0000-000" data-mask="codigo-postal" value="<?= e($checkout['codigo_postal']) ?>"
                       autocomplete="postal-code" pattern="[0-9]{4}-[0-9]{3}" title="Use o formato 0000-000" required>
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
                <input id="checkout-nif" name="nif" type="text" inputmode="numeric" maxlength="9"
                       data-mask="nif" pattern="[0-9]{9}" title="O NIF deve ter 9 dígitos" value="<?= e($checkout['nif']) ?>">
              </div>
              <div class="checkout-field-wide">
                <label for="checkout-notas">Notas para a entrega</label>
                <textarea id="checkout-notas" name="notas" maxlength="500" rows="3"><?= e($checkout['notas']) ?></textarea>
              </div>
              </fieldset>
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
  <script>
    (() => {
      const countryCode = document.getElementById('checkout-indicativo');
      const phone = document.getElementById('checkout-telemovel');
      const country = document.getElementById('checkout-pais');
      const manualFields = document.getElementById('checkout-manual-fields');
      const addressOptions = document.querySelectorAll('input[name="morada_guardada"]');
      if (!countryCode || !phone || !country) return;

      const normalizeCountry = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
      const formatDigits = (digits, groups) => {
        const parts = [];
        let offset = 0;
        for (const size of groups) {
          if (offset >= digits.length) break;
          parts.push(digits.slice(offset, offset + size));
          offset += size;
        }
        if (offset < digits.length) parts.push(digits.slice(offset));
        return parts.join(' ');
      };

      const formatPhone = () => {
        const selected = countryCode.selectedOptions[0];
        const digits = phone.value.replace(/\D/g, '').slice(0, Number(selected.dataset.max));
        phone.value = formatDigits(digits, selected.dataset.grupos.split(',').map(Number));
      };

      const validatePhone = () => {
        const selected = countryCode.selectedOptions[0];
        const digits = phone.value.replace(/\D/g, '');
        const min = Number(selected.dataset.min);
        const max = Number(selected.dataset.max);
        phone.setCustomValidity(digits && (digits.length < min || digits.length > max)
          ? 'O número não corresponde ao indicativo selecionado.'
          : '');
      };

      const formatPhoneAtCursor = () => {
        const cursor = phone.selectionStart ?? phone.value.length;
        const digitsBeforeCursor = phone.value.slice(0, cursor).replace(/\D/g, '').length;
        const selected = countryCode.selectedOptions[0];
        const digits = phone.value.replace(/\D/g, '').slice(0, Number(selected.dataset.max));
        const formatted = formatDigits(digits, selected.dataset.grupos.split(',').map(Number));
        phone.value = formatted;
        let nextCursor = 0;
        let countedDigits = 0;
        while (nextCursor < formatted.length && countedDigits < digitsBeforeCursor) {
          if (/\d/.test(formatted[nextCursor])) countedDigits++;
          nextCursor++;
        }
        if (formatted[nextCursor] === ' ' && countedDigits > 0) nextCursor++;
        phone.setSelectionRange(nextCursor, nextCursor);
        validatePhone();
      };

      const applyMask = (input, formatter, maxDigits) => {
        input.addEventListener('input', () => {
          const cursor = input.selectionStart ?? input.value.length;
          const digitsBeforeCursor = input.value.slice(0, cursor).replace(/\D/g, '').length;
          const digits = input.value.replace(/\D/g, '').slice(0, maxDigits);
          const formatted = formatter(digits);
          input.value = formatted;
          let nextCursor = 0;
          let countedDigits = 0;
          while (nextCursor < formatted.length && countedDigits < digitsBeforeCursor) {
            if (/\d/.test(formatted[nextCursor])) countedDigits++;
            nextCursor++;
          }
          if (formatted[nextCursor] === '-' && countedDigits === 4) nextCursor++;
          input.setSelectionRange(nextCursor, nextCursor);
        });
        input.value = formatter(input.value.replace(/\D/g, '').slice(0, maxDigits));
      };

      countryCode.addEventListener('change', () => {
        formatPhone();
        validatePhone();
      });
      phone.addEventListener('input', formatPhoneAtCursor);
      country.addEventListener('change', () => {
        const enteredCountry = normalizeCountry(country.value);
        const matchingOption = Array.from(countryCode.options).find((option) =>
          option.dataset.aliases.split('|').some((alias) => normalizeCountry(alias) === enteredCountry)
        );
        if (matchingOption) {
          countryCode.value = matchingOption.value;
          formatPhone();
          validatePhone();
        }
      });

      const updateAddressMode = () => {
        const selectedAddress = document.querySelector('input[name="morada_guardada"]:checked');
        if (manualFields && selectedAddress) {
          manualFields.hidden = selectedAddress.value !== 'nova';
          manualFields.disabled = selectedAddress.value !== 'nova';
        }
      };
      addressOptions.forEach((option) => option.addEventListener('change', updateAddressMode));
      applyMask(document.getElementById('checkout-cp'), (digits) =>
        digits.length > 4 ? digits.slice(0, 4) + '-' + digits.slice(4) : digits, 7);
      applyMask(document.getElementById('checkout-nif'), (digits) => digits, 9);
      updateAddressMode();
      formatPhone();
      validatePhone();
    })();
  </script>
</body>
</html>
