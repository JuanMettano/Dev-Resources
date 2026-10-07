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
- **Blocked IPs** — exact IPs or CIDR ranges, IPv4 and IPv6.
- **Suspicious links** — blocked domain extensions (`top`, `xyz`, `ru`…) and an option to block any link.
- **Honeypot** — hidden field added automatically to every Elementor form; bots that fill it are blocked.
- **Rate limit** — default 8 submissions per IP every 10 minutes (IPv6 grouped by /64).
- Lines starting with `#` are notes.
- **Test a message** — check a submission (and optionally an IP) against the saved settings.
- **Blocked submissions** — log with date, reason, email, IP and form name.

## How blocking works
Order of checks: honeypot → blocked IP → rate limit → content (emails, websites, links, keywords, Cyrillic, bot text).

The submission passes validation, so the visitor sees the success message, but every `wp_mail()` in that request is cancelled (`pre_wp_mail`, removing other mailers' filters such as Elementor Site Mailer), with a `phpmailer_init` fallback for SMTP plugins. The submission is still stored in Elementor → Submissions. Webhooks, CRM and other non-email form actions still run.

The visitor IP is `REMOTE_ADDR`; `CF-Connecting-IP` is used only when the request comes from a Cloudflare range.

## Release
Bump `Version:` and `MFP_VERSION` in `mettano-form-protection.php`, update `CHANGELOG.md`, then zip the `mettano-form-protection` folder (the ZIP must contain the folder, not the loose files).
