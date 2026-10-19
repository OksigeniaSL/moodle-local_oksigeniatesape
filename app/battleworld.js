/**
 * The battle on the class screen, with movement: a clear canvas over the board draws the Embrollo where the board keeps
 * its place, floating and fraying as it loses threads, and the heroes of each team over the name of their team. Each
 * turn is seen: the right answers pull threads off it, the knot it throws flies to the team it goes for (or to all), a
 * shield stops it, a broken knot bursts, and at the end it is shut in its jar or runs away. What is read and touched is
 * the board, under it, as before; nothing of the canvas is touched or read.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
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
      var count = {hits: 0, knots: 0, shields: 0, bursts: 0};
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
          if (l.revealed && (l.right || 0) > 0 && once('hit:' + turn)) {
            count.hits++;
            var b = bossAt();
            S.threads.explode(10 + Math.min(20, (l.right || 0) * 3), b.x, b.y);
            S.boss.shake = 1;
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
          return {ready: !!S, boss: !!(S && S.boss && S.boss.img.visible), life: S ? S.life : 1, ending: S ? S.ending || '' : '',
            teams: Object.keys(heroes).length, heroes: Object.keys(heroes).reduce(function(sum, id) {
              return sum + heroes[id].filter(Boolean).length;
            }, 0), hits: count.hits, knots: count.knots, shields: count.shields, bursts: count.bursts,
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
          // Whole, frayed, down to its last thread, if the pack paints it so.
          [1, 2, 3].forEach(function(phase) {
            if (T.artUrl(['boss.embrollo.' + phase])) {
              this.load.image('boss.' + phase, T.artUrl(['boss.embrollo.' + phase]));
            }
          }, this);
        },
        create: function() {
          S = this;
          // From now on the board leaves the Embrollo and the heroes to the canvas.
          document.body.classList.add('battle-live');
          T.cast.textures(S);
          var img = S.add.image(0, 0, S.textures.exists('boss') ? 'boss' : 'soft').setDepth(10);
          S.boss = {img: img, shake: 0};
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
          // As big as its place in the board, which already grows smaller as it loses threads.
          var size = b.size / Math.max(1, boss.img.height);
          if (S.ending === 'won') {
            boss.out = Math.min(1, (boss.out || 0) + dt * 0.8);
          } else if (S.ending) {
            boss.away = Math.min(1, (boss.away || 0) + dt * 0.6);
          }
          boss.img.setPosition(b.x + jitter, b.y + Math.sin(time / 700) * 8 * k - (boss.away || 0) * S.scale.height * 0.6)
            .setScale(size * (1 - (boss.out || 0) * 0.9)).setAngle(Math.sin(time / 1100) * 4 + (boss.out || 0) * 360)
            .setAlpha(1 - Math.max(boss.out || 0, boss.away || 0));
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
