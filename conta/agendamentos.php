<?php
require __DIR__ . '/ligacao.php';
require __DIR__ . '/listas_compras.php';
require __DIR__ . '/agendamento_compras.php';

if (empty($_SESSION['utilizador_id'])) {
    header('Location: conta.php');
    exit;
}

$idUtilizador = (int) $_SESSION['utilizador_id'];
$stmt = $pdo->prepare('SELECT Tipo, Ativo FROM Utilizador WHERE IDUtilizador = ? LIMIT 1');
$stmt->execute([$idUtilizador]);
$utilizador = $stmt->fetch();
if (!$utilizador || !(int) $utilizador['Ativo']) {
    header('Location: logout.php');
    exit;
}
if ($utilizador['Tipo'] === 'funcionario') {
    header('Location: ../admin.php#encomendas');
    exit;
}

agendamentos_compras_preparar($pdo);
$erro = '';
$frequencias = ['semanal', 'mensal'];
$metodosPagamento = ['mbway', 'multibanco', 'cartao', 'paypal', 'pagamento_entrega'];
$dataHoje = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        http_response_code(400);
        $erro = 'Pedido inválido. Atualize a página e tente novamente.';
    } else {
        $acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';
        $idAgendamento = filter_input(INPUT_POST, 'id_agendamento', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($acao === 'criar' || $acao === 'alterar') {
            $idLista = filter_input(INPUT_POST, 'id_lista', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $idMorada = filter_input(INPUT_POST, 'id_morada', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $frequencia = isset($_POST['frequencia']) && is_string($_POST['frequencia']) ? $_POST['frequencia'] : '';
            $dataProxima = isset($_POST['data_proxima']) && is_string($_POST['data_proxima']) ? trim($_POST['data_proxima']) : '';
            $metodoPagamento = isset($_POST['metodo_pagamento']) && is_string($_POST['metodo_pagamento'])
                ? $_POST['metodo_pagamento']
                : '';

            $dataValida = DateTimeImmutable::createFromFormat('!Y-m-d', $dataProxima);
            if (!$idLista || !$idMorada) {
                $erro = 'Selecione uma lista e uma morada válidas.';
            } elseif (!in_array($frequencia, $frequencias, true)) {
                $erro = 'Selecione uma frequência válida.';
            } elseif (!$dataValida || $dataValida->format('Y-m-d') !== $dataProxima || $dataProxima < $dataHoje) {
                $erro = 'Indique uma data igual ou posterior a hoje.';
            } elseif (!in_array($metodoPagamento, $metodosPagamento, true)) {
                $erro = 'Selecione um método de pagamento válido.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT l.IDListaCompra, COUNT(il.IDItemListaCompra) AS TotalItens
                     FROM ListaCompra l
                     LEFT JOIN ItemListaCompra il ON il.IDListaCompra = l.IDListaCompra
                     WHERE l.IDListaCompra = ? AND l.IDUtilizador = ?
                     GROUP BY l.IDListaCompra'
                );
                $stmt->execute([$idLista, $idUtilizador]);
                $lista = $stmt->fetch();
                $stmt = $pdo->prepare(
                    'SELECT IDMorada, NomeDestinatario, Telemovel, Pais, Distrito, ConcelhoRegiao,
                            Rua, NumeroPorta, NumeroLote, CodigoPostal, Localidade
                     FROM Morada
                     WHERE IDMorada = ? AND IDUtilizador = ?'
                );
                $stmt->execute([$idMorada, $idUtilizador]);
                $morada = $stmt->fetch();

                if (!$lista || (int) $lista['TotalItens'] < 1) {
                    $erro = 'Escolha uma lista que tenha pelo menos um artigo.';
                } elseif (!$morada) {
                    $erro = 'Selecione uma morada guardada válida.';
                } elseif ($acao === 'criar') {
                    $pdo->prepare(
                        'INSERT INTO AgendamentoCompra
                            (IDUtilizador, IDListaCompra, IDMorada, Frequencia, DataProxima, DiaDoMes,
                             NomeDestinatario, Telemovel, Morada, CodigoPostal, Localidade,
                             ConcelhoRegiao, Distrito, Pais, NifFatura, MetodoPagamento)
                         SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NIF, ?
                         FROM Utilizador
                         WHERE IDUtilizador = ?'
                    )->execute([
                        $idUtilizador,
                        $idLista,
                        $idMorada,
                        $frequencia,
                        $dataProxima,
                        (int) $dataValida->format('j'),
                        $morada['NomeDestinatario'],
                        $morada['Telemovel'],
                        trim($morada['Rua'] . ', ' . $morada['NumeroPorta']
                            . (!empty($morada['NumeroLote']) ? ', ' . $morada['NumeroLote'] : '')),
                        $morada['CodigoPostal'],
                        $morada['Localidade'],
                        $morada['ConcelhoRegiao'],
                        $morada['Distrito'],
                        $morada['Pais'],
                        $metodoPagamento,
                        $idUtilizador,
                    ]);
                    header('Location: agendamentos.php?ok=criado');
                    exit;
                } elseif ($idAgendamento) {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare(
                            'SELECT Estado, ComprasDesdeConfirmacao
                             FROM AgendamentoCompra
                             WHERE IDAgendamento = ? AND IDUtilizador = ?
                             FOR UPDATE'
                        );
                        $stmt->execute([$idAgendamento, $idUtilizador]);
                        $agendamento = $stmt->fetch();
                        if (!$agendamento || $agendamento['Estado'] === 'cancelado') {
                            $pdo->rollBack();
                            $erro = 'Este agendamento já não está ativo.';
                        } else {
                            $comprasDesdeConfirmacao = $agendamento['Estado'] === 'aguardar_confirmacao'
                                ? 0
                                : (int) $agendamento['ComprasDesdeConfirmacao'];
                            $estadoAgendamento = in_array(
                                $agendamento['Estado'],
                                ['aguardar_confirmacao', 'aguardar_aprovacao'],
                                true
                            ) ? 'ativo' : $agendamento['Estado'];
                            $pdo->prepare(
                                'UPDATE AgendamentoCompra
                                 SET IDListaCompra = ?, IDMorada = ?, Frequencia = ?, DataProxima = ?, DiaDoMes = ?,
                                     NomeDestinatario = ?, Telemovel = ?, Morada = ?, CodigoPostal = ?,
                                     Localidade = ?, ConcelhoRegiao = ?, Distrito = ?, Pais = ?,
                                     NifFatura = (SELECT NIF FROM Utilizador WHERE IDUtilizador = ?),
                                     MetodoPagamento = ?, Estado = ?, ComprasDesdeConfirmacao = ?,
                                     UltimoResultado = \'Agendamento atualizado.\'
                                 WHERE IDAgendamento = ? AND IDUtilizador = ?'
                            )->execute([
                                $idLista,
                                $idMorada,
                                $frequencia,
                                $dataProxima,
                                (int) $dataValida->format('j'),
                                $morada['NomeDestinatario'],
                                $morada['Telemovel'],
                                trim($morada['Rua'] . ', ' . $morada['NumeroPorta']
                                    . (!empty($morada['NumeroLote']) ? ', ' . $morada['NumeroLote'] : '')),
                                $morada['CodigoPostal'],
                                $morada['Localidade'],
                                $morada['ConcelhoRegiao'],
                                $morada['Distrito'],
                                $morada['Pais'],
                                $idUtilizador,
                                $metodoPagamento,
                                $estadoAgendamento,
                                $comprasDesdeConfirmacao,
                                $idAgendamento,
                                $idUtilizador,
                            ]);
                            $pdo->commit();
                            header('Location: agendamentos.php?ok=alterado');
                            exit;
                        }
                    } catch (Throwable $erroTransacao) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $erroTransacao;
                    }
                } else {
                    $erro = 'Agendamento inválido.';
                }
            }
        } elseif ($acao === 'aprovar' && $idAgendamento) {
            $hashSubmetido = isset($_POST['hash_conteudo']) && is_string($_POST['hash_conteudo'])
                ? $_POST['hash_conteudo']
                : '';
            if (($_POST['confirmar_conteudo'] ?? '') !== '1' || !preg_match('/^[a-f0-9]{64}$/', $hashSubmetido)) {
                $erro = 'Confirme os artigos e os valores apresentados antes de criar a encomenda.';
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare(
                        'SELECT *
                         FROM AgendamentoCompra
                         WHERE IDAgendamento = ? AND IDUtilizador = ? AND Estado = \'aguardar_aprovacao\'
                         FOR UPDATE'
                    );
                    $stmt->execute([$idAgendamento, $idUtilizador]);
                    $agendamento = $stmt->fetch();
                    if (!$agendamento) {
                        $pdo->rollBack();
                        $erro = 'Esta ocorrência já não está à espera de aprovação.';
                    } else {
                        $previsao = agendamento_previsualizar(
                            $pdo,
                            (int) $agendamento['IDListaCompra'],
                            true
                        );
                        if (!$previsao['itens'] || !$previsao['disponivel']) {
                            $pdo->rollBack();
                            $erro = 'A lista está vazia ou já não tem stock suficiente. Atualize os artigos e reveja a lista.';
                        } elseif (!hash_equals($previsao['hash'], $hashSubmetido)) {
                            $pdo->rollBack();
                            $erro = 'O conteúdo, preço ou disponibilidade mudou. Reveja os artigos e confirme novamente.';
                        } else {
                            $dataAgendada = (string) $agendamento['DataProxima'];
                            $dataEntrega = $dataAgendada < $dataHoje ? $dataHoje : $dataAgendada;
                            $numeroEncomenda = agendamento_criar_encomenda(
                                $pdo,
                                $agendamento,
                                $previsao,
                                $dataEntrega
                            );
                            $compras = (int) $agendamento['ComprasDesdeConfirmacao'] + 1;
                            $estado = $compras >= 5 ? 'aguardar_confirmacao' : 'ativo';
                            $proximaData = agendamento_data_futura(
                                agendamento_proxima_data(
                                    $dataAgendada,
                                    $agendamento['Frequencia'],
                                    (int) $agendamento['DiaDoMes']
                                ),
                                $agendamento['Frequencia'],
                                (int) $agendamento['DiaDoMes'],
                                $dataHoje
                            );
                            $mensagemEncomenda = 'Encomenda ' . $numeroEncomenda
                                . ' criada após confirmação, com entrega prevista para '
                                . date('d/m/Y', strtotime($dataEntrega))
                                . '. Pagamento pendente; não foi efetuada nenhuma cobrança.';
                            if ($estado === 'aguardar_confirmacao') {
                                $mensagemEncomenda .= ' Confirme se pretende continuar, alterar ou cancelar o agendamento.';
                            }
                            $pdo->prepare(
                                'UPDATE AgendamentoCompra
                                 SET Estado = ?, ComprasDesdeConfirmacao = ?, DataProxima = ?,
                                     NumeroUltimaEncomenda = ?, UltimoResultado = ?, DataUltimaExecucao = NOW()
                                 WHERE IDAgendamento = ?'
                            )->execute([
                                $estado,
                                min(5, $compras),
                                $proximaData,
                                $numeroEncomenda,
                                $mensagemEncomenda,
                                $idAgendamento,
                            ]);
                            $pdo->commit();
                            header('Location: agendamentos.php?ok=aprovado');
                            exit;
                        }
                    }
                } catch (Throwable $erroTransacao) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $erroTransacao;
                }
            }
        } elseif ($acao === 'ignorar' && $idAgendamento) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'SELECT Frequencia, DataProxima, DiaDoMes
                     FROM AgendamentoCompra
                     WHERE IDAgendamento = ? AND IDUtilizador = ? AND Estado = \'aguardar_aprovacao\'
                     FOR UPDATE'
                );
                $stmt->execute([$idAgendamento, $idUtilizador]);
                $agendamento = $stmt->fetch();
                if (!$agendamento) {
                    $pdo->rollBack();
                    $erro = 'Esta ocorrência já não está à espera de aprovação.';
                } else {
                    $proximaData = agendamento_data_futura(
                        agendamento_proxima_data(
                            $agendamento['DataProxima'],
                            $agendamento['Frequencia'],
                            (int) $agendamento['DiaDoMes']
                        ),
                        $agendamento['Frequencia'],
                        (int) $agendamento['DiaDoMes'],
                        $dataHoje
                    );
                    $pdo->prepare(
                        'UPDATE AgendamentoCompra
                         SET Estado = \'ativo\', DataProxima = ?,
                             UltimoResultado = \'Cliente optou por não comprar nesta ocorrência.\',
                             DataUltimaExecucao = NOW()
                         WHERE IDAgendamento = ?'
                    )->execute([$proximaData, $idAgendamento]);
                    $pdo->commit();
                    header('Location: agendamentos.php?ok=ignorada');
                    exit;
                }
            } catch (Throwable $erroTransacao) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $erroTransacao;
            }
        } elseif (in_array($acao, ['continuar', 'cancelar'], true) && $idAgendamento) {
            $stmt = $pdo->prepare(
                'SELECT Estado, Frequencia, DataProxima, DiaDoMes
                 FROM AgendamentoCompra
                 WHERE IDAgendamento = ? AND IDUtilizador = ?'
            );
            $stmt->execute([$idAgendamento, $idUtilizador]);
            $agendamento = $stmt->fetch();
            if (!$agendamento) {
                $erro = 'Este agendamento já não existe.';
            } elseif ($acao === 'cancelar') {
                if ($agendamento['Estado'] !== 'cancelado') {
                    $pdo->prepare(
                        'UPDATE AgendamentoCompra SET Estado = \'cancelado\' WHERE IDAgendamento = ? AND IDUtilizador = ?'
                    )->execute([$idAgendamento, $idUtilizador]);
                }
                header('Location: agendamentos.php?ok=cancelado');
                exit;
            } elseif ($agendamento['Estado'] !== 'aguardar_confirmacao') {
                $erro = 'Este agendamento não precisa de confirmação neste momento.';
            } else {
                $dataReiniciada = agendamento_data_futura(
                    $agendamento['DataProxima'],
                    $agendamento['Frequencia'],
                    (int) $agendamento['DiaDoMes'],
                    $dataHoje
                );
                $stmt = $pdo->prepare(
                    'UPDATE AgendamentoCompra
                     SET Estado = \'ativo\', ComprasDesdeConfirmacao = 0, DataProxima = ?,
                         UltimoResultado = \'Agendamento confirmado para mais 5 compras.\'
                     WHERE IDAgendamento = ? AND IDUtilizador = ? AND Estado = \'aguardar_confirmacao\''
                );
                $stmt->execute([$dataReiniciada, $idAgendamento, $idUtilizador]);
                if ($stmt->rowCount() !== 1) {
                    $erro = 'O estado do agendamento foi alterado. Atualize a página.';
                } else {
                    header('Location: agendamentos.php?ok=continuar');
                    exit;
                }
            }
        } else {
            $erro = 'Ação inválida.';
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT IDListaCompra, Nome
     FROM ListaCompra
     WHERE IDUtilizador = ?
       AND EXISTS (SELECT 1 FROM ItemListaCompra il WHERE il.IDListaCompra = ListaCompra.IDListaCompra)
     ORDER BY DataAlterado DESC, IDListaCompra DESC'
);
$stmt->execute([$idUtilizador]);
$listas = $stmt->fetchAll();
$previsoesListas = [];
foreach ($listas as $lista) {
    $idLista = (int) $lista['IDListaCompra'];
    $previsoesListas[$idLista] = agendamento_previsualizar($pdo, $idLista);
}
$stmt = $pdo->prepare(
    'SELECT IDMorada, Designacao, NomeDestinatario, Rua, NumeroPorta, NumeroLote,
            CodigoPostal, Localidade, ConcelhoRegiao, Distrito, Pais
     FROM Morada
     WHERE IDUtilizador = ?
     ORDER BY Predefinida DESC, IDMorada DESC'
);
$stmt->execute([$idUtilizador]);
$moradas = $stmt->fetchAll();
$stmt = $pdo->prepare(
    'SELECT a.*, l.Nome AS NomeLista
     FROM AgendamentoCompra a
     JOIN ListaCompra l ON l.IDListaCompra = a.IDListaCompra
     WHERE a.IDUtilizador = ?
     ORDER BY FIELD(a.Estado, \'aguardar_aprovacao\', \'aguardar_confirmacao\', \'ativo\', \'cancelado\'),
              a.DataProxima, a.IDAgendamento DESC'
);
$stmt->execute([$idUtilizador]);
$agendamentos = $stmt->fetchAll();
foreach ($agendamentos as &$agendamento) {
    if ($agendamento['Estado'] === 'aguardar_aprovacao') {
        $idLista = (int) $agendamento['IDListaCompra'];
        $agendamento['Previsao'] = $previsoesListas[$idLista]
            ?? agendamento_previsualizar($pdo, $idLista);
    }
}
unset($agendamento);

$mensagens = [
    'criado' => 'Agendamento criado. A primeira encomenda será gerada na data indicada.',
    'alterado' => 'Agendamento atualizado.',
    'continuar' => 'Agendamento confirmado para mais 5 compras.',
    'aprovado' => 'Encomenda criada. O pagamento continua pendente de confirmação.',
    'ignorada' => 'Esta ocorrência foi ignorada; o agendamento continua na próxima data.',
    'cancelado' => 'Agendamento cancelado.',
];
$ok = isset($_GET['ok']) && is_string($_GET['ok']) ? ($mensagens[$_GET['ok']] ?? '') : '';
$nomesFrequencia = ['semanal' => 'Semanal', 'mensal' => 'Mensal'];
$nomesEstado = [
    'ativo' => 'Ativo',
    'aguardar_aprovacao' => 'À espera da confirmação dos artigos',
    'aguardar_confirmacao' => 'À espera da sua confirmação',
    'cancelado' => 'Cancelado',
];
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Compras agendadas | Cantinho</title>
  <link rel="stylesheet" href="../css/agendamentos/agendamentos.css">
</head>
<body>
  <header class="account-header">
    <a class="brand" href="../index.php" aria-label="Cantinho - página inicial">
      <span class="brand-mark" aria-hidden="true">
        <svg class="svg" viewBox="0 0 24 24"><path d="M20 4C12 4 5 8 5 15c0 2 1 4 3 5 1-5 4-8 7-11-4 4-6 7-7 11 6 0 10-4 10-16Z"/></svg>
      </span>
      <span>Cantinho</span>
    </a>
    <a class="back-link" href="perfil.php">Voltar à minha conta</a>
  </header>
  <main class="account-main schedule-main">
    <div class="intro">
      <p class="eyebrow">A sua conta Cantinho</p>
      <h1>Compras agendadas</h1>
      <p>Escolha uma lista e programe encomendas semanais ou mensais.</p>
    </div>

    <?php if ($ok): ?><div class="alert alert-ok" role="status"><?= e($ok) ?></div><?php endif; ?>
    <?php if ($erro !== ''): ?><div class="alert alert-error" role="alert"><?= e($erro) ?></div><?php endif; ?>

    <?php if ($listas && $moradas): ?>
      <section class="account-card schedule-create">
        <h2>Criar agendamento</h2>
        <form method="post" action="agendamentos.php" class="schedule-form">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="acao" value="criar">
          <label>
            Lista de compras
            <select name="id_lista" required>
              <option value="">Escolha uma lista</option>
              <?php foreach ($listas as $lista): ?>
                <option value="<?= (int) $lista['IDListaCompra'] ?>"><?= e($lista['Nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <a class="schedule-content-link" href="#conteudos-listas">Consultar o conteúdo e os preços das listas</a>
          <label>
            Frequência
            <select name="frequencia" required>
              <option value="semanal">Semanal</option>
              <option value="mensal">Mensal</option>
            </select>
          </label>
          <label>
            Primeira data de entrega
            <input type="date" name="data_proxima" min="<?= e($dataHoje) ?>" required>
          </label>
          <label>
            Morada de entrega
            <select name="id_morada" required>
              <option value="">Escolha uma morada</option>
              <?php foreach ($moradas as $morada): ?>
                <option value="<?= (int) $morada['IDMorada'] ?>">
                  <?= e($morada['Designacao'] . ' — ' . $morada['Rua'] . ', ' . $morada['NumeroPorta']
                    . ' · ' . $morada['CodigoPostal'] . ' ' . $morada['Localidade']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Método de pagamento
            <select name="metodo_pagamento" required>
              <option value="pagamento_entrega">Pagamento na entrega</option>
              <option value="mbway">MB Way</option>
              <option value="multibanco">Multibanco</option>
              <option value="cartao">Cartão</option>
              <option value="paypal">PayPal</option>
            </select>
          </label>
          <button type="submit" class="btn-small btn-primary">Criar agendamento</button>
        </form>
      </section>
    <?php elseif (!$moradas): ?>
      <section class="account-card"><p>Guarde primeiro uma morada na sua conta para poder agendar entregas.</p><a class="btn-small btn-secondary" href="perfil.php#moradas">Adicionar morada</a></section>
    <?php else: ?>
      <section class="account-card"><p>Crie primeiro uma lista de compras com artigos.</p><a class="btn-small btn-secondary" href="carrinho.php">Ver listas de compras</a></section>
    <?php endif; ?>

    <section class="account-card schedule-list-contents" id="conteudos-listas">
      <h2>Conteúdo das listas</h2>
      <p>Consulte os produtos, quantidades, preços atuais e disponibilidade antes de agendar uma compra.</p>
      <?php if (!$listas): ?>
        <p>Ainda não tem listas com artigos.</p>
      <?php else: ?>
        <div class="schedule-grid">
          <?php foreach ($listas as $lista): ?>
            <?php
              $idLista = (int) $lista['IDListaCompra'];
              $previsaoLista = $previsoesListas[$idLista];
            ?>
            <details class="schedule-list-detail" id="lista-conteudo-<?= $idLista ?>">
              <summary>
                <strong><?= e($lista['Nome']) ?></strong>
                <span><?= count($previsaoLista['itens']) ?> <?= count($previsaoLista['itens']) === 1 ? 'produto' : 'produtos' ?></span>
              </summary>
              <ul class="schedule-preview-items">
                <?php foreach ($previsaoLista['itens'] as $item): ?>
                  <li>
                    <span>
                      <strong><?= e($item['Nome']) ?></strong>
                      <small>
                        <?= (int) $item['Quantidade'] ?>
                        <?= $item['Unidade'] !== '' ? ' ' . e($item['Unidade']) : '' ?>
                        × <?= number_format((float) $item['PrecoAtual'], 2, ',', '.') ?> €
                      </small>
                    </span>
                    <span class="schedule-preview-price">
                      <?= number_format((float) $item['TotalLinha'], 2, ',', '.') ?> €
                      <small class="<?= $item['Disponivel'] ? '' : 'schedule-unavailable' ?>">
                        <?= $item['Disponivel'] ? 'Disponível' : 'Indisponível ou sem stock suficiente' ?>
                      </small>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
              <div class="schedule-preview-total">
                <span>Subtotal atual</span><strong><?= number_format((float) $previsaoLista['subtotal'], 2, ',', '.') ?> €</strong>
                <span>Entrega</span><strong><?= $previsaoLista['custo_envio'] > 0 ? number_format((float) $previsaoLista['custo_envio'], 2, ',', '.') . ' €' : 'Grátis' ?></strong>
                <span>Total atual</span><strong><?= number_format((float) $previsaoLista['total'], 2, ',', '.') ?> €</strong>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <a class="btn-small btn-secondary" href="carrinho.php#shopping-lists-title">Editar listas</a>
    </section>

    <section class="account-card schedule-list">
      <h2>Os seus agendamentos</h2>
      <?php if (!$agendamentos): ?>
        <p>Ainda não tem compras agendadas.</p>
      <?php else: ?>
        <div class="schedule-grid">
          <?php foreach ($agendamentos as $agendamento): ?>
            <?php $idAtual = (int) $agendamento['IDAgendamento']; ?>
            <article class="schedule-card">
              <div class="schedule-heading">
                <div>
                  <h3><?= e($agendamento['NomeLista']) ?></h3>
                  <span class="schedule-status schedule-status-<?= e($agendamento['Estado']) ?>">
                    <?= e($nomesEstado[$agendamento['Estado']] ?? $agendamento['Estado']) ?>
                  </span>
                </div>
                <?php if ($agendamento['Estado'] !== 'cancelado'): ?>
                  <strong><?= e($nomesFrequencia[$agendamento['Frequencia']]) ?></strong>
                <?php endif; ?>
              </div>
              <?php if ($agendamento['Estado'] !== 'cancelado'): ?>
                <p>Próxima encomenda: <strong><?= e(date('d/m/Y', strtotime($agendamento['DataProxima']))) ?></strong></p>
                <p>Entrega para <?= e($agendamento['NomeDestinatario']) ?>, <?= e($agendamento['Morada']) ?>, <?= e($agendamento['CodigoPostal']) ?> <?= e($agendamento['Localidade']) ?>.</p>
                <?php if (isset($previsoesListas[(int) $agendamento['IDListaCompra']])): ?>
                  <a class="schedule-content-link" href="#lista-conteudo-<?= (int) $agendamento['IDListaCompra'] ?>">Ver conteúdo atual da lista</a>
                <?php endif; ?>
              <?php endif; ?>
              <?php if (!empty($agendamento['UltimoResultado'])): ?>
                <p class="<?= in_array($agendamento['Estado'], ['aguardar_aprovacao', 'aguardar_confirmacao'], true) ? 'schedule-notice' : 'schedule-result' ?>" role="status">
                  <?= e($agendamento['UltimoResultado']) ?>
                </p>
              <?php endif; ?>
              <?php if ($agendamento['Estado'] === 'aguardar_aprovacao'): ?>
                <?php $previsao = $agendamento['Previsao']; ?>
                <section class="schedule-preview" aria-label="Conteúdo da encomenda a confirmar">
                  <h4>Revise os produtos e preços desta ocorrência</h4>
                  <?php if ($previsao['itens']): ?>
                    <ul class="schedule-preview-items">
                      <?php foreach ($previsao['itens'] as $item): ?>
                        <li>
                          <span>
                            <strong><?= e($item['Nome']) ?></strong>
                            <small>
                              <?= (int) $item['Quantidade'] ?>
                              <?= $item['Unidade'] !== '' ? ' ' . e($item['Unidade']) : '' ?>
                              × <?= number_format((float) $item['PrecoAtual'], 2, ',', '.') ?> €
                            </small>
                          </span>
                          <span class="schedule-preview-price">
                            <?= number_format((float) $item['TotalLinha'], 2, ',', '.') ?> €
                            <?php if (!$item['Disponivel']): ?><small class="schedule-unavailable">Sem stock suficiente</small><?php endif; ?>
                          </span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                    <div class="schedule-preview-total">
                      <span>Subtotal</span><strong><?= number_format((float) $previsao['subtotal'], 2, ',', '.') ?> €</strong>
                      <span>Entrega</span><strong><?= $previsao['custo_envio'] > 0 ? number_format((float) $previsao['custo_envio'], 2, ',', '.') . ' €' : 'Grátis' ?></strong>
                      <span>Total</span><strong><?= number_format((float) $previsao['total'], 2, ',', '.') ?> €</strong>
                    </div>
                  <?php else: ?>
                    <p class="schedule-unavailable">Esta lista não tem artigos. Atualize-a antes de confirmar.</p>
                  <?php endif; ?>
                  <?php if (!$previsao['disponivel']): ?>
                    <p class="schedule-unavailable">Não é possível criar esta encomenda enquanto houver produtos indisponíveis ou sem stock suficiente.</p>
                    <a href="carrinho.php#shopping-lists-title">Atualizar a lista de compras</a>
                  <?php endif; ?>
                  <form method="post" action="agendamentos.php" class="schedule-confirm-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="aprovar">
                    <input type="hidden" name="id_agendamento" value="<?= $idAtual ?>">
                    <input type="hidden" name="hash_conteudo" value="<?= e($previsao['hash']) ?>">
                    <label class="schedule-confirm-checkbox">
                      <input type="checkbox" name="confirmar_conteudo" value="1" required <?= !$previsao['disponivel'] ? 'disabled' : '' ?>>
                      <span>Confirmo os produtos, quantidades e preços apresentados.</span>
                    </label>
                    <button class="btn-small btn-primary" type="submit" <?= !$previsao['disponivel'] ? 'disabled' : '' ?>>
                      Confirmar conteúdo e encomenda
                    </button>
                  </form>
                  <form method="post" action="agendamentos.php" class="schedule-skip-form" onsubmit="return confirm('Não comprar esta lista nesta ocorrência?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="ignorar">
                    <input type="hidden" name="id_agendamento" value="<?= $idAtual ?>">
                    <button class="cart-remove-button" type="submit">Não comprar desta vez</button>
                  </form>
                </section>
              <?php endif; ?>
              <?php if ($agendamento['Estado'] === 'aguardar_confirmacao'): ?>
                <div class="schedule-actions">
                  <form method="post" action="agendamentos.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="continuar">
                    <input type="hidden" name="id_agendamento" value="<?= $idAtual ?>">
                    <button class="btn-small btn-primary" type="submit">Continuar por mais 5 compras</button>
                  </form>
                </div>
              <?php endif; ?>
              <?php if (in_array($agendamento['Estado'], ['ativo', 'aguardar_aprovacao', 'aguardar_confirmacao'], true)): ?>
                <details class="schedule-edit">
                  <summary>
                    <?= $agendamento['Estado'] === 'aguardar_confirmacao'
                        ? 'Alterar em vez de continuar'
                        : ($agendamento['Estado'] === 'aguardar_aprovacao'
                            ? 'Alterar agendamento antes de aprovar'
                            : 'Alterar agendamento') ?>
                  </summary>
                  <form method="post" action="agendamentos.php" class="schedule-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="alterar">
                    <input type="hidden" name="id_agendamento" value="<?= $idAtual ?>">
                    <label>
                      Lista
                      <select name="id_lista" required>
                        <?php foreach ($listas as $lista): ?>
                          <option value="<?= (int) $lista['IDListaCompra'] ?>" <?= (int) $lista['IDListaCompra'] === (int) $agendamento['IDListaCompra'] ? 'selected' : '' ?>><?= e($lista['Nome']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>
                      Frequência
                      <select name="frequencia" required>
                        <?php foreach ($nomesFrequencia as $valor => $rotulo): ?>
                          <option value="<?= e($valor) ?>" <?= $agendamento['Frequencia'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>
                      Próxima data
                      <input type="date" name="data_proxima" min="<?= e($dataHoje) ?>" value="<?= e($agendamento['DataProxima']) ?>" required>
                    </label>
                    <label>
                      Morada
                      <select name="id_morada" required>
                        <?php foreach ($moradas as $morada): ?>
                          <option value="<?= (int) $morada['IDMorada'] ?>"
                            <?= (int) ($agendamento['IDMorada'] ?? 0) === (int) $morada['IDMorada'] ? 'selected' : '' ?>>
                            <?= e($morada['Designacao'] . ' — ' . $morada['Rua'] . ', ' . $morada['NumeroPorta']
                              . ' · ' . $morada['CodigoPostal'] . ' ' . $morada['Localidade']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>
                      Método de pagamento
                      <select name="metodo_pagamento" required>
                        <?php foreach ([
                            'pagamento_entrega' => 'Pagamento na entrega',
                            'mbway' => 'MB Way',
                            'multibanco' => 'Multibanco',
                            'cartao' => 'Cartão',
                            'paypal' => 'PayPal',
                        ] as $valor => $rotulo): ?>
                          <option value="<?= e($valor) ?>" <?= $agendamento['MetodoPagamento'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <button class="btn-small btn-secondary" type="submit">Guardar alterações</button>
                  </form>
                </details>
                <form method="post" action="agendamentos.php" class="schedule-cancel-form" onsubmit="return confirm('Pretende cancelar este agendamento?');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acao" value="cancelar">
                  <input type="hidden" name="id_agendamento" value="<?= $idAtual ?>">
                  <button class="cart-remove-button" type="submit">Cancelar agendamento</button>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
