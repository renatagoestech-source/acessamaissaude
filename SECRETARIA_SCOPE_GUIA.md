# Escopo de acesso das Secretarias às UBS

## Regra aplicada

Uma conta do tipo **Secretaria** não é uma conta global. Ela só pode consultar, selecionar, editar e operar uma UBS quando a unidade:

1. tem `ubs.secretaria_id` igual ao ID da conta Secretaria autenticada; e
2. ainda pertence à cidade e à UF registradas na própria conta.

O backend lê a cidade/UF da conta no banco em cada requisição; não confia no município enviado pelo navegador nem apenas nos valores guardados na sessão. Uma conta só pode receber vínculos para UBS cuja auditoria comprova que foi criada por essa mesma conta Secretaria. O perfil **Desenvolvedor** mantém o acesso global administrativo; a conta de uma UBS continua limitada à própria unidade.

Essa regra é aplicada na lista do seletor, no painel Secretaria, consultas, exames/anexos, indicadores, funcionários, filas, Fila Viva, gestão/arquivamento de unidades e encaminhamento de chamados. Alterar a requisição no navegador não concede acesso a outra UBS.

## Criação de novas UBS

Quando uma conta Secretaria cria uma UBS, o servidor grava o ID da própria conta na coluna `ubs.secretaria_id`. A conta não pode escolher outro owner pelo formulário. A cidade/UF da unidade é herdada da conta; quando uma conta antiga ainda não possui localização, a primeira criação válida grava a localidade na conta e na UBS.

## UBS existentes

A migração atribui automaticamente apenas unidades cuja auditoria identifica um único criador do tipo Secretaria e cuja cidade/UF coincide com a conta registrada. Unidades sem comprovação, com autoria ambígua ou localidade incompatível permanecem com owner nulo e, portanto, invisíveis às contas Secretaria. Não se deve atribuir ownership por nome da UBS, cidade isoladamente ou suposição.

O painel de gestão de ownership é exclusivo do **Desenvolvedor**. Ele oferece somente a conta criadora única indicada pela auditoria e compatível com cidade/UF. O servidor repete essa verificação em transação antes de gravar o vínculo; portanto, um Desenvolvedor não pode transferir a UBS para outra conta Secretaria. Desvincular deixa a UBS invisível às Secretarias; não apaga a unidade nem seus dados.

Na aplicação da migration de homologação, a verificação agregada encontrou **1 UBS com vínculo comprovado, 5 sem owner e 0 incompatibilidades de localização**. Nenhum nome de UBS ou dado de paciente foi necessário para essa verificação. A decisão sobre as cinco unidades sem comprovação deve ser feita por um administrador autorizado, não inferida pelo software.

## Migrações

- **MySQL/TiDB:** o bootstrap adiciona a coluna `secretaria_id`, índice e FK com `ON DELETE SET NULL`; backfill e verificador versionados consideram somente logs `ubs_criada` inequívocos e localização compatível, limpando owners preexistentes sem prova. O `database.sql` atualizado é somente para instalação nova e contém `DROP DATABASE`; não o execute em base existente.
- **Supabase/PostgreSQL:** aplicar manualmente `migrations/20261107_secretaria_ubs_scope.sql` depois de `migrations/20261106_fila_viva.sql`, e então `migrations/20261107_secretaria_ubs_creator_guard.sql` e `migrations/20261107_secretaria_ubs_location_guard.sql`. As migrations foram aplicadas no projeto `acessamaissaude` confirmado como staging; produção não foi alterada.

## Verificação

O teste `php tests/secretaria_scope_test.php` verifica que o filtro usa owner + cidade/UF, recusa alias SQL arbitrário, exige autoria inequívoca no backfill e impede transferir ownership para outra conta. As suítes existentes de compatibilidade PostgreSQL, sessão, profissionais e frontend devem continuar passando.
