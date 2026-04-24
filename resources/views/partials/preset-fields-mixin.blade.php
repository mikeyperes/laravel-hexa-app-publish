{{--
    Shared preset fields JS mixin.
    Field types and grouping come from the SERVER schema metadata.
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
        const schemaKeys = Object.keys(resolvedSchema);

        if (schemaKeys.length > 0) {
            schemaKeys.forEach(k => {
                const val = data ? data[k] : undefined;
                const type = resolvedSchema[k]?.type || 'text';
                const hasKey = data && Object.prototype.hasOwnProperty.call(data, k);

                if (val !== null && val !== undefined) {
                    defaults[k] = val;
                } else if (hasKey) {
                    if (type === 'checkbox' || type === 'array') defaults[k] = [];
                    else if (type === 'number') defaults[k] = '';
                    else if (type === 'boolean') defaults[k] = false;
                    else defaults[k] = '';
                }
            });
        } else if (data) {
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
            this[prefix + '_overrides'] = JSON.parse(JSON.stringify(overrides || {}));
            this[prefix + '_dirty'] = {};
            const values = overrides
                ? { ...this[prefix + '_defaults'], ...overrides }
                : (this[prefix + '_defaults'] || {});
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
            if (overrides && Object.prototype.hasOwnProperty.call(overrides, field)) return overrides[field];
            const defaults = this[prefix + '_defaults'];
            if (defaults && Object.prototype.hasOwnProperty.call(defaults, field)) return defaults[field];

            const schema = this[prefix + '_schema']?.[field] || null;
            if (schema && Object.prototype.hasOwnProperty.call(schema, 'default')) return schema.default;
            if (schema?.multiple) return [];
            if (schema?.type === 'boolean') return false;
            return '';
        },
        isPresetDirty(prefix, field) {
            return !!this[prefix + '_dirty']?.[field];
        },
        getPresetFieldSchema(prefix, field) {
            return this[prefix + '_schema']?.[field] || {};
        },
        getPresetFieldType(prefix, field) {
            return this.getPresetFieldSchema(prefix, field).type || 'text';
        },
        getPresetFieldOptions(prefix, field) {
            return this.getPresetFieldSchema(prefix, field).options || null;
        },
        getPresetFieldLabel(prefix, field) {
            return this.getPresetFieldSchema(prefix, field).label || field.replace(/_/g, ' ');
        },
        getPresetFieldCount(prefix, exclude = []) {
            return this.getPresetFields(prefix, exclude).length;
        },
        getPresetFields(prefix, exclude = []) {
            const schema = this[prefix + '_schema'] || {};
            const defaults = this[prefix + '_defaults'] || {};
            const excludeSet = new Set((exclude || []).map(value => String(value)));
            const keys = Object.keys(schema).length ? Object.keys(schema) : Object.keys(defaults);

            return keys
                .filter(field => !excludeSet.has(String(field)))
                .map(field => {
                    const meta = schema[field] || {};
                    return {
                        name: field,
                        label: meta.label || field.replace(/_/g, ' '),
                        type: meta.type || 'text',
                        options: meta.options || {},
                        help: meta.help || '',
                        placeholder: meta.placeholder || '',
                        columns: meta.columns || '',
                        rows: meta.meta?.rows || null,
                        emptyLabel: meta.meta?.empty_label || '',
                        section: meta.meta?.section || 'general',
                        meta: meta.meta || {},
                    };
                });
        },
        getPresetSections(prefix, exclude = [], labels = {}, descriptions = {}) {
            const defaultLabels = {
                account: 'Account & Ownership',
                basic: 'Preset Identity',
                discovery: 'Discovery Strategy',
                schedule: 'Preset Schedule',
                copy: 'Writing Brief',
                content: 'Content Rules',
                research: 'Research & AI Models',
                media: 'Media & Photos',
                defaults: 'Preset Defaults',
                system: 'System Flags',
                general: 'General Settings',
            };
            const fields = this.getPresetFields(prefix, exclude);
            const sections = [];
            const grouped = new Map();

            fields.forEach(field => {
                const key = field.section || 'general';
                if (!grouped.has(key)) grouped.set(key, []);
                grouped.get(key).push(field);
            });

            for (const [key, sectionFields] of grouped.entries()) {
                sections.push({
                    key,
                    label: labels?.[key] || defaultLabels[key] || key.replace(/[_-]/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
                    description: descriptions?.[key] || '',
                    fields: sectionFields,
                });
            }

            return sections;
        },
        getPresetOverrides(prefix) {
            const result = { ...this[prefix + '_defaults'] };
            const overrides = this[prefix + '_overrides'] || {};
            Object.keys(overrides).forEach(k => { result[k] = overrides[k]; });
            return result;
        },
    };
</script>
