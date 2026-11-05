<?php
require __DIR__ . '/conta/ligacao.php';

$logado = !empty($_SESSION['utilizador_id']);
$nomeUtilizador = $_SESSION['utilizador_nome'] ?? '';
$primeiroNome = $nomeUtilizador !== '' ? explode(' ', trim($nomeUtilizador))[0] : '';
$inicial = $primeiroNome !== '' ? mb_strtoupper(mb_substr($primeiroNome, 0, 1)) : '';
$tipoUtilizador = $_SESSION['utilizador_tipo'] ?? 'cliente';

$totalCarrinho = 0;
if ($logado) {
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(ic.Quantidade), 0)
         FROM ItemCarrinho ic
         JOIN Carrinho c ON c.IDCarrinho = ic.IDCarrinho
         WHERE c.IDUtilizador = ?'
    );
    $stmt->execute([(int) $_SESSION['utilizador_id']]);
    $totalCarrinho = (int) $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Consulte as perguntas frequentes e os termos e condições de utilização da loja online O Cantinho Mercado.">
  <title>Ajuda, FAQs e Termos | O Cantinho Mercado</title>
  <link rel="stylesheet" href="css/ajuda/ajuda.css">
</head>
<body>
  <?php require __DIR__ . '/componentes/navbar.php'; ?>

  <main class="help-main">
    <section class="help-hero">
      <div class="help-hero-copy">
        <span class="help-eyebrow"><span aria-hidden="true">✳</span> Estamos aqui para ajudar</span>
        <h1>Respostas simples.<br><span>Compras tranquilas.</span></h1>
        <p>Encontre respostas às dúvidas mais comuns sobre encomendas, pagamentos e entregas, ou consulte os termos de utilização da loja.</p>
        <div class="help-hero-links">
          <a href="#faqs">Ver perguntas frequentes <span aria-hidden="true">↓</span></a>
          <a href="#termos">Ler termos e condições <span aria-hidden="true">↓</span></a>
        </div>
      </div>
      <div class="help-hero-card" aria-hidden="true">
        <span class="help-card-number">C</span>
        <span class="help-card-spark">✳</span>
        <p>O que precisa<br>de saber, num<br>só lugar.</p>
        <span class="help-card-caption">Ajuda do Cantinho</span>
      </div>
    </section>

    <div class="help-layout">
      <aside class="help-sidebar" aria-label="Nesta página">
        <span class="sidebar-title">Nesta página</span>
        <a href="#faqs">Perguntas frequentes</a>
        <a href="#encomendas">Encomendas e conta</a>
        <a href="#entrega-pagamento">Entrega e pagamento</a>
        <a href="#termos">Termos e condições</a>
        <a href="#produtos-precos">Produtos e preços</a>
        <a href="#encomendas-termos">Encomendas e entrega</a>
        <a href="#pagamentos-termos">Pagamentos</a>
      </aside>

      <div class="help-content">
        <section class="faq-section" id="faqs">
          <div class="help-section-heading">
            <span class="help-section-kicker">Ajuda rápida</span>
            <h2>Perguntas frequentes</h2>
            <p>As respostas às dúvidas mais comuns sobre as compras no Cantinho.</p>
          </div>

          <div class="faq-group" id="encomendas">
            <h3>Encomendas e conta</h3>
            <details class="faq-item">
              <summary>Como faço uma encomenda?</summary>
              <div class="faq-answer">
                <p>Inicie sessão ou crie uma conta, adicione produtos ao carrinho e avance para a finalização. No checkout, indique ou escolha uma morada, selecione um método de pagamento e confirme a encomenda.</p>
              </div>
            </details>
            <details class="faq-item">
              <summary>Preciso de ter conta para comprar?</summary>
              <div class="faq-answer">
                <p>Sim. É necessário iniciar sessão para aceder ao carrinho e finalizar uma encomenda. A conta também permite guardar moradas e consultar o histórico de compras.</p>
              </div>
            </details>
            <details class="faq-item">
              <summary>Onde posso acompanhar a minha encomenda?</summary>
              <div class="faq-answer">
                <p>Depois de iniciar sessão, consulte a área <a href="conta/perfil.php#encomendas">As minhas encomendas</a> no seu perfil. Aí pode ver o estado da encomenda e do pagamento.</p>
              </div>
            </details>
            <details class="faq-item">
              <summary>Posso alterar ou cancelar uma encomenda depois de a confirmar?</summary>
              <div class="faq-answer">
                <p>Atualmente, o site não disponibiliza uma opção para alterar ou pedir o cancelamento de uma encomenda pelo perfil. A loja pode atualizar o respetivo estado, que ficará visível na sua conta. Os direitos previstos na lei aplicável mantêm-se.</p>
              </div>
            </details>
          </div>

          <div class="faq-group" id="entrega-pagamento">
            <h3>Entrega e pagamento</h3>
            <details class="faq-item">
              <summary>Quanto custa a entrega?</summary>
              <div class="faq-answer">
                <p>A entrega é gratuita para encomendas com subtotal superior a 35 €. Para encomendas até 35 €, incluindo exatamente 35 €, o custo de entrega apresentado no checkout é 3,99 €.</p>
              </div>
            </details>
            <details class="faq-item">
              <summary>Que métodos de pagamento posso selecionar?</summary>
              <div class="faq-answer">
                <p>No checkout pode selecionar MB Way, Multibanco, cartão, PayPal ou pagamento na entrega. A aplicação regista o método escolhido, mas não processa cobranças automaticamente. O pagamento da encomenda fica pendente até o estado ser atualizado.</p>
              </div>
            </details>
            <details class="faq-item">
              <summary>Porque é que o meu carrinho não deixa finalizar a encomenda?</summary>
              <div class="faq-answer">
                <p>O stock é confirmado novamente ao finalizar a encomenda. Se algum artigo já não estiver disponível ou a quantidade exceder o stock, atualize o carrinho e tente de novo.</p>
              </div>
            </details>
            <details class="faq-item">
              <summary>Como posso guardar ou alterar uma morada?</summary>
              <div class="faq-answer">
                <p>Na sua conta, aceda ao perfil e à área de moradas. Também pode escolher uma morada guardada ou indicar outra durante a finalização da encomenda.</p>
              </div>
            </details>
          </div>
        </section>

        <section class="terms-section" id="termos">
          <div class="help-section-heading">
            <span class="help-section-kicker">Informação da loja</span>
            <h2>Termos e condições</h2>
            <p>Condições gerais de utilização da loja online O Cantinho Mercado.</p>
          </div>

          <div class="terms-notice">
            <span aria-hidden="true">i</span>
            <p>Esta página descreve o funcionamento atual da loja. A identificação legal da entidade responsável e os seus contactos de apoio devem ser acrescentados antes de utilizar o site como serviço comercial.</p>
          </div>

          <article class="terms-article" id="utilizacao">
            <h3>1. Âmbito e utilização</h3>
            <p>Estes termos aplicam-se à navegação e às compras efetuadas através da loja online O Cantinho Mercado. Ao utilizar a loja, o utilizador compromete-se a fornecer informação correta e a utilizar a conta e os serviços de forma lícita e responsável.</p>
            <p>Para finalizar uma encomenda é necessário ter uma conta e iniciar sessão. O utilizador é responsável por manter os dados da conta atualizados e por proteger as respetivas credenciais de acesso.</p>
          </article>

          <article class="terms-article" id="produtos-precos">
            <h3>2. Produtos, disponibilidade e preços</h3>
            <p>A loja apresenta a informação disponível sobre cada produto, incluindo preço, unidade e disponibilidade. As imagens são ilustrativas e podem não representar todos os detalhes da embalagem ou apresentação do artigo.</p>
            <p>Os produtos estão sujeitos a stock. A disponibilidade e as quantidades são verificadas durante a finalização da encomenda; se um artigo estiver indisponível ou não existir stock suficiente, a encomenda não poderá ser concluída com esse artigo.</p>
            <p>O subtotal e os custos aplicáveis são apresentados no carrinho e confirmados no checkout antes da submissão da encomenda. A entrega custa 3,99 € para subtotais até 35 € e é gratuita para subtotais superiores a 35 €.</p>
          </article>

          <article class="terms-article" id="encomendas-termos">
            <h3>3. Encomendas e entrega</h3>
            <p>Para efetuar uma encomenda, o utilizador deve selecionar os produtos, indicar uma morada de entrega válida e confirmar os dados apresentados no checkout. Após a submissão, é apresentada uma referência da encomenda, que também pode ser consultada no perfil.</p>
            <p>A encomenda fica sujeita à disponibilidade dos artigos e à atualização do seu estado na conta do utilizador. A criação da encomenda não significa, por si só, que o pagamento tenha sido processado ou que a encomenda já tenha sido entregue.</p>
            <p>O site permite consultar o estado da encomenda, mas não disponibiliza atualmente uma opção de alteração ou pedido de cancelamento pelo perfil. Esta limitação da plataforma não prejudica os direitos que assistem ao consumidor nos termos da lei aplicável.</p>
          </article>

          <article class="terms-article" id="pagamentos-termos">
            <h3>4. Métodos e estado do pagamento</h3>
            <p>O checkout permite selecionar MB Way, Multibanco, cartão, PayPal ou pagamento na entrega. O método selecionado é associado à encomenda.</p>
            <p>Na versão atual da plataforma não é efetuada uma cobrança automática durante o checkout. As encomendas são registadas com o pagamento pendente, e o estado pode ser consultado no perfil. Não considere o pagamento concluído enquanto o estado apresentado na conta não indicar que foi pago.</p>
          </article>

          <article class="terms-article" id="dados-conta">
            <h3>5. Dados da conta</h3>
            <p>Os dados de conta, moradas e contacto indicados pelo utilizador são utilizados para disponibilizar as funcionalidades da loja e associar as encomendas ao respetivo perfil. O utilizador pode consultar e atualizar os dados disponíveis na área da conta.</p>
          </article>

          <article class="terms-article" id="alteracoes-termos">
            <h3>6. Alterações aos termos</h3>
            <p>Estes termos podem ser atualizados para refletir alterações ao funcionamento da loja. A versão publicada nesta página é a que se encontra atualmente disponível. As atualizações não eliminam os direitos previstos na legislação aplicável.</p>
          </article>

          <article class="terms-article" id="direitos">
            <h3>7. Direitos do consumidor</h3>
            <p>Nada nestes termos exclui ou limita direitos que sejam conferidos ao consumidor pela legislação aplicável. As regras específicas sobre cancelamentos, devoluções e resolução de reclamações devem ser confirmadas e publicadas pela entidade responsável pela loja.</p>
          </article>

          <p class="terms-updated">Última atualização: 5 de novembro de 2026</p>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/componentes/footer.php'; ?>
  <script src="javascript.js"></script>
</body>
</html>
