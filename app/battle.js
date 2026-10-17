/**
 * Tesapé: the battle against the Embrollo on the phone of a student. A call appears by itself when the class screen
 * opens a battle with phones; inside, the question of the round with big buttons, what happened when the answer is
 * shown, and the prize if the class wins. It lives in its own layer, over the game, and asks the server every two seconds
 * and a half while playing (or just when the question closes); otherwise, less and less often while there is no battle,
 * and never while the page is hidden.
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
  var FAST = 2500;
  var SLOW = 8000;
  var SLOWEST = 30000;
  var LETTERS = 'ABCDEF';
  var layer = document.createElement('div');
  layer.className = 'battle-layer';
  document.body.appendChild(layer);
  var state = {live: null, inside: false, dismissed: 0, timer: 0, sig: '', sending: false, tick: 0, idle: 0};
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
      state.idle = r.live ? 0 : state.idle + 1;
      show(r.live || null);
    }).catch(function() {
      // No character yet, or no connection: try again later.
      state.idle++;
    }).then(function() {
      state.timer = setTimeout(poll, wait());
    });
  }

  /**
   * How long until the next poll.
   *
   * @return {number} Milliseconds.
   */
  function wait() {
    var live = state.live;
    if (state.inside) {
      // The phone comes back just as the question closes, which is what matters.
      return live && live.open && live.left > 0 ? Math.min(FAST, (live.left + 0.3) * 1000) : FAST;
    }
    if (live) {
      return SLOW;
    }
    // No battle: a little longer each time, up to half a minute.
    return Math.min(SLOWEST, SLOW * Math.pow(2, Math.max(0, state.idle - 1)));
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
    if (live.outcome && state.celebrated !== live.id && T.fx) {
      // The end of the battle, heard and seen once on each phone.
      state.celebrated = live.id;
      if (live.outcome === 'won') {
        T.fx.sound('win');
        T.fx.confetti();
      } else {
        T.fx.sound('wrong');
      }
    }
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
      esc(t('app_battle_title')) + '</b>' + (live.round ? '<small>' + esc(t(live.stage ? 'board_turn_round' : 'app_battle_round', live)) + '</small>' : '') +
      '<span class="meter boss"><i style="width:' + Math.round(100 * life) + '%"></i></span>' + (live.maxcandles ?
      '<small class="candles-left">' + T.icon('lantern') + ' ' + esc(t('board_turn_candles', {lit: live.candles,
      all: live.maxcandles})) + '</small>' : '') + '</div></div>';
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
    if (live.stage === 'intent' || live.stage === 'prep') {
      // By turns: the knot, what the Embrollo claims and which team it goes for; meanwhile, the verb of the role.
      var mine = live.targetid && live.targetid === live.myteam;
      var tangled = (live.tangled || []).indexOf(live.myteam) >= 0;
      var intent = live.intent && live.intent !== 'team' ? '<p class="knot-target intent-' + live.intent + '">' +
        esc(t('board_turn_intent_' + live.intent)) + '</p>' : live.target ? '<p class="knot-target' + (mine ? ' mine' : '') +
        '">' + esc(mine ? t('app_turn_mine') : t('app_turn_goes', live.target)) + '</p>' : '';
      return '<div class="knot-call' + (tangled ? ' tangled' : '') + '"><p class="knot-name">' + esc(live.knot.name) +
        '</p><p class="knot-claim">«' + esc(live.knot.claim.replace(/^«|»$/g, '')) + '»</p>' + intent + '</div>' +
        (live.stage === 'prep' && !T.adapted.notime ? '<p class="battle-left" aria-live="off">' + esc(t('app_turn_prep',
        live.stageleft)) + '</p>' : '') + token(live);
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
    } else if (live.left >= 0 && !T.adapted.notime) {
      note = '<p class="battle-left" data-left="' + live.left + '" aria-live="off">' + esc(t('app_battle_left', live.left)) + '</p>';
    }
    var trail = live.trail && !live.revealed ? '<p class="battle-trail">' + esc(t('app_token_trail_shown', live.trail)) +
      '</p>' : '';
    if (live.stage === 'resolve' && live.done !== undefined) {
      // By turns: what the team did with its lane, the knot if it broke, and what the Embrollo did.
      note += '<p class="turn-outcome' + (live.done ? ' undone' : ' fell') + '">' + esc(t(live.done ? 'app_turn_lane_done' :
        'app_turn_lane_failed')) + '</p>' + (live.knot.broken ? '<p class="turn-outcome undone">' +
        esc(t('board_turn_broken')) + '</p>' : '') + (live.counter && !live.recovered ? '<p class="turn-outcome fell">' +
        esc(t(live.intent === 'all' ? 'board_turn_candle_all' : 'board_turn_candle', live.target)) + '</p>' : '') +
        (live.knot.laugh ? '<p class="knot-laugh">«' + esc(live.knot.laugh.replace(/^«|»$/g, '')) + '»</p>' : '');
    } else if (live.stage === 'resolve' && live.target) {
      var fell = live.counter || live.shielded;
      note += '<p class="turn-outcome' + (fell ? ' fell' : ' undone') + '">' + esc(t(!fell ? 'app_turn_undone' : live.shielded ?
        'app_turn_shield' : 'app_turn_fell', live.target)) + '</p>' + (live.knot.laugh ? '<p class="knot-laugh">«' +
        esc(live.knot.laugh.replace(/^«|»$/g, '')) + '»</p>' : '');
    }
    return (live.stage === 'strike' ? '<p class="knot-name">' + esc(t('app_turn_strike', live.knot.name)) + '</p>' : '') +
      '<p class="battle-q">' + esc(q.text) + '</p>' + trail + '<div class="battle-options">' + options + '</div>' + note +
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
    // By turns, the scout who followed the trail sees what comes next.
    var ahead = live.nextknot ? '<p class="battle-trail">' + esc(t('app_token_next', {knot: live.nextknot,
      team: live.nexttarget || ''})) + '</p>' : '';
    if (live.tokenused) {
      return ahead + '<p class="help-soft battle-token-used">' + esc(live.tokenrest ? t('app_token_rest', live.tokenrest) :
        t('app_token_used')) + '</p>';
    }
    // By turns, the verb is ready from the moment the knot is announced, in big while the roles get ready.
    var ready = live.open || live.stage === 'intent' || live.stage === 'prep';
    var big = live.stage === 'intent' || live.stage === 'prep';
    if (!ready) {
      return ahead;
    }
    if (live.targets && live.targets.length > 1) {
      // The shield and the cheer go to a team: one big button for each, their own first.
      var mine = live.targets.filter(function(x) {
        return x.id === live.myteam;
      }).concat(live.targets.filter(function(x) {
        return x.id !== live.myteam;
      }));
      return ahead + '<div class="battle-token"><p class="help-soft">' + esc(t('app_token_' + live.token + (live.maxcandles ? '_turns' : ''))) + '</p>' +
        mine.map(function(x) {
          return '<button type="button" class="btn ' + (big ? 'btn-main' : 'btn-soft') + '" data-token data-target="' + x.id +
            '">' + esc(t('app_token_on', x.id === live.myteam ? t('app_token_ownteam') : x.name)) + '</button>';
        }).join('') + '</div>';
    }
    return ahead + '<div class="battle-token"><button type="button" class="btn ' + (big ? 'btn-main btn-big' : 'btn-soft') +
      '" data-token>' + esc(t('app_token_' + live.token + (live.maxcandles ? '_turns' : ''))) + '</button></div>';
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
    layer.querySelectorAll('[data-token]').forEach(function(use) {
      use.onclick = function() {
        layer.querySelectorAll('[data-token]').forEach(function(b) {
          b.disabled = true;
        });
        api('token', {target: Number(use.dataset.target || 0)}).then(function(r) {
          state.sig = '';
          show(r.live || null);
        }).catch(function(e) {
          layer.querySelectorAll('[data-token]').forEach(function(b) {
            b.disabled = false;
          });
          T.toast(esc(e.message), true);
        });
      };
    });
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
