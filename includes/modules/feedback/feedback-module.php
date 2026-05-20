<?php

namespace Zaplane\Modules\Feedback;

use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Feedback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeedbackModule implements ModuleInterface {

	protected static ?self $instance = null;

	public static function init( Container $container ): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks(): void {
		add_shortcode( 'zaplane_feedback', [ $this, 'render_shortcode' ] );
		add_action( 'admin_init', [ $this, 'ensure_page_exists' ] );
	}

	public function ensure_page_exists(): void {
		\Zaplane\Installer::init()->create_feedback_page();
	}

	public function render_shortcode( array $atts ): string {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $order_id || ! $key ) {
			return $this->render_error( 'Invalid feedback link. Please check the link in your email.' );
		}

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return $this->render_error( 'Order not found.' );
		}

		if ( ! hash_equals( $order->get_order_key(), $key ) ) {
			return $this->render_error( 'Invalid feedback link.' );
		}

		if ( Feedback::for_order( $order_id ) ) {
			return $this->render_thankyou( 'Your feedback has already been received. Thank you!' );
		}

		$endpoint = esc_url( rest_url( 'zaplane/v1/feedback' ) );
		$nonce    = wp_create_nonce( 'wp_rest' );

		ob_start();
		?>
		<div class="zpf-wrap" id="zpf-wrap">
			<style>
				.zpf-wrap {
					max-width: 520px;
					margin: 40px auto;
					font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
					color: #1a1a2e;
				}
				.zpf-card {
					background: #fff;
					border-radius: 16px;
					box-shadow: 0 4px 32px rgba(0,0,0,.08);
					padding: 40px 36px;
					text-align: center;
				}
				.zpf-icon {
					font-size: 48px;
					margin-bottom: 12px;
				}
				.zpf-title {
					font-size: 22px;
					font-weight: 700;
					margin: 0 0 6px;
				}
				.zpf-subtitle {
					font-size: 14px;
					color: #666;
					margin: 0 0 28px;
				}
				.zpf-stars {
					display: flex;
					justify-content: center;
					gap: 8px;
					margin-bottom: 28px;
					flex-direction: row-reverse;
				}
				.zpf-stars input[type="radio"] {
					display: none;
				}
				.zpf-stars label {
					font-size: 40px;
					color: #ddd;
					cursor: pointer;
					transition: color .15s, transform .15s;
					line-height: 1;
				}
				.zpf-stars label:hover,
				.zpf-stars label:hover ~ label,
				.zpf-stars input:checked ~ label {
					color: #f5a623;
				}
				.zpf-stars label:hover {
					transform: scale(1.2);
				}
				.zpf-label {
					display: block;
					text-align: left;
					font-size: 13px;
					font-weight: 600;
					color: #444;
					margin-bottom: 6px;
				}
				.zpf-textarea {
					width: 100%;
					border: 1.5px solid #e2e8f0;
					border-radius: 10px;
					padding: 12px 14px;
					font-size: 14px;
					font-family: inherit;
					resize: vertical;
					min-height: 100px;
					box-sizing: border-box;
					transition: border-color .2s;
					outline: none;
					color: #1a1a2e;
				}
				.zpf-textarea:focus {
					border-color: #6c63ff;
				}
				.zpf-btn {
					display: inline-block;
					margin-top: 20px;
					padding: 14px 40px;
					background: #6c63ff;
					color: #fff;
					border: none;
					border-radius: 10px;
					font-size: 15px;
					font-weight: 600;
					cursor: pointer;
					transition: background .2s, transform .1s;
					width: 100%;
				}
				.zpf-btn:hover { background: #574fd6; }
				.zpf-btn:active { transform: scale(.98); }
				.zpf-btn:disabled { background: #a8a4e6; cursor: not-allowed; }
				.zpf-error-msg {
					color: #e53e3e;
					font-size: 13px;
					margin-top: 10px;
					display: none;
				}
				.zpf-rating-hint {
					font-size: 13px;
					color: #888;
					min-height: 20px;
					margin-bottom: 16px;
					transition: color .15s;
				}
				@media (max-width: 560px) {
					.zpf-card { padding: 28px 18px; }
					.zpf-stars label { font-size: 32px; }
				}
			</style>

			<div class="zpf-card">
				<div class="zpf-icon">⭐</div>
				<h2 class="zpf-title">How was your experience?</h2>
				<p class="zpf-subtitle">Order #<?php echo esc_html( $order->get_order_number() ); ?> &mdash; Your feedback means a lot to us.</p>

				<form id="zpf-form" novalidate>
					<div class="zpf-stars">
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<input type="radio" name="rating" id="zpf-star-<?php echo $i; ?>" value="<?php echo $i; ?>">
							<label for="zpf-star-<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">&#9733;</label>
						<?php endfor; ?>
					</div>
					<p class="zpf-rating-hint" id="zpf-rating-hint">&nbsp;</p>

					<label class="zpf-label" for="zpf-comment">Leave a comment <span style="font-weight:400;color:#999">(optional)</span></label>
					<textarea class="zpf-textarea" id="zpf-comment" name="comment" placeholder="Tell us what you think..."></textarea>

					<button type="submit" class="zpf-btn" id="zpf-submit">Submit Feedback</button>
					<p class="zpf-error-msg" id="zpf-error"></p>
				</form>
			</div>
		</div>

		<script>
		(function () {
			var hints = ['', 'Very bad', 'Could be better', 'It was OK', 'Good experience', 'Excellent!'];
			var form     = document.getElementById('zpf-form');
			var hint     = document.getElementById('zpf-rating-hint');
			var errorEl  = document.getElementById('zpf-error');
			var btn      = document.getElementById('zpf-submit');
			var wrap     = document.getElementById('zpf-wrap');

			document.querySelectorAll('input[name="rating"]').forEach(function (radio) {
				radio.addEventListener('change', function () {
					hint.textContent = hints[parseInt(this.value, 10)] || '';
				});
			});

			form.addEventListener('submit', function (e) {
				e.preventDefault();

				var rating = form.querySelector('input[name="rating"]:checked');
				if (!rating) {
					showError('Please select a star rating.');
					return;
				}

				btn.disabled = true;
				btn.textContent = 'Submitting…';
				errorEl.style.display = 'none';

				fetch(<?php echo wp_json_encode( $endpoint ); ?>, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': <?php echo wp_json_encode( $nonce ); ?>,
					},
					body: JSON.stringify({
						order_id: <?php echo (int) $order_id; ?>,
						key:      <?php echo wp_json_encode( $key ); ?>,
						rating:   parseInt(rating.value, 10),
						comment:  document.getElementById('zpf-comment').value,
					}),
				})
				.then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
				.then(function (res) {
					if (res.ok && res.data.success) {
						wrap.innerHTML = <?php echo wp_json_encode( $this->render_thankyou() ); ?>;
					} else {
						showError(res.data.message || 'Something went wrong. Please try again.');
						btn.disabled = false;
						btn.textContent = 'Submit Feedback';
					}
				})
				.catch(function () {
					showError('Network error. Please check your connection and try again.');
					btn.disabled = false;
					btn.textContent = 'Submit Feedback';
				});
			});

			function showError(msg) {
				errorEl.textContent = msg;
				errorEl.style.display = 'block';
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	private function render_thankyou( string $message = 'Thank you for your feedback! We really appreciate it.' ): string {
		return '
		<div class="zpf-wrap">
			<style>
				.zpf-wrap { max-width:520px; margin:40px auto; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; text-align:center; }
				.zpf-card { background:#fff; border-radius:16px; box-shadow:0 4px 32px rgba(0,0,0,.08); padding:48px 36px; }
				.zpf-ty-icon { font-size:56px; margin-bottom:16px; }
				.zpf-ty-title { font-size:22px; font-weight:700; color:#1a1a2e; margin:0 0 10px; }
				.zpf-ty-msg { font-size:15px; color:#555; margin:0; }
			</style>
			<div class="zpf-card">
				<div class="zpf-ty-icon">🎉</div>
				<h2 class="zpf-ty-title">Thank you!</h2>
				<p class="zpf-ty-msg">' . esc_html( $message ) . '</p>
			</div>
		</div>';
	}

	private function render_error( string $message ): string {
		return '
		<div class="zpf-wrap">
			<style>
				.zpf-wrap { max-width:520px; margin:40px auto; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; text-align:center; }
				.zpf-card { background:#fff; border-radius:16px; box-shadow:0 4px 32px rgba(0,0,0,.08); padding:48px 36px; }
				.zpf-err-icon { font-size:48px; margin-bottom:16px; }
				.zpf-err-title { font-size:20px; font-weight:700; color:#e53e3e; margin:0 0 10px; }
				.zpf-err-msg { font-size:14px; color:#555; margin:0; }
			</style>
			<div class="zpf-card">
				<div class="zpf-err-icon">⚠️</div>
				<h2 class="zpf-err-title">Oops!</h2>
				<p class="zpf-err-msg">' . esc_html( $message ) . '</p>
			</div>
		</div>';
	}
}
