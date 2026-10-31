/**
 * Tesapé: what makes the game feel alive: sounds, a soft sound of the place, confetti, hearts, sparkles and a drum
 * roll before a result. The sounds are made by the browser (Web Audio), without files and without anything from outside;
 * they are quiet, wait for the first touch of the student (browsers ask for it) and stop with the button of silence,
 * which is remembered. The movement respects «less motion» of the device.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var KEY = 'tesape-sound';
  /** @var {string} The volume of the device, one for all the games of Oksigenia on this site: 0 to 1. */
  var VOLUME = 'oksigenia-volume';
  /** @var {number} Low to start: in a class, many devices sound at once, and a voice that comes loud scares the youngest. */
  var VOLUME_START = 0.4;
  var ctx = null;
  var master = null;
  var sampleOut = null;
  var ambient = null;
  var wanted = null;
  /** The place whose own sound is wanted (sound.ambient.<place>), if the pack has one. */
  var wantedPlace = null;
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // The teacher took the sound away from the devices of the class.
  var quiet = false;
  // Only the class screen plays music.
  var musicPage = false;
  /** The class screen: the sounds of the stage of the battle are only heard there, and only it fetches them. */
  var stage = false;
  var STAGE = ['battle.start', 'counter', 'escape', 'jar', 'phase2', 'shield', 'sting.win', 'sting.lost', 'tick'];
  /**
   * The sounds of the big moments (a level, a lantern, the creature hatching): on a tablet they are not loaded ahead, but
   * the first time they sound, since they come with a moment that opens slowly; a session where none comes does not
   * download them.
   */
  var RARE = ['applause', 'drumroll', 'hatch', 'heart', 'lantern', 'levelup', 'path', 'win'];

  /**
   * The volume of the device, as it was left; low the first time.
   *
   * @return {number} 0 to 1.
   */
  function volume() {
    try {
      var v = parseFloat(window.localStorage.getItem(VOLUME));
      return isNaN(v) ? VOLUME_START : Math.min(1, Math.max(0, v));
    } catch (e) {
      return VOLUME_START;
    }
  }

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
      master.gain.value = 0.45 * volume();
      master.connect(ctx.destination);
      // The sounds of the pack come with their level made: they do not go through the one of the sounds made here.
      sampleOut = ctx.createGain();
      sampleOut.gain.value = volume();
      sampleOut.connect(ctx.destination);
    }
    // Suspended, or interrupted (a call, the tablet locked, on iPad): it comes back at the next touch.
    if (ctx.state !== 'running' && ctx.state !== 'closed') {
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
   * music (sound.music): recorded or made for the world. The short ones are loaded after the first touch, and the sound
   * of a place when the student is there; they are used when they are there and, until then, the sounds made here.
   */
  var samples = {};

  /**
   * Loads a sound of the art pack, once.
   *
   * @param {string} key
   * @param {Function} [done] Called when it is ready.
   */
  function loadSample(key, done) {
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
      if (done) {
        done();
      }
    }).catch(function() {
      samples[key] = 'none';
    });
  }

  /**
   * Loads the short sounds of the art pack, once the page may sound. The sounds of the places are long: each one when
   * the student is there; and the music only where it plays, the class screen.
   */
  function loadSamples() {
    var files = (T.cfg && T.cfg.art && T.cfg.art.files) || {};
    Object.keys(files).forEach(function(key) {
      // The pieces of music (the soft one and those of the battle) ahead only on the class screen; on a tablet, the one
      // that is going to play, when it plays.
      if ((key.indexOf('sound.') === 0 && key.indexOf('sound.ambient.') !== 0 && key.indexOf('sound.music') !== 0 &&
          (stage || (STAGE.indexOf(key.slice(6)) < 0 && RARE.indexOf(key.slice(6)) < 0))) ||
          (key.indexOf('sound.music') === 0 && stage)) {
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
    // A touch of the teacher with a reason: three soft bells going up.
    chime: function() {
      tone(784, 0, 0.5, 'sine', 0.1);
      tone(988, 0.12, 0.5, 'sine', 0.1);
      tone(1319, 0.24, 0.7, 'sine', 0.09);
    },
    // What was won reaching its counter: two bright notes.
    coin: function() {
      tone(988, 0, 0.07, 'triangle', 0.12);
      tone(1319, 0.07, 0.12, 'triangle', 0.12);
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
    // The little laugh of the Embrollo when it catches a slip in a minigame.
    giggle: function() {
      tone(260, 0, 0.18, 'sine', 0.12, 200);
      [620, 560, 640, 540].forEach(function(freq, i) {
        tone(freq, 0.16 + i * 0.075, 0.06, 'triangle', 0.08, freq - 60);
      });
    },
    // An animal that gets into the nest: a rush of leaves and a soft bump.
    near: function() {
      noise(0, 0.22, 0.1, 700, 1.2);
      tone(150, 0.16, 0.2, 'sine', 0.16, 90);
    },
    // The creature chirps: the streak has started.
    chirp: function() {
      tone(1800, 0, 0.06, 'sine', 0.07, 2400);
      tone(2000, 0.09, 0.07, 'sine', 0.07, 2700);
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
        if (ambient.lfo) {
          ambient.lfo.stop(ctx.currentTime + 2);
        }
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
    var own = wantedPlace && 'sound.ambient.' + wantedPlace;
    var key = own && T.artUrl && T.artUrl([own]) ? own : 'sound.ambient.' + kind;
    var recorded = samples[key];
    if (recorded && typeof recorded !== 'string') {
      var level = c.createGain();
      level.gain.value = 0.0001;
      level.gain.setTargetAtTime(0.7, c.currentTime, 1.5);
      level.connect(sampleOut);
      ambient = {kind: kind, place: wantedPlace, source: playSample(key, level, true), gain: level, timer: 0, level: 0.7};
      return;
    }
    // The sound of the place comes now: meanwhile, the one made here; when it is ready, if the student is still there,
    // one gives way to the other.
    loadSample(key, function() {
      if (wanted === kind && ambient && ambient.kind === kind && ambient.lfo) {
        startAmbient(kind);
      }
    });
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
    ambient = {kind: kind, place: wantedPlace, source: source, gain: gain, timer: timer, lfo: lfo, level: place.volume};
  }

  /* ---------- What the characters say ---------- */

  /** @var {number} Seconds over which the end of a voice fades out. */
  var VOICE_FADE = 0.07;
  /** @var {number} Milliseconds of breath between a voice that ends and the next one. */
  var VOICE_GAP = 260;
  /** @var {Object|null} The voice that speaks now (source, finish): one at a time. */
  var speaking = null;

  /**
   * The music and the sound of the place step back while someone speaks, and come back after.
   *
   * @param {boolean} down
   */
  function duck(down) {
    var c = ctx;
    if (!c) {
      return;
    }
    if (music && music.out) {
      music.out.gain.setTargetAtTime(down ? 0.35 : 1, c.currentTime, 0.25);
    }
    if (ambient && ambient.gain && ambient.level) {
      ambient.gain.gain.setTargetAtTime(ambient.level * (down ? 0.45 : 1), c.currentTime, 0.25);
    }
  }

  /** The voice that speaks stops. */
  function hush() {
    if (speaking) {
      var cut = speaking;
      speaking = null;
      try {
        cut.source.onended = null;
        cut.source.stop();
      } catch (e) {
        // Already over.
      }
      duck(false);
      cut.finish();
    }
  }

  /**
   * Says a line of a character, from the pack: now if it is here, or as soon as it comes if that is soon; a line that
   * comes late is not said. A line that starts stops the one before.
   *
   * @param {string} key voice.<…>
   * @param {Function} [end] When it has been said (or will not be).
   * @param {Function} [start] When it starts to sound, with its length in seconds.
   * @return {boolean} Whether the pack has it and the page may sound.
   */
  function voice(key, end, start) {
    var finish = function() {
      if (end) {
        var e = end;
        end = null;
        e();
      }
    };
    if (!T.artUrl || !T.artUrl([key]) || !audio()) {
      finish();
      return false;
    }
    var asked = Date.now();
    var play = function() {
      if (Date.now() - asked > 2500) {
        finish();
        return;
      }
      hush();
      // The voices come trimmed tight to the last word: their end fades out in a moment instead of stopping dry, and
      // the next one waits a breath.
      var c = audio();
      var level = c.createGain();
      level.connect(sampleOut);
      var source = playSample(key, level);
      if (!source) {
        finish();
        return;
      }
      var end = c.currentTime + source.buffer.duration;
      level.gain.setValueAtTime(1, Math.max(c.currentTime, end - VOICE_FADE));
      level.gain.linearRampToValueAtTime(0.0001, end);
      var now = {source: source, finish: finish};
      speaking = now;
      duck(true);
      if (start) {
        start(source.buffer.duration);
      }
      source.onended = function() {
        if (speaking === now) {
          speaking = null;
          duck(false);
        }
        setTimeout(finish, VOICE_GAP);
      };
    };
    if (samples[key] && typeof samples[key] !== 'string') {
      play();
    } else if (samples[key] === 'none') {
      finish();
    } else {
      waitFor(key, play, finish);
    }
    return true;
  }

  /**
   * Calls `ready` when a sound of the pack is loaded, loading it if it is not yet; `fail` if it does not come.
   *
   * @param {string} key
   * @param {Function} ready
   * @param {Function} fail
   */
  function waitFor(key, ready, fail) {
    var tries = 0;
    loadSample(key);
    var check = function() {
      var s = samples[key];
      if (s && typeof s !== 'string') {
        ready();
      } else if (s === 'none' || tries++ > 30) {
        fail();
      } else {
        setTimeout(check, 100);
      }
    };
    check();
  }

  /* ---------- The music of a moment that is played ---------- */

  /** @var {Object|null} What played before the moment: its piece, and whether it was on. */
  var before = null;

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
  /** The piece of music that plays: the soft one, or that of a battle (sound.music.battle1, battle2). */
  var musicKey = 'sound.music';
  /** On the devices of the students, music as the teacher said: 0 never, 1 only in battles, 2 always; and the battle. */
  var device = {mode: -1, battle: false};

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
    // On a tablet the piece is fetched only when it is going to play.
    if (samples[musicKey] === undefined) {
      loadSample(musicKey);
    }
    if (samples[musicKey] === 'loading' && tries < 16) {
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
    // The piece wanted, or the soft one if the pack does not bring that one.
    var recorded = playSample(musicKey, out, true) || (musicKey !== 'sound.music' && playSample('sound.music', out, true));
    if (recorded) {
      out.connect(sampleOut);
      music = {out: out, timer: 0, source: recorded, key: musicKey};
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

  /**
   * On the device of a student, the music that may play now, and its piece: that of the battle in one, the soft one
   * out of it if the teacher allows it always.
   */
  function deviceTune() {
    var may = device.mode === 2 || (device.mode === 1 && device.battle);
    musicPage = may;
    var key = device.battle ? 'sound.music.battle1' : 'sound.music';
    if (!may) {
      stopMusic();
    } else if (key !== musicKey || !music) {
      musicKey = key;
      stopMusic();
      if (musicWanted() && !muted()) {
        startMusic();
      }
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

  /** The sheets of frames of the pack already read (anim.fx.<name>), by key. */
  var sheets = {};

  /**
   * A sheet of frames of the pack played over a figure, once (anim.fx.<name> and its picture fx.<name>, as Gaudí draws
   * them: frame, cols, rows, frames, fps, anchor, fit, cues, still): its frame fits the box of the figure (fit), its
   * anchor sits on the centre of the figure, and each cue calls back at its frame (swap: where the light covers the
   * figure). Without movement, the still frame, with the cues called at once.
   *
   * @param {HTMLElement} host Where it goes, over the figure (a grid where each child is in the same cell).
   * @param {string} name evolution…
   * @param {HTMLElement} figure The figure it goes over: its width is the box.
   * @param {Function} cue Gets the name of each cue.
   * @return {boolean} Whether the pack has the sheet: if not, nothing is played and no cue is called.
   */
  function sprite(host, name, figure, cue) {
    var metaUrl = T.artUrl && T.artUrl(['anim.fx.' + name]);
    var picture = T.artUrl && T.artUrl(['fx.' + name]);
    if (!metaUrl || !picture) {
      return false;
    }
    var fail = function() {
      // Without the sheet after all: the figure of now, at once.
      cue('swap');
    };
    var ready = sheets[name] ? Promise.resolve(sheets[name]) : fetch(metaUrl, {credentials: 'same-origin'}).then(function(r) {
      return r.json();
    }).then(function(meta) {
      // The picture, loaded before the first frame.
      return new Promise(function(done) {
        var img = new Image();
        img.onload = function() {
          sheets[name] = meta;
          done(meta);
        };
        img.onerror = function() {
          done(null);
        };
        img.src = picture;
      });
    });
    ready.then(function(meta) {
      if (!meta || !meta.frame || !meta.cols || !meta.rows || !host.isConnected) {
        fail();
        return;
      }
      var box = figure.getBoundingClientRect().width || 150;
      var w = box * (meta.fit || 1);
      var h = w * meta.frame[1] / meta.frame[0];
      var anchor = meta.anchor || [0.5, 0.5];
      var el = document.createElement('div');
      el.className = 'fx-sheet';
      el.setAttribute('aria-hidden', 'true');
      el.style.width = w + 'px';
      el.style.height = h + 'px';
      el.style.backgroundImage = 'url("' + picture + '")';
      el.style.backgroundSize = meta.cols * 100 + '% ' + meta.rows * 100 + '%';
      el.style.transform = 'translate(' + (0.5 - anchor[0]) * w + 'px, ' + (0.5 - anchor[1]) * h + 'px)';
      var show = function(f) {
        el.style.backgroundPosition = (f % meta.cols) / (meta.cols - 1) * 100 + '% ' +
          Math.floor(f / meta.cols) / (meta.rows - 1) * 100 + '%';
      };
      host.appendChild(el);
      var cues = meta.cues || {};
      if (!T.moves()) {
        Object.keys(cues).forEach(cue);
        if (meta.still === null || meta.still === undefined) {
          el.remove();
        } else {
          show(meta.still);
        }
        return;
      }
      var t0 = performance.now();
      var fired = {};
      (function tick(now) {
        var f = Math.floor((now - t0) / 1000 * (meta.fps || 12));
        Object.keys(cues).forEach(function(c) {
          if (f >= cues[c] && !fired[c]) {
            fired[c] = true;
            cue(c);
          }
        });
        if (f >= (meta.frames || meta.cols * meta.rows) || !el.isConnected) {
          el.remove();
          return;
        }
        show(f);
        requestAnimationFrame(tick);
      })(t0);
    }).catch(fail);
    return true;
  }

  T.fx = {
    /**
     * A sound, by name: tap, choose, right, wrong, heart, lantern, levelup, hatch, hit, win, applause, drumroll, path.
     *
     * @param {string} name
     * @return {boolean} Whether something sounded (or would, without the silence).
     */
    sound: function(name) {
      if (playSample('sound.' + name)) {
        return true;
      }
      var key = 'sound.' + name;
      if (RARE.indexOf(name) >= 0 && !samples[key] && T.artUrl && T.artUrl([key]) && audio()) {
        // A sound of a big moment, not loaded ahead: it sounds as soon as it comes, or the one made here if it does not.
        var asked = Date.now();
        waitFor(key, function() {
          if (Date.now() - asked < 2500) {
            playSample(key);
          }
        }, function() {
          if (SOUNDS[name]) {
            SOUNDS[name]();
          }
        });
        return true;
      }
      if (SOUNDS[name]) {
        SOUNDS[name]();
        return true;
      }
      return false;
    },
    /**
     * A sound of the pack that is not loaded ahead (music.victory…): played as soon as it comes, if that is soon.
     *
     * @param {string} name
     * @return {boolean} Whether the pack has it and the page may sound.
     */
    later: function(name) {
      var key = 'sound.' + name;
      if (!T.artUrl || !T.artUrl([key]) || !audio()) {
        return false;
      }
      var asked = Date.now();
      waitFor(key, function() {
        if (Date.now() - asked < 2500) {
          playSample(key);
        }
      }, function() {
        // Without it.
      });
      return true;
    },
    voice: voice,
    hush: hush,
    /** Whether someone is speaking now. */
    speaking: function() {
      return !!speaking;
    },
    /**
     * Brings some sounds or voices of the pack ahead, those a moment is going to use.
     *
     * @param {string[]} keys
     */
    fetch: function(keys) {
      if (audio()) {
        keys.forEach(function(key) {
          loadSample(key);
        });
      }
    },
    /**
     * The music of a moment that is played (explore, find), while it lasts, over the one of the page; '' when it is
     * over, and the one before comes back. It plays where music may play now and the device has it on.
     *
     * @param {string} kind
     */
    moment: function(kind) {
      var may = !quiet && musicWanted() && !muted() && (device.mode < 0 || device.mode === 2);
      if (kind) {
        var key = 'sound.music.' + kind;
        if (!before) {
          before = {key: musicKey, on: !!music};
        }
        if (!may || !T.artUrl || !T.artUrl([key]) || (music && music.key === key)) {
          return;
        }
        musicKey = key;
        stopMusic();
        startMusic();
      } else if (before) {
        var back = before;
        before = null;
        if (musicKey !== back.key) {
          musicKey = back.key;
          stopMusic();
          if (back.on && may) {
            startMusic();
          }
        }
      }
    },
    /**
     * The soft sound of a kind of place (meadow, forest, lake, mountain, night), or none; the sound of the place itself
     * (sound.ambient.<place>) if the pack has it. It waits for the first touch.
     *
     * @param {string|null} kind
     * @param {string} [place] The code of the place.
     */
    ambient: function(kind, place) {
      var moved = (place || null) !== wantedPlace;
      wanted = kind || null;
      wantedPlace = place || null;
      if (!wanted) {
        stopAmbient();
      } else if (!ambient || ambient.kind !== wanted || (moved && T.artUrl && T.artUrl(['sound.ambient.' + wantedPlace]))) {
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
    sprite: sprite,
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
    /**
     * The piece of music of the class screen: the soft one, or that of a battle. If the music is on and the piece
     * changes, one fades out as the other fades in.
     *
     * @param {string} kind '' (the soft one), battle1 or battle2.
     */
    musicKind: function(kind) {
      var key = kind ? 'sound.music.' + kind : 'sound.music';
      if (key === musicKey) {
        return;
      }
      musicKey = key;
      if (music && musicWanted() && !muted()) {
        stopMusic();
        startMusic();
      }
    },
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
     * On the device of a student, the music the teacher allows: never, only in battles or always.
     *
     * @param {number} mode 0, 1 or 2.
     * @param {string} [world] The music of the world: valley, sea or space.
     */
    deviceMusic: function(mode, world) {
      device.mode = mode;
      mood = MOODS[world] || mood;
      deviceTune();
    },
    /**
     * On the device of a student, it is in a battle (its music, if the teacher allows it) or it is out.
     *
     * @param {boolean} on
     */
    battleMusic: function(on) {
      if (device.battle !== !!on) {
        device.battle = !!on;
        deviceTune();
      }
    },
    /**
     * Whether this page offers to turn the music on: the class screen always, a tablet when the teacher allows it now.
     *
     * @return {boolean}
     */
    musicAllowed: function() {
      return !quiet && (device.mode < 0 || device.mode === 2 || (device.mode === 1 && device.battle));
    },
    /**
     * This page plays the music the device remembers, if it was on, as soon as browsers let it sound.
     *
     * @param {string} [world] The music of the world: valley, sea or space.
     */
    musicHere: function(world) {
      musicPage = true;
      stage = true;
      if (ctx) {
        loadSamples();
      }
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
    /**
     * The volume of the device.
     *
     * @return {number} 0 to 1.
     */
    volume: volume,
    /**
     * Sets the volume of the device, remembered for all the games of Oksigenia on this site.
     *
     * @param {number} v 0 to 1.
     */
    setVolume: function(v) {
      v = Math.min(1, Math.max(0, Number(v) || 0));
      try {
        window.localStorage.setItem(VOLUME, String(v));
      } catch (e) {
        // Without storage, only for this page.
      }
      if (ctx) {
        master.gain.setTargetAtTime(0.45 * v, ctx.currentTime, 0.05);
        sampleOut.gain.setTargetAtTime(v, ctx.currentTime, 0.05);
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

  // Browsers let a page sound only after the first touch: then the sound of the place starts. On a click too, once:
  // some versions of Safari on the iPad do not take the first touch down as the gesture that lets it sound.
  document.addEventListener('click', function() {
    if (ctx && ctx.state === 'suspended') {
      ctx.resume();
    }
  }, {passive: true, once: true});
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
