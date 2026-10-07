# Publicar pelo GitHub e Vercel

## Estrutura correta

Depois de extrair este pacote, a pasta selecionada deve conter diretamente:

```text
vercel.json
index.php
api/
script.js
style.css
config.php
database.sql
```

Não faça upload da pasta externa que contém o projeto. Faça upload do conteúdo desta pasta.

## GitHub pelo navegador

1. Crie um repositório vazio no GitHub.
2. Abra **Add file → Upload files**.
3. Selecione `vercel.json`, `index.php`, `api/` e os demais arquivos desta pasta.
4. Confirme em **Commit changes**.
5. Verifique se `api/index.php` e `api/api.php` aparecem dentro da pasta `api`.

## Vercel

1. Abra o Vercel e selecione **Add New Project**.
2. Importe o repositório do GitHub.
3. Use `Other` como Framework Preset.
4. Deixe **Root Directory** como `./`.
5. Deixe Build Command e Output Directory vazios.
6. Clique em **Deploy**.

O `vercel.json` já aponta explicitamente para `api/index.php` e `api/api.php` usando o runtime PHP.

## Banco de dados

Configure um MySQL externo e importe `database.sql`. No Vercel, adicione as variáveis `ACESSA_DB_HOST`, `ACESSA_DB_PORT`, `ACESSA_DB_NAME`, `ACESSA_DB_USER` e `ACESSA_DB_PASS` em **Project Settings → Environment Variables**.

A assinatura permanece em modo demonstração e não gera cobrança real.
