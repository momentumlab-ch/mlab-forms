<?php
/**
 * Produktinformationen im WordPress-Dialog «Details anzeigen».
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Ergänzt den aktiven Update-Anbieter, ohne Version, Download oder Lizenzprüfung zu ersetzen.
 */
final class PluginDetails {

	/**
	 * Gilt sowohl für den GitHub-Checker als auch für MLab Core.
	 */
	public function register(): void {
		add_filter( 'plugins_api_result', array( $this, 'enrich' ), 20, 3 );
	}

	/**
	 * Ergänzt nur die Informationen für Forms.
	 *
	 * @param mixed  $result Antwort des Update-Anbieters.
	 * @param string $action API-Aktion.
	 * @param object $args   Anfrage mit Slug.
	 * @return mixed
	 */
	public function enrich( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action || 'mlab-forms' !== ( $args->slug ?? '' ) || ! is_object( $result ) || is_wp_error( $result ) ) {
			return $result;
		}
		$result->name             = 'MomentumLab Forms';
		$sections                 = (array) ( $result->sections ?? array() );
		$sections['description']  = self::description();
		$sections['installation'] = '<ol><li>' . esc_html__( 'Plugin aktivieren und unter Einstellungen → E-Mail Empfänger und Absender hinterlegen.', 'mlab-forms' ) . '</li><li>' . esc_html__( 'Änderungen speichern und eine Testmail senden.', 'mlab-forms' ) . '</li><li>' . esc_html__( 'Formular im Builder erstellen und mit [mlab_form id="123"] auf einer Seite einbinden.', 'mlab-forms' ) . '</li><li>' . esc_html__( 'Neue Anfragen unter Anfragen prüfen, bearbeiten und bei Bedarf erneut zustellen.', 'mlab-forms' ) . '</li></ol>';
		$sections['faq']          = '<h3>' . esc_html__( 'An wen geht Resend?', 'mlab-forms' ) . '</h3><p>' . esc_html__( 'Ausschliesslich an die aktuell hinterlegte Zustelladresse des Formulars. Ohne eigene Zustelladresse gelten die Standard-Empfänger. Die vom Kunden eingegebene Adresse erhält beim Neuversand keine weitere Bestätigung.', 'mlab-forms' ) . '</p><h3>' . esc_html__( 'Wo bleiben die Daten?', 'mlab-forms' ) . '</h3><p>' . esc_html__( 'Anfragen werden in der eigenen WordPress-Datenbank gespeichert. Aufbewahrung und Löschung sind einstellbar. Optionale Spam-Dienste können Daten an Dritte übertragen und müssen passend zur Datenschutzerklärung eingerichtet werden.', 'mlab-forms' ) . '</p><h3>' . esc_html__( 'Wie kommen Updates?', 'mlab-forms' ) . '</h3><p>' . esc_html__( 'Verwaltet MLab Core dieses Plugin, liefert Core die Updates über den Lizenzserver. Sonst übernimmt der direkte GitHub-Update-Checker. Automatische Updates lassen sich in WordPress ein- und ausschalten.', 'mlab-forms' ) . '</p>';
		$screenshots              = self::screenshots();
		if ( '' !== $screenshots ) {
			$sections['screenshots'] = $screenshots;
		}
		$result->sections = $sections;
		return $result;
	}

	/**
	 * Beschreibung der tatsächlich verfügbaren Funktionen.
	 */
	private static function description(): string {
		return '<h2>MomentumLab Forms</h2><p>' . esc_html__( 'Kontakt-, Event- und Angebotsanfragen übersichtlich verwalten – vom Formular bis zur Bearbeitung im Team.', 'mlab-forms' ) . '</p><ul><li>' . esc_html__( 'Formular-Builder mit Vorlagen, Pflichtfeldern, Auswahllisten, Uploads und Vorschau.', 'mlab-forms' ) . '</li><li>' . esc_html__( 'Anfragen mit Status, Suche, Formularfilter, Detailvorschau, internen Notizen und CSV-Export.', 'mlab-forms' ) . '</li><li>' . esc_html__( 'Resend direkt in der Liste: gespeicherte Anfrage inklusive verfügbarer Anhänge erneut an die Zustelladresse senden. Das Ergebnis bleibt im Verlauf.', 'mlab-forms' ) . '</li><li>' . esc_html__( 'Empfänger, Absender und Antwort-Adresse klar getrennt; Versandvorschau und Testmail in den Einstellungen.', 'mlab-forms' ) . '</li><li>' . esc_html__( 'Mehrsprachige Formulare, Spam-Schutz, konfigurierbare Aufbewahrung und WordPress-Datenschutzwerkzeuge.', 'mlab-forms' ) . '</li><li>' . esc_html__( 'Responsives Backend mit lokal eingebundener Montserrat-Schrift und ohne externe Schriftanfragen.', 'mlab-forms' ) . '</li></ul><p>' . esc_html__( 'Der Versand verwendet wp_mail und lässt sich mit einem SMTP-Plugin kombinieren. Eine erfolgreiche Übergabe an den Mailversand ist noch keine bestätigte Zustellung im Posteingang.', 'mlab-forms' ) . '</p>';
	}

	/**
	 * Echte Screenshots aus der reproduzierbaren WordPress-Demoinstallation.
	 */
	private static function screenshots(): string {
		$images = array(
			'anfragen.png'            => __( 'Anfragen mit Status, Suche und direkter Resend-Aktion.', 'mlab-forms' ),
			'anfragen-vorschau.png'   => __( 'Seitliche Vorschau mit den Angaben und der Zustelladresse.', 'mlab-forms' ),
			'anfrage-detail.png'      => __( 'Detailansicht mit Status, internen Notizen und Verlauf.', 'mlab-forms' ),
			'einstellungen-email.png' => __( 'E-Mail-Einstellungen: Empfänger, Absender und Testversand.', 'mlab-forms' ),
			'anfragen-mobil.png'      => __( 'Anfragen auf einem schmalen Bildschirm.', 'mlab-forms' ),
		);
		$html   = '<p>' . esc_html__( 'Aufnahmen einer WordPress-Testinstallation. Alle Namen, Adressen und Anfragen sind fiktive Demodaten.', 'mlab-forms' ) . '</p>';
		$count  = 0;
		foreach ( $images as $file => $caption ) {
			if ( ! is_file( MLAB_FORMS_DIR . 'assets/screenshots/' . $file ) ) {
				continue;
			}
			++$count;
			$html .= '<figure style="margin:24px 0"><img style="max-width:100%;height:auto;border:1px solid #e5e6e8" loading="lazy" src="' . esc_url( plugins_url( 'assets/screenshots/' . $file, MLAB_FORMS_FILE ) ) . '" alt="' . esc_attr( $caption ) . '"><figcaption>' . esc_html( $caption ) . '</figcaption></figure>';
		}
		return $count ? $html : '';
	}
}
