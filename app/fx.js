/**
 * Tesapé: what makes the game feel alive: sounds, a soft sound of the place, confetti, hearts, sparkles and a drum
 * roll before a result. The sounds are made by the browser (Web Audio), without files and without anything from outside;
 * they are quiet, wait for the first touch of the student (browsers ask for it) and stop with the button of silence,
 * which is remembered. The movement respects «less motion» of the device.
 *
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var KEY = 'tesape-sound';
  var ctx = null;
  var master = null;
  var sampleOut = null;
  var ambient = null;
  var wanted = null;
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // The teacher took the sound away from the devices of the class.
  var quiet = false;
  // Only the class screen plays music.
  var musicPage = false;

  function muted() {
    if (quiet) {
      return true;
    }
    try {
      return window.localStorage.getItem(KEY) === 'off';
    } catch (e) {
      return false;
    }
  }

  /** The sound of the page, once the student has touched it. */
  function audio() {
    if (muted()) {
      return null;
    }
    if (!ctx) {
      var Context = window.AudioContext || window.webkitAudioContext;
      if (!Context) {
        return null;
      }
      ctx = new Context();
      master = ctx.createGain();
      master.gain.value = 0.45;
      master.connect(ctx.destination);
      // The sounds of the pack come with their level made: they do not go through the one of the sounds made here.
      sampleOut = ctx.createGain();
      sampleOut.gain.value = 1;
      sampleOut.connect(ctx.destination);
    }
    if (ctx.state === 'suspended') {
      ctx.resume();
    }
    return ctx.state === 'running' ? ctx : null;
  }

  function tone(freq, at, length, type, volume, to) {
    var c = audio();
    if (!c) {
      return;
    }
    var start = c.currentTime + at;
    var osc = c.createOscillator();
    var gain = c.createGain();
    osc.type = type || 'sine';
    osc.frequency.setValueAtTime(freq, start);
    if (to) {
      osc.frequency.exponentialRampToValueAtTime(to, start + length);
    }
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(volume || 0.2, start + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + length);
    osc.connect(gain).connect(master);
    osc.start(start);
    osc.stop(start + length + 0.05);
  }

  var noiseBuffer = null;

  function noiseSource(c) {
    if (!noiseBuffer) {
      noiseBuffer = c.createBuffer(1, c.sampleRate * 2, c.sampleRate);
      var data = noiseBuffer.getChannelData(0);
      for (var i = 0; i < data.length; i++) {
        data[i] = Math.random() * 2 - 1;
      }
    }
    var source = c.createBufferSource();
    source.buffer = noiseBuffer;
    return source;
  }

  function noise(at, length, volume, freq, q) {
    var c = audio();
    if (!c) {
      return;
    }
    var start = c.currentTime + at;
    var source = noiseSource(c);
    var filter = c.createBiquadFilter();
    var gain = c.createGain();
    filter.type = 'bandpass';
    filter.frequency.value = freq || 1500;
    filter.Q.value = q || 1;
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(volume || 0.2, start + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + length);
    source.connect(filter).connect(gain).connect(master);
    source.start(start, Math.random());
    source.stop(start + length + 0.05);
  }

  function notes(list, step, type, volume) {
    list.forEach(function(freq, i) {
      tone(freq, i * step, step * 2.4, type, volume);
    });
  }

  /* ---------- Sounds of the world ---------- */

  /*
   * An art pack may bring its own sounds (sound.<name>), the sound of each kind of place (sound.ambient.<kind>) and its
   * music (sound.music): recorded or made for the world. They are loaded after the first touch and used when they are
   * there; until then, and without them, the sounds made here.
   */
  var samples = {};

  function loadSample(key) {
    if (samples[key] || !ctx || !T.artUrl) {
      return;
    }
    var url = T.artUrl([key]);
    if (!url) {
      samples[key] = 'none';
      return;
    }
    samples[key] = 'loading';
    window.fetch(url, {credentials: 'same-origin'}).then(function(r) {
      return r.arrayBuffer();
    }).then(function(data) {
      return new Promise(function(resolve, reject) {
        ctx.decodeAudioData(data, resolve, reject);
      });
    }).then(function(buffer) {
      samples[key] = buffer;
    }).catch(function() {
      samples[key] = 'none';
    });
  }

  /** Loads every sound of the art pack, once the page may sound. */
  function loadSamples() {
    var files = (T.cfg && T.cfg.art && T.cfg.art.files) || {};
    Object.keys(files).forEach(function(key) {
      // The music only where it plays: the class screen.
      if (key.indexOf('sound.') === 0 && (key !== 'sound.music' || musicPage)) {
        loadSample(key);
      }
    });
  }

  /**
   * Plays a sound of the art pack, if it is loaded.
   *
   * @param {string} key
   * @param {AudioNode} out Where it goes.
   * @param {boolean} loop
   * @return {AudioBufferSourceNode|null}
   */
  function playSample(key, out, loop) {
    var buffer = samples[key];
    var c = audio();
    if (!c || !buffer || typeof buffer === 'string') {
      return null;
    }
    var source = c.createBufferSource();
    source.buffer = buffer;
    source.loop = !!loop;
    if (loop) {
      // A loop without a gap: whatever silence the decoding left at the ends stays out of it.
      var ends = silentEnds(buffer);
      source.loopStart = ends[0];
      source.loopEnd = ends[1];
    }
    source.connect(out || sampleOut);
    source.start(0, loop ? source.loopStart : 0);
    return source;
  }

  /**
   * Where a sound really starts and ends, in seconds, without the silence at its ends (up to a tenth of a second).
   *
   * @param {AudioBuffer} buffer
   * @return {number[]}
   */
  function silentEnds(buffer) {
    var data = buffer.getChannelData(0);
    var most = Math.min(data.length >> 1, Math.round(buffer.sampleRate / 10));
    var start = 0;
    var end = data.length;
    while (start < most && Math.abs(data[start]) < 0.0001) {
      start++;
    }
    while (data.length - end < most && Math.abs(data[end - 1]) < 0.0001) {
      end--;
    }
    return [start / buffer.sampleRate, end / buffer.sampleRate];
  }

  /** The sounds, by name. */
  var SOUNDS = {
    tap: function() {
      tone(660, 0, 0.06, 'sine', 0.12);
    },
    choose: function() {
      tone(520, 0, 0.09, 'triangle', 0.16, 720);
    },
    right: function() {
      tone(660, 0, 0.14, 'sine', 0.22);
      tone(990, 0.1, 0.24, 'sine', 0.22);
    },
    wrong: function() {
      tone(260, 0, 0.22, 'sine', 0.16, 190);
    },
    heart: function() {
      tone(440, 0, 0.16, 'sine', 0.18);
      tone(554, 0.13, 0.3, 'sine', 0.18);
    },
    lantern: function() {
      notes([784, 988, 1175, 1568], 0.07, 'sine', 0.16);
      noise(0.25, 0.5, 0.05, 6000, 2);
    },
    levelup: function() {
      notes([523, 659, 784, 1047], 0.09, 'triangle', 0.2);
    },
    hatch: function() {
      notes([880, 1175, 1397, 1760], 0.06, 'sine', 0.14);
      noise(0.2, 0.4, 0.05, 7000, 3);
    },
    hit: function() {
      noise(0, 0.16, 0.35, 280, 0.8);
      tone(120, 0, 0.2, 'square', 0.08, 60);
    },
    win: function() {
      notes([523, 659, 784, 1047, 784, 1047], 0.11, 'triangle', 0.2);
    },
    applause: function() {
      for (var i = 0; i < 70; i++) {
        noise(Math.random() * 1.6, 0.05, 0.05 + Math.random() * 0.05, 1800 + Math.random() * 1500, 1.5);
      }
    },
    path: function() {
      // Steps on the road, and the place lighting up at the end of it.
      for (var i = 0; i < 5; i++) {
        noise(0.3 + i * 0.3, 0.06, 0.08, 900, 2);
      }
      [523, 659, 784, 1047].forEach(function(freq, i) {
        tone(freq, 1.8 + i * 0.12, 0.3, 'sine', 0.12);
      });
    },
    drumroll: function() {
      for (var t = 0; t < 1.1; t += 0.045) {
        noise(t, 0.05, 0.05 + t * 0.12, 220, 1);
      }
      noise(1.1, 0.5, 0.18, 5000, 0.7);
    }
  };

  /* ---------- The soft sound of a place ---------- */

  /** What each kind of place sounds like: wind (a filtered noise that breathes), water, and birds or crickets. */
  var PLACES = {
    meadow: {freq: 900, volume: 0.025, rate: 0.12, chirps: 'bird'},
    forest: {freq: 600, volume: 0.03, rate: 0.09, chirps: 'bird'},
    lake: {freq: 420, volume: 0.04, rate: 0.28, chirps: null},
    mountain: {freq: 700, volume: 0.045, rate: 0.06, chirps: null},
    night: {freq: 500, volume: 0.02, rate: 0.1, chirps: 'cricket'}
  };

  function stopAmbient() {
    if (ambient) {
      clearInterval(ambient.timer);
      try {
        ambient.gain.gain.setTargetAtTime(0.0001, ctx.currentTime, 0.4);
        ambient.source.stop(ctx.currentTime + 2);
      } catch (e) {
        // Already stopped.
      }
      ambient = null;
    }
  }

  function startAmbient(kind) {
    var c = audio();
    var place = PLACES[kind];
    if (!c || !place) {
      return;
    }
    stopAmbient();
    var recorded = samples['sound.ambient.' + kind];
    if (recorded && typeof recorded !== 'string') {
      var level = c.createGain();
      level.gain.value = 0.0001;
      level.gain.setTargetAtTime(0.7, c.currentTime, 1.5);
      level.connect(sampleOut);
      ambient = {kind: kind, source: playSample('sound.ambient.' + kind, level, true), gain: level, timer: 0};
      return;
    }
    var source = noiseSource(c);
    source.loop = true;
    var filter = c.createBiquadFilter();
    filter.type = 'lowpass';
    filter.frequency.value = place.freq;
    var gain = c.createGain();
    gain.gain.value = 0.0001;
    gain.gain.setTargetAtTime(place.volume, c.currentTime, 1.5);
    // The wind or the water breathes slowly.
    var lfo = c.createOscillator();
    var depth = c.createGain();
    lfo.frequency.value = place.rate;
    depth.gain.value = place.volume * 0.6;
    lfo.connect(depth).connect(gain.gain);
    lfo.start();
    source.connect(filter).connect(gain).connect(master);
    source.start();
    var timer = place.chirps ? setInterval(function() {
      if (Math.random() < 0.4) {
        var f = place.chirps === 'bird' ? 2200 + Math.random() * 1200 : 4200;
        tone(f, 0, 0.08, 'sine', 0.025, f * (place.chirps === 'bird' ? 1.3 : 1));
        tone(f * 1.1, 0.12, 0.07, 'sine', 0.02);
      }
    }, 1400) : 0;
    ambient = {kind: kind, source: source, gain: gain, timer: timer};
  }

  /* ---------- Soft music, for the class screen ---------- */

  /*
   * A slow round of four chords, held softly, with a few bell notes of the same scale over them, never the same twice.
   * It is made as it plays, a little ahead, and only on the class screen: on the devices of the students it would be
   * a choir of different songs.
   */
  var MUSIC = 'tesape-music';
  /** The music of each world: its chords, the bells over them, how long each chord lasts and how often a bell rings. */
  var MOODS = {
    // The valley at night: A minor, F, C and G, with warm pads.
    valley: {chords: [[220, 261.63, 329.63], [174.61, 220, 261.63], [261.63, 329.63, 392], [196, 246.94, 293.66]],
      bells: [440, 523.25, 587.33, 659.25, 783.99, 880], bar: 4.8, wave: 'triangle', bell: 0.55, sea: false},
    // The sea: D, G, E minor and A, slower, with the swell of the waves.
    sea: {chords: [[146.83, 220, 369.99], [196, 293.66, 369.99], [164.81, 246.94, 329.63], [220, 277.18, 329.63]],
      bells: [587.33, 659.25, 739.99, 880, 987.77, 1174.66], bar: 5.6, wave: 'sine', bell: 0.45, sea: true},
    // Space: open chords that float, and a few far bells.
    space: {chords: [[130.81, 196, 293.66], [116.54, 174.61, 261.63], [103.83, 155.56, 233.08], [116.54, 174.61, 261.63]],
      bells: [523.25, 622.25, 698.46, 783.99, 932.33, 1046.5], bar: 6.4, wave: 'sine', bell: 0.35, sea: false}
  };
  var mood = MOODS.valley;
  var music = null;

  function pad(c, out, freq, at, length) {
    [0, 3].forEach(function(cents) {
      var osc = c.createOscillator();
      var gain = c.createGain();
      osc.type = mood.wave;
      osc.frequency.value = freq;
      osc.detune.value = cents;
      gain.gain.setValueAtTime(0.0001, at);
      gain.gain.exponentialRampToValueAtTime(0.03, at + 1.4);
      gain.gain.setValueAtTime(0.03, at + length - 1.2);
      gain.gain.exponentialRampToValueAtTime(0.0001, at + length + 0.6);
      osc.connect(gain).connect(out);
      osc.start(at);
      osc.stop(at + length + 0.7);
    });
  }

  function bell(c, out, freq, at) {
    var osc = c.createOscillator();
    var gain = c.createGain();
    osc.type = 'sine';
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(0.035, at + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + 2.2);
    osc.connect(gain).connect(out);
    osc.start(at);
    osc.stop(at + 2.3);
  }

  /** A wave that comes and goes, once in each chord. */
  function swell(c, out, at, length) {
    var source = noiseSource(c);
    var filter = c.createBiquadFilter();
    var gain = c.createGain();
    filter.type = 'lowpass';
    filter.frequency.value = 500;
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(0.05, at + length * 0.45);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + length);
    source.connect(filter).connect(gain).connect(out);
    source.start(at, Math.random());
    source.stop(at + length + 0.1);
  }

  function startMusic(tries) {
    var c = audio();
    if (!c || music) {
      return;
    }
    // The music of the pack may still be on its way: a moment, and then the one made here.
    tries = tries || 0;
    if (samples['sound.music'] === 'loading' && tries < 16) {
      setTimeout(function() {
        if (musicWanted()) {
          startMusic(tries + 1);
        }
      }, 250);
      return;
    }
    var out = c.createGain();
    out.gain.value = 0.0001;
    out.gain.setTargetAtTime(1, c.currentTime, 1.5);
    var recorded = playSample('sound.music', out, true);
    if (recorded) {
      out.connect(sampleOut);
      music = {out: out, timer: 0, source: recorded};
      return;
    }
    var filter = c.createBiquadFilter();
    filter.type = 'lowpass';
    filter.frequency.value = 1800;
    out.connect(filter).connect(master);
    var step = 0;
    var next = c.currentTime + 0.3;
    var plan = function() {
      while (next < c.currentTime + 2) {
        mood.chords[step % mood.chords.length].forEach(function(freq) {
          pad(c, out, freq, next, mood.bar);
        });
        for (var i = 0; i < 4; i++) {
          if (Math.random() < mood.bell) {
            bell(c, out, mood.bells[Math.floor(Math.random() * mood.bells.length)],
              next + i * mood.bar / 4 + Math.random() * 0.3);
          }
        }
        if (mood.sea) {
          swell(c, out, next, mood.bar);
        }
        next += mood.bar;
        step++;
      }
    };
    plan();
    music = {out: out, timer: setInterval(plan, 600)};
  }

  function stopMusic() {
    if (music) {
      clearInterval(music.timer);
      if (music.source) {
        try {
          music.source.stop(ctx.currentTime + 3);
        } catch (e) {
          // Already stopped.
        }
      }
      try {
        music.out.gain.setTargetAtTime(0.0001, ctx.currentTime, 0.6);
      } catch (e) {
        // Already silent.
      }
      var out = music.out;
      setTimeout(function() {
        out.disconnect();
      }, 3000);
      music = null;
    }
  }

  function musicWanted() {
    try {
      return window.localStorage.getItem(MUSIC) === 'on';
    } catch (e) {
      return false;
    }
  }

  /* ---------- What is seen ---------- */

  function layer() {
    var div = document.createElement('div');
    div.className = 'fx-layer';
    div.setAttribute('aria-hidden', 'true');
    document.body.appendChild(div);
    setTimeout(function() {
      div.remove();
    }, 3200);
    return div;
  }

  var COLOURS = ['#ffc857', '#e8508f', '#7cc67f', '#8fd3ff', '#b9a6ff', '#fff1bd'];

  function confetti() {
    if (still) {
      return;
    }
    var div = layer();
    var html = '';
    for (var i = 0; i < 70; i++) {
      html += '<i class="fx-confetti" style="left:' + (Math.random() * 100) + '%;background:' +
        COLOURS[i % COLOURS.length] + ';animation-delay:' + (Math.random() * 0.6) + 's;animation-duration:' +
        (1.8 + Math.random() * 1.2) + 's;transform:rotate(' + Math.floor(Math.random() * 360) + 'deg)"></i>';
    }
    div.innerHTML = html;
  }

  function floating(char, count, cls) {
    if (still) {
      return;
    }
    var div = layer();
    var html = '';
    for (var i = 0; i < count; i++) {
      html += '<i class="' + cls + '" style="left:' + (20 + Math.random() * 60) + '%;animation-delay:' +
        (Math.random() * 0.8) + 's">' + char + '</i>';
    }
    div.innerHTML = html;
  }

  T.fx = {
    /**
     * A sound, by name: tap, choose, right, wrong, heart, lantern, levelup, hatch, hit, win, applause, drumroll, path.
     *
     * @param {string} name
     */
    sound: function(name) {
      if (!playSample('sound.' + name) && SOUNDS[name]) {
        SOUNDS[name]();
      }
    },
    /**
     * The soft sound of a kind of place (meadow, forest, lake, mountain, night), or none. It waits for the first touch.
     *
     * @param {string|null} kind
     */
    ambient: function(kind) {
      wanted = kind || null;
      if (!wanted) {
        stopAmbient();
      } else if (!ambient || ambient.kind !== wanted) {
        startAmbient(wanted);
      }
    },
    confetti: confetti,
    hearts: function() {
      floating('❤', 12, 'fx-heart');
    },
    sparkles: function() {
      floating('✦', 16, 'fx-sparkle');
    },
    /**
     * The drum roll before a result: heard and seen on an element, for a moment.
     *
     * @param {HTMLElement} el
     */
    drumroll: function(el) {
      if (!playSample('sound.drumroll')) {
        SOUNDS.drumroll();
      }
      if (el && !still) {
        el.classList.add('fx-drumroll');
        setTimeout(function() {
          el.classList.remove('fx-drumroll');
        }, 1200);
      }
    },
    /**
     * The kind of soft sound of a place of the valley, by its code or its landscape.
     *
     * @param {string} place
     * @return {string}
     */
    place: function(place) {
      return {claro: 'meadow', biblioteca: 'meadow', bosque: 'forest', jardin: 'meadow', lago: 'lake',
        torre: 'mountain'}[place] || 'meadow';
    },
    muted: muted,
    /** No movement on this page: no confetti, hearts, sparkles or drum roll to see. */
    still: function() {
      still = true;
    },
    /**
     * The soft music of the class screen: whether it is on, remembered on the device.
     *
     * @return {boolean}
     */
    musicOn: function() {
      return musicWanted() && !muted();
    },
    /**
     * Music on or off on this device. It starts at once if the page may sound, or at the first touch.
     *
     * @return {boolean} Whether it is on now.
     */
    music: function() {
      var on = !musicWanted();
      try {
        window.localStorage.setItem(MUSIC, on ? 'on' : 'off');
      } catch (e) {
        // Only for this page.
      }
      if (on && audio() === null && ctx) {
        // The browser lets the page sound a moment after the touch.
        ctx.resume().then(function() {
          startMusic();
        });
      } else if (on) {
        startMusic();
      } else {
        stopMusic();
      }
      return on;
    },
    /**
     * This page plays the music the device remembers, if it was on, as soon as browsers let it sound.
     *
     * @param {string} [world] The music of the world: valley, sea or space.
     */
    musicHere: function(world) {
      musicPage = true;
      mood = MOODS[world] || MOODS.valley;
      if (musicWanted()) {
        startMusic();
      }
    },
    /**
     * No sound at all on this device, because the teacher said so for the class.
     *
     * @param {boolean} on
     */
    quiet: function(on) {
      quiet = !!on;
      if (quiet) {
        stopAmbient();
      }
    },
    /** Silence on or off, remembered on the device. */
    toggle: function() {
      try {
        window.localStorage.setItem(KEY, muted() ? 'on' : 'off');
      } catch (e) {
        // Without storage, only for this page.
      }
      if (muted()) {
        stopAmbient();
        stopMusic();
        if (ctx) {
          ctx.suspend();
        }
      } else if (wanted) {
        startAmbient(wanted);
      }
      return !muted();
    }
  };

  // Browsers let a page sound only after the first touch: then the sound of the place starts.
  document.addEventListener('pointerdown', function() {
    if (audio()) {
      loadSamples();
    }
    if (audio() && wanted && !ambient) {
      startAmbient(wanted);
    }
    if (musicPage && !music && musicWanted() && !muted()) {
      audio();
      if (ctx) {
        // The browser lets the page sound a moment after the touch.
        ctx.resume().then(function() {
          startMusic();
        });
      }
    }
  }, {passive: true});
})();
