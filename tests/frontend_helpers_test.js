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
assert.match(html, /if \(\$appPortal === 'clinica'\)[\s\S]*http_build_query\(\$_GET/, 'O redirecionamento da rota clínica deve preservar o slug.');
const css = fs.readFileSync('style.css', 'utf8');
assert.match(css, /#professionalCalendar \.calendar-cell/);
assert.match(css, /#profAgenda \.calendar-toolbar/);
assert.match(css, /@media\(max-width:520px\).*#professionalCalendar/);
console.log('Link público da clínica e escopo do calendário: OK');

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

testPatientCampaigns().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
