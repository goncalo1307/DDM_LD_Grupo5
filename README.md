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

O perfil usa `GastoCompra` para apresentar total, compras recentes e média mensal ou semanal. O checkout cria um registo de gasto ligado à referência da encomenda.

## Listas de compras predefinidas

No checkout, cada utilizador pode guardar a compra como lista predefinida. Ao adicionar produtos, também pode escolher uma das suas listas; no carrinho pode ajustar quantidades, remover produtos e adicionar uma lista inteira ao carrinho, com validação do stock. As tabelas `ListaCompra` e `ItemListaCompra` são criadas automaticamente na primeira utilização da funcionalidade; o utilizador da base de dados precisa de permissões para criar tabelas.

## Checkout

O checkout é feito no fim da página do carrinho. Revalida o stock dentro de uma transação, guarda a encomenda e os respetivos itens, regista o movimento de stock e limpa o carrinho. O envio custa 3,99 € em encomendas até 35 € e é gratuito acima desse valor. O método de pagamento fica registado, mas não existe integração com gateways: todas as encomendas ficam pendentes e não é efetuada qualquer cobrança. O total da encomenda também é guardado em `GastoCompra` para alimentar o painel de gastos.
