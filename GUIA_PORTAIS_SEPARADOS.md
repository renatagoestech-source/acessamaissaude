# Acessa+ Saúde — Portais separados

> **Nota da versão corrigida:** este guia registra funcionalidades de versões anteriores. Nesta distribuição, o portal da clínica não mostra a aba/atalhos de prontuário nem disponibiliza essas rotas na API; dados existentes no banco são preservados. Consulte `IMPLEMENTACAO_AGENDA_SEGURANCA.md` para as instruções atuais de agenda e salvamento.

## Estrutura recomendada

A plataforma possui uma entrada única, mas três caminhos separados:

| Caminho | Quem usa | Identificação | Agenda | Dados
|---|---|---|---|---|
| UBS / SUS | Paciente da rede pública | Cartão SUS + UBS | `consultas` | UBS e rede pública
| Clínica particular | Paciente de clínica | Nome, e-mail e celular | `consultas_profissionais` | Somente a clínica escolhida
| Profissional | Médico, psicólogo, enfermeiro ou clínica | E-mail e senha profissional | Agenda própria | Pacientes e prontuários vinculados

O banco pode ser compartilhado tecnicamente, mas as tabelas, sessões e autorizações são diferentes. O paciente de clínica não precisa informar Cartão SUS e não recebe as telas de UBS.

## Entrada inicial

Ao abrir o sistema, o usuário escolhe:

1. **Paciente SUS / UBS**: leva ao cadastro público com Cartão SUS e escolha da UBS.
2. **Profissional / clínica**: abre o login e cadastro profissional.
3. **Paciente de clínica particular**: usa o link público recebido da clínica.
4. **Desenvolvedor / UBS**: abre o acesso administrativo.

## Link público da clínica

Quando o profissional cria uma conta, o sistema gera um endereço semelhante a:

```text
http://localhost/acessa_saude/?clinica=clinica-vida-plena
```

O profissional deve copiar esse endereço e divulgar em:

- Instagram.
- WhatsApp.
- Cartão de visita.
- Site profissional.
- Google Business Profile.

No portal profissional, a aba **Minha marca** permite alterar nome, especialidade, registro, telefone, WhatsApp, endereço, modalidade, valor, horário de funcionamento, apresentação, aviso público e cor principal. Também é possível enviar uma logo em PNG, JPG ou WEBP de até 5 MB. A logo, o horário e o aviso são exibidos automaticamente na página pública do link.

O paciente abre esse link e vê somente:

- Nome da clínica.
- Especialidade.
- Apresentação.
- Modalidade.
- Telefone ou WhatsApp.
- Endereço.
- Formulário de solicitação de consulta.

## Agendamento particular

O paciente informa:

- Nome.
- E-mail.
- Celular.
- Data desejada.
- Horário desejado.
- Assunto da consulta.

Na aba **Prontuários**, ao selecionar o paciente agendado, o profissional pode completar o cadastro complementar com data de nascimento, endereço, condições de saúde preexistentes, alergias, medicamentos e informações adicionais. Esses dados ficam protegidos pelo vínculo daquele profissional.

A solicitação é criada com status `solicitada`. A clínica confirma ou organiza o horário no portal profissional. Esse fluxo não consulta UBS e não exige Cartão SUS.

## Fluxo UBS / SUS

O paciente entra pela opção **Paciente SUS / UBS**, informa os dados e seleciona a UBS. O sistema mantém:

- UBS de referência.
- Cartão SUS.
- Especialidades da unidade.
- Fila da rede pública.
- Campanhas e documentos.
- Resultados de exames da unidade.

Nenhuma clínica particular é exibida nesse fluxo.

## Fluxo do profissional

O profissional entra pela opção **Profissional / clínica** e usa sua própria conta. No portal, ele acessa:

- Pacientes vinculados.
- Agenda particular.
- Prontuários.
- Personalização.
- Link público da clínica.
- Planos e assinatura.

O profissional somente acessa pacientes associados à própria conta. O servidor verifica esse vínculo em cada operação de prontuário e agenda.

## Fluxo do desenvolvedor

O desenvolvedor continua com acesso global para:

- Administrar UBS.
- Criar novas UBS.
- Consultar auditoria.
- Responder suporte.
- Alterar o modo institucional.
- Consultar pagamentos profissionais.
- Aprovar pagamentos.
- Ativar ou cancelar assinaturas.

## Pagamentos

A assinatura profissional é criada como `pendente`. Após o recebimento, o desenvolvedor pode aprovar o pagamento no painel. A aprovação ativa o plano por um mês.

Para cobrança automática, ainda é necessário configurar um gateway e webhooks assinados. O sistema não armazena cartão e não deve receber dados de cartão diretamente.

## Instalação e atualização

1. Faça backup do banco e de `uploads/exames`.
2. Copie os arquivos para a pasta do XAMPP.
3. Inicie Apache e MySQL.
4. Acesse `install.php` apenas em instalação nova.
5. Em um banco existente, a conexão executa as migrações compatíveis sem apagar os dados.
6. Atualize a página com `Ctrl + F5`.

## O que não deve ser misturado

- Paciente SUS não deve aparecer no portal público da clínica sem vínculo explícito.
- Paciente particular não deve ser obrigado a informar Cartão SUS.
- Consultas particulares não devem entrar na fila da UBS.
- Prontuário profissional não deve aparecer na área de exames da UBS.
- Uma clínica não deve acessar pacientes de outra clínica.

## Próximas etapas para produção

Antes de comercializar, recomenda-se adicionar confirmação de e-mail, recuperação de senha, autenticação em dois fatores, disponibilidade de horários configurável, confirmação de consulta pela clínica, política de privacidade, consentimento e gateway de pagamento real.

## Agenda, pagamento e recibo

Quando o paciente solicita uma consulta pelo link público, o sistema mostra imediatamente uma confirmação com protocolo, data e horário. Na aba **Agenda**, o profissional pode confirmar ou alterar o status, cancelar com motivo e reagendar para outra data e horário. O sistema impede escolher um horário já ocupado pela mesma clínica.

A agenda também permite registrar a forma de pagamento como PIX, dinheiro, cartão, convênio ou não informada, além de marcar o pagamento como pendente, pago ou dispensado. O botão **Recibo** gera um documento pronto para impressão. O botão **WhatsApp** abre a conversa do paciente com uma mensagem resumida do recibo; o profissional revisa e envia pelo próprio WhatsApp.


## Acessos diretos e permissões administrativas

- A rede SUS/UBS pode ser acessada diretamente em `/ubs`; essa tela mantém o acesso administrativo no topo e leva ao cadastro do paciente SUS.
- A clínica pode ser acessada diretamente em `/clinica`, sem passar pela tela que apresenta os dois portais. O login mantém cadastro profissional e recuperação de senha. Links públicos com `?clinica=...` continuam funcionando.
- Contas UBS podem consultar a lista de funcionários da própria unidade, mas não podem adicionar, editar ou excluir funcionários. A gravação é protegida também no servidor.
- A gestão de funcionários fica para a Secretaria e para o desenvolvedor; as permissões globais existentes do desenvolvedor para UBS, configurações e administração das contas/assinaturas particulares são preservadas.
- O painel da clínica exibe um único controle claro para encerrar a sessão.

- O login profissional não apresenta links para a UBS nem para a Secretaria. O acesso administrativo do desenvolvedor fica na entrada administrativa da UBS.
