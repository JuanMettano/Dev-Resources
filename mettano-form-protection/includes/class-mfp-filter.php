<?php
/**
 * Elementor Pro form hooks: silent antispam, honeypot, rate limit, blocked IPs
 * and US phone validation.
 */

defined( 'ABSPATH' ) || exit;

class MFP_Filter {

	/** Name of the hidden honeypot input added to every Elementor form. */
	const HONEYPOT = 'mfp_ref_code';

	/** True once a submission in this request has been flagged as spam. */
	private static $block_mail = false;

	/** Cloudflare proxy ranges: only then is CF-Connecting-IP trusted. */
	private static $cloudflare = array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
		'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
		'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
		'2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	);

	public static function init() {
		add_action( 'elementor_pro/forms/validation/tel', array( __CLASS__, 'validate_phone' ), 10, 3 );
		add_action( 'elementor_pro/forms/validation', array( __CLASS__, 'validate_spam' ), 20, 2 );
		add_action( 'phpmailer_init', array( __CLASS__, 'empty_mailer' ), PHP_INT_MAX );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'add_honeypot' ), 10, 2 );
	}

	/* =========================================================
	 * US phone validation
	 * ======================================================= */

	public static function phone_error( $value ) {
		$number = preg_replace( '/\D/', '', (string) $value );

		// Allow the +1 country code.
		if ( 11 === strlen( $number ) && '1' === $number[0] ) {
			$number = substr( $number, 1 );
		}

		// NANP: area code and exchange can't start with 0 or 1.
		if ( ! preg_match( '/^[2-9]\d{2}[2-9]\d{6}$/', $number ) ) {
			return 'Please enter a valid 10-digit US phone number (e.g. 630 555 1234).';
		}

		return '';
	}

	public static function validate_phone( $field, $record, $ajax_handler ) {
		if ( empty( $field['value'] ) ) {
			return;
		}

		$error = self::phone_error( $field['value'] );
		if ( '' !== $error ) {
			$ajax_handler->add_error( $field['id'], $error );
		}
	}

	/* =========================================================
	 * Honeypot
	 * ======================================================= */

	/**
	 * Adds a hidden field to every Elementor form when it is rendered (server side,
	 * so bots that read the HTML see it too). People never see or fill it.
	 */
	public static function add_honeypot( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'form' !== $widget->get_name() ) {
			return $content;
		}

		$settings = MFP_Settings::get();
		if ( empty( $settings['honeypot'] ) || false === strpos( $content, '</form>' ) || false !== strpos( $content, self::HONEYPOT ) ) {
			return $content;
		}

		$field = '<div aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;">'
			. '<label>Leave this field empty <input type="text" name="' . esc_attr( self::HONEYPOT ) . '" value="" tabindex="-1" autocomplete="off"></label>'
			. '</div>';

		// Insert right before the last </form>.
		$pos = strrpos( $content, '</form>' );
		return substr( $content, 0, $pos ) . $field . substr( $content, $pos );
	}

	/* =========================================================
	 * IP helpers
	 * ======================================================= */

	/**
	 * Visitor IP. Uses CF-Connecting-IP only when the request really comes from Cloudflare,
	 * so it can't be spoofed on sites that don't use Cloudflare.
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( $ip && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			foreach ( self::$cloudflare as $range ) {
				if ( self::ip_matches( $ip, $range ) ) {
					$cf = trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
					if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
						$ip = $cf;
					}
					break;
				}
			}
		}

		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
		return (string) apply_filters( 'mfp_client_ip', $ip );
	}

	/**
	 * True if $ip equals $rule, or falls inside $rule when it is a CIDR range (IPv4 or IPv6).
	 */
	public static function ip_matches( $ip, $rule ) {
		$rule = trim( $rule );
		$bin  = @inet_pton( $ip );
		if ( false === $bin || '' === $rule ) {
			return false;
		}

		if ( false === strpos( $rule, '/' ) ) {
			$rule_bin = @inet_pton( $rule );
			return false !== $rule_bin && $rule_bin === $bin;
		}

		list( $subnet, $bits ) = explode( '/', $rule, 2 );
		$subnet_bin = @inet_pton( trim( $subnet ) );
		$bits       = (int) $bits;

		if ( false === $subnet_bin || strlen( $subnet_bin ) !== strlen( $bin ) || $bits < 0 || $bits > strlen( $bin ) * 8 ) {
			return false;
		}

		$full_bytes = intdiv( $bits, 8 );
		if ( substr( $bin, 0, $full_bytes ) !== substr( $subnet_bin, 0, $full_bytes ) ) {
			return false;
		}

		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}

		$mask = chr( ( 0xFF << ( 8 - $rest ) ) & 0xFF );
		return ( $bin[ $full_bytes ] & $mask ) === ( $subnet_bin[ $full_bytes ] & $mask );
	}

	public static function ip_blocked( $ip, array $settings ) {
		if ( '' === $ip ) {
			return '';
		}
		foreach ( MFP_Settings::lines( $settings['blocked_ips'] ) as $rule ) {
			if ( self::ip_matches( $ip, $rule ) ) {
				return 'Blocked IP: ' . $rule;
			}
		}
		return '';
	}

	/**
	 * Key used for the rate limit. IPv6 is grouped by /64 because one connection
	 * owns the whole /64 and bots rotate the last part.
	 */
	private static function rate_key( $ip ) {
		$bin = @inet_pton( $ip );
		if ( false !== $bin && 16 === strlen( $bin ) ) {
			$ip = bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
		}
		return 'mfp_rl_' . md5( $ip );
	}

	/**
	 * Counts this submission and returns a reason if the IP went over the limit.
	 */
	public static function rate_limited( $ip, array $settings ) {
		if ( empty( $settings['rate_limit'] ) || '' === $ip ) {
			return '';
		}

		$max    = max( 1, (int) $settings['rate_max'] );
		$window = max( 1, (int) $settings['rate_window'] ) * MINUTE_IN_SECONDS;
		$key    = self::rate_key( $ip );
		$now    = time();
		$data   = get_transient( $key );

		if ( ! is_array( $data ) || ! isset( $data['c'], $data['t'] ) || ( $now - (int) $data['t'] ) >= $window ) {
			$data = array( 'c' => 0, 't' => $now );
		}

		$data['c']++;
		set_transient( $key, $data, max( 60, $window - ( $now - (int) $data['t'] ) ) );

		if ( $data['c'] > $max ) {
			return sprintf( 'Rate limit: %d submissions in %d min', $data['c'], (int) $settings['rate_window'] );
		}
		return '';
	}

	/* =========================================================
	 * Content check (also used by the admin "Test a message" tool)
	 * ======================================================= */

	/**
	 * Returns the block reason, or '' if the submission is clean.
	 *
	 * @param array $fields   Elementor fields: [ id => [ 'type' => ..., 'value' => ... ] ].
	 * @param array $settings Plugin settings (MFP_Settings::get()).
	 */
	public static function check( array $fields, array $settings ) {
		$emails   = MFP_Settings::email_rules( $settings['emails'] );
		$websites = MFP_Settings::website_rules( $settings['websites'] );
		$keywords = MFP_Settings::lines( $settings['keywords'] );
		$tlds     = MFP_Settings::tld_rules( $settings['blocked_tlds'] );

		foreach ( $fields as $field ) {
			$value = isset( $field['value'] ) && is_string( $field['value'] ) ? trim( $field['value'] ) : '';
			if ( '' === $value ) {
				continue;
			}
			$is_email = isset( $field['type'] ) && 'email' === $field['type'];

			// 1) Email.
			if ( $is_email ) {
				$email  = strtolower( $value );
				$at     = strrchr( $email, '@' );
				$domain = $at ? substr( $at, 1 ) : '';

				if ( in_array( $email, $emails['exact'], true ) ) {
					return 'Blocked email: ' . $email;
				}
				if ( $domain && in_array( $domain, $emails['domains'], true ) ) {
					return 'Blocked email domain: @' . $domain;
				}
				foreach ( $emails['contains'] as $part ) {
					if ( false !== strpos( $email, $part ) ) {
						return 'Email contains: ' . $part;
					}
				}
				continue; // The rest of the checks are for text fields.
			}

			// 2) Websites / links inside the text.
			foreach ( $websites as $site ) {
				if ( false !== stripos( $value, $site ) ) {
					return 'Blocked website: ' . $site;
				}
			}

			// 3) Links: any link at all, or links to suspicious extensions (.top, .xyz…).
			if ( ! empty( $settings['block_all_links'] ) && preg_match( '#https?://|www\.#i', $value ) ) {
				return 'Message contains a link';
			}
			if ( $tlds && preg_match_all( '#(?:https?://)?(?:[a-z0-9-]+\.)+([a-z]{2,24})(?=[/:?\#\s"\'<>)\],]|$)#i', $value, $m ) ) {
				foreach ( $m[0] as $i => $host ) {
					// Skip the domain part of email addresses written in the message.
					$start = strpos( $value, $host );
					if ( false !== $start && $start > 0 && '@' === $value[ $start - 1 ] ) {
						continue;
					}
					$tld = strtolower( $m[1][ $i ] );
					if ( in_array( $tld, $tlds, true ) ) {
						return 'Suspicious link: .' . $tld;
					}
				}
			}

			// 4) Keywords (case-insensitive, whole word, works with Cyrillic/accents).
			foreach ( $keywords as $keyword ) {
				$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $keyword, '/' ) . '(?![\p{L}\p{N}])/iu';
				if ( preg_match( $pattern, $value ) ) {
					return 'Blocked keyword: ' . $keyword;
				}
			}

			// 5) Mostly Cyrillic text.
			if ( ! empty( $settings['block_cyrillic'] ) && preg_match_all( '/\p{Cyrillic}/u', $value ) >= 5 ) {
				return 'Cyrillic text';
			}

			// 6) Bot garbage like "NAYUYUTY2503033NEHTYHYHTR".
			if ( ! empty( $settings['block_bot_text'] ) && preg_match( '/[A-Za-z]{5,}\d{5,}[A-Za-z]{3,}/', $value ) ) {
				return 'Bot garbage text';
			}
		}

		return '';
	}

	/* =========================================================
	 * Main validation hook
	 * ======================================================= */

	public static function validate_spam( $record, $ajax_handler ) {
		$settings = MFP_Settings::get();
		$fields   = (array) $record->get( 'fields' );
		$ip       = self::client_ip();
		$reason   = '';

		// 1) Honeypot filled → bot.
		if ( ! empty( $settings['honeypot'] ) && ! empty( $_POST[ self::HONEYPOT ] ) ) {
			$reason = 'Honeypot';
		}

		// 2) Blocked IP.
		if ( '' === $reason ) {
			$reason = self::ip_blocked( $ip, $settings );
		}

		// 3) Rate limit (every submission from the IP counts, spam or not).
		if ( '' === $reason ) {
			$reason = self::rate_limited( $ip, $settings );
		}

		// 4) Content: emails, websites, links, keywords, Cyrillic, bot text.
		if ( '' === $reason ) {
			$reason = self::check( $fields, $settings );
		}

		if ( '' === $reason ) {
			return;
		}

		$email = '';
		foreach ( $fields as $field ) {
			if ( isset( $field['type'] ) && 'email' === $field['type'] ) {
				$email = (string) $field['value'];
				break;
			}
		}

		$form_name = (string) $record->get_form_settings( 'form_name' );

		MFP_Logger::write( $reason, $email, $form_name, $ip );
		self::$block_mail = true;

		// Cancel every wp_mail() in this request (notification and auto-reply).
		// Mail services like Elementor Site Mailer also hook pre_wp_mail to send through their API,
		// so their filters are removed first (only for this blocked request) or they would still send.
		remove_all_filters( 'pre_wp_mail' );
		add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
	}

	/**
	 * Fallback in case an SMTP plugin ignores pre_wp_mail: empty the message before it is sent.
	 */
	public static function empty_mailer( $phpmailer ) {
		if ( ! self::$block_mail ) {
			return;
		}

		$phpmailer->clearAllRecipients();
		$phpmailer->clearReplyTos();
		$phpmailer->clearAttachments();
		$phpmailer->clearCustomHeaders();
		$phpmailer->Body    = '';
		$phpmailer->AltBody = '';
	}
}
