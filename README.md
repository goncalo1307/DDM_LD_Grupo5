# Laboratorio2
Projeto para avaliação Laboratório 2, Gonçalo Gonçalves &amp; André &amp; Fábio

## Imagens dos produtos

Na edição do produto, a imagem do catálogo é independente das imagens de destaque: imagens de catálogo não aparecem na galeria, e imagens de destaque não são usadas no catálogo. A galeria aceita até 3 imagens JPG, PNG ou WebP, com um máximo de 5 MB por ficheiro. Os uploads são guardados em `imagens/produtos/uploads/`; o servidor Web precisa de permissão de escrita nessa pasta.

A tabela `ProdutoImagem`, usada para guardar a galeria, é criada automaticamente quando a aplicação a consulta pela primeira vez; o utilizador da base de dados precisa de permissões para criar tabelas.

## Categorias

As categorias são ordenadas automaticamente por nome, por ordem alfabética, sem opção de ordenação manual. Na loja, o menu Produtos permite escolher uma categoria e, quando existem, as respetivas subcategorias para filtrar o catálogo. Uma categoria só pode ser eliminada quando não tem produtos nem subcategorias associados. Reatribua os produtos ou subcategorias antes de tentar eliminá-la.

## Fornecedores

O painel de administração permite criar fornecedores com nome e dados de contacto. Os fornecedores criados ficam disponíveis para associação aos produtos. As listas de produtos e fornecedores no admin têm pesquisa própria e paginação de 10 registos; a pesquisa de fornecedores também encontra os produtos associados e pode usar o NIF, telefone ou número de fornecedor.

## Componentes partilhados

A navegação e o rodapé das páginas da loja são reutilizados a partir de `componentes/navbar.php` e `componentes/footer.php`.

## Estilos CSS

Os ficheiros CSS estão organizados na pasta `css/`, com uma pasta por área ou página, como `css/index/`, `css/produto/`, `css/perfil/` e `css/admin/`. As folhas de cada área importam os estilos comuns de `css/shared/`, evitando duplicar regras partilhadas.

## Acompanhamento de gastos

O perfil usa `GastoCompra` para apresentar total, compras recentes e média mensal ou semanal. Também permite exportar os totais por mês ou semana e o número de compras em CSV. O checkout cria um registo de gasto ligado à referência da encomenda.

## Encomendas e entregas

O painel lista as encomendas com o número, cliente, artigos, estado e pagamento. Administradores podem gerir a loja e criar contas de funcionário; funcionários entram em `conta/funcionario.php` e só podem consultar encomendas e atualizar entrega/pagamento. Essas contas não podem entrar pelo login normal de cliente, consultar o perfil de cliente ou usar o carrinho. O administrador tem uma secção de histórico paginada, com alterações de estado e confirmações manuais de pagamento. Os clientes consultam os estados na secção “As minhas encomendas” do perfil. Para suportar o papel `funcionario`, execute `migracao_funcionario.sql` na base `o_cantinho` através do separador SQL do phpMyAdmin. Os pagamentos continuam a ser confirmados manualmente, pois não existe integração com gateways de pagamento.

## Listas de compras predefinidas

No checkout, cada utilizador pode guardar a compra como lista predefinida. Na página de um produto, o botão para adicionar a uma lista abre uma janela onde pode escolher uma lista existente ou criar uma nova. Na loja e nos resultados de pesquisa, também pode adicionar o produto ao carrinho e a uma lista no mesmo passo. No carrinho pode ajustar quantidades, remover produtos e adicionar uma lista inteira ao carrinho, com validação do stock. As tabelas `ListaCompra` e `ItemListaCompra` são criadas automaticamente na primeira utilização da funcionalidade; o utilizador da base de dados precisa de permissões para criar tabelas.

## Checkout

O checkout é feito no fim da página do carrinho. Revalida o stock dentro de uma transação, guarda a encomenda e os respetivos itens, regista o movimento de stock e limpa o carrinho. O envio custa 3,99 € em encomendas até 35 € e é gratuito acima desse valor. O método de pagamento fica registado, mas não existe integração com gateways: todas as encomendas ficam pendentes e não é efetuada qualquer cobrança. O total da encomenda também é guardado em `GastoCompra` para alimentar o painel de gastos.
