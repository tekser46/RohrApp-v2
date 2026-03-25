/**
 * RohrApp+ Widget v1.0
 * Usage: <script src="https://rohrapp.de/widget.js" data-key="LICENSE_KEY"></script>
 *
 * Features:
 * - Auto-captures all form submissions on the page
 * - Sends form data to RohrApp+ API
 * - Shows success/error feedback to user
 * - Ready for future: live chat widget
 */
(function () {
    'use strict';

    // ── Config ──
    var script = document.currentScript || document.querySelector('script[data-key]');
    if (!script) return;

    var LICENSE_KEY = script.getAttribute('data-key');
    if (!LICENSE_KEY) { console.warn('RohrApp+: data-key fehlt'); return; }

    var API_BASE = script.src.replace(/\/widget\.js.*$/, '') + '/api/webhook/contact';
    var WIDGET_ID = 'rohrapp-widget';

    // ── Styles ──
    var css = document.createElement('style');
    css.textContent = [
        '.rohrapp-toast{position:fixed;bottom:24px;right:24px;padding:14px 24px;border-radius:10px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;color:#fff;z-index:999999;opacity:0;transform:translateY(20px);transition:all .3s ease;box-shadow:0 4px 20px rgba(0,0,0,.15);max-width:360px}',
        '.rohrapp-toast.show{opacity:1;transform:translateY(0)}',
        '.rohrapp-toast.success{background:linear-gradient(135deg,#059669,#10b981)}',
        '.rohrapp-toast.error{background:linear-gradient(135deg,#dc2626,#ef4444)}',
        '.rohrapp-toast .rohrapp-close{position:absolute;top:6px;right:10px;background:none;border:none;color:#fff;font-size:16px;cursor:pointer;opacity:.7}',
        '.rohrapp-toast .rohrapp-close:hover{opacity:1}',
        '.rohrapp-sending{position:relative;pointer-events:none;opacity:.7}',
        '.rohrapp-sending::after{content:"";position:absolute;top:50%;left:50%;width:20px;height:20px;margin:-10px 0 0 -10px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:rohrapp-spin .6s linear infinite}',
        '@keyframes rohrapp-spin{to{transform:rotate(360deg)}}',
    ].join('\n');
    document.head.appendChild(css);

    // ── Toast notification ──
    function showToast(msg, type) {
        // Remove existing
        var old = document.querySelector('.rohrapp-toast');
        if (old) old.remove();

        var el = document.createElement('div');
        el.className = 'rohrapp-toast ' + (type || 'success');
        el.innerHTML = msg + '<button class="rohrapp-close" onclick="this.parentElement.remove()">&times;</button>';
        document.body.appendChild(el);

        setTimeout(function () { el.classList.add('show'); }, 10);
        setTimeout(function () {
            el.classList.remove('show');
            setTimeout(function () { el.remove(); }, 300);
        }, 5000);
    }

    // ── Extract form fields intelligently ──
    function extractFormData(form) {
        var data = {};
        var fd = new FormData(form);

        // Try to map fields by name/type/placeholder
        fd.forEach(function (value, key) {
            var input = form.querySelector('[name="' + key + '"]');
            var k = key.toLowerCase();
            var type = input ? input.type : '';
            var placeholder = input ? (input.placeholder || '').toLowerCase() : '';

            // Skip hidden, csrf, token fields
            if (type === 'hidden' || k.indexOf('csrf') >= 0 || k.indexOf('token') >= 0 || k.indexOf('_') === 0) return;
            // Skip passwords
            if (type === 'password') return;
            // Skip submit buttons
            if (type === 'submit') return;

            // Smart field mapping
            if (k === 'name' || k === 'fullname' || k === 'full_name' || k === 'your-name' || k === 'vorname' ||
                placeholder.indexOf('name') >= 0 || placeholder.indexOf('ihr name') >= 0) {
                data.name = (data.name ? data.name + ' ' : '') + value;
            } else if (k === 'nachname' || k === 'last_name' || k === 'lastname' || k === 'surname') {
                data.name = (data.name ? data.name + ' ' : '') + value;
            } else if (k === 'email' || k === 'e-mail' || k === 'your-email' || k === 'mail' || type === 'email' ||
                placeholder.indexOf('email') >= 0 || placeholder.indexOf('e-mail') >= 0) {
                data.email = value;
            } else if (k === 'phone' || k === 'telefon' || k === 'tel' || k === 'your-phone' || k === 'mobile' || k === 'handy' || type === 'tel' ||
                placeholder.indexOf('telefon') >= 0 || placeholder.indexOf('phone') >= 0) {
                data.phone = value;
            } else if (k === 'subject' || k === 'betreff' || k === 'your-subject' ||
                placeholder.indexOf('betreff') >= 0 || placeholder.indexOf('subject') >= 0) {
                data.subject = value;
            } else if (k === 'message' || k === 'nachricht' || k === 'your-message' || k === 'comment' || k === 'kommentar' || k === 'bemerkung' || k === 'anliegen' ||
                (input && input.tagName === 'TEXTAREA') ||
                placeholder.indexOf('nachricht') >= 0 || placeholder.indexOf('message') >= 0) {
                data.message = value;
            } else {
                // Unknown field — append to message
                data.message = (data.message ? data.message + '\n' : '') + key + ': ' + value;
            }
        });

        // Add page info
        data.page_url = window.location.href;
        data.page_title = document.title;

        return data;
    }

    // ── Send to RohrApp+ ──
    function sendToRohrApp(formData, submitBtn) {
        var url = API_BASE + '?token=' + encodeURIComponent(LICENSE_KEY);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onload = function () {
            if (submitBtn) {
                submitBtn.classList.remove('rohrapp-sending');
                submitBtn.disabled = false;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                showToast('✓ Nachricht erfolgreich gesendet!', 'success');
            } else {
                var errMsg = 'Fehler beim Senden';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.error && resp.error.message) errMsg = resp.error.message;
                } catch (e) {}
                showToast('✗ ' + errMsg, 'error');
            }
        };

        xhr.onerror = function () {
            if (submitBtn) {
                submitBtn.classList.remove('rohrapp-sending');
                submitBtn.disabled = false;
            }
            showToast('✗ Netzwerkfehler — bitte versuchen Sie es erneut.', 'error');
        };

        xhr.send(JSON.stringify(formData));
    }

    // ── Intercept form submissions ──
    function interceptForms() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM') return;

            // Skip forms with data-rohrapp="ignore"
            if (form.getAttribute('data-rohrapp') === 'ignore') return;

            // Skip login/search forms
            var action = (form.action || '').toLowerCase();
            if (action.indexOf('login') >= 0 || action.indexOf('search') >= 0 || action.indexOf('register') >= 0) return;

            // Check if it looks like a contact form (has email or textarea)
            var hasEmail = form.querySelector('input[type="email"], input[name*="email"], input[name*="mail"]');
            var hasTextarea = form.querySelector('textarea');
            var hasPhone = form.querySelector('input[type="tel"], input[name*="phone"], input[name*="telefon"]');

            // Only intercept if it looks like a contact form
            if (!hasEmail && !hasTextarea && !hasPhone) return;

            // Extract data before form resets
            var formData = extractFormData(form);

            // Find submit button for loading state
            var submitBtn = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
            if (submitBtn) {
                submitBtn.classList.add('rohrapp-sending');
                submitBtn.disabled = true;
            }

            // Send to RohrApp+ (don't prevent default — let form submit normally too)
            sendToRohrApp(formData, submitBtn);

        }, true); // capture phase — fires before other handlers
    }

    // ── Init ──
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', interceptForms);
    } else {
        interceptForms();
    }

    // ── Public API (for future live chat etc.) ──
    window.RohrApp = window.RohrApp || {};
    window.RohrApp.key = LICENSE_KEY;
    window.RohrApp.sendMessage = function (data) {
        sendToRohrApp(data);
    };
    // Placeholder for live chat — will be activated later
    window.RohrApp.chat = {
        open: function () { console.log('RohrApp+ Live Chat — coming soon'); },
        close: function () {},
    };

    console.log('RohrApp+ Widget v1.0 geladen ✓');
})();
