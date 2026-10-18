/**
 * The stage of the moments that are played with movement: the engine (Phaser) is loaded the first time one opens, and
 * the moment takes the whole screen, with the world drawn in a canvas and, over it, what is read and touched (who
 * speaks, the decisions, «See as a list» and «Leave»), in the page, so that the reader, the keyboard and the panel of
 * Access keep working. Closing it takes the engine down too. Without WebGL, or for who has «no movement», the moment
 * is played as a list, as before.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var cfg = window.TESAPE_CONFIG;
  var t = T.t;
  var esc = T.esc;
  var loading = null;
  /** @var {string} What this device remembers: the student chose to play these moments as a list. */
  var LIST = 'tesape-moments-as-list';

  /**
   * Whether this device draws with WebGL.
   *
   * @return {boolean}
   */
  function webgl() {
    try {
      var canvas = document.createElement('canvas');
      return !!(window.WebGLRenderingContext && (canvas.getContext('webgl2') || canvas.getContext('webgl')));
    } catch (e) {
      return false;
    }
  }

  /**
   * A game of the engine in an element, at the resolution of the screen, drawn again when the window changes.
   *
   * @param {Object} Phaser
   * @param {Element} parent
   * @param {Object} scene
   * @param {number} [limit] Frames a second at most (0: as the screen).
   * @param {boolean} [fit] The size of the parent, not of the window.
   * @return {Object} game, dpr, resize (the listener, to take it away).
   */
  function game(Phaser, parent, scene, limit, fit) {
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var size = function() {
      return fit ? [Math.max(1, parent.clientWidth), Math.max(1, parent.clientHeight)] : [window.innerWidth, window.innerHeight];
    };
    var made = new Phaser.Game({
      type: Phaser.AUTO,
      parent: parent,
      backgroundColor: '#0f2131',
      scale: {mode: Phaser.Scale.NONE, width: Math.round(size()[0] * dpr), height: Math.round(size()[1] * dpr), zoom: 1 / dpr},
      audio: {noAudio: true},
      banner: false,
      fps: {limit: limit || 0},
      scene: scene
    });
    var resize = function() {
      made.scale.resize(Math.round(size()[0] * dpr), Math.round(size()[1] * dpr));
    };
    window.addEventListener('resize', resize);
    return {game: made, dpr: dpr, resize: resize};
  }

  T.stage = {
    /**
     * Whether a moment can be played with movement here: WebGL, things that move, and the student did not choose the
     * list on this device.
     *
     * @return {boolean}
     */
    available: function() {
      return !T.stage.asList() && T.moves() && webgl();
    },

    /**
     * Whether the student chose to play the moments as a list on this device.
     *
     * @return {boolean}
     */
    asList: function() {
      try {
        return window.localStorage.getItem(LIST) === '1';
      } catch (e) {
        return false;
      }
    },

    /**
     * Remembers on this device how the student plays the moments.
     *
     * @param {boolean} on As a list.
     */
    setList: function(on) {
      try {
        window.localStorage.setItem(LIST, on ? '1' : '0');
      } catch (e) {
        // Only for this page.
      }
    },

    /**
     * Loads the engine, once.
     *
     * @return {Promise}
     */
    load: function() {
      if (window.Phaser) {
        return Promise.resolve(window.Phaser);
      }
      if (!loading) {
        loading = new Promise(function(resolve, reject) {
          // A page with a loader of modules would keep the engine to itself: for a moment, there is none.
          var amd = window.define && window.define.amd;
          if (amd) {
            window.define.amd = false;
          }
          var script = document.createElement('script');
          script.src = cfg.wwwroot + '/local/oksigeniatesape/app/engine/phaser.min.js?v=' + (cfg.version || 0);
          script.onload = function() {
            if (amd) {
              window.define.amd = amd;
            }
            if (window.Phaser) {
              resolve(window.Phaser);
            } else {
              loading = null;
              reject(new Error('Phaser'));
            }
          };
          script.onerror = function() {
            if (amd) {
              window.define.amd = amd;
            }
            loading = null;
            reject(new Error('Phaser'));
          };
          document.head.appendChild(script);
        });
      }
      return loading;
    },

    /**
     * Opens a moment.
     *
     * @param {Object} opts title; scene (preload, create, update: called with the scene of Phaser as this, and the
     *     moment as its `stage`); onlist (the student goes to the list); onclose (the moment is over or left).
     * @return {Promise} The moment: say(), hush(), panel(), hidePanel(), close(), toList(), still.
     */
    /**
     * A world drawn behind a moment that is read and touched in the page (a scene): only the canvas, in an element the
     * moment puts behind what it shows; nothing of it is read or touched.
     *
     * @param {Element} parent
     * @param {Object} scene preload, create, update: called with the scene of Phaser as this.
     * @param {Object} [opts] fit: the size of the parent (a field of a game) and not of the window; fps (30 by default:
     *     behind a page, figures that breathe and talk, and the tablet works less).
     * @return {Promise} game, dpr, close().
     */
    backdrop: function(parent, scene, opts) {
      opts = opts || {};
      return T.stage.load().then(function(Phaser) {
        var made = game(Phaser, parent, {key: 'backdrop', preload: scene.preload, create: scene.create, update: scene.update},
          opts.fps || 30, !!opts.fit);
        var closed = false;
        return {game: made.game, dpr: made.dpr, close: function() {
          if (closed) {
            return;
          }
          closed = true;
          window.removeEventListener('resize', made.resize);
          try {
            made.game.destroy(true);
          } catch (e) {
            // Already gone.
          }
        }};
      });
    },

    open: function(opts) {
      return T.stage.load().then(function(Phaser) {
        var back = document.activeElement;
        var layer = document.createElement('div');
        layer.className = 'stage-layer';
        layer.setAttribute('role', 'dialog');
        layer.setAttribute('aria-modal', 'true');
        layer.setAttribute('aria-labelledby', 'tesape-stage-title');
        layer.innerHTML = '<div class="stage-world" aria-hidden="true"></div><div class="stage-ui"><header class="stage-top">' +
          '<h2 id="tesape-stage-title">' + esc(opts.title || '') + '</h2><div class="stage-hud" data-hud></div>' +
          '<button type="button" class="btn btn-soft" data-stage-list>' + esc(t('app_stage_list')) + '</button>' +
          '<button type="button" class="btn btn-soft" data-stage-close aria-label="' + esc(t('app_close')) + '">' +
          T.icon('close') + '</button></header><p class="sr-only">' + esc(t('app_stage_help')) + '</p>' +
          '<div class="stage-say" role="status" aria-live="polite" data-say hidden></div>' +
          '<div class="stage-panel" data-panel hidden></div></div>';
        document.body.appendChild(layer);
        document.body.classList.add('stage-open');
        var lines = [];
        var speaking = false;
        var timer = null;
        var closed = false;
        var moment = {
          layer: layer,
          still: !T.moves(),
          /**
           * Who says what, in the box at the bottom: one line after the other, each long enough to be read.
           *
           * @param {string} who
           * @param {string} text
           * @param {boolean} quoted Said by a voice that is not seen.
           */
          say: function(who, text, quoted) {
            lines.push({who: who, text: text, quoted: quoted});
            if (!speaking) {
              next();
            }
          },
          /** What was still to be said goes: the moment moved on. */
          hush: function() {
            lines = [];
          },
          /**
           * A panel over the world, for a decision: the say box steps aside.
           *
           * @param {string} html
           * @param {string} where bottom (by default) or top, to leave the scene in view.
           * @return {Element}
           */
          panel: function(html, where) {
            var panel = layer.querySelector('[data-panel]');
            panel.setAttribute('data-where', where || 'bottom');
            panel.innerHTML = html;
            panel.hidden = false;
            layer.querySelector('[data-say]').hidden = true;
            var first = panel.querySelector('button:not([disabled])');
            if (first) {
              first.focus();
            }
            return panel;
          },
          hidePanel: function() {
            layer.querySelector('[data-panel]').hidden = true;
            layer.querySelector('[data-say]').hidden = !speaking;
          },
          /**
           * What the moment counts up there (traces found, gems), in the page.
           *
           * @param {string} html
           */
          hud: function(html) {
            layer.querySelector('[data-hud]').innerHTML = html;
          },
          /** The moment is over or left: the engine goes too. */
          close: function() {
            if (closed) {
              return;
            }
            closed = true;
            clearTimeout(timer);
            window.removeEventListener('resize', resize);
            try {
              made.game.destroy(true);
            } catch (e) {
              // Already gone.
            }
            layer.remove();
            document.body.classList.remove('stage-open');
            if (window.TesapeStage === moment) {
              window.TesapeStage = null;
            }
            if (back && back.focus) {
              back.focus();
            }
            if (opts.onclose) {
              opts.onclose();
            }
          },
          /** The same moment, as a list. */
          toList: function() {
            var onlist = opts.onlist;
            opts.onclose = null;
            moment.close();
            if (onlist) {
              onlist();
            }
          }
        };
        var next = function() {
          var line = lines.shift();
          var box = layer.querySelector('[data-say]');
          if (!line) {
            speaking = false;
            box.hidden = true;
            return;
          }
          speaking = true;
          box.hidden = !layer.querySelector('[data-panel]').hidden;
          box.innerHTML = '<span class="stage-who">' + esc(line.who) + '</span>' + (line.quoted ? '<em>«' + esc(line.text) +
            '»</em>' : '<span>' + esc(line.text) + '</span>');
          timer = setTimeout(next, Math.max(2200, line.text.length * 55));
        };
        layer.querySelector('[data-stage-close]').onclick = moment.close;
        layer.querySelector('[data-stage-list]').onclick = function() {
          T.stage.setList(true);
          moment.toList();
        };
        layer.addEventListener('keydown', function(ev) {
          if (ev.key === 'Escape') {
            moment.close();
          }
        });
        var scene = {
          key: 'moment',
          preload: function() {
            this.stage = moment;
            if (opts.scene.preload) {
              opts.scene.preload.call(this);
            }
          },
          create: function() {
            this.stage = moment;
            opts.scene.create.call(this);
          },
          update: function(time, delta) {
            if (opts.scene.update) {
              opts.scene.update.call(this, time, delta);
            }
          }
        };
        var made = game(Phaser, layer.querySelector('.stage-world'), scene);
        var resize = made.resize;
        moment.game = made.game;
        moment.dpr = made.dpr;
        // For the tests of the browser: the moment open now.
        window.TesapeStage = moment;
        layer.querySelector('[data-stage-list]').focus();
        return moment;
      });
    }
  };
})();
