/**
 * Tesapé: the burst of the Embrollo. Micro-challenges of one word, one after the other and a little faster each time
 * something goes right: «Alive!» (is this thing alive?), «Group!» (the group of a being), «Think!» (a question of the
 * battle). Three misses and it is over; at the end the Embrollo throws three things at once, to touch those that are
 * alive. There is a mode without hurry, with no clock. The server counts it all again.
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

  /** The clock goes faster with each right answer in a row, down to this part of its time. */
  var FASTEST = 0.7;

  /** Milliseconds the answer stays on screen before the next one. */
  var PAUSE = 1100;

  /**
   * Plays a burst.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playBurst = function(opts) {
    var m = T.minigame(opts, 'burst', function(g) {
      game = g;
      intro();
    });
    var layer = m.layer;
    var show = m.show;
    var sound = m.sound;
    var game = null;
    var run = null;
    var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    m.onclose = function() {
      stop();
    };

    function stop() {
      if (run && run.timer) {
        clearTimeout(run.timer);
        run.timer = null;
      }
    }

    /* ---------- Start ---------- */

    function intro() {
      // Of the passives of the roles, only that of the protector acts here: a miss more.
      var body = show('<div class="burst-intro">' + m.opening('<span class="burst-word big" aria-hidden="true">' +
        esc(t('app_burst_shout')) + '</span>') + '<p class="help">' + esc(t('app_burst_goal', {n: game.burst.length,
        misses: game.misses})) + '</p>' + (game.passive === 'protector' ? m.passiveNote() : '') + '<label class="chip ' +
        'nest-calm"><input type="checkbox" data-calm' + (calm ? ' checked' : '') + '> ' + esc(t('app_whack_calm')) +
        '</label><div class="actions"><button class="btn btn-main" data-go>' + esc(t('app_whoami_start')) +
        '</button></div></div>');
      body.querySelector('[data-calm]').onchange = function() {
        calm = this.checked;
      };
      body.querySelector('[data-go]').onclick = start;
      body.querySelector('[data-go]').focus();
    }

    function start() {
      run = {events: [{n: game.seed}], i: 0, misses: 0, right: 0, streak: 0, over: false, picked: [], missed: []};
      m.begin();
      next();
    }

    /* ---------- One micro-challenge ---------- */

    function next() {
      stop();
      var item = game.burst[run.i];
      if (!item || run.misses >= game.misses) {
        finish();
        return;
      }
      run.answered = false;
      run.picked = [];
      var seconds = item.seconds * Math.max(FASTEST, 1 - 0.06 * run.streak);
      var hearts = '';
      for (var h = 0; h < game.misses; h++) {
        hearts += '<span class="burst-miss' + (h < run.misses ? ' gone' : '') + '"></span>';
      }
      var thing = item.picture || item.label ? '<div class="burst-thing">' + T.thing(item.picture, T.cap(item.label)) + '</div>' : '';
      var answers = item.kind === 'boss' ? '<div class="burst-three" role="group" aria-label="' + esc(item.prompt) + '">' +
        item.things.map(function(x) {
          return '<button type="button" class="burst-pick" data-pick="' + esc(x.id) + '" aria-pressed="false">' +
            T.thing(x.picture, T.cap(x.label)) + '</button>';
        }).join('') + '</div><div class="actions"><button type="button" class="btn btn-main" data-bossdone>' +
        esc(t('app_lane_done')) + '</button></div>' : '<div class="burst-options' + (item.options.length > 2 ? ' many' : '') +
        '">' + item.options.map(function(o, k) {
          return '<button type="button" class="btn btn-soft burst-opt" data-a="' + k + '">' + esc(o) + '</button>';
        }).join('') + '</div>';
      var body = show('<div class="burst-hud"><span class="kicker">' + esc(t('app_burst_count', {n: run.i + 1,
        all: game.burst.length})) + '</span><span class="burst-misses" role="img" aria-label="' + esc(t('app_burst_misses', {n: run.misses,
        all: game.misses})) + '">' + hearts + '</span></div><div class="burst-card ' + esc(item.kind) + '">' +
        '<div class="burst-word" aria-hidden="true">' + esc(item.word) + '</div>' + thing + '<p class="burst-prompt" ' +
        'aria-live="polite">' + esc(item.prompt) + '</p>' + answers + (calm ? '' : '<div class="burst-clock" aria-hidden="true">' +
        '<i style="animation-duration:' + seconds.toFixed(2) + 's"></i></div>') + '<div class="burst-say" data-say ' +
        'aria-live="polite"></div></div>');
      body.querySelectorAll('[data-a]').forEach(function(b) {
        b.onclick = function() {
          answer(Number(b.dataset.a));
        };
      });
      body.querySelectorAll('[data-pick]').forEach(function(b) {
        b.onclick = function() {
          if (run.answered) {
            return;
          }
          var at = run.picked.indexOf(b.dataset.pick);
          if (at >= 0) {
            run.picked.splice(at, 1);
          } else {
            run.picked.push(b.dataset.pick);
          }
          b.setAttribute('aria-pressed', at < 0);
        };
      });
      var boss = body.querySelector('[data-bossdone]');
      if (boss) {
        boss.onclick = function() {
          answer(0);
        };
      }
      var first = body.querySelector('[data-a], [data-pick]');
      if (first) {
        first.focus();
      }
      if (!calm) {
        run.timer = setTimeout(function() {
          answer(-1);
        }, seconds * 1000);
      }
    }

    /**
     * An answer, or the time out (-1): right or a miss, what was right shown a moment, and the next one.
     *
     * @param {number} a
     */
    function answer(a) {
      var item = game.burst[run.i];
      if (run.answered || !item) {
        return;
      }
      run.answered = true;
      stop();
      var ok;
      if (item.kind === 'boss') {
        var should = item.things.filter(function(x) {
          return x.alive;
        }).map(function(x) {
          return x.id;
        });
        ok = a >= 0 && should.length === run.picked.length && should.every(function(id) {
          return run.picked.indexOf(id) >= 0;
        });
        run.events.push({i: run.i, picked: a >= 0 ? run.picked.slice() : []});
        layer.querySelectorAll('[data-pick]').forEach(function(b) {
          b.disabled = true;
          b.classList.add(should.indexOf(b.dataset.pick) >= 0 ? 'right' : 'notalive');
        });
      } else {
        ok = a === item.right;
        run.events.push({i: run.i, a: a});
        layer.querySelectorAll('[data-a]').forEach(function(b) {
          b.disabled = true;
          if (Number(b.dataset.a) === item.right) {
            b.classList.add('right');
          } else if (Number(b.dataset.a) === a) {
            b.classList.add('wrong');
          }
        });
      }
      if (ok) {
        run.right++;
        run.streak++;
        sound('right');
      } else {
        run.misses++;
        run.streak = 0;
        run.missed.push(item);
        sound('giggle');
      }
      var say = layer.querySelector('[data-say]');
      say.className = 'burst-say ' + (ok ? 'good' : 'bad');
      say.textContent = ok ? t('app_burst_right') : a < 0 ? t('app_burst_late') : t('app_burst_wrong');
      run.i++;
      setTimeout(next, calm ? PAUSE * 1.6 : PAUSE);
    }

    /* ---------- The end ---------- */

    function finish() {
      if (run.over) {
        return;
      }
      run.over = true;
      stop();
      run.playid = run.playid || T.playId();
      m.save(run.playid, run.events, ending);
    }

    function ending(result) {
      // What went wrong, with what was right.
      var learn = run.missed.map(function(item) {
        if (item.kind === 'boss') {
          return '<li>' + esc(t('app_burst_alive_were', item.things.filter(function(x) {
            return x.alive;
          }).map(function(x) {
            return x.label;
          }).join(', '))) + '</li>';
        }
        return '<li><b>' + esc(item.label ? T.cap(item.label) : item.prompt) + ':</b> ' + esc(item.options[item.right]) + '</li>';
      }).join('');
      m.ending(result, {won: t('app_burst_won'), lost: t('app_burst_lost', run.i),
        art: '<span class="burst-word big' + (result.success ? ' beaten' : '') + '" aria-hidden="true">' +
          esc(t('app_burst_shout')) + '</span>', learn: learn, again: again});
    }

    function again() {
      show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_loading')) + '</div>');
      T.api('get_minigame', {courseid: cfg.courseid, game: game.code}).then(function(g) {
        m.game = g;
        game = g;
        intro();
      }).catch(function(e) {
        show('<p>' + esc(e.message) + '</p>');
      });
    }
  };
})();
