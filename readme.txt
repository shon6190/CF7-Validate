=== CF7 Validate ===
Contributors:      shon6190
Tags:              contact form 7, cf7, validation, form validation, phone validation
Requires at least: 5.9
Tested up to:      6.7
Stable tag:        1.0.0
Requires PHP:      7.4
Requires Plugins:  contact-form-7
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive client-side and server-side validation addon for Contact Form 7, configurable per-field via a dedicated Validation tab in the CF7 editor.

== Description ==

CF7 Validate adds powerful, flexible validation to Contact Form 7 forms — without touching your form shortcodes. Configure rules per field directly inside the CF7 editor using the new **Validation** tab.

**Features:**

* **Required field marking** — red asterisk (*) on required fields, (Optional) label on non-required fields
* **Per-field error messages** — inline error tips appear below each field with custom label support
* **Text & Name fields** — min/max length, alpha-only, special character control, emoji blocking, whitespace collapse, input masking, character counter
* **Email** — format validation, automatic leading/trailing space trimming
* **Phone** — min/max digit length; optional international flag selector with dial code (powered by intl-tel-input)
* **Number** — min/max value, negative and zero control
* **URL** — format validation (must include http:// or https://)
* **Textarea / Message** — max character limit (default 1500), live character counter, max height with scrollbar, security pattern stripping
* **Select / Dropdown** — placeholder value detection
* **Checkbox & Radio** — required selection enforcement
* **File upload** — allowed file types, max file size, multiple file support, file preview, upload progress bar
* **Submit button** — disabled during submission with loading spinner, re-enabled on any CF7 response
* **Multi-form pages** — multiple forms on the same page are fully isolated
* **Form duplication** — validation config copies automatically when a CF7 form is duplicated

All validation runs both **client-side** (instant feedback) and **server-side** (secure fallback).

**Requirements:**

* WordPress 5.9 or higher
* PHP 7.4 or higher
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) (must be installed and active)

== Installation ==

1. Upload the `cf7-validate` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Make sure Contact Form 7 is installed and active
4. Open any CF7 form editor — a new **Validation** tab will appear
5. Configure rules per field and click **Save Validation Settings**

== Frequently Asked Questions ==

= Does this work with all CF7 field types? =

Yes — text, name, email, phone (tel), number, URL, textarea, select, checkbox, radio, and file upload fields are all supported.

= Do I need to change my CF7 shortcodes? =

No. CF7 Validate works by post-processing the rendered form HTML and reading the CF7 form editor configuration. Your shortcodes stay unchanged.

= Does validation run on the server too? =

Yes. Client-side validation provides instant feedback. Server-side validation runs independently as a fallback, so validation cannot be bypassed by disabling JavaScript.

= What happens if Contact Form 7 is deactivated? =

CF7 Validate will automatically deactivate itself and show an admin notice asking you to install Contact Form 7.

= Is the international phone input (intl-tel-input) free? =

Yes. The international phone flag selector is bundled with the plugin at no cost. When the default country is set to "auto", the plugin makes a request to ipapi.co to detect the visitor's country — see the Privacy Policy section for details.

= What does "Strip code/security operators" do on textarea fields? =

When enabled, the textarea field rejects submissions that contain patterns resembling code injection or SQL attacks (e.g. `<script>`, `eval()`, `DROP TABLE`, `<?php`). It is enabled by default as a safety net for public-facing contact forms.

== Privacy Policy ==

**International Phone — Geo-IP Detection**

When the international phone input is enabled for a field and the default country is set to **"auto"**, this plugin makes a request to a third-party geo-IP service to detect the visitor's country:

* **Service:** [ipapi.co](https://ipapi.co)
* **Data sent:** The visitor's IP address (sent automatically by the browser as part of any HTTP request)
* **Data received:** Country code (e.g. `US`, `GB`) used to pre-select the phone flag
* **Purpose:** Pre-selecting the correct country flag in the phone input for a better user experience
* **When it runs:** Only on pages that display a CF7 form with international phone enabled, and only when the default country is set to "auto"

To avoid this external request, set the **Default country code** to a specific country (e.g. `us`, `gb`) instead of `auto`. No request will be made when a specific country is configured.

No data is stored or logged by this plugin. For ipapi.co's privacy policy, visit [https://ipapi.co/privacy/](https://ipapi.co/privacy/).

== Screenshots ==

1. Validation tab in the CF7 form editor — per-field rule configuration
2. Frontend form with required asterisks, optional labels, and inline error messages
3. Character counter on a textarea field
4. International phone input with flag selector

== Changelog ==

= 1.0.0 =
* Initial release
* Client-side and server-side validation for all CF7 field types
* International phone input (intl-tel-input v18.2.1)
* Character counter for text and textarea fields
* File upload preview and progress bar
* React-powered Validation tab in the CF7 editor
* Form duplication config copy support

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
