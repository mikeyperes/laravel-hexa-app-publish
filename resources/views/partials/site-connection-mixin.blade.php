{{--
    Shared site connection test + author loading mixin.
    Include this partial in any Blade view that needs WordPress site testing.

    Usage in Alpine component:
        1. Include mixin data: ...siteConnectionMixin()
        2. Call: this.testSiteConnection(siteId, csrfToken, options)
        3. Read: this.siteConn.testing, .status, .message, .authors, .log

    Options:
        cacheKey: localStorage key for caching (null = no cache)
        authorField: Alpine model to sync author into (null = no sync)
        onSuccess: callback function after successful connection
        onAuthorsLoaded: callback after authors are available
--}}
<script>
    function siteConnectionMixin() {
        return {
            siteConn: {
                testing: false,
                status: null,
                message: '',
                authors: [],
                log: [],
                defaultAuthor: null,
                lastVerifiedAt: null,
            },

            /**
             * Test WordPress site connection and load authors.
             *
             * @param {number|string} siteId
             * @param {string} csrfToken
             * @param {object} options { cacheKey, onSuccess, onAuthorsLoaded }
             */
            async testSiteConnection(siteId, csrfToken, options = {}) {
                if (!siteId) return;

                this.siteConn.testing = true;
                this.siteConn.status = null;
                this.siteConn.message = 'Connecting...';
                this.siteConn.log = [];
                this.siteConn.authors = [];
                this.siteConn.defaultAuthor = null;
                this.siteConn.lastVerifiedAt = null;

                const time = () => new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                this.siteConn.log.push({ type: 'info', message: 'Testing WordPress connection...', time: time() });

                try {
                    const r = await fetch('/publish/sites/' + siteId + '/test-write', {
                        method: 'POST',
                        headers: window.hexaRequestHeaders({ 'Content-Type': 'application/json' })
                    });
                    const d = await r.json();

                    this.siteConn.status = d.success;
                    this.siteConn.message = d.message || (d.success ? 'Connected' : 'Connection failed');
                    this.siteConn.log.push({ type: d.success ? 'success' : 'error', message: this.siteConn.message, time: time() });

                    if (d.authors) {
                        this.siteConn.authors = d.authors;
                        this.siteConn.log.push({ type: 'info', message: d.authors.length + ' authors loaded', time: time() });
                        if (options.onAuthorsLoaded) options.onAuthorsLoaded.call(this, d.authors);
                    }

                    if (d.default_author) {
                        this.siteConn.defaultAuthor = d.default_author;
                    }

                    if (d.success) {
                        this.siteConn.lastVerifiedAt = new Date().toISOString();
                    }

                    if (d.success && options.onSuccess) {
                        options.onSuccess.call(this, d);
                    }
                } catch (e) {
                    this.siteConn.status = false;
                    this.siteConn.message = 'Network error: ' + e.message;
                    this.siteConn.log.push({ type: 'error', message: e.message, time: time() });
                }

                this.siteConn.testing = false;

                // Cache if key provided
                if (options.cacheKey) {
                    localStorage.setItem(options.cacheKey, JSON.stringify({
                        site_id: siteId,
                        status: this.siteConn.status,
                        message: this.siteConn.message,
                        authors: this.siteConn.authors,
                        default_author: this.siteConn.defaultAuthor,
                        verified_at: this.siteConn.lastVerifiedAt,
                    }));
                }
            },

            /**
             * Restore cached site connection state.
             *
             * @param {number|string} siteId
             * @param {string} cacheKey
             * @returns {boolean} true if restored successfully
             */
            restoreSiteConnection(siteId, cacheKey, options = {}) {
                if (!cacheKey) return false;
                try {
                    const saved = localStorage.getItem(cacheKey);
                    if (!saved) return false;
                    const conn = JSON.parse(saved);
                    const maxAgeMs = Number(options.maxAgeMs || (12 * 60 * 60 * 1000));
                    const verifiedAt = conn.verified_at ? Date.parse(conn.verified_at) : NaN;
                    if (
                        conn.site_id != siteId
                        || conn.status !== true
                        || Number.isNaN(verifiedAt)
                        || (Date.now() - verifiedAt) > maxAgeMs
                    ) {
                        return false;
                    }

                    this.siteConn.status = conn.status;
                    this.siteConn.message = conn.message || 'Connected';
                    this.siteConn.authors = Array.isArray(conn.authors) ? conn.authors : [];
                    this.siteConn.defaultAuthor = conn.default_author || null;
                    this.siteConn.lastVerifiedAt = conn.verified_at || null;
                    return true;
                } catch (e) {
                    return false;
                }
            },

            /**
             * Load site authors without running a full write test.
             *
             * @param {number|string} siteId
             * @returns {Promise<Array>}
             */
            async loadSiteAuthors(siteId, options = {}) {
                if (!siteId) return [];

                if (Object.prototype.hasOwnProperty.call(this, 'authorsLoading')) {
                    this.authorsLoading = true;
                }
                if (this.siteConn.status === true && this.siteConn.authors.length === 0) {
                    this.siteConn.testing = true;
                    this.siteConn.message = 'Connected — loading authors...';
                }

                try {
                    const resp = await fetch('/publish/sites/' + siteId + '/authors', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await resp.json();

                    this.siteConn.authors = Array.isArray(data.authors) ? data.authors : [];
                    if (data.default_author) {
                        this.siteConn.defaultAuthor = data.default_author;
                    }
                    if (data.success !== false && this.siteConn.authors.length > 0) {
                        this.siteConn.status = true;
                        this.siteConn.message = 'Connected';
                        this.siteConn.lastVerifiedAt = new Date().toISOString();
                        if (this.selectedSite && String(this.selectedSite.id || '') === String(siteId)) {
                            this.selectedSite.status = 'connected';
                        }
                        if (data.default_author && !this.publishAuthor) {
                            this.publishAuthor = data.default_author;
                            this.publishAuthorSource = 'profile';
                        }

                        const cacheKey = options.cacheKey
                            || (typeof this.siteConnectionCacheKey === 'function' ? this.siteConnectionCacheKey(siteId) : null);

                        if (cacheKey) {
                            localStorage.setItem(cacheKey, JSON.stringify({
                                site_id: siteId,
                                status: true,
                                message: this.siteConn.message,
                                authors: this.siteConn.authors,
                                default_author: this.siteConn.defaultAuthor,
                                verified_at: this.siteConn.lastVerifiedAt,
                            }));
                        }
                    }

                    return this.siteConn.authors;
                } catch (e) {
                    return [];
                } finally {
                    if (this.siteConn.status === true) {
                        this.siteConn.testing = false;
                        this.siteConn.message = 'Connected';
                    }
                    if (Object.prototype.hasOwnProperty.call(this, 'authorsLoading')) {
                        this.authorsLoading = false;
                    }
                }
            },
        };
    }
</script>
