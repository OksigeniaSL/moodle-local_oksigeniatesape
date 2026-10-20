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
      // The first time, the prologue of the mission: what happened in the valley, before choosing who to be.
      if (w.opening && w.opening.beats.length) {
        opening(0);
        return;
      }
      go('role');
      return;
    }
    celebrate();
    world();
    sendWaiting();
  }

  /* ---------- The first time: a story that ends with the character ---------- */

  /**
   * A vignette of the prologue: its picture (of the pack, or drawn until it arrives), who speaks and what is told; the
   * first one sees a lantern go out. «Go on» leads to the next one and, after the last, to the question of the role.
   *
   * @param {number} i
   */
  function opening(i) {
    var o = state.world.opening;
    var b = o.beats[i];
    if (!b) {
      go('role');
      return;
    }
    var art = T.artUrl(['scene.' + b.image + '.' + o.skin, 'scene.' + b.image, i === 0 ? 'scene.prologue' : '']);
    var face = b.speaker ? T.artUrl(['speaker.' + b.speaker]) : null;
    app.innerHTML = '<div class="story opening' + (i === 0 ? ' lantern-out' : '') + '">' + (art ? '<div class="vignette art" ' +
      'aria-hidden="true"><img src="' + esc(art) + '" alt=""></div>' : T.vignette(i === 0 ? 'region' : 'mission')) +
      (i === 0 ? '<span class="opening-lantern" aria-hidden="true">' + T.icon('lantern') + '</span>' : '') +
      (b.speakername ? '<p class="opening-who">' + (face ? '<img src="' + esc(face) + '" alt="">' : '') + '<b>' +
      esc(b.speakername) + '</b></p>' : '') + '<p class="lead">' + esc(b.text) + '</p><div class="actions sticky-actions">' +
      '<button class="btn btn-main" data-goon>' + esc(t(i + 1 < o.beats.length ? 'app_next' : 'app_opening_go')) +
      '</button></div></div>';
    var on = app.querySelector('[data-goon]');
    on.focus();
    on.onclick = function() {
      opening(i + 1);
    };
    window.scrollTo(0, 0);
  }

  function steps(current) {
    var list = ['role'];
    // People come as a boy or a girl on the cards of the roles; animals with two names (Oso, Osa) choose it after.
    if (state.draft.role && !T.isPeople(state.world.family) && T.hasForms(state.world.family, state.draft.role)) {
      list.push('form');
    }
    // The lineage and the look, on one screen.
    if (T.hasLineages(state.world.family) || lookChanges(state.draft, 1)) {
      list.push('look');
    }
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
    if (!step) {
      create();
      return;
    }
    ({role: chooseRole, form: chooseForm, look: chooseLook})[step]();
    window.scrollTo(0, 0);
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
          esc(T.roleName(fam, role, form)) + '</strong>' + (state.world.opening ? '<em class="role-does">' +
          esc(t('app_role_does_' + role)) + '</em>' : '') + '<small data-roleline>' + (on ? esc(t('roledesc_' + role)) : '') +
          '</small></button>';
      }).join('');
    }).join('');
    // What each role does, in one line, only on the card touched. In the prologue, the question of the druid.
    var ask = state.world.opening && state.world.opening.ask;
    app.innerHTML = '<div class="story">' + s.bar + '<h2>' + esc(ask || t('app_who')) + '</h2><div class="choices">' + cards +
      '</div><div class="actions sticky-actions">' +
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
          o.querySelector('[data-roleline]').textContent = o === b ? t('roledesc_' + b.dataset.role) : '';
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
    var d = state.draft;
    d.look = d.look || T.defaultLook(d.role);
    var lineages = T.hasLineages(state.world.family);
    d.lineage = d.lineage || (lineages ? 'human' : '');
    var draw = function() {
      var cards = lineages ? '<div class="choices lineages">' + T.LINEAGES.map(function(l) {
        return '<button class="choice" type="button" data-lineage="' + l + '" aria-pressed="' + (d.lineage === l) + '">' +
          draftAvatar({lineage: l}) + '<strong>' + esc(T.lineageName(l, d.nameform)) + '</strong></button>';
      }).join('') + '</div><p class="help">' + esc(t('app_lineage_help')) + '</p>' : '';
      // In the prologue, the mirror of the river.
      var mirror = state.world.opening && state.world.opening.mirror;
      app.innerHTML = '<div class="story">' + s.bar + '<h2>' + esc(mirror || t('app_look')) + '</h2>' + cards +
        (lookChanges(d, 1) ? builder(d, 1) : '') + nav(s) + '</div>';
      app.querySelectorAll('[data-lineage]').forEach(function(b) {
        b.onclick = function() {
          d.lineage = b.dataset.lineage;
          draw();
          app.querySelector('[data-lineage="' + d.lineage + '"]').focus();
        };
      });
      bindBuilder(d, 1);
      bindNav(s);
    };
    draw();
  }

  /** The titles of the story as the hero grows, from the level they start at. */
  var RANKS = [[1, 'apprentice'], [5, 'walker'], [12, 'tracker'], [25, 'naturalist'], [40, 'guardian']];

  function rankName(key, form) {
    return t('app_rank_' + key + '_' + (form === 'f' ? 'f' : 'm'));
  }

  /**
   * The title of the story of a hero, by their level: Apprentice of the valley, Walker, Tracker…
   *
   * @param {Object} c
   * @return {string}
   */
  function rank(c) {
    return rankName(RANKS.filter(function(r) {
      return c.level >= r[0];
    }).pop()[1], c.nameform);
  }

  /**
   * What comes next as the hero grows, whichever comes first: the next title of the story or the next power.
   *
   * @param {Object} c
   * @return {string}
   */
  function nextUnlock(c) {
    var next = RANKS.filter(function(r) {
      return r[0] > c.level;
    }).map(function(r) {
      return {level: r[0], what: t('app_unlock_rank', rankName(r[1], c.nameform))};
    }).concat((state.world.powers || []).filter(function(p) {
      return p.minlevel > c.level;
    }).map(function(p) {
      return {level: p.minlevel, what: t('app_unlock_power', p.name)};
    })).sort(function(a, b) {
      return a.level - b.level;
    })[0];
    return next ? t('app_unlock', next) : '';
  }

  /**
   * What the role of the hero does, as numbers and a sentence: the passive of each role, seen.
   *
   * @param {Object} c
   * @return {string}
   */
  function traits(c) {
    // In the middle of a sentence: «Como druida», not «Como Druida».
    var name = T.roleName(state.world.family, c.role, c.nameform);
    name = name.charAt(0).toLocaleLowerCase(cfg.lang || 'es') + name.slice(1);
    return '<p class="traits"><b>' + esc(t('app_trait_' + c.role)) + '</b><span>' + esc(t('app_trait_' + c.role + '_help', name)) +
      '</span></p>';
  }

  /**
   * The talents of a student on their sheet, as medals; and the choice waiting, if they left it for later.
   *
   * @param {Object} c The character.
   * @return {string}
   */
  function talentsHtml(c) {
    var list = c.talents || [];
    return (list.length ? '<ul class="talent-medals">' + list.map(function(x) {
      return '<li><b>' + esc(x.name) + '</b><small>' + esc(x.description) + '</small></li>';
    }).join('') + '</ul>' + (c.talentsoff ? '<p class="help-soft">' + esc(t('app_talents_off')) + '</p>' : '') : '') +
      (c.talentpending ? '<p><button type="button" class="btn btn-main" data-choosetalent>' + esc(t('app_talent_choose')) +
      '</button></p>' : '') + (c.branchpending ? '<p><button type="button" class="btn btn-main" data-choosebranch>' +
      esc(t('app_branch_choose', creatureName(c))) + '</button></p>' : '') + (c.ascentpending ? '<p><button type="button" ' +
      'class="btn btn-main" data-ascentopen>' + esc(t('app_ascent_choose')) + '</button></p>' : '');
  }

  /**
   * A choice that stays: a touch marks a card (another touch changes it), and a button keeps it. Nothing is chosen by
   * one touch alone.
   *
   * @param {Element} layer
   * @param {string} selector The cards.
   * @param {Function} keep Called with the card kept.
   */
  function markThenKeep(layer, selector, keep) {
    var cards = layer.querySelectorAll(selector);
    var bar = document.createElement('div');
    bar.className = 'actions keep-bar';
    cards[0].parentNode.insertAdjacentElement('afterend', bar);
    var button = null;
    var marked = null;
    cards.forEach(function(card) {
      card.setAttribute('aria-pressed', 'false');
      card.onclick = function() {
        marked = card;
        cards.forEach(function(x) {
          x.setAttribute('aria-pressed', x === card ? 'true' : 'false');
        });
        if (!button) {
          bar.innerHTML = '<button type="button" class="btn btn-main" data-keep></button>';
          button = bar.querySelector('[data-keep]');
          button.onclick = function() {
            keep(marked, cards, button);
          };
        }
        button.textContent = t('app_keep_this', card.querySelector('b').textContent);
      };
    });
  }

  /**
   * The creature reached its second stage: it chooses its way, land, air or water, with the coat that goes with each.
   * Once; its next stages follow it.
   *
   * @param {Function} [then]
   * @return {Element}
   */
  function branchMoment(then) {
    var c = state.world.character;
    var traits = {};
    try {
      traits = JSON.parse(c.creature || '{}') || {};
    } catch (e) {
      traits = {};
    }
    var coats = {land: 'fur', air: 'feathers', water: 'scales'};
    var layer = moment('<div class="kicker">' + esc(t('app_branch_kicker', creatureName(c))) + '</div><h2 ' +
      'id="tesape-branch-title">' + esc(t('app_branch_title')) + '</h2><p class="help">' + esc(t('app_branch_help')) +
      '</p><div class="talent-cards branch-cards">' + Object.keys(coats).map(function(b) {
        return '<button type="button" class="talent-card" data-branch="' + b + '"><span class="branch-pet" aria-hidden="true">' +
          T.creature(Object.assign({}, traits, {coat: coats[b]}), c.creaturestage) + '</span><b>' + esc(t('app_branch_' + b)) +
          '</b><small>' + esc(t('app_branch_' + b + '_why')) + '</small></button>';
      }).join('') + '</div>', 'tesape-branch-title', then);
    var later = layer.querySelector('[data-close]');
    later.textContent = t('app_talent_later');
    later.className = 'btn btn-soft';
    // Offered by itself once on each device: then it waits on the sheet.
    try {
      window.localStorage.setItem('tesape-branch-' + cfg.courseid + '-' + cfg.userid, '1');
    } catch (e) {
      // Without storage it is offered again next time.
    }
    layer.querySelector('[data-branch]').focus();
    markThenKeep(layer, '[data-branch]', function(b, cards, button) {
      cards.forEach(function(x) {
        x.disabled = true;
      });
      button.disabled = true;
      T.api('choose_branch', {courseid: cfg.courseid, branch: b.dataset.branch}).then(function(r) {
        c.creature = r.creature;
        delete c.branchpending;
        if (T.fx) {
          T.fx.sound('hatch');
          T.fx.sparkles();
        }
        T.toast(esc(t('app_branch_done', creatureName(c))));
        later.click();
        world();
      }).catch(function(e) {
        cards.forEach(function(x) {
          x.disabled = false;
        });
        button.disabled = false;
        T.toast(esc(e.message), true);
      });
    });
    return layer;
  }

  /**
   * What the hero has learnt to do: a medal for each objective of the mission, lit when it is learnt. No numbers.
   *
   * @param {Object} c The character.
   * @return {string}
   */
  function skillsHtml(c) {
    var list = c.skills || [];
    if (!list.length) {
      return '';
    }
    return '<h3 class="skills-title">' + esc(t('app_skills')) + '</h3><ul class="skill-medals">' + list.map(function(s) {
      return '<li class="' + (s.lit ? 'lit' : 'dim') + '"><span class="skill-mark" aria-hidden="true">' + (s.lit ? '✦' : '·') +
        '</span><span>' + esc(s.name) + (s.lit ? '' : '<span class="sr-only"> (' + esc(t('app_skill_todo')) + ')</span>') +
        '</span></li>';
    }).join('') + '</ul><p class="help-soft">' + esc(t('app_skills_help')) + '</p>';
  }

  /**
   * A skill lit since this device saw them: the hero has learnt to do something.
   *
   * @param {Object[]} list The skills newly lit.
   * @param {Function} [then]
   * @return {Element}
   */
  function skillMoment(list, then) {
    return moment('<div class="kicker">' + esc(t('app_skill_kicker')) + '</div><h2 id="tesape-skill-title">' +
      esc(t('app_skill_title')) + '</h2><ul class="skill-medals">' + list.map(function(s) {
        return '<li class="lit"><span class="skill-mark" aria-hidden="true">✦</span><span>' + esc(s.name) + '</span></li>';
      }).join('') + '</ul>', 'tesape-skill-title', then);
  }

  /**
   * The rite of ascent, once in a mission: the four roles in the hero's stage and lineage, theirs marked as theirs. A
   * touch marks one and a button keeps it; keeping their own is staying. Left for later, it waits on the sheet.
   *
   * @param {Function} [then]
   * @return {Element}
   */
  function ascentMoment(then) {
    var c = state.world.character;
    var family = state.world.family;
    var layer = moment('<div class="kicker">' + esc(t('app_ascent_kicker')) + '</div><h2 id="tesape-ascent-title">' +
      esc(t('app_ascent_title')) + '</h2><p class="help">' + esc(t(c.ascentpending === 'final' ? 'app_ascent_final' :
      'app_ascent_lanterns')) + '</p><div class="talent-cards ascent-cards">' + T.ROLES.map(function(role) {
        return '<button type="button" class="talent-card" data-ascent="' + role + '"><span class="ascent-hero" ' +
          'aria-hidden="true">' + T.avatar({role: role, nameform: c.nameform, lineage: c.lineage, look: c.appearance,
          stage: c.stage, family: family}) + '</span><b>' + esc(T.roleName(family, role, c.nameform)) + '</b><small>' +
          esc(t('app_role_does_' + role)) + (role === c.role ? ' · ' + esc(t('app_ascent_yours')) : '') + '</small></button>';
      }).join('') + '</div>', 'tesape-ascent-title', then);
    var later = layer.querySelector('[data-close]');
    later.textContent = t('app_talent_later');
    later.className = 'btn btn-soft';
    // Offered by itself once on each device: then it waits on the sheet.
    try {
      window.localStorage.setItem('tesape-ascent-' + cfg.courseid + '-' + cfg.userid, '1');
    } catch (e) {
      // Without storage it is offered again next time.
    }
    layer.querySelector('[data-ascent="' + c.role + '"]').focus();
    markThenKeep(layer, '[data-ascent]', function(b, cards, button) {
      cards.forEach(function(x) {
        x.disabled = true;
      });
      button.disabled = true;
      T.api('ascend', {courseid: cfg.courseid, role: b.dataset.ascent}).then(function(r) {
        var name = T.roleName(family, r.role, c.nameform);
        T.toast(esc(t(r.role === c.role ? 'app_ascent_stayed' : 'app_ascent_changed', name)));
        c.ascentpending = '';
        if (T.fx) {
          T.fx.sound('levelup');
          T.fx.sparkles();
        }
        later.click();
        // The world again: the hero in their role, and the talents of a new role to choose.
        refresh();
      }).catch(function(e) {
        cards.forEach(function(x) {
          x.disabled = false;
        });
        button.disabled = false;
        T.toast(esc(e.message), true);
      });
    });
    return layer;
  }

  /**
   * A level that brings a talent: two cards of the role, one to keep. Left for later, it waits on the sheet.
   *
   * @param {Object} p level and options.
   * @param {Function} [then]
   * @return {Element}
   */
  function talentMoment(p, then) {
    var layer = moment('<div class="kicker">' + esc(t('app_talent_kicker')) + '</div><h2 id="tesape-talent-title">' +
      esc(t('app_talent_title')) + '</h2><p class="help">' + esc(t('app_talent_help')) + '</p><div class="talent-cards">' +
      p.options.map(function(o) {
        return '<button type="button" class="talent-card" data-talent="' + esc(o.code) + '"><b>' + esc(o.name) + '</b>' +
          '<small>' + esc(o.description) + '</small></button>';
      }).join('') + '</div>', 'tesape-talent-title', then);
    // Offered by itself once on each device: then it waits on the sheet.
    try {
      window.localStorage.setItem('tesape-talent-' + cfg.courseid + '-' + cfg.userid, String(p.level));
    } catch (e) {
      // Without storage it is offered again next time.
    }
    // The cards are the choice: «Later» stays quiet, and the first card has the focus.
    var later = layer.querySelector('[data-close]');
    later.textContent = t('app_talent_later');
    later.className = 'btn btn-soft';
    layer.querySelector('[data-talent]').focus();
    markThenKeep(layer, '[data-talent]', function(b, cards, button) {
      cards.forEach(function(x) {
        x.disabled = true;
      });
      button.disabled = true;
      T.api('choose_talent', {courseid: cfg.courseid, level: p.level, code: b.dataset.talent}).then(function(r) {
        state.world.character.talents = r.talents;
        if (r.talentpending) {
          state.world.character.talentpending = r.talentpending;
        } else {
          delete state.world.character.talentpending;
        }
        if (T.fx) {
          T.fx.sound('levelup');
          T.fx.sparkles();
        }
        T.toast(esc(t('app_talent_chosen', b.querySelector('b').textContent)));
        later.click();
        world();
        // The next level that brought a talent, straight after.
        if (r.talentpending) {
          talentMoment(r.talentpending);
        }
      }).catch(function(e) {
        cards.forEach(function(x) {
          x.disabled = false;
        });
        button.disabled = false;
        T.toast(esc(e.message), true);
      });
    });
    return layer;
  }

  function title(d) {
    var name = T.roleName(state.world.family, d.role, d.nameform);
    var lineage = T.hasLineages(state.world.family) ? T.lineageName(d.lineage, d.nameform) : '';
    return lineage ? name + ' ' + lineage : name;
  }

  /**
   * Creates the character and goes straight into the first scene of the mission (the map comes after it); who arrives
   * with the expedition on its way goes through the welcome instead.
   */
  function create() {
    var d = state.draft;
    var button = app.querySelector('[data-next]');
    if (button) {
      button.disabled = true;
    }
    T.api('create_character', {
      courseid: cfg.courseid,
      role: d.role,
      nameform: d.nameform,
      lineage: T.hasLineages(state.world.family) ? d.lineage : '',
      appearance: JSON.stringify(d.look)
    }).then(function(world) {
      var played = !!(state.world && state.world.opening);
      state.world = world;
      state.tab = 'world';
      remember();
      var m = world.mission;
      // Who played the prologue does not need the card that tells it again.
      if (played && m) {
        try {
          window.localStorage.setItem('tesape-mission-' + cfg.courseid + '-' + m.id + '-' + cfg.userid, '1');
        } catch (e) {
          // Without storage the card shows once; nothing else.
        }
      }
      var first = !world.welcome && m && m.chapters.length ? m.chapters[0].scenes.filter(function(sc) {
        return !sc.done;
      })[0] : null;
      if (!first) {
        route();
        return;
      }
      try {
        window.localStorage.setItem('tesape-mission-' + cfg.courseid + '-' + m.id + '-' + cfg.userid, '1');
      } catch (e) {
        // Without storage the opening of the mission shows once more; nothing else.
      }
      app.innerHTML = '<div class="loading"><div><div class="lamp"></div></div></div>';
      T.playScene({sceneid: first.id, world: world, onclose: function(w) {
        state.world = w;
        route();
      }});
    }).catch(function(e) {
      if (button) {
        button.disabled = false;
      }
      T.toast(esc(e.message), true);
    });
  }

  function nav(s) {
    // The last step creates the character.
    return '<div class="actions sticky-actions">' + (s.prev ? '<button class="btn btn-soft" data-prev>' + esc(t('app_back')) +
      '</button>' : '') + '<button class="btn btn-main" data-next>' + esc(t(s.next ? 'app_next' : 'app_ready')) +
      '</button></div>';
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

  /**
   * The figure of the student, for other parts of the game (the battle on the tablet): empty without a character.
   *
   * @param {boolean} [portrait]
   * @return {string}
   */
  T.myHero = function(portrait) {
    var c = state.world && state.world.character;
    return c && c.role ? avatarOf(c, '', portrait) : '';
  };

  function avatarOf(c, label, portrait) {
    var avatar = T.avatar({role: c.role, nameform: c.nameform, lineage: c.lineage, look: c.appearance, stage: c.stage,
      label: label, portrait: portrait});
    // The aura bought in the market, seen by everybody.
    return c.aura ? '<span class="aura ' + esc(c.aura) + '">' + avatar + '</span>' : avatar;
  }

  /**
   * What was won since the student last looked, on this device: the saber and the gems fly to their counters, and a
   * new level, a new stage or a creature that grew have their moment, on the tablet of the student.
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
    var gained = seen ? [['saber', seen.saber, c.saber], ['gems', seen.gems, c.gems]].filter(function(r) {
      return typeof r[1] === 'number' && r[2] > r[1];
    }) : [];
    // The header shows what there was, and then each one flies to its counter.
    state.loot = {};
    gained.forEach(function(r, i) {
      state.loot[r[0]] = r[1];
      setTimeout(function() {
        delete state.loot[r[0]];
        var counter = app.querySelector('[data-res="' + r[0] + '"]');
        if (counter) {
          T.loot(counter, r[0], r[1], r[2]);
          setTimeout(function() {
            explain(r[0]);
          }, 1300);
        }
      }, 400 + i * 450);
    });
    remember();
    // One moment after the other: the welcome, the new level and the creature, then the badge.
    var moments = [];
    if (state.world.welcome) {
      var w = state.world.welcome;
      state.world.welcome = null;
      moments.push(function(then) {
        welcomeMoment(w, then);
      });
    }
    // What the teacher noticed, with its reason, not seen yet on this device.
    var touches = unseenTouches();
    if (touches.length) {
      moments.push(function(then) {
        touchMoment(touches, then);
      });
    }
    if (seen && typeof seen.level === 'number' && c.level > seen.level) {
      moments.push(function(then) {
        levelMoment(seen, c, then);
      });
    }
    // A level that brings a talent: the choice of two, now or later. By itself once on each device; then it waits on the
    // sheet.
    var tkey = 'tesape-talent-' + cfg.courseid + '-' + cfg.userid;
    var offered = null;
    try {
      offered = window.localStorage.getItem(tkey);
    } catch (e) {
      offered = null;
    }
    if (c.talentpending && offered !== String(c.talentpending.level)) {
      moments.push(function(then) {
        talentMoment(c.talentpending, then);
      });
    }
    // A skill lit since this device last saw them (the first time, they are only noted).
    var skey = 'tesape-skills-' + cfg.courseid + '-' + cfg.userid;
    var lit = (c.skills || []).filter(function(s) {
      return s.lit;
    });
    var known = null;
    try {
      known = JSON.parse(window.localStorage.getItem(skey) || 'null');
    } catch (e) {
      known = null;
    }
    var learnt = known ? lit.filter(function(s) {
      return known.indexOf(s.code) < 0;
    }) : [];
    try {
      window.localStorage.setItem(skey, JSON.stringify(lit.map(function(s) {
        return s.code;
      })));
    } catch (e) {
      // Without storage, nothing to tell next time.
    }
    if (learnt.length) {
      moments.push(function(then) {
        skillMoment(learnt, then);
      });
    }
    if (seen && seen.creaturestage && c.creaturestage > seen.creaturestage) {
      moments.push(function(then) {
        creatureMoment(seen.creaturestage, c, then);
      });
    }
    // Grown to its second stage, the creature chooses its way: by itself once on each device, then on the sheet.
    var branchOffered = null;
    try {
      branchOffered = window.localStorage.getItem('tesape-branch-' + cfg.courseid + '-' + cfg.userid);
    } catch (e) {
      branchOffered = null;
    }
    if (c.branchpending && !branchOffered) {
      moments.push(function(then) {
        branchMoment(then);
      });
    }
    // The rite of ascent: by itself once on each device, then on the sheet.
    var ascentOffered = null;
    try {
      ascentOffered = window.localStorage.getItem('tesape-ascent-' + cfg.courseid + '-' + cfg.userid);
    } catch (e) {
      ascentOffered = null;
    }
    if (c.ascentpending && !ascentOffered) {
      moments.push(function(then) {
        ascentMoment(then);
      });
    }
    // The banner of the team, grown since this device last saw it (the first time, it is only noted).
    var team = state.world.team;
    if (team && team.bannerstage) {
      var fkey = 'tesape-banner-' + cfg.courseid + '-' + team.id;
      var flag = null;
      try {
        flag = window.localStorage.getItem(fkey);
        window.localStorage.setItem(fkey, String(team.bannerstage.stage));
      } catch (e) {
        flag = null;
      }
      if (flag && team.bannerstage.stage > Number(flag)) {
        moments.push(function(then) {
          bannerMoment(team, Number(flag), then);
        });
      }
    }
    // The badge of the mission, celebrated once on each device.
    var b = state.world.badge;
    if (b && b.issued) {
      var bkey = 'tesape-badge-' + cfg.courseid + '-' + cfg.userid;
      var had = null;
      try {
        had = window.localStorage.getItem(bkey);
      } catch (e) {
        had = b.name;
      }
      if (had !== b.name) {
        moments.push(function(then) {
          // Seen once it shows, not when it is queued: a scene opened from the welcome does not leave it unseen.
          try {
            window.localStorage.setItem(bkey, b.name);
          } catch (e) {
            // Without storage it shows again next time, which is better than never.
          }
          badgeMoment(b, then);
        });
      }
    }
    var next = function() {
      var moment = moments.shift();
      if (moment) {
        moment(next);
      }
    };
    if (moments.length) {
      // After what flies has reached its counter.
      setTimeout(next, gained.length ? 600 + gained.length * 450 + 900 : 500);
    }
  }

  /**
   * A moment over the game, as the one of the badge: dark, what there was, light, what there is now.
   *
   * @param {string} html The card.
   * @param {string} label Id of its title.
   * @param {Function} [then] When it is closed.
   * @return {Element} The layer.
   */
  function moment(html, label, then) {
    var layer = document.createElement('div');
    layer.className = 'badge-moment rise-moment' + (T.moves() ? '' : ' still');
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', label);
    layer.innerHTML = '<div class="badge-card">' + html + '<div class="actions"><button class="btn btn-main" type="button" ' +
      'data-close>' + esc(t('app_continue')) + '</button></div></div>';
    document.body.appendChild(layer);
    var close = function() {
      document.removeEventListener('keydown', onkey);
      layer.remove();
      if (then) {
        then();
      }
    };
    var onkey = function(ev) {
      if (ev.key === 'Escape') {
        close();
      }
    };
    document.addEventListener('keydown', onkey);
    layer.querySelector('[data-close]').onclick = close;
    layer.querySelector('[data-close]').focus();
    return layer;
  }

  /** Where this device remembers the last touch with a reason it showed. */
  function touchKey() {
    return 'tesape-touch-' + cfg.courseid + '-' + cfg.userid;
  }

  /**
   * The touches of the teacher with a reason that this device has not shown yet; the first time, only those of the last
   * two days.
   *
   * @return {Array} Newest first, three at most.
   */
  function unseenTouches() {
    var seen = 0;
    try {
      seen = Number(window.localStorage.getItem(touchKey()) || 0);
    } catch (e) {
      // Without storage they would show every time: better not at all.
      return [];
    }
    var now = Date.now() / 1000;
    return (state.world.log || []).filter(function(e) {
      return e.reason === 'manual' && e.note && !e.undone && e.amount > 0 && e.id > seen &&
        (seen || now - e.time < 2 * 86400);
    }).slice(0, 3);
  }

  /**
   * What the teacher noticed: the reason and what it gave, with three bells. Only on the device of the student, never on
   * the class screen.
   *
   * @param {Array} list
   * @param {Function} [then]
   * @return {Element}
   */
  function touchMoment(list, then) {
    try {
      window.localStorage.setItem(touchKey(), String(Math.max.apply(null, list.map(function(e) {
        return e.id;
      }))));
    } catch (e) {
      // It was shown anyway.
    }
    var layer = moment('<div class="kicker">' + esc(t('app_reason_manual')) + '</div><h2 id="tesape-touch-title">' +
      esc(t('app_touch_title')) + '</h2><ul class="touch-list">' + list.map(function(e) {
        return '<li><b>' + esc(e.note) + '</b><span class="amount up"><span class="touch-icon" aria-hidden="true">' +
          T.icon(e.resource) + '</span>+' + e.amount + '<span class="sr-only"> ' + esc(t(e.resource === 'gems' && e.amount === 1 ? 'app_gem' :
          'app_' + e.resource)) +
          '</span></span></li>';
      }).join('') + '</ul>', 'tesape-touch-title', then);
    if (T.fx) {
      T.fx.sound('chime');
      T.fx.sparkles();
    }
    return layer;
  }

  /**
   * A new level: the ring with the level of before, then the new one; and with a new stage, the hero of before and the
   * one of now.
   *
   * @param {Object} before What was seen: level and stage.
   * @param {Object} c The character now.
   * @param {Function} [then]
   */
  function levelMoment(before, c, then) {
    var staged = typeof before.stage === 'number' && c.stage > before.stage;
    var was = Object.assign({}, c, {stage: before.stage});
    var layer = moment('<div class="kicker">' + esc(t('app_levelup_kicker')) + '</div><div class="rise">' +
      (staged ? '<div class="rise-from">' + avatarOf(was) + '</div><div class="rise-to">' + avatarOf(c) + '</div>' :
      '<div class="rise-from ring big"><b>' + before.level + '</b></div><div class="rise-to ring big"><b>' + c.level +
      '</b></div>') + '</div><h2 id="tesape-rise-title">' + esc(t('app_levelup', c.level)) + '</h2><p>' + esc(title(c)) + '</p>' +
      (staged ? '<p class="help">' +
      esc(t('app_stageup_help')) + '</p>' : ''), 'tesape-rise-title', then);
    if (T.fx) {
      T.fx.sound('drumroll');
      setTimeout(function() {
        T.fx.sound('levelup');
        T.fx.sparkles();
      }, T.moves() ? 1100 : 0);
    }
    return layer;
  }

  /**
   * The name of a stage of the creature, from the light (Spark, Gleam, Flame, Blaze, Beacon): nouns, so that they say
   * nothing of whether it is a he or a she.
   *
   * @param {number} n From 1 to 5.
   * @return {string}
   */
  function stageName(n) {
    return t('app_creaturestage_' + Math.max(1, Math.min(5, n || 1)));
  }

  /**
   * The creature grew: the one of before, and the one of now.
   *
   * @param {number} from Its stage before.
   * @param {Object} c The character now.
   * @param {Function} [then]
   */
  function creatureMoment(from, c, then) {
    moment('<div class="kicker">' + esc(t('app_creature_kicker')) + '</div><div class="rise"><div class="rise-from">' +
      T.creature(c.creature, from, true) + '</div><div class="rise-to">' + T.creature(c.creature, c.creaturestage, true) +
      '</div></div><h2 id="tesape-creature-title">' + esc(t('app_creaturegrew', {name: creatureName(c),
      stage: stageName(c.creaturestage)})) + '</h2><p class="help">' +
      esc(t('app_creature_grew_help')) + '</p>', 'tesape-creature-title', then);
    if (T.fx) {
      setTimeout(function() {
        T.fx.sound('hatch');
        T.fx.sparkles();
      }, T.moves() ? 1100 : 0);
    }
  }

  /**
   * The banner of the team has grown: as it was and as it is, and what it gives now.
   *
   * @param {Object} team
   * @param {number} from The stage this device saw last.
   * @param {Function} then
   */
  function bannerMoment(team, from, then) {
    var now = team.bannerstage;
    moment('<div class="kicker">' + esc(t('app_banner_kicker')) + '</div><div class="rise"><div class="rise-from team-flag-big">' +
      T.banner({stage: from}, team.id) + '</div><div class="rise-to team-flag-big">' + T.banner(now, team.id) + '</div></div>' +
      '<h2 id="tesape-banner-title">' + esc(t('app_banner_grew', t('app_banner_' + now.name))) + '</h2><p class="help">' +
      esc(t('app_banner_grew_help')) + '</p>' + bannerGifts(now.stage, from), 'tesape-banner-title', then);
    if (T.fx) {
      setTimeout(function() {
        T.fx.sound('levelup');
        T.fx.sparkles();
      }, T.moves() ? 1100 : 0);
    }
  }

  /**
   * What the banner gives at a stage: with the border, two breaths more in the reserve; with the emblem, the first hint
   * of each scene played as a team free. Only what came after a stage, if it is given.
   *
   * @param {number} stage
   * @param {number} [after]
   * @return {string}
   */
  function bannerGifts(stage, after) {
    var gifts = [[2, 'app_banner_gives_border', 'breath'], [3, 'app_banner_gives_emblem', 'log']].filter(function(g) {
      return stage >= g[0] && (after || 0) < g[0];
    });
    return gifts.length ? '<ul class="banner-gifts">' + gifts.map(function(g) {
      return '<li>' + T.icon(g[2]) + ' ' + esc(t(g[1])) + '</li>';
    }).join('') + '</ul>' : '';
  }

  /**
   * The banner in the card of the team: as it is, the weeks with the goal, how many more it needs to grow and what it
   * gives.
   *
   * @param {Object} team
   * @return {string}
   */
  function bannerLine(team) {
    var b = team.bannerstage;
    if (!b) {
      return '';
    }
    return '<div class="team-banner-line"><span class="team-flag-box">' + T.banner(b, team.id) + '</span><span class="grow">' +
      '<b>' + esc(t('app_banner_now', t('app_banner_' + b.name))) + '</b><small>' + esc(b.next ?
      t('app_banner_next', {weeks: b.weeks, next: b.next}) : t('app_banner_full', b.weeks)) + '</small>' + bannerGifts(b.stage) +
      '</span></div>';
  }

  /**
   * Whether the student follows the expedition from far (home, hospital), as the teacher marked.
   *
   * @return {boolean}
   */
  function farFrom() {
    var m = state.world.character && state.world.character.member;
    return !!(m && m.remote);
  }

  /**
   * The welcome of who comes back after a long time, or arrives with the expedition on its way: what the class did
   * meanwhile, who waits for them, and a short way to catch up, each scene ready to play.
   *
   * @param {Object} w The welcome.
   * @param {Function} then When it is closed.
   */
  function welcomeMoment(w, then) {
    var layer = document.createElement('div');
    layer.className = 'badge-moment welcome-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-welcome-title');
    var back = w.kind === 'back';
    layer.innerHTML = '<div class="badge-card">' + listen() + '<div class="kicker">' + esc(t(back ? 'app_welcome_back_kicker' :
      'app_welcome_new_kicker')) + '</div><h2 id="tesape-welcome-title">' + esc(t(back ? 'app_welcome_back' :
      'app_welcome_new')) + '</h2>' + (back && w.days ? '<p>' + esc(t('app_welcome_days', w.days)) + '</p>' : '') +
      (w.lanterns.length ? '<p>' + esc(t(back ? 'app_welcome_lanterns' : 'app_welcome_lanterns_new',
      w.lanterns.join(', '))) + '</p>' : '') + (w.team.length ? '<p>' + esc(t('app_welcome_team', w.team.join(', '))) +
      '</p>' : '') + (w.path.length ? '<h3>' + esc(t('app_welcome_path')) + '</h3><ul class="list welcome-path">' +
      w.path.map(function(p) {
        return '<li class="item"><span class="grow"><b>' + esc(p.title) + '</b><small>' + esc(p.place) + '</small></span>' +
          '<button class="btn btn-main" type="button" data-welcomescene="' + p.id + '">' + esc(t('app_play')) + '</button></li>';
      }).join('') + '</ul>' : '') + '<div class="actions"><button class="btn btn-soft" type="button" data-close>' +
      esc(t('app_welcome_later')) + '</button></div></div>';
    document.body.appendChild(layer);
    bindListen(layer);
    layer.querySelector('[data-close]').onclick = function() {
      if (window.speechSynthesis) {
        window.speechSynthesis.cancel();
      }
      layer.remove();
      then();
    };
    layer.querySelectorAll('[data-welcomescene]').forEach(function(b) {
      b.onclick = function() {
        layer.remove();
        T.playScene({sceneid: Number(b.dataset.welcomescene), world: state.world, onclose: function(world) {
          state.world = world;
          route();
          // The moments that were waiting after the welcome come now.
          then();
        }});
      };
    });
    if (T.fx) {
      T.fx.sound('lantern');
      T.fx.sparkles();
    }
    layer.querySelector('button').focus();
  }

  /**
   * The moment of the badge: the medal comes with a drum roll, and then the applause.
   *
   * @param {Object} b The badge.
   * @param {Function} [then] When it is closed.
   */
  function badgeMoment(b, then) {
    var layer = document.createElement('div');
    layer.className = 'badge-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-badge-title');
    layer.innerHTML = '<div class="badge-card"><div class="kicker">' + esc(t('app_badge_won')) + '</div><img class="badge-medal" ' +
      'src="' + esc(b.image) + '" alt=""><h2 id="tesape-badge-title">' + esc(b.name) + '</h2><p>' + esc(t('app_badge_why')) +
      '</p><div class="actions"><a class="btn btn-soft" href="' + esc(b.url) + '">' + esc(t('app_badge_see')) + '</a>' +
      '<button class="btn btn-main" type="button" data-close>' + esc(t('app_badge_go')) + '</button></div></div>';
    document.body.appendChild(layer);
    var close = function() {
      document.removeEventListener('keydown', onkey);
      layer.remove();
      if (then) {
        then();
      }
    };
    var onkey = function(ev) {
      if (ev.key === 'Escape') {
        close();
      }
    };
    document.addEventListener('keydown', onkey);
    layer.querySelector('[data-close]').onclick = close;
    layer.querySelector('[data-close]').focus();
    if (T.fx) {
      T.fx.drumroll(layer.querySelector('.badge-card'));
      setTimeout(function() {
        T.fx.sound('win');
        T.fx.sound('applause');
        T.fx.confetti();
        T.fx.sparkles();
      }, 1250);
    }
  }

  function remember() {
    var c = state.world.character;
    try {
      window.localStorage.setItem('tesape-seen-' + cfg.courseid + '-' + cfg.userid,
        JSON.stringify({saber: c.saber, gems: c.gems, level: c.level, stage: c.stage, creaturestage: c.creaturestage}));
    } catch (e) {
      // Without storage there is just no toast next time.
    }
  }

  /** @var {string[]} The places of the map that open as a page: the market, the album and the museum. */
  var LANDMARKS = ['market', 'album', 'museum'];
  var TABS = ['world', 'team', 'challenges', 'log', 'me'];

  function world() {
    var w = state.world;
    // What a counter is goes with the screen it was said on.
    var said = document.querySelector('.res-callout');
    if (said) {
      said.remove();
    }
    var member = w.character && w.character.member;
    T.adapt(member ? member.adapt : []);
    if (T.fx) {
      T.fx.quiet(w.quiet || !!T.adapted.quiet);
      T.fx.deviceMusic(w.devicemusic === undefined ? 1 : w.devicemusic, cfg.art && cfg.art.theme ? cfg.art.theme.music :
        'valley');
    }
    if (T.critters) {
      T.critters.start([25, 55]);
    }
    var c = w.character;
    var m = c.member || {energy: 0, breath: 0};
    var pct = c.levelneeded ? Math.round(100 * c.levelinto / c.levelneeded) : 100;
    // What waits in «Challenges»: only what the place of today asks for, with a mission.
    var place = w.mission && w.mission.chapters.length ? todayChapter(w.mission) : null;
    var pending = w.challenges.filter(function(ch) {
      return (!ch.status || ch.status === 'notyet') && (!place || ch.id === place.challengeid);
    }).length + w.activities.filter(function(a) {
      return a.tracked && !a.done && (!place || a.chapterid === place.id);
    }).length;
    app.innerHTML = '<div class="sky"></div><div class="world">' +
      // Its name starts with what it shows (the name of the character), and says where it goes.
      '<header class="hud"><button class="me" data-tab="me"><span class="face">' + avatarOf(c, '', true) +
      '</span><span class="who"><b>' + esc(myName()) + '</b> <small>' + esc(title(c)) + '</small></span><span class="sr-only">' +
      ' · ' + esc(t('app_tab_me')) + '</span></button><button type="button" class="ring" data-tab="me" style="--p:' + pct + '" title="' + esc(t('app_level')) +
      ' ' + c.level + '"><b>' + c.level + '</b><span class="sr-only"> · ' + esc(t('app_level')) + ' · ' + esc(t('app_tab_me')) +
      '</span></button>' + (T.fx && !w.quiet && !T.adapted.quiet ? '<button type="button" class="sound" data-sound aria-pressed="' + !T.fx.muted() +
      '" aria-label="' + esc(t('app_sound')) + '" title="' + esc(t('app_sound')) + '">' + T.icon(T.fx.muted() ? 'mute' : 'sound') +
      '</button>' : '') + (T.fx && w.devicemusic === 2 && T.fx.musicAllowed() ? '<button type="button" class="sound" ' +
      'data-music aria-pressed="' + T.fx.musicOn() + '" aria-label="' + esc(t('board_music')) + '" title="' +
      esc(t('board_music')) + '">♪</button>' : '') + '<details class="exit"><summary aria-label="' + esc(t('app_leave')) + '" title="' +
      esc(t('app_leave')) + '">⎋</summary><div class="exit-menu">' +
      // A course that goes straight into the game has no page of its own to go back to: only the dashboard.
      (/\/my\/?$/.test(cfg.courseurl) ? '' : '<a href="' + esc(cfg.courseurl) + '">' + esc(t('app_exit')) + '</a>') +
      '<a href="' + esc(cfg.wwwroot + '/my/') + '">' + esc(t('app_exit_my')) + '</a>' + savedNote() +
      '</div></details><div class="res">' +
      res('saber', c.saber, t('app_saber')) + res('gems', c.gems, t('app_gems')) +
      // The creature goes with the hero also up here; touching it, «Me».
      (c.creature ? '<button type="button" class="r pet' + moodClass() + '" data-tab="me" title="' + esc(creatureName(c)) + '">' +
      T.creature(c.creature, c.creaturestage) + '<span class="sr-only">' + esc(creatureName(c)) + ' · ' + esc(t('app_tab_me')) +
      '</span></button>' : '') + '</div></header>' +
      '<nav class="tabs" aria-label="Tesapé">' + TABS.map(function(tab) {
        // What is waiting glows; how many, only for who listens.
        var badge = tab === 'challenges' && pending ? '<span class="new-glow" aria-hidden="true"></span><span class="sr-only"> (' +
          pending + ')</span>' : '';
        // A friend of the expedition has something to tell, in the book.
        if (tab === 'log' && friendWaiting()) {
          badge = '<span class="new-glow" aria-hidden="true"></span><span class="sr-only"> (' + esc(t('app_friend_waits')) +
            ')</span>';
        }
        // A talent to choose waits on the sheet.
        if (tab === 'me' && c && c.talentpending) {
          badge = '<span class="new-glow" aria-hidden="true"></span><span class="sr-only"> (' + esc(t('app_talent_title')) +
            ')</span>';
        }
        var on = state.tab === tab || (tab === 'world' && LANDMARKS.indexOf(state.tab) >= 0);
        return '<button type="button" data-tab="' + tab + '"' + (on ? ' aria-current="page"' : '') + '>' +
          T.icon(tab) + '<span>' + esc(t('app_tab_' + tab)) + badge + '</span></button>';
      }).join('') + '</nav><main class="content" id="content"></main></div>';
    app.querySelectorAll('[data-res]').forEach(function(b) {
      b.onclick = function() {
        explain(b.dataset.res, true);
      };
    });
    var tune = app.querySelector('[data-music]');
    if (tune) {
      tune.onclick = function() {
        tune.setAttribute('aria-pressed', String(T.fx.music()));
      };
    }
    var sound = app.querySelector('[data-sound]');
    if (sound) {
      sound.onclick = function() {
        var on = T.fx.toggle();
        sound.innerHTML = T.icon(on ? 'sound' : 'mute');
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
      me: tabMe, market: function() {
        return landmarkView('market');
      }, album: function() {
        return landmarkView('album');
      }, museum: function() {
        return landmarkView('museum');
      }})[state.tab]();
    if (state.tab === 'me' || LANDMARKS.indexOf(state.tab) >= 0) {
      bindMe();
    }
    app.querySelectorAll('[data-tab-back], [data-landmark]').forEach(function(b) {
      b.onclick = function() {
        state.tab = b.dataset.tabBack || b.dataset.landmark;
        state.meSection = null;
        world();
        window.scrollTo(0, 0);
      };
    });
    if (state.tab === 'world' || state.tab === 'challenges') {
      bindWorld();
    }
    if (state.tab === 'challenges') {
      bindParts();
    }
    if (state.tab === 'team') {
      bindTeam();
    }
    if (state.tab === 'log') {
      bindFriends();
      bindWear();
    }
  }

  /**
   * Wearing an item, or putting it away: the world comes back with it in its place.
   */
  function bindWear() {
    app.querySelectorAll('[data-wear]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        var on = b.dataset.on === '1';
        T.api('equip', {courseid: cfg.courseid, item: b.dataset.wear, on: on}).then(function(w) {
          state.world = w;
          if (T.fx) {
            T.fx.sound(on ? 'chime' : 'tap');
          }
          world();
          var again = app.querySelector('[data-wear="' + b.dataset.wear + '"]');
          if (again) {
            again.focus();
          }
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
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

  /*
   * The plays kept on the device while there was no connection go by themselves: when the game opens, when the
   * connection comes back, and every minute and a half while some wait. Then the world is asked again.
   */
  var sending = false;

  function sendWaiting() {
    if (sending || !T.sendPlays || !T.waitingPlays().length) {
      return;
    }
    sending = true;
    T.sendPlays(function(title) {
      T.toast(esc(t('app_offline_sent', title)));
    }).then(function(count) {
      sending = false;
      if (count) {
        refresh();
      }
    });
  }

  window.addEventListener('online', sendWaiting);
  setInterval(sendWaiting, 90000);

  /**
   * What the menu to leave says: what was finished is saved; and the plays that wait on this device, if any.
   *
   * @return {string}
   */
  function savedNote() {
    var waiting = T.waitingPlays ? T.waitingPlays().length : 0;
    return '<p class="exit-saved">' + esc(t('app_exit_saved')) + (waiting ? ' ' + esc(t('app_exit_waiting', waiting)) : '') +
      '</p>';
  }

  /**
   * A resource of the character at the top: touched, it says what it is for (a tablet has no mouse to hover).
   *
   * @param {string} kind
   * @param {string|number} value
   * @param {string} label
   * @return {string}
   */
  function res(kind, value, label) {
    // What is on its way to the counter is not there yet.
    var shown = state.loot && state.loot[kind] !== undefined ? state.loot[kind] : value;
    return '<button type="button" class="r" data-res="' + kind + '" title="' + esc(label) + '">' + T.icon(kind) + '<b>' +
      esc(shown) + '</b><span class="sr-only"> ' + esc(label) + '</span></button>';
  }

  /**
   * What a counter of the header is, said next to it: once on each device, the first time it goes up; and whenever it
   * is touched.
   *
   * @param {string} kind saber or gems.
   * @param {boolean} [asked] Touched: it says it again.
   */
  function explain(kind, asked) {
    var key = 'tesape-explained-' + kind;
    try {
      if (!asked && window.localStorage.getItem(key)) {
        return;
      }
      window.localStorage.setItem(key, '1');
    } catch (e) {
      if (!asked) {
        return;
      }
    }
    var counter = app.querySelector('[data-res="' + kind + '"]');
    var old = document.querySelector('.res-callout');
    if (old) {
      old.remove();
    }
    if (!counter) {
      return;
    }
    var box = document.createElement('div');
    box.className = 'res-callout';
    box.setAttribute('role', 'status');
    box.innerHTML = '<p><b>' + esc(t('app_' + kind)) + '</b>: ' + esc(t('app_res_' + kind + '_help')) + '</p><button type="button" ' +
      'class="btn btn-soft" data-callout-ok>' + esc(t('app_callout_ok')) + '</button>';
    document.body.appendChild(box);
    var r = counter.getBoundingClientRect();
    box.style.top = Math.round(r.bottom + 10) + 'px';
    box.style.left = Math.round(Math.max(8, Math.min(window.innerWidth - box.offsetWidth - 8, r.left + r.width / 2 -
      box.offsetWidth / 2))) + 'px';
    box.style.setProperty('--arrow', Math.round(r.left + r.width / 2 - box.offsetLeft) + 'px');
    // It never stands in the way: it goes with «Got it», Escape, a touch anywhere else or after a while.
    var timer = null;
    var close = function() {
      box.remove();
      clearTimeout(timer);
      document.removeEventListener('keydown', onkey);
      document.removeEventListener('pointerdown', outside, true);
    };
    var onkey = function(ev) {
      if (ev.key === 'Escape') {
        close();
      }
    };
    var outside = function(ev) {
      if (!box.contains(ev.target)) {
        close();
      }
    };
    timer = setTimeout(close, 9000);
    document.addEventListener('keydown', onkey);
    document.addEventListener('pointerdown', outside, true);
    box.querySelector('[data-callout-ok]').onclick = close;
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

  /**
   * The place of today: where the teacher has the class («Today in the valley»), or else the first one not done.
   *
   * @param {Object} m The mission.
   * @return {Object}
   */
  function todayChapter(m) {
    return m.chapters.filter(function(ch) {
      return m.today && ch.code === m.today;
    })[0] || nextChapter(m);
  }

  /**
   * What the student does next, one thing: the scene of their step not done in the place of today, the page the creature
   * asks for, a minigame or a quest of that place, or else to look at the board.
   *
   * @param {Object} m The mission.
   * @return {Object} kind (scene, page, game, quest or board), and id, code, type, title and place as it needs.
   */
  function nextStep(m) {
    var ch = todayChapter(m);
    var sc = ch.scenes.filter(function(x) {
      return !x.done;
    })[0];
    if (sc) {
      return {kind: 'scene', id: sc.id, title: sc.title, place: ch.name};
    }
    var p = state.world.page;
    if (p && !p.answered && state.world.character.creature) {
      return {kind: 'page'};
    }
    var g = (ch.games || []).filter(function(x) {
      return !x.done;
    })[0];
    if (g) {
      return {kind: 'game', code: g.code, type: g.type || 'nest', title: g.title, place: ch.name};
    }
    var q = (ch.quests || []).filter(function(x) {
      return !x.done;
    })[0];
    if (q) {
      return {kind: 'quest', id: q.id, title: q.title, place: ch.name};
    }
    return {kind: 'board'};
  }

  /**
   * «Your next step»: one card, one big button.
   *
   * @param {Object} m The mission.
   * @return {string}
   */
  function nextCard(m) {
    var n = nextStep(m);
    state.next = n;
    var name = creatureName(state.world.character);
    var body = n.kind === 'board' ? '<h2>' + esc(t('app_next_board')) + '</h2><p class="help">' + esc(t('app_next_board_help')) +
      '</p>' : '<h2>' + esc(n.kind === 'page' ? t('app_next_feed', name) : n.title) + '</h2>' + (n.place ? '<p class="help">' +
      esc(n.place) + '</p>' : '') + '<button type="button" class="btn btn-main btn-big" data-next-step>' + esc(t('app_open')) +
      '</button>';
    return '<section class="card next-step">' + listen() + '<div class="kicker">' + esc(t('app_next_step')) + '</div>' + body +
      '</section>';
  }

  /**
   * The creature asks for the page of the day: its face, the question, and it eats it when it is right.
   */
  function feedMoment() {
    var layer = document.createElement('div');
    layer.className = 'badge-moment feed-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-feed-title');
    document.body.appendChild(layer);
    var close = function() {
      document.removeEventListener('keydown', onkey);
      layer.remove();
      world();
    };
    var onkey = function(ev) {
      if (ev.key === 'Escape') {
        close();
      }
    };
    document.addEventListener('keydown', onkey);
    var draw = function(ate) {
      var p = state.world.page;
      var c = state.world.character;
      var name = creatureName(c);
      var title = !p.answered ? t('app_page_hungry', name) : p.right ? t('app_page_fed', name) : t('app_page_missed', name);
      var note = p.streak >= p.glow ? t('app_page_glow', {name: name, days: p.streak}) : p.streak > 1 ?
        t('app_page_streak', p.streak) : '';
      layer.innerHTML = '<div class="badge-card feed-card"><div class="feed-face' + (ate ? ' eating' : '') + '">' +
        T.creature(c.creature, c.creaturestage, true) + '</div><h2 id="tesape-feed-title">' + esc(title) + '</h2>' +
        (note ? '<p class="help">' + esc(note) + '</p>' : '') + (!p.answered ? '<p class="help">' + esc(t('app_page_help',
        {name: name, breath: p.breath})) + '</p>' : '') + (p.minion && !p.untangled ? '<p class="page-minion">' +
        minionMark(false) + '<span>' + esc(t('app_page_minion', p.minion)) + '</span></p>' : '') +
        '<p class="page-q">' + esc(p.question.text) + '</p><div class="opts">' +
        p.question.options.map(function(o, i) {
          return '<button type="button" class="opt' + (p.answered && i === p.correct ? ' sel' : '') + '" data-feedopt="' + i +
            '"' + (p.answered ? ' disabled' : '') + '>' + esc(o) + '</button>';
        }).join('') + '</div>' + (p.answered ? (p.why ? '<p class="help">' + esc(p.why) + '</p>' : '') + '<p class="conseq said">' +
        esc(p.right ? t('app_page_breath', p.breath) : t('app_page_tomorrow')) + '</p>' : '') + (p.untangled ?
        '<p class="page-minion untangled">' + minionMark(true) + '<span>' + esc(t('app_page_untangled', p.minion)) +
        '</span></p>' : '') +
        '<div class="actions"><button class="btn ' + (p.answered ? 'btn-main' : 'btn-soft') + '" type="button" data-close>' +
        esc(t(p.answered ? 'app_continue' : 'app_later')) + '</button></div></div>';
      layer.querySelector('[data-close]').onclick = close;
      layer.querySelectorAll('[data-feedopt]:not([disabled])').forEach(function(b) {
        b.onclick = function() {
          layer.querySelectorAll('[data-feedopt]').forEach(function(o) {
            o.disabled = true;
          });
          T.api('answer_page', {courseid: cfg.courseid, answer: Number(b.dataset.feedopt)}).then(function(w) {
            state.world = w;
            draw(!!w.page.right);
            if (T.fx) {
              T.fx.sound(w.page.right ? 'heart' : 'choose');
              if (w.page.right) {
                T.fx.hearts();
              }
            }
            layer.querySelector('[data-close]').focus();
          }).catch(function(e) {
            T.toast(esc(e.message), true);
            draw(false);
          });
        };
      });
    };
    draw(false);
    (layer.querySelector('[data-feedopt]:not([disabled])') || layer.querySelector('[data-close]')).focus();
  }

  function nextChapter(m) {
    var open = m.chapters.filter(function(ch) {
      return !ch.done;
    });
    return open.length ? open[0] : m.chapters[m.chapters.length - 1];
  }

  /**
   * The stretch of road walked since the last look, drawing itself.
   *
   * @param {Object} fresh What T.mapNewRoad gave.
   * @return {string}
   */
  function roadDrawing(fresh) {
    return fresh.road ? fresh.road.replace('/>', ' style="animation-delay:' + (0.3 + fresh.delay) + 's,' +
      (2.4 + fresh.delay) + 's"/>') : '';
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
    // The lanterns lit since this device last saw the map light in front of the student.
    var lighting = T.mapNewLanterns(cfg.courseid + '-' + cfg.userid + '-' + m.code, m.chapters);
    // The lanterns painted for the map, if the art pack has them; the fog over the places the class has not opened yet
    // (all after the one after the last lit by the class).
    var lampOff = T.artUrl(['map.lantern.off']);
    var lampOn = T.artUrl(['map.lantern.on']);
    var opened = 0;
    m.chapters.forEach(function(ch, i) {
      if (ch.lit) {
        opened = i + 1;
      }
    });
    var mates = matesAt();
    var prank = state.prank = prankToday(m);
    var places = m.chapters.map(function(ch, i) {
      var today = m.today && ch.code === m.today;
      var here = mates[ch.id] || 0;
      // A place the class lit that the Embrollo came back to: its lantern flickers until the stain is cleaned.
      var back = ch.lit && (state.world.stains || []).some(function(s) {
        return s.chapter === ch.code && !s.forteam && s.kind !== 'page';
      });
      var cls = 'place' + (ch.mine ? ' mine' : '') + (lighting[String(ch.id)] ? ' lighting' : '') + (ch.lit ? ' lit' : '') +
        (back ? ' flicker' : '') + (prank.name && prank.name.chapter === ch.code ? ' pranked' : '') +
        (ch.id === current.id ? ' here' : '') +
        (ch.id === state.chapter ? ' selected' : '') + (today ? ' today' : '');
      return '<g class="' + cls + '" data-chapter="' + ch.id + '" data-art="' + esc(ch.art || ch.code) + '" tabindex="0" ' +
        'role="button" aria-label="' + esc((today ? t('app_today_here') + ' ' : '') + ch.name + (here ? '. ' +
        t('app_mates_here', here) : '')) + '">' +
        '<rect class="hit" x="' + (ch.x - 80) + '" y="' + (ch.y - 32) + '" width="160" height="84" rx="16"/>' +
        (ch.lit ? '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="40" class="classglow"/>' : '') +
        // Where the class is today: what the teacher has on «Today in the valley».
        (today ? '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="33" class="today-ring"/><text x="' + ch.x + '" y="' +
        (ch.y - 38) + '" text-anchor="middle" class="today-tag">' + esc(t('app_today_here')) + ' </text>' : '') +
        '<circle cx="' + ch.x + '" cy="' + ch.y + '" r="25" class="ring' + (lampOff ? ' painted' : '') + '"/>' +
        (lampOff && lampOn ? '<image href="' + esc(lampOff) + '" x="' + (ch.x - 20) + '" y="' + (ch.y - 30) + '" width="40" ' +
        'height="50" class="lamp-off"/>' + (ch.mine ? '<image href="' + esc(lampOn) + '" x="' + (ch.x - 20) + '" y="' +
        (ch.y - 30) + '" width="40" height="50" class="lamp-on"/>' : '') :
        '<rect x="' + (ch.x - 2) + '" y="' + (ch.y - 4) + '" width="4" height="17" rx="1" fill="#2b2219"/>' +
        '<circle cx="' + ch.x + '" cy="' + (ch.y - 7) + '" r="7" class="flame"/>') +
        (i > opened && !today ? '<ellipse cx="' + ch.x + '" cy="' + (ch.y + 6) + '" rx="62" ry="34" class="fog"/>' : '') +
        (state.visit ? '' : T.photoSticker(photoOf(ch.code), ch.x, ch.y, i)) + T.mapLabel(ch.x, ch.y + 44, prank.name &&
        prank.name.chapter === ch.code ? prank.name.name : ch.name) + '</g>';
    }).join('');
    // The team, beside the lanterns but outside the places: the name of a place is what it says, and the team is in it.
    var crowd = m.chapters.map(function(ch) {
      return silhouettes(ch, mates[ch.id] || 0);
    }).join('');
    // The footprints of the Embrollo on the way from the place of today to the next one: it is getting there first.
    var prints = '';
    var at = m.chapters.map(function(ch) {
      return ch.code;
    }).indexOf(m.today);
    if (!state.visit && m.prints > 0 && at >= 0 && at + 1 < m.chapters.length) {
      var a = m.chapters[at];
      var b = m.chapters[at + 1];
      var angle = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI + 90;
      for (var k = 1; k <= m.prints; k++) {
        var f = 0.25 + 0.5 * k / 6;
        var side = k % 2 ? -5 : 5;
        prints += '<g class="print" transform="translate(' + Math.round(a.x + f * (b.x - a.x)) + ' ' + Math.round(a.y + f *
          (b.y - a.y)) + ') rotate(' + Math.round(angle) + ') translate(' + side + ' 0)"><ellipse rx="3.4" ry="5.2"/>' +
          '<circle cx="-2.6" cy="-7.4" r="1.3"/><circle cx="0" cy="-8.2" r="1.3"/><circle cx="2.6" cy="-7.4" r="1.3"/></g>';
      }
      prints = '<g class="prints" aria-hidden="true">' + prints + '</g>';
    }
    // What the Embrollo stained, a blot of ink beside the lantern of the place, to clean it; or the page it stole, to bring
    // it back.
    // A page hidden after a battle lost is not on the map: it is found by opening its place.
    var blots = state.visit ? '' : (state.world.stains || []).filter(function(s) {
      return !s.hidden;
    }).map(function(s, i) {
      var ch = m.chapters.filter(function(x) {
        return x.code === s.chapter;
      })[0];
      var page = s.kind === 'page';
      var label = page ? t('app_page_stolen') + ': «' + s.title + '»' : t(s.forteam ? 'app_stain_team' : 'app_stain_on', s.place);
      return ch ? '<foreignObject x="' + (ch.x + 28 + 14 * i) + '" y="' + (ch.y - 46) + '" width="36" height="36" class="blot' +
        (page ? ' stolen' : '') + (s.forteam ? ' team-knot' : '') + '"><div xmlns="http://www.w3.org/1999/xhtml"><button type="button" class="blot-btn" ' +
        'data-stain="' + s.id + '" title="' + esc(label) + '"><span aria-hidden="true">' + (page ? PAGE : BLOT) + '</span>' +
        '<span class="sr-only">' + esc(label) + '</span></button></div></foreignObject>' : '';
    }).join('');
    // The thread the Embrollo hung from a lantern today, outside the place: a pull takes it away.
    var thread = m.chapters.filter(function(ch) {
      return prank.thread === ch.code;
    }).map(function(ch) {
      return '<foreignObject x="' + (ch.x + 3) + '" y="' + (ch.y - 14) + '" width="22" height="46" class="prank-thread"><div ' +
        'xmlns="http://www.w3.org/1999/xhtml"><button type="button" class="thread-btn" data-thread title="' +
        esc(t('app_prank_thread_pull')) + '"><svg viewBox="0 0 22 46" aria-hidden="true"><path d="M11 2c-6 6 6 10 0 16s6 10 ' +
        '0 16" fill="none" stroke="#b07cd8" stroke-width="2.4" stroke-linecap="round"/><circle cx="11" cy="40" r="4" ' +
        'fill="#8a5bb0"/></svg><span class="sr-only">' + esc(t('app_prank_thread_pull')) + '</span></button></div>' +
        '</foreignObject>';
    }).join('');
    // The minion of the Embrollo that stands on the road to the place the class is opening: on the way from the place
    // before (from the first place, on the way out of it).
    var minions = state.visit ? '' : m.chapters.map(function(ch, i) {
      if (!ch.minion) {
        return '';
      }
      var from = m.chapters[i - 1] || m.chapters[i + 1] || ch;
      var f = m.chapters[i - 1] ? 0.62 : 0.3;
      var x = Math.round(from.x + (ch.x - from.x) * f);
      var y = Math.round(from.y + (ch.y - from.y) * f);
      var art = T.artUrl([ch.minion.picture]);
      var label = t('app_minion_road', T.capital(ch.minion.name));
      return '<foreignObject x="' + (x - 21) + '" y="' + (y - 30) + '" width="42" height="42" class="map-minion' +
        (ch.minion.tried ? ' tried' : '') + '"><div xmlns="http://www.w3.org/1999/xhtml"><button type="button" ' +
        'class="minion-btn" data-minion="' + ch.id + '" title="' + esc(label) + '">' + (art ? '<img src="' + esc(art) +
        '" alt="">' : '<span aria-hidden="true">' + T.embrollo(0.6) + '</span>') + '<span class="sr-only">' + esc(label) +
        '</span></button></div></foreignObject>';
    }).join('');
    var c = state.world.character;
    var reached = Math.min(lastDone + 1, m.chapters.length - 1);
    var fresh = T.mapNewRoad(cfg.courseid + '-' + cfg.userid + '-' + m.code, m.chapters, reached);
    var step = T.mapWalk(cfg.courseid + '-' + cfg.userid + '-' + m.code, m.chapters, current);
    // Where the student went on the map last, if not where the next step is: the hero waits there.
    var went = m.chapters.filter(function(ch) {
      return ch.id === state.chapter;
    })[0];
    if (went && went.id !== current.id) {
      current = went;
      step = {};
    }
    var walk = step.from ? ' walking" style="--walk-x:' + (step.from.x - current.x) + 'px;--walk-y:' +
      (step.from.y - current.y) + 'px;animation-delay:' + (0.3 + step.delay) + 's' : '';
    // The creature goes with the hero, at their feet; if today's place is another one, a little ahead on the way to it.
    var today = m.today && m.today !== current.code ? m.chapters.filter(function(ch) {
      return ch.code === m.today;
    })[0] : null;
    var ahead = today && !step.from ? {x: Math.round(0.28 * (today.x - current.x)), y: Math.round(0.28 * (today.y - current.y))} :
      {x: 0, y: 0};
    var pet = c.creature ? '<foreignObject x="' + (current.x - 106 + ahead.x) + '" y="' + (current.y - 6 + ahead.y) + '" ' +
      'width="34" height="34" class="me-creature' + moodClass() + (today && !step.from ? ' ahead' : '') + walk + '"><div ' +
      'xmlns="http://www.w3.org/1999/xhtml">' +
      T.creature(c.creature, c.creaturestage) + '</div></foreignObject>' : '';
    // The page of the day: the creature is hungry, over the hero.
    var p = state.world.page;
    // Near the hero, where it covers no place nor its name: a touch on a place always opens the place.
    var spot = hungerSpot(m.chapters, current);
    var hungry = p && !p.answered && c.creature && !state.visit ? '<foreignObject x="' + spot.x + '" y="' +
      spot.y + '" width="' + spot.w + '" height="40" class="hunger' + (spot.small ? ' small' : '') + '"><div ' +
      'xmlns="http://www.w3.org/1999/xhtml"><button type="button" class="hunger-bubble" data-feed title="' +
      esc(t('app_feed_bubble')) + '">' + (spot.small ? '<span aria-hidden="true">!</span>' : esc(t('app_feed_bubble'))) +
      '<span class="sr-only"> ' + (spot.small ? esc(t('app_feed_bubble')) + ' ' : '') +
      esc(t('app_page_hungry', creatureName(c))) + '</span></button></div></foreignObject>' : '';
    var me = '<foreignObject x="' + (current.x - 74) + '" y="' + (current.y - 22) + '" width="44" height="44" class="me-marker' +
      walk + '">' +
      '<div xmlns="http://www.w3.org/1999/xhtml" class="face">' + T.avatar({role: c.role, nameform: c.nameform,
      lineage: c.lineage, look: c.appearance, stage: c.stage, portrait: true}) + '</div></foreignObject>';
    // The painted map of the art pack, the bands of a long map in layers, or a drawing of the valley.
    var height = T.mapHeight(m);
    if (T.fx) {
      T.fx.ambient(T.fx.place(current.art || current.code), current.code);
    }
    // The card of the day goes over the map, outside it (the landmarks sit on its top edge), both in one column.
    var card = state.visit ? '' : cardToday();
    return (card ? '<div class="map-col">' + card : '') + '<div class="map-wrap' + (height > 640 ? ' tall' : '') +
      '" data-herox="' + current.x + '" data-heroy="' + current.y +
      '">' + (state.visit ? '' : landmarks() + asleep()) + '<svg class="map" viewBox="0 0 400 ' + height +
      '" role="group" aria-label="' + esc(m.title) + '"><defs>' + T.MAPGLOW + '</defs>' + T.mapGround(m) +
      T.mapLandmarks(m) + T.mapNight(m) + '<polyline points="' + pts + '" class="road"/>' +
      (walked ? '<polyline points="' + walked + '" class="road walked"/>' : '') + roadDrawing(fresh) +
      (T.critters ? T.critters.fireflies(m) : '') + prints + places + crowd + blots + minions + thread + me + pet + hungry + '</svg></div>' + (card ? '</div>' : '');
  }

  /**
   * How the creature is now: asleep after a week away, until it is woken; happy after three good endings in a row.
   *
   * @return {string} asleep, happy or empty.
   */
  function mood() {
    var c = state.world.character || {};
    var m = (c.member && c.member.mood) || '';
    return m === 'asleep' && state.woke ? '' : m;
  }

  /**
   * The mood as a class for the drawings of the creature.
   *
   * @return {string}
   */
  function moodClass() {
    return mood() ? ' mood-' + mood() : '';
  }

  /**
   * The creature, asleep after a week away: on the map, it waits for a touch to wake up (never ill, never gone).
   *
   * @return {string}
   */
  /**
   * The pranks of the Embrollo on the map of this device today: the place it renamed (fixed on going into it) and the
   * lantern it hung a thread on (fixed with a pull). Nothing counts; the day after, another one.
   *
   * @param {Object} m The mission.
   * @return {Object} key, done, name (chapter, name) and thread (code of the place), the last two if not fixed.
   */
  function prankToday(m) {
    var list = m.pranks || [];
    if (state.visit || !list.length) {
      return {};
    }
    var d = new Date();
    var day = d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
    var key = 'tesape-prank-' + cfg.courseid + '-' + cfg.userid;
    var done = {};
    try {
      done = JSON.parse(window.localStorage.getItem(key) || '{}') || {};
    } catch (e) {
      done = {};
    }
    if (done.day !== day) {
      done = {day: day};
    }
    var h = 0;
    var seed = day + '-' + cfg.userid;
    for (var i = 0; i < seed.length; i++) {
      h = (h * 31 + seed.charCodeAt(i)) % 9973;
    }
    var lit = m.chapters.filter(function(c) {
      return c.lit || c.mine;
    });
    return {key: key, done: done, name: done.name ? null : list[h % list.length],
      thread: done.thread || !lit.length ? null : lit[(h >> 2) % lit.length].code};
  }

  /**
   * A prank of today put right, on this device.
   *
   * @param {string} what name or thread.
   */
  function prankFixed(what) {
    var p = state.prank;
    if (!p || !p.key) {
      return;
    }
    p.done[what] = true;
    p[what] = null;
    try {
      window.localStorage.setItem(p.key, JSON.stringify(p.done));
    } catch (e) {
      // Without storage, the prank comes back next time; nothing else.
    }
  }

  /**
   * The card the teacher drew today, over the map: its name, and what it does at a touch.
   *
   * @return {string}
   */
  function cardToday() {
    var c = state.world.card;
    return c ? '<details class="card-today"><summary><span class="kicker">' + esc(t('app_card_today')) + '</span> ' +
      esc(c.name) + '</summary><p>' + esc(c.text) + '</p></details>' : '';
  }

  function asleep() {
    var c = state.world.character || {};
    if (mood() !== 'asleep' || !c.creature) {
      return '';
    }
    return '<button type="button" class="pet-asleep" data-wake><span class="pet-zzz" aria-hidden="true">' +
      T.creature(c.creature, c.creaturestage) + '<i>z</i><i>z</i></span><span>' + esc(t('app_pet_asleep', creatureName(c))) +
      '</span></button>';
  }

  /**
   * The market stall and the big book of the album, on the map: places of the valley, not cards of a profile.
   *
   * @return {string}
   */
  function landmarks() {
    var w = state.world;
    var list = [];
    if (w.market && w.market.length) {
      list.push(['market', T.artUrl(['landmark.market', 'market.banner-lantern']), 'gems']);
    }
    if (w.album || (w.mission && (w.mission.guide || []).length)) {
      list.push(['album', T.artUrl(['landmark.album', 'album.ave']), 'log']);
    }
    if (w.museum) {
      list.push(['museum', T.artUrl(['landmark.museum', 'landmark.valley.library']), 'log']);
    }
    return list.length ? '<div class="map-landmarks">' + list.map(function(l) {
      return '<button type="button" class="landmark-btn ' + l[0] + '" data-landmark="' + l[0] + '"><span class="landmark-pic" ' +
        'aria-hidden="true">' + (l[1] ? '<img src="' + esc(l[1]) + '" alt="">' : T.icon(l[2])) + '</span><span>' +
        esc(t('app_landmark_' + l[0])) + '</span></button>';
    }).join('') + '</div>' : '';
  }

  /**
   * The market or the album as a place: what it has, and the way back to the map.
   *
   * @param {string} which market or album.
   * @return {string}
   */
  function landmarkView(which) {
    if (which === 'museum' && T.fx) {
      T.fx.ambient('meadow', 'biblioteca');
    }
    return '<div class="landmark-view"><button type="button" class="btn btn-soft back-map" data-tab-back="world">' +
      T.icon('world') + ' ' + esc(t('app_back_map')) + '</button>' + ({market: marketCard, album: albumCard,
      museum: museumCard})[which]() + '</div>';
  }

  /**
   * The museum of the class in the Library: the shelves that the entries of the field guide of the whole class fill.
   *
   * @return {string}
   */
  function museumCard() {
    var m = state.world.museum;
    if (!m) {
      return '';
    }
    return '<section class="card no-print museum-card" style="margin-top:16px"><h2>' + esc(t('app_museum')) + '</h2>' +
      '<p class="help-soft">' + esc(t('app_museum_help', {entries: m.entries, lit: m.shelves.filter(function(s) {
        return s.lit;
      }).length})) + '</p>' + T.museum(m, true) + '</section>';
  }

  /**
   * How many of the team are in each place: where they last played, without names.
   *
   * @return {Object} Chapter id => count.
   */
  function matesAt() {
    var at = {};
    (state.world.team ? state.world.team.members : []).forEach(function(x) {
      if (!x.me && x.at) {
        at[x.at] = (at[x.at] || 0) + 1;
      }
    });
    return at;
  }

  /**
   * The teammates in a place, as small silhouettes beside its lantern: up to three, and how many more.
   *
   * @param {Object} ch
   * @param {number} count
   * @return {string}
   */
  function silhouettes(ch, count) {
    var out = '';
    for (var k = 0; k < Math.min(count, 3); k++) {
      var x = ch.x + 30 + k * 13;
      var y = ch.y + 4;
      out += '<g class="mate" aria-hidden="true"><circle cx="' + x + '" cy="' + (y - 9) + '" r="4.6"/><path d="M' + (x - 7) + ' ' +
        (y + 7) + 'q0-11 7-11t7 11z"/></g>';
    }
    return out + (count > 3 ? '<text class="mate-more" x="' + (ch.x + 70) + '" y="' + (ch.y + 8) + '" aria-hidden="true">+' +
      (count - 3) + '</text>' : '');
  }

  /**
   * The hero walks on the map to the place the student touched, with their creature; on a phone the map comes close
   * to the place, under the sheet of the place.
   *
   * @param {Element} g The place.
   * @param {Object} ch
   */
  /**
   * Where the bubble of the hungry creature goes on the map: around the hero (over it, to its left, under it, to its
   * right), the first place that covers no place of the map nor its name; over the hero if none is free.
   *
   * @param {Object[]} chapters
   * @param {Object} at The place the hero is in.
   * @return {Object} x, y of the bubble (140 × 40).
   */
  function hungerSpot(chapters, at) {
    // The market and the album sit in the corner at the top on the left.
    var corner = state.world.market && state.world.market.length || state.world.album;
    var free = function(x, y, w) {
      return x >= 4 && x + w <= 396 && y >= 4 && !(corner && x < 184 && y < 112) && chapters.every(function(ch) {
        // What a touch on a place takes: its lantern and, under it, its name (as the map draws them).
        return x + w < ch.x - 84 || x > ch.x + 84 || y + 40 < ch.y - 56 || y > ch.y + 68;
      });
    };
    var big = [[at.x - 140, at.y - 100], [at.x - 228, at.y - 40], [at.x - 140, at.y + 72], [at.x + 88, at.y - 100],
      [at.x + 88, at.y + 72], [at.x - 228, at.y + 20], [at.x + 88, at.y - 20]];
    for (var i = 0; i < big.length; i++) {
      if (free(big[i][0], big[i][1], 140)) {
        return {x: big[i][0], y: big[i][1], w: 140, small: false};
      }
    }
    // No room for the bubble: a small round one beside the creature.
    var small = [[at.x - 132, at.y - 50], [at.x - 132, at.y + 10], [at.x - 132, at.y - 110], [at.x + 90, at.y - 50],
      [at.x + 90, at.y + 10]];
    for (var j = 0; j < small.length; j++) {
      if (free(small[j][0], small[j][1], 40)) {
        return {x: small[j][0], y: small[j][1], w: 40, small: true};
      }
    }
    return {x: Math.max(4, at.x - 132), y: at.y - 50, w: 40, small: true};
  }

  /** A blot of ink, drawn. */
  var BLOT = '<svg viewBox="0 0 40 40" width="100%" height="100%"><path d="M20 4c5 0 6 5 10 6s7 5 5 10-1 7-4 10-8 6-13 ' +
    '5-8-3-11-7-6-8-3-12 7-12 16-12z" fill="#2b1d4a" stroke="#b9a6ff" stroke-width="2"/><circle cx="33" cy="33" r="3" ' +
    'fill="#2b1d4a" stroke="#b9a6ff" stroke-width="1.5"/></svg>';

  /** A page of the Great Book, torn out by the Embrollo, drawn. */
  var PAGE = '<svg viewBox="0 0 40 40" width="100%" height="100%"><path d="M9 5h17l6 6v22l-3-2-3 3-3-3-3 3-3-3-3 3-3-3-2 2z" ' +
    'fill="#f3e6c4" stroke="#b9a6ff" stroke-width="2" stroke-linejoin="round"/><path d="M26 5v6h6" fill="none" ' +
    'stroke="#b9a6ff" stroke-width="2"/><path d="M13 15h13M13 20h13M13 25h9" stroke="#8a7a5a" stroke-width="1.6" ' +
    'stroke-linecap="round"/></svg>';

  /**
   * A minion of the Embrollo on the road: its face, what it claims and a question of its knot; one try a day, and the
   * right answer untangles it, with a gem, what undoes it and the why.
   *
   * @param {Object} minion code, name, claim, picture, question, options, tried.
   */
  function minionMoment(minion) {
    var art = T.artUrl([minion.picture]);
    var layer = document.createElement('div');
    layer.className = 'badge-moment stain-moment minion-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-minion-title');
    layer.innerHTML = '<div class="badge-card"><div class="kicker">' + esc(t('app_minion_on_road')) + '</div>' +
      '<div class="minion-face" aria-hidden="true">' + (art ? '<img src="' + esc(art) + '" alt="">' : T.embrollo(0.8)) +
      '</div><h2 id="tesape-minion-title">' + esc(T.capital(minion.name)) + '</h2>' + (minion.claim ? '<p class="knot-laugh">' +
      esc(minion.claim) + '</p>' : '') + (minion.tried ? '<p class="turn-outcome fell">' + esc(t('app_minion_tried')) +
      '</p>' : '<p class="help">' + esc(t('app_minion_question')) + '</p><p><b>' + esc(minion.question) + '</b></p><div ' +
      'class="page-options">' + minion.options.map(function(o, i) {
        return '<button type="button" class="btn btn-soft" data-opt="' + i + '">' + esc(o) + '</button>';
      }).join('') + '</div>') + '<div data-said aria-live="polite"></div><div class="actions"><button type="button" ' +
      'class="btn btn-soft" data-close>' + esc(t('app_close')) + '</button></div></div>';
    document.body.appendChild(layer);
    var close = function() {
      layer.remove();
      refresh();
    };
    layer.querySelector('[data-close]').onclick = close;
    layer.querySelectorAll('[data-opt]').forEach(function(b) {
      b.onclick = function() {
        layer.querySelectorAll('[data-opt]').forEach(function(x) {
          x.disabled = true;
        });
        T.api('untangle_minion', {courseid: cfg.courseid, code: minion.code, answer: Number(b.dataset.opt)}).then(function(r) {
          var said = layer.querySelector('[data-said]');
          var right = layer.querySelector('[data-opt="' + r.correct + '"]');
          if (right) {
            right.classList.add('chosen');
          }
          if (!r.right) {
            if (T.fx) {
              T.fx.sound('giggle');
            }
            said.innerHTML = '<p class="turn-outcome fell">' + esc(t('app_minion_laughs')) + '</p>' + (r.why ? '<p class="help">' +
              esc(r.why) + '</p>' : '');
          } else {
            if (T.fx) {
              T.fx.sound('coin');
            }
            layer.querySelector('.minion-face').classList.add('flees');
            said.innerHTML = '<div class="conseq good"><span class="what">' + esc(t('app_minion_untangled', T.capital(minion.name))) +
              '</span>' + (r.gems ? esc(t(r.gems === 1 ? 'app_encounter_gem' : 'app_encounter_gems', r.gems)) : '') + '</div>' +
              (r.weakness ? '<p><b>' + esc(t('app_minion_weakness', r.weakness)) + '</b></p>' : '') + (r.why ? '<p class="help">' +
              esc(r.why) + '</p>' : '');
          }
          layer.querySelector('[data-close]').focus();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          close();
        });
      };
    });
    layer.querySelector('button').focus();
  }

  /**
   * A page of the Great Book the Embrollo stole: what it claims and the question of today; one try a day for each
   * student, and the first right answer brings it back to the whole class, with a gem, the page and the why.
   *
   * @param {Object} stain id, title, claim, question, options, tried.
   */
  function pageMoment(stain) {
    var layer = document.createElement('div');
    layer.className = 'badge-moment stain-moment page-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-page-title');
    layer.innerHTML = '<div class="badge-card"><div class="kicker" id="tesape-page-title">' + esc(t('app_page_stolen')) +
      '</div><h2>«' + esc(stain.title) + '»</h2>' + (stain.claim ? '<p class="knot-laugh">' + esc(stain.claim) + '</p>' : '') +
      (stain.tried ? '<p class="turn-outcome fell">' + esc(t('app_page_tried')) + '</p>' : '<p class="help">' +
      esc(t('app_page_question')) + '</p><p><b>' + esc(stain.question) + '</b></p><div class="page-options">' +
      stain.options.map(function(o, i) {
        return '<button type="button" class="btn btn-soft" data-opt="' + i + '">' + esc(o) + '</button>';
      }).join('') + '</div>') + '<div data-said></div><div class="actions"><button type="button" class="btn btn-soft" ' +
      'data-close>' + esc(t('app_close')) + '</button></div></div>';
    document.body.appendChild(layer);
    var close = function() {
      layer.remove();
      refresh();
    };
    layer.querySelector('[data-close]').onclick = close;
    layer.querySelectorAll('[data-opt]').forEach(function(b) {
      b.onclick = function() {
        layer.querySelectorAll('[data-opt]').forEach(function(x) {
          x.disabled = true;
        });
        T.api('recover_page', {courseid: cfg.courseid, stainid: stain.id, answer: Number(b.dataset.opt)}).then(function(r) {
          var said = layer.querySelector('[data-said]');
          if (!r.right) {
            said.innerHTML = '<p class="turn-outcome fell">' + esc(t('app_page_escaped')) + '</p>';
            return;
          }
          b.classList.add('chosen');
          if (T.fx) {
            T.fx.sound('coin');
          }
          said.innerHTML = '<div class="conseq good"><span class="what">' + esc(t('app_page_back', r.title)) + '</span>' +
            esc(t(r.gems === 1 ? 'app_encounter_gem' : 'app_encounter_gems', r.gems)) + '</div><div class="book-page"><b>' + esc(r.title) + '</b><p>' +
            esc(r.text) + '</p></div>' + (r.why ? '<p class="help">' + esc(r.why) + '</p>' : '');
          layer.querySelector('[data-close]').focus();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          close();
        });
      };
    });
    layer.querySelector('button').focus();
  }

  /**
   * A stain of the Embrollo, to clean: its sheet with its words; the wrong ones touched, it is clean for the whole class,
   * with a gem and the complaint of the Embrollo, and then each mistake with what it should say.
   *
   * @param {Object} stain id, place, title, parts.
   */
  function stainMoment(stain) {
    var layer = document.createElement('div');
    layer.className = 'badge-moment stain-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-stain-title');
    layer.innerHTML = '<div class="badge-card"><div class="kicker" id="tesape-stain-title">' +
      esc(t(stain.forteam ? 'app_stain_team' : 'app_stain_on', stain.place)) + '</div><h2>' + esc(stain.title) + '</h2><div data-sheet></div><div data-said></div><div class="actions">' +
      '<button type="button" class="btn btn-soft" data-close>' + esc(t('app_close')) + '</button></div></div>';
    document.body.appendChild(layer);
    var sheet = layer.querySelector('[data-sheet]');
    var key = 'stain-' + stain.id;
    var lane = {engine: 'errors', parts: stain.parts, mine: true};
    var close = function() {
      layer.remove();
    };
    layer.querySelector('[data-close]').onclick = close;
    var draw = function() {
      T.lane(sheet, lane, {key: key, sent: false, open: true, onsend: function(payload) {
        T.api('clean_stain', {courseid: cfg.courseid, stainid: stain.id, payload: JSON.stringify(payload)}).then(function(r) {
          var said = layer.querySelector('[data-said]');
          if (!r.clean) {
            // Still ink: what was touched is forgotten, and it is tried again.
            delete T.lane.kept[key];
            said.innerHTML = '<p class="turn-outcome fell">' + esc(t('app_stain_still')) + '</p>';
            draw();
            return;
          }
          lane.solution = r.solution;
          T.lane(sheet, lane, {key: key, sent: true, open: false, onsend: function() {}});
          said.innerHTML = '<p class="turn-outcome undone">' + esc(t('app_stain_clean', r.gems)) + '</p>' + (r.complaint ?
            '<p class="knot-laugh">«' + esc(r.complaint.replace(/^«|»$/g, '')) + '»</p>' : '');
          if (T.fx) {
            T.fx.sound('gem');
          }
          layer.querySelector('[data-close]').onclick = function() {
            close();
            refresh();
          };
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          close();
          refresh();
        });
      }});
    };
    draw();
    var first = layer.querySelector('button');
    if (first) {
      first.focus();
    }
  }

  function walkTo(g, ch) {
    var wrap = app.querySelector('.map-wrap');
    if (!wrap) {
      return;
    }
    var dx = ch.x - Number(wrap.dataset.herox);
    var dy = ch.y - Number(wrap.dataset.heroy);
    wrap.querySelectorAll('.me-marker, .me-creature').forEach(function(el) {
      el.classList.remove('walking');
      el.style.animationDelay = '';
      el.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
    });
    var hunger = wrap.querySelector('.hunger');
    if (hunger) {
      hunger.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
    }
    var svg = wrap.querySelector('.map');
    var box = svg.viewBox.baseVal;
    svg.style.transformOrigin = (100 * ch.x / box.width) + '% ' + (100 * ch.y / box.height) + '%';
    wrap.classList.add('zoomed');
  }

  /**
   * On a phone the place comes up as a sheet over the map, which stays close to it; closing it, the map goes back.
   *
   * @param {Element} g The place touched.
   */
  function openSheet(g) {
    var panel = app.querySelector('.mission-grid .chapter');
    if (!panel) {
      return;
    }
    panel.classList.add('as-sheet');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', g.getAttribute('aria-label'));
    panel.insertAdjacentHTML('afterbegin', '<button type="button" class="sheet-close" data-sheetclose aria-label="' +
      esc(t('app_close')) + '"><span class="sheet-grip" aria-hidden="true"></span>' + T.icon('close') + '</button>');
    document.body.classList.add('sheet-up');
    var close = function() {
      panel.classList.remove('as-sheet');
      panel.removeAttribute('role');
      panel.removeAttribute('aria-label');
      var b = panel.querySelector('[data-sheetclose]');
      if (b) {
        b.remove();
      }
      document.body.classList.remove('sheet-up');
      var wrap = app.querySelector('.map-wrap');
      if (wrap) {
        wrap.classList.remove('zoomed');
      }
      document.removeEventListener('keydown', esc1);
      g.focus();
    };
    var esc1 = function(ev) {
      if (ev.key === 'Escape') {
        close();
      }
    };
    document.addEventListener('keydown', esc1);
    panel.querySelector('[data-sheetclose]').onclick = close;
    // The place, visible above the sheet.
    var r = g.getBoundingClientRect();
    var room = window.innerHeight - panel.getBoundingClientRect().height;
    window.scrollBy({top: r.top + r.height / 2 - Math.max(120, room / 2), behavior: T.moves() ? 'smooth' : 'auto'});
    var title = panel.querySelector('h2');
    if (title) {
      title.setAttribute('tabindex', '-1');
      title.focus({preventScroll: true});
    }
  }

  /**
   * Who of the team is here to play a scene together, on this device: it will count for each of them.
   *
   * @param {number} sceneid
   */
  function teamPicker(sceneid) {
    // Who is not playing today does not show; who follows from far cannot be at this device.
    var mates = state.world.team.members.filter(function(m) {
      var mm = m.character && m.character.member;
      return !m.me && !(mm && mm.status === 'away');
    });
    var layer = document.createElement('div');
    layer.className = 'badge-moment team-pick';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-team-title');
    // The healer sees who is worn out: playing together rescues them.
    var healer = state.world.character && state.world.character.role === 'healer';
    layer.innerHTML = '<form class="badge-card"><h2 id="tesape-team-title">' + esc(t('app_team_who')) + '</h2><p>' +
      esc(t('app_team_who_help')) + '</p><div class="team-mates">' + mates.map(function(m) {
        var far = m.character && m.character.member && m.character.member.remote;
        var worn = healer && m.character && m.character.member && m.character.member.status === 'fallen';
        return '<label class="chip' + (far ? ' off' : '') + '"><input type="checkbox" value="' + m.userid + '"' +
          (far ? ' disabled' : '') + '> ' + esc(m.name) + (far ? ' · ' + esc(t('app_team_far')) : '') +
          (worn ? ' · ' + esc(t('app_team_rescue')) : '') + '</label>';
      }).join('') + '</div><div class="actions"><button class="btn btn-soft" type="button" data-cancel>' +
      esc(t('app_cancel')) + '</button><button class="btn btn-main" type="submit" disabled>' + esc(t('app_team_start')) +
      '</button></div><div class="huddle-offer"><p class="help-soft">' + esc(t('app_huddle_tablets_help')) + '</p>' +
      '<button class="btn btn-soft" type="button" data-tablets>' + esc(t('app_huddle_tablets')) + '</button></div></form>';
    document.body.appendChild(layer);
    var form = layer.querySelector('form');
    var go = form.querySelector('[type="submit"]');
    var picked = function() {
      return Array.prototype.map.call(form.querySelectorAll('input:checked'), function(i) {
        return Number(i.value);
      });
    };
    form.onchange = function() {
      go.disabled = !picked().length;
    };
    layer.querySelector('[data-cancel]').onclick = function() {
      layer.remove();
    };
    // With the tablets of the team: each one chooses on theirs when it is their turn; the scene runs on this one.
    layer.querySelector('[data-tablets]').onclick = function() {
      layer.remove();
      T.huddleLobby(sceneid, function(h, players) {
        T.playScene({sceneid: sceneid, world: state.world, huddle: h.id, team: players.filter(function(id) {
          return Number(id) !== Number(cfg.userid);
        }), onclose: function(world) {
          state.world = world;
          route();
        }});
      });
    };
    form.onsubmit = function(ev) {
      ev.preventDefault();
      var team = picked();
      layer.remove();
      T.playScene({sceneid: sceneid, world: state.world, team: team, onclose: function(world) {
        state.world = world;
        route();
      }});
    };
    var first = form.querySelector('input:not([disabled])');
    if (first) {
      first.focus();
    }
  }

  /**
   * Where the spots of a place go on its painting, in percent of the painting, when the mission does not say: inside
   * what a phone shows of it.
   */
  var SPOTS = [[34, 74], [50, 56], [66, 76], [36, 32], [64, 30], [50, 14], [70, 52], [30, 50]];

  /**
   * The place as a place: its painting, with what there is to do in it as spots to touch (the scenes, the minigames
   * and the quests); what is done carries its lantern, and what comes next glows, with the hero beside it. The list
   * under it says the same, for the keyboard and the screen reader.
   *
   * @param {Object} ch The chapter.
   * @param {boolean} past A past expedition: only to look.
   * @return {string}
   */
  function placeView(ch, past) {
    var art = T.artUrl(['scene.' + (ch.art || ch.code)]);
    if (!art || past) {
      return '';
    }
    var spots = ch.scenes.map(function(sc) {
      return {kind: 'scene', id: sc.id, code: sc.code, title: sc.title, done: sc.done, next: isNext('scene', sc.id),
        icon: 'saber'};
    }).concat((ch.games || []).map(function(g) {
      return {kind: 'game', id: g.code, code: g.code, title: g.title, done: g.done, next: isNext('game', g.code), icon: 'game'};
    })).concat((ch.quests || []).map(function(q) {
      return {kind: 'quest', id: q.id, code: q.code, title: q.title, done: q.done, next: false, icon: 'challenges'};
    }));
    // Each one where the mission puts it on the painting; the rest, in the free places of the default ones.
    var placed = {};
    (ch.spots || []).forEach(function(sp) {
      placed[sp.target] = [sp.x, sp.y];
    });
    var free = SPOTS.slice();
    spots = spots.filter(function(sp) {
      sp.at = placed[sp.code] || free.shift();
      return !!sp.at;
    });
    var c = state.world.character;
    // What a phone shows of the painting: near its left edge, the hero goes to the other side of what comes next.
    var narrow = window.innerWidth <= 600;
    // With «Night of fireflies» as the card of the day, the secrets glow more.
    var glow = state.world.card && state.world.card.code === 'glowworms' ? ' glowday' : '';
    return '<div class="place-view' + glow + '" style="background-image:url(\'' + esc(art) + '\')">' + spots.map(function(sp) {
      var left = narrow ? (sp.at[0] - 21.875) / 0.5625 : (sp.at[0] - 12.5) / 0.75;
      // On the painting, the name before its colon («Bajo la lupa»); the whole of it, for who listens.
      var short = sp.title.split(':')[0].trim();
      return '<button type="button" class="spot ' + sp.kind + (sp.done ? ' done' : '') + (sp.next ? ' next' : '') +
        (left < 26 ? ' flip' : '') + '" style="--x:' + sp.at[0] + ';--y:' + sp.at[1] + '" data-spot="' + sp.kind + ':' +
        esc(String(sp.id)) + '"' + (short !== sp.title ? ' aria-label="' + esc(sp.title) + '"' : '') + '>' +
        (sp.next ? '<span class="spot-hero" aria-hidden="true">' + avatarOf(c, '', true) + '</span>' +
        (c.creature ? '<span class="spot-pet" aria-hidden="true">' + T.creature(c.creature, c.creaturestage) + '</span>' : '') : '') +
        '<span class="spot-icon" aria-hidden="true">' + T.icon(sp.done ? 'lantern' : sp.icon) + '</span><span class="spot-name">' +
        esc(short || sp.title) + '</span></button>';
    }).join('') + (ch.secrets || []).map(function(x) {
      // Something to find, in the painting: a print of ink, or a glint.
      return '<button type="button" class="secret ' + esc(x.kind) + '" style="--x:' + x.x + ';--y:' + x.y + '" data-secret="' +
        esc(x.id) + '" data-secretplace="' + ch.id + '" aria-label="' + esc(t('app_secret_look')) + '"><span aria-hidden="true">' + (x.kind === 'print' ?
        INKPRINT : '✦') + '</span></button>';
    }).join('') + '</div>';
  }

  /** A print of ink that is no animal's: a sole and three toes, run. */
  var INKPRINT = '<svg viewBox="0 0 40 40" width="100%" height="100%"><path d="M20 15c6 0 9 6 9 12s-4 9-9 9-9-3-9-9 3-12 ' +
    '9-12z" fill="#2c1f3d"/><circle cx="11" cy="10" r="3.5" fill="#2c1f3d"/><circle cx="20" cy="6" r="3.5" fill="#2c1f3d"/>' +
    '<circle cx="29" cy="10" r="3.5" fill="#2c1f3d"/><path d="M27 33c3 1 6 1 8 3" stroke="#2c1f3d" stroke-width="2" ' +
    'fill="none" stroke-linecap="round"/></svg>';

  /**
   * A secret found in the painting of a place: what it says, the page of the Great Book it gave, and what the whole set
   * says if it was its last one. It goes to the album.
   *
   * @param {Object} ch The place.
   * @param {Element} btn
   */
  function secretMoment(ch, btn) {
    var id = btn.dataset.secret;
    btn.disabled = true;
    T.api('find_secret', {courseid: cfg.courseid, id: id}).then(function(r) {
      // Out of the painting, and into the album.
      ch.secrets = (ch.secrets || []).filter(function(x) {
        return x.id !== id;
      });
      var m = state.world.mission;
      if (m && m.secrets) {
        m.secrets.found.push({id: id, kind: btn.classList.contains('print') ? 'print' : 'thing', text: r.text});
      }
      btn.remove();
      if (T.fx && !T.fx.sound('clue')) {
        T.fx.sound('chime');
      }
      var layer = document.createElement('div');
      layer.className = 'badge-moment secret-moment';
      layer.setAttribute('role', 'dialog');
      layer.setAttribute('aria-modal', 'true');
      layer.setAttribute('aria-labelledby', 'tesape-secret-title');
      layer.innerHTML = '<div class="badge-card"><div class="kicker" id="tesape-secret-title">' + esc(t('app_secret_found')) +
        '</div><p class="secret-text">' + esc(r.text) + '</p>' + (r.page.title ? '<div class="book-page"><small>' +
        esc(t('app_secret_page')) + '</small><b>' + esc(r.page.title) + '</b><p>' + esc(r.page.text) + '</p></div>' : '') +
        (r.set ? '<div class="conseq good"><span class="what">' + esc(r.set) + '</span></div>' : '') + '<div class="actions">' +
        '<button type="button" class="btn btn-main" data-close>' + esc(t('app_continue')) + '</button></div></div>';
      document.body.appendChild(layer);
      var close = layer.querySelector('[data-close]');
      close.onclick = function() {
        layer.remove();
      };
      close.focus();
    }).catch(function(e) {
      btn.disabled = false;
      T.toast(esc(e.message), true);
    });
  }

  function isNext(kind, id) {
    var n = state.next;
    return !!n && n.kind === kind && (kind === 'game' ? n.code === id : n.id === id);
  }

  function chapterPanel(m, ch, past) {
    var w = state.world;
    var challenge = w.challenges.filter(function(x) {
      return x.id === ch.challengeid;
    })[0];
    var lantern = ch.skipped ? t('app_lantern_skipped') : ch.lit ? t('app_lantern_class') : ch.mine ? t('app_lantern_mine') :
      t('app_lantern_off');
    // With teammates, a scene that can be played as a team: on one device, deciding together, for all who are there.
    var mates = w.team ? w.team.members.filter(function(m) {
      return !m.me;
    }) : [];
    var together = function(sc) {
      return mates.length && sc.mode !== 'solo' ? '<button class="btn ' + (sc.mode === 'team' && !sc.done ? 'btn-main' :
        'btn-soft') + '" data-teamscene="' + sc.id + '">' + esc(t('app_team_play')) + '</button>' : '';
    };
    var scenes = ch.scenes.map(function(sc) {
      return '<li class="item"><span class="grow"><b>' + esc(sc.title) + done(sc) + '</b><small>' + (sc.mode === 'team' ?
        esc(t('app_team_meant')) : '') + (sc.done && sc.better ? (sc.mode === 'team' ? ' · ' : '') +
        esc(t('app_status_better')) : '') + '</small></span>' +
        // In a past expedition, the free expedition: played alone, for pleasure.
        '<span class="item-actions">' + (past ? '' : together(sc)) + '<button class="btn ' + (!past && isNext('scene', sc.id) ?
        'btn-main' : 'btn-soft') + '" data-scene="' + sc.id + '">' + esc(sc.done ? t('app_playagain') :
        t('app_play')) + '</button></span></li>';
    }).join('');
    var acts = past ? [] : w.activities.filter(function(a) {
      return a.chapterid === ch.id;
    });
    // A being peeks out here today: the creature has smelt something.
    var hero = state.world.character;
    var peek = ch.encounter && !past ? '<div class="encounter-card"><span class="encounter-pet" aria-hidden="true">' +
      (hero && hero.creature ? T.creature(hero.creature, hero.creaturestage) : T.icon('lens')) + '</span><div><b>' +
      esc(t('app_encounter_peek', ch.name)) + '</b><p class="help-soft">' + esc(t('app_encounter_help')) + '</p>' +
      '<button type="button" class="btn btn-main" data-encounter="' + ch.id + '">' + esc(t('app_encounter_go')) +
      '</button></div></div>' : '';
    // The scene a battle lost opens, only until the rematch: out of the fold, so that it is seen.
    var night = (ch.quests || []).filter(function(q) {
      return q.after === 'lost';
    }).map(function(q) {
      return '<div class="night-scene"><small>' + esc(t('app_night_scene')) + '</small><b>' + esc(q.title) + done(q) + '</b>' +
        '<p class="help-soft">' + esc(t('app_night_help')) + '</p>' + (past ? '' : '<button class="btn btn-main" data-scene="' +
        q.id + '">' + esc(q.done ? t('app_playagain') : t('app_play')) + '</button>') + '</div>';
    }).join('');
    // Quests: for whoever wants more, whatever their step; gems and pages, never saber.
    var quests = (ch.quests || []).filter(function(q) {
      return q.after !== 'lost';
    }).map(function(q) {
      return '<li class="item"><span class="grow"><b>' + esc(q.title) + done(q) + '</b><small>' + esc(t('app_quest_step_' +
        (q.step === 4 ? 4 : 3))) + (q.done && q.better ? ' · ' + esc(t('app_status_better')) : '') + '</small></span>' +
        '<button class="btn btn-soft" data-scene="' + q.id + '">' + esc(q.done ? t('app_playagain') : t('app_play')) +
        '</button></li>';
    }).join('');
    var games = (ch.games || []).map(function(g) {
      return '<li class="item"><span class="mini-icon">' + T.icon('game') + '</span><span class="grow"><b>' + esc(g.title) +
        '</b><small>' + esc(g.done ? t('app_game_done') : t('app_game_help')) + '</small></span>' + (past ? '' :
        '<button class="btn ' + (isNext('game', g.code) ? 'btn-main' : 'btn-soft') + '" data-game="' + esc(g.code) + '" data-gametype="' +
        esc(g.type || 'nest') + '">' +
        esc(g.done ? t('app_playagain') : t('app_play')) + '</button>') + '</li>';
    }).join('');
    if (past) {
      challenge = null;
    }
    var here = past ? 0 : matesAt()[ch.id] || 0;
    // The page the Embrollo hid here after a battle lost: whoever opens the place finds it.
    var hidden = past ? [] : (state.world.stains || []).filter(function(s) {
      return s.hidden && s.chapter === ch.code;
    });
    peek = hidden.map(function(s) {
      return '<div class="hidden-page"><span aria-hidden="true">' + PAGE + '</span><div><b>' + esc(t('app_page_found_here')) +
        '</b><p class="help-soft">' + esc(t('app_page_found_help', s.title)) + '</p><button type="button" class="btn btn-main" ' +
        'data-stain="' + s.id + '">' + esc(t('app_page_found_take')) + '</button></div></div>';
    }).join('') + peek;
    return '<section class="card chapter">' + listen() + '<div class="kicker">' + esc(lantern) + '</div><h2>' +
      esc(ch.name) + '</h2>' + (here ? '<p class="mates-here">' + T.icon('team') + ' ' + esc(t('app_mates_here', here)) +
      '</p>' : '') + placeView(ch, past) +
      '<p>' + esc(ch.intro) + '</p>' + (ch.memory && ch.memory.length ? '<div class="memory"><small>' +
      esc(t('app_memory')) + '</small>' + ch.memory.map(function(line) {
        return '<p>' + esc(line) + '</p>';
      }).join('') + '</div>' : '') + (ch.letter ? '<blockquote class="letter"><small>' + esc(t('app_letter')) +
      '</small>' + esc(ch.letter) + '</blockquote>' : '') + (ch.teaser ? '<p class="teaser"><b>' + esc(t('app_teaser')) +
      '</b> ' + esc(ch.teaser) + '</p>' : '') + peek + night + (scenes ? '<ul class="list">' + scenes + '</ul>' : '') +
      photoCard(ch, past) + more(games, quests, acts, challenge) + '</section>';
  }

  /**
   * The photo of the classroom a team sent for a place, if any.
   *
   * @param {string} code Code of the place.
   * @return {Object|null} chapter, status, url, mine.
   */
  function photoOf(code) {
    return (state.world.photos || []).filter(function(p) {
      return p.chapter === code;
    })[0] || null;
  }

  /**
   * The photo of the classroom of a place: once the teacher approves it, a sticker of the map of the team and of the
   * class screen. Never people: what they found, their drawings, the notebook.
   *
   * @param {Object} ch
   * @param {boolean} past
   * @return {string}
   */
  function photoCard(ch, past) {
    var p = photoOf(ch.code);
    var status = p ? p.status : '';
    // Who may not send photos sees the one of the team on the map and in the place, without the button.
    if (past || !state.world.team || (!state.world.sendphoto && status !== 'approved')) {
      return '';
    }
    var note = {pending: 'app_photo_pending', rejected: 'app_photo_rejected', approved: 'app_photo_approved'}[status] ||
      'app_photo_help';
    return '<div class="photo-card' + (status ? ' ' + status : '') + '">' + (status === 'approved' && p.url ?
      '<img class="photo-print" src="' + esc(p.url) + '" alt="' + esc(t('app_photo_alt', ch.name)) + '">' : '') +
      '<div class="grow"><b>' + esc(t('app_photo_title')) + '</b><p class="help-soft">' + esc(t(note)) + '</p>' +
      (state.world.sendphoto ? '<label class="btn btn-soft photo-pick">' + T.icon('camera') + ' ' + esc(t(status ?
      'app_photo_again' : 'app_photo_send')) + '<input type="file" accept="image/*" capture="environment" class="sr-only" ' +
      'data-photo="' + esc(ch.code) + '"></label>' : '') + '</div></div>';
  }

  /**
   * A photo made smaller on the device before it leaves it: drawn again on a canvas, it also loses what the camera
   * writes in the file (where and when it was taken).
   *
   * @param {File} file
   * @return {Promise<string>} The picture, as a data URL.
   */
  function shrinkPhoto(file) {
    return new Promise(function(resolve, reject) {
      var src = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function() {
        var k = Math.min(1, 1024 / Math.max(img.naturalWidth, img.naturalHeight));
        var c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(img.naturalWidth * k));
        c.height = Math.max(1, Math.round(img.naturalHeight * k));
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(src);
        resolve(c.toDataURL('image/jpeg', 0.82));
      };
      img.onerror = function() {
        URL.revokeObjectURL(src);
        reject(new Error(t('app_photo_bad')));
      };
      img.src = src;
    });
  }

  /**
   * What else a place has, folded: the scenes of the place come first, the rest only for who looks for it (minigames,
   * quests, activities of Moodle, the challenge of the class).
   *
   * @param {string} games
   * @param {string} quests
   * @param {Object[]} acts
   * @param {Object|null} challenge
   * @return {string}
   */
  function more(games, quests, acts, challenge) {
    var count = (games.match(/<li/g) || []).length + (quests.match(/<li/g) || []).length + acts.length + (challenge ? 1 : 0);
    if (!count) {
      return '';
    }
    return '<details class="more-place"><summary>' + esc(t('app_more_place')) + '</summary>' +
      (games ? '<h3 style="margin-top:16px">' + esc(t('app_minigames')) + '</h3><ul class="list">' + games + '</ul>' : '') +
      (quests ? '<h3 style="margin-top:16px">' + esc(t('app_quests')) + '</h3><p class="help-soft">' +
      esc(t('app_quests_help')) + '</p><ul class="list">' + quests + '</ul>' : '') +
      (acts.length ? '<h3 style="margin-top:16px">' + esc(t('app_inmoodle')) + '</h3><ul class="list">' +
      acts.map(activityItem).join('') + '</ul>' : '') +
      (challenge ? '<h3 style="margin-top:16px">' + esc(t('app_inclass')) + '</h3><ul class="list">' + challengeItem(challenge) +
      '</ul>' : '') + '</details>';
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
      rewards.push(t(a.gems === 1 ? 'app_encounter_gem' : 'app_encounter_gems', a.gems));
    }
    if (a.teamwork) {
      rewards.push(t('app_teamwork'));
    }
    return '<li class="item"><span class="mini-icon"><img src="' + esc(a.icon) + '" alt="" width="24" height="24"></span>' +
      '<span class="grow"><b>' + esc(a.name) + '</b><small>' + esc(rewards.join(' · ')) + '</small></span>' +
      done(a) + '<button class="btn ' +
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
    })[0] || todayChapter(m);
    var lit = m.chapters.filter(function(ch) {
      return ch.mine;
    }).length;
    return '<div class="mission"><div class="mission-head"><div><div class="kicker">' + esc(m.subtitle) + '</div><h2>' +
      esc(m.title) + '</h2></div><div class="tally"><span>' + T.icon('lantern') + ' ' + lit + '/' + m.chapters.length +
      '</span><span>' + T.icon('log') + ' ' + m.pages.length + '/' + m.pagecount + '</span></div></div>' +
      (!past && farFrom() ? '<p class="team-banner far-banner">' + esc(t('app_far_banner')) + '</p>' : '') +
      (past ? '' : nextCard(m)) + '<div class="mission-grid">' + missionMap(m) + chapterPanel(m, chapter, past) + '</div></div>';
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
   * The buttons of the world tab.
   *
   * @param {boolean} [stay] Only the panel of a place changed: the map stays where it is.
   */
  function bindWorld(stay) {
    bindListen(app);
    var wake = app.querySelector('[data-wake]');
    if (wake) {
      wake.onclick = function() {
        state.woke = true;
        if (T.fx) {
          T.fx.sound('chirp');
        }
        T.toast(esc(t('app_pet_woke', creatureName(state.world.character))));
        world();
      };
    }
    var seen = app.querySelector('[data-battlesseen]');
    if (seen) {
      seen.onclick = function() {
        state.world.battles = null;
        world();
      };
    }
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
    var step = app.querySelector('[data-next-step]');
    if (step && state.next) {
      step.onclick = function() {
        var n = state.next;
        if (n.kind === 'page') {
          feedMoment();
        } else if (n.kind === 'game') {
          var play = {errors: T.playErrors, whack: T.playWhack, venn: T.playVenn, whoami: T.playWhoami}[n.type] || T.playNest;
          play({code: n.code, world: state.world, onclose: function(world) {
            state.world = world;
            route();
          }});
        } else {
          T.playScene({sceneid: Number(n.id), world: state.world, onclose: function(world) {
            state.world = world;
            route();
          }});
        }
      };
    }
    app.querySelectorAll('[data-spot]').forEach(function(b) {
      b.onclick = function() {
        var parts = b.dataset.spot.split(':');
        var target = app.querySelector(parts[0] === 'game' ? '.chapter [data-game="' + parts[1] + '"]' :
          '.chapter [data-scene="' + parts[1] + '"]');
        if (target) {
          target.click();
        }
      };
    });
    var feed = app.querySelector('[data-feed]');
    if (feed) {
      feed.onclick = feedMoment;
    }
    app.querySelectorAll('[data-encounter]').forEach(function(b) {
      b.onclick = function() {
        var ch = (state.world.mission.chapters || []).filter(function(x) {
          return x.id === Number(b.dataset.encounter);
        })[0];
        if (ch && ch.encounter) {
          meet(ch);
        }
      };
    });
    app.querySelectorAll('[data-photo]').forEach(function(input) {
      input.onchange = function() {
        var file = input.files && input.files[0];
        var pick = input.closest('.photo-pick');
        if (!file || pick.classList.contains('busy')) {
          return;
        }
        pick.classList.add('busy');
        shrinkPhoto(file).then(function(picture) {
          return T.api('photo', {courseid: cfg.courseid, action: 'upload', chapter: input.dataset.photo, picture: picture});
        }).then(function(r) {
          state.world.photos = r.photos;
          T.toast(esc(t('app_photo_sent')));
          var card = app.querySelector('.photo-card');
          var m = state.world.mission;
          var ch = m && m.chapters.filter(function(c) {
            return c.code === input.dataset.photo;
          })[0];
          if (card && ch) {
            var box = document.createElement('div');
            box.innerHTML = photoCard(ch, false);
            card.replaceWith(box.firstElementChild);
            bindWorld(true);
          }
        }).catch(function(e) {
          pick.classList.remove('busy');
          T.toast(esc(e.message), true);
        });
      };
    });
    app.querySelectorAll('[data-thread]').forEach(function(b) {
      b.onclick = function(ev) {
        ev.stopPropagation();
        prankFixed('thread');
        var fo = b.closest('foreignObject');
        if (fo) {
          fo.classList.add('pulled');
          setTimeout(function() {
            fo.remove();
          }, 600);
        }
        if (T.fx) {
          T.fx.sound('tap');
        }
        T.toast(esc(t('app_prank_thread')));
      };
    });
    app.querySelectorAll('[data-secret]').forEach(function(b) {
      b.onclick = function(ev) {
        ev.stopPropagation();
        var ch = (state.world.mission.chapters || []).filter(function(x) {
          return x.id === Number(b.dataset.secretplace);
        })[0];
        if (ch) {
          secretMoment(ch, b);
        }
      };
    });
    app.querySelectorAll('[data-minion]').forEach(function(b) {
      b.onclick = function(ev) {
        ev.stopPropagation();
        var ch = (state.world.mission.chapters || []).filter(function(x) {
          return x.id === Number(b.dataset.minion);
        })[0];
        if (ch && ch.minion) {
          minionMoment(ch.minion);
        }
      };
    });
    app.querySelectorAll('[data-stain]').forEach(function(b) {
      b.onclick = function(ev) {
        ev.stopPropagation();
        var stain = (state.world.stains || []).filter(function(s) {
          return s.id === Number(b.dataset.stain);
        })[0];
        if (stain) {
          (stain.kind === 'page' ? pageMoment : stainMoment)(stain);
        }
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
    var tall = stay ? null : app.querySelector('.map-wrap.tall');
    var here = tall && tall.querySelector('.place.here .ring');
    if (here) {
      tall.scrollTop += here.getBoundingClientRect().top - tall.getBoundingClientRect().top - tall.clientHeight / 2;
    }
    app.querySelectorAll('[data-chapter]').forEach(function(g) {
      var pickChapter = function() {
        state.chapter = Number(g.dataset.chapter);
        var m = state.visit || state.world.mission;
        var ch = m && m.chapters.filter(function(c) {
          return c.id === state.chapter;
        })[0];
        // The name the Embrollo put to the place comes off on going into it.
        if (ch && state.prank && state.prank.name && state.prank.name.chapter === ch.code) {
          prankFixed('name');
          var label = g.querySelector('text.place-label');
          if (label) {
            label.outerHTML = T.mapLabel(ch.x, ch.y + 44, ch.name);
          }
          g.classList.remove('pranked');
          T.toast(esc(t('app_prank_name', ch.name)));
        }
        var old = app.querySelector('.mission-grid .chapter');
        if (ch && old) {
          // Only the panel of the place changes: the map stays as it is, without a flash, its fireflies where they were.
          app.querySelectorAll('[data-chapter]').forEach(function(o) {
            o.classList.toggle('selected', o === g);
            o.classList.remove('picked');
          });
          // The place answers the touch, and its panel comes in.
          void g.getBoundingClientRect();
          g.classList.add('picked');
          var box = document.createElement('div');
          box.innerHTML = chapterPanel(m, ch, !!state.visit);
          old.replaceWith(box.firstElementChild);
          bindWorld(true);
          walkTo(g, ch);
          if (window.innerWidth < 900) {
            openSheet(g);
          }
        } else {
          world();
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
        var visit = state.visit;
        T.playScene({sceneid: Number(b.dataset.scene), world: state.world, onclose: function(world) {
          state.world = world;
          if (!visit) {
            route();
            return;
          }
          // In the free expedition, the past one again, with what was just played in it.
          T.api('get_expedition', {courseid: cfg.courseid, missionid: visit.id}).then(function(r) {
            state.visit = r.mission;
            route();
          }).catch(function() {
            route();
          });
        }});
      };
    });
    app.querySelectorAll('[data-teamscene]').forEach(function(b) {
      b.onclick = function() {
        teamPicker(Number(b.dataset.teamscene));
      };
    });
    app.querySelectorAll('[data-game]').forEach(function(b) {
      b.onclick = function() {
        // Defensa del nido, a hunt for mistakes of the Embrollo, Who is alive?, the two circles or Who am I?
        var play = {errors: T.playErrors, whack: T.playWhack, venn: T.playVenn, whoami: T.playWhoami}[b.dataset.gametype] ||
          T.playNest;
        play({code: b.dataset.game, world: state.world, onclose: function(world) {
          state.world = world;
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
        esc(t('app_expedition_visiting')) + '</div><p class="help-soft">' + esc(t('app_expedition_free')) + '</p>' +
        '<div class="actions" style="justify-content:flex-start"><button ' +
        'class="btn btn-soft" data-leave>' + esc(t('app_expedition_back')) + '</button></div></section>' +
        missionView(state.visit, true);
    }
    if (w.mission) {
      // A page hidden after a battle lost: somewhere in the valley, to be found.
    var hidden = (w.stains || []).some(function(s) {
      return s.hidden;
    }) ? '<p class="team-banner hidden-banner">' + esc(t('app_page_hidden')) + '</p>' : '';
    return battlesCard() + hidden + missionView(w.mission) + expeditionsCard();
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

  /**
   * A button that reads a card aloud, for who has «Reading aloud»: the scenes have theirs; the rest of the game, this.
   *
   * @return {string}
   */
  function listen() {
    return T.adapted.readaloud && T.readAloud && window.speechSynthesis ? '<button type="button" class="btn btn-soft ' +
      'read-card" data-readcard aria-label="' + esc(t('app_read_aloud')) + '" title="' + esc(t('app_read_aloud')) +
      '">' + T.icon('speak') + '</button>' : '';
  }

  /**
   * The read-aloud buttons in a part of the page: each reads its item, card or moment.
   *
   * @param {HTMLElement} root
   */
  function bindListen(root) {
    root.querySelectorAll('[data-readcard]').forEach(function(b) {
      b.onclick = function() {
        T.readAloud(b.closest('.item, .card, .badge-card'));
      };
    });
  }

  /**
   * For who follows the expedition from far: the battles of the class since they last came in, once.
   *
   * @return {string}
   */
  function battlesCard() {
    var list = state.world.battles || [];
    if (!list.length) {
      return '';
    }
    var day = function(time) {
      try {
        return new Intl.DateTimeFormat(cfg.lang, {weekday: 'long', day: 'numeric', month: 'long'}).format(new Date(time * 1000));
      } catch (e) {
        return new Date(time * 1000).toLocaleDateString();
      }
    };
    return '<section class="card battles-missed" style="margin-bottom:12px">' + listen() + '<div class="kicker">' +
      esc(t('app_far_battles')) + '</div><ul class="list">' + list.map(function(b) {
        var face = T.embrollo(b.outcome === 'won' ? 0.2 : 1);
        return '<li class="item"><span class="battle-mini">' + face + '</span><span class="grow"><b>' +
          esc(t(b.outcome === 'won' ? 'app_far_battle_won' : 'app_far_battle_lost')) + '</b><small>' + esc(day(b.time)) +
          (b.lantern ? ' · ' + esc(t('app_far_battle_lantern', b.lantern)) : '') + '</small></span></li>';
      }).join('') + '</ul><div class="actions" style="justify-content:flex-start"><button class="btn btn-soft" ' +
      'data-battlesseen>' + esc(t('app_far_battles_ok')) + '</button></div></section>';
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
        // How the team is doing today: the class screen shows it beside the team, without names.
        '<div class="team-state" role="group" aria-label="' + esc(t('app_teamstate')) + '"><small>' +
        esc(t('app_teamstate')) + '</small><div class="pills">' + ['ready', 'working', 'help'].map(function(st) {
          return '<button type="button" class="chip state-' + st + '" data-teamstate="' + st + '" aria-pressed="' +
            (team.state === st) + '">' + esc(t('app_teamstate_' + st)) + '</button>';
        }).join('') + '</div>' + (team.state === 'help' ? '<p class="help-soft">' + esc(t('app_teamstate_help_note')) +
        '</p>' : '') + '</div><div class="team-week"><span class="meter level"><i style="width:' + pct + '%"></i></span>' +
        '<small>' + esc(wk.reached ? t('app_team_reached') : t('app_team_week', {done: wk.done, needed: wk.needed,
        part: wk.part})) + '</small></div><div class="team-week"><span class="meter energy"><i style="width:' + epct +
        '%"></i></span><small>' + esc(t(team.carer ? 'app_team_energy_carer' : 'app_team_energy', {energy: team.energy,
        max: team.energymax})) + '</small></div>' + bannerLine(team) + '<ul class="list">' + team.members.map(function(mate) {
          var c = mate.character;
          var m = c && c.member;
          var tag = m && m.status === 'paused' ? '<span class="tag todo">' + esc(t('app_resting')) + '</span>' :
            m && m.status === 'fallen' ? '<span class="tag notyet">' + esc(t('app_wornout')) + '</span>' :
            m && m.remote && !mate.me ? '<span class="tag">' + esc(t('app_team_far')) + '</span>' : '';
          return '<li class="item"><span class="face">' + (c ? avatarOf(c, '', true) : '') + '</span><span class="grow"><b>' +
            esc(mate.name) + (mate.me ? ' (' + esc(t('app_you')) + ')' : '') + '</b><small>' +
            (c ? esc(T.roleName(fam, c.role, c.nameform)) + ' · ' + esc(rank(c)) + ' · ' + esc(t('app_level')) + ' ' + c.level +
            (c.creature ? ' · ' + esc(t('app_with_creature', creatureName(c))) : '') : esc(t('app_nocharacter'))) +
            '</small>' + (!mate.me && m && m.status === 'fallen' && w.character && w.character.role === 'healer' ?
            '<small class="help-soft">' + esc(t('app_rescue_how')) + '</small>' : '') + '</span>' +
            (c && c.creature ? '<span class="team-creature" aria-hidden="true">' +
            T.creature(c.creature, c.creaturestage) + '</span>' : '') + tag + (m && m.energy >= 0 ? '<span class="bars">' +
            '<span class="meter energy" ' +
            'title="' + esc(t('app_energy')) + '"><i style="width:' + Math.round(100 * m.energy / w.energymax) +
            '%"></i></span></span>' : '') + '</li>';
        }).join('') + '</ul></section>' + councilCard(team) + reserveCard(team.reserve) + pactsCard(team.pacts);
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
  /**
   * The council of the team: the task of the place of today with who of the team has the role of each part, and the
   * role that starts the hand in the scenes of today.
   *
   * @param {Object} team
   * @return {string}
   */
  function councilCard(team) {
    var cn = team.council;
    if (!cn) {
      return '';
    }
    var fam = state.world.family;
    var roles = [];
    team.members.forEach(function(mate) {
      var role = mate.character && mate.character.role;
      if (role && roles.indexOf(role) < 0) {
        roles.push(role);
      }
    });
    var parts = cn.parts.length ? '<ul class="list council-parts">' + cn.parts.map(function(p) {
      return '<li class="item' + (p.done ? ' done' : '') + '"><span class="grow"><b>' + esc(T.roleName(fam, p.role, 'm')) +
        (p.done ? ' ✓' : '') + '</b><small>' + esc(p.text) + '</small><small class="help-soft">' +
        esc(p.who ? t('app_council_who', p.who) : t('app_council_nobody')) + '</small></span></li>';
    }).join('') + '</ul>' : '';
    return '<section class="card council-card"><div class="kicker">' + esc(t('app_council')) + '</div><h3>' +
      esc(cn.place) + '</h3>' + (cn.task ? '<p><b>' + esc(cn.task) + '</b></p>' + parts : '<p class="help-soft">' +
      esc(t('app_council_notask')) + '</p>') + (roles.length > 1 ? '<div class="council-first" role="group" ' +
      'aria-label="' + esc(t('app_council_first')) + '"><small>' + esc(t('app_council_first')) + '</small><div class="pills">' +
      roles.map(function(role) {
        return '<button type="button" class="chip" data-first="' + role + '" aria-pressed="' + (team.first === role) + '">' +
          esc(T.roleName(fam, role, 'm')) + '</button>';
      }).join('') + '</div></div>' : '') + '</section>';
  }

  /**
   * The reserve of breath of the team: what it has, the proposals of today to confirm or take back, and what can be
   * proposed (getting up, only for who is worn out).
   *
   * @param {Object} r breath, max, costs, open.
   * @return {string}
   */
  function reserveCard(r) {
    if (!r) {
      return '';
    }
    var me = state.world.character && state.world.character.member;
    var cost = {};
    r.costs.forEach(function(c) {
      cost[c.kind] = c.cost;
    });
    var open = r.open.map(function(o) {
      return '<li class="item"><span class="grow">' + esc(o.mine ? t('app_reserve_' + o.kind, cost[o.kind]) + ' · ' +
        t('app_reserve_waiting') : t('app_reserve_asks_' + o.kind, o.by)) + '</span>' + (o.mine ?
        '<button type="button" class="btn btn-soft" data-reserve="cancel" data-spend="' + o.id + '">' +
        esc(t('app_reserve_cancel')) + '</button>' : '<button type="button" class="btn btn-main" data-reserve="confirm" ' +
        'data-spend="' + o.id + '"' + (r.breath < cost[o.kind] ? ' disabled' : '') + '>' + esc(t('app_reserve_confirm')) +
        '</button>') + '</li>';
    }).join('');
    var asked = r.open.map(function(o) {
      return o.kind;
    });
    var kinds = ['refresh'].concat(me && me.status === 'fallen' ? ['rise'] : []).filter(function(k) {
      return asked.indexOf(k) < 0;
    });
    var propose = kinds.map(function(k) {
      return '<li class="item"><span class="grow">' + esc(t('app_reserve_' + k, cost[k])) + '</span><button type="button" ' +
        'class="btn btn-soft" data-reserve="propose" data-kind="' + k + '"' + (r.breath < cost[k] ? ' disabled' : '') + '>' +
        esc(t('app_reserve_propose')) + '</button></li>';
    }).join('');
    return '<section class="card reserve-card"><div class="kicker">' + esc(t('app_reserve')) + '</div><div class="team-week">' +
      '<span class="meter breath"><i style="width:' + Math.round(100 * r.breath / r.max) + '%"></i></span><small>' +
      esc(t('app_reserve_amount', r)) + '</small></div><p class="help-soft">' + esc(t('app_reserve_help')) + '</p>' +
      '<ul class="list">' + open + propose + '</ul></section>';
  }

  function pactsCard(p) {
    if (!p) {
      return '';
    }
    var tags = {open: ['todo', 'app_pact_open'], kept: ['done', 'app_pact_kept'], broken: ['notyet', 'app_pact_broken']};
    var list = p.list.length ? '<ul class="list">' + p.list.map(function(x) {
      // Without a mark: one of a teammate that is not for the team to see.
      var tag = x.status ? tags[x.status] || tags.open : null;
      return '<li class="item' + (x.me ? ' mine' : '') + '"><span class="grow"><b>' + esc(x.name) + (x.me ? ' (' +
        esc(t('app_you')) + ')' : '') + '</b><small>' + esc(x.text) + '</small></span>' + (tag ? '<span class="tag ' +
        tag[0] + '">' + esc(t(tag[1])) + '</span>' : '') + '</li>';
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
      return !mate.me && mm && mm.status !== 'paused' && mm.status !== 'away';
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
        if (mate.me || !m || m.status === 'paused' || m.status === 'away') {
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
    app.querySelectorAll('[data-reserve]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        T.api('team_reserve', {courseid: cfg.courseid, action: b.dataset.reserve, kind: b.dataset.kind || '',
          spendid: Number(b.dataset.spend || 0)}).then(function() {
          return refresh();
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
    app.querySelectorAll('[data-first]').forEach(function(b) {
      b.onclick = function() {
        var role = b.getAttribute('aria-pressed') === 'true' ? '' : b.dataset.first;
        T.api('set_team_state', {courseid: cfg.courseid, state: '', first: role}).then(function(r) {
          state.world.team.first = r.first;
          world();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
    app.querySelectorAll('[data-teamstate]').forEach(function(b) {
      b.onclick = function() {
        T.api('set_team_state', {courseid: cfg.courseid, state: b.dataset.teamstate}).then(function(r) {
          state.world.team.state = r.state;
          world();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
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

  /**
   * Done, as the game says it: a lit lantern (and the word, for who listens).
   *
   * @param {Object} x Anything with done.
   * @return {string}
   */
  function done(x) {
    return x.done ? ' <span class="done-lantern" title="' + esc(t('app_status_done')) + '">' + T.icon('lantern') +
      '<span class="sr-only">' + esc(t('app_status_done')) + '</span></span>' : '';
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
      rewards.push(t(ch.gems === 1 ? 'app_encounter_gem' : 'app_encounter_gems', ch.gems));
    }
    return '<li class="item" style="align-items:flex-start"><span class="grow"><b>' + esc(ch.name) + '</b><small>' +
      esc(rewards.join(' · ')) + (ch.teamwork ? ' · ' + esc(t('app_teamwork')) : '') + '</small>' +
      (ch.description ? '<div style="margin-top:6px;font-size:15px">' + ch.description + '</div>' : '') +
      (ch.note ? '<div class="note"><b>' + esc(t('app_teachersays')) + ':</b> ' + esc(ch.note) + '</div>' : '') +
      partsHtml(ch) + '</span>' + listen() + (status === 'done' ? done({done: true}) : '<span class="tag ' + status + '">' +
      esc(t(label)) + '</span>') + '</li>';
  }

  /**
   * A team task with parts: each with its role, whether it is done in the team and by whom; the part of their role is
   * theirs to mark, and one of a role missing that day can be covered by anyone.
   *
   * @param {Object} ch
   * @return {string}
   */
  function partsHtml(ch) {
    var parts = ch.parts || [];
    if (!parts.length) {
      return '';
    }
    var c = state.world.character || {};
    var open = ch.status !== 'done' && ch.status !== 'merit';
    var count = parts.filter(function(p) {
      return p.done;
    }).length;
    return '<ul class="task-parts">' + parts.map(function(p, i) {
      var mine = p.role && p.role === c.role;
      var who = p.role ? T.roleName(state.world.family, p.role, c.role === p.role ? c.nameform : 'm') : t('app_part_anyone');
      var action = !open ? '' : p.done ? (p.mine ? '<button type="button" class="btn btn-soft" data-partch="' + ch.id +
        '" data-part="' + i + '" data-undo>' + esc(t('app_part_undo')) + '</button>' : '') : '<button type="button" ' +
        'class="btn ' + (mine ? 'btn-main' : 'btn-soft') + '" data-partch="' + ch.id + '" data-part="' + i + '">' +
        esc(t(mine ? 'app_part_mine' : 'app_part_cover')) + '</button>';
      return '<li class="' + (p.done ? 'done' : '') + (mine ? ' mine' : '') + '"><span class="grow"><b>' + esc(who) +
        (p.done ? ' ✓' : '') + '</b><small>' + esc(p.text) + '</small>' + (p.done && p.by ? '<small class="help-soft">' +
        esc(t('app_part_done_by', p.by)) + '</small>' : '') + '</span>' + action + '</li>';
    }).join('') + '</ul><small class="help-soft">' + esc(t('app_parts_help', count + '/' + parts.length)) + '</small>';
  }

  /** The friends of the expedition: listening to what they have to tell. */
  function bindFriends() {
    app.querySelectorAll('[data-friend]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        friendMoment(b.dataset.friend, Number(b.dataset.at));
      };
    });
  }

  /** «My part is done» and the rest: marked, and the task drawn again. */
  function bindParts() {
    app.querySelectorAll('[data-part]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        var id = Number(b.dataset.partch);
        T.api('mark_part', {courseid: cfg.courseid, challengeid: id, part: Number(b.dataset.part),
          done: !b.hasAttribute('data-undo')}).then(function(r) {
          state.world.challenges.forEach(function(ch) {
            if (ch.id === id) {
              ch.parts = r.parts;
            }
          });
          if (T.fx && !b.hasAttribute('data-undo')) {
            T.fx.sound('item');
          }
          world();
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
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
      // A touch with a reason says why: «Checked before claiming · From your teacher».
      if (e.note) {
        reason = e.note + ' · ' + reason;
      }
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
    var items = w.items.length ? '<section class="card items-card"><h2>' + esc(t('app_items')) + '</h2>' + wornLine() +
      '<ul class="list">' + w.items.map(function(i) {
        var art = T.artUrl(['item.' + i.code]);
        // What can be worn says where and what it does so, with its button.
        var wear = i.fits && !i.lent ? '<small class="item-worn">' + esc(i.worn) + '</small>' : '';
        var button = i.fits && !i.lent ? '<button type="button" class="btn ' + (i.slot ? 'btn-soft' : 'btn-main') +
          '" data-wear="' + esc(i.code) + '" data-on="' + (i.slot ? 0 : 1) + '">' + esc(t(i.slot ? 'app_wear_off' :
          'app_wear_' + i.fits)) + '</button>' : '';
        return '<li class="item' + (i.slot ? ' worn' : '') + '">' + (art ? '<img class="item-art" src="' + esc(art) +
          '" alt="">' : '<span style="width:22px;height:22px;flex:none">' + T.icon('gems') + '</span>') +
          '<span class="grow"><b>' + esc(i.name) + '</b>' + (i.slot ? '<span class="tag done">' + esc(t('app_worn_' + i.slot)) +
          '</span>' : '') + '<small>' + esc(i.description) + '</small>' + wear + (i.lent ?
          '<small class="item-trade">' + esc(t('app_item_lent', i.lent)) + '</small>' : i.borrowed ? '<small class="item-trade">' +
          esc(t('app_item_borrowed', i.borrowed)) + '</small>' : '') + button + '</span></li>';
      }).join('') + '</ul></section>' : '';
    return '<div class="grid2">' + book + items + '</div>' + friendsCard() + '<section class="card" style="margin-top:16px">' +
      '<h2>' + esc(t('app_tab_log')) + '</h2>' + logList(w.log) + '</section>';
  }

  /**
   * What the hero wears: in the hand and in the pack, or nothing yet.
   *
   * @return {string}
   */
  function wornLine() {
    var worn = {};
    (state.world.items || []).forEach(function(i) {
      if (i.slot) {
        worn[i.slot] = i.name;
      }
    });
    return '<p class="help-soft worn-line">' + esc(t('app_worn_line', {hand: worn.hand || t('app_worn_nothing'),
      pack: worn.pack || t('app_worn_nothing')})) + '</p>';
  }

  /**
   * Whether a friend of the expedition has something new to tell.
   *
   * @return {boolean}
   */
  function friendWaiting() {
    var m = state.world && state.world.mission;
    return !!(m && (m.friends || []).some(function(f) {
      return f.talks.some(function(k) {
        return k.open && !k.read;
      });
    }));
  }

  /**
   * The friends of the expedition: each one, with the pages given back from what is theirs and what they told; what
   * they have to tell, to listen to; what is still to come, with the pages it takes.
   *
   * @return {string}
   */
  function friendsCard() {
    var m = state.world.mission;
    if (!m || !(m.friends || []).length) {
      return '';
    }
    return '<section class="card friends-card" style="margin-top:16px"><h2>' + esc(t('app_friends')) + '</h2><p class="help-soft">' +
      esc(t('app_friends_help')) + '</p><ul class="list">' + m.friends.map(function(f) {
        var art = T.artUrl(['speaker.' + f.code + '.' + m.skin, 'speaker.' + f.code]);
        var talks = f.talks.map(function(k) {
          if (k.open && !k.read) {
            return '<button type="button" class="btn btn-main" data-friend="' + esc(f.code) + '" data-at="' + k.at + '">' +
              esc(t('app_friend_listen')) + '</button>';
          }
          return k.open ? '<blockquote class="friend-said">«' + esc(k.text) + '»</blockquote>' : '<small class="help-soft">' +
            esc(t('app_friend_later', k.at)) + '</small>';
        }).join('');
        return '<li class="item friend"><span class="face">' + (art ? '<img src="' + esc(art) + '" alt="">' : T.icon('me')) +
          '</span><span class="grow"><b>' + esc(f.name) + '</b><small>' + esc(t('app_friend_pages', f.pages + '/' + f.of)) +
          '</small>' + talks + '</span></li>';
      }).join('') + '</ul></section>';
  }

  /**
   * What a friend tells: their picture, their words and, the first time, their gift.
   *
   * @param {string} code
   * @param {number} at
   */
  function friendMoment(code, at) {
    var f = state.world.mission.friends.filter(function(x) {
      return x.code === code;
    })[0];
    T.api('friend_talk', {courseid: cfg.courseid, friend: code, at: at}).then(function(r) {
      var art = T.artUrl(['speaker.' + code + '.' + state.world.mission.skin, 'speaker.' + code]);
      var layer = document.createElement('div');
      layer.className = 'badge-moment friend-moment';
      layer.setAttribute('role', 'dialog');
      layer.setAttribute('aria-modal', 'true');
      layer.setAttribute('aria-labelledby', 'tesape-friend-title');
      layer.innerHTML = '<div class="badge-card">' + (art ? '<img class="friend-face" src="' + esc(art) + '" alt="">' : '') +
        '<h2 id="tesape-friend-title">' + esc(f ? f.name : '') + '</h2><p class="lead">«' + esc(r.text) + '»</p>' +
        (r.gems ? '<p class="conseq good"><span class="what">' + esc(r.gems === 1 ? t('app_friend_gift_one') :
          t('app_friend_gift', r.gems)) + '</span></p>' : '') +
        '<div class="actions"><button type="button" class="btn btn-main" data-close>' + esc(t('app_continue')) +
        '</button></div></div>';
      document.body.appendChild(layer);
      if (T.fx) {
        T.fx.sound('heart');
      }
      var close = layer.querySelector('[data-close]');
      close.focus();
      close.onclick = function() {
        layer.remove();
        refresh();
      };
    }).catch(function(e) {
      T.toast(esc(e.message), true);
    });
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
    var creature = c.creature ? '<div class="creature-box' + glow + '"><button type="button" class="pet-me" data-petme ' +
      'aria-label="' + esc(t('app_pet_pat', creatureName(c))) + '">' + T.creature(c.creature, c.creaturestage) + '</button><b>' +
      esc(creatureName(c)) + '</b><small>' + esc(t('app_creaturestage', {n: c.creaturestage,
      name: stageName(c.creaturestage)})) + '</small>' + (T.creatureMark(c.creature) ? '<small class="creature-mark-says">' +
      esc(T.creatureMark(c.creature)) + '</small>' : '') + petCare(c) + '</div>' : '';
    var unlock = nextUnlock(c);
    return '<section class="card sheet"><div class="sheet-art">' + avatarOf(c, title(c)) + creature + '</div><div class="stats"><h2>' + esc(myName()) +
      '</h2><p style="color:var(--text-soft)">' + esc(rank(c)) + ' · ' + esc(title(c)) + '</p>' + traits(c) + talentsHtml(c) + skillsHtml(c) +
      '<div class="stat"><span>' + esc(t('app_level')) + '</span><b>' + c.level + '</b></div>' +
      '<div class="meter level" style="margin:6px 0 4px"><i style="width:' + pct + '%"></i></div><small style="color:' +
      'var(--text-soft)">' + esc(c.levelneeded ? t('app_tonext', c.levelneeded - c.levelinto) : t('app_maxlevel')) +
      '</small>' + (unlock ? '<p class="unlock">' + esc(unlock) + '</p>' : '') + '<div class="stat"><span>' +
      esc(t('app_saber')) + '</span><b>' + c.saber + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_renown')) + '</span><b>' + (m.renown || 0) + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_gems')) + '</span><b>' + c.gems + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_energy')) + '</span><b>' + (m.energy || 0) + ' / ' + w.energymax + '</b></div>' +
      '<div class="stat"><span>' + esc(t('app_breath')) + '</span><b>' + (m.breath || 0) + ' / ' + w.breathmax + '</b></div>' +
      '<div class="actions no-print" style="justify-content:flex-start;margin-top:16px">' +
      '<button class="btn btn-soft" data-print>' + esc(t('app_print')) +
      '</button><a class="btn btn-soft" href="' + esc(cfg.courseurl) + '">' + esc(t('app_exit')) + '</a></div></div>' +
      '</section>' + meDoors();
  }

  /**
   * The home of the creature: it sleeps in its nest, with what the hero has won around it (the items, the golden
   * cards, its coat and its paint), and a short outing with it, for whoever wants it at home: its page, the being of
   * the day and the stain of tonight, in any order. Nothing of the outing is needed for the class.
   *
   * @return {string}
   */
  function homeCard() {
    var w = state.world;
    var c = w.character;
    if (!c.creature) {
      return '';
    }
    var name = creatureName(c);
    var traits = {};
    try {
      traits = JSON.parse(c.creature) || {};
    } catch (e) {
      traits = {};
    }
    var nest = T.artUrl(['stage.nest']);
    var front = T.artUrl(['stage.nest.front']);
    var things = [];
    if (traits.coat && traits.coat !== 'none') {
      things.push(t('app_home_coat', t('app_coat_' + traits.coat)));
    }
    if (traits.paint) {
      things.push(t('app_home_paint', t('app_market_' + traits.paint)));
    }
    var items = (w.items || []).filter(function(i) {
      return !i.borrowed;
    });
    var golden = ((w.album && w.album.cards) || []).filter(function(x) {
      return x.golden;
    });
    return '<section class="card no-print creature-home" style="margin-top:16px"><h2>' + esc(t('app_home_title', name)) +
      '</h2><div class="home-nest' + (nest ? ' art' : '') + '">' + (nest ? '<img class="home-back" src="' + esc(nest) +
      '" alt="">' : '') + '<span class="home-creature pet-zzz">' + T.creature(c.creature, c.creaturestage) +
      '<i>z</i><i>z</i></span>' + (front ? '<img class="home-front" src="' + esc(front) + '" alt="">' : '') + '</div>' +
      '<p class="help-soft">' + esc(t('app_home_sleeps', name)) + (things.length ? ' ' + esc(things.join(' · ')) : '') +
      '</p>' + (items.length ? '<div class="home-shelf" aria-label="' + esc(t('app_items')) + '">' + items.map(function(i) {
        var art = T.artUrl(['item.' + i.code]);
        return '<span class="home-thing" title="' + esc(i.name) + '">' + (art ? '<img src="' + esc(art) + '" alt="">' :
          T.icon('gems')) + '<small>' + esc(i.name) + '</small></span>';
      }).join('') + '</div>' : '') + (golden.length ? '<p class="help-soft">' + esc(t('app_home_golden', golden.map(function(x) {
        return x.name;
      }).join(', '))) + '</p>' : '') + outingCard(name) + '</section>';
  }

  /**
   * A short outing with the creature, for whoever wants it at home: what is left today of its page, the being of the
   * day and the stain of tonight, each with its button; done, a tick. Breath and gems, never knowledge.
   *
   * @param {string} name Of the creature.
   * @return {string}
   */
  function outingCard(name) {
    var steps = outingSteps();
    if (!steps.length) {
      return '';
    }
    var open = steps.filter(function(s) {
      return !s.done;
    });
    var left = open.length;
    return '<div class="outing"><h3>' + esc(t('app_outing_title', name)) + '</h3><p class="help-soft">' +
      esc(t(left ? 'app_outing_help' : 'app_outing_done', name)) + '</p><ul class="list">' + steps.map(function(s, i) {
        return '<li class="item' + (s.done ? ' done' : '') + '"><span class="mini-icon">' + T.icon(s.done ? 'lantern' : s.icon) +
          '</span><span class="grow"><b>' + esc(s.label) + '</b>' + (s.where ? '<small>' + esc(s.where) + '</small>' : '') +
          '</span>' + (s.done ? '<span class="tag done">' + esc(t('app_outing_tick')) + '</span>' : '<button type="button" ' +
          'class="btn ' + (s === open[0] ? 'btn-main' : 'btn-soft') + '" data-outing="' + i + '">' + esc(t('app_open')) + '</button>') + '</li>';
      }).join('') + '</ul></div>';
  }

  /**
   * The steps of the outing today: the page of the creature, the being of the day (in the first place with one) and the
   * stain of tonight, those there are.
   *
   * @return {Object[]} label, where, icon, done and go (what opens it).
   */
  function outingSteps() {
    var w = state.world;
    var c = w.character;
    var name = creatureName(c);
    var steps = [];
    if (w.page) {
      steps.push({label: t('app_outing_feed', name), icon: 'energy', done: !!w.page.answered, go: feedMoment});
    }
    var chapters = (w.mission && w.mission.chapters) || [];
    var being = chapters.filter(function(ch) {
      return ch.encounter;
    })[0];
    if (being) {
      steps.push({label: t('app_outing_walk'), where: being.name, icon: 'trail', done: false, go: function() {
        meet(being);
      }});
    }
    var stain = (w.stains || []).filter(function(s) {
      return s.kind !== 'page';
    })[0];
    if (stain) {
      steps.push({label: t('app_outing_stain'), where: stain.place, icon: 'lens', done: false, go: function() {
        stainMoment(stain);
      }});
    }
    return steps;
  }

  /**
   * The parts of «Me» besides the sheet, as big buttons in two rows; the one open, under them, with the way back.
   *
   * @return {string}
   */
  function meDoors() {
    var w = state.world;
    var c = w.character;
    var coat = w.hascoat && c.creature ? '<section class="card no-print" style="margin-top:16px"><h2>' +
      esc(t('app_coat_title', creatureName(c))) + '</h2><p class="help-soft">' + esc(t('app_coat_help')) + '</p>' +
      T.coatPicker(w, function() {}).html + '</section>' : '';
    var map = w.mission && w.mission.chapters.length;
    var parts = [
      // The look opens its builder: it is not a part under the buttons.
      ['look', 'me', lookChanges(c, c.stage) ? 'edit' : ''],
      ['home', 'game', homeCard()],
      ['comfort', 'sun', comfortCard()],
      ['names', 'log', namesCard()],
      ['coat', 'game', coat],
      ['ideas', 'idea', ideasCard()],
      // Without a map, the market and the album have no place of their own: they stay here.
      ['market', 'gems', map ? '' : marketCard()],
      ['album', 'log', map ? '' : albumCard()],
      ['museum', 'log', map ? '' : museumCard()]
    ].filter(function(p) {
      return p[2];
    });
    var open = parts.filter(function(p) {
      return p[0] === state.meSection;
    })[0];
    return '<div class="me-doors no-print">' + parts.map(function(p) {
      return '<button type="button" class="me-door" ' + (p[2] === 'edit' ? 'data-edit' : 'data-mesection="' + p[0] +
        '" aria-expanded="' + (open === p) + '"') + '>' + T.icon(p[1]) + '<span>' + esc(t('app_door_' + p[0])) + '</span></button>';
    }).join('') + '</div>' + (open ? open[2] : '');
  }

  /** The adaptations a student can turn on for themselves: they do not change the game. */
  var COMFORT = ['bigtext', 'readaloud', 'still', 'contrast', 'colours', 'quiet'];

  /**
   * How the student sees and hears the game better: what they turn on, and what their teacher marked, already on.
   *
   * @return {string}
   */
  function comfortCard() {
    var m = state.world.character.member;
    if (!m) {
      return '';
    }
    var teacher = m.adaptteacher || [];
    var own = m.adaptown || [];
    return '<section class="card no-print comfort-card" style="margin-top:16px"><h2>' + esc(t('app_comfort')) + '</h2>' +
      '<p class="help-soft">' + esc(t('app_comfort_help')) + '</p><div class="adapt-options">' + COMFORT.map(function(code) {
        var byteacher = teacher.indexOf(code) >= 0;
        return '<label class="chip"><input type="checkbox" data-comfort="' + code + '"' + (byteacher || own.indexOf(code) >= 0 ?
          ' checked' : '') + (byteacher ? ' disabled' : '') + '> <span><b>' + esc(t('teach_adapt_' + code)) + '</b><small>' +
          esc(byteacher ? t('app_comfort_teacher') : t('app_comfort_' + code)) + '</small></span></label>';
      }).join('') + '</div></section>';
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
   * The picture of a being: its own, from the art pack, or nothing (the card of its group would show other beings: an
   * ant with a snail and a ladybird).
   *
   * @param {string} id
   * @return {string|null}
   */
  function beingArt(id) {
    return T.artUrl(['being.' + id]);
  }

  /** How the creature takes a being of each group when it is recognised: it hops, steps back or wiggles. */
  var REACT = {insecto: 'hop', anfibio: 'hop', ave: 'hop', aracnido: 'back', reptil: 'back', mamifero: 'wiggle',
    pez: 'wiggle', molusco: 'wiggle', gusano: 'wiggle', planta: 'wiggle', hongo: 'back'};

  /**
   * The being of the day of a place: walking the place, where it can be walked here; as a list, if not.
   *
   * @param {Object} ch The place, with its encounter.
   */
  function meet(ch) {
    if (!T.walk || !T.walk.can(ch)) {
      encounterMoment(ch.encounter, 0, ch);
      return;
    }
    T.walk.encounter(state.world, ch, function(e, opened) {
      encounterMoment(e, opened, ch);
    }, refresh).catch(function() {
      // Without the engine, as a list.
      encounterMoment(ch.encounter, 0, ch);
    });
  }

  /**
   * A being peeks out, as a list: the creature sniffs it and says its group; it is recognised with its clues, opened
   * one by one, choosing its name among four. With one clue and no miss, a golden card. Who chose the list on this
   * device can go back to walking the place.
   *
   * @param {Object} e place, clues, group, options, picture, crossed.
   * @param {number} opened Clues opened already, walking.
   * @param {Object} ch The place.
   */
  function encounterMoment(e, opened, ch) {
    var c = state.world.character;
    var layer = document.createElement('div');
    layer.className = 'badge-moment encounter-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-encounter-title');
    // The names missed today are crossed out, and each miss opened one more clue.
    var crossed = e.crossed || [];
    var shown = Math.min(Math.max(1 + crossed.length, opened || 0), e.clues.length);
    // The creature by its name, or «Your creature» while it has none.
    var petName = creatureName(c) || t('app_stage_creature');
    var being = e.picture.replace(/^being\./, '');
    var art = beingArt(being);
    var skin = state.world.mission && state.world.mission.skin;
    // Each clue can be heard, said by the hidden being, if the pack brings its voice (that of the skin, if it has one).
    var hear = function(i) {
      var key = 'voice.encounter.' + being + '.' + (i + 1) + (skin && skin !== 'valley' ? '.' + skin : '');
      return T.fx && T.artUrl([key]) ? key : null;
    };
    var draw = function() {
      layer.innerHTML = '<div class="badge-card"><div class="kicker" id="tesape-encounter-title">' +
        esc(t('app_encounter_title')) + '</div>' + (art ? '<img class="encounter-pic hidden-one" src="' + esc(art) + '" alt="">' :
        '<span class="encounter-pic shadow" aria-hidden="true">?</span>') + '<p class="encounter-sniff">' +
        (c && c.creature ? '<span class="encounter-pet" aria-hidden="true">' + T.creature(c.creature, c.creaturestage) +
        '</span>' : '') + esc(t('app_encounter_sniff', {creature: petName, group: t('app_group_' + e.group)})) + '</p>' +
        '<ol class="encounter-clues">' + e.clues.slice(0, shown).map(function(x, i) {
          return '<li>«' + esc(x) + '»' + (hear(i) ? ' <button type="button" class="btn btn-soft icon-btn hear-btn" data-hear="' +
            i + '" aria-label="' + esc(t('app_encounter_hear', i + 1)) + '">' + T.icon('sound') + '</button>' : '') + '</li>';
        }).join('') + '</ol>' + (shown < e.clues.length ? '<div class="actions" style="justify-content:flex-start">' +
        '<button type="button" class="btn btn-soft" data-clue>' + esc(t('app_encounter_clue')) + '</button></div>' : '') +
        '<p><b>' + esc(t('app_encounter_who')) + '</b></p><div class="encounter-options">' + e.options.map(function(o) {
          if (crossed.indexOf(o.id) >= 0) {
            return '<button type="button" class="btn btn-soft crossed" disabled><s>' + esc(o.name) + '</s></button>';
          }
          return '<button type="button" class="btn btn-soft" data-who="' + esc(o.id) + '">' + esc(o.name) + '</button>';
        }).join('') + '</div><div data-said>' + (crossed.length ? '<p class="help-soft">' + esc(t('app_encounter_again',
          petName)) + '</p>' : '') + '</div>' + (ch && T.stage && T.stage.asList() && T.moves() ? '<div class="actions" ' +
          'style="justify-content:flex-start"><button type="button" class="btn btn-soft" data-walkagain>' +
          esc(t('app_stage_walk')) + '</button></div>' : '') + '</div>';
      var again = layer.querySelector('[data-walkagain]');
      if (again) {
        again.onclick = function() {
          T.stage.setList(false);
          layer.remove();
          meet(ch);
        };
      }
      layer.querySelectorAll('[data-hear]').forEach(function(b) {
        b.onclick = function() {
          T.fx.voice(hear(Number(b.dataset.hear)));
        };
      });
      var clue = layer.querySelector('[data-clue]');
      if (clue) {
        clue.onclick = function() {
          shown++;
          draw();
          layer.querySelector('[data-who]').focus();
        };
      }
      layer.querySelectorAll('[data-who]').forEach(function(b) {
        b.onclick = function() {
          layer.querySelectorAll('[data-who], [data-clue]').forEach(function(x) {
            x.disabled = true;
          });
          T.api('answer_encounter', {courseid: cfg.courseid, place: e.place, choice: b.dataset.who, clues: shown})
          .then(function(r) {
            if (r.again) {
              // Not that one: it flees to another bush; another clue, and they look for it again.
              crossed = r.crossed;
              e.crossed = crossed;
              shown = Math.min(shown + 1, e.clues.length);
              draw();
              layer.querySelector('[data-who]').focus();
              return;
            }
            var said = layer.querySelector('[data-said]');
            var pet = layer.querySelector('.encounter-pet');
            var pose = r.right ? (REACT[r.group] || 'wiggle') : 'look';
            if (pet) {
              pet.classList.add('react-' + pose);
            }
            var react = c && c.creature ? '<p class="encounter-react">' + esc(t(r.right ? 'app_encounter_react_' +
              (REACT[r.group] ? r.group : 'other') : 'app_encounter_react_hid', petName)) + '</p>' : '';
            if (r.right) {
              b.classList.add('chosen');
              layer.classList.add('known');
              if (T.fx) {
                T.fx.sound('item');
              }
              var name = r.name.charAt(0).toUpperCase() + r.name.slice(1);
              said.innerHTML = '<div class="conseq good"><span class="what">' + esc(t('app_encounter_right', name)) + '</span>' +
                (r.golden ? esc(t('app_encounter_golden')) + '<br>' : '') + esc(t(r.gems === 1 ? 'app_encounter_gem' :
                'app_encounter_gems', r.gems)) + '</div>' +
                '<p class="encounter-fact">' + esc(r.fact) + '</p>' + react;
            } else {
              said.innerHTML = '<p class="turn-outcome fell">' + esc(t('app_encounter_hid')) + '</p>' + react;
            }
            said.innerHTML += '<div class="actions"><button type="button" class="btn btn-main" data-close>' +
              esc(t('app_embrollo_go')) + '</button></div>';
            var close = said.querySelector('[data-close]');
            close.onclick = function() {
              if (T.fx) {
                T.fx.hush();
              }
              layer.remove();
              refresh();
            };
            close.focus();
          }).catch(function(err) {
            T.toast(esc(err.message), true);
            layer.remove();
            refresh();
          });
        };
      });
    };
    document.body.appendChild(layer);
    draw();
    layer.querySelector('button').focus();
  }

  /**
   * The beings met, place by place: the cards of those registered (golden with one clue) and, for those still missing,
   * gaps; how many are missing in each place.
   *
   * @return {string}
   */
  function guideCard() {
    var guide = (state.world.mission && state.world.mission.guide) || [];
    if (!guide.length) {
      return '';
    }
    return '<section class="card no-print album-card" style="margin-top:16px"><h2>' + esc(t('app_guide_beings')) + '</h2>' +
      guide.map(function(g) {
        var missing = g.total - g.beings.length;
        return '<h3>' + esc(g.name) + ' <small>' + esc(missing ? t('app_guide_missing', missing) : t('app_guide_full')) +
          '</small></h3><div class="album">' + g.beings.map(function(b) {
            var art = beingArt(b.id);
            return '<div class="album-card' + (b.golden ? ' golden' : '') + '">' + (art ? '<img src="' + esc(art) + '" alt="">' :
              '') + '<b>' + esc(b.name) + '</b><small>' + esc(t('app_groupname_' + b.group)) + '</small><small>' +
              esc(b.fact) + '</small></div>';
          }).join('') + new Array(missing + 1).join('<div class="album-card gap" aria-hidden="true"><b>?</b></div>') + '</div>';
      }).join('') + '</section>';
  }

  /**
   * The album of the field guide: a card for each entry of the student or their team, golden with its food chain.
   *
   * @return {string}
   */
  /**
   * A minion of the Embrollo, small: a tangle of threads, or a straight thread with a bow once untangled.
   *
   * @param {boolean} untangled
   * @return {string} SVG.
   */
  function minionMark(untangled) {
    if (untangled) {
      return '<svg class="minion-mark untangled" viewBox="0 0 40 40" aria-hidden="true"><path d="M4 30 C14 30 18 10 36 10" ' +
        'fill="none" stroke="#f2c14e" stroke-width="3" stroke-linecap="round"/><path d="M20 20 l-6 -6 m6 6 l6 -7 m-6 7 ' +
        'l-5 8 m5 -8 l7 6" stroke="#f2c14e" stroke-width="2.4" stroke-linecap="round"/></svg>';
    }
    var loops = '';
    for (var i = 0; i < 4; i++) {
      loops += '<ellipse cx="20" cy="20" rx="' + (15 - i * 2) + '" ry="' + (7 + i * 2) + '" transform="rotate(' + (i * 47) +
        ' 20 20)" fill="none" stroke="' + (i % 2 ? '#8e7cc3' : '#b9a6ff') + '" stroke-width="2.4"/>';
    }
    return '<svg class="minion-mark" viewBox="0 0 40 40" aria-hidden="true">' + loops + '<circle cx="16" cy="18" r="2" ' +
      'fill="#fff"/><circle cx="24" cy="18" r="2" fill="#fff"/></svg>';
  }

  /**
   * The bestiary of the Embrollo: its minions in the places the student has played, and those they have untangled with
   * the page of the day, with the idea that undoes each one.
   *
   * @return {string}
   */
  function bestiaryCard() {
    var list = state.world.bestiary || [];
    if (!list.length && !state.world.mission) {
      return '';
    }
    var beaten = list.filter(function(m) {
      return m.untangled;
    }).length;
    return '<section class="card no-print bestiary-card" style="margin-top:16px"><h2>' + esc(t('app_bestiary')) + '</h2>' +
      '<p class="help-soft">' + esc(t('app_bestiary_help', {untangled: beaten, all: list.length})) + '</p>' + (list.length ?
      '<ul class="list">' + list.map(function(m) {
        // Its picture when the art pack brings it; meanwhile, a small tangle drawn here.
        var pic = m.code ? T.artUrl(['minion.' + m.code]) : null;
        return '<li class="item minion' + (m.untangled ? ' untangled' : '') + '">' + (pic ? '<img class="minion-art" src="' +
          esc(pic) + '" alt="">' : minionMark(m.untangled)) +
          '<span class="grow"><b>' + esc(m.name) + '</b><span class="tag' + (m.untangled ? ' done' : ' todo') + '">' +
          esc(t(m.untangled ? 'app_bestiary_untangled' : 'app_bestiary_standing')) + '</span><small>«' + esc(m.claim) +
          '»</small>' + (m.untangled ? '<small class="minion-why">' + esc(t('app_bestiary_weakness', m.weakness)) +
          '</small>' : '') + '</span></li>';
      }).join('') + '</ul>' : '<p class="empty">' + esc(t('app_bestiary_empty')) + '</p>') + '</section>';
  }

  /**
   * The secrets of the places the student found, in the album: what each one says; and how many there are, without
   * saying where.
   *
   * @return {string}
   */
  function secretsCard() {
    var x = state.world.mission && state.world.mission.secrets;
    if (!x || !x.total) {
      return '';
    }
    return '<section class="card no-print secrets-card" style="margin-top:16px"><h2>' + esc(t('app_secrets')) + '</h2>' +
      '<p class="help-soft">' + esc(t('app_secrets_help', {found: x.found.length, all: x.total})) + '</p>' + (x.found.length ?
      '<ul class="list">' + x.found.map(function(f) {
        return '<li class="item secret-found ' + esc(f.kind) + '"><span class="secret-icon" aria-hidden="true">' +
          (f.kind === 'print' ? INKPRINT : '✦') + '</span><span class="grow">' + esc(f.text) + '</span></li>';
      }).join('') + '</ul>' : '') + '</section>';
  }

  function albumCard() {
    var a = state.world.album;
    if (!a) {
      return guideCard() + bestiaryCard() + secretsCard();
    }
    var golden = a.cards.filter(function(x) {
      return x.golden;
    }).length;
    return '<section class="card no-print album-card" style="margin-top:16px"><h2>' + esc(t('app_album')) + '</h2>' +
      '<p class="help-soft">' + esc(t('app_album_help', {cards: a.cards.length, golden: golden, class: a['class']})) + '</p>' +
      (a.cards.length ? '<div class="album">' + a.cards.map(function(x) {
        // The picture of its group, from the art pack.
        var art = T.groupArt(x.group);
        return '<div class="album-card' + (x.golden ? ' golden' : '') + '">' + (art ? '<img src="' + esc(art) + '" alt="">' : '') +
          '<b>' + esc(x.name) + '</b>' + (x.group ?
          '<small>' + esc(x.group) + '</small>' : '') + (x.mine ? '<small class="mine">' + esc(t('app_album_mine')) + '</small>' :
          '') + '</div>';
      }).join('') + '</div>' : '<p class="empty">' + esc(t('app_album_empty')) + '</p>') + '<div class="actions" style="' +
      'justify-content:flex-start"><button class="btn btn-soft" data-activity="' + a.cmid + '">' + esc(t('app_album_open')) +
      '</button></div></section>' + guideCard() + bestiaryCard() + secretsCard();
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

  /**
   * Caring for the creature, with nothing to win: the card it remembers from some days ago, and a card of the field
   * guide to show it, once a day.
   *
   * @param {Object} c The character.
   * @return {string}
   */
  function petCare(c) {
    var care = state.world.petcare;
    if (!care) {
      return '';
    }
    var beings = petBeings();
    return (care.remembers ? '<p class="pet-remembers">' + esc(t('app_pet_remembers', {creature: creatureName(c),
      being: ofName(care.remembers)})) + '</p>' : '') + (beings.length ? (care.shown ? '<small class="pet-shown">' +
      esc(t('app_pet_shown_today', creatureName(c))) + '</small>' : '<button type="button" class="btn btn-soft pet-show" ' +
      'data-petshow>' + esc(t('app_pet_show', creatureName(c))) + '</button>') : '');
  }

  /**
   * The beings of the field guide of the student, to show one to the creature.
   *
   * @return {Object[]} id, name.
   */
  function petBeings() {
    var out = [];
    ((state.world.mission && state.world.mission.guide) || []).forEach(function(g) {
      g.beings.forEach(function(b) {
        out.push(b);
      });
    });
    return out;
  }

  /**
   * «de» before a name with its article: «el búho» becomes «del búho».
   *
   * @param {string} name
   * @return {string}
   */
  function ofName(name) {
    return /^el /i.test(name) ? 'del ' + name.slice(3) : 'de ' + name;
  }

  /**
   * The cards of the field guide to show the creature: a touch on one, and it looks at it.
   *
   * @param {Object} c The character.
   */
  function showCardMoment(c) {
    var layer = document.createElement('div');
    layer.className = 'badge-moment pet-show-moment';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-petshow-title');
    layer.innerHTML = '<div class="badge-card"><h2 id="tesape-petshow-title">' + esc(t('app_pet_show', creatureName(c))) +
      '</h2><div class="album">' + petBeings().map(function(b) {
        var art = beingArt(b.id);
        return '<button type="button" class="album-card" data-petcard="' + esc(b.id) + '">' + (art ? '<img src="' +
          esc(art) + '" alt="">' : '') + '<b>' + esc(b.name) + '</b></button>';
      }).join('') + '</div><div class="actions"><button type="button" class="btn btn-soft" data-close>' + esc(t('app_close')) +
      '</button></div></div>';
    document.body.appendChild(layer);
    layer.querySelector('[data-close]').onclick = function() {
      layer.remove();
    };
    layer.querySelectorAll('[data-petcard]').forEach(function(b) {
      b.onclick = function() {
        var being = petBeings().filter(function(x) {
          return x.id === b.dataset.petcard;
        })[0];
        T.api('show_card', {courseid: cfg.courseid, being: b.dataset.petcard}).then(function() {
          layer.remove();
          state.world.petcare.shown = true;
          if (T.fx) {
            T.fx.sound('heart');
            T.fx.hearts();
          }
          T.toast(esc(t('app_pet_shown', {creature: creatureName(c), being: ofName(being ? being.name : '')})));
          world();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    });
    layer.querySelector('button').focus();
  }

  function bindMe() {
    var c = state.world.character;
    // A pat for the creature: it is happy, and that is all.
    var pet = app.querySelector('[data-petme]');
    if (pet) {
      pet.onclick = function() {
        pet.classList.remove('patted');
        void pet.offsetWidth;
        pet.classList.add('patted');
        if (T.fx) {
          T.fx.sound('heart');
          T.fx.hearts();
        }
        T.toast(esc(t('app_pet_happy_' + (1 + Math.floor(Math.random() * 3)), creatureName(c))));
      };
    }
    var showcard = app.querySelector('[data-petshow]');
    if (showcard) {
      showcard.onclick = function() {
        showCardMoment(c);
      };
    }
    // The outing: each step opens what it is.
    app.querySelectorAll('[data-outing]').forEach(function(b) {
      b.onclick = function() {
        var step = outingSteps()[Number(b.dataset.outing)];
        if (step) {
          step.go();
        }
      };
    });
    var museum = app.querySelector('.museum-card');
    if (museum && state.world.museum) {
      T.bindMuseum(museum, state.world.museum, state.book, function(book) {
        state.book = book;
      });
    }
    app.querySelectorAll('[data-mesection]').forEach(function(b) {
      b.onclick = function() {
        state.meSection = state.meSection === b.dataset.mesection ? null : b.dataset.mesection;
        world();
        var part = app.querySelector('.me-doors + section');
        if (part) {
          part.scrollIntoView({behavior: T.moves() ? 'smooth' : 'auto', block: 'start'});
        }
      };
    });
    app.querySelectorAll('[data-comfort]').forEach(function(box) {
      box.onchange = function() {
        var list = Array.prototype.map.call(app.querySelectorAll('[data-comfort]:checked:not([disabled])'), function(b) {
          return b.dataset.comfort;
        });
        app.querySelectorAll('[data-comfort]').forEach(function(b) {
          b.disabled = true;
        });
        T.api('set_comfort', {courseid: cfg.courseid, adapt: list}).then(function(w) {
          state.world = w;
          world();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          world();
        });
      };
    });
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
      var choose = app.querySelector('[data-choosetalent]');
      if (choose && c.talentpending) {
        choose.onclick = function() {
          talentMoment(c.talentpending);
        };
      }
      var way = app.querySelector('[data-choosebranch]');
      if (way && c.branchpending) {
        way.onclick = function() {
          branchMoment();
        };
      }
      var rite = app.querySelector('[data-ascentopen]');
      if (rite && c.ascentpending) {
        rite.onclick = function() {
          ascentMoment();
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
