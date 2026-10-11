/**
 * Tesapé: the class screen, for the projector or the whiteboard. The map of the mission with the lanterns of the class,
 * the event of the day and the teams with their portraits; quick touches of saber and gems on students or whole teams.
 * It refreshes itself every few seconds, so what the teacher does from the phone shows up here too. Only good news on
 * this screen: nothing that takes something away from a student in front of the class.
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
  var LIVE = 1500;
  var LETTERS = 'ABCD';
  var CARD_ICON = {calm: 'energy', breeze: 'saber', footprint: 'world', exchange: 'breath'};
  var state = {board: null, cls: null, sel: {}, reveal: false, busy: false, sig: '', live: null, seen: 0, timer: 0,
    battle: {mode: 'phones', count: 6, secs: 30}, auto: 0};

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
      T.api('get_class', {courseid: cfg.courseid}),
      T.api('live_host', {courseid: cfg.courseid, action: 'state'})
    ]).then(function(r) {
      state.board = r[0];
      state.cls = r[1];
      state.live = r[2].live || null;
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

  function refresh() {
    if (!document.hidden && !state.busy && !battling()) {
      load(false);
    }
  }

  /* ---------- The battle against the Embrollo ---------- */

  function battling() {
    var l = state.live;
    return !!(l && (!l.outcome || state.seen !== l.id));
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
    clearTimeout(state.timer);
    return T.api('live_host', Object.assign({courseid: cfg.courseid, action: action}, extra || {})).then(function(r) {
      state.live = r.live || null;
      if (battling()) {
        drawBattle();
      } else {
        load(false);
      }
    }).catch(function(e) {
      if (action !== 'state') {
        T.toast(esc(e.message), true);
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
      '<div class="battle-options-row">' + pills('mode', ['phones', 'aloud'], function(v) {
        return t('board_battle_' + v);
      }) + '</div><div class="battle-options-row"><span>' + esc(t('board_battle_count')) + '</span>' +
      pills('count', [4, 6, 8, 10], String) + '</div><div class="battle-options-row"><span>' + esc(t('board_battle_secs')) +
      '</span>' + pills('secs', [0, 20, 30, 45, 60], function(v) {
        return v ? v + ' s' : t('board_battle_nolimit');
      }) + '</div><div class="actions"><button class="btn btn-main" data-battlestart>' + esc(t('board_battle_start')) +
      '</button></div></section>';
  }

  function drawBattle() {
    var l = state.live;
    var life = l.maxhp ? l.hp / l.maxhp : 1;
    var main;
    var buttons = '';
    if (l.outcome) {
      main = '<div class="battle-end ' + l.outcome + '"><h2>' + esc(t('board_battle_' + l.outcome)) + '</h2><p class="lead">' +
        esc(l.outcome === 'won' ? t('board_battle_wonhelp', l.rewarded) : t('board_battle_losthelp')) + '</p></div>';
      buttons = '<button class="btn btn-main" data-dismiss>' + esc(t('board_battle_back')) + '</button>';
    } else if (!l.round) {
      main = '<div class="battle-lobby"><p class="lead">' + esc(t(l.mode === 'phones' ? 'board_battle_join' :
        'board_battle_aloudhelp')) + '</p>' + (l.mode === 'phones' ? '<p class="battle-count">' +
        esc(t('board_battle_joined', l.joined)) + '</p>' : '') + '</div>';
      buttons = '<button class="btn btn-main" data-battlenext' + (l.mode === 'phones' && !l.joined ? ' disabled' : '') + '>' +
        esc(t('board_battle_first')) + '</button>';
    } else {
      var q = l.question;
      main = '<p class="battle-qround">' + esc(t('board_battle_round', l)) + '</p><p class="battle-q">' + esc(q.text) +
        '</p><ol class="battle-board-options">' + q.options.map(function(o, i) {
          return '<li class="' + (l.revealed && q.correct === i ? 'right' : '') + '"><b>' + LETTERS[i] + '</b>' + esc(o) + '</li>';
        }).join('') + '</ol>';
      if (l.revealed) {
        main += '<p class="why">' + esc(q.why || '') + '</p>' + (l.mode === 'phones' ? '<p class="battle-count">' +
          esc(t('board_battle_rights', l.right || 0)) + '</p>' : '<p class="help-soft">' + esc(t('board_battle_markhelp')) +
          '</p>');
        buttons = l.round < l.total ? '<button class="btn btn-main" data-battlenext>' + esc(t('board_battle_next')) +
          '</button>' : '';
      } else {
        main += '<p class="battle-count">' + (l.mode === 'phones' ? esc(t('board_battle_answered', l)) : '') +
          (l.left >= 0 ? ' <span class="battle-left" data-left="' + l.left + '">' + l.left + ' s</span>' : '') + '</p>';
        buttons = '<button class="btn btn-main" data-battlereveal>' + esc(t('board_battle_reveal')) + '</button>';
      }
    }
    if (!l.outcome && l.round) {
      buttons += '<button class="btn btn-soft" data-battlefinish>' + esc(t('board_battle_finish')) + '</button>';
    }
    var teams = l.teams.map(function(team) {
      var marks = l.mode === 'aloud' && l.revealed && !l.outcome ? '<span class="pills"><button class="chip" data-mark="' +
        team.id + ':1" aria-pressed="' + (team.mark === 1) + '" aria-label="' + esc(t('board_battle_markright', team.name)) +
        '">✓</button><button class="chip" data-mark="' + team.id + ':0" aria-pressed="' + (team.mark === 0) +
        '" aria-label="' + esc(t('board_battle_markwrong', team.name)) + '">✗</button></span>' : '';
      return '<div class="battle-team"><b>' + esc(team.name) + '</b><small>' + (l.mode === 'phones' ?
        esc(t('board_battle_joined', team.joined)) + ' · ' : '') + esc(t('board_battle_teamright', team.right)) + '</small>' +
        marks + '</div>';
    }).join('');
    app.innerHTML = '<div class="board battle-board"><header class="board-top"><div><b>' + esc(cfg.coursename) + '</b><small>' +
      esc(t('board_battle')) + '</small></div><div class="board-tools"><button class="btn btn-soft" data-full>' +
      esc(t('board_fullscreen')) + '</button></div></header><div class="battle-stage"><div class="battle-boss' +
      (l.revealed && (l.right || 0) > 0 ? ' hit' : '') + '">' + T.embrollo(life) + '<span class="meter boss"><i style="width:' +
      Math.round(100 * life) + '%"></i></span><small>' + esc(t('board_battle_life')) + (l.maxhp ? ': ' + l.hp + ' / ' +
      l.maxhp : '') + '</small></div><div class="battle-main">' + main + '<div class="actions">' + buttons + '</div></div></div>' +
      '<div class="battle-teams">' + teams + '</div></div>';
    bindBattle();
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
    on('[data-battlenext]', function() {
      host('next');
    });
    on('[data-battlereveal]', function() {
      host('reveal');
    });
    on('[data-battlefinish]', function() {
      host('finish');
    });
    on('[data-mark]', function(el) {
      var p = el.dataset.mark.split(':');
      host('mark', {groupid: +p[0], right: p[1] === '1'});
    });
    on('[data-dismiss]', function() {
      state.seen = l.id;
      render();
      load(false);
    });
    // The answer shows by itself when the time is up or, with phones, when everybody in has answered.
    var done = l.round && !l.revealed && !l.outcome && (!l.open || (l.mode === 'phones' && l.joined && l.answered >= l.joined));
    if (done && state.auto !== l.id * 100 + l.round) {
      state.auto = l.id * 100 + l.round;
      setTimeout(function() {
        host('reveal');
      }, 900);
    }
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
    var places = m.chapters.map(function(ch) {
      return '<g class="place' + (ch.lit ? ' mine lit' : '') + '" aria-label="' + esc(ch.name) + '">' +
        (ch.lit ? '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="40" class="classglow"/>' : '') +
        '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="25" class="ring"/>' +
        '<rect x="' + (ch.x - 2) + '" y="' + (ch.y - 4) + '" width="4" height="17" rx="1" fill="#2b2219"/>' +
        '<circle cx="' + ch.x + '" cy="' + (ch.y - 7) + '" r="7" class="flame"/>' +
        '<text x="' + ch.x + '" y="' + (ch.y + 44) + '" text-anchor="middle">' + esc(ch.name) + '</text></g>';
    }).join('');
    var art = T.artUrl(['map.' + m.code + '.' + m.skin, 'map.' + m.code]);
    var ground = art ? '<image href="' + esc(art) + '" x="0" y="0" width="400" height="640" ' +
      'preserveAspectRatio="xMidYMid slice"/>' : '<rect width="400" height="640" rx="18" fill="#16304a"/>';
    return '<section class="board-map"><h2>' + esc(m.title) + '</h2><div class="map-wrap"><svg class="map" ' +
      'viewBox="0 0 400 640" role="img" aria-label="' + esc(m.title) + '">' + ground +
      '<polyline points="' + pts + '" class="road"/>' + (lit ? '<polyline points="' + walked + '" class="road walked"/>' : '') +
      places + '</svg></div></section>';
  }

  /* ---------- The event of the day ---------- */

  function teamName(id) {
    var g = state.cls.teams.filter(function(x) {
      return x.id === id;
    })[0];
    return g ? g.name : '';
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
    var cls = 'board-student' + (state.sel[s.userid] ? ' on' : '') + (m.status && m.status !== 'active' ? ' resting' : '');
    return '<button type="button" class="' + cls + '" data-student="' + s.userid + '" aria-pressed="' + !!state.sel[s.userid] +
      '"><span class="face">' + T.avatar({role: c.role, nameform: c.nameform, lineage: c.lineage, look: c.appearance,
      stage: c.stage, portrait: true}) + '</span><span class="name">' + esc(s.firstname) + '</span></button>';
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
      var pct = w && w.goal ? Math.min(100, Math.round(100 * w.saber / w.goal)) : 0;
      return '<div class="board-team"><button type="button" class="team-name" data-team="' + team.id + '">' +
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

  /* ---------- The screen ---------- */

  function render() {
    if (battling()) {
      drawBattle();
      return;
    }
    var pending = state.cls.powerspending;
    app.innerHTML = '<div class="board">' +
      '<header class="board-top"><div><b>' + esc(cfg.coursename) + '</b><small>' + esc(t('board_title')) + '</small></div>' +
      '<div class="board-tools">' + (pending && can('approvepowers') ? '<a class="btn btn-soft" href="' +
      esc(cfg.wwwroot + '/local/oksigeniatesape/teach.php?id=' + cfg.courseid) + '">' + esc(t('board_powers', pending)) +
      '</a>' : '') + '<a class="btn btn-soft" href="' + esc(cfg.wwwroot + '/local/oksigeniatesape/teach.php?id=' +
      cfg.courseid) + '">' + esc(t('board_panel')) + '</a><button class="btn btn-soft" data-full>' +
      esc(t('board_fullscreen')) + '</button></div></header>' +
      '<div class="board-grid">' + map() + '<div class="board-side">' + eventCard() + battleCard() + teams() + '</div></div>' +
      bar() + '</div>';
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
    on('[data-battle]', function(el) {
      var p = el.dataset.battle.split(':');
      state.battle[p[0]] = p[0] === 'mode' ? p[1] : +p[1];
      render();
    });
    on('[data-battlestart]', function() {
      var b = state.battle;
      host('start', {mode: b.mode, count: b.count, secs: b.secs});
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
      var p = el.dataset.give.split(':');
      var ids = Object.keys(state.sel).map(Number);
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
