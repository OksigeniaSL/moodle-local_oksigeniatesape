/**
 * Tesapé: what every minigame shares, so that each one keeps only its mechanics. Its layer over the map, the frame with
 * its title and the close button, the loading, the pause button, the saving (kept on the device without a connection,
 * or sent again from here) and the end, where the world answers: what the mission says, what was given and what was
 * mixed up, with its why; never a count of right answers.
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

    var m = {layer: layer, world: opts.world, game: null, onclose: null};

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
        gifts.push('<li>' + T.icon('gems') + esc('+' + result.gems + ' ' + t('app_gems')) + '</li>');
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
