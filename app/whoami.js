/**
 * Tesapé: Who am I? A shadow of the valley gives its clues one by one, from the hardest to the easiest, and the student
 * guesses who it is among four. A wrong guess shows the next clue; with no clue left, the shadow gets away. The fewer
 * clues needed, the more stars. The server counts it all again.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var t = T.t;
  var esc = T.esc;

  /**
   * Plays Who am I?
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playWhoami = function(opts) {
    var m = T.minigame(opts, 'whoami', function(g) {
      game = g;
      intro();
    });
    var layer = m.layer;
    var show = m.show;
    var sound = m.sound;
    var game = null;
    var run = null;

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
      var body = show('<div class="whoami-intro">' + m.opening('<span class="whoami-shadow big" aria-hidden="true">?</span>') +
        '<p class="help">' + esc(t('app_whoami_goal', {misses: game.misses})) +
        '</p>' + m.passiveNote() + '<div class="actions"><button class="btn btn-main" data-go>' + esc(t('app_whoami_start')) + '</button></div>' +
        '</div>');
      body.querySelector('[data-go]').onclick = start;
      body.querySelector('[data-go]').focus();
    }

    function start() {
      run = {riddle: 0, events: [], wrong: 0, found: 0, stars: 0, over: false, lost: [], dimmed: {}};
      m.begin();
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
        run.stars)) + '"><span aria-hidden="true">★ </span>' + run.stars + '</span>' + m.lookButton() + '</div>' +
        '<section class="card whoami-card">' + (reads ? '<button type="button" class="btn btn-soft read-card" ' +
        'data-readcard aria-label="' + esc(t('app_read_aloud')) + '">' + T.icon('speak') + '</button>' : '') + '<div class="whoami-head">' +
        '<span class="whoami-shadow' + (run.done === 'found' ? ' found' : '') + '" aria-hidden="true">' +
        (run.done === 'found' ? '✓' : '?') + '</span><h2>' + esc(run.done ? T.cap(r.answer) : t('app_whoami_who')) + '</h2>' +
        '</div><ol class="whoami-clues">' + r.clues.slice(0, run.done ? r.clues.length : run.shown).map(function(c, i) {
          return '<li class="' + (i >= run.shown ? 'late' : '') + '">' + esc(c) + '</li>';
        }).join('') + (scouted(r) ? '<li class="trail">' + T.icon('trail') + '<span class="sr-only">' +
          esc(t('app_passive_seen')) + ' </span>' + esc(r.clues[run.shown]) + '</li>' : '') + '</ol></section><div class="whoami-say' + (run.say ? ' ' + run.say.kind : '') + '" aria-live="polite">' +
        (run.say ? run.say.html : '') + '</div>';
      if (!run.done) {
        var dimmed = run.dimmed[r.id] || [];
        html += '<div class="whoami-options">' + r.options.map(function(o) {
          var tried = run.tried.indexOf(o) >= 0;
          var dim = dimmed.indexOf(o) >= 0;
          return '<button type="button" class="btn btn-soft' + (tried ? ' tried' : '') + (dim ? ' dimmed' : '') + '" data-guess="' +
            esc(o) + '"' + (tried || dim ? ' disabled' : '') + '>' + esc(T.cap(o)) + '</button>';
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
      // The look of the sage: two names that are not this one go out.
      var spent = body.querySelector('[data-look]');
      if (spent && m.lookSpent()) {
        spent.disabled = true;
        spent.classList.add('spent');
      }
      m.bindLook(body, function() {
        if (run.done) {
          return false;
        }
        run.dimmed[r.id] = r.options.filter(function(o) {
          return o.toLowerCase() !== r.answer.toLowerCase() && run.tried.indexOf(o) < 0;
        }).slice(0, 2);
        riddle(false);
        return true;
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
      m.answer(name.toLowerCase() === r.answer.toLowerCase());
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
      if (run.wrong - m.flow.forgiven >= game.misses) {
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

    /**
     * Whether the scout sees the next clue already, without it counting as asked for.
     *
     * @param {Object} r
     * @return {boolean}
     */
    function scouted(r) {
      return game.passive === 'scout' && !run.done && run.shown < r.clues.length;
    }

    /* ---------- The end ---------- */

    function finish() {
      if (run.over) {
        return;
      }
      run.over = true;
      run.playid = run.playid || T.playId();
      m.save(run.playid, run.events, ending);
    }

    function ending(result) {
      // The shadows that got away, with all their clues: to read now, without hurry.
      var lost = run.lost.map(function(r) {
        return '<li><b>' + esc(T.cap(r.answer)) + '</b>: ' + esc(r.clues.join(' ')) + '</li>';
      }).join('');
      m.ending(result, {won: t('app_whoami_won'), lost: t('app_whoami_lost'), learn: lost, again: intro,
        art: '<span class="whoami-shadow big' + (result.success ? ' found' : '') + '" aria-hidden="true">' +
          (result.success ? '✓' : '?') + '</span>'});
    }

  };
})();
