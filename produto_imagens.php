<?php

function produto_galeria_preparar(PDO $pdo): void {
    static $preparada = false;
    if ($preparada) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ProdutoImagem (
            IDProdutoImagem INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            IDProduto INT NOT NULL,
            Caminho VARCHAR(500) NOT NULL,
            Ordem TINYINT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_produto_imagem_ordem (IDProduto, Ordem, IDProdutoImagem)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $preparada = true;
}

function produto_galeria_listar(PDO $pdo, int $idProduto): array {
    produto_galeria_preparar($pdo);
    $stmt = $pdo->prepare(
        'SELECT IDProdutoImagem, Caminho, Ordem
         FROM ProdutoImagem
         WHERE IDProduto = ?
         ORDER BY Ordem, IDProdutoImagem'
    );
    $stmt->execute([$idProduto]);
    return $stmt->fetchAll();
}

function produto_imagens(string $nome): array {
    $nomeNormalizado = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    $nomeNormalizado = strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $nomeNormalizado ?: $nome));
    $grupos = [
        'agua com gas' => 'bebidas/aguas/agua com gas',
        'agua sem gas' => 'bebidas/aguas/agua sem gas',
        'salmao congel' => 'peixe/peixe congelado/salmao',
        'dourada congel' => 'peixe/peixe congelado/dourada',
        'salmao fresc' => 'peixe/peixe fresco/salmao',
        'dourada fresc' => 'peixe/peixe fresco/dourada',
        'frango' => 'carne/frango',
        'peru' => 'carne/peru',
        'porco' => 'carne/porco',
        'banana' => 'legumes-frutas/frutas/banana',
        'pera' => 'legumes-frutas/frutas/pera',
        'cebola' => 'legumes-frutas/legumes',
        'tomate' => 'legumes-frutas/legumes',
        'arroz' => 'mercearia/arroz-massa-farinha/arroz',
        'farinha' => 'mercearia/arroz-massa-farinha/farinhas',
        'massa' => 'mercearia/arroz-massa-farinha/massa',
        'azeite' => 'mercearia/azeite-oleo/azeite',
        'oleo' => 'mercearia/azeite-oleo/oleo',
    ];

    $pastaGrupo = '';
    $termoGrupo = '';
    foreach ($grupos as $termo => $pasta) {
        if (strpos($nomeNormalizado, $termo) !== false) {
            $pastaGrupo = $pasta;
            $termoGrupo = $termo;
            break;
        }
    }

    if ($pastaGrupo === '') {
        return ['principal' => '', 'galeria' => []];
    }

    $ficheiros = glob(__DIR__ . DIRECTORY_SEPARATOR . 'imagens' . DIRECTORY_SEPARATOR . 'produtos'
        . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pastaGrupo) . DIRECTORY_SEPARATOR . '*.jpg');
    if ($ficheiros === false || !$ficheiros) {
        return ['principal' => '', 'galeria' => []];
    }

    if ($termoGrupo === 'cebola' || $termoGrupo === 'tomate') {
        $prefixo = $termoGrupo === 'cebola' ? 'legumes-cebola-' : 'legumes-tomate-';
        $ficheiros = array_values(array_filter($ficheiros, static function (string $ficheiro) use ($prefixo): bool {
            return strpos(basename($ficheiro), $prefixo) === 0;
        }));
    }

    if (!$ficheiros) {
        return ['principal' => '', 'galeria' => []];
    }

    sort($ficheiros, SORT_NATURAL | SORT_FLAG_CASE);
    $galeria = [];
    $imagemCatalogo = '';
    foreach ($ficheiros as $ficheiro) {
        $caminhoRelativo = substr($ficheiro, strlen(__DIR__) + 1);
        $url = implode('/', array_map('rawurlencode', explode(DIRECTORY_SEPARATOR, $caminhoRelativo)));
        $galeria[] = $url;
        if (strpos(strtolower(basename($ficheiro)), '-catalago.') !== false) {
            $imagemCatalogo = $url;
        }
    }

    return [
        'principal' => $imagemCatalogo !== '' ? $imagemCatalogo : $galeria[0],
        'galeria' => $galeria,
    ];
}
