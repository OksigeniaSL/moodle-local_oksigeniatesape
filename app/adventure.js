/**
 * A scene played in its place, as an adventure: the hero walks the place and does what is decided there with their
 * hands (takes a passenger and puts it in the barge, carries the salamander to the water, points the way) instead of
 * choosing among buttons of text. Who lives there says what is wanted and answers in a line with their voice; what
 * was done wrong is seen and said by the world. The role of the student is a way of looking (the lens of the sage, the
 * ear of the healer, the trail of the scout, the firm hand of the protector) with a clue of its own for each thing, and
 * a way out of its own where the scene has one.
 *
 * Only the way it is played changes: the nodes, their events and the server are those of the scene (scene.js asks
 * this player for the nodes it plays, and keeps the events, the flags, the branches, the ending and what is won). A
 * scene is played here when it brings its place (`adventure` in the scene: the painting, the ground, who speaks, and for
 * each decision how it is played) and every decision on its way has its play; if not, as always.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;
  var t = T.t;
  var esc = T.esc;

  /** @var {number} Width and height of the pictures of the places. */
  var W = 1440;
  var H = 810;
  /** @var {Object} The ways of looking of each role: its icon, and who says what it finds (the hero, or the being). */
  var VERBS = {
    sage: {icon: 'lens', who: 'hero'},
    healer: {icon: 'cheer', who: 'being'},
    scout: {icon: 'trail', who: 'hero'},
    protector: {icon: 'shield', who: 'hero'}
  };
  /** @var {Object} How each node type is played here. */
  var PLAYS = {classify: ['board'], choice: ['rescue', 'route']};

  /**
   * One of the ways of saying something, not the one said last time.
   *
   * @param {string|string[]} ways
   * @param {Object} memory Where the last one of each list is kept.
   * @param {string} name
   * @return {string}
   */
  function oneOf(ways, memory, name) {
    if (!Array.isArray(ways)) {
      return ways || '';
    }
    if (ways.length < 2) {
      return ways[0] || '';
    }
    var i;
    do {
      i = Math.floor(Math.random() * ways.length);
    } while (i === memory[name]);
    memory[name] = i;
    return ways[i];
  }

  /**
   * A colour of the engine from its three parts.
   *
   * @param {number} r
   * @param {number} g
   * @param {number} b
   * @return {number}
   */
  function rgb(r, g, b) {
    return (r << 16) | (g << 8) | b;
  }

  function point(p) {
    return {x: p[0], y: p[1]};
  }

  function distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
  }

  /**
   * A sound of the art, if the pack has it; else the one drawn by the game, if any.
   *
   * @param {string} name
   * @param {string} [fallback]
   */
  function sound(name, fallback) {
    if (T.fx && !T.fx.sound(name) && fallback) {
      T.fx.sound(fallback);
    }
  }

  T.adventure = {
    /**
     * Whether a scene can be played in its place here: it brings it, the device moves and draws, and every decision on
     * its way has its play (after a branch, only endings).
     *
     * @param {Object} sc The scene.
     * @return {boolean}
     */
    can: function(sc) {
      var q = sc && sc.adventure;
      if (!q || !q.nodes || !q.walk || !T.stage || !T.stage.available() || !T.walk || !T.walk.parts ||
          !T.artUrl(['scene.' + q.place])) {
        return false;
      }
      var ok = true;
      var seen = {};
      var visit = function(id, past) {
        if (!ok || !id || seen[id]) {
          return;
        }
        seen[id] = true;
        var n = sc.nodes[id];
        if (!n) {
          ok = false;
          return;
        }
        if (n.type === 'ending') {
          return;
        }
        if (n.type === 'branch') {
          if (n.pick) {
            ok = false;
            return;
          }
          (n.rules || []).forEach(function(rule) {
            visit(rule.next, true);
          });
          visit(n.else, true);
          return;
        }
        var play = (q.nodes[id] || {}).play;
        if (past || (n.type !== 'story' && (PLAYS[n.type] || []).indexOf(play) < 0)) {
          ok = false;
          return;
        }
        visit(n.next, false);
        (n.options || []).forEach(function(o) {
          visit(o.next, false);
        });
      };
      visit(sc.start, false);
      return ok;
    },

    /**
     * Opens the place of a scene.
     *
     * @param {Object} o data (the scene as get_scene gives it, parsed), sc (the scene), world, tx (a text of the scene in
     *     the skin of the mission), has (whether what a thing requires is there), name (of the student, as the scene
     *     says it), creature (its name), onlist (the
     *     student goes to the scene as it was), onclose (the place was left before the end).
     * @return {Promise} The player: plays(id, n), play(id, n, run, go), finish(n, run, done), close().
     */
    open: function(o) {
      var sc = o.sc;
      var q = sc.adventure;
      var data = o.data;
      var world = o.world || {};
      var c = world.character || {};
      var role = data.role || c.role || '';
      var P = T.walk.parts;
      var place = {walk: q.walk, start: q.start};
      var said = {};
      var moment = null;
      var S = null;
      var ready = null;
      var ended = false;
      // What the hero can do now: walk (explore), nothing (busy), or nothing at all (over).
      var state = {phase: 'busy', solved: false};
      var tx = o.tx;
      var speakerName = (data.speakers || {})[q.speaker.who] || q.speaker.who;
      var petName = o.creature || t('app_stage_creature');

      /**
       * Who says what: in the box at the bottom and, if the pack has it, in their voice; their figure talks while it
       * sounds. What was still being said goes.
       *
       * @param {string} who The speaker of the place, hero, pet, or a being (being:<id>).
       * @param {string} text
       * @param {Function} [end] When it has been said.
       */
      var say = function(who, text, end) {
        text = tx(text);
        if (!text || !moment) {
          if (end) {
            end();
          }
          return;
        }
        var name;
        var key;
        var figure = null;
        var hash = T.lineKey(text).replace(/^voice\.line\./, '');
        if (who === 'hero') {
          name = o.name || t('app_adventure_you');
          key = T.heroKey(c, world.classstage, text);
        } else if (who === 'pet') {
          name = petName;
          key = 'voice.say.creature.' + hash;
          figure = S && S.pet;
        } else if (who.indexOf('being:') === 0) {
          var id = who.slice(6);
          var b = S && S.beings[id];
          name = b ? b.name : id;
          key = 'voice.say.pasajero-' + id + '.' + hash;
          figure = b;
        } else {
          name = speakerName;
          key = 'voice.say.' + who + '.' + hash;
          figure = S && S.speaker;
        }
        moment.hush();
        moment.say(name, text, true);
        // Once, whether it was said aloud or only shown.
        var over = false;
        var done = function() {
          if (!over) {
            over = true;
            if (end) {
              end();
            }
          }
        };
        var spoke = false;
        spoke = !!(T.fx && key && T.fx.voice(key, function() {
          // A voice that could not sound says so at once: then the words wait to be read.
          if (spoke) {
            done();
          }
        }, function(seconds) {
          if (figure && figure.puppet) {
            T.cast.says(figure, seconds * 1000);
          } else if (figure && figure.sprite) {
            talkBob(figure, seconds * 1000);
          }
        }));
        if (!spoke) {
          // Without a voice, long enough to read it.
          setTimeout(done, Math.max(1600, text.length * 55));
        }
        if (who === 'hero' && S && S.hero) {
          T.cast.says(S.hero, Math.max(1200, text.length * 50));
        }
      };

      /**
       * A being that is a picture talks: it bobs while its voice sounds.
       *
       * @param {Object} b
       * @param {number} ms
       */
      var talkBob = function(b, ms) {
        if (!S || moment.still) {
          return;
        }
        S.tweens.add({targets: b.sprite, scaleY: b.sprite.scaleY * 1.06, duration: 140, yoyo: true,
          repeat: Math.max(1, Math.round(ms / 280)), ease: 'Sine.easeInOut'});
      };

      /**
       * What is wanted now, up there: in words, and how much of it is done.
       *
       * @param {string} goal
       * @param {Array} [dots] true for what is done.
       */
      var goal = function(text, dots) {
        if (!moment) {
          return;
        }
        if (!text) {
          moment.hud('');
          return;
        }
        moment.hud('<span class="adventure-goal"><b>' + esc(tx(text)) + '</b>' + (dots && dots.length ?
          '<span class="stage-prints" role="img" aria-label="' + esc(t('app_adventure_done', dots.filter(Boolean).length + '/' +
          dots.length)) + '">' + dots.map(function(on) {
            return '<i class="' + (on ? 'on' : '') + '"></i>';
          }).join('') + '</span>' : '') + '</span>');
      };

      /** The button of the way of looking of the role, at hand; nothing for who has no role. */
      var verbButton = function(on, label) {
        var box = moment.layer.querySelector('[data-adventure-verb]');
        if (!box && VERBS[role]) {
          box = document.createElement('div');
          box.className = 'adventure-verb';
          box.setAttribute('data-adventure-verb', '');
          box.innerHTML = '<button type="button" class="btn btn-main" data-verb>' + T.icon(VERBS[role].icon) + '<span>' +
            esc(t('app_adventure_verb_' + role)) + '</span></button>';
          moment.layer.querySelector('.stage-ui').appendChild(box);
          box.querySelector('[data-verb]').onclick = function() {
            if (S && S.onVerb) {
              S.onVerb();
            }
          };
        }
        if (box) {
          box.hidden = !on;
          box.classList.toggle('armed', !!label);
          box.querySelector('[data-verb]').setAttribute('aria-pressed', label ? 'true' : 'false');
        }
      };

      var player = {
        /**
         * Whether this player plays a node: the decisions it has a play for, and what is told (said by who lives here,
         * or nothing: the place tells it).
         *
         * @param {string} id
         * @param {Object} n
         * @return {boolean}
         */
        plays: function(id, n) {
          if (ended) {
            return false;
          }
          if (n.type === 'story') {
            return true;
          }
          return (PLAYS[n.type] || []).indexOf((q.nodes[id] || {}).play) >= 0;
        },

        /**
         * Plays a node, and goes on to the next one.
         *
         * @param {string} id
         * @param {Object} n
         * @param {Object} run Of scene.js: events and flags.
         * @param {Function} go To the next node.
         */
        play: function(id, n, run, go) {
          ready.then(function() {
            var how = q.nodes[id] || {};
            if (n.type === 'story') {
              tell(how.say, function() {
                go(n.next);
              });
            } else if (how.play === 'board') {
              board(id, n, how, run, go);
            } else if (how.play === 'rescue') {
              rescue(id, n, how, run, go);
            } else {
              route(id, n, how, run, go);
            }
          });
        },

        /**
         * The end: the boat sails with everybody on it, who speaks says how it went, and the place goes.
         *
         * @param {string} id
         * @param {Object} n The ending.
         * @param {Function} done Whether the ending was told here, and the place where it ended up (a painting).
         */
        finish: function(id, n, done) {
          ended = true;
          ready.then(function() {
            sail(id, n, done);
          });
        },

        /** The place goes, now. */
        close: function() {
          ended = true;
          if (moment) {
            var m = moment;
            moment = null;
            m.close();
          }
        }
      };

      /**
       * What is told, said by who lives here, one line after another; then on.
       *
       * @param {string|string[]} lines
       * @param {Function} next
       */
      var tell = function(lines, next) {
        lines = [].concat(lines || []);
        var i = 0;
        var one = function() {
          if (i >= lines.length) {
            next();
            return;
          }
          say(q.speaker.who, lines[i++], function() {
            setTimeout(one, 250);
          });
        };
        one();
      };

      /* ---------- The barge: who goes where ---------- */

      /**
       * A classify played as boarding: each thing waits on the shore, and the hero takes it to its place (dragging it,
       * or touching it and then its place). In its place, it stays; in another, it complains and goes back.
       *
       * @param {string} id
       * @param {Object} n
       * @param {Object} how
       * @param {Object} run
       * @param {Function} go
       */
      var board = function(id, n, how, run, go) {
        var right = {};
        var tried = {};
        var used = {};
        var items = n.items.filter(function(it) {
          return o.has(it.requires) && !(data.aided && it.hard);
        });
        var dots = function() {
          return items.map(function(it) {
            return !!right[it.id];
          });
        };
        goal(how.goal, dots());
        items.forEach(function(it) {
          S.addBeing(it, how);
        });
        // The creature runs to the first of them and waits there: they are to be taken.
        var first = items[0] && S.beings[items[0].id];
        if (first) {
          S.pet.mode = 'lead';
          S.pet.goal = {x: first.x - 60, y: Math.max(first.y + 14, 600)};
        }
        S.spots = how.spots;
        S.mode = {kind: 'board'};
        state.phase = 'explore';
        S.last = S.time.now;
        verbButton(true, false);
        // If nothing happens for a while, the help that comes by itself: the first note of the node, free, once.
        var idle = setInterval(function() {
          if (!S || ended) {
            clearInterval(idle);
            return;
          }
          if (S.time.now - S.last > 18000 && !run.idleHint && n.hints && n.hints.length) {
            run.idleHint = true;
            run.events.push({n: id, hint: 0, idle: 1});
            S.hop(S.pet);
            say(q.speaker.who, how.idle || n.hints[0]);
          }
        }, 1000);
        S.onPlace = function(b, bin) {
          run.events.push({n: id, item: b.id, bin: bin});
          var first = !tried[b.id];
          tried[b.id] = true;
          if (bin === b.item.bin) {
            right[b.id] = true;
            b.first = first;
            S.settle(b, bin);
            goal(how.goal, dots());
            var all = items.every(function(it) {
              return right[it.id];
            });
            if (all) {
              clearInterval(idle);
              state.phase = 'busy';
              verbButton(false);
              S.mode = null;
              S.time.delayedCall(700, function() {
                S.cheer(true);
                say(q.speaker.who, oneOf(how.done, said, 'done'), function() {
                  go(n.next);
                });
              });
            } else if (Math.random() < 0.6) {
              S.time.delayedCall(500, function() {
                say(q.speaker.who, oneOf(how.right, said, 'right'));
              });
            }
          } else {
            S.refuse(b, bin, function() {
              var times = (b.wrong = (b.wrong || 0) + 1);
              // The complaint of the thing; the second time, who lives here says something too.
              say('being:' + b.id, (how.protest || {})[b.id] || tx(b.item.feedback && b.item.feedback[bin] || ''),
                function() {
                  if (times > 1) {
                    say(q.speaker.who, oneOf(how.wrong, said, 'wrong'));
                  }
                });
            });
          }
        };
        S.onVerb = function(b) {
          b = b || S.carried;
          if (!b) {
            // Armed: the next thing touched is looked at.
            S.armed = !S.armed;
            verbButton(true, S.armed);
            if (S.armed) {
              say('pet', t('app_adventure_verb_whom'));
            }
            return;
          }
          S.armed = false;
          verbButton(true, false);
          var line = ((how.verbs || {})[role] || {})[b.id];
          if (!line) {
            return;
          }
          if (!used[b.id]) {
            used[b.id] = true;
            run.events.push({n: id, verb: b.id});
          }
          S.look(b, role, function() {
            say(VERBS[role].who === 'being' ? 'being:' + b.id : 'hero', line);
          });
        };
      };

      /* ---------- Someone in trouble: what the hero does for them ---------- */

      /**
       * A choice played on a being in trouble: the hero takes it to a place of the scene (each place, an option), or
       * uses on it the way of looking of their role, which is the option of the role where the scene has one.
       *
       * @param {string} id
       * @param {Object} n
       * @param {Object} how
       * @param {Object} run
       * @param {Function} go
       */
      var rescue = function(id, n, how, run, go) {
        var b = S.beings[how.being];
        if (!b) {
          var board0 = Object.keys(q.nodes).filter(function(k) {
            return q.nodes[k].play === 'board';
          })[0];
          var item = ((sc.nodes[board0] || {}).items || []).filter(function(it) {
            return it.id === how.being;
          })[0] || {id: how.being, label: how.being};
          b = S.addBeing(item, q.nodes[board0] || {});
          b.bin = item.bin;
          b.state = 'aboard';
        }
        var options = {};
        n.options.forEach(function(op) {
          options[op.id] = op;
        });
        var mine = (how.verbs || {})[role];
        if (mine && (!options[mine] || !o.has(options[mine].requires))) {
          mine = null;
        }
        state.phase = 'busy';
        S.trouble(b, how.at, function() {
          goal(how.goal);
          say(q.speaker.who, how.say, function() {
            state.phase = 'explore';
            S.spots = {};
            Object.keys(how.spots || {}).forEach(function(k) {
              if (options[k]) {
                S.spots[k] = how.spots[k].at;
              }
            });
            S.showSpots(how.spots);
            S.mode = {kind: 'rescue', being: b};
            verbButton(!!mine, false);
          });
        });
        var choose = function(option, then) {
          state.phase = 'busy';
          S.mode = null;
          S.carried = null;
          S.glowSpots(false);
          verbButton(false);
          S.hideSpots();
          var op = options[option];
          run.events.push({n: id, pick: [option]});
          (op.effects || []).forEach(function(e) {
            if (e.flag && run.flags.indexOf(e.flag) < 0) {
              run.flags.push(e.flag);
            }
          });
          then(op, function() {
            var line = (how.lines || {})[option];
            var who = (how.who || {})[option] || q.speaker.who;
            say(who, line || op.say || '', function() {
              S.recover(b, function() {
                say(q.speaker.who, how.back, function() {
                  go(op.next || n.next);
                });
              });
            });
          });
        };
        S.onPlace = function(being, spot) {
          if (being !== b || !options[spot]) {
            return;
          }
          choose(spot, function(op, after) {
            S.react(b, spot, op.correct !== false, after);
          });
        };
        S.onVerb = function() {
          if (!mine) {
            return;
          }
          S.walkNear(b, function() {
            choose(mine, function(op, after) {
              S.look(b, role, after);
            });
          });
        };
      };

      /* ---------- Which way ---------- */

      /**
       * A choice played as a way to take: each option, a light on the water where it goes; the hero points one.
       *
       * @param {string} id
       * @param {Object} n
       * @param {Object} how
       * @param {Object} run
       * @param {Function} go
       */
      var route = function(id, n, how, run, go) {
        var ways = n.options.filter(function(op) {
          return how.ways && how.ways[op.id] && o.has(op.requires);
        });
        state.phase = 'busy';
        goal(how.goal || n.prompt);
        say(q.speaker.who, how.say || n.prompt, function() {
          S.showWays(ways.map(function(op) {
            return {id: op.id, at: how.ways[op.id].at, label: tx(how.ways[op.id].label || op.label)};
          }), function(way) {
            var op = ways.filter(function(x) {
              return x.id === way;
            })[0];
            run.events.push({n: id, pick: [way]});
            (op.effects || []).forEach(function(e) {
              if (e.flag && run.flags.indexOf(e.flag) < 0) {
                run.flags.push(e.flag);
              }
            });
            S.way = how.ways[way];
            S.wayLine = (how.lines || {})[way] || '';
            go(op.next || n.next);
          });
        });
      };

      /* ---------- The end ---------- */

      var sail = function(id, n, done) {
        state.phase = 'busy';
        verbButton(false);
        goal('');
        var line = (q.nodes[id] || {}).say;
        S.embark(function() {
          if (S.wayLine) {
            say(q.speaker.who, S.wayLine);
          }
          // Who went first where they did not belong complains all the way (the ending says how it went).
          S.grumble(n.outcome !== 'success');
          S.depart(function() {
            say(q.speaker.who, line || '', function() {
              S.cameras.main.fadeOut(moment.still ? 1 : 700, 15, 33, 49);
              setTimeout(function() {
                player.close();
                // Where it ended up: the painting the ending is told in.
                done(!!line, q.arrive || '');
              }, moment.still ? 10 : 750);
            });
          });
        });
      };

      ready = new Promise(function(resolve, reject) {
        T.stage.open({
          title: tx(q.title || sc.title),
          onlist: function() {
            ended = true;
            if (o.onlist) {
              o.onlist();
            }
          },
          onclose: function() {
            if (T.fx) {
              T.fx.hush();
              T.fx.moment('');
              T.fx.figureSays = null;
            }
            if (!ended && o.onclose) {
              ended = true;
              o.onclose();
            }
          },
          scene: {
            preload: function() {
              var L = this;
              var key = 'scene.' + q.place;
              L.load.image('bg', T.artUrl([key + '.empty', key]));
              L.loose = !!(T.artUrl([key + '.empty']) && T.artUrl([key + '.barge']) && T.artUrl([key + '.barge.front']));
              if (L.loose) {
                L.load.image('barge', T.artUrl([key + '.barge']));
                L.load.image('barge.front', T.artUrl([key + '.barge.front']));
                if (T.artUrl([key + '.dock'])) {
                  L.load.image('dock', T.artUrl([key + '.dock']));
                }
              }
              L.cast = T.cast.load(L, c);
              // Who lives here: their puppet or model, if the pack brings it; else their figure.
              var rig = T.puppet ? T.puppet.find('figure.' + q.speaker.who, 'speaker') : null;
              L.speakerRig = rig;
              if (rig) {
                T.puppet.load(L, 'puppet.speaker', rig);
              } else if (T.artUrl(['figure.' + q.speaker.who])) {
                L.load.image('speaker', T.artUrl(['figure.' + q.speaker.who]));
              }
              // The things of the scene that are looked at, carried and placed.
              Object.keys(q.nodes).forEach(function(nid) {
                var how = q.nodes[nid];
                var node = sc.nodes[nid] || {};
                (node.items || []).forEach(function(it) {
                  var url = T.artUrl([].concat((how.pictures || {})[it.id] || [], it.art || [], data.pictures[it.id] || [],
                    'being.' + it.id, 'thing.' + it.id));
                  if (url && !L.textures.exists('being.' + it.id)) {
                    L.load.image('being.' + it.id, url);
                  }
                });
              });
              if (T.artUrl(['critter.firefly'])) {
                L.load.image('firefly', T.artUrl(['critter.firefly']));
              }
              if (T.fx) {
                // What will be said here, ahead.
                T.fx.ambient(T.fx.place(q.ambient || q.place), q.ambient || q.place);
              }
            },
            create: function() {
              S = this;
              moment = S.stage;
              try {
                build(S, resolve);
              } catch (e) {
                reject(e);
              }
            },
            update: function(time, delta) {
              if (S && S.tick) {
                S.tick(time, delta);
              }
            }
          }
        }).catch(reject);
      });

      /**
       * The place, once loaded: the painting, the boat, who lives there, the hero, the creature and the beings.
       *
       * @param {Object} S The scene of the engine.
       * @param {Function} resolve
       */
      var build = function(S, resolve) {
        var still = moment.still;
        T.cast.textures(S);
        S.beings = {};
        S.last = 0;
        S.add.image(0, 0, 'bg').setOrigin(0).setDepth(0);
        // The boat, loose from the painting: it rocks, takes the passengers inside and sails.
        var boat = q.boat || {};
        var bc = point(boat.center || [W / 2, H / 2]);
        S.boat = S.add.container(bc.x, bc.y).setDepth(boat.depth || 400);
        if (S.loose) {
          S.boatBack = S.add.image(-bc.x, -bc.y, 'barge').setOrigin(0);
          S.boatFront = S.add.image(-bc.x, -bc.y, 'barge.front').setOrigin(0);
          // Its shadow on the water, under the hull: it sits on the lake, docked or sailing.
          var sh = boat.shadow || [0, 108, 900, 40];
          var shadow = S.add.ellipse(sh[0], sh[1], sh[2], sh[3], 0x0b1a2a, 0.32);
          S.boat.add([shadow, S.boatBack, S.boatFront]);
          if (S.textures.exists('dock')) {
            S.add.image(0, 0, 'dock').setOrigin(0).setDepth((boat.depth || 400) + 50);
          }
          if (!still) {
            S.rock = S.tweens.add({targets: S.boat, y: bc.y + 2.5, angle: 0.35, duration: 1900, yoyo: true, repeat: -1,
              ease: 'Sine.easeInOut'});
          }
        }
        // The hero and the creature.
        var start = point(q.start);
        if (T.fx) {
          T.fx.figureSays = function(which, ms) {
            T.cast.says(which === 'hero' ? S.hero : S.pet, ms);
          };
        }
        var pet = T.cast.creature(c);
        S.hero = T.cast.actor(S, 'hero', start.x, start.y, q.hero || 250, still);
        T.cast.aura(S, S.hero, c.aura, still);
        S.pet = T.cast.actor(S, 'pet', start.x - 80, start.y + 12, q.pet || 124, still, pet.hue, pet.paint);
        S.pet.mode = 'follow';
        // Who lives here, where they stand.
        var sp = q.speaker;
        var puppet = S.speakerRig ? T.puppet.make(S, 'puppet.speaker', still) : null;
        var sprite = puppet ? puppet.container : S.textures.exists('speaker') ? S.add.image(0, 0, 'speaker')
          .setOrigin(0.5, 860 / 896) : null;
        if (sprite) {
          S.speaker = {x: sp.at[0], y: sp.at[1], sprite: sprite, puppet: puppet, shadow: S.add.ellipse(0, 0, 10, 10,
            0x0b1a10, 0.32), base: (sp.tall || 170) / (sprite.height || 896), walk: 0, moving: false, facing: sp.face || 1,
            lift: 0, tilt: 0, pose: null};
          if (puppet) {
            puppet.play('greet', true);
          }
        }
        // Effects.
        S.dust = S.add.particles(0, 0, 'dot', {lifespan: 520, speed: {min: 8, max: 36}, angle: {min: 200, max: 340},
          scale: {start: 0.45, end: 1.3}, alpha: {start: 0.3, end: 0}, tint: 0xd8c79f, emitting: false});
        S.sparks = S.add.particles(0, 0, 'spark', {lifespan: 800, speed: {min: 60, max: 200}, scale: {start: 0.8, end: 0},
          alpha: {start: 1, end: 0}, tint: [0xfff1bd, 0xffc857, 0x9fe3ff], gravityY: 180, emitting: false}).setDepth(4500);
        S.splash = S.add.particles(0, 0, 'dot', {lifespan: 700, speed: {min: 70, max: 190}, angle: {min: 220, max: 320},
          scale: {start: 0.7, end: 0.1}, alpha: {start: 0.9, end: 0}, tint: [0x9fe3ff, 0xd8f4ff, 0x5fb6e6], gravityY: 420,
          emitting: false}).setDepth(4500);
        S.hearts = S.add.particles(0, 0, 'heart', {lifespan: 1300, speed: {min: 30, max: 90}, angle: {min: 240, max: 300},
          scale: {start: 0.9, end: 0.3}, alpha: {start: 1, end: 0}, tint: [0xe8508f, 0xff8fbf], gravityY: -40,
          emitting: false}).setDepth(4700);
        S.emotes = [];
        S.keys = S.input.keyboard.createCursorKeys();
        // The camera: the whole place if it fits; if not, it follows what happens, softly.
        S.camAt = {x: W / 2, y: H / 2};
        S.fit = function() {
          var cam = S.cameras.main;
          S.zoom = Math.max(S.scale.width / W, S.scale.height / H);
          cam.setZoom(S.zoom);
          cam.setBounds(0, 0, W, H);
        };
        S.fit();
        S.scale.on('resize', S.fit);
        S.cameras.main.centerOn(W / 2, H / 2);

        /** A round bubble over a head: «!», «?», a heart. */
        S.emote = function(who, symbol) {
          var text = S.add.text(0, 0, symbol, {fontFamily: 'Lexend, sans-serif', fontSize: '26px', fontStyle: 'bold',
            color: '#fff1bd'}).setOrigin(0.5);
          var w = Math.max(46, text.width + 28);
          var frame = S.add.graphics();
          frame.fillStyle(0x0f2131, 0.92).fillRoundedRect(-w / 2, -22, w, 44, 22);
          frame.lineStyle(2.5, 0xffc857, 1).strokeRoundedRect(-w / 2, -22, w, 44, 22);
          frame.fillStyle(0x0f2131, 0.92).fillTriangle(-7, 20, 7, 20, 0, 31);
          var bubble = S.add.container(0, 0, [frame, text]).setDepth(4800).setScale(still ? 1 : 0);
          var em = {bubble: bubble, who: who, rise: 0};
          S.emotes.push(em);
          if (!still) {
            S.tweens.add({targets: bubble, scale: 1, duration: 220, ease: 'Back.easeOut'});
          }
          S.tweens.add({targets: em, rise: 16, delay: 1100, duration: 400});
          S.tweens.add({targets: bubble, alpha: 0, delay: 1100, duration: 400, onComplete: function() {
            bubble.destroy();
            S.emotes = S.emotes.filter(function(x) {
              return x !== em;
            });
          }});
        };
        S.hop = function(who) {
          if (!who || T.cast.act(who, who === S.pet ? 'hop' : 'surprise') || still) {
            return;
          }
          S.tweens.add({targets: who, lift: -22, duration: 160, yoyo: true, ease: 'Sine.easeOut'});
        };
        S.cheer = function(big) {
          T.cast.act(S.hero, 'celebrate');
          S.hop(S.pet);
          S.pet.pose = 'happy';
          S.time.delayedCall(1600, function() {
            S.pet.pose = null;
          });
          if (big) {
            S.hearts.explode(still ? 2 : 8, S.pet.x, S.pet.y - 80);
            sound('joy', 'heart');
          }
        };

        /* The beings: they wait on the shore (or in the water), go with the hero and jump into the boat. */

        /**
         * How tall a being stands where it is: further back, smaller.
         *
         * @param {Object} b
         * @param {number} y
         * @return {number}
         */
        var tallAt = function(b, y) {
          return b.tall * P.depthOf(place, Math.max(y, place.walk.reduce(function(m, p) {
            return Math.min(m, p[1]);
          }, H)));
        };
        S.addBeing = function(it, how) {
          if (S.beings[it.id]) {
            return S.beings[it.id];
          }
          var wait = (how.wait || {})[it.id] || [W / 2, 640];
          var key = S.textures.exists('being.' + it.id) ? 'being.' + it.id : null;
          if (!key) {
            T.cast.figure(S, 'being.' + it.id, 0x2b2418);
            key = 'being.' + it.id;
          }
          var sprite = S.add.image(wait[0], wait[1], key).setOrigin(0.5, 1);
          var b = {id: it.id, item: it, name: tx(it.label).replace(/^(un|una|el|la) /, ''), sprite: sprite,
            home: {x: wait[0], y: wait[1]}, water: wait[2] === 'water', tall: ((how.tall || {})[it.id] || 80),
            x: wait[0], y: wait[1], state: 'wait', ripple: null};
          b.name = b.name.charAt(0).toUpperCase() + b.name.slice(1);
          S.size(b, b.y);
          sprite.setDepth(b.y);
          if (b.water) {
            b.ripple = S.add.ellipse(b.x, b.y - 6, b.sprite.displayWidth * 0.9, 16).setStrokeStyle(2, 0xd8f4ff, 0.7)
              .setDepth(b.y - 1);
          }
          // Each one breathes in its own time.
          if (!still) {
            b.breath = S.tweens.add({targets: sprite, scaleY: sprite.scaleY * 1.035, duration: 900 + Math.random() * 500,
              yoyo: true, repeat: -1, ease: 'Sine.easeInOut', delay: Math.random() * 600});
          }
          S.beings[it.id] = b;
          return b;
        };
        S.size = function(b, y, k) {
          var h = (k || 1) * tallAt(b, y);
          var s = h / b.sprite.height;
          b.sprite.setScale(s);
          return s;
        };
        /**
         * The being under a point of the place, of those that wait (and the one in trouble).
         *
         * @param {number} x
         * @param {number} y
         * @return {Object|null}
         */
        S.beingAt = function(x, y) {
          var found = null;
          var best = Infinity;
          Object.keys(S.beings).forEach(function(k) {
            var b = S.beings[k];
            if (b.state !== 'wait' && b.state !== 'trouble') {
              return;
            }
            var bounds = b.sprite.getBounds();
            var pad = 24;
            if (x > bounds.x - pad && x < bounds.right + pad && y > bounds.y - pad && y < bounds.bottom + pad) {
              var d = Math.hypot(x - bounds.centerX, y - bounds.centerY);
              if (d < best) {
                best = d;
                found = b;
              }
            }
          });
          return found;
        };
        /**
         * The place of the boat (or of the scene) under a point: within a box around each one.
         *
         * @param {number} x
         * @param {number} y
         * @return {string|null}
         */
        S.spotAt = function(x, y) {
          var found = null;
          var best = Infinity;
          Object.keys(S.spots || {}).forEach(function(k) {
            var s = S.spots[k];
            var dx = Math.abs(x - s[0]);
            var dy = y - s[1];
            if (dx < 68 && dy > -80 && dy < 70) {
              var d = dx + Math.abs(dy);
              if (d < best) {
                best = d;
                found = k;
              }
            }
          });
          return found;
        };

        /** Where the hero stands to hand something to a place: on the shore, the nearest to it. */
        var shoreFor = function(x, y) {
          return P.onGround(place, x, Math.max(y + 300, 600));
        };
        S.walkTo = function(p, arrive) {
          S.hero.target = p;
          S.hero.arrive = arrive;
          if (Math.abs(p.x - S.hero.x) > 4) {
            S.hero.facing = Math.sign(p.x - S.hero.x);
          }
          var ripple = S.add.ellipse(p.x, p.y, 34, 13).setStrokeStyle(2, 0xfff1bd, 0.9).setDepth(3);
          S.tweens.add({targets: ripple, scale: 2.6, alpha: 0, duration: still ? 1 : 520, onComplete: function() {
            ripple.destroy();
          }});
        };
        S.walkNear = function(b, arrive) {
          var p = P.onGround(place, b.x - 70 * (b.x > S.hero.x ? 1 : -1), b.y + 20);
          if (distance(S.hero, p) < 40) {
            arrive();
            return;
          }
          S.walkTo(p, arrive);
        };

        /** The hero takes a being: it goes with them, at their side (in the water, along the shore). */
        S.take = function(b) {
          if (S.carried && S.carried !== b) {
            S.putBack(S.carried);
          }
          S.carried = b;
          b.state = 'carried';
          S.pet.mode = 'follow';
          sound('pick', 'tap');
          S.hop(S.pet);
          if (!still) {
            // A little jump of joy: up and squashed back.
            b.sprite.setScale(b.sprite.scaleX * 1.25, b.sprite.scaleY * 0.8);
            S.tweens.add({targets: b, lift: -26, duration: 160, yoyo: true, ease: 'Quad.easeOut'});
          }
          S.glowSpots(true);
          // The hero names what they took.
          say('hero', b.item.label);
        };
        S.putBack = function(b) {
          b.state = 'jump';
          S.jump(b, b.home.x, b.home.y, null, function() {
            b.state = 'wait';
          });
          if (S.carried === b) {
            S.carried = null;
            S.glowSpots(false);
          }
        };

        /**
         * A being jumps in an arc to a point of the place, or into the boat.
         *
         * @param {Object} b
         * @param {number} x
         * @param {number} y
         * @param {number|null} inBoat How much smaller it is inside the boat (null: on the shore).
         * @param {Function} done
         */
        S.jump = function(b, x, y, inBoat, done) {
          var from = {x: b.x, y: b.y + (b.lift || 0)};
          b.lift = 0;
          if (b.sprite.parentContainer) {
            // Out of the boat, where it was: the container gives it back to the place.
            var wx0 = S.boat.x + b.sprite.x * S.boat.scaleX;
            var wy0 = S.boat.y + b.sprite.y * S.boat.scaleY;
            S.boat.remove(b.sprite);
            b.sprite.setPosition(wx0, wy0);
            from = {x: wx0, y: wy0};
          }
          var s0 = b.sprite.scaleX;
          var s1 = inBoat ? (b.tall * inBoat) / b.sprite.height : tallAt(b, y) / b.sprite.height;
          var arc = Math.max(90, Math.abs(from.y - y) * 0.6);
          var k = {v: 0};
          if (b.ripple) {
            b.ripple.setVisible(false);
          }
          b.sprite.setDepth(4400);
          sound('hop', 'tap');
          S.tweens.add({targets: k, v: 1, duration: still ? 1 : 560, ease: 'Sine.easeInOut', onUpdate: function() {
            var v = k.v;
            b.x = from.x + (x - from.x) * v;
            b.y = from.y + (y - from.y) * v;
            b.sprite.setPosition(b.x, b.y - Math.sin(v * Math.PI) * arc);
            b.sprite.setScale(s0 + (s1 - s0) * v);
          }, onComplete: function() {
            b.sprite.setPosition(x, y);
            b.sprite.setDepth(y);
            if (b.ripple && !inBoat) {
              b.ripple.setPosition(x, y - 6).setVisible(b.water && Math.abs(x - b.home.x) < 2);
            }
            if (!still) {
              // It lands: squashed, and back.
              var sx = b.sprite.scaleX;
              var sy = b.sprite.scaleY;
              b.sprite.setScale(sx * 1.3, sy * 0.7);
              S.tweens.add({targets: b.sprite, scaleX: sx, scaleY: sy, duration: 180, ease: 'Back.easeOut'});
            }
            if (done) {
              done();
            }
          }});
        };

        /**
         * Into the boat: the being goes in, and sits behind the side of its place.
         *
         * @param {Object} b
         * @param {string} bin
         * @param {Function} landed
         */
        S.toBoat = function(b, bin, landed) {
          var spot = S.spots[bin];
          // Two in the same place sit side by side.
          var mates = Object.keys(S.beings).filter(function(k) {
            return S.beings[k].bin === bin && S.beings[k] !== b && S.beings[k].state === 'aboard';
          }).length;
          var x = spot[0] + (mates % 2 ? 26 : -12) * (mates ? 1 : 0);
          var inBoat = 0.5;
          var sit = b.tall * inBoat * 0.42;
          S.jump(b, x, spot[1] + sit, inBoat, function() {
            // Inside the boat: it rocks with it, between its back and its side.
            S.boat.addAt(b.sprite, S.boatFront ? S.boat.getIndex(S.boatFront) : S.boat.length);
            b.sprite.setPosition(x - S.boat.x, spot[1] + sit - S.boat.y);
            b.x = x;
            b.y = spot[1] + sit;
            landed();
          });
        };

        /** Its place: it stays inside, the boat answers with a light, and the hero and the creature are glad. */
        S.settle = function(b, bin) {
          S.carried = null;
          S.glowSpots(false);
          b.state = 'aboard';
          b.bin = bin;
          S.toBoat(b, bin, function() {
            var spot = S.spots[bin];
            S.sparks.explode(still ? 4 : 12, spot[0], spot[1] - 10);
            S.light(spot);
            sound('settle', 'chime');
            S.cheer(false);
          });
        };

        /**
         * Not its place: it jumps in, and out again at once, complaining, and back to where it waited.
         *
         * @param {Object} b
         * @param {string} bin
         * @param {Function} complain
         */
        S.refuse = function(b, bin, complain) {
          S.carried = null;
          S.glowSpots(false);
          b.state = 'jump';
          S.toBoat(b, bin, function() {
            sound('nope', 'wrong');
            S.emote(b, '!');
            complain();
            S.time.delayedCall(still ? 0 : 700, function() {
              // Out of the boat, a shake, and home.
              var wx = S.boat.x + b.sprite.x;
              var wy = S.boat.y + b.sprite.y;
              S.boat.remove(b.sprite);
              b.sprite.setPosition(wx, wy);
              b.x = wx;
              b.y = wy;
              if (!still) {
                S.tweens.add({targets: b.sprite, angle: {from: -8, to: 8}, duration: 70, yoyo: true, repeat: 3,
                  onComplete: function() {
                    b.sprite.setAngle(0);
                  }});
              }
              S.time.delayedCall(still ? 0 : 320, function() {
                S.jump(b, b.home.x, b.home.y, null, function() {
                  b.state = 'wait';
                  b.bin = null;
                });
              });
            });
          });
        };

        /** A warm light on a place of the boat, that comes and goes. */
        S.light = function(spot) {
          var glow = S.add.image(spot[0], spot[1] - 18, 'soft').setTint(0xffd27a).setBlendMode('ADD').setScale(2.6, 1.4)
            .setAlpha(0).setDepth(4300);
          S.tweens.add({targets: glow, alpha: 0.9, duration: 160, yoyo: true, hold: 260, onComplete: function() {
            glow.destroy();
          }});
        };

        /** The places of the boat, lit softly while a being goes with the hero. */
        S.rings = [];
        S.glowSpots = function(on) {
          S.rings.forEach(function(r) {
            r.destroy();
          });
          S.rings = [];
          if (!on) {
            return;
          }
          Object.keys(S.spots || {}).forEach(function(k) {
            var s = S.spots[k];
            var ring = S.add.ellipse(s[0], s[1] - 14, 96, 34).setStrokeStyle(3, 0xfff1bd, 0.85).setDepth(4300);
            S.rings.push(ring);
            if (!still) {
              S.tweens.add({targets: ring, alpha: {from: 0.95, to: 0.35}, duration: 700, yoyo: true, repeat: -1});
            }
          });
        };

        /**
         * What the lens shows of a being: the middle of its picture, twice as big, in a circle (a texture of its own:
         * the engine has no masks that every device draws).
         *
         * @param {Object} b
         * @param {number} r The radius, in the units of the place.
         * @return {string} The texture.
         */
        var lensOf = function(b, r) {
          var name = 'lens.' + b.id + '.' + Math.round(r);
          if (S.textures.exists(name)) {
            return name;
          }
          var source = S.textures.get(b.sprite.texture.key).getSourceImage();
          var canvas = S.textures.createCanvas(name, Math.round(r * 2), Math.round(r * 2));
          var ctx = canvas.getContext();
          ctx.fillStyle = '#f4ead2';
          ctx.beginPath();
          ctx.arc(r, r, r, 0, Math.PI * 2);
          ctx.fill();
          ctx.save();
          ctx.clip();
          // Twice as big as it stands now, its middle in the middle.
          var scale = b.sprite.scaleY * 2.3;
          var w = source.width * scale;
          var h = source.height * scale;
          ctx.drawImage(source, r - w / 2, r - h * 0.55, w, h);
          ctx.restore();
          canvas.refresh();
          return name;
        };

        /**
         * The way of looking of the role, on a being: what it shows, over it.
         *
         * @param {Object} b
         * @param {string} how sage, healer, scout or protector.
         * @param {Function} done
         */
        S.look = function(b, how, done) {
          var at = b.sprite.getBounds();
          var cx = at.centerX;
          var cy = at.centerY;
          S.walkNear(b, function() {
            S.hero.facing = Math.sign(b.x - S.hero.x) || 1;
            T.cast.act(S.hero, 'point');
            sound('look', 'chime');
            if (how === 'sage') {
              // The lens: the being, bigger, in a circle over it.
              var r = Math.max(70, Math.min(110, at.height * 0.9));
              var big = S.add.image(cx, cy - r * 0.4, lensOf(b, r)).setDepth(4600);
              var rim = S.add.circle(cx, cy - r * 0.4, r).setStrokeStyle(6, 0xc9a24a, 1).setDepth(4601);
              var bits = [big, rim];
              bits.forEach(function(x) {
                x.setAlpha(0);
              });
              S.tweens.add({targets: bits, alpha: 1, duration: 220});
              S.time.delayedCall(still ? 2600 : 3200, function() {
                S.tweens.add({targets: bits, alpha: 0, duration: 260, onComplete: function() {
                  bits.forEach(function(x) {
                    x.destroy();
                  });
                }});
              });
            } else if (how === 'healer') {
              // The ear: rings of sound from the being to the hero.
              for (var i = 0; i < 3; i++) {
                (function(k) {
                  var ring = S.add.ellipse(cx, cy, 30, 30).setStrokeStyle(3, 0x9be3c0, 0.9).setDepth(4600);
                  S.tweens.add({targets: ring, scale: 3.2, alpha: 0, delay: k * 260, duration: 900, onComplete: function() {
                    ring.destroy();
                  }});
                })(i);
              }
              S.hearts.explode(still ? 1 : 3, cx, cy - 20);
            } else if (how === 'scout') {
              // The trail: prints that light up from the being outwards.
              for (var j = 0; j < 6; j++) {
                (function(k) {
                  var print = S.add.ellipse(cx - 30 - k * 34, b.y + 6 + (k % 2) * 10, 18, 9, 0xffd27a, 0).setDepth(5);
                  S.tweens.add({targets: print, fillAlpha: 0.85, delay: k * 140, duration: 200, yoyo: true, hold: 1600,
                    onComplete: function() {
                      print.destroy();
                    }});
                })(j);
              }
            } else {
              // The firm hand: the hero holds it, and a light of trust goes round it.
              var shield = S.add.circle(cx, cy, Math.max(50, at.height * 0.6)).setStrokeStyle(5, 0x9fc6ff, 0.9)
                .setDepth(4600).setScale(0.6).setAlpha(0);
              S.tweens.add({targets: shield, scale: 1, alpha: 1, duration: 260, yoyo: true, hold: 1600, onComplete: function() {
                shield.destroy();
              }});
            }
            S.sparks.explode(still ? 2 : 6, cx, cy);
            if (done) {
              done();
            }
          });
        };

        /* Someone in trouble. */

        /**
         * A being of the boat comes out in trouble to a place of the shore: it dries, still, losing its colour.
         *
         * @param {Object} b
         * @param {number[]} at
         * @param {Function} done
         */
        S.trouble = function(b, at, done) {
          b.state = 'jump';
          S.jump(b, at[0], at[1], null, function() {
            b.state = 'trouble';
            b.home = {x: at[0], y: at[1]};
            S.emote(b, '…');
            if (!still) {
              S.tweens.addCounter({from: 0, to: 1, duration: 2600, onUpdate: function(tw) {
                var v = tw.getValue();
                var g = Math.round(255 - 70 * v);
                b.tint = rgb(Math.round(255 - 40 * v), g, Math.round(g * 0.9));
                b.sprite.setTint(b.tint);
              }});
            } else {
              b.tint = 0xd7c3a8;
              b.sprite.setTint(b.tint);
            }
            b.dry = true;
            done();
          });
        };
        S.showSpots = function(spots) {
          S.hideSpots();
          S.marks = Object.keys(spots || {}).map(function(k) {
            var s = spots[k];
            var tint = s.look === 'sun' ? 0xffc857 : 0x9fe3ff;
            var glow = S.add.image(s.at[0], s.at[1] - 10, 'soft').setTint(tint).setBlendMode('ADD').setScale(3.4, 1.3)
              .setAlpha(0.7).setDepth(4);
            if (!still) {
              S.tweens.add({targets: glow, alpha: {from: 0.45, to: 0.9}, duration: 1000, yoyo: true, repeat: -1});
            }
            return glow;
          });
        };
        S.hideSpots = function() {
          (S.marks || []).forEach(function(m) {
            m.destroy();
          });
          S.marks = [];
        };
        /**
         * What the place does to the being taken there: water, or the sun.
         *
         * @param {Object} b
         * @param {string} spot
         * @param {boolean} good
         * @param {Function} done
         */
        S.react = function(b, spot, good, done) {
          if (good) {
            S.splash.explode(still ? 4 : 16, b.x, b.y - 10);
            sound('splash', 'chime');
            done();
            return;
          }
          // The sun: it goes still and pale, and who lives here wets it in time.
          sound('nope', 'wrong');
          S.emote(b, '!');
          b.tint = 0xc9a27a;
          b.sprite.setTint(b.tint);
          if (!still) {
            S.tweens.add({targets: b.sprite, scaleY: b.sprite.scaleY * 0.85, duration: 500, yoyo: true});
          }
          S.time.delayedCall(still ? 0 : 900, function() {
            S.splash.explode(still ? 4 : 16, b.x, b.y - 10);
            sound('splash', 'chime');
            done();
          });
        };
        /** It gets its colour back, jumps for joy and goes back to its place in the boat. */
        S.recover = function(b, done) {
          b.dry = false;
          b.state = 'jump';
          if (still) {
            b.sprite.clearTint();
          } else {
            var from = b.tint || 0xffffff;
            var r0 = from >> 16 & 255;
            var g0 = from >> 8 & 255;
            var b0 = from & 255;
            S.tweens.addCounter({from: 0, to: 1, duration: 700, onUpdate: function(tw) {
              var v = tw.getValue();
              b.sprite.setTint(rgb(Math.round(r0 + (255 - r0) * v), Math.round(g0 + (255 - g0) * v),
                Math.round(b0 + (255 - b0) * v)));
            }, onComplete: function() {
              b.sprite.clearTint();
            }});
          }
          S.hearts.explode(still ? 2 : 6, b.x, b.y - 40);
          S.cheer(false);
          S.time.delayedCall(still ? 0 : 800, function() {
            S.spots = q.nodes[Object.keys(q.nodes).filter(function(k) {
              return q.nodes[k].play === 'board';
            })[0]].spots;
            S.toBoat(b, b.bin, function() {
              b.state = 'aboard';
              done();
            });
          });
        };

        /* Which way: lights on the water. */

        S.showWays = function(ways, chosen) {
          var bits = [];
          var pick = function(id) {
            bits.forEach(function(x) {
              x.destroy();
            });
            S.input.off('pointerdown', onWay);
            S.wayKeys = null;
            sound('choose', 'chime');
            chosen(id);
          };
          var onWay = function(pointer) {
            ways.some(function(w) {
              if (Math.hypot(pointer.worldX - w.at[0], pointer.worldY - w.at[1]) < 110) {
                pick(w.id);
                return true;
              }
              return false;
            });
          };
          ways.forEach(function(w) {
            var glow = S.add.image(w.at[0], w.at[1], 'soft').setTint(0xfff1bd).setBlendMode('ADD').setScale(4, 2)
              .setDepth(4200);
            var label = S.add.text(w.at[0], w.at[1] + 46, w.label, {fontFamily: 'Lexend, sans-serif', fontSize: '24px',
              fontStyle: 'bold', color: '#fff1bd', backgroundColor: 'rgba(15,33,49,0.85)', padding: {x: 14, y: 8}})
              .setOrigin(0.5).setDepth(4201);
            bits.push(glow, label);
            if (!still) {
              S.tweens.add({targets: glow, alpha: {from: 0.5, to: 1}, scale: {from: 3.4, to: 4.4}, duration: 900, yoyo: true,
                repeat: -1});
            }
          });
          S.input.on('pointerdown', onWay);
          // For the keyboard and the tests: the first way with Enter.
          S.wayKeys = function(i) {
            if (ways[i]) {
              pick(ways[i].id);
            }
          };
        };

        /* The crossing. */

        /** Everybody on board: the hero, the creature and who lives here jump into the boat. */
        S.embark = function(done) {
          var deck = q.boat && q.boat.deck || {};
          var riders = [['hero', S.hero, deck.hero], ['pet', S.pet, deck.pet], ['speaker', S.speaker, deck.speaker]];
          S.riders = [];
          var left = riders.length;
          var landed = function() {
            if (--left === 0) {
              done();
            }
          };
          S.walkTo(P.onGround(place, deck.from ? deck.from[0] : 330, deck.from ? deck.from[1] : 600), function() {
            riders.forEach(function(r, i) {
              var f = r[1];
              var to = r[2];
              if (!f || !to) {
                landed();
                return;
              }
              S.time.delayedCall(still ? 0 : i * 260, function() {
                var from = {x: f.x, y: f.y};
                var k = {v: 0};
                var b0 = f.base;
                var b1 = f.base * (to[2] || 0.42);
                f.target = null;
                f.mode = 'still';
                S.hop(f);
                S.tweens.add({targets: k, v: 1, duration: still ? 1 : 620, ease: 'Sine.easeInOut', onUpdate: function() {
                  f.x = from.x + (to[0] - from.x) * k.v;
                  f.y = from.y + (to[1] - from.y) * k.v;
                  f.lift = -Math.sin(k.v * Math.PI) * 120;
                  f.base = b0 + (b1 - b0) * k.v;
                  f.fixed = 1;
                }, onComplete: function() {
                  f.lift = 0;
                  S.riders.push({f: f, dx: to[0] - S.boat.x, dy: to[1] - S.boat.y, base: b1});
                  landed();
                }});
              });
            });
          });
        };
        /** Who went in the wrong place first complains all the way. */
        S.grumble = function(on) {
          if (!on || still) {
            return;
          }
          var grumpy = Object.keys(S.beings).map(function(k) {
            return S.beings[k];
          }).filter(function(b) {
            return b.state === 'aboard' && !b.first;
          })[0];
          if (grumpy) {
            S.time.addEvent({delay: 1300, repeat: 3, callback: function() {
              S.emote({sprite: {x: S.boat.x + grumpy.sprite.x * S.boat.scaleX, y: S.boat.y + grumpy.sprite.y * S.boat.scaleY,
                displayHeight: grumpy.sprite.displayHeight}}, '!');
            }});
          }
        };
        /** Off it goes: a little back, then away towards where it was pointed, smaller and smaller. */
        S.depart = function(done) {
          if (S.rock) {
            S.rock.stop();
          }
          var way = S.way && S.way.at ? S.way.at : [W * 0.7, H * 0.2];
          sound('boat', 'path');
          if (still || !S.loose) {
            S.time.delayedCall(400, done);
            return;
          }
          S.tweens.add({targets: S.boat, angle: -1.2, y: S.boat.y + 6, duration: 380, ease: 'Cubic.easeIn', onComplete: function() {
            S.tweens.add({targets: S.boat, x: way[0], y: way[1] + 60, scale: 0.42, angle: 0, duration: 5200,
              ease: 'Sine.easeInOut'});
            S.wake = S.time.addEvent({delay: 120, loop: true, callback: function() {
              S.splash.setDepth(S.boat.depth - 1);
              S.splash.emitParticleAt(S.boat.x - 260 * S.boat.scaleX, S.boat.y + 90 * S.boat.scaleY, 1);
            }});
            S.time.delayedCall(2600, done);
          }});
        };

        /* What the student does. */

        S.press = null;
        S.input.on('pointerdown', function(pointer) {
          if (state.phase !== 'explore' || !S.mode) {
            return;
          }
          S.last = S.time.now;
          var x = pointer.worldX;
          var y = pointer.worldY;
          var b = S.beingAt(x, y);
          if (b && (S.mode.kind === 'board' || b === S.mode.being)) {
            if (S.armed && S.onVerb) {
              S.onVerb(b);
              return;
            }
            S.press = {b: b, x: x, y: y, drag: false};
            return;
          }
          var spot = S.carried ? S.spotAt(x, y) : null;
          if (spot) {
            S.give(S.carried, spot, false);
            return;
          }
          S.walkTo(P.onGround(place, x, y), null);
        });
        S.input.on('pointermove', function(pointer) {
          var p = S.press;
          if (!p || !pointer.isDown) {
            return;
          }
          var x = pointer.worldX;
          var y = pointer.worldY;
          if (!p.drag && Math.hypot(x - p.x, y - p.y) > 14) {
            p.drag = true;
            if (S.carried && S.carried !== p.b) {
              S.putBack(S.carried);
            }
            p.b.state = 'drag';
            S.glowSpots(true);
            sound('pick', 'tap');
            p.b.sprite.setDepth(4400);
            if (p.b.ripple) {
              p.b.ripple.setVisible(false);
            }
          }
          if (p.drag) {
            p.b.x = x;
            p.b.y = y + p.b.sprite.displayHeight * 0.4;
            p.b.sprite.setPosition(p.b.x, p.b.y);
            // The hero goes after it, along the shore.
            if (!S.hero.target || Math.abs(S.hero.target.x - x) > 60) {
              S.hero.target = P.onGround(place, x, Math.max(y + 200, 610));
              S.hero.facing = Math.sign(x - S.hero.x) || S.hero.facing;
            }
          }
        });
        var release = function(pointer) {
          var p = S.press;
          S.press = null;
          if (!p) {
            return;
          }
          if (!p.drag) {
            // A touch: the hero goes to take it.
            S.walkNear(p.b, function() {
              if (p.b.state === 'wait' || p.b.state === 'trouble') {
                S.take(p.b);
              }
            });
            return;
          }
          var spot = S.spotAt(pointer.worldX, pointer.worldY);
          if (spot) {
            S.give(p.b, spot, true);
          } else {
            S.glowSpots(false);
            S.putBack(p.b);
          }
        };
        S.input.on('pointerup', release);
        S.input.on('pointerupoutside', release);
        /**
         * A being given to a place: the hero goes to the shore in front of it first, if they carry it.
         *
         * @param {Object} b
         * @param {string} spot
         * @param {boolean} dragged
         */
        S.give = function(b, spot, dragged) {
          if (S.mode && S.mode.kind === 'rescue') {
            // On the shore: it goes there, and the place does what it does.
            var at = S.spots[spot];
            var put = function() {
              S.carried = null;
              S.glowSpots(false);
              b.state = 'jump';
              S.jump(b, at[0], at[1], null, function() {
                b.home = {x: at[0], y: at[1]};
                b.state = 'trouble';
                if (S.onPlace) {
                  S.onPlace(b, spot);
                }
              });
            };
            if (dragged) {
              put();
            } else {
              S.walkTo(P.onGround(place, at[0] - 80, at[1] + 30), put);
            }
            return;
          }
          var go = function() {
            state.phase = S.mode ? 'explore' : state.phase;
            if (S.onPlace) {
              S.onPlace(b, spot);
            }
          };
          if (dragged) {
            go();
          } else {
            // It goes on with the hero to the shore in front of its place; nothing else is touched meanwhile.
            state.phase = 'busy';
            S.walkTo(shoreFor(S.spots[spot][0], S.spots[spot][1]), go);
          }
        };
        S.input.keyboard.on('keydown-ENTER', function() {
          if (S.wayKeys) {
            S.wayKeys(0);
          }
        });

        /* Every frame. */

        S.tick = function(time, delta) {
          var dt = Math.min(delta, 50) / 1000;
          P.moveHero(S, place, state, dt);
          if (S.pet.mode !== 'still') {
            P.movePet(S, dt, place);
          }
          // Who rides the boat moves with it.
          (S.riders || []).forEach(function(r) {
            r.f.x = S.boat.x + r.dx * S.boat.scaleX;
            r.f.y = S.boat.y + r.dy * S.boat.scaleY;
            r.f.base = r.base * S.boat.scaleX;
          });
          var depth = function(f) {
            return f.fixed ? 1 : P.depthOf(place, f.y);
          };
          T.cast.move(S, S.hero, depth(S.hero), time, dt, 7, still, function() {
            sound('step.grass', '');
          });
          T.cast.move(S, S.pet, depth(S.pet), time, dt, 12, still);
          if (S.speaker) {
            T.cast.move(S, S.speaker, 1, time, dt, 7, still);
            S.speaker.sprite.setDepth(900);
            S.speaker.shadow.setDepth(899);
          }
          (S.riders || []).forEach(function(r) {
            r.f.sprite.setDepth(S.boat.depth + 20);
            r.f.shadow.setVisible(false);
          });
          // What goes with the hero: at their side, a little ahead; in the water, along the shore.
          var b = S.carried;
          if (b && b.state === 'carried') {
            var gx = S.hero.x + S.hero.facing * 74;
            var gy = b.water ? Math.min(b.home.y, S.hero.y - 40) : S.hero.y + 8;
            b.x += (gx - b.x) * Math.min(1, dt * 7);
            b.y += (gy - b.y) * Math.min(1, dt * 7);
            S.size(b, b.y);
            b.sprite.setPosition(b.x, b.y + (b.lift || 0) - (still ? 0 : Math.abs(Math.sin(time / 140)) * 6 * (S.hero.moving ?
              1 : 0))).setDepth(b.y + 1).setFlipX(S.hero.facing < 0);
            if (b.ripple) {
              b.ripple.setPosition(b.x, b.y - 6).setVisible(b.water);
            }
          }
          S.emotes.forEach(function(em) {
            em.bubble.setPosition(em.who.sprite.x, em.who.sprite.y - em.who.sprite.displayHeight - 30 - em.rise);
          });
          // The camera: if the place is wider than the screen, it follows what happens, softly.
          var cam = S.cameras.main;
          var view = cam.worldView;
          if (view.width < W - 2 || view.height < H - 2) {
            var focus = S.press && S.press.drag ? S.press.b : S.carried || S.hero;
            var fx = S.riders && S.riders.length ? S.boat.x : focus.x;
            var fy = S.riders && S.riders.length ? S.boat.y : focus.y - 120;
            var k = 1 - Math.exp(-4 * dt);
            S.camAt.x += (fx - S.camAt.x) * k;
            S.camAt.y += (fy - S.camAt.y) * k;
            cam.centerOn(S.camAt.x, S.camAt.y);
          }
        };

        // For the tests of the browser: where things are, and what the student can do.
        moment.debug = {
          state: function() {
            return {phase: state.phase, mode: S.mode ? S.mode.kind : '', carried: S.carried ? S.carried.id : '',
              hero: {x: Math.round(S.hero.x), y: Math.round(S.hero.y)}, loose: !!S.loose, ways: !!S.wayKeys,
              models: {hero: !!(T.model && T.model.is(S.hero.puppet)), speaker: !!(S.speaker && T.model &&
                T.model.is(S.speaker.puppet))},
              beings: Object.keys(S.beings).map(function(k) {
                var b = S.beings[k];
                return {id: k, state: b.state, bin: b.bin || '', x: Math.round(b.x), y: Math.round(b.y)};
              }), spots: S.spots || {}};
          },
          give: function(id, spot) {
            var b = S.beings[id];
            if (b && state.phase === 'explore') {
              S.give(b, spot, true);
            }
          },
          take: function(id) {
            var b = S.beings[id];
            if (b && state.phase === 'explore') {
              S.walkNear(b, function() {
                S.take(b);
              });
            }
          },
          verb: function() {
            if (S.onVerb) {
              S.onVerb(S.carried || null);
            }
          },
          way: function(i) {
            if (S.wayKeys) {
              S.wayKeys(i || 0);
            }
          }
        };
        // The place is ready.
        S.pet.mode = 'follow';
        resolve(player);
      };
      return ready;
    }
  };
})();
