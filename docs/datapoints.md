### bekannte Datenpunkte

| Datenpunkt | Bedeutung | Variablentyp | Variablenprofil |
| :--------- | :-------- | :----------- | :-------------- |
| /dhwCircuits/dhw1/actualTemp | Warmwasser-Temperatur - Ist | Float | BuderusKM200.Celsius |
| /dhwCircuits/dhw1/currentSetpoint | Warmwasser-Temperatur - Soll | Float | BuderusKM200.Celsius |
| /dhwCircuits/dhw1/temperatureLevels/high | Warmwasser-Temperatur - Soll | Float | BuderusKM200.Celsius |
| /gateway/DateTime | Systemzeit | Integer | ~UnixTimestamp |
| /gateway/update/status | Update-Status | String | |
| /gateway/versionFirmware | Firmware-Version | String | |
| /gateway/versionHardware | Hardware-Version | String | |
| /heatingCircuits/hc1/currentRoomSetpoint | Raum-Temperatur - Soll | Float | BuderusKM200.Celsius |
| /heatingCircuits/hc1/manualRoomSetpointg | Raum-Temperatur - Soll | Float | BuderusKM200.Celsius |
| /heatingCircuits/hc1/operationModeg | Heizmodus | Integer | BuderusKM200.Hc_OperationMode |
| /heatingCircuits/hc1/pumpModulation | Heizkkreislauf-Pumpleistung | Float | BuderusKM200.Percent |
| /heatingCircuits/hc1/status | Heizkreislauf-Status | Boolean | BuderusKM200.Status |
| /heatingCircuits/hc1/temperatureLevels/comfort2g | Temperaturnivea Heizen | Float | BuderusKM200.Celsius |
| /heatingCircuits/hc1/temperatureLevels/ecog | Temperaturnivea Absenken | Float | BuderusKM200.Celsius |
| /heatSources/actualModulation | aktuelle Leistung | Float | BuderusKM200.Percent |
| /heatSources/actualPower | aktuelle Leistung | Float | BuderusKM200.kW |
| /heatSources/energyMonitoring/consumptiong | Float | BuderusKM200.kWh |
| /heatSources/energyMonitoring/startDateTime | Integer | ~UnixTimestamp |
| /heatSources/flameStatus | Brenner | Boolean | BuderusKM200.OnOff |
| /heatSources/hs1/actualModulation | aktuelle Leistung | Float | BuderusKM200.Percent |
| /heatSources/hs1/actualPower | aktuelle Leistung | Float | BuderusKM200.kW |
| /heatSources/hs1/flameStatus | Brenner | Boolean | BuderusKM200.OnOff |
| /heatSources/hs1/type | Typ | String | |
| /heatSources/numberOfStarts | Anzahl der Zündungen | Integer | |
| /heatSources/workingTime/centralHeating | Heizzeit | Integer | BuderusKM200.min |
| /heatSources/workingTime/totalSystem | Betriebszeit | Integer | BuderusKM200.min |
| /solarCircuits/sc1/collectorTemperature | Solarkollektor-Temperatur | Float | BuderusKM200.Celsius |
| /solarCircuits/sc1/dhwTankTemperature | untere Speicher-Temperatur | Float | BuderusKM200.Celsius |
| /solarCircuits/sc1/solarYield | Solarkollektor-Ertrag | Float | BuderusKM200.Wh |
| /solarCircuits/sc1/status | Solarkollektor-Status | Boolean | BuderusKM200.Status |
| /system/brand | Hersteller | String | |
| /system/bus | Bus-Typ | String | |
| /system/healthStatus | Status | Integer | BuderusKM200.HealthStatus |
| /system/sensors/temperatures/chimney | Schornstein-Temperatur | Float | BuderusKM200.Celsius |
| /system/sensors/temperatures/hotWater_t2 | Warmwasser-Temperatur - Ist | Float | BuderusKM200.Celsius |
| /system/sensors/temperatures/outdoor_t1 | Aussentemperatur | Float | BuderusKM200.Celsius |
| /system/sensors/temperatures/return | Rücklauf-Temperatur | Float | BuderusKM200.Celsius |
| /system/sensors/temperatures/supply_t1 | Raum-Vorlauftemperatur - Ist | Float | BuderusKM200.Celsius |
| /system/systemType | System-Typ | String | |

Anmerkung: die mit **1** bezeichneten Gruppen (also _hc1_, _hs1_, _sc1_) könne auch mehrfach vorkommen, die **1** wird entsprechend hochgezählt.
