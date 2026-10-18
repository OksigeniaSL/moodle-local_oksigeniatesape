/**
 * Tesapé: the workshop where the teacher makes a mission from scratch, step by step: the mission, what is learnt, the
 * places of the map, the guided scenes of each place and the questions of the battle; then the checker, and making it
 * the mission of the course. What the teacher writes is saved by itself a moment after they stop.
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
  var STEPS = ['mission', 'objectives', 'places', 'scenes', 'questions', 'publish'];
  var st = {el: null, done: null, info: null, d: null, step: 'mission', scene: null, timer: 0};

  function api(action, extra) {
    return T.api('author_mission', Object.assign({courseid: cfg.courseid, action: action}, extra || {})).then(function(r) {
      return JSON.parse(r.json);
    });
  }

  function uid(prefix) {
    return prefix + Date.now().toString(36) + Math.floor(Math.random() * 1000);
  }

  /* ---------- Fields, bound to a path of the draft ---------- */

  function get(path) {
    return path.split('.').reduce(function(o, k) {
      return o === undefined || o === null ? undefined : o[k];
    }, st.d);
  }

  function set(path, value) {
    var keys = path.split('.');
    var last = keys.pop();
    var o = keys.reduce(function(acc, k) {
      return acc[k];
    }, st.d);
    o[last] = value;
  }

  function input(path, label, opts) {
    opts = opts || {};
    var value = get(path);
    var field;
    if (opts.lines) {
      field = '<textarea rows="' + (opts.rows || 3) + '" data-k="' + esc(path) + '" data-lines>' + esc((value || []).join('\n')) +
        '</textarea>';
    } else if (opts.area) {
      field = '<textarea rows="' + (opts.rows || 3) + '" data-k="' + esc(path) + '">' + esc(value || '') + '</textarea>';
    } else {
      field = '<input type="text" data-k="' + esc(path) + '" value="' + esc(value || '') + '">';
    }
    return '<label class="ws-field"><span>' + esc(label) + '</span>' + field + (opts.help ? '<small>' + esc(opts.help) +
      '</small>' : '') + '</label>';
  }

  function select(path, label, options, empty) {
    var value = String(get(path) === undefined ? '' : get(path));
    return '<label class="ws-field"><span>' + esc(label) + '</span><select data-k="' + esc(path) + '">' +
      (empty ? '<option value="">' + esc(empty) + '</option>' : '') + options.map(function(o) {
        return '<option value="' + esc(o.value) + '"' + (String(o.value) === value ? ' selected' : '') + '>' + esc(o.label) +
          '</option>';
      }).join('') + '</select></label>';
  }

  function check(path, label) {
    return '<label class="ws-check"><input type="checkbox" data-k="' + esc(path) + '"' + (get(path) ? ' checked' : '') +
      '> ' + esc(label) + '</label>';
  }

  function btn(act, label, extra, main) {
    return '<button type="button" class="btn ' + (main ? 'btn-main' : 'btn-soft') + '" data-act="' + act + '"' +
      (extra || '') + '>' + esc(label) + '</button>';
  }

  function objectives() {
    return st.d.objectives.map(function(o, i) {
      return {value: o.code, label: (o.name || t('teach_au_objective_n', i + 1))};
    });
  }

  function places() {
    return st.d.places.map(function(p, i) {
      return {value: p.code, label: (i + 1) + '. ' + (p.name || t('teach_au_place_n', i + 1))};
    });
  }

  /* ---------- The steps ---------- */

  function stepMission() {
    return '<section class="card">' + input('title', t('teach_au_title')) + input('subtitle', t('teach_au_subtitle')) +
      input('ages', t('teach_au_ages')) + input('prologue', t('teach_au_prologue'), {area: true, rows: 4,
        help: t('teach_au_prologue_help')}) + input('ending', t('teach_au_ending'), {area: true, rows: 3,
        help: t('teach_au_ending_help')}) + '</section>';
  }

  function stepObjectives() {
    var max = st.info.limits.maxobjectives;
    return '<p class="help">' + esc(t('teach_au_objectives_help')) + '</p>' + st.d.objectives.map(function(o, i) {
      var p = 'objectives.' + i;
      return '<section class="card ws-chapter"><div class="kicker">' + esc(t('teach_au_objective_n', i + 1)) + '</div>' +
        input(p + '.name', t('teach_au_objective_name')) + input(p + '.description', t('teach_au_objective_desc'),
          {area: true, rows: 2}) + '<details><summary>' + esc(t('teach_au_levels')) + '</summary>' +
        [1, 2, 3, 4].map(function(level, n) {
          return input(p + '.levels.' + n, t('teach_au_level_' + level), {area: true, rows: 2});
        }).join('') + '</details><div class="actions" style="justify-content:flex-end">' +
        btn('del-objective', t('teach_au_delete'), ' data-i="' + i + '"') + '</div></section>';
    }).join('') + (st.d.objectives.length < max ? '<div class="actions" style="justify-content:flex-start">' +
      btn('add-objective', t('teach_au_add_objective')) + '</div>' : '');
  }

  function stepPlaces() {
    var l = st.info.limits;
    return '<p class="help">' + esc(t('teach_au_places_help', {min: l.minplaces, max: l.maxplaces})) + '</p>' +
      st.d.places.map(function(pl, i) {
        var p = 'places.' + i;
        return '<section class="card ws-chapter"><div class="kicker">' + esc(t('teach_au_place_n', i + 1)) + '</div>' +
          input(p + '.name', t('teach_au_place_name')) + input(p + '.intro', t('teach_ws_k_intro'), {area: true, rows: 2}) +
          input(p + '.offscreen', t('teach_ws_k_offscreen'), {help: t('teach_au_offscreen_help')}) +
          input(p + '.pacts', t('teach_au_pacts'), {lines: true, rows: 3, help: t('teach_au_lines_help')}) +
          (pl.sessions || []).map(function(s, n) {
            return input(p + '.sessions.' + n + '.do', t('teach_ws_k_session', n + 1), {area: true, rows: 2}) +
              input(p + '.sessions.' + n + '.materials', t('teach_ws_k_materials', n + 1));
          }).join('') + '<div class="actions" style="justify-content:flex-end">' +
          btn('add-session', t('teach_au_add_session'), ' data-i="' + i + '"') +
          (i > 0 ? btn('up-place', '↑', ' data-i="' + i + '" aria-label="' + esc(t('teach_au_up')) + '"') : '') +
          btn('del-place', t('teach_au_delete'), ' data-i="' + i + '"') + '</div></section>';
      }).join('') + (st.d.places.length < l.maxplaces ? '<div class="actions" style="justify-content:flex-start">' +
      btn('add-place', t('teach_au_add_place')) + '</div>' : '');
  }

  function stepScenes() {
    if (st.scene !== null) {
      return sceneEditor();
    }
    if (!st.d.places.length) {
      return '<p class="help">' + esc(t('teach_au_scenes_noplaces')) + '</p>';
    }
    return '<p class="help">' + esc(t('teach_au_scenes_help')) + '</p>' + st.d.places.map(function(pl, i) {
      var mine = [];
      st.d.scenes.forEach(function(sc, n) {
        if (sc.place === pl.code) {
          mine.push(n);
        }
      });
      return '<section class="card ws-chapter"><h3>' + esc((i + 1) + '. ' + (pl.name || t('teach_au_place_n', i + 1))) +
        '</h3>' + (mine.length ? '<ul class="list">' + mine.map(function(n) {
          var sc = st.d.scenes[n];
          return '<li class="item"><span class="grow"><b>' + esc(sc.title || t('teach_au_untitled')) + '</b><small>' +
            esc(t('teach_au_moments_n', (sc.moments || []).length)) + '</small></span>' +
            btn('edit-scene', t('teach_au_edit'), ' data-n="' + n + '"', true) +
            btn('del-scene', t('teach_au_delete'), ' data-n="' + n + '"') + '</li>';
        }).join('') + '</ul>' : '<p class="help">' + esc(t('teach_au_place_empty')) + '</p>') +
        '<div class="actions" style="justify-content:flex-start">' + btn('add-scene', t('teach_au_add_scene'),
          ' data-place="' + esc(pl.code) + '"') + '</div></section>';
    }).join('');
  }

  function momentEditor(m, i) {
    var p = 'scenes.' + st.scene + '.moments.' + i;
    var types = ['one', 'many', 'classify', 'order', 'decision'].map(function(type) {
      return {value: type, label: t('teach_au_type_' + type)};
    });
    var body = '';
    if (m.type === 'decision') {
      // Two or three ways, none of them right: each one tells what happens next.
      body = '<p class="help">' + esc(t('teach_au_decision_help')) + '</p>' + (m.options || []).map(function(o, n) {
        var q = p + '.options.' + n;
        return '<div class="ws-question">' + input(q + '.label', t('teach_au_way')) + input(q + '.feedback',
          t('teach_au_consequence'), {area: true, rows: 2}) + '<div class="actions" style="justify-content:flex-end">' +
          btn('del-option', t('teach_au_delete'), ' data-m="' + i + '" data-n="' + n + '"') + '</div></div>';
      }).join('') + ((m.options || []).length < 3 ? btn('add-option', t('teach_au_add_way'), ' data-m="' + i + '"') : '');
    } else if (m.type === 'one' || m.type === 'many') {
      body = (m.options || []).map(function(o, n) {
        var q = p + '.options.' + n;
        return '<div class="ws-question">' + input(q + '.label', t('teach_ws_k_option')) +
          check(q + '.correct', t('teach_au_correct')) + input(q + '.feedback', t('teach_ws_k_feedback_option')) +
          '<div class="actions" style="justify-content:flex-end">' + btn('del-option', t('teach_au_delete'),
            ' data-m="' + i + '" data-n="' + n + '"') + '</div></div>';
      }).join('') + ((m.options || []).length < 6 ? btn('add-option', t('teach_au_add_option'), ' data-m="' + i + '"') : '');
    } else if (m.type === 'classify') {
      var bins = (m.bins || []).map(function(b, n) {
        return {value: n + 1, label: b.label || t('teach_au_box_n', n + 1)};
      });
      body = (m.bins || []).map(function(b, n) {
        return '<div class="grid2" style="gap:10px;align-items:end">' + input(p + '.bins.' + n + '.label',
          t('teach_au_box_n', n + 1)) + '<div>' + btn('del-bin', t('teach_au_delete'), ' data-m="' + i + '" data-n="' + n +
          '"') + '</div></div>';
      }).join('') + ((m.bins || []).length < 4 ? btn('add-bin', t('teach_au_add_box'), ' data-m="' + i + '"') : '') +
        (m.items || []).map(function(it, n) {
          var q = p + '.items.' + n;
          return '<div class="ws-question">' + input(q + '.label', t('teach_ws_k_card')) +
            select(q + '.bin', t('teach_au_its_box'), bins, t('teach_au_choose')) + input(q + '.ok', t('teach_au_ok')) +
            input(q + '.feedback', t('teach_au_wrong')) + '<div class="actions" style="justify-content:flex-end">' +
            btn('del-item', t('teach_au_delete'), ' data-m="' + i + '" data-n="' + n + '"') + '</div></div>';
        }).join('') + ((m.items || []).length < 12 ? btn('add-item', t('teach_au_add_card'), ' data-m="' + i + '"') : '');
    } else {
      body = input(p + '.steps', t('teach_au_steps'), {lines: true, rows: 4, help: t('teach_au_steps_help')}) +
        input(p + '.stuck', t('teach_ws_k_stuck'));
    }
    return '<section class="card ws-chapter"><div class="kicker">' + esc(t('teach_ws_moment', i + 1)) + '</div>' +
      // A decision is not evidence of any objective: it has none to choose.
      '<div class="grid2" style="gap:10px">' + select(p + '.type', t('teach_au_type'), types) + (m.type === 'decision' ? '' :
        select(p + '.objective', t('teach_ws_q_objective'), objectives(), t('teach_ws_q_none'))) + '</div>' +
      (m.type === 'order' ? input(p + '.prompt', t('teach_ws_k_prompt')) : input(p + '.prompt', t('teach_ws_k_prompt'),
        {area: true, rows: 2})) + body + input(p + '.hints', t('teach_au_hints'), {lines: true, rows: 2,
        help: t('teach_au_lines_help')}) + '<div class="actions" style="justify-content:flex-end">' +
      btn('del-moment', t('teach_au_delete_moment'), ' data-m="' + i + '"') + '</div></section>';
  }

  /** A draft of the scene by the AI of the site, if the course has it: the teacher reads it and changes it. */
  function aiBox() {
    if (!st.info.ai || !st.info.ai.canuse) {
      return '';
    }
    return '<details class="card ws-chapter"><summary><h3>' + esc(t('teach_au_ai')) + '</h3></summary><p class="help">' +
      esc(t('teach_au_ai_help')) + '</p><label class="ws-field"><textarea rows="3" data-aiask placeholder="' +
      esc(t('teach_au_ai_placeholder')) + '"></textarea></label><div class="actions" style="justify-content:flex-start">' +
      btn('ai-propose', t('teach_au_ai_go'), '', true) + '</div></details>';
  }

  function sceneEditor() {
    var sc = st.d.scenes[st.scene];
    var p = 'scenes.' + st.scene;
    var speakers = st.info.speakers.map(function(s) {
      return {value: s.code, label: s.name};
    });
    return '<div class="actions" style="justify-content:flex-start">' + btn('back-scenes', t('teach_au_back_scenes')) +
      btn('try-scene', t('teach_au_try'), '', true) + '</div><section class="card">' +
      select(p + '.place', t('teach_au_its_place'), places()) +
      input(p + '.title', t('teach_ws_k_title')) + select(p + '.speaker', t('teach_au_speaker'), speakers,
        t('teach_au_narrator')) + input(p + '.text', t('teach_ws_k_story'), {area: true, rows: 4}) + '</section>' +
      aiBox() + (sc.moments || []).map(momentEditor).join('') + ((sc.moments || []).length < st.info.limits.maxmoments ?
        '<div class="actions" style="justify-content:flex-start">' + btn('add-moment', t('teach_au_add_moment')) + '</div>' :
        '') + '<details class="card ws-chapter"><summary><h3>' + esc(t('teach_au_endings')) + '</h3></summary>' +
      '<p class="help">' + esc(t('teach_au_endings_help')) + '</p>' +
      input(p + '.success', t('teach_au_success'), {area: true, rows: 2}) +
      input(p + '.partial', t('teach_au_partial'), {area: true, rows: 2}) +
      input(p + '.retry', t('teach_au_retry'), {area: true, rows: 2}) + '</details>';
  }

  function stepQuestions() {
    var min = st.info.limits.minquestions;
    return '<p class="help">' + esc(t('teach_au_questions_help', min)) + '</p>' + st.d.questions.map(function(q, i) {
      var p = 'questions.' + i;
      var options = (q.options || []).slice();
      while (options.length < 4) {
        options.push('');
      }
      q.options = options;
      return '<section class="card ws-question"><div class="grid2" style="gap:10px">' +
        select(p + '.place', t('teach_ws_q_chapter'), places()) +
        select(p + '.objective', t('teach_ws_q_objective'), objectives(), t('teach_ws_q_none')) + '</div>' +
        input(p + '.text', t('teach_ws_q_text'), {area: true, rows: 2}) + options.map(function(o, n) {
          return '<div class="ws-field' + (n ? '' : ' right') + '">' + input(p + '.options.' + n,
            t(n ? 'teach_ws_q_other' : 'teach_ws_q_right')) + '</div>';
        }).join('') + input(p + '.why', t('teach_ws_q_why'), {area: true, rows: 2}) +
        '<div class="actions" style="justify-content:flex-end">' + btn('del-question', t('teach_ws_q_delete'),
          ' data-i="' + i + '"') + '</div></section>';
    }).join('') + '<div class="actions" style="justify-content:flex-start">' + btn('add-question', t('teach_ws_q_add')) +
      '</div>';
  }

  function stepPublish() {
    var problems = st.info.problems || [];
    var advice = st.info.advice || [];
    // What would make it a game and not a test: it does not stop it.
    return (advice.length ? '<section class="card" style="margin-bottom:16px"><h3>' + esc(t('teach_au_advice')) + '</h3><ul>' +
      advice.map(function(a) {
        return '<li>' + esc(a) + '</li>';
      }).join('') + '</ul></section>' : '') + '<section class="card">' + (problems.length ? '<h3>' + esc(t('teach_au_missing')) + '</h3><ul>' +
      problems.map(function(p) {
        return '<li>' + esc(p) + '</li>';
      }).join('') + '</ul>' : '<h3>' + esc(t('teach_au_ready')) + '</h3><p class="help">' +
      esc(t(st.info.current ? 'teach_au_republish_help' : 'teach_au_publish_help')) + '</p>' +
      '<div class="actions" style="justify-content:flex-start">' + btn('publish', t(st.info.current ?
        'teach_au_republish' : 'teach_au_publish'), '', true) + '</div>') + '</section>';
  }

  /* ---------- The whole workshop ---------- */

  function render() {
    var nav = '<div class="pills ws-steps">' + STEPS.map(function(step, i) {
      return '<button type="button" class="chip' + (st.step === step ? ' on' : '') + '" data-step="' + step + '">' +
        (i + 1) + '. ' + esc(t('teach_au_step_' + step)) + '</button>';
    }).join('') + '</div>';
    var body = {mission: stepMission, objectives: stepObjectives, places: stepPlaces, scenes: stepScenes,
      questions: stepQuestions, publish: stepPublish}[st.step]();
    st.el.innerHTML = '<section class="card ws-head"><div class="kicker">' + esc(t('teach_au_kicker')) + '</div><h2>' +
      esc(st.d.title || t('teach_au_untitled')) + '</h2><div class="actions" style="justify-content:flex-start">' +
      btn('back', t('teach_ws_back')) + '<span class="help" data-saved></span></div>' + nav + '</section>' + body;
  }

  /** Saves the draft a moment after the teacher stops writing. */
  function later() {
    clearTimeout(st.timer);
    var saved = st.el.querySelector('[data-saved]');
    if (saved) {
      saved.textContent = '…';
    }
    st.timer = setTimeout(save, 800);
  }

  function save() {
    clearTimeout(st.timer);
    return api('save', {code: st.info.code, value: JSON.stringify(st.d)}).then(function(info) {
      // The draft of the screen stays as it is (with its empty rows); what is missing comes from the server.
      st.info.problems = info.problems;
      st.info.advice = info.advice;
      var saved = st.el.querySelector('[data-saved]');
      if (saved) {
        saved.textContent = t('app_saved');
      }
    }).catch(function(e) {
      T.toast(esc(e.message), true);
    });
  }

  var ACTS = {
    'add-objective': function() {
      st.d.objectives.push({code: uid('O'), name: '', description: '', levels: ['', '', '', '']});
    },
    'del-objective': function(b) {
      st.d.objectives.splice(+b.dataset.i, 1);
    },
    'add-place': function() {
      st.d.places.push({code: uid('l'), name: '', intro: '', offscreen: '', pacts: [], sessions: [{do: '', materials: ''}]});
    },
    'del-place': function(b) {
      var code = st.d.places[+b.dataset.i].code;
      st.d.places.splice(+b.dataset.i, 1);
      st.d.scenes = st.d.scenes.filter(function(sc) {
        return sc.place !== code;
      });
    },
    'up-place': function(b) {
      var i = +b.dataset.i;
      st.d.places.splice(i - 1, 0, st.d.places.splice(i, 1)[0]);
    },
    'add-session': function(b) {
      var pl = st.d.places[+b.dataset.i];
      pl.sessions = pl.sessions || [];
      pl.sessions.push({do: '', materials: ''});
    },
    'add-scene': function(b) {
      st.d.scenes.push({code: uid('s'), place: b.dataset.place, title: '', speaker: '', text: '', success: '',
        partial: '', retry: '', moments: [{type: 'one', prompt: '', objective: '', hints: [], options: [
          {label: '', correct: true, feedback: ''}, {label: '', correct: false, feedback: ''}]}]});
      st.scene = st.d.scenes.length - 1;
    },
    'edit-scene': function(b) {
      st.scene = +b.dataset.n;
    },
    'del-scene': function(b) {
      if (window.confirm(t('teach_au_delete_scene_confirm'))) {
        st.d.scenes.splice(+b.dataset.n, 1);
      }
    },
    'back-scenes': function() {
      st.scene = null;
    },
    'add-moment': function() {
      st.d.scenes[st.scene].moments.push({type: 'one', prompt: '', objective: '', hints: [], options: [
        {label: '', correct: true, feedback: ''}, {label: '', correct: false, feedback: ''}]});
    },
    'del-moment': function(b) {
      st.d.scenes[st.scene].moments.splice(+b.dataset.m, 1);
    },
    'add-option': function(b) {
      var m = st.d.scenes[st.scene].moments[+b.dataset.m];
      m.options = m.options || [];
      m.options.push({label: '', correct: false, feedback: ''});
    },
    'del-option': function(b) {
      st.d.scenes[st.scene].moments[+b.dataset.m].options.splice(+b.dataset.n, 1);
    },
    'add-bin': function(b) {
      var m = st.d.scenes[st.scene].moments[+b.dataset.m];
      m.bins = m.bins || [];
      m.bins.push({label: ''});
    },
    'del-bin': function(b) {
      st.d.scenes[st.scene].moments[+b.dataset.m].bins.splice(+b.dataset.n, 1);
    },
    'add-item': function(b) {
      var m = st.d.scenes[st.scene].moments[+b.dataset.m];
      m.items = m.items || [];
      m.items.push({label: '', bin: 0, ok: '', feedback: ''});
    },
    'del-item': function(b) {
      st.d.scenes[st.scene].moments[+b.dataset.m].items.splice(+b.dataset.n, 1);
    },
    'add-question': function() {
      st.d.questions.push({place: st.d.places.length ? st.d.places[0].code : '', objective: '', text: '',
        options: ['', '', '', ''], why: ''});
    },
    'del-question': function(b) {
      st.d.questions.splice(+b.dataset.i, 1);
    }
  };

  function bind() {
    st.el.addEventListener('input', function(ev) {
      var el = ev.target;
      if (!el.dataset || !el.dataset.k || el.tagName === 'SELECT' || el.type === 'checkbox') {
        return;
      }
      set(el.dataset.k, el.dataset.lines !== undefined ? el.value.split('\n') : el.value);
      later();
    });
    st.el.addEventListener('change', function(ev) {
      var el = ev.target;
      if (!el.dataset || !el.dataset.k) {
        return;
      }
      if (el.type === 'checkbox') {
        set(el.dataset.k, el.checked);
      } else if (el.tagName === 'SELECT') {
        set(el.dataset.k, /\.bin$/.test(el.dataset.k) ? +el.value : el.value);
        if (/\.type$/.test(el.dataset.k)) {
          // Another kind of moment: its fields change.
          render();
        }
      } else if (el.dataset.lines !== undefined) {
        // Lines, without the empty ones, once the teacher leaves the field.
        set(el.dataset.k, el.value.split('\n').map(function(l) {
          return l.trim();
        }).filter(function(l) {
          return l !== '';
        }));
      }
      later();
    });
    st.el.addEventListener('click', function(ev) {
      var step = ev.target.closest('[data-step]');
      if (step) {
        save().then(function() {
          st.step = step.dataset.step;
          st.scene = null;
          if (st.step === 'publish') {
            return api('get', {code: st.info.code}).then(function(info) {
              st.info = info;
            });
          }
          return null;
        }).then(render);
        return;
      }
      var b = ev.target.closest('[data-act]');
      if (!b) {
        return;
      }
      var act = b.dataset.act;
      if (act === 'back') {
        save().then(st.done);
      } else if (act === 'ai-propose') {
        var ask = st.el.querySelector('[data-aiask]').value.trim();
        var scene = st.d.scenes[st.scene];
        if (!ask) {
          return;
        }
        b.disabled = true;
        b.textContent = t('teach_au_ai_wait');
        save().then(function() {
          return api('propose', {code: st.info.code, value: scene.code + '\n' + ask});
        }).then(function(proposal) {
          // The proposal takes the place of the texts and moments; the scene keeps its place and who speaks.
          st.d.scenes[st.scene] = Object.assign({}, proposal, {code: scene.code, place: scene.place, speaker: scene.speaker});
          render();
          later();
          T.toast(esc(t('teach_au_ai_done')));
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          render();
        });
      } else if (act === 'try-scene') {
        var code = st.d.scenes[st.scene].code;
        save().then(function() {
          return api('preview', {code: st.info.code, value: code});
        }).then(function(scene) {
          // As a student would see it, with a made-up character; nothing is kept.
          T.playScene({preview: scene, world: {character: {role: '', creature: '{}', stage: 1}, items: [], team: null,
            family: 'storyheroes', nicknames: false}, onclose: function() {
            return null;
          }});
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      } else if (act === 'publish') {
        if (!window.confirm(t('teach_au_publish_confirm'))) {
          return;
        }
        save().then(function() {
          return api('publish', {code: st.info.code});
        }).then(function(info) {
          st.info = info;
          T.toast(esc(t('teach_au_published')));
          st.done();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      } else if (ACTS[act]) {
        ACTS[act](b);
        render();
        later();
      }
    });
  }

  /** Opens a draft in an element of the panel. */
  function open(el, code, done) {
    el.innerHTML = '<div class="ws"></div>';
    st.el = el.firstChild;
    st.done = done;
    st.step = 'mission';
    st.scene = null;
    st.el.innerHTML = '<div class="loading"><div><div class="lamp"></div>' + esc(t('app_loading')) + '</div></div>';
    api('get', {code: code}).then(function(info) {
      st.info = info;
      st.d = info.draft;
      render();
      bind();
    }).catch(function(e) {
      T.toast(esc(e.message), true);
      done();
    });
  }

  /**
   * The card of the mission tab: the missions the teacher made in the course, and a new one.
   *
   * @param {HTMLElement} box
   * @param {Function} edit Called with the code of the draft to open.
   */
  function card(box, edit) {
    var draw = function(list) {
      box.innerHTML = '<h2>' + esc(t('teach_au_card')) + '</h2><p style="color:var(--text-soft)">' +
        esc(t('teach_au_card_help')) + '</p>' + (list.length ? '<ul class="list">' + list.map(function(d) {
          return '<li class="item"><span class="grow"><b>' + esc(d.title || t('teach_au_untitled')) + '</b><small>' +
            esc(d.version ? t('teach_au_version', d.version) + (d.current ? ' · ' + t('teach_au_current') : '') :
              t('teach_au_draft')) + '</small></span>' + btn('open', t('teach_au_edit'), ' data-code="' + esc(d.code) + '"',
            true) + btn('duplicate', t('teach_au_duplicate'), ' data-code="' + esc(d.code) + '"') +
            (d.version ? '<a class="btn btn-soft" href="' + esc(cfg.wwwroot + '/local/oksigeniatesape/author_export.php?id=' +
              cfg.courseid + '&code=' + d.code + '&sesskey=' + cfg.sesskey) + '">' + esc(t('teach_au_download')) + '</a>' :
              btn('delete', t('teach_au_delete'), ' data-code="' + esc(d.code) + '"')) + '</li>';
        }).join('') + '</ul>' : '') + '<div class="actions" style="justify-content:flex-start"><input type="text" ' +
        'data-newtitle placeholder="' + esc(t('teach_au_new_placeholder')) + '"> ' + btn('new', t('teach_au_new'), '', true) +
        '<a class="btn btn-soft" href="' + esc(cfg.wwwroot + '/local/oksigeniatesape/author_import.php?id=' + cfg.courseid) +
        '">' + esc(t('teach_au_import')) + '</a></div>';
      box.querySelectorAll('[data-act="open"]').forEach(function(b) {
        b.onclick = function() {
          edit(b.dataset.code);
        };
      });
      box.querySelectorAll('[data-act="delete"]').forEach(function(b) {
        b.onclick = function() {
          if (window.confirm(t('teach_au_delete_confirm'))) {
            api('delete', {code: b.dataset.code}).then(draw).catch(function(e) {
              T.toast(esc(e.message), true);
            });
          }
        };
      });
      box.querySelectorAll('[data-act="duplicate"]').forEach(function(b) {
        b.onclick = function() {
          api('duplicate', {code: b.dataset.code}).then(draw).catch(function(e) {
            T.toast(esc(e.message), true);
          });
        };
      });
      box.querySelector('[data-act="new"]').onclick = function() {
        api('create', {value: box.querySelector('[data-newtitle]').value}).then(function(info) {
          edit(info.code);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    };
    api('list').then(draw).catch(function() {
      box.innerHTML = '';
    });
  }

  T.author = {open: open, card: card};
})();
