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
  var cfg = T.cfg;
  var t = T.t;
  var esc = T.esc;

  /** The places of a card: only the first circle, both, only the second. */
  var ZONES = ['a', 'ab', 'b'];

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

  function cap(text) {
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
  }

  /**
   * Plays the two circles.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playVenn = function(opts) {
    var layer = document.createElement('div');
    layer.className = 'scene-layer venn-layer';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    document.body.appendChild(layer);
    document.body.classList.add('in-scene');
    var world = opts.world;
    var game = null;
    var run = null;

    function close() {
      document.removeEventListener('keydown', onKey);
      if (window.speechSynthesis) {
        window.speechSynthesis.cancel();
      }
      layer.remove();
      document.body.classList.remove('in-scene');
      opts.onclose(world);
    }

    function show(html) {
      layer.innerHTML = '<div class="scene venn"><div class="scene-top"><span class="scene-title">' +
        esc(game ? game.title : '') + '</span><button class="btn btn-soft scene-close" type="button" aria-label="' +
        esc(t('app_close')) + '">✕</button></div><div class="scene-body">' + html + '</div></div>';
      layer.querySelector('.scene-close').onclick = close;
      return layer.querySelector('.scene-body');
    }

    function sound(name) {
      if (T.fx) {
        T.fx.sound(name);
      }
    }

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
      run.order = shuffle(s.items.map(function(x, i) {
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
        run.say = {kind: 'good', html: '<b>' + esc(cap(t('app_venn_right', thing.label))) + '</b>' +
          (thing.why ? ' ' + esc(cap(thing.why)) : '')};
      } else {
        run.slips++;
        run.mixed.push({label: thing.label, place: place(s, thing.zone), why: thing.why});
        run.placed[thing.zone].push({label: thing.label, wrong: true});
        sound('wrong');
        run.say = {kind: 'bad', html: '<b>' + esc(cap(t('app_venn_goes', {thing: thing.label,
          place: place(s, thing.zone)}))) + '</b>' + (thing.why ? ' ' + esc(cap(thing.why)) : '')};
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
      // What went to the wrong place, with where it goes and why: to read now, without hurry.
      var mixed = run.mixed.map(function(x) {
        return '<li><b>' + esc(cap(t('app_venn_goes', {thing: x.label, place: x.place}))) + '</b>' +
          (x.why ? ' ' + esc(cap(x.why)) : '') + '</li>';
      }).join('');
      sound(result.success ? 'win' : 'wrong');
      var body = show('<div class="nest-end ' + (result.success ? 'won' : 'lost') + '"><span class="battle-mini" ' +
        'aria-hidden="true">' + T.embrollo(result.success ? 0.2 : 1) + '</span><h2>' + esc(result.success ?
        t('app_venn_won') : t('app_venn_lost')) + '</h2><p class="lead">' + esc(result.success ? game.win : game.lose) +
        '</p><p class="help">' + esc(t('app_venn_score', {right: result.caught, total: result.goal})) + '</p>' +
        (gifts.length ? '<ul class="gifts">' + gifts.join('') + '</ul>' : '') + (mixed ? '<div class="card whack-learn">' +
        '<h3>' + esc(t('app_whack_learn')) + '</h3><ul>' + mixed + '</ul></div>' : '') + '<div class="actions">' +
        (result.success ? '' : '<button class="btn btn-main" data-again>' + esc(t('app_tryagain')) + '</button>') +
        '<button class="btn ' + (result.success ? 'btn-main' : 'btn-soft') + '" data-back>' + esc(t('app_backtomap')) +
        '</button></div></div>');
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
