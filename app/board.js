/**
 * Tesapé: the class screen, for the projector or the whiteboard. The map of the mission with the lanterns of the class,
 * the event of the day and the teams with their portraits; quick touches of saber and gems on students or whole teams.
 * It refreshes itself (the battles every few seconds, the rest every minute), so what the teacher does from the phone
 * shows up here too. Only good news on this screen: nothing that takes something away from a student in front of the
 * class.
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
  var app = document.getElementById('app');
  var REFRESH = 5000;
  var FULL = 60000;
  var LIVE = 1500;
  var LETTERS = 'ABCD';
  var CARD_ICON = {calm: 'energy', breeze: 'saber', footprint: 'world', exchange: 'breath'};
  var state = {board: null, cls: null, sel: {}, reveal: false, busy: false, sig: '', live: null, seen: 0, timer: 0,
    battle: {mode: 'phones', count: 6, secs: 30}, auto: 0, print: '', scene: {chapter: 0, mode: 'phones'}};
  var VOTES = [
    {letter: 'A', colour: '#3f6fb5', shape: '<circle cx="50" cy="50" r="44"/>'},
    {letter: 'B', colour: '#2f8f6a', shape: '<path d="M50 4 L96 92 L4 92Z"/>'},
    {letter: 'C', colour: '#d39a2a', shape: '<rect x="8" y="8" width="84" height="84" rx="6"/>'},
    {letter: 'D', colour: '#7b5bc4', shape: '<path d="M50 3 L62 37 L98 38 L69 59 L80 95 L50 74 L20 95 L31 59 L2 38 L38 37Z"/>'}
  ];

  function can(cap) {
    return cfg.caps && cfg.caps[cap];
  }

  /* ---------- Loading and refreshing ---------- */

  function load(first) {
    if (first) {
      app.innerHTML = '<div class="loading"><div><div class="lamp"></div>' + esc(t('app_loading')) + '</div></div>';
    }
    return Promise.all([
      T.api('get_board', {courseid: cfg.courseid}),
      T.api('get_class', {courseid: cfg.courseid, board: true}),
      T.api('live_host', {courseid: cfg.courseid, action: 'state'})
    ]).then(function(r) {
      state.loaded = Date.now();
      state.board = r[0];
      state.cls = r[1];
      state.live = r[2].live || null;
      modes();
      T.family = r[1].region.family;
      schedule();
      var sig = JSON.stringify(r);
      if (first || sig !== state.sig) {
        state.sig = sig;
        render();
      }
    }).catch(function(e) {
      if (first) {
        app.innerHTML = '<div class="story"><h2>' + esc(t('app_error')) + '</h2><p class="help">' + esc(e.message) +
          '</p><div class="actions"><button class="btn btn-main" data-retry>' + esc(t('app_retry')) + '</button></div></div>';
        app.querySelector('[data-retry]').onclick = function() {
          load(true);
        };
      }
    });
  }

  /**
   * How the class plays, once for the course: with a tablet each (the scene votes and the battle on the phones), or
   * without devices (cards held up and the battle aloud). Who cannot change the course changes it for this screen.
   */
  function modes() {
    var devices = state.devices !== undefined ? state.devices : state.cls.region.devices !== false;
    state.scene.mode = devices ? 'phones' : 'cards';
    state.battle.mode = devices ? 'phones' : 'aloud';
  }

  function devicesPicker() {
    var on = state.scene.mode === 'phones';
    return '<span class="pills devices" role="group" aria-label="' + esc(t('board_devices')) + '"><span class="devices-label">' +
      esc(t('board_devices')) + '</span>' + [['1', 'board_devices_tablets'], ['0', 'board_devices_none']].map(function(d) {
        return '<button type="button" class="chip" data-devices="' + d[0] + '" aria-pressed="' + (on === (d[0] === '1')) + '">' +
          esc(t(d[1])) + '</button>';
      }).join('') + '</span>';
  }

  function refresh() {
    if (document.hidden || state.busy || battling()) {
      return;
    }
    // Everything every half minute; in between, only whether a battle or a vote has started somewhere else.
    if (Date.now() - (state.loaded || 0) >= FULL) {
      load(false);
      return;
    }
    T.api('live_host', {courseid: cfg.courseid, action: 'state'}).then(function(r) {
      var live = r.live || null;
      var was = state.live;
      if ((live && live.id) !== (was && was.id) || (live && live.outcome) !== (was && was.outcome)) {
        load(false);
      }
    }).catch(function() {
      // A poll that fails is tried again at the next one.
    });
  }

  /* ---------- The battle against the Embrollo ---------- */

  var SEEN = 'tesape-board-seen-' + cfg.courseid;
  try {
    // The battle the teacher already closed stays closed after reloading the page of the class screen.
    state.seen = Number(window.sessionStorage.getItem(SEEN)) || 0;
  } catch (e) {
    state.seen = 0;
  }

  function battling() {
    var l = state.live;
    return !!(l && l.kind === 'battle' && (!l.outcome || state.seen !== l.id));
  }

  function schedule() {
    clearTimeout(state.timer);
    if (battling() && !document.hidden) {
      state.timer = setTimeout(function() {
        host('state');
      }, LIVE);
    }
  }

  /**
   * Asks the server to do something with the battle, and shows how it is going.
   *
   * @param {string} action
   * @param {Object} extra
   * @return {Promise}
   */
  function host(action, extra) {
    if (action !== 'state') {
      // One action at a time: a second tap while the first is on its way would act twice.
      if (state.hosting) {
        return Promise.resolve();
      }
      state.hosting = true;
      clearTimeout(state.autotimer);
      app.querySelectorAll('.battle-board button').forEach(function(b) {
        if (!b.hasAttribute('data-full')) {
          b.disabled = true;
        }
      });
    }
    clearTimeout(state.timer);
    return T.api('live_host', Object.assign({courseid: cfg.courseid, action: action}, extra || {})).then(function(r) {
      if (action !== 'state') {
        state.hosting = false;
      }
      state.live = r.live || null;
      if (battling()) {
        // A poll that brings nothing new only moves the countdown; an action draws it all again.
        drawBattle(action !== 'state');
      } else {
        load(false);
      }
    }).catch(function(e) {
      if (action !== 'state') {
        state.hosting = false;
        T.toast(esc(e.message), true);
        if (battling()) {
          drawBattle(true);
        }
      }
    }).then(schedule);
  }

  function battleCard() {
    if (!state.board.mission || !can('award')) {
      return '';
    }
    var b = state.battle;
    var pills = function(key, values, label) {
      return '<span class="pills">' + values.map(function(v) {
        return '<button class="chip" data-battle="' + key + ':' + v + '" aria-pressed="' + (String(b[key]) === String(v)) + '">' +
          esc(label(v)) + '</button>';
      }).join('') + '</span>';
    };
    return '<section class="card board-battle"><div class="kicker">' + esc(t('board_battle')) + '</div><div class="event-head">' +
      '<span class="event-icon">' + T.embrollo(1) + '</span><p>' + esc(t('board_battle_help')) + '</p></div>' +
      '<div class="battle-options-row"><span>' + esc(t('board_battle_count')) + '</span>' +
      pills('count', [4, 6, 8, 10], String) + '</div><div class="battle-options-row"><span>' + esc(t('board_battle_secs')) +
      '</span>' + pills('secs', [0, 20, 30, 45, 60], function(v) {
        return v ? v + ' s' : t('board_battle_nolimit');
      }) + '</div>' + (state.board.mission.final ? '<div class="battle-options-row">' + pills('final', [0, 1], function(v) {
        return v ? t('board_battle_final') : t('board_battle');
      }) + (b.final ? '<small class="help-soft">' + esc(t('board_battle_final_help')) + '</small>' : '') + '</div>' : '') +
      '<div class="actions"><button class="btn btn-main" data-battlestart>' + esc(t('board_battle_start')) +
      '</button></div></section>';
  }

  /**
   * What the class hears and sees on the class screen, once each: a hit on the Embrollo, and the end of the battle.
   *
   * @param {Object} l
   */
  function battleFx(l) {
    if (!T.fx) {
      return;
    }
    if (l.maxcandles) {
      turnFx(l);
    }
    var key = l.id + ':' + (l.outcome || (l.revealed ? 'r' + l.round : ''));
    if (key === state.fxlast || key === l.id + ':') {
      return;
    }
    state.fxlast = key;
    if (l.maxcandles && l.outcome) {
      // By turns, the end has its own sounds: the jar or the escape, and its sting.
      T.fx.sound(l.outcome === 'won' ? 'jar' : 'escape');
      setTimeout(function() {
        T.fx.sound(l.outcome === 'won' ? 'sting.win' : 'sting.lost');
      }, 700);
      if (l.outcome === 'won') {
        T.fx.confetti();
      }
      return;
    }
    if (l.maxcandles && l.revealed) {
      // The turn resolved: the shield that stops the blow, the candle that goes out, the knot that breaks.
      T.fx.sound(l.shielded ? 'shield' : l.counter && !l.recovered ? 'counter' : l.knot && l.knot.broken ? 'hit' :
        (l.right || 0) > 0 ? 'hit' : 'wrong');
      return;
    }
    if (l.outcome === 'won') {
      T.fx.sound('win');
      T.fx.confetti();
      setTimeout(function() {
        T.fx.sound('applause');
      }, 600);
    } else if (l.outcome) {
      T.fx.sound('wrong');
    } else if ((l.right || 0) > 0) {
      T.fx.sound('hit');
    } else {
      T.fx.sound('wrong');
    }
  }

  /**
   * By turns, the music and the moments of the battle: its start, its music (the second piece once the Embrollo is
   * frayed), the moment it frays; and back to the soft music when it ends.
   *
   * @param {Object} l
   */
  function turnFx(l) {
    var frayed = l.maxhp && l.hp / l.maxhp <= 0.6;
    T.fx.musicKind(l.outcome || !l.round ? '' : frayed ? 'battle2' : 'battle1');
    if (l.round === 1 && l.stage === 'intent' && state.started !== l.id) {
      state.started = l.id;
      T.fx.sound('battle.start');
    }
    if (frayed && !l.outcome && state.frayed !== l.id) {
      state.frayed = l.id;
      T.fx.sound('phase2');
    }
  }

  /**
   * The heartbeat of the last five seconds of a lane, once a second, on every poll that brings a new second.
   *
   * @param {Object} l
   */
  function beat(l) {
    if (!T.fx || !l || !l.maxcandles || l.paused || l.stage !== 'strike' || l.left < 1 || l.left > 5 || state.beatlast === l.id + ':' +
      l.round + ':' + l.left) {
      return;
    }
    state.beatlast = l.id + ':' + l.round + ':' + l.left;
    T.fx.sound('tick');
  }

  /**
   * Draws the battle on the class screen. Every 1.5 s a poll brings it again: if nothing seen has changed, only the
   * countdown and the count of answers move, so that a tap on a button is never lost while it is drawn again.
   *
   * @param {boolean} force Draw it all, after an action of the teacher.
   */
  function drawBattle(force) {
    var l = state.live;
    beat(l);
    var sig = JSON.stringify(Object.assign({}, l, {left: 0, answered: 0, stageleft: 0}));
    if (!force && sig === state.battlesig && app.querySelector('.battle-board')) {
      var left = app.querySelector('[data-left]');
      if (left && l.left >= 0) {
        left.textContent = l.left + ' s';
        left.dataset.left = l.left;
      }
      var stageleft = app.querySelector('[data-stageleft]');
      if (stageleft && l.stageleft >= 0) {
        stageleft.textContent = t(stageleft.dataset.stageleft, l.stageleft);
      }
      var answered = app.querySelector('[data-answered]');
      if (answered) {
        answered.textContent = t('board_battle_answered', l);
      }
      return;
    }
    state.battlesig = sig;
    // The Embrollo shakes once when it is hit, not at every drawing after.
    var hit = l.revealed && (l.right || 0) > 0 && state.hitlast !== l.id + ':' + l.round;
    if (hit) {
      state.hitlast = l.id + ':' + l.round;
    }
    // By turns, when the attack fell and was not stopped, it laughs: once too.
    var laugh = l.stage === 'resolve' && l.knot && l.knot.laugh && state.laughlast !== l.id + ':' + l.round;
    if (laugh) {
      state.laughlast = l.id + ':' + l.round;
    }
    var life = l.maxhp ? l.hp / l.maxhp : 1;
    var main;
    var buttons = '';
    var bm = state.board.mission;
    // The end of the mission, and what the Great Book remembers of the class.
    var ending = l.final && l.outcome === 'won' && bm && bm.ending ? '<p class="battle-ending">' + esc(bm.ending) + '</p>' +
      (bm.remembered && bm.remembered.length ? '<div class="memory"><small>' + esc(t('board_remembered')) + '</small>' +
      bm.remembered.map(function(line) {
        return '<p>' + esc(line) + '</p>';
      }).join('') + '</div>' : '') : '';
    if (l.outcome) {
      main = '<div class="battle-end ' + l.outcome + '"><h2>' + esc(t('board_battle_' + l.outcome)) + '</h2>' + ending +
        '<p class="lead">' + esc(l.outcome === 'won' ? t('board_battle_wonhelp', l.rewarded) : t(l.maxcandles ?
        'board_battle_losthelp_turns' : 'board_battle_losthelp')) +
        '</p></div>';
      buttons = '<button class="btn btn-main" data-dismiss>' + esc(t('board_battle_back')) + '</button>';
    } else if (!l.round) {
      main = '<div class="battle-lobby">' + (l.rematch ? '<p class="turn-outcome fell">' + esc(t('board_battle_rematch',
        l.rematch)) + '</p>' : '') + '<p class="lead">' + esc(t(l.mode === 'phones' ? 'board_battle_join' :
        'board_battle_aloudhelp')) + '</p>' + (l.mode === 'phones' ? '<p class="battle-count">' +
        esc(t('board_battle_joined', l.joined)) + '</p>' : '') + '</div>';
      buttons = '<button class="btn btn-main" data-battlenext' + (l.mode === 'phones' && !l.joined ? ' disabled' : '') + '>' +
        esc(t(l.maxcandles ? 'board_turn_first' : 'board_battle_first')) + '</button>' + (l.kind === 'battle' ?
        '<button class="btn btn-soft" data-battlefinish>' +
        esc(t('board_battle_cancel')) + '</button>' : '');
    } else if (l.stage === 'intent' || l.stage === 'prep') {
      // By turns: the Embrollo lets a knot go, says what it claims and which team it goes for; the roles get ready.
      main = '<p class="battle-qround">' + esc(t(l.stage ? 'board_turn_round' : 'board_battle_round', l)) + '</p><div class="knot-call"><p class="knot-name">' +
        esc(l.knot.name) + '</p><p class="knot-claim">«' + esc(l.knot.claim.replace(/^«|»$/g, '')) + '»</p>' +
        intentLine(l) + '</div>' +
        (l.stage === 'prep' ? '<p class="battle-count" data-stageleft="board_turn_prep" aria-live="off">' +
        esc(t('board_turn_prep', l.stageleft)) + '</p>' : '');
      main += (l.tokens || []).map(function(k) {
        return '<p class="battle-token-used">' + esc(k.target ? t('board_token_' + k.kind + '_on', k) :
          t('board_token_' + k.kind, k.team || '')) + '</p>';
      }).join('');
    } else if (l.lanes && l.lanes.length) {
      // By turns: each team has its lane in its tablets; here, which one, and once resolved, whether it was undone.
      main = '<p class="battle-qround">' + esc(t('board_turn_round', l)) + (l.stage === 'strike' ? ' · ' +
        esc(t('board_turn_strike', l.knot.name)) : '') + '</p><ul class="lanes">' + l.lanes.map(function(lane) {
          var q = lane.question;
          return '<li class="lane' + (l.revealed ? (lane.done ? ' done' : ' failed') : '') +
            (l.tangled.indexOf(lane.teamid) >= 0 ? ' tangled' : '') + '"><b>' + esc(lane.team) + '</b><span>' +
            esc(q.text) + '</span>' + (l.revealed ? '<small>' + (lane.done ? '✓ ' : '✗ ') +
            esc(q.options[q.correct] || '') + '</small>' : '') + '</li>';
        }).join('') + '</ul>';
      main += (l.tokens || []).map(function(k) {
        return '<p class="battle-token-used">' + esc(k.target ? t('board_token_' + k.kind + '_on', k) :
          t('board_token_' + k.kind, k.team || '')) + '</p>';
      }).join('');
      if (l.revealed) {
        main += turnOutcome(l);
        buttons = l.round < l.total && l.candles > 0 && l.hp > 0 ? '<button class="btn btn-main" data-battlenext>' +
          esc(t('board_turn_nextnow')) + '</button>' : '';
      } else {
        main += '<p class="battle-count"><span data-answered>' + esc(t('board_battle_answered', l)) + '</span>' +
          (l.left >= 0 ? ' <span class="battle-left" data-left="' + l.left + '" aria-live="off">' + l.left + ' s</span>' : '') +
          '</p>';
        buttons = '<button class="btn btn-main" data-battlereveal>' + esc(t('board_battle_reveal')) + '</button>';
      }
    } else {
      var q = l.question;
      main = '<p class="battle-qround">' + esc(t(l.stage ? 'board_turn_round' : 'board_battle_round', l)) + (l.stage === 'strike' ? ' · ' +
        esc(t('board_turn_strike', l.knot.name)) : '') + '</p><p class="battle-q">' + esc(q.text) +
        '</p><ol class="battle-board-options">' + q.options.map(function(o, i) {
          return '<li class="' + (l.revealed && q.correct === i ? 'right' : '') + '"><b>' + LETTERS[i] + '</b>' + esc(o) + '</li>';
        }).join('') + '</ol>';
      main += (l.tokens || []).map(function(k) {
        return '<p class="battle-token-used">' + esc(k.target ? t('board_token_' + k.kind + '_on', k) :
          t('board_token_' + k.kind, k.team || '')) + '</p>';
      }).join('');
      if (l.revealed) {
        // By turns, what happened is told by the turn; without them, as it was.
        var told = l.stage === 'resolve' && l.target;
        main += '<p class="why">' + esc(q.why || '') + '</p>' + (l.mode === 'phones' ? '<p class="battle-count">' +
          esc(t('board_battle_rights', l.right || 0)) + '</p>' : '<p class="help-soft">' + esc(t('board_battle_markhelp')) +
          '</p>') + (l.counter && !told ? '<p class="battle-counter">' + esc(t('board_battle_counter', l.counter)) + '</p>' : '') +
          (l.shielded && !told ? '<p class="battle-counter shield">' + esc(t('board_battle_shielded')) + '</p>' : '') +
          turnOutcome(l);
        buttons = l.round < l.total ? '<button class="btn btn-main" data-battlenext>' + esc(t(l.stage ? 'board_turn_nextnow' :
          'board_battle_next')) + '</button>' : '';
      } else {
        main += '<p class="battle-count">' + (l.mode === 'phones' ? '<span data-answered>' + esc(t('board_battle_answered', l)) +
          '</span>' : '') +
          (l.left >= 0 ? ' <span class="battle-left" data-left="' + l.left + '" aria-live="off">' + l.left + ' s</span>' : '') + '</p>';
        buttons = '<button class="btn btn-main" data-battlereveal>' + esc(t('board_battle_reveal')) + '</button>';
      }
    }
    if (l.paused && !l.outcome) {
      // Stopped a moment: the time of the turn stands still, and the only way on is to go on.
      main = '<p class="battle-paused">' + T.icon('pause') + esc(t('board_battle_paused')) + '</p>' + main;
      buttons = '<button class="btn btn-main" data-battlepause="resume">' + esc(t('board_battle_resume')) + '</button>';
    } else if (!l.outcome && l.round && l.kind === 'battle') {
      buttons += '<button class="btn btn-soft" data-battlepause="pause">' + esc(t('board_battle_pause')) + '</button>';
    }
    if (!l.outcome && l.round) {
      buttons += '<button class="btn btn-soft" data-battlefinish>' + esc(t('board_battle_finish')) + '</button>';
    }
    var teams = l.teams.map(function(team) {
      var marks = l.mode === 'aloud' && l.revealed && !l.outcome ? '<span class="pills"><button class="chip" data-mark="' +
        team.id + ':1" aria-pressed="' + (team.mark === 1) + '" aria-label="' + esc(t('board_battle_markright', team.name)) +
        '">✓</button><button class="chip" data-mark="' + team.id + ':0" aria-pressed="' + (team.mark === 0) +
        '" aria-label="' + esc(t('board_battle_markwrong', team.name)) + '">✗</button></span>' : '';
      var lane = (l.lanes || []).filter(function(x) {
        return x.teamid === team.id;
      })[0];
      var won = l.stage === 'resolve' && lane && lane.done;
      var shielded = l.stage === 'resolve' && l.shielded && l.targetid === team.id;
      return '<div class="battle-team' + (l.targetid === team.id && !l.outcome ? ' target' : '') +
        ((l.tangled || []).indexOf(team.id) >= 0 && !l.outcome ? ' tangled' : '') + (shielded ? ' shielded' : '') + '">' +
        (l.maxcandles ? heroes(team.id) : '') + (won ? '<span class="thread-off" aria-hidden="true">−1</span>' : '') +
        (shielded ? '<span class="team-shield" aria-hidden="true">' + T.icon('shield') + '</span>' : '') + '<b>' + esc(team.name) +
        '</b><small>' + (l.mode === 'phones' ?
        esc(t('board_battle_joined', team.joined)) + ' · ' : '') + esc(t('board_battle_teamright', team.right)) + '</small>' +
        marks + '</div>';
    }).join('');
    var tower = l.maxcandles ? T.artUrl(['scene.torre']) : null;
    var end = l.maxcandles && l.outcome ? ' ' + l.outcome : '';
    app.innerHTML = '<div class="board battle-board' + (hit ? ' shake' : '') + (l.maxcandles ? ' turns' + end : '') + '"' +
      (tower ? ' style="--tower:url(\'' + esc(tower) + '\')"' : '') + '><header class="board-top">' + T.emblem() +
      '<div><b>' + esc(cfg.coursename) + '</b><small>' +
      esc(t(l.final ? 'board_battle_final' : 'board_battle')) + (l.phase > 1 && !l.outcome ? ' · ' +
      esc(t('board_battle_phase')) : '') + '</small></div><div class="board-tools">' + musicButton() + lightButton() +
      '<button class="btn btn-soft" data-full>' + esc(t('board_fullscreen')) + '</button></div></header>' +
      '<div class="battle-stage"><div class="battle-boss' +
      (hit ? ' hit' : '') + (laugh ? ' laugh' : '') + '">' + embrolloSays(l) + intentChip(l) + bossArt(l, life) +
      '<span class="meter boss"><i style="width:' +
      Math.round(100 * life) + '%"></i></span><small>' + esc(t(l.maxcandles ? 'board_turn_threads' : 'board_battle_life')) +
      (l.maxhp ? ': ' + l.hp + ' / ' + l.maxhp : '') + '</small>' + candles(l) + '</div><div class="battle-main">' + main +
      '<div class="actions">' + buttons + '</div></div></div>' +
      '<div class="battle-teams">' + teams + '</div></div>';
    battleFx(l);
    bindBattle();
  }

  /**
   * What happened in a turn, at its resolution: the team the knot went for undid it, or the attack fell on them (a
   * shield stopped it, or the Embrollo laughs and pulls itself together); the life it lost; and when the next turn starts.
   *
   * @param {Object} l
   * @return {string}
   */
  function turnOutcome(l) {
    if (l.stage !== 'resolve') {
      return '';
    }
    if (l.lanes && l.lanes.length) {
      // What the Embrollo meant to do and did not get: told too, so that every turn says what happened.
      var spared = !l.counter && !l.shielded && !l.recovered ? '<p class="turn-outcome undone">' + esc(l.intent === 'team' ?
        t('board_turn_undone', l.target) : t('board_turn_spared_' + (l.intent || 'team'))) + '</p>' : '';
      return spared + (l.knot.broken ? '<p class="turn-outcome undone knot-broken">' + esc(t('board_turn_broken')) + '</p>' +
        (l.knot.why ? '<p class="why">' + esc(l.knot.why) + '</p>' : '') : '') + (l.shielded ? '<p class="turn-outcome">' +
        esc(t('board_turn_shield', l.target)) + '</p>' : '') + (l.counter && !l.recovered ? '<p class="turn-outcome fell">' +
        esc(t(l.intent === 'all' ? 'board_turn_candle_all' : 'board_turn_candle', l.target)) + '</p>' : '') +
        (l.recovered ? '<p class="turn-outcome fell">' + esc(t('board_turn_recovered', l.counter)) + '</p>' : '') +
        (l.knot.laugh ? '<p class="knot-laugh">«' + esc(l.knot.laugh.replace(/^«|»$/g, '')) + '»</p>' : '') +
        (l.damage ? '<p class="battle-count">' + esc(t('board_turn_threads_off', l.damage)) + '</p>' : '') +
        (l.stageleft > 0 && l.round < l.total ? '<p class="help-soft" data-stageleft="board_turn_next" aria-live="off">' +
        esc(t('board_turn_next', l.stageleft)) + '</p>' : '');
    }
    var fell = l.counter || l.shielded;
    return (l.target ? '<p class="turn-outcome' + (fell ? ' fell' : ' undone') + '">' + esc(t(!fell ? 'board_turn_undone' :
      l.shielded ? 'board_turn_shield' : 'board_turn_fell', l.target)) + '</p>' : '') + (l.knot.laugh ?
      '<p class="knot-laugh">«' + esc(l.knot.laugh.replace(/^«|»$/g, '')) + '»</p>' : '') + (l.damage ? '<p class="battle-count">' +
      esc(t('board_turn_damage', l.damage)) + '</p>' : '') + (l.stageleft > 0 && l.round < l.total ? '<p class="help-soft" ' +
      'data-stageleft="board_turn_next" aria-live="off">' + esc(t('board_turn_next', l.stageleft)) + '</p>' : '');
  }

  /**
   * What the Embrollo means to do in a turn: go for a team, for everybody, pull itself together, or nothing, dazed.
   *
   * @param {Object} l
   * @return {string}
   */
  function intentLine(l) {
    if (!l.intent) {
      return l.target ? '<p class="knot-target">' + esc(t('board_turn_goes', l.target)) + '</p>' : '';
    }
    return '<p class="knot-target intent-' + l.intent + '">' + esc(l.intent === 'team' ? t('board_turn_goes', l.target) :
      t('board_turn_intent_' + l.intent)) + '</p>';
  }

  /**
   * What the Embrollo means to do, over its head, while the turn is played: a team, everybody, pulling itself together
   * or dazed.
   *
   * @param {Object} l
   * @return {string}
   */
  function intentChip(l) {
    if (!l.intent || l.outcome || !l.stage || l.stage === 'resolve') {
      return '';
    }
    var icon = {team: 'team', all: 'class', recompose: 'power', dazed: 'sun'}[l.intent] || 'team';
    return '<p class="intent-chip intent-' + l.intent + '">' + T.icon(icon) + '<span>' + esc(l.intent === 'team' ?
      l.target : t('board_turn_chip_' + l.intent)) + '</span></p>';
  }

  /**
   * The Embrollo, or at the end of a battle by turns, the jar it is kept in (won) or the Embrollo rolling away (lost).
   *
   * @param {Object} l
   * @param {number} life
   * @return {string}
   */
  function bossArt(l, life) {
    var jar = l.maxcandles && l.outcome === 'won' ? T.artUrl(['boss.embrollo.jar']) : null;
    if (jar) {
      return '<img class="embrollo art jar" src="' + esc(jar) + '" alt="">';
    }
    return T.embrollo(life);
  }

  /**
   * The heroes of a team, in a row, the protector in front: who plays the battle, without names.
   *
   * @param {number} teamid
   * @return {string}
   */
  function heroes(teamid) {
    var members = (state.cls ? state.cls.students : []).filter(function(st) {
      return st.teamid === teamid && st.character;
    }).sort(function(a, b) {
      return (b.character.role === 'protector') - (a.character.role === 'protector');
    }).slice(0, 5);
    return members.length ? '<span class="team-heroes" aria-hidden="true">' + members.map(function(st) {
      var c = st.character;
      return '<span class="team-hero">' + T.avatar({role: c.role, nameform: c.nameform, lineage: c.lineage,
        look: c.appearance, stage: c.stage, portrait: true}) + '</span>';
    }).join('') + '</span>' : '';
  }

  /**
   * The candles of the tower, under the Embrollo: if they all go out, it escapes.
   *
   * @param {Object} l
   * @return {string}
   */
  function candles(l) {
    if (!l.maxcandles) {
      return '';
    }
    var out = '';
    for (var i = 0; i < l.maxcandles; i++) {
      out += '<span class="candle' + (i < l.candles ? ' lit' : ' out') + '" aria-hidden="true">' + T.icon('lantern') + '</span>';
    }
    return '<div class="candles" role="img" aria-label="' + esc(t('board_turn_candles', {lit: l.candles, all: l.maxcandles})) +
      '">' + out + '</div>';
  }

  /**
   * What the Embrollo says now, in its bubble next to the tangle: at the start, when it is hit or pulls itself together,
   * when it is down to half its life, and at the end. One line of the mission for each moment, the same on every
   * drawing of the moment.
   *
   * @param {Object} l
   * @return {string}
   */
  function embrolloSays(l) {
    var m = state.board.mission;
    if (!m || !m.lines || l.kind !== 'battle') {
      return '';
    }
    var kind = l.outcome === 'won' ? 'won' : l.outcome ? 'lost' : !l.round ? 'start' : !l.revealed ? (l.phase > 1 ? 'phase2' :
      '') : l.counter ? 'recover' : (l.right || 0) > 0 ? 'hit' : '';
    var set = m.lines.filter(function(x) {
      return x.kind === kind;
    })[0];
    if (!set || !set.lines.length || (kind === 'recover' && l.knot && l.knot.laugh)) {
      return '';
    }
    var line = set.lines[(l.id + l.round) % set.lines.length];
    return '<p class="embrollo-says">«' + esc(line.replace(/^«|»$/g, '')) + '»</p>';
  }

  function bindBattle() {
    var l = state.live;
    var on = function(sel, fn) {
      app.querySelectorAll(sel).forEach(function(el) {
        el.onclick = function() {
          fn(el);
        };
      });
    };
    on('[data-full]', fullscreen);
    on('[data-light]', switchLight);
    on('[data-music]', switchMusic);
    on('[data-battlenext]', function() {
      host('next', {round: l.round});
    });
    on('[data-battlereveal]', function() {
      host('reveal', {round: l.round});
    });
    on('[data-battlefinish]', function() {
      host('finish');
    });
    on('[data-battlepause]', function(el) {
      host(el.dataset.battlepause);
    });
    on('[data-mark]', function(el) {
      var p = el.dataset.mark.split(':');
      host('mark', {groupid: +p[0], right: p[1] === '1'});
    });
    on('[data-dismiss]', function() {
      state.seen = l.id;
      try {
        window.sessionStorage.setItem(SEEN, String(l.id));
      } catch (e) {
        // Without storage, a reload shows the end of the battle once more.
      }
      render();
      load(false);
    });
    // The answer shows by itself when the time is up or, with phones, when everybody in has answered.
    // By turns, not while the knot is announced and the roles get ready (it does not take answers yet).
    var waiting = l.stage === 'intent' || l.stage === 'prep';
    var done = l.round && !l.revealed && !l.outcome && !waiting && (!l.open || (l.mode === 'phones' && l.joined &&
      l.answered >= l.joined));
    if (done && state.auto !== l.id * 100 + l.round) {
      state.auto = l.id * 100 + l.round;
      clearTimeout(state.autotimer);
      state.autotimer = setTimeout(function() {
        host('reveal', {round: l.round});
      }, 900);
    }
  }

  T.light.restore();
  // The little living things visit the class screen too, less often.
  if (T.critters) {
    T.critters.start([40, 80]);
  }
  if (T.fx) {
    // The music of the world of the art pack.
    T.fx.musicHere(cfg.art && cfg.art.theme ? cfg.art.theme.music : 'valley');
  }

  /**
   * The soft music of the class, on or off on this device.
   *
   * @return {string}
   */
  function musicButton() {
    if (!T.fx) {
      return '';
    }
    return '<button class="btn btn-soft" type="button" data-music aria-pressed="' + T.fx.musicOn() + '" title="' +
      esc(t('board_music_help')) + '">♪ ' + esc(t('board_music')) + '</button>';
  }

  function switchMusic() {
    T.fx.music();
    render();
  }

  function lightButton() {
    var day = T.theme() === 'day';
    return '<button class="btn btn-soft" type="button" data-light title="' + esc(t('board_light_help')) + '">' +
      (day ? '☾ ' : '☀ ') + esc(t(day ? 'board_dark' : 'board_light')) + '</button>';
  }

  function switchLight() {
    T.light.toggle();
    render();
  }

  function fullscreen() {
    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else if (document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen();
    }
  }

  /**
   * Runs an action, shows what went wrong if anything, and refreshes.
   *
   * @param {Promise} p
   * @return {Promise}
   */
  function act(p) {
    state.busy = true;
    return p.catch(function(e) {
      T.toast(esc(e.message), true);
    }).then(function() {
      state.busy = false;
      return load(false);
    });
  }

  /* ---------- The map ---------- */

  function map() {
    var m = state.board.mission;
    if (!m) {
      return '<section class="card board-map"><p class="empty">' + esc(t('board_nomission')) + '</p></section>';
    }
    var pts = m.chapters.map(function(ch) {
      return ch.x + ',' + ch.y;
    }).join(' ');
    var lit = m.chapters.filter(function(ch) {
      return ch.lit;
    }).length;
    var walked = m.chapters.slice(0, lit + 1).map(function(ch) {
      return ch.x + ',' + ch.y;
    }).join(' ');
    // The lanterns painted for the map, if the art pack has them: lit, those the class lit.
    var lampOff = T.artUrl(['map.lantern.off']);
    var lampOn = T.artUrl(['map.lantern.on']);
    var places = m.chapters.map(function(ch) {
      return '<g class="place' + (ch.lit ? ' mine lit' : '') + '" data-art="' + esc(ch.art || ch.code) + '" aria-label="' +
        esc(ch.name) + '">' +
        (ch.lit ? '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="40" class="classglow"/>' : '') +
        '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="25" class="ring' + (lampOff && lampOn ? ' painted' : '') + '"/>' +
        (lampOff && lampOn ? '<image href="' + esc(ch.lit ? lampOn : lampOff) + '" x="' + (ch.x - 20) + '" y="' + (ch.y - 30) +
        '" width="40" height="50" class="' + (ch.lit ? 'lamp-on' : 'lamp-off') + '"/>' :
        '<rect x="' + (ch.x - 2) + '" y="' + (ch.y - 4) + '" width="4" height="17" rx="1" fill="#2b2219"/>' +
        '<circle cx="' + ch.x + '" cy="' + (ch.y - 7) + '" r="7" class="flame"/>') +
        T.mapLabel(ch.x, ch.y + 44, ch.name) + '</g>';
    }).join('') + dozing(m);
    var height = T.mapHeight(m);
    var fresh = T.mapNewRoad('board-' + cfg.courseid + '-' + m.code, m.chapters, Math.min(lit, m.chapters.length - 1));
    var drawn = fresh.road ? fresh.road.replace('/>', ' style="animation-delay:' + (0.3 + fresh.delay) + 's,' +
      (2.4 + fresh.delay) + 's"/>') : '';
    return '<section class="board-map"><h2>' + esc(m.title) + '</h2><div class="map-wrap' + (height > 640 ? ' tall' : '') +
      '"><svg class="map" viewBox="0 0 400 ' + height + '" role="img" aria-label="' + esc(m.title) + '"><defs>' + T.MAPGLOW +
      '</defs>' + T.mapGround(m) + T.mapLandmarks(m) + T.mapNight(m) +
      '<polyline points="' + pts + '" class="road"/>' + (lit ? '<polyline points="' + walked + '" class="road walked"/>' : '') +
      drawn + (T.critters ? T.critters.fireflies(m) : '') + places + '</svg></div>' + pastExpeditions() + '</section>';
  }

  /** Where the creatures doze around a lantern, in units of the map, nearest first. */
  var DOZE = [[-44, 8], [44, 8], [-60, -8], [60, -8], [-40, -24], [40, -24], [-76, 8], [76, 8], [-56, 22], [56, 22]];

  /**
   * The creatures of the class dozing by the lanterns it lit (by the first place, while none is lit), without names:
   * the creature exists outside the sheet.
   *
   * @param {Object} m The mission of the board.
   * @return {string}
   */
  function dozing(m) {
    var lit = m.chapters.filter(function(ch) {
      return ch.lit;
    });
    var by = lit.length ? lit : m.chapters.slice(0, 1);
    var pets = (state.cls ? state.cls.students : []).filter(function(st) {
      return st.character && st.character.creature && st.character.creature !== '{}';
    });
    return pets.slice(0, by.length * DOZE.length).map(function(st, i) {
      var ch = by[i % by.length];
      var at = DOZE[Math.floor(i / by.length)];
      return '<foreignObject x="' + (ch.x + at[0] - 13) + '" y="' + (ch.y + at[1] - 13) + '" width="26" height="26" ' +
        'class="doze" style="animation-delay:-' + ((i * 0.7) % 4).toFixed(1) + 's" aria-hidden="true"><div ' +
        'xmlns="http://www.w3.org/1999/xhtml">' + T.creature(st.character.creature, st.character.creaturestage) +
        '</div></foreignObject>';
    }).join('');
  }

  /**
   * The expeditions the class made before the current one, with the lanterns it lit.
   *
   * @return {string}
   */
  function pastExpeditions() {
    var list = state.board.expeditions || [];
    return list.length ? '<p class="help-soft board-past">' + esc(t('app_expeditions')) + ': ' + list.map(function(x) {
      return esc(x.title) + ' (' + esc(t('app_expedition_lit', {lit: x.lit, total: x.total})) + ')';
    }).join(' · ') + '</p>' : '';
  }

  /* ---------- The event of the day ---------- */

  function teamName(id) {
    var g = state.cls.teams.filter(function(x) {
      return x.id === id;
    })[0];
    return g ? g.name : '';
  }

  /**
   * The question to open the class, from the last plan the AI made for the teacher.
   *
   * @return {string}
   */
  /**
   * Today in the valley: what the mission proposes for the sessions of the chapter the class is in, with the scene to
   * play together, the last letter of the Embrollo and what confuses the class most.
   *
   * @return {string}
   */
  function todayCard() {
    var d = state.board.today;
    if (!d) {
      return '';
    }
    var award = can('award');
    var sessions = d.sessions.map(function(x) {
      return '<li><b>' + esc(t('board_today_session', x.n)) + '</b>' + (x.minutes ? ' · ' + esc(t('app_minutes', x.minutes)) :
        '') + '<p>' + esc(x.do) + '</p>' + (x.materials ? '<small>' + esc(t('board_today_materials', x.materials)) +
        '</small>' : '') + (award && x.sceneid ? '<div class="actions board-small"><button class="btn btn-soft" ' +
        'data-playscene="' + x.sceneid + '">' + esc(t('board_today_scene', x.scene)) + '</button></div>' : '') +
        (award && x.final ? '<div class="actions board-small"><button class="btn btn-soft" data-battle="final:1">' +
        esc(t('board_today_final')) + '</button></div>' : '') + '</li>';
    }).join('');
    // What confuses the class most: in the letter, the Embrollo boasts of it (no names); without a letter, said plainly.
    var taunt = d.letter && d.mixup ? '<span class="taunt">' + esc(t('board_today_taunt', d.mixup)) + '</span>' : '';
    return '<section class="card board-today"><div class="kicker">' + esc(t('board_today')) + '</div><h3>' +
      esc(d.chapter) + '</h3>' + (d.gloat ? '<blockquote class="letter gloat"><small>' + esc(t('board_today_gloat')) +
      '</small>' + esc(d.gloat) + '</blockquote>' : '') + stainsList(d.stains || [], award) +
      (award && d.lostscene ? '<div class="actions board-small">' +
      '<button class="btn btn-soft" data-playscene="' + d.lostscene.id + '">' + esc(t('board_today_scene', d.lostscene.title)) +
      '</button></div>' : '') + (d.letter ? '<blockquote class="letter"><small>' + esc(t('board_today_letter',
      d.letter.chapter)) + '</small>' + esc(d.letter.text) + taunt + '</blockquote>' + (d.letter.teaser ? '<p class="teaser">' +
      '<b>' + esc(t('app_teaser')) + '</b> ' + esc(d.letter.teaser) + '</p>' : '') : '') + (sessions ?
      '<ol class="today-sessions">' + sessions + '</ol>' : '') + (d.offscreen ? '<p class="help-soft">' +
      esc(t('board_today_offscreen', d.offscreen)) + '</p>' : '') + (d.mixup && !taunt ? '<p class="help-soft">' +
      esc(t('board_today_mixup', d.mixup)) + '</p>' : '') + '</section>';
  }

  /**
   * What the Embrollo stained, under «Today in the valley»: the class cleans it aloud and the teacher takes it as
   * cleaned (nobody gets a gem: the class did it).
   *
   * @param {Object[]} stains
   * @param {boolean} award
   * @return {string}
   */
  function stainsList(stains, award) {
    return stains.length ? '<ul class="stains">' + stains.map(function(s) {
      return '<li><span class="stain-dot" aria-hidden="true"></span><span>' + esc(t('board_today_stain', s)) + '</span>' +
        (award ? '<button type="button" class="btn btn-soft" data-erasestain="' + s.id + '">' + esc(t('board_stain_erase')) +
        '</button>' : '') + '</li>';
    }).join('') + '</ul>' : '';
  }

  function openerCard() {
    if (!state.board.opener || state.openerhidden) {
      return '';
    }
    return '<section class="card board-opener"><div class="kicker">' + esc(t('board_opener')) + '</div><p class="lead">' +
      esc(state.board.opener) + '</p><div class="actions board-small"><button class="btn btn-soft" data-openerhide>' +
      esc(t('board_opener_hide')) + '</button></div></section>';
  }

  function eventCard() {
    var e = state.board.event;
    var head = '<div class="kicker">' + esc(t('board_event')) + '</div>';
    if (!e) {
      return '<section class="card board-event">' + head + '<div class="actions"><button class="btn btn-main" ' +
        'data-draw>' + esc(t('board_draw')) + '</button></div></section>';
    }
    if (e.status === 'skipped') {
      return '<section class="card board-event">' + head + '<p class="help-soft">' + esc(t('board_skipped')) + '</p>' +
        '<div class="actions"><button class="btn btn-soft" data-draw>' + esc(t('board_draw')) + '</button></div></section>';
    }
    var acted = e.solved || e.gifts.length;
    var body = '';
    if (e.code === 'footprint' && e.question) {
      body = footprint(e);
    } else if (e.code === 'exchange') {
      body = exchange(e);
    }
    return '<section class="card board-event card-' + esc(e.code) + '">' + head + '<div class="event-head"><span ' +
      'class="event-icon">' + T.icon(CARD_ICON[e.code] || 'saber') + '</span><div><h2>' + esc(t('event_' + e.code)) +
      '</h2><p>' + esc(t('event_' + e.code + '_text')) + '</p></div></div>' + body + (acted ? '' :
      '<div class="actions board-small"><button class="btn btn-soft" data-draw>' + esc(t('board_change')) + '</button>' +
      '<button class="btn btn-soft" data-skip>' + esc(t('board_skip')) + '</button></div>') + '</section>';
  }

  function footprint(e) {
    var q = e.question;
    var shown = state.reveal || e.solved;
    var letters = 'ABCD';
    var options = q.options.map(function(o, i) {
      return '<li class="' + (shown && i === q.correct ? 'right' : '') + '"><b>' + letters[i] + '</b>' + esc(o) + '</li>';
    }).join('');
    var html = '<div class="question"><p class="q">' + esc(q.text) + '</p><ol class="options">' + options + '</ol>' +
      (shown ? '<p class="why">' + esc(q.why) + '</p>' : '') + '</div>';
    if (e.solved) {
      return html + '<p class="help-soft">' + esc(e.solved === 'right' ? t('board_solvedright', 5) : t('board_solvedwrong')) +
        '</p>';
    }
    if (!shown) {
      return html + '<div class="actions"><button class="btn btn-main" data-reveal>' + esc(t('board_reveal')) +
        '</button></div>';
    }
    return html + (can('award') ? '<div class="actions"><button class="btn btn-main" data-solve="1">' +
      esc(t('board_classright')) + '</button><button class="btn btn-soft" data-solve="0">' + esc(t('board_classwrong')) +
      '</button></div>' : '');
  }

  function exchange(e) {
    var given = {};
    e.gifts.forEach(function(g) {
      given[g.from] = g.to;
    });
    return '<ul class="list exchange">' + state.cls.teams.map(function(team) {
      if (given[team.id]) {
        return '<li class="item"><span class="grow">' + esc(t('board_gave', {from: team.name, to: teamName(given[team.id])})) +
          '</span></li>';
      }
      return '<li class="item" style="flex-wrap:wrap"><span class="grow"><b>' + esc(t('board_gives', team.name)) +
        '</b></span><span class="pills">' + state.cls.teams.filter(function(other) {
          return other.id !== team.id;
        }).map(function(other) {
          return '<button class="chip" data-gift="' + team.id + ':' + other.id + '"' + (can('award') ? '' : ' disabled') +
            '>' + esc(other.name) + '</button>';
        }).join('') + '</span></li>';
    }).join('') + '</ul>';
  }

  /* ---------- The teams ---------- */

  function student(s) {
    var c = s.character;
    if (!c) {
      return '';
    }
    var m = c.member || {};
    // Worn out shows, as part of the game; who the teacher paused, not in front of the class.
    var cls = 'board-student' + (state.sel[s.userid] ? ' on' : '') + (m.status === 'fallen' ? ' resting' : '');
    return '<button type="button" class="' + cls + '" data-student="' + s.userid + '" aria-pressed="' + !!state.sel[s.userid] +
      '"><span class="face' + (c.aura ? ' aura ' + esc(c.aura) : '') + '">' + T.avatar({role: c.role, nameform: c.nameform,
      lineage: c.lineage, look: c.appearance, stage: c.stage, portrait: true}) + '</span><span class="name">' +
      esc(s.firstname) + '</span></button>';
  }

  function teams() {
    var cls = state.cls;
    var standings = {};
    cls.standings.forEach(function(x) {
      standings[x.id] = x;
    });
    var groups = cls.teams.map(function(team) {
      var members = cls.students.filter(function(s) {
        return s.teamid === team.id;
      });
      var w = standings[team.id];
      var pct = w && w.needed ? Math.min(100, Math.round(100 * w.done / w.needed)) : 0;
      var lantern = (team.banner || []).indexOf('banner-lantern') >= 0 ? '<span class="banner-lantern">' +
        T.icon('lantern') + '</span> ' : '';
      return '<div class="board-team"><button type="button" class="team-name" data-team="' + team.id + '">' + lantern +
        esc(team.name) + '</button>' + (w ? '<span class="meter level"><i style="width:' + pct + '%"></i></span>' : '') +
        '<div class="members">' + members.map(student).join('') + '</div></div>';
    }).join('');
    var loose = cls.students.filter(function(s) {
      return !s.teamid;
    });
    return '<section class="card board-teams"><div class="kicker">' + esc(t('board_teams')) + '</div>' +
      (groups || '<p class="empty">' + esc(t('board_noteams')) + '</p>') +
      (loose.length ? '<div class="board-team"><div class="members">' + loose.map(student).join('') + '</div></div>' : '') +
      '<p class="help-soft">' + esc(t('board_tapstudents')) + '</p></section>';
  }

  function bar() {
    var ids = Object.keys(state.sel);
    if (!ids.length || !can('award')) {
      return '';
    }
    return '<div class="board-bar"><b>' + esc(t('board_selected', ids.length)) + '</b>' +
      [5, 10, 20].map(function(n) {
        return '<button class="btn btn-main" data-give="saber:' + n + '">' + T.icon('saber') + ' +' + n + '</button>';
      }).join('') + '<button class="btn btn-main" data-give="gems:1">' + T.icon('gems') + ' +1</button>' +
      '<button class="btn btn-soft" data-clear>' + esc(t('board_clear')) + '</button></div>';
  }

  /* ---------- A scene played by the whole class ---------- */

  function sceneCard() {
    var m = state.board.mission;
    if (!m || !can('award')) {
      return '';
    }
    var sc = state.scene;
    var chapter = m.chapters.filter(function(ch) {
      return ch.id === sc.chapter;
    })[0] || m.chapters[0];
    return '<section class="card board-scene"><div class="kicker">' + esc(t('board_scene')) + '</div><p class="help-soft">' +
      esc(t('board_scene_help')) + '</p><div class="battle-options-row"><span class="pills">' + m.chapters.map(function(ch) {
        return '<button class="chip" data-scenechapter="' + ch.id + '" aria-pressed="' + (ch.id === chapter.id) + '">' +
          esc(ch.name) + '</button>';
      }).join('') + '</span></div><ul class="list">' + chapter.scenes.map(function(scene) {
        return '<li class="item"><span class="grow"><b>' + esc(scene.title) + '</b></span><button class="btn btn-main" ' +
          'data-playscene="' + scene.id + '">' + esc(t('board_scene_play')) + '</button></li>';
      }).join('') + '</ul></section>';
  }

  function playScene(sceneid) {
    // A double tap on «Play» opens one scene and one vote, not two.
    if (state.opening || document.querySelector('.scene-layer')) {
      return;
    }
    state.opening = true;
    var phones = state.scene.mode === 'phones';
    var m = state.board.mission;
    var host = function(action, extra) {
      return T.api('live_host', Object.assign({courseid: cfg.courseid, action: action}, extra || {})).then(function(r) {
        return r.live || null;
      });
    };
    var classmode = {
      name: t('board_scene_us'),
      creature: t('board_scene_creatures'),
      items: m.items,
      roles: m.roles,
      vote: phones ? {
        open: function(text, labels) {
          return host('vote', {text: text, options: JSON.stringify(labels)});
        },
        state: function() {
          return host('state');
        },
        close: function() {
          return host('reveal');
        }
      } : null,
      finish: function(events, from) {
        return T.api('class_scene', {courseid: cfg.courseid, sceneid: sceneid, events: JSON.stringify(events), start: from || '',
          phones: phones}).then(function(result) {
          if (result.outcome === 'retry' && phones) {
            // A new vote for the retry: the phones come in again.
            return host('startvote', {sceneid: sceneid}).then(function() {
              return result;
            });
          }
          return result;
        });
      }
    };
    var world = {family: state.cls.region.family, character: {role: 'sage', creature: '{}'}, items: [], team: null};
    var go = function() {
      state.opening = false;
      T.playScene({sceneid: sceneid, world: world, classmode: classmode, onclose: function() {
        if (phones) {
          host('state').then(function(live) {
            if (live && live.kind === 'vote' && !live.outcome) {
              host('finish');
            }
          });
        }
        load(false);
      }});
    };
    if (phones) {
      host('startvote', {sceneid: sceneid}).then(go).catch(function(e) {
        state.opening = false;
        T.toast(esc(e.message), true);
      });
    } else {
      go();
    }
  }

  /* ---------- Printable material ---------- */

  function printCard() {
    return '<section class="card board-print"><div class="kicker">' + esc(t('board_print')) + '</div><p class="help-soft">' +
      esc(t('board_print_help')) + '</p><div class="actions board-small">' +
      ['votes', 'teams', 'chars', 'diplomas'].map(function(k) {
        return '<button class="btn btn-soft" data-print="' + k + '">' + esc(t('board_print_' + k)) + '</button>';
      }).join('') + '</div></section>';
  }

  function voteSet(team) {
    return '<div class="sheet-page votes">' + VOTES.map(function(v) {
      return '<div class="vote-card"><svg viewBox="0 0 100 100" aria-hidden="true"><g fill="' + v.colour + '" stroke="#000" ' +
        'stroke-width="3">' + v.shape + '</g><text x="50" y="' + (v.letter === 'B' ? 76 : 66) + '" text-anchor="middle" ' +
        'font-size="' + (v.letter === 'B' ? 34 : 42) + '" font-weight="700" fill="#fff" stroke="#000" stroke-width="1.5">' +
        v.letter + '</text></svg><small>' + esc(team) + '</small></div>';
    }).join('') + '</div>';
  }

  function portrait(s) {
    var c = s.character;
    return c ? T.avatar({role: c.role, nameform: c.nameform, lineage: c.lineage, look: c.appearance, stage: c.stage,
      portrait: true}) : '';
  }

  /**
   * A diploma for each student with a character, for the end of the mission.
   *
   * @param {string} fam The family of names of the region.
   * @return {string}
   */
  function diplomas(fam) {
    var m = state.board.mission;
    var date = T.date(new Date(), {day: 'numeric', month: 'long', year: 'numeric'});
    return state.cls.students.filter(function(s) {
      return s.character;
    }).map(function(s) {
      var c = s.character;
      return '<div class="sheet-page diploma"><div class="diploma-frame"><p class="diploma-kicker">Tesapé</p><h1>' +
        esc(t('board_diploma')) + '</h1><span class="face">' + portrait(s) + '</span><h2>' + esc(s.fullname) + '</h2><p>' +
        esc(T.roleName(fam, c.role, c.nameform)) + ' · ' + esc(t('app_level')) + ' ' + c.level + '</p><p class="lead">' +
        esc(m ? t('board_diploma_for', m.title) : t('board_diploma_course', cfg.coursename)) + '</p><div class="diploma-foot">' +
        '<span>' + esc(cfg.coursename) + '<br>' + esc(date) + '</span><span class="diploma-sign">' + esc(t('board_diploma_sign')) +
        '</span></div></div></div>';
    }).join('');
  }

  function drawPrint() {
    var cls = state.cls;
    var fam = cls.region.family;
    var pages = '';
    if (state.print === 'votes') {
      pages = (cls.teams.length ? cls.teams : [{name: cfg.coursename}]).map(function(team) {
        return voteSet(team.name);
      }).join('');
    } else if (state.print === 'teams') {
      pages = cls.teams.map(function(team) {
        var members = cls.students.filter(function(s) {
          return s.teamid === team.id && s.character;
        });
        return '<div class="sheet-page team-sheet"><h1>' + esc(team.name) + '</h1><div class="team-faces">' +
          members.map(function(s) {
            return '<div><span class="face">' + portrait(s) + '</span><b>' + esc(s.firstname) + '</b><small>' +
              esc(T.roleName(fam, s.character.role, s.character.nameform)) + '</small></div>';
          }).join('') + '</div><p class="sheet-foot">Tesapé · ' + esc(cfg.coursename) + '</p></div>';
      }).join('');
    } else if (state.print === 'diplomas') {
      pages = diplomas(fam);
    } else {
      var chars = cls.students.filter(function(s) {
        return s.character;
      });
      for (var i = 0; i < chars.length; i += 6) {
        pages += '<div class="sheet-page char-sheets">' + chars.slice(i, i + 6).map(function(s) {
          var c = s.character;
          return '<div class="char-card"><span class="face">' + portrait(s) + '</span><div><b>' + esc(s.fullname) + '</b>' +
            '<small>' + esc(T.roleName(fam, c.role, c.nameform)) + ' · ' + esc(t('app_level')) + ' ' + c.level + '</small>' +
            '<small>' + esc(t('app_saber')) + ' ' + c.saber + ' · ' + esc(t('app_gems')) + ' ' + c.gems + '</small></div></div>';
        }).join('') + '</div>';
      }
    }
    app.innerHTML = '<div class="printout"><div class="actions no-print"><button class="btn btn-main" data-doprint>' +
      esc(t('board_print_go')) + '</button><button class="btn btn-soft" data-printback>' + esc(t('board_print_back')) +
      '</button></div>' + pages + '</div>';
    app.querySelector('[data-doprint]').onclick = function() {
      window.print();
    };
    app.querySelector('[data-printback]').onclick = function() {
      state.print = '';
      render();
    };
  }

  /* ---------- The screen ---------- */

  function render() {
    if (battling()) {
      drawBattle(true);
      return;
    }
    if (state.print) {
      drawPrint();
      return;
    }
    var pending = state.cls.powerspending;
    app.innerHTML = '<div class="board">' +
      '<header class="board-top">' + T.emblem() + '<div><b>' + esc(cfg.coursename) + '</b><small>' + esc(t('board_title')) + '</small></div>' +
      '<div class="board-tools">' + (pending && can('approvepowers') ? '<a class="btn btn-soft" href="' +
      esc(cfg.wwwroot + '/local/oksigeniatesape/teach.php?id=' + cfg.courseid) + '">' + esc(t('board_powers', pending)) +
      '</a>' : '') + '<a class="btn btn-soft" href="' + esc(cfg.wwwroot + '/local/oksigeniatesape/teach.php?id=' +
      cfg.courseid) + '">' + esc(t('board_panel')) + '</a>' + devicesPicker() + musicButton() + lightButton() +
      '<button class="btn btn-soft" data-full>' +
      esc(t('board_fullscreen')) + '</button></div></header>' +
      '<div class="board-grid">' + map() + '<div class="board-side">' + todayCard() + openerCard() + eventCard() + battleCard() +
      sceneCard() + teams() + printCard() + '</div></div>' + bar() + '</div>';
    bind();
  }

  function bind() {
    var on = function(sel, fn) {
      app.querySelectorAll(sel).forEach(function(el) {
        el.onclick = function() {
          fn(el);
        };
      });
    };
    on('[data-full]', fullscreen);
    on('[data-light]', switchLight);
    on('[data-music]', switchMusic);
    on('[data-openerhide]', function() {
      state.openerhidden = true;
      render();
    });
    on('[data-battle]', function(el) {
      var p = el.dataset.battle.split(':');
      state.battle[p[0]] = p[0] === 'mode' ? p[1] : +p[1];
      render();
    });
    on('[data-devices]', function(el) {
      state.devices = el.dataset.devices === '1';
      modes();
      render();
      if (can('manageregion')) {
        T.api('save_region', {courseid: cfg.courseid, devices: state.devices}).then(function(region) {
          state.cls.region = region;
          state.devices = undefined;
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      }
    });
    on('[data-scenechapter]', function(el) {
      state.scene.chapter = +el.dataset.scenechapter;
      render();
    });
    on('[data-playscene]', function(el) {
      playScene(+el.dataset.playscene);
    });
    on('[data-erasestain]', function(el) {
      el.disabled = true;
      act(T.api('erase_stain', {courseid: cfg.courseid, stainid: +el.dataset.erasestain}));
    });
    on('[data-print]', function(el) {
      state.print = el.dataset.print;
      render();
    });
    on('[data-battlestart]', function() {
      var b = state.battle;
      host('start', {mode: b.mode, count: b.count, secs: b.secs, final: !!b.final, turns: true});
    });
    on('[data-draw]', function() {
      state.reveal = false;
      act(T.api('class_event', {courseid: cfg.courseid, action: 'draw'}));
    });
    on('[data-skip]', function() {
      act(T.api('class_event', {courseid: cfg.courseid, action: 'skip'}));
    });
    on('[data-reveal]', function() {
      state.reveal = true;
      render();
    });
    on('[data-solve]', function(el) {
      act(T.api('class_event', {courseid: cfg.courseid, action: 'solve', right: el.dataset.solve === '1'}));
    });
    on('[data-gift]', function(el) {
      var p = el.dataset.gift.split(':');
      act(T.api('class_event', {courseid: cfg.courseid, action: 'gift', from: +p[0], to: +p[1]}));
    });
    on('[data-student]', function(el) {
      var id = el.dataset.student;
      if (state.sel[id]) {
        delete state.sel[id];
      } else {
        state.sel[id] = true;
      }
      render();
    });
    on('[data-team]', function(el) {
      var id = +el.dataset.team;
      var members = state.cls.students.filter(function(s) {
        return s.teamid === id && s.character;
      });
      var all = members.every(function(s) {
        return state.sel[s.userid];
      });
      members.forEach(function(s) {
        if (all) {
          delete state.sel[s.userid];
        } else {
          state.sel[s.userid] = true;
        }
      });
      render();
    });
    on('[data-clear]', function() {
      state.sel = {};
      render();
    });
    on('[data-give]', function(el) {
      // A second tap while the first is on its way would give it twice.
      if (state.giving) {
        return;
      }
      var p = el.dataset.give.split(':');
      var ids = Object.keys(state.sel).map(Number);
      state.giving = true;
      app.querySelectorAll('[data-give]').forEach(function(b) {
        b.disabled = true;
      });
      state.busy = true;
      T.api('award', {courseid: cfg.courseid, userids: ids, resource: p[0], amount: +p[1]}).then(function(results) {
        var given = results.filter(function(r) {
          return r.status === 'given';
        }).length;
        T.toast(esc(t('board_given', given)));
        state.sel = {};
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      }).then(function() {
        state.busy = false;
        state.giving = false;
        return load(false);
      });
    });
  }

  load(true);
  setInterval(refresh, REFRESH);
  document.addEventListener('visibilitychange', function() {
    refresh();
    schedule();
  });
})();
