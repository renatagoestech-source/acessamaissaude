# Acessa+ Saúde — repositório Vercel

Este repositório contém a versão PHP do Acessa+ Saúde preparada para o runtime PHP comunitário recomendado pelo Vercel. O frontend continua em `index.php` e as chamadas existentes para `api.php?action=...` são encaminhadas por `api/api.php`.

## Importante antes do deploy

O Vercel não fornece um MySQL local persistente. Antes de publicar, crie um banco MySQL externo acessível pela internet e configure as variáveis `ACESSA_DB_*` no painel do Vercel. O arquivo `database.sql` contém o esquema inicial.

O armazenamento local do Vercel também não é persistente. Uploads de logo e anexos devem ser migrados para um serviço de arquivos persistente antes de uso em produção. Para demonstração, a interface e o banco funcionam com as configurações adequadas.

## TiDB Cloud Starter com Vercel

O TiDB Cloud Starter é uma opção MySQL-compatible com cota gratuita. A integração oficial Vercel/PingCAP adiciona `TIDB_HOST`, `TIDB_PORT`, `TIDB_USER`, `TIDB_PASSWORD` e `TIDB_DATABASE`; a conexão deste projeto aceita essas variáveis quando `ACESSA_DB_HOST` não estiver definido. O TiDB Starter usa normalmente a porta `4000`, exige TLS e usa o pacote de certificados CA do runtime. Para uma integração PHP, selecione o modo **General**, não Prisma.

Para evitar cobranças, mantenha o limite de gastos do Starter em `US$ 0`. Ao atingir a cota gratuita, o banco pode bloquear novas conexões até a renovação mensal. Confira os limites atuais em [TiDB Cloud Starter pricing](https://www.pingcap.com/tidb-cloud-starter-pricing-details/).

O banco ainda precisa do esquema inicial. `database.sql` começa com `DROP DATABASE`; execute-o somente em uma instalação nova e vazia, nunca sobre uma base que já contenha dados. As migrações compatíveis restantes são executadas pela aplicação após a primeira conexão.

Em instalações existentes com Supabase/PostgreSQL, aplique manualmente no SQL Editor as migrações necessárias de `migrations/`; para o filtro de UBS por localização, execute `migrations/20261009_ubs_location.sql` antes de publicar o código. O bootstrap da aplicação não executa DDL no PostgreSQL.

Como este sistema pode armazenar informações de saúde, use dados fictícios no plano gratuito até avaliar requisitos de privacidade, backups e disponibilidade para o uso pretendido.

## Subir pelo GitHub

1. Crie um repositório vazio no GitHub.
2. Envie todos os arquivos desta pasta para esse repositório.
3. No Vercel, selecione **Add New Project** e importe o repositório.
4. Mantenha a raiz do projeto como a pasta que contém `vercel.json`.

> **Importante:** no Vercel, defina a raiz do projeto como a pasta que contém diretamente `vercel.json`, `index.php` e a pasta `api`. Não selecione uma pasta pai que contenha `acessa-saude-vercel` como subpasta.

5. Em **Project Settings → Environment Variables**, cadastre as variáveis do `.env.example` com os valores reais do seu MySQL.
6. Faça o deploy.

## Subir pelo terminal

Com Git e Vercel CLI instalados:

```bash
git init
git add .
git commit -m "Preparar projeto para Vercel"
vercel login
vercel link
vercel --prod
```

## Desenvolvimento local

O runtime PHP do Vercel exige PHP local para `vercel dev`. Como alternativa, use o servidor PHP embutido:

```bash
php -S 127.0.0.1:8000
```

## Modo demonstração

A aba de assinatura está em modo demonstração e não cria cobrança real no Asaas. Não configure `ASAAS_API_KEY` enquanto esse modo estiver sendo utilizado.

## Referências

- [Vercel — runtimes](https://vercel.com/docs/functions/runtimes)
- [Runtime PHP comunitário](https://github.com/vercel-community/php)
- [PHP no Vercel com Docker](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker)

## Suporte da clínica e escopo da Secretaria

- Os chamados abertos a partir do link público de uma clínica são encaminhados primeiro à própria clínica. A clínica pode registrar uma resposta, resolver o chamado e, após a triagem/resposta, encaminhá-lo ao desenvolvedor com a descrição da falha técnica.
- A marcação particular valida novamente o horário disponível no servidor e grava paciente, vínculo e consulta em transação para reduzir colisões de agenda.
- O perfil **Secretaria** não tem acesso global: lista, painel, fila, metas e ações são filtrados no servidor para as UBS atribuídas àquela conta e à cidade/UF cadastradas nela. Novas UBS criadas pela própria conta recebem o vínculo no servidor; não é possível enviar outro owner no formulário.
- A primeira conta municipal deve ser criada por um usuário **Desenvolvedor** autenticado, na aba **Administrador da Secretaria** do painel. Use uma senha forte de pelo menos 12 caracteres. Nenhuma senha padrão é embutida no repositório.
- UBS antigas só recebem owner automaticamente quando a auditoria comprova um único criador Secretaria e a cidade/UF ainda coincidem. Unidades sem prova ficam invisíveis; o Desenvolvedor só pode recuperar o vínculo para a conta criadora comprovada — nunca transferir para outra Secretaria por conveniência. A ação é auditada.
- Consulte o [guia de escopo por Secretaria e vínculo seguro das UBS](SECRETARIA_SCOPE_GUIA.md) para a regra de acesso, migrações e tratamento das unidades legadas.
- As migrações compatíveis de colunas e tabelas rodam pela aplicação ao conectar ao banco existente. `database.sql` serve apenas para instalação nova: começa com `DROP DATABASE`; **não o execute em uma base de produção existente**.


## Links de acesso separados

- **SUS / UBS:** `/ubs`
- **Clínica:** `/clinica`

No XAMPP/Apache, as rotas são tratadas pelo `.htaccess`; na Vercel, estão declaradas em `vercel.json`. A home `/` continua disponível como seletor.

Usuários UBS visualizam a equipe em modo somente leitura. Somente Secretaria e desenvolvedor podem criar, editar ou excluir funcionários; a API valida essa permissão além de ocultar os controles na interface.


## Fila Viva — Acessa+ Saúde Inteligente

A branch de trabalho `feature/acessa-mais-saude-inteligente` inclui encaminhamentos, fila por especialidade/unidade, validação humana de prioridade, ofertas controladas de vagas, confirmação/desfechos e indicadores. Veja o [guia operacional e de avaliação da Fila Viva](FILA_VIVA_GUIA.md) antes de preparar uma demonstração ou submissão.

O módulo não diagnostica, não calcula prioridade e não ordena pacientes automaticamente. Horários por profissional não existem no cadastro atual; a ocupação é estimada pelo limite diário da UBS. A equipe deve confirmar o horário após o aceite do paciente. SMS/WhatsApp automático e e-SUS APS **não estão integrados**; mensagens ficam pendentes para contato manual.

Antes de usar dados reais, valide em homologação, faça e teste backup, revise acessos/LGPD e aplique a migração apropriada. MySQL/TiDB recebe alterações aditivas pelo bootstrap PHP na primeira conexão com a versão atualizada. Supabase/PostgreSQL requer aplicação manual das migrations de `migrations/`; `20261106_fila_viva`, `20261107_secretaria_ubs_scope`, `20261107_secretaria_ubs_creator_guard` e `20261107_secretaria_ubs_location_guard` foram aplicadas somente no staging confirmado pelo usuário. Nenhuma migration ou publicação de produção foi feita neste trabalho.


## Estado de homologação da Fila Viva — 6 de novembro de 2026

As migrations `20261106_fila_viva`, `20261107_secretaria_ubs_scope`, `20261107_secretaria_ubs_creator_guard` e `20261107_secretaria_ubs_location_guard` foram aplicadas ao projeto Supabase `acessamaissaude`, confirmado pelo usuário como homologação. O owner é atribuído somente por autoria auditada e localização válida; unidades antigas sem prova permanecem sem owner. Para validar a revisão atual, publique a branch, confirme `DATABASE_URL` como segredo apenas no Preview/branch de avaliação e redeploy; o smoke test end-to-end ainda está pendente. Não promova para produção sem revisão, backup e autorização e não reutilize credenciais de produção.
