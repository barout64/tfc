// Meeting Management System – client-side behaviour
(function () {
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  // ---- Theme (light / dark) ------------------------------------------------
  var themeBtn = $('#themeBtn');
  if (themeBtn) themeBtn.addEventListener('click', function () {
    var root = document.documentElement;
    var current = root.dataset.theme ||
      (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    var next = current === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try { localStorage.setItem('theme', next); } catch (e) {}
  });

  // ---- Mobile sidebar ------------------------------------------------------
  var menuBtn = $('#menuBtn'), backdrop = $('#backdrop');
  if (menuBtn) menuBtn.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
  if (backdrop) backdrop.addEventListener('click', function () { document.body.classList.remove('nav-open'); });

  // Close open menus when clicking elsewhere
  document.addEventListener('click', function (e) {
    $$('details.user-menu[open], details.dropdown[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });

  // Dismiss flash messages (and fade out success messages automatically)
  $$('.flash-close').forEach(function (btn) {
    btn.addEventListener('click', function () { btn.closest('.flash').remove(); });
  });
  $$('.flash-success').forEach(function (f) {
    setTimeout(function () { f.style.transition = 'opacity .4s'; f.style.opacity = '0'; setTimeout(function () { f.remove(); }, 400); }, 6000);
  });

  // Confirmation prompts on forms and buttons with data-confirm
  document.addEventListener('submit', function (e) {
    var submitter = e.submitter;
    var msg = (submitter && submitter.dataset.confirm) || e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Clickable table rows
  $$('tr[data-href]').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, select, label')) return;
      window.location = row.dataset.href;
    });
  });

  // ---- Tabs on the meeting page (state kept in the URL hash) --------------
  var tabs = $$('.tabs [data-tab]');
  if (tabs.length) {
    var show = function (name) {
      if (!document.getElementById('tab-' + name)) name = 'overview';
      tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === name); });
      $$('.tab-panel').forEach(function (p) { p.classList.toggle('active', p.id === 'tab-' + name); });
    };
    var go = function (name, scroll) {
      history.replaceState(null, '', '#' + name);
      show(name);
      if (scroll) window.scrollTo({ top: $('.tabs').offsetTop - 80, behavior: 'smooth' });
    };
    tabs.forEach(function (t) { t.addEventListener('click', function (e) { e.preventDefault(); go(t.dataset.tab); }); });
    $$('[data-tab-link]').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); go(a.dataset.tabLink, true); });
    });
    window.addEventListener('hashchange', function () { show(location.hash.slice(1) || 'overview'); });
    show(location.hash.slice(1) || 'overview');
  }

  var allPresent = $('#markAllPresent');
  if (allPresent) allPresent.addEventListener('click', function () {
    $$('select.att-select').forEach(function (s) { s.value = 'present'; s.className = 'att-select att-present'; });
  });

  // ---- Participant picker: people / departments / groups ------------------
  $$('[data-picker]').forEach(function (root) {
    var people = $$('.people-list input[type=checkbox]', root);
    var units = $$('.unit input[type=checkbox]', root);
    var byId = {};
    people.forEach(function (p) { byId[p.value] = p; });
    var count = $('[data-count]', root), tray = $('[data-tray]', root);
    var members = function (u) { return u.dataset.members ? u.dataset.members.split(',') : []; };

    var refresh = function () {
      var sel = people.filter(function (p) { return p.checked && !p.disabled; });
      count.textContent = sel.length;
      tray.innerHTML = '';
      sel.slice(0, 40).forEach(function (p) {
        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'tray-chip';
        chip.title = 'Remove ' + p.dataset.name;
        chip.textContent = p.dataset.name;
        var x = document.createElement('span');
        x.className = 'x';
        x.textContent = '×';
        chip.appendChild(x);
        chip.addEventListener('click', function () { p.checked = false; onPersonChange(p); });
        tray.appendChild(chip);
      });
      if (sel.length > 40) {
        var more = document.createElement('span');
        more.className = 'muted small';
        more.textContent = '+' + (sel.length - 40) + ' more';
        tray.appendChild(more);
      }
    };
    // Unticking a person who belongs to a ticked department/group un-ticks that unit,
    // otherwise the server would add them back when it expands the unit.
    var onPersonChange = function (p) {
      if (!p.checked) units.forEach(function (u) { if (u.checked && members(u).indexOf(p.value) !== -1) u.checked = false; });
      refresh();
    };
    people.forEach(function (p) { p.addEventListener('change', function () { onPersonChange(p); }); });
    units.forEach(function (u) {
      u.addEventListener('change', function () {
        if (u.checked) {
          members(u).forEach(function (id) { if (byId[id] && !byId[id].disabled) byId[id].checked = true; });
        } else {
          var keep = {};
          units.forEach(function (o) { if (o !== u && o.checked) members(o).forEach(function (id) { keep[id] = true; }); });
          members(u).forEach(function (id) { if (byId[id] && !byId[id].disabled && !keep[id]) byId[id].checked = false; });
        }
        refresh();
      });
    });
    $$('[data-ptab]', root).forEach(function (btn) {
      btn.addEventListener('click', function () {
        $$('[data-ptab]', root).forEach(function (b) { b.classList.toggle('active', b === btn); });
        $$('.picker-pane', root).forEach(function (p) { p.classList.toggle('active', p.dataset.pane === btn.dataset.ptab); });
      });
    });
    var filter = $('[data-filter]', root);
    if (filter) filter.addEventListener('input', function () {
      var q = filter.value.toLowerCase();
      $$('.person', root).forEach(function (row) { row.style.display = row.dataset.search.indexOf(q) === -1 ? 'none' : ''; });
    });
    $('[data-all]', root).addEventListener('click', function () {
      people.forEach(function (p) { if (!p.disabled) p.checked = true; });
      refresh();
    });
    $('[data-clear]', root).addEventListener('click', function () {
      people.forEach(function (p) { if (!p.disabled) p.checked = false; });
      units.forEach(function (u) { u.checked = false; });
      refresh();
    });
    refresh();
  });

  // ---- Recurrence builder with live preview -------------------------------
  var form = $('#meetingForm'), repeat = $('#fRepeat');
  if (form && repeat) {
    var endMode = $('#fEndMode'), wrapCount = $('#wrapCount'), wrapUntil = $('#wrapUntil');
    var submitLabel = $('#submitLabel'), timer = null, seq = 0;
    var fields = ['#fDate', '#fStart', '#fEnd', '#fRoom', '#fRepeat', '#fEndMode', '#fCount', '#fUntil'];

    var toggle = function () {
      var on = repeat.value !== 'none';
      $$('[data-recur-only]').forEach(function (el) { el.style.display = on ? '' : 'none'; });
      wrapCount.style.display = endMode.value === 'count' ? '' : 'none';
      wrapUntil.style.display = endMode.value === 'until' ? '' : 'none';
      if (!on) submitLabel.textContent = 'Create meeting';
    };
    var render = function (data) {
      var items = data.items || [];
      var clashes = items.filter(function (i) { return i.conflict; }).length;
      $('#pvSummary').textContent = data.summary || '';
      $('#pvCount').textContent = items.length + ' meeting' + (items.length === 1 ? '' : 's') +
        (items.length ? ' · ' + items[0].label + ' → ' + items[items.length - 1].label : '') +
        (clashes ? ' · ' + clashes + ' room clash' + (clashes === 1 ? '' : 'es') + ' (will be skipped)' : '') +
        (data.capped ? ' · limited to 120' : '');
      var box = $('#pvDates');
      box.innerHTML = '';
      items.forEach(function (it, i) {
        var el = document.createElement('span');
        el.className = 'pv-date' + (it.conflict ? ' clash' : '') + (it.weekend ? ' weekend' : '');
        el.title = it.conflict ? 'Room already booked: ' + it.conflict : (it.weekend ? 'Falls on a weekend' : '');
        var n = document.createElement('span');
        n.className = 'n';
        n.textContent = i + 1;
        el.appendChild(n);
        el.appendChild(document.createTextNode(it.label));
        box.appendChild(el);
      });
      var ok = items.length - clashes;
      if (repeat.value !== 'none') submitLabel.textContent = 'Create ' + ok + ' meeting' + (ok === 1 ? '' : 's');
    };
    var update = function () {
      toggle();
      if (repeat.value === 'none') return;
      clearTimeout(timer);
      timer = setTimeout(function () {
        var params = new URLSearchParams({
          date: $('#fDate').value, start: $('#fStart').value, end: $('#fEnd').value, room_id: $('#fRoom').value,
          repeat: repeat.value, end_mode: endMode.value, occurrences: $('#fCount').value, until: $('#fUntil').value
        });
        var mine = ++seq;
        fetch(form.dataset.previewUrl + '?' + params.toString(), { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) { if (mine === seq) render(data); })
          .catch(function () { $('#pvSummary').textContent = 'Preview unavailable'; });
      }, 200);
    };
    fields.forEach(function (sel) {
      var el = $(sel);
      if (el) { el.addEventListener('change', update); el.addEventListener('input', update); }
    });
    $$('[data-preset]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var parts = btn.dataset.preset.split(':');
        repeat.value = parts[0];
        endMode.value = parts[1];
        update();
      });
    });
    update();
  }

  // Live meeting: follow which agenda item is being discussed, without reloading the page
  var live = $('[data-live-url]');
  if (live) {
    var current = live.dataset.current;
    var timer = setInterval(function () {
      if (document.hidden) return;
      fetch(live.dataset.liveUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          var now = $('#liveNow');
          if (d.status !== 'in_progress') {
            clearInterval(timer);
            now.textContent = 'This meeting has ended.';
            $$('.agenda li.now').forEach(function (li) { li.classList.remove('now'); });
            return;
          }
          var id = d.current ? String(d.current.id) : '';
          if (id === current) return;
          current = id;
          $$('.agenda > li[data-agenda-id]').forEach(function (li) {
            if (li.classList.contains('now')) li.classList.add('discussed');
            li.classList.toggle('now', li.dataset.agendaId === id);
          });
          now.textContent = '';
          if (d.current) {
            now.appendChild(document.createTextNode('Now discussing: '));
            var b = document.createElement('strong');
            b.textContent = d.current.number + '. ' + d.current.title;
            now.appendChild(b);
            var s = document.createElement('span');
            s.className = 'muted small';
            s.textContent = ' since ' + d.current.since;
            now.appendChild(s);
          } else {
            now.textContent = 'No agenda item picked yet';
          }
        })
        .catch(function () {});
    }, 8000);
  }
})();
