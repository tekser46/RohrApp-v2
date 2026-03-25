/**
 * RohrApp+ — License Page
 */
async function renderLicensePage(renderId) {
    panelLayout('license', 'Lizenz', '<div class="loading-inline"><div class="loading-spinner"></div></div>');

    try {
        const [licRes, pkgRes] = await Promise.all([
            API.get('license'),
            API.get('license/packages'),
        ]);

        if (renderId && renderId !== Router.renderCount) return;
        const lic = licRes.data;
        const packages = pkgRes.data;
        const currentSlug = lic.package_slug;

        const planColors = { demo: '#64748b', starter: '#0066a1', professional: '#059669' };
        const planIcons  = { demo: '🆓', starter: '⭐', professional: '👑' };

        // License info card
        let licenseHtml = `
        <div class="card">
            <div class="card-header"><span class="card-title">Ihre aktuelle Lizenz</span></div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px">
                    <div style="width:64px;height:64px;border-radius:16px;background:${planColors[currentSlug]}12;display:flex;align-items:center;justify-content:center;font-size:32px">${planIcons[currentSlug] || '📦'}</div>
                    <div>
                        <div style="font-size:24px;font-weight:800;color:${planColors[currentSlug]}">${esc(lic.package_name)}</div>
                        <span class="badge badge-${lic.status === 'active' ? 'success' : lic.status === 'trial' ? 'info' : 'muted'}">${esc(lic.status)}</span>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div class="info-box"><div class="info-label">Lizenzschlüssel</div><div class="info-value" style="font-family:monospace">${esc(lic.license_key)}</div></div>
                    <div class="info-box"><div class="info-label">Paketpreis</div><div class="info-value">${lic.price_monthly > 0 ? formatMoney(lic.price_monthly) + ' / Monat' : 'Kostenlos'}</div></div>
                    <div class="info-box"><div class="info-label">Startdatum</div><div class="info-value">${formatDate(lic.starts_at)}</div></div>
                    <div class="info-box"><div class="info-label">Gültig bis</div><div class="info-value">${lic.expires_at ? formatDate(lic.expires_at) : 'Unbegrenzt'}</div></div>
                </div>

                <!-- Widget Integration Code -->
                <div style="margin-top:20px;padding:16px;background:#f0f7ff;border-radius:10px;border:1px solid #d0e3f7">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                        <span style="font-weight:700;font-size:14px;color:var(--primary)">📋 Website Integration</span>
                        <button class="btn btn-sm btn-primary" onclick="copyWidgetCode('${esc(lic.license_key)}')">Code kopieren</button>
                    </div>
                    <p style="font-size:12px;color:var(--text-light);margin-bottom:10px">Fügen Sie diesen Code vor &lt;/body&gt; in Ihre Website ein. Kontaktformulare werden automatisch erfasst.</p>
                    <pre id="widgetCode" style="background:#1e293b;color:#22d3ee;padding:14px;border-radius:8px;font-size:12px;overflow-x:auto;margin:0;user-select:all">&lt;script src="${window.location.origin}/widget.js" data-key="${esc(lic.license_key)}"&gt;&lt;/script&gt;</pre>
                </div>
            </div>
        </div>`;

        // Package comparison cards
        let packagesHtml = '<div style="margin-top:24px"><h2 style="font-size:18px;font-weight:700;margin-bottom:16px">Verfügbare Pakete</h2><div class="packages-grid">';

        packages.forEach(pkg => {
            const isCurrent = pkg.slug === currentSlug;
            const color = planColors[pkg.slug] || '#64748b';
            const icon  = planIcons[pkg.slug] || '📦';

            let features = '';
            features += `<li>${pkg.has_messages ? '✓' : '✗'} Nachrichten</li>`;
            features += `<li>${pkg.has_email_inbox ? '✓' : '✗'} Email Inbox</li>`;
            features += `<li>${pkg.has_call_logs ? '✓' : '✗'} Anrufverfolgung</li>`;
            features += `<li>${pkg.max_sipgate_numbers > 0 ? '✓ ' + pkg.max_sipgate_numbers + ' Sipgate Nummern' : '✗ Sipgate'}</li>`;
            features += `<li>${pkg.max_websites > 0 ? '✓ ' + pkg.max_websites + ' Websites' : '✗ Websites'}</li>`;

            packagesHtml += `
            <div class="package-card ${isCurrent ? 'package-current' : ''}" style="border-color:${isCurrent ? color : 'var(--border)'}">
                <div class="package-header" style="color:${color}">
                    <span style="font-size:28px">${icon}</span>
                    <div class="package-name">${esc(pkg.name)}</div>
                    <div class="package-price">${pkg.price_monthly > 0 ? formatMoney(pkg.price_monthly) + '<small>/Monat</small>' : 'Kostenlos'}</div>
                </div>
                <div class="package-description">${esc(pkg.description)}</div>
                <ul class="package-features">${features}</ul>
                <div class="package-action">
                    ${isCurrent
                        ? '<button class="btn btn-secondary btn-full" disabled>Aktuelles Paket</button>'
                        : '<button class="btn btn-primary btn-full" onclick="requestUpgrade(' + pkg.id + ',\'' + esc(pkg.name) + '\')">Upgrade anfragen</button>'}
                </div>
            </div>`;
        });

        packagesHtml += '</div></div>';

        document.querySelector('.content-body').innerHTML = licenseHtml + packagesHtml;

    } catch (e) {
        document.querySelector('.content-body').innerHTML = `<div class="empty-state"><p>Fehler: ${esc(e.message)}</p></div>`;
    }
}

/**
 * Request upgrade
 */
async function requestUpgrade(packageId, packageName) {
    const message = prompt('Nachricht (optional):') || '';
    try {
        await API.post('upgrade-requests', { package_id: parseInt(packageId), message });
        Toast.success('Upgrade-Anfrage für ' + packageName + ' wurde gesendet!');
        renderLicensePage(Router.renderCount);
    } catch (err) {
        Toast.error(err.message);
    }
}

function copyWidgetCode(key) {
    var code = '<script src="' + window.location.origin + '/widget.js" data-key="' + key + '"><\/script>';
    navigator.clipboard.writeText(code).then(function () {
        Toast.success('Widget-Code kopiert!');
    });
}
