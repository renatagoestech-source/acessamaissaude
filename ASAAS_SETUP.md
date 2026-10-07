# Integração Asaas — modo demonstração

Neste momento, a aba **Assinatura** está em modo demonstração. Ao escolher um plano, o sistema ativa uma assinatura demonstrativa e não cria cobrança real no Asaas.

A integração real com o Asaas permanece preparada no backend para uma próxima etapa. Quando for liberada, será necessário configurar no servidor:

```bash
ASAAS_ENV=sandbox
ASAAS_API_KEY=cole_a_chave_api_do_asaas_aqui
ASAAS_WEBHOOK_TOKEN=crie_um_token_secreto_forte
```

O webhook futuro deverá ser cadastrado em:

```text
https://SEU_DOMINIO/api.php?action=asaas_webhook
```

A chave API nunca deve ser colocada no JavaScript ou commitada no repositório.
