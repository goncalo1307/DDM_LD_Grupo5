<?php
require __DIR__ . '/ligacao.php';
require __DIR__ . '/gastos.php';
$indicativosTelefone = require __DIR__ . '/indicativos.php';

// Só para quem tem sessão iniciada
if (empty($_SESSION['utilizador_id'])) {
    header('Location: conta.php');
    exit;
}

$id = (int) $_SESSION['utilizador_id'];

// Carrega o utilizador (se a conta foi desativada/apagada, termina sessão)
$stmt = $pdo->prepare('SELECT * FROM Utilizador WHERE IDUtilizador = ? AND Ativo = 1');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) {
    header('Location: logout.php');
    exit;
}

gastos_compras_preparar($pdo);
$periodoGastos = isset($_GET['periodo']) && $_GET['periodo'] === 'semanal' ? 'semanal' : 'mensal';
$resumoGastos = gastos_compras_resumo($pdo, $id, $periodoGastos);

function campo_csrf(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

$erros = ['dados' => [], 'password' => [], 'morada' => []];

// Valores dos formulários (por defeito, os da BD)
$v = [
    'nome'           => $u['Nome'],
    'username'       => $u['Username'],
    'email'          => $u['Email'],
    'telemovel'      => $u['Telemovel'] ?? '',
    'nif'            => $u['NIF'] ?? '',
    'dataNascimento' => $u['DataNascimento'] ?? '',
];

$m = [
    'designacao' => '', 'destinatario' => $u['Nome'], 'telemovel' => $u['Telemovel'] ?? '',
    'pais' => 'Portugal', 'distrito' => '', 'concelho' => '', 'rua' => '',
    'numero_porta' => '', 'numero_lote' => '', 'codigo_postal' => '', 'localidade' => '',
    'predefinida' => false,
];
$indicativoMorada = 'pt';

$mensagens = [
    'dados'           => 'Dados atualizados com sucesso.',
    'password'        => 'Password alterada com sucesso.',
    'morada'          => 'Morada adicionada com sucesso.',
    'morada_default'  => 'Morada predefinida atualizada.',
    'morada_removida' => 'Morada removida.',
];
$ok = $mensagens[$_GET['ok'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if (!csrf_valido()) {
        $erros['dados'][] = 'Pedido inválido. Atualize a página e tente novamente.';
        $acao = '';
    }

    /* ---------- Editar dados pessoais ---------- */
    if ($acao === 'dados') {
        $v['nome']           = trim($_POST['nome'] ?? '');
        $v['username']       = trim($_POST['username'] ?? '');
        $v['email']          = mb_strtolower(trim($_POST['email'] ?? ''));
        $v['telemovel']      = trim($_POST['telemovel'] ?? '');
        $v['nif']            = trim($_POST['nif'] ?? '');
        $v['dataNascimento'] = trim($_POST['dataNascimento'] ?? '');
        $e = &$erros['dados'];

        if ($v['nome'] === '' || mb_strlen($v['nome']) > 100) $e[] = 'Indique um nome válido (máx. 100 caracteres).';
        if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $v['username'])) $e[] = 'O username deve ter 3 a 50 caracteres (letras, números, _ e .).';
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 255) $e[] = 'Indique um email válido.';
        if ($v['telemovel'] !== '' && !preg_match('/^\+?[0-9 ]{9,20}$/', $v['telemovel'])) $e[] = 'Telemóvel inválido.';
        if ($v['nif'] !== '' && !preg_match('/^\d{9}$/', $v['nif'])) $e[] = 'O NIF deve ter 9 dígitos.';
        if ($v['dataNascimento'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $v['dataNascimento']);
            if (!$d || $d->format('Y-m-d') !== $v['dataNascimento'] || $d > new DateTime('today')) $e[] = 'Data de nascimento inválida.';
        }

        // Username / email já usados por OUTRO utilizador?
        if (!$e) {
            $stmt = $pdo->prepare('SELECT Username, Email FROM Utilizador WHERE (Username = ? OR Email = ?) AND IDUtilizador <> ?');
            $stmt->execute([$v['username'], $v['email'], $id]);
            foreach ($stmt->fetchAll() as $linha) {
                if (strcasecmp($linha['Username'], $v['username']) === 0) $e[] = 'Esse username já está em uso.';
                if (strcasecmp($linha['Email'], $v['email']) === 0)       $e[] = 'Esse email já tem conta.';
            }
            $e = array_values(array_unique($e));
        }

        if (!$e) {
            // Se mudou o email, deixa de estar verificado
            $emailVerificado = strcasecmp($v['email'], $u['Email']) === 0 ? (int) $u['EmailVerificado'] : 0;

            try {
                $pdo->prepare(
                    'UPDATE Utilizador
                     SET Nome = ?, Username = ?, Email = ?, Telemovel = ?, NIF = ?, DataNascimento = ?,
                         EmailVerificado = ?, DataAlterado = NOW()
                     WHERE IDUtilizador = ?'
                )->execute([
                    $v['nome'], $v['username'], $v['email'],
                    $v['telemovel']      !== '' ? $v['telemovel']      : null,
                    $v['nif']            !== '' ? $v['nif']            : null,
                    $v['dataNascimento'] !== '' ? $v['dataNascimento'] : null,
                    $emailVerificado, $id,
                ]);

                $_SESSION['utilizador_nome']     = $v['nome'];
                $_SESSION['utilizador_username'] = $v['username'];

                header('Location: perfil.php?ok=dados');
                exit;
            } catch (PDOException $ex) {
                $e[] = $ex->getCode() === '23000' ? 'Username ou email já registado.' : 'Não foi possível guardar. Tente novamente.';
            }
        }
        unset($e);
    }

    /* ---------- Alterar password ---------- */
    if ($acao === 'password') {
        $atual    = $_POST['password_atual'] ?? '';
        $nova     = $_POST['password_nova'] ?? '';
        $confirma = $_POST['password_confirma'] ?? '';
        $e = &$erros['password'];

        if (!password_verify($atual, $u['PasswordHash'])) $e[] = 'A password atual está incorreta.';
        if (strlen($nova) < 8)       $e[] = 'A nova password deve ter pelo menos 8 caracteres.';
        elseif (strlen($nova) > 72)  $e[] = 'A nova password não pode ter mais de 72 caracteres.';
        if ($nova !== $confirma)     $e[] = 'As passwords novas não coincidem.';
        if (!$e && $nova === $atual) $e[] = 'A nova password tem de ser diferente da atual.';

        if (!$e) {
            $pdo->prepare('UPDATE Utilizador SET PasswordHash = ?, DataAlterado = NOW() WHERE IDUtilizador = ?')
                ->execute([password_hash($nova, PASSWORD_DEFAULT), $id]);
            session_regenerate_id(true);
            header('Location: perfil.php?ok=password');
            exit;
        }
        unset($e);
    }

    /* ---------- Adicionar morada ---------- */
    if ($acao === 'morada_add') {
        $m = [
            'designacao'    => trim($_POST['designacao'] ?? ''),
            'destinatario'  => trim($_POST['destinatario'] ?? ''),
            'telemovel'     => trim($_POST['m_telemovel'] ?? ''),
            'pais'          => trim($_POST['pais'] ?? ''),
            'distrito'      => trim($_POST['distrito'] ?? ''),
            'concelho'      => trim($_POST['concelho'] ?? ''),
            'rua'           => trim($_POST['rua'] ?? ''),
            'numero_porta'  => trim($_POST['numero_porta'] ?? ''),
            'numero_lote'   => trim($_POST['numero_lote'] ?? ''),
            'codigo_postal' => trim($_POST['codigo_postal'] ?? ''),
            'localidade'    => trim($_POST['localidade'] ?? ''),
            'predefinida'   => !empty($_POST['predefinida']),
        ];
        $e = &$erros['morada'];
        $indicativoMorada = isset($_POST['m_indicativo']) && is_string($_POST['m_indicativo'])
            ? $_POST['m_indicativo']
            : '';
        $telefoneMorada = null;

        // [chave, etiqueta, tamanho máximo, obrigatório]
        $regras = [
            ['designacao',   'Designação',            50,  true],
            ['destinatario', 'Nome do destinatário',  100, true],
            ['telemovel',    'Telemóvel',             20,  false],
            ['pais',         'País',                  60,  true],
            ['distrito',     'Distrito',              60,  true],
            ['concelho',     'Concelho/Região',       60,  true],
            ['rua',          'Rua',                   150, true],
            ['numero_porta', 'Nº de porta',           10,  true],
            ['numero_lote',  'Lote/Andar',            30,  false],
            ['localidade',   'Localidade',            80,  true],
        ];
        foreach ($regras as [$k, $etiqueta, $max, $obrig]) {
            if ($obrig && $m[$k] === '') $e[] = "$etiqueta é obrigatório.";
            elseif (mb_strlen($m[$k]) > $max) $e[] = "$etiqueta não pode ter mais de $max caracteres.";
        }
        if (!preg_match('/^\d{4}-\d{3}$/', $m['codigo_postal'])) $e[] = 'Código postal inválido (formato 0000-000).';
        $configTelefoneMorada = $indicativosTelefone[$indicativoMorada] ?? null;
        if ($configTelefoneMorada === null) {
            $e[] = 'Selecione um indicativo telefónico válido.';
        } elseif ($m['telemovel'] !== '') {
            if (!preg_match('/^[0-9 ]+$/', $m['telemovel'])) {
                $e[] = 'Telemóvel da morada inválido.';
            } else {
                $digitosTelefoneMorada = preg_replace('/\D/', '', $m['telemovel']);
                $totalDigitosTelefoneMorada = strlen($digitosTelefoneMorada);
                if ($totalDigitosTelefoneMorada < $configTelefoneMorada['min']
                    || $totalDigitosTelefoneMorada > $configTelefoneMorada['max']) {
                    $e[] = 'O número de telemóvel não corresponde ao indicativo selecionado.';
                } else {
                    $telefoneMorada = '+' . $configTelefoneMorada['codigo'] . $digitosTelefoneMorada;
                }
            }
        }

        if (!$e) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('SELECT COUNT(*) FROM Morada WHERE IDUtilizador = ?');
                $stmt->execute([$id]);
                $primeira = ((int) $stmt->fetchColumn() === 0);
                $predef   = ($primeira || $m['predefinida']) ? 1 : 0;

                if ($predef) {
                    $pdo->prepare('UPDATE Morada SET Predefinida = 0 WHERE IDUtilizador = ?')->execute([$id]);
                }

                $pdo->prepare(
                    'INSERT INTO Morada
                        (IDUtilizador, Designacao, NomeDestinatario, Telemovel, Pais, Distrito, ConcelhoRegiao,
                         Rua, NumeroPorta, NumeroLote, CodigoPostal, Localidade, Predefinida, DataCriacao, DataAlterado)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                )->execute([
                    $id, $m['designacao'], $m['destinatario'],
                    $telefoneMorada,
                    $m['pais'], $m['distrito'], $m['concelho'], $m['rua'], $m['numero_porta'],
                    $m['numero_lote'] !== '' ? $m['numero_lote'] : null,
                    $m['codigo_postal'], $m['localidade'], $predef,
                ]);

                $pdo->commit();
                header('Location: perfil.php?ok=morada#moradas');
                exit;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $e[] = 'Não foi possível guardar a morada. Tente novamente.';
            }
        }
        unset($e);
    }

    /* ---------- Morada predefinida ---------- */
    if ($acao === 'morada_default') {
        $idMorada = (int) ($_POST['id_morada'] ?? 0);
        $pdo->prepare('UPDATE Morada SET Predefinida = (IDMorada = ?) WHERE IDUtilizador = ?')
            ->execute([$idMorada, $id]);
        header('Location: perfil.php?ok=morada_default#moradas');
        exit;
    }

    /* ---------- Remover morada ---------- */
    if ($acao === 'morada_delete') {
        $idMorada = (int) ($_POST['id_morada'] ?? 0);

        $stmt = $pdo->prepare('SELECT Predefinida FROM Morada WHERE IDMorada = ? AND IDUtilizador = ?');
        $stmt->execute([$idMorada, $id]);
        $linha = $stmt->fetch();

        if ($linha) {
            $pdo->prepare('DELETE FROM Morada WHERE IDMorada = ? AND IDUtilizador = ?')->execute([$idMorada, $id]);
            if ((int) $linha['Predefinida'] === 1) {
                // Passa a mais recente a predefinida
                $pdo->prepare('UPDATE Morada SET Predefinida = 1 WHERE IDUtilizador = ? ORDER BY IDMorada DESC LIMIT 1')
                    ->execute([$id]);
            }
        }
        header('Location: perfil.php?ok=morada_removida#moradas');
        exit;
    }
}

// Moradas do utilizador
$stmt = $pdo->prepare('SELECT * FROM Morada WHERE IDUtilizador = ? ORDER BY Predefinida DESC, IDMorada DESC');
$stmt->execute([$id]);
$moradas = $stmt->fetchAll();

$desde = $u['DataCriacao'] ? date('d/m/Y', strtotime($u['DataCriacao'])) : '';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>O meu perfil | Cantinho</title>
  <link rel="stylesheet" href="css/conta.css?v=20261026225230">
</head>
<body>
  <header class="account-header">
    <a class="brand" href="../index.php" aria-label="Cantinho - página inicial">
      <span class="brand-mark" aria-hidden="true">
        <svg class="svg" viewBox="0 0 24 24"><path d="M20 4C12 4 5 8 5 15c0 2 1 4 3 5 1-5 4-8 9-11-4 4-6 7-7 11 6 0 10-4 10-16Z"/></svg>
      </span>
      <span>Cantinho</span>
    </a>
    <a class="back-link" href="../index.php">Voltar à loja</a>
  </header>

  <main class="account-main perfil-main">
    <div class="intro perfil-top">
      <div>
        <p class="eyebrow">A sua conta Cantinho</p>
        <h1>Olá, <?= e(explode(' ', trim($u['Nome']))[0]) ?></h1>
        <p>@<?= e($u['Username']) ?><?= $desde ? ' · cliente desde ' . e($desde) : '' ?></p>
      </div>
      <div class="perfil-acoes">
        <a class="btn-small btn-secondary" href="carrinho.php">O meu carrinho</a>
        <a class="btn-small btn-danger" href="logout.php">Terminar sessão</a>
      </div>
    </div>

    <?php if ($ok): ?>
      <div class="alert alert-ok" role="status"><?= e($ok) ?></div>
    <?php endif; ?>

    <?php if (!(int) $u['EmailVerificado']): ?>
      <div class="alert alert-aviso">O seu email ainda não está verificado.</div>
    <?php endif; ?>

    <!-- ===== Gastos ===== -->
    <section class="account-card" id="gastos">
      <h2>Os meus gastos</h2>
      <p>Acompanhe o total das compras e a média dos períodos em que fez compras.</p>

      <form class="gastos-periodo" method="get" action="perfil.php#gastos" aria-label="Período da média de gastos">
        <button type="submit" name="periodo" value="mensal" class="<?= $periodoGastos === 'mensal' ? 'ativo' : '' ?>" aria-pressed="<?= $periodoGastos === 'mensal' ? 'true' : 'false' ?>">Ver média mensal</button>
        <button type="submit" name="periodo" value="semanal" class="<?= $periodoGastos === 'semanal' ? 'ativo' : '' ?>" aria-pressed="<?= $periodoGastos === 'semanal' ? 'true' : 'false' ?>">Ver média semanal</button>
      </form>

      <div class="gastos-resumo">
        <div>
          <span>Média <?= $periodoGastos === 'mensal' ? 'mensal' : 'semanal' ?></span>
          <strong><?= number_format($resumoGastos['media'], 2, ',', '.') ?> €</strong>
        </div>
        <div>
          <span>Total em compras</span>
          <strong><?= number_format($resumoGastos['total'], 2, ',', '.') ?> €</strong>
        </div>
        <div>
          <span>Compras registadas</span>
          <strong><?= $resumoGastos['compras'] ?></strong>
        </div>
      </div>

      <?php if ($resumoGastos['recentes']): ?>
        <h3>Compras recentes</h3>
        <ul class="gastos-lista">
          <?php foreach ($resumoGastos['recentes'] as $compra): ?>
            <li>
              <span>
                <strong><?= e($compra['ReferenciaCompra']) ?></strong>
                <time datetime="<?= e(date('Y-m-d\TH:i:s', strtotime($compra['DataCompra']))) ?>"><?= e(date('d/m/Y', strtotime($compra['DataCompra']))) ?></time>
              </span>
              <strong><?= number_format((float) $compra['ValorTotal'], 2, ',', '.') ?> €</strong>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="gastos-vazio">Ainda não tem compras pagas registadas.</p>
      <?php endif; ?>
    </section>

    <section class="account-card" id="dados">
      <h2>Dados pessoais</h2>
      <p>Atualize as suas informações.</p>

      <?php if ($erros['dados']): ?>
        <div class="alert alert-error" role="alert"><ul>
          <?php foreach ($erros['dados'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?>
        </ul></div>
      <?php endif; ?>

      <form method="post" action="perfil.php#dados" novalidate>
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="dados">

        <label for="p-nome">Nome *</label>
        <input id="p-nome" name="nome" type="text" maxlength="100" value="<?= e($v['nome']) ?>" required>

        <label for="p-username">Username *</label>
        <input id="p-username" name="username" type="text" maxlength="50" value="<?= e($v['username']) ?>" required>

        <label for="p-email">Email *</label>
        <input id="p-email" name="email" type="email" maxlength="255" value="<?= e($v['email']) ?>" required>

        <label for="p-tel">Telemóvel <span>(opcional)</span></label>
        <input id="p-tel" name="telemovel" type="tel" maxlength="20" value="<?= e($v['telemovel']) ?>">

        <label for="p-nif">NIF <span>(opcional)</span></label>
        <input id="p-nif" name="nif" type="text" inputmode="numeric" maxlength="9" value="<?= e($v['nif']) ?>">

        <label for="p-nasc">Data de nascimento <span>(opcional)</span></label>
        <input id="p-nasc" name="dataNascimento" type="date" value="<?= e($v['dataNascimento']) ?>">

        <button type="submit">Guardar alterações</button>
      </form>
    </section>

    <!-- ===== Password ===== -->
    <section class="account-card" id="password">
      <h2>Alterar password</h2>
      <p>Por segurança, confirme a password atual.</p>

      <?php if ($erros['password']): ?>
        <div class="alert alert-error" role="alert"><ul>
          <?php foreach ($erros['password'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?>
        </ul></div>
      <?php endif; ?>

      <form method="post" action="perfil.php#password" novalidate>
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="password">

        <label for="pw-atual">Password atual</label>
        <input id="pw-atual" name="password_atual" type="password" autocomplete="current-password" required>

        <label for="pw-nova">Nova password</label>
        <input id="pw-nova" name="password_nova" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>

        <label for="pw-confirma">Confirmar nova password</label>
        <input id="pw-confirma" name="password_confirma" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>

        <button type="submit">Alterar password</button>
      </form>
    </section>

    <!-- ===== Moradas ===== -->
    <section class="account-card" id="moradas">
      <h2>As minhas moradas</h2>
      <p>Moradas de entrega guardadas para as suas compras.</p>

      <?php if (!$moradas): ?>
        <p class="vazio">Ainda não tem nenhuma morada guardada.</p>
      <?php endif; ?>

      <?php foreach ($moradas as $mo): ?>
        <div class="morada-item">
          <div class="morada-texto">
            <strong><?= e($mo['Designacao']) ?></strong>
            <?php if ((int) $mo['Predefinida']): ?><span class="badge">Predefinida</span><?php endif; ?>
            <div><?= e($mo['NomeDestinatario']) ?></div>
            <div>
              <?= e($mo['Rua']) ?>, <?= e($mo['NumeroPorta']) ?><?= $mo['NumeroLote'] ? ', ' . e($mo['NumeroLote']) : '' ?>
            </div>
            <div><?= e($mo['CodigoPostal']) ?> <?= e($mo['Localidade']) ?></div>
            <div><?= e($mo['ConcelhoRegiao']) ?>, <?= e($mo['Distrito']) ?> · <?= e($mo['Pais']) ?></div>
            <?php if ($mo['Telemovel']): ?><div>Tel. <?= e($mo['Telemovel']) ?></div><?php endif; ?>
          </div>
          <div class="morada-acoes">
            <?php if (!(int) $mo['Predefinida']): ?>
              <form method="post" action="perfil.php#moradas">
                <?= campo_csrf() ?>
                <input type="hidden" name="acao" value="morada_default">
                <input type="hidden" name="id_morada" value="<?= (int) $mo['IDMorada'] ?>">
                <button type="submit" class="btn-small btn-secondary">Tornar predefinida</button>
              </form>
            <?php endif; ?>
            <form method="post" action="perfil.php#moradas" onsubmit="return confirm('Remover esta morada?');">
              <?= campo_csrf() ?>
              <input type="hidden" name="acao" value="morada_delete">
              <input type="hidden" name="id_morada" value="<?= (int) $mo['IDMorada'] ?>">
              <button type="submit" class="btn-small btn-danger">Remover</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <details class="nova-morada" <?= $erros['morada'] ? 'open' : '' ?>>
        <summary>+ Adicionar nova morada</summary>

        <?php if ($erros['morada']): ?>
          <div class="alert alert-error" role="alert"><ul>
            <?php foreach ($erros['morada'] as $x): ?><li><?= e($x) ?></li><?php endforeach; ?>
          </ul></div>
        <?php endif; ?>

        <form method="post" action="perfil.php#moradas" novalidate>
          <?= campo_csrf() ?>
          <input type="hidden" name="acao" value="morada_add">

          <label for="m-des">Designação * <span>(ex.: Casa, Trabalho)</span></label>
          <input id="m-des" name="designacao" type="text" maxlength="50" value="<?= e($m['designacao']) ?>" required>

          <label for="m-dest">Nome do destinatário *</label>
          <input id="m-dest" name="destinatario" type="text" maxlength="100" value="<?= e($m['destinatario']) ?>" required>

          <label for="m-tel">Telemóvel <span>(opcional)</span></label>
          <div class="address-phone-fields">
            <select id="m-indicativo" name="m_indicativo" aria-label="Indicativo telefónico">
              <?php foreach ($indicativosTelefone as $codigoPais => $configTelefone): ?>
                <option value="<?= e($codigoPais) ?>"
                        data-min="<?= (int) $configTelefone['min'] ?>"
                        data-max="<?= (int) $configTelefone['max'] ?>"
                        data-grupos="<?= e(implode(',', $configTelefone['grupos'])) ?>"
                        data-aliases="<?= e(implode('|', $configTelefone['aliases'])) ?>"
                        <?= $indicativoMorada === $codigoPais ? 'selected' : '' ?>>
                  +<?= e($configTelefone['codigo']) ?> (<?= e($configTelefone['pais']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <input id="m-tel" name="m_telemovel" type="tel" inputmode="numeric" maxlength="20"
                   placeholder="912 345 678" value="<?= e($m['telemovel']) ?>"
                   autocomplete="tel-national" aria-label="Número de telemóvel sem indicativo">
          </div>

          <label for="m-rua">Rua *</label>
          <input id="m-rua" name="rua" type="text" maxlength="150" value="<?= e($m['rua']) ?>" required>

          <div class="grid-2">
            <div>
              <label for="m-porta">Nº de porta *</label>
              <input id="m-porta" name="numero_porta" type="text" maxlength="10" value="<?= e($m['numero_porta']) ?>" required>
            </div>
            <div>
              <label for="m-lote">Lote/Andar <span>(opcional)</span></label>
              <input id="m-lote" name="numero_lote" type="text" maxlength="30" value="<?= e($m['numero_lote']) ?>">
            </div>
            <div>
              <label for="m-cp">Código postal *</label>
              <input id="m-cp" name="codigo_postal" type="text" inputmode="numeric" maxlength="8"
                     placeholder="0000-000" data-mask="codigo-postal" pattern="[0-9]{4}-[0-9]{3}"
                     title="Use o formato 0000-000" value="<?= e($m['codigo_postal']) ?>" required>
            </div>
            <div>
              <label for="m-loc">Localidade *</label>
              <input id="m-loc" name="localidade" type="text" maxlength="80" value="<?= e($m['localidade']) ?>" required>
            </div>
            <div>
              <label for="m-conc">Concelho/Região *</label>
              <input id="m-conc" name="concelho" type="text" maxlength="60" value="<?= e($m['concelho']) ?>" required>
            </div>
            <div>
              <label for="m-dist">Distrito *</label>
              <input id="m-dist" name="distrito" type="text" maxlength="60" value="<?= e($m['distrito']) ?>" required>
            </div>
          </div>

          <label for="m-pais">País *</label>
          <input id="m-pais" name="pais" type="text" maxlength="60" value="<?= e($m['pais']) ?>" required>

          <label class="check">
            <input type="checkbox" name="predefinida" value="1" <?= $m['predefinida'] ? 'checked' : '' ?>>
            Usar como morada predefinida
          </label>

          <button type="submit">Guardar morada</button>
        </form>
      </details>
    </section>
  </main>
  <script>
    (() => {
      const countryCode = document.getElementById('m-indicativo');
      const phone = document.getElementById('m-tel');
      const country = document.getElementById('m-pais');
      const postalCode = document.getElementById('m-cp');
      if (!countryCode || !phone || !country || !postalCode) return;

      const normalizeCountry = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
      const getFormattedPhone = () => {
        const selected = countryCode.selectedOptions[0];
        const groups = selected.dataset.grupos.split(',').map(Number);
        const digits = phone.value.replace(/\D/g, '').slice(0, Number(selected.dataset.max));
        const parts = [];
        let offset = 0;
        for (const size of groups) {
          if (offset >= digits.length) break;
          parts.push(digits.slice(offset, offset + size));
          offset += size;
        }
        if (offset < digits.length) parts.push(digits.slice(offset));
        return { value: parts.join(' '), digits, min: Number(selected.dataset.min) };
      };

      const formatPhone = () => {
        const formatted = getFormattedPhone();
        phone.value = formatted.value;
        phone.setCustomValidity(formatted.digits && formatted.digits.length < formatted.min
          ? 'O número não corresponde ao indicativo selecionado.'
          : '');
      };

      const formatPhoneAtCursor = () => {
        const cursor = phone.selectionStart ?? phone.value.length;
        const digitsBeforeCursor = phone.value.slice(0, cursor).replace(/\D/g, '').length;
        formatPhone();
        let nextCursor = 0;
        let countedDigits = 0;
        while (nextCursor < phone.value.length && countedDigits < digitsBeforeCursor) {
          if (/\d/.test(phone.value[nextCursor])) countedDigits++;
          nextCursor++;
        }
        if (phone.value[nextCursor] === ' ' && countedDigits > 0) nextCursor++;
        phone.setSelectionRange(nextCursor, nextCursor);
      };

      const formatPostalCode = () => {
        const cursor = postalCode.selectionStart ?? postalCode.value.length;
        const digitsBeforeCursor = postalCode.value.slice(0, cursor).replace(/\D/g, '').length;
        const digits = postalCode.value.replace(/\D/g, '').slice(0, 7);
        postalCode.value = digits.length > 4 ? digits.slice(0, 4) + '-' + digits.slice(4) : digits;
        let nextCursor = 0;
        let countedDigits = 0;
        while (nextCursor < postalCode.value.length && countedDigits < digitsBeforeCursor) {
          if (/\d/.test(postalCode.value[nextCursor])) countedDigits++;
          nextCursor++;
        }
        if (postalCode.value[nextCursor] === '-' && countedDigits === 4) nextCursor++;
        postalCode.setSelectionRange(nextCursor, nextCursor);
      };

      countryCode.addEventListener('change', formatPhone);
      phone.addEventListener('input', formatPhoneAtCursor);
      country.addEventListener('change', () => {
        const enteredCountry = normalizeCountry(country.value);
        const matchingOption = Array.from(countryCode.options).find((option) =>
          option.dataset.aliases.split('|').some((alias) => normalizeCountry(alias) === enteredCountry)
        );
        if (matchingOption) {
          countryCode.value = matchingOption.value;
          formatPhone();
        }
      });
      postalCode.addEventListener('input', formatPostalCode);
      formatPhone();
      formatPostalCode();
    })();
  </script>
</body>
</html>