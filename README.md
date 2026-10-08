# Acessa+ Saúde — repositório Vercel

Este repositório contém a versão PHP do Acessa+ Saúde preparada para o runtime PHP comunitário recomendado pelo Vercel. O frontend continua em `index.php` e as chamadas existentes para `api.php?action=...` são encaminhadas por `api/api.php`.

## Supabase PostgreSQL (produção)

No Vercel, configure `DATABASE_URL` como variável **Sensitive**, somente no servidor. Use a connection string PostgreSQL do projeto Supabase e TLS; para funções serverless, prefira a connection string de pooler adequada à sua região/rede. O PHP conecta diretamente ao banco e executa as consultas no servidor. **Não coloque essa URL, a senha do banco ou uma service-role key no JavaScript/browser.** As policies RLS bloqueiam os papéis públicos `anon` e `authenticated`; o backend usa a credencial privada do servidor.

Quando `DATABASE_URL` estiver definido, a aplicação não executa migrações automáticas nem altera o schema na conexão. As tabelas já precisam existir no Supabase. **Não execute `database.sql` em produção:** esse arquivo começa com `DROP DATABASE` e serve apenas como referência de instalação MySQL limpa.

O Vercel não mantém arquivos gravados localmente entre execuções. Uploads de logo e anexos precisam de armazenamento externo persistente antes de uso amplo em produção.

## Compatibilidade MySQL/TiDB legada

Sem `DATABASE_URL`, o modo legado aceita `ACESSA_DB_*` ou as variáveis `TIDB_HOST`, `TIDB_PORT`, `TIDB_USER`, `TIDB_PASSWORD` e `TIDB_DATABASE`. O TiDB exige TLS. Essa opção existe para desenvolvimento/instalações antigas; produção deste projeto usa Supabase PostgreSQL.

Como o sistema pode armazenar informações de saúde, use contas e dados fictícios em testes e avalie privacidade, backups e disponibilidade antes do uso pretendido.

## Subir pelo GitHub

1. Crie um repositório vazio no GitHub.
2. Envie todos os arquivos desta pasta para esse repositório.
3. No Vercel, selecione **Add New Project** e importe o repositório.
4. Mantenha a raiz do projeto como a pasta que contém `vercel.json`.

> **Importante:** no Vercel, defina a raiz do projeto como a pasta que contém diretamente `vercel.json`, `index.php` e a pasta `api`. Não selecione uma pasta pai que contenha `acessa-saude-vercel` como subpasta.

5. Em **Project Settings → Environment Variables**, cadastre `DATABASE_URL` como segredo no ambiente Production; não use dados reais em Preview.
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

## Suporte da clínica e gestão global da Secretaria

- Os chamados abertos a partir do link público de uma clínica são encaminhados primeiro à própria clínica. A clínica pode registrar uma resposta, resolver o chamado e, após a triagem/resposta, encaminhá-lo ao desenvolvedor com a descrição da falha técnica.
- A marcação particular valida novamente o horário disponível no servidor e grava paciente, vínculo e consulta em transação para reduzir colisões de agenda.
- O perfil **Secretaria** tem escopo global nas UBS: pode consultar e alterar dados de qualquer unidade, criar UBS e desativar/reativar unidades sem apagar o histórico. O painel agregado apresenta consultas do dia e metas de campanhas, vacinação e citologia; os valores de metas e realizados são cadastrados pela Secretaria.
- A primeira conta global deve ser criada por um usuário **Desenvolvedor** autenticado, na aba **Administrador da Secretaria** do painel. Use uma senha forte de pelo menos 12 caracteres. Nenhuma senha padrão é embutida no repositório.
- As migrações compatíveis de colunas e tabelas rodam pela aplicação ao conectar ao banco existente. `database.sql` serve apenas para instalação nova: começa com `DROP DATABASE`; **não o execute em uma base de produção existente**.


## Links de acesso separados

- **SUS / UBS:** `/ubs`
- **Clínica:** `/clinica`

No XAMPP/Apache, as rotas são tratadas pelo `.htaccess`; na Vercel, estão declaradas em `vercel.json`. A home `/` continua disponível como seletor.

Usuários UBS visualizam a equipe em modo somente leitura. Somente Secretaria e desenvolvedor podem criar, editar ou excluir funcionários; a API valida essa permissão além de ocultar os controles na interface.
