/**
 * Heartbeat Control Center — frotas e painel ADM.
 */
(function () {
    'use strict';

    function pingUrl() {
        var app = typeof window.__SF_APP_BASE__ === 'string' ? window.__SF_APP_BASE__.replace(/\/+$/, '') : '';
        var adm = typeof window.__ADM_API_BASE__ === 'string' ? window.__ADM_API_BASE__.replace(/\/+$/, '') : '';
        if (adm) {
            return adm + '/telemetry_ping.php';
        }
        if (app) {
            return app + '/pages_adm/api/v1/telemetry_ping.php';
        }
        return '/pages_adm/api/v1/telemetry_ping.php';
    }

    function ping() {
        var fd = new FormData();
        fd.append('pagina', window.location.pathname + window.location.search);
        fetch(pingUrl(), { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ping);
    } else {
        ping();
    }
    setInterval(ping, 30000);
})();
