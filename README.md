# IPSymconBuderusKM200

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Module-Version](https://img.shields.io/badge/Modul_Version-1.0-blue.svg)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://github.styleci.io/repos/175371809/shield?branch=master)](https://github.styleci.io/repos/xxxx)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

## 2. Voraussetzungen

 - IP-Symcon ab Version 5
 - Buderus mit KM200

## 3. Installation

### IP-Symcon

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconBuderusKM200.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

In IP-Symcon nun _Instanz hinzufügen_ (_CTRL+1_) auswählen unter der Kategorie, unter der man die Instanz hinzufügen will, und Hersteller _Buderus_ und als Gerät _KM200_ auswählen.

## 4. Funktionsreferenz

## 5. Konfiguration

### Variablen

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :-----------------------------------------: |
| Instanz ist deaktiviert   | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Host                      | string   |              | KM200-Server |
| Port                      | integer  | 80           | HTTP-Port |
|                           |          |              | |
| Key                       | string   |              | AES-Key |
|                           |          |              | |
| Felder                    |          |              | Tabelle zur Angabe der auszulesenden Datenpunkte |
| Werte konvertieren        |          |              | |
|                           |          |              | |
| Aktualisiere Status ...   | integer  | 60           | Aktualisierungsintervall, Angabe in Sekunden |

- AES-Key:<br>
Key zum Zugriff auf die KEM200, den man mittels dieses [AES-Key-Generator](https://ssl-account.com/km200.andreashahn.info) ermitteln kann.

- Felder:<br>
Liste der zu übernehmenden Datenpunkte und ANgabe des Datentyps der Variable. Variablen, die aus dieser Liste gelöscht werden, werden gelöscht.
Der Ident dieser erzeugten Variablen ist wiefolgt ausgebaut: _DP_ + Bezeichung des Datenpunkts, die **/** isnd ersetzt durch **_**.

- Werte konvertieren:<br>
mit diesen Scripten kann man Werte zu konvertieren

Ein passendes Code-Fragment für ein Script (siehe auch _docs_:

```
<?php

$datapoint = $_IPS['datapoint'];
$value = $_IPS['value'];

$ret = '';
if ($datapoint == '/heatSources/workingTime/totalSystem') {
    $m = $value;
    if ($m > 60) {
        $h = floor($m / 60);
        $m = $m % 60;
        $ret .= sprintf('%dh', $h);
    }
    if ($m > 0) {
        $ret .= sprintf('%dm', $m);
    }
}

echo $ret;
```
Wandelt die Betriebszeit (in Minuten) in einer Darstellung als _<Stunden>d<Minuten>m_, Variable dann als String.

#### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :----------------------------: |
| Zugriff prüfen               | Zugriff auf prüfen |
| Aktualisiere Status          | aktuellen Status holen |
| Datenpunkt-Tabelle           | Tabelle der verfügbafren Datenpunkte erzeugen |

- Datenpunkt-Tabelle:<br>
liest alle Datenpunkte der Heizung ein und legt diese csv-Datei als Medien-Objekt unterhalb der Instanz ab.

### Variablenprofile

* Boolean<br>
BuderusKM200.OnOff [Off|On], BuderusKM200.Status [Inactive|Active]. BuderusKM200.Charge [Stop|Start]

* Integer<br>
BuderusKM200.min, BuderusKM200.HealthStatus [Error|Maintenance|Ok], BuderusKM200.Hc_OperationMode [automatic|manual], BuderusKM200.Dwh_OperationMode [Off|High|HC-Program|Own program]

* Float<br>
BuderusKM200.bar, BuderusKM200.Celsius, BuderusKM200.kW, BuderusKM200.kWh, BuderusKM200.l_min, BuderusKM200.Pascal, BuderusKM200.Percent, BuderusKM200.Wh

## 6. Anhang

GUIDs

- Modul: `{6AE8F5B3-93AC-428E-9EDB-B37D46B708F1}`
- Instanzen:
  - BuderusKM200: `{3A2FE2B9-EB88-4B14-B144-2A3839A761CA}`

Folgende Datenpunkte meine ich identifiziert zu haben: siehe `docs/datapoints.csv`

## 7. Versions-Historie

- 1.0 @ 28.03.2019 19:07<br>
  Initiale Version
