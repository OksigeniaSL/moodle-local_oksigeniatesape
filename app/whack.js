/**
 * Tesapé: Who is alive? Living things and things that are not come out of the holes of the clearing for a moment; the
 * student touches only what is alive. A thing touched that is not alive is a slip, with its why; a living thing let go
 * hides again. There is a mode without hurry, one thing at a time with two answers, and a pause. At the end, what was
 * mixed up, with its why. The server counts it all again.
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

  /** Seconds a thing stays out of its hole, by speed. */
  var STAY = [3.6, 3.0, 2.5, 2.1];

  /** Holes of the clearing. */
  var HOLES = 6;

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
   * A text with its first letter in capitals, to start a sentence with the name of a thing.
   *
   * @param {string} text
   * @return {string}
   */
  function cap(text) {
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
  }

  /**
   * Plays Who is alive?
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playWhack = function(opts) {
    var layer = document.createElement('div');
    layer.className = 'scene-layer whack-layer';
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
      if (window.speechSynthesis) {
        window.speechSynthesis.cancel();
      }
      layer.remove();
      document.body.classList.remove('in-scene');
      opts.onclose(world);
    }

    function show(html) {
      layer.innerHTML = '<div class="scene whack"><div class="scene-top"><span class="scene-title">' +
        esc(game ? game.title : '') + '</span><button class="btn btn-soft scene-close" type="button" aria-label="' +
        esc(t('app_close')) + '">✕</button></div><div class="scene-body">' + html + '</div></div>';
      layer.querySelector('.scene-close').onclick = close;
      layer.scrollTop = 0;
      return layer.querySelector('.scene-body');
    }

    function sound(name) {
      if (T.fx) {
        T.fx.sound(name);
      }
    }

    function alive() {
      return game.things.filter(function(x) {
        return x.alive;
      }).length;
    }

    /**
     * Why a thing is alive or not: its own why, or the three things every living thing does.
     *
     * @param {Object} thing
     * @return {string}
     */
    function why(thing) {
      return thing.why || t(thing.alive ? 'app_whack_why_alive' : 'app_whack_why_not');
    }

    /* ---------- Start ---------- */

    function intro() {
      var body = show('<div class="whack-intro"><span class="battle-mini" aria-hidden="true">' + T.embrollo(1) + '</span>' +
        '<p class="lead">' + esc(game.intro) + '</p><p class="help">' + esc(t('app_whack_goal', {goal: game.goal,
        alive: alive(), misses: game.misses})) + '</p><label class="chip nest-calm"><input type="checkbox" data-calm' +
        (calm ? ' checked' : '') + '> ' + esc(t('app_whack_calm')) + '</label><div class="actions">' +
        '<button class="btn btn-main" data-go>' + esc(t('app_whack_start')) + '</button></div></div>');
      body.querySelector('[data-calm]').onchange = function() {
        calm = this.checked;
      };
      body.querySelector('[data-go]').onclick = start;
    }

    /* ---------- Playing ---------- */

    function start() {
      run = {
        queue: shuffle(game.things),
        out: [],
        events: [],
        mixed: [],
        caught: 0,
        slips: 0,
        paused: false,
        last: 0,
        spawnIn: 0.6,
        frame: 0,
        over: false
      };
      var holes = '';
      for (var i = 0; i < HOLES; i++) {
        holes += '<div class="whack-hole" data-hole="' + i + '"><small aria-hidden="true">' + (i + 1) + '</small></div>';
      }
      var body = show('<div class="nest-hud"><span class="whack-slips" data-slips></span><span class="nest-count" ' +
        'data-count></span>' + (calm ? '' : '<button class="btn btn-soft" data-pause>' + esc(t('app_nest_pause')) +
        '</button>') + '</div><div class="whack-arena' + (calm ? ' calm' : '') + '" data-arena role="group" aria-label="' +
        esc(t('app_whack_holes')) + '">' + (calm ? '<div class="whack-one" data-one></div>' : holes) +
        '<div class="nest-say" data-say aria-live="polite"></div></div>');
      var pause = body.querySelector('[data-pause]');
      if (pause) {
        pause.onclick = togglePause;
      }
      document.addEventListener('keydown', onKey);
      hud();
      if (calm) {
        nextOne();
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
        b.textContent = run.paused ? t('app_nest_resume') : t('app_nest_pause');
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
      if (!run || run.over || calm) {
        return;
      }
      if (ev.key === ' ') {
        ev.preventDefault();
        togglePause();
        return;
      }
      var n = parseInt(ev.key, 10);
      if (n >= 1 && n <= HOLES) {
        ev.preventDefault();
        var o = run.out.filter(function(x) {
          return x.hole === n - 1;
        })[0];
        if (o) {
          touch(o);
        }
      }
    }

    function hud() {
      var drops = '';
      for (var i = 0; i < game.misses; i++) {
        drops += '<i class="' + (i < run.slips ? 'slip' : '') + '" aria-hidden="true"></i>';
      }
      var slips = t('app_whack_slips', {slips: run.slips, misses: game.misses});
      layer.querySelector('[data-slips]').innerHTML = '<span class="sr-only">' + esc(slips) + '</span>' + drops;
      layer.querySelector('[data-slips]').title = slips;
      layer.querySelector('[data-count]').textContent = t('app_whack_count', run.caught + ' / ' + game.goal);
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

    /** A thing comes out of a free hole. */
    function spawn() {
      var thing = run.queue.shift();
      var taken = run.out.map(function(o) {
        return o.hole;
      });
      var free = [];
      for (var i = 0; i < HOLES; i++) {
        if (taken.indexOf(i) < 0) {
          free.push(i);
        }
      }
      var hole = free[Math.floor(Math.random() * free.length)];
      var el = document.createElement('button');
      el.type = 'button';
      el.className = 'whack-thing';
      el.textContent = thing.label;
      el.setAttribute('aria-label', t('app_whack_hole', hole + 1) + ': ' + thing.label);
      layer.querySelector('[data-hole="' + hole + '"]').appendChild(el);
      var o = {thing: thing, el: el, hole: hole, left: stay(), done: false};
      el.onclick = function() {
        touch(o);
      };
      run.out.push(o);
      // On the next frame, so that it comes up with its animation.
      requestAnimationFrame(function() {
        el.classList.add('up');
      });
    }

    function stay() {
      // With «no hurry», the things stay out longer.
      return STAY[Math.max(0, Math.min(3, game.speed - 1))] * (T.adapted.notime ? 1.6 : 1);
    }

    function tick(now) {
      if (!run || run.over || run.paused) {
        return;
      }
      var dt = Math.min(0.1, (now - run.last) / 1000);
      run.last = now;
      run.spawnIn -= dt;
      var most = game.speed >= 3 ? 3 : 2;
      if (run.spawnIn <= 0 && run.out.length < most && run.queue.length) {
        spawn();
        run.spawnIn = stay() * 0.55;
      }
      run.out.slice().forEach(function(o) {
        o.left -= dt;
        if (o.left <= 0) {
          hide(o);
        }
      });
      if (!run.over) {
        if (!run.out.length && !run.queue.length) {
          finish();
          return;
        }
        run.frame = requestAnimationFrame(tick);
      }
    }

    function away(o, how) {
      o.done = true;
      o.el.disabled = true;
      o.el.classList.remove('up');
      o.el.classList.add(how);
      run.out = run.out.filter(function(x) {
        return x !== o;
      });
      setTimeout(function() {
        o.el.remove();
      }, 600);
    }

    /**
     * A thing touched: one more living thing in the notebook, or a slip.
     *
     * @param {Object} o
     */
    function touch(o) {
      if (!run || run.over || run.paused || o.done) {
        return;
      }
      run.events.push({i: o.thing.id});
      if (o.thing.alive) {
        run.caught++;
        sound('right');
        say('<b>' + esc(cap(t('app_whack_alive', o.thing.label))) + '</b>' + (o.thing.why ? ' ' + esc(o.thing.why) : ''),
          true);
        away(o, 'caught');
      } else {
        run.slips++;
        run.mixed.push(o.thing);
        sound('wrong');
        say('<b>' + esc(cap(t('app_whack_notalive', o.thing.label))) + '</b> ' + esc(why(o.thing)), false);
        away(o, 'slipped');
      }
      hud();
      if (run.slips >= game.misses) {
        finish();
      }
    }

    /**
     * A thing goes back into its hole without being touched: right if it was not alive.
     *
     * @param {Object} o
     */
    function hide(o) {
      if (o.thing.alive) {
        run.mixed.push(o.thing);
        say('<b>' + esc(cap(t('app_whack_hid', o.thing.label))) + '</b> ' + esc(why(o.thing)), false);
      }
      away(o, 'gone');
    }

    /* ---------- Without hurry: one thing at a time ---------- */

    function nextOne() {
      if (run.over) {
        return;
      }
      if (!run.queue.length) {
        finish();
        return;
      }
      var thing = run.queue.shift();
      var reads = T.adapted.readaloud && T.readAloud && window.speechSynthesis;
      var box = layer.querySelector('[data-one]');
      box.innerHTML = '<div class="whack-thing up" data-thing>' + esc(thing.label) + '</div><p class="sr-only" ' +
        'aria-live="polite">' + esc(t('app_whack_comes', thing.label)) + '</p><div class="actions">' +
        (reads ? '<button type="button" class="btn btn-soft" data-read aria-label="' + esc(t('app_read_aloud')) +
        '">🔊</button>' : '') + '<button class="btn btn-main" data-yes>' + esc(t('app_whack_yes')) + '</button>' +
        '<button class="btn btn-soft" data-no>' + esc(t('app_whack_no')) + '</button></div>';
      var o = {thing: thing, el: box.querySelector('[data-thing]'), hole: -1, left: 0, done: false};
      box.querySelector('[data-yes]').onclick = function() {
        answer(o, true);
      };
      box.querySelector('[data-no]').onclick = function() {
        answer(o, false);
      };
      var read = box.querySelector('[data-read]');
      if (read) {
        read.onclick = function() {
          T.readAloud(box.querySelector('[data-thing]'));
        };
      }
      box.querySelector('[data-yes]').focus();
    }

    /**
     * An answer without hurry: «it is a living thing» touches it; «it is not» lets it go.
     *
     * @param {Object} o
     * @param {boolean} yes
     */
    function answer(o, yes) {
      if (o.done) {
        return;
      }
      o.done = true;
      var right = yes === o.thing.alive;
      if (yes) {
        run.events.push({i: o.thing.id});
        if (o.thing.alive) {
          run.caught++;
        } else {
          run.slips++;
        }
      }
      if (!right) {
        run.mixed.push(o.thing);
      }
      sound(right ? 'right' : 'wrong');
      var key = o.thing.alive ? (yes ? 'app_whack_alive' : 'app_whack_wasalive') : 'app_whack_notalive';
      say('<b>' + esc(cap(t(key, o.thing.label))) + '</b> ' + esc(why(o.thing)), right);
      hud();
      if (run.slips >= game.misses) {
        finish();
        return;
      }
      var box = layer.querySelector('[data-one]');
      box.querySelector('.actions').innerHTML = '<button class="btn btn-main" data-next>' +
        esc(run.queue.length ? t('app_continue') : t('app_nest_end')) + '</button>';
      var next = box.querySelector('[data-next]');
      next.onclick = function() {
        layer.querySelector('[data-say]').className = 'nest-say';
        nextOne();
      };
      next.focus();
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
      var events = JSON.stringify(run.events);
      T.api('play_minigame', {courseid: cfg.courseid, game: game.code, events: events}).then(function(result) {
        world = result.world;
        ending(result);
      }).catch(function(e) {
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
      });
      if (result.gems) {
        gifts.push('<li>' + T.icon('gems') + esc('+' + result.gems + ' ' + t('app_gems')) + '</li>');
      }
      // What was mixed up, once each, with its why: to read now, without hurry.
      var seen = {};
      var mixed = run.mixed.filter(function(x) {
        if (seen[x.id]) {
          return false;
        }
        seen[x.id] = true;
        return true;
      }).map(function(x) {
        return '<li><b>' + esc(cap(t(x.alive ? 'app_whack_wasalive' : 'app_whack_wasnot', x.label))) + '</b> ' +
          esc(why(x)) + '</li>';
      }).join('');
      sound(result.success ? 'win' : 'wrong');
      var body = show('<div class="nest-end ' + (result.success ? 'won' : 'lost') + '"><span class="battle-mini" ' +
        'aria-hidden="true">' + T.embrollo(result.success ? 0.2 : 1) + '</span><h2>' + esc(result.success ?
        t('app_whack_won') : t('app_whack_lost')) + '</h2><p class="lead">' + esc(result.success ? game.win : game.lose) +
        '</p><p class="help">' + esc(t('app_whack_score', {caught: result.caught, alive: alive(), goal: result.goal})) +
        '</p>' + (gifts.length ? '<ul class="gifts">' + gifts.join('') + '</ul>' : '') + (mixed ? '<div class="card ' +
        'whack-learn"><h3>' + esc(t('app_whack_learn')) + '</h3><ul>' + mixed + '</ul></div>' : '') +
        '<div class="actions">' + (result.success ? '' : '<button class="btn btn-main" data-again>' +
        esc(t('app_tryagain')) + '</button>') + '<button class="btn ' + (result.success ? 'btn-main' : 'btn-soft') +
        '" data-back>' + esc(t('app_backtomap')) + '</button></div></div>');
      body.querySelector('[data-back]').onclick = close;
      var again = body.querySelector('[data-again]');
      if (again) {
        again.onclick = intro;
      }
      if (result.success && T.fx && T.fx.confetti) {
        T.fx.confetti();
      }
    }

    show('<p class="help">' + esc(t('app_loading')) + '</p>');
    T.api('get_minigame', {courseid: cfg.courseid, game: opts.code}).then(function(g) {
      game = g;
      intro();
    }).catch(function(e) {
      show('<p>' + esc(e.message) + '</p>');
    });
  };
})();
