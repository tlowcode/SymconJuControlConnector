# JuControlDevice
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Stellt die Verbindung zur Judo i-soft safe Wasserenthärtungsanlage her. Die Verbindung läuft über die API des Hersteller (myjudo) mittels Benutzername und Passwort. Aktuell wird nur eine Anlage pro Nutzeraccount unterstützt. Bei mehreren eingerichteten Anlagen kann es zu Fehlfunktionen kommen.

### 2. Voraussetzungen

- IP-Symcon ab Version 5.5

### 3. Software-Installation

* Über den Module Store das 'JuControlDevice'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'JuControlDevice'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

| Eigenschaft | Bezeichnung     		    | Beschreibung                         |
|-------------|-----------------------|--------------------------------------|
| Username    | Benutzername        	 | Benutzername des myjudo-Accounts     |
| Password    | Passwort      		      | zugehöriges Benutzerpasswort         |
| RefreshRate | Refreshrate		         | Aktualisierungsintervall in Sekunden |

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

| Default Name		                 | Ident | Typ     	 | Beschreibung                    |
|--------------------------------|-------|-----------|---------------------------------|
| Geräte-ID		                    |       | String	   |                                 |
| Geräte-Typ		                   |       | String	   |                                 |
| Status			                      |       | String	   |                                 |
| Seriennummer		                 |       | String	   |                                 |
| Ziel-Wasserhärte	              |       | Integer	  |                                 |
| Ist-Wasserhärte	               |       | Float		   |                                 |
| Füllstand Salz	                |       | Integer	  |                                 |
| Aktueller Durchfluss           |       | Integer	  |                                 |
| Batteriezustand Notstrommodul  |       | Integer	  |                                 |
| Aktive Wasserszene	            |       | Integer	  |                                 |
| SW-Version			                  |       | String	   | Sofware Version Gerätesteuerung |
| HW-Version			                  |       | String	   |                                 |
| CCU-Version			                 |       | String	   |                                 |
| Tage bis zur Wartung	          |       | Integer	  |                                 |
| Notstrommodul verbaut          |       | String	   |                                 |
| Gesamt-Durchfluss	             |       | Integer	  |                                 |
| Gesamt-Regenerationen          |       | Integer	  |                                 |
| Gesamt-Wartungen		             |       | Integer	  |                                 |
| Szenen-Wasserhärte Waschen     |       | Integer	  |                                 |
| Szenen-Wasserhärte Heizung     |       | Integer	  |                                 |
| Szenen-Wasserhärte Bewässerung |       | Integer	  |                                 |
| Szenen-Wasserhärte Duschen 		  |       | Integer	  |                                 |
| Restlaufzeit Szene			          |       | Integer	  |                                 |
| Szenen-Wasserhärte Normal		    |       | Integer	  |                                 |

#### Profile

| Name | Typ |
|------|-----|
       |
       |

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

`boolean JCD_BeispielFunktion(integer $InstanzID);`
Erklärung der Funktion.

Beispiel:
`JCD_BeispielFunktion(12345);`
