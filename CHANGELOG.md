# Changelog

## [1.2.1] - 2026-08-14

### Behoben
- "Geladene Energie (Session)" und "PV-Anteil (Session)" resetteten sich
  praktisch nie: die Erkennung einer neuen Ladesession beruhte auf dem
  Wechsel des von uns GESENDETEN Ladestroms von 0 auf >0 - durch das
  Sicherheitsnetz (nie 0 senden) trat dieser Fall aber fast nie ein, wodurch
  "Session" faktisch zu einem tagelangen Dauerzähler wurde statt sich pro
  Ladevorgang zurückzusetzen. Die Erkennung basiert jetzt (sofern eine
  Ladeleistung-Variable konfiguriert ist) auf der tatsächlich GEMESSENEN
  Ladeleistung statt auf dem selbst gesendeten Wert.

## [1.2.0] - 2026-08-11

### Hinzugefügt
- Neue Option "Echtes Aus erlauben (0A senden)" (Standard: aus). Bisher
  sendete der Modus "Aus" immer den Minimalstrom statt 0, weil viele
  Wallboxen eine 0-Vorgabe als Fehler werten. Wer eine Zielsteuerung nutzt,
  die 0 selbst sicher abfängt (z.B. die eigene Heidelberg-to-MQTT-Firmware,
  die 0 intern in ein Remote-Lock statt eine direkte Registerschreibung
  übersetzt), kann jetzt bewusst ein echtes Aus aktivieren.

## [1.1.2] - 2026-08-06

### Behoben
- Status blieb nach Ladeende (Auto voll oder abgesteckt) fälschlich auf
  "lädt" hängen, obwohl real 0 W flossen - das Modul fordert unabhängig
  davon weiter Strom an, solange der Modus nicht manuell auf "Aus" steht.
  Betraf u.a. die Fronius-Drosselung, die dadurch dauerhaft deaktiviert
  blieb. Status wird jetzt (falls eine Ladeleistungs-Variable konfiguriert
  ist) aus der tatsächlich gemessenen Ladeleistung abgeleitet, nicht mehr
  nur aus dem gewünschten Modus.

## [1.1.1] - 2026-08-06

### Behoben
- Status blieb nach einem Moduswechsel (z.B. auf "Aus") auf dem alten Wert
  hängen, falls sich der tatsächlich gesendete Ladestrom dabei zufällig
  nicht änderte (z.B. weil vorher schon der Minimalstrom aktiv war) - der
  Status wurde nur innerhalb des Hysterese-Blocks aktualisiert, der in
  diesem Fall übersprungen wurde. Status wird jetzt bei jedem Zyklus
  unabhängig davon aus dem aktuellen Modus berechnet.

## [1.1.0] - 2026-08-05

### Hinzugefügt
- Zwei neue Variablen: "Davon aus PV (Session)" und "PV-Anteil (Session)" -
  berechnen pro Regelzyklus den PV-gedeckten Anteil der Ladeleistung
  (`min(Überschuss, Ladeleistung)`) und summieren ihn über die Session auf,
  um den Prozentsatz der rein aus Solarstrom geladenen Energie zu ermitteln.

## [1.0.1] - 2026-08-01

### Behoben
- Name und Profil einer Variable wurden nach dem ersten Anlegen nicht mehr
  aktualisiert, selbst nach erneutem Speichern. `MaintainVariable()`
  aktualisiert Name/Profil offenbar nur beim erstmaligen Anlegen - jetzt
  zusätzlich bei jedem Speichern explizit erzwungen.

## [1.0.0] - 2026-08-01

### Hinzugefügt
- Erste Modul-Version: portiert aus der bisherigen Script-Version, jetzt mit
  frei auswählbaren Quellvariablen (Netzsaldo, Ladeleistung, Ladestatus,
  Ladestrom-Vorgabe) statt fester `VID_*`-Konstanten, sowie einstellbaren
  Wallbox-Parametern (Phasen, Spannung, Min./Max. Ladestrom) statt fester
  Konstanten im Code.
- 4 gleichwertige Modi (Aus, Minimal, Minimal mit Überschuss, Maximal) mit
  Hysterese im stufenlosen Modus.
- Sicherheitsnetz: Ladestrom wird nie unter das Minimum (insbesondere nie 0)
  gesetzt.
