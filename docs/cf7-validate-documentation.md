# CF7 Validate Pro — Complete Documentation

**Version:** 1.0.0  
**Requires:** WordPress 5.9+, PHP 7.4+, Contact Form 7 (any recent version)  
**License:** GPL-2.0-or-later

---

## Table of Contents

1. [Overview](#1-overview)
2. [Features at a Glance](#2-features-at-a-glance)
3. [Installation & Requirements](#3-installation--requirements)
4. [How It Works — Architecture](#4-how-it-works--architecture)
5. [Admin Usage — Validation Tab](#5-admin-usage--validation-tab)
6. [Field Types & Validation Rules](#6-field-types--validation-rules)
7. [Frontend Behaviour](#7-frontend-behaviour)
8. [International Phone Fields](#8-international-phone-fields)
9. [File Upload Fields](#9-file-upload-fields)
10. [Developer Reference](#10-developer-reference)
11. [FAQ & Troubleshooting](#11-faq--troubleshooting)
12. [Changelog](#12-changelog)

---

## 1. Overview

**CF7 Validate Pro** is a WordPress plugin that extends Contact Form 7 (CF7) with powerful, configurable validation — both on the client (browser) and on the server (PHP). It adds a **Validation** tab directly inside the CF7 form editor, where administrators can configure detailed rules for every field without writing any code or modifying CF7 shortcodes.

When a visitor fills in a form:
- Rules are validated **in real-time** as the user types or moves between fields.
- On submit, all rules run again **client-side** before the form posts.
- On the server, every rule is re-checked **independently** in PHP — so validation cannot be bypassed by disabling JavaScript.

The plugin keeps all styling and validation logic **outside the plugin's own shortcodes**: it post-processes the rendered CF7 HTML and injects the necessary elements, meaning you never need to change your form shortcodes.

---

## 2. Features at a Glance

| Feature | Description |
|---------|-------------|
| **Per-field validation rules** | Configure required, length, format, and type-specific rules per field |
| **Dual validation** | Identical rules run client-side (instant feedback) and server-side (security) |
| **Required / Optional badges** | Automatic asterisk for required fields; optional "(Optional)" label for others |
| **Inline error messages** | Errors appear directly beneath each field, not in a single banner |
| **Character counters** | Live counters on text and textarea fields with colour feedback |
| **International phone** | intl-tel-input flag picker, dial code prefix, and E.164 format enforcement |
| **File upload validation** | Allowed file types, max file size, image preview, multiple files |
| **Input masks** | Pattern-constrained input (e.g. dates, phone formats) |
| **Security sanitisation** | Server-side stripping of script tags, PHP code, and SQL injection patterns |
| **Form duplication** | Validation config copies automatically when a CF7 form is duplicated |
| **No shortcode changes needed** | Works with existing forms; injects UI elements via HTML post-processing |

---

## 3. Installation & Requirements

### Requirements

- WordPress 5.9 or later
- PHP 7.4 or later
- **Contact Form 7** must be installed and active

The plugin checks for CF7 on every page load. If CF7 is deactivated, CF7 Validate Pro will automatically deactivate itself and display an admin notice.

### Installation

1. Upload the `cf7-validate` folder to `wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Confirm Contact Form 7 is also active.
4. Open any CF7 form editor — a new **Validation** tab will appear alongside Mail, Messages, and Additional Settings.

### Build (developers)

The React validation tab is pre-built. To rebuild from source:

```bash
cd wp-content/plugins/cf7-validate
npm install
npm run build      # production build → build/validation-tab/
npm run start      # watch mode for development
```

---

## 4. How It Works — Architecture

The plugin has four PHP classes and three JavaScript modules, each with a distinct role.

### PHP Classes

| Class | File | Role |
|-------|------|------|
| `CFV_Config` | `includes/class-cfv-config.php` | Read, write, and sanitise validation config stored in post meta |
| `CFV_Field_Decorator` | `includes/class-cfv-field-decorator.php` | Post-process CF7 HTML to inject asterisks, error spans, and counters |
| `CFV_Validator` | `includes/class-cfv-validator.php` | Server-side validation of form submissions |
| `CFV_Hooks` | `includes/class-cfv-hooks.php` | All WordPress hook registrations and AJAX handlers |

### JavaScript Modules

| Module | File | Role |
|--------|------|------|
| Validation engine | `assets/js/cfv-validation.js` | Real-time and submit-time client-side validation |
| Character counter | `assets/js/cfv-counter.js` | Live counter display on text/textarea fields |
| Intl phone | `assets/js/cfv-intl-phone.js` | International phone flag picker and E.164 formatting |

### React Admin Tab

| File | Role |
|------|------|
| `src/validation-tab/index.js` | React mount point inside CF7 editor |
| `src/validation-tab/components/ValidationTab.js` | Root component; loads/saves config via AJAX |
| `src/validation-tab/components/FieldRuleRow.js` | Per-field row; dispatches to type-specific rule components |
| `src/validation-tab/components/GlobalSettings.js` | Global settings (show optional label) |
| `src/validation-tab/components/[Type]Rules.js` | Rule controls for each field type |
| `src/validation-tab/hooks/useFormFields.js` | Parses CF7 form body to detect fields |

### Data Storage

All validation configuration is stored as a single JSON string in WordPress post meta:

- **Meta key:** `_cfv_validation_config`
- **Post type:** `wpcf7_contact_form`
- **Format:**

```json
{
  "global": {
    "show_optional_label": false
  },
  "fields": {
    "your-name": {
      "type": "text",
      "label": "Your Name",
      "required": true,
      "min_length": 2,
      "max_length": 100
    },
    "your-email": {
      "type": "email",
      "label": "Email Address",
      "required": true
    }
  }
}
```

### Complete Data Flow

**Admin saves rules:**
1. Admin opens CF7 editor → Validation tab.
2. React component mounts; fetches current config via `cfv_get_config` AJAX.
3. `useFormFields` hook parses the form body textarea and lists all CF7 fields.
4. Admin adjusts rules, clicks **Save Validation Settings**.
5. Component POSTs JSON to `cfv_save_config` AJAX.
6. PHP sanitises and stores config as post meta.

**Visitor submits a form:**
1. CF7 shortcode renders form HTML on the page.
2. `wpcf7_form_elements` filter fires — `CFV_Field_Decorator` injects asterisks, error `<span>` elements, and counter `<span>` elements.
3. Front-end assets (CSS + JS) are enqueued on the first form render.
4. `wp_footer` action outputs an inline `cfvConfig` object listing config for every form on the page.
5. `DOMContentLoaded`: JS modules initialise per-form instances.
6. User interacts — real-time validation fires on `blur` / `input` events; error tips appear inline.
7. User submits — JS validates all fields; blocks submit if any fail.
8. CF7 AJAX request posts the form.
9. `wpcf7_before_send_mail` fires — `CFV_Validator` validates server-side.
10. `wpcf7_ajax_json_echo` fires — field-level errors are merged into CF7's response JSON.
11. CF7 JS handles the response: success clears the form; failure displays per-field error messages.

---

## 5. Admin Usage — Validation Tab

### Opening the Tab

1. Go to **Contact → Contact Forms**.
2. Click any form title to open the editor.
3. Click the **Validation** tab (between Messages and Additional Settings).

### Global Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Show Optional Label | Off | When enabled, fields that are not required display the text "(Optional)" next to their label |

### Field Rules Panel

The tab automatically detects all fields in the CF7 form body and lists them. Each field row shows:

- **Field name** (in code format, e.g. `your-email`)
- **Type** detected from the CF7 shortcode tag (e.g. `email`)
- **CF7 Required** badge — shown if the field uses the `*` shortcode variant (e.g. `[email* your-email]`)
- **Label** — editable text field; used in validation error messages (auto-generated from field name if left blank)
- **Required** toggle
- **Type-specific rule controls** (see [Section 6](#6-field-types--validation-rules))

### Saving

Click **Save Validation Settings** at the bottom of the tab. A success or error message displays for 3 seconds. Config is saved to the form's post meta immediately.

### Form Duplication

If you duplicate a CF7 form (using the Duplicate Post plugin or CF7's own duplication), the validation config is **automatically copied** to the new form.

---

## 6. Field Types & Validation Rules

CF7 Validate Pro auto-detects field types from the CF7 shortcode tag. Fields named `your-name`, `first-name`, `last-name`, or `full-name` are automatically treated as `name` type regardless of their shortcode tag.

---

### Text (`[text]`)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | Field must not be empty |
| Min Length | — | Minimum character count |
| Max Length | — | Maximum character count |
| Allow Special Characters | On | Permits symbols like `!@#$%` |
| Allow Emoji | On | Permits emoji characters |
| Collapse Whitespace | On | Multiple consecutive spaces are reduced to one on blur |
| Input Mask | — | Constrains input to a pattern (9=digit, a=letter, *=any) |
| Counter Format | Off | Display character count: `count/max` or `remaining` |

---

### Name (`[text]` with name-like field name)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | Field must not be empty |
| Min Length | 2 | Minimum character count |
| Max Length | 56 | Maximum character count |
| Alpha Only | On | Letters, spaces, hyphens, and apostrophes only — no digits or special symbols |

Special characters and emoji are disallowed by default for name fields.

---

### Email (`[email]`)

Email format is always validated automatically (`user@domain.tld` structure required). Leading and trailing spaces are trimmed on blur. No additional rules are configurable.

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | Field must not be empty |

---

### Phone (`[tel]`)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | Field must not be empty |
| Min Digits | 7 | Minimum number of digits |
| Max Digits | 15 | Maximum number of digits |
| Enable International Input | Off | Adds flag picker and dial code; enforces E.164 format |
| Default Country | auto | Initial flag country; `auto` uses geo-IP detection |

When international input is enabled, the field displays a flag picker and country dial code prefix. The submitted value includes the full E.164 number (e.g. `+353871234567`).

See [Section 8](#8-international-phone-fields) for full details.

---

### Number (`[number]`)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | Field must not be empty |
| Min Value | — | Minimum numeric value allowed |
| Max Value | — | Maximum numeric value allowed |
| Allow Negative | Off | When off, negative numbers are rejected |
| Allow Zero | On | When off, zero is rejected |

---

### URL (`[url]`)

URL format is always validated automatically. The URL must start with `http://` or `https://`. No additional rules are configurable.

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | Field must not be empty |

---

### Textarea (`[textarea]`)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | Field must not be empty |
| Max Characters | 1500 | Maximum character count; exceeding this shows an error |
| Counter Format | count/max | Display: `count/max` (e.g. 45 / 1500) or `remaining` (e.g. 1455 remaining) |
| Max Height (px) | 200 | Textarea scrolls vertically beyond this height instead of expanding |
| Security Sanitise | On | Server-side: strips `<script>`, `<?php`, `eval()`, SQL injection patterns from submitted value |

---

### Select (`[select]`)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | A real option (not the placeholder) must be selected |
| Placeholder Value | — | The option value that represents "nothing selected" (e.g. `0`, `""`) |

When the placeholder value matches the submitted value, the field is treated as empty.

---

### Checkbox (`[checkbox]`)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | At least one checkbox in the group must be checked |

---

### Radio (`[radio]`)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | An option must be selected |

---

### File (`[file]`)

| Rule | Default | Description |
|------|---------|-------------|
| Required | Off | A file must be attached |
| Allowed Types | jpg,jpeg,png,pdf | Comma-separated list of permitted file extensions |
| Max File Size (MB) | 5 | Maximum size per file in megabytes |
| Allow Multiple Files | Off | Permits selecting more than one file |
| Show Preview | Off | Displays thumbnail previews for images (other files show a document icon) |

See [Section 9](#9-file-upload-fields) for full details.

---

### Feature Matrix

| Feature | Text | Name | Email | Tel | Number | URL | Textarea | Select | Checkbox | Radio | File |
|---------|:----:|:----:|:-----:|:---:|:------:|:---:|:--------:|:------:|:--------:|:-----:|:----:|
| Required | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Min / Max Length | ✓ | ✓ | — | ✓ | — | — | ✓ | — | — | — | — |
| Min / Max Value | — | — | — | — | ✓ | — | — | — | — | — | — |
| Alpha Only | ✓ | ✓ | — | — | — | — | — | — | — | — | — |
| Allow Special Chars | ✓ | — | — | — | — | — | — | — | — | — | — |
| Allow Emoji | ✓ | — | — | — | — | — | — | — | — | — | — |
| Allow Negative | — | — | — | — | ✓ | — | — | — | — | — | — |
| Allow Zero | — | — | — | — | ✓ | — | — | — | — | — | — |
| Collapse Whitespace | ✓ | ✓ | — | — | — | — | — | — | — | — | — |
| Input Mask | ✓ | ✓ | — | — | — | — | — | — | — | — | — |
| Character Counter | ✓ | ✓ | — | — | — | — | ✓ | — | — | — | — |
| Intl Flag Picker | — | — | — | ✓ | — | — | — | — | — | — | — |
| Placeholder Detection | — | — | — | — | — | — | — | ✓ | — | — | — |
| Security Sanitise | — | — | — | — | — | — | ✓ | — | — | — | — |
| Allowed File Types | — | — | — | — | — | — | — | — | — | — | ✓ |
| Max File Size | — | — | — | — | — | — | — | — | — | — | ✓ |
| File Preview | — | — | — | — | — | — | — | — | — | — | ✓ |

---

## 7. Frontend Behaviour

### Required / Optional Indicators

When the Validation tab has rules saved for a form:
- Fields marked **required** get a red asterisk `*` injected inside their `<label>`.
- If **Show Optional Label** is enabled globally, non-required fields get an "(Optional)" indicator inside their label.

These are injected into the server-rendered HTML via `CFV_Field_Decorator` — they do not appear if no config is saved for the form.

### Real-Time Validation

Field validation runs at three points:
- **On focus** — clears any existing error for the field.
- **On input** — validates as the user types (after the field has been blurred at least once, to avoid showing errors prematurely).
- **On blur** — trims leading/trailing spaces (configurable), collapses whitespace, then validates.

Error messages appear in a `<span class="cfv-error-tip">` element directly after the field, which is pre-injected into the page HTML. This means errors are always in the correct DOM position without JavaScript DOM insertion.

### Character Counters

For text/textarea fields with counter rules configured, a counter element (`<span class="cfv-counter">`) is injected after the field. It updates on every keystroke:
- Normal: gray text showing the count.
- Near limit (≥ 90% of max): turns orange.
- Over limit: turns red and bold.

Counter formats:
- `count/max` — e.g. **45 / 1500**
- `remaining` — e.g. **1455 characters remaining**

### Submit Handling

On submit click:
1. All fields are validated simultaneously.
2. If any field fails: submit is blocked, errors are displayed, the first invalid field is focused.
3. If all fields pass: the submit button is disabled and a loading spinner is shown. An upload progress bar appears for forms with file fields.

After CF7 responds:
- **Success** (`wpcf7mailsent`): all errors cleared, form resets, button re-enabled.
- **Failure** (`wpcf7invalid`, `wpcf7mailfailed`, `wpcf7spam`): button re-enabled, existing errors remain visible.

---

## 8. International Phone Fields

When **Enable International Input** is turned on for a `[tel]` field:

1. A flag picker and country dial code prefix appear beside the phone input.
2. The user selects their country and enters only the subscriber number.
3. On submit, the plugin constructs the full E.164 number (e.g. `+353871234567`) and stores it in a hidden `<input>` named `cfv_phone_full_{fieldName}`.
4. The server-side validator reads from this hidden field when validating intl phone fields; it requires the value to match `/^\+\d{8,15}$/`.

### Default Country

The **Default Country** setting accepts:
- An ISO 3166-1 alpha-2 country code (e.g. `ie`, `gb`, `us`) — the picker starts on that country.
- `auto` — the picker performs a geo-IP lookup via `https://ipapi.co/json` to detect the visitor's country. No personal data is stored; it is a real-time lookup by the visitor's browser.

### Privacy Note

When **Default Country** is set to `auto`, the visitor's browser makes a request to `ipapi.co` to detect their country. This should be disclosed in your site's privacy policy if applicable.

---

## 9. File Upload Fields

### Configuration

Set the following rules on any `[file]` field:

- **Allowed Types** — comma-separated extensions without dots (e.g. `jpg,jpeg,png,pdf,docx`). Default: `jpg,jpeg,png,pdf`.
- **Max File Size (MB)** — maximum size per uploaded file. Default: `5`.
- **Allow Multiple Files** — enable to allow selecting more than one file at once.
- **Show Preview** — enable to display previews below the file input after a file is selected.

### Preview Behaviour

- **Image files** (jpg, jpeg, png, gif, webp): displayed as a 72×72px thumbnail.
- **Other files**: displayed with a generic document icon (📄).
- Each preview item shows the filename below the icon/thumbnail.
- Previews are cleared when the form resets after successful submission.

### Upload Progress Bar

When a form with a file field is submitted, a thin progress bar appears beneath the file input to indicate the upload is in progress. It resets on form success.

### Server-Side File Validation

The server checks:
- File extension against the allowed types list (case-insensitive).
- File size against the max size limit.
- If required, that at least one file was provided.

---

## 10. Developer Reference

### Plugin Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `CFV_VERSION` | `1.0.0` | Plugin version string |
| `CFV_PLUGIN_DIR` | Absolute path | Plugin root directory with trailing slash |
| `CFV_PLUGIN_URL` | URL | Plugin root URL with trailing slash |
| `CFV_PLUGIN_BASENAME` | String | Plugin basename (for deactivation) |

### Post Meta Keys

| Key | Type | Description |
|-----|------|-------------|
| `_cfv_validation_config` | JSON string | Full validation config for one CF7 form |

Stored on `wpcf7_contact_form` post type.

### Config API

**Read config for a form:**
```php
$config = CFV_Config::get( $form_id );
// Returns: [ 'global' => [...], 'fields' => [...] ]
```

**Save config for a form:**
```php
CFV_Config::save( $form_id, $config_array );
```

**Copy config between forms:**
```php
CFV_Config::copy( $source_form_id, $target_form_id );
```

### AJAX Endpoints

Both endpoints require a valid nonce (`cfv_admin`) and the `manage_options` capability.

**Load config:**
```
POST /wp-admin/admin-ajax.php
action=cfv_get_config
form_id=<id>
nonce=<cfv_admin nonce>
```
Returns: `{ success: true, data: { config: {...} } }`

**Save config:**
```
POST /wp-admin/admin-ajax.php
action=cfv_save_config
form_id=<id>
config=<JSON string>
nonce=<cfv_admin nonce>
```
Returns: `{ success: true }` or `{ success: false, data: { message: "..." } }`

### Frontend JavaScript Config Object

The plugin outputs the following inline script before the footer JS:

```javascript
var cfvConfig = {
  forms: {
    "69": {
      global: { show_optional_label: false },
      fields: {
        "your-name": { type: "text", required: true, label: "Your Name", ... },
        "your-email": { type: "email", required: true, label: "Email Address" },
        ...
      }
    }
  },
  ajaxUrl: "https://example.com/wp-admin/admin-ajax.php"
};
```

Keys in `forms` are CF7 form IDs as strings. Each form contains `global` settings and a `fields` object.

### Hooks

**`wpcf7_form_elements`** (filter)  
Used by `CFV_Field_Decorator::decorate()` to inject validation UI elements into rendered form HTML. The filter receives the rendered HTML string and returns the modified string.

**`wpcf7_before_send_mail`** (action)  
Used by `CFV_Hooks::validate_submission()` to run server-side validation. If validation fails, the submission's abort flag is set.

**`wpcf7_ajax_json_echo`** (filter)  
Used by `CFV_Hooks::override_error_response()` to merge field-specific errors into CF7's AJAX response JSON.

**`wpcf7_editor_panels`** (filter)  
Used to add the Validation tab to the CF7 editor.

**`wp_insert_post`** (action)  
Used to detect CF7 form duplication and copy config to the new form.

### Frontend Script Handles

| Handle | File | Description |
|--------|------|-------------|
| `cfv-styles` | `assets/css/cfv-styles.css` | Frontend validation UI styles |
| `cfv-validation` | `assets/js/cfv-validation.js` | Main validation engine |
| `cfv-counter` | `assets/js/cfv-counter.js` | Character counter module |
| `cfv-intl-phone` | `assets/js/cfv-intl-phone.js` | International phone integration |
| `intl-tel-input` | Vendored | Flag picker library |

Frontend scripts are enqueued **only when at least one CF7 form is rendered on the current page**. They are not loaded globally.

### Uninstall

When the plugin is deleted (not just deactivated), the uninstall handler removes all `_cfv_validation_config` meta from the database. No other data is created or modified by this plugin.

---

## 11. FAQ & Troubleshooting

**Do I need to modify my CF7 form shortcodes?**  
No. The plugin works by post-processing the rendered HTML. Your form shortcodes stay exactly as they are.

**Does validation still work if JavaScript is disabled?**  
Yes. All validation rules are implemented independently in PHP and run server-side on every submission. Client-side validation provides the instant feedback; server-side validation provides the security guarantee.

**The Validation tab is not appearing.**  
Ensure Contact Form 7 is installed and active. CF7 Validate Pro auto-deactivates if CF7 is missing. Check **Plugins → Installed Plugins** for a notice.

**I configured rules but the asterisk / error messages are not appearing on the frontend.**  
Check that the form's validation config was saved (click Save Validation Settings and confirm the success message). Also confirm the shortcode on the page uses the correct form ID.

**The "Optional" label is not appearing on optional fields.**  
Enable the **Show Optional Label** toggle in the Global Settings section of the Validation tab, then save.

**Phone validation is failing for valid numbers.**  
If **Enable International Input** is on, the submitted value must be a full E.164 number (e.g. `+353871234567`). Ensure the intl-tel-input JS is loading on the page (check browser console for errors). If the field is just a basic phone, turn off Enable International Input and adjust the Min/Max Digits settings.

**File previews are not showing.**  
Enable the **Show Preview** toggle on the file field in the Validation tab. Previews only show after a file is selected — they do not show on page load.

**The form was duplicated but validation rules are missing on the copy.**  
The copy hook listens for `_dp_original` (Duplicate Post plugin) and `_wpcf7_copy_from` (CF7 native) meta keys. If your duplication method does not set either of these, open the Validation tab on the new form and save the rules manually.

**What SQL injection patterns does the security sanitise rule catch?**  
The textarea security sanitise rule strips or blocks the following patterns from submitted text:

- `<script>` tags and their content
- `<?php` and `<?=` PHP tags
- `eval(` calls
- `DROP TABLE`, `DROP DATABASE`
- `INSERT INTO`
- `SELECT ... FROM` (full SQL SELECT, not the word "select" alone)
- `UPDATE ... SET`
- `DELETE FROM`
- `--` SQL comment prefix

---

## 12. Changelog

### 1.0.1 — Always-On Rules, Radio Handling, Space Enforcement

**Server-side**
- Email, tel, and URL format checks now fire **even when the field is not in the saved validation config** — uses `WPCF7_ContactForm::scan_form_tags()` to discover all format-typed inputs on the form.
- Leading/trailing-space detection now reads the pre-sanitised raw value (before `sanitize_textarea_field` trims it) so the check can actually flag the offence.
- Textarea security-sanitise pattern scan moved to the raw value for the same reason — `<script>` tags were being stripped by WP sanitisation before we could flag them.
- CF7-returned array values (select / checkbox) are unwrapped to a scalar string for non-group types.
- Required check on checkbox / radio filters empty strings so an empty-string POST no longer satisfies the requirement.
- SQL-keyword regex in `strip_dangerous_patterns()` tightened with context so common words like "select" or "update" no longer cause false positives.

**Radio groups**
- Contact Form 7 core always treats a radio group as required. The per-field **Required** toggle is now hidden for radio rows in the Validation tab and replaced by an explanatory note.
- Radio groups always receive the red asterisk (never the "(Optional)" label), whether or not they're in the saved config.
- Server-returned `invalid_fields` for radios are mirrored into our `cfv-error-tip` span via the `wpcf7submit` event, so errors show even when `.wpcf7-not-valid-tip` is hidden.
- Generic browser / CF7 messages like *"Please fill out this field."* are rewritten to `"Please select at least one {Label}"` for radio and checkbox, or `"{Label} is required"` for everything else.

**Space enforcement (config-independent)**
- `email` and `tel` inputs now block the space key on `keydown` and strip whitespace from pasted content. The browser's `type="email"` sanitisation was hiding leading/trailing whitespace from `.value`, so an input-event strip alone wasn't enough.
- `text`, `textarea`, `url`, `number` inputs block leading whitespace on input and strip trailing whitespace on blur.
- These rules apply to every matching field on the form, whether configured or not.

**Error-tip coverage**
- The decorator now injects an empty `cfv-error-tip` span for *every* CF7 field on the form (second pass after the config loop), so server-returned errors always have a DOM target.

**Browser HTML5 validation**
- `wpcf7_form_novalidate` is now filtered to true — browser popups never fire on CF7 forms, so only our `cfv-error-tip` UI surfaces required/format errors.

**Naming**
- Plugin renamed from "CF7 Validate Pro" to **CF7 Validate** (WordPress.org submission prep). Folder is now `cf7-validate/` with main file `cf7-validate.php`.

### 1.0.0 — Initial Release

- Per-field validation rules configurable via CF7 editor Validation tab
- Server-side and client-side validation for all CF7 field types
- Required / optional field badges injected into form HTML
- Inline error messages per field
- Character counters (text, textarea) with colour feedback
- International phone input with flag picker and E.164 enforcement
- File upload validation (type, size, multiple, preview)
- Input mask support for text fields
- Security sanitisation for textarea fields (script tags, SQL patterns)
- Automatic config copy on form duplication
- Upload progress bar for file fields
- Loading spinner on submit button during AJAX submission
