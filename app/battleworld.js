/**
 * The battle on the class screen, with movement: a clear canvas over the board draws the Embrollo where the board keeps
 * its place, floating and fraying as it loses threads, and the heroes of each team over the name of their team. Each
 * turn is seen: the right answers pull threads off it, the knot it throws flies to the team it goes for (or to all), a
 * shield stops it, a broken knot bursts, and at the end it is shut in its jar or runs away. When the art pack brings it
 * as a puppet (rig.boss.embrollo and its states), it takes the blow, throws itself at the team, laughs when the class
 * fails and gets dizzy as it frays. What is read and touched is the board, under it, as before; nothing of the canvas
 * is touched or read.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
  'use strict';

  var T = window.Tesape;

  T.battleWorld = {
    /**
     * Whether the battle can be drawn with movement here.
     *
     * @return {boolean}
     */
    can: function() {
      return !!(T.stage && T.cast && T.stage.available());
    },

    /**
     * Opens the canvas over the board.
     *
     * @return {Promise} The battle: update(), close(), state().
     */
    open: function() {
      var S = null;
      var holder = document.createElement('div');
      holder.className = 'battle-world';
      holder.setAttribute('aria-hidden', 'true');
      document.body.appendChild(holder);
      // What has been seen already, so that each thing happens once.
      var seen = {};
      var last = null;
      var places = {boss: null, teams: {}};
      var heroes = {};
      var count = {hits: 0, knots: 0, shields: 0, bursts: 0, verbs: 0};
      // The colour of each verb: the shield, the cheer, the glance and the trail.
      var VERBS = {shield: 0xffc857, cheer: 0xe8508f, glance: 0x8fd3ff, trail: 0x7cc67f, carry: 0xb9a6ff};
      var ratio = function() {
        return S.scale.height / Math.max(1, window.innerHeight);
      };
      /**
       * Once for each key.
       *
       * @param {string} key
       * @return {boolean} Whether it is the first time.
       */
      var once = function(key) {
        if (seen[key]) {
          return false;
        }
        seen[key] = true;
        return true;
      };
      // Where things are in the page now, read at every frame: the page may move them (another width, full screen, a
      // bubble of the Embrollo that takes two lines) without drawing the board again.
      var rect = function(el) {
        return el && el.isConnected ? el.getBoundingClientRect() : null;
      };
      var bossAt = function() {
        var r = rect(places.boss);
        var k = ratio();
        return r ? {x: (r.left + r.width / 2) * k, y: (r.top + r.height / 2) * k, size: Math.max(r.width, r.height) * k} :
          {x: S.scale.width / 2, y: S.scale.height * 0.3, size: S.scale.height * 0.3};
      };
      // The place of the heroes of a team in the board: they stand on its bottom.
      var teamAt = function(id) {
        var r = rect(places.teams[id]);
        var k = ratio();
        return r ? {x: (r.left + r.width / 2) * k, y: (r.top + r.height) * k, width: r.width * k, height: r.height * k} : null;
      };
      /**
       * A verb used: an arrow of its colour goes from the heroes of the team that used it to those of the team it goes to,
       * along a curve, with a light at its head; on the same team, a ring of its colour over them.
       *
       * @param {number} fromId
       * @param {number} toId
       * @param {string} kind shield, cheer, glance, trail or carry (the lane of a dizzy team).
       */
      var verb = function(fromId, toId, kind) {
        var a = teamAt(fromId);
        var b = teamAt(toId);
        if (!a || !b) {
          return;
        }
        count.verbs++;
        var k = ratio();
        var colour = VERBS[kind] || 0xffffff;
        var y0 = a.y - 100 * k;
        var y1 = b.y - 100 * k;
        var quick = T.moves && !T.moves();
        if (fromId === toId) {
          var ring = S.add.ellipse(b.x, y1, b.width * 0.8, 50 * k).setStrokeStyle(5 * k, colour, 1).setDepth(35);
          S.tweens.add({targets: ring, scaleX: 1.25, scaleY: 1.25, alpha: 0, duration: quick ? 400 : 1100,
            onComplete: function() {
              ring.destroy();
            }});
          return;
        }
        // A curve over the row, higher the further apart they are.
        var mid = {x: (a.x + b.x) / 2, y: Math.min(y0, y1) - Math.min(170 * k, Math.abs(b.x - a.x) * 0.35 + 40 * k)};
        var point = function(t) {
          var u = 1 - t;
          return {x: u * u * a.x + 2 * u * t * mid.x + t * t * b.x, y: u * u * y0 + 2 * u * t * mid.y + t * t * y1};
        };
        var line = S.add.graphics().setDepth(35);
        var head = S.add.image(a.x, y0, 'spark').setTint(colour).setScale(1.8 * k).setDepth(36);
        var p = {t: 0};
        S.tweens.add({targets: p, t: 1, duration: quick ? 150 : 750, ease: 'Sine.easeInOut', onUpdate: function() {
          line.clear().lineStyle(5 * k, colour, 0.9).beginPath().moveTo(a.x, y0);
          for (var i = 1; i <= 24 * p.t; i++) {
            var at = point(i / 24);
            line.lineTo(at.x, at.y);
          }
          var end = point(p.t);
          line.lineTo(end.x, end.y).strokePath();
          head.setPosition(end.x, end.y);
        }, onComplete: function() {
          S.sparks.explode(10, b.x, y1);
          S.tweens.add({targets: [line, head], alpha: 0, duration: 500, delay: 500, onComplete: function() {
            line.destroy();
            head.destroy();
          }});
        }});
      };
      /**
       * A knot flies from the Embrollo to a team: it falls on them, or a shield stops it.
       *
       * @param {number} id The team.
       * @param {boolean} shield
       */
      var knot = function(id, shield) {
        var to = teamAt(id);
        var from = bossAt();
        if (!to) {
          return;
        }
        var ball = S.add.image(from.x, from.y, 'dot').setTint(0x8b5cf6).setScale(2.2 * ratio()).setDepth(30);
        S.tweens.add({targets: ball, x: to.x, y: to.y - (shield ? 120 : 40) * ratio(), duration: 600, ease: 'Quad.easeIn',
          onComplete: function() {
            ball.destroy();
            if (shield) {
              count.shields++;
              var arc = S.add.ellipse(to.x, to.y - 110 * ratio(), to.width * 0.9, 60 * ratio()).setStrokeStyle(5, 0xffc857, 1)
                .setDepth(31);
              S.sparks.explode(16, to.x, to.y - 110 * ratio());
              S.tweens.add({targets: arc, alpha: 0, scaleX: 1.2, duration: 900, onComplete: function() {
                arc.destroy();
              }});
              return;
            }
            count.knots++;
            S.threads.explode(14, to.x, to.y - 40 * ratio());
            (heroes[id] || []).forEach(function(h, i) {
              S.tweens.add({targets: h.sprite, y: h.sprite.y - 14 * ratio(), duration: 120, delay: i * 40, yoyo: true,
                ease: 'Sine.easeOut'});
            });
          }});
      };
      /**
       * The heroes of a team, standing over the name of their team.
       *
       * @param {Object} team id, members (characters).
       */
      var standTeam = function(team) {
        var at = teamAt(team.id);
        if (!at) {
          return;
        }
        var list = heroes[team.id] || [];
        team.members.forEach(function(c, i) {
          var key = 'hero.' + c.userid;
          var h = list[i];
          if (!h || h.key !== key) {
            if (h) {
              h.sprite.destroy();
            }
            var url = T.artUrl([T.cast.heroKey(c)]);
            if (!url) {
              list[i] = null;
              return;
            }
            h = {key: key, sprite: S.add.image(0, 0, S.textures.exists(key) ? key : 'dot').setOrigin(0.5, 860 / 896)
              .setDepth(20).setVisible(false), phase: Math.random() * 6};
            list[i] = h;
            if (!S.textures.exists(key)) {
              var shown = h;
              S.load.image(key, url);
              S.load.once('filecomplete-image-' + key, function() {
                shown.sprite.setTexture(key);
              });
              S.load.start();
            }
          }
          // Its place in the row of its team; where the row is, at every frame.
          h.index = i;
          h.count = team.members.length;
        });
        list.length = team.members.length;
        heroes[team.id] = list;
      };
      /**
       * The puppet of the Embrollo does something once (hit, attack).
       *
       * @param {string} clip
       */
      var act = function(clip) {
        var puppet = S.boss.puppet;
        if (puppet) {
          puppet.play(clip, true);
        }
      };
      /**
       * The puppet of the Embrollo keeps doing something for a while (taunt, dizzy), and then goes back to waiting.
       *
       * @param {string} clip
       * @param {number} ms
       */
      var mood = function(clip, ms) {
        S.boss.mood = clip;
        S.boss.moodUntil = S.time.now + ms;
      };
      var battle = {
        /**
         * The board has been drawn again: where things are now, and what has happened.
         *
         * @param {Object} l The battle, as the board gets it.
         * @param {Object} where boss (the Embrollo in the page), teams ({id, box: the place of its heroes in the page,
         *     members}): elements, read again at every frame.
         */
        update: function(l, where) {
          if (!S) {
            last = [l, where];
            return;
          }
          places.boss = where.boss;
          places.teams = {};
          where.teams.forEach(function(team) {
            places.teams[team.id] = team.box;
          });
          where.teams.forEach(standTeam);
          Object.keys(heroes).forEach(function(id) {
            if (!places.teams[id]) {
              heroes[id].forEach(function(h) {
                if (h) {
                  h.sprite.destroy();
                }
              });
              delete heroes[id];
            }
          });
          S.target = !l.outcome && l.targetid ? l.targetid : null;
          var life = l.maxhp ? l.hp / l.maxhp : 1;
          S.life = life;
          var turn = l.id + ':' + l.round;
          // The verbs used in the turn, each once: from the team that used it to the one it goes to.
          var ids = {};
          (l.teams || []).forEach(function(team) {
            ids[team.name] = team.id;
          });
          (l.tokens || []).forEach(function(token, i) {
            var from = ids[token.team];
            var to = token.target ? ids[token.target] : from;
            if (from && to && once('verb:' + turn + ':' + i + ':' + token.kind + ':' + token.team + ':' + token.target)) {
              verb(from, to, token.kind);
            }
          });
          if (l.revealed && (l.right || 0) > 0 && once('hit:' + turn)) {
            count.hits++;
            var b = bossAt();
            S.threads.explode(10 + Math.min(20, (l.right || 0) * 3), b.x, b.y);
            S.boss.shake = 1;
            act('hit');
          } else if (l.revealed && !(l.right || 0) && !l.outcome && once('taunt:' + turn)) {
            // Nobody undid it: it laughs, bouncing.
            mood('taunt', 2600);
          }
          var frayed = life <= 0.6;
          if (frayed && !l.outcome && once('frayed:' + l.id)) {
            mood('dizzy', 2200);
          }
          if (l.stage === 'resolve' && once('resolve:' + turn)) {
            var ids = l.intent === 'all' ? where.teams.map(function(team) {
              return team.id;
            }) : l.targetid ? [l.targetid] : [];
            if (l.shielded) {
              ids.forEach(function(id) {
                knot(id, true);
              });
            } else if (l.counter && !l.recovered) {
              act('attack');
              ids.forEach(function(id) {
                knot(id, false);
              });
            }
            if (l.knot && l.knot.broken) {
              count.bursts++;
              var at = bossAt();
              S.sparks.explode(30, at.x, at.y);
              S.threads.explode(24, at.x, at.y);
            }
          }
          if (l.outcome && once('end:' + l.id)) {
            S.ending = l.outcome;
            var end = bossAt();
            S.sparks.explode(l.outcome === 'won' ? 40 : 10, end.x, end.y);
          }
        },

        /** For the tests of the browser. */
        state: function() {
          return {ready: !!S, boss: !!(S && S.boss && (S.boss.img.visible || S.boss.puppet)),
            bossPuppet: S && S.boss && S.boss.puppet ? S.boss.puppet.clipName : '', life: S ? S.life : 1,
            ending: S ? S.ending || '' : '',
            teams: Object.keys(heroes).length, heroes: Object.keys(heroes).reduce(function(sum, id) {
              return sum + heroes[id].filter(Boolean).length;
            }, 0), hits: count.hits, knots: count.knots, shields: count.shields, bursts: count.bursts,
            verbs: count.verbs,
            // The teams whose heroes stand inside their place now.
            aligned: !S ? 0 : Object.keys(heroes).filter(function(id) {
              var at = teamAt(id);
              return at && heroes[id].every(function(h) {
                return !h || (h.sprite.x >= at.x - at.width / 2 - 1 && h.sprite.x <= at.x + at.width / 2 + 1 &&
                  Math.abs(h.sprite.y - at.y) < 12 * ratio());
              });
            }).length};
        },
        close: function() {
          document.body.classList.remove('battle-live');
          holder.remove();
        }
      };
      return T.stage.backdrop(holder, {
        preload: function() {
          var art = T.artUrl(['boss.embrollo', 'figure.embrollo']);
          if (art) {
            this.load.image('boss', art);
          }
          // Whole, frayed, down to its last thread, if the pack paints it so; and its puppets, if it brings them.
          [1, 2, 3].forEach(function(phase) {
            if (T.artUrl(['boss.embrollo.' + phase])) {
              this.load.image('boss.' + phase, T.artUrl(['boss.embrollo.' + phase]));
            }
          }, this);
          ['', '.1', '.2', '.3'].forEach(function(phase) {
            var rig = T.puppet && T.puppet.find('boss.embrollo' + phase, 'boss');
            if (rig) {
              T.puppet.load(this, 'puppet.boss' + phase, rig);
            }
          }, this);
        },
        create: function() {
          S = this;
          // From now on the board leaves the Embrollo and the heroes to the canvas.
          document.body.classList.add('battle-live');
          T.cast.textures(S);
          var img = S.add.image(0, 0, S.textures.exists('boss') ? 'boss' : 'soft').setDepth(10);
          S.boss = {img: img, shake: 0, puppets: {}, puppet: null, mood: '', moodUntil: 0};
          ['', '.1', '.2', '.3'].forEach(function(phase) {
            var puppet = T.puppet && T.puppet.make(S, 'puppet.boss' + phase, false);
            if (puppet) {
              puppet.container.setDepth(10).setVisible(false);
              S.boss.puppets[phase] = puppet;
            }
          });
          S.threads = S.add.particles(0, 0, 'leaf', {lifespan: 1400, speed: {min: 60, max: 260}, rotate: {min: 0, max: 360},
            scale: {start: 2.2, end: 0.6}, alpha: {start: 1, end: 0}, gravityY: 120, tint: [0x8b5cf6, 0xb9a6ff, 0x6d28d9],
            emitting: false}).setDepth(25);
          S.sparks = S.add.particles(0, 0, 'spark', {lifespan: 1000, speed: {min: 80, max: 300}, scale: {start: 1.2, end: 0},
            alpha: {start: 1, end: 0}, tint: [0xfff1bd, 0xffc857], gravityY: 160, emitting: false}).setDepth(40);
          S.ring = S.add.ellipse(0, 0, 10, 10).setStrokeStyle(4, 0xe8508f, 0.9).setDepth(15).setVisible(false);
          S.life = 1;
          if (last) {
            battle.update(last[0], last[1]);
            last = null;
          }
        },
        update: function(time, delta) {
          if (!S) {
            return;
          }
          var dt = Math.min(delta, 50) / 1000;
          var k = ratio();
          var b = bossAt();
          var boss = S.boss;
          // It floats, frays as it loses threads, shakes when hit; at the end, into its jar or away.
          boss.shake = Math.max(0, boss.shake - dt * 2.5);
          var jitter = boss.shake * Math.sin(time / 25) * 14 * k + (1 - S.life) * Math.sin(time / 90) * 3 * k;
          var phase = S.life > 0.6 ? 1 : S.life > 0.25 ? 2 : 3;
          if (S.textures.exists('boss.' + phase) && boss.img.texture.key !== 'boss.' + phase) {
            boss.img.setTexture('boss.' + phase);
          }
          if (S.ending === 'won') {
            boss.out = Math.min(1, (boss.out || 0) + dt * 0.8);
          } else if (S.ending) {
            boss.away = Math.min(1, (boss.away || 0) + dt * 0.6);
          }
          var gone = 1 - Math.max(boss.out || 0, boss.away || 0);
          var rise = (boss.away || 0) * S.scale.height * 0.6;
          var spin = (boss.out || 0) * 360;
          // Its puppet in the state it is in, or the one of the whole Embrollo; else, its picture.
          var puppet = boss.puppets['.' + phase] || boss.puppets[''] || null;
          if (puppet !== boss.puppet) {
            if (boss.puppet) {
              boss.puppet.container.setVisible(false);
            }
            boss.puppet = puppet;
          }
          boss.img.setVisible(!puppet);
          if (puppet) {
            if (boss.mood && time > boss.moodUntil) {
              boss.mood = '';
            }
            if (!puppet.busy()) {
              puppet.play(boss.mood || 'idle');
            }
            puppet.draw(dt);
            // As big as its place in the board (which already grows smaller as it loses threads), standing on its feet.
            var unit = b.size / 512;
            var floats = phase === 1 ? Math.sin(time / 700) * 8 * k : 0;
            puppet.container.setVisible(true).setPosition(b.x + jitter, b.y - b.size / 2 + puppet.feet[1] * unit + floats - rise)
              .setScale(unit * (1 - (boss.out || 0) * 0.9)).setAngle(spin).setAlpha(gone);
          } else {
            var size = b.size / Math.max(1, boss.img.height);
            boss.img.setPosition(b.x + jitter, b.y + Math.sin(time / 700) * 8 * k - rise)
              .setScale(size * (1 - (boss.out || 0) * 0.9)).setAngle(Math.sin(time / 1100) * 4 + spin).setAlpha(gone);
          }
          // The team the knot goes for, marked under its heroes.
          var target = S.target && teamAt(S.target);
          S.ring.setVisible(!!target);
          if (target) {
            S.ring.setPosition(target.x, target.y - 4 * k).setSize(target.width * 0.95, 22 * k)
              .setAlpha(0.6 + 0.4 * Math.sin(time / 200));
          }
          Object.keys(heroes).forEach(function(id) {
            var at = teamAt(id);
            heroes[id].forEach(function(h) {
              if (!h) {
                return;
              }
              h.sprite.setVisible(!!at && h.sprite.texture.key === h.key);
              if (!at) {
                return;
              }
              // Close together, a little over each other, as tall as their place.
              var size = Math.min(at.height * 0.98, at.width / Math.max(3, h.count) * 2.6);
              h.sprite.setPosition(at.x + (h.index - (h.count - 1) / 2) * size * 0.36,
                at.y - 2 * k - Math.abs(Math.sin(time / 600 + h.phase)) * 3 * k).setScale(size / 896);
            });
          });
        }
      }, {clear: true, fps: 30}).then(function(made) {
        var close = battle.close;
        battle.close = function() {
          close();
          made.close();
        };
        return battle;
      }).catch(function(e) {
        battle.close();
        throw e;
      });
    }
  };
})();
