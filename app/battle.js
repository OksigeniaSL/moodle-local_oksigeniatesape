/**
 * Tesapé: the battle against the Embrollo on the phone of a student. A call appears by itself when the class screen
 * opens a battle with phones; inside, the question of the round with big buttons, what happened when the answer is
 * shown, and the prize if the class wins. It lives in its own layer, over the game, and asks the server every two seconds
 * and a half while playing (or just when the question closes); otherwise, less and less often while there is no battle,
 * and never while the page is hidden.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
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
  // By turns, one sentence at each change of stage, for the screen reader: it is there before it says anything.
  var said = document.createElement('p');
  said.className = 'sr-only';
  said.setAttribute('aria-live', 'polite');
  document.body.appendChild(said);
  var state = {live: null, inside: false, dismissed: 0, timer: 0, sig: '', sending: false, tick: 0, idle: 0, timing: {}};

  /** Milliseconds the knot takes to cross the bar of the pull or the parry, and where its clear zone is. */
  var TIMING = 3000;
  var ZONE = [0.58, 0.82];
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
    var u = live && live.untangle ? JSON.parse(live.untangle) : null;
    var nb = live && live.nest ? JSON.parse(live.nest) : null;
    var sig = JSON.stringify([live ? Object.assign({}, live, {left: 0, stageleft: 0, untangle: u ? JSON.stringify(Object.assign({},
      u, {left: 0})) : '', nest: nb ? JSON.stringify(Object.assign({}, nb, {left: 0})) : ''}) : null, state.inside]);
    if (u) {
      var uleft = layer.querySelector('[data-uleft]');
      if (uleft) {
        uleft.textContent = Math.floor(u.left / 60) + ':' + ('0' + (u.left % 60)).slice(-2);
      }
    }
    if (sig === state.sig) {
      return;
    }
    state.sig = sig;
    // Inside a battle, its music, if the teacher allows it on the devices and the student turns it on.
    if (T.fx) {
      T.fx.battleMusic(!!(playing && live.joined && live.kind !== 'vote' && !live.outcome));
    }
    if (!playing || (!live.joined && live.outcome)) {
      clearInterval(state.tick);
      layer.innerHTML = '';
      document.body.classList.remove('in-battle');
      return;
    }
    var vote = live.kind === 'vote';
    var knot = live.kind === 'untangle';
    var nest = live.kind === 'nest';
    if (!live.joined) {
      layer.innerHTML = '<div class="battle-call"><span class="battle-mini">' + (vote ? T.icon('world') : T.embrollo(1)) +
        '</span><p>' + esc(t(vote ? 'app_vote_call' : knot ? 'app_untangle_call' : nest ? 'app_nestboard_call' :
        'app_battle_call')) + '</p><button class="btn btn-main" data-join>' + esc(t(vote ? 'app_vote_join' : knot || nest ?
        'app_untangle_join' : 'app_battle_join')) + '</button></div>';
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
    if (knot) {
      untangle(live, u);
      return;
    }
    if (nest) {
      nestboard(live, nb);
      return;
    }
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
    tell(live);
  }

  /**
   * Defensa del nido on the class screen, on the tablet: the nets of this student, to throw at the animal on the class
   * screen when it is theirs; the others hold other nets.
   *
   * @param {Object} live
   * @param {Object} n Its nest.
   */
  function nestboard(live, n) {
    var eggs = '';
    for (var i = 0; i < n.maxeggs; i++) {
      eggs += '<i class="nest-egg' + (i < n.eggs ? ' warm' : '') + '"></i>';
    }
    var head = '<div class="battle-head"><span class="battle-mini">' + T.icon('world') + '</span><div class="grow"><b>' +
      esc(t('app_nestboard')) + '</b><small>' + esc(n.team) + '</small></div><span class="nest-eggs" role="img" aria-label="' +
      esc(t('board_nest_eggs', n)) + '">' + eggs + '</span></div>';
    var last = n.last ? '<p class="nest-last ' + (n.last.right ? 'good' : 'bad') + '" role="status">' + esc(n.last.right ?
      t('board_nest_caught', n.last) : n.last.net ? t('board_nest_wrong', n.last) : t('board_nest_late', n.last)) + '</p>' : '';
    var body = '';
    if (n.phase === 'lobby') {
      body = '<p class="help">' + esc(t('app_untangle_wait')) + '</p>';
    } else if (n.phase === 'play' && n.animal) {
      body = last + '<p class="help-soft">' + esc(t('app_nestboard_look', T.capital(n.animal.label))) + '</p><p><b>' +
        esc(t('app_nestboard_mine')) + '</b></p><div class="fight-nets">' + n.nets.map(function(x) {
          return '<button type="button" class="btn btn-soft fight-net" data-nnet="' + esc(x.id) + '">' + esc(x.label) + '</button>';
        }).join('') + '</div><p class="help-soft">' + esc(t('app_nestboard_help')) + '</p>';
    } else {
      body = last + '<h2>' + esc(t(n.outcome === 'won' ? 'board_nest_won' : 'board_nest_lost')) + '</h2>' + (n.gem ? '<p><b>' +
        esc(t('app_teamknot_gem')) + '</b></p>' : '') + '<div class="actions"><button type="button" class="btn btn-main" ' +
        'data-back>' + esc(t('app_continue')) + '</button></div>';
    }
    layer.innerHTML = '<div class="battle-phone nest-phone" role="dialog" aria-modal="true" aria-label="' +
      esc(t('app_nestboard')) + '">' + head + body + '</div>';
    layer.querySelectorAll('[data-nnet]').forEach(function(b) {
      b.onclick = function() {
        layer.querySelectorAll('[data-nnet]').forEach(function(x) {
          x.disabled = true;
        });
        api('card', {item: b.dataset.nnet, round: n.index}).then(function(r) {
          show(r.live || null);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          state.sig = '';
          poll();
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
          // Without storage, a reload shows the end once more.
        }
        state.sig = '';
        show(live);
      };
    }
    if (T.fx && n.last && state.nestlast !== n.index) {
      state.nestlast = n.index;
      T.fx.sound(n.last.right ? 'right' : 'wrong');
    }
    var first = layer.querySelector('[data-nnet]:not([disabled]), [data-back]');
    if (first) {
      first.focus();
    }
  }

  /**
   * The great untangling on the phone: the strand of the team, the micro-challenge of the student, one after the other,
   * and the end.
   *
   * @param {Object} live
   * @param {Object} u Its untangling.
   */
  function untangle(live, u) {
    var clock = Math.floor(u.left / 60) + ':' + ('0' + (u.left % 60)).slice(-2);
    var head = '<div class="battle-head"><span class="battle-mini">' + T.embrollo(1) + '</span><div class="grow"><b>' +
      esc(t('app_untangle')) + '</b>' + (u.phase === 'play' ? '<small>' + (u.mine > 0 ? esc(t('app_untangle_mine', u.mine)) :
      esc(t('app_untangle_minefree'))) + '</small>' : '') + '</div>' + (u.phase === 'play' ? '<span class="untangle-clock small" ' +
      'data-uleft>' + clock + '</span>' : '') + '</div>';
    var body = '';
    if (u.phase === 'lobby') {
      body = '<p class="help">' + esc(t('app_untangle_wait')) + '</p>';
    } else if (u.phase === 'play' && u.item) {
      var last = u.last ? '<p class="untangle-last ' + (u.last.right ? 'good' : 'bad') + '" role="status">' +
        esc(t(u.last.right ? 'app_untangle_right' : 'app_untangle_wrong')) + '</p>' : '';
      body = last + (u.helping ? '<p class="help-soft">' + esc(t('app_untangle_helping', u.helping)) + '</p>' : '') +
        '<div class="burst-word" aria-hidden="true">' + esc(u.item.word) + '</div>' + (u.item.picture && T.artUrl([u.item.picture]) ?
        '<img class="untangle-pic" src="' + esc(T.artUrl([u.item.picture])) + '" alt="">' : '') + (u.item.label ?
        '<p class="untangle-thing">' + esc(u.item.label) + '</p>' : '') + '<p class="burst-prompt"><b>' + esc(u.item.prompt) + '</b></p><div class="opts">' +
        u.item.options.map(function(o, i) {
          return '<button type="button" class="opt" data-ua="' + i + '">' + esc(o) + '</button>';
        }).join('') + '</div>';
    } else {
      body = '<h2>' + esc(t(u.outcome === 'won' ? 'app_untangle_won' : 'app_untangle_time')) + '</h2><p>' +
        esc(t('app_untangle_loose', {loose: u.loose, strands: u.strands})) + '</p><div class="actions"><button type="button" ' +
        'class="btn btn-main" data-back>' + esc(t('app_continue')) + '</button></div>';
    }
    layer.innerHTML = '<div class="battle-phone untangle-phone" role="dialog" aria-modal="true" aria-label="' +
      esc(t('app_untangle')) + '">' + head + body + '</div>';
    layer.querySelectorAll('[data-ua]').forEach(function(b) {
      b.onclick = function() {
        layer.querySelectorAll('[data-ua]').forEach(function(x) {
          x.disabled = true;
        });
        api('answer', {round: u.number, answer: Number(b.dataset.ua)}).then(function(r) {
          show(r.live || null);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          state.sig = '';
          poll();
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
          // Without storage, a reload shows the end once more.
        }
        state.sig = '';
        show(live);
      };
    }
    if (T.fx && u.last && state.untanglelast !== u.number) {
      state.untanglelast = u.number;
      T.fx.sound(u.last.right ? 'right' : 'wrong');
    }
    if (u.phase === 'end' && state.celebrated !== live.id && T.fx) {
      state.celebrated = live.id;
      T.fx.sound(u.outcome === 'won' ? 'win' : 'chime');
    }
    var first = layer.querySelector('[data-ua]:not([disabled]), [data-back]');
    if (first) {
      first.focus();
    }
  }

  /**
   * By turns, what each stage is, said once: for the screen reader always; aloud, the knot and the lane, with the
   * reading aloud of the student.
   *
   * @param {Object} live
   */
  function tell(live) {
    var at = live.id + ':' + live.round + ':' + live.stage;
    if (!live.stage || state.told === at) {
      return;
    }
    state.told = at;
    var text = '';
    var aloud = false;
    if (live.stage === 'intent') {
      text = live.knot.name + ': «' + live.knot.claim.replace(/^«|»$/g, '') + '». ' + (live.intent && live.intent !== 'team' ?
        t('board_turn_intent_' + live.intent) : live.targetid === live.myteam ? t('app_turn_mine') :
        t('app_turn_goes', live.target));
      aloud = true;
    } else if (live.stage === 'prep') {
      text = t('app_turn_prep_say');
    } else if (live.stage === 'strike') {
      var lane = live.lane ? JSON.parse(live.lane) : null;
      text = t('app_turn_strike', live.knot.name) + '. ' + (lane && lane.prompt ? lane.prompt : lane ? t('board_lane_errors') :
        live.question ? live.question.text : '');
      aloud = true;
    } else if (live.stage === 'resolve' && live.done !== undefined) {
      text = t(live.done ? 'app_turn_lane_done' : 'app_turn_lane_failed');
    }
    said.textContent = text;
    if (aloud && text && T.adapted.readaloud && T.readAloud && window.speechSynthesis) {
      var el = document.createElement('div');
      el.textContent = text;
      window.speechSynthesis.cancel();
      T.readAloud(el);
    }
  }

  /**
   * By turns, what the team stakes in the turn: on the safe side, a strong pull or supporting the team attacked. Any of
   * the team chooses it while the knot is announced; the last one counts.
   *
   * @param {Object} live
   * @return {string}
   */
  function tactics(live) {
    if (!live.tactic) {
      return '';
    }
    return '<div class="battle-tactics" role="radiogroup" aria-label="' + esc(t('app_tactic')) + '"><small>' +
      esc(t('app_tactic')) + '</small><div class="pills">' + ['safe', 'strong', 'support'].map(function(k) {
        return '<button type="button" class="chip" role="radio" data-tactic="' + k + '" aria-checked="' + (live.tactic === k) +
          '"' + (live.cantactic ? '' : ' disabled') + '>' + esc(t('app_tactic_' + k)) + '</button>';
      }).join('') + '</div><p class="help-soft">' + esc(t('app_tactic_' + live.tactic + '_help')) + '</p></div>';
  }

  function head(live) {
    if (live.kind === 'vote') {
      return '<div class="battle-head"><span class="battle-mini">' + T.icon('world') + '</span><div class="grow"><b>' +
        esc(t('app_vote_title')) + '</b></div></div>';
    }
    var life = live.maxhp ? live.hp / live.maxhp : 1;
    return '<div class="battle-head"><span class="battle-mini">' + T.embrollo(life) + '</span><div class="grow"><b>' +
      esc(t('app_battle_title')) + '</b>' + (live.round ? '<small>' + esc(t(live.stage || live.maxcandles ? 'board_turn_round' :
      'app_battle_round', live)) + '</small>' : '') +
      '<span class="meter boss"><i style="width:' + Math.round(100 * life) + '%"></i></span>' + (live.maxcandles ?
      '<small class="candles-left">' + T.icon('lantern') + ' ' + esc(t('board_turn_candles', {lit: live.candles,
      all: live.maxcandles})) + '</small>' : '') + '</div>' + (T.fx && !live.outcome && T.fx.musicAllowed() ?
      '<button type="button" class="btn btn-soft icon-btn battle-music" data-music aria-pressed="' + T.fx.musicOn() +
      '" aria-label="' + esc(t('board_music')) + '" title="' + esc(t('board_music')) + '">♪</button>' : '') + '</div>';
  }

  /**
   * By turns, the deck of the team: a card for each thing its members carry, played once a battle by anyone in it.
   *
   * @param {Object} live
   * @return {string}
   */
  function deck(live) {
    var cards = live.cards || [];
    if (!cards.length) {
      return '';
    }
    return '<div class="battle-deck"><p class="help-soft">' + esc(t('app_deck')) + '</p><div class="battle-cards">' +
      cards.map(function(c) {
        return '<button type="button" class="battle-card' + (c.used ? ' used' : '') + '" data-card="' + esc(c.item) + '"' +
          (c.used ? ' disabled' : '') + '><b>' + esc(t('app_card_' + c.item)) + '</b><small>' + esc(c.used ?
          t('app_card_used') : t('app_card_' + c.item + '_what')) + '</small></button>';
      }).join('') + '</div></div>';
  }

  /**
   * The double techniques of the team this turn: two verbs of the team together, with their name and what they do.
   *
   * @param {Object} live
   * @return {string}
   */
  function comboNote(live) {
    return (live.combos || []).map(function(code) {
      return '<p class="battle-combo">' + esc(t('app_combo', {name: t('app_combo_' + code), what: t('app_combo_' + code +
        '_what')})) + '</p>';
    }).join('');
  }

  /**
   * By turns, the pull after answering or the parry after the shield: a knot crosses a bar in three seconds and the
   * touch counts if it is in the clear zone. Drawn again on each poll, it goes on from where it was; once touched, or
   * once the knot has crossed, it is not there any more.
   *
   * @param {Object} live
   * @param {string} kind pull or parry.
   * @return {string}
   */
  function timing(live, kind) {
    var key = live.id + ':' + live.round + ':' + kind;
    var at = state.timing[key] = state.timing[key] || {start: Date.now(), done: ''};
    var gone = Date.now() - at.start;
    if (at.done) {
      return '<p class="timing-said ' + (at.done === 'hit' ? 'good' : '') + '">' + esc(t('app_' + kind + '_' + at.done)) + '</p>';
    }
    if (gone >= TIMING) {
      return '';
    }
    return '<div class="timing ' + kind + '" data-timing="' + kind + '"><p class="timing-title"><b>' +
      esc(t('app_' + kind + '_title')) + '</b> ' + esc(t('app_' + kind + '_help')) + '</p><div class="timing-track" ' +
      'aria-hidden="true"><span class="timing-zone" style="left:' + (ZONE[0] * 100) + '%;width:' + ((ZONE[1] - ZONE[0]) * 100) +
      '%"></span><span class="timing-knot" style="animation-duration:' + TIMING + 'ms;animation-delay:-' + gone + 'ms">' +
      '</span></div><button type="button" class="btn btn-main" data-timingtap="' + kind + '">' + esc(t('app_' + kind + '_tap')) +
      '</button></div>';
  }

  function body(live) {
    if (live.kind === 'vote') {
      return voteBody(live);
    }
    if (live.paused && !live.outcome) {
      return '<p class="battle-paused">' + T.icon('pause') + esc(t('board_battle_paused')) + '</p>';
    }
    if (live.outcome === 'won') {
      var p = live.prize;
      var prize = p ? '<p class="lead">' + esc(t('app_battle_prize', p)) + (p.capped ? ' (' + esc(t('app_capped', p.capped)) +
        ')' : '') + '</p>' + (p.best ? '<p>' + esc(live.maxcandles ? (p.item ? t('app_battle_classitem', p.item) : t('app_battle_class')) :
        p.item ? t('app_battle_bestitem', p.item) : t('app_battle_best')) +
        '</p>' : '') + (p.final ? '<p>' + esc(t('app_battle_finalprize', p.final)) + '</p>' : '') + (p.grew ? '<p>' +
        esc(t('app_battle_grew')) + '</p>' : '') : '';
      return '<div class="battle-end won"><h2>' + esc(t('app_battle_won')) + '</h2>' + prize +
        '<button class="btn btn-main" data-back>' + esc(t('app_battle_back')) + '</button></div>';
    }
    if (live.outcome === 'lost') {
      return '<div class="battle-end"><h2>' + esc(t('app_battle_escaped')) + '</h2><p>' + esc(t(live.maxcandles ?
        'app_battle_lost_turns' : 'app_battle_lost')) + '</p>' +
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
        '</p><p class="knot-claim">«' + esc(live.knot.claim.replace(/^«|»$/g, '')) + '»</p>' + intent + '</div>' + tactics(live) +
        comboNote(live) +
        (live.stage === 'prep' && !T.adapted.notime ? '<p class="battle-left" data-left="' + live.stageleft +
        '" data-say="app_turn_prep" aria-live="off">' + esc(t('app_turn_prep', live.stageleft)) + '</p>' : '') + token(live);
    }
    if (!live.round || !live.question) {
      return '<p class="battle-wait">' + esc(t('app_battle_wait')) + '</p>';
    }
    if (live.lane) {
      // By turns, a lane of another engine: drawn by the lanes of exercises.js once the layer is there.
      var laneNote = live.revealed ? '' : live.mine >= 0 ? '<p class="battle-note">' + esc(t('app_battle_answered')) + '</p>' :
        !live.open ? '<p class="battle-note">' + esc(t('app_battle_closed')) + '</p>' : live.left >= 0 && !T.adapted.notime ?
        '<p class="battle-left" data-left="' + live.left + '" aria-live="off">' + esc(t('app_battle_left', live.left)) + '</p>' :
        '';
      return (live.stage === 'strike' ? '<p class="knot-name">' + esc(t('app_turn_strike', live.knot.name)) + '</p>' : '') +
        '<div class="lane-play" data-lane></div>' + (live.trail && !live.revealed ? '<p class="battle-trail">' +
        esc(t('app_token_trail_shown', live.trail)) + '</p>' : '') + comboNote(live) + laneNote + (live.pull ? timing(live, 'pull') : '') +
        turnNote(live) + token(live);
    }
    var q = live.question;
    var answered = live.mine >= 0;
    var options = q.options.map(function(o, i) {
      var sniffed = live.sniffed === i && !live.revealed;
      var struck = (live.struck === i || live.struck2 === i || sniffed) && !live.revealed;
      var cls = 'battle-option' + (live.mine === i ? ' mine' : '') + (live.revealed && q.correct === i ? ' right' : '') +
        (struck ? ' struck' : '');
      return '<button type="button" class="' + cls + '" data-answer="' + i + '"' + (answered || !live.open || struck ?
        ' disabled' : '') + (struck ? ' title="' + esc(t(sniffed ? 'app_battle_sniffed' : 'app_battle_struck')) + '"' : '') +
        '><b>' + LETTERS[i] + '</b><span>' + esc(o) + '</span></button>';
    }).join('');
    var note = '';
    if (live.revealed) {
      note = live.mine < 0 ? t('app_battle_none') : live.mine === q.correct ? t('app_battle_right') : t('app_battle_wrong');
      note = '<p class="battle-note">' + esc(note) + '</p><p class="help-soft">' + esc(q.why || '') + '</p>';
    } else if (answered) {
      note = '<p class="battle-note">' + esc(t('app_battle_answered')) + '</p>' + (live.pull ? timing(live, 'pull') : '');
    } else if (!live.open) {
      note = '<p class="battle-note">' + esc(t('app_battle_closed')) + '</p>';
    } else if (live.left >= 0 && !T.adapted.notime) {
      note = '<p class="battle-left" data-left="' + live.left + '" aria-live="off">' + esc(t('app_battle_left', live.left)) + '</p>';
    }
    var trail = (live.trail && !live.revealed ? '<p class="battle-trail">' + esc(t('app_token_trail_shown', live.trail)) +
      '</p>' : '') + (live.sniffed !== undefined && live.sniffed !== null && !live.revealed ? '<p class="battle-trail">' +
      esc(t('app_battle_sniff', live.sniffplace)) + '</p>' : '');
    if (live.stage === 'resolve' && live.done !== undefined) {
      note += turnNote(live);
    } else if (live.stage === 'resolve' && live.target) {
      var fell = live.counter || live.shielded;
      note += '<p class="turn-outcome' + (fell ? ' fell' : ' undone') + '">' + esc(t(!fell ? 'app_turn_undone' : live.shielded ?
        'app_turn_shield' : 'app_turn_fell', live.target)) + '</p>' + (live.parried ? '<p class="turn-outcome undone">' +
        esc(t('app_turn_parried')) + '</p>' : '') + (live.knot.laugh ? '<p class="knot-laugh">«' +
        esc(live.knot.laugh.replace(/^«|»$/g, '')) + '»</p>' : '');
    }
    return (live.stage === 'strike' ? '<p class="knot-name">' + esc(t('app_turn_strike', live.knot.name)) + '</p>' : '') +
      '<p class="battle-q">' + esc(q.text) + '</p>' + trail + comboNote(live) + '<div class="battle-options">' + options +
      '</div>' + note +
      token(live);
  }

  /**
   * By turns, at the resolution: what the team did with its lane, the knot if it broke, and what the Embrollo did.
   *
   * @param {Object} live
   * @return {string}
   */
  function turnNote(live) {
    if (live.stage !== 'resolve' || live.done === undefined) {
      return '';
    }
    // Their own figure: it jumps if the team undid its lane; if not, it staggers and stays on its feet.
    var hero = T.myHero ? T.myHero(false) : '';
    return (hero ? '<span class="turn-hero ' + (live.done ? 'jump' : 'sway') + (T.moves && !T.moves() ? ' still' : '') +
      '" aria-hidden="true">' + hero + '</span>' : '') + '<p class="turn-outcome' + (live.done ? ' undone' : ' fell') + '">' +
      esc(t(live.done ? 'app_turn_lane_done' :
      'app_turn_lane_failed')) + '</p>' + (live.knot.broken ? '<p class="turn-outcome undone">' +
      esc(t('board_turn_broken')) + '</p>' : '') + (live.counter && !live.recovered ? '<p class="turn-outcome fell">' +
      esc(t(live.intent === 'all' ? 'board_turn_candle_all' : 'board_turn_candle', live.target)) + '</p>' : '') +
      (live.shielded && live.intent === 'all' ? '<p class="turn-outcome undone">' + esc(t('board_turn_shield_all')) + '</p>' :
      '') + (live.knot.laugh ? '<p class="knot-laugh">«' + esc(live.knot.laugh.replace(/^«|»$/g, '')) + '»</p>' : '');
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
    // By turns, the verb is ready from the moment the knot is announced, in big while the roles get ready.
    var ready = live.open || live.stage === 'intent' || live.stage === 'prep';
    var big = live.stage === 'intent' || live.stage === 'prep';
    // The verbs of a missing role and the deck of the team, while the roles get ready: during the lane, the lane is what
    // matters.
    ahead += big ? covers(live) + deck(live) : '';
    // A glance or a trail another team aimed at this one, and the weak point of the knot a glance read.
    if (live.glancefrom) {
      ahead = '<p class="battle-trail">' + esc(t('app_token_glance_from', live.glancefrom)) + '</p>' + ahead;
    }
    if (live.trailfrom) {
      ahead = '<p class="battle-trail">' + esc(t('app_token_trail_from', live.trailfrom)) + '</p>' + ahead;
    }
    if (live.weakness) {
      ahead = '<p class="battle-trail">' + esc(t('app_token_weakness', live.weakness)) + '</p>' + ahead;
    }
    // A dizzy team does not strike: another team may carry its lane, with its action of the turn.
    if ((live.dizzy || []).indexOf(live.myteam) >= 0) {
      ahead = '<p class="battle-dizzy">' + esc(live.carriedby ? t('app_dizzy_carried', live.carriedby) : t('app_dizzy_mine')) +
        '</p>' + ahead;
    }
    if (live.carrying) {
      return ahead + '<p class="battle-trail">' + esc(t('app_carrying', live.carrying)) + '</p>';
    }
    if (live.carry && live.carry.length && ready) {
      ahead += '<div class="battle-token carry"><p class="help-soft">' + esc(t('app_carry_help')) + '</p>' +
        live.carry.map(function(x) {
          return '<button type="button" class="btn btn-soft" data-carry="' + x.id + '">' + esc(t('app_carry_on', x.name)) +
            '</button>';
        }).join('') + '</div>';
    }
    if (live.tokenused) {
      // The shield just put: raised in time, a blow on it bounces back.
      return ahead + '<p class="help-soft battle-token-used">' + esc(live.tokenrest ? t('app_token_rest', live.tokenrest) :
        t('app_token_used')) + '</p>' + (live.parry ? timing(live, 'parry') : '');
    }
    if (!ready) {
      return ahead;
    }
    var aims = live.glancetargets || live.trailtargets || [];
    if (aims.length) {
      // «Wide glance» and «Wide trail»: their own team, and another one, the one they choose.
      return ahead + '<div class="battle-token"><p class="help-soft">' + esc(t('app_token_' + live.token + '_turns')) + '</p>' +
        aims.map(function(x) {
          return '<button type="button" class="btn ' + (big ? 'btn-main' : 'btn-soft') + '" data-token data-target="' + x.id +
            '">' + esc(t('app_token_glance_wide', x.name)) + '</button>';
        }).join('') + '<button type="button" class="btn btn-soft" data-token>' + esc(t('app_token_on',
        t('app_token_ownteam'))) + '</button></div>';
    }
    if ((live.token === 'shield' || live.token === 'cheer') && live.targets && live.targets.length > 1) {
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

  /**
   * By turns, the verbs of the roles nobody in the team has today: anyone can use one, once in the battle. Without
   * saying why the role is missing.
   *
   * @param {Object} live
   * @return {string}
   */
  function covers(live) {
    if (!live.covers || !live.covers.length) {
      return '';
    }
    var teams = (live.targets || []).filter(function(x) {
      return x.id === live.myteam;
    }).concat((live.targets || []).filter(function(x) {
      return x.id !== live.myteam;
    }));
    // The free cover of the battle used, the reserve of the team pays the next one.
    return '<div class="battle-token cover"><p class="help-soft">' + esc(live.coverpaid ? t('app_token_cover_reserve',
      {cost: live.coverpaid, left: live.reserve}) : t('app_token_cover')) + '</p>' +
      live.covers.map(function(kind) {
        var on = kind === 'shield' || kind === 'cheer' ? teams : [];
        if (!on.length) {
          return '<button type="button" class="btn btn-soft" data-token data-cover="' + kind + '">' +
            esc(t('app_token_' + kind + '_turns')) + '</button>';
        }
        return on.map(function(x) {
          return '<button type="button" class="btn btn-soft" data-token data-cover="' + kind + '" data-target="' + x.id + '">' +
            esc(t('app_token_cover_on', {verb: t('app_verb_' + kind), team: x.id === live.myteam ? t('app_token_ownteam') :
            x.name})) + '</button>';
        }).join('');
      }).join('') + '</div>';
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
    layer.querySelectorAll('[data-tactic]').forEach(function(b) {
      b.onclick = function() {
        layer.querySelectorAll('[data-tactic]').forEach(function(x) {
          x.disabled = true;
        });
        api('tactic', {item: b.dataset.tactic}).then(function(r) {
          state.sig = '';
          show(r.live || null);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          state.sig = '';
          poll();
        });
      };
    });
    layer.querySelectorAll('[data-timingtap]').forEach(function(b) {
      b.onclick = function() {
        var kind = b.dataset.timingtap;
        var at = state.timing[live.id + ':' + live.round + ':' + kind];
        if (!at || at.done) {
          return;
        }
        var where = (Date.now() - at.start) / TIMING;
        var hit = where >= ZONE[0] && where <= ZONE[1];
        at.done = hit ? 'hit' : 'miss';
        if (T.fx) {
          T.fx.sound(hit ? (kind === 'parry' ? 'shield' : 'right') : 'tap');
        }
        if (navigator.vibrate && hit) {
          navigator.vibrate([20, 30, 20]);
        }
        var box = b.closest('[data-timing]');
        box.outerHTML = '<p class="timing-said ' + (hit ? 'good' : '') + '">' + esc(t('app_' + kind + '_' + at.done)) + '</p>';
        api(kind, {round: live.round, answer: hit ? 1 : 0}).catch(function() {
          // A touch that did not arrive is a touch that did not count.
          return null;
        });
      };
    });
    var tune = layer.querySelector('[data-music]');
    if (tune) {
      tune.onclick = function() {
        tune.setAttribute('aria-pressed', String(T.fx.music()));
      };
    }
    var laneEl = layer.querySelector('[data-lane]');
    if (laneEl && T.lane) {
      // A lane of another engine: what the student did goes to the server, which says whether it undoes it.
      T.lane(laneEl, JSON.parse(live.lane), {key: live.id + ':' + live.round, sent: live.mine >= 0, open: live.open,
        onsend: function(payload) {
          if (navigator.vibrate) {
            navigator.vibrate(30);
          }
          api('strike', {round: live.round, payload: JSON.stringify(payload)}).then(function(r) {
            show(r.live || null);
          }).catch(function(e) {
            T.toast(esc(e.message), true);
            state.sig = '';
            poll();
          });
        }});
    }
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
    layer.querySelectorAll('[data-carry]').forEach(function(use) {
      use.onclick = function() {
        layer.querySelectorAll('[data-carry], [data-token]').forEach(function(b) {
          b.disabled = true;
        });
        api('carry', {target: Number(use.dataset.carry)}).then(function(r) {
          state.sig = '';
          show(r.live || null);
        }).catch(function(e) {
          layer.querySelectorAll('[data-carry], [data-token]').forEach(function(b) {
            b.disabled = false;
          });
          T.toast(esc(e.message), true);
        });
      };
    });
    layer.querySelectorAll('[data-card]').forEach(function(card) {
      card.onclick = function() {
        layer.querySelectorAll('[data-card]').forEach(function(b) {
          b.disabled = true;
        });
        api('card', {item: card.dataset.card}).then(function(r) {
          state.sig = '';
          show(r.live || null);
          if (T.fx) {
            T.fx.sound('chime');
          }
        }).catch(function(e) {
          state.sig = '';
          T.toast(esc(e.message), true);
          poll();
        });
      };
    });
    layer.querySelectorAll('[data-token]').forEach(function(use) {
      use.onclick = function() {
        layer.querySelectorAll('[data-token]').forEach(function(b) {
          b.disabled = true;
        });
        api('token', {target: Number(use.dataset.target || 0), cover: use.dataset.cover || ''}).then(function(r) {
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
        left.textContent = t(left.dataset.say || 'app_battle_left', secs);
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
