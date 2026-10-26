# Laboratorio2
Projeto para avaliação Laboratório 2, Gonçalo &amp; André

## Imagens dos produtos

Na edição do produto, a imagem do cartão é escolhida separadamente das imagens da galeria de destaque. A galeria aceita até 3 imagens JPG, PNG ou WebP, com um máximo de 5 MB por ficheiro. Os uploads são guardados em `imagens/produtos/uploads/`; o servidor Web precisa de permissão de escrita nessa pasta.

A tabela `ProdutoImagem`, usada para guardar a galeria, é criada automaticamente quando a aplicação a consulta pela primeira vez; o utilizador da base de dados precisa de permissões para criar tabelas.

## Categorias

Uma categoria só pode ser eliminada quando não tem produtos nem subcategorias associados. Reatribua os produtos ou subcategorias antes de tentar eliminá-la.

## Componentes partilhados

A navegação e o rodapé das páginas da loja são reutilizados a partir de `componentes/navbar.php` e `componentes/footer.php`.

## Acompanhamento de gastos

O perfil usa `Encomenda` como fonte de gastos, sem duplicar o valor e a data numa tabela separada. Apenas encomendas com `EstadoPagamento = 'pago'` e `DataPagamento` preenchida são contabilizadas; `ItemEncomenda` mantém os detalhes dos produtos comprados. O painel apresenta total, compras recentes e média mensal ou semanal sobre os períodos com pagamentos.

Para acelerar estas consultas em bases de dados já existentes, pode aplicar o índice em `sql/gastos.sql`. A tabela antiga `GastoCompra`, caso exista, não é usada pelo painel.
