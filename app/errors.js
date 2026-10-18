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
  var t = T.t;
  var esc = T.esc;

  /**
   * Plays a hunt for mistakes.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playErrors = function(opts) {
    var m = T.minigame(opts, 'errors', function(g) {
      game = g;
      intro();
    });
    var layer = m.layer;
    var show = m.show;
    var sound = m.sound;
    var game = null;
    var run = null;

    /** The blots of ink the student still has, as drops. */
    function blots() {
      var html = '';
      for (var i = 0; i < game.misses; i++) {
        html += '<i class="' + (i < blotted() ? 'blot' : '') + '" aria-hidden="true"></i>';
      }
      return '<div class="errors-blots" title="' + esc(t('app_errors_blots', {blots: blotted(), misses: game.misses})) +
        '"><span class="sr-only">' + esc(t('app_errors_blots', {blots: blotted(), misses: game.misses})) + '</span>' +
        html + '</div>';
    }

    /**
     * The blots, but the one the healer got back.
     *
     * @return {number}
     */
    function blotted() {
      return run.blots - m.flow.forgiven;
    }

    /* ---------- Start ---------- */

    function intro() {
      var body = show('<div class="errors-intro">' + m.opening('<span class="battle-mini" aria-hidden="true">' +
        T.embrollo(1) + '</span>') + '<p class="help">' + esc(t('app_errors_goal', {goal: game.goal,
        misses: game.misses})) + '</p>' + m.passiveNote() + '<div class="actions"><button class="btn btn-main" data-go>' +
        esc(t('app_errors_start')) + '</button></div></div>');
      body.querySelector('[data-go]').onclick = start;
    }

    function start() {
      run = {sheet: 0, events: [], found: {}, blots: 0, over: false, sent: false};
      m.begin();
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
        total: game.sheets.length})) + '</span>' + m.lookButton() + blots() + '</div><section class="card errors-sheet">' +
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
      trail(body);
      // The look of the sage: a word with a mistake is marked.
      var spent = body.querySelector('[data-look]');
      if (spent && m.lookSpent()) {
        spent.disabled = true;
        spent.classList.add('spent');
      }
      m.bindLook(body, function() {
        var b = body.querySelector('.errors-text [data-m]');
        if (b) {
          b.classList.add('hinted');
        }
        return !!b;
      });
      body.querySelectorAll('[data-m]').forEach(function(b) {
        b.onclick = function() {
          run.found[s.id + ':' + b.dataset.m] = true;
          run.events.push({s: s.id, m: Number(b.dataset.m)});
          m.answer(true);
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
      m.answer(false);
      sound('giggle');
      b.disabled = true;
      b.classList.add('ok');
      T.toast(esc(t('app_errors_ok')), true);
      var drops = layer.querySelector('.errors-blots');
      if (drops) {
        drops.outerHTML = blots();
      }
      if (blotted() >= game.misses) {
        finish();
      }
    }

    /**
     * What the scout sees: the footprint at the start of the sentence where the next mistake is hiding.
     *
     * @param {Element} body
     */
    function trail(body) {
      if (game.passive !== 'scout') {
        return;
      }
      var all = Array.prototype.slice.call(body.querySelectorAll('.errors-text button'));
      // The next mistake, or the next two with «Far sight»: the start of the sentence of each.
      var starts = [];
      all.forEach(function(b, k) {
        if (!b.hasAttribute('data-m') || starts.length >= (game.ahead || 1)) {
          return;
        }
        var start = k;
        while (start > 0 && !/[.!?:;]$/.test(all[start - 1].textContent.trim())) {
          start--;
        }
        if (starts.indexOf(start) < 0) {
          starts.push(start);
        }
      });
      starts.forEach(function(start) {
        all[start].classList.add('trail');
        all[start].insertAdjacentHTML('beforebegin', '<span class="errors-trail">' + T.icon('trail') + '<span class="sr-only">' +
          esc(t('app_passive_trail')) + '</span></span>');
      });
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
      m.ending(result, {won: t('app_errors_won'), lost: t('app_errors_lost'), again: intro});
    }

  };
})();
