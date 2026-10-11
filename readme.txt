=== MomentumLab Forms ===
Contributors: momentumlab
Tags: formulare, kontakt, anfrage
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Anfrage- und Kontaktformulare für Kundenseiten von Momentum Lab.

== Description ==

MomentumLab Forms hat einen eigenen Menüpunkt im Backend. Unter «MomentumLab Forms › Einstellungen» gibt es die Tabs Allgemein, E-Mail, Spam-Schutz, Datenschutz und Updates.

Updates kommen aus den GitHub-Releases von momentumlab-ch/mlab-forms. Im Tab «Updates» legst du fest, ob WordPress sie automatisch installiert (Standard: aus).

== Installation ==

1. Das ZIP `mlab-forms-<version>.zip` aus den GitHub-Releases herunterladen.
2. In WordPress unter «Plugins › Installieren › Plugin hochladen» das ZIP auswählen und installieren.
3. Plugin aktivieren.
4. Unter «MomentumLab Forms › Einstellungen › Updates» festlegen, ob Updates automatisch installiert werden (Standard: aus).

== Neue Version veröffentlichen ==

1. Version an drei Stellen erhöhen: Plugin-Header und Konstante in `mlab-forms.php`, «Stable tag» in `readme.txt`.
2. Auf main mergen, dann ein Release mit dem Tag `vX.Y.Z` anlegen.
3. Der Workflow baut das ZIP und hängt es an das Release. Ab dann sehen die Websites das Update.

== Changelog ==

= 0.2.0 =
* Eigener Menüpunkt «MomentumLab Forms» mit Einstellungen in Tabs.
* Updates aus den GitHub-Releases, mit Knopf «Jetzt nach Updates suchen».

= 0.1.0 =
* Gerüst mit Einstellungsseite und Schalter für automatische Updates.
