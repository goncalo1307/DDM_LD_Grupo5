<?php
require __DIR__ . '/ligacao.php';

// Já está autenticado
if (!empty($_SESSION['utilizador_id'])) {
    header('Location: ../index.php');
    exit;
}

$erro  = '';
$email = '';
$irParaCarrinho = ($_GET['motivo'] ?? '') === 'carrinho';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = mb_strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $irParaCarrinho = ($_POST['destino'] ?? '') === 'carrinho';

    if (!csrf_valido()) {
        $erro = 'Pedido inválido. Atualize a página e tente novamente.';
    } elseif ($email === '' || $password === '') {
        $erro = 'Preencha o email e a palavra-passe.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT IDUtilizador, Nome, Username, PasswordHash, Tipo, Ativo
             FROM Utilizador WHERE Email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        // Mensagem genérica de propósito (não revela se o email existe)
        if (!$u || !password_verify($password, $u['PasswordHash'])) {
            $erro = 'Email ou palavra-passe incorretos.';
        } elseif (!(int) $u['Ativo']) {
            $erro = 'Esta conta está desativada.';
        } else {
            session_regenerate_id(true);

            $_SESSION['utilizador_id']       = (int) $u['IDUtilizador'];
            $_SESSION['utilizador_nome']     = $u['Nome'];
            $_SESSION['utilizador_username'] = $u['Username'];
            $_SESSION['utilizador_tipo']     = $u['Tipo']; // 'cliente' | 'admin'

            // Atualiza hash se o algoritmo por defeito mudar no futuro
            if (password_needs_rehash($u['PasswordHash'], PASSWORD_DEFAULT)) {
                $novo = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE Utilizador SET PasswordHash = ? WHERE IDUtilizador = ?')
                    ->execute([$novo, $u['IDUtilizador']]);
            }

            $pdo->prepare('UPDATE Utilizador SET UltimoLogin = NOW() WHERE IDUtilizador = ?')
                ->execute([$u['IDUtilizador']]);

            header('Location: ' . ($irParaCarrinho ? 'carrinho.php' : '../index.php'));
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
  <title>A minha conta | Cantinho</title>
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
      <p class="eyebrow">A sua conta Cantinho</p>
      <h1>Bem-vindo ao Cantinho</h1>
      <p>Inicie sessão para continuar.</p>
    </div>

    <section class="account-card" id="iniciar-sessao">
      <h2>Iniciar sessão</h2>
      <p>Introduza os seus dados de acesso.</p>

      <?php if (($_GET['registo'] ?? '') === 'ok'): ?>
        <div class="alert alert-ok" role="status">Conta criada com sucesso! Já pode iniciar sessão.</div>
      <?php endif; ?>

      <?php if (($_GET['motivo'] ?? '') === 'carrinho'): ?>
        <div class="alert alert-aviso" role="status">
          Para guardar e consultar o seu carrinho, precisa de iniciar sessão ou criar uma conta.
        </div>
      <?php endif; ?>

      <?php if ($erro): ?>
        <div class="alert alert-error" role="alert"><?= e($erro) ?></div>
      <?php endif; ?>

      <form method="post" action="conta.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if ($irParaCarrinho): ?>
          <input type="hidden" name="destino" value="carrinho">
        <?php endif; ?>

        <label for="login-email">Email</label>
        <input id="login-email" name="email" type="email" autocomplete="email"
               placeholder="nome@exemplo.pt" value="<?= e($email) ?>" required>

        <label for="login-password">Palavra-passe</label>
        <input id="login-password" name="password" type="password" autocomplete="current-password" required>

        <button type="submit">Iniciar sessão</button>
      </form>
      <p class="account-switch">Ainda não tem conta? <a href="criar-conta.php">Criar conta</a></p>
    </section>
  </main>
</body>
</html>