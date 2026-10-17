/**
 * Tesapé: Defensa del nido. Animals come down towards the nest of the creature; the student throws the net of their
 * group to catch them, look at them and let them go far away. A wrong net, or none, lets the animal pass and hurts
 * the shield. There is a mode without hurry, by turns, and a pause. The server counts it all again.
 *
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var cfg = T.cfg;
  var t = T.t;
  var esc = T.esc;

  /** Seconds an animal takes to reach the nest, by speed. */
  var FALL = [14, 11, 9, 7];

  /** Animals on the screen at once. */
  var AT_ONCE = 3;

  function shuffle(list) {
    var a = list.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var x = a[i];
      a[i] = a[j];
      a[j] = x;
    }
    return a;
  }

  /**
   * Plays Defensa del nido.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playNest = function(opts) {
    var layer = document.createElement('div');
    layer.className = 'scene-layer nest-layer';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    document.body.appendChild(layer);
    document.body.classList.add('in-scene');
    var world = opts.world;
    var game = null;
    var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var run = null;

    function close() {
      stop();
      document.removeEventListener('keydown', onKey);
      layer.remove();
      document.body.classList.remove('in-scene');
      opts.onclose(world);
    }

    function show(html) {
      layer.innerHTML = '<div class="scene nest"><div class="scene-top"><span class="scene-title">' +
        esc(game ? game.title : '') + '</span><button class="btn btn-soft scene-close" type="button" aria-label="' +
        esc(t('app_close')) + '">✕</button></div><div class="scene-body">' + html + '</div></div>';
      layer.querySelector('.scene-close').onclick = close;
      layer.scrollTop = 0;
      return layer.querySelector('.scene-body');
    }

    function netLabel(id) {
      var n = game.nets.filter(function(x) {
        return x.id === id;
      })[0];
      return n ? n.label : id;
    }

    function netHint(id) {
      var n = game.nets.filter(function(x) {
        return x.id === id;
      })[0];
      return n ? n.hint : '';
    }

    function creature() {
      var c = world.character || {};
      return c.creature ? T.creature(c.creature, c.creaturestage) : T.icon('lantern');
    }

    /* ---------- Start ---------- */

    function intro() {
      var body = show('<div class="nest-intro">' + '<div class="nest-hero" aria-hidden="true">' + creature() + '</div>' +
        '<p class="lead">' + esc(game.intro) + '</p><ul class="nest-legend">' + game.nets.map(function(n) {
          return '<li><b>' + esc(n.label) + '</b><span>' + esc(n.hint) + '</span></li>';
        }).join('') + '</ul><p class="help">' + esc(t('app_nest_goal', {goal: game.goal, total: game.animals.length})) + '</p>' +
        '<label class="chip nest-calm"><input type="checkbox" data-calm' + (calm ? ' checked' : '') + '> ' +
        esc(t('app_nest_calm')) + '</label><div class="actions"><button class="btn btn-main" data-go>' +
        esc(t('app_nest_start')) + '</button></div></div>');
      body.querySelector('[data-calm]').onchange = function() {
        calm = this.checked;
      };
      body.querySelector('[data-go]').onclick = start;
    }

    /* ---------- Playing ---------- */

    function start() {
      run = {
        queue: shuffle(game.animals),
        live: [],
        events: [],
        caught: 0,
        missed: 0,
        aim: null,
        paused: false,
        last: 0,
        spawnIn: 0,
        frame: 0,
        over: false,
        lane: 0
      };
      var body = show('<div class="nest-hud"><span class="nest-shield" data-shield></span><span class="nest-count" ' +
        'data-count></span>' + (calm ? '' : '<button class="btn btn-soft icon-btn game-pause" data-pause aria-pressed="false" aria-label="' +
        esc(t('app_nest_pause')) + '" title="' + esc(t('app_nest_pause')) + '">' + T.pauseIcon(false) + '</button>') + '</div><div class="nest-arena" data-arena><div class="nest-home" aria-hidden="true">' + creature() +
        '</div><div class="nest-say" data-say aria-live="polite"></div></div><div class="nest-nets" role="group" ' +
        'aria-label="' + esc(t('app_nest_nets')) + '">' + game.nets.map(function(n, i) {
          return '<button class="btn nest-net" type="button" data-net="' + esc(n.id) + '"><small>' + (i + 1) + '</small>' +
            esc(n.label) + '</button>';
        }).join('') + '</div>');
      body.querySelectorAll('[data-net]').forEach(function(b) {
        b.onclick = function() {
          throwNet(b.dataset.net);
        };
      });
      var pause = body.querySelector('[data-pause]');
      if (pause) {
        pause.onclick = togglePause;
      }
      document.addEventListener('keydown', onKey);
      hud();
      if (calm) {
        nextTurn();
      } else {
        run.last = performance.now();
        run.frame = requestAnimationFrame(tick);
      }
    }

    function stop() {
      if (run && run.frame) {
        cancelAnimationFrame(run.frame);
        run.frame = 0;
      }
    }

    function togglePause() {
      if (!run || run.over || calm) {
        return;
      }
      run.paused = !run.paused;
      var b = layer.querySelector('[data-pause]');
      if (b) {
        // An icon, so that the counters fit on a phone; its name says what it does.
        b.innerHTML = T.pauseIcon(run.paused);
        b.setAttribute('aria-pressed', run.paused ? 'true' : 'false');
        b.setAttribute('aria-label', run.paused ? t('app_nest_resume') : t('app_nest_pause'));
        b.title = b.getAttribute('aria-label');
      }
      layer.querySelector('[data-arena]').classList.toggle('paused', run.paused);
      if (!run.paused) {
        run.last = performance.now();
        run.frame = requestAnimationFrame(tick);
      } else {
        stop();
      }
    }

    function onKey(ev) {
      if (!run || run.over) {
        return;
      }
      if (ev.key === ' ' && !calm) {
        ev.preventDefault();
        togglePause();
        return;
      }
      var n = parseInt(ev.key, 10);
      if (n >= 1 && n <= game.nets.length) {
        ev.preventDefault();
        throwNet(game.nets[n - 1].id);
        return;
      }
      if ((ev.key === 'ArrowLeft' || ev.key === 'ArrowRight') && run.live.length > 1) {
        ev.preventDefault();
        var i = run.live.indexOf(run.aim);
        i = (i + (ev.key === 'ArrowLeft' ? -1 : 1) + run.live.length) % run.live.length;
        aim(run.live[i]);
      }
    }

    function hud() {
      var shield = layer.querySelector('[data-shield]');
      var left = Math.max(0, game.shield - run.missed);
      var hearts = '';
      for (var i = 0; i < game.shield; i++) {
        hearts += '<i class="' + (i < left ? 'on' : '') + '"></i>';
      }
      shield.innerHTML = '<span class="sr-only">' + esc(t('app_nest_shield')) + ' ' + left + '/' + game.shield + '</span>' +
        hearts;
      layer.querySelector('[data-count]').textContent = t('app_nest_count', run.caught + ' / ' + game.goal);
    }

    function spawn() {
      var animal = run.queue.shift();
      if (!animal) {
        return null;
      }
      var arena = layer.querySelector('[data-arena]');
      var el = document.createElement('button');
      el.type = 'button';
      el.className = 'critter';
      el.innerHTML = '<span class="paw" aria-hidden="true"></span>' + esc(animal.label);
      var lanes = calm ? [1] : [0, 1, 2];
      var lane = lanes[run.lane++ % lanes.length];
      // Lanes far enough from the edges that a long name stays inside the field.
      el.style.left = (calm ? 50 : 24 + lane * 26) + '%';
      arena.appendChild(el);
      var live = {animal: animal, el: el, y: 0, done: false};
      el.onclick = function() {
        aim(live);
      };
      run.live.push(live);
      if (!run.aim || run.aim.done) {
        aim(live);
      }
      place(live);
      return live;
    }

    function place(live) {
      live.el.style.top = (4 + live.y * 66) + '%';
    }

    function aim(live) {
      run.live.forEach(function(l) {
        l.el.classList.toggle('aimed', l === live);
        l.el.setAttribute('aria-pressed', l === live ? 'true' : 'false');
      });
      run.aim = live;
    }

    function lowest() {
      var best = null;
      run.live.forEach(function(l) {
        if (!best || l.y > best.y) {
          best = l;
        }
      });
      return best;
    }

    function tick(now) {
      if (!run || run.over || run.paused) {
        return;
      }
      var dt = Math.min(0.1, (now - run.last) / 1000);
      run.last = now;
      // With «no hurry», the animals come down more slowly.
      var fall = FALL[Math.max(0, Math.min(3, game.speed - 1))] * (T.adapted.notime ? 1.6 : 1);
      run.spawnIn -= dt;
      if (run.spawnIn <= 0 && run.live.length < AT_ONCE && run.queue.length) {
        spawn();
        run.spawnIn = fall / 2.6;
      }
      run.live.slice().forEach(function(l) {
        l.y += dt / fall;
        place(l);
        if (l.y >= 1) {
          slip(l, '');
        }
      });
      if (!run.over) {
        if (!run.live.length && !run.queue.length) {
          finish();
          return;
        }
        run.frame = requestAnimationFrame(tick);
      }
    }

    function nextTurn() {
      if (run.over) {
        return;
      }
      if (!run.queue.length) {
        finish();
        return;
      }
      var l = spawn();
      l.y = 0.42;
      place(l);
    }

    function remove(l) {
      l.done = true;
      l.el.classList.remove('aimed');
      l.el.disabled = true;
      run.live = run.live.filter(function(x) {
        return x !== l;
      });
      setTimeout(function() {
        l.el.remove();
      }, 700);
      if (run.aim === l) {
        var next = lowest();
        if (next) {
          aim(next);
        } else {
          run.aim = null;
        }
      }
    }

    function say(html, good) {
      var box = layer.querySelector('[data-say]');
      box.className = 'nest-say show ' + (good ? 'good' : 'bad');
      box.innerHTML = html;
      clearTimeout(box.timer);
      if (!calm) {
        box.timer = setTimeout(function() {
          box.className = 'nest-say';
        }, 2600);
      }
    }

    function throwNet(net) {
      if (!run || run.over || run.paused) {
        return;
      }
      var l = run.aim && !run.aim.done ? run.aim : lowest();
      if (!l || (calm && run.waiting)) {
        return;
      }
      var right = l.animal.net;
      run.events.push({a: l.animal.id, net: net});
      if (net === right) {
        run.caught++;
        l.el.classList.add('caught');
        say('<b>' + esc(t('app_nest_caught', l.animal.label)) + '</b> ' + esc(netLabel(right)) + '.', true);
        remove(l);
      } else {
        slip(l, net, true);
        return;
      }
      hud();
      after();
    }

    /**
     * An animal gets past: with a wrong net, or because nobody threw one.
     *
     * @param {Object} l
     * @param {string} net The wrong net, or empty.
     * @param {boolean} recorded The event is already kept.
     */
    function slip(l, net, recorded) {
      if (l.done) {
        return;
      }
      if (!recorded) {
        run.events.push({a: l.animal.id, net: ''});
      }
      run.missed++;
      l.el.classList.add('slipped');
      var why = l.animal.why || netHint(l.animal.net);
      say('<b>' + esc(t(net ? 'app_nest_wrong' : 'app_nest_escaped', {animal: l.animal.label,
        net: netLabel(l.animal.net)})) + '</b> ' + esc(why), false);
      var home = layer.querySelector('.nest-home');
      home.classList.remove('hit');
      // Restart the animation of the hit.
      void home.offsetWidth;
      home.classList.add('hit');
      remove(l);
      hud();
      after();
    }

    function after() {
      if (run.missed >= game.shield) {
        finish();
        return;
      }
      if (calm) {
        run.waiting = true;
        var arena = layer.querySelector('[data-arena]');
        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'btn btn-main nest-next';
        next.textContent = run.queue.length ? t('app_continue') : t('app_nest_end');
        arena.appendChild(next);
        next.focus();
        next.onclick = function() {
          next.remove();
          run.waiting = false;
          layer.querySelector('[data-say]').className = 'nest-say';
          nextTurn();
        };
      }
    }

    /* ---------- The end ---------- */

    function finish() {
      if (run.over) {
        return;
      }
      run.over = true;
      stop();
      document.removeEventListener('keydown', onKey);
      show('<p class="help">' + esc(t('app_saving')) + '</p>');
      T.api('play_minigame', {courseid: cfg.courseid, game: game.code, events: JSON.stringify(run.events)})
        .then(function(result) {
          world = result.world;
          ending(result);
        }).catch(function(e) {
          var events = JSON.stringify(run.events);
          if ((e.network || e.retry) && T.keepPlay && T.keepPlay({kind: 'minigame', game: game.code, title: game.title || '',
            events: events, at: Date.now()})) {
            // No connection: what was played is kept on the device and goes by itself when the connection comes back.
            show('<div class="conseq good"><span class="what">' + esc(t('app_offline_kept')) + '</span>' +
              esc(t('app_offline_kept_help')) + '</div><div class="actions"><button class="btn btn-main" data-back>' +
              esc(t('app_backtomap')) + '</button></div>').querySelector('[data-back]').onclick = close;
            return;
          }
          // It could not be kept either: it can be sent again from here, without playing it again.
          var body = show('<p>' + esc(e.message) + '</p><div class="actions"><button class="btn btn-main" data-again>' +
            esc(t('app_retry')) + '</button><button class="btn btn-soft" data-back>' + esc(t('app_backtomap')) +
            '</button></div>');
          body.querySelector('[data-back]').onclick = close;
          body.querySelector('[data-again]').onclick = function() {
            run.over = false;
            finish();
          };
        });
    }

    function ending(result) {
      var gifts = result.items.map(function(name) {
        return '<li>' + T.icon('gems') + esc(t('app_gotitem', name)) + '</li>';
      }).join('');
      var html = '<div class="nest-end ' + (result.success ? 'won' : 'lost') + '"><div class="nest-hero" aria-hidden="true">' +
        creature() + '</div><h2>' + esc(result.success ? t('app_nest_won') : t('app_nest_lost')) + '</h2><p class="lead">' +
        esc(result.success ? game.win : game.lose) + '</p><p class="help">' + esc(t('app_nest_score',
        {caught: result.caught, total: game.animals.length, goal: result.goal})) + '</p>' + (gifts ? '<ul class="gifts">' + gifts + '</ul>' : '');
      if (result.coat && world.character && world.character.creature) {
        html += coatChooser();
      }
      html += '<div class="actions">' + (result.success ? '' : '<button class="btn btn-main" data-again>' +
        esc(t('app_tryagain')) + '</button>') + '<button class="btn ' + (result.success ? 'btn-main' : 'btn-soft') +
        '" data-back>' + esc(t('app_backtomap')) + '</button></div></div>';
      var body = show(html);
      body.querySelector('[data-back]').onclick = close;
      var again = body.querySelector('[data-again]');
      if (again) {
        again.onclick = intro;
      }
      bindCoat(body);
    }

    function coatChooser() {
      return '<div class="coat-choice"><h3>' + esc(t('app_coat_choose')) + '</h3><div class="pills">' +
        ['fur', 'feathers', 'scales'].map(function(coat) {
          return '<button class="choice coat" type="button" data-coat="' + coat + '">' + coatPreview(coat) + '<strong>' +
            esc(t('app_coat_' + coat)) + '</strong></button>';
        }).join('') + '</div></div>';
    }

    function coatPreview(coat) {
      var c = world.character || {};
      var traits = {};
      try {
        traits = JSON.parse(c.creature || '{}') || {};
      } catch (e) {
        traits = {};
      }
      traits.coat = coat;
      return T.creature(traits, c.creaturestage);
    }

    function bindCoat(body) {
      body.querySelectorAll('[data-coat]').forEach(function(b) {
        b.onclick = function() {
          T.api('set_coat', {courseid: cfg.courseid, coat: b.dataset.coat}).then(function(w) {
            world = w;
            body.querySelectorAll('[data-coat]').forEach(function(o) {
              o.setAttribute('aria-pressed', o === b);
            });
            T.toast(esc(t('app_coat_done')));
          }).catch(function(e) {
            T.toast(esc(e.message), true);
          });
        };
      });
    }

    show('<p class="help">' + esc(t('app_loading')) + '</p>');
    T.api('get_minigame', {courseid: cfg.courseid, game: opts.code}).then(function(g) {
      game = g;
      intro();
    }).catch(function(e) {
      show('<p>' + esc(e.message) + '</p>');
    });
  };

  /**
   * The coat chooser outside the game, for the character sheet.
   *
   * @param {Object} world
   * @param {function} done Called with the new world.
   * @return {Object} html and bind(element).
   */
  T.coatPicker = function(world, done) {
    var c = world.character || {};
    var traits = {};
    try {
      traits = JSON.parse(c.creature || '{}') || {};
    } catch (e) {
      traits = {};
    }
    var html = '<div class="pills coat-picker">' + ['fur', 'feathers', 'scales', ''].map(function(coat) {
      return '<button class="chip" type="button" data-coatpick="' + coat + '" aria-pressed="' +
        ((traits.coat || '') === coat) + '">' + esc(t('app_coat_' + (coat || 'none'))) + '</button>';
    }).join('') + '</div>';
    return {
      html: html,
      bind: function(el) {
        el.querySelectorAll('[data-coatpick]').forEach(function(b) {
          b.onclick = function() {
            T.api('set_coat', {courseid: cfg.courseid, coat: b.dataset.coatpick}).then(done).catch(function(e) {
              T.toast(esc(e.message), true);
            });
          };
        });
      }
    };
  };
})();
