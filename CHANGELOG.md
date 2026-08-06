# Changelog

## [1.1.1] - 2026-08-06

### Behoben
- Status blieb nach einem Moduswechsel (z.B. auf "Aus") auf dem alten Wert
  hängen, falls sich der tatsächlich gesendete Ladestrom dabei zufällig
  nicht änderte (z.B. weil vorher schon der Minimalstrom aktiv war) - der
  Status wurde nur innerhalb des Hysterese-Blocks aktualisiert, der in
  diesem Fall übersprungen wurde. Betraf u.a. die Fronius-Drosselung, die
  sich dadurch nach Ladeende nicht wieder aktivierte. Status wird jetzt bei
  jedem Zyklus unabhängig davon aus dem aktuellen Modus berechnet.

## [1.1.0] - 2026-08-05

### Hinzugefügt
- Zwei neue Variablen: "Davon aus PV (Session)" und "PV-Anteil (Session)" -
  berechnen pro Regelzyklus den PV-gedeckten Anteil der Ladeleistung
  (`min(Überschuss, Ladeleistung)`) und summieren ihn über die Session auf,
  um den Prozentsatz der rein aus Solarstrom geladenen Energie zu ermitteln.

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
