const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const js = fs.readFileSync('script.js', 'utf8');
const start = js.indexOf('function normalizarCpf(');
const end = js.indexOf('function toast(', start);
assert(start >= 0 && end > start, 'Helpers de CPF devem permanecer definidos antes de toast().');

const context = {};
vm.runInNewContext(js.slice(start, end), context);
assert.equal(context.normalizarCpf('529.982.247-25'), '52998224725');
assert.equal(context.validarCpf('529.982.247-25'), true);
assert.equal(context.validarCpf('529.982.247-24'), false);
assert.equal(context.validarCpf('111.111.111-11'), false);
assert.equal(context.validarCpf('123'), false);
assert.equal(context.formatarCpf('52998224725'), '529.982.247-25');
assert.equal(context.formatarCpf('123'), '123');

const html = fs.readFileSync('index.php', 'utf8');
const apiSource = fs.readFileSync('api.php', 'utf8');
const definitions = new Set([
  ...Array.from(js.matchAll(/\bfunction\s+([A-Za-z_$][\w$]*)\s*\(/g), match => match[1]),
  ...Array.from(js.matchAll(/\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(?:async\s*)?(?:function|\(?[^=]*=>)/g), match => match[1]),
  ...Array.from(js.matchAll(/\bwindow\.([A-Za-z_$][\w$]*)\s*=/g), match => match[1])
]);
const ignore = new Set([
  'if', 'for', 'while', 'switch', 'catch', 'setTimeout', 'setInterval',
  'alert', 'prompt', 'confirm', 'JSON', 'Number', 'String', 'Math', 'Date',
  'URL', 'URLSearchParams', 'encodeURIComponent', 'decodeURIComponent',
  'console', 'fetch', 'print'
]);
const missing = new Set();
for (const match of html.matchAll(/on(?:click|change|input|blur|submit)\s*=\s*"([^"]+)"/gi)) {
  for (const call of match[1].matchAll(/\b([A-Za-z_$][\w$]*)\s*\(/g)) {
    if (!definitions.has(call[1]) && !ignore.has(call[1])) missing.add(call[1]);
  }
}
assert.deepEqual([...missing], [], `Handlers HTML sem função JavaScript: ${[...missing].join(', ')}`);
assert.doesNotMatch(html, /class="nav-item" onclick="mostrarUBSMenu\(\)"/, 'A aba UBS não deve aparecer na navegação lateral do paciente.');
assert.match(html, /class="nav-item" onclick="iniciarAgendamento\(\)"/, 'O acesso para agendar consulta deve continuar disponível.');

console.log('CPF helpers e handlers HTML: OK');

function testClinicPublicLink() {
  const linkStart = js.indexOf('function atualizarLinkPublicoClinica(slug)');
  const linkEnd = js.indexOf('\nasync function copiarLinkPublicoClinica()', linkStart);
  assert(linkStart >= 0 && linkEnd > linkStart, 'O construtor do link público deve continuar disponível.');

  const publicLink = { href: '#', textContent: '', dataset: {} };
  const previewLink = {
    href: '#',
    attributes: {},
    setAttribute(name, value) { this.attributes[name] = value; },
    removeAttribute(name) { delete this.attributes[name]; }
  };
  const copyButton = { disabled: true };
  const context = {
    URL,
    location: { origin: 'https://clinic.example' },
    appPath: path => path,
    $: id => ({
      profPublicLink: publicLink,
      profPublicLinkOpen: previewLink,
      copyPublicClinicLinkButton: copyButton
    })[id] || null
  };
  vm.runInNewContext(js.slice(linkStart, linkEnd), context);
  context.atualizarLinkPublicoClinica('clinica-teste');

  const generated = new URL(publicLink.href);
  assert.equal(generated.origin, 'https://clinic.example');
  assert.equal(generated.pathname, '/', 'O link usa a rota raiz pública compartilhada com os e-mails da clínica.');
  assert.equal(generated.searchParams.get('clinica'), 'clinica-teste');
  assert.equal(previewLink.href, publicLink.href);
  assert.equal(copyButton.disabled, false);
}

testClinicPublicLink();
assert(apiSource.includes('function ensure_professional_public_slug('), 'Contas antigas sem slug devem receber um identificador público seguro.');
assert(apiSource.includes("$generated=slug_publico($pdo,'profissional-'.$id);"));
assert(apiSource.includes("if($existing!==''){$professional['slug']=$existing;return $professional;}"), 'Slugs públicos existentes devem ser preservados.');
assert(apiSource.includes("'slug'=>$p['slug']"), 'O backend deve incluir o slug na resposta de cadastro/login.');
assert(apiSource.includes('$p=ensure_professional_public_slug($pdo,$p);'));
assert(apiSource.includes('$professional=ensure_professional_public_slug($pdo,$professional);'));
assert(js.includes('professionalSession=d.professional;atualizarLinkPublicoClinica(d.professional?.slug);'), 'Login e cadastro devem preencher o link logo após autenticar.');
assert.match(html, /if \(\$appPortal === 'clinica'\)[\s\S]*http_build_query\(\$_GET/, 'O redirecionamento da rota clínica deve preservar o slug.');
const css = fs.readFileSync('style.css', 'utf8');
assert.match(css, /#professionalCalendar \.calendar-cell/);
assert.match(css, /#profAgenda \.calendar-toolbar/);
assert.match(css, /@media\(max-width:520px\).*#professionalCalendar/);
console.log('Link público da clínica e escopo do calendário: OK');

function testClinicScheduleOverview() {
  const scheduleStart = js.indexOf('const diasAgendaProfissional=');
  const scheduleEnd = js.indexOf('\nasync function salvarPerfilProfissional()', scheduleStart);
  assert(scheduleStart >= 0 && scheduleEnd > scheduleStart, 'O editor de expediente deve carregar e salvar sua grade semanal.');
  const profileStart = js.indexOf('async function carregarPerfilProfissional()');
  const profileEnd = js.indexOf('\nconst diasAgendaProfissional=', profileStart);
  const profileSource = js.slice(profileStart, profileEnd);
  assert(profileSource.indexOf('void carregarAgendaSemanal()') >= 0 && profileSource.indexOf('void carregarAgendaSemanal()') < profileSource.indexOf("api('professional_me')"), 'A grade deve começar a carregar antes do perfil, para um erro no perfil não deixá-la presa no carregamento.');

  const summary = { textContent: '', classList: { toggle() {} } };
  const box = { innerHTML: '', querySelector() { return null; }, querySelectorAll() { return []; } };
  const context = {
    $: id => id === 'professionalScheduleEditor' ? box : id === 'professionalScheduleStatus' ? summary : null,
    escapeAttr: value => String(value),
    escapeHTML: value => String(value)
  };
  vm.runInNewContext(js.slice(scheduleStart, scheduleEnd), context);
  const rows = [
    { dia: 0, ativo: 't', inicio: '08:00', fim: '17:00', duracao: 30, pausainicio: '12:00', pausafim: '13:00' },
    { dia: 1, ativo: false, inicio: '09:00', fim: '17:00', duracao: 30, pausaInicio: '', pausaFim: '' }
  ];
  const normalizedPause = context.normalizarAgendaSemanal(rows)[0];
  assert.equal(normalizedPause.pausaInicio, '12:00', 'Alias PostgreSQL minúsculo deve restaurar o início da pausa ao editar.');
  assert.equal(normalizedPause.pausaFim, '13:00', 'Alias PostgreSQL minúsculo deve restaurar o fim da pausa ao editar.');
  context.desenharAgendaSemanal(rows);
  assert.equal(context.agendaAtivo('t'), true, 'O valor booleano PostgreSQL t deve significar dia ativo.');
  assert.equal(context.agendaAtivo('false'), false);
  assert.match(box.innerHTML, /schedule-tab-hours/);
  assert.match(box.innerHTML, /08:00–17:00/);
  assert.match(box.innerHTML, /value="12:00"/);
  assert.match(box.innerHTML, /value="13:00"/);
  assert.match(box.innerHTML, /Fechado/);
  assert.equal(typeof box.oninput, 'function', 'Alterar horários deve atualizar o resumo sem recarregar a tela.');
}

testClinicScheduleOverview();
assert.match(html, /hash_file\('sha256'/, 'Os assets devem usar fingerprints de conteúdo para invalidar cache após mudanças.');
assert.match(html, /duração da consulta e pausa opcional[\s\S]*Salvar expediente e pausas semanais/);
assert.match(css, /\.schedule-tab-hours/);
console.log('Editor semanal e cache de assets da clínica: OK');

async function testScheduleLoadingStates() {
  const start = js.indexOf('const diasAgendaProfissional=');
  const end = js.indexOf('\nasync function salvarPerfilProfissional()', start);
  function createContext(api) {
    const box = { innerHTML: '', querySelector() { return null; }, querySelectorAll() { return []; } };
    const summary = { textContent: 'Carregando expediente semanal…', classList: { toggle() {} } };
    let timeoutMs = 0;
    let toastMessage = '';
    const context = {
      $: id => id === 'professionalScheduleEditor' ? box : id === 'professionalScheduleStatus' ? summary : null,
      api,
      AbortController,
      setTimeout(_callback, ms) { timeoutMs = ms; return 1; },
      clearTimeout() {},
      toast: message => { toastMessage = message; },
      escapeAttr: value => String(value),
      escapeHTML: value => String(value)
    };
    vm.runInNewContext(js.slice(start, end), context);
    return { context, box, summary, get timeoutMs() { return timeoutMs; }, get toastMessage() { return toastMessage; } };
  }

  const success = createContext(async action => {
    assert.equal(action, 'professional_schedule');
    return { schedule: [] };
  });
  await success.context.carregarAgendaSemanal();
  assert.equal(success.timeoutMs, 15000, 'A consulta deve ter limite para não manter o campo carregando indefinidamente.');
  assert.match(success.box.innerHTML, /scheduleTab6/, 'Mesmo uma grade vazia deve renderizar os sete dias para permitir configurar um expediente.');
  assert.match(success.box.innerHTML, /data-schedule-active="0"/);
  assert.doesNotMatch(success.box.innerHTML, /Tentar novamente/);
  assert.doesNotMatch(success.summary.textContent, /Carregando expediente/);

  const failure = createContext(async () => { throw new Error('Sessão profissional expirada.'); });
  await failure.context.carregarAgendaSemanal();
  assert.match(failure.box.innerHTML, /Tentar novamente/, 'Falha de carga deve oferecer nova tentativa, não deixar o placeholder.');
  assert.match(failure.summary.textContent, /Sessão profissional expirada/);
  assert.match(failure.toastMessage, /Sessão profissional expirada/);
  console.log('Carregamento independente do expediente, estado vazio e retry: OK');
}

async function testPatientCampaigns() {
  const start = js.indexOf('async function mostrarAvisos(');
  const end = js.indexOf('async function verificarNotificacoes()', start);
  assert(start >= 0 && end > start, 'A área de campanhas deve atualizar os dados do servidor ao abrir.');

  const container = { innerHTML: '' };
  const visibleSections = [];
  let requestedAction = '';
  const context = {
    STORAGE_PATIENT: 'patient',
    STORAGE_LAST_UBS: 'last-ubs',
    sessionStorage: {
      getItem(key) {
        return key === 'patient' ? JSON.stringify({ ubsId: 42 }) : null;
      }
    },
    $: id => id === 'listaAvisos' ? container : null,
    api: async action => {
      requestedAction = action;
      return { ubs: [
        { id: '42', nome: 'UBS Central', horario: '07:00 às 18:00', campanhas: ['Vacinação <2026>'] },
        { id: '43', nome: 'Outra UBS', horario: '08:00 às 17:00', campanhas: ['Não deve aparecer'] }
      ] };
    },
    mostrarApenas: section => visibleSections.push(section),
    escapeHTML: value => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;')
  };

  vm.runInNewContext(js.slice(start, end), context);
  await context.mostrarAvisos();
  assert.equal(requestedAction, 'get_ubs');
  assert.deepEqual(visibleSections, ['avisosSection']);
  assert.match(container.innerHTML, /CAMPANHA \/ EVENTO/);
  assert.match(container.innerHTML, /Vacinação &lt;2026&gt;/);
  assert.match(container.innerHTML, /UBS Central/);
  assert.doesNotMatch(container.innerHTML, /Não deve aparecer/);
  console.log('Campanhas e eventos atualizados para a UBS do paciente: OK');
}

async function runAsyncTests() {
  await testScheduleLoadingStates();
  await testPatientCampaigns();
}

runAsyncTests().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
