// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The climb to the tower: the path of a mission that brings one (its package's `climb`), instead of the map. The way
 * in, the valley with its six lanterns and the line of light of the path, the place of now with its steps as stones
 * (each one a scene played in its place), the lantern that lights when they are done and the gems it brings.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';
  var T = window.Tesape;
  var cfg = T.cfg;
  var t = T.t;
  var esc = T.esc;
  var app = document.getElementById('app');
  document.body.classList.add('climb');

  /** @var {Object|null} Where the student is (get_climb): climb, speakers, chapter, scenes, lantern, hero, gems… */
  var state = null;
  /** @var {Object} The world of the student, for the scenes played in their place (their hero and creature). */
  var world = {};
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var SVG = {
    lantern: '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" ' +
      'aria-hidden="true"><path d="M16 2v4M11 6h10l-1 3H12z"/><path d="M10 9h12l-1 15H11z"/><path d="M12 28h8M14 24v4M18 24v4"/>' +
      '<path d="M16 13c2 2 2 5 0 7-2-2-2-5 0-7z" fill="currentColor" stroke="none"/></svg>',
    little: '<svg viewBox="0 0 14 20" aria-hidden="true"><path d="M4.5 4.5a2.5 2.5 0 0 1 5 0" fill="none" ' +
      'stroke-width="1.6"/><path d="M3 5h8l-.6 1.5H3.6z"/><path d="M3.4 6.5h7.2l-.8 9.5H4.2z"/><path d="M2.6 16h8.8v2.2H2.6z"/>' +
      '</svg>',
    gem: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12l4 6-10 12L2 9z" fill="currentColor"/>' +
      '<path d="M2 9h20M9 3 7 9l5 12 5-12-2-6" fill="none" stroke="rgba(255,255,255,.55)" stroke-width="1.2"/></svg>',
    // The sign cut in each stone of the place, by the kind of play of its scene.
    nest: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M3 13c2 5 16 5 18 0"/><path d="M4 13c3-2 13-2 16 0M5 16c3-1 11-1 14 0"/>' +
      '<ellipse cx="12" cy="10.5" rx="3" ry="3.6" fill="currentColor" stroke="none"/></svg>',
    hunt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
      'stroke-linejoin="round"><path d="M4 19c-1-3 1-6 4-6 0-3 3-5 5-3 2-2 6-1 6 3 2 0 3 3 2 6z" fill="currentColor" ' +
      'fill-opacity=".35"/><path d="M3 9c1-1.5 2-1.5 3 0M18 7c1-1.5 2-1.5 3 0M10 5c1-1.5 2-1.5 3 0"/></svg>',
    duel: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">' +
      '<path d="M12 6c-3-2-6-2-9-1v14c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1z"/><path d="M12 6v14M6 9h3M6 12h3M15 9h3M15 12h3"/>' +
      '</svg>'
  };

  /**
   * The size of the painting of the valley.
   *
   * @return {number[]}
   */
  function master() {
    return ((state.climb.valley || {}).size) || [1920, 1080];
  }

  /**
   * A mark on the valley, at the pixels of its painting, as a share: it follows the painting at any width.
   *
   * @param {HTMLElement} el
   * @param {number[]} xy
   * @return {HTMLElement}
   */
  function at(el, xy) {
    var m = master();
    el.style.left = (xy[0] / m[0] * 100) + '%';
    el.style.top = (xy[1] / m[1] * 100) + '%';
    return el;
  }

  /**
   * Calls a service of the climb and keeps what it says of where the student is.
   *
   * @param {string} name
   * @param {Object} [args]
   * @return {Promise}
   */
  function call(name, args) {
    return T.api(name, Object.assign({courseid: cfg.courseid}, args || {})).then(function(r) {
      state = JSON.parse(r.data);
      return state;
    });
  }

  /**
   * The voice of a line: the hero speaks with the voice of their role and figure; anyone else, with theirs.
   *
   * @param {string} who
   * @param {string} text
   * @return {string|null}
   */
  function voiceOf(who, text) {
    var hash = T.lineKey(text).replace(/^voice\.line\./, '');
    var key;
    if (who === 'heroe' || who === 'hero') {
      var hero = state.hero || {};
      key = 'voice.hero.' + (hero.nameform === 'f' ? 'f' : 'm') + (['healer', 'scout'].indexOf(hero.role) >= 0 ? '1' : '2') +
        '.' + hash;
    } else {
      key = 'voice.say.' + who + '.' + hash;
    }
    return T.artUrl([key]) ? key : null;
  }

  /**
   * What is said, written the same under: who says it and the line. Empty clears it.
   *
   * @param {string} who
   * @param {string} text
   */
  function subtitle(who, text) {
    var place = document.querySelector('.pl-subtitulos');
    if (!place) {
      place = document.body.appendChild(document.createElement('div'));
      place.className = 'pl-subtitulos';
      place.setAttribute('aria-live', 'polite');
      place.innerHTML = '<p class="pl-subtitulo"></p>';
    }
    var p = place.firstChild;
    var hero = who === 'heroe' || who === 'hero';
    var name = hero ? t('app_climb_hero') : (state.speakers || {})[who] || '';
    p.setAttribute('data-quien', hero ? 'heroe' : who === 'druida-mayor' ? 'druida' : who);
    p.innerHTML = text ? (name ? '<small>' + esc(name) + '</small>' : '') + '<span>' + esc(text) + '</span>' : '';
  }

  /**
   * Says a line, [who, text], with its voice and its subtitle; it ends when the voice ends (or after time to read it).
   *
   * @param {Array} pair
   * @return {Promise}
   */
  function say(pair) {
    return new Promise(function(done) {
      if (!pair || !pair[1]) {
        done();
        return;
      }
      subtitle(pair[0], pair[1]);
      var over = false;
      var end = function() {
        if (!over) {
          over = true;
          done();
        }
      };
      var key = voiceOf(pair[0], pair[1]);
      var spoke = !!(key && T.fx && T.fx.voice(key, end));
      if (!spoke) {
        setTimeout(end, Math.max(1600, pair[1].length * 55));
      }
    });
  }

  /**
   * Says several lines, one after another.
   *
   * @param {Array} lines
   * @return {Promise}
   */
  function chain(lines) {
    return (lines || []).reduce(function(p, pair) {
      return p.then(function() {
        return say(pair);
      });
    }, Promise.resolve()).then(function() {
      subtitle('', '');
    });
  }

  /**
   * «Your path: n of 6», up on the left, with six little lanterns.
   *
   * @param {number} n
   */
  function counter(n) {
    var all = ((state.climb.valley || {}).lanterns || []).length || 6;
    var el = document.querySelector('.pl-contador') || document.body.appendChild(document.createElement('div'));
    el.className = 'pl-contador';
    el.setAttribute('role', 'status');
    var pips = '';
    for (var i = 0; i < all; i++) {
      pips += '<i class="' + (i < n ? 'on' : '') + '">' + SVG.little + '</i>';
    }
    el.innerHTML = '<span class="pl-contador-farol">' + SVG.lantern + '</span><span class="pl-contador-texto">' +
      '<span class="pl-contador-linea"><span>' + esc(t('app_climb_way')) + '</span><b>' +
      esc(t('app_climb_count', {n: n, of: all})) + '</b></span><span class="pl-contador-pips" aria-hidden="true">' + pips +
      '</span></span>';
  }

  /**
   * How many lanterns of the path the student has lit: on the path, once its moment came (its gems are given then).
   *
   * @return {number}
   */
  function lit() {
    return state.paid ? 1 : 0;
  }

  /**
   * The valley, at the size of the screen (or sliding sideways on a phone held upright), with its lanterns lit and every
   * lantern a target that trembles before its time.
   *
   * @return {Object} scene and map (the elements).
   */
  function valley() {
    app.innerHTML = '';
    var scene = document.createElement('main');
    scene.className = 'pl-escena pl-desliza';
    var map = document.createElement('div');
    map.className = 'pl-mapa';
    map.innerHTML = '<img class="pl-fondo" src="' + esc(T.artUrl(['scene.valle']) || '') + '" alt="' +
      esc(t('app_climb_valley')) + '" draggable="false">';
    scene.appendChild(map);
    app.appendChild(scene);
    var v = state.climb.valley || {};
    for (var i = 0; i < lit(); i++) {
      layer(map, i, false);
    }
    (v.lanterns || []).forEach(function(xy, i) {
      var b = mark(map, 'pl-farol', xy, t('app_climb_lantern', i + 1) + (i < lit() ? t('app_climb_lit') : t('app_climb_out')));
      b.onclick = function() {
        if (i >= lit()) {
          b.classList.remove('tiembla');
          void b.offsetWidth;
          b.classList.add('tiembla');
        }
      };
    });
    counter(lit());
    return {scene: scene, map: map};
  }

  /**
   * The painted layer of a lantern lit, on its box; it may come in slowly.
   *
   * @param {HTMLElement} map
   * @param {number} i
   * @param {boolean} fade
   * @return {HTMLElement|null}
   */
  function layer(map, i, fade) {
    var box = ((state.climb.valley || {}).lit || [])[i];
    var url = T.artUrl(['scene.valle.luz-' + (i + 1)]);
    if (!box || !url) {
      return null;
    }
    var m = master();
    var img = document.createElement('img');
    img.className = 'pl-capa';
    img.alt = '';
    img.src = url;
    img.style.left = (box[0] / m[0] * 100) + '%';
    img.style.top = (box[1] / m[1] * 100) + '%';
    img.style.width = ((box[2] - box[0]) / m[0] * 100) + '%';
    img.style.height = ((box[3] - box[1]) / m[1] * 100) + '%';
    if (fade && !still) {
      img.style.opacity = '0';
      img.style.transition = 'opacity 2.4s ease';
      setTimeout(function() {
        img.style.opacity = '1';
      }, 60);
    }
    map.insertBefore(img, map.children[1] || null);
    return img;
  }

  /**
   * A button on the valley.
   *
   * @param {HTMLElement} map
   * @param {string} cls
   * @param {number[]} xy
   * @param {string} label
   * @param {string} [html]
   * @return {HTMLElement}
   */
  function mark(map, cls, xy, label, html) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'pl-marca ' + cls;
    b.setAttribute('aria-label', label);
    b.innerHTML = html || '';
    map.appendChild(at(b, xy));
    return b;
  }

  /**
   * On a phone held upright the valley slides: the point of the painting given, in the middle.
   *
   * @param {HTMLElement} scene
   * @param {number} x
   */
  function centre(scene, x) {
    if (window.innerWidth > window.innerHeight) {
      return;
    }
    var map = scene.querySelector('.pl-mapa');
    scene.scrollLeft = Math.max(0, map.offsetWidth * x / master()[0] - window.innerWidth / 2);
  }

  /**
   * The way in: the valley at dusk, the tower lit up there, and one button. Nothing sounds before it is touched.
   */
  function entrance() {
    var v = valley();
    var beacon = document.createElement('i');
    beacon.className = 'pl-marca pl-faro';
    v.map.appendChild(at(beacon, (state.climb.valley || {}).beacon || [1342, 152]));
    var veil = document.createElement('div');
    veil.className = 'pl-velo';
    v.scene.appendChild(veil);
    document.querySelector('.pl-contador').remove();
    var first = (state.climb.opening || [])[0] || {};
    var place = document.createElement('div');
    place.className = 'pl-entrar-sitio';
    place.innerHTML = '<button type="button" class="btn btn-main pl-entrar">' + esc(first.button || t('app_climb_enter')) +
      '</button>';
    app.appendChild(place);
    centre(v.scene, 1230);
    place.querySelector('button').onclick = function() {
      this.classList.add('pl-fuera');
      v.scene.classList.add('pl-dentro');
      T.fx.ambient('night', 'claro');
      setTimeout(function() {
        place.remove();
        path();
      }, still ? 0 : 900);
    };
  }

  /**
   * The path: the valley with the place of now lit and its steps as stones, each one a scene; the step of now has its
   * name over it. When all are done, its lantern lights; then the camp and the next place appear.
   */
  function path() {
    var v = valley();
    var c = state.climb.valley || {};
    if (!lit()) {
      var glow = document.createElement('i');
      glow.className = 'pl-lugar';
      v.map.appendChild(at(glow, c.place || [445, 905]));
    }
    var now = state.scenes.filter(function(s) {
      return !s.done;
    })[0] || null;
    state.scenes.forEach(function(s) {
      var where = (c.steps || {})[s.code];
      if (!where) {
        return;
      }
      var how = s.done ? 'hecha' : s === now ? 'ahora' : 'luego';
      var sign = SVG[/nido/.test(s.code) ? 'nest' : /duelo/.test(s.code) ? 'duel' : 'hunt'];
      var b = mark(v.map, 'pl-piedra ' + how, where, s.title + (s.done ? t('app_climb_done') : s === now ?
        t('app_climb_now') : ''), sign);
      b.onclick = function() {
        if (s === now || s.done) {
          play(s);
        } else {
          b.classList.remove('tiembla');
          void b.offsetWidth;
          b.classList.add('tiembla');
        }
      };
      if (s === now) {
        var tag = document.createElement('span');
        tag.className = 'pl-marca pl-piedra-nombre';
        tag.textContent = s.title;
        v.map.appendChild(at(tag, [where[0], where[1] - 44]));
      }
    });
    if (lit()) {
      var camp = mark(v.map, 'pl-campamento', c.camp || [735, 930], t('app_climb_camp'), T.artUrl(['thing.valle.tienda']) ?
        '<img src="' + esc(T.artUrl(['thing.valle.tienda'])) + '" alt="">' : '');
      camp.onclick = function() {
        if (T.climbCamp) {
          T.climbCamp(api());
        }
      };
      var next = mark(v.map, 'pl-siguiente', c.next || [1075, 735], t('app_climb_next'));
      next.onclick = function() {
        chain(((state.climb.next || {}).touch || {}).say);
      };
    }
    centre(v.scene, lit() ? 540 : 490);
    if (!now && !lit()) {
      lantern(v);
    }
  }

  /**
   * A step of the place: its scene, played in its place; back on the path when it closes.
   *
   * @param {Object} s
   */
  function play(s) {
    T.api('get_world', {courseid: cfg.courseid}).catch(function() {
      return null;
    }).then(function(w) {
      world = w || world;
      T.playScene({sceneid: s.id, world: world, onclose: function() {
        call('get_climb').then(path);
      }});
    });
  }

  /**
   * The lantern of the place lights: the light of the nest goes up its post, its layer of the valley comes in, the
   * creature learns its sense, and the gems of the day fly to the bag and are counted.
   *
   * @param {Object} v The valley.
   */
  function lantern(v) {
    var before = state.gems;
    var says = state.climb.lantern || {};
    call('climb_act', {action: 'light'}).then(function() {
      layer(v.map, 0, true);
      var glow = v.map.querySelector('.pl-lugar');
      if (glow) {
        glow.remove();
      }
      T.fx.sound('lantern');
      counter(lit());
      return chain(says.say);
    }).then(function() {
      return gems(before);
    }).then(function() {
      path();
    });
  }

  /**
   * The gems of the day: what the Embrollo dropped, the light of the lantern and the shine of the find, counted into the
   * bag. Only here and at the camp are they seen.
   *
   * @param {number} before What the bag had before the lantern.
   * @return {Promise}
   */
  function gems(before) {
    var says = state.climb.gems || {};
    var from = state.scenes.reduce(function(n, s) {
      return n + s.gems;
    }, 0) + (state.paid ? (says.from || {}).lantern || 0 : 0);
    var bag = document.createElement('div');
    bag.className = 'pl-bolsa';
    bag.setAttribute('role', 'status');
    bag.innerHTML = '<span class="pl-bolsa-gema">' + SVG.gem + '</span><b>0</b>';
    document.body.appendChild(bag);
    var shown = bag.querySelector('b');
    var n = 0;
    return new Promise(function(done) {
      var step = function() {
        if (n < from) {
          n++;
          shown.textContent = String(n);
          bag.classList.remove('pl-bolsa-late');
          void bag.offsetWidth;
          bag.classList.add('pl-bolsa-late');
          T.fx.sound('coin');
          setTimeout(step, still ? 0 : 160);
          return;
        }
        shown.textContent = String(Math.max(state.gems, before + from));
        chain(says.say).then(function() {
          setTimeout(function() {
            bag.remove();
            done();
          }, still ? 0 : 1200);
        });
      };
      setTimeout(step, still ? 0 : 400);
    });
  }

  /**
   * What the other parts of the climb (the camp, the postcard, the first minute) may use of this one.
   *
   * @return {Object}
   */
  function api() {
    return {state: function() {
      return state;
    }, call: call, say: say, chain: chain, subtitle: subtitle, counter: counter, valley: valley, path: path, at: at,
    svg: SVG, still: still};
  }

  // For the tests of the browser.
  T.climb = {state: function() {
    return state;
  }, path: function() {
    path();
  }};

  call('get_climb').then(function() {
    entrance();
  }).catch(function(e) {
    app.innerHTML = '<p class="climb-error">' + esc((e && e.message) || t('app_error')) + '</p>';
  });
})();
