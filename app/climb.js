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
   * Notes a measure of the script, once (the server keeps only the first): what, and its value.
   *
   * @param {string} what
   * @param {string|number} value
   */
  function note(what, value) {
    if (state && state.marks && state.marks.indexOf(what) < 0) {
      state.marks.push(what);
      if (!state.hero || state.hero.trying) {
        // Before there is a hero (the first minute), kept until there is one.
        waiting.push([what, value]);
        return;
      }
      T.api('climb_act', {courseid: cfg.courseid, action: 'mark', mark: what, value: String(value)}).catch(function() {
        return null;
      });
    }
  }

  /** @var {Array} What was noted before the student had a hero. */
  var waiting = [];

  /**
   * Sends what was noted before the student had a hero.
   */
  function sendWaiting() {
    var all = waiting;
    waiting = [];
    all.forEach(function(one) {
      T.api('climb_act', {courseid: cfg.courseid, action: 'mark', mark: one[0], value: String(one[1])}).catch(function() {
        return null;
      });
    });
  }

  /** @var {number} When the valley was entered (for the second of the first touch). */
  var enteredAt = 0;
  /** @var {number} When the path came back after the next place showed itself (for whether it is touched, and when). */
  var invitedAt = 0;

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
      enteredAt = Date.now();
      T.fx.ambient('night', 'claro');
      setTimeout(function() {
        place.remove();
        if (state.hero) {
          path();
        } else {
          opening(v);
        }
      }, still ? 0 : 900);
    };
  }

  /**
   * Waits a while (none without movement).
   *
   * @param {number} ms
   * @return {Promise}
   */
  function wait(ms) {
    return new Promise(function(done) {
      setTimeout(done, still ? 0 : ms);
    });
  }

  /**
   * Waits for a touch on an element.
   *
   * @param {HTMLElement} el
   * @return {Promise}
   */
  function touched(el) {
    return new Promise(function(done) {
      el.onclick = function() {
        el.onclick = null;
        done();
      };
    });
  }

  /**
   * A picture of the pack on the screen, where the class says.
   *
   * @param {HTMLElement} host
   * @param {string} cls
   * @param {string} key
   * @param {string} [tag] img (default) or button.
   * @param {string} [label]
   * @return {HTMLElement}
   */
  function figure(host, cls, key, tag, label) {
    var el = document.createElement(tag || 'img');
    el.className = cls;
    var url = T.artUrl([key]) || '';
    if (tag === 'button') {
      el.type = 'button';
      el.setAttribute('aria-label', label || '');
      el.innerHTML = '<img src="' + esc(url) + '" alt="">';
    } else {
      el.src = url;
      el.alt = '';
    }
    host.appendChild(el);
    return el;
  }

  /**
   * Puts an element over a box of the painting of the clearing, as it covers the screen.
   *
   * @param {HTMLElement} el
   * @param {number[]} box x0, y0, x1, y1 in pixels of the painting (or a point: x, y).
   * @param {number} [tall] For a point: the height there, in pixels of the painting; the point is at its feet.
   */
  function onClearing(el, box, tall) {
    var size = (state.climb.clearing || {}).size || [1440, 810];
    var k = Math.max(window.innerWidth / size[0], window.innerHeight / size[1]);
    var dx = (window.innerWidth - size[0] * k) / 2;
    var dy = (window.innerHeight - size[1] * k) / 2;
    el.style.position = 'absolute';
    if (box.length === 4) {
      el.style.left = (dx + box[0] * k) + 'px';
      el.style.top = (dy + box[1] * k) + 'px';
      el.style.width = ((box[2] - box[0]) * k) + 'px';
      el.style.height = ((box[3] - box[1]) * k) + 'px';
    } else {
      el.style.left = (dx + box[0] * k) + 'px';
      el.style.top = (dy + (box[1] - tall) * k) + 'px';
      el.style.height = (tall * k) + 'px';
      el.style.transform = 'translateX(-50%)';
    }
  }

  /**
   * The point of the path of the valley nearest to a point.
   *
   * @param {number[]} xy
   * @return {number} Its index.
   */
  function nearest(xy) {
    var pts = (state.climb.valley || {}).path || [];
    var best = 0;
    pts.forEach(function(p, i) {
      if (Math.hypot(p[0] - xy[0], p[1] - xy[1]) < Math.hypot(pts[best][0] - xy[0], pts[best][1] - xy[1])) {
        best = i;
      }
    });
    return best;
  }

  /**
   * The first minute: the Embrollo puts the six lanterns out on its way up to the tower; the druid gives the seed; the
   * line of light draws the path; the student chooses who they are (a hero, then its figure) and leaves the seed in the
   * nest, where the Nest begins. What is said comes from the package (climb.opening), in its order.
   *
   * @param {Object} v The valley of the way in.
   */
  function opening(v) {
    var beats = state.climb.opening || [];
    var beat = function(n) {
      return beats[n] || {};
    };
    var c = state.climb.valley || {};
    var pts = c.path || [];
    // Lit at first, all six; each goes out as the Embrollo passes it.
    var layers = (c.lanterns || []).map(function(xy, i) {
      return layer(v.map, i, false);
    });
    var veil = v.scene.querySelector('.pl-velo');
    var ball = figure(v.map, 'pl-marca op-embrollo', 'figure.embrollo');
    at(ball, pts[0] || [445, 905]);
    var climbUp = function(ms) {
      return new Promise(function(done) {
        if (still || !pts.length) {
          layers.forEach(function(l) {
            if (l) {
              l.style.opacity = '0';
            }
          });
          at(ball, c.beacon || [1342, 152]);
          done();
          return;
        }
        var outAt = (c.lanterns || []).map(nearest);
        var t0 = 0;
        requestAnimationFrame(function step(now) {
          t0 = t0 || now;
          var k = Math.min(1, (now - t0) / ms);
          var i = Math.min(pts.length - 1, Math.floor(k * (pts.length - 1)));
          at(ball, pts[i]);
          ball.style.transform = 'translate(-50%, -80%) rotate(' + (k * 1440) + 'deg)';
          outAt.forEach(function(j, n) {
            var l = layers[n];
            if (l && i >= j && l.style.opacity !== '0') {
              l.style.transition = 'opacity .5s ease';
              l.style.opacity = '0';
              T.fx.sound('tap');
            }
          });
          if (k < 1) {
            requestAnimationFrame(step);
          } else {
            done();
          }
        });
      });
    };
    if (veil) {
      veil.remove();
    }
    wait(600).then(function() {
      // Second 5: it rolls across the clearing and up the path, putting the lanterns out.
      T.fx.sound('giggle');
      return Promise.all([climbUp(7000), chain(beat(1).say)]);
    }).then(function() {
      ball.remove();
      return clearing();
    });

    // Seconds 12 to 30: the druid, the seed that beats, in the hands of who plays.
    var clearing = function() {
      var stage = document.createElement('div');
      stage.className = 'op-claro';
      stage.style.backgroundImage = 'url("' + (T.artUrl(['scene.claro-abierto']) || '') + '")';
      app.appendChild(stage);
      var druid = figure(stage, 'op-druida', 'figure.druida-mayor');
      var cl = state.climb.clearing || {};
      if (cl.druid) {
        // Where she stands in the Nest, as tall.
        onClearing(druid, cl.druid, cl.druidTall || 170);
      }
      return wait(400).then(function() {
        druid.classList.add('op-dentro');
        T.fx.sound('near');
        return wait(1600);
      }).then(function() {
        var seed = figure(stage, 'op-semilla', 'creature.seed', 'button', t('app_climb_seed'));
        var hand = document.createElement('i');
        hand.className = 'op-mano';
        hand.innerHTML = '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M20 44c-6-3-11-10-12-16-1-3 2-5 4-3l4 4V9a3 3 0 0 1 6 0v12' +
          'V6a3 3 0 0 1 6 0v15V8a3 3 0 0 1 6 0v14-9a3 3 0 0 1 6 0v17c0 8-5 14-12 15z"/></svg>';
        stage.appendChild(hand);
        return chain(beat(3).say).then(function() {
          return touched(seed);
        }).then(function() {
          // The first touch with an effect: its second, from the way in.
          note('first', Math.round((Date.now() - enteredAt) / 1000));
          hand.remove();
          seed.classList.add('op-late');
          T.fx.sound('chime');
          return wait(900);
        }).then(function() {
          seed.classList.add('op-abajo');
          druid.classList.add('op-fuera');
          return chain(beat(4).say);
        }).then(function() {
          stage.remove();
          return way(seed);
        });
      });
    };

    // Seconds 30 to 40: the line of light of the path, the counter, the Embrollo up in the tower.
    var way = function(seed) {
      app.appendChild(seed);
      var line = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      var m = master();
      line.setAttribute('class', 'pl-sendero');
      line.setAttribute('viewBox', '0 0 ' + m[0] + ' ' + m[1]);
      line.setAttribute('preserveAspectRatio', 'none');
      line.innerHTML = '<path d="M' + pts.map(function(xy) {
        return xy[0] + ' ' + xy[1];
      }).join(' L') + '"/>';
      v.map.appendChild(line);
      line.style.setProperty('--largo', Math.ceil(line.querySelector('path').getTotalLength()));
      counter(0);
      T.fx.sound('path');
      return chain(beat(5).say).then(function() {
        var top = figure(v.map, 'pl-marca op-embrollo op-arriba', 'figure.embrollo');
        at(top, c.beacon || [1342, 152]);
        return chain(beat(6).say).then(function() {
          top.remove();
          return heroes(seed);
        });
      });
    };

    // Seconds 40 to 62: who are you? Four heroes; the one chosen steps forward; its two figures; one takes the seed.
    var heroes = function(seed) {
      var stage = document.createElement('div');
      stage.className = 'op-claro op-heroes';
      stage.style.backgroundImage = 'url("' + (T.artUrl(['scene.claro-abierto']) || '') + '")';
      app.appendChild(stage);
      var row = document.createElement('div');
      row.className = 'op-fila';
      stage.appendChild(row);
      var said = beat(8).say || {};
      var roles = ['sage', 'healer', 'scout', 'protector'];
      var heard = {};
      var forms = {sage: 'f', healer: 'm', scout: 'f', protector: 'm'};
      return chain(beat(7).say).then(function() {
        return new Promise(function(chosen) {
          var last = null;
          roles.forEach(function(role) {
            // Not named on the first day: each one is what it says.
            var b = figure(row, 'op-heroe', 'character.storyheroes.' + role + '.' + forms[role] + '.1', 'button',
              (said[role] || [])[1] || role);
            b.dataset.role = role;
            b.onclick = function() {
              if (last === role) {
                chosen(role);
                return;
              }
              last = role;
              heard[role] = true;
              row.querySelectorAll('.op-heroe').forEach(function(o) {
                o.classList.toggle('op-tocado', o === b);
              });
              state.hero = {role: role, nameform: forms[role], trying: true};
              say(said[role]).then(function() {
                subtitle('', '');
              });
            };
          });
        });
      }).then(function(role) {
        // The heroes heard, by their first letter (sage, healer, scout, protector: s, h, c, p).
        note('heard', roles.filter(function(r) {
          return heard[r];
        }).map(function(r) {
          return {sage: 's', healer: 'h', scout: 'c', protector: 'p'}[r];
        }).join(''));
        row.querySelectorAll('.op-heroe').forEach(function(o) {
          o.classList.toggle('op-aparta', o.dataset.role !== role);
        });
        T.fx.sound('choose');
        return wait(900).then(function() {
          row.innerHTML = '';
          T.fx.sound('chime');
          return new Promise(function(picked) {
            ['m', 'f'].forEach(function(form) {
              var b = figure(row, 'op-heroe op-figura', 'character.storyheroes.' + role + '.' + form + '.1', 'button',
                t('app_climb_figure_' + form));
              b.dataset.form = form;
              b.onclick = function() {
                picked([role, form]);
              };
            });
          });
        });
      }).then(function(pick) {
        row.querySelectorAll('.op-figura').forEach(function(o) {
          o.classList.toggle('op-aparta', o.dataset.form !== pick[1]);
          if (o.dataset.form === pick[1]) {
            // The seed goes to its hands.
            seed.classList.add('op-manos');
          }
        });
        return call('climb_act', {action: 'hero', role: pick[0], nameform: pick[1]});
      }).then(function() {
        sendWaiting();
        return wait(1200);
      }).then(function() {
        stage.remove();
        seed.remove();
        return nest();
      });
    };

    // Second 62: the Nest, in its place: the seed comes to the hands of the hero, who leaves it in the nest when the
    // nest is touched; then the Nest begins.
    var nest = function() {
      var first = state.scenes.filter(function(s) {
        return s.code === (beat(beats.length - 1).scene || '');
      })[0] || state.scenes[0];
      path();
      if (first) {
        play(first);
      }
      return Promise.resolve();
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
        campsite();
      };
      var next = mark(v.map, 'pl-siguiente', c.next || [1075, 735], t('app_climb_next'));
      next.onclick = function() {
        // The measure that decides: touched with nobody asking, and after how many seconds.
        if (invitedAt) {
          note('next', Math.round((Date.now() - invitedAt) / 1000));
          note('mute', T.fx.muted && T.fx.muted() ? 'yes' : 'no');
        }
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
      T.playScene({sceneid: s.id, world: world, bare: true, onclose: function() {
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
    bag.className = 'pl-bolsa-dia';
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
          bag.classList.remove('pl-late');
          void bag.offsetWidth;
          bag.classList.add('pl-late');
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

  /** @var {Object} The paints of the market, the colour of each pot and how it looks on the drawn creature. */
  var POTS = {
    'paint-gold': {pot: '#f2b632', filter: 'hue-rotate(17deg) saturate(1.6) brightness(1.18)'},
    'paint-night': {pot: '#2f4f86', filter: 'hue-rotate(190deg) saturate(.9) brightness(.75)'},
    'paint-snow': {pot: '#e9eef3', filter: 'grayscale(.92) brightness(1.25)'},
    'paint-ember': {pot: '#e0613f', filter: 'hue-rotate(-22deg) saturate(1.6) brightness(1.05)'},
    'paint-moss': {pot: '#5f8f3e', filter: 'hue-rotate(75deg) saturate(.9) brightness(.92)'},
    'paint-coral': {pot: '#f07b86', filter: 'hue-rotate(-38deg) saturate(1.2) brightness(1.15)'}
  };

  /** @var {Array} The skins of the camp, by the tone of the pictures of the heroes (t1, t2, t3). */
  var TONES = [['t1', 0], ['t2', 2], ['t3', 4]];

  /**
   * The picture of the student's hero: its role, its figure and, if chosen, its tone.
   *
   * @param {string} [form]
   * @param {string} [tone]
   * @return {string}
   */
  function heroPicture(form, tone) {
    var h = state.hero || {};
    var base = 'character.storyheroes.' + h.role + '.' + (form || h.nameform) + '.1';
    return T.artUrl([base + '.' + (tone || toneOf(h.skin)), base].filter(function(k) {
      return k.slice(-1) !== '.';
    })) || '';
  }

  /**
   * The tone of the pictures for a skin of the game.
   *
   * @param {string} skin
   * @return {string}
   */
  function toneOf(skin) {
    var i = (T.SKINS || []).indexOf(skin);
    return i < 0 ? '' : 't' + (i < 2 ? 1 : i < 4 ? 2 : 3);
  }

  /**
   * The camp, by the clearing once its lantern is lit: the hero (figure and skin, free), the creature (named if wanted,
   * painted with gems), the pots of paint, the bag of gems, and the eight things of the Nest at the edge, that the
   * creature sniffs with the sense it learnt. Leaving it, the postcard.
   */
  function campsite() {
    var says = state.climb.camp || {};
    var trying = '';
    document.body.classList.add('pl-en-campa');
    app.innerHTML = '';
    var bg = document.createElement('div');
    bg.className = 'pl-campa-fondo pl-noche';
    bg.style.backgroundImage = 'url("' + (T.artUrl(['scene.campamento', 'scene.claro-abierto']) || '') + '")';
    app.appendChild(bg);
    var bag = document.createElement('div');
    bag.className = 'pl-bolsa';
    bag.setAttribute('role', 'status');
    bag.innerHTML = SVG.gem + '<b></b>';
    app.appendChild(bag);
    var main = document.createElement('main');
    main.className = 'pl-campa';
    var pot = function(colour) {
      return '<svg viewBox="0 0 64 72" aria-hidden="true"><ellipse cx="32" cy="14" rx="22" ry="7" fill="#8a6a45"/>' +
        '<path d="M10 14v44c0 6 10 10 22 10s22-4 22-10V14" fill="#a7835a"/><path d="M10 14c0 5 10 8 22 8s22-3 22-8" fill="' +
        colour + '"/><path d="M14 22c2 1 4 9 5 16" stroke="' + colour + '" stroke-width="5" stroke-linecap="round" ' +
        'fill="none"/><rect x="18" y="34" width="28" height="14" rx="4" fill="#f3ead4" opacity=".85"/><circle cx="32" ' +
        'cy="41" r="4.5" fill="' + colour + '"/></svg>';
    };
    var nose = '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" ' +
      'stroke-linejoin="round" aria-hidden="true"><path d="M12.5 4c0 6-2 10-4.5 13-2 2.6-.6 6 2.8 5.6"/><path d="M19.5 4c0 6 ' +
      '2 10 4.5 13 2 2.6.6 6-2.8 5.6"/><path d="M13.5 22c1.4 1.5 3.6 1.5 5 0"/><circle cx="12.3" cy="20.3" r="1.3" ' +
      'fill="currentColor" stroke="none"/><circle cx="19.7" cy="20.3" r="1.3" fill="currentColor" stroke="none"/></svg>';
    var h = state.hero || {};
    main.innerHTML = '<section class="pl-heroe" aria-label="' + esc(t('app_climb_hero')) + '"><img src="' +
      esc(heroPicture()) + '" alt=""><p class="pl-heroe-titulo">' + esc(t('app_climb_hero')) + '</p>' +
      '<div class="pl-opciones">' + ['m', 'f'].map(function(form) {
        return '<button type="button" class="pl-op figura" data-form="' + form + '" aria-pressed="' + (h.nameform === form) +
          '" aria-label="' + esc(t('app_climb_figure_' + form)) + '"><img src="' + esc(heroPicture(form)) + '" alt=""></button>';
      }).join('') + '</div><div class="pl-opciones">' + TONES.filter(function(tone) {
        return T.artUrl(['character.storyheroes.' + h.role + '.' + h.nameform + '.1.' + tone[0]]);
      }).map(function(tone) {
        var skin = (T.SKINS || [])[tone[1] + 1] || '';
        return '<button type="button" class="pl-op piel" data-skin="' + esc(skin) + '" aria-pressed="' +
          (toneOf(h.skin) === tone[0]) + '" aria-label="' + esc(t('app_climb_skin_' + tone[0])) + '"><i style="background:' +
          esc(skin) + '"></i></button>';
      }).join('') + '</div></section><section class="pl-criatura" aria-label="' + esc(t('app_climb_creature')) + '">' +
      '<button type="button" class="pl-criatura-boton" aria-label="' + esc(t('app_climb_name_it')) + '"><img src="' +
      esc(T.artUrl(['creature.2.fur', 'creature.2']) || '') + '" alt=""></button><span class="pl-nombre"></span></section>' +
      '<section class="pl-pinturas" aria-label="' + esc(t('app_climb_paints')) + '"><div class="pl-estante">' +
      Object.keys(POTS).map(function(code) {
        return '<button type="button" class="pl-bote" data-code="' + code + '" aria-pressed="false" aria-label="' +
          esc(t('app_climb_' + code.replace('-', '_'))) + '">' + pot(POTS[code].pot) + '</button>';
      }).join('') + '</div><span class="pl-precio">' + SVG.gem + esc(t('app_climb_each', (says.paint || {}).price || 10)) +
      '</span><button type="button" class="btn btn-main pl-pintarla" hidden>' + esc(t('app_climb_paint_it')) + '</button>' +
      '</section><div class="pl-borde"><button type="button" class="pl-olfato" aria-label="' + esc(t('app_climb_sniff')) +
      '"><img src="' + esc(cfg.wwwroot + '/local/oksigeniatesape/app/scent.svg') + '" alt="">' + nose + '</button>' +
      '<div class="pl-cosas" aria-label="' + esc(t('app_climb_things')) + '"></div><button type="button" ' +
      'class="btn btn-soft pl-salir">' + esc(t('app_climb_leave')) + '</button></div>';
    app.appendChild(main);
    var naming = document.createElement('div');
    naming.className = 'pl-nombrar';
    naming.hidden = true;
    naming.innerHTML = '<form><label for="pl-nombre-campo">' + esc(((says.name || {}).ask || [])[1] || '') + '</label>' +
      '<input id="pl-nombre-campo" maxlength="24" autocomplete="off"><div class="botones"><button type="button" ' +
      'class="btn btn-soft" data-cerrar>' + esc(t('app_climb_not_now')) + '</button><button class="btn btn-main">' +
      esc(t('app_climb_give_it')) + '</button></div></form>';
    app.appendChild(naming);
    var creature = main.querySelector('.pl-criatura-boton img');
    var paintIt = main.querySelector('.pl-pintarla');
    var show = function(spent) {
      bag.querySelector('b').textContent = String(state.gems);
      bag.setAttribute('aria-label', t('app_climb_gems', state.gems));
      if (spent) {
        bag.classList.remove('gasta');
        void bag.offsetWidth;
        bag.classList.add('gasta');
      }
      var name = main.querySelector('.pl-nombre');
      name.textContent = state.creature.name || (says.name || {}).default || '';
      name.classList.toggle('puesto', !!state.creature.name);
      creature.style.setProperty('--paint', (POTS[trying || state.creature.paint] || {}).filter || 'none');
      main.querySelectorAll('.pl-bote').forEach(function(b) {
        b.setAttribute('aria-pressed', String(b.dataset.code === (trying || state.creature.paint)));
      });
    };
    // The pots: touching one tries it on the creature; «Paint it» spends the gems (or wears one it has).
    main.querySelectorAll('.pl-bote').forEach(function(b) {
      b.onclick = function() {
        trying = b.dataset.code;
        var have = state.paints.indexOf(trying) >= 0;
        paintIt.hidden = trying === state.creature.paint;
        paintIt.disabled = !have && state.gems < ((says.paint || {}).price || 10);
        show(false);
        say(says.say && says.say.paints).then(function() {
          subtitle('', '');
        });
      };
    });
    paintIt.onclick = function() {
      var had = state.paints.indexOf(trying) >= 0;
      call('climb_act', {action: 'paint', code: trying}).then(function() {
        paintIt.hidden = true;
        trying = '';
        show(!had);
        T.fx.sound('coin');
        return say(says.say && says.say.painted);
      }).then(function() {
        subtitle('', '');
      }).catch(function() {
        paintIt.disabled = true;
      });
    };
    // The hero: its figure and its skin, for free.
    main.querySelectorAll('.pl-op.figura').forEach(function(b) {
      b.onclick = function() {
        call('climb_act', {action: 'hero', role: state.hero.role, nameform: b.dataset.form}).then(function() {
          campsite();
        });
      };
    });
    main.querySelectorAll('.pl-op.piel').forEach(function(b) {
      b.onclick = function() {
        call('climb_act', {action: 'skin', skin: b.dataset.skin}).then(function() {
          campsite();
        });
      };
    });
    // The things at the edge, and the creature's nose: it sniffs them one after another.
    var things = main.querySelector('.pl-cosas');
    var sniffed = (((state.climb.postcard || {}).surprises) || {});
    Object.keys(sniffed).filter(function(id) {
      return id !== 'caracol';
    }).forEach(function(id) {
      var d = document.createElement('span');
      d.className = 'pl-cosa';
      d.dataset.id = id;
      d.dataset.alive = /^being\./.test(sniffed[id].art) ? '1' : '';
      d.setAttribute('role', 'img');
      d.setAttribute('aria-label', id);
      d.innerHTML = '<img src="' + esc(T.artUrl([sniffed[id].art]) || '') + '" alt="">';
      things.appendChild(d);
    });
    main.querySelector('.pl-olfato').onclick = function() {
      var all = things.querySelectorAll('.pl-cosa');
      all.forEach(function(d) {
        d.classList.remove('vivo', 'novivo');
      });
      all.forEach(function(d, i) {
        setTimeout(function() {
          d.classList.add(d.dataset.alive ? 'vivo' : 'novivo');
          T.fx.sound(d.dataset.alive ? 'chime' : 'tap');
        }, (still ? 0 : 450) * i);
      });
    };
    // The name, if the child wants one.
    main.querySelector('.pl-criatura-boton').onclick = function() {
      naming.hidden = false;
      naming.querySelector('input').value = state.creature.name;
      naming.querySelector('input').focus();
      say((says.name || {}).ask).then(function() {
        subtitle('', '');
      });
    };
    naming.querySelector('form').onsubmit = function(e) {
      e.preventDefault();
      naming.hidden = true;
      call('climb_act', {action: 'name', name: naming.querySelector('input').value}).then(function() {
        show(false);
      });
    };
    naming.querySelector('[data-cerrar]').onclick = function() {
      naming.hidden = true;
    };
    main.querySelector('.pl-salir').onclick = function() {
      document.body.classList.remove('pl-en-campa');
      postcard();
    };
    counter(lit());
    show(false);
    say(says.say && says.say.arrive).then(function() {
      subtitle('', '');
    });
  }

  /**
   * Loads a picture for the canvas (null if it does not come).
   *
   * @param {string} src
   * @return {Promise}
   */
  function picture(src) {
    return new Promise(function(done) {
      if (!src) {
        done(null);
        return;
      }
      var i = new Image();
      i.onload = function() {
        done(i);
      };
      i.onerror = function() {
        done(null);
      };
      i.src = src;
    });
  }

  /**
   * The postcard for home, on leaving the camp: «What surprised you most?» with the things of the day; one touch and
   * the postcard is drawn (the valley with the lantern lit, the hero, the creature with its paint sniffing what was
   * chosen, the stamp and the words in the child's voice) and can be saved as a picture. It is not said aloud: it is for
   * the adult to read. Closing it, the twenty seconds of the next place.
   */
  function postcard() {
    var card = state.climb.postcard || {};
    var surprises = card.surprises || {};
    app.innerHTML = '';
    var bg = document.createElement('div');
    bg.className = 'pl-postal-fondo';
    bg.style.backgroundImage = 'url("' + (T.artUrl(['scene.campamento', 'scene.claro-abierto']) || '') + '")';
    app.appendChild(bg);
    var main = document.createElement('main');
    main.className = 'pl-postal';
    var ask = (card.say || [])[0] || [];
    main.innerHTML = '<section class="pl-elegir"><h1>' + esc(ask[1] ? ask[1].replace(/^[^¿]*(?=¿)/, '') : '') +
      '</h1><div class="pl-rejilla"></div></section><section class="pl-hecha" hidden><canvas role="img"></canvas>' +
      '<div class="pl-acciones"><a class="btn btn-main pl-guardar" download="' + esc(t('app_climb_postcard_file')) +
      '" href="#">' + esc(t('app_climb_postcard_save')) + '</a><button type="button" class="btn pl-otra">' +
      esc(t('app_climb_postcard_other')) + '</button><button type="button" class="btn btn-soft pl-cerrar">' +
      esc(t('app_climb_postcard_done')) + '</button></div></section>';
    app.appendChild(main);
    var choose = main.querySelector('.pl-elegir');
    var made = main.querySelector('.pl-hecha');
    Object.keys(surprises).forEach(function(id) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'pl-eleccion';
      b.dataset.id = id;
      b.innerHTML = '<img src="' + esc(T.artUrl([surprises[id].art]) || '') + '" alt=""><span>' +
        esc(surprises[id].text.split(',')[0].replace(/^(el|la|los|las) /, '')) + '</span>';
      b.onclick = function() {
        note('surprise', id);
        draw(id);
      };
      main.querySelector('.pl-rejilla').appendChild(b);
    });
    main.querySelector('.pl-otra').onclick = function() {
      made.hidden = true;
      choose.hidden = false;
    };
    main.querySelector('.pl-cerrar').onclick = function() {
      library();
    };
    main.querySelector('.pl-guardar').addEventListener('click', function() {
      note('saved', 'yes');
    });
    chain(card.say);
    var canvas = made.querySelector('canvas');
    var draw = function(id) {
      choose.hidden = true;
      made.hidden = false;
      subtitle('', '');
      var upright = window.innerHeight > window.innerWidth;
      var W = upright ? 1000 : 1600;
      var H = upright ? 1720 : 1000;
      var L = upright ? {px: 46, py: 46, pw: 908, ph: 780, stamp: [W - 236, 880], tx: 60, ty: 950, tw: 660, foot: H - 50, t1: 62,
        t2: 42, step: 54} : {px: 46, py: 46, pw: 900, ph: H - 92, stamp: [W - 236, 60], tx: 1000, ty: 330, tw: 540, foot: H - 70,
        t1: 46, t2: 29, step: 40};
      canvas.width = W;
      canvas.height = H;
      var ctx = canvas.getContext('2d');
      var style = window.getComputedStyle(document.body);
      var titleFont = style.getPropertyValue('--f-title') || 'Georgia, serif';
      var textFont = style.getPropertyValue('--f-text') || 'Verdana, sans-serif';
      var alive = /^being\./.test(surprises[id].art);
      var lines = function(text, width) {
        var out = [];
        var l = '';
        text.split(' ').forEach(function(w) {
          var tt = l ? l + ' ' + w : w;
          if (ctx.measureText(tt).width > width && l) {
            out.push(l);
            l = w;
          } else {
            l = tt;
          }
        });
        out.push(l);
        return out;
      };
      var round = function(x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
      };
      var box = ((state.climb.valley || {}).lit || [])[0];
      Promise.all([picture(T.artUrl(['scene.valle'])), picture(T.artUrl(['scene.valle.luz-1'])),
        picture(T.artUrl(['creature.2.fur.sniff', 'creature.2.fur'])), picture(T.artUrl([surprises[id].art])),
        picture(heroPicture()), picture(cfg.wwwroot + '/local/oksigeniatesape/app/' + (alive ? 'scent.svg' : 'scent-cold.svg')),
        document.fonts ? document.fonts.ready : null]).then(function(r) {
        var valleyPic = r[0];
        var light = r[1];
        var creature = r[2];
        var thing = r[3];
        var hero = r[4];
        var scent = r[5];
        ctx.fillStyle = '#f3ead4';
        round(0, 0, W, H, 36);
        ctx.fill();
        ctx.setLineDash([14, 10]);
        ctx.strokeStyle = '#c99a3c';
        ctx.lineWidth = 4;
        round(18, 18, W - 36, H - 36, 26);
        ctx.stroke();
        ctx.setLineDash([]);
        // The picture: the valley from the clearing to the tower, the lantern of the clearing lit.
        var m = master();
        ctx.save();
        round(L.px, L.py, L.pw, L.ph, 22);
        ctx.clip();
        var sh = Math.min(m[1], 1060 * L.ph / L.pw);
        var sw = sh * L.pw / L.ph;
        var sx = Math.max(0, Math.min(m[0] - sw, 893 - sw / 2));
        var sy = m[1] - sh;
        if (valleyPic) {
          ctx.drawImage(valleyPic, sx, sy, sw, sh, L.px, L.py, L.pw, L.ph);
        }
        if (light && box) {
          ctx.drawImage(light, L.px + (box[0] - sx) * L.pw / sw, L.py + (box[1] - sy) * L.ph / sh, (box[2] - box[0]) * L.pw / sw,
            (box[3] - box[1]) * L.ph / sh);
        }
        var g = ctx.createRadialGradient(L.px + L.pw * 0.55, L.py + L.ph * 0.86, 10, L.px + L.pw * 0.55, L.py + L.ph * 0.86, 420);
        g.addColorStop(0, 'rgba(255, 210, 120, .55)');
        g.addColorStop(1, 'rgba(255, 210, 120, 0)');
        ctx.fillStyle = g;
        ctx.fillRect(L.px, L.py, L.pw, L.ph);
        if (hero) {
          ctx.drawImage(hero, L.px + 16, L.py + L.ph - 540, 540 * hero.width / hero.height, 540);
        }
        // The creature in profile, sniffing, with its paint; the thing before its nose; the trail of the smell.
        var kx = L.px + 270;
        var ky = L.py + L.ph - 450;
        var ks = 430;
        if (creature) {
          ctx.filter = (POTS[state.creature.paint] || {}).filter || 'none';
          ctx.drawImage(creature, kx, ky, ks, ks);
          ctx.filter = 'none';
        }
        var nx = kx + ks * 0.86;
        var ny = ky + ks * 0.45;
        var ew = 210;
        var eh = 105;
        var ex = nx - 5;
        var ey = ny - eh * 0.25;
        var ts = 180;
        if (thing) {
          ctx.drawImage(thing, ex + ew * 0.766 - ts / 2 + 10, ey + eh * 0.766 + 5, ts, ts);
        }
        if (scent) {
          ctx.save();
          ctx.translate(ex + ew, ey);
          ctx.scale(-1, 1);
          ctx.drawImage(scent, 0, 0, ew, eh);
          ctx.restore();
        }
        ctx.restore();
        // The stamp, with the lantern.
        var s0 = L.stamp[0];
        var s1 = L.stamp[1];
        ctx.fillStyle = '#16304a';
        ctx.fillRect(s0, s1, 170, 200);
        ctx.strokeStyle = '#f3ead4';
        ctx.lineWidth = 10;
        ctx.setLineDash([8, 8]);
        ctx.strokeRect(s0 - 5, s1 - 5, 180, 210);
        ctx.setLineDash([]);
        ctx.strokeStyle = '#ffc857';
        ctx.lineWidth = 4;
        ctx.strokeRect(s0 + 14, s1 + 14, 142, 172);
        var lx = s0 + 85;
        var ly = s1 + 100;
        var gl = ctx.createRadialGradient(lx, ly, 4, lx, ly, 70);
        gl.addColorStop(0, 'rgba(255, 200, 87, .9)');
        gl.addColorStop(1, 'rgba(255, 200, 87, 0)');
        ctx.fillStyle = gl;
        ctx.fillRect(s0 + 14, s1 + 14, 142, 172);
        ctx.fillStyle = '#ffc857';
        ctx.fillRect(lx - 4, ly - 62, 8, 14);
        ctx.fillRect(lx - 26, ly - 48, 52, 8);
        ctx.beginPath();
        ctx.moveTo(lx - 22, ly - 40);
        ctx.lineTo(lx + 22, ly - 40);
        ctx.lineTo(lx + 18, ly + 34);
        ctx.lineTo(lx - 18, ly + 34);
        ctx.closePath();
        ctx.fill();
        ctx.fillStyle = '#fff1bd';
        ctx.beginPath();
        ctx.ellipse(lx, ly - 4, 9, 16, 0, 0, Math.PI * 2);
        ctx.fill();
        // The words, in the child's voice, to the family.
        var name = state.creature.name;
        var text = (card.text || []).slice();
        if (name && card.named) {
          text[0] = card.named.replace('{nombre}', name);
        }
        var y = L.ty;
        ctx.fillStyle = '#16304a';
        ctx.font = '700 ' + L.t1 + 'px ' + titleFont;
        lines(text.shift() || '', L.tw).forEach(function(l) {
          ctx.fillText(l, L.tx, y);
          y += L.t1 + 8;
        });
        y += 14;
        ctx.font = '500 ' + L.t2 + 'px ' + textFont;
        ctx.fillStyle = '#2a2219';
        text.forEach(function(p) {
          lines(p.replace('{sorpresa}', surprises[id].text), L.tw).forEach(function(l) {
            ctx.fillText(l, L.tx, y);
            y += L.step;
          });
          y += 16;
        });
        ctx.strokeStyle = 'rgba(42, 34, 25, .18)';
        ctx.lineWidth = 2;
        for (var r2 = L.ty + 60; r2 < L.foot - 30; r2 += 40) {
          if (r2 > y - 10) {
            ctx.beginPath();
            ctx.moveTo(L.tx, r2);
            ctx.lineTo(L.tx + L.tw, r2);
            ctx.stroke();
          }
        }
        ctx.fillStyle = '#9c650c';
        ctx.font = '600 22px ' + textFont;
        ctx.fillText('Tesapé', L.tx, L.foot);
        main.querySelector('.pl-guardar').href = canvas.toDataURL('image/png');
        canvas.setAttribute('aria-label', t('app_climb_postcard_of', surprises[id].text));
      });
    };
  }

  /**
   * The twenty seconds of the next place, on closing the postcard: the picture goes up the path into a room of shelves
   * in the half-dark; on a table, a forgotten slice of bread; a trail of smell goes from it to the creature, and the
   * bread beats as the seed did. Neither the place nor the mould is named in writing. Then back to the path.
   */
  function library() {
    var next = state.climb.next || {};
    var lib = next.library || {};
    app.innerHTML = '';
    var room = document.createElement('div');
    room.className = 'pl-biblioteca';
    room.style.backgroundImage = 'url("' + (T.artUrl(['scene.biblioteca-estantes', 'scene.biblioteca']) || '') + '")';
    room.innerHTML = '<img class="pl-pan" src="' + esc(T.artUrl(['thing.pan.moho']) || '') + '" alt=""><img class="pl-estela" ' +
      'src="' + esc(cfg.wwwroot + '/local/oksigeniatesape/app/scent.svg') + '" alt="">';
    app.appendChild(room);
    counter(lit());
    wait(1800).then(function() {
      room.classList.add('pl-huele');
      T.fx.sound('chime');
      return wait(900);
    }).then(function() {
      return chain(lib.say);
    }).then(function() {
      return wait(1200);
    }).then(function() {
      room.classList.add('pl-sale');
      return wait(still ? 0 : 900);
    }).then(function() {
      path();
      invitedAt = Date.now();
    });
  }

  // For the tests of the browser.
  T.climb = {state: function() {
    return state;
  }, path: function() {
    path();
  }, camp: function() {
    campsite();
  }, postcard: function() {
    postcard();
  }};

  call('get_climb').then(function() {
    entrance();
  }).catch(function(e) {
    app.innerHTML = '<p class="climb-error">' + esc((e && e.message) || t('app_error')) + '</p>';
  });
})();
