/**
 * Tesapé: Who am I? A shadow of the valley gives its clues one by one, from the hardest to the easiest, and the student
 * guesses who it is among four. A wrong guess shows the next clue; with no clue left, the shadow gets away. The fewer
 * clues needed, the more stars. The server counts it all again.
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

  function cap(text) {
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
  }

  /**
   * Plays Who am I?
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playWhoami = function(opts) {
    var layer = document.createElement('div');
    layer.className = 'scene-layer whoami-layer';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    document.body.appendChild(layer);
    document.body.classList.add('in-scene');
    var world = opts.world;
    var game = null;
    var run = null;

    function close() {
      if (window.speechSynthesis) {
        window.speechSynthesis.cancel();
      }
      layer.remove();
      document.body.classList.remove('in-scene');
      opts.onclose(world);
    }

    function show(html) {
      layer.innerHTML = '<div class="scene whoami"><div class="scene-top"><span class="scene-title">' +
        esc(game ? game.title : '') + '</span><button class="btn btn-soft scene-close" type="button" aria-label="' +
        esc(t('app_close')) + '">' + T.icon('close') + '</button></div><div class="scene-body">' + html + '</div></div>';
      layer.querySelector('.scene-close').onclick = close;
      return layer.querySelector('.scene-body');
    }

    function sound(name) {
      if (T.fx) {
        T.fx.sound(name);
      }
    }

    /** Stars as a string of ★ and ☆. */
    function stars(won, of) {
      var out = '';
      for (var i = 0; i < of; i++) {
        out += i < won ? '★' : '☆';
      }
      return out;
    }

    /* ---------- Start ---------- */

    function intro() {
      var body = show('<div class="whoami-intro"><span class="whoami-shadow big" aria-hidden="true">?</span>' +
        '<p class="lead">' + esc(game.intro) + '</p><p class="help">' + esc(t('app_whoami_goal', {misses: game.misses})) +
        '</p><div class="actions"><button class="btn btn-main" data-go>' + esc(t('app_whoami_start')) + '</button></div>' +
        '</div>');
      body.querySelector('[data-go]').onclick = start;
      body.querySelector('[data-go]').focus();
    }

    function start() {
      run = {riddle: 0, events: [], wrong: 0, found: 0, stars: 0, over: false, lost: []};
      riddle(true);
    }

    function fresh() {
      var r = game.riddles[run.riddle];
      run.shown = 1;
      run.tried = [];
      run.done = '';
      run.say = null;
      return r;
    }

    /* ---------- A shadow ---------- */

    /**
     * Draws the shadow: turned, when it is a new one; in place, after a clue or a guess.
     *
     * @param {boolean} turn
     */
    function riddle(turn) {
      var r = turn ? fresh() : game.riddles[run.riddle];
      var last = run.riddle === game.riddles.length - 1;
      var left = r.clues.length - run.shown;
      var reads = T.adapted.readaloud && T.readAloud && window.speechSynthesis;
      var html = '<div class="nest-hud"><span class="kicker">' + esc(t('app_whoami_count', {n: run.riddle + 1,
        total: game.riddles.length})) + '</span><span class="whoami-stars" title="' + esc(t('app_whoami_stars',
        run.stars)) + '"><span aria-hidden="true">★ </span>' + run.stars + '</span></div>' +
        '<section class="card whoami-card">' + (reads ? '<button type="button" class="btn btn-soft read-card" ' +
        'data-readcard aria-label="' + esc(t('app_read_aloud')) + '">' + T.icon('speak') + '</button>' : '') + '<div class="whoami-head">' +
        '<span class="whoami-shadow' + (run.done === 'found' ? ' found' : '') + '" aria-hidden="true">' +
        (run.done === 'found' ? '✓' : '?') + '</span><h2>' + esc(run.done ? cap(r.answer) : t('app_whoami_who')) + '</h2>' +
        '</div><ol class="whoami-clues">' + r.clues.slice(0, run.done ? r.clues.length : run.shown).map(function(c, i) {
          return '<li class="' + (i >= run.shown ? 'late' : '') + '">' + esc(c) + '</li>';
        }).join('') + '</ol></section><div class="whoami-say' + (run.say ? ' ' + run.say.kind : '') + '" aria-live="polite">' +
        (run.say ? run.say.html : '') + '</div>';
      if (!run.done) {
        html += '<div class="whoami-options">' + r.options.map(function(o) {
          var tried = run.tried.indexOf(o) >= 0;
          return '<button type="button" class="btn btn-soft' + (tried ? ' tried' : '') + '" data-guess="' + esc(o) + '"' +
            (tried ? ' disabled' : '') + '>' + esc(cap(o)) + '</button>';
        }).join('') + '</div><div class="actions">' + (left > 0 ? '<button class="btn btn-soft" data-clue>' +
          esc(t('app_whoami_clue', left)) + '</button>' : '') + '</div>';
      } else {
        html += '<div class="actions"><button class="btn btn-main" data-next>' + esc(last ? t('app_errors_finish') :
          t('app_whoami_next')) + '</button></div>';
      }
      var body = layer.querySelector('.scene-body');
      if (turn || !body) {
        body = show(html);
      } else {
        body.innerHTML = html;
      }
      body.querySelectorAll('[data-guess]').forEach(function(b) {
        b.onclick = function() {
          guess(b.dataset.guess);
        };
      });
      var clue = body.querySelector('[data-clue]');
      if (clue) {
        clue.onclick = function() {
          run.events.push({r: r.id, c: 1});
          run.shown++;
          run.say = null;
          riddle(false);
          var again = layer.querySelector('[data-clue]') || layer.querySelector('[data-guess]:not([disabled])');
          if (again) {
            again.focus();
          }
        };
      }
      var read = body.querySelector('[data-readcard]');
      if (read) {
        read.onclick = function() {
          T.readAloud(body.querySelector('.whoami-card'));
        };
      }
      var next = body.querySelector('[data-next]');
      if (next) {
        next.onclick = function() {
          if (last) {
            finish();
          } else {
            run.riddle++;
            riddle(true);
          }
        };
        next.focus();
      }
    }

    /**
     * A guess: the shadow is found, or the next clue shows (and with none left, it gets away).
     *
     * @param {string} name
     */
    function guess(name) {
      var r = game.riddles[run.riddle];
      run.events.push({r: r.id, g: name});
      if (name.toLowerCase() === r.answer.toLowerCase()) {
        var won = r.clues.length - run.shown + 1;
        run.found++;
        run.stars += won;
        run.done = 'found';
        sound('right');
        run.say = {kind: 'good', html: '<b>' + esc(t('app_whoami_found', r.answer)) + '</b> ' +
          '<span aria-hidden="true">' + stars(won, r.clues.length) + '</span><span class="sr-only">' +
          esc(t('app_whoami_stars', won)) + '</span>'};
      } else {
        run.wrong++;
        run.tried.push(name);
        sound('wrong');
        if (run.shown >= r.clues.length) {
          run.done = 'away';
          run.lost.push(r);
          run.say = {kind: 'bad', html: '<b>' + esc(t('app_whoami_away', r.answer)) + '</b>'};
        } else {
          run.shown++;
          run.say = {kind: 'bad', html: '<b>' + esc(t('app_whoami_notme', name)) + '</b> ' +
            esc(t('app_whoami_another'))};
        }
      }
      if (run.wrong >= game.misses) {
        finish();
        return;
      }
      riddle(false);
      if (!run.done) {
        var first = layer.querySelector('[data-guess]:not([disabled])');
        if (first) {
          first.focus();
        }
      }
    }

    /* ---------- The end ---------- */

    function finish() {
      if (run.over) {
        return;
      }
      run.over = true;
      run.playid = run.playid || T.playId();
      show('<p class="help">' + esc(t('app_saving')) + '</p>');
      var events = JSON.stringify(run.events);
      T.api('play_minigame', {courseid: cfg.courseid, game: game.code, playid: run.playid, events: events}).then(function(result) {
        world = result.world;
        ending(result);
      }).catch(function(e) {
        if ((e.network || e.retry) && T.keepPlay && T.keepPlay({kind: 'minigame', game: game.code, playid: run.playid, title: game.title || '',
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
      // The shadows that got away, with all their clues: to read now, without hurry.
      var lost = run.lost.map(function(r) {
        return '<li><b>' + esc(cap(r.answer)) + '</b>: ' + esc(r.clues.join(' ')) + '</li>';
      }).join('');
      sound(result.success ? 'win' : 'wrong');
      var body = show('<div class="nest-end ' + (result.success ? 'won' : 'lost') + '"><span class="whoami-shadow big' +
        (result.success ? ' found' : '') + '" aria-hidden="true">' + (result.success ? '✓' : '?') + '</span><h2>' +
        esc(result.success ? t('app_whoami_won') : t('app_whoami_lost')) + '</h2><p class="lead">' +
        esc(result.success ? game.win : game.lose) + '</p><p class="help">' + esc(t('app_whoami_score', {found: result.caught,
        total: result.goal, stars: run.stars})) + '</p>' + (gifts.length ? '<ul class="gifts">' + gifts.join('') + '</ul>' :
        '') + (lost ? '<div class="card whack-learn"><h3>' + esc(t('app_whack_learn')) + '</h3><ul>' + lost + '</ul></div>' :
        '') + '<div class="actions">' + (result.success ? '' : '<button class="btn btn-main" data-again>' +
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

    show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_loading')) + '</div>');
    T.api('get_minigame', {courseid: cfg.courseid, game: opts.code}).then(function(g) {
      game = g;
      intro();
    }).catch(function(e) {
      show('<p>' + esc(e.message) + '</p>');
    });
  };
})();
