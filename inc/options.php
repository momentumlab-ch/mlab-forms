<?php
/**
 * Alle Einstellungen an einem Ort: Schlüssel, Tab, Typ, Bezeichnung und Standardwert.
 *
 * Settings, die Einstellungsseite und die Bereinigung lesen nur von hier.
 *
 * @package MLab_Forms
 */

defined( 'ABSPATH' ) || exit;

return array(
	'load_css'               => array(
		'tab'         => 'general',
		'type'        => 'checkbox',
		'label'       => __( 'Formular-Styling laden', 'mlab-forms' ),
		'default'     => true,
		'description' => __( 'Ausschalten, wenn das Theme die Formulare selbst gestaltet.', 'mlab-forms' ),
	),
	'style_preset'           => array(
		'tab'     => 'general',
		'type'    => 'select',
		'label'   => __( 'Farbschema', 'mlab-forms' ),
		'default' => 'light',
		'options' => array(
			'light' => __( 'Hell', 'mlab-forms' ),
			'dark'  => __( 'Dunkel', 'mlab-forms' ),
		),
	),
	'email_from_name'        => array(
		'tab'         => 'email',
		'type'        => 'text',
		'label'       => __( 'Absendername', 'mlab-forms' ),
		'default'     => '',
		'description' => __( 'Leer lassen für den Namen der Website.', 'mlab-forms' ),
	),
	'email_from_address'     => array(
		'tab'         => 'email',
		'type'        => 'email',
		'label'       => __( 'Absenderadresse', 'mlab-forms' ),
		'default'     => '',
		'description' => __( 'Leer lassen für die Admin-Adresse. WP Mail SMTP kann diesen Wert übersteuern.', 'mlab-forms' ),
	),
	'email_admin_to'         => array(
		'tab'         => 'email',
		'type'        => 'email',
		'label'       => __( 'Standard-Empfänger', 'mlab-forms' ),
		'default'     => '',
		'description' => __( 'Empfänger für neue Anfragen, wenn das Formular nichts anderes festlegt. Leer lassen für die Admin-Adresse.', 'mlab-forms' ),
	),
	'spam_honeypot'          => array(
		'tab'     => 'spam',
		'type'    => 'checkbox',
		'label'   => __( 'Honeypot verwenden', 'mlab-forms' ),
		'default' => true,
	),
	'spam_min_seconds'       => array(
		'tab'         => 'spam',
		'type'        => 'number',
		'label'       => __( 'Mindestzeit zum Ausfüllen (Sekunden)', 'mlab-forms' ),
		'default'     => 3,
		'min'         => 0,
		'max'         => 60,
		'description' => __( 'Schnellere Absendungen gelten als Spam. 0 schaltet die Prüfung aus.', 'mlab-forms' ),
	),
	'spam_rate_limit'        => array(
		'tab'         => 'spam',
		'type'        => 'number',
		'label'       => __( 'Maximal Anfragen pro Stunde und Absender', 'mlab-forms' ),
		'default'     => 5,
		'min'         => 0,
		'max'         => 100,
		'description' => __( 'Gezählt wird pro gehashter IP-Adresse. 0 schaltet die Begrenzung aus.', 'mlab-forms' ),
	),
	'retention_days'         => array(
		'tab'         => 'privacy',
		'type'        => 'number',
		'label'       => __( 'Anfragen aufbewahren (Tage)', 'mlab-forms' ),
		'default'     => 365,
		'min'         => 1,
		'max'         => 3650,
		'description' => __( 'Standard für neue Formulare. Ältere Anfragen werden automatisch gelöscht.', 'mlab-forms' ),
	),
	'keep_data_on_uninstall' => array(
		'tab'         => 'privacy',
		'type'        => 'checkbox',
		'label'       => __( 'Daten beim Löschen des Plugins behalten', 'mlab-forms' ),
		'default'     => true,
		'description' => __( 'Ausschalten, damit beim Löschen des Plugins alle Formulare, Anfragen und Einstellungen entfernt werden.', 'mlab-forms' ),
	),
);
