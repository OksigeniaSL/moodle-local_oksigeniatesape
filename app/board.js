/**
 * Tesapé: the class screen, for the projector or the whiteboard. The map of the mission with the lanterns of the class,
 * the event of the day and the teams with their portraits; quick touches of saber and gems on students or whole teams.
 * It refreshes itself (the battles every few seconds, the rest every minute), so what the teacher does from the phone
 * shows up here too. Only good news on this screen: nothing that takes something away from a student in front of the
 * class.
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
  var app = document.getElementById('app');
  var REFRESH = 5000;
  var FULL = 60000;
  var LIVE = 1500;
  var LETTERS = 'ABCD';
  /** @var {number} Seconds of the sorting of the loose threads, at the end of the mission. */
  var SORTING = 60;
  var CARD_ICON = {calm: 'energy', breeze: 'saber', footprint: 'world', exchange: 'breath', knot: 'net', messengers: 'idea',
    trade: 'team'};
  var state = {board: null, cls: null, sel: {}, reveal: false, busy: false, sig: '', live: null, seen: 0, timer: 0,
    battle: {mode: 'phones', count: 6, secs: 30}, auto: 0, print: '', scene: {chapter: 0, mode: 'phones'}, lend: {}};
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

  /** The books of the museum this class screen already saw pass, by course. */
  var BOOKS = 'tesape-board-books-' + cfg.courseid;

  /**
   * A new card in the field guide of the class is a page that turns on the class screen: its name and its group, for a
   * few seconds. Not in the middle of a battle or of something the teacher launched; the first time this screen sees
   * the museum, it only takes note.
   */
  function museumNews() {
    var m = state.cls && state.cls.museum;
    if (!m || battling() || state.showEl) {
      return;
    }
    var now = [];
    m.shelves.forEach(function(shelf) {
      shelf.books.forEach(function(b) {
        now.push({key: (shelf.group || '') + '|' + b.name, name: b.name, group: shelf.group || t('app_museum_nogroup')});
      });
    });
    var seen = null;
    try {
      seen = JSON.parse(window.localStorage.getItem(BOOKS) || 'null');
    } catch (e) {
      seen = null;
    }
    try {
      window.localStorage.setItem(BOOKS, JSON.stringify(now.map(function(b) {
        return b.key;
      })));
    } catch (e) {
      // Without storage, the screen does not show the pages; nothing else.
      return;
    }
    var fresh = seen ? now.filter(function(b) {
      return seen.indexOf(b.key) < 0;
    }).slice(0, 3) : [];
    if (!fresh.length) {
      return;
    }
    var el = document.createElement('div');
    el.className = 'session-show shows-page';
    el.setAttribute('role', 'status');
    el.innerHTML = '<div class="show-card"><div class="kicker">' + esc(t('board_page_new')) + '</div><div class="pages">' +
      fresh.map(function(b, i) {
        return '<div class="page-turn" style="animation-delay:' + (i * 1.4) + 's"><small>' + esc(b.group) + '</small><b>' +
          esc(b.name) + '</b></div>';
      }).join('') + '</div></div>';
    document.body.appendChild(el);
    state.showEl = el;
    if (T.fx && !T.fx.sound('card')) {
      T.fx.sound('chime');
    }
    setTimeout(function() {
      if (state.showEl === el) {
        state.showEl = null;
      }
      el.remove();
    }, 3500 + fresh.length * 1400);
  }

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
      session(r[2].session);
      state.teamstates = r[2].teams ? JSON.parse(r[2].teams) : {};
      state.huddles = r[2].huddles ? JSON.parse(r[2].huddles) : {};
      modes();
      T.family = r[1].region.family;
      schedule();
      var sig = JSON.stringify(r);
      if (first || sig !== state.sig) {
        state.sig = sig;
        render();
      }
      museumNews();
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
      session(r.session);
      teamStates(r.teams, r.huddles);
      var live = r.live || null;
      var was = state.live;
      if ((live && live.id) !== (was && was.id) || (live && live.outcome) !== (was && was.outcome)) {
        load(false);
      }
    }).catch(function() {
      // A poll that fails is tried again at the next one.
    });
  }

  /* ---------- How each team says it is doing ---------- */

  /**
   * The lantern of the state of a team: ready, working or asking for help, without names nor why.
   *
   * @param {number} id
   * @param {string} st
   * @return {string}
   */
  function teamState(id, st) {
    // A team playing a scene with its tablets joined: a firefly, which the teacher can touch to close it.
    var huddle = (state.huddles || {})[id];
    return '<span class="team-state-lamp' + (st ? ' state-' + st : '') + '" data-teamlamp="' + id + '">' +
      (st ? '<i aria-hidden="true"></i>' + esc(t('board_teamstate_' + st)) : '') + (huddle ? '<button type="button" ' +
      'class="team-huddle" data-huddle="' + huddle + '" title="' + esc(t('board_huddle_close')) + '">' + T.icon('cheer') +
      '<span class="sr-only">' + esc(t('board_huddle')) + '</span></button>' : '') + '</span>';
  }

  function teamStates(json, huddles) {
    var states = {};
    try {
      states = json ? JSON.parse(json) : {};
      state.huddles = huddles ? JSON.parse(huddles) : {};
    } catch (e) {
      states = {};
    }
    state.teamstates = states;
    app.querySelectorAll('[data-teamlamp]').forEach(function(el) {
      var st = (states[el.dataset.teamlamp] || '') + '|' + ((state.huddles || {})[el.dataset.teamlamp] || '');
      if ((el.dataset.st || '') !== st) {
        el.outerHTML = teamState(el.dataset.teamlamp, states[el.dataset.teamlamp] || '').replace('data-teamlamp=',
          'data-st="' + st + '" data-teamlamp=');
      }
    });
    bindHuddles();
  }

  /** The teacher closes a team session from its firefly. */
  function bindHuddles() {
    app.querySelectorAll('[data-huddle]').forEach(function(b) {
      b.onclick = function(ev) {
        ev.stopPropagation();
        if (!window.confirm(t('board_huddle_close_confirm'))) {
          return;
        }
        T.api('huddle', {courseid: cfg.courseid, action: 'close', huddleid: Number(b.dataset.huddle)}).then(function() {
          b.remove();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
  }

  /* ---------- The session the teacher runs from their guide ---------- */

  /**
   * The session going on, as the class screen gets it: what the teacher launched shows here.
   *
   * @param {string} json
   */
  function session(json) {
    var s = null;
    try {
      s = json ? JSON.parse(json) : null;
    } catch (e) {
      s = null;
    }
    var key = s ? s.id + ':' + s.shown + ':' + s.shownat : '';
    if (key === state.sessionkey) {
      // The same thing shown: on the first day, the heroes made; otherwise, only how the teams are doing.
      if (s && s.firstday && state.showEl) {
        firstSparks(state.showEl, s.firstday);
      }
      if (s && (s.pacts || s.council || s.expedition) && state.showEl) {
        var list = state.showEl.querySelector('[data-steams]');
        if (list) {
          list.innerHTML = s.pacts ? pactTeams(s.pacts) : s.council ? councilTeams(s.council) : expeditionTeams(s.expedition);
        }
        var flies = state.showEl.querySelector('[data-sflies]');
        if (flies && s.expedition) {
          flies.innerHTML = fireflies(s.expedition);
        }
      }
      return;
    }
    state.sessionkey = key;
    // The clock of the server, to count a timer from when the teacher launched it.
    state.sessionoffset = s ? s.now * 1000 - Date.now() : 0;
    showSession(s);
  }

  /**
   * The heroes made on the first day: a spark for each new one, where it always is (the old ones stay, so that only the
   * new ones light up), how many of the class there are already, and how many of each role.
   *
   * @param {Element} el The show.
   * @param {Object} f made, total, sparks (roles in the order they were made).
   */
  function firstSparks(el, f) {
    var sky = el.querySelector('[data-ssparks]');
    if (!sky) {
      return;
    }
    for (var i = sky.children.length; i < f.sparks.length; i++) {
      // Spread like the seeds of a sunflower: each one in its place, none over another.
      var r = 46 * Math.sqrt((i + 0.5) / Math.max(f.total, f.sparks.length, 1));
      var a = i * 2.39996;
      var spark = document.createElement('i');
      spark.className = 'first-spark role-' + f.sparks[i];
      spark.style.left = (50 + r * Math.cos(a)).toFixed(1) + '%';
      spark.style.top = (50 + r * Math.sin(a)).toFixed(1) + '%';
      sky.appendChild(spark);
    }
    el.querySelector('[data-sfirst]').textContent = t('board_firstday_count', {made: f.made, total: f.total});
    var count = {};
    f.sparks.forEach(function(role) {
      count[role] = (count[role] || 0) + 1;
    });
    el.querySelector('[data-sroles]').innerHTML = ['protector', 'healer', 'sage', 'scout'].map(function(role) {
      return '<li class="role-' + role + '"><i class="first-spark role-' + role + '" aria-hidden="true"></i>' +
        esc(T.roleName(T.family, role, 'm')) + ': <b>' + (count[role] || 0) + '</b></li>';
    }).join('');
  }

  /**
   * The teams in council, on the class screen: the role that starts the hand once they chose it. No names.
   *
   * @param {Object} c place, teams (name, first).
   * @return {string}
   */
  function councilTeams(c) {
    return c.teams.map(function(team) {
      return '<li class="' + (team.first ? 'ready' : '') + '"><b>' + esc(team.name) + '</b><span>' + esc(team.first ?
        t('board_council_first', T.roleName(T.family, team.first, 'm')) : t('board_council_on')) + '</span></li>';
    }).join('');
  }

  /**
   * The teams on the expedition, on the class screen: a firefly lit by each one that has come into the game since the
   * session started. No names of students.
   *
   * @param {Object} x teams (name, in).
   * @return {string}
   */
  function expeditionTeams(x) {
    return x.teams.map(function(team) {
      return '<li class="' + (team.in ? 'ready' : '') + '"><span class="fly-dot" aria-hidden="true"></span><b>' +
        esc(team.name) + '</b><span>' + esc(t(team.in ? 'board_expedition_in' : 'board_expedition_out')) + '</span></li>';
    }).join('');
  }

  /**
   * The fireflies over the place of the expedition, one by team that has come in, each one where it always is.
   *
   * @param {Object} x teams (name, in).
   * @return {string}
   */
  function fireflies(x) {
    return x.teams.map(function(team, i) {
      return team.in ? '<span class="expedition-fly" style="left:' + (8 + (i * 37) % 84) + '%;top:' + (12 + (i * 23) % 56) +
        '%;animation-delay:-' + ((i * 1.3) % 5).toFixed(1) + 's"></span>' : '';
    }).join('');
  }

  /**
   * The map of the mission at dawn, still at night and lighting up: the lanterns the class lit and, by the places
   * stained, the ink of the Embrollo.
   *
   * @param {Object|null} m The mission of the board.
   * @param {Object} d What the Embrollo did (did: chapter of each).
   * @return {string}
   */
  function dawnMap(m, d) {
    if (!m || !m.chapters || !m.chapters.length) {
      return '';
    }
    var stained = {};
    d.did.forEach(function(x) {
      if (x.chapter) {
        stained[x.chapter] = true;
      }
    });
    var pts = m.chapters.map(function(ch) {
      return ch.x + ',' + ch.y;
    }).join(' ');
    return '<svg class="dawn-map" viewBox="0 0 400 ' + T.mapHeight(m) + '" aria-hidden="true">' + T.mapGround(m) +
      T.mapLandmarks(m) + '<polyline points="' + pts + '" class="road"/>' + m.chapters.map(function(ch) {
        return '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="10" class="dawn-lamp' + (ch.lit ? ' lit' : '') + '"/>' +
          (stained[ch.code] ? '<circle cx="' + (ch.x + 22) + '" cy="' + (ch.y - 20) + '" r="13" class="dawn-blot"/>' : '');
      }).join('') + '</svg>';
  }

  function pactTeams(p) {
    return p.teams.map(function(team) {
      return '<li><b>' + esc(team.name) + '</b><span class="meter"><i style="width:' + (team.total ? Math.round(100 *
        team.chose / team.total) : 0) + '%"></i></span><span>' + esc(t('board_session_chose', team)) + '</span></li>';
    }).join('');
  }

  /**
   * One challenge of the burst on the class screen: big, with its clock; then what was right, a moment; then the next.
   * At the end, the Embrollo gives up.
   *
   * @param {Element} box
   * @param {Object[]} items As the burst of a student, with no student.
   * @param {number} i
   */
  function boardBurst(box, items, i) {
    // A thing or a being, drawn if the pack has it, with its name.
    var thing = function(picture, label) {
      var art = picture ? T.artUrl([picture]) : null;
      return (art ? '<img class="thing-pic" src="' + esc(art) + '" alt="">' : '') + '<span class="thing-name">' +
        esc(label ? label.charAt(0).toLocaleUpperCase() + label.slice(1) : '') + '</span>';
    };
    var item = items[i];
    if (!box || !box.isConnected) {
      return;
    }
    if (!item) {
      box.innerHTML = '<div class="burst-word big beaten" aria-hidden="true">' + esc(t('app_burst_shout')) + '</div><h1>' +
        esc(t('board_burst_end')) + '</h1><span class="battle-mini" aria-hidden="true">' + T.embrollo(0.2) + '</span>';
      if (T.fx) {
        T.fx.sound('win');
      }
      return;
    }
    var letters = 'ABCD';
    var seconds = item.seconds + 3;
    box.innerHTML = '<div class="burst-hud"><span class="kicker">' + esc(t('app_burst_count', {n: i + 1, all: items.length})) +
      '</span></div><div class="burst-word" aria-hidden="true">' + esc(item.word) + '</div>' + (item.picture || item.label ?
      '<div class="burst-thing">' + thing(item.picture, item.label) + '</div>' : '') + '<p class="burst-prompt">' +
      esc(item.prompt) + '</p>' + (item.kind === 'boss' ? '<div class="burst-three">' + item.things.map(function(x) {
        return '<span class="burst-pick" data-thing="' + esc(x.id) + '">' + thing(x.picture, x.label) + '</span>';
      }).join('') + '</div>' : '<ol class="burst-board-options">' + item.options.map(function(o, k) {
        return '<li data-k="' + k + '"><b>' + letters.charAt(k) + '</b> ' + esc(o) + '</li>';
      }).join('') + '</ol>') + '<div class="burst-clock" aria-hidden="true"><i style="animation-duration:' + seconds + 's"></i>' +
      '</div>';
    if (T.fx) {
      T.fx.sound('chime');
    }
    state.bursttimer = setTimeout(function() {
      // What was right, a moment.
      if (item.kind === 'boss') {
        item.things.forEach(function(x) {
          var b = box.querySelector('[data-thing="' + x.id + '"]');
          if (b) {
            b.classList.add(x.alive ? 'right' : 'notalive');
          }
        });
      } else {
        var right = box.querySelector('[data-k="' + item.right + '"]');
        if (right) {
          right.classList.add('right');
        }
      }
      if (T.fx) {
        T.fx.sound('right');
      }
      state.bursttimer = setTimeout(function() {
        boardBurst(box, items, i + 1);
      }, 3500);
    }, seconds * 1000);
  }

  function showSession(s) {
    clearInterval(state.showtimer);
    clearTimeout(state.bursttimer);
    if (state.showEl) {
      state.showEl.remove();
      state.showEl = null;
    }
    if (!s || !s.shown) {
      return;
    }
    if (s.shown === 'card') {
      // The card of the day, drawn from the guide: the class screen shows it at once.
      load(false).then(function() {
        var card = app.querySelector('.board-event');
        if (card) {
          card.scrollIntoView({behavior: 'smooth', block: 'center'});
          card.classList.add('flash');
        }
      });
      return;
    }
    if (s.shown === 'scene') {
      playScene(s.target);
      return;
    }
    if (s.shown === 'light') {
      // The rite of the lantern: the place, its lantern lighting up; then the map, with its new road.
      var lamp = T.artUrl(['map.lantern.on']);
      var rite = document.createElement('div');
      rite.className = 'session-show shows-light';
      rite.setAttribute('role', 'dialog');
      rite.setAttribute('aria-modal', 'true');
      rite.innerHTML = '<div class="show-card"><div class="kicker">' + esc(t('board_session_light')) + '</div><div ' +
        'class="show-lantern" aria-hidden="true">' + (lamp ? '<img src="' + esc(lamp) + '" alt="">' : '<span class="flame">' +
        '</span>') + '</div><h1>' + esc(s.lit || '') + '</h1></div>';
      document.body.appendChild(rite);
      state.showEl = rite;
      if (T.fx) {
        T.fx.sound('lantern');
      }
      setTimeout(function() {
        if (state.showEl === rite) {
          rite.remove();
          state.showEl = null;
        }
        load(false);
      }, 6500);
      return;
    }
    var el = document.createElement('div');
    el.className = 'session-show shows-' + s.shown;
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    var html = '';
    var end = 0;
    if (s.shown === 'enter') {
      var art = T.artUrl(['scene.' + s.chapter]);
      if (art) {
        el.style.backgroundImage = 'linear-gradient(rgba(0,0,0,.25), rgba(0,0,0,.65)), url("' + art + '")';
      }
      // The letter of the Embrollo, line after line.
      var lines = String(s.letter || '').split(/(?<=[.!?…»])\s+/).filter(Boolean);
      html = '<div class="show-card"><div class="kicker">' + esc(t('board_session_enter')) + '</div><h1>' + esc(s.place) +
        '</h1>' + (s.intro ? '<p class="lead">' + esc(s.intro) + '</p>' : '') + (lines.length ? '<div class="show-letter">' +
        '<span class="battle-mini" aria-hidden="true">' + T.embrollo(1) + '</span><div>' + lines.map(function(line, i) {
          return '<p style="animation-delay:' + (1.2 + i * 2.2) + 's">' + esc(line) + '</p>';
        }).join('') + '</div></div>' : '') + '</div>';
      if (T.fx) {
        T.fx.sound('path');
      }
    } else if (s.shown === 'timer') {
      end = (s.shownat + s.minutes * 60) * 1000 - state.sessionoffset;
      html = '<div class="show-card"><div class="kicker">' + esc(t('board_session_timer')) + '</div><div class="show-clock" ' +
        'data-sclock>' + s.minutes + ':00</div><div class="meter show-sand"><i data-ssand style="width:100%"></i></div></div>';
    } else if (s.shown === 'pacts') {
      end = (s.shownat + 120) * 1000 - state.sessionoffset;
      html = '<div class="show-card"><div class="kicker">' + esc(t('board_session_pacts')) + '</div><h1>' +
        esc(s.pacts && s.pacts.place || s.place) + '</h1><ol class="show-pacts">' + (s.pacts ? s.pacts.options : []).map(function(o) {
          return '<li>' + esc(o) + '</li>';
        }).join('') + '</ol><ul class="show-teams" data-steams>' + (s.pacts ? pactTeams(s.pacts) : '') + '</ul>' +
        '<div class="show-clock small" data-sclock>2:00</div></div>';
    } else if (s.shown === 'campfire') {
      // The campfire, to close: the creatures of the class asleep around the fire, without names, and three cards to
      // talk about what each one takes home.
      var pets = (state.cls ? state.cls.students : []).filter(function(st) {
        return st.character && st.character.creature && st.character.creature !== '{}';
      }).slice(0, 14);
      html = '<div class="show-card campfire"><div class="kicker">' + esc(t('board_campfire')) + '</div><h1>' +
        esc(t('board_campfire_title')) + '</h1><div class="campfire-ring" aria-hidden="true">' + pets.map(function(st, i) {
          var a = (i / Math.max(1, pets.length)) * Math.PI * 2;
          return '<span class="campfire-pet" style="left:' + (50 + 40 * Math.cos(a)).toFixed(1) + '%;top:' +
            (50 + 30 * Math.sin(a)).toFixed(1) + '%;animation-delay:-' + ((i * 0.7) % 4).toFixed(1) + 's">' +
            T.creature(st.character.creature, st.character.creaturestage) + '</span>';
        }).join('') + '<span class="campfire-fire"><i></i><i></i><i></i></span></div><ol class="campfire-cards">' +
        ['new', 'confused', 'home'].map(function(k) {
          return '<li>' + esc(t('board_campfire_' + k)) + '</li>';
        }).join('') + '</ol><p class="lead">' + esc(t('board_campfire_help')) + '</p></div>';
      if (T.fx) {
        T.fx.sound('lantern');
      }
    } else if (s.shown === 'firstday') {
      // The first day: the four steps to make a hero, and a spark for each one already in the valley, of the colour of
      // their role. No names.
      var first = s.firstday || {made: 0, total: 0, sparks: []};
      html = '<div class="show-card firstday"><div class="kicker">' + esc(t('board_firstday')) + '</div><h1>' +
        esc(t('board_firstday_title')) + '</h1><ol class="firstday-steps">' + [1, 2, 3, 4].map(function(n) {
          return '<li>' + esc(t('board_firstday_' + n)) + '</li>';
        }).join('') + '</ol><div class="firstday-sky" data-ssparks aria-hidden="true"></div><p class="lead" data-sfirst>' +
        '</p><ul class="firstday-roles" data-sroles></ul></div>';
      if (T.fx) {
        T.fx.sound('chime');
      }
    } else if (s.shown === 'dawn') {
      // Dawn in the valley: the map still at night, lighting up, and what the Embrollo did in the night, told by itself.
      var night = s.dawn || {did: [], prints: 0};
      var said = night.did.map(function(x) {
        if (x.kind === 'page') {
          return x.hidden ? t('board_dawn_hid', x.title) : t('board_dawn_page', {place: x.place, title: x.title});
        }
        return t('board_dawn_stain', {place: x.place, title: x.title});
      });
      if (night.prints) {
        said.push(t(night.prints === 1 ? 'board_dawn_print' : 'board_dawn_prints', {n: night.prints, place: night.towards}));
      }
      if (!said.length) {
        said.push(t('board_dawn_quiet'));
      }
      html = '<div class="show-card dawn"><div class="kicker">' + esc(t('board_dawn')) + '</div><h1>' +
        esc(t('board_dawn_title')) + '</h1>' + dawnMap(state.board && state.board.mission, night) +
        '<div class="show-letter"><span class="battle-mini" aria-hidden="true">' + T.embrollo(1) + '</span><div>' +
        said.map(function(line, i) {
          return '<p style="animation-delay:' + (1.5 + i * 2.2) + 's">' + esc(line) + '</p>';
        }).join('') + '</div></div></div>';
      if (T.fx) {
        T.fx.sound('giggle');
      }
    } else if (s.shown === 'expedition') {
      // The expedition: the place of the session, a firefly by each team that has come in, and a soft hourglass.
      var land = T.artUrl(['scene.' + s.chapter]);
      if (land) {
        el.style.backgroundImage = 'linear-gradient(rgba(0,0,0,.1), rgba(0,0,0,.6)), url("' + land + '")';
      }
      var trip = s.expedition || {teams: []};
      end = (s.shownat + s.minutes * 60) * 1000 - state.sessionoffset;
      html = '<div class="expedition-flies" data-sflies aria-hidden="true">' + fireflies(trip) + '</div><div ' +
        'class="show-card expedition"><div class="kicker">' + esc(t('board_expedition')) + '</div><h1>' +
        esc(s.place || '') + '</h1><ul class="show-teams" data-steams>' + expeditionTeams(trip) + '</ul><div ' +
        'class="meter show-sand" role="img" aria-label="' + esc(t('board_expedition_sand')) + '"><i data-ssand ' +
        'style="width:100%"></i></div></div>';
      if (T.fx) {
        T.fx.sound('path');
      }
    } else if (s.shown === 'encounter') {
      // The encounter of the class: who peeks out today, in silhouette, with its clues one after another and four names
      // to guess aloud; the teacher reveals it from the remote.
      var e = s.encounter;
      var pic = e && T.artUrl([e.picture]);
      html = e ? '<div class="show-card encounter' + (e.revealed ? ' revealed' : '') + '"><div class="kicker">' +
        esc(t('board_encounter', e.place)) + '</div><h1>' + esc(t(e.revealed ? 'board_encounter_was' :
        'board_encounter_title')) + '</h1><div class="encounter-show">' + (pic ? '<img class="encounter-being" src="' +
        esc(pic) + '" alt="' + esc(e.revealed ? e.options.filter(function(o) {
          return o.id === e.answer;
        }).map(function(o) {
          return o.name;
        }).join('') : t('board_encounter_hidden')) + '">' : '') + '<ol class="encounter-clues">' +
        e.clues.map(function(c, i) {
          return '<li style="animation-delay:' + (e.revealed ? 0 : 1 + i * 2.5) + 's">' + esc(c) + '</li>';
        }).join('') + '</ol></div><p class="lead">' + esc(t('app_encounter_sniff', {creature: t('board_encounter_pet'),
        group: t('app_group_' + e.group)})) + '</p><ul class="encounter-names">' + e.options.map(function(o) {
          return '<li class="' + (e.revealed && o.id === e.answer ? 'right' : '') + '">' + esc(o.name) + '</li>';
        }).join('') + '</ul></div>' : '<div class="show-card"><div class="kicker">' + esc(t('board_encounter', s.place)) +
        '</div><p class="lead">' + esc(t('board_encounter_none')) + '</p></div>';
      if (T.fx) {
        T.fx.sound(e && e.revealed ? 'right' : 'chirp');
      }
    } else if (s.shown === 'loot') {
      // The loot of the session: the chest of each team, opened one after another, with what its members earned. Nothing
      // by chance.
      var loot = s.loot || {teams: [], lit: []};
      html = '<div class="show-card loot"><div class="kicker">' + esc(t('board_loot')) + '</div><h1>' +
        esc(t('board_loot_title')) + '</h1>' + (loot.lit.length ? '<p class="lead">' + esc(t('board_loot_lit',
        loot.lit.join(', '))) + '</p>' : '') + '<ul class="loot-chests">' + loot.teams.map(function(team, i) {
          var inside = [];
          if (team.saber) {
            inside.push('<li>' + T.icon('saber') + ' ' + esc(t('board_loot_saber', team.saber)) + '</li>');
          }
          if (team.gems) {
            inside.push('<li>' + T.icon('gems') + ' ' + esc(team.gems === 1 ? t('board_loot_gem') : t('board_loot_gems', team.gems)) + '</li>');
          }
          team.beings.forEach(function(b) {
            inside.push('<li>' + T.icon('lens') + ' ' + esc(b) + '</li>');
          });
          if (team.more) {
            inside.push('<li>' + esc(t('board_loot_more', team.more)) + '</li>');
          }
          return '<li class="loot-chest" style="animation-delay:' + (0.6 + i * 1.4) + 's"><span class="loot-lid" ' +
            'aria-hidden="true"></span><b>' + esc(team.name) + '</b><ul>' + (inside.length ? inside.join('') : '<li>' +
            esc(t('board_loot_empty')) + '</li>') + '</ul></li>';
        }).join('') + '</ul></div>';
      if (T.fx) {
        T.fx.sound('coin');
      }
    } else if (s.shown === 'burst') {
      // The burst of the Embrollo, to start the class: each challenge big, the class answers aloud, then the answer.
      html = '<div class="show-card burst-board"><div class="kicker">' + esc(t('board_burst')) + '</div><div data-sburst></div>' +
        '</div>';
    } else if (s.shown === 'tangle' && s.tangle) {
      // The tangle of the week, written by a team: the class argues aloud and then sees what is true and why.
      html = '<div class="show-card tangle-board"><div class="kicker">' + esc(t('board_tangle')) + '</div><p class="lead">' +
        esc(t('board_tangle_by', s.tangle.team)) + '</p><div class="tangle-claim"><span class="battle-mini" aria-hidden="true">' +
        T.embrollo(0.3) + '</span><h1>«' + esc(s.tangle.claim.replace(/^«|»$/g, '')) + '»</h1></div><p class="lead">' +
        esc(t('board_tangle_ask')) + '</p><ol class="burst-board-options">' + s.tangle.options.map(function(o, k) {
          return '<li' + (o === s.tangle.truth ? ' data-truth' : '') + '><b>' + 'ABC'.charAt(k) + '</b>' + esc(o) + '</li>';
        }).join('') + '</ol><div class="actions" style="justify-content:center"><button type="button" class="btn btn-main" ' +
        'data-tangleshow>' + esc(t('board_tangle_show')) + '</button></div><p class="why" data-tanglewhy hidden>' +
        esc(s.tangle.why) + '</p></div>';
    } else if (s.shown === 'council') {
      // The teams in council: each one, on a tablet, looks at its task and chooses who starts the hand.
      var ev = state.board && state.board.event;
      end = (s.shownat + s.minutes * 60) * 1000 - state.sessionoffset;
      html = '<div class="show-card"><div class="kicker">' + esc(t('board_session_council')) + '</div><h1>' +
        esc(s.council && s.council.place || s.place) + '</h1><p class="lead">' + esc(t('board_session_council_help')) +
        '</p>' + (ev && ev.status === 'drawn' ? '<p class="show-event"><b>' + esc(t('board_event')) + ':</b> ' +
        esc(ev.name || t('event_' + ev.code)) + '</p>' : '') + '<ul class="show-teams" data-steams>' +
        (s.council ? councilTeams(s.council) : '') + '</ul><div class="show-clock small" data-sclock>' + s.minutes +
        ':00</div></div>';
    }
    el.innerHTML = '<button type="button" class="btn btn-soft scene-close" aria-label="' + esc(t('app_close')) + '">' +
      T.icon('close') + '</button>' + html;
    el.querySelector('.scene-close').onclick = function() {
      clearInterval(state.showtimer);
      clearTimeout(state.bursttimer);
      el.remove();
      state.showEl = null;
    };
    document.body.appendChild(el);
    state.showEl = el;
    if (s.firstday) {
      firstSparks(el, s.firstday);
    }
    if (s.shown === 'burst') {
      boardBurst(el.querySelector('[data-sburst]'), s.burst || [], 0);
    }
    var tangleshow = el.querySelector('[data-tangleshow]');
    if (tangleshow) {
      // What is true, when the class has said what it thinks.
      tangleshow.onclick = function() {
        var right = el.querySelector('[data-truth]');
        if (right) {
          right.classList.add('right');
        }
        el.querySelector('[data-tanglewhy]').hidden = false;
        tangleshow.remove();
        if (T.fx) {
          T.fx.sound('right');
        }
      };
    }
    if (end) {
      var total = end - Date.now();
      var tick = function() {
        var left = Math.max(0, end - Date.now());
        var clock = el.querySelector('[data-sclock]');
        var sand = el.querySelector('[data-ssand]');
        var secs = Math.ceil(left / 1000);
        if (clock) {
          clock.textContent = left ? Math.floor(secs / 60) + ':' + ('0' + secs % 60).slice(-2) : t('board_session_timeup');
        }
        if (sand) {
          sand.style.width = (total > 0 ? Math.round(100 * left / total) : 0) + '%';
        }
        if (!left) {
          clearInterval(state.showtimer);
          el.classList.add('done');
          if (T.fx && total > 0) {
            T.fx.sound('lantern');
          }
        }
      };
      tick();
      state.showtimer = setInterval(tick, 1000);
    }
  }

  /* ---------- The battle against the Embrollo ---------- */

  var SEEN = 'tesape-board-seen-' + cfg.courseid;

  /** The settings of the last battle of the course, on this class screen. */
  var BATTLE = 'tesape-board-battle-' + cfg.courseid;
  try {
    var last = JSON.parse(window.localStorage.getItem(BATTLE) || 'null');
    if (last && live0(last.count, [4, 6, 8, 10]) && live0(last.secs, [0, 20, 30, 45, 60])) {
      state.battle.count = last.count;
      state.battle.secs = last.secs;
    }
  } catch (e) {
    // The usual ones.
  }

  function live0(v, list) {
    return list.indexOf(v) >= 0;
  }
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

  /**
   * Whether the great untangling is on the class screen: going on, or its end not seen yet.
   *
   * @return {boolean}
   */
  function untangling() {
    var l = state.live;
    return !!(l && l.kind === 'untangle' && l.untangle && (!l.outcome || state.seen !== l.id));
  }

  /**
   * Whether the nest of a team is on the class screen: going on, or its end not seen yet.
   *
   * @return {boolean}
   */
  function nesting() {
    var l = state.live;
    return !!(l && l.kind === 'nest' && l.nest && (!l.outcome || state.seen !== l.id));
  }

  /**
   * Defensa del nido on the class screen for a team: the animal coming down, the eggs of the nest, who holds each net,
   * and how the last one went. The nets are on the tablets.
   */
  function drawNest() {
    var l = state.live;
    var n = JSON.parse(l.nest);
    var sig = JSON.stringify([l.id, n.phase, n.index, n.eggs, n.joined, n.outcome, n.nets]);
    if (sig === state.nestsig && app.querySelector('.nest-board')) {
      var left = app.querySelector('[data-nleft]');
      if (left) {
        left.textContent = n.left + ' s';
      }
      return;
    }
    state.nestsig = sig;
    var eggs = '';
    for (var i = 0; i < n.maxeggs; i++) {
      eggs += '<i class="nest-egg' + (i < n.eggs ? ' warm' : '') + '"></i>';
    }
    var last = n.last ? '<p class="nest-last ' + (n.last.right ? 'good' : 'bad') + '" role="status">' + esc(n.last.right ?
      t('board_nest_caught', n.last) : n.last.net ? t('board_nest_wrong', n.last) : t('board_nest_late', n.last)) + '</p>' +
      (!n.last.right && n.last.hint ? '<p class="help-soft">' + esc(n.last.hint) + '</p>' : '') : '';
    var main = '';
    var buttons = '';
    if (n.phase === 'lobby') {
      main = '<p class="lead">' + esc(t('board_nest_lobby', n.team)) + '</p><p><b>' + esc(t('board_untangle_joined',
        n.joined.length)) + '</b>' + (n.joined.length ? ' · ' + esc(n.joined.join(', ')) : '') + '</p>';
      buttons = '<button class="btn btn-main" data-ngo' + (n.joined.length ? '' : ' disabled') + '>' +
        esc(t('board_untangle_go')) + '</button><button class="btn btn-soft" data-nfinish>' + esc(t('board_untangle_cancel')) +
        '</button>';
    } else if (n.phase === 'play' && n.animal) {
      var pic = T.artUrl(['being.' + n.animal.id]);
      main = last + '<p class="kicker">' + esc(t('board_nest_count', {n: n.index + 1, all: n.total})) + '</p><div class="nest-animal">' +
        (pic ? '<img src="' + esc(pic) + '" alt="">' : '') + '<h1>' + esc(T.capital(n.animal.label)) + '</h1></div><p class="lead">' +
        esc(t('board_nest_ask')) + ' <span class="untangle-clock small" data-nleft>' + n.left + ' s</span></p>';
      buttons = '<button class="btn btn-soft" data-nfinish>' + esc(t('board_untangle_finish')) + '</button>';
    } else {
      main = last + '<h1>' + esc(t(n.outcome === 'won' ? 'board_nest_won' : n.outcome === 'lost' ? 'board_nest_lost' :
        'board_untangle_none')) + '</h1><p>' + esc(t('board_nest_result', n)) + '</p>';
      buttons = '<button class="btn btn-main" data-dismiss-nest>' + esc(t('board_untangle_back')) + '</button>';
    }
    var nets = n.phase === 'lobby' ? '' : '<ol class="untangle-strands nest-nets">' + n.nets.map(function(x) {
      return '<li class="untangle-strand"><b>' + esc(x.label) + '</b><small>' + esc(x.who || t('board_nest_nobody')) +
        '</small></li>';
    }).join('') + '</ol>';
    app.innerHTML = '<div class="board battle-board nest-board"><header class="board-top">' + T.emblem() + '<div><b>' +
      esc(cfg.coursename) + '</b><small>' + esc(t('board_nest', n.team)) + '</small></div><div class="board-tools">' +
      musicButton() + lightButton() + '<button class="btn btn-soft" data-full>' + esc(t('board_fullscreen')) + '</button></div>' +
      '</header><div class="battle-stage"><div class="battle-boss"><span class="nest-eggs" role="img" aria-label="' +
      esc(t('board_nest_eggs', n)) + '">' + eggs + '</span></div><div class="battle-main">' + main + '<div class="actions">' +
      buttons + '</div></div></div>' + nets + '</div>';
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
    on('[data-ngo]', function() {
      host('go');
    });
    on('[data-nfinish]', function() {
      host('finish');
    });
    on('[data-dismiss-nest]', function() {
      state.seen = l.id;
      try {
        window.sessionStorage.setItem(SEEN, String(l.id));
      } catch (e) {
        // Without storage, a reload shows the end once more.
      }
      render();
      load(false);
    });
    if (T.fx && n.last && state.nestfx !== l.id + ':' + n.index) {
      state.nestfx = l.id + ':' + n.index;
      T.fx.sound(n.phase === 'end' && n.outcome === 'won' ? 'win' : n.last.right ? 'right' : 'wrong');
    }
  }

  /**
   * The great untangling on the class screen: the knot of the Embrollo with a strand for each team, the clock, and
   * what the teacher does (start it, end it).
   */
  function drawUntangle() {
    var l = state.live;
    var u = JSON.parse(l.untangle);
    var sig = JSON.stringify([l.id, u.phase, u.strands, u.joined, u.outcome]);
    var clock = Math.floor(u.left / 60) + ':' + ('0' + (u.left % 60)).slice(-2);
    if (sig === state.untanglesig && app.querySelector('.untangle-board')) {
      var left = app.querySelector('[data-uleft]');
      if (left) {
        left.textContent = clock;
      }
      return;
    }
    state.untanglesig = sig;
    var total = u.strands.length * u.strand;
    var knots = u.strands.reduce(function(sum, s) {
      return sum + s.knots;
    }, 0);
    var strands = u.strands.map(function(s) {
      var marks = '';
      for (var i = 0; i < u.strand; i++) {
        marks += '<i class="' + (i < s.knots ? 'tight' : '') + '"></i>';
      }
      return '<li class="untangle-strand' + (s.loose ? ' loose' : '') + '"><b>' + esc(s.name) + '</b><span class="untangle-knots" ' +
        'role="img" aria-label="' + esc(s.loose ? t('board_untangle_free') : t('board_untangle_knots', s.knots)) + '">' + marks +
        '</span><small>' + esc(s.loose ? t('board_untangle_free') : t('board_untangle_knots', s.knots)) + '</small></li>';
    }).join('');
    var main = '';
    var buttons = '';
    if (u.phase === 'lobby') {
      main = '<p class="lead">' + esc(t('board_untangle_lobby')) + '</p><p><b>' + esc(t('board_untangle_joined', u.joined)) +
        '</b></p>';
      buttons = '<button class="btn btn-main" data-ugo' + (u.joined ? '' : ' disabled') + '>' + esc(t('board_untangle_go')) +
        '</button><button class="btn btn-soft" data-ufinish>' + esc(t('board_untangle_cancel')) + '</button>';
    } else if (u.phase === 'play') {
      main = '<p class="untangle-clock" data-uleft>' + clock + '</p><p>' + esc(t('board_untangle_loose', {loose: u.loose,
        strands: u.strands.length})) + '</p>';
      buttons = '<button class="btn btn-soft" data-ufinish>' + esc(t('board_untangle_finish')) + '</button>';
    } else {
      var pct = u.strands.length ? Math.round(20 * u.loose / u.strands.length) : 0;
      main = '<h1>' + esc(t(u.outcome === 'won' ? 'board_untangle_won' : u.outcome === 'time' ? 'board_untangle_time' :
        'board_untangle_none')) + '</h1>' + (u.strands.length ? '<p>' + esc(t('board_untangle_loose', {loose: u.loose,
        strands: u.strands.length})) + '</p><p><b>' + esc(t('board_untangle_weaker', pct)) + '</b></p>' : '');
      buttons = '<button class="btn btn-main" data-dismiss-untangle>' + esc(t('board_untangle_back')) + '</button>';
    }
    app.innerHTML = '<div class="board battle-board untangle-board"><header class="board-top">' + T.emblem() + '<div><b>' +
      esc(cfg.coursename) + '</b><small>' + esc(t('board_untangle')) + '</small></div><div class="board-tools">' + musicButton() +
      lightButton() + '<button class="btn btn-soft" data-full>' + esc(t('board_fullscreen')) + '</button></div></header>' +
      '<div class="battle-stage"><div class="battle-boss">' + bossArt(l, total ? knots / total : 1) + '</div><div ' +
      'class="battle-main">' + main + '<div class="actions">' + buttons + '</div></div></div>' + (strands ? '<ol class=' +
      '"untangle-strands">' + strands + '</ol>' : '') + '</div>';
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
    on('[data-ugo]', function() {
      host('go');
    });
    on('[data-ufinish]', function() {
      host('finish');
    });
    on('[data-dismiss-untangle]', function() {
      state.seen = l.id;
      try {
        window.sessionStorage.setItem(SEEN, String(l.id));
      } catch (e) {
        // Without storage, a reload shows the end once more.
      }
      render();
      load(false);
    });
    if (u.phase === 'end' && T.fx && state.untanglefx !== l.id) {
      state.untanglefx = l.id;
      T.fx.sound(u.outcome === 'won' ? 'win' : 'chime');
      if (u.outcome === 'won') {
        T.fx.confetti();
      }
    }
  }

  function schedule() {
    clearTimeout(state.timer);
    if ((battling() || untangling() || nesting()) && !document.hidden) {
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
      } else if (untangling()) {
        drawUntangle();
      } else if (nesting()) {
        drawNest();
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

  /**
   * The skein of the Embrollo comments on how the class is doing, with humour: its stains, its lanterns, its field
   * guide. Never names; only the class.
   *
   * @param {Object} d What is on today.
   * @return {string}
   */
  function quip(d) {
    var m = state.board.mission;
    if (!m) {
      return '';
    }
    var lit = m.chapters.filter(function(ch) {
      return ch.lit;
    }).length;
    var stains = (d.stains || []).length;
    var books = state.cls && state.cls.museum ? state.cls.museum.entries : 0;
    var line = stains >= 2 ? t('board_quip_stains', stains) : lit === m.chapters.length - 1 ? t('board_quip_last') :
      !lit ? t('board_quip_none') : books >= 10 ? t('board_quip_books', books) : t('board_quip_idle');
    return '<p class="quip"><span class="battle-mini" aria-hidden="true">' + T.embrollo(1) + '</span><span>' + esc(line) +
      '</span></p>';
  }

  /**
   * The strength of the expedition: what the class did weakens the Embrollo before the final battle.
   *
   * @param {Object} m The mission.
   * @return {string}
   */
  function expeditionBar(m) {
    var x = m.final && m.expedition;
    if (!x) {
      return '';
    }
    return '<div class="expedition"><div class="expedition-head"><b>' + esc(t('board_expedition')) + '</b><span>' +
      Math.round(100 * x.strength) + ' %</span></div><span class="meter level"><i style="width:' +
      Math.round(100 * x.strength) + '%"></i></span><small class="help-soft">' + esc(t('board_expedition_help', {
        lanterns: x.lanterns[0] + '/' + x.lanterns[1], stains: x.stains, tasks: x.tasks[0] + '/' + x.tasks[1],
        weaker: x.weaker})) + (x.untangled && x.untangled[1] ? ' ' + esc(t('board_expedition_untangled', {loose: x.untangled[0],
        strands: x.untangled[1]})) : '') + '</small>' + (x.known && x.known.length ? '<p class="expedition-known"><b>' +
      esc(t('board_expedition_known', x.known.join(' · '))) + '</b> ' + esc(t('board_expedition_known_help')) + '</p>' : '') +
      '</div>';
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
      expeditionBar(state.board.mission) +
      '<div class="actions"><button class="btn btn-main" data-battlestart>' + esc(t('board_battle_start')) +
      '</button><button class="btn btn-soft" data-battlerehearsal title="' + esc(t('board_battle_rehearsal_help')) + '">' +
      esc(t('board_battle_rehearsal')) + '</button></div><div class="untangle-offer"><p class="help-soft">' +
      esc(t('board_untangle_help')) + '</p><button class="btn btn-soft" data-untangle>' + esc(t('board_untangle_start')) +
      '</button></div>' + (state.cls && state.cls.teams.length ? '<div class="untangle-offer"><p class="help-soft">' +
      esc(t('board_nest_help')) + '</p><div class="pills" style="justify-content:flex-start">' + state.cls.teams.map(function(team) {
        return '<button class="chip" data-nest="' + team.id + '">' + esc(team.name) + '</button>';
      }).join('') + '</div></div>' : '') + '</section>';
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
   * How many of the class can come into a battle: those with a character who are not resting.
   *
   * @return {number}
   */
  function players() {
    return (state.cls ? state.cls.students : []).filter(function(st) {
      return st.character && !(st.character.member && st.character.member.status === 'paused');
    }).length;
  }

  /**
   * By turns, the music and the moments of the battle: its start, its music (the second piece once the Embrollo is
   * frayed), the moment it frays; and back to the soft music when it ends.
   *
   * @param {Object} l
   */
  function turnFx(l) {
    var frayed = l.maxhp && l.hp / l.maxhp <= 0.6;
    T.fx.musicKind(l.outcome ? '' : frayed && l.round ? 'battle2' : 'battle1');
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
        '</p></div>' + (l.sorting ? '<div class="board-sorting" data-sorting></div>' : '');
      buttons = '<button class="btn btn-main" data-dismiss>' + esc(t('board_battle_back')) + '</button>';
    } else if (!l.round) {
      main = '<div class="battle-lobby">' + (l.grown ? '<p class="turn-outcome fell grown">' + esc(t('board_battle_grown')) +
        '</p>' : '') + (l.rematch ? '<p class="turn-outcome fell">' + esc(t('board_battle_rematch',
        l.rematch)) + '</p>' : '') + '<p class="lead">' + esc(t(l.mode === 'phones' ? 'board_battle_join' :
        l.maxcandles ? 'board_turn_aloudhelp' :
        'board_battle_aloudhelp')) + '</p>' + (l.mode === 'phones' ? '<p class="battle-count">' + esc(players() ?
        t('board_battle_joined_of', {joined: l.joined, total: players()}) : t('board_battle_joined', l.joined)) + '</p>' :
        '') + '</div>';
      buttons = '<button class="btn btn-main" data-battlenext' + (l.mode === 'phones' && !l.joined ? ' disabled' : '') + '>' +
        esc(t(l.maxcandles ? 'board_turn_first' : 'board_battle_first')) + '</button>' + (l.kind === 'battle' ?
        '<button class="btn btn-soft" data-battlefinish>' +
        esc(t('board_battle_cancel')) + '</button>' : '');
    } else if (l.stage === 'intent' || l.stage === 'prep') {
      // By turns: the Embrollo lets a knot go, says what it claims and which team it goes for; the roles get ready.
      main = '<p class="battle-qround">' + esc((l.rehearsal ? t('board_battle_rehearsal_tag') + ' · ' : '') + t(l.stage ? 'board_turn_round' : 'board_battle_round', l)) + '</p><div class="knot-call"><p class="knot-name">' +
        esc(l.knot.name) + '</p>' + (l.knot.known ? '<p class="knot-known">' + esc(t('board_turn_known')) + '</p>' : '') +
        '<p class="knot-claim">«' + esc(l.knot.claim.replace(/^«|»$/g, '')) + '»</p>' + intentLine(l) + '</div>' +
        (l.stage === 'prep' ? '<p class="battle-count" data-stageleft="board_turn_prep" aria-live="off">' +
        esc(t('board_turn_prep', l.stageleft)) + '</p>' : '');
      main += tokenLines(l);
    } else if (l.lanes && l.lanes.length) {
      // By turns: each team has its lane in its tablets; here, which one, and once resolved, whether it was undone.
      var playing = aloudTurns(l) && !l.revealed && (l.lanes.filter(function(x) {
        return x.teamid === state.playing && x.play && !x.played;
      })[0] || null);
      main = '<p class="battle-qround">' + esc(t('board_turn_round', l)) + (l.stage === 'strike' ? ' · ' +
        esc(t('board_turn_strike', l.knot.name)) : '') + '</p>' + (playing ? boardLane(l, playing) : '<ul class="lanes">' +
        l.lanes.map(function(lane) {
          var q = lane.question;
          var settled = l.revealed && l.stage !== 'mark';
          return '<li class="lane' + (settled ? (lane.done ? ' done' : ' failed') : '') +
            (l.tangled.indexOf(lane.teamid) >= 0 ? ' tangled' : '') + '"><b>' + esc(lane.team) + '</b><span>' +
            esc(q.text) + '</span>' + (aloudTurns(l) ? aloudLane(l, lane) : '') + (settled || (l.revealed && !lane.play) ?
            '<small>' + (settled ? (lane.done ? '✓ ' : '✗ ') : '') + esc(lane.play ? '' : q.options[q.correct] || '') +
            '</small>' : '') + '</li>';
        }).join('') + '</ul>');
      main += tokenLines(l);
      if (l.stage === 'mark') {
        // By turns aloud: the teacher marks each team (those that played on the screen are marked) and resolves it.
        main += '<p class="help-soft">' + esc(t('board_turn_markhelp')) + '</p>';
        buttons = '<button class="btn btn-main" data-battlesettle>' + esc(t('board_turn_settle')) + '</button>';
      } else if (l.revealed) {
        main += turnOutcome(l);
        buttons = l.round < l.total && l.candles > 0 && l.hp > 0 ? '<button class="btn btn-main" data-battlenext>' +
          esc(t('board_turn_nextnow')) + '</button>' : '';
      } else {
        main += '<p class="battle-count">' + (l.mode === 'phones' ? '<span data-answered>' + esc(t('board_battle_answered', l)) +
          '</span>' : '') + (l.left >= 0 ? ' <span class="battle-left" data-left="' + l.left + '" aria-live="off">' + l.left +
          ' s</span>' : '') + '</p>';
        buttons = '<button class="btn btn-main" data-battlereveal>' + esc(t('board_battle_reveal')) + '</button>';
      }
    } else {
      var q = l.question;
      main = '<p class="battle-qround">' + esc((l.rehearsal ? t('board_battle_rehearsal_tag') + ' · ' : '') + t(l.stage ? 'board_turn_round' : 'board_battle_round', l)) + (l.stage === 'strike' ? ' · ' +
        esc(t('board_turn_strike', l.knot.name)) : '') + '</p><p class="battle-q">' + esc(q.text) +
        '</p><ol class="battle-board-options">' + q.options.map(function(o, i) {
          return '<li class="' + (l.revealed && q.correct === i ? 'right' : '') + '"><b>' + LETTERS[i] + '</b>' + esc(o) + '</li>';
        }).join('') + '</ol>';
      main += tokenLines(l);
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
      var marks = l.mode === 'aloud' && l.revealed && !l.outcome && (!l.maxcandles || l.stage === 'mark') ?
        '<span class="pills"><button class="chip" data-mark="' +
        team.id + ':1" aria-pressed="' + (team.mark === 1) + '" aria-label="' + esc(t('board_battle_markright', team.name)) +
        '">✓</button><button class="chip" data-mark="' + team.id + ':0" aria-pressed="' + (team.mark === 0) +
        '" aria-label="' + esc(t('board_battle_markwrong', team.name)) + '">✗</button><button class="chip argue" data-argue="' +
        team.id + '" aria-pressed="' + !!team.argued + '" aria-label="' +
        esc(t('board_battle_argued_for', team.name)) + '">' + esc(t('board_battle_argued')) + '</button></span>' : '';
      var say = aloudTurns(l) && l.round && !l.revealed && !l.outcome ? teamVerbs(l, team) : '';
      // Aloud, who speaks for the team in this round: by turns, so that everybody gets to say why.
      var speaker = l.mode === 'aloud' && team.speaker && l.round && !l.outcome ? '<span class="tag speaker-tag">' +
        esc(t('board_battle_speaker', team.speaker)) + '</span>' : '';
      var lane = (l.lanes || []).filter(function(x) {
        return x.teamid === team.id;
      })[0];
      var won = l.stage === 'resolve' && lane && lane.done;
      var shielded = l.stage === 'resolve' && l.shielded && l.targetid === team.id;
      return '<div class="battle-team' + (l.targetid === team.id && !l.outcome ? ' target' : '') +
        ((l.tangled || []).indexOf(team.id) >= 0 && !l.outcome ? ' tangled' : '') + (shielded ? ' shielded' : '') +
        ((l.dizzy || []).indexOf(team.id) >= 0 && !l.outcome ? ' dizzy' : '') +
        '" data-teamid="' + team.id + '">' +
        (l.maxcandles ? heroes(team.id) : '') + (won ? '<span class="thread-off" aria-hidden="true">−1</span>' : '') +
        (shielded ? '<span class="team-shield" aria-hidden="true">' + T.icon('shield') + '</span>' : '') + '<b>' + esc(team.name) +
        '</b>' + ((l.dizzy || []).indexOf(team.id) >= 0 && !l.outcome ? '<span class="tag dizzy-tag">' +
        esc(t('board_team_dizzy')) + '</span>' : '') + '<small>' + (l.mode === 'phones' ?
        esc(t('board_battle_joined', team.joined)) + ' · ' : '') + esc(t('board_battle_teamright', team.right)) + '</small>' +
        (team.tactic && l.maxcandles && !l.outcome ? '<span class="tag tactic-tag ' + esc(team.tactic) + '">' +
        esc(t('app_tactic_' + team.tactic)) + '</span>' : '') + speaker + marks + say + '</div>';
    }).join('');
    // The place of the battle, as the story of the mission paints it (the port of the expedition of Azara), or the tower.
    var skin = (state.board.mission || {}).skin;
    var tower = l.maxcandles ? T.artUrl(['scene.battle.' + skin, 'scene.torre']) : null;
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
    playOnBoard(l);
    sorting(l);
    liveBattle(l);
  }

  /**
   * The verbs used in the turn, one line each; aloud, the trail tells there where the answer is and what comes next.
   *
   * @param {Object} l
   * @return {string}
   */
  function tokenLines(l) {
    return (l.tokens || []).map(function(k) {
      return '<p class="battle-token-used">' + esc(k.target ? t('board_token_' + k.kind + '_on', k) :
        t('board_token_' + k.kind, k.team || '')) + (k.place ? ' · ' + esc(t('board_token_trail_place', k.place)) : '') +
        (k.next ? ' · ' + esc(t('board_token_trail_next', k)) : '') + '</p>';
    }).join('') + (l.combos || []).map(function(c) {
      // A double technique: two verbs of a team in the same turn.
      return '<p class="battle-combo">' + esc(t('board_combo', {team: c.team, name: t('app_combo_' + c.code)})) + ' · ' +
        esc(t('app_combo_' + c.code + '_what')) + '</p>';
    }).join('');
  }

  /**
   * Whether the battle is by turns and aloud (with cards, without tablets): the teacher marks the verbs and the teams,
   * and the lanes that are not to choose are played on this screen.
   *
   * @param {Object} l
   * @return {boolean}
   */
  function aloudTurns(l) {
    return l.mode === 'aloud' && !!l.maxcandles;
  }

  /**
   * By turns aloud, under the lane of a team: the options of a lane to choose (with the one a glance struck out), or the
   * button to play it on this screen, or that it was played.
   *
   * @param {Object} l
   * @param {Object} lane
   * @return {string}
   */
  function aloudLane(l, lane) {
    if (lane.play) {
      return lane.played ? '<small class="tag done">' + esc(t('board_lane_played')) + '</small>' : l.stage === 'strike' &&
        !l.revealed ? '<button class="btn btn-soft" data-boardplay="' + lane.teamid + '">' + esc(t('board_lane_play')) +
        '</button>' : '';
    }
    if (l.revealed) {
      return '';
    }
    return '<ol class="lane-options">' + lane.question.options.map(function(o, i) {
      return '<li' + (i === lane.struck ? ' class="struck"' : '') + '><b>' + LETTERS[i] + '</b> ' + esc(o) + '</li>';
    }).join('') + '</ol>';
  }

  /**
   * By turns aloud, the lane of a team that is not to choose, played on this screen by its spokesperson.
   *
   * @param {Object} l
   * @param {Object} lane
   * @return {string}
   */
  function boardLane(l, lane) {
    var team = l.teams.filter(function(x) {
      return x.id === lane.teamid;
    })[0] || {};
    return '<div class="board-lane"><p class="lead"><b>' + esc(lane.team) + '</b>' + (team.speaker ? ' · ' +
      esc(t('board_battle_speaker', team.speaker)) : '') + '</p><div class="lane-play" data-boardlane></div>' +
      '<button class="btn btn-soft" data-boardback>' + esc(t('board_lane_back')) + '</button></div>';
  }

  /**
   * Draws the lane played on this screen, if one is, where boardLane() left its place.
   *
   * @param {Object} l
   */
  function playOnBoard(l) {
    var el = app.querySelector('[data-boardlane]');
    var lane = el && (l.lanes || []).filter(function(x) {
      return x.teamid === state.playing;
    })[0];
    if (!lane || !T.lane) {
      return;
    }
    T.lane(el, JSON.parse(lane.play), {key: 'board-' + l.id + '-' + l.round + '-' + lane.teamid, open: true,
      onsend: function(payload) {
        host('boardplay', {groupid: lane.teamid, meta: JSON.stringify(payload)}).then(function() {
          state.playing = null;
          drawBattle(true);
        });
      }});
  }

  /**
   * By turns aloud, the verbs a team can say now, on its card: the teacher marks the one it says. The shield and the
   * cheer ask first which team they go to; a team can carry a dizzy one.
   *
   * @param {Object} l
   * @param {Object} team
   * @return {string}
   */
  function teamVerbs(l, team) {
    var aim = state.aim && state.aim.team === team.id ? state.aim.kind : '';
    if (aim) {
      return '<span class="pills team-verbs"><small>' + esc(t('board_verb_aim', t('board_verb_' + aim))) + '</small>' +
        l.teams.map(function(to) {
          return '<button class="chip" data-say="' + team.id + ':' + aim + ':' + to.id + '">' + esc(to.name) + '</button>';
        }).join('') + '<button class="chip" data-say="cancel">' + esc(t('board_verb_cancel')) + '</button></span>';
    }
    var carry = (l.dizzy || []).filter(function(id) {
      return id !== team.id;
    }).map(function(id) {
      var to = l.teams.filter(function(x) {
        return x.id === id;
      })[0];
      return '<button class="chip" data-say="' + team.id + ':carry:' + id + '">' + esc(t('board_verb_carry', to ? to.name :
        '')) + '</button>';
    }).join('');
    return (team.verbs || []).length || carry ? '<span class="pills team-verbs">' + (team.verbs || []).map(function(kind) {
      return '<button class="chip verb-' + kind + '" data-say="' + team.id + ':' + kind + '">' + esc(t('board_verb_' + kind)) +
        '</button>';
    }).join('') + carry + '</span>' : '';
  }

  /**
   * The final battle won: the loose threads fall, each one with what a knot tangled, and the class puts each back in its
   * place of the map, aloud and in a minute: one touches, the class decides. Without damage nor prizes; at the end, the
   * map in order and how many went to their place at the first go.
   *
   * @param {Object} l
   */
  function sorting(l) {
    var el = app.querySelector('[data-sorting]');
    clearInterval(state.sortingtimer);
    if (!el || !T.lane) {
      return;
    }
    var s = state.sorting && state.sorting.id === l.id ? state.sorting : null;
    if (!s) {
      el.innerHTML = '<p class="lead">' + esc(t('board_sorting_intro')) + '</p><div class="actions"><button class="btn btn-main" ' +
        'data-sortstart>' + esc(t('board_sorting_start')) + '</button></div>';
      el.querySelector('[data-sortstart]').onclick = function() {
        state.sorting = {id: l.id, until: Date.now() + SORTING * 1000, done: false};
        sorting(l);
      };
      return;
    }
    var key = 'sorting-' + l.id;
    var solution = {};
    l.sorting.solution.forEach(function(x) {
      solution[x.item] = x.bin;
    });
    var finish = function() {
      clearInterval(state.sortingtimer);
      s.done = true;
      sorting(l);
    };
    var placed = (T.lane.kept[key] || {}).placed || {};
    var right = l.sorting.items.filter(function(i) {
      return placed[i.id] === solution[i.id];
    }).length;
    el.innerHTML = (s.done ? '<p class="lead">' + esc(t('board_sorting_done', {right: right, total: l.sorting.items.length})) +
      '</p>' : '<p class="battle-count" data-sortleft aria-live="off"></p>') + '<div class="lane-play" data-sortlane></div>';
    T.lane(el.querySelector('[data-sortlane]'), {engine: 'classify', prompt: t('board_sorting_prompt'), items: l.sorting.items,
      bins: l.sorting.bins, mine: true, nopics: true, solution: s.done ? solution : null}, {key: key, open: !s.done, sent: s.done,
      onsend: finish});
    if (!s.done) {
      var tick = function() {
        var left = Math.max(0, Math.ceil((s.until - Date.now()) / 1000));
        var box = el.querySelector('[data-sortleft]');
        if (box) {
          box.textContent = t('board_sorting_left', left);
        }
        if (!left) {
          finish();
        }
      };
      tick();
      state.sortingtimer = setInterval(tick, 1000);
    }
  }

  /**
   * The battle with movement over the board (battleworld.js), where the screen moves and draws: it is told where the
   * Embrollo and each team are after every drawing, and what happened. Without it, the board as always.
   *
   * @param {Object} l
   */
  function liveBattle(l) {
    if (!T.battleWorld || !T.battleWorld.can()) {
      return;
    }
    // The heroes of a team, as heroes() shows them: the protector in front, five at most.
    var members = function(id) {
      return (state.cls ? state.cls.students : []).filter(function(st) {
        return st.teamid === id && st.character;
      }).sort(function(a, b) {
        return (b.character.role === 'protector') - (a.character.role === 'protector');
      }).slice(0, 5).map(function(st) {
        return Object.assign({userid: st.userid}, st.character);
      });
    };
    var tell = function(w) {
      var boss = app.querySelector('.battle-boss .embrollo');
      var teams = Array.prototype.map.call(app.querySelectorAll('.battle-team[data-teamid]'), function(box) {
        var id = Number(box.dataset.teamid);
        // The heroes stand where the board keeps their place (the battle by turns); without it, only the Embrollo moves.
        var place = box.querySelector('.team-heroes');
        return {id: id, box: place || box, members: place ? members(id) : []};
      });
      w.update(l, {boss: boss, teams: teams});
    };
    if (state.battleWorld && state.battleWorld !== 'opening') {
      tell(state.battleWorld);
    } else if (!state.battleWorld) {
      state.battleWorld = 'opening';
      T.battleWorld.open().then(function(w) {
        if (!battling()) {
          w.close();
          state.battleWorld = null;
          return;
        }
        state.battleWorld = w;
        // For the tests of the browser.
        app.battleWorld = w;
        tell(w);
      }).catch(function() {
        state.battleWorld = null;
      });
    }
  }

  /** The battle is over and left: the movement goes. */
  function unliveBattle() {
    if (state.battleWorld && state.battleWorld !== 'opening') {
      state.battleWorld.close();
    }
    state.battleWorld = null;
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
        esc(l.intent === 'all' ? t('board_turn_shield_all') : t('board_turn_shield', l.target)) + '</p>' : '') +
        // The shield raised in time: the blow bounces back.
        (l.parried ? '<p class="turn-outcome undone parried">' + esc(t('board_battle_parried')) + '</p>' : '') +
        // The teams that pulled the thread in time, a thread more each.
        l.lanes.filter(function(lane) {
          return lane.pulled;
        }).map(function(lane) {
          return '<p class="turn-outcome undone pulled">' + esc(t('board_turn_pulled', lane.team)) + '</p>';
        }).join('') + (l.counter && !l.recovered ? '<p class="turn-outcome fell">' +
        esc(t(l.intent === 'all' ? 'board_turn_candle_all' : 'board_turn_candle', l.target)) + '</p>' : '') +
        (l.recovered ? '<p class="turn-outcome fell">' + esc(t('board_turn_recovered', l.counter)) + '</p>' : '') +
        (l.supported ? '<p class="turn-outcome">' + esc(t('board_turn_supported')) + '</p>' : '') +
        (l.strongmiss ? '<p class="turn-outcome fell">' + esc(t(l.strongmiss === 1 ? 'board_turn_strongmiss_one' :
          'board_turn_strongmiss', l.strongmiss)) + '</p>' : '') +
        // A team carried by another, and the one the blow left dizzy.
        (l.carried || []).map(function(c) {
          return '<p class="turn-outcome undone">' + esc(t('board_turn_carried', c)) + '</p>';
        }).join('') + (l.dizzied ? '<p class="turn-outcome fell">' + esc(t('board_turn_dizzied', l.dizzied)) + '</p>' : '') +
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
    on('[data-argue]', function(el) {
      host('argue', {groupid: +el.dataset.argue});
    });
    on('[data-battlesettle]', function() {
      host('settle');
    });
    on('[data-say]', function(el) {
      var p = el.dataset.say.split(':');
      if (p[0] === 'cancel' || (!p[2] && (p[1] === 'shield' || p[1] === 'cheer'))) {
        // The shield and the cheer go to a team: which one, first.
        state.aim = p[0] === 'cancel' ? null : {team: +p[0], kind: p[1]};
        drawBattle(true);
        return;
      }
      state.aim = null;
      host('verb', {groupid: +p[0], kind: p[1], target: +(p[2] || 0)});
    });
    on('[data-boardplay]', function(el) {
      state.playing = +el.dataset.boardplay;
      drawBattle(true);
    });
    on('[data-boardback]', function() {
      state.playing = null;
      drawBattle(true);
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
  /* ---------- The tools of the classroom: the hourglass and the wheel of fate ---------- */

  /** Minutes the hourglass can be turned for. */
  var MINUTES = [1, 2, 3, 5, 10, 15, 20];

  function toolButtons() {
    return '<button class="btn btn-soft" type="button" data-hourglass>' + esc(t('board_hourglass')) + '</button>' +
      '<button class="btn btn-soft" type="button" data-wheel>' + esc(t('board_wheel')) + '</button>';
  }

  /**
   * A tool over the class screen, with its way out.
   *
   * @param {string} html
   * @param {string} label Id of its title.
   * @return {Element}
   */
  function toolLayer(html, label) {
    closeTool();
    var el = document.createElement('div');
    el.className = 'class-tool';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', label);
    el.innerHTML = '<button type="button" class="btn btn-soft scene-close" data-toolclose aria-label="' + esc(t('app_close')) +
      '">' + T.icon('close') + '</button><div class="class-tool-body">' + html + '</div>';
    document.body.appendChild(el);
    el.querySelector('[data-toolclose]').onclick = closeTool;
    state.tool = el;
    return el;
  }

  function closeTool() {
    clearInterval(state.tooltimer);
    clearTimeout(state.wheeltimer);
    if (state.tool) {
      state.tool.remove();
      state.tool = null;
    }
  }

  /** The hourglass of the Embrollo: the minutes, and then the sand falling with the time left in big. */
  function hourglass() {
    var el = toolLayer('<h1 id="tesape-hourglass">' + esc(t('board_hourglass')) + '</h1><p class="lead">' +
      esc(t('board_hourglass_pick')) + '</p><div class="tool-picks">' + MINUTES.map(function(m) {
        return '<button type="button" class="btn btn-soft" data-minutes="' + m + '">' + esc(t('board_minutes', m)) + '</button>';
      }).join('') + '</div>', 'tesape-hourglass');
    el.querySelectorAll('[data-minutes]').forEach(function(b) {
      b.onclick = function() {
        runHourglass(el, Number(b.dataset.minutes) * 60);
      };
    });
    el.querySelector('[data-minutes]').focus();
  }

  /**
   * The hourglass running: the sand goes down as the time goes, it can wait or get a minute more, and at the end it says
   * so.
   *
   * @param {Element} el
   * @param {number} total Seconds.
   */
  function runHourglass(el, total) {
    var end = Date.now() + total * 1000;
    var left = total * 1000;
    var waiting = false;
    var body = el.querySelector('.class-tool-body');
    body.innerHTML = '<h1 id="tesape-hourglass">' + esc(t('board_hourglass')) + '</h1><div class="hourglass" aria-hidden="true">' +
      '<span class="hg-top"><i></i></span><span class="hg-bottom"><i></i></span></div><div class="show-clock" data-hgclock ' +
      'aria-live="off"></div><div class="actions" style="justify-content:center"><button type="button" class="btn btn-soft" ' +
      'data-hgwait>' + esc(t('board_hourglass_wait')) + '</button><button type="button" class="btn btn-soft" data-hgmore>' +
      esc(t('board_hourglass_more')) + '</button></div>';
    var glass = body.querySelector('.hourglass');
    var clock = body.querySelector('[data-hgclock]');
    var draw = function() {
      if (!waiting) {
        left = Math.max(0, end - Date.now());
      }
      var secs = Math.ceil(left / 1000);
      clock.textContent = Math.floor(secs / 60) + ':' + ('0' + secs % 60).slice(-2);
      glass.style.setProperty('--done', String(1 - left / (total * 1000)));
      if (left <= 0) {
        clearInterval(state.tooltimer);
        clock.textContent = t('board_hourglass_end');
        clock.setAttribute('aria-live', 'assertive');
        glass.classList.add('done');
        body.querySelectorAll('[data-hgwait], [data-hgmore]').forEach(function(b) {
          b.disabled = true;
        });
        if (T.fx) {
          T.fx.sound('win');
        }
      }
    };
    body.querySelector('[data-hgwait]').onclick = function() {
      waiting = !waiting;
      if (!waiting) {
        end = Date.now() + left;
      }
      this.textContent = t(waiting ? 'board_hourglass_go' : 'board_hourglass_wait');
    };
    body.querySelector('[data-hgmore]').onclick = function() {
      total += 60;
      end += 60000;
      left += 60000;
    };
    clearInterval(state.tooltimer);
    state.tooltimer = setInterval(draw, 250);
    draw();
  }

  /** The wheel of fate: a team or a student at random, without repeating until everybody has come out. */
  function wheel() {
    var el = toolLayer('<h1 id="tesape-wheel">' + esc(t('board_wheel')) + '</h1><p class="lead">' + esc(t('board_wheel_help')) +
      '</p><div class="tool-picks"><button type="button" class="btn btn-soft" data-wheelkind="teams">' +
      esc(t('board_wheel_teams')) + '</button><button type="button" class="btn btn-soft" data-wheelkind="students">' +
      esc(t('board_wheel_students')) + '</button></div><div data-wheelbox></div>', 'tesape-wheel');
    el.querySelectorAll('[data-wheelkind]').forEach(function(b) {
      b.onclick = function() {
        spin(el.querySelector('[data-wheelbox]'), b.dataset.wheelkind);
      };
    });
    el.querySelector('[data-wheelkind]').focus();
  }

  /**
   * Who can come out of the wheel: the teams, or the students with a hero, by the name their classmates see.
   *
   * @param {string} kind teams or students.
   * @return {string[]}
   */
  function wheelNames(kind) {
    var cls = state.cls;
    if (kind === 'teams') {
      return cls.teams.map(function(team) {
        return team.name;
      });
    }
    var heroes = cls.region && cls.region.nicknames;
    return cls.students.filter(function(s) {
      return s.character;
    }).map(function(s) {
      return heroes && s.character.nickname ? s.character.nickname : s.firstname;
    });
  }

  /**
   * One turn of the wheel: the names go by, slower and slower, and stop on one that has not come out yet.
   *
   * @param {Element} box
   * @param {string} kind
   */
  function spin(box, kind) {
    clearTimeout(state.wheeltimer);
    var all = wheelNames(kind);
    if (!all.length) {
      box.innerHTML = '<p class="lead">' + esc(t('board_wheel_empty')) + '</p>';
      return;
    }
    var key = 'tesape-wheel-' + cfg.courseid + '-' + kind;
    var used = [];
    try {
      used = JSON.parse(window.sessionStorage.getItem(key) || '[]');
    } catch (e) {
      used = [];
    }
    var left = all.filter(function(n) {
      return used.indexOf(n) < 0;
    });
    if (!left.length) {
      used = [];
      left = all.slice();
    }
    var pick = left[Math.floor(Math.random() * left.length)];
    used.push(pick);
    try {
      window.sessionStorage.setItem(key, JSON.stringify(used));
    } catch (e) {
      // Without storage, the wheel may repeat: nothing else changes.
    }
    box.innerHTML = '<div class="wheel-name" data-wheelname aria-live="polite"></div><p class="help-soft" data-wheelleft></p>' +
      '<div class="actions" style="justify-content:center"><button type="button" class="btn btn-main" data-wheelagain disabled>' +
      esc(t('board_wheel_again')) + '</button><button type="button" class="btn btn-soft" data-wheelreset>' +
      esc(t('board_wheel_reset')) + '</button></div>';
    var name = box.querySelector('[data-wheelname]');
    var steps = T.moves() ? 16 : 0;
    var delay = 60;
    var i = 0;
    var go = function() {
      if (i >= steps) {
        name.textContent = pick;
        name.classList.add('chosen');
        box.querySelector('[data-wheelleft]').textContent = t('board_wheel_left', all.length - used.length);
        var again = box.querySelector('[data-wheelagain]');
        again.disabled = false;
        again.focus();
        if (T.fx) {
          T.fx.sound('right');
        }
        return;
      }
      name.textContent = all[(i * 5 + used.length) % all.length];
      i++;
      delay *= 1.17;
      state.wheeltimer = setTimeout(go, delay);
    };
    box.querySelector('[data-wheelagain]').onclick = function() {
      spin(box, kind);
    };
    box.querySelector('[data-wheelreset]').onclick = function() {
      try {
        window.sessionStorage.removeItem(key);
      } catch (e) {
        // Nothing kept, nothing to forget.
      }
      spin(box, kind);
    };
    go();
  }

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
    // The photos of the classroom the teacher approved, a sticker for each team beside the lantern of its place.
    var photos = (state.cls && state.cls.photos || []).filter(function(p) {
      return p.status === 'approved';
    });
    // The board as a game board: the fog of the Embrollo over the places the class forgot, and the token of each team.
    var fog = state.board.fog || [];
    var tokens = state.board.tokens || [];
    // Each token with the initial of its team; two letters where two teams start alike.
    var bare = function(x) {
      return String(x.team).replace(/^(Team|Equipo)\s+/i, '');
    };
    tokens.forEach(function(x) {
      var one = bare(x).charAt(0).toUpperCase();
      var alike = tokens.filter(function(y) {
        return bare(y).charAt(0).toUpperCase() === one;
      }).length > 1;
      x.initial = alike ? one + bare(x).charAt(1).toLowerCase() : one;
    });
    var places = m.chapters.map(function(ch, i) {
      var here = photos.filter(function(p) {
        return p.chapter === ch.code;
      }).slice(0, 3);
      var foggy = fog.indexOf(ch.code) >= 0;
      var teams = tokens.filter(function(x) {
        return x.place === ch.code;
      });
      // A place the class lit that the Embrollo came back to: its lantern flickers until the stain is cleaned.
      var back = ch.lit && ((state.board.today && state.board.today.stains) || []).some(function(s) {
        return s.chapter === ch.code && s.kind !== 'page';
      });
      return '<g class="place' + (ch.lit ? ' mine lit' : '') + (back ? ' flicker' : '') + (foggy ? ' foggy' : '') + '" data-art="' +
        esc(ch.art || ch.code) + '" aria-label="' + esc(ch.name + (foggy ? ' · ' + t('board_fog_place') : '') +
        (teams.length ? ' · ' + teams.map(function(x) {
          return x.team;
        }).join(', ') : '')) + '">' + here.map(function(p, k) {
          return T.photoSticker(p, ch.x, ch.y, i, k);
        }).join('') +
        (ch.lit ? '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="40" class="classglow"/>' : '') +
        '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="25" class="ring' + (lampOff && lampOn ? ' painted' : '') + '"/>' +
        (lampOff && lampOn ? '<image href="' + esc(ch.lit ? lampOn : lampOff) + '" x="' + (ch.x - 20) + '" y="' + (ch.y - 30) +
        '" width="40" height="50" class="' + (ch.lit ? 'lamp-on' : 'lamp-off') + '"/>' :
        '<rect x="' + (ch.x - 2) + '" y="' + (ch.y - 4) + '" width="4" height="17" rx="1" fill="#2b2219"/>' +
        '<circle cx="' + ch.x + '" cy="' + (ch.y - 7) + '" r="7" class="flame"/>') +
        T.mapLabel(ch.x, ch.y + 44, ch.name) + (foggy ? fogCloud(ch.x, ch.y) : '') + teams.map(function(x, k) {
          return teamToken(x, ch.x, ch.y, k);
        }).join('') + '</g>';
    }).join('') + dozing(m) + stickers(m);
    var height = T.mapHeight(m);
    var fresh = T.mapNewRoad('board-' + cfg.courseid + '-' + m.code, m.chapters, Math.min(lit, m.chapters.length - 1));
    var drawn = fresh.road ? fresh.road.replace('/>', ' style="animation-delay:' + (0.3 + fresh.delay) + 's,' +
      (2.4 + fresh.delay) + 's"/>') : '';
    return '<section class="board-map"><h2>' + esc(m.title) + '</h2><div class="map-wrap' + (height > 640 ? ' tall' : '') +
      '"><svg class="map" viewBox="0 0 400 ' + height + '" role="img" aria-label="' + esc(m.title) + '"><defs>' + T.MAPGLOW +
      '</defs>' + T.mapGround(m) + T.mapLandmarks(m) + T.mapNight(m) +
      '<polyline points="' + pts + '" class="road"/>' + (lit ? '<polyline points="' + walked + '" class="road walked"/>' : '') +
      drawn + (T.critters ? T.critters.fireflies(m) : '') + places + '</svg></div>' + fogNote(m, fog) + fromFar() + classRow() +
      pastExpeditions() + '</section>';
  }

  /**
   * What the fog says under the map: the places the class has forgotten and, with three or more, that the Embrollo grows.
   *
   * @param {Object} m The mission.
   * @param {string[]} fog Codes of the places in the fog.
   * @return {string}
   */
  function fogNote(m, fog) {
    if (!fog.length) {
      return '';
    }
    var names = m.chapters.filter(function(ch) {
      return fog.indexOf(ch.code) >= 0;
    }).map(function(ch) {
      return ch.name;
    });
    var list = names.length > 1 ? names.slice(0, -1).join(', ') + ' ' + t('board_and') + ' ' + names[names.length - 1] :
      names[0];
    return '<p class="fog-note">' + esc(t('board_fog_note', list)) + (fog.length >= 3 ? ' ' +
      esc(t('board_fog_grows')) : '') + '</p>';
  }

  /**
   * Who brought their strength from far, playing the last battle in deferred from home or the hospital.
   *
   * @return {string}
   */
  function fromFar() {
    var list = state.board.fromfar || [];
    if (!list.length) {
      return '';
    }
    var names = list.map(function(x) {
      return x.name;
    });
    var who = names.length > 1 ? names.slice(0, -1).join(', ') + ' ' + t('board_and') + ' ' + names[names.length - 1] : names[0];
    return '<p class="from-far">' + esc(t('board_fromfar', who)) + '</p>';
  }

  /**
   * The fog of the Embrollo over a place the class has forgotten: lilac clouds that drift over its lantern.
   *
   * @param {number} x
   * @param {number} y
   * @return {string}
   */
  function fogCloud(x, y) {
    return '<g class="fog-cloud" aria-hidden="true">' + [[-26, -6, 22], [-4, -16, 26], [20, -4, 20], [0, 8, 24]].map(function(c) {
      return '<ellipse cx="' + (x + c[0]) + '" cy="' + (y + c[1]) + '" rx="' + c[2] + '" ry="' + Math.round(c[2] * 0.62) + '"/>';
    }).join('') + '</g>';
  }

  /**
   * The token of a team on the board, beside the place where it works: a round flag with its initial.
   *
   * @param {Object} x teamid, team, place.
   * @param {number} cx
   * @param {number} cy
   * @param {number} k Its order among the teams of the place.
   * @return {string}
   */
  function teamToken(x, cx, cy, k) {
    var tx = cx - 34 + (k % 3) * 22;
    var ty = cy + 62 + Math.floor(k / 3) * 22;
    return '<g class="team-token token-' + (x.teamid % 6) + (String(x.initial).length > 1 ? ' two' : '') + '" aria-hidden="true">' +
      '<circle cx="' + tx + '" cy="' + ty + '" r="10"/><text x="' + tx + '" y="' + (ty + 4) + '" text-anchor="middle">' +
      esc(x.initial) + '</text></g>';
  }

  /**
   * The class at the foot of the map, team by team: their heroes breathing, without names. A team that did more of its
   * part of the week since the screen last looked jumps for a moment: the team, never who did it.
   *
   * @return {string}
   */
  function classRow() {
    var cls = state.cls;
    if (!cls || !cls.teams.length) {
      return '';
    }
    var done = {};
    (cls.standings || []).forEach(function(x) {
      done[x.id] = x.done;
    });
    var seen = state.rowdone;
    state.rowdone = done;
    return '<div class="class-row" aria-hidden="true">' + cls.teams.map(function(team, i) {
      var row = heroes(team.id);
      var cheer = seen && seen[team.id] !== undefined && (done[team.id] || 0) > seen[team.id];
      return row ? '<span class="class-row-team' + (cheer ? ' cheer' : '') + '" style="--breath:-' + (i * 0.7).toFixed(1) +
        's">' + row + '</span>' : '';
    }).join('') + '</div>';
  }

  /**
   * The decisions of the class by their places: a sticker for each one that at least half of it took (the frog in its
   * pond, the lantern of the tower), with its drawing if the art pack has it.
   *
   * @param {Object} m The mission of the board.
   * @return {string}
   */
  function stickers(m) {
    return m.chapters.map(function(ch) {
      // Over the lantern of the place, one above the other: the name of the place is under it.
      return (ch.stickers || []).slice(0, 3).map(function(st, i) {
        var art = T.artUrl(['sticker.' + st.flag]);
        return '<foreignObject x="' + (ch.x - 90) + '" y="' + (ch.y - 64 - i * 24) + '" width="180" height="22" ' +
          'class="sticker-fo"><div xmlns="http://www.w3.org/1999/xhtml" class="sticker-row"><span class="sticker' +
          (i % 2 ? ' tilt' : '') + '">' + (art ? '<img src="' + esc(art) + '" alt="">' : '<span class="sticker-dot" ' +
          'aria-hidden="true"></span>') + '<span>' + esc(st.text) + '</span></span></div></foreignObject>';
      }).join('');
    }).join('');
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
    // And the class answers it: who knows why it is not so.
    var ask = d.mixup ? '<p class="taunt-ask">' + esc(t('board_today_ask', d.mixup)) + '</p>' : '';
    return '<section class="card board-today"><div class="kicker">' + esc(t('board_today')) + '</div><h3>' +
      esc(d.chapter) + '</h3>' + (d.gloat ? '<blockquote class="letter gloat"><small>' + esc(t('board_today_gloat')) +
      '</small>' + esc(d.gloat) + '</blockquote>' : '') + stainsList(d.stains || [], award) + quip(d) +
      (award && d.lostscene ? '<div class="actions board-small">' +
      '<button class="btn btn-soft" data-playscene="' + d.lostscene.id + '">' + esc(t('board_today_scene', d.lostscene.title)) +
      '</button></div>' : '') + (d.letter ? '<blockquote class="letter"><small>' + esc(t('board_today_letter',
      d.letter.chapter)) + '</small>' + esc(d.letter.text) + taunt + '</blockquote>' + (taunt ? ask : '') + (d.letter.teaser ? '<p class="teaser">' +
      '<b>' + esc(t('app_teaser')) + '</b> ' + esc(d.letter.teaser) + '</p>' : '') : '') + (sessions ?
      '<ol class="today-sessions">' + sessions + '</ol>' : '') + (d.offscreen ? '<p class="help-soft">' +
      esc(t('board_today_offscreen', d.offscreen)) + '</p>' : '') + (d.mixup && !taunt ? '<p class="help-soft">' +
      esc(t('board_today_mixup', d.mixup)) + '</p>' + ask : '') + '</section>';
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
      return '<li><span class="stain-dot" aria-hidden="true"></span><span>' + esc(t(s.kind === 'page' ? 'board_today_page' : 'board_today_stain', s)) + '</span>' +
        (award && s.kind !== 'page' && (s.parts || []).length ? '<button type="button" class="btn btn-main" data-cleanclass="' +
        s.id + '">' + esc(t('board_stain_clean')) + '</button>' : '') +
        (award ? '<button type="button" class="btn btn-soft" data-erasestain="' + s.id + '">' + esc(t(s.kind === 'page' ? 'board_page_erase' : 'board_stain_erase')) +
        '</button>' : '') + '</li>';
    }).join('') + '</ul>' : '';
  }

  /**
   * A stain cleaned by the whole class, aloud: its sheet on the class screen, the class says which words are wrong and
   * the teacher touches them. Every mistake found, it is clean for the class (nobody gets a gem) and the Embrollo
   * complains; if not, the ink stays and it is tried again.
   *
   * @param {Object} stain id, place, title, parts.
   */
  function cleanWithClass(stain) {
    var layer = document.createElement('div');
    layer.className = 'session-show shows-cleanclass';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-cleanclass');
    layer.innerHTML = '<button type="button" class="btn btn-soft scene-close" aria-label="' + esc(t('app_close')) + '">' +
      T.icon('close') + '</button><div class="show-card cleanclass"><div class="kicker">' + esc(t('app_stain_on', stain.place)) +
      '</div><h1 id="tesape-cleanclass">' + esc(stain.title) + '</h1><p class="lead">' + esc(t('board_stain_clean_help')) +
      '</p><div class="cleanclass-sheet" data-sheet></div><div data-said aria-live="polite"></div></div>';
    document.body.appendChild(layer);
    var key = 'board-stain-' + stain.id;
    var lane = {engine: 'errors', parts: stain.parts, mine: true};
    var close = function() {
      layer.remove();
      load(false);
    };
    layer.querySelector('.scene-close').onclick = close;
    var sheet = layer.querySelector('[data-sheet]');
    var draw = function() {
      T.lane(sheet, lane, {key: key, sent: false, open: true, onsend: function(payload) {
        T.api('erase_stain', {courseid: cfg.courseid, stainid: stain.id, payload: JSON.stringify(payload)}).then(function(r) {
          var said = layer.querySelector('[data-said]');
          if (!r.clean) {
            delete T.lane.kept[key];
            said.innerHTML = '<p class="turn-outcome fell">' + esc(t('app_stain_still')) + '</p>';
            draw();
            return;
          }
          lane.solution = r.solution;
          T.lane(sheet, lane, {key: key, sent: true, open: false, onsend: function() {}});
          said.innerHTML = '<p class="turn-outcome undone">' + esc(t('board_stain_cleaned')) + '</p>' + (r.complaint ?
            '<p class="knot-laugh">«' + esc(r.complaint.replace(/^«|»$/g, '')) + '»</p>' : '');
          if (T.fx) {
            T.fx.sound('lantern');
          }
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          close();
        });
      }});
    };
    draw();
    layer.querySelector('.scene-close').focus();
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
    var acted = e.solved || e.gifts.length || e.to || e.cleaned || (e.loans && e.loans.length);
    var body = '';
    if (e.code === 'footprint' && e.question) {
      body = footprint(e);
    } else if (e.code === 'exchange') {
      body = exchange(e);
    } else if (e.code === 'trade') {
      body = trade(e);
    } else if (e.code === 'knot' || e.code === 'messengers') {
      body = forTeam(e, acted);
    }
    // The mission may give the card its own name and text.
    return '<section class="card board-event card-' + esc(e.code) + '">' + head + '<div class="event-head"><span ' +
      'class="event-icon">' + T.icon(CARD_ICON[e.code] || 'saber') + '</span><div><h2>' + esc(e.name || t('event_' + e.code)) +
      '</h2><p>' + esc(e.text || t('event_' + e.code + '_text')) + '</p></div></div>' + body + (acted ? '' :
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

  /**
   * A card for a team: the team it goes to (another one can be chosen before anything comes of it) and how it goes: the
   * knot of the team, cleaned or not; the team the messengers take their hint to.
   *
   * @param {Object} e The event.
   * @param {boolean} acted Something came of it.
   * @return {string}
   */
  function forTeam(e, acted) {
    var who = teamName(e.target);
    var html = acted ? '<p class="event-for">' + esc(t('board_event_for', who)) + '</p>' :
      '<div class="event-for"><span>' + esc(t('board_event_for', '')) + '</span><span class="pills">' +
      state.cls.teams.map(function(team) {
        return '<button class="chip" data-aim="' + team.id + '" aria-pressed="' + (team.id === e.target) + '">' +
          esc(team.name) + '</button>';
      }).join('') + '</span></div>';
    if (e.code === 'knot') {
      return html + '<p class="help-soft">' + esc(t(e.cleaned ? 'board_knot_done' : 'board_knot_open', who)) + '</p>';
    }
    if (e.to) {
      return html + '<p class="help-soft">' + esc(t('board_messengers_done', {from: who, to: teamName(e.to)})) + '</p>';
    }
    return html + '<div class="event-for"><b>' + esc(t('board_messengers_to', who)) + '</b><span class="pills">' +
      state.cls.teams.filter(function(team) {
        return team.id !== e.target;
      }).map(function(team) {
        return '<button class="chip" data-deliver="' + team.id + '"' + (can('award') ? '' : ' disabled') + '>' +
          esc(team.name) + '</button>';
      }).join('') + '</span></div>';
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

  /**
   * The trade: each team chooses one of the items its members carry, and then the team it lends it to, until tomorrow.
   *
   * @param {Object} e
   * @return {string}
   */
  function trade(e) {
    var lent = {};
    (e.loans || []).forEach(function(l) {
      lent[l.from] = l;
    });
    var offers = {};
    (e.offers || []).forEach(function(o) {
      offers[o.team] = o.items;
    });
    return '<ul class="list exchange trade">' + state.cls.teams.map(function(team) {
      var loan = lent[team.id];
      if (loan) {
        return '<li class="item"><span class="grow">' + esc(t('board_lent', {from: team.name, item: loan.name,
          to: teamName(loan.to)})) + '</span></li>';
      }
      var items = offers[team.id] || [];
      if (!items.length) {
        return '<li class="item"><span class="grow help-soft">' + esc(t('board_nothing_to_lend', team.name)) + '</span></li>';
      }
      var chosen = items.filter(function(i) {
        return i.code === state.lend[team.id];
      })[0];
      if (!chosen) {
        return '<li class="item" style="flex-wrap:wrap"><span class="grow"><b>' + esc(t('board_lends', team.name)) +
          '</b></span><span class="pills">' + items.map(function(i) {
            return '<button class="chip" data-lenditem="' + team.id + ':' + esc(i.code) + '"' + (can('award') ? '' :
              ' disabled') + '>' + esc(i.name) + '</button>';
          }).join('') + '</span></li>';
      }
      return '<li class="item" style="flex-wrap:wrap"><span class="grow"><b>' + esc(team.name) + ' · ' +
        esc(t('board_lend_to', chosen.name)) + '</b></span><span class="pills">' + state.cls.teams.filter(function(other) {
          return other.id !== team.id;
        }).map(function(other) {
          return '<button class="chip" data-lend="' + team.id + ':' + other.id + '"' + (can('award') ? '' : ' disabled') + '>' +
            esc(other.name) + '</button>';
        }).join('') + '<button class="chip" data-lenditem="' + team.id + ':">' + esc(t('board_lend_other')) + '</button></span></li>';
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
      // The banner of the team, as it has grown with the weeks it reached its goal.
      var flag = team.bannerstage ? '<span class="team-flag-box" title="' + esc(t('app_banner_now',
        t('app_banner_' + team.bannerstage.name))) + '">' + T.banner(team.bannerstage, team.id) + '</span>' : '';
      return '<div class="board-team"><button type="button" class="team-name" data-team="' + team.id + '">' + flag + lantern +
        esc(team.name) + '</button>' + teamState(team.id, (state.teamstates || {})[team.id] || team.state) + (w ? '<span class="meter level"><i style="width:' + pct + '%"></i></span>' : '') +
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

  /**
   * The remote of a scene played by the class: what the class screen shows to press (the options, «Go on», closing a
   * vote…) goes to the phone of the teacher, and what they press there is pressed here. While the scene is open.
   *
   * @return {function} Stops it.
   */
  function relay() {
    var api = function(action, extra) {
      return T.api('scene_remote', Object.assign({courseid: cfg.courseid, action: action}, extra || {}));
    };
    var layer = null;
    var sent = '';
    var seen = -1;
    var timer = 0;
    var observer = null;
    var later = 0;
    // The buttons a person could press now in the scene: visible, enabled, with something to say; not the close.
    var buttons = function() {
      return layer ? Array.prototype.filter.call(layer.querySelectorAll('button'), function(b) {
        return !b.disabled && b.offsetParent !== null && !b.closest('.scene-close') && !b.hasAttribute('data-still') &&
          (b.getAttribute('aria-label') || b.textContent).trim() !== '';
      }).slice(0, 12) : [];
    };
    // What a button says, as it reads: each of its pieces apart (the letter of an option, then its text).
    var label = function(b) {
      var said = Array.prototype.map.call(b.childNodes, function(n) {
        return n.textContent.trim();
      }).filter(function(x) {
        return x;
      }).join(' ');
      return (said || b.getAttribute('aria-label') || '').replace(/\s+/g, ' ');
    };
    // The key of a button: its place and what it says, so that a press made on what was shown is not done on another.
    var keyOf = function(b, i) {
      var text = label(b);
      var hash = 0;
      for (var k = 0; k < text.length; k++) {
        hash = (hash * 31 + text.charCodeAt(k)) % 1000003;
      }
      return 'b' + i + '-' + hash;
    };
    var publish = function() {
      if (!layer) {
        return;
      }
      var list = buttons().map(function(b, i) {
        return {key: keyOf(b, i), label: label(b)};
      });
      var title = layer.querySelector('.scene-title');
      var ask = layer.querySelector('.scene-body .scene-prompt');
      var payload = {title: title ? title.textContent.trim() : '', text: ask ? ask.textContent.trim().slice(0, 400) : '',
        buttons: JSON.stringify(list)};
      var sig = JSON.stringify(payload);
      if (sig === sent) {
        return;
      }
      sent = sig;
      api('publish', payload).catch(function() {
        sent = '';
      });
    };
    var poll = function() {
      if (!layer) {
        layer = document.querySelector('.scene-layer');
        if (layer) {
          observer = new MutationObserver(function() {
            clearTimeout(later);
            later = setTimeout(publish, 300);
          });
          observer.observe(layer, {childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'class']});
          publish();
        }
        return;
      }
      api('alive').then(function(r) {
        if (!r.on) {
          sent = '';
          publish();
          return;
        }
        // What was pressed before the scene opened is not done again.
        if (seen < 0) {
          seen = r.seq;
          return;
        }
        if (r.seq > seen) {
          seen = r.seq;
          var match = buttons().filter(function(b, i) {
            return keyOf(b, i) === r.pressed;
          })[0];
          if (match) {
            match.click();
          }
        }
      }).catch(function() {
        // The next poll tries again.
      });
    };
    timer = setInterval(poll, 1500);
    poll();
    return function() {
      clearInterval(timer);
      clearTimeout(later);
      if (observer) {
        observer.disconnect();
      }
      layer = null;
      api('clear').catch(function() {
        return null;
      });
    };
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
        open: function(text, labels, meta) {
          return host('vote', {text: text, options: JSON.stringify(labels), meta: JSON.stringify(meta || {})});
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
      var unrelay = relay();
      T.playScene({sceneid: sceneid, world: world, classmode: classmode, onclose: function() {
        unrelay();
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

  /**
   * The museum of the class in the Library: the shelves with books, for the class to read together.
   *
   * @return {string}
   */
  function museumCard() {
    var m = state.cls.museum;
    if (!m) {
      return '';
    }
    return '<section class="card board-museum"><div class="kicker">' + esc(t('app_museum')) + '</div><p class="help-soft">' +
      esc(t('app_museum_help', {entries: m.entries, lit: m.shelves.filter(function(s) {
        return s.lit;
      }).length})) + '</p>' + T.museum(m) + '</section>';
  }

  /**
   * The desk of the teacher, folded at the end of the class screen: what the class plays with and what to print. The
   * class looks at the stage, not at the desk.
   *
   * @return {string}
   */
  function deskCard() {
    return '<details class="card board-desk board-print"' + (state.desk ? ' open' : '') + '><summary>' +
      esc(t('board_desk')) + '</summary>' + devicesPicker() + '<h3>' + esc(t('board_print')) + '</h3><p class="help-soft">' +
      esc(t('board_print_help')) + '</p><div class="actions board-small">' +
      ['votes', 'teams', 'chars', 'heroes', 'knots', 'diplomas'].map(function(k) {
        return '<button class="btn btn-soft" data-print="' + k + '">' + esc(t('board_print_' + k)) + '</button>';
      }).join('') + '</div></details>';
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

  /**
   * A hero sheet of one page for each student with a character: their screen when there is no tablet, and what they
   * take home. Portrait, name, role and what it does, their creature, boxes to mark their energy and breath, and lines
   * for their talents and what their team carries.
   *
   * @param {string} fam The family of names of the region.
   * @return {string}
   */
  function heroSheets(fam) {
    var boxes = function(n) {
      return '<span class="boxes" aria-hidden="true">' + new Array(n + 1).join('<i></i>') + '</span>';
    };
    var lines = function(n) {
      return new Array(n + 1).join('<span class="write-line"></span>');
    };
    return state.cls.students.filter(function(s) {
      return s.character;
    }).map(function(s) {
      var c = s.character;
      var team = state.cls.teams.filter(function(x) {
        return x.id === s.teamid;
      })[0];
      var pet = '';
      try {
        pet = JSON.parse(c.creature || '{}').name || '';
      } catch (e) {
        pet = '';
      }
      return '<div class="sheet-page hero-sheet"><div class="hero-top"><span class="face">' + portrait(s) + '</span><div>' +
        '<p class="diploma-kicker">Tesapé</p><h1>' + esc(s.fullname) + '</h1><p class="lead">' +
        esc(T.roleName(fam, c.role, c.nameform)) + ' · ' + esc(t('app_level')) + ' ' + c.level + (team ? ' · ' +
        esc(team.name) : '') + '</p><p><b>' + esc(t('board_hero_does')) + ':</b> ' + esc(t('app_role_does_' + c.role)) +
        '</p></div></div>' + (c.creature ? '<div class="hero-pet"><span class="pet-art">' +
        T.creature(c.creature, c.creaturestage) + '</span><p><b>' + esc(t('board_hero_creature')) + ':</b> ' + esc(pet) +
        '</p></div>' : '') + '<div class="hero-boxes"><p><b>' + esc(t('app_energy')) + '</b> ' + boxes(10) + '</p><p><b>' +
        esc(t('board_hero_breath')) + '</b> ' + boxes(5) + '</p></div><div class="hero-write"><p><b>' +
        esc(t('board_hero_talents')) + '</b></p>' + lines(3) + '<p><b>' + esc(t('board_hero_items')) + '</b></p>' + lines(2) +
        '</div><p class="sheet-foot">Tesapé · ' + esc(cfg.coursename) + '</p></div>';
    }).join('');
  }

  /**
   * The cards of the knots of the Embrollo, four to a page: what each one claims, what undoes it and why. For the
   * battle with cards and for the wall of the class.
   *
   * @return {string}
   */
  function knotCards() {
    var knots = (state.board.mission && state.board.mission.knots) || [];
    var pages = '';
    for (var i = 0; i < knots.length; i += 4) {
      pages += '<div class="sheet-page knot-cards">' + knots.slice(i, i + 4).map(function(k) {
        return '<div class="knot-card"><span class="battle-mini" aria-hidden="true">' + T.embrollo(1) + '</span><h2>' +
          esc(k.name) + '</h2><p><b>' + esc(t('board_knot_says')) + '</b> «' + esc(k.claim.replace(/^«|»$/g, '')) + '»</p>' +
          '<p><b>' + esc(t('board_knot_undo')) + '</b> ' + esc(k.weakness) + '</p><p class="knot-why">' + esc(k.why) +
          '</p></div>';
      }).join('') + '</div>';
    }
    return pages;
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
    } else if (state.print === 'heroes') {
      pages = heroSheets(fam);
    } else if (state.print === 'knots') {
      pages = knotCards();
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
    if (untangling()) {
      drawUntangle();
      return;
    }
    if (nesting()) {
      drawNest();
      return;
    }
    unliveBattle();
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
      cfg.courseid) + '">' + esc(t('board_panel')) + '</a>' + toolButtons() + musicButton() + lightButton() +
      '<button class="btn btn-soft" data-full>' +
      esc(t('board_fullscreen')) + '</button></div></header>' +
      '<div class="board-grid">' + map() + '<div class="board-side">' + todayCard() + openerCard() + eventCard() + battleCard() +
      sceneCard() + teams() + museumCard() + deskCard() + '</div></div>' + bar() + '</div>';
    bind();
    var museum = app.querySelector('.board-museum');
    if (museum) {
      T.bindMuseum(museum, state.cls.museum, state.book, function(book) {
        state.book = book;
      });
    }
    bindHuddles();
  }

  function bind() {
    var on = function(sel, fn) {
      app.querySelectorAll(sel).forEach(function(el) {
        el.onclick = function() {
          fn(el);
        };
      });
    };
    // The desk stays open or folded as the teacher left it when the class screen is drawn again.
    var desk = app.querySelector('.board-desk');
    if (desk) {
      desk.ontoggle = function() {
        state.desk = desk.open;
      };
    }
    on('[data-full]', fullscreen);
    on('[data-light]', switchLight);
    on('[data-music]', switchMusic);
    on('[data-hourglass]', hourglass);
    on('[data-wheel]', wheel);
    on('[data-openerhide]', function() {
      state.openerhidden = true;
      render();
    });
    on('[data-battle]', function(el) {
      var p = el.dataset.battle.split(':');
      state.battle[p[0]] = p[0] === 'mode' ? p[1] : +p[1];
      // The questions and the time of the last battle, the next time: one button to launch it.
      try {
        window.localStorage.setItem(BATTLE, JSON.stringify({count: state.battle.count, secs: state.battle.secs}));
      } catch (e) {
        // Without storage, the usual ones.
      }
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
    on('[data-cleanclass]', function(el) {
      var stain = ((state.board && state.board.today && state.board.today.stains) || []).filter(function(x) {
        return x.id === +el.dataset.cleanclass;
      })[0];
      if (stain) {
        cleanWithClass(stain);
      }
    });
    on('[data-print]', function(el) {
      state.print = el.dataset.print;
      render();
    });
    on('[data-battlestart]', function() {
      var b = state.battle;
      host('start', {mode: b.mode, count: b.count, secs: b.secs, final: !!b.final, turns: true});
    });
    // Three test questions to check the phones, without prizes nor the questions of the mission.
    on('[data-untangle]', function() {
      host('untangle');
    });
    on('[data-nest]', function(el) {
      host('nest', {groupid: Number(el.dataset.nest)});
    });
    on('[data-battlerehearsal]', function() {
      var b = state.battle;
      host('start', {mode: b.mode, count: 4, secs: b.secs || 30, rehearsal: true});
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
    on('[data-lenditem]', function(el) {
      var p = el.dataset.lenditem.split(':');
      state.lend[p[0]] = p[1];
      render();
    });
    on('[data-lend]', function(el) {
      var p = el.dataset.lend.split(':');
      act(T.api('class_event', {courseid: cfg.courseid, action: 'lend', from: +p[0], to: +p[1], item: state.lend[p[0]]}));
    });
    on('[data-aim]', function(el) {
      act(T.api('class_event', {courseid: cfg.courseid, action: 'aim', to: +el.dataset.aim}));
    });
    on('[data-deliver]', function(el) {
      act(T.api('class_event', {courseid: cfg.courseid, action: 'deliver', to: +el.dataset.deliver}));
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
