/**
 * Tesapé: what every minigame shares, so that each one keeps only its mechanics. Its layer over the map, the frame with
 * its title and the close button, the loading, the pause button, the saving (kept on the device without a connection,
 * or sent again from here) and the end, where the world answers: what the mission says, what was given and what was
 * mixed up, with its why; never a count of right answers. And inside the play, the streak (sparks, and a game that
 * goes a notch faster every three right answers in a row, up to the ceiling of the step) and the passive of the role:
 * the protector holds one slip more (the server gives it), the healer gets the first slip back after a streak (the
 * server counts it the same way), the sage has one look and the scout sees what comes next.
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

  /**
   * A copy of a list in another order.
   *
   * @param {Array} list
   * @return {Array}
   */
  T.shuffle = function(list) {
    var a = list.slice();
    for (var i = a.length - 1; i > 0; i--) {
      var j = Math.floor(Math.random() * (i + 1));
      var x = a[i];
      a[i] = a[j];
      a[j] = x;
    }
    return a;
  };

  /**
   * A text with its first letter in capitals, to start a sentence with the name of a thing.
   *
   * @param {string} text
   * @return {string}
   */
  T.cap = function(text) {
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
  };

  /**
   * A being or a thing of the mission, drawn: its picture from the art pack, with its name under it; without a picture,
   * its name alone (the reserve, until the art comes).
   *
   * @param {string} picture Key in the art pack (being.<id> or thing.<id>).
   * @param {string} label
   * @return {string}
   */
  T.thing = function(picture, label) {
    var art = picture ? T.artUrl([picture]) : null;
    return (art ? '<img class="thing-pic" src="' + esc(art) + '" alt="">' : '') + '<span class="thing-name">' + esc(label) +
      '</span>';
  };

  /**
   * Opens a minigame: its layer, and its data from the server.
   *
   * @param {Object} opts code, world, onclose(world), as the map gives them.
   * @param {string} kind Class of the game (nest, whack…), for its layer and its frame.
   * @param {function} ready Called with the game when it comes.
   * @return {Object} The frame: world, game, show(html), close(), sound(name), pauseButton(), setPaused(paused), save(),
   *   ending(), and onclose, to stop what the game runs.
   */
  T.minigame = function(opts, kind, ready) {
    var layer = document.createElement('div');
    layer.className = 'scene-layer ' + kind + '-layer';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    document.body.appendChild(layer);
    document.body.classList.add('in-scene');

    var m = {layer: layer, world: opts.world, game: null, onclose: null, flow: null};

    m.close = function() {
      if (m.onclose) {
        m.onclose();
      }
      if (window.speechSynthesis) {
        window.speechSynthesis.cancel();
      }
      layer.remove();
      document.body.classList.remove('in-scene');
      opts.onclose(m.world);
    };

    m.show = function(html) {
      layer.innerHTML = '<div class="scene ' + kind + '"><div class="scene-top"><span class="scene-title">' +
        esc(m.game ? m.game.title : '') + '</span><button class="btn btn-soft scene-close" type="button" aria-label="' +
        esc(t('app_close')) + '">' + T.icon('close') + '</button></div><div class="scene-body">' + html + '</div></div>';
      layer.querySelector('.scene-close').onclick = m.close;
      layer.scrollTop = 0;
      return layer.querySelector('.scene-body');
    };

    m.sound = function(name) {
      if (T.fx) {
        T.fx.sound(name);
      }
    };

    /**
     * The pause button of the counters: an icon, so that they fit on a phone; its name says what it does.
     *
     * @return {string}
     */
    m.pauseButton = function() {
      return '<button class="btn btn-soft icon-btn game-pause" data-pause aria-pressed="false" aria-label="' +
        esc(t('app_nest_pause')) + '" title="' + esc(t('app_nest_pause')) + '">' + T.pauseIcon(false) + '</button>';
    };

    m.setPaused = function(paused) {
      var b = layer.querySelector('[data-pause]');
      if (b) {
        b.innerHTML = T.pauseIcon(paused);
        b.setAttribute('aria-pressed', paused ? 'true' : 'false');
        b.setAttribute('aria-label', paused ? t('app_nest_resume') : t('app_nest_pause'));
        b.title = b.getAttribute('aria-label');
      }
      var arena = layer.querySelector('[data-arena]');
      if (arena) {
        arena.classList.toggle('paused', paused);
      }
    };

    /**
     * The start of a minigame: the two lines of its scene (what the Embrollo did, and how the creature takes it, if it
     * has hatched), then what to do; without a scene, the art of the game and what to do, as before.
     *
     * @param {string} art The picture of the game, when it has no scene.
     * @return {string}
     */
    m.opening = function(art) {
      var game = m.game;
      var scene = game.scene || {};
      if (!scene.embrollo) {
        return art + '<p class="lead">' + esc(game.intro) + '</p>';
      }
      var c = m.world.character || {};
      var html = '<div class="game-opening"><p class="line embrollo"><span class="who" aria-hidden="true">' +
        T.embrollo(1) + '</span><span>' + esc(scene.embrollo) + '</span></p>';
      var name = '';
      try {
        name = c.creature ? JSON.parse(c.creature).name || '' : '';
      } catch (e) {
        name = '';
      }
      if (scene.creature && name && c.creaturestage > 0) {
        html += '<p class="line creature"><span class="who" aria-hidden="true">' + T.creature(c.creature, c.creaturestage) +
          '</span><span>' + esc(scene.creature.replace(/\{\{creature\}\}/g, name)) + '</span></p>';
      }
      return html + '</div><p class="help">' + esc(game.intro) + '</p>';
    };

    /* ---------- Inside the play: the streak and the passive of the role ---------- */

    /** A new play: no streak, no notch, the look of the sage unused. */
    m.begin = function() {
      m.flow = {streak: 0, misses: 0, heat: 0, slipped: false, forgiven: 0, looked: 0};
      layer.classList.remove('streak');
    };

    /**
     * How fast the game goes now, from 0: a notch more every three right answers in a row, one less after two slips
     * in a row, never above the ceiling of the step.
     *
     * @param {number} top
     * @return {number}
     */
    m.notch = function(top) {
      return Math.max(0, Math.min(top, m.flow ? m.flow.heat : 0));
    };

    /**
     * One answer, in the order the server counts them. The healer gets the first slip of the play back after a streak,
     * once: the game takes it off its slips.
     *
     * @param {boolean} right
     * @return {boolean} Whether the healer got a slip back just now.
     */
    m.answer = function(right) {
      var f = m.flow;
      var back = false;
      if (!right) {
        f.slipped = true;
        f.streak = 0;
        if (++f.misses >= 2) {
          f.heat = Math.max(0, f.heat - 1);
          f.misses = 0;
        }
      } else {
        f.misses = 0;
        if (++f.streak % 3 === 0) {
          f.heat++;
        }
        if (f.streak === 3) {
          m.sound('chirp');
        }
        if (m.game.passive === 'healer' && f.slipped && !f.forgiven && f.streak >= m.game.streak) {
          f.forgiven = 1;
          back = true;
        }
      }
      layer.classList.toggle('streak', f.streak >= 3);
      if (back) {
        m.sound('heart');
        T.toast(esc(t('app_passive_back')));
      }
      return back;
    };

    /**
     * What the role of the student does in this minigame, for the start.
     *
     * @return {string} A paragraph, or nothing.
     */
    m.passiveNote = function() {
      var c = m.world.character || {};
      var role = m.game.passive;
      if (!role || !T.roleName) {
        return '';
      }
      var name = T.roleName(m.world.family, role, c.nameform);
      name = name.charAt(0).toLocaleLowerCase(cfg.lang || 'es') + name.slice(1);
      // With a talent that changes it, the passive tells it so («Keen eye»: two looks; «Far sight»: two things).
      var key = 'app_passive_' + role + ((role === 'sage' && m.game.looks > 1) || (role === 'scout' && m.game.ahead > 1) ||
        (role === 'protector' && m.game.extra > 1) ? '_two' : '');
      // And the creature, from its second stage: it sniffs a case once a play.
      return '<p class="help game-passive">' + T.icon(role === 'sage' ? 'lens' : role === 'scout' ? 'trail' :
        role === 'protector' ? 'shield' : 'energy') + esc(t(key, {name: name, streak: m.game.streak, misses: m.game.misses})) +
        '</p>' + (m.game.sniffs ? '<p class="help game-passive">' + T.icon('trail') + esc(t('app_passive_sniff_note')) +
        '</p>' : '');
    };

    /**
     * Whether the sage has used up their looks of the play (one, or two with «Keen eye»).
     *
     * @return {boolean}
     */
    m.lookSpent = function() {
      return m.flow.looked >= (m.game.looks || 0) + (m.game.sniffs || 0);
    };

    /**
     * The look of the sage, for the counters: once a play (twice with «Keen eye»).
     *
     * @return {string}
     */
    m.lookButton = function() {
      if (!(m.game.looks || 0) && !(m.game.sniffs || 0)) {
        return '';
      }
      // The sage looks; the creature, from its second stage, sniffs: the same help, once a play each.
      var label = t(m.game.looks ? 'app_passive_look' : 'app_passive_sniff');
      return '<button class="btn btn-soft icon-btn game-look" type="button" data-look aria-label="' + esc(label) +
        '" title="' + esc(label) + '">' + T.icon(m.game.looks ? 'lens' : 'trail') + '</button>';
    };

    /**
     * What the look does, given by the game; it is spent when it shows something.
     *
     * @param {Element} body
     * @param {function} look Returns false if there was nothing to look at yet.
     */
    m.bindLook = function(body, look) {
      var b = body.querySelector('[data-look]');
      if (!b) {
        return;
      }
      b.onclick = function() {
        if (m.lookSpent()) {
          return;
        }
        // Spent before it looks: a game may draw itself again to show it.
        m.flow.looked++;
        if (look() === false) {
          m.flow.looked--;
          return;
        }
        m.sound('choose');
        var now = layer.querySelector('[data-look]') || b;
        if (m.lookSpent()) {
          now.disabled = true;
          now.classList.add('spent');
        }
      };
    };

    /**
     * Where the scout sees what comes next.
     *
     * @return {string}
     */
    m.aheadSlot = function() {
      return m.game.passive === 'scout' ? '<p class="game-ahead" data-ahead aria-live="polite"></p>' : '';
    };

    /**
     * What the scout sees coming, from what comes next: one thing, or two with «Far sight».
     *
     * @param {string[]} labels What comes, in order.
     * @return {string}
     */
    m.coming = function(labels) {
      var seen = labels.slice(0, m.game.ahead || 1);
      return seen.length ? t('app_passive_coming', seen.join(' · ')) : '';
    };

    /**
     * What the scout sees coming.
     *
     * @param {string} text Empty when nothing more comes.
     */
    m.ahead = function(text) {
      var box = layer.querySelector('[data-ahead]');
      if (box) {
        box.innerHTML = text ? T.icon('trail') + esc(text) : '';
      }
    };

    /**
     * Sends what was played. Without a connection it is kept on the device and goes by itself later; if it cannot be
     * kept either, it can be sent again from here, without playing it again.
     *
     * @param {string} playid
     * @param {Array} events
     * @param {function} done Called with the result, once the world is the new one.
     */
    m.save = function(playid, events, done) {
      var game = m.game;
      var json = JSON.stringify(events);
      m.show('<p class="help">' + esc(t('app_saving')) + '</p>');
      T.api('play_minigame', {courseid: cfg.courseid, game: game.code, playid: playid, events: json}).then(function(result) {
        m.world = result.world;
        done(result);
      }).catch(function(e) {
        if ((e.network || e.retry) && T.keepPlay && T.keepPlay({kind: 'minigame', game: game.code, playid: playid,
          title: game.title || '', events: json, at: Date.now()})) {
          m.show('<div class="conseq good"><span class="what">' + esc(t('app_offline_kept')) + '</span>' +
            esc(t('app_offline_kept_help')) + '</div><div class="actions"><button class="btn btn-main" data-back>' +
            esc(t('app_backtomap')) + '</button></div>').querySelector('[data-back]').onclick = m.close;
          return;
        }
        var body = m.show('<p>' + esc(e.message) + '</p><div class="actions"><button class="btn btn-main" data-again>' +
          esc(t('app_retry')) + '</button><button class="btn btn-soft" data-back>' + esc(t('app_backtomap')) +
          '</button></div>');
        body.querySelector('[data-back]').onclick = m.close;
        body.querySelector('[data-again]').onclick = function() {
          m.save(playid, events, done);
        };
      });
    };

    /**
     * The end: the world answers. What the mission says when it is won or lost, what was given, what was mixed up with
     * its why, and to play again if it was lost.
     *
     * @param {Object} result Of the server.
     * @param {Object} o won and lost (titles), art (html above), learn (list items of what was mixed up), more (html
     *   after the gifts), again (to play again).
     * @return {Element} The body, for what the game adds.
     */
    m.ending = function(result, o) {
      var game = m.game;
      var gifts = (result.items || []).map(function(name) {
        return '<li>' + T.icon('gems') + esc(t('app_gotitem', name)) + '</li>';
      });
      if (result.gems) {
        gifts.push('<li>' + T.icon('gems') + esc(t(result.gems === 1 ? 'app_encounter_gem' : 'app_encounter_gems', result.gems)) + '</li>');
      }
      m.sound(result.success ? 'win' : 'wrong');
      var art = o.art || '<span class="battle-mini" aria-hidden="true">' + T.embrollo(result.success ? 0.2 : 1) + '</span>';
      var body = m.show('<div class="nest-end ' + (result.success ? 'won' : 'lost') + '">' + art + '<h2>' +
        esc(result.success ? o.won : o.lost) + '</h2><p class="lead">' + esc(result.success ? game.win : game.lose) +
        '</p>' + (gifts.length ? '<ul class="gifts">' + gifts.join('') + '</ul>' : '') + (o.learn ? '<div class="card ' +
        'whack-learn"><h3>' + esc(t('app_whack_learn')) + '</h3><ul>' + o.learn + '</ul></div>' : '') + (o.more || '') +
        '<div class="actions">' + (result.success ? '' : '<button class="btn btn-main" data-again>' +
        esc(t('app_tryagain')) + '</button>') + '<button class="btn ' + (result.success ? 'btn-main' : 'btn-soft') +
        '" data-back>' + esc(t('app_backtomap')) + '</button></div></div>');
      body.querySelector('[data-back]').onclick = m.close;
      var again = body.querySelector('[data-again]');
      if (again) {
        again.onclick = o.again;
      }
      if (result.success && T.fx && T.fx.confetti) {
        T.fx.confetti();
      }
      return body;
    };

    m.show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_loading')) + '</div>');
    T.api('get_minigame', {courseid: cfg.courseid, game: opts.code}).then(function(g) {
      m.game = g;
      ready(g);
    }).catch(function(e) {
      m.show('<p>' + esc(e.message) + '</p>');
    });
    return m;
  };
})();
