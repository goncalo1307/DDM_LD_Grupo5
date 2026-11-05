<?php

function gastos_compras_resumo(PDO $pdo, int $idUtilizador, string $periodo): array {
    $agrupamento = $periodo === 'semanal'
        ? 'YEARWEEK(DataCriacao, 3)'
        : 'YEAR(DataCriacao), MONTH(DataCriacao)';

    $stmt = $pdo->prepare(
        'SELECT COALESCE(AVG(periodos.Total), 0)
         FROM (
             SELECT SUM(Total) AS Total
             FROM encomenda
             WHERE IDUtilizador = ? AND EstadoPagamento = \'pago\' AND Estado = \'entregue\'
             GROUP BY ' . $agrupamento . '
         ) AS periodos'
    );
    $stmt->execute([$idUtilizador]);
    $media = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(Total), 0) AS TotalGasto, COUNT(*) AS TotalCompras
         FROM encomenda
         WHERE IDUtilizador = ? AND EstadoPagamento = \'pago\' AND Estado = \'entregue\''
    );
    $stmt->execute([$idUtilizador]);
    $totais = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT NumeroEncomenda AS ReferenciaCompra, Total AS ValorTotal, DataCriacao AS DataCompra
         FROM encomenda
         WHERE IDUtilizador = ? AND EstadoPagamento = \'pago\' AND Estado = \'entregue\'
         ORDER BY DataCriacao DESC, IDEncomenda DESC
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

function gastos_compras_periodos(PDO $pdo, int $idUtilizador, string $periodo): array {
    if ($periodo === 'semanal') {
        $periodoSql = "CONCAT(FLOOR(YEARWEEK(DataCriacao, 3) / 100), '-W', LPAD(MOD(YEARWEEK(DataCriacao, 3), 100), 2, '0'))";
        $agrupamento = 'YEARWEEK(DataCriacao, 3)';
    } else {
        $periodoSql = "DATE_FORMAT(DataCriacao, '%Y-%m')";
        $agrupamento = 'YEAR(DataCriacao), MONTH(DataCriacao)';
    }

    $stmt = $pdo->prepare(
        'SELECT ' . $periodoSql . ' AS Periodo, SUM(Total) AS TotalGasto, COUNT(*) AS TotalCompras
         FROM encomenda
         WHERE IDUtilizador = ? AND EstadoPagamento = \'pago\' AND Estado = \'entregue\'
         GROUP BY ' . $agrupamento . '
         ORDER BY MAX(DataCriacao) DESC'
    );
    $stmt->execute([$idUtilizador]);

    return $stmt->fetchAll();
}
