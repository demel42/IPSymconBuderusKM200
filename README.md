# IPSymconBuderusKM200

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.3+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

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

Das Modul dient zu Anbindung einer Buderus-Heizung mit einem KM200-Kommunikationsmodul. Es können alle Datenpunkte abgerufen und die beschreibbaren Datenpunkte geändert werden.

Diese Modul basiert auf den Arbeiten von [Slash](https://www.symcon.de/forum/members/1206-Slash), siehe auch [Buderus Logamatic Web KM200](https://www.symcon.de/forum/threads/25188-Buderus-Logamatic-Web-KM200) und
[Buderus Logamatic Web KM200 Reloaded](https://www.symcon.de/forum/threads/25211-Buderus-Logamatic-Web-KM200-Reloaded).

## 2. Voraussetzungen

 - IP-Symcon ab Version 5.3
 - Buderus mit KM200<br>
Nach Prüfung durch [danam33](https://www.symcon.de/forum/members/3940-danam33) funktioniert das Modul auch mit Heizungen von Junkers mit dem Modul **Junkers MB Lan** funktionsfähig (siehe [hier](https://www.symcon.de/forum/threads/25211-Buderus-Logamatic-Web-KM200-Reloaded?p=414018#post414018)).

## 3. Installation

### IP-Symcon

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconBuderusKM200.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

In IP-Symcon nun _Instanz hinzufügen_ (_CTRL+1_) auswählen unter der Kategorie, unter der man die Instanz hinzufügen will, und Hersteller _Buderus_ und als Gerät _KM200_ auswählen.

### Einrichtung der Instanz

Nach Eingabe von _Host_ und _Key_ in der Konfigurationsmaske kann man mit die Funktion _Zugriff prüfen_ verwenden, um ein paar Basisdaten abzurufen.

Leider gibt es sehr viele Datenpunkte, deren Existenz bzw. deren Befüllung von der individuellen Konfiguration abhängen.
Daher macht es Sinn, als erstes _Datenpunkt-Tabelle_ auszulösen. Hiermit werden alle verfügbaren Datenpunkte ermittelt und im csv-Format als Medienobjekt unterhalb der Instanz gespeichert.<br>
Das dauert etwas, weil einige Hundert Abfragen gemacht werden. Diese Tabelle sollte man sich in einer geeigneten Programm öffnen und ansehen.<br>
Folgende Datenpunkte meine ich identifiziert zu haben: siehe [hier](docs/datapoints.md), Ergänzungen sind ausdrücklich erwünscht.

In dem Konfigurationsformular im Bereich _Felder_ kann man nun die Datenpunkte eingeben, die man haben möchte und zu dem Datenpunkt den gewünschten Variablentyp.<br>
Hierbei liefern die o.g. Tabellen Informationen
1. welcher Datentyp von _KM200_ geliefert wird 
2. welcher Variablentyp im IPS  möglicherweise der sinnvollste ist.

Eine gewisse Konvertierung der Werte wird automatisch durchgeführt, so wird z.B. _/gateway/DateTime_ automatisch in ein Timestamp umgewandelt, wenn der Variablentyp _Integer_ ist; ist es _String_ wird der Wert unverändert übernommen.
Eine Umsetzung wird ebenfalls bei allen Variablen gemacht, bei denen in der Spalte _Wertemenge_ eine Wertemenge angegeben ist und der Variablentyp _Integer_ (bzw. _Boolean_). Siehe hierzu auch die vordefinierten Datentypen.
Sind weitergehenden Konvertierungen des Datentyps erwünscht, kann man optional ein Script einbinden (siehe unten).

Die Variablen sind so benannt wie der Datenpunkt, es müsste also sinnvollerweise die Bezeichnung und der Datentyp angepasst werden.

Wichtig: wenn man einen Datenpunkt wieder aus der Liste æntfernt, wird die dazugehörige Variable gelöscht!

## 4. Funktionsreferenz

`BuderusKEM200_UpdateData(int $InstanzID)`

ruft die Daten vom KEM200 ab; für jeden Datenpunkt muss ein separater HTTP-Call durchgeführt werden.
Der Abruf wird automatisch zyklisch durch die Instanz durchgeführt im Abstand wie in der Konfiguration angegeben.

`BuderusKEM200_DatapointSheet(int $InstanzID)`

erzeugt das Medien-Objekt _Datenpunkt-Tabelle_.

`BuderusKEM200_GetData(int $InstanzID, string $datapoint)`

ruft die Daten eines einzelnen Datenpunktes ab und liefert ein JSON-kodiester Objekt zurück.

`BuderusKEM200_SetData(int $InstanzID, string $datapoint, string $Value)`

setzt ein Datenobjekt auf den übergebenen Wert. Die Objekte, die beschreibbar sind, sind in der o.g. Tabelle der Datenpunkte gekennzeichet.

## 5. Konfiguration

### Variablen

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :------------------------------------------ |
| Instanz ist deaktiviert   | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Host                      | string   |              | KM200-Server |
| Port                      | integer  | 80           | HTTP-Port |
|                           |          |              | |
| Gateway-Passwort          | string   |              | Gateway-Passwort mit oder ohne **-** |
| privates Passwort         | string   |              | privater Schlüssel |
|                           |          |              | |
| Felder                    |          |              | Tabelle zur Angabe der auszulesenden Datenpunkte |
| Werte konvertieren        |          |              | |
|                           |          |              | |
| Aktualisiere Status ...   | integer  | 60           | Aktualisierungsintervall, Angabe in Sekunden |

- Felder:<br>
Liste der zu übernehmenden Datenpunkte und Angabe des Datentyps der Variable. Variablen, die aus dieser Liste gelöscht werden, werden gelöscht.
Der Ident dieser erzeugten Variablen ist wiefolgt ausgebaut: _DP_ + Bezeichung des Datenpunkts, die **/** isnd ersetzt durch **_**.

Anmerkung: der Datenpunkt _/notifications_ wird automatisch abgerufen und in einer Variablen vom Typ _~HTMLBox_ abgelegt.

- Werte konvertieren:<br>
mit diesen Scripten kann man Werte zu konvertieren

Ein passendes Code-Fragment für ein Script (siehe auch _docs/convert_script.php_):

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
| :--------------------------- | :----------------------------- |
| Zugriff prüfen               | Zugriff auf prüfen |
| Aktualisiere Status          | aktuellen Status holen |
| Datenpunkt-Tabelle           | Tabelle der verfügbafren Datenpunkte erzeugen |

- Datenpunkt-Tabelle:<br>
liest alle Datenpunkte der Heizung ein und legt diese csv-Datei als Medien-Objekt unterhalb der Instanz ab.

### Variablenprofile

* Boolean<br>
BuderusKM200.OnOff [Off|On],
BuderusKM200.Status [Inactive|Active],
BuderusKM200.Charge [Stop|Start]

* Integer<br>
BuderusKM200.min,
BuderusKM200.HealthStatus [Error|Maintenance|Ok],
BuderusKM200.Hc_OperationMode [automatic|manual],
BuderusKM200.Dwh_OperationMode [Off|High|HC-Program|Own program]

* Float<br>
BuderusKM200.bar,
BuderusKM200.Celsius,
BuderusKM200.kW,
BuderusKM200.kWh,
BuderusKM200.l_min,
BuderusKM200.Pascal,
BuderusKM200.Percent,
BuderusKM200.Wh

## 6. Anhang

GUIDs

- Modul: `{6AE8F5B3-93AC-428E-9EDB-B37D46B708F1}`
- Instanzen:
  - BuderusKM200: `{3A2FE2B9-EB88-4B14-B144-2A3839A761CA}`

## 7. Versions-Historie

- 1.11 @ 13.05.2020 17:42
  - mehr Debug zur Funktion 'SetData'

- 1.10 @ 06.01.2020 11:17
  - Nutzung von RegisterReference() für im Modul genutze Objekte (Scripte, Kategorien etc)
  - SetTimerInterval() erst nach KR_READY

- 1.9 @ 02.01.2020 13:28
  - Fix wegen Umstellung auf strict_types=1
  - Schreibfehler korrigiert

- 1.8 @ 30.12.2019 10:56
  - Anpassungen an IPS 5.3
    - Formular-Elemente: 'label' in 'caption' geändert

- 1.7 @ 09.12.2019 16:46
  - mehr Ausgabe zu der Funktion 'DatapoitSheet' (Tabelle der Datenpunkte)

- 1.6 @ 29.10.2019 10:21
  - Datenpunkt 'notifications' gegen fehlende Felder abgesichert

- 1.5 @ 13.10.2019 13:18
  - Anpassungen an IPS 5.2
    - IPS_SetVariableProfileValues(), IPS_SetVariableProfileDigits() nur bei INTEGER, FLOAT
    - Dokumentation-URL in module.json
  - Umstellung auf strict_types=1
  - Umstellung von StyleCI auf php-cs-fixer

- 1.4 @ 09.08.2019 14:32
  - Schreibfehler korrigiert

- 1.3 @ 16.06.2019 18:34
  - leeres Ergebnis eines HTTP-Request abfangen

- 1.2 @ 03.05.2019 14:14
  - Dateiname der Mediadatei "Buderus KM200 Datenpunkte" ist nun eindeutig
  - Abfrage des Status nun über GetStatus() mit Emulation für IPS < 5.1

- 1.1 @ 02.05.2019 11:56
  - Bugfix: Auswertung des HTTP-Port

- 1.0 @ 28.03.2019 19:07
  - Initiale Version
