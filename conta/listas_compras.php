<?php

function listas_compras_preparar(PDO $pdo): void {
    static $preparada = false;
    if ($preparada) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ListaCompra (
            IDListaCompra INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            IDUtilizador INT UNSIGNED NOT NULL,
            Nome VARCHAR(80) NOT NULL,
            DataCriacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            DataAlterado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_lista_compra_utilizador (IDUtilizador, DataAlterado),
            CONSTRAINT fk_lista_compra_utilizador
                FOREIGN KEY (IDUtilizador) REFERENCES Utilizador (IDUtilizador)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ItemListaCompra (
            IDItemListaCompra INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            IDListaCompra INT UNSIGNED NOT NULL,
            IDProduto INT UNSIGNED NOT NULL,
            Quantidade INT UNSIGNED NOT NULL DEFAULT 1,
            UNIQUE KEY uq_item_lista_produto (IDListaCompra, IDProduto),
            CONSTRAINT fk_item_lista_lista
                FOREIGN KEY (IDListaCompra) REFERENCES ListaCompra (IDListaCompra)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_item_lista_produto
                FOREIGN KEY (IDProduto) REFERENCES Produto (IDProduto)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $preparada = true;
}

function listas_compras_do_utilizador(PDO $pdo, int $idUtilizador): array {
    $stmt = $pdo->prepare(
        'SELECT IDListaCompra, Nome
         FROM ListaCompra
         WHERE IDUtilizador = ?
         ORDER BY DataAlterado DESC, IDListaCompra DESC'
    );
    $stmt->execute([$idUtilizador]);

    return $stmt->fetchAll();
}
