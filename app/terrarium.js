/**
 * Tesapé: the terrarium of the clearing. The Embrollo has filled the clearing with things; the student puts in the
 * terrarium what they think is alive and lets a week go by. What is alive changes (it grows, eats, has young), what is
 * not stays the same, or changes without being alive. They take out what did not change and try again, until everything
 * inside is alive. Never lost. A touch puts a thing in or takes it out: no dragging needed. The server counts it again.
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

  /**
   * Plays the terrarium.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playTerrarium = function(opts) {
    var m = T.minigame(opts, 'terrarium', function(g) {
      game = g;
      intro();
    });
    var layer = m.layer;
    var show = m.show;
    var sound = m.sound;
    var game = null;
    var run = null;

    /**
     * A thing of the game.
     *
     * @param {string} id
     * @return {Object}
     */
    function thing(id) {
      for (var i = 0; i < game.things.length; i++) {
        if (game.things[i].id === id) {
          return game.things[i];
        }
      }
      return {id: id, label: id, alive: false, week: '', why: '', picture: '', weekpicture: ''};
    }

    /* ---------- Start ---------- */

    function intro() {
      var role = game.passive;
      var note = '';
      if (role === 'sage' || game.sniffs) {
        note = '<p class="help game-passive">' + T.icon(role === 'sage' ? 'lens' : 'trail') +
          esc(t(role === 'sage' ? 'app_terrarium_passive_sage' : 'app_terrarium_sniff_note')) + '</p>';
      }
      var body = show('<div class="terrarium-intro">' + m.opening('<span class="ter-glass big" aria-hidden="true"></span>') +
        '<p class="help">' + esc(t('app_terrarium_goal', {goal: game.goal, most: game.most})) + '</p>' + note +
        '<div class="actions"><button class="btn btn-main" data-go>' + esc(t('app_whoami_start')) + '</button></div></div>');
      body.querySelector('[data-go]').onclick = start;
      body.querySelector('[data-go]').focus();
    }

    function start() {
      // seen: what a week (or a look) already showed of each thing: alive, or not.
      run = {events: [], inside: [], seen: {}, changed: {}, weeks: 0, say: null, done: false, over: false};
      m.begin();
      draw();
    }

    /* ---------- The clearing and the terrarium ---------- */

    /**
     * A thing as a button: its picture (a week later, when one went by and the pack has it), its name and what was seen.
     *
     * @param {Object} x
     * @param {boolean} inside
     * @return {string}
     */
    function piece(x, inside) {
      var seen = run.seen[x.id];
      var later = run.changed[x.id] && x.alive && x.weekpicture && T.artUrl([x.weekpicture]) ? x.weekpicture : x.picture;
      var tag = seen === 'alive' ? '<span class="ter-tag alive">' + esc(t('app_terrarium_changed')) + '</span>' :
        seen === 'not' ? '<span class="ter-tag not">' + esc(t(x.week ? 'app_terrarium_notalive' : 'app_terrarium_same')) +
        '</span>' : '';
      var full = !inside && run.inside.length >= game.most;
      // Its name is what it shows, and what a touch does, for whoever does not see it.
      return '<button type="button" class="ter-thing' + (seen ? ' seen-' + seen : '') + (run.changed[x.id] && x.alive ?
        ' grown' : '') + '" data-thing="' + esc(x.id) + '"' + (run.done || full ? ' disabled' : '') + '>' +
        T.thing(later, T.cap(x.label)) + tag + '<span class="sr-only">. ' + esc(t(inside ? 'app_terrarium_takeout' :
        'app_terrarium_putin')) + '</span></button>';
    }

    function draw() {
      var out = game.things.filter(function(x) {
        return run.inside.indexOf(x.id) < 0;
      });
      var html = '<div class="nest-hud"><span class="kicker">' + esc(run.weeks ? t('app_terrarium_week', run.weeks) :
        t('app_terrarium_title')) + '</span><span class="ter-count">' + esc(t('app_terrarium_count', {n: run.inside.length,
        most: game.most})) + '</span>' + m.lookButton() + '</div>' +
        '<div class="ter-box' + (run.done ? ' done' : '') + '" role="group" aria-label="' + esc(t('app_terrarium_box')) +
        '"><span class="ter-glass" aria-hidden="true"></span><div class="ter-in">' + (run.inside.length ? run.inside.map(function(id) {
          return piece(thing(id), true);
        }).join('') : '<p class="help ter-empty">' + esc(t('app_terrarium_empty')) + '</p>') + '</div></div>' +
        '<div class="ter-say" data-say aria-live="polite">' + (run.say ? run.say : '') + '</div>' +
        '<div class="actions" data-actions></div>' +
        '<h3 class="ter-clearing">' + esc(t('app_terrarium_clearing')) + '</h3><div class="ter-out" role="group" ' +
        'aria-label="' + esc(t('app_terrarium_clearing')) + '">' + out.map(function(x) {
          return piece(x, false);
        }).join('') + '</div>';
      var body = show(html);
      body.querySelectorAll('[data-thing]').forEach(function(b) {
        b.onclick = function() {
          toggle(b.dataset.thing);
        };
      });
      m.bindLook(body, look);
      var actions = body.querySelector('[data-actions]');
      if (run.done) {
        actions.innerHTML = '<button class="btn btn-main" data-end>' + esc(t('app_terrarium_end')) + '</button>';
        actions.querySelector('[data-end]').onclick = finish;
        actions.querySelector('[data-end]').focus();
      } else {
        actions.innerHTML = '<button class="btn btn-main" data-week' + (run.inside.length ? '' : ' disabled') + '>' +
          T.icon('saber') + esc(t('app_terrarium_letweek')) + '</button>';
        actions.querySelector('[data-week]').onclick = week;
      }
    }

    /**
     * A thing goes into the terrarium, or out of it.
     *
     * @param {string} id
     */
    function toggle(id) {
      if (run.done) {
        return;
      }
      var at = run.inside.indexOf(id);
      if (at < 0 && run.inside.length >= game.most) {
        return;
      }
      if (at < 0) {
        run.inside.push(id);
      } else {
        run.inside.splice(at, 1);
      }
      run.events.push({i: id, in: at < 0 ? 1 : 0});
      sound('choose');
      run.say = run.inside.length >= game.most ? esc(t('app_terrarium_full', game.most)) : null;
      draw();
      // The focus stays with the thing, wherever it went.
      var again = layer.querySelector('[data-thing="' + id + '"]');
      if (again && !again.disabled) {
        again.focus();
      }
    }

    /** A week goes by: each thing inside shows what it does when it is alive, or that it is not. */
    function week() {
      if (run.done || !run.inside.length) {
        return;
      }
      run.events.push({w: 1});
      run.weeks++;
      var living = 0;
      var lines = run.inside.map(function(id) {
        var x = thing(id);
        run.seen[id] = x.alive ? 'alive' : 'not';
        if (x.alive) {
          living++;
          run.changed[id] = true;
        }
        return '<li class="' + (x.alive ? 'alive' : 'not') + '"><b>' + esc(T.cap(x.label)) + ':</b> ' + esc(x.week ||
          t('app_terrarium_same_long')) + '</li>';
      });
      var all = living === run.inside.length;
      run.done = all && living >= game.goal;
      sound(run.done ? 'right' : all ? 'chime' : 'near');
      run.say = '<p class="ter-weeksay"><b>' + esc(t('app_terrarium_after', run.weeks)) + '</b> ' +
        esc(run.done ? t('app_terrarium_alldone') : all ? t('app_terrarium_more', game.goal - living) :
        t('app_terrarium_some', run.inside.length - living)) + '</p><ul class="ter-lines">' + lines.join('') + '</ul>';
      draw();
    }

    /**
     * The look of the sage, or the nose of the creature: a thing that is not alive, and not seen yet, says so.
     *
     * @return {boolean} False if there was nothing to look at.
     */
    function look() {
      if (run.done) {
        return false;
      }
      var hidden = T.shuffle(game.things.filter(function(x) {
        return !x.alive && !run.seen[x.id];
      }));
      if (!hidden.length) {
        return false;
      }
      var x = hidden[0];
      run.events.push({l: x.id});
      run.seen[x.id] = 'not';
      run.say = '<p class="ter-weeksay">' + esc(t('app_terrarium_looked', T.cap(x.label))) + '</p>';
      draw();
      return true;
    }

    /* ---------- The end ---------- */

    function finish() {
      if (run.over) {
        return;
      }
      run.over = true;
      run.playid = run.playid || T.playId();
      m.save(run.playid, run.events, ending);
    }

    function ending(result) {
      // What the week did to each living thing, and what did not change: the definition, seen.
      var learn = game.things.filter(function(x) {
        return run.seen[x.id];
      }).map(function(x) {
        return '<li><b>' + esc(T.cap(x.label)) + ':</b> ' + esc(x.alive ? x.week : (x.week ? x.week + ' ' : '') +
          (x.why || t('app_whack_why_not'))) + '</li>';
      }).join('');
      m.ending(result, {won: t('app_terrarium_won', run.weeks), lost: t('app_terrarium_title'),
        art: '<span class="ter-glass big done" aria-hidden="true"></span>', more: learn ? '<div class="card whack-learn">' +
        '<h3>' + esc(t('app_terrarium_learn')) + '</h3><ul>' + learn + '</ul></div>' : '', again: again});
    }

    function again() {
      show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_loading')) + '</div>');
      T.api('get_minigame', {courseid: cfg.courseid, game: game.code}).then(function(g) {
        m.game = g;
        game = g;
        intro();
      }).catch(function(e) {
        show('<p>' + esc(e.message) + '</p>');
      });
    }
  };
})();
