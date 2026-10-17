# MomentumLab Forms: Builder und Ausbau

Stand: 2026-10-17. Freigegeben von Chris im Projekt-Thread (Vorschlag A für Mehrsprachigkeit).

## Absicht

Kunden von Momentum Lab sollen Formulare ohne Code erstellen und pflegen. Heute ist ein Formular JSON in einer Metabox. Das JSON bleibt die einzige Quelle der Wahrheit, verschwindet aber hinter einer Oberfläche. Leitbild: Apple-Design, klar, selbsterklärend, die Logik sitzt im System. Vorbilder für den Funktionsumfang sind Contact Form 7 (Mail-Vorlagen, Platzhalter) und WPForms (Builder, bedingte Felder, mehrstufig).

## Ziele

1. **Builder** im Backend: Palette links, Formular in der Mitte, Eigenschaften rechts, Reiter Felder, Einstellungen, Vorschau. Bedienbar am Handy und per Tastatur. Der Klick-Entwurf im Thread ist die Referenz für Aufbau und Texte.
2. **Bausteine und eigene Felder:** vordefinierte Felder (Vorname, Nachname, E-Mail, Telefon, Firma, Adresse, PLZ und Ort, Land, Nachricht, Datum, Einwilligung) mit fertiger Prüfung und Autofill, dazu «Eigenes Feld» mit frei wählbarem Typ. Interne Feldnamen vergibt das System.
3. **Pflicht oder freiwillig** pro Feld. Beim Senden wird Pflicht und Format geprüft (E-Mail, Telefon, PLZ, Datum, Zahl), Meldung am Feld in verständlicher Sprache. Die Prüfung läuft serverseitig, das Frontend spiegelt sie.
4. **Auswahllisten:** eine Zeile als Standard markierbar, optional leere erste Zeile «Bitte wählen», Vorauswahl per Link oder Hook mit Schalter pro Feld.
5. **Spam-Schutz als Schalter:** Honeypot und Zeitfalle immer an. Einschaltbar: Cloudflare Turnstile, Google reCAPTCHA v3, hCaptcha (nur eines gleichzeitig) und Akismet. Pro Dienst Schlüsselfelder und «Verbindung testen». Google ist standardmässig aus und trägt einen Datenschutzhinweis. Das Briefing sagte «kein Google reCAPTCHA»; Chris wünscht ihn ausdrücklich als Option.
6. **Absender:** Absendername, Absender-E-Mail und Antwort-an konfigurierbar. Standard: Seitenname und Admin-Adresse. Antwort-an kann die vom Besucher eingegebene E-Mail sein. Hinweis, dass die Absender-Adresse zur eigenen Domain passen soll.
7. **Aufbewahrung:** Gesendete Formulare bleiben gespeichert. Pro Formular einstellbar: 30 Tage, 90 Tage, 1 Jahr, 2 Jahre, unbegrenzt oder eigene Anzahl Tage. Danach löscht der bestehende Cron (erst Papierkorb, nach 30 Tagen endgültig). Das Schema kennt `retention_days` bereits.
8. **Mehrsprachigkeit (Vorschlag A):** Alle Texte eines Formulars (Beschriftungen, Platzhalter, Hilfetexte, Optionen, Meldungen, Button, Mail-Betreff und -Text) lassen sich pro Sprache pflegen, Reiter im Builder. Gewählt wird nach der Sprache der Seite (WPML, Polylang, sonst `determine_locale`), Rückfall auf die Standardsprache des Formulars. Plugin-Texte bleiben über `.po` übersetzbar.
9. **Design:** Standard ist «Theme übernehmen»: das Formular bringt kein eigenes Aussehen mit und erbt Schrift, Farben und Eingabefelder. Wer mehr will, stellt Farben, Rundung, Abstände und Button-Stil im Backend ein (CSS-Variablen, bestehende `--mlab-form-*`) und kann eigenes CSS ergänzen.
10. **KI darf bearbeiten:** `create-form` und `update-form` als Abilities, validiert gegen das Schema, jede Änderung als Revision, rückgängig machbar. Kein Mailversand durch die KI.
11. **Qualität:** `/simplify` und `/security-review` am Ende jeder Etappe, vor dem Release.

## Nicht-Ziele

- Kein Zahlungs-Feld, keine Datei-Uploads in dieser Runde (Chris meinte mit «mandatory files» die Pflichtfeld-Prüfung, nicht Uploads).
- Keine eigene Update-Logik, keine externen Requests ausser den vom Admin eingeschalteten Spam-Diensten.
- Kein Page-Builder für das Layout über Breite voll/halb/drittel hinaus.

## Anti-Ziele (wäre ein Fehlschlag, auch wenn es technisch läuft)

- Ein Kunde muss JSON, CSS oder Feldnamen anfassen, um ein normales Formular zu bauen.
- Fehlermeldungen oder Beschriftungen mit Fachjargon.
- Spam-Dienste, die ohne ausdrückliches Einschalten Daten an Dritte senden.
- Ein bestehendes Formular geht beim Update kaputt: das Schema bleibt abwärtskompatibel (`schema_version` 1, neue Felder optional).

## Entscheidungen von Chris

- Mehrsprachigkeit nach Vorschlag A (Texte pro Formular in mehreren Sprachen).
- Umsetzung durch günstigere Modelle, Prüfung und Security-Review durch die starken.
- Bereits gesendete Formulare bleiben gespeichert, nur die Dauer wird konfigurierbar.

## Entscheidungen des Bauenden (Annahmen, zu prüfen)

- **Builder in Vanilla-JS ohne Build-Schritt** statt React mit `@wordpress/scripts`. Grund: das ZIP läuft ohne Node, weniger Abhängigkeiten, der Klick-Entwurf ist bereits Vanilla. Wechsel auf React wäre später möglich, das Schema bleibt gleich.
- **Reihenfolge der Etappen** (je ein lauffähiges ZIP, Version erhöhen und mergen löst das Release aus):
  - 0.5.0 Grundlagen: Absender, Aufbewahrung, Spam-Dienste, Pflicht- und Formatprüfung, Standardzeile in Listen (Schema, Logik, Einstellungsseite, noch ohne Builder).
  - 0.6.0 Builder: Oberfläche, Bausteine, eigene Felder, Speichern über REST, JSON-Ansicht.
  - 0.7.0 Mehrsprachigkeit.
  - 0.8.0 Design-Einstellungen.
  - 0.9.0 KI-Abilities `create-form` und `update-form`.
- Nummerierung der Bausteine und deren Texte folgen dem Klick-Entwurf.
- Akismet nutzt den Dienst des Akismet-Plugins, wenn aktiv, sonst eigener API-Schlüssel.
- Schlüssel der Spam-Dienste liegen in Optionen (nicht im Schema, nie im Export eines Formulars) und werden nur im Backend angezeigt, maskiert.

## Überlassen dem Bauenden

Dateiaufteilung unter `inc/`, genaue Texte der Fehlermeldungen (Deutsch, Schweizer Schreibweise), Testfälle, Gestaltung der Einstellungsseite im Rahmen des Klick-Entwurfs.

## Rahmen

Folgt dem Skill `momentumlab:wordpress-development`: Klassen unter `inc/`, Updates nur über Plugin Update Checker, PHPCS (WordPress), PHPStan Level 6, PHPUnit. Texte Deutsch (Schweiz). Keine Hinweise auf KI-Werkzeuge in Commits, Code, Changelog oder readme.
