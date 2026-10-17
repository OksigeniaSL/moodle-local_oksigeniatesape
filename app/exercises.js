/**
 * Tesapé: the lanes of a battle by turns that are not to choose, the same way on every tablet. Given a lane, it draws it
 * and gives back what the student did (the server says whether it undoes it): sorting, touch a thing and then its
 * group; the mistakes of the Embrollo, touch the words that are wrong in the sheet. Once the turn is resolved, it shows
 * the solution: each thing in its group, each mistake with the right word and why.
 *
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var t = T.t;
  var esc = T.esc;

  /**
   * Sorting: the things to place and the groups; a thing touched goes to the group touched next.
   *
   * @param {Object} lane prompt, items, bins, mine (this student sorts), speaker, solution once resolved.
   * @param {Object} state What this tablet has done so far: placed (item id => bin id), picked.
   * @param {boolean} sent It was sent already.
   * @return {string}
   */
  function classifyHtml(lane, state, sent) {
    var placed = state.placed || {};
    var solution = lane.solution || null;
    var free = lane.items.filter(function(i) {
      return !placed[i.id] && !solution;
    });
    var chip = function(i, where) {
      var right = solution ? solution[i.id] === where : null;
      return '<button type="button" class="chip lane-item' + (state.picked === i.id ? ' picked' : '') +
        (right === true ? ' right' : right === false ? ' wrong' : '') + '" data-item="' + esc(i.id) + '"' +
        (sent || solution || !lane.mine ? ' disabled' : '') + '>' + esc(i.label) + '</button>';
    };
    return '<p class="battle-q">' + esc(lane.prompt) + '</p>' + (!lane.mine && lane.speaker ? '<p class="help-soft">' +
      esc(t('app_lane_speaker', lane.speaker)) + '</p>' : '') + '<div class="lane-items">' + free.map(function(i) {
        return chip(i, '');
      }).join('') + '</div><div class="lane-bins">' + lane.bins.map(function(b) {
        var here = lane.items.filter(function(i) {
          return solution ? solution[i.id] === b.id : placed[i.id] === b.id;
        });
        // A box with its own button to put the thing picked there (the things in it are buttons of their own).
        return '<div class="lane-bin" data-bin="' + esc(b.id) + '"><button type="button" class="lane-put" data-put="' +
          esc(b.id) + '"' + (sent || solution || !lane.mine || !state.picked ? ' disabled' : '') + '>' + esc(b.label) +
          '</button><span>' + here.map(function(i) {
            return chip(i, b.id);
          }).join('') + '</span></div>';
      }).join('') + '</div>';
  }

  /**
   * The mistakes: the sheet with its words; the words of the marks are the mistakes, any other word touched is a slip.
   *
   * @param {Object} lane parts (text, mark, wrong), solution once resolved (mark, right, why).
   * @param {Object} state marks (touched), misses.
   * @param {boolean} sent
   * @return {string}
   */
  function errorsHtml(lane, state, sent) {
    var touched = state.marks || [];
    var slips = state.slips || [];
    var n = 0;
    var solution = lane.solution ? lane.solution.reduce(function(all, s) {
      all[s.mark] = s;
      return all;
    }, {}) : null;
    var text = lane.parts.map(function(p) {
      if (p.mark < 0) {
        return p.text.split(/(\s+)/).map(function(w) {
          if (/^\s*$/.test(w)) {
            return esc(w);
          }
          n++;
          return '<span class="lane-word' + (slips.indexOf(n) >= 0 ? ' slip' : '') + '" data-plain="' + n + '">' + esc(w) +
            '</span>';
        }).join('');
      }
      var fixed = solution && solution[p.mark];
      return '<button type="button" class="lane-word mark' + (touched.indexOf(p.mark) >= 0 ? ' touched' : '') + '" data-mark="' +
        p.mark + '"' + (sent || solution ? ' disabled' : '') + '>' + (fixed ? '<s>' + esc(p.wrong) + '</s> ' +
        esc((fixed.right || []).join(' / ')) : esc(p.wrong)) + '</button>';
    }).join('');
    var whys = solution ? '<ul class="lane-whys">' + lane.solution.filter(function(s) {
      return s.why;
    }).map(function(s) {
      return '<li>' + esc(s.why) + '</li>';
    }).join('') + '</ul>' : '';
    return '<p class="battle-q">' + esc(t('board_lane_errors')) + '</p><p class="lane-sheet">' + text + '</p>' + whys;
  }

  /**
   * A lane drawn in an element: what the student does is kept in the tablet until they send it with «Done».
   *
   * @param {Element} el
   * @param {Object} lane
   * @param {Object} opts sent (already sent), open (the lane takes answers), onsend(payload).
   */
  T.lane = function(el, lane, opts) {
    var state = T.lane.kept[opts.key] = T.lane.kept[opts.key] || {placed: {}, marks: [], slips: [], picked: ''};
    var draw = function() {
      var body = lane.engine === 'classify' ? classifyHtml(lane, state, opts.sent) : errorsHtml(lane, state, opts.sent);
      var ready = lane.engine === 'classify' ? lane.items.every(function(i) {
        return state.placed[i.id];
      }) : state.marks.length > 0;
      el.innerHTML = body + (lane.mine && !opts.sent && !lane.solution && opts.open ? '<div class="actions">' +
        '<button type="button" class="btn btn-main" data-lanedone' + (ready ? '' : ' disabled') + '>' + esc(t('app_lane_done')) +
        '</button></div>' : '');
      el.querySelectorAll('[data-item]').forEach(function(b) {
        b.onclick = function(ev) {
          ev.stopPropagation();
          // A thing in a group goes back to the things to place.
          if (state.placed[b.dataset.item]) {
            delete state.placed[b.dataset.item];
            state.picked = '';
          } else {
            state.picked = state.picked === b.dataset.item ? '' : b.dataset.item;
          }
          draw();
        };
      });
      el.querySelectorAll('[data-bin]').forEach(function(b) {
        // The whole box takes the thing picked on a tablet; its button, with the keyboard.
        b.onclick = function() {
          if (state.picked && lane.mine && !opts.sent && !lane.solution) {
            state.placed[state.picked] = b.dataset.bin;
            state.picked = '';
            draw();
          }
        };
      });
      el.querySelectorAll('[data-mark]').forEach(function(b) {
        b.onclick = function() {
          var mark = Number(b.dataset.mark);
          var at = state.marks.indexOf(mark);
          if (at >= 0) {
            state.marks.splice(at, 1);
          } else {
            state.marks.push(mark);
          }
          draw();
        };
      });
      if (lane.mine && !opts.sent && !lane.solution) {
        el.querySelectorAll('[data-plain]').forEach(function(w) {
          w.onclick = function() {
            // A word that was right: a slip, and it stays marked so that the student sees it.
            var at = Number(w.dataset.plain);
            if (state.slips.indexOf(at) < 0) {
              state.slips.push(at);
              w.classList.add('slip');
            }
          };
        });
      }
      var done = el.querySelector('[data-lanedone]');
      if (done) {
        done.onclick = function() {
          done.disabled = true;
          opts.onsend(lane.engine === 'classify' ? {placed: state.placed} : {marks: state.marks, misses: state.slips.length});
        };
      }
    };
    draw();
  };

  /** What each tablet has done in each lane, kept across the redrawings of the battle. */
  T.lane.kept = {};
})();
