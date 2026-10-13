/**
 * Tesapé: the game client of a student. The first time is a story that ends with their character; after that, the
 * world: the map of the mission with its places (scenes, Moodle activities and minigames), the team with its powers,
 * the challenges, the diary and the character sheet with the box of ideas.
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
  var state = {world: null, tab: 'world', draft: null, editing: false, chapter: 0, power: 0};

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
      T.family = world.family;
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
    // People come as a boy or a girl on the cards of the roles; animals with two names (Oso, Osa) choose it after.
    if (state.draft.role && !T.isPeople(state.world.family) && T.hasForms(state.world.family, state.draft.role)) {
      list.push('form');
    }
    if (T.hasLineages(state.world.family)) {
      list.push('lineage');
    }
    if (lookChanges(state.draft, 1)) {
      list.push('look');
    }
    list.push('ready');
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
    var people = T.isPeople(fam);
    var cards = T.ROLES.map(function(role) {
      return (people ? ['m', 'f'] : ['m']).map(function(form) {
        var on = state.draft.role === role && (!people || state.draft.nameform === form);
        return '<button class="choice" type="button" data-role="' + role + '" data-nameform="' + form + '" aria-pressed="' +
          on + '">' + T.avatar({role: role, nameform: form, look: T.defaultLook(role), stage: 1}) + '<strong>' +
          esc(T.roleName(fam, role, form)) + '</strong><small>' + esc(t('roledesc_' + role)) + '</small></button>';
      }).join('');
    }).join('');
    app.innerHTML = '<div class="story">' + s.bar + '<h2>' + esc(t('app_who')) + '</h2><p class="help">' +
      esc(t('app_who_help')) + '</p><div class="choices">' + cards + '</div><div class="actions">' +
      '<button class="btn btn-main" data-next disabled>' + esc(t('app_next')) + '</button></div></div>';
    var next = app.querySelector('[data-next]');
    next.disabled = !state.draft.role;
    app.querySelectorAll('[data-role]').forEach(function(b) {
      b.onclick = function() {
        state.draft.role = b.dataset.role;
        if (people) {
          state.draft.nameform = b.dataset.nameform;
        }
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
    function row(key, html) {
      return shows[key] ? '<div class="row"><span>' + esc(t('app_' + key)) + '</span>' + html + '</div>' : '';
    }
    // Only what shows: with an art pack, one tone of skin for each image of the pack, and nothing painted in.
    var shows = T.lookShows({role: d.role, nameform: d.nameform, lineage: d.lineage, stage: stage});
    var hairs = '<div class="swatches">' + T.HAIRS.map(function(h) {
      return '<button class="chip" type="button" data-key="hair" data-value="' + h + '" aria-pressed="' + (d.look.hair === h) +
        '">' + esc(t('app_hair_' + h)) + '</button>';
    }).join('') + '</div>';
    return '<div class="builder"><div class="preview" data-preview>' + T.avatar({role: d.role, nameform: d.nameform,
      lineage: d.lineage, look: d.look, stage: stage}) + '</div><div class="rows">' +
      row('skin', swatches('skin', shows.art ? [T.SKINS[0], T.SKINS[2], T.SKINS[5]] : T.SKINS)) +
      row('hair', hairs) + row('haircolour', swatches('haircolour', T.HAIRCOLOURS)) +
      row('outfit', swatches('outfit', T.OUTFITS)) + '</div></div>';
  }

  /**
   * Whether the look of a character can change at all.
   *
   * @param {Object} d role, nameform, lineage.
   * @param {number} stage
   * @return {boolean}
   */
  function lookChanges(d, stage) {
    var shows = T.lookShows({role: d.role, nameform: d.nameform, lineage: d.lineage, stage: stage});
    return shows.skin || shows.hair || shows.haircolour || shows.outfit;
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
    var avatar = T.avatar({role: c.role, nameform: c.nameform, lineage: c.lineage, look: c.appearance, stage: c.stage,
      label: label, portrait: portrait});
    // The aura bought in the market, seen by everybody.
    return c.aura ? '<span class="aura ' + esc(c.aura) + '">' + avatar + '</span>' : avatar;
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
    if (T.fx) {
      T.fx.quiet(w.quiet);
    }
    var c = w.character;
    var m = c.member || {energy: 0, breath: 0};
    var pct = c.levelneeded ? Math.round(100 * c.levelinto / c.levelneeded) : 100;
    var pending = w.challenges.filter(function(ch) {
      return !ch.status || ch.status === 'notyet';
    }).length + w.activities.filter(function(a) {
      return a.tracked && !a.done;
    }).length;
    app.innerHTML = '<div class="sky"></div><div class="world">' +
      '<header class="hud"><button class="me" data-tab="me" aria-label="' + esc(t('app_tab_me')) + '"><span class="face">' +
      avatarOf(c, '', true) + '</span><span class="who"><b>' + esc(myName()) + '</b><small>' + esc(title(c)) + '</small></span>' +
      '</button><div class="ring" style="--p:' + pct + '" title="' + esc(t('app_level')) + ' ' + c.level + '"><b>' +
      c.level + '</b></div>' + (T.fx && !w.quiet ? '<button type="button" class="sound" data-sound aria-pressed="' + !T.fx.muted() +
      '" aria-label="' + esc(t('app_sound')) + '" title="' + esc(t('app_sound')) + '">' + (T.fx.muted() ? '🔇' : '🔊') +
      '</button>' : '') + '<div class="res">' +
      res('saber', c.saber, t('app_saber')) + res('gems', c.gems, t('app_gems')) +
      res('energy', m.energy + '/' + w.energymax, t('app_energy')) + res('breath', m.breath + '/' + w.breathmax,
        t('app_breath')) + '</div></header>' +
      '<nav class="tabs" aria-label="Tesapé">' + TABS.map(function(tab) {
        var badge = tab === 'challenges' && pending ? ' (' + pending + ')' : '';
        return '<button type="button" data-tab="' + tab + '"' + (state.tab === tab ? ' aria-current="page"' : '') + '>' +
          T.icon(tab) + '<span>' + esc(t('app_tab_' + tab)) + badge + '</span></button>';
      }).join('') + '</nav><main class="content" id="content"></main></div>';
    var sound = app.querySelector('[data-sound]');
    if (sound) {
      sound.onclick = function() {
        var on = T.fx.toggle();
        sound.textContent = on ? '🔊' : '🔇';
        sound.setAttribute('aria-pressed', String(on));
      };
    }
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
    if (state.tab === 'world' || state.tab === 'challenges') {
      bindWorld();
    }
    if (state.tab === 'team') {
      bindTeam();
    }
  }

  /**
   * Shows the world again with what the server says now, with toasts for what was won.
   */
  function refresh() {
    return T.api('get_world', {courseid: cfg.courseid}).then(function(w) {
      state.world = w;
      celebrate();
      world();
    }).catch(function(e) {
      T.toast(esc(e.message), true);
    });
  }
  // The battle layer refreshes the world when a battle gives something.
  T.refreshWorld = refresh;

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

  /* ---------- The mission: map, places and scenes ---------- */

  function nextChapter(m) {
    var open = m.chapters.filter(function(ch) {
      return !ch.done;
    });
    return open.length ? open[0] : m.chapters[m.chapters.length - 1];
  }

  function missionMap(m) {
    var current = nextChapter(m);
    var pts = m.chapters.map(function(ch) {
      return ch.x + ',' + ch.y;
    }).join(' ');
    var lastDone = -1;
    m.chapters.forEach(function(ch, i) {
      if (ch.done) {
        lastDone = i;
      }
    });
    var walked = m.chapters.slice(0, lastDone + 2).map(function(ch) {
      return ch.x + ',' + ch.y;
    }).join(' ');
    var places = m.chapters.map(function(ch) {
      var cls = 'place' + (ch.mine ? ' mine' : '') + (ch.lit ? ' lit' : '') + (ch.id === current.id ? ' here' : '') +
        (ch.id === state.chapter ? ' selected' : '');
      var label = ch.name.length > 22 ? ch.name.slice(0, 21) + '…' : ch.name;
      return '<g class="' + cls + '" data-chapter="' + ch.id + '" tabindex="0" role="button" aria-label="' + esc(ch.name) + '">' +
        '<rect class="hit" x="' + (ch.x - 80) + '" y="' + (ch.y - 32) + '" width="160" height="84" rx="16"/>' +
        (ch.lit ? '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="40" class="classglow"/>' : '') +
        '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="25" class="ring"/>' +
        '<rect x="' + (ch.x - 2) + '" y="' + (ch.y - 4) + '" width="4" height="17" rx="1" fill="#2b2219"/>' +
        '<circle cx="' + ch.x + '" cy="' + (ch.y - 7) + '" r="7" class="flame"/>' +
        '<text x="' + ch.x + '" y="' + (ch.y + 44) + '" text-anchor="middle">' + esc(label) + '</text></g>';
    }).join('');
    var c = state.world.character;
    var me = '<foreignObject x="' + (current.x - 74) + '" y="' + (current.y - 22) + '" width="44" height="44" class="me-marker">' +
      '<div xmlns="http://www.w3.org/1999/xhtml" class="face">' + T.avatar({role: c.role, nameform: c.nameform,
      lineage: c.lineage, look: c.appearance, stage: c.stage, portrait: true}) + '</div></foreignObject>';
    // The painted map of the art pack, the bands of a long map in layers, or a drawing of the valley.
    var height = T.mapHeight(m);
    if (T.fx) {
      T.fx.ambient(T.fx.place(current.art || current.code));
    }
    return '<div class="map-wrap' + (height > 640 ? ' tall' : '') + '"><svg class="map" viewBox="0 0 400 ' + height +
      '" role="group" aria-label="' + esc(m.title) + '"><defs>' + T.MAPGLOW + '</defs>' + T.mapGround(m) +
      T.mapLandmarks(m) + T.mapNight(m) + '<polyline points="' + pts + '" class="road"/>' +
      (walked ? '<polyline points="' + walked + '" class="road walked"/>' : '') + places + me + '</svg></div>';
  }

  function chapterPanel(m, ch, past) {
    var w = state.world;
    var challenge = w.challenges.filter(function(x) {
      return x.id === ch.challengeid;
    })[0];
    var lantern = ch.lit ? t('app_lantern_class') : ch.mine ? t('app_lantern_mine') : t('app_lantern_off');
    var scenes = ch.scenes.map(function(sc) {
      return '<li class="item"><span class="grow"><b>' + esc(sc.title) + '</b><small>' + (sc.minutes ?
        esc(t('app_minutes', sc.minutes)) : '') + (sc.done ? ' · ' + esc(t(sc.better ? 'app_status_better' :
        'app_status_done')) : '') + '</small></span>' + (past ? '' :
        '<button class="btn ' + (sc.done ? 'btn-soft' : 'btn-main') + '" data-scene="' + sc.id + '">' +
        esc(sc.done ? t('app_playagain') : t('app_play')) + '</button>') + '</li>';
    }).join('');
    var acts = past ? [] : w.activities.filter(function(a) {
      return a.chapterid === ch.id;
    });
    // Quests: for whoever wants more, whatever their step; gems and pages, never saber.
    var quests = (ch.quests || []).map(function(q) {
      return '<li class="item"><span class="grow"><b>' + esc(q.title) + '</b><small>' + esc(t('app_quest_step_' +
        (q.step === 4 ? 4 : 3))) + (q.minutes ? ' · ' + esc(t('app_minutes', q.minutes)) : '') + (q.done ? ' · ' +
        esc(t(q.better ? 'app_status_better' : 'app_status_done')) : '') + '</small></span>' + (past ? '' :
        '<button class="btn btn-soft" data-scene="' + q.id + '">' + esc(q.done ? t('app_playagain') : t('app_play')) +
        '</button>') + '</li>';
    }).join('');
    var games = (ch.games || []).map(function(g) {
      return '<li class="item"><span class="mini-icon">' + T.icon('game') + '</span><span class="grow"><b>' + esc(g.title) +
        '</b><small>' + esc(g.done ? t('app_game_done') : t('app_game_help')) + '</small></span>' + (past ? '' :
        '<button class="btn ' + (g.done ? 'btn-soft' : 'btn-main') + '" data-game="' + esc(g.code) + '">' +
        esc(g.done ? t('app_playagain') : t('app_play')) + '</button>') + '</li>';
    }).join('');
    if (past) {
      challenge = null;
    }
    return '<section class="card chapter"><div class="kicker">' + esc(lantern) + '</div><h2>' + esc(ch.name) + '</h2>' +
      '<p>' + esc(ch.intro) + '</p>' + (ch.letter ? '<blockquote class="letter"><small>' + esc(t('app_letter')) +
      '</small>' + esc(ch.letter) + '</blockquote>' : '') + (scenes ? '<ul class="list">' + scenes + '</ul>' : '') +
      (games ? '<h3 style="margin-top:16px">' + esc(t('app_minigames')) + '</h3><ul class="list">' + games + '</ul>' : '') +
      (quests ? '<h3 style="margin-top:16px">' + esc(t('app_quests')) + '</h3><p class="help-soft">' +
      esc(t('app_quests_help')) + '</p><ul class="list">' + quests + '</ul>' : '') +
      (acts.length ? '<h3 style="margin-top:16px">' + esc(t('app_inmoodle')) + '</h3><ul class="list">' +
      acts.map(activityItem).join('') + '</ul>' : '') +
      (challenge ? '<h3 style="margin-top:16px">' + esc(t('app_inclass')) + '</h3><ul class="list">' + challengeItem(challenge) +
      '</ul>' : '') + '</section>';
  }

  /**
   * A Moodle activity in the world: it opens inside the game.
   *
   * @param {Object} a
   * @return {string}
   */
  function activityItem(a) {
    var rewards = [];
    if (a.saber) {
      rewards.push('+' + a.saber + ' ' + t('app_saber'));
    }
    if (a.gems) {
      rewards.push('+' + a.gems + ' ' + t('app_gems'));
    }
    if (a.teamwork) {
      rewards.push(t('app_teamwork'));
    }
    return '<li class="item"><span class="mini-icon"><img src="' + esc(a.icon) + '" alt="" width="24" height="24"></span>' +
      '<span class="grow"><b>' + esc(a.name) + '</b><small>' + esc(rewards.join(' · ')) + '</small></span>' +
      (a.done ? '<span class="tag done">' + esc(t('app_status_done')) + '</span>' : '') + '<button class="btn ' +
      (a.done ? 'btn-soft' : 'btn-main') + '" data-activity="' + a.cmid + '">' + esc(t('app_open')) + '</button></li>';
  }

  /**
   * Opens a Moodle activity in a panel of the world, without the frame of Moodle around it.
   *
   * @param {number} cmid
   */
  function openActivity(cmid) {
    var a = state.world.activities.filter(function(x) {
      return x.cmid === cmid;
    })[0];
    if (!a) {
      return;
    }
    var layer = document.createElement('div');
    layer.className = 'frame-layer';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-label', a.name);
    layer.innerHTML = '<div class="frame-top"><button class="btn btn-soft" type="button" data-close>← ' +
      esc(t('app_backtovalley')) + '</button><b>' + esc(a.name) + '</b><a class="btn btn-soft" href="' + esc(a.url) +
      '" target="_blank" rel="noopener">' + esc(t('app_newtab')) + '</a></div><iframe src="' + esc(a.url) + '" title="' +
      esc(a.name) + '"></iframe>';
    document.body.appendChild(layer);
    document.body.classList.add('in-scene');
    var frame = layer.querySelector('iframe');
    frame.onload = function() {
      try {
        var doc = frame.contentDocument;
        var style = doc.createElement('style');
        style.textContent = FRAME_CSS;
        doc.head.appendChild(style);
      } catch (e) {
        // Another site: it shows as it is.
      }
    };
    layer.querySelector('[data-close]').onclick = function() {
      layer.remove();
      document.body.classList.remove('in-scene');
      refresh();
    };
    layer.querySelector('[data-close]').focus();
  }

  /** What hides the frame of Moodle around an activity opened in the world. */
  var FRAME_CSS = 'nav.navbar,#page-header,.secondary-navigation,#page-footer,footer,.drawer,.drawer-toggles,' +
    '.btn-footer-popover,.footer-popover,.activity-navigation,.course-content-footer,#top-footer,.breadcrumb,' +
    '[data-region="drawer-toggle"],#theme_boost-drawers-courseindex,#theme_boost-drawers-blocks{display:none!important}' +
    'body{padding-top:0!important}#page,#page-wrapper #page{margin:0!important;padding-top:8px!important}' +
    '#page.drawers{margin-left:0!important;margin-right:0!important}';

  function missionView(m, past) {
    var key = 'tesape-mission-' + cfg.courseid + '-' + m.id + '-' + cfg.userid;
    // Whoever has played a scene has begun, also on another device (the tablets of a class are shared).
    var begun = m.chapters.some(function(ch) {
      return ch.mine || ch.scenes.some(function(sc) {
        return sc.done;
      });
    });
    var seen = true;
    try {
      seen = past || begun || !!window.localStorage.getItem(key);
    } catch (e) {
      seen = true;
    }
    if (!seen) {
      // The opening of the mission, with the painting of the valley if the art pack has it.
      var opening = T.artUrl(['scene.prologue.' + m.skin, 'scene.prologue']);
      return '<section class="card story-card">' + (opening ? '<div class="vignette art" aria-hidden="true"><img src="' +
        esc(opening) + '" alt=""></div>' : '') + '<div class="kicker">' + esc(m.subtitle) + '</div><h2>' + esc(m.title) +
        '</h2><p class="lead">' + esc(m.prologue) + '</p><div class="actions" style="justify-content:flex-start">' +
        '<button class="btn btn-main" data-begin="' + esc(key) + '">' + esc(t('app_beginmission')) + '</button></div></section>';
    }
    if (!m.chapters.length) {
      return '<section class="card"><h2>' + esc(m.title) + '</h2><p class="empty">' + esc(t('app_mission_soon')) +
        '</p></section>';
    }
    var chapter = m.chapters.filter(function(ch) {
      return ch.id === state.chapter;
    })[0] || nextChapter(m);
    var lit = m.chapters.filter(function(ch) {
      return ch.mine;
    }).length;
    return '<div class="mission"><div class="mission-head"><div><div class="kicker">' + esc(m.subtitle) + '</div><h2>' +
      esc(m.title) + '</h2></div><div class="tally"><span>' + T.icon('lantern') + ' ' + lit + '/' + m.chapters.length +
      '</span><span>' + T.icon('log') + ' ' + m.pages.length + '/' + m.pagecount + '</span></div></div>' +
      (past ? '' : pageCard()) + '<div class="mission-grid">' + missionMap(m) + chapterPanel(m, chapter, past) + '</div></div>';
  }

  /**
   * The expeditions the class made before the current one: their maps stay lit, to visit.
   *
   * @return {string}
   */
  function expeditionsCard() {
    var list = state.world.expeditions || [];
    if (!list.length) {
      return '';
    }
    return '<section class="card expeditions-card" style="margin-top:16px"><h2>' + esc(t('app_expeditions')) + '</h2>' +
      '<ul class="list">' + list.map(function(x) {
        return '<li class="item"><span class="mini-icon">' + T.icon('lantern') + '</span><span class="grow"><b>' +
          esc(x.title) + '</b><small>' + esc(t('app_expedition_lit', {lit: x.lit, total: x.total})) + ' · ' +
          esc(t('app_expedition_mine', {mine: x.mine, pages: x.pages})) + '</small></span><button class="btn btn-soft" ' +
          'data-visit="' + x.id + '">' + esc(t('app_expedition_visit')) + '</button></li>';
      }).join('') + '</ul></section>';
  }

  /**
   * The page of the day: the creature asks for a quick question of what the student has played; answered right, it
   * eats it and gives breath. Three days in a row and it glows.
   *
   * @return {string}
   */
  function pageCard() {
    var p = state.world.page;
    var c = state.world.character;
    if (!p) {
      return '';
    }
    var name = creatureName(c);
    var glow = p.streak >= p.glow;
    var title = !p.answered ? t('app_page_hungry', name) : p.right ? t('app_page_fed', name) : t('app_page_missed', name);
    var note = glow ? t('app_page_glow', {name: name, days: p.streak}) : p.streak > 1 ? t('app_page_streak', p.streak) : '';
    var options = p.question.options.map(function(o, i) {
      var cls = 'opt';
      if (p.answered) {
        cls += i === p.correct ? ' right' : i === p.mine ? ' wrong' : '';
      }
      return '<button type="button" class="' + cls + '" data-page="' + i + '"' + (p.answered ? ' disabled' : '') + '>' +
        esc(o) + '</button>';
    }).join('');
    var after = '';
    if (p.answered) {
      after = (p.why ? '<p class="help">' + esc(p.why) + '</p>' : '') + '<p class="conseq ' + (p.right ? 'good' : '') + '">' +
        '<span class="what">' + esc(p.right ? t('app_page_breath', p.breath) : t('app_page_tomorrow')) + '</span></p>';
    }
    return '<section class="card page-card' + (glow ? ' glow' : '') + '"><div class="page-head">' +
      T.creature(c.creature, c.creaturestage) + '<div><b>' + esc(title) + '</b>' + (note ? '<small>' + esc(note) +
      '</small>' : '') + (!p.answered ? '<small>' + esc(t('app_page_help', {name: name, breath: p.breath})) + '</small>' :
      '') + '</div></div><p class="page-q">' + esc(p.question.text) + '</p><div class="opts">' + options + '</div>' + after +
      '</section>';
  }

  function bindWorld() {
    app.querySelectorAll('[data-visit]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        T.api('get_expedition', {courseid: cfg.courseid, missionid: Number(b.dataset.visit)}).then(function(r) {
          state.visit = r.mission;
          state.chapter = 0;
          world();
          window.scrollTo(0, 0);
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
    var leave = app.querySelector('[data-leave]');
    if (leave) {
      leave.onclick = function() {
        state.visit = null;
        state.chapter = 0;
        world();
      };
    }
    app.querySelectorAll('[data-page]:not([disabled])').forEach(function(b) {
      b.onclick = function() {
        app.querySelectorAll('[data-page]').forEach(function(o) {
          o.disabled = true;
        });
        T.api('answer_page', {courseid: cfg.courseid, answer: Number(b.dataset.page)}).then(function(w) {
          state.world = w;
          world();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          world();
        });
      };
    });
    var begin = app.querySelector('[data-begin]');
    if (begin) {
      begin.onclick = function() {
        try {
          window.localStorage.setItem(begin.dataset.begin, '1');
        } catch (e) {
          // Without storage the prologue shows again next time; nothing else.
        }
        world();
      };
    }
    // A long map opens where the student is.
    var tall = app.querySelector('.map-wrap.tall');
    var here = tall && tall.querySelector('.place.here .ring');
    if (here) {
      tall.scrollTop += here.getBoundingClientRect().top - tall.getBoundingClientRect().top - tall.clientHeight / 2;
    }
    app.querySelectorAll('[data-chapter]').forEach(function(g) {
      var pickChapter = function() {
        state.chapter = Number(g.dataset.chapter);
        world();
        var panel = app.querySelector('.chapter');
        if (panel && window.innerWidth < 900) {
          panel.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
      };
      g.onclick = pickChapter;
      g.onkeydown = function(ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          pickChapter();
        }
      };
    });
    app.querySelectorAll('[data-scene]').forEach(function(b) {
      b.onclick = function() {
        T.playScene({sceneid: Number(b.dataset.scene), world: state.world, onclose: function(world) {
          state.world = world;
          remember();
          route();
        }});
      };
    });
    app.querySelectorAll('[data-game]').forEach(function(b) {
      b.onclick = function() {
        T.playNest({code: b.dataset.game, world: state.world, onclose: function(world) {
          state.world = world;
          remember();
          route();
        }});
      };
    });
    app.querySelectorAll('[data-activity]').forEach(function(b) {
      b.onclick = function() {
        openActivity(Number(b.dataset.activity));
      };
    });
  }

  function tabWorld() {
    var w = state.world;
    if (state.visit) {
      return '<section class="card visit-head" style="margin-bottom:12px"><div class="kicker">' +
        esc(t('app_expedition_visiting')) + '</div><div class="actions" style="justify-content:flex-start"><button ' +
        'class="btn btn-soft" data-leave>' + esc(t('app_expedition_back')) + '</button></div></section>' +
        missionView(state.visit, true);
    }
    if (w.mission) {
      return missionView(w.mission) + expeditionsCard();
    }
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
    var w = state.world;
    var team = w.team;
    var fam = w.family;
    var html = '';
    if (!team) {
      html += '<section class="card"><p class="empty">' + esc(t('app_noteam')) + '</p></section>';
    } else {
      var wk = team.week;
      var pct = wk.needed ? Math.min(100, Math.round(100 * wk.done / wk.needed)) : 0;
      var epct = team.energymax ? Math.round(100 * team.energy / team.energymax) : 0;
      html += '<section class="card team-card"><div class="kicker">' + esc(t('app_tab_team')) + '</div><h2>' +
        (team.banner.indexOf('banner-lantern') >= 0 ? '<span class="banner-lantern" title="' +
        esc(t('app_market_banner-lantern')) + '">' + T.icon('lantern') + '</span> ' : '') + esc(team.name) + '</h2>' +
        '<div class="team-week"><span class="meter level"><i style="width:' + pct + '%"></i></span>' +
        '<small>' + esc(wk.reached ? t('app_team_reached') : t('app_team_week', {done: wk.done, needed: wk.needed,
        part: wk.part})) + '</small></div><div class="team-week"><span class="meter energy"><i style="width:' + epct +
        '%"></i></span><small>' + esc(t(team.carer ? 'app_team_energy_carer' : 'app_team_energy', {energy: team.energy,
        max: team.energymax})) + '</small></div><ul class="list">' + team.members.map(function(mate) {
          var c = mate.character;
          var m = c && c.member;
          var tag = m && m.status === 'paused' ? '<span class="tag todo">' + esc(t('app_resting')) + '</span>' :
            m && m.status === 'fallen' ? '<span class="tag notyet">' + esc(t('app_wornout')) + '</span>' : '';
          return '<li class="item"><span class="face">' + (c ? avatarOf(c, '', true) : '') + '</span><span class="grow"><b>' +
            esc(mate.name) + (mate.me ? ' (' + esc(t('app_you')) + ')' : '') + '</b><small>' +
            (c ? esc(T.roleName(fam, c.role, c.nameform)) + ' · ' + esc(t('app_level')) + ' ' + c.level :
            esc(t('app_nocharacter'))) + '</small></span>' + tag + (m && m.energy >= 0 ? '<span class="bars">' +
            '<span class="meter energy" ' +
            'title="' + esc(t('app_energy')) + '"><i style="width:' + Math.round(100 * m.energy / w.energymax) +
            '%"></i></span></span>' : '') + '</li>';
        }).join('') + '</ul></section>' + pactsCard(team.pacts);
    }
    html += powersCard();
    if (w.standings.length > 1) {
      var top = Math.max.apply(null, w.standings.map(function(x) {
        return x.saber;
      }).concat([1]));
      html += '<section class="card"><h2>' + esc(t('app_standings')) + '</h2><p class="help-soft">' +
        esc(t('app_standings_help')) + '</p><ul class="list">' + w.standings.map(function(x) {
          var mine = team && x.id === team.id;
          return '<li class="item' + (mine ? ' mine' : '') + '"><span class="grow"><b>' + esc(x.name) + '</b>' +
            '<span class="meter level"><i style="width:' + Math.round(100 * x.saber / top) + '%"></i></span></span>' +
            '<span class="amount up">' + x.saber + '</span>' + (x.reached ? '<span class="tag merit">' +
            esc(t('app_team_goal')) + '</span>' : '') + '</li>';
        }).join('') + '</ul></section>';
    }
    return html;
  }

  /**
   * The commitments of the team in the chapter the class is in: who chose what and how it went, and the student's own
   * choice while the teacher has not marked it.
   *
   * @param {Object} p
   * @return {string}
   */
  function pactsCard(p) {
    if (!p) {
      return '';
    }
    var tags = {open: ['todo', 'app_pact_open'], kept: ['done', 'app_pact_kept'], broken: ['notyet', 'app_pact_broken']};
    var list = p.list.length ? '<ul class="list">' + p.list.map(function(x) {
      var tag = tags[x.status] || tags.open;
      return '<li class="item' + (x.me ? ' mine' : '') + '"><span class="grow"><b>' + esc(x.name) + (x.me ? ' (' +
        esc(t('app_you')) + ')' : '') + '</b><small>' + esc(x.text) + '</small></span><span class="tag ' + tag[0] + '">' +
        esc(t(tag[1])) + '</span></li>';
    }).join('') + '</ul>' : '<p class="empty">' + esc(t('app_pacts_none')) + '</p>';
    var choose = '';
    if (!p.status || p.status === 'open') {
      choose = '<p class="pact-ask"><b>' + esc(t(p.mine < 0 ? 'app_pacts_choose' : 'app_pacts_change')) + '</b></p>' +
        '<div class="pact-options">' + p.options.map(function(text, i) {
          return '<button type="button" class="opt pact-opt" data-pact="' + i + '" aria-pressed="' + (p.mine === i) + '">' +
            esc(text) + '</button>';
        }).join('') + '</div>';
    }
    return '<section class="card pacts-card"><h3>' + esc(t('app_pacts', p.chapter)) + '</h3><p class="help-soft">' +
      esc(t('app_pacts_help', {breath: p.breath, loss: p.loss})) + '</p>' + (p.done ? '<p class="conseq good">' +
      '<span class="what">' + esc(t('app_pacts_done', p.breath)) + '</span></p>' : '') + list + choose + '</section>';
  }

  /**
   * The powers of the student: what each does, what it costs, and the latest requests.
   *
   * @return {string}
   */
  function powersCard() {
    var w = state.world;
    var c = w.character;
    var m = c.member || {};
    var dots = '';
    for (var i = 0; i < w.breathmax; i++) {
      dots += '<i class="' + (i < m.breath ? 'on' : '') + '"></i>';
    }
    var blocked = m.status === 'paused' ? t('app_power_paused') : m.status === 'fallen' ? t('app_power_wornout') : '';
    var mates = (w.team ? w.team.members : []).filter(function(mate) {
      var mm = mate.character && mate.character.member;
      return !mate.me && mm && mm.status !== 'paused';
    });
    var fallen = mates.filter(function(mate) {
      return mate.character.member.status === 'fallen';
    });
    var list = w.powers.map(function(p) {
      var why = p.locked ? t('app_power_level', p.minlevel) : p.pending ? t('app_power_pending') :
        (p.target !== 'none' && !w.team) ? t('app_power_noteam') : m.breath < p.cost ? t('app_power_breath') : blocked ||
        (p.target === 'fallen' && !fallen.length ? t('app_power_nofallen_short') : '') ||
        (p.target === 'mate' && !mates.length ? t('app_power_nomates_short') : '');
      var open = state.power === p.id && !why;
      return '<li class="item power' + (p.locked ? ' locked' : '') + '"><div class="power-head"><span class="mini-icon">' +
        T.icon('power') + '</span><b>' + esc(p.name) + '</b><span class="cost" title="' + esc(t('app_breath')) + '">' +
        p.cost + ' ' + T.icon('breath') + '</span></div><p class="power-desc">' + esc(p.description) + '</p>' +
        '<div class="power-foot">' + (why ? '<span class="tag todo">' + esc(why) + '</span>' : '<button class="btn ' +
        (open ? 'btn-soft' : 'btn-main') + '" data-power="' + p.id + '">' + esc(open ? t('app_cancel') : t('app_power_use')) +
        '</button>') + '</div>' + (open ? powerConfirm(p) : '') + '</li>';
    }).join('');
    var requests = w.powerrequests.map(function(r) {
      return '<li class="item"><span class="grow"><b>' + esc(r.name) + '</b><small>' + (r.target ? esc(t('app_power_for',
        r.target)) + ' · ' : '') + esc(when(r.time)) + '</small></span><span class="tag ' + ({pending: 'todo',
        approved: 'done', used: 'done', rejected: 'notyet'}[r.status] || 'todo') + '">' + esc(t('app_power_' + r.status)) +
        '</span></li>';
    }).join('');
    return '<section class="card"><div class="section-title"><h2>' + esc(t('app_powers')) + '</h2><span class="breath-dots" ' +
      'title="' + esc(t('app_breath')) + ' ' + m.breath + '/' + w.breathmax + '">' + dots + '</span></div><p class="help-soft">' +
      esc(t('app_powers_help')) + '</p>' + (list ? '<ul class="list">' + list + '</ul>' : '<p class="empty">' +
      esc(t('app_nopowers')) + '</p>') + (requests ? '<h3 style="margin-top:16px">' + esc(t('app_power_requests')) +
      '</h3><ul class="list">' + requests + '</ul>' : '') + '</section>';
  }

  /**
   * Asking for a power: who it is for, if it is used on a teammate, and the confirmation.
   *
   * @param {Object} p
   * @return {string}
   */
  function powerConfirm(p) {
    var w = state.world;
    var mates = [];
    if (p.target === 'mate' || p.target === 'fallen') {
      mates = (w.team ? w.team.members : []).filter(function(mate) {
        var m = mate.character && mate.character.member;
        if (mate.me || !m || m.status === 'paused') {
          return false;
        }
        return p.target === 'fallen' ? m.status === 'fallen' : true;
      });
      if (!mates.length) {
        return '<div class="power-ask"><p class="help-soft">' + esc(t(p.target === 'fallen' ? 'app_power_nofallen' :
          'app_power_nomates')) + '</p></div>';
      }
    }
    return '<div class="power-ask">' + (mates.length ? '<p>' + esc(t('app_power_who')) + '</p><div class="pills" ' +
      'style="justify-content:flex-start">' + mates.map(function(mate, i) {
        return '<button class="chip" type="button" data-target="' + mate.userid + '" aria-pressed="' + (i === 0) + '">' +
          esc(mate.name) + '</button>';
      }).join('') + '</div>' : '') + '<p>' + esc(t('app_power_confirm', {name: p.name, cost: p.cost})) + '</p>' +
      '<button class="btn btn-main" data-ask="' + p.id + '">' + esc(t('app_power_ask')) + '</button></div>';
  }

  function bindTeam() {
    app.querySelectorAll('[data-pact]').forEach(function(b) {
      b.onclick = function() {
        if (b.getAttribute('aria-pressed') === 'true') {
          return;
        }
        app.querySelectorAll('[data-pact]').forEach(function(o) {
          o.disabled = true;
        });
        T.api('choose_pact', {courseid: cfg.courseid, option: Number(b.dataset.pact)}).then(function(w) {
          state.world = w;
          T.toast(esc(t('app_pact_chosen')));
          world();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          world();
        });
      };
    });
    app.querySelectorAll('[data-power]').forEach(function(b) {
      b.onclick = function() {
        var id = Number(b.dataset.power);
        state.power = state.power === id ? 0 : id;
        world();
      };
    });
    app.querySelectorAll('[data-target]').forEach(function(b) {
      b.onclick = function() {
        app.querySelectorAll('[data-target]').forEach(function(o) {
          o.setAttribute('aria-pressed', o === b);
        });
      };
    });
    var ask = app.querySelector('[data-ask]');
    if (ask) {
      ask.onclick = function() {
        var target = app.querySelector('[data-target][aria-pressed="true"]');
        ask.disabled = true;
        T.api('use_power', {courseid: cfg.courseid, powerid: Number(ask.dataset.ask),
          targetuserid: target ? Number(target.dataset.target) : 0}).then(function(w) {
          state.world = w;
          state.power = 0;
          var last = w.powerrequests[0];
          T.toast(esc(last && last.status !== 'pending' ? t('app_power_done') : t('app_power_asked')));
          world();
        }).catch(function(e) {
          ask.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    }
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
    var acts = state.world.activities;
    return (acts.length ? '<section class="card"><h2>' + esc(t('app_inmoodle')) + '</h2><p style="color:var(--text-soft)">' +
      esc(t('app_activities_help')) + '</p><ul class="list">' + acts.map(activityItem).join('') + '</ul></section>' : '') +
      '<section class="card"' + (acts.length ? ' style="margin-top:16px"' : '') + '><h2>' + esc(t('app_inclass')) + '</h2>' +
      '<p style="color:var(--text-soft)">' + esc(t('app_challenges_help')) + '</p>' + (list.length ? '<ul class="list">' +
      list.map(challengeItem).join('') + '</ul>' : '<p class="empty">' + esc(t('app_nochallenges')) + '</p>') + '</section>';
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
    var w = state.world;
    var book = '';
    if (w.mission) {
      book = '<section class="card parchment book"><h2>' + esc(w.mission.book || t('app_book')) + '</h2><p class="book-count">' +
        esc(t('app_pages', w.mission.pages.length + '/' + w.mission.pagecount)) + '</p>' + (w.mission.pages.length ?
        w.mission.pages.map(function(pg) {
          return '<article class="page"><h3>' + esc(pg.title) + '</h3><p>' + esc(pg.text) + '</p></article>';
        }).join('') : '<p>' + esc(t('app_nopages')) + '</p>') + '</section>';
    }
    var items = w.items.length ? '<section class="card"><h2>' + esc(t('app_items')) + '</h2><ul class="list">' +
      w.items.map(function(i) {
        var art = T.artUrl(['item.' + i.code]);
        return '<li class="item">' + (art ? '<img class="item-art" src="' + esc(art) + '" alt="">' :
          '<span style="width:22px;height:22px;flex:none">' + T.icon('gems') + '</span>') +
          '<span class="grow"><b>' + esc(i.name) + '</b><small>' + esc(i.description) + '</small></span></li>';
      }).join('') + '</ul></section>' : '';
    return '<div class="grid2">' + book + items + '</div><section class="card" style="margin-top:16px"><h2>' +
      esc(t('app_tab_log')) + '</h2>' + logList(w.log) + '</section>';
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
    var glow = w.page && w.page.streak >= w.page.glow ? ' glow' : '';
    var creature = c.creature ? '<div class="creature-box' + glow + '">' + T.creature(c.creature, c.creaturestage) + '<b>' +
      esc(creatureName(c)) + '</b><small>' + esc(t('app_creaturestage', c.creaturestage)) + '</small></div>' : '';
    return '<section class="card sheet"><div class="sheet-art">' + avatarOf(c, title(c)) + creature + '</div><div class="stats"><h2>' + esc(myName()) +
      '</h2><p style="color:var(--text-soft)">' + esc(title(c)) + ' · ' + esc(t('app_stage', c.stage)) + '</p>' +
      '<div class="stat"><span>' + esc(t('app_level')) + '</span><b>' + c.level + '</b></div>' +
      '<div class="meter level" style="margin:6px 0 4px"><i style="width:' + pct + '%"></i></div><small style="color:' +
      'var(--text-soft)">' + esc(c.levelneeded ? t('app_tonext', c.levelneeded - c.levelinto) : t('app_maxlevel')) +
      '</small><div class="stat"><span>' + esc(t('app_saber')) + '</span><b>' + c.saber + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_renown')) + '</span><b>' + (m.renown || 0) + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_gems')) + '</span><b>' + c.gems + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_energy')) + '</span><b>' + (m.energy || 0) + ' / ' + w.energymax + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_breath')) + '</span><b>' + (m.breath || 0) + ' / ' + w.breathmax + '</b></div>' +
      '<div class="actions no-print" style="justify-content:flex-start;margin-top:16px">' + (lookChanges(c, c.stage) ?
      '<button class="btn btn-soft" data-edit>' + esc(t('app_changelook')) + '</button>' : '') +
      '<button class="btn btn-soft" data-print>' + esc(t('app_print')) +
      '</button><a class="btn btn-soft" href="' + esc(cfg.courseurl) + '">' + esc(t('app_exit')) + '</a></div></div>' +
      '</section>' + (w.hascoat && c.creature ? '<section class="card no-print" style="margin-top:16px"><h2>' +
      esc(t('app_coat_title', creatureName(c))) + '</h2><p class="help-soft">' + esc(t('app_coat_help')) + '</p>' +
      T.coatPicker(w, function() {}).html + '</section>' : '') + marketCard() + albumCard() + namesCard() + ideasCard();
  }

  /**
   * The market of the valley: paints for the creature and auras for the hero, and the lantern of the team's banner,
   * which the team pays for together.
   *
   * @return {string}
   */
  function marketCard() {
    var w = state.world;
    var c = w.character;
    if (!w.market || !w.market.length) {
      return '';
    }
    var items = w.market.map(function(x) {
      var action;
      var art = T.artUrl(['market.' + x.code]);
      var pic = art ? '<img class="item-art" src="' + esc(art) + '" alt="">' : '';
      if (x.team) {
        var pct = Math.min(100, Math.round(100 * x.given / x.price));
        action = x.owned ? '<span class="tag done">' + esc(t('app_market_team_done')) + '</span>' : '<span class="row-actions">' +
          '<button class="chip" type="button" data-give="' + x.code + ':1"' + (c.gems < 1 ? ' disabled' : '') + '>+1</button>' +
          '<button class="chip" type="button" data-give="' + x.code + ':5"' + (c.gems < 1 ? ' disabled' : '') + '>+5</button>' +
          '</span>';
        return '<li class="item">' + pic + '<span class="grow"><b>' + esc(t('app_market_' + x.code)) + '</b><small>' +
          esc(t('app_market_team_help', {given: x.given, price: x.price, mine: x.mine})) + '</small><span class="meter level">' +
          '<i style="width:' + pct + '%"></i></span></span>' + action + '</li>';
      }
      if (!x.owned) {
        action = '<button class="btn btn-soft" type="button" data-buy="' + x.code + '"' + (c.gems < x.price ? ' disabled' : '') +
          '>' + esc(t('app_market_buy', x.price)) + '</button>';
      } else if (x.worn) {
        action = '<button class="chip" type="button" data-takeoff="' + x.kind + '" aria-pressed="true">' +
          esc(t('app_market_worn')) + '</button>';
      } else {
        action = '<button class="chip" type="button" data-wear="' + x.code + '">' + esc(t('app_market_wear')) + '</button>';
      }
      return '<li class="item">' + pic + '<span class="grow"><b>' + esc(t('app_market_' + x.code)) + '</b><small>' +
        esc(t('app_market_kind_' + x.kind)) + '</small></span>' + action + '</li>';
    }).join('');
    return '<section class="card no-print market-card" style="margin-top:16px"><h2>' + esc(t('app_market')) + '</h2>' +
      '<p class="help-soft">' + esc(t('app_market_help', c.gems)) + '</p><ul class="list">' + items + '</ul></section>';
  }

  /**
   * The album of the field guide: a card for each entry of the student or their team, golden with its food chain.
   *
   * @return {string}
   */
  function albumCard() {
    var a = state.world.album;
    if (!a) {
      return '';
    }
    var golden = a.cards.filter(function(x) {
      return x.golden;
    }).length;
    return '<section class="card no-print album-card" style="margin-top:16px"><h2>' + esc(t('app_album')) + '</h2>' +
      '<p class="help-soft">' + esc(t('app_album_help', {cards: a.cards.length, golden: golden, class: a['class']})) + '</p>' +
      (a.cards.length ? '<div class="album">' + a.cards.map(function(x) {
        // The picture of its group, from the art pack: the name of the group without accents (Mamífero: album.mamifero).
        var art = x.group ? T.artUrl(['album.' + String(x.group).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')]) : null;
        return '<div class="album-card' + (x.golden ? ' golden' : '') + '">' + (art ? '<img src="' + esc(art) + '" alt="">' : '') +
          '<b>' + esc(x.name) + '</b>' + (x.group ?
          '<small>' + esc(x.group) + '</small>' : '') + (x.mine ? '<small class="mine">' + esc(t('app_album_mine')) + '</small>' :
          '') + '</div>';
      }).join('') + '</div>' : '<p class="empty">' + esc(t('app_album_empty')) + '</p>') + '<div class="actions" style="' +
      'justify-content:flex-start"><button class="btn btn-soft" data-activity="' + a.cmid + '">' + esc(t('app_album_open')) +
      '</button></div></section>';
  }

  /**
   * The names the student can give: their hero name, where the course allows it, and the name of their creature. From
   * the list it counts at once; their own waits for a teacher.
   *
   * @return {string}
   */
  function namesCard() {
    var w = state.world;
    var c = w.character;
    var n = w.names;
    if (!n || (!n.heroes && !c.creature)) {
      return '';
    }
    function part(what, title, current, options, pending) {
      return '<div class="names-part"><h3>' + esc(title) + '</h3><p class="help-soft">' + esc(current ?
        t('app_name_now', current) : t('app_hero_none')) + '</p><p><b>' + esc(t('app_name_pick')) + '</b></p>' +
        '<div class="chips">' + options.map(function(o, i) {
          return '<button type="button" class="chip" data-name="' + what + ':' + i + '" aria-pressed="' + (o === current) +
            '">' + esc(o) + '</button>';
        }).join('') + '</div>' + (n.own ? '<label class="names-own"><span>' + esc(t('app_name_own')) + '</span><span ' +
        'class="row"><input type="text" maxlength="20" data-ownname="' + what + '"><button type="button" class="btn ' +
        'btn-soft" data-sendname="' + what + '">' + esc(t('app_name_send')) + '</button></span></label><small ' +
        'class="help-soft">' + esc(t('app_name_own_help')) + '</small>' : '') + (pending ? '<p class="tag todo">' +
        esc(t('app_name_pending', pending)) + '</p>' : '') + '</div>';
    }
    return '<section class="card no-print names-card" style="margin-top:16px"><h2>' + esc(t('app_names')) + '</h2>' +
      (n.heroes ? part('hero', t('app_hero_name'), c.nickname, n.hero, n.heropending) : '') +
      (c.creature ? part('creature', t('app_creature_name'), creatureName(c), n.creature, n.creaturepending) : '') +
      '</section>';
  }

  /**
   * The box of ideas: the student tells their teachers an idea for the game or the class.
   *
   * @return {string}
   */
  function ideasCard() {
    var ideas = state.world.ideas;
    return '<section class="card no-print" style="margin-top:16px"><h2>' + T.icon('idea').replace('<svg',
      '<svg width="22" height="22" style="vertical-align:-4px;color:var(--lantern)"') + ' ' + esc(t('app_ideas')) + '</h2>' +
      '<p class="help-soft">' + esc(t('app_ideas_help')) + '</p><label class="sr-only" for="tesape-idea">' +
      esc(t('app_ideas')) + '</label><textarea id="tesape-idea" data-idea rows="3" maxlength="1000" placeholder="' +
      esc(t('app_ideas_placeholder')) + '"></textarea><div class="actions" style="justify-content:space-between;' +
      'margin-top:10px"><label class="chip" style="display:inline-flex;gap:8px;align-items:center"><input ' +
      'type="checkbox" data-anon> ' + esc(t('app_ideas_anonymous')) + '</label><button class="btn btn-main" data-sendidea>' +
      esc(t('app_ideas_send')) + '</button></div>' + (ideas.length ? '<ul class="list" style="margin-top:12px">' +
      ideas.map(function(i) {
        return '<li class="item"><span class="grow">' + esc(i.message) + '<small>' + esc(when(i.time)) +
          (i.anonymous ? ' · ' + esc(t('app_ideas_noname')) : '') + '</small></span><span class="tag ' +
          (i.status === 'read' ? 'done' : 'todo') + '">' + esc(t('app_ideas_' + i.status)) + '</span></li>';
      }).join('') + '</ul>' : '') + '</section>';
  }

  function creatureName(c) {
    try {
      return JSON.parse(c.creature || '{}').name || '';
    } catch (e) {
      return '';
    }
  }

  function bindMe() {
    var c = state.world.character;
    var shop = function(fn, args, done) {
      app.querySelectorAll('[data-buy], [data-wear], [data-takeoff], [data-give]').forEach(function(o) {
        o.disabled = true;
      });
      T.api(fn, Object.assign({courseid: cfg.courseid}, args)).then(function(w) {
        state.world = w;
        if (done) {
          T.toast(esc(done));
        }
        world();
      }).catch(function(e) {
        T.toast(esc(e.message), true);
        world();
      });
    };
    app.querySelectorAll('[data-buy]').forEach(function(b) {
      b.onclick = function() {
        shop('market_buy', {code: b.dataset.buy}, t('app_market_bought'));
      };
    });
    app.querySelectorAll('[data-wear]').forEach(function(b) {
      b.onclick = function() {
        shop('market_wear', {code: b.dataset.wear});
      };
    });
    app.querySelectorAll('[data-takeoff]').forEach(function(b) {
      b.onclick = function() {
        shop('market_wear', {code: b.dataset.takeoff});
      };
    });
    app.querySelectorAll('[data-give]').forEach(function(b) {
      b.onclick = function() {
        var parts = b.dataset.give.split(':');
        shop('market_buy', {code: parts[0], amount: Number(parts[1])}, t('app_market_given'));
      };
    });
    app.querySelectorAll('[data-activity]').forEach(function(b) {
      b.onclick = function() {
        openActivity(Number(b.dataset.activity));
      };
    });
    var saveName = function(what, option, own) {
      app.querySelectorAll('[data-name], [data-sendname]').forEach(function(o) {
        o.disabled = true;
      });
      T.api('set_name', {courseid: cfg.courseid, what: what, option: option, own: own || ''}).then(function(w) {
        state.world = w;
        T.toast(esc(t(option < 0 ? 'app_name_sent' : 'app_name_saved')));
        world();
      }).catch(function(e) {
        T.toast(esc(e.message), true);
        world();
      });
    };
    app.querySelectorAll('[data-name]').forEach(function(b) {
      b.onclick = function() {
        var parts = b.dataset.name.split(':');
        if (b.getAttribute('aria-pressed') !== 'true') {
          saveName(parts[0], Number(parts[1]));
        }
      };
    });
    app.querySelectorAll('[data-sendname]').forEach(function(b) {
      b.onclick = function() {
        var input = app.querySelector('[data-ownname="' + b.dataset.sendname + '"]');
        if (input && input.value.trim()) {
          saveName(b.dataset.sendname, -1, input.value.trim());
        }
      };
    });
    var edit = app.querySelector('[data-edit]');
    var send = app.querySelector('[data-sendidea]');
    if (send) {
      send.onclick = function() {
        var box = app.querySelector('[data-idea]');
        if (!box.value.trim()) {
          box.focus();
          return;
        }
        send.disabled = true;
        T.api('send_idea', {courseid: cfg.courseid, message: box.value, anonymous: app.querySelector('[data-anon]').checked})
          .then(function(w) {
            state.world = w;
            T.toast(esc(t('app_ideas_sent')));
            world();
          }).catch(function(e) {
            send.disabled = false;
            T.toast(esc(e.message), true);
          });
      };
    }
    if (state.world.hascoat && c.creature) {
      T.coatPicker(state.world, function(w) {
        state.world = w;
        T.toast(esc(t('app_coat_done')));
        world();
      }).bind(app);
    }
    if (!state.editing) {
      // The sheet; the button to change the look is only there when the stage has something to change.
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
      }
      var print = app.querySelector('[data-print]');
      if (print) {
        print.onclick = function() {
          window.print();
        };
      }
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
