/**
 * Tesapé: the player of decision scenes. It shows each node with the art of the game, notes what the student does
 * and, at an ending, sends it to the server, which plays the scene again and decides the ending and the rewards.
 *
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var cfg = T.cfg;
  var t = T.t;
  var esc = T.esc;
  /** The medallion of each role, as on its verb. */
  var ROLEICON = {protector: 'shield', sage: 'log', healer: 'cheer', scout: 'trail'};

  /**
   * The keys of the art pack for the picture of a node, from the most precise to the most general: the image of the
   * node, the place its name starts with (lago-red: the lake), and the place of the scene's chapter; each one first in
   * the skin of the mission. A skin without its own pictures uses those of the first skin.
   *
   * @param {string} key Image of the node, or the code of the scene.
   * @param {Object} where skin and place of the scene.
   * @return {string[]}
   */
  function pictureKeys(key, where) {
    var keys = [];
    [String(key || '').toLowerCase(), String(key || '').toLowerCase().split('-')[0], where.place].forEach(function(k) {
      if (k && keys.indexOf('scene.' + k) < 0) {
        keys.push('scene.' + k + '.' + where.skin, 'scene.' + k);
      }
    });
    return keys;
  }

  /**
   * A picture for a node: the painting of the art pack, or one drawn from words in its key until the art arrives.
   *
   * @param {string} key
   * @param {Object} where skin and place of the scene.
   * @return {string}
   */
  function picture(key, where) {
    var art = T.artUrl(pictureKeys(key, where || {}));
    if (art) {
      return '<div class="vignette scene-pic art" aria-hidden="true"><img src="' + esc(art) + '" alt=""></div>';
    }
    var k = String(key || '');
    var mid = '';
    if (/lago|gota|agua|barcaza|rio/.test(k)) {
      mid = '<ellipse cx="170" cy="104" rx="130" ry="15" fill="#2f6f8f" opacity=".85"/>' +
        '<path d="M120 98 q10 -6 20 0 q10 6 20 0" stroke="#8fd3ff" stroke-width="2" fill="none" opacity=".6"/>';
    } else if (/biblioteca|torre|sotano|pagina|embrollo/.test(k)) {
      mid = '<rect x="140" y="26" width="60" height="86" rx="4" fill="#2b2219"/><rect x="152" y="44" width="12" height="16" ' +
        'fill="#ffc857"/><rect x="176" y="44" width="12" height="16" fill="#ffc857" opacity=".6"/>' +
        (/embrollo/.test(k) ? '<path d="M150 80 q20 -30 40 0 q-20 30 -40 0" stroke="#b9a6ff" stroke-width="3" fill="none"/>' : '');
    } else if (/bosque|buho|rastro|noche/.test(k)) {
      mid = '<path d="M60 112 L88 36 L116 112Z M210 112 L242 26 L274 112Z" fill="#1f4a33"/><circle cx="242" cy="58" r="7" ' +
        'fill="#ffe38a" opacity=".8"/>';
    } else if (/plantas|jardin|raiz/.test(k)) {
      mid = '<path d="M150 112 q-6 -40 20 -60 q26 20 20 60Z" fill="#7cc67f"/><path d="M170 112 V60" stroke="#4f8f45" ' +
        'stroke-width="3"/>';
    } else {
      mid = '<circle cx="170" cy="96" r="20" fill="#3b2a18"/><circle cx="170" cy="96" r="8" fill="#ffc857"/>';
    }
    return '<div class="vignette scene-pic" aria-hidden="true"><svg viewBox="0 0 340 120" ' +
      'preserveAspectRatio="xMidYMid slice"><rect width="340" height="120" fill="#16314a"/><circle cx="300" cy="26" r="12" ' +
      'fill="#fff3c4" opacity=".8"/><path d="M0 120 Q80 64 160 100 T340 92 V120Z" fill="#183528"/>' + mid + '</svg></div>';
  }

  /**
   * Who speaks: their name, with their round portrait if the art pack has it. A portrait for one skin only
   * (speaker.druida-mayor.valley) is not shown in another skin, where that speaker can be someone else.
   *
   * @param {string} code
   * @param {Object} data speakers (code => name) and skin.
   * @return {string}
   */
  function speaker(code, data) {
    var art = T.artUrl(['speaker.' + code + '.' + data.skin, 'speaker.' + code, 'boss.' + code]);
    return '<div class="speaker-row">' + (art ? '<img class="speaker-face" src="' + esc(art) + '" alt="">' : '') +
      '<div class="speaker">' + esc(data.speakers[code] || code) + '</div></div>';
  }

  /**
   * Plays a scene.
   *
   * With classmode, the whole class plays it on the class screen: the texts speak of the class, the class has the items
   * and roles of any of its players, each option has its letter (for the vote cards), the phones can vote, and the
   * ending goes to the server as a play of the class.
   *
   * With preview, a teacher tries a scene of the mission they are making: the scene comes in it, as get_scene gives it,
   * and nothing is kept.
   *
   * @param {Object} opts sceneid or preview, world (the world of the student), onclose(world) when they leave; classmode:
   *     name, creature, items, roles, vote ({open(prompt, labels), state(), close()}, or null without phones), finish(events,
   *     from).
   */
  /* ---------- Reading aloud, for who needs it ---------- */

  /**
   * Reads aloud what is on the screen (a scene: the narration, the question, the options; or a card of the game), with
   * a voice of the device itself, so that the text goes nowhere else; touched again, it stops. Without a voice of its
   * own in the language of the page, the device says so.
   *
   * @param {HTMLElement} body
   * @param {boolean} [waited] The voices were waited for already.
   */
  function readAloud(body, waited) {
    var speech = window.speechSynthesis;
    if (!speech || !body) {
      return;
    }
    if (speech.speaking) {
      speech.cancel();
      return;
    }
    if (!waited && !speech.getVoices().length) {
      // Some devices find their voices a moment after they are asked for: up to a second.
      var go = function() {
        speech.removeEventListener('voiceschanged', go);
        clearTimeout(late);
        readAloud(body, true);
      };
      var late = setTimeout(go, 1000);
      speech.addEventListener('voiceschanged', go);
      return;
    }
    var lang = (cfg.lang || 'es').slice(0, 2);
    var voice = speech.getVoices().filter(function(v) {
      return v.localService && v.lang.slice(0, 2) === lang;
    })[0];
    if (!voice) {
      T.toast(esc(t('app_read_novoice')), true);
      return;
    }
    // All its text, in order, with a pause after each block; not the buttons of the game (play, close, listen).
    var copy = body.cloneNode(true);
    copy.querySelectorAll('.actions, script, style, svg, img, button.btn').forEach(function(el) {
      el.remove();
    });
    // What is folded and closed is not on the screen: only its title.
    copy.querySelectorAll('details:not([open]) > :not(summary)').forEach(function(el) {
      el.remove();
    });
    copy.querySelectorAll('p, h2, h3, li, div, blockquote, small, b, label, button:not(.gloss), .what, .speaker, .kicker, .tag')
      .forEach(function(el) {
        el.appendChild(document.createTextNode('. '));
      });
    var text = copy.textContent.replace(/\s+/g, ' ').replace(/([.:;!?…»])\s*(\.\s*)+/g, '$1 ')
      .replace(/(\.\s*){2,}/g, '. ').trim();
    var say = new window.SpeechSynthesisUtterance(text || copy.textContent);
    say.voice = voice;
    say.lang = voice.lang;
    say.rate = 0.95;
    speech.speak(say);
  }
  T.readAloud = readAloud;
  // Some browsers find their voices a moment later.
  if (window.speechSynthesis) {
    window.speechSynthesis.getVoices();
  }

  /* ---------- Without connection: the scenes kept on the device, and the plays waiting to be sent ---------- */

  var PLAYS = 'tesape-plays-' + cfg.courseid + '-' + cfg.userid;
  var SCENES = 'tesape-scenes-' + cfg.courseid + '-' + cfg.userid;
  /** The scenes kept on a device, of all its students: a tablet may be shared by a whole school. */
  var KEPT = 12;
  /** Times a play is sent while the server fails (not the connection) before it is given up. */
  var TRIES = 10;

  function load(key, empty) {
    try {
      return JSON.parse(window.localStorage.getItem(key) || 'null') || empty;
    } catch (e) {
      return empty;
    }
  }

  /**
   * The keys of the scenes kept on this device, of every student and course.
   *
   * @return {string[]}
   */
  function sceneKeys() {
    var keys = [];
    try {
      for (var i = 0; i < window.localStorage.length; i++) {
        var key = window.localStorage.key(i);
        if (key && key.indexOf('tesape-scenes-') === 0) {
          keys.push(key);
        }
      }
    } catch (e) {
      // Not allowed: none.
    }
    return keys;
  }

  function save(key, value) {
    var write = function() {
      window.localStorage.setItem(key, JSON.stringify(value));
    };
    try {
      write();
      return true;
    } catch (e) {
      if (key.indexOf('tesape-scenes-') === 0) {
        return false;
      }
    }
    // Full: a play matters more than the scenes kept to play without connection, which the server gives again.
    try {
      sceneKeys().forEach(function(k) {
        window.localStorage.removeItem(k);
      });
      write();
      return true;
    } catch (e) {
      return false;
    }
  }

  /**
   * A scene as the server gave it, kept on the device so that it can be played without connection: the last ones
   * opened on the device, by whoever.
   *
   * @param {Object} d
   */
  function keepScene(d) {
    var all = [];
    var lists = {};
    sceneKeys().concat([SCENES]).forEach(function(key) {
      if (lists[key]) {
        return;
      }
      lists[key] = load(key, {});
      Object.keys(lists[key]).forEach(function(id) {
        all.push({key: key, id: id, at: lists[key][id].at || 0});
      });
    });
    lists[SCENES][d.id] = {at: Date.now(), d: d};
    all = all.filter(function(e) {
      return !(e.key === SCENES && String(e.id) === String(d.id));
    }).sort(function(a, b) {
      return b.at - a.at;
    });
    var changed = {};
    changed[SCENES] = true;
    all.slice(KEPT - 1).forEach(function(e) {
      delete lists[e.key][e.id];
      changed[e.key] = true;
    });
    Object.keys(changed).forEach(function(key) {
      try {
        window.localStorage.removeItem(key);
      } catch (e) {
        // Nothing to do.
      }
      if (Object.keys(lists[key]).length) {
        save(key, lists[key]);
      }
    });
  }

  /**
   * The plays waiting for the connection, on this device and for this student.
   *
   * @return {Object[]}
   */
  T.waitingPlays = function() {
    return load(PLAYS, []);
  };

  /**
   * Keeps a play on the device until the connection comes back: a scene, or a minigame (kind minigame).
   *
   * @param {Object} play
   * @return {boolean} Whether it could be kept.
   */
  T.keepPlay = function(play) {
    return save(PLAYS, T.waitingPlays().concat([play]));
  };

  /**
   * Sends the plays that wait, one after the other, while there is connection. A play the server does not take (the
   * scene changed, the game was turned off) is left out, and the student is told: sending it again would not help,
   * playing it again does. While the server fails, it is tried a few times more.
   *
   * @param {Function} [sent] Called with the title of each play the server took.
   * @return {Promise} With the number of plays sent.
   */
  T.sendPlays = function(sent) {
    var count = 0;
    var next = function() {
      var list = T.waitingPlays();
      if (!list.length) {
        return Promise.resolve(count);
      }
      var p = list[0];
      var call = p.kind === 'minigame' ? T.api('play_minigame', {courseid: cfg.courseid, game: p.game, playid: p.playid || '',
        events: p.events}) :
        T.api('play_scene', {courseid: cfg.courseid, sceneid: p.sceneid, events: p.events, start: p.start || '',
        team: p.team || '', playid: p.playid || ''});
      return call.then(function() {
        count++;
        save(PLAYS, T.waitingPlays().slice(1));
        if (sent) {
          sent(p.title);
        }
        return next();
      }).catch(function(e) {
        var failing = e.network && e.status >= 500;
        if ((e.network && !failing) || e.retry) {
          return count;
        }
        p.tries = (p.tries || 0) + 1;
        if (failing && p.tries < TRIES) {
          save(PLAYS, [p].concat(T.waitingPlays().slice(1)));
          return count;
        }
        save(PLAYS, T.waitingPlays().slice(1));
        T.toast(esc(t('app_play_lost', p.title || '')), true);
        return next();
      });
    };
    return next();
  };

  /**
   * A mark for a play, made on the device: sent twice, it counts once.
   *
   * @return {string}
   */
  T.playId = function() {
    return (Date.now().toString(36) + Math.random().toString(36).slice(2, 10)).slice(0, 20);
  };

  T.playScene = function(opts) {
    var cm = opts.classmode || null;
    // In the preview of the Workshop: what each answer counts for, beside it.
    var evidence = !!(opts.preview && opts.evidence);
    var LETTERS = 'ABCDEF';
    var layer = document.createElement('div');
    layer.className = 'scene-layer' + (cm ? ' class-mode' : '');
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    document.body.appendChild(layer);
    document.body.classList.add('in-scene');
    var world = opts.world;
    var data = null;
    var sc = null;
    var run = null;
    // The seed of the creature, warm and alive, with the cracks that open as it is touched: over the painting of the pack
    // too, from the split at its tip.
    var SEED = T.artUrl && T.artUrl(['creature.seed']) ? '<span class="seed-art"><img src="' + esc(T.artUrl(['creature.seed'])) +
      '" alt=""><svg viewBox="0 0 512 512" aria-hidden="true"><path class="c1" d="M300 46 L318 92 L298 138"/><path ' +
      'class="c2" d="M298 138 L256 172 L266 216"/><path class="c3" d="M298 138 L342 174 L332 224"/></svg></span>' :
      '<svg viewBox="0 0 100 120" aria-hidden="true"><defs><radialGradient id="tesape-seed" cx="40%" cy="35%" r="70%">' +
      '<stop offset="0" stop-color="#fff2c4"/><stop offset=".55" stop-color="#e9b85a"/><stop offset="1" stop-color="#9a6a2a"/>' +
      '</radialGradient></defs><ellipse cx="50" cy="66" rx="34" ry="44" fill="url(#tesape-seed)"/><path class="c1" ' +
      'd="M50 26 L44 44 L54 52" stroke="#5b3a12" stroke-width="3" fill="none"/><path class="c2" d="M54 52 L46 66 L58 76" ' +
      'stroke="#5b3a12" stroke-width="3" fill="none"/><path class="c3" d="M58 76 L50 92 L60 104" stroke="#5b3a12" ' +
      'stroke-width="3" fill="none"/><path d="M50 24 Q60 10 70 14 Q60 22 50 24Z" fill="#7cc67f"/></svg>';
    // While a play is being saved there is no way out: closed then, the map would show it as not played.
    var saving = false;
    // A team session: the scene runs here, and the hand chooses on its own tablet; who is in it now.
    var huddle = null;
    var huddleIds = [];
    // The place alive behind the scene (sceneworld.js), when it can be: what it is told waits until it is ready.
    var live = null;
    var liveQueue = null;
    // What is read grows (what the world says after a decision): the figures move up to stand above it.
    var roomWatch = null;
    // Something looked for on the painting of the place alive: where the painting is, and how to draw the moment again
    // on the page if the place alive goes.
    var search = null;

    /**
     * Tells the place alive behind the scene, if there is one, what happens.
     *
     * @param {string} what image, speak, talk, answer, ask or ending.
     */
    function alive(what) {
      var args = [].slice.call(arguments, 1);
      if (live) {
        live[what].apply(null, args);
      } else if (liveQueue) {
        liveQueue.push([what, args]);
      }
    }

    /**
     * Opens the place alive behind the scene, where the device moves and draws: for the scenes a student plays, and for
     * those the class plays on its screen (without a hero of its own: the place and who speaks). Not in a preview.
     */
    function liven() {
      if (opts.preview || !T.sceneWorld || !T.sceneWorld.can()) {
        return;
      }
      liveQueue = [];
      var holder = document.createElement('div');
      holder.className = 'scene-world';
      holder.setAttribute('aria-hidden', 'true');
      layer.insertBefore(holder, layer.firstChild);
      layer.classList.add('live');
      // In class, the hero is the whole class: no figure of its own, only the place and who speaks.
      T.sceneWorld.open(holder, {world: cm ? {} : world, image: T.artUrl(pictureKeys(sceneImage(), data)), still: !T.moves()})
      .then(function(w) {
        if (!liveQueue) {
          w.close();
          return;
        }
        live = w;
        liveQueue.forEach(function(call) {
          w[call[0]].apply(null, call[1]);
        });
        liveQueue = null;
        // For the tests of the browser.
        layer.liveWorld = w;
      }).catch(function() {
        // Without the engine, the scene as it was.
        unliven();
      });
    }

    /** The place alive goes: the scene goes on as it was, with its paintings in the page. */
    function unliven() {
      var again = search && search.redraw;
      search = null;
      liveQueue = null;
      if (roomWatch) {
        roomWatch.disconnect();
        roomWatch = null;
      }
      if (live) {
        live.close();
        live = null;
      }
      var holder = layer.querySelector('.scene-world');
      if (holder) {
        holder.remove();
      }
      layer.classList.remove('live', 'has-figure');
      if (again) {
        again();
      }
    }

    function close() {
      unliven();
      if (huddle) {
        huddle.stop();
        huddle = null;
      }
      if (window.speechSynthesis) {
        window.speechSynthesis.cancel();
      }
      layer.remove();
      document.body.classList.remove('in-scene');
      opts.onclose(world);
    }

    /**
     * The ✕ of a scene: with decisions made and no ending yet, it asks first, because what was decided is not kept. The
     * ✕ again (or Escape) is «keep playing».
     */
    function leave() {
      var asked = layer.querySelector('.leave-ask');
      if (asked) {
        asked.remove();
        layer.querySelector('.scene-close').focus();
        return;
      }
      var at = sc && run && sc.nodes[run.node];
      var decided = run && run.events.some(function(e) {
        return e.pick || e.item || e.step;
      });
      if (!decided || !at || at.type === 'ending') {
        close();
        return;
      }
      var ask = document.createElement('div');
      ask.className = 'leave-ask';
      ask.setAttribute('role', 'alertdialog');
      ask.setAttribute('aria-labelledby', 'tesape-leave-ask');
      ask.innerHTML = '<p id="tesape-leave-ask">' + esc(t('app_leave_ask')) + '</p><div class="actions"><button type="button" ' +
        'class="btn btn-main" data-stay>' + esc(t('app_leave_stay')) + '</button><button type="button" class="btn btn-soft" ' +
        'data-leave>' + esc(t('app_leave_go')) + '</button></div>';
      layer.querySelector('.scene-top').insertAdjacentElement('afterend', ask);
      ask.querySelector('[data-stay]').onclick = leave;
      ask.querySelector('[data-leave]').onclick = close;
      ask.querySelector('[data-stay]').focus();
    }

    /**
     * What is heard and seen at an ending: confetti and applause for a good one, hearts halfway, and the lanterns lit
     * after it.
     *
     * @param {string} outcome
     * @param {Object} r What it gave, if anything.
     * @param {boolean} [told] The place already answered, while how it ends was told.
     */
    function celebrate(outcome, r, told) {
      if (!T.fx) {
        return;
      }
      if (!told) {
        alive('ending', outcome);
      }
      if (outcome === 'success') {
        T.fx.sound('win');
        T.fx.confetti();
        setTimeout(function() {
          T.fx.sound('applause');
        }, 500);
      } else if (outcome === 'partial') {
        T.fx.sound('heart');
        T.fx.hearts();
      } else {
        // Not this time: no buzz of a referee, only the world that waits.
        T.fx.sound('choose');
      }
      if (r && r.lanterns && r.lanterns.length) {
        setTimeout(function() {
          T.fx.sound('lantern');
          T.fx.sparkles();
        }, 1100);
      }
      // The creature is born when the seed is touched (hatching), and a new level has its moment on the map.
    }

    /**
     * The creature is born from its seed: touched three times, it cracks and opens. Without movement or reading aloud,
     * it is there at once.
     *
     * @param {Element} body
     * @param {Object} n The ending, with what the creature does when it comes out (born), if it says.
     */
    function hatching(body, n) {
      var box = body.querySelector('[data-hatch]');
      if (!box) {
        return;
      }
      var born = function() {
        box.innerHTML = T.creature(world.character.creature, 1, true) + (n.born ? '<p class="scene-text">' + esc(tx(n.born)) +
          '</p>' : '') + '<p>' + esc(t('app_hatched', creatureName())) + '</p>';
        if (T.fx) {
          T.fx.sound('hatch');
          T.fx.hearts();
        }
      };
      if (!T.moves() || T.adapted.readaloud) {
        born();
        return;
      }
      var touches = 0;
      var seed = box.querySelector('[data-seed]');
      seed.onclick = function() {
        touches++;
        if (T.fx) {
          T.fx.sound('tap');
        }
        seed.className = 'seed crack' + touches;
        if (touches >= 3) {
          setTimeout(born, 350);
        }
      };
    }

    /** The drum roll before a result, and a promise that waits for it. */
    function drumroll() {
      if (!T.fx) {
        return Promise.resolve();
      }
      T.fx.drumroll(layer.querySelector('.scene'));
      return new Promise(function(resolve) {
        setTimeout(resolve, 1100);
      });
    }

    function show(html) {
      // What was played on the painting goes with its moment.
      layer.querySelectorAll(':scope > .on-painting').forEach(function(over) {
        over.remove();
      });
      // A new moment: whoever was speaking stops.
      if (T.fx && T.fx.hush) {
        T.fx.hush();
      }
      // Reading aloud is always at hand in a scene (who has it in their adaptations hears it by itself).
      var reads = !!window.speechSynthesis;
      if (reads) {
        window.speechSynthesis.cancel();
      }
      var page = '<div class="scene"><div class="scene-top"><span class="scene-title">' + esc(sc ? sc.title : '') +
        '</span>' + (layer.classList.contains('live') ? '<button class="btn btn-soft scene-still" type="button" data-still ' +
        'aria-label="' + esc(t('app_stage_list')) + '" title="' + esc(t('app_stage_list')) + '">' + T.icon('log') + '</button>' :
        '') + (reads ? '<button class="btn btn-soft scene-read" type="button" ' +
        'data-read aria-label="' + esc(t('app_read_aloud')) + '" title="' + esc(t('app_read_aloud')) + '">' +
        T.icon('speak') + '</button>' : '') + (saving ? '' : '<button class="btn btn-soft scene-close" type="button" ' +
        'aria-label="' + esc(t('app_close')) + '">' + T.icon('close') + '</button>') + '</div>' + (opts.team &&
        opts.team.length ? '<p class="team-banner">' + esc(t('app_team_banner')) + '</p>' : '') +
        '<div class="scene-body" tabindex="-1">' + html + '</div></div>';
      // With the place alive behind, only what is read and touched changes; the world stays.
      var old = layer.querySelector('.scene');
      if (layer.classList.contains('live') && old) {
        old.outerHTML = page;
      } else if (layer.classList.contains('live')) {
        layer.insertAdjacentHTML('beforeend', page);
      } else {
        layer.innerHTML = page;
      }
      var still = layer.querySelector('[data-still]');
      if (still) {
        // As a list: the moments without the place alive, here and in the next ones, on this device.
        still.onclick = function() {
          T.stage.setList(true);
          unliven();
          still.remove();
          layer.querySelector('.scene-body').focus();
        };
      }
      // The painting of the moment, in the world behind; who spoke goes quiet; the figures stand above what is read.
      var painted = layer.querySelector('.scene-body .scene-pic.art img');
      if (painted) {
        alive('image', painted.getAttribute('src'));
      }
      alive('talk', 0);
      if (layer.classList.contains('live')) {
        var room = function() {
          var now = layer.querySelector('.scene-body');
          if (now) {
            alive('room', function() {
              var r = now.getBoundingClientRect();
              return {top: r.top, left: r.left, right: r.right};
            });
          }
        };
        room();
        if (roomWatch) {
          roomWatch.disconnect();
        }
        if (window.ResizeObserver) {
          roomWatch = new window.ResizeObserver(room);
          roomWatch.observe(layer.querySelector('.scene-body'));
        }
      }
      var cross = layer.querySelector('.scene-close');
      if (cross) {
        cross.onclick = leave;
      }
      var read = layer.querySelector('[data-read]');
      if (read) {
        read.onclick = function() {
          if (T.fx && T.fx.hush) {
            T.fx.hush();
          }
          readAloud(layer.querySelector('.scene-body'));
        };
      }
      layer.scrollTop = 0;
      // The keyboard and the screen reader go on from the start of the new moment, never from the page under the scene.
      var focus = layer.querySelector('[data-focus]') || layer.querySelector('.scene-body');
      focus.focus({preventScroll: true});
      return layer.querySelector('.scene-body');
    }

    function myName() {
      if (cm) {
        return cm.name;
      }
      var c = world.character;
      return world.nicknames && c && c.nickname ? c.nickname : cfg.firstname;
    }

    function creatureName() {
      if (cm) {
        return cm.creature;
      }
      try {
        return JSON.parse(world.character.creature || '{}').name || t('app_creature');
      } catch (e) {
        return t('app_creature');
      }
    }

    /**
     * A text of the scene: in the skin of the course, with the local examples, the name and the creature.
     *
     * @param {*} text
     * @return {string}
     */
    function tx(text) {
      if (text && typeof text === 'object') {
        // The voice of the place first (valley.py speaks with «ustedes»), then the story.
        text = text[data.skin + '.' + data.variant] || text[data.skin] || text[Object.keys(text)[0]];
      }
      text = String(text || '');
      if (cm) {
        // In class, a placeholder that opens a sentence gets a capital letter.
        text = text.replace(/(^|[.!?¡¿]\s*)\{\{(name|creature)\}\}/g, function(m, before, key) {
          var word = key === 'name' ? myName() : creatureName();
          return before + word.charAt(0).toUpperCase() + word.slice(1);
        });
      }
      return text.replace(/\{\{local:([a-z0-9_-]+)\}\}/g, function(m, key) {
        var local = sc.locals && sc.locals[key];
        return local ? (local[data.variant] || local[Object.keys(local)[0]]) : m;
      }).replace(/\{\{name\}\}/g, myName()).replace(/\{\{creature\}\}/g, creatureName());
    }

    function has(req) {
      if (!req) {
        return true;
      }
      if (req.flag) {
        return run.flags.indexOf(req.flag) >= 0;
      }
      if (req.role) {
        // The whole class, at the class screen, has the role of any of its players.
        return cm ? cm.roles.indexOf(req.role) >= 0 : hasRole(req.role);
      }
      if (req.nature) {
        // A word of the hero: the class, at the class screen, has none of its own.
        return !cm && natures().indexOf(req.nature) >= 0;
      }
      if (cm && req.item) {
        return cm.items.indexOf(req.item) >= 0;
      }
      if (cm && req.teamrole) {
        return cm.roles.indexOf(req.teamrole) >= 0;
      }
      if (req.item) {
        return (world.items || []).some(function(i) {
          return i.code === req.item;
        });
      }
      if (req.teamrole) {
        var roles = [world.character.role];
        ((world.team && world.team.members) || []).forEach(function(m) {
          if (m.character) {
            roles.push(m.character.role);
          }
        });
        return roles.indexOf(req.teamrole) >= 0;
      }
      if (req.any) {
        return req.any.some(has);
      }
      return false;
    }

    /**
     * The words that describe whoever plays, in the order they chose them.
     *
     * @return {string[]}
     */
    function natures() {
      return world && world.character ? String(world.character.nature || '').split(',').filter(Boolean) : [];
    }

    /**
     * What a moment says to whoever is described by one of their words (nature: {word: text}): the first of their words
     * that it has. Nothing at the class screen.
     *
     * @param {Object} n The node.
     * @return {string}
     */
    function natureSay(n) {
      if (cm || !n.nature) {
        return '';
      }
      var words = natures().filter(function(w) {
        return n.nature[w];
      });
      return words.length ? tx(n.nature[words[0]]) : '';
    }

    /**
     * The words of the glossary in a text: the first time each one appears in a moment, a button that opens its card.
     * The rest of the text, escaped. Not at the class screen.
     *
     * @param {string} text
     * @param {Object} used The words already marked in this moment (by id), changed here.
     * @return {string}
     */
    function glossify(text, used) {
      var list = !cm && world && world.mission ? world.mission.glossary || [] : [];
      if (!list.length || !text) {
        return esc(text);
      }
      var low = String(text).toLowerCase();
      var hits = [];
      list.forEach(function(g) {
        if (used[g.id]) {
          return;
        }
        var best = null;
        g.forms.forEach(function(f) {
          var re = new RegExp('(^|[^a-záéíóúñü])(' + String(f).toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, '\\$&') +
            ')(?![a-záéíóúñü])');
          var found = re.exec(low);
          if (found && (!best || found.index + found[1].length < best.at)) {
            best = {at: found.index + found[1].length, len: found[2].length, id: g.id};
          }
        });
        if (best) {
          hits.push(best);
        }
      });
      hits.sort(function(a, b) {
        return a.at - b.at || b.len - a.len;
      });
      var html = '';
      var pos = 0;
      hits.forEach(function(h) {
        if (h.at < pos) {
          return;
        }
        used[h.id] = true;
        html += esc(text.slice(pos, h.at)) + '<button type="button" class="gloss" data-gloss="' + esc(h.id) + '">' +
          esc(text.substr(h.at, h.len)) + '</button>';
        pos = h.at + h.len;
      });
      return html + esc(text.slice(pos));
    }

    /**
     * The card of a word of the glossary: what it means, its drawing if the pack has it, and to hear it.
     *
     * @param {string} id
     * @param {string} said How it was written where it was touched.
     */
    function glossCard(id, said) {
      var g = ((world && world.mission && world.mission.glossary) || []).filter(function(x) {
        return x.id === id;
      })[0];
      if (!g) {
        return;
      }
      var old = layer.querySelector('.gloss-card');
      if (old) {
        old.remove();
      }
      var art = g.picture ? T.artUrl([g.picture]) : null;
      var card = document.createElement('div');
      card.className = 'gloss-card';
      card.setAttribute('role', 'dialog');
      card.setAttribute('aria-labelledby', 'tesape-gloss-title');
      card.innerHTML = (art ? '<img src="' + esc(art) + '" alt="">' : '') + '<div><h3 id="tesape-gloss-title">' +
        esc(said.charAt(0).toLocaleUpperCase() + said.slice(1)) + '</h3><p>' + esc(g.text) + '</p><div class="actions">' +
        (window.speechSynthesis ? '<button type="button" class="btn btn-soft" data-glosslisten>' + T.icon('sound') + ' ' +
        esc(t('app_gloss_listen')) + '</button>' : '') + '<button type="button" class="btn btn-main" data-glossclose>' +
        esc(t('app_close')) + '</button></div></div>';
      layer.appendChild(card);
      var close = card.querySelector('[data-glossclose]');
      close.onclick = function() {
        if (window.speechSynthesis) {
          window.speechSynthesis.cancel();
        }
        card.remove();
      };
      var listen = card.querySelector('[data-glosslisten]');
      if (listen) {
        listen.onclick = function() {
          window.speechSynthesis.cancel();
          readAloud(card.querySelector('div'));
        };
      }
      close.focus();
    }

    /**
     * A text of a node (its narration or its question), in easy reading for whoever has it marked, if the scene has it.
     *
     * @param {Object} n The node.
     * @param {string} key text or prompt.
     * @return {*}
     */
    function easy(n, key) {
      var simple = key === 'text' ? n.easy : n.easyprompt;
      return !cm && T.adapted.easyread && simple ? simple : n[key];
    }

    // A word of the glossary, touched anywhere in the scene: its card.
    layer.addEventListener('click', function(ev) {
      var b = ev.target.closest && ev.target.closest('[data-gloss]');
      if (b && layer.contains(b)) {
        ev.preventDefault();
        ev.stopPropagation();
        glossCard(b.dataset.gloss, b.textContent);
      }
    });

    function missing(req) {
      if (req.item) {
        return t('app_needs', data.items[req.item] || req.item);
      }
      if (req.role) {
        return t('app_onlyrole', T.roleName(world ? world.family : '', req.role, 'm'));
      }
      if (req.teamrole) {
        return t('app_needsrole', T.roleName(world.family, req.teamrole, 'm'));
      }
      if (req.any) {
        return req.any.map(missing).join(' · ');
      }
      return '';
    }

    /**
     * Writes down what an answer told, if it tells something (an effect {clue: code} of the clues of the scene).
     *
     * @param {Object} e An effect.
     */
    function clue(e) {
      if (e.clue && sc.clues && sc.clues[e.clue] && run.clues.indexOf(e.clue) < 0) {
        run.clues.push(e.clue);
      }
    }

    /**
     * What the answers told so far, in the field notebook, over the question.
     *
     * @return {string}
     */
    function clueBox() {
      return run.clues.length ? '<div class="clues"><small>' + T.icon('log') + ' ' + esc(t('app_clues')) + '</small><ul>' +
        run.clues.map(function(c) {
          return '<li>' + esc(tx(sc.clues[c])) + '</li>';
        }).join('') + '</ul></div>' : '';
    }

    function start(from) {
      run = {node: from || sc.start, from: from || '', events: [], extra: 0, extraused: {}, hints: 0, shield: 0, guarded: {},
        flags: (data.flags || []).slice(), opened: {}, spent: 0, trailed: {}, turn: 0, handfor: {}, looks: {}, tried: {},
        keep: '', clues: [], sniffed: {}};
      // Back from an ending that sent here: what it left written in pencil shows over the question.
      if (from) {
        Object.keys(sc.nodes).some(function(id) {
          var e = sc.nodes[id];
          if (e.type === 'ending' && e.retry === from && e.keep) {
            run.keep = e.keep;
            return true;
          }
          return false;
        });
      }
      next();
    }

    /**
     * The picture of the scene, for its nodes without one of their own: that of its first node, or its place.
     *
     * @return {string}
     */
    function sceneImage() {
      return (sc.nodes[sc.start] || {}).image || sc.id;
    }

    function go(node) {
      // What was looked for on the painting is over: the painting fills the place again.
      if (search) {
        clearTimeout(search.wait);
        search = null;
        alive('search', false);
      }
      run.node = node;
      next();
    }

    function next() {
      var n = sc.nodes[run.node];
      if (!n.lineup && n.type !== 'branch') {
        alive('lineup', null);
      }
      // In a play as a team, each new decision passes to the next one of the team.
      if (/^(choice|classify|order|path|tend|find|use|justify)$/.test(n.type)) {
        if (run.decided && run.decided !== run.node) {
          run.turn++;
        }
        run.decided = run.node;
        handTo(n);
        alive('ask');
      }
      if (n.type === 'story') {
        story(n);
      } else if (n.type === 'branch' && n.pick && n.pick.length) {
        // One of its ways at random (the trail of the owl: whose it is changes from one play to the next).
        var way = n.pick[Math.floor(Math.random() * n.pick.length)];
        run.events.push({n: run.node, to: way});
        go(way);
      } else if (n.type === 'branch') {
        var target = n.else;
        (n.rules || []).some(function(rule) {
          if (rule.if && rule.if.flag !== undefined) {
            if (run.flags.indexOf(rule.if.flag) >= 0) {
              target = rule.next;
              return true;
            }
            return false;
          }
          if (rule.if && rule.if.correct_at_least !== undefined && correctIn(rule.if.in) >= rule.if.correct_at_least) {
            target = rule.next;
            return true;
          }
          return false;
        });
        go(target);
      } else if (n.type === 'choice') {
        choice(n);
      } else if (n.type === 'use' || n.type === 'justify') {
        choice(asChoice(n));
      } else if (n.type === 'find') {
        find(n);
      } else if (n.type === 'classify') {
        classify(n);
      } else if (n.type === 'order') {
        order(n);
      } else if (n.type === 'path') {
        path(n);
      } else if (n.type === 'tend') {
        tend(n);
      } else if (n.type === 'ending') {
        ending(n);
      }
    }

    /**
     * A told moment as in a role-playing game: the place behind, who speaks in big, the hero of the student and their
     * creature in it, and the words in a parchment that goes on with a touch, a piece at a time (all at once for who
     * has «no movement» or reading aloud).
     *
     * @param {Object} n The node.
     */
    function story(n) {
      tell(n, function() {
        go(n.next);
      });
    }

    /**
     * A text told in the box of words, a piece at a time, with the recorded voice of each piece: a told moment, or how
     * a scene ends. Who speaks is in the place; `nolabel` leaves their name out of the box (the narrator tells it, and
     * they only say what goes between «»).
     *
     * @param {Object} n text, image, speaker, nolabel.
     * @param {Function} done When the last piece is passed.
     */
    function tell(n, done) {
      var pieces = cut(tx(easy(n, 'text')));
      // Each word of the glossary, marked the first time it appears in the moment (each piece, worked out once).
      var glossed = {};
      var marked = [];
      // Then, what is said to whoever is like this.
      var said = natureSay(n);
      if (said) {
        pieces.push(said);
      }
      var at = 0;
      // Who speaks stands in the place, if the pack has their figure; then their round portrait is not needed.
      var figure = n.speaker ? T.artUrl(['figure.' + n.speaker + '.' + data.skin, 'figure.' + n.speaker]) : null;
      layer.classList.toggle('has-figure', !!figure);
      // The recorded voice of each piece, if the pack has it (not for who has reading aloud: they hear it already).
      var voices = pieces.map(voiceOf);
      if (voices.some(Boolean)) {
        T.fx.fetch(voices.filter(Boolean));
      }
      var body = show('<div class="dlg">' + stage(n.image, n.speaker) + '<div class="dlg-box">' + (n.speaker && !n.nolabel ?
        '<span class="dlg-name">' + esc(data.speakers[n.speaker] || n.speaker) + '</span>' : '') +
        '<p class="scene-text" data-piece></p><span class="dlg-dots" data-dots aria-hidden="true"></span>' +
        '<div class="actions"><button class="btn btn-main" data-focus data-next></button></div></div></div>');
      var button = body.querySelector('[data-next]');
      // The name of who speaks, over the pieces where they speak: in a text with «», only those that have them.
      var quotes = pieces.join(' ').indexOf('«') >= 0;
      function piece() {
        var last = at >= pieces.length - 1;
        marked[at] = marked[at] || glossify(pieces[at], glossed);
        body.querySelector('[data-piece]').innerHTML = marked[at];
        var label = body.querySelector('.dlg-name');
        if (label) {
          label.hidden = quotes && pieces[at].indexOf('«') < 0;
        }
        body.querySelector('[data-dots]').innerHTML = pieces.length > 1 ? pieces.map(function(p, i) {
          return '<i class="' + (i === at ? 'on' : i < at ? 'seen' : '') + '"></i>';
        }).join('') : '';
        button.textContent = last ? t('app_continue') : t('app_next');
        alive('speak', n.speaker || null, figure);
        alive('talk', Math.max(1500, pieces[at].length * 45));
        if (voices[at]) {
          T.fx.voice(voices[at]);
        }
      }
      button.onclick = function() {
        if (at >= pieces.length - 1) {
          done();
          return;
        }
        at++;
        piece();
        button.focus();
      };
      piece();
    }

    /**
     * The recorded voice of a text the scene shows, if the art pack has it (looked for by the text itself, as the pieces
     * told); none for who has reading aloud, who hears it already.
     *
     * @param {string} text
     * @return {string|null}
     */
    function voiceOf(text) {
      if (!text || !T.fx || !T.lineKey || T.adapted.readaloud) {
        return null;
      }
      var key = T.lineKey(text);
      if (T.artUrl([key])) {
        return key;
      }
      // A text with the name of the creature or of the student has its voice without the sentences that carry it (no
      // recording can say a name the student chose): those stay as text.
      var names = [creatureName(), myName()].filter(Boolean);
      var sentences = text.match(/[^.!?…]+[.!?…]+[»"”)]*\s*|[^.!?…]+$/g) || [text];
      var rest = sentences.filter(function(x) {
        return !names.some(function(name) {
          return x.indexOf(name) >= 0;
        });
      }).join('').trim();
      key = rest && rest !== text ? T.lineKey(rest) : '';
      return key && T.artUrl([key]) ? key : null;
    }

    /**
     * What happens, said aloud: the voice of each text, one after the other; who speaks in the place moves while
     * someone says something between «».
     *
     * @param {string[]} texts
     */
    function sayAloud(texts) {
      var said = texts.filter(function(text) {
        return voiceOf(text);
      });
      var i = 0;
      (function one() {
        if (i < said.length) {
          var text = said[i++];
          if (text.indexOf('«') >= 0) {
            alive('talk', Math.max(1500, text.length * 45));
          }
          T.fx.voice(voiceOf(text), one);
        }
      })();
    }

    /**
     * Who says what goes between «» where nobody is named: the one who speaks most in the told moments of the scene.
     *
     * @return {string|null}
     */
    function mainSpeaker() {
      var count = {};
      Object.keys(sc.nodes).forEach(function(k) {
        var who = sc.nodes[k].type === 'story' && sc.nodes[k].speaker;
        if (who) {
          count[who] = (count[who] || 0) + 1;
        }
      });
      return Object.keys(count).sort(function(a, b) {
        return count[b] - count[a];
      })[0] || null;
    }

    /**
     * The suspects of a node standing in the place (`lineup`: the options of a choice of candidates, each with its
     * drawing): those that what is known contradicts have stepped back, or those `out` says. Without the place alive,
     * or with a drawing missing, nobody stands, and the page is as always.
     *
     * @param {Object} n The node.
     * @param {Function} [out] Whether a suspect (an option) has stepped back, if not by what is known.
     * @return {boolean} Whether they stand.
     */
    function lineup(n, out) {
      var line = n.lineup && sc.nodes[n.lineup];
      var list = line && layer.classList.contains('live') ? (line.options || []).map(function(o) {
        return {id: o.id, url: T.artUrl(['being.' + o.id]), out: out ? out(o) : (o.contradicts || []).some(function(c) {
          return run.clues.indexOf(c) >= 0;
        })};
      }) : [];
      if (!list.length || list.some(function(one) {
        return !one.url;
      })) {
        alive('lineup', null);
        return false;
      }
      alive('lineup', list);
      return true;
    }

    /**
     * A text in pieces of about 45 words, cut where a sentence ends: a parchment a child of nine reads in one go.
     *
     * @param {string} text
     * @return {string[]}
     */
    function cut(text) {
      if (T.adapted.still || T.adapted.readaloud) {
        return [text];
      }
      var sentences = text.match(/[^.!?…]+[.!?…]+[»"”)]*\s*|[^.!?…]+$/g) || [text];
      var pieces = [];
      var words = 0;
      sentences.forEach(function(sentence) {
        var count = sentence.trim().split(/\s+/).length;
        if (pieces.length && words + count <= 45) {
          pieces[pieces.length - 1] += sentence;
          words += count;
        } else {
          pieces.push(sentence);
          words = count;
        }
      });
      return pieces.map(function(p) {
        return p.trim();
      }).filter(Boolean);
    }

    /**
     * The stage of a moment: the painting of the place, who speaks in big, and the student's hero and creature.
     *
     * @param {string} image
     * @param {string} who The speaker, if anybody speaks.
     * @return {string}
     */
    function stage(image, who) {
      var face = who ? T.artUrl(['speaker.' + who + '.' + data.skin, 'speaker.' + who, 'boss.' + who]) : '';
      var me = '';
      var c = !cm && world && world.character;
      if (c && c.role) {
        var traits = {};
        try {
          traits = JSON.parse(c.creature || '{}') || {};
        } catch (e) {
          traits = {};
        }
        me = '<span class="dlg-me" aria-hidden="true">' + T.avatar({role: c.role, nameform: c.nameform, lineage: c.lineage,
          look: c.appearance, stage: c.stage, family: world.family}) + (c.creature ? '<span class="dlg-creature">' +
          T.creature(traits, c.creaturestage) + '</span>' : '') + '</span>';
      }
      return '<div class="dlg-stage">' + picture(image || sceneImage(), data) + (face ? '<img class="dlg-face" src="' +
        esc(face) + '" alt="">' : '') + me + '</div>';
    }

    /**
     * The world answers what was chosen: its picture changes or moves, if the option says how.
     *
     * @param {Element} body
     * @param {Object} o The option or the item.
     */
    function answer(body, o) {
      var pic = body.querySelector('.scene-pic');
      alive('answer', o.fx || '', o.correct === true || (o.points || 0) > 0 ? 'good' : o.correct === false ? 'bad' : '');
      if (o.image && T.artUrl(pictureKeys(o.image, data))) {
        alive('image', T.artUrl(pictureKeys(o.image, data)));
      }
      if (o.image && pic) {
        var art = T.artUrl(pictureKeys(o.image, data));
        var img = pic.querySelector('img');
        if (art && img) {
          pic.classList.add('fx-fade');
          setTimeout(function() {
            img.src = art;
            pic.classList.remove('fx-fade');
          }, 250);
        }
      }
      if (o.fx && pic && /^(light|dark|shake|grow)$/.test(o.fx)) {
        pic.classList.remove('fx-' + o.fx);
        void pic.offsetWidth;
        pic.classList.add('fx-' + o.fx);
      }
    }

    /**
     * The right answers in a node, or in several added up: options marked correct in a choice, items placed at the first
     * try in a classify (as the server counts them).
     *
     * @param {string|Array} nodes
     * @return {number}
     */
    function correctIn(nodes) {
      return [].concat(nodes).reduce(function(sum, node) {
        return sum + correctInOne(node);
      }, 0);
    }

    function correctInOne(node) {
      var n = sc.nodes[node] || {};
      if (n.type === 'classify') {
        var seen = {};
        var right = 0;
        run.events.forEach(function(e) {
          if (e.n === node && e.item && !seen[e.item]) {
            seen[e.item] = true;
            var item = (n.items || []).filter(function(i) {
              return i.id === e.item;
            })[0];
            right += item && item.bin === e.bin ? 1 : 0;
          }
        });
        return right;
      }
      // The last answer: the options picked, or the things given in the last round of a tend.
      var picks = [];
      run.events.forEach(function(e) {
        if (e.n === node && (e.pick || e.give)) {
          picks = e.pick || e.give;
        }
      });
      var opts = n.type === 'tend' ? n.items || [] : n.type === 'find' ? n.spots || [] : n.options || [];
      if (n.type === 'find') {
        picks = run.events.filter(function(e) {
          return e.n === node && e.spot;
        }).map(function(e) {
          return e.spot;
        });
      }
      return picks.filter(function(id) {
        return opts.some(function(o) {
          return o.id === id && o.correct === true;
        });
      }).length;
    }

    /**
     * A mistake: the protector's shield stops the points it costs, once a play (twice with «Long shield»); with «Team
     * guard» in the play, the first mistake of each one who decides. As the server counts it.
     */
    function mistake() {
      if (cm) {
        return;
      }
      var hand = turnOf();
      if (hand && gifted('guard') && !run.guarded[hand.userid]) {
        run.guarded[hand.userid] = true;
        T.toast(esc(t('app_shield_team')));
      } else if (data.role === 'protector' && run.shield < (data.shields || 1)) {
        run.shield++;
        T.toast(esc(t('app_shield_stops')));
      }
    }

    /**
     * Whether someone in a play as a team brings a gift of their talents: «guard» (the guard of the team) or «trail»
     * (the trail of the scout, for the team).
     *
     * @param {string} gift
     * @return {boolean}
     */
    function gifted(gift) {
      if (cm || !opts.team || !opts.team.length || !world.team) {
        return false;
      }
      var players = [cfg.userid].concat(opts.team).map(Number);
      return (world.team.members || []).some(function(m) {
        return players.indexOf(Number(m.userid)) >= 0 && (m.gifts || []).indexOf(gift) >= 0 &&
          (gift !== 'trail' || !m.me);
      });
    }

    /**
     * Whether the play has a role: the student's own, or the scout's lent by a teammate with «Shared trail».
     *
     * @param {string} role
     * @return {boolean}
     */
    function hasRole(role) {
      return data.role === role || (role === 'scout' && gifted('trail'));
    }

    /**
     * What the next hint costs: one breath; the sage gets the first free and pays half for the rest; free in class. The
     * day the messengers of another team brought one, the first is free; and in a play as a team, from the emblem of its
     * banner, one more.
     *
     * @return {number}
     */
    function hintCost() {
      // With «more help» or the aids of the first steps, the lens is free (the server charges the same).
      if (cm || data.aided || T.adapted.hints) {
        return 0;
      }
      var team = world && world.team;
      var free = (team && team.brought ? 1 : 0) + (team && opts.team && opts.team.length && team.bannerstage &&
        team.bannerstage.stage >= 3 ? 1 : 0);
      if (data.role === 'sage') {
        // The first free (the first two with «Hints of the house»), and half for the rest: as the server charges.
        var gift = (data.househints ? 2 : 1) + free;
        return run.hints < gift ? 0 : (run.hints - gift + 1) % 2;
      }
      return run.hints < free ? 0 : 1;
    }

    /**
     * The role of the student at hand in every decision, with its verb: the shield of the protector (ready or used), the
     * lens of the sage, the cheer of the healer and the trail of the scout, which finds what only they see. In a team
     * play, who decides now, in turn.
     *
     * @param {Object} n The node.
     * @param {Element} body
     */
    function roleBar(n, body) {
      if (cm || !data.role) {
        return;
      }
      var role = data.role;
      var hand = turnOf();
      if (hand && Number(hand.userid) !== Number(cfg.userid)) {
        // The role at hand is the one of the student on whose device it is: the others decide with their words. A scout
        // with «Shared trail» brings theirs.
        role = hand.character && hand.character.role === 'scout' && (hand.gifts || []).indexOf('trail') >= 0 ? 'scout' : '';
      }
      var spent = run.shield >= (data.shields || 1);
      if (hand) {
        var who = document.createElement('div');
        who.className = 'hand';
        who.setAttribute('aria-live', 'polite');
        who.innerHTML = (hand.character ? '<span class="hand-face" aria-hidden="true">' + T.avatar({role: hand.character.role,
          nameform: hand.character.nameform, lineage: hand.character.lineage, look: hand.character.appearance,
          stage: hand.character.stage, family: world.family}) + '</span>' : '') + '<span>' + esc(t('app_hand',
          {name: hand.name, role: hand.character ? T.roleName(world.family, hand.character.role,
          hand.character.nameform) : ''})) + '</span>';
        body.insertBefore(who, body.firstChild);
      }
      var verbs = {
        protector: {icon: 'shield', label: spent ? t('app_role_shield_used') : t('app_role_shield_ready')},
        sage: {icon: 'log', label: t('app_role_notebook')},
        healer: {icon: 'cheer', label: t('app_role_cheer')},
        scout: {icon: 'trail', label: t('app_role_trail')}
      };
      var verb = verbs[role];
      if (!verb) {
        return;
      }
      // In the bar on top, beside the title: it never covers what is read or chosen.
      var bar = document.createElement('div');
      bar.className = 'role-bar';
      bar.innerHTML = '<button type="button" class="role-verb ' + esc(role) + (role === 'protector' && spent ? ' used' : '') +
        '" data-role>' + T.icon(verb.icon) + '<span>' + esc(verb.label) + '</span></button>';
      var top = layer.querySelector('.scene-top .scene-title');
      if (top) {
        top.insertAdjacentElement('afterend', bar);
      } else {
        body.appendChild(bar);
      }
      bar.querySelector('[data-role]').onclick = function() {
        if (role === 'sage') {
          var lens = body.querySelector('[data-hint]');
          if (lens) {
            lens.click();
          } else {
            T.toast(esc(t('app_role_notebook_none')));
          }
        } else if (role === 'scout') {
          var found = body.querySelectorAll('[data-trail]');
          run.trailed[run.node] = true;
          if (found.length) {
            found.forEach(function(o) {
              o.hidden = false;
              o.removeAttribute('data-trail');
              o.classList.add('found');
            });
            if (T.fx) {
              T.fx.sound('choose');
            }
            T.toast(esc(t('app_role_trail_found')));
          } else {
            T.toast(esc(t('app_role_trail_none')));
          }
        } else {
          T.toast(esc(t(role === 'protector' ? 'app_role_shield_help' : 'app_role_cheer_help')));
        }
      };
    }

    /**
     * What each role sees of a decision (views): in a team session, only the piece of the role of this tablet, the others
     * are on theirs; on one tablet, alone or as a team, all of them, each with its role.
     *
     * @param {Object} n The node.
     * @return {string}
     */
    function pieces(n) {
      var views = n.views || {};
      var roles = Object.keys(views).filter(function(r) {
        return views[r];
      });
      if (!roles.length) {
        return '';
      }
      if (huddle) {
        // Its own piece, and those of the roles nobody in the session has: they would be lost otherwise.
        var held = (world.team ? world.team.members : []).filter(function(m) {
          return Number(m.userid) !== Number(cfg.userid) && huddleIds.indexOf(Number(m.userid)) >= 0 && m.character;
        }).map(function(m) {
          return m.character.role;
        });
        return roles.filter(function(r) {
          return r === data.role || held.indexOf(r) < 0;
        }).map(function(r) {
          return '<div class="piece mine"><b>' + esc(r === data.role ? t('app_piece_mine') : t('app_piece_of',
            T.roleName(world ? world.family : '', r, 'm'))) + '</b><p>' + esc(tx(views[r])) + '</p></div>';
        }).join('');
      }
      // On one device, what each role sees, folded under its name: the decision first, and each piece when wanted.
      return '<div class="pieces folded"><span class="pieces-title">' + esc(t('app_pieces_title')) + '</span>' +
        roles.map(function(r) {
          return '<details class="piece"><summary>' + T.icon(ROLEICON[r] || 'log') + '<b>' + esc(t('app_piece_of',
            T.roleName(world ? world.family : '', r, 'm'))) + '</b></summary><p>' + esc(tx(views[r])) + '</p></details>';
        }).join('') + '</div>';
    }

    /**
     * In a team session, the decision at hand as the other tablets see it: what it asks and, if it is one to choose, the
     * options that can be chosen now; and who decides.
     *
     * @return {Object|null}
     */
    function huddleNow() {
      var n = sc && run ? sc.nodes[run.node] : null;
      if (!n) {
        return null;
      }
      // Using something from the backpack and saying why are choices too; putting in order is choosing the next step.
      var chooses = ['choice', 'use', 'justify'].indexOf(n.type) >= 0;
      var max = n.type === 'justify' ? (n.select && n.select.max) || 2 : (n.select && n.select.max) || 1;
      var multi = chooses && max > 1;
      var kind = chooses ? (multi ? 'multi' : 'choose') : n.type === 'order' ? 'choose' : n.type;
      var options = [];
      var bins = [];
      if (kind === 'choose' || kind === 'multi') {
        layer.querySelectorAll('.opt[data-id]').forEach(function(b) {
          if (!b.hidden && !b.disabled) {
            options.push({id: b.dataset.id, label: b.textContent.trim()});
          }
        });
      } else if (kind === 'classify') {
        // The things still to place (those on the table and those placed this time), without what was seen with the lupa.
        layer.querySelectorAll('.cls-thing[data-thing], .cls-thing[data-take]').forEach(function(b) {
          var label = b.cloneNode(true);
          label.querySelectorAll('small, .ev-tag').forEach(function(x) {
            x.remove();
          });
          options.push({id: b.dataset.thing || b.dataset.take, label: label.textContent.trim()});
        });
        layer.querySelectorAll('.bin[data-bin]').forEach(function(b) {
          bins.push({id: b.dataset.bin, label: b.textContent.trim()});
        });
      }
      if ((kind === 'multi' || kind === 'classify') && !options.length) {
        // Drawn some other way (the pieces of a card): it is decided on this tablet.
        kind = n.type;
      }
      var hand = turnOf();
      // What each role sees: each tablet gets only its own piece.
      var views = {};
      Object.keys(n.views || {}).forEach(function(r) {
        views[r] = tx(n.views[r]);
      });
      // What the answers told so far, for every tablet.
      var known = run.clues.map(function(c) {
        return tx(sc.clues[c]);
      });
      return {node: run.node, kind: kind, prompt: n.prompt ? tx(easy(n, 'prompt')) : '', options: options, views: views, known: known,
        hand: hand ? Number(hand.userid) : Number(cfg.userid), bins: bins, max: max,
        min: Math.min(max, (n.select && n.select.min) || 1)};
    }

    /**
     * In a team session, what the hand decided on their tablet: played here as if it were touched here. One option; or the
     * options picked of several; or, to sort, each thing to its group; and then confirmed.
     *
     * @param {string} id
     * @param {string} [answer] Of several or to sort, the whole answer (JSON).
     */
    function huddlePlay(id, answer) {
      var tap = function(sel) {
        var b = layer.querySelector(sel);
        if (b && !b.disabled && !b.hidden) {
          b.click();
          return true;
        }
        return false;
      };
      var whole = null;
      try {
        whole = answer ? JSON.parse(answer) : null;
      } catch (e) {
        whole = null;
      }
      if (!whole) {
        tap('.opt[data-id="' + id + '"]');
        return;
      }
      if (Array.isArray(whole)) {
        // What was picked here and not there, off; what was picked there, on.
        layer.querySelectorAll('.opt.sel[data-id]').forEach(function(b) {
          if (whole.indexOf(b.dataset.id) < 0) {
            b.click();
          }
        });
        whole.forEach(function(pick) {
          var b = layer.querySelector('.opt[data-id="' + pick + '"]');
          if (b && !b.classList.contains('sel')) {
            tap('.opt[data-id="' + pick + '"]');
          }
        });
      } else {
        Object.keys(whole).forEach(function(thing) {
          // A thing placed elsewhere this time goes back to the table first.
          tap('[data-take="' + thing + '"]');
          if (tap('[data-thing="' + thing + '"]')) {
            tap('[data-bin="' + whole[thing] + '"]');
          }
        });
      }
      tap('[data-confirm]');
    }

    /**
     * A list in another order, each time (the class screen does not load the minigames, which have their own).
     *
     * @param {Array} list
     * @return {Array}
     */
    function mixed(list) {
      var out = list.slice();
      for (var i = out.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var keep = out[i];
        out[i] = out[j];
        out[j] = keep;
      }
      return out;
    }

    /**
     * The drawing of a thing to sort that is in the catalog of the mission, when the art pack has it: before its name.
     *
     * @param {Object} it
     * @return {string}
     */
    function clsPic(it) {
      var key = (data.pictures || {})[it.id];
      var url = key ? T.artUrl([key]) : null;
      return url ? '<img class="cls-pic" src="' + esc(url) + '" alt="">' : '';
    }

    /**
     * In a team session with the information shared out: until everybody in told the team their piece, the decision
     * waits on this tablet too, saying how many told it.
     *
     * @param {Object|null} wait told, all; or null, when it can be decided.
     */
    function huddleGate(wait) {
      layer.classList.toggle('team-wait', !!wait);
      var note = layer.querySelector('.team-wait-note');
      if (!wait) {
        if (note) {
          note.remove();
        }
        return;
      }
      if (!note) {
        note = document.createElement('p');
        note.className = 'team-wait-note';
        note.setAttribute('role', 'status');
        var prompt = layer.querySelector('.scene-prompt');
        if (prompt) {
          prompt.parentNode.insertBefore(note, prompt.nextSibling);
        } else {
          layer.appendChild(note);
        }
      }
      note.textContent = t('app_huddle_told', wait);
    }

    /**
     * In a team session, who is still in: if the hand of a decision to choose went away, it passes to the next one who
     * is there.
     *
     * @param {number[]} ids
     */
    function huddlePresent(ids) {
      huddleIds = ids;
      var n = sc && run ? sc.nodes[run.node] : null;
      var hand = turnOf();
      if (!n || ['choice', 'use', 'justify', 'classify', 'order'].indexOf(n.type) < 0 || !hand ||
        ids.indexOf(Number(hand.userid)) >= 0 ||
        layer.querySelector('.opt.sel')) {
        return;
      }
      var order = [cfg.userid].concat(opts.team || []);
      for (var k = 1; k <= order.length; k++) {
        if (ids.indexOf(Number(order[(run.turn + k) % order.length])) >= 0) {
          run.turn += k;
          run.handfor[run.node] = 0;
          next();
          return;
        }
      }
    }

    /**
     * An event with who of the team decided it, in a play as a team: it stays with the play, as its team does.
     *
     * @param {Object} e
     * @return {Object}
     */
    function by(e) {
      var hand = turnOf();
      if (hand) {
        e.by = Number(hand.userid);
      }
      return e;
    }

    /**
     * A decision with an option that asks for a role of the team goes to who plays that role, if they are playing:
     * their turn, out of the order.
     *
     * @param {Object} n The node.
     */
    function handTo(n) {
      if (!opts.team || !opts.team.length || !world.team || run.handfor[run.node] !== undefined) {
        return;
      }
      // An option of the role of whoever plays it (teamrole), or of the role of the student on whose device it is.
      var need = (n.options || []).map(function(o) {
        var req = o.requires || {};
        return req.teamrole || (req.role && hasRole(req.role) ? req.role : '');
      }).filter(Boolean)[0];
      var who = 0;
      if (need) {
        [cfg.userid].concat(opts.team).forEach(function(id) {
          var m = (world.team.members || []).filter(function(mm) {
            return Number(mm.userid) === Number(id);
          })[0];
          if (!who && m && m.character && m.character.role === need) {
            who = Number(id);
          }
        });
      }
      run.handfor[run.node] = who;
    }

    /**
     * Who decides now in a play as a team on one device: each decision a different one, in turn, starting with who
     * opened it.
     *
     * @return {Object|null} A member of the team (name, character), or null if it is not a play as a team.
     */
    function turnOf() {
      if (!opts.team || !opts.team.length || !world.team) {
        return null;
      }
      var order = [cfg.userid].concat(opts.team).map(Number);
      // The council of the team chose the role that starts the hand today: its first player in this scene, first.
      var first = (world.team.members || []).filter(function(m) {
        return world.team.first && m.character && m.character.role === world.team.first &&
          order.indexOf(Number(m.userid)) >= 0;
      })[0];
      if (first) {
        var at = order.indexOf(Number(first.userid));
        order = order.slice(at).concat(order.slice(0, at));
      }
      var id = run.handfor[run.node] || order[run.turn % order.length];
      return (world.team.members || []).filter(function(m) {
        return Number(m.userid) === Number(id);
      })[0] || null;
    }

    /**
     * The hints of a decision, if it has any: the ones already opened and a button for the next.
     *
     * @param {Object} n The node.
     * @param {Element} body
     */
    /**
     * What can be looked at in an option or an item: its observation and its tool, if the student carries the tool (the
     * lens is always at hand). Not at the class screen.
     *
     * @param {Object} thing
     * @return {Object|null} {text, tool}
     */
    function lookOf(thing) {
      if (cm || !thing || !thing.look) {
        return null;
      }
      var look = typeof thing.look === 'string' ? {text: thing.look} : thing.look;
      var tool = look.tool || 'lens';
      var needs = {lens: '', net: 'net', lantern: 'lantern'}[tool];
      if (needs === undefined || (needs && !has({item: needs}))) {
        return null;
      }
      return {text: look.text || '', tool: tool};
    }

    function looked(id) {
      return (run.looks[run.node] || []).indexOf(id) >= 0;
    }

    function lookLabel(look, label) {
      var cost = hintCost();
      return t('app_look_on', {tool: t('app_look_' + look.tool), thing: label,
        price: cost ? t('app_look_price', cost) : t('app_look_free')});
    }

    function lookSaid(look, label) {
      return '<p class="look-said">' + T.icon(look.tool) + '<span>' + (label ? '<b>' + esc(label) + '</b> ' : '') +
        esc(tx(look.text)) + '</span></p>';
    }

    /**
     * Looks at a thing with its tool: it costs as a hint does, and what is seen is a note of the field notebook.
     *
     * @param {Object} thing
     * @param {Function} done
     */
    function doLook(thing, done) {
      var cost = hintCost();
      if (cost > (data.breath || 0) - run.spent) {
        T.toast(esc(t('app_hint_nobreath')), true);
        return;
      }
      run.events.push({n: run.node, look: thing.id});
      run.looks[run.node] = (run.looks[run.node] || []).concat([thing.id]);
      run.hints++;
      run.spent += cost;
      if (T.fx) {
        T.fx.sound('choose');
      }
      if (run.hintDraw) {
        run.hintDraw();
      }
      done();
    }

    /**
     * The tool next to each option that can be looked at.
     *
     * @param {Object[]} options
     * @param {Element} body
     */
    function lookTools(options, body) {
      body.querySelectorAll('.opts .opt[data-id]').forEach(function(b) {
        var o = options.filter(function(x) {
          return x.id === b.dataset.id;
        })[0];
        var look = lookOf(o);
        if (!look) {
          return;
        }
        var row = document.createElement('div');
        row.className = 'opt-row';
        b.parentNode.insertBefore(row, b);
        row.appendChild(b);
        if (looked(o.id)) {
          row.insertAdjacentHTML('beforeend', lookSaid(look, ''));
          return;
        }
        var tool = document.createElement('button');
        tool.type = 'button';
        tool.className = 'look-tool';
        tool.innerHTML = T.icon(look.tool);
        tool.setAttribute('aria-label', lookLabel(look, tx(o.label)));
        tool.title = tool.getAttribute('aria-label');
        row.appendChild(tool);
        tool.onclick = function() {
          doLook(o, function() {
            tool.remove();
            row.insertAdjacentHTML('beforeend', lookSaid(look, ''));
          });
        };
      });
    }

    /**
     * The help that comes by itself (the ladder of the common guide of design by ages): with a decision on screen and the
     * student still, a clock runs, not while somebody speaks nor with the page out of sight, and every 15 s of it brings
     * the next step. First the question, said aloud; then a ring on what there is to touch, and the hero points at it;
     * then it is done together: the creature sniffs out an option that is not it, if it can, or the notebook opens by
     * itself at its first note, without breath. A touch, a key or the wheel puts the clock back and takes the ring away.
     * Each step comes once a decision, and again if the decision comes back after a mistake (it is not the same question
     * then). What is hidden to be found in a painting is never shown by the ring. Not at the class screen: the teacher
     * leads there.
     *
     * @param {Object} n The node.
     * @param {Element} body
     */
    function ladder(n, body) {
      if (cm || opts.preview) {
        return;
      }
      var WAIT = 15;
      var key = run.node + '#' + (run.tried[run.node] || []).length;
      run.ladder = run.ladder || {};
      var done = run.ladder[key] = run.ladder[key] || {};
      var quiet = 0;
      var all = function(selector) {
        return [].filter.call(layer.querySelectorAll(selector), function(el) {
          return !el.disabled && !el.hidden && el.offsetParent !== null;
        });
      };
      var OPEN = '.opts .opt[data-id]:not(.sel), .cls-thing[data-thing], .card-piece, .path-stop:not(.visited)';
      // What there is to touch now: the boxes, with a thing in hand; the button that confirms, with all chosen; what is
      // left to choose or to place; and else, what goes on.
      var touch = function() {
        var lists = [all('.bin'), all('.scene-body [data-confirm], .scene-body [data-days]'), all(OPEN),
          all('.scene-body [data-next], .scene-body [data-go], .scene-body [data-goon]')];
        return lists.filter(function(l) {
          return l.length;
        })[0] || [];
      };
      var asking = function() {
        return all(OPEN + ', .bin, .find-spot:not(.found)').length > 0;
      };
      var steps = {
        say: function() {
          var prompt = body.querySelector('.scene-prompt');
          var text = tx(easy(n, 'prompt'));
          if (!prompt || !text || !asking()) {
            return false;
          }
          if (T.adapted.readaloud && T.readAloud) {
            T.readAloud(prompt);
            return true;
          }
          if (!voiceOf(text)) {
            return false;
          }
          sayAloud([text]);
          return true;
        },
        ring: function() {
          var there = touch();
          if (!there.length) {
            return false;
          }
          there.forEach(function(el) {
            el.classList.add('nudge');
          });
          alive('hint');
          return true;
        },
        together: function() {
          if (!asking()) {
            return false;
          }
          var sniff = all('.scene-body [data-sniff]')[0];
          if (sniff) {
            sniff.click();
            return true;
          }
          var notes = body.querySelector('.scene-hints');
          return !!(notes && notes.freeNote && notes.freeNote());
        }
      };
      var wake = function() {
        quiet = 0;
        layer.querySelectorAll('.nudge').forEach(function(el) {
          el.classList.remove('nudge');
        });
      };
      var EVENTS = ['pointerdown', 'keydown', 'wheel'];
      EVENTS.forEach(function(e) {
        layer.addEventListener(e, wake, true);
      });
      var timer = setInterval(function() {
        if (!body.isConnected) {
          clearInterval(timer);
          EVENTS.forEach(function(e) {
            layer.removeEventListener(e, wake, true);
          });
          return;
        }
        var talking = (T.fx && T.fx.speaking && T.fx.speaking()) || (window.speechSynthesis && window.speechSynthesis.speaking);
        if (document.hidden || talking || ++quiet < WAIT) {
          return;
        }
        quiet = 0;
        // The next step not given yet; one that has nothing to do here gives way to the one after, at once.
        Object.keys(steps).some(function(step) {
          if (done[step]) {
            return false;
          }
          done[step] = true;
          return steps[step]();
        });
      }, 1000);
    }

    function hintArea(n, body) {
      ladder(n, body);
      if (run.keep && run.node === run.from) {
        var keep = document.createElement('p');
        keep.className = 'aid-note keep-note';
        keep.innerHTML = T.icon('log') + ' <span><b>' + esc(t('app_keep')) + '</b> ' + esc(tx(run.keep)) + '</span>';
        var top = body.querySelector('.scene-prompt');
        if (top) {
          top.insertAdjacentElement('beforebegin', keep);
        } else {
          body.insertBefore(keep, body.firstChild);
        }
        run.keep = '';
      }
      if (data.aided && n.aid && !cm) {
        // The aid of the first steps: a note of the field notebook under the question.
        var aid = document.createElement('p');
        aid.className = 'aid-note';
        aid.innerHTML = T.icon('log') + ' ' + esc(tx(n.aid));
        var prompt = body.querySelector('.scene-prompt');
        if (prompt) {
          prompt.insertAdjacentElement('afterend', aid);
        } else {
          body.insertBefore(aid, body.firstChild);
        }
      }
      if (!n.hints || !n.hints.length) {
        return;
      }
      var box = document.createElement('div');
      box.className = 'scene-hints';
      body.appendChild(box);
      var draw = function() {
        var opened = run.opened[run.node] || [];
        var cost = hintCost();
        run.hintDraw = box.isConnected ? draw : null;
        // The field notebook: what the expedition wrote down that makes one think, not the answer.
        // The note the help brought by itself says who brought it, without breath.
        var brought = (run.brought || {})[run.node];
        box.innerHTML = opened.map(function(i) {
          return '<p class="hint">' + T.icon('log') + ' <span>' + esc(tx(n.hints[i])) + (brought === i ? '<small>' +
            esc(world && world.character && world.character.creaturestage >= 1 ? t('app_freenote_brought', creatureName()) :
            t('app_freenote_open')) + '</small>' : '') + '</span></p>';
        }).join('') + (opened.length < n.hints.length ? '<button class="btn btn-soft" type="button" data-hint>' +
          T.icon('log') + ' ' + esc(cost ? t('app_hint_cost', {cost: cost, left: Math.max(0, (data.breath || 0) - run.spent)}) :
          t('app_hint_free')) + '</button>' : '');
        var b = box.querySelector('[data-hint]');
        // A note opened: paid, or brought by the help that comes by itself, free (the first of the node, if none is open).
        var open = function(free) {
          if (!free && cost > (data.breath || 0) - run.spent) {
            T.toast(esc(t('app_hint_nobreath')), true);
            return;
          }
          alive('hint');
          var index = opened.length;
          run.events.push(free ? {n: run.node, hint: index, idle: 1} : {n: run.node, hint: index});
          run.opened[run.node] = opened.concat([index]);
          if (free) {
            run.brought = run.brought || {};
            run.brought[run.node] = index;
          } else {
            run.hints++;
            run.spent += cost;
          }
          draw();
        };
        if (b) {
          b.onclick = function() {
            open(false);
          };
        }
        box.freeNote = opened.length || !b ? null : function() {
          open(true);
          return true;
        };
      };
      draw();
    }

    /**
     * The option the creature strikes out sniffing a choice, as the server decides it: the first the scene marks as
     * clearly not it (obvious), or else the last that is not right; only in a question of one answer with three options
     * or more, never in a decision without a right answer. Null where it cannot sniff.
     *
     * @param {Object} n
     * @return {string|null}
     */
    function sniffs(n) {
      if (n.type === 'choice' && n.sniff_wrong && n.sniff_wrong.option) {
        // Where its scent goes wrong (the feather smells of bird), it points at that option instead of striking one.
        return n.sniff_wrong.option;
      }
      var one = !n.dilemma && ((n.select && n.select.max) || 1) === 1;
      if (!one || (n.options || []).length < 3) {
        return null;
      }
      var wrong = n.options.filter(function(o) {
        return o.correct === false && !o.requires;
      });
      var obvious = wrong.filter(function(o) {
        return o.obvious;
      });
      return obvious.length ? obvious[0].id : wrong.length ? wrong[wrong.length - 1].id : null;
    }

    /**
     * The creature can sniff here: hatched, in a scene that lets it, not used yet in this play, and with something to
     * strike out that is still there.
     *
     * @param {Object} n
     * @param {string[]} tried
     * @return {string|null} The option it would strike out.
     */
    function canSniff(n, tried) {
      var c = !cm && world && world.character;
      if (!c || !(c.creaturestage >= 1) || sc.sniff === false || Object.keys(run.sniffed).length) {
        return null;
      }
      var struck = sniffs(n);
      return struck && tried.indexOf(struck) < 0 ? struck : null;
    }

    function sniffedOption(o) {
      return '<button class="opt sniffed" type="button" disabled><s>' + esc(tx(o.label)) + '</s><small>' +
        esc(t('app_sniffed', creatureName())) + '</small></button>';
    }

    function choice(n) {
      var max = (n.select && n.select.max) || 1;
      var min = (n.select && n.select.min) || max;
      var multi = max > 1;
      var selected = [];
      // A card to fill in, on a device: the same choice, played by taking each piece to it.
      if (n.layout === 'card' && multi && !cm) {
        cardChoice(n, min, max);
        return;
      }
      // At the class screen the phones vote by letter: the options stay where they are.
      var tried = cm ? [] : run.tried[run.node] || [];
      // What contradicts what the answers told is crossed out by itself: who asked well arrives with one candidate.
      var crossed = function(o) {
        return (o.contradicts || []).some(function(c) {
          return run.clues.indexOf(c) >= 0;
        });
      };
      // The options in their place, or in another order each play where the scene says so (`shuffle`: the candidates of
      // the owl, so that the right one is not learned by where it is).
      var options = n.options;
      if (n.shuffle) {
        run.orders = run.orders || {};
        run.orders[run.node] = run.orders[run.node] || mixed(n.options.map(function(o) {
          return o.id;
        }));
        options = run.orders[run.node].map(function(id) {
          return n.options.filter(function(o) {
            return o.id === id;
          })[0];
        });
      }
      var open = options.filter(function(o) {
        return has(o.requires) && tried.indexOf(o.id) < 0 && !crossed(o);
      });
      var opts = options.filter(function(o) {
        // What other scenes left (flags) is memory, not something to get: without it, the option is not there; nor is
        // the option of another role (each role sees only its own). What was tried and led back here is not there
        // either: the world changed, it is not the same question again.
        // In the backpack, only what is carried.
        var hidden = o.requires && (o.requires.flag || o.requires.role || o.requires.nature || n.type === 'use') &&
          !has(o.requires);
        return !hidden && tried.indexOf(o.id) < 0;
      }).map(function(o) {
        if (!has(o.requires)) {
          return '<button class="opt locked" type="button" disabled><b>' + esc(tx(o.label)) + '</b><small>' +
            esc(o.locked ? tx(o.locked) : missing(o.requires)) + '</small></button>';
        }
        if (crossed(o)) {
          return '<button class="opt crossed" type="button" disabled><s>' + esc(tx(o.label)) + '</s><small>' +
            esc(t('app_clue_crossed')) + '</small></button>';
        }
        if (run.sniffed[run.node] === o.id) {
          return sniffedOption(o);
        }
        var letter = cm ? '<span class="opt-letter">' + LETTERS[open.indexOf(o)] + '</span>' : '';
        var art = o.item ? T.artUrl(['item.' + o.item]) : '';
        letter += art ? '<img class="item-art" src="' + esc(art) + '" alt="">' : '';
        // What only the scout sees is there to be found: it shows when they follow the trail.
        var trail = !cm && o.requires && o.requires.role === 'scout' && !run.trailed[run.node];
        return '<button class="opt' + (trail ? ' trail' : '') + '" type="button" data-id="' + esc(o.id) + '"' +
          (trail ? ' data-trail hidden' : '') + '>' + letter + esc(tx(o.label)) + evidenceTag(n, o) +
          '<span class="opt-votes" data-votes></span></button>';
      }).join('');
      var voting = cm && cm.vote && !multi && open.length >= 2;
      var said = natureSay(n);
      // The suspects in the place: in a choice of several (`away`), all of them in the light until it is confirmed.
      var multiAway = multi && n.options.some(function(o) {
        return o.away;
      });
      var standing = !cm && lineup(n, multiAway ? function() {
        return false;
      } : null);
      // The painting of the candidates is not needed when they stand in the place.
      var body = show((n.image && !standing ? picture(n.image, data) : '') + clueBox() + pieces(n) + (n.recall ?
        '<p class="justify-about">' + esc(n.recall) + '</p>' : '') + (said ? '<p class="scene-nature">' + esc(said) + '</p>' : '') +
        '<p class="scene-prompt">' + glossify(tx(easy(n, 'prompt')), {}) + '</p>' +
        (voting ? '<div class="class-vote" data-vote><button class="btn btn-soft" data-voteopen>' +
        esc(t('board_scene_voteopen')) + '</button></div>' : '') +
        '<div class="opts">' + opts + '</div>' + (canSniff(n, tried) ? '<div class="sniff-row"><button type="button" ' +
        'class="btn btn-soft sniff-btn" data-sniff>' + '<span class="sniff-pet" aria-hidden="true">' +
        T.creature(world.character.creature, world.character.creaturestage) + '</span>' +
        esc(t('app_sniff', creatureName())) + '</button></div>' : '') +
        (multi ? '<div class="counter" data-count>' + esc(t('app_chosen', '0/' + max)) +
        '</div><div class="actions"><button class="btn btn-main" data-confirm disabled>' + esc(t('app_confirm')) +
        '</button></div>' : '') + '<div data-out></div>');
      hintArea(n, body);
      roleBar(n, body);
      if (voting) {
        classVote(body, n, open);
      }
      // The choice of the suspects themselves: they are chosen with a touch in the place, too.
      alive('pick', standing && n.lineup === run.node ? function(id) {
        var b = body.querySelector('.opt[data-id="' + id + '"]');
        if (b && !b.disabled) {
          b.click();
        }
      } : null);
      function pick(id) {
        return n.options.filter(function(o) {
          return o.id === id;
        })[0];
      }
      lookTools(n.options, body);
      var sniff = body.querySelector('[data-sniff]');
      if (sniff) {
        sniff.onclick = function() {
          // Free, once a play: it strikes out one option that is clearly not it, and the evidence says so.
          var struck = canSniff(n, tried);
          if (!struck) {
            return;
          }
          run.events.push({n: run.node, sniff: 1});
          run.sniffed[run.node] = struck;
          var b = body.querySelector('.opt[data-id="' + struck + '"]');
          if (b && n.sniff_wrong) {
            // It got it wrong: it points at it, and the option stays to be chosen or not.
            b.classList.add('sniff-point');
            b.insertAdjacentHTML('beforeend', '<small class="sniff-tag">' + esc(t('app_sniff_point', creatureName())) +
              '</small>');
            // Where it points, in sight.
            b.scrollIntoView({block: 'center'});
          } else if (b) {
            b.outerHTML = sniffedOption(pick(struck));
          }
          sniff.closest('.sniff-row').classList.add('done');
          sniff.disabled = true;
          if (T.fx && !T.fx.sound('sniff')) {
            T.fx.sound('tap');
          }
          alive('sniff');
          T.toast(esc(n.sniff_wrong ? tx(n.sniff_wrong.say) : t('app_sniffed', creatureName())));
        };
      }
      body.querySelectorAll('.opt[data-id]').forEach(function(b) {
        b.onclick = function() {
          var id = b.dataset.id;
          if (!multi) {
            body.querySelectorAll('.opt, .look-tool').forEach(function(x) {
              x.disabled = true;
            });
            b.classList.add('sel');
            run.events.push(by({n: run.node, pick: [id]}));
            alive('pick', null);
            gather(body);
            if (standing && n.lineup === run.node) {
              alive('chosen', id, pick(id).correct !== false);
            }
            reveal(body, [pick(id)], function() {
              var o = pick(id);
              (o.effects || []).forEach(function(e) {
                run.extra += e.extraquestion || 0;
                if (e.flag) {
                  run.flags.push(e.flag);
                }
                clue(e);
              });
              if (o.correct === false) {
                mistake();
              }
              var target = o.next || n.next;
              // What the answer told sends back the suspects it contradicts.
              if (standing && n.lineup !== run.node) {
                lineup(n);
              }
              if (target === run.node) {
                run.tried[run.node] = (run.tried[run.node] || []).concat([id]);
                next();
              } else if (run.extra > 0 && n.extra && !run.extraused[run.node]) {
                run.extra--;
                run.extraused[run.node] = true;
                T.toast(esc(t('app_extraquestion')));
                next();
              } else {
                revealAfter(n, [o], target);
              }
            });
            return;
          }
          var i = selected.indexOf(id);
          if (i >= 0) {
            selected.splice(i, 1);
            b.classList.remove('sel');
          } else if (selected.length < max) {
            selected.push(id);
            b.classList.add('sel');
          }
          body.querySelector('[data-count]').textContent = t('app_chosen', selected.length + '/' + max);
          body.querySelector('[data-confirm]').disabled = selected.length < min;
        };
      });
      if (multi) {
        body.querySelector('[data-confirm]').onclick = function() {
          body.querySelectorAll('.opt, .look-tool').forEach(function(x) {
            x.disabled = true;
          });
          this.disabled = true;
          run.events.push(by({n: run.node, pick: selected.slice()}));
          gather(body);
          selected.forEach(function(id) {
            (pick(id).effects || []).forEach(function(e) {
              run.extra += e.extraquestion || 0;
              if (e.flag) {
                run.flags.push(e.flag);
              }
              clue(e);
            });
            if (pick(id).correct === false) {
              mistake();
            }
          });
          // Each answer chosen sends back the suspects it leaves out (`away`).
          if (standing && multiAway) {
            var away = [];
            selected.forEach(function(id) {
              away = away.concat(pick(id).away || []);
            });
            lineup(n, function(o) {
              return away.indexOf(o.id) >= 0;
            });
          }
          reveal(body, selected.map(pick), function() {
            revealAfter(n, selected.map(pick), n.next);
          });
        };
      }
    }

    /**
     * Once chosen, with the place alive, the options are put away: what was chosen and what happens are said below, and
     * the box of words stays short, so that the place is seen.
     *
     * @param {Element} body
     */
    function gather(body) {
      if (!layer.classList.contains('live')) {
        return;
      }
      body.querySelectorAll('.opts, [data-count], .sniff-row, [data-confirm]').forEach(function(x) {
        x.hidden = true;
      });
    }

    /**
     * Shows what each choice brings, one by one: the consequences are the explanation.
     *
     * @param {Element} body
     * @param {Array} list
     * @param {Function} done
     */
    function reveal(body, list, done) {
      var out = body.querySelector('[data-out]');
      if (list.length > 1) {
        // Several chosen at once: the world answers for all of them in one view.
        out.innerHTML = '<div class="conseq wait" aria-hidden="true"><span></span><span></span><span></span></div>';
        setTimeout(function() {
          if (T.fx) {
            T.fx.sound('choose');
          }
          list.forEach(function(o) {
            answer(body, o);
          });
          sayAloud(list.map(function(o) {
            return tx(o.say || '');
          }));
          out.innerHTML = '<div class="conseq said"><ul class="cls-said">' + list.map(function(o) {
            // What the world says, and why, folded, when the option has both.
            return '<li><b>' + esc(tx(o.label)) + '</b> ' + esc(tx(o.say || o.feedback || '')) + (o.say && o.feedback ?
              '<details class="why-more"><summary>' + esc(t('app_why')) + '</summary><p>' + esc(tx(o.feedback)) + '</p></details>' :
              '') + '</li>';
          }).join('') + '</ul></div><div class="actions"><button class="btn btn-main" data-more>' +
            esc(t('app_continue')) + '</button></div>';
          var more = out.querySelector('[data-more]');
          more.focus();
          more.onclick = done;
          out.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }, T.adapted.still ? 0 : 300);
        return;
      }
      var i = 0;
      function step() {
        if (i >= list.length) {
          done();
          return;
        }
        var o = list[i++];
        // A moment of waiting, and then the world answers: what happens, in the voice of the place, never a referee.
        out.innerHTML = '<div class="conseq wait" aria-hidden="true"><span></span><span></span><span></span></div>';
        setTimeout(function() {
          if (T.fx) {
            T.fx.sound(o.sound || 'choose');
          }
          answer(body, o);
          var said = tx(o.say || o.feedback || '');
          sayAloud([tx(o.say || '')]);
          var why = o.say && o.feedback ? '<details class="why-more"><summary>' + esc(t('app_why')) + '</summary><p>' +
            esc(tx(o.feedback)) + '</p></details>' : '';
          out.innerHTML = '<div class="conseq said"><span class="what">' + esc(tx(o.label)) + '</span>' + esc(said) + why +
            '</div><div class="actions"><button class="btn btn-main" data-more>' + esc(i < list.length ? t('app_next') :
            t('app_continue')) + '</button></div>';
          var more = out.querySelector('[data-more]');
          more.focus();
          more.onclick = step;
          out.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }, T.adapted.still ? 0 : 300);
      }
      step();
    }

    /**
     * Sorting, decided all at once: everything on the table, each thing taken to its place, and then «Confirm»; the
     * world answers for all of them together, and what was not in its place goes back to the table for a second trip.
     * Tap a thing and then its place; a thing already placed goes back to the table with a tap.
     *
     * @param {Object} n The node.
     */
    /**
     * With «See what evidence it leaves», in the preview of the Workshop: what an answer counts for. An option, the
     * objective and whether it is right; a thing to put in a group, the objective and its group.
     *
     * @param {Object} n The node.
     * @param {Object} o The option or the thing.
     * @param {string} [group] For a thing: the group where it goes.
     * @return {string}
     */
    function evidenceTag(n, o, group) {
      if (!evidence) {
        return '';
      }
      if (n.dilemma) {
        return '<span class="ev-tag">' + esc(t('app_ev_dilemma')) + '</span>';
      }
      var objective = o.objective || n.objective;
      if (!objective) {
        return '<span class="ev-tag">' + esc(t('app_ev_none')) + '</span>';
      }
      if (group !== undefined) {
        return '<span class="ev-tag ok">' + esc(objective + ' → ' + group) + '</span>';
      }
      var ok = o.correct !== false && (o.correct === true || (o.points || 0) > 0);
      return '<span class="ev-tag ' + (ok ? 'ok' : 'no') + '">' + esc(objective) + ' ' + (ok ? '✓' : '✗') + '</span>';
    }

    function classify(n) {
      var once = n.retry === 'none';
      // With the aids of the first steps, the hard things stay out of the table.
      var items = n.items.filter(function(it) {
        return has(it.requires) && !(data.aided && it.hard);
      });
      var locked = n.items.filter(function(it) {
        return !has(it.requires);
      });
      // A table left empty by the aids (all its things are hard): nothing to place, the scene goes on.
      if (!items.length && !locked.length) {
        go(n.next);
        return;
      }
      var place = {};
      var right = {};
      var chosen = null;
      var trip = 1;
      var body = show((n.image ? stage(n.image, n.speaker) : '') + pieces(n) + '<p class="scene-prompt">' + glossify(tx(easy(n, 'prompt')), {}) +
        '</p><div data-cls></div><div data-out></div>');
      var box = body.querySelector('[data-cls]');
      hintArea(n, body);
      roleBar(n, body);
      function table() {
        return items.filter(function(it) {
          return !right[it.id] && !place[it.id];
        });
      }
      function draw(focus) {
        var left = table();
        if (chosen && left.map(function(it) {
          return it.id;
        }).indexOf(chosen) < 0) {
          chosen = null;
        }
        if (!chosen && left.length) {
          chosen = left[0].id;
        }
        var all = items.every(function(it) {
          return right[it.id] || place[it.id];
        });
        box.innerHTML = '<p class="help">' + esc(trip > 1 ? t('app_classify_again') : t('app_classify_help')) + '</p>' +
          '<div class="cls-table" role="group" aria-label="' + esc(t('app_classify_table')) + '">' + left.map(function(it) {
            return '<button type="button" class="cls-thing" data-thing="' + esc(it.id) + '" aria-pressed="' +
              (chosen === it.id) + '">' + clsPic(it) + esc(tx(it.label)) + evidenceTag(n, it, tx((n.bins.filter(function(b) {
                return b.id === it.bin;
              })[0] || {}).label || it.bin)) + '</button>';
          }).join('') + locked.map(function(it) {
            return '<button type="button" class="cls-thing locked" disabled>' + esc(tx(it.label)) + '<small>' +
              esc(it.locked ? tx(it.locked) : missing(it.requires)) + '</small></button>';
          }).join('') + '</div>' + lookArea() + '<div class="bins' + (n.mode === 'venn' ? ' venn' : '') + '">' + n.bins.map(function(b) {
            var inside = items.filter(function(it) {
              return (right[it.id] || place[it.id]) === b.id;
            });
            return '<div class="cls-bin' + (n.mode === 'venn' ? ' zone-' + esc(b.id) : '') + '"><button type="button" ' +
              'class="bin" data-bin="' + esc(b.id) + '"' +
              (chosen ? '' : ' disabled') + '>' + esc(tx(b.label)) + '</button><span class="cls-in">' +
              inside.map(function(it) {
                return right[it.id] ? '<span class="cls-thing kept">' + esc(tx(it.label)) + '</span>' :
                  '<button type="button" class="cls-thing placed" data-take="' + esc(it.id) + '" aria-label="' +
                  esc(t('app_classify_take', tx(it.label))) + '">' + clsPic(it) + esc(tx(it.label)) + '</button>';
              }).join('') + '</span></div>';
          }).join('') + '</div><div class="actions"><button class="btn btn-main" data-confirm' + (all ? '' : ' disabled') +
          '>' + esc(n.commit ? tx(n.commit) : t('app_confirm')) + '</button></div>';
        box.querySelectorAll('[data-thing]').forEach(function(b) {
          b.onclick = function() {
            chosen = b.dataset.thing;
            draw('[data-thing="' + chosen + '"]');
          };
        });
        box.querySelectorAll('[data-take]').forEach(function(b) {
          b.onclick = function() {
            delete place[b.dataset.take];
            chosen = b.dataset.take;
            draw('[data-thing="' + chosen + '"]');
          };
        });
        box.querySelectorAll('[data-bin]').forEach(function(b) {
          b.onclick = function() {
            if (!chosen) {
              return;
            }
            place[chosen] = b.dataset.bin;
            chosen = null;
            draw('[data-bin="' + b.dataset.bin + '"]');
          };
        });
        box.querySelector('[data-confirm]').onclick = commit;
        var lookb = box.querySelector('[data-look]');
        if (lookb) {
          lookb.onclick = function() {
            var thing = items.filter(function(it) {
              return it.id === chosen;
            })[0];
            doLook(thing, function() {
              draw('[data-thing="' + chosen + '"]');
            });
          };
        }
        var again = focus && box.querySelector(focus);
        if (again && !again.disabled) {
          again.focus();
        } else if (all) {
          box.querySelector('[data-confirm]').focus();
        }
      }
      // The tool for the thing in hand, if it can be looked at, and what was seen of the things of the table.
      function lookArea() {
        var thing = chosen && items.filter(function(it) {
          return it.id === chosen;
        })[0];
        var look = thing && lookOf(thing);
        var html = look && !looked(thing.id) ? '<div class="cls-look"><button type="button" class="btn btn-soft" data-look>' +
          T.icon(look.tool) + ' ' + esc(lookLabel(look, tx(thing.label))) + '</button></div>' : '';
        return html + items.filter(function(it) {
          return looked(it.id) && lookOf(it);
        }).map(function(it) {
          return lookSaid(lookOf(it), tx(it.label));
        }).join('');
      }
      function commit() {
        var moved = items.filter(function(it) {
          return !right[it.id];
        });
        var put = {};
        moved.forEach(function(it) {
          put[it.id] = place[it.id];
          run.events.push(by({n: run.node, item: it.id, bin: place[it.id]}));
        });
        if (moved.some(function(it) {
          return put[it.id] !== it.bin;
        })) {
          mistake();
        }
        moved.forEach(function(it) {
          if (put[it.id] === it.bin || once) {
            right[it.id] = put[it.id];
          }
          delete place[it.id];
        });
        // The world answers for all of them at once, in the voice of the place.
        if (T.fx) {
          T.fx.sound('choose');
        }
        var out = body.querySelector('[data-out]');
        // What is in its place, in one line (unless it has something of its own to say); what is not, with what the world
        // says of it, or else the general clue once at the end.
        var good = moved.filter(function(it) {
          return put[it.id] === it.bin;
        });
        var plain = good.filter(function(it) {
          return !it.ok;
        });
        var lines = (plain.length ? '<li><b>' + esc(t('app_classify_inplace')) + '</b> ' + esc(plain.map(function(it) {
          return tx(it.label);
        }).join(', ')) + '.</li>' : '') + good.filter(function(it) {
          return it.ok;
        }).map(function(it) {
          return '<li><b>' + esc(tx(it.label)) + '</b> ' + esc(tx(it.ok)) + '</li>';
        }).join('');
        var general = false;
        moved.forEach(function(it) {
          if (put[it.id] !== it.bin) {
            // Its own line for the group where it went, or the why of the thing, whatever the group.
            var own = it.feedback && it.feedback[put[it.id]] || it.why;
            general = general || !own;
            lines += '<li><b>' + esc(tx(it.label)) + '</b> ' + esc(own ? tx(own) : t('app_classify_back')) + '</li>';
          }
        });
        var finished = items.every(function(it) {
          return right[it.id];
        });
        box.innerHTML = '';
        out.innerHTML = '<div class="conseq said"><ul class="cls-said">' + lines + '</ul>' + (general ? '<p>' +
          esc(n.clue ? tx(n.clue) : t('app_classify_clue')) + '</p>' : '') + '</div><div class="actions">' +
          '<button class="btn btn-main" data-more>' + esc(finished ? t('app_continue') : t('app_classify_retry')) +
          '</button></div>';
        var more = out.querySelector('[data-more]');
        more.focus();
        more.onclick = function() {
          out.innerHTML = '';
          if (finished) {
            go(n.next);
            return;
          }
          trip++;
          draw();
        };
        out.scrollIntoView({behavior: 'smooth', block: 'nearest'});
      }
      draw();
    }

    function order(n) {
      var built = [];
      var pool = n.steps.slice().sort(function() {
        return Math.random() - 0.5;
      });
      function draw() {
        if (built.length === n.steps.length) {
          go(n.next);
          return;
        }
        var body = show(pieces(n) + '<p class="scene-prompt">' + glossify(tx(easy(n, 'prompt')), {}) + '</p><ol class="built">' +
          built.map(function(s) {
          return '<li>' + esc(tx(s.label)) + '</li>';
        }).join('') + '</ol><div class="opts">' + pool.filter(function(s) {
          return built.indexOf(s) < 0;
        }).map(function(s) {
          return '<button class="opt" type="button" data-id="' + esc(s.id) + '">' + esc(tx(s.label)) + '</button>';
        }).join('') + '</div><div data-out></div>');
        hintArea(n, body);
        roleBar(n, body);
        body.querySelectorAll('.opt').forEach(function(b) {
          b.onclick = function() {
            var s = n.steps.filter(function(x) {
              return x.id === b.dataset.id;
            })[0];
            var expected = n.steps[built.length];
            run.events.push(by({n: run.node, step: s.id}));
            if (s === expected) {
              built.push(s);
              draw();
            } else {
              var stuck = n.stuck || {};
              var msg = stuck[s.id + '-antes-de-' + expected.id] || stuck.default || t('app_stuck');
              body.querySelector('[data-out]').innerHTML = '<div class="conseq said">' + esc(tx(msg)) + '</div>';
              if (T.fx) {
                T.fx.sound('choose');
              }
              // Not this step yet: it stays off until the right one is in place.
              b.disabled = true;
              mistake();
              b.classList.remove('shake');
              void b.offsetWidth;
              b.classList.add('shake');
            }
          };
        });
      }
      draw();
    }

    /**
     * What was not known when deciding, told after the consequences (`reveal_after`, with `reveal_image` and
     * `reveal_speaker`): and how each option picked looks now (`after`). Then on to where the decision leads.
     *
     * @param {Object} n The node.
     * @param {Object[]} picked The options picked.
     * @param {string} target
     */
    function revealAfter(n, picked, target) {
      if (!n.reveal_after) {
        go(target);
        return;
      }
      var text = [tx(n.reveal_after)].concat(picked.map(function(o) {
        return o.after ? tx(o.after) : '';
      })).filter(Boolean).join(' ');
      story({type: 'story', text: text, image: n.reveal_image, speaker: n.reveal_speaker, next: target});
    }

    /**
     * A choice told as a card to fill in (`layout: card`): a blank card with as many gaps as pieces go in, and the
     * pieces below. A piece is taken to the card by dragging it or with a touch (or Enter); touched in the card, it goes
     * back. With the card full, it is handed in, and the world answers for each piece as in any choice of several.
     *
     * @param {Object} n The node.
     * @param {number} min
     * @param {number} max
     */
    function cardChoice(n, min, max) {
      var placed = [];
      function pick(id) {
        return n.options.filter(function(o) {
          return o.id === id;
        })[0];
      }
      var usable = n.options.filter(function(o) {
        return has(o.requires);
      });
      function draw() {
        var gaps = [];
        for (var i = 0; i < max; i++) {
          var o = placed[i] ? pick(placed[i]) : null;
          gaps.push(o ? '<button type="button" class="card-gap full" data-back="' + esc(o.id) + '" aria-label="' +
            esc(t('app_card_back', tx(o.label))) + '">' + esc(tx(o.label)) + '</button>' :
            '<span class="card-gap" aria-hidden="true"></span>');
        }
        var body = show((n.image ? picture(n.image, data) : '') + pieces(n) + '<p class="scene-prompt">' + glossify(tx(easy(n, 'prompt')), {}) +
          '</p><div class="fill-card" data-card><div class="fill-card-title">' + esc(tx(n.cardtitle || '')) + '</div>' +
          gaps.join('') + '</div><div class="card-pieces">' + usable.filter(function(o) {
            return placed.indexOf(o.id) < 0;
          }).map(function(o) {
            return '<button type="button" class="chip card-piece" data-piece="' + esc(o.id) + '">' + esc(tx(o.label)) +
              '</button>';
          }).join('') + '</div><div class="actions"><button class="btn btn-main" data-confirm' +
          (placed.length < min ? ' disabled' : '') + '>' + esc(t('app_card_hand')) + '</button></div><div data-out></div>');
        hintArea(n, body);
        roleBar(n, body);
        var card = body.querySelector('[data-card]');
        var put = function(id) {
          if (placed.length < max && placed.indexOf(id) < 0) {
            placed.push(id);
            if (T.fx) {
              T.fx.sound('tap');
            }
            draw();
          }
        };
        body.querySelectorAll('[data-piece]').forEach(function(b) {
          b.onclick = function() {
            put(b.dataset.piece);
          };
          // Dragged with a finger or the mouse: let go over the card and it goes in.
          b.onpointerdown = function(ev) {
            var ghost = null;
            var moved = false;
            var sx = ev.clientX;
            var sy = ev.clientY;
            var move = function(e) {
              if (!moved && Math.abs(e.clientX - sx) + Math.abs(e.clientY - sy) < 8) {
                return;
              }
              if (!ghost) {
                moved = true;
                ghost = b.cloneNode(true);
                ghost.classList.add('dragging');
                document.body.appendChild(ghost);
              }
              ghost.style.left = e.clientX + 'px';
              ghost.style.top = e.clientY + 'px';
              var r = card.getBoundingClientRect();
              card.classList.toggle('over', e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top &&
                e.clientY <= r.bottom);
            };
            var up = function(e) {
              document.removeEventListener('pointermove', move);
              document.removeEventListener('pointerup', up);
              if (ghost) {
                ghost.remove();
              }
              var r = card.getBoundingClientRect();
              card.classList.remove('over');
              if (moved && e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom) {
                put(b.dataset.piece);
              }
              if (moved) {
                // A drag is not a touch as well.
                b.onclick = null;
                setTimeout(function() {
                  b.onclick = function() {
                    put(b.dataset.piece);
                  };
                }, 0);
              }
            };
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
          };
        });
        body.querySelectorAll('[data-back]').forEach(function(b) {
          b.onclick = function() {
            placed.splice(placed.indexOf(b.dataset.back), 1);
            draw();
          };
        });
        body.querySelector('[data-confirm]').onclick = function() {
          body.querySelectorAll('button').forEach(function(x) {
            x.disabled = true;
          });
          run.events.push(by({n: run.node, pick: placed.slice()}));
          placed.forEach(function(id) {
            (pick(id).effects || []).forEach(function(e) {
              run.extra += e.extraquestion || 0;
              if (e.flag) {
                run.flags.push(e.flag);
              }
              clue(e);
            });
            if (pick(id).correct === false) {
              mistake();
            }
          });
          reveal(body, placed.map(pick), function() {
            revealAfter(n, placed.map(pick), n.next);
          });
        };
      }
      draw();
    }

    /**
     * A use or a justify, as the choice it is, as the server sees it: the things of the backpack (each option with its
     * `item`, there only if it is carried), or the reasons for what was picked in the decision it is about (those with
     * `for`, only if one of those was picked), two to mark by default, with what was picked said above.
     *
     * @param {Object} n The node.
     * @return {Object} The node, as a choice.
     */
    function asChoice(n) {
      var c = Object.assign({}, n);
      if (n.type === 'use') {
        c.options = (n.options || []).map(function(o) {
          return o.item && !o.requires ? Object.assign({}, o, {requires: {item: o.item}}) : o;
        });
        return c;
      }
      var picked = [];
      run.events.forEach(function(e) {
        if (e.n === n.about && e.pick) {
          picked = e.pick;
        }
      });
      c.options = (n.options || []).filter(function(o) {
        return !o.for || [].concat(o.for).some(function(id) {
          return picked.indexOf(id) >= 0;
        });
      });
      c.select = n.select || {min: 2, max: 2};
      var about = (sc.nodes[n.about] || {}).options || [];
      var said = about.filter(function(o) {
        return picked.indexOf(o.id) >= 0;
      }).map(function(o) {
        return tx(o.label);
      });
      c.recall = said.length ? t('app_justify_about', said.join(' · ')) : '';
      return c;
    }

    /**
     * Whether what is played on a picture (a find, a path, what is tended) goes on the painting of the place alive:
     * while the place is there, the picture has its painting, and it has not had to go back to the page.
     *
     * @param {string} image
     * @return {boolean}
     */
    function onPainting(image) {
      return layer.classList.contains('live') && !!T.artUrl(pictureKeys(image, data)) && !(search && search.page);
    }

    /**
     * Puts what is played on a picture (the .on-painting of the moment, if it has one) on the painting of the place
     * alive, which the world shows whole above what is read; the first time, it asks the world for it. If the painting
     * does not come, the moment is drawn again on the page.
     *
     * @param {string} image
     * @param {Function} redraw Draws the moment again as it is now.
     */
    function overPainting(image, redraw) {
      // Out of the moment, which comes in moving: on the layer, where the painting is in the page.
      var mine = layer.querySelector('.scene-body .on-painting');
      if (mine) {
        layer.appendChild(mine);
      }
      var place = function() {
        var over = layer.querySelector(':scope > .on-painting');
        var box = search && search.box;
        if (!over || !box) {
          return;
        }
        over.style.left = box.left + 'px';
        over.style.top = box.top + 'px';
        over.style.width = box.width + 'px';
        over.style.height = box.height + 'px';
        over.hidden = false;
      };
      if (!search) {
        search = {box: null, redraw: null, page: false, wait: setTimeout(function() {
          if (search && !search.box) {
            search.page = true;
            alive('search', false);
            search.redraw();
          }
        }, 4000)};
        alive('image', T.artUrl(pictureKeys(image, data)));
        alive('search', true, function(box) {
          if (search) {
            search.box = box;
            place();
          }
        }, layer.querySelector('.scene-top').getBoundingClientRect().bottom);
      }
      search.redraw = redraw;
      place();
    }

    /**
     * A find: things to find on a picture, touched one by one. What is one of them stays marked, with what it says; what
     * is not says why, once. Touching where there is nothing says so, without counting. For who does not see the
     * picture, each thing is a button with its name. With the place alive behind, they are looked for on the painting of
     * the place itself, seen whole above what is read, and the world answers each touch; if the painting does not come,
     * on the page.
     *
     * @param {Object} n The node: image, spots (id, label, x, y, r in percent; correct; say, feedback), find.
     */
    function find(n) {
      var spots = n.spots || [];
      var need = Math.min(spots.filter(function(s) {
        return s.correct;
      }).length, n.find || spots.length);
      var found = [];
      var wrong = [];
      var image = n.image || sceneImage();
      function spot(id) {
        return spots.filter(function(s) {
          return s.id === id;
        })[0];
      }
      function draw(said, done) {
        var world = onPainting(image);
        var marks = spots.map(function(s) {
          var got = found.indexOf(s.id) >= 0;
          var miss = wrong.indexOf(s.id) >= 0;
          var r = Number(s.r || 7);
          // The name of what was found stays inside the picture, near the edges too.
          var edge = Number(s.x) > 70 ? ' edge-r' : Number(s.x) < 30 ? ' edge-l' : '';
          return '<button type="button" class="find-spot' + edge + (got ? ' found' : '') + (miss ? ' miss' : '') + '" data-id="' +
            esc(s.id) + '" style="left:' + Number(s.x) + '%;top:' + Number(s.y) + '%;width:' + 2 * r + '%" aria-label="' +
            esc(tx(s.label)) + '"' + (got || miss || done ? ' disabled' : '') + '>' + (got ? '<span>' + esc(tx(s.label)) +
            '</span>' : '') + '</button>';
        }).join('');
        var body = show(pieces(n) + '<p class="scene-prompt">' + glossify(tx(easy(n, 'prompt')), {}) + '</p><p class="find-count">' +
          esc(t('app_find_count', found.length + '/' + need)) + '</p>' + (world ? '<div class="on-painting find-on" hidden>' + marks +
          '</div>' : '<div class="find-map">' + picture(image, data) + marks + '</div>') + '<div data-out>' + (said || '') +
          (done ? '<div class="actions"><button class="btn btn-main" data-go>' + esc(t('app_continue')) + '</button></div>' :
          '') + '</div>');
        hintArea(n, body);
        roleBar(n, body);
        var map = body.querySelector(world ? '.on-painting' : '.find-map');
        if (world) {
          overPainting(image, function() {
            draw(said, done);
          });
        } else {
          var img = map.querySelector('img');
          var fit = function() {
            if (img.naturalWidth) {
              map.style.aspectRatio = img.naturalWidth + ' / ' + img.naturalHeight;
            }
          };
          if (img) {
            if (img.complete) {
              fit();
            } else {
              img.onload = fit;
            }
          }
        }
        if (done) {
          body.querySelector('[data-go]').onclick = function() {
            go(n.next);
          };
          return;
        }
        // Where there is nothing.
        map.onclick = function(ev) {
          if (ev.target.closest('.find-spot')) {
            return;
          }
          var out = body.querySelector('[data-out]');
          out.innerHTML = '<div class="conseq said">' + esc(t('app_find_nothing')) + '</div>';
          if (world) {
            var r = map.getBoundingClientRect();
            alive('mark', 100 * (ev.clientX - r.left) / r.width, 100 * (ev.clientY - r.top) / r.height, false);
          }
        };
        map.querySelectorAll('.find-spot:not([disabled])').forEach(function(b) {
          b.onclick = function() {
            var s = spot(b.dataset.id);
            run.events.push(by({n: run.node, spot: s.id}));
            // What the world says, and why, folded, as with an option.
            var words = '<span class="what">' + esc(tx(s.label)) + '</span>' + esc(tx(s.say || s.feedback || '') ||
              t('app_find_not')) + (s.say && s.feedback ? '<details class="why-more"><summary>' + esc(t('app_why')) +
              '</summary><p>' + esc(tx(s.feedback)) + '</p></details>' : '');
            alive('mark', Number(s.x), Number(s.y), !!s.correct);
            if (s.correct) {
              found.push(s.id);
              (s.effects || []).forEach(clue);
              if (T.fx) {
                T.fx.sound('tap');
              }
              draw('<div class="conseq said">' + words + '</div>', found.length >= need);
              return;
            }
            wrong.push(s.id);
            mistake();
            if (T.fx) {
              T.fx.sound('choose');
            }
            draw('<div class="conseq said">' + words + '</div>');
          };
        });
      }
      draw();
    }

    /**
     * A path: someone (a drop, an animal) waits on a picture and the student touches the place it goes on to, one after
     * the other; the right one takes it there, a wrong one makes it slip back with what it says. It is an order, told
     * as a journey.
     *
     * @param {Object} n The node: steps with id, label, x and y (in percent of the picture), traveler, from.
     */
    function path(n) {
      var done = [];
      var from = n.from || {x: 50, y: 92};
      var image = n.image || sceneImage();
      function draw(said) {
        var at = done.length ? done[done.length - 1] : from;
        // With the place alive, the journey is on its painting; else, on a picture in the page.
        var world = onPainting(image);
        var body = show(pieces(n) + '<p class="scene-prompt">' + glossify(tx(easy(n, 'prompt')), {}) + '</p>' + (world ?
          '<div class="on-painting path-on" hidden>' : '<div class="path-map">' + picture(image, data)) + n.steps.map(function(s, i) {
            var visited = done.indexOf(s) >= 0;
            return '<button type="button" class="path-stop' + (visited ? ' visited' : '') + '" data-id="' + esc(s.id) + '" ' +
              'style="left:' + Number(s.x) + '%;top:' + Number(s.y) + '%"' + (visited ? ' disabled' : '') + '>' +
              (visited ? '<b>' + (done.indexOf(s) + 1) + '</b>' : '') + '<span>' + esc(tx(s.label)) + '</span></button>';
          }).join('') + '<span class="path-traveler" style="left:' + Number(at.x) + '%;top:' + Number(at.y) + '%">' +
          esc(tx(n.traveler || '•')) + '</span></div><div data-out>' + (said || '') + '</div>');
        hintArea(n, body);
        roleBar(n, body);
        // Where the stops are: on the painting (out of the moment), or in the page.
        var stops = world ? body.querySelector('.on-painting') : body;
        if (world) {
          overPainting(image, function() {
            draw(said);
          });
        }
        // The map takes the proportion of its picture, so that each place stays on what it marks.
        var img = world ? null : body.querySelector('.path-map img');
        var fit = function() {
          if (img.naturalWidth) {
            body.querySelector('.path-map').style.aspectRatio = img.naturalWidth + ' / ' + img.naturalHeight;
          }
        };
        if (img) {
          if (img.complete) {
            fit();
          } else {
            img.onload = fit;
          }
        }
        stops.querySelectorAll('.path-stop:not([disabled])').forEach(function(b) {
          b.onclick = function() {
            var s = n.steps.filter(function(x) {
              return x.id === b.dataset.id;
            })[0];
            var expected = n.steps[done.length];
            run.events.push(by({n: run.node, step: s.id}));
            alive('mark', Number(s.x), Number(s.y), s === expected);
            if (s === expected) {
              done.push(s);
              if (T.fx) {
                T.fx.sound('tap');
              }
              if (done.length === n.steps.length) {
                draw();
                setTimeout(function() {
                  go(n.next);
                }, T.moves && !T.moves() ? 0 : 700);
                return;
              }
              draw();
              return;
            }
            var stuck = n.stuck || {};
            var msg = stuck[s.id + '-antes-de-' + expected.id] || stuck.default || t('app_stuck');
            mistake();
            if (T.fx) {
              T.fx.sound('choose');
            }
            draw('<div class="conseq said">' + esc(tx(msg)) + '</div>');
            var traveler = layer.querySelector('.path-traveler');
            if (traveler) {
              traveler.classList.add('slip');
            }
          };
        });
      }
      draw();
    }

    /**
     * Tending a being: the student gives it some of the things at hand and days go by; what they gave makes a state of
     * the being (the first whose condition holds, as the server counts it). They may try another round, changing
     * something, up to the rounds of the node, or go on.
     *
     * @param {Object} n The node: items, give, rounds, states (when, correct, text, image, feedback, next).
     */
    function tend(n) {
      var give = n.give || 1;
      var rounds = n.rounds || 1;
      var round = 0;
      var chosen = [];
      var last = null;
      function state(given) {
        return (n.states || []).filter(function(st) {
          var w = st.when || {};
          var all = (w.all || []).every(function(id) {
            return given.indexOf(id) >= 0;
          });
          var any = !(w.any || []).length || (w.any || []).some(function(id) {
            return given.indexOf(id) >= 0;
          });
          var none = !(w.none || []).some(function(id) {
            return given.indexOf(id) >= 0;
          });
          return all && any && none;
        })[0] || null;
      }
      function draw() {
        var items = (n.items || []).filter(function(it) {
          return has(it.requires);
        });
        // How the being looks now: with the place alive, on its painting, seen whole above what is read; else, in the page.
        var looks = last ? last.image : n.image;
        var world = !!looks && onPainting(looks);
        var shown = last ? '<div class="tend-state' + (last.correct ? ' good' : '') + '">' + (last.image && !world ?
          picture(last.image, data) : '') + '<p>' + esc(tx(last.text || '')) + '</p>' + (last.feedback ? '<small>' +
          esc(tx(last.feedback)) + '</small>' : '') + '</div>' : (n.image && !world ? picture(n.image, data) : '');
        var more = last && !last.correct && round < rounds;
        var body = show(pieces(n) + shown + '<p class="scene-prompt">' + glossify(tx(easy(n, 'prompt')), {}) + '</p>' + (last && (last.correct ||
          !more) ? '' : '<div class="opts tend-items">' + items.map(function(it) {
          var on = chosen.indexOf(it.id) >= 0;
          return '<button type="button" class="opt' + (on ? ' sel' : '') + '" data-id="' + esc(it.id) + '" aria-pressed="' +
            on + '">' + esc(tx(it.label)) + '</button>';
        }).join('') + '</div><div class="counter">' + esc(t('app_chosen', chosen.length + '/' + give)) + '</div>') +
          '<div class="actions">' + (last && (last.correct || !more) ? '<button class="btn btn-main" data-goon>' +
          esc(t('app_next')) + '</button>' : '<button class="btn btn-main" data-days' + (chosen.length === give ? '' :
          ' disabled') + '>' + esc(tx(n.days || t('app_tend_days'))) + '</button>' + (more ? '<button class="btn btn-soft" ' +
          'data-goon>' + esc(t('app_tend_goon')) + '</button>' : '')) + '</div>');
        hintArea(n, body);
        roleBar(n, body);
        if (world) {
          overPainting(looks, function() {
            draw();
          });
          alive('image', T.artUrl(pictureKeys(looks, data)));
        }
        body.querySelectorAll('.tend-items .opt').forEach(function(b) {
          b.onclick = function() {
            var id = b.dataset.id;
            var at = chosen.indexOf(id);
            if (at >= 0) {
              chosen.splice(at, 1);
            } else if (chosen.length < give) {
              chosen.push(id);
            }
            draw();
          };
        });
        var days = body.querySelector('[data-days]');
        if (days) {
          days.onclick = function() {
            run.events.push(by({n: run.node, give: chosen.slice()}));
            last = state(chosen) || {text: '', correct: false};
            round++;
            if (!last.correct) {
              mistake();
            }
            if (T.fx) {
              T.fx.sound(last.correct ? 'item' : 'choose');
            }
            // The world answers how the being is: it grows, or it is looked at worried.
            alive('answer', last.correct ? 'grow' : '');
            chosen = [];
            draw();
          };
        }
        var goon = body.querySelector('[data-goon]');
        if (goon) {
          goon.onclick = function() {
            go(last && last.correct ? last.next || n.next : n.next);
          };
        }
      }
      draw();
    }

    /**
     * The phones of the class vote a decision: the class screen shows how many have voted and, once closed, the votes of
     * each option. The teacher then taps the option the class chose.
     *
     * @param {Element} body
     * @param {Object} n The node.
     * @param {Array} open The options that can be chosen.
     */
    function classVote(body, n, open) {
      var box = body.querySelector('[data-vote]');
      var timer = 0;
      // The button to close the vote is drawn once; each poll only changes the count beside it, so that a tap on it is
      // never lost while it is drawn again. Once closed, a poll that was on its way changes nothing.
      var closed = false;
      function count(live) {
        var label = box.querySelector('[data-votecount]');
        var text = t('board_scene_votes', {answered: live.answered, joined: live.joined});
        if (label) {
          label.textContent = text;
          return;
        }
        box.innerHTML = '<span data-votecount>' + esc(text) + '</span>' +
          '<button class="btn btn-main" data-voteclose>' + esc(t('board_scene_voteclose')) + '</button>';
        box.querySelector('[data-voteclose]').onclick = function() {
          closed = true;
          this.disabled = true;
          clearTimeout(timer);
          cm.vote.close().then(function(live) {
            tally(live);
            poll();
          });
        };
      }
      var round = 0;
      // The vote is followed while the decision is on the screen: the count, the vote closed from the teacher's phone,
      // and the option they chose there, which is played here as if it were touched here.
      function poll() {
        timer = setTimeout(function() {
          if (!document.body.contains(box)) {
            return;
          }
          cm.vote.state().then(function(live) {
            if (!live || live.round !== round) {
              return;
            }
            if (live.pick !== undefined && live.pick >= 0 && open[live.pick]) {
              var b = body.querySelector('.opt[data-id="' + open[live.pick].id + '"]');
              if (b && !b.disabled) {
                b.click();
              }
              return;
            }
            if (live.open && !closed) {
              count(live);
            } else if (!live.open && !closed) {
              closed = true;
              tally(live);
            }
            poll();
          });
        }, 1500);
      }
      function tally(live) {
        var votes = (live && live.tally) || [];
        var best = Math.max.apply(null, votes.concat([0]));
        box.innerHTML = '<span>' + esc(t('board_scene_voted')) + '</span>';
        open.forEach(function(o, i) {
          var b = body.querySelector('.opt[data-id="' + o.id + '"]');
          if (b) {
            b.querySelector('[data-votes]').textContent = t('board_scene_optvotes', votes[i] || 0);
            if (best > 0 && votes[i] === best) {
              b.classList.add('voted');
            }
          }
        });
      }
      box.querySelector('[data-voteopen]').onclick = function() {
        this.disabled = true;
        // The node and its options go with the vote: what the class votes wrong counts in what confuses it.
        cm.vote.open(tx(easy(n, 'prompt')), open.map(function(o) {
          return tx(o.label);
        }), {node: run.node, ids: open.map(function(o) {
          return o.id;
        })}).then(function(live) {
          round = live ? live.round : 0;
          count(live);
          poll();
        }).catch(function(e) {
          T.toast(esc(e.message), true);
        });
      };
    }

    /**
     * A question to think about when a scene is over, «what if…?»: alone, with the team, or with the whole class.
     *
     * @param {boolean} ok The scene was done (a good ending or halfway).
     * @param {boolean} [folded] Under its title, to open.
     * @return {string}
     */
    function whatif(ok, folded) {
      if (!ok || !sc || !sc.whatif) {
        return '';
      }
      var inside = '<p>' + esc(tx(sc.whatif)) + '</p><small>' + esc(t(cm ? 'board_whatif_help' : opts.team &&
        opts.team.length ? 'app_whatif_team' : 'app_whatif_help')) + '</small>';
      // After what was won, folded: one more thing to think about, for who wants it.
      return folded ? '<details class="whatif"><summary class="what">' + esc(t('app_whatif')) + '</summary>' + inside +
        '</details>' : '<div class="whatif"><span class="what">' + esc(t('app_whatif')) + '</span>' + inside + '</div>';
    }

    function classEnding(n) {
      show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_saving')) + '</div>');
      Promise.all([cm.finish(run.events, run.from), drumroll()]).then(function(answers) {
        var result = answers[0];
        var success = result.outcome !== 'retry';
        celebrate(result.outcome, null);
        var body = show(picture(n.image || sceneImage(), data) + '<p class="scene-text">' + glossify(tx(easy(n, 'text')), {}) + '</p>' + (success ?
          '<div class="conseq good"><span class="what">' + esc(t('board_scene_rewarded', result.rewarded)) + '</span></div>' :
          '') + better(result) + whatif(success) + '<div class="actions">' + (success ? '<button class="btn btn-main" ' +
          'data-focus data-map>' +
          esc(t('board_battle_back')) + '</button>' + replay(result) : '<button class="btn btn-main" data-focus data-retry>' +
          esc(t('app_tryagain')) + '</button>') + '</div>');
        onReplay(body);
        var map = body.querySelector('[data-map]');
        if (map) {
          map.onclick = close;
        } else {
          body.querySelector('[data-retry]').onclick = function() {
            start(result.retry || n.retry);
          };
        }
      }).catch(function(e) {
        var body = show('<p class="scene-text">' + esc(t('app_error')) + '</p><p class="help">' + esc(e.message) + '</p>' +
          '<div class="actions"><button class="btn btn-main" data-again>' + esc(t('app_retry')) + '</button></div>');
        body.querySelector('[data-again]').onclick = function() {
          classEnding(n);
        };
      });
    }

    /**
     * A partial ending: it counts, and playing the scene again can make it better.
     *
     * @param {Object} result
     * @return {string}
     */
    function better(result) {
      return result.outcome === 'partial' ? '<p class="help scene-better">' + esc(t('app_scene_partial')) + '</p>' : '';
    }

    function replay(result) {
      return result.outcome === 'partial' ? '<button class="btn btn-soft" data-replay>' + esc(t('app_scene_replay')) +
        '</button>' : '';
    }

    function onReplay(body) {
      var again = body.querySelector('[data-replay]');
      if (again) {
        again.onclick = function() {
          start();
        };
      }
    }

    /**
     * The Embrollo of a place comes out when the student lights its lantern: the screen trembles, it says its letter,
     * the student answers it with the idea that undoes its knot (or agrees), and it runs off leaving ink either way. It
     * counts for nothing: it is the villain, in person, once a place. In the story of Azara, a note in tangled writing.
     *
     * @param {Object} e name, claim, laugh, weakness, letter.
     * @param {string} skin
     */
    function embrolloMoment(e, skin) {
      var note = skin === 'azara';
      var layer = document.createElement('div');
      layer.className = 'badge-moment embrollo-moment' + (note ? ' note' : '') + (T.moves() ? '' : ' still');
      layer.setAttribute('role', 'dialog');
      layer.setAttribute('aria-modal', 'true');
      layer.setAttribute('aria-labelledby', 'tesape-embrollo-title');
      var face = note ? '<span class="embrollo-note" aria-hidden="true">' + T.icon('log') + '</span>' :
        '<span class="embrollo-face" aria-hidden="true">' + T.embrollo(1) + '</span>';
      var replies = [['right', e.weakness], ['agree', t(note ? 'app_note_agree' : 'app_embrollo_agree')]];
      if (Math.random() < 0.5) {
        replies.reverse();
      }
      layer.innerHTML = '<div class="badge-card">' + face + '<div class="kicker" id="tesape-embrollo-title">' +
        esc(t(note ? 'app_note_kicker' : 'app_embrollo_kicker')) + '</div>' + (e.letter ? '<blockquote class="letter">' +
        esc(e.letter) + '</blockquote>' : '') + '<p class="embrollo-claim">«' + esc(e.claim.replace(/^«|»$/g, '')) + '»</p>' +
        '<p><b>' + esc(t('app_embrollo_reply')) + '</b></p><div class="actions embrollo-replies">' + replies.map(function(r) {
          return '<button type="button" class="btn btn-soft" data-reply="' + r[0] + '">' + esc(r[1]) + '</button>';
        }).join('') + '</div><div data-fled></div></div>';
      document.body.appendChild(layer);
      if (T.fx) {
        T.fx.sound('wrong');
      }
      layer.querySelector('[data-reply]').focus();
      layer.querySelectorAll('[data-reply]').forEach(function(b) {
        b.onclick = function() {
          var right = b.dataset.reply === 'right';
          layer.querySelectorAll('[data-reply]').forEach(function(x) {
            x.disabled = true;
          });
          b.classList.add('chosen');
          layer.classList.add('fled');
          if (T.fx) {
            T.fx.sound(right ? 'right' : 'tap');
          }
          var fled = layer.querySelector('[data-fled]');
          fled.innerHTML = (right || !e.laugh ? '' : '<p class="knot-laugh">«' + esc(e.laugh.replace(/^«|»$/g, '')) + '»</p>') +
            '<p class="embrollo-ink">' + esc(t(note ? (right ? 'app_note_fled_right' : 'app_note_fled') :
            (right ? 'app_embrollo_fled_right' : 'app_embrollo_fled'))) + '</p><div class="actions"><button type="button" ' +
            'class="btn btn-main" data-close>' + esc(t('app_embrollo_go')) + '</button></div>';
          var close = fled.querySelector('[data-close]');
          close.onclick = function() {
            layer.remove();
          };
          close.focus();
        };
      });
    }

    function ending(n) {
      if (cm) {
        classEnding(n);
        return;
      }
      if (opts.preview) {
        // A teacher trying a scene of their mission: the ending, and nothing is kept.
        var outcome = t(n.outcome === 'success' ? 'app_preview_success' : n.outcome === 'partial' ? 'app_preview_partial' :
          'app_preview_retry');
        var end = show('<p class="scene-text">' + esc(tx(n.text || '')) + '</p><p class="help">' + esc(outcome) + '</p>' +
          whatif(n.outcome !== 'retry') + '<div class="actions"><button class="btn btn-soft" data-replay>' +
          esc(t('app_playagain')) + '</button>' +
          '<button class="btn btn-main" data-focus data-previewclose>' + esc(t('app_close')) + '</button></div>');
        end.querySelector('[data-previewclose]').onclick = close;
        onReplay(end);
        celebrate(n.outcome, null);
        return;
      }
      // The same mark if it is sent again from here: it counts once.
      run.playid = run.playid || T.playId();
      saving = true;
      show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_saving')) + '</div>');
      Promise.all([T.api('play_scene', {courseid: cfg.courseid, sceneid: data.id, events: JSON.stringify(run.events),
        start: run.from, team: opts.team && opts.team.length ? JSON.stringify(opts.team) : '', playid: run.playid}),
        drumroll()])
      .then(function(answers) {
        saving = false;
        var result = answers[0];
        var before = world.character;
        world = result.world;
        var r = result.rewards;
        var lines = [];
        if (r.saber) {
          lines.push('+' + r.saber + ' ' + t('app_saber'));
        }
        if (r.capped) {
          lines.push(t('app_capped', r.capped));
        }
        if (r.gems) {
          lines.push(t(r.gems === 1 ? 'app_encounter_gem' : 'app_encounter_gems', r.gems));
        }
        r.items.forEach(function(name) {
          lines.push(t('app_gotitem', name));
        });
        r.pages.forEach(function(name) {
          lines.push(t('app_gotpage', name));
        });
        r.lanterns.forEach(function(name) {
          lines.push(t('app_gotlantern', name));
        });
        if (r.classlantern) {
          lines.push(t('app_classlantern', r.classlantern));
        }
        if (r.grew) {
          lines.push(t('app_creaturegrew', creatureName()));
        }
        if (r.cared) {
          lines.push(t('app_cared', r.cared));
        }
        if (r.rescued && r.rescued.length) {
          lines.push(t('app_rescued', r.rescued.join(', ')));
        }
        if (r.hints) {
          lines.push(t('app_hints_paid', r.hints));
        }
        if (world.character && world.character.member) {
          // The breath left after the hints paid, for the next play.
          data.breath = world.character.member.breath;
        }
        var success = result.outcome !== 'retry';
        var hatch = r.hatched ? '<div class="hatch" data-hatch><button type="button" class="seed" data-seed aria-label="' +
          esc(t('app_seed_touch')) + '">' + SEED + '</button><p>' + esc(t('app_seed_touch')) + '</p></div>' : '';
        var levelup = world.character.level > before.level ? '<div class="conseq good"><span class="what">' +
          esc(t('app_levelup', world.character.level)) + '</span></div>' : '';
        var done = !success ? '' : (result.attempt > 1 && !lines.length ? '<p class="help">' + esc(t('app_alreadydone')) +
          '</p>' : '');
        // Played as a team, it counted for the teammates who were there too.
        if (result.teamwith && result.teamwith.length) {
          done += '<p class="help">' + esc(t('app_team_counted', result.teamwith.join(', '))) + '</p>';
        }
        if (success) {
          // What this play left (memory between scenes) counts for the next one.
          data.flags = run.flags.slice();
        }
        // The creature reacts: it jumps when it went well; when it is to be tried again, it got ink on its nose.
        var pet = before.creature && !r.hatched ? '<div class="end-creature ' + (success ? 'happy' : 'smudged') + '">' +
          '<span class="end-pet" aria-hidden="true">' + T.creature(before.creature, world.character.creaturestage) +
          '</span><small>' + esc(t(success ? 'app_creature_happy' : 'app_creature_smudged', creatureName())) + '</small></div>' : '';
        // First how it ends, told in the box of words with its voice, in the place; then what was won, a thing at a time.
        var told = tx(easy(n, 'text'));
        var quoted = told.indexOf('«') >= 0 ? mainSpeaker() : null;
        alive('ending', result.outcome);
        if (n.lineup) {
          lineup(n, function(o) {
            return o.correct !== true;
          });
        }
        tell({text: told, image: n.image, speaker: quoted, nolabel: true}, function() {
          var body = show(pet + hatch + levelup + (lines.length ? '<div class="conseq good loot"><span class="what">' +
            esc(t('app_forcharacter')) + '</span><ul>' + lines.map(function(line, i) {
              return '<li style="--i: ' + i + '">' + esc(line) + '</li>';
            }).join('') + '</ul></div>' : '') + done + better(result) + whatif(success, true) + '<div class="actions">' +
            (success ?
            '<button class="btn btn-main" data-focus data-map>' + esc(t('app_backtomap')) + '</button>' + replay(result) :
            '<button class="btn btn-main" data-focus data-retry>' + esc(t('app_tryagain')) + '</button>') + '</div>');
          ended(body, result, r, n);
        });
      }).catch(function(e) {
        caught(e, n);
      });
    }

    /**
     * What was won, on the screen after how the scene ends: each thing comes in with its sound, the creature and the
     * seed, the Embrollo of the place if its lantern was lit, and the way back to the map or to try again.
     *
     * @param {Element} body
     * @param {Object} result What the server said.
     * @param {Object} r The rewards.
     * @param {Object} n The ending.
     */
    function ended(body, result, r, n) {
      onReplay(body);
      celebrate(result.outcome, r, true);
      hatching(body, n);
      if (T.fx && T.moves()) {
        body.querySelectorAll('.loot li').forEach(function(li, i) {
          setTimeout(function() {
            T.fx.sound('coin');
          }, 700 + i * 380);
        });
      }
      // The lantern of the place lit with this scene: the Embrollo of the place comes out.
      var place = (world.mission.chapters || []).filter(function(ch) {
        return (ch.scenes || []).some(function(x) {
          return x.id === data.id;
        });
      })[0];
      if (place && place.embrollo && r.lanterns.indexOf(place.name) >= 0 && !r.hatched) {
        setTimeout(function() {
          embrolloMoment(place.embrollo, world.mission.skin);
        }, T.moves() ? 1400 : 300);
      }
      var map = body.querySelector('[data-map]');
      if (map) {
        map.onclick = close;
      } else {
        body.querySelector('[data-retry]').onclick = function() {
          start(result.retry || n.retry);
        };
      }
    }

    /**
     * When the play of an ending could not be kept: on the device until the connection comes back, or to play again.
     *
     * @param {Error} e
     * @param {Object} n The ending.
     */
    function caught(e, n) {
      saving = false;
      if ((e.network || e.retry) && T.keepPlay({sceneid: data.id, title: sc.title || '', events: JSON.stringify(run.events),
        start: run.from || '', team: opts.team && opts.team.length ? JSON.stringify(opts.team) : '', at: Date.now(),
        playid: run.playid})) {
        // No connection: the play is kept on the device and goes by itself when the connection comes back.
        var kept = show('<p class="scene-text">' + esc(tx(n.text || '')) + '</p><div class="conseq good"><span ' +
          'class="what">' + esc(t('app_offline_kept')) + '</span>' + esc(t('app_offline_kept_help')) + '</div>' +
          whatif(n.outcome !== 'retry') + '<div class="actions"><button class="btn btn-main" data-focus data-map>' +
          esc(t('app_backtomap')) +
          '</button></div>');
        kept.querySelector('[data-map]').onclick = close;
        return;
      }
      if (!e.network && !e.retry && !e.errorcode) {
        // Not the server: the play was kept, and something failed while showing its end. Playing it again would count
        // it twice; back to the map, where it shows as done.
        window.console.error(e);
        show('<p class="scene-text">' + esc(t('app_saved')) + '</p><div class="actions"><button class="btn btn-main" ' +
          'data-focus data-map>' + esc(t('app_backtomap')) + '</button></div>').querySelector('[data-map]').onclick = close;
        return;
      }
      if (!e.network && !e.retry) {
        // The server did not take what was played: sending it again would not help, playing it again does.
        var tangled = show('<p class="scene-text">' + esc(t('app_scene_tangled')) + '</p><div class="actions">' +
          '<button class="btn btn-main" data-focus data-restart>' + esc(t('app_scene_restart')) + '</button></div>');
        tangled.querySelector('[data-restart]').onclick = function() {
          start(run.from);
        };
        return;
      }
      var body = show('<p class="scene-text">' + esc(t('app_error')) + '</p><p class="help">' + esc(e.message) + '</p>' +
        '<div class="actions"><button class="btn btn-main" data-again>' + esc(t('app_retry')) + '</button></div>');
      body.querySelector('[data-again]').onclick = function() {
        ending(n);
      };
    }

    show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_loading')) + '</div>');
    (opts.preview ? Promise.resolve(opts.preview) : T.api('get_scene', {courseid: cfg.courseid, sceneid: opts.sceneid})
      .then(function(d) {
        keepScene(d);
        return d;
      }).catch(function(e) {
        var kept = e.network && load(SCENES, {})[opts.sceneid];
        if (kept) {
          T.toast(esc(t('app_offline_scene')));
          return kept.d;
        }
        throw e;
      }))
      .then(function(d) {
        data = d;
        data.speakers = JSON.parse(d.speakers || '{}');
        data.items = JSON.parse(d.items || '{}');
        data.flags = JSON.parse(d.flags || '[]');
        data.pictures = JSON.parse(d.pictures || '{}');
        sc = JSON.parse(d.definition);
        liven();
        if (T.fx) {
          // The soft sound of the place of the scene.
          T.fx.ambient(T.fx.place(d.place), d.place);
        }
        if (opts.huddle && T.huddleSync) {
          // Those who start are in until the session says otherwise.
          huddleIds = (opts.team || []).map(Number);
          huddle = T.huddleSync(opts.huddle, huddleNow, huddlePlay, huddlePresent, huddleGate);
        }
        start(null);
      }).catch(function(e) {
        show('<p class="scene-text">' + esc(t('app_error')) + '</p><p class="help">' + esc(e.message) + '</p>');
      });
  };
})();
