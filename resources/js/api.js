/**
 * Helper fetch tipis untuk panggil JSON API (routes/api.php) dari halaman portal
 * yang login-nya session-based (bukan token). Sanctum stateful (statefulApi() di
 * bootstrap/app.php) mengenali request ini via cookie session, tapi tetap butuh
 * header X-XSRF-TOKEN untuk lolos verifikasi CSRF pada request non-GET.
 */
function getXsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

export async function apiFetch(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        'X-XSRF-TOKEN': getXsrfToken(),
        ...(options.headers || {}),
    };

    if (options.body && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(path, {
        ...options,
        credentials: 'same-origin',
        headers,
    });

    const data = await response.json().catch(() => null);

    if (!response.ok) {
        throw Object.assign(new Error(data?.message || 'Request gagal'), { status: response.status, data });
    }

    return data;
}
