/**
 * The field of Defensa del nido, drawn: the forest behind, the nest with the creature of the student in it, the animals
 * coming down with their own drawing, swaying a little as they come, and the nets that fly from the nest. The right
 * net wraps the animal and takes it away; a wrong one, or none, and the animal slips in: the creature gets a fright and
 * the nest shakes. What is read and touched stays in the page over it (the name of each animal, which can be aimed at
 * with a touch or the keyboard, the nets, the warmth of the nest); the game counts as before.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  /** @var {number} Width of the painted nest on the field, in pixels of the page. */
  var NEST = 170;

  /**
   * The key of the drawing of an animal by the name the student sees (caballito de mar: being.caballitodemar), as the
   * beings of the encounters are named: the same animal has one drawing in every game, and each regional variant (lince
   * ibérico, yaguareté) its own.
   *
   * @param {string} name
   * @return {string}
   */
  function drawing(name) {
    return 'being.' + String(name || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z]/g, '');
  }

  T.nestWorld = {
    drawing: drawing,

    /**
     * Whether the field can be drawn here.
     *
     * @return {boolean}
     */
    can: function() {
      return !!(T.stage && T.cast && T.stage.available());
    },

    /**
     * Draws the field in the arena of the game.
     *
     * @param {Element} arena
     * @param {Object} opts world (with its character), place (the code of the place, for its painting).
     * @return {Promise} The field: add(), aim(), caught(), slipped(), close(), state().
     */
    open: function(arena, opts) {
      var c = opts.world && opts.world.character;
      var S = null;
      var holder = document.createElement('div');
      holder.className = 'nest-world';
      holder.setAttribute('aria-hidden', 'true');
      arena.insertBefore(holder, arena.firstChild);
      arena.classList.add('live');
      // Each animal on the field, by the object the game keeps for it.
      var animals = [];
      var counts = {caught: 0, slipped: 0};
      // What comes before the field is drawn, drawn when it is.
      var waiting = [];
      var aimedNow = null;
      var size = function() {
        return {w: S.scale.width, h: S.scale.height, ratio: S.scale.height / Math.max(1, arena.clientHeight)};
      };
      // Where the game puts an animal (its name in the page), and its drawing just above it.
      var spot = function(l) {
        var z = size();
        var left = parseFloat(l.el.style.left) || 50;
        var top = parseFloat(l.el.style.top) || 4;
        return {x: z.w * left / 100, y: z.h * top / 100 - 4 * z.ratio};
      };
      var nest = function() {
        var z = size();
        return {x: z.w / 2, y: z.h - 10 * z.ratio};
      };
      var find = function(l) {
        return animals.filter(function(a) {
          return a.l === l;
        })[0];
      };
      var layout = function() {
        var z = size();
        if (S.bg) {
          S.bg.setPosition(z.w / 2, z.h / 2).setScale(Math.max(z.w / S.bg.width, z.h / S.bg.height));
        }
        var n = nest();
        S.nestShape.setPosition(n.x, n.y);
        S.nestShape.setScale(z.ratio);
        if (S.nestFront) {
          S.nestFront.setPosition(n.x, n.y).setScale(z.ratio * NEST / 560);
        }
        if (S.pet) {
          // Sitting in the painted nest (its front edge hides its feet); on the drawn one, on top.
          S.pet.x = n.x;
          S.pet.y = n.y - (S.textures.exists('stage.nest') ? 235 * NEST / 560 * 0.66 : 16) * z.ratio;
          S.pet.base = Math.min(z.h * 0.26, 110 * z.ratio) / S.pet.sprite.height;
        }
      };
      /**
       * A net flies from the nest to an animal.
       *
       * @param {Object} a
       * @param {boolean} hits
       * @param {Function} done
       */
      var fly = function(a, hits, done) {
        var n = nest();
        var to = spot(a.l);
        var z = size();
        var net = S.add.image(n.x, n.y - 40 * z.ratio, 'net').setScale(0.4 * z.ratio).setDepth(30);
        var miss = hits ? 0 : (to.x < z.w / 2 ? 1 : -1) * 70 * z.ratio;
        S.tweens.add({targets: net, x: to.x + miss, y: to.y - 30 * z.ratio, scale: z.ratio, angle: 200, duration: 260,
          ease: 'Sine.easeOut', onComplete: function() {
            if (hits) {
              net.destroy();
            } else {
              S.tweens.add({targets: net, alpha: 0, y: net.y - 40 * z.ratio, duration: 300, onComplete: function() {
                net.destroy();
              }});
            }
            done();
          }});
      };
      var field = {
        /**
         * An animal comes onto the field.
         *
         * @param {Object} l The animal as the game keeps it: animal (id), el (its name in the page), y.
         */
        add: function(l) {
          if (!S) {
            waiting.push(l);
            return;
          }
          var key = T.artKey([drawing(l.animal.label), 'being.' + l.animal.id]) || drawing(l.animal.label);
          var make = function() {
            if (find(l) || l.done) {
              return;
            }
            var z = size();
            var tex = S.textures.exists(key) ? key : 'paw';
            var img = S.add.image(0, 0, tex).setOrigin(0.5, 1).setDepth(10);
            img.setScale((tex === 'paw' ? 0.9 : 74 / img.height) * z.ratio);
            var a = {l: l, img: img, sway: Math.random() * 6, aimed: aimedNow === l, gone: false};
            animals.push(a);
            var p = spot(l);
            img.setPosition(p.x, p.y);
            if (!opts.still) {
              img.setAlpha(0);
              S.tweens.add({targets: img, alpha: 1, duration: 300});
            }
          };
          var url = T.artUrl([key]);
          if (url && !S.textures.exists(key)) {
            S.load.image(key, url);
            S.load.once('complete', make);
            S.load.start();
          } else {
            make();
          }
        },

        /**
         * The animal aimed at: a ring of light under it.
         *
         * @param {Object|null} l
         */
        aim: function(l) {
          aimedNow = l;
          animals.forEach(function(a) {
            a.aimed = a.l === l;
          });
        },

        /**
         * The right net: it wraps the animal, and both go up and away; the creature jumps for joy.
         *
         * @param {Object} l
         */
        caught: function(l) {
          var a = find(l);
          counts.caught++;
          if (!a || !S) {
            return;
          }
          a.gone = true;
          fly(a, true, function() {
            var z = size();
            var wrap = S.add.image(a.img.x, a.img.y - a.img.displayHeight / 2, 'net').setScale(z.ratio * 1.1).setDepth(11)
              .setAlpha(0.85);
            S.sparks.explode(opts.still ? 4 : 14, a.img.x, a.img.y - a.img.displayHeight / 2);
            S.tweens.add({targets: [a.img, wrap], y: '-=' + (90 * z.ratio), alpha: 0, duration: 700, ease: 'Sine.easeIn',
              onComplete: function() {
                a.img.destroy();
                wrap.destroy();
              }});
            if (S.pet) {
              T.cast.act(S.pet, 'hop');
            }
          });
        },

        /**
         * The animal slips in (a wrong net, or none): it runs down to the nest, the creature gets a fright, the nest
         * shakes.
         *
         * @param {Object} l
         * @param {boolean} thrown A net was thrown, and missed.
         */
        slipped: function(l, thrown) {
          var a = find(l);
          counts.slipped++;
          if (!a || !S) {
            return;
          }
          a.gone = true;
          var run = function() {
            var n = nest();
            S.tweens.add({targets: a.img, x: n.x + (a.img.x < n.x ? -60 : 60) * size().ratio, y: n.y + 30 * size().ratio,
              alpha: 0, duration: opts.still ? 1 : 520, ease: 'Quad.easeIn', onComplete: function() {
                a.img.destroy();
              }});
            if (S.pet) {
              T.cast.act(S.pet, 'alert');
            }
            if (!opts.still) {
              S.cameras.main.shake(260, 0.008);
              S.tweens.add({targets: S.nestShape, angle: {from: -4, to: 4}, duration: 70, yoyo: true, repeat: 3,
                onComplete: function() {
                  S.nestShape.setAngle(0);
                }});
            }
          };
          if (thrown) {
            fly(a, false, run);
          } else {
            run();
          }
        },

        /** For the tests of the browser. */
        state: function() {
          return {ready: !!S, animals: animals.filter(function(a) {
            return !a.gone;
          }).length, caught: counts.caught, slipped: counts.slipped, pet: S && S.pet && S.pet.puppet ?
            S.pet.puppet.clipName : ''};
        },
        close: function() {
          arena.classList.remove('live');
          holder.remove();
        }
      };
      return T.stage.backdrop(holder, {
        preload: function() {
          var art = T.artUrl(['scene.' + (opts.place || 'bosque')]);
          if (art) {
            this.load.image('bg', art);
          }
          // The nest painted, and its front edge apart, so that the creature sits inside it.
          ['stage.nest', 'stage.nest.front'].forEach(function(key) {
            if (T.artUrl([key])) {
              this.load.image(key, T.artUrl([key]));
            }
          }, this);
          if (c && c.creature) {
            T.cast.load(this, {creature: c.creature, creaturestage: c.creaturestage});
          }
        },
        create: function() {
          S = this;
          T.cast.textures(S);
          if (S.textures.exists('bg')) {
            S.bg = S.add.image(0, 0, 'bg').setDepth(-10).setTint(0xb8c4cc);
          }
          // The nest: painted, or woven twigs drawn here.
          var parts = [];
          if (S.textures.exists('stage.nest')) {
            parts.push(S.add.image(0, 0, 'stage.nest').setOrigin(0.5, 1).setScale(NEST / 560));
          } else {
            var g = S.add.graphics();
            g.fillStyle(0x5a3a1e, 1).fillEllipse(0, 0, 150, 46);
            g.lineStyle(3, 0x8a5a2b, 1);
            for (var i = 0; i < 9; i++) {
              g.beginPath();
              g.arc(0, 4, 62 - i * 3, Math.PI * (0.05 + i * 0.02), Math.PI * (0.95 - i * 0.02));
              g.strokePath();
            }
            g.lineStyle(2, 0xb07a3c, 0.9).strokeEllipse(0, -6, 140, 30);
            parts.push(g);
          }
          S.nestShape = S.add.container(0, 0, parts).setDepth(4);
          if (S.textures.exists('stage.nest.front')) {
            S.nestFront = S.add.image(0, 0, 'stage.nest.front').setOrigin(0.5, 1).setDepth(6);
          }
          // The net: a round mesh with its ring.
          var m = S.add.graphics();
          m.lineStyle(3, 0xfff1bd, 1).strokeCircle(40, 40, 36);
          m.lineStyle(1.5, 0xfff1bd, 0.8);
          for (var k = -2; k <= 2; k++) {
            m.lineBetween(40 + k * 14, 6, 40 + k * 14, 74);
            m.lineBetween(6, 40 + k * 14, 74, 40 + k * 14);
          }
          m.generateTexture('net', 80, 80);
          m.clear();
          // An animal without a drawing yet: a paw print.
          m.fillStyle(0xf3ead4, 1).fillCircle(32, 44, 16).fillCircle(16, 22, 7).fillCircle(32, 14, 7).fillCircle(48, 22, 7);
          m.generateTexture('paw', 64, 64);
          m.destroy();
          if (c && c.creature) {
            var pet = T.cast.creature(c);
            S.pet = T.cast.actor(S, 'pet', 0, 0, 1, !!opts.still, pet.hue, pet.paint);
          }
          S.ring = S.add.ellipse(0, 0, 70, 20).setStrokeStyle(3, 0xffc857, 1).setDepth(9).setVisible(false);
          S.sparks = S.add.particles(0, 0, 'spark', {lifespan: 800, speed: {min: 60, max: 200}, scale: {start: 0.8, end: 0},
            alpha: {start: 1, end: 0}, tint: [0xfff1bd, 0xffc857], emitting: false}).setDepth(40);
          layout();
          S.scale.on('resize', layout);
          waiting.forEach(function(l) {
            if (!l.done) {
              field.add(l);
            }
          });
          waiting = [];
        },
        update: function(time, delta) {
          if (!S) {
            return;
          }
          var dt = Math.min(delta, 50) / 1000;
          var aimed = null;
          animals.forEach(function(a) {
            if (a.gone) {
              return;
            }
            // Down as the game says, swaying a little, like the invaders of the old games.
            var p = spot(a.l);
            var sway = opts.still ? 0 : Math.sin(time / 380 + a.sway) * 10 * size().ratio;
            a.img.setPosition(p.x + sway, p.y);
            if (a.aimed) {
              aimed = a;
            }
          });
          S.ring.setVisible(!!aimed);
          if (aimed) {
            S.ring.setPosition(aimed.img.x, aimed.img.y).setScale(size().ratio);
          }
          if (S.pet) {
            T.cast.move(S, S.pet, 1, time, dt, 12, !!opts.still);
            // Inside the nest: over its back, under its front edge.
            S.pet.sprite.setDepth(5);
            S.pet.shadow.setDepth(4.5);
          }
        }
      }, {fit: true, fps: 60}).then(function(made) {
        var close = field.close;
        field.close = function() {
          close();
          made.close();
        };
        return field;
      }).catch(function(e) {
        field.close();
        throw e;
      });
    }
  };
})();
