<?php
/**
 * Captcha-Widget im Formular.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Spam;

use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\Language;
use MLab_Forms\Html;
use MLab_Forms\Submission\SpamGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Gibt den Container des gewählten Anbieters aus. Das Skript des Anbieters lädt Assets::enqueue_captcha().
 */
final class Widget {

	/**
	 * Attribute am form-Element für form.js.
	 *
	 * @param Form $form Formular.
	 * @return array<string, string|null>
	 */
	public static function form_attributes( Form $form ): array {
		$provider = SpamGuard::provider( $form );

		return array(
			'data-captcha'     => '' === $provider ? null : $provider,
			'data-captcha-key' => Services::RECAPTCHA === $provider ? Services::site_key( $provider ) : null,
		);
	}

	/**
	 * Container des Widgets samt Hinweis ohne JavaScript; leer, wenn das Formular keinen Anbieter nutzt.
	 *
	 * @param Form $form Formular.
	 */
	public static function html( Form $form ): string {
		$provider = SpamGuard::provider( $form );
		if ( '' === $provider ) {
			return '';
		}

		$field    = Services::captchas()[ $provider ]['field'];
		$site_key = Services::site_key( $provider );
		$lang     = Language::captcha();
		$inner    = match ( $provider ) {
			Services::TURNSTILE => Html::tag(
				'div',
				array(
					'class'         => 'cf-turnstile',
					'data-sitekey'  => $site_key,
					'data-language' => '' === $lang ? null : $lang,
				),
				''
			),
			Services::HCAPTCHA  => Html::tag(
				'div',
				array(
					'class'        => 'h-captcha',
					'data-sitekey' => $site_key,
				),
				''
			),
			default             => Html::tag(
				'input',
				array(
					'type'  => 'hidden',
					'name'  => $field,
					'value' => '',
				),
				null
			),
		};

		return Html::tag(
			'div',
			array( 'class' => 'mlab-form__captcha' ),
			$inner . '<noscript>' . Html::tag( 'p', array( 'class' => 'mlab-form__help' ), esc_html__( 'Für den Spam-Schutz braucht dieses Formular JavaScript.', 'mlab-forms' ) ) . '</noscript>'
		);
	}
}
