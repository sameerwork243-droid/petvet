// PetVet Web — runs the *exact same* app the desktop build runs, in a
// browser. Reuses the embedded Express API (server.js) and every IPC
// handler (handlers/*.js) verbatim: an Electron shim installed via
// Module._load turns ipcMain.handle(...) registrations into an RPC
// registry, and a generated browser preload (a ~zero-mutation transform of
// the real preload.js) replays the renderer's window.electronAPI.* calls as
// HTTP posts. Nothing in pet-vet/dist or handlers/ was modified.
//
//   node web-server.js            # default http://localhost:8080
//   Set WEB_PORT to override. WEB_USER_DATA_DIR overrides the "app data"
//   directory used for electron-store auth/session + uploads.
const Module = require('module');
const path = require('path');
const os = require('os');
const fs = require('fs');

const WEB_PORT = Number(process.env.WEB_PORT || process.env.PORT || 8080);
const WEB_USER_DATA_DIR = path.resolve(
  process.env.WEB_USER_DATA_DIR ||
  path.join(os.homedir(), '.petvet-web'),
);
fs.mkdirSync(WEB_USER_DATA_DIR, { recursive: true });

// Serve the SAME uploaded files the desktop app shows (clinic logo, form
// pages, pet reports) so branding/photo URLs resolve identically. Fall back
// to our own dir when the desktop install has never been run.
const DESKTOP_UPLOADS = path.join(
  process.env.APPDATA || path.join(os.homedir(), 'AppData', 'Roaming'),
  'PetVet (Pro)',
  'uploads',
);
const UPLOADS_DIR = fs.existsSync(DESKTOP_UPLOADS)
  ? DESKTOP_UPLOADS
  : path.join(WEB_USER_DATA_DIR, 'uploads');
fs.mkdirSync(UPLOADS_DIR, { recursive: true });

// ---------------------------------------------------------------------------
// 1. Electron shim — every `require('electron')` inside handlers/server.js
//    resolves to this object, so the exact same source runs under Node.
// ---------------------------------------------------------------------------
const rpcChannels = new Map(); // channel -> handler fn
let shimDialogFilePath = null; // set from an RPC upload before a handler runs
const sseClients = new Set();   // open EventSource connections

function sseSend(channel, payload) {
  const data = JSON.stringify(payload === undefined ? {} : payload);
  for (const res of sseClients) {
    try { res.write(`event: ${channel}\ndata: ${data}\n\n`); } catch (_) {}
  }
}

// One BrowserWindow facade: loadURL is a no-op (no real window in Node),
// webContents.print() is treated as "printed", and webContents.send() fans
// out to every connected browser tab (SSE).
function createWindowFacade() {
  const wc = {
    send(channel, payload) { sseSend(channel, payload); },
    once() {},
    on() {},
    print(_opts, cb) { if (typeof cb === 'function') cb(true, ''); },
  };
  return {
    webContents: wc,
    loadURL: async () => {},
    loadFile: async () => {},
    close() {},
    hide() {},
    show() {},
    focus() {},
    restore() {},
    isMinimized: () => false,
    on() {},
    once() {},
  };
}

const electronShim = {
  setDialogFilePath(p) { shimDialogFilePath = p; },
  getDialogFilePath: () => shimDialogFilePath,
  broadcast: sseSend,
  ipcMain: {
    handle(channel, fn) { rpcChannels.set(channel, fn); },
    on() {},
    once() {},
    removeAllListeners() {},
  },
  app: {
    isPackaged: false,
    getPath(name) {
      switch (name) {
        case 'userData': return WEB_USER_DATA_DIR;
        case 'appData': return path.dirname(WEB_USER_DATA_DIR);
        case 'home': return os.homedir();
        case 'temp': return os.tmpdir();
        case 'documents': return path.join(os.homedir(), 'Documents');
        case 'desktop': return path.join(os.homedir(), 'Desktop');
        case 'downloads': return path.join(os.homedir(), 'Downloads');
        default: return WEB_USER_DATA_DIR;
      }
    },
    getVersion: () => '5.0.10-web',
    getAppPath: () => __dirname,
    on() {},
    once() {},
    whenReady: async () => {},
    quit() {},
  },
  shell: {
    openExternal: async () => {},
    openPath: async () => {},
    showItemInFolder: () => {},
  },
  dialog: {
    async showOpenDialog() {
      // A browser can't ask the OS for a file path, so the renderer's file
      // channels upload first (POST /web-upload) and the RPC dispatcher
      // stores that path here; whatever handler calls showOpenDialog* next
      // receives it as the "picked" file.
      if (shimDialogFilePath) {
        const p = shimDialogFilePath;
        shimDialogFilePath = null;
        return { canceled: false, filePaths: [p] };
      }
      return { canceled: true, filePaths: [] };
    },
    async showSaveDialog(opts) {
      const name = (opts && opts.defaultPath) || 'file';
      return { canceled: false, filePath: path.join(os.tmpdir(), path.basename(name)) };
    },
  },
  safeStorage: {
    isEncryptionAvailable: () => true,
    encryptString: (s) => ({ toString: (enc) => Buffer.from(String(s), 'utf8').toString('base64') }),
    decryptString: (b) => {
      // Real Electron: decryptString receives the base64-DECODED ciphertext
      // bytes (saveTokens -> Buffer.from(value,'base64')). Our shim's
      // "ciphertext" IS the plain token bytes, so decode them as utf8 and
      // NEVER re-decode as base64 (that mangles JWT '-'/'_' chars).
      return Buffer.isBuffer(b) ? b.toString('utf8')
        : Buffer.from(String(b), 'base64').toString('utf8');
    },
  },
  Notification: class {
    static isSupported() { return false; }
    on() {}
    show() {}
  },
  BrowserWindow: function BrowserWindowShim() { return createWindowFacade(); },
};
electronShim.BrowserWindow.getAllWindows = () => [createWindowFacade()];
electronShim.BrowserWindow.fromWebContents = () => createWindowFacade();

// electron-log would try to touch real Electron internals under plain Node.
const logShim = {
  transports: { file: { level: 'info' }, console: { level: 'info' } },
  error: (...a) => console.error('[web]', ...a),
  warn: (...a) => console.warn('[web]', ...a),
  info: (...a) => console.log('[web]', ...a),
  log: (...a) => console.log('[web]', ...a),
};

const originalLoad = Module._load;
Module._load = function (request, parent, isMain) {
  if (request === 'electron') return electronShim;
  if (request === 'electron-log') return logShim;
  return originalLoad.call(this, request, parent, isMain);
};

// Force server.js's uploads dir to the same directory the desktop serves
// images from, regardless of env.
process.env.PETVET_UPLOADS_DIR = UPLOADS_DIR;

// Same env behaviour as main.js.
try { require('dotenv').config(); } catch (_) {}

// ---------------------------------------------------------------------------
// 2. Same app, same handlers — mirror main.js's setup list exactly.
// ---------------------------------------------------------------------------
const { app, startServer } = require('./server');

// A tiny electron-store-compatible JSON store (get/set/delete) that works in
// a bare Node process. The real electron-store hard-requires the `electron`
// package, which is NOT installed on Render — so we persist config/session to
// a file under WEB_USER_DATA_DIR instead. Handlers only ever use get/set/delete.
class JsonStore {
  constructor(file) {
    this.file = file;
    this.data = {};
    try { this.data = JSON.parse(fs.readFileSync(file, 'utf8')); } catch (_) {}
  }
  get(key, def) {
    const has = Object.prototype.hasOwnProperty.call(this.data, key);
    return has ? this.data[key] : def;
  }
  set(key, value) {
    this.data[key] = value;
    this._flush();
  }
  delete(key) {
    delete this.data[key];
    this._flush();
  }
  _flush() {
    fs.mkdirSync(path.dirname(this.file), { recursive: true });
    fs.writeFileSync(this.file, JSON.stringify(this.data, null, 2));
  }
}
const store = new JsonStore(path.join(WEB_USER_DATA_DIR, 'web-store.json'));

// saasClient (handlers/authHandlers.js) fetches this baseUrl when proxying
// API calls; point it at ourselves so every /api/* call stays same-origin.
process.env.SAAS_API_BASE_URL = `http://localhost:${WEB_PORT}`;

// On Render (or any single-process deploy) the same server proxies /api/* to
// SAAS_API_BASE_URL — same-origin here, so it short-circuits to ourselves.
if (process.env.SAAS_API_BASE_URL_OVERRIDE) {
  process.env.SAAS_API_BASE_URL = process.env.SAAS_API_BASE_URL_OVERRIDE;
}

// The desktop build persists absolute `http://localhost:4000/...` URLs in
// DB columns (e.g. clinic_settings.logo_url) and API data. Rewrite those so
// the web app is self-contained and shows the same images even when the
// desktop is closed. Desktop is untouched (this file only runs for the web).
{
  const local4000 = /http:\/\/localhost:4000\/|http:\/\/127\.0\.0\.1:4000\//g;
  function rewriteValue(value) {
    if (typeof value === 'string') return value.replace(local4000, `http://localhost:${WEB_PORT}/`);
    if (Array.isArray(value)) {
      // skip Buffer-shaped payloads (PDF bytes): too big, need no rewrite
      if (value.length > 2000 && value.every((v) => typeof v === 'number')) return value;
      for (let i = 0; i < value.length; i++) value[i] = rewriteValue(value[i]);
      return value;
    }
    if (value && typeof value === 'object') {
      for (const k of Object.keys(value)) value[k] = rewriteValue(value[k]);
      return value;
    }
    return value;
  }
  const expressResponseJson = require('express').response.json;
  app.response.json = function (body) {
    if (body && typeof body === 'object') body = rewriteValue(body);
    return expressResponseJson.call(this, body);
  };
}

const setupAuthHandlers = require('./handlers/authHandlers');
const setupEmployeesHandlers = require('./handlers/employeesHandlers');
const setupClinicUsersHandlers = require('./handlers/clinicUsersHandlers');
const setupFormsHandlers = require('./handlers/formsHandlers');
const setupBranchesHandlers = require('./handlers/branchesHandlers');
const setupSubscriptionHandlers = require('./handlers/subscriptionHandlers');
const setupPaymentSubmissionHandlers = require('./handlers/paymentSubmissionHandlers');
const setupClientsHandlers = require('./handlers/clientsHandlers');
const setupPetsHandlers = require('./handlers/petsHandlers');
const setupServicesHandlers = require('./handlers/servicesHandlers');
const setupClientBillingHandlers = require('./handlers/clientBillingHandlers');
const setupProductsHandlers = require('./handlers/productsHandlers');
const setupVendorsHandlers = require('./handlers/vendorsHandlers');
const setupBillingHandlers = require('./handlers/billingHandlers');
const setupSettingsHandlers = require('./handlers/settingsHandlers');
const setupPaymentsHandlers = require('./handlers/paymentsHandlers');
const setupAppointmentProductsHandlers = require('./handlers/appointmentProductsHandlers');
const setupAppointmentsHandlers = require('./handlers/appointmentsHandlers');
const setupReportsHandlers = require('./handlers/reportsHandlers');
const setupRemindersHandlers = require('./handlers/remindersHandlers');
const setupReminderNotifications = require('./handlers/reminderNotifications');
const setupExpensesHandlers = require('./handlers/expensesHandlers');
const setupRecordHandlers = require('./handlers/recordHandlers');
const setupDataManagementHandlers = require('./handlers/dataManagementHandlers');
const setupBoardingHandlers = require('./handlers/boardingHandlers');
const setupAiProductHandlers = require('./handlers/ai/productHandler');
const setupSoapTextHandlers = require('./handlers/ai/soapTextHandler');

setupAuthHandlers(store);
setupEmployeesHandlers();
setupClinicUsersHandlers();
setupFormsHandlers();
setupBranchesHandlers();
setupSubscriptionHandlers();
setupPaymentSubmissionHandlers();
setupClientsHandlers();
setupPetsHandlers();
setupRecordHandlers();
setupRemindersHandlers();
setupServicesHandlers();
setupProductsHandlers();
setupVendorsHandlers();
setupBillingHandlers(store);
setupAppointmentProductsHandlers();
setupAppointmentsHandlers(store);
setupPaymentsHandlers(store);
setupClientBillingHandlers(store);
setupSettingsHandlers(store);
setupExpensesHandlers(store);
setupReportsHandlers();
setupDataManagementHandlers();
setupBoardingHandlers(store);
setupAiProductHandlers();
setupSoapTextHandlers();

// Background reminder check → bell updates. Browser tabs have no native
// notifications, but the bell feed + the "reminder-notifications-updated"
// SSE event still work exactly as in the desktop build.
setupReminderNotifications(store, () => createWindowFacade());

// Desktop-only channel (autoUpdater.quitAndInstall) — web no-op.
rpcChannels.set('restart-app-to-update', async () => ({ success: true }));

// ---------------------------------------------------------------------------
// 3. RPC + web routes on the SAME express app (paths kept off /api/* so the
//    catch-all in server.js never swallows them).
// ---------------------------------------------------------------------------
app.post('/_rpc/:channel', async (req, res) => {
  const channel = req.params.channel;
  const handler = rpcChannels.get(channel);
  if (!handler) {
    return res.status(404).json({ ok: false, error: { message: `Unknown channel: ${channel}` } });
  }
  if (req.body && req.body.dialogFilePath) {
    electronShim.setDialogFilePath(req.body.dialogFilePath);
  }
  let args = (req.body && req.body.args) || [];
  try {
    const result = await handler({}, ...args);
    res.json({ ok: true, result: result === undefined ? null : serializeRpcResult(result) });
  } catch (err) {
    res.json({ ok: false, error: { message: (err && err.message) || String(err) } });
  }
});

// Browser preload cannot hand "real" Buffers across an HTTP JSON boundary,
// but the renderer expects PDF bytes back from channels like
// boarding-preview-consent-form / generate-quick-bill (it builds
// `new Blob([result.data])`). JSON-encoding a Buffer as {type,data[]} turns
// that Blob into garbage, so instead we base64-encode it with a marker the
// generated preload decodes back into a real Uint8Array.
const BUFFER_MARKER = '__pdfBuffer';
function serializeRpcResult(value) {
  if (Buffer.isBuffer(value)) return { [BUFFER_MARKER]: value.toString('base64') };
  if (Array.isArray(value)) return value.map(serializeRpcResult);
  if (value && typeof value === 'object') {
    const out = {};
    for (const k of Object.keys(value)) out[k] = serializeRpcResult(value[k]);
    return out;
  }
  return value;
}
function reviveRpcResult(value) {
  if (Array.isArray(value)) return value.map(reviveRpcResult);
  if (value && typeof value === 'object') {
    if (typeof value[BUFFER_MARKER] === 'string') {
      try {
        const bin = atob(value[BUFFER_MARKER]);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes;
      } catch (_) { return value; }
    }
    for (const k of Object.keys(value)) value[k] = reviveRpcResult(value[k]);
  }
  return value;
}

// SSE — powers ipcRenderer.on(...) subscriptions in the browser preload.
app.get('/events', (req, res) => {
  res.writeHead(200, {
    'Content-Type': 'text/event-stream',
    'Cache-Control': 'no-cache',
    'Connection': 'keep-alive',
    'X-Accel-Buffering': 'no',
  });
  res.write(': connected\n\n');
  sseClients.add(res);
  const heartbeat = setInterval(() => {
    try { res.write(': ping\n\n'); } catch (_) { clearInterval(heartbeat); }
  }, 25000);
  req.on('close', () => {
    clearInterval(heartbeat);
    sseClients.delete(res);
  });
});

// Browser file upload → readable absolute path for a subsequent RPC call.
app.post('/web-upload', (req, res) => {
  const chunks = [];
  req.on('data', (c) => chunks.push(c));
  req.on('end', () => {
    try {
      const rawName = (req.headers['x-filename'] || 'upload').replace(/[^\w.\- ]+/g, '');
      const fileName = `${Date.now()}-${rawName}`;
      const filePath = path.join(UPLOADS_DIR, fileName);
      fs.writeFileSync(filePath, Buffer.concat(chunks));
      res.json({ ok: true, path: filePath, fileName });
    } catch (err) {
      res.json({ ok: false, error: { message: err.message } });
    }
  });
});

// Serve an arbitrary generated file (PDFs, exports) back to the browser.
app.get('/serve-file', (req, res) => {
  const p = String(req.query.path || '');
  const full = path.resolve(p);
  const allowedRoots = [
    path.resolve(WEB_USER_DATA_DIR),
    os.tmpdir(),
    path.join(os.homedir(), 'Documents'),
    path.join(os.homedir(), 'Downloads'),
    path.join(os.homedir(), 'Desktop'),
    DESKTOP_UPLOADS,
  ];
  const underAllowed = allowedRoots.some((root) => full === root || full.startsWith(root + path.sep));
  if (!underAllowed) {
    return res.status(403).json({ ok: false, error: { message: 'Refused to serve that path' } });
  }
  if (!fs.existsSync(full)) return res.status(404).json({ ok: false, error: { message: 'File not found' } });
  if (req.query.inline === '1' || req.query.inline === 'true') {
    res.setHeader('Content-Disposition', 'inline');
    return res.sendFile(full);
  }
  res.download(full);
});

// ---------------------------------------------------------------------------
// 4. Browser preload (generated by transforming the real preload.js) + the
//    static renderer bundle, exactly as Electron would load them.
// ---------------------------------------------------------------------------
const DIST_DIR = path.join(__dirname, 'pet-vet', 'dist');

function generateWebPreload() {
  const src = fs.readFileSync(path.join(__dirname, 'preload.js'), 'utf8');

  // Browser bootstrap: same names the real preload closes over.
  const bootstrap = `
(function () {
  var __listenerMap = {};
  var __es = null;
  function __revive(value) {
    if (Array.isArray(value)) return value.map(__revive);
    if (value && typeof value === 'object') {
      if (typeof value.__pdfBuffer === 'string') {
        try {
          var bin = atob(value.__pdfBuffer);
          var bytes = new Uint8Array(bin.length);
          for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
          return bytes;
        } catch (_) { return value; }
      }
      for (var k in value) value[k] = __revive(value[k]);
    }
    return value;
  }
  function __wire() {
    if (__es) return;
    __es = new EventSource('/events');
    __es.onmessage = function () {};
  }
  function __dispatch(channel, payload) {
    (__listenerMap[channel] || []).slice().forEach(function (fn) { fn(null, payload); });
  }
  var contextBridge = {
    exposeInMainWorld: function (name, obj) { window[name] = obj; if (window.__electronApiReady) window.__electronApiReady(name, obj); }
  };
  var ipcRenderer = {
    invoke: function (channel) {
      var args = Array.prototype.slice.call(arguments, 1)
        .map(function (a) { return a === undefined ? null : a; });
      if (channel === 'open-file-dialog' || channel === 'scan-products-csv' ||
          channel === 'pick-logo-file-select') {
        return __pickAndUpload(channel).then(function (go) {
          if (!go) return channel === 'open-file-dialog'
            ? { success: false, message: 'No file selected' }
            : { success: false, canceled: true };
          return __invokeRpc(channel, args, go.path);
        });
      }
      return __invokeRpc(channel, args, null);
    },
    on: function (channel, listener) {
      __wire();
      if (!__listenerMap[channel]) __listenerMap[channel] = [];
      __listenerMap[channel].push(listener);
      __es.addEventListener(channel, function (e) {
        var payload = null;
        try { payload = JSON.parse(e.data); } catch (_) {}
        (__listenerMap[channel] || []).slice().forEach(function (fn) { fn(null, payload); });
      });
    },
    removeListener: function (channel) {
      __listenerMap[channel] = [];
    }
  };
  function __invokeRpc(channel, args, dialogFilePath) {
    return fetch('/_rpc/' + encodeURIComponent(channel), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ args: args, dialogFilePath: dialogFilePath })
    }).then(function (r) { return r.json(); }).then(function (json) {
      if (json.ok) return __revive(json.result);
      var err = new Error((json.error && json.error.message) || 'Request failed');
      err.code = (json.error && json.error.code) || undefined;
      throw err;
    });
  }
  function __pickAndUpload(channel) {
    return new Promise(function (resolve) {
      if (!window.__fileInput) {
        window.__fileInput = document.createElement('input');
        window.__fileInput.type = 'file';
        window.__fileInput.style.display = 'none';
        document.body.appendChild(window.__fileInput);
      }
      var input = window.__fileInput;
      var accepts = channel === 'scan-products-csv' ? '.csv'
        : channel === 'pick-logo-file-select' ? '.png,.jpg,.jpeg,.webp'
        : '.json,.xlsx,.xls';
      input.accept = accepts;
      var done = false;
      input.onchange = function () {
        if (done) return; done = true;
        var file = input.files && input.files[0];
        if (!file) return resolve(null);
        fetch('/web-upload', { method: 'POST', headers: { 'X-Filename': encodeURIComponent(file.name) }, body: file })
          .then(function (r) { return r.json(); })
          .then(function (j) { return j.ok ? resolve({ path: j.path, name: j.fileName }) : resolve(null); })
          .catch(function () { resolve(null); });
      };
      input.value = '';
      input.click();
    });
  }
  var invoke = function (channel) {
    var args = Array.prototype.slice.call(arguments, 1)
      .map(function (a) { return a === undefined ? null : a; });
    return ipcRenderer.invoke.apply(null, [channel].concat(args));
  };
`;

  // The real preload body is a single contextBridge.exposeInMainWorld(...).
  const marker = 'contextBridge.exposeInMainWorld(';
  const idx = src.indexOf(marker);
  if (idx === -1) throw new Error('Could not locate exposeInMainWorld in preload.js');
  const body = src.slice(idx);

  // Replace desktop-only wrappers so the same channels become browser flows.
  const extra = `
  window.electronAPI.restartAppToUpdate = function () { return Promise.resolve({ success: true }); };
  window.electronAPI.openExternalLink = function (url) { window.open(url, '_blank'); return Promise.resolve(); };
  window.electronAPI.openWhatsApp = function (url) { window.open(url, '_blank'); return Promise.resolve(); };
  window.electronAPI.printPdfFile = function (pdfPath) {
    if (pdfPath) window.open('/serve-file?path=' + encodeURIComponent(pdfPath) + '&inline=1', '_blank');
    return Promise.resolve({ success: true });
  };
  window.electronAPI.printPdfBuffer = function (payload) {
    try {
      var d = (payload && payload.data) || payload;
      var bytes = typeof d === 'string' ? atob(d) : null;
      if (payload && payload.data && typeof payload.data === 'object' && payload.data.data) {
        bytes = payload.data.data.map(function (b) { return String.fromCharCode(b); }).join('');
      }
      if (bytes) {
        var blob = new Blob([Uint8Array.from(bytes, function (c) { return c.charCodeAt(0); })], { type: 'application/pdf' });
        window.open(URL.createObjectURL(blob), '_blank');
      }
    } catch (_) {}
    return Promise.resolve({ success: true });
  };

  function __rewriteFileUris(root) {
    root = root || document;
    var els = root.querySelectorAll ? root.querySelectorAll('iframe[src^="file:"], img[src^="file:"], a[href^="file:"]') : [];
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      var attr = el.tagName === 'A' ? 'href' : 'src';
      var val = el.getAttribute(attr);
      if (!val || val.indexOf('file:') !== 0) continue;
      var p = val;
      if (p.indexOf('file:///') === 0) p = p.substring(7);
      else if (p.indexOf('file://') === 0) p = p.substring(7);
      else if (p.indexOf('file:/') === 0) p = p.substring(6);
      else p = p.substring(5);
      // Convert any backslashes to forward slashes and strip the leading
      // slash ONLY when it prefixes a drive letter (Windows). A plain
      // leading '/' IS the path root on POSIX and must survive
      // (file:///tmp/x.pdf -> /tmp/x.pdf), otherwise path.resolve() can't
      // map it back under /tmp and /serve-file refuses it.
      p = p.split('\\\\').join('/');
      if (/^\\/[A-Za-z]:/.test(p)) p = p.substring(1);
      el.setAttribute(attr, '/serve-file?path=' + encodeURIComponent(p) + '&inline=1');
    }
  }
  __rewriteFileUris();
  var __mut = new MutationObserver(function () { __rewriteFileUris(document); });
  __mut.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['src', 'href'] });
  window.addEventListener('load', function () { __rewriteFileUris(document); });
})();
`;

  return bootstrap + '\n' + body + '\n' + extra;
}

const webPreloadSource = generateWebPreload();
const themePickerSource = fs.readFileSync(path.join(__dirname, 'theme-picker.js'), 'utf8');
const colorPickerSource = fs.readFileSync(path.join(__dirname, 'color-picker.js'), 'utf8');

function buildWebIndexHtml() {
  const raw = fs.readFileSync(path.join(DIST_DIR, 'index.html'), 'utf8');
  const inject = '<script src="/web-preload.js"></script>\n    <script src="/color-picker.js" defer></script>\n    <script src="/theme-picker.js" defer></script>\n  ';
  return raw.replace('<head>', '<head>\n    ' + inject);
}
const webIndexHtml = buildWebIndexHtml();

app.get('/web-preload.js', (req, res) => {
  res.type('application/javascript').send(webPreloadSource);
});
app.get('/color-picker.js', (req, res) => {
  res.type('application/javascript').send(colorPickerSource);
});
app.get('/theme-picker.js', (req, res) => {
  res.type('application/javascript').send(themePickerSource);
});
app.get('/color-picker.html', (req, res) => {
  res.type('text/html').send(fs.readFileSync(path.join(__dirname, 'color-picker.html'), 'utf8'));
});
app.get('/', (req, res) => {
  res.type('text/html').send(webIndexHtml);
});
app.use(expressStatic(DIST_DIR));
app.use((req, res, next) => {
  if (req.method === 'GET' && !req.path.startsWith('/api/') && !req.path.startsWith('/_rpc/')) {
    return res.type('text/html').send(webIndexHtml);
  }
  next();
});

function expressStatic(dir) {
  const express = require('express');
  return express.static(dir);
}

// ---------------------------------------------------------------------------
// 5. Start the embedded API + web server.
// ---------------------------------------------------------------------------
startServer(WEB_PORT).then(() => {
  console.log(`PetVet WEB running at http://localhost:${WEB_PORT}`);
  console.log(`  data dir: ${WEB_USER_DATA_DIR}`);
  console.log(`  login with any account on the embedded backend (e.g. admin / admin123)`);
});