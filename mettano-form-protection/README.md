# Mettano Form Protection

Silent antispam and US phone validation for Elementor Pro forms.

## Install / update
1. Plugins → Add New → Upload Plugin → choose `mettano-form-protection.zip`.
2. To update, upload the new ZIP and choose **Replace current with uploaded**. Settings and log are kept.
3. Deactivate the old Code Snippets ("Elementor antispam" and "US phone validation") — the plugin shows a warning while they're still active.

## Settings → Form Protection
- **Blocked keywords** — one per line, case-insensitive, whole-word.
- **Blocked websites** — one per line, matches anywhere in the message.
- **Blocked emails** — `name@domain.com` (exact), `@domain.com` (whole domain), `text` (address contains).
- Lines starting with `#` are notes.
- **Test a message** — check a submission against the saved settings.
- **Blocked submissions** — log with date, reason, email, IP and form name.

## How blocking works
The submission passes validation, so the visitor sees the success message, but every `wp_mail()` in that request is cancelled (`pre_wp_mail`), with a `phpmailer_init` fallback for SMTP plugins. The submission is still stored in Elementor → Submissions.

Webhooks, CRM and other non-email form actions still run.

## Release
Bump `Version:` and `MFP_VERSION` in `mettano-form-protection.php`, then zip the `mettano-form-protection` folder (the ZIP must contain the folder, not the loose files).
