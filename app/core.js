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
   * A text of the language pack, with {$a} (or {$a->name}, when a is an object) replaced.
   *
   * @param {string} key
   * @param {*} a
   * @return {string}
   */
  T.t = function(key, a) {
    var s = (cfg.strings && cfg.strings[key]) || '[' + key + ']';
    if (a === undefined) {
      return s;
    }
    if (a !== null && typeof a === 'object') {
      return s.replace(/\{\$a->(\w+)\}/g, function(m, name) {
        return a[name] === undefined ? m : String(a[name]);
      });
    }
    return s.split('{$a}').join(String(a));
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
    // Errors say whether the server answered (errorcode) or did not (network), so that queues know what to retry.
    var offline = function() {
      var err = new Error(T.t('app_error'));
      err.network = true;
      return err;
    };
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify([{index: 0, methodname: 'local_oksigeniatesape_' + name, args: args || {}}])
    }).catch(function() {
      throw offline();
    }).then(function(response) {
      if (!response.ok) {
        throw offline();
      }
      return response.json().catch(function() {
        throw offline();
      });
    }).then(function(data) {
      var result = Array.isArray(data) ? data[0] : data;
      if (!result || result.error) {
        var e = (result && (result.exception || result)) || {};
        if (e.errorcode === 'servicerequireslogin' || e.errorcode === 'invalidsesskey') {
          window.location.reload();
        }
        var err = new Error(e.message || T.t('app_error'));
        err.errorcode = e.errorcode || 'error';
        throw err;
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
   * Whether the characters of a family are people: then the student picks a boy or a girl together with the role.
   *
   * @param {string} family
   * @return {boolean}
   */
  T.isPeople = function(family) {
    return family === 'storyheroes' || family === 'epic' || family === 'nomagic';
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

  /**
   * The emblem of Tesapé, in its small drawing (for 24 to 48 px).
   *
   * @return {string}
   */
  T.emblem = function() {
    return '<img class="emblem" src="' + T.esc(cfg.wwwroot) + '/local/oksigeniatesape/app/emblem.svg" alt="">';
  };

  /* ---------- Art packs ---------- */

  /**
   * The image of the art pack of the site for the first of some keys, from the most precise to the most general.
   * Without a pack, or without an image for any key, null: then the client draws its own.
   *
   * @param {string[]} keys
   * @return {string|null}
   */
  T.artUrl = function(keys) {
    var files = cfg.art && cfg.art.files;
    if (!files) {
      return null;
    }
    for (var i = 0; i < keys.length; i++) {
      if (files[keys[i]]) {
        return files[keys[i]];
      }
    }
    return null;
  };

  /* ---------- The map of a mission ---------- */

  /** @var {string[]} The zones of a map in layers, from the bottom up. */
  var ZONES = ['meadow', 'forest', 'lake', 'mountain'];

  /**
   * The height of the map of a mission: 640, the painted map, or that of a long map in layers.
   *
   * @param {Object} m The mission (map, mapworld, mapheight, code, skin, chapters).
   * @return {number}
   */
  T.mapHeight = function(m) {
    return m.mapworld && m.mapheight > 640 ? m.mapheight : 640;
  };

  /**
   * The ground of the map of a mission, in its SVG: the painted map; or, for a map in layers, the bands of its world
   * stacked from the bottom up (meadow, forest, lake, mountain) with the drawing of the night under them until the
   * art arrives.
   *
   * @param {Object} m
   * @return {string}
   */
  T.mapGround = function(m) {
    var height = T.mapHeight(m);
    var night = '<rect width="400" height="' + height + '" fill="url(#mapglow)"/>';
    if (m.mapworld && m.mapheight) {
      var band = 533;
      var count = Math.ceil(height / band);
      var bands = '';
      for (var i = 0; i < count; i++) {
        // The meadow at the bottom and the mountain at the top, where the last place is; forest and lake between.
        var zone = i === count - 1 ? 'mountain' : i === 0 ? 'meadow' : ZONES[1 + (i - 1) % 2];
        var art = T.artUrl(['band.' + m.mapworld + '.' + zone]);
        // A little taller than their place, so that no line shows between two of them.
        bands += art ? '<image href="' + T.esc(art) + '" x="0" y="' + (height - (i + 1) * band - 2) + '" width="400" ' +
          'height="' + (band + 4) + '" preserveAspectRatio="xMidYMid slice"/>' : '';
      }
      return night + bands;
    }
    var map = m.map || m.code;
    var painted = T.artUrl(['map.' + map + '.' + m.skin, 'map.' + map]);
    return painted ? '<image href="' + T.esc(painted) + '" x="0" y="0" width="400" height="640" ' +
      'preserveAspectRatio="xMidYMid slice"/>' : night +
      '<path d="M0 600 Q120 520 220 590 T400 560 V640 H0Z" fill="#183a2b"/>' +
      '<path d="M0 230 Q90 190 170 230 T400 200 V260 Q300 300 200 270 T0 290Z" fill="#1d4a5e" opacity=".6"/>' +
      '<path d="M20 120 L52 50 L84 120Z M330 380 L356 318 L382 380Z M40 470 L64 414 L88 470Z" fill="#163829"/>';
  };

  /**
   * The landmarks of the places of a map in layers, behind their lanterns.
   *
   * @param {Object} m
   * @return {string}
   */
  T.mapLandmarks = function(m) {
    if (!m.mapworld) {
      return '';
    }
    return m.chapters.map(function(ch) {
      var art = ch.landmark ? T.artUrl(['landmark.' + m.mapworld + '.' + ch.landmark]) : null;
      return art ? '<image class="landmark" href="' + T.esc(art) + '" x="' + (ch.x - 56) + '" y="' + (ch.y - 118) +
        '" width="112" height="112"/>' : '';
    }).join('');
  };

  /** The gradient of the night under the drawn map, for the defs of its SVG. */
  T.MAPGLOW = '<radialGradient id="mapglow" cx="50%" cy="40%" r="70%"><stop offset="0" stop-color="#21465c"/>' +
    '<stop offset="1" stop-color="#10283b"/></radialGradient>';

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
    // No skin until the student picks one: an art pack then shows each character as it is painted.
    return {hair: 'short', haircolour: T.HAIRCOLOURS[1], outfit: T.ROLE_OUTFIT[role] || T.OUTFITS[0]};
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
   * The keys of the image of a character in an art pack, from the most precise to the most general: family, role,
   * lineage, form of the name and stage, each first with the tone of skin the student chose (t1 light, t2 medium, t3
   * dark). Without a choice, only the images as they are painted.
   *
   * @param {string} family
   * @param {string} role
   * @param {string} lineage
   * @param {string} form m or f.
   * @param {number} stage
   * @param {number} skin Index of the skin colour (-1: unknown).
   * @param {boolean} portrait For the small round picture.
   * @return {string[]}
   */
  T.characterKeys = function(family, role, lineage, form, stage, skin, portrait) {
    var tone = skin < 0 ? 0 : skin < 2 ? 1 : skin < 4 ? 2 : 3;
    var keys = [];
    (portrait ? ['portrait', 'character'] : ['character']).forEach(function(kind) {
      var b = kind + '.' + family + '.' + role;
      var lin = lineage || 'none';
      [b + '.' + lin + '.' + form + '.' + stage, b + '.' + lin + '.' + form, b + '.' + form + '.' + stage, b + '.' + form,
        b + '.' + stage, b].forEach(function(k) {
        if (tone) {
          keys.push(k + '.t' + tone);
        }
        keys.push(k);
      });
    });
    return keys;
  };

  /**
   * What of the look shows on a character. Without an art pack the client draws all of it; with one, the hair and the
   * clothes are painted in, and only the tone of skin can change, when the pack has an image for each tone.
   *
   * @param {Object} c role, nameform, lineage, stage.
   * @return {Object} art, and skin, hair, haircolour and outfit: true when they show.
   */
  T.lookShows = function(c) {
    var keys = function(skin) {
      return T.characterKeys(T.family || 'storyheroes', c.role, c.lineage, c.nameform === 'f' ? 'f' : 'm', c.stage || 1,
        skin, false);
    };
    if (!c.role || !T.artUrl(keys(-1))) {
      return {art: false, skin: true, hair: true, haircolour: true, outfit: true};
    }
    return {art: true, skin: T.artUrl(keys(0)) !== T.artUrl(keys(T.SKINS.length - 1)), hair: false, haircolour: false,
      outfit: false};
  };

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
    var art = T.artUrl(T.characterKeys(c.family || T.family || 'storyheroes', c.role, c.lineage, form, stage,
      T.SKINS.indexOf(look.skin), c.portrait));
    if (art) {
      return '<img class="avatar art' + (c.portrait ? ' portrait' : '') + '" src="' + T.esc(art) + '" alt="' +
        T.esc(c.label || '') + '">';
    }
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

  /**
   * Draws the companion creature from its traits.
   *
   * @param {string|Object} traits JSON or object: hue, ears, eyes, round, name.
   * @param {number} stage 1 when it hatches, up to 4.
   * @param {boolean} big With the hatching animation.
   * @return {string} SVG, or empty before it hatches.
   */
  /**
   * Paints of the market of the valley: a filter for the drawn creature (whose own colour is orange, about 35º), and a
   * hue for the one made of shapes.
   */
  var PAINTS = {
    'paint-gold': {filter: 'hue-rotate(17deg) saturate(1.6) brightness(1.18)', hue: 45},
    'paint-night': {filter: 'hue-rotate(190deg) saturate(.9) brightness(.75)', hue: 240},
    'paint-snow': {filter: 'grayscale(.92) brightness(1.25)', hue: 205}
  };

  T.creature = function(traits, stage, big) {
    var c = traits;
    if (typeof c === 'string') {
      try {
        c = JSON.parse(c || 'null');
      } catch (e) {
        c = null;
      }
    }
    if (!c) {
      return '';
    }
    var paint = PAINTS[c.paint];
    var h = paint ? paint.hue : Number(c.hue) || 0;
    var st = Math.max(1, Math.min(4, stage || 1));
    var art = T.artUrl(['creature.' + st + '.' + (c.coat || 'none'), 'creature.' + st, 'creature']);
    if (art) {
      // One image for every creature: its own hue makes it unique, or the paint bought in the market.
      return '<img class="creature art' + (big ? ' born' : '') + '" src="' + T.esc(art) + '" alt="' + T.esc(c.name || '') +
        '" style="filter:' + (paint ? paint.filter : 'hue-rotate(' + h + 'deg)') + '">';
    }
    var s = 1 + (st - 1) * 0.12;
    var body = 'hsl(' + h + ' 60% 62%)';
    var dark = 'hsl(' + h + ' 60% 52%)';
    var belly = 'hsl(' + h + ' 70% 84%)';
    var ears = c.ears === 0 ? '<path d="M30 34 L24 12 L42 28Z M70 34 L76 12 L58 28Z" fill="' + dark + '"/>' :
      c.ears === 1 ? '<circle cx="30" cy="26" r="9" fill="' + dark + '"/><circle cx="70" cy="26" r="9" fill="' + dark + '"/>' :
      '<path d="M44 22 Q40 4 48 2 Q50 14 50 22 M56 22 Q60 4 52 2" stroke="hsl(' + ((h + 120) % 360) + ' 70% 60%)" ' +
      'stroke-width="4" fill="none"/>';
    var leaf = st >= 2 ? '<path d="M50 20 Q62 8 70 14 Q60 24 50 20Z" fill="#7cc67f"/>' : '';
    var spots = st >= 3 ? '<circle cx="34" cy="70" r="3" fill="' + belly + '"/><circle cx="66" cy="70" r="3" fill="' + belly +
      '"/>' : '';
    var glow = st >= 4 ? '<circle cx="50" cy="60" r="40" fill="none" stroke="#ffc857" stroke-opacity=".5" stroke-width="2"/>' :
      '';
    var eyes = 5 + (Number(c.eyes) || 0);
    var ry = 26 + (Number(c.round) || 0) * 4;
    // The coat it got in the forest: fur, feathers or scales.
    var behind = '';
    var over = '';
    if (c.coat === 'fur') {
      behind = [[22, 52], [24, 70], [32, 84], [68, 84], [76, 70], [78, 52], [36, 36], [50, 32], [64, 36]].map(function(p) {
        return '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="7" fill="' + dark + '"/>';
      }).join('');
    } else if (c.coat === 'feathers') {
      behind = '<path d="M22 58 Q4 50 8 72 Q16 70 24 66Z M78 58 Q96 50 92 72 Q84 70 76 66Z" fill="' + dark + '"/>';
      over = '<path d="M46 ' + (60 - ry + 2) + ' Q40 ' + (60 - ry - 14) + ' 46 ' + (60 - ry - 18) + ' Q50 ' + (60 - ry - 8) +
        ' 48 ' + (60 - ry + 2) + 'Z M52 ' + (60 - ry + 2) + ' Q58 ' + (60 - ry - 16) + ' 64 ' + (60 - ry - 14) + ' Q58 ' +
        (60 - ry - 4) + ' 54 ' + (60 - ry + 2) + 'Z" fill="hsl(' + ((h + 40) % 360) + ' 75% 60%)"/>';
    } else if (c.coat === 'scales') {
      behind = '<path d="M30 ' + (60 - ry + 8) + ' l4 -9 4 8 M40 ' + (60 - ry + 3) + ' l5 -10 5 9 M54 ' + (60 - ry + 3) +
        ' l5 -10 5 9 M64 ' + (60 - ry + 8) + ' l4 -9 4 8" fill="' + dark + '" stroke="' + dark + '" stroke-width="2"/>';
      over = [[36, 62], [44, 58], [56, 58], [64, 62], [40, 70], [60, 70]].map(function(p) {
        return '<path d="M' + (p[0] - 4) + ' ' + p[1] + ' q4 5 8 0" stroke="' + dark + '" stroke-width="1.6" fill="none"/>';
      }).join('');
    }
    return '<svg class="creature' + (big ? ' born' : '') + '" viewBox="0 0 100 100" role="img" aria-label="' +
      T.esc(c.name || '') + '"><g transform="translate(50 60) scale(' + s + ') translate(-50 -60)">' + glow + ears + leaf +
      behind + '<ellipse cx="50" cy="60" rx="30" ry="' + ry + '" fill="' + body + '"/>' + over + spots +
      '<ellipse cx="50" cy="70" rx="18" ry="12" fill="' + belly + '"/>' +
      '<circle cx="40" cy="52" r="' + eyes + '" fill="#fff"/><circle cx="60" cy="52" r="' + eyes + '" fill="#fff"/>' +
      '<circle cx="41" cy="53" r="' + (eyes / 2) + '" fill="#1b1b2a"/><circle cx="61" cy="53" r="' + (eyes / 2) +
      '" fill="#1b1b2a"/><path d="M45 64 Q50 68 55 64" stroke="#1b1b2a" stroke-width="2" fill="none"/></g></svg>';
  };

  /* ---------- Icons ---------- */

  T.icon = function(name) {
    var art = T.artUrl(['icon.' + name]);
    if (art) {
      return '<img class="icon art" src="' + T.esc(art) + '" alt="" aria-hidden="true">';
    }
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
      lantern: '<rect x="11" y="13" width="2" height="9" fill="#8a6a3a"/><path d="M8 6h8l-1 8H9z" fill="#ffc857"/>' +
        '<path d="M9 6l3-3 3 3" stroke="#8a6a3a" stroke-width="1.5" fill="none"/>',
      me: '<circle cx="12" cy="8" r="4" fill="currentColor"/><path d="M4 21c.7-5 4-7 8-7s7.3 2 8 7z" fill="currentColor"/>',
      class: '<rect x="3" y="4" width="18" height="12" rx="2" fill="currentColor" opacity=".9"/><path d="M8 20h8M12 16v4" ' +
        'stroke="currentColor" stroke-width="2"/>',
      power: '<path d="M13 2L5 13h6l-1 9 8-11h-6z" fill="#b9a6ff"/>',
      exit: '<path d="M10 4H5v16h5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/><path ' +
        'd="M14 8l4 4-4 4M18 12H9" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
      idea: '<path d="M9 18h6M10 21h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 3a6 6 0 0 ' +
        '0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z" fill="currentColor"/>',
      game: '<path d="M6 8h12a4 4 0 0 1 4 4v1a4 4 0 0 1-7 2.6h-6A4 4 0 0 1 2 13v-1a4 4 0 0 1 4-4z" fill="currentColor"/>' +
        '<path d="M7 11v3M5.5 12.5h3" stroke="#0f2131" stroke-width="1.6"/><circle cx="16" cy="11.5" r="1.2" fill="#0f2131"/>' +
        '<circle cx="18" cy="13.5" r="1.2" fill="#0f2131"/>',
      activity: '<rect x="4" y="3" width="16" height="18" rx="2" fill="currentColor"/><path d="M8 8h8M8 12h8M8 16h5" ' +
        'stroke="#0f2131" stroke-width="1.6"/>',
      settings: '<circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v3M12 ' +
        '19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1" stroke="currentColor" ' +
        'stroke-width="2" stroke-linecap="round"/>'
    }[name] || '';
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' + p + '</svg>';
  };

  /**
   * The Embrollo: a mischievous skein of yarn with eyes, not a monster. It shrinks as it loses life.
   *
   * @param {number} life From 0 to 1.
   * @return {string} SVG, or the image of the art pack.
   */
  T.embrollo = function(life) {
    var art = T.artUrl(['boss.embrollo']);
    var s = 0.45 + 0.55 * Math.max(0, Math.min(1, life));
    if (art) {
      return '<img class="embrollo art" src="' + T.esc(art) + '" alt="" style="transform:scale(' + s + ')">';
    }
    var loops = '';
    for (var i = 0; i < 9; i++) {
      loops += '<ellipse cx="100" cy="100" rx="' + (62 - i * 3) + '" ry="' + (30 + i * 4) + '" transform="rotate(' + (i * 41) +
        ' 100 100)" fill="none" stroke="' + (i % 2 ? '#8e7cc3' : '#b9a6ff') + '" stroke-width="5"/>';
    }
    return '<svg class="embrollo" viewBox="0 0 200 200" aria-hidden="true"><g transform="translate(100 100) scale(' + s +
      ') translate(-100 -100)"><circle cx="100" cy="100" r="64" fill="#5b4a8e"/>' + loops +
      '<path d="M150 140 Q178 160 168 186 Q160 196 176 198" fill="none" stroke="#b9a6ff" stroke-width="5" ' +
      'stroke-linecap="round"/><ellipse cx="82" cy="94" rx="13" ry="15" fill="#fff"/><ellipse cx="120" cy="94" rx="13" ' +
      'ry="15" fill="#fff"/><circle cx="86" cy="98" r="6" fill="#2a2219"/><circle cx="116" cy="98" r="6" fill="#2a2219"/>' +
      '<path d="M84 124 Q100 134 118 122" fill="none" stroke="#2a2219" stroke-width="4" stroke-linecap="round"/></g></svg>';
  };

  /**
   * A picture of the valley at dusk, for the story screens and the region card: the painting of the art pack, or one
   * drawn here.
   *
   * @param {string} kind prologue or region.
   * @return {string}
   */
  T.vignette = function(kind) {
    // One painting serves both, if the pack has only one.
    var art = T.artUrl(['scene.' + kind, 'scene.' + (kind === 'region' ? 'prologue' : 'region')]);
    if (art) {
      return '<div class="vignette art" aria-hidden="true"><img src="' + T.esc(art) + '" alt=""></div>';
    }
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
