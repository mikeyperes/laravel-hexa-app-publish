{{--
    Shared preset fields JS mixin.
    Include before any Alpine component that uses preset fields.

    Usage:
        1. @include('app-publish::partials.preset-fields-mixin')
        2. In Alpine: ...presetFieldsMixin('template'), ...presetFieldsMixin('preset')
        3. Call: this.loadPresetFields('template', templateData)
        4. Read: this.getPresetValue('template', 'tone')
--}}
<script>
    function presetFieldsMixin(prefix) {
        const data = {};
        data[prefix + '_defaults'] = {};
        data[prefix + '_overrides'] = {};
        data[prefix + '_dirty'] = {};
        data[prefix + '_expanded'] = false;
        return data;
    }

    /**
     * Load preset/template fields into the mixin state.
     * Call from your Alpine component: this.loadPresetFields('template', templateObject)
     *
     * @param {string} prefix - 'template' or 'preset'
     * @param {object} data - The template/preset record with all fields
     * @param {array} fields - Which fields to expose (null = auto-detect)
     */
    function _loadPresetFields(component, prefix, data, fields) {
        if (!data) {
            component[prefix + '_defaults'] = {};
            component[prefix + '_overrides'] = {};
            component[prefix + '_dirty'] = {};
            return;
        }

        // Auto-detect fields: exclude meta, FKs, relationships, timestamps
        const excludeKeys = ['id', 'created_at', 'updated_at', 'deleted_at', 'name', 'status', 'is_default',
            'publish_account_id', 'user_id', 'created_by', 'description',
            'default_site_id', 'default_template_id', 'default_preset_id',
            'account', 'user', 'creator', 'site', 'campaigns', 'articles', 'template', 'preset'];

        const defaults = {};
        const keys = fields || Object.keys(data).filter(k => {
            if (excludeKeys.includes(k)) return false;
            if (k.endsWith('_id')) return false;
            const val = data[k];
            if (val === null || val === undefined || val === '') return false;
            if (typeof val === 'object' && !Array.isArray(val)) return false; // Skip relationship objects
            return true;
        });

        keys.forEach(k => {
            defaults[k] = data[k];
        });

        component[prefix + '_defaults'] = defaults;
        component[prefix + '_overrides'] = {};
        component[prefix + '_dirty'] = {};
    }

    // These are meant to be mixed into Alpine components via methods
    const presetFieldsMethods = {
        loadPresetFields(prefix, data, fields) {
            _loadPresetFields(this, prefix, data, fields);
        },
        restorePresetDefaults(prefix) {
            this[prefix + '_overrides'] = {};
            this[prefix + '_dirty'] = {};
        },
        getPresetValue(prefix, field) {
            const overrides = this[prefix + '_overrides'];
            if (overrides && overrides.hasOwnProperty(field)) return overrides[field];
            return this[prefix + '_defaults']?.[field];
        },
        isPresetDirty(prefix, field) {
            return !!this[prefix + '_dirty']?.[field];
        },
        getPresetOverrides(prefix) {
            // Returns merged: defaults + overrides
            const result = { ...this[prefix + '_defaults'] };
            const overrides = this[prefix + '_overrides'] || {};
            Object.keys(overrides).forEach(k => { result[k] = overrides[k]; });
            return result;
        },
    };
</script>
