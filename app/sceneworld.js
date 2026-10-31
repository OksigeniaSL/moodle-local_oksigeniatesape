/**
 * The place alive behind a scene: the painting of the moment at full screen, the hero and the creature of the student
 * standing in it (puppets when the art pack brings them), who speaks in front of them when the pack has their figure,
 * and the world that answers what happens: a light, a shadow, a shake, the creature that looks up or jumps for joy. What
 * is read and decided stays in the page, over it, as before; nothing of the world is read or touched.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
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
     * @return {Promise} The world: image(), speak(), answer(), ending(), ask(), say(), close(), state().
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
      // Where what is read is: a function that tells its top, left and right in pixels of the page, asked at each layout.
      var room = null;
      // The suspects standing in the place (lineup): by id, each with its picture, where it stands and whether it stepped
      // back; in their order; and what a touch on one of them does, while they can be chosen.
      var suspects = {};
      var row = [];
      var picking = null;
      // While something is looked for on the painting: told where the painting is, and where the bar on top ends.
      var searching = null;
      var bar = 0;
      var layout = function(snap) {
        var w = S.scale.width;
        var h = S.scale.height;
        var ratio = h / (window.innerHeight || h);
        var tall = h > w;
        var box = room ? room() : null;
        var top = box ? box.top * ratio : 0;
        // On a wide screen with room on both sides of what is read, the figures stand on the floor of the place, big,
        // beside it: the hero and the creature on one side, who speaks on the other, facing them.
        var side = box && !tall ? Math.min(box.left, (window.innerWidth || w / ratio) - box.right) * ratio : 0;
        var beside = side >= w * 0.17;
        var ground;
        var size;
        var heroX;
        var speakerX;
        if (beside) {
          ground = h * 0.97;
          size = Math.min(h * 0.7, side * 1.5);
          heroX = Math.max(side * 0.42, size * 0.3);
          speakerX = w - side * 0.5;
        } else {
          // Else they stand on a line above the box of words; the line goes up when the box is tall, never above a
          // third of the screen.
          ground = h * (tall ? 0.56 : 0.66);
          if (box) {
            ground = Math.max(h * 0.36, Math.min(ground, top - 6 * ratio));
          }
          size = Math.min(h * (tall ? 0.34 : 0.42), w * (tall ? 0.5 : 0.3), (ground - 64 * ratio) * 0.92);
          heroX = w * (tall ? 0.26 : 0.22);
          speakerX = w * (tall ? 0.76 : 0.74);
        }
        // Looking for something on the painting, it is seen whole between the bar on top and what is read; else, it
        // fills the screen.
        var above = (bar || 64) * ratio;
        var bottom = box ? top - 8 * ratio : h * 0.62;
        S.bgs.forEach(function(bg) {
          if (bg.texture && bg.texture.key !== '__MISSING') {
            if (searching && bottom - above > 40) {
              bg.setPosition(w / 2, above + (bottom - above) / 2).setScale(Math.min(w / bg.width, (bottom - above) /
                bg.height));
            } else {
              bg.setPosition(w / 2, h / 2).setScale(Math.max(w / bg.width, h / bg.height));
            }
          }
        });
        var painting = S.bgs[S.bgs.length - 1];
        if (searching && painting) {
          searching({left: (painting.x - painting.displayWidth / 2) / ratio,
            top: (painting.y - painting.displayHeight / 2) / ratio, width: painting.displayWidth / ratio,
            height: painting.displayHeight / ratio});
        }
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
        place(S.hero, heroX, ground, unit);
        // The creature beside the hero, in front of what is read, never under it.
        var petX = (S.hero ? S.hero.tx : heroX) + size * 0.38;
        place(S.pet, beside ? Math.min(petX, side - size * 0.14) : petX, ground - size * 0.03, size * 0.5 / (S.pet ?
          S.pet.sprite.height : 1));
        place(S.speaker, speakerX, ground, unit * (S.speaker && TALL[S.speaker.code] ? 1.1 : 1));
        // The suspects, in a row on the floor just above what is read (beside it, over its width; on a phone, behind
        // the figures, between them). When what is read grows tall, they go up with it, and smaller, up to the bar on
        // top: they are never behind it.
        if (row.length) {
          var from = beside ? box.left * ratio : w * (tall ? 0.06 : 0.3);
          var to = beside ? box.right * ratio : w * (tall ? 0.94 : 0.7);
          var line = box ? Math.max(Math.min(h * 0.3, ((bar || 64) + 40) * ratio), top - 6 * ratio) : h * 0.62;
          if (!beside) {
            line = Math.min(line, ground - size * 0.45);
          }
          var each = (to - from) / row.length;
          var high = Math.max(40 * ratio, Math.min(h * 0.24, each * 1.05, (line - 70 * ratio) * 0.75));
          row.forEach(function(id, i) {
            var one = suspects[id];
            one.tx = from + each * (i + 0.5);
            one.ty = line;
            one.size = high;
            if (snap || one.x === undefined) {
              one.x = one.tx;
              one.y = one.ty;
            }
          });
        }
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
         * Where what is read is now: the figures move to stand beside it, or above it.
         *
         * @param {Function} where Tells its top, left and right, in pixels of the page.
         */
        room: later(function(where) {
          room = where;
          layout(false);
        }),

        /**
         * Something is looked for on the painting (find): it is seen whole above what is read, the figures step aside,
         * and the scene is told where the painting is, now and whenever it moves, to put what can be touched on it.
         *
         * @param {boolean} on
         * @param {Function} [told] Gets left, top, width and height, in pixels of the page.
         * @param {number} [top] Where the bar on top of the scene ends, in pixels of the page.
         */
        search: later(function(on, told, top) {
          if (!on && !searching) {
            return;
          }
          searching = on ? told : null;
          bar = top || 0;
          row.forEach(function(id) {
            suspects[id].hidden = on;
          });
          [S.hero, S.pet, S.speaker].forEach(function(f) {
            if (f) {
              S.tweens.add({targets: [f.sprite, f.shadow], alpha: on ? 0 : 1, duration: still ? 1 : 300});
              if (f.aura) {
                f.aura.setVisible(!on);
              }
            }
          });
          layout(false);
        }),

        /**
         * What was touched on the painting: sparks where something was found, a puff of dust where it was not.
         *
         * @param {number} x In percent of the painting.
         * @param {number} y
         * @param {boolean} found
         */
        mark: later(function(x, y, found) {
          var bg = S.bgs[S.bgs.length - 1];
          if (!bg) {
            return;
          }
          var px = bg.x + (x / 100 - 0.5) * bg.displayWidth;
          var py = bg.y + (y / 100 - 0.5) * bg.displayHeight;
          if (found) {
            S.sparks.explode(still ? 4 : 18, px, py);
            return;
          }
          if (!S.puff) {
            S.puff = S.add.particles(0, 0, 'dot', {lifespan: 520, speed: {min: 20, max: 70}, scale: {start: 0.6, end: 0},
              alpha: {start: 0.55, end: 0}, tint: 0xb9c2c9, emitting: false}).setDepth(5000);
          }
          S.puff.explode(still ? 3 : 10, px, py);
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
         * The world answers what was chosen: a light, a shadow, a shake or something that grows. Who speaks and the
         * creature take it as it went: a nod and a jump of joy with a few sparks when it went well, a start and an
         * alert look when not; with neither, a nod and a look.
         *
         * @param {string} fx light, dark, shake, grow, or nothing.
         * @param {string} [mood] good, bad, or nothing.
         */
        answer: later(function(fx, mood) {
          if (S.pet) {
            T.cast.act(S.pet, mood === 'good' ? 'hop' : 'alert');
            if (mood === 'good' && !still) {
              S.sparks.explode(10, S.pet.x, S.pet.y - S.pet.sprite.displayHeight * 0.6);
            }
          }
          if (S.speaker && S.speaker.puppet) {
            T.cast.act(S.speaker, fx === 'shake' || fx === 'dark' || mood === 'bad' ? 'surprise' : 'nod');
          }
          if (S.hero && mood === 'good') {
            T.cast.act(S.hero, 'celebrate');
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
         * The suspects stand in the place (the options of a choice of candidates, each with its drawing), or go; those
         * that stepped back stay in the shade, a little behind. A suspect that steps back now does it with a puff.
         *
         * @param {Array|null} list id, url, out; null, and they go.
         */
        lineup: later(function(list) {
          if (!list) {
            row.forEach(function(id) {
              var one = suspects[id];
              S.tweens.add({targets: [one.sprite, one.shadow], alpha: 0, duration: still ? 1 : 300, onComplete: function() {
                one.sprite.destroy();
                one.shadow.destroy();
              }});
            });
            suspects = {};
            row = [];
            picking = null;
            return;
          }
          var missing = list.filter(function(one) {
            return !S.textures.exists('suspect.' + one.id);
          });
          var stand = function() {
            if (row.length && row.join() !== list.map(function(one) {
              return one.id;
            }).join()) {
              world.lineup(null);
            }
            list.forEach(function(one) {
              var was = suspects[one.id];
              if (!was) {
                var sprite = S.add.image(0, 0, 'suspect.' + one.id).setOrigin(0.5, 0.99).setAlpha(still ? 1 : 0);
                was = suspects[one.id] = {id: one.id, sprite: sprite, out: false, k: 1, a: still ? 1 : 0, hop: 0,
                  shadow: S.add.ellipse(0, 0, 10, 10, 0x0b1a10, 0.28)};
                row.push(one.id);
                sprite.on('pointerdown', function() {
                  if (picking && !was.out) {
                    picking(one.id);
                  }
                });
                sprite.on('pointerover', function() {
                  was.over = true;
                });
                sprite.on('pointerout', function() {
                  was.over = false;
                });
              }
              if (one.out && !was.out && !still && was.x !== undefined) {
                if (!S.puff) {
                  S.puff = S.add.particles(0, 0, 'dot', {lifespan: 520, speed: {min: 20, max: 70}, scale: {start: 0.6,
                    end: 0}, alpha: {start: 0.55, end: 0}, tint: 0xb9c2c9, emitting: false}).setDepth(5000);
                }
                S.puff.explode(10, was.x, was.y - (was.size || 100) * 0.4);
              }
              was.out = !!one.out;
            });
            world.pick(picking);
            layout(false);
          };
          if (missing.length) {
            missing.forEach(function(one) {
              S.load.image('suspect.' + one.id, one.url);
            });
            S.load.once('complete', stand);
            S.load.start();
          } else {
            stand();
          }
        }),

        /**
         * The suspects can be chosen with a touch, those still in the light; null, and they cannot.
         *
         * @param {Function|null} then Gets the id of the one touched.
         */
        pick: later(function(then) {
          picking = then || null;
          row.forEach(function(id) {
            var one = suspects[id];
            if (picking && !one.out) {
              one.sprite.setInteractive({useHandCursor: true, pixelPerfect: false});
            } else {
              one.sprite.disableInteractive();
            }
          });
        }),

        /**
         * The suspect chosen answers: the right one jumps in a shower of sparks, another one steps back.
         *
         * @param {string} id
         * @param {boolean} right
         */
        chosen: later(function(id, right) {
          var one = suspects[id];
          if (!one) {
            return;
          }
          if (right) {
            if (!still) {
              S.tweens.add({targets: one, hop: (one.size || 100) * 0.18, duration: 220, yoyo: true, repeat: 1,
                ease: 'Sine.easeOut'});
              S.sparks.explode(24, one.x, one.y - (one.size || 100) * 0.6);
            }
          } else {
            one.out = true;
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
         * The hero says what the student chose (a question to who speaks there): they point at them, and the creature
         * looks at the hero.
         */
        say: later(function() {
          if (S.hero) {
            T.cast.act(S.hero, 'point');
          }
          if (S.pet && S.hero) {
            S.pet.facing = Math.sign(S.hero.x - S.pet.x) || -1;
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

        /**
         * For the tests of the browser: a touch on a suspect in the place, as a finger would give it.
         *
         * @param {string} id
         * @return {boolean} Whether it could be touched.
         */
        touch: function(id) {
          var one = suspects[id];
          if (!picking || !one || one.out) {
            return false;
          }
          one.sprite.emit('pointerdown');
          return true;
        },

        /** For the tests of the browser. */
        state: function() {
          return {ready: !!S, image: shown, speaker: who, figure: !!(S && S.speaker),
            speakerPuppet: S && S.speaker && S.speaker.puppet ? S.speaker.puppet.clipName : '', rigs: cast.rigs || {},
            hero: S && S.hero && S.hero.puppet ? S.hero.puppet.clipName + (S.hero.puppet.tone ? ' ' + S.hero.puppet.tone : '') :
              '',
            pet: S && S.pet && S.pet.puppet ? S.pet.puppet.clipName : '',
            aura: !!(S && S.hero && S.hero.aura),
            paint: !!(S && S.pet && (S.pet.puppet ? S.pet.puppet.parts[0].image.texture.key :
              S.pet.sprite.texture.key).indexOf('.paint.') >= 0),
            lineup: row.map(function(id) {
              return id + (suspects[id].out ? ' fuera' : '');
            }).join(', '), picking: !!picking,
            // Where the suspects stand, in pixels of the page: the line of their feet, and the top of the tallest.
            feet: S && row.length ? suspects[row[0]].ty * (window.innerHeight || S.scale.height) / S.scale.height : 0,
            heads: S && row.length ? Math.min.apply(null, row.map(function(id) {
              return suspects[id].ty - suspects[id].size;
            })) * (window.innerHeight || S.scale.height) / S.scale.height : 0};
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
          // The suspects go to their place, step back into the shade or come out of it, and breathe.
          row.forEach(function(id, i) {
            var one = suspects[id];
            if (one.tx === undefined) {
              return;
            }
            var q = still ? 1 : k;
            one.x += (one.tx - one.x) * q;
            one.y += (one.ty - one.y) * q;
            one.k += ((one.out ? 0.82 : one.over && picking ? 1.08 : 1) - one.k) * q;
            one.a += ((one.hidden ? 0 : one.out ? 0.45 : 1) - one.a) * q;
            var base = one.size / Math.max(one.sprite.width, one.sprite.height);
            // Those that can be chosen now beat a little: the only thing that moves to ask for a touch.
            var breathe = still || one.out ? 1 : 1 + Math.sin(time / (picking ? 260 : 700) + i) * (picking ? 0.03 : 0.012);
            var back = one.out ? one.size * 0.08 : 0;
            one.sprite.setPosition(one.x, one.y - back - one.hop).setScale(base * one.k, base * one.k * breathe)
              .setAlpha(one.a).setDepth(one.y - back);
            if (one.out) {
              one.sprite.setTint(0x5a6478);
            } else {
              one.sprite.clearTint();
            }
            one.shadow.setPosition(one.x, one.y - back - 2).setSize(one.size * 0.5 * one.k, one.size * 0.07)
              .setAlpha(one.a).setDepth(one.y - back - 1);
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
