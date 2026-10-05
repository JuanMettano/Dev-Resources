<?php
/**
 * Elementor Pro form hooks: silent antispam + US phone validation.
 */

defined( 'ABSPATH' ) || exit;

class MFP_Filter {

	/** True once a submission in this request has been flagged as spam. */
	private static $block_mail = false;

	public static function init() {
		add_action( 'elementor_pro/forms/validation/tel', array( __CLASS__, 'validate_phone' ), 10, 3 );
		add_action( 'elementor_pro/forms/validation', array( __CLASS__, 'validate_spam' ), 20, 2 );
		add_action( 'phpmailer_init', array( __CLASS__, 'empty_mailer' ), PHP_INT_MAX );
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
	 * Antispam
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

		foreach ( $fields as $field ) {
			$value = isset( $field['value'] ) && is_string( $field['value'] ) ? trim( $field['value'] ) : '';
			if ( '' === $value ) {
				continue;
			}

			// 1) Email.
			if ( isset( $field['type'] ) && 'email' === $field['type'] ) {
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
			}

			// 2) Websites / links inside the text.
			foreach ( $websites as $site ) {
				if ( false !== stripos( $value, $site ) ) {
					return 'Blocked website: ' . $site;
				}
			}

			// 3) Keywords (case-insensitive, whole word, works with Cyrillic/accents).
			foreach ( $keywords as $keyword ) {
				$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $keyword, '/' ) . '(?![\p{L}\p{N}])/iu';
				if ( preg_match( $pattern, $value ) ) {
					return 'Blocked keyword: ' . $keyword;
				}
			}

			// 4) Mostly Cyrillic text.
			if ( ! empty( $settings['block_cyrillic'] ) && preg_match_all( '/\p{Cyrillic}/u', $value ) >= 5 ) {
				return 'Cyrillic text';
			}

			// 5) Bot garbage like "NAYUYUTY2503033NEHTYHYHTR".
			if ( ! empty( $settings['block_bot_text'] ) && preg_match( '/[A-Za-z]{5,}\d{5,}[A-Za-z]{3,}/', $value ) ) {
				return 'Bot garbage text';
			}
		}

		return '';
	}

	public static function validate_spam( $record, $ajax_handler ) {
		$fields = (array) $record->get( 'fields' );
		$reason = self::check( $fields, MFP_Settings::get() );

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

		MFP_Logger::write( $reason, $email, $form_name );
		self::$block_mail = true;

		// Cancel every wp_mail() in this request (notification and auto-reply).
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
