# Plano — Acessa+ Saúde Inteligente / Fila Viva

## Objetivo
Evoluir o Acessa+ Saúde existente — sem substituir o sistema nem mudar sua marca/base tecnológica — para apoiar o fluxo administrativo de encaminhamentos, fila, disponibilidade, confirmação, cancelamento, faltas, ofertas de vagas e acompanhamento municipal. A interface é uma ferramenta de apoio; não diagnostica nem decide prioridade clínica.

## Abordagem
- Reutilizar a aplicação PHP existente (`index.php`, `script.js`, `style.css`, `api.php`) e o modelo existente de UBS, pacientes, consultas, `lista_espera`, notificações e auditoria.
- Estender a fila para registrar encaminhamento, prioridade informada, validação humana (responsável, data/hora e justificativa), protocolo e histórico de oferta/reaproveitamento. Somente usuários da Secretaria podem validar a prioridade nesta versão; a Secretaria deve designar formalmente as contas autorizadas. UBS pode abrir/acompanhar solicitações e registrar situações administrativas.
- Substituir a confirmação/agendamento automático decorrente de cancelamento por uma vaga disponível e oferta escolhida por operador. A ordem da fila continua transparente/cronológica; o sistema não interpreta justificativas nem ordena pacientes por critérios clínicos.
- Oferecer confirmação/recusa de vaga por link individual de uso único e prazo limitado, protegido por token aleatório armazenado como hash; fornecer também opção de contato por WhatsApp via link `wa.me`, sem envio automático. Registrar lembretes e notificações internas sem afirmar que SMS/WhatsApp está integrado.
- Exibir painel da Secretaria com filtros de unidade/especialidade/período e indicadores calculados de dados existentes: fila, espera, encaminhamentos, agenda, comparecimentos, faltas, cancelamentos, vagas abertas/ofertadas/preenchidas e confirmações. Métricas sem linha de base suficiente serão exibidas como indisponíveis, não como zero/percentual prometido.
- Adicionar migração aditiva compatível com a instalação MySQL/TiDB e script SQL separado para Supabase/PostgreSQL (a inicialização atual não executa DDL nesse caminho). Não executar nem testar escrita em base compartilhada/produção.
- Registrar eventos administrativos na auditoria existente, minimizar campos de saúde no fluxo de regulação e manter mensagens/relatórios sem diagnóstico desnecessário.
- Exibir explicitamente que a integração e-SUS APS e o envio automatizado de mensagens dependem de validação, autorização e configuração externa. Não simular conexão nem afirmar recursos já integrados.

## Estrutura do projeto
- `api.php`: endpoints de fila viva, validação, ofertas, resposta segura e indicadores, com escopo de acesso por UBS/Secretaria.
- `config.php`: bootstrap de colunas/tabelas aditivas somente no caminho MySQL, seguindo o padrão existente.
- `migrations/`: SQL manual compatível com PostgreSQL/Supabase.
- `index.php`: nova aba/painel no modal administrativo e interface de resposta de oferta.
- `script.js`: carregamento, filtros, renderização, ações, confirmação de oferta e atualização do painel.
- `style.css`: componentes visuais responsivos alinhados ao design atual.
- `README.md` e guia operacional: instalação/migração, configuração do piloto e limites de integrações externas.

## Direção de design
Reutilizar o sistema visual Acessa+ Saúde: interface clínica clara, verde/azul petróleo, componentes e tipografia existentes. O novo módulo deve parecer ferramenta municipal de operação, priorizando legibilidade, estados explícitos, trilha de auditoria e uso em telas menores; as cores de status não serão o único meio de transmitir significado.

## Limites e decisões
- Nenhuma regra de prioridade clínica será inventada. Categorias e justificativas são informadas e validadas por pessoa autorizada pela Secretaria; o sistema não as infere nem diagnostica.
- Nenhuma integração com e-SUS APS, SMS ou WhatsApp Business será declarada como ativa sem autorização, especificação técnica, provedor e credenciais. O fluxo demonstrável usa notificações internas e compartilhamento manual de mensagem.
- Os dados e indicadores históricos presentes no PDF (2025, PSI, tempos de espera) não serão inseridos como dados reais do sistema; precisam ser validados na fonte oficial antes de divulgação.
- O checkpoint de origem fica na tag/branch `checkpoint/pre-acessa-mais-saude-inteligente-2026-11-06` (SHA `b5705c551da8f5a0a455eea171a6bbccb9716e97`). A implementação permanecerá em branch separada; nenhuma publicação de produção será feita neste trabalho.
