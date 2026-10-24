<?php
require __DIR__ . '/conta/ligacao.php';
require __DIR__ . '/produto_imagens.php';

if (empty($_SESSION['utilizador_id'])) {
    header('Location: conta/conta.php');
    exit;
}

$stmt = $pdo->prepare('SELECT Tipo, Ativo FROM Utilizador WHERE IDUtilizador = ? LIMIT 1');
$stmt->execute([(int) $_SESSION['utilizador_id']]);
$utilizador = $stmt->fetch();

if (!$utilizador || !(int) $utilizador['Ativo'] || $utilizador['Tipo'] !== 'admin') {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="pt-PT">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Acesso recusado | Cantinho</title>
      <link rel="stylesheet" href="admin.css">
    </head>
    <body class="admin-page">
      <main class="admin-shell">
        <h1>Acesso recusado</h1>
        <p>Esta área está disponível apenas para administradores.</p>
        <a class="admin-secondary-button" href="index.php">Voltar à loja</a>
      </main>
    </body>
    </html>
    <?php
    exit;
}

$erro = '';
$mensagem = '';
$produtoVazio = [
    'IDProduto' => '',
    'SKU' => '',
    'Nome' => '',
    'Descricao' => '',
    'Marca' => '',
    'IDCategoria' => '',
    'IDFornecedor' => '',
    'Preco' => '',
    'PrecoPromocional' => '',
    'TaxaIVA' => '6.00',
    'Unidade' => '',
    'StockQuantidade' => '0',
    'StockMinimo' => '5',
    'Alergenios' => '',
    'Imagem' => '',
    'Destaque' => '0',
    'Ativo' => '1',
];
$categoriaVazia = [
    'IDCategoria' => '',
    'IDCategoriaPai' => '',
    'Nome' => '',
    'Slug' => '',
    'Descricao' => '',
    'Ordem' => '0',
    'Ativo' => '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        http_response_code(400);
        $erro = 'O pedido expirou ou é inválido. Atualize a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';

        try {
            if ($acao === 'guardar_produto') {
                $idProdutoRaw = trim($_POST['idProduto'] ?? '0');
                if (!preg_match('/^\d+$/', $idProdutoRaw)) {
                    throw new InvalidArgumentException('Produto inválido.');
                }
                $idProduto = (int) $idProdutoRaw;
                $sku = trim($_POST['sku'] ?? '');
                $nome = trim($_POST['nome'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                $marca = trim($_POST['marca'] ?? '');
                $idCategoriaRaw = trim($_POST['idCategoria'] ?? '0');
                if (!preg_match('/^\d+$/', $idCategoriaRaw)) {
                    throw new InvalidArgumentException('Categoria inválida.');
                }
                $idCategoria = (int) $idCategoriaRaw;
                $idFornecedor = trim($_POST['idFornecedor'] ?? '');
                $preco = trim($_POST['preco'] ?? '');
                $precoPromocional = trim($_POST['precoPromocional'] ?? '');
                $taxaIva = trim($_POST['taxaIva'] ?? '6.00');
                $unidade = trim($_POST['unidade'] ?? '');
                $stockQuantidade = trim($_POST['stockQuantidade'] ?? '');
                $stockMinimo = trim($_POST['stockMinimo'] ?? '');
                $alergenios = trim($_POST['alergenios'] ?? '');
                $imagem = trim($_POST['imagem'] ?? '');
                $destaque = isset($_POST['destaque']) ? 1 : 0;
                $ativo = isset($_POST['ativo']) ? 1 : 0;

                if ($sku === '' || mb_strlen($sku) > 40) {
                    throw new InvalidArgumentException('Indique um SKU com até 40 caracteres.');
                }
                if ($nome === '' || mb_strlen($nome) > 150) {
                    throw new InvalidArgumentException('Indique um nome com até 150 caracteres.');
                }
                if ($idCategoria < 1 || !preg_match('/^\d+(?:\.\d{1,2})?$/', $preco)) {
                    throw new InvalidArgumentException('Selecione uma categoria e indique preços válidos.');
                }
                if ($precoPromocional !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/', $precoPromocional)) {
                    throw new InvalidArgumentException('Indique um preço promocional válido ou deixe o campo vazio.');
                }
                if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $taxaIva) || (float) $taxaIva > 100) {
                    throw new InvalidArgumentException('Indique uma taxa de IVA válida entre 0 e 100.');
                }
                if (!preg_match('/^\d+$/', $stockQuantidade) || !preg_match('/^\d+$/', $stockMinimo)) {
                    throw new InvalidArgumentException('O stock atual e mínimo devem ser números inteiros não negativos.');
                }
                if ($imagem !== '' && !filter_var($imagem, FILTER_VALIDATE_URL) && !admin_imagem_local_valida($imagem)) {
                    throw new InvalidArgumentException('Selecione uma imagem disponível ou indique um URL válido.');
                }
                if (mb_strlen($marca) > 80 || mb_strlen($unidade) > 20 || mb_strlen($alergenios) > 255) {
                    throw new InvalidArgumentException('Verifique o tamanho dos campos Marca, Unidade e Alergénios.');
                }

                $stmt = $pdo->prepare('SELECT COUNT(*) FROM Categoria WHERE IDCategoria = ?');
                $stmt->execute([$idCategoria]);
                if (!(int) $stmt->fetchColumn()) {
                    throw new InvalidArgumentException('A categoria selecionada não existe.');
                }

                if ($idFornecedor !== '') {
                    if (!ctype_digit($idFornecedor)) {
                        throw new InvalidArgumentException('Selecione um fornecedor válido.');
                    }
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM Fornecedor WHERE IDFornecedor = ?');
                    $stmt->execute([(int) $idFornecedor]);
                    if (!(int) $stmt->fetchColumn()) {
                        throw new InvalidArgumentException('O fornecedor selecionado não existe.');
                    }
                }

                $valores = [
                    $sku,
                    $nome,
                    $descricao !== '' ? $descricao : null,
                    $marca !== '' ? $marca : null,
                    $idCategoria,
                    $idFornecedor !== '' ? (int) $idFornecedor : null,
                    $preco,
                    $precoPromocional !== '' ? $precoPromocional : null,
                    $taxaIva,
                    $unidade !== '' ? $unidade : 'un',
                    (int) $stockQuantidade,
                    (int) $stockMinimo,
                    $alergenios !== '' ? $alergenios : null,
                    $imagem !== '' ? $imagem : null,
                    $destaque,
                    $ativo,
                ];

                if ($idProduto > 0) {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM Produto WHERE IDProduto = ?');
                    $stmt->execute([$idProduto]);
                    if (!(int) $stmt->fetchColumn()) {
                        throw new InvalidArgumentException('O produto que pretende editar já não existe.');
                    }
                    $valores[] = (int) $_SESSION['utilizador_id'];
                    $valores[] = $idProduto;
                    $pdo->prepare(
                        'UPDATE Produto
                         SET SKU = ?, Nome = ?, Descricao = ?, Marca = ?, IDCategoria = ?, IDFornecedor = ?,
                             Preco = ?, PrecoPromocional = ?, TaxaIVA = ?, Unidade = ?, StockQuantidade = ?,
                             StockMinimo = ?, Alergenios = ?, Imagem = ?, Destaque = ?, Ativo = ?,
                             IDAlteradoPor = ?, DataAlterado = NOW()
                         WHERE IDProduto = ?'
                    )->execute($valores);
                } else {
                    $valores[] = (int) $_SESSION['utilizador_id'];
                    $valores[] = (int) $_SESSION['utilizador_id'];
                    $pdo->prepare(
                        'INSERT INTO Produto
                            (SKU, Nome, Descricao, Marca, IDCategoria, IDFornecedor, Preco, PrecoPromocional,
                             TaxaIVA, Unidade, StockQuantidade, StockMinimo, Alergenios, Imagem, Destaque,
                             Ativo, IDCriadoPor, IDAlteradoPor)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute($valores);
                }

                header('Location: admin.php?ok=produto');
                exit;
            }

            if ($acao === 'guardar_categoria') {
                $idCategoriaRaw = trim($_POST['idCategoria'] ?? '0');
                if (!preg_match('/^\d+$/', $idCategoriaRaw)) {
                    throw new InvalidArgumentException('Categoria inválida.');
                }
                $idCategoria = (int) $idCategoriaRaw;
                $idCategoriaPai = trim($_POST['idCategoriaPai'] ?? '');
                $nome = trim($_POST['nome'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                $ordem = trim($_POST['ordem'] ?? '0');
                $ativo = isset($_POST['ativo']) ? 1 : 0;

                if ($nome === '' || mb_strlen($nome) > 80) {
                    throw new InvalidArgumentException('Indique um nome de categoria com até 80 caracteres.');
                }
                if ($slug === '') {
                    $slugBase = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
                    $slug = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $slugBase ?: $nome)), '-');
                }
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 100) {
                    throw new InvalidArgumentException('O slug deve conter letras minúsculas, números e hífen.');
                }
                if (!preg_match('/^\d+$/', $ordem)) {
                    throw new InvalidArgumentException('A ordem deve ser um número inteiro não negativo.');
                }
                if ($idCategoriaPai !== '') {
                    if (!ctype_digit($idCategoriaPai) || (int) $idCategoriaPai === $idCategoria) {
                        throw new InvalidArgumentException('Selecione uma categoria principal válida.');
                    }
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM Categoria WHERE IDCategoria = ?');
                    $stmt->execute([(int) $idCategoriaPai]);
                    if (!(int) $stmt->fetchColumn()) {
                        throw new InvalidArgumentException('A categoria principal selecionada não existe.');
                    }
                }

                if ($idCategoria > 0) {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM Categoria WHERE IDCategoria = ?');
                    $stmt->execute([$idCategoria]);
                    if (!(int) $stmt->fetchColumn()) {
                        throw new InvalidArgumentException('A categoria que pretende editar já não existe.');
                    }
                    $pdo->prepare(
                        'UPDATE Categoria
                         SET IDCategoriaPai = ?, Nome = ?, Slug = ?, Descricao = ?, Ordem = ?, Ativo = ?
                         WHERE IDCategoria = ?'
                    )->execute([
                        $idCategoriaPai !== '' ? (int) $idCategoriaPai : null,
                        $nome, $slug, $descricao !== '' ? $descricao : null, (int) $ordem, $ativo, $idCategoria,
                    ]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO Categoria (IDCategoriaPai, Nome, Slug, Descricao, Ordem, Ativo)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $idCategoriaPai !== '' ? (int) $idCategoriaPai : null,
                        $nome, $slug, $descricao !== '' ? $descricao : null, (int) $ordem, $ativo,
                    ]);
                }

                header('Location: admin.php?ok=categoria');
                exit;
            }

            if ($acao === 'desativar_produto') {
                $idProduto = filter_input(INPUT_POST, 'idProduto', FILTER_VALIDATE_INT);
                if (!$idProduto) {
                    throw new InvalidArgumentException('Produto inválido.');
                }
                $pdo->prepare(
                    'UPDATE Produto
                     SET Ativo = 0, IDAlteradoPor = ?, DataAlterado = NOW()
                     WHERE IDProduto = ?'
                )->execute([(int) $_SESSION['utilizador_id'], $idProduto]);
                header('Location: admin.php?ok=desativado');
                exit;
            }

            throw new InvalidArgumentException('Ação de administração inválida.');
        } catch (InvalidArgumentException $ex) {
            $erro = $ex->getMessage();
        } catch (PDOException $ex) {
            $erro = 'Não foi possível guardar a alteração. Verifique os dados e as relações da base de dados.';
        }
    }
}

$mensagens = [
    'produto' => 'Produto guardado com sucesso.',
    'categoria' => 'Categoria guardada com sucesso.',
    'desativado' => 'Produto desativado.',
];
$mensagem = $mensagens[$_GET['ok'] ?? ''] ?? '';

$categorias = $pdo->query('SELECT IDCategoria, IDCategoriaPai, Nome, Slug, Descricao, Ordem, Ativo FROM Categoria ORDER BY Nome')->fetchAll();
$fornecedores = $pdo->query('SELECT IDFornecedor, Nome FROM Fornecedor ORDER BY Nome')->fetchAll();
$produtos = $pdo->query(
    'SELECT p.IDProduto, p.SKU, p.Nome, p.Preco, p.StockQuantidade, p.Ativo, p.Imagem,
            c.Nome AS CategoriaNome
     FROM Produto p
     LEFT JOIN Categoria c ON c.IDCategoria = p.IDCategoria
     ORDER BY p.Nome
     LIMIT 300'
)->fetchAll();

$produtoEdicao = $produtoVazio;
$mostrarFormularioProduto = isset($_GET['novo_produto']);
if (isset($_GET['editar_produto']) && is_string($_GET['editar_produto']) && ctype_digit($_GET['editar_produto'])) {
    $stmt = $pdo->prepare('SELECT * FROM Produto WHERE IDProduto = ?');
    $stmt->execute([(int) $_GET['editar_produto']]);
    $produtoEdicao = $stmt->fetch() ?: $produtoVazio;
    $mostrarFormularioProduto = (bool) $produtoEdicao['IDProduto'];
}

$categoriaEdicao = $categoriaVazia;
$mostrarFormularioCategoria = isset($_GET['nova_categoria']);
if (isset($_GET['editar_categoria']) && is_string($_GET['editar_categoria']) && ctype_digit($_GET['editar_categoria'])) {
    $stmt = $pdo->prepare('SELECT * FROM Categoria WHERE IDCategoria = ?');
    $stmt->execute([(int) $_GET['editar_categoria']]);
    $categoriaEdicao = $stmt->fetch() ?: $categoriaVazia;
    $mostrarFormularioCategoria = (bool) $categoriaEdicao['IDCategoria'];
}

if ($erro !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['acao'] ?? '') === 'guardar_produto') {
        $produtoEdicao = array_merge($produtoVazio, [
            'IDProduto' => admin_post_campo('idProduto'),
            'SKU' => admin_post_campo('sku'),
            'Nome' => admin_post_campo('nome'),
            'Descricao' => admin_post_campo('descricao'),
            'Marca' => admin_post_campo('marca'),
            'IDCategoria' => admin_post_campo('idCategoria'),
            'IDFornecedor' => admin_post_campo('idFornecedor'),
            'Preco' => admin_post_campo('preco'),
            'PrecoPromocional' => admin_post_campo('precoPromocional'),
            'TaxaIVA' => admin_post_campo('taxaIva'),
            'Unidade' => admin_post_campo('unidade'),
            'StockQuantidade' => admin_post_campo('stockQuantidade'),
            'StockMinimo' => admin_post_campo('stockMinimo'),
            'Alergenios' => admin_post_campo('alergenios'),
            'Imagem' => admin_post_campo('imagem'),
            'Destaque' => isset($_POST['destaque']) ? '1' : '0',
            'Ativo' => isset($_POST['ativo']) ? '1' : '0',
        ]);
        $mostrarFormularioProduto = true;
    } elseif (($_POST['acao'] ?? '') === 'guardar_categoria') {
        $categoriaEdicao = array_merge($categoriaVazia, [
            'IDCategoria' => admin_post_campo('idCategoria'),
            'IDCategoriaPai' => admin_post_campo('idCategoriaPai'),
            'Nome' => admin_post_campo('nome'),
            'Slug' => admin_post_campo('slug'),
            'Descricao' => admin_post_campo('descricao'),
            'Ordem' => admin_post_campo('ordem'),
            'Ativo' => isset($_POST['ativo']) ? '1' : '0',
        ]);
        $mostrarFormularioCategoria = true;
    }
}

function admin_campo(array $dados, string $campo): string {
    return e((string) ($dados[$campo] ?? ''));
}

function admin_post_campo(string $campo): string {
    $valor = $_POST[$campo] ?? '';
    return is_scalar($valor) ? (string) $valor : '';
}

function admin_imagem_local_valida(string $imagem): bool {
    $caminhoRelativo = rawurldecode($imagem);
    if (!preg_match('~^imagens[\\\\/]produtos[\\\\/]~i', $caminhoRelativo)) {
        return false;
    }

    $raizImagens = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'imagens' . DIRECTORY_SEPARATOR . 'produtos');
    $caminhoImagem = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $caminhoRelativo));
    $extensao = strtolower(pathinfo($caminhoRelativo, PATHINFO_EXTENSION));
    return $raizImagens !== false
        && $caminhoImagem !== false
        && strpos($caminhoImagem, $raizImagens . DIRECTORY_SEPARATOR) === 0
        && in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'], true)
        && is_file($caminhoImagem);
}

function admin_csrf(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administração | Cantinho</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page">
  <header class="admin-topbar">
    <a class="brand" href="index.php">
      <span class="brand-mark"><svg class="svg" viewBox="0 0 24 24"><path d="M20 4C12 4 5 8 5 15c0 2 1 4 3 5 1-5 4-8 9-11-4 4-6 7-7 11 6 0 10-4 10-16Z"/></svg></span>
      <span>Cantinho</span>
    </a>
    <div class="admin-topbar-actions">
      <a class="admin-secondary-button" href="index.php">Voltar à loja</a>
      <a class="admin-secondary-button" href="conta/logout.php">Terminar sessão</a>
    </div>
  </header>

  <main class="admin-shell">
    <div class="admin-heading">
      <div>
        <p class="eyebrow">Área reservada</p>
        <h1>Administração</h1>
        <p>Gerir produtos e categorias da loja.</p>
      </div>
    </div>

    <nav class="admin-nav" aria-label="Secções de administração">
      <a href="#produtos">Produtos <span><?= count($produtos) ?></span></a>
      <a href="#categorias">Categorias <span><?= count($categorias) ?></span></a>
    </nav>

    <?php if ($mensagem): ?>
      <div class="admin-alert admin-alert-ok" role="status"><?= e($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="admin-alert admin-alert-error" role="alert"><?= e($erro) ?></div>
    <?php endif; ?>

    <section class="admin-section" id="produtos">
      <div class="admin-section-heading">
        <div>
          <h2>Produtos</h2>
          <p>Consulte o catálogo e edite cada produto individualmente.</p>
        </div>
        <a class="admin-primary-button" href="admin.php?novo_produto=1#form-produto">+ Adicionar produto</a>
      </div>

      <?php if ($mostrarFormularioProduto): ?>
      <form class="admin-form admin-form-featured" id="form-produto" method="post" action="admin.php#form-produto">
        <?php
          $imagensProduto = produto_imagens((string) ($produtoEdicao['Nome'] ?? ''));
          $imagemGuardada = (string) ($produtoEdicao['Imagem'] ?? '');
          $imagemSelecionada = $imagemGuardada !== '' ? $imagemGuardada : $imagensProduto['principal'];
        ?>
        <?= admin_csrf() ?>
        <input type="hidden" name="acao" value="guardar_produto">
        <input type="hidden" name="idProduto" value="<?= admin_campo($produtoEdicao, 'IDProduto') ?>">
        <div class="admin-form-heading">
          <div>
            <p class="admin-form-kicker"><?= $produtoEdicao['IDProduto'] ? 'A editar produto' : 'Novo produto' ?></p>
            <h3><?= $produtoEdicao['IDProduto'] ? admin_campo($produtoEdicao, 'Nome') : 'Adicionar produto ao catálogo' ?></h3>
          </div>
          <a class="admin-secondary-button" href="admin.php#produtos">Cancelar</a>
        </div>

        <div class="admin-form-grid">
          <label>SKU *
            <input name="sku" maxlength="40" value="<?= admin_campo($produtoEdicao, 'SKU') ?>" required>
          </label>
          <label>Nome *
            <input name="nome" maxlength="150" value="<?= admin_campo($produtoEdicao, 'Nome') ?>" required>
          </label>
          <label>Categoria *
            <select name="idCategoria" required>
              <option value="">Selecione uma categoria</option>
              <?php foreach ($categorias as $categoria): ?>
                <option value="<?= (int) $categoria['IDCategoria'] ?>" <?= (string) $categoria['IDCategoria'] === (string) ($produtoEdicao['IDCategoria'] ?? '') ? 'selected' : '' ?>>
                  <?= e($categoria['Nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Fornecedor
            <select name="idFornecedor">
              <option value="">Sem fornecedor</option>
              <?php foreach ($fornecedores as $fornecedor): ?>
                <option value="<?= (int) $fornecedor['IDFornecedor'] ?>" <?= (string) $fornecedor['IDFornecedor'] === (string) ($produtoEdicao['IDFornecedor'] ?? '') ? 'selected' : '' ?>>
                  <?= e($fornecedor['Nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Marca
            <input name="marca" maxlength="80" value="<?= admin_campo($produtoEdicao, 'Marca') ?>">
          </label>
          <label>Preço *
            <input name="preco" type="number" min="0" step="0.01" value="<?= admin_campo($produtoEdicao, 'Preco') ?>" required>
          </label>
          <label>Preço promocional
            <input name="precoPromocional" type="number" min="0" step="0.01" value="<?= admin_campo($produtoEdicao, 'PrecoPromocional') ?>">
          </label>
          <label>Taxa de IVA (%) *
            <input name="taxaIva" type="number" min="0" max="100" step="0.01" value="<?= admin_campo($produtoEdicao, 'TaxaIVA') ?>" required>
          </label>
          <label>Unidade
            <input name="unidade" maxlength="20" value="<?= admin_campo($produtoEdicao, 'Unidade') ?: 'un' ?>" placeholder="Ex.: kg, unidade">
          </label>
          <label>Quantidade em stock *
            <input name="stockQuantidade" type="number" min="0" step="1" value="<?= admin_campo($produtoEdicao, 'StockQuantidade') ?>" required>
          </label>
          <label>Stock mínimo *
            <input name="stockMinimo" type="number" min="0" step="1" value="<?= admin_campo($produtoEdicao, 'StockMinimo') ?>" required>
          </label>
          <?php if ($imagensProduto['galeria']): ?>
            <label class="admin-field-wide">Imagem principal
              <select name="imagem">
                <option value="" <?= $imagemSelecionada === '' ? 'selected' : '' ?>>Sem imagem</option>
                <?php if ($imagemGuardada !== '' && !in_array($imagemGuardada, $imagensProduto['galeria'], true)): ?>
                  <option value="<?= e($imagemGuardada) ?>" selected>Manter imagem atual</option>
                <?php endif; ?>
                <?php foreach ($imagensProduto['galeria'] as $indiceImagem => $imagemOpcao): ?>
                  <option value="<?= e($imagemOpcao) ?>" <?= $imagemSelecionada === $imagemOpcao ? 'selected' : '' ?>>
                    <?= strpos(strtolower(basename(rawurldecode($imagemOpcao))), '-catalago.') !== false ? 'Imagem de catálogo' : 'Imagem alternativa ' . ($indiceImagem + 1) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php else: ?>
            <label>Imagem
              <input name="imagem" type="text" maxlength="255" value="<?= admin_campo($produtoEdicao, 'Imagem') ?>" placeholder="URL ou caminho da imagem">
            </label>
          <?php endif; ?>
          <label class="admin-field-wide">Alergénios
            <input name="alergenios" maxlength="255" value="<?= admin_campo($produtoEdicao, 'Alergenios') ?>">
          </label>
          <label class="admin-field-wide">Descrição
            <textarea name="descricao" rows="3"><?= admin_campo($produtoEdicao, 'Descricao') ?></textarea>
          </label>
          <label class="admin-checkbox">
            <input name="destaque" type="checkbox" value="1" <?= (string) ($produtoEdicao['Destaque'] ?? '0') === '1' ? 'checked' : '' ?>>
            Produto em destaque
          </label>
          <label class="admin-checkbox">
            <input name="ativo" type="checkbox" value="1" <?= (string) ($produtoEdicao['Ativo'] ?? '1') === '1' ? 'checked' : '' ?>>
            Produto ativo
          </label>
        </div>
        <div class="admin-form-actions">
          <button class="admin-primary-button" type="submit"><?= $produtoEdicao['IDProduto'] ? 'Guardar alterações' : 'Adicionar produto' ?></button>
        </div>
      </form>
      <?php endif; ?>

      <div class="admin-product-list">
        <?php foreach ($produtos as $produto): ?>
          <article class="admin-product-card <?= (string) $produto['IDProduto'] === (string) ($produtoEdicao['IDProduto'] ?? '') && $mostrarFormularioProduto ? 'is-editing' : '' ?>">
            <div class="admin-product-main">
              <?php $imagemProduto = (string) ($produto['Imagem'] ?? '') ?: produto_imagens((string) $produto['Nome'])['principal']; ?>
              <?php if ($imagemProduto !== ''): ?>
                <img class="admin-product-thumbnail" src="<?= e($imagemProduto) ?>" alt="">
              <?php else: ?>
                <div class="admin-product-icon" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $produto['Nome'], 0, 1))) ?></div>
              <?php endif; ?>
              <div>
                <h3><?= e($produto['Nome']) ?></h3>
                <p><?= e($produto['SKU']) ?> <span aria-hidden="true">·</span> <?= e($produto['CategoriaNome'] ?? 'Sem categoria') ?></p>
              </div>
            </div>
            <div class="admin-product-meta">
              <div><span>Preço</span><strong><?= e(number_format((float) $produto['Preco'], 2, ',', ' ')) ?> €</strong></div>
              <div><span>Stock</span><strong><?= (int) $produto['StockQuantidade'] ?> un.</strong></div>
              <span class="admin-status <?= (int) $produto['Ativo'] ? 'is-active' : 'is-inactive' ?>">
                <?= (int) $produto['Ativo'] ? 'Ativo' : 'Inativo' ?>
              </span>
            </div>
            <div class="admin-product-actions">
              <a class="admin-secondary-button" href="admin.php?editar_produto=<?= (int) $produto['IDProduto'] ?>#form-produto">Editar</a>
              <?php if ((int) $produto['Ativo']): ?>
                <form method="post" action="admin.php#produtos" onsubmit="return confirm('Desativar este produto?');">
                  <?= admin_csrf() ?>
                  <input type="hidden" name="acao" value="desativar_produto">
                  <input type="hidden" name="idProduto" value="<?= (int) $produto['IDProduto'] ?>">
                  <button class="admin-text-button" type="submit">Desativar</button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$produtos): ?>
          <div class="admin-empty-state">Ainda não há produtos registados. Adicione o primeiro produto ao catálogo.</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="admin-section" id="categorias">
      <div class="admin-section-heading">
        <div>
          <h2>Categorias</h2>
          <p>Adicionar ou editar categorias existentes.</p>
        </div>
        <a class="admin-primary-button" href="admin.php?nova_categoria=1#form-categoria">+ Adicionar categoria</a>
      </div>

      <?php if ($mostrarFormularioCategoria): ?>
      <form class="admin-form admin-form-featured" id="form-categoria" method="post" action="admin.php#form-categoria">
        <?= admin_csrf() ?>
        <input type="hidden" name="acao" value="guardar_categoria">
        <input type="hidden" name="idCategoria" value="<?= admin_campo($categoriaEdicao, 'IDCategoria') ?>">
        <div class="admin-form-heading">
          <div>
            <p class="admin-form-kicker"><?= $categoriaEdicao['IDCategoria'] ? 'A editar categoria' : 'Nova categoria' ?></p>
            <h3><?= $categoriaEdicao['IDCategoria'] ? admin_campo($categoriaEdicao, 'Nome') : 'Adicionar categoria' ?></h3>
          </div>
          <a class="admin-secondary-button" href="admin.php#categorias">Cancelar</a>
        </div>
        <div class="admin-form-grid">
          <label>Nome *
            <input name="nome" maxlength="80" value="<?= admin_campo($categoriaEdicao, 'Nome') ?>" required>
          </label>
          <label>Categoria principal
            <select name="idCategoriaPai">
              <option value="">Sem categoria principal</option>
              <?php foreach ($categorias as $categoria): ?>
                <?php if ((string) $categoria['IDCategoria'] !== (string) ($categoriaEdicao['IDCategoria'] ?? '')): ?>
                  <option value="<?= (int) $categoria['IDCategoria'] ?>" <?= (string) $categoria['IDCategoria'] === (string) ($categoriaEdicao['IDCategoriaPai'] ?? '') ? 'selected' : '' ?>>
                    <?= e($categoria['Nome']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Slug
            <input name="slug" maxlength="100" value="<?= admin_campo($categoriaEdicao, 'Slug') ?>" placeholder="gerado-a-partir-do-nome">
          </label>
          <label>Ordem
            <input name="ordem" type="number" min="0" step="1" value="<?= admin_campo($categoriaEdicao, 'Ordem') ?>">
          </label>
          <label class="admin-field-wide">Descrição
            <textarea name="descricao" rows="3"><?= admin_campo($categoriaEdicao, 'Descricao') ?></textarea>
          </label>
          <label class="admin-checkbox">
            <input name="ativo" type="checkbox" value="1" <?= (string) ($categoriaEdicao['Ativo'] ?? '1') === '1' ? 'checked' : '' ?>>
            Categoria ativa
          </label>
        </div>
        <div class="admin-form-actions">
          <button class="admin-primary-button" type="submit"><?= $categoriaEdicao['IDCategoria'] ? 'Guardar alterações' : 'Adicionar categoria' ?></button>
        </div>
      </form>
      <?php endif; ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Categoria</th><th>Slug</th><th>Estado</th><th>Ações</th></tr></thead>
          <tbody>
            <?php foreach ($categorias as $categoria): ?>
              <tr class="<?= (string) $categoria['IDCategoria'] === (string) ($categoriaEdicao['IDCategoria'] ?? '') && $mostrarFormularioCategoria ? 'admin-category-editing' : '' ?>">
                <td><?= e($categoria['Nome']) ?></td>
                <td><?= e($categoria['Slug'] ?? '') ?></td>
                <td><span class="admin-status <?= (int) ($categoria['Ativo'] ?? 0) ? 'is-active' : 'is-inactive' ?>"><?= (int) ($categoria['Ativo'] ?? 0) ? 'Ativa' : 'Inativa' ?></span></td>
                <td><a class="admin-secondary-button" href="admin.php?editar_categoria=<?= (int) $categoria['IDCategoria'] ?>#form-categoria">Editar</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$categorias): ?>
              <tr><td colspan="4">Ainda não há categorias registadas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
