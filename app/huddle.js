/**
 * Tesapé: the team session, the tablets of a team joined to a scene. The scene runs on the tablet of who opened it (the
 * guide); the others are the hand: they see each decision to choose and, when it is their turn, choose on their own
 * tablet. Here: the waiting room of the guide, what the scene of the guide sends and takes, and the call and the view of
 * the rest of the team, which ask the server every second and a half while inside and less and less often otherwise.
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
  var FAST = 1500;
  var SLOW = 4000;
  var SLOWEST = 16000;

  function api(action, extra) {
    return T.api('huddle', Object.assign({courseid: cfg.courseid, action: action}, extra || {}));
  }

  /**
   * The names of who is in, as chips.
   *
   * @param {Object} h The session.
   * @return {string}
   */
  function chips(h) {
    return '<div class="huddle-members">' + h.members.map(function(m) {
      return '<span class="chip' + (m.me ? ' me' : '') + '">' + esc(m.name) + '</span>';
    }).join('') + '</div>';
  }

  /* ---------- The guide ---------- */

  /**
   * The waiting room of the guide: the session opens, the team comes in, and «Start» begins the scene with those who
   * are in. If someone of the team had opened one already, this tablet joins theirs instead.
   *
   * @param {number} sceneid
   * @param {Function} start Gets the session and the users who play.
   */
  T.huddleLobby = function(sceneid, start) {
    var layer = document.createElement('div');
    layer.className = 'badge-moment huddle-lobby';
    layer.setAttribute('role', 'dialog');
    layer.setAttribute('aria-modal', 'true');
    layer.setAttribute('aria-labelledby', 'tesape-huddle-title');
    document.body.appendChild(layer);
    var timer = 0;
    var gone = false;
    var finish = function() {
      gone = true;
      clearTimeout(timer);
      layer.remove();
    };
    var draw = function(h) {
      layer.innerHTML = '<div class="badge-card"><div class="kicker">' + esc(h.scene) + '</div><h2 id="tesape-huddle-title">' +
        esc(t('app_huddle_waiting')) + '</h2><p class="help">' + esc(t('app_huddle_waiting_help')) + '</p>' + chips(h) +
        '<div class="actions"><button type="button" class="btn btn-soft" data-cancel>' + esc(t('app_cancel')) +
        '</button><button type="button" class="btn btn-main" data-start' + (h.members.length < 2 ? ' disabled' : '') + '>' +
        esc(t('app_huddle_start')) + '</button></div></div>';
      layer.querySelector('[data-cancel]').onclick = function() {
        api('close', {huddleid: h.id}).catch(function() {
          // Without its guide, it is gone in two minutes anyway.
        });
        finish();
      };
      layer.querySelector('[data-start]').onclick = function(ev) {
        ev.target.disabled = true;
        api('start', {huddleid: h.id}).then(function(r) {
          finish();
          start(r.huddle, r.huddle.players);
        }).catch(function(e) {
          ev.target.disabled = false;
          T.toast(esc(e.message), true);
        });
      };
    };
    var poll = function() {
      api('mine').then(function(r) {
        if (gone) {
          return;
        }
        if (!r.huddle) {
          finish();
          return;
        }
        var focus = document.activeElement && document.activeElement.hasAttribute('data-start');
        draw(r.huddle);
        if (focus) {
          layer.querySelector('[data-start]').focus();
        }
        timer = setTimeout(poll, FAST);
      }).catch(function() {
        timer = setTimeout(poll, SLOW);
      });
    };
    api('open', {sceneid: sceneid}).then(function(r) {
      if (!r.huddle.guide) {
        // Someone of the team had opened one: this tablet is in theirs.
        finish();
        follow.now();
        return;
      }
      draw(r.huddle);
      layer.querySelector('[data-cancel]').focus();
      timer = setTimeout(poll, FAST);
    }).catch(function(e) {
      finish();
      T.toast(esc(e.message), true);
    });
  };

  /**
   * What the scene of the guide sends and takes while it runs: the decision at hand when it changes, the choice the hand
   * made on their tablet, and who is still in.
   *
   * @param {number} id The session.
   * @param {Function} now Returns the decision at hand (node, kind, prompt, options, hand), or null.
   * @param {Function} play Plays the option chosen on another tablet.
   * @param {Function} present Gets the users still in.
   * @param {Function} [gate] Gets, with the information shared out, how many told their piece (told, all) while the
   *     decision waits for them, or null when it can be decided.
   * @return {Object} With stop().
   */
  T.huddleSync = function(id, now, play, present, gate) {
    follow.guiding = true;
    var sent = '';
    var applied = {};
    var timer = 0;
    var stopped = false;
    var tick = function() {
      var node = now();
      var said = node ? JSON.stringify(node) : '';
      var ask = said && said !== sent ? api('publish', {huddleid: id, node: said}) : api('mine');
      ask.then(function(r) {
        if (stopped) {
          return;
        }
        if (said && said !== sent) {
          sent = said;
        }
        var h = r.huddle;
        if (h) {
          present(h.members.map(function(m) {
            return Number(m.userid);
          }));
          if (gate) {
            gate(h.shared && !h.ready ? {told: h.told, all: h.tellers} : null);
          }
          if (h.pending && h.pending.seq === h.seq && !applied[h.seq]) {
            applied[h.seq] = true;
            play(h.pending.option, h.pending.answer || '');
          }
        }
      }).catch(function() {
        // A moment without connection: the next tick tries again.
      }).then(function() {
        if (!stopped) {
          timer = setTimeout(tick, FAST);
        }
      });
    };
    timer = setTimeout(tick, 300);
    return {
      stop: function() {
        stopped = true;
        follow.guiding = false;
        clearTimeout(timer);
        api('close', {huddleid: id}).catch(function() {
          // Without its guide, it is gone in two minutes anyway.
        });
      }
    };
  };

  /* ---------- The rest of the team ---------- */

  var follow = {guiding: false, joined: false, closed: false, dismissed: 0, idle: 0, timer: 0, seen: null, sig: ''};
  var layer = document.createElement('div');
  layer.className = 'huddle-layer';
  document.body.appendChild(layer);

  /**
   * How long until the next question to the server.
   *
   * @return {number}
   */
  function wait() {
    if (follow.joined) {
      return FAST;
    }
    if (follow.seen) {
      return SLOW;
    }
    return Math.min(SLOWEST, SLOW * Math.pow(2, Math.max(0, follow.idle - 1)));
  }

  follow.poll = function() {
    clearTimeout(follow.timer);
    if (document.hidden || follow.guiding) {
      follow.timer = setTimeout(follow.poll, SLOW);
      return;
    }
    api('mine').then(function(r) {
      follow.idle = r.huddle ? 0 : follow.idle + 1;
      follow.show(r.huddle || null);
    }).catch(function() {
      follow.idle++;
    }).then(function() {
      follow.timer = setTimeout(follow.poll, wait());
    });
  };

  /** Asks now, after joining or when a tablet comes in through the lobby of someone else. */
  follow.now = function() {
    api('join').then(function(r) {
      follow.joined = !!r.huddle;
      follow.show(r.huddle || null);
    }).catch(function(e) {
      T.toast(esc(e.message), true);
    }).then(function() {
      follow.timer = setTimeout(follow.poll, FAST);
    });
  };

  /**
   * The call to join, the session from inside, or nothing.
   *
   * @param {Object|null} h
   */
  follow.show = function(h) {
    var had = follow.seen;
    follow.seen = h;
    if (!h || (h.guide && !h.knot)) {
      if (follow.joined && had && !h) {
        // The guide closed it, or its tablet went away.
        layer.innerHTML = '<div class="huddle-phone" role="dialog" aria-modal="true" aria-labelledby="tesape-huddle-end">' +
          '<h2 id="tesape-huddle-end">' + esc(t('app_huddle_closed')) + '</h2><div class="actions"><button type="button" ' +
          'class="btn btn-main" data-leave>' + esc(t('app_huddle_leave')) + '</button></div></div>';
        follow.joined = false;
        follow.closed = true;
        follow.bindLeave();
        return;
      }
      // What says it closed stays until it is read.
      if (!follow.closed) {
        layer.innerHTML = '';
      }
      follow.joined = false;
      follow.sig = '';
      document.body.classList.remove('in-huddle');
      return;
    }
    // Not over a battle nor over a scene being played on this tablet.
    if (document.body.classList.contains('in-battle') || (!follow.joined && document.body.classList.contains('in-scene'))) {
      layer.innerHTML = '';
      return;
    }
    if (!h.joined || follow.dismissed === h.id) {
      if (follow.dismissed === h.id) {
        layer.innerHTML = '';
        return;
      }
      var call = JSON.stringify(['call', h.id, h.scene, h.guidename]);
      if (follow.sig === call) {
        return;
      }
      follow.sig = call;
      layer.innerHTML = '<div class="battle-call huddle-call"><p>' + esc(h.knot ? t('app_teamknot_call', h.guidename) :
        t('app_huddle_call', {scene: h.scene, guide: h.guidename})) + '</p><button type="button" class="btn btn-main" data-join>' + esc(t('app_huddle_join')) +
        '</button><button type="button" class="btn btn-soft" data-later>' + esc(t('app_talent_later')) + '</button></div>';
      layer.querySelector('[data-join]').onclick = function(ev) {
        ev.target.disabled = true;
        follow.now();
      };
      layer.querySelector('[data-later]').onclick = function() {
        follow.dismissed = h.id;
        layer.innerHTML = '';
      };
      return;
    }
    follow.joined = true;
    document.body.classList.add('in-huddle');
    if (h.knot) {
      knot(h);
      return;
    }
    var sig = JSON.stringify([h.seq, h.started, h.members, h.waiting, h.hand, h.myturn, h.piece, h.known, h.told, h.tellers,
      h.mytold, h.ready]);
    if (follow.sig === sig) {
      return;
    }
    follow.sig = sig;
    // What only their role sees: the others need it, so it is to be said aloud.
    var piece = (h.started && h.known && h.known.length ? '<div class="clues"><small>' + esc(t('app_clues')) + '</small><ul>' +
      h.known.map(function(k) {
        return '<li>' + esc(k) + '</li>';
      }).join('') + '</ul></div>' : '') + (h.started && h.piece ? '<div class="piece mine"><b>' + esc(t('app_piece_mine')) +
      '</b><p>' + esc(h.piece) + '</p><small>' + esc(t('app_piece_tell')) + '</small></div>' : '') +
      // With the information shared out, each one says they told their piece; it is decided when everybody has.
      (h.started && h.shared ? '<div class="huddle-told">' + (h.mytold ? '' : '<button type="button" class="btn btn-main" ' +
      'data-tell>' + esc(t('app_huddle_tell')) + '</button>') + '<p class="help-soft" role="status">' +
      esc(t('app_huddle_told', {told: h.told, all: h.tellers})) + '</p></div>' : '');
    // Until everybody told their piece, the hand waits.
    var wait = h.shared && !h.ready;
    var body = '';
    if (!h.started) {
      body = '<p class="help">' + esc(t('app_huddle_notyet', h.guidename)) + '</p>';
    } else if (h.kind === 'choose' && h.options.length) {
      var mine = h.myturn && !h.waiting && !wait;
      body = '<p class="huddle-turn' + (h.myturn ? ' mine' : '') + '">' + esc(h.myturn ? t('app_huddle_yourturn') :
        t('app_huddle_theirturn', h.handname)) + '</p><p class="scene-prompt">' + esc(h.prompt) + '</p><div class="opts">' +
        h.options.map(function(o) {
          return '<button type="button" class="opt" data-pick="' + esc(o.id) + '"' + (mine ? '' : ' disabled') + '>' +
            esc(o.label) + '</button>';
        }).join('') + '</div>' + (h.waiting ? '<p class="help-soft">' + esc(t('app_huddle_chosen', h.guidename)) + '</p>' : '');
    } else if (h.kind === 'multi' && h.options.length) {
      // Several at once: picked here and sent together.
      var picks = follow.picks && follow.picks.seq === h.seq ? follow.picks.ids : [];
      follow.picks = {seq: h.seq, ids: picks};
      var free = h.myturn && !h.waiting && !wait;
      body = '<p class="huddle-turn' + (h.myturn ? ' mine' : '') + '">' + esc(h.myturn ? t('app_huddle_yourturn') :
        t('app_huddle_theirturn', h.handname)) + '</p><p class="scene-prompt">' + esc(h.prompt) + '</p><div class="opts">' +
        h.options.map(function(o) {
          return '<button type="button" class="opt' + (picks.indexOf(o.id) >= 0 ? ' sel' : '') + '" data-multi="' + esc(o.id) +
            '" aria-pressed="' + (picks.indexOf(o.id) >= 0) + '"' + (free ? '' : ' disabled') + '>' + esc(o.label) + '</button>';
        }).join('') + '</div>' + (free ? '<p class="help-soft" data-count>' + esc(t('app_chosen', picks.length + '/' + h.max)) +
        '</p><div class="actions"><button type="button" class="btn btn-main" data-multisend' + (picks.length < h.min ?
        ' disabled' : '') + '>' + esc(t('app_confirm')) + '</button></div>' : '') + (h.waiting ? '<p class="help-soft">' +
        esc(t('app_huddle_chosen', h.guidename)) + '</p>' : '');
    } else if (h.kind === 'classify' && h.options.length && h.bins.length && T.lane) {
      // To sort, on this tablet too: where each thing goes is sent whole.
      body = '<p class="huddle-turn' + (h.myturn ? ' mine' : '') + '">' + esc(h.myturn ? t('app_huddle_yourturn') :
        t('app_huddle_theirturn', h.handname)) + '</p><div class="lane-play" data-hlane></div>' + (h.waiting ?
        '<p class="help-soft">' + esc(t('app_huddle_chosen', h.guidename)) + '</p>' : '');
    } else if (h.kind === 'story') {
      body = '<p class="help">' + esc(t('app_huddle_story', h.guidename)) + '</p>';
    } else if (h.kind === 'ending') {
      body = '<p class="help">' + esc(t('app_huddle_end', h.guidename)) + '</p>';
    } else {
      body = (h.prompt ? '<p class="scene-prompt">' + esc(h.prompt) + '</p>' : '') + '<p class="help">' +
        esc(t('app_huddle_there', h.guidename)) + '</p>';
    }
    layer.innerHTML = '<div class="huddle-phone" role="dialog" aria-modal="true" aria-labelledby="tesape-huddle-scene">' +
      '<div class="kicker">' + esc(t('app_huddle_in', h.guidename)) + '</div><h2 id="tesape-huddle-scene">' + esc(h.scene) +
      '</h2>' + chips(h) + piece + body + '<div class="actions"><button type="button" class="btn btn-soft" data-leave>' +
      esc(t('app_huddle_leave')) + '</button></div></div>';
    layer.querySelectorAll('[data-pick]').forEach(function(b) {
      b.onclick = function() {
        layer.querySelectorAll('[data-pick]').forEach(function(x) {
          x.disabled = true;
        });
        b.classList.add('sel');
        follow.decide(h, {option: b.dataset.pick});
      };
    });
    layer.querySelectorAll('[data-multi]').forEach(function(b) {
      b.onclick = function() {
        var ids = follow.picks.ids;
        var at = ids.indexOf(b.dataset.multi);
        if (at >= 0) {
          ids.splice(at, 1);
        } else if (ids.length < h.max) {
          ids.push(b.dataset.multi);
        }
        follow.sig = '';
        follow.show(h);
      };
    });
    var tell = layer.querySelector('[data-tell]');
    if (tell) {
      tell.onclick = function() {
        tell.disabled = true;
        api('tell', {huddleid: h.id, seq: h.seq}).then(function(r) {
          follow.show(r.huddle || null);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          follow.sig = '';
        });
      };
    }
    var send = layer.querySelector('[data-multisend]');
    if (send) {
      send.onclick = function() {
        send.disabled = true;
        follow.decide(h, {answer: JSON.stringify(follow.picks.ids)});
      };
    }
    var lane = layer.querySelector('[data-hlane]');
    if (lane) {
      T.lane(lane, {engine: 'classify', prompt: h.prompt, items: h.options, bins: h.bins, mine: h.myturn && !wait},
        {key: 'huddle-' + h.id + '-' + h.seq, open: h.myturn && !h.waiting && !wait, sent: h.waiting, onsend: function(payload) {
          follow.decide(h, {answer: JSON.stringify(payload.placed)});
        }});
    }
    follow.bindLeave();
    var first = layer.querySelector('[data-pick]:not([disabled]), [data-multi]:not([disabled])');
    if (first) {
      first.focus();
    }
  };

  /**
   * A knot of the team, the same on every tablet: the waiting room, each turn (who answers, the question, the help of
   * the others with the verb of their role) and the end.
   *
   * @param {Object} h The session, with its knot.
   */
  function knot(h) {
    var k = h.knot;
    var sig = JSON.stringify(['knot', h.members, k]);
    if (follow.sig === sig) {
      return;
    }
    follow.sig = sig;
    var cap = function(s) {
      return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
    };
    var said = {right: 'app_teamknot_right', again: 'app_teamknot_again', shielded: 'app_teamknot_shielded',
      blown: 'app_teamknot_blown'};
    var title = k.phase === 'lobby' ? t('app_teamknot_lobby') : k.phase === 'end' ? t(k.outcome === 'won' ?
      'app_teamknot_won' : 'app_teamknot_lost') : cap(k.name);
    var body = '';
    if (k.phase === 'lobby') {
      body = '<p class="help">' + esc(t('app_teamknot_lobby_help')) + '</p><div class="actions"><button type="button" ' +
        'class="btn btn-main" data-kstart' + (h.members.length < 2 ? ' disabled' : '') + '>' + esc(t('app_huddle_start')) +
        '</button></div>' + (h.members.length < 2 ? '<p class="help-soft">' + esc(t('app_teamknot_few')) + '</p>' : '');
    } else if (k.phase === 'round') {
      var flames = '';
      for (var i = 0; i < k.maxcandles; i++) {
        flames += '<span class="knot-candle' + (i < k.candles ? ' lit' : '') + '" aria-hidden="true"></span>';
      }
      body = '<div class="knot-bar"><span>' + esc(t('app_teamknot_threads', k)) + '</span><span role="img" aria-label="' +
        esc(t('app_teamknot_candles', k)) + '">' + flames + '</span></div>' + (k.last && said[k.last] ? '<p class="knot-last ' +
        esc(k.last) + '" role="status">' + esc(t(said[k.last])) + '</p>' : '') + (k.claim ? '<p class="knot-claim">«' +
        esc(k.claim) + '»</p>' : '') + '<p class="huddle-turn' + (k.myturn ? ' mine' : '') + '">' + esc(k.myturn ?
        t('app_teamknot_yourturn') : t(k.canhelp ? 'app_teamknot_theirturn_help' : 'app_teamknot_theirturn', k.handname)) +
        '</p><p class="scene-prompt">' + esc(k.question) + '</p><div class="opts">' + k.options.map(function(o, n) {
          return '<button type="button" class="opt' + (o.struck ? ' struck' : '') + '" data-kopt="' + n + '"' +
            (k.myturn && !o.struck ? '' : ' disabled') + (o.struck ? ' aria-label="' + esc(t('app_teamknot_struck', o.text)) +
            '"' : '') + '>' + esc(o.text) + '</button>';
        }).join('') + '</div>' + (k.weakness ? '<p class="knot-weak"><b>' + esc(t('app_teamknot_weakness')) + '</b> ' +
        esc(k.weakness) + '</p>' : '') + (k.helped.length ? '<ul class="knot-helped">' + k.helped.map(function(x) {
          return '<li>' + esc(t('app_teamknot_helped_' + x.verb, x.name)) + '</li>';
        }).join('') + '</ul>' : '') + (k.canhelp ? '<div class="actions" style="justify-content:flex-start"><button ' +
        'type="button" class="btn btn-soft" data-khelp="' + esc(k.myverb) + '">' + esc(t('app_teamknot_verb_' + k.myverb)) +
        '</button></div>' : '');
    } else {
      var results = {right: 'app_teamknot_log_right', shielded: 'app_teamknot_log_shielded', blown: 'app_teamknot_log_blown'};
      body = '<p>' + esc(t(k.outcome === 'won' ? 'app_teamknot_won_text' : k.candles > 0 ? 'app_teamknot_lost_text' :
        'app_teamknot_lost_candles', k)) + '</p>' +
        (k.gem ? '<p><b>' + esc(t('app_teamknot_gem')) + '</b></p>' : k.outcome === 'won' && k.player ? '<p class="help-soft">' +
        esc(t('app_teamknot_gemtoday')) + '</p>' : '') + '<ul class="list">' + k.log.map(function(x) {
          return '<li class="item"><span class="grow">' + esc(cap(x.name)) + '</span><small>' + esc(t(results[x.result] ||
            results.blown)) + '</small></li>';
        }).join('') + '</ul>';
    }
    layer.innerHTML = '<div class="huddle-phone team-knot' + (k.outcome ? ' ' + esc(k.outcome) : '') + '" role="dialog" ' +
      'aria-modal="true" aria-labelledby="tesape-knot-title"><div class="kicker">' + esc(t('app_teamknot')) + '</div><h2 ' +
      'id="tesape-knot-title">' + esc(title) + '</h2>' + chips(h) + body + '<div class="actions"><button type="button" ' +
      'class="btn btn-soft" data-leave>' + esc(t(k.phase === 'end' ? 'app_continue' : 'app_huddle_leave')) +
      '</button></div></div>';
    var act = function(action, extra) {
      layer.querySelectorAll('[data-kopt], [data-khelp], [data-kstart]').forEach(function(b) {
        b.disabled = true;
      });
      api(action, Object.assign({huddleid: h.id, seq: k.round}, extra || {})).then(function(r) {
        follow.show(r.huddle || null);
      }).catch(function(e) {
        T.toast(esc(e.message), true);
        follow.sig = '';
      });
    };
    var start = layer.querySelector('[data-kstart]');
    if (start) {
      start.onclick = function() {
        act('kstart');
      };
    }
    layer.querySelectorAll('[data-kopt]').forEach(function(b) {
      b.onclick = function() {
        b.classList.add('sel');
        act('kanswer', {option: b.dataset.kopt});
      };
    });
    var help = layer.querySelector('[data-khelp]');
    if (help) {
      help.onclick = function() {
        act('khelp', {option: help.dataset.khelp});
      };
    }
    follow.bindLeave();
    var first = layer.querySelector('[data-kopt]:not([disabled]), [data-khelp], [data-kstart]:not([disabled])');
    if (first) {
      first.focus();
    }
  }

  /**
   * Opens a knot of the team from this tablet, or comes into the one open.
   */
  T.teamKnot = function() {
    clearTimeout(follow.timer);
    follow.dismissed = 0;
    follow.closed = false;
    api('knot').then(function(r) {
      follow.joined = !!r.huddle;
      follow.show(r.huddle || null);
    }).catch(function(e) {
      T.toast(esc(e.message), true);
    }).then(function() {
      follow.timer = setTimeout(follow.poll, FAST);
    });
  };

  /**
   * Sends what the hand decided: an option, or a whole answer (several, or a sorting).
   *
   * @param {Object} h The session.
   * @param {Object} what option or answer.
   */
  follow.decide = function(h, what) {
    api('decide', Object.assign({huddleid: h.id, seq: h.seq}, what)).then(function(r) {
      follow.show(r.huddle || null);
    }).catch(function(e) {
      T.toast(esc(e.message), true);
      follow.sig = '';
    });
  };

  /** «Leave»: this tablet stops following; the session goes on with the others. */
  follow.bindLeave = function() {
    var leave = layer.querySelector('[data-leave]');
    if (leave) {
      leave.onclick = function() {
        var h = follow.seen;
        follow.dismissed = h ? h.id : 0;
        follow.joined = false;
        follow.closed = false;
        follow.sig = '';
        layer.innerHTML = '';
        document.body.classList.remove('in-huddle');
        if (h && h.joined) {
          api('leave', {huddleid: h.id}).catch(function() {
            // Without signs it stops counting by itself.
          });
        }
      };
    }
  };

  document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
      follow.poll();
    }
  });
  follow.timer = setTimeout(follow.poll, 2000);
})();
