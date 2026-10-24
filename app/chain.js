/**
 * Tesapé: Eslabón. The links of a food chain, shuffled: the student puts them in their order from the sun, touching
 * them one after the other (a link put goes back with a touch). Right, the energy runs along the chain and less of it
 * reaches each link. Then the questions: why there are few at the top, what happens when the Embrollo pulls a link out,
 * who uses what is left. The server counts it all again.
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
   * A link as a card: its drawing, if the art pack has it, and its name.
   *
   * @param {Object} c id, label, picture.
   * @return {string}
   */
  function card(c) {
    var art = T.artUrl([c.picture]);
    return (art ? '<img src="' + esc(art) + '" alt="">' : '') + '<span>' + esc(T.cap(c.label)) + '</span>';
  }

  /**
   * Plays Eslabón.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playChain = function(opts) {
    var m = T.minigame(opts, 'chain', function(g) {
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
      var body = show('<div class="chain-intro">' + m.opening('<span class="chain-links big" aria-hidden="true">' +
        '<i></i><i></i><i></i></span>') + '<p class="help">' + esc(t('app_leaf_goal', {rounds: game.rounds.length,
        misses: game.misses})) + '</p>' + m.passiveNote() + '<div class="actions"><button class="btn btn-main" data-go>' +
        esc(t('app_chain_start')) + '</button></div></div>');
      body.querySelector('[data-go]').onclick = start;
      body.querySelector('[data-go]').focus();
    }

    function start() {
      run = {round: 0, events: [], right: 0, wrong: 0, over: false, lost: [], put: [], done: null};
      m.begin();
      round();
    }

    /* ---------- A round ---------- */

    function round() {
      var r = game.rounds[run.round];
      var last = run.round === game.rounds.length - 1;
      var html = '<div class="nest-hud"><span class="kicker">' + esc(t('app_leaf_count', {n: run.round + 1,
        total: game.rounds.length})) + '</span><span>' + esc(t('app_leaf_score', {right: run.right, wrong: run.wrong})) +
        '</span></div><section class="card leaf-card"><h2>' + esc(r.title) + '</h2><p>' + esc(r.prompt) + '</p>';
      var say = run.done ? '<div class="whoami-say ' + (run.done.right ? 'good' : 'bad') + '" aria-live="polite"><b>' +
        esc(t(run.done.right ? (r.kind === 'question' ? 'app_leaf_right' : 'app_chain_right') : (r.kind === 'question' ?
        'app_leaf_wrong' : 'app_chain_wrong'))) + '</b> ' + esc(r.why) + '</div>' : '<div class="whoami-say" aria-live="polite">' +
        '</div>';
      if (r.kind === 'question') {
        html += '</section>' + say + (run.done ? '' : '<div class="whoami-options">' + r.options.map(function(o) {
          return '<button type="button" class="btn btn-soft" data-answer="' + esc(o) + '">' + esc(o) + '</button>';
        }).join('') + '</div>');
      } else {
        var byid = {};
        r.cards.forEach(function(c) {
          byid[c.id] = c;
        });
        var shown = run.done && run.done.right ? r.order : run.put;
        // The chain as it is put; right, the energy that reaches each link.
        html += '<ol class="chain-row" aria-label="' + esc(t('app_chain_row')) + '">' + r.order.map(function(id, i) {
          var c = byid[shown[i]];
          var energy = Math.max(4, Math.round(100 / Math.pow(3, i)));
          return '<li class="chain-slot' + (c ? ' full' : '') + '">' + (c ? '<button type="button" class="chain-card" data-out="' + i +
            '"' + (run.done ? ' disabled' : '') + ' aria-label="' + esc(t('app_chain_out', {n: i + 1, name: c.label})) + '">' +
            card(c) + '</button>' : '<span class="chain-empty">' + (i + 1) + '</span>') + (run.done && run.done.right ?
            '<span class="chain-energy" role="img" aria-label="' + esc(t('app_chain_energy', energy)) + '"><i style="width:' +
            energy + '%"></i></span>' : '') + '</li>';
        }).join('') + '</ol>';
        if (!run.done) {
          html += '<p class="help-soft">' + esc(t('app_chain_help')) + '</p><div class="chain-bank">' + r.cards.filter(function(c) {
            return run.put.indexOf(c.id) < 0;
          }).map(function(c) {
            return '<button type="button" class="chain-card" data-in="' + esc(c.id) + '">' + card(c) + '</button>';
          }).join('') + '</div>';
        } else if (run.done.right) {
          html += '<p class="help-soft">' + esc(t('app_chain_flows')) + '</p>';
        }
        html += '</section>' + say + (run.done ? '' : '<div class="actions"><button class="btn btn-main" data-check' +
          (run.put.length < r.order.length ? ' disabled' : '') + '>' + esc(t('app_chain_check')) + '</button></div>');
      }
      if (run.done) {
        html += '<div class="actions"><button class="btn btn-main" data-next>' + esc(last ? t('app_errors_finish') :
          t('app_leaf_next')) + '</button></div>';
      }
      var body = layer.querySelector('.scene-body');
      if (!body) {
        body = show(html);
      } else {
        body.innerHTML = html;
      }
      body.querySelectorAll('[data-in]').forEach(function(b) {
        b.onclick = function() {
          run.put.push(b.dataset.in);
          sound('tap');
          round();
          var next = layer.querySelector('[data-in]') || layer.querySelector('[data-check]');
          if (next) {
            next.focus();
          }
        };
      });
      body.querySelectorAll('[data-out]').forEach(function(b) {
        b.onclick = function() {
          run.put.splice(Number(b.dataset.out), 1);
          round();
        };
      });
      var check = body.querySelector('[data-check]');
      if (check) {
        check.onclick = function() {
          var right = run.put.join(',') === r.order.join(',');
          run.events.push({r: r.id, o: run.put.slice()});
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
            run.put = [];
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
      var lost = run.lost.map(function(r) {
        return '<li><b>' + esc(r.title) + '</b>: ' + esc(r.why) + '</li>';
      }).join('');
      m.ending(result, {won: t('app_chain_won'), lost: t('app_chain_lost'), learn: lost, again: intro,
        art: '<span class="chain-links big' + (result.success ? ' whole' : '') + '" aria-hidden="true"><i></i><i></i><i></i></span>'});
    }
  };
})();
