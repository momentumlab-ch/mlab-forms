== Changelog ==

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
