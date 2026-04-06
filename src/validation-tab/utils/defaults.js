// Mirror of PHP CFV_Config::get_field_type_defaults per type.
export const FIELD_TYPE_DEFAULTS = {
    text:     { required: false, min_length: '', max_length: '', alpha_only: false, allow_special_chars: true, allow_emoji: true, collapse_whitespace: true, input_mask: '', counter_format: 'off' },
    name:     { required: false, min_length: 2, max_length: 56, alpha_only: true, allow_special_chars: false, allow_emoji: false, collapse_whitespace: true, input_mask: '', counter_format: 'off' },
    email:    { required: false, trim_whitespace: true },
    tel:      { required: false, min_length: 7, max_length: 15, enable_intl: false, default_country: 'auto' },
    number:   { required: false, min_value: '', max_value: '', allow_negative: false, allow_zero: true },
    url:      { required: false },
    textarea: { required: false, max_length: 1500, counter_format: 'count/max', max_height: 200, security_sanitize: true },
    select:   { required: false, placeholder_value: '' },
    checkbox: { required: false },
    radio:    { required: false },
    file:     { required: false, allowed_types: 'jpg,jpeg,png,pdf', max_size_mb: 5, allow_multiple: false, show_preview: false },
};

export function getFieldDefaults( type ) {
    return FIELD_TYPE_DEFAULTS[ type ] ?? FIELD_TYPE_DEFAULTS.text;
}
