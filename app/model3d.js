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
 * The characters in live 3D: a model of the art packs (mdl.<key>, a GLB with its gestures as animations, its face in
 * mdl.<key>.cara and the light of each place in mdl.luz) stands where its puppet would, with the same calls (play,
 * busy, draw), so the place, the walk and the fights do not tell them apart. They are drawn by three.js in a layer of
 * their own over the place, with a camera in the units of the place: each model where the engine puts its figure, as
 * tall as its puppet, turned three quarters to the side it faces. Two tones of the light of the place (its lit and its
 * shaded colour) instead of lights, as cheap as a picture for a weak device. The face blinks and, while it talks, moves
 * its mouth. Without movement, each gesture stays in its still pose. Only with WebGL 2; without it, the sheets or the
 * puppets. three.js is loaded only when the packs bring models.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var cfg = T.cfg;
  /** @var {Object} How tall the puppet each body replaces stands in its canvas: a model is as tall in the place. */
  var BODIES = {
    person: {canvas: [768, 896], feet: [384, 860], tall: 790},
    small: {canvas: [512, 512], feet: [256, 457], tall: 430}
  };
  /**
   * @var {number} Metres of a grown-up as tall as the puppet of a grown-up who speaks (the druid; FORMATO.md of Gaudí,
   *     «Las medidas»): the models are measured in metres, so each one keeps its own height (a dwarf, shorter than a
   *     druid; the creature, its size beside them).
   */
  var PERSON = 1.7;
  /** @var {number} How much taller than a hero the puppet of a grown-up who speaks is drawn (sceneworld.js). */
  var GROWN = 1.1;
  /** @var {Object} For a state of the face that a model lacks, the nearest (FORMATO.md of Gaudí). */
  var NEAREST = {wide: 'open', happy: 'closed', laugh: 'a'};

  /**
   * How the eyes are at a moment of a blink: half, closed for three frames at 24 a second, half.
   *
   * @param {number} b Seconds since the blink began; before it or after it, open.
   * @return {string}
   */
  function blinkAt(b) {
    return b < 0 || b >= 5 / 24 ? 'open' : b < 1 / 24 || b >= 4 / 24 ? 'half' : 'closed';
  }
  /** @var {number} How much a model turns to the side it faces, in radians: three quarters. */
  var YAW = 0.45;
  /** @var {number} Seconds of passage from one gesture to the next. */
  var FADE = 0.25;
  /** @var {Object} The light of a place without its colours in mdl.luz. */
  var DEFAULT = {key: '#fff1d6', shade: '#b9c0c8'};
  var loading = null;
  var webgl = null;
  var models = {};
  var faces = {};
  var lights = null;

  /**
   * The files of the art packs.
   *
   * @return {Object}
   */
  function files() {
    return (cfg.art && cfg.art.files) || {};
  }

  /**
   * Whether the device can draw the models: WebGL 2, and not the light art (little memory, saving data, or a class whose
   * teacher asked for it): there, the library and the models are not brought, and the puppets stand.
   *
   * @return {boolean}
   */
  function canDraw() {
    if (T.lightArt) {
      return false;
    }
    if (webgl === null) {
      try {
        webgl = !!document.createElement('canvas').getContext('webgl2');
      } catch (e) {
        webgl = false;
      }
    }
    return webgl;
  }

  /**
   * Fetches a file of the packs once, as JSON or as bytes.
   *
   * @param {Object} cache
   * @param {string} url
   * @param {boolean} bytes
   * @return {Promise}
   */
  function once(cache, url, bytes) {
    if (!cache[url]) {
      cache[url] = window.fetch(url, {credentials: 'same-origin'}).then(function(r) {
        if (!r.ok) {
          throw new Error(url);
        }
        return bytes ? r.arrayBuffer() : r.json();
      });
    }
    return cache[url];
  }

  /**
   * The model of a figure, parsed once: its scene, its gestures and what each gesture says of itself (extras).
   *
   * @param {string} url
   * @return {Promise}
   */
  function gltfOf(url) {
    var key = 'gltf:' + url;
    if (!models[key]) {
      models[key] = once(models, url, true).then(function(bytes) {
        return new Promise(function(resolve, reject) {
          var loader = new window.THREE.GLTFLoader();
          loader.setMeshoptDecoder(window.THREE.MeshoptDecoder);
          loader.parse(bytes, '', resolve, reject);
        });
      });
    }
    return models[key];
  }

  /**
   * The two tones of the light of a place on a model: the lit side towards the key colour, the side in shade towards the
   * shade colour (three steps for a drawn model, a soft swing for a painted one). One multiply, no light loop.
   *
   * @param {Object} src The material of the model.
   * @param {boolean} painted Its texture carries its own shading.
   * @param {Object} light key, shade and sunView of the layer.
   * @return {Object}
   */
  function toonMaterial(src, painted, light) {
    var THREE = window.THREE;
    // The two pieces of the face share one texture: each one, its own copy, to move its offset.
    var map = src.map && src.name.indexOf('face_') === 0 ? src.map.clone() : src.map;
    var m = new THREE.MeshBasicMaterial({map: map, color: 0xffffff, side: src.side, transparent: src.transparent,
      alphaTest: src.alphaTest});
    m.name = src.name;
    m.onBeforeCompile = function(sh) {
      sh.uniforms.uSun = {value: light.sunView};
      sh.uniforms.uKey = {value: light.key};
      sh.uniforms.uShade = {value: light.shade};
      sh.vertexShader = sh.vertexShader
        .replace('#include <common>', '#include <common>\nvarying vec3 vToonN;')
        .replace('#include <project_vertex>', '#include <project_vertex>\n\tvToonN = normalize( transformedNormal );');
      var band = painted ? 'float b = smoothstep( -0.12, 0.38, ndl ); vec3 lt = mix( mix( uKey, uShade, 0.4 ) * 0.9, uKey, b );' :
        'float fw = fwidth( ndl ) + 0.015;\n\t\tfloat b1 = smoothstep( 0.06 - fw, 0.06 + fw, ndl );\n\t\t' +
        'float b2 = smoothstep( 0.66 - fw, 0.66 + fw, ndl );\n\t\tvec3 lt = mix( uShade, uKey, b1 ) * ( 1.0 + 0.07 * b2 );';
      sh.fragmentShader = sh.fragmentShader
        .replace('#include <common>', '#include <common>\nvarying vec3 vToonN;\nuniform vec3 uSun;\nuniform vec3 uKey;\n' +
          'uniform vec3 uShade;')
        .replace('vec3 outgoingLight = reflectedLight.indirectDiffuse;',
          'vec3 outgoingLight = reflectedLight.indirectDiffuse;\n\t{\n\t\tfloat ndl = dot( normalize( vToonN ) * ' +
          '( gl_FrontFacing ? 1.0 : -1.0 ), uSun );\n\t\t' + band + '\n\t\toutgoingLight *= lt;\n\t}');
    };
    return m;
  }

  /**
   * The line around a drawn model: its shell (faces turned inwards, the colour of the line per vertex), pushed a little
   * in pixels of the screen, so the line keeps its width when the figure is small.
   *
   * @param {Object} src
   * @param {Object} outline uPx and uRes of the layer.
   * @return {Object}
   */
  function outlineMaterial(src, outline) {
    var THREE = window.THREE;
    var m = new THREE.MeshBasicMaterial({vertexColors: true, color: 0xffffff, side: THREE.FrontSide});
    m.name = src.name;
    m.onBeforeCompile = function(sh) {
      sh.uniforms.uPx = outline.uPx;
      sh.uniforms.uRes = outline.uRes;
      sh.vertexShader = sh.vertexShader
        .replace('#include <common>', '#include <common>\nuniform float uPx;\nuniform vec2 uRes;')
        .replace('#include <project_vertex>', '#include <project_vertex>\n\t{\n\t\tvec4 nc = projectionMatrix * ' +
          'vec4( -transformedNormal, 0.0 );\n\t\tvec2 d = nc.xy / max( length( nc.xy ), 1e-5 );\n\t\t' +
          'gl_Position.xy += d * uPx * 2.0 / uRes * gl_Position.w;\n\t}');
    };
    return m;
  }

  /**
   * What stands in front of the place (branches, the edge of a shelf: the picture the engine draws over everything),
   * drawn again in the layer of the models, in front of them, so the hero still passes behind it; the one of the engine
   * is hidden, not to draw it twice.
   *
   * @param {Object} image The picture of the engine, with its origin at the top left.
   * @return {Object} A plane of three.js.
   */
  function frontOf(image) {
    var THREE = window.THREE;
    var map = new THREE.Texture(image.texture.getSourceImage());
    map.colorSpace = THREE.SRGBColorSpace;
    map.needsUpdate = true;
    var w = image.width * image.scaleX;
    var h = image.height * image.scaleY;
    var plane = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({map: map, transparent: true,
      depthTest: false, depthWrite: false}));
    plane.position.set(image.x + w / 2, -(image.y + h / 2), 9000);
    plane.renderOrder = 10;
    image.setVisible(false);
    return plane;
  }

  /**
   * The layer of the models of a place, made once: a canvas of its own over the one of the place, the same size, with
   * a camera in the units of the place; drawn after each update of the place.
   *
   * @param {Object} scene The scene of the place.
   * @return {Object}
   */
  function layerOf(scene) {
    if (scene.modelLayer) {
      return scene.modelLayer;
    }
    var THREE = window.THREE;
    var game = scene.game;
    var host = game.canvas.parentNode;
    if (window.getComputedStyle(host).position === 'static') {
      host.style.position = 'relative';
    }
    var canvas = document.createElement('canvas');
    canvas.className = 'model-layer';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.position = 'absolute';
    canvas.style.pointerEvents = 'none';
    host.insertBefore(canvas, game.canvas.nextSibling);
    var renderer = new THREE.WebGLRenderer({canvas: canvas, alpha: true, antialias: true});
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    var camera = new THREE.OrthographicCamera(0, 1, 0, -1, -10000, 10000);
    camera.position.set(0, 0, 5000);
    var layer = {
      scene: new THREE.Scene(),
      camera: camera,
      renderer: renderer,
      models: [],
      // The sun from the upper left, as the light of every place and of every sheet.
      light: {key: new THREE.Color(DEFAULT.key), shade: new THREE.Color(DEFAULT.shade),
        sunView: new THREE.Vector3(-0.55, 0.75, 0.38).normalize()},
      outline: {uPx: {value: 0.6}, uRes: {value: new THREE.Vector2(1, 1)}}
    };
    var fit = function() {
      var r = game.canvas.getBoundingClientRect();
      var p = host.getBoundingClientRect();
      canvas.style.left = (r.left - p.left) + 'px';
      canvas.style.top = (r.top - p.top) + 'px';
      canvas.style.width = r.width + 'px';
      canvas.style.height = r.height + 'px';
      renderer.setSize(r.width, r.height, false);
      camera.left = 0;
      camera.right = scene.scale.width;
      camera.top = 0;
      camera.bottom = -scene.scale.height;
      camera.updateProjectionMatrix();
      layer.outline.uRes.value.set(r.width * renderer.getPixelRatio(), r.height * renderer.getPixelRatio());
    };
    // Once the place is drawn, with its camera where it is now (a place that follows the hero scrolls and zooms).
    var draw = function() {
      var view = scene.cameras && scene.cameras.main && scene.cameras.main.worldView;
      if (view && view.width) {
        camera.left = view.x;
        camera.right = view.x + view.width;
        camera.top = -view.y;
        camera.bottom = -(view.y + view.height);
        camera.updateProjectionMatrix();
      }
      if (scene.front && !layer.front) {
        layer.front = frontOf(scene.front);
        layer.scene.add(layer.front);
      }
      layer.models.forEach(function(m) {
        m.sync();
      });
      renderer.render(layer.scene, camera);
    };
    var gone = function() {
      scene.events.off('render', draw);
      scene.scale.off('resize', fit);
      window.removeEventListener('resize', fit);
      renderer.dispose();
      canvas.remove();
      scene.modelLayer = null;
    };
    fit();
    scene.scale.on('resize', fit);
    window.addEventListener('resize', fit);
    scene.events.on('render', draw);
    scene.events.once('shutdown', gone);
    scene.events.once('destroy', gone);
    scene.modelLayer = layer;
    T.model.place(scene, scene.modelPlace || null);
    return layer;
  }

  /**
   * A figure in live 3D.
   *
   * @param {Object} scene
   * @param {Object} found As find() gives it.
   * @param {boolean} still Without movement.
   * @param {string} [body] The kind of puppet it stands for.
   */
  function Model(scene, found, still, body) {
    var self = this;
    var small = body === 'creature' || body === 'minion';
    this.scene = scene;
    this.still = still;
    this.small = small;
    this.body = BODIES[small ? 'small' : 'person'];
    this.feet = this.body.feet.slice();
    this.mirror = false;
    this.tone = null;
    this.clipName = '';
    this.want = 'idle';
    this.lift = 0;
    this.root = null;
    // Where the engine puts it: an empty figure of the size of its puppet; the model follows it.
    this.container = scene.add.container(0, 0);
    this.container.setSize(this.body.canvas[0], this.body.canvas[1]);
    // As a puppet, one piece, for what reads them.
    this.id = found.id;
    this.parts = [{name: 'body', frame: 'body', image: {texture: {key: 'model:' + found.id}}}];
    this.layer = layerOf(scene);
    this.blink = 2 + Math.random() * 3;
    this.blinking = -1;
    this.talkClock = 0;
    Promise.all([gltfOf(found.glb), found.face ? once(faces, found.face, false).catch(function() {
      return null;
    }) : null]).then(function(got) {
      if (self.container.scene) {
        self.attach(got[0], got[1]);
      }
    }).catch(function(e) {
      // Without its model, nothing stands there: the scene goes on, and the console says why.
      window.console.warn('Tesapé 3D: ' + found.id, e && e.message ? e.message : e);
    });
  }

  /**
   * The model, once parsed: a copy of its scene with the materials of the place, in the layer.
   *
   * @param {Object} gltf
   * @param {Object|null} face The states of its face.
   */
  Model.prototype.attach = function(gltf, face) {
    var THREE = window.THREE;
    var self = this;
    var root = THREE.SkeletonUtils.clone(gltf.scene);
    var painted = true;
    root.traverse(function(o) {
      if (o.isMesh && o.material && o.material.name === 'outline') {
        painted = false;
      }
    });
    root.traverse(function(o) {
      if (!o.isMesh) {
        return;
      }
      o.frustumCulled = false;
      o.material = o.material.name === 'outline' ? outlineMaterial(o.material, self.layer.outline) :
        toonMaterial(o.material, painted, self.layer.light);
      if (o.material.name === 'face_eyes') {
        self.eyes = o;
      } else if (o.material.name === 'face_mouth') {
        self.mouth = o;
      }
    });
    var box = new THREE.Box3().setFromObject(root);
    this.metres = Math.max(0.01, box.max.y);
    // A small one who speaks (a bird) has to be seen: how many times its metres it stands in the place (FORMATO.md).
    this.presence = Number((gltf.scene && gltf.scene.userData && gltf.scene.userData.presence) || 1);
    // For the tests of the browser: how tall each model stands, in metres.
    T.model.metres[this.id] = Math.round(this.metres * 100) / 100;
    this.face = face;
    this.mixer = new THREE.AnimationMixer(root);
    this.clips = {};
    this.info = {};
    var described = (gltf.parser && gltf.parser.json && gltf.parser.json.animations) || [];
    gltf.animations.forEach(function(c, i) {
      self.clips[c.name] = c;
      self.info[c.name] = (described[i] || {}).extras || {};
    });
    this.actions = {};
    this.root = root;
    this.layer.scene.add(root);
    this.layer.models.push(this);
    var want = this.want || 'idle';
    this.clipName = '';
    this.play(this.clips[want] ? want : 'idle', true);
    this.sync();
  };

  /**
   * Whether a gesture loops (rest, walk, talk, think), as the model says, or by its name.
   *
   * @param {string} name
   * @return {boolean}
   */
  Model.prototype.loops = function(name) {
    var info = (this.info || {})[name] || {};
    return 'loop' in info ? !!info.loop : ['idle', 'walk', 'talk', 'think'].indexOf(name) >= 0;
  };

  /**
   * Starts a gesture from its beginning, passing from the one before in a quarter of a second; one already playing goes
   * on, unless it is asked again. Before the model has come, the gesture is kept for when it comes.
   *
   * @param {string} name
   * @param {boolean} [again]
   * @return {boolean} Whether the figure has that gesture.
   */
  Model.prototype.play = function(name, again) {
    if (!this.root) {
      this.want = name;
      return true;
    }
    var clip = this.clips[name];
    if (!clip) {
      return false;
    }
    if (this.clipName === name && !again && this.current) {
      return true;
    }
    var THREE = window.THREE;
    var action = this.actions[name];
    if (!action) {
      action = this.actions[name] = this.mixer.clipAction(clip);
      if (!this.loops(name)) {
        action.setLoop(THREE.LoopOnce, 1);
        action.clampWhenFinished = true;
      }
    }
    action.reset().setEffectiveWeight(1).play();
    if (this.still) {
      // Without movement, its still pose (seconds into the gesture), held.
      var still = (this.info[name] || {}).still;
      action.time = typeof still === 'number' ? still : 0;
      if (this.current && this.current !== action) {
        this.current.stop();
      }
      this.mixer.update(0);
      action.paused = true;
    } else if (this.current && this.current !== action) {
      this.current.crossFadeTo(action, FADE, false);
    }
    this.current = action;
    this.clipName = name;
    return true;
  };

  /**
   * Whether a gesture that does not loop has not reached its end.
   *
   * @return {boolean}
   */
  Model.prototype.busy = function() {
    return !this.still && !!this.current && !this.loops(this.clipName) && this.current.isRunning();
  };

  /**
   * Moves on: the gesture, and the face (a blink every few seconds; while it talks, the mouth).
   *
   * @param {number} [dt] Seconds since the last time.
   */
  Model.prototype.draw = function(dt) {
    if (!this.root || this.still) {
      return;
    }
    // Walking, its steps at the pace it goes forward (speed of the gesture, in metres per second).
    var walk = this.clipName === 'walk' && this.info.walk && this.info.walk.speed;
    var rate = T.sheet.pace(this, dt, walk ? walk * this.perMetre() : 0);
    if (this.current) {
      this.current.timeScale = rate;
    }
    this.mixer.update(dt || 0);
    if (!this.face) {
      return;
    }
    var clip = (this.face.clips || {})[this.clipName] || {};
    var t = this.current ? this.current.time : 0;
    // The eyes: open, with a loose blink every few seconds, those of the gesture and what the gesture asks of them.
    this.blink -= dt || 0;
    if (this.blink < 0) {
      this.blinking = 0;
      this.blink = 2.5 + Math.random() * 3;
    }
    var eyes = 'open';
    if (this.blinking >= 0) {
      this.blinking += dt || 0;
      eyes = blinkAt(this.blinking);
      if (this.blinking >= 5 / 24) {
        this.blinking = -1;
      }
    }
    (clip.blink || []).forEach(function(at) {
      eyes = eyes === 'open' ? blinkAt(t - at) : eyes;
    });
    (clip.eyes || []).forEach(function(e) {
      if (t >= e[0] && t < e[1]) {
        eyes = e[2];
      }
    });
    // The mouth: what the gesture says; while it talks without saying, one of its own.
    var mouth = 'closed';
    if ((clip.mouth || []).length) {
      clip.mouth.forEach(function(m) {
        if (t >= m[0] && t < m[1]) {
          mouth = m[2];
        }
      });
    } else if (this.clipName === 'talk') {
      this.talkClock += dt || 0;
      mouth = ['a', 'o', 'closed', 'a', 'closed', 'o'][Math.floor(this.talkClock / 0.11) % 6];
    }
    this.setFace(eyes, mouth);
  };

  /**
   * The face of the model: its eyes and its mouth, by the offset of their textures.
   *
   * @param {string} eyes open, half or closed.
   * @param {string} mouth closed, a, o or smile.
   */
  Model.prototype.setFace = function(eyes, mouth) {
    var face = this.face;
    this.faceNow = eyes + ' ' + mouth;
    [[this.eyes, 'eyes', eyes], [this.mouth, 'mouth', mouth]].forEach(function(one) {
      var states = one[0] && one[0].material.map && face[one[1]] && face[one[1]].states;
      // A state this face lacks (it was made before that one): the nearest it has.
      var off = states && (states[one[2]] || states[NEAREST[one[2]]]);
      if (off) {
        one[0].material.map.offset.set(off[0], off[1]);
      }
    });
  };

  /**
   * The model where the engine has put its figure: at its feet, in metres of the place (perMetre), turned
   * three quarters to the side it faces, shown when it shows.
   */
  Model.prototype.sync = function() {
    var c = this.container;
    if (!this.root) {
      return;
    }
    this.root.visible = !!c.scene && c.visible && c.alpha > 0.05;
    var k = this.perMetre();
    this.root.position.set(c.x, -c.y, c.depth || 0);
    this.root.scale.set(k, k, k);
    this.root.rotation.set(0, c.scaleX < 0 ? -YAW : YAW, 0);
  };

  /**
   * Units of the place per metre where the model stands: from the scale the engine gives its figure (that of a hero, or
   * of the creature, which it draws at half the size of a person in a canvas of 512), the same for everyone on the same
   * ground; a grown-up of 1.7 m as tall as the puppet of a grown-up.
   *
   * @return {number}
   */
  Model.prototype.perMetre = function() {
    var c = this.container;
    var perPerson = this.small ? Math.abs(c.scaleY) * 1024 : Math.abs(c.scaleY) * BODIES.person.canvas[1];
    return perPerson * GROWN * (BODIES.person.tall / BODIES.person.canvas[1]) / PERSON * (this.presence || 1);
  };

  T.model = {
    /** @var {Object} How tall each model stands, in metres, once it has come (for the tests). */
    metres: {},

    /**
     * Whether a figure is a model: it brings its height in metres, so the place does not make it taller.
     *
     * @param {Object} [puppet] What stands for the figure (a puppet, sheets or a model).
     * @return {boolean}
     */
    is: function(puppet) {
      return puppet instanceof Model;
    },

    /**
     * The model of a figure in the art packs, if the device can draw it; null if not.
     *
     * @param {string} id The key of the figure: creature.2.fur, storyheroes.healer.dwarf.f.3, figure.druida-mayor…
     * @return {Object|null} glb, face and id.
     */
    find: function(id) {
      var f = files();
      var glb = f['mdl.' + id];
      if (!glb || !/\.glb(\?|$)/.test(glb) || !canDraw()) {
        return null;
      }
      return {glb: glb, face: f['mdl.' + id + '.cara'] || null, id: id};
    },

    /**
     * Whether the packs bring models the device can draw (so three.js is loaded with the engine).
     *
     * @return {boolean}
     */
    wanted: function() {
      var f = files();
      return canDraw() && Object.keys(f).some(function(k) {
        return k.indexOf('mdl.') === 0 && /\.glb(\?|$)/.test(f[k]);
      });
    },

    /**
     * Loads three.js, once.
     *
     * @return {Promise}
     */
    load: function() {
      if (window.THREE && window.THREE.GLTFLoader) {
        return Promise.resolve(window.THREE);
      }
      if (!loading) {
        loading = new Promise(function(resolve, reject) {
          // A page with a loader of modules would keep the library to itself: for a moment, there is none.
          var amd = window.define && window.define.amd;
          if (amd) {
            window.define.amd = false;
          }
          var script = document.createElement('script');
          script.src = cfg.wwwroot + '/local/oksigeniatesape/app/engine/three.min.js?v=' + (cfg.version || 0);
          var done = function() {
            if (amd) {
              window.define.amd = amd;
            }
          };
          script.onload = function() {
            done();
            if (window.THREE && window.THREE.GLTFLoader) {
              resolve(window.THREE);
            } else {
              loading = null;
              reject(new Error('three'));
            }
          };
          script.onerror = function() {
            done();
            loading = null;
            reject(new Error('three'));
          };
          document.head.appendChild(script);
        });
      }
      return loading;
    },

    /**
     * Starts fetching a model while the place loads.
     *
     * @param {Object} found As find() gives it.
     */
    prefetch: function(found) {
      if (window.THREE && window.THREE.GLTFLoader) {
        gltfOf(found.glb).catch(function() {
          // It will be tried again when the figure is made.
        });
      }
    },

    /**
     * The figure of a model; null if three.js did not come.
     *
     * @param {Object} scene
     * @param {Object} found As find() gives it.
     * @param {boolean} still
     * @param {string} [body]
     * @return {Model|null}
     */
    make: function(scene, found, still, body) {
      return window.THREE && window.THREE.GLTFLoader ? new Model(scene, found, still, body) : null;
    },

    /**
     * The light of the place on its models: the colours of its painting (mdl.luz, by the key of the painting).
     *
     * @param {Object} scene
     * @param {string|null} url The painting shown.
     */
    place: function(scene, url) {
      if (!scene) {
        return;
      }
      // Kept for the layer, if it comes later.
      scene.modelPlace = url;
      var layer = scene.modelLayer;
      var f = files();
      if (!layer || !f['mdl.luz']) {
        return;
      }
      var key = Object.keys(f).filter(function(k) {
        return f[k] === url;
      })[0];
      if (!lights) {
        lights = once({}, f['mdl.luz'], false).catch(function() {
          return {};
        });
      }
      lights.then(function(all) {
        var one = (all || {})[key] || DEFAULT;
        layer.light.key.set(one.key || DEFAULT.key);
        layer.light.shade.set(one.shade || DEFAULT.shade);
      });
    }
  };
})();
