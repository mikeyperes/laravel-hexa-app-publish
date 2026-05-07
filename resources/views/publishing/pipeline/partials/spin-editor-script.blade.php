        _getSpinEditorInstance() {
            return typeof tinymce !== 'undefined' ? tinymce.get('spin-preview-editor') : null;
        },

        _setSpinEditorTextareaValue(html) {
            const textarea = document.getElementById('spin-preview-editor');
            if (textarea) textarea.value = html || '';
        },

        _safeSetSpinEditorContent(editor, html, { syncState = true } = {}) {
            const content = html || '';
            this._pendingSpinEditorContent = content;
            this._setSpinEditorTextareaValue(content);

            if (!editor) return false;

            try {
                const current = String(editor.getContent({ format: 'raw' }) || '');
                if (current === content) {
                    if (syncState) this.syncEditorStateFromEditor();
                    return true;
                }
            } catch (error) {}

            try {
                editor.setContent(content);
                if (syncState) this.syncEditorStateFromEditor();
                return true;
            } catch (error) {
                this._logSpin('warn', 'Editor content update deferred: ' + (error?.message || 'setContent failed'));
                return false;
            }
        },

        _spinEditorHasMountedUi() {
            const editor = this._getSpinEditorInstance();
            const container = editor?.getContainer?.();
            return !!(
                editor
                && container
                && container.classList
                && container.classList.contains('tox-tinymce')
                && container.isConnected
                && document.contains(container)
            );
        },

        _ensureSpinEditorConfigured(initialHtml = '') {
            const self = this;
            const content = initialHtml || this._pendingSpinEditorContent || '';
            this._pendingSpinEditorContent = content;
            this._setSpinEditorTextareaValue(content);

            if (this._spinEditorConfigured) {
                if (this._spinEditorHasMountedUi()) {
                    const editor = this._getSpinEditorInstance();
                    if (editor) {
                        this._spinEditorRecoveryAttempts = 0;
                        this._safeSetSpinEditorContent(editor, content);
                        return;
                    }
                }
                this._spinEditorConfigured = false;
            }

            if (this._spinEditorConfiguring) return;
            this._spinEditorConfiguring = true;

            this.$nextTick(() => {
                let attempts = 0;
                const wait = setInterval(() => {
                    attempts++;

                    if (typeof tinymce === 'undefined') {
                        if (attempts > 100) {
                            clearInterval(wait);
                            self._spinEditorConfiguring = false;
                        }
                        return;
                    }

                    const textarea = document.getElementById('spin-preview-editor');
                    if (!textarea) {
                        if (attempts > 100) {
                            clearInterval(wait);
                            self._spinEditorConfiguring = false;
                        }
                        return;
                    }

                    clearInterval(wait);

                    const triggerMount = () => {
                        try {
                            self._setSpinEditorTextareaValue(self._pendingSpinEditorContent || '');
                            hexaReinitTinyMCE('spin-preview-editor', {
                        plugins: 'lists link image media table fullscreen wordcount code searchreplace autolink autoresize',
                        toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link image media | addPhotoBtn uploadPhotoBtn | table | alignleft aligncenter alignright | outdent indent | fullscreen code searchreplace',
                        menubar: true,
                        min_height: 400,
                        autoresize_bottom_margin: 50,
                        extended_valid_elements: 'div[*],span[*],img[*],figure[*],figcaption[*]',
                        custom_elements: '~div',
                        content_style: '.photo-placeholder { cursor: pointer !important; } .photo-placeholder:hover { opacity: 0.9; }',
                        setup: function(ed) {
                            ed.ui.registry.addButton('addPhotoBtn', {
                                icon: 'gallery',
                                tooltip: 'Search & Insert Photo',
                                onAction: function() {
                                    self._photoSuggestionIdx = null;
                                    self.photoSearch = '';
                                    self.photoResults = [];
                                    self.insertingPhoto = null;
                                    self.showPhotoOverlay = true;
                                }
                            });

                            ed.ui.registry.addButton('uploadPhotoBtn', {
                                icon: 'upload',
                                tooltip: 'Upload Photo from Device',
                                onAction: function() {
                                    self.showUploadPortal = true;
                                }
                            });

                            ed.on('init', function() {
                                if (!self._spinEditorHasMountedUi()) {
                                    self._spinEditorConfigured = false;
                                    self._spinEditorConfiguring = false;
                                    self._spinEditorRecoveryAttempts = (self._spinEditorRecoveryAttempts || 0) + 1;
                                    try { ed.remove(); } catch (error) {}
                                    if ((self._spinEditorRecoveryAttempts || 0) <= 4) {
                                        self._logSpin('warn', 'Retrying TinyMCE mount after detached Create Article init');
                                        setTimeout(() => self._ensureSpinEditorConfigured(self._pendingSpinEditorContent || ''), 150);
                                    }
                                    return;
                                }
                                self._spinEditorConfigured = true;
                                self._spinEditorConfiguring = false;
                                self._spinEditorRecoveryAttempts = 0;
                                self._safeSetSpinEditorContent(ed, self._pendingSpinEditorContent, { syncState: false });
                                self.syncDeferredEnrichmentState('editor_init', { log: false });
                                self.hydrateResolvedPhotoPlaceholders('editor_init');
                                self.scheduleThumbStateReconcile('editor_init');
                                self.queueInlinePhotoAutoHydration('editor_init');
                                self.queueFeaturedImageAutoHydration('editor_init');
                                self.syncEditorStateFromEditor();
                            });

                            ed.on('remove', function() {
                                self._spinEditorConfigured = false;
                            });

                            ed.on('change keyup input SetContent Undo Redo', function() {
                                self.syncEditorStateFromEditor();
                            });

                            ed.on('click', function(e) {
                                const target = e.target;

                                if (target.classList && target.classList.contains('photo-view')) {
                                    e.preventDefault();
                                    const ph = target.closest('.photo-placeholder');
                                    if (ph) self.viewPhotoInfo(parseInt(ph.getAttribute('data-idx')));
                                    return;
                                }
                                if (target.classList && target.classList.contains('photo-confirm')) {
                                    e.preventDefault();
                                    const ph = target.closest('.photo-placeholder');
                                    if (ph) self.confirmPhoto(parseInt(ph.getAttribute('data-idx')));
                                    return;
                                }
                                if (target.classList && target.classList.contains('photo-change')) {
                                    e.preventDefault();
                                    const ph = target.closest('.photo-placeholder');
                                    if (ph) self.changePhoto(parseInt(ph.getAttribute('data-idx')));
                                    return;
                                }
                                if (target.classList && target.classList.contains('photo-remove')) {
                                    e.preventDefault();
                                    const ph = target.closest('.photo-placeholder');
                                    if (ph) self.removePhotoPlaceholder(parseInt(ph.getAttribute('data-idx')));
                                    return;
                                }

                                const placeholder = target.closest('.photo-placeholder') || (target.classList && target.classList.contains('photo-placeholder') ? target : null);
                                if (placeholder) {
                                    const idx = parseInt(placeholder.getAttribute('data-idx'));
                                    if (!isNaN(idx)) self.viewPhotoInfo(idx);
                                }
                            });
                        }
                            });
                        } catch (error) {
                            self._spinEditorConfigured = false;
                            self._spinEditorConfiguring = false;
                            self._logSpin('warn', 'Editor init failed: ' + (error?.message || 'hexaReinitTinyMCE failed'));
                        }
                    };

                    requestAnimationFrame(() => {
                        triggerMount();
                        setTimeout(() => {
                            if (self._spinEditorHasMountedUi()) {
                                return;
                            }
                            self._spinEditorConfigured = false;
                            self._spinEditorConfiguring = false;
                            self._spinEditorRecoveryAttempts = (self._spinEditorRecoveryAttempts || 0) + 1;
                            if ((self._spinEditorRecoveryAttempts || 0) <= 4) {
                                self._logSpin('warn', 'Retrying TinyMCE mount after delayed Create Article transition');
                                self._ensureSpinEditorConfigured(self._pendingSpinEditorContent || '');
                            }
                        }, 1200);
                    });
                }, 100);
            });
        },

        setSpinEditor(html) {
            const content = html || '';
            this._pendingSpinEditorContent = content;
            this._setSpinEditorTextareaValue(content);

            const editor = this._getSpinEditorInstance();
            if (this._spinEditorConfigured && editor) {
                if (this._spinEditorHasMountedUi() && this._safeSetSpinEditorContent(editor, content)) return;
                this._spinEditorConfigured = false;
                this._logSpin('warn', 'Recovering TinyMCE after detached Create Article editor UI');
                try {
                    if (typeof editor.remove === 'function') editor.remove();
                    else if (typeof editor.destroy === 'function') editor.destroy();
                } catch (error) {}
            }

            this._ensureSpinEditorConfigured(content);
        },
