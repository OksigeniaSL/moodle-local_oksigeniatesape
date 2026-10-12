/**
 * Tesapé: the battle against the Embrollo on the phone of a student. A call appears by itself when the class screen
 * opens a battle with phones; inside, the question of the round with big buttons, what happened when the answer is
 * shown, and the prize if the class wins. It lives in its own layer, over the game, and asks the server every second and
 * a half while playing (every few seconds otherwise, and never while the page is hidden).
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
  var FAST = 1500;
  var SLOW = 8000;
  var LETTERS = 'ABCDEF';
  var layer = document.createElement('div');
  layer.className = 'battle-layer';
  document.body.appendChild(layer);
  var state = {live: null, inside: false, dismissed: 0, timer: 0, sig: '', sending: false, tick: 0};
  // The result of a battle shows for a few minutes after it ends: once closed, not again on the next page.
  var SEEN = 'tesape-battle-' + cfg.courseid + '-' + cfg.userid;
  try {
    state.dismissed = +window.sessionStorage.getItem(SEEN) || 0;
  } catch (e) {
    state.dismissed = 0;
  }

  function api(action, extra) {
    return T.api('live_play', Object.assign({courseid: cfg.courseid, action: action}, extra || {}));
  }

  function poll() {
    clearTimeout(state.timer);
    if (document.hidden) {
      return;
    }
    api('state').then(function(r) {
      show(r.live || null);
    }).catch(function() {
      // No character yet, or no connection: try again later.
    }).then(function() {
      state.timer = setTimeout(poll, state.inside ? FAST : SLOW);
    });
  }

  /**
   * Shows the call, the battle or nothing.
   *
   * @param {Object|null} live
   */
  function show(live) {
    state.live = live;
    var playing = live && live.mode === 'phones' && state.dismissed !== live.id;
    state.inside = !!(playing && live.joined);
    // The seconds left change on every poll; the countdown shows them without drawing everything again.
    var sig = JSON.stringify([live ? Object.assign({}, live, {left: 0}) : null, state.inside]);
    if (sig === state.sig) {
      return;
    }
    state.sig = sig;
    if (!playing || (!live.joined && live.outcome)) {
      clearInterval(state.tick);
      layer.innerHTML = '';
      document.body.classList.remove('in-battle');
      return;
    }
    var vote = live.kind === 'vote';
    if (!live.joined) {
      layer.innerHTML = '<div class="battle-call"><span class="battle-mini">' + (vote ? T.icon('world') : T.embrollo(1)) +
        '</span><p>' + esc(t(vote ? 'app_vote_call' : 'app_battle_call')) + '</p><button class="btn btn-main" data-join>' +
        esc(t('app_battle_join')) + '</button></div>';
      layer.querySelector('[data-join]').onclick = function(e) {
        e.target.disabled = true;
        api('join').then(function(r) {
          show(r.live || null);
          poll();
        }).catch(function(err) {
          T.toast(esc(err.message), true);
        });
      };
      return;
    }
    document.body.classList.add('in-battle');
    layer.innerHTML = '<div class="battle-phone" role="dialog" aria-modal="true" aria-label="' +
      esc(t('app_battle_title')) + '">' + head(live) + body(live) + '</div>';
    bind(live);
  }

  function head(live) {
    if (live.kind === 'vote') {
      return '<div class="battle-head"><span class="battle-mini">' + T.icon('world') + '</span><div class="grow"><b>' +
        esc(t('app_vote_title')) + '</b></div></div>';
    }
    var life = live.maxhp ? live.hp / live.maxhp : 1;
    return '<div class="battle-head"><span class="battle-mini">' + T.embrollo(life) + '</span><div class="grow"><b>' +
      esc(t('app_battle_title')) + '</b>' + (live.round ? '<small>' + esc(t('app_battle_round', live)) + '</small>' : '') +
      '<span class="meter boss"><i style="width:' + Math.round(100 * life) + '%"></i></span></div></div>';
  }

  function body(live) {
    if (live.kind === 'vote') {
      return voteBody(live);
    }
    if (live.outcome === 'won') {
      var p = live.prize;
      var prize = p ? '<p class="lead">' + esc(t('app_battle_prize', p)) + (p.capped ? ' (' + esc(t('app_capped', p.capped)) +
        ')' : '') + '</p>' + (p.best ? '<p>' + esc(p.item ? t('app_battle_bestitem', p.item) : t('app_battle_best')) +
        '</p>' : '') + (p.final ? '<p>' + esc(t('app_battle_finalprize', p.final)) + '</p>' : '') + (p.grew ? '<p>' +
        esc(t('app_battle_grew')) + '</p>' : '') : '';
      return '<div class="battle-end won"><h2>' + esc(t('app_battle_won')) + '</h2>' + prize +
        '<button class="btn btn-main" data-back>' + esc(t('app_battle_back')) + '</button></div>';
    }
    if (live.outcome === 'lost') {
      return '<div class="battle-end"><h2>' + esc(t('app_battle_escaped')) + '</h2><p>' + esc(t('app_battle_lost')) + '</p>' +
        '<button class="btn btn-main" data-back>' + esc(t('app_battle_back')) + '</button></div>';
    }
    if (!live.round || !live.question) {
      return '<p class="battle-wait">' + esc(t('app_battle_wait')) + '</p>';
    }
    var q = live.question;
    var answered = live.mine >= 0;
    var options = q.options.map(function(o, i) {
      var struck = live.struck === i && !live.revealed;
      var cls = 'battle-option' + (live.mine === i ? ' mine' : '') + (live.revealed && q.correct === i ? ' right' : '') +
        (struck ? ' struck' : '');
      return '<button type="button" class="' + cls + '" data-answer="' + i + '"' + (answered || !live.open || struck ?
        ' disabled' : '') + (struck ? ' title="' + esc(t('app_battle_struck')) + '"' : '') + '><b>' + LETTERS[i] + '</b><span>' +
        esc(o) + '</span></button>';
    }).join('');
    var note = '';
    if (live.revealed) {
      note = live.mine < 0 ? t('app_battle_none') : live.mine === q.correct ? t('app_battle_right') : t('app_battle_wrong');
      note = '<p class="battle-note">' + esc(note) + '</p><p class="help-soft">' + esc(q.why || '') + '</p>';
    } else if (answered) {
      note = '<p class="battle-note">' + esc(t('app_battle_answered')) + '</p>';
    } else if (!live.open) {
      note = '<p class="battle-note">' + esc(t('app_battle_closed')) + '</p>';
    } else if (live.left >= 0) {
      note = '<p class="battle-left" data-left="' + live.left + '">' + esc(t('app_battle_left', live.left)) + '</p>';
    }
    var trail = live.trail && !live.revealed ? '<p class="battle-trail">' + esc(t('app_token_trail_shown', live.trail)) +
      '</p>' : '';
    return '<p class="battle-q">' + esc(q.text) + '</p>' + trail + '<div class="battle-options">' + options + '</div>' + note +
      token(live);
  }

  /**
   * The battle token of the role of the student: once a battle, while a question is open.
   *
   * @param {Object} live
   * @return {string}
   */
  function token(live) {
    if (!live.token || live.revealed) {
      return '';
    }
    if (live.tokenused) {
      return '<p class="help-soft battle-token-used">' + esc(t('app_token_used')) + '</p>';
    }
    return live.open ? '<div class="battle-token"><button type="button" class="btn btn-soft" data-token>' +
      esc(t('app_token_' + live.token)) + '</button></div>' : '';
  }

  function voteBody(live) {
    if (live.outcome) {
      return '<div class="battle-end"><h2>' + esc(t('app_vote_done')) + '</h2><button class="btn btn-main" data-back>' +
        esc(t('app_battle_back')) + '</button></div>';
    }
    if (!live.round || !live.question) {
      return '<p class="battle-wait">' + esc(t('app_vote_wait')) + '</p>';
    }
    var q = live.question;
    var tally = live.tally || [];
    var options = q.options.map(function(o, i) {
      return '<button type="button" class="battle-option' + (live.mine === i ? ' mine' : '') + '" data-answer="' + i + '"' +
        (live.mine >= 0 || !live.open ? ' disabled' : '') + '><b>' + LETTERS[i] + '</b><span>' + esc(o) +
        (live.revealed ? ' · ' + esc(t('board_scene_optvotes', tally[i] || 0)) : '') + '</span></button>';
    }).join('');
    var note = live.revealed ? t('app_vote_closed') : live.mine >= 0 ? t('app_vote_sent') : '';
    return '<p class="battle-q">' + esc(q.text) + '</p><div class="battle-options">' + options + '</div>' +
      (note ? '<p class="battle-note">' + esc(note) + '</p>' : '');
  }

  function bind(live) {
    layer.querySelectorAll('[data-answer]').forEach(function(b) {
      b.onclick = function() {
        if (state.sending) {
          return;
        }
        state.sending = true;
        b.classList.add('mine');
        layer.querySelectorAll('[data-answer]').forEach(function(o) {
          o.disabled = true;
        });
        if (navigator.vibrate) {
          navigator.vibrate(30);
        }
        api('answer', {round: live.round, answer: +b.dataset.answer}).then(function(r) {
          show(r.live || null);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          state.sig = '';
          poll();
        }).then(function() {
          state.sending = false;
        });
      };
    });
    var use = layer.querySelector('[data-token]');
    if (use) {
      use.onclick = function() {
        use.disabled = true;
        api('token', {}).then(function(r) {
          state.sig = '';
          show(r.live || null);
        }).catch(function(e) {
          use.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    }
    var back = layer.querySelector('[data-back]');
    if (back) {
      back.onclick = function() {
        state.dismissed = live.id;
        try {
          window.sessionStorage.setItem(SEEN, String(live.id));
        } catch (e) {
          // Without storage it shows again on the next page, as before.
        }
        show(live);
        if (live.prize && T.refreshWorld) {
          T.refreshWorld();
        }
      };
    }
    clearInterval(state.tick);
    var left = layer.querySelector('[data-left]');
    if (left) {
      var secs = +left.dataset.left;
      state.tick = setInterval(function() {
        secs = Math.max(0, secs - 1);
        left.textContent = t('app_battle_left', secs);
      }, 1000);
    }
  }

  document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
      poll();
    }
  });
  poll();
})();
