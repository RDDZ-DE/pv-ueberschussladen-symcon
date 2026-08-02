# Changelog

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
