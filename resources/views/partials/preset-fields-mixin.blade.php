{{--
    Shared preset fields JS mixin.
    Field types come from the SERVER via getFieldSchema() — not client-side guessing.
--}}
<script>
    function presetFieldsMixin(prefix) {
        const data = {};
        data[prefix + '_defaults'] = {};
        data[prefix + '_overrides'] = {};
        data[prefix + '_dirty'] = {};
        data[prefix + '_schema'] = {};
        return data;
    }

    function _loadPresetFields(component, prefix, data, schema) {
        if (!data) {
            component[prefix + '_defaults'] = {};
            component[prefix + '_overrides'] = {};
            component[prefix + '_dirty'] = {};
            return;
        }

        const excludeKeys = ['id', 'created_at', 'updated_at', 'deleted_at', 'name', 'status', 'is_default',
            'publish_account_id', 'user_id', 'created_by', 'description',
            'default_site_id', 'default_template_id', 'default_preset_id',
            'account', 'user', 'creator', 'site', 'campaigns', 'articles', 'template', 'preset'];

        const defaults = {};
        const keys = Object.keys(data).filter(k => {
            if (excludeKeys.includes(k)) return false;
            if (k.endsWith('_id')) return false;
            const val = data[k];
            if (val === null || val === undefined || val === '') return false;
            if (typeof val === 'object' && !Array.isArray(val)) return false;
            return true;
        });

        keys.forEach(k => { defaults[k] = data[k]; });

        component[prefix + '_defaults'] = defaults;
        component[prefix + '_overrides'] = {};
        component[prefix + '_dirty'] = {};
        if (schema) component[prefix + '_schema'] = schema;
    }

    const presetFieldsMethods = {
        loadPresetFields(prefix, data, schema) {
            _loadPresetFields(this, prefix, data, schema || this[prefix + '_schema']);
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
            return this[prefix + '_schema']?.[field]?.type || 'text';
        },
        getPresetFieldOptions(prefix, field) {
            return this[prefix + '_schema']?.[field]?.options || null;
        },
        getPresetOverrides(prefix) {
            const result = { ...this[prefix + '_defaults'] };
            const overrides = this[prefix + '_overrides'] || {};
            Object.keys(overrides).forEach(k => { result[k] = overrides[k]; });
            return result;
        },
    };
</script>
