{{--
    Shared preset fields JS mixin.
    Field types are auto-detected from the data — NO hardcoded field names.

    Type detection:
    - boolean values → toggle switch
    - integer values → number input
    - array values → comma-separated text input
    - string > 100 chars → textarea
    - string ≤ 100 chars → text input
--}}
<script>
    function presetFieldsMixin(prefix) {
        const data = {};
        data[prefix + '_defaults'] = {};
        data[prefix + '_overrides'] = {};
        data[prefix + '_dirty'] = {};
        data[prefix + '_types'] = {};
        return data;
    }

    function _loadPresetFields(component, prefix, data, fields) {
        if (!data) {
            component[prefix + '_defaults'] = {};
            component[prefix + '_overrides'] = {};
            component[prefix + '_dirty'] = {};
            component[prefix + '_types'] = {};
            return;
        }

        const excludeKeys = ['id', 'created_at', 'updated_at', 'deleted_at', 'name', 'status', 'is_default',
            'publish_account_id', 'user_id', 'created_by', 'description',
            'default_site_id', 'default_template_id', 'default_preset_id',
            'account', 'user', 'creator', 'site', 'campaigns', 'articles', 'template', 'preset'];

        const defaults = {};
        const types = {};
        const keys = fields || Object.keys(data).filter(k => {
            if (excludeKeys.includes(k)) return false;
            if (k.endsWith('_id')) return false;
            const val = data[k];
            if (val === null || val === undefined || val === '') return false;
            if (typeof val === 'object' && !Array.isArray(val)) return false;
            return true;
        });

        keys.forEach(k => {
            const val = data[k];
            defaults[k] = val;
            // Auto-detect type from value
            if (typeof val === 'boolean') {
                types[k] = 'boolean';
            } else if (Array.isArray(val)) {
                types[k] = 'array';
            } else if (typeof val === 'number' || (typeof val === 'string' && /^\d+$/.test(val) && k.match(/count|min|max|links|photos/i))) {
                types[k] = 'number';
                defaults[k] = parseInt(val) || 0;
            } else if (typeof val === 'string' && val.length > 100) {
                types[k] = 'textarea';
            } else {
                types[k] = 'text';
            }
        });

        component[prefix + '_defaults'] = defaults;
        component[prefix + '_overrides'] = {};
        component[prefix + '_dirty'] = {};
        component[prefix + '_types'] = types;
    }

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
        getPresetFieldType(prefix, field) {
            return this[prefix + '_types']?.[field] || 'text';
        },
        getPresetOverrides(prefix) {
            const result = { ...this[prefix + '_defaults'] };
            const overrides = this[prefix + '_overrides'] || {};
            Object.keys(overrides).forEach(k => { result[k] = overrides[k]; });
            return result;
        },
    };
</script>
