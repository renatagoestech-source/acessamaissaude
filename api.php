<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/common.php';

try {
    $pdo = db();
    $action = $_GET['action'] ?? '';

    switch ($action) {

        case 'professional_register': professional_register($pdo, body_json()); break;
        case 'public_clinic': public_clinic($pdo, trim((string)($_GET['slug'] ?? ''))); break;
        case 'public_clinic_book': public_clinic_book($pdo, body_json()); break;
        case 'professional_login': professional_login($pdo, body_json()); break;
        case 'professional_logout': $_SESSION['professional']=null; json_response(['success'=>true]);
        case 'professional_me': professional_me($pdo); break;
        case 'professional_update_settings': professional_update_settings($pdo, body_json()); break;
        case 'professional_update_relationship_message': professional_update_relationship_message($pdo, body_json()); break;
        case 'professional_update_patient': professional_update_patient($pdo, body_json()); break;
        case 'professional_upload_logo': professional_upload_logo($pdo); break;
        case 'professional_link_patient': professional_link_patient($pdo, body_json()); break;
        case 'professional_patients': professional_patients($pdo); break;
        case 'professional_create_patient': professional_create_patient($pdo, body_json()); break;
        case 'professional_patient_history': professional_patient_history($pdo); break;
        case 'professional_finance': professional_finance($pdo); break;
        case 'professional_daily_report': professional_daily_report($pdo); break;
        case 'asaas_webhook': asaas_webhook($pdo); break;
        case 'professional_register_payment': professional_register_payment($pdo, body_json()); break;
        case 'professional_receipt': professional_receipt($pdo); break;
        case 'professional_create_record': professional_create_record($pdo, body_json()); break;
        case 'professional_records': professional_records($pdo); break;
        case 'professional_relationships': professional_relationships($pdo); break;
        case 'professional_log_relationship': professional_log_relationship($pdo, body_json()); break;
        case 'professional_create_appointment': professional_create_appointment($pdo, body_json()); break;
        case 'professional_appointments': professional_appointments($pdo); break;
        case 'professional_update_appointment': professional_update_appointment($pdo, body_json()); break;
        case 'subscription_plans': subscription_plans($pdo); break;
        case 'professional_subscription': professional_subscription($pdo); break;
        case 'admin_professional_payments': admin_professional_payments($pdo); break;
        case 'admin_update_professional_payment': admin_update_professional_payment($pdo, body_json()); break;
        case 'professional_start_subscription': professional_start_subscription($pdo, body_json()); break;
        case 'professional_cancel_subscription': professional_cancel_subscription($pdo); break;
        case 'get_app_config':
            get_app_config($pdo);
            break;

        case 'update_app_config':
            update_app_config($pdo, body_json());
            break;

        case 'get_ubs':
            json_response([
                'success' => true,
                'ubs' => all_ubs($pdo, false)
            ]);
            break;

        case 'save_patient':
            save_patient($pdo, body_json());
            break;

        case 'update_patient_profile':
            update_patient_profile($pdo, body_json());
            break;

        case 'create_ubs':
            create_ubs($pdo, body_json());
            break;

        case 'support_message':
            support_message($pdo, body_json());
            break;

        case 'get_support':
            get_support($pdo, trim((string)($_GET['sus'] ?? '')));
            break;

        case 'admin_support_messages':
            admin_support_messages($pdo);
            break;

        case 'admin_update_support':
            admin_update_support($pdo, body_json());
            break;

        case 'admin_audit':
            admin_audit($pdo);
            break;

        case 'get_patient_appointments':
            get_patient_appointments(
                $pdo,
                trim((string)($_GET['sus'] ?? ''))
            );
            break;

        case 'get_patient_notifications':
            get_patient_notifications($pdo, trim((string)($_GET['sus'] ?? '')));
            break;

        case 'join_waitlist':
            join_waitlist($pdo, body_json());
            break;

        case 'get_occupied_slots':
            get_occupied_slots(
                $pdo,
                trim((string)($_GET['ubs_id'] ?? '')),
                trim((string)($_GET['especialidade'] ?? '')),
                trim((string)($_GET['data'] ?? ''))
            );
            break;

        case 'book_appointment':
            book_appointment($pdo, body_json());
            break;

        case 'cancel_appointment':
            cancel_appointment($pdo, body_json());
            break;

        case 'set_reminder':
            set_reminder($pdo, body_json());
            break;

        case 'get_patient_exams':
            get_patient_exams($pdo, trim((string)($_GET['sus'] ?? '')));
            break;

        case 'admin_update_appointment_status':
            admin_update_appointment_status($pdo, body_json());
            break;

        case 'admin_save_exam':
            admin_save_exam($pdo, $_POST ?: body_json());
            break;

        case 'download_exam':
            download_exam($pdo);
            break;

        case 'admin_exams':
            admin_exams($pdo);
            break;

        case 'login':
            login_admin($pdo, body_json());
            break;

        case 'admin_ubs_data':
            $ubsId = trim((string)($_GET['ubs_id'] ?? ''));
            authorize_ubs($ubsId);

            json_response([
                'success' => true,
                'ubs' => get_ubs($pdo, $ubsId, true)
            ]);
            break;

        case 'update_ubs':
            update_ubs($pdo, body_json());
            break;

        case 'save_employee':
            save_employee($pdo, body_json());
            break;

        case 'delete_employee':
            delete_employee($pdo, body_json());
            break;

        case 'admin_appointments':
            admin_appointments($pdo);
            break;

        case 'logout':
            $_SESSION = [];
            session_destroy();

            json_response([
                'success' => true
            ]);
            break;

        default:
            json_response([
                'success' => false,
                'message' => 'Ação não encontrada.'
            ], 404);
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    json_response([
        'success' => false,
        'message' => 'Erro de banco de dados. Verifique se o MySQL do XAMPP está iniciado e se o banco foi instalado.'
    ], 500);

} catch (Throwable $e) {

    error_log($e->getMessage());

    json_response([
        'success' => false,
        'message' => 'Erro interno do sistema.'
    ], 500);
}

function all_ubs(PDO $pdo, bool $admin): array
{
    $stmt = $pdo->query(
        'SELECT id, nome, endereco, telefone, horario, usuario
         FROM ubs
         ORDER BY nome'
    );

    $result = [];

    foreach ($stmt->fetchAll() as $row) {
        $result[] = get_ubs_from_row($pdo, $row, $admin);
    }

    return $result;
}

function get_ubs(PDO $pdo, string $id, bool $admin = false): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nome, endereco, telefone, horario, usuario
         FROM ubs
         WHERE id = ?'
    );

    $stmt->execute([$id]);

    $row = $stmt->fetch();

    if (!$row) {
        json_response([
            'success' => false,
            'message' => 'UBS não encontrada.'
        ], 404);
    }

    return get_ubs_from_row($pdo, $row, $admin);
}

function get_ubs_from_row(PDO $pdo, array $row, bool $admin): array
{
    $id = $row['id'];

    $specialties = $pdo->prepare(
        'SELECT nome FROM ubs_especialidades WHERE ubs_id = ? ORDER BY id'
    );
    $specialties->execute([$id]);

    $services = $pdo->prepare(
        'SELECT nome FROM ubs_servicos WHERE ubs_id = ? ORDER BY id'
    );
    $services->execute([$id]);

    $campaigns = $pdo->prepare(
        'SELECT nome FROM ubs_campanhas WHERE ubs_id = ? ORDER BY id'
    );
    $campaigns->execute([$id]);

    $documents = $pdo->prepare(
        'SELECT nome FROM ubs_documentos WHERE ubs_id = ? ORDER BY id'
    );
    $documents->execute([$id]);

    $employees = $pdo->prepare(
        'SELECT id, nome, cargo FROM funcionarios WHERE ubs_id = ? ORDER BY nome'
    );
    $employees->execute([$id]);

    $result = [
        'id' => $row['id'],
        'nome' => $row['nome'],
        'endereco' => $row['endereco'],
        'telefone' => $row['telefone'],
        'horario' => $row['horario'],
        'especialidades' => array_column($specialties->fetchAll(), 'nome'),
        'servicos' => array_column($services->fetchAll(), 'nome'),
        'campanhas' => array_column($campaigns->fetchAll(), 'nome'),
        'documentos' => array_column($documents->fetchAll(), 'nome'),
        'funcionarios' => $employees->fetchAll()
    ];

    if ($admin) {
        $result['usuario'] = $row['usuario'];
        $result['senha'] = '';
    }

    return $result;
}

function professional_register(PDO $pdo, array $data): never {
    $nome=required_string($data,'nome'); $email=strtolower(required_string($data,'email')); $senha=(string)($data['senha']??'');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($senha)<8) json_response(['success'=>false,'message'=>'Informe um e-mail válido e uma senha com pelo menos 8 caracteres.'],422);
    try { $slug=slug_publico($pdo,$nome);$q=$pdo->prepare('INSERT INTO profissionais (nome,email,slug,senha_hash,especialidade,registro_profissional,telefone) VALUES (?,?,?,?,?,?,?)');$q->execute([$nome,$email,$slug,password_hash($senha,PASSWORD_DEFAULT),trim((string)($data['especialidade']??''))?:null,trim((string)($data['registro_profissional']??''))?:null,trim((string)($data['telefone']??''))?:null]);$id=(int)$pdo->lastInsertId();$_SESSION['professional']=['id'=>$id,'email'=>$email,'nome'=>$nome];json_response(['success'=>true,'professional'=>['id'=>$id,'nome'=>$nome,'email'=>$email,'slug'=>$slug]]); } catch(PDOException $e){ if((int)($e->errorInfo[1]??0)===1062) json_response(['success'=>false,'message'=>'Este e-mail já está cadastrado.'],409); json_response(['success'=>false,'message'=>'Não foi possível criar a conta profissional. Verifique os dados e tente novamente.'],500); }
}
function slug_publico(PDO $pdo,string $nome): string { $s=iconv('UTF-8','ASCII//TRANSLIT',$nome);$s=preg_replace('/[^a-z0-9]+/','-',strtolower((string)$s));$s=trim($s,'-')?:'profissional';$base=$s;$i=2;$q=$pdo->prepare('SELECT 1 FROM profissionais WHERE slug=? LIMIT 1');while(true){$q->execute([$s]);if(!$q->fetchColumn())return $s;$s=$base.'-'.$i++;} }
function public_clinic(PDO $pdo,string $slug): never { if($slug==='')json_response(['success'=>false,'message'=>'Clínica não informada.'],422);$q=$pdo->prepare('SELECT id,nome,slug,especialidade,registro_profissional AS registroProfissional,telefone,whatsapp,endereco,modalidade,valor_consulta AS valorConsulta,apresentacao,logo_arquivo AS logoArquivo,horario_funcionamento AS horarioFuncionamento,mensagem_pos_venda AS mensagemPosVenda,aviso_publico AS avisoPublico,cor_primaria AS corPrimaria,cor_secundaria AS corSecundaria FROM profissionais WHERE slug=? AND status=\'ativo\'');$q->execute([$slug]);$p=$q->fetch();if(!$p)json_response(['success'=>false,'message'=>'Clínica não encontrada.'],404);json_response(['success'=>true,'clinic'=>$p]);}
function normalize_cpf($value): string { return preg_replace('/\D+/', '', (string)$value); }
function valid_cpf(string $cpf): bool { if(strlen($cpf)!==11 || preg_match('/^(\d)\1{10}$/',$cpf)) return false; for($t=9;$t<11;$t++){ $sum=0; for($i=0;$i<$t;$i++) $sum += (int)$cpf[$i]*(($t+1)-$i); $digit=(($sum*10)%11)%10; if((int)$cpf[$t]!==$digit)return false; } return true; }
function public_clinic_book(PDO $pdo,array $data): never { $slug=required_string($data,'slug');$nome=required_string($data,'nome');$cpf=normalize_cpf($data['cpf']??'');$email=trim((string)($data['email']??''));$telefone=required_string($data,'telefone');$date=required_string($data,'data_consulta');$time=required_string($data,'horario');if(!valid_cpf($cpf)||!filter_var($email,FILTER_VALIDATE_EMAIL)||!valid_date($date))json_response(['success'=>false,'message'=>'Informe CPF, e-mail e data válidos.'],422);$q=$pdo->prepare('SELECT id,valor_consulta FROM profissionais WHERE slug=? AND status=\'ativo\'');$q->execute([$slug]);$pro=$q->fetch();if(!$pro)json_response(['success'=>false,'message'=>'Clínica não encontrada.'],404);$q=$pdo->prepare('SELECT id FROM consultas_profissionais WHERE profissional_id=? AND data_consulta=? AND horario=? AND status NOT IN (\'cancelada\',\'faltou\')');$q->execute([$pro['id'],$date,$time]);if($q->fetch())json_response(['success'=>false,'message'=>'Este horário já foi ocupado.'],409);$q=$pdo->prepare('SELECT id FROM pacientes WHERE cpf=? ORDER BY id DESC LIMIT 1');$q->execute([$cpf]);$pat=$q->fetch();if(!$pat){enforce_professional_patient_limit($pdo,(int)$pro['id']);$synthetic='CLI-'.$pro['id'].'-'.bin2hex(random_bytes(5));$q=$pdo->prepare('INSERT INTO pacientes (nome,telefone,email,cpf,sus,ubs_id) VALUES (?,?,?,?,?,NULL)');$q->execute([$nome,$telefone,$email,$cpf,$synthetic]);$patientId=(int)$pdo->lastInsertId();}else{$patientId=(int)$pat['id'];enforce_professional_patient_limit($pdo,(int)$pro['id'],$patientId);$q=$pdo->prepare('UPDATE pacientes SET nome=?,telefone=?,email=?,cpf=? WHERE id=?');$q->execute([$nome,$telefone,$email,$cpf,$patientId]);}$q=$pdo->prepare('INSERT IGNORE INTO profissional_pacientes (profissional_id,paciente_id,consentimento_em) VALUES (?,?,NOW())');$q->execute([$pro['id'],$patientId]);$id='PUB'.date('YmdHis').bin2hex(random_bytes(3));$q=$pdo->prepare('INSERT INTO consultas_profissionais (id,profissional_id,paciente_id,data_consulta,horario,assunto,valor,status) VALUES (?,?,?,?,?,?,?,\'confirmada\')');$q->execute([$id,$pro['id'],$patientId,$date,$time,trim((string)($data['assunto']??''))?:null,$pro['valor_consulta']]);criar_notificacoes_profissionais($pdo,(int)$pro['id'],$patientId,$id,$date,$time,$nome,$pro['nome']);json_response(['success'=>true,'message'=>'Consulta confirmada automaticamente. Você receberá a confirmação e um lembrete um dia antes.','appointment_id'=>$id,'confirmation'=>['id'=>$id,'clinic'=>$pro['id'],'date'=>$date,'time'=>$time,'value'=>$pro['valor_consulta'],'status'=>'confirmada']]);}

function professional_login(PDO $pdo, array $data): never { $email=strtolower(required_string($data,'email'));$senha=(string)($data['senha']??'');$q=$pdo->prepare('SELECT id,nome,email,senha_hash,status FROM profissionais WHERE email=?');$q->execute([$email]);$p=$q->fetch();if(!$p||!password_verify($senha,$p['senha_hash'])||$p['status']!=='ativo')json_response(['success'=>false,'message'=>'E-mail, senha ou status inválido.'],401);$_SESSION['professional']=['id'=>(int)$p['id'],'email'=>$p['email'],'nome'=>$p['nome']];json_response(['success'=>true,'professional'=>['id'=>(int)$p['id'],'nome'=>$p['nome'],'email'=>$p['email']]]);}
function professional_me(PDO $pdo): never { $s=require_professional();$q=$pdo->prepare('SELECT id,nome,email,slug,cnpj,especialidade,registro_profissional AS registroProfissional,telefone,whatsapp,endereco,modalidade,valor_consulta AS valorConsulta,apresentacao,logo_arquivo AS logoArquivo,horario_funcionamento AS horarioFuncionamento,mensagem_pos_venda AS mensagemPosVenda,aviso_publico AS avisoPublico,cor_primaria AS corPrimaria,cor_secundaria AS corSecundaria,status FROM profissionais WHERE id=?');$q->execute([$s['id']]);json_response(['success'=>true,'professional'=>$q->fetch()]);}
function professional_update_settings(PDO $pdo,array $data): never { $s=require_professional();$nome=required_string($data,'nome');if(!array_key_exists('logo_arquivo',$data)){ $keep=$pdo->prepare('SELECT logo_arquivo FROM profissionais WHERE id=?');$keep->execute([$s['id']]);$data['logo_arquivo']=$keep->fetchColumn(); }$fields=['cnpj','especialidade','registro_profissional','telefone','whatsapp','endereco','modalidade','apresentacao','logo_arquivo','horario_funcionamento','aviso_publico','mensagem_pos_venda','cor_primaria','cor_secundaria'];$v=[];foreach($fields as $f)$v[]=trim((string)($data[$f]??''))?:null;$q=$pdo->prepare('UPDATE profissionais SET nome=?,cnpj=?,especialidade=?,registro_profissional=?,telefone=?,whatsapp=?,endereco=?,modalidade=?,apresentacao=?,logo_arquivo=?,horario_funcionamento=?,aviso_publico=?,mensagem_pos_venda=?,cor_primaria=COALESCE(?,cor_primaria),cor_secundaria=COALESCE(?,cor_secundaria),valor_consulta=? WHERE id=?');$q->execute([$nome,$v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$v[6],$v[7],$v[8],$v[9],$v[10],$v[11],$v[12],$v[13],$data['valor_consulta']??null,$s['id']]);professional_me($pdo);}
function professional_update_relationship_message(PDO $pdo,array $data): never {
    $s=require_professional(); $message=trim((string)($data['mensagem_pos_venda']??''));
    if($message==='') json_response(['success'=>false,'message'=>'Informe uma mensagem de pós-venda.'],422);
    if(strlen($message)>2000) json_response(['success'=>false,'message'=>'A mensagem deve ter no máximo 2.000 caracteres.'],422);
    $q=$pdo->prepare('UPDATE profissionais SET mensagem_pos_venda=? WHERE id=?'); $q->execute([$message,$s['id']]);
    json_response(['success'=>true,'message'=>'Mensagem de pós-venda salva.']);
}
function professional_patient_limit(PDO $pdo,int $professionalId): array {
    $q=$pdo->prepare("SELECT p.limite_pacientes,a.status FROM assinaturas_profissionais a INNER JOIN planos_assinatura p ON p.id=a.plano_id WHERE a.profissional_id=? AND a.status='ativa' AND (a.fim IS NULL OR a.fim>=CURDATE()) ORDER BY a.id DESC LIMIT 1");
    $q->execute([$professionalId]);$sub=$q->fetch();
    return $sub ? ['limite'=>(int)$sub['limite_pacientes'],'assinado'=>true] : ['limite'=>5,'assinado'=>false];
}
function enforce_professional_patient_limit(PDO $pdo,int $professionalId,int $patientId=0): void {
    if($patientId>0){$q=$pdo->prepare('SELECT 1 FROM profissional_pacientes WHERE profissional_id=? AND paciente_id=?');$q->execute([$professionalId,$patientId]);if($q->fetchColumn())return;}
    $q=$pdo->prepare('SELECT COUNT(*) FROM profissional_pacientes WHERE profissional_id=?');$q->execute([$professionalId]);$count=(int)$q->fetchColumn();
    $limit=professional_patient_limit($pdo,$professionalId);
    if($count >= $limit['limite']) {
        $msg=$limit['assinado'] ? 'O limite de pacientes do seu plano foi atingido.' : 'Você já possui 5 pacientes gratuitos. Escolha e pague um plano para cadastrar novos pacientes.';
        json_response(['success'=>false,'message'=>$msg,'limitePacientes'=>$limit['limite'],'assinaturaNecessaria'=>!$limit['assinado']],409);
    }
}
function professional_link_patient(PDO $pdo,array $data): never {
    $s=require_professional();$sus=required_string($data,'sus');
    $q=$pdo->prepare('SELECT id,nome FROM pacientes WHERE sus=?');$q->execute([$sus]);$p=$q->fetch();
    if(!$p)json_response(['success'=>false,'message'=>'Paciente não encontrado.'],404);
    enforce_professional_patient_limit($pdo,$s['id'],(int)$p['id']);
    $q=$pdo->prepare('INSERT IGNORE INTO profissional_pacientes (profissional_id,paciente_id,consentimento_em) VALUES (?,?,NOW())');$q->execute([$s['id'],$p['id']]);
    json_response(['success'=>true,'message'=>'Paciente vinculado ao seu consultório.','patient'=>$p]);
}

function generate_patient_code(): string {
    return 'PAC-'.date('ym').'-'.strtoupper(bin2hex(random_bytes(3)));
}
function professional_create_patient(PDO $pdo,array $data): never {
    $s=require_professional();
    enforce_professional_patient_limit($pdo,$s['id']);
    $nome=required_string($data,'nome');
    $telefone=trim((string)($data['telefone']??''));
    $email=trim((string)($data['email']??''));
    $sus=trim((string)($data['sus']??''));
    $cpf=normalize_cpf($data['cpf']??'');
    if($cpf!==''&&!valid_cpf($cpf)) json_response(['success'=>false,'message'=>'CPF inválido.'],422);
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) json_response(['success'=>false,'message'=>'E-mail inválido.'],422);
    if($sus!==''){
        $q=$pdo->prepare('SELECT id,nome FROM pacientes WHERE sus=?');$q->execute([$sus]);
        if($q->fetch()) json_response(['success'=>false,'message'=>'Já existe um paciente com este Cartão SUS. Use a opção Vincular paciente.'],409);
    } else $sus='CLI-'.strtoupper(bin2hex(random_bytes(6)));
    $codigo=generate_patient_code();
    $q=$pdo->prepare('INSERT INTO pacientes (codigo,nome,telefone,email,cpf,sus) VALUES (?,?,?,?,?,?)');
    $q->execute([$codigo,$nome,$telefone?:null,$email?:null,$cpf?:null,$sus]);
    $pid=(int)$pdo->lastInsertId();
    $q=$pdo->prepare('INSERT INTO profissional_pacientes (profissional_id,paciente_id,consentimento_em) VALUES (?,?,NOW())');
    $q->execute([$s['id'],$pid]);
    json_response(['success'=>true,'patient'=>['id'=>$pid,'codigo'=>$codigo,'nome'=>$nome,'telefone'=>$telefone,'email'=>$email,'cpf'=>$cpf,'sus'=>$sus]]);
}
function professional_patient_history(PDO $pdo): never {
    $s=require_professional();$pid=(int)($_GET['patient_id']??0);if(!$pid)json_response(['success'=>false,'message'=>'Paciente não informado.'],422);
    assert_prof_patient($pdo,$s['id'],$pid);
    $q=$pdo->prepare("SELECT p.id,p.codigo,p.nome,p.telefone,p.email,p.cpf,p.sus,p.endereco,p.data_nascimento AS nascimento,
        c.id AS consultaId,c.data_consulta AS data,c.horario,c.assunto,c.valor,c.forma_pagamento AS formaPagamento,c.tipo_cartao AS tipoCartao,c.parcelas,c.recibo_valor AS reciboValor,c.pagamento_status AS pagamentoStatus,c.pago_em AS pagoEm,c.status
        FROM pacientes p LEFT JOIN consultas_profissionais c ON c.paciente_id=p.id AND c.profissional_id=?
        WHERE p.id=? ORDER BY c.data_consulta DESC,c.horario DESC");
    $q->execute([$s['id'],$pid]);$rows=$q->fetchAll();
    json_response(['success'=>true,'patient'=>$rows[0]??null,'appointments'=>$rows]);
}
function professional_finance(PDO $pdo): never {
    $s=require_professional();
    $inicio=trim((string)($_GET['inicio']??''));$fim=trim((string)($_GET['fim']??''));$forma=trim((string)($_GET['forma_pagamento']??''));$busca=trim((string)($_GET['busca']??''));
    $sql="SELECT f.id,f.recebido_em AS data,f.valor,f.forma_pagamento AS formaPagamento,f.tipo_cartao AS tipoCartao,f.parcelas,
        f.consulta_id AS consultaId,p.codigo,p.nome AS paciente
        FROM pagamentos_pacientes f INNER JOIN pacientes p ON p.id=f.paciente_id WHERE f.profissional_id=?";
    $params=[$s['id']];
    if($inicio!==''){ $sql.=" AND DATE(f.recebido_em)>=?";$params[]=$inicio; }
    if($fim!==''){ $sql.=" AND DATE(f.recebido_em)<=?";$params[]=$fim; }
    if($forma!==''){ $sql.=" AND f.forma_pagamento=?";$params[]=$forma; }
    if($busca!==''){ $sql.=" AND (p.nome LIKE ? OR p.codigo LIKE ? OR f.consulta_id LIKE ?)";$like="%$busca%";array_push($params,$like,$like,$like); }
    $sql.=" ORDER BY f.recebido_em DESC,f.id DESC";
    $q=$pdo->prepare($sql);$q->execute($params);$rows=$q->fetchAll();
    $total=array_sum(array_map(fn($r)=>(float)$r['valor'],$rows));
    json_response(['success'=>true,'payments'=>$rows,'total'=>$total]);
}
function professional_register_payment(PDO $pdo,array $data): never {
    $s=require_professional();$pid=(int)($data['patient_id']??0);$consulta=trim((string)($data['consulta_id']??''));$valor=(float)($data['valor']??0);
    $forma=trim((string)($data['forma_pagamento']??''));$tipo=trim((string)($data['tipo_cartao']??''));$parcelas=(int)($data['parcelas']??1);
    if(!$pid||$valor<=0||!in_array($forma,['pix','dinheiro','cartao','boleto'],true))json_response(['success'=>false,'message'=>'Informe paciente, valor e forma de pagamento válida.'],422);
    assert_prof_patient($pdo,$s['id'],$pid);
    if($forma==='cartao'&&!in_array($tipo,['credito','debito'],true))json_response(['success'=>false,'message'=>'Selecione crédito ou débito.'],422);
    if($forma==='cartao' && $tipo==='debito') $parcelas=1;
    if($forma!=='cartao'){ $tipo=null;$parcelas=null; } if($consulta!==''){ $q=$pdo->prepare("SELECT id FROM pagamentos_pacientes WHERE profissional_id=? AND consulta_id=? LIMIT 1");$q->execute([$s['id'],$consulta]);if($q->fetch())json_response(['success'=>false,'message'=>'Esta consulta já possui um lançamento financeiro. Ele não pode ser alterado nem duplicado.'],409); }
    if($forma==='cartao' && ($parcelas<1||$parcelas>24))json_response(['success'=>false,'message'=>'Quantidade de parcelas inválida.'],422);
    $q=$pdo->prepare('INSERT INTO pagamentos_pacientes (profissional_id,paciente_id,consulta_id,valor,forma_pagamento,tipo_cartao,parcelas) VALUES (?,?,?,?,?,?,?)');
    $q->execute([$s['id'],$pid,$consulta?:null,$valor,$forma,$tipo,$parcelas?:null]);
    if($consulta!==''){
        $q=$pdo->prepare("UPDATE consultas_profissionais SET recibo_valor=?,forma_pagamento=?,tipo_cartao=?,parcelas=?,pagamento_status='pago',pago_em=NOW(),valor=? WHERE id=? AND profissional_id=? AND pagamento_status<>'pago'");
        $q->execute([$valor,$forma,$tipo,$parcelas?:null,$valor,$consulta,$s['id']]);
    }
    json_response(['success'=>true,'payment_id'=>(int)$pdo->lastInsertId(),'message'=>'Pagamento registrado. Este lançamento não pode ser editado.']);
}
function professional_receipt(PDO $pdo): never {
    $s=require_professional();$consulta=required_string(['id'=>$_GET['id']??''],'id');
    $q=$pdo->prepare("SELECT c.*,p.codigo,p.nome AS paciente,p.telefone AS pacienteTelefone,pr.nome AS clinicaNome,pr.cnpj,pr.endereco AS clinicaEndereco,pr.telefone AS clinicaTelefone
        FROM consultas_profissionais c INNER JOIN pacientes p ON p.id=c.paciente_id INNER JOIN profissionais pr ON pr.id=c.profissional_id
        WHERE c.id=? AND c.profissional_id=? LIMIT 1");$q->execute([$consulta,$s['id']]);$r=$q->fetch();
    if(!$r)json_response(['success'=>false,'message'=>'Consulta não encontrada.'],404);
    json_response(['success'=>true,'receipt'=>$r]);
}
function professional_patients(PDO $pdo): never { $s=require_professional();$q=$pdo->prepare('SELECT p.id,p.codigo,p.nome,p.telefone,p.email,p.cpf,p.sus,p.endereco,p.data_nascimento AS nascimento,p.condicoes_saude AS condicoesSaude,p.alergias,p.medicamentos,p.informacoes_adicionais AS informacoesAdicionais,pp.consentimento_em AS consentimentoEm FROM profissional_pacientes pp INNER JOIN pacientes p ON p.id=pp.paciente_id WHERE pp.profissional_id=? ORDER BY p.nome');$q->execute([$s['id']]);json_response(['success'=>true,'patients'=>$q->fetchAll()]);}
function assert_prof_patient(PDO $pdo,int $professionalId,int $patientId): void { $q=$pdo->prepare('SELECT 1 FROM profissional_pacientes WHERE profissional_id=? AND paciente_id=?');$q->execute([$professionalId,$patientId]);if(!$q->fetchColumn())json_response(['success'=>false,'message'=>'Paciente não está vinculado a este profissional.'],403);}
function professional_create_record(PDO $pdo,array $data): never { $s=require_professional();$patient=(int)($data['patient_id']??0);$content=required_string($data,'conteudo');assert_prof_patient($pdo,$s['id'],$patient);$q=$pdo->prepare('INSERT INTO prontuarios_profissionais (profissional_id,paciente_id,consulta_id,tipo,conteudo) VALUES (?,?,?,?,?)');$q->execute([$s['id'],$patient,trim((string)($data['consulta_id']??''))?:null,trim((string)($data['tipo']??'evolucao')),$content]);json_response(['success'=>true,'record_id'=>(int)$pdo->lastInsertId()]);}
function professional_update_patient(PDO $pdo,array $data): never { $s=require_professional();$patient=(int)($data['patient_id']??0);if(!$patient)json_response(['success'=>false,'message'=>'Paciente não informado.'],422);assert_prof_patient($pdo,$s['id'],$patient);$nome=required_string($data,'nome');$telefone=trim((string)($data['telefone']??''));$email=trim((string)($data['email']??''));$nascimento=trim((string)($data['data_nascimento']??''));$endereco=trim((string)($data['endereco']??''));$condicoes=trim((string)($data['condicoes_saude']??''));$alergias=trim((string)($data['alergias']??''));$medicamentos=trim((string)($data['medicamentos']??''));$adicionais=trim((string)($data['informacoes_adicionais']??''));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))json_response(['success'=>false,'message'=>'E-mail do paciente inválido.'],422);if($nascimento!==''&&!valid_date($nascimento))json_response(['success'=>false,'message'=>'Data de nascimento inválida.'],422);$q=$pdo->prepare('UPDATE pacientes SET nome=?,telefone=?,email=?,data_nascimento=?,endereco=?,condicoes_saude=?,alergias=?,medicamentos=?,informacoes_adicionais=? WHERE id=?');$q->execute([$nome,$telefone?:null,$email?:null,$nascimento?:null,$endereco?:null,$condicoes?:null,$alergias?:null,$medicamentos?:null,$adicionais?:null,$patient]);json_response(['success'=>true,'message'=>'Cadastro do paciente atualizado.']);}
function professional_upload_logo(PDO $pdo): never { $s=require_professional();if(empty($_FILES['logo'])||$_FILES['logo']['error']!==UPLOAD_ERR_OK)json_response(['success'=>false,'message'=>'Selecione uma imagem válida.'],422);$file=$_FILES['logo'];if($file['size']>5*1024*1024)json_response(['success'=>false,'message'=>'A logo deve ter no máximo 5 MB.'],422);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$allowed=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];if(!isset($allowed[$mime]))json_response(['success'=>false,'message'=>'Use PNG, JPG ou WEBP.'],422);$dir=__DIR__.'/uploads/marca';if(!is_dir($dir))mkdir($dir,0750,true);$name='prof-'.$s['id'].'-'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))json_response(['success'=>false,'message'=>'Não foi possível salvar a logo.'],500);$q=$pdo->prepare('UPDATE profissionais SET logo_arquivo=? WHERE id=?');$q->execute([$name,$s['id']]);json_response(['success'=>true,'arquivo'=>$name,'url'=>'uploads/marca/'.$name]);}

function professional_records(PDO $pdo): never { $s=require_professional();$patient=(int)($_GET['patient_id']??0);if(!$patient)json_response(['success'=>false,'message'=>'Paciente não informado.'],422);assert_prof_patient($pdo,$s['id'],$patient);$q=$pdo->prepare('SELECT id,consulta_id AS consultaId,tipo,conteudo,criado_em AS criadoEm FROM prontuarios_profissionais WHERE profissional_id=? AND paciente_id=? ORDER BY criado_em DESC');$q->execute([$s['id'],$patient]);json_response(['success'=>true,'records'=>$q->fetchAll()]);}
function professional_relationships(PDO $pdo): never {
    $s=require_professional();
    $q=$pdo->prepare("SELECT p.id,p.nome,p.cpf,p.telefone,p.email,MAX(r.enviado_em) AS ultimoContato,COUNT(r.id) AS totalContatos,MAX(c.data_consulta) AS ultimaConsulta FROM profissional_pacientes pp INNER JOIN pacientes p ON p.id=pp.paciente_id LEFT JOIN relacionamento_contatos r ON r.profissional_id=pp.profissional_id AND r.paciente_id=p.id LEFT JOIN consultas_profissionais c ON c.profissional_id=pp.profissional_id AND c.paciente_id=p.id WHERE pp.profissional_id=? GROUP BY p.id,p.nome,p.cpf,p.telefone,p.email ORDER BY p.nome");
    $q->execute([$s['id']]); json_response(['success'=>true,'patients'=>$q->fetchAll()]);
}
function professional_log_relationship(PDO $pdo,array $data): never {
    $s=require_professional(); $pid=(int)($data['patient_id']??0); $message=trim((string)($data['mensagem']??''));
    if(!$pid || $message==='') json_response(['success'=>false,'message'=>'Paciente e mensagem são obrigatórios.'],422); assert_prof_patient($pdo,$s['id'],$pid);
    $q=$pdo->prepare('INSERT INTO relacionamento_contatos (profissional_id,paciente_id,mensagem) VALUES (?,?,?)'); $q->execute([$s['id'],$pid,$message]);
    json_response(['success'=>true,'enviadoEm'=>date('Y-m-d H:i:s')]);
}
function professional_create_appointment(PDO $pdo,array $data): never { $s=require_professional();$patient=(int)($data['patient_id']??0);$date=required_string($data,'data_consulta');$time=required_string($data,'horario');if(!valid_date($date))json_response(['success'=>false,'message'=>'Data inválida.'],422);assert_prof_patient($pdo,$s['id'],$patient);$q=$pdo->prepare('SELECT id FROM consultas_profissionais WHERE profissional_id=? AND data_consulta=? AND horario=? AND status NOT IN (\'cancelada\',\'faltou\')');$q->execute([$s['id'],$date,$time]);if($q->fetch())json_response(['success'=>false,'message'=>'Este horário já está ocupado.'],409);$id='P'.date('YmdHis').bin2hex(random_bytes(3));$q=$pdo->prepare('INSERT INTO consultas_profissionais (id,profissional_id,paciente_id,data_consulta,horario,assunto,valor,status) VALUES (?,?,?,?,?,?,?,\'confirmada\')');$q->execute([$id,$s['id'],$patient,$date,$time,trim((string)($data['assunto']??''))?:null,$data['valor']??null]);criar_notificacoes_profissionais($pdo,(int)$s['id'],$patient,$id,$date,$time,'','');json_response(['success'=>true,'appointment_id'=>$id,'status'=>'confirmada']);}
function criar_notificacoes_profissionais(PDO $pdo,int $profissionalId,int $pacienteId,string $consultaId,string $date,string $time,string $nome='',string $clinica=''): void {
    $q=$pdo->prepare('SELECT nome,telefone,email FROM pacientes WHERE id=?');$q->execute([$pacienteId]);$p=$q->fetch()?:[];
    $nome=$nome?:($p['nome']??'Paciente');$clinica=$clinica?:'sua clínica';
    $when=DateTime::createFromFormat('Y-m-d H:i:s',$date.' '.$time.':00') ?: new DateTime($date.' '.$time);
    $confirm="Olá, {$nome}! Sua consulta na {$clinica} foi confirmada para {$when->format('d/m/Y')} às {$when->format('H:i')}.";
    $reminder=(clone $when)->modify('-1 day'); $rem="Lembrete: {$nome}, sua consulta na {$clinica} será amanhã, {$when->format('d/m/Y')} às {$when->format('H:i')}.";
    $q=$pdo->prepare('INSERT IGNORE INTO notificacoes_profissionais (profissional_id,paciente_id,consulta_id,tipo,mensagem,agendada_para) VALUES (?,?,?,?,?,?)');
    $q->execute([$profissionalId,$pacienteId,$consultaId,'confirmacao',$confirm,(new DateTime())->format('Y-m-d H:i:s')]);
    $q->execute([$profissionalId,$pacienteId,$consultaId,'lembrete',$rem,$reminder->format('Y-m-d H:i:s')]);
}
function professional_daily_report(PDO $pdo): never {
    $s=require_professional();$date=trim((string)($_GET['data']??date('Y-m-d')));if(!valid_date($date))json_response(['success'=>false,'message'=>'Data do relatório inválida.'],422);
    $q=$pdo->prepare('SELECT f.id,f.recebido_em AS data,f.valor,f.forma_pagamento AS formaPagamento,f.tipo_cartao AS tipoCartao,f.parcelas,f.consulta_id AS consultaId,p.codigo,p.nome AS paciente FROM pagamentos_pacientes f INNER JOIN pacientes p ON p.id=f.paciente_id WHERE f.profissional_id=? AND DATE(f.recebido_em)=? ORDER BY f.recebido_em');$q->execute([$s['id'],$date]);$rows=$q->fetchAll();$total=array_sum(array_map(fn($r)=>(float)$r['valor'],$rows));$formas=[];foreach($rows as $r){$formas[$r['formaPagamento']]=($formas[$r['formaPagamento']]??0)+(float)$r['valor'];}json_response(['success'=>true,'data'=>$date,'payments'=>$rows,'total'=>$total,'porForma'=>$formas]);
}
function professional_appointments(PDO $pdo): never { $s=require_professional();$q=$pdo->prepare('SELECT c.id,c.data_consulta AS data,c.horario,c.assunto,c.valor,c.forma_pagamento AS formaPagamento,c.pagamento_status AS pagamentoStatus,c.pago_em AS pagoEm,c.status,c.cancelamento_motivo AS cancelamentoMotivo,p.id AS patientId,p.nome AS paciente,p.sus,p.telefone,p.email,pr.nome AS clinicaNome,pr.cnpj AS clinicaCnpj,pr.endereco AS clinicaEndereco,pr.telefone AS clinicaTelefone FROM consultas_profissionais c INNER JOIN pacientes p ON p.id=c.paciente_id INNER JOIN profissionais pr ON pr.id=c.profissional_id WHERE c.profissional_id=? ORDER BY c.data_consulta,c.horario');$q->execute([$s['id']]);json_response(['success'=>true,'appointments'=>$q->fetchAll()]);}
function professional_update_appointment(PDO $pdo,array $data): never {
    $s=require_professional();$id=required_string($data,'id');
    $q=$pdo->prepare('SELECT id,data_consulta,horario,status,pagamento_status FROM consultas_profissionais WHERE id=? AND profissional_id=?');$q->execute([$id,$s['id']]);$old=$q->fetch();
    if(!$old)json_response(['success'=>false,'message'=>'Consulta não encontrada.'],404);
    $status=trim((string)($data['status']??$old['status']));$allowed=['solicitada','agendada','confirmada','atendida','cancelada','faltou'];
    if(!in_array($status,$allowed,true))json_response(['success'=>false,'message'=>'Status inválido.'],422);
    $date=trim((string)($data['data_consulta']??$old['data_consulta']));$time=trim((string)($data['horario']??$old['horario']));
    if(!valid_date($date)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/',$time))json_response(['success'=>false,'message'=>'Data ou horário inválido.'],422);
    if($status!=='cancelada'){ $busy=$pdo->prepare("SELECT id FROM consultas_profissionais WHERE profissional_id=? AND data_consulta=? AND horario=? AND status NOT IN ('cancelada','faltou') AND id<>?");$busy->execute([$s['id'],$date,$time,$id]);if($busy->fetch())json_response(['success'=>false,'message'=>'Este horário já está ocupado.'],409);}
    $cancel=trim((string)($data['cancelamento_motivo']??''));
    $sql="UPDATE consultas_profissionais SET data_consulta=?,horario=?,status=?,cancelamento_motivo=?,confirmada_em=CASE WHEN ?='confirmada' THEN COALESCE(confirmada_em,NOW()) ELSE confirmada_em END,atendida_em=CASE WHEN ?='atendida' THEN COALESCE(atendida_em,NOW()) ELSE atendida_em END WHERE id=? AND profissional_id=?";
    $pdo->prepare($sql)->execute([$date,$time,$status,$cancel,$status,$status,$id,$s['id']]);
    json_response(['success'=>true,'message'=>'Consulta atualizada. Dados financeiros salvos permanecem imutáveis.']);
}
function subscription_plans(PDO $pdo): never { $q=$pdo->query('SELECT id,codigo,nome,valor_mensal AS valorMensal,limite_pacientes AS limitePacientes FROM planos_assinatura WHERE ativo=1 ORDER BY valor_mensal');json_response(['success'=>true,'plans'=>$q->fetchAll()]);}
function professional_subscription(PDO $pdo): never { $s=require_professional();$q=$pdo->prepare('SELECT a.id,a.status,a.inicio,a.fim,p.nome AS plano,p.valor_mensal AS valorMensal,p.limite_pacientes AS limitePacientes FROM assinaturas_profissionais a INNER JOIN planos_assinatura p ON p.id=a.plano_id WHERE a.profissional_id=? ORDER BY a.id DESC LIMIT 1');$q->execute([$s['id']]);json_response(['success'=>true,'subscription'=>$q->fetch()?:null]);}
function admin_professional_payments(PDO $pdo): never { $s=require_admin();if($s['tipo']!=='desenvolvedor')json_response(['success'=>false,'message'=>'Somente o desenvolvedor pode administrar pagamentos.'],403);$q=$pdo->query('SELECT pg.id,pg.profissional_id,pr.nome AS profissional,pg.valor,pg.status,pg.metodo,pg.criado_em AS criadoEm,a.id AS assinaturaId,pl.nome AS plano FROM pagamentos_profissionais pg INNER JOIN profissionais pr ON pr.id=pg.profissional_id LEFT JOIN assinaturas_profissionais a ON a.id=pg.assinatura_id LEFT JOIN planos_assinatura pl ON pl.id=a.plano_id ORDER BY pg.id DESC');json_response(['success'=>true,'payments'=>$q->fetchAll()]);}
function admin_update_professional_payment(PDO $pdo,array $data): never { $s=require_admin();if($s['tipo']!=='desenvolvedor')json_response(['success'=>false,'message'=>'Somente o desenvolvedor pode aprovar pagamentos.'],403);$id=(int)($data['id']??0);$status=required_string($data,'status');if(!in_array($status,['aprovado','recusado','estornado','manual'],true))json_response(['success'=>false,'message'=>'Status de pagamento inválido.'],422);$pdo->beginTransaction();$q=$pdo->prepare('SELECT profissional_id,assinatura_id FROM pagamentos_profissionais WHERE id=? FOR UPDATE');$q->execute([$id]);$pay=$q->fetch();if(!$pay){$pdo->rollBack();json_response(['success'=>false,'message'=>'Pagamento não encontrado.'],404);}$q=$pdo->prepare('UPDATE pagamentos_profissionais SET status=?,pago_em=IF(? IN (\'aprovado\',\'manual\'),NOW(),NULL) WHERE id=?');$q->execute([$status,$status,$id]);if($pay['assinatura_id']){$subStatus=$status==='aprovado'||$status==='manual'?'ativa':($status==='estornado'?'cancelada':'inadimplente');$q=$pdo->prepare('UPDATE assinaturas_profissionais SET status=?,inicio=IF(?=\'ativa\' AND inicio IS NULL,CURDATE(),inicio),fim=IF(?=\'ativa\',DATE_ADD(CURDATE(),INTERVAL 1 MONTH),fim) WHERE id=?');$q->execute([$subStatus,$subStatus,$subStatus,$pay['assinatura_id']]);}$pdo->commit();audit_event($pdo,'pagamento_profissional_atualizado','pagamentos_profissionais',(string)$id,['status'=>$status]);json_response(['success'=>true,'status'=>$status]);}

function asaas_request(string $method,string $path,array $payload=[]): array {
    $key=getenv('ASAAS_API_KEY') ?: ''; if($key==='') throw new RuntimeException('Integração Asaas não configurada. Defina ASAAS_API_KEY no servidor.');
    $base=(getenv('ASAAS_ENV')==='production')?'https://api.asaas.com/api/v3':'https://api-sandbox.asaas.com/api/v3';
    $ch=curl_init($base.$path); $headers=['Content-Type: application/json','access_token: '.$key];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>20]);
    if($payload!==[]) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE));
    $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    if($raw===false||$err!=='') throw new RuntimeException('Não foi possível comunicar com o Asaas.');
    $data=json_decode($raw,true)?:[]; if($http<200||$http>=300) throw new RuntimeException((string)($data['errors'][0]['description']??'O Asaas recusou a cobrança.')); return $data;
}
function professional_start_subscription(PDO $pdo,array $data): never {
    $s=require_professional();$plan=(int)($data['plano_id']??0);$metodo=trim((string)($data['metodo']??''));
    if(!in_array($metodo,['pix','cartao','boleto'],true))json_response(['success'=>false,'message'=>'Escolha PIX, cartão ou boleto.'],422);
    $q=$pdo->prepare('SELECT id,valor_mensal,nome FROM planos_assinatura WHERE id=? AND ativo=1');$q->execute([$plan]);$p=$q->fetch();if(!$p)json_response(['success'=>false,'message'=>'Plano não encontrado.'],404);
    $pdo->beginTransaction();$q=$pdo->prepare('INSERT INTO assinaturas_profissionais (profissional_id,plano_id,status,gateway) VALUES (?,?,\'ativa\',\'demonstracao\')');$q->execute([$s['id'],$plan]);$sub=(int)$pdo->lastInsertId();
    $q=$pdo->prepare('UPDATE assinaturas_profissionais SET inicio=CURDATE(),fim=DATE_ADD(CURDATE(),INTERVAL 1 MONTH) WHERE id=?');$q->execute([$sub]);
    $q=$pdo->prepare('INSERT INTO pagamentos_profissionais (profissional_id,assinatura_id,valor,status,metodo,gateway,pago_em) VALUES (?,?,?,\'manual\',?,\'demonstracao\',NOW())');$q->execute([$s['id'],$sub,$p['valor_mensal'],$metodo]);$pdo->commit();
    json_response(['success'=>true,'message'=>'Demonstração ativada. Nenhuma cobrança real foi criada.','subscription_id'=>$sub,'status'=>'ativa','metodo'=>$metodo]);
}

function aplicar_status_pagamento_asaas(PDO $pdo,string $paymentId,string $status): void {
    $map=['PAYMENT_RECEIVED'=>'aprovado','PAYMENT_CONFIRMED'=>'aprovado','PAYMENT_RECEIVED_IN_CASH'=>'aprovado','PAYMENT_OVERDUE'=>'recusado','PAYMENT_REFUNDED'=>'estornado','PAYMENT_CHARGEBACK_REQUESTED'=>'estornado','PAYMENT_CHARGEBACK_DISPUTE'=>'estornado','RECEIVED'=>'aprovado','CONFIRMED'=>'aprovado','RECEIVED_IN_CASH'=>'aprovado','OVERDUE'=>'recusado','REFUNDED'=>'estornado'];$local=$map[$status]??null;if($local===null)return;
    $q=$pdo->prepare('SELECT id,assinatura_id FROM pagamentos_profissionais WHERE referencia_externa=? LIMIT 1');$q->execute([$paymentId]);$pay=$q->fetch();if(!$pay)return;
    $pdo->beginTransaction();$q=$pdo->prepare('UPDATE pagamentos_profissionais SET status=?,pago_em=IF(?=\'aprovado\',NOW(),NULL) WHERE id=?');$q->execute([$local,$local,$pay['id']]);
    if($pay['assinatura_id']){$sub=$local==='aprovado'?'ativa':($local==='estornado'?'cancelada':'inadimplente');$q=$pdo->prepare('UPDATE assinaturas_profissionais SET status=?,inicio=IF(?=\'ativa\' AND inicio IS NULL,CURDATE(),inicio),fim=IF(?=\'ativa\',DATE_ADD(CURDATE(),INTERVAL 1 MONTH),fim) WHERE id=?');$q->execute([$sub,$sub,$sub,$pay['assinatura_id']]);}$pdo->commit();
}
function asaas_webhook(PDO $pdo): never {
    $expected=getenv('ASAAS_WEBHOOK_TOKEN')?:'';$received=$_SERVER['HTTP_ASAAS_ACCESS_TOKEN']??'';if($expected===''||!hash_equals($expected,$received))json_response(['success'=>false,'message'=>'Webhook não autorizado.'],401);
    $payload=json_decode(file_get_contents('php://input'),true)?:[];$payment=$payload['payment']??[];$id=trim((string)($payment['id']??''));if($id!=='')aplicar_status_pagamento_asaas($pdo,$id,(string)($payload['event']??''));json_response(['success'=>true]);
}

function professional_cancel_subscription(PDO $pdo): never { $s=require_professional();$q=$pdo->prepare('UPDATE assinaturas_profissionais SET status=\'cancelada\',fim=CURDATE() WHERE profissional_id=? AND status IN (\'pendente\',\'ativa\')');$q->execute([$s['id']]);json_response(['success'=>true,'message'=>'Assinatura cancelada.']);}

function get_app_config(PDO $pdo): never { $row=$pdo->query('SELECT id,modo,nome_exibicao AS nomeExibicao,especialidade,registro_profissional AS registroProfissional,telefone,whatsapp,endereco,modalidade,valor_consulta AS valorConsulta,apresentacao,logo_arquivo AS logoArquivo,cor_primaria AS corPrimaria,cor_secundaria AS corSecundaria FROM configuracao_app WHERE id=1')->fetch(); json_response(['success'=>true,'config'=>$row ?: ['modo'=>'ubs','nomeExibicao'=>'Acessa+ Saúde']]); }
function update_app_config(PDO $pdo, array $data): never { $session=require_admin();if($session['tipo']!=='desenvolvedor')json_response(['success'=>false,'message'=>'Somente o desenvolvedor pode alterar o modo do sistema.'],403);$modo=required_string($data,'modo');if(!in_array($modo,['ubs','profissional'],true))json_response(['success'=>false,'message'=>'Modo inválido.'],422);$nome=required_string($data,'nome_exibicao');$campos=['especialidade','registro_profissional','telefone','whatsapp','endereco','modalidade','apresentacao','logo_arquivo','cor_primaria','cor_secundaria'];$vals=[];foreach($campos as $campo)$vals[]=trim((string)($data[$campo]??''))?:null;$valor=$data['valor_consulta']??null;$q=$pdo->prepare('UPDATE configuracao_app SET modo=?,nome_exibicao=?,especialidade=?,registro_profissional=?,telefone=?,whatsapp=?,endereco=?,modalidade=?,valor_consulta=?,apresentacao=?,logo_arquivo=?,cor_primaria=COALESCE(?,cor_primaria),cor_secundaria=COALESCE(?,cor_secundaria) WHERE id=1');$q->execute([$modo,$nome,$vals[0],$vals[1],$vals[2],$vals[3],$vals[4],$vals[5],$valor!==''?$valor:null,$vals[6],$vals[7],$vals[8],$vals[9]]);audit_event($pdo,'configuracao_modo_atualizada','configuracao_app','1',['modo'=>$modo]);get_app_config($pdo);}

function save_patient(PDO $pdo, array $data): never
{
    $nome=required_string($data,'nome'); $telefone=required_string($data,'telefone'); $sus=required_string($data,'sus'); $ubsId=required_string($data,'ubs_id');
    if (strlen($nome) < 3 || strlen($nome) > 150) json_response(['success'=>false,'message'=>'Informe um nome completo válido.'],422);
    if (!preg_match('/^[0-9()+\s-]{8,30}$/', $telefone)) json_response(['success'=>false,'message'=>'Informe um celular válido.'],422);
    if (strlen($sus) > 20) json_response(['success'=>false,'message'=>'Cartão SUS inválido.'],422);
    if (strlen(preg_replace('/\D+/','',$sus))<8) json_response(['success'=>false,'message'=>'Cartão SUS inválido.'],422);
    $q=$pdo->prepare('SELECT id FROM ubs WHERE id=?'); $q->execute([$ubsId]); if(!$q->fetch()) json_response(['success'=>false,'message'=>'UBS de referência não encontrada.'],422);
    $codigo='PAC-'.date('ym').'-'.strtoupper(substr(hash('sha256',$sus),0,6)); $q=$pdo->prepare('INSERT INTO pacientes (codigo,nome,telefone,sus,ubs_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=VALUES(nome),telefone=VALUES(telefone),ubs_id=COALESCE(VALUES(ubs_id),ubs_id)'); $q->execute([$codigo,$nome,$telefone,$sus,$ubsId?:null]);
    $q=$pdo->prepare('SELECT id,codigo,nome,telefone,sus,ubs_id AS ubsId,endereco,data_nascimento AS dataNascimento,condicoes_saude AS condicoesSaude,alergias,medicamentos,informacoes_adicionais AS informacoesAdicionais FROM pacientes WHERE sus=?'); $q->execute([$sus]); json_response(['success'=>true,'patient'=>$q->fetch()]);
}

function update_patient_profile(PDO $pdo, array $data): never
{
    $sus = required_string($data, 'sus');
    $nome = required_string($data, 'nome');
    $telefone = required_string($data, 'telefone');
    $dataNascimento = trim((string)($data['data_nascimento'] ?? ''));
    $endereco = trim((string)($data['endereco'] ?? ''));
    $condicoes = trim((string)($data['condicoes_saude'] ?? ''));
    $alergias = trim((string)($data['alergias'] ?? ''));
    $medicamentos = trim((string)($data['medicamentos'] ?? ''));
    $informacoes = trim((string)($data['informacoes_adicionais'] ?? ''));

    foreach (['nome'=>$nome,'telefone'=>$telefone,'endereco'=>$endereco,'condicoes_saude'=>$condicoes,'alergias'=>$alergias,'medicamentos'=>$medicamentos,'informacoes_adicionais'=>$informacoes] as $campo=>$valor) {
        if (strlen($valor) > 2000) json_response(['success'=>false,'message'=>'O campo '.$campo.' excede o limite permitido.'],422);
    }
    if (strlen($nome) < 3 || !preg_match('/^[0-9()+\s-]{8,30}$/', $telefone)) json_response(['success'=>false,'message'=>'Nome ou celular inválido.'],422);

    if ($dataNascimento !== '' && !valid_date($dataNascimento)) {
        json_response(['success' => false, 'message' => 'Data de nascimento inválida.'], 422);
    }
    if ($dataNascimento !== '' && new DateTime($dataNascimento) > new DateTime('today')) json_response(['success'=>false,'message'=>'A data de nascimento não pode ser futura.'],422);
    $stmt = $pdo->prepare(
        'UPDATE pacientes
         SET nome = ?, telefone = ?, endereco = ?, data_nascimento = ?,
             condicoes_saude = ?, alergias = ?, medicamentos = ?, informacoes_adicionais = ?
         WHERE sus = ?'
    );
    $stmt->execute([$nome, $telefone, $endereco ?: null, $dataNascimento ?: null, $condicoes ?: null, $alergias ?: null, $medicamentos ?: null, $informacoes ?: null, $sus]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM pacientes WHERE sus = ?');
        $check->execute([$sus]);
        if (!$check->fetch()) {
            json_response(['success' => false, 'message' => 'Paciente não encontrado.'], 404);
        }
    }
    $q=$pdo->prepare('SELECT id,nome,telefone,sus,ubs_id AS ubsId,endereco,data_nascimento AS dataNascimento,condicoes_saude AS condicoesSaude,alergias,medicamentos,informacoes_adicionais AS informacoesAdicionais FROM pacientes WHERE sus=?');
    $q->execute([$sus]);
    json_response(['success' => true, 'patient' => $q->fetch()]);
}

function patient_id_by_sus(PDO $pdo, string $sus): ?int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM pacientes WHERE sus = ?'
    );

    $stmt->execute([$sus]);

    $id = $stmt->fetchColumn();

    return $id === false ? null : (int)$id;
}

function get_patient_appointments(PDO $pdo, string $sus): never
{
    if ($sus === '') {
        json_response([
            'success' => false,
            'message' => 'Cartão SUS não informado.'
        ], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT
            c.id,
            c.ubs_id AS ubsId,
            u.nome AS ubsNome,
            p.nome,
            p.telefone,
            p.sus,
            c.especialidade,
            c.assunto,
            DATE_FORMAT(c.data_consulta, "%Y-%m-%d") AS data,
            CASE WHEN c.horario = \'00:00:00\' THEN NULL ELSE TIME_FORMAT(c.horario, \'%H:%i\') END AS horario,
            c.fila,
            c.status,
            c.cancelamento_motivo AS cancelamentoMotivo,
            c.lembrete,
            c.notificado,
            c.criada_em AS criadaEm
         FROM consultas c
         INNER JOIN pacientes p ON p.id = c.paciente_id
         INNER JOIN ubs u ON u.id = c.ubs_id
         WHERE p.sus = ?
         ORDER BY c.data_consulta, c.horario'
    );

    $stmt->execute([$sus]);

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['lembrete'] = (bool)$row['lembrete'];
        $row['notificado'] = (bool)$row['notificado'];
    }

    json_response([
        'success' => true,
        'appointments' => $rows
    ]);
}

function join_waitlist(PDO $pdo, array $data): never
{
    $ubsId=required_string($data,'ubs_id'); $sus=required_string($data,'sus'); $especialidade=required_string($data,'especialidade'); $date=required_string($data,'data');
    if (!valid_date($date)) json_response(['success'=>false,'message'=>'Data inválida.'],422);
    $patientId=patient_id_by_sus($pdo,$sus); if($patientId===null) json_response(['success'=>false,'message'=>'Paciente não cadastrado.'],422);
    $q=$pdo->prepare('SELECT id FROM ubs WHERE id=?');$q->execute([$ubsId]);if(!$q->fetch())json_response(['success'=>false,'message'=>'UBS não encontrada.'],404);
    $q=$pdo->prepare('INSERT IGNORE INTO lista_espera (paciente_id,ubs_id,especialidade,data_consulta) VALUES (?,?,?,?)');$q->execute([$patientId,$ubsId,$especialidade,$date]);
    json_response(['success'=>true,'message'=>'Você entrou na lista de espera desta data.']);
}

function get_occupied_slots(PDO $pdo,string $ubsId,string $especialidade,string $date): never
{
 if($ubsId===''||$especialidade===''||!valid_date($date)) json_response(['success'=>false,'message'=>'Dados de agenda inválidos.'],422);
 $q=$pdo->prepare('SELECT COUNT(*) FROM consultas WHERE ubs_id=? AND especialidade=? AND data_consulta=? AND status="agendado"'); $q->execute([$ubsId,$especialidade,$date]); $ocupadas=(int)$q->fetchColumn();
 json_response(['success'=>true,'ocupadas'=>$ocupadas,'vagas'=>max(0,12-$ocupadas),'proximaFila'=>$ocupadas+1,'limite'=>12]);
}

function book_appointment(PDO $pdo,array $data): never
{
 $ubsId=required_string($data,'ubs_id'); $sus=required_string($data,'sus'); $especialidade=required_string($data,'especialidade'); $date=required_string($data,'data'); $assunto=trim((string)($data['assunto'] ?? ''));
 if(!valid_date($date)) json_response(['success'=>false,'message'=>'Data inválida.'],422); if(!is_business_day($date)) json_response(['success'=>false,'message'=>'A UBS não realiza agendamentos aos finais de semana ou nos feriados fixos.'],422); if(new DateTime($date)<new DateTime('today')) json_response(['success'=>false,'message'=>'Não é possível agendar uma data que já passou.'],422);
 $ubs=get_ubs($pdo,$ubsId,false); $patientId=patient_id_by_sus($pdo,$sus); if($patientId===null) json_response(['success'=>false,'message'=>'Paciente não cadastrado.'],422); if(!in_array($especialidade,$ubs['especialidades'],true)) json_response(['success'=>false,'message'=>'A especialidade selecionada não está disponível nesta UBS.'],422);
 $pdo->beginTransaction(); try { $lock=$pdo->prepare('SELECT id FROM ubs WHERE id=? FOR UPDATE'); $lock->execute([$ubsId]); $q=$pdo->prepare('SELECT COUNT(*) FROM consultas WHERE ubs_id=? AND especialidade=? AND data_consulta=? AND status="agendado"'); $q->execute([$ubsId,$especialidade,$date]); $ocupadas=(int)$q->fetchColumn(); if($ocupadas>=12){$pdo->rollBack();json_response(['success'=>false,'message'=>'As 12 vagas desta especialidade já foram preenchidas para este dia.'],409);} $q=$pdo->prepare('SELECT id FROM consultas WHERE paciente_id=? AND ubs_id=? AND especialidade=? AND data_consulta=? AND status="agendado" FOR UPDATE'); $q->execute([$patientId,$ubsId,$especialidade,$date]); if($q->fetch()){$pdo->rollBack();json_response(['success'=>false,'message'=>'Você já possui uma consulta para esta especialidade nesta data.'],409);} $id='CONS-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3))); $fila=$ocupadas+1; $q=$pdo->prepare('INSERT INTO consultas (id,ubs_id,paciente_id,especialidade,assunto,data_consulta,horario,fila,status) VALUES (?,?,?,?,?,?,"00:00:00",?,"agendado")'); $q->execute([$id,$ubsId,$patientId,$especialidade,$assunto ?: null,$date,$fila]); enqueue_reminder($pdo,$patientId,$id,$date); $pdo->commit(); json_response(['success'=>true,'appointment'=>appointment_by_id($pdo,$id)]); } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function appointment_by_id(PDO $pdo, string $id): array
{
    $stmt = $pdo->prepare(
        'SELECT
            c.id,
            c.ubs_id AS ubsId,
            u.nome AS ubsNome,
            p.nome,
            p.telefone,
            p.sus,
            c.especialidade,
            c.assunto,
            DATE_FORMAT(c.data_consulta, "%Y-%m-%d") AS data,
            CASE WHEN c.horario = \'00:00:00\' THEN NULL ELSE TIME_FORMAT(c.horario, \'%H:%i\') END AS horario,
            c.fila,
            c.status,
            c.cancelamento_motivo AS cancelamentoMotivo,
            c.lembrete,
            c.notificado,
            c.criada_em AS criadaEm
         FROM consultas c
         INNER JOIN pacientes p ON p.id = c.paciente_id
         INNER JOIN ubs u ON u.id = c.ubs_id
         WHERE c.id = ?'
    );

    $stmt->execute([$id]);

    $row = $stmt->fetch();

    if (!$row) {
        json_response([
            'success' => false,
            'message' => 'Consulta não encontrada.'
        ], 404);
    }

    $row['lembrete'] = (bool)$row['lembrete'];
    $row['notificado'] = (bool)$row['notificado'];

    return $row;
}

function cancel_appointment(PDO $pdo, array $data): never
{
    $id = required_string($data, 'id');
    $motivo = trim((string)($data['motivo'] ?? ''));
    if (strlen($motivo) > 255) json_response(['success'=>false,'message'=>'O motivo deve ter no máximo 255 caracteres.'],422);
    $session = admin_session();

    $stmt = $pdo->prepare(
        'SELECT c.id, c.ubs_id, p.sus, c.status
         FROM consultas c
         INNER JOIN pacientes p ON p.id = c.paciente_id
         WHERE c.id = ?'
    );

    $stmt->execute([$id]);

    $appointment = $stmt->fetch();

    if (!$appointment) {
        json_response([
            'success' => false,
            'message' => 'Consulta não encontrada.'
        ], 404);
    }

    if (!$session) {

        $sus = trim((string)($data['sus'] ?? ''));

        if (
            $sus === '' ||
            !hash_equals(
                (string)$appointment['sus'],
                $sus
            )
        ) {
            json_response([
                'success' => false,
                'message' => 'Você não tem permissão para cancelar esta consulta.'
            ], 403);
        }

    } else {

        if (
            $session['tipo'] !== 'desenvolvedor' &&
            $session['ubs_id'] !== $appointment['ubs_id']
        ) {
            json_response([
                'success' => false,
                'message' => 'Você não tem permissão para cancelar esta consulta.'
            ], 403);
        }
    }

    if ($appointment['status'] === 'cancelado') {
        json_response([
            'success' => true,
            'message' => 'Consulta já estava cancelada.'
        ]);
    }

    $stmt = $pdo->prepare('UPDATE consultas SET status = "cancelado", cancelamento_motivo = ? WHERE id = ?');
    $stmt->execute([$motivo ?: null, $id]);
    audit_event($pdo, 'consulta_cancelada', 'consultas', $id, ['motivo'=>$motivo]);
    $pdo->prepare('UPDATE notificacoes SET status = "erro" WHERE consulta_id = ? AND status = "pendente"')->execute([$id]);

    json_response([
        'success' => true
    ]);
}

function set_reminder(PDO $pdo, array $data): never
{
    $id = required_string($data, 'id');
    $sus = required_string($data, 'sus');

    $stmt = $pdo->prepare(
        'UPDATE consultas c
         INNER JOIN pacientes p ON p.id = c.paciente_id
         SET c.lembrete = 1,
             c.notificado = 0
         WHERE c.id = ?
           AND p.sus = ?
           AND c.status = "agendado"'
    );

    $stmt->execute([
        $id,
        $sus
    ]);

    if ($stmt->rowCount() === 0) {
        json_response([
            'success' => false,
            'message' => 'Não foi possível ativar o lembrete.'
        ], 403);
    }

    json_response([
        'success' => true
    ]);
}


function get_patient_exams(PDO $pdo, string $sus): never
{
    if ($sus === '') {
        json_response(['success' => false, 'message' => 'Cartão SUS não informado.'], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT e.id, e.nome_exame AS nome, DATE_FORMAT(e.data_exame, "%Y-%m-%d") AS data,
                e.resultado, e.observacoes, e.anexo_nome AS anexoNome,
                (e.anexo_arquivo IS NOT NULL) AS anexo, u.nome AS ubsNome
         FROM exames_resultados e
         INNER JOIN pacientes p ON p.id = e.paciente_id
         INNER JOIN ubs u ON u.id = e.ubs_id
         WHERE p.sus = ?
         ORDER BY e.data_exame DESC, e.id DESC'
    );
    $stmt->execute([$sus]);

    json_response(['success' => true, 'exams' => $stmt->fetchAll()]);
}

function admin_update_appointment_status(PDO $pdo, array $data): never
{
    $id = required_string($data, 'id');
    $status = required_string($data, 'status');

    if (!in_array($status, ['agendado', 'atendido', 'faltou', 'cancelado'], true)) {
        json_response(['success' => false, 'message' => 'Status inválido.'], 422);
    }

    $stmt = $pdo->prepare('SELECT ubs_id FROM consultas WHERE id = ?');
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();
    if (!$appointment) {
        json_response(['success' => false, 'message' => 'Consulta não encontrada.'], 404);
    }

    authorize_ubs($appointment['ubs_id']);

    $stmt = $pdo->prepare('UPDATE consultas SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
    audit_event($pdo, 'consulta_status_atualizado', 'consultas', $id, ['status'=>$status]);

    json_response(['success' => true]);
}

function admin_save_exam(PDO $pdo, array $data): never
{
    $ubsId = required_string($data, 'ubs_id');
    authorize_ubs($ubsId);

    $sus = required_string($data, 'sus');
    $nome = required_string($data, 'nome');
    $dataExame = required_string($data, 'data_exame');
    $resultado = required_string($data, 'resultado');
    $observacoes = trim((string)($data['observacoes'] ?? ''));

    if (!valid_date($dataExame)) {
        json_response(['success' => false, 'message' => 'Data do exame inválida.'], 422);
    }

    $patientId = patient_id_by_sus($pdo, $sus);
    if ($patientId === null) {
        json_response(['success' => false, 'message' => 'Paciente não encontrado. Verifique o Cartão SUS.'], 404);
    }

    $owner = $pdo->prepare('SELECT ubs_id FROM pacientes WHERE id = ?');
    $owner->execute([$patientId]);
    $patientUBS = $owner->fetchColumn();
    if ($patientUBS !== null && $patientUBS !== $ubsId) {
        json_response(['success' => false, 'message' => 'Este paciente está vinculado a outra UBS.'], 403);
    }

    $uploadDir = __DIR__ . '/uploads/exames';
    $anexoNome = null;
    $anexoMime = null;
    $anexoArquivo = null;
    $arquivoMovido = false;
    $upload = $_FILES['anexo'] ?? null;

    if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            json_response(['success' => false, 'message' => 'Não foi possível receber o arquivo anexado.'], 422);
        }
        if (($upload['size'] ?? 0) > 10 * 1024 * 1024) {
            json_response(['success' => false, 'message' => 'O anexo deve ter no máximo 10 MB.'], 422);
        }
        if (!is_uploaded_file($upload['tmp_name'] ?? '')) {
            json_response(['success' => false, 'message' => 'Arquivo anexado inválido.'], 422);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $anexoMime = $finfo->file($upload['tmp_name']) ?: '';
        $extensoes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png'
        ];
        if (!isset($extensoes[$anexoMime])) {
            json_response(['success' => false, 'message' => 'Formato não permitido. Envie apenas PDF, JPG ou PNG.'], 422);
        }
        $anexoNome = trim((string)($upload['name'] ?? 'resultado.' . $extensoes[$anexoMime]));
        $anexoNome = preg_replace('/[^A-Za-z0-9._ -]/u', '', $anexoNome) ?: 'resultado.' . $extensoes[$anexoMime];
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Não foi possível preparar a pasta de anexos.');
        }
        $anexoArquivo = 'exam_' . bin2hex(random_bytes(16)) . '.' . $extensoes[$anexoMime];
        if (!move_uploaded_file($upload['tmp_name'], $uploadDir . '/' . $anexoArquivo)) {
            throw new RuntimeException('Não foi possível salvar o anexo.');
        }
        $arquivoMovido = true;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO exames_resultados
            (paciente_id, ubs_id, nome_exame, data_exame, resultado, observacoes, anexo_nome, anexo_mime, anexo_arquivo, enviado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    try {
        $session = admin_session();
        $stmt->execute([$patientId, $ubsId, $nome, $dataExame, $resultado, $observacoes ?: null, $anexoNome, $anexoMime, $anexoArquivo, $session['id'] ?? null]);
    } catch (Throwable $e) {
        if ($arquivoMovido && $anexoArquivo) {
            @unlink($uploadDir . '/' . $anexoArquivo);
        }
        throw $e;
    }

    $examId = (int)$pdo->lastInsertId();
    audit_event($pdo, 'exame_cadastrado', 'exames_resultados', (string)$examId, ['ubs_id'=>$ubsId]);
    json_response(['success' => true, 'exam_id' => $examId]);
}

function download_exam(PDO $pdo): never
{
    $id = required_string($_GET, 'id');
    $stmt = $pdo->prepare(
        'SELECT e.anexo_nome, e.anexo_mime, e.anexo_arquivo, e.ubs_id, p.sus
         FROM exames_resultados e
         INNER JOIN pacientes p ON p.id = e.paciente_id
         WHERE e.id = ?'
    );
    $stmt->execute([(int)$id]);
    $exam = $stmt->fetch();
    if (!$exam || !$exam['anexo_arquivo']) {
        json_response(['success' => false, 'message' => 'Anexo não encontrado.'], 404);
    }

    $session = admin_session();
    if (!$session) {
        $sus = trim((string)($_GET['sus'] ?? ''));
        if ($sus === '' || !hash_equals((string)$exam['sus'], $sus)) {
            json_response(['success' => false, 'message' => 'Você não tem permissão para abrir este anexo.'], 403);
        }
    } elseif ($session['tipo'] !== 'desenvolvedor' && $session['ubs_id'] !== $exam['ubs_id']) {
        json_response(['success' => false, 'message' => 'Você não tem permissão para abrir este anexo.'], 403);
    }

    $path = __DIR__ . '/uploads/exames/' . basename((string)$exam['anexo_arquivo']);
    if (!is_file($path)) {
        json_response(['success' => false, 'message' => 'Arquivo do anexo não encontrado no servidor.'], 404);
    }

    header('Content-Type: ' . $exam['anexo_mime']);
    header('Content-Disposition: inline; filename="' . addcslashes((string)$exam['anexo_nome'], "\\\"") . '"');
    header('Content-Length: ' . (string)filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function enqueue_reminder(PDO $pdo,int $patientId,string $appointmentId,string $date): void { $q=$pdo->prepare('INSERT INTO notificacoes (paciente_id,consulta_id,canal,tipo,mensagem,agendada_para) VALUES (?,? ,"sms","lembrete_consulta",?,?)'); $q->execute([$patientId,$appointmentId,'Lembrete: consulta em '.date('d/m/Y',strtotime($date)),date('Y-m-d H:i:s',strtotime($date.' 08:00:00 -1 day'))]); }
function support_message(PDO $pdo,array $data): never {
    $nome=required_string($data,'nome');$telefone=required_string($data,'telefone');$mensagem=required_string($data,'mensagem');
    if(strlen($mensagem)>3000)json_response(['success'=>false,'message'=>'A mensagem deve ter no máximo 3000 caracteres.'],422);
    $patientId=!empty($data['sus'])?patient_id_by_sus($pdo,trim((string)$data['sus'])):null;
    $protocolo='SUP-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
    $token=bin2hex(random_bytes(32));
    $q=$pdo->prepare('INSERT INTO suporte_mensagens (protocolo,acesso_token,paciente_id,nome,telefone,mensagem) VALUES (?,?,?,?,?,?)');
    $q->execute([$protocolo,$token,$patientId,$nome,$telefone,$mensagem]);
    json_response(['success'=>true,'protocolo'=>$protocolo,'acesso_token'=>$token,'message'=>'Solicitação enviada. Guarde o protocolo e a chave de acesso para consultar a resposta.']);
}
function get_support(PDO $pdo, string $sus): never {
    $protocolo=trim((string)($_GET['protocolo']??''));$token=trim((string)($_GET['token']??''));
    if($protocolo!==''&&$token!==''){
        $q=$pdo->prepare('SELECT protocolo,mensagem,resposta,status,criado_em AS criadoEm,atualizado_em AS atualizadoEm FROM suporte_mensagens WHERE protocolo=? AND acesso_token=? LIMIT 1');
        $q->execute([$protocolo,$token]);$rows=$q->fetchAll();json_response(['success'=>true,'messages'=>$rows]);
    }
    // Sem a chave secreta, não retorna protocolos nem respostas.
    json_response(['success'=>true,'messages'=>[]]);
}
function admin_support_messages(PDO $pdo): never
{
    $session = require_admin();
    if ($session['tipo'] !== 'desenvolvedor') {
        json_response(['success' => false, 'message' => 'Somente o desenvolvedor pode consultar as mensagens de suporte.'], 403);
    }
    $status = trim((string)($_GET['status'] ?? ''));
    $sql = 'SELECT id, protocolo, nome, telefone, mensagem, resposta, status, criado_em AS criadoEm, atualizado_em AS atualizadoEm FROM suporte_mensagens';
    $params = [];
    if (in_array($status, ['aberto','em_atendimento','resolvido'], true)) { $sql .= ' WHERE status = ?'; $params[] = $status; }
    $sql .= ' ORDER BY criado_em DESC, id DESC';
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    json_response(['success' => true, 'messages' => $stmt->fetchAll()]);
}

function admin_update_support(PDO $pdo, array $data): never
{
    $session = require_admin();
    if ($session['tipo'] !== 'desenvolvedor') {
        json_response(['success' => false, 'message' => 'Somente o desenvolvedor pode atualizar mensagens de suporte.'], 403);
    }
    $id = required_string($data, 'id');
    $status = required_string($data, 'status');
    $resposta = trim((string)($data['resposta'] ?? ''));
    if (!in_array($status, ['aberto', 'em_atendimento', 'resolvido'], true)) {
        json_response(['success' => false, 'message' => 'Status de suporte inválido.'], 422);
    }
    $stmt = $pdo->prepare('UPDATE suporte_mensagens SET status = ?, resposta = ? WHERE id = ?');
    $stmt->execute([$status, $resposta ?: null, (int)$id]);
    audit_event($pdo, 'suporte_atualizado', 'suporte_mensagens', $id, ['status' => $status]);
    json_response(['success' => true]);
}

function admin_audit(PDO $pdo): never
{
    $session = require_admin();
    if ($session['tipo'] !== 'desenvolvedor') json_response(['success'=>false,'message'=>'Somente o desenvolvedor pode consultar a auditoria.'],403);
    $limit = min(200, max(10, (int)($_GET['limit'] ?? 100)));
    $stmt = $pdo->query('SELECT id, tipo_usuario AS tipoUsuario, acao, entidade, entidade_id AS entidadeId, detalhes, ip, criado_em AS criadoEm FROM auditoria ORDER BY id DESC LIMIT '.$limit);
    json_response(['success'=>true,'events'=>$stmt->fetchAll()]);
}

function get_patient_notifications(PDO $pdo, string $sus): never
{
    $id = patient_id_by_sus($pdo, $sus);
    if ($id === null) json_response(['success'=>true,'notifications'=>[]]);
    $stmt = $pdo->prepare('SELECT id, tipo, mensagem, status, agendada_para AS agendadaPara FROM notificacoes WHERE paciente_id = ? ORDER BY agendada_para DESC LIMIT 30');
    $stmt->execute([$id]);
    json_response(['success'=>true,'notifications'=>$stmt->fetchAll()]);
}
function create_ubs(PDO $pdo,array $data): never { $session=require_admin(); if($session['tipo']!=='desenvolvedor')json_response(['success'=>false,'message'=>'Apenas o desenvolvedor pode cadastrar novas UBS.'],403); $id=required_string($data,'id');$nome=required_string($data,'nome');$endereco=required_string($data,'endereco');$telefone=required_string($data,'telefone');$horario=required_string($data,'horario');$usuario=required_string($data,'usuario');$senha=required_string($data,'senha');$esp=clean_list($data['especialidades']??[]);if(!$esp)json_response(['success'=>false,'message'=>'Cadastre pelo menos uma especialidade.'],422);if(!preg_match('/^[A-Za-z0-9_-]{2,20}$/',$id))json_response(['success'=>false,'message'=>'Identificador inválido.'],422);$q=$pdo->prepare('SELECT id FROM ubs WHERE id=? OR usuario=? UNION SELECT ubs_id FROM administradores WHERE usuario=?');$q->execute([$id,$usuario,$usuario]);if($q->fetch())json_response(['success'=>false,'message'=>'Identificador ou usuário já utilizado.'],409);$pdo->beginTransaction();try{$pdo->prepare('INSERT INTO ubs(id,nome,endereco,telefone,horario,usuario) VALUES(?,?,?,?,?,?)')->execute([$id,$nome,$endereco,$telefone,$horario,$usuario]);replace_list($pdo,'ubs_especialidades',$id,$esp);$pdo->prepare('INSERT INTO administradores(usuario,senha_hash,tipo,ubs_id) VALUES(?,?,"ubs",?)')->execute([$usuario,password_hash($senha,PASSWORD_DEFAULT),$id]);$pdo->commit(); audit_event($pdo, 'ubs_criada', 'ubs', $id, ['nome'=>$nome]); json_response(['success'=>true,'ubs'=>get_ubs($pdo,$id,true)]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

function admin_exams(PDO $pdo): never
{
    $ubsId = trim((string)($_GET['ubs_id'] ?? ''));
    authorize_ubs($ubsId);

    $stmt = $pdo->prepare(
        'SELECT e.id, e.nome_exame AS nome, DATE_FORMAT(e.data_exame, "%Y-%m-%d") AS data,
                e.resultado, e.observacoes, e.anexo_nome AS anexoNome,
                (e.anexo_arquivo IS NOT NULL) AS anexo, p.nome AS paciente, p.sus
         FROM exames_resultados e
         INNER JOIN pacientes p ON p.id = e.paciente_id
         WHERE e.ubs_id = ?
         ORDER BY e.data_exame DESC, e.id DESC'
    );
    $stmt->execute([$ubsId]);

    json_response(['success' => true, 'exams' => $stmt->fetchAll()]);
}

function login_admin(PDO $pdo, array $data): never
{
    $usuario = required_string($data, 'usuario');
    $senha = (string)($data['senha'] ?? '');

    if ($senha === '') {
        json_response([
            'success' => false,
            'message' => 'Informe a senha.'
        ], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT id, usuario, senha_hash, tipo, ubs_id
         FROM administradores
         WHERE usuario = ?'
    );

    $stmt->execute([$usuario]);

    $admin = $stmt->fetch();

    if (
        !$admin ||
        !password_verify(
            $senha,
            $admin['senha_hash']
        )
    ) {
        json_response([
            'success' => false,
            'message' => 'Usuário ou senha inválidos.'
        ], 401);
    }

    session_regenerate_id(true);

    $_SESSION['admin'] = [
        'id' => (int)$admin['id'],
        'tipo' => $admin['tipo'],
        'ubs_id' => $admin['ubs_id']
    ];

    audit_event($pdo, 'login_admin', 'administradores', (string)$admin['id'], ['usuario' => $usuario]);

    json_response([
        'success' => true,
        'session' => [
            'tipo' => $admin['tipo'],
            'ubsId' => $admin['ubs_id']
        ]
    ]);
}

function audit_event(PDO $pdo, string $acao, ?string $entidade = null, ?string $entidadeId = null, array $detalhes = []): void
{
    try {
        $session = admin_session();
        $stmt = $pdo->prepare('INSERT INTO auditoria (admin_id,tipo_usuario,acao,entidade,entidade_id,detalhes,ip) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$session['id'] ?? null, $session['tipo'] ?? null, $acao, $entidade, $entidadeId, $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $ignored) {
        error_log('Falha ao gravar auditoria: '.$ignored->getMessage());
    }
}

function update_ubs(PDO $pdo, array $data): never
{
    $ubsId = required_string($data, 'ubs_id');

    authorize_ubs($ubsId);

    $nome = required_string($data, 'nome');
    $endereco = required_string($data, 'endereco');
    $telefone = required_string($data, 'telefone');
    $horario = required_string($data, 'horario');
    $usuario = required_string($data, 'usuario');

    $especialidades = clean_list($data['especialidades'] ?? []);
    $servicos = clean_list($data['servicos'] ?? []);
    $campanhas = clean_list($data['campanhas'] ?? []);
    $documentos = clean_list($data['documentos'] ?? []);
    $novaSenha = trim((string)($data['senha'] ?? ''));

    if (!$especialidades) {
        json_response([
            'success' => false,
            'message' => 'Cadastre pelo menos uma especialidade.'
        ], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM ubs
         WHERE usuario = ?
           AND id <> ?'
    );

    $stmt->execute([
        $usuario,
        $ubsId
    ]);

    if ($stmt->fetch()) {
        json_response([
            'success' => false,
            'message' => 'Esse usuário já pertence a outra UBS.'
        ], 409);
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM administradores
         WHERE usuario = ?
           AND (ubs_id IS NULL OR ubs_id <> ?)'
    );

    $stmt->execute([
        $usuario,
        $ubsId
    ]);

    if ($stmt->fetch()) {
        json_response([
            'success' => false,
            'message' => 'Esse usuário já está sendo utilizado por outro administrador.'
        ], 409);
    }

    $pdo->beginTransaction();

    try {

        $stmt = $pdo->prepare(
            'UPDATE ubs
             SET nome = ?,
                 endereco = ?,
                 telefone = ?,
                 horario = ?,
                 usuario = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $nome,
            $endereco,
            $telefone,
            $horario,
            $usuario,
            $ubsId
        ]);

        replace_list(
            $pdo,
            'ubs_especialidades',
            $ubsId,
            $especialidades
        );

        replace_list(
            $pdo,
            'ubs_servicos',
            $ubsId,
            $servicos
        );

        replace_list(
            $pdo,
            'ubs_campanhas',
            $ubsId,
            $campanhas
        );

        replace_list(
            $pdo,
            'ubs_documentos',
            $ubsId,
            $documentos
        );

        $stmt = $pdo->prepare(
            'UPDATE administradores
             SET usuario = ?
             WHERE ubs_id = ?'
        );

        $stmt->execute([
            $usuario,
            $ubsId
        ]);

        if ($novaSenha !== '') {

            $stmt = $pdo->prepare(
                'UPDATE administradores
                 SET senha_hash = ?
                 WHERE ubs_id = ?'
            );

            $stmt->execute([
                password_hash(
                    $novaSenha,
                    PASSWORD_DEFAULT
                ),
                $ubsId
            ]);

        }

        $pdo->commit();

        json_response([
            'success' => true,
            'ubs' => get_ubs(
                $pdo,
                $ubsId,
                true
            )
        ]);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function replace_list(
    PDO $pdo,
    string $table,
    string $ubsId,
    array $items
): void
{
    $allowed = [
        'ubs_especialidades',
        'ubs_servicos',
        'ubs_campanhas',
        'ubs_documentos'
    ];

    if (!in_array($table, $allowed, true)) {
        throw new RuntimeException(
            'Tabela de lista inválida.'
        );
    }

    $pdo->prepare(
        "DELETE FROM {$table} WHERE ubs_id = ?"
    )->execute([$ubsId]);

    $stmt = $pdo->prepare(
        "INSERT INTO {$table} (ubs_id, nome) VALUES (?, ?)"
    );

    foreach ($items as $item) {
        $stmt->execute([
            $ubsId,
            $item
        ]);
    }
}

function save_employee(PDO $pdo, array $data): never
{
    $ubsId = required_string($data, 'ubs_id');

    authorize_ubs($ubsId);

    $nome = required_string($data, 'nome');
    $cargo = required_string($data, 'cargo');
    $id = trim((string)($data['id'] ?? ''));

    if ($id !== '') {

        $stmt = $pdo->prepare(
            'UPDATE funcionarios
             SET nome = ?, cargo = ?
             WHERE id = ? AND ubs_id = ?'
        );

        $stmt->execute([
            $nome,
            $cargo,
            $id,
            $ubsId
        ]);

        if ($stmt->rowCount() === 0) {

            $check = $pdo->prepare(
                'SELECT id
                 FROM funcionarios
                 WHERE id = ?'
            );

            $check->execute([$id]);

            if ($check->fetch()) {
                json_response([
                    'success' => false,
                    'message' => 'Funcionário não pertence a esta UBS.'
                ], 403);
            }

            json_response([
                'success' => false,
                'message' => 'Funcionário não encontrado.'
            ], 404);
        }

    } else {

        $id =
            'func-' .
            bin2hex(
                random_bytes(6)
            );

        $stmt = $pdo->prepare(
            'INSERT INTO funcionarios
                (id, ubs_id, nome, cargo)
             VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            $id,
            $ubsId,
            $nome,
            $cargo
        ]);

    }

    $stmt = $pdo->prepare(
        'SELECT id, nome, cargo
         FROM funcionarios
         WHERE ubs_id = ?
         ORDER BY nome'
    );

    $stmt->execute([$ubsId]);

    json_response([
        'success' => true,
        'funcionarios' => $stmt->fetchAll()
    ]);
}

function delete_employee(PDO $pdo, array $data): never
{
    $id = required_string($data, 'id');

    $stmt = $pdo->prepare(
        'SELECT ubs_id
         FROM funcionarios
         WHERE id = ?'
    );

    $stmt->execute([$id]);

    $employee = $stmt->fetch();

    if (!$employee) {
        json_response([
            'success' => false,
            'message' => 'Funcionário não encontrado.'
        ], 404);
    }

    authorize_ubs($employee['ubs_id']);

    $stmt = $pdo->prepare(
        'DELETE FROM funcionarios
         WHERE id = ?'
    );

    $stmt->execute([$id]);

    $stmt = $pdo->prepare(
        'SELECT id, nome, cargo
         FROM funcionarios
         WHERE ubs_id = ?
         ORDER BY nome'
    );

    $stmt->execute([
        $employee['ubs_id']
    ]);

    json_response([
        'success' => true,
        'funcionarios' => $stmt->fetchAll()
    ]);
}

function admin_appointments(PDO $pdo): never
{
    $ubsId =
        trim(
            (string)(
                $_GET['ubs_id'] ?? ''
            )
        );

    authorize_ubs($ubsId);

    $date =
        trim(
            (string)(
                $_GET['data'] ?? ''
            )
        );

    $specialty =
        trim(
            (string)(
                $_GET['especialidade'] ?? ''
            )
        );

    $sql =
        'SELECT
            c.id,
            c.ubs_id AS ubsId,
            u.nome AS ubsNome,
            p.nome,
            p.telefone,
            p.sus,
            c.especialidade,
            c.assunto,
            DATE_FORMAT(c.data_consulta, "%Y-%m-%d") AS data,
            CASE WHEN c.horario = \'00:00:00\' THEN NULL ELSE TIME_FORMAT(c.horario, \'%H:%i\') END AS horario,
            c.fila,
            c.status,
            c.cancelamento_motivo AS cancelamentoMotivo,
            c.lembrete,
            c.notificado,
            c.criada_em AS criadaEm
         FROM consultas c
         INNER JOIN pacientes p ON p.id = c.paciente_id
         INNER JOIN ubs u ON u.id = c.ubs_id
         WHERE c.ubs_id = ?';

    $params = [$ubsId];

    if ($date !== '') {
        $sql .= ' AND c.data_consulta = ?';
        $params[] = $date;
    }

    if ($specialty !== '') {
        $sql .= ' AND c.especialidade = ?';
        $params[] = $specialty;
    }

    $sql .=
        ' ORDER BY c.data_consulta, c.horario';

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['lembrete'] = (bool)$row['lembrete'];
        $row['notificado'] = (bool)$row['notificado'];
    }

    json_response([
        'success' => true,
        'appointments' => $rows
    ]);
}
