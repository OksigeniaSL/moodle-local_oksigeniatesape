/**
 * The place alive behind a scene: the painting of the moment at full screen, the hero and the creature of the student
 * standing in it (puppets when the art pack brings them), who speaks in front of them when the pack has their figure,
 * and the world that answers what happens: a light, a shadow, a shake, the creature that looks up or jumps for joy. What
 * is read and decided stays in the page, over it, as before; nothing of the world is read or touched.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  /** @var {Object} Who is drawn as a grown-up, a little taller than the hero. */
  var TALL = {'druida-mayor': true, 'bibliotecaria': true, 'jardinera': true, 'barquero': true};
  /** @var {Object} Who is a bird: their puppet has the body of a bird (rig.bird), the others that of a speaker. */
  var BIRD = {buho: true, garza: true};

  T.sceneWorld = {
    /**
     * Whether a scene can have its place alive here.
     *
     * @return {boolean}
     */
    can: function() {
      return !!(T.stage && T.cast && T.stage.available());
    },

    /**
     * Opens the place behind a scene.
     *
     * @param {Element} parent Where the canvas goes, behind the scene.
     * @param {Object} opts world (with its character), image (the address of the painting), still.
     * @return {Promise} The world: image(), speak(), answer(), ending(), ask(), close(), state().
     */
    open: function(parent, opts) {
      var c = opts.world && opts.world.character;
      var still = !!opts.still;
      var S = null;
      var cast = {};
      var shown = opts.image;
      var who = null;
      var speaking = false;
      var hush = 0;
      var pending = [];
      // Until the world is ready, what it is asked to do waits.
      var later = function(f) {
        return function() {
          var args = arguments;
          if (S) {
            f.apply(null, args);
          } else {
            pending.push(function() {
              f.apply(null, args);
            });
          }
        };
      };
      // Where what is read starts, in pixels of the page: the figures stand above it.
      var room = 0;
      var layout = function(snap) {
        var w = S.scale.width;
        var h = S.scale.height;
        var ratio = h / (window.innerHeight || h);
        var tall = h > w;
        // The figures stand on a line above the box of words; the hero and the creature on one side, who speaks on the
        // other, facing them. The line goes up when the box is tall, never above a third of the screen.
        var ground = h * (tall ? 0.56 : 0.66);
        if (room) {
          ground = Math.max(h * 0.36, Math.min(ground, room * ratio - 6 * ratio));
        }
        var size = Math.min(h * (tall ? 0.34 : 0.42), w * (tall ? 0.5 : 0.3), (ground - 64 * ratio) * 0.92);
        S.bgs.forEach(function(bg) {
          if (bg.texture && bg.texture.key !== '__MISSING') {
            bg.setPosition(w / 2, h / 2).setScale(Math.max(w / bg.width, h / bg.height));
          }
        });
        S.ground = ground;
        var place = function(f, x, y, base) {
          if (!f) {
            return;
          }
          f.tx = x;
          f.ty = y;
          f.base = base;
          if (snap || f.x === 0) {
            f.x = x;
            f.y = y;
          }
        };
        // The figures are drawn on one canvas (768 × 896, the feet in the same place): the same scale keeps their
        // heights; the grown-ups who speak, a little taller than the hero (as Gaudí draws them).
        var unit = size / 896;
        place(S.hero, w * (tall ? 0.26 : 0.22), ground, unit);
        place(S.pet, (S.hero ? S.hero.tx : w * 0.22) + size * 0.38, ground - size * 0.03, size * 0.5 / (S.pet ?
          S.pet.sprite.height : 1));
        place(S.speaker, w * (tall ? 0.76 : 0.74), ground, unit * (S.speaker && TALL[S.speaker.code] ? 1.1 : 1));
      };
      var world = {
        /**
         * The painting of the moment changes: it fades into the new one.
         *
         * @param {string|null} url
         */
        image: later(function(url) {
          if (!url || url === shown) {
            return;
          }
          shown = url;
          var key = 'bg.' + (S.bgCount = (S.bgCount || 0) + 1);
          S.load.image(key, url);
          S.load.once('complete', function() {
            var old = S.bgs[S.bgs.length - 1];
            var bg = S.add.image(0, 0, key).setDepth(-10).setAlpha(still ? 1 : 0);
            S.bgs.push(bg);
            layout(false);
            S.tweens.add({targets: bg, alpha: 1, duration: still ? 1 : 450, onComplete: function() {
              if (old) {
                old.destroy();
                S.bgs = S.bgs.filter(function(b) {
                  return b !== old;
                });
              }
            }});
          });
          S.load.start();
        }),

        /**
         * Who speaks now: their figure comes in front of the hero, if the pack has it, and moves while the words show;
         * nobody (the narrator), and whoever was there stays quiet.
         *
         * @param {string|null} code
         * @param {string|null} url The address of their figure.
         */
        speak: later(function(code, url) {
          if (code === who) {
            return;
          }
          who = code;
          if (S.speaker) {
            var leaving = S.speaker;
            S.speaker = null;
            S.tweens.add({targets: [leaving.sprite, leaving.shadow], alpha: 0, duration: still ? 1 : 250, onComplete: function() {
              leaving.sprite.destroy();
              leaving.shadow.destroy();
            }});
          }
          if (!url) {
            return;
          }
          var key = 'figure.' + code;
          // Their puppet, cut from that same figure, if the pack brings it: then they talk, nod and greet.
          var rig = T.puppet ? T.puppet.find(key, BIRD[code] ? 'bird' : 'speaker') : null;
          var name = 'puppet.' + key;
          var enter = function() {
            if (who !== code) {
              return;
            }
            var puppet = rig && T.puppet.make(S, name, still);
            var sprite = puppet ? puppet.container : S.add.image(0, 0, key).setOrigin(0.5, 860 / 896);
            S.speaker = {code: code, x: 0, y: 0, sprite: sprite, puppet: puppet,
              shadow: S.add.ellipse(0, 0, 10, 10, 0x0b1a10, 0.32), base: 1, walk: 0, moving: false, facing: 1, lift: 0,
              tilt: 0, talk: 0};
            layout(true);
            if (puppet) {
              puppet.play('greet', true);
            }
            if (!still) {
              sprite.setAlpha(0);
              S.tweens.add({targets: sprite, alpha: 1, duration: 300});
            }
          };
          if (rig ? S.textures.exists(name) : S.textures.exists(key)) {
            enter();
          } else {
            if (rig) {
              T.puppet.load(S, name, rig);
            } else {
              S.load.image(key, url);
            }
            S.load.once('complete', enter);
            S.load.start();
          }
          if (S.hero) {
            S.hero.facing = 1;
          }
          if (S.pet) {
            S.pet.facing = 1;
          }
        }),

        /**
         * Where what is read starts now, in pixels of the page: the figures move to stand above it.
         *
         * @param {number} top
         */
        room: later(function(top) {
          room = top;
          layout(false);
        }),

        /**
         * Who speaks moves for as long as the words take to read; 0, and they go quiet.
         *
         * @param {number} ms
         */
        talk: later(function(ms) {
          clearTimeout(hush);
          speaking = ms > 0;
          if (speaking) {
            hush = setTimeout(function() {
              speaking = false;
            }, ms);
          }
        }),

        /**
         * The world answers what was chosen: a light, a shadow, a shake or something that grows; the creature looks.
         *
         * @param {string} fx light, dark, shake, grow, or nothing.
         */
        answer: later(function(fx) {
          if (S.pet) {
            T.cast.act(S.pet, 'alert');
          }
          if (S.speaker && S.speaker.puppet) {
            T.cast.act(S.speaker, fx === 'shake' || fx === 'dark' ? 'surprise' : 'nod');
          }
          if (still) {
            return;
          }
          var cam = S.cameras.main;
          if (fx === 'light') {
            cam.flash(500, 255, 241, 189);
          } else if (fx === 'dark') {
            cam.fade(260, 10, 18, 30, false, function(c, p) {
              if (p >= 1) {
                cam.fadeIn(600, 10, 18, 30);
              }
            });
          } else if (fx === 'shake') {
            cam.shake(320, 0.006);
          } else if (fx === 'grow') {
            S.tweens.add({targets: cam, zoom: 1.05, duration: 420, yoyo: true, ease: 'Sine.easeInOut'});
          }
        }),

        /**
         * The creature sniffs out an option that is not it: it sniffs for a moment, and then looks up.
         */
        sniff: later(function() {
          if (S.pet) {
            S.pet.pose = 'sniff';
            S.time.delayedCall(still ? 0 : 1600, function() {
              S.pet.pose = null;
              T.cast.act(S.pet, 'alert');
            });
          }
        }),

        /**
         * A help is opened: the hero points at what it shows.
         */
        hint: later(function() {
          if (S.hero) {
            T.cast.act(S.hero, 'point');
          }
        }),

        /**
         * A decision is asked: the hero thinks, and the creature looks at them.
         */
        ask: later(function() {
          if (S.pet && S.hero) {
            S.pet.facing = Math.sign(S.hero.x - S.pet.x) || -1;
          }
        }),

        /**
         * How the scene ends: joy for a good ending, a warm moment halfway, and the creature that comforts if not.
         *
         * @param {string} outcome success, partial or other.
         */
        ending: later(function(outcome) {
          if (outcome === 'success') {
            if (S.hero) {
              T.cast.act(S.hero, 'celebrate');
            }
            if (S.speaker && S.speaker.puppet) {
              T.cast.act(S.speaker, 'laugh');
            }
            if (S.pet) {
              S.pet.pose = 'happy';
              T.cast.act(S.pet, 'hop');
            }
            S.sparks.explode(still ? 6 : 30, S.hero ? S.hero.x : S.scale.width / 2, S.ground - S.scale.height * 0.3);
          } else if (outcome === 'partial') {
            if (S.pet) {
              S.pet.pose = 'happy';
            }
          } else if (S.pet) {
            T.cast.act(S.pet, 'hop');
          }
        }),

        /** For the tests of the browser. */
        state: function() {
          return {ready: !!S, image: shown, speaker: who, figure: !!(S && S.speaker),
            speakerPuppet: S && S.speaker && S.speaker.puppet ? S.speaker.puppet.clipName : '', rigs: cast.rigs || {},
            hero: S && S.hero && S.hero.puppet ? S.hero.puppet.clipName + (S.hero.puppet.tone ? ' ' + S.hero.puppet.tone : '') :
              '',
            pet: S && S.pet && S.pet.puppet ? S.pet.puppet.clipName : '',
            aura: !!(S && S.hero && S.hero.aura),
            paint: !!(S && S.pet && S.pet.sprite.filters && S.pet.sprite.filters.internal.list.length)};
        },
        close: function() {
          pending = [];
        }
      };
      return T.stage.backdrop(parent, {
        preload: function() {
          if (opts.image) {
            this.load.image('bg.0', opts.image);
          }
          if (c && c.role) {
            cast = T.cast.load(this, c, opts.world.family);
          }
        },
        create: function() {
          S = this;
          S.bgs = [];
          if (S.textures.exists('bg.0')) {
            S.bgs.push(S.add.image(0, 0, 'bg.0').setDepth(-10));
          }
          T.cast.textures(S);
          if (c && c.role) {
            S.hero = T.cast.actor(S, 'hero', 0, 0, 1, still);
            T.cast.aura(S, S.hero, c.aura, still);
            if (c.creature) {
              var pet = T.cast.creature(c);
              S.pet = T.cast.actor(S, 'pet', 0, 0, 1, still, pet.hue, pet.paint);
            }
          }
          S.sparks = S.add.particles(0, 0, 'spark', {lifespan: 1000, speed: {min: 80, max: 280}, scale: {start: 0.9, end: 0},
            alpha: {start: 1, end: 0}, tint: [0xfff1bd, 0xffc857, 0xe8508f], gravityY: 180, emitting: false}).setDepth(5000);
          layout(true);
          S.scale.on('resize', function() {
            layout(true);
          });
          pending.forEach(function(f) {
            f();
          });
          pending = [];
        },
        update: function(time, delta) {
          if (!S) {
            return;
          }
          var dt = Math.min(delta, 50) / 1000;
          var k = Math.min(1, dt * 6);
          [S.hero, S.pet, S.speaker].forEach(function(f) {
            if (f && f.tx !== undefined) {
              f.x += (f.tx - f.x) * k;
              f.y += (f.ty - f.y) * k;
            }
          });
          [S.hero, S.pet].forEach(function(f) {
            if (f) {
              T.cast.move(S, f, 1, time, dt, 7, still);
            }
          });
          var sp = S.speaker;
          if (sp && sp.puppet) {
            // A puppet talks while the words show, and listens after.
            if (!sp.puppet.busy()) {
              sp.puppet.play(speaking ? 'talk' : 'idle');
            }
            sp.puppet.draw(dt);
            sp.sprite.setPosition(sp.x, sp.y).setScale(sp.base, sp.base).setDepth(sp.y);
            sp.shadow.setPosition(sp.x, sp.y - 2).setSize(sp.sprite.displayHeight * 0.4, sp.sprite.displayHeight * 0.08)
              .setDepth(sp.y - 1);
          } else if (sp) {
            // Who speaks moves while the words show: a breath, and a little bounce at each phrase.
            sp.talk += speaking && !still ? dt * 7 : 0;
            var bounce = speaking && !still ? Math.abs(Math.sin(sp.talk)) * 6 : 0;
            var breathe = 1 + Math.sin(time / 600) * 0.012;
            sp.sprite.setPosition(sp.x, sp.y - bounce).setScale(sp.base, sp.base * breathe).setDepth(sp.y);
            sp.shadow.setPosition(sp.x, sp.y - 2).setSize(sp.sprite.displayHeight * 0.4, sp.sprite.displayHeight * 0.08)
              .setDepth(sp.y - 1);
          }
        }
      }).then(function(made) {
        world.close = function() {
          pending = [];
          clearTimeout(hush);
          made.close();
        };
        return world;
      });
    }
  };
})();
