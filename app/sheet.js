// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The characters drawn in 3D: for each figure, a sheet per gesture and view (spr.<key>.<gesture>.<view>, its pages and
 * its atlas spr.<…>.atlas), frame by frame. A figure made of sheets stands where a puppet would, with the same calls
 * (play, busy, draw) and its feet at the same place, so the place, the walk and the fights do not tell them apart. The
 * view «d» looks to the right and is mirrored to look to the left. The sheets of rest, talk and walk are loaded with the
 * place; the rest of the gestures, when they are asked for the first time. Without movement, each gesture shows its
 * still frame.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  /**
   * @var {Object} The canvas of the puppet each body replaces, where its feet are, and how tall the figure stands in it:
   *     a figure of sheets is measured as the puppet it stands for, so it is as big in the place.
   */
  var BODIES = {
    person: {canvas: [768, 896], feet: [384, 860], tall: 790},
    small: {canvas: [512, 512], feet: [256, 457], tall: 430}
  };
  /**
   * @var {Object} The gestures each body uses at once, loaded with the place: the hero and the creature rest and walk,
   *     who speaks rests and talks. The rest are loaded when they are asked for.
   */
  var FIRST = {hero: ['idle', 'walk'], creature: ['idle', 'walk'], speaker: ['idle', 'talk'], bird: ['idle', 'talk']};
  /** @var {number} Milliseconds the frame of the gesture before stays over the new one, fading. */
  var BLEND = 100;

  /**
   * The sheets of a figure in the art packs, by gesture and view, each with its atlas and its pages; null without its
   * rest (idle).
   *
   * @param {string} id The key of the figure: creature.2.fur, storyheroes.healer.dwarf.f.3, figure.druida-mayor…
   * @return {Object|null}
   */
  function find(id) {
    var files = (T.cfg.art && T.cfg.art.files) || {};
    var prefix = 'spr.' + id + '.';
    var out = {};
    Object.keys(files).forEach(function(k) {
      var m = k.indexOf(prefix) === 0 && /^([a-z_]+)\.(d|f)\.atlas$/.exec(k.slice(prefix.length));
      var key = m ? k.slice(0, -'.atlas'.length) : '';
      if (!m || !files[key]) {
        return;
      }
      var pages = [key];
      for (var n = 1; files[key + '-' + n]; n++) {
        pages.push(key + '-' + n);
      }
      out[m[1]] = out[m[1]] || {};
      out[m[1]][m[2]] = {key: key, atlas: files[k], pages: pages, urls: pages.map(function(p) {
        return files[p];
      })};
    });
    return out.idle && out.idle.d ? out : null;
  }

  /**
   * Asks the engine for the sheet of one gesture: its atlas and its pages.
   *
   * @param {Object} scene
   * @param {Object} spec As find() gives it for a gesture and view.
   */
  function queue(scene, spec) {
    if (!scene.cache.json.exists('sheet:' + spec.key)) {
      scene.load.json('sheet:' + spec.key, spec.atlas);
    }
    spec.pages.forEach(function(p, i) {
      if (!scene.textures.exists('sheet:' + p)) {
        scene.load.image('sheet:' + p, spec.urls[i]);
      }
    });
  }

  /**
   * The animation of one sheet, made once from its atlas: each frame from its page.
   *
   * @param {Object} scene
   * @param {Object} spec
   * @return {Object|null} key, meta and the frames; null while it is not loaded.
   */
  function animOf(scene, spec) {
    var json = scene.cache.json.get('sheet:' + spec.key);
    if (!json || !json.frames || !json.meta || !spec.pages.every(function(p) {
      return scene.textures.exists('sheet:' + p);
    })) {
      return null;
    }
    var key = 'sheet:' + spec.key;
    var frames = Object.keys(json.frames).sort().map(function(name) {
      var f = json.frames[name];
      var page = 'sheet:' + spec.pages[Math.min(spec.pages.length - 1, f.page || 0)];
      var texture = scene.textures.get(page);
      if (!texture.has(name)) {
        texture.add(name, 0, f.frame.x, f.frame.y, f.frame.w, f.frame.h);
      }
      return {key: page, frame: name};
    });
    if (!frames.length) {
      return null;
    }
    if (!scene.anims.exists(key)) {
      scene.anims.create({key: key, frames: frames, frameRate: json.meta.fps || 12, repeat: json.meta.loop ? -1 : 0});
    }
    return {key: key, meta: json.meta, frames: frames};
  }

  /**
   * A figure made of sheets.
   *
   * @param {Object} scene
   * @param {Object} sheets As find() gives them.
   * @param {boolean} still Without movement.
   */
  function Sheet(scene, sheets, still, body) {
    this.scene = scene;
    this.sheets = sheets;
    this.still = still;
    this.view = 'd';
    this.body = BODIES[body === 'creature' || body === 'minion' ? 'small' : 'person'];
    this.feet = this.body.feet.slice();
    this.mirror = false;
    this.tone = null;
    this.clipName = '';
    this.meta = null;
    this.want = '';
    this.loading = {};
    var first = animOf(scene, sheets.idle.d);
    this.image = scene.add.sprite(0, 0, first.frames[0].key, first.frames[0].frame);
    // The last frame of the gesture before, over the new one for a moment, fading: no jump from one sheet to the next.
    this.ghost = scene.add.image(0, 0, first.frames[0].key, first.frames[0].frame).setVisible(false);
    this.container = scene.add.container(0, 0, [this.image, this.ghost]);
    this.container.setSize(this.body.canvas[0], this.body.canvas[1]);
    // As a puppet, one piece: what reads the pieces (its texture, whether it shows another drawing) finds this one.
    this.parts = [{name: 'body', frame: 'body', image: this.image}];
    this.play('idle');
  }

  /**
   * Starts a gesture from its beginning; one already playing goes on, unless it is asked again. A gesture not loaded
   * yet is loaded, and starts as soon as it comes, if it is still the one wanted.
   *
   * @param {string} name
   * @param {boolean} [again]
   * @return {boolean} Whether the figure has that gesture.
   */
  Sheet.prototype.play = function(name, again) {
    var spec = this.sheets[name] && (this.sheets[name][this.view] || this.sheets[name].d);
    if (!spec) {
      return false;
    }
    if (this.clipName === name && !again) {
      return true;
    }
    var anim = animOf(this.scene, spec);
    if (!anim) {
      this.want = name;
      this.fetch(name, spec);
      return true;
    }
    this.want = '';
    if (this.clipName && !this.still && this.image.frame) {
      var ghost = this.ghost;
      this.scene.tweens.killTweensOf(ghost);
      ghost.setTexture(this.image.texture.key, this.image.frame.name).setOrigin(this.image.originX, this.image.originY)
        .setScale(this.image.scaleX, this.image.scaleY).setPosition(0, 0).setAlpha(1).setVisible(true);
      this.scene.tweens.add({targets: ghost, alpha: 0, duration: BLEND, onComplete: function() {
        ghost.setVisible(false);
      }});
    }
    this.clipName = name;
    this.meta = anim.meta;
    var cell = anim.meta.cell || {w: 256, h: 256};
    var anchor = anim.meta.anchor || {x: cell.w / 2, y: cell.h - 24};
    // The cell, to the canvas of the puppet it stands for: its feet where the feet of the puppet are (the origin of the
    // figure), the figure standing as tall as the puppet (meta.height: its height in the cell, standing at rest).
    var tall = anim.meta.height || cell.h * 0.6;
    this.image.setOrigin(anchor.x / cell.w, anchor.y / cell.h).setScale(this.body.tall / tall).setPosition(0, 0);
    if (this.still) {
      var at = anim.frames[Math.min(anim.frames.length - 1, anim.meta.still || 0)];
      this.image.anims.stop();
      this.image.setTexture(at.key, at.frame);
    } else {
      this.image.play({key: anim.key, repeat: anim.meta.loop ? -1 : 0}, false);
    }
    return true;
  };

  /**
   * Loads the sheet of a gesture while the place goes on.
   *
   * @param {string} name
   * @param {Object} spec
   */
  Sheet.prototype.fetch = function(name, spec) {
    var self = this;
    var scene = this.scene;
    if (this.loading[spec.key] || !scene.load) {
      return;
    }
    this.loading[spec.key] = true;
    queue(scene, spec);
    scene.load.once('complete', function() {
      if (self.want === name && self.image.scene) {
        self.play(name, true);
      }
    });
    if (!scene.load.isLoading()) {
      scene.load.start();
    }
  };

  /**
   * Whether a gesture that does not loop has not reached its end.
   *
   * @return {boolean}
   */
  Sheet.prototype.busy = function() {
    return !this.still && !!this.meta && !this.meta.loop && !!this.image.anims && this.image.anims.isPlaying;
  };

  /**
   * The frames move by themselves; here, only how high the feet are over the ground in this frame.
   */
  Sheet.prototype.draw = function() {
    var lift = this.meta && this.meta.lift;
    var frame = this.image.anims && this.image.anims.currentFrame;
    this.lift = lift && frame ? lift[frame.index - 1] || 0 : 0;
  };

  T.sheet = {
    find: find,

    /**
     * Asks the engine, while the place loads, for the sheets its body uses at once (those it has).
     *
     * @param {Object} scene
     * @param {Object} sheets As find() gives them.
     * @param {string} [body] The kind of puppet it stands for.
     */
    load: function(scene, sheets, body) {
      (FIRST[body] || ['idle']).forEach(function(name) {
        if (sheets[name] && sheets[name].d) {
          queue(scene, sheets[name].d);
        }
      });
    },

    /**
     * The figure, once loaded; null if its rest did not come.
     *
     * @param {Object} scene
     * @param {Object} sheets
     * @param {boolean} still
     * @param {string} [body] The kind of puppet it stands for: hero, speaker, creature, minion…
     * @return {Sheet|null}
     */
    make: function(scene, sheets, still, body) {
      return animOf(scene, sheets.idle.d) ? new Sheet(scene, sheets, still, body) : null;
    }
  };
})();
