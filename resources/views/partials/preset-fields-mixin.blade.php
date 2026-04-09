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
        if (!data && !schema) {
            component[prefix + '_defaults'] = {};
            component[prefix + '_overrides'] = {};
            component[prefix + '_dirty'] = {};
            return;
        }

        const defaults = {};
        const resolvedSchema = schema || component[prefix + '_schema'] || {};

        // Use schema keys as the source of truth — show ALL fields, even if null
        const schemaKeys = Object.keys(resolvedSchema);
        if (schemaKeys.length > 0) {
            schemaKeys.forEach(k => {
                const val = data ? data[k] : undefined;
                const type = resolvedSchema[k]?.type || 'text';
                const hasKey = data && (k in data);
                // Only set defaults for fields that exist in the data
                // Skip missing fields so reactive form's own field defaults take over
                if (val !== null && val !== undefined) {
                    defaults[k] = val;
                } else if (hasKey) {
                    // Field exists in data but is null — use type-appropriate empty
                    if (type === 'checkbox' || type === 'array') defaults[k] = [];
                    else if (type === 'number') defaults[k] = '';
                    else if (type === 'boolean') defaults[k] = false;
                    else defaults[k] = '';
                }
                // If !hasKey, field is not in template data — don't set, let form defaults apply
            });
        } else if (data) {
            // Fallback: no schema, use data keys (legacy behavior)
            const excludeKeys = ['id', 'created_at', 'updated_at', 'deleted_at', 'name', 'status', 'is_default',
                'publish_account_id', 'user_id', 'created_by', 'description',
                'default_site_id', 'default_template_id', 'default_preset_id',
                'account', 'user', 'creator', 'site', 'campaigns', 'articles', 'template', 'preset'];
            Object.keys(data).filter(k => {
                if (excludeKeys.includes(k) || k.endsWith('_id')) return false;
                const val = data[k];
                if (typeof val === 'object' && !Array.isArray(val)) return false;
                return true;
            }).forEach(k => { defaults[k] = data[k] ?? ''; });
        }

        component[prefix + '_defaults'] = defaults;
        component[prefix + '_overrides'] = {};
        component[prefix + '_dirty'] = {};
        if (schema) component[prefix + '_schema'] = schema;
    }

    const presetFieldsMethods = {
        loadPresetFields(prefix, data, schema, overrides) {
            _loadPresetFields(this, prefix, data, schema || this[prefix + '_schema']);
            // Merge overrides into defaults for the reactive form if provided
            const values = overrides
                ? { ...this[prefix + '_defaults'], ...overrides }
                : (this[prefix + '_defaults'] || {});
            // Dispatch to core reactive form component — delay slightly to ensure form is rendered
            const formId = prefix === 'preset' ? 'wp-preset-form' : 'article-preset-form';
            setTimeout(() => {
                window.dispatchEvent(new CustomEvent('hexa-form-load', {
                    detail: { component_id: formId, values }
                }));
            }, 100);
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
