/**
 * Tee Shirt Ali — Tee Party Countdown Timer
 * tsa-tee-party.js  |  Version 2.0.0
 *
 * Reads data-end ISO datetime from #tsa-countdown and counts down
 * to that moment, updating #cd-days, #cd-hours, #cd-mins, #cd-secs.
 */
(function () {
    'use strict';

    var $countdown  = document.getElementById('tsa-countdown');
    if (!$countdown) return;

    var endISO      = $countdown.getAttribute('data-end');
    if (!endISO)    return;

    var endTime     = new Date(endISO).getTime();
    if (isNaN(endTime)) return;

    var elDays      = document.getElementById('cd-days');
    var elHours     = document.getElementById('cd-hours');
    var elMins      = document.getElementById('cd-mins');
    var elSecs      = document.getElementById('cd-secs');
    var elLive      = document.getElementById('tsa-party-live');   // shown when drop goes live
    var elPre       = document.getElementById('tsa-party-pre');    // hidden when drop goes live

    function pad(n) {
        return String(Math.max(0, n)).padStart(2, '0');
    }

    function setExpired() {
        // Switch UI to "drop is live" state
        $countdown.classList.add('tsa-countdown--expired');
        if (elLive) elLive.style.display = '';
        if (elPre)  elPre.style.display  = 'none';

        // Swap hero kicker text if element exists
        var kicker = document.querySelector('.tsa-party-hero__kicker');
        if (kicker) kicker.textContent = '🎉 Drop is Live — Shop Now!';
    }

    function tick() {
        var now  = Date.now();
        var diff = endTime - now;

        if (diff <= 0) {
            if (elDays)  elDays.textContent  = '00';
            if (elHours) elHours.textContent = '00';
            if (elMins)  elMins.textContent  = '00';
            if (elSecs)  elSecs.textContent  = '00';
            setExpired();
            clearInterval(timer);
            return;
        }

        var totalSecs = Math.floor(diff / 1000);
        var days      = Math.floor(totalSecs / 86400);
        var hours     = Math.floor((totalSecs % 86400) / 3600);
        var mins      = Math.floor((totalSecs % 3600)  / 60);
        var secs      = totalSecs % 60;

        if (elDays)  elDays.textContent  = pad(days);
        if (elHours) elHours.textContent = pad(hours);
        if (elMins)  elMins.textContent  = pad(mins);
        if (elSecs)  elSecs.textContent  = pad(secs);
    }

    // Run immediately, then every second
    tick();
    var timer = setInterval(tick, 1000);

    /* ── Urgency strip: refresh pulse every 10 min ── */
    function refreshUrgency() {
        var strip = document.querySelector('.tsa-party-urgency');
        if (!strip) return;
        strip.style.opacity = '0';
        setTimeout(function () { strip.style.transition = 'opacity .5s'; strip.style.opacity = '1'; }, 50);
    }
    setInterval(refreshUrgency, 600000);

}());
