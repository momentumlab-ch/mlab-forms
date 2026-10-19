=== MomentumLab Forms ===
Contributors: momentumlab
Tags: formulare, kontakt, anfrage
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Anfrage- und Kontaktformulare für Kundenseiten von Momentum Lab.

== Description ==

MomentumLab Forms hat einen eigenen Menüpunkt im Backend mit «Alle Formulare», «Neues Formular», «Anfragen» und «Einstellungen» (Tabs Allgemein, E-Mail, Spam-Schutz, Datenschutz, Updates).

* Formular-Builder mit Bausteinen (Vorname, E-Mail, PLZ, Einwilligung …), eigenen Feldern, Pflichtfeldern, Auswahllisten und Vorschau. Dahinter steht ein JSON-Schema (Experten-Ansicht) mit Vorlagen (Kontakt, Anfrage, Gutschein), Prüfung beim Speichern und Revisionen.
* Einbinden mit `[mlab_form id="123"]` oder `[mlab_form slug="kontakt"]`. Funktioniert auch ohne JavaScript und mit Seiten-Cache.
* Anfragen mit Status, Notizen und CSV-Export; Benachrichtigungen mit Platzhaltern wie `{field:email}` und `{all_fields}`.
* Spam-Schutz mit Honeypot, Mindestzeit und Limit pro IP (gehasht gespeichert). Optional Cloudflare Turnstile, Google reCAPTCHA v3, hCaptcha (nur einer pro Formular) und Akismet; die Schlüssel liegen nur in den Einstellungen.
* Absender (Name, E-Mail, «Antwort an») und Aufbewahrung pro Formular einstellbar.
* Empfänger: Der Standard-Empfänger (Einstellungen › E-Mail) gilt für alle Formulare. Im Builder unter «Einstellungen › Empfänger» überschreibst du ihn pro Formular.
* Datenschutz: Aufbewahrungsdauer, Export und Löschung über die WordPress-Datenschutz-Werkzeuge.
* Mehrsprachig: Texte pro Formular in mehreren Sprachen pflegen, passend zur Sprache der Seite (WPML, Polylang oder Sprache der Website). Siehe «Mehrsprachige Formulare».

= Empfänger pro Formular =

Ohne Angabe gehen die Anfragen an den Standard-Empfänger aus den Einstellungen (Tab «E-Mail»), sonst an die Admin-E-Mail der Website. Ein Formular überschreibt das mit `settings.recipients`: ein Text mit festen Adressen, getrennt durch Komma, Semikolon oder Zeilenumbruch (höchstens 10, jede muss gültig sein; Duplikate fallen weg). Leer oder fehlend heisst «Standard-Empfänger».

* Neues Merge-Tag `{default_recipient}`: die Adressen von `settings.recipients`, sonst der Standard-Empfänger, sonst die Admin-E-Mail. Die mitgelieferten Vorlagen und neue Formulare verwenden es als `to` der Benachrichtigung.
* Der Override gilt für jede Benachrichtigung, deren `to` genau `{site_admin_email}` oder `{default_recipient}` ist, also auch für bestehende Formulare. Andere Angaben, z. B. `{field:email}` für die Bestätigung an den Besucher, bleiben unverändert.
* Besucher können den Empfänger nie beeinflussen: In `settings.recipients` und im Standard-Empfänger zählen nur feste Adressen, Platzhalter wie `{field:email}` werden dort nicht aufgelöst.

= Mehrsprachige Formulare =

Im Schema gibt `languages` die Sprachen an, z. B. `["de", "fr", "en"]`; die erste ist die Standardsprache, ihre Texte stehen wie bisher in den normalen Eigenschaften. Fehlt `languages`, ist das Formular einsprachig. Weitere Sprachen liegen unter `i18n`:

* Feld: `"i18n": { "fr": { "label": "…", "placeholder": "…", "help": "…", "content": "…", "options": { "<Wert der Option>": "<Beschriftung>" } } }`
* Einstellungen: `settings.i18n` mit `submit_label` und `success_message`.
* Benachrichtigung: `i18n` mit `subject` und `body`.

Leere oder fehlende Übersetzungen zeigen den Standardtext. Der Wert einer Option ist in allen Sprachen gleich, nur die Beschriftung wechselt; gespeichert wird der Wert. Das Formular sendet die Sprache im versteckten Feld `mlab_lang` mit (der Server akzeptiert nur Sprachen des Formulars). Mails an die Admin-Adresse (`to` enthält nur diese Adresse) erscheinen in der Standardsprache, alle anderen in der Sprache der Absendung. Texte, die vom Plugin selbst stammen (z. B. «Bitte fülle dieses Feld aus.»), folgen der Sprache der Website über die `.po`-Dateien (Deutsch (Schweiz) und Englisch liegen bei) und nicht der Sprache des Formulars.

= Hooks =

Filter:

* `mlab_forms_field_types`: eigene Feldtypen ergänzen oder bestehende ersetzen (Typ => Klasse).
* `mlab_forms_presets`: Bausteine des Builders ergänzen, ändern oder entfernen (je Baustein `key`, `name`, `hint`, optional `id_hint` und `field` mit dem Feld-Schema ohne `id`).
* `mlab_forms_options_providers`: Quellen für Auswahllisten ergänzen (`options_source` im Formular).
* `mlab_forms_style_vars`: CSS-Variablen der Formulare setzen, z. B. `--mlab-form-accent` aus den Theme-Farben.
* `mlab_forms_current_language`: Sprache der Seite für mehrsprachige Formulare überschreiben (Code wie `fr`, dazu die Sprachen des Formulars). Ohne Filter gilt WPML, dann Polylang, dann die Sprache der Website.
* `mlab_forms_client_ip`: IP-Adresse hinter einem Proxy oder CDN korrekt bestimmen.
* `mlab_forms_recaptcha_threshold`: Mindest-Score für Google reCAPTCHA v3 (Standard 0.5).
* `mlab_forms_token_max_age`: Höchstalter des Zeitfallen-Tokens in Sekunden (Standard 604800, also 7 Tage).

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

= 0.8.0 =
* Neues Feld «Datei» für einen Upload, z. B. den Lebenslauf. Erlaubt sind standardmässig PDF, DOC und DOCX bis 5 MB; Endungen (`accept`) und Grösse in MB (`max_size`, höchstens 20) lassen sich pro Feld einstellen. Der Server prüft Endung und Inhalt der Datei.
* Hochgeladene Dateien liegen geschützt unter `wp-content/uploads/mlab-forms/`, ohne direkten Zugriff aus dem Web. In der Anfrage im Backend gibt es einen Link zum Herunterladen, nur für Benutzer, die Anfragen sehen dürfen. Die Datei hängt an der Mail an die Empfänger der Seite, nie an der Bestätigung an den Besucher.
* Dateien werden mit ihrer Anfrage gelöscht: beim Aufräumen nach der Aufbewahrungsfrist, bei der Datenlöschung nach DSGVO/DSG und beim Löschen des Plugins (wenn die Daten nicht behalten werden). Speichert ein Formular keine Anfragen, wird die Datei nach dem Versand gelöscht.
* Formulare mit Pflichtfeldern zeigen oben den Hinweis «* Pflichtfeld». Ausschalten pro Formular mit `settings.required_note = false`.
* Auswahllisten mit Pflicht: Die erste Zeile «Bitte wählen» ist nur noch Hinweis und lässt sich nicht mehr auswählen.

= 0.7.1 =
* Jedes Formular kann an eine eigene Adresse senden. Im Builder unter «Einstellungen › Empfänger» trägst du eine oder mehrere Adressen ein (z. B. verkauf@firma.ch, info@firma.ch). Ist das Feld leer, gehen die Anfragen wie bisher an den Standard-Empfänger. Eine ungültige Adresse meldet der Builder sofort am Feld; der Server lehnt sie beim Speichern mit einer verständlichen Meldung ab.
* Der Standard-Empfänger in den Einstellungen (Tab «E-Mail», Abschnitt «Absender») darf jetzt mehrere Adressen enthalten. Ist er leer, gilt die Admin-E-Mail der Website.
* Die Bestätigungsmail an den Besucher bleibt unverändert. Bestehende Formulare funktionieren weiter und können die neue Einstellung ebenfalls nutzen.
* Mails an die Empfänger der Seite erscheinen in der Standardsprache des Formulars, auch wenn ein Formular eigene Empfänger hat.
* Für Entwickler: neues Merge-Tag `{default_recipient}` und optionales Schema-Feld `settings.recipients`. Besucher-Eingaben (`{field:…}`) werden als Empfänger nie aufgelöst.

= 0.7.0 =
* Mehrsprachige Formulare: Beschriftungen, Platzhalter, Hilfetexte, Einträge von Auswahllisten, Text des Absende-Buttons und Erfolgsmeldung lassen sich pro Sprache pflegen. Im Builder wählst du oben die Sprache (DE, FR, EN …) oder fügst eine hinzu (Deutsch, Französisch, Italienisch, Englisch, Rätoromanisch, Spanisch, Portugiesisch, Niederländisch). In einer weiteren Sprache siehst du nur die Texte, mit dem Standardtext darunter; ein leeres Feld zeigt den Standardtext. Der Zähler «12 von 14 übersetzt» und die Markierung «Noch nicht übersetzt» an den Feldern zeigen, was noch fehlt. Eine Sprache entfernen fragt zuerst nach und lässt sich kurz rückgängig machen. Die Vorschau hat einen eigenen Umschalter.
* Die Seite bestimmt die Sprache: WPML, Polylang oder die Sprache der Website. Pflegt das Formular die Sprache nicht, erscheint die Standardsprache (die erste Sprache). Erfolgsmeldung, Fehlermeldungen und die Mail an den Besucher kommen in der Sprache, in der das Formular ausgefüllt wurde. Mails an die Admin-Adresse erscheinen immer in der Standardsprache des Formulars. Gespeichert wird der Wert einer Auswahl, nicht ihre Übersetzung, damit Anfragen und Auswertungen vergleichbar bleiben. Die Sprache steht in der Detailansicht der Anfrage.
* Betreff und Text der Benachrichtigungen übersetzt du in der Experten-Ansicht (JSON) unter «i18n» der Benachrichtigung; einen Editor dafür gibt es im Builder noch nicht.
* Bestehende Formulare bleiben unverändert. Das Schema bleibt bei Version 1, alles Neue ist optional («languages» und «i18n»).
* Die Plugin-Oberfläche gibt es zusätzlich auf Englisch (en_US), damit Seiten auf Englisch englische Meldungen zeigen.
* Für Entwickler: neuer Filter `mlab_forms_current_language`, um die erkannte Sprache zu überschreiben.

= 0.6.0 =
* Neuer Formular-Builder: Felder werden links aus Bausteinen gewählt, in der Mitte angeordnet und rechts bearbeitet. Reiter «Felder», «Einstellungen» und «Vorschau»; am Handy als Zeile mit Bausteinen und Eigenschaften als Blatt von unten. Alles lässt sich per Tastatur bedienen, Felder verschiebst du mit den Pfeilen.
* Fertige Bausteine mit passender Prüfung und Autofill: Vorname, Nachname, E-Mail, Telefon, Firma, Adresse, PLZ, Ort, Land, Nachricht, Datum, Auswahlliste, Mehrfachauswahl, Einwilligung und Zwischentitel. Dazu «Eigenes Feld» mit frei wählbarer Art. Interne Feldnamen vergibt das System und lassen sich später ohne Folgen für gespeicherte Anfragen umbeschriften.
* Pro Feld einstellbar: Pflichtfeld, Breite (voll, halb, Drittel), Platzhalter, Hilfetext, Standardwert. Bei Auswahllisten: Einträge hinzufügen, entfernen und per Pfeil verschieben, einen Eintrag als Standard markieren, leere erste Zeile «Bitte wählen» und Vorauswahl per Link.
* Die Vorschau zeigt das Formular genau so, wie es auf der Seite erscheint, auch vor dem Speichern, am Handy oder Computer. Fehlt etwas, nennt sie das Feld und das Problem in einfachen Worten.
* Der Reiter «Einstellungen» enthält Text des Absende-Buttons, Erfolgsmeldung und Danke-Seite. Das JSON bleibt über «Experten-Ansicht (JSON)» erreichbar und die Quelle der Wahrheit; bestehende Formulare, Revisionen und die Prüfung beim Speichern funktionieren unverändert.
* Neue Formulare beginnen mit Vorname, Nachname, E-Mail, Nachricht und Einwilligung. Die mitgelieferten Vorlagen lassen sich weiterhin laden.
* Für Entwickler: neuer Filter `mlab_forms_presets` für eigene Bausteine.

= 0.5.0 =
* Absender einstellbar: Name, E-Mail-Adresse und «Antwort an» (E-Mail des Besuchers oder feste Adresse), global und pro Formular. Ohne eigene Angabe verwendet WordPress seinen Standard-Absender; eine Absender-Adresse von einer fremden Domain wird auf der Einstellungsseite mit einem Hinweis markiert.
* Aufbewahrung pro Formular wählbar: 30 Tage, 90 Tage, 1 Jahr, 2 Jahre, unbegrenzt oder eigene Anzahl Tage.
* Spam-Schutz zuschaltbar: Cloudflare Turnstile, Google reCAPTCHA v3 oder hCaptcha (nur einer gleichzeitig) und Akismet. Mit «Verbindung testen» auf der Einstellungsseite. Standardmässig ist alles aus; der Datenschutz-Textbaustein nennt nur eingeschaltete Dienste und weist auf die Übermittlung in die USA hin. Akismet erhält nur Name (aus als Name gekennzeichneten Feldern), E-Mail, Nachricht, IP, Browser und Seiten-Adresse ohne Parameter. Abgelehnte Absendungen zählen im Limit pro Stunde mit, Zeitfallen-Token laufen nach 7 Tagen ab, und gespeicherte Schlüssel lassen sich mit «Schlüssel entfernen» löschen.
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
