/**
 * Tesapé: the game client of a student. The first time is a story that ends with their character; after that, the
 * world: their region, team, classroom challenges, diary and character sheet.
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
  var state = {world: null, tab: 'world', draft: null, editing: false};

  /* ---------- Start ---------- */

  function loading() {
    app.innerHTML = '<div class="loading"><div><div class="lamp"></div>' + esc(t('app_loading')) + '</div></div>';
  }

  function fail(error) {
    app.innerHTML = '<div class="story"><h2>' + esc(t('app_error')) + '</h2><p class="help">' + esc(error.message) +
      '</p><div class="actions"><button class="btn btn-main" data-go="retry">' + esc(t('app_retry')) + '</button>' +
      '<a class="btn btn-soft" href="' + esc(cfg.courseurl) + '">' + esc(t('app_exit')) + '</a></div></div>';
    app.querySelector('[data-go="retry"]').onclick = load;
  }

  function load() {
    loading();
    T.api('get_world', {courseid: cfg.courseid}).then(function(world) {
      state.world = world;
      route();
    }).catch(fail);
  }

  function route() {
    var w = state.world;
    if (!w.enabled) {
      app.innerHTML = '<div class="story">' + T.vignette('region') + '<h2>' + esc(cfg.coursename) + '</h2><p class="lead">' +
        esc(t('app_off')) + '</p><div class="actions"><a class="btn btn-soft" href="' + esc(cfg.courseurl) + '">' +
        esc(t('app_exit')) + '</a></div></div>';
      return;
    }
    if (!w.character) {
      state.draft = {role: '', nameform: 'm', lineage: '', look: null};
      prologue();
      return;
    }
    celebrate();
    world();
  }

  /* ---------- The first time: a story that ends with the character ---------- */

  function steps(current) {
    var list = ['role'];
    if (state.draft.role && T.hasForms(state.world.family, state.draft.role)) {
      list.push('form');
    }
    if (T.hasLineages(state.world.family)) {
      list.push('lineage');
    }
    list.push('look', 'ready');
    var i = list.indexOf(current);
    return {
      list: list,
      bar: '<div class="steps" aria-hidden="true">' + list.map(function(s, k) {
        return '<span' + (k <= i ? ' class="on"' : '') + '></span>';
      }).join('') + '</div>',
      next: list[i + 1],
      prev: list[i - 1]
    };
  }

  function go(step) {
    ({role: chooseRole, form: chooseForm, lineage: chooseLineage, look: chooseLook, ready: ready})[step]();
    window.scrollTo(0, 0);
  }

  function prologue() {
    app.innerHTML = '<div class="story">' + T.vignette('prologue') + '<h1>' + esc(t('app_prologue_title')) + '</h1>' +
      '<p class="lead">' + esc(t('app_prologue')) + '</p><div class="actions"><button class="btn btn-main" data-go="role">' +
      esc(t('app_start')) + '</button></div></div>';
    app.querySelector('[data-go]').onclick = function() {
      go('role');
    };
  }

  function draftAvatar(extra) {
    var d = state.draft;
    return T.avatar(Object.assign({role: d.role || 'sage', nameform: d.nameform, lineage: d.lineage,
      look: d.look || T.defaultLook(d.role), stage: 1}, extra || {}));
  }

  function chooseRole() {
    var fam = state.world.family;
    var s = steps('role');
    var cards = T.ROLES.map(function(role) {
      var names = T.hasForms(fam, role) ? T.roleName(fam, role, 'm') + ' · ' + T.roleName(fam, role, 'f') :
        T.roleName(fam, role, 'm');
      return '<button class="choice" type="button" data-role="' + role + '" aria-pressed="' + (state.draft.role === role) +
        '">' + T.avatar({role: role, nameform: 'm', look: T.defaultLook(role), stage: 1}) + '<strong>' + esc(names) +
        '</strong><small>' + esc(t('roledesc_' + role)) + '</small></button>';
    }).join('');
    app.innerHTML = '<div class="story">' + s.bar + '<h2>' + esc(t('app_who')) + '</h2><p class="help">' +
      esc(t('app_who_help')) + '</p><div class="choices">' + cards + '</div><div class="actions">' +
      '<button class="btn btn-main" data-next disabled>' + esc(t('app_next')) + '</button></div></div>';
    var next = app.querySelector('[data-next]');
    next.disabled = !state.draft.role;
    app.querySelectorAll('[data-role]').forEach(function(b) {
      b.onclick = function() {
        state.draft.role = b.dataset.role;
        state.draft.look = state.draft.look || T.defaultLook(b.dataset.role);
        if (!state.draft.lookTouched) {
          state.draft.look.outfit = T.ROLE_OUTFIT[b.dataset.role];
        }
        app.querySelectorAll('[data-role]').forEach(function(o) {
          o.setAttribute('aria-pressed', o === b);
        });
        next.disabled = false;
      };
      b.ondblclick = function() {
        go(steps('role').next);
      };
    });
    next.onclick = function() {
      go(steps('role').next);
    };
  }

  function chooseForm() {
    var fam = state.world.family;
    var role = state.draft.role;
    var s = steps('form');
    var cards = ['m', 'f'].map(function(form) {
      return '<button class="choice" type="button" data-form="' + form + '" aria-pressed="' +
        (state.draft.nameform === form) + '">' + draftAvatar({nameform: form}) + '<strong>' +
        esc(T.roleName(fam, role, form)) + '</strong></button>';
    }).join('');
    app.innerHTML = '<div class="story">' + s.bar + '<h2>' + esc(t('app_form')) + '</h2><div class="choices" ' +
      'style="max-width:420px">' + cards + '</div>' + nav(s) + '</div>';
    app.querySelectorAll('[data-form]').forEach(function(b) {
      b.onclick = function() {
        state.draft.nameform = b.dataset.form;
        app.querySelectorAll('[data-form]').forEach(function(o) {
          o.setAttribute('aria-pressed', o === b);
        });
      };
    });
    bindNav(s);
  }

  function chooseLineage() {
    var s = steps('lineage');
    state.draft.lineage = state.draft.lineage || 'human';
    var cards = T.LINEAGES.map(function(l) {
      return '<button class="choice" type="button" data-lineage="' + l + '" aria-pressed="' + (state.draft.lineage === l) +
        '">' + draftAvatar({lineage: l}) + '<strong>' + esc(T.lineageName(l, state.draft.nameform)) + '</strong></button>';
    }).join('');
    app.innerHTML = '<div class="story">' + s.bar + '<h2>' + esc(t('app_lineage')) + '</h2><p class="help">' +
      esc(t('app_lineage_help')) + '</p><div class="choices">' + cards + '</div>' + nav(s) + '</div>';
    app.querySelectorAll('[data-lineage]').forEach(function(b) {
      b.onclick = function() {
        state.draft.lineage = b.dataset.lineage;
        app.querySelectorAll('[data-lineage]').forEach(function(o) {
          o.setAttribute('aria-pressed', o === b);
        });
      };
    });
    bindNav(s);
  }

  /**
   * The look builder, for a new character or to change the look of an existing one.
   *
   * @param {Object} d The draft: role, nameform, lineage, look.
   * @param {number} stage
   * @return {string}
   */
  function builder(d, stage) {
    function swatches(key, colours) {
      return '<div class="swatches">' + colours.map(function(c, i) {
        return '<button class="swatch" type="button" data-key="' + key + '" data-value="' + c + '" style="background:' + c +
          '" aria-label="' + esc(t('app_' + key)) + ' ' + (i + 1) + '" aria-pressed="' + (d.look[key] === c) + '"></button>';
      }).join('') + '</div>';
    }
    var hairs = '<div class="swatches">' + T.HAIRS.map(function(h) {
      return '<button class="chip" type="button" data-key="hair" data-value="' + h + '" aria-pressed="' + (d.look.hair === h) +
        '">' + esc(t('app_hair_' + h)) + '</button>';
    }).join('') + '</div>';
    return '<div class="builder"><div class="preview" data-preview>' + T.avatar({role: d.role, nameform: d.nameform,
      lineage: d.lineage, look: d.look, stage: stage}) + '</div><div class="rows">' +
      '<div class="row"><span>' + esc(t('app_skin')) + '</span>' + swatches('skin', T.SKINS) + '</div>' +
      '<div class="row"><span>' + esc(t('app_hair')) + '</span>' + hairs + '</div>' +
      '<div class="row"><span>' + esc(t('app_haircolour')) + '</span>' + swatches('haircolour', T.HAIRCOLOURS) + '</div>' +
      '<div class="row"><span>' + esc(t('app_outfit')) + '</span>' + swatches('outfit', T.OUTFITS) + '</div></div></div>';
  }

  function bindBuilder(d, stage) {
    app.querySelectorAll('[data-key]').forEach(function(b) {
      b.onclick = function() {
        d.look[b.dataset.key] = b.dataset.value;
        d.lookTouched = true;
        app.querySelectorAll('[data-key="' + b.dataset.key + '"]').forEach(function(o) {
          o.setAttribute('aria-pressed', o === b);
        });
        app.querySelector('[data-preview]').innerHTML = T.avatar({role: d.role, nameform: d.nameform, lineage: d.lineage,
          look: d.look, stage: stage});
      };
    });
  }

  function chooseLook() {
    var s = steps('look');
    state.draft.look = state.draft.look || T.defaultLook(state.draft.role);
    app.innerHTML = '<div class="story">' + s.bar + '<h2>' + esc(t('app_look')) + '</h2>' + builder(state.draft, 1) +
      nav(s) + '</div>';
    bindBuilder(state.draft, 1);
    bindNav(s);
  }

  function title(d) {
    var name = T.roleName(state.world.family, d.role, d.nameform);
    var lineage = T.hasLineages(state.world.family) ? T.lineageName(d.lineage, d.nameform) : '';
    return lineage ? name + ' ' + lineage : name;
  }

  function ready() {
    var s = steps('ready');
    var d = state.draft;
    app.innerHTML = '<div class="story"><div class="halo">' + draftAvatar() + '</div><h2>' + esc(t('app_youwillbe',
      title(d))) + '</h2><p class="help">' + esc(t('app_who_help')) + '</p><div class="actions">' +
      '<button class="btn btn-soft" data-prev>' + esc(t('app_back')) + '</button><button class="btn btn-main" data-create>' +
      esc(t('app_ready')) + '</button></div></div>';
    app.querySelector('[data-prev]').onclick = function() {
      go(s.prev);
    };
    var create = app.querySelector('[data-create]');
    create.onclick = function() {
      create.disabled = true;
      T.api('create_character', {
        courseid: cfg.courseid,
        role: d.role,
        nameform: d.nameform,
        lineage: T.hasLineages(state.world.family) ? d.lineage : '',
        appearance: JSON.stringify(d.look)
      }).then(function(world) {
        state.world = world;
        reveal();
      }).catch(function(e) {
        create.disabled = false;
        T.toast(esc(e.message), true);
      });
    };
  }

  function reveal() {
    var c = state.world.character;
    app.innerHTML = '<div class="story reveal"><div class="halo">' + avatarOf(c) + '</div><h1>' +
      esc(t('app_welcome', myName())) + '</h1><p class="lead">' + esc(title(c)) + '</p><div class="actions">' +
      '<button class="btn btn-main" data-enter>' + esc(t('app_enter')) + '</button></div></div>';
    remember();
    app.querySelector('[data-enter]').onclick = function() {
      state.tab = 'world';
      world();
    };
  }

  function nav(s) {
    return '<div class="actions">' + (s.prev ? '<button class="btn btn-soft" data-prev>' + esc(t('app_back')) +
      '</button>' : '') + '<button class="btn btn-main" data-next>' + esc(t('app_next')) + '</button></div>';
  }

  function bindNav(s) {
    var prev = app.querySelector('[data-prev]');
    if (prev) {
      prev.onclick = function() {
        go(s.prev);
      };
    }
    app.querySelector('[data-next]').onclick = function() {
      go(s.next);
    };
  }

  /* ---------- The world ---------- */

  function myName() {
    var c = state.world.character;
    return state.world.nicknames && c && c.nickname ? c.nickname : cfg.firstname;
  }

  function avatarOf(c, label, portrait) {
    return T.avatar({role: c.role, nameform: c.nameform, lineage: c.lineage, look: c.appearance, stage: c.stage,
      label: label, portrait: portrait});
  }

  /**
   * Toasts for what was won since the student last looked, and a level up.
   */
  function celebrate() {
    var c = state.world.character;
    var key = 'tesape-seen-' + cfg.courseid + '-' + cfg.userid;
    var seen = null;
    try {
      seen = JSON.parse(window.localStorage.getItem(key) || 'null');
    } catch (e) {
      seen = null;
    }
    if (seen) {
      if (c.saber > seen.saber) {
        T.toast(T.icon('saber').replace('<svg', '<svg width="18" height="18"') + ' <b>+' + (c.saber - seen.saber) + '</b> ' +
          esc(t('app_saber')));
      }
      if (c.level > seen.level) {
        T.toast('<b>' + esc(t('app_levelup', c.level)) + '</b>');
      }
    }
    remember();
  }

  function remember() {
    var c = state.world.character;
    try {
      window.localStorage.setItem('tesape-seen-' + cfg.courseid + '-' + cfg.userid,
        JSON.stringify({saber: c.saber, level: c.level}));
    } catch (e) {
      // Without storage there is just no toast next time.
    }
  }

  var TABS = ['world', 'team', 'challenges', 'log', 'me'];

  function world() {
    var w = state.world;
    var c = w.character;
    var m = c.member || {energy: 0, breath: 0};
    var pct = c.levelneeded ? Math.round(100 * c.levelinto / c.levelneeded) : 100;
    var pending = w.challenges.filter(function(ch) {
      return !ch.status || ch.status === 'notyet';
    }).length;
    app.innerHTML = '<div class="sky"></div><div class="world">' +
      '<header class="hud"><button class="me" data-tab="me" aria-label="' + esc(t('app_tab_me')) + '"><span class="face">' +
      avatarOf(c, '', true) + '</span><span class="who"><b>' + esc(myName()) + '</b><small>' + esc(title(c)) + '</small></span>' +
      '</button><div class="ring" style="--p:' + pct + '" title="' + esc(t('app_level')) + ' ' + c.level + '"><b>' +
      c.level + '</b></div><div class="res">' +
      res('saber', c.saber, t('app_saber')) + res('gems', c.gems, t('app_gems')) +
      res('energy', m.energy + '/' + w.energymax, t('app_energy')) + res('breath', m.breath + '/' + w.breathmax,
        t('app_breath')) + '</div></header>' +
      '<nav class="tabs" aria-label="Tesapé">' + TABS.map(function(tab) {
        var badge = tab === 'challenges' && pending ? ' (' + pending + ')' : '';
        return '<button type="button" data-tab="' + tab + '"' + (state.tab === tab ? ' aria-current="page"' : '') + '>' +
          T.icon(tab) + '<span>' + esc(t('app_tab_' + tab)) + badge + '</span></button>';
      }).join('') + '</nav><main class="content" id="content"></main></div>';
    app.querySelectorAll('[data-tab]').forEach(function(b) {
      b.onclick = function() {
        state.tab = b.dataset.tab;
        world();
        window.scrollTo(0, 0);
      };
    });
    var content = document.getElementById('content');
    content.innerHTML = status(m) + ({world: tabWorld, team: tabTeam, challenges: tabChallenges, log: tabLog,
      me: tabMe})[state.tab]();
    if (state.tab === 'me') {
      bindMe();
    }
  }

  function res(kind, value, label) {
    return '<span class="r" title="' + esc(label) + '">' + T.icon(kind) + '<b>' + esc(value) + '</b><span class="sr-only"> ' +
      esc(label) + '</span></span>';
  }

  function status(m) {
    if (m.status === 'paused') {
      return '<div class="banner rest"><div><b>' + esc(t('app_paused')) + '</b><p>' + esc(t('app_paused_help')) + '</p>' +
        (m.statusreason ? '<div class="note">' + esc(m.statusreason) + '</div>' : '') + '</div></div>';
    }
    if (m.status === 'fallen') {
      return '<div class="banner fallen"><div><b>' + esc(t('app_fallen')) + '</b><p>' + esc(t('app_fallen_help')) +
        '</p></div></div>';
    }
    return '';
  }

  function tabWorld() {
    var w = state.world;
    var next = w.challenges.filter(function(ch) {
      return !ch.status || ch.status === 'notyet';
    }).slice(0, 3);
    return '<section class="card region">' + T.vignette('region') + '<div class="body"><div class="kicker">' +
      esc(t('app_region')) + '</div><h2>' + esc(cfg.coursename) + '</h2><p>' + esc(t('app_mission_soon')) + '</p></div>' +
      '</section><div class="grid2" style="margin-top:16px"><section class="card"><h3>' + esc(t('app_tab_challenges')) +
      '</h3>' + (next.length ? '<ul class="list">' + next.map(challengeItem).join('') + '</ul>' : '<p class="empty">' +
      esc(t('app_nochallenges')) + '</p>') + '</section><section class="card"><h3>' + esc(t('app_tab_log')) + '</h3>' +
      logList(w.log.slice(0, 4)) + '</section></div>';
  }

  function tabTeam() {
    var team = state.world.team;
    if (!team) {
      return '<section class="card"><p class="empty">' + esc(t('app_noteam')) + '</p></section>';
    }
    var fam = state.world.family;
    return '<section class="card"><h2>' + esc(team.name) + '</h2><ul class="list">' + team.members.map(function(mate) {
      var c = mate.character;
      var m = c && c.member;
      var tag = m && m.status === 'paused' ? '<span class="tag todo">' + esc(t('app_resting')) + '</span>' :
        m && m.status === 'fallen' ? '<span class="tag notyet">' + esc(t('app_wornout')) + '</span>' : '';
      return '<li class="item"><span class="mini">' + (c ? avatarOf(c) : '') + '</span><span class="grow"><b>' +
        esc(mate.name) + (mate.me ? ' (' + esc(t('app_you')) + ')' : '') + '</b><small>' +
        (c ? esc(T.roleName(fam, c.role, c.nameform)) + ' · ' + esc(t('app_level')) + ' ' + c.level :
        esc(t('app_nocharacter'))) + '</small></span>' + tag + (m ? '<span class="bars"><span class="meter energy" ' +
        'title="' + esc(t('app_energy')) + '"><i style="width:' + Math.round(100 * m.energy / state.world.energymax) +
        '%"></i></span></span>' : '') + '</li>';
    }).join('') + '</ul></section>';
  }

  function challengeItem(ch) {
    var status = ch.status || 'todo';
    var label = {done: 'app_status_done', merit: 'app_status_merit', notyet: 'app_status_notyet',
      todo: 'app_status_pending'}[status];
    var rewards = [];
    if (ch.saber) {
      rewards.push('+' + ch.saber + ' ' + t('app_saber'));
    }
    if (ch.gems) {
      rewards.push('+' + ch.gems + ' ' + t('app_gems'));
    }
    return '<li class="item" style="align-items:flex-start"><span class="grow"><b>' + esc(ch.name) + '</b><small>' +
      esc(rewards.join(' · ')) + (ch.teamwork ? ' · ' + esc(t('app_teamwork')) : '') + '</small>' +
      (ch.description ? '<div style="margin-top:6px;font-size:15px">' + ch.description + '</div>' : '') +
      (ch.note ? '<div class="note"><b>' + esc(t('app_teachersays')) + ':</b> ' + esc(ch.note) + '</div>' : '') +
      '</span><span class="tag ' + status + '">' + esc(t(label)) + '</span></li>';
  }

  function tabChallenges() {
    var list = state.world.challenges;
    return '<section class="card"><h2>' + esc(t('app_tab_challenges')) + '</h2><p style="color:var(--text-soft)">' +
      esc(t('app_challenges_help')) + '</p>' + (list.length ? '<ul class="list">' + list.map(challengeItem).join('') +
      '</ul>' : '<p class="empty">' + esc(t('app_nochallenges')) + '</p>') + '</section>';
  }

  function when(time) {
    try {
      return new Intl.DateTimeFormat(cfg.lang, {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'})
        .format(new Date(time * 1000));
    } catch (e) {
      return new Date(time * 1000).toLocaleString();
    }
  }

  function logList(entries) {
    if (!entries.length) {
      return '<p class="empty">' + esc(t('app_nolog')) + '</p>';
    }
    return '<ul class="list">' + entries.map(function(e) {
      var cls = e.undone ? 'off' : e.amount >= 0 ? 'up' : 'down';
      var amount = e.resource === 'item' ? '' : (e.amount > 0 ? '+' : '') + e.amount;
      var reason = cfg.strings['app_reason_' + e.reason] ? t('app_reason_' + e.reason) : e.reason;
      return '<li class="item"><span style="width:22px;height:22px;flex:none">' + T.icon(e.resource === 'item' ? 'gems' :
        e.resource) + '</span><span class="grow">' + esc(reason) + '<small>' + esc(when(e.time)) +
        (e.capped ? ' · ' + esc(t('app_capped', e.capped)) : '') + '</small></span><span class="amount ' + cls + '">' +
        esc(amount) + '</span></li>';
    }).join('') + '</ul>';
  }

  function tabLog() {
    return '<section class="card"><h2>' + esc(t('app_tab_log')) + '</h2>' + logList(state.world.log) + '</section>';
  }

  function tabMe() {
    var w = state.world;
    var c = w.character;
    var m = c.member || {};
    if (state.editing) {
      return '<section class="card"><h2>' + esc(t('app_changelook')) + '</h2>' + builder(state.editing, c.stage) +
        '<div class="story" style="min-height:0;padding:10px 0 0"><div class="actions"><button class="btn btn-soft" ' +
        'data-cancel>' + esc(t('app_cancel')) + '</button><button class="btn btn-main" data-save>' + esc(t('app_save')) +
        '</button></div></div></section>';
    }
    var pct = c.levelneeded ? Math.round(100 * c.levelinto / c.levelneeded) : 100;
    return '<section class="card sheet"><div>' + avatarOf(c, title(c)) + '</div><div class="stats"><h2>' + esc(myName()) +
      '</h2><p style="color:var(--text-soft)">' + esc(title(c)) + ' · ' + esc(t('app_stage', c.stage)) + '</p>' +
      '<div class="stat"><span>' + esc(t('app_level')) + '</span><b>' + c.level + '</b></div>' +
      '<div class="meter level" style="margin:6px 0 4px"><i style="width:' + pct + '%"></i></div><small style="color:' +
      'var(--text-soft)">' + esc(c.levelneeded ? t('app_tonext', c.levelneeded - c.levelinto) : t('app_maxlevel')) +
      '</small><div class="stat"><span>' + esc(t('app_saber')) + '</span><b>' + c.saber + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_renown')) + '</span><b>' + (m.renown || 0) + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_gems')) + '</span><b>' + c.gems + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_energy')) + '</span><b>' + (m.energy || 0) + ' / ' + w.energymax + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_breath')) + '</span><b>' + (m.breath || 0) + ' / ' + w.breathmax + '</b></div>' +
      '<div class="actions no-print" style="justify-content:flex-start;margin-top:16px"><button class="btn btn-soft" ' +
      'data-edit>' + esc(t('app_changelook')) + '</button><button class="btn btn-soft" data-print>' + esc(t('app_print')) +
      '</button><a class="btn btn-soft" href="' + esc(cfg.courseurl) + '">' + esc(t('app_exit')) + '</a></div></div>' +
      '</section>';
  }

  function bindMe() {
    var c = state.world.character;
    var edit = app.querySelector('[data-edit]');
    if (edit) {
      edit.onclick = function() {
        var look = {};
        try {
          look = JSON.parse(c.appearance || '{}') || {};
        } catch (e) {
          look = {};
        }
        state.editing = {role: c.role, nameform: c.nameform, lineage: c.lineage,
          look: Object.assign(T.defaultLook(c.role), look)};
        world();
      };
      app.querySelector('[data-print]').onclick = function() {
        window.print();
      };
      return;
    }
    bindBuilder(state.editing, c.stage);
    app.querySelector('[data-cancel]').onclick = function() {
      state.editing = false;
      world();
    };
    var save = app.querySelector('[data-save]');
    save.onclick = function() {
      save.disabled = true;
      T.api('update_look', {courseid: cfg.courseid, appearance: JSON.stringify(state.editing.look),
        lineage: c.lineage || '', nameform: c.nameform}).then(function(world) {
        state.world = world;
        state.editing = false;
        T.toast(esc(t('app_saved')));
        route();
      }).catch(function(e) {
        save.disabled = false;
        T.toast(esc(e.message), true);
      });
    };
  }

  load();
})();
