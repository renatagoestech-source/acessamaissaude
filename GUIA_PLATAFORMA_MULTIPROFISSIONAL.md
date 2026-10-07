# Plataforma Acessa+ Saúde — Multi-profissional

> **Nota da versão corrigida:** este guia contém referência a recursos de prontuário de versões anteriores. Nesta distribuição, a aba/atalhos e as rotas de API de prontuário foram retirados; os registros já existentes no banco não são apagados. Para comportamento atual do expediente, pausas e salvamento, consulte `IMPLEMENTACAO_AGENDA_SEGURANCA.md`.

## O que foi implementado

A versão agora permite múltiplas contas profissionais no mesmo sistema, com isolamento por `profissional_id`.

Cada profissional possui:

- Conta própria com e-mail e senha.
- Pacientes vinculados somente à sua conta.
- Agenda independente.
- Prontuários separados.
- Personalização individual.
- Assinatura e plano próprios.
- Pagamentos com status e aprovação controlados.

## Como criar uma conta profissional

1. Abra a página inicial.
2. Clique em **Acesso administrativo**.
3. Clique em **Entrar como profissional**.
4. Informe nome, e-mail, especialidade e uma senha com pelo menos 8 caracteres.
5. Clique em **Criar conta**.
6. O portal profissional será aberto automaticamente.

## Como vincular um paciente

1. No portal, abra **Pacientes**.
2. Informe o Cartão SUS do paciente.
3. Clique em **Vincular paciente**.
4. O sistema cria um vínculo exclusivo entre o profissional e o paciente.
5. Outro profissional não conseguirá abrir o prontuário desse paciente sem um vínculo próprio.

O vínculo registra data de consentimento. Em uma versão de produção, o ideal é substituir o Cartão SUS por convite seguro, aceite do paciente e autenticação própria do paciente.

## Como usar a agenda independente

1. Abra **Agenda**.
2. Escolha um paciente vinculado.
3. Informe data e horário.
4. Digite o assunto da consulta.
5. Clique em **Agendar**.

A agenda profissional fica na tabela própria `consultas_profissionais` e não se mistura às consultas das UBS.

## Como usar o prontuário

1. Abra **Prontuários**.
2. Selecione um paciente vinculado.
3. Escolha o tipo de registro.
4. Escreva a evolução ou anotação clínica.
5. Clique em **Salvar registro**.

O prontuário só é retornado quando a sessão profissional corresponde ao vínculo do paciente. O sistema aplica uma verificação de autorização antes de listar ou salvar registros.

## Como personalizar o perfil

1. Abra **Minha marca**.
2. Informe nome, especialidade, registro, telefone, WhatsApp, endereço e modalidade.
3. Defina o valor da consulta e o texto de apresentação.
4. Escolha a cor principal.
5. Salve as alterações.

O modelo de dados já possui campo para logo. O upload da logo deve ser conectado a um armazenamento protegido antes de uso público em produção.

## Planos e assinaturas

1. Abra **Assinatura**.
2. Escolha um plano:
   - Essencial.
   - Profissional.
   - Clínica.
3. O sistema cria uma assinatura com status **pendente**.
4. Também cria um pagamento pendente associado à assinatura.
5. O desenvolvedor pode consultar os pagamentos e alterar o status após confirmar o recebimento.

Os status possíveis incluem:

- Pendente.
- Aprovado.
- Recusado.
- Estornado.
- Manual.

Quando o pagamento é aprovado ou marcado como manual, a assinatura passa para **ativa** por um mês.

## Pagamento online

O pacote possui a estrutura de assinaturas e pagamentos, mas não inclui uma cobrança automática real. Para ativar pagamentos online, é necessário integrar um gateway, como Stripe, Mercado Pago ou outro provedor escolhido.

A integração de produção deve usar:

- Checkout hospedado pelo gateway.
- Webhook assinado.
- Nunca armazenar número completo de cartão.
- Idempotência para evitar cobranças duplicadas.
- Tratamento de estorno e inadimplência.
- Registro da referência externa do gateway.

Não coloque chaves secretas no JavaScript ou no repositório público.

## Isolamento e segurança

As consultas de pacientes, prontuários e agendas usam o profissional autenticado. Não basta esconder telas; a API também verifica o vínculo no banco.

Antes de publicar, recomenda-se acrescentar:

- Confirmação de e-mail.
- Recuperação de senha.
- Autenticação em dois fatores.
- Limite de tentativas de login.
- HTTPS obrigatório.
- Backup criptografado.
- Logs de acesso ao prontuário.
- Consentimento formal do paciente.
- Política LGPD revisada.
- Retenção e exclusão de dados definida.

## Banco de dados

As novas tabelas principais são:

- `profissionais`.
- `profissional_pacientes`.
- `agendas_profissionais`.
- `consultas_profissionais`.
- `prontuarios_profissionais`.
- `planos_assinatura`.
- `assinaturas_profissionais`.
- `pagamentos_profissionais`.

A conexão também executa migração compatível para instalações existentes. Faça backup antes de atualizar.

## Limitação atual importante

O sistema agora oferece a base funcional multi-profissional e isolamento de dados. Para uma operação comercial completa, ainda devem ser finalizados o gateway de pagamento, recuperação de senha, verificação de e-mail, upload protegido da logo e revisão jurídica/LGPD.
