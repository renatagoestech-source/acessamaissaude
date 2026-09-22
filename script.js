/* ============================================================
   ACESSA+ SAÚDE
   SISTEMA COMPLETO DE AGENDAMENTO UBS
   ============================================================ */

"use strict";

/* ============================================================
   CONFIGURAÇÕES
   ============================================================ */

const API_URL = "api.php";
const STORAGE_PATIENT = "acessaMaisSaude_patient_v1";
const CPF_STORAGE_KEY = 'acessaMaisSaude_cpf_v1';

const HORARIOS_MANHA = [
    "07:00","07:30","08:00","08:30","09:00",
    "09:30","10:00","10:30","11:00","11:30"
];

const HORARIOS_TARDE = [
    "13:00","13:30","14:00","14:30","15:00",
    "15:30","16:00","16:30","17:00","17:30"
];
/* ============================================================
   ESTADO DO SISTEMA
   ============================================================ */

let ubsList = [];
let appointments = [];
let occupiedSlots = [];
let databaseReady = false;

let currentUBS = null;
let currentSpecialty = null;
let currentDate = null;
let currentTime = null;
let currentSubject = "";

let calendarDate = new Date();

let adminSession = null;
let professionalSession = null;
let professionalPatients = [];
let appConfig = {modo:"ubs", nomeExibicao:"Acessa+ Saúde"};
let editingEmployeeId = null;

/* ============================================================
   INICIALIZAÇÃO
   ============================================================ */

document.addEventListener("DOMContentLoaded", async function () {

    const supportFab = $("supportFab");
    if (supportFab) supportFab.addEventListener("click", abrirSuporte);

    // O cadastro do paciente vale somente enquanto esta página está aberta.
    // Assim, voltar ao início ou abrir o site novamente não reaproveita dados pessoais.
    sessionStorage.removeItem(STORAGE_PATIENT);

    await carregarDados();

    aplicarMascaraTelefone();

    document
        .getElementById("susPaciente")
        .addEventListener("input", function () {
            this.value = this.value.replace(/\D/g, "").slice(0, 15);
        });

    renderUBS();
    preencherSelectUBSPaciente();

    const pacienteSalvo = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (pacienteSalvo && databaseReady) {
        definirModoUsuario(true);
        preencherDadosPaciente(pacienteSalvo);
        atualizarDashboard();
        mostrarApenas("dashboardSection");
    } else {
        definirModoUsuario(false);
        const clinicSlug = new URLSearchParams(location.search).get("clinica");
        if (databaseReady && clinicSlug) carregarClinicaPublica(clinicSlug);
        else mostrarApenas(databaseReady ? "portalChooserSection" : "databaseSetupSection");
    }

    verificarLembretes();

});

async function api(action, options = {}) {

    const params = options.params || {};

    const query = new URLSearchParams({
        action,
        ...params
    });

    const config = {
        ...options,
        headers: {
            ...(options.headers || {})
        }
    };

    delete config.params;

    if (config.body instanceof FormData) {
        // O navegador define automaticamente o Content-Type e o boundary.
    } else if (config.body && typeof config.body !== "string") {

        config.headers["Content-Type"] = "application/json";

        config.body = JSON.stringify(config.body);

    }

    const response =
        await fetch(`${API_URL}?${query.toString()}`, config);

    const data =
        await response
            .json()
            .catch(() => ({
                success: false,
                message: "Resposta inválida do servidor."
            }));

    if (!response.ok || data.success === false) {

        throw new Error(
            data.message ||
            "Erro no servidor."
        );

    }

    return data;

}

async function carregarDados() {

    try {

        const configData = await api("get_app_config");
        appConfig = configData.config || appConfig;
        aplicarConfiguracaoApp();
        const data =
            await api("get_ubs");

        ubsList =
            data.ubs || [];
        databaseReady = true;

        const paciente =
            JSON.parse(
                sessionStorage.getItem(
                    STORAGE_PATIENT
                ) || "null"
            );

        if (paciente) {

            await carregarConsultasPaciente(
                paciente.sus
            );

        }

    } catch (error) {
        databaseReady = false;
        ubsList = [];
        appointments = [];
        renderUBS();
        preencherSelectUBSPaciente();

        const setupMessage = $("databaseSetupMessage");
        if (setupMessage) {
            setupMessage.textContent =
                "O sistema não conseguiu acessar o banco de dados. Instale ou verifique o MySQL para liberar o cadastro, os agendamentos e a área administrativa.";
        }

    }

}

async function carregarConsultasPaciente(sus) {

    try {

        const data =
            await api(
                "get_patient_appointments",
                {
                    params: { sus }
                }
            );

        appointments =
            data.appointments || [];

    } catch (error) {

        appointments = [];

    }

}

async function carregarHorariosOcupados() {

    occupiedSlots = [];

    if (
        !currentUBS ||
        !currentSpecialty ||
        !currentDate
    ) {
        return;
    }

    try {

        const data =
            await api(
                "get_occupied_slots",
                {
                    params: {
                        ubs_id: currentUBS.id,
                        especialidade: currentSpecialty,
                        assunto: currentSubject,
                        data: currentDate
                    }
                }
            );

        occupiedSlots = data;

    } catch (error) {

        occupiedSlots = [];

    }

}

function salvarUBS() {
    return Promise.resolve();
}

function salvarConsultas() {
    return Promise.resolve();
}

/* ============================================================
   UTILIDADES
   ============================================================ */

function $(id) {
    return document.getElementById(id);
}

function escapeHTML(text) {

    if (text === null || text === undefined) {
        return "";
    }

    return String(text)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function toast(mensagem) {

    const elemento = $("toast");

    elemento.textContent = mensagem;
    elemento.classList.add("show");

    setTimeout(function () {
        elemento.classList.remove("show");
    }, 3000);
}

function mostrarApenas(id) {

    const secoes = [
        "cadastro",
        "databaseSetupSection",
        "perfilSection",
        "dashboardSection",
        "ubsSection",
        "ubsDetalhes",
        "especialidadeSection",
        "agendaSection",
        "confirmacaoSection",
        "meusAgendamentos",
        "examesSection",
        "avisosSection",
        "ajudaSection",
        "professionalSection",
        "portalChooserSection",
        "publicClinicSection"
    ];

    secoes.forEach(function (secao) {

        const elemento = $(secao);

        if (elemento) {
            elemento.classList.add("hidden");
        }

    });

    $(id).classList.remove("hidden");

    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
}

/* ============================================================
   PACIENTE
   ============================================================ */

async function salvarPaciente() {

    const nome =
        $("nomePaciente").value.trim();

    const telefone =
        $("telefonePaciente").value.trim();

    const sus =
        $("susPaciente").value.trim();

    if (!nome) {

        toast("Digite seu nome completo.");
        $("nomePaciente").focus();

        return;

    }

    if (
        telefone.replace(/\D/g, "").length < 10
    ) {

        toast("Digite um celular válido.");
        $("telefonePaciente").focus();

        return;

    }

    if (sus.length < 8) {

        toast("Digite o número do Cartão SUS.");
        $("susPaciente").focus();

        return;

    }

    const ubsId = $("ubsPaciente").value;
    if (!ubsId) {
        toast("Selecione sua UBS de referência.");
        $("ubsPaciente").focus();
        return;
    }

    try {

        const data =
            await api(
                "save_patient",
                {
                    method: "POST",
                    body: {
                        nome,
                        telefone,
                        sus,
                        ubs_id: ubsId
                    }
                }
            );

        sessionStorage.setItem(
            STORAGE_PATIENT,
            JSON.stringify(data.patient)
        );

        definirModoUsuario(true);

        await carregarConsultasPaciente(sus);
        await carregarExamesPaciente(sus);
        preencherDadosPaciente(data.patient);
        atualizarDashboard();
        mostrarApenas("dashboardSection");
        toast("Cadastro realizado. Bem-vindo ao Acessa+ Saúde!");

    } catch (error) {

        toast(error.message);

    }

}

function mostrarPerfilPaciente() {
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (!paciente) {
        mostrarApenas("cadastro");
        toast("Faça seu cadastro primeiro.");
        return;
    }
    preencherPerfilPaciente(paciente);
    mostrarApenas("perfilSection");
}

function preencherPerfilPaciente(paciente) {
    const campos = {
        perfilNome: paciente.nome,
        perfilTelefone: paciente.telefone,
        perfilNascimento: paciente.dataNascimento,
        perfilEndereco: paciente.endereco,
        perfilCondicoes: paciente.condicoesSaude,
        perfilAlergias: paciente.alergias,
        perfilMedicamentos: paciente.medicamentos,
        perfilInformacoes: paciente.informacoesAdicionais
    };
    Object.entries(campos).forEach(([id, value]) => { if ($(id)) $(id).value = value || ""; });
}

async function salvarPerfilPaciente() {
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (!paciente) return;
    const body = {
        sus: paciente.sus,
        nome: $("perfilNome").value.trim(),
        telefone: $("perfilTelefone").value.trim(),
        data_nascimento: $("perfilNascimento").value,
        endereco: $("perfilEndereco").value.trim(),
        condicoes_saude: $("perfilCondicoes").value.trim(),
        alergias: $("perfilAlergias").value.trim(),
        medicamentos: $("perfilMedicamentos").value.trim(),
        informacoes_adicionais: $("perfilInformacoes").value.trim()
    };
    if (!body.nome || body.telefone.replace(/\D/g, "").length < 10) {
        toast("Informe nome e celular válidos.");
        return;
    }
    try {
        const data = await api("update_patient_profile", {method: "POST", body});
        sessionStorage.setItem(STORAGE_PATIENT, JSON.stringify(data.patient));
        preencherDadosPaciente(data.patient);
        toast("Perfil atualizado com sucesso.");
        mostrarApenas("dashboardSection");
    } catch (error) {
        toast(error.message);
    }
}

function renderUBS() {

    const grid = $("ubsGrid");
    if (!grid) return;

    grid.innerHTML = "";

    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    const unidades = paciente?.ubsId ? ubsList.filter(ubs => ubs.id === paciente.ubsId) : ubsList;
    unidades.forEach(function (ubs) {

        const card = document.createElement("div");

        card.className = "ubs-card";

        card.innerHTML = `
            <div class="ubs-icon">🏥</div>

            <h3>${escapeHTML(ubs.nome)}</h3>

            <p>📍 ${escapeHTML(ubs.endereco)}</p>

            <p>☎ ${escapeHTML(ubs.telefone)}</p>

            <p>🕐 ${escapeHTML(ubs.horario)}</p>

            <br>

            <button class="btn primary">
                Entrar na UBS →
            </button>
        `;

        card.addEventListener("click", function () {
            abrirUBS(ubs.id);
        });

        grid.appendChild(card);

    });
}

function abrirUBS(id) {

    const ubs = ubsList.find(function (item) {
        return item.id === id;
    });

    if (!ubs) {
        toast("UBS não encontrada.");
        return;
    }

    currentUBS = ubs;

    $("detalheNomeUBS").textContent = ubs.nome;
    $("detalheEndereco").textContent = "📍 " + ubs.endereco;
    $("detalheTelefone").textContent = "☎ " + ubs.telefone;
    $("detalheHorario").textContent = "🕐 " + ubs.horario;

    preencherLista("detalheServicos", ubs.servicos);
    preencherLista("detalheCampanhas", ubs.campanhas);
    preencherLista("detalheDocumentos", ubs.documentos);

    const funcionarios = ubs.funcionarios
        .map(function (funcionario) {
            return `${funcionario.nome} — ${funcionario.cargo}`;
        });

    preencherLista("detalheFuncionarios", funcionarios);

    mostrarApenas("ubsDetalhes");

    atualizarPasso(2);
}

function preencherLista(id, itens) {

    const elemento = $(id);

    elemento.innerHTML = "";

    if (!itens || itens.length === 0) {

        elemento.innerHTML = "<li>Nenhuma informação cadastrada.</li>";

        return;
    }

    itens.forEach(function (item) {

        const li = document.createElement("li");

        li.textContent = item;

        elemento.appendChild(li);

    });
}

function voltarUBS() {

    currentUBS = null;

    mostrarApenas("ubsSection");

    atualizarPasso(2);
}

/* ============================================================
   ESPECIALIDADES
   ============================================================ */

function irEspecialidades() {

    if (!currentUBS) {
        toast("Escolha uma UBS primeiro.");
        return;
    }

    renderEspecialidades();

    mostrarApenas("especialidadeSection");

    atualizarPasso(3);
}

function renderEspecialidades() {

    const grid = $("especialidadesGrid");

    grid.innerHTML = "";

    $("especialidadeUBSText").textContent =
        currentUBS.nome + " • Escolha uma especialidade";

    const icones = {
        "Clínico Geral": "🩺",
        "Dentista": "🦷",
        "Enfermeira": "👩‍⚕️"
    };

    currentUBS.especialidades.forEach(function (especialidade) {

        const div = document.createElement("div");

        div.className = "option";

        div.innerHTML = `
            <div class="option-icon">
                ${icones[especialidade] || "🏥"}
            </div>

            <h3>${escapeHTML(especialidade)}</h3>

            <p>Ver horários disponíveis</p>
        `;

        div.addEventListener("click", function () {

            selecionarEspecialidade(especialidade);

        });

        grid.appendChild(div);

    });
}

function selecionarEspecialidade(especialidade) {

    currentSpecialty = especialidade;
    currentSubject = "";
    currentDate = null;
    currentTime = null;
    if ($("consultaAssunto")) $("consultaAssunto").value = "";

    $("agendaInfo").textContent =
        currentUBS.nome +
        " • " +
        currentSpecialty;

    calendarDate = new Date();

    renderCalendario();

    mostrarApenas("agendaSection");

    atualizarPasso(4);
}

function voltarDetalhes() {

    if (currentUBS) {
        mostrarApenas("ubsDetalhes");
    }
}

function voltarEspecialidades() {

    mostrarApenas("especialidadeSection");

    atualizarPasso(3);
}

/* ============================================================
   CALENDÁRIO
   ============================================================ */

function renderCalendario() {

    const calendar = $("calendar");

    calendar.innerHTML = "";

    const ano = calendarDate.getFullYear();
    const mes = calendarDate.getMonth();

    $("mesAtual").textContent =
        new Intl.DateTimeFormat("pt-BR", {
            month: "long",
            year: "numeric"
        }).format(calendarDate);

    const diasSemana = [
        "Dom",
        "Seg",
        "Ter",
        "Qua",
        "Qui",
        "Sex",
        "Sáb"
    ];

    diasSemana.forEach(function (dia) {

        const elemento = document.createElement("div");

        elemento.className = "day-name";

        elemento.textContent = dia;

        calendar.appendChild(elemento);

    });

    const primeiroDia = new Date(
        ano,
        mes,
        1
    ).getDay();

    const quantidadeDias =
        new Date(
            ano,
            mes + 1,
            0
        ).getDate();

    for (let i = 0; i < primeiroDia; i++) {

        const vazio = document.createElement("div");

        calendar.appendChild(vazio);
    }

    for (let dia = 1; dia <= quantidadeDias; dia++) {

        const data = new Date(
            ano,
            mes,
            dia
        );

        const elemento = document.createElement("button");

        elemento.className = "day";

        elemento.textContent = dia;

        const dataISO = formatarData(data);

        if (
            data.getDay() === 0 ||
            data.getDay() === 6 ||
            ehFeriado(data)
        ) {

            elemento.classList.add("disabled");

            elemento.disabled = true;

        } else {

            elemento.addEventListener(
                "click",
                function () {
                    selecionarData(dataISO);
                }
            );

        }

        if (dataISO === currentDate) {
            elemento.classList.add("selected");
        }

        calendar.appendChild(elemento);
    }
}

function selecionarData(data) {

    currentDate = data;

    renderCalendario();

    renderHorarios();

    atualizarPasso(4);
}

function mesAnterior() {

    const hoje = new Date();

    const mesAtualReal =
        hoje.getFullYear() * 12 +
        hoje.getMonth();

    const mesSelecionado =
        calendarDate.getFullYear() * 12 +
        calendarDate.getMonth();

    if (mesSelecionado <= mesAtualReal) {
        return;
    }

    calendarDate.setMonth(
        calendarDate.getMonth() - 1
    );

    renderCalendario();
}

function mesProximo() {

    calendarDate.setMonth(
        calendarDate.getMonth() + 1
    );

    renderCalendario();
}

function formatarData(data) {

    const ano = data.getFullYear();

    const mes = String(
        data.getMonth() + 1
    ).padStart(2, "0");

    const dia = String(
        data.getDate()
    ).padStart(2, "0");

    return `${ano}-${mes}-${dia}`;
}

/* ============================================================
   FERIADOS
   ============================================================ */

function ehFeriado(data) {

    const dia = data.getDate();
    const mes = data.getMonth() + 1;

    const fixos = [
        "01-01",
        "04-21",
        "05-01",
        "09-07",
        "10-12",
        "11-02",
        "11-15",
        "12-25"
    ];

    const chave =
        String(mes).padStart(2, "0") +
        "-" +
        String(dia).padStart(2, "0");

    return fixos.includes(chave);
}

/* ============================================================
   HORÁRIOS
   ============================================================ */

async function renderHorarios(){const box=$("filaDisponibilidade");if(!currentDate){box.innerHTML='<h3>Escolha um dia para ver a fila</h3><p>São 12 vagas por dia para cada especialidade.</p>';return;}box.innerHTML='<p>Consultando vagas...</p>';await carregarHorariosOcupados();const ocupadas=occupiedSlots.ocupadas||0;if(ocupadas>=12){box.innerHTML='<h3>Dia lotado</h3><p>As 12 vagas desta especialidade já foram preenchidas.</p><button class="btn secondary" onclick="entrarListaEspera()">Entrar na lista de espera</button>';return;}const fila=occupiedSlots.proximaFila||ocupadas+1;box.innerHTML=`<h3>${fila}ª posição disponível</h3><p>${ocupadas} de 12 vagas preenchidas.</p><button class="btn primary" onclick="agendarConsulta()">Confirmar agendamento — entrar na fila</button>`;}

async function entrarListaEspera(){const paciente=JSON.parse(sessionStorage.getItem(STORAGE_PATIENT)||'null');if(!paciente||!currentUBS||!currentSpecialty||!currentDate)return;try{const d=await api('join_waitlist',{method:'POST',body:{sus:paciente.sus,ubs_id:currentUBS.id,especialidade:currentSpecialty,data:currentDate}});toast(d.message);}catch(e){toast(e.message);}}

async function agendarConsulta() {

    const paciente =
        JSON.parse(
            sessionStorage.getItem(
                STORAGE_PATIENT
            ) || "null"
        );

    if (!paciente) {

        toast(
            "Preencha seus dados primeiro."
        );

        mostrarApenas("cadastro");

        return;

    }

    if (
        !currentUBS ||
        !currentSpecialty ||
        !currentDate
    ) {

        toast(
            "Selecione UBS, especialidade e data."
        );

        return;

    }

    currentSubject = $("consultaAssunto")?.value.trim() || "";

    try {

        const data =
            await api(
                "book_appointment",
                {
                    method: "POST",
                    body: {
                        ubs_id: currentUBS.id,
                        sus: paciente.sus,
                        especialidade: currentSpecialty,
                        assunto: currentSubject,
                        data: currentDate
                    }
                }
            );

        appointments.push(
            data.appointment
        );

        currentTime = null;

        mostrarComprovante(
            data.appointment
        );

        mostrarApenas(
            "confirmacaoSection"
        );

        atualizarPasso(5);

        toast(
            "Consulta agendada com sucesso!"
        );

    } catch (error) {

        toast(error.message);

        await renderHorarios();

    }

}

function mostrarComprovante(consulta) {

    const dataFormatada =
        formatarDataBR(consulta.data);

    $("comprovante").innerHTML = `

        <img src="img/logo.png" class="receipt-logo" alt="Acessa+ Saúde">

        <div class="receipt-row">
            <strong>Paciente</strong>
            <span>${escapeHTML(consulta.nome)}</span>
        </div>

        <div class="receipt-row">
            <strong>UBS</strong>
            <span>${escapeHTML(consulta.ubsNome)}</span>
        </div>

        <div class="receipt-row">
            <strong>Especialidade</strong>
            <span>${escapeHTML(consulta.especialidade)}</span>
        </div>

        ${consulta.assunto ? `<div class="receipt-row"><strong>Assunto</strong><span>${escapeHTML(consulta.assunto)}</span></div>` : ""}

        <div class="receipt-row">
            <strong>Data</strong>
            <span>${dataFormatada}</span>
        </div>

        <div class="receipt-row">
            <strong>Fila</strong>
            <span>${consulta.fila}º atendimento do dia</span>
        </div>

        <div class="receipt-row">
            <strong>Posição na fila</strong>
            <span>${consulta.fila}º</span>
        </div>

        <div class="receipt-row">
            <strong>Protocolo</strong>
            <span>${consulta.id}</span>
        </div>

    `;
}

function imprimirComprovante() {

    window.print();

}

/* ============================================================
   MEUS AGENDAMENTOS
   ============================================================ */


let patientExams = [];
let historicoFiltro = "todas";

function preencherDadosPaciente(paciente) {
    if (!paciente) return;
    if ($("nomePaciente")) $("nomePaciente").value = paciente.nome || "";
    if ($("telefonePaciente")) $("telefonePaciente").value = paciente.telefone || "";
    if ($("susPaciente")) $("susPaciente").value = paciente.sus || "";
    if ($("ubsPaciente")) $("ubsPaciente").value = paciente.ubsId || "";
    if ($("nomeTopo")) $("nomeTopo").textContent = "Olá, " + ((paciente.nome || "").split(" ")[0] || "Paciente");
    if ($("nomeDashboard")) $("nomeDashboard").textContent = ((paciente.nome || "").split(" ")[0] || "Paciente");
}

async function carregarExamesPaciente(sus) {
    try {
        const data = await api("get_patient_exams", { params: { sus } });
        patientExams = data.exams || [];
    } catch (error) {
        patientExams = [];
    }
}

function atualizarDashboard() {
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (!paciente) return;
    preencherDadosPaciente(paciente);
    const unidadeAtual = ubsList.find(u => u.id === paciente.ubsId);
    if ($("ubsAtualDashboard")) $("ubsAtualDashboard").textContent = unidadeAtual?.nome || "Não informada";
    const futuras = appointments.filter(a => a.status === "agendado").sort((a,b) => (a.data+a.horario).localeCompare(b.data+b.horario));
    const realizadas = appointments.filter(a => a.status === "atendido");
    const canceladas = appointments.filter(a => a.status === "cancelado");
    $("countProximas") && ($("countProximas").textContent = futuras.length);
    $("countRealizadas") && ($("countRealizadas").textContent = realizadas.length);
    $("countCanceladas") && ($("countCanceladas").textContent = canceladas.length);

    const prox = $("dashboardProximas");
    if (prox) {
        prox.innerHTML = futuras.length ? futuras.slice(0,2).map(c => `<div class="dashboard-consult"><strong>${escapeHTML(c.especialidade)}</strong>${c.assunto ? `<p>📝 ${escapeHTML(c.assunto)}</p>` : ""}<p>📍 ${escapeHTML(c.ubsNome)}</p><p>📅 ${formatarDataBR(c.data)} às ${escapeHTML(c.horario)}</p></div>`).join("") : '<div class="info-box">Você não possui consultas marcadas.</div>';
    }
    const avisos = $("dashboardAvisos");
    if (avisos) {
        const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
        const unidades = paciente?.ubsId ? ubsList.filter(u => u.id === paciente.ubsId) : [];
        const itens = unidades.flatMap(u => (u.campanhas || []).slice(0,2).map(c => ({nome:c,ubs:u.nome})));
        avisos.innerHTML = itens.length ? itens.slice(0,4).map(i => `<div class="notice"><b>${escapeHTML(i.nome)}</b><small>${escapeHTML(i.ubs)}</small></div>`).join("") : '<div class="info-box">Nenhum aviso cadastrado.</div>';
    }
}

function iniciarAgendamento() {
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (!paciente) { mostrarApenas("cadastro"); toast("Faça seu cadastro para agendar uma consulta."); return; }
    if (paciente.ubsId) { abrirUBS(paciente.ubsId); return; }
    renderUBS();
    mostrarApenas("ubsSection");
    atualizarPasso(2);
}

function mostrarUBSMenu() { iniciarAgendamento(); }

function mostrarExames() {
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (!paciente) { mostrarApenas("cadastro"); toast("Faça seu cadastro para acessar seus resultados."); return; }
    carregarExamesPaciente(paciente.sus).then(() => renderExamesPaciente());
    mostrarApenas("examesSection");
}

function renderExamesPaciente() {
    const container = $("listaExames");
    if (!container) return;
    if (!patientExams.length) { container.innerHTML = '<div class="info-box"><h3>Nenhum resultado disponível</h3><p>Quando a UBS disponibilizar um resultado, ele aparecerá aqui.</p></div>'; return; }
    container.innerHTML = patientExams.map(e => `<article class="exam-card"><div class="exam-head"><div><h3>${escapeHTML(e.nome)}</h3><span class="exam-date">Data: ${formatarDataBR(e.data)} • ${escapeHTML(e.ubsNome)}</span></div><span class="status">Disponível</span></div><div class="exam-result"><strong>Resultado</strong><br>${escapeHTML(e.resultado)}</div>${e.observacoes ? `<div class="subtitle"><strong>Observações:</strong> ${escapeHTML(e.observacoes)}</div>` : ""}${e.anexo ? `<a class="btn secondary exam-attachment" href="api.php?action=download_exam&id=${encodeURIComponent(e.id)}&sus=${encodeURIComponent(JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "{}").sus || "")}" target="_blank" rel="noopener">Abrir anexo: ${escapeHTML(e.anexoNome || "resultado")}</a>` : ""}</article>`).join("");
}

function mostrarAvisos() {
    const container = $("listaAvisos");
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    const unidades = paciente?.ubsId ? ubsList.filter(u => u.id === paciente.ubsId) : [];
    const itens = unidades.flatMap(u => (u.campanhas || []).map(c => ({nome:c,ubs:u.nome,horario:u.horario})));
    container.innerHTML = itens.length ? itens.map(i => `<div class="notice-card"><span class="tag">CAMPANHA / AVISO</span><h3>${escapeHTML(i.nome)}</h3><p>${escapeHTML(i.ubs)} • Atendimento: ${escapeHTML(i.horario)}</p></div>`).join("") : '<div class="info-box">Nenhum aviso cadastrado.</div>';
    mostrarApenas("avisosSection");
}

async function verificarNotificacoes() {
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (!paciente) { toast("Faça seu cadastro para receber notificações."); return; }
    try { const data = await api("get_patient_notifications", {params:{sus:paciente.sus}}); const pendentes = (data.notifications || []).filter(n => n.status === "pendente"); toast(pendentes.length ? `${pendentes.length} lembrete(s) de consulta pendente(s).` : "Você não possui notificações pendentes."); } catch (error) { toast(error.message); }
}

function mostrarAjuda() { mostrarApenas("ajudaSection"); }

function mostrarAgendamentos() {

    const paciente =
        JSON.parse(
            sessionStorage.getItem(
                STORAGE_PATIENT
            ) || "null"
        );

    if (!paciente) {

        toast(
            "Faça seu cadastro primeiro."
        );

        mostrarApenas(
            "cadastro"
        );

        return;

    }

    renderMeusAgendamentos();
    atualizarDashboard();
    mostrarApenas("meusAgendamentos");

}


function renderMeusAgendamentos() {
    const container = $("listaAgendamentos");
    if (!container) return;
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (!paciente) { container.innerHTML = ""; return; }
    const lista = historicoFiltro === "todas" ? appointments : appointments.filter(a => a.status === historicoFiltro);
    if (!lista.length) { container.innerHTML = '<div class="info-box">Nenhuma consulta encontrada nesta categoria.</div>'; return; }
    const ordenada = [...lista].sort((a,b) => (b.data+b.horario).localeCompare(a.data+a.horario));
    container.innerHTML = ordenada.map(c => {
        const statusTexto = c.status === "atendido" ? "Realizada" : c.status === "cancelado" ? "Cancelada" : c.status === "faltou" ? "Não compareceu" : "Marcada";
        const classe = c.status;
        return `<article class="appointment"><div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><h3>${escapeHTML(c.especialidade)}</h3><span class="status ${classe}">${statusTexto}</span></div>${c.assunto ? `<p>📝 <strong>Assunto:</strong> ${escapeHTML(c.assunto)}</p>` : ""}<p>📍 ${escapeHTML(c.ubsNome)}</p><p>📅 ${formatarDataBR(c.data)} às ${escapeHTML(c.horario)}</p><p>Protocolo: ${escapeHTML(c.id)}</p>${c.status === "agendado" ? `<div class="appointment-actions"><button class="btn secondary" onclick="ativarLembrete('${escapeHTML(c.id)}')">🔔 ${c.lembrete ? "Lembrete ativado" : "Ativar lembrete"}</button><button class="btn danger" onclick="cancelarConsultaPaciente('${escapeHTML(c.id)}')">Cancelar</button><button class="btn secondary" onclick="imprimirConsulta('${escapeHTML(c.id)}')">Imprimir</button></div>` : ""}</article>`;
    }).join("");
}

function filtrarHistorico(filtro, botao) {
    historicoFiltro = filtro;
    document.querySelectorAll(".history-tabs button").forEach(b => b.classList.remove("active"));
    if (botao) botao.classList.add("active");
    renderMeusAgendamentos();
}

async function cancelarConsultaPaciente(id) {
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (!paciente) return;
    const motivo = prompt("Informe o motivo do cancelamento (opcional):", "");
    if (motivo === null) return;
    try {
        await api("cancel_appointment", {method:"POST", body:{id, sus:paciente.sus, motivo:motivo.trim()}});
        await carregarConsultasPaciente(paciente.sus);
        renderMeusAgendamentos();
        atualizarDashboard();
        toast("Consulta cancelada.");
    } catch(error) { toast(error.message); }
}

function preencherSelectUBSPaciente(){const select=$("ubsPaciente");if(select)select.innerHTML='<option value="">Selecione sua UBS</option>'+ubsList.map(u=>`<option value="${escapeHTML(u.id)}">${escapeHTML(u.nome)}</option>`).join('');}
function abrirSuporte(){const p=JSON.parse(sessionStorage.getItem(STORAGE_PATIENT)||'null');if(p){$("suporteNome").value=p.nome||'';$("suporteTelefone").value=p.telefone||'';}$("supportModal").classList.remove('hidden');carregarMeuSuporte();}
function fecharSuporte(){$("supportModal").classList.add('hidden');}
async function carregarMeuSuporte(){
    const box=$("supportHistory");
    const entries=JSON.parse(localStorage.getItem('CONectaSupportProtocols')||'[]');
    if(!entries.length){box.innerHTML='<div class="info-box">Envie um chamado para receber um protocolo e acompanhar a resposta. Somente quem possui a chave de acesso poderá consultar o chamado.</div>';return;}
    try{
        let msgs=[];
        for(const item of entries.slice(-10)){
            try{const d=await api('get_support',{params:{protocolo:item.protocolo,token:item.token}});for(const m of (d.messages||[])){if(!msgs.some(x=>x.protocolo===m.protocolo))msgs.push(m);}}catch(_e){}
        }
        msgs.sort((a,b)=>String(b.atualizadoEm||b.criadoEm||'').localeCompare(String(a.atualizadoEm||a.criadoEm||'')));
        box.innerHTML=msgs.length?msgs.map(m=>`<div class="info-box"><strong>${escapeHTML(m.protocolo)}</strong><p>Status: ${escapeHTML(m.status)}</p><p>${escapeHTML(m.mensagem)}</p>${m.resposta?`<div class="support-reply"><strong>Resposta do desenvolvedor:</strong><p>${escapeHTML(m.resposta)}</p><small>Atualizado em: ${escapeHTML(m.atualizadoEm||m.criadoEm||'')}</small></div>`:'<p><em>Aguardando resposta.</em></p>'}</div>`).join(''):'<div class="info-box">Nenhum chamado acessível neste dispositivo.</div>';
    }catch(e){box.innerHTML='<div class="info-box">'+escapeHTML(e.message)+'</div>';}
}
async function enviarSuporte(){
    const p=JSON.parse(sessionStorage.getItem(STORAGE_PATIENT)||'null');
    const body={nome:$("suporteNome").value.trim(),telefone:$("suporteTelefone").value.trim(),mensagem:$("suporteMensagem").value.trim(),sus:p?.sus||''};
    if(!body.nome||!body.telefone||!body.mensagem){toast('Preencha nome, celular e mensagem.');return;}
    try{
        const d=await api('support_message',{method:'POST',body});$("suporteMensagem").value='';
        const lista=JSON.parse(localStorage.getItem('CONectaSupportProtocols')||'[]');
        if(d.protocolo&&d.acesso_token){lista.push({protocolo:d.protocolo,token:d.acesso_token});localStorage.setItem('CONectaSupportProtocols',JSON.stringify(lista.slice(-20)));}
        toast(d.message);await carregarMeuSuporte();
    }catch(e){toast(e.message);}
}

function abrirNovaUBSForm(){$("formNovaUBS").classList.remove('hidden');}function fecharNovaUBSForm(){$("formNovaUBS").classList.add('hidden');}
async function salvarNovaUBS(){const body={id:$("novaUBSId").value.trim(),nome:$("novaUBSNome").value.trim(),endereco:$("novaUBSEndereco").value.trim(),telefone:$("novaUBSTelefone").value.trim(),horario:$("novaUBSHorario").value.trim(),usuario:$("novaUBSUsuario").value.trim(),senha:$("novaUBSSenha").value,especialidades:converterTextoLista($("novaUBSEspecialidades").value)};try{const d=await api('create_ubs',{method:'POST',body});ubsList.push(d.ubs);ubsList.sort((a,b)=>a.nome.localeCompare(b.nome));preencherSelectAdmin();$("adminUBSSelect").value=d.ubs.id;fecharNovaUBSForm();await carregarDadosAdmin(d.ubs.id);toast('UBS criada com sucesso.');}catch(e){toast(e.message);}}

function imprimirConsulta(id) {

    const consulta =
        appointments.find(
            item => item.id === id
        );

    if (!consulta) return;

    mostrarComprovante(consulta);

    window.print();

}

async function ativarLembrete(id) {

    const consulta =
        appointments.find(
            item => item.id === id
        );

    if (!consulta) return;

    const paciente =
        JSON.parse(
            sessionStorage.getItem(
                STORAGE_PATIENT
            ) || "null"
        );

    try {

        await api(
            "set_reminder",
            {
                method: "POST",
                body: {
                    id,
                    sus: paciente?.sus || "",
                    lembrete: true
                }
            }
        );

        consulta.lembrete =
            true;

        if ("Notification" in window) {

            const permissao =
                await Notification.requestPermission();

            if (
                permissao === "granted"
            ) {

                new Notification(
                    "Acessa+ Saúde",
                    {
                        body:
                            "Lembrete ativado para sua consulta em " +
                            formatarDataBR(
                                consulta.data
                            ) +
                            " às " +
                            consulta.horario
                    }
                );

            }

        }

        toast(
            "Lembrete ativado neste navegador."
        );

    } catch (error) {

        toast(error.message);

    }

}

function abrirLogin() {

    $("loginUsuario").value = "";
    $("loginSenha").value = "";

    $("loginModal").classList.remove("hidden");

    setTimeout(function () {
        $("loginUsuario").focus();
    }, 100);

}

function fecharLogin() {

    $("loginModal").classList.add("hidden");

}

async function realizarLogin() {

    const usuario =
        $("loginUsuario").value.trim();

    const senha =
        $("loginSenha").value;

    if (!usuario || !senha) {

        toast(
            "Informe usuário e senha."
        );

        return;

    }

    try {

        const data =
            await api(
                "login",
                {
                    method: "POST",
                    body: {
                        usuario,
                        senha
                    }
                }
            );

        adminSession =
            data.session;

        fecharLogin();

        abrirPainelAdmin();

    } catch (error) {

        toast(
            error.message
        );

    }

}

async function carregarDadosAdmin(id){try{const data=await api('admin_ubs_data',{params:{ubs_id:id}});const i=ubsList.findIndex(u=>u.id===id);if(i>=0)ubsList[i]=data.ubs;const u=data.ubs;[ ['editNomeUBS','nome'],['editEndereco','endereco'],['editTelefone','telefone'],['editHorario','horario'],['editUsuario','usuario'] ].forEach(([a,b])=>$(a).value=u[b]||'');$("editEspecialidades").value=(u.especialidades||[]).join('\n');$("editServicos").value=(u.servicos||[]).join('\n');$("editCampanhas").value=(u.campanhas||[]).join('\n');$("editDocumentos").value=(u.documentos||[]).join('\n');renderFuncionariosAdmin();}catch(e){toast(e.message);}}

async function abrirPainelAdmin() {

    if (!adminSession) {
        return;
    }

    $("adminModal")
        .classList
        .remove("hidden");

    $("seletorDesenvolvedor")
        .classList
        .toggle(
            "hidden",
            adminSession.tipo !==
            "desenvolvedor"
        );

    $("adminSuporteTab")
        .classList
        .toggle("hidden", adminSession.tipo !== "desenvolvedor");
    $("adminAuditoriaTab")
        .classList
        .toggle("hidden", adminSession.tipo !== "desenvolvedor");
    $("adminConfigTab")
        .classList
        .toggle("hidden", adminSession.tipo !== "desenvolvedor");

    $("adminTitulo").textContent =
        adminSession.tipo ===
        "desenvolvedor"
            ? "Painel do Desenvolvedor"
            : "Painel " +
              (
                getUBS(
                    adminSession.ubsId
                )?.nome ||
                "UBS"
              );

    preencherSelectAdmin();

    const id =
        adminSession.tipo ===
        "desenvolvedor"
            ? (
                ubsList[0]?.id ||
                ""
              )
            : adminSession.ubsId;

    if (id) {

        $("adminUBSSelect").value =
            id;

        await carregarDadosAdmin(
            id
        );

    }

    abrirAbaAdmin("dados");

}

function fecharAdmin() {

    $("adminModal").classList.add("hidden");

}

function logoutAdmin() {

    adminSession = null;
    fecharAdmin();
    definirModoUsuario(false);
    mostrarApenas("portalChooserSection");
    history.replaceState({}, "", location.pathname);
    toast("Sessão administrativa encerrada.");

}

function getUBS(id) {

    return ubsList.find(
        item => item.id === id
    );
}

function UBSAdminAtual() {

    if (
        !adminSession
    ) {
        return null;
    }

    if (
        adminSession.tipo === "desenvolvedor"
    ) {

        return getUBS(
            $("adminUBSSelect").value
        );

    }

    return getUBS(
        adminSession.ubsId
    );
}

function preencherSelectAdmin() {

    const select =
        $("adminUBSSelect");

    select.innerHTML = "";

    ubsList.forEach(function (ubs) {

        const option =
            document.createElement("option");

        option.value = ubs.id;

        option.textContent = ubs.nome;

        select.appendChild(option);

    });

}

async function trocarUBSAdmin() {

    await carregarDadosAdmin(
        $("adminUBSSelect").value
    );

    abrirAbaAdmin("dados");

}

function abrirAbaAdmin(aba) {

    $("adminDados")
        .classList.add("hidden");

    $("adminFuncionarios")
        .classList.add("hidden");

    $("adminConsultas")
        .classList.add("hidden");

    $("adminSuporte")
        .classList.add("hidden");

    $("adminConfig")
        .classList.add("hidden");

    $("adminAuditoria")
        .classList.add("hidden");

    if (aba === "dados") {

        $("adminDados")
            .classList.remove("hidden");

    }

    if (aba === "config" && adminSession?.tipo === "desenvolvedor") {
        $("adminConfig").classList.remove("hidden");
        carregarConfiguracaoAdmin();
        carregarPagamentosProfissionais();
    }

    if (aba === "funcionarios") {

        $("adminFuncionarios")
            .classList.remove("hidden");

        renderFuncionariosAdmin();

    }

    if (aba === "exames") {
        $("adminExames").classList.remove("hidden");
        carregarExamesAdmin();
    }

    if (aba === "consultas") {

        $("adminConsultas")
            .classList.remove("hidden");

        preencherFiltroEspecialidades();

        renderConsultasAdmin();

    }

    if (aba === "suporte" && adminSession?.tipo === "desenvolvedor") {
        $("adminSuporte").classList.remove("hidden");
        carregarSuporteAdmin();
    }
    if (aba === "auditoria" && adminSession?.tipo === "desenvolvedor") {
        $("adminAuditoria").classList.remove("hidden");
        carregarAuditoria();
    }

}

async function carregarSuporteAdmin() {
    if (!adminSession || adminSession.tipo !== "desenvolvedor") return;
    const container = $("listaSuporteAdmin");
    if (!container) return;
    try {
        const data = await api("admin_support_messages", {params:{status:$("filtroSuporte")?.value || ""}});
        const mensagens = data.messages || [];
        container.innerHTML = mensagens.length ? mensagens.map(m => `<article class="admin-appointment"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><strong>${escapeHTML(m.protocolo)} • ${escapeHTML(m.nome)}</strong><span class="status ${m.status === "resolvido" ? "atendido" : ""}">${escapeHTML(m.status)}</span></div><p>Telefone: ${escapeHTML(m.telefone)} • ${escapeHTML(m.criadoEm || "")}</p><p>${escapeHTML(m.mensagem)}</p>${m.resposta ? `<div class="info-box"><strong>Resposta:</strong> ${escapeHTML(m.resposta)}</div>` : ""}<textarea id="respostaSuporte_${Number(m.id)}" placeholder="Digite uma resposta para o paciente"></textarea><div class="admin-actions"><button class="btn secondary" onclick="alterarStatusSuporte(${Number(m.id)},'em_atendimento')">Em atendimento</button><button class="btn primary" onclick="alterarStatusSuporte(${Number(m.id)},'resolvido')">Responder e resolver</button></div></article>`).join("") : '<div class="info-box">Nenhuma mensagem de suporte recebida.</div>';
    } catch (error) {
        container.innerHTML = "";
        toast(error.message);
    }
}

async function alterarStatusSuporte(id, status) {
    try {
        const resposta = $("respostaSuporte_" + id)?.value.trim() || "";
        await api("admin_update_support", {method:"POST", body:{id, status, resposta}});
        await carregarSuporteAdmin();
        toast("Status da demanda atualizado.");
    } catch (error) {
        toast(error.message);
    }
}

/* ============================================================
   SALVAR INFORMAÇÕES UBS
   ============================================================ */

async function salvarInformacoesUBS() {

    const ubs =
        UBSAdminAtual();

    if (!ubs) {

        toast(
            "Nenhuma UBS selecionada."
        );

        return;

    }

    const payload = {

        ubs_id: ubs.id,

        nome:
            $("editNomeUBS")
                .value
                .trim(),

        endereco:
            $("editEndereco")
                .value
                .trim(),

        telefone:
            $("editTelefone")
                .value
                .trim(),

        horario:
            $("editHorario")
                .value
                .trim(),

        especialidades:
            converterTextoLista(
                $("editEspecialidades")
                    .value
            ),

        servicos:
            converterTextoLista(
                $("editServicos")
                    .value
            ),

        campanhas:
            converterTextoLista(
                $("editCampanhas")
                    .value
            ),

        documentos:
            converterTextoLista(
                $("editDocumentos")
                    .value
            ),

        usuario:
            $("editUsuario")
                .value
                .trim(),

        senha:
            $("editSenha")
                .value
                .trim()

    };

    if (!payload.usuario) {

        toast(
            "Usuário não pode ficar vazio."
        );

        return;

    }

    try {

        const data =
            await api(
                "update_ubs",
                {
                    method: "POST",
                    body: payload
                }
            );

        const idx =
            ubsList.findIndex(
                u => u.id === ubs.id
            );

        if (idx >= 0) {

            ubsList[idx] =
                data.ubs;

        }

        if (
            currentUBS &&
            currentUBS.id === ubs.id
        ) {

            currentUBS =
                data.ubs;

        }

        $("editSenha").value = "";

        renderUBS();

        toast(
            "Informações da UBS atualizadas!"
        );

    } catch (error) {

        toast(
            error.message
        );

    }

}

function converterTextoLista(texto) {

    return texto
        .split("\n")
        .map(item => item.trim())
        .filter(item => item.length > 0);

}

/* ============================================================
   FUNCIONÁRIOS
   ============================================================ */

function renderFuncionariosAdmin() {

    const container =
        $("listaFuncionariosAdmin");

    container.innerHTML = "";

    const ubs =
        UBSAdminAtual();

    if (!ubs) return;

    if (
        !ubs.funcionarios ||
        ubs.funcionarios.length === 0
    ) {

        container.innerHTML = `
            <div class="info-box">
                Nenhum funcionário cadastrado.
            </div>
        `;

        return;
    }

    ubs.funcionarios.forEach(function (funcionario) {

        const div =
            document.createElement("div");

        div.className = "employee";

        div.innerHTML = `

            <div class="employee-info">

                <strong>
                    ${escapeHTML(funcionario.nome)}
                </strong>

                <span>
                    ${escapeHTML(funcionario.cargo)}
                </span>

            </div>

            <div class="employee-actions">

                <button
                    class="btn secondary"
                    onclick="editarFuncionario('${funcionario.id}')">
                    Editar
                </button>

                <button
                    class="btn danger"
                    onclick="excluirFuncionario('${funcionario.id}')">
                    Excluir
                </button>

            </div>

        `;

        container.appendChild(div);

    });

}

function abrirFuncionarioForm() {

    editingEmployeeId = null;

    $("funcionarioId").value = "";

    $("funcionarioNome").value = "";

    $("funcionarioCargo").value = "";

    $("tituloFuncionarioForm")
        .textContent = "Novo funcionário";

    $("formFuncionario")
        .classList.remove("hidden");

}

function fecharFuncionarioForm() {

    editingEmployeeId = null;

    $("formFuncionario")
        .classList.add("hidden");

}

async function salvarFuncionario() {

    const ubs =
        UBSAdminAtual();

    if (!ubs) return;

    const nome =
        $("funcionarioNome")
            .value
            .trim();

    const cargo =
        $("funcionarioCargo")
            .value
            .trim();

    if (!nome || !cargo) {

        toast(
            "Preencha nome e cargo."
        );

        return;

    }

    try {

        const data =
            await api(
                "save_employee",
                {
                    method: "POST",
                    body: {
                        id:
                            editingEmployeeId ||
                            "",
                        ubs_id:
                            ubs.id,
                        nome,
                        cargo
                    }
                }
            );

        ubs.funcionarios =
            data.funcionarios;

        renderFuncionariosAdmin();

        fecharFuncionarioForm();

        atualizarDetalhesUBS();

        toast(
            "Funcionário salvo com sucesso."
        );

    } catch (error) {

        toast(
            error.message
        );

    }

}

function editarFuncionario(id) {

    const ubs =
        UBSAdminAtual();

    if (!ubs) return;

    const funcionario =
        ubs.funcionarios.find(
            item => item.id === id
        );

    if (!funcionario) return;

    editingEmployeeId = id;

    $("funcionarioId").value = id;

    $("funcionarioNome").value =
        funcionario.nome;

    $("funcionarioCargo").value =
        funcionario.cargo;

    $("tituloFuncionarioForm")
        .textContent =
        "Editar funcionário";

    $("formFuncionario")
        .classList.remove("hidden");

}

async function excluirFuncionario(id) {

    const ubs =
        UBSAdminAtual();

    if (!ubs) return;

    const funcionario =
        ubs.funcionarios.find(
            item => item.id === id
        );

    if (!funcionario) return;

    if (
        !confirm(
            "Deseja excluir " +
            funcionario.nome +
            "?"
        )
    ) {
        return;
    }

    try {

        const data =
            await api(
                "delete_employee",
                {
                    method: "POST",
                    body: {
                        id
                    }
                }
            );

        ubs.funcionarios =
            data.funcionarios;

        renderFuncionariosAdmin();

        atualizarDetalhesUBS();

        toast(
            "Funcionário excluído."
        );

    } catch (error) {

        toast(
            error.message
        );

    }

}

function atualizarDetalhesUBS() {

    if (!currentUBS) return;

    const ubs =
        getUBS(currentUBS.id);

    if (!ubs) return;

    currentUBS = ubs;

    $("detalheNomeUBS").textContent =
        ubs.nome;

    $("detalheEndereco").textContent =
        "📍 " + ubs.endereco;

    $("detalheTelefone").textContent =
        "☎ " + ubs.telefone;

    $("detalheHorario").textContent =
        "🕐 " + ubs.horario;

    preencherLista(
        "detalheServicos",
        ubs.servicos
    );

    preencherLista(
        "detalheCampanhas",
        ubs.campanhas
    );

    preencherLista(
        "detalheDocumentos",
        ubs.documentos
    );

    preencherLista(
        "detalheFuncionarios",
        ubs.funcionarios.map(
            f =>
                `${f.nome} — ${f.cargo}`
        )
    );

}

/* ============================================================
   CONSULTAS ADMIN
   ============================================================ */

function preencherFiltroEspecialidades() {

    const select =
        $("filtroEspecialidade");

    const ubs =
        UBSAdminAtual();

    if (!ubs) return;

    select.innerHTML =
        `<option value="">Todas as especialidades</option>`;

    ubs.especialidades.forEach(
        function (especialidade) {

            const option =
                document.createElement("option");

            option.value =
                especialidade;

            option.textContent =
                especialidade;

            select.appendChild(option);

        }
    );

}

async function renderConsultasAdmin() {

    const container =
        $("listaConsultasAdmin");

    if (!container) return;

    container.innerHTML =
        "<p>Carregando...</p>";

    const ubs =
        UBSAdminAtual();

    if (!ubs) return;

    try {

        const params = {
            ubs_id: ubs.id
        };

        if ($("filtroData").value) {

            params.data =
                $("filtroData").value;

        }

        if (
            $("filtroEspecialidade").value
        ) {

            params.especialidade =
                $("filtroEspecialidade")
                    .value;

        }

        const data =
            await api(
                "admin_appointments",
                {
                    params
                }
            );

        const lista =
            data.appointments || [];

        container.innerHTML = "";

        if (!lista.length) {

            container.innerHTML =
                '<div class="info-box">Nenhuma consulta encontrada.</div>';

            return;

        }

        lista.forEach(
            function (consulta) {

                const div =
                    document.createElement(
                        "div"
                    );

                div.className =
                    "admin-appointment";

                const cancelado =
                    consulta.status ===
                    "cancelado";

                div.innerHTML = `

                    <strong>
                        ${escapeHTML(
                            consulta.nome
                        )}
                    </strong>

                    <p>
                        SUS:
                        ${escapeHTML(
                            consulta.sus
                        )}
                    </p>

                    <p>
                        ${escapeHTML(
                            consulta.especialidade
                        )}
                    </p>

                    ${consulta.assunto ? `<p>📝 <strong>Assunto:</strong> ${escapeHTML(consulta.assunto)}</p>` : ""}

                    <p>
                        📅 ${formatarDataBR(
                            consulta.data
                        )}
                        às
                        ${escapeHTML(
                            consulta.horario
                        )}
                    </p>

                    <p>
                        Fila:
                        ${consulta.fila}º
                    </p>

                    <p>
                        Status:
                        <span class="status ${escapeHTML(consulta.status)}">
                            ${consulta.status === "atendido" ? "Realizada" : consulta.status === "faltou" ? "Não compareceu" : cancelado ? "Cancelada" : "Agendada"}
                        </span>
                    </p>
                    <div class="admin-actions">
                    ${!cancelado && consulta.status !== "atendido" ? `<button class="btn primary" onclick="atualizarStatusConsultaAdmin('${consulta.id}','atendido')">✓ Marcar como realizada</button>` : ""}
                    ${!cancelado && consulta.status !== "faltou" && consulta.status !== "atendido" ? `<button class="btn secondary" onclick="atualizarStatusConsultaAdmin('${consulta.id}','faltou')">Não compareceu</button>` : ""}
                    ${!cancelado && consulta.status !== "atendido" ? `<button class="btn danger" onclick="cancelarConsultaAdmin('${consulta.id}')">Cancelar</button>` : ""}
                    </div>

                `;

                container.appendChild(
                    div
                );

            }
        );

    } catch (error) {

        container.innerHTML = "";

        toast(
            error.message
        );

    }

}


async function atualizarStatusConsultaAdmin(id, status) {
    try {
        await api("admin_update_appointment_status", {method:"POST", body:{id,status}});
        await renderConsultasAdmin();
        toast(status === "atendido" ? "Consulta marcada como realizada." : "Status atualizado.");
    } catch(error) { toast(error.message); }
}

async function cancelarConsultaAdmin(id) {

    const consulta =
        appointments.find(
            item => item.id === id
        );

    if (
        !confirm(
            "Deseja cancelar esta consulta?"
        )
    ) {
        return;
    }

    try {

        await api(
            "cancel_appointment",
            {
                method: "POST",
                body: { id }
            }
        );

        await renderConsultasAdmin();

        toast(
            "Consulta cancelada pelo administrador."
        );

    } catch (error) {

        toast(
            error.message
        );

    }

}


function abrirExameForm() { $("formExame").classList.remove("hidden"); $("exameData").value = new Date().toISOString().slice(0,10); }
function fecharExameForm() { $("formExame").classList.add("hidden"); ["exameSus","exameNome","exameData","exameResultado","exameObservacoes"].forEach(id => $(id).value = ""); if ($("exameArquivo")) $("exameArquivo").value = ""; }
async function salvarResultadoExame() {
    const ubs = UBSAdminAtual();
    if (!ubs) return;
    try {
        const body = new FormData();
        body.append("ubs_id", ubs.id);
        body.append("sus", $("exameSus").value.trim());
        body.append("nome", $("exameNome").value.trim());
        body.append("data_exame", $("exameData").value);
        body.append("resultado", $("exameResultado").value.trim());
        body.append("observacoes", $("exameObservacoes").value.trim());
        if ($("exameArquivo")?.files?.[0]) body.append("anexo", $("exameArquivo").files[0]);
        await api("admin_save_exam", {method:"POST", body});
        fecharExameForm(); await carregarExamesAdmin(); toast("Resultado cadastrado com sucesso.");
    } catch(error) { toast(error.message); }
}
async function carregarExamesAdmin() {
    const ubs=UBSAdminAtual(), container=$("listaExamesAdmin"); if(!ubs||!container)return;
    container.innerHTML="<p>Carregando...</p>";
    try { const data=await api("admin_exams",{params:{ubs_id:ubs.id}}); const lista=data.exams||[]; container.innerHTML=lista.length?lista.map(e=>`<div class="admin-appointment"><strong>${escapeHTML(e.nome)}</strong><p>Paciente: ${escapeHTML(e.paciente)} • SUS: ${escapeHTML(e.sus)}</p><p>Data: ${formatarDataBR(e.data)}</p><p>${escapeHTML(e.resultado)}</p>${e.anexo ? `<a class="btn secondary exam-attachment" href="api.php?action=download_exam&id=${encodeURIComponent(e.id)}" target="_blank" rel="noopener">Abrir anexo: ${escapeHTML(e.anexoNome || "resultado")}</a>` : ""}</div>`).join(""):'<div class="info-box">Nenhum resultado cadastrado.</div>'; } catch(error){container.innerHTML="";toast(error.message);}
}

function formatarDataBR(data) {

    if (!data) return "";

    const partes =
        data.split("-");

    if (partes.length !== 3) {
        return data;
    }

    return (
        partes[2] +
        "/" +
        partes[1] +
        "/" +
        partes[0]
    );
}

/* ============================================================
   TELEFONE
   ============================================================ */

function aplicarMascaraTelefone() {

    const campo =
        $("telefonePaciente");

    campo.addEventListener(
        "input",
        function () {

            let valor =
                this.value.replace(/\D/g, "");

            valor =
                valor.substring(0, 11);

            if (valor.length <= 10) {

                valor =
                    valor.replace(
                        /^(\d{2})(\d)/,
                        "($1) $2"
                    );

                valor =
                    valor.replace(
                        /(\d{4})(\d)/,
                        "$1-$2"
                    );

            } else {

                valor =
                    valor.replace(
                        /^(\d{2})(\d)/,
                        "($1) $2"
                    );

                valor =
                    valor.replace(
                        /(\d{5})(\d)/,
                        "$1-$2"
                    );
            }

            this.value = valor;

        }
    );

}

/* ============================================================
   NAVEGAÇÃO
   ============================================================ */

function irDashboard() { voltarInicio(); }

function alternarMenuPaciente() {
    $("patientMenu").classList.toggle("hidden");
}

function fecharMenuPaciente() {
    $("patientMenu").classList.add("hidden");
}

function sairPaciente() {
    sessionStorage.removeItem(STORAGE_PATIENT);
    appointments = [];
    patientExams = [];
    currentUBS = null;
    currentSpecialty = null;
    currentDate = null;
    fecharMenuPaciente();
    definirModoUsuario(false);
    $("nomeTopo").textContent = "Olá!";
    $("nomeDashboard").textContent = "Paciente";
    mostrarApenas("cadastro");
    atualizarPasso(1);
    history.replaceState({}, "", location.pathname);
    toast("Você saiu da área do paciente UBS.");
}

function definirModoUsuario(logado) {
    const shell = $("appShell");
    if (shell) shell.classList.toggle("guest-mode", !logado);
    const profileMenu = $("patientProfileMenuWrap");
    if (profileMenu) profileMenu.classList.toggle("hidden", !logado);
    document.querySelectorAll(".admin-only-entry").forEach(element => element.classList.toggle("hidden", logado));
}

function voltarInicio() {
    const paciente = JSON.parse(sessionStorage.getItem(STORAGE_PATIENT) || "null");
    if (paciente) { atualizarDashboard(); mostrarApenas("dashboardSection"); }
    else { mostrarApenas("cadastro"); atualizarPasso(1); }
}

function atualizarPasso(numero) {

    const passos =
        document.querySelectorAll(".step");

    passos.forEach(function (step, index) {

        if (index < numero) {
            step.classList.add("active");
        } else {
            step.classList.remove("active");
        }

    });

}

/* ============================================================
   LEMBRETES
   ============================================================ */

function verificarLembretes() {

    const agora =
        new Date();

    const paciente =
        JSON.parse(
            sessionStorage.getItem(STORAGE_PATIENT)
        );

    if (!paciente) return;

    appointments.forEach(function (consulta) {

        if (
            consulta.status === "cancelado" ||
            !consulta.lembrete ||
            consulta.sus !== paciente.sus
        ) {
            return;
        }

        const dataConsulta =
            new Date(
                consulta.data +
                "T" +
                consulta.horario +
                ":00"
            );

        const diferenca =
            dataConsulta.getTime() -
            agora.getTime();

        /*
          Aproximadamente 24 horas antes.
        */

        const vinteQuatroHoras =
            24 * 60 * 60 * 1000;

        if (
            diferenca > 0 &&
            diferenca <= vinteQuatroHoras &&
            !consulta.notificado
        ) {

            if (
                "Notification" in window &&
                Notification.permission === "granted"
            ) {

                new Notification(
                    "Acessa+ Saúde - Lembrete",
                    {
                        body:
                            "Você tem consulta amanhã às " +
                            consulta.horario +
                            " na " +
                            consulta.ubsNome +
                            "."
                    }
                );

            }

            consulta.notificado = true;

            salvarConsultas();

        }

    });

}

/*
   Verifica os lembretes enquanto
   a página estiver aberta.
*/

setInterval(
    verificarLembretes,
    60000
);

function aplicarConfiguracaoApp(){
    const profissional=appConfig.modo==='profissional';
    document.body.dataset.appMode=appConfig.modo||'ubs';
    document.title=(appConfig.nomeExibicao||'Acessa+ Saúde')+' | Agendamento';
    if ($("modoSistemaLabel")) $("modoSistemaLabel").textContent=profissional?'Modo profissional particular / clínica':'Modo UBS / rede pública';
    if ($("ubsAtualDashboard")) $("ubsAtualDashboard").textContent=profissional?(appConfig.nomeExibicao||'Profissional'):'UBS de referência: Não informada';
    document.querySelectorAll('.brand img,.hero-logo,.registration-hero-logo').forEach(img=>{if(appConfig.logoArquivo)img.src='uploads/marca/'+appConfig.logoArquivo;});
}
async function carregarConfiguracaoAdmin(){try{const d=await api('get_app_config');const c=d.config||{};['modo','nomeExibicao','especialidade','registroProfissional','telefone','whatsapp','endereco','modalidade','valorConsulta','apresentacao','corPrimaria','corSecundaria'].forEach(k=>{const id={'nomeExibicao':'appNomeExibicao','registroProfissional':'appRegistro','valorConsulta':'appValor','corPrimaria':'appCorPrimaria','corSecundaria':'appCorSecundaria','modo':'appModo','especialidade':'appEspecialidade','telefone':'appTelefone','whatsapp':'appWhatsapp','endereco':'appEndereco','modalidade':'appModalidade','apresentacao':'appApresentacao'}[k];if(id&&$(id))$(id).value=c[k]??'';});}catch(e){toast(e.message);}}
async function salvarConfiguracaoApp(){const body={modo:$("appModo").value,nome_exibicao:$("appNomeExibicao").value.trim(),especialidade:$("appEspecialidade").value.trim(),registro_profissional:$("appRegistro").value.trim(),telefone:$("appTelefone").value.trim(),whatsapp:$("appWhatsapp").value.trim(),endereco:$("appEndereco").value.trim(),modalidade:$("appModalidade").value.trim(),valor_consulta:$("appValor").value,apresentacao:$("appApresentacao").value.trim(),cor_primaria:$("appCorPrimaria").value,cor_secundaria:$("appCorSecundaria").value};if(!body.nome_exibicao){toast('Informe o nome que será exibido.');return;}try{const d=await api('update_app_config',{method:'POST',body});appConfig=d.config||body;aplicarConfiguracaoApp();toast('Modo e personalização salvos.');}catch(e){toast(e.message);}}
async function carregarPagamentosProfissionais(){const box=$("listaPagamentosProfissionais");if(!box||adminSession?.tipo!=="desenvolvedor")return;try{const d=await api('admin_professional_payments');box.innerHTML=(d.payments||[]).length?d.payments.map(p=>`<article class="admin-appointment"><strong>${escapeHTML(p.profissional)} — ${escapeHTML(p.plano||'Plano')}</strong><p>R$ ${Number(p.valor).toFixed(2).replace('.',',')} • ${escapeHTML(p.status)} • ${escapeHTML(p.metodo||'')}</p>${p.status==='pendente'?`<button class="btn primary" onclick="aprovarPagamentoProfissional(${Number(p.id)})">Aprovar pagamento</button>`:''}</article>`).join(''):'<div class="info-box">Nenhum pagamento encontrado.</div>';}catch(e){box.innerHTML='<div class="info-box">'+escapeHTML(e.message)+'</div>';}}
async function aprovarPagamentoProfissional(id){try{await api('admin_update_professional_payment',{method:'POST',body:{id,status:'aprovado'}});toast('Pagamento aprovado e assinatura ativada.');carregarPagamentosProfissionais();}catch(e){toast(e.message);}}

async function carregarAuditoria() {
    if (!adminSession || adminSession.tipo !== "desenvolvedor") return;
    const box = $("listaAuditoria");
    try { const data = await api("admin_audit"); box.innerHTML = (data.events || []).length ? data.events.map(e => `<article class="admin-appointment"><strong>${escapeHTML(e.acao)}</strong><p>${escapeHTML(e.tipoUsuario || "")} • ${escapeHTML(e.criadoEm || "")}</p><p>${escapeHTML(e.entidade || "")} ${escapeHTML(e.entidadeId || "")} ${escapeHTML(e.detalhes || "")}</p></article>`).join("") : '<div class="info-box">Nenhum evento registrado.</div>'; } catch (error) { box.innerHTML = ""; toast(error.message); }
}

function entrarPortalUBS(){mostrarApenas('cadastro');definirModoUsuario(false);}
function voltarEntradaPortais(){history.replaceState({},'',location.pathname);mostrarApenas('portalChooserSection');}
function sairPaginaClinica(){const slug=window.publicClinicSlug;sessionStorage.removeItem('acessa_clinic_public_form');if(slug){carregarClinicaPublica(slug);}else{mostrarApenas('portalChooserSection');}}

function abrirPortalClinicaPorSlug(){const slug=prompt('Cole o link ou informe o identificador público da clínica:');if(!slug)return;const m=slug.match(/clinica=([^&]+)/);carregarClinicaPublica(decodeURIComponent(m?m[1]:slug.trim()));}
async function carregarClinicaPublica(slug){try{const d=await api('public_clinic',{params:{slug}});const c=d.clinic;window.publicClinicSlug=c.slug;mostrarApenas('publicClinicSection');$('publicClinicName').textContent=c.nome;$('publicClinicPresentation').textContent=c.apresentacao||'Agende sua consulta particular de forma simples.';$('publicClinicDetails').textContent=[c.especialidade,c.modalidade,c.endereco,c.whatsapp||c.telefone,c.horarioFuncionamento?'Horário: '+c.horarioFuncionamento:''].filter(Boolean).join(' • ');const notice=$('publicClinicNotice');if(notice)notice.textContent=c.avisoPublico||'';if(c.logoArquivo)$('publicClinicLogo').src='uploads/marca/'+c.logoArquivo;document.title=c.nome+' | Agendamento';const cleanUrl=location.pathname+'?clinica='+encodeURIComponent(c.slug);if(location.search!==('?clinica='+encodeURIComponent(c.slug)))history.replaceState({},'',cleanUrl);}catch(e){toast(e.message);mostrarApenas('portalChooserSection');}}
async function solicitarConsultaClinica(){const body={slug:window.publicClinicSlug,nome:$('publicPatientName').value.trim(),cpf:normalizarCpf($('publicPatientCpf').value),email:$('publicPatientEmail').value.trim(),telefone:$('publicPatientPhone').value.trim(),data_consulta:$('publicPatientDate').value,horario:$('publicPatientTime').value,assunto:$('publicPatientSubject').value.trim()};if(!body.nome||!validarCpf(body.cpf)||!body.email||!body.telefone||!body.data_consulta||!body.horario){toast('Preencha um CPF válido, nome, e-mail, celular, data e horário.');return;}try{const d=await api('public_clinic_book',{method:'POST',body});const c=d.confirmation||{};const box=$('publicClinicConfirmation');if(box){box.innerHTML=`<strong>Consulta confirmada</strong><p>${escapeHTML(d.message)}</p><p><b>Protocolo:</b> ${escapeHTML(c.id||d.appointment_id||'')}</p><p><b>Data:</b> ${formatarDataBR(c.date||body.data_consulta)} às ${escapeHTML(c.time||body.horario)}</p><p>A consulta já está confirmada no calendário da clínica. Você receberá a confirmação e um lembrete um dia antes.</p>`;box.classList.remove('hidden');}toast('Solicitação enviada. Guarde o protocolo.');$('publicPatientSubject').value='';}catch(e){toast(e.message);}}

function abrirLoginProfissional(){fecharLogin();$('professionalAuthModal').classList.remove('hidden');alternarAuthProfissional('login');$('professionalEmail').focus();}
function alternarAuthProfissional(mode){const signup=mode==='signup';$('professionalSignupFields').classList.toggle('hidden',!signup);$('professionalLoginButton').classList.toggle('hidden',signup);$('professionalSignupButton').classList.toggle('hidden',!signup);$('profAuthLoginTab').classList.toggle('active',!signup);$('profAuthSignupTab').classList.toggle('active',signup);}
function fecharLoginProfissional(){$('professionalAuthModal').classList.add('hidden');}
async function loginProfissional(){const email=$('professionalEmail').value.trim(),senha=$('professionalSenha').value;if(!email||!senha){toast('Informe e-mail e senha.');return;}try{const d=await api('professional_login',{method:'POST',body:{email,senha}});professionalSession=d.professional;fecharLoginProfissional();abrirPortalProfissional('dashboard');}catch(e){toast(e.message);}}
async function cadastrarProfissional(){const nome=$('professionalNome').value.trim(),email=$('professionalEmail').value.trim(),senha=$('professionalSenha').value;if(!nome||!email||senha.length<8){toast('Informe nome, e-mail e senha com pelo menos 8 caracteres.');return;}try{const d=await api('professional_register',{method:'POST',body:{nome,email,senha,especialidade:$('professionalEspecialidade').value.trim()}});professionalSession=d.professional;fecharLoginProfissional();toast('Conta profissional criada.');abrirPortalProfissional('dashboard');}catch(e){toast(e.message);}}
async function sairProfissional(){await api('professional_logout',{method:'POST'}).catch(()=>{});professionalSession=null;definirModoUsuario(false);mostrarApenas('portalChooserSection');history.replaceState({},'',location.pathname);toast('Sessão profissional encerrada.');}
function normalizarCpf(value){return String(value||'').replace(/\D/g,'').slice(0,11);}
function validarCpf(cpf){cpf=normalizarCpf(cpf);if(cpf.length!==11||/^(\d)\1{10}$/.test(cpf))return false;let sum=0;for(let t=9;t<11;t++){sum=0;for(let i=0;i<t;i++)sum+=Number(cpf[i])*((t+1)-i);let d=((sum*10)%11)%10;if(Number(cpf[t])!==d)return false;}return true;}
function formatarCpf(cpf){cpf=normalizarCpf(cpf);return cpf.length===11?cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/,'$1.$2.$3-$4'):cpf;}
function abrirPortalProfissional(tab='dashboard'){if(!professionalSession){abrirLoginProfissional();return;}mostrarApenas('professionalSection');document.querySelectorAll('.professional-tab').forEach(x=>x.classList.add('hidden'));const id='prof'+tab.charAt(0).toUpperCase()+tab.slice(1);if($(id))$(id).classList.remove('hidden');$('professionalNomeTopo').textContent=professionalSession.nome||'profissional';if(tab==='dashboard'||tab==='pacientes'||tab==='agenda'||tab==='prontuarios'||tab==='financeiro'||tab==='relacionamento')carregarPortalProfissional();if(tab==='relacionamento')setTimeout(carregarRelacionamentoProfissional,50);if(tab==='financeiro')setTimeout(carregarFinanceiroProfissional,50);if(tab==='marca')carregarPerfilProfissional();if(tab==='assinatura')carregarPlanosProfissional();}
async function carregarPortalProfissional(){try{const [p,a,m]=await Promise.all([api('professional_patients'),api('professional_appointments'),api('professional_me')]);professionalPatients=p.patients||[];$('profCountPatients').textContent=professionalPatients.length;$('profCountAppointments').textContent=(a.appointments||[]).length;professionalSession=m.professional;renderPatientsProfissional();renderAppointmentsProfissional(a.appointments||[]);renderCalendarioProfissional(a.appointments||[]);preencherSelectsProfissional();}catch(e){toast(e.message);}}
function renderPatientsProfissional(){
 const box=$('profPatientsList');
 box.innerHTML=professionalPatients.length?professionalPatients.map(p=>`<article class="admin-appointment"><strong>${escapeHTML(p.codigo||'PAC')} — ${escapeHTML(p.nome)}</strong><p>Contato: ${escapeHTML(p.email||'')} • ${escapeHTML(p.telefone||'')} • CPF: ${escapeHTML(formatarCpf(p.cpf||''))}</p><button class="btn secondary" onclick="abrirHistoricoPaciente(${Number(p.id)})">Histórico de consultas</button><button class="btn secondary" onclick="abrirPortalProfissional('prontuarios');setTimeout(()=>{if($('profRecordPaciente')){$('profRecordPaciente').value='${Number(p.id)}';carregarProntuariosProfissional();}},100)">Abrir prontuário</button></article>`).join(''):'<div class="info-box">Nenhum paciente vinculado. Use o Cartão SUS ou cadastre um paciente novo.</div>';
}
function statusConsultaLabel(s){return ({solicitada:'Solicitada',agendada:'Agendada',confirmada:'Confirmada',atendida:'Atendida',cancelada:'Cancelada',faltou:'Não compareceu'}[s]||s||'');}
function escapeAttr(v){return escapeHTML(v).replaceAll('`','&#096;');}
function renderAppointmentsProfissional(list){const box=$('profAppointmentsList');if(!box)return;const selected=agendaDiaAtual;const filtered=selected?list.filter(a=>a.data===selected):list;box.innerHTML=filtered.length?filtered.map(a=>{const status=a.status||'confirmada';return `<article class="admin-appointment clinic-appointment status-${escapeAttr(status)}"><div class="appointment-topline"><strong>${escapeHTML(a.paciente)}</strong><span class="status confirmado">${statusConsultaLabel(status)}</span></div><p>${escapeHTML(formatarDataBR(a.data))} às ${escapeHTML(a.horario)} • ${escapeHTML(a.telefone||'')}</p><p>${escapeHTML(a.assunto||'Sem assunto')}</p><p class="notification-planned">✓ Confirmação registrada • ✓ Lembrete programado para 1 dia antes</p><div class="appointment-actions"><button class="btn secondary" onclick="reagendarConsulta('${escapeAttr(a.id)}','${escapeAttr(a.data)}','${escapeAttr(a.horario)}')">Reagendar</button><button class="btn danger" onclick="cancelarConsultaProfissional('${escapeAttr(a.id)}')">Cancelar</button><button class="btn secondary" onclick="enviarConfirmacaoWhatsApp('${escapeAttr(a.id)}')">Confirmação WhatsApp</button></div></article>`;}).join(''):'<div class="info-box">Nenhuma consulta para este dia.</div>';window.professionalAppointments=list;}
function renderCalendarioProfissional(list){const box=$('professionalCalendar');if(!box)return;const y=agendaMesAtual.getFullYear(),m=agendaMesAtual.getMonth(),first=new Date(y,m,1),last=new Date(y,m+1,0),days=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];$('agendaMesTitulo').textContent=first.toLocaleDateString('pt-BR',{month:'long',year:'numeric'});let html=days.map(d=>`<div class="calendar-weekday">${d}</div>`).join('');for(let i=0;i<first.getDay();i++)html+='<div class="calendar-cell empty"></div>';for(let d=1;d<=last.getDate();d++){const iso=`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;const events=list.filter(a=>a.data===iso);const cls=events.some(a=>a.status==='cancelada')?'has-canceled':events.length?'has-confirmed':'';html+=`<button class="calendar-cell ${cls} ${agendaDiaAtual===iso?'selected':''}" onclick="selecionarDiaAgenda('${iso}')"><b>${d}</b><span>${events.length?events.length+' consulta'+(events.length>1?'s':''):''}</span>${events.slice(0,3).map(a=>`<i class="calendar-event ${a.status==='atendida'?'attended':a.status==='cancelada'?'canceled':'confirmed'}">${escapeHTML((a.horario||'').slice(0,5))} ${escapeHTML(a.paciente||'')}</i>`).join('')}</button>`;}box.innerHTML=html;const info=$('agendaDiaSelecionado');if(info)info.innerHTML=agendaDiaAtual?`<strong>Dia selecionado:</strong> ${formatarDataBR(agendaDiaAtual)}. Clique em uma data para ver as consultas.`:'<strong>Selecione um dia</strong> para filtrar as consultas abaixo.';}
function mudarMesAgenda(delta){agendaMesAtual.setMonth(agendaMesAtual.getMonth()+delta);renderCalendarioProfissional(window.professionalAppointments||[]);renderAppointmentsProfissional(window.professionalAppointments||[]);}
function selecionarDiaAgenda(iso){agendaDiaAtual=agendaDiaAtual===iso?null:iso;const a=window.professionalAppointments||[];renderCalendarioProfissional(a);renderAppointmentsProfissional(a);if($('profAgendaData')&&iso)$('profAgendaData').value=iso;}

async function salvarStatusConsulta(id){const a=(window.professionalAppointments||[]).find(x=>x.id===id);if(!a)return;try{await api('professional_update_appointment',{method:'POST',body:{id,status:$('status-'+id).value,forma_pagamento:$('method-'+id).value,pagamento_status:$('payment-'+id).value,data_consulta:a.data,horario:a.horario}});toast('Consulta atualizada.');carregarAgendaProfissional();}catch(e){toast(e.message);}}
async function cancelarConsultaProfissional(id){const motivo=prompt('Informe o motivo do cancelamento:', '');if(motivo===null)return;const a=(window.professionalAppointments||[]).find(x=>x.id===id);try{await api('professional_update_appointment',{method:'POST',body:{id,status:'cancelada',cancelamento_motivo:motivo,data_consulta:a.data,horario:a.horario}});toast('Consulta cancelada.');carregarAgendaProfissional();}catch(e){toast(e.message);}}
async function reagendarConsulta(id,data,horario){const novaData=prompt('Nova data (AAAA-MM-DD):',data);if(novaData===null)return;const novoHorario=prompt('Novo horário (HH:MM):',horario.slice(0,5));if(novoHorario===null)return;const a=(window.professionalAppointments||[]).find(x=>x.id===id);try{await api('professional_update_appointment',{method:'POST',body:{id,status:'agendada',data_consulta:novaData,horario:novoHorario,forma_pagamento:a.formaPagamento||'',pagamento_status:a.pagamentoStatus||''}});toast('Consulta reagendada.');carregarAgendaProfissional();}catch(e){toast(e.message);}}
function renderFinanceAppointments(list){const box=$('financeAppointmentsList');if(!box)return;const open=list.filter(a=>!['cancelada','faltou'].includes(a.status));box.innerHTML=open.length?`<div class="admin-section-title"><h3>Consultas para lançar pagamento</h3></div>`+open.map(a=>`<article class="admin-appointment"><strong>${escapeHTML(a.paciente)}</strong><p>${escapeHTML(formatarDataBR(a.data))} às ${escapeHTML(a.horario)} • ${escapeHTML(a.pagamentoStatus==='pago'?'Pagamento finalizado':'Pagamento pendente')}</p>${a.pagamentoStatus==='pago'?'<small>Lançamento financeiro finalizado e bloqueado.</small>':`<button class="btn primary" onclick="abrirPagamento('${escapeAttr(a.id)}',${Number(a.patientId||0)})">Registrar pagamento</button>`}</article>`).join(''):'<div class="info-box">Nenhuma consulta disponível para lançamento financeiro.</div>';}
function dadosReciboConsulta(id){return (window.professionalAppointments||[]).find(x=>x.id===id);}
function reciboHTML(a){return `<html><head><title>Recibo ${escapeHTML(a.id)}</title><style>body{font-family:Arial;color:#16474b;padding:35px;max-width:720px;margin:auto}h1{color:#087c7f}hr{border:0;border-top:1px solid #ddd}.clinic{background:#f3fafa;padding:16px;border-radius:10px;margin-bottom:15px}.clinic strong{font-size:18px}.row{padding:9px 0;border-bottom:1px solid #eee;display:flex;justify-content:space-between;gap:20px}</style></head><body><h1>Recibo de atendimento</h1><div class="clinic"><strong>${escapeHTML(a.clinicaNome||professionalSession?.nome||'Clínica')}</strong><div>CNPJ: ${escapeHTML(a.clinicaCnpj||'Não informado')}</div><div>Endereço: ${escapeHTML(a.clinicaEndereco||'Não informado')}</div><div>Telefone: ${escapeHTML(a.clinicaTelefone||'Não informado')}</div></div><hr><div class="row"><b>Paciente</b><span>${escapeHTML(a.codigo||'PAC')} — ${escapeHTML(a.paciente)}</span></div><div class="row"><b>Protocolo</b><span>${escapeHTML(a.id)}</span></div><div class="row"><b>Data e horário</b><span>${escapeHTML(formatarDataBR(a.data))} às ${escapeHTML(a.horario)}</span></div><div class="row"><b>Valor</b><span>R$ ${Number(a.reciboValor??a.valor??0).toFixed(2).replace('.',',')}</span></div><div class="row"><b>Pagamento</b><span>${escapeHTML(a.pagamentoStatus==='pago'?'Pago':a.pagamentoStatus==='dispensado'?'Dispensado':'Pendente')} • ${escapeHTML(a.formaPagamento||'Não informado')}</span></div><p>Documento gerado pelo sistema para impressão ou envio ao paciente.</p></body></html>`;}
function gerarReciboConsulta(id){const a=dadosReciboConsulta(id);if(!a)return;const w=window.open('','_blank');if(!w){toast('Permita pop-ups para gerar o recibo.');return;}w.document.write(reciboHTML(a));w.document.close();w.focus();setTimeout(()=>w.print(),250);}
function enviarReciboWhatsApp(id){const a=dadosReciboConsulta(id);if(!a)return;const phone=String(a.telefone||'').replace(/\D/g,'');if(!phone){toast('Este paciente não possui celular cadastrado.');return;}const msg=`Olá, ${a.paciente}! Segue o recibo da consulta ${a.id}. ${a.clinicaNome||professionalSession?.nome||'Clínica'} | CNPJ: ${a.clinicaCnpj||'Não informado'} | Endereço: ${a.clinicaEndereco||'Não informado'} | Telefone: ${a.clinicaTelefone||'Não informado'}. Data: ${formatarDataBR(a.data)} às ${a.horario}. Valor: R$ ${Number(a.reciboValor??a.valor??0).toFixed(2).replace('.',',')}. Pagamento: ${a.pagamentoStatus==='pago'?'Pago':a.pagamentoStatus==='dispensado'?'Dispensado':'Pendente'}.`;window.open('https://wa.me/55'+phone+'?text='+encodeURIComponent(msg),'_blank');}

async function cadastrarPacienteNovo(){
    const body={nome:$('novoPacienteNome').value.trim(),telefone:$('novoPacienteTelefone').value.trim(),cpf:normalizarCpf($('novoPacienteCpf').value),email:$('novoPacienteEmail').value.trim(),sus:$('novoPacienteSus').value.trim()};
    if(!body.nome){toast('Informe o nome do paciente.');return;}
    try{const d=await api('professional_create_patient',{method:'POST',body});toast(`Paciente cadastrado: ${d.patient.codigo}`);['novoPacienteNome','novoPacienteTelefone','novoPacienteCpf','novoPacienteEmail','novoPacienteSus'].forEach(id=>$(id).value='');await carregarPortalProfissional();}catch(e){toast(e.message);}
}
async function abrirHistoricoPaciente(id){
    const box=$('profPatientHistory');if(!box)return;
    try{
        const d=await api('professional_patient_history',{params:{patient_id:id}});const p=d.patient||{};
        box.classList.remove('hidden');
        box.innerHTML=`<div class="info-box"><strong>${escapeHTML(p.codigo||'PAC')} — ${escapeHTML(p.nome||'')}</strong><p>${escapeHTML(p.telefone||'')} • ${escapeHTML(p.email||'')}</p></div>`+
        `<h4>Todas as consultas deste paciente</h4>`+
        ((d.appointments||[]).filter(a=>a.consultaId).map(a=>`<article class="admin-appointment"><strong>${escapeHTML(formatarDataBR(a.data))} às ${escapeHTML(a.horario)}</strong><p>${escapeHTML(a.assunto||'Sem assunto')} • ${statusConsultaLabel(a.status)}</p><p>Status da consulta: ${statusConsultaLabel(a.status)}</p></article>`).join('')||'<div class="info-box">Nenhuma consulta registrada.</div>');
    }catch(e){toast(e.message);}
}
async function gerarRelatorioDiario(){const date=$('finRelatorioData')?.value;if(!date){toast('Escolha a data do relatório.');return;}try{const d=await api('professional_daily_report',{params:{data:date}});const rows=(d.payments||[]).map(p=>`<tr><td>${escapeHTML(p.data)}</td><td>${escapeHTML(p.paciente)}</td><td>${escapeHTML(p.formaPagamento)}</td><td>R$ ${Number(p.valor).toFixed(2).replace('.',',')}</td></tr>`).join('');const formas=Object.entries(d.porForma||{}).map(([k,v])=>`<li>${escapeHTML(k)}: R$ ${Number(v).toFixed(2).replace('.',',')}</li>`).join('');const w=window.open('','_blank');if(!w){toast('Permita pop-ups para gerar o relatório.');return;}w.document.write(`<html><head><title>Relatório financeiro ${date}</title><style>body{font-family:Arial;color:#16474b;padding:30px}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{padding:9px;border-bottom:1px solid #ddd;text-align:left}h1{color:#087c7f}</style></head><body><h1>Relatório financeiro diário</h1><p>Data: ${formatarDataBR(date)} • Clínica: ${escapeHTML(professionalSession?.nome||'')}</p><h2>Total recebido: R$ ${Number(d.total||0).toFixed(2).replace('.',',')}</h2><ul>${formas||'<li>Nenhum recebimento</li>'}</ul><table><thead><tr><th>Horário</th><th>Paciente</th><th>Forma</th><th>Valor</th></tr></thead><tbody>${rows||'<tr><td colspan=4>Nenhum pagamento registrado.</td></tr>'}</tbody></table></body></html>`);w.document.close();w.focus();setTimeout(()=>w.print(),250);}catch(e){toast(e.message);}}
function enviarConfirmacaoWhatsApp(id){const a=dadosReciboConsulta(id);if(!a)return;const phone=String(a.telefone||'').replace(/\D/g,'');if(!phone){toast('Este paciente não possui celular cadastrado.');return;}const msg=`Olá, ${a.paciente}! Sua consulta na ${a.clinicaNome||professionalSession?.nome||'clínica'} está confirmada para ${formatarDataBR(a.data)} às ${a.horario}.`;window.open('https://wa.me/55'+phone+'?text='+encodeURIComponent(msg),'_blank');}
function abrirPagamento(consultaId,patientId){
    $('pagConsultaId').value=consultaId;$('pagPacienteId').value=patientId;$('pagValor').value='';
    $('pagForma').value='pix';atualizarCamposCartao();$('paymentModal').classList.remove('hidden');
}
function fecharPagamento(){$('paymentModal').classList.add('hidden');}
function atualizarCamposCartao(){
    const cartao=$('pagForma').value==='cartao';$('pagCartaoFields').classList.toggle('hidden',!cartao);
    const tipo=$('pagTipoCartao').value;const sel=$('pagParcelas');
    sel.innerHTML='';for(let i=1;i<=12;i++)sel.innerHTML+=`<option value="${i}" ${tipo==='debito'&&i===1?'selected':''}>${i}x</option>`;
    sel.disabled=tipo==='debito';
}
async function salvarPagamentoFormulario(){
    const forma=$('pagForma').value,tipo=forma==='cartao'?$('pagTipoCartao').value:'',parcelas=forma==='cartao'?Number($('pagParcelas').value):null;
    const body={consulta_id:$('pagConsultaId').value,patient_id:Number($('pagPacienteId').value),valor:$('pagValor').value.replace(',','.'),forma_pagamento:forma,tipo_cartao:tipo,parcelas};
    if(!body.valor||Number(body.valor)<=0){toast('Informe o valor recebido.');return;}
    try{await api('professional_register_payment',{method:'POST',body});fecharPagamento();toast('Pagamento registrado com segurança. O lançamento não pode ser alterado.');await carregarPortalProfissional();await carregarFinanceiroProfissional();}catch(e){toast(e.message);}
}
async function registrarPagamento(body){
    try{await api('professional_register_payment',{method:'POST',body});toast('Pagamento registrado com segurança. O lançamento não pode ser alterado.');carregarPortalProfissional();carregarFinanceiroProfissional();}catch(e){toast(e.message);}
}
async function carregarFinanceiroProfissional(){
    if($('finRelatorioData')&&!$('finRelatorioData').value)$('finRelatorioData').value=new Date().toISOString().slice(0,10);
    try{
        const d=await api('professional_finance',{params:{busca:$('finBusca')?.value.trim()||'',inicio:$('finInicio')?.value||'',fim:$('finFim')?.value||'',forma_pagamento:$('finForma')?.value||''}});
        $('finTotal').textContent=Number(d.total||0).toFixed(2).replace('.',',');
        renderFinanceAppointments(window.professionalAppointments||[]);$('financeList').innerHTML=(d.payments||[]).map(p=>`<article class="admin-appointment"><strong>${escapeHTML(p.codigo||'PAC')} — ${escapeHTML(p.paciente)}</strong><p>${escapeHTML(p.data)} • R$ ${Number(p.valor).toFixed(2).replace('.',',')} • ${escapeHTML(p.formaPagamento)}</p><p>${p.tipoCartao?escapeHTML(p.tipoCartao)+' • '+escapeHTML(p.parcelas||1)+'x':''}</p><small>Lançamento #${p.id} — somente consulta, sem edição.</small></article>`).join('')||'<div class="info-box">Nenhum pagamento encontrado.</div>';
    }catch(e){toast(e.message);}
}
let relationshipPatients=[];
let mensagemPosVenda='Olá, {nome}! Aqui é da clínica {clinica}. Gostaríamos de saber como você está após sua consulta. Podemos ajudar em algo?';
async function carregarRelacionamentoProfissional(){try{const d=await api('professional_relationships');relationshipPatients=d.patients||[];const me=await api('professional_me');mensagemPosVenda=me.professional?.mensagemPosVenda||mensagemPosVenda;if($('relMensagemPadrao'))$('relMensagemPadrao').value=mensagemPosVenda;renderRelacionamentoProfissional(relationshipPatients);}catch(e){toast(e.message);}}
async function salvarMensagemPosVenda(){const msg=$('relMensagemPadrao')?.value.trim()||'';if(!msg){toast('Digite uma mensagem padrão.');return;}try{await api('professional_update_relationship_message',{method:'POST',body:{mensagem_pos_venda:msg}});mensagemPosVenda=msg;if($('relMensagemStatus'))$('relMensagemStatus').textContent='Mensagem salva com sucesso.';toast('Mensagem de pós-venda salva.');}catch(e){toast(e.message);}}
function filtrarRelacionamentoProfissional(){const q=($('relBusca')?.value||'').toLowerCase();renderRelacionamentoProfissional(relationshipPatients.filter(p=>(p.nome||'').toLowerCase().includes(q)||(p.cpf||'').includes(normalizarCpf(q))));}
function renderRelacionamentoProfissional(list){const box=$('relationshipList');if(!box)return;const total=list.length,contacted=list.filter(p=>p.ultimoContato).length;const sum=$('relacionamentoResumo');if(sum)sum.innerHTML=`<div><b>${total}</b><span>Pacientes na base</span></div><div><b>${contacted}</b><span>Já contatados</span></div><div><b>${total-contacted}</b><span>Sem contato pós-venda</span></div>`;box.innerHTML=list.length?list.map(p=>{const phone=String(p.telefone||'').replace(/\D/g,'');const msg=mensagemPosVenda.replaceAll('{nome}',p.nome||'paciente').replaceAll('{clinica}',professionalSession?.nome||'nossa clínica');const wa=phone?`https://wa.me/55${phone}?text=${encodeURIComponent(msg)}`:'#';return `<article class="relationship-card"><div><strong>${escapeHTML(p.nome)}</strong><p>CPF: ${escapeHTML(formatarCpf(p.cpf||''))} • ${escapeHTML(p.telefone||'Sem telefone')}</p><small>${p.ultimoContato?'Última mensagem: '+escapeHTML(p.ultimoContato):'Nenhuma mensagem registrada'} • ${Number(p.totalContatos||0)} contato(s)</small></div><a class="btn primary" href="${wa}" target="_blank" rel="noopener" onclick="registrarContatoWhatsApp(${Number(p.id)},${JSON.stringify(msg).replace(/"/g,'&quot;')})">Abrir WhatsApp</a></article>`;}).join(''):'<div class="info-box">Nenhum paciente encontrado.</div>';}
async function registrarContatoWhatsApp(patientId,message){try{await api('professional_log_relationship',{method:'POST',body:{patient_id:patientId,mensagem:message}});setTimeout(carregarRelacionamentoProfissional,500);}catch(e){toast(e.message);}}
function preencherSelectsProfissional(){['profAgendaPaciente','profRecordPaciente'].forEach(id=>{const s=$(id);if(!s)return;s.innerHTML='<option value="">Selecione o paciente</option>'+professionalPatients.map(p=>`<option value="${p.id}">${escapeHTML(p.codigo||'PAC')} — ${escapeHTML(p.nome)}</option>`).join('');});}
async function vincularPacienteProfissional(){const sus=$('profPatientSus').value.trim();if(!sus){toast('Informe o Cartão SUS.');return;}try{await api('professional_link_patient',{method:'POST',body:{sus}});$('profPatientSus').value='';toast('Paciente vinculado.');carregarPortalProfissional();}catch(e){toast(e.message);}}
async function criarConsultaProfissional(){const body={patient_id:Number($('profAgendaPaciente').value),data_consulta:$('profAgendaData').value,horario:$('profAgendaHorario').value,assunto:$('profAgendaAssunto').value.trim()};if(!body.patient_id||!body.data_consulta||!body.horario){toast('Informe paciente, data e horário.');return;}try{await api('professional_create_appointment',{method:'POST',body});toast('Consulta confirmada e adicionada ao calendário.');$('profAgendaAssunto').value='';carregarPortalProfissional();}catch(e){toast(e.message);}}
async function carregarAgendaProfissional(){await carregarPortalProfissional();}
function preencherCadastroPacienteProfissional(){const id=Number($('profRecordPaciente').value);const p=professionalPatients.find(x=>Number(x.id)===id);if(!p)return;const map={nome:'profPatientNome',telefone:'profPatientTelefone',email:'profPatientEmail',nascimento:'profPatientNascimento',endereco:'profPatientEndereco',condicoesSaude:'profPatientCondicoes',alergias:'profPatientAlergias',medicamentos:'profPatientMedicamentos',informacoesAdicionais:'profPatientAdicionais'};Object.entries(map).forEach(([k,field])=>{if($(field))$(field).value=p[k]||'';});}
async function salvarCadastroPacienteProfissional(){const id=Number($('profRecordPaciente').value);if(!id){toast('Selecione um paciente agendado.');return;}const body={patient_id:id,nome:$('profPatientNome').value.trim(),telefone:$('profPatientTelefone').value.trim(),email:$('profPatientEmail').value.trim(),data_nascimento:$('profPatientNascimento').value,endereco:$('profPatientEndereco').value.trim(),condicoes_saude:$('profPatientCondicoes').value.trim(),alergias:$('profPatientAlergias').value.trim(),medicamentos:$('profPatientMedicamentos').value.trim(),informacoes_adicionais:$('profPatientAdicionais').value.trim()};try{await api('professional_update_patient',{method:'POST',body});toast('Cadastro do paciente atualizado.');carregarPortalProfissional();}catch(e){toast(e.message);}}

async function salvarProntuarioProfissional(){const body={patient_id:Number($('profRecordPaciente').value),tipo:$('profRecordTipo').value.trim()||'evolução',conteudo:$('profRecordContent').value.trim()};if(!body.patient_id||!body.conteudo){toast('Selecione o paciente e escreva o registro.');return;}try{await api('professional_create_record',{method:'POST',body});$('profRecordContent').value='';toast('Registro salvo com acesso restrito.');carregarProntuariosProfissional();}catch(e){toast(e.message);}}
async function carregarProntuariosProfissional(){const patient=Number($('profRecordPaciente').value);const box=$('profRecordsList');if(!patient){box.innerHTML='';return;}try{const d=await api('professional_records',{params:{patient_id:patient}});box.innerHTML=(d.records||[]).map(r=>`<article class="admin-appointment"><strong>${escapeHTML(r.tipo)}</strong><p>${escapeHTML(r.criadoEm)}</p><div>${escapeHTML(r.conteudo)}</div></article>`).join('')||'<div class="info-box">Nenhum registro.</div>';}catch(e){toast(e.message);}}
async function carregarPerfilProfissional(){try{const d=await api('professional_me'),p=d.professional||{};const map={nome:'profSetNome',cnpj:'profSetCnpj',especialidade:'profSetEspecialidade',registroProfissional:'profSetRegistro',telefone:'profSetTelefone',whatsapp:'profSetWhatsapp',modalidade:'profSetModalidade',horarioFuncionamento:'profSetHorario',valorConsulta:'profSetValor',endereco:'profSetEndereco',apresentacao:'profSetApresentacao',avisoPublico:'profSetAviso',mensagemPosVenda:'relMensagemPadrao',corPrimaria:'profSetCor'};Object.entries(map).forEach(([k,id])=>{if($(id))$(id).value=p[k]??'';});professionalSession={...professionalSession,nome:p.nome,slug:p.slug};const link=$('profPublicLink');if(link&&p.slug){link.href=location.origin+location.pathname+'?clinica='+encodeURIComponent(p.slug);link.textContent=link.href;}}catch(e){toast(e.message);}}
async function salvarPerfilProfissional(){const body={nome:$('profSetNome').value.trim(),cnpj:$('profSetCnpj').value.trim(),especialidade:$('profSetEspecialidade').value.trim(),registro_profissional:$('profSetRegistro').value.trim(),telefone:$('profSetTelefone').value.trim(),whatsapp:$('profSetWhatsapp').value.trim(),modalidade:$('profSetModalidade').value.trim(),horario_funcionamento:$('profSetHorario').value.trim(),valor_consulta:$('profSetValor').value,endereco:$('profSetEndereco').value.trim(),apresentacao:$('profSetApresentacao').value.trim(),aviso_publico:$('profSetAviso').value.trim(),mensagem_pos_venda:mensagemPosVenda,cor_primaria:$('profSetCor').value};try{const d=await api('professional_update_settings',{method:'POST',body});professionalSession.nome=d.professional.nome;const file=$('profLogoFile').files[0];if(file){const form=new FormData();form.append('logo',file);await api('professional_upload_logo',{method:'POST',body:form});}toast('Personalização, horário e aviso salvos.');carregarPerfilProfissional();}catch(e){toast(e.message);}}
let planoPagamentoSelecionado=null;
let agendaMesAtual=new Date();
let agendaDiaAtual=null;
async function carregarPlanosProfissional(){
    try{
        const [d,sub]=await Promise.all([api('subscription_plans'),api('professional_subscription')]);
        $('profPlansList').innerHTML=(d.plans||[]).map(p=>`<div class="info-box plan-card"><h3>${escapeHTML(p.nome)}</h3><p class="plan-price">R$ ${Number(p.valorMensal).toFixed(2).replace('.',',')} <small>/mês</small></p><p>Até ${Number(p.limitePacientes)} pacientes</p><div class="payment-options"><button class="btn secondary" onclick="assinarPlanoProfissional(${Number(p.id)},'pix')">PIX</button><button class="btn secondary" onclick="assinarPlanoProfissional(${Number(p.id)},'cartao')">Cartão</button><button class="btn secondary" onclick="assinarPlanoProfissional(${Number(p.id)},'boleto')">Boleto</button></div></div>`).join('');
        const active=sub.subscription;
        const statusTexto=active?(active.status==='ativa'?`Ativa — ${active.plano} (${Number(active.limitePacientes||0)} pacientes)`:(active.status==='inadimplente'?'Pagamento não confirmado':'Pendente')): 'Gratuito — 5 pacientes';['profSubscriptionStatus','profSubscriptionStatusResumo'].forEach(id=>{if($(id))$(id).textContent=statusTexto;});
    }catch(e){toast(e.message);}
}
async function assinarPlanoProfissional(id,metodo='pix'){
    try{
        const d=await api('professional_start_subscription',{method:'POST',body:{plano_id:id,metodo}});
        ['profSubscriptionStatus','profSubscriptionStatusResumo'].forEach(id=>{if($(id))$(id).textContent='Pendente';});
        toast(d.message+` Forma: ${metodo.toUpperCase()}.`);
    }catch(e){toast(e.message);}
}
async function cancelarAssinaturaProfissional(){if(!confirm('Cancelar a assinatura atual?'))return;try{await api('professional_cancel_subscription',{method:'POST'});['profSubscriptionStatus','profSubscriptionStatusResumo'].forEach(id=>{if($(id))$(id).textContent='Cancelada';});toast('Assinatura cancelada.');}catch(e){toast(e.message);}}

/* ============================================================
   TECLA ENTER NO LOGIN
   ============================================================ */

document.addEventListener(
    "keydown",
    function (event) {

        if (
            event.key === "Enter" &&
            !$("loginModal").classList.contains("hidden")
        ) {

            realizarLogin();

        }

    }
);

/* ============================================================
   FIM
   ============================================================ */
