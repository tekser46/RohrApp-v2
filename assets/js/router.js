/**
 * RohrApp+ — Hash Router
 * Routes: #/login, #/register, #/dashboard, etc.
 */
const Router = {
    routes: {},
    currentPage: null,
    renderCount: 0, // increments on each navigation — stale async renders can check this

    /**
     * Register a route
     */
    register(path, handler, requiresAuth = true) {
        this.routes[path] = { handler, requiresAuth };
    },

    /**
     * Initialize router — listen for hash changes
     */
    init() {
        window.addEventListener('hashchange', () => this.resolve());
        this.resolve();
    },

    /**
     * Navigate to a path
     */
    navigate(path) {
        window.location.hash = '#/' + path.replace(/^#?\/?/, '');
    },

    /**
     * Resolve current hash to a route handler
     */
    async resolve() {
        const hash = window.location.hash.replace(/^#\/?/, '') || 'login';
        const route = this.routes[hash];

        if (!route) {
            this.navigate('login');
            return;
        }

        // Auth guard
        if (route.requiresAuth && !AppState.isAuthenticated) {
            this.navigate('login');
            return;
        }

        // If authenticated and trying to access login/register, go to dashboard
        if (!route.requiresAuth && AppState.isAuthenticated && (hash === 'login' || hash === 'register' || hash === 'forgot-password')) {
            this.navigate('dashboard');
            return;
        }

        // Stop any active polling from previous page
        if (typeof stopCallPolling === 'function') stopCallPolling();

        // Update state
        AppState.currentPage = hash;
        this.currentPage = hash;
        this.renderCount++;

        // Call handler
        route.handler(this.renderCount);
    },
};
