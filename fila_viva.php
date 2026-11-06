<?php
declare(strict_types=1);

/**
 * Fila Viva: gestão administrativa de encaminhamentos e vagas SUS.
 * Nenhuma função calcula prioridade clínica. A seleção final de candidatos
 * e a validação da categoria ficam sob responsabilidade humana da Secretaria.
 */
function fila_viva_require_scope(PDO $pdo, string $ubsId = ''): array
{
    $session = require_admin();
    if (!in_array(($session['tipo'] ?? ''), ['ubs', 'secretaria', 'desenvolvedor'], true)) {
        json_response(['success' => false, 'message' => 'Perfil sem permissão para acessar a Fila Viva.'], 403);
    }
    if (($session['tipo'] ?? '') === 'secretaria' && (trim((string)($session['cidade'] ?? '')) === '' || !preg_match('/^[A-Za-z]{2}$/', trim((string)($session['estado'] ?? ''))))) {
        json_response(['success' => false, 'message' => 'A conta da Secretaria precisa ter cidade e UF definidas antes de acessar a Fila Viva.'], 403);
    }

    if (($session['tipo'] ?? '') === 'ubs') {
        $own = trim((string)($session['ubs_id'] ?? ''));
        if ($own === '' || ($ubsId !== '' && $ubsId !== $own)) {
            json_response(['success' => false, 'message' => 'Você não tem permissão para acessar esta UBS.'], 403);
        }
        $ubsId = $own;
    }

    if ($ubsId !== '') {
        fila_viva_check_unit($pdo, $session, $ubsId);
    }

    return [$session, $ubsId];
}

function fila_viva_check_unit(PDO $pdo, array $session, string $ubsId): array
{
    $stmt = $pdo->prepare('SELECT id,nome,cidade,estado,limite_diario,ativa FROM ubs WHERE id=?');
    $stmt->execute([$ubsId]);
    $unit = $stmt->fetch();
    if (!$unit) json_response(['success' => false, 'message' => 'Unidade de saúde não encontrada.'], 404);

    if (($session['tipo'] ?? '') === 'ubs' && (string)$session['ubs_id'] !== (string)$unit['id']) {
        json_response(['success' => false, 'message' => 'Você não tem permissão para acessar esta UBS.'], 403);
    }

    if (($session['tipo'] ?? '') === 'secretaria') {
        $city = trim((string)($session['cidade'] ?? ''));
        $state = strtoupper(trim((string)($session['estado'] ?? '')));
        if ($city === '' || !preg_match('/^[A-Z]{2}$/', $state)) json_response(['success' => false, 'message' => 'A conta da Secretaria não possui escopo municipal válido.'], 403);
        if (mb_strtolower($city, 'UTF-8') !== mb_strtolower(trim((string)$unit['cidade']), 'UTF-8') || $state !== strtoupper(trim((string)$unit['estado']))) {
            json_response(['success' => false, 'message' => 'A unidade está fora do escopo municipal desta conta.'], 403);
        }
    }

    return $unit;
}

function fila_viva_check_referral_units(PDO $pdo, array $session, string $originId, string $targetId): array
{
    $origin = fila_viva_check_unit($pdo, $session, $originId);
    if (!database_bool($origin['ativa'] ?? false)) json_response(['success' => false, 'message' => 'A unidade de origem está inativa.'], 409);
    $query = $pdo->prepare('SELECT id,nome,cidade,estado,ativa FROM ubs WHERE id=?');
    $query->execute([$targetId]);
    $target = $query->fetch();
    if (!$target) json_response(['success' => false, 'message' => 'Unidade de destino não encontrada.'], 404);
    if (!database_bool($target['ativa'] ?? false)) json_response(['success' => false, 'message' => 'A unidade de destino está inativa.'], 409);

    if (($session['tipo'] ?? '') === 'ubs') {
        if (mb_strtolower(trim((string)$origin['cidade']), 'UTF-8') !== mb_strtolower(trim((string)$target['cidade']), 'UTF-8') || strtoupper(trim((string)$origin['estado'])) !== strtoupper(trim((string)$target['estado']))) {
            json_response(['success' => false, 'message' => 'A unidade de destino deve pertencer ao mesmo município da UBS de origem.'], 403);
        }
    } else {
        fila_viva_check_unit($pdo, $session, $targetId);
    }
    return [$origin, $target];
}

function fila_viva_referral_targets(PDO $pdo, array $session): array
{
    $where = ' WHERE u.ativa=1';
    $params = [];
    if (($session['tipo'] ?? '') === 'ubs') {
        $own = $pdo->prepare('SELECT cidade,estado FROM ubs WHERE id=?');
        $own->execute([(string)$session['ubs_id']]);
        $unit = $own->fetch();
        if (!$unit) return [];
        $where .= ' AND u.cidade=? AND u.estado=?';
        $params = [(string)$unit['cidade'], (string)$unit['estado']];
    } elseif (($session['tipo'] ?? '') === 'secretaria') {
        $city = trim((string)($session['cidade'] ?? ''));
        $state = strtoupper(trim((string)($session['estado'] ?? '')));
        if ($city !== '' || $state !== '') {
            $where .= ' AND u.cidade=? AND u.estado=?';
            $params = [$city, $state];
        }
    }
    $query = $pdo->prepare('SELECT u.id,u.nome,u.cidade,u.estado,e.nome AS especialidade FROM ubs u LEFT JOIN ubs_especialidades e ON e.ubs_id=u.id' . $where . ' ORDER BY u.nome,e.nome');
    $query->execute($params);
    $units = [];
    foreach ($query->fetchAll() as $row) {
        $id = (string)$row['id'];
        if (!isset($units[$id])) $units[$id] = ['id' => $id, 'nome' => (string)$row['nome'], 'cidade' => (string)$row['cidade'], 'estado' => (string)$row['estado'], 'especialidades' => []];
        if ($row['especialidade'] !== null) $units[$id]['especialidades'][] = (string)$row['especialidade'];
    }
    return array_values($units);
}

function fila_viva_sql_city_scope(array $session, string $alias): array
{
    if (($session['tipo'] ?? '') !== 'secretaria') return ['', []];
    $city = trim((string)($session['cidade'] ?? ''));
    $state = strtoupper(trim((string)($session['estado'] ?? '')));
    if ($city === '' && $state === '') return ['', []];
    return [" AND {$alias}.cidade=? AND {$alias}.estado=?", [$city, $state]];
}

function fila_viva_require_secretaria(): array
{
    $session = require_admin();
    if (($session['tipo'] ?? '') !== 'secretaria') {
        json_response(['success' => false, 'message' => 'A validação de prioridade é exclusiva de uma conta da Secretaria formalmente autorizada.'], 403);
    }
    if (trim((string)($session['cidade'] ?? '')) === '' || !preg_match('/^[A-Za-z]{2}$/', trim((string)($session['estado'] ?? '')))) json_response(['success' => false, 'message' => 'A conta da Secretaria precisa ter cidade e UF definidas.'], 403);
    return $session;
}

function fila_viva_record_vacancy(PDO $pdo, string $appointmentId, string $ubsId, string $specialty, string $date): void
{
    if ($appointmentId === '' || $ubsId === '' || $specialty === '' || !valid_date($date) || $date <= date('Y-m-d') || !is_business_day($date)) return;
    $exists = $pdo->prepare('SELECT id FROM fila_viva_vagas WHERE cancelamento_consulta_id=?');
    $exists->execute([$appointmentId]);
    if ($exists->fetchColumn()) return;

    try {
        $insert = $pdo->prepare("INSERT INTO fila_viva_vagas (cancelamento_consulta_id,ubs_id,especialidade,data_consulta,status) VALUES (?,?,?,?,'disponivel')");
        $insert->execute([$appointmentId, $ubsId, $specialty, $date]);
        audit_event($pdo, 'fila_viva_vaga_liberada', 'fila_viva_vagas', (string)$pdo->lastInsertId(), ['ubs_id' => $ubsId, 'especialidade' => $specialty, 'data' => $date]);
    } catch (PDOException $error) {
        if (!database_is_unique_violation($error)) throw $error;
    }
}

function fila_viva_create_referral(PDO $pdo, array $data): never
{
    [$session] = fila_viva_require_scope($pdo);
    $originId = required_string($data, 'origem_ubs_id');
    $targetId = required_string($data, 'ubs_id');
    $specialty = required_string($data, 'especialidade');
    $sus = preg_replace('/\D+/', '', required_string($data, 'sus'));
    $date = required_string($data, 'data_consulta');
    [$origin, $target] = fila_viva_check_referral_units($pdo, $session, $originId, $targetId);

    if (!valid_date($date) || $date < date('Y-m-d') || !is_business_day($date)) {
        json_response(['success' => false, 'message' => 'Informe uma data futura ou de hoje em dia de funcionamento.'], 422);
    }
    if (mb_strlen($specialty, 'UTF-8') > 120) json_response(['success' => false, 'message' => 'Especialidade inválida.'], 422);

    $patientQuery = $pdo->prepare('SELECT id,ubs_id FROM pacientes WHERE sus=?');
    $patientQuery->execute([$sus]);
    $patient = $patientQuery->fetch();
    if (!$patient) json_response(['success' => false, 'message' => 'Não foi encontrado paciente com esse Cartão SUS.'], 404);
    if (($session['tipo'] ?? '') === 'ubs' && (string)($patient['ubs_id'] ?? '') !== $originId) {
        json_response(['success' => false, 'message' => 'O paciente não está vinculado à UBS de origem selecionada.'], 403);
    }

    $specialtyQuery = $pdo->prepare('SELECT 1 FROM ubs_especialidades WHERE ubs_id=? AND nome=?');
    $specialtyQuery->execute([$targetId, $specialty]);
    if (!$specialtyQuery->fetchColumn()) json_response(['success' => false, 'message' => 'A especialidade não está cadastrada para a unidade de destino.'], 422);

    $protocol = 'FV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(9)));
    try {
        $insert = $pdo->prepare("INSERT INTO lista_espera (paciente_id,ubs_id,especialidade,data_consulta,status,encaminhado_em,origem_ubs_id,protocolo) VALUES (?,?,?,?,'pendente',CURRENT_TIMESTAMP,?,?)");
        $insert->execute([(int)$patient['id'], $targetId, $specialty, $date, $originId, $protocol]);
    } catch (PDOException $error) {
        if (database_is_unique_violation($error)) json_response(['success' => false, 'message' => 'Já existe uma solicitação deste paciente para a mesma unidade, especialidade e data.'], 409);
        throw $error;
    }

    $id = (string)$pdo->lastInsertId();
    $msg = 'Seu encaminhamento foi registrado com o protocolo ' . $protocol . '. A prioridade ainda depende de validação por profissional autorizado.';
    $notificationQueued = true;
    try {
        $pdo->prepare("INSERT INTO notificacoes (paciente_id,canal,tipo,mensagem,agendada_para,status) VALUES (?,'push','fila_viva_encaminhamento',?,CURRENT_TIMESTAMP,'pendente')")
            ->execute([(int)$patient['id'], $msg]);
    } catch (Throwable $ignored) { $notificationQueued = false; }
    audit_event($pdo, 'fila_viva_encaminhamento_criado', 'lista_espera', $id, ['protocolo' => $protocol, 'origem_ubs_id' => $originId, 'destino_ubs_id' => $targetId, 'especialidade' => $specialty]);

    $resultMessage = 'Encaminhamento registrado e aguardando validação.';
    if (!$notificationQueued) $resultMessage .= ' A notificação interna não foi registrada; a unidade deve orientar o paciente manualmente.';
    json_response(['success' => true, 'id' => $id, 'protocolo' => $protocol, 'unidade' => (string)$target['nome'], 'message' => $resultMessage]);
}

function fila_viva_validate_priority(PDO $pdo, array $data): never
{
    $session = fila_viva_require_secretaria();
    $id = (int)($data['id'] ?? 0);
    $category = trim((string)($data['prioridade_categoria'] ?? ''));
    $note = trim((string)($data['prioridade_observacao'] ?? ''));
    if ($id < 1 || mb_strlen($category, 'UTF-8') < 2 || mb_strlen($category, 'UTF-8') > 120 || mb_strlen($note, 'UTF-8') > 255) {
        json_response(['success' => false, 'message' => 'Informe a categoria definida pela Secretaria e uma observação administrativa de até 255 caracteres.'], 422);
    }

    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT id,ubs_id,status FROM lista_espera WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $row = $query->fetch();
        if (!$row) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Encaminhamento não encontrado.'], 404); }
        fila_viva_check_unit($pdo, $session, (string)$row['ubs_id']);
        if ($row['status'] !== 'pendente') { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Somente solicitações pendentes podem receber validação de prioridade.'], 409); }
        $update = $pdo->prepare('UPDATE lista_espera SET prioridade_categoria=?,prioridade_observacao=?,prioridade_validada_por=?,prioridade_validada_em=CURRENT_TIMESTAMP WHERE id=?');
        $update->execute([$category, $note !== '' ? $note : null, (int)$session['id'], $id]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    audit_event($pdo, 'fila_viva_prioridade_validada', 'lista_espera', (string)$id, ['categoria' => $category]);
    json_response(['success' => true, 'message' => 'Validação humana registrada. O sistema não reordenou a fila nem inferiu prioridade.']);
}

function fila_viva_cancel_referral(PDO $pdo, array $data): never
{
    [$session] = fila_viva_require_scope($pdo);
    $id = (int)($data['id'] ?? 0);
    if ($id < 1) json_response(['success' => false, 'message' => 'Solicitação inválida.'], 422);

    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT id,ubs_id,status FROM lista_espera WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $row = $query->fetch();
        if (!$row) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Encaminhamento não encontrado.'], 404); }
        fila_viva_check_unit($pdo, $session, (string)$row['ubs_id']);
        if ($row['status'] !== 'pendente') { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Somente solicitações ainda pendentes podem ser canceladas.'], 409); }
        $offers = $pdo->prepare("SELECT vaga_id FROM fila_viva_ofertas WHERE lista_espera_id=? AND status='pendente'");
        $offers->execute([$id]);
        $vacancyIds = array_unique(array_map('strval', $offers->fetchAll(PDO::FETCH_COLUMN)));
        $pdo->prepare("UPDATE lista_espera SET status='cancelado' WHERE id=? AND status='pendente'")->execute([$id]);
        $pdo->prepare("UPDATE fila_viva_ofertas SET status='expirada',resposta_em=CURRENT_TIMESTAMP WHERE lista_espera_id=? AND status='pendente'")->execute([$id]);
        foreach ($vacancyIds as $vacancyId) {
            $pdo->prepare("UPDATE fila_viva_vagas SET status='disponivel' WHERE id=? AND status='ofertada'")->execute([$vacancyId]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    audit_event($pdo, 'fila_viva_encaminhamento_cancelado', 'lista_espera', (string)$id);
    json_response(['success' => true, 'message' => 'Encaminhamento cancelado; eventual oferta pendente foi encerrada.']);
}

function fila_viva_update_confirmation(PDO $pdo, array $data): never
{
    [$session] = fila_viva_require_scope($pdo);
    $id = required_string($data, 'id');
    $confirmation = required_string($data, 'confirmacao_status');
    if (!in_array($confirmation, ['pendente', 'confirmada'], true)) json_response(['success' => false, 'message' => 'Estado de confirmação inválido.'], 422);
    $query = $pdo->prepare('SELECT ubs_id,status FROM consultas WHERE id=?');
    $query->execute([$id]);
    $row = $query->fetch();
    if (!$row) json_response(['success' => false, 'message' => 'Consulta não encontrada.'], 404);
    fila_viva_check_unit($pdo, $session, (string)$row['ubs_id']);
    if ($row['status'] !== 'agendado') json_response(['success' => false, 'message' => 'Apenas consultas agendadas podem ter confirmação atualizada.'], 409);
    $confirmedAt = $confirmation === 'confirmada' ? date('Y-m-d H:i:s') : null;
    $pdo->prepare('UPDATE consultas SET confirmacao_status=?,confirmada_em=? WHERE id=?')->execute([$confirmation, $confirmedAt, $id]);
    audit_event($pdo, 'fila_viva_confirmacao_atualizada', 'consultas', $id, ['confirmacao_status' => $confirmation]);
    json_response(['success' => true, 'message' => $confirmation === 'confirmada' ? 'Confirmação registrada após contato com o paciente.' : 'Consulta voltou para aguardando confirmação.']);
}

function fila_viva_reschedule(PDO $pdo, array $data): never
{
    [$session] = fila_viva_require_scope($pdo);
    $id = required_string($data, 'id');
    $newDate = required_string($data, 'data_consulta');
    if (!valid_date($newDate) || $newDate < date('Y-m-d') || !is_business_day($newDate)) json_response(['success' => false, 'message' => 'A nova data deve ser hoje ou futura e em dia de funcionamento.'], 422);

    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT id,ubs_id,especialidade,data_consulta,status FROM consultas WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $row = $query->fetch();
        if (!$row) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Consulta não encontrada.'], 404); }
        fila_viva_check_unit($pdo, $session, (string)$row['ubs_id']);
        if ($row['status'] !== 'agendado') { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Somente consultas agendadas podem ser reagendadas.'], 409); }
        $unitLock = $pdo->prepare('SELECT limite_diario FROM ubs WHERE id=? FOR UPDATE');
        $unitLock->execute([$row['ubs_id']]);
        $capacity = max(1, (int)$unitLock->fetchColumn());
        if ($newDate !== (string)$row['data_consulta']) {
            $count = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE ubs_id=? AND especialidade=? AND data_consulta=? AND status='agendado'");
            $count->execute([$row['ubs_id'], $row['especialidade'], $newDate]);
            if ((int)$count->fetchColumn() >= $capacity) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Não há capacidade configurada para esta especialidade e data.'], 409); }
        }
        $queue = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE ubs_id=? AND especialidade=? AND data_consulta=? AND status='agendado'");
        $queue->execute([$row['ubs_id'], $row['especialidade'], $newDate]);
        $position = $newDate === (string)$row['data_consulta'] ? null : (int)$queue->fetchColumn() + 1;
        if ($position === null) {
            $update = $pdo->prepare("UPDATE consultas SET confirmacao_status='pendente',confirmada_em=NULL WHERE id=?");
            $update->execute([$id]);
        } else {
            $update = $pdo->prepare("UPDATE consultas SET data_consulta=?,horario='00:00:00',fila=?,confirmacao_status='pendente',confirmada_em=NULL WHERE id=?");
            $update->execute([$newDate, $position, $id]);
            $pdo->prepare("UPDATE lista_espera SET data_consulta=? WHERE consulta_id=? AND status='agendado'")->execute([$newDate, $id]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && database_is_unique_violation($error)) json_response(['success' => false, 'message' => 'Já existe uma solicitação do paciente na fila para a nova data.'], 409);
        throw $error;
    }
    audit_event($pdo, 'fila_viva_consulta_reagendada', 'consultas', $id, ['data_consulta' => $newDate]);
    json_response(['success' => true, 'message' => 'Consulta reagendada; a confirmação foi reiniciada.']);
}

function fila_viva_expire_offers(PDO $pdo, ?string $vacancyId = null): void
{
    $sql = "UPDATE fila_viva_ofertas SET status='expirada',resposta_em=CURRENT_TIMESTAMP WHERE status='pendente' AND expira_em<=CURRENT_TIMESTAMP";
    $params = [];
    if ($vacancyId !== null) { $sql .= ' AND vaga_id=?'; $params[] = $vacancyId; }
    $expireQuery = $pdo->prepare($sql);
    $expireQuery->execute($params);
    $expiredCount = $expireQuery->rowCount();
    if ($expiredCount > 0) audit_event($pdo, 'fila_viva_ofertas_expiradas', 'fila_viva_ofertas', 'lote', ['quantidade' => $expiredCount]);
    $sql = "UPDATE fila_viva_vagas SET status='disponivel' WHERE status='ofertada' AND NOT EXISTS (SELECT 1 FROM fila_viva_ofertas o WHERE o.vaga_id=fila_viva_vagas.id AND o.status='pendente')";
    if ($vacancyId !== null) { $sql .= ' AND id=?'; $params2 = [$vacancyId]; $pdo->prepare($sql)->execute($params2); }
    else $pdo->exec($sql);
}

function fila_viva_offer_candidate(PDO $pdo, array $data): never
{
    [$session] = fila_viva_require_scope($pdo);
    $vacancyId = (int)($data['vaga_id'] ?? 0);
    $waitlistId = (int)($data['lista_espera_id'] ?? 0);
    if ($vacancyId < 1 || $waitlistId < 1) json_response(['success' => false, 'message' => 'Vaga ou candidato inválido.'], 422);

    $pdo->beginTransaction();
    try {
        fila_viva_expire_offers($pdo, (string)$vacancyId);
        $vacancyQuery = $pdo->prepare('SELECT id,ubs_id,especialidade,data_consulta,status FROM fila_viva_vagas WHERE id=? FOR UPDATE');
        $vacancyQuery->execute([$vacancyId]);
        $vacancy = $vacancyQuery->fetch();
        if (!$vacancy) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Vaga não encontrada.'], 404); }
        fila_viva_check_unit($pdo, $session, (string)$vacancy['ubs_id']);
        if ($vacancy['status'] !== 'disponivel') { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Esta vaga já está ofertada ou preenchida. Atualize o painel.'], 409); }
        if ((string)$vacancy['data_consulta'] <= date('Y-m-d') || !is_business_day((string)$vacancy['data_consulta'])) {
            $pdo->prepare("UPDATE fila_viva_vagas SET status='preenchida' WHERE id=?")->execute([$vacancyId]);
            $pdo->commit();
            json_response(['success' => false, 'message' => 'Vaga passada, do dia corrente ou em dia sem atendimento não pode ser oferecida.'], 409);
        }
        $vacancyUnit = fila_viva_check_unit($pdo, $session, (string)$vacancy['ubs_id']);
        if (!database_bool($vacancyUnit['ativa'] ?? false)) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'A unidade está inativa; a vaga não pode ser ofertada.'], 409); }
        $occupiedQuery = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE ubs_id=? AND especialidade=? AND data_consulta=? AND status IN ('agendado','atendido','faltou')");
        $occupiedQuery->execute([$vacancy['ubs_id'], $vacancy['especialidade'], $vacancy['data_consulta']]);
        if ((int)$occupiedQuery->fetchColumn() >= max(1, (int)$vacancyUnit['limite_diario'])) {
            $pdo->prepare("UPDATE fila_viva_vagas SET status='preenchida' WHERE id=?")->execute([$vacancyId]);
            $pdo->commit();
            json_response(['success' => false, 'message' => 'A capacidade da unidade já foi ocupada. Atualize a agenda antes de oferecer a vaga.'], 409);
        }

        $candidateQuery = $pdo->prepare("SELECT l.id,l.paciente_id,l.ubs_id,l.especialidade,l.data_consulta,l.prioridade_validada_em,p.nome,p.telefone,u.nome AS unidade FROM lista_espera l INNER JOIN pacientes p ON p.id=l.paciente_id INNER JOIN ubs u ON u.id=l.ubs_id WHERE l.id=? AND l.status='pendente' AND l.prioridade_validada_por IS NOT NULL FOR UPDATE");
        $candidateQuery->execute([$waitlistId]);
        $candidate = $candidateQuery->fetch();
        if (!$candidate) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Candidato não encontrado, não está pendente ou ainda não teve a prioridade validada pela Secretaria.'], 409); }
        fila_viva_check_unit($pdo, $session, (string)$candidate['ubs_id']);
        if ((string)$candidate['ubs_id'] !== (string)$vacancy['ubs_id'] || (string)$candidate['especialidade'] !== (string)$vacancy['especialidade'] || (string)$candidate['data_consulta'] !== (string)$vacancy['data_consulta']) {
            $pdo->rollBack(); json_response(['success' => false, 'message' => 'O candidato não corresponde à unidade, especialidade e data desta vaga.'], 409);
        }
        $already = $pdo->prepare("SELECT id FROM fila_viva_ofertas WHERE vaga_id=? AND lista_espera_id=? AND status='pendente'");
        $already->execute([$vacancyId, $waitlistId]);
        if ($already->fetchColumn()) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Já existe uma oferta pendente para este candidato.'], 409); }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $slotDeadline = strtotime((string)$vacancy['data_consulta'] . ' 00:00:00');
        $expiresTimestamp = min(time() + 24 * 60 * 60, $slotDeadline);
        if ($expiresTimestamp <= time() + 300) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Não há tempo suficiente para a pessoa responder antes do início da data da vaga.'], 409); }
        $expiresAt = date('Y-m-d H:i:s', $expiresTimestamp);
        $insert = $pdo->prepare("INSERT INTO fila_viva_ofertas (vaga_id,lista_espera_id,paciente_id,token_hash,status,expira_em,criado_por) VALUES (?,?,?,?,'pendente',?,?)");
        $insert->execute([$vacancyId, $waitlistId, (int)$candidate['paciente_id'], $tokenHash, $expiresAt, (int)$session['id']]);
        $offerId = (string)$pdo->lastInsertId();
        $pdo->prepare("UPDATE fila_viva_vagas SET status='ofertada' WHERE id=? AND status='disponivel'")->execute([$vacancyId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && database_is_unique_violation($error)) json_response(['success' => false, 'message' => 'A oferta já foi registrada. Atualize a fila.'], 409);
        throw $error;
    }

    $base = function_exists('app_public_url') ? rtrim(app_public_url(), '/') : '';
    $link = ($base !== '' ? $base : '') . '/#fila-viva-offer=' . rawurlencode($token);
    $safeDate = date('d/m/Y', strtotime((string)$vacancy['data_consulta']));
    $message = 'Uma vaga está disponível para ' . (string)$vacancy['especialidade'] . ' em ' . (string)$candidate['unidade'] . ', no dia ' . $safeDate . '. O horário será confirmado pela UBS. Confirme ou recuse até ' . date('d/m/Y H:i', strtotime($expiresAt)) . ': ' . $link;
    $notificationQueued = true;
    try {
        $pdo->prepare("INSERT INTO notificacoes (paciente_id,canal,tipo,mensagem,agendada_para,status) VALUES (?,'push','fila_viva_oferta',?,CURRENT_TIMESTAMP,'pendente')")
            ->execute([(int)$candidate['paciente_id'], 'Há uma oferta de vaga aguardando sua resposta. Acesse o link seguro enviado pela unidade.']);
    } catch (Throwable $ignored) { $notificationQueued = false; }
    $phone = preg_replace('/\D+/', '', (string)$candidate['telefone']);
    $whatsappUrl = null;
    if (str_starts_with($phone, '55') && in_array(strlen($phone), [12, 13], true)) {
        $whatsappUrl = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    } elseif (in_array(strlen($phone), [10, 11], true)) {
        $whatsappUrl = 'https://wa.me/55' . $phone . '?text=' . rawurlencode($message);
    }
    audit_event($pdo, 'fila_viva_vaga_ofertada', 'fila_viva_ofertas', $offerId, ['vaga_id' => $vacancyId, 'lista_espera_id' => $waitlistId]);
    $note = $notificationQueued ? 'Aviso interno registrado como pendente. A equipe deve enviar o WhatsApp manualmente; nenhum canal externo foi acionado.' : 'A oferta foi registrada, mas o aviso interno falhou. Use o link/mensagem abaixo para comunicar manualmente; nenhum canal externo foi acionado.';
    json_response(['success' => true, 'whatsapp_url' => $whatsappUrl, 'expires_at' => $expiresAt, 'message' => $message, 'note' => $note]);
}

function fila_viva_public_offer_preview(PDO $pdo, string $token): never
{
    $token = strtolower(trim($token));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) json_response(['success' => false, 'message' => 'Link de oferta inválido.'], 404);
    $hash = hash('sha256', $token);
    $query = $pdo->prepare("SELECT o.id,o.vaga_id,o.status,o.expira_em,v.especialidade,v.data_consulta,u.nome AS unidade FROM fila_viva_ofertas o INNER JOIN fila_viva_vagas v ON v.id=o.vaga_id INNER JOIN ubs u ON u.id=v.ubs_id WHERE o.token_hash=?");
    $query->execute([$hash]);
    $offer = $query->fetch();
    if (!$offer) json_response(['success' => false, 'message' => 'Link de oferta não encontrado ou já removido.'], 404);
    if ($offer['status'] !== 'pendente') json_response(['success' => false, 'message' => 'Esta oferta já foi respondida ou expirou.'], 410);
    if (strtotime((string)$offer['expira_em']) <= time() || (string)$offer['data_consulta'] <= date('Y-m-d') || !is_business_day((string)$offer['data_consulta'])) {
        $pdo->prepare("UPDATE fila_viva_ofertas SET status='expirada',resposta_em=CURRENT_TIMESTAMP WHERE id=? AND status='pendente'")->execute([$offer['id']]);
        $pdo->prepare("UPDATE fila_viva_vagas SET status='disponivel' WHERE id=? AND status='ofertada'")->execute([$offer['vaga_id']]);
        audit_event($pdo, 'fila_viva_oferta_expirada', 'fila_viva_ofertas', (string)$offer['id']);
        json_response(['success' => false, 'message' => 'O prazo terminou ou a data da vaga já não permite atendimento.'], 410);
    }
    json_response(['success' => true, 'offer' => ['especialidade' => $offer['especialidade'], 'data' => date('Y-m-d', strtotime((string)$offer['data_consulta'])), 'unidade' => $offer['unidade'], 'expira_em' => $offer['expira_em']]]);
}

function fila_viva_public_offer_response(PDO $pdo, array $data): never
{
    $token = strtolower(trim((string)($data['token'] ?? '')));
    $decision = trim((string)($data['decisao'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || !in_array($decision, ['aceitar', 'recusar'], true)) json_response(['success' => false, 'message' => 'Resposta inválida.'], 422);
    $hash = hash('sha256', $token);
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT o.id,o.vaga_id,o.lista_espera_id,o.paciente_id,o.status,o.expira_em,v.ubs_id,v.especialidade,v.data_consulta,v.status AS vaga_status FROM fila_viva_ofertas o INNER JOIN fila_viva_vagas v ON v.id=o.vaga_id WHERE o.token_hash=? FOR UPDATE');
        $query->execute([$hash]);
        $offer = $query->fetch();
        if (!$offer) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Oferta inválida ou não encontrada.'], 404); }
        $offerDateUnavailable = (string)$offer['data_consulta'] <= date('Y-m-d') || !is_business_day((string)$offer['data_consulta']);
        if ($offer['status'] !== 'pendente' || strtotime((string)$offer['expira_em']) <= time() || $offerDateUnavailable) {
            if ($offer['status'] === 'pendente') {
                $pdo->prepare("UPDATE fila_viva_ofertas SET status='expirada',resposta_em=CURRENT_TIMESTAMP WHERE id=?")->execute([$offer['id']]);
                $pdo->prepare("UPDATE fila_viva_vagas SET status='disponivel' WHERE id=? AND status='ofertada'")->execute([$offer['vaga_id']]);
            }
            $pdo->commit();
            if ($offer['status'] === 'pendente') audit_event($pdo, 'fila_viva_oferta_expirada', 'fila_viva_ofertas', (string)$offer['id']);
            json_response(['success' => false, 'message' => 'O prazo da oferta terminou ou ela já recebeu uma resposta.'], 410);
        }
        if ($offer['vaga_status'] !== 'ofertada') { $pdo->rollBack(); json_response(['success' => false, 'message' => 'A vaga não está mais disponível.'], 409); }

        if ($decision === 'recusar') {
            $pdo->prepare("UPDATE fila_viva_ofertas SET status='recusada',resposta_em=CURRENT_TIMESTAMP WHERE id=?")->execute([$offer['id']]);
            $pdo->prepare("UPDATE fila_viva_vagas SET status='disponivel' WHERE id=? AND status='ofertada'")->execute([$offer['vaga_id']]);
            $pdo->commit();
            audit_event($pdo, 'fila_viva_oferta_recusada', 'fila_viva_ofertas', (string)$offer['id']);
            json_response(['success' => true, 'message' => 'Resposta registrada. A unidade poderá oferecer a vaga a outra pessoa elegível.']);
        }

        $candidate = $pdo->prepare("SELECT id FROM lista_espera WHERE id=? AND paciente_id=? AND status='pendente' AND prioridade_validada_por IS NOT NULL FOR UPDATE");
        $candidate->execute([(int)$offer['lista_espera_id'], (int)$offer['paciente_id']]);
        if (!$candidate->fetchColumn()) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'A solicitação não está mais elegível para aceitar esta oferta.'], 409); }
        $unitLock = $pdo->prepare('SELECT limite_diario,nome,ativa FROM ubs WHERE id=? FOR UPDATE');
        $unitLock->execute([$offer['ubs_id']]);
        $unit = $unitLock->fetch();
        if (!$unit) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Unidade não encontrada.'], 404); }
        if (!database_bool($unit['ativa'] ?? false)) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'A unidade está inativa. Procure a Secretaria para orientação.'], 409); }
        $count = $pdo->prepare("SELECT COUNT(*) FROM consultas WHERE ubs_id=? AND especialidade=? AND data_consulta=? AND status IN ('agendado','atendido','faltou')");
        $count->execute([$offer['ubs_id'], $offer['especialidade'], $offer['data_consulta']]);
        $position = (int)$count->fetchColumn() + 1;
        if ($position > max(1, (int)$unit['limite_diario'])) {
            $pdo->prepare("UPDATE fila_viva_ofertas SET status='expirada',resposta_em=CURRENT_TIMESTAMP WHERE id=?")->execute([$offer['id']]);
            $pdo->prepare("UPDATE fila_viva_vagas SET status='disponivel' WHERE id=? AND status='ofertada'")->execute([$offer['vaga_id']]);
            $pdo->commit();
            audit_event($pdo, 'fila_viva_oferta_expirada_capacidade', 'fila_viva_ofertas', (string)$offer['id']);
            json_response(['success' => false, 'message' => 'A capacidade da unidade foi preenchida. A oferta expirou; entre em contato com a unidade para orientação.'], 409);
        }

        $appointmentId = 'CONS-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(9)));
        $insert = $pdo->prepare("INSERT INTO consultas (id,ubs_id,paciente_id,especialidade,assunto,data_consulta,horario,fila,status,confirmacao_status,confirmada_em) VALUES (?,?,?,?,'Vaga aceita pelo paciente via Fila Viva',?,'00:00:00',?,'agendado','pendente',NULL)");
        $insert->execute([$appointmentId, $offer['ubs_id'], (int)$offer['paciente_id'], $offer['especialidade'], $offer['data_consulta'], $position]);
        $pdo->prepare("UPDATE lista_espera SET status='agendado',consulta_id=? WHERE id=?")->execute([$appointmentId, (int)$offer['lista_espera_id']]);
        $pdo->prepare("UPDATE fila_viva_ofertas SET status='aceita',resposta_em=CURRENT_TIMESTAMP WHERE id=?")->execute([$offer['id']]);
        $pdo->prepare("UPDATE fila_viva_vagas SET status='preenchida' WHERE id=?")->execute([$offer['vaga_id']]);
        $message = 'Seu aceite foi registrado para ' . (string)$offer['especialidade'] . ' em ' . date('d/m/Y', strtotime((string)$offer['data_consulta'])) . '. Unidade: ' . (string)$unit['nome'] . '. A UBS ainda deve confirmar o horário. Protocolo: ' . $appointmentId . '.';
        $pdo->prepare("INSERT INTO notificacoes (paciente_id,consulta_id,canal,tipo,mensagem,agendada_para,status) VALUES (?,?,'push','fila_viva_vaga_confirmada',?,CURRENT_TIMESTAMP,'pendente')")
            ->execute([(int)$offer['paciente_id'], $appointmentId, $message]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($error instanceof PDOException && database_is_unique_violation($error)) json_response(['success' => false, 'message' => 'Não foi possível reservar a vaga. Atualize o painel ou contate a unidade.'], 409);
        throw $error;
    }
    audit_event($pdo, 'fila_viva_oferta_aceita', 'fila_viva_ofertas', (string)$offer['id'], ['consulta_id' => $appointmentId]);
    json_response(['success' => true, 'message' => 'Vaga confirmada. Guarde o protocolo ' . $appointmentId . ' e compareça à unidade na data informada.']);
}

function fila_viva_dashboard(PDO $pdo): never
{
    $requestedUnit = trim((string)($_GET['ubs_id'] ?? ''));
    [$session, $ubsId] = fila_viva_require_scope($pdo, $requestedUnit);
    $start = trim((string)($_GET['inicio'] ?? date('Y-m-d', strtotime('-89 days'))));
    $end = trim((string)($_GET['fim'] ?? date('Y-m-d')));
    $specialty = trim((string)($_GET['especialidade'] ?? ''));
    if (!valid_date($start) || !valid_date($end) || $start > $end || (strtotime($end) - strtotime($start)) > 366 * 86400) {
        json_response(['success' => false, 'message' => 'Período inválido. Selecione até 12 meses.'], 422);
    }

    $dateParams = [$start . ' 00:00:00', $end . ' 23:59:59'];
    $where = 'l.encaminhado_em BETWEEN ? AND ?';
    $params = $dateParams;
    if ($ubsId !== '') { $where .= ' AND l.ubs_id=?'; $params[] = $ubsId; }
    if ($specialty !== '') { $where .= ' AND l.especialidade=?'; $params[] = $specialty; }
    [$citySql, $cityParams] = fila_viva_sql_city_scope($session, 'u');

    $unitsSql = 'SELECT u.id,u.nome,u.cidade,u.estado,u.limite_diario FROM ubs u WHERE u.ativa=1' . $citySql;
    $unitsQuery = $pdo->prepare($unitsSql . ' ORDER BY u.nome');
    $unitsQuery->execute($cityParams);
    $units = $unitsQuery->fetchAll();
    if ($ubsId !== '') $units = array_values(array_filter($units, static fn($unit) => (string)$unit['id'] === $ubsId));
    $specialtySql = 'SELECT DISTINCT e.nome FROM ubs_especialidades e INNER JOIN ubs u ON u.id=e.ubs_id WHERE u.ativa=1' . $citySql;
    $specialtyQuery = $pdo->prepare($specialtySql . ' ORDER BY e.nome');
    $specialtyQuery->execute($cityParams);
    $specialties = array_map(static fn($row) => (string)$row['nome'], $specialtyQuery->fetchAll());
    $referralTargets = fila_viva_referral_targets($pdo, $session);

    $waitQuerySql = 'SELECT l.id,l.protocolo,l.ubs_id,u.nome AS unidade,l.origem_ubs_id,ou.nome AS origem,p.nome AS paciente,p.telefone,l.especialidade,l.data_consulta AS data,l.encaminhado_em,l.prioridade_categoria,l.prioridade_observacao,l.prioridade_validada_em,l.status,c.status AS consulta_status,a.usuario AS validador FROM lista_espera l INNER JOIN pacientes p ON p.id=l.paciente_id INNER JOIN ubs u ON u.id=l.ubs_id LEFT JOIN ubs ou ON ou.id=l.origem_ubs_id LEFT JOIN consultas c ON c.id=l.consulta_id LEFT JOIN administradores a ON a.id=l.prioridade_validada_por WHERE ' . $where . $citySql . ' ORDER BY l.encaminhado_em,l.id LIMIT 500';
    $waitQuery = $pdo->prepare($waitQuerySql);
    $waitQuery->execute(array_merge($params, $cityParams));
    $allReferrals = $waitQuery->fetchAll();

    $pendingQuery = $pdo->prepare('SELECT COUNT(*) FROM lista_espera l INNER JOIN ubs u ON u.id=l.ubs_id WHERE ' . $where . $citySql . " AND l.status='pendente'");
    $pendingQuery->execute(array_merge($params, $cityParams));
    $pendingCount = (int)$pendingQuery->fetchColumn();
    $referralTotalQuery = $pdo->prepare('SELECT COUNT(*) FROM lista_espera l INNER JOIN ubs u ON u.id=l.ubs_id WHERE ' . $where . $citySql);
    $referralTotalQuery->execute(array_merge($params, $cityParams));
    $referralTotal = (int)$referralTotalQuery->fetchColumn();
    $validationStatsQuery = $pdo->prepare("SELECT SUM(CASE WHEN l.prioridade_validada_em IS NULL THEN 1 ELSE 0 END) AS aguardando,SUM(CASE WHEN l.prioridade_validada_em IS NOT NULL THEN 1 ELSE 0 END) AS validadas FROM lista_espera l INNER JOIN ubs u ON u.id=l.ubs_id WHERE " . $where . $citySql . " AND l.status='pendente'");
    $validationStatsQuery->execute(array_merge($params, $cityParams));
    $validationStats = $validationStatsQuery->fetch() ?: [];
    $awaitingValidation = (int)($validationStats['aguardando'] ?? 0);
    $validatedCount = (int)($validationStats['validadas'] ?? 0);

    $appointmentWhere = 'c.data_consulta>=? AND c.data_consulta<=?';
    $appointmentParams = [$start, $end];
    if ($ubsId !== '') { $appointmentWhere .= ' AND c.ubs_id=?'; $appointmentParams[] = $ubsId; }
    if ($specialty !== '') { $appointmentWhere .= ' AND c.especialidade=?'; $appointmentParams[] = $specialty; }
    $appointmentWhere .= $citySql;
    $appointmentQuery = $pdo->prepare('SELECT c.id,c.ubs_id,u.nome AS unidade,p.nome AS paciente,p.telefone,c.especialidade,c.data_consulta AS data,c.horario,c.status,c.confirmacao_status,c.confirmada_em FROM consultas c INNER JOIN pacientes p ON p.id=c.paciente_id INNER JOIN ubs u ON u.id=c.ubs_id WHERE ' . $appointmentWhere . ' ORDER BY c.data_consulta DESC,c.horario LIMIT 500');
    $appointmentQuery->execute(array_merge($appointmentParams, $cityParams));
    $appointments = $appointmentQuery->fetchAll();

    $appointmentStatsQuery = $pdo->prepare('SELECT c.status,c.confirmacao_status,COUNT(*) AS total FROM consultas c INNER JOIN ubs u ON u.id=c.ubs_id WHERE ' . $appointmentWhere . ' GROUP BY c.status,c.confirmacao_status');
    $appointmentStatsQuery->execute(array_merge($appointmentParams, $cityParams));
    $appointmentStats = ['agendado' => 0, 'atendido' => 0, 'faltou' => 0, 'cancelado' => 0, 'confirmadas' => 0, 'aguardandoConfirmacao' => 0];
    foreach ($appointmentStatsQuery->fetchAll() as $row) {
        $status = (string)$row['status'];
        $count = (int)$row['total'];
        if (array_key_exists($status, $appointmentStats)) $appointmentStats[$status] += $count;
        if ($status === 'agendado' && (string)$row['confirmacao_status'] === 'confirmada') $appointmentStats['confirmadas'] += $count;
        if ($status === 'agendado' && (string)$row['confirmacao_status'] !== 'confirmada') $appointmentStats['aguardandoConfirmacao'] += $count;
    }

    $waitTimesQuery = $pdo->prepare('SELECT l.encaminhado_em,c.data_consulta FROM lista_espera l INNER JOIN consultas c ON c.id=l.consulta_id INNER JOIN ubs u ON u.id=l.ubs_id WHERE ' . $where . $citySql . " AND c.status='atendido'");
    $waitTimesQuery->execute(array_merge($params, $cityParams));
    $waitTimes = [];
    foreach ($waitTimesQuery->fetchAll() as $row) {
        try {
            $from = new DateTimeImmutable(substr((string)$row['encaminhado_em'], 0, 10));
            $to = new DateTimeImmutable((string)$row['data_consulta']);
            $days = (int)$from->diff($to)->format('%r%a');
            if ($days >= 0) $waitTimes[] = $days;
        } catch (Throwable $ignored) {}
    }
    sort($waitTimes);
    $averageWait = count($waitTimes) ? round(array_sum($waitTimes) / count($waitTimes), 1) : null;
    $medianWait = null;
    if ($waitTimes) {
        $middle = intdiv(count($waitTimes), 2);
        $medianWait = count($waitTimes) % 2 ? $waitTimes[$middle] : round(($waitTimes[$middle - 1] + $waitTimes[$middle]) / 2, 1);
    }

    fila_viva_expire_offers($pdo);
    $vacancyWhere = 'v.criado_em BETWEEN ? AND ?';
    $vacancyParams = $dateParams;
    if ($ubsId !== '') { $vacancyWhere .= ' AND v.ubs_id=?'; $vacancyParams[] = $ubsId; }
    if ($specialty !== '') { $vacancyWhere .= ' AND v.especialidade=?'; $vacancyParams[] = $specialty; }
    $vacancyWhere .= $citySql;
    $vacancyQuery = $pdo->prepare('SELECT v.id,v.cancelamento_consulta_id,v.ubs_id,u.nome AS unidade,v.especialidade,v.data_consulta AS data,v.status,v.criado_em FROM fila_viva_vagas v INNER JOIN ubs u ON u.id=v.ubs_id WHERE ' . $vacancyWhere . ' ORDER BY v.criado_em DESC LIMIT 300');
    $vacancyQuery->execute(array_merge($vacancyParams, $cityParams));
    $vacancies = $vacancyQuery->fetchAll();
    foreach ($vacancies as &$vacancy) {
        $offerQuery = $pdo->prepare("SELECT o.status,o.expira_em,l.id AS lista_espera_id,p.nome AS paciente FROM fila_viva_ofertas o INNER JOIN lista_espera l ON l.id=o.lista_espera_id INNER JOIN pacientes p ON p.id=o.paciente_id WHERE o.vaga_id=? AND o.status='pendente' ORDER BY o.id DESC LIMIT 1");
        $offerQuery->execute([(int)$vacancy['id']]);
        $vacancy['oferta'] = $offerQuery->fetch() ?: null;
    }
    unset($vacancy);

    $vacancyStatsQuery = $pdo->prepare("SELECT v.status,COUNT(*) AS total FROM fila_viva_vagas v INNER JOIN ubs u ON u.id=v.ubs_id WHERE " . $vacancyWhere . ' GROUP BY v.status');
    $vacancyStatsQuery->execute(array_merge($vacancyParams, $cityParams));
    $vacancyStats = ['disponivel' => 0, 'ofertada' => 0, 'preenchida' => 0];
    foreach ($vacancyStatsQuery->fetchAll() as $row) if (isset($vacancyStats[(string)$row['status']])) $vacancyStats[(string)$row['status']] = (int)$row['total'];
    $reuseQuery = $pdo->prepare("SELECT COUNT(*) FROM fila_viva_ofertas o INNER JOIN fila_viva_vagas v ON v.id=o.vaga_id INNER JOIN ubs u ON u.id=v.ubs_id WHERE v.criado_em BETWEEN ? AND ? AND o.status='aceita'" . ($ubsId !== '' ? ' AND v.ubs_id=?' : '') . $citySql);
    $reuseQuery->execute(array_merge($dateParams, $ubsId !== '' ? [$ubsId] : [], $cityParams));
    $reused = (int)$reuseQuery->fetchColumn();

    $outcomeCount = $appointmentStats['atendido'] + $appointmentStats['faltou'];
    $noShowRate = $outcomeCount > 0 ? round($appointmentStats['faltou'] / $outcomeCount * 100, 1) : null;
    $confirmationBase = $appointmentStats['confirmadas'] + $appointmentStats['aguardandoConfirmacao'];
    $confirmationRate = $confirmationBase > 0 ? round($appointmentStats['confirmadas'] / $confirmationBase * 100, 1) : null;

    $capacity = 0;
    $capacityUnitsSql = 'SELECT u.id,u.limite_diario,e.nome AS especialidade FROM ubs u INNER JOIN ubs_especialidades e ON e.ubs_id=u.id WHERE u.ativa=1' . $citySql;
    $capacityParams = $cityParams;
    if ($ubsId !== '') { $capacityUnitsSql .= ' AND u.id=?'; $capacityParams[] = $ubsId; }
    if ($specialty !== '') { $capacityUnitsSql .= ' AND e.nome=?'; $capacityParams[] = $specialty; }
    $capacityQuery = $pdo->prepare($capacityUnitsSql);
    $capacityQuery->execute($capacityParams);
    $capacityRows = $capacityQuery->fetchAll();
    $businessDays = 0;
    $dateCursor = new DateTimeImmutable($start);
    $dateEnd = new DateTimeImmutable($end);
    while ($dateCursor <= $dateEnd) {
        if (is_business_day($dateCursor->format('Y-m-d'))) $businessDays++;
        $dateCursor = $dateCursor->modify('+1 day');
    }
    foreach ($capacityRows as $capacityRow) $capacity += max(1, (int)$capacityRow['limite_diario']) * $businessDays;
    $usedQuery = $pdo->prepare("SELECT COUNT(*) FROM consultas c INNER JOIN ubs u ON u.id=c.ubs_id WHERE c.data_consulta>=? AND c.data_consulta<=? AND c.status IN ('agendado','atendido','faltou')" . ($ubsId !== '' ? ' AND c.ubs_id=?' : '') . ($specialty !== '' ? ' AND c.especialidade=?' : '') . $citySql);
    $usedQuery->execute(array_merge([$start, $end], $ubsId !== '' ? [$ubsId] : [], $specialty !== '' ? [$specialty] : [], $cityParams));
    $used = (int)$usedQuery->fetchColumn();
    $occupancy = $capacity > 0 ? round($used / $capacity * 100, 1) : null;

    $monthRefQuery = $pdo->prepare('SELECT l.encaminhado_em,c.status AS consulta_status FROM lista_espera l INNER JOIN ubs u ON u.id=l.ubs_id LEFT JOIN consultas c ON c.id=l.consulta_id WHERE ' . $where . $citySql . ' ORDER BY l.encaminhado_em DESC LIMIT 5000');
    $monthRefQuery->execute(array_merge($params, $cityParams));
    $monthly = [];
    foreach ($monthRefQuery->fetchAll() as $row) {
        $month = substr((string)$row['encaminhado_em'], 0, 7);
        if ($month === '') continue;
        $monthly[$month] ??= ['mes' => $month, 'encaminhamentos' => 0, 'atendidos' => 0];
        $monthly[$month]['encaminhamentos']++;

    }
    $monthlyAppointments = $pdo->prepare("SELECT c.data_consulta,c.status FROM consultas c INNER JOIN ubs u ON u.id=c.ubs_id WHERE " . $appointmentWhere . $citySql . " AND c.status='atendido' ORDER BY c.data_consulta DESC LIMIT 5000");
    $monthlyAppointments->execute(array_merge($appointmentParams, $cityParams));
    foreach ($monthlyAppointments->fetchAll() as $row) {
        $month = substr((string)$row['data_consulta'], 0, 7);
        $monthly[$month] ??= ['mes' => $month, 'encaminhamentos' => 0, 'atendidos' => 0];
        $monthly[$month]['atendidos']++;
    }
    ksort($monthly);

    json_response(['success' => true, 'periodo' => ['inicio' => $start, 'fim' => $end], 'ubs' => $units, 'unidadesDestino' => $referralTargets, 'especialidades' => $specialties, 'metrics' => [
        'encaminhamentos' => $referralTotal, 'pendentes' => $pendingCount, 'aguardandoValidacao' => $awaitingValidation, 'prioridadesValidadas' => $validatedCount,
        'tempoMedioEspera' => $averageWait, 'tempoMedianoEspera' => $medianWait, 'amostraEspera' => count($waitTimes),
        'agendadas' => $appointmentStats['agendado'], 'realizadas' => $appointmentStats['atendido'], 'faltas' => $appointmentStats['faltou'], 'canceladas' => $appointmentStats['cancelado'],
        'taxaFaltasRegistradas' => $noShowRate, 'confirmadas' => $appointmentStats['confirmadas'], 'aguardandoConfirmacao' => $appointmentStats['aguardandoConfirmacao'], 'taxaConfirmacao' => $confirmationRate,
        'vagasLiberadas' => $releasedVacancies, 'vagasDisponiveis' => $vacancyStats['disponivel'], 'vagasOfertadas' => $vacancyStats['ofertada'], 'vagasPreenchidas' => $vacancyStats['preenchida'], 'vagasReaproveitadas' => $reused, 'taxaReaproveitamento' => $reuseRate,
        'ocupacaoAgenda' => $occupancy, 'capacidadeConfigurada' => $capacity, 'horariosOcupados' => $used
    ], 'encaminhamentos' => $allReferrals, 'vagas' => $vacancies, 'consultas' => $appointments, 'evolucaoMensal' => array_values($monthly), 'limiteResultados' => 500]);
}


function fila_viva_cancel_appointment(PDO $pdo, array $data): never
{
    [$session] = fila_viva_require_scope($pdo);
    $id = required_string($data, 'id');
    $reason = trim((string)($data['motivo'] ?? ''));
    if (mb_strlen($reason, 'UTF-8') > 255) json_response(['success' => false, 'message' => 'O motivo deve ter até 255 caracteres.'], 422);

    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT id,ubs_id,especialidade,data_consulta,status FROM consultas WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $row = $query->fetch();
        if (!$row) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Consulta não encontrada.'], 404); }
        fila_viva_check_unit($pdo, $session, (string)$row['ubs_id']);
        if ($row['status'] === 'cancelado') { $pdo->commit(); json_response(['success' => true, 'message' => 'A consulta já estava cancelada.']); }
        if ($row['status'] !== 'agendado') { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Somente consultas ainda agendadas podem ser canceladas por este fluxo.'], 409); }
        $pdo->prepare("UPDATE consultas SET status='cancelado',cancelamento_motivo=? WHERE id=?")->execute([$reason !== '' ? $reason : null, $id]);
        if ($row['status'] === 'agendado') {
            fila_viva_record_vacancy($pdo, $id, (string)$row['ubs_id'], (string)$row['especialidade'], (string)$row['data_consulta']);
            $pdo->prepare("UPDATE lista_espera SET status='cancelado' WHERE consulta_id=? AND status='agendado'")->execute([$id]);
        }
        $pdo->prepare("UPDATE notificacoes SET status='erro' WHERE consulta_id=? AND status='pendente'")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    audit_event($pdo, 'fila_viva_consulta_cancelada', 'consultas', $id, ['motivo_preenchido' => $reason !== '']);
    json_response(['success' => true, 'message' => 'Consulta cancelada. Se a data ainda é futura, a vaga foi registrada para oferta controlada.']);
}

function fila_viva_update_appointment_status(PDO $pdo, array $data): never
{
    [$session] = fila_viva_require_scope($pdo);
    $id = required_string($data, 'id');
    $status = required_string($data, 'status');
    if (!in_array($status, ['atendido', 'faltou'], true)) json_response(['success' => false, 'message' => 'Estado inválido para este endpoint.'], 422);

    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT ubs_id,status,data_consulta FROM consultas WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $row = $query->fetch();
        if (!$row) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Consulta não encontrada.'], 404); }
        fila_viva_check_unit($pdo, $session, (string)$row['ubs_id']);
        if ($row['status'] !== 'agendado') { $pdo->rollBack(); json_response(['success' => false, 'message' => 'Somente consultas agendadas podem receber este desfecho.'], 409); }
        if ((string)$row['data_consulta'] > date('Y-m-d')) { $pdo->rollBack(); json_response(['success' => false, 'message' => 'O desfecho só pode ser registrado na data da consulta ou depois.'], 409); }
        $pdo->prepare('UPDATE consultas SET status=? WHERE id=?')->execute([$status, $id]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    audit_event($pdo, 'fila_viva_consulta_desfecho', 'consultas', $id, ['status' => $status]);
    json_response(['success' => true, 'message' => $status === 'atendido' ? 'Consulta marcada como realizada.' : 'Falta registrada.']);
}
