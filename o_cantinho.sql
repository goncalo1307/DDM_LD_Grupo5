-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 27-Out-2026 às 02:59
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `o_cantinho`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `carrinho`
--

CREATE TABLE `carrinho` (
  `IDCarrinho` int(10) UNSIGNED NOT NULL,
  `IDUtilizador` int(10) UNSIGNED NOT NULL,
  `DataCriacao` datetime NOT NULL DEFAULT current_timestamp(),
  `DataAlterado` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `carrinho`
--

INSERT INTO `carrinho` (`IDCarrinho`, `IDUtilizador`, `DataCriacao`, `DataAlterado`) VALUES
(1, 3, '2026-10-21 10:49:49', '2026-10-21 10:49:49'),
(2, 4, '2026-10-24 09:29:06', '2026-10-26 23:09:36');

-- --------------------------------------------------------

--
-- Estrutura da tabela `categoria`
--

CREATE TABLE `categoria` (
  `IDCategoria` int(10) UNSIGNED NOT NULL,
  `IDCategoriaPai` int(10) UNSIGNED DEFAULT NULL,
  `Nome` varchar(80) NOT NULL,
  `Slug` varchar(100) NOT NULL,
  `Descricao` varchar(255) DEFAULT NULL,
  `Ordem` smallint(6) NOT NULL DEFAULT 0,
  `Ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `categoria`
--

INSERT INTO `categoria` (`IDCategoria`, `IDCategoriaPai`, `Nome`, `Slug`, `Descricao`, `Ordem`, `Ativo`) VALUES
(1, NULL, 'Frutas e Legumes', 'frutas-e-legumes', NULL, 1, 1),
(2, NULL, 'Padaria e Pastelaria', 'padaria-e-pastelaria', NULL, 2, 1),
(3, NULL, 'Talho', 'talho', NULL, 3, 1),
(4, NULL, 'Peixaria', 'peixaria', NULL, 4, 1),
(5, NULL, 'Laticínios e Ovos', 'laticinios-e-ovos', NULL, 5, 1),
(6, NULL, 'Mercearia', 'mercearia', NULL, 6, 1),
(7, NULL, 'Bebidas', 'bebidas', NULL, 7, 1),
(8, NULL, 'Congelados', 'congelados', NULL, 8, 1),
(9, 1, 'Frutas', 'frutas', NULL, 1, 1),
(10, 1, 'Legumes', 'legumes', NULL, 2, 1),
(11, 6, 'Massas e Arroz', 'massas-e-arroz', NULL, 1, 1),
(12, 6, 'Azeite e Óleos', 'azeite-e-oleos', NULL, 2, 1),
(13, 7, 'Águas', 'aguas', NULL, 1, 1),
(14, 7, 'Vinhos', 'vinhos', NULL, 2, 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `encomenda`
--

CREATE TABLE `encomenda` (
  `IDEncomenda` int(10) UNSIGNED NOT NULL,
  `NumeroEncomenda` varchar(20) NOT NULL,
  `IDUtilizador` int(10) UNSIGNED NOT NULL,
  `Estado` enum('pedido','em_preparacao','a_entregar','entregue','cancelada') NOT NULL DEFAULT 'pedido',
  `EntregaNome` varchar(100) NOT NULL,
  `EntregaTelemovel` varchar(20) DEFAULT NULL,
  `EntregaMorada` varchar(255) NOT NULL,
  `EntregaCodigoPostal` char(8) NOT NULL,
  `EntregaLocalidade` varchar(80) NOT NULL,
  `EntregaConcelhoRegiao` varchar(60) NOT NULL,
  `EntregaDistrito` varchar(60) NOT NULL,
  `EntregaPais` varchar(60) NOT NULL DEFAULT 'Portugal',
  `NifFatura` char(9) DEFAULT NULL,
  `Subtotal` decimal(10,2) NOT NULL,
  `CustoEnvio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Total` decimal(10,2) NOT NULL,
  `MetodoPagamento` enum('mbway','multibanco','cartao','paypal','pagamento_entrega') NOT NULL,
  `EstadoPagamento` enum('pendente','pago','falhado','reembolsado') NOT NULL DEFAULT 'pendente',
  `DataPagamento` datetime DEFAULT NULL,
  `NotasCliente` varchar(500) DEFAULT NULL,
  `NotasInternas` varchar(500) DEFAULT NULL,
  `DataEntregaPrevista` date DEFAULT NULL,
  `DataEntrega` datetime DEFAULT NULL,
  `DataCriacao` datetime NOT NULL DEFAULT current_timestamp(),
  `IDAlteradoPor` int(10) UNSIGNED DEFAULT NULL,
  `DataAlterado` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Extraindo dados da tabela `encomenda`
--

INSERT INTO `encomenda` (`IDEncomenda`, `NumeroEncomenda`, `IDUtilizador`, `Estado`, `EntregaNome`, `EntregaTelemovel`, `EntregaMorada`, `EntregaCodigoPostal`, `EntregaLocalidade`, `EntregaConcelhoRegiao`, `EntregaDistrito`, `EntregaPais`, `NifFatura`, `Subtotal`, `CustoEnvio`, `Total`, `MetodoPagamento`, `EstadoPagamento`, `DataPagamento`, `NotasCliente`, `NotasInternas`, `DataEntregaPrevista`, `DataEntrega`, `DataCriacao`, `IDAlteradoPor`, `DataAlterado`) VALUES
(1, 'OC-2026-000001', 2, 'pedido', 'Cliente Teste', '910000002', 'Rua das Flores, 25, 3º Esq', '1200-195', 'Lisboa', 'Lisboa', 'Lisboa', 'Portugal', NULL, 5.82, 3.99, 9.81, 'mbway', 'pendente', NULL, 'Tocar duas vezes.', NULL, '2026-10-22', NULL, '2026-10-21 08:10:00', NULL, '2026-10-21 08:50:08'),
(2, 'OC-2026-000002', 2, 'a_entregar', 'Cliente Teste', '910000002', 'Rua das Flores, 25, 3º Esq', '1200-195', 'Lisboa', 'Lisboa', 'Lisboa', 'Portugal', NULL, 13.16, 3.99, 17.15, 'multibanco', 'pago', '2026-10-20 10:30:00', NULL, NULL, '2026-10-21', NULL, '2026-10-20 09:45:00', 1, '2026-10-21 08:50:08'),
(3, 'OC-2026-000003', 2, 'entregue', 'Cliente Teste', '910000002', 'Rua das Flores, 25, 3º Esq', '1200-195', 'Lisboa', 'Lisboa', 'Lisboa', 'Portugal', NULL, 20.64, 3.99, 24.63, 'cartao', 'pago', '2026-10-17 18:20:00', NULL, NULL, '2026-10-19', '2026-10-19 11:40:00', '2026-10-17 18:15:00', 1, '2026-10-21 08:50:08'),
(4, 'OC-2026-8766EF15EC61', 4, 'pedido', 'Gonçalo Gonçalves', '+351961161087', 'RUA DOS NIGGAS, 69, 67', '3434-344', 'O CARALHO', 'PUTAS', 'CU DE JDAS', 'Portugal', '123213311', 6.00, 3.99, 9.99, 'mbway', 'pendente', NULL, NULL, NULL, NULL, NULL, '2026-10-26 23:09:36', NULL, '2026-10-26 23:09:36');

-- --------------------------------------------------------

--
-- Estrutura da tabela `fornecedor`
--

CREATE TABLE `fornecedor` (
  `IDFornecedor` int(10) UNSIGNED NOT NULL,
  `Nome` varchar(120) NOT NULL,
  `NIF` char(9) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Telefone` varchar(20) DEFAULT NULL,
  `Endereco` varchar(200) DEFAULT NULL,
  `CodigoPostal` char(8) DEFAULT NULL,
  `Localidade` varchar(80) DEFAULT NULL,
  `Pais` varchar(60) NOT NULL DEFAULT 'Portugal',
  `Notas` varchar(500) DEFAULT NULL,
  `Ativo` tinyint(1) NOT NULL DEFAULT 1,
  `DataCriacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `fornecedor`
--

INSERT INTO `fornecedor` (`IDFornecedor`, `Nome`, `NIF`, `Email`, `Telefone`, `Endereco`, `CodigoPostal`, `Localidade`, `Pais`, `Notas`, `Ativo`, `DataCriacao`) VALUES
(1, 'Hortas do Vale, Lda.', NULL, 'comercial@hortasdovale.local', '210000001', NULL, NULL, 'Torres Vedras', 'Portugal', NULL, 1, '2026-10-21 08:50:07'),
(2, 'Lacticínios da Serra, Lda.', NULL, 'encomendas@laticiniosserra.local', '210000002', NULL, NULL, 'Seia', 'Portugal', NULL, 1, '2026-10-21 08:50:07'),
(3, 'Mercearia Atlântico, S.A.', NULL, 'vendas@merceariaatlantico.local', '210000003', NULL, NULL, 'Setúbal', 'Portugal', NULL, 1, '2026-10-21 08:50:07');

-- --------------------------------------------------------

--
-- Estrutura da tabela `gastocompra`
--

CREATE TABLE `gastocompra` (
  `IDGastoCompra` int(11) NOT NULL,
  `IDUtilizador` int(11) NOT NULL,
  `ReferenciaCompra` varchar(100) DEFAULT NULL,
  `ValorTotal` decimal(10,2) UNSIGNED NOT NULL,
  `DataCompra` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `gastocompra`
--

INSERT INTO `gastocompra` (`IDGastoCompra`, `IDUtilizador`, `ReferenciaCompra`, `ValorTotal`, `DataCompra`) VALUES
(1, 4, 'TEST-U4-2026-001', 22.50, '2026-09-01 12:00:00'),
(2, 4, 'TEST-U4-2026-002', 35.90, '2026-09-16 18:30:00'),
(3, 4, 'TEST-U4-2026-003', 18.75, '2026-09-22 11:15:00'),
(4, 4, 'TEST-U4-2026-004', 42.30, '2026-10-03 14:10:00'),
(5, 4, 'TEST-U4-2026-005', 26.40, '2026-10-17 19:45:00'),
(6, 4, 'TEST-U4-2026-006', 31.80, '2026-10-25 10:20:00'),
(8, 4, 'OC-2026-8766EF15EC61', 9.99, '2026-10-26 23:09:36');

-- --------------------------------------------------------

--
-- Estrutura da tabela `historicoestadoencomenda`
--

CREATE TABLE `historicoestadoencomenda` (
  `IDHistorico` int(10) UNSIGNED NOT NULL,
  `IDEncomenda` int(10) UNSIGNED NOT NULL,
  `EstadoAnterior` enum('pedido','em_preparacao','a_entregar','entregue','cancelada') DEFAULT NULL,
  `EstadoNovo` enum('pedido','em_preparacao','a_entregar','entregue','cancelada') NOT NULL,
  `IDUtilizador` int(10) UNSIGNED DEFAULT NULL,
  `Nota` varchar(255) DEFAULT NULL,
  `DataAlteracao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `historicoestadoencomenda`
--

INSERT INTO `historicoestadoencomenda` (`IDHistorico`, `IDEncomenda`, `EstadoAnterior`, `EstadoNovo`, `IDUtilizador`, `Nota`, `DataAlteracao`) VALUES
(1, 1, NULL, 'pedido', 2, 'Encomenda criada', '2026-10-21 08:10:00'),
(2, 2, NULL, 'pedido', 2, 'Encomenda criada', '2026-10-20 09:45:00'),
(3, 2, 'pedido', 'em_preparacao', 1, 'Pagamento confirmado', '2026-10-20 10:35:00'),
(4, 2, 'em_preparacao', 'a_entregar', 1, 'Saiu para entrega', '2026-10-21 07:50:00'),
(5, 3, NULL, 'pedido', 2, 'Encomenda criada', '2026-10-17 18:15:00'),
(6, 3, 'pedido', 'em_preparacao', 1, NULL, '2026-10-18 09:00:00'),
(7, 3, 'em_preparacao', 'a_entregar', 1, NULL, '2026-10-19 08:30:00'),
(8, 3, 'a_entregar', 'entregue', 4, 'Entregue ao cliente', '2026-10-19 11:40:00'),
(9, 4, NULL, 'pedido', 4, 'Encomenda criada pelo checkout.', '2026-10-26 23:09:36');

-- --------------------------------------------------------

--
-- Estrutura da tabela `itemcarrinho`
--

CREATE TABLE `itemcarrinho` (
  `IDItemCarrinho` int(10) UNSIGNED NOT NULL,
  `IDCarrinho` int(10) UNSIGNED NOT NULL,
  `IDProduto` int(10) UNSIGNED NOT NULL,
  `Quantidade` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `DataAdicionado` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `itemencomenda`
--

CREATE TABLE `itemencomenda` (
  `IDItemEncomenda` int(10) UNSIGNED NOT NULL,
  `IDEncomenda` int(10) UNSIGNED NOT NULL,
  `IDProduto` int(10) UNSIGNED NOT NULL,
  `SKU` varchar(40) NOT NULL,
  `NomeProduto` varchar(150) NOT NULL,
  `Quantidade` int(10) UNSIGNED NOT NULL,
  `PrecoUnitario` decimal(10,2) NOT NULL,
  `TaxaIVA` decimal(4,2) NOT NULL
) ;

--
-- Extraindo dados da tabela `itemencomenda`
--

INSERT INTO `itemencomenda` (`IDItemEncomenda`, `IDEncomenda`, `IDProduto`, `SKU`, `NomeProduto`, `Quantidade`, `PrecoUnitario`, `TaxaIVA`) VALUES
(11, 4, 1, 'CAM-AZL-M', 'banana', 2, 3.00, 6.00);

-- --------------------------------------------------------

--
-- Estrutura da tabela `morada`
--

CREATE TABLE `morada` (
  `IDMorada` int(10) UNSIGNED NOT NULL,
  `IDUtilizador` int(10) UNSIGNED NOT NULL,
  `Designacao` varchar(50) NOT NULL DEFAULT 'Casa',
  `NomeDestinatario` varchar(100) NOT NULL,
  `Telemovel` varchar(20) DEFAULT NULL,
  `Pais` varchar(60) NOT NULL DEFAULT 'Portugal',
  `Distrito` varchar(60) NOT NULL,
  `ConcelhoRegiao` varchar(60) NOT NULL,
  `Rua` varchar(150) NOT NULL,
  `NumeroPorta` varchar(10) NOT NULL,
  `NumeroLote` varchar(30) DEFAULT NULL,
  `CodigoPostal` char(8) NOT NULL,
  `Localidade` varchar(80) NOT NULL,
  `Predefinida` tinyint(1) NOT NULL DEFAULT 0,
  `DataCriacao` datetime NOT NULL DEFAULT current_timestamp(),
  `DataAlterado` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `morada`
--

INSERT INTO `morada` (`IDMorada`, `IDUtilizador`, `Designacao`, `NomeDestinatario`, `Telemovel`, `Pais`, `Distrito`, `ConcelhoRegiao`, `Rua`, `NumeroPorta`, `NumeroLote`, `CodigoPostal`, `Localidade`, `Predefinida`, `DataCriacao`, `DataAlterado`) VALUES
(1, 2, 'Casa', 'Cliente Teste', '910000002', 'Portugal', 'Lisboa', 'Lisboa', 'Rua das Flores', '25', '3º Esq', '1200-195', 'Lisboa', 1, '2026-10-21 08:50:07', '2026-10-21 08:50:07'),
(2, 3, 'CASA', 'NOME', '123456789', 'Portugal', 'lisboa', 'lisboa', 'rua da minha casa', '69', '1', '8273-123', 'lumiar', 1, '2026-10-21 11:14:40', '2026-10-21 11:14:40');

-- --------------------------------------------------------

--
-- Estrutura da tabela `movimentostock`
--

CREATE TABLE `movimentostock` (
  `IDMovimento` int(10) UNSIGNED NOT NULL,
  `IDProduto` int(10) UNSIGNED NOT NULL,
  `Tipo` enum('entrada','venda','cancelamento','ajuste','perda') NOT NULL,
  `Quantidade` int(11) NOT NULL,
  `StockApos` int(11) NOT NULL,
  `IDEncomenda` int(10) UNSIGNED DEFAULT NULL,
  `IDUtilizador` int(10) UNSIGNED DEFAULT NULL,
  `Nota` varchar(255) DEFAULT NULL,
  `DataMovimento` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `movimentostock`
--

INSERT INTO `movimentostock` (`IDMovimento`, `IDProduto`, `Tipo`, `Quantidade`, `StockApos`, `IDEncomenda`, `IDUtilizador`, `Nota`, `DataMovimento`) VALUES
(13, 1, 'venda', -2, 11, 4, 4, 'Venda na encomenda OC-2026-8766EF15EC61', '2026-10-26 23:09:36');

-- --------------------------------------------------------

--
-- Estrutura da tabela `produto`
--

CREATE TABLE `produto` (
  `IDProduto` int(10) UNSIGNED NOT NULL,
  `SKU` varchar(40) NOT NULL,
  `Nome` varchar(150) NOT NULL,
  `Descricao` text DEFAULT NULL,
  `Marca` varchar(80) DEFAULT NULL,
  `IDCategoria` int(10) UNSIGNED NOT NULL,
  `IDFornecedor` int(10) UNSIGNED DEFAULT NULL,
  `Preco` decimal(10,2) NOT NULL,
  `PrecoPromocional` decimal(10,2) DEFAULT NULL,
  `TaxaIVA` decimal(4,2) NOT NULL DEFAULT 6.00,
  `Unidade` varchar(20) NOT NULL DEFAULT 'un',
  `StockQuantidade` int(11) NOT NULL DEFAULT 0,
  `StockMinimo` int(11) NOT NULL DEFAULT 5,
  `Alergenios` varchar(255) DEFAULT NULL,
  `Imagem` varchar(255) DEFAULT NULL,
  `Destaque` tinyint(1) NOT NULL DEFAULT 0,
  `Ativo` tinyint(1) NOT NULL DEFAULT 1,
  `IDCriadoPor` int(10) UNSIGNED DEFAULT NULL,
  `DataCriacao` datetime NOT NULL DEFAULT current_timestamp(),
  `IDAlteradoPor` int(10) UNSIGNED DEFAULT NULL,
  `DataAlterado` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Extraindo dados da tabela `produto`
--

INSERT INTO `produto` (`IDProduto`, `SKU`, `Nome`, `Descricao`, `Marca`, `IDCategoria`, `IDFornecedor`, `Preco`, `PrecoPromocional`, `TaxaIVA`, `Unidade`, `StockQuantidade`, `StockMinimo`, `Alergenios`, `Imagem`, `Destaque`, `Ativo`, `IDCriadoPor`, `DataCriacao`, `IDAlteradoPor`, `DataAlterado`) VALUES
(1, 'CAM-AZL-M', 'banana', 'EWQRTWYE', 'banana', 9, 1, 4.00, 3.00, 6.00, 'un', 11, 5, '123123123132', 'imagens/produtos/uploads/produto-1/cartao-fc045264601a0d21a696fde2.jpg', 0, 1, 4, '2026-10-24 21:34:52', 4, '2026-10-26 23:09:36');

-- --------------------------------------------------------

--
-- Estrutura da tabela `produtoimagem`
--

CREATE TABLE `produtoimagem` (
  `IDProdutoImagem` int(11) NOT NULL,
  `IDProduto` int(11) NOT NULL,
  `Caminho` varchar(500) NOT NULL,
  `Ordem` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `produtoimagem`
--

INSERT INTO `produtoimagem` (`IDProdutoImagem`, `IDProduto`, `Caminho`, `Ordem`) VALUES
(1, 1, 'imagens/produtos/uploads/produto-1/destaque-1e63558d1a7ffd31a06ca12a.jpg', 0),
(2, 1, 'imagens/produtos/uploads/produto-1/destaque-15e801bdcea75ab68104afb9.jpg', 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `tokenutilizador`
--

CREATE TABLE `tokenutilizador` (
  `IDToken` int(10) UNSIGNED NOT NULL,
  `IDUtilizador` int(10) UNSIGNED NOT NULL,
  `Tipo` enum('recuperar_password','verificar_email') NOT NULL,
  `TokenHash` char(64) NOT NULL,
  `ExpiraEm` datetime NOT NULL,
  `UsadoEm` datetime DEFAULT NULL,
  `DataCriacao` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizador`
--

CREATE TABLE `utilizador` (
  `IDUtilizador` int(10) UNSIGNED NOT NULL,
  `Nome` varchar(100) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `Tipo` enum('cliente','admin') NOT NULL DEFAULT 'cliente',
  `Telemovel` varchar(20) DEFAULT NULL,
  `NIF` char(9) DEFAULT NULL,
  `DataNascimento` date DEFAULT NULL,
  `EmailVerificado` tinyint(1) NOT NULL DEFAULT 0,
  `Ativo` tinyint(1) NOT NULL DEFAULT 1,
  `UltimoLogin` datetime DEFAULT NULL,
  `DataCriacao` datetime NOT NULL DEFAULT current_timestamp(),
  `DataAlterado` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `utilizador`
--

INSERT INTO `utilizador` (`IDUtilizador`, `Nome`, `Username`, `Email`, `PasswordHash`, `Tipo`, `Telemovel`, `NIF`, `DataNascimento`, `EmailVerificado`, `Ativo`, `UltimoLogin`, `DataCriacao`, `DataAlterado`) VALUES
(1, 'Administrador', 'admin', 'admin@ocantinho.local', '$2y$10$OLJTgBnTX1DDidz7AdHk/etaDXZT12qj7t9Ik7B14e.vvbBnYFOWm', 'admin', '910000001', NULL, NULL, 1, 1, NULL, '2026-10-21 08:50:07', '2026-10-21 08:50:07'),
(2, 'Cliente Teste', 'cliente.teste', 'cliente@ocantinho.local', '$2y$10$4hTqe3oAWT3tCMJ8xGVBaewJaYmabqBo3W3WQUyF6slB5OLCAyaMG', 'cliente', '910000002', NULL, NULL, 1, 1, NULL, '2026-10-21 08:50:07', '2026-10-21 08:50:07'),
(3, 'nome', 'testador', 'ola@exemplo.com', '$2y$10$QOuZCOzZliwO6pGv/k8ExugWET2Xok2m2AdfKOly7rDbQgt01hQnm', 'cliente', '123456789', '187267351', '2026-09-30', 0, 1, '2026-10-21 11:12:34', '2026-10-21 10:49:49', '2026-10-21 11:13:39'),
(4, 'Gonçalo Gonçalves', 'gouxalu', 'goncalohero@gmail.com', '$2y$10$4XC/84tQjHHH3mxc7ID2sO4lGzm3Uk.lqjkuZ3D5cRQOY17zfDJ.q', 'admin', '932326789324789', '378942165', '2026-10-23', 1, 1, '2026-10-24 09:43:19', '2026-10-24 09:29:06', '2026-10-24 09:43:19');

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `vw_carrinho_detalhe`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `vw_carrinho_detalhe` (
`IDUtilizador` int(10) unsigned
,`IDItemCarrinho` int(10) unsigned
,`IDProduto` int(10) unsigned
,`SKU` varchar(40)
,`Nome` varchar(150)
,`Imagem` varchar(255)
,`Unidade` varchar(20)
,`Quantidade` int(10) unsigned
,`PrecoUnitario` decimal(10,2)
,`TotalLinha` decimal(20,2)
,`StockQuantidade` int(11)
,`Ativo` tinyint(1)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `vw_encomenda_admin`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `vw_encomenda_admin` (
`IDEncomenda` int(10) unsigned
,`NumeroEncomenda` varchar(20)
,`Estado` enum('pedido','em_preparacao','a_entregar','entregue','cancelada')
,`EstadoPagamento` enum('pendente','pago','falhado','reembolsado')
,`MetodoPagamento` enum('mbway','multibanco','cartao','paypal','pagamento_entrega')
,`Total` decimal(10,2)
,`DataCriacao` datetime
,`DataEntregaPrevista` date
,`DataEntrega` datetime
,`IDUtilizador` int(10) unsigned
,`Cliente` varchar(100)
,`EmailCliente` varchar(255)
,`TelemovelCliente` varchar(20)
,`EntregaLocalidade` varchar(80)
,`EntregaDistrito` varchar(60)
,`TotalArtigos` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `vw_produto_loja`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `vw_produto_loja` (
`IDProduto` int(10) unsigned
,`SKU` varchar(40)
,`Nome` varchar(150)
,`Descricao` text
,`Marca` varchar(80)
,`IDCategoria` int(10) unsigned
,`Categoria` varchar(80)
,`Preco` decimal(10,2)
,`PrecoPromocional` decimal(10,2)
,`PrecoFinal` decimal(10,2)
,`TaxaIVA` decimal(4,2)
,`Unidade` varchar(20)
,`Alergenios` varchar(255)
,`Imagem` varchar(255)
,`Destaque` tinyint(1)
,`StockQuantidade` int(11)
,`EmStock` int(1)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `vw_produto_stock_baixo`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `vw_produto_stock_baixo` (
`IDProduto` int(10) unsigned
,`SKU` varchar(40)
,`Nome` varchar(150)
,`Categoria` varchar(80)
,`Fornecedor` varchar(120)
,`StockQuantidade` int(11)
,`StockMinimo` int(11)
);

-- --------------------------------------------------------

--
-- Estrutura para vista `vw_carrinho_detalhe`
--
DROP TABLE IF EXISTS `vw_carrinho_detalhe`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_carrinho_detalhe`  AS SELECT `ca`.`IDUtilizador` AS `IDUtilizador`, `ic`.`IDItemCarrinho` AS `IDItemCarrinho`, `ic`.`IDProduto` AS `IDProduto`, `p`.`SKU` AS `SKU`, `p`.`Nome` AS `Nome`, `p`.`Imagem` AS `Imagem`, `p`.`Unidade` AS `Unidade`, `ic`.`Quantidade` AS `Quantidade`, coalesce(`p`.`PrecoPromocional`,`p`.`Preco`) AS `PrecoUnitario`, `ic`.`Quantidade`* coalesce(`p`.`PrecoPromocional`,`p`.`Preco`) AS `TotalLinha`, `p`.`StockQuantidade` AS `StockQuantidade`, `p`.`Ativo` AS `Ativo` FROM ((`itemcarrinho` `ic` join `carrinho` `ca` on(`ca`.`IDCarrinho` = `ic`.`IDCarrinho`)) join `produto` `p` on(`p`.`IDProduto` = `ic`.`IDProduto`)) ;

-- --------------------------------------------------------

--
-- Estrutura para vista `vw_encomenda_admin`
--
DROP TABLE IF EXISTS `vw_encomenda_admin`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_encomenda_admin`  AS SELECT `e`.`IDEncomenda` AS `IDEncomenda`, `e`.`NumeroEncomenda` AS `NumeroEncomenda`, `e`.`Estado` AS `Estado`, `e`.`EstadoPagamento` AS `EstadoPagamento`, `e`.`MetodoPagamento` AS `MetodoPagamento`, `e`.`Total` AS `Total`, `e`.`DataCriacao` AS `DataCriacao`, `e`.`DataEntregaPrevista` AS `DataEntregaPrevista`, `e`.`DataEntrega` AS `DataEntrega`, `u`.`IDUtilizador` AS `IDUtilizador`, `u`.`Nome` AS `Cliente`, `u`.`Email` AS `EmailCliente`, `u`.`Telemovel` AS `TelemovelCliente`, `e`.`EntregaLocalidade` AS `EntregaLocalidade`, `e`.`EntregaDistrito` AS `EntregaDistrito`, (select coalesce(sum(`i`.`Quantidade`),0) from `itemencomenda` `i` where `i`.`IDEncomenda` = `e`.`IDEncomenda`) AS `TotalArtigos` FROM (`encomenda` `e` join `utilizador` `u` on(`u`.`IDUtilizador` = `e`.`IDUtilizador`)) ;

-- --------------------------------------------------------

--
-- Estrutura para vista `vw_produto_loja`
--
DROP TABLE IF EXISTS `vw_produto_loja`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_produto_loja`  AS SELECT `p`.`IDProduto` AS `IDProduto`, `p`.`SKU` AS `SKU`, `p`.`Nome` AS `Nome`, `p`.`Descricao` AS `Descricao`, `p`.`Marca` AS `Marca`, `p`.`IDCategoria` AS `IDCategoria`, `c`.`Nome` AS `Categoria`, `p`.`Preco` AS `Preco`, `p`.`PrecoPromocional` AS `PrecoPromocional`, coalesce(`p`.`PrecoPromocional`,`p`.`Preco`) AS `PrecoFinal`, `p`.`TaxaIVA` AS `TaxaIVA`, `p`.`Unidade` AS `Unidade`, `p`.`Alergenios` AS `Alergenios`, `p`.`Imagem` AS `Imagem`, `p`.`Destaque` AS `Destaque`, `p`.`StockQuantidade` AS `StockQuantidade`, `p`.`StockQuantidade`> 0 AS `EmStock` FROM (`produto` `p` join `categoria` `c` on(`c`.`IDCategoria` = `p`.`IDCategoria`)) WHERE `p`.`Ativo` = 1 AND `c`.`Ativo` = 1 ;

-- --------------------------------------------------------

--
-- Estrutura para vista `vw_produto_stock_baixo`
--
DROP TABLE IF EXISTS `vw_produto_stock_baixo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_produto_stock_baixo`  AS SELECT `p`.`IDProduto` AS `IDProduto`, `p`.`SKU` AS `SKU`, `p`.`Nome` AS `Nome`, `c`.`Nome` AS `Categoria`, `f`.`Nome` AS `Fornecedor`, `p`.`StockQuantidade` AS `StockQuantidade`, `p`.`StockMinimo` AS `StockMinimo` FROM ((`produto` `p` join `categoria` `c` on(`c`.`IDCategoria` = `p`.`IDCategoria`)) left join `fornecedor` `f` on(`f`.`IDFornecedor` = `p`.`IDFornecedor`)) WHERE `p`.`Ativo` = 1 AND `p`.`StockQuantidade` <= `p`.`StockMinimo` ;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `carrinho`
--
ALTER TABLE `carrinho`
  ADD PRIMARY KEY (`IDCarrinho`),
  ADD UNIQUE KEY `uq_carrinho_utilizador` (`IDUtilizador`);

--
-- Índices para tabela `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`IDCategoria`),
  ADD UNIQUE KEY `uq_categoria_slug` (`Slug`),
  ADD KEY `ix_categoria_pai` (`IDCategoriaPai`);

--
-- Índices para tabela `encomenda`
--
ALTER TABLE `encomenda`
  ADD PRIMARY KEY (`IDEncomenda`),
  ADD UNIQUE KEY `uq_encomenda_numero` (`NumeroEncomenda`),
  ADD KEY `ix_encomenda_utilizador` (`IDUtilizador`,`DataCriacao`),
  ADD KEY `ix_encomenda_estado` (`Estado`,`DataCriacao`),
  ADD KEY `fk_encomenda_alterado_por` (`IDAlteradoPor`),
  ADD KEY `ix_encomenda_gastos` (`IDUtilizador`,`EstadoPagamento`,`DataPagamento`);

--
-- Índices para tabela `fornecedor`
--
ALTER TABLE `fornecedor`
  ADD PRIMARY KEY (`IDFornecedor`),
  ADD KEY `ix_fornecedor_nome` (`Nome`);

--
-- Índices para tabela `gastocompra`
--
ALTER TABLE `gastocompra`
  ADD PRIMARY KEY (`IDGastoCompra`),
  ADD UNIQUE KEY `uq_gasto_compra_referencia` (`IDUtilizador`,`ReferenciaCompra`),
  ADD KEY `idx_gasto_compra_data` (`IDUtilizador`,`DataCompra`);

--
-- Índices para tabela `historicoestadoencomenda`
--
ALTER TABLE `historicoestadoencomenda`
  ADD PRIMARY KEY (`IDHistorico`),
  ADD KEY `ix_historico_encomenda` (`IDEncomenda`,`DataAlteracao`),
  ADD KEY `fk_historico_utilizador` (`IDUtilizador`);

--
-- Índices para tabela `itemcarrinho`
--
ALTER TABLE `itemcarrinho`
  ADD PRIMARY KEY (`IDItemCarrinho`),
  ADD UNIQUE KEY `uq_itemcarrinho_produto` (`IDCarrinho`,`IDProduto`),
  ADD KEY `ix_itemcarrinho_produto` (`IDProduto`);

--
-- Índices para tabela `itemencomenda`
--
ALTER TABLE `itemencomenda`
  ADD PRIMARY KEY (`IDItemEncomenda`),
  ADD UNIQUE KEY `uq_itemencomenda_produto` (`IDEncomenda`,`IDProduto`),
  ADD KEY `ix_itemencomenda_produto` (`IDProduto`);

--
-- Índices para tabela `morada`
--
ALTER TABLE `morada`
  ADD PRIMARY KEY (`IDMorada`),
  ADD KEY `ix_morada_utilizador` (`IDUtilizador`);

--
-- Índices para tabela `movimentostock`
--
ALTER TABLE `movimentostock`
  ADD PRIMARY KEY (`IDMovimento`),
  ADD KEY `ix_movimento_produto` (`IDProduto`,`DataMovimento`),
  ADD KEY `ix_movimento_encomenda` (`IDEncomenda`),
  ADD KEY `fk_movimento_utilizador` (`IDUtilizador`);

--
-- Índices para tabela `produto`
--
ALTER TABLE `produto`
  ADD PRIMARY KEY (`IDProduto`),
  ADD UNIQUE KEY `uq_produto_sku` (`SKU`),
  ADD KEY `ix_produto_categoria` (`IDCategoria`),
  ADD KEY `ix_produto_fornecedor` (`IDFornecedor`),
  ADD KEY `ix_produto_loja` (`Ativo`,`Destaque`),
  ADD KEY `fk_produto_criado_por` (`IDCriadoPor`),
  ADD KEY `fk_produto_alterado_por` (`IDAlteradoPor`);
ALTER TABLE `produto` ADD FULLTEXT KEY `ft_produto_pesquisa` (`Nome`,`Descricao`,`Marca`);

--
-- Índices para tabela `produtoimagem`
--
ALTER TABLE `produtoimagem`
  ADD PRIMARY KEY (`IDProdutoImagem`),
  ADD KEY `idx_produto_imagem_ordem` (`IDProduto`,`Ordem`,`IDProdutoImagem`);

--
-- Índices para tabela `tokenutilizador`
--
ALTER TABLE `tokenutilizador`
  ADD PRIMARY KEY (`IDToken`),
  ADD UNIQUE KEY `uq_token_hash` (`TokenHash`),
  ADD KEY `ix_token_utilizador` (`IDUtilizador`,`Tipo`);

--
-- Índices para tabela `utilizador`
--
ALTER TABLE `utilizador`
  ADD PRIMARY KEY (`IDUtilizador`),
  ADD UNIQUE KEY `uq_utilizador_username` (`Username`),
  ADD UNIQUE KEY `uq_utilizador_email` (`Email`),
  ADD KEY `ix_utilizador_tipo` (`Tipo`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `carrinho`
--
ALTER TABLE `carrinho`
  MODIFY `IDCarrinho` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `categoria`
--
ALTER TABLE `categoria`
  MODIFY `IDCategoria` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `encomenda`
--
ALTER TABLE `encomenda`
  MODIFY `IDEncomenda` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fornecedor`
--
ALTER TABLE `fornecedor`
  MODIFY `IDFornecedor` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `gastocompra`
--
ALTER TABLE `gastocompra`
  MODIFY `IDGastoCompra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `historicoestadoencomenda`
--
ALTER TABLE `historicoestadoencomenda`
  MODIFY `IDHistorico` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `itemcarrinho`
--
ALTER TABLE `itemcarrinho`
  MODIFY `IDItemCarrinho` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `itemencomenda`
--
ALTER TABLE `itemencomenda`
  MODIFY `IDItemEncomenda` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `morada`
--
ALTER TABLE `morada`
  MODIFY `IDMorada` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `movimentostock`
--
ALTER TABLE `movimentostock`
  MODIFY `IDMovimento` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `produto`
--
ALTER TABLE `produto`
  MODIFY `IDProduto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtoimagem`
--
ALTER TABLE `produtoimagem`
  MODIFY `IDProdutoImagem` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `tokenutilizador`
--
ALTER TABLE `tokenutilizador`
  MODIFY `IDToken` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `utilizador`
--
ALTER TABLE `utilizador`
  MODIFY `IDUtilizador` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `carrinho`
--
ALTER TABLE `carrinho`
  ADD CONSTRAINT `fk_carrinho_utilizador` FOREIGN KEY (`IDUtilizador`) REFERENCES `utilizador` (`IDUtilizador`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `categoria`
--
ALTER TABLE `categoria`
  ADD CONSTRAINT `fk_categoria_pai` FOREIGN KEY (`IDCategoriaPai`) REFERENCES `categoria` (`IDCategoria`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `encomenda`
--
ALTER TABLE `encomenda`
  ADD CONSTRAINT `fk_encomenda_alterado_por` FOREIGN KEY (`IDAlteradoPor`) REFERENCES `utilizador` (`IDUtilizador`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_encomenda_utilizador` FOREIGN KEY (`IDUtilizador`) REFERENCES `utilizador` (`IDUtilizador`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `historicoestadoencomenda`
--
ALTER TABLE `historicoestadoencomenda`
  ADD CONSTRAINT `fk_historico_encomenda` FOREIGN KEY (`IDEncomenda`) REFERENCES `encomenda` (`IDEncomenda`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historico_utilizador` FOREIGN KEY (`IDUtilizador`) REFERENCES `utilizador` (`IDUtilizador`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `itemcarrinho`
--
ALTER TABLE `itemcarrinho`
  ADD CONSTRAINT `fk_itemcarrinho_carrinho` FOREIGN KEY (`IDCarrinho`) REFERENCES `carrinho` (`IDCarrinho`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_itemcarrinho_produto` FOREIGN KEY (`IDProduto`) REFERENCES `produto` (`IDProduto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `itemencomenda`
--
ALTER TABLE `itemencomenda`
  ADD CONSTRAINT `fk_itemencomenda_encomenda` FOREIGN KEY (`IDEncomenda`) REFERENCES `encomenda` (`IDEncomenda`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_itemencomenda_produto` FOREIGN KEY (`IDProduto`) REFERENCES `produto` (`IDProduto`) ON UPDATE CASCADE;

--
-- Limitadores para a tabela `morada`
--
ALTER TABLE `morada`
  ADD CONSTRAINT `fk_morada_utilizador` FOREIGN KEY (`IDUtilizador`) REFERENCES `utilizador` (`IDUtilizador`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limitadores para a tabela `movimentostock`
--
ALTER TABLE `movimentostock`
  ADD CONSTRAINT `fk_movimento_encomenda` FOREIGN KEY (`IDEncomenda`) REFERENCES `encomenda` (`IDEncomenda`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimento_produto` FOREIGN KEY (`IDProduto`) REFERENCES `produto` (`IDProduto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_movimento_utilizador` FOREIGN KEY (`IDUtilizador`) REFERENCES `utilizador` (`IDUtilizador`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `produto`
--
ALTER TABLE `produto`
  ADD CONSTRAINT `fk_produto_alterado_por` FOREIGN KEY (`IDAlteradoPor`) REFERENCES `utilizador` (`IDUtilizador`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_produto_categoria` FOREIGN KEY (`IDCategoria`) REFERENCES `categoria` (`IDCategoria`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_produto_criado_por` FOREIGN KEY (`IDCriadoPor`) REFERENCES `utilizador` (`IDUtilizador`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_produto_fornecedor` FOREIGN KEY (`IDFornecedor`) REFERENCES `fornecedor` (`IDFornecedor`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limitadores para a tabela `tokenutilizador`
--
ALTER TABLE `tokenutilizador`
  ADD CONSTRAINT `fk_token_utilizador` FOREIGN KEY (`IDUtilizador`) REFERENCES `utilizador` (`IDUtilizador`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
