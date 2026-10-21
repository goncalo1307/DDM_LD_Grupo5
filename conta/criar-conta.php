<?php
require __DIR__ . '/ligacao.php';

// Se já tem sessão iniciada, não faz sentido registar
if (!empty($_SESSION['utilizador_id'])) {
    header('Location: ../index.html');
    exit;
}

$erros = [];
$v = [ // valores para repor no formulário
    'nome' => '', 'username' => '', 'email' => '',
    'telemovel' => '', 'nif' => '', 'dataNascimento' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $erros[] = 'Pedido inválido. Atualize a página e tente novamente.';
    }

    $v['nome']           = trim($_POST['nome'] ?? '');
    $v['username']       = trim($_POST['username'] ?? '');
    $v['email']          = mb_strtolower(trim($_POST['email'] ?? ''));
    $password            = $_POST['password'] ?? '';
    $v['telemovel']      = trim($_POST['telemovel'] ?? '');
    $v['nif']            = trim($_POST['nif'] ?? '');
    $v['dataNascimento'] = trim($_POST['dataNascimento'] ?? '');

    // ---- Validações ----
    if ($v['nome'] === '' || mb_strlen($v['nome']) > 100) {
        $erros[] = 'Indique um nome válido (máx. 100 caracteres).';
    }

    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $v['username'])) {
        $erros[] = 'O username deve ter 3 a 50 caracteres (letras, números, _ e .).';
    }

    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($v['email']) > 255) {
        $erros[] = 'Indique um email válido.';
    }

    if (strlen($password) < 8) {
        $erros[] = 'A password deve ter pelo menos 8 caracteres.';
    } elseif (strlen($password) > 72) {
        $erros[] = 'A password não pode ter mais de 72 caracteres.';
    }

    if ($v['telemovel'] !== '' && !preg_match('/^\+?[0-9 ]{9,20}$/', $v['telemovel'])) {
        $erros[] = 'Telemóvel inválido.';
    }

    if ($v['nif'] !== '' && !preg_match('/^\d{9}$/', $v['nif'])) {
        $erros[] = 'O NIF deve ter 9 dígitos.';
    }

    if ($v['dataNascimento'] !== '') {
        $data = DateTime::createFromFormat('Y-m-d', $v['dataNascimento']);
        if (!$data || $data->format('Y-m-d') !== $v['dataNascimento'] || $data > new DateTime('today')) {
            $erros[] = 'Data de nascimento inválida.';
        }
    }

    // ---- Username / email já existem? ----
    if (!$erros) {
        $stmt = $pdo->prepare('SELECT Username, Email FROM Utilizador WHERE Username = ? OR Email = ?');
        $stmt->execute([$v['username'], $v['email']]);
        foreach ($stmt->fetchAll() as $linha) {
            if (strcasecmp($linha['Username'], $v['username']) === 0) $erros[] = 'Esse username já está em uso.';
            if (strcasecmp($linha['Email'], $v['email']) === 0)       $erros[] = 'Esse email já tem conta.';
        }
        $erros = array_unique($erros);
    }

    // ---- Inserir ----
    if (!$erros) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO Utilizador
                    (Nome, Username, Email, PasswordHash, Tipo, Telemovel, NIF, DataNascimento,
                     EmailVerificado, Ativo, DataCriacao, DataAlterado)
                 VALUES (?, ?, ?, ?, "cliente", ?, ?, ?, 0, 1, NOW(), NOW())'
            );
            $stmt->execute([
                $v['nome'],
                $v['username'],
                $v['email'],
                $hash,
                $v['telemovel']      !== '' ? $v['telemovel']      : null,
                $v['nif']            !== '' ? $v['nif']            : null,
                $v['dataNascimento'] !== '' ? $v['dataNascimento'] : null,
            ]);

            $idUtilizador = (int) $pdo->lastInsertId();

            // Carrinho (1 por utilizador)
            $stmt = $pdo->prepare('INSERT INTO Carrinho (IDUtilizador, DataCriacao, DataAlterado) VALUES (?, NOW(), NOW())');
            $stmt->execute([$idUtilizador]);

            $pdo->commit();

            header('Location: conta.php?registo=ok');
            exit;
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();

            // 23000 = violação de UNIQUE (corrida entre dois registos iguais)
            if ($ex->getCode() === '23000') {
                $erros[] = 'Username ou email já registado.';
            } else {
                $erros[] = 'Não foi possível criar a conta. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Criar conta | Cantinho</title>
  <link rel="stylesheet" href="css/conta.css">
</head>
<body>
  <header class="account-header">
    <a class="brand" href="../index.html" aria-label="Cantinho - página inicial">
      <span class="brand-mark" aria-hidden="true">
        <svg class="svg" viewBox="0 0 24 24"><path d="M20 4C12 4 5 8 5 15c0 2 1 4 3 5 1-5 4-8 9-11-4 4-6 7-7 11 6 0 10-4 10-16Z"/></svg>
      </span>
      <span>Cantinho</span>
    </a>
    <a class="back-link" href="../index.html">Voltar à loja</a>
  </header>

  <main class="account-main">
    <div class="intro">
      <p class="eyebrow">A sua conta Cantinho</p>
      <h1>Criar conta</h1>
      <p>Preencha os seus dados para se registar.</p>
    </div>

    <section class="account-card" id="criar-conta">
      <h2>Dados da conta</h2>
      <p>Os campos assinalados são obrigatórios.</p>

      <?php if ($erros): ?>
        <div class="alert alert-error" role="alert">
          <ul>
            <?php foreach ($erros as $erro): ?>
              <li><?= e($erro) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="criar-conta.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label for="register-name">Nome *</label>
        <input id="register-name" name="nome" type="text" autocomplete="name" maxlength="100"
               value="<?= e($v['nome']) ?>" required>

        <label for="register-username">Username *</label>
        <input id="register-username" name="username" type="text" autocomplete="username" maxlength="50"
               value="<?= e($v['username']) ?>" required>

        <label for="register-email">Email *</label>
        <input id="register-email" name="email" type="email" autocomplete="email" maxlength="255"
               placeholder="nome@exemplo.pt" value="<?= e($v['email']) ?>" required>

        <label for="register-password">Password *</label>
        <input id="register-password" name="password" type="password" autocomplete="new-password"
               minlength="8" maxlength="72" required>

        <label for="register-phone">Telemóvel <span>(opcional)</span></label>
        <input id="register-phone" name="telemovel" type="tel" autocomplete="tel" maxlength="20"
               value="<?= e($v['telemovel']) ?>">

        <label for="register-nif">NIF <span>(opcional)</span></label>
        <input id="register-nif" name="nif" type="text" inputmode="numeric" maxlength="9"
               value="<?= e($v['nif']) ?>">

        <label for="register-birthdate">Data de nascimento <span>(opcional)</span></label>
        <input id="register-birthdate" name="dataNascimento" type="date" autocomplete="bday"
               value="<?= e($v['dataNascimento']) ?>">

        <button type="submit">Criar conta</button>
      </form>
      <p class="account-switch">Já tem conta? <a href="conta.php">Iniciar sessão</a></p>
    </section>
  </main>
</body>
</html>