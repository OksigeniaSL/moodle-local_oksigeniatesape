/**
 * Tesapé: the workshop where the teacher changes the mission of the course: the texts of its places and scenes, and the
 * questions of the battle. The changes are kept apart from the mission (a newer version of it does not take them away);
 * an empty text, or «Back to the original», takes a change away.
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
  var MISSION = '#';
  var state = {data: null, el: null, done: null, questions: null};

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
    switch (f.kind) {
      case 'title':
        return t('teach_ws_k_title');
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
      default:
        return f.kind;
    }
  }

  function fieldHtml(scene, f) {
    var rows = Math.min(8, Math.max(2, Math.ceil(f.text.length / 75)));
    return '<label class="ws-field' + (f.changed ? ' changed' : '') + '" data-field="' + esc(f.path) + '"><span>' +
      esc(label(f)) + (f.changed ? ' <button type="button" class="ws-link" data-orig>' + esc(t('teach_ws_original')) +
      '</button>' : '') + '</span><textarea rows="' + rows + '" data-scene="' + esc(scene) + '" data-path="' +
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
    var html = '';
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
      ch.name) + '</h3>' + changes(total) + '</summary><details class="ws-scene" data-place="' + esc(ch.code) +
      '"><summary><b>' + esc(t('teach_ws_place')) + '</b>' + changes(placechanged) + '</summary><div class="ws-body">' +
      '</div></details>' + ch.scenes.map(function(sc) {
        return '<details class="ws-scene" data-code="' + esc(sc.code) + '"><summary><b>' + esc(sc.title) + '</b><small>' +
          esc(stepName(sc)) + '</small>' + changes(sc.changed) + '</summary><div class="ws-body"></div></details>';
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
    state.el.innerHTML = '<section class="card ws-head" data-wshead></section>' +
      state.data.chapters.map(chapterHtml).join('') + questionsHtml() + shareHtml();
    header();
    bindQuestions();
    bindShare();
  }

  /** Fills a scene or a place when it is opened: there are many texts, and most stay closed. */
  function fill(details) {
    var body = details.querySelector(':scope > .ws-body');
    if (details.dataset.place) {
      var ch = findChapter(details.dataset.place);
      body.innerHTML = ch.fields.map(function(f) {
        return fieldHtml(MISSION, f);
      }).join('');
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
        s.innerHTML = '<b>' + esc(sc.title) + '</b><small>' + esc(stepName(sc)) + '</small>' + changes(sc.changed);
      });
      box.querySelector(':scope > summary').innerHTML = '<h3>' + esc(ch.number + '. ' + ch.name) + '</h3>' + changes(total);
      box.querySelector('[data-place] > summary').innerHTML = '<b>' + esc(t('teach_ws_place')) + '</b>' +
        changes(placechanged);
    });
    header();
  }

  function save(scene, path, value, details) {
    return api('save', {scene: scene, path: path, value: value}).then(function() {
      fill(details);
      counts();
      T.toast(esc(t('app_saved')));
    }).catch(function(e) {
      T.toast(esc(e.message), true);
    });
  }

  function bind() {
    state.el.addEventListener('toggle', function(ev) {
      var d = ev.target;
      if (d.classList && d.classList.contains('ws-scene') && d.open) {
        fill(d);
      }
    }, true);
    state.el.addEventListener('change', function(ev) {
      var area = ev.target;
      if (area.tagName !== 'TEXTAREA' || !area.dataset.path) {
        return;
      }
      save(area.dataset.scene, area.dataset.path, area.value.trim(), area.closest('.ws-scene'));
    });
    state.el.addEventListener('click', function(ev) {
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
