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
|                           |          |              | |
| Aktualisiere Status ...   | integer  | 60           | Aktualisierungsintervall, Angabe in Sekunden |

#### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :----------------------------: |
| Zugriff prüfen               | Zugriff auf prüfen |
| Aktualisiere Status          | aktuellen Status holen |

## 6. Anhang

GUIDs

- Modul: `{6AE8F5B3-93AC-428E-9EDB-B37D46B708F1}`
- Instanzen:
  - BuderusKM200: `{3A2FE2B9-EB88-4B14-B144-2A3839A761CA}`

## 7. Versions-Historie

- 1.0 @ 28.03.2019 19:07<br>
  Initiale Version
