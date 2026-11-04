// Letter rendering shared by the browser (window.Render) and the server (require).
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.Render = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  // Fields the system fills in automatically; templates may use them but not redefine them.
  const SYSTEM_FIELDS = {
    ref: 'Reference number',
    date: 'Date',
    sender_name: 'Signatory name',
    sender_title: 'Signatory title',
    department: 'Department',
    org_name: 'Organization name',
  };

  const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
    'August', 'September', 'October', 'November', 'December'];

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function formatDate(v) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v || ''));
    if (!m) return v || '';
    return Number(m[3]) + ' ' + MONTHS[Number(m[2]) - 1] + ' ' + m[1];
  }

  // Uploaded image files carry their pixel size in the name: <id>-<width>x<height>.<png|jpg>
  const IMAGE_FILE = /^[a-z0-9-]+?-(\d+)x(\d+)\.(png|jpg)$/;
  const IMAGE_TAG = /\[\[image:([a-z0-9-]+?-\d+x\d+\.(?:png|jpg))(?:\|(\d{1,4}))?(?:\|(left|center|right))?\]\]/g;

  function imageKeys(body) {
    const files = new Set();
    String(body || '').replace(IMAGE_TAG, (_, f) => files.add(f));
    return [...files];
  }

  function imgTag(file, width, base, height, extra) {
    const m = IMAGE_FILE.exec(file);
    if (!m) return '';
    const ratio = Number(m[2]) / Number(m[1]);
    let w = width ? Number(width) : Number(m[1]);
    if (height) w = Math.round(height / ratio);
    w = Math.max(10, Math.min(w, 640));
    const h = Math.round(w * ratio);
    return '<img src="' + esc((base == null ? '/protocol/uploads/' : base) + file) + '" width="' + w + '" height="' + h +
      '" style="width:' + w + 'px;height:' + h + 'px;max-width:100%" alt=""' + (extra || '') + '>';
  }

  function fieldKeys(body) {
    const keys = new Set();
    String(body || '').replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, k) => keys.add(k));
    return [...keys];
  }

  // Simple markup: blank line = new paragraph, **bold**, "- " bullets, "1. " numbered lists.
  function inline(s) {
    return s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  }

  function blocks(text, imgs) {
    return text.split(/\n{2,}/).map((p) => {
      // A paragraph of only images is an image row: shown side by side, aligned like its first image.
      const row = imgs && /^(?:\u0001\d+\u0001\s*)+$/.test(p.trim()) ? p.trim().match(/\u0001\d+\u0001/g) : null;
      if (row) return '<p class="limg" style="text-align:' + (imgs[row[0].slice(1, -1)].align || 'left') + '">' + row.join('&emsp;') + '</p>';
      const lines = p.split('\n');
      if (lines.every((l) => /^\s*[-•]\s+/.test(l))) {
        return '<ul>' + lines.map((l) => '<li>' + l.replace(/^\s*[-•]\s+/, '') + '</li>').join('') + '</ul>';
      }
      if (lines.every((l) => /^\s*\d+[.)]\s+/.test(l))) {
        return '<ol>' + lines.map((l) => '<li>' + l.replace(/^\s*\d+[.)]\s+/, '') + '</li>').join('') + '</ol>';
      }
      return '<p>' + lines.join('<br>') + '</p>';
    }).join('');
  }

  function markup(text) {
    return blocks(inline(esc(String(text || '').replace(/\r/g, '').trim())));
  }

  // editable: { selected } marks images so the template editor can drag, select and resize them.
  function renderBody(body, values, fields, preview, assetBase, editable) {
    const defs = {};
    (fields || []).forEach((f) => { defs[f.key] = f; });
    const used = [];
    const imgs = [];
    const tokenized = inline(esc(String(body || '').replace(/\r/g, '').trim()))
      .replace(IMAGE_TAG, (_, file, width, align) => {
        imgs.push({ file, width, align });
        return '\u0001' + (imgs.length - 1) + '\u0001';
      })
      .replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, k) => {
        used.push(k);
        return '\u0000' + (used.length - 1) + '\u0000';
      });
    return blocks(tokenized, imgs).replace(/\u0001(\d+)\u0001/g, (_, i) => imgTag(imgs[i].file, imgs[i].width, assetBase, 0,
      editable ? ' data-img="' + i + '" draggable="true"' + (Number(i) === editable.selected ? ' class="sel"' : '') : '')).replace(/\u0000(\d+)\u0000/g, (_, i) => {
      const k = used[i];
      const d = defs[k];
      let v = values ? values[k] : '';
      if (v != null && String(v).trim() !== '') {
        if (k === 'date' || (d && d.type === 'date')) v = formatDate(v);
        return '<span class="fv">' + esc(v).replace(/\n/g, '<br>') + '</span>';
      }
      if (!preview) return '';
      return '<span class="ph">[' + esc(d ? d.label : (SYSTEM_FIELDS[k] || k)) + ']</span>';
    });
  }

  const LOGO_POSITIONS = ['left', 'center', 'right'];

  // Letterhead logos are placed freely: `x` is the left edge as a percentage of the letterhead width,
  // `y` the top in pixels, `height` in pixels. HEADER_W is the reference letterhead width in pixels
  // (the Word page's text width), used wherever pixels and percentages must agree.
  const HEADER_W = 642;
  const MAX_LOGOS = 6;

  // Displayed width of a letterhead logo, in pixels.
  function logoWidth(l) {
    const m = IMAGE_FILE.exec(l.file);
    if (!m) return 0;
    return Math.max(10, Math.min(640, Math.round((l.height || 84) * Number(m[1]) / Number(m[2]))));
  }

  // Logos may sit anywhere across the page, including the 2 cm (76 px) side margins.
  const MARGIN = 76;
  const clampX = (x, w) => Math.round(Math.max((-MARGIN / HEADER_W) * 100, Math.min(((HEADER_W + MARGIN - w) / HEADER_W) * 100, x)) * 10) / 10;

  // Converts logos saved by earlier versions (left/center/right + offset) to free positions.
  function normLogos(list) {
    const old = list.filter((l) => l.x == null);
    const centerOld = old.find((l) => l.position === 'center');
    const band = (centerOld ? (centerOld.height || 84) + 6 : 0) + 63;
    return list.map((l) => {
      if (l.x != null) return { file: l.file, x: Number(l.x), y: Number(l.y) || 0, height: l.height || 84 };
      const w = logoWidth(l);
      const off = l.offset || 0;
      const left = l.position === 'left' ? off : l.position === 'right' ? HEADER_W - off - w : HEADER_W / 2 + off - w / 2;
      const y = l.position === 'center' ? 0 : Math.max(0, Math.round((band - (l.height || 84)) / 2));
      return { file: l.file, x: clampX((left / HEADER_W) * 100, w), y, height: l.height || 84 };
    });
  }

  // A template's own letterhead logos, or null to use the organization logo (centered at the top).
  function letterLogos(o) {
    if (Array.isArray(o.logos)) return normLogos(o.logos.filter((l) => IMAGE_FILE.test(l.file)));
    const s = o.settings || {};
    if (!s.logo) return [];
    const l = { file: s.logo, height: 84, y: 0 };
    return [{ ...l, x: clampX(50 - (logoWidth(l) / HEADER_W) * 50, logoWidth(l)) }];
  }

  // The organization name starts at textTop: the template's own value if set (fixed, so moving logos never
  // moves the name), otherwise below any logo overlapping the middle of the letterhead. The logo area is
  // at least as tall as the lowest logo (bandMin).
  function headerLayout(logos, fixedTextTop) {
    let textTop = 0;
    let bandMin = 0;
    for (const l of logos) {
      const h = l.height || 84;
      const left = (l.x / 100) * HEADER_W;
      const right = left + logoWidth(l);
      if (right > HEADER_W * 0.3 && left < HEADER_W * 0.7) textTop = Math.max(textTop, l.y + h + 6);
      bandMin = Math.max(bandMin, l.y + h);
    }
    if (fixedTextTop != null) textTop = fixedTextTop;
    return { logos, textTop, bandMin };
  }

  // Body: the letter text, plus free images (signatures, stamps) placed anywhere on top of it.
  // Free images use the same coordinates as header logos: x in % of the text width, y in px from the top of the body.
  const freeImages = (list) => (Array.isArray(list) ? list : []).filter((b) => IMAGE_FILE.test(b.file));
  function bodyArea(o, ed) {
    const free = freeImages(o.bodyImages);
    const minH = free.reduce((m, b) => Math.max(m, b.y + (b.height || 80)), 0);
    return '<div class="lbw" style="min-height:' + minH + 'px">' +
      '<div class="lb">' + renderBody(o.body, o.values, o.fields, o.preview, o.assetBase, ed) + '</div>' +
      free.map((b, i) => '<span class="lb-free" style="left:' + b.x + '%;top:' + b.y + 'px;width:' + logoWidth({ file: b.file, height: b.height || 80 }) + 'px">' +
        imgTag(b.file, 0, o.assetBase, b.height || 80, ed ? ' data-bimg="' + i + '" draggable="false"' + (ed.selectedBody === i ? ' class="sel"' : '') : '') + '</span>').join('') +
      '</div>';
  }

  function renderLetter(o) {
    const s = o.settings || {};
    const contact = [s.address, s.phone, s.email].filter(Boolean).map(esc).join(' &nbsp;|&nbsp; ');
    const lay = headerLayout(letterLogos(o), o.headerTextTop);
    const ed = o.editable;
    const logos = lay.logos.map((l, i) => '<span class="lh-lg" style="left:' + l.x + '%;top:' + l.y + 'px;width:' + logoWidth(l) + 'px">' +
      imgTag(l.file, 0, o.assetBase, l.height || 84, ed ? ' data-logo="' + i + '" draggable="false"' + (ed.selectedLogo === i ? ' class="sel"' : '') : '') + '</span>').join('');
    const center = '<div class="lh-band" style="min-height:' + lay.bandMin + 'px">' + logos +
      '<div class="lh-text" style="padding-top:' + lay.textTop + 'px">' + (ed ? '<div class="lh-drag" data-htext="1" title="Drag up or down">' : '<div>') +
      '<div class="lh-org">' + esc(s.orgName || '') + '</div>' +
      (s.motto ? '<div class="lh-motto">' + esc(s.motto) + '</div>' : '') +
      (o.departmentName ? '<div class="lh-dept">' + esc(o.departmentName) + '</div>' : '') + '</div></div></div>';
    // Optional image under the letterhead (e.g. a banner or flag stripe) and up to 3 footer images.
    const banner = o.headerImage && IMAGE_FILE.test(o.headerImage.file) ? o.headerImage : null;
    const foot = (Array.isArray(o.footerImages) ? o.footerImages : []).filter((l) => IMAGE_FILE.test(l.file));
    const footImg = (pos) => {
      const l = foot.find((x) => x.position === pos);
      return l ? imgTag(l.file, 0, o.assetBase, l.height || 50,
        ed ? ' data-foot="' + pos + '" draggable="true"' + (ed.selectedFoot === pos ? ' class="sel"' : '') : '') : '';
    };
    return '<div class="letter' + (ed ? ' edz' : '') + '">' +
      (o.watermark ? '<div class="wm">' + esc(o.watermark) + '</div>' : '') +
      '<header class="lh">' +
        center +
        (contact ? '<div class="lh-contact">' + contact + '</div>' : '') +
        (banner ? '<div class="lh-banner" style="text-align:' + (banner.align || 'center') + '">' +
          imgTag(banner.file, 0, o.assetBase, banner.height || 60, ed ? ' data-banner="1"' + (ed.selectedBanner ? ' class="sel"' : '') : '') + '</div>' : '') +
      '</header>' +
      bodyArea(o, ed) +
      (s.footer || foot.length || ed
        ? '<footer class="lf">' +
          (foot.length ? '<table class="lf-t"><tr>' + LOGO_POSITIONS.map((p) => '<td class="lf-' + p[0] + '">' + footImg(p) + '</td>').join('') + '</tr></table>'
            : (ed ? '<div class="lf-hint">Footer – drag images here</div>' : '')) +
          (s.footer ? '<div>' + esc(s.footer) + '</div>' : '') + '</footer>'
        : '') +
      '</div>';
  }

  const LETTER_CSS = `
.letter{position:relative;background:#fff;color:#111;font-family:"Times New Roman",Times,serif;font-size:12pt;line-height:1.5;padding:48px 56px;max-width:794px;margin:0 auto;overflow:hidden;display:flex;flex-direction:column;min-height:calc(min(100cqw,794px)*1.4142)}
.letter .lbw{position:relative;flex:1 0 auto;display:flex;flex-direction:column}
.letter .lb{flex:1 0 auto}
.letter .lb-free{position:absolute;line-height:0;z-index:2}
.letter .lb-free img{max-width:none!important}
.letter.edz .lb-free img{cursor:move;touch-action:none}
.letter.edz .lb img[data-img]{touch-action:none}
.letter.edz{overflow:visible}
.letter.edz .lf{margin-top:36px}
.letter.edz .lh,.letter.edz .lb,.letter.edz .lf{position:relative;outline-offset:8px;border-radius:2px}
.letter.edz .lh{outline:2px dashed #b9cff5}
.letter.edz .lb{outline:2px dashed #cfd6df}
.letter.edz .lf{outline:2px dashed #b6e0c6}
.letter.edz .lh::before,.letter.edz .lb::before,.letter.edz .lf::before{position:absolute;left:-6px;top:-20px;font:700 9px/16px Arial,sans-serif;letter-spacing:.1em;padding:0 7px;border-radius:8px;z-index:2}
.letter.edz .lh::before{content:"HEADER";background:#e3ecfb;color:#2f6fde}
.letter.edz .lb::before{content:"BODY";background:#eceff3;color:#44505e}
.letter.edz .lf::before{content:"FOOTER";background:#e3f4ea;color:#1f8a4c}
.letter .lh{text-align:center;border-bottom:3px double #1f3a5f;padding-bottom:10px;margin-bottom:22px}
.letter .lh-logo{margin-bottom:6px}
.letter .lh-logo img{display:inline-block}
.letter .lh-band{position:relative}
.letter .lh-lg{position:absolute;line-height:0;z-index:1}
.letter .lh-lg img{max-width:none!important}
.letter .lh-text{position:relative}
.letter.edz .lh-lg img{cursor:move;touch-action:none}
.letter.edz .lh-drag{cursor:ns-resize;touch-action:none;border-radius:4px}
.letter.edz .lh-drag:hover{outline:1px dashed #9dbbf0;outline-offset:3px}
.letter .lb img{vertical-align:middle}
.letter .lb p.limg{margin:0 0 12px}
.letter .lh-org{font-size:18pt;font-weight:bold;letter-spacing:.04em;text-transform:uppercase;color:#1f3a5f}
.letter .lh-motto{font-style:italic;font-size:10pt;color:#444}
.letter .lh-dept{font-size:12pt;font-weight:bold;text-transform:uppercase;margin-top:2px}
.letter .lh-contact{font-size:9pt;color:#333;margin-top:4px}
.letter .lb p{margin:0 0 12px}
.letter .lb ul,.letter .lb ol{margin:0 0 12px;padding-left:28px}
.letter .lh-banner{margin-top:8px}
.letter .lf-t{width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:4px}
.letter .lf-t td{vertical-align:middle;padding:0}
.letter .lf-l{text-align:left}.letter .lf-c{text-align:center}.letter .lf-r{text-align:right}
.letter .lf-hint{color:#9aa3ad;font:italic 10pt Arial,sans-serif;border:1px dashed #cfd6df;border-radius:6px;padding:8px;margin-bottom:4px}
.letter .lf{border-top:1px solid #999;margin-top:28px;padding-top:6px;font-size:8.5pt;color:#444;text-align:center}
.letter .ph{background:#fff4c2;color:#7a5b00;border-radius:3px;padding:0 3px;font-family:Arial,sans-serif;font-size:10pt}
.letter .wm{position:absolute;top:40%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font:bold 96pt Arial,sans-serif;color:rgba(200,30,30,.09);pointer-events:none;white-space:nowrap}
`;

  return { freeImages, SYSTEM_FIELDS, IMAGE_FILE, LOGO_POSITIONS, HEADER_W, MAX_LOGOS, letterLogos, normLogos, logoWidth, clampX, headerLayout, esc, formatDate, fieldKeys, imageKeys, imgTag, markup, renderBody, renderLetter, LETTER_CSS };
});
