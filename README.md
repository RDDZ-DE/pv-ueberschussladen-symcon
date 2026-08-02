# PV-Überschussladen Symcon-Modul

IP-Symcon-Modul für PV-Überschussladen einer Wallbox. Berechnet aus
Netzeinspeisung/-bezug und aktueller Ladeleistung den verfügbaren
Überschuss und regelt den Ladestrom entsprechend - komplett unabhängig
vom Wallbox-Hersteller/-Anbindung, solange eine schreibbare
Ladestrom-Variable mit Aktion existiert (z.B. aus dem
[Heidelberg-to-MQTT-Modul](https://github.com/RDDZ-DE/heidelberg-to-mqtt-symcon),
funktioniert aber genauso mit jeder anderen Wallbox-Anbindung).

## Voraussetzungen

- Eine Variable, die die aktuelle Netzeinspeisung/den Netzbezug in Watt
  liefert (z.B. von einem Shelly 3EM o.ä.)
- Eine schreibbare Variable mit Aktion, über die sich der Ladestrom (in
  Ampere) der Wallbox setzen lässt
- Optional: eine Variable mit der aktuellen Ladeleistung der Wallbox in
  Watt (wichtig, damit der Überschuss nicht durch die eigene Ladeleistung
  verfälscht wird)

## Installation

**Über die Modules Control:** Symcon → Modules Control → "+" → Git-URL
`https://github.com/RDDZ-DE/pv-ueberschussladen-symcon` eintragen.

**Alternativ lokal:**
```
cd /var/lib/symcon/modules
git clone https://github.com/RDDZ-DE/pv-ueberschussladen-symcon.git
```

## Einrichtung

1. Instanz "PV Überschussladen" anlegen (kein Parent nötig).
2. Quellvariablen auswählen: Netzsaldo, optional Ladeleistung und
   Ladestatus, sowie die schreibbare Ladestrom-Vorgabe der Wallbox.
3. Prüfen, ob dein Netzsaldo-Sensor Einspeisung als positiven oder
   negativen Wert meldet, und die Checkbox entsprechend setzen.
4. Wallbox-Parameter (Phasen, Spannung, Min./Max. Ladestrom) passend zu
   deiner Wallbox eintragen.
5. Speichern - die Instanz legt eigene Status-/Steuervariablen an
   (Überschuss, Modus, aktuell gesetzter Ladestrom, Ladestart, Session-kWh).

## Modus (4 gleichwertige, direkt wählbare Zustände)

- **Aus** - lädt trotzdem mit dem eingestellten Minimalstrom weiter (siehe
  Sicherheitshinweis unten), sperrt aber nicht per se - "echtes Aus" hängt
  von der angebundenen Wallbox-Steuerung ab.
- **Minimal (fest)** - lädt konstant mit dem eingestellten Minimalstrom.
- **Minimal mit Überschuss** - lädt mindestens mit dem Minimalstrom, regelt
  stufenlos nach oben hoch, sobald mehr PV-Überschuss verfügbar ist. Der
  "intelligente" Alltagsmodus.
- **Maximal (fest)** - lädt konstant mit dem eingestellten Maximalstrom.

## Wichtiger Sicherheitshinweis

Der Ladestrom wird NIE unter den eingestellten Minimalwert (insbesondere
nie auf 0) gesetzt. Viele Wallboxen (u.a. Heidelberg Energy Control)
werten eine Stromvorgabe von 0 als Kommunikationsfehler statt als "Aus".
Ein echtes, vollständiges Abschalten der Ladung muss deshalb über eine
separate Sperr-/Freigabe-Funktion der jeweiligen Wallbox-Anbindung erfolgen,
nicht über dieses Modul allein.
