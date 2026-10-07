# Acessa+ Saúde — Guia de uso

## O que foi criado

Esta versão usa uma única base de código com dois modos de operação:

- **UBS / rede pública:** mantém o fluxo de unidades, pacientes, campanhas, funcionários, exames e atendimento por UBS.
- **Profissional particular / clínica:** permite personalizar o nome exibido, especialidade, registro profissional, telefone, WhatsApp, endereço, modalidade de atendimento, valor da consulta, apresentação e cores.

O modo é alterado pelo desenvolvedor no painel administrativo em **Modo e personalização**.

## 1. Instalação no XAMPP

1. Inicie o **Apache** e o **MySQL** no painel do XAMPP.
2. Extraia o pacote para:

   `C:\xampp\htdocs\acessa_saude`

3. Abra no navegador:

   `http://localhost/acessa_saude/install.php`

4. Aguarde a confirmação da instalação.
5. Depois abra:

   `http://localhost/acessa_saude/`

6. O instalador cria um arquivo `install.lock` depois da primeira execução. Isso evita que o banco seja apagado acidentalmente.

## 2. Atualização de uma instalação existente

A versão atual possui uma migração automática. Ela verifica e cria as colunas e tabelas novas, incluindo:

- Assunto da consulta.
- Motivo do cancelamento.
- Protocolos e respostas de suporte.
- Auditoria.
- Lista de espera.
- Configuração dos modos.

Por isso, em uma instalação existente, primeiro faça backup e depois substitua os arquivos do projeto. Não é necessário apagar o banco. Se aparecer um erro antigo, confirme se o MySQL está ativo e atualize a página com `Ctrl + F5`.

## 3. Uso no modo UBS / rede pública

1. Entre na página inicial.
2. Cadastre o paciente com nome, celular, Cartão SUS e UBS de referência.
3. O paciente passa a visualizar somente as informações da UBS escolhida.
4. Acesse **Agendar consulta**.
5. Escolha a especialidade.
6. Informe o assunto, como pré-natal, citologia, vacinação ou acompanhamento de hipertensão.
7. Escolha uma data.
8. Confirme o agendamento e guarde o comprovante.
9. Em uma agenda lotada, use **Entrar na lista de espera**.
10. Em **Minhas consultas**, o paciente pode consultar, imprimir ou cancelar a consulta. O cancelamento pode ter um motivo.

## 4. Uso no modo profissional particular / clínica

1. Na página inicial, abra **Acesso administrativo**.
2. Entre com a conta do desenvolvedor.
3. Abra a aba **Modo e personalização**.
4. Selecione **Profissional particular / clínica**.
5. Preencha:
   - Nome do profissional ou clínica.
   - Especialidade.
   - Registro profissional.
   - Telefone e WhatsApp.
   - Endereço.
   - Modalidade presencial, online ou domiciliar.
   - Valor da consulta.
   - Texto de apresentação.
   - Cores da identidade visual.
6. Clique em **Salvar configuração**.
7. Atualize a página para conferir o modo e a identidade visual.
8. Configure a unidade inicial com o nome do consultório ou clínica e suas especialidades.
9. Use a agenda para receber marcações e o campo de assunto para entender o motivo da consulta.

Este modo é uma base de personalização e agenda. Antes de utilizar com dados reais, o profissional ainda deve configurar documentos de privacidade, consentimento, política de cancelamento e a forma de pagamento.

## 5. Acesso administrativo

O acesso administrativo aparece somente na página inicial.

- **UBS:** acessa apenas a própria unidade.
- **Desenvolvedor:** pode selecionar unidades, criar UBS, ver suporte, consultar auditoria e alterar o modo do sistema.

A aba **Auditoria** registra ações administrativas importantes, como login, alterações de consulta, criação de UBS, exames e mudanças de suporte.

## 6. Suporte ao paciente

1. Clique em **Suporte ao paciente**.
2. Informe nome, celular e mensagem.
3. Envie a solicitação.
4. Guarde o protocolo exibido.
5. Use **Ver meus protocolos** para acompanhar o status e a resposta.
6. O desenvolvedor pode filtrar mensagens por aberta, em atendimento ou resolvida e escrever uma resposta.

## 7. Exames e anexos

A UBS pode cadastrar resultados e anexar PDF, JPG ou PNG de até 10 MB. O arquivo não deve ser acessado por URL pública; ele é entregue pela API somente ao paciente correspondente ou à UBS autorizada.

Faça backup periódico do banco e da pasta:

`uploads/exames/`

## 8. Backup recomendado

Antes de atualizar ou reinstalar:

1. Exporte o banco pelo phpMyAdmin.
2. Copie a pasta `uploads/exames`.
3. Guarde os backups fora da pasta pública do site.
4. Teste a restauração em uma instalação separada.

## 9. Troca de modo

A troca de modo é global para esta instalação. Ela não cria ainda várias contas profissionais independentes no mesmo banco. Para atender vários profissionais simultaneamente, a próxima evolução deve adicionar organizações separadas, cada uma com seus pacientes, agenda, prontuários e identidade visual isolados.

## 10. Antes de uso profissional real

Não use dados reais de pacientes sem revisar com um profissional responsável:

- Política de privacidade e LGPD.
- Consentimento do paciente.
- Controle de acesso por profissional.
- Backup protegido.
- Retenção e exclusão de dados.
- Prontuário adequado à profissão.
- Integração e conciliação de pagamentos.
- Segurança do servidor e HTTPS.

O pacote atual resolve o erro de agendamento em bancos antigos por meio de migração automática e mantém a instalação protegida pelo `install.lock`.
