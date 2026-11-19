<?php

function agendamentos_compras_preparar(PDO $pdo): void {
    static $preparada = false;
    if ($preparada) {
        return;
    }

    listas_compras_preparar($pdo);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS AgendamentoCompra (
            IDAgendamento INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            IDUtilizador INT UNSIGNED NOT NULL,
            IDListaCompra INT UNSIGNED NOT NULL,
            IDMorada INT UNSIGNED DEFAULT NULL,
            Frequencia ENUM(\'semanal\', \'mensal\') NOT NULL,
            DataProxima DATE NOT NULL,
            DiaDoMes TINYINT UNSIGNED NOT NULL,
            NomeDestinatario VARCHAR(100) NOT NULL,
            Telemovel VARCHAR(20) DEFAULT NULL,
            Morada VARCHAR(255) NOT NULL,
            CodigoPostal CHAR(8) NOT NULL,
            Localidade VARCHAR(80) NOT NULL,
            ConcelhoRegiao VARCHAR(60) NOT NULL,
            Distrito VARCHAR(60) NOT NULL,
            Pais VARCHAR(60) NOT NULL,
            NifFatura CHAR(9) DEFAULT NULL,
            MetodoPagamento ENUM(\'mbway\', \'multibanco\', \'cartao\', \'paypal\', \'pagamento_entrega\') NOT NULL,
            Estado ENUM(\'ativo\', \'aguardar_aprovacao\', \'aguardar_confirmacao\', \'cancelado\') NOT NULL DEFAULT \'ativo\',
            ComprasDesdeConfirmacao TINYINT UNSIGNED NOT NULL DEFAULT 0,
            NumeroUltimaEncomenda VARCHAR(20) DEFAULT NULL,
            UltimoResultado VARCHAR(500) DEFAULT NULL,
            DataUltimaExecucao DATETIME DEFAULT NULL,
            DataCriacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            DataAlterado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_agendamento_execucao (Estado, DataProxima),
            INDEX idx_agendamento_utilizador (IDUtilizador, Estado),
            CONSTRAINT fk_agendamento_utilizador
                FOREIGN KEY (IDUtilizador) REFERENCES Utilizador (IDUtilizador)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_agendamento_lista
                FOREIGN KEY (IDListaCompra) REFERENCES ListaCompra (IDListaCompra)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_agendamento_morada
                FOREIGN KEY (IDMorada) REFERENCES Morada (IDMorada)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $stmt = $pdo->query("SHOW COLUMNS FROM AgendamentoCompra LIKE 'Estado'");
    $colunaEstado = $stmt->fetch();
    if (!$colunaEstado) {
        throw new RuntimeException('Não foi possível verificar o estado dos agendamentos.');
    }
    if (strpos($colunaEstado['Type'], 'aguardar_aprovacao') === false) {
        $pdo->exec(
            'ALTER TABLE AgendamentoCompra
             MODIFY Estado ENUM(\'ativo\', \'aguardar_aprovacao\', \'aguardar_confirmacao\', \'cancelado\')
             NOT NULL DEFAULT \'ativo\''
        );
    }

    $preparada = true;
}

function agendamento_proxima_data(string $dataAtual, string $frequencia, int $diaDoMes): string {
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dataAtual);
    if (!$data || $data->format('Y-m-d') !== $dataAtual) {
        throw new InvalidArgumentException('Data de agendamento inválida.');
    }

    if ($frequencia === 'semanal') {
        return $data->modify('+7 days')->format('Y-m-d');
    }
    if ($frequencia !== 'mensal' || $diaDoMes < 1 || $diaDoMes > 31) {
        throw new InvalidArgumentException('Frequência de agendamento inválida.');
    }

    $proximoMes = $data->modify('first day of next month');
    $diaAplicavel = min($diaDoMes, (int) $proximoMes->format('t'));

    return $proximoMes->setDate(
        (int) $proximoMes->format('Y'),
        (int) $proximoMes->format('m'),
        $diaAplicavel
    )->format('Y-m-d');
}

function agendamento_data_futura(string $dataAtual, string $frequencia, int $diaDoMes, string $hoje): string {
    $data = $dataAtual;
    while ($data <= $hoje) {
        $data = agendamento_proxima_data($data, $frequencia, $diaDoMes);
    }

    return $data;
}

function agendamento_previsualizar(PDO $pdo, int $idLista, bool $bloquearStock = false): array {
    $sql = 'SELECT il.Quantidade, p.IDProduto, p.SKU, p.Nome, p.Unidade, p.Preco, p.PrecoPromocional,
                   p.TaxaIVA, p.StockQuantidade, p.Ativo
            FROM ItemListaCompra il
            JOIN Produto p ON p.IDProduto = il.IDProduto
            WHERE il.IDListaCompra = ?
            ORDER BY p.IDProduto';
    if ($bloquearStock) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idLista]);
    $itens = $stmt->fetchAll();
    $subtotal = 0.0;
    $disponivel = (bool) $itens;

    foreach ($itens as &$item) {
        $quantidade = (int) $item['Quantidade'];
        $precoBase = (float) $item['Preco'];
        $precoPromocional = (float) ($item['PrecoPromocional'] ?? 0);
        $item['Quantidade'] = $quantidade;
        $item['PrecoAtual'] = $precoPromocional > 0 && $precoPromocional < $precoBase
            ? $precoPromocional
            : $precoBase;
        $item['TotalLinha'] = round($item['PrecoAtual'] * $quantidade, 2);
        $item['Disponivel'] = (int) $item['Ativo'] === 1
            && $quantidade >= 1
            && $quantidade <= 99
            && $quantidade <= (int) $item['StockQuantidade'];
        if (!$item['Disponivel']) {
            $disponivel = false;
        }
        $subtotal += $item['TotalLinha'];
    }
    unset($item);

    $custoEnvio = $subtotal > 35 ? 0.0 : 3.99;
    $conteudo = [];
    foreach ($itens as $item) {
        $conteudo[] = [
            (int) $item['IDProduto'],
            (string) $item['SKU'],
            (string) $item['Nome'],
            (string) ($item['Unidade'] ?? ''),
            (int) $item['Quantidade'],
            number_format((float) $item['PrecoAtual'], 2, '.', ''),
            number_format((float) $item['TaxaIVA'], 2, '.', ''),
            (bool) $item['Disponivel'],
        ];
    }
    $json = json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    if ($json === false) {
        throw new RuntimeException('Não foi possível preparar a confirmação dos artigos.');
    }

    return [
        'itens' => $itens,
        'subtotal' => $subtotal,
        'custo_envio' => $custoEnvio,
        'total' => round($subtotal + $custoEnvio, 2),
        'disponivel' => $disponivel,
        'hash' => hash('sha256', $json),
    ];
}

function agendamento_criar_encomenda(PDO $pdo, array $agendamento, array $previsao, string $dataEntrega): string {
    if (!$previsao['itens'] || !$previsao['disponivel']) {
        throw new RuntimeException('Os artigos ou o stock mudaram. Reveja a lista antes de confirmar.');
    }

    $numeroEncomenda = 'OC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
    $pdo->prepare(
        'INSERT INTO Encomenda
            (NumeroEncomenda, IDUtilizador, Estado, EntregaNome, EntregaTelemovel,
             EntregaMorada, EntregaCodigoPostal, EntregaLocalidade, EntregaConcelhoRegiao,
             EntregaDistrito, EntregaPais, NifFatura, Subtotal, CustoEnvio, Total,
             MetodoPagamento, EstadoPagamento, DataPagamento, NotasCliente, DataEntregaPrevista)
         VALUES (?, ?, \'pedido\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pendente\', NULL, ?, ?)'
    )->execute([
        $numeroEncomenda,
        (int) $agendamento['IDUtilizador'],
        $agendamento['NomeDestinatario'],
        $agendamento['Telemovel'],
        trim($agendamento['Morada']),
        $agendamento['CodigoPostal'],
        $agendamento['Localidade'],
        $agendamento['ConcelhoRegiao'],
        $agendamento['Distrito'],
        $agendamento['Pais'],
        $agendamento['NifFatura'],
        number_format($previsao['subtotal'], 2, '.', ''),
        number_format($previsao['custo_envio'], 2, '.', ''),
        number_format($previsao['total'], 2, '.', ''),
        $agendamento['MetodoPagamento'],
        'Encomenda recorrente confirmada pelo cliente; pagamento por confirmar.',
        $dataEntrega,
    ]);
    $idEncomenda = (int) $pdo->lastInsertId();

    $stmtItem = $pdo->prepare(
        'INSERT INTO ItemEncomenda
            (IDEncomenda, IDProduto, SKU, NomeProduto, Quantidade, PrecoUnitario, TaxaIVA)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmtStock = $pdo->prepare(
        'UPDATE Produto
         SET StockQuantidade = StockQuantidade - ?
         WHERE IDProduto = ? AND Ativo = 1 AND StockQuantidade >= ?'
    );
    $stmtMovimento = $pdo->prepare(
        'INSERT INTO MovimentoStock
            (IDProduto, Tipo, Quantidade, StockApos, IDEncomenda, IDUtilizador, Nota)
         VALUES (?, \'venda\', ?, ?, ?, ?, ?)'
    );
    foreach ($previsao['itens'] as $item) {
        $quantidade = (int) $item['Quantidade'];
        $stmtStock->execute([$quantidade, $item['IDProduto'], $quantidade]);
        if ($stmtStock->rowCount() !== 1) {
            throw new RuntimeException('O stock de um produto mudou durante a confirmação.');
        }

        $stmtItem->execute([
            $idEncomenda,
            $item['IDProduto'],
            $item['SKU'],
            $item['Nome'],
            $quantidade,
            number_format($item['PrecoAtual'], 2, '.', ''),
            number_format((float) $item['TaxaIVA'], 2, '.', ''),
        ]);
        $stockApos = (int) $item['StockQuantidade'] - $quantidade;
        $stmtMovimento->execute([
            $item['IDProduto'],
            -$quantidade,
            $stockApos,
            $idEncomenda,
            (int) $agendamento['IDUtilizador'],
            'Venda recorrente confirmada na encomenda ' . $numeroEncomenda,
        ]);
    }

    $pdo->prepare(
        'INSERT INTO HistoricoEstadoEncomenda
            (IDEncomenda, EstadoAnterior, EstadoNovo, IDUtilizador, Nota)
         VALUES (?, NULL, \'pedido\', ?, ?)'
    )->execute([
        $idEncomenda,
        (int) $agendamento['IDUtilizador'],
        'Encomenda recorrente criada após confirmação do cliente.',
    ]);

    return $numeroEncomenda;
}

function agendamentos_processar(PDO $pdo, string $hoje): array {
    $stmt = $pdo->prepare(
        'SELECT IDAgendamento
         FROM AgendamentoCompra
         WHERE Estado = \'ativo\' AND DataProxima <= ?
         ORDER BY DataProxima, IDAgendamento'
    );
    $stmt->execute([$hoje]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $resultado = ['aguardam_aprovacao' => 0, 'ignoradas' => 0];

    foreach ($ids as $idAgendamento) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM AgendamentoCompra
                 WHERE IDAgendamento = ? AND Estado = \'ativo\'
                 FOR UPDATE'
            );
            $stmt->execute([$idAgendamento]);
            $agendamento = $stmt->fetch();
            if (!$agendamento || $agendamento['DataProxima'] > $hoje) {
                $pdo->commit();
                continue;
            }

            $stmt = $pdo->prepare('SELECT Ativo FROM Utilizador WHERE IDUtilizador = ? FOR UPDATE');
            $stmt->execute([(int) $agendamento['IDUtilizador']]);
            if (!(int) $stmt->fetchColumn()) {
                $pdo->prepare(
                    'UPDATE AgendamentoCompra
                     SET Estado = \'cancelado\', UltimoResultado = \'Agendamento cancelado: conta de cliente inativa.\',
                         DataUltimaExecucao = NOW()
                     WHERE IDAgendamento = ?'
                )->execute([$idAgendamento]);
                $resultado['ignoradas']++;
                $pdo->commit();
                continue;
            }

            $pdo->prepare(
                'UPDATE AgendamentoCompra
                 SET Estado = \'aguardar_aprovacao\',
                     UltimoResultado = \'A ocorrência chegou à data prevista e aguarda a sua confirmação dos artigos, quantidades e preços.\',
                     DataUltimaExecucao = NOW()
                 WHERE IDAgendamento = ?'
            )->execute([$idAgendamento]);
            $resultado['aguardam_aprovacao']++;
            $pdo->commit();
        } catch (Throwable $erro) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException(
                'Falha ao preparar o agendamento ' . $idAgendamento . ': ' . $erro->getMessage(),
                0,
                $erro
            );
        }
    }

    return $resultado;
}
