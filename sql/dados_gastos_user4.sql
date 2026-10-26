-- Dados fictícios para testar o painel de gastos do utilizador 4.
-- Reexecutar atualiza os valores existentes sem criar duplicados.
INSERT INTO GastoCompra (IDUtilizador, ReferenciaCompra, ValorTotal, DataCompra)
VALUES
    (4, 'TEST-U4-2026-001', 22.50, '2026-09-01 12:00:00'),
    (4, 'TEST-U4-2026-002', 35.90, '2026-09-16 18:30:00'),
    (4, 'TEST-U4-2026-003', 18.75, '2026-09-22 11:15:00'),
    (4, 'TEST-U4-2026-004', 42.30, '2026-10-03 14:10:00'),
    (4, 'TEST-U4-2026-005', 26.40, '2026-10-17 19:45:00'),
    (4, 'TEST-U4-2026-006', 31.80, '2026-10-25 10:20:00')
ON DUPLICATE KEY UPDATE
    ValorTotal = VALUES(ValorTotal),
    DataCompra = VALUES(DataCompra);

-- Confirma que os registos estão na tabela consultada pelo perfil.
SELECT IDGastoCompra, IDUtilizador, ReferenciaCompra, ValorTotal, DataCompra
FROM GastoCompra
WHERE IDUtilizador = 4
  AND ReferenciaCompra LIKE 'TEST-U4-2026-%'
ORDER BY DataCompra DESC;
