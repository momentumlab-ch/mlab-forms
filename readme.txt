=== MomentumLab Forms ===
Contributors: momentumlab
Tags: formulare, kontakt, anfrage
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Anfrage- und Kontaktformulare für Kundenseiten von Momentum Lab.

== Description ==

MomentumLab Forms hat einen eigenen Menüpunkt im Backend mit «Alle Formulare», «Neues Formular», «Anfragen» und «Einstellungen» (Tabs Allgemein, E-Mail, Spam-Schutz, Datenschutz, Updates).

* Formulare als JSON-Schema mit Vorlagen (Kontakt, Anfrage, Gutschein), Prüfung beim Speichern und Revisionen.
* Einbinden mit `[mlab_form id="123"]` oder `[mlab_form slug="kontakt"]`. Funktioniert auch ohne JavaScript und mit Seiten-Cache.
* Anfragen mit Status, Notizen und CSV-Export; Benachrichtigungen mit Platzhaltern wie `{field:email}` und `{all_fields}`.
* Spam-Schutz mit Honeypot, Mindestzeit und Limit pro IP (gehasht gespeichert). Optional Cloudflare Turnstile, Google reCAPTCHA v3, hCaptcha (nur einer pro Formular) und Akismet; die Schlüssel liegen nur in den Einstellungen.
* Absender (Name, E-Mail, «Antwort an») und Aufbewahrung pro Formular einstellbar.
* Datenschutz: Aufbewahrungsdauer, Export und Löschung über die WordPress-Datenschutz-Werkzeuge.

= Hooks =

Filter:

* `mlab_forms_field_types`: eigene Feldtypen ergänzen oder bestehende ersetzen (Typ => Klasse).
* `mlab_forms_options_providers`: Quellen für Auswahllisten ergänzen (`options_source` im Formular).
* `mlab_forms_style_vars`: CSS-Variablen der Formulare setzen, z. B. `--mlab-form-accent` aus den Theme-Farben.
* `mlab_forms_client_ip`: IP-Adresse hinter einem Proxy oder CDN korrekt bestimmen.
* `mlab_forms_recaptcha_threshold`: Mindest-Score für Google reCAPTCHA v3 (Standard 0.5).

Actions:

* `mlab_forms_entry_created`: nach dem Speichern einer neuen Anfrage, mit Anfrage-ID und Eingaben.
* `mlab_forms_settings_tab_{tab}`: eigener Inhalt unter dem Formular eines Einstellungs-Tabs.

= Updates =

Updates kommen aus den GitHub-Releases von momentumlab-ch/mlab-forms und erscheinen wie jedes andere Plugin-Update unter «Dashboard › Aktualisierungen» und in der Plugin-Liste. Automatische Updates schaltest du wie bei jedem Plugin mit «Automatische Updates aktivieren» in der Plugin-Liste ein oder aus (Standard: aus). Der Tab «Updates» zeigt die Versionen und denselben Schalter.

Das Repository ist öffentlich, ein Token ist nicht nötig. Wird es privat, kommt der Token nur als Konstante `MLAB_FORMS_GITHUB_TOKEN` in die `wp-config.php` der Website, nie in den Code.

== Installation ==

1. Das ZIP `mlab-forms.zip` aus dem neusten GitHub-Release herunterladen.
2. In WordPress unter «Plugins › Installieren › Plugin hochladen» das ZIP auswählen und installieren.
3. Plugin aktivieren.
4. Optional in der Plugin-Liste «Automatische Updates aktivieren» wählen.

== Neue Version veröffentlichen ==

1. Version an drei Stellen erhöhen: Plugin-Header und Konstante in `mlab-forms.php`, «Stable tag» in `readme.txt`. Eintrag in `CHANGELOG.md` und hier im Changelog ergänzen.
2. Auf main mergen. Der Workflow «Release» legt das Release `vX.Y.Z` mit `mlab-forms.zip` an, sobald es zur Version noch keines gibt. Ab dann sehen die Websites das Update.

== Changelog ==

= 0.5.0 =
* Absender einstellbar: Name, E-Mail-Adresse und «Antwort an» (E-Mail des Besuchers oder feste Adresse), global und pro Formular. Standard sind Seitenname und Admin-Adresse.
* Aufbewahrung pro Formular wählbar: 30 Tage, 90 Tage, 1 Jahr, 2 Jahre, unbegrenzt oder eigene Anzahl Tage.
* Spam-Schutz zuschaltbar: Cloudflare Turnstile, Google reCAPTCHA v3 oder hCaptcha (nur einer gleichzeitig) und Akismet. Mit «Verbindung testen» auf der Einstellungsseite. Standardmässig ist alles aus; der Datenschutz-Textbaustein nennt nur eingeschaltete Dienste.
* Telefonnummern und Schweizer Postleitzahlen werden geprüft, mit verständlichen Meldungen.
* Auswahllisten: eine Zeile als Standard vorauswählen, leere erste Zeile «Bitte wählen», Vorauswahl per Link pro Feld abschaltbar.

= 0.4.1 =
* Interne Aufräumarbeiten nach den MomentumLab-Standards. Für Websites ändert sich nichts.
* Die readme beschreibt jetzt alle Filter und Actions.

= 0.4.0 =
* Updates laufen über den Plugin Update Checker; das Release-ZIP heisst jetzt immer `mlab-forms.zip`.
* Das Plugin bringt alles mit, was es braucht, und läuft auch aus einem frisch heruntergeladenen ZIP.
* Lizenz ist jetzt GPL-2.0 oder später.
* Übersetzungsvorlage und deutsche Übersetzung (Schweiz) liegen bei.

= 0.3.0 =
* Formulare mit Feldtypen, Vorlagen, Revisionen und Shortcode.
* Anfragen im Backend mit Status, Notizen und CSV-Export.
* E-Mail-Benachrichtigungen, Spam-Schutz, Aufbewahrung und Datenschutz-Werkzeuge.
* Automatische Updates laufen über den WordPress-eigenen Schalter; ein früher gesetzter Plugin-Schalter wird übernommen.
* Prüfungen mit PHPCS, PHPStan und PHPUnit in der Pipeline.

= 0.2.0 =
* Eigener Menüpunkt «MomentumLab Forms» mit Einstellungen in Tabs.
* Updates aus den GitHub-Releases, mit Knopf «Jetzt nach Updates suchen».

= 0.1.0 =
* Gerüst mit Einstellungsseite und Schalter für automatische Updates.
