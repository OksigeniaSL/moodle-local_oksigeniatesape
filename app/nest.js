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

  /**
   * Plays Defensa del nido.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playNest = function(opts) {
    var m = T.minigame(opts, 'nest', function(g) {
      game = g;
      intro();
    });
    var layer = m.layer;
    var show = m.show;
    m.onclose = function() {
      stop();
      unfield();
      document.removeEventListener('keydown', onKey);
    };
    var game = null;
    var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var run = null;
    // The field drawn by the engine (nestworld.js), where it can be; without it, the field of the page.
    var field = null;

    function unfield() {
      if (field) {
        field.close();
        field = null;
      }
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
      var c = m.world.character || {};
      return c.creature ? T.creature(c.creature, c.creaturestage) : T.icon('lantern');
    }

    /* ---------- Start ---------- */

    function intro() {
      var body = show('<div class="nest-intro">' + m.opening('<div class="nest-hero" aria-hidden="true">' + creature() +
        '</div>') + '<ul class="nest-legend">' + game.nets.map(function(n) {
          return '<li><b>' + esc(n.label) + '</b><span>' + esc(n.hint) + '</span></li>';
        }).join('') + '</ul><p class="help">' + esc(t('app_nest_goal', {goal: game.goal, total: game.animals.length})) + '</p>' +
        m.passiveNote() + '<label class="chip nest-calm"><input type="checkbox" data-calm' + (calm ? ' checked' : '') + '> ' +
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
        queue: T.shuffle(game.animals),
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
      m.begin();
      var body = show('<div class="nest-hud"><span class="nest-shield" data-shield></span><span class="nest-count" ' +
        'data-count></span>' + m.lookButton() + (calm ? '' : m.pauseButton()) + '</div>' + m.aheadSlot() +
        '<div class="nest-arena" data-arena><div class="nest-home" aria-hidden="true">' + creature() +
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
      // The look of the sage: the net of the animal aimed at shines until it is thrown.
      m.bindLook(body, function() {
        var l = run.aim && !run.aim.done ? run.aim : lowest();
        var net = l ? layer.querySelector('[data-net="' + l.animal.net + '"]') : null;
        if (!net) {
          return false;
        }
        net.classList.add('hinted');
        return true;
      });
      var pause = body.querySelector('[data-pause]');
      if (pause) {
        pause.onclick = togglePause;
      }
      document.addEventListener('keydown', onKey);
      hud();
      if (T.nestWorld && T.nestWorld.can()) {
        var arena = layer.querySelector('[data-arena]');
        T.nestWorld.open(arena, {world: m.world, place: game.place || 'bosque'}).then(function(f) {
          if (!run || run.over || !arena.isConnected) {
            f.close();
            return;
          }
          field = f;
          // Those already coming down, and the one aimed at.
          run.live.forEach(function(l) {
            f.add(l);
          });
          f.aim(run.aim);
          // For the tests of the browser.
          layer.nestField = f;
        }).catch(function() {
          // The field of the page, as always.
        });
      }
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
      m.setPaused(run.paused);
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
      // Space pauses, except on a button, where it presses the button (a net, for who plays with the keyboard).
      if (ev.key === ' ' && !calm && !/^(BUTTON|INPUT|SELECT|TEXTAREA)$/.test(ev.target.tagName)) {
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
      // The warmth of the nest of the creature: each animal that gets in takes some of it.
      var shield = layer.querySelector('[data-shield]');
      var left = Math.max(0, game.shield - missed());
      shield.innerHTML = '<small aria-hidden="true">' + esc(t('app_nest_warmth')) + '</small><span class="meter warmth" ' +
        'role="img" aria-label="' + esc(t('app_nest_warmth_left', {left: left, all: game.shield})) + '"><i style="width:' +
        Math.round(100 * left / game.shield) + '%"></i></span>';
      layer.querySelector('[data-count]').textContent = t('app_nest_count', (run.caught + m.flow.forgiven) + ' / ' + game.goal);
    }

    /**
     * The animals that got in, but the one the healer took out.
     *
     * @return {number}
     */
    function missed() {
      return run.missed - m.flow.forgiven;
    }

    function spawn() {
      var animal = run.queue.shift();
      if (!animal) {
        return null;
      }
      m.ahead(m.coming(run.queue.map(function(q) {
        return q.label;
      })));
      var arena = layer.querySelector('[data-arena]');
      var el = document.createElement('button');
      el.type = 'button';
      el.className = 'critter';
      // Its own drawing, when the art pack paints it (never the card of its group: that would give the net away).
      var pic = T.artUrl([T.nestWorld ? T.nestWorld.drawing(animal.label) : '', 'being.' + animal.id]);
      el.innerHTML = (pic ? '<img class="critter-pic" src="' + esc(pic) + '" alt="">' : '<span class="paw" aria-hidden="true">' +
        '</span>') + esc(animal.label);
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
      place(live);
      if (field) {
        field.add(live);
      }
      if (!run.aim || run.aim.done) {
        aim(live);
      }
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
      if (field) {
        field.aim(live);
      }
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
      var fall = FALL[m.notch(Math.min(3, game.speed - 1))] * (T.adapted.notime ? 1.6 : 1);
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
      layer.querySelectorAll('.nest-net.hinted').forEach(function(b) {
        b.classList.remove('hinted');
      });
      if (net === right) {
        run.caught++;
        m.sound('right');
        m.answer(true);
        l.el.classList.add('caught');
        if (field) {
          field.caught(l);
        }
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
      m.sound('near');
      m.answer(false);
      l.el.classList.add('slipped');
      if (field) {
        field.slipped(l, !!net);
      }
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
      if (missed() >= game.shield) {
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
      run.playid = run.playid || T.playId();
      stop();
      // The last net lands before the field goes.
      var going = field;
      field = null;
      if (going) {
        setTimeout(going.close, 900);
      }
      document.removeEventListener('keydown', onKey);
      m.save(run.playid, run.events, ending);
    }

    function ending(result) {
      var body = m.ending(result, {won: t('app_nest_won'), lost: t('app_nest_lost'), again: intro,
        art: '<div class="nest-hero" aria-hidden="true">' + creature() + '</div>',
        more: result.coat && m.world.character && m.world.character.creature ? coatChooser() : ''});
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
      var c = m.world.character || {};
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
            m.world = w;
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
