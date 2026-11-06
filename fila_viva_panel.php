<div id="adminFilaViva" class="admin-tab hidden">
    <div class="admin-section-title fv-heading">
        <div>
            <span class="eyebrow">OPERAÇÃO MUNICIPAL • AVALIAÇÃO DE PROJETO</span>
            <h3>Fila Viva — Acessa+ Saúde</h3>
            <p class="subtitle">Encaminhamentos, fila, agenda, confirmações e reaproveitamento controlado de vagas. Módulo preparado para avaliação pela Secretaria de Saúde de Triunfo/PE; não representa integração ou implantação oficial.</p>
        </div>
        <button type="button" class="btn secondary" onclick="carregarFilaViva()">Atualizar painel</button>
    </div>

    <div class="info-box fv-safety-note">
        <strong>Governança e limites:</strong> prioridade clínica é validada por uma conta da Secretaria formalmente autorizada. A plataforma não diagnostica, não calcula prioridade e não decide a ordem clínica. SMS/WhatsApp automático e integração e-SUS APS ainda dependem de autorização, provedor e configuração externa.
    </div>

    <section class="fv-filter-card" aria-label="Filtros do painel Fila Viva">
        <div class="fv-filters">
            <div id="fvFilterUnitField"><label for="fvFilterUnit">Unidade</label><select id="fvFilterUnit" onchange="carregarFilaViva()"><option value="">Todas as unidades do escopo</option></select></div>
            <div><label for="fvFilterSpecialty">Especialidade</label><select id="fvFilterSpecialty" onchange="carregarFilaViva()"><option value="">Todas</option></select></div>
            <div><label for="fvStart">De</label><input id="fvStart" type="date" onchange="carregarFilaViva()"></div>
            <div><label for="fvEnd">Até</label><input id="fvEnd" type="date" onchange="carregarFilaViva()"></div>
            <button type="button" class="btn primary fv-filter-button" onclick="carregarFilaViva()">Aplicar filtros</button>
        </div>
    </section>

    <div class="fv-metrics-grid" aria-live="polite">
        <article class="fv-metric"><span>Encaminhamentos</span><strong id="fvMetricReferrals">—</strong><small>no período</small></article>
        <article class="fv-metric"><span>Na fila</span><strong id="fvMetricWaiting">—</strong><small>solicitações pendentes</small></article>
        <article class="fv-metric"><span>Aguardando validação</span><strong id="fvMetricValidation">—</strong><small>decisão humana necessária</small></article>
        <article class="fv-metric"><span>Espera média / mediana</span><strong id="fvMetricWait">—</strong><small id="fvMetricWaitSample">sem amostra suficiente</small></article>
        <article class="fv-metric"><span>Agendadas / realizadas</span><strong id="fvMetricAppointments">—</strong><small>no período</small></article>
        <article class="fv-metric"><span>Faltas registradas</span><strong id="fvMetricAbsences">—</strong><small id="fvMetricNoShowRate">taxa indisponível</small></article>
        <article class="fv-metric"><span>Confirmações</span><strong id="fvMetricConfirmations">—</strong><small id="fvMetricConfirmationRate">taxa indisponível</small></article>
        <article class="fv-metric"><span>Vagas liberadas / reutilizadas</span><strong id="fvMetricVacancies">—</strong><small id="fvMetricReused">aguardando dados</small></article>
        <article class="fv-metric"><span>Ocupação estimada</span><strong id="fvMetricOccupancy">—</strong><small>limite diário cadastrado; não usa agenda por profissional</small></article>
    </div>

    <section class="fv-monthly-section">
        <div class="fv-subheading"><div><h4>Evolução mensal</h4><p>Encaminhamentos registrados e consultas realizadas, conforme dados disponíveis.</p></div></div>
        <div id="fvMonthly" class="fv-monthly" aria-live="polite"><div class="info-box">Carregue o painel para visualizar a evolução mensal.</div></div>
    </section>

    <section class="fv-create-card">
        <div class="fv-subheading"><div><h4>Registrar encaminhamento</h4><p>Use o Cartão SUS apenas para localizar o cadastro. Não inclua diagnóstico ou detalhes clínicos no campo administrativo.</p></div></div>
        <div class="fv-form-grid">
            <div><label for="fvSus">Cartão SUS do paciente</label><input id="fvSus" inputmode="numeric" autocomplete="off" maxlength="15" placeholder="Somente números"></div>
            <div id="fvOriginField"><label for="fvOrigin">UBS de origem</label><select id="fvOrigin"></select></div>
            <div><label for="fvTarget">Unidade de destino</label><select id="fvTarget" onchange="atualizarEspecialidadesDestinoFilaViva()"></select></div>
            <div><label for="fvReferralSpecialty">Especialidade</label><select id="fvReferralSpecialty"></select></div>
            <div><label for="fvReferralDate">Data solicitada</label><input id="fvReferralDate" type="date"></div>
            <div class="fv-submit-field"><button type="button" class="btn primary" onclick="criarEncaminhamentoFilaViva()">Registrar encaminhamento</button></div>
        </div>
        <p id="fvReferralResult" class="field-help" aria-live="polite"></p>
    </section>

    <div class="fv-work-grid">
        <section class="fv-list-section">
            <div class="fv-subheading"><div><h4>Encaminhamentos e fila</h4><p>A fila é apresentada cronologicamente. A categoria validada é informativa; a escolha para oferta fica com o operador autorizado.</p></div></div>
            <div id="fvReferrals" aria-live="polite"><div class="info-box">Carregue o painel para visualizar os encaminhamentos.</div></div>
        </section>
        <section class="fv-list-section">
            <div class="fv-subheading"><div><h4>Vagas liberadas</h4><p>Cancelamentos abrem uma vaga para oferta manual. A vaga só é preenchida após aceite do paciente.</p></div></div>
            <div id="fvVacancies" aria-live="polite"><div class="info-box">Carregue o painel para visualizar as vagas.</div></div><div id="fvOfferResult" class="info-box hidden" aria-live="polite"></div>
        </section>
    </div>

    <section class="fv-list-section fv-appointments-section">
        <div class="fv-subheading"><div><h4>Agenda, confirmação e comparecimento</h4><p>Horários das consultas UBS ainda não são cadastrados por hora neste sistema; a disponibilidade utiliza o limite diário configurado.</p></div></div>
        <div id="fvAppointments" aria-live="polite"><div class="info-box">Carregue o painel para visualizar consultas.</div></div>
    </section>

    <div class="info-box fv-data-note">
        Indicadores são calculados do banco atual. Espera média/mediana considera apenas encaminhamentos vinculados a consultas realizadas; taxa de faltas usa atendimentos e faltas já registrados; ocupação estima a capacidade pelo limite diário cadastrado e dias de funcionamento, não por horários individuais de profissionais. Sem base suficiente, a métrica é exibida como indisponível. Revise os dados e as regras locais antes de usá-los em avaliação oficial.
    </div>
</div>
