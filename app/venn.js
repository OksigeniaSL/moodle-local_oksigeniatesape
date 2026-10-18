/**
 * Tesapé: the two circles. The Embrollo has let loose the cards of two circles of light; the student takes each one to
 * its circle, or to the middle, where they meet, if it belongs to both. A card in the wrong place is a slip, and shows
 * where it went and why; with too many slips the circles get tangled this time. The server counts it all again.
 *
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var t = T.t;
  var esc = T.esc;

  /** The places of a card: only the first circle, both, only the second. */
  var ZONES = ['a', 'ab', 'b'];

  /**
   * Plays the two circles.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playVenn = function(opts) {
    var m = T.minigame(opts, 'venn', function(g) {
      game = g;
      intro();
    });
    var layer = m.layer;
    var show = m.show;
    var sound = m.sound;
    m.onclose = function() {
      document.removeEventListener('keydown', onKey);
    };
    var game = null;
    var run = null;

    /**
     * Where a card goes, in words: a circle, or the middle.
     *
     * @param {Object} s The sheet.
     * @param {string} zone
     * @return {string}
     */
    function place(s, zone) {
      return zone === 'ab' ? t('app_venn_centre') : '«' + s.groups[zone === 'a' ? 0 : 1] + '»';
    }

    function answer(s, zone) {
      return zone === 'ab' ? t('app_venn_both') : t('app_venn_only', s.groups[zone === 'a' ? 0 : 1]);
    }

    /* ---------- Start ---------- */

    function intro() {
      var body = show('<div class="venn-intro"><span class="battle-mini" aria-hidden="true">' + T.embrollo(1) + '</span>' +
        '<p class="lead">' + esc(game.intro) + '</p><p class="help">' + esc(t('app_venn_goal', {misses: game.misses})) +
        '</p><div class="actions"><button class="btn btn-main" data-go>' + esc(t('app_venn_start')) + '</button></div></div>');
      body.querySelector('[data-go]').onclick = start;
      body.querySelector('[data-go]').focus();
    }

    function start() {
      run = {sheet: 0, events: [], slips: 0, right: 0, mixed: [], over: false, say: null};
      document.addEventListener('keydown', onKey);
      sheet();
    }

    function sheet() {
      var s = game.sheets[run.sheet];
      run.order = T.shuffle(s.items.map(function(x, i) {
        return i;
      }));
      run.item = 0;
      run.placed = {a: [], ab: [], b: []};
      run.say = null;
      draw('', true);
    }

    function onKey(ev) {
      if (!run || run.over || ev.target.tagName === 'INPUT') {
        return;
      }
      var n = parseInt(ev.key, 10);
      if (n >= 1 && n <= 3 && run.item < run.order.length) {
        ev.preventDefault();
        put(ZONES[n - 1]);
      }
    }

    /* ---------- A sheet ---------- */

    /**
     * Draws the page: turned, when it is a new one; in place, when a card has just been put.
     *
     * @param {string} focus The answer to keep the focus on.
     * @param {boolean} turn
     */
    function draw(focus, turn) {
      var s = game.sheets[run.sheet];
      var done = run.item >= run.order.length;
      var thing = done ? null : s.items[run.order[run.item]];
      var last = run.sheet === game.sheets.length - 1;
      var drops = '';
      for (var i = 0; i < game.misses; i++) {
        drops += '<i class="' + (i < run.slips ? 'slip' : '') + '" aria-hidden="true"></i>';
      }
      var slips = t('app_venn_slips', {slips: run.slips, misses: game.misses});
      var board = '<div class="venn-names"><span class="a">' + esc(s.groups[0]) + '</span><span class="b">' +
        esc(s.groups[1]) + '</span></div><div class="venn-board"><div class="circle a"></div><div class="circle b"></div>' +
        ZONES.map(function(z) {
          // The circles are touched too; the keyboard and the screen reader use the three buttons under them.
          return '<button type="button" class="venn-zone z-' + z + '" data-zone="' + z + '"' + (done ? ' disabled' : '') +
            ' tabindex="-1" aria-hidden="true"><span class="chips">' + run.placed[z].map(function(p) {
              return '<i class="' + (p.wrong ? 'fixed' : '') + '">' + esc(p.label) + '</i>';
            }).join('') + '</span></button>';
        }).join('') + '</div>';
      var html = '<div class="nest-hud"><span class="whack-slips" title="' + esc(slips) + '"><span class="sr-only">' +
        esc(slips) + '</span>' + drops + '</span><span class="kicker">' + esc(t('app_venn_sheet', {n: run.sheet + 1,
        total: game.sheets.length})) + '</span></div>' + (s.title ? '<h2 class="venn-title">' + esc(s.title) + '</h2>' : '') +
        board + '<div class="venn-say' + (run.say ? ' ' + run.say.kind : '') + '" aria-live="polite">' +
        (run.say ? run.say.html : '') + '</div>';
      if (thing) {
        html += '<div class="venn-card"><span class="kicker">' + esc(t('app_venn_where')) + '</span><b>' +
          esc(thing.label) + '</b><small>' + esc(t('app_venn_left', run.order.length - run.item)) + '</small></div>' +
          '<div class="venn-answers">' + ZONES.map(function(z, n) {
            return '<button type="button" class="btn btn-soft" data-zone="' + z + '"><small aria-hidden="true">' + (n + 1) +
              '</small>' + esc(answer(s, z)) + '</button>';
          }).join('') + '</div>';
      } else {
        html += '<div class="actions"><button class="btn btn-main" data-next>' + esc(last ? t('app_errors_finish') :
          t('app_venn_next')) + '</button></div>';
      }
      var body = layer.querySelector('.scene-body');
      if (turn || !body) {
        body = show(html);
      } else {
        body.innerHTML = html;
      }
      body.querySelectorAll('[data-zone]').forEach(function(b) {
        b.onclick = function() {
          put(b.dataset.zone);
        };
      });
      var next = body.querySelector('[data-next]');
      if (next) {
        next.onclick = function() {
          if (last) {
            finish();
          } else {
            run.sheet++;
            sheet();
          }
        };
        next.focus();
      } else if (focus) {
        var again = body.querySelector('.venn-answers [data-zone="' + focus + '"]');
        if (again) {
          again.focus();
        }
      }
    }

    /**
     * A card taken to a place: to its circle, or a slip that shows where it goes and why.
     *
     * @param {string} zone
     */
    function put(zone) {
      if (!run || run.over || run.item >= run.order.length) {
        return;
      }
      var s = game.sheets[run.sheet];
      var i = run.order[run.item];
      var thing = s.items[i];
      run.events.push({s: s.id, i: i, z: zone});
      run.item++;
      if (zone === thing.zone) {
        run.right++;
        run.placed[zone].push({label: thing.label});
        sound('right');
        run.say = {kind: 'good', html: '<b>' + esc(T.cap(t('app_venn_right', thing.label))) + '</b>' +
          (thing.why ? ' ' + esc(T.cap(thing.why)) : '')};
      } else {
        run.slips++;
        run.mixed.push({label: thing.label, place: place(s, thing.zone), why: thing.why});
        run.placed[thing.zone].push({label: thing.label, wrong: true});
        sound('wrong');
        run.say = {kind: 'bad', html: '<b>' + esc(T.cap(t('app_venn_goes', {thing: thing.label,
          place: place(s, thing.zone)}))) + '</b>' + (thing.why ? ' ' + esc(T.cap(thing.why)) : '')};
      }
      if (run.slips >= game.misses) {
        finish();
        return;
      }
      draw(zone);
    }

    /* ---------- The end ---------- */

    function finish() {
      if (run.over) {
        return;
      }
      run.over = true;
      run.playid = run.playid || T.playId();
      document.removeEventListener('keydown', onKey);
      m.save(run.playid, run.events, ending);
    }

    function ending(result) {
      // What went to the wrong place, with where it goes and why: to read now, without hurry.
      var mixed = run.mixed.map(function(x) {
        return '<li><b>' + esc(T.cap(t('app_venn_goes', {thing: x.label, place: x.place}))) + '</b>' +
          (x.why ? ' ' + esc(T.cap(x.why)) : '') + '</li>';
      }).join('');
      m.ending(result, {won: t('app_venn_won'), lost: t('app_venn_lost'), learn: mixed, again: intro});
    }

  };
})();
