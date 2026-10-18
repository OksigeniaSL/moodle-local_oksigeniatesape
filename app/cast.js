/**
 * The figures of the student in the moments that are played: their hero and their creature, as puppets that move by
 * pieces when the art pack brings the puppet cut from their picture, or as that picture, walking and breathing from a
 * single image; and, without either, a shape. Shared by the walked encounter and the scenes in the place.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
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
   * @return {string|null}
   */
  function heroKey(c, family) {
    var look = {};
    try {
      look = JSON.parse(c.appearance || '{}') || {};
    } catch (e) {
      look = {};
    }
    return T.artKey(T.characterKeys(family || T.family || 'storyheroes', c.role, c.lineage, c.nameform === 'f' ? 'f' : 'm',
      c.stage || 1, T.SKINS.indexOf(look.skin), false));
  }

  /**
   * The picture of the creature of a character, its hue and its name.
   *
   * @param {Object} c The character.
   * @return {Object} key, url, hue, name.
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
    return {key: key, url: key ? T.artUrl([key]) : null, hue: Number(traits.hue) || 0, name: traits.name || ''};
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
      var hero = heroKey(c, family);
      var pet = creature(c);
      var rigs = T.puppet ? {hero: T.puppet.find(hero, 'hero'), pet: T.puppet.find(pet.key, 'creature')} : {};
      if (rigs.hero) {
        T.puppet.load(scene, 'puppet.hero', rigs.hero);
      } else if (hero) {
        scene.load.image('hero', T.artUrl([hero]));
      }
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
     * @return {Object} The figure: x, y, sprite, puppet, shadow, base, facing, lift, tilt, pose…
     */
    actor: function(S, which, x, y, height, still, hue) {
      var puppet = T.puppet && T.puppet.make(S, 'puppet.' + which, still);
      if (!puppet && !S.textures.exists(which)) {
        figure(S, which, which === 'hero' ? 0xffc857 : 0x7cc67f);
      }
      var sprite = puppet ? puppet.container.setPosition(x, y) : S.add.image(x, y, which).setOrigin(0.5, 1);
      var shadow = S.add.ellipse(x, y, 10, 10, 0x0b1a10, 0.32);
      if (hue) {
        try {
          sprite.enableFilters();
          sprite.filters.internal.addColorMatrix().colorMatrix.hue(hue);
        } catch (err) {
          // Without filters, the creature keeps the colour of its picture.
        }
      }
      return {x: x, y: y, sprite: sprite, puppet: puppet, shadow: shadow, base: height / sprite.height, walk: 0,
        moving: false, facing: 1, lift: 0, tilt: 0, pose: null};
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
