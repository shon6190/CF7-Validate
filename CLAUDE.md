# CF7 Validate — CLAUDE.md

## Project Overview

**CF7 Validate** is a WordPress addon plugin for Contact Form 7 (CF7). It adds comprehensive client-side (JavaScript) and server-side (PHP) validation, configurable via a custom React-powered **Validation** tab injected into the CF7 form editor.

- **Version:** 1.0.0
- **Author:** Shon
- **Requires:** WordPress 5.9+, PHP 7.4+, Contact Form 7 (active)
- **Plugin folder:** `cf7-validate/`
- **Main file:** `cf7-validate.php`
- **GitHub:** `https://github.com/shon6190/cf7-validate` — default branch: `develop`

---

## Directory Structure

```
cf7-validate/
├── cf7-validate.php              # Plugin bootstrap, constants, CF7 dependency check
├── uninstall.php                 # Deletes all _cfv_validation_config post meta on uninstall
├── package.json                  # @wordpress/scripts build config
├── includes/
│   ├── class-cfv-config.php      # Config read/write/sanitize/copy (post meta)
│   ├── class-cfv-hooks.php       # All WP/CF7 hooks, AJAX handlers
│   ├── class-cfv-field-decorator.php  # Injects asterisks, error spans, counters into CF7 HTML
│   └── class-cfv-validator.php   # Server-side validation engine
├── src/
│   └── validation-tab/
│       ├── index.js              # React 18 entry point (createRoot)
│       ├── components/
│       │   ├── ValidationTab.js  # Root component: config load/save, field list
│       │   ├── GlobalSettings.js # show_optional_label toggle
│       │   ├── FieldRuleRow.js   # Per-field row: label, required override, type-specific rules
│       │   └── RuleInputs/       # 9 type-specific rule components (see below)
│       ├── hooks/
│       │   └── useFormFields.js  # Parses CF7 form body textarea, returns field list
│       └── utils/
│           └── defaults.js       # FIELD_TYPE_DEFAULTS + getFieldDefaults(type)
├── assets/
│   ├── css/
│   │   └── cfv-styles.css        # All frontend + admin styles
│   ├── js/
│   │   ├── cfv-validation.js     # Client-side validation engine
│   │   ├── cfv-counter.js        # Character counter
│   │   └── cfv-intl-phone.js     # intl-tel-input integration
│   └── vendor/
│       └── intl-tel-input/       # Vendored v18.2.1 (JS, CSS, flags.png, flags@2x.png)
├── build/
│   └── validation-tab/           # Compiled React output (git-ignored in dev, committed for releases)
└── docs/                         # Additional documentation
```

### RuleInputs Components (`src/validation-tab/components/RuleInputs/`)

| File | Field Type |
|------|-----------|
| `TextRules.js` | text |
| `NameRules.js` | name |
| `EmailRules.js` | email |
| `PhoneRules.js` | tel |
| `NumericRules.js` | number |
| `TextareaRules.js` | textarea |
| `SelectRules.js` | select |
| `CheckboxRules.js` | checkbox / radio |
| `FileRules.js` | file |

---

## Architecture

### Config Storage

- Stored per-form as WordPress post meta: `_cfv_validation_config` (JSON)
- Shape: `{ global: { show_optional_label: bool }, fields: { [fieldName]: { type, ...rules } } }`
- **Field `type` must always be saved alongside rules** — both JS and PHP validators use it to route to the correct validation logic.

### Independent Validation Layer

Both validators read the **same JSON config format**:
- **JS validator** reads `window.cfvConfig.forms[formId]` (output by `wp_localize_script`)
- **PHP validator** reads post meta via `CFV_Config::get()`

Neither calls the other. This is intentional — client and server operate independently.

### CF7 Hooks Used

| Hook | Class/Method | Priority |
|------|-------------|----------|
| `wpcf7_editor_panels` | CFV_Hooks::register_validation_panel | 10 |
| `wpcf7_form_elements` | CFV_Hooks::decorate_form_elements | 10 |
| `wpcf7_before_send_mail` | CFV_Hooks::validate_submission | 10 |
| `wpcf7_ajax_json_echo` | CFV_Hooks::override_error_response | 10 |
| `wp_enqueue_scripts` | CFV_Hooks::enqueue_frontend_assets | 10 |
| `admin_enqueue_scripts` | CFV_Hooks::enqueue_admin_assets | 10 |
| `wp_insert_post` | CFV_Hooks::maybe_copy_config_on_duplicate | 10 |
| AJAX: `cfv_get_config` / `cfv_save_config` | CFV_Hooks | — |

---

## Development

### Prerequisites

- Node.js + npm (for React build)
- Local WordPress install with Contact Form 7 active
- PHP 7.4+

### Build Commands

```bash
# Install dependencies
npm install

# Development (watch mode)
npm run start

# Production build
npm run build
```

Build output goes to `build/validation-tab/`. The PHP side reads `build/validation-tab/index.asset.php` for versioning and dependency arrays.

### Key Design Decisions

1. **`wp_unslash()` only on JSON input** — never `sanitize_textarea_field()`. The latter corrupts escaped quotes in JSON strings. `json_decode()` provides its own structural validation.

2. **`intval()` not `absint()` for integer config values** — negative min_value (e.g., `-50`) must be preserved. `absint(-50)` would silently return `50`. Detection uses `is_numeric($v) && strpos((string)$v, '.') === false`.

3. **Lazy-load intl-tel-input instances** — `cfv-intl-phone.js` populates `window.cfvItiInstances` after `cfv-validation.js` runs. Always read instances inside `runFieldValidation()` at validation time, never at `createInstance()` init time.

4. **Class-based CF7 detection** — `class_exists('WPCF7')` is used (not `function_exists()`). It's stable across CF7 versions.

5. **Form duplication support** — checks both `_dp_original` (Duplicate Post plugin) and `_wpcf7_copy_from` (CF7 native) meta keys to copy validation config when a form is duplicated.

6. **No XHR prototype patching** — progress bar uses simple event-based 70% width on valid submit. CF7 response events (`wpcf7mailsent`, `wpcf7invalid`, `wpcf7mailfailed`, `wpcf7spam`) reset it.

7. **Submit button re-enable** — must listen to all three CF7 failure events: `wpcf7invalid`, `wpcf7mailfailed`, `wpcf7spam`. Missing any one leaves the button permanently disabled on failure.

8. **React 18 createRoot** — uses `createRoot(el).render()` via `@wordpress/element`, not the deprecated `ReactDOM.render()`.

9. **Always-on rules (config-independent)** — some rules fire regardless of whether a field is present in the saved config:
   - **Whitespace:** leading whitespace blocked on `text`, `textarea`, `url`, `number`; trailing whitespace stripped on blur. Email/tel strip **all** whitespace (keydown + paste guards, since `type="email"` auto-sanitizes `.value` per HTML5 spec and hides the space from JS).
   - **Format checks:** `email`, `tel`, `url` inputs get format validation even when not configured — both client-side ([cfv-validation.js:`runUnmanagedValidations`]) and server-side ([class-cfv-validator.php:`validate_unmanaged_format_fields`] via `WPCF7_ContactForm::scan_form_tags()`).
   - **Error-tip spans:** the decorator injects `cfv-error-tip` for *every* CF7 field on the form (second pass after the config loop), so server-returned `invalid_fields` always have a target once `.wpcf7-not-valid-tip` is hidden.
   - **Radio required:** CF7 core always enforces radio — our Required toggle is hidden for radio, and an asterisk is auto-injected. The Validation tab shows an explanatory note instead of a toggle.

10. **Browser HTML5 validation disabled** — the `wpcf7_form_novalidate` filter returns true so browser popups never fire; only our `cfv-error-tip` UI surfaces errors.

11. **wpcf7submit mirror** — on any submit, `invalid_fields` from the AJAX response is mirrored into `cfv-error-tip` spans. Generic messages like "Please fill out this field" get rewritten to `"Please select at least one {Label}"` (radio/checkbox) or `"{Label} is required"`.

---

## Supported Field Types & Rules

| Type | Key Rules |
|------|-----------|
| `text` | min/max length, leading-space blocked (always), allow_special_chars, allow_emoji, collapse_whitespace, input_mask, counter_format |
| `name` | min/max length, alpha_only, leading-space blocked (always) |
| `email` | format always validated (FILTER_VALIDATE_EMAIL, client + server, config-independent); all whitespace stripped on keydown/paste |
| `tel` | min/max length, all whitespace stripped on keydown/paste, enable_intl (E.164 via intl-tel-input), default_country |
| `number` | min_value, max_value, allow_negative, allow_zero, leading-space blocked |
| `url` | format always validated (FILTER_VALIDATE_URL + https?:// check, client + server, config-independent) |
| `textarea` | max_length (1500), counter_format, max_height, security_sanitize, leading-space blocked |
| `select` | placeholder_value (treated as "not selected") |
| `checkbox` | required (at least one checked) |
| `radio` | always required (CF7 core enforced — no toggle); asterisk auto-injected |
| `file` | allowed_types (comma-separated extensions), max_size_mb, allow_multiple, show_preview |

---

## intl-tel-input Integration

- **Vendored version:** 18.2.1 (PNG flags — v18.2.1 uses `.png`, not `.webp`)
- **Instance registry:** `window.cfvItiInstances[instanceKey][fieldName]` where `instanceKey = formId_N`
- **Hidden input:** `cfv_phone_full_{fieldName}` — populated with `iti.getNumber()` (E.164) on submit, read by PHP validator
- **PHP validation pattern:** `/^\+\d{8,15}$/` for intl format
- **Three documented conflict fixes** are in `docs/`

---

## AJAX Endpoints

| Action | Handler | Cap Required |
|--------|---------|-------------|
| `cfv_get_config` | `CFV_Hooks::ajax_get_config` | `manage_options` |
| `cfv_save_config` | `CFV_Hooks::ajax_save_config` | `manage_options` |

Both use `check_ajax_referer('cfv_admin_nonce', 'nonce')`.

---

## Security Notes

- All field names echoed into HTML use `esc_attr()`
- Regex patterns use `preg_quote($field_name, '/')` to prevent injection
- Server-side `strip_dangerous_patterns()` applies `strip_tags()` then strips `script/eval/alert/SQL` keyword patterns
- Config nonces expire with the standard WordPress nonce lifetime (24h)
- Uninstall removes all plugin post meta via `$wpdb->delete()`

---

## Known Pending Work (as of v1.0.0)

- [ ] **Task 20** – End-to-end QA against `QA Checklist - Sheet1.csv`
- [ ] **npm build** – Run `npm run build` after any changes to `src/` to update `build/validation-tab/`

---

## Git Workflow

- Branch: `develop`
- Milestones:
  - **M1** — Tasks 1-10 (bootstrap, config, AJAX, React tab)
  - **M2** — Tasks 11-18 (decorator, validators, hooks, duplication)
  - **M3** — Tasks 19+ (file preview, progress bar, QA fixes)
- Commit style: `feat:`, `fix:`, `refactor:`, `test:` prefixes
