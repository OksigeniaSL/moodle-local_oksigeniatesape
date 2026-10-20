/**
 * Tesapé: the naturalist's Guess who. Twelve beings of the valley face up and one of them hidden: the student asks by
 * traits («Does it have feathers?», «Is it born from an egg?») and the beings that do not fit turn over, until they say
 * who it is. A dichotomous key, played. Never lost; with few questions, the card of the being goes golden to the field
 * guide. The server counts it all again.
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

  /** What the questions ask about, in the order of the tabs. */
  var TRAITS = ['kind', 'cover', 'legs', 'breath', 'born', 'eats', 'habitat'];

  /**
   * Plays Guess who.
   *
   * @param {Object} opts code, world, onclose(world).
   */
  T.playGuesswho = function(opts) {
    var m = T.minigame(opts, 'guesswho', function(g) {
      game = g;
      intro();
    });
    var layer = m.layer;
    var show = m.show;
    var sound = m.sound;
    var game = null;
    var run = null;

    /**
     * A card of the game.
     *
     * @param {string} id
     * @return {Object}
     */
    function card(id) {
      for (var i = 0; i < game.cards.length; i++) {
        if (game.cards[i].id === id) {
          return game.cards[i];
        }
      }
      return {id: id, label: id, name: id, picture: ''};
    }

    /**
     * The questions that count so far: the healer's first slip does not.
     *
     * @return {number}
     */
    function counted() {
      return run.count - (game.passive === 'healer' && run.slips ? 1 : 0);
    }

    /* ---------- Start ---------- */

    /**
     * What the role does here, and the help of the creature and of the magnifier.
     *
     * @return {string}
     */
    function passiveNote() {
      var c = m.world.character || {};
      var role = game.passive;
      var html = '';
      if (role && T.roleName) {
        var name = T.roleName(m.world.family, role, c.nameform);
        name = name.charAt(0).toLocaleLowerCase(cfg.lang || 'es') + name.slice(1);
        var two = (role === 'sage' && game.looks - (game.lens || 0) > 1) || (role === 'protector' && game.extra > 1);
        html += '<p class="help game-passive">' + T.icon(role === 'sage' ? 'lens' : role === 'scout' ? 'trail' :
          role === 'protector' ? 'shield' : 'energy') + esc(t('app_guesswho_passive_' + role + (two ? '_two' : ''),
          {name: name})) + '</p>';
      }
      if (game.sniffs) {
        html += '<p class="help game-passive">' + T.icon('trail') + esc(t('app_guesswho_sniff_note')) + '</p>';
      }
      if (game.lens) {
        html += '<p class="help game-passive">' + T.icon('lens') + esc(t('app_worn_lens')) + '</p>';
      }
      return html;
    }

    function intro() {
      var body = show('<div class="guesswho-intro">' + m.opening('<span class="gw-hidden big" aria-hidden="true">?</span>') +
        '<p class="help">' + esc(t('app_guesswho_goal', game.golden)) + '</p>' + passiveNote() +
        '<div class="actions"><button class="btn btn-main" data-go>' + esc(t('app_whoami_start')) + '</button></div></div>');
      body.querySelector('[data-go]').onclick = start;
      body.querySelector('[data-go]').focus();
    }

    function start() {
      run = {events: [{n: game.seed}], up: game.cards.map(function(c) {
        return c.id;
      }), asked: {}, order: [], count: 0, slips: 0, chosen: '', done: false, say: null, over: false,
        tab: firstTab()};
      m.begin();
      draw();
    }

    /**
     * The first tab with questions.
     *
     * @return {string}
     */
    function firstTab() {
      for (var i = 0; i < TRAITS.length; i++) {
        if (questionsOf(TRAITS[i]).length) {
          return TRAITS[i];
        }
      }
      return '';
    }

    /**
     * The questions about a trait.
     *
     * @param {string} trait
     * @return {Object[]}
     */
    function questionsOf(trait) {
      return game.questions.filter(function(q) {
        return q.trait === trait;
      });
    }

    /* ---------- The board ---------- */

    /** Draws the board once a play: then only what changes changes, so that the cards turn over. */
    function draw() {
      var html = '<div class="nest-hud"><span class="kicker" data-count></span><span class="gw-par">' +
        esc(t('app_guesswho_par', game.golden)) + '</span>' + m.lookButton() + '</div>' +
        '<div class="gw-board" role="group" aria-label="' + esc(t('app_guesswho_board')) + '">' +
        game.cards.map(function(c) {
          return '<button type="button" class="gw-card" data-card="' + esc(c.id) + '"><span class="gw-inner">' +
            '<span class="gw-face">' + T.thing(c.picture, T.cap(c.label)) + '</span><span class="gw-back" ' +
            'aria-hidden="true"></span></span></button>';
        }).join('') + '</div><div class="gw-say" data-say aria-live="polite"></div>' +
        '<div class="gw-ask" data-ask></div><div class="actions" data-actions></div>';
      var body = show(html);
      body.querySelectorAll('[data-card]').forEach(function(b) {
        b.onclick = function() {
          if (run.done || run.up.indexOf(b.dataset.card) < 0) {
            return;
          }
          run.chosen = run.chosen === b.dataset.card ? '' : b.dataset.card;
          update();
          var go = layer.querySelector('[data-guess]');
          if (go) {
            go.focus();
          }
        };
      });
      // The look of the sage (or the nose of the creature): two beings that are not the one turn over.
      m.bindLook(body, look);
      update();
    }

    /** What changes after a question, a name or a look. */
    function update() {
      var count = layer.querySelector('[data-count]');
      if (!count) {
        return;
      }
      count.textContent = t('app_guesswho_count', counted());
      layer.querySelectorAll('[data-card]').forEach(function(b) {
        var c = card(b.dataset.card);
        var down = run.up.indexOf(c.id) < 0;
        var found = run.done && c.id === game.secret;
        b.classList.toggle('down', down);
        b.classList.toggle('chosen', run.chosen === c.id);
        b.classList.toggle('found', found);
        b.disabled = down || run.done;
        b.setAttribute('aria-label', T.cap(c.label) + (down ? ', ' + t('app_guesswho_out') : ''));
        b.setAttribute('aria-pressed', run.chosen === c.id ? 'true' : 'false');
      });
      var say = layer.querySelector('[data-say]');
      say.className = 'gw-say' + (run.say ? ' ' + run.say.kind : '');
      say.innerHTML = run.say ? run.say.html : esc(t('app_guesswho_ask'));
      drawAsk();
      var actions = layer.querySelector('[data-actions]');
      if (run.done) {
        actions.innerHTML = '<button class="btn btn-main" data-end>' + esc(t('app_guesswho_tocard')) + '</button>';
        actions.querySelector('[data-end]').onclick = finish;
        actions.querySelector('[data-end]').focus();
      } else if (run.chosen) {
        actions.innerHTML = '<button class="btn btn-main" data-guess>' + esc(t('app_guesswho_isit',
          card(run.chosen).name)) + '</button><button class="btn btn-soft" data-unchoose>' + esc(t('app_guesswho_keepasking')) +
          '</button>';
        actions.querySelector('[data-guess]').onclick = function() {
          guess(run.chosen);
        };
        actions.querySelector('[data-unchoose]').onclick = function() {
          run.chosen = '';
          update();
        };
      } else {
        actions.innerHTML = run.up.length === 1 ? '' : '<p class="help gw-tip">' + esc(t('app_guesswho_tip')) + '</p>';
      }
    }

    /** The questions: a tab for each trait, and the questions of the tab, with what was answered. */
    function drawAsk() {
      var box = layer.querySelector('[data-ask]');
      if (run.done) {
        box.innerHTML = '';
        return;
      }
      var tabs = TRAITS.filter(function(trait) {
        return questionsOf(trait).length > 0;
      });
      box.innerHTML = '<div class="gw-tabs" role="tablist">' + tabs.map(function(trait) {
        return '<button type="button" role="tab" class="gw-tab' + (trait === run.tab ? ' on' : '') + '" data-tab="' + trait +
          '" aria-selected="' + (trait === run.tab ? 'true' : 'false') + '">' + esc(t('app_guesswho_trait_' + trait)) +
          '</button>';
      }).join('') + '</div><div class="gw-questions" role="tabpanel">' + questionsOf(run.tab).map(function(q) {
        var asked = Object.prototype.hasOwnProperty.call(run.asked, q.id);
        var split = '';
        if (!asked && game.passive === 'scout') {
          // The scout sees how many would stay on each side.
          var yes = run.up.filter(function(id) {
            return q.yes.indexOf(id) >= 0;
          }).length;
          split = '<small class="gw-split">' + T.icon('trail') + esc(t('app_guesswho_split', {yes: yes,
            no: run.up.length - yes})) + '</small>';
        }
        return '<button type="button" class="btn btn-soft gw-q' + (asked ? ' asked ' + (run.asked[q.id] ? 'yes' : 'no') : '') +
          '" data-q="' + esc(q.id) + '"' + (asked ? ' disabled' : '') + '>' + esc(q.text) + (asked ? ' <b>' +
          esc(run.asked[q.id] ? t('app_guesswho_yes') : t('app_guesswho_no')) + '</b>' : '') + split + '</button>';
      }).join('') + '</div>';
      box.querySelectorAll('[data-tab]').forEach(function(b) {
        b.onclick = function() {
          run.tab = b.dataset.tab;
          drawAsk();
          var on = layer.querySelector('[data-tab="' + run.tab + '"]');
          if (on) {
            on.focus();
          }
        };
      });
      box.querySelectorAll('[data-q]').forEach(function(b) {
        b.onclick = function() {
          ask(b.dataset.q);
        };
      });
    }

    /* ---------- Asking, naming, looking ---------- */

    /**
     * A question: the hidden one answers, and those that do not answer the same turn over.
     *
     * @param {string} id
     */
    function ask(id) {
      var q = game.questions.filter(function(x) {
        return x.id === id;
      })[0];
      if (!q || run.done || Object.prototype.hasOwnProperty.call(run.asked, id)) {
        return;
      }
      var answer = q.yes.indexOf(game.secret) >= 0;
      var before = run.up.length;
      run.events.push({q: id});
      run.asked[id] = answer;
      run.order.push(id);
      run.count++;
      run.up = run.up.filter(function(c) {
        return (q.yes.indexOf(c) >= 0) === answer;
      });
      if (run.up.indexOf(run.chosen) < 0) {
        run.chosen = '';
      }
      var turned = before - run.up.length;
      var healed = false;
      if (!turned) {
        run.slips++;
        healed = game.passive === 'healer' && run.slips === 1;
      }
      sound(turned ? 'choose' : 'near');
      run.say = {kind: turned ? 'good' : 'bad', html: '<b>' + esc(answer ? t('app_guesswho_yes') : t('app_guesswho_no')) +
        '.</b> ' + esc(turned ? t(turned === 1 ? 'app_guesswho_turned_one' : 'app_guesswho_turned', turned) :
        t('app_guesswho_nothing')) + (healed ? ' ' + esc(t('app_guesswho_healed')) : '') +
        (run.up.length === 1 ? ' ' + esc(t('app_guesswho_onlyone')) : '')};
      update();
    }

    /**
     * A name: the hidden one is found, or that card turns over and it counts as a question.
     *
     * @param {string} id
     */
    function guess(id) {
      if (run.done || run.up.indexOf(id) < 0) {
        return;
      }
      var c = card(id);
      run.events.push({g: id});
      run.chosen = '';
      if (id === game.secret) {
        run.done = true;
        sound('right');
        run.say = {kind: 'good', html: '<b>' + esc(t('app_guesswho_found', c.name)) + '</b>'};
      } else {
        run.up = run.up.filter(function(x) {
          return x !== id;
        });
        run.count++;
        run.slips++;
        sound('wrong');
        run.say = {kind: 'bad', html: '<b>' + esc(t('app_guesswho_notit', c.name)) + '</b> ' +
          esc(t('app_guesswho_counts')) + (game.passive === 'healer' && run.slips === 1 ? ' ' +
          esc(t('app_guesswho_healed')) : '') + (run.up.length === 1 ? ' ' + esc(t('app_guesswho_onlyone')) : '')};
      }
      update();
    }

    /**
     * The look: two beings that are not the hidden one turn over, without it counting as a question.
     *
     * @return {boolean} False if there was nothing to turn over.
     */
    function look() {
      if (run.done) {
        return false;
      }
      var out = T.shuffle(run.up.filter(function(id) {
        return id !== game.secret;
      })).slice(0, 2);
      if (!out.length) {
        return false;
      }
      out.forEach(function(id) {
        run.events.push({l: id});
      });
      run.up = run.up.filter(function(id) {
        return out.indexOf(id) < 0;
      });
      if (out.indexOf(run.chosen) >= 0) {
        run.chosen = '';
      }
      run.say = {kind: 'good', html: esc(t('app_guesswho_looked', out.map(function(id) {
        return card(id).label;
      }).join(' · '))) + (run.up.length === 1 ? ' ' + esc(t('app_guesswho_onlyone')) : '')};
      update();
      return true;
    }

    /* ---------- The end ---------- */

    function finish() {
      if (run.over) {
        return;
      }
      run.over = true;
      run.playid = run.playid || T.playId();
      m.save(run.playid, run.events, ending);
    }

    function ending(result) {
      var c = card(game.secret);
      // What the questions said of it: the key, read back.
      var path = run.order.map(function(id) {
        var q = game.questions.filter(function(x) {
          return x.id === id;
        })[0];
        return '<li>' + esc(q.text) + ' <b>' + esc(run.asked[id] ? t('app_guesswho_yes') : t('app_guesswho_no')) + '</b></li>';
      }).join('');
      var note = !result.success ? '' : result.newcard ? t(result.golden ? 'app_guesswho_golden' : 'app_guesswho_card',
        result.card || c.name) : t('app_guesswho_hadit', result.card || c.name);
      var body = m.ending(result, {won: t('app_guesswho_won', c.name), lost: t('app_guesswho_lost'),
        art: '<span class="gw-found-card' + (result.golden ? ' golden' : '') + '">' + T.thing(c.picture, T.cap(c.label)) +
          '</span>',
        more: (note ? '<p class="gw-cardnote' + (result.golden ? ' golden' : '') + '">' + esc(note) + '</p>' : '') +
          (path ? '<div class="card whack-learn"><h3>' + esc(t('app_guesswho_path')) + '</h3><ul>' + path +
          '</ul></div>' : '') + '<div class="actions"><button class="btn btn-soft" data-another>' +
          esc(t('app_guesswho_another')) + '</button></div>',
        again: again});
      var another = body.querySelector('[data-another]');
      if (another) {
        another.onclick = again;
      }
    }

    /** Another being hides: the server says which, after the play kept. */
    function again() {
      show('<div class="loading-inline"><div class="lamp"></div>' + esc(t('app_loading')) + '</div>');
      T.api('get_minigame', {courseid: cfg.courseid, game: game.code}).then(function(g) {
        m.game = g;
        game = g;
        intro();
      }).catch(function(e) {
        show('<p>' + esc(e.message) + '</p>');
      });
    }
  };
})();
