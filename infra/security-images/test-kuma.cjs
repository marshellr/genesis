// Synthetic API compatibility checks for the explicitly patched dependencies.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { createRequire } = require('node:module');
const requireApp = createRequire('/app/package.json');

(async () => {
  const jsonata = requireApp('jsonata');
  assert.equal(await jsonata('a.b + 2').evaluate({ a: { b: 3 } }), 5);
  const protobuf = requireApp('protobufjs');
  const schema = protobuf.parse('syntax="proto3"; message Probe { int32 value=1; }').root.lookupType('Probe');
  assert.equal(schema.decode(schema.encode({ value: 7 }).finish()).value, 7);
  const tar = requireApp('tar');
  const folder = fs.mkdtempSync(path.join(os.tmpdir(), 'kuma-security-'));
  try {
    fs.mkdirSync(path.join(folder, 'out'));
    fs.writeFileSync(path.join(folder, 'input.txt'), 'synthetic');
    await tar.create({ cwd: folder, file: path.join(folder, 'fixture.tar.gz'), gzip: true }, ['input.txt']);
    await tar.x({ cwd: path.join(folder, 'out'), file: path.join(folder, 'fixture.tar.gz') });
    assert.equal(fs.readFileSync(path.join(folder, 'out/input.txt'), 'utf8'), 'synthetic');
  } finally { fs.rmSync(folder, { recursive: true }); }
  assert.equal(requireApp('jsonata/package.json').version, '2.2.1');
  assert.equal(requireApp('protobufjs/package.json').version, '7.5.5');
  assert.equal(requireApp('tar/package.json').version, '7.5.19');
  console.log('Patched JSONata, protobuf and tar APIs verified using synthetic data');
})().catch(() => { console.error('Patched dependency compatibility failed'); process.exitCode = 1; });
