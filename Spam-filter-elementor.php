
<?php
/**
 * IMPORTANT: TO USE THIS CODE, ADD IT AS A PHP SNIPPET SET TO "AUTO INSERT" -> "RUN EVERYWHERE".
 *
 * Elementor Forms – Silent antispam (keywords + emails)
 *
 * When a submission matches any of the lists below:
 *   - the spammer sees the normal success message (they don't know it was blocked),
 *   - NO email is sent,
 *   - the reason is logged to wp-content/elementor-antispam/log.txt.
 * Note: the submission is still saved in Elementor > Submissions (useful to review false positives).
 */

/* =========================================================
 * LISTS – edit only this section
 * ======================================================= */

function elementor_antispam_lists() {
	return [

		// Exact email addresses.
		'emails' => [
			'admin@lichnyj-kabinet.ru',
			'arklay@lichnyj-kabinet.ru',
			'arklay@mail.com',
			'oliverbrooks0808@gmail.com',
			'santiagoedward59@gmail.com',
			'eva.rocketdigitaltech@gmail.com',
		],

		// Email domains (anything coming from @domain).
		'email_domains' => [
			'lichnyj-kabinet.ru',
			'notboxletters.com',
		],

		// Email substrings (for vendors who keep switching accounts).
		'email_contains' => [
			'rocketdigital',
			'seoagency',
			'webdesign',
			'digitalmarketing',
		],

		// Domains/links inside the message.
		'content_domains' => [
			'mega.nz',
			'telegra.ph',
			't.me/',
			'wa.me/',
			'bit.ly/',
		],

		// Keywords or phrases in the message (case-insensitive; whole-word match).
		'content_keywords' => [

			// --- Web design / development ---
			'website redesign',
			'revamp',
			'web development',
			'website design',
			'website development',
			'app development',
			'mobile app',
			'ui/ux',
			'e-commerce website',

			// --- Marketing / sales ---
			'digital marketing',
			'social media marketing',
			'online branding',
			'brand promotion',
			'lead generation',
			'price list',
			'pricing options',
			'brief proposal',
			'white label',
			'virtual assistant',

			// --- Abusive content / other languages ---
			'brothers in faith',
			'penetrate',
			'violate',
			'rape',
			'criminals',
			'explosives',
			'saudi',
			'satanic violation',
			'Hejka',
			'zwracam',
			'kupilem',
			'Если',
			'Надежный',
			'aвтосервис',

			// ⚠️ Disabled: on therapy sites a real client may write these
			// (grief, suicidal thoughts). Enable them only on sites where that doesn't apply.
			// 'death',
			// 'killing',
		],

		// Block text with many Cyrillic characters (Russian spam).
		'block_cyrillic' => true,
	];
}

/* =========================================================
 * LOG
 * ======================================================= */

function elementor_antispam_write_log( $reason, $email = '' ) {
	$dir = WP_CONTENT_DIR . '/elementor-antispam';

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$htaccess = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( $htaccess, "Require all denied\n" );
	}

	$index = $dir . '/index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$line = current_time( 'mysql' ) . ' | ' . $reason . ' | ' . $email . ' | ' . $ip . "\n";

	file_put_contents( $dir . '/log.txt', $line, FILE_APPEND | LOCK_EX );
}

/* =========================================================
 * DETECTION
 * Returns the block reason, or '' if the submission is clean.
 * ======================================================= */

function elementor_antispam_check( array $fields ) {
	$l = elementor_antispam_lists();

	$emails        = array_map( 'strtolower', $l['emails'] );
	$email_domains = array_map( 'strtolower', $l['email_domains'] );

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

			if ( in_array( $email, $emails, true ) ) {
				return 'correo bloqueado: ' . $email;
			}
			if ( $domain && in_array( $domain, $email_domains, true ) ) {
				return 'dominio de correo bloqueado: ' . $domain;
			}
			foreach ( $l['email_contains'] as $part ) {
				if ( false !== stripos( $email, $part ) ) {
					return 'correo contiene: ' . $part;
				}
			}
		}

		// 2) Domains/links inside the text.
		foreach ( $l['content_domains'] as $bad_domain ) {
			if ( false !== stripos( $value, $bad_domain ) ) {
				return 'contenido con dominio bloqueado: ' . $bad_domain;
			}
		}

		// 3) Keywords. The "u" flag makes it work with Cyrillic/accented characters.
		foreach ( $l['content_keywords'] as $keyword ) {
			$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $keyword, '/' ) . '(?![\p{L}\p{N}])/iu';
			if ( preg_match( $pattern, $value ) ) {
				return 'palabra bloqueada: ' . $keyword;
			}
		}

		// 4) Mostly Cyrillic text.
		if ( ! empty( $l['block_cyrillic'] ) && preg_match_all( '/\p{Cyrillic}/u', $value ) >= 5 ) {
			return 'texto en cirílico';
		}

		// 5) Bot garbage like "NAYUYUTY2503033NEHTYHYHTR".
		if ( preg_match( '/[A-Za-z]{5,}\d{5,}[A-Za-z]{3,}/', $value ) ) {
			return 'texto basura de bot';
		}
	}

	return '';
}

/* =========================================================
 * HOOKS
 * ======================================================= */

$GLOBALS['elementor_antispam_block_next_mail'] = false;

add_action( 'elementor_pro/forms/validation', function ( $record, $ajax_handler ) {
	$fields = $record->get( 'fields' );
	$reason = elementor_antispam_check( $fields );

	if ( '' === $reason ) {
		return;
	}

	$email = '';
	foreach ( $fields as $field ) {
		if ( isset( $field['type'] ) && 'email' === $field['type'] ) {
			$email = $field['value'];
			break;
		}
	}

	elementor_antispam_write_log( $reason, $email );
	$GLOBALS['elementor_antispam_block_next_mail'] = true;

	// Cancel every wp_mail() in this request (notification to the client and auto-reply).
	add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
}, 10, 2 );

// Fallback in case an SMTP plugin ignores pre_wp_mail: empty the message before it is sent.
add_action( 'phpmailer_init', function ( $phpmailer ) {
	if ( empty( $GLOBALS['elementor_antispam_block_next_mail'] ) ) {
		return;
	}

	$phpmailer->clearAllRecipients();
	$phpmailer->clearReplyTos();
	$phpmailer->clearAttachments();
	$phpmailer->clearCustomHeaders();
	$phpmailer->Body    = '';
	$phpmailer->AltBody = '';
}, PHP_INT_MAX );
