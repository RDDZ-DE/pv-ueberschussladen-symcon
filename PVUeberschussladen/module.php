<?php

declare(strict_types=1);

class PVUeberschussladen extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Quellvariablen (statt fixer VID_*-Konstanten aus der Script-Vorversion)
        $this->RegisterPropertyInteger('NetzsaldoVariable', 0);
        $this->RegisterPropertyBoolean('EinspeisungIstPositiv', true);
        $this->RegisterPropertyInteger('LadeleistungVariable', 0);
        $this->RegisterPropertyInteger('LadestatusVariable', 0);
        $this->RegisterPropertyInteger('LadestromSollVariable', 0);

        // Wallbox-/Regelparameter (statt fixer Konstanten)
        $this->RegisterPropertyInteger('Phasen', 3);
        $this->RegisterPropertyInteger('Spannung', 230);
        $this->RegisterPropertyInteger('MinAmpere', 6);
        $this->RegisterPropertyInteger('MaxAmpere', 16);
        $this->RegisterPropertyInteger('HalteVerzoegerungS', 60);
        $this->RegisterPropertyInteger('ZyklusSekunden', 20);

        // Interner Zustand (keine sichtbaren Variablen, um die Instanz übersichtlich zu halten)
        $this->RegisterAttributeInteger('StufeSeitTS', 0);
        $this->RegisterAttributeInteger('NeuerAmpereIntern', -1);
        $this->RegisterAttributeInteger('LetzterLaufTS', 0);

        $this->RegisterTimer('Regelzyklus', 0, 'PVUL_Regelzyklus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SicherstelleProfile();

        $variablen = [
            ['Ueberschuss',        'Aktueller Überschuss',                      1, 'PVUL.Watt'],
            ['Modus',              'Modus',                                     1, 'PVUL.Modus'],
            ['LadestromIst',       'Aktuell gesetzter Ladestrom',                1, 'PVUL.Ampere'],
            ['Ladestufe',          'Status (0=Aus,1=Teilladung,2=Volladung)',    1, ''],
            ['Ladestart',          'Ladestart',                                 3, ''],
            ['SessionKwh',         'Geladene Energie (Session)',                2, '~Electricity'],
            ['SessionKwhSolar',    'Davon aus PV (Session)',                    2, '~Electricity'],
            ['SessionSolarAnteil', 'PV-Anteil (Session)',                       1, 'PVUL.Prozent'],
            ['LetzterLauf',        'Letzte Berechnung',                         3, '']
        ];
        $positionen = [10, 20, 30, 40, 50, 60, 65, 68, 70];
        foreach ($variablen as $i => [$ident, $name, $typ, $profil]) {
            $this->MaintainVariable($ident, $name, $typ, $profil, $positionen[$i], true);
            $this->AktualisiereNameUndProfil($ident, $name, $profil);
        }
        $this->EnableAction('Modus');

        $netzOk = $this->ReadPropertyInteger('NetzsaldoVariable') > 0;
        $sollOk = $this->ReadPropertyInteger('LadestromSollVariable') > 0;

        if (!$netzOk || !$sollOk) {
            $this->SetStatus(201);
            $this->SetTimerInterval('Regelzyklus', 0);
            return;
        }

        $this->SetStatus(102);
        $this->SetTimerInterval('Regelzyklus', $this->ReadPropertyInteger('ZyklusSekunden') * 1000);
    }

    private function SicherstelleProfile(): void
    {
        $modusProfil = 'PVUL.Modus';
        if (!IPS_VariableProfileExists($modusProfil)) {
            IPS_CreateVariableProfile($modusProfil, 1);
        }
        IPS_SetVariableProfileAssociation($modusProfil, 0, 'Aus', '', -1);
        IPS_SetVariableProfileAssociation($modusProfil, 1, 'Minimal (fest)', '', -1);
        IPS_SetVariableProfileAssociation($modusProfil, 2, 'Minimal mit Überschuss', '', -1);
        IPS_SetVariableProfileAssociation($modusProfil, 3, 'Maximal (fest)', '', -1);

        foreach ([['PVUL.Watt', 1, ' W'], ['PVUL.Ampere', 1, ' A'], ['PVUL.Prozent', 1, ' %']] as [$name, $typ, $suffix]) {
            if (!IPS_VariableProfileExists($name)) {
                IPS_CreateVariableProfile($name, $typ);
            }
            IPS_SetVariableProfileText($name, '', $suffix);
        }
    }

    /**
     * MaintainVariable() setzt Name/Profil offenbar nur beim erstmaligen Anlegen einer Variable,
     * nicht bei jedem erneuten ApplyChanges für eine bereits bestehende Variable. Deshalb hier
     * zusätzlich explizit erzwungen, bei jedem Speichern.
     */
    private function AktualisiereNameUndProfil(string $ident, string $name, string $profil): void
    {
        $vid = @$this->GetIDForIdent($ident);
        if ($vid === false || $vid === 0) {
            return;
        }
        IPS_SetName($vid, $name);
        if ($profil !== '') {
            IPS_SetVariableCustomProfile($vid, $profil);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'Modus') {
            $this->SetValue('Modus', $Value);
            $this->Regelzyklus(); // sofort reagieren, nicht bis zum nächsten Timer-Tick warten
            return;
        }

        throw new Exception('Invalid Ident: ' . $Ident);
    }

    /**
     * Kernlogik: Überschuss berechnen, Zielstrom je nach Modus bestimmen, mit Hysterese
     * (außer bei festen Modi) an die Wallbox senden. Läuft per Timer alle ZyklusSekunden,
     * zusätzlich manuell über den Button im Formular oder sofort nach einem Moduswechsel.
     */
    public function Regelzyklus(): void
    {
        $vidNetz          = $this->ReadPropertyInteger('NetzsaldoVariable');
        $vidLadeleistung   = $this->ReadPropertyInteger('LadeleistungVariable');
        $vidLadestromSoll = $this->ReadPropertyInteger('LadestromSollVariable');

        if ($vidNetz <= 0 || !@IPS_VariableExists($vidNetz) || $vidLadestromSoll <= 0 || !@IPS_VariableExists($vidLadestromSoll)) {
            return;
        }

        $phasen              = $this->ReadPropertyInteger('Phasen');
        $spannung            = $this->ReadPropertyInteger('Spannung');
        $minAmpere           = $this->ReadPropertyInteger('MinAmpere');
        $maxAmpere           = $this->ReadPropertyInteger('MaxAmpere');
        $halteVerzoegerungS  = $this->ReadPropertyInteger('HalteVerzoegerungS');

        $netzRoh         = GetValue($vidNetz);
        $ladeleistungIst = ($vidLadeleistung > 0 && @IPS_VariableExists($vidLadeleistung)) ? GetValue($vidLadeleistung) : 0;

        // Einspeisung (positiv = Strom fließt Richtung Netz, "Überschuss")
        $einspeisung = $this->ReadPropertyBoolean('EinspeisungIstPositiv') ? $netzRoh : -$netzRoh;

        // Die Wallbox zieht bereits Leistung, die sonst mit eingespeist würde - die zählt also
        // zusätzlich zum messbaren Überschuss dazu, um den GESAMT verfügbaren Überschuss zu ermitteln
        $ueberschuss = $einspeisung + $ladeleistungIst;
        $this->SetValue('Ueberschuss', (int) $ueberschuss);

        $jetzt = time();

        // kWh-Tracking der Ladesession (Integration der Ist-Ladeleistung über die Zeit),
        // zusätzlich getrennt nach PV-gedecktem Anteil für die Prozent-Berechnung
        $letzterLaufTS = $this->ReadAttributeInteger('LetzterLaufTS');
        if ($letzterLaufTS > 0 && $ladeleistungIst > 0) {
            $deltaSekunden = $jetzt - $letzterLaufTS;
            $deltaKwh = ($ladeleistungIst * $deltaSekunden) / 3600000; // W * s -> kWh
            $this->SetValue('SessionKwh', $this->GetValue('SessionKwh') + $deltaKwh);

            // PV-gedeckter Teil der aktuellen Ladeleistung: min(Überschuss, Ladeleistung), nie negativ
            $pvAnteilWatt = max(0, min($ueberschuss, $ladeleistungIst));
            $deltaKwhSolar = ($pvAnteilWatt * $deltaSekunden) / 3600000;
            $this->SetValue('SessionKwhSolar', $this->GetValue('SessionKwhSolar') + $deltaKwhSolar);

            $sessionKwh = $this->GetValue('SessionKwh');
            $anteilProzent = $sessionKwh > 0 ? (int) round(($this->GetValue('SessionKwhSolar') / $sessionKwh) * 100) : 0;
            $this->SetValue('SessionSolarAnteil', $anteilProzent);
        }
        $this->WriteAttributeInteger('LetzterLaufTS', $jetzt);
        $this->SetValue('LetzterLauf', date('Y-m-d H:i:s'));

        // Zielstrom je nach Modus bestimmen
        $modus = $this->GetValue('Modus');
        $sofortWirksam = true;

        switch ($modus) {
            case 0: // Aus - siehe Sicherheitsnetz unten, warum trotzdem MinAmpere gesendet wird
                $berechneterAmpere = $minAmpere;
                break;
            case 1: // Minimal, fest
                $berechneterAmpere = $minAmpere;
                break;
            case 3: // Maximal, fest
                $berechneterAmpere = $maxAmpere;
                break;
            case 2: // Minimal mit Überschuss - startet immer bei MinAmpere, regelt nur nach oben hoch
            default:
                $sofortWirksam = false;
                $zielAmpereRoh = (int) floor($ueberschuss / ($phasen * $spannung));
                $berechneterAmpere = max($minAmpere, min($zielAmpereRoh, $maxAmpere));
                break;
        }

        // Sicherheitsnetz: unabhängig vom Modus NIE unter das Minimum (insbesondere nie 0) an die
        // Wallbox senden - viele Wallboxen (u.a. Heidelberg Energy Control) werten eine
        // Stromvorgabe von 0 als Kommunikationsfehler, nicht als "Aus".
        if ($berechneterAmpere < $minAmpere) {
            $berechneterAmpere = $minAmpere;
        }

        // Hysterese: im Modus "Minimal mit Überschuss" erst übernehmen, wenn der Zielstrom
        // HalteVerzoegerungS Sekunden stabil ist. Feste Modi (0,1,3) wirken immer sofort.
        $aktuellerAmpere = $this->GetValue('LadestromIst');

        if ($this->ReadAttributeInteger('StufeSeitTS') == 0) {
            $this->WriteAttributeInteger('StufeSeitTS', $jetzt);
        }

        if ($berechneterAmpere != $this->ReadAttributeInteger('NeuerAmpereIntern')) {
            $this->WriteAttributeInteger('NeuerAmpereIntern', $berechneterAmpere);
            $this->WriteAttributeInteger('StufeSeitTS', $jetzt);
        }

        $stabilSeit = $jetzt - $this->ReadAttributeInteger('StufeSeitTS');
        $zielIstStabil = $sofortWirksam || ($stabilSeit >= $halteVerzoegerungS);

        if ($berechneterAmpere != $aktuellerAmpere && $zielIstStabil) {
            RequestAction($vidLadestromSoll, $berechneterAmpere);
            $this->SetValue('LadestromIst', $berechneterAmpere);

            if ($aktuellerAmpere == 0 && $berechneterAmpere > 0) {
                $this->SetValue('Ladestart', date('Y-m-d H:i:s'));
                $this->SetValue('SessionKwh', 0.0);
                $this->SetValue('SessionKwhSolar', 0.0);
                $this->SetValue('SessionSolarAnteil', 0);
            }
        }

        // Status unabhängig von der Hysterese bei JEDEM Zyklus aktualisieren, sonst bleibt er
        // z.B. nach einem Moduswechsel auf "Aus" hängen, falls sich der gesendete Ladestrom
        // dabei zufällig nicht ändert (z.B. weil vorher schon MinAmpere aktiv war).
        $this->SetValue('Ladestufe', $modus == 0 ? 0 : ($berechneterAmpere >= $maxAmpere ? 2 : 1));
    }
}
