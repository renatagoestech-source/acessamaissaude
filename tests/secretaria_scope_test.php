<?php
declare(strict_types=1);

require_once __DIR__ . '/../common.php';

function scope_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$scope = ['id' => '42', 'cidade' => 'Triunfo', 'estado' => 'PE'];
[$where, $params] = secretaria_ubs_scope_filter($scope, 'u');
scope_assert(
    $where === 'u.secretaria_id=? AND LOWER(TRIM(u.cidade))=LOWER(TRIM(?)) AND UPPER(TRIM(u.estado))=UPPER(TRIM(?))',
    'O filtro deve exigir titularidade da conta e município/UF canônicos.'
);
scope_assert($params === ['42', 'Triunfo', 'PE'], 'O filtro deve usar o ID e a localização da conta autenticada.');

$rejectedUnsafeAlias = false;
try {
    secretaria_ubs_scope_filter($scope, 'u; DROP TABLE ubs');
} catch (InvalidArgumentException $expected) {
    $rejectedUnsafeAlias = true;
}
scope_assert($rejectedUnsafeAlias, 'O alias SQL deve ser validado para impedir concatenação arbitrária.');

$common = file_get_contents(__DIR__ . '/../common.php');
$api = file_get_contents(__DIR__ . '/../api.php');
$fila = file_get_contents(__DIR__ . '/../fila_viva.php');
$scopeMigration = file_get_contents(__DIR__ . '/../migrations/20261107_secretaria_ubs_scope.sql');
$creatorGuardMigration = file_get_contents(__DIR__ . '/../migrations/20261107_secretaria_ubs_creator_guard.sql');
$locationGuardMigration = file_get_contents(__DIR__ . '/../migrations/20261107_secretaria_ubs_location_guard.sql');
$config = file_get_contents(__DIR__ . '/../config.php');
scope_assert(str_contains($common, "=== 'desenvolvedor') {"), 'Somente o desenvolvedor deve manter acesso administrativo global.');
scope_assert(str_contains($api, 'all_ubs($pdo,true,$session)'), 'A listagem administrativa precisa passar a sessão para aplicar o escopo.');
scope_assert(str_contains($api, 'secretaria_id) VALUES(?,?,?,?,?,?,?,?,?,?)'), 'A criação de UBS deve persistir o owner vinculado à sessão.');
scope_assert(str_contains($fila, 'secretaria_ubs_scope_filter($scope, \'u\')'), 'Destinos da Fila Viva devem usar o mesmo filtro de titularidade.');
scope_assert(str_contains($scopeMigration, 'ON DELETE SET NULL'), 'Excluir uma conta não deve apagar as UBS nem dados históricos.');
scope_assert(str_contains($scopeMigration, 'HAVING COUNT(DISTINCT admin_id) = 1'), 'Backfill legado precisa de autoria inequívoca.');
scope_assert(str_contains($creatorGuardMigration, 'NOT EXISTS'), 'Migration corretiva precisa remover owners sem criador comprovado.');
scope_assert(str_contains($locationGuardMigration, "UPPER(TRIM(a.estado)) ~ '^[A-Z]{2}$'"), 'Migration de integridade exige UF válida antes de manter vínculo.');
scope_assert(str_contains($config, 'ubs_secretaria_owner_integrity_v1'), 'MySQL/TiDB deve executar a limpeza de owners preexistentes uma única vez.');
scope_assert(str_contains($config, 'SET u.secretaria_id=NULL WHERE u.secretaria_id IS NOT NULL'), 'O bootstrap deve invalidar owners sem autoria única e localização compatível.');
scope_assert(str_contains($api, 'creator.creator_id AS criadorSecretariaId'), 'A UI de ownership só deve oferecer o criador comprovado na auditoria.');
scope_assert(str_contains($api, '$creatorId === false || (int)$creatorId !== (int)$account[\'id\']'), 'O backend deve impedir transferência de UBS para outra conta Secretaria.');
scope_assert(str_contains($api, 'A UBS só pode ser vinculada à conta Secretaria cuja autoria única está comprovada'), 'UBS legadas sem prova devem permanecer sem acesso Secretaria.');
scope_assert(str_contains(file_get_contents(__DIR__ . '/../script.js'), 'String(account.id)===creatorId'), 'O seletor não deve oferecer contas diferentes da criadora auditada.');

echo "Escopo de Secretaria, owner e segurança do migration: OK\n";
