/**
 * Tesapé: the little living things of a world, moving around the game. Fireflies by the lanterns of the map, and now
 * and then a visitor: a butterfly that crosses the screen, a ladybird along the edge of a card, a spider coming down on
 * its thread, ants in a row, a snail… Touched, they say what they are and something true about them. Each world brings
 * its own in its art pack (theme.critters), with their pictures, how they move and their texts; without them, those of
 * the valley, drawn here. Few and slow, never during a scene, and none with «less motion».
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
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- The drawings of the valley, until the painted ones come in the art pack ---------- */

  var SVG = {
    firefly: '<ellipse cx="11" cy="21" rx="6" ry="4.5" fill="#efff8f"/><ellipse cx="19" cy="20" rx="7" ry="4" ' +
      'fill="#3a2a1a"/><circle cx="26.5" cy="19" r="3" fill="#2a1d12"/><ellipse class="wing" cx="18" cy="15" rx="7" ' +
      'ry="3" fill="#d9eeff" opacity=".55"/>',
    ladybug: '<g stroke="#1b1b1b" stroke-width="1.6" stroke-linecap="round"><path d="M12 16 6 12M11 22H5M12 27l-6 3M28 ' +
      '16l6-4M29 22h6M28 27l6 3"/></g><circle cx="20" cy="10" r="5" fill="#1b1b1b"/><circle cx="18" cy="8.5" r="1" ' +
      'fill="#fff"/><circle cx="22" cy="8.5" r="1" fill="#fff"/><circle cx="20" cy="23" r="11" fill="#d8312b"/><path ' +
      'd="M20 12v22" stroke="#1b1b1b" stroke-width="1.2"/><g fill="#1b1b1b"><circle cx="15" cy="18.5" r="2.2"/><circle ' +
      'cx="25" cy="18.5" r="2.2"/><circle cx="14.5" cy="27" r="2.4"/><circle cx="25.5" cy="27" r="2.4"/></g><ellipse ' +
      'cx="15.5" cy="15.5" rx="3" ry="1.6" fill="#fff" opacity=".35"/>',
    butterfly: '<g class="wing wl"><path d="M19 19C12 6 3 6 4 14c1 6 8 7 15 6Z" fill="#f29a3a" stroke="#3b2410" ' +
      'stroke-width="1.2"/><path d="M19 21c-6 1-12 4-10 9 2 4 7 1 10-7Z" fill="#f6c25c" stroke="#3b2410" ' +
      'stroke-width="1.2"/><circle cx="9" cy="12" r="1.4" fill="#fff"/></g><g class="wing wr"><path d="M21 19c7-13 16-13 ' +
      '15-5-1 6-8 7-15 6Z" fill="#f29a3a" stroke="#3b2410" stroke-width="1.2"/><path d="M21 21c6 1 12 4 10 9-2 4-7 1-10-7Z" ' +
      'fill="#f6c25c" stroke="#3b2410" stroke-width="1.2"/><circle cx="31" cy="12" r="1.4" fill="#fff"/></g><ellipse ' +
      'cx="20" cy="21" rx="1.6" ry="8" fill="#2a1d12"/><path d="M19.5 13.5 17 8M20.5 13.5 23 8" stroke="#2a1d12" ' +
      'stroke-width=".9" fill="none"/>',
    dragonfly: '<g class="wing" fill="#d8f4ff" stroke="#9fd6e6" stroke-width=".8" opacity=".75"><ellipse cx="11" ' +
      'cy="14" rx="9" ry="2.6" transform="rotate(-8 11 14)"/><ellipse cx="29" cy="14" rx="9" ry="2.6" ' +
      'transform="rotate(8 29 14)"/><ellipse cx="11.5" cy="18.5" rx="8" ry="2.3" transform="rotate(10 11.5 18.5)"/>' +
      '<ellipse cx="28.5" cy="18.5" rx="8" ry="2.3" transform="rotate(-10 28.5 18.5)"/></g><rect x="18.6" y="12" ' +
      'width="2.8" height="25" rx="1.4" fill="#2a8f9e"/><circle cx="20" cy="10" r="3.4" fill="#1d6f7a"/><circle cx="18.6" ' +
      'cy="9.4" r="1.1" fill="#bff3ff"/><circle cx="21.4" cy="9.4" r="1.1" fill="#bff3ff"/>',
    bee: '<ellipse class="wing" cx="17" cy="13" rx="6" ry="4" fill="#eaf6ff" opacity=".75"/><path d="M9 22l-3 1 3 1Z" ' +
      'fill="#2a1d12"/><ellipse cx="18" cy="22" rx="9" ry="6.2" fill="#f2b632"/><g fill="#2a1d12"><rect x="14" y="16" ' +
      'width="2.6" height="12" rx="1"/><rect x="19.4" y="16" width="2.6" height="12" rx="1"/></g><circle cx="27.5" ' +
      'cy="21" r="4.2" fill="#2a1d12"/><circle cx="29" cy="20" r="1" fill="#fff"/>',
    fairy: '<circle cx="20" cy="20" r="10" fill="url(#tesape-fairyglow)"/><g class="wing" fill="#efe8ff" opacity=".8">' +
      '<ellipse cx="14" cy="14" rx="6" ry="3.2" transform="rotate(-30 14 14)"/><ellipse cx="26" cy="14" rx="6" ry="3.2" ' +
      'transform="rotate(30 26 14)"/></g><circle cx="20" cy="20" r="3.6" fill="#fff8dc"/><defs><radialGradient ' +
      'id="tesape-fairyglow"><stop offset="0" stop-color="#fff3b0" stop-opacity=".9"/><stop offset="1" ' +
      'stop-color="#fff3b0" stop-opacity="0"/></radialGradient></defs>',
    spider: '<g stroke="#2e2433" stroke-width="1.5" fill="none" stroke-linecap="round"><path d="M17 20c-5-3-8-3-11 0M17 ' +
      '23c-5-1-9 0-12 4M17 26c-4 1-7 4-8 8M18 18c-3-4-6-6-9-6M23 20c5-3 8-3 11 0M23 23c5-1 9 0 12 4M23 26c4 1 7 4 8 8M22 ' +
      '18c3-4 6-6 9-6"/></g><circle cx="20" cy="17" r="6.5" fill="#3a2e3f"/><circle cx="20" cy="26" r="4" fill="#2e2433"/>' +
      '<circle cx="18.6" cy="27" r=".9" fill="#ffe08a"/><circle cx="21.4" cy="27" r=".9" fill="#ffe08a"/>',
    ant: '<g stroke="#3a2416" stroke-width="1.3" stroke-linecap="round" fill="none"><path d="M22 20l-3-6M22 20l-3 6M24 ' +
      '20l1-6M24 20l1 6M26 20l4-5M26 20l4 5M33 18l3-4M33 19l4-1"/></g><ellipse cx="15" cy="20" rx="5.5" ry="3.8" ' +
      'fill="#3a2416"/><ellipse cx="24" cy="20" rx="3" ry="2" fill="#4a2e1c"/><circle cx="31" cy="20" r="3" ' +
      'fill="#3a2416"/>',
    snail: '<path d="M5 29c4-3 22-3 30-2 2 0 2 3 0 3H7c-2 0-3-0-2-1Z" fill="#cdb48c"/><path d="M31 27l3-14M28 27l2-13" ' +
      'stroke="#cdb48c" stroke-width="2" stroke-linecap="round"/><circle cx="34" cy="12.5" r="1.6" fill="#3b2a1a"/>' +
      '<circle cx="30" cy="13.5" r="1.5" fill="#3b2a1a"/><circle cx="17" cy="19" r="9.5" fill="#c98a4a"/><path d="M17 ' +
      '19m0-6a6 6 0 1 1-6 6a4 4 0 0 1 4-4a2.5 2.5 0 0 1 2.5 2.5" stroke="#7a4a22" stroke-width="1.6" fill="none"/>'
  };

  /*
   * The little living things of the valley. move: how they go (fly across the screen, walk along a card, line: a row
   * that walks, drop: down on a thread and up again, peek: out of the edge of a card and back, onmap: a while on the
   * map, map: glowing by the lanterns of the map); facing: where the picture looks (right; up for those seen from
   * above; front for those that do not turn); size in pixels; speed in pixels a second.
   */
  var VALLEY = [
    {code: 'firefly', move: 'map', size: 16, art: ['critter.firefly']},
    {code: 'butterfly', move: 'fly', facing: 'up', size: 40, speed: 70, art: ['critter.butterfly.1', 'critter.butterfly.2']},
    {code: 'dragonfly', move: 'fly', facing: 'up', size: 46, speed: 120, art: ['critter.dragonfly']},
    {code: 'bee', move: 'fly', facing: 'right', size: 32, speed: 90, art: ['critter.bee']},
    {code: 'fairy', move: 'fly', facing: 'right', size: 36, speed: 80, art: ['critter.fairy.1', 'critter.fairy.2']},
    {code: 'moth', move: 'fly', facing: 'up', size: 40, speed: 55, art: ['critter.moth']},
    {code: 'bat', move: 'fly', facing: 'front', size: 42, speed: 130, art: ['critter.bat.1', 'critter.bat.2']},
    {code: 'seed', move: 'fly', facing: 'front', size: 36, speed: 28, art: ['critter.seed']},
    {code: 'ladybug', move: 'walk', facing: 'up', size: 44, speed: 40, art: ['critter.ladybug'], fly: 'critter.ladybug.fly'},
    {code: 'snail', move: 'walk', facing: 'right', size: 36, speed: 9, art: ['critter.snail']},
    {code: 'ant', move: 'line', facing: 'right', size: 24, speed: 34, art: ['critter.ant.1', 'critter.ant.2'],
      carry: 'critter.ant.leaf'},
    {code: 'spider', move: 'drop', facing: 'up', size: 40, speed: 60, art: ['critter.spider']},
    {code: 'worm', move: 'peek', facing: 'right', size: 44, art: ['critter.worm']},
    {code: 'frog', move: 'onmap', facing: 'up', size: 34, art: ['critter.frog'], near: 'lago'},
    {code: 'owl', move: 'onmap', facing: 'front', size: 46, art: ['critter.owl.eyes']}
  ];

  /**
   * The little living things of the world of the art pack, or those of the valley.
   *
   * @return {Object[]}
   */
  function all() {
    var own = cfg.art && cfg.art.theme && cfg.art.theme.critters;
    if (own && own.length) {
      // Those of the world, each with its picture in the pack.
      return own.filter(function(c) {
        return T.artUrl(c.art || []);
      });
    }
    return VALLEY.filter(function(c) {
      return SVG[c.code] || T.artUrl(c.art);
    });
  }

  /**
   * The name and the fact of a little living thing, in the language of the page.
   *
   * @param {Object} c
   * @return {{name: string, fact: string}}
   */
  function words(c) {
    if (c.name) {
      var lang = (cfg.lang || 'en').slice(0, 2);
      var pick = function(texts) {
        return texts[lang] || texts.en || texts[Object.keys(texts)[0]] || '';
      };
      return {name: pick(c.name), fact: pick(c.fact || {})};
    }
    return {name: t('app_critter_' + c.code), fact: t('app_critter_' + c.code + '_fact')};
  }

  /**
   * How a little living thing looks: its painted frames, or its drawing.
   *
   * @param {Object} c
   * @param {string} [key] A picture of the pack to show instead (the ladybird flying away).
   * @return {string}
   */
  function look(c, key) {
    var frames = (key ? [key] : c.art || []).map(function(k) {
      return T.artUrl([k]);
    }).filter(Boolean);
    if (frames.length) {
      return frames.map(function(url, i) {
        return '<img class="frame f' + (i + 1) + (frames.length > 1 ? ' two' : '') + '" src="' + esc(url) + '" alt="">';
      }).join('');
    }
    return '<svg viewBox="0 0 40 40" aria-hidden="true">' + (SVG[c.code] || '') + '</svg>';
  }

  /* ---------- The fireflies of the map ---------- */

  /**
   * Fireflies on the map: two by each lantern the class lit and a few more around, blinking and drifting.
   *
   * @param {Object} m The mission, with its places.
   * @return {string} SVG to go into the map.
   */
  function fireflies(m) {
    var kind = all().filter(function(c) {
      return c.move === 'map';
    })[0];
    if (!kind) {
      return '';
    }
    var height = T.mapHeight(m);
    var spots = [];
    m.chapters.forEach(function(ch) {
      if (ch.lit) {
        spots.push([ch.x - 28, ch.y - 20], [ch.x + 30, ch.y + 6]);
      }
    });
    for (var i = 0; i < 4; i++) {
      spots.push([40 + Math.random() * 320, 40 + Math.random() * (height - 80)]);
    }
    var art = T.artUrl(kind.art || []);
    return '<g class="fireflies" aria-hidden="true">' + spots.slice(0, 16).map(function(p) {
      var style = 'style="--dx:' + Math.round(Math.random() * 30 - 15) + 'px;--dy:' + Math.round(Math.random() * 24 - 12) +
        'px;animation-duration:' + (5 + Math.random() * 4).toFixed(1) + 's;animation-delay:-' +
        (Math.random() * 6).toFixed(1) + 's"';
      return '<g class="firefly" ' + style + '><circle cx="' + p[0] + '" cy="' + p[1] + '" r="6" class="halo"/>' +
        (art ? '<image href="' + esc(art) + '" x="' + (p[0] - 6) + '" y="' + (p[1] - 6) + '" width="12" height="12"/>' :
        '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="1.9" class="core"/>') + '</g>';
    }).join('') + '</g>';
  }

  /* ---------- The visitors ---------- */

  var layer = null;
  var busy = false;
  var timer = 0;
  var pace = [25, 55];

  function stage() {
    if (!layer) {
      layer = document.createElement('div');
      layer.className = 'lives';
      layer.setAttribute('aria-hidden', 'true');
      document.body.appendChild(layer);
    }
    return layer;
  }

  /**
   * A little living thing, ready to move: touched, it says what it is and goes away.
   *
   * @param {Object} c
   * @param {Function} away What it does when touched: it must end with done().
   * @return {HTMLElement}
   */
  function make(c, away) {
    var el = document.createElement('span');
    el.className = 'life life-' + c.code;
    el.style.width = el.style.height = c.size + 'px';
    el.innerHTML = '<span class="body">' + look(c) + '</span>';
    el.addEventListener('pointerdown', function(ev) {
      ev.preventDefault();
      ev.stopPropagation();
      if (el.dataset.touched) {
        return;
      }
      el.dataset.touched = '1';
      var w = words(c);
      T.toast('<b>' + esc(w.name) + '</b>' + (w.fact ? ' ' + esc(w.fact) : ''));
      if (T.fx) {
        T.fx.sound('tap');
      }
      away(el);
    });
    return el;
  }

  /** The angle a picture must turn to go from a to b: those seen from above look up; the others, right. */
  function heading(c, dx, dy) {
    if (c.facing === 'front') {
      return '';
    }
    var angle = Math.atan2(dy, dx) * 180 / Math.PI;
    if (c.facing === 'up') {
      return 'rotate(' + Math.round(angle + 90) + 'deg)';
    }
    return dx < 0 ? 'scaleX(-1)' : '';
  }

  function done(el) {
    el.remove();
    busy = false;
    next();
  }

  /** Across the screen, with a soft wave, from one side to the other. */
  function fly(c) {
    var w = window.innerWidth;
    var h = window.innerHeight;
    var right = Math.random() < 0.5;
    var y0 = h * (0.15 + Math.random() * 0.5);
    var y1 = y0 + (Math.random() - 0.5) * h * 0.3;
    var x0 = right ? -c.size : w + c.size;
    var x1 = right ? w + c.size : -c.size;
    var steps = 6;
    var frames = [];
    for (var i = 0; i <= steps; i++) {
      var k = i / steps;
      var x = x0 + (x1 - x0) * k;
      var y = y0 + (y1 - y0) * k + Math.sin(k * Math.PI * 3) * 26;
      var nx = x0 + (x1 - x0) * Math.min(1, k + 0.05);
      var ny = y0 + (y1 - y0) * Math.min(1, k + 0.05) + Math.sin(Math.min(1, k + 0.05) * Math.PI * 3) * 26;
      frames.push({transform: 'translate(' + Math.round(x) + 'px,' + Math.round(y) + 'px) ' + heading(c, nx - x, ny - y)});
    }
    var anim;
    var el = make(c, function(me) {
      anim.updatePlaybackRate(4);
    });
    stage().appendChild(el);
    anim = el.animate(frames, {duration: Math.abs(x1 - x0) / c.speed * 1000, easing: 'linear'});
    anim.onfinish = function() {
      done(el);
    };
  }

  /**
   * The part of the screen in sight: below the header and above the bar of tabs, when they stay on the screen.
   *
   * @return {number[]} Top and bottom, in pixels of the window.
   */
  function sight() {
    var top = 0;
    var bottom = window.innerHeight;
    var hud = document.querySelector('#app .hud');
    var tabs = document.querySelector('#app nav.tabs');
    if (hud) {
      var h = hud.getBoundingClientRect();
      if (h.top <= 1 && h.bottom > 0) {
        top = h.bottom;
      }
    }
    if (tabs) {
      var b = tabs.getBoundingClientRect();
      if (b.top > window.innerHeight / 2 && b.top < bottom) {
        bottom = b.top;
      }
    }
    return [top, bottom];
  }

  /**
   * A card in sight, wide enough to walk along its edge.
   *
   * @return {HTMLElement|null}
   */
  function perch() {
    var view = sight();
    var cards = Array.prototype.filter.call(document.querySelectorAll('#app .card'), function(card) {
      var r = card.getBoundingClientRect();
      return r.width > 220 && r.top > view[0] + 50 && r.top < view[1] - 40;
    });
    return cards.length ? cards[Math.floor(Math.random() * cards.length)] : null;
  }

  /** Along the top edge of a card, with a rest in the middle; the ladybird flies away at the end. */
  function walk(c) {
    var card = perch();
    if (!card) {
      busy = false;
      next();
      return;
    }
    card.classList.add('life-host');
    var width = card.getBoundingClientRect().width;
    var right = Math.random() < 0.5;
    var x0 = right ? -c.size * 0.5 : width - c.size * 0.5;
    var x1 = right ? width * (0.55 + Math.random() * 0.35) : width * (0.1 + Math.random() * 0.35);
    var turn = heading(c, x1 - x0, 0);
    var mid = (x0 + x1) / 2;
    var duration = Math.abs(x1 - x0) / c.speed * 1000 + 1500;
    var el = make(c, function(me) {
      flyAway(c, me, anim);
    });
    el.classList.add('on-edge');
    el.style.top = (-c.size + 4) + 'px';
    if (c.code === 'snail' || c.trail) {
      var trail = document.createElement('span');
      trail.className = 'life-trail';
      trail.style.left = Math.min(x0, x1) + c.size * 0.5 + 'px';
      card.appendChild(trail);
      trail.animate([{width: '0px', marginLeft: right ? '0px' : Math.abs(x1 - x0) + 'px'},
        {width: Math.abs(x1 - x0) + 'px', marginLeft: '0px'}], {duration: duration - 1500, fill: 'forwards'});
      setTimeout(function() {
        trail.animate([{opacity: 1}, {opacity: 0}], {duration: 4000, fill: 'forwards'}).onfinish = function() {
          trail.remove();
        };
      }, duration);
    }
    card.appendChild(el);
    var anim = el.animate([
      {transform: 'translateX(' + x0 + 'px) ' + turn},
      {transform: 'translateX(' + mid + 'px) ' + turn, offset: 0.45},
      {transform: 'translateX(' + mid + 'px) ' + turn, offset: 0.6},
      {transform: 'translateX(' + x1 + 'px) ' + turn}
    ], {duration: duration, easing: 'linear', fill: 'forwards'});
    anim.onfinish = function() {
      flyAway(c, el, anim);
    };
  }

  /** The end of a walk: the ladybird opens its wings and goes; the others just leave, slowly. */
  function flyAway(c, el, anim) {
    if (el.dataset.gone) {
      return;
    }
    el.dataset.gone = '1';
    var at = getComputedStyle(el).transform;
    at = at === 'none' ? '' : at;
    anim.cancel();
    el.style.transform = at;
    if (c.fly && T.artUrl([c.fly])) {
      el.querySelector('.body').innerHTML = look(c, c.fly);
    }
    // Up and away on the screen (the move goes first, so it is not turned with the picture).
    var flies = c.code === 'ladybug' || c.fly;
    el.animate([{transform: 'translate(0,0) ' + at, opacity: 1},
      {transform: (flies ? 'translate(60px,-160px) ' : 'translate(0,0) ') + at, opacity: 0}],
    {duration: flies ? 1400 : 2500, easing: 'ease-in', fill: 'forwards'}).onfinish = function() {
      done(el);
    };
  }

  /** A row that walks along a card: the ants, one after the other. */
  function line(c) {
    var card = perch();
    if (!card) {
      busy = false;
      next();
      return;
    }
    card.classList.add('life-host');
    var width = card.getBoundingClientRect().width;
    var right = Math.random() < 0.5;
    var x0 = right ? -c.size : width;
    var x1 = right ? width : -c.size;
    var count = 4 + Math.floor(Math.random() * 3);
    var left = count;
    var duration = Math.abs(x1 - x0) / c.speed * 1000;
    var turn = heading(c, x1 - x0, 0);
    var walkers = [];
    var scatter = function() {
      walkers.forEach(function(w) {
        w.anim.updatePlaybackRate(5);
      });
    };
    for (var i = 0; i < count; i++) {
      var el = make(c, scatter);
      if (c.carry && i % 3 === 1 && T.artUrl([c.carry])) {
        el.querySelector('.body').innerHTML = look(c, c.carry);
      }
      el.classList.add('on-edge');
      el.style.top = (-c.size + 3) + 'px';
      card.appendChild(el);
      var anim = el.animate([{transform: 'translateX(' + x0 + 'px) ' + turn}, {transform: 'translateX(' + x1 + 'px) ' + turn}],
        {duration: duration, delay: i * 700, easing: 'linear', fill: 'both'});
      walkers.push({el: el, anim: anim});
      anim.onfinish = (function(me) {
        return function() {
          me.remove();
          left--;
          if (!left) {
            busy = false;
            next();
          }
        };
      })(el);
    }
  }

  /** Down on a thread from the top of the screen, a little swing, and up again. */
  function drop(c) {
    var w = window.innerWidth;
    var length = Math.round(window.innerHeight * (0.18 + Math.random() * 0.17));
    var hang = document.createElement('span');
    hang.className = 'life-drop';
    hang.style.left = Math.round(w * (0.12 + Math.random() * 0.76)) + 'px';
    hang.style.height = length + 'px';
    hang.style.top = -length + 'px';
    var anim;
    var el = make(c, function() {
      anim.updatePlaybackRate(3);
    });
    el.style.left = Math.round(-c.size / 2) + 'px';
    el.style.top = Math.round(length - c.size * 0.35) + 'px';
    hang.appendChild(el);
    stage().appendChild(hang);
    var down = 'translateY(' + length + 'px)';
    anim = hang.animate([
      {transform: 'translateY(0)'},
      {transform: down, offset: 0.3},
      {transform: down + ' rotate(5deg)', offset: 0.42},
      {transform: down + ' rotate(-5deg)', offset: 0.56},
      {transform: down + ' rotate(0deg)', offset: 0.66},
      {transform: 'translateY(0)'}
    ], {duration: length / c.speed * 1000 * 2 + 4000, easing: 'ease-in-out'});
    anim.onfinish = function() {
      hang.remove();
      busy = false;
      next();
    };
  }

  /** Out of the top edge of a card, a look around, and back in. */
  function peek(c) {
    var card = perch();
    if (!card) {
      busy = false;
      next();
      return;
    }
    card.classList.add('life-host');
    var width = card.getBoundingClientRect().width;
    var anim;
    var el = make(c, function() {
      anim.updatePlaybackRate(3);
    });
    el.classList.add('on-edge', 'peeking');
    el.style.top = (-c.size + 2) + 'px';
    el.style.left = Math.round(width * (0.15 + Math.random() * 0.7)) + 'px';
    card.appendChild(el);
    var hidden = 'translateY(' + Math.round(c.size * 0.7) + 'px)';
    anim = el.animate([
      {transform: hidden, opacity: 0},
      {transform: 'translateY(0)', opacity: 1, offset: 0.2},
      {transform: 'translateY(0) rotate(-6deg)', opacity: 1, offset: 0.45},
      {transform: 'translateY(0) rotate(6deg)', opacity: 1, offset: 0.65},
      {transform: 'translateY(0)', opacity: 1, offset: 0.8},
      {transform: hidden, opacity: 0}
    ], {duration: 6500, easing: 'ease-in-out'});
    anim.onfinish = function() {
      done(el);
    };
  }

  /** A while on the map: by its place if it has one (the frog by the lake), or anywhere in sight; the owl blinks. */
  function onmap(c) {
    var wrap = document.querySelector('#app .map-wrap');
    var svg = wrap && wrap.querySelector('svg.map');
    if (!svg) {
      busy = false;
      next();
      return;
    }
    wrap.classList.add('life-host');
    var box = svg.getBoundingClientRect();
    var view = svg.viewBox.baseVal;
    var place = c.near && Array.prototype.filter.call(svg.querySelectorAll('.place[data-art]'), function(g) {
      return g.dataset.art.indexOf(c.near) >= 0;
    })[0];
    // Only in the part of the map in sight, from the top of the map.
    var shown = wrap.getBoundingClientRect();
    var view = sight();
    var from = Math.max(box.top, shown.top, view[0]) - box.top;
    var to = Math.min(box.bottom, shown.bottom, view[1]) - box.top;
    if (to - from < c.size * 2) {
      busy = false;
      next();
      return;
    }
    var x = box.width * (0.15 + Math.random() * 0.7);
    var y = from + c.size + Math.random() * (to - from - 2 * c.size);
    if (place) {
      var ring = place.querySelector('.ring');
      var px = (ring.cx.baseVal.value + 34) * box.width / view.width;
      var py = (ring.cy.baseVal.value - 10) * box.height / view.height;
      if (py > from + c.size && py < to - c.size) {
        x = px;
        y = py;
      }
    }
    var anim;
    var el = make(c, function() {
      anim.updatePlaybackRate(3);
    });
    el.classList.add('on-map', 'life-' + c.move);
    el.style.left = Math.round(x - c.size / 2) + 'px';
    el.style.top = Math.round(y - c.size / 2) + 'px';
    wrap.appendChild(el);
    anim = el.animate(c.code === 'frog' ? [
      {opacity: 0, transform: 'translateY(0)'},
      {opacity: 1, transform: 'translateY(0)', offset: 0.15},
      {opacity: 1, transform: 'translateY(-10px)', offset: 0.3},
      {opacity: 1, transform: 'translateY(0)', offset: 0.4},
      {opacity: 1, transform: 'translateY(-10px)', offset: 0.7},
      {opacity: 1, transform: 'translateY(0)', offset: 0.8},
      {opacity: 0, transform: 'translate(24px,-16px)'}
    ] : [
      {opacity: 0, transform: 'scaleY(1)'},
      {opacity: 1, transform: 'scaleY(1)', offset: 0.2},
      {opacity: 1, transform: 'scaleY(1)', offset: 0.42},
      {opacity: 1, transform: 'scaleY(.1)', offset: 0.45},
      {opacity: 1, transform: 'scaleY(1)', offset: 0.48},
      {opacity: 1, transform: 'scaleY(1)', offset: 0.7},
      {opacity: 1, transform: 'scaleY(.1)', offset: 0.73},
      {opacity: 1, transform: 'scaleY(1)', offset: 0.76},
      {opacity: 0, transform: 'scaleY(1)'}
    ], {duration: 7000, easing: 'ease-in-out'});
    anim.onfinish = function() {
      done(el);
    };
  }

  var MOVES = {fly: fly, walk: walk, line: line, drop: drop, peek: peek, onmap: onmap};

  /** A visitor, if the moment is good: never in a scene, a battle, a dialog or a hidden page. */
  function visit() {
    timer = 0;
    var away = document.hidden || document.body.classList.contains('in-scene') ||
      document.querySelector('.badge-moment, .battle-layer, [role="dialog"]');
    var kinds = all().filter(function(c) {
      return MOVES[c.move];
    });
    if (busy || away || !kinds.length) {
      next();
      return;
    }
    busy = true;
    var c = kinds[Math.floor(Math.random() * kinds.length)];
    MOVES[c.move](c);
  }

  function next() {
    if (!timer && !still && pace) {
      timer = setTimeout(visit, (pace[0] + Math.random() * (pace[1] - pace[0])) * 1000);
    }
  }

  T.critters = {
    fireflies: fireflies,
    /**
     * Visitors from now on, every so many seconds.
     *
     * @param {number[]} [every] Fewest and most seconds between two visitors.
     */
    start: function(every) {
      if (every) {
        pace = every;
      }
      if (!timer && !busy) {
        timer = setTimeout(visit, (8 + Math.random() * 8) * 1000);
      }
    },
    /**
     * One visitor now, of a kind or any: to see them.
     *
     * @param {string} [code]
     */
    now: function(code) {
      var kinds = all().filter(function(c) {
        return MOVES[c.move] && (!code || c.code === code);
      });
      if (kinds.length && !busy) {
        clearTimeout(timer);
        timer = 0;
        busy = true;
        MOVES[kinds[0].move](kinds[Math.floor(Math.random() * kinds.length)]);
      }
    }
  };
})();
