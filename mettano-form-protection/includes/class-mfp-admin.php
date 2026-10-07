<?php
/**
 * Admin panel: Settings → Form Protection.
 */

defined( 'ABSPATH' ) || exit;

class MFP_Admin {

	const SLUG = 'mettano-form-protection';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_mfp_clear_log', array( __CLASS__, 'clear_log' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MFP_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_options_page( 'Form Protection', 'Form Protection', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function register() {
		register_setting(
			'mfp',
			MFP_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'MFP_Settings', 'sanitize' ),
			)
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Settings</a>' );
		return $links;
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Warnings shown on every admin page.
	 */
	public static function notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// The old Code Snippets version is still active: both would run.
		if ( function_exists( 'elementor_antispam_lists' ) || function_exists( 'elementor_antispam_write_log' ) ) {
			echo '<div class="notice notice-warning"><p><strong>Mettano Form Protection:</strong> the old "Elementor antispam" code snippet is still active. Deactivate it in Snippets so the filter doesn\'t run twice. Also deactivate the old US phone validation snippet.</p></div>';
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'settings_page_' . self::SLUG === $screen->id && ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			echo '<div class="notice notice-error"><p><strong>Elementor Pro is not active.</strong> This plugin only protects Elementor Pro forms.</p></div>';
		}
	}

	public static function clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'mfp_clear_log' );
		MFP_Logger::clear();
		wp_safe_redirect( self::url( array( 'mfp_cleared' => 1 ) ) . '#mfp-log' );
		exit;
	}

	/**
	 * Runs the test tool if it was submitted. Returns [ 'spam' => string, 'phone' => string ] or null.
	 */
	private static function run_test() {
		if ( empty( $_POST['mfp_test'] ) ) {
			return null;
		}
		check_admin_referer( 'mfp_test' );

		$email   = isset( $_POST['mfp_test_email'] ) ? sanitize_text_field( wp_unslash( $_POST['mfp_test_email'] ) ) : '';
		$phone   = isset( $_POST['mfp_test_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['mfp_test_phone'] ) ) : '';
		$message = isset( $_POST['mfp_test_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mfp_test_message'] ) ) : '';
		$ip      = isset( $_POST['mfp_test_ip'] ) ? sanitize_text_field( wp_unslash( $_POST['mfp_test_ip'] ) ) : '';
		$s       = MFP_Settings::get();

		$fields = array(
			'email'   => array( 'type' => 'email', 'value' => $email ),
			'message' => array( 'type' => 'textarea', 'value' => $message ),
		);

		return array(
			'email'   => $email,
			'phone'   => $phone,
			'message' => $message,
			'ip'      => $ip,
			'spam'    => '' !== $ip && '' !== MFP_Filter::ip_blocked( $ip, $s ) ? MFP_Filter::ip_blocked( $ip, $s ) : MFP_Filter::check( $fields, $s ),
			'phone_e' => '' === $phone ? '' : MFP_Filter::phone_error( $phone ),
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s    = MFP_Settings::get();
		$opt  = MFP_Settings::OPTION;
		$test = self::run_test();
		$rows = MFP_Logger::read( 200 );
		?>
		<div class="wrap mfp-wrap">
			<h1>Mettano Form Protection <span class="mfp-version">v<?php echo esc_html( MFP_VERSION ); ?></span></h1>
			<p class="description">Blocks spam on Elementor Pro forms <strong>silently</strong>: the visitor sees the normal success message, but no email is sent. Blocked submissions still appear in Elementor → Submissions and are listed in the log below. Phone fields only accept valid 10-digit US numbers.</p>

			<?php if ( ! empty( $_GET['mfp_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Log cleared.</p></div>
			<?php endif; ?>

			<style>
				.mfp-version{font-size:13px;color:#646970;font-weight:400}
				.mfp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-top:16px}
				.mfp-card{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px 18px}
				.mfp-card h2{margin:0 0 4px;font-size:15px}
				.mfp-card p.description{margin:0 0 10px}
				.mfp-card textarea{width:100%;min-height:340px;font-family:Menlo,Consolas,monospace;font-size:12.5px;line-height:1.5}
				.mfp-card code{font-size:11.5px}
				.mfp-opts label{display:block;margin:6px 0}
				.mfp-opts input[type=number]{width:70px}
				.mfp-card textarea.mfp-short{min-height:180px}
				.mfp-result{padding:10px 12px;border-radius:4px;margin-top:12px;font-weight:600}
				.mfp-result.bad{background:#fcf0f1;border-left:4px solid #d63638}
				.mfp-result.good{background:#edfaef;border-left:4px solid #00a32a}
				.mfp-log td,.mfp-log th{font-size:12.5px}
				.mfp-log td:nth-child(2){font-weight:600}
			</style>

			<form method="post" action="options.php">
				<?php settings_fields( 'mfp' ); ?>

				<div class="mfp-grid">
					<div class="mfp-card">
						<h2>Blocked keywords</h2>
						<p class="description">One word or phrase per line. Case-insensitive, whole-word match (<code>revamp</code> won't match "revamping"). Lines starting with <code>#</code> are notes.</p>
						<textarea name="<?php echo esc_attr( $opt ); ?>[keywords]" spellcheck="false"><?php echo esc_textarea( $s['keywords'] ); ?></textarea>
					</div>

					<div class="mfp-card">
						<h2>Blocked websites</h2>
						<p class="description">One per line. Blocks any message that contains this domain or link. <code>https://</code> and <code>www.</code> are ignored.</p>
						<textarea name="<?php echo esc_attr( $opt ); ?>[websites]" spellcheck="false"><?php echo esc_textarea( $s['websites'] ); ?></textarea>
					</div>

					<div class="mfp-card">
						<h2>Blocked emails</h2>
						<p class="description">One per line:<br>
							<code>name@domain.com</code> → that exact address<br>
							<code>@domain.com</code> → the whole domain<br>
							<code>seoagency</code> → any address that contains it</p>
						<textarea name="<?php echo esc_attr( $opt ); ?>[emails]" spellcheck="false"><?php echo esc_textarea( $s['emails'] ); ?></textarea>
					</div>
				</div>

				<div class="mfp-grid">
					<div class="mfp-card">
						<h2>Blocked IPs</h2>
						<p class="description">One per line. Exact IPs (<code>98.159.234.151</code>) or ranges (<code>158.173.166.0/24</code>, <code>2601:249:1881::/48</code>). Your current IP: <code><?php echo esc_html( MFP_Filter::client_ip() ); ?></code></p>
						<textarea class="mfp-short" name="<?php echo esc_attr( $opt ); ?>[blocked_ips]" spellcheck="false"><?php echo esc_textarea( $s['blocked_ips'] ); ?></textarea>
					</div>

					<div class="mfp-card">
						<h2>Suspicious links</h2>
						<p class="description">Domain extensions to block, one per line (<code>top</code> blocks <code>https://anything.top/…</code>). Email addresses inside the message are ignored.</p>
						<textarea class="mfp-short" name="<?php echo esc_attr( $opt ); ?>[blocked_tlds]" spellcheck="false"><?php echo esc_textarea( $s['blocked_tlds'] ); ?></textarea>
						<p class="mfp-opts"><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[block_all_links]" value="1" <?php checked( $s['block_all_links'] ); ?>> Block <strong>any</strong> message that contains a link (<code>http://</code>, <code>https://</code> or <code>www.</code>)</label></p>
					</div>
				</div>

				<div class="mfp-card mfp-opts" style="margin-top:20px">
					<h2>Extra protection</h2>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[block_cyrillic]" value="1" <?php checked( $s['block_cyrillic'] ); ?>> Block messages written in Cyrillic (Russian spam)</label>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[block_bot_text]" value="1" <?php checked( $s['block_bot_text'] ); ?>> Block bot garbage text (e.g. <code>NAYUYUTY2503033NEHTYHYHTR</code>)</label>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[honeypot]" value="1" <?php checked( $s['honeypot'] ); ?>> Honeypot: add a hidden field to every Elementor form that only bots fill</label>
					<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[rate_limit]" value="1" <?php checked( $s['rate_limit'] ); ?>> Rate limit: block an IP after
						<input type="number" min="1" max="100" name="<?php echo esc_attr( $opt ); ?>[rate_max]" value="<?php echo (int) $s['rate_max']; ?>"> submissions in
						<input type="number" min="1" max="1440" name="<?php echo esc_attr( $opt ); ?>[rate_window]" value="<?php echo (int) $s['rate_window']; ?>"> minutes</label>
				</div>

				<?php submit_button( 'Save changes' ); ?>
			</form>

			<hr>

			<div class="mfp-card" id="mfp-test">
				<h2>Test a message</h2>
				<p class="description">Check if a submission would be blocked with the <strong>saved</strong> settings (lists, links, IPs). Nothing is sent or logged, and it doesn't count toward the rate limit.</p>
				<form method="post" action="<?php echo esc_url( self::url() ); ?>#mfp-test">
					<?php wp_nonce_field( 'mfp_test' ); ?>
					<input type="hidden" name="mfp_test" value="1">
					<p>
						<input type="text" name="mfp_test_email" placeholder="Email" class="regular-text" value="<?php echo esc_attr( $test ? $test['email'] : '' ); ?>">
						<input type="text" name="mfp_test_phone" placeholder="Phone (optional)" class="regular-text" value="<?php echo esc_attr( $test ? $test['phone'] : '' ); ?>">
						<input type="text" name="mfp_test_ip" placeholder="IP (optional)" class="regular-text" value="<?php echo esc_attr( $test ? $test['ip'] : '' ); ?>">
					</p>
					<p><textarea name="mfp_test_message" rows="5" class="large-text" placeholder="Message"><?php echo esc_textarea( $test ? $test['message'] : '' ); ?></textarea></p>
					<?php submit_button( 'Run test', 'secondary', 'submit', false ); ?>
				</form>

				<?php if ( $test ) : ?>
					<?php if ( '' !== $test['spam'] ) : ?>
						<div class="mfp-result bad">Blocked — <?php echo esc_html( $test['spam'] ); ?></div>
					<?php else : ?>
						<div class="mfp-result good">Passes — the email would be delivered.</div>
					<?php endif; ?>
					<?php if ( '' !== $test['phone_e'] ) : ?>
						<div class="mfp-result bad">Phone rejected — <?php echo esc_html( $test['phone_e'] ); ?></div>
					<?php elseif ( '' !== $test['phone'] ) : ?>
						<div class="mfp-result good">Phone is valid.</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="mfp-card mfp-log" id="mfp-log" style="margin-top:20px">
				<h2>Blocked submissions <span class="mfp-version">(<?php echo (int) MFP_Logger::count(); ?> total, newest first)</span></h2>
				<?php if ( empty( $rows ) ) : ?>
					<p>Nothing blocked yet.</p>
				<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th>Date</th><th>Reason</th><th>Email</th><th>IP</th><th>Form</th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $r ) : ?>
							<tr>
								<td><?php echo esc_html( $r[0] ); ?></td>
								<td><?php echo esc_html( $r[1] ); ?></td>
								<td><?php echo esc_html( $r[2] ); ?></td>
								<td><?php echo esc_html( $r[3] ); ?></td>
								<td><?php echo esc_html( $r[4] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px" onsubmit="return confirm('Clear the whole log?');">
						<?php wp_nonce_field( 'mfp_clear_log' ); ?>
						<input type="hidden" name="action" value="mfp_clear_log">
						<?php submit_button( 'Clear log', 'delete', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
