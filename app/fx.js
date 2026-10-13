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
  var ambient = null;
  var wanted = null;
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // The teacher took the sound away from the devices of the class.
  var quiet = false;

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
     * A sound, by name: tap, choose, right, wrong, heart, lantern, levelup, hatch, hit, win, applause, drumroll.
     *
     * @param {string} name
     */
    sound: function(name) {
      if (SOUNDS[name]) {
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
      SOUNDS.drumroll();
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
    if (audio() && wanted && !ambient) {
      startAmbient(wanted);
    }
  }, {passive: true});
})();
