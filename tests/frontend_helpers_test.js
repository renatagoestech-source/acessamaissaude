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

console.log('CPF helpers e handlers HTML: OK');
