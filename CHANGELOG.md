== Changelog ==

= 0.9.1 =
* Der Spam-Schutz (Turnstile, reCAPTCHA, hCaptcha) erscheint in der Sprache der Seite statt in der Browser-Sprache des Besuchers. Für Sprachen, die der Dienst nicht kennt, wählt er selbst.
* Läuft MomentumLab Multilingual, übernimmt das Formular dessen Sprache der Seite. WPML, Polylang und die Sprache der Website funktionieren wie bisher.
* Die Texte des Plugins gibt es jetzt auch auf Französisch und Italienisch (Schweiz und Standard).
* Für Entwickler: Filter `mlab_forms_captcha_language`.

= 0.9.0 =
* Neu: WhatsApp-Knopf. Nummer und vorausgefüllte Nachricht unter Einstellungen › Allgemein, {page} setzt den Titel der Seite ein. Als Shortcode [mlab_whatsapp] (Attribute label, offer, text, class) oder als runder Knopf unten rechts auf allen Seiten (Standard aus). Ein normaler Link zu wa.me: kein Skript, kein Cookie.
* Neu: Formular-Abbrüche. Das Skript meldet einmal pro Seitenaufruf die erste Eingabe (nur die Formular-ID, per sendBeacon). Gespeichert wird eine Zahl pro Formular und Tag, 400 Tage lang, höchstens 1000 pro Tag. Starts::funnel() liefert angefangen, gesendet und abgebrochen, z. B. für MomentumLab Insights.
* Neu: Nach dem Senden löst das Formular das Ereignis «mlab-forms:sent» aus. Die Meldung an Google Analytics (generate_lead) bleibt wie bisher und läuft nur, wenn Analytics geladen und erlaubt ist.

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
