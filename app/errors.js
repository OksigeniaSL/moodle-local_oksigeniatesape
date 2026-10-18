/**
 * Tesapé: the hunt for mistakes of the Embrollo. The Embrollo has changed facts in the sheets of the field notebook of a
 * place; the student reads each sheet and touches what is wrong, and is shown what is right and why. A word touched that
 * was right is a blot of ink; with too many, the notebook is lost this time. The server counts it all again.
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

  /**
   * Plays a hunt for mistakes.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playErrors = function(opts) {
    var layer = document.createElement('div');
    layer.className = 'scene-layer errors-layer';
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
      layer.innerHTML = '<div class="scene errors"><div class="scene-top"><span class="scene-title">' +
        esc(game ? game.title : '') + '</span><button class="btn btn-soft scene-close" type="button" aria-label="' +
        esc(t('app_close')) + '">' + T.icon('close') + '</button></div><div class="scene-body">' + html + '</div></div>';
      layer.querySelector('.scene-close').onclick = close;
      layer.scrollTop = 0;
      return layer.querySelector('.scene-body');
    }

    function sound(name) {
      if (T.fx) {
        T.fx.sound(name);
      }
    }

    /** The blots of ink the student still has, as drops. */
    function blots() {
      var html = '';
      for (var i = 0; i < game.misses; i++) {
        html += '<i class="' + (i < run.blots ? 'blot' : '') + '" aria-hidden="true"></i>';
      }
      return '<div class="errors-blots" title="' + esc(t('app_errors_blots', {blots: run.blots, misses: game.misses})) +
        '"><span class="sr-only">' + esc(t('app_errors_blots', {blots: run.blots, misses: game.misses})) + '</span>' +
        html + '</div>';
    }

    /* ---------- Start ---------- */

    function intro() {
      var body = show('<div class="errors-intro"><span class="battle-mini" aria-hidden="true">' + T.embrollo(1) + '</span>' +
        '<p class="lead">' + esc(game.intro) + '</p><p class="help">' + esc(t('app_errors_goal', {goal: game.goal,
        misses: game.misses})) + '</p><div class="actions"><button class="btn btn-main" data-go>' +
        esc(t('app_errors_start')) + '</button></div></div>');
      body.querySelector('[data-go]').onclick = start;
    }

    function start() {
      run = {sheet: 0, events: [], found: {}, blots: 0, over: false, sent: false};
      sheet();
    }

    /* ---------- A sheet ---------- */

    /**
     * The words of a piece of plain text, each one to touch.
     *
     * @param {string} text
     * @return {string}
     */
    function words(text) {
      return text.split(/(\s+)/).map(function(w) {
        if (!w.trim()) {
          return w;
        }
        return '<button type="button" class="word" data-w>' + esc(w) + '</button>';
      }).join('');
    }

    /**
     * Draws the sheet: turned, when it is a new one; in place, when a mistake has just been found.
     *
     * @param {boolean} inplace
     * @param {number} at For the keyboard: the place of the word just touched, to keep the focus there.
     */
    function sheet(inplace, at) {
      var s = game.sheets[run.sheet];
      var marks = s.parts.filter(function(p) {
        return p.mark >= 0;
      });
      var left = marks.filter(function(p) {
        return !run.found[s.id + ':' + p.mark];
      }).length;
      var text = s.parts.map(function(p) {
        if (p.mark < 0) {
          return words(p.text);
        }
        if (run.found[s.id + ':' + p.mark]) {
          return '<span class="fixed"><del>' + esc(p.wrong) + '</del> <ins>' + esc(p.right[0] || '') + '</ins></span>';
        }
        return '<button type="button" class="word" data-m="' + p.mark + '">' + esc(p.wrong) + '</button>';
      }).join('');
      var why = marks.filter(function(p) {
        return run.found[s.id + ':' + p.mark] && p.why;
      }).map(function(p) {
        return '<li><b>' + esc(p.right.join(' / ')) + '</b>: ' + esc(p.why) + '</li>';
      }).join('');
      var last = run.sheet === game.sheets.length - 1;
      var reads = T.adapted.readaloud && T.readAloud && window.speechSynthesis;
      var html = '<div class="errors-head"><span class="kicker">' + esc(t('app_errors_sheet', {n: run.sheet + 1,
        total: game.sheets.length})) + '</span>' + blots() + '</div><section class="card errors-sheet">' +
        (reads ? '<button type="button" class="btn btn-soft read-card" data-readcard aria-label="' +
        esc(t('app_read_aloud')) + '">' + T.icon('speak') + '</button>' : '') + '<h2>' + esc(s.title) + '</h2><p class="errors-text">' + text +
        '</p></section>' + (why ? '<ul class="errors-why">' + why + '</ul>' : '') + '<p class="help" aria-live="polite">' +
        esc(left ? t('app_errors_left', left) : t('app_errors_clean')) + '</p>' + (left ? '' :
        '<div class="actions"><button class="btn btn-main" data-focus data-next>' + esc(last ? t('app_errors_finish') :
        t('app_errors_next')) + '</button></div>');
      var body = layer.querySelector('.scene-body');
      if (inplace && body) {
        body.innerHTML = html;
        var buttons = body.querySelectorAll('.errors-text button');
        if (buttons.length && at >= 0) {
          buttons[Math.min(at, buttons.length - 1)].focus();
        }
      } else {
        body = show(html);
      }
      body.querySelectorAll('[data-w]').forEach(function(b) {
        b.onclick = function() {
          blot(s, b);
        };
      });
      body.querySelectorAll('[data-m]').forEach(function(b) {
        b.onclick = function() {
          run.found[s.id + ':' + b.dataset.m] = true;
          run.events.push({s: s.id, m: Number(b.dataset.m)});
          sound('right');
          sheet(true, b === document.activeElement ?
            Array.prototype.indexOf.call(body.querySelectorAll('.errors-text button'), b) : -1);
        };
      });
      var read = body.querySelector('[data-readcard]');
      if (read) {
        read.onclick = function() {
          T.readAloud(body.querySelector('.errors-sheet'));
        };
      }
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
      }
    }

    /**
     * A word touched that was right: a blot of ink.
     *
     * @param {Object} s The sheet.
     * @param {HTMLElement} b
     */
    function blot(s, b) {
      run.blots++;
      run.events.push({s: s.id, x: 1});
      sound('wrong');
      b.disabled = true;
      b.classList.add('ok');
      T.toast(esc(t('app_errors_ok')), true);
      var drops = layer.querySelector('.errors-blots');
      if (drops) {
        drops.outerHTML = blots();
      }
      if (run.blots >= game.misses) {
        finish();
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
      sound(result.success ? 'win' : 'wrong');
      var body = show('<div class="nest-end ' + (result.success ? 'won' : 'lost') + '"><span class="battle-mini" ' +
        'aria-hidden="true">' + T.embrollo(result.success ? 0.2 : 1) + '</span><h2>' + esc(result.success ?
        t('app_errors_won') : t('app_errors_lost')) + '</h2><p class="lead">' + esc(result.success ? game.win : game.lose) +
        '</p>' +
        (gifts.length ? '<ul class="gifts">' + gifts.join('') + '</ul>' : '') + '<div class="actions">' +
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
