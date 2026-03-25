/**
 * RohrApp+ — Global State
 */
const AppState = {
    user: null,
    isAuthenticated: false,
    currentPage: '',

    /**
     * Set user data (after login/register/me)
     */
    setUser(user) {
        this.user = user;
        this.isAuthenticated = !!user;
    },

    /**
     * Clear user data (after logout)
     */
    clearUser() {
        this.user = null;
        this.isAuthenticated = false;
    },

    /**
     * Get display name
     */
    displayName() {
        if (!this.user) return '';
        if (this.user.first_name && this.user.last_name) {
            return `${this.user.first_name} ${this.user.last_name}`;
        }
        if (this.user.first_name) return this.user.first_name;
        if (this.user.company_name) return this.user.company_name;
        return this.user.email.split('@')[0];
    },

    /**
     * Get initials for avatar
     */
    initials() {
        if (!this.user) return '?';
        if (this.user.first_name && this.user.last_name) {
            return (this.user.first_name[0] + this.user.last_name[0]).toUpperCase();
        }
        return this.user.email[0].toUpperCase();
    },
};
