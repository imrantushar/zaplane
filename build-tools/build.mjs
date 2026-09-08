#!/usr/bin/env node
/**
 * Cross-platform WordPress plugin release builder.
 *
 * One command, identical behaviour on Linux / macOS / Windows:
 *   1. Production JS build          (npm ci + npm run <buildScript>)
 *   2. Production Composer install  (--no-dev) + Strauss namespace prefixing
 *   3. POT translation template     (wp i18n make-pot, or a delegated command)
 *   4. Distribution zip             (honours .distignore, built in Node so it
 *                                    never drops vendor/autoload.php and needs
 *                                    no system `zip`)
 *
 * Everything is orchestrated from Node so there is no bash / make / PowerShell
 * dependency. External CLIs (composer, wp, php) are spawned through the OS shell,
 * which resolves .cmd/.bat/.phar wrappers on Windows automatically.
 *
 * Usage:
 *   node build-tools/build.mjs                # full release build
 *   node build-tools/build.mjs --skip-js      # skip a step (--skip-js|composer|pot|zip)
 *   node build-tools/build.mjs --only=zip     # run a single step
 *   node build-tools/build.mjs --no-download  # never fetch wp/strauss phars, fail instead
 *   node build-tools/build.mjs --dev          # keep dev deps (debug builds)
 */

import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import https from 'node:https';
import { execSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CACHE = path.join(ROOT, '.build-cache');
const args = process.argv.slice(2);
const hasFlag = (f) => args.includes(f);
const onlyStep = (args.find((a) => a.startsWith('--only=')) || '').split('=')[1] || null;

// ---------------------------------------------------------------------------
// Config: read build.config.json, fall back to sensible auto-detected defaults.
// ---------------------------------------------------------------------------
function loadConfig() {
  const cfgPath = path.join(ROOT, 'build.config.json');
  const cfg = fs.existsSync(cfgPath) ? JSON.parse(fs.readFileSync(cfgPath, 'utf8')) : {};

  // Auto-detect the main plugin file (the .php with a `Plugin Name:` header).
  if (!cfg.mainFile) cfg.mainFile = detectMainFile();
  const headers = cfg.mainFile ? readPluginHeaders(path.join(ROOT, cfg.mainFile)) : {};

  // Version: package.json is the single source of truth (webpack.config.js reads
  // the same field), then the plugin header, then an explicit config override.
  const pkgPath = path.join(ROOT, 'package.json');
  const pkgVersion = fs.existsSync(pkgPath)
    ? JSON.parse(fs.readFileSync(pkgPath, 'utf8')).version
    : null;

  return {
    slug: cfg.slug || path.basename(ROOT),
    textdomain: cfg.textdomain || headers.TextDomain || path.basename(ROOT),
    mainFile: cfg.mainFile,
    version: cfg.version || pkgVersion || headers.Version || '0.0.0',
    languages: cfg.languages || 'languages',
    steps: { manifest: false, js: true, composer: true, strauss: true, pot: true, zip: true, ...(cfg.steps || {}) },
    // install: true | false | "auto" (install only when node_modules is absent).
    // installCommand overrides the default npm ci / npm install (e.g. to add
    // --legacy-peer-deps for trees with peer-dep conflicts).
    js: { install: true, installCommand: null, buildScript: 'build', ...(cfg.js || {}) },
    composer: {
      // If `command` is set, it fully owns the composer step (install + strauss +
      // dump-autoload). Use this when the plugin already has a composer script that
      // wires Strauss via post-install-cmd — running our own steps would double it.
      command: null,
      flags: ['--no-dev', '--optimize-autoloader', '--prefer-dist', '--no-interaction'],
      ...(cfg.composer || {}),
    },
    strauss: { command: null, ...(cfg.strauss || {}) },
    // A plugin-owned command that regenerates a generated source file the zip
    // must ship (Zaplane: the integration catalogue). Off unless configured.
    manifest: { command: null, ...(cfg.manifest || {}) },
    // pot.command, when set, fully owns the POT step (e.g. a babel-aware
    // make-pot wrapper for large webpack bundles WP-CLI's parser cannot read).
    pot: { command: null, exclude: null, ...(cfg.pot || {}) },
    // strictVendor: die (instead of warn) when the zip step detects a dev vendor.
    zip: { strictVendor: false, ...(cfg.zip || {}) },
    autoDownloadTools: cfg.autoDownloadTools !== false,
    toolVersions: { wpCli: '2.12.0', strauss: '0.21.1', ...(cfg.toolVersions || {}) },
    zipName: cfg.zipName || '{slug}.{version}.zip',
    // Where the release zip is written, relative to the plugin root. Default "dist".
    // Use ".." to drop it directly in the parent plugins/ folder.
    outputDir: cfg.outputDir || 'dist',
  };
}

function detectMainFile() {
  for (const f of fs.readdirSync(ROOT)) {
    if (!f.endsWith('.php')) continue;
    const head = fs.readFileSync(path.join(ROOT, f), 'utf8').slice(0, 4096);
    if (/^\s*\*?\s*Plugin Name:/mi.test(head)) return f;
  }
  return null;
}

function readPluginHeaders(file) {
  if (!file || !fs.existsSync(file)) return {};
  const head = fs.readFileSync(file, 'utf8').slice(0, 8192);
  const grab = (k) => (head.match(new RegExp(`^\\s*\\*?\\s*${k}:\\s*(.+)$`, 'mi')) || [])[1]?.trim();
  return { Version: grab('Version'), TextDomain: grab('Text Domain') };
}

// ---------------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------------
const log = (msg) => console.log(`\x1b[36m[build]\x1b[0m ${msg}`);
const step = (msg) => console.log(`\n\x1b[1m\x1b[35m▶ ${msg}\x1b[0m`);
const warn = (msg) => console.log(`\x1b[33m[warn]\x1b[0m ${msg}`);
const die = (msg) => { console.error(`\x1b[31m[error]\x1b[0m ${msg}`); process.exit(1); };

/** Run a command through the OS shell (resolves .cmd/.bat/.phar on Windows). */
function run(cmd, opts = {}) {
  log(`$ ${cmd}`);
  execSync(cmd, { cwd: ROOT, stdio: 'inherit', shell: true, ...opts });
}

/** Is a CLI available on PATH? (`wp --version` style probe.) */
function has(bin, versionFlag = '--version') {
  const r = spawnSync(bin, [versionFlag], { stdio: 'ignore', shell: true });
  return r.status === 0;
}

/** Download a file (follows GitHub redirects) to dest. */
function download(url, dest, redirects = 0) {
  return new Promise((resolve, reject) => {
    if (redirects > 5) return reject(new Error('too many redirects'));
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    const file = fs.createWriteStream(dest);
    https.get(url, { headers: { 'User-Agent': 'wp-build-kit' } }, (res) => {
      if ([301, 302, 307, 308].includes(res.statusCode)) {
        file.close(); fs.rmSync(dest, { force: true });
        return resolve(download(res.headers.location, dest, redirects + 1));
      }
      if (res.statusCode !== 200) { file.close(); return reject(new Error(`HTTP ${res.statusCode} for ${url}`)); }
      res.pipe(file);
      file.on('finish', () => file.close(resolve));
    }).on('error', (e) => { fs.rmSync(dest, { force: true }); reject(e); });
  });
}

// ---------------------------------------------------------------------------
// Step 1 — production JS build
// ---------------------------------------------------------------------------
function buildJs(cfg) {
  if (!fs.existsSync(path.join(ROOT, 'package.json'))) return warn('no package.json — skipping JS build');
  const pkg = JSON.parse(fs.readFileSync(path.join(ROOT, 'package.json'), 'utf8'));
  if (!pkg.scripts?.[cfg.js.buildScript]) return warn(`no "${cfg.js.buildScript}" script — skipping JS build`);

  const nodeModules = fs.existsSync(path.join(ROOT, 'node_modules'));
  const doInstall = cfg.js.install === 'auto' ? !nodeModules : cfg.js.install;
  if (doInstall) {
    const hasLock = fs.existsSync(path.join(ROOT, 'package-lock.json'));
    run(cfg.js.installCommand || (hasLock ? 'npm ci' : 'npm install'));
  } else if (cfg.js.install === 'auto') {
    log('node_modules present — skipping install (set js.install:true to force)');
  }
  run(`npm run ${cfg.js.buildScript}`);
}

// ---------------------------------------------------------------------------
// Step 2 — production Composer install + Strauss
// ---------------------------------------------------------------------------
async function buildComposer(cfg) {
  if (!fs.existsSync(path.join(ROOT, 'composer.json'))) return warn('no composer.json — skipping Composer');
  if (!has('composer')) return die('composer not found on PATH — install Composer or use --skip-composer');

  // Delegated mode: the plugin's own composer script owns install + Strauss + dump.
  if (cfg.composer.command) return run(cfg.composer.command);

  // 1. Prod-only dependency tree.
  run(`composer install ${cfg.composer.flags.join(' ')}`);

  // 2. Strauss: prefix the shipped library namespaces so two plugins can't clash.
  if (cfg.steps.strauss) await runStrauss(cfg);

  // 3. Regenerate the autoloader for the final (prefixed) layout.
  run('composer dump-autoload --no-dev --optimize --no-interaction');
}

async function runStrauss(cfg) {
  const composerJson = JSON.parse(fs.readFileSync(path.join(ROOT, 'composer.json'), 'utf8'));
  if (!composerJson.extra?.strauss) return warn('no extra.strauss in composer.json — skipping Strauss');

  // Explicit override wins.
  if (cfg.strauss.command) return run(cfg.strauss.command);
  // A composer script named "strauss".
  if (composerJson.scripts?.strauss) return run('composer strauss');
  // A locally vendored binary (only present if strauss is a *prod* dep).
  const localBin = path.join(ROOT, 'vendor', 'bin', os.platform() === 'win32' ? 'strauss.bat' : 'strauss');
  if (fs.existsSync(localBin)) return run(`"${localBin}"`);
  // A cached / committed phar.
  for (const p of [path.join(ROOT, 'bin', 'strauss.phar'), path.join(CACHE, 'strauss.phar')]) {
    if (fs.existsSync(p)) return run(`php "${p}"`);
  }
  // Last resort: fetch the phar (independent of the plugin's own deps).
  if (!cfg.autoDownloadTools) return die('Strauss not found and --no-download set. Add it as a composer script or commit bin/strauss.phar.');
  const dest = path.join(CACHE, 'strauss.phar');
  const url = `https://github.com/BrianHenryIE/strauss/releases/download/${cfg.toolVersions.strauss}/strauss.phar`;
  log(`downloading Strauss ${cfg.toolVersions.strauss} …`);
  await download(url, dest);
  run(`php "${dest}"`);
}

// ---------------------------------------------------------------------------
// Step 3 — POT translation template
// ---------------------------------------------------------------------------
// Regenerates a source file the release must ship, before anything packages it.
// Deliberately fails rather than skipping: a stale generated file is worse than
// a build that stops, because it ships silently.
async function buildManifest(cfg) {
  if (!cfg.manifest.command) return;
  run(cfg.manifest.command);
}

async function buildPot(cfg) {
  // Delegated mode: the plugin owns POT generation (e.g. a babel-aware make-pot
  // wrapper for large webpack bundles WP-CLI's Peast parser cannot read).
  if (cfg.pot.command) return run(cfg.pot.command);

  const dest = path.join(ROOT, cfg.languages, `${cfg.textdomain}.pot`);
  fs.mkdirSync(path.dirname(dest), { recursive: true });

  let wp = has('wp') ? 'wp' : null;
  if (!wp) {
    const cached = path.join(CACHE, 'wp-cli.phar');
    if (!fs.existsSync(cached)) {
      if (!cfg.autoDownloadTools) return die('wp-cli not found and --no-download set. Install WP-CLI or --skip-pot.');
      const url = `https://github.com/wp-cli/wp-cli/releases/download/v${cfg.toolVersions.wpCli}/wp-cli-${cfg.toolVersions.wpCli}.phar`;
      log(`downloading WP-CLI ${cfg.toolVersions.wpCli} …`);
      await download(url, cached);
    }
    wp = `php "${cached}"`;
  }

  // Exclude everything the .pot never needs (mirrors .distignore intent).
  const exclude = (cfg.pot.exclude ||
    ['node_modules', 'vendor', 'vendor-prefixed', 'build', 'dist', '.git', 'tests', cfg.languages]).join(',');
  run(`${wp} i18n make-pot . "${path.relative(ROOT, dest)}" --domain=${cfg.textdomain} --exclude=${exclude} --skip-audit`);
}

// ---------------------------------------------------------------------------
// Guard: the fast (--only=zip) path packages the tree AS-IS. Make sure we are
// not silently shipping a dev vendor (dev deps present / namespaces un-prefixed).
// ---------------------------------------------------------------------------
function checkVendorState(cfg) {
  const composerPath = path.join(ROOT, 'composer.json');
  const vendorDir = path.join(ROOT, 'vendor');
  if (!fs.existsSync(composerPath) || !fs.existsSync(vendorDir)) return; // nothing to check
  const composer = JSON.parse(fs.readFileSync(composerPath, 'utf8'));

  const issues = [];
  // 1. Strauss output should exist when Strauss is configured.
  const target = composer.extra?.strauss?.target_directory;
  if (target && !fs.existsSync(path.join(ROOT, target))) {
    issues.push(`Strauss output "${target}" missing — namespaces not prefixed`);
  }
  // 2. Any require-dev package still on disk means this is not a --no-dev tree.
  const devPkgs = Object.keys(composer['require-dev'] || {})
    .filter((p) => p.includes('/') && fs.existsSync(path.join(vendorDir, p)));
  if (devPkgs.length) {
    issues.push(`dev dependencies present: ${devPkgs.slice(0, 3).join(', ')}${devPkgs.length > 3 ? ` +${devPkgs.length - 3}` : ''}`);
  }
  if (!issues.length) return; // looks production-ready

  warn('vendor/ does not look like a production build:');
  for (const i of issues) warn(`  • ${i}`);
  warn('This zip may ship dev code. Run  npm run dist  for a full production build.');
  if (cfg.zip.strictVendor || hasFlag('--strict-vendor')) {
    die('aborting — vendor is not production-ready (--strict-vendor)');
  }
}

// ---------------------------------------------------------------------------
// Step 4 — distribution zip (Node-native, honours .distignore)
// ---------------------------------------------------------------------------
async function buildZip(cfg) {
  checkVendorState(cfg);

  let makeZip, ignore;
  try {
    const am = await import('archiver');
    // Support archiver v7 (default factory) and v8 (pure-ESM classes).
    if (typeof am.default === 'function') makeZip = (o) => am.default('zip', o);
    else if (am.ZipArchive) makeZip = (o) => new am.ZipArchive(o);
    else if (am.Archiver) makeZip = (o) => new am.Archiver('zip', o);
    else throw new Error('unsupported archiver');
    const im = await import('ignore'); ignore = im.default || im;
  } catch (e) {
    return die(`Zip step needs dev deps. Run:  npm i -D archiver ignore\n(${e.message})`);
  }

  const ig = ignore();
  const distignore = path.join(ROOT, '.distignore');
  if (fs.existsSync(distignore)) ig.add(fs.readFileSync(distignore, 'utf8'));
  else warn('no .distignore — shipping everything except .git/node_modules');
  ig.add(['.git', 'node_modules', '.build-cache', 'dist']); // always-ignored safety net

  const outDir = path.resolve(ROOT, cfg.outputDir);
  fs.mkdirSync(outDir, { recursive: true });
  const zipName = cfg.zipName.replace('{slug}', cfg.slug).replace('{version}', cfg.version);
  const outPath = path.join(outDir, zipName);
  const replacing = fs.existsSync(outPath);
  fs.rmSync(outPath, { force: true }); // overwrite any existing zip of the same name

  // Walk once, pruning ignored directories, and stream matched files into the zip
  // under a top-level <slug>/ folder (required layout for a WP plugin zip).
  const files = [];
  (function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const abs = path.join(dir, entry.name);
      let rel = path.relative(ROOT, abs).split(path.sep).join('/');
      if (entry.isDirectory()) rel += '/';
      if (ig.ignores(rel)) continue;
      if (entry.isDirectory()) walk(abs);
      else files.push({ abs, rel: rel });
    }
  })(ROOT);

  if (!files.length) return die('nothing to zip — check your .distignore');

  await new Promise((resolve, reject) => {
    const output = fs.createWriteStream(outPath);
    const archive = makeZip({ zlib: { level: 9 } });
    output.on('close', resolve);
    archive.on('error', reject);
    archive.pipe(output);
    for (const f of files) archive.file(f.abs, { name: `${cfg.slug}/${f.rel}` });
    archive.finalize();
  });

  const kb = (fs.statSync(outPath).size / 1024).toFixed(0);
  const shown = path.relative(ROOT, outPath).split(path.sep).join('/');
  log(`${replacing ? 'replaced' : 'created'} ${shown}  (${files.length} files, ${kb} KB)`);

  // Guard against the classic "vendor got shipped but autoload.php got dropped" bug.
  if (files.some((f) => f.rel.startsWith('vendor/')) && !files.some((f) => f.rel === 'vendor/autoload.php')) {
    warn('vendor/ is shipped but vendor/autoload.php is missing — check your .distignore patterns!');
  }
}

// ---------------------------------------------------------------------------
// Orchestrate
// ---------------------------------------------------------------------------
(async () => {
  const cfg = loadConfig();
  if (hasFlag('--dev')) cfg.composer.flags = cfg.composer.flags.filter((f) => f !== '--no-dev');

  const wanted = (name) => (onlyStep ? onlyStep === name : cfg.steps[name] && !hasFlag(`--skip-${name}`));

  log(`${cfg.slug} v${cfg.version}  (textdomain: ${cfg.textdomain}, main: ${cfg.mainFile || 'n/a'})`);
  const t0 = Date.now();
  const timings = [];

  // Run a step, print its header, and record how long it took.
  const timed = async (name, title, fn) => {
    step(title);
    const s = Date.now();
    await fn();
    const secs = (Date.now() - s) / 1000;
    timings.push([title.replace(/^\d+\/\d+\s+/, ''), secs]);
    log(`↳ ${secs.toFixed(1)}s`);
  };

  const total_steps = ['manifest', 'js', 'composer', 'pot', 'zip'].filter((n) => cfg.steps[n]).length;
  let n = 0;
  const label = (title) => `${++n}/${total_steps}  ${title}`;

  if (wanted('manifest')) await timed('manifest', label('Generated manifest'), () => buildManifest(cfg));
  if (wanted('js'))       await timed('js', label('Production JS build'), () => buildJs(cfg));
  if (wanted('composer')) await timed('composer', label('Composer (no-dev) + Strauss'), () => buildComposer(cfg));
  if (wanted('pot'))      await timed('pot', label('POT translation template'), () => buildPot(cfg));
  if (wanted('zip'))      await timed('zip', label('Distribution zip'), () => buildZip(cfg));

  const total = (Date.now() - t0) / 1000;
  if (timings.length > 1) {
    console.log('');
    for (const [name, secs] of timings) log(`  ${name.padEnd(34)} ${secs.toFixed(1)}s`);
  }
  log(`\x1b[32mdone\x1b[0m in ${total.toFixed(1)}s`);
})().catch((e) => die(e.stack || e.message));
