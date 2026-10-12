/**
 * Tesapé: the teacher panel, made for the phone first. The class with names, photos, characters and teams; touches
 * of knowledge, gems and breath; the powers the students ask for, approved with a tap; the word on classroom
 * challenges, also without connection; the mission and how the class is doing; the box of ideas; pause and recovery;
 * the log with undo; and the settings of the course.
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
  var state = {data: null, tab: 'class', team: 0, selected: {}, open: 0, log: null, logUser: 0, challenge: 0, powers: null,
    ideas: null, newpower: false};
  var QUEUE = 'tesape-queue-' + cfg.courseid + '-' + cfg.userid;

  /**
   * A date and time, short.
   *
   * @param {number} time
   * @return {string}
   */
  function when(time) {
    try {
      return new Intl.DateTimeFormat(cfg.lang, {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'})
        .format(new Date(time * 1000));
    } catch (e) {
      return new Date(time * 1000).toLocaleString();
    }
  }

  function can(cap) {
    return cfg.caps && cfg.caps[cap];
  }

  /* ---------- Loading, and the queue for when there is no connection ---------- */

  function load(keep) {
    if (!keep) {
      app.innerHTML = '<div class="loading"><div><div class="lamp"></div>' + esc(t('app_loading')) + '</div></div>';
    }
    return T.api('get_class', {courseid: cfg.courseid}).then(function(data) {
      state.data = data;
      T.family = data.region.family;
      render();
    }).catch(function(e) {
      app.innerHTML = '<div class="story"><h2>' + esc(t('app_error')) + '</h2><p class="help">' + esc(e.message) +
        '</p><div class="actions"><button class="btn btn-main" data-retry>' + esc(t('app_retry')) + '</button></div></div>';
      app.querySelector('[data-retry]').onclick = function() {
        load();
      };
    });
  }

  function queued() {
    try {
      return JSON.parse(window.localStorage.getItem(QUEUE) || '[]');
    } catch (e) {
      return [];
    }
  }

  function setQueue(list) {
    try {
      window.localStorage.setItem(QUEUE, JSON.stringify(list));
    } catch (e) {
      // Nothing to keep it in: the action is simply lost, and the teacher was told it failed.
    }
  }

  /**
   * Sends the word on a classroom challenge; without connection it waits in the queue (saying it again is harmless).
   *
   * @param {Object} args
   * @return {Promise}
   */
  function sendValidation(args) {
    return T.api('validate', args).catch(function(e) {
      if (!e.network && navigator.onLine !== false) {
        throw e;
      }
      var list = queued();
      list.push(args);
      setQueue(list);
      T.toast(esc(t('teach_queued')));
      return null;
    });
  }

  function flush() {
    var list = queued();
    if (!list.length || navigator.onLine === false) {
      return;
    }
    var next = list[0];
    T.api('validate', next).then(function() {
      setQueue(queued().slice(1));
      if (queued().length) {
        flush();
      } else {
        T.toast(esc(t('teach_sent')));
        load(true);
      }
    }).catch(function(e) {
      if (e.network) {
        // Still no luck; it will try again when the connection comes back.
        return;
      }
      // The server said no (the challenge was deleted meanwhile, for instance): it will not say yes later.
      T.toast(esc(e.message), true);
      setQueue(queued().slice(1));
      flush();
    });
  }

  window.addEventListener('online', flush);

  /* ---------- Frame ---------- */

  var TABS = ['class', 'powers', 'mission', 'challenges', 'log'];

  function render() {
    var d = state.data;
    if (!d.region.enabled && state.tab !== 'settings') {
      welcome();
      return;
    }
    var pending = queued().length;
    app.innerHTML = '<div class="sky"></div><div class="world">' +
      '<header class="hud"><div class="me"><span class="face" style="display:grid;place-items:center">' +
      '<span style="width:26px;height:26px;color:var(--lantern)">' + T.icon('saber') + '</span></span><span class="who">' +
      '<b>' + esc(cfg.coursename) + '</b><small>' + esc(t('teach_title')) + (pending ? ' · ' + esc(t('teach_pending',
      pending)) : '') + '</small></span></div><div class="hud-actions"><button class="btn btn-soft icon-btn" type="button" ' +
      'data-tab="ideas" aria-label="' + esc(t('teach_tab_ideas')) + '"' + (state.tab === 'ideas' ? ' aria-current="page"' : '') +
      '>' + T.icon('idea') + (d.ideasunread ? '<span class="badge">' + d.ideasunread + '</span>' : '') + '</button>' +
      (can('runclassscreen') ? '<a class="btn btn-soft icon-btn" href="' +
      esc(cfg.wwwroot + '/local/oksigeniatesape/board.php?id=' + cfg.courseid) + '" aria-label="' + esc(t('teach_board')) +
      '" title="' + esc(t('teach_board')) + '">' + T.icon('world') + '</a>' : '') +
      '<button class="btn btn-soft icon-btn" type="button" data-tab="settings" aria-label="' + esc(t('teach_tab_settings')) +
      '"' + (state.tab === 'settings' ? ' aria-current="page"' : '') + '>' + T.icon('settings') + '</button><a class="btn ' +
      'btn-soft exit" href="' + esc(cfg.courseurl) + '" aria-label="' + esc(t('app_exit')) + '">' + T.icon('exit') +
      '<span>' + esc(t('app_exit')) + '</span></a></div></header><nav class="tabs" ' +
      'aria-label="Tesapé">' + TABS.map(function(tab) {
        var count = tab === 'powers' ? d.powerspending : tab === 'challenges' ? d.pactspending : 0;
        var badge = count ? '<span class="badge">' + count + '</span>' : '';
        return '<button type="button" data-tab="' + tab + '"' + (state.tab === tab ? ' aria-current="page"' : '') + '>' +
          T.icon({class: 'team', powers: 'power', mission: 'world', challenges: 'challenges', log: 'log'}[tab]) + badge +
          '<span>' + esc(t('teach_tab_' + tab)) + '</span></button>';
      }).join('') + '</nav><main class="content" id="content"></main></div>';
    app.querySelectorAll('[data-tab]').forEach(function(b) {
      b.onclick = function() {
        state.tab = b.dataset.tab;
        state.open = 0;
        if (state.tab === 'powers') {
          state.powers = null;
        }
        if (state.tab === 'mission') {
          // The results change as the class plays: read them again on each visit.
          state.results = null;
        }
        if (state.tab === 'ideas') {
          state.ideas = null;
        }
        render();
        window.scrollTo(0, 0);
      };
    });
    ({class: tabClass, powers: tabPowers, mission: tabMission, challenges: tabChallenges, log: tabLog, settings: tabSettings,
      ideas: tabIdeas})[state.tab]();
  }

  function content(html) {
    var el = document.getElementById('content');
    el.innerHTML = html;
    return el;
  }

  function welcome() {
    var d = state.data;
    app.innerHTML = '<div class="sky"></div><div class="story">' + T.vignette('region') + '<h1>' + esc(t('teach_welcome')) +
      '</h1><p class="lead">' + esc(t('teach_welcome_help')) + '</p><h3>' + esc(t('teach_family')) + '</h3>' +
      '<div class="pills" style="margin-bottom:6px">' + families(d.region.family) + '</div><p class="help">' +
      esc(t('teach_family_help')) + '</p><div class="actions">' +
      (can('manageregion') ? '<button class="btn btn-main" data-on>' + esc(t('teach_turnon')) + '</button>' :
      '<p class="help">' + esc(t('teach_askeditor')) + '</p>') + '<a class="btn btn-soft" href="' + esc(cfg.courseurl) +
      '">' + esc(t('app_exit')) + '</a></div></div>';
    var family = d.region.family;
    bindFamilies(function(f) {
      family = f;
    });
    var on = app.querySelector('[data-on]');
    if (on) {
      on.onclick = function() {
        on.disabled = true;
        T.api('save_region', {courseid: cfg.courseid, enabled: true, family: family}).then(function() {
          T.toast(esc(t('teach_on')));
          load();
        }).catch(function(e) {
          on.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    }
  }

  var FAMILIES = ['animals', 'dinosaurs', 'storyheroes', 'epic', 'elements', 'nomagic'];

  function families(current) {
    return FAMILIES.map(function(f) {
      return '<button class="pill" type="button" data-family="' + f + '" aria-pressed="' + (current === f) + '">' +
        esc(t('teach_family_' + f)) + '<br><small style="color:var(--text-soft)">' +
        esc(T.ROLES.map(function(r) {
          return T.roleName(f, r, 'm');
        }).join(' · ')) + '</small></button>';
    }).join('');
  }

  function bindFamilies(onpick) {
    app.querySelectorAll('[data-family]').forEach(function(b) {
      b.onclick = function() {
        app.querySelectorAll('[data-family]').forEach(function(o) {
          o.setAttribute('aria-pressed', o === b);
        });
        onpick(b.dataset.family);
      };
    });
  }

  /* ---------- The class ---------- */

  function studentsShown() {
    return state.data.students.filter(function(s) {
      return !state.team || s.teamid === state.team;
    });
  }

  function selectedIds() {
    return Object.keys(state.selected).filter(function(k) {
      return state.selected[k];
    }).map(Number);
  }

  function badge(s) {
    var m = s.character && s.character.member;
    if (!s.character) {
      return '<span class="tag todo">' + esc(t('app_nocharacter')) + '</span>';
    }
    if (m && m.status === 'paused') {
      return '<span class="tag todo">' + esc(t('app_resting')) + '</span>';
    }
    if (m && m.status === 'fallen') {
      return '<span class="tag notyet">' + esc(t('app_wornout')) + '</span>';
    }
    return '';
  }

  /**
   * The round portrait of the character of a student and, at its foot, the photo, if the student has one.
   *
   * @param {Object} s
   * @return {string}
   */
  function faces(s) {
    var c = s.character;
    return '<span class="faces">' + (c ? '<span class="face">' + T.avatar({role: c.role, nameform: c.nameform,
      lineage: c.lineage, look: c.appearance, stage: c.stage, portrait: true}) + '</span>' : '') +
      (s.picture ? '<img class="photo" src="' + esc(s.picture) + '" alt="">' : '') + '</span>';
  }

  function studentCard(s) {
    var c = s.character;
    var m = c && c.member;
    var fam = state.data.region.family;
    var sel = !!state.selected[s.userid];
    return '<li class="item" style="cursor:pointer;' + (sel ? 'border-color:var(--lantern);background:rgba(255,200,87,.1)' :
      '') + '" data-student="' + s.userid + '" tabindex="0"><input type="checkbox" aria-label="' + esc(s.fullname) + '" ' +
      (sel ? 'checked' : '') + ' data-check="' + s.userid + '" style="width:22px;height:22px;flex:none">' +
      faces(s) + '<span class="grow"><b>' + esc(s.fullname) +
      '</b><small>' + (c ? esc(T.roleName(fam, c.role, c.nameform)) + ' · ' + esc(t('app_level')) + ' ' + c.level +
      (c.nickname ? ' · «' + esc(c.nickname) + '»' : '') : '') + '</small></span>' + badge(s) +
      (m ? '<span class="bars" aria-hidden="true"><span class="meter energy"><i style="width:' +
      Math.round(100 * m.energy / state.data.energymax) + '%"></i></span><span class="meter breath"><i style="width:' +
      Math.round(100 * m.breath / state.data.breathmax) + '%"></i></span></span>' : '') + '</li>';
  }

  /**
   * Today in the valley: what the mission proposes for the sessions of the chapter the class is in, and what is waiting
   * for the teacher.
   *
   * @return {string}
   */
  function todayCard() {
    var d = state.data;
    var today = d.today;
    if (!today) {
      return '';
    }
    var waiting = [];
    if (d.powerspending) {
      waiting.push(['powers', t('teach_today_powers', d.powerspending)]);
    }
    if (d.pactspending) {
      waiting.push(['challenges', t('teach_today_pacts', d.pactspending)]);
    }
    if (d.ideasunread) {
      waiting.push(['ideas', t('teach_today_ideas', d.ideasunread)]);
    }
    var sessions = today.sessions.map(function(x) {
      return '<li><b>' + esc(t('board_today_session', x.n)) + '</b>' + (x.minutes ? ' · ' + esc(t('app_minutes', x.minutes)) :
        '') + '<p>' + esc(x.do) + '</p>' + (x.materials ? '<small>' + esc(t('board_today_materials', x.materials)) +
        '</small>' : '') + (x.scene ? '<small>' + esc(t('teach_today_scene', x.scene)) + '</small>' : '') + '</li>';
    }).join('');
    return '<section class="card today-card" style="margin-bottom:16px"><div class="kicker">' + esc(t('board_today')) +
      '</div><h2>' + esc(today.chapter) + '</h2>' + (sessions ? '<ol class="today-sessions">' + sessions + '</ol>' : '') +
      (today.offscreen ? '<p class="help-soft">' + esc(t('board_today_offscreen', today.offscreen)) + '</p>' : '') +
      (waiting.length ? '<div class="chips">' + waiting.map(function(x) {
        return '<button type="button" class="chip" data-goto="' + x[0] + '">' + esc(x[1]) + '</button>';
      }).join('') + '</div>' : '') + '</section>';
  }

  /**
   * Own names the students wrote for their hero or their creature: nobody else sees them until a teacher takes them.
   *
   * @return {string}
   */
  function namesCard() {
    var d = state.data;
    if (!d.namespending || !d.namespending.length) {
      return '';
    }
    var names = {};
    d.students.forEach(function(s) {
      names[s.userid] = s.fullname;
    });
    var marks = can('award');
    return '<section class="card" style="margin-bottom:16px"><h2>' + esc(t('teach_names')) + '</h2><p style="color:' +
      'var(--text-soft)">' + esc(t('teach_names_help')) + '</p><ul class="list">' + d.namespending.map(function(x) {
        var key = x.userid + ':' + x.what;
        return '<li class="item"><span class="grow"><b>' + esc(x.name) + '</b><small>' + esc(names[x.userid] || '') + ' · ' +
          esc(t('teach_name_' + x.what)) + '</small></span>' + (marks ? '<span class="row-actions"><button class="chip" ' +
          'type="button" data-namedecide="' + key + ':1">' + esc(t('teach_name_ok')) + '</button><button class="chip" ' +
          'type="button" data-namedecide="' + key + ':0">' + esc(t('teach_name_no')) + '</button></span>' : '') + '</li>';
      }).join('') + '</ul></section>';
  }

  function tabClass() {
    var d = state.data;
    var teams = '<div class="pills" style="justify-content:flex-start;margin-bottom:12px"><button class="chip" ' +
      'data-team="0" aria-pressed="' + (!state.team) + '">' + esc(t('teach_everyone')) + '</button>' +
      d.teams.map(function(g) {
        return '<button class="chip" data-team="' + g.id + '" aria-pressed="' + (state.team === g.id) + '">' + esc(g.name) +
          '</button>';
      }).join('') + '</div>';
    var shown = studentsShown();
    var el = content(todayCard() + namesCard() + teamsCard() + '<section class="card"><div class="section-title"><h2>' +
      esc(t('teach_tab_class')) +
      '</h2><button class="btn btn-soft" data-all>' + esc(t('teach_selectall')) + '</button></div>' + teams +
      (shown.length ? '<ul class="list">' + shown.map(studentCard).join('') + '</ul>' : '<p class="empty">' +
      esc(t('teach_nostudents')) + '</p>') + '</section><div id="actionbar"></div>');
    bindMakeTeams(el);
    el.querySelectorAll('[data-goto]').forEach(function(b) {
      b.onclick = function() {
        state.tab = b.dataset.goto;
        state.open = 0;
        render();
      };
    });
    el.querySelectorAll('[data-namedecide]').forEach(function(b) {
      b.onclick = function() {
        var parts = b.dataset.namedecide.split(':');
        el.querySelectorAll('[data-namedecide]').forEach(function(o) {
          o.disabled = true;
        });
        T.api('decide_name', {courseid: cfg.courseid, userid: Number(parts[0]), what: parts[1],
          approve: parts[2] === '1'}).then(function() {
          return load(true);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          render();
        });
      };
    });
    el.querySelectorAll('[data-team]').forEach(function(b) {
      b.onclick = function() {
        state.team = Number(b.dataset.team);
        state.selected = {};
        render();
      };
    });
    el.querySelector('[data-all]').onclick = function() {
      var all = shown.every(function(s) {
        return state.selected[s.userid];
      });
      shown.forEach(function(s) {
        state.selected[s.userid] = !all;
      });
      render();
    };
    el.querySelectorAll('[data-check]').forEach(function(box) {
      box.onclick = function(ev) {
        ev.stopPropagation();
        state.selected[box.dataset.check] = box.checked;
        render();
      };
    });
    el.querySelectorAll('[data-student]').forEach(function(li) {
      li.onclick = function() {
        openStudent(Number(li.dataset.student));
      };
      li.onkeydown = function(ev) {
        if (ev.target === li && (ev.key === 'Enter' || ev.key === ' ')) {
          ev.preventDefault();
          openStudent(Number(li.dataset.student));
        }
      };
    });
    actionBar();
  }

  /**
   * The teams this week, or the way to make them in one go.
   *
   * @return {string}
   */
  function teamsCard() {
    var d = state.data;
    if (!d.standings.length) {
      return can('manageregion') && d.students.length ? '<section class="card" style="margin-bottom:16px"><h2>' +
        esc(t('teach_teams_none')) + '</h2><p style="color:var(--text-soft)">' + esc(t('teach_teams_help')) + '</p>' +
        makeTeamsForm() + '</section>' : '';
    }
    return '<section class="card" style="margin-bottom:16px"><div class="section-title"><h2>' + esc(t('teach_teams_week')) +
      '</h2>' + (d.region.leaderboard ? '' : '<span class="tag todo">' + esc(t('teach_teams_hidden')) + '</span>') +
      '</div><ul class="list">' + d.standings.map(function(x) {
        var pct = x.needed ? Math.min(100, Math.round(100 * x.done / x.needed)) : 0;
        return '<li class="item"><span class="grow"><b>' + esc(x.name) + '</b><span class="meter level"><i style="width:' +
          pct + '%"></i></span><small>' + esc(t('teach_teams_progress', {done: x.done, needed: x.needed, part: x.part,
          saber: x.saber, players: x.players})) + '</small></span>' +
          (x.reached ? '<span class="tag merit">' + esc(t('app_team_goal')) + '</span>' : '') + '</li>';
      }).join('') + '</ul></section>';
  }

  function makeTeamsForm() {
    return '<div class="actions" style="justify-content:flex-start;align-items:center;margin-top:10px"><label>' +
      esc(t('teach_teams_size')) + ' <select data-teamsize>' + [3, 4, 5, 6].map(function(n) {
        return '<option value="' + n + '"' + (n === 4 ? ' selected' : '') + '>' + n + '</option>';
      }).join('') + '</select></label><button class="btn btn-main" type="button" data-maketeams>' +
      esc(t('teach_teams_make')) + '</button></div>';
  }

  function bindMakeTeams(el) {
    var b = el.querySelector('[data-maketeams]');
    if (!b) {
      return;
    }
    b.onclick = function() {
      var size = Number(el.querySelector('[data-teamsize]').value);
      if (state.data.standings.length && !window.confirm(t('teach_teams_again'))) {
        return;
      }
      b.disabled = true;
      T.api('make_teams', {courseid: cfg.courseid, size: size}).then(function(r) {
        T.toast(esc(t('teach_teams_made', r.teams)));
        state.tab = 'class';
        load(true);
      }).catch(function(e) {
        b.disabled = false;
        T.toast(esc(e.message), true);
      });
    };
  }

  function actionBar() {
    var ids = selectedIds();
    var bar = document.getElementById('actionbar');
    if (!ids.length || !can('award')) {
      bar.innerHTML = '';
      return;
    }
    var amounts = [5, 10, 20];
    bar.innerHTML = '<section class="card no-print" style="position:sticky;bottom:84px;margin-top:14px;border-color:' +
      'var(--lantern)"><b>' + esc(t('teach_selected', ids.length)) + '</b><div class="pills" style="justify-content:' +
      'flex-start;margin-top:10px">' + amounts.map(function(a) {
        return '<button class="pill" data-award="saber" data-amount="' + a + '">+' + a + ' ' + esc(t('app_saber')) +
          '</button>';
      }).join('') + '<button class="pill" data-award="gems" data-amount="1">+1 ' + esc(t('app_gems')) + '</button>' +
      '<button class="pill" data-award="breath" data-amount="1">+1 ' + esc(t('app_breath')) + '</button>' +
      '<button class="pill" data-award="energy" data-amount="-1" title="' + esc(t('teach_commitment_help')) + '">−1 ' +
      esc(t('app_energy')) + '</button></div></section>';
    bar.querySelectorAll('[data-award]').forEach(function(b) {
      b.onclick = function() {
        // A second tap while the first is on its way would give it twice.
        bar.querySelectorAll('[data-award]').forEach(function(x) {
          x.disabled = true;
        });
        award(ids, b.dataset.award, Number(b.dataset.amount)).then(function() {
          bar.querySelectorAll('[data-award]').forEach(function(x) {
            x.disabled = false;
          });
        });
      };
    });
  }

  function award(ids, resource, amount) {
    return T.api('award', {courseid: cfg.courseid, userids: ids, resource: resource, amount: amount}).then(function(results) {
      var given = results.filter(function(r) {
        return r.status === 'given';
      }).length;
      var other = results.length - given;
      T.toast(esc(t('teach_given', given)) + (other ? ' · ' + esc(t('teach_notgiven', other)) : ''), given === 0);
      state.selected = {};
      load(true);
    }).catch(function(e) {
      T.toast(esc(e.message), true);
    });
  }

  /**
   * The teacher makes the character of a student who does not use a device: role, form of the names and, in the
   * families of heroes, lineage. The role suggested is the one the class has least of.
   *
   * @param {string} fam The family of names of the region.
   * @return {string}
   */
  function makeCharacter(fam) {
    var roles = ['protector', 'healer', 'sage', 'scout'];
    var count = {};
    state.data.students.forEach(function(x) {
      if (x.character) {
        count[x.character.role] = (count[x.character.role] || 0) + 1;
      }
    });
    var best = roles.slice().sort(function(a, b) {
      return (count[a] || 0) - (count[b] || 0);
    })[0];
    state.make = {role: best, nameform: 'f', lineage: T.hasLineages(fam) ? 'human' : ''};
    return '<div class="make-character" style="margin-top:16px"><h3>' + esc(t('teach_make_title')) + '</h3>' +
      '<div data-makeform></div><div class="actions" style="justify-content:flex-start"><button class="btn btn-main" ' +
      'data-make>' + esc(t('teach_make_go')) + '</button></div></div>';
  }

  function bindMakeCharacter(el, s) {
    var box = el.querySelector('[data-makeform]');
    if (!box) {
      return;
    }
    var fam = state.data.region.family;
    var chips = function(key, values, label) {
      return '<div class="pills" style="justify-content:flex-start;margin-bottom:10px">' + values.map(function(v) {
        return '<button class="chip" type="button" data-pick="' + key + ':' + v + '" aria-pressed="' +
          (state.make[key] === v) + '">' + esc(label(v)) + '</button>';
      }).join('') + '</div>';
    };
    var draw = function() {
      var m = state.make;
      box.innerHTML = chips('role', ['protector', 'healer', 'sage', 'scout'], function(v) {
        return T.roleName(fam, v, m.nameform);
      }) + chips('nameform', ['f', 'm'], function(v) {
        return t('teach_make_form_' + v, T.roleName(fam, m.role, v));
      }) + (m.lineage ? chips('lineage', ['human', 'elf', 'dwarf', 'fairy'], function(v) {
        return T.lineageName(v, m.nameform);
      }) : '');
      box.querySelectorAll('[data-pick]').forEach(function(b) {
        b.onclick = function() {
          var p = b.dataset.pick.split(':');
          state.make[p[0]] = p[1];
          draw();
        };
      });
    };
    draw();
    var go = el.querySelector('[data-make]');
    go.onclick = function() {
      go.disabled = true;
      T.api('make_character', {courseid: cfg.courseid, userid: s.userid, role: state.make.role,
        nameform: state.make.nameform, lineage: state.make.lineage}).then(function() {
        T.toast(esc(t('teach_make_done', s.firstname)));
        return load(true);
      }).then(function() {
        openStudent(s.userid);
      }).catch(function(e) {
        go.disabled = false;
        T.toast(esc(e.message), true);
      });
    };
  }

  function openStudent(userid) {
    var s = state.data.students.filter(function(x) {
      return x.userid === userid;
    })[0];
    var c = s.character;
    var m = c && c.member;
    var fam = state.data.region.family;
    var html = '<section class="card"><button class="btn btn-soft" data-back>← ' + esc(t('app_back')) + '</button>' +
      '<div class="sheet" style="margin-top:12px"><div style="text-align:center">' + (s.picture ? '<img src="' +
      esc(s.picture) + '" alt="" width="88" height="88" style="border-radius:50%;border:3px solid var(--lantern)">' : '') +
      (c ? T.avatar({
      role: c.role, nameform: c.nameform, lineage: c.lineage, look: c.appearance, stage: c.stage}) : '') + '</div>' +
      '<div class="stats"><h2>' + esc(s.fullname) + '</h2>';
    if (!c) {
      html += '<p>' + esc(t('teach_nocharacter_help')) + '</p></div></div>' + (can('award') ? makeCharacter(fam) : '') +
        '</section>';
      var el = content(html);
      el.querySelector('[data-back]').onclick = render;
      bindMakeCharacter(el, s);
      return;
    }
    html += '<p style="color:var(--text-soft)">' + esc(T.roleName(fam, c.role, c.nameform)) + ' ' +
      esc(T.hasLineages(fam) ? T.lineageName(c.lineage, c.nameform) : '') + (c.nickname ? ' · «' + esc(c.nickname) + '»' :
      '') + '</p>' + stat('app_level', c.level) + stat('app_saber', c.saber) + stat('app_renown', m ? m.renown : 0) +
      stat('app_gems', c.gems) + (m ? stat('app_energy', m.energy + ' / ' + state.data.energymax) +
      stat('app_breath', m.breath + ' / ' + state.data.breathmax) : '');
    if (m && m.status === 'paused') {
      html += '<div class="banner rest" style="margin-top:12px"><div><b>' + esc(t('app_resting')) + '</b>' +
        (m.statusreason ? '<div class="note">' + esc(m.statusreason) + '</div>' : '') + '</div></div>';
    }
    html += '<h3 style="margin-top:16px">' + esc(t('teach_step')) + '</h3><div class="pills" style="justify-content:' +
      'flex-start">' + [1, 2, 3, 4].map(function(st) {
        return '<button class="chip" data-step="' + st + '" aria-pressed="' + (m && m.step === st) + '"' +
          (can('manageregion') ? '' : ' disabled') + '>' + esc(t('teach_step_' + st)) + '</button>';
      }).join('') + '</div><div class="actions" style="justify-content:flex-start;margin-top:16px">';
    if (can('pause')) {
      if (m && m.status === 'paused') {
        html += '<button class="btn btn-soft" data-status="resume">' + esc(t('teach_resume')) + '</button>';
      } else {
        html += '<button class="btn btn-soft" data-status="pause">' + esc(t('teach_pause')) + '</button>';
      }
      if (m && m.status === 'fallen') {
        html += '<button class="btn btn-main" data-status="recover">' + esc(t('teach_recover')) + '</button>';
      }
    }
    if (can('viewlog')) {
      html += '<button class="btn btn-soft" data-studentlog>' + esc(t('teach_tab_log')) + '</button>';
    }
    html += '</div></div></div></section>';
    var el = content(html);
    el.querySelector('[data-back]').onclick = render;
    el.querySelectorAll('[data-step]').forEach(function(b) {
      b.onclick = function() {
        T.api('set_step', {courseid: cfg.courseid, userid: userid, step: Number(b.dataset.step)}).then(function() {
          T.toast(esc(t('app_saved')));
          load(true).then(function() {
            openStudent(userid);
          });
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
    el.querySelectorAll('[data-status]').forEach(function(b) {
      b.onclick = function() {
        var action = b.dataset.status;
        var reason = '';
        if (action === 'pause') {
          reason = window.prompt(t('teach_pause_reason'), '');
          if (reason === null) {
            return;
          }
        }
        T.api('set_status', {courseid: cfg.courseid, userid: userid, action: action, reason: reason}).then(function() {
          load(true).then(function() {
            openStudent(userid);
          });
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
    var logbtn = el.querySelector('[data-studentlog]');
    if (logbtn) {
      logbtn.onclick = function() {
        state.tab = 'log';
        state.logUser = userid;
        state.log = null;
        render();
      };
    }
  }

  function stat(key, value) {
    return '<div class="stat"><span>' + esc(t(key)) + '</span><b>' + esc(value) + '</b></div>';
  }

  /* ---------- Powers ---------- */

  var ROLES = ['protector', 'healer', 'sage', 'scout', 'all'];

  function tabPowers() {
    if (!state.powers) {
      content('<section class="card"><p class="empty">' + esc(t('app_loading')) + '</p></section>');
      T.api('get_powers', {courseid: cfg.courseid}).then(function(r) {
        state.powers = r;
        if (state.tab === 'powers') {
          tabPowers();
        }
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
      return;
    }
    var r = state.powers;
    var fam = state.data.region.family;
    var request = function(q, pending) {
      var note = q.code === 'revive' && pending ? '<small class="note">' + esc(t('teach_power_revive')) + '</small>' : '';
      return '<li class="item" style="flex-wrap:wrap"><span class="mini-icon">' + T.icon('power') + '</span><span class="grow">' +
        '<b>' + esc(q.fullname) + '</b><small>' + esc(q.power) + (q.target ? ' → ' + esc(q.target) : '') + ' · ' +
        esc(t('teach_power_cost', q.cost)) + ' · ' + esc(when(q.time)) + '</small>' + note +
        '</span>' + (pending ? '<span class="pills"><button class="btn btn-main" data-decide="' + q.id + '" data-yes="1">' +
        esc(t('teach_power_yes')) + '</button><button class="btn btn-soft" data-decide="' + q.id + '" data-yes="0">' +
        esc(t('teach_power_no')) + '</button></span>' : '<span class="tag ' + (q.status === 'rejected' ? 'notyet' : 'done') +
        '">' + esc(t('app_power_' + q.status)) + '</span>' + (q.status === 'approved' || q.status === 'used' ?
        '<button class="btn btn-soft board-small" data-decide="' + q.id + '" data-yes="0">' + esc(t('teach_power_withdraw')) +
        '</button>' : '')) + '</li>';
    };
    var catalog = ROLES.map(function(role) {
      var list = r.powers.filter(function(p) {
        return p.role === role;
      });
      if (!list.length) {
        return '';
      }
      return '<h3 style="margin-top:14px">' + esc(role === 'all' ? t('teach_power_all') : T.roleName(fam, role, 'm')) +
        '</h3><ul class="list">' + list.map(function(p) {
          return '<li class="item power-row' + (p.enabled ? '' : ' off') + '" style="flex-wrap:wrap"><span class="grow"><b>' +
            esc(p.name) + '</b><small>' + esc(p.description) + '</small></span><label class="mini-field">' +
            esc(t('app_breath')) + ' <input type="number" min="0" max="5" value="' + p.cost + '" data-cost="' + p.id + '"' +
            (can('manageregion') ? '' : ' disabled') + '></label><label class="mini-field">' + esc(t('app_level')) +
            ' <input type="number" min="1" max="50" value="' + p.minlevel + '" data-level="' + p.id + '"' +
            (can('manageregion') ? '' : ' disabled') + '></label><label class="chip"><input type="checkbox" data-on="' + p.id +
            '"' + (p.enabled ? ' checked' : '') + (can('manageregion') ? '' : ' disabled') + '> ' + esc(t('teach_power_on')) +
            '</label><label class="chip"><input type="checkbox" data-auto="' + p.id + '"' + (p.autoapprove ? ' checked' : '') +
            (can('manageregion') ? '' : ' disabled') + '> ' + esc(t('teach_power_auto')) + '</label></li>';
        }).join('') + '</ul>';
    }).join('');
    var form = state.newpower ? '<form data-newpower class="card" style="margin-top:12px">' + field('name',
      t('teach_power_name'), '<input name="name" required maxlength="255" placeholder="' + esc(t('teach_power_example')) +
      '">') + field('description', t('teach_ch_description'), '<textarea name="description" rows="2"></textarea>') +
      '<div class="grid2" style="gap:10px">' + field('role', t('teach_power_for'), '<select name="role">' +
      ROLES.map(function(role) {
        return '<option value="' + role + '"' + (role === 'all' ? ' selected' : '') + '>' + esc(role === 'all' ?
          t('teach_power_all') : T.roleName(fam, role, 'm')) + '</option>';
      }).join('') + '</select>') + field('cost', t('app_breath'), '<input name="cost" type="number" min="0" max="5" ' +
      'value="2">') + field('minlevel', t('app_level'), '<input name="minlevel" type="number" min="1" max="50" value="1">') +
      '</div><div class="actions" style="justify-content:flex-start"><button class="btn btn-main" type="submit">' +
      esc(t('app_save')) + '</button></div></form>' : '';
    var el = content('<section class="card"><h2>' + esc(t('teach_power_pending')) + '</h2>' + (r.pending.length ?
      '<ul class="list">' + r.pending.map(function(q) {
        return request(q, true);
      }).join('') + '</ul>' : '<p class="empty">' + esc(t('teach_power_nopending')) + '</p>') + '</section>' +
      (r.decided.length ? '<section class="card" style="margin-top:16px"><h2>' + esc(t('teach_power_decided')) + '</h2>' +
      '<ul class="list">' + r.decided.map(function(q) {
        return request(q, false);
      }).join('') + '</ul></section>' : '') + '<section class="card" style="margin-top:16px"><div class="section-title"><h2>' +
      esc(t('teach_power_catalog')) + '</h2>' + (can('manageregion') ? '<button class="btn btn-soft" data-newpowerbtn>' +
      esc(t('teach_power_new')) + '</button>' : '') + '</div><p style="color:var(--text-soft)">' +
      esc(t('teach_power_help')) + '</p>' + form + catalog + '</section>');
    el.querySelectorAll('[data-decide]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        T.api('decide_power', {courseid: cfg.courseid, requestid: Number(b.dataset.decide), approve: b.dataset.yes === '1'})
          .then(function(res) {
            state.powers = res;
            state.data.powerspending = res.pending.length;
            render();
          }).catch(function(e) {
            b.disabled = false;
            T.toast(esc(e.message), true);
          });
      };
    });
    var save = function(id, data) {
      T.api('save_power', Object.assign({courseid: cfg.courseid, id: id}, data)).then(function(res) {
        state.powers = res;
        T.toast(esc(t('app_saved')));
      }).catch(function(e) {
        T.toast(esc(e.message), true);
        tabPowers();
      });
    };
    var power = function(id) {
      return r.powers.filter(function(p) {
        return p.id === id;
      })[0];
    };
    var current = function(id) {
      var p = power(id);
      return {name: p.own ? p.name : '', description: p.own ? p.description : '', cost: p.cost, minlevel: p.minlevel,
        enabled: p.enabled, autoapprove: p.autoapprove};
    };
    el.querySelectorAll('[data-cost]').forEach(function(i) {
      i.onchange = function() {
        save(Number(i.dataset.cost), Object.assign(current(Number(i.dataset.cost)), {cost: Number(i.value)}));
      };
    });
    el.querySelectorAll('[data-level]').forEach(function(i) {
      i.onchange = function() {
        save(Number(i.dataset.level), Object.assign(current(Number(i.dataset.level)), {minlevel: Number(i.value)}));
      };
    });
    el.querySelectorAll('[data-on]').forEach(function(i) {
      i.onchange = function() {
        save(Number(i.dataset.on), Object.assign(current(Number(i.dataset.on)), {enabled: i.checked}));
      };
    });
    el.querySelectorAll('[data-auto]').forEach(function(i) {
      i.onchange = function() {
        save(Number(i.dataset.auto), Object.assign(current(Number(i.dataset.auto)), {autoapprove: i.checked}));
      };
    });
    var add = el.querySelector('[data-newpowerbtn]');
    if (add) {
      add.onclick = function() {
        state.newpower = !state.newpower;
        tabPowers();
      };
    }
    var f = el.querySelector('form[data-newpower]');
    if (f) {
      f.onsubmit = function(ev) {
        ev.preventDefault();
        T.api('save_power', {courseid: cfg.courseid, id: 0, role: f.role.value, name: f.name.value,
          description: f.description.value, cost: Number(f.cost.value), minlevel: Number(f.minlevel.value)})
          .then(function(res) {
            state.powers = res;
            state.newpower = false;
            T.toast(esc(t('app_saved')));
            tabPowers();
          }).catch(function(e) {
            T.toast(esc(e.message), true);
          });
      };
    }
  }

  /* ---------- The box of ideas ---------- */

  function tabIdeas() {
    if (!state.ideas) {
      content('<section class="card"><p class="empty">' + esc(t('app_loading')) + '</p></section>');
      T.api('get_ideas', {courseid: cfg.courseid}).then(function(list) {
        state.ideas = list;
        if (state.tab === 'ideas') {
          tabIdeas();
        }
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
      return;
    }
    var el = content('<section class="card"><h2>' + esc(t('teach_tab_ideas')) + '</h2><p style="color:var(--text-soft)">' +
      esc(t('teach_ideas_help')) + '</p>' + (state.ideas.length ? '<ul class="list">' + state.ideas.map(function(i) {
        return '<li class="item idea' + (i.status === 'new' ? ' fresh' : '') + '">' +
          '<span class="grow"><b>' + esc(i.fullname || t('teach_ideas_anonymous')) + '</b><small>' + esc(when(i.time)) +
          '</small><span class="idea-text">' + esc(i.message) + '</span></span><span class="row-actions">' +
          (i.status === 'new' ? '<button class="chip" data-idea="' + i.id + '" data-action="read">' +
          esc(t('teach_ideas_read')) + '</button>' : '<span class="tag done">' + esc(t('app_ideas_read')) + '</span>') +
          '<button class="chip" data-idea="' + i.id + '" data-action="delete">' + esc(t('teach_delete')) + '</button>' +
          '</span></li>';
      }).join('') + '</ul>' : '<p class="empty">' + esc(t('teach_ideas_none')) + '</p>') + '</section>');
    el.querySelectorAll('[data-idea]').forEach(function(b) {
      b.onclick = function() {
        if (b.dataset.action === 'delete' && !window.confirm(t('teach_ideas_delete'))) {
          return;
        }
        T.api('handle_idea', {courseid: cfg.courseid, ideaid: Number(b.dataset.idea), action: b.dataset.action})
          .then(function(list) {
            state.ideas = list;
            state.data.ideasunread = list.filter(function(i) {
              return i.status === 'new';
            }).length;
            render();
          }).catch(function(e) {
            T.toast(esc(e.message), true);
          });
      };
    });
  }

  /* ---------- The mission ---------- */

  function tabMission() {
    if (!state.missions || !state.results || !state.nextclass) {
      content('<section class="card"><p class="empty">' + esc(t('app_loading')) + '</p></section>');
      Promise.all([
        T.api('get_missions', {courseid: cfg.courseid}),
        can('viewlog') ? T.api('get_results', {courseid: cfg.courseid}) : Promise.resolve({chapters: []}),
        can('viewlog') ? T.api('next_class', {courseid: cfg.courseid, action: 'state'}).catch(function() {
          return {ai: false};
        }) : Promise.resolve({ai: false})
      ]).then(function(r) {
        state.missions = r[0];
        state.results = r[1];
        state.nextclass = r[2];
        if (state.tab === 'mission') {
          tabMission();
        }
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
      return;
    }
    var m = state.missions;
    var html = '';
    if (!m.installed) {
      html = '<section class="card"><h2>' + esc(t('teach_tab_mission')) + '</h2><p style="color:var(--text-soft)">' +
        esc(t('teach_mission_none')) + '</p></section>' + m.packages.map(packageCard).join('');
    } else {
      var pkg = m.packages.filter(function(p) {
        return p.code === m.installed.code;
      })[0];
      // Other missions start a new expedition; the current one stays lit as a past one.
      var others = m.packages.filter(function(p) {
        return p.code !== m.installed.code && !m.expeditions.some(function(x) {
          return x.code === p.code;
        });
      });
      html = '<section class="card"><div class="kicker">' + esc(t('teach_mission_installed')) + '</div><h2>' +
        esc(m.installed.title) + '</h2>' + (pkg ? '<p style="color:var(--text-soft)">' + esc(pkg.subtitle) + ' · ' +
        esc(t('teach_mission_ages', pkg.ages)) + '</p>' + (can('manageregion') ? packageForm(pkg, m.installed) : '') : '') +
        '</section>' + reportCard() + nextClass() + games() + results() + expeditionsCard(m.expeditions) +
        (others.length && can('manageregion') ? '<section class="card" style="margin-top:16px"><h2>' +
        esc(t('teach_expedition_new')) + '</h2><p style="color:var(--text-soft)">' + esc(t('teach_expedition_new_help')) +
        '</p></section>' + others.map(packageCard).join('') : '');
    }
    var el = content(html);
    bindNextClass(el);
    el.querySelectorAll('form[data-install]').forEach(function(f) {
      f.onsubmit = function(ev) {
        ev.preventDefault();
        var skin = f.querySelector('[name="skin"]:checked');
        var variant = f.querySelector('[name="variant"]:checked');
        var acts = f.querySelector('[name="activities"]');
        T.api('install_mission', {courseid: cfg.courseid, code: f.dataset.install, skin: skin.value,
          variant: variant.value, activities: !!(acts && acts.checked)}).then(function(missions) {
          state.missions = missions;
          state.results = null;
          T.toast(esc(t('app_saved')));
          tabMission();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
  }

  /**
   * The report of the class, to take to a spreadsheet, a gradebook or a PDF: one row for each student.
   *
   * @return {string}
   */
  function reportCard() {
    if (!can('viewlog')) {
      return '';
    }
    var link = function(format, label) {
      return '<a class="btn btn-soft" href="' + esc(cfg.wwwroot + '/local/oksigeniatesape/download.php?id=' + cfg.courseid +
        '&format=' + format + '&sesskey=' + cfg.sesskey) + '">' + esc(label) + '</a>';
    };
    return '<section class="card" style="margin-top:16px"><h2>' + esc(t('teach_report')) + '</h2><p style="color:' +
      'var(--text-soft)">' + esc(t('teach_report_help')) + '</p><div class="actions" style="justify-content:flex-start">' +
      link('excel', 'Excel') + link('ods', 'ODS') + link('csv', 'CSV') + link('pdf', 'PDF') + '</div></section>';
  }

  /**
   * The expeditions the class made before the current one, with the lanterns it lit.
   *
   * @param {Array} list
   * @return {string}
   */
  function expeditionsCard(list) {
    if (!list || !list.length) {
      return '';
    }
    return '<section class="card" style="margin-top:16px"><h2>' + esc(t('app_expeditions')) + '</h2><ul class="list">' +
      list.map(function(x) {
        return '<li class="item"><span class="grow"><b>' + esc(x.title) + '</b><small>' + esc(x.subtitle) + '</small></span>' +
          '<span class="tag done">' + esc(t('app_expedition_lit', {lit: x.lit, total: x.total})) + '</span></li>';
      }).join('') + '</ul></section>';
  }

  function packageForm(p, installed) {
    var radios = function(name, list, current) {
      return '<div class="pills" style="justify-content:flex-start;margin-bottom:10px">' + list.map(function(o, i) {
        var on = current ? current === o.code : i === 0;
        return '<label class="chip" style="display:inline-flex;gap:8px;align-items:center"><input type="radio" name="' + name +
          '" value="' + esc(o.code) + '"' + (on ? ' checked' : '') + '> ' + esc(o.name) + '</label>';
      }).join('') + '</div>';
    };
    var acts = !installed || installed.activitiesmissing;
    return '<form data-install="' + esc(p.code) + '" style="margin-top:12px"><h3>' + esc(t('teach_mission_skin')) + '</h3>' +
      radios('skin', p.skins, installed && installed.skin) + '<h3>' + esc(t('teach_mission_variant')) + '</h3>' +
      radios('variant', p.variants, installed && installed.variant) + (acts ? '<label class="chip" style="display:flex;' +
      'gap:8px;align-items:flex-start;margin:6px 0 4px"><input type="checkbox" name="activities"' + (installed ? '' :
      ' checked') + '> <span>' + esc(installed ? t('teach_mission_missing') : t('teach_mission_activities')) + '</span>' +
      '</label>' : '') + '<div class="actions" style="justify-content:' +
      'flex-start"><button class="btn ' + (installed ? 'btn-soft' : 'btn-main') + '" type="submit">' +
      esc(installed ? t('teach_mission_change') : t('teach_mission_install')) + '</button></div></form>';
  }

  function packageCard(p) {
    return '<section class="card" style="margin-top:16px"><div class="kicker">' + esc(t('teach_mission_ages', p.ages)) +
      '</div><h2>' + esc(p.title) + '</h2><p>' + esc(p.subtitle) + '</p><p style="color:var(--text-soft)">' +
      esc(t('teach_mission_size', p.chapters + ' · ' + p.scenes)) + (p.lang !== cfg.lang.slice(0, 2) ? ' · ' +
      esc(t('teach_mission_lang', p.lang)) : '') + '</p>' + (can('manageregion') ? packageForm(p, null) : '') + '</section>';
  }

  /**
   * The plan for the next class, made by the AI of the site from what confuses the class.
   *
   * @return {string}
   */
  function nextClass() {
    var n = state.nextclass;
    if (!n || !n.ai || !state.results || !state.results.chapters.length) {
      return '';
    }
    var head = '<section class="card next-class" style="margin-top:16px"><div class="kicker">' + esc(t('teach_next')) +
      '</div><h2>' + esc(t('teach_next_title')) + '</h2>';
    if (!n.policy) {
      // The policy of the site is a text of Moodle itself, with its own HTML.
      return head + '<div class="ai-policy">' + n.policytext + '</div><div class="actions"><button class="btn btn-main" ' +
        'data-aiaccept>' + esc(t('teach_next_accept')) + '</button></div></section>';
    }
    var p = n.plan;
    var plan = p ? '<p class="lead">' + esc(p.summary) + '</p><h3>' + esc(t('teach_next_review')) + '</h3><ol>' +
      p.review.map(function(r) {
        return '<li><b>' + esc(r.point) + '</b>' + (r.how ? '<br>' + esc(r.how) : '') + '</li>';
      }).join('') + '</ol>' + (p.example ? '<h3>' + esc(t('teach_next_example')) + '</h3><p>' + esc(p.example) + '</p>' :
      '') + '<h3>' + esc(t('teach_next_opener')) + '</h3><p class="next-opener">' + esc(p.opener) + '</p>' +
      '<small class="help-soft">' + esc(t('teach_next_onboard')) + '</small>' + (p.challenge ? '<h3>' +
      esc(t('teach_next_challenge')) + '</h3><p><b>' + esc(p.challengename) + '</b>' + (p.challengewhy ? '<br>' +
      esc(p.challengewhy) : '') + '</p>' : '') + '<small class="help-soft" style="display:block;margin-top:10px">' +
      esc(t('teach_next_when', when(p.time))) + '</small>' : '<p style="color:var(--text-soft)">' +
      esc(t('teach_next_help')) + '</p>';
    return head + plan + '<div class="actions" style="justify-content:flex-start"><button class="btn ' +
      (p ? 'btn-soft' : 'btn-main') + '" data-nextmake' + (state.nextbusy ? ' disabled' : '') + '>' +
      esc(state.nextbusy ? t('teach_next_busy') : t(p ? 'teach_next_again' : 'teach_next_make')) + '</button></div>' +
      '<small class="help-soft">' + esc(t('teach_next_privacy')) + '</small></section>';
  }

  /**
   * The buttons of the plan for the next class.
   *
   * @param {Element} el
   */
  function bindNextClass(el) {
    var call = function(action) {
      return T.api('next_class', {courseid: cfg.courseid, action: action}).then(function(r) {
        state.nextclass = r;
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      }).then(function() {
        state.nextbusy = false;
        if (state.tab === 'mission') {
          tabMission();
        }
      });
    };
    var accept = el.querySelector('[data-aiaccept]');
    if (accept) {
      accept.onclick = function() {
        call('accept');
      };
    }
    var make = el.querySelector('[data-nextmake]');
    if (make) {
      make.onclick = function() {
        state.nextbusy = true;
        tabMission();
        call('generate');
      };
    }
  }

  function games() {
    var r = state.results;
    if (!r || !r.games || !r.games.length) {
      return '';
    }
    return '<section class="card" style="margin-top:16px"><h2>' + esc(t('teach_games')) + '</h2>' + r.games.map(function(g) {
      return '<div class="item" style="display:block"><b>' + esc(g.title) + '</b><small style="display:block;color:' +
        'var(--text-soft)">' + esc(t('teach_game_stats', g.players + ' · ' + g.won + ' · ' + g.tries)) + '</small>' +
        (g.confusions.length ? '<div class="note"><b>' + esc(t('teach_confusions')) + '</b><br>' + g.confusions.map(
          function(c) {
            return esc(c.text) + ' (' + c.count + ')';
          }).join('<br>') + '</div>' : '') + '</div>';
    }).join('') + '</section>';
  }

  function results() {
    var r = state.results;
    if (!r || !r.chapters.length) {
      return '';
    }
    return '<section class="card" style="margin-top:16px"><h2>' + esc(t('teach_results')) + '</h2><p style="color:' +
      'var(--text-soft)">' + esc(t('teach_results_help')) + '</p>' + r.chapters.map(function(ch) {
        return '<div class="result-chapter"><div class="section-title"><h3>' + esc(ch.name) + '</h3><span class="tag ' +
          (ch.lit ? 'merit' : 'todo') + '">' + esc(ch.lit ? t('teach_lantern_lit') : t('teach_lantern_off')) + '</span>' +
          '</div><p style="color:var(--text-soft);margin-top:-6px">' + esc(t('teach_chapter_done', ch.done + '/' +
          r.players)) + '</p>' + ch.scenes.map(function(sc) {
            var objectives = sc.objectives.map(function(o) {
              var pct = o.total ? Math.round(100 * o.ok / o.total) : 0;
              return '<div class="objective"><span><b>' + esc(o.code) + '</b> ' + esc(o.name) + '</span><span class="meter ' +
                'level"><i style="width:' + pct + '%"></i></span><b>' + pct + '%</b></div>';
            }).join('');
            var confusions = sc.confusions.length ? '<div class="note"><b>' + esc(t('teach_confusions')) + '</b><br>' +
              sc.confusions.map(function(c) {
                return (c.question ? '<small>' + esc(c.question) + '</small><br>' : '') + esc(c.text) + ' (' + c.count + ')';
              }).join('<br>') + '</div>' : '';
            return '<div class="item" style="display:block"><b>' + esc(sc.title) + '</b><small style="display:block;color:' +
              'var(--text-soft)">' + esc(t('teach_scene_stats', sc.players + ' · ' + sc.done + ' · ' + sc.attempts)) +
              '</small>' + (sc.attempts ? objectives + confusions : '<small style="display:block;color:var(--text-soft)">' +
              esc(t('teach_results_none')) + '</small>') + '</div>';
          }).join('') + '</div>';
      }).join('') + '</section>';
  }

  /* ---------- Classroom challenges ---------- */

  function tabChallenges() {
    var d = state.data;
    if (state.challenge) {
      validationView(state.challenge);
      return;
    }
    var list = d.challenges.map(function(ch) {
      var done = ch.validations.filter(function(v) {
        return v.status !== 'notyet';
      }).length;
      return '<li class="item" data-open="' + ch.id + '" style="cursor:pointer" tabindex="0" role="button"><span class="grow">' +
        '<b>' + esc(ch.name) +
        '</b><small>' + rewards(ch) + (ch.teamwork ? ' · ' + esc(t('teach_teamwork')) : '') + '</small></span>' +
        '<span class="tag done">' + done + ' / ' + d.students.length + '</span></li>';
    }).join('');
    var acts = d.activities.map(function(a) {
      return '<li class="item act"><span class="mini-icon"><img src="' + esc(a.icon) + '" alt="" ' +
        'width="24" height="24"></span><span class="grow"><b>' + esc(a.name) + '</b><small>' + rewards(a) +
        (a.teamwork ? ' · ' + esc(t('teach_teamwork')) : '') + (a.tracked ? '' : ' · ' + esc(t('teach_act_notracking'))) +
        '</small></span><span class="row-actions"><span class="tag done" title="' + esc(t('teach_act_done')) + '">' +
        a.done + ' / ' + d.students.length + '</span>' + (can('manageregion') ? '<a class="chip" href="' + esc(a.url) + '">' +
        esc(t('teach_edit')) + '</a>' : '') + '</span></li>';
    }).join('');
    var el = content(pactsSection() + '<section class="card"><div class="section-title"><h2>' +
      esc(t('teach_tab_challenges')) + '</h2>' +
      (can('manageregion') ? '<button class="btn btn-main" data-new>' + esc(t('teach_newchallenge')) + '</button>' : '') +
      '</div><p style="color:var(--text-soft)">' + esc(t('teach_challenges_help')) + '</p>' + (list ? '<ul class="list">' +
      list + '</ul>' : '<p class="empty">' + esc(t('app_nochallenges')) + '</p>') + '</section><div id="form"></div>' +
      '<section class="card" style="margin-top:16px"><h2>' + esc(t('teach_act_title')) + '</h2><p style="color:' +
      'var(--text-soft)">' + esc(t('teach_act_help')) + '</p>' + (acts ? '<ul class="list">' + acts + '</ul>' :
      '<p class="empty">' + esc(t('teach_act_none')) + '</p>') + '</section>');
    el.querySelectorAll('[data-open]').forEach(function(li) {
      li.onclick = function() {
        state.challenge = Number(li.dataset.open);
        render();
      };
      li.onkeydown = function(ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          li.onclick();
        }
      };
    });
    var add = el.querySelector('[data-new]');
    if (add) {
      add.onclick = function() {
        challengeForm(null);
      };
    }
    el.querySelectorAll('[data-pactmark]').forEach(function(b) {
      b.onclick = function() {
        var parts = b.dataset.pactmark.split(':');
        // Tapping the mark it has takes it back to pending.
        var status = b.getAttribute('aria-pressed') === 'true' ? 'open' : parts[1];
        el.querySelectorAll('[data-pactmark]').forEach(function(o) {
          o.disabled = true;
        });
        T.api('decide_pact', {courseid: cfg.courseid, pactid: Number(parts[0]), status: status}).then(function() {
          return load(true);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          render();
        });
      };
    });
  }

  /**
   * The commitments of the teams in the chapter the class is in (and those still pending from earlier ones), to mark
   * kept or not kept.
   *
   * @return {string}
   */
  function pactsSection() {
    var d = state.data;
    var p = d.pacts;
    if (!p || !p.options) {
      return '';
    }
    var names = {};
    d.students.forEach(function(s) {
      names[s.userid] = s.fullname;
    });
    var chose = {};
    p.list.forEach(function(x) {
      if (!x.chapter) {
        chose[x.userid] = true;
      }
    });
    var teams = d.teams.map(function(g) {
      var mine = p.list.filter(function(x) {
        return x.teamid === g.id;
      });
      var missing = d.students.filter(function(s) {
        return s.teamid === g.id && s.character && !chose[s.userid];
      }).map(function(s) {
        return s.firstname;
      });
      if (!mine.length && !missing.length) {
        return '';
      }
      var marks = can('award');
      return '<h3 class="pact-team">' + esc(g.name) + '</h3><ul class="list">' + mine.map(function(x) {
        return '<li class="item"><span class="grow"><b>' + esc(names[x.userid] || '') + '</b><small>' + esc(x.text) +
          (x.chapter ? ' · ' + esc(t('teach_pact_from', x.chapter)) : '') + '</small></span>' + (marks ?
          '<span class="row-actions"><button class="chip" type="button" data-pactmark="' + x.id + ':kept" aria-pressed="' +
          (x.status === 'kept') + '">' + esc(t('teach_pact_kept')) + '</button><button class="chip" type="button" ' +
          'data-pactmark="' + x.id + ':broken" aria-pressed="' + (x.status === 'broken') + '">' +
          esc(t('teach_pact_broken')) + '</button></span>' : '') + '</li>';
      }).join('') + '</ul>' + (missing.length ? '<p class="help-soft">' + esc(t('teach_pacts_missing', missing.join(', '))) +
        '</p>' : '');
    }).join('');
    return '<section class="card pacts-teach" style="margin-bottom:16px"><h2>' + esc(t('teach_pacts')) + '</h2><p ' +
      'style="color:var(--text-soft)">' + esc(t('teach_pacts_help', {breath: p.breath, loss: p.loss})) + '</p><p><b>' +
      esc(t('teach_pacts_chapter', p.chapter)) + '</b></p>' + (teams || '<p class="empty">' + esc(t('teach_pacts_none')) +
      '</p>') + '</section>';
  }

  function rewards(ch) {
    var r = [];
    if (ch.saber) {
      r.push('+' + ch.saber + ' ' + t('app_saber'));
    }
    if (ch.gems) {
      r.push('+' + ch.gems + ' ' + t('app_gems'));
    }
    return esc(r.join(' · '));
  }

  function challengeForm(ch) {
    var c = ch || {id: 0, name: '', description: '', saber: 20, gems: 0, steps: '1,2,3,4', teamwork: false};
    var steps = String(c.steps).split(',');
    var form = document.getElementById('form');
    form.innerHTML = '<section class="card" style="margin-top:16px"><h3>' + esc(ch ? t('teach_editchallenge') :
      t('teach_newchallenge')) + '</h3><form data-challenge>' + field('name', t('teach_ch_name'),
      '<input name="name" required maxlength="255" value="' + esc(c.name) + '" placeholder="' + esc(t('teach_ch_example')) +
      '">') + field('description', t('teach_ch_description'), '<textarea name="description" rows="3">' +
      esc(c.description) + '</textarea>') + '<div class="grid2" style="gap:10px">' + field('saber', t('app_saber'),
      '<input name="saber" type="number" min="0" max="200" value="' + c.saber + '">') + field('gems', t('app_gems'),
      '<input name="gems" type="number" min="0" max="50" value="' + c.gems + '">') + '</div>' + field('steps',
      t('teach_step'), '<div class="pills" style="justify-content:flex-start">' + [1, 2, 3, 4].map(function(st) {
        return '<label class="chip"><input type="checkbox" name="step" value="' + st + '"' +
          (steps.indexOf(String(st)) >= 0 ? ' checked' : '') + '> ' + esc(t('teach_step_' + st)) + '</label>';
      }).join('') + '</div>') + '<label class="chip" style="display:inline-flex;gap:8px;align-items:center"><input ' +
      'type="checkbox" name="teamwork"' + (c.teamwork ? ' checked' : '') + '> ' + esc(t('teach_teamwork')) + '</label>' +
      '<div class="actions" style="justify-content:flex-start;margin-top:14px"><button class="btn btn-main" type="submit">' +
      esc(t('app_save')) + '</button>' + (ch && can('manageregion') ? '<button class="btn btn-soft" type="button" ' +
      'data-delete>' + esc(t('teach_delete')) + '</button>' : '') + '</div></form></section>';
    var f = form.querySelector('form');
    f.onsubmit = function(ev) {
      ev.preventDefault();
      var picked = Array.prototype.map.call(f.querySelectorAll('[name="step"]:checked'), function(i) {
        return i.value;
      }).join(',');
      T.api('save_challenge', {courseid: cfg.courseid, id: c.id, name: f.name.value, description: f.description.value,
        saber: Number(f.saber.value) || 0, gems: Number(f.gems.value) || 0, steps: picked || '1,2,3,4',
        teamwork: f.teamwork.checked}).then(function(r) {
        T.toast(esc(t('app_saved')));
        state.challenge = r.id;
        load(true);
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
    var del = form.querySelector('[data-delete]');
    if (del) {
      del.onclick = function() {
        if (!window.confirm(t('teach_delete_confirm'))) {
          return;
        }
        T.api('delete_challenge', {courseid: cfg.courseid, id: c.id}).then(function() {
          state.challenge = 0;
          load(true);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    }
    form.scrollIntoView({behavior: 'smooth'});
  }

  function field(name, label, input) {
    return '<label style="display:block;margin-bottom:12px"><span style="display:block;font-size:14px;color:' +
      'var(--text-soft);margin-bottom:4px">' + esc(label) + '</span>' + input + '</label>';
  }

  var STATUSES = ['done', 'merit', 'notyet'];

  function validationView(id) {
    var d = state.data;
    var ch = d.challenges.filter(function(x) {
      return x.id === id;
    })[0];
    if (!ch) {
      state.challenge = 0;
      render();
      return;
    }
    var word = {};
    ch.validations.forEach(function(v) {
      word[v.userid] = v;
    });
    var rows = function(list) {
      return list.map(function(s) {
        var v = word[s.userid];
        return '<li class="item" style="flex-wrap:wrap">' + faces(s) + '<span class="grow"><b>' + esc(s.fullname) +
          '</b>' + (v && v.note ? '<small>' +
          esc(v.note) + '</small>' : '') + '</span><span class="pills">' + STATUSES.map(function(st) {
            return '<button class="chip" data-val="' + st + '" data-user="' + s.userid + '" aria-pressed="' +
              (v ? v.status === st : false) + '"' + (s.character ? '' : ' disabled') + '>' + esc(t('teach_short_' + st)) +
              '</button>';
          }).join('') + '<button class="chip" data-note="' + s.userid + '"' + (s.character ? '' : ' disabled') +
          ' aria-label="' + esc(t('teach_note')) + '">✎</button></span></li>';
      }).join('');
    };
    var body = '';
    if (d.teams.length) {
      body = d.teams.map(function(g) {
        var list = d.students.filter(function(s) {
          return s.teamid === g.id;
        });
        if (!list.length) {
          return '';
        }
        return '<div class="section-title" style="flex-wrap:wrap"><h3>' + esc(g.name) + '</h3><span class="pills" ' +
          'style="justify-content:flex-end"><small style="align-self:center;color:var(--text-soft)">' +
          esc(t('teach_wholeteam')) + '</small>' + STATUSES.map(function(st) {
            return '<button class="chip" data-team-val="' + st + '" data-team="' + g.id + '">' +
              esc(t('teach_short_' + st)) + '</button>';
          }).join('') + '</span></div><ul class="list">' + rows(list) + '</ul>';
      }).join('');
      var loose = d.students.filter(function(s) {
        return !s.teamid;
      });
      if (loose.length) {
        body += '<div class="section-title"><h3>' + esc(t('teach_noteam')) + '</h3></div><ul class="list">' + rows(loose) +
          '</ul>';
      }
    } else {
      body = '<ul class="list">' + rows(d.students) + '</ul>';
    }
    var el = content('<section class="card"><button class="btn btn-soft" data-back>← ' + esc(t('app_back')) + '</button>' +
      '<div class="section-title" style="margin-top:12px"><h2>' + esc(ch.name) + '</h2>' + (can('manageregion') ?
      '<button class="btn btn-soft" data-edit>' + esc(t('teach_edit')) + '</button>' : '') + '</div><p>' + rewards(ch) +
      (ch.teamwork ? ' · ' + esc(t('teach_teamwork')) : '') + '</p>' + (ch.description ? '<p style="color:' +
      'var(--text-soft)">' + esc(ch.description) + '</p>' : '') + body + '</section><div id="form"></div>');
    el.querySelector('[data-back]').onclick = function() {
      state.challenge = 0;
      render();
    };
    var edit = el.querySelector('[data-edit]');
    if (edit) {
      edit.onclick = function() {
        challengeForm(ch);
      };
    }
    function say(userids, status, note) {
      var args = {courseid: cfg.courseid, challengeid: ch.id, userids: userids, status: status};
      if (note !== undefined) {
        args.note = note;
      }
      // Show it at once; the server confirms it (or the queue keeps it).
      userids.forEach(function(u) {
        var v = word[u] || {userid: u, note: ''};
        v.status = status;
        if (note !== undefined) {
          v.note = note;
        }
        if (!word[u]) {
          ch.validations.push(v);
          word[u] = v;
        }
      });
      validationView(id);
      sendValidation(args).then(function(results) {
        if (results) {
          load(true);
        }
      }).catch(function(e) {
        T.toast(esc(e.message), true);
        load(true);
      });
    }
    el.querySelectorAll('[data-val]').forEach(function(b) {
      b.onclick = function() {
        say([Number(b.dataset.user)], b.dataset.val);
      };
    });
    el.querySelectorAll('[data-team-val]').forEach(function(b) {
      b.onclick = function() {
        var ids = d.students.filter(function(s) {
          return s.teamid === Number(b.dataset.team) && s.character;
        }).map(function(s) {
          return s.userid;
        });
        if (ids.length) {
          say(ids, b.dataset.teamVal);
        }
      };
    });
    el.querySelectorAll('[data-note]').forEach(function(b) {
      b.onclick = function() {
        var u = Number(b.dataset.note);
        var v = word[u];
        var note = window.prompt(t('teach_note'), v ? v.note : '');
        if (note !== null) {
          say([u], v ? v.status : 'done', note);
        }
      };
    });
  }

  /* ---------- The log ---------- */

  function tabLog() {
    if (!state.log) {
      content('<section class="card"><p class="empty">' + esc(t('app_loading')) + '</p></section>');
      T.api('get_log', {courseid: cfg.courseid, userid: state.logUser, limit: 60}).then(function(entries) {
        state.log = entries;
        if (state.tab === 'log') {
          tabLog();
        }
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
      return;
    }
    var d = state.data;
    var who = '<select data-user style="min-height:44px;border-radius:10px;padding:0 10px;background:var(--night-2);' +
      'color:var(--text);border:1px solid var(--line)"><option value="0">' + esc(t('teach_everyone')) + '</option>' +
      d.students.map(function(s) {
        return '<option value="' + s.userid + '"' + (state.logUser === s.userid ? ' selected' : '') + '>' +
          esc(s.fullname) + '</option>';
      }).join('') + '</select>';
    var rows = state.log.map(function(e) {
      var cls = e.undone ? 'off' : e.amount >= 0 ? 'up' : 'down';
      var reason = cfg.strings['app_reason_' + e.reason] ? t('app_reason_' + e.reason) : e.reason;
      return '<li class="item"><span style="width:22px;height:22px;flex:none">' + T.icon(e.resource === 'item' ? 'gems' :
        e.resource) + '</span><span class="grow"><b>' + esc(e.fullname) + '</b><small>' + esc(reason) +
        (e.actor ? ' · ' + esc(e.actor) : '') + ' · ' + esc(new Date(e.time * 1000).toLocaleString(cfg.lang)) +
        (e.capped ? ' · ' + esc(t('app_capped', e.capped)) : '') + '</small></span><span class="amount ' + cls + '">' +
        (e.resource === 'item' ? '' : (e.amount > 0 ? '+' : '') + e.amount) + '</span>' + (can('undo') && !e.undone &&
        e.reason !== 'undo' ? '<button class="chip" data-undo="' + e.id + '">' + esc(t('teach_undo')) + '</button>' : '') +
        '</li>';
    }).join('');
    var el = content('<section class="card"><div class="section-title"><h2>' + esc(t('teach_tab_log')) + '</h2>' + who +
      '</div>' + (rows ? '<ul class="list">' + rows + '</ul>' : '<p class="empty">' + esc(t('app_nolog')) + '</p>') +
      '</section>');
    el.querySelector('[data-user]').onchange = function() {
      state.logUser = Number(this.value);
      state.log = null;
      tabLog();
    };
    el.querySelectorAll('[data-undo]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        T.api('undo', {courseid: cfg.courseid, entryid: Number(b.dataset.undo)}).then(function() {
          T.toast(esc(t('teach_undone')));
          state.log = null;
          load(true);
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
  }

  /* ---------- Settings ---------- */

  function tabSettings() {
    var r = state.data.region;
    var editable = can('manageregion');
    var num = function(name, value, help) {
      return field(name, t('teach_set_' + name), '<input name="' + name + '" type="number" min="0" max="1000" value="' +
        value + '"' + (editable ? '' : ' disabled') + '>' + (help ? '<small style="display:block;color:var(--text-soft);' +
        'margin-top:4px">' + esc(t('teach_set_' + name + '_help')) + '</small>' : ''));
    };
    var groupings = '<select name="groupingid"' + (editable ? '' : ' disabled') + '><option value="0">' +
      esc(t('teach_allgroups')) + '</option>' + state.data.groupings.map(function(g) {
        return '<option value="' + g.id + '"' + (r.groupingid === g.id ? ' selected' : '') + '>' + esc(g.name) + '</option>';
      }).join('') + '</select>';
    // The days of the game (caps, event of the day, energy and breath) start at midnight in this time zone.
    var zones = ['99', 'Europe/Madrid', 'Atlantic/Canary', 'America/Asuncion', 'America/Argentina/Buenos_Aires',
      'America/Montevideo', 'America/Santiago', 'America/La_Paz', 'America/Lima', 'America/Bogota', 'America/Caracas',
      'America/Mexico_City'];
    if (zones.indexOf(r.timezone) < 0) {
      zones.push(r.timezone);
    }
    var timezone = '<select name="timezone"' + (editable ? '' : ' disabled') + '>' + zones.map(function(z) {
      return '<option value="' + esc(z) + '"' + (r.timezone === z ? ' selected' : '') + '>' +
        esc(z === '99' ? t('teach_tz_site') : z.replace(/_/g, ' ')) + '</option>';
    }).join('') + '</select><small style="display:block;color:var(--text-soft);margin-top:4px">' +
      esc(t('teach_set_timezone_help')) + '</small>';
    var nick = [0, 1, 2].map(function(n) {
      return '<label class="chip" style="display:flex;gap:8px;align-items:center;margin-bottom:6px"><input type="radio" ' +
        'name="nicknames" value="' + n + '"' + (r.nicknames === n ? ' checked' : '') + (editable ? '' : ' disabled') +
        '> ' + esc(t('teach_nick_' + n)) + '</label>';
    }).join('');
    // The cover of the course page: each mode with a small drawing of how it looks.
    var covers = ['door', 'card', 'direct', 'menu'].map(function(mode) {
      return '<label class="cover-option"><input type="radio" name="portal" value="' + mode + '"' +
        (r.portal === mode ? ' checked' : '') + (editable ? '' : ' disabled') + '><span class="cover-sketch cover-' + mode +
        '" aria-hidden="true"><i></i><b></b><b></b><b></b></span><span class="cover-text"><b>' +
        esc(t('teach_cover_' + mode)) + '</b><small>' + esc(t('teach_cover_' + mode + '_help')) + '</small></span></label>';
    }).join('');
    var inside = '<label class="chip" style="display:flex;gap:8px;align-items:flex-start;margin:10px 0 4px"><input ' +
      'type="checkbox" name="hideacts"' + (r.hideacts ? ' checked' : '') + (editable && (r.allowstealth || r.hideacts) ? '' :
      ' disabled') + '> <span>' + esc(t('teach_set_hideacts')) + '</span></label><small style="display:block;color:' +
      'var(--text-soft);margin-bottom:14px">' + esc(t(r.allowstealth ? 'teach_set_hideacts_help' : 'teach_set_nostealth')) +
      (!r.allowstealth && can('siteconfig') ? ' <a href="' + esc(cfg.wwwroot +
      '/admin/settings.php?section=local_oksigeniatesape') + '">' + esc(t('teach_set_stealth_admin')) + '</a>' : '') +
      '</small>';
    var coverCard = '<h3>' + esc(t('teach_cover')) + '</h3><p style="color:var(--text-soft)">' + esc(t('teach_cover_help')) +
      '</p><div class="cover-options">' + covers + '</div>' + inside + '<p><a href="' +
      esc(cfg.wwwroot + '/course/view.php?id=' + cfg.courseid) + '">' + esc(t('teach_cover_see')) + '</a></p>';
    var el = content('<section class="card"><h2>' + esc(t('teach_tab_settings')) + '</h2><form data-settings>' +
      '<label class="chip" style="display:inline-flex;gap:8px;align-items:center;margin-bottom:14px"><input ' +
      'type="checkbox" name="enabled"' + (r.enabled ? ' checked' : '') + (editable ? '' : ' disabled') + '> ' +
      esc(t('teach_set_enabled')) + '</label><h3>' + esc(t('teach_family')) + '</h3><div class="pills" style="' +
      'justify-content:flex-start;margin-bottom:14px">' + families(r.family) + '</div>' +
      field('groupingid', t('teach_set_groupingid'), groupings) + field('timezone', t('teach_set_timezone'), timezone) +
      (editable ? '<div style="margin:-6px 0 14px">' +
      makeTeamsForm() + '</div>' : '') + '<div class="grid2" style="gap:10px">' +
      num('weeklycap', r.weeklycap, true) + num('dailymanualcap', r.dailymanualcap, false) +
      num('dailyenergylosscap', r.dailyenergylosscap, false) + num('completionrule', r.completionrule, true) + '</div>' +
      '<label class="chip" style="display:inline-flex;gap:8px;align-items:center;margin:4px 0 14px"><input ' +
      'type="checkbox" name="leaderboard"' + (r.leaderboard ? ' checked' : '') + (editable ? '' : ' disabled') + '> ' +
      esc(t('teach_set_leaderboard')) + '</label><h3>' + esc(t('teach_set_nicknames')) + '</h3>' + nick + coverCard +
      (editable ? '<div class="actions" style="justify-content:flex-start;margin-top:14px"><button class="btn btn-main" ' +
      'type="submit">' + esc(t('app_save')) + '</button></div>' : '') + '</form></section>');
    var family = r.family;
    bindFamilies(function(f) {
      family = f;
    });
    bindMakeTeams(el);
    var f = el.querySelector('form');
    f.onsubmit = function(ev) {
      ev.preventDefault();
      var picked = f.querySelector('[name="nicknames"]:checked');
      var cover = f.querySelector('[name="portal"]:checked');
      T.api('save_region', {
        courseid: cfg.courseid,
        enabled: f.enabled.checked,
        family: family,
        groupingid: Number(f.groupingid.value),
        weeklycap: Number(f.weeklycap.value),
        dailymanualcap: Number(f.dailymanualcap.value),
        dailyenergylosscap: Number(f.dailyenergylosscap.value),
        completionrule: Number(f.completionrule.value),
        leaderboard: f.leaderboard.checked,
        nicknames: picked ? Number(picked.value) : 0,
        timezone: f.timezone.value,
        portal: cover ? cover.value : 'door',
        hideacts: f.hideacts.checked
      }).then(function() {
        T.toast(esc(t('app_saved')));
        state.tab = 'class';
        load(true);
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
  }

  load().then(flush);
})();
