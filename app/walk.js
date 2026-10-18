/**
 * The encounter of the day, walked: the hero goes through the place, the creature runs ahead and sniffs out the traces,
 * a voice from the grass says a clue at each one, and something rustles in a bush. Next to the bush the student says
 * who it is. A wrong name is crossed out and the being flees to the other bush, and the creature finds another clue if
 * there is one left: the encounter is never lost. The place comes from the mission (`stages`); the server decides and
 * gives what it gives (`answer_encounter`), as in the encounter as a list, which is still there for who wants it.
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

  /** @var {number} Width and height of the pictures of the places. */
  var W = 1440;
  var H = 810;
  /** @var {number} How near the hero has to be of a trace or of the bush. */
  var NEAR = 85;
  /** @var {number} Pixels a second the hero walks. */
  var SPEED = 270;
  /** @var {Object} The beings that have a picture among the critters of the valley, until they have their own. */
  var CRITTER = {caracol: 'critter.snail', hormiga: 'critter.ant.1', rana: 'critter.frog', mariposa: 'critter.butterfly.1',
    luciernaga: 'critter.firefly', murcielago: 'critter.bat.1', arana: 'critter.spider'};

  /**
   * Whether a point is on the ground of the place.
   *
   * @param {number[][]} poly
   * @param {number} x
   * @param {number} y
   * @return {boolean}
   */
  function inside(poly, x, y) {
    var yes = false;
    for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      var xi = poly[i][0];
      var yi = poly[i][1];
      var xj = poly[j][0];
      var yj = poly[j][1];
      if ((yi > y) !== (yj > y) && x < (xj - xi) * (y - yi) / (yj - yi) + xi) {
        yes = !yes;
      }
    }
    return yes;
  }

  /**
   * The nearest point of the ground to where the student touched.
   *
   * @param {Object} place
   * @param {number} x
   * @param {number} y
   * @return {Object} x, y.
   */
  function onGround(place, x, y) {
    if (inside(place.walk, x, y)) {
      return {x: x, y: y};
    }
    var best = {x: place.start[0], y: place.start[1]};
    var gap = Infinity;
    for (var i = 0; i < place.walk.length; i++) {
      var a = place.walk[i];
      var b = place.walk[(i + 1) % place.walk.length];
      var len = Math.pow(b[0] - a[0], 2) + Math.pow(b[1] - a[1], 2) || 1;
      var k = Math.max(0, Math.min(1, ((x - a[0]) * (b[0] - a[0]) + (y - a[1]) * (b[1] - a[1])) / len));
      var px = a[0] + k * (b[0] - a[0]);
      var py = a[1] + k * (b[1] - a[1]);
      var d = Math.hypot(x - px, y - py);
      if (d < gap) {
        gap = d;
        best = {x: px, y: py};
      }
    }
    // A step inside, towards where the student came in.
    var inward = {x: best.x + (place.start[0] - best.x) * 0.04, y: best.y + (place.start[1] - best.y) * 0.04};
    return inside(place.walk, inward.x, inward.y) ? inward : {x: place.start[0], y: place.start[1]};
  }

  function distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
  }

  function point(p) {
    return {x: p[0], y: p[1]};
  }

  /**
   * A sound of the art, if the pack has it; else the one drawn by the game, if any.
   *
   * @param {string} name
   * @param {string} fallback
   */
  function sound(name, fallback) {
    if (T.fx && !T.fx.sound(name) && fallback) {
      T.fx.sound(fallback);
    }
  }

  /**
   * A line said aloud, if the pack has its voice; the text goes in the box all the same.
   *
   * @param {string} key voice.<…>
   * @param {Function} [end] When it has been said, or at once without it.
   */
  function voice(key, end) {
    if (!T.fx || !T.fx.voice(key, end)) {
      if (end) {
        end();
      }
    }
  }

  /**
   * The picture of the hero of the student, as the game draws them: its key in the pack.
   *
   * @param {Object} c The character.
   * @return {string|null}
   */
  function heroArt(c) {
    var look = {};
    try {
      look = JSON.parse(c.appearance || '{}') || {};
    } catch (e) {
      look = {};
    }
    return T.artKey(T.characterKeys(T.family || 'storyheroes', c.role, c.lineage, c.nameform === 'f' ? 'f' : 'm',
      c.stage || 1, T.SKINS.indexOf(look.skin), false));
  }

  /**
   * The picture of the creature of the student, and its hue.
   *
   * @param {Object} c The character.
   * @return {Object} key, url, hue, name.
   */
  function creatureArt(c) {
    var traits = {};
    try {
      traits = JSON.parse(c.creature || '{}') || {};
    } catch (e) {
      traits = {};
    }
    var st = Math.max(1, Math.min(5, c.creaturestage || 1));
    var low = Math.min(4, st);
    var coat = traits.coat || 'none';
    var key = T.artKey(['creature.' + st + '.' + coat, 'creature.' + st, 'creature.' + low + '.' + coat, 'creature.' + low,
      'creature']);
    return {key: key, url: key ? T.artUrl([key]) : null, hue: Number(traits.hue) || 0, name: traits.name || ''};
  }

  T.walk = {
    /**
     * Whether a place can be walked for its encounter here.
     *
     * @param {Object} ch The place, as the world gives it.
     * @return {boolean}
     */
    can: function(ch) {
      return !!(ch && ch.stage && ch.encounter && T.stage && T.stage.available() && T.artUrl(['scene.' + ch.code]));
    },

    /**
     * Walks the place to find the being of the day.
     *
     * @param {Object} world
     * @param {Object} ch The place.
     * @param {function} aslist Plays it as a list instead, with what was found so far.
     * @param {function} done When it is over: the world is asked again.
     * @return {Promise}
     */
    encounter: function(world, ch, aslist, done) {
      var e = ch.encounter;
      var place = JSON.parse(ch.stage);
      var c = world.character;
      var pet = creatureArt(c);
      var hero = heroArt(c);
      // The puppets cut from those same pictures, if the pack brings them: then they move by pieces.
      var rigs = T.puppet ? {hero: T.puppet.find(hero, 'hero'), pet: T.puppet.find(pet.key, 'creature')} : {};
      var being = e.picture.replace(/^being\./, '');
      var skin = world.mission && world.mission.skin && world.mission.skin !== 'valley' ? '.' + world.mission.skin : '';
      // What the hidden being says at each trace, in the voice of the pack (the one of its skin, if it has one).
      var clueVoice = function(i) {
        return 'voice.encounter.' + being + '.' + (i + 1) + skin;
      };
      var step = place.ground === 'wood' ? 'step.wood' : 'step.grass';
      // Where it hides: the bush, or the hideout of the place (books, ferns, reeds, rocks) if the pack brings it.
      var hideout = place.hide && T.artUrl(['stage.hide.' + place.hide + '.still']) ? 'stage.hide.' + place.hide : 'stage.bush';
      var bits = {books: [0xf3ead2, 0xd8c79f, 0xb98b5e], rocks: [0x8a8478, 0xa59d8c, 0x6f6a60]}[place.hide] ||
        [0x2f5b46, 0x3f7454, 0x6f8f5a, 0xe8508f];
      var petname = pet.name || t('app_stage_creature');
      var hides = place.hides.map(point);
      var traces = place.traces.map(point);
      var state = {found: Math.min(1 + (e.crossed || []).length, e.clues.length) - 1, crossed: (e.crossed || []).slice(),
        phase: 'intro', busy: false, solved: false, hide: 0, prompted: false, last: 0, hinted: -1};
      var moment = null;
      var scene = null;
      var hud = function() {
        if (!moment) {
          return;
        }
        moment.hud('<span class="stage-prints" role="img" aria-label="' +
          esc(t('app_stage_found', state.found + '/' + traces.length)) + '">' + traces.map(function(tr, i) {
            return '<i class="' + (i < state.found ? 'on' : '') + '"></i>';
          }).join('') + '</span>');
      };
      var choose = function() {
        if (state.solved || state.busy || !state.found) {
          return;
        }
        var opts = e.options.map(function(o) {
          var out = state.crossed.indexOf(o.id) >= 0;
          return '<button type="button" class="btn btn-soft' + (out ? ' crossed' : '') + '" data-who="' + esc(o.id) + '"' +
            (out ? ' disabled' : '') + '>' + (out ? '<s>' + esc(o.name) + '</s>' : esc(o.name)) + '</button>';
        }).join('');
        var panel = moment.panel('<h3>' + esc(t('app_encounter_who')) + '</h3><p class="help-soft">' +
          esc(t('app_stage_clues', state.found + '/' + traces.length)) + '</p><div class="encounter-options">' + opts +
          '</div><div class="actions"><button type="button" class="btn btn-soft" data-keep>' +
          esc(t('app_stage_keep')) + '</button></div>', 'top');
        panel.querySelector('[data-keep]').onclick = function() {
          moment.hidePanel();
        };
        panel.querySelectorAll('[data-who]').forEach(function(b) {
          b.onclick = function() {
            moment.hidePanel();
            var name = e.options.filter(function(o) {
              return o.id === b.dataset.who;
            })[0].name;
            state.busy = true;
            scene.callOut(name);
            T.api('answer_encounter', {courseid: cfg.courseid, place: e.place, choice: b.dataset.who, clues: state.found})
            .then(function(r) {
              setTimeout(function() {
                state.busy = false;
                if (r.again) {
                  state.crossed = r.crossed;
                  scene.flee(name);
                } else if (r.right) {
                  scene.solve(r);
                }
              }, moment.still ? 0 : 900);
            }).catch(function(err) {
              state.busy = false;
              T.toast(esc(err.message), true);
            });
          };
        });
      };
      return T.stage.open({
        title: t('app_stage_encounter', ch.name),
        onlist: function() {
          e.crossed = state.crossed;
          aslist(e, state.found);
        },
        onclose: function() {
          if (T.fx) {
            T.fx.hush();
            T.fx.moment('');
          }
          done();
        },
        scene: {
          preload: function() {
            this.load.image('bg', T.artUrl(['scene.' + ch.code]));
            if (place.front && T.artUrl([place.front])) {
              this.load.image('front', T.artUrl([place.front]));
            }
            if (rigs.hero) {
              T.puppet.load(this, 'puppet.hero', rigs.hero);
            } else if (hero) {
              this.load.image('hero', T.artUrl([hero]));
            }
            if (rigs.pet) {
              T.puppet.load(this, 'puppet.pet', rigs.pet);
            } else if (pet.url) {
              this.load.image('pet', pet.url);
            }
            var picture = T.artUrl(['being.' + being, CRITTER[being] || 'none']);
            if (picture) {
              this.load.image('being', picture);
            }
            if (T.artUrl(['critter.firefly'])) {
              this.load.image('firefly', T.artUrl(['critter.firefly']));
            }
            ['still', 'rustle', 'open', 'flat'].forEach(function(look) {
              var url = T.artUrl([hideout + '.' + look]);
              if (url) {
                this.load.image('bush.' + look, url);
              }
            }, this);
          },
          create: function() {
            scene = this;
            moment = this.stage;
            var S = this;
            var still = moment.still;
            // Without a picture, a shape: the moment is still played.
            if (!S.textures.exists('being')) {
              makeFigure(S, 'being', 0x2b2418);
            }
            makeTextures(S);
            S.add.image(0, 0, 'bg').setOrigin(0).setDepth(0);
            if (S.textures.exists('front')) {
              S.add.image(0, 0, 'front').setOrigin(0).setDepth(5000);
            }
            // A figure: a puppet that moves by pieces, or its picture; the size, that of the figure it was cut from.
            var actor = function(key, x, y, height, rig) {
              var puppet = rig && T.puppet.make(S, rig, still);
              if (!puppet && !S.textures.exists(key)) {
                makeFigure(S, key, key === 'hero' ? 0xffc857 : 0x7cc67f);
              }
              var sprite = puppet ? puppet.container.setPosition(x, y) : S.add.image(x, y, key).setOrigin(0.5, 1);
              var shadow = S.add.ellipse(x, y, 10, 10, 0x0b1a10, 0.32);
              return {x: x, y: y, sprite: sprite, puppet: puppet, shadow: shadow, base: height / sprite.height, walk: 0,
                moving: false, facing: 1, lift: 0, tilt: 0, pose: null};
            };
            var start = point(place.start);
            S.hero = actor('hero', start.x, start.y, 250, rigs.hero && 'puppet.hero');
            S.pet = actor('pet', start.x - 85, start.y + 12, 124, rigs.pet && 'puppet.pet');
            S.pet.mode = 'follow';
            if (pet.hue) {
              try {
                S.pet.sprite.enableFilters();
                S.pet.sprite.filters.internal.addColorMatrix().colorMatrix.hue(pet.hue);
              } catch (err) {
                // Without filters, the creature keeps the colour of its picture.
              }
            }
            var hide = hides[state.hide];
            S.bush = S.add.image(hide.x, hide.y, S.textures.exists('bush.still') ? 'bush.still' : 'soft').setOrigin(0.5, 0.912)
              .setScale(S.textures.exists('bush.still') ? 230 / 560 : 3).setDepth(hide.y + 6);
            S.hidden = S.add.image(hide.x, hide.y, 'being').setOrigin(0.5, 1).setDepth(hide.y + 2).setVisible(false);
            // A pool of warm light and a ring mark the next place to look, without words.
            S.pool = S.add.image(0, 0, 'soft').setScale(3.4, 1.1).setTint(0xffd27a).setBlendMode('ADD').setDepth(1)
              .setVisible(false);
            S.ring = S.add.ellipse(0, 0, 60, 22).setStrokeStyle(3, 0xfff1bd, 0.9).setDepth(1).setVisible(false);
            if (!still) {
              S.tweens.add({targets: S.pool, alpha: {from: 0.45, to: 0.95}, duration: 1100, yoyo: true, repeat: -1});
              S.tweens.add({targets: S.ring, scaleX: 3.2, scaleY: 3.2, alpha: {from: 0.9, to: 0}, duration: 1500, repeat: -1});
            }
            S.fly = S.add.image(start.x, start.y - 160, S.textures.exists('firefly') ? 'firefly' : 'spark').setDepth(4000)
              .setScale(S.textures.exists('firefly') ? 0.16 : 1);
            S.flyGlow = S.add.image(0, 0, 'soft').setScale(1.4).setTint(0xfff1a8).setBlendMode('ADD').setDepth(4001);
            S.flyTo = {x: start.x, y: start.y - 160};
            S.dust = S.add.particles(0, 0, 'dot', {lifespan: 520, speed: {min: 8, max: 36}, angle: {min: 200, max: 340},
              scale: {start: 0.45, end: 1.3}, alpha: {start: 0.3, end: 0}, tint: 0xd8c79f, emitting: false});
            S.burst = S.add.particles(0, 0, 'spark', {lifespan: 900, speed: {min: 80, max: 260}, scale: {start: 0.9, end: 0},
              alpha: {start: 1, end: 0}, tint: [0xfff1bd, 0xffc857, 0xe8508f], gravityY: 160, emitting: false}).setDepth(4500);
            S.leaves = S.add.particles(0, 0, 'leaf', {lifespan: 1100, speed: {min: 50, max: 150}, angle: {min: 220, max: 320},
              rotate: {min: 0, max: 360}, alpha: {start: 1, end: 0}, gravityY: 260, tint: bits, emitting: false})
              .setDepth(4300);
            S.hearts = S.add.particles(0, 0, 'heart', {lifespan: 1300, speed: {min: 30, max: 90}, angle: {min: 240, max: 300},
              scale: {start: 0.9, end: 0.3}, alpha: {start: 1, end: 0}, tint: [0xe8508f, 0xff8fbf], gravityY: -40,
              emitting: false}).setDepth(4700);
            S.emotes = [];
            S.keys = S.input.keyboard.createCursorKeys();
            S.input.keyboard.on('keydown-SPACE', function() {
              S.interact();
            });
            S.input.keyboard.on('keydown-ENTER', function() {
              S.interact();
            });
            S.input.on('pointerdown', function(pointer) {
              S.tap(pointer);
            });
            S.camTarget = {x: start.x, y: start.y - 110};
            S.cameras.main.setBounds(0, 0, W, H);
            S.fit = function() {
              var cam = S.cameras.main;
              var closer = S.scale.width > S.scale.height ? 1.3 : 1;
              S.zoom = Math.max(S.scale.width / W, (S.scale.height / H) * closer);
              cam.setZoom(S.zoom);
            };
            S.fit();
            S.scale.on('resize', S.fit);
            S.cameras.main.startFollow(S.camTarget, false, 0.06, 0.06);
            if (T.fx) {
              T.fx.ambient(T.fx.place(ch.code), ch.code);
              T.fx.moment(state.found ? 'find' : 'explore');
              // What will be said here, ahead: the clues of this being and the lines of the creature.
              T.fx.fetch(e.clues.map(function(clue, i) {
                return clueVoice(i);
              }).concat(['voice.creature.smell', 'voice.creature.here', 'voice.creature.another',
                hideout === 'stage.bush' ? 'voice.creature.bush' : 'voice.creature.hideout', 'voice.creature.hello',
                'voice.hidden.giggle', 'voice.hidden.found']));
            }
            hud();

            /**
             * A figure does something once (surprise, point, sniff…), if it is a puppet.
             *
             * @param {Object} who
             * @param {string} clip
             * @return {boolean} Whether it did.
             */
            S.act = function(who, clip) {
              return !!who.puppet && who.puppet.play(clip, true);
            };

            /** The bush shakes. */
            S.rustle = function() {
              var bush = S.bush;
              if (S.textures.exists('bush.rustle')) {
                bush.setTexture('bush.rustle');
              }
              S.leaves.explode(still ? 2 : 5, bush.x, bush.y - 60);
              sound(place.hide === 'books' ? 'page' : 'bush.rustle', 'tap');
              if (still) {
                return;
              }
              S.tweens.add({targets: bush, angle: {from: -3, to: 3}, duration: 110, yoyo: true, repeat: 3, onComplete: function() {
                bush.setAngle(0);
                if (!state.solved && S.textures.exists('bush.still')) {
                  bush.setTexture('bush.still');
                }
              }});
            };

            /**
             * The creature runs ahead, to a trace or to the bush, and the firefly marks it.
             *
             * @param {Object} spot
             * @param {boolean} ring With the ring that beats.
             */
            S.lead = function(spot, ring) {
              S.pet.mode = 'lead';
              S.pet.goal = {x: spot.x - 46, y: spot.y + 14};
              S.flyTo = {x: spot.x, y: spot.y - 120};
              S.pool.setPosition(spot.x, spot.y + (ring ? 0 : 22)).setVisible(true);
              S.ring.setPosition(spot.x, spot.y).setVisible(ring);
            };
            S.next = function() {
              if (state.found < traces.length) {
                S.lead(traces[state.found], true);
              } else {
                S.lead({x: hides[state.hide].x - 30, y: hides[state.hide].y}, false);
              }
            };

            /** A round bubble over a head: «!», «?», a heart or a name called. */
            S.emote = function(who, symbol) {
              var text = S.add.text(0, 0, symbol, {fontFamily: 'Lexend, sans-serif', fontSize: '26px', fontStyle: 'bold',
                color: '#fff1bd'}).setOrigin(0.5);
              var w = Math.max(46, text.width + 28);
              var frame = S.add.graphics();
              frame.fillStyle(0x0f2131, 0.92).fillRoundedRect(-w / 2, -22, w, 44, 22);
              frame.lineStyle(2.5, 0xffc857, 1).strokeRoundedRect(-w / 2, -22, w, 44, 22);
              frame.fillStyle(0x0f2131, 0.92).fillTriangle(-7, 20, 7, 20, 0, 31);
              var bubble = S.add.container(who.sprite.x, who.sprite.y, [frame, text]).setDepth(4800).setScale(still ? 1 : 0);
              var em = {bubble: bubble, who: who, rise: 0};
              S.emotes.push(em);
              if (!still) {
                S.tweens.add({targets: bubble, scale: 1, duration: 260, ease: 'Back.easeOut'});
              }
              S.tweens.add({targets: em, rise: 16, delay: 1100, duration: 400});
              S.tweens.add({targets: bubble, alpha: 0, delay: 1100, duration: 400, onComplete: function() {
                bubble.destroy();
                S.emotes = S.emotes.filter(function(x) {
                  return x !== em;
                });
              }});
            };
            S.hop = function(who, loud) {
              if (loud) {
                sound('hop', '');
              }
              if (S.act(who, who === S.pet ? 'hop' : 'surprise')) {
                return;
              }
              if (!still) {
                S.tweens.add({targets: who, lift: -22, duration: 160, yoyo: true, ease: 'Sine.easeOut'});
              }
            };

            /** The student touches the ground: the hero walks there; touching the bush, to the bush. */
            S.tap = function(pointer) {
              if (state.solved || state.phase !== 'explore' || !moment.layer.querySelector('[data-panel]').hidden) {
                return;
              }
              var hide = hides[state.hide];
              if (Math.hypot(pointer.worldX - hide.x, pointer.worldY - (hide.y - 40)) < 90) {
                S.walkTo(onGround(place, hide.x - 90, hide.y + 30), S.interact);
                return;
              }
              S.walkTo(onGround(place, pointer.worldX, pointer.worldY), null);
            };
            S.walkTo = function(p, arrive) {
              S.hero.target = p;
              S.hero.arrive = arrive;
              if (Math.abs(p.x - S.hero.x) > 4) {
                S.hero.facing = Math.sign(p.x - S.hero.x);
              }
              var ripple = S.add.ellipse(p.x, p.y, 34, 13).setStrokeStyle(2, 0xfff1bd, 0.9).setDepth(3);
              S.tweens.add({targets: ripple, scale: 2.6, alpha: 0, duration: still ? 1 : 520, onComplete: function() {
                ripple.destroy();
              }});
            };
            S.interact = function() {
              if (!state.solved && !state.busy && state.found && distance(S.hero, hides[state.hide]) < NEAR + 50) {
                choose();
              }
            };

            /** At a trace: the creature sniffs and a voice from the grass says a clue. */
            S.trace = function() {
              var i = state.found;
              var spot = traces[i];
              if (!spot || state.busy) {
                return;
              }
              state.busy = true;
              S.pet.mode = 'lead';
              S.pet.goal = {x: spot.x - 30, y: spot.y + 10};
              S.time.delayedCall(still ? 0 : 450, function() {
                S.pet.mode = 'still';
                S.pet.pose = 'sniff';
                sound('sniff', 'near');
                // The first time, the creature says it has found something; the clue comes when it has said it.
                var waited = false;
                var said = !i ? false : true;
                var clue = function() {
                  if (!waited || !said) {
                    return;
                  }
                  waited = false;
                  S.pet.pose = null;
                  S.pool.setVisible(false);
                  S.ring.setVisible(false);
                  S.burst.explode(still ? 6 : 22, spot.x, spot.y - 10);
                  sound('clue', 'chime');
                  state.found++;
                  state.last = S.time.now;
                  hud();
                  if (state.found === 1) {
                    T.fx.moment('find');
                  }
                  moment.say(t('app_stage_grass'), e.clues[i], true);
                  voice(clueVoice(i));
                  S.rustle();
                  S.hop(S.hero);
                  S.emote(S.hero, '!');
                  if (i === 1) {
                    S.time.delayedCall(900, S.peek);
                  }
                  S.time.delayedCall(still ? 0 : 1600, function() {
                    state.busy = false;
                    S.next();
                    if (state.found >= traces.length) {
                      var bush = hideout === 'stage.bush';
                      moment.say(petname, t(bush ? 'app_stage_bush' : 'app_stage_hideout'), true);
                      voice(bush ? 'voice.creature.bush' : 'voice.creature.hideout');
                    }
                  });
                };
                if (!i) {
                  moment.say(petname, t('app_stage_here'), true);
                  voice('voice.creature.here', function() {
                    said = true;
                    clue();
                  });
                }
                S.time.delayedCall(still ? 0 : 1150, function() {
                  waited = true;
                  clue();
                });
              });
            };

            /** After the second clue the being peeks out for a moment, as a dark shape. */
            S.peek = function() {
              var hide = hides[state.hide];
              var hidden = S.hidden;
              if (still) {
                return;
              }
              S.peeking = true;
              hidden.setPosition(hide.x + 12, hide.y - 8).setVisible(true).setTint(0x101820).setAlpha(0.92)
                .setScale((S.pet.sprite.displayHeight * 0.5) / hidden.height);
              sound('peek', '');
              S.rustle();
              S.emote(S.pet, '?');
              S.act(S.pet, 'alert');
              S.tweens.add({targets: hidden, y: hide.y - 92, duration: 260, yoyo: true, hold: 650, ease: 'Sine.easeOut',
                onComplete: function() {
                  hidden.setVisible(false).clearTint().setAlpha(1).setPosition(hide.x, hide.y);
                  S.peeking = false;
                }});
            };

            /** The hero calls the chosen one towards the bush. */
            S.callOut = function(name) {
              var short = name.replace(/^(el|la|los|las) /, '');
              // A figure with its tool in the left hand points to the left of its picture: it turns the other way.
              var side = Math.sign(hides[state.hide].x - S.hero.x) || 1;
              S.hero.facing = S.hero.puppet && S.hero.puppet.mirror ? -side : side;
              S.act(S.hero, 'point');
              S.emote(S.hero, t('app_stage_call', short.charAt(0).toUpperCase() + short.slice(1)));
            };

            /** A wrong name: a giggle, and the being flees to the other bush; the creature finds another clue. */
            S.flee = function(name) {
              var from = hides[state.hide];
              state.hide = (state.hide + 1) % hides.length;
              var to = hides[state.hide];
              state.prompted = false;
              S.rustle();
              S.emote(S.pet, '?');
              S.act(S.pet, 'alert');
              moment.hush();
              moment.say(t('app_stage_grass'), t('app_stage_fled', name), false);
              voice('voice.hidden.giggle');
              if (!T.fx || !T.artUrl(['voice.hidden.giggle'])) {
                sound('escape', 'giggle');
              }
              for (var i = 0; i <= 5; i++) {
                (function(k) {
                  S.time.delayedCall(k * 110, function() {
                    S.leaves.explode(still ? 1 : 3, from.x + (to.x - from.x) * k / 5, from.y + (to.y - from.y) * k / 5 - 20);
                  });
                })(i);
              }
              var move = function() {
                S.bush.setPosition(to.x, to.y).setDepth(to.y + 6);
                S.hidden.setPosition(to.x, to.y).setDepth(to.y + 2);
              };
              if (still) {
                move();
              } else {
                var scale = S.bush.scaleY;
                S.tweens.add({targets: S.bush, scaleY: 0.02, duration: 200, delay: 450, ease: 'Sine.easeIn',
                  onComplete: function() {
                    move();
                    S.tweens.add({targets: S.bush, scaleY: scale, duration: 380, ease: 'Back.easeOut'});
                  }});
              }
              // The creature finds one more clue, if there is one left.
              if (state.found < traces.length) {
                S.time.delayedCall(still ? 0 : 1300, function() {
                  moment.say(petname, t('app_stage_another'), true);
                  voice('voice.creature.another');
                });
                S.time.delayedCall(still ? 0 : 2600, S.trace);
              } else {
                S.next();
              }
            };

            /** Found: it comes out of a jump and goes to meet the creature; hearts; then the card. */
            S.solve = function(r) {
              state.solved = true;
              state.phase = 'solved';
              var hide = hides[state.hide];
              S.pool.setVisible(false);
              S.ring.setVisible(false);
              moment.hush();
              if (T.fx) {
                T.fx.hush();
                T.fx.moment('');
              }
              // The music of the moment the being comes out, where music plays; if not, its short sound.
              if (!T.fx || !T.fx.musicOn() || !T.fx.musicAllowed() || !T.fx.later('music.victory')) {
                sound('sting.win', 'win');
              }
              if (S.textures.exists('bush.open')) {
                S.bush.setTexture('bush.open');
                S.time.delayedCall(320, function() {
                  if (S.textures.exists('bush.flat')) {
                    S.bush.setTexture('bush.flat');
                  }
                });
              }
              S.leaves.explode(still ? 4 : 14, hide.x, hide.y - 50);
              var side = Math.sign(S.hero.x - hide.x) || -1;
              var hidden = S.hidden;
              var size = (S.pet.sprite.displayHeight * 0.6) / hidden.height;
              hidden.setPosition(hide.x, hide.y).setVisible(true).setScale(still ? size : 0.05).setFlipX(side < 0);
              if (!still) {
                S.tweens.add({targets: hidden, scale: size, duration: 520, ease: 'Back.easeOut'});
                S.cameras.main.shake(160, 0.0025);
              }
              S.burst.explode(still ? 8 : 30, hide.x, hide.y - 60);
              S.act(S.hero, 'surprise');
              S.emote(S.hero, '!');
              S.time.delayedCall(still ? 0 : 600, function() {
                moment.say(t('app_stage_grass'), t('app_stage_gotme'), true);
                voice('voice.hidden.found');
              });
              S.walkTo(onGround(place, hide.x + side * 200, hide.y + 34), null);
              S.pet.mode = 'lead';
              S.pet.goal = {x: hide.x + side * 118, y: hide.y + 16};
              S.tweens.add({targets: hidden, x: hide.x + side * 22, y: hide.y + 14, duration: still ? 1 : 800, delay: 700,
                ease: 'Sine.easeInOut', onUpdate: function() {
                  hidden.setDepth(hidden.y);
                }});
              S.time.delayedCall(still ? 0 : 1500, function() {
                S.pet.mode = 'still';
                S.pet.facing = -side;
                S.hop(S.pet, true);
                S.pet.pose = 'happy';
                S.act(S.hero, 'celebrate');
                S.hearts.explode(still ? 2 : 8, hide.x + side * 70, hide.y - 70);
                sound('joy', 'heart');
                S.emote(S.pet, '♥');
                S.time.delayedCall(still ? 0 : 1300, function() {
                  moment.say(petname, t('app_stage_hello'), true);
                  voice('voice.creature.hello');
                });
                for (var g = 0; g < r.gems; g++) {
                  S.time.delayedCall(g * 180, function() {
                    sound('gem', 'coin');
                  });
                }
              });
              S.time.delayedCall(still ? 0 : 4200, function() {
                sound('card', 'chime');
                var name = r.name.charAt(0).toUpperCase() + r.name.slice(1);
                var panel = moment.panel('<h3>' + esc(t('app_encounter_right', name)) + '</h3>' + (r.golden ? '<p>' +
                  esc(t('app_encounter_golden')) + '</p>' : '') + (r.gems ? '<p class="conseq good"><span class="what">' +
                  esc(t(r.gems === 1 ? 'app_encounter_gem' : 'app_encounter_gems', r.gems)) + '</span></p>' : '') +
                  '<p class="encounter-fact">' + esc(r.fact) + '</p><div class="actions"><button type="button" ' +
                  'class="btn btn-main" data-done>' + esc(t('app_continue')) + '</button></div>', 'top');
                panel.querySelector('[data-done]').onclick = moment.close;
              });
            };

            // The place introduces itself: something rustles, and the creature has smelled something.
            state.phase = 'explore';
            state.last = S.time.now;
            S.rustle();
            moment.say(petname, t('app_stage_smell'), true);
            voice('voice.creature.smell');
            S.next();
            // For the tests of the browser: where things are, and what the student can do.
            moment.debug = {
              state: function() {
                return {place: e.place, being: being, found: state.found, crossed: state.crossed.slice(), hide: hides[state.hide],
                  solved: state.solved, busy: state.busy, hero: {x: S.hero.x, y: S.hero.y}, traces: traces,
                  phase: state.phase, pictures: {hero: hero, pet: pet.key, rigs: {hero: !!rigs.hero, pet: !!rigs.pet}},
                  puppets: {hero: S.hero.puppet ? S.hero.puppet.clipName : '', pet: S.pet.puppet ? S.pet.puppet.clipName : ''}};
              },
              walkTo: function(x, y) {
                S.walkTo(onGround(place, x, y), null);
              },
              choose: choose
            };
          },
          update: function(time, delta) {
            var S = this;
            if (!S.hero) {
              return;
            }
            var dt = Math.min(delta, 50) / 1000;
            moveHero(S, place, state, dt);
            movePet(S, dt);
            var wob = moment.still ? 0 : 18;
            S.fly.x += (S.flyTo.x + Math.sin(time / 420) * wob - S.fly.x) * Math.min(1, dt * 1.6);
            S.fly.y += (S.flyTo.y + Math.cos(time / 530) * wob * 0.7 - S.fly.y) * Math.min(1, dt * 1.6);
            S.flyGlow.setPosition(S.fly.x, S.fly.y).setAlpha(0.45 + 0.2 * Math.sin(time / 180));
            if (state.solved) {
              S.camTarget.x = (S.hero.x + S.pet.x + S.hidden.x) / 3;
              S.camTarget.y = (S.hero.y + S.pet.y + S.hidden.y) / 3 - 100;
            } else {
              S.camTarget.x = S.hero.x * 0.72 + S.pet.x * 0.28;
              S.camTarget.y = S.hero.y * 0.85 + S.pet.y * 0.15 - 110;
            }
            animate(S, S.hero, place, time, dt, 7, moment.still, step);
            animate(S, S.pet, place, time, dt, 12, moment.still, step);
            S.emotes.forEach(function(em) {
              em.bubble.setPosition(em.who.sprite.x, em.who.sprite.y - em.who.sprite.displayHeight - 30 - em.rise);
            });
            if (state.phase !== 'explore' || state.busy || state.solved) {
              return;
            }
            var next = traces[state.found];
            if (next && distance(S.hero, next) < NEAR) {
              S.trace();
              return;
            }
            var near = distance(S.hero, hides[state.hide]) < NEAR + 30;
            if (near && !state.prompted && state.found) {
              state.prompted = true;
              choose();
            } else if (!near) {
              state.prompted = false;
            }
            // If nothing happens for a while, the creature reminds without giving orders.
            if (next && time - state.last > 12000 && state.hinted !== state.found) {
              state.hinted = state.found;
              S.hop(S.pet, true);
              moment.say(petname, t('app_stage_waits'), true);
              voice('voice.creature.waits');
            }
          }
        }
      }).then(function(m) {
        moment = m;
        return m;
      });
    }
  };

  /**
   * The hero walks: with the arrows, or towards where the student touched.
   *
   * @param {Object} S The scene.
   * @param {Object} place
   * @param {Object} state
   * @param {number} dt
   */
  function moveHero(S, place, state, dt) {
    var hero = S.hero;
    var free = !state.solved && state.phase === 'explore' && S.stage.layer.querySelector('[data-panel]').hidden;
    var dx = free ? (S.keys.right.isDown ? 1 : 0) - (S.keys.left.isDown ? 1 : 0) : 0;
    var dy = free ? (S.keys.down.isDown ? 1 : 0) - (S.keys.up.isDown ? 1 : 0) : 0;
    if (dx || dy) {
      hero.target = null;
      var len = Math.hypot(dx, dy);
      step(place, hero, hero.x + dx / len * SPEED * dt, hero.y + dy / len * SPEED * dt * 0.7);
      hero.moving = true;
      if (dx) {
        hero.facing = Math.sign(dx);
      }
      return;
    }
    if (!hero.target) {
      hero.moving = false;
      return;
    }
    var gap = distance(hero, hero.target);
    if (gap < 6) {
      hero.target = null;
      hero.moving = false;
      if (hero.arrive) {
        var arrive = hero.arrive;
        hero.arrive = null;
        arrive();
      }
      return;
    }
    var d = Math.min(gap, SPEED * dt);
    if (Math.abs(hero.target.x - hero.x) > 4) {
      hero.facing = Math.sign(hero.target.x - hero.x);
    }
    if (!step(place, hero, hero.x + (hero.target.x - hero.x) / gap * d, hero.y + (hero.target.y - hero.y) / gap * d)) {
      hero.target = null;
    }
    hero.moving = true;
  }

  /**
   * A step on the ground, or along its edge.
   *
   * @param {Object} place
   * @param {Object} who
   * @param {number} x
   * @param {number} y
   * @return {boolean} It moved.
   */
  function step(place, who, x, y) {
    if (inside(place.walk, x, y)) {
      who.x = x;
      who.y = y;
      return true;
    }
    if (inside(place.walk, x, who.y)) {
      who.x = x;
      return true;
    }
    if (inside(place.walk, who.x, y)) {
      who.y = y;
      return true;
    }
    return false;
  }

  /**
   * The creature follows the hero, or runs ahead like a dog and waits where it leads, looking back.
   *
   * @param {Object} S The scene.
   * @param {number} dt
   */
  function movePet(S, dt) {
    var pet = S.pet;
    var hero = S.hero;
    if (pet.mode === 'still') {
      pet.moving = false;
      return;
    }
    var goal = pet.goal;
    if (pet.mode === 'follow' || !goal) {
      goal = {x: hero.x - hero.facing * 78, y: hero.y + 14};
    } else {
      // Never further than the student can see.
      var reach = S.cameras.main.worldView.width * 0.38;
      var ahead = distance(hero, goal);
      if (ahead > reach) {
        goal = {x: hero.x + (goal.x - hero.x) / ahead * reach, y: hero.y + (goal.y - hero.y) / ahead * reach};
      }
    }
    var gap = distance(pet, goal);
    if (gap > 10) {
      var d = Math.min(gap, (pet.mode === 'lead' ? 330 : 300) * dt * (gap > 200 ? 1.4 : 1));
      pet.x += (goal.x - pet.x) / gap * d;
      pet.y += (goal.y - pet.y) / gap * d;
      pet.facing = Math.sign(goal.x - pet.x) || pet.facing;
      pet.moving = true;
    } else {
      pet.moving = false;
      if (pet.mode === 'lead') {
        pet.facing = Math.sign(hero.x - pet.x) || pet.facing;
        if (!pet.nextHop || S.time.now > pet.nextHop) {
          pet.nextHop = S.time.now + 1700;
          S.hop(pet);
        }
      }
    }
  }

  /**
   * Walking and breathing from a single picture: bob, sway, squash, dust; smaller further back.
   *
   * @param {Object} S The scene.
   * @param {Object} who
   * @param {Object} place
   * @param {number} time
   * @param {number} dt
   * @param {number} rate
   * @param {boolean} still
   * @param {string} ground The sound of a step there.
   */
  function animate(S, who, place, time, dt, rate, still, ground) {
    var top = Math.min.apply(null, place.walk.map(function(p) {
      return p[1];
    }));
    var bottom = Math.max.apply(null, place.walk.map(function(p) {
      return p[1];
    }));
    var depth = 0.7 + 0.3 * Math.max(0, Math.min(1, (who.y - top) / ((bottom - top) || 1)));
    var scale = who.base * depth;
    var bob = 0;
    var sway = 0;
    var squash = 1;
    if (who.moving) {
      who.walk += dt * rate;
      bob = Math.abs(Math.sin(who.walk)) * (still ? 2 : 9) * depth;
      sway = still ? 0 : Math.sin(who.walk) * 3.5;
      squash = 1 - Math.abs(Math.cos(who.walk)) * 0.035;
      who.dustClock = (who.dustClock || 0) + dt;
      if (who.dustClock > 0.17) {
        who.dustClock = 0;
        S.dust.setDepth(who.y - 2);
        S.dust.emitParticleAt(who.x, who.y - 4, still ? 1 : 2);
        if (who === S.hero) {
          who.steps = (who.steps || 0) + 1;
          if (who.steps % 2 === 0) {
            sound(ground, '');
          }
        }
      }
    } else {
      squash = 1 + Math.sin(time / 520 + who.x) * 0.012;
    }
    if (who.puppet) {
      // A puppet walks and breathes by itself, or does what it is doing, unless it is in the middle of something.
      if (!who.puppet.busy()) {
        who.puppet.play(who.moving ? 'walk' : who.pose || 'idle');
      }
      who.puppet.draw(dt);
      bob = 0;
      sway = 0;
      squash = 1;
    }
    who.sprite.setPosition(who.x, who.y - bob + who.lift);
    who.sprite.setScale(scale * who.facing, scale * squash);
    who.sprite.setAngle(sway + who.tilt);
    who.sprite.setDepth(who.y);
    who.shadow.setPosition(who.x, who.y - 2);
    who.shadow.setSize(who.sprite.displayHeight * 0.42, who.sprite.displayHeight * 0.09);
    who.shadow.setDepth(who.y - 1);
  }

  /**
   * Textures drawn in code for the effects: a dot, a spark, a leaf, a soft light and a heart.
   *
   * @param {Object} S The scene.
   */
  function makeTextures(S) {
    var g = S.add.graphics();
    g.fillStyle(0xffffff, 1).fillCircle(8, 8, 8);
    g.generateTexture('dot', 16, 16);
    g.clear();
    g.fillStyle(0xffffff, 1).fillPoints([{x: 8, y: 0}, {x: 10, y: 6}, {x: 16, y: 8}, {x: 10, y: 10}, {x: 8, y: 16},
      {x: 6, y: 10}, {x: 0, y: 8}, {x: 6, y: 6}], true);
    g.generateTexture('spark', 16, 16);
    g.clear();
    g.fillStyle(0xffffff, 1).fillEllipse(6, 3, 12, 6);
    g.generateTexture('leaf', 12, 6);
    g.clear();
    for (var r = 32; r > 0; r--) {
      g.fillStyle(0xffffff, 0.05).fillCircle(32, 32, r);
    }
    g.generateTexture('soft', 64, 64);
    g.clear();
    g.fillStyle(0xffffff, 1).fillCircle(6, 6, 6).fillCircle(16, 6, 6).fillTriangle(0.5, 8, 21.5, 8, 11, 20);
    g.generateTexture('heart', 22, 22);
    g.destroy();
  }

  /**
   * A shape for a figure without a picture: a rounded body with a light on top.
   *
   * @param {Object} S The scene.
   * @param {string} key
   * @param {number} colour
   */
  function makeFigure(S, key, colour) {
    var g = S.add.graphics();
    g.fillStyle(colour, 1).fillRoundedRect(10, 20, 60, 90, 28);
    g.fillStyle(0xffffff, 0.35).fillCircle(40, 40, 12);
    g.generateTexture(key, 80, 120);
    g.destroy();
  }
})();
