/**
 * Tesapé: the workshop where the teacher changes the mission of the course: the texts of its places and scenes, the
 * questions of the battle and the secrets of the class in the paintings. The changes are kept apart from the mission (a
 * newer version of it does not take them away); an empty text, or «Back to the original», takes a change away.
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
  var MISSION = '#';
  var state = {data: null, el: null, done: null, questions: null, secrets: null};

  function api(action, extra) {
    return T.api('edit_mission', Object.assign({courseid: cfg.courseid, action: action}, extra || {})).then(function(r) {
      state.data = JSON.parse(r.json);
      return state.data;
    });
  }

  /**
   * The name of a text for the teacher: narration, question, option, hint…
   *
   * @param {Object} f
   * @return {string}
   */
  function label(f) {
    var parts = f.path.split('/');
    var list = parts[0] === 'nodes' ? parts[2] : '';
    var n = parseInt(parts[parts.length - 1], 10) + 1;
    // A text of the sheet of a session: of the entry, or of a session of a place.
    var at = parts[0] === 'entry' ? 1 : parts[2] === 'sessions' ? 4 : -1;
    if (at > 0 && parts.length > at && (parts.length > at + 1 || f.kind !== 'do' && f.kind !== 'materials')) {
      var session = parts[0] === 'entry' ? t('teach_ws_k_entry') : t('teach_ws_k_sessionname', parseInt(parts[3], 10) + 1);
      var item = parseInt(parts[at + 1], 10) + 1;
      switch (f.kind) {
        case 'title':
          return t('teach_ws_k_sheet_title', session);
        case 'spare':
          return t('teach_ws_k_sheet_spare', session);
        case 'before':
          return t('teach_ws_k_sheet_before', session);
        case 'do':
          return t('teach_ws_k_sheet_step', {s: session, n: item});
        case 'say':
          return t('teach_ws_k_sheet_say', {s: session, n: item});
        case 'ask':
          return t('teach_ws_k_sheet_ask', {s: session, n: item});
        case 'expect':
          return t('teach_ws_k_sheet_expect', {s: session, n: item});
        case 'mistake':
          return t('teach_ws_k_sheet_mistake', {s: session, n: item});
        case 'redirect':
          return t('teach_ws_k_sheet_redirect', {s: session, n: item});
      }
    }
    switch (f.kind) {
      case 'title':
        return t('teach_ws_k_title');
      case 'whatif':
        return t('teach_ws_k_whatif');
      case 'text':
        return t(f.type === 'ending' ? 'teach_ws_k_ending' : 'teach_ws_k_story');
      case 'prompt':
        return t('teach_ws_k_prompt');
      case 'hints':
        return t('teach_ws_k_hint');
      case 'label':
        return t({options: 'teach_ws_k_option', items: 'teach_ws_k_card', bins: 'teach_ws_k_box', steps: 'teach_ws_k_step'}[list] ||
          'teach_ws_k_option');
      case 'feedback':
        return t(list === 'items' ? 'teach_ws_k_feedback_card' : 'teach_ws_k_feedback_option');
      case 'stuck':
        return t('teach_ws_k_stuck');
      case 'views':
        // A piece of the information shared out: what only that role sees.
        return t('teach_ws_k_piece', T.roleName ? T.roleName(T.family || '', parts[parts.length - 1], 'm') :
          parts[parts.length - 1]);
      case 'nature':
        // What is said to whoever is described by a word.
        return t('teach_ws_k_nature', T.natureName ? T.natureName(parts[parts.length - 1], 'm') : parts[parts.length - 1]);
      case 'intro':
        return t('teach_ws_k_intro');
      case 'offscreen':
        return t('teach_ws_k_offscreen');
      case 'do':
        return t('teach_ws_k_session', parseInt(parts[3], 10) + 1);
      case 'materials':
        return t('teach_ws_k_materials', parseInt(parts[3], 10) + 1);
      case 'pacts':
        return t('teach_ws_k_pact', n);
      case 'say':
        return t('teach_ws_k_say');
      case 'name':
      case 'claim':
      case 'laugh':
      case 'weakness':
      case 'why':
        // A knot of the battle: its texts, with the knot they belong to.
        return t('teach_ws_k_knot_' + f.kind, parts[2]);
      default:
        return f.kind;
    }
  }

  function fieldHtml(scene, f) {
    var rows = Math.min(8, Math.max(2, Math.ceil(f.text.length / 75)));
    return '<label class="ws-field' + (f.changed ? ' changed' : '') + '" data-field="' + esc(f.path) + '"><span>' +
      esc(label(f)) + (f.changed ? ' <button type="button" class="ws-link" data-orig>' + esc(t('teach_ws_original')) +
      '</button>' : '') + '</span><textarea rows="' + rows + '"' + (f.max ? ' maxlength="' + f.max + '"' : '') +
      ' data-scene="' + esc(scene) + '" data-path="' +
      esc(f.path) + '">' + esc(f.text) + '</textarea>' + (f.changed ? '<small>' + esc(t('teach_ws_was', f.original)) +
      '</small>' : '') + '</label>';
  }

  /**
   * The texts of a scene, moment after moment.
   *
   * @param {Object} sc
   * @return {string}
   */
  function sceneBody(sc) {
    // Played as a student would, with what is changed here; or with what each answer counts for beside it.
    var html = '<div class="actions" style="justify-content:flex-start;margin-bottom:10px"><button type="button" ' +
      'class="btn btn-soft" data-tryscene="' + sc.id + '">' + esc(t('teach_ws_try')) + '</button><button type="button" ' +
      'class="btn btn-soft" data-tryscene="' + sc.id + '" data-evidence>' + esc(t('teach_ws_evidence')) + '</button></div>';
    // In the game or off: off, it is not on the map nor on the class screen, and its chapter is done without it.
    html += '<label class="ws-toggle"><input type="checkbox" data-enabledscene="' + esc(sc.code) + '"' + (sc.enabled ?
      ' checked' : '') + '> <span><b>' + esc(t('teach_ws_enabled')) + '</b><small>' + esc(t('teach_ws_enabled_help')) +
      '</small></span></label>';
    // The sniffing of the creature in this scene: the teacher switches it off to see the scene without that help.
    html += sc.sniffable ? '<label class="ws-toggle"><input type="checkbox" data-sniffscene="' + esc(sc.code) + '" ' +
      'data-came="' + (sc.sniffcame ? 1 : 0) + '"' + (sc.sniff ? ' checked' : '') + '> <span><b>' + esc(t('teach_ws_sniff')) +
      '</b><small>' + esc(t('teach_ws_sniff_help')) + '</small></span></label>' : '';
    var node = null;
    var moment = 0;
    sc.fields.forEach(function(f) {
      if (f.node !== node) {
        html += (node !== null ? '</div>' : '') + (f.node ? '<div class="ws-node"><div class="kicker">' +
          esc(t('teach_ws_moment', ++moment)) + '</div>' : '<div class="ws-node">');
        node = f.node;
      }
      html += fieldHtml(sc.code, f);
    });
    html += node !== null ? '</div>' : '';
    return html + (sc.changed ? '<div class="actions" style="justify-content:flex-start"><button class="btn btn-soft" ' +
      'data-restorescene="' + esc(sc.code) + '">' + esc(t('teach_ws_sceneorig')) + '</button></div>' : '');
  }

  function stepName(sc) {
    return sc.quest ? t('app_quests') + ' · ' + t('teach_step_' + sc.step) : sc.step ? t('teach_step_' + sc.step) :
      t('teach_ws_everybody');
  }

  /**
   * For each knot of the place, lanes drafted by the AI of the site, if the course has it: nothing is kept until the
   * teacher takes it, one by one.
   *
   * @param {Object} ch
   * @return {string}
   */
  function knotDrafts(ch) {
    if (!state.data.ai || !state.data.ai.canuse) {
      return '';
    }
    var codes = [];
    ch.fields.forEach(function(f) {
      var m = /^battle\/knots\/([^/]+)\//.exec(f.path);
      if (m && codes.indexOf(m[1]) < 0) {
        codes.push(m[1]);
      }
    });
    return codes.map(function(code) {
      return '<div class="ws-knotdraft"><button type="button" class="btn btn-soft" data-knotdraft="' + esc(code) + '">' +
        esc(t('teach_ws_knotdraft')) + '</button><small>' + esc(t('teach_ws_knotdraft_help')) + '</small>' +
        '<div data-knotbox="' + esc(code) + '"></div></div>';
    }).join('');
  }

  /**
   * What the AI proposed for a knot: each question with its options (the right one first) and why, and the boasts.
   *
   * @param {Element} box
   * @param {string} code
   * @param {Object} p questions and laughs.
   */
  function drawProposal(box, code, p) {
    box.innerHTML = p.questions.map(function(q, i) {
      return '<div class="ws-question"><b>' + esc(q.text) + '</b><ul>' + q.options.map(function(o, n) {
        return '<li>' + (n === 0 ? '✓ ' : '') + esc(o) + '</li>';
      }).join('') + '</ul>' + (q.why ? '<small>' + esc(q.why) + '</small>' : '') + '<div class="actions" ' +
        'style="justify-content:flex-start"><button type="button" class="btn btn-soft" data-knottake="' + i + '">' +
        esc(t('teach_ws_knottake')) + '</button></div></div>';
    }).join('') + p.laughs.map(function(l, i) {
      return '<div class="ws-question"><i>«' + esc(l) + '»</i><div class="actions" style="justify-content:flex-start">' +
        '<button type="button" class="btn btn-soft" data-knotlaugh="' + i + '">' + esc(t('teach_ws_knotlaugh')) +
        '</button></div></div>';
    }).join('');
    box.querySelectorAll('[data-knottake]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        api('knotaccept', {scene: code, value: JSON.stringify(p.questions[Number(b.dataset.knottake)])}).then(function() {
          b.textContent = t('teach_ws_knottaken');
          counts();
          T.toast(esc(t('app_saved')));
        }).catch(function(e) {
          b.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    });
    box.querySelectorAll('[data-knotlaugh]').forEach(function(b) {
      b.onclick = function() {
        b.disabled = true;
        save(MISSION, 'battle/knots/' + code + '/laugh', p.laughs[Number(b.dataset.knotlaugh)], b.closest('.ws-scene'));
      };
    });
  }

  function skippedTag(ch) {
    return ch.skipped ? '<span class="tag todo">' + esc(t('teach_ws_skipped')) + '</span>' : '';
  }

  function sceneSummary(sc) {
    return '<b>' + esc(sc.title) + '</b><small>' + esc(stepName(sc)) + '</small>' + (sc.enabled ? '' : '<span class="tag ' +
      'todo">' + esc(t('teach_ws_off')) + '</span>') + changes(sc.changed);
  }

  function changes(n) {
    return n ? '<span class="tag done">' + esc(t('teach_ws_changes', n)) + '</span>' : '';
  }

  function chapterHtml(ch) {
    var placechanged = ch.fields.filter(function(f) {
      return f.changed;
    }).length;
    var total = placechanged + ch.scenes.reduce(function(sum, sc) {
      return sum + sc.changed;
    }, 0);
    return '<details class="card ws-chapter" data-chapter="' + esc(ch.code) + '"><summary><h3>' + esc(ch.number + '. ' +
      ch.name) + '</h3>' + skippedTag(ch) + changes(total) + '</summary><details class="ws-scene" data-place="' + esc(ch.code) +
      '"><summary><b>' + esc(t('teach_ws_place')) + '</b>' + changes(placechanged) + '</summary><div class="ws-body">' +
      '</div></details>' + ch.scenes.map(function(sc) {
        return '<details class="ws-scene" data-code="' + esc(sc.code) + '"><summary>' + sceneSummary(sc) + '</summary>' +
          '<div class="ws-body"></div></details>';
      }).join('') + '</details>';
  }

  function findScene(code) {
    var found = null;
    state.data.chapters.forEach(function(ch) {
      ch.scenes.forEach(function(sc) {
        if (sc.code === code) {
          found = sc;
        }
      });
    });
    return found;
  }

  function findChapter(code) {
    return state.data.chapters.filter(function(ch) {
      return ch.code === code;
    })[0];
  }

  /* ---------- The questions of the battle ---------- */

  function questionHtml(q, i) {
    var d = state.data;
    var options = q.options.slice();
    while (options.length < 4) {
      options.push('');
    }
    return '<div class="ws-question" data-q="' + i + '"><div class="grid2" style="gap:10px">' +
      '<label class="ws-field"><span>' + esc(t('teach_ws_q_chapter')) + '</span><select data-qchapter>' +
      d.chapters.map(function(ch) {
        return '<option value="' + esc(ch.code) + '"' + (ch.code === q.chapter ? ' selected' : '') + '>' +
          esc(ch.number + '. ' + ch.name) + '</option>';
      }).join('') + '</select></label><label class="ws-field"><span>' + esc(t('teach_ws_q_objective')) +
      '</span><select data-qobjective><option value="">' + esc(t('teach_ws_q_none')) + '</option>' +
      d.objectives.map(function(o) {
        return '<option value="' + esc(o.code) + '"' + (o.code === q.objective ? ' selected' : '') + '>' +
          esc(o.code + ' · ' + o.name) + '</option>';
      }).join('') + '</select></label></div><label class="ws-field"><span>' + esc(t('teach_ws_q_text')) +
      '</span><textarea rows="2" data-qtext>' + esc(q.text) + '</textarea></label>' + options.map(function(o, n) {
        return '<label class="ws-field' + (n ? '' : ' right') + '"><span>' + esc(t(n ? 'teach_ws_q_other' : 'teach_ws_q_right')) +
          '</span><input type="text" data-qoption value="' + esc(o) + '"></label>';
      }).join('') + '<label class="ws-field"><span>' + esc(t('teach_ws_q_why')) + '</span><textarea rows="2" data-qwhy>' +
      esc(q.why) + '</textarea></label><div class="actions" style="justify-content:flex-end"><button class="btn btn-soft" ' +
      'data-qdelete>' + esc(t('teach_ws_q_delete')) + '</button></div></div>';
  }

  function questionsHtml() {
    var list = state.questions;
    return '<details class="card ws-chapter" data-questions' + (state.questionsopen ? ' open' : '') + '><summary><h3>' +
      esc(t('teach_ws_questions')) + '</h3>' + (state.data.questionschanged ? changes(1) : '') + '</summary>' +
      '<p class="help">' + esc(t('teach_ws_questions_help')) + '</p>' + list.map(questionHtml).join('') +
      '<div class="actions" style="justify-content:flex-start"><button class="btn btn-soft" data-qadd>' +
      esc(t('teach_ws_q_add')) + '</button><button class="btn btn-main" data-qsave>' + esc(t('teach_ws_q_save')) +
      '</button>' + (state.data.questionschanged ? '<button class="btn btn-soft" data-qorig>' + esc(t('teach_ws_q_orig')) +
      '</button>' : '') + '</div></details>';
  }

  /** The questions as they are on the screen now. */
  function readQuestions() {
    return Array.prototype.map.call(state.el.querySelectorAll('.ws-question'), function(box) {
      var q = state.questions[+box.dataset.q] || {};
      return {
        id: q.id || '',
        chapter: box.querySelector('[data-qchapter]').value,
        objective: box.querySelector('[data-qobjective]').value,
        text: box.querySelector('[data-qtext]').value,
        options: Array.prototype.map.call(box.querySelectorAll('[data-qoption]'), function(input) {
          return input.value;
        }).filter(function(o) {
          return o.trim() !== '';
        }),
        why: box.querySelector('[data-qwhy]').value
      };
    });
  }

  function renderQuestions() {
    var box = state.el.querySelector('[data-questions]');
    var html = document.createElement('div');
    html.innerHTML = questionsHtml();
    box.replaceWith(html.firstChild);
    bindQuestions();
  }

  function bindQuestions() {
    var box = state.el.querySelector('[data-questions]');
    box.addEventListener('toggle', function() {
      state.questionsopen = box.open;
    });
    box.querySelector('[data-qadd]').onclick = function() {
      state.questions = readQuestions();
      var last = state.questions[state.questions.length - 1];
      state.questions.push({id: '', chapter: last ? last.chapter : state.data.chapters[0].code, objective: '', text: '',
        options: [], why: ''});
      renderQuestions();
      var boxes = state.el.querySelectorAll('.ws-question');
      boxes[boxes.length - 1].querySelector('[data-qtext]').focus();
    };
    box.querySelectorAll('[data-qdelete]').forEach(function(b) {
      b.onclick = function() {
        state.questions = readQuestions();
        state.questions.splice(+b.closest('.ws-question').dataset.q, 1);
        renderQuestions();
      };
    });
    box.querySelector('[data-qsave]').onclick = function() {
      var list = readQuestions();
      var bad = list.filter(function(q) {
        return !q.text.trim() || q.options.length < 2;
      });
      if (bad.length) {
        T.toast(esc(t('teach_ws_q_incomplete')), true);
        return;
      }
      api('questions', {value: JSON.stringify(list)}).then(function(d) {
        state.questions = d.questions;
        renderQuestions();
        header();
        T.toast(esc(t('app_saved')));
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
    var orig = box.querySelector('[data-qorig]');
    if (orig) {
      orig.onclick = function() {
        if (!window.confirm(t('teach_ws_q_orig_confirm'))) {
          return;
        }
        api('questions', {value: '[]'}).then(function(d) {
          state.questions = d.questions;
          renderQuestions();
          header();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    }
  }

  /* ---------- The secrets of the class ---------- */

  /**
   * A secret of the teacher: its place, where it is in the painting (touched on it), and what it says.
   *
   * @param {Object} s place, x, y, text.
   * @param {number} i
   * @return {string}
   */
  function secretHtml(s, i) {
    var d = state.data;
    var ch = d.chapters.filter(function(c) {
      return c.code === s.place;
    })[0] || d.chapters[0];
    var art = T.artUrl ? T.artUrl(['scene.' + (ch.art || ch.code)]) : null;
    // The secrets of the mission in this place, faded, so that a new one does not sit on them.
    var theirs = (ch.secrets || []).map(function(o) {
      return '<span class="ws-secret-dot theirs" style="left:' + o.x + '%;top:' + o.y + '%"></span>';
    }).join('');
    return '<div class="ws-secret" data-s="' + i + '"><label class="ws-field"><span>' + esc(t('teach_ws_s_place')) +
      '</span><select data-splace>' + d.chapters.map(function(c) {
        return '<option value="' + esc(c.code) + '"' + (c.code === ch.code ? ' selected' : '') + '>' +
          esc(c.number + '. ' + c.name) + '</option>';
      }).join('') + '</select></label>' + (art ? '<div class="ws-secret-art" data-spick style="background-image:url(\'' +
      esc(art) + '\')" role="button" tabindex="0" aria-label="' + esc(t('teach_ws_s_where')) + '"><span ' +
      'class="ws-secret-band" aria-hidden="true"></span>' + theirs + '<span class="ws-secret-dot" style="left:' + s.x +
      '%;top:' + s.y + '%"></span></div><small>' + esc(t('teach_ws_s_where_help')) + '</small>' : '') +
      '<label class="ws-field"><span>' + esc(t('teach_ws_s_text')) + '</span><textarea rows="2" maxlength="' +
      d.secretmax + '" data-stext>' + esc(s.text) + '</textarea></label><div class="actions" ' +
      'style="justify-content:flex-end"><button class="btn btn-soft" data-sdelete>' + esc(t('teach_ws_s_delete')) +
      '</button></div></div>';
  }

  function secretsHtml() {
    var list = state.secrets;
    return '<details class="card ws-chapter" data-secrets' + (state.secretsopen ? ' open' : '') + '><summary><h3>' +
      esc(t('teach_ws_secrets')) + '</h3>' + (state.data.ownsecrets.length ? '<span class="tag done">' +
      esc(t('teach_ws_s_count', state.data.ownsecrets.length)) + '</span>' : '') + '</summary><p class="help">' +
      esc(t('teach_ws_secrets_help', state.data.secretsmost)) + '</p>' + list.map(secretHtml).join('') +
      '<div class="actions" style="justify-content:flex-start">' + (list.length < state.data.secretsmost ?
      '<button class="btn btn-soft" data-sadd>' + esc(t('teach_ws_s_add')) + '</button>' : '') +
      '<button class="btn btn-main" data-ssave>' + esc(t('teach_ws_q_save')) + '</button></div></details>';
  }

  /** The secrets as they are on the screen now. */
  function readSecrets() {
    return Array.prototype.map.call(state.el.querySelectorAll('.ws-secret'), function(box) {
      var s = state.secrets[+box.dataset.s] || {};
      return {id: s.id || '', place: box.querySelector('[data-splace]').value, x: s.x, y: s.y,
        text: box.querySelector('[data-stext]').value};
    });
  }

  function renderSecrets() {
    var box = state.el.querySelector('[data-secrets]');
    var html = document.createElement('div');
    html.innerHTML = secretsHtml();
    box.replaceWith(html.firstChild);
    bindSecrets();
  }

  function bindSecrets() {
    var box = state.el.querySelector('[data-secrets]');
    box.addEventListener('toggle', function() {
      state.secretsopen = box.open;
    });
    var add = box.querySelector('[data-sadd]');
    if (add) {
      add.onclick = function() {
        state.secrets = readSecrets();
        var last = state.secrets[state.secrets.length - 1];
        state.secrets.push({id: '', place: last ? last.place : state.data.chapters[0].code, x: 50, y: 60, text: ''});
        renderSecrets();
        var boxes = state.el.querySelectorAll('.ws-secret');
        boxes[boxes.length - 1].querySelector('[data-stext]').focus();
      };
    }
    box.querySelectorAll('.ws-secret').forEach(function(one) {
      var i = +one.dataset.s;
      one.querySelector('[data-sdelete]').onclick = function() {
        state.secrets = readSecrets();
        state.secrets.splice(i, 1);
        renderSecrets();
      };
      one.querySelector('[data-splace]').onchange = function() {
        state.secrets = readSecrets();
        renderSecrets();
      };
      var pick = one.querySelector('[data-spick]');
      if (!pick) {
        return;
      }
      // Where it is: touched on the painting, inside what a phone shows of it; with the keys, step by step.
      var put = function(x, y) {
        state.secrets = readSecrets();
        state.secrets[i].x = Math.max(25, Math.min(75, Math.round(x)));
        state.secrets[i].y = Math.max(12, Math.min(88, Math.round(y)));
        var dot = pick.querySelector('.ws-secret-dot:not(.theirs)');
        dot.style.left = state.secrets[i].x + '%';
        dot.style.top = state.secrets[i].y + '%';
      };
      pick.onclick = function(e) {
        var r = pick.getBoundingClientRect();
        put((e.clientX - r.left) * 100 / r.width, (e.clientY - r.top) * 100 / r.height);
      };
      pick.onkeydown = function(e) {
        var s = state.secrets[i];
        var move = {ArrowLeft: [-2, 0], ArrowRight: [2, 0], ArrowUp: [0, -2], ArrowDown: [0, 2]}[e.key];
        if (move) {
          e.preventDefault();
          put(s.x + move[0], s.y + move[1]);
        }
      };
    });
    box.querySelector('[data-ssave]').onclick = function() {
      var list = readSecrets();
      if (list.some(function(s) {
        return !s.text.trim();
      })) {
        T.toast(esc(t('teach_ws_s_incomplete')), true);
        return;
      }
      api('secrets', {value: JSON.stringify(list)}).then(function(d) {
        state.secrets = d.ownsecrets.slice();
        renderSecrets();
        header();
        T.toast(esc(t('app_saved')));
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
  }

  /* ---------- The whole workshop ---------- */

  function header() {
    var head = state.el.querySelector('[data-wshead]');
    head.innerHTML = '<div class="kicker">' + esc(t('teach_tab_mission')) + '</div><h2>' + esc(t('teach_ws_title')) +
      '</h2><p style="color:var(--text-soft)">' + esc(t('teach_ws_intro')) + '</p><div class="actions" ' +
      'style="justify-content:flex-start"><button class="btn btn-main" data-wsback>' + esc(t('teach_ws_back')) +
      '</button>' + (state.data.changed ? '<button class="btn btn-soft" data-allorig>' + esc(t('teach_ws_allorig')) +
      '</button>' + changes(state.data.changed) : '') + '</div>';
    head.querySelector('[data-wsback]').onclick = function() {
      state.done();
    };
    var all = head.querySelector('[data-allorig]');
    if (all) {
      all.onclick = function() {
        if (!window.confirm(t('teach_ws_allorig_confirm'))) {
          return;
        }
        api('restore', {}).then(render).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    }
  }

  /* ---------- The changes, to another course or another teacher ---------- */

  function shareHtml() {
    return '<details class="card ws-chapter" data-share><summary><h3>' + esc(t('teach_ws_share')) + '</h3></summary>' +
      '<p class="help">' + esc(t('teach_ws_share_help')) + '</p><div class="actions" style="justify-content:flex-start">' +
      '<button class="btn btn-soft" data-copy>' + esc(t('teach_ws_copy')) + '</button><button class="btn btn-soft" ' +
      'data-export>' + esc(t('teach_ws_export')) + '</button><label class="btn btn-soft">' + esc(t('teach_ws_import')) +
      '<input type="file" accept=".json,application/json" data-import hidden></label></div><div data-copyto></div>' +
      '</details>';
  }

  function done(d) {
    T.toast(esc(t('teach_ws_done', d.done)));
  }

  function bindShare() {
    var box = state.el.querySelector('[data-share]');
    box.querySelector('[data-export]').onclick = function() {
      T.api('edit_mission', {courseid: cfg.courseid, action: 'export'}).then(function(r) {
        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([r.json], {type: 'application/json'}));
        link.download = 'tesape-' + JSON.parse(r.json).mission + '-' + cfg.courseid + '.json';
        document.body.appendChild(link);
        link.click();
        link.remove();
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
    box.querySelector('[data-import]').onchange = function(ev) {
      var file = ev.target.files[0];
      if (!file) {
        return;
      }
      file.text().then(function(text) {
        return api('import', {value: text});
      }).then(function(d) {
        done(d);
        render();
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
    box.querySelector('[data-copy]').onclick = function() {
      var to = box.querySelector('[data-copyto]');
      T.api('edit_mission', {courseid: cfg.courseid, action: 'courses'}).then(function(r) {
        var courses = JSON.parse(r.json);
        var ids = Object.keys(courses);
        if (!ids.length) {
          to.innerHTML = '<p class="help">' + esc(t('teach_ws_copy_none')) + '</p>';
          return;
        }
        to.innerHTML = '<div class="actions" style="justify-content:flex-start"><select data-target>' + ids.map(function(id) {
          return '<option value="' + esc(id) + '">' + esc(courses[id]) + '</option>';
        }).join('') + '</select><button class="btn btn-main" data-copygo>' + esc(t('teach_ws_copy_go')) +
          '</button></div>';
        to.querySelector('[data-copygo]').onclick = function() {
          var select = to.querySelector('[data-target]');
          if (!window.confirm(t('teach_ws_copy_confirm', courses[select.value]))) {
            return;
          }
          api('copy', {target: +select.value}).then(done).catch(function(e) {
            T.toast(esc(e.message), true);
          });
        };
      }).catch(function(e) {
        T.toast(esc(e.message), true);
      });
    };
  }

  function render() {
    state.questions = state.data.questions.slice();
    state.secrets = state.data.ownsecrets.slice();
    state.el.innerHTML = '<section class="card ws-head" data-wshead></section>' +
      state.data.chapters.map(chapterHtml).join('') + questionsHtml() + secretsHtml() + shareHtml();
    header();
    bindQuestions();
    bindSecrets();
    bindShare();
  }

  /** Fills a scene or a place when it is opened: there are many texts, and most stay closed. */
  function fill(details) {
    var body = details.querySelector(':scope > .ws-body');
    if (details.dataset.place) {
      var ch = findChapter(details.dataset.place);
      // The class passes the place by, or comes back to it.
      body.innerHTML = (ch.skipped ? '<div class="ws-skip"><p>' + esc(t('teach_ws_skipped_help')) + '</p><button type="button" ' +
        'class="btn btn-soft" data-skipchapter="' + esc(ch.code) + '">' + esc(t('teach_ws_unskip')) + '</button></div>' :
        ch.skippable ? '<div class="ws-skip"><button type="button" class="btn btn-soft" data-skipchapter="' + esc(ch.code) +
        '" data-skip>' + esc(t('teach_ws_skip')) + '</button><small>' + esc(t('teach_ws_skip_help')) + '</small></div>' : '') +
        // The classroom challenge of the place: one of those of the course, made in «Challenges».
        '<label class="ws-field"><span>' + esc(t('teach_ws_challenge')) + '</span><select data-placechallenge="' +
        esc(ch.code) + '"><option value="0">' + esc(t('teach_ws_challenge_none')) + '</option>' +
        (state.data.challenges || []).map(function(c) {
          // One that is another place's moves here: it says so.
          var other = c.chapterid && c.chapterid !== ch.id ? state.data.chapters.filter(function(x) {
            return x.id === c.chapterid;
          })[0] : null;
          return '<option value="' + c.id + '"' + (c.id === ch.challengeid ? ' selected' : '') + '>' + esc(c.name +
            (other ? ' · ' + t('teach_ws_challenge_elsewhere', other.name) : '')) + '</option>';
        }).join('') + '</select><small>' + esc(t('teach_ws_challenge_help')) + '</small></label>' +
        ch.fields.map(function(f) {
          return fieldHtml(MISSION, f);
        }).join('') + knotDrafts(ch);
    } else {
      body.innerHTML = sceneBody(findScene(details.dataset.code));
    }
  }

  /** After a change, the summaries say how many changes there are. */
  function counts() {
    state.data.chapters.forEach(function(ch) {
      var box = state.el.querySelector('[data-chapter="' + ch.code + '"]');
      if (!box) {
        return;
      }
      var placechanged = ch.fields.filter(function(f) {
        return f.changed;
      }).length;
      var total = placechanged;
      ch.scenes.forEach(function(sc) {
        total += sc.changed;
        var s = box.querySelector('[data-code="' + sc.code + '"] > summary');
        s.innerHTML = sceneSummary(sc);
      });
      box.querySelector(':scope > summary').innerHTML = '<h3>' + esc(ch.number + '. ' + ch.name) + '</h3>' + skippedTag(ch) +
        changes(total);
      box.querySelector('[data-place] > summary').innerHTML = '<b>' + esc(t('teach_ws_place')) + '</b>' +
        changes(placechanged);
    });
    header();
  }

  function save(scene, path, value, details) {
    // The last change on its way: a preview waits for it, so that it shows what was just written.
    state.saving = api('save', {scene: scene, path: path, value: value}).then(function() {
      fill(details);
      counts();
      T.toast(esc(t('app_saved')));
    }).catch(function(e) {
      T.toast(esc(e.message), true);
    });
    return state.saving;
  }

  function bind() {
    state.el.addEventListener('toggle', function(ev) {
      var d = ev.target;
      if (d.classList && d.classList.contains('ws-scene') && d.open) {
        fill(d);
      }
    }, true);
    state.el.addEventListener('change', function(ev) {
      var box = ev.target;
      if (box.dataset && box.dataset.placechallenge) {
        var holder = box.closest('.ws-scene');
        api('challenge', {scene: box.dataset.placechallenge, value: box.value}).then(function() {
          fill(holder);
          T.toast(esc(t('app_saved')));
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          fill(holder);
        });
        return;
      }
      if (box.dataset && box.dataset.enabledscene) {
        var details = box.closest('.ws-scene');
        save(box.dataset.enabledscene, 'enabled', box.checked ? '' : 'off', details).then(function() {
          fill(details);
        });
        return;
      }
      if (box.dataset && box.dataset.sniffscene) {
        var came = box.dataset.came === '1';
        save(box.dataset.sniffscene, 'sniff', box.checked === came ? '' : (box.checked ? 'on' : 'off'), box.closest('.ws-scene'));
        return;
      }
      var area = ev.target;
      if (area.tagName !== 'TEXTAREA' || !area.dataset.path) {
        return;
      }
      save(area.dataset.scene, area.dataset.path, area.value.trim(), area.closest('.ws-scene'));
    });
    state.el.addEventListener('click', function(ev) {
      var draft = ev.target.closest('[data-knotdraft]');
      if (draft) {
        var code = draft.dataset.knotdraft;
        var box = state.el.querySelector('[data-knotbox="' + code + '"]');
        draft.disabled = true;
        box.innerHTML = '<p class="help">' + esc(t('teach_ws_knotwait')) + '</p>';
        // Not through api(): what comes back is a proposal, not the workshop.
        T.api('edit_mission', {courseid: cfg.courseid, action: 'knotdraft', scene: code}).then(function(r) {
          draft.disabled = false;
          drawProposal(box, code, JSON.parse(r.json));
        }).catch(function(e) {
          draft.disabled = false;
          box.innerHTML = '';
          T.toast(esc(e.message), true);
        });
        return;
      }
      var skip = ev.target.closest('[data-skipchapter]');
      if (skip) {
        var going = skip.hasAttribute('data-skip');
        var place = findChapter(skip.dataset.skipchapter);
        if (!window.confirm(t(going ? 'teach_ws_skip_confirm' : 'teach_ws_unskip_confirm', place.name))) {
          return;
        }
        skip.disabled = true;
        var holder = skip.closest('.ws-scene');
        api('skip', {scene: place.code, value: going ? 'on' : ''}).then(function() {
          fill(holder);
          counts();
          T.toast(esc(t('app_saved')));
        }).catch(function(e) {
          skip.disabled = false;
          T.toast(esc(e.message), true);
        });
        return;
      }
      var tryit = ev.target.closest('[data-tryscene]');
      if (tryit) {
        // What was being written is kept first (it is saved when the box is left).
        if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
          document.activeElement.blur();
        }
        setTimeout(function() {
          Promise.resolve(state.saving).then(function() {
            return T.api('get_scene', {courseid: cfg.courseid, sceneid: Number(tryit.dataset.tryscene)});
          }).then(function(scene) {
            // As a student would see it, with a made-up character; nothing is kept.
            T.playScene({preview: scene, evidence: tryit.hasAttribute('data-evidence'), world: {character: {role: '',
              creature: '{}', stage: 1}, items: [], team: null, family: T.family || 'storyheroes', nicknames: false},
              onclose: function() {
                return null;
              }});
          }).catch(function(e) {
            T.toast(esc(e.message), true);
          });
        }, 50);
        return;
      }
      var orig = ev.target.closest('[data-orig]');
      if (orig) {
        ev.preventDefault();
        var area = orig.closest('.ws-field').querySelector('textarea');
        save(area.dataset.scene, area.dataset.path, '', area.closest('.ws-scene'));
        return;
      }
      var scene = ev.target.closest('[data-restorescene]');
      if (scene) {
        var details = scene.closest('.ws-scene');
        api('restore', {scene: scene.dataset.restorescene}).then(function() {
          fill(details);
          counts();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      }
    });
  }

  T.workshop = {
    /**
     * Opens the workshop in an element of the panel.
     *
     * @param {HTMLElement} el
     * @param {Function} done When the teacher goes back to the mission.
     */
    open: function(el, done) {
      // A new element each time: the listeners of an earlier visit stay with the old one.
      el.innerHTML = '<div class="ws"></div>';
      state.el = el.firstChild;
      state.done = done;
      state.el.innerHTML = '<div class="loading"><div><div class="lamp"></div>' + esc(t('app_loading')) + '</div></div>';
      api('view').then(function() {
        render();
        bind();
      }).catch(function(e) {
        T.toast(esc(e.message), true);
        done();
      });
    }
  };
})();
