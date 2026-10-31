/**
 * The puppets of the moments that are played: a figure cut in pieces (an atlas with the pivot of each piece and where it
 * hangs from its parent), the skeleton its kind of body shares, and animations as data (walk, surprise, sniff…),
 * the same for every figure of that body. Each piece is an image of the engine inside a container: the container is the
 * figure, placed by its feet, turned and flipped as one. Without movement, each animation holds its still pose.
 *
 * Arms, forearms and tool are asked for as directions (`a`: 0 to the right, 90 down), so that every figure takes the
 * same posture whatever the pose it was cut in (the atlas says where each piece pointed then, meta.rig.dirs); the other
 * pieces turn from where their parent leaves them (`r`). A figure with its tool in the left hand plays every gesture
 * mirrored, so it points and waves with its free hand; a tool may change the gestures (byTool: a shield is strapped to
 * the forearm), a figure may hang a piece from another parent or draw it in another order (meta.rig.override), and it
 * may have pieces its body does not (meta.rig.extra: wings), which the gestures move if they have tracks for them.
 * The skin is painted in one tone (meta.rig.skin.own); for another of its tones, each piece with skin is drawn again
 * over itself with its frame <piece>.skin.<tone>, the same piece with the skin already in that tone.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;

  /** The eases the animations name, as the engine names them. */
  var EASE = {
    'linear': function(t) {
      return t;
    },
    'Sine.easeIn': function(t) {
      return 1 - Math.cos(t * Math.PI / 2);
    },
    'Sine.easeOut': function(t) {
      return Math.sin(t * Math.PI / 2);
    },
    'Sine.easeInOut': function(t) {
      return -(Math.cos(Math.PI * t) - 1) / 2;
    },
    'Quad.easeIn': function(t) {
      return t * t;
    },
    'Quad.easeOut': function(t) {
      return t * (2 - t);
    },
    'Quad.easeInOut': function(t) {
      return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
    },
    'Cubic.easeOut': function(t) {
      return 1 - Math.pow(1 - t, 3);
    },
    'Back.easeOut': function(t) {
      var s = 1.70158;
      t -= 1;
      return t * t * ((s + 1) * t + s) + 1;
    },
    'Back.easeIn': function(t) {
      var s = 1.70158;
      return t * t * ((s + 1) * t - s);
    }
  };
  var ZERO = {r: 0, x: 0, y: 0, sx: 1, sy: 1};
  /** @var {number} Seconds to pass from one gesture to the next. */
  var FADE = 0.25;

  /**
   * The values of a track at a moment of its clip.
   *
   * @param {Array} track Keyframes: [t from 0 to 1, values, ease into it].
   * @param {number} t
   * @return {Object}
   */
  function sample(track, t) {
    if (t <= track[0][0]) {
      return track[0][1];
    }
    for (var i = 1; i < track.length; i++) {
      if (t <= track[i][0]) {
        var a = track[i - 1];
        var b = track[i];
        var k = (EASE[b[2] || 'linear'] || EASE.linear)((t - a[0]) / (b[0] - a[0] || 1));
        var v = {};
        Object.keys(ZERO).concat('a' in a[1] || 'a' in b[1] ? ['a'] : []).forEach(function(key) {
          var va = key in a[1] ? a[1][key] : (key in b[1] ? b[1][key] : ZERO[key]);
          var vb = key in b[1] ? b[1][key] : va;
          v[key] = va + (vb - va) * k;
        });
        return v;
      }
    }
    return track[track.length - 1][1];
  }

  /**
   * A move of the plane: to a point, turned and stretched, as [a, b, c, d, e, f].
   *
   * @param {number} x
   * @param {number} y
   * @param {number} deg
   * @param {number} sx
   * @param {number} sy
   * @return {number[]}
   */
  function local(x, y, deg, sx, sy) {
    var r = deg * Math.PI / 180;
    var cos = Math.cos(r);
    var sin = Math.sin(r);
    return [cos * sx, sin * sx, -sin * sy, cos * sy, x, y];
  }

  /**
   * One move after another.
   *
   * @param {number[]} m
   * @param {number[]} n
   * @return {number[]}
   */
  function times(m, n) {
    return [m[0] * n[0] + m[2] * n[1], m[1] * n[0] + m[3] * n[1], m[0] * n[2] + m[2] * n[3], m[1] * n[2] + m[3] * n[3],
      m[0] * n[4] + m[2] * n[5] + m[4], m[1] * n[4] + m[3] * n[5] + m[5]];
  }

  /**
   * A figure.
   *
   * @param {Object} scene
   * @param {string} name What it was loaded as.
   * @param {boolean} still Without movement.
   * @param {string|null} tone The tone of skin (t1, t2, t3), or null for the one it is painted in.
   * @param {string} [from] The texture its pieces are cut from, if not the one it was loaded as (a copy in other colours).
   */
  function Puppet(scene, name, still, tone, from) {
    var self = this;
    var atlas = scene.cache.json.get(name + '.atlas');
    var skeleton = scene.cache.json.get(name + '.skeleton');
    var rig = atlas.meta.rig;
    var key = from || name;
    var texture = scene.textures.get(key);
    var override = rig.override || {};
    this.anims = scene.cache.json.get(name + '.anims');
    this.still = still;
    /** @var {string|null} The tone of skin drawn over the one it is painted in. */
    this.tone = tone || null;
    this.feet = rig.feet;
    /** @var {boolean} Its tool in the left hand: every gesture mirrored; it points to the left of its picture. */
    this.mirror = rig.toolHand === 'l';
    this.dirs = rig.dirs || {};
    this.byTool = (this.anims.byTool || {})[rig.tool] || {};
    this.byName = {};
    this.parts = [];
    // The pieces of its body, and those only this figure has (meta.rig.extra: wings…).
    skeleton.parts.concat(rig.extra || []).forEach(function(s) {
      var f = atlas.frames[s.name];
      var ov = override[s.name] || {};
      if (!f || !rig.joints[s.name]) {
        return;
      }
      if (!texture.has(s.name)) {
        texture.add(s.name, 0, f.frame.x, f.frame.y, f.frame.w, f.frame.h);
      }
      var origin = [f.pivot ? f.pivot.x : 0.5, f.pivot ? f.pivot.y : 0.5];
      var part = {name: s.name, parent: 'parent' in ov ? ov.parent : s.parent, z: 'z' in ov ? ov.z : s.z,
        at: rig.joints[s.name], image: scene.add.image(0, 0, key, s.name).setOrigin(origin[0], origin[1]), over: null};
      // The same piece with the skin in the tone of the student, over it.
      var skin = tone && atlas.frames[s.name + '.skin.' + tone];
      if (skin) {
        if (!texture.has(s.name + '.skin.' + tone)) {
          texture.add(s.name + '.skin.' + tone, 0, skin.frame.x, skin.frame.y, skin.frame.w, skin.frame.h);
        }
        part.over = scene.add.image(0, 0, key, s.name + '.skin.' + tone).setOrigin(origin[0], origin[1]);
      }
      self.parts.push(part);
      self.byName[s.name] = part;
    });
    var drawn = [];
    this.parts.slice().sort(function(a, b) {
      return a.z - b.z;
    }).forEach(function(p) {
      drawn.push(p.image);
      if (p.over) {
        drawn.push(p.over);
      }
    });
    this.container = scene.add.container(0, 0, drawn);
    // Its size is that of the figure it was cut from: what is put over its head goes over it.
    this.container.setSize((skeleton.canvas || rig.canvas)[0], (skeleton.canvas || rig.canvas)[1]);
    this.clip = null;
    this.clipName = '';
    this.time = 0;
    this.play('idle');
    this.draw();
  }

  /**
   * Starts a clip from its beginning; a clip that is already playing goes on, unless it is asked again.
   *
   * @param {string} name
   * @param {boolean} [again] From its beginning even if it is the one playing.
   * @return {boolean} Whether the figure has that clip.
   */
  Puppet.prototype.play = function(name, again) {
    var clip = this.anims.clips[name];
    if (!clip) {
      return false;
    }
    if (this.clipName === name && !again) {
      return true;
    }
    var extra = (this.byTool.clips || {})[name];
    // From one gesture to the next, a moment of passage (a quarter of a second): an arm that was raised comes down, it
    // does not jump.
    this.from = this.clip && !this.still ? this.pose() : null;
    this.fade = 0;
    this.clipName = name;
    this.clip = extra ? Object.assign({}, clip, {tracks: Object.assign({}, clip.tracks, extra)}) : clip;
    this.time = 0;
    return true;
  };

  /**
   * Whether the clip that plays is one that does not loop and has not reached its end.
   *
   * @return {boolean}
   */
  Puppet.prototype.busy = function() {
    return !!this.clip && !this.clip.loop && !this.still && this.time < this.clip.duration;
  };

  /**
   * The pose now: for each piece (and the root), how it is turned, moved and stretched.
   *
   * @return {Object}
   */
  Puppet.prototype.pose = function() {
    var clip = this.clip;
    var base = Object.assign({}, this.anims.base || {}, this.byTool.base || {});
    var rigid = this.byTool.rigid || [];
    var mirror = this.mirror;
    var out = {};
    var t = 0;
    if (clip) {
      if (this.still) {
        t = 'still' in clip ? clip.still : 1;
      } else if (clip.loop) {
        t = (this.time % clip.duration) / clip.duration;
      } else {
        t = Math.min(1, this.time / clip.duration);
      }
    }
    ['root'].concat(Object.keys(this.byName)).forEach(function(name) {
      // Mirrored, the left piece does what the right one would, the other way round.
      var src = mirror ? name.replace(/_(l|r)$/, function(m, side) {
        return side === 'l' ? '_r' : '_l';
      }) : name;
      var v = Object.assign({}, ZERO, base[src] || {});
      if (clip && clip.tracks[src]) {
        Object.assign(v, sample(clip.tracks[src], t));
      }
      if (rigid.indexOf(name) >= 0) {
        delete v.a;
        v.r = 0;
      }
      if (mirror) {
        v.r = -v.r;
        v.x = -v.x;
        if ('a' in v) {
          v.a = 180 - v.a;
        }
      }
      out[name] = v;
    });
    // Passing from the gesture before: each value goes from where it was to where this one wants it, eased.
    var from = this.from;
    if (from && this.fade < FADE) {
      var k = EASE['Sine.easeInOut'](this.fade / FADE);
      Object.keys(out).forEach(function(name) {
        var was = from[name];
        var now = out[name];
        if (!was) {
          return;
        }
        ['x', 'y', 'r', 'sx', 'sy'].forEach(function(f) {
          now[f] = was[f] + (now[f] - was[f]) * k;
        });
        if ('a' in now && 'a' in was) {
          // The shorter way round.
          var d = ((now.a - was.a) % 360 + 540) % 360 - 180;
          now.a = was.a + d * k;
        }
      });
    }
    return out;
  };

  /**
   * Moves on and puts each piece where the pose says, from the feet of the figure.
   *
   * @param {number} [dt] Seconds since the last time.
   */
  Puppet.prototype.draw = function(dt) {
    var self = this;
    this.time += dt || 0;
    this.fade += dt || 0;
    var pose = this.pose();
    var root = pose.root;
    var origin = times(local(root.x, root.y, root.r, root.sx, root.sy), local(-this.feet[0], -this.feet[1], 0, 1, 1));
    var world = {};
    var turned = {};
    // How much each piece has turned from the pose it was cut in: towards a direction, or from its parent.
    var turn = function(p) {
      if (!(p.name in turned)) {
        var v = pose[p.name];
        turned[p.name] = 'a' in v ? v.a - (self.dirs[p.name] || 0) : (up(p) ? turn(up(p)) : 0) + v.r;
      }
      return turned[p.name];
    };
    var up = function(p) {
      return p.parent && self.byName[p.parent] || null;
    };
    var matrixOf = function(p) {
      if (!world[p.name]) {
        var v = pose[p.name];
        var parent = up(p);
        world[p.name] = times(parent ? matrixOf(parent) : origin, local(p.at[0] + v.x, p.at[1] + v.y,
          turn(p) - (parent ? turn(parent) : 0), v.sx, v.sy));
      }
      return world[p.name];
    };
    this.parts.forEach(function(p) {
      var m = matrixOf(p);
      var sx = Math.hypot(m[0], m[1]);
      var sy = sx ? (m[0] * m[3] - m[1] * m[2]) / sx : 0;
      [p.image, p.over].forEach(function(image) {
        if (image) {
          image.setPosition(m[4], m[5]).setRotation(Math.atan2(m[1], m[0])).setScale(sx, sy);
        }
      });
    });
  };

  T.puppet = {
    /**
     * The puppet cut from a picture of the pack (rig.<its key>), with its atlas, the skeleton of its body and the
     * animations; null if the pack does not bring all of it.
     *
     * @param {string|null} picture The key of the picture: character.storyheroes.healer.human.f.1, creature.2.fur…
     * @param {string} body hero or creature.
     * @return {Object|null} image, atlas, skeleton, anims: their addresses.
     */
    find: function(picture, body) {
      if (!picture) {
        return null;
      }
      var key = 'rig.' + picture.replace(/^character\./, '');
      var found = {image: T.artUrl([key]), atlas: T.artUrl([key + '.atlas']), skeleton: T.artUrl(['rig.' + body]),
        anims: T.artUrl(['anim.rig.' + body])};
      return found.image && found.atlas && found.skeleton && found.anims ? found : null;
    },

    /**
     * Asks the engine for a puppet, while the scene loads.
     *
     * @param {Object} scene
     * @param {string} name What it is loaded as.
     * @param {Object} found As find() gives it.
     */
    load: function(scene, name, found) {
      scene.load.image(name, found.image);
      scene.load.json(name + '.atlas', found.atlas);
      scene.load.json(name + '.skeleton', found.skeleton);
      scene.load.json(name + '.anims', found.anims);
    },

    /**
     * The puppet, once loaded; null if something of it did not come or does not fit, or if it does not have the tone of
     * skin asked for (then the picture in that tone is shown).
     *
     * @param {Object} scene
     * @param {string} name
     * @param {boolean} still Without movement.
     * @param {string|null} [tone] t1, t2 or t3; null for the tone it is painted in.
     * @param {string} [from] The texture to cut its pieces from, if not the one it was loaded as.
     * @return {Puppet|null}
     */
    make: function(scene, name, still, tone, from) {
      try {
        var atlas = scene.cache.json.get(name + '.atlas');
        var skeleton = scene.cache.json.get(name + '.skeleton');
        var anims = scene.cache.json.get(name + '.anims');
        if (!scene.textures.exists(name) || !atlas || !atlas.meta || !atlas.meta.rig || !skeleton || !skeleton.parts ||
            !anims || !anims.clips) {
          return null;
        }
        var skin = atlas.meta.rig.skin;
        if (tone && (!skin || (tone !== skin.own && (skin.tones || []).indexOf(tone) < 0))) {
          return null;
        }
        var puppet = new Puppet(scene, name, still, tone && skin && tone !== skin.own ? tone : null,
          from && scene.textures.exists(from) ? from : null);
        return puppet.parts.length ? puppet : null;
      } catch (e) {
        return null;
      }
    }
  };
})();
