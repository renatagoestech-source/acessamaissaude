# ACESSA+ SAÚDE — PHP + MySQL + XAMPP

Versão atualizada do sistema de agendamento de UBS, com visual inspirado na identidade da logo Acessa+ Saúde.

## O que foi alterado

- Nome visual do sistema: Acessa+ Saúde
- Logo Acessa+ Saúde incluída em `img/logo.png`
- Interface refeita com as cores da logo: verde-petróleo e turquesa
- Dashboard do paciente após o cadastro
- Menu lateral com acesso rápido
- Área "Minhas consultas"
- Consultas marcadas
- Histórico de consultas realizadas (quando a UBS marcar como realizada)
- Consultas canceladas e consultas em que o paciente não compareceu
- Área "Resultados de exames"
- UBS pode cadastrar resultados de exames para o paciente
- Paciente consulta seus próprios resultados usando o Cartão SUS
- Painel administrativo com opção de marcar uma consulta como realizada
- Painel administrativo com cadastro de resultados de exames
- Acesso administrativo de UBS e desenvolvedor disponível diretamente na primeira página
- Anexo de resultado de exame em PDF, JPG ou PNG, com limite de 10 MB
- Cadastro do paciente sem escolha de unidade na tela inicial; a UBS é escolhida depois, no agendamento
- Aba Perfil para endereço, data de nascimento, condições de saúde, alergias, medicamentos e outras informações
- UBS obrigatória no cadastro; após o login, o paciente visualiza somente a unidade escolhida
- Área administrativa visível somente na tela inicial; o desenvolvedor possui aba de mensagens de suporte e acesso a todas as UBS
- Logo exibida no topo do cadastro e no comprovante impresso
- Botão de suporte corrigido e envio de mensagens testado
- Campo de assunto/motivo da consulta após a escolha da especialidade
- Auditoria das ações administrativas, protocolos e respostas de suporte, lista de espera e motivo de cancelamento
- Central de ajuda, UBS atual no dashboard e notificações reais de lembretes
- Mantido o agendamento, calendário, lembretes, UBS, funcionários e campanhas

## Estrutura

- index.php — interface principal
- script.js — lógica da interface e comunicação com a API
- style.css — identidade visual e responsividade
- api.php — API PHP, regras e banco
- config.php — configuração do MySQL
- common.php — funções auxiliares
- database.sql — banco, tabelas e dados iniciais
- install.php — instalador automático
- img/logo.png — logo Acessa+ Saúde

## Como instalar no XAMPP

1. Abra o XAMPP.
2. Inicie Apache e MySQL.
3. Extraia a pasta `conecta_saude` para:
   `C:\xampp\htdocs\`
4. Acesse:
   `http://localhost/conecta_saude/install.php`
5. Depois acesse:
   `http://localhost/conecta_saude/`

> A instalação recria o banco `conecta_saude` do zero. Faça backup antes de repetir o processo em uma instalação que já tenha dados.

Se você abrir a página principal antes de instalar o banco, o sistema exibirá uma tela de configuração com os links para `install.php` e `diagnostico_xampp.php`. Ele não carregará UBSs fictícias nem permitirá confundir o modo demonstrativo com o sistema real.

O banco continua se chamando `conecta_saude` para facilitar a atualização do projeto anterior. O nome apresentado ao usuário é Acessa+ Saúde.

## Login administrativo inicial

Desenvolvedor:
- Usuário: desenvolvedor
- Senha: 2026

UBS A:
- Usuário: adminA
- Senha: 1234

UBS C:
- Usuário: adminC
- Senha: 1234

UBS D:
- Usuário: adminD
- Senha: 1234

UBS E:
- Usuário: adminE
- Senha: 1234

UBS F:
- Usuário: adminF
- Senha: 1234

## Área do paciente

Depois do cadastro com nome, telefone e Cartão SUS, o paciente entra no dashboard e pode acessar:

- Próximas consultas
- Minhas consultas
- Consultas que já foram realizadas
- Consultas canceladas
- Resultados de exames
- UBS
- Campanhas e avisos

## Como registrar que o paciente foi à consulta

1. Entre em `Área administrativa`.
2. Faça login com o administrador da UBS.
3. Abra a aba `Consultas`.
4. Localize a consulta.
5. Clique em `Marcar como realizada`.
6. Ela passará para o histórico do paciente como `Realizada`.

## Como cadastrar resultado de exame

1. Entre na `Área administrativa`.
2. Abra a aba `Resultados de exames`.
3. Clique em `+ Novo resultado`.
4. Informe o Cartão SUS do paciente.
5. Informe nome, data, resultado e observações.
6. Salve.
7. O paciente poderá abrir `Resultados de exames` na própria área dele.

## Banco de dados

Principais tabelas:

- `ubs`
- `ubs_especialidades`
- `ubs_servicos`
- `ubs_campanhas`
- `ubs_documentos`
- `funcionarios`
- `pacientes`
- `consultas`
- `exames_resultados`
- `administradores`

O acesso administrativo fica no botão **Acesso administrativo** da primeira página, antes do cadastro do paciente. Depois que o paciente entra, os atalhos administrativos desaparecem. O paciente deve escolher a UBS durante o cadastro e, após entrar, a aba UBS mostra somente as informações dessa unidade.

O desenvolvedor pode selecionar qualquer UBS no painel, cadastrar novas unidades e consultar a aba **Mensagens de suporte**, alterando cada demanda para “Em atendimento” ou “Resolvido”.

Ao escolher uma especialidade, o paciente pode explicar o motivo do atendimento, como pré-natal, citologia, acompanhamento de hipertensão ou vacinação. Essa informação aparece no comprovante, no histórico do paciente e no painel da UBS.

O instalador cria um arquivo `install.lock` depois da primeira execução. Isso impede que o banco seja apagado acidentalmente ao acessar novamente `install.php`. Para uma reinstalação intencional, faça backup do banco e dos anexos, remova o arquivo de bloqueio e execute o instalador.

O manual dos modos **UBS / rede pública** e **profissional particular / clínica** está em `GUIA_MODO_UBS_PROFISSIONAL.md`. Para a correção mais recente de expediente, pausas, salvamento e interface do portal profissional, consulte `IMPLEMENTACAO_AGENDA_SEGURANCA.md`.

Para a documentação de referência da versão multiprofissional, consulte também `GUIA_PLATAFORMA_MULTIPROFISSIONAL.md`. Nesta distribuição, a interface de prontuários foi removida; veja a nota de versão nesse guia.

O fluxo de entrada separada para **Paciente SUS / UBS**, **Paciente de clínica particular**, **Profissional** e **Desenvolvedor** está explicado em `GUIA_PORTAIS_SEPARADOS.md`.

Depois de entrar na área do paciente, o menu junto ao nome **Olá, paciente** possui a opção **Perfil**. Nessa área, o paciente pode registrar endereço, data de nascimento, condições de saúde preexistentes, alergias, medicamentos e outras informações relevantes.

Na área administrativa, ao cadastrar um resultado, é possível anexar um arquivo PDF, JPG ou PNG. O arquivo é armazenado fora do acesso público direto e só pode ser aberto pelo paciente correspondente ou pela UBS autorizada.

O padrão é MySQL em `127.0.0.1:3306`, usuário `root` sem senha. Para outro ambiente, defina `ACESSA_DB_HOST`, `ACESSA_DB_PORT`, `ACESSA_DB_USER`, `ACESSA_DB_PASS` e, se necessário, `ACESSA_DB_NAME` no ambiente do PHP/Apache. O instalador, o diagnóstico e a API usam essas mesmas configurações.

## Segurança implementada

- PDO com prepared statements
- Senhas administrativas com `password_hash`
- Sessão PHP na área administrativa
- Controle de acesso por UBS
- Validação de datas e horários no servidor
- Proteção contra agendamento duplicado
- Proteção de resultados de exames por Cartão SUS

Para uso real em saúde, ainda seriam necessários controles adicionais de segurança, privacidade, LGPD, autenticação forte, HTTPS, logs e políticas formais de acesso.


CORREÇÃO DO BANCO - VERSÃO ATUAL
=================================
Se uma versão anterior apresentou o erro MySQL #1005 / Foreign key constraint is incorrectly formed, use o instalador desta versão.

1. Feche o phpMyAdmin e confirme Apache + MySQL iniciados no XAMPP.
2. Coloque a pasta conecta_saude em C:\xampp\htdocs\
3. Abra: http://localhost/conecta_saude/install.php
4. O instalador remove somente o banco técnico conecta_saude e cria novamente todas as tabelas na ordem correta.
5. Depois clique em "Abrir Acessa+ Saúde".

ATENÇÃO: executar install.php recria o banco conecta_saude e apaga os dados que estiverem nele. Faça isso apenas na instalação/reinstalação do projeto.

Se preferir usar o phpMyAdmin manualmente, importe o arquivo database.sql. Ele também começa recriando o banco conecta_saude, evitando conflito com uma estrutura antiga.


CORREÇÃO DO BANCO
A tabela consultas não usa mais colunas GENERATED com DATE_FORMAT/TIME_FORMAT, pois versões do MariaDB usadas pelo XAMPP podem rejeitar essas funções em GENERATED ALWAYS AS. Os conflitos de horário e duplicidade de consulta são validados no servidor PHP com transação.


## Atualizações desta versão

- Logo no cartão branco superior do menu lateral, conforme o layout de referência.
- Botões de início e navegação interna funcionando com retorno ao dashboard.
- Botão fixo de suporte ao paciente no canto inferior esquerdo, com registro das solicitações no banco.
- Cadastro do paciente vinculado à UBS de referência.
- Desenvolvedor ou Administrador da Secretaria pode cadastrar novas UBS, especialidades, usuário e senha de acesso.
- Agendamento por ordem de fila, sem escolha de horário, com limite de 12 vagas por dia e por especialidade.
- Paciente pode cancelar ou reagendar; o comprovante mostra a posição na fila e a marca discreta da logo na impressão.
- Tabela de notificações criada para futura integração com SMS, WhatsApp, e-mail ou push. Os lembretes são enfileirados automaticamente um dia antes da consulta.

A integração efetiva com um provedor de mensagens exige configurar credenciais e um trabalhador/cron para consumir a tabela `notificacoes` e atualizar `status`, `enviada_em` e `erro`.
