# JuControlDevice
Instanz der Enthärtungsanlage.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

Stellt die Verbindung zur Judo Wasserenthärtungsanlage her. Die Verbindung läuft über die API des Hersteller (myjudo) mittels Benutzername und Passwort. Aktuell werden folgende Gerätetypen unterstützt:en kann es zu Fehlfunktionen kommen.
* Judo i-soft SAFE+
* Judo i-soft plus

### 2. Voraussetzungen

- IP-Symcon ab Version 5.5

### 3. Software-Installation

Über den Module Store das 'JuControlDevice'-Modul installieren.

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'JuControlDevice'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

| Eigenschaft            | Bezeichnung     		    | Beschreibung                                                                                             |
|------------------------|-----------------------|----------------------------------------------------------------------------------------------------------|
| Username               | Benutzername        	 | Benutzername des myjudo-Accounts                                                                         |
| Password               | Passwort      		      | zugehöriges Benutzerpasswort                                                                             |
| DeviceType             | Gerätetyp		           | Auswahl des Gerätetyps                                                                                   |
| SerialNumber | Gerätenummer		     | Angabe der Gerätenummer. Falls keine Gerätenummer angegeben ist, wird das erste gefundene Gerät genommen |
| RefreshRate            | Refreshrate		         | Aktualisierungsintervall in Sekunden                                                                     |

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

| Default Name		                 | Ident | Typ     	 | Beschreibung                    |
|--------------------------------|-------|-----------|---------------------------------|
| Geräte-ID		                    |       | String	   | Geräte-ID |
| Geräte-Typ		                   |       | String	   | Geräte-Typ |
| Status			                      |       | String	   | Aktueller Status |
| Seriennummer		                 |       | String	   | Seriennummer der Anlage |
| Ziel-Wasserhärte	              |       | Integer	  | Aktuell eingestellte Zielwasserhärte |
| Ist-Wasserhärte	               |       | Float		   | Wasserhärte eingangseitig (vom Versorger) |
| Füllstand Salz	                |       | Integer	  | Aktueller Füllstand des Salzbehälters |
| Aktueller Durchfluss           |       | Integer	  | Aktueller Wasserdurchfluss |
| Batteriezustand Notstrommodul  |       | Integer	  | Zustand der Notstromversorgung |
| Aktive Wasserszene	            |       | Integer	  | Aktiver Wasserszene |
| SW-Version			                  |       | String	   | Sofware Version Gerätesteuerung |
| HW-Version			                  |       | String	   | Hardware Version Gerätesteuerung |
| CCU-Version			                 |       | String	   | CCU Version Gerätesteuerung |
| Tage bis zur Wartung	          |       | Integer	  | Anzahl der Tage bis zur nächsten Wartung |
| Notstrommodul verbaut          |       | String	   | Notstrommodul verbaut ja/nein |
| Gesamt-Durchfluss	             |       | Integer	  | Gesamt-Durchfluss der Anlage seit Inbetriebnahme |
| Gesamt-Regenerationen          |       | Integer	  | Anzahl der durchgeführten Regenerationen |
| Gesamt-Wartungen		             |       | Integer	  | Anzahl der durchgeführten Wartungen |
| Szenen-Wasserhärte Waschen     |       | Integer	  | Ziel-Wasserhärte der Wasserszene "Waschen" |
| Szenen-Wasserhärte Heizung     |       | Integer	  | Ziel-Wasserhärte der Wasserszene "Heizung" |
| Szenen-Wasserhärte Bewässerung |       | Integer	  | Ziel-Wasserhärte der Wasserszene "Bewässerung" |
| Szenen-Wasserhärte Duschen     |       | Integer	  | Ziel-Wasserhärte der Wasserszene "Duschen" |
| Restlaufzeit Szene			          |       | Integer	  | Aktuelle Restlaufzeit der Wasserszene |
| Szenen-Wasserhärte Normal		    |       | Integer	  | Ziel-Wasserhärte im Normalbetrieb |


#### Profile

| Name | Typ |
|------|-----|
| JCD.Days | Integer |
| JCD.Liter| Integer |
| JCD.lph | Integer |
| JCD.Minutes | Integer |
| JCD.dH_int | Integer |
| JCD.Hours | Integer |
| JCD.Minutes.WSMaxPeriodOfUse | Integer |
| JCD.Liters.WSMaxQuantity | Integer |
| JCD.lph.WSMaxWaterFlow | Integer |
| JCD.Waterscene | Integer |
| JCD.WSHolidayMode | Integer |
| JCD.kg | Integer |
| JCD.dH_float | Float |
| JCD.NoYes | Boolean |

### 6. WebFront

Anzeige der aktuellen Betriebsdaten der Enthärtungsanlage und Möglichkeit Betriebsparameter (bspw. Wasserszene, Resthärten, ...) zu verändern (zur Zeit nur i-soft SAFE+).

### 7. PHP-Befehlsreferenz

`boolean JCD_TestConnection(integer $InstanzID);`
Testet die Verbindung zum Cloud-Service von Judo.

Beispiel:
`JCD_TestConnection(12345);`

`boolean JCD_RefreshData(integer $InstanzID);`
Aktualisiert die Daten der Anlage manuell.

Beispiel:
`JCD_RefreshData(12345);`

### 8. Version-History
20.01.2023
V1.3
- Unterstützung mehrerer angemeldeter Geräte
- Unterstützung des Gerätetyps 'i-soft plus'

10.12.2022
V1.2
- Wasserszenen-Dauer einstellbar
- Einschalten der Regeneration ermöglicht
- Robustheit bei unvollständigen Antworten erhöht

02.12.2022
V1.1 
- Code cleanup; Translations added

30.11.2022
V1.0
- Initial Release for store