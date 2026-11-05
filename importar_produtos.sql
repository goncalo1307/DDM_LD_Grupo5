-- Importa os 18 produtos e as respetivas imagens para uma base o_cantinho
-- já criada com as tabelas Categoria, Produto e ProdutoImagem.
-- Os preços e stocks são valores iniciais de demonstração.

START TRANSACTION;

-- Mantém a referência do produto Banana existente no dump antigo.
UPDATE Produto
SET SKU = 'FRU-BAN-001'
WHERE IDProduto = 1
  AND LOWER(Nome) = 'banana'
  AND SKU = 'CAM-AZL-M';

INSERT INTO Produto
    (SKU, Nome, Descricao, Marca, IDCategoria, IDFornecedor, Preco, PrecoPromocional,
     TaxaIVA, Unidade, StockQuantidade, StockMinimo, Alergenios, Imagem, Destaque, Ativo)
VALUES
    ('FRU-BAN-001', 'Banana', 'Banana fresca.', NULL, 9, 1, 4.00, 3.00, 6.00, 'kg', 11, 5, NULL, 'imagens/produtos/legumes-frutas/frutas/banana/fruta-banana-catalago.jpg', 0, 1),
    ('FRU-PER-001', 'Pera', 'Pera fresca.', NULL, 9, 1, 2.49, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/legumes-frutas/frutas/pera/fruta-pera-catalago.jpg', 0, 1),
    ('LEG-CEB-001', 'Cebola', 'Cebola fresca.', NULL, 10, 1, 1.49, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/legumes-frutas/legumes/legumes-cebola-catalago.jpg', 0, 1),
    ('LEG-TOM-001', 'Tomate', 'Tomate fresco.', NULL, 10, 1, 2.49, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/legumes-frutas/legumes/legumes-tomate-catalago.jpg', 0, 1),
    ('MER-ARR-001', 'Arroz', 'Arroz.', NULL, 11, 3, 1.79, NULL, 6.00, '1 kg', 20, 5, NULL, 'imagens/produtos/mercearia/arroz-massa-farinha/arroz/mercearia-arroz-catalago.jpg', 0, 1),
    ('MER-FAR-001', 'Farinha', 'Farinha de trigo.', NULL, 11, 3, 1.09, NULL, 6.00, '1 kg', 20, 5, 'Glúten', 'imagens/produtos/mercearia/arroz-massa-farinha/farinhas/mercearia-farinha-catalago.jpg', 0, 1),
    ('MER-MAS-001', 'Massa', 'Massa seca.', NULL, 11, 3, 0.99, NULL, 6.00, '500 g', 20, 5, 'Glúten', 'imagens/produtos/mercearia/arroz-massa-farinha/massa/mercearia-massa-catalago.jpg', 0, 1),
    ('MER-AZE-001', 'Azeite', 'Azeite.', NULL, 12, 3, 6.99, NULL, 6.00, '750 ml', 20, 5, NULL, 'imagens/produtos/mercearia/azeite-oleo/azeite/mercearia-azeite-catalago.jpg', 0, 1),
    ('MER-OLE-001', 'Óleo', 'Óleo alimentar.', NULL, 12, 3, 2.49, NULL, 6.00, '1 l', 20, 5, NULL, 'imagens/produtos/mercearia/azeite-oleo/oleo/mercearia-oleo-catalago.jpg', 0, 1),
    ('PEI-SAL-CNG-001', 'Salmão congelado', 'Salmão congelado.', NULL, 8, NULL, 9.99, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/peixe/peixe congelado/salmao/peixe-salmao_congelado-catalago.jpg', 0, 1),
    ('PEI-DOU-CNG-001', 'Dourada congelada', 'Dourada congelada.', NULL, 8, NULL, 7.99, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/peixe/peixe congelado/dourada/peixe-dourada_congelado-catalago.jpg', 0, 1),
    ('PEI-SAL-FRE-001', 'Salmão fresco', 'Salmão fresco.', NULL, 4, NULL, 18.99, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/peixe/peixe fresco/salmao/peixe-salmao_fresco-catalago.jpg', 0, 1),
    ('PEI-DOU-FRE-001', 'Dourada fresca', 'Dourada fresca.', NULL, 4, NULL, 12.99, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/peixe/peixe fresco/dourada/peixe-dourada_fresco-catalago.jpg', 0, 1),
    ('TAL-FRA-001', 'Frango', 'Frango.', NULL, 3, 1, 5.99, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/carne/frango/img-frango-catalago.jpg', 0, 1),
    ('TAL-PER-001', 'Peru', 'Peru.', NULL, 3, 1, 8.49, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/carne/peru/img-peru-catalago.jpg', 0, 1),
    ('TAL-POR-001', 'Porco', 'Carne de porco.', NULL, 3, 1, 6.99, NULL, 6.00, 'kg', 20, 5, NULL, 'imagens/produtos/carne/porco/img-porco-catalago.jpg', 0, 1),
    ('BEB-AGC-001', 'Água com gás', 'Água com gás.', NULL, 13, 3, 0.79, NULL, 6.00, '1,5 l', 20, 5, NULL, 'imagens/produtos/bebidas/aguas/agua com gas/bebidas-agua_comgas-catalago.jpg', 0, 1),
    ('BEB-AGS-001', 'Água sem gás', 'Água sem gás.', NULL, 13, 3, 0.59, NULL, 6.00, '1,5 l', 20, 5, NULL, 'imagens/produtos/bebidas/aguas/agua sem gas/bebidas-agua_semgas-catalago.jpg', 0, 1)
ON DUPLICATE KEY UPDATE
    Nome = VALUES(Nome),
    Descricao = VALUES(Descricao),
    Marca = VALUES(Marca),
    IDCategoria = VALUES(IDCategoria),
    IDFornecedor = VALUES(IDFornecedor),
    Preco = VALUES(Preco),
    PrecoPromocional = VALUES(PrecoPromocional),
    TaxaIVA = VALUES(TaxaIVA),
    Unidade = VALUES(Unidade),
    StockQuantidade = VALUES(StockQuantidade),
    StockMinimo = VALUES(StockMinimo),
    Alergenios = VALUES(Alergenios),
    Imagem = VALUES(Imagem),
    Destaque = VALUES(Destaque),
    Ativo = VALUES(Ativo);

INSERT INTO ProdutoImagem (IDProduto, Caminho, Ordem)
SELECT Produto.IDProduto, Imagens.Caminho, Imagens.Ordem
FROM (
    SELECT 'FRU-BAN-001' AS SKU, 'imagens/produtos/legumes-frutas/frutas/banana/fruta-banana-1.jpg' AS Caminho, 0 AS Ordem
    UNION ALL SELECT 'FRU-BAN-001', 'imagens/produtos/legumes-frutas/frutas/banana/fruta-banana-2.jpg', 1
    UNION ALL SELECT 'FRU-PER-001', 'imagens/produtos/legumes-frutas/frutas/pera/fruta-pera-1.jpg', 0
    UNION ALL SELECT 'LEG-CEB-001', 'imagens/produtos/legumes-frutas/legumes/legumes-cebola-1.jpg', 0
    UNION ALL SELECT 'LEG-TOM-001', 'imagens/produtos/legumes-frutas/legumes/legumes-tomate-1.jpg', 0
    UNION ALL SELECT 'LEG-TOM-001', 'imagens/produtos/legumes-frutas/legumes/legumes-tomate-2.jpg', 1
    UNION ALL SELECT 'MER-ARR-001', 'imagens/produtos/mercearia/arroz-massa-farinha/arroz/mercearia-arroz-1.jpg', 0
    UNION ALL SELECT 'MER-FAR-001', 'imagens/produtos/mercearia/arroz-massa-farinha/farinhas/mercearia-farinha-1.jpg', 0
    UNION ALL SELECT 'MER-MAS-001', 'imagens/produtos/mercearia/arroz-massa-farinha/massa/mercearia-massa-1.jpg', 0
    UNION ALL SELECT 'MER-AZE-001', 'imagens/produtos/mercearia/azeite-oleo/azeite/mercearia-azeite-1.jpg', 0
    UNION ALL SELECT 'MER-OLE-001', 'imagens/produtos/mercearia/azeite-oleo/oleo/mercearia-oleo-1.jpg', 0
    UNION ALL SELECT 'PEI-SAL-CNG-001', 'imagens/produtos/peixe/peixe congelado/salmao/peixe-salmao_congelado-1.jpg', 0
    UNION ALL SELECT 'PEI-SAL-CNG-001', 'imagens/produtos/peixe/peixe congelado/salmao/peixe-salmao_congelado-2.jpg', 1
    UNION ALL SELECT 'PEI-DOU-CNG-001', 'imagens/produtos/peixe/peixe congelado/dourada/peixe-dourada_congelado-1.jpg', 0
    UNION ALL SELECT 'PEI-SAL-FRE-001', 'imagens/produtos/peixe/peixe fresco/salmao/peixe-salmao_fresco-1.jpg', 0
    UNION ALL SELECT 'PEI-DOU-FRE-001', 'imagens/produtos/peixe/peixe fresco/dourada/peixe-dourada_fresco-1.jpg', 0
    UNION ALL SELECT 'TAL-FRA-001', 'imagens/produtos/carne/frango/img-frango-1.jpg', 0
    UNION ALL SELECT 'TAL-PER-001', 'imagens/produtos/carne/peru/img-peru-1.jpg', 0
    UNION ALL SELECT 'TAL-PER-001', 'imagens/produtos/carne/peru/img-peru-2.jpg', 1
    UNION ALL SELECT 'TAL-PER-001', 'imagens/produtos/carne/peru/img-peru-3.jpg', 2
    UNION ALL SELECT 'TAL-POR-001', 'imagens/produtos/carne/porco/img-porco-1.jpg', 0
    UNION ALL SELECT 'BEB-AGC-001', 'imagens/produtos/bebidas/aguas/agua com gas/bebidas-agua_comgas-1.jpg', 0
    UNION ALL SELECT 'BEB-AGC-001', 'imagens/produtos/bebidas/aguas/agua com gas/bebidas-agua_comgas-2.jpg', 1
    UNION ALL SELECT 'BEB-AGS-001', 'imagens/produtos/bebidas/aguas/agua sem gas/bebidas-agua_semgas-1.jpg', 0
) AS Imagens
JOIN Produto ON Produto.SKU = Imagens.SKU
WHERE NOT EXISTS (
    SELECT 1
    FROM ProdutoImagem
    WHERE ProdutoImagem.IDProduto = Produto.IDProduto
      AND ProdutoImagem.Caminho = Imagens.Caminho
);

COMMIT;
