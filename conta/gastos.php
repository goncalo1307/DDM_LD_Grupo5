<?php

function gastos_compras_preparar(PDO $pdo): void {
    static $preparada = false;
    if ($preparada) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS GastoCompra (
            IDGastoCompra INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            IDUtilizador INT NOT NULL,
            ReferenciaCompra VARCHAR(100) NULL,
            ValorTotal DECIMAL(10,2) UNSIGNED NOT NULL,
            DataCompra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_gasto_compra_referencia (IDUtilizador, ReferenciaCompra),
            INDEX idx_gasto_compra_data (IDUtilizador, DataCompra)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $preparada = true;
}

function gastos_compras_resumo(PDO $pdo, int $idUtilizador, string $periodo): array {
    $agrupamento = $periodo === 'semanal'
        ? 'YEARWEEK(DataCompra, 3)'
        : 'YEAR(DataCompra), MONTH(DataCompra)';

    $stmt = $pdo->prepare(
        'SELECT COALESCE(AVG(periodos.Total), 0)
         FROM (
             SELECT SUM(ValorTotal) AS Total
             FROM GastoCompra
             WHERE IDUtilizador = ?
             GROUP BY ' . $agrupamento . '
         ) AS periodos'
    );
    $stmt->execute([$idUtilizador]);
    $media = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(ValorTotal), 0) AS TotalGasto, COUNT(*) AS TotalCompras
         FROM GastoCompra
         WHERE IDUtilizador = ?'
    );
    $stmt->execute([$idUtilizador]);
    $totais = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT ReferenciaCompra, ValorTotal, DataCompra
         FROM GastoCompra
         WHERE IDUtilizador = ?
         ORDER BY DataCompra DESC, IDGastoCompra DESC
         LIMIT 5'
    );
    $stmt->execute([$idUtilizador]);

    return [
        'media' => $media,
        'total' => (float) $totais['TotalGasto'],
        'compras' => (int) $totais['TotalCompras'],
        'recentes' => $stmt->fetchAll(),
    ];
}
