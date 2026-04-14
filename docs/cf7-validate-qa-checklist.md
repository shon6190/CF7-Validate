# CF7 Validate — QA Test Checklist

**Version under test:** 1.0.1
**Scope:** Client-side + server-side validation, admin Validation tab, field decoration, CF7 integration.
**Prerequisites:** Contact Form 7 active, test form created with at least one of every field type, admin access.

Legend: ✅ expected pass · ❌ expected fail (blocks submit) · ⚠️ behaviour to observe

---

## 1. Admin — Validation Tab

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1.1 | Tab appears | Open any CF7 form editor | ✅ "Validation" tab visible next to Mail / Messages |
| 1.2 | Fields auto-listed | Add `[text* your-name]` to the form body, open Validation tab | ✅ `your-name` appears in the field list with type `text` |
| 1.3 | Field type detection | Add tags: `text`, `email`, `tel`, `number`, `url`, `textarea`, `select`, `checkbox`, `radio`, `file` | ✅ each row shows the correct type label |
| 1.4 | Name heuristic | Add `[text first-name]` | ✅ detected as type `name` (not `text`) |
| 1.5 | Required toggle — non-radio | Toggle Required on a text field and save | ✅ config persists after reload |
| 1.6 | Required toggle — radio | Open a radio row | ✅ no Required toggle; note explains CF7 core enforcement |
| 1.7 | Global Optional label | Enable "Show '(Optional)' label on non-required fields" and save | ✅ frontend shows `(Optional)` after every non-required, non-radio label |
| 1.8 | Save + reload | Save form, reload editor | ✅ all field settings restored |
| 1.9 | Form duplication | Duplicate the form (CF7 native or Duplicate Post) | ✅ validation config copied to the new form |
| 1.10 | Uninstall cleanup | Deactivate + delete plugin | ✅ `_cfv_validation_config` post meta removed from all forms |

---

## 2. Field Decoration (Frontend HTML)

| # | Test | Expected |
|---|------|----------|
| 2.1 | Required asterisk | Red `*` appears on required field labels |
| 2.2 | Optional label | `(Optional)` on non-required fields when global toggle is on |
| 2.3 | Radio asterisk | Red `*` on every radio group label, regardless of config |
| 2.4 | Error-tip span | Every CF7 field has an empty `<span class="cfv-error-tip" data-field="...">` after the control wrap |
| 2.5 | Character counter | Textarea / text with `counter_format` set shows live `X / max` counter |
| 2.6 | No native popup | Submit invalid form → **no** browser "Please fill out this field" popup |

---

## 3. Required-Field Behaviour

### 3.1 Non-radio required field left empty
| Steps | Expected |
|-------|----------|
| 1. Leave a required text / email / tel / select / checkbox field empty.<br>2. Click submit. | ❌ Red message `"{Label} is required"` appears below the field.<br>❌ Form does not submit.<br>❌ Submit button briefly disables then re-enables. |

### 3.2 Required checkbox — none checked
| Steps | Expected |
|-------|----------|
| Leave all checkbox options unchecked. Submit. | ❌ `"Please select at least one {Label}"` |

### 3.3 Radio — none selected (CF7 core requirement)
| Steps | Expected |
|-------|----------|
| Do not select any radio option. Submit. | ❌ `"Please select at least one {Label}"` — surfaced via our span (native tip is hidden). |

### 3.4 Required field filled correctly
| Steps | Expected |
|-------|----------|
| Fill all required fields with valid values. Submit. | ✅ Form submits, success message shows. |

---

## 4. Space Enforcement (Config-Independent)

### 4.1 Email / Tel — typing spaces
| Steps | Expected |
|-------|----------|
| Click into email / tel field. Press Space. | ⚠️ Nothing happens — keystroke blocked. |
| Type `" foo@bar.com"` (leading space attempts). | ✅ Leading space never appears; value = `"foo@bar.com"`. |
| Type `"foo @bar.com"` (middle space). | ✅ Space is blocked, value = `"foo@bar.com"`. |

### 4.2 Email / Tel — pasting with spaces
| Steps | Expected |
|-------|----------|
| Paste `"  hello @ world.com  "` into email. | ✅ All whitespace stripped to `"hello@world.com"`. |
| Paste `" + 91 98765 43210 "` into tel. | ✅ Stripped to `"+919876543210"`. |

### 4.3 Text / Textarea / URL / Number — leading whitespace
| Steps | Expected |
|-------|----------|
| Type `"   john"` in a text field. | ✅ Leading spaces stripped on each keystroke; value = `"john"`. |
| Type `"john   "` then Tab (blur). | ✅ Trailing spaces stripped on blur; value = `"john"`. |
| Submit with only spaces entered (required text). | ❌ Treated as empty → `"{Label} is required"`. |

---

## 5. Format Validation (Always-On, No Config Needed)

> These fire even if the field is **not** listed in the Validation tab's saved config.

### 5.1 Email format
| Input | Expected |
|-------|----------|
| `foo` | ❌ `"Email must be a valid email address"` |
| `foo@bar` | ❌ Same error |
| `foo@bar.com` | ✅ Passes |
| (empty, not required) | ✅ Passes (no error) |

### 5.2 URL format
| Input | Expected |
|-------|----------|
| `example.com` | ❌ `"URL must be a valid URL including http:// or https://"` |
| `http://example.com` | ✅ Passes |
| `ftp://example.com` | ❌ Must be http/https |

### 5.3 Tel format
| Input | Expected |
|-------|----------|
| `abcd` | ❌ `"phone must be a valid phone number"` |
| `12345` (too short, default min=7) | ❌ Same |
| `9876543210` | ✅ Passes |

---

## 6. Configured Rules — Type-Specific

### 6.1 Text / Name
| Rule | Invalid input | Expected error |
|------|---------------|----------------|
| `min_length: 5` | `"abc"` | `"{Label} must be at least 5 characters"` |
| `max_length: 10` | `"abcdefghijk"` | `"{Label} must be no more than 10 characters"` |
| `alpha_only: true` | `"John123"` | `"{Label} must contain letters only"` |
| `allow_special_chars: false` | `"John#"` | `"{Label} must not contain special characters"` |
| `allow_emoji: false` | `"John 😀"` | `"{Label} must not contain emoji"` |

### 6.2 Number
| Rule | Invalid input | Expected error |
|------|---------------|----------------|
| `min_value: 10` | `5` | `"{Label} must be at least 10"` |
| `max_value: 100` | `500` | `"{Label} must be no more than 100"` |
| `allow_negative: false` | `-5` | `"{Label} must be a positive number"` |
| `allow_zero: false` | `0` | `"{Label} cannot be zero"` |
| Non-numeric input | `"abc"` | `"{Label} must contain numbers only"` |
| **Negative min_value preserved** | Set `min_value: -50` in admin, save, reload | ✅ Config still shows `-50` (not `50`) |

### 6.3 Textarea
| Rule | Invalid input | Expected error |
|------|---------------|----------------|
| `max_length: 1500` | Paste 1600 characters | Browser prevents typing past limit; server rejects paste |
| `security_sanitize: true` | `"<script>alert(1)</script>"` | `"{Label} contains invalid characters"` |
| `security_sanitize: true` | `"I want to select the best option"` | ✅ Passes (no false positive on the word "select") |

### 6.4 Select
| Rule | Invalid input | Expected error |
|------|---------------|----------------|
| `required: true`, `placeholder_value: "Choose..."` | Leave on placeholder | `"{Label} is required"` |
| Required, valid option chosen | | ✅ Passes |

### 6.5 File
| Rule | Invalid input | Expected error |
|------|---------------|----------------|
| `allowed_types: "jpg,png"` | Upload `.pdf` | `"{Label} accepts jpg,png files only"` |
| `max_size_mb: 2` | Upload 5 MB file | `"{Label} must be under 2MB"` |
| `show_preview: true`, image | Select JPG | ✅ Thumbnail preview appears below field |
| `show_preview: true`, non-image | Select PDF | ✅ Document icon + filename shown |
| `allow_multiple: true` | Select 3 files | ✅ All 3 previews listed |

---

## 7. International Phone (intl-tel-input)

| # | Test | Expected |
|---|------|----------|
| 7.1 | Flag picker visible | `enable_intl: true` on a tel field → country flag dropdown renders |
| 7.2 | Default country | `default_country: "in"` → India flag pre-selected |
| 7.3 | E.164 sent to server | Submit valid number → PHP receives `cfv_phone_full_{name}` as `+919876543210` |
| 7.4 | Invalid intl format | Submit alpha characters → `"{Label} — only numbers are allowed"` |
| 7.5 | Phone too short | Number below min_length → `"{Label} must be a valid phone number"` |
| 7.6 | Reset on success | After successful submit, phone field resets to default country and empty value |

---

## 8. Submit & Error UX

| # | Scenario | Expected |
|---|----------|----------|
| 8.1 | Multiple invalid fields | All errors show simultaneously; focus moves to first error field |
| 8.2 | Error clears on fix | Typing valid content clears that field's `cfv-error-tip` immediately |
| 8.3 | Submit button state | Disables on valid submit; re-enables on `wpcf7invalid`, `wpcf7mailfailed`, `wpcf7spam` |
| 8.4 | Progress bar | Shows 70% on valid submit; resets to 0% on CF7 response events |
| 8.5 | Spam response | Submit button re-enables; no stuck "loading" state |
| 8.6 | Mail failure | Same — submit button recovers |
| 8.7 | Success | `wpcf7mailsent` → form resets, all error tips cleared, phone reset, progress cleared |

---

## 9. Server-Side Security (JS Disabled)

> Disable JavaScript in browser and retry.

| # | Test | Expected |
|---|------|----------|
| 9.1 | Required-field bypass attempt | Submit empty required fields → server returns `validation_failed`, errors appear in `cfv-error-tip` spans (since PHP decorator runs regardless) |
| 9.2 | Email format bypass | Submit `foo` in email (not in config) → server still flags via un-managed format check |
| 9.3 | XSS attempt | Textarea with `security_sanitize: true`, submit `<script>` → server rejects |
| 9.4 | SQL keywords in prose | Submit textarea `"Please select my entry and update me"` → ✅ passes (no false positive) |

---

## 10. Multi-Form & Edge Cases

| # | Test | Expected |
|---|------|----------|
| 10.1 | Two forms on one page | Errors, asterisks, progress bars are scoped per form — no cross-talk |
| 10.2 | Same form twice on one page | Separate intl-tel-input instances; each form validates independently |
| 10.3 | CF7 deactivated | CF7 Validate auto-deactivates; admin notice shown |
| 10.4 | Form without any configured rules | Form still submits; only always-on rules (spaces, email/tel/url format, radio required) apply |
| 10.5 | Case-mismatched field names | Field `Your-Email` in shortcode with `your-email` in old config → only decorates once; config migrates to canonical parsed name |

---

## 11. Regression Checks

| # | Past Bug | Re-test |
|---|----------|---------|
| 11.1 | BUG-001 | Textarea with `security_sanitize`, submit `<script>alert(1)</script>` → ❌ rejected (not silently stripped) |
| 11.2 | BUG-002 | Checkbox required with empty-string in POST → ❌ still flagged |
| 11.3 | BUG-003 | Text field submitted as `"  john"` → ❌ flagged "must not have leading or trailing spaces" |
| 11.4 | BUG-004 | Textarea content `"I want to update my profile"` → ✅ passes |
| 11.5 | BUG-005 | Select submitted as array → correctly unwrapped, validated as scalar |

---

## Reporting Template

When a test fails, capture:

- **Test ID** (e.g. 4.2, 6.3, 11.4)
- **Field type + configured rules**
- **Exact input** used
- **Expected** vs **actual** result
- **Browser + version** (Chrome, Firefox, Safari, Edge)
- **CF7 version** and **WordPress version**
- Screenshot or screen recording
- Any errors in the browser console
- AJAX response JSON (Network tab) for submit-level failures
