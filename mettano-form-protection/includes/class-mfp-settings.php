<?php
/**
 * Settings storage, defaults and list parsing.
 */

defined( 'ABSPATH' ) || exit;

class MFP_Settings {

	const OPTION = 'mfp_settings';

	/**
	 * Default values loaded the first time the plugin is activated.
	 */
	public static function defaults() {
		return array(
			'keywords'        => implode(
				"\n",
				array(
					'# Web design / development',
					'website redesign',
					'revamp',
					'web development',
					'website design',
					'website development',
					'app development',
					'mobile app',
					'ui/ux',
					'e-commerce website',
					'# Marketing / sales',
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
					'# Abusive content / other languages',
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
				)
			),
			'websites'        => implode(
				"\n",
				array(
					'mega.nz',
					'telegra.ph',
					't.me/',
					'wa.me/',
					'bit.ly/',
				)
			),
			'emails'          => implode(
				"\n",
				array(
					'# Exact addresses',
					'admin@lichnyj-kabinet.ru',
					'arklay@lichnyj-kabinet.ru',
					'arklay@mail.com',
					'oliverbrooks0808@gmail.com',
					'santiagoedward59@gmail.com',
					'eva.rocketdigitaltech@gmail.com',
					'# Whole domains',
					'@lichnyj-kabinet.ru',
					'@notboxletters.com',
					'# Address contains',
					'rocketdigital',
					'seoagency',
					'webdesign',
					'digitalmarketing',
				)
			),
			'block_cyrillic'  => 1,
			'block_bot_text'  => 1,
		);
	}

	public static function activate() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Turns a textarea value into a clean list. Lines starting with # are comments.
	 */
	public static function lines( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			$out[] = $line;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Splits the email list into exact addresses, whole domains and "contains" parts.
	 *   name@domain.com  -> exact address
	 *   @domain.com      -> whole domain
	 *   anything else    -> address contains this text
	 */
	public static function email_rules( $text ) {
		$rules = array(
			'exact'    => array(),
			'domains'  => array(),
			'contains' => array(),
		);

		foreach ( self::lines( $text ) as $line ) {
			$line = strtolower( $line );
			if ( 0 === strpos( $line, '@' ) ) {
				$rules['domains'][] = substr( $line, 1 );
			} elseif ( false !== strpos( $line, '@' ) ) {
				$rules['exact'][] = $line;
			} else {
				$rules['contains'][] = $line;
			}
		}

		return $rules;
	}

	/**
	 * Websites: strips http(s):// and www. so "https://www.mega.nz" works the same as "mega.nz".
	 */
	public static function website_rules( $text ) {
		$out = array();
		foreach ( self::lines( $text ) as $line ) {
			$line = strtolower( $line );
			$line = preg_replace( '#^https?://#', '', $line );
			$line = preg_replace( '#^www\.#', '', $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize callback for register_setting().
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		foreach ( array( 'keywords', 'websites', 'emails' ) as $key ) {
			$value = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$value = sanitize_textarea_field( $value );
			// Normalize line endings and drop empty lines / duplicates, keep comments.
			$lines = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $value ) as $line ) {
				$line = trim( $line );
				if ( '' !== $line && ! in_array( $line, $lines, true ) ) {
					$lines[] = $line;
				}
			}
			$clean[ $key ] = implode( "\n", $lines );
		}

		$clean['block_cyrillic'] = empty( $input['block_cyrillic'] ) ? 0 : 1;
		$clean['block_bot_text'] = empty( $input['block_bot_text'] ) ? 0 : 1;

		return $clean;
	}
}
