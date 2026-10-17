/**
 * Tesapé: the player of decision scenes. It shows each node with the art of the game, notes what the student does
 * and, at an ending, sends it to the server, which plays the scene again and decides the ending and the rewards.
 *
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var cfg = T.cfg;
  var t = T.t;
  var esc = T.esc;

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
    copy.querySelectorAll('p, h2, h3, li, div, blockquote, small, b, label, button, .what, .speaker, .kicker, .tag')
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
    // The seed of the creature, warm and alive, with the cracks that open as it is touched.
    var SEED = T.artUrl && T.artUrl(['creature.seed']) ? '<img src="' + esc(T.artUrl(['creature.seed'])) + '" alt="">' :
      '<svg viewBox="0 0 100 120" aria-hidden="true"><defs><radialGradient id="tesape-seed" cx="40%" cy="35%" r="70%">' +
      '<stop offset="0" stop-color="#fff2c4"/><stop offset=".55" stop-color="#e9b85a"/><stop offset="1" stop-color="#9a6a2a"/>' +
      '</radialGradient></defs><ellipse cx="50" cy="66" rx="34" ry="44" fill="url(#tesape-seed)"/><path class="c1" ' +
      'd="M50 26 L44 44 L54 52" stroke="#5b3a12" stroke-width="3" fill="none"/><path class="c2" d="M54 52 L46 66 L58 76" ' +
      'stroke="#5b3a12" stroke-width="3" fill="none"/><path class="c3" d="M58 76 L50 92 L60 104" stroke="#5b3a12" ' +
      'stroke-width="3" fill="none"/><path d="M50 24 Q60 10 70 14 Q60 22 50 24Z" fill="#7cc67f"/></svg>';
    // While a play is being saved there is no way out: closed then, the map would show it as not played.
    var saving = false;

    function close() {
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
     */
    function celebrate(outcome, r) {
      if (!T.fx) {
        return;
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
      // Reading aloud is always at hand in a scene (who has it in their adaptations hears it by itself).
      var reads = !!window.speechSynthesis;
      if (reads) {
        window.speechSynthesis.cancel();
      }
      layer.innerHTML = '<div class="scene"><div class="scene-top"><span class="scene-title">' + esc(sc ? sc.title : '') +
        '</span>' + (reads ? '<button class="btn btn-soft scene-read" type="button" data-read aria-label="' +
        esc(t('app_read_aloud')) + '" title="' + esc(t('app_read_aloud')) + '">' + T.icon('speak') + '</button>' : '') +
        (saving ? '' : '<button class="btn btn-soft scene-close" type="button" aria-label="' + esc(t('app_close')) +
        '">' + T.icon('close') + '</button>') + '</div>' + (opts.team && opts.team.length ? '<p class="team-banner">' + esc(t('app_team_banner')) +
        '</p>' : '') + '<div class="scene-body" tabindex="-1">' + html + '</div></div>';
      var cross = layer.querySelector('.scene-close');
      if (cross) {
        cross.onclick = leave;
      }
      var read = layer.querySelector('[data-read]');
      if (read) {
        read.onclick = function() {
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
        return cm ? cm.roles.indexOf(req.role) >= 0 : data.role === req.role;
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
      run = {node: from || sc.start, from: from || '', events: [], extra: 0, extraused: {}, hints: 0, shield: false,
        flags: (data.flags || []).slice(), opened: {}, spent: 0, trailed: {}, turn: 0, handfor: {}, looks: {}, tried: {},
        keep: '', clues: []};
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
      run.node = node;
      next();
    }

    function next() {
      var n = sc.nodes[run.node];
      // In a play as a team, each new decision passes to the next one of the team.
      if (/^(choice|classify|order)$/.test(n.type)) {
        if (run.decided && run.decided !== run.node) {
          run.turn++;
        }
        run.decided = run.node;
        handTo(n);
      }
      if (n.type === 'story') {
        story(n);
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
      } else if (n.type === 'classify') {
        classify(n);
      } else if (n.type === 'order') {
        order(n);
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
      var pieces = cut(tx(n.text));
      var at = 0;
      var body = show('<div class="dlg">' + stage(n.image, n.speaker) + '<div class="dlg-box">' + (n.speaker ?
        '<span class="dlg-name">' + esc(data.speakers[n.speaker] || n.speaker) + '</span>' : '') +
        '<p class="scene-text" data-piece></p><span class="dlg-dots" data-dots aria-hidden="true"></span>' +
        '<div class="actions"><button class="btn btn-main" data-focus data-next></button></div></div></div>');
      var button = body.querySelector('[data-next]');
      function piece() {
        var last = at >= pieces.length - 1;
        body.querySelector('[data-piece]').textContent = pieces[at];
        body.querySelector('[data-dots]').innerHTML = pieces.length > 1 ? pieces.map(function(p, i) {
          return '<i class="' + (i === at ? 'on' : i < at ? 'seen' : '') + '"></i>';
        }).join('') : '';
        button.textContent = last ? t('app_continue') : t('app_next');
      }
      button.onclick = function() {
        if (at >= pieces.length - 1) {
          go(n.next);
          return;
        }
        at++;
        piece();
        button.focus();
      };
      piece();
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
      var picks = [];
      run.events.forEach(function(e) {
        if (e.n === node && e.pick) {
          picks = e.pick;
        }
      });
      var opts = n.options || [];
      return picks.filter(function(id) {
        return opts.some(function(o) {
          return o.id === id && o.correct === true;
        });
      }).length;
    }

    /**
     * A mistake: the protector's shield stops the points it costs, once a play.
     */
    function mistake() {
      if (!cm && data.role === 'protector' && !run.shield) {
        run.shield = true;
        T.toast(esc(t('app_shield_stops')));
      }
    }

    /**
     * What the next hint costs: one breath; the sage gets the first free and pays half for the rest; free in class.
     *
     * @return {number}
     */
    function hintCost() {
      // With «more help» or the aids of the first steps, the lens is free (the server charges the same).
      if (cm || data.aided || T.adapted.hints) {
        return 0;
      }
      if (data.role === 'sage') {
        return run.hints % 2;
      }
      return 1;
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
      var mine = true;
      var hand = turnOf();
      if (hand && Number(hand.userid) !== Number(cfg.userid)) {
        // The role at hand is the one of the student on whose device it is: the others decide with their words.
        mine = false;
      }
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
        protector: {icon: 'shield', label: run.shield ? t('app_role_shield_used') : t('app_role_shield_ready')},
        sage: {icon: 'log', label: t('app_role_notebook')},
        healer: {icon: 'cheer', label: t('app_role_cheer')},
        scout: {icon: 'trail', label: t('app_role_trail')}
      };
      var verb = verbs[data.role];
      if (!verb || !mine) {
        return;
      }
      var bar = document.createElement('div');
      bar.className = 'role-bar';
      bar.innerHTML = '<button type="button" class="role-verb ' + esc(data.role) + (data.role === 'protector' && run.shield ?
        ' used' : '') + '" data-role>' + T.icon(verb.icon) + '<span>' + esc(verb.label) + '</span></button>';
      body.appendChild(bar);
      bar.querySelector('[data-role]').onclick = function() {
        if (data.role === 'sage') {
          var lens = body.querySelector('[data-hint]');
          if (lens) {
            lens.click();
          } else {
            T.toast(esc(t('app_role_notebook_none')));
          }
        } else if (data.role === 'scout') {
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
          T.toast(esc(t(data.role === 'protector' ? 'app_role_shield_help' : 'app_role_cheer_help')));
        }
      };
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
        return req.teamrole || (req.role && req.role === data.role ? req.role : '');
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
      var order = [cfg.userid].concat(opts.team);
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

    function hintArea(n, body) {
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
        box.innerHTML = opened.map(function(i) {
          return '<p class="hint">' + T.icon('log') + ' ' + esc(tx(n.hints[i])) + '</p>';
        }).join('') + (opened.length < n.hints.length ? '<button class="btn btn-soft" type="button" data-hint>' +
          T.icon('log') + ' ' + esc(cost ? t('app_hint_cost', {cost: cost, left: Math.max(0, (data.breath || 0) - run.spent)}) :
          t('app_hint_free')) + '</button>' : '');
        var b = box.querySelector('[data-hint]');
        if (b) {
          b.onclick = function() {
            if (cost > (data.breath || 0) - run.spent) {
              T.toast(esc(t('app_hint_nobreath')), true);
              return;
            }
            var index = opened.length;
            run.events.push({n: run.node, hint: index});
            run.opened[run.node] = opened.concat([index]);
            run.hints++;
            run.spent += cost;
            draw();
          };
        }
      };
      draw();
    }

    function choice(n) {
      var max = (n.select && n.select.max) || 1;
      var min = (n.select && n.select.min) || max;
      var multi = max > 1;
      var selected = [];
      // At the class screen the phones vote by letter: the options stay where they are.
      var tried = cm ? [] : run.tried[run.node] || [];
      // What contradicts what the answers told is crossed out by itself: who asked well arrives with one candidate.
      var crossed = function(o) {
        return (o.contradicts || []).some(function(c) {
          return run.clues.indexOf(c) >= 0;
        });
      };
      var open = n.options.filter(function(o) {
        return has(o.requires) && tried.indexOf(o.id) < 0 && !crossed(o);
      });
      var opts = n.options.filter(function(o) {
        // What other scenes left (flags) is memory, not something to get: without it, the option is not there; nor is
        // the option of another role (each role sees only its own). What was tried and led back here is not there
        // either: the world changed, it is not the same question again.
        var hidden = o.requires && (o.requires.flag || o.requires.role) && !has(o.requires);
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
        var letter = cm ? '<span class="opt-letter">' + LETTERS[open.indexOf(o)] + '</span>' : '';
        // What only the scout sees is there to be found: it shows when they follow the trail.
        var trail = !cm && o.requires && o.requires.role === 'scout' && !run.trailed[run.node];
        return '<button class="opt' + (trail ? ' trail' : '') + '" type="button" data-id="' + esc(o.id) + '"' +
          (trail ? ' data-trail hidden' : '') + '>' + letter + esc(tx(o.label)) + '<span class="opt-votes" data-votes></span></button>';
      }).join('');
      var voting = cm && cm.vote && !multi && open.length >= 2;
      var body = show((n.image ? picture(n.image, data) : '') + clueBox() + '<p class="scene-prompt">' + esc(tx(n.prompt)) +
        '</p>' +
        (voting ? '<div class="class-vote" data-vote><button class="btn btn-soft" data-voteopen>' +
        esc(t('board_scene_voteopen')) + '</button></div>' : '') +
        '<div class="opts">' + opts + '</div>' + (multi ? '<div class="counter" data-count>' + esc(t('app_chosen', '0/' + max)) +
        '</div><div class="actions"><button class="btn btn-main" data-confirm disabled>' + esc(t('app_confirm')) +
        '</button></div>' : '') + '<div data-out></div>');
      hintArea(n, body);
      roleBar(n, body);
      if (voting) {
        classVote(body, n, open);
      }
      function pick(id) {
        return n.options.filter(function(o) {
          return o.id === id;
        })[0];
      }
      lookTools(n.options, body);
      body.querySelectorAll('.opt[data-id]').forEach(function(b) {
        b.onclick = function() {
          var id = b.dataset.id;
          if (!multi) {
            body.querySelectorAll('.opt, .look-tool').forEach(function(x) {
              x.disabled = true;
            });
            b.classList.add('sel');
            run.events.push(by({n: run.node, pick: [id]}));
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
              if (target === run.node) {
                run.tried[run.node] = (run.tried[run.node] || []).concat([id]);
                next();
              } else if (run.extra > 0 && n.extra && !run.extraused[run.node]) {
                run.extra--;
                run.extraused[run.node] = true;
                T.toast(esc(t('app_extraquestion')));
                next();
              } else {
                go(target);
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
          reveal(body, selected.map(pick), function() {
            go(n.next);
          });
        };
      }
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
    function classify(n) {
      var once = n.retry === 'none';
      // With the aids of the first steps, the hard things stay out of the table.
      var items = n.items.filter(function(it) {
        return has(it.requires) && !(data.aided && it.hard);
      });
      var locked = n.items.filter(function(it) {
        return !has(it.requires);
      });
      var place = {};
      var right = {};
      var chosen = null;
      var trip = 1;
      var body = show((n.image ? stage(n.image, n.speaker) : '') + '<p class="scene-prompt">' + esc(tx(n.prompt)) +
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
              (chosen === it.id) + '">' + esc(tx(it.label)) + '</button>';
          }).join('') + locked.map(function(it) {
            return '<button type="button" class="cls-thing locked" disabled>' + esc(tx(it.label)) + '<small>' +
              esc(it.locked ? tx(it.locked) : missing(it.requires)) + '</small></button>';
          }).join('') + '</div>' + lookArea() + '<div class="bins">' + n.bins.map(function(b) {
            var inside = items.filter(function(it) {
              return (right[it.id] || place[it.id]) === b.id;
            });
            return '<div class="cls-bin"><button type="button" class="bin" data-bin="' + esc(b.id) + '"' +
              (chosen ? '' : ' disabled') + '>' + esc(tx(b.label)) + '</button><span class="cls-in">' +
              inside.map(function(it) {
                return right[it.id] ? '<span class="cls-thing kept">' + esc(tx(it.label)) + '</span>' :
                  '<button type="button" class="cls-thing placed" data-take="' + esc(it.id) + '" aria-label="' +
                  esc(t('app_classify_take', tx(it.label))) + '">' + esc(tx(it.label)) + '</button>';
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
            var own = it.feedback && it.feedback[put[it.id]];
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
        var body = show('<p class="scene-prompt">' + esc(tx(n.prompt)) + '</p><ol class="built">' + built.map(function(s) {
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
          cm.vote.close().then(tally);
        };
      }
      function poll() {
        timer = setTimeout(function() {
          if (!document.body.contains(box) || closed) {
            return;
          }
          cm.vote.state().then(function(live) {
            if (live && live.open && !closed) {
              count(live);
              poll();
            }
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
        cm.vote.open(tx(n.prompt), open.map(function(o) {
          return tx(o.label);
        })).then(function(live) {
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
     * @return {string}
     */
    function whatif(ok) {
      if (!ok || !sc || !sc.whatif) {
        return '';
      }
      return '<div class="whatif"><span class="what">' + esc(t('app_whatif')) + '</span><p>' + esc(tx(sc.whatif)) +
        '</p><small>' + esc(t(cm ? 'board_whatif_help' : opts.team && opts.team.length ? 'app_whatif_team' :
        'app_whatif_help')) + '</small></div>';
    }

    function classEnding(n) {
      show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_saving')) + '</div>');
      Promise.all([cm.finish(run.events, run.from), drumroll()]).then(function(answers) {
        var result = answers[0];
        var success = result.outcome !== 'retry';
        celebrate(result.outcome, null);
        var body = show(picture(n.image || sceneImage(), data) + '<p class="scene-text">' + esc(tx(n.text)) + '</p>' + (success ?
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
          lines.push('+' + r.gems + ' ' + t('app_gems'));
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
        var body = show(picture(n.image || sceneImage(), data) + '<p class="scene-text">' + esc(tx(n.text)) + '</p>' + hatch + levelup +
          (lines.length ? '<div class="conseq good"><span class="what">' + esc(t('app_forcharacter')) + '</span>' +
          lines.map(esc).join('<br>') + '</div>' : '') + done + better(result) + whatif(success) + '<div class="actions">' +
          (success ?
          '<button class="btn btn-main" data-focus data-map>' + esc(t('app_backtomap')) + '</button>' + replay(result) :
          '<button class="btn btn-main" data-focus data-retry>' + esc(t('app_tryagain')) + '</button>') + '</div>');
        onReplay(body);
        celebrate(result.outcome, r);
        hatching(body, n);
        var map = body.querySelector('[data-map]');
        if (map) {
          map.onclick = close;
        } else {
          body.querySelector('[data-retry]').onclick = function() {
            start(result.retry || n.retry);
          };
        }
      }).catch(function(e) {
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
      });
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
        sc = JSON.parse(d.definition);
        if (T.fx) {
          // The soft sound of the place of the scene.
          T.fx.ambient(T.fx.place(d.place));
        }
        start(null);
      }).catch(function(e) {
        show('<p class="scene-text">' + esc(t('app_error')) + '</p><p class="help">' + esc(e.message) + '</p>');
      });
  };
})();
