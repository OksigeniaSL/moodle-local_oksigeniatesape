/**
 * Tesapé: the team session, the tablets of a team joined to a scene. The scene runs on the tablet of who opened it (the
 * guide); the others are the hand: they see each decision to choose and, when it is their turn, choose on their own
 * tablet. Here: the waiting room of the guide, what the scene of the guide sends and takes, and the call and the view of
 * the rest of the team, which ask the server every second and a half while inside and less and less often otherwise.
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
   * @return {Object} With stop().
   */
  T.huddleSync = function(id, now, play, present) {
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
          if (h.pending && h.pending.seq === h.seq && !applied[h.seq]) {
            applied[h.seq] = true;
            play(h.pending.option);
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
    if (!h || h.guide) {
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
      layer.innerHTML = '<div class="battle-call huddle-call"><p>' + esc(t('app_huddle_call', {scene: h.scene,
        guide: h.guidename})) + '</p><button type="button" class="btn btn-main" data-join>' + esc(t('app_huddle_join')) +
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
    var sig = JSON.stringify([h.seq, h.started, h.members, h.waiting, h.hand, h.myturn]);
    if (follow.sig === sig) {
      return;
    }
    follow.sig = sig;
    var body = '';
    if (!h.started) {
      body = '<p class="help">' + esc(t('app_huddle_notyet', h.guidename)) + '</p>';
    } else if (h.kind === 'choose' && h.options.length) {
      var mine = h.myturn && !h.waiting;
      body = '<p class="huddle-turn' + (h.myturn ? ' mine' : '') + '">' + esc(h.myturn ? t('app_huddle_yourturn') :
        t('app_huddle_theirturn', h.handname)) + '</p><p class="scene-prompt">' + esc(h.prompt) + '</p><div class="opts">' +
        h.options.map(function(o) {
          return '<button type="button" class="opt" data-pick="' + esc(o.id) + '"' + (mine ? '' : ' disabled') + '>' +
            esc(o.label) + '</button>';
        }).join('') + '</div>' + (h.waiting ? '<p class="help-soft">' + esc(t('app_huddle_chosen', h.guidename)) + '</p>' : '');
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
      '</h2>' + chips(h) + body + '<div class="actions"><button type="button" class="btn btn-soft" data-leave>' +
      esc(t('app_huddle_leave')) + '</button></div></div>';
    layer.querySelectorAll('[data-pick]').forEach(function(b) {
      b.onclick = function() {
        layer.querySelectorAll('[data-pick]').forEach(function(x) {
          x.disabled = true;
        });
        b.classList.add('sel');
        api('decide', {huddleid: h.id, option: b.dataset.pick, seq: h.seq}).then(function(r) {
          follow.show(r.huddle || null);
        }).catch(function(e) {
          T.toast(esc(e.message), true);
          follow.sig = '';
        });
      };
    });
    follow.bindLeave();
    var first = layer.querySelector('[data-pick]:not([disabled])');
    if (first) {
      first.focus();
    }
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
