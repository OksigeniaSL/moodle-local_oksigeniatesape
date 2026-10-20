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

  // The art comes light (the address of each pack once, and the rest of each image): its addresses, whole again.
  if (cfg.art && cfg.art.roots) {
    Object.keys(cfg.art.files).forEach(function(key) {
      var f = cfg.art.files[key];
      if (Array.isArray(f)) {
        cfg.art.files[key] = cfg.art.roots[f[0]] + (/[./]/.test(f[1]) ? f[1] : key.split('.')[0] + '/' + key + '.' + f[1]);
      }
    });
  }

  /*
   * A device may be shared (a tablet of the class, the computer of the teacher): what the game keeps on it of somebody
   * else goes when another one comes in. Only what still waits to be sent stays, so that nothing played is lost: the
   * plays of a student for a fortnight, and the marks of a teacher.
   */
  (function() {
    if (!cfg.userid) {
      return;
    }
    var old = Date.now() - 14 * 86400000;
    try {
      var store = window.localStorage;
      var keys = [];
      for (var i = 0; i < store.length; i++) {
        keys.push(store.key(i));
      }
      keys.forEach(function(key) {
        var who = /^tesape-(plays|scenes|seen|badge|mission|queue)-.*-(\d+)$/.exec(key || '');
        if (!who || who[2] === String(cfg.userid) || who[1] === 'queue') {
          return;
        }
        var fresh = who[1] === 'plays' ? (JSON.parse(store.getItem(key) || '[]') || []).filter(function(p) {
          return (p.at || 0) > old;
        }) : [];
        if (fresh.length) {
          store.setItem(key, JSON.stringify(fresh));
        } else {
          store.removeItem(key);
        }
      });
    } catch (e) {
      // Without storage, nothing kept.
    }
  })();

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
   * The theme of the page, night or day, or changes it. The page comes with the one of the class.
   *
   * @param {string} [name]
   * @return {string}
   */
  T.theme = function(name) {
    var root = document.documentElement;
    if (name === 'night' || name === 'day') {
      root.dataset.theme = name;
      var bar = document.querySelector('meta[name="theme-color"]');
      if (bar) {
        bar.content = getComputedStyle(root).getPropertyValue('--night').trim() || bar.content;
      }
    }
    return root.dataset.theme || 'night';
  };

  /* The stretches of road being drawn now, so that drawing the map again does not start them over. */
  var drawing = {};

  /**
   * The road walked since this device last saw a map: drawn as it is seen, once, from the place where it was left.
   * The first time a map is seen, nothing is drawn.
   *
   * @param {string} key The map and who looks at it.
   * @param {Object[]} chapters The places, in order, with x and y.
   * @param {number} upto The last place reached.
   * @return {{road: string, from: (Object|null), delay: number}} The stretch to draw, where it starts, and how far its
   *     drawing already went (a negative delay, in seconds).
   */
  T.mapNewRoad = function(key, chapters, upto) {
    var store = 'tesape-road-' + key;
    var now = Date.now();
    var d = drawing[store];
    if (d && now < d.until) {
      return {road: d.road, from: d.from, delay: (d.start - now) / 1000};
    }
    var seen = NaN;
    try {
      seen = parseInt(window.localStorage.getItem(store), 10);
      window.localStorage.setItem(store, String(upto));
    } catch (e) {
      // Without storage, nothing is drawn.
    }
    if (isNaN(seen) || upto <= seen || !chapters[seen]) {
      return {road: '', from: null, delay: 0};
    }
    var pts = chapters.slice(seen, upto + 1).map(function(ch) {
      return ch.x + ',' + ch.y;
    }).join(' ');
    d = {road: '<polyline points="' + pts + '" class="road drawing" pathLength="1"/>', from: chapters[seen], start: now,
      until: now + 3200};
    drawing[store] = d;
    if (T.fx) {
      T.fx.sound('path');
    }
    return {road: d.road, from: d.from, delay: 0};
  };

  /**
   * The lanterns of the student lit since this device last saw a map: they light in front of them, once. The first time
   * a map is seen, nothing lights.
   *
   * @param {string} key The map and who looks at it.
   * @param {Object[]} chapters The places, with id and mine (the lantern of the student is lit there).
   * @return {Object} Id of each place whose lantern lights now => true.
   */
  T.mapNewLanterns = function(key, chapters) {
    var store = 'tesape-lanterns-' + key;
    var now = Date.now();
    var d = drawing[store];
    if (d && now < d.until) {
      return d.ids;
    }
    var lit = chapters.filter(function(ch) {
      return ch.mine;
    }).map(function(ch) {
      return String(ch.id);
    });
    var seen = null;
    try {
      seen = JSON.parse(window.localStorage.getItem(store) || 'null');
      window.localStorage.setItem(store, JSON.stringify(lit));
    } catch (e) {
      // Without storage, nothing lights in front of them.
    }
    var ids = {};
    if (Array.isArray(seen)) {
      lit.forEach(function(id) {
        if (seen.indexOf(id) < 0) {
          ids[id] = true;
        }
      });
    }
    if (Object.keys(ids).length) {
      drawing[store] = {ids: ids, until: now + 3000};
      if (T.fx) {
        setTimeout(function() {
          T.fx.sound('lantern');
          T.fx.sparkles();
        }, 1000);
      }
    }
    return ids;
  };

  /**
   * Where the character was the last time this device saw the map, if it was another place: the character walks from
   * there. Nothing the first time.
   *
   * @param {string} key The map and who looks at it.
   * @param {Object[]} chapters The places, with id, x and y.
   * @param {Object} here The place where the character is now.
   * @return {{from: (Object|null), delay: number}} The place it comes from, and how far its walk already went.
   */
  /**
   * The photo of the classroom of a place, once the teacher approved it: a sticker beside its lantern, a little crooked,
   * on its left (on the right go the blots of the Embrollo and the teammates), unless the map ends there.
   *
   * @param {Object|null} photo status and url.
   * @param {number} x
   * @param {number} y Where the lantern is.
   * @param {number} i The place, to tilt each sticker its own way.
   * @param {number} [k] On the class screen, the photo of the place (one per team), a little further each one.
   * @return {string} SVG.
   */
  T.photoSticker = function(photo, x, y, i, k) {
    if (!photo || photo.status !== 'approved' || !photo.url) {
      return '';
    }
    k = k || 0;
    var left = x < 100 ? x + 34 + k * 16 : x - 88 - k * 16;
    return '<g class="photo-sticker" aria-hidden="true" transform="translate(' + left + ' ' + Math.max(4, y - 44 + k * 6) +
      ') rotate(' + ((i + k) % 2 ? 6 : -5) + ' 26 29)"><rect width="52" height="58" rx="3"/><image href="' +
      T.esc(photo.url) + '" x="5" y="5" width="42" height="42" preserveAspectRatio="xMidYMid slice"/></g>';
  };

  /**
   * The name of a place under its lantern on a map: in two lines when it is long, and never out of the map.
   *
   * @param {number} x
   * @param {number} y Where the text starts.
   * @param {string} name
   * @return {string} SVG.
   */
  T.mapLabel = function(x, y, name) {
    var lines = [name];
    if (name.length > 18) {
      var middle = Math.floor(name.length / 2);
      var cut = -1;
      for (var d = 0; d < middle; d++) {
        if (name.charAt(middle - d) === ' ') {
          cut = middle - d;
          break;
        }
        if (name.charAt(middle + d) === ' ') {
          cut = middle + d;
          break;
        }
      }
      if (cut > 0) {
        // The space stays at the end of the first line, so that the name reads whole.
        lines = [name.slice(0, cut + 1), name.slice(cut + 1)];
      }
    }
    // About eight pixels a letter, at the size of the names: kept inside the 400 of the map.
    var half = Math.max.apply(null, lines.map(function(l) {
      return l.length;
    })) * 4.2;
    var at = Math.round(Math.max(half + 4, Math.min(396 - half, x)));
    return '<text x="' + at + '" y="' + y + '" text-anchor="middle">' + lines.map(function(l, i) {
      return '<tspan x="' + at + '" dy="' + (i ? '1.1em' : '0') + '">' + T.esc(l) + '</tspan>';
    }).join('') + '</text>';
  };

  /**
   * The icon of the pause of a game: two bars, or the triangle to go on.
   *
   * @param {boolean} paused
   * @return {string} SVG.
   */
  T.pauseIcon = function(paused) {
    return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">' + (paused ?
      '<path d="M7 4l13 8-13 8z" fill="currentColor"/>' :
      '<rect x="6" y="4" width="4.5" height="16" rx="1" fill="currentColor"/><rect x="13.5" y="4" width="4.5" height="16" ' +
      'rx="1" fill="currentColor"/>') + '</svg>';
  };

  /**
   * A date in the language of the site, also when Moodle's name for it is not one the browser knows (en_kids).
   *
   * @param {Date} date
   * @param {Object} opts As for Intl.DateTimeFormat.
   * @return {string}
   */
  T.date = function(date, opts) {
    var langs = [cfg.lang, String(cfg.lang || '').slice(0, 2), undefined];
    for (var i = 0; i < langs.length; i++) {
      try {
        return new Intl.DateTimeFormat(langs[i], opts).format(date);
      } catch (e) {
        // The next one.
      }
    }
    return date.toLocaleString();
  };

  T.mapWalk = function(key, chapters, here) {
    var store = 'tesape-here-' + key;
    var now = Date.now();
    var d = drawing[store];
    if (d && now < d.until && d.to === here.id) {
      return {from: d.from, delay: (d.start - now) / 1000};
    }
    var before = null;
    try {
      before = window.localStorage.getItem(store);
      window.localStorage.setItem(store, String(here.id));
    } catch (e) {
      // Without storage, the character just stands there.
    }
    var from = chapters.filter(function(ch) {
      return before !== null && String(ch.id) === before && ch.id !== here.id;
    })[0] || null;
    if (from) {
      drawing[store] = {from: from, to: here.id, start: now, until: now + 2400};
    }
    return {from: from, delay: 0};
  };

  /**
   * What the game adapts for this student, as their teacher marked: each one a class on the page (big text, high
   * contrast, colours…) and a flag for the scripts (no hurry, no movement, reading aloud…).
   *
   * @param {string[]} list
   */
  T.adapted = {};
  T.adapt = function(list) {
    T.adapted = {};
    (list || []).forEach(function(code) {
      T.adapted[code] = true;
    });
    var body = document.body;
    body.className = body.className.split(' ').filter(function(c) {
      return c.indexOf('adapt-') !== 0;
    }).concat((list || []).map(function(code) {
      return 'adapt-' + code;
    })).join(' ');
    if (T.fx && T.adapted.still) {
      T.fx.still();
    }
  };

  /*
   * The light of a screen of the teacher (the panel and the class screen): each classroom and each projector is
   * different, so it changes on this device only and the device remembers it; back to the theme of the class, it
   * forgets it. The students follow the theme of the class.
   */
  var LIGHT = 'tesape-screen-theme';
  T.light = {
    /** The theme this device remembers, over the one of the class. */
    restore: function() {
      try {
        var stored = window.localStorage.getItem(LIGHT);
        if (stored) {
          T.theme(stored);
        }
      } catch (e) {
        // Without storage, the theme of the class.
      }
    },
    /** From night to day or back, on this device. */
    toggle: function() {
      var next = T.theme() === 'day' ? 'night' : 'day';
      T.theme(next);
      try {
        if (T.cfg.theme && next === T.cfg.theme.current) {
          window.localStorage.removeItem(LIGHT);
        } else {
          window.localStorage.setItem(LIGHT, next);
        }
      } catch (e) {
        // Only for this page.
      }
    },
    /**
     * The class has a new theme: the device follows it again.
     *
     * @param {string} theme
     */
    follow: function(theme) {
      if (T.cfg.theme) {
        T.cfg.theme.current = theme;
      }
      try {
        window.localStorage.removeItem(LIGHT);
      } catch (e) {
        // Nothing remembered.
      }
      T.theme(theme);
    }
  };

  /** The calls that may take long on the server (a mission installed or drafted, the next class drafted): no limit. */
  var SLOW_CALLS = ['install_mission', 'author_mission', 'next_class', 'make_teams'];

  /**
   * Calls a web service of the plugin. With a weak wifi a call can hang for minutes: after twenty seconds it is given
   * up as without connection, so that what waits for it (a play, a minigame) is kept on the device and goes later.
   *
   * @param {string} name Without the component prefix.
   * @param {Object} args
   * @return {Promise}
   */
  T.api = function(name, args) {
    var url = cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + encodeURIComponent(cfg.sesskey) +
      '&info=local_oksigeniatesape_' + name;
    // Errors say whether the server answered (errorcode) or did not (network), so that queues know what to retry.
    var offline = function(status) {
      var err = new Error(T.t('app_error'));
      err.network = true;
      err.status = status || 0;
      return err;
    };
    var abort = window.AbortController && SLOW_CALLS.indexOf(name) < 0 ? new window.AbortController() : null;
    var timer = abort ? setTimeout(function() {
      abort.abort();
    }, 20000) : 0;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify([{index: 0, methodname: 'local_oksigeniatesape_' + name, args: args || {}}]),
      signal: abort ? abort.signal : undefined
    }).catch(function() {
      throw offline();
    }).then(function(response) {
      clearTimeout(timer);
      if (!response.ok) {
        throw offline(response.status);
      }
      return response.json().catch(function() {
        throw offline();
      });
    }, function(e) {
      clearTimeout(timer);
      throw e;
    }).then(function(data) {
      var result = Array.isArray(data) ? data[0] : data;
      if (!result || result.error) {
        var e = (result && (result.exception || result)) || {};
        var err = new Error(e.message || T.t('app_error'));
        err.errorcode = e.errorcode || 'error';
        if (e.errorcode === 'servicerequireslogin' || e.errorcode === 'invalidsesskey') {
          // The session ended, or another student came in on this device: what was being sent waits, and the page
          // starts again. Not in the middle of a scene or a game: the page starts again when it is closed.
          err.retry = true;
          var open = document.querySelector('.scene-layer');
          if (!open) {
            window.location.reload();
          } else if (!T.reloading) {
            T.reloading = true;
            T.toast(T.esc(T.t('app_session_ended')), true);
            new MutationObserver(function(changes, watch) {
              if (!document.querySelector('.scene-layer')) {
                watch.disconnect();
                window.location.reload();
              }
            }).observe(document.body, {childList: true});
          }
        }
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
  /**
   * Whether things move: not with «no movement», nor when the system asks for less motion.
   *
   * @return {boolean}
   */
  T.moves = function() {
    return !T.adapted.still && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  };

  /**
   * What was won flies from the middle of the screen to its counter in the header, which beats and counts up. Nothing
   * flies without movement: the counter just shows what there is now.
   *
   * @param {Element} counter The button of the counter, with its number in a <b>.
   * @param {string} icon
   * @param {number} from
   * @param {number} to
   */
  T.loot = function(counter, icon, from, to) {
    var num = counter.querySelector('b');
    var count = function() {
      counter.classList.remove('beat');
      void counter.offsetWidth;
      counter.classList.add('beat');
      if (T.fx) {
        T.fx.sound('coin');
      }
      if (!T.moves()) {
        num.textContent = to;
        return;
      }
      var start = null;
      var step = function(time) {
        start = start || time;
        var k = Math.min(1, (time - start) / 600);
        num.textContent = Math.round(from + (to - from) * k);
        if (k < 1) {
          window.requestAnimationFrame(step);
        }
      };
      window.requestAnimationFrame(step);
    };
    if (!T.moves() || !counter.animate) {
      count();
      return;
    }
    num.textContent = from;
    var chip = document.createElement('div');
    chip.className = 'loot-fly';
    chip.setAttribute('aria-hidden', 'true');
    chip.innerHTML = T.icon(icon) + '<b>+' + (to - from) + '</b>';
    document.body.appendChild(chip);
    var r = counter.getBoundingClientRect();
    var c = chip.getBoundingClientRect();
    var dx = Math.round(r.left + r.width / 2 - (c.left + c.width / 2));
    var dy = Math.round(r.top + r.height / 2 - (c.top + c.height / 2));
    chip.animate([
      {transform: 'translate(-50%, -50%) scale(.6)', opacity: 0},
      {transform: 'translate(-50%, -50%) scale(1.2)', opacity: 1, offset: 0.3},
      {transform: 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + dy + 'px)) scale(.45)', opacity: 0.9}
    ], {duration: 950, easing: 'cubic-bezier(.5, 0, .3, 1)'}).onfinish = function() {
      chip.remove();
      count();
    };
  };

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

  /**
   * Which of some keys the art pack of the site has first, from the most precise to the most general; or null.
   *
   * @param {string[]} keys
   * @return {string|null}
   */
  T.artKey = function(keys) {
    var files = cfg.art && cfg.art.files;
    for (var i = 0; files && i < keys.length; i++) {
      if (files[keys[i]]) {
        return keys[i];
      }
    }
    return null;
  };

  /**
   * The key of the recorded voice of a piece of text (voice.line.<FNV-1a of its UTF-8 bytes, in hex>): it follows the
   * words, so a text that changes (another variant, the name of a creature) simply has no voice, never another one.
   *
   * @param {string} text
   * @return {string}
   */
  T.lineKey = function(text) {
    var bytes = window.TextEncoder ? new window.TextEncoder().encode(String(text)) : [];
    var h = 0x811c9dc5;
    for (var i = 0; i < bytes.length; i++) {
      h ^= bytes[i];
      h = Math.imul(h, 0x01000193) >>> 0;
    }
    return 'voice.line.' + ('0000000' + h.toString(16)).slice(-8);
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

  /**
   * The night over the valley, lighter with each lantern the class lights: dark at the beginning, clear at the end.
   *
   * @param {Object} m
   * @return {string}
   */
  T.mapNight = function(m) {
    var lit = m.chapters.filter(function(ch) {
      return ch.lit;
    }).length;
    var dark = 0.55 * (1 - lit / Math.max(1, m.chapters.length));
    return dark > 0.01 ? '<rect class="night" width="400" height="' + T.mapHeight(m) + '" fill="#081322" opacity="' +
      dark.toFixed(2) + '"/>' : '';
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

  /** The images of the creatures with their mark drawn in, by image and mark: addresses of the device, or who waits. */
  var MARKED = {};

  /**
   * The mark of a creature with the art of a pack, its own among those of the class: a small sign on its belly, its shape
   * from its ears, how many from its eyes and how they sit from its roundness. Drawn in blue on the orange of the
   * painting: the colour of each creature turns both, so the mark always has the colour opposite to its own.
   *
   * @param {CanvasRenderingContext2D} g
   * @param {string} key ears, eyes, round and stage.
   * @param {number} k Pixels for a hundredth of the image.
   */
  function drawMark(g, key, k) {
    var v = key.split(',').map(Number);
    var ears = v[0] || 0;
    var n = (v[1] || 0) + 1;
    var round = v[2] || 0;
    // Where the belly is in the paintings: the first stage sits, the others stand.
    var cx = v[3] === 1 ? 40 : 50;
    var cy = v[3] === 1 ? 62 : 68;
    var r = 3.1 + round * 0.6;
    g.fillStyle = 'hsl(205 65% 40%)';
    g.strokeStyle = '#fdfaf2';
    g.lineWidth = 0.7 * k;
    for (var i = 0; i < n; i++) {
      var d = (i - (n - 1) / 2) * (r * 2.1);
      // In a row, in an arc, or one above the other.
      var x = (round === 2 ? cx : cx + d) * k;
      var y = (round === 2 ? cy + d : round === 1 ? cy - Math.abs(d) * 0.5 : cy) * k;
      var s = r * k;
      g.beginPath();
      if (ears === 0) {
        // A leaf.
        g.moveTo(x, y - s);
        g.quadraticCurveTo(x + s, y, x, y + s);
        g.quadraticCurveTo(x - s, y, x, y - s);
      } else if (ears === 1) {
        // A spot.
        g.arc(x, y, s * 0.75, 0, Math.PI * 2);
      } else {
        // A spark.
        var q = s * 0.32;
        [[0, -s], [q, -q], [s, 0], [q, q], [0, s], [-q, q], [-s, 0], [-q, -q]].forEach(function(p, j) {
          g[j ? 'lineTo' : 'moveTo'](x + p[0], y + p[1]);
        });
      }
      g.closePath();
      g.fill();
      g.stroke();
    }
  }

  /**
   * Draws its mark into the image of a creature, once for each image and mark: the element stays the same image, so
   * every place that shows a creature keeps its size and its colour.
   *
   * @param {HTMLImageElement} img With data-mark.
   */
  function markCreature(img) {
    var key = img.getAttribute('data-mark');
    var art = img.getAttribute('src');
    img.removeAttribute('data-mark');
    var id = art + '|' + key;
    if (typeof MARKED[id] === 'string') {
      img.src = MARKED[id];
      return;
    }
    if (MARKED[id]) {
      MARKED[id].push(img);
      return;
    }
    MARKED[id] = [img];
    var done = function(url) {
      var waiting = MARKED[id];
      MARKED[id] = url;
      if (url !== art) {
        waiting.forEach(function(el) {
          el.src = url;
        });
      }
    };
    var pic = new Image();
    pic.onload = function() {
      try {
        var c = document.createElement('canvas');
        c.width = pic.naturalWidth;
        c.height = pic.naturalHeight;
        var g = c.getContext('2d');
        g.drawImage(pic, 0, 0);
        drawMark(g, key, c.width / 100);
        c.toBlob(function(blob) {
          done(blob ? URL.createObjectURL(blob) : art);
        }, 'image/png');
      } catch (e) {
        // Without a canvas, the creature without its mark.
        done(art);
      }
    };
    pic.onerror = function() {
      done(art);
    };
    pic.src = art;
  }

  // The images of creatures that come into the page, with their mark.
  if (window.MutationObserver && window.HTMLCanvasElement) {
    new MutationObserver(function(list) {
      list.forEach(function(m) {
        m.addedNodes.forEach(function(node) {
          if (node.nodeType !== 1) {
            return;
          }
          if (node.matches('img[data-mark]')) {
            markCreature(node);
          }
          node.querySelectorAll('img[data-mark]').forEach(markCreature);
        });
      });
    }).observe(document.documentElement, {childList: true, subtree: true});
  }

  /**
   * A name at the start of a sentence: «la Piedra que rueda» becomes «La Piedra que rueda».
   *
   * @param {string} text
   * @return {string}
   */
  T.capital = function(text) {
    text = String(text || '');
    return text.charAt(0).toUpperCase() + text.slice(1);
  };

  /**
   * The mark of a creature told in words, for its sheet: «two leaves in a row on its belly».
   *
   * @param {Object|string} traits
   * @return {string} Empty for a creature without the art of a pack (its drawing is its own already).
   */
  T.creatureMark = function(traits) {
    var c = traits;
    if (typeof c === 'string') {
      try {
        c = JSON.parse(c || 'null');
      } catch (e) {
        c = null;
      }
    }
    if (!c || !T.artUrl(['creature.1', 'creature'])) {
      return '';
    }
    var shape = ['leaf', 'spot', 'spark'][Number(c.ears) || 0];
    var n = (Number(c.eyes) || 0) + 1;
    var round = Number(c.round) || 0;
    return n === 1 ? T.t('app_mark_one', {shape: T.t('app_mark_' + shape), size: T.t('app_mark_size_' + round)}) :
      T.t('app_mark_many', {count: T.t('app_mark_count_' + n), shapes: T.t('app_mark_' + shape + 's'),
        way: T.t('app_mark_way_' + round)});
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
    // Up to five stages; a pack that paints four shows the fourth for the fifth.
    var st = Math.max(1, Math.min(5, stage || 1));
    var low = Math.min(4, st);
    var art = T.artUrl(['creature.' + st + '.' + (c.coat || 'none'), 'creature.' + st, 'creature.' + low + '.' +
      (c.coat || 'none'), 'creature.' + low, 'creature']);
    if (art) {
      // One image for every creature: its own hue, or the paint bought in the market; and its own mark on the belly,
      // drawn into the image on the device (at once, when this device already drew it).
      var mark = [Number(c.ears) || 0, Number(c.eyes) || 0, Number(c.round) || 0, low].join(',');
      var marked = MARKED[art + '|' + mark];
      return '<img class="creature art' + (big ? ' born' : '') + '" src="' + T.esc(typeof marked === 'string' ? marked :
        art) + '"' + (typeof marked === 'string' ? '' : ' data-mark="' + mark + '"') + ' alt="' + T.esc(c.name || '') +
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
      shield: '<path d="M12 2l8 3v6c0 5-3.4 9.3-8 11-4.6-1.7-8-6-8-11V5z" fill="currentColor" opacity=".9"/><path d="M12 6v12" ' +
        'stroke="#0f2131" stroke-width="1.6" opacity=".5"/>',
      cheer: '<path d="M12 21s-7-4.6-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.4-7 10-7 10z" fill="currentColor"/><path ' +
        'd="M12 9v6M9 12h6" stroke="#0f2131" stroke-width="2" stroke-linecap="round"/>',
      trail: '<ellipse cx="7" cy="16" rx="2.6" ry="3.6" fill="currentColor"/><ellipse cx="16" cy="8" rx="2.6" ry="3.6" ' +
        'fill="currentColor"/><circle cx="5" cy="11" r="1.1" fill="currentColor"/><circle cx="9" cy="10.6" r="1.1" ' +
        'fill="currentColor"/><circle cx="14" cy="3.4" r="1.1" fill="currentColor"/><circle cx="18" cy="3" r="1.1" ' +
        'fill="currentColor"/>',
      net: '<path d="M3 21l7-7" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/><circle cx="15" cy="9" r="6.5" ' +
        'fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M10.5 5.5l9 7M9.5 10l9-5.5M12 15l6-11M15 2.5v13" ' +
        'stroke="currentColor" stroke-width="1" opacity=".7"/>',
      close: '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>',
      pause: '<path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z" fill="currentColor"/>',
      sound: '<path d="M4 9h4l5-4v14l-5-4H4z" fill="currentColor"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12" ' +
        'stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
      mute: '<path d="M4 9h4l5-4v14l-5-4H4z" fill="currentColor"/><path d="M16.5 9.5l5 5M21.5 9.5l-5 5" stroke="currentColor" ' +
        'stroke-width="2" stroke-linecap="round"/>',
      speak: '<path d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-8l-5 4v-4H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" fill="currentColor"/>' +
        '<path d="M7 9h10M7 12.5h7" stroke="#0f2131" stroke-width="1.8" stroke-linecap="round"/>',
      camera: '<path d="M4 8h3.2l1.6-2.4h6.4L16.8 8H20a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z" ' +
        'fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="13.5" r="3.4" ' +
        'fill="none" stroke="currentColor" stroke-width="2"/>',
      lens: '<circle cx="10" cy="10" r="6" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M14.5 14.5 L21 21" ' +
        'stroke="currentColor" stroke-width="3" stroke-linecap="round"/><circle cx="10" cy="10" r="3.2" fill="currentColor" ' +
        'opacity=".25"/>',
      idea: '<path d="M9 18h6M10 21h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 3a6 6 0 0 ' +
        '0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z" fill="currentColor"/>',
      game: '<path d="M6 8h12a4 4 0 0 1 4 4v1a4 4 0 0 1-7 2.6h-6A4 4 0 0 1 2 13v-1a4 4 0 0 1 4-4z" fill="currentColor"/>' +
        '<path d="M7 11v3M5.5 12.5h3" stroke="#0f2131" stroke-width="1.6"/><circle cx="16" cy="11.5" r="1.2" fill="#0f2131"/>' +
        '<circle cx="18" cy="13.5" r="1.2" fill="#0f2131"/>',
      activity: '<rect x="4" y="3" width="16" height="18" rx="2" fill="currentColor"/><path d="M8 8h8M8 12h8M8 16h5" ' +
        'stroke="#0f2131" stroke-width="1.6"/>',
      settings: '<path d="M19.3 9.8L21.9 10.3V13.7L19.3 14.2L18.7 15.5L20.2 17.8L17.8 20.2L15.5 18.7L14.2 19.3L13.7 ' +
        '21.9H10.3L9.8 19.3L8.5 18.7L6.2 20.2L3.8 17.8L5.3 15.5L4.7 14.2L2.1 13.7V10.3L4.7 9.8L5.3 8.5L3.8 6.2L6.2 3.8L8.5 ' +
        '5.3L9.8 4.7L10.3 2.1H13.7L14.2 4.7L15.5 5.3L17.8 3.8L20.2 6.2L18.7 8.5Z" fill="none" stroke="currentColor" ' +
        'stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" ' +
        'stroke-width="1.8"/>',
      sun: '<circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v2.5M12 ' +
        '19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8" ' +
        'stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
      moon: '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5Z" fill="none" stroke="currentColor" ' +
        'stroke-width="2" stroke-linejoin="round"/>'
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
    // Its state by the threads it has left, if the art pack paints them: whole, frayed, down to its last thread.
    var phase = life > 0.6 ? 1 : life > 0.25 ? 2 : 3;
    var art = T.artUrl(['boss.embrollo.' + phase, 'boss.embrollo']);
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
   * The banner of a team, as it has grown with the weeks it reached its goal: the cloth, its border, the emblem of the
   * light, the ribbons and the star on top of the pole. The cloth takes a colour of its own for each team.
   *
   * @param {Object} banner stage (1 to 5), as the server sends it.
   * @param {number} teamid
   * @return {string} SVG.
   */
  T.banner = function(banner, teamid) {
    var stage = (banner && banner.stage) || 1;
    var cloth = ['#c0504d', '#3f7fbf', '#4f9a5a', '#8e5bb5', '#d08a2e', '#2d9a9a'][Math.abs(teamid || 0) % 6];
    var shape = 'M10 10 H42 V46 L26 38 L10 46 Z';
    var svg = '<svg class="team-flag stage-' + stage + '" viewBox="0 0 48 64" aria-hidden="true">' +
      '<path d="M8 6 V62" stroke="#7a5a3a" stroke-width="3" stroke-linecap="round"/>' +
      '<path d="' + shape + '" fill="' + cloth + '"/>' +
      '<path d="M10 10 H42 V24 L10 24 Z" fill="#fff" opacity=".12"/>';
    if (stage >= 2) {
      svg += '<path d="' + shape + '" fill="none" stroke="#f2c14e" stroke-width="2.4" stroke-linejoin="round"/>';
    }
    if (stage >= 3) {
      // The emblem: a small sun, the light of Tesapé.
      var rays = '';
      for (var i = 0; i < 8; i++) {
        rays += '<path d="M26 16.5 V14" transform="rotate(' + (i * 45) + ' 26 24)" stroke="#fff3c4" stroke-width="1.8" ' +
          'stroke-linecap="round"/>';
      }
      svg += rays + '<circle cx="26" cy="24" r="5.2" fill="#ffd866" stroke="#fff3c4" stroke-width="1"/>';
    }
    if (stage >= 4) {
      svg += '<path d="M8 12 Q2 22 5 34" fill="none" stroke="#f2c14e" stroke-width="2.4" stroke-linecap="round"/>' +
        '<path d="M8 12 Q4 26 9 40" fill="none" stroke="#fff3c4" stroke-width="2" stroke-linecap="round"/>';
    }
    if (stage >= 5) {
      svg += '<path d="M8 0.5 L9.6 4 L13.3 4.3 L10.5 6.7 L11.4 10.4 L8 8.4 L4.6 10.4 L5.5 6.7 L2.7 4.3 L6.4 4 Z" ' +
        'fill="#ffd866" stroke="#b8860b" stroke-width=".8" stroke-linejoin="round"/>';
    } else {
      svg += '<circle cx="8" cy="5.5" r="2.4" fill="#7a5a3a"/>';
    }
    return svg + '</svg>';
  };

  /**
   * The picture of a group of living things in the art pack, by its name without accents (Mamífero: album.mamifero).
   *
   * @param {string} group
   * @return {string|null}
   */
  T.groupArt = function(group) {
    return group ? T.artUrl(['album.' + String(group).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')]) : null;
  };

  /**
   * The shelves of the museum of the class: a shelf for each group, a book for each entry of the field guide, with a
   * light of its own when it has three or more. A book is a button that opens its entry under its shelf.
   *
   * @param {Object} m The museum, as the server sends it.
   * @param {boolean} [full] Also the empty shelves (the tablet); the class screen shows only those with books.
   * @return {string}
   */
  /**
   * Where the shelves of the field guide are in the painting of the room of the museum, in hundredths of the painting
   * (left, top, width, height): the three empty cabinets with their brass plates, from the top, left to right.
   */
  var MUSEUM_SPOTS = [[49, 41.8, 9.5, 6.2], [49, 49, 9.5, 6.5], [49, 56.5, 9.5, 6.5], [62.5, 41, 10, 6.5],
    [62.5, 49, 10, 6.8], [62.5, 57, 10, 6.8], [76, 39, 10.5, 7.2], [76, 47.8, 10.5, 7.8]];

  T.museum = function(m, full) {
    var shelves = m.shelves.map(function(shelf, s) {
      return {shelf: shelf, s: s};
    }).filter(function(x) {
      return full || x.shelf.books.length;
    });
    if (!m.entries) {
      return '<p class="empty">' + T.esc(T.t('app_museum_empty')) + '</p>';
    }
    // Dark enough for the light letters of the spine.
    var colours = ['#7a3e33', '#335a77', '#4a6630', '#64477a', '#80552a', '#2c625c', '#76334f'];
    // The room of the museum, when the art pack brings it: its empty shelves fill up with the books of the class.
    var room = T.artUrl(['scene.museo']);
    var spines = function(shelf) {
      return shelf.books.slice(0, 7).map(function(b) {
        var h = 0;
        for (var k = 0; k < b.name.length; k++) {
          h = (h * 31 + b.name.charCodeAt(k)) % 997;
        }
        return '<i style="height:' + (58 + h % 34) + '%;background:' + colours[h % colours.length] + '"></i>';
      }).join('');
    };
    var spots = room ? '<div class="museum-room"><img src="' + T.esc(room) + '" alt="">' + m.shelves.slice(0,
      MUSEUM_SPOTS.length).map(function(shelf, s) {
        var at = MUSEUM_SPOTS[s];
        var name = shelf.group || T.t('app_museum_nogroup');
        return '<button type="button" class="museum-spot' + (shelf.lit ? ' lit' : '') + '" data-spot="' + s + '" style="left:' +
          at[0] + '%;top:' + at[1] + '%;width:' + at[2] + '%;height:' + at[3] + '%" aria-label="' + T.esc(name + ': ' +
          T.t('app_museum_books', shelf.books.length)) + '"' + (full || shelf.books.length ? '' : ' disabled') + '><span>' +
          spines(shelf) + '</span></button>';
      }).join('') + '</div>' : '';
    return spots + '<div class="museum">' + shelves.map(function(x) {
      var shelf = x.shelf;
      var art = T.groupArt(shelf.group);
      var name = shelf.group || T.t('app_museum_nogroup');
      return '<div class="museum-shelf' + (shelf.lit ? ' lit' : '') + (shelf.books.length ? '' : ' empty') + '" data-shelf="' +
        x.s + '"><div class="museum-label">' + (art ? '<img src="' +
        T.esc(art) + '" alt="">' : '') + '<b>' + T.esc(name) + '</b><small>' + T.esc(T.t('app_museum_books',
        shelf.books.length)) + '</small>' + (shelf.lit ? '<span class="museum-light" title="' + T.esc(T.t('app_museum_lit')) +
        '">' + T.icon('lantern') + '<span class="sr-only">' + T.esc(T.t('app_museum_lit')) + '</span></span>' : '') +
        '</div><div class="museum-books">' + shelf.books.map(function(b, i) {
          // Each book its own height and colour, the same every time: from its name.
          var h = 0;
          for (var k = 0; k < b.name.length; k++) {
            h = (h * 31 + b.name.charCodeAt(k)) % 997;
          }
          return '<button type="button" class="museum-book' + (b.golden ? ' golden' : '') + '" data-book="' + x.s + ':' + i +
            '" style="height:' + (70 + h % 24) + 'px;background:' + colours[h % colours.length] + '" aria-label="' +
            T.esc(b.name) + '"><span aria-hidden="true">' + T.esc(b.name) + '</span></button>';
        }).join('') + '</div></div>';
    }).join('') + '</div><div class="museum-read" aria-live="polite"><p class="help-soft">' +
      T.esc(T.t('app_museum_pick')) + '</p></div>';
  };

  /**
   * Opens the entry of each book of the museum under its shelf. The page is drawn again now and then: the book that
   * was open opens again, without a sound.
   *
   * @param {Element} root
   * @param {Object} m The museum.
   * @param {string} [opened] The book open before (shelf:book).
   * @param {Function} [onopen] Told the book opened.
   */
  T.bindMuseum = function(root, m, opened, onopen) {
    var read = root.querySelector('.museum-read');
    // A shelf of the painting takes to its shelf of the list, and to its first book.
    root.querySelectorAll('[data-spot]').forEach(function(spot) {
      spot.onclick = function() {
        var shelf = root.querySelector('.museum-shelf[data-shelf="' + spot.dataset.spot + '"]');
        if (!shelf) {
          return;
        }
        shelf.scrollIntoView({behavior: T.moves() ? 'smooth' : 'auto', block: 'center'});
        shelf.classList.remove('called');
        void shelf.offsetWidth;
        shelf.classList.add('called');
        var first = shelf.querySelector('[data-book]');
        if (first) {
          first.focus({preventScroll: true});
        }
      };
    });
    var show = function(btn, quiet) {
      var at = btn.dataset.book.split(':');
      var shelf = m.shelves[Number(at[0])];
      var b = shelf && shelf.books[Number(at[1])];
      if (!b) {
        return;
      }
      root.querySelectorAll('[data-book]').forEach(function(o) {
        o.setAttribute('aria-pressed', String(o === btn));
      });
      read.innerHTML = '<h3>' + T.esc(b.name) + '</h3><p class="museum-group"><small>' +
        T.esc(shelf.group || T.t('app_museum_nogroup')) + (b.chain ? ' · ' + T.esc(T.t('app_museum_chain', b.chain)) : '') +
        '</small></p>' + (b.looks ? '<p>' + T.esc(b.looks) + '</p>' : '') + (b.lives ? '<p><small>' +
        T.esc(T.t('app_museum_lives', b.lives)) + '</small></p>' : '');
      // Under the shelf of the book, where the eyes are.
      btn.closest('.museum-shelf').after(read);
      if (!quiet) {
        read.scrollIntoView({behavior: T.moves() ? 'smooth' : 'auto', block: 'nearest'});
        if (T.fx && !T.fx.sound('card')) {
          T.fx.sound('tap');
        }
      }
    };
    root.querySelectorAll('[data-book]').forEach(function(btn) {
      btn.setAttribute('aria-pressed', 'false');
      btn.onclick = function() {
        show(btn);
        if (onopen) {
          onopen(btn.dataset.book);
        }
      };
    });
    var was = opened && root.querySelector('[data-book="' + opened + '"]');
    if (was) {
      show(was, true);
    }
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

  // When a scene or a minigame closes, the focus goes back to what opened it, as a dialog does. The page under it is
  // often drawn again meanwhile: then, to the button of the same scene or game, or else to the content.
  var opener = null;
  // What was last touched or focused outside a scene: by the time a scene is on the page, the focus is inside it.
  var lastOutside = null;
  var remember = function(ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('button, a, [tabindex], [role="button"]') : null;
    if (el && !el.closest('.scene-layer')) {
      lastOutside = el;
    }
  };
  document.addEventListener('focusin', remember, true);
  document.addEventListener('pointerdown', remember, true);
  // A ripple from where an option, a group or a thing is touched.
  document.addEventListener('pointerdown', function(ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('.opt, .bin, .cls-thing, .role-verb, .look-tool') : null;
    if (!el || el.disabled || !T.moves()) {
      return;
    }
    var r = el.getBoundingClientRect();
    var size = Math.ceil(Math.max(r.width, r.height) * 2);
    var dot = document.createElement('span');
    dot.className = 'ripple';
    dot.setAttribute('aria-hidden', 'true');
    dot.style.width = size + 'px';
    dot.style.height = size + 'px';
    dot.style.left = Math.round(ev.clientX - r.left - size / 2) + 'px';
    dot.style.top = Math.round(ev.clientY - r.top - size / 2) + 'px';
    el.appendChild(dot);
    setTimeout(function() {
      dot.remove();
    }, 600);
  }, true);
  // Where the last touch was: a scene opens from there as the light of a lantern.
  var touched = null;
  document.addEventListener('pointerdown', function(ev) {
    touched = {x: ev.clientX, y: ev.clientY, at: Date.now()};
  }, true);
  var iris = function(layer, from) {
    if (!T.moves() || !layer.animate) {
      return;
    }
    var x = window.innerWidth / 2;
    var y = window.innerHeight / 2;
    if (touched && Date.now() - touched.at < 2000) {
      x = touched.x;
      y = touched.y;
    } else if (from && from.getBoundingClientRect) {
      var r = from.getBoundingClientRect();
      x = r.left + r.width / 2;
      y = r.top + r.height / 2;
    }
    var far = Math.ceil(Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y)));
    layer.animate([{clipPath: 'circle(20px at ' + x + 'px ' + y + 'px)'}, {clipPath: 'circle(' + far + 'px at ' + x + 'px ' +
      y + 'px)'}], {duration: 480, easing: 'cubic-bezier(.3, 0, .2, 1)'});
  };
  new MutationObserver(function(changes) {
    changes.forEach(function(change) {
      Array.prototype.forEach.call(change.addedNodes, function(node) {
        if (node.classList && node.classList.contains('scene-layer') && !opener) {
          var from = lastOutside;
          iris(node, from);
          var keys = from && from.attributes ? Array.prototype.filter.call(from.attributes, function(a) {
            return /^data-(scene|game|playscene|teamscene|visit|welcomescene)$/.test(a.name);
          }) : [];
          opener = {el: from, selector: keys.length ? '[' + keys[0].name + '="' + keys[0].value + '"]' : ''};
        }
      });
      Array.prototype.forEach.call(change.removedNodes, function(node) {
        if (node.classList && node.classList.contains('scene-layer') && opener &&
            !document.querySelector('.scene-layer')) {
          var back = opener;
          opener = null;
          // The world is often drawn again after a scene, a moment later: wait for the button to be there again.
          var tries = 0;
          var place = function() {
            if (document.querySelector('.scene-layer')) {
              return;
            }
            var el = (back.selector && document.querySelector(back.selector)) ||
              (back.el && document.body.contains(back.el) && tries > 5 ? back.el : null);
            if (!el && ++tries <= 8) {
              setTimeout(place, 150);
              return;
            }
            el = el || document.getElementById('content');
            if (el) {
              if (!el.hasAttribute('tabindex') && !/^(BUTTON|A|INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) {
                el.setAttribute('tabindex', '-1');
              }
              el.focus({preventScroll: false});
            }
          };
          setTimeout(place, 60);
        }
      });
    });
  }).observe(document.body, {childList: true});

  // A scene or a minigame open over the page is a dialog: Tab goes round inside it, never to the page under it, and
  // Escape closes it as its ✕ does (there is no ✕ while a play is being saved, and then Escape does nothing).
  document.addEventListener('keydown', function(ev) {
    var layers = document.querySelectorAll('.scene-layer');
    var layer = layers[layers.length - 1];
    if (!layer || ev.defaultPrevented) {
      return;
    }
    if (ev.key === 'Escape') {
      var cross = layer.querySelector('.scene-close');
      if (cross) {
        ev.preventDefault();
        cross.click();
      }
      return;
    }
    if (ev.key !== 'Tab') {
      return;
    }
    var focusable = Array.prototype.filter.call(layer.querySelectorAll('button, [href], input, select, textarea, ' +
      '[tabindex]:not([tabindex="-1"])'), function(el) {
      return !el.disabled && el.offsetParent !== null;
    });
    if (!focusable.length) {
      return;
    }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    var inside = layer.contains(document.activeElement);
    if (ev.shiftKey && (document.activeElement === first || !inside)) {
      ev.preventDefault();
      last.focus();
    } else if (!ev.shiftKey && (document.activeElement === last || !inside)) {
      ev.preventDefault();
      first.focus();
    }
  });
})();
