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
   * @param {Object} opts sceneid, world (the world of the student), onclose(world) when the student leaves; classmode:
   *     name, creature, items, roles, vote ({open(prompt, labels), state(), close()}, or null without phones), finish(events,
   *     from).
   */
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

    function close() {
      layer.remove();
      document.body.classList.remove('in-scene');
      opts.onclose(world);
    }

    function show(html) {
      layer.innerHTML = '<div class="scene"><div class="scene-top"><span class="scene-title">' + esc(sc ? sc.title : '') +
        '</span><button class="btn btn-soft scene-close" type="button" aria-label="' + esc(t('app_close')) + '">✕</button>' +
        '</div><div class="scene-body">' + html + '</div></div>';
      layer.querySelector('.scene-close').onclick = close;
      layer.scrollTop = 0;
      var focus = layer.querySelector('[data-focus]');
      if (focus) {
        focus.focus();
      }
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
        return !cm && data.role === req.role;
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

    function start(from) {
      run = {node: from || sc.start, from: from || '', events: [], extra: 0, extraused: {}, hints: 0, shield: false,
        flags: (data.flags || []).slice(), opened: {}, spent: 0};
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
      if (n.type === 'story') {
        var body = show(picture(n.image || sceneImage(), data) + (n.speaker ? speaker(n.speaker, data) : '') +
          '<p class="scene-text">' + esc(tx(n.text)) + '</p><div class="actions">' +
          '<button class="btn btn-main" data-focus data-next>' + esc(t('app_continue')) + '</button></div>');
        body.querySelector('[data-next]').onclick = function() {
          go(n.next);
        };
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

    function correctIn(node) {
      var picks = [];
      run.events.forEach(function(e) {
        if (e.n === node && e.pick) {
          picks = e.pick;
        }
      });
      var opts = sc.nodes[node].options || [];
      return picks.filter(function(id) {
        return opts.some(function(o) {
          return o.id === id && o.correct === true;
        });
      }).length;
    }

    function kind(o) {
      if (o.correct === true || (o.correct !== false && (o.points || 0) >= 2)) {
        return 'good';
      }
      return o.correct === false ? 'bad' : 'meh';
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
      if (cm) {
        return 0;
      }
      if (data.role === 'sage') {
        return run.hints % 2;
      }
      return 1;
    }

    /**
     * The hints of a decision, if it has any: the ones already opened and a button for the next.
     *
     * @param {Object} n The node.
     * @param {Element} body
     */
    function hintArea(n, body) {
      if (!n.hints || !n.hints.length) {
        return;
      }
      var box = document.createElement('div');
      box.className = 'scene-hints';
      body.appendChild(box);
      var draw = function() {
        var opened = run.opened[run.node] || [];
        var cost = hintCost();
        box.innerHTML = opened.map(function(i) {
          return '<p class="hint">' + T.icon('idea') + ' ' + esc(tx(n.hints[i])) + '</p>';
        }).join('') + (opened.length < n.hints.length ? '<button class="btn btn-soft" type="button" data-hint>' +
          esc(cost ? t('app_hint_cost', cost) : t('app_hint_free')) + '</button>' : '');
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
      var open = n.options.filter(function(o) {
        return has(o.requires);
      });
      var opts = n.options.filter(function(o) {
        // What other scenes left (flags) is memory, not something to get: without it, the option is not there.
        return !(o.requires && o.requires.flag) || has(o.requires);
      }).map(function(o) {
        if (!has(o.requires)) {
          return '<button class="opt locked" type="button" disabled><b>' + esc(tx(o.label)) + '</b><small>' +
            esc(o.locked ? tx(o.locked) : missing(o.requires)) + '</small></button>';
        }
        var letter = cm ? '<span class="opt-letter">' + LETTERS[open.indexOf(o)] + '</span>' : '';
        return '<button class="opt" type="button" data-id="' + esc(o.id) + '">' + letter + esc(tx(o.label)) +
          '<span class="opt-votes" data-votes></span></button>';
      }).join('');
      var voting = cm && cm.vote && !multi && open.length >= 2;
      var body = show((n.image ? picture(n.image, data) : '') + '<p class="scene-prompt">' + esc(tx(n.prompt)) + '</p>' +
        (voting ? '<div class="class-vote" data-vote><button class="btn btn-soft" data-voteopen>' +
        esc(t('board_scene_voteopen')) + '</button></div>' : '') +
        '<div class="opts">' + opts + '</div>' + (multi ? '<div class="counter" data-count>' + esc(t('app_chosen', '0/' + max)) +
        '</div><div class="actions"><button class="btn btn-main" data-confirm disabled>' + esc(t('app_confirm')) +
        '</button></div>' : '') + '<div data-out></div>');
      hintArea(n, body);
      if (voting) {
        classVote(body, n, open);
      }
      function pick(id) {
        return n.options.filter(function(o) {
          return o.id === id;
        })[0];
      }
      body.querySelectorAll('.opt[data-id]').forEach(function(b) {
        b.onclick = function() {
          var id = b.dataset.id;
          if (!multi) {
            body.querySelectorAll('.opt').forEach(function(x) {
              x.disabled = true;
            });
            b.classList.add('sel');
            run.events.push({n: run.node, pick: [id]});
            reveal(body, [pick(id)], function() {
              var o = pick(id);
              (o.effects || []).forEach(function(e) {
                run.extra += e.extraquestion || 0;
                if (e.flag) {
                  run.flags.push(e.flag);
                }
              });
              if (o.correct === false) {
                mistake();
              }
              var target = o.next || n.next;
              if (target === run.node) {
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
          body.querySelectorAll('.opt').forEach(function(x) {
            x.disabled = true;
          });
          this.disabled = true;
          run.events.push({n: run.node, pick: selected.slice()});
          selected.forEach(function(id) {
            (pick(id).effects || []).forEach(function(e) {
              run.extra += e.extraquestion || 0;
              if (e.flag) {
                run.flags.push(e.flag);
              }
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
      var i = 0;
      function step() {
        if (i >= list.length) {
          done();
          return;
        }
        var o = list[i++];
        out.innerHTML = '<div class="conseq ' + kind(o) + '"><span class="what">' + esc(tx(o.label)) + '</span>' +
          esc(tx(o.feedback || '')) + '</div><div class="actions"><button class="btn btn-main" data-more>' +
          esc(i < list.length ? t('app_next') : t('app_continue')) + '</button></div>';
        var more = out.querySelector('[data-more]');
        more.focus();
        more.onclick = step;
        out.scrollIntoView({behavior: 'smooth', block: 'nearest'});
      }
      step();
    }

    function classify(n) {
      var items = n.items.slice();
      var idx = 0;
      var once = n.retry === 'none';
      function draw() {
        if (idx >= items.length) {
          go(n.next);
          return;
        }
        var it = items[idx];
        var dots = items.map(function(x, j) {
          return '<i class="' + (j < idx ? 'ok' : '') + '"></i>';
        }).join('');
        if (!has(it.requires)) {
          var lockedbody = show('<p class="scene-prompt">' + esc(tx(n.prompt)) + '</p><div class="progress">' + dots + '</div>' +
            '<div class="current">' + esc(tx(it.label)) + '</div><div class="conseq meh">' + esc(it.locked ? tx(it.locked) :
            missing(it.requires)) + '</div><div class="actions"><button class="btn btn-soft" data-focus data-skip>' +
            esc(t('app_later')) + '</button></div>');
          lockedbody.querySelector('[data-skip]').onclick = function() {
            idx++;
            draw();
          };
          return;
        }
        var bins = n.bins.map(function(b) {
          return '<button class="bin" type="button" data-bin="' + esc(b.id) + '">' + esc(tx(b.label)) + '</button>';
        }).join('');
        var body = show('<p class="scene-prompt">' + esc(tx(n.prompt)) + '</p><div class="progress">' + dots + '</div>' +
          '<div class="current" data-cur>' + esc(tx(it.label)) + '</div><div class="bins">' + bins + '</div><div data-out></div>');
        hintArea(n, body);
        body.querySelectorAll('.bin').forEach(function(b) {
          b.onclick = function() {
            var bin = b.dataset.bin;
            run.events.push({n: run.node, item: it.id, bin: bin});
            var out = body.querySelector('[data-out]');
            if (bin === it.bin || once) {
              body.querySelectorAll('.bin').forEach(function(x) {
                x.disabled = true;
              });
              var good = bin === it.bin;
              out.innerHTML = '<div class="conseq ' + (good ? 'good' : 'bad') + '"><span class="what">' + esc(tx(it.label)) +
                '</span>' + esc(good ? tx(it.ok || t('app_inplace')) : tx((it.feedback && it.feedback[bin]) ||
                t('app_notthere'))) + '</div><div class="actions"><button class="btn btn-main" data-more>' +
                esc(t('app_next')) + '</button></div>';
              var more = out.querySelector('[data-more]');
              more.focus();
              more.onclick = function() {
                idx++;
                draw();
              };
            } else {
              // That group is not it: it stays off until the next item.
              b.disabled = true;
              mistake();
              out.innerHTML = '<div class="conseq bad">' + esc(tx((it.feedback && it.feedback[bin]) || t('app_notthere'))) +
                '</div>';
              var cur = body.querySelector('[data-cur]');
              cur.classList.remove('shake');
              void cur.offsetWidth;
              cur.classList.add('shake');
            }
          };
        });
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
        body.querySelectorAll('.opt').forEach(function(b) {
          b.onclick = function() {
            var s = n.steps.filter(function(x) {
              return x.id === b.dataset.id;
            })[0];
            var expected = n.steps[built.length];
            run.events.push({n: run.node, step: s.id});
            if (s === expected) {
              built.push(s);
              draw();
            } else {
              var stuck = n.stuck || {};
              var msg = stuck[s.id + '-antes-de-' + expected.id] || stuck.default || t('app_stuck');
              body.querySelector('[data-out]').innerHTML = '<div class="conseq bad">' + esc(tx(msg)) + '</div>';
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
      function count(live) {
        box.innerHTML = '<span>' + esc(t('board_scene_votes', {answered: live.answered, joined: live.joined})) + '</span>' +
          '<button class="btn btn-main" data-voteclose>' + esc(t('board_scene_voteclose')) + '</button>';
        box.querySelector('[data-voteclose]').onclick = function() {
          clearTimeout(timer);
          cm.vote.close().then(tally);
        };
      }
      function poll() {
        timer = setTimeout(function() {
          if (!document.body.contains(box)) {
            return;
          }
          cm.vote.state().then(function(live) {
            if (live && live.open) {
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

    function classEnding(n) {
      show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_saving')) + '</div>');
      cm.finish(run.events, run.from).then(function(result) {
        var success = result.outcome !== 'retry';
        var body = show(picture(n.image || sceneImage(), data) + '<p class="scene-text">' + esc(tx(n.text)) + '</p>' + (success ?
          '<div class="conseq good"><span class="what">' + esc(t('board_scene_rewarded', result.rewarded)) + '</span></div>' :
          '') + better(result) + '<div class="actions">' + (success ? '<button class="btn btn-main" data-focus data-map>' +
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
      show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_saving')) + '</div>');
      T.api('play_scene', {courseid: cfg.courseid, sceneid: data.id, events: JSON.stringify(run.events),
        start: run.from}).then(function(result) {
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
        var hatch = r.hatched ? '<div class="hatch">' + T.creature(world.character.creature, 1, true) + '<p>' +
          esc(t('app_hatched', creatureName())) + '</p></div>' : '';
        var levelup = world.character.level > before.level ? '<div class="conseq good"><span class="what">' +
          esc(t('app_levelup', world.character.level)) + '</span></div>' : '';
        var done = !success ? '' : (result.attempt > 1 && !lines.length ? '<p class="help">' + esc(t('app_alreadydone')) +
          '</p>' : '');
        if (success) {
          // What this play left (memory between scenes) counts for the next one.
          data.flags = run.flags.slice();
        }
        var body = show(picture(n.image || sceneImage(), data) + '<p class="scene-text">' + esc(tx(n.text)) + '</p>' + hatch + levelup +
          (lines.length ? '<div class="conseq good"><span class="what">' + esc(t('app_forcharacter')) + '</span>' +
          lines.map(esc).join('<br>') + '</div>' : '') + done + better(result) + '<div class="actions">' + (success ?
          '<button class="btn btn-main" data-focus data-map>' + esc(t('app_backtomap')) + '</button>' + replay(result) :
          '<button class="btn btn-main" data-focus data-retry>' + esc(t('app_tryagain')) + '</button>') + '</div>');
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
        if (!e.network) {
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
    T.api('get_scene', {courseid: cfg.courseid, sceneid: opts.sceneid}).then(function(d) {
      data = d;
      data.speakers = JSON.parse(d.speakers || '{}');
      data.items = JSON.parse(d.items || '{}');
      data.flags = JSON.parse(d.flags || '[]');
      sc = JSON.parse(d.definition);
      start(null);
    }).catch(function(e) {
      show('<p class="scene-text">' + esc(t('app_error')) + '</p><p class="help">' + esc(e.message) + '</p>');
    });
  };
})();
