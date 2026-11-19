<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/conta/ligacao.php';
require __DIR__ . '/conta/listas_compras.php';
require __DIR__ . '/conta/agendamento_compras.php';

agendamentos_compras_preparar($pdo);

try {
    $resultado = agendamentos_processar($pdo, date('Y-m-d'));
    printf(
        "Agendamentos processados. Ocorrencias a aguardar aprovacao: %d; ignoradas: %d.%s",
        $resultado['aguardam_aprovacao'],
        $resultado['ignoradas'],
        PHP_EOL
    );
} catch (Throwable $erro) {
    fwrite(STDERR, $erro->getMessage() . PHP_EOL);
    exit(1);
}
