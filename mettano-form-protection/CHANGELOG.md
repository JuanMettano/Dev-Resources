# Changelog

## 1.1.0
- Rate limit per IP (default 8 submissions every 10 minutes; IPv6 grouped by /64).
- Blocked IPs list (exact IPs and CIDR ranges, IPv4/IPv6).
- Honeypot field added automatically to every Elementor form.
- Suspicious links: blocked domain extensions (.top, .xyz, .ru…) and optional "block any link".
- Real visitor IP behind Cloudflare (header trusted only from Cloudflare ranges).
- Test tool accepts an IP.

## 1.0.1
- Fix: blocked submissions could still be sent by mail services that hook `pre_wp_mail` (Elementor Site Mailer).

## 1.0.0
- First release: blocked keywords, websites and emails, Cyrillic and bot-text detection, US phone validation, admin panel, log.
