/**
 * Seasonal Modes: shared behaviour
 *
 * 1. Keeps the decorative corner layer clear of a sticky/fixed header and a
 *    fixed bottom navigation (selectors come from the site adapter).
 * 2. Gives the header a positioning context for the stripe when the theme
 *    leaves it static.
 * 3. First visit each season: a short intro from the pack ("crawl": a few
 *    sprites crawl up from the bottom of the window and leave; "snow": flakes
 *    drift down from the top and fade). Remembered in localStorage per pack and year; if
 *    storage is not available the intro still runs at most once per page
 *    view. Skipped when the visitor prefers reduced motion. The overlay never
 *    takes pointer events and is removed when it finishes.
 *
 * Loaded only while a pack is active.
 */
(function () {
    'use strict';

    var config = window.seasonalModes || {};
    var body = document.body;

    if (!body || !body.classList.contains('sm-active')) {
        return;
    }

    function query(selector) {
        if (!selector) {
            return null;
        }
        try {
            return document.querySelector(selector);
        } catch (e) {
            return null;
        }
    }

    /* ---------- Header stripe context ---------- */

    var header = query(config.headerSelector);
    if (config.headerStripe && header && window.getComputedStyle(header).position === 'static') {
        header.classList.add('sm-header-relative');
    }

    /* ---------- Corner insets ---------- */

    function visibleHeight(el) {
        if (!el) {
            return 0;
        }
        var style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') {
            return 0;
        }
        return el.getBoundingClientRect().height;
    }

    function updateInsets() {
        var top = 0;
        if (header) {
            var position = window.getComputedStyle(header).position;
            if (position === 'sticky' || position === 'fixed') {
                top = Math.max(0, Math.round(header.getBoundingClientRect().bottom));
            }
        }
        var bottomNav = query(config.bottomNavSelector);
        var bottom = 0;
        if (bottomNav && window.getComputedStyle(bottomNav).position === 'fixed') {
            bottom = Math.round(visibleHeight(bottomNav));
        }
        body.style.setProperty('--sm-top', top + 'px');
        body.style.setProperty('--sm-bottom', bottom + 'px');
    }

    var insetFrame = 0;
    function scheduleInsets() {
        if (insetFrame) {
            return;
        }
        insetFrame = window.requestAnimationFrame(function () {
            insetFrame = 0;
            updateInsets();
        });
    }

    updateInsets();
    window.addEventListener('resize', scheduleInsets, { passive: true });
    window.addEventListener('scroll', scheduleInsets, { passive: true });
    window.addEventListener('load', updateInsets);

    /* ---------- First-visit intro ---------- */

    var intro = config.intro || {};
    var STORAGE_KEY = config.storageKey || 'sm_intro_seen';

    function hasSeenIntro() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function rememberIntro() {
        try {
            window.localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            /* Storage blocked: the intro still runs only once on this page view. */
        }
    }

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    var introStarted = false;

    function runCrawl() {
        var width = window.innerWidth || document.documentElement.clientWidth;
        var height = window.innerHeight || document.documentElement.clientHeight;
        var count = width < 600 ? 3 : (width < 1200 ? 5 : 7);
        var layer = document.createElement('div');
        layer.className = 'sm-intro';
        layer.setAttribute('aria-hidden', 'true');

        var animations = [];
        var lane = width / count;

        for (var i = 0; i < count; i++) {
            var size = Math.round((width < 600 ? 26 : 34) + Math.random() * 14);
            var x = Math.round(lane * i + Math.random() * Math.max(0, lane - size));
            x = Math.min(Math.max(0, x), Math.max(0, width - size));
            var peak = Math.round(height * (0.25 + Math.random() * 0.4));
            var drift = Math.round((Math.random() - 0.5) * 60);
            var duration = 4200 + Math.random() * 1800;
            var delay = Math.random() * 900;

            var img = document.createElement('img');
            img.className = 'sm-sprite';
            img.src = intro.image;
            img.alt = '';
            img.width = size;
            img.height = size;
            img.decoding = 'async';
            img.style.left = x + 'px';
            layer.appendChild(img);

            // top: 100% puts each sprite just below the window; translate up from there.
            var climb = -(peak + size);
            animations.push(img.animate([
                { transform: 'translate(0, 0) rotate(0deg)', opacity: 1, offset: 0 },
                { transform: 'translate(' + (drift * 0.4) + 'px, ' + Math.round(climb * 0.35) + 'px) rotate(-8deg)', opacity: 1, offset: 0.25 },
                { transform: 'translate(' + drift + 'px, ' + Math.round(climb * 0.75) + 'px) rotate(7deg)', opacity: 1, offset: 0.5 },
                { transform: 'translate(' + drift + 'px, ' + climb + 'px) rotate(0deg)', opacity: 1, offset: 0.62 },
                { transform: 'translate(' + drift + 'px, ' + climb + 'px) rotate(180deg)', opacity: 1, offset: 0.72 },
                { transform: 'translate(' + drift + 'px, ' + Math.round(climb * 0.4) + 'px) rotate(180deg)', opacity: 0.6, offset: 0.9 },
                { transform: 'translate(' + drift + 'px, 0) rotate(180deg)', opacity: 0, offset: 1 }
            ], {
                duration: duration,
                delay: delay,
                easing: 'ease-in-out',
                fill: 'both'
            }));
        }

        finishIntro(layer, animations);
    }

    function runSnow() {
        var width = window.innerWidth || document.documentElement.clientWidth;
        var height = window.innerHeight || document.documentElement.clientHeight;
        var count = width < 600 ? 12 : (width < 1200 ? 18 : 26);
        var layer = document.createElement('div');
        layer.className = 'sm-intro';
        layer.setAttribute('aria-hidden', 'true');

        var animations = [];
        for (var i = 0; i < count; i++) {
            var size = Math.round((width < 600 ? 12 : 14) + Math.random() * 16);
            var x = Math.round(Math.random() * Math.max(0, width - size));
            var fall = Math.round(height * (0.55 + Math.random() * 0.45)) + size;
            var sway = Math.round(20 + Math.random() * 40) * (Math.random() < 0.5 ? -1 : 1);
            var spin = Math.round(90 + Math.random() * 180) * (Math.random() < 0.5 ? -1 : 1);

            var img = document.createElement('img');
            img.className = 'sm-sprite sm-sprite--fall';
            img.src = intro.image;
            img.alt = '';
            img.width = size;
            img.height = size;
            img.decoding = 'async';
            img.style.left = x + 'px';
            layer.appendChild(img);

            // bottom: 100% puts each flake just above the window; it drifts side to side as it falls.
            animations.push(img.animate([
                { transform: 'translate(0, 0) rotate(0deg)', opacity: 0, offset: 0 },
                { transform: 'translate(' + Math.round(sway * 0.6) + 'px, ' + Math.round(fall * 0.3) + 'px) rotate(' + Math.round(spin * 0.3) + 'deg)', opacity: 1, offset: 0.25 },
                { transform: 'translate(' + Math.round(-sway * 0.4) + 'px, ' + Math.round(fall * 0.65) + 'px) rotate(' + Math.round(spin * 0.65) + 'deg)', opacity: 0.9, offset: 0.6 },
                { transform: 'translate(' + sway + 'px, ' + fall + 'px) rotate(' + spin + 'deg)', opacity: 0, offset: 1 }
            ], {
                duration: 4500 + Math.random() * 2500,
                delay: Math.random() * 1500,
                easing: 'linear',
                fill: 'both'
            }));
        }

        finishIntro(layer, animations);
    }

    function finishIntro(layer, animations) {
        body.appendChild(layer);

        var removed = false;
        function cleanup() {
            if (removed) {
                return;
            }
            removed = true;
            if (layer.parentNode) {
                layer.parentNode.removeChild(layer);
            }
        }

        var finished = animations.map(function (animation) {
            return animation.finished ? animation.finished.catch(function () {}) : null;
        });
        if (typeof Promise !== 'undefined' && finished.every(Boolean)) {
            Promise.all(finished).then(cleanup, cleanup);
        }
        // Safety net in case an animation never resolves (e.g. throttled tab).
        window.setTimeout(cleanup, 9000);
    }

    var INTROS = { crawl: runCrawl, snow: runSnow };

    function runIntro() {
        if (introStarted) {
            return;
        }
        introStarted = true;
        rememberIntro();

        if (!intro.image || !INTROS[intro.type] || typeof Element.prototype.animate !== 'function') {
            return;
        }
        INTROS[intro.type]();
    }

    function maybeRunIntro() {
        if (!intro.type || prefersReducedMotion() || hasSeenIntro()) {
            return;
        }
        if (document.visibilityState === 'hidden') {
            document.addEventListener('visibilitychange', function onVisible() {
                if (document.visibilityState === 'visible') {
                    document.removeEventListener('visibilitychange', onVisible);
                    maybeRunIntro();
                }
            });
            return;
        }
        runIntro();
    }

    if (document.readyState === 'complete') {
        maybeRunIntro();
    } else {
        window.addEventListener('load', maybeRunIntro, { once: true });
    }
})();
