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
    ideas: null, newpower: false, showres: false};
  var QUEUE = 'tesape-queue-' + cfg.courseid + '-' + cfg.userid;
  // Energy and breath in the class list, only for who asked to see them.
  try {
    state.showres = !!window.localStorage.getItem('tesape-showres-' + cfg.userid);
  } catch (e) {
    state.showres = false;
  }

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

  var flushing = false;

  /**
   * Takes one word given out of the queue: the one that was sent, not whatever is first now.
   *
   * @param {Object} sent
   */
  function unqueue(sent) {
    var key = JSON.stringify(sent);
    var list = queued();
    var at = list.map(JSON.stringify).indexOf(key);
    if (at >= 0) {
      list.splice(at, 1);
      setQueue(list);
    }
  }

  function flush() {
    var list = queued();
    // One at a time: the connection that comes back while one is on its way does not send it twice.
    if (flushing || !list.length || navigator.onLine === false) {
      return;
    }
    flushing = true;
    var next = list[0];
    T.api('validate', next).then(function() {
      flushing = false;
      unqueue(next);
      if (queued().length) {
        flush();
      } else {
        T.toast(esc(t('teach_sent')));
        load(true);
      }
    }).catch(function(e) {
      flushing = false;
      if (e.network) {
        // Still no luck; it will try again when the connection comes back.
        return;
      }
      // The server said no (the challenge was deleted meanwhile, for instance): it will not say yes later.
      T.toast(esc(e.message), true);
      unqueue(next);
      flush();
    });
  }

  window.addEventListener('online', flush);

  /* ---------- Frame ---------- */

  var TABS = ['class', 'powers', 'mission', 'competencies', 'challenges', 'log'];

  /** What the game can adapt for a student (as adapt::ALL on the server). */
  var ADAPT = ['bigtext', 'readaloud', 'notime', 'still', 'contrast', 'colours', 'quiet', 'hints'];

  /** The profiles of one touch, and what each one marks (as adapt::PROFILES on the server). */
  var PROFILES = {reading: ['readaloud', 'bigtext', 'hints'], calm: ['notime', 'quiet', 'still'], sight: ['bigtext', 'contrast']};

  /**
   * The buttons of the profiles of one touch.
   *
   * @param {string} attr The attribute that says which one was touched.
   * @return {string}
   */
  function profileChips(attr) {
    return Object.keys(PROFILES).map(function(code) {
      var what = PROFILES[code].map(function(a) {
        return t('teach_adapt_' + a);
      }).join(', ');
      return '<button type="button" class="chip" ' + attr + '="' + code + '" title="' + esc(what) + '">' +
        esc(t('teach_adapt_profile_' + code)) + '<span class="sr-only">: ' + esc(what) + '</span></button>';
    }).join('');
  }

  // The light this device remembers for the screens of the teacher.
  T.light.restore();

  /**
   * The switch of light of the panel: the sun to go to day, the moon to go back to night, on this device only.
   *
   * @return {string}
   */
  function lightButton() {
    var day = T.theme() === 'day';
    return '<button class="btn btn-soft icon-btn" type="button" data-light aria-label="' + esc(t(day ? 'teach_dark' :
      'teach_light')) + '" title="' + esc(t(day ? 'teach_dark' : 'teach_light')) + '">' + T.icon(day ? 'moon' : 'sun') +
      '</button>';
  }

  function bindLight() {
    var b = app.querySelector('[data-light]');
    if (b) {
      b.onclick = function() {
        T.light.toggle();
        b.outerHTML = lightButton();
        bindLight();
      };
    }
  }

  function render() {
    var d = state.data;
    clearInterval(state.remotetimer);
    if (!d.region.enabled && state.tab !== 'settings') {
      welcome();
      return;
    }
    if (state.steps) {
      firstSteps(state.steps);
      return;
    }
    var pending = queued().length;
    app.innerHTML = '<div class="sky"></div><div class="world">' +
      '<header class="hud"><div class="me"><span class="face emblem-face">' + T.emblem() + '</span><span class="who">' +
      '<b>' + esc(cfg.coursename) + '</b><small>' + esc(t('teach_title')) + (pending ? ' · ' + esc(t('teach_pending',
      pending)) : '') + '</small></span></div><div class="hud-actions"><button class="btn btn-soft icon-btn" type="button" ' +
      // Its name, hidden, before the ideas not read yet: the button reads «Buzón de ideas 2», as it shows.
      'data-tab="ideas"' + (state.tab === 'ideas' ? ' aria-current="page"' : '') + '><span class="sr-only">' +
      esc(t('teach_tab_ideas')) + ' </span>' + T.icon('idea') + (d.ideasunread ? '<span class="badge">' + d.ideasunread +
      '</span>' : '') + '</button>' +
      (can('runclassscreen') ? '<a class="btn btn-soft icon-btn" href="' +
      esc(cfg.wwwroot + '/local/oksigeniatesape/board.php?id=' + cfg.courseid) + '" aria-label="' + esc(t('teach_board')) +
      '" title="' + esc(t('teach_board')) + '">' + T.icon('world') + '</a>' : '') +
      '<button class="btn btn-soft icon-btn" type="button" data-how aria-label="' + esc(t('teach_how')) + '" title="' +
      esc(t('teach_how')) + '">?</button>' + lightButton() + '<button class="btn btn-soft icon-btn" type="button" ' +
      'data-tab="settings" aria-label="' +
      esc(t('teach_tab_settings')) + '" title="' + esc(t('teach_tab_settings')) +
      '"' + (state.tab === 'settings' ? ' aria-current="page"' : '') + '>' + T.icon('settings') + '</button><a class="btn ' +
      'btn-soft exit" href="' + esc(cfg.courseurl) + '" aria-label="' + esc(t('app_exit')) + '">' + T.icon('exit') +
      '<span>' + esc(t('app_exit')) + '</span></a></div></header><nav class="tabs" ' +
      'aria-label="Tesapé">' + TABS.map(function(tab) {
        var count = tab === 'powers' ? d.powerspending : tab === 'challenges' ? d.pactspending : 0;
        var badge = count ? '<span class="badge">' + count + '</span>' : '';
        return '<button type="button" data-tab="' + tab + '"' + (state.tab === tab ? ' aria-current="page"' : '') + '>' +
          T.icon({class: 'team', powers: 'power', mission: 'world', competencies: 'saber', challenges: 'challenges',
            log: 'log'}[tab]) + badge +
          '<span>' + esc(t('teach_tab_' + tab)) + '</span></button>';
      }).join('') + '</nav><main class="content" id="content"></main></div>';
    bindLight();
    app.querySelector('[data-how]').onclick = howItWorks;
    // The first time a teacher opens the panel of a course, how it works in two minutes: once the first week is done,
    // not over its steps.
    try {
      var hkey = 'tesape-how-' + cfg.courseid + '-' + cfg.userid;
      if (!window.localStorage.getItem(hkey) && d.region.setup >= STEPS.length) {
        window.localStorage.setItem(hkey, '1');
        setTimeout(function() {
          if (!state.steps) {
            howItWorks();
          }
        }, 300);
      }
    } catch (e) {
      // Without storage it does not show by itself; the «?» is there.
    }
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
        if (state.tab === 'competencies') {
          state.comp = null;
        }
        render();
        window.scrollTo(0, 0);
      };
    });
    ({class: tabClass, powers: tabPowers, mission: tabMission, competencies: tabCompetencies, challenges: tabChallenges,
      log: tabLog, settings: tabSettings, ideas: tabIdeas})[state.tab]();
  }

  function content(html) {
    var el = document.getElementById('content');
    el.innerHTML = html;
    return el;
  }

  /* ---------- The first week, step by step ---------- */

  /** The steps of the first week, and the minutes each one takes. */
  var STEPS = [['mission', 3], ['family', 1], ['devices', 1], ['teams', 1], ['rehearse', 3]];

  /**
   * How long a step takes, said as people say it.
   *
   * @param {number} n Minutes.
   * @return {string}
   */
  function minutes(n) {
    return n === 1 ? t('teach_first_minute') : t('teach_first_minutes', n);
  }

  function welcome() {
    app.innerHTML = '<div class="sky"></div><div class="story">' + T.vignette('region') + '<h1>' + esc(t('teach_welcome')) +
      '</h1><p class="lead">' + esc(t('teach_welcome_help')) + '</p><ol class="first-plan">' + STEPS.map(function(x) {
        return '<li><b>' + esc(t('teach_first_' + x[0])) + '</b> <small>' + esc(minutes(x[1])) + '</small></li>';
      }).join('') + '</ol><div class="actions">' + (can('manageregion') ? '<button class="btn btn-main" data-on>' +
      esc(t('teach_turnon')) + '</button>' : '<p class="help">' + esc(t('teach_askeditor')) + '</p>') +
      '<a class="btn btn-soft" href="' + esc(cfg.courseurl) + '">' + esc(t('app_exit')) + '</a></div></div>';
    var on = app.querySelector('[data-on]');
    if (on) {
      on.onclick = function() {
        on.disabled = true;
        T.api('save_region', {courseid: cfg.courseid, enabled: true}).then(function() {
          T.toast(esc(t('teach_on')));
          state.steps = 1;
          load();
        }).catch(function(e) {
          on.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    }
  }

  /**
   * One step of the first week: what to do and how long it takes, done here; then on to the next one, or back to the
   * panel to go on later.
   *
   * @param {number} n From 1 to 5.
   */
  function firstSteps(n) {
    var d = state.data;
    var r = d.region;
    var step = STEPS[n - 1];
    state.steps = n;
    if (step[0] === 'mission' && !state.missions) {
      app.innerHTML = '<div class="loading"><div><div class="lamp"></div>' + esc(t('app_loading')) + '</div></div>';
      T.api('get_missions', {courseid: cfg.courseid}).then(function(m) {
        state.missions = m;
        if (state.steps === n) {
          firstSteps(n);
        }
      }).catch(function(e) {
        T.toast(esc(e.message), true);
        state.steps = 0;
        render();
      });
      return;
    }
    var body = '';
    if (step[0] === 'mission') {
      var m = state.missions;
      body = (m.installed ? '<p class="first-done">✓ ' + esc(t('teach_first_mission_done', m.installed.title)) + '</p>' :
        m.packages.map(packageCard).join('')) + (r.completion ? '' : '<p class="first-warn">' +
        esc(t('teach_first_completion')) + '</p>');
    } else if (step[0] === 'family') {
      body = '<div class="pills" style="margin-bottom:6px">' + families(r.family) + '</div>';
    } else if (step[0] === 'devices') {
      body = '<div class="first-choices" role="group" aria-label="' + esc(t('teach_first_devices')) + '">' +
        [['1', 'tablets'], ['0', 'none']].map(function(x) {
          var on = (r.devices !== false) === (x[0] === '1');
          return '<button type="button" class="pill" aria-pressed="' + on + '" ' +
            'data-firstdevices="' + x[0] + '"><b>' + esc(t('board_devices_' + x[1])) + '</b><small>' +
            esc(t('teach_first_devices_' + x[1])) + '</small></button>';
        }).join('') + '</div>';
    } else if (step[0] === 'teams') {
      var have = d.students.filter(function(x) {
        return !!x.character;
      }).length;
      var count = {have: have, total: d.students.length};
      // Nearly everyone has a character: they can be made now, in «Class».
      body = '<p class="first-done">' + (d.standings.length ? '✓ ' + esc(t('teach_first_teams_done', d.standings.length)) :
        esc(t(have && have >= 0.8 * d.students.length ? 'teach_first_teams_ready' : 'teach_first_teams_wait', count))) +
        '</p>';
    } else {
      body = '<ol class="first-list">' + ['board', 'print', 'sheet'].map(function(x) {
        return '<li>' + esc(t('teach_first_rehearse_' + x)) + '</li>';
      }).join('') + '</ol>' + (can('runclassscreen') ? '<p><a class="btn btn-soft" target="_blank" href="' +
        esc(cfg.wwwroot + '/local/oksigeniatesape/board.php?id=' + cfg.courseid) + '">' + esc(t('teach_first_open_board')) +
        '</a></p>' : '');
    }
    var last = n === STEPS.length;
    app.innerHTML = '<div class="sky"></div><div class="story first-steps"><div class="kicker">' +
      esc(t('teach_first_step', {n: n, total: STEPS.length, time: minutes(step[1])})) + '</div><ol class="first-dots" ' +
      'aria-hidden="true">' + STEPS.map(function(x, i) {
        return '<li class="' + (i + 1 < n ? 'done' : i + 1 === n ? 'now' : '') + '"></li>';
      }).join('') + '</ol><h1>' + esc(t('teach_first_' + step[0])) + '</h1><p class="lead">' +
      esc(t('teach_first_' + step[0] + '_help')) + '</p>' + body + '<div class="actions">' + (n > 1 ?
      '<button class="btn btn-soft" type="button" data-firstback>' + esc(t('teach_first_back')) + '</button>' : '') +
      '<button class="btn btn-main" type="button" data-firstnext>' + esc(t(last ? 'teach_first_finish' : 'teach_first_next')) +
      '</button><button class="btn btn-soft" type="button" data-firstlater>' + esc(t('teach_first_later')) +
      '</button></div></div>';
    window.scrollTo(0, 0);
    var save = function(settings) {
      return T.api('save_region', Object.assign({courseid: cfg.courseid}, settings)).then(function(region) {
        state.data.region = region;
        T.family = region.family;
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
    bindInstall(app, function() {
      firstSteps(n);
    });
    bindFamilies(function(f) {
      save({family: f});
    });
    app.querySelectorAll('[data-firstdevices]').forEach(function(b) {
      b.onclick = function() {
        app.querySelectorAll('[data-firstdevices]').forEach(function(o) {
          o.setAttribute('aria-pressed', o === b);
        });
        save({devices: b.dataset.firstdevices === '1'});
      };
    });
    var back = app.querySelector('[data-firstback]');
    if (back) {
      back.onclick = function() {
        firstSteps(n - 1);
      };
    }
    app.querySelector('[data-firstnext]').onclick = function() {
      this.disabled = true;
      save({setup: Math.max(r.setup, n)}).then(function() {
        if (last) {
          state.steps = 0;
          state.tab = 'class';
          render();
        } else {
          firstSteps(n + 1);
        }
      });
    };
    app.querySelector('[data-firstlater]').onclick = function() {
      state.steps = 0;
      state.tab = 'class';
      render();
    };
  }

  /**
   * In the class tab: the way back to the first week while it is not finished, and then the list of the first day.
   *
   * @return {string}
   */
  function firstCard() {
    var d = state.data;
    var r = d.region;
    if (!can('manageregion') || r.setup >= 6) {
      return '';
    }
    if (r.setup < STEPS.length) {
      return '<section class="card first-card" style="margin-bottom:16px"><div class="kicker">' + esc(t('teach_first')) +
        '</div><h2>' + esc(t('teach_first_left', {done: r.setup, total: STEPS.length})) + '</h2><div class="actions" ' +
        'style="justify-content:flex-start"><button class="btn btn-main" type="button" data-firststeps>' +
        esc(t(r.setup ? 'teach_first_go_on' : 'teach_first_start')) + '</button></div></section>';
    }
    var ticked = {};
    try {
      ticked = JSON.parse(window.localStorage.getItem('tesape-firstday-' + cfg.courseid) || '{}') || {};
    } catch (e) {
      // Nothing ticked on this device.
    }
    return '<section class="card first-card" style="margin-bottom:16px"><div class="kicker">' + esc(t('teach_first')) +
      '</div><h2>' + esc(t('teach_firstday_title')) + '</h2><ul class="first-ticks">' + ['users', 'board',
      r.devices === false ? 'cards' : 'tablets', 'sheet', 'rehearsal'].map(function(item) {
        return '<li><label class="chip"><input type="checkbox" data-firsttick="' + item + '"' + (ticked[item] ? ' checked' :
          '') + '> ' + esc(t('teach_firstday_' + item, d.students.length)) + '</label></li>';
      }).join('') + '</ul><div class="actions" style="justify-content:flex-start"><button class="btn btn-soft" ' +
      'type="button" data-firstdone>' + esc(t('teach_firstday_done')) + '</button></div></section>';
  }

  function bindFirstCard(el) {
    var go = el.querySelector('[data-firststeps]');
    if (go) {
      go.onclick = function() {
        firstSteps(Math.min(STEPS.length, state.data.region.setup + 1));
      };
    }
    el.querySelectorAll('[data-firsttick]').forEach(function(box) {
      box.onchange = function() {
        var ticked = {};
        el.querySelectorAll('[data-firsttick]').forEach(function(b) {
          ticked[b.dataset.firsttick] = b.checked;
        });
        try {
          window.localStorage.setItem('tesape-firstday-' + cfg.courseid, JSON.stringify(ticked));
        } catch (e) {
          // Without storage, the ticks last while the page is open.
        }
      };
    });
    var done = el.querySelector('[data-firstdone]');
    if (done) {
      done.onclick = function() {
        done.disabled = true;
        T.api('save_region', {courseid: cfg.courseid, setup: 6}).then(function(region) {
          state.data.region = region;
          render();
        }).catch(function(e) {
          done.disabled = false;
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
      (m && state.showres ? '<span class="bars" aria-hidden="true"><span class="meter energy"><i style="width:' +
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
    var sessions = today.sessions.map(function(x) {
      return '<li><b>' + esc(t('board_today_session', x.n)) + '</b>' + (x.minutes ? ' · ' + esc(t('app_minutes', x.minutes)) :
        '') + '<p>' + esc(x.do) + '</p>' + (x.materials ? '<small>' + esc(t('board_today_materials', x.materials)) +
        '</small>' : '') + (x.scene ? '<small>' + esc(t('teach_today_scene', x.scene)) + '</small>' : '') + '</li>';
    }).join('');
    return '<section class="card today-card" style="margin-bottom:16px"><div class="kicker">' + esc(t('board_today')) +
      '</div><h2>' + esc(today.chapter) + '</h2>' + (today.stains && today.stains.length ? '<ul class="stains">' +
      today.stains.map(function(s) {
        return '<li><span class="stain-dot" aria-hidden="true"></span><span>' + esc(t(s.kind === 'page' ? 'board_today_page' : 'board_today_stain', s)) + '</span>' +
          (can('award') ? '<button type="button" class="btn btn-soft" data-erasestain="' + s.id + '">' +
          esc(t(s.kind === 'page' ? 'board_page_erase' : 'board_stain_erase')) + '</button>' : '') + '</li>';
      }).join('') + '</ul>' : '') + (today.lostscene ? '<p class="teaser"><b>' + esc(t('teach_today_night')) +
      '</b> ' + esc(t('teach_today_night_help', today.lostscene.title)) + '</p>' : '') +
      (sessions ? '<ol class="today-sessions">' + sessions + '</ol>' : '') +
      (today.offscreen ? '<p class="help-soft">' + esc(t('board_today_offscreen', today.offscreen)) + '</p>' : '') +
      '</section>';
  }

  /**
   * What waits for the teacher, in one tray: the own names the students wrote (nobody else sees them until a teacher
   * takes them), the powers asked, the team plays of today, and where the rest waits. Each thing, with its answer here.
   *
   * @return {string}
   */
  function inboxCard() {
    var d = state.data;
    var marks = can('award');
    var names = {};
    d.students.forEach(function(s) {
      names[s.userid] = s.fullname;
    });
    var group = function(title, help, all, items) {
      return '<div class="inbox-group"><div class="section-title"><h3>' + esc(title) + '</h3>' + all + '</div>' +
        (help ? '<p class="help-soft">' + esc(help) + '</p>' : '') + '<ul class="list">' + items.join('') + '</ul></div>';
    };
    var groups = [];
    var waiting = d.namespending || [];
    if (waiting.length) {
      groups.push(group(t('teach_inbox_names', waiting.length), t('teach_names_help'), marks && waiting.length > 1 ?
        '<button class="btn btn-soft" type="button" data-inboxnames>' + esc(t('teach_inbox_names_all', waiting.length)) +
        '</button>' : '', waiting.map(function(x) {
          var key = x.userid + ':' + x.what;
          return '<li class="item"><span class="grow"><b>' + esc(x.name) + '</b><small>' + esc(names[x.userid] || '') + ' · ' +
            esc(t('teach_name_' + x.what)) + '</small></span>' + (marks ? '<span class="row-actions"><button class="chip" ' +
            'type="button" data-inboxname="' + key + ':1">' + esc(t('teach_name_ok')) + '</button><button class="chip" ' +
            'type="button" data-inboxname="' + key + ':0">' + esc(t('teach_name_no')) + '</button></span>' : '') + '</li>';
        })));
    }
    var queue = d.powersqueue || [];
    if (queue.length) {
      groups.push(group(t('teach_inbox_powers', queue.length), '', queue.length > 1 ? '<button class="btn btn-soft" ' +
        'type="button" data-inboxpowers>' + esc(t('teach_inbox_powers_all', queue.length)) + '</button>' : '',
        queue.map(function(q) {
          return '<li class="item"><span class="grow"><b>' + esc(q.fullname) + '</b><small>' + esc(q.power) + (q.target ?
            ' → ' + esc(q.target) : '') + ' · ' + esc(t('teach_power_cost', q.cost)) + ' · ' + esc(when(q.time)) +
            '</small></span><span class="row-actions"><button class="chip" type="button" data-inboxpower="' + q.id +
            ':1">' + esc(t('teach_power_yes')) + '</button><button class="chip" type="button" data-inboxpower="' + q.id +
            ':0">' + esc(t('teach_power_no')) + '</button></span></li>';
        })));
    }
    // The team plays of today: a classmate marked them as there; if they were not, the teacher takes it back.
    var day = new Date();
    day.setHours(0, 0, 0, 0);
    var plays = [];
    d.students.forEach(function(s) {
      (s.teamplays || []).forEach(function(p) {
        if (p.time * 1000 >= day.getTime()) {
          plays.push('<li class="item"><span class="grow"><b>' + esc(s.fullname) + '</b><small>' + esc(p.scene) + ' · ' +
            esc(t('teach_teamplay_by', {by: p.by || '—', time: when(p.time)})) + '</small></span><button class="chip" ' +
            'type="button" data-inboxundo="' + p.id + '">' + esc(t('teach_teamplay_undo')) + '</button></li>');
        }
      });
    });
    if (plays.length && can('undo')) {
      groups.push(group(t('teach_inbox_teamplays', plays.length), t('teach_teamplays_help'), '', plays));
    }
    // Where the rest waits: a tap goes there.
    var links = [];
    if (d.powerspending && !queue.length) {
      links.push('<button type="button" class="chip" data-goto="powers">' + esc(t('teach_today_powers', d.powerspending)) +
        '</button>');
    }
    if (d.pactspending) {
      links.push('<button type="button" class="chip" data-goto="challenges">' + esc(t('teach_today_pacts', d.pactspending)) +
        '</button>');
    }
    if (d.ideasunread) {
      links.push('<button type="button" class="chip" data-goto="ideas">' + esc(t('teach_today_ideas', d.ideasunread)) +
        '</button>');
    }
    // A classroom challenge validated by halves: the teacher started and did not finish.
    d.challenges.forEach(function(ch) {
      var done = ch.validations.filter(function(v) {
        return v.status !== 'notyet';
      }).length;
      if (done && done < d.students.length) {
        links.push('<button type="button" class="chip" data-inboxchallenge="' + ch.id + '">' + esc(t('teach_inbox_challenge',
          {name: ch.name, done: done, total: d.students.length})) + '</button>');
      }
    });
    if (!groups.length && !links.length) {
      return '';
    }
    return '<section class="card inbox-card" data-inbox style="margin-bottom:16px"><div class="kicker">' +
      esc(t('teach_inbox')) + '</div>' + groups.join('') + (links.length ? '<div class="chips">' + links.join('') +
      '</div>' : '') + '</section>';
  }

  /**
   * Draws the tray again with what the server answered, without asking for the whole class: and the counter of the
   * powers on its tab.
   */
  function refreshInbox() {
    var old = app.querySelector('[data-inbox]');
    var html = inboxCard();
    if (old && html) {
      old.outerHTML = html;
      bindInbox(app.querySelector('[data-inbox]'));
    } else if (old) {
      old.remove();
    }
    var tab = app.querySelector('.tabs [data-tab="powers"]');
    if (tab) {
      var badge = tab.querySelector('.badge');
      if (badge) {
        badge.remove();
      }
      if (state.data.powerspending) {
        tab.lastElementChild.insertAdjacentHTML('beforebegin', '<span class="badge">' + state.data.powerspending + '</span>');
      }
    }
  }

  /**
   * The answers of the tray.
   *
   * @param {Element} el The tray.
   */
  function bindInbox(el) {
    if (!el) {
      return;
    }
    var d = state.data;
    var busy = function() {
      el.querySelectorAll('button').forEach(function(b) {
        b.disabled = true;
      });
    };
    var failed = function(e) {
      T.toast(esc(e.message), true);
      refreshInbox();
    };
    var names = function(args, many) {
      busy();
      T.api('decide_name', Object.assign({courseid: cfg.courseid, approve: true}, args)).then(function(res) {
        var left = many ? res.names.length : 0;
        var still = {};
        res.names.forEach(function(x) {
          still[x.userid + ':' + x.what] = true;
        });
        if (args.approve !== false) {
          d.namespending.forEach(function(x) {
            var s = studentOf(x.userid);
            var asked = many || (x.userid === args.userid && x.what === args.what);
            if (asked && x.what === 'hero' && !still[x.userid + ':hero'] && s && s.character) {
              s.character.nickname = x.name;
            }
          });
        }
        d.namespending = res.names;
        render();
        T.toast(esc(t(left ? 'teach_inbox_names_left' : 'app_saved')));
      }).catch(failed);
    };
    el.querySelectorAll('[data-inboxname]').forEach(function(b) {
      b.onclick = function() {
        var parts = b.dataset.inboxname.split(':');
        names({userid: Number(parts[0]), what: parts[1], approve: parts[2] === '1'}, false);
      };
    });
    var all = el.querySelector('[data-inboxnames]');
    if (all) {
      all.onclick = function() {
        names({names: d.namespending.map(function(x) {
          return {userid: x.userid, what: x.what};
        })}, true);
      };
    }
    var powers = function(args) {
      busy();
      T.api('decide_power', Object.assign({courseid: cfg.courseid}, args)).then(function(res) {
        state.powers = res;
        d.powersqueue = res.pending;
        d.powerspending = res.pending.length;
        refreshInbox();
        T.toast(esc(t('app_saved')));
      }).catch(failed);
    };
    el.querySelectorAll('[data-inboxpower]').forEach(function(b) {
      b.onclick = function() {
        var parts = b.dataset.inboxpower.split(':');
        powers({requestid: Number(parts[0]), approve: parts[1] === '1'});
      };
    });
    var approve = el.querySelector('[data-inboxpowers]');
    if (approve) {
      approve.onclick = function() {
        powers({approve: true, requestids: (d.powersqueue || []).map(function(q) {
          return q.id;
        })});
      };
    }
    el.querySelectorAll('[data-inboxundo]').forEach(function(b) {
      b.onclick = function() {
        busy();
        // It takes saber and gems back: the class is read again.
        T.api('undo_teamplay', {courseid: cfg.courseid, attemptid: Number(b.dataset.inboxundo)}).then(function() {
          T.toast(esc(t('teach_teamplay_undone')));
          return load(true);
        }).catch(failed);
      };
    });
    el.querySelectorAll('[data-goto]').forEach(function(b) {
      b.onclick = function() {
        state.tab = b.dataset.goto;
        state.open = 0;
        render();
      };
    });
    el.querySelectorAll('[data-inboxchallenge]').forEach(function(b) {
      b.onclick = function() {
        state.tab = 'challenges';
        state.challenge = Number(b.dataset.inboxchallenge);
        render();
        window.scrollTo(0, 0);
      };
    });
  }

  /**
   * How long ago a student came into the game, in days (-1: not known yet).
   *
   * @param {Object} m The member of the student.
   * @return {number}
   */
  function daysAway(m) {
    return m && m.lastseen ? Math.floor((Date.now() / 1000 - m.lastseen) / 86400) : -1;
  }

  /**
   * When a student last came into the game, said plainly.
   *
   * @param {Object} m
   * @return {string}
   */
  function lastSeen(m) {
    var days = daysAway(m);
    return days < 0 ? t('teach_seen_unknown') : days === 0 ? t('teach_seen_today') : days === 1 ? t('teach_seen_yesterday') :
      t('teach_seen_days', days);
  }

  /**
   * Who is far from the expedition: who has not come in for a week or more, and who follows it from far (home,
   * hospital). Each one opens their card.
   *
   * @return {string}
   */
  function farCard() {
    var far = state.data.students.filter(function(s) {
      var m = s.character && s.character.member;
      return m && (m.remote || daysAway(m) >= 7);
    });
    if (!far.length) {
      return '';
    }
    return '<section class="card" style="margin-top:16px"><div class="kicker">' + esc(t('teach_far')) + '</div><p ' +
      'class="help-soft">' + esc(t('teach_far_help')) + '</p><ul class="list">' + far.map(function(s) {
        var m = s.character.member;
        // A list of buttons: the item of the list, and the button in it (screen readers count the list).
        return '<li><div class="item" data-student="' + s.userid + '" tabindex="0" role="button" style="cursor:pointer">' +
          '<span class="grow"><b>' + esc(s.fullname) + '</b><small>' + esc(lastSeen(m)) + '</small></span>' + (m.remote ?
          '<span class="tag">' + esc(t('teach_remote_tag')) + '</span>' : '') + '</div></li>';
      }).join('') + '</ul></section>';
  }

  /**
   * How Tesapé works, in two minutes: the six ideas of the first day. The rest (breath, energy, gems, powers,
   * commitments, quests, pages) is told when it shows.
   */
  function howItWorks() {
    // Once at a time: it may open by itself while the teacher opens it with the «?».
    if (document.querySelector('.how-moment')) {
      return;
    }
    var ideas = [['me', 'teach_how_1'], ['team', 'teach_how_2'], ['saber', 'teach_how_3'], ['lantern', 'teach_how_4'],
      ['game', 'teach_how_5'], ['challenges', 'teach_how_6']];
    var layer = document.createElement('div');
    layer.className = 'badge-moment how-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-how-title');
    layer.innerHTML = '<div class="badge-card how-card"><h2 id="tesape-how-title">' + esc(t('teach_how')) + '</h2>' +
      '<ol class="how-list">' + ideas.map(function(i) {
        return '<li><span class="how-icon" aria-hidden="true">' + T.icon(i[0]) + '</span><span>' + esc(t(i[1])) + '</span></li>';
      }).join('') + '</ol><p class="help-soft">' + esc(t('teach_how_rest')) + '</p><div class="actions"><button ' +
      'class="btn btn-main" type="button" data-close>' + esc(t('teach_how_go')) + '</button></div></div>';
    document.body.appendChild(layer);
    var close = function() {
      document.removeEventListener('keydown', onkey);
      layer.remove();
    };
    var onkey = function(ev) {
      if (ev.key === 'Escape') {
        close();
      }
    };
    document.addEventListener('keydown', onkey);
    layer.querySelector('[data-close]').onclick = close;
    layer.querySelector('[data-close]').focus();
  }

  /**
   * The battle from here, the phone of the teacher, as from the class screen: launch it, go on, show the answer, end it;
   * while it is played, the turn, the candles and the threads. The class screen follows what is done here.
   *
   * @return {string}
   */
  /* ---------- The session, as a director ---------- */

  /**
   * The session of today: the one going on, step by step, with what each step launches on the class screen; or the
   * one that comes next, to start it.
   *
   * @return {string}
   */
  function sessionCard() {
    return can('runclassscreen') ? '<section class="card session-card" data-session-body><p class="help-soft">' +
      esc(t('teach_command_loading')) + '</p></section>' : '';
  }

  function bindSession(el) {
    var box = el.querySelector('[data-session-body]');
    if (!box) {
      return;
    }
    var run = function(action, extra) {
      return T.api('run_session', Object.assign({courseid: cfg.courseid, action: action}, extra || {})).then(function(r) {
        state.session = JSON.parse(r.json);
        state.sessionat = Date.now();
        draw();
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
    var name = function(v) {
      return v.chapter === 'entrada' ? t('teach_session_entry') : t('teach_session_name', v);
    };
    var folded = function(title, html) {
      return html ? '<details class="session-fold"><summary>' + esc(title) + '</summary>' + html + '</details>' : '';
    };
    var sheetExtras = function(v) {
      return folded(t('teach_session_questions'), v.questions.length ? '<ol>' + v.questions.map(function(q) {
        return '<li><b>' + esc(q.ask) + '</b>' + (q.expect ? '<br><i>' + esc(q.expect) + '</i>' : '') + '</li>';
      }).join('') + '</ol>' : '') + folded(t('teach_session_watch'), v.watch.length ? '<ul>' + v.watch.map(function(w) {
        return '<li><i>' + esc(w.mistake) + '</i> → ' + esc(w.redirect) + '</li>';
      }).join('') + '</ul>' : '') + folded(t('teach_session_spare'), v.spare ? '<p>' + esc(v.spare) + '</p>' : '');
    };
    // The buttons of a step: what it launches on the class screen.
    var actionButton = function(action, st) {
      switch (action) {
        case 'enter':
          return '<button type="button" class="btn btn-main" data-sshow="enter">' + esc(t('teach_session_act_enter')) + '</button>';
        case 'card':
          return '<button type="button" class="btn btn-main" data-scard>' + esc(t('teach_session_act_card')) + '</button>';
        case 'scene':
          return st.sceneid ? '<button type="button" class="btn btn-main" data-sscene="' + st.sceneid + '">' +
            esc(t('teach_session_act_scene', st.scene)) + '</button>' : '';
        case 'battle':
          return '<button type="button" class="btn btn-main" data-sbattle>' + esc(t('teach_session_act_battle')) + '</button>';
        case 'timer':
          return '<button type="button" class="btn btn-main" data-stimer="' + st.timer + '">' +
            esc(t('teach_session_act_timer', st.timer)) + '</button>';
        case 'pacts':
          return '<button type="button" class="btn btn-main" data-sshow="pacts">' + esc(t('teach_session_act_pacts')) + '</button>';
        case 'challenge':
          return '<button type="button" class="btn btn-soft" data-sgoto="challenges">' + esc(t('teach_session_act_challenge')) +
            '</button>';
        case 'light':
          return state.session.open && state.session.open.ready && state.session.open.ready.length ? '<button type="button" ' +
            'class="btn btn-main" data-slight>' + esc(t('teach_session_act_light')) + '</button>' : '';
        case 'game':
          return '<span class="chip">' + esc(t('teach_session_act_game', st.gametitle)) + '</span>';
      }
      return '';
    };
    var steps = function(v, live) {
      return '<ol class="session-steps">' + v.steps.map(function(st, i) {
        var now = live && i === v.step;
        return '<li class="' + (now ? 'now' : '') + '"><div class="session-min">' + st.from + '–' + st.to + ' min</div><div>' +
          '<p>' + esc(st.do) + '</p>' + (st.say ? '<blockquote class="session-say"><small>' + esc(t('teach_session_say')) +
          '</small>' + esc(st.say).replace(/\n/g, '<br>') + '</blockquote>' : '') + (live ? '<div class="actions" ' +
          'style="justify-content:flex-start">' + actionButton(st.action, st) + actionButton(st.also, st) + (now ? '' :
          '<button type="button" class="btn btn-soft" data-sstep="' + i + '">' + esc(t('teach_session_here')) + '</button>') +
          '</div>' : '') + '</div></li>';
      }).join('') + '</ol>';
    };
    var review = function(v) {
      return v.review && v.review.length ? '<div class="session-review"><b>' + esc(t('teach_session_review')) + '</b><ul>' +
        v.review.map(function(r) {
          return '<li>' + esc(r) + '</li>';
        }).join('') + '</ul></div>' : '';
    };
    var draw = function() {
      var s = state.session;
      if (!s) {
        return;
      }
      var v = s.open || s.next;
      if (state.sessionclosing && s.open) {
        box.innerHTML = closing(s.open);
        return;
      }
      if (!v) {
        box.innerHTML = '<div class="kicker">' + esc(t('teach_session_next')) + '</div><p class="empty">' +
          esc(t('teach_session_none')) + '</p>' + other(s);
        return;
      }
      if (s.open) {
        var mins = Math.max(0, Math.floor((v.now - v.startedat) / 60) + Math.floor((Date.now() - state.sessionat) / 60000));
        // A lantern the class has: it waits for the teacher to light it with the class, whatever the step.
        var ready = v.ready && v.ready.length ? '<div class="session-ready"><span>' + esc(t('teach_session_ready',
          v.ready.join(', '))) + '</span><button type="button" class="btn btn-main" data-slight>' +
          esc(t('teach_session_act_light')) + '</button></div>' : '';
        box.innerHTML = '<div class="kicker">' + esc(t('teach_session_now')) + ' · <span data-sclock>' +
          esc(t('teach_session_clock', mins)) + '</span></div><h2>' + esc(name(v)) + '</h2><p class="lead">' + esc(v.title) +
          '</p>' + ready + review(v) + steps(v, true) + sheetExtras(v) + '<div class="actions" style="justify-content:flex-start">' +
          (v.shown ? '<button type="button" class="btn btn-soft" data-sshow="">' + esc(t('teach_session_clear')) + '</button>' :
          '') + '<button type="button" class="btn btn-main" data-sclose>' + esc(t('teach_session_close')) + '</button></div>';
      } else {
        box.innerHTML = '<div class="kicker">' + esc(t('teach_session_next')) + (v.optional ? ' · ' +
          esc(t('teach_session_optional')) : '') + '</div><h2>' + esc(name(v)) + '</h2><p class="lead">' + esc(v.title) + ' · ' +
          esc(t('teach_session_minutes', v.minutes)) + '</p>' + review(v) + folded(t('teach_session_before'), v.before.length ?
          '<ul>' + v.before.map(function(b) {
            return '<li>' + esc(b) + '</li>';
          }).join('') + '</ul>' : '') + folded(t('teach_session_steps'), steps(v, false)) + sheetExtras(v) +
          '<div class="actions" style="justify-content:flex-start"><button type="button" class="btn btn-main" data-sstart="' +
          v.chapter + ':' + v.number + '">' + esc(t('teach_session_start')) + '</button></div>' + other(s);
      }
    };
    // Another session, to start it instead.
    var other = function(s) {
      return can('award') ? '<details class="session-fold"><summary>' + esc(t('teach_session_other')) + '</summary><ul ' +
        'class="list">' + s.list.map(function(x) {
          return '<li class="item"><span class="grow"><b>' + esc(x.chapter === 'entrada' ? t('teach_session_entry') :
            t('teach_session_name', x)) + '</b><small>' + esc(x.title) + (x.done ? ' · ' + esc(t('teach_session_done')) : '') +
            (x.optional ? ' · ' + esc(t('teach_session_optional')) : '') + '</small></span><button type="button" ' +
            'class="btn btn-soft" data-sstart="' + x.chapter + ':' + x.number + '">' + esc(t('teach_session_start')) +
            '</button></li>';
        }).join('') + '</ul></details>' : '';
    };
    // Closing in three steps: the challenge and the commitments (in «Challenges»), and what to review next time.
    var closing = function(v) {
      return '<div class="kicker">' + esc(t('teach_session_close')) + '</div><h2>' + esc(name(v)) + '</h2>' +
        '<p><b>' + esc(t('teach_session_close_challenge')) + '</b></p><p><b>' + esc(t('teach_session_close_pacts')) +
        '</b></p><div class="actions" style="justify-content:flex-start"><button type="button" class="btn btn-soft" ' +
        'data-sgoto="challenges">' + esc(t('teach_session_close_go')) + '</button></div><p><b>' +
        esc(t('teach_session_close_review')) + '</b></p><p class="help-soft">' + esc(t('teach_session_close_review_help')) +
        '</p><ul class="list session-confusions">' + (v.confusions || []).map(function(c, i) {
          return '<li class="item"><label><input type="checkbox" data-sreview value="' + esc(t('teach_session_confusion', c)) +
            '"' + (i < 3 ? ' checked' : '') + '> ' + esc(t('teach_session_confusion', c)) + '</label></li>';
        }).join('') + '</ul><label class="field"><span>' + esc(t('teach_session_close_add')) + '</span><input type="text" ' +
        'maxlength="300" data-sreviewmore></label><div class="actions" style="justify-content:flex-start"><button ' +
        'type="button" class="btn btn-main" data-sclosedone>' + esc(t('teach_session_close_done')) + '</button><button ' +
        'type="button" class="btn btn-soft" data-sclosecancel>' + esc(t('teach_session_cancel')) + '</button></div>';
    };
    box.addEventListener('click', function(ev) {
      var b = ev.target.closest('button');
      if (!b || !box.contains(b)) {
        return;
      }
      var d = b.dataset;
      if (d.sstart) {
        var p = d.sstart.split(':');
        state.sessionclosing = false;
        run('start', {chapter: p[0], number: Number(p[1])});
      } else if (d.sstep) {
        run('step', {step: Number(d.sstep)});
      } else if (d.sshow !== undefined) {
        run('show', {show: d.sshow});
      } else if (d.stimer) {
        run('show', {show: 'timer', minutes: Number(d.stimer)});
      } else if (d.slight !== undefined) {
        run('light');
      } else if (d.sscene) {
        run('show', {show: 'scene', target: Number(d.sscene)});
      } else if (d.scard !== undefined) {
        // The card of the day, drawn here and shown on the class screen.
        T.api('class_event', {courseid: cfg.courseid, action: 'draw'}).catch(function() {
          return null;
        }).then(function() {
          return run('show', {show: 'card'});
        });
      } else if (d.sbattle !== undefined) {
        // As the command starts it: with the devices the course plays with, by turns, eight knots and thirty seconds.
        var final = !!(state.session.open && state.session.open.final);
        T.api('live_host', {courseid: cfg.courseid, action: 'start', mode: state.data.region.devices === false ? 'aloud' :
          'phones', count: 8, secs: 30, turns: true, final: final}).then(function() {
          T.toast(esc(t('teach_session_act_battle')));
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      } else if (d.sgoto) {
        state.tab = d.sgoto;
        render();
      } else if (d.sclose !== undefined) {
        state.sessionclosing = true;
        draw();
      } else if (d.sclosecancel !== undefined) {
        state.sessionclosing = false;
        draw();
      } else if (d.sclosedone !== undefined) {
        var review = Array.prototype.map.call(box.querySelectorAll('[data-sreview]:checked'), function(x) {
          return x.value;
        });
        var more = box.querySelector('[data-sreviewmore]').value.trim();
        if (more) {
          review.push(more);
        }
        state.sessionclosing = false;
        run('close', {review: review}).then(function() {
          T.toast(esc(t('teach_session_closed')));
        });
      }
    });
    if (state.session) {
      draw();
    }
    // Asked again only when it is old: drawing the panel again after a tap does not ask for it.
    if (!state.session || Date.now() - (state.sessionat || 0) > 30000) {
      T.api('run_session', {courseid: cfg.courseid, action: 'view'}).then(function(r) {
        state.session = JSON.parse(r.json);
        state.sessionat = Date.now();
        draw();
      }).catch(function() {
        box.remove();
      });
    }
    // The clock of the session, discreet: once a minute.
    clearInterval(state.sessiontimer);
    state.sessiontimer = setInterval(function() {
      var clock = box.querySelector('[data-sclock]');
      var v = state.session && state.session.open;
      if (!clock || !v || !document.body.contains(box)) {
        return;
      }
      clock.textContent = t('teach_session_clock', Math.max(0, Math.floor((v.now - v.startedat) / 60) +
        Math.floor((Date.now() - state.sessionat) / 60000)));
    }, 30000);
  }

  function remoteCard() {
    return can('runclassscreen') ? '<section class="card remote-card"><div class="kicker">' + esc(t('teach_command')) +
      '</div><div data-remote-body><p class="help-soft">' + esc(t('teach_command_loading')) + '</p></div></section>' : '';
  }

  function bindRemote(el) {
    var box = el.querySelector('[data-remote-body]');
    if (!box) {
      return;
    }
    var host = function(action, extra) {
      return T.api('live_host', Object.assign({courseid: cfg.courseid, action: action}, extra || {}));
    };
    var drawn = '';
    var draw = function(l) {
      // Drawn again only when something seen changes: a tap is never lost while the buttons are replaced.
      var sig = JSON.stringify(l ? Object.assign({}, l, {left: 0, stageleft: 0, answered: 0}) : null);
      if (sig === drawn) {
        return;
      }
      drawn = sig;
      var buttons = [];
      var line = '';
      if (!l || (l.outcome && l.kind === 'battle')) {
        line = l && l.outcome ? t('board_battle_' + l.outcome) : t('teach_command_none');
        buttons = [['start', 'teach_command_start'], ['startfinal', 'teach_command_final']];
      } else if (l.kind !== 'battle') {
        line = t('teach_command_vote');
      } else if (!l.round) {
        line = t('board_battle_joined', l.joined);
        buttons = [['next', l.maxcandles ? 'board_turn_first' : 'board_battle_first'], ['finish', 'board_battle_cancel']];
      } else {
        // The same buttons as the class screen at each moment: while the Embrollo speaks and the roles get ready, only
        // to end it; while it is played, to show the answer; once resolved, the next one.
        line = t(l.stage ? 'board_turn_round' : 'board_battle_round', l) + (l.maxcandles ? ' · ' +
          t('board_turn_candles', {lit: l.candles, all: l.maxcandles}) + ' · ' + t('board_turn_threads') + ': ' + l.hp +
          ' / ' + l.maxhp : '');
        var going = l.round < l.total && (!l.maxcandles || (l.candles > 0 && l.hp > 0));
        buttons = (l.stage === 'intent' || l.stage === 'prep' ? [] : l.revealed ? (going ? [['next', l.stage ?
          'board_turn_nextnow' : 'board_battle_next']] : []) : [['reveal', 'board_battle_reveal']]).concat([['pause',
          'board_battle_pause'], ['finish', 'board_battle_finish']]);
        if (l.paused) {
          line = t('board_battle_paused') + ' · ' + line;
          buttons = [['resume', 'board_battle_resume'], ['finish', 'board_battle_finish']];
        }
      }
      // Aloud, the teacher is who says whether each team got it: from here too.
      var marks = l && l.mode === 'aloud' && l.revealed && !l.outcome ? '<ul class="list remote-marks">' +
        l.teams.map(function(team) {
          return '<li><b>' + esc(team.name) + '</b><span class="pills"><button type="button" class="chip" data-remotemark="' +
            team.id + ':1" aria-pressed="' + (team.mark === 1) + '" aria-label="' + esc(t('board_battle_markright', team.name)) +
            '">✓</button><button type="button" class="chip" data-remotemark="' + team.id + ':0" aria-pressed="' +
            (team.mark === 0) + '" aria-label="' + esc(t('board_battle_markwrong', team.name)) + '">✗</button></span></li>';
        }).join('') + '</ul>' : '';
      // By turns, how each team is, to referee: who it goes for, who is tangled, who undid their lane, the verbs used.
      var how = l && l.maxcandles && l.round && !l.outcome ? '<ul class="list remote-teams">' + l.teams.map(function(team) {
        var lane = (l.lanes || []).filter(function(x) {
          return x.teamid === team.id;
        })[0];
        var verbs = (l.tokens || []).filter(function(k) {
          return k.team === team.name;
        }).length;
        var tags = (l.targetid === team.id ? [t('teach_command_target')] : []).concat((l.tangled || []).indexOf(team.id) >= 0 ?
          [t('teach_command_tangled')] : []).concat(l.revealed && lane && lane.done ? [t('teach_command_undone')] : []).concat(
          verbs ? [t('teach_command_verbs', verbs)] : []);
        return '<li><b>' + esc(team.name) + '</b><span>' + tags.map(function(x) {
          return '<span class="tag">' + esc(x) + '</span>';
        }).join(' ') + '</span></li>';
      }).join('') + '</ul>' : '';
      // As on the class screen: with tablets, the first turn waits until somebody is in.
      var empty = l && !l.round && l.mode === 'phones' && !l.joined;
      box.innerHTML = '<p>' + esc(line) + '</p>' + how + marks + '<div class="actions" style="justify-content:flex-start">' +
        buttons.map(function(b, i) {
          return '<button type="button" class="btn ' + (i ? 'btn-soft' : 'btn-main') + '" data-remote="' + b[0] + '"' +
            (empty && b[0] === 'next' ? ' disabled' : '') + '>' + esc(t(b[1])) + '</button>';
        }).join('') + '</div>';
      box.querySelectorAll('[data-remotemark]').forEach(function(b) {
        b.onclick = function() {
          var p = b.dataset.remotemark.split(':');
          host('mark', {groupid: +p[0], right: p[1] === '1'}).then(function(r) {
            draw(r.live || null);
          }).catch(function(e) {
            T.toast(esc(e.message), true);
          });
        };
      });
      box.querySelectorAll('[data-remote]').forEach(function(b) {
        b.onclick = function() {
          var what = b.dataset.remote;
          if (what === 'finish' && !window.confirm(t('teach_command_finishsure'))) {
            return;
          }
          b.disabled = true;
          // Launched with the devices the course plays with, by turns, eight knots and thirty seconds for each lane.
          var start = {mode: state.data.region.devices === false ? 'aloud' : 'phones', count: 8, secs: 30, turns: true};
          var call = what === 'start' || what === 'startfinal' ? host('start', Object.assign(start, {final: what === 'startfinal'})) :
            host(what, {round: l ? l.round : -1});
          call.then(function(r) {
            draw(r.live || null);
          }).catch(function(e) {
            b.disabled = false;
            drawn = '';
            T.toast(esc(e.message), true);
          });
        };
      });
    };
    var poll = function() {
      host('state').then(function(r) {
        if (document.body.contains(box)) {
          draw(r.live || null);
        }
      }).catch(function() {
        // The next poll tries again.
      });
    };
    poll();
    state.remotetimer = setInterval(poll, 4000);
  }

  function tabClass() {
    var d = state.data;
    var teams = '<div class="pills" style="justify-content:flex-start;margin-bottom:12px"><button class="chip" ' +
      'data-team="0" aria-pressed="' + (!state.team) + '">' + esc(t('teach_everyone')) + '</button>' +
      d.teams.map(function(g) {
        return '<button class="chip" data-team="' + g.id + '" aria-pressed="' + (state.team === g.id) + '">' + esc(g.name) +
          (g.state ? ' <span class="team-state-dot state-' + g.state + '" title="' + esc(t('app_teamstate_' + g.state)) +
          '" aria-label="' + esc(t('app_teamstate_' + g.state)) + '"></span>' : '') + '</button>';
      }).join('') + '</div>';
    var shown = studentsShown();
    var el = content(firstCard() + sessionCard() + remoteCard() + inboxCard() + todayCard() + teamsCard() + farCard() + '<section class="card"><div class="section-title"><h2>' +
      esc(t('teach_tab_class')) +
      '</h2><span class="section-tools"><label class="chip"><input type="checkbox" data-showres' + (state.showres ? ' checked' :
      '') + '> ' + esc(t('teach_showres')) + '</label><button class="btn btn-soft" data-all>' + esc(t('teach_selectall')) +
      '</button></span></div>' + teams +
      (shown.length ? '<ul class="list">' + shown.map(studentCard).join('') + '</ul>' : '<p class="empty">' +
      esc(t('teach_nostudents')) + '</p>') + '</section><div id="actionbar"></div>');
    bindMakeTeams(el);
    bindSession(el);
    bindRemote(el);
    el.querySelector('[data-showres]').onchange = function() {
      state.showres = this.checked;
      try {
        window.localStorage.setItem('tesape-showres-' + cfg.userid, state.showres ? '1' : '');
      } catch (e) {
        // Without storage it is asked again next time.
      }
      render();
    };
    el.querySelectorAll('[data-erasestain]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        T.api('erase_stain', {courseid: cfg.courseid, stainid: Number(b.dataset.erasestain)}).then(function() {
          b.closest('li').remove();
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
    el.querySelectorAll('[data-goto]').forEach(function(b) {
      b.onclick = function() {
        state.tab = b.dataset.goto;
        state.open = 0;
        render();
      };
    });
    bindInbox(el.querySelector('[data-inbox]'));
    bindFirstCard(el);
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
   * A student of the class by their user.
   *
   * @param {number} userid
   * @return {Object|undefined}
   */
  function studentOf(userid) {
    return state.data.students.filter(function(s) {
      return s.userid === userid;
    })[0];
  }

  /**
   * The saber of the teams this week, after some was given: only the card of the teams is drawn again, and quietly.
   */
  function refreshStandings() {
    T.api('get_class', {courseid: cfg.courseid}).then(function(data) {
      state.data.standings = data.standings;
      var old = app.querySelector('[data-teamscard]');
      if (old && data.standings.length) {
        old.outerHTML = teamsCard();
      }
    }).catch(function() {
      // The next visit reads it.
    });
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
    return '<section class="card" data-teamscard style="margin-bottom:16px"><div class="section-title"><h2>' +
      esc(t('teach_teams_week')) +
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
    var reasons = state.data.region.reasons || [];
    bar.innerHTML = '<section class="card no-print" style="position:sticky;bottom:84px;margin-top:14px;border-color:' +
      'var(--lantern)"><b>' + esc(t('teach_selected', ids.length)) + '</b>' + (reasons.length ? '<div class="pills ' +
      'touch-chips" style="justify-content:flex-start;margin-top:10px">' + reasons.map(function(r, i) {
        return '<button class="pill" data-touch="' + i + '">' + esc(r.text) + ' <small>+' + r.amount + ' ' +
          esc(t(r.resource === 'gems' && r.amount === 1 ? 'app_gem' : 'app_' + r.resource)) + '</small></button>';
      }).join('') + '</div>' : '') + '<div class="pills adapt-profiles" style="justify-content:flex-start;margin-top:10px">' +
      (reasons.length ? '<span class="help-soft">' + esc(t('teach_touch_none')) + '</span>' : '') + amounts.map(function(a) {
        return '<button class="pill" data-award="saber" data-amount="' + a + '">+' + a + ' ' + esc(t('app_saber')) +
          '</button>';
      }).join('') + '<button class="pill" data-award="gems" data-amount="1">' + esc(t('app_encounter_gem')) + '</button>' +
      '<button class="pill" data-award="breath" data-amount="1">+1 ' + esc(t('app_breath')) + '</button>' +
      // A commitment not kept costs the same here as in «Challenges» (LOSS in pacts.php).
      '<button class="pill" data-award="energy" data-amount="-2" title="' + esc(t('teach_commitment_help')) + '">−2 ' +
      esc(t('app_energy')) + '</button></div><div class="pills adapt-profiles" style="justify-content:flex-start;margin-top:8px">' +
      '<span class="help-soft">' + esc(t('teach_adapt_profiles_many')) + '</span>' + profileChips('data-adaptmany') +
      '</div></section>';
    // A profile of one touch for all those selected: what it marks is added to what each one has.
    bar.querySelectorAll('[data-adaptmany]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        T.api('set_adapt', {courseid: cfg.courseid, userids: ids, profile: b.dataset.adaptmany}).then(function(r) {
          b.disabled = false;
          (r.students || []).forEach(function(x) {
            var s = studentOf(x.userid);
            if (s && s.character && s.character.member) {
              s.character.member.adaptteacher = x.adapt;
            }
          });
          var none = ids.length - (r.students || []).length;
          T.toast(esc(t('teach_adapt_done', (r.students || []).length)) + (none ? ' · ' + esc(t('teach_adapt_nochar', none)) :
            ''));
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
    bar.querySelectorAll('[data-award]').forEach(function(b) {
      b.onclick = function() {
        // A second tap while the first is on its way would give it twice.
        bar.querySelectorAll('[data-award], [data-touch]').forEach(function(x) {
          x.disabled = true;
        });
        award(ids, b.dataset.award, Number(b.dataset.amount)).then(function() {
          bar.querySelectorAll('[data-award], [data-touch]').forEach(function(x) {
            x.disabled = false;
          });
        });
      };
    });
    // A touch with a reason: the student sees why on their tablet.
    bar.querySelectorAll('[data-touch]').forEach(function(b) {
      b.onclick = function() {
        var r = reasons[Number(b.dataset.touch)];
        bar.querySelectorAll('[data-award], [data-touch]').forEach(function(x) {
          x.disabled = true;
        });
        award(ids, r.resource, r.amount, r.text).then(function() {
          bar.querySelectorAll('[data-award], [data-touch]').forEach(function(x) {
            x.disabled = false;
          });
        });
      };
    });
  }

  function award(ids, resource, amount, note) {
    return T.api('award', {courseid: cfg.courseid, userids: ids, resource: resource, amount: amount,
      note: note || ''}).then(function(results) {
      var given = results.filter(function(r) {
        return r.status === 'given';
      }).length;
      var other = results.length - given;
      T.toast(esc(t('teach_given', given)) + (other ? ' · ' + esc(t('teach_notgiven', other)) : ''), given === 0);
      // What changed, drawn at once from the answer: the class is not read again.
      results.forEach(function(r) {
        var s = studentOf(r.userid);
        if (r.status !== 'given' || r.level === undefined || !s || !s.character) {
          return;
        }
        s.character.level = r.level;
        if (s.character.member) {
          s.character.member.energy = r.energy;
          s.character.member.breath = r.breath;
          s.character.member.status = r.state;
        }
      });
      state.selected = {};
      render();
      if (resource === 'saber' && state.data.standings.length) {
        refreshStandings();
      }
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
    if (can('manageregion')) {
      // The role, when the student asks to change it: they keep their level and everything they have.
      html += '<h3 style="margin-top:16px">' + esc(t('teach_role')) + '</h3><p class="help-soft">' + esc(t('teach_role_help')) +
        '</p><div class="pills" style="justify-content:flex-start">' + T.ROLES.map(function(r) {
          return '<button class="chip" data-role="' + r + '" aria-pressed="' + (c.role === r) + '">' +
            esc(T.roleName(fam, r, c.nameform)) + '</button>';
        }).join('') + '</div>';
    }
    html += '<h3 style="margin-top:16px">' + esc(t('teach_step')) + '</h3><div class="pills" style="justify-content:' +
      'flex-start">' + [1, 2, 3, 4].map(function(st) {
        return '<button class="chip" data-step="' + st + '" aria-pressed="' + (m && m.step === st) + '"' +
          (can('manageregion') ? '' : ' disabled') + '>' + esc(t('teach_step_' + st)) + '</button>';
      }).join('') + '</div>';
    // What the game adapts for them: what they need, never a diagnosis; saved as soon as it is marked.
    var adapted = (m && (m.adaptteacher || m.adapt)) || [];
    var own = (m && m.adaptown) || [];
    html += '<h3 style="margin-top:16px">' + esc(t('teach_adapt')) + '</h3><p class="help-soft">' + esc(t('teach_adapt_help')) +
      '</p>' + (can('award') && m ? '<div class="pills adapt-profiles"><span class="help-soft">' + esc(t('teach_adapt_profiles')) +
      '</span>' + profileChips('data-adaptprofile') + '</div>' : '') + '<div class="adapt-options">' + ADAPT.map(function(code) {
        return '<label class="chip"><input type="checkbox" data-adapt="' + code + '"' + (adapted.indexOf(code) >= 0 ?
          ' checked' : '') + (can('award') ? '' : ' disabled') + '> <span><b>' + esc(t('teach_adapt_' + code)) + '</b><small>' +
          esc(t('teach_adapt_' + code + '_help')) + '</small></span></label>';
      }).join('') + '</div>' + (own.length ? '<p class="help-soft">' + esc(t('teach_adapt_own', own.map(function(code) {
        return t('teach_adapt_' + code);
      }).join(', '))) + '</p>' : '');
    // Their talents, chosen as they grew: a teacher lets them choose again if they regret it.
    var talents = (c && c.talents) || [];
    if (talents.length) {
      html += '<h3 style="margin-top:16px">' + esc(t('teach_talents')) + '</h3><ul class="talent-medals">' +
        talents.map(function(x) {
          return '<li><b>' + esc(x.name) + '</b><small>' + esc(x.description) + '</small></li>';
        }).join('') + '</ul>' + (can('award') ? '<button type="button" class="btn btn-soft" data-resettalents>' +
        esc(t('teach_talents_reset')) + '</button>' : '');
    }
    // Far from the expedition: when they came last, and whether they follow it from home or hospital.
    html += '<h3 style="margin-top:16px">' + esc(t('teach_far')) + '</h3><p class="help-soft">' + esc(lastSeen(m)) + '</p>' +
      '<label class="chip" style="display:inline-flex;gap:8px;align-items:center"><input type="checkbox" data-remote' +
      (m && m.remote ? ' checked' : '') + (can('award') ? '' : ' disabled') + '> ' + esc(t('teach_remote')) + '</label>' +
      (m && m.remote ? ' <label class="chip" style="display:inline-flex;gap:8px;align-items:center"><input type="checkbox" ' +
      'data-remoteshown' + (m.remoteshown ? ' checked' : '') + (can('award') ? '' : ' disabled') + '> ' +
      esc(t('teach_remote_shown')) + '</label>' : '') +
      '<p class="help-soft" style="margin-top:6px">' + esc(t('teach_remote_help')) + '</p>' +
      (m && m.remote ? '<p class="help-soft">' + esc(t('teach_remote_shown_help')) + '</p>' : '') + teamplaysList(s) +
      '<div class="actions" style="justify-content:flex-start;margin-top:16px">';
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
    el.querySelectorAll('[data-adapt]').forEach(function(box) {
      box.onchange = function() {
        var list = Array.prototype.map.call(el.querySelectorAll('[data-adapt]:checked'), function(b) {
          return b.dataset.adapt;
        });
        T.api('set_adapt', {courseid: cfg.courseid, userid: userid, adapt: list}).then(function(r) {
          T.toast(esc(t('app_saved')));
          // The sheet stays open, to mark the next one.
          if (m) {
            m.adaptteacher = r && r.adapt ? r.adapt : list;
          }
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
    var resetTalents = el.querySelector('[data-resettalents]');
    if (resetTalents) {
      resetTalents.onclick = function() {
        if (!window.confirm(t('teach_talents_reset_confirm'))) {
          return;
        }
        resetTalents.disabled = true;
        T.api('reset_talents', {courseid: cfg.courseid, userid: userid}).then(function() {
          T.toast(esc(t('app_saved')));
          return load(true);
        }).then(function() {
          openStudent(userid);
        }).catch(function(e) {
          resetTalents.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    }
    el.querySelectorAll('[data-adaptprofile]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        T.api('set_adapt', {courseid: cfg.courseid, userid: userid, profile: b.dataset.adaptprofile}).then(function(r) {
          b.disabled = false;
          T.toast(esc(t('app_saved')));
          m.adaptteacher = r.adapt;
          el.querySelectorAll('[data-adapt]').forEach(function(box) {
            box.checked = r.adapt.indexOf(box.dataset.adapt) >= 0;
          });
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
    el.querySelectorAll('[data-role]').forEach(function(b) {
      b.onclick = function() {
        if (b.getAttribute('aria-pressed') === 'true') {
          return;
        }
        T.api('set_role', {courseid: cfg.courseid, userid: userid, role: b.dataset.role}).then(function() {
          T.toast(esc(t('app_saved')));
          return load(true);
        }).then(function() {
          openStudent(userid);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
    el.querySelectorAll('[data-undoteamplay]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        T.api('undo_teamplay', {courseid: cfg.courseid, attemptid: Number(b.dataset.undoteamplay)}).then(function() {
          T.toast(esc(t('teach_teamplay_undone')));
          return load(true);
        }).then(function() {
          openStudent(userid);
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
    var shown = el.querySelector('[data-remoteshown]');
    if (shown) {
      shown.onchange = function() {
        T.api('set_remote', {courseid: cfg.courseid, userid: userid, remote: true, shown: shown.checked}).then(function() {
          T.toast(esc(t('app_saved')));
          if (m) {
            m.remoteshown = shown.checked;
          }
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    }
    var remote = el.querySelector('[data-remote]');
    if (remote) {
      remote.onchange = function() {
        T.api('set_remote', {courseid: cfg.courseid, userid: userid, remote: remote.checked}).then(function() {
          T.toast(esc(t('app_saved')));
          return load(true);
        }).then(function() {
          openStudent(userid);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    }
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

  /**
   * The team plays a classmate made for a student on their device, which the teacher can take back if they were not
   * there.
   *
   * @param {Object} s The student.
   * @return {string}
   */
  function teamplaysList(s) {
    var list = s.teamplays || [];
    if (!list.length) {
      return '';
    }
    return '<h3 style="margin-top:16px">' + esc(t('teach_teamplays')) + '</h3><p class="help-soft">' +
      esc(t('teach_teamplays_help')) + '</p><ul class="list">' + list.map(function(p) {
        return '<li class="item"><span class="grow"><b>' + esc(p.scene) + '</b><small>' + esc(t('teach_teamplay_by',
          {by: p.by || '—', time: when(p.time)})) + (p.decisions ? ' · ' + esc(t('teach_teamplay_decided',
          {decided: p.decided, decisions: p.decisions})) : '') + '</small></span>' + (can('undo') ? '<button class="btn btn-soft" ' +
          'data-undoteamplay="' + p.id + '">' + esc(t('teach_teamplay_undo')) + '</button>' : '') + '</li>';
      }).join('') + '</ul>';
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
      }).join('') + '</ul>' : '<p class="empty">' + esc(t('teach_ideas_none')) + '</p>') + '</section>' +
      // The ideas of the teacher for Oksigenia, where the site has suggestions on: they go from the server.
      (cfg.suggest ? '<section class="card suggest"><h2>' + esc(t('teach_suggest_title')) + '</h2><p style="color:' +
      'var(--text-soft)">' + esc(t('teach_suggest_help')) + '</p><label style="display:block;margin-bottom:12px">' +
      '<span class="sr-only">' + esc(t('teach_suggest_title')) + '</span><textarea data-suggest-text rows="4" ' +
      'maxlength="3500"></textarea></label>' + field('email', t('teach_suggest_email'), '<input type="email" ' +
      'data-suggest-email autocomplete="email">') + '<div class="actions"><button class="btn btn-main" data-suggest>' +
      esc(t('teach_suggest_send')) + '</button></div></section>' : ''));
    var send = el.querySelector('[data-suggest]');
    if (send) {
      send.onclick = function() {
        var text = el.querySelector('[data-suggest-text]').value.trim();
        if (!text) {
          el.querySelector('[data-suggest-text]').focus();
          return;
        }
        send.disabled = true;
        T.api('send_suggestion', {courseid: cfg.courseid, text: text, email: el.querySelector('[data-suggest-email]').value})
          .then(function() {
            el.querySelector('[data-suggest-text]').value = '';
            T.toast(esc(t('teach_suggest_sent')));
          }).catch(function(e) {
            T.toast(esc(e.message), true);
          }).then(function() {
            send.disabled = false;
          });
      };
    }
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

  /**
   * The competencies: which competency of Moodle each objective of the mission leaves evidence in, and the grid of the
   * class to rate each student with a tap, with the share right at the first try as a clue.
   */
  function tabCompetencies() {
    if (!state.comp) {
      content('<section class="card"><p class="empty">' + esc(t('app_loading')) + '</p></section>');
      T.api('get_competencies', {courseid: cfg.courseid}).then(function(r) {
        state.comp = r;
        if (state.tab === 'competencies') {
          tabCompetencies();
        }
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
      return;
    }
    var c = state.comp;
    if (!c.enabled) {
      content('<section class="card"><h2>' + esc(t('teach_tab_competencies')) + '</h2><p class="empty">' +
        esc(t('teach_comp_off')) + '</p></section>');
      return;
    }
    var editable = can('manageregion');
    var mapping = c.objectives.map(function(o) {
      var options = '<option value="0">' + esc(t('teach_comp_none')) + '</option>' + c.options.map(function(x) {
        return '<option value="' + x.id + '"' + (x.id === o.competencyid ? ' selected' : '') + '>' + esc(x.framework + ' · ' +
          x.name) + '</option>';
      }).join('');
      return '<li class="item"><span class="grow"><b>' + esc(o.code) + '</b><small>' + esc(o.name) + '</small></span>' +
        '<select data-compobj="' + esc(o.code) + '" aria-label="' + esc(o.code + ' · ' + o.name) + '"' + (editable ? '' :
        ' disabled') + '>' + options + '</select></li>';
    }).join('');
    // The suggestions of a student not rated yet: what «Confirm» sends.
    var pending = function(st) {
      return st.cells.filter(function(cell, i) {
        return cell.suggested && !cell.rating && c.objectives[i].competencyid;
      }).map(function(cell) {
        return {userid: st.userid, objective: cell.objective, grade: cell.suggested};
      });
    };
    var all = [];
    c.students.forEach(function(st) {
      all = all.concat(pending(st));
    });
    var head = '<th>' + esc(t('teach_comp_student')) + '</th>' + c.objectives.map(function(o) {
      return '<th title="' + esc(o.name) + '">' + esc(o.code) + '</th>';
    }).join('') + (all.length ? '<th>' + esc(t('teach_comp_suggestions')) + '</th>' : '');
    var rows = c.students.map(function(st) {
      var mine = pending(st);
      return '<tr><th>' + esc(st.fullname) + '</th>' + st.cells.map(function(cell, i) {
        var o = c.objectives[i];
        if (!o.competencyid) {
          return '<td class="comp-none">—</td>';
        }
        var levels = '<option value="0"' + (cell.rating ? '' : ' selected') + ' disabled>·</option>' +
          o.levels.map(function(level, n) {
            return '<option value="' + (n + 1) + '"' + (cell.rating === n + 1 ? ' selected' : '') + '>' + esc(level) +
              '</option>';
          }).join('');
        // How the answers came, beside the share: alone at the first try · with help · after the consequence.
        var marks = cell.percent < 0 ? '' : '<span class="comp-marks" title="' + esc(t('teach_comp_marks', cell)) + '">' +
          '<span aria-hidden="true">' + cell.first + ' · ' + cell.helped + ' · ' + cell.after + (cell.twice ? ' · ' +
          cell.twice + '✗' : '') + '</span><span class="sr-only">' + esc(t('teach_comp_marks', cell)) + '</span></span>';
        return '<td><small>' + (cell.percent < 0 ? '—' : cell.percent + ' %') + '</small>' + marks + '<select data-rate="' +
          st.userid + ':' + esc(o.code) + '" aria-label="' + esc(st.fullname + ' · ' + o.code) + '">' + levels +
          '</select>' + (cell.suggested && !cell.rating ? '<small class="comp-suggested">' + esc(t('teach_comp_suggested',
          o.levels[cell.suggested - 1] || cell.suggested)) + '</small>' : '') + '</td>';
      }).join('') + (all.length ? '<td>' + (mine.length ? '<button type="button" class="chip" data-confirmrow="' +
        st.userid + '">' + esc(t('teach_comp_confirm_row')) + '</button>' : '') + '</td>' : '') + '</tr>';
    }).join('');
    var el = content('<section class="card"><h2>' + esc(t('teach_tab_competencies')) + '</h2><p style="color:' +
      'var(--text-soft)">' + esc(t('teach_comp_help')) + '</p><p class="help-soft">' + esc(t('teach_comp_marks_help')) +
      '</p>' + (all.length ? '<div class="actions" style="justify-content:flex-start"><button type="button" class="btn ' +
      'btn-soft" data-confirmall>' + esc(t('teach_comp_confirm_all', all.length)) + '</button></div>' : '') +
      '<div class="comp-grid"><table><thead><tr>' + head +
      '</tr></thead><tbody>' + rows + '</tbody></table></div></section><section class="card" style="margin-top:16px">' +
      '<h2>' + esc(t('teach_comp_links')) + '</h2><p style="color:var(--text-soft)">' + esc(t('teach_comp_links_help')) +
      '</p><ul class="list">' + mapping + '</ul></section>');
    el.querySelectorAll('[data-compobj]').forEach(function(sel) {
      sel.onchange = function() {
        T.api('set_competency', {courseid: cfg.courseid, objective: sel.dataset.compobj,
          competencyid: Number(sel.value)}).then(function(r) {
          state.comp = r;
          T.toast(esc(t('app_saved')));
          tabCompetencies();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
    // Confirming takes the suggestions as they are, only where the teacher has not rated yet.
    var confirm = function(list, button) {
      button.disabled = true;
      T.api('rate_competency', {courseid: cfg.courseid, ratings: list}).then(function() {
        list.forEach(function(x) {
          c.students.forEach(function(st) {
            st.cells.forEach(function(cell) {
              if (st.userid === x.userid && cell.objective === x.objective) {
                cell.rating = x.grade;
              }
            });
          });
        });
        T.toast(esc(t('teach_comp_confirmed', list.length)));
        tabCompetencies();
      }).catch(function(e) {
        button.disabled = false;
        T.toast(esc(e.message), true);
      });
    };
    var everything = el.querySelector('[data-confirmall]');
    if (everything) {
      everything.onclick = function() {
        confirm(all, everything);
      };
    }
    el.querySelectorAll('[data-confirmrow]').forEach(function(b) {
      b.onclick = function() {
        confirm(pending(c.students.filter(function(st) {
          return st.userid === Number(b.dataset.confirmrow);
        })[0]), b);
      };
    });
    el.querySelectorAll('[data-rate]').forEach(function(sel) {
      sel.onchange = function() {
        var parts = sel.dataset.rate.split(':');
        T.api('rate_competency', {courseid: cfg.courseid, userid: Number(parts[0]), objective: parts[1],
          grade: Number(sel.value)}).then(function() {
          sel.closest('td').classList.add('rated');
          // Rated by hand: its suggestion is no longer waiting.
          c.students.forEach(function(st) {
            st.cells.forEach(function(cell) {
              if (st.userid === Number(parts[0]) && cell.objective === parts[1]) {
                cell.rating = Number(sel.value);
              }
            });
          });
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
  }

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
        esc(t('teach_mission_none')) + '</p></section>' + m.packages.map(packageCard).join('') + authorCard();
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
        esc(t('teach_mission_ages', pkg.ages)) + '</p>' + rights(pkg) + (can('manageregion') ? packageForm(pkg, m.installed) :
        '') : '') +
        '</section>' + workshopCard(m.installed) + authorCard() + reportCard() + nextClass() + games() + results() +
        expeditionsCard(m.expeditions) +
        (others.length && can('manageregion') ? '<section class="card" style="margin-top:16px"><h2>' +
        esc(t('teach_expedition_new')) + '</h2><p style="color:var(--text-soft)">' + esc(t('teach_expedition_new_help')) +
        '</p></section>' + others.map(packageCard).join('') : '');
    }
    var el = content(html);
    bindNextClass(el);
    var author = el.querySelector('[data-authorcard]');
    if (author) {
      T.author.card(author, function(code) {
        T.author.open(content(''), code, function() {
          // The mission of the course may be another one now.
          state.missions = null;
          state.results = null;
          tabMission();
        });
      });
    }
    var workshop = el.querySelector('[data-workshop]');
    if (workshop) {
      workshop.onclick = function() {
        T.workshop.open(content(''), function() {
          // Titles and texts may have changed: the results are asked again.
          state.results = null;
          tabMission();
        });
      };
    }
    bindInstall(el, tabMission);
  }

  /**
   * The forms that install a mission in the course.
   *
   * @param {Element} el
   * @param {Function} done What to draw once installed.
   */
  function bindInstall(el, done) {
    el.querySelectorAll('form[data-install]').forEach(function(f) {
      f.onsubmit = function(ev) {
        ev.preventDefault();
        var skin = f.querySelector('[name="skin"]:checked');
        var variant = f.querySelector('[name="variant"]:checked');
        var acts = f.querySelector('[name="activities"]');
        var button = f.querySelector('[type="submit"]');
        button.disabled = true;
        T.api('install_mission', {courseid: cfg.courseid, code: f.dataset.install, skin: skin.value,
          variant: variant.value, activities: !!(acts && acts.checked)}).then(function(missions) {
          state.missions = missions;
          state.results = null;
          T.toast(esc(t('app_saved')));
          done();
        }).catch(function(e) {
          button.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
  }

  /**
   * The way into the workshop, where the teacher changes the texts and the questions of the mission of the course. Not
   * for a mission the teacher made: that one is changed where it was made.
   *
   * @param {Object} installed
   * @return {string}
   */
  function workshopCard(installed) {
    if (!can('manageregion') || /^own[0-9]+$/.test(installed.code || '')) {
      return '';
    }
    return '<section class="card" style="margin-top:16px"><h2>' + esc(t('teach_ws_title')) + '</h2><p style="color:' +
      'var(--text-soft)">' + esc(t('teach_ws_help')) + '</p><div class="actions" style="justify-content:flex-start">' +
      '<button class="btn btn-main" data-workshop>' + esc(t('teach_ws_open')) + '</button></div></section>';
  }

  /**
   * The place of the missions the teacher makes from scratch; it fills itself (author.js).
   *
   * @return {string}
   */
  function authorCard() {
    return can('manageregion') ? '<section class="card" data-authorcard style="margin-top:16px"></section>' : '';
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
      esc(t('teach_mission_lang', p.lang)) : '') + '</p>' + rights(p) + (can('manageregion') ? packageForm(p, null) : '') +
      '</section>';
  }

  /**
   * The licences of a mission, to choose from, with a link to read each one.
   *
   * @param {Object} p A package.
   * @return {string}
   */
  function rights(p) {
    if (!p.rights || !p.rights.length) {
      return '';
    }
    var list = p.rights.map(function(r) {
      return r.url ? '<a href="' + esc(r.url) + '" target="_blank" rel="noopener">' + esc(r.name) + '</a>' : esc(r.name);
    }).join(' ' + esc(t('teach_mission_or')) + ' ');
    return '<p class="rights">' + esc(t('teach_mission_rights')).split('{$a}').join(list) + '</p>';
  }

  /**
   * The plan for the next class, made by the AI of the site from what confuses the class.
   *
   * @return {string}
   */
  function nextClass() {
    var n = state.nextclass;
    // Whoever cannot use the AI still sees the last plan of the class, if there is one.
    if (!n || !(n.canuse || n.plan) || !state.results || !state.results.chapters.length) {
      return '';
    }
    var head = '<section class="card next-class" style="margin-top:16px"><div class="kicker">' + esc(t('teach_next')) +
      '</div><h2>' + esc(t('teach_next_title')) + '</h2>';
    if (n.canuse && !n.policy) {
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
    if (!n.canuse) {
      return head + plan + '<small class="help-soft" style="display:block">' + esc(t('teach_next_readonly')) + '</small></section>';
    }
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
            // The decisions without a right answer: what the class chose, not how many got it right.
            var dilemmas = (sc.dilemmas || []).map(function(d) {
              return '<div class="note"><b>' + esc(t('teach_dilemma')) + '</b><br><small>' + esc(d.question) + '</small><br>' +
                d.options.map(function(o) {
                  return esc(o.text) + ' (' + o.count + ')';
                }).join(' · ') + '</div>';
            }).join('');
            return '<div class="item" style="display:block"><b>' + esc(sc.title) + '</b><small style="display:block;color:' +
              'var(--text-soft)">' + esc(t('teach_scene_stats', sc.players + ' · ' + sc.done + ' · ' + sc.attempts)) +
              '</small>' + (sc.attempts ? objectives + confusions + dilemmas :
              '<small style="display:block;color:var(--text-soft)">' +
              esc(t('teach_results_none')) + '</small>') + '</div>';
          }).join('') + '</div>';
      }).join('') + hanging(r.knots || []) + '</section>';
  }

  /**
   * The knots the class left hanging in the battles of the week: what to go over again, with why each idea is wrong.
   *
   * @param {Array} knots
   * @return {string}
   */
  function hanging(knots) {
    return knots.length ? '<div class="result-chapter"><div class="section-title"><h3>' + esc(t('teach_knots_hanging')) +
      '</h3></div><p class="help-soft">' + esc(t('teach_knots_hanging_help')) + '</p>' + knots.map(function(k) {
        return '<div class="item" style="display:block"><b>' + esc(k.name) + '</b><small style="display:block;color:' +
          'var(--text-soft)">' + esc(t('teach_knots_count', k)) + '</small><p class="note">«' + esc(k.claim) + '»' +
          (k.why ? '<br><small>' + esc(k.why) + '</small>' : '') + '</p></div>';
      }).join('') + '</div>' : '';
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
      return '<li><div class="item" data-open="' + ch.id + '" style="cursor:pointer" tabindex="0" role="button">' +
        '<span class="grow"><b>' + esc(ch.name) +
        '</b><small>' + rewards(ch) + (ch.teamwork ? ' · ' + esc(t('teach_teamwork')) : '') + '</small></span>' +
        '<span class="tag done">' + done + ' / ' + d.students.length + '</span></div></li>';
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
    el.querySelectorAll('[data-pactsall]').forEach(function(b) {
      b.onclick = function() {
        el.querySelectorAll('[data-pactmark], [data-pactsall]').forEach(function(o) {
          o.disabled = true;
        });
        T.api('decide_pact', {courseid: cfg.courseid, status: 'kept', pactids: b.dataset.pactsall.split(',').map(Number)})
        .then(function() {
          return load(true);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          render();
        });
      };
    });
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
      // All of the team kept them, in one touch; then who did not is unmarked.
      var open = mine.filter(function(x) {
        return x.status !== 'kept';
      });
      return '<h3 class="pact-team">' + esc(g.name) + (marks && open.length > 1 ? ' <button class="btn btn-soft" type="button" ' +
        'data-pactsall="' + open.map(function(x) {
          return x.id;
        }).join(',') + '">' + esc(t('teach_pacts_allkept')) + '</button>' : '') + '</h3><ul class="list">' + mine.map(function(x) {
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
      r.push(t(ch.gems === 1 ? 'app_encounter_gem' : 'app_encounter_gems', ch.gems));
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
      if (e.note) {
        reason = e.note + ' · ' + reason;
      }
      return '<li class="item"><span style="width:22px;height:22px;flex:none">' + T.icon(e.resource === 'item' ? 'gems' :
        e.resource) + '</span><span class="grow"><b>' + esc(e.fullname) + '</b><small>' + esc(reason) +
        (e.actor ? ' · ' + esc(e.actor) : '') + ' · ' + esc(T.date(new Date(e.time * 1000), {day: 'numeric', month: 'short',
          hour: '2-digit', minute: '2-digit'})) +
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

  /** What a touch with a reason can give, and how much at most (as regions::REASON_MAX). */
  var TOUCH_MAX = {saber: 50, gems: 3, breath: 2};

  /** What a new touch gives when what it gives changes. */
  var TOUCH_USUAL = {saber: 5, gems: 1, breath: 1};

  /**
   * The touches with a reason of the course, to change, add or take away.
   *
   * @param {Object} r The settings of the course.
   * @param {boolean} editable
   * @return {string}
   */
  function touchesEditor(r, editable) {
    var dis = editable ? '' : ' disabled';
    return '<h3>' + esc(t('teach_touches')) + '</h3><p class="help-soft">' + esc(t('teach_touches_help')) + '</p>' +
      '<ul class="reason-rows" data-reasons>' + (r.reasons || []).map(function(x) {
        return touchRow(x, dis);
      }).join('') + '</ul>' + (editable ? '<div class="actions" style="justify-content:flex-start;margin-bottom:14px">' +
      '<button type="button" class="btn btn-soft" data-reasonadd>' + esc(t('teach_touch_add')) + '</button>' +
      (r.reasonsown ? '<button type="button" class="btn btn-soft" data-reasonreset>' + esc(t('teach_touch_reset')) +
      '</button>' : '') + '</div>' : '');
  }

  function touchRow(x, dis) {
    return '<li class="reason-row"><input type="text" maxlength="60" value="' + esc(x.text) + '" aria-label="' +
      esc(t('teach_touch_text')) + '" data-reasontext' + dis + '><select data-reasonres aria-label="' +
      esc(t('teach_touch_gives')) + '"' + dis + '>' + Object.keys(TOUCH_MAX).map(function(res) {
        return '<option value="' + res + '"' + (x.resource === res ? ' selected' : '') + '>' + esc(t('app_' + res)) +
          '</option>';
      }).join('') + '</select><input type="number" min="1" max="' + TOUCH_MAX[x.resource] + '" value="' + x.amount + '" ' +
      'aria-label="' + esc(t('teach_touch_amount')) + '" data-reasonamount' + dis + '>' + (dis ? '' : '<button ' +
      'type="button" class="btn btn-soft icon-btn" data-reasondel aria-label="' + esc(t('teach_touch_remove')) + '" title="' +
      esc(t('teach_touch_remove')) + '">×</button>') + '</li>';
  }

  /**
   * The answers of the editor of the touches: whether they were changed, and how they are left.
   *
   * @param {Element} el
   * @return {Object} edited() and list().
   */
  function bindTouchesEditor(el) {
    var box = el.querySelector('[data-reasons]');
    var edited = false;
    var bindRow = function(row) {
      row.querySelectorAll('input, select').forEach(function(input) {
        input.addEventListener('input', function() {
          edited = true;
        });
      });
      var res = row.querySelector('[data-reasonres]');
      res.addEventListener('change', function() {
        var amount = row.querySelector('[data-reasonamount]');
        amount.max = TOUCH_MAX[res.value];
        amount.value = TOUCH_USUAL[res.value];
        edited = true;
      });
      var del = row.querySelector('[data-reasondel]');
      if (del) {
        del.onclick = function() {
          row.remove();
          edited = true;
        };
      }
    };
    box.querySelectorAll('.reason-row').forEach(bindRow);
    var add = el.querySelector('[data-reasonadd]');
    if (add) {
      add.onclick = function() {
        if (box.children.length >= 12) {
          return;
        }
        box.insertAdjacentHTML('beforeend', touchRow({text: '', resource: 'saber', amount: 5}, ''));
        bindRow(box.lastElementChild);
        box.lastElementChild.querySelector('[data-reasontext]').focus();
        edited = true;
      };
    }
    var reset = el.querySelector('[data-reasonreset]');
    if (reset) {
      reset.onclick = function() {
        reset.disabled = true;
        T.api('save_region', {courseid: cfg.courseid, reasons: 'default'}).then(function(region) {
          state.data.region = region;
          T.toast(esc(t('app_saved')));
          tabSettings();
        }).catch(function(e) {
          reset.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    }
    return {
      edited: function() {
        return edited;
      },
      list: function() {
        return Array.prototype.map.call(box.querySelectorAll('.reason-row'), function(row) {
          return {text: row.querySelector('[data-reasontext]').value.trim(), resource: row.querySelector('[data-reasonres]').value,
            amount: Number(row.querySelector('[data-reasonamount]').value)};
        }).filter(function(x) {
          return x.text;
        });
      }
    };
  }

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
    // The theme of the class: the one of the world, or the other one of the plugin (night or day).
    var looks = [''].concat(['night', 'day'].filter(function(l) {
      return l !== r.worldlook;
    })).concat(r.look && r.look === r.worldlook ? [r.look] : []);
    var lookCard = '<h3>' + esc(t('teach_look')) + '</h3><p style="color:var(--text-soft)">' + esc(t('teach_look_help')) +
      '</p><div class="cover-options">' + looks.map(function(look) {
        var shown = look || r.worldlook;
        return '<label class="cover-option"><input type="radio" name="look" value="' + look + '"' +
          (r.look === look ? ' checked' : '') + (editable ? '' : ' disabled') + '><span class="look-sketch look-' + shown +
          '" aria-hidden="true"><i></i><b></b><b></b></span><span class="cover-text"><b>' + esc(look ? t('teach_look_' + look) :
          t('teach_look_world_' + shown)) + '</b><small>' + esc(t('teach_look_' + shown + '_help')) +
          '</small></span></label>';
      }).join('') + '</div>';
    var coverCard = '<h3>' + esc(t('teach_cover')) + '</h3><p style="color:var(--text-soft)">' + esc(t('teach_cover_help')) +
      '</p><div class="cover-options">' + covers + '</div><p><a href="' +
      esc(cfg.wwwroot + '/course/view.php?id=' + cfg.courseid) + '">' + esc(t('teach_cover_see')) + '</a></p>';
    // The badge of Moodle the mission brings: whether it is on, and where the teacher turns it on.
    var badgeCard = r.badgeid ? '<h3>' + esc(t('teach_badge')) + '</h3><p style="color:var(--text-soft)">' +
      esc(t(r.badgeon ? 'teach_badge_on' : 'teach_badge_off', r.badge)) + ' <a href="' +
      esc(cfg.wwwroot + '/badges/overview.php?id=' + r.badgeid) + '">' + esc(t('teach_badge_manage')) + '</a></p>' : '';
    // The gradebook of Moodle, off by default: only the ratings the teacher gives in the competencies.
    var gradesCard = '<h3>' + esc(t('teach_gradebook')) + '</h3><label class="chip" style="display:flex;gap:8px;' +
      'align-items:flex-start;margin:4px 0"><input type="checkbox" name="gradebook"' + (r.gradebook ? ' checked' : '') +
      (editable ? '' : ' disabled') + '> <span>' + esc(t('teach_set_gradebook')) + '</span></label><small style="' +
      'display:block;color:var(--text-soft);margin-bottom:14px">' + esc(t('teach_set_gradebook_help')) + '</small>';
    var check = function(name, on) {
      return '<label class="chip" style="display:inline-flex;gap:8px;align-items:center;margin:4px 0 14px"><input ' +
        'type="checkbox" name="' + name + '"' + (on ? ' checked' : '') + (editable ? '' : ' disabled') + '> ' +
        esc(t('teach_set_' + name)) + '</label>';
    };
    var choice = function(name, values, now, label) {
      return '<div class="pills" style="justify-content:flex-start" role="radiogroup" aria-label="' +
        esc(t('teach_set_' + name)) + '">' +
        values.map(function(v) {
          return '<label class="chip"><input type="radio" name="' + name + '" value="' + v + '"' + (String(now) === String(v) ?
            ' checked' : '') + (editable ? '' : ' disabled') + '> ' + esc(label(v)) + '</label>';
        }).join('') + '</div>';
    };
    // Two layers: what a teacher touches any day, and folded, what is set once or rarely.
    var daily = '<label class="chip" style="display:inline-flex;gap:8px;align-items:center;margin-bottom:14px"><input ' +
      'type="checkbox" name="enabled"' + (r.enabled ? ' checked' : '') + (editable ? '' : ' disabled') + '> ' +
      esc(t('teach_set_enabled')) + '</label><h3>' + esc(t('teach_family')) + '</h3><div class="pills" style="' +
      'justify-content:flex-start;margin-bottom:14px">' + families(r.family) + '</div><h3>' + esc(t('board_devices')) +
      '</h3><p class="help-soft">' + esc(t('teach_set_devices_help')) + '</p><div class="pills" style="justify-content:' +
      'flex-start" role="radiogroup" ' +
      'aria-label="' + esc(t('board_devices')) + '">' + [['1', 'board_devices_tablets'], ['0', 'board_devices_none']]
      .map(function(d) {
        return '<label class="chip"><input type="radio" name="devices" value="' + d[0] + '"' + ((r.devices !== false) ===
          (d[0] === '1') ? ' checked' : '') + (editable ? '' : ' disabled') + '> ' + esc(t(d[1])) + '</label>';
      }).join('') + '</div><h3>' + esc(t('teach_set_nicknames')) + '</h3>' + nick + '<div>' +
      check('leaderboard', r.leaderboard) + ' ' + check('quiet', r.quiet) + '</div><h3>' + esc(t('teach_set_devicemusic')) +
      '</h3><p class="help-soft">' + esc(t('teach_set_devicemusic_help')) + '</p>' + choice('devicemusic', [0, 1, 2],
      r.devicemusic === undefined ? 1 : r.devicemusic, function(m) {
        return t('teach_set_devicemusic_' + m);
      }) + '<h3>' + esc(t('teach_set_mischief')) + '</h3><p class="help-soft">' + esc(t('teach_set_mischief_help')) +
      '</p>' + choice('mischief', ['off', 'calm', 'naughty'], r.mischief || 'calm', function(m) {
        return t('teach_set_mischief_' + m);
      }) + coverCard;
    var advanced = '<details class="settings-advanced"' + (state.advanced ? ' open' : '') + '><summary><b>' +
      esc(t('teach_set_advanced')) + '</b><small>' + esc(t('teach_set_advanced_help')) + '</small></summary>' +
      field('groupingid', t('teach_set_groupingid'), groupings) + field('timezone', t('teach_set_timezone'), timezone) +
      (editable ? '<div style="margin:-6px 0 14px">' + makeTeamsForm() + '</div>' : '') +
      '<div class="grid2" style="gap:10px">' + num('weeklycap', r.weeklycap, true) +
      num('dailymanualcap', r.dailymanualcap, false) + num('dailyenergylosscap', r.dailyenergylosscap, false) +
      num('completionrule', r.completionrule, true) + '</div>' + '<label class="chip" style="display:flex;gap:8px;' +
      'align-items:flex-start;margin:4px 0"><input type="checkbox" name="talentson"' + (r.talentson !== false ?
      ' checked' : '') + (editable ? '' : ' disabled') + '> <span>' + esc(t('teach_set_talentson')) + '</span></label>' +
      '<small style="display:block;color:var(--text-soft);margin-bottom:14px">' + esc(t('teach_set_talentson_help')) +
      '</small>' + touchesEditor(r, editable) + inside + lookCard + badgeCard + gradesCard + '</details>';
    var el = content('<section class="card"><h2>' + esc(t('teach_tab_settings')) + '</h2><form data-settings>' + daily +
      advanced + (editable ? '<div class="actions" style="justify-content:flex-start;margin-top:14px"><button ' +
      'class="btn btn-main" type="submit">' + esc(t('app_save')) + '</button></div>' : '') + '</form></section>');
    el.querySelector('.settings-advanced').ontoggle = function() {
      state.advanced = this.open;
    };
    var touches = bindTouchesEditor(el);
    var family = r.family;
    bindFamilies(function(f) {
      family = f;
    });
    bindMakeTeams(el);
    // The theme of the class is saved and seen as soon as it is chosen.
    el.querySelectorAll('[name="look"]').forEach(function(input) {
      input.onchange = function() {
        T.api('save_region', {courseid: cfg.courseid, look: input.value}).then(function(region) {
          r.look = region.look;
          T.light.follow(region.look || region.worldlook);
          var b = app.querySelector('[data-light]');
          if (b) {
            b.outerHTML = lightButton();
            bindLight();
          }
          T.toast(esc(t('app_saved')));
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
    var f = el.querySelector('form');
    f.onsubmit = function(ev) {
      ev.preventDefault();
      var picked = f.querySelector('[name="nicknames"]:checked');
      var cover = f.querySelector('[name="portal"]:checked');
      var look = f.querySelector('[name="look"]:checked');
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
        quiet: f.quiet.checked,
        devices: f.querySelector('[name="devices"]:checked').value === '1',
        devicemusic: Number(f.querySelector('[name="devicemusic"]:checked').value),
        mischief: f.querySelector('[name="mischief"]:checked').value,
        nicknames: picked ? Number(picked.value) : 0,
        timezone: f.timezone.value,
        portal: cover ? cover.value : 'door',
        hideacts: f.hideacts.checked,
        gradebook: f.gradebook.checked,
        talentson: f.talentson.checked,
        look: look ? look.value : '',
        // Only when they were changed: those of the plugin follow the language of each teacher.
        reasons: touches.edited() ? JSON.stringify(touches.list()) : null
      }).then(function(region) {
        T.light.follow(region.look || region.worldlook);
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
