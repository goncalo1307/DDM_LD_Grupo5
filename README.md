# Laboratorio2
Projeto para avaliação Laboratório 2, Gonçalo &amp; André

## Imagens dos produtos

Na edição do produto, a imagem do cartão é escolhida separadamente das imagens da galeria de destaque. A galeria aceita até 3 imagens JPG, PNG ou WebP, com um máximo de 5 MB por ficheiro. Os uploads são guardados em `imagens/produtos/uploads/`; o servidor Web precisa de permissão de escrita nessa pasta.

A tabela `ProdutoImagem`, usada para guardar a galeria, é criada automaticamente quando a aplicação a consulta pela primeira vez; o utilizador da base de dados precisa de permissões para criar tabelas.

## Categorias

Uma categoria só pode ser eliminada quando não tem produtos nem subcategorias associados. Reatribua os produtos ou subcategorias antes de tentar eliminá-la.

## Componentes partilhados

A navegação e o rodapé das páginas da loja são reutilizados a partir de `componentes/navbar.php` e `componentes/footer.php`.
