/**
 * Tesapé: what the game client and the teacher panel share. Web services, texts, little helpers and the drawing of
 * the characters (placeholder art: modular and by parameters, as the character workshop will need).
 *
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var cfg = window.TESAPE_CONFIG || {};
  var T = window.Tesape = {cfg: cfg};

  /**
   * A text of the language pack, with {$a} replaced.
   *
   * @param {string} key
   * @param {*} a
   * @return {string}
   */
  T.t = function(key, a) {
    var s = (cfg.strings && cfg.strings[key]) || '[' + key + ']';
    return a === undefined ? s : s.split('{$a}').join(String(a));
  };

  /**
   * Escapes text for HTML.
   *
   * @param {*} s
   * @return {string}
   */
  T.esc = function(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function(c) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
    });
  };

  /**
   * Calls a web service of the plugin.
   *
   * @param {string} name Without the component prefix.
   * @param {Object} args
   * @return {Promise}
   */
  T.api = function(name, args) {
    var url = cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(cfg.sesskey) +
      '&info=local_oksigeniatesape_' + name;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify([{index: 0, methodname: 'local_oksigeniatesape_' + name, args: args || {}}])
    }).then(function(response) {
      return response.json();
    }).then(function(data) {
      var result = Array.isArray(data) ? data[0] : data;
      if (!result || result.error) {
        var e = (result && (result.exception || result)) || {};
        if (e.errorcode === 'servicerequireslogin' || e.errorcode === 'invalidsesskey') {
          window.location.reload();
        }
        throw new Error(e.message || T.t('app_error'));
      }
      return result.data;
    });
  };

  /**
   * Shows a short message for a moment.
   *
   * @param {string} html
   * @param {boolean} bad
   */
  T.toast = function(html, bad) {
    var box = document.querySelector('.toasts');
    if (!box) {
      box = document.createElement('div');
      box.className = 'toasts';
      box.setAttribute('aria-live', 'polite');
      document.body.appendChild(box);
    }
    var el = document.createElement('div');
    el.className = 'toast' + (bad ? ' bad' : '');
    el.innerHTML = html;
    box.appendChild(el);
    setTimeout(function() {
      el.remove();
    }, 3800);
  };

  /**
   * The role name in the family of the course, in the form the student chose.
   *
   * @param {string} family
   * @param {string} role
   * @param {string} form
   * @return {string}
   */
  T.roleName = function(family, role, form) {
    return T.t('role_' + family + '_' + role + '_' + (form || 'm'));
  };

  /**
   * Whether a family has two forms for a role (Mago / Maga).
   *
   * @param {string} family
   * @param {string} role
   * @return {boolean}
   */
  T.hasForms = function(family, role) {
    return T.roleName(family, role, 'm') !== T.roleName(family, role, 'f');
  };

  /**
   * Whether a family has lineages (only the fantasy ones).
   *
   * @param {string} family
   * @return {boolean}
   */
  T.hasLineages = function(family) {
    return family === 'storyheroes' || family === 'epic';
  };

  T.lineageName = function(lineage, form) {
    return lineage ? T.t('lineage_' + lineage + '_' + (form || 'm')) : '';
  };

  /* ---------- The look of the characters ---------- */

  T.ROLES = ['protector', 'healer', 'sage', 'scout'];
  T.LINEAGES = ['human', 'elf', 'dwarf', 'fairy'];
  T.SKINS = ['#f6d7bd', '#eec09a', '#d9a173', '#b97a4f', '#8d5636', '#5e3a24'];
  T.HAIRS = ['short', 'curly', 'long', 'braid', 'bun', 'spiky'];
  T.HAIRCOLOURS = ['#2b1d14', '#5b3a1f', '#8a5a2b', '#c58b3c', '#e6c06b', '#b33a2b', '#6d6d72', '#3b5bbf'];
  T.OUTFITS = ['#3f6fb5', '#2f8f6a', '#7b5bc4', '#c0563f', '#d39a2a', '#2e7f9a', '#9a4d7a', '#556070'];
  T.ROLE_OUTFIT = {protector: '#3f6fb5', healer: '#2f8f6a', sage: '#7b5bc4', scout: '#c0563f'};

  /**
   * The look by default for a role.
   *
   * @param {string} role
   * @return {Object}
   */
  T.defaultLook = function(role) {
    return {skin: T.SKINS[1], hair: 'short', haircolour: T.HAIRCOLOURS[1], outfit: T.ROLE_OUTFIT[role] || T.OUTFITS[0]};
  };

  /**
   * A colour a bit darker or lighter.
   *
   * @param {string} hex
   * @param {number} f -1 to 1.
   * @return {string}
   */
  function shade(hex, f) {
    var n = parseInt(hex.slice(1), 16);
    var r = n >> 16, g = (n >> 8) & 255, b = n & 255;
    var t = f < 0 ? 0 : 255, p = Math.abs(f);
    r = Math.round((t - r) * p + r);
    g = Math.round((t - g) * p + g);
    b = Math.round((t - b) * p + b);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  }

  /**
   * Draws a character.
   *
   * @param {Object} c role, nameform, lineage, look (object or JSON), stage, label.
   * @return {string} SVG.
   */
  T.avatar = function(c) {
    var look = c.look || {};
    if (typeof look === 'string') {
      try {
        look = JSON.parse(look || '{}') || {};
      } catch (e) {
        look = {};
      }
    }
    look = Object.assign(T.defaultLook(c.role), look);
    var skin = /^#[0-9a-f]{6}$/i.test(look.skin) ? look.skin : T.SKINS[1];
    var hairc = /^#[0-9a-f]{6}$/i.test(look.haircolour) ? look.haircolour : T.HAIRCOLOURS[1];
    var cloth = /^#[0-9a-f]{6}$/i.test(look.outfit) ? look.outfit : T.OUTFITS[0];
    var form = c.nameform === 'f' ? 'f' : 'm';
    var stage = c.stage || 1;
    var dwarf = c.lineage === 'dwarf';
    var out = [];
    // Wings behind (fairy).
    if (c.lineage === 'fairy') {
      out.push('<g opacity=".8"><path d="M44 70 C14 40 6 78 20 94 C28 102 40 92 46 82Z" fill="#cfeaff" stroke="#9cc9ee"/>' +
        '<path d="M76 70 C106 40 114 78 100 94 C92 102 80 92 74 82Z" fill="#cfeaff" stroke="#9cc9ee"/></g>');
    }
    // Aura at the top stage.
    if (stage >= 5) {
      out.push('<circle cx="60" cy="70" r="56" fill="none" stroke="#ffc857" stroke-opacity=".45" stroke-width="3"/>');
    }
    // Cape from stage 4.
    if (stage >= 4) {
      out.push('<path d="M38 74 Q26 118 30 136 L90 136 Q94 118 82 74Z" fill="' + shade(cloth, -0.45) + '"/>');
    }
    // Body.
    var bodyTop = dwarf ? 82 : 76;
    var w = dwarf ? 30 : 24;
    out.push('<path d="M' + (60 - w) + ' 136 Q' + (60 - w - 2) + ' ' + (bodyTop + 10) + ' 60 ' + bodyTop +
      ' Q' + (60 + w + 2) + ' ' + (bodyTop + 10) + ' ' + (60 + w) + ' 136Z" fill="' + cloth + '"/>');
    out.push('<path d="M60 ' + bodyTop + ' L52 136 L68 136Z" fill="' + shade(cloth, -0.18) + '"/>');
    // Arms.
    out.push('<path d="M' + (60 - w + 2) + ' ' + (bodyTop + 10) + ' Q' + (60 - w - 10) + ' 106 ' + (60 - w - 4) +
      ' 118" stroke="' + shade(cloth, -0.1) + '" stroke-width="9" stroke-linecap="round" fill="none"/>');
    out.push('<path d="M' + (60 + w - 2) + ' ' + (bodyTop + 10) + ' Q' + (60 + w + 10) + ' 104 ' + (60 + w + 6) +
      ' 112" stroke="' + shade(cloth, -0.1) + '" stroke-width="9" stroke-linecap="round" fill="none"/>');
    out.push('<circle cx="' + (60 - w - 4) + '" cy="119" r="5" fill="' + skin + '"/>');
    out.push('<circle cx="' + (60 + w + 6) + '" cy="113" r="5" fill="' + skin + '"/>');
    // Belt or trim from stage 2.
    if (stage >= 2) {
      out.push('<path d="M' + (60 - w + 1) + ' 112 Q60 117 ' + (60 + w - 1) + ' 112" stroke="#ffc857" stroke-width="4" ' +
        'fill="none"/><rect x="56" y="110" width="8" height="7" rx="1.5" fill="#ffe29a"/>');
    }
    // Neck and head.
    var hy = dwarf ? 58 : 52;
    out.push('<rect x="55" y="' + (hy + 14) + '" width="10" height="10" rx="3" fill="' + shade(skin, -0.12) + '"/>');
    if (c.lineage === 'elf') {
      out.push('<path d="M41 ' + (hy + 2) + ' L28 ' + (hy - 12) + ' L44 ' + (hy + 8) + 'Z" fill="' + skin + '"/>' +
        '<path d="M79 ' + (hy + 2) + ' L92 ' + (hy - 12) + ' L76 ' + (hy + 8) + 'Z" fill="' + skin + '"/>');
    } else {
      out.push('<circle cx="42" cy="' + (hy + 3) + '" r="4" fill="' + shade(skin, -0.08) + '"/>' +
        '<circle cx="78" cy="' + (hy + 3) + '" r="4" fill="' + shade(skin, -0.08) + '"/>');
    }
    // Hair behind the head.
    var hair = look.hair;
    if (hair === 'long' || (form === 'f' && dwarf)) {
      out.push('<path d="M41 ' + hy + ' Q36 ' + (hy + 30) + ' 44 ' + (hy + 34) + ' L76 ' + (hy + 34) + ' Q84 ' + (hy + 30) +
        ' 79 ' + hy + 'Z" fill="' + shade(hairc, -0.1) + '"/>');
    }
    out.push('<circle cx="60" cy="' + hy + '" r="19" fill="' + skin + '"/>');
    // Face.
    out.push('<circle cx="52" cy="' + (hy + 2) + '" r="3.4" fill="#fff"/><circle cx="68" cy="' + (hy + 2) +
      '" r="3.4" fill="#fff"/>');
    out.push('<circle cx="52.6" cy="' + (hy + 2.6) + '" r="2.2" fill="#2a2219"/><circle cx="68.6" cy="' + (hy + 2.6) +
      '" r="2.2" fill="#2a2219"/>');
    out.push('<circle cx="53.4" cy="' + (hy + 1.6) + '" r=".8" fill="#fff"/><circle cx="69.4" cy="' + (hy + 1.6) +
      '" r=".8" fill="#fff"/>');
    out.push('<path d="M48 ' + (hy - 4) + ' Q52 ' + (hy - 6.5) + ' 56 ' + (hy - 4.5) + ' M64 ' + (hy - 4.5) + ' Q68 ' +
      (hy - 6.5) + ' 72 ' + (hy - 4) + '" stroke="' + shade(hairc, -0.2) + '" stroke-width="1.6" fill="none" ' +
      'stroke-linecap="round"/>');
    out.push('<circle cx="47" cy="' + (hy + 8) + '" r="3.2" fill="#ff8f8f" opacity=".35"/><circle cx="73" cy="' +
      (hy + 8) + '" r="3.2" fill="#ff8f8f" opacity=".35"/>');
    out.push('<path d="M55 ' + (hy + 9) + ' Q60 ' + (hy + 13) + ' 65 ' + (hy + 9) + '" stroke="#7a3b2b" stroke-width="1.8" ' +
      'fill="none" stroke-linecap="round"/>');
    // Beard (dwarf).
    if (dwarf && form === 'm') {
      out.push('<path d="M44 ' + (hy + 6) + ' Q46 ' + (hy + 34) + ' 60 ' + (hy + 38) + ' Q74 ' + (hy + 34) + ' 76 ' +
        (hy + 6) + ' Q68 ' + (hy + 14) + ' 60 ' + (hy + 13) + ' Q52 ' + (hy + 14) + ' 44 ' + (hy + 6) + 'Z" fill="' +
        hairc + '"/><path d="M55 ' + (hy + 10) + ' Q60 ' + (hy + 14) + ' 65 ' + (hy + 10) + '" stroke="#7a3b2b" ' +
        'stroke-width="1.8" fill="none"/>');
    }
    // Hair on top.
    var top = 'M41 ' + (hy - 2) + ' Q42 ' + (hy - 22) + ' 60 ' + (hy - 22) + ' Q78 ' + (hy - 22) + ' 79 ' + (hy - 2) +
      ' Q72 ' + (hy - 12) + ' 60 ' + (hy - 12) + ' Q48 ' + (hy - 12) + ' 41 ' + (hy - 2) + 'Z';
    if (hair === 'curly') {
      var curls = '';
      [[44, -10], [50, -17], [58, -20], [66, -19], [73, -14], [77, -6], [42, -3]].forEach(function(p) {
        curls += '<circle cx="' + p[0] + '" cy="' + (hy + p[1]) + '" r="7" fill="' + hairc + '"/>';
      });
      out.push(curls);
    } else if (hair === 'spiky') {
      out.push('<path d="M41 ' + (hy - 2) + ' L40 ' + (hy - 18) + ' L49 ' + (hy - 13) + ' L52 ' + (hy - 27) + ' L60 ' +
        (hy - 16) + ' L67 ' + (hy - 28) + ' L71 ' + (hy - 14) + ' L80 ' + (hy - 19) + ' L79 ' + (hy - 2) + ' Q70 ' +
        (hy - 11) + ' 60 ' + (hy - 11) + ' Q50 ' + (hy - 11) + ' 41 ' + (hy - 2) + 'Z" fill="' + hairc + '"/>');
    } else {
      out.push('<path d="' + top + '" fill="' + hairc + '"/>');
    }
    if (hair === 'bun') {
      out.push('<circle cx="60" cy="' + (hy - 25) + '" r="8" fill="' + hairc + '"/>');
    }
    if (hair === 'braid' || (form === 'f' && dwarf)) {
      out.push('<path d="M77 ' + (hy + 2) + ' Q84 ' + (hy + 20) + ' 80 ' + (hy + 36) + '" stroke="' + hairc + '" ' +
        'stroke-width="7" stroke-linecap="round" fill="none" stroke-dasharray="6 2"/>');
      if (dwarf) {
        out.push('<path d="M43 ' + (hy + 2) + ' Q36 ' + (hy + 20) + ' 40 ' + (hy + 36) + '" stroke="' + hairc + '" ' +
          'stroke-width="7" stroke-linecap="round" fill="none" stroke-dasharray="6 2"/>');
      }
    }
    // Circlet from stage 3.
    if (stage >= 3) {
      out.push('<path d="M45 ' + (hy - 14) + ' Q60 ' + (hy - 19) + ' 75 ' + (hy - 14) + '" stroke="#ffc857" ' +
        'stroke-width="3" fill="none"/><circle cx="60" cy="' + (hy - 17.5) + '" r="3" fill="#8fd3ff" stroke="#ffc857"/>');
    }
    // What the role carries.
    var hx = 60 + w + 6;
    if (c.role === 'protector') {
      out.push('<path d="M' + (hx - 2) + ' 96 L' + (hx + 18) + ' 92 L' + (hx + 18) + ' 114 Q' + (hx + 8) + ' 128 ' +
        (hx - 2) + ' 114Z" fill="#cfdbe8" stroke="#4d6b8a" stroke-width="2"/><circle cx="' + (hx + 8) + '" cy="106" ' +
        'r="4.5" fill="#ffc857"/>');
    } else if (c.role === 'healer') {
      out.push('<line x1="' + hx + '" y1="84" x2="' + hx + '" y2="136" stroke="#7a5a2e" stroke-width="4" ' +
        'stroke-linecap="round"/><path d="M' + hx + ' 72 Q' + (hx + 14) + ' 80 ' + hx + ' 96 Q' + (hx - 12) + ' 82 ' + hx +
        ' 72Z" fill="#9fe0a3" stroke="#4f8f45"/>');
    } else if (c.role === 'sage') {
      out.push('<line x1="' + hx + '" y1="78" x2="' + hx + '" y2="136" stroke="#7a5a2e" stroke-width="4" ' +
        'stroke-linecap="round"/><path d="M' + hx + ' 62 l3.5 7 7.5 1 -5.5 5 1.4 7.5 -6.9 -3.6 -6.9 3.6 1.4 -7.5 ' +
        '-5.5 -5 7.5 -1z" fill="#ffe38a" stroke="#e9a92b"/>');
    } else if (c.role === 'scout') {
      out.push('<path d="M' + (hx + 4) + ' 80 Q' + (hx + 22) + ' 106 ' + (hx + 4) + ' 132" stroke="#7a5a2e" ' +
        'stroke-width="3.5" fill="none"/><line x1="' + (hx + 4) + '" y1="80" x2="' + (hx + 4) + '" y2="132" ' +
        'stroke="#eee" stroke-width="1"/>');
    }
    var label = c.label ? ' role="img" aria-label="' + T.esc(c.label) + '"' : ' aria-hidden="true"';
    var box = c.portrait ? '30 ' + (hy - 30) + ' 60 60' : '0 0 120 140';
    return '<svg class="avatar" viewBox="' + box + '"' + label + '>' + out.join('') + '</svg>';
  };

  /* ---------- Icons ---------- */

  T.icon = function(name) {
    var p = {
      saber: '<path d="M12 2l2.6 6.1L21 9l-4.9 4.3L17.5 20 12 16.6 6.5 20l1.4-6.7L3 9l6.4-.9z" fill="#ffc857"/>',
      gems: '<path d="M6 3h12l4 6-10 12L2 9z" fill="#8fd3ff"/><path d="M2 9h20M9 3l3 18 3-18" stroke="#3b7fa8" ' +
        'fill="none" stroke-width="1.2"/>',
      energy: '<path d="M12 21s-8-5.2-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.8-8 11-8 11z" fill="#ef7d5c"/>',
      breath: '<path d="M3 8h11a3 3 0 1 0-3-3M3 12h15a3 3 0 1 1-3 3M3 16h8" stroke="#8fd3ff" stroke-width="2.2" ' +
        'fill="none" stroke-linecap="round"/>',
      world: '<path d="M3 20l6-12 4 7 3-4 5 9z" fill="currentColor" opacity=".9"/><circle cx="17" cy="6" r="2.4" ' +
        'fill="currentColor"/>',
      team: '<circle cx="8" cy="8" r="3.2" fill="currentColor"/><circle cx="16.5" cy="9" r="2.8" fill="currentColor" ' +
        'opacity=".75"/><path d="M2 20c.5-4 3-6 6-6s5.5 2 6 6zM13 20c.3-2.8 1.8-4.6 3.5-4.6S20.7 17.2 22 20z" ' +
        'fill="currentColor"/>',
      challenges: '<path d="M5 3v18" stroke="currentColor" stroke-width="2"/><path d="M6 4h12l-3 4 3 4H6z" ' +
        'fill="currentColor"/>',
      log: '<path d="M5 3h11l3 3v15H5z" fill="currentColor" opacity=".9"/><path d="M8 9h8M8 13h8M8 17h5" ' +
        'stroke="#0f2131" stroke-width="1.6"/>',
      me: '<circle cx="12" cy="8" r="4" fill="currentColor"/><path d="M4 21c.7-5 4-7 8-7s7.3 2 8 7z" fill="currentColor"/>',
      class: '<rect x="3" y="4" width="18" height="12" rx="2" fill="currentColor" opacity=".9"/><path d="M8 20h8M12 16v4" ' +
        'stroke="currentColor" stroke-width="2"/>',
      settings: '<circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v3M12 ' +
        '19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1" stroke="currentColor" ' +
        'stroke-width="2" stroke-linecap="round"/>'
    }[name] || '';
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' + p + '</svg>';
  };

  /**
   * A picture of the valley at dusk, for the story screens and the region card.
   *
   * @param {string} kind prologue or region.
   * @return {string}
   */
  T.vignette = function(kind) {
    var lamps = kind === 'region' ? [[90, 96, 0.5], [170, 84, 1], [250, 92, 0.35]] : [[170, 86, 1]];
    var lights = lamps.map(function(l) {
      return '<circle cx="' + l[0] + '" cy="' + l[1] + '" r="16" fill="#ffc857" opacity="' + (0.18 * l[2]) + '"/>' +
        '<rect x="' + (l[0] - 1.5) + '" y="' + (l[1] - 2) + '" width="3" height="20" fill="#2b2219"/>' +
        '<circle cx="' + l[0] + '" cy="' + (l[1] - 4) + '" r="4.5" fill="#ffc857" opacity="' + (0.35 + 0.65 * l[2]) + '"/>';
    }).join('');
    return '<div class="vignette" aria-hidden="true"><svg viewBox="0 0 340 130" preserveAspectRatio="xMidYMid slice">' +
      '<defs><linearGradient id="tsk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1b3a57"/>' +
      '<stop offset="1" stop-color="#2d5470"/></linearGradient></defs>' +
      '<rect width="340" height="130" fill="url(#tsk)"/><circle cx="292" cy="30" r="13" fill="#fff3c4" opacity=".85"/>' +
      '<path d="M0 104 Q60 58 130 92 T260 80 T340 86 V130 H0Z" fill="#1f4a3a"/>' +
      '<path d="M0 118 Q90 90 180 110 T340 104 V130 H0Z" fill="#163829"/>' +
      '<path d="M40 112 L56 66 L72 112Z M266 108 L284 58 L302 108Z" fill="#12301f"/>' + lights + '</svg></div>';
  };
})();
