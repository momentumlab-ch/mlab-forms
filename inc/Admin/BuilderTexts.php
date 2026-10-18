<?php
/**
 * Texte des Builders.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Alle Texte, die das Builder-Skript anzeigt, übersetzbar im PHP.
 */
final class BuilderTexts {

	/**
	 * Schlüssel => Text.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array_merge( self::areas(), self::fields(), self::properties(), self::choices(), self::settings(), self::messages() );
	}

	/**
	 * Reiter, Palette und Werkzeugleiste.
	 *
	 * @return array<string, string>
	 */
	private static function areas(): array {
		return array(
			'area'             => __( 'Bereich', 'mlab-forms' ),
			'tabFields'        => __( 'Felder', 'mlab-forms' ),
			'tabSettings'      => __( 'Einstellungen', 'mlab-forms' ),
			'tabPreview'       => __( 'Vorschau', 'mlab-forms' ),
			'loadTemplate'     => __( 'Vorlage laden …', 'mlab-forms' ),
			'confirmTemplate'  => __( 'Das aktuelle Formular durch die Vorlage ersetzen?', 'mlab-forms' ),
			'templateLoaded'   => __( 'Vorlage geladen', 'mlab-forms' ),
			'expert'           => __( 'Experten-Ansicht (JSON)', 'mlab-forms' ),
			'backToBuilder'    => __( 'Zurück zum Builder', 'mlab-forms' ),
			'palette'          => __( 'Bausteine', 'mlab-forms' ),
			'customField'      => __( 'Eigenes Feld', 'mlab-forms' ),
			'customFieldHint'  => __( 'Art selbst wählen', 'mlab-forms' ),
			'customFieldIntro' => __( 'Wähle, welche Art von Eingabe du brauchst.', 'mlab-forms' ),
			'cancel'           => __( 'Abbrechen', 'mlab-forms' ),
			'formArea'         => __( 'Formular', 'mlab-forms' ),
			'saveNote'         => __( 'Deine Änderungen gelten erst, wenn du das Formular speicherst oder aktualisierst.', 'mlab-forms' ),
		);
	}

	/**
	 * Felder in der Formular-Ansicht.
	 *
	 * @return array<string, string>
	 */
	private static function fields(): array {
		return array(
			'emptyForm'    => __( 'Das Formular ist noch leer. Wähle links einen Baustein.', 'mlab-forms' ),
			'field'        => __( 'Feld', 'mlab-forms' ),
			'moveUp'       => __( 'Nach oben', 'mlab-forms' ),
			'moveDown'     => __( 'Nach unten', 'mlab-forms' ),
			'duplicate'    => __( 'Duplizieren', 'mlab-forms' ),
			'remove'       => __( 'Entfernen', 'mlab-forms' ),
			'pleaseChoose' => __( 'Bitte wählen', 'mlab-forms' ),
			'copySuffix'   => __( ' (Kopie)', 'mlab-forms' ),
			/* translators: %s: Name des Felds */
			'added'        => __( 'Feld «%s» hinzugefügt', 'mlab-forms' ),
			/* translators: 1: Name des Felds, 2: Position */
			'moved'        => __( 'Feld «%1$s» steht jetzt an Position %2$d', 'mlab-forms' ),
			/* translators: %s: Name des Felds */
			'duplicated'   => __( 'Feld «%s» dupliziert', 'mlab-forms' ),
			/* translators: %s: Name des Felds */
			'removed'      => __( 'Feld «%s» entfernt', 'mlab-forms' ),
			'undo'         => __( 'Rückgängig', 'mlab-forms' ),
			'undone'       => __( 'Rückgängig gemacht', 'mlab-forms' ),
			/* translators: %s: Name des Eintrags */
			'entryRemoved' => __( 'Eintrag «%s» entfernt', 'mlab-forms' ),
			'usedInMail'   => __( 'Dieses Feld wird in einer Benachrichtigung verwendet. Beim Löschen kommt die Mail nicht mehr richtig an.', 'mlab-forms' ),
			'removeAnyway' => __( 'Trotzdem löschen', 'mlab-forms' ),
			'noFields'     => __( 'Das Formular braucht mindestens ein Feld.', 'mlab-forms' ),
			'labelMissing' => __( 'Die Beschriftung fehlt.', 'mlab-forms' ),
			/* translators: %s: Name des Felds */
			'fieldPrefix'  => __( 'Feld «%s»:', 'mlab-forms' ),
		);
	}

	/**
	 * Eigenschaften des gewählten Felds.
	 *
	 * @return array<string, string>
	 */
	private static function properties(): array {
		return array(
			'properties'      => __( 'Eigenschaften', 'mlab-forms' ),
			'selectField'     => __( 'Wähle ein Feld aus, um es zu bearbeiten.', 'mlab-forms' ),
			'done'            => __( 'Fertig', 'mlab-forms' ),
			'label'           => __( 'Beschriftung', 'mlab-forms' ),
			'titleText'       => __( 'Titel', 'mlab-forms' ),
			'placeholder'     => __( 'Platzhalter', 'mlab-forms' ),
			'emptyLabel'      => __( 'Text der leeren ersten Zeile', 'mlab-forms' ),
			'help'            => __( 'Hilfetext', 'mlab-forms' ),
			'htmlContent'     => __( 'Inhalt (HTML)', 'mlab-forms' ),
			'defaultValue'    => __( 'Standardwert', 'mlab-forms' ),
			'required'        => __( 'Pflichtfeld', 'mlab-forms' ),
			'requiredHint'    => __( 'Muss vor dem Senden ausgefüllt sein', 'mlab-forms' ),
			'width'           => __( 'Breite', 'mlab-forms' ),
			'widthFull'       => __( 'Voll', 'mlab-forms' ),
			'widthHalf'       => __( 'Halb', 'mlab-forms' ),
			'widthThird'      => __( 'Drittel', 'mlab-forms' ),
			'format'          => __( 'Prüfung', 'mlab-forms' ),
			'formatNone'      => __( 'Keine besondere Prüfung', 'mlab-forms' ),
			'formatPostcode'  => __( 'Schweizer Postleitzahl', 'mlab-forms' ),
			'min'             => __( 'Kleinster Wert', 'mlab-forms' ),
			'max'             => __( 'Grösster Wert', 'mlab-forms' ),
			'futureOnly'      => __( 'Nur Daten ab heute', 'mlab-forms' ),
			'futureOnlyHint'  => __( 'Frühere Daten werden abgelehnt', 'mlab-forms' ),
			'linkPrivacy'     => __( 'Mit der Datenschutzerklärung verlinken', 'mlab-forms' ),
			'linkPrivacyHint' => __( 'Der Text wird zum Link auf deine Datenschutzseite.', 'mlab-forms' ),
			'autoChecked'     => __( 'Wird automatisch geprüft:', 'mlab-forms' ),
			'checkEmail'      => __( 'gültige E-Mail-Adresse', 'mlab-forms' ),
			'checkTel'        => __( 'gültige Telefonnummer', 'mlab-forms' ),
			'checkPostcode'   => __( 'gültige Postleitzahl', 'mlab-forms' ),
			'checkDate'       => __( 'gültiges Datum', 'mlab-forms' ),
			'checkNumber'     => __( 'nur Zahlen', 'mlab-forms' ),
		);
	}

	/**
	 * Einträge von Auswahllisten.
	 *
	 * @return array<string, string>
	 */
	private static function choices(): array {
		return array(
			'entries'          => __( 'Einträge', 'mlab-forms' ),
			'entry'            => __( 'Eintrag', 'mlab-forms' ),
			'addEntry'         => __( 'Eintrag hinzufügen', 'mlab-forms' ),
			'newEntry'         => __( 'Neuer Eintrag', 'mlab-forms' ),
			'removeEntry'      => __( 'Eintrag entfernen', 'mlab-forms' ),
			'makeDefault'      => __( 'Als Standard festlegen', 'mlab-forms' ),
			'defaultHint'      => __( 'Der Stern markiert den Standard-Eintrag. Es gibt höchstens einen.', 'mlab-forms' ),
			'option1'          => __( 'Option 1', 'mlab-forms' ),
			'option2'          => __( 'Option 2', 'mlab-forms' ),
			'optionEmpty'      => __( 'Ein Eintrag ist leer.', 'mlab-forms' ),
			'optionDuplicate'  => __( 'Zwei Einträge heissen gleich.', 'mlab-forms' ),
			'multipleDefaults' => __( 'Es darf nur ein Eintrag der Standard sein.', 'mlab-forms' ),
			'optionsMissing'   => __( 'Es braucht mindestens einen Eintrag.', 'mlab-forms' ),
			'emptyFirst'       => __( 'Leere erste Zeile anzeigen', 'mlab-forms' ),
			'emptyFirstHint'   => __( 'Zeigt «Bitte wählen» zuoberst.', 'mlab-forms' ),
			'prefill'          => __( 'Vorauswahl per Link erlauben', 'mlab-forms' ),
			/* translators: %s: Name des URL-Parameters */
			'prefillHint'      => __( 'Ein Link wie …/kontakt/?%s=Eintrag wählt den Eintrag vor. Ausgeschaltet, gelten nur die Einträge oben.', 'mlab-forms' ),
			'prefillDev'       => __( 'Für Entwickler: Filter mlab_forms_options_providers', 'mlab-forms' ),
		);
	}

	/**
	 * Reiter «Einstellungen».
	 *
	 * @return array<string, string>
	 */
	private static function settings(): array {
		return array(
			'afterSubmit'    => __( 'Nach dem Absenden', 'mlab-forms' ),
			'submitLabel'    => __( 'Text des Absende-Buttons', 'mlab-forms' ),
			'successMessage' => __( 'Erfolgsmeldung', 'mlab-forms' ),
			'thanksPage'     => __( 'Danke-Seite (Adresse)', 'mlab-forms' ),
			'thanksPageHint' => __( 'Optional. Wenn du eine Adresse einträgst, geht es nach dem Absenden dort weiter.', 'mlab-forms' ),
			'retention'      => __( 'Aufbewahrung', 'mlab-forms' ),
			'retentionHint'  => __( 'Wie lange gesendete Anfragen gespeichert bleiben, stellst du im Kasten «Aufbewahrung» rechts ein.', 'mlab-forms' ),
		);
	}

	/**
	 * Vorschau und Experten-Ansicht.
	 *
	 * @return array<string, string>
	 */
	private static function messages(): array {
		return array(
			'previewTitle'    => __( 'Vorschau des Formulars', 'mlab-forms' ),
			'previewSize'     => __( 'Grösse der Vorschau', 'mlab-forms' ),
			'previewNote'     => __( 'So sieht das Formular auf deiner Seite aus. Absenden ist hier ausgeschaltet.', 'mlab-forms' ),
			'previewLoading'  => __( 'Vorschau wird geladen …', 'mlab-forms' ),
			'previewInvalid'  => __( 'Die Vorschau ist noch nicht möglich. Bitte korrigiere:', 'mlab-forms' ),
			'previewFailed'   => __( 'Die Vorschau konnte nicht geladen werden. Bitte versuche es nochmals.', 'mlab-forms' ),
			'phone'           => __( 'Handy', 'mlab-forms' ),
			'computer'        => __( 'Computer', 'mlab-forms' ),
			/* translators: %s: Fehlermeldung des JSON-Parsers */
			'jsonInvalid'     => __( 'Kein gültiges JSON: %s', 'mlab-forms' ),
			'jsonNotObject'   => __( 'Das Schema muss ein JSON-Objekt sein.', 'mlab-forms' ),
			'jsonNeedsFields' => __( 'Das Schema braucht eine Liste «fields».', 'mlab-forms' ),
		);
	}
}
