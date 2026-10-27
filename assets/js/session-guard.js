(function () {
    'use strict';

    var redirecting = false;

    function goLogin() {
        if (redirecting) {
            return;
        }
        redirecting = true;
        var path = window.location.pathname.replace(/^.*\//, '') + window.location.search;
        window.location.href = 'login.php?returnurl=' + encodeURIComponent(path);
    }

    function isExpired(status, body) {
        if (status === 401) {
            return true;
        }
        return !!(body && body.session_expired === true);
    }

    if (window.jQuery) {
        window.jQuery(document).ajaxComplete(function (event, xhr) {
            var body = xhr && xhr.responseJSON;
            if (isExpired(xhr && xhr.status, body)) {
                goLogin();
            }
        });
    }

    if (typeof window.fetch === 'function') {
        var originalFetch = window.fetch;
        window.fetch = function () {
            return originalFetch.apply(this, arguments).then(function (response) {
                if (response && response.status === 401) {
                    var type = response.headers.get('Content-Type') || '';
                    if (type.indexOf('application/json') !== -1) {
                        response.clone().json().then(function (body) {
                            if (isExpired(401, body)) {
                                goLogin();
                            }
                        }).catch(function () {});
                    }
                }
                return response;
            });
        };
    }
})();
