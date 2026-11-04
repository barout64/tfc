// TFC Protocol & Correspondence Management System — browser app.
'use strict';

const ROLES = {
  admin: 'System administrator',
  protocol: 'Protocol officer',
  head: 'Department head',
  secretary: 'Department secretary',
  employee: 'Employee',
};
const CATEGORIES = ['Official Letter', 'Internal Memo', 'Request for Approval', 'Meeting Invitation', 'Meeting Minutes',
  'Report', 'Notice', 'Appointment Letter', 'Warning Letter', 'Circular', 'Other'];
const DOC_STATUS = {
  draft: ['Draft', ''],
  pending: ['Awaiting approval', 'warn'],
  returned: ['Returned for corrections', 'bad'],
  final: ['Final', 'ok'],
};
const FIELD_TYPES = { text: 'Short text', textarea: 'Long text', date: 'Date', number: 'Number', select: 'Choice list' };

const S = { me: null, settings: {}, departments: [], users: [], summary: {}, dirty: false, skipHash: false, lastHash: '', lib: { q: '', dept: '', cat: '' } };
const esc = Render.esc;
const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

const ICONS = {
  home: 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10',
  library: 'M4 4h6v16H4zM14 4h6v16h-6zM7 8h0M17 8h0',
  doc: 'M6 2h9l5 5v15H6zM14 2v6h6M9 13h8M9 17h8',
  check: 'M4 12l5 5L20 6',
  edit: 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
  book: 'M4 4h7a3 3 0 013 3v13a2 2 0 00-2-2H4zM20 4h-5a3 3 0 00-3 3',
  chart: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
  users: 'M16 19v-1a4 4 0 00-4-4H6a4 4 0 00-4 4v1M9 10a3 3 0 100-6 3 3 0 000 6M22 19v-1a4 4 0 00-3-3.9M16 4.1a3 3 0 010 5.8',
  log: 'M12 7v5l3 3M12 21a9 9 0 110-18 9 9 0 010 18',
  bell: 'M18 16V11a6 6 0 00-12 0v5l-2 2h16zM10 20a2 2 0 004 0',
  menu: 'M3 6h18M3 12h18M3 18h18',
};
const icon = (name) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${ICONS[name]}"/></svg>`;

// ---------- helpers ----------
async function api(path, { method = 'GET', body } = {}) {
  const res = await fetch('/protocol/api' + path, {
    method,
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  let data = {};
  try { data = await res.json(); } catch { /* empty response */ }
  if (res.status === 401 || res.status === 419) { location.href = '/login'; }
  if (!res.ok) throw new Error(data.error || 'Request failed');
  return data;
}

function toast(msg, type = '') {
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = msg;
  $('#toasts').appendChild(el);
  setTimeout(() => el.remove(), type === 'bad' ? 6000 : 3500);
}

// Runs an async action, reporting errors as a toast. Disables the button while running.
async function run(btn, fn) {
  if (btn) btn.disabled = true;
  try { return await fn(); } catch (e) { toast(e.message, 'bad'); } finally { if (btn) btn.disabled = false; }
}

const deptName = (id) => (S.departments.find((d) => d.id === id) || {}).name || '—';
const userName = (id) => (S.users.find((u) => u.id === id) || {}).name || 'Unknown user';
const today = () => { const d = new Date(); return new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); };
function fmtDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
function fmtDT(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function ago(iso) {
  const s = (Date.now() - new Date(iso)) / 1000;
  if (s < 60) return 'just now';
  if (s < 3600) return Math.floor(s / 60) + ' min ago';
  if (s < 86400) return Math.floor(s / 3600) + ' h ago';
  if (s < 86400 * 7) return Math.floor(s / 86400) + ' d ago';
  return fmtDate(iso);
}
const docBadge = (st) => `<span class="badge ${DOC_STATUS[st][1]}">${DOC_STATUS[st][0]}</span>`;
function tplBadges(t) {
  const out = [];
  if (t.archived) out.push('<span class="badge">Archived</span>');
  if (t.published) out.push(`<span class="badge ok">Published v${t.published.version}</span>`);
  if (t.draft) {
    const m = { draft: ['Draft changes', ''], submitted: ['Awaiting approval', 'warn'], returned: ['Returned', 'bad'] }[t.draft.status];
    out.push(`<span class="badge ${m[1]}">${m[0]}</span>`);
  }
  if (t.dueForReview) out.push('<span class="badge warn">Due for review</span>');
  return out.join(' ');
}
const isRole = (...r) => r.includes(S.me.role);
const setTitle = (t) => { $('#pageTitle').textContent = t; document.title = t + ' · ' + (S.settings.orgName || 'TFC Protocol'); };
const main = () => $('#main');

function modal(title, html, { wide = false, onSubmit, submitLabel = 'Save', danger = false } = {}) {
  const bg = document.createElement('div');
  bg.className = 'modal-bg';
  bg.innerHTML = `<form class="modal ${wide ? 'wide' : ''}"><h2>${esc(title)}</h2><div class="mbody">${html}</div>
    <div class="row end" style="margin-top:16px"><button type="button" class="btn" data-close>Cancel</button>
    ${onSubmit ? `<button class="btn ${danger ? 'danger solid' : 'primary'}" type="submit">${esc(submitLabel)}</button>` : ''}</div></form>`;
  document.body.appendChild(bg);
  const close = () => bg.remove();
  bg.addEventListener('mousedown', (e) => { if (e.target === bg) close(); });
  $('[data-close]', bg).onclick = close;
  const form = $('form', bg);
  form.onsubmit = async (e) => {
    e.preventDefault();
    if (!onSubmit) return close();
    const btn = $('button[type=submit]', form);
    await run(btn, async () => { if ((await onSubmit(form)) !== false) close(); });
  };
  const first = $('input,textarea,select', form);
  if (first) first.focus();
  return form;
}
const confirmBox = (title, text, label, danger) => new Promise((resolve) => {
  const form = modal(title, `<p>${esc(text)}</p>`, { onSubmit: () => resolve(true), submitLabel: label, danger });
  $('[data-close]', form).addEventListener('click', () => resolve(false));
});

// Lets the user choose an image, converts it to PNG/JPEG in the browser (so any format works,
// including WebP) and uploads it. Resolves with the upload record, or null if cancelled.
function pickImage() {
  return new Promise((resolve, reject) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = () => {
      const file = input.files[0];
      if (!file) return resolve(null);
      uploadImage(file).then(resolve, reject);
    };
    input.click();
  });
}

async function uploadImage(file) {
  if (file.size > 20e6) throw new Error('The image is too large (maximum 20 MB).');
  const url = URL.createObjectURL(file);
  try {
    const img = await new Promise((res, rej) => {
      const i = new Image();
      i.onload = () => res(i);
      i.onerror = () => rej(new Error('This file is not an image the browser can read.'));
      i.src = url;
    });
    const scale = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
    const w = Math.max(1, Math.round(img.naturalWidth * scale));
    const h = Math.max(1, Math.round(img.naturalHeight * scale));
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
    const photo = file.type === 'image/jpeg';
    const data = canvas.toDataURL(photo ? 'image/jpeg' : 'image/png', 0.9);
    return await api('/uploads', { method: 'POST', body: { name: file.name, data, width: w, height: h } });
  } finally {
    URL.revokeObjectURL(url);
  }
}
// Shows the image library in a window; resolves with the chosen file name, or null.
function chooseImage(list, title) {
  return new Promise((resolve) => {
    let done = false;
    const finish = (v) => { if (!done) { done = true; resolve(v); } };
    const form = modal(title, `<div class="row" style="margin-bottom:12px"><button type="button" class="btn sm primary" data-upload>Upload new image</button>
      <small class="muted">or click an image below</small></div>
      ${list.length ? `<div class="gallery">${list.map((u) => `<button type="button" class="imgthumb big" data-pick="${esc(u.file)}">${Render.imgTag(u.file, 0, '/protocol/uploads/', 80)}<small>${esc(u.name)}</small></button>`).join('')}</div>` : '<p class="muted">No images uploaded yet.</p>'}`, { wide: true });
    const close = () => form.closest('.modal-bg').remove();
    $('[data-close]', form).addEventListener('click', () => finish(null));
    form.addEventListener('click', (ev) => {
      const up = ev.target.closest('[data-upload]');
      if (up) {
        run(up, async () => {
          const rec = await pickImage();
          if (!rec) return;
          list.unshift(rec);
          finish(rec.file);
          close();
        });
        return;
      }
      const b = ev.target.closest('[data-pick]');
      if (b) { finish(b.dataset.pick); close(); }
    });
  });
}
const logoImg = (file, height) => (file ? Render.imgTag(file, 0, '/protocol/uploads/', height) : '');

function letterHtml({ body, fields, logos, headerTextTop, headerImage, footerImages, bodyImages, values, departmentId, preview, watermark, editable }) {
  return Render.renderLetter({
    settings: S.settings,
    departmentName: deptName(departmentId),
    body, fields, logos, headerTextTop, headerImage, footerImages, bodyImages, preview, watermark, editable,
    values: { ...values, department: deptName(departmentId), org_name: S.settings.orgName },
  });
}

// Updates a preview by replacing only the parts that changed, so typing stays fast
// and images are not reloaded on every keystroke.
// Zooms each page preview so the true-size A4 page fits its panel.
const PAGE_W = 794;
function fitPapers() {
  $$('.paper').forEach((p) => {
    const cs = getComputedStyle(p);
    const avail = p.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    const z = Math.min(1, avail / PAGE_W);
    if (p._z !== z) { p._z = z; p.style.setProperty('--z', z); }
  });
}
window.addEventListener('resize', throttleFrame(fitPapers));

function patchHtml(el, html) {
  if (el._html === html) return;
  el._html = html;
  const next = document.createElement('div');
  next.innerHTML = html;
  syncChildren(el, next, 4);
}
function sameAttrs(a, b) {
  if (a.attributes.length !== b.attributes.length) return false;
  for (const at of a.attributes) if (b.getAttribute(at.name) !== at.value) return false;
  return true;
}
function syncChildren(cur, next, depth) {
  const a = [...cur.childNodes];
  const b = [...next.childNodes];
  b.forEach((y, i) => {
    const x = a[i];
    if (!x) cur.appendChild(y);
    else if (x.nodeType !== y.nodeType || x.nodeName !== y.nodeName) cur.replaceChild(y, x);
    else if (x.nodeType !== 1) { if (x.nodeValue !== y.nodeValue) x.nodeValue = y.nodeValue; }
    else if (x.outerHTML !== y.outerHTML) {
      if (depth > 0 && sameAttrs(x, y)) syncChildren(x, y, depth - 1); else cur.replaceChild(y, x);
    }
  });
  for (let i = a.length - 1; i >= b.length; i--) cur.removeChild(a[i]);
}
// Sets innerHTML only when it changed (keeps focus and avoids flicker).
function setHtml(el, html) {
  if (el._html !== html) { el._html = html; el.innerHTML = html; }
}
// Runs fn at most once per animation frame.
function throttleFrame(fn) {
  let queued = false;
  return () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => { queued = false; fn(); });
  };
}

// ---------- login & shell ----------
async function showLogin() {
  let pub = { orgName: '', logo: '', demo: [] };
  try { pub = await api('/public'); } catch { /* still show the form */ }
  $('#app').innerHTML = `<div class="login"><form class="box">
    <div class="row" style="margin-bottom:18px">${pub.logo ? `<div class="login-logo">${logoImg(pub.logo, 64)}</div>` : '<div class="brand" style="padding:0;border:0"><div class="logo" style="background:var(--navy);color:#fff">OP</div></div>'}
    <div><h1 style="margin:0">TFC Protocol</h1><small>${esc(pub.orgName)} · Correspondence &amp; Templates</small></div></div>
    <label class="f"><span>Username</span><input type="text" name="username" autocomplete="username" required></label>
    <label class="f"><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>
    <button class="btn primary" style="width:100%;justify-content:center" type="submit">Sign in</button>
    ${pub.demo.length ? `<div class="demo"><b>Sample accounts</b> (password: <code>password123</code>)<br>
      <small>An administrator can hide this list in Administration → Settings.</small>
      <div class="tbl-wrap"><table class="tbl" style="margin-top:6px">${pub.demo.map((d) => `<tr><td><button type="button" data-u="${esc(d.username)}">${esc(d.username)}</button></td><td>${esc(ROLES[d.role])}</td><td>${esc(d.department || '')}</td></tr>`).join('')}</table></div></div>` : ''}
  </form></div>`;
  const form = $('.login form');
  $$('[data-u]', form).forEach((b) => b.onclick = () => { form.username.value = b.dataset.u; form.password.value = 'password123'; form.password.focus(); });
  form.onsubmit = async (e) => {
    e.preventDefault();
    await run($('button[type=submit]', form), async () => {
      await api('/login', { method: 'POST', body: { username: form.username.value, password: form.password.value } });
      await boot();
    });
  };
  form.username.focus();
}

function navItems() {
  const s = S.summary;
  const items = [
    { r: 'dashboard', label: 'Dashboard', icon: 'home' },
    { r: 'library', label: 'Template Library', icon: 'library' },
    { r: 'documents', label: 'My Documents', icon: 'doc', badge: s.returnedToMe },
  ];
  if (isRole('admin', 'protocol', 'head')) items.push({ r: 'approvals', label: 'Approvals', icon: 'check', badge: (s.pendingTemplates || 0) + (s.pendingDocs || 0) });
  if (isRole('admin', 'protocol', 'secretary')) items.push({ r: 'manage', label: 'Manage Templates', icon: 'edit' });
  items.push({ r: 'protocol', label: 'Protocol Handbook', icon: 'book' });
  if (!isRole('employee')) items.push({ r: 'reports', label: 'Reports', icon: 'chart' });
  if (isRole('admin')) items.push({ r: 'admin', label: 'Administration', icon: 'users' });
  if (isRole('admin', 'protocol')) items.push({ r: 'audit', label: 'Audit Trail', icon: 'log' });
  return items;
}

function renderNav() {
  const cur = (location.hash.split('/')[1] || 'dashboard');
  const map = { t: 'library', compose: 'library', doc: 'documents', edit: 'manage' };
  const active = map[cur] || cur;
  $('#nav').innerHTML = navItems().map((i) =>
    `<a href="#/${i.r}" class="${i.r === active ? 'on' : ''}">${icon(i.icon)}<span>${i.label}</span>${i.badge ? `<em>${i.badge}</em>` : ''}</a>`).join('');
  const bell = $('#bellCount');
  bell.textContent = S.summary.unread || '';
  bell.style.display = S.summary.unread ? '' : 'none';
}

async function refreshSummary() {
  try { S.summary = await api('/summary'); renderNav(); } catch { /* ignore */ }
}

function shell() {
  const initials = (S.settings.orgCode || 'OP').slice(0, 3);
  $('#app').innerHTML = `<div class="layout">
    <aside class="side" id="side">
      <div class="brand">${S.settings.logo ? `<div class="logo img">${logoImg(S.settings.logo, 34)}</div>` : `<div class="logo">${esc(initials)}</div>`}<div><b>TFC Protocol</b><small>${esc(S.settings.orgName)}</small></div></div>
      <nav id="nav"></nav>
      <div class="who"><b>${esc(S.me.name)}</b>${esc(ROLES[S.me.role])} · ${esc(deptName(S.me.departmentId))}
        <div class="row" style="margin-top:8px"><a href="/" style="color:#9fc0ff">TFC Portal</a> · <a href="#" id="pwBtn" style="color:#9fc0ff">Change password</a> · <a href="#" id="logoutBtn" style="color:#9fc0ff">Sign out</a></div></div>
    </aside>
    <div class="mainwrap">
      <header class="top">
        <button class="iconbtn menu-btn" id="menuBtn" aria-label="Menu">${icon('menu')}</button>
        <div class="top-title" id="pageTitle"></div>
        <button class="iconbtn" id="bellBtn" aria-label="Notifications">${icon('bell')}<em id="bellCount"></em></button>
      </header>
      <main id="main"></main>
    </div></div>`;
  $('#menuBtn').onclick = () => $('#side').classList.toggle('open');
  $('#nav').onclick = () => $('#side').classList.remove('open');
  $('#logoutBtn').onclick = async (e) => { e.preventDefault(); await api('/logout', { method: 'POST' }); location.href = '/'; };
  $('#pwBtn').onclick = (e) => { e.preventDefault(); changePassword(); };
  $('#bellBtn').onclick = toggleNotifications;
  renderNav();
}

function toggleNotifications(e) {
  e.stopPropagation();
  const open = $('.dropdown');
  if (open) return open.remove();
  const list = S.summary.notifications || [];
  const dd = document.createElement('div');
  dd.className = 'dropdown';
  dd.innerHTML = `<div class="row" style="padding:10px 14px;border-bottom:1px solid var(--line)"><b>Notifications</b></div>` +
    (list.length ? list.map((n) => `<a class="item ${n.read ? '' : 'unread'}" href="${esc(n.link || '#/dashboard')}">${esc(n.text)}<small>${ago(n.at)}</small></a>`).join('')
      : '<div class="empty">No notifications yet.</div>');
  $('.top').appendChild(dd);
  const close = () => { dd.remove(); document.removeEventListener('click', close); };
  setTimeout(() => document.addEventListener('click', close));
  if (S.summary.unread) api('/notifications/read', { method: 'POST' }).then(refreshSummary).catch(() => {});
}

function changePassword() {
  modal('Change password', `
    <label class="f"><span>Current password</span><input type="password" name="current" required></label>
    <label class="f"><span>New password</span><input type="password" name="next" minlength="8" required><small class="muted">At least 8 characters.</small></label>
    <label class="f"><span>Repeat new password</span><input type="password" name="again" required></label>`, {
    onSubmit: async (f) => {
      if (f.next.value !== f.again.value) throw new Error('The new passwords do not match.');
      await api('/password', { method: 'POST', body: { current: f.current.value, next: f.next.value } });
      toast('Password changed', 'ok');
    },
  });
}

// ---------- routing ----------
const VIEWS = [
  [/^#\/(dashboard)?$/, () => viewDashboard()],
  [/^#\/library$/, () => viewLibrary()],
  [/^#\/t\/(\w+)$/, (id) => viewTemplate(id, false)],
  [/^#\/t\/(\w+)\/review$/, (id) => viewTemplate(id, true)],
  [/^#\/compose\/(\w+)$/, (id) => viewCompose({ templateId: id })],
  [/^#\/doc\/(\w+)\/edit$/, (id) => viewCompose({ docId: id })],
  [/^#\/doc\/(\w+)$/, (id) => viewDoc(id)],
  [/^#\/documents$/, () => viewDocuments()],
  [/^#\/approvals$/, () => viewApprovals()],
  [/^#\/manage$/, () => viewManage()],
  [/^#\/edit\/(\w+)$/, (id) => viewEditor(id)],
  [/^#\/protocol$/, () => viewProtocol()],
  [/^#\/reports$/, () => viewReports()],
  [/^#\/admin$/, () => viewAdmin()],
  [/^#\/audit$/, () => viewAudit()],
];

// Pages limited to certain roles (the server enforces the same rules).
const PAGE_ROLES = {
  approvals: ['admin', 'protocol', 'head'],
  manage: ['admin', 'protocol', 'secretary'],
  edit: ['admin', 'protocol', 'secretary'],
  reports: ['admin', 'protocol', 'head', 'secretary'],
  admin: ['admin'],
  audit: ['admin', 'protocol'],
};

async function route() {
  if (!S.me) return;
  const h = location.hash || '#/dashboard';
  S.lastHash = h;
  for (const [re, fn] of VIEWS) {
    const m = h.match(re);
    if (!m) continue;
    renderNav();
    main().innerHTML = '<div class="loading">Loading…</div>';
    const roles = PAGE_ROLES[h.split('/')[1]];
    try { if (roles && !isRole(...roles)) throw new Error('This page is not available for your role.'); await fn(...m.slice(1).filter((x) => x !== undefined)); } catch (e) {
      main().innerHTML = `<div class="card empty"><h2>Cannot open this page</h2><p>${esc(e.message)}</p><a class="btn" href="#/dashboard">Back to dashboard</a></div>`;
    }
    window.scrollTo(0, 0);
    fitPapers();
    refreshSummary();
    return;
  }
  location.hash = '#/dashboard';
}

window.addEventListener('hashchange', () => {
  if (S.skipHash) { S.skipHash = false; return; }
  if (S.dirty && !confirm('You have unsaved changes. Leave this page without saving?')) {
    S.skipHash = true;
    location.hash = S.lastHash;
    return;
  }
  S.dirty = false;
  route();
});
window.addEventListener('beforeunload', (e) => { if (S.dirty) { e.preventDefault(); e.returnValue = ''; } });
const go = (hash) => { S.dirty = false; location.hash = hash; };

// ---------- dashboard ----------
async function viewDashboard() {
  setTitle('Dashboard');
  const [sum, tpls, docs] = await Promise.all([api('/summary'), api('/templates'), api('/documents?scope=mine')]);
  S.summary = sum;
  const usable = tpls.filter((t) => t.perms.use);
  const approver = isRole('admin', 'protocol', 'head');
  const count = (st) => docs.filter((d) => d.status === st).length;
  const popular = usable.slice().sort((a, b) => b.usage - a.usage).slice(0, 5);
  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>Welcome, ${esc(S.me.name.split(' ')[0])}</h1>
      <p>Find the approved template, follow the office protocol, and produce correctly formatted documents.</p></div></div>

    <div class="card"><form id="qs" class="row"><input type="search" name="q" placeholder="What do you need to write? e.g. interview letter, memo, quotation…" style="flex:1;min-width:200px">
      <button class="btn primary">Find template</button></form></div>

    <div class="grid g4" style="margin-bottom:16px">
      <a class="card stat" href="#/library"><div class="n">${usable.length}</div><div class="l">Templates available to you</div></a>
      <a class="card stat" href="#/documents"><div class="n">${count('draft')}</div><div class="l">My drafts</div></a>
      <a class="card stat" href="#/documents"><div class="n">${count('pending')}</div><div class="l">My documents awaiting approval</div></a>
      ${approver ? `<a class="card stat ${sum.pendingTemplates + sum.pendingDocs ? 'hl' : ''}" href="#/approvals"><div class="n">${sum.pendingTemplates + sum.pendingDocs}</div><div class="l">Waiting for my approval</div></a>`
        : `<a class="card stat ${sum.returnedToMe ? 'hl' : ''}" href="#/documents"><div class="n">${sum.returnedToMe}</div><div class="l">Returned to me for corrections</div></a>`}
    </div>

    <div class="card"><h2>How it works</h2><div class="steps">
      <div class="step"><b><span>1</span>Find</b><p>Search the library for the right template for your purpose.</p></div>
      <div class="step"><b><span>2</span>Read the guidance</b><p>Check when to use it, how to address the recipient and who signs.</p></div>
      <div class="step"><b><span>3</span>Fill in</b><p>Complete the form. The letter is formatted for you as you type.</p></div>
      <div class="step"><b><span>4</span>Approve &amp; download</b><p>Submit for approval if required, then download Word or PDF.</p></div>
    </div></div>

    <div class="grid g2">
      <div class="card"><h2>Most used templates</h2>
        ${popular.length ? `<table class="tbl">${popular.map((t) => `<tr class="click" data-go="#/t/${t.id}"><td><b>${esc(t.published.title)}</b><br><small>${esc(deptName(t.departmentId))} · ${esc(t.published.category)}</small></td><td class="num"><small>${t.usage} used</small></td></tr>`).join('')}</table>` : '<div class="empty">No templates yet.</div>'}
      </div>
      <div class="card"><h2>My recent documents</h2>
        ${docs.length ? `<table class="tbl">${docs.slice(0, 6).map((d) => `<tr class="click" data-go="#/doc/${d.id}"><td><b>${esc(d.title)}</b><br><small>${esc(d.ref || d.tpl.title)} · ${ago(d.updatedAt)}</small></td><td>${docBadge(d.status)}</td></tr>`).join('')}</table>`
          : '<div class="empty">You have not created any documents yet.<br><a href="#/library">Start from a template</a></div>'}
      </div>
    </div>
    ${sum.dueForReview.length ? `<div class="card"><h2>Templates due for review</h2><p class="muted">These templates have passed their review date. Check that they still follow current office protocol.</p>
      <table class="tbl">${sum.dueForReview.map((t) => `<tr class="click" data-go="#/edit/${t.id}"><td>${esc(t.title)}</td><td><small>Last approved ${fmtDate(t.approvedAt)}</small></td></tr>`).join('')}</table></div>` : ''}`;
  bindRowLinks();
  $('#qs').onsubmit = (e) => { e.preventDefault(); S.lib.q = e.target.q.value; go('#/library'); };
}

function bindRowLinks(root = main()) {
  $$('[data-go]', root).forEach((el) => el.onclick = (e) => { if (e.target.closest('a,button')) return; location.hash = el.dataset.go; });
}

// ---------- template library ----------
async function viewLibrary() {
  setTitle('Template Library');
  const tpls = (await api('/templates')).filter((t) => t.perms.use);
  const cats = [...new Set(tpls.map((t) => t.published.category))].sort();
  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>Template Library</h1><p>All approved templates. Open one to read its guidance and use it.</p></div></div>
    <div class="card row">
      <input type="search" id="q" placeholder="Search by name, purpose or keyword" style="flex:2;min-width:200px" value="${esc(S.lib.q)}">
      <select id="dept" style="flex:1;min-width:150px"><option value="">All departments</option>${S.departments.map((d) => `<option value="${d.id}" ${S.lib.dept === d.id ? 'selected' : ''}>${esc(d.name)}</option>`).join('')}</select>
      <select id="cat" style="flex:1;min-width:150px"><option value="">All document types</option>${cats.map((c) => `<option ${S.lib.cat === c ? 'selected' : ''}>${esc(c)}</option>`).join('')}</select>
    </div>
    <div id="cards"></div>`;
  const draw = () => {
    const q = S.lib.q.trim().toLowerCase();
    const list = tpls.filter((t) => {
      const p = t.published;
      if (S.lib.dept && t.departmentId !== S.lib.dept) return false;
      if (S.lib.cat && p.category !== S.lib.cat) return false;
      return !q || [p.title, p.category, p.description, p.whenToUse, deptName(t.departmentId)].join(' ').toLowerCase().includes(q);
    }).sort((a, b) => a.published.title.localeCompare(b.published.title));
    $('#cards').innerHTML = list.length ? `<div class="tcards">${list.map((t) => {
      const p = t.published;
      return `<a class="tcard" href="#/t/${t.id}">
        <div class="meta"><span class="badge info">${esc(p.category)}</span><span class="spacer"></span><span>v${p.version}</span></div>
        <h3>${esc(p.title)}</h3><p>${esc(p.description || p.whenToUse || '')}</p>
        <div class="meta"><span>${esc(deptName(t.departmentId))}</span>
          ${p.requiresApproval ? '<span class="badge warn">Needs approval</span>' : ''}
          ${p.confidential ? '<span class="badge bad">Confidential</span>' : ''}</div></a>`;
    }).join('')}</div>` : '<div class="card empty">No templates match your search.</div>';
  };
  $('#q').oninput = (e) => { S.lib.q = e.target.value; draw(); };
  $('#dept').onchange = (e) => { S.lib.dept = e.target.value; draw(); };
  $('#cat').onchange = (e) => { S.lib.cat = e.target.value; draw(); };
  draw();
}

function sampleValues(c, departmentId) {
  const v = { date: today(), department: deptName(departmentId), ref: `${S.settings.orgCode || 'ORG'}/SAMPLE/0001`, sender_name: S.me.name, sender_title: S.me.title };
  c.fields.forEach((f) => { if (f.example) v[f.key] = f.example; });
  return v;
}

function guidanceHtml(c) {
  return `<dl class="guide" style="margin:0">
    ${c.description ? `<dt>Purpose</dt><dd>${esc(c.description)}</dd>` : ''}
    ${c.whenToUse ? `<dt>When to use</dt><dd>${esc(c.whenToUse)}</dd>` : ''}
    ${c.audience ? `<dt>How to address the recipient</dt><dd>${esc(c.audience)}</dd>` : ''}
    ${c.signatory ? `<dt>Authorized signatory</dt><dd>${esc(c.signatory)}</dd>` : ''}
    <dt>Approval</dt><dd>${c.requiresApproval ? 'Each letter must be approved by your Head of Department before it becomes official.' : 'No approval needed. The letter is final when you generate it.'}</dd>
    ${c.attachments && c.attachments.length ? `<dt>Required attachments</dt><dd><ul>${c.attachments.map((a) => `<li>${esc(a)}</li>`).join('')}</ul></dd>` : ''}
    ${c.confidential ? '<dt>Confidentiality</dt><dd>Confidential. Only staff of the owning department can see this template.</dd>' : ''}
  </dl>`;
}

// ---------- template view / review ----------
async function viewTemplate(id, review) {
  const t = await api('/templates/' + id);
  const showDraft = review && t.draft;
  const c = showDraft ? t.draft : (t.published || t.draft);
  setTitle(c.title);
  const awaiting = t.draft && t.draft.status === 'submitted';
  const canDecide = awaiting && t.perms.approve && (t.draft.submittedBy !== S.me.id || isRole('admin'));
  main().innerHTML = `
    <div class="page-head"><div class="grow">
      <div class="row" style="margin-bottom:4px"><span class="badge info">${esc(c.category)}</span>${tplBadges(t)}</div>
      <h1>${esc(c.title)}</h1><p>${esc(deptName(t.departmentId))}${c.version ? ` · Version ${c.version}, approved ${fmtDate(c.approvedAt)} by ${esc(userName(c.approvedBy))}` : ''}</p></div>
      <div class="row">
        ${t.perms.manage ? `<a class="btn" href="#/edit/${t.id}">Edit template</a>` : ''}
        ${t.published && t.draft && (t.perms.manage || t.perms.approve) ? (showDraft ? `<a class="btn" href="#/t/${t.id}">View published version</a>` : `<a class="btn" href="#/t/${t.id}/review">View pending changes</a>`) : ''}
        ${t.perms.use ? `<a class="btn primary" href="#/compose/${t.id}">Use this template</a>` : ''}
      </div></div>
    ${showDraft ? `<div class="callout ${awaiting ? 'warn' : ''}"><b>${awaiting ? 'Submitted for approval' : 'Unpublished draft'}</b>
      ${awaiting ? `Submitted by ${esc(userName(t.draft.submittedBy))} on ${fmtDT(t.draft.submittedAt)}. ${t.published ? `This will replace version ${t.published.version}.` : 'This will be a new template.'}` : 'Employees cannot see these changes until they are approved.'}
      ${canDecide ? `<div class="row" style="margin-top:10px"><button class="btn ok" id="approveT">Approve &amp; publish</button><button class="btn danger" id="returnT">Return for corrections</button></div>` : ''}</div>` : ''}
    ${!showDraft && !t.published ? '<div class="callout warn"><b>Not yet published</b>This template is still a draft.</div>' : ''}
    <div class="split">
      <div>
        <div class="card"><h2>Guidance</h2>${guidanceHtml(c)}</div>
        ${c.fields.length ? `<div class="card"><h2>Information you will need</h2><table class="tbl">${c.fields.map((f) => `<tr><td><b>${esc(f.label)}</b>${f.required ? '' : ' <small>(optional)</small>'}${f.help ? `<br><small>${esc(f.help)}</small>` : ''}</td></tr>`).join('')}</table></div>` : ''}
        <div class="card"><h2>Version history</h2>${t.versions.length ? `<ul class="timeline">${t.versions.slice().reverse().map((v) => `<li><b>Version ${v.version}</b>${v.note ? ` – ${esc(v.note)}` : ''}<small>Approved by ${esc(userName(v.approvedBy))} on ${fmtDT(v.approvedAt)}${v.submittedBy ? ` · prepared by ${esc(userName(v.submittedBy))}` : ''}</small></li>`).join('')}</ul>` : '<p class="muted">Not published yet.</p>'}</div>
      </div>
      <div class="sticky"><div class="row" style="margin-bottom:8px"><h2 style="margin:0">Sample letter</h2><small class="muted">Filled with example information</small></div>
        <div class="paper">${letterHtml({ body: c.body, fields: c.fields, logos: c.logos, headerTextTop: c.headerTextTop, headerImage: c.headerImage, footerImages: c.footerImages, bodyImages: c.bodyImages, values: sampleValues(c, t.departmentId), departmentId: t.departmentId, preview: true, watermark: 'SAMPLE' })}</div></div>
    </div>`;
  if (canDecide) {
    $('#approveT').onclick = () => modal('Approve and publish template', `<p>Once approved, <b>${esc(c.title)}</b> becomes the official version for everyone.</p>
      <label class="f"><span>What changed? (shown in version history)</span><input type="text" name="note" placeholder="e.g. Updated signatory rules"></label>`, {
      submitLabel: 'Approve & publish',
      onSubmit: async (f) => { await api(`/templates/${t.id}/approve`, { method: 'POST', body: { note: f.note.value } }); toast('Template published', 'ok'); go('#/t/' + t.id); },
    });
    $('#returnT').onclick = () => modal('Return template for corrections', `<label class="f"><span>What needs to be corrected?</span><textarea name="note" required></textarea></label>`, {
      submitLabel: 'Return to secretary',
      onSubmit: async (f) => { await api(`/templates/${t.id}/return`, { method: 'POST', body: { note: f.note.value } }); toast('Returned for corrections'); go('#/approvals'); },
    });
  }
}

// ---------- compose a document ----------
function inputFor(f, value) {
  const attrs = `name="${esc(f.key)}" data-k="${esc(f.key)}"`;
  if (f.type === 'textarea') return `<textarea ${attrs} rows="4">${esc(value)}</textarea>`;
  if (f.type === 'select') return `<select ${attrs}><option value="">— Choose —</option>${f.options.map((o) => `<option ${o === value ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
  const type = { date: 'date', number: 'number' }[f.type] || 'text';
  return `<input type="${type}" ${attrs} value="${esc(value)}">`;
}

async function viewCompose({ templateId, docId }) {
  let doc = null;
  let tpl;
  let departmentId = S.me.departmentId;
  if (docId) {
    doc = await api('/documents/' + docId);
    if (!doc.perms.edit) { go('#/doc/' + doc.id); return; }
    tpl = doc.tpl;
    departmentId = doc.departmentId;
  } else {
    const t = await api('/templates/' + templateId);
    if (!t.perms.use) throw new Error('This template is not available for use.');
    tpl = { ...t.published, templateId: t.id };
  }
  const values = doc ? { ...doc.values } : { date: today(), sender_name: S.me.name, sender_title: S.me.title };
  setTitle(doc ? 'Edit: ' + doc.title : 'New: ' + tpl.title);
  const finishLabel = tpl.requiresApproval ? 'Submit for approval' : 'Generate final letter';
  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>${esc(tpl.title)}</h1><p>Fill in the form. The letter on the right updates as you type.</p></div></div>
    <div class="callout ok"><b>You are working on your own copy</b>Filling in this form creates a separate document. The official template (version ${tpl.version}) is never changed, so everyone else keeps getting the original.</div>
    ${doc && doc.status === 'returned' ? `<div class="callout bad"><b>Returned for corrections</b>${esc(doc.reviewNote)}</div>` : ''}
    <div class="split">
      <form id="cf" autocomplete="off">
        <details class="card" ${doc ? '' : 'open'}><summary style="cursor:pointer"><b>How to use this template</b></summary><div style="margin-top:8px">${guidanceHtml(tpl)}</div></details>
        <div class="card"><h2>Letter content</h2>
          ${tpl.fields.map((f) => `<label class="f"><span>${esc(f.label)} ${f.required ? '<i>*</i>' : '<small class="muted">(optional)</small>'}</span>${inputFor(f, values[f.key] || '')}${f.help ? `<small class="muted">${esc(f.help)}</small>` : ''}</label>`).join('') || '<p class="muted">This template has no fields to fill.</p>'}
        </div>
        <div class="card"><h2>Date and signatory</h2>
          <div class="grid g2"><label class="f"><span>Letter date <i>*</i></span><input type="date" data-k="date" value="${esc(values.date || '')}"></label><div></div></div>
          <div class="grid g2">
            <label class="f"><span>Signatory name <i>*</i></span><input type="text" data-k="sender_name" value="${esc(values.sender_name || '')}"></label>
            <label class="f"><span>Signatory title</span><input type="text" data-k="sender_title" value="${esc(values.sender_title || '')}"></label>
          </div>
          ${tpl.signatory ? `<small class="muted">Authorized signatory for this template: ${esc(tpl.signatory)}</small>` : ''}
        </div>
        ${tpl.attachments.length ? `<div class="card"><h2>Required attachments</h2><p class="muted">Tick each item to confirm it is attached.</p>
          ${tpl.attachments.map((a, i) => `<label class="check"><input type="checkbox" class="att" data-i="${i}" ${doc && doc.attachmentsConfirmed ? 'checked' : ''}><span>${esc(a)}</span></label>`).join('')}</div>` : ''}
        <div class="card"><p class="muted" style="margin:0">The reference number is assigned automatically ${tpl.requiresApproval ? 'when the letter is approved' : 'when you generate the final letter'}.
          ${tpl.requiresApproval ? 'After submission, your Head of Department will review it.' : ''}</p></div>
      </form>
      <div class="sticky"><div class="paper" id="pv"></div></div>
    </div>
    <div class="actionbar">
      <button class="btn" id="saveDraft">Save draft</button>
      <span class="spacer"></span>
      <a class="btn ghost" href="${doc ? '#/doc/' + doc.id : '#/t/' + tpl.templateId}">Cancel</a>
      <button class="btn primary" id="finish">${finishLabel}</button>
    </div>`;
  const form = $('#cf');
  const read = () => {
    $$('[data-k]', form).forEach((el) => { values[el.dataset.k] = el.value; });
    return values;
  };
  const preview = throttleFrame(() => {
    patchHtml($('#pv'), letterHtml({ body: tpl.body, fields: tpl.fields, logos: tpl.logos, headerTextTop: tpl.headerTextTop, headerImage: tpl.headerImage, footerImages: tpl.footerImages, bodyImages: tpl.bodyImages, values: { ...read(), ref: doc && doc.ref ? doc.ref : 'To be assigned' }, departmentId, preview: true }));
  });
  form.addEventListener('input', () => { S.dirty = true; preview(); });
  form.addEventListener('change', preview);
  preview();

  const attachmentsOk = () => $$('.att', form).every((c) => c.checked);
  const save = async (btn, action) => {
    read();
    if (action === 'complete') {
      $$('.invalid', form).forEach((el) => el.classList.remove('invalid'));
      const missing = tpl.fields.filter((f) => f.required && !String(values[f.key] || '').trim());
      if (!values.date) missing.push({ key: 'date' });
      if (!String(values.sender_name || '').trim()) missing.push({ key: 'sender_name' });
      missing.forEach((f) => { const el = $(`[data-k="${f.key}"]`, form); if (el) el.classList.add('invalid'); });
      if (missing.length) {
        toast('Please complete the highlighted fields.', 'bad');
        $('.invalid', form).focus();
        return;
      }
      if (!attachmentsOk()) { toast('Please confirm all required attachments.', 'bad'); return; }
      if (!tpl.requiresApproval && !(await confirmBox('Generate final letter', 'This will assign an official reference number and the letter can no longer be edited. Continue?', 'Generate'))) return;
    }
    await run(btn, async () => {
      const body = { values, attachmentsConfirmed: attachmentsOk(), action };
      const d = doc
        ? await api('/documents/' + doc.id, { method: 'PUT', body })
        : await api('/documents', { method: 'POST', body: { ...body, templateId: tpl.templateId } });
      toast(action === 'save' ? 'Draft saved' : (d.status === 'final' ? `Letter generated: ${d.ref}` : 'Submitted for approval'), 'ok');
      go('#/doc/' + d.id);
    });
  };
  $('#saveDraft').onclick = (e) => save(e.currentTarget, 'save');
  $('#finish').onclick = (e) => save(e.currentTarget, 'complete');
}

// ---------- document view ----------
async function viewDoc(id) {
  const d = await api('/documents/' + id);
  setTitle(d.title);
  const final = d.status === 'final';
  main().innerHTML = `
    <div class="page-head"><div class="grow">
      <div class="row" style="margin-bottom:4px">${docBadge(d.status)}<span class="badge">${esc(d.tpl.title)} v${d.tpl.version}</span></div>
      <h1>${esc(d.title)}</h1>
      <p>${d.ref ? `<b>${esc(d.ref)}</b> · ` : ''}${esc(deptName(d.departmentId))} · prepared by ${esc(userName(d.createdBy))} · updated ${fmtDT(d.updatedAt)}</p></div>
      <div class="row no-print">
        ${d.perms.edit ? `<a class="btn" href="#/doc/${d.id}/edit">Edit</a>` : ''}
        ${d.perms.remove ? '<button class="btn danger" id="delD">Delete</button>' : ''}
        <a class="btn" href="/protocol/api/documents/${d.id}/export?format=doc">Download Word</a>
        <a class="btn primary" href="/protocol/api/documents/${d.id}/export?format=print" target="_blank" rel="noopener">Print / Save PDF</a>
      </div></div>
    ${d.status === 'returned' ? `<div class="callout bad"><b>Returned for corrections</b>${esc(d.reviewNote)}<div style="margin-top:8px"><a class="btn sm" href="#/doc/${d.id}/edit">Make corrections</a></div></div>` : ''}
    ${d.status === 'pending' ? `<div class="callout warn"><b>Awaiting approval</b>This letter is not official until it is approved. ${d.perms.approve ? 'You can approve it or return it for corrections.' : 'You will be notified when it is reviewed.'}
      ${d.perms.approve ? '<div class="row" style="margin-top:10px"><button class="btn ok" id="appD">Approve</button><button class="btn danger" id="retD">Return for corrections</button></div>' : ''}</div>` : ''}
    ${d.status === 'draft' ? '<div class="callout"><b>Draft</b>This letter has not been finalized. Downloads are marked DRAFT.</div>' : ''}
    ${final ? `<div class="callout ok"><b>Official document</b>Reference ${esc(d.ref)}${d.approvedBy ? ` · approved by ${esc(userName(d.approvedBy))} on ${fmtDT(d.approvedAt)}` : ''}</div>` : ''}
    <div class="split">
      <div class="paper">${letterHtml({ body: d.tpl.body, fields: d.tpl.fields, logos: d.tpl.logos, headerTextTop: d.tpl.headerTextTop, headerImage: d.tpl.headerImage, footerImages: d.tpl.footerImages, bodyImages: d.tpl.bodyImages, values: { ...d.values, ref: d.ref || 'To be assigned' }, departmentId: d.departmentId, preview: !final, watermark: final ? '' : 'DRAFT' })}</div>
      <div>
        <div class="card"><h2>History</h2><ul class="timeline">${d.history.slice().reverse().map((h) => `<li><b>${esc(h.action)}</b>${h.note ? ` – ${esc(h.note)}` : ''}<small>${esc(userName(h.by))} · ${fmtDT(h.at)}</small></li>`).join('')}</ul></div>
        ${d.tpl.attachments.length ? `<div class="card"><h2>Attachments</h2><ul style="margin:0;padding-left:18px">${d.tpl.attachments.map((a) => `<li>${esc(a)}</li>`).join('')}</ul>
          <small class="muted">${d.attachmentsConfirmed ? 'Confirmed as attached by the author.' : 'Not yet confirmed.'}</small></div>` : ''}
      </div>
    </div>`;
  if ($('#delD')) $('#delD').onclick = async (e) => {
    if (!(await confirmBox('Delete document', 'Delete this document permanently?', 'Delete', true))) return;
    await run(e.target, async () => { await api('/documents/' + d.id, { method: 'DELETE' }); toast('Document deleted'); go('#/documents'); });
  };
  if ($('#appD')) {
    $('#appD').onclick = () => modal('Approve document', `<p>Approving assigns the official reference number and makes the letter final.</p>
      <label class="f"><span>Comment (optional)</span><input type="text" name="note"></label>`, {
      submitLabel: 'Approve',
      onSubmit: async (f) => { const r = await api(`/documents/${d.id}/approve`, { method: 'POST', body: { note: f.note.value } }); toast('Approved: ' + r.ref, 'ok'); route(); },
    });
    $('#retD').onclick = () => modal('Return for corrections', `<label class="f"><span>What needs to be corrected?</span><textarea name="note" required></textarea></label>`, {
      submitLabel: 'Return to author',
      onSubmit: async (f) => { await api(`/documents/${d.id}/return`, { method: 'POST', body: { note: f.note.value } }); toast('Returned to the author'); route(); },
    });
  }
}

// ---------- document lists ----------
function docTable(docs, showAuthor) {
  if (!docs.length) return '<div class="empty">No documents here.</div>';
  return `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Document</th><th>Reference</th>${showAuthor ? '<th>Prepared by</th>' : ''}<th>Status</th><th>Updated</th></tr></thead><tbody>
    ${docs.map((d) => `<tr class="click" data-go="#/doc/${d.id}"><td><b>${esc(d.title)}</b><br><small>${esc(d.tpl.title)}</small></td><td>${esc(d.ref || '—')}</td>
      ${showAuthor ? `<td>${esc(userName(d.createdBy))}<br><small>${esc(deptName(d.departmentId))}</small></td>` : ''}<td>${docBadge(d.status)}</td><td><small>${fmtDT(d.updatedAt)}</small></td></tr>`).join('')}
  </tbody></table></div>`;
}

async function viewDocuments() {
  setTitle('My Documents');
  const tabs = [['mine', 'My documents']];
  if (isRole('head', 'admin')) tabs.push(['review', 'My department']);
  if (isRole('admin', 'protocol')) tabs.push(['all', 'All documents']);
  let scope = 'mine';
  let status = '';
  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>Documents</h1><p>Letters and memos created from templates.</p></div><a class="btn primary" href="#/library">New document</a></div>
    <div class="card">${tabs.length > 1 ? `<div class="tabs">${tabs.map(([k, l]) => `<button data-s="${k}" class="${k === scope ? 'on' : ''}">${l}</button>`).join('')}</div>` : ''}
      <div class="row" style="margin-bottom:12px"><select id="st" style="max-width:240px"><option value="">All statuses</option>${Object.entries(DOC_STATUS).map(([k, v]) => `<option value="${k}">${v[0]}</option>`).join('')}</select></div>
      <div id="list"></div></div>`;
  let docs = [];
  const draw = () => {
    $('#list').innerHTML = docTable(docs.filter((d) => !status || d.status === status), scope !== 'mine');
    bindRowLinks();
  };
  const load = async () => { docs = await api('/documents?scope=' + scope); draw(); };
  $$('.tabs button').forEach((b) => b.onclick = () => { scope = b.dataset.s; $$('.tabs button').forEach((x) => x.classList.toggle('on', x === b)); load(); });
  $('#st').onchange = (e) => { status = e.target.value; draw(); };
  await load();
}

// ---------- approvals ----------
async function viewApprovals() {
  setTitle('Approvals');
  const [tpls, docs] = await Promise.all([api('/templates'), api('/documents?scope=review')]);
  const tq = tpls.filter((t) => t.draft && t.draft.status === 'submitted' && t.perms.approve && (t.draft.submittedBy !== S.me.id || isRole('admin')));
  const dq = docs.filter((d) => d.status === 'pending' && d.perms.approve);
  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>Approvals</h1><p>Approving a <b>template</b> makes it the official standard. Approving a <b>document</b> authorizes one specific letter.</p></div></div>
    <div class="card"><h2>Templates waiting for approval (${tq.length})</h2>
      ${tq.length ? `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Template</th><th>Department</th><th>Submitted by</th><th>Change</th></tr></thead><tbody>
        ${tq.map((t) => `<tr class="click" data-go="#/t/${t.id}/review"><td><b>${esc(t.draft.title)}</b><br><small>${esc(t.draft.category)}</small></td><td>${esc(deptName(t.departmentId))}</td>
          <td>${esc(userName(t.draft.submittedBy))}<br><small>${fmtDT(t.draft.submittedAt)}</small></td><td>${t.published ? `Update to v${t.published.version}` : '<span class="badge info">New template</span>'}</td></tr>`).join('')}
      </tbody></table></div>` : '<div class="empty">Nothing waiting. Well done!</div>'}</div>
    <div class="card"><h2>Documents waiting for approval (${dq.length})</h2>${dq.length ? docTable(dq, true) : '<div class="empty">Nothing waiting.</div>'}</div>`;
  bindRowLinks();
}

// ---------- manage templates ----------
async function viewManage() {
  setTitle('Manage Templates');
  const tpls = (await api('/templates')).filter((t) => t.perms.manage);
  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>Manage Templates</h1><p>Create and update your department's templates. Changes must be approved before staff can use them.</p></div>
      <a class="btn primary" href="#/edit/new">New template</a></div>
    <div class="card">${tpls.length ? `<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Template</th><th>Department</th><th>Status</th><th class="num">Used</th><th></th></tr></thead><tbody>
      ${tpls.sort((a, b) => (a.published || a.draft).title.localeCompare((b.published || b.draft).title)).map((t) => {
        const c = t.draft || t.published;
        return `<tr><td><b>${esc(c.title)}</b><br><small>${esc(c.category)}</small>${t.draft && t.draft.status === 'returned' ? `<br><small style="color:var(--bad)">Returned: ${esc(t.draft.reviewNote)}</small>` : ''}</td>
          <td>${esc(deptName(t.departmentId))}</td><td>${tplBadges(t)}</td><td class="num">${t.usage}</td>
          <td><div class="row end"><a class="btn sm" href="#/edit/${t.id}">Edit</a><a class="btn sm" href="#/t/${t.id}${t.draft ? '/review' : ''}">Preview</a>
          ${t.published ? `<button class="btn sm ghost" data-arch="${t.id}" data-v="${t.archived ? 0 : 1}">${t.archived ? 'Restore' : 'Archive'}</button>` : ''}</div></td></tr>`;
      }).join('')}</tbody></table></div>` : '<div class="empty">No templates yet. Click "New template" to create the first one.</div>'}</div>`;
  $$('[data-arch]').forEach((b) => b.onclick = async () => {
    const archive = b.dataset.v === '1';
    if (archive && !(await confirmBox('Archive template', 'Staff will no longer be able to use this template. Existing documents are kept. Continue?', 'Archive'))) return;
    await run(b, async () => { await api(`/templates/${b.dataset.arch}/archive`, { method: 'POST', body: { archived: archive } }); toast(archive ? 'Template archived' : 'Template restored'); route(); });
  });
}

const STARTER_BODY = `Ref: {{ref}}
Date: {{date}}

{{recipient_name}}
{{recipient_address}}

Dear Sir/Madam,

**RE: {{subject}}**

{{message}}

Yours faithfully,

{{sender_name}}
{{sender_title}}`;

function labelFromKey(k) { return k.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase()); }

async function viewEditor(id) {
  const isNew = id === 'new';
  const t = isNew ? null : await api('/templates/' + id);
  if (t && !t.perms.manage) throw new Error('You cannot edit this template.');
  const src = t ? (t.draft || t.published) : {
    title: '', category: 'Official Letter', description: '', whenToUse: '', audience: '', signatory: '', attachments: [],
    confidential: false, requiresApproval: false, reviewMonths: 12, body: STARTER_BODY,
    fields: [
      { key: 'recipient_name', label: 'Recipient name', type: 'text', required: true, help: '', example: 'The Director', options: [] },
      { key: 'recipient_address', label: 'Recipient address', type: 'textarea', required: true, help: '', example: 'P.O. Box 100\nCity', options: [] },
      { key: 'subject', label: 'Subject', type: 'text', required: true, help: 'Capital letters, short and specific.', example: 'REQUEST FOR INFORMATION', options: [] },
      { key: 'message', label: 'Message', type: 'textarea', required: true, help: 'Separate paragraphs with a blank line.', example: 'We kindly request…', options: [] },
    ],
  };
  const c = JSON.parse(JSON.stringify(src));
  c.logos = Array.isArray(c.logos) ? Render.normLogos(c.logos) : null;
  if (c.headerTextTop === undefined) c.headerTextTop = null;
  if (!Array.isArray(c.bodyImages)) c.bodyImages = [];
  if (!c.headerImage) c.headerImage = null;
  if (!Array.isArray(c.footerImages)) c.footerImages = [];
  let uploads = [];
  try { uploads = await api('/uploads'); } catch { /* gallery stays empty */ }
  let departmentId = t ? t.departmentId : S.me.departmentId;
  const chooseDept = isRole('admin', 'protocol');
  const locked = t && t.draft && t.draft.status === 'submitted';
  setTitle(isNew ? 'New template' : 'Edit: ' + c.title);

  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>${isNew ? 'New template' : esc(c.title)}</h1>
      <p>${t && t.published ? `Published version ${t.published.version} stays in use until your changes are approved.` : 'Not yet published.'}</p></div>
      <div class="row">${t ? tplBadges(t) : ''}</div></div>
    ${t && t.draft && t.draft.status === 'returned' ? `<div class="callout bad"><b>Returned for corrections by ${esc(userName(t.draft.returnedBy))}</b>${esc(t.draft.reviewNote)}</div>` : ''}
    ${locked ? '<div class="callout warn"><b>Waiting for approval</b>Saving changes will withdraw the submission. You will need to submit again.</div>' : ''}
    <div class="split">
      <form id="ef" autocomplete="off">
        <div class="card"><h2>1. Basic information</h2>
          <label class="f"><span>Template title <i>*</i></span><input type="text" data-c="title" value="${esc(c.title)}" placeholder="e.g. Interview Invitation Letter"></label>
          <div class="grid g2">
            <label class="f"><span>Document type</span><input type="text" data-c="category" list="cats" value="${esc(c.category)}"><datalist id="cats">${CATEGORIES.map((x) => `<option>${esc(x)}</option>`).join('')}</datalist></label>
            <label class="f"><span>Department</span>${chooseDept ? `<select id="deptSel">${S.departments.map((d) => `<option value="${d.id}" ${d.id === departmentId ? 'selected' : ''}>${esc(d.name)}</option>`).join('')}</select>` : `<input type="text" value="${esc(deptName(departmentId))}" disabled>`}</label>
          </div>
          <label class="f"><span>Short description</span><input type="text" data-c="description" value="${esc(c.description)}" placeholder="What is this document for?"></label>
        </div>
        <div class="card"><h2>2. Guidance for staff</h2>
          <label class="f"><span>When to use it</span><textarea data-c="whenToUse" rows="3" placeholder="Situations where this template must be used, deadlines, conditions…">${esc(c.whenToUse)}</textarea></label>
          <label class="f"><span>How to address the recipient</span><textarea data-c="audience" rows="2" placeholder="Salutation, titles, closing (Yours faithfully / sincerely)…">${esc(c.audience)}</textarea></label>
          <label class="f"><span>Authorized signatory</span><input type="text" data-c="signatory" value="${esc(c.signatory)}" placeholder="Who is allowed to sign?"></label>
          <label class="f"><span>Required attachments</span><textarea data-c="attachments" rows="2" placeholder="One per line">${esc(c.attachments.join('\n'))}</textarea><small class="muted">Staff must tick each one before finalizing.</small></label>
        </div>
        <div class="card"><div class="row" style="margin-bottom:6px"><h2 style="margin:0">3. Images</h2><span class="spacer"></span><button type="button" class="btn sm primary" id="upImg">Upload image</button></div>
          <p class="muted">All images for this template in one place: the <b>header</b> (up to ${Render.MAX_LOGOS} logos placed anywhere, and 1 image under the address line), the <b>body</b> of the letter, and the <b>footer</b> (up to 3 images).
            Upload an image once, then use it anywhere. You can also drag images straight onto the preview: into the header, the letter or the footer.</p>
          <h3>Image library</h3>
          <p class="muted" style="margin-bottom:6px">Click an image to choose where it goes (header, body or footer), or drag it onto the preview to put it exactly where you want.</p>
          <div id="imgLib"></div>
          <div class="img-part"><h3>Header</h3>
          <h4>Logos</h4>
          <label class="check"><input type="radio" name="logoMode" value="default" ${c.logos ? '' : 'checked'}><span><b>Use the organization logo</b><br><small class="muted">The logo set by the administrator (currently the coat of arms), centered at the top.</small></span></label>
          <label class="check"><input type="radio" name="logoMode" value="custom" ${c.logos ? 'checked' : ''}><span><b>Choose logos for this template</b><br><small class="muted">Add up to ${Render.MAX_LOGOS} logos and drag each one anywhere on the letterhead in the preview: left, right, up or down.</small></span></label>
          <div id="logoSlots"></div>
          <h4>Image at the bottom of the header</h4>
          <p class="muted" style="margin-bottom:6px">For example a banner or flag stripe under the address line.</p>
          <div id="bannerSlot"></div></div>
          <div class="img-part"><h3>Body</h3>
          <p class="muted" style="margin-bottom:6px">Stamps, signatures, diagrams. Drag them in the preview to move them, or drop one onto another to put them side by side.</p>
          <div id="imgList"></div></div>
          <div class="img-part"><h3>Footer</h3>
          <div id="footSlots"></div></div>
        </div>
        <div class="card"><h2>4. Letter layout</h2>
          <p class="muted">Write the letter exactly as it should look. Put <code>{{field_name}}</code> where staff should fill in information.
            Leave a blank line between paragraphs. Use <code>**text**</code> for bold, <code>- item</code> for bullet lists. Images appear as <code>[[image:…|width|position]]</code>.</p>
          <div style="margin-bottom:8px"><small class="muted">Filled automatically:</small> <span class="chips">${Object.entries(Render.SYSTEM_FIELDS).map(([k, l]) => `<button type="button" class="chip sys" data-ins="${k}" title="${esc(l)}">{{${k}}}</button>`).join('')}</span></div>
          <div style="margin-bottom:8px" id="customChips"></div>
          <textarea class="code" id="body" spellcheck="true">${esc(c.body)}</textarea>
          <div id="bodyWarn"></div>
        </div>
        <div class="card"><div class="row" style="margin-bottom:10px"><h2 style="margin:0">5. Fields staff fill in</h2><span class="spacer"></span>
          <button type="button" class="btn sm" id="detect">Detect fields from letter</button><button type="button" class="btn sm" id="addField">Add field</button></div>
          <div id="fields"></div></div>
        <div class="card"><h2>6. Rules</h2>
          <label class="check"><input type="checkbox" data-c="requiresApproval" ${c.requiresApproval ? 'checked' : ''}><span><b>Each letter needs approval</b><br><small class="muted">The Head of Department must approve every letter before it becomes official.</small></span></label>
          <label class="check"><input type="checkbox" data-c="confidential" ${c.confidential ? 'checked' : ''}><span><b>Confidential</b><br><small class="muted">Only staff of this department can see and use the template.</small></span></label>
          <label class="f" style="max-width:260px;margin-top:10px"><span>Review every (months)</span><input type="number" min="0" max="60" data-c="reviewMonths" value="${c.reviewMonths}"><small class="muted">You'll be reminded to review it. 0 = no reminder.</small></label>
        </div>
        ${t && t.versions.length ? `<div class="card"><h2>Earlier versions</h2><table class="tbl">${t.versions.slice().reverse().map((v) => `<tr><td><b>v${v.version}</b> ${esc(v.note || '')}<br><small>${fmtDT(v.approvedAt)} · ${esc(userName(v.approvedBy))}</small></td><td class="num"><button type="button" class="btn sm" data-restore="${v.version}">Copy into draft</button></td></tr>`).join('')}</table></div>` : ''}
      </form>
      <div class="sticky pv-col"><div class="row" style="margin-bottom:8px"><h2 style="margin:0">Live preview</h2><small class="muted">Using the example values · scroll here to see the whole page</small></div>
        <div id="imgTools" class="imgtools"></div><div class="paper drag-zone" id="pv"></div></div>
    </div>
    <div class="actionbar">
      ${t && t.draft ? `<button class="btn danger" id="discard">${t.published ? 'Discard changes' : 'Delete draft'}</button>` : ''}
      <span class="spacer"></span>
      <button class="btn" id="saveT">Save draft</button>
      <button class="btn primary" id="submitT">Submit for approval</button>
    </div>`;

  const form = $('#ef');
  const bodyEl = $('#body');

  const drawFields = () => {
    $('#fields').innerHTML = c.fields.length ? c.fields.map((f, i) => `<div class="field-row" data-i="${i}">
      <div class="grid g3">
        <label class="f"><span>Label shown to staff</span><input type="text" data-f="label" value="${esc(f.label)}"></label>
        <label class="f"><span>Placeholder name</span><input type="text" data-f="key" value="${esc(f.key)}" style="font-family:monospace"></label>
        <label class="f"><span>Type</span><select data-f="type">${Object.entries(FIELD_TYPES).map(([k, l]) => `<option value="${k}" ${f.type === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
      </div>
      <div class="grid g2">
        <label class="f"><span>Help text</span><input type="text" data-f="help" value="${esc(f.help)}" placeholder="Instructions for staff"></label>
        <label class="f"><span>Example (used in sample letter)</span>${f.type === 'textarea' || f.example.includes('\n') ? `<textarea data-f="example" rows="2">${esc(f.example)}</textarea>` : `<input type="text" data-f="example" value="${esc(f.example)}">`}</label>
      </div>
      ${f.type === 'select' ? `<label class="f"><span>Choices (separate with commas)</span><input type="text" data-f="options" value="${esc(f.options.join(', '))}"></label>` : ''}
      <div class="row"><label class="check" style="margin:0"><input type="checkbox" data-f="required" ${f.required ? 'checked' : ''}><span>Required</span></label><span class="spacer"></span>
        <button type="button" class="btn sm ghost" data-up="${i}" ${i === 0 ? 'disabled' : ''}>↑</button><button type="button" class="btn sm ghost" data-down="${i}" ${i === c.fields.length - 1 ? 'disabled' : ''}>↓</button>
        <button type="button" class="btn sm danger" data-del="${i}">Remove</button></div>
    </div>`).join('') : '<p class="muted">No fields yet. Add <code>{{placeholders}}</code> to the letter and click "Detect fields from letter".</p>';
  };

  const read = () => {
    $$('[data-c]', form).forEach((el) => {
      const k = el.dataset.c;
      if (el.type === 'checkbox') c[k] = el.checked;
      else if (k === 'attachments') c[k] = el.value.split('\n').map((s) => s.trim()).filter(Boolean);
      else if (k === 'reviewMonths') c[k] = parseInt(el.value, 10) || 0;
      else c[k] = el.value;
    });
    c.body = bodyEl.value;
    $$('.field-row', form).forEach((row) => {
      const f = c.fields[row.dataset.i];
      $$('[data-f]', row).forEach((el) => {
        const k = el.dataset.f;
        if (k === 'required') f.required = el.checked;
        else if (k === 'options') f.options = el.value.split(',').map((s) => s.trim()).filter(Boolean);
        else if (k === 'key') f.key = el.value.trim().toLowerCase().replace(/[^a-z0-9_]+/g, '_');
        else f[k] = el.value;
      });
    });
    if ($('#deptSel')) departmentId = $('#deptSel').value;
  };

  const refresh = () => {
    const keys = Render.fieldKeys(c.body);
    const defined = new Set(c.fields.map((f) => f.key));
    const unknown = keys.filter((k) => !defined.has(k) && !Render.SYSTEM_FIELDS[k]);
    const unused = c.fields.filter((f) => !keys.includes(f.key)).map((f) => f.key);
    $('#bodyWarn').innerHTML = (unknown.length ? `<div class="callout warn" style="margin:10px 0 0"><b>Not defined as fields yet</b>${unknown.map((k) => `<code>{{${esc(k)}}}</code>`).join(' ')} — click "Detect fields from letter".</div>` : '') +
      (unused.length ? `<div class="callout" style="margin:10px 0 0"><b>Fields not used in the letter</b>${unused.map((k) => `<code>${esc(k)}</code>`).join(' ')}</div>` : '');
    const imgs = Render.imageKeys(c.body);
    setHtml($('#imgList'), (c.bodyImages.length ? `<small class="muted">Free images (drag them anywhere in the preview):</small>
      <div class="gallery" style="margin:6px 0 10px">${c.bodyImages.map((b, i) => `<div class="slot" style="width:150px"><div class="slot-img">${Render.imgTag(b.file, 0, '/protocol/uploads/', 60)}</div>
        <div class="row"><button type="button" class="btn sm" data-bisel="${i}">Select</button><button type="button" class="btn sm danger" data-bidel="${i}">Remove</button></div></div>`).join('')}</div>` : '') + (imgs.length ? `<small class="muted">Used in this letter – click one to change its size or position, or to remove it:</small>
      <div class="gallery" style="margin-top:6px">${imgs.map((f) => `<button type="button" class="imgthumb big" data-imgedit="${esc(f)}">${Render.imgTag(f, 0, '/protocol/uploads/', 70)}<small>${esc(uploadName(f))}</small></button>`).join('')}</div>`
      : (c.bodyImages.length ? '' : '<p class="muted" style="margin:0"><i>No images in the letter yet.</i></p>')));
    setHtml($('#imgLib'), uploads.length ? `<div class="gallery">${uploads.map((u) => `<button type="button" class="imgthumb big" draggable="true" data-pick="${esc(u.file)}" title="Click or drag into the letter">${Render.imgTag(u.file, 0, '/protocol/uploads/', 70)}<small>${esc(u.name)}</small></button>`).join('')}</div>`
      : '<p class="muted">No images uploaded yet. Click "Upload image".</p>');
    setHtml($('#customChips'), c.fields.length ? `<small class="muted">Your fields:</small> <span class="chips">${c.fields.map((f) => `<button type="button" class="chip" data-ins="${esc(f.key)}">{{${esc(f.key)}}}</button>`).join('')}</span>` : '');
    const sizeSel = (attr, val, opts) => `<label class="f" style="margin:8px 0 0"><span>Size</span><select ${attr}>${opts.map(([h, n]) => `<option value="${h}" ${val === h ? 'selected' : ''}>${n}</option>`).join('')}${opts.some(([h]) => h === val) ? '' : `<option value="${val}" selected>Custom (${val}px)</option>`}</select></label>`;
    const b = c.headerImage;
    setHtml($('#bannerSlot'), `<div class="slot" style="max-width:420px"><div class="slot-img">${b ? Render.imgTag(b.file, 0, '/protocol/uploads/', Math.min(70, b.height || 60)) : '<small class="muted">No image</small>'}</div>
      <div class="row"><button type="button" class="btn sm" data-banner-choose>${b ? 'Change' : 'Choose'}</button>${b ? '<button type="button" class="btn sm danger" data-banner-rm>Remove</button>' : ''}</div>
      ${b ? `<div class="grid g2">${sizeSel('data-banner-size', b.height || 60, [[20, 'Thin'], [40, 'Small'], [60, 'Medium'], [90, 'Large'], [130, 'Extra large']])}
        <label class="f" style="margin:8px 0 0"><span>Position</span><select data-banner-align>${Render.LOGO_POSITIONS.map((a) => `<option value="${a}" ${(b.align || 'center') === a ? 'selected' : ''}>${a[0].toUpperCase() + a.slice(1)}</option>`).join('')}</select></label></div>` : ''}</div>`);
    setHtml($('#footSlots'), `<div class="grid g3">${Render.LOGO_POSITIONS.map((pos) => {
      const l = c.footerImages.find((x) => x.position === pos);
      return `<div class="slot"><b>${pos[0].toUpperCase() + pos.slice(1)}</b>
        <div class="slot-img">${l ? Render.imgTag(l.file, 0, '/protocol/uploads/', 60) : '<small class="muted">No image</small>'}</div>
        <div class="row"><button type="button" class="btn sm" data-foot-choose="${pos}">${l ? 'Change' : 'Choose'}</button>${l ? `<button type="button" class="btn sm danger" data-foot-rm="${pos}">Remove</button>` : ''}</div>
        ${l ? sizeSel(`data-foot-size="${pos}"`, l.height || 50, [[35, 'Small'], [50, 'Medium'], [70, 'Large'], [100, 'Extra large']]) : ''}</div>`;
    }).join('')}</div>`);
    setHtml($('#logoSlots'), c.logos ? `<div class="gallery" style="margin-top:10px">${c.logos.map((l, i) => `<div class="slot" style="width:170px">
        <div class="slot-img">${Render.imgTag(l.file, 0, '/protocol/uploads/', 70)}</div>
        <div class="row"><button type="button" class="btn sm" data-lsel="${i}">Select</button><button type="button" class="btn sm danger" data-ldel="${i}">Remove</button></div>
        ${sizeSel(`data-lsz="${i}"`, l.height || 84, [[60, 'Small'], [84, 'Medium'], [110, 'Large'], [140, 'Extra large']])}</div>`).join('')}
        ${c.logos.length < Render.MAX_LOGOS ? '<button type="button" class="btn" data-ladd style="align-self:center">+ Add logo</button>' : ''}</div>
        <small class="muted">Drag logos in the preview to place them anywhere – left, right, up, down, even into the side margins. Drag the organization name up or down to move it.</small>` : '');
    patchHtml($('#pv'), letterHtml({ body: c.body, fields: c.fields, logos: c.logos, headerTextTop: c.headerTextTop, headerImage: c.headerImage, footerImages: c.footerImages, bodyImages: c.bodyImages, values: sampleValues(c, departmentId), departmentId, preview: true, watermark: 'SAMPLE', editable: { selected: sel, selectedLogo: selLogo, selectedFoot: selFoot, selectedBanner: selBanner, selectedBody: selBody } }));
    const im = sel == null ? null : findImg(bodyParas(), sel);
    if (sel != null && !im) sel = null;
    const lg = selLogo != null ? currentLogos()[selLogo] : null;
    if (selLogo != null && !lg) selLogo = null;
    if (!sliding && lg) setHtml($('#imgTools'), `<b>Selected logo</b><span class="part-tag header">HEADER</span>
      <small class="muted">Drag it anywhere – no snapping. Quick align:</small>
      <span class="seg">${Render.LOGO_POSITIONS.map((a) => `<button type="button" class="btn sm" data-lpos="${a}">${a[0].toUpperCase() + a.slice(1)}</button>`).join('')}</span>
      <label class="row" style="gap:6px">Size <input type="range" min="30" max="200" step="2" value="${lg.height || 84}" data-lsize> <span id="sizeVal">${lg.height || 84}px</span></label>
      <button type="button" class="btn sm danger" data-lrm>Remove</button><button type="button" class="btn sm ghost" data-desel>Done</button>`);
    const ft = selFoot && c.footerImages.find((l) => l.position === selFoot);
    if (selFoot && !ft) selFoot = null;
    if (selBanner && !c.headerImage) selBanner = false;
    const seg = (attr, cur) => `<span class="seg">${Render.LOGO_POSITIONS.map((a) => `<button type="button" class="btn sm ${cur === a ? 'on' : ''}" ${attr}="${a}">${a[0].toUpperCase() + a.slice(1)}</button>`).join('')}</span>`;
    if (lg) { /* letterhead logo toolbar drawn above */ }
    const bi = selBody != null ? c.bodyImages[selBody] : null;
    if (selBody != null && !bi) selBody = null;
    if (lg) { /* drawn above */ }
    else if (!sliding && bi) setHtml($('#imgTools'), `<b>Selected image</b><span class="part-tag body">BODY</span>
      <small class="muted">Drag it anywhere – no snapping. Quick align:</small>
      <span class="seg">${Render.LOGO_POSITIONS.map((a) => `<button type="button" class="btn sm" data-bipos="${a}">${a[0].toUpperCase() + a.slice(1)}</button>`).join('')}</span>
      <label class="row" style="gap:6px">Size <input type="range" min="20" max="400" step="2" value="${bi.height || 80}" data-bisize> <span id="sizeVal">${bi.height || 80}px</span></label>
      <button type="button" class="btn sm danger" data-birm>Remove</button><button type="button" class="btn sm ghost" data-desel>Done</button>`);
    else if (!sliding && selBanner) setHtml($('#imgTools'), `<b>Selected image</b><span class="part-tag header">HEADER</span>${seg('data-bpos', c.headerImage.align || 'center')}
      <label class="row" style="gap:6px">Size <input type="range" min="10" max="200" step="2" value="${c.headerImage.height || 60}" data-bsize> <span id="sizeVal">${c.headerImage.height || 60}px</span></label>
      <button type="button" class="btn sm danger" data-brm>Remove</button><button type="button" class="btn sm ghost" data-desel>Done</button>`);
    else if (!sliding && ft) setHtml($('#imgTools'), `<b>Selected image</b><span class="part-tag footer">FOOTER</span>${seg('data-fpos', selFoot)}
      <label class="row" style="gap:6px">Size <input type="range" min="20" max="140" step="2" value="${ft.height || 50}" data-fsize> <span id="sizeVal">${ft.height || 50}px</span></label>
      <button type="button" class="btn sm danger" data-frm>Remove</button><button type="button" class="btn sm ghost" data-desel>Done</button>`);
    else if (!sliding) setHtml($('#imgTools'), im ? `<b>Selected image</b><span class="part-tag body">BODY</span>
      <span class="seg">${['left', 'center', 'right'].map((a) => `<button type="button" class="btn sm ${im.align === a ? 'on' : ''}" data-al="${a}">${a[0].toUpperCase() + a.slice(1)}</button>`).join('')}</span>
      <label class="row" style="gap:6px">Size <input type="range" min="30" max="640" step="5" value="${im.width}" data-size> <span id="sizeVal">${im.width}px</span></label>
      ${im.rowCount > 1 ? `<button type="button" class="btn sm" data-side="-1" ${im.pos === 0 ? 'disabled' : ''} title="Move left in the row">◀</button><button type="button" class="btn sm" data-side="1" ${im.pos === im.rowCount - 1 ? 'disabled' : ''} title="Move right in the row">▶</button>` : ''}
      <button type="button" class="btn sm" data-mv="up">↑ Up</button><button type="button" class="btn sm" data-mv="down">↓ Down</button>
      <button type="button" class="btn sm danger" data-rmimg>Remove</button><button type="button" class="btn sm ghost" data-desel>Done</button>`
      : '<small class="muted">Tip: drag images in the preview to move them – into the header, the letter or the footer. Drop an image onto the middle of another to put them side by side. Click one to resize or align it.</small>');
  };

  // ----- moving and arranging images directly in the preview -----
  // The preview's body has one element per paragraph of the letter text (split on blank lines),
  // so positions in the preview map directly to paragraphs in the text.
  const IMG_TAG = /\[\[image:([a-z0-9-]+?-\d+x\d+\.(?:png|jpg))(?:\|(\d{1,4}))?(?:\|(left|center|right))?\]\]/g;
  let sel = null;
  let selLogo = null; // index of the selected letterhead logo
  let selFoot = null; // position of the selected footer image
  let selBanner = false; // the image at the bottom of the header is selected
  let selBody = null; // index of the selected free body image (signature, stamp…)
  const clearSel = () => { sel = null; selLogo = null; selFoot = null; selBanner = false; selBody = null; };
  // Adds a free body image; with a pointer position its middle goes under the pointer, otherwise near the top, centered.
  function addBodyImage(file, clientX, clientY) {
    const b = { file, height: 80, x: 0, y: 0 };
    const w = Render.logoWidth(b);
    if (clientX == null) {
      b.x = Render.clampX(50 - (w / Render.HEADER_W) * 50, w);
    } else {
      const r = pv.querySelector('.lbw').getBoundingClientRect();
      const k = r.width / Render.HEADER_W;
      b.x = Render.clampX((((clientX - r.left) / k - w / 2) / Render.HEADER_W) * 100, w);
      b.y = Math.max(0, Math.round((clientY - r.top) / k - b.height / 2));
    }
    c.bodyImages.push(b);
    clearSel();
    selBody = c.bodyImages.length - 1;
    S.dirty = true;
    refresh();
  }
  function setFooter(pos, file, height) {
    c.footerImages = c.footerImages.filter((l) => l.position !== pos).concat({ file, position: pos, height: height || 50 });
    selFoot = pos; selLogo = null; selBanner = false; sel = null; selBody = null;
    S.dirty = true;
    refresh();
  }
  function moveFooter(from, to) {
    c.footerImages.forEach((l) => { if (l.position === from) l.position = to; else if (l.position === to) l.position = from; });
    selFoot = to;
    S.dirty = true;
    refresh();
  }
  // Letterhead logos: dragging or editing one switches the template to its own logos.
  // Positions are free: x = left edge in % of the letterhead width, y = top in pixels (see Render.headerLayout).
  const currentLogos = () => Render.letterLogos({ settings: S.settings, logos: c.logos });
  function ensureCustomLogos() {
    if (c.logos) return;
    c.logos = currentLogos().map((l) => ({ ...l }));
    form.querySelector('input[name=logoMode][value=custom]').checked = true;
  }
  function logosChanged(i) {
    selLogo = i;
    sel = null;
    selFoot = null;
    selBanner = false;
    selBody = null;
    S.dirty = true;
    refresh();
  }
  // Quick align: flush left, centered or flush right, keeping the logo's height on the page.
  function alignLogo(i, where) {
    ensureCustomLogos();
    freezeText();
    const l = c.logos[i];
    const w = Render.logoWidth(l);
    l.x = Render.clampX(where === 'left' ? 0 : where === 'right' ? 100 : 50 - (w / Render.HEADER_W) * 50, w);
    logosChanged(i);
  }
  // Adds a logo; with a pointer position, its middle goes under the pointer, otherwise it is centered at the top.
  const clampY = (v) => Math.max(0, Math.min(400, Math.round(v)));
  // From the first move on, the organization name keeps its own position, so logos never push it around.
  function freezeText() {
    if (c.headerTextTop == null) c.headerTextTop = Render.headerLayout(currentLogos()).textTop;
  }
  function addLogo(file, clientX, clientY) {
    ensureCustomLogos();
    freezeText();
    if (c.logos.length >= Render.MAX_LOGOS) { toast(`A letterhead can have at most ${Render.MAX_LOGOS} logos.`, 'bad'); return false; }
    const l = { file, height: 84, y: 0, x: 0 };
    const w = Render.logoWidth(l);
    if (clientX == null) {
      l.x = Render.clampX(50 - (w / Render.HEADER_W) * 50, w);
    } else {
      const r = pv.querySelector('.lh-band').getBoundingClientRect();
      const k = r.width / Render.HEADER_W; // screen pixels per page pixel
      l.x = Render.clampX((((clientX - r.left) / k - w / 2) / Render.HEADER_W) * 100, w);
      l.y = clampY((clientY - r.top) / k - l.height / 2);
    }
    c.logos.push(l);
    logosChanged(c.logos.length - 1);
    return true;
  }
  let sliding = false; // true while the size slider is being dragged, so the toolbar is not redrawn under it
  const bodyParas = () => bodyEl.value.replace(/\r/g, '').trim().split(/\n{2,}/);
  const tagText = (im) => `[[image:${im.file}|${im.width}|${im.align}]]`;
  const naturalWidth = (file) => Math.min(640, Number(Render.IMAGE_FILE.exec(file)[1]));
  // A paragraph that holds only images is a row: its images sit side by side.
  const onlyImages = (t) => !t.replace(IMG_TAG, '').trim();
  function findImg(paras, n) {
    let k = 0;
    for (let p = 0; p < paras.length; p++) {
      const found = [...paras[p].matchAll(IMG_TAG)];
      for (let pos = 0; pos < found.length; pos++) {
        const m = found[pos];
        if (k++ === n) {
          return {
            p, pos, index: m.index, length: m[0].length, file: m[1], width: Number(m[2]) || naturalWidth(m[1]), align: m[3] || 'left',
            lone: paras[p].trim() === m[0], row: onlyImages(paras[p]), rowCount: found.length,
          };
        }
      }
    }
    return null;
  }
  // Removes an image tag from a paragraph, dropping the line if nothing else was on it.
  function cutTag(text, index, length) {
    const ls = text.lastIndexOf('\n', index - 1) + 1;
    const le = text.indexOf('\n', index + length);
    const lineEnd = le < 0 ? text.length : le;
    const rest = text.slice(ls, index) + text.slice(index + length, lineEnd);
    if (rest.trim()) return text.slice(0, index) + text.slice(index + length);
    if (le >= 0) return text.slice(0, ls) + text.slice(le + 1);
    return text.slice(0, Math.max(0, ls - 1));
  }
  function setParas(paras) {
    bodyEl.value = paras.join('\n\n');
    S.dirty = true;
    read();
    refresh();
  }
  const countBefore = (paras, upto) => paras.slice(0, upto).reduce((n, t) => n + [...t.matchAll(IMG_TAG)].length, 0);
  // Puts an image (moved or new) as its own paragraph before paragraph number `target`,
  // or, when `slot` is given, into the image row at paragraph `target` at that position.
  function placeImage(target, im, fromIndex, slot) {
    const items = bodyParas().map((t) => ({ t }));
    const row = slot != null ? items[target] : null;
    const tokens = row ? [...row.t.matchAll(IMG_TAG)].map((m) => m[0]) : null;
    if (fromIndex != null) {
      const old = findImg(items.map((x) => x.t), fromIndex);
      if (row && old.p === target) {
        tokens.splice(old.pos, 1);
        if (old.pos < slot) slot--;
      } else {
        items[old.p].t = cutTag(items[old.p].t, old.index, old.length);
      }
    }
    let moved;
    if (row) {
      const first = tokens.length ? [...tokens[0].matchAll(IMG_TAG)][0] : null;
      tokens.splice(slot, 0, tagText({ ...im, align: first ? first[3] || 'left' : im.align }));
      row.t = tokens.join(' ');
      moved = row;
    } else {
      moved = { t: tagText(im) };
      items.splice(Math.max(0, Math.min(target, items.length)), 0, moved);
    }
    const kept = items.filter((x) => x === moved || x.t.trim());
    const paras = kept.map((x) => x.t);
    sel = countBefore(paras, kept.indexOf(moved)) + (row ? slot : 0);
    setParas(paras);
  }
  function updateSelected(changes) {
    const paras = bodyParas();
    const im = findImg(paras, sel);
    if (!im) return;
    const t = paras[im.p];
    if (changes.align && im.row) {
      // A row is aligned as a whole.
      paras[im.p] = t.replace(IMG_TAG, (_, f, w) => tagText({ file: f, width: Number(w) || naturalWidth(f), align: changes.align }));
    } else {
      paras[im.p] = t.slice(0, im.index) + tagText({ ...im, ...changes }) + t.slice(im.index + im.length);
    }
    setParas(paras);
  }

  const pv = $('#pv');
  pv.addEventListener('click', (e) => {
    const img = e.target.closest('img[data-img]');
    const lg = e.target.closest('img[data-logo]');
    const ft = e.target.closest('img[data-foot]');
    sel = img ? Number(img.dataset.img) : null;
    selLogo = lg ? Number(lg.dataset.logo) : null;
    selFoot = ft ? ft.dataset.foot : null;
    selBanner = !!e.target.closest('img[data-banner]');
    const fb = e.target.closest('img[data-bimg]');
    selBody = fb ? Number(fb.dataset.bimg) : null;
    refresh();
  });
  $('#imgTools').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (b && selBody != null) {
      const bi = c.bodyImages[selBody];
      if (b.dataset.bipos) {
        const w = Render.logoWidth(bi);
        bi.x = Render.clampX(b.dataset.bipos === 'left' ? 0 : b.dataset.bipos === 'right' ? 100 - (w / Render.HEADER_W) * 100 : 50 - (w / Render.HEADER_W) * 50, w);
        S.dirty = true; refresh();
      } else if ('birm' in b.dataset) { c.bodyImages.splice(selBody, 1); selBody = null; S.dirty = true; refresh(); }
      else if ('desel' in b.dataset) { selBody = null; refresh(); }
      return;
    }
    if (b && selBanner) {
      if (b.dataset.bpos) { c.headerImage.align = b.dataset.bpos; S.dirty = true; refresh(); }
      else if ('brm' in b.dataset) { c.headerImage = null; selBanner = false; S.dirty = true; refresh(); }
      else if ('desel' in b.dataset) { selBanner = false; refresh(); }
      return;
    }
    if (b && selFoot) {
      if (b.dataset.fpos) moveFooter(selFoot, b.dataset.fpos);
      else if ('frm' in b.dataset) { c.footerImages = c.footerImages.filter((l) => l.position !== selFoot); selFoot = null; S.dirty = true; refresh(); }
      else if ('desel' in b.dataset) { selFoot = null; refresh(); }
      return;
    }
    if (b && selLogo != null) {
      if (b.dataset.lpos) alignLogo(selLogo, b.dataset.lpos);
      else if ('lrm' in b.dataset) { ensureCustomLogos(); c.logos.splice(selLogo, 1); logosChanged(null); }
      else if ('desel' in b.dataset) { selLogo = null; refresh(); }
      return;
    }
    if (!b || sel == null) return;
    const paras = bodyParas();
    const im = findImg(paras, sel);
    if (!im) return;
    if (b.dataset.al) updateSelected({ align: b.dataset.al });
    else if (b.dataset.side) placeImage(im.p, im, sel, b.dataset.side === '1' ? im.pos + 2 : im.pos - 1);
    else if (b.dataset.mv === 'up') placeImage(im.lone ? im.p - 1 : im.p, im, sel);
    else if (b.dataset.mv === 'down') placeImage(im.lone ? im.p + 2 : im.p + 1, im, sel);
    else if ('rmimg' in b.dataset) {
      paras[im.p] = cutTag(paras[im.p], im.index, im.length);
      sel = null;
      setParas(paras.filter((t) => t.trim()));
    } else if ('desel' in b.dataset) { sel = null; refresh(); }
  });
  $('#imgTools').addEventListener('input', (e) => {
    const live = (fn) => { $('#sizeVal').textContent = e.target.value + 'px'; fn(Number(e.target.value)); S.dirty = true; sliding = true; refresh(); sliding = false; };
    if ('bisize' in e.target.dataset && selBody != null) return live((v) => { c.bodyImages[selBody].height = v; });
    if ('bsize' in e.target.dataset && selBanner) return live((v) => { c.headerImage.height = v; });
    if ('fsize' in e.target.dataset && selFoot) return live((v) => { c.footerImages.find((l) => l.position === selFoot).height = v; });
    if ('lsize' in e.target.dataset && selLogo != null) {
      ensureCustomLogos();
      $('#sizeVal').textContent = e.target.value + 'px';
      const l = c.logos[selLogo];
      l.height = Number(e.target.value);
      l.x = Render.clampX(l.x, Render.logoWidth(l));
      sliding = true;
      logosChanged(selLogo);
      sliding = false;
      return;
    }
    if (!('size' in e.target.dataset)) return;
    $('#sizeVal').textContent = e.target.value + 'px';
    sliding = true;
    updateSelected({ width: Number(e.target.value) });
    sliding = false;
  });

  // Drag and drop: images inside the preview, or thumbnails from the image library.
  const DRAG_TYPE = 'application/x-opms-image';
  const line = document.createElement('div');
  line.className = 'drop-line';
  // Where a drop at (x, y) lands: before paragraph `index`, or inside the image row `index` at `slot`.
  const dropTarget = (x, y) => {
    const kids = [...pv.querySelectorAll('.lb > *')];
    for (let i = 0; i < kids.length; i++) {
      const r = kids[i].getBoundingClientRect();
      if (y < r.top) return { index: i, top: r.top - 6 };
      if (y > r.bottom) continue;
      const edge = Math.min(14, r.height / 4);
      if (kids[i].matches('p.limg') && y > r.top + edge && y < r.bottom - edge) {
        const imgs = [...kids[i].querySelectorAll('img')].map((im) => im.getBoundingClientRect());
        const slot = imgs.filter((b) => b.left + b.width / 2 < x).length;
        const lineX = slot < imgs.length ? imgs[slot].left - 8 : imgs[imgs.length - 1].right + 4;
        return { index: i, slot, left: lineX, top: r.top, height: r.height };
      }
      return y < r.top + r.height / 2 ? { index: i, top: r.top - 6 } : { index: i + 1, top: r.bottom + 2 };
    }
    const last = kids[kids.length - 1];
    return { index: kids.length, top: last ? last.getBoundingClientRect().bottom + 2 : pv.getBoundingClientRect().top + 40 };
  };
  const startDrag = (e, data) => {
    e.dataTransfer.setData(DRAG_TYPE, data);
    e.dataTransfer.effectAllowed = 'copyMove';
  };
  pv.addEventListener('dragstart', (e) => {
    const img = e.target.closest('img[data-img]');
    const lg = e.target.closest('img[data-logo]');
    if (img || lg || e.target.closest('img[data-bimg]')) e.preventDefault(); // moved with the pointer (see below), not drag-and-drop
    else if (e.target.closest('img[data-foot]')) startDrag(e, 'foot:' + e.target.closest('img[data-foot]').dataset.foot);
  });
  // Over the footer, a drop goes to the left, center or right footer position.
  const footerZone = (x, y) => {
    const f = pv.querySelector('.lf');
    if (!f) return null;
    const r = f.getBoundingClientRect();
    if (y < r.top - 10 || y > r.bottom + 10) return null;
    const third = r.width / 3;
    const i = Math.max(0, Math.min(2, Math.floor((x - r.left) / third)));
    return { position: Render.LOGO_POSITIONS[i], left: r.left + i * third, top: r.top, width: third, height: r.height, footer: true };
  };
  // Over the letterhead, a dropped image becomes a logo exactly where it is dropped.
  const headerZone = (x, y) => {
    const head = pv.querySelector('.lh');
    if (!head) return null;
    const r = head.getBoundingClientRect();
    if (y < r.top - 10 || y > r.bottom) return null;
    return { left: r.left, top: r.top, width: r.width, height: r.height, header: true };
  };
  // Free moving: press on a logo and drag it anywhere (no snapping), or drag the organization name up or down.
  // Movement follows the pointer exactly, converted from screen pixels to page pixels.
  // Body images: press and drag to move them anywhere. An image that sits in the text becomes a free image
  // (at the same spot) as soon as it is moved, so it can then go anywhere.
  pv.addEventListener('pointerdown', (e) => {
    const freeEl = e.target.closest('img[data-bimg]');
    const inlineEl = !freeEl && e.target.closest('.lb img[data-img]');
    if ((!freeEl && !inlineEl) || e.button !== 0) return;
    e.preventDefault();
    const sx = e.clientX;
    const sy = e.clientY;
    let i = freeEl ? Number(freeEl.dataset.bimg) : -1;
    let start = i >= 0 ? { x: c.bodyImages[i].x, y: c.bodyImages[i].y } : null;
    let k = 1;
    let last = null;
    const toFree = () => {
      // Lift the image out of the text into a free image at exactly the same place.
      const area = pv.querySelector('.lbw').getBoundingClientRect();
      const ir = inlineEl.getBoundingClientRect();
      k = area.width / Render.HEADER_W;
      const n = Number(inlineEl.dataset.img);
      const paras = bodyParas();
      const im = findImg(paras, n);
      if (!im) return false;
      const b = { file: im.file, height: Math.round(ir.height / k), x: 0, y: Math.max(0, Math.round((ir.top - area.top) / k)) };
      b.x = Render.clampX((((ir.left - area.left) / k) / Render.HEADER_W) * 100, Render.logoWidth(b));
      paras[im.p] = cutTag(paras[im.p], im.index, im.length);
      bodyEl.value = paras.filter((t) => t.trim()).join('\n\n');
      read();
      c.bodyImages.push(b);
      i = c.bodyImages.length - 1;
      start = { x: b.x, y: b.y };
      return true;
    };
    const apply = throttleFrame(() => {
      if (i < 0) {
        if (Math.abs(last.clientX - sx) + Math.abs(last.clientY - sy) < 4) return; // a click, not a move
        if (!toFree()) return;
      }
      k = pv.querySelector('.lbw').getBoundingClientRect().width / Render.HEADER_W;
      const b = c.bodyImages[i];
      b.x = Render.clampX(start.x + ((last.clientX - sx) / k / Render.HEADER_W) * 100, Render.logoWidth(b));
      b.y = Math.max(0, Math.min(3000, Math.round(start.y + (last.clientY - sy) / k)));
      clearSel();
      selBody = i;
      S.dirty = true;
      refresh();
    });
    const onMove = (ev) => { last = ev; apply(); };
    const onUp = () => {
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);
    };
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
  });
  pv.addEventListener('pointerdown', (e) => {
    const img = e.target.closest('img[data-logo]');
    const txt = !img && e.target.closest('[data-htext]');
    if ((!img && !txt) || e.button !== 0) return;
    e.preventDefault();
    ensureCustomLogos();
    freezeText();
    const k = pv.querySelector('.lh-band').getBoundingClientRect().width / Render.HEADER_W;
    const i = img ? Number(img.dataset.logo) : -1;
    const start = img ? { x: c.logos[i].x, y: c.logos[i].y } : { y: c.headerTextTop };
    const sx = e.clientX;
    const sy = e.clientY;
    let last = null;
    const apply = throttleFrame(() => {
      const dx = (last.clientX - sx) / k;
      const dy = (last.clientY - sy) / k;
      if (img) {
        const l = c.logos[i];
        l.x = Render.clampX(start.x + (dx / Render.HEADER_W) * 100, Render.logoWidth(l));
        l.y = clampY(start.y + dy);
      } else {
        c.headerTextTop = clampY(start.y + dy);
      }
      S.dirty = true;
      refresh();
    });
    const onMove = (ev) => { last = ev; apply(); };
    const onUp = () => {
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);
    };
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    selLogo = img ? i : null; sel = null; selFoot = null; selBanner = false; selBody = null;
    refresh();
  });
  $('#imgLib').addEventListener('dragstart', (e) => {
    const b = e.target.closest('[data-pick]');
    if (b) startDrag(e, 'file:' + b.dataset.pick);
  });
  pv.addEventListener('dragover', (e) => {
    if (!e.dataTransfer.types.includes(DRAG_TYPE)) return;
    e.preventDefault();
    const box = pv.getBoundingClientRect();
    const z = headerZone(e.clientX, e.clientY) || footerZone(e.clientX, e.clientY);
    if (z) {
      line.className = 'drop-line zone';
      line.style.cssText = `left:${z.left - box.left}px;top:${z.top - box.top + pv.scrollTop}px;width:${z.width}px;height:${z.height}px`;
      line.dataset.label = z.header ? 'Add as a header logo here' : z.position[0].toUpperCase() + z.position.slice(1) + ' footer image';
      if (!line.isConnected) pv.appendChild(line);
      return;
    }
    const lbw = pv.querySelector('.lbw').getBoundingClientRect();
    if (e.clientY >= lbw.top && e.clientY <= lbw.bottom) {
      line.className = 'drop-line zone';
      line.style.cssText = `left:${lbw.left - box.left}px;top:${lbw.top - box.top}px;width:${lbw.width}px;height:${lbw.height}px`;
      line.dataset.label = 'Drop to place it here in the body';
      if (!line.isConnected) pv.appendChild(line);
      return;
    }
    const t = dropTarget(e.clientX, e.clientY);
    line.className = 'drop-line' + (t.slot != null ? ' v' : '');
    line.style.cssText = t.slot != null
      ? `left:${t.left - box.left}px;top:${t.top - box.top + pv.scrollTop}px;height:${t.height}px`
      : `top:${t.top - box.top + pv.scrollTop}px`;
    if (!line.isConnected) pv.appendChild(line);
  });
  pv.addEventListener('dragleave', (e) => { if (!pv.contains(e.relatedTarget)) line.remove(); });
  const placed = (part) => toast(`Placed in the ${part}`, 'ok');
  pv.addEventListener('drop', (e) => {
    const data = e.dataTransfer.getData(DRAG_TYPE);
    line.remove();
    if (!data) return;
    e.preventDefault();
    const fz = footerZone(e.clientX, e.clientY);
    if (fz) {
      if (data.startsWith('foot:')) moveFooter(data.slice(5), fz.position);
      else if (data.startsWith('file:')) setFooter(fz.position, data.slice(5));
      else if (data.startsWith('img:')) {
        // A letter image dropped on the footer becomes a footer image there.
        const paras = bodyParas();
        const im = findImg(paras, Number(data.slice(4)));
        if (!im) return;
        paras[im.p] = cutTag(paras[im.p], im.index, im.length);
        bodyEl.value = paras.filter((t) => t.trim()).join('\n\n');
        read();
        setFooter(fz.position, im.file);
      }
      placed('FOOTER');
      return;
    }
    const z = headerZone(e.clientX, e.clientY);
    if (z) {
      if (data.startsWith('foot:')) return;
      let file = data.slice(5);
      if (data.startsWith('img:')) {
        // A letter image dropped on the letterhead becomes a logo there.
        const paras = bodyParas();
        const im = findImg(paras, Number(data.slice(4)));
        if (!im) return;
        paras[im.p] = cutTag(paras[im.p], im.index, im.length);
        bodyEl.value = paras.filter((t) => t.trim()).join('\n\n');
        read();
        file = im.file;
      }
      if (!addLogo(file, e.clientX, e.clientY)) return;
      placed('HEADER');
      return;
    }
    const t = dropTarget(e.clientX, e.clientY);
    if (data.startsWith('foot:')) {
      toast('Footer images stay in the footer. Drop them inside the footer box.');
      return;
    }
    if (data.startsWith('img:')) {
      const n = Number(data.slice(4));
      const im = findImg(bodyParas(), n);
      if (im) placeImage(t.index, { ...im, align: im.row ? im.align : 'center' }, n, t.slot);
    } else {
      addBodyImage(data.slice(5), e.clientX, e.clientY);
    }
    placed('BODY');
  });
  document.addEventListener('dragend', () => line.remove());
  const uploadName = (file) => (uploads.find((u) => u.file === file) || {}).name || 'Image';
  const refreshSoon = throttleFrame(() => { read(); refresh(); });

  drawFields();
  refresh();
  form.addEventListener('input', (e) => {
    S.dirty = true;
    if (e.target.dataset.f === 'type') { read(); drawFields(); }
    refreshSoon();
  });
  form.addEventListener('change', (e) => {
    const t2 = e.target;
    if (t2.name === 'logoMode') {
      selLogo = null;
      c.headerTextTop = null;
      c.logos = t2.value === 'custom' ? Render.letterLogos({ settings: S.settings, logos: null }) : null;
      S.dirty = true;
    } else if ('bannerSize' in t2.dataset || 'bannerAlign' in t2.dataset) {
      if ('bannerSize' in t2.dataset) c.headerImage.height = parseInt(t2.value, 10);
      else c.headerImage.align = t2.value;
      S.dirty = true;
    } else if (t2.dataset.footSize) {
      c.footerImages.find((l) => l.position === t2.dataset.footSize).height = parseInt(t2.value, 10);
      S.dirty = true;
    } else if (t2.dataset.lsz) {
      const l = c.logos[Number(t2.dataset.lsz)];
      l.height = parseInt(t2.value, 10);
      l.x = Render.clampX(l.x, Render.logoWidth(l));
      S.dirty = true;
    } else if (t2.dataset.f === 'type') { read(); drawFields(); }
    read();
    refresh();
  });
  form.addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    read();
    if ('bannerChoose' in b.dataset) {
      chooseImage(uploads, 'Choose the image for the bottom of the header').then((file) => {
        if (!file) return;
        c.headerImage = { file, height: (c.headerImage && c.headerImage.height) || 60, align: (c.headerImage && c.headerImage.align) || 'center' };
        selBanner = true; sel = null; selLogo = null; selFoot = null; selBody = null;
        S.dirty = true;
        refresh();
      });
      return;
    } else if ('bannerRm' in b.dataset) {
      c.headerImage = null;
      S.dirty = true;
    } else if (b.dataset.footChoose) {
      const pos = b.dataset.footChoose;
      chooseImage(uploads, 'Choose the ' + pos + ' footer image').then((file) => { if (file) setFooter(pos, file); });
      return;
    } else if (b.dataset.footRm) {
      c.footerImages = c.footerImages.filter((l) => l.position !== b.dataset.footRm);
      S.dirty = true;
    } else if (b.dataset.bisel) {
      clearSel();
      selBody = Number(b.dataset.bisel);
      $('#pv .lbw').scrollIntoView({ block: 'nearest' });
    } else if (b.dataset.bidel) {
      c.bodyImages.splice(Number(b.dataset.bidel), 1);
      selBody = null;
      S.dirty = true;
    } else if ('ladd' in b.dataset) {
      chooseImage(uploads, 'Choose a logo to add').then((file) => { if (file) addLogo(file); });
      return;
    } else if (b.dataset.ldel) {
      c.logos.splice(Number(b.dataset.ldel), 1);
      selLogo = null;
      S.dirty = true;
    } else if (b.dataset.lsel) {
      selLogo = Number(b.dataset.lsel); sel = null; selFoot = null; selBanner = false;
      $('#pv .lh').scrollIntoView({ block: 'nearest' });
    } else if (b.dataset.pick) {
      placeFromLibrary(b.dataset.pick);
      return;
    } else if (b.dataset.ins) {
      const tag = `{{${b.dataset.ins}}}`;
      const s = bodyEl.selectionStart;
      bodyEl.setRangeText(tag, s, bodyEl.selectionEnd, 'end');
      bodyEl.focus();
      S.dirty = true;
      read();
    } else if (b.dataset.del) { c.fields.splice(+b.dataset.del, 1); S.dirty = true; drawFields(); }
    else if (b.dataset.up) { const i = +b.dataset.up; [c.fields[i - 1], c.fields[i]] = [c.fields[i], c.fields[i - 1]]; drawFields(); S.dirty = true; }
    else if (b.dataset.down) { const i = +b.dataset.down; [c.fields[i + 1], c.fields[i]] = [c.fields[i], c.fields[i + 1]]; drawFields(); S.dirty = true; }
    else if (b.dataset.restore) {
      confirmBox('Copy old version', `Replace the current draft with the content of version ${b.dataset.restore}?`, 'Copy into draft').then(async (ok) => {
        if (!ok) return;
        await run(b, async () => { await api(`/templates/${t.id}/restore-version`, { method: 'POST', body: { version: b.dataset.restore } }); toast('Version copied into draft'); S.dirty = false; route(); });
      });
      return;
    } else return;
    refresh();
  });
  // Clicking a library image asks where it should go, so it never ends up somewhere unexpected.
  let bodyCursorSet = false; // true once the user has clicked or typed in the letter text
  ['focus', 'click', 'keyup'].forEach((ev) => bodyEl.addEventListener(ev, () => { bodyCursorSet = true; }));
  function placeFromLibrary(file) {
    const btn = (attr, label) => `<button type="button" class="btn" ${attr}>${label}</button>`;
    const form = modal('Where should this image go?', `<div style="text-align:center;margin-bottom:14px">${Render.imgTag(file, 0, '/protocol/uploads/', 80)}</div>
      <h3>Header</h3><div class="row" style="margin-bottom:12px">${btn('data-to="logo:left"', 'Logo (start on the left)')}${btn('data-to="logo:center"', 'Logo (start in the center)')}${btn('data-to="logo:right"', 'Logo (start on the right)')}${btn('data-to="banner"', 'Under the address line')}</div>
      <h3>Body</h3><div class="row" style="margin-bottom:12px">${btn('data-to="free"', 'Free – I will drag it where I want (signature, stamp)')}${btn('data-to="body"', bodyCursorSet ? 'In line with the text, where my cursor is' : 'In line with the text, at the top')}</div>
      <h3>Footer</h3><div class="row">${btn('data-to="foot:left"', 'Left')}${btn('data-to="foot:center"', 'Center')}${btn('data-to="foot:right"', 'Right')}</div>`, { wide: true });
    form.addEventListener('click', (ev) => {
      const t = ev.target.closest('[data-to]');
      if (!t) return;
      form.closest('.modal-bg').remove();
      const [where, pos] = t.dataset.to.split(':');
      if (where === 'logo') { if (addLogo(file)) alignLogo(c.logos.length - 1, pos); }
      else if (where === 'foot') setFooter(pos, file);
      else if (where === 'banner') {
        c.headerImage = { file, height: 60, align: 'center' };
        selBanner = true; sel = null; selLogo = null; selFoot = null; selBody = null;
        S.dirty = true;
        refresh();
      } else if (where === 'free') addBodyImage(file);
      else if (bodyCursorSet) insertImage(file);
      else placeImage(0, { file, width: Math.min(150, naturalWidth(file)), align: 'center' }, null);
      toast(`Placed in the ${{ logo: 'HEADER', banner: 'HEADER', foot: 'FOOTER' }[where] || 'BODY'}`, 'ok');
      $('#pv').scrollIntoView({ block: 'nearest' });
    });
  }
  const insertImage = (file) => {
    const s0 = bodyEl.selectionStart;
    const before = bodyEl.value.slice(0, s0);
    const pad = before && !before.endsWith('\n\n') ? (before.endsWith('\n') ? '\n' : '\n\n') : '';
    bodyEl.setRangeText(`${pad}[[image:${file}|150|center]]\n\n`, s0, bodyEl.selectionEnd, 'end');
    bodyEl.focus();
    S.dirty = true;
    read();
    refresh();
  };
  $('#upImg').onclick = (e) => run(e.currentTarget, async () => {
    const rec = await pickImage();
    if (!rec) return;
    uploads.unshift(rec);
    refresh();
    toast('Image uploaded to the library. Click it or drag it onto the preview to place it.', 'ok');
  });
  $('#imgList').addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-imgedit]');
    if (!b) return;
    const file = b.dataset.imgedit;
    const re = new RegExp('\\[\\[image:' + file.replace(/[.-]/g, '\\$&') + '(?:\\|(\\d+))?(?:\\|(left|center|right))?\\]\\]');
    const m = re.exec(bodyEl.value) || [];
    modal('Image size and position', `<div style="text-align:center;margin-bottom:12px">${Render.imgTag(file, 0, '/protocol/uploads/', 100)}</div>
      <div class="grid g2"><label class="f"><span>Width on the page (pixels)</span><input type="number" name="w" min="10" max="640" value="${m[1] || 150}"><small class="muted">About 150 for a stamp, 600 for full width.</small></label>
      <label class="f"><span>Position</span><select name="a">${['left', 'center', 'right'].map((a) => `<option ${(m[2] || 'left') === a ? 'selected' : ''}>${a}</option>`).join('')}</select></label></div>
      <label class="check"><input type="checkbox" name="rm"><span>Remove this image from the letter</span></label>`, {
      submitLabel: 'Apply',
      onSubmit: (f) => {
        bodyEl.value = bodyEl.value.replace(re, f.rm.checked ? '' : `[[image:${file}|${parseInt(f.w.value, 10) || 150}|${f.a.value}]]`);
        S.dirty = true;
        read();
        refresh();
      },
    });
  });
  $('#addField').onclick = () => { read(); c.fields.push({ key: 'field_' + (c.fields.length + 1), label: '', type: 'text', required: true, help: '', example: '', options: [] }); drawFields(); refresh(); S.dirty = true; };
  $('#detect').onclick = () => {
    read();
    const defined = new Set(c.fields.map((f) => f.key));
    const added = Render.fieldKeys(c.body).filter((k) => !defined.has(k) && !Render.SYSTEM_FIELDS[k]);
    added.forEach((k) => c.fields.push({ key: k, label: labelFromKey(k), type: /date/.test(k) ? 'date' : 'text', required: true, help: '', example: '', options: [] }));
    drawFields(); refresh();
    toast(added.length ? `Added ${added.length} field(s). Give them clear labels and examples.` : 'All placeholders are already defined.');
    if (added.length) S.dirty = true;
  };

  const saveTpl = async () => {
    read();
    const body = { content: c, departmentId };
    const saved = isNew ? await api('/templates', { method: 'POST', body }) : await api('/templates/' + t.id, { method: 'PUT', body });
    S.dirty = false;
    return saved;
  };
  $('#saveT').onclick = (e) => run(e.currentTarget, async () => {
    const saved = await saveTpl();
    toast('Draft saved', 'ok');
    if (isNew) go('#/edit/' + saved.id); else route();
  });
  $('#submitT').onclick = (e) => run(e.currentTarget, async () => {
    const saved = await saveTpl();
    try {
      await api(`/templates/${saved.id}/submit`, { method: 'POST' });
    } catch (err) {
      if (isNew) go('#/edit/' + saved.id);
      throw err;
    }
    toast('Submitted for approval', 'ok');
    go('#/manage');
  });
  if ($('#discard')) $('#discard').onclick = async (e) => {
    if (!(await confirmBox(t.published ? 'Discard changes' : 'Delete draft', t.published ? 'Throw away the unpublished changes? The published version stays.' : 'Delete this unpublished template?', 'Yes, discard', true))) return;
    await run(e.target, async () => { await api(`/templates/${t.id}/draft`, { method: 'DELETE' }); toast('Discarded'); go('#/manage'); });
  };
}

// ---------- protocol handbook ----------
async function viewProtocol() {
  setTitle('Protocol Handbook');
  const entries = await api('/protocol');
  const editor = isRole('admin', 'protocol');
  let q = '';
  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>TFC Protocol Handbook</h1><p>The rules everyone follows for official communication. Please read each rule and confirm.</p></div>
      ${editor ? '<button class="btn primary" id="addRule">Add rule</button>' : ''}</div>
    <div class="card row"><input type="search" id="pq" placeholder="Search the handbook" style="flex:1"><span id="ackSum" class="muted"></span></div>
    <div id="rules"></div>`;
  const draw = () => {
    const ql = q.toLowerCase();
    const list = entries.filter((e) => !ql || (e.title + ' ' + e.body + ' ' + e.category).toLowerCase().includes(ql));
    const cats = [...new Set(list.map((e) => e.category))];
    const read = entries.filter((e) => e.acks.includes(S.me.id)).length;
    $('#ackSum').textContent = `You have confirmed ${read} of ${entries.length} rules`;
    $('#rules').innerHTML = cats.length ? cats.map((cat) => `<h2 style="margin:18px 0 10px">${esc(cat)}</h2>` + list.filter((e) => e.category === cat).map((e) => {
      const acked = e.acks.includes(S.me.id);
      return `<div class="card rule"><div class="row" style="margin-bottom:6px"><h3 style="margin:0">${esc(e.title)}</h3><span class="spacer"></span>
        ${editor ? `<button class="btn sm ghost" data-edit="${e.id}">Edit</button><button class="btn sm ghost danger" data-del="${e.id}">Delete</button>` : ''}</div>
        <div class="body">${Render.markup(e.body)}</div>
        <div class="row"><small class="muted">Updated ${fmtDate(e.updatedAt)} by ${esc(userName(e.updatedBy))} · ${e.acks.length} staff confirmed</small><span class="spacer"></span>
        ${acked ? '<span class="badge ok">✓ You confirmed reading this</span>' : `<button class="btn sm primary" data-ack="${e.id}">I have read and understood</button>`}</div></div>`;
    }).join('')).join('') : '<div class="card empty">No rules found.</div>';
  };
  const ruleForm = (e) => modal(e ? 'Edit rule' : 'Add rule', `
    <label class="f"><span>Title</span><input type="text" name="title" value="${esc(e ? e.title : '')}" required></label>
    <label class="f"><span>Section</span><input type="text" name="category" list="pcats" value="${esc(e ? e.category : '')}"><datalist id="pcats">${[...new Set(entries.map((x) => x.category))].map((c) => `<option>${esc(c)}</option>`).join('')}</datalist></label>
    <label class="f"><span>Rule</span><textarea name="body" rows="10">${esc(e ? e.body : '')}</textarea><small class="muted">Blank line = new paragraph · **bold** · "- " for bullet lists</small></label>
    ${e ? '<p class="muted">Saving will ask all staff to confirm this rule again.</p>' : ''}`, {
    wide: true,
    onSubmit: async (f) => {
      const body = { title: f.title.value, category: f.category.value, body: f.body.value };
      await api('/protocol' + (e ? '/' + e.id : ''), { method: e ? 'PUT' : 'POST', body });
      toast('Rule saved', 'ok');
      route();
    },
  });
  $('#pq').oninput = (e) => { q = e.target.value; draw(); };
  if (editor) $('#addRule').onclick = () => ruleForm(null);
  $('#rules').onclick = async (ev) => {
    const b = ev.target.closest('button');
    if (!b) return;
    if (b.dataset.ack) {
      await run(b, async () => { const e = await api(`/protocol/${b.dataset.ack}/ack`, { method: 'POST' }); entries.splice(entries.findIndex((x) => x.id === e.id), 1, e); draw(); });
    } else if (b.dataset.edit) ruleForm(entries.find((x) => x.id === b.dataset.edit));
    else if (b.dataset.del && await confirmBox('Delete rule', 'Delete this rule from the handbook?', 'Delete', true)) {
      await run(b, async () => { await api('/protocol/' + b.dataset.del, { method: 'DELETE' }); route(); });
    }
  };
  draw();
}

// ---------- reports ----------
async function viewReports() {
  setTitle('Reports');
  const r = await api('/reports');
  const pct = (a, b) => (b ? Math.round((a / b) * 100) + '%' : '—');
  main().innerHTML = `
    <div class="page-head"><div class="grow"><h1>Reports &amp; Compliance</h1><p>${r.scopedTo ? 'Figures for ' + esc(deptName(r.scopedTo)) : 'Figures for the whole organization'}.</p></div>
      <button class="btn no-print" onclick="window.print()">Print report</button></div>
    <div class="grid g4" style="margin-bottom:16px">
      <div class="card stat"><div class="n">${r.templates.published}</div><div class="l">Published templates</div></div>
      <div class="card stat"><div class="n">${r.templates.awaiting}</div><div class="l">Templates awaiting approval</div></div>
      <div class="card stat"><div class="n">${r.documents.final}</div><div class="l">Official documents issued</div></div>
      <div class="card stat"><div class="n">${r.documents.pending}</div><div class="l">Documents awaiting approval</div></div>
    </div>
    <div class="grid g2">
      <div class="card"><h2>Most used templates</h2><table class="tbl"><thead><tr><th>Template</th><th class="num">Documents</th></tr></thead><tbody>
        ${r.topTemplates.map((t) => `<tr class="click" data-go="#/t/${t.id}"><td>${esc(t.title)}<br><small>${esc(deptName(t.departmentId))}</small></td><td class="num">${t.count}</td></tr>`).join('') || '<tr><td colspan="2" class="muted">No data</td></tr>'}</tbody></table></div>
      <div class="card"><h2>By department</h2><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Department</th><th class="num">Staff</th><th class="num">Templates</th><th class="num">Documents</th><th class="num">Ever returned</th></tr></thead><tbody>
        ${r.byDepartment.map((d) => `<tr><td>${esc(deptName(d.departmentId))}</td><td class="num">${d.staff}</td><td class="num">${d.templates}</td><td class="num">${d.documents}</td><td class="num">${d.returned}</td></tr>`).join('')}</tbody></table></div>
        <small class="muted">"Ever returned" counts documents sent back for corrections at least once. A high number suggests staff need more guidance.</small></div>
    </div>
    <div class="grid g2">
      <div class="card"><h2>Documents by status</h2><table class="tbl"><tbody>
        ${Object.entries(DOC_STATUS).map(([k, v]) => `<tr><td>${docBadge(k)}</td><td class="num">${r.documents[k]}</td></tr>`).join('')}
        <tr><td><b>Total</b></td><td class="num"><b>${r.documents.total}</b></td></tr></tbody></table></div>
      <div class="card"><h2>Protocol handbook – staff confirmation</h2><table class="tbl"><thead><tr><th>Rule</th><th class="num">Confirmed</th></tr></thead><tbody>
        ${r.acknowledgements.map((a) => `<tr><td>${esc(a.title)}</td><td class="num">${a.count} / ${a.total} (${pct(a.count, a.total)})</td></tr>`).join('')}</tbody></table></div>
    </div>
    <div class="card"><h2>Templates due for review</h2>${r.dueForReview.length ? `<table class="tbl">${r.dueForReview.map((t) => `<tr><td>${esc(t.title)}</td><td><small>Approved ${fmtDate(t.approvedAt)}, review every ${t.reviewMonths} months</small></td></tr>`).join('')}</table>` : '<p class="muted">All templates are up to date.</p>'}</div>`;
  bindRowLinks();
}

// ---------- administration ----------
async function viewAdmin() {
  setTitle('Administration');
  let tab = 'users';
  main().innerHTML = `<div class="page-head"><div class="grow"><h1>Administration</h1><p>Manage staff accounts, departments and organization details.</p></div></div>
    <div class="card"><div class="tabs"><button data-t="users" class="on">Users</button><button data-t="depts">Departments</button><button data-t="settings">Organization settings</button></div><div id="adm"></div></div>`;
  const draw = async () => {
    const el = $('#adm');
    if (tab === 'users') {
      const users = await api('/users');
      S.users = users;
      el.innerHTML = `<div class="row" style="margin-bottom:12px"><input type="search" id="uq" placeholder="Search users" style="max-width:300px"><span class="spacer"></span><button class="btn primary" id="addU">Add user</button></div>
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Department</th><th>Status</th><th></th></tr></thead><tbody id="ut"></tbody></table></div>`;
      const rows = (q) => users.filter((u) => !q || (u.name + u.username + u.title).toLowerCase().includes(q.toLowerCase())).map((u) => `<tr><td><b>${esc(u.name)}</b><br><small>${esc(u.title)}</small></td><td>${esc(u.username)}</td><td>${esc(ROLES[u.role])}</td><td>${esc(deptName(u.departmentId))}</td>
        <td>${u.active ? '<span class="badge ok">Active</span>' : '<span class="badge">Inactive</span>'}</td><td class="num"><button class="btn sm" data-u="${u.id}">Edit</button></td></tr>`).join('');
      $('#ut').innerHTML = rows('');
      $('#uq').oninput = (e) => { $('#ut').innerHTML = rows(e.target.value); };
      $('#addU').onclick = () => userForm(null);
      $('#ut').onclick = (e) => { const b = e.target.closest('[data-u]'); if (b) userForm(users.find((u) => u.id === b.dataset.u)); };
    } else if (tab === 'depts') {
      el.innerHTML = `<div class="row end" style="margin-bottom:12px"><button class="btn primary" id="addD">Add department</button></div>
        <table class="tbl"><thead><tr><th>Department</th><th>Code</th><th class="num">Staff</th><th></th></tr></thead><tbody>
        ${S.departments.map((d) => `<tr><td>${esc(d.name)}</td><td><code>${esc(d.code)}</code></td><td class="num">${S.users.filter((u) => u.departmentId === d.id && u.active).length}</td><td class="num"><button class="btn sm" data-d="${d.id}">Edit</button></td></tr>`).join('')}</tbody></table>
        <p class="muted" style="margin-top:10px">The code is used in reference numbers, e.g. ${esc(S.settings.orgCode)}/<b>HR</b>/2026/0001.</p>`;
      $('#addD').onclick = () => deptForm(null);
      $$('[data-d]', el).forEach((b) => b.onclick = () => deptForm(S.departments.find((d) => d.id === b.dataset.d)));
    } else {
      const s = S.settings;
      const inp = (k, l, help) => `<label class="f"><span>${l}</span><input type="text" name="${k}" value="${esc(s[k] || '')}">${help ? `<small class="muted">${help}</small>` : ''}</label>`;
      el.innerHTML = `<form id="sf" class="split"><div>
        ${inp('orgName', 'Organization name')}${inp('orgCode', 'Short code', 'Used in reference numbers.')}${inp('motto', 'Motto / tagline')}
        ${inp('address', 'Postal address')}${inp('phone', 'Telephone')}${inp('email', 'Email')}${inp('footer', 'Letter footer text')}
        ${inp('refFormat', 'Reference number format', 'Use {ORG}, {DEPT}, {YEAR} and {SEQ}. Example: {ORG}/{DEPT}/{YEAR}/{SEQ}')}
        <div class="f"><span style="display:block;font-weight:600;font-size:13px;margin-bottom:4px">Letterhead logo</span>
          <div class="row"><div id="logoBox" class="logo-box"></div><button type="button" class="btn sm" id="logoUp">Upload new logo</button><button type="button" class="btn sm danger" id="logoRm">Remove logo</button></div>
          <small class="muted">Shown at the top of every letter, in the menu and on the sign-in page.</small></div>
        <label class="check"><input type="checkbox" name="showDemoLogins" ${s.showDemoLogins ? 'checked' : ''}><span>Show sample accounts on the sign-in page <small class="muted">(turn off before real use)</small></span></label>
        <button class="btn primary">Save settings</button></div>
        <div><h3>Letterhead preview</h3><div class="paper" id="lhp"></div></div></form>`;
      const form = $('#sf');
      let logo = s.logo || '';
      const drawLogo = () => {
        $('#logoBox').innerHTML = logo ? logoImg(logo, 60) : '<small class="muted">No logo</small>';
        $('#logoRm').style.display = logo ? '' : 'none';
      };
      $('#logoUp').onclick = (e) => run(e.currentTarget, async () => { const rec = await pickImage(); if (rec) { logo = rec.file; drawLogo(); pv(); toast('Logo uploaded – click Save settings to use it'); } });
      $('#logoRm').onclick = () => { logo = ''; drawLogo(); pv(); };
      const pv = () => {
        const tmp = { ...S.settings, logo };
        $$('input[type=text]', form).forEach((i) => { tmp[i.name] = i.value; });
        $('#lhp').innerHTML = Render.renderLetter({ settings: tmp, departmentName: 'Human Resources', body: 'Ref: {{ref}}\n\nDear Sir/Madam,\n\n**RE: SAMPLE LETTER**\n\nThis is how your letterhead will look.', values: { ref: tmp.refFormat.replace('{ORG}', tmp.orgCode).replace('{DEPT}', 'HR').replace('{YEAR}', new Date().getFullYear()).replace('{SEQ}', '0001') }, preview: true });
      };
      form.oninput = pv;
      drawLogo();
      pv();
      form.onsubmit = (e) => {
        e.preventDefault();
        const body = { showDemoLogins: form.showDemoLogins.checked, logo };
        $$('input[type=text]', form).forEach((i) => { body[i.name] = i.value; });
        run($('button.primary', form), async () => { S.settings = await api('/settings', { method: 'PUT', body }); toast('Settings saved', 'ok'); shell(); route(); });
      };
    }
  };
  const userForm = (u) => modal(u ? 'Edit user' : 'Add user', `
    <div class="grid g2">
      <label class="f"><span>Full name</span><input type="text" name="name" value="${esc(u ? u.name : '')}" required></label>
      <label class="f"><span>Job title</span><input type="text" name="title" value="${esc(u ? u.title : '')}"></label>
      <label class="f"><span>Email (used to sign in)</span><input type="email" name="username" value="${esc(u ? u.username : '')}" required></label>
      <label class="f"><span>${u ? 'New password (leave blank to keep)' : 'Initial password'}</span><input type="text" name="password" ${u ? '' : 'required'} minlength="6"></label>
      <label class="f"><span>Role</span><select name="role">${Object.entries(ROLES).map(([k, l]) => `<option value="${k}" ${u && u.role === k ? 'selected' : (!u && k === 'employee' ? 'selected' : '')}>${l}</option>`).join('')}</select></label>
      <label class="f"><span>Department</span><select name="departmentId">${S.departments.map((d) => `<option value="${d.id}" ${u && u.departmentId === d.id ? 'selected' : ''}>${esc(d.name)}</option>`).join('')}</select></label>
    </div>
    ${u ? `<label class="check"><input type="checkbox" name="active" ${u.active ? 'checked' : ''}><span>Account active</span></label>` : ''}
    <details><summary class="muted" style="cursor:pointer">What can each role do?</summary><ul class="muted" style="font-size:13px">
      <li><b>Employee</b> – uses templates, creates documents.</li><li><b>Department secretary</b> – creates and updates the department's templates.</li>
      <li><b>Department head</b> – approves the department's templates and letters.</li><li><b>Protocol officer</b> – maintains the handbook; can manage and approve any template.</li>
      <li><b>System administrator</b> – everything, including users and settings.</li></ul></details>`, {
    wide: true,
    onSubmit: async (f) => {
      const body = { name: f.name.value, title: f.title.value, username: f.username.value, role: f.role.value, departmentId: f.departmentId.value };
      if (f.password.value) body.password = f.password.value;
      if (u) body.active = f.active.checked;
      await api('/users' + (u ? '/' + u.id : ''), { method: u ? 'PUT' : 'POST', body });
      toast('User saved', 'ok');
      await draw();
    },
  });
  const deptForm = (d) => modal(d ? 'Edit department' : 'Add department', `
    <label class="f"><span>Department name</span><input type="text" name="name" value="${esc(d ? d.name : '')}" required></label>
    <label class="f"><span>Short code</span><input type="text" name="code" value="${esc(d ? d.code : '')}" maxlength="10" required><small class="muted">Letters/numbers, e.g. HR, FIN. Used in reference numbers.</small></label>`, {
    onSubmit: async (f) => {
      await api('/departments' + (d ? '/' + d.id : ''), { method: d ? 'PUT' : 'POST', body: { name: f.name.value, code: f.code.value } });
      S.departments = (await api('/bootstrap')).departments;
      toast('Department saved', 'ok');
      await draw();
    },
  });
  $$('.tabs button').forEach((b) => b.onclick = () => { tab = b.dataset.t; $$('.tabs button').forEach((x) => x.classList.toggle('on', x === b)); run(null, draw); });
  await draw();
}

// ---------- audit ----------
async function viewAudit() {
  setTitle('Audit Trail');
  const rows = await api('/audit');
  main().innerHTML = `<div class="page-head"><div class="grow"><h1>Audit Trail</h1><p>Who created, changed, approved, printed or downloaded what, and when.</p></div></div>
    <div class="card"><input type="search" id="aq" placeholder="Filter by person, action or document" style="max-width:420px;margin-bottom:12px">
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th></tr></thead><tbody id="at"></tbody></table></div></div>`;
  const draw = (q) => {
    const ql = q.toLowerCase();
    $('#at').innerHTML = rows.filter((r) => !ql || (userName(r.userId) + ' ' + r.action + ' ' + r.detail).toLowerCase().includes(ql)).slice(0, 500)
      .map((r) => `<tr><td><small>${fmtDT(r.at)}</small></td><td>${r.userId ? esc(userName(r.userId)) : '<small class="muted">System</small>'}</td><td>${esc(r.action)}</td><td>${esc(r.detail)}</td></tr>`).join('');
  };
  $('#aq').oninput = (e) => draw(e.target.value);
  draw('');
}

// ---------- start ----------
async function boot() {
  try {
    const b = await api('/bootstrap');
    Object.assign(S, { me: b.me, settings: b.settings, departments: b.departments, users: b.users });
  } catch {
    return; // api() already showed the sign-in page
  }
  shell();
  await refreshSummary();
  route();
}

document.head.insertAdjacentHTML('beforeend', `<style>${Render.LETTER_CSS}</style>`);
boot();
