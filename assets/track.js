/* Glass Engagement Time. Created by Dulal Das. No cookies, no storage, no third parties. */
(function () {
	'use strict';
	var cfg = window.GlassEngagement;
	if (!cfg || !cfg.url || navigator.webdriver) { return; }
	var active = 0, lastAct = Date.now(), maxPct = 0, first = 1, sentSecs = 0;
	function pct() {
		var d = document.documentElement, h = Math.max(d.scrollHeight, document.body ? document.body.scrollHeight : 0);
		if (h <= 0) { return 0; }
		var p = Math.round(((window.pageYOffset || d.scrollTop || 0) + (window.innerHeight || d.clientHeight)) / h * 100);
		return Math.max(0, Math.min(100, p));
	}
	function act() { lastAct = Date.now(); }
	function onScroll() { act(); var p = pct(); if (p > maxPct) { maxPct = p; } }
	['mousemove', 'keydown', 'touchstart', 'click', 'wheel'].forEach(function (e) { window.addEventListener(e, act, { passive: true }); });
	window.addEventListener('scroll', onScroll, { passive: true });
	maxPct = pct();
	setInterval(function () {
		if (document.visibilityState === 'visible' && Date.now() - lastAct < 15000) { active++; }
	}, 1000);
	function send() {
		var secs = active - sentSecs;
		if (first && secs < 1 && maxPct < 1) { return; }
		if (!first && secs < 1) { return; }
		var body = JSON.stringify({ post: cfg.post, secs: secs, pct: maxPct, first: first });
		sentSecs = active; first = 0;
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(cfg.url, new Blob([body], { type: 'application/json' }))) { return; }
		} catch (e) {}
		try { fetch(cfg.url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'omit' }); } catch (e) {}
	}
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { send(); } });
	window.addEventListener('pagehide', send);
})();
