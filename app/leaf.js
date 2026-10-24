/**
 * Tesapé: the leaf factory. A leaf seen inside, with three keys (light, water and air): it makes as much sugar as the
 * least of the three lets it, and what is left of the others is wasted. Rounds of a recipe (fill the jar with the sugar
 * asked, wasting nothing, within what the cloud or the dry root let) and rounds of a question (the night, the water).
 * The leaf changes as the keys move: pale without light, wrinkled without water, bright when the three go together.
 * The server counts it all again.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var t = T.t;
  var esc = T.esc;
  var KEYS = ['light', 'water', 'air'];

  /**
   * The leaf inside, as the keys leave it: its green, its wrinkles, the jar and the bubbles of oxygen.
   *
   * @param {Object} k light, water, air.
   * @param {number} most
   * @return {string} SVG.
   */
  function leaf(k, most) {
    var made = Math.min(k.light, k.water, k.air);
    var pale = k.light < Math.max(k.water, k.air);
    var dry = k.water < Math.max(k.light, k.air);
    var green = !made ? '#9aa79a' : pale ? '#b9d98a' : '#3fae5a';
    var jar = Math.round(60 * made / most);
    var bubbles = '';
    for (var i = 0; i < made; i++) {
      bubbles += '<circle class="leaf-bubble" cx="' + (150 + i * 14) + '" cy="' + (60 - i * 8) + '" r="5" style="animation-delay:' +
        (i * 0.4) + 's"/>';
    }
    return '<svg class="leaf-svg" viewBox="0 0 240 160" role="img" aria-label="' + esc(t('app_leaf_state', {made: made,
      waste: k.light + k.water + k.air - 3 * made})) + '"><path d="M20 120 C40 30 140 10 200 40 C170 110 90 150 20 120 Z" fill="' +
      green + '" stroke="#2d6b3a" stroke-width="3"' + (dry ? ' stroke-dasharray="6 4" class="leaf-dry"' : '') + '/>' +
      '<path d="M20 120 C80 90 140 60 200 40" stroke="#2d6b3a" stroke-width="3" fill="none"/>' + bubbles +
      '<rect x="196" y="90" width="34" height="62" rx="6" fill="none" stroke="currentColor" stroke-width="3"/>' +
      '<rect x="199" y="' + (149 - jar) + '" width="28" height="' + jar + '" rx="4" fill="#ffc857"/></svg>';
  }

  /**
   * Plays the leaf factory.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playLeaf = function(opts) {
    var m = T.minigame(opts, 'leaf', function(g) {
      game = g;
      intro();
    });
    var layer = m.layer;
    var show = m.show;
    var sound = m.sound;
    var game = null;
    var run = null;

    /* ---------- Start ---------- */

    function intro() {
      var keys = {light: 2, water: 3, air: 1};
      var body = show('<div class="leaf-intro">' + m.opening(leaf(keys, game.most)) + '<p class="help">' +
        esc(t('app_leaf_goal', {rounds: game.rounds.length, misses: game.misses})) + '</p>' + m.passiveNote() +
        '<div class="actions"><button class="btn btn-main" data-go>' + esc(t('app_leaf_start')) + '</button></div></div>');
      body.querySelector('[data-go]').onclick = start;
      body.querySelector('[data-go]').focus();
    }

    function start() {
      run = {round: 0, events: [], right: 0, wrong: 0, over: false, lost: [], keys: null, done: null};
      m.begin();
      round();
    }

    /* ---------- A round ---------- */

    function round() {
      var r = game.rounds[run.round];
      var last = run.round === game.rounds.length - 1;
      if (!run.keys && r.kind !== 'question') {
        run.keys = {light: 0, water: 0, air: 0};
      }
      var html = '<div class="nest-hud"><span class="kicker">' + esc(t('app_leaf_count', {n: run.round + 1,
        total: game.rounds.length})) + '</span><span>' + esc(t('app_leaf_score', {right: run.right, wrong: run.wrong})) +
        '</span></div><section class="card leaf-card"><h2>' + esc(r.title) + '</h2><p>' + esc(r.prompt) + '</p>';
      if (r.kind === 'question') {
        html += '</section><div class="whoami-say' + (run.done ? ' ' + (run.done.right ? 'good' : 'bad') : '') +
          '" aria-live="polite">' + (run.done ? '<b>' + esc(t(run.done.right ? 'app_leaf_right' : 'app_leaf_wrong')) + '</b> ' +
          esc(r.why) : '') + '</div>' + (run.done ? '' : '<div class="whoami-options">' + r.options.map(function(o) {
            return '<button type="button" class="btn btn-soft" data-answer="' + esc(o) + '">' + esc(o) + '</button>';
          }).join('') + '</div>');
      } else {
        var k = run.keys;
        var made = Math.min(k.light, k.water, k.air);
        html += leaf(k, game.most) + '<p class="leaf-reading" aria-live="polite">' + esc(t('app_leaf_reading', {made: made,
          waste: k.light + k.water + k.air - 3 * made})) + '</p><div class="leaf-keys">' + KEYS.map(function(key) {
          var cap = r.caps[key];
          return '<label class="leaf-key"><span>' + esc(t('app_leaf_' + key)) + (cap < game.most ? ' <small>' +
            esc(t('app_leaf_cap', cap)) + '</small>' : '') + '</span><input type="range" min="0" max="' + cap + '" step="1" value="' +
            k[key] + '" data-key="' + key + '"' + (run.done ? ' disabled' : '') + '><b>' + k[key] + '</b></label>';
        }).join('') + '</div></section><div class="whoami-say' + (run.done ? ' ' + (run.done.right ? 'good' : 'bad') : '') +
          '" aria-live="polite">' + (run.done ? '<b>' + esc(t(run.done.right ? 'app_leaf_full' : 'app_leaf_notyet', r.target)) +
          '</b> ' + esc(r.why) : '') + '</div>' + (run.done ? '' : '<div class="actions"><button class="btn btn-main" data-run>' +
          esc(t('app_leaf_run')) + '</button></div>');
      }
      if (run.done) {
        html += '<div class="actions"><button class="btn btn-main" data-next>' + esc(last ? t('app_errors_finish') :
          t('app_whoami_next')) + '</button></div>';
      }
      var body = layer.querySelector('.scene-body');
      if (!body) {
        body = show(html);
      } else {
        body.innerHTML = html;
      }
      body.querySelectorAll('[data-key]').forEach(function(input) {
        input.oninput = function() {
          // Only the leaf, the reading and the number change: the key keeps being dragged.
          var k = run.keys;
          k[input.dataset.key] = Number(input.value);
          input.nextElementSibling.textContent = input.value;
          var made = Math.min(k.light, k.water, k.air);
          var svg = body.querySelector('.leaf-svg');
          if (svg) {
            svg.outerHTML = leaf(k, game.most);
          }
          body.querySelector('.leaf-reading').textContent = t('app_leaf_reading', {made: made,
            waste: k.light + k.water + k.air - 3 * made});
        };
      });
      var go = body.querySelector('[data-run]');
      if (go) {
        go.onclick = function() {
          var keys = run.keys;
          var right = keys.light === keys.water && keys.water === keys.air && keys.light === r.target;
          run.events.push({r: r.id, l: keys.light, w: keys.water, a: keys.air});
          settle(r, right);
        };
      }
      body.querySelectorAll('[data-answer]').forEach(function(b) {
        b.onclick = function() {
          var right = b.dataset.answer.toLowerCase() === r.answer.toLowerCase();
          run.events.push({r: r.id, g: b.dataset.answer});
          settle(r, right);
        };
      });
      var next = body.querySelector('[data-next]');
      if (next) {
        next.onclick = function() {
          if (last) {
            finish();
          } else {
            run.round++;
            run.keys = null;
            run.done = null;
            round();
          }
        };
        next.focus();
      }
    }

    /**
     * How a round went: right or not, said with its why.
     *
     * @param {Object} r
     * @param {boolean} right
     */
    function settle(r, right) {
      m.answer(right);
      run.done = {right: right};
      if (right) {
        run.right++;
        sound('right');
      } else {
        run.wrong++;
        run.lost.push(r);
        sound('wrong');
      }
      round();
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
      // What went wrong, with its why: to read now, without hurry.
      var lost = run.lost.map(function(r) {
        return '<li><b>' + esc(r.title) + '</b>: ' + esc(r.why) + '</li>';
      }).join('');
      var keys = result.success ? {light: game.most, water: game.most, air: game.most} : {light: 1, water: 3, air: 2};
      m.ending(result, {won: t('app_leaf_won'), lost: t('app_leaf_lost'), learn: lost, again: intro, art: leaf(keys, game.most)});
    }
  };
})();
