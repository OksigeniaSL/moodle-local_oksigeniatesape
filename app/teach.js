/**
 * Tesapé: the teacher panel, made for the phone first. The class with names, photos and characters; touches of
 * knowledge, gems and breath; the word on classroom challenges, also without connection; pause and recovery; the log
 * with undo; and the settings of the course.
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
  var state = {data: null, tab: 'class', team: 0, selected: {}, open: 0, log: null, logUser: 0, challenge: 0};
  var QUEUE = 'tesape-queue-' + cfg.courseid + '-' + cfg.userid;

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
      if (navigator.onLine !== false && !/fetch|network|load failed/i.test(e.message)) {
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
    }).catch(function() {
      // Still no luck; it will try again when the connection comes back.
    });
  }

  window.addEventListener('online', flush);

  /* ---------- Frame ---------- */

  var TABS = ['class', 'mission', 'challenges', 'log', 'settings'];

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
      pending)) : '') + '</small></span></div><a class="btn btn-soft" href="' + esc(cfg.courseurl) + '">' +
      esc(t('app_exit')) + '</a></header><nav class="tabs" aria-label="Tesapé">' + TABS.map(function(tab) {
        return '<button type="button" data-tab="' + tab + '"' + (state.tab === tab ? ' aria-current="page"' : '') + '>' +
          T.icon({class: 'team', mission: 'world', challenges: 'challenges', log: 'log', settings: 'settings'}[tab]) + '<span>' +
          esc(t('teach_tab_' + tab)) + '</span></button>';
      }).join('') + '</nav><main class="content" id="content"></main></div>';
    app.querySelectorAll('[data-tab]').forEach(function(b) {
      b.onclick = function() {
        state.tab = b.dataset.tab;
        state.open = 0;
        render();
        window.scrollTo(0, 0);
      };
    });
    ({class: tabClass, mission: tabMission, challenges: tabChallenges, log: tabLog, settings: tabSettings})[state.tab]();
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

  function studentCard(s) {
    var c = s.character;
    var m = c && c.member;
    var fam = state.data.region.family;
    var sel = !!state.selected[s.userid];
    return '<li class="item" style="cursor:pointer;' + (sel ? 'border-color:var(--lantern);background:rgba(255,200,87,.1)' :
      '') + '" data-student="' + s.userid + '"><input type="checkbox" aria-label="' + esc(s.fullname) + '" ' +
      (sel ? 'checked' : '') + ' data-check="' + s.userid + '" style="width:22px;height:22px;flex:none">' +
      '<img src="' + esc(s.picture) + '" alt="" width="44" height="44" style="border-radius:50%;flex:none">' +
      '<span class="mini" style="width:40px;height:46px">' + (c ? T.avatar({role: c.role, nameform: c.nameform,
      lineage: c.lineage, look: c.appearance, stage: c.stage}) : '') + '</span><span class="grow"><b>' + esc(s.fullname) +
      '</b><small>' + (c ? esc(T.roleName(fam, c.role, c.nameform)) + ' · ' + esc(t('app_level')) + ' ' + c.level +
      (c.nickname ? ' · «' + esc(c.nickname) + '»' : '') : '') + '</small></span>' + badge(s) +
      (m ? '<span class="bars" aria-hidden="true"><span class="meter energy"><i style="width:' +
      Math.round(100 * m.energy / state.data.energymax) + '%"></i></span><span class="meter breath"><i style="width:' +
      Math.round(100 * m.breath / state.data.breathmax) + '%"></i></span></span>' : '') + '</li>';
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
    var el = content('<section class="card"><div class="section-title"><h2>' + esc(t('teach_tab_class')) + '</h2>' +
      '<button class="btn btn-soft" data-all>' + esc(t('teach_selectall')) + '</button></div>' + teams +
      (shown.length ? '<ul class="list">' + shown.map(studentCard).join('') + '</ul>' : '<p class="empty">' +
      esc(t('teach_nostudents')) + '</p>') + '</section><div id="actionbar"></div>');
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
    });
    actionBar();
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
        award(ids, b.dataset.award, Number(b.dataset.amount));
      };
    });
  }

  function award(ids, resource, amount) {
    T.api('award', {courseid: cfg.courseid, userids: ids, resource: resource, amount: amount}).then(function(results) {
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

  function openStudent(userid) {
    var s = state.data.students.filter(function(x) {
      return x.userid === userid;
    })[0];
    var c = s.character;
    var m = c && c.member;
    var fam = state.data.region.family;
    var html = '<section class="card"><button class="btn btn-soft" data-back>← ' + esc(t('app_back')) + '</button>' +
      '<div class="sheet" style="margin-top:12px"><div style="text-align:center"><img src="' + esc(s.picture) +
      '" alt="" width="88" height="88" style="border-radius:50%;border:3px solid var(--lantern)">' + (c ? T.avatar({
      role: c.role, nameform: c.nameform, lineage: c.lineage, look: c.appearance, stage: c.stage}) : '') + '</div>' +
      '<div class="stats"><h2>' + esc(s.fullname) + '</h2>';
    if (!c) {
      html += '<p>' + esc(t('teach_nocharacter_help')) + '</p></div></div></section>';
      content(html).querySelector('[data-back]').onclick = render;
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

  /* ---------- The mission ---------- */

  function tabMission() {
    if (!state.missions || !state.results) {
      content('<section class="card"><p class="empty">' + esc(t('app_loading')) + '</p></section>');
      Promise.all([
        T.api('get_missions', {courseid: cfg.courseid}),
        can('viewlog') ? T.api('get_results', {courseid: cfg.courseid}) : Promise.resolve({chapters: []})
      ]).then(function(r) {
        state.missions = r[0];
        state.results = r[1];
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
      html = '<section class="card"><div class="kicker">' + esc(t('teach_mission_installed')) + '</div><h2>' +
        esc(m.installed.title) + '</h2>' + (pkg ? '<p style="color:var(--text-soft)">' + esc(pkg.subtitle) + ' · ' +
        esc(t('teach_mission_ages', pkg.ages)) + '</p>' + (can('manageregion') ? packageForm(pkg, m.installed) : '') : '') +
        '</section>' + results();
    }
    var el = content(html);
    el.querySelectorAll('form[data-install]').forEach(function(f) {
      f.onsubmit = function(ev) {
        ev.preventDefault();
        var skin = f.querySelector('[name="skin"]:checked');
        var variant = f.querySelector('[name="variant"]:checked');
        T.api('install_mission', {courseid: cfg.courseid, code: f.dataset.install, skin: skin.value,
          variant: variant.value}).then(function(missions) {
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

  function packageForm(p, installed) {
    var radios = function(name, list, current) {
      return '<div class="pills" style="justify-content:flex-start;margin-bottom:10px">' + list.map(function(o, i) {
        var on = current ? current === o.code : i === 0;
        return '<label class="chip" style="display:inline-flex;gap:8px;align-items:center"><input type="radio" name="' + name +
          '" value="' + esc(o.code) + '"' + (on ? ' checked' : '') + '> ' + esc(o.name) + '</label>';
      }).join('') + '</div>';
    };
    return '<form data-install="' + esc(p.code) + '" style="margin-top:12px"><h3>' + esc(t('teach_mission_skin')) + '</h3>' +
      radios('skin', p.skins, installed && installed.skin) + '<h3>' + esc(t('teach_mission_variant')) + '</h3>' +
      radios('variant', p.variants, installed && installed.variant) + '<div class="actions" style="justify-content:' +
      'flex-start"><button class="btn ' + (installed ? 'btn-soft' : 'btn-main') + '" type="submit">' +
      esc(installed ? t('teach_mission_change') : t('teach_mission_install')) + '</button></div></form>';
  }

  function packageCard(p) {
    return '<section class="card" style="margin-top:16px"><div class="kicker">' + esc(t('teach_mission_ages', p.ages)) +
      '</div><h2>' + esc(p.title) + '</h2><p>' + esc(p.subtitle) + '</p><p style="color:var(--text-soft)">' +
      esc(t('teach_mission_size', p.chapters + ' · ' + p.scenes)) + (p.lang !== cfg.lang.slice(0, 2) ? ' · ' +
      esc(t('teach_mission_lang', p.lang)) : '') + '</p>' + (can('manageregion') ? packageForm(p, null) : '') + '</section>';
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
                return esc(c.text) + ' (' + c.count + ')';
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
      return '<li class="item" data-open="' + ch.id + '" style="cursor:pointer"><span class="grow"><b>' + esc(ch.name) +
        '</b><small>' + rewards(ch) + (ch.teamwork ? ' · ' + esc(t('teach_teamwork')) : '') + '</small></span>' +
        '<span class="tag done">' + done + ' / ' + d.students.length + '</span></li>';
    }).join('');
    var el = content('<section class="card"><div class="section-title"><h2>' + esc(t('teach_tab_challenges')) + '</h2>' +
      (can('manageregion') ? '<button class="btn btn-main" data-new>' + esc(t('teach_newchallenge')) + '</button>' : '') +
      '</div><p style="color:var(--text-soft)">' + esc(t('teach_challenges_help')) + '</p>' + (list ? '<ul class="list">' +
      list + '</ul>' : '<p class="empty">' + esc(t('app_nochallenges')) + '</p>') + '</section><div id="form"></div>');
    el.querySelectorAll('[data-open]').forEach(function(li) {
      li.onclick = function() {
        state.challenge = Number(li.dataset.open);
        render();
      };
    });
    var add = el.querySelector('[data-new]');
    if (add) {
      add.onclick = function() {
        challengeForm(null);
      };
    }
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
        return '<li class="item" style="flex-wrap:wrap"><img src="' + esc(s.picture) + '" alt="" width="36" height="36" ' +
          'style="border-radius:50%"><span class="grow"><b>' + esc(s.fullname) + '</b>' + (v && v.note ? '<small>' +
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
    var nick = [0, 1, 2].map(function(n) {
      return '<label class="chip" style="display:flex;gap:8px;align-items:center;margin-bottom:6px"><input type="radio" ' +
        'name="nicknames" value="' + n + '"' + (r.nicknames === n ? ' checked' : '') + (editable ? '' : ' disabled') +
        '> ' + esc(t('teach_nick_' + n)) + '</label>';
    }).join('');
    var el = content('<section class="card"><h2>' + esc(t('teach_tab_settings')) + '</h2><form data-settings>' +
      '<label class="chip" style="display:inline-flex;gap:8px;align-items:center;margin-bottom:14px"><input ' +
      'type="checkbox" name="enabled"' + (r.enabled ? ' checked' : '') + (editable ? '' : ' disabled') + '> ' +
      esc(t('teach_set_enabled')) + '</label><h3>' + esc(t('teach_family')) + '</h3><div class="pills" style="' +
      'justify-content:flex-start;margin-bottom:14px">' + families(r.family) + '</div>' +
      field('groupingid', t('teach_set_groupingid'), groupings) + '<div class="grid2" style="gap:10px">' +
      num('weeklycap', r.weeklycap, true) + num('dailymanualcap', r.dailymanualcap, false) +
      num('dailyenergylosscap', r.dailyenergylosscap, false) + num('completionrule', r.completionrule, true) + '</div>' +
      '<label class="chip" style="display:inline-flex;gap:8px;align-items:center;margin:4px 0 14px"><input ' +
      'type="checkbox" name="leaderboard"' + (r.leaderboard ? ' checked' : '') + (editable ? '' : ' disabled') + '> ' +
      esc(t('teach_set_leaderboard')) + '</label><h3>' + esc(t('teach_set_nicknames')) + '</h3>' + nick +
      (editable ? '<div class="actions" style="justify-content:flex-start;margin-top:14px"><button class="btn btn-main" ' +
      'type="submit">' + esc(t('app_save')) + '</button></div>' : '') + '</form></section>');
    var family = r.family;
    bindFamilies(function(f) {
      family = f;
    });
    var f = el.querySelector('form');
    f.onsubmit = function(ev) {
      ev.preventDefault();
      var picked = f.querySelector('[name="nicknames"]:checked');
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
        nicknames: picked ? Number(picked.value) : 0
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
