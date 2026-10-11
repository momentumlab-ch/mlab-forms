=== MomentumLab Forms ===
Contributors: momentumlab
Tags: formulare, kontakt, anfrage
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.3.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Anfrage- und Kontaktformulare für Kundenseiten von Momentum Lab.

== Description ==

MomentumLab Forms hat einen eigenen Menüpunkt im Backend mit «Alle Formulare», «Neues Formular», «Anfragen» und «Einstellungen» (Tabs Allgemein, E-Mail, Spam-Schutz, Datenschutz, Updates).

* Formulare als JSON-Schema mit Vorlagen (Kontakt, Anfrage, Gutschein), Prüfung beim Speichern und Revisionen.
* Einbinden mit `[mlab_form id="123"]` oder `[mlab_form slug="kontakt"]`. Funktioniert auch ohne JavaScript und mit Seiten-Cache.
* Anfragen mit Status, Notizen und CSV-Export; Benachrichtigungen mit Platzhaltern wie `{field:email}` und `{all_fields}`.
* Spam-Schutz mit Honeypot, Mindestzeit und Limit pro IP (gehasht gespeichert).
* Datenschutz: Aufbewahrungsdauer, Export und Löschung über die WordPress-Datenschutz-Werkzeuge.

Erweiterbar über die Filter `mlab_forms_field_types`, `mlab_forms_options_providers`, `mlab_forms_style_vars` und `mlab_forms_client_ip`.

= Updates =

Updates kommen aus den GitHub-Releases von momentumlab-ch/mlab-forms und erscheinen wie jedes andere Plugin-Update unter «Dashboard › Aktualisierungen» und in der Plugin-Liste. Automatische Updates schaltest du wie bei jedem Plugin mit «Automatische Updates aktivieren» in der Plugin-Liste ein oder aus (Standard: aus). Der Tab «Updates» zeigt die Versionen und denselben Schalter.

== Installation ==

1. Das ZIP `mlab-forms-<version>.zip` aus den GitHub-Releases herunterladen.
2. In WordPress unter «Plugins › Installieren › Plugin hochladen» das ZIP auswählen und installieren.
3. Plugin aktivieren.
4. Optional in der Plugin-Liste «Automatische Updates aktivieren» wählen.

== Neue Version veröffentlichen ==

1. Version an drei Stellen erhöhen: Plugin-Header und Konstante in `mlab-forms.php`, «Stable tag» in `readme.txt`.
2. Auf main mergen, dann ein Release mit dem Tag `vX.Y.Z` anlegen.
3. Der Workflow baut das ZIP und hängt es an das Release. Ab dann sehen die Websites das Update.

== Changelog ==

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
