<?php
$aboutStyleVersion = substr(hash_file('sha256', __DIR__ . '/conheca.css') ?: '1', 0, 16);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#075e61">
    <meta name="description" content="Conheça a história, a visão e os idealizadores da Acessa+ Saúde.">
    <title>Conheça a Acessa+ Saúde</title>
    <link rel="stylesheet" href="/conheca.css?v=<?= htmlspecialchars($aboutStyleVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="conheca-page">
    <div class="about-shell">
        <header class="about-header">
            <a class="about-brand" href="/" aria-label="Voltar para a página inicial da Acessa+ Saúde">
                <img src="/img/logo-transparent.png" alt="Acessa+ Saúde">
            </a>
            <nav class="about-nav" aria-label="Navegação da apresentação">
                <a href="#historia">Nossa visão</a>
                <a href="#equipe">Idealizadores</a>
                <a class="nav-cta" href="/">Entrar no site <span aria-hidden="true">↗</span></a>
            </nav>
        </header>

        <main>
            <section class="about-hero" aria-labelledby="hero-title">
                <div class="hero-copy">
                    <div class="about-kicker"><span>01</span> QUEM ESTÁ POR TRÁS</div>
                    <h1 id="hero-title">A saúde pode ser <em>mais simples.</em></h1>
                    <p class="hero-lead">A Acessa+ Saúde nasceu para aproximar pessoas, profissionais e serviços de saúde em uma experiência mais organizada, humana e acessível.</p>
                    <div class="hero-actions">
                        <a class="about-button about-button--primary" href="#equipe">Conheça nossa equipe <span aria-hidden="true">↓</span></a>
                        <a class="about-button about-button--quiet" href="#historia">Entenda o projeto <span aria-hidden="true">↘</span></a>
                    </div>
                    <div class="hero-signature"><span class="signature-dot"></span> Tecnologia, gestão e cuidado lado a lado com você.</div>
                </div>
                <div class="hero-visual" aria-label="Identidade Acessa+ Saúde">
                    <div class="visual-orbit visual-orbit--one"></div>
                    <div class="visual-orbit visual-orbit--two"></div>
                    <div class="visual-grid"></div>
                    <div class="visual-card">
                        <span class="visual-label">ACESSA+ SAÚDE</span>
                        <div class="visual-heart" aria-hidden="true">♡<b>+</b></div>
                        <p>Mais acesso.<br>Mais cuidado.<br>Uma saúde melhor.</p>
                    </div>
                    <span class="visual-note">CUIDAR TAMBÉM<br>É CONECTAR.</span>
                </div>
            </section>

            <section class="about-section manifesto-section" id="historia" aria-labelledby="history-title">
                <div class="section-marker"><span>02</span><i></i></div>
                <div class="manifesto-grid">
                    <div>
                        <p class="eyebrow">UMA IDEIA COM PROPÓSITO</p>
                        <h2 id="history-title">Quando tecnologia encontra o cuidado, o acesso ganha outro caminho.</h2>
                    </div>
                    <div class="manifesto-copy">
                        <p>A Acessa+ Saúde é uma plataforma pensada para facilitar o acesso a informações, agendamentos e serviços de saúde. Nosso ponto de partida é simples: reduzir filas, esperas e deslocamentos desnecessários.</p>
                        <p>O projeto une a experiência de quem conhece a saúde de perto com a vontade de construir uma solução que ajude a pessoa a agendar, acompanhar a fila e chegar à UBS na hora certa.</p>
                    </div>
                </div>
                <div class="pillar-row" aria-label="Pilares do projeto">
                    <article class="pillar-card">
                        <span class="pillar-number">01</span>
                        <span class="pillar-icon" aria-hidden="true">+</span>
                        <h3>Mais acesso</h3>
                        <p>Informação e caminhos mais simples para chegar ao atendimento.</p>
                    </article>
                    <article class="pillar-card pillar-card--highlight">
                        <span class="pillar-number">02</span>
                        <span class="pillar-icon" aria-hidden="true">♡</span>
                        <h3>Mais cuidado</h3>
                        <p>Uma experiência digital que respeita o tempo e a história de cada pessoa.</p>
                    </article>
                    <article class="pillar-card">
                        <span class="pillar-number">03</span>
                        <span class="pillar-icon" aria-hidden="true">↗</span>
                        <h3>Uma saúde melhor</h3>
                        <p>Melhorias contínuas para conectar gestão, profissionais e comunidade.</p>
                    </article>
                </div>
                <div class="solution-note">
                    <div class="solution-heading">
                        <p class="eyebrow">COMO FUNCIONA</p>
                        <h3>E se você só precisasse ir à UBS na hora certa?</h3>
                        <p>Menos fila, menos espera e mais previsibilidade para quem precisa de atendimento.</p>
                    </div>
                    <div class="solution-steps">
                        <div><span>01</span><strong>Agendar</strong><small>Escolha UBS, especialidade, data e horário.</small></div>
                        <div><span>02</span><strong>Acompanhar</strong><small>Veja sua posição na fila e a previsão de atendimento.</small></div>
                        <div><span>03</span><strong>Chegar na hora</strong><small>Vá à unidade quando estiver perto da sua vez.</small></div>
                    </div>
                </div>
            </section>

            <section class="about-section team-section" id="equipe" aria-labelledby="team-title">
                <div class="team-heading">
                    <div>
                        <div class="section-marker"><span>03</span><i></i></div>
                        <p class="eyebrow">A EQUIPE</p>
                        <h2 id="team-title">Tecnologia, gestão e saúde na mesma equipe.</h2>
                    </div>
                    <p class="team-heading-note">Conheça quem transforma a ideia em plataforma — com visão prática, colaboração e vontade de fazer diferente.</p>
                </div>

                <div class="team-list">
                    <article class="person-card">
                        <div class="person-photo-wrap">
                            <span class="person-index">01</span>
                            <img class="person-photo" src="/img/renata-goes-team.png" alt="Renata Goes">
                        </div>
                        <div class="person-content">
                            <p class="person-role">VISÃO DA SAÚDE E DO ATENDIMENTO</p>
                            <h3>Renata Goes</h3>
                            <p class="person-title">Visão da saúde e do atendimento, idealizadora e co-desenvolvedora da plataforma</p>
                            <p class="person-education">Técnica em Radiologia | cursando Técnico em Informática</p>
                            <ul class="contribution-list">
                                <li>Idealização e concepção inicial do projeto.</li>
                                <li>Iniciação e estruturação do MVP.</li>
                                <li>Contribuição para o desenvolvimento e aprimoramento do site.</li>
                                <li>Aplicação da experiência na área da saúde para orientar melhorias na plataforma.</li>
                                <li>Identificação de necessidades dos usuários e oportunidades de evolução do produto.</li>
                            </ul>
                        </div>
                    </article>

                    <article class="person-card person-card--reverse">
                        <div class="person-photo-wrap">
                            <span class="person-index">02</span>
                            <img class="person-photo" src="/img/welson-pereira-team.png" alt="Welson Pereira">
                        </div>
                        <div class="person-content">
                            <p class="person-role">TECNOLOGIA E DESENVOLVIMENTO</p>
                            <h3>Welson Pereira</h3>
                            <p class="person-title">Co-idealizador e desenvolvedor da plataforma</p>
                            <p class="person-education">Cursando Técnico em Informática</p>
                            <ul class="contribution-list">
                                <li>Contribuição para o desenvolvimento do site.</li>
                                <li>Proposição e implementação de melhorias na plataforma.</li>
                                <li>Colaboração na evolução das funcionalidades do MVP.</li>
                                <li>Apoio na identificação e correção de problemas.</li>
                                <li>Participação no aprimoramento técnico e no crescimento do projeto.</li>
                            </ul>
                        </div>
                    </article>
                </div>

                <div class="team-statement"><span>+</span><p>Conseguimos executar porque unimos tecnologia, gestão e saúde — e o MVP já está em fase de testes.</p></div>
            </section>

            <section class="about-closing" aria-labelledby="closing-title">
                <div class="closing-mark" aria-hidden="true">♡<b>+</b></div>
                <div>
                    <p class="eyebrow">PARA SEGUIR DE PERTO</p>
                    <h2 id="closing-title">O cuidado começa quando o acesso fica mais perto.</h2>
                    <p>Visite a Acessa+ Saúde e acompanhe essa construção com a gente.</p>
                </div>
                <a class="about-button about-button--light" href="/">Conhecer o site <span aria-hidden="true">↗</span></a>
            </section>
        </main>

        <footer class="about-footer">
            <div class="footer-brand"><img src="/img/logo-transparent.png" alt="Acessa+ Saúde"><span>Informação e cuidado ao seu alcance.</span></div>
            <div class="footer-meta"><span>© 2026 Acessa+ Saúde</span><a href="mailto:acessamaissaude@gmail.com">acessamaissaude@gmail.com</a><a href="/">Voltar ao site principal ↗</a></div>
        </footer>
    </div>
</body>
</html>
