# Acessa+ Saúde Inteligente — guia da Fila Viva

**Versão de trabalho:** 6 de novembro de 2026
**Objetivo:** apoiar a avaliação do projeto pela Secretaria Municipal de Saúde de Triunfo–PE, sem afirmar aprovação, implantação oficial ou integração não realizada.

## Resumo

A branch `feature/acessa-mais-saude-inteligente` acrescenta ao Acessa+ Saúde um fluxo administrativo de encaminhamentos, fila por unidade/especialidade, validação humana de prioridade, registro de vagas liberadas, oferta controlada, resposta do paciente e indicadores gerenciais. O sistema não diagnostica, não calcula prioridade clínica e não reorganiza automaticamente a fila. A versão atual foi preparada para validação funcional com dados de teste; ainda não foi publicada nem conectada a uma base de produção.

## O que já está no código

| Frente | Estado nesta versão | Observação operacional |
|---|---|---|
| Encaminhamentos e protocolo | Implementado | UBS registra pelo Cartão SUS e escolhe unidade/especialidade/dia; fluxo tradicional da lista de espera também recebe protocolo e data de entrada. |
| Fila por unidade, especialidade e situação | Implementado | Escopo UBS e Secretaria aplicado no servidor. A fila aparece cronologicamente; categoria de prioridade não altera automaticamente a ordem. |
| Validação de prioridade | Implementado com responsabilidade humana | Conta da Secretaria registra categoria e observação administrativa. A Prefeitura deve designar formalmente os usuários e definir os critérios antes do piloto. Não inserir diagnóstico nas observações. |
| Agenda, confirmação e desfecho | Parcialmente integrado | O painel reutiliza consultas existentes e registra confirmação, reagendamento, cancelamento, realização e falta. O catálogo atual não contém horários por profissional para a UBS. |
| Reposição de vaga | Implementado com controle manual | Cancelamento em dia útil futuro registra vaga. Operador escolhe manualmente um encaminhamento elegível da mesma unidade/especialidade/data, com prioridade validada. Não há promoção ou decisão automática. |
| Aceite/recusa | Implementado | Link bearer aleatório de uso único, armazenado no banco somente como hash, com prazo de até 24h ou até o início do dia da vaga. Não se oferta vaga passada, do dia corrente ou em dia sem atendimento. |
| Registro de presença | Implementado | Falta e atendimento são registrados por usuário autorizado e auditados. A taxa só aparece com denominador de consultas que já têm desfecho. |
| Painel de indicadores | Implementado | Filtros por período, unidade e especialidade; fila, pendências, espera, agenda, faltas, confirmações, vagas e evolução mensal. Métricas sem amostra adequada ficam indisponíveis. |
| Notificações externas | Não integrado | O sistema cria aviso interno pendente e pode preparar um link `wa.me` para envio manual. Não envia SMS, WhatsApp ou e-mail automaticamente. |
| e-SUS APS | Não integrado | Integração depende de levantamento técnico, autorização, padrões e responsabilidades definidos com a Secretaria. |

### Importante sobre o horário do atendimento

A base atual usa limite diário por especialidade, não uma grade real de horários por profissional. Ao aceitar uma oferta, o sistema registra o aceite e a data, mas apresenta o horário como **a confirmar pela UBS**. A unidade precisa informar o horário ao paciente e registrar a confirmação final. A ocupação mostrada no painel é estimativa baseada no limite diário configurado e nos dias considerados de funcionamento, não uma ocupação calculada de slots reais.

### Regras de oferta nesta versão

1. Uma vaga só é registrada para data futura em dia de funcionamento.
2. A Secretaria valida a categoria; isso não gera ordenação clínica automática.
3. A interface lista candidatos pendentes que coincidam com unidade, especialidade e data e cuja prioridade tenha sido validada.
4. O operador escolhe a pessoa. O aviso fica pendente; a equipe inicia manualmente o contato pelo WhatsApp ou outro canal autorizado.
5. O token permite uma única resposta, aceitar ou recusar. Se aceito, o sistema cria o agendamento pendente de definição/confirmacão de horário. Se recusado ou expirado, a vaga volta a ficar disponível para outra oferta humana.

## Indicadores e interpretações

- **Fila/encaminhamentos:** registros no intervalo de entrada selecionado; o total é calculado por consulta agregada, sem depender do limite de linhas exibidas.
- **Espera média e mediana:** intervalo entre data de encaminhamento e data de consulta, somente para encaminhamentos vinculados a consulta realizada. Não representa o tempo atual de quem ainda está pendente.
- **Faltas:** quantidade de consultas com desfecho `faltou`; a taxa é `faltas ÷ (faltas + consultas realizadas)` e não aparece sem base.
- **Confirmação:** confirmadas ÷ consultas agendadas com estado de confirmação conhecido.
- **Vagas liberadas/reaproveitadas:** quantidade de cancelamentos futuros registrados, ofertas aceitas e proporção de vagas liberadas que terminaram preenchidas; oferecer não significa que a vaga foi preenchida.
- **Ocupação estimada:** consultas ocupadas ÷ capacidade configurada (limite diário por especialidade × dias úteis). Não substituir relatório oficial de agenda enquanto não houver grade por profissional e horários disponíveis no sistema.
- **Evolução mensal:** entradas de encaminhamento e consultas realizadas por mês, conforme os dados cadastrados no banco.

Os números citados na proposta — 14.241 atendimentos em 2025, 236 pessoas na fila de psiquiatria, mediana de 288 dias e 318 dias para a coorte neurodivergente — **não foram inseridos como dados do sistema**. O documento-base recomenda confirmá-los com a fonte oficial antes de qualquer divulgação ou comparação.

## Piloto recomendado para Triunfo–PE

O escopo sugerido pelo PDF é começar no Ambulatório de Especialidades Médicas com **uma ou duas especialidades escolhidas pela Secretaria**. Não pré-configure uma especialidade, uma regra de prioridade ou uma linha de base sem decisão oficial.

### Fases

1. **Diagnóstico e pactuação:** obter o edital completo; mapear fluxos em papel/digitais, unidades, ambulatório, profissionais, agenda, critérios de prioridade, sistemas em uso, papéis autorizados e responsáveis pelo tratamento de dados.
2. **Preparação:** escolher uma ou duas especialidades; revisar os cadastros/limites de capacidade; confirmar regras e prazo de resposta; designar usuários da Secretaria e UBS; preparar dados fictícios e roteiro de treinamento.
3. **Piloto controlado:** registrar encaminhamentos, validar prioridade manualmente, operar confirmações e faltas, testar cancelamento/reagendamento e uma oferta de vaga sem envio automático.
4. **Medição:** estabelecer linha de base antes do piloto e comparar demanda, tempos, faltas, confirmações e vagas ocupadas/reaproveitadas, com período e denominadores explícitos.
5. **Avaliação e expansão:** recolher feedback de pacientes e equipes, corrigir problemas, produzir relatório e só então decidir expansão para outras especialidades/unidades.

### Decisões que a Secretaria precisa tomar

- critérios e categorias de prioridade, quem pode validá-los e como documentar a decisão;
- unidade(s), especialidades, população e período do piloto;
- procedimento de contato, janela de resposta, canal autorizado e plano se não houver telefone;
- como atribuir horário e profissional após aceite de vaga;
- indicadores de linha de base e fonte oficial para conferência;
- treinamento, suporte, responsáveis, cronograma, custos e condições de expansão.

## Privacidade, segurança e governança

O módulo aplica autorização no servidor, escopo por UBS e cidade para contas da Secretaria, token de resposta armazenado com hash, validação CSRF nas operações, auditoria de ações e conteúdo de mensagem sem diagnóstico. A equipe deve revisar o controle de acesso do produto como um todo antes de dados reais.

**Ainda não substituído pelo código:** análise formal de LGPD e bases legais, acordo de responsabilidades entre Prefeitura e equipe, inventário e retenção/descarte, backups protegidos e testados, plano de resposta a incidentes, monitoramento/alertas, avaliação de vulnerabilidades e validação de segurança da infraestrutura. Até completar esses itens, usar somente registros fictícios ou ambiente de teste aprovado.

## Integrações futuras

- **e-SUS APS:** planejar estudo de viabilidade, padrões, autorização e fluxo de consentimento/compartilhamento. Não há integração funcional nesta versão.
- **SMS/WhatsApp:** escolher provedor, revisar opt-in e modelos de mensagem, credenciais, custos, monitoramento e tratamento de falhas. O envio atual é manual.
- **Agenda por profissional/horário:** requer cadastro de profissionais habilitados, slots reais, regras locais e integração com a agenda utilizada pelo ambulatório.

## Migração e validação técnica

- **MySQL/TiDB:** as alterações aditivas estão incluídas no bootstrap `config.php` e serão aplicadas quando uma instância atualizada fizer a conexão. Não foram executadas em produção; antes de publicar, obter backup testado e validar em cópia/homologação.
- **Supabase/PostgreSQL:** `migrations/20261106_fila_viva.sql` foi aplicada em 6 de novembro de 2026 ao projeto Supabase `acessamaissaude`, confirmado pelo usuário como homologação. O registro da migration, as colunas esperadas e RLS nas duas tabelas novas foram verificados. A migration é aditiva e também preenche `encaminhado_em` nas entradas legadas. A produção não foi alterada.
- **Vercel Preview:** ainda não conectado ao banco de homologação. Falta configurar `DATABASE_URL` como variável sensível, somente no escopo Preview e para a branch de avaliação; não reutilizar as variáveis TiDB de produção.
- **Instalação nova:** `database.sql` contém `DROP DATABASE`; nunca executar sobre uma base existente.
- **Testes concluídos neste trabalho:** lint de todos os arquivos PHP, `node --check script.js`, `git diff --check`, testes `postgres_compat`, `postgres_session_handler`, `professional_session` e `frontend_helpers`.
- **Teste ainda necessário:** configurar o Preview para conectar ao Supabase de homologação e executar smoke test CRUD/concorrência, permissões por perfil, expiração, aceite/recusa, falta, cancelamento e reagendamento. Não foi confirmado backup/snapshot do staging nesta sessão; fazê-lo antes de novos testes com escrita.

## Preparação para envio ao edital

Antes de submeter: (1) obter e conferir edital completo, elegibilidade, prazos, orçamento, contrapartidas e propriedade intelectual; (2) validar as estatísticas com a fonte oficial; (3) demonstrar o MVP distinguindo recurso implementado de integração futura; (4) fechar equipe, cronograma, custos de infraestrutura, implantação, suporte e treinamento; e (5) anexar plano de segurança, governança e avaliação do piloto. Este guia é uma nota operacional, não uma confirmação de elegibilidade ou resposta oficial ao edital.

## Recuperação da versão anterior

A versão anterior foi preservada antes das alterações no checkpoint local `checkpoint/pre-acessa-mais-saude-inteligente-2026-11-06`, commit `b5705c551da8f5a0a455eea171a6bbccb9716e97`. A evolução permanece isolada na branch `feature/acessa-mais-saude-inteligente`; a branch `main` e o deploy de produção não foram alterados.
