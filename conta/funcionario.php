<?php
require __DIR__ . '/ligacao.php';

if (!empty($_SESSION['utilizador_id'])) {
    $stmt = $pdo->prepare('SELECT Tipo, Ativo FROM Utilizador WHERE IDUtilizador = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['utilizador_id']]);
    $sessao = $stmt->fetch();
    if ($sessao && (int) $sessao['Ativo'] && $sessao['Tipo'] === 'funcionario') {
        header('Location: ../admin.php#encomendas');
        exit;
    }
}

$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!csrf_valido()) {
        $erro = 'Pedido inválido. Atualize a página e tente novamente.';
    } elseif ($email === '' || $password === '') {
        $erro = 'Preencha o email e a palavra-passe.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT IDUtilizador, Nome, Username, PasswordHash, Tipo, Ativo
             FROM Utilizador
             WHERE Email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $utilizador = $stmt->fetch();

        if (!$utilizador || !password_verify($password, $utilizador['PasswordHash'])) {
            $erro = 'Email ou palavra-passe incorretos.';
        } elseif (!(int) $utilizador['Ativo']) {
            $erro = 'Esta conta está desativada.';
        } elseif ($utilizador['Tipo'] !== 'funcionario') {
            $erro = 'Esta área é exclusiva para contas de funcionário.';
        } else {
            session_regenerate_id(true);
            $_SESSION['utilizador_id'] = (int) $utilizador['IDUtilizador'];
            $_SESSION['utilizador_nome'] = $utilizador['Nome'];
            $_SESSION['utilizador_username'] = $utilizador['Username'];
            $_SESSION['utilizador_tipo'] = 'funcionario';

            if (password_needs_rehash($utilizador['PasswordHash'], PASSWORD_DEFAULT)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE Utilizador SET PasswordHash = ? WHERE IDUtilizador = ?')
                    ->execute([$passwordHash, $utilizador['IDUtilizador']]);
            }
            $pdo->prepare('UPDATE Utilizador SET UltimoLogin = NOW() WHERE IDUtilizador = ?')
                ->execute([$utilizador['IDUtilizador']]);

            header('Location: ../admin.php#encomendas');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acesso de funcionário | Cantinho</title>
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
    <a class="back-link" href="../index.php">Voltar à loja</a>
  </header>

  <main class="account-main">
    <div class="intro">
      <p class="eyebrow">Área de equipa</p>
      <h1>Acesso de funcionário</h1>
      <p>Entre para consultar encomendas e atualizar entrega e pagamento.</p>
    </div>

    <section class="account-card">
      <h2>Iniciar sessão</h2>
      <?php if ($erro !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($erro) ?></div>
      <?php endif; ?>
      <form method="post" action="funcionario.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="funcionario-email">Email</label>
        <input id="funcionario-email" name="email" type="email" autocomplete="username"
               value="<?= e($email) ?>" required>
        <label for="funcionario-password">Palavra-passe</label>
        <input id="funcionario-password" name="password" type="password"
               autocomplete="current-password" required>
        <button type="submit">Entrar na área de funcionário</button>
      </form>
      <p class="account-switch">Esta área só aceita contas criadas por um administrador.</p>
      <p class="account-switch"><a href="conta.php">Acesso de cliente</a></p>
    </section>
  </main>
</body>
</html>
