# Entregas — Acessa+ Saúde Inteligente

- [x] **Fila por especialidade, unidade e situação** — Encaminhamentos com protocolo, unidade de origem/destino, data de entrada e filtros. Consultas agregadas não dependem do limite de itens renderizados.
- [x] **Prioridade com validação humana** — Categoria e observação administrativa, responsável e data de validação; somente Secretaria. Não diagnostica, não reordena a fila e não automatiza decisão clínica. A Prefeitura deve designar formalmente quem pode validar.
- [x] **Agenda e disponibilidade existentes** — Reutiliza agenda atual, limite diário por especialidade e estados de consulta. **Limite conhecido:** não há grade de horário por profissional; ocupação é estimada.
- [x] **Confirmação, cancelamento, reagendamento e falta** — Ações disponíveis com validação no servidor e auditoria; horário de vaga aceita precisa ser confirmado pela UBS.
- [x] **Fila Viva/reaproveitamento controlado** — Cancelamento futuro em dia de atendimento registra vaga; operador escolhe candidato validado e compatível; oferta de resposta única e expiração. Sem promoção automática.
- [x] **Notificação com limites explícitos** — Registro interno pendente e link de WhatsApp para envio manual; não simula envio. Provedor SMS/WhatsApp ainda não conectado.
- [x] **Painel municipal** — Indicadores e evolução mensal com filtros por unidade, especialidade e período; taxas e espera explicadas, indisponíveis sem amostra adequada.
- [x] **Registro de faltas/linha de base** — Desfechos estruturados; taxa de faltas usa apenas consultas com desfecho. A linha de base municipal ainda precisa ser coletada e validada.
- [x] **Controles do módulo** — Escopo de UBS/cidade, autenticação existente, CSRF, token aleatório armazenado como hash e auditoria. **Pendente antes de dados reais:** revisão de segurança/LGPD, backups, retenção/descarte e resposta a incidentes.
- [x] **Isolamento Secretaria por criador** — Cada Secretaria vê somente UBS com owner igual ao seu ID e cidade/UF compatíveis. Novas UBS recebem owner no servidor; legado só é atribuído com autoria única comprovada em auditoria. Transferência para outra Secretaria é recusada também no endpoint.
- [x] **Pendências externas documentadas** — e-SUS APS, SMS/WhatsApp automático, horário/profissional e autorização institucional não são anunciados como integrações existentes.
- [x] **Preparação do piloto Triunfo–PE** — Guia com etapas, decisões municipais, métricas e pontos do edital. Especialidades, regras, cronograma, equipe e custos aguardam pactuação com a Secretaria.
- [x] **Migração compatível** — Bootstrap aditivo MySQL/TiDB e SQL manual PostgreSQL/Supabase; migração não executada em produção.
- [x] **Checkpoint de recuperação** — `checkpoint/pre-acessa-mais-saude-inteligente-2026-11-06`, SHA `b5705c551da8f5a0a455eea171a6bbccb9716e97`.
- [ ] **Validação de homologação** — Publicar a revisão na branch e garantir que `DATABASE_URL` do Supabase staging esteja configurada como segredo apenas para Preview/branch de avaliação. Depois do redeploy, executar smoke test com dois perfis UBS e conta Secretaria, incluindo fluxo Fila Viva, concorrência de oferta, expiração, recusa, aceite, falta, cancelamento, reagendamento e isolamento municipal.
- [ ] **Implantação oficial** — Depende de autorização da Secretaria, backup verificado, migração aplicada, análise de segurança e configuração da infraestrutura/provedores.


## Status de homologação — 6 de novembro de 2026

As migrations PostgreSQL `20261106_fila_viva`, `20261107_secretaria_ubs_scope`, `20261107_secretaria_ubs_creator_guard` e `20261107_secretaria_ubs_location_guard` foram aplicadas no projeto Supabase `acessamaissaude`, confirmado pelo usuário como staging; tabelas, colunas e owners existentes foram verificados. O resultado agregado é 1 UBS com criador e localidade compatíveis; 5 permaneceram sem owner; nenhum owner sem prova e nenhuma divergência municipal. A produção permanece intocada. Preview e smoke tests end-to-end dependem de `DATABASE_URL` do staging no escopo Preview/branch de avaliação e do redeploy da revisão atual.
