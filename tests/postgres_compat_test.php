<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/postgres_compat.php';

$cases = [
    [
        <<<'SQL'
SELECT DATE_FORMAT(c.data_consulta, "%Y-%m-%d") AS data,
       CASE WHEN c.horario = '00:00:00' THEN NULL ELSE TIME_FORMAT(c.horario, '%H:%i') END AS horario
FROM consultas c
SQL,
        ["TO_CHAR(c.data_consulta::date, 'YYYY-MM-DD')", "TO_CHAR(c.horario::time, 'HH24:MI')", "c.horario = '00:00:00'"]
    ],
    [
        'INSERT IGNORE INTO profissional_pacientes (profissional_id,paciente_id) VALUES (?,?)',
        ['INSERT INTO profissional_pacientes', 'ON CONFLICT DO NOTHING']
    ],
    [
        'SELECT x.id AS camelCase FROM x WHERE ativo=1 AND lembrete=0 ORDER BY FIELD(i.tipo,"campanha","vacina","citologia"),u.nome',
        ['AS "camelCase"', 'ativo = TRUE', 'lembrete = FALSE', "CASE i.tipo WHEN 'campanha' THEN 1 WHEN 'vacina' THEN 2 WHEN 'citologia' THEN 3 ELSE 4 END"]
    ],
    [
        'UPDATE y SET expira_em=DATE_ADD(NOW(),INTERVAL ? HOUR) WHERE id=?',
        ["NOW() + (? * INTERVAL '1 hour')"]
    ],
    [
        'UPDATE subscriptions SET fim=DATE_ADD(CURDATE(),INTERVAL 1 MONTH) WHERE id=?',
        ["CURRENT_DATE + INTERVAL '1 month'"]
    ],
    [
        'SELECT (inicio_janela<DATE_SUB(NOW(),INTERVAL 15 MINUTE)) FROM tentativas_autenticacao',
        ["NOW() - INTERVAL '15 minutes'"]
    ],
    [
        'SELECT id FROM suporte_mensagens WHERE encaminhado_desenvolvedor=1 AND ativo=0',
        ['encaminhado_desenvolvedor = TRUE', 'ativo = FALSE']
    ],
    [
        "INSERT INTO pacientes (codigo,nome,telefone,sus,ubs_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=VALUES(nome),telefone=VALUES(telefone),ubs_id=COALESCE(VALUES(ubs_id),ubs_id)",
        ['ON CONFLICT (sus) DO UPDATE SET nome = EXCLUDED.nome', 'COALESCE(EXCLUDED.ubs_id, pacientes.ubs_id)']
    ],
];

foreach ($cases as $index => [$input, $needles]) {
    $actual = postgres_compat_sql($input);
    foreach ($needles as $needle) {
        if (!str_contains($actual, $needle)) {
            fwrite(STDERR, "FAIL case " . ($index + 1) . ": missing {$needle}\n{$actual}\n");
            exit(1);
        }
    }
}
echo 'OK: ' . count($cases) . " PostgreSQL SQL compatibility cases\n";
