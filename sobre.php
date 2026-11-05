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
  <meta name="description" content="Conheça O Cantinho Mercado: um mercado online feito para tornar as compras do dia a dia mais simples, frescas e próximas.">
  <title>Sobre Nós | O Cantinho Mercado</title>
  <link rel="stylesheet" href="css/sobre/sobre.css">
</head>
<body>
  <?php require __DIR__ . '/componentes/navbar.php'; ?>

  <main>
    <section class="about-hero">
      <div class="about-hero-inner">
        <div class="about-hero-copy">
          <span class="about-eyebrow"><span aria-hidden="true">✳</span> O mercado da sua casa</span>
          <h1>Mais do que compras. <span>Um Cantinho à sua medida.</span></h1>
          <p>Estamos aqui para tornar cada ida ao mercado mais simples: bons produtos, escolhas cuidadas e tudo à distância de um clique.</p>
          <a class="about-button" href="index.php#categorias">
            Descobrir produtos
            <svg class="about-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
          </a>
          <div class="about-hero-note">
            <span class="note-mark" aria-hidden="true">C</span>
            <span>Feito com cuidado,<br><strong>pensado para si.</strong></span>
          </div>
        </div>
        <div class="about-hero-art" aria-label="Uma seleção de produtos do Cantinho">
          <div class="art-sun" aria-hidden="true"></div>
          <figure class="art-card art-card-main">
            <img src="imagens/produtos/legumes-frutas/frutas/banana/fruta-banana-1.jpg" alt="Bananas frescas">
            <figcaption>Frescura todos os dias <span aria-hidden="true">✳</span></figcaption>
          </figure>
          <figure class="art-card art-card-small">
            <img src="imagens/produtos/legumes-frutas/legumes/legumes-tomate-1.jpg" alt="Tomates selecionados">
          </figure>
          <div class="art-sticker"><span>O bom<br>da vida<br>começa à mesa.</span></div>
          <div class="art-doodle" aria-hidden="true">〰</div>
        </div>
      </div>
      <div class="hero-bottom-line"><span>Da nossa seleção para a sua mesa</span><span aria-hidden="true">↓</span></div>
    </section>

    <section class="about-intro" id="a-nossa-historia">
      <div class="section-label"><span>01</span> Quem somos</div>
      <div class="intro-content">
        <h2>O mercado de sempre,<br><span>com a facilidade de hoje.</span></h2>
        <div class="intro-copy">
          <p>O Cantinho nasceu de uma ideia simples: as compras do dia a dia não têm de ser complicadas. Queremos que encontre num só lugar aquilo de que precisa, sem perder o prazer de escolher bem.</p>
          <p>Somos uma mercearia online com gosto pelas coisas boas. Juntamos produtos essenciais, sabores familiares e uma experiência prática, para que sobre mais tempo para o que realmente importa.</p>
        </div>
      </div>
    </section>

    <section class="about-values" id="valores">
      <div class="values-heading">
        <div>
          <div class="section-label section-label-light"><span>02</span> O que nos move</div>
          <h2>Pequenos cuidados.<br><span>Uma grande diferença.</span></h2>
        </div>
        <p>Em cada encomenda, queremos que sinta a atenção e a proximidade que fazem de um mercado um lugar especial.</p>
      </div>
      <div class="values-grid">
        <article class="value-card value-card-yellow">
          <span class="value-number">01</span>
          <span class="value-icon" aria-hidden="true">✳</span>
          <h3>Escolhas com cuidado</h3>
          <p>Procuramos oferecer produtos de qualidade para que possa escolher com confiança, desde os ingredientes para uma refeição especial até aos indispensáveis da despensa.</p>
        </article>
        <article class="value-card value-card-cream">
          <span class="value-number">02</span>
          <span class="value-icon" aria-hidden="true">⌂</span>
          <h3>Proximidade a sério</h3>
          <p>Queremos ser aquele mercado de confiança que conhece as rotinas da casa e está sempre por perto — agora também no ecrã do seu telemóvel ou computador.</p>
        </article>
        <article class="value-card value-card-orange">
          <span class="value-number">03</span>
          <span class="value-icon" aria-hidden="true">↗</span>
          <h3>Mais tempo para si</h3>
          <p>Uma experiência de compra simples e conveniente ajuda a organizar o dia. Faça a sua lista, encontre o que procura e deixe o resto connosco.</p>
        </article>
      </div>
    </section>

    <section class="about-promise" id="compromisso">
      <div class="promise-image">
        <img src="imagens/produtos/mercearia/arroz-massa-farinha/arroz/mercearia-arroz-1.jpg" alt="Um produto essencial da despensa">
        <span class="promise-image-tag">O essencial, bem escolhido</span>
      </div>
      <div class="promise-copy">
        <div class="section-label"><span>03</span> O nosso compromisso</div>
        <h2>Um mercado mais simples começa com <span>boas escolhas.</span></h2>
        <p>Acreditamos que comprar bem deve ser fácil. Por isso, trabalhamos para apresentar os produtos de forma clara, ajudar a encontrar o que precisa e tornar cada encomenda numa experiência tranquila.</p>
        <p>O nosso compromisso é continuar a melhorar, ouvir quem nos escolhe e levar até si um serviço próximo, acessível e feito com atenção aos detalhes.</p>
        <a class="text-link" href="pesquisa.php">Explore a nossa loja <span aria-hidden="true">→</span></a>
      </div>
    </section>

    <section class="about-cta">
      <span class="cta-spark cta-spark-one" aria-hidden="true">✳</span>
      <span class="cta-spark cta-spark-two" aria-hidden="true">✳</span>
      <p>É bom ter um mercado por perto.</p>
      <h2>Bem-vindo ao seu Cantinho.</h2>
      <a href="index.php#categorias" class="about-button about-button-light">
        Fazer compras
        <svg class="about-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
      </a>
    </section>
  </main>

  <?php require __DIR__ . '/componentes/footer.php'; ?>
  <script src="javascript.js"></script>
</body>
</html>