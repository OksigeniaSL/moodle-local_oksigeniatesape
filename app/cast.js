/**
 * The figures of the student in the moments that are played: their hero and their creature, as puppets that move by
 * pieces when the art pack brings the puppet cut from their picture, or as that picture, walking and breathing from a
 * single image; and, without either, a shape. Shared by the walked encounter and the scenes in the place.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;

  /**
   * The picture of the hero of a character, as the game draws them: its key in the pack.
   *
   * @param {Object} c The character.
   * @param {string} [family] The family of heroes of the world.
   * @param {boolean} [untoned] The picture in the tone it is painted in, whatever the tone chosen.
   * @return {string|null}
   */
  function heroKey(c, family, untoned) {
    return T.artKey(T.characterKeys(family || T.family || 'storyheroes', c.role, c.lineage, c.nameform === 'f' ? 'f' : 'm',
      c.stage || 1, untoned ? -1 : skinOf(c), false));
  }

  /**
   * The tone of skin the student chose, as the index of T.SKINS (-1: none chosen).
   *
   * @param {Object} c The character.
   * @return {number}
   */
  function skinOf(c) {
    var look = {};
    try {
      look = JSON.parse(c.appearance || '{}') || {};
    } catch (e) {
      look = {};
    }
    return T.SKINS.indexOf(look.skin);
  }

  /**
   * The tone of skin of the student as the pictures name it (t1, t2, t3), or null if none was chosen.
   *
   * @param {Object} c The character.
   * @return {string|null}
   */
  function toneOf(c) {
    var skin = skinOf(c);
    return skin < 0 ? null : 't' + (skin < 2 ? 1 : skin < 4 ? 2 : 3);
  }

  /**
   * The paints of the market as colour matrices, the same as the tablet paints them with filters: one over the hue of
   * the creature.
   */
  var PAINTS = {
    'paint-gold': function(m) {
      m.hue(17);
      m.saturate(0.9, true);
      m.brightness(1.18, true);
    },
    'paint-night': function(m) {
      m.hue(190);
      m.saturate(-0.15, true);
      m.brightness(0.75, true);
    },
    'paint-snow': function(m) {
      m.grayscale(0.92);
      m.brightness(1.25, true);
    }
  };

  /**
   * The picture of the creature of a character, its hue, its paint and its name.
   *
   * @param {Object} c The character.
   * @return {Object} key, url, hue, paint, name.
   */
  function creature(c) {
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
    return {key: key, url: key ? T.artUrl([key]) : null, hue: Number(traits.hue) || 0, paint: PAINTS[traits.paint] ?
      traits.paint : '', name: traits.name || ''};
  }

  /**
   * A shape for a figure without a picture: a rounded body with a light on top.
   *
   * @param {Object} S The scene.
   * @param {string} key
   * @param {number} colour
   */
  function figure(S, key, colour) {
    var g = S.add.graphics();
    g.fillStyle(colour, 1).fillRoundedRect(10, 20, 60, 90, 28);
    g.fillStyle(0xffffff, 0.35).fillCircle(40, 40, 12);
    g.generateTexture(key, 80, 120);
    g.destroy();
  }

  /**
   * A copy of a picture with the colour of the creature in it: its turn of hue, or its paint of the market. Without it
   * (a picture the browser does not let read), the picture as it is.
   *
   * @param {Object} S The scene.
   * @param {string} key The texture of the picture.
   * @param {number} hue
   * @param {string} paint
   * @return {string} The texture of the copy.
   */
  function painted(S, key, hue, paint) {
    var name = key + '.paint.' + (PAINTS[paint] ? paint : hue);
    if (S.textures.exists(name)) {
      return name;
    }
    try {
      var source = S.textures.get(key).getSourceImage();
      var canvas = document.createElement('canvas');
      canvas.width = source.width;
      canvas.height = source.height;
      var ctx = canvas.getContext('2d', {willReadFrequently: true});
      ctx.drawImage(source, 0, 0);
      var pixels = ctx.getImageData(0, 0, canvas.width, canvas.height);
      var d = pixels.data;
      var m = new window.Phaser.Display.ColorMatrix();
      if (PAINTS[paint]) {
        PAINTS[paint](m);
      } else {
        m.hue(hue);
      }
      // Rows of five: red, green, blue and alpha in, and what is added (already out of 1).
      var k = m.getData();
      for (var i = 0; i < d.length; i += 4) {
        if (!d[i + 3]) {
          continue;
        }
        var r = d[i] / 255;
        var g = d[i + 1] / 255;
        var b = d[i + 2] / 255;
        var a = d[i + 3] / 255;
        d[i] = 255 * (k[0] * r + k[1] * g + k[2] * b + k[3] * a + k[4]);
        d[i + 1] = 255 * (k[5] * r + k[6] * g + k[7] * b + k[8] * a + k[9]);
        d[i + 2] = 255 * (k[10] * r + k[11] * g + k[12] * b + k[13] * a + k[14]);
      }
      ctx.putImageData(pixels, 0, 0);
      S.textures.addCanvas(name, canvas);
      return name;
    } catch (e) {
      return key;
    }
  }

  T.cast = {
    heroKey: heroKey,
    creature: creature,
    figure: figure,

    /**
     * What a moment loads for the student's figures: the puppets, or the pictures.
     *
     * @param {Object} scene The scene of the engine, while it loads.
     * @param {Object} c The character.
     * @param {string} [family]
     * @return {Object} hero (key of its picture), pet (as creature() gives it), rigs (hero, pet: whether puppets).
     */
    load: function(scene, c, family) {
      var hero = c.role ? heroKey(c, family) : null;
      // The puppet is cut from the picture in the tone it is painted in, and brings the other tones it can take.
      var tone = c.role ? toneOf(c) : null;
      var pet = creature(c);
      var rigs = T.puppet ? {hero: c.role ? T.puppet.find(heroKey(c, family, true), 'hero') : null,
        pet: T.puppet.find(pet.key, 'creature')} : {};
      if (rigs.hero) {
        T.puppet.load(scene, 'puppet.hero', rigs.hero);
      }
      // The picture, too, when there is no puppet or the tone chosen might not be one of the puppet's.
      if (hero && (!rigs.hero || tone)) {
        scene.load.image('hero', T.artUrl([hero]));
      }
      scene.castTone = tone;
      if (rigs.pet) {
        T.puppet.load(scene, 'puppet.pet', rigs.pet);
      } else if (pet.url) {
        scene.load.image('pet', pet.url);
      }
      return {hero: hero, pet: pet, rigs: {hero: !!rigs.hero, pet: !!rigs.pet}};
    },

    /**
     * A figure of the student, once loaded: its puppet, or its picture, or a shape; the size, that of the figure it was
     * cut from.
     *
     * @param {Object} S The scene.
     * @param {string} which hero or pet.
     * @param {number} x
     * @param {number} y Where its feet are.
     * @param {number} height In the units of the scene.
     * @param {boolean} still Without movement.
     * @param {number} [hue] A turn of colour (the creature).
     * @param {string} [paint] The paint of the market, over the hue (the creature).
     * @return {Object} The figure: x, y, sprite, puppet, shadow, base, facing, lift, tilt, pose…
     */
    actor: function(S, which, x, y, height, still, hue, paint) {
      // The colour of the creature goes into a copy of its picture, not in a filter over the figure: the filter is cut
      // to the box of the figure, and a puppet stands on its feet, so it cut its head.
      var from = (hue || PAINTS[paint]) && S.textures.exists('puppet.' + which) ?
        painted(S, 'puppet.' + which, hue, paint) : null;
      var puppet = T.puppet && T.puppet.make(S, 'puppet.' + which, still, which === 'hero' ? S.castTone || null : null,
        from);
      if (!puppet && !S.textures.exists(which)) {
        figure(S, which, which === 'hero' ? 0xffc857 : 0x7cc67f);
      }
      var image = !puppet && (hue || PAINTS[paint]) ? painted(S, which, hue, paint) : which;
      var sprite = puppet ? puppet.container.setPosition(x, y) : S.add.image(x, y, image).setOrigin(0.5, 1);
      var shadow = S.add.ellipse(x, y, 10, 10, 0x0b1a10, 0.32);
      return {x: x, y: y, sprite: sprite, puppet: puppet, shadow: shadow, base: height / sprite.height, walk: 0,
        moving: false, facing: 1, lift: 0, tilt: 0, pose: null};
    },

    /**
     * The aura of the market around the hero, as the tablet shows it: fireflies that blink around them, or leaves that
     * fall around them. Without movement, a still light of its colour. Needs the textures of textures().
     *
     * @param {Object} S The scene.
     * @param {Object} who The figure, as actor() makes it.
     * @param {string} aura aura-fireflies or aura-leaves.
     * @param {boolean} still
     */
    aura: function(S, who, aura, still) {
      var looks = {
        // A firefly is a soft light, a leaf a leaf.
        'aura-fireflies': {texture: 'soft', tint: [0xffd23f, 0xffe58a], light: 0xffe58a, scale: {start: 0.36, end: 0.2},
          speed: {min: 4, max: 16}, gravityY: 0, frequency: 150, blendMode: 'ADD'},
        'aura-leaves': {texture: 'leaf', tint: [0x6fd08c, 0x9bdc6a, 0x4fae6a], light: 0x6fd08c, scale: {start: 1.1, end: 0.8},
          speed: {min: 6, max: 20}, gravityY: 14, frequency: 260, rotate: {min: 0, max: 360}, blendMode: 'NORMAL'}
      };
      var look = looks[aura];
      if (!look) {
        return;
      }
      // Around the body, as tall as the figure is drawn now (move() keeps it).
      var around = {getRandomPoint: function(p) {
        var tall = who.auraTall || 100;
        p.x = (Math.random() - 0.5) * tall * 0.65;
        p.y = (Math.random() - 0.5) * tall * 0.9;
        return p;
      }};
      who.auraStill = still;
      try {
        who.aura = still ? S.add.image(who.x, who.y, 'soft').setTint(look.light).setAlpha(0.35) :
          S.add.particles(0, 0, look.texture, {lifespan: 2000, scale: look.scale, speed: look.speed, gravityY: look.gravityY,
            rotate: look.rotate || 0, tint: look.tint, frequency: look.frequency, blendMode: look.blendMode,
            emitZone: {type: 'random', source: around}, alpha: {onEmit: function() {
              return 0;
            }, onUpdate: function(particle, key, t) {
              // It comes, glows and goes.
              return Math.sin(t * Math.PI);
            }}});
      } catch (err) {
        // Without particles, the hero goes without their aura here; the tablet still shows it.
      }
    },

    /**
     * A figure does something once (surprise, point, sniff…), if it is a puppet.
     *
     * @param {Object} who
     * @param {string} clip
     * @return {boolean} Whether it did.
     */
    act: function(who, clip) {
      return !!who.puppet && who.puppet.play(clip, true);
    },

    /**
     * Walking and breathing: a puppet does it by itself (walk, or what it is doing, unless it is in the middle of
     * something); a single picture bobs, sways and squashes. Smaller further back; dust under the feet.
     *
     * @param {Object} S The scene.
     * @param {Object} who
     * @param {number} depth 0.7 at the back of the ground, 1 at the front.
     * @param {number} time
     * @param {number} dt Seconds.
     * @param {number} rate Steps of a picture that walks.
     * @param {boolean} still
     * @param {Function} [stepped] Every second step of the hero.
     */
    move: function(S, who, depth, time, dt, rate, still, stepped) {
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
          if (S.dust) {
            S.dust.setDepth(who.y - 2);
            S.dust.emitParticleAt(who.x, who.y - 4, still ? 1 : 2);
          }
          who.steps = (who.steps || 0) + 1;
          if (stepped && who.steps % 2 === 0) {
            stepped();
          }
        }
      } else {
        squash = 1 + Math.sin(time / 520 + who.x) * 0.012;
      }
      if (who.puppet) {
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
      if (who.aura) {
        // The fireflies around the body, in front of it.
        var tall = who.sprite.height * scale;
        who.auraTall = tall;
        if (who.auraStill) {
          who.aura.setPosition(who.x, who.y - tall * 0.5).setDisplaySize(tall * 0.9, tall * 1.1);
        } else {
          who.aura.setPosition(who.x, who.y - tall * 0.55);
        }
        who.aura.setDepth(who.y + 1);
      }
    },

    /**
     * Textures drawn in code for the effects: a dot, a spark, a leaf, a soft light and a heart.
     *
     * @param {Object} S The scene.
     */
    textures: function(S) {
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
  };
})();
