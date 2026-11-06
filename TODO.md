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
- [x] **Pendências externas documentadas** — e-SUS APS, SMS/WhatsApp automático, horário/profissional e autorização institucional não são anunciados como integrações existentes.
- [x] **Preparação do piloto Triunfo–PE** — Guia com etapas, decisões municipais, métricas e pontos do edital. Especialidades, regras, cronograma, equipe e custos aguardam pactuação com a Secretaria.
- [x] **Migração compatível** — Bootstrap aditivo MySQL/TiDB e SQL manual PostgreSQL/Supabase; migração não executada em produção.
- [x] **Checkpoint de recuperação** — `checkpoint/pre-acessa-mais-saude-inteligente-2026-11-06`, SHA `b5705c551da8f5a0a455eea171a6bbccb9716e97`.
- [ ] **Validação de homologação** — Executar smoke test com banco clone, dois perfis UBS e conta Secretaria, incluindo concorrência de oferta, expiração, recusa, aceite, falta, cancelamento, reagendamento e segregação municipal.
- [ ] **Implantação oficial** — Depende de autorização da Secretaria, backup verificado, migração aplicada, análise de segurança e configuração da infraestrutura/provedores.
