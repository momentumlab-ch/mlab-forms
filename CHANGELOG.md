== Changelog ==

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
