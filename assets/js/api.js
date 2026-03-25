/**
 * RohrApp+ — API Client
 * All backend communication goes through here.
 */
const API = {
    baseUrl: '/api',
    csrfToken: null,

    /**
     * Make API request
     * @param {string} endpoint - e.g. "auth/login"
     * @param {object} options - { method, body, params }
     * @returns {Promise<object>}
     */
    async request(endpoint, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        let url = `${this.baseUrl}/${endpoint}`;

        if (options.params) {
            url += '?' + new URLSearchParams(options.params).toString();
        }

        const fetchOpts = {
            method,
            headers: {},
            credentials: 'same-origin', // Send cookies
        };

        if (options.body) {
            fetchOpts.headers['Content-Type'] = 'application/json';
            fetchOpts.body = JSON.stringify(options.body);
        }

        // Add CSRF token for state-changing requests
        if (['POST', 'PUT', 'DELETE'].includes(method) && this.csrfToken) {
            fetchOpts.headers['X-CSRF-Token'] = this.csrfToken;
        }

        const res = await fetch(url, fetchOpts);
        const data = await res.json();

        // Update CSRF token if returned
        if (data.data && data.data.csrf_token) {
            this.csrfToken = data.data.csrf_token;
        }

        if (!data.success) {
            throw new Error(data.error?.message || 'API Fehler');
        }

        return data;
    },

    /**
     * Upload FormData (for file attachments)
     * Does NOT set Content-Type — browser sets multipart/form-data with boundary
     */
    async upload(endpoint, formData) {
        let url = `${this.baseUrl}/${endpoint}`;

        const fetchOpts = {
            method: 'POST',
            headers: {},
            credentials: 'same-origin',
            body: formData,
        };

        // Add CSRF token
        if (this.csrfToken) {
            fetchOpts.headers['X-CSRF-Token'] = this.csrfToken;
        }

        const res = await fetch(url, fetchOpts);
        const data = await res.json();

        if (data.data && data.data.csrf_token) {
            this.csrfToken = data.data.csrf_token;
        }

        if (!data.success) {
            throw new Error(data.error?.message || 'API Fehler');
        }

        return data;
    },

    // Convenience methods
    get(endpoint, params = {}) {
        return this.request(endpoint, { params });
    },

    post(endpoint, body = {}) {
        return this.request(endpoint, { method: 'POST', body });
    },

    put(endpoint, body = {}) {
        return this.request(endpoint, { method: 'PUT', body });
    },

    delete(endpoint, params = {}) {
        return this.request(endpoint, { method: 'DELETE', params });
    },
};
