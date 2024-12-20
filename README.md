# Lichtautomat (Light Automat)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.4-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-7.2.20241220-orange.svg?style=flat-square)](https://github.com/Wilkware/LightAutomat)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/LightAutomat/style.yml?branch=main&label=CheckStyle&style=flat-square)](https://github.com/Wilkware/LightAutomat/actions)

Das Modul Lichtautomat (Light Automat) überwacht und schaltet das Licht automatisch nach einer bestimmten Zeit wieder aus.

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#user-content-5-statusvariablen-und-profile)
6. [Visualisierung](#user-content-6-visualisierung)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

* Überwacht und schaltet das Licht(er) automatisch nach einer bestimmten Zeit wieder aus.
* Bei Variablenänderung der Statusvariable(n) wird ein Timer gestartet.
* Nach eingestellter Zeit wird der Staus wieder zurückgestellt ("STATE" = flase).
* Sollte das Licht schon vorher manuell aus geschalten worden sein, wird der Timer deaktiviert.
* Zusätzlich bzw. ausschließlich kann ein Script ausgeführt werden.
* Möglichkeit des manuellen Dauerbetriebes schaltbar über eine boolesche Variable, wenn __true__ wird kein Timer gestartet.
* Hinterlegung eines Wochenplans zum gezielten Aktivieren bzw. Deaktivierung des Automaten.
* Berücksichtigung von Bewegungsmelder, wenn dieser aktiv ist wird der Timer immer wieder erneuert.
* Möglichkeit der Steuerung der Wartezeit über eigene Laufzeit-Variable (z.B. via WebFront).
* Start der Wartezeit bei Aktivierung des Automaten via Wochenplan (Übergang Inaktiv zu Aktiv).

### 2. Voraussetzungen

* IP-Symcon ab Version 6.4

### 3. Installation

* Über den Modul Store das Modul _Lichtautomat_ installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/LightAutomat` oder `git://github.com/Wilkware/LightAutomat.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter 'Instanz hinzufügen' ist das _Lichtautomat_-Modul unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> Geräte ...

Name                 | Beschreibung
-------------------- | ---------------------------------
Geräteanzahl         | 'Ein Gerät' oder 'Mehrere Geräte' - Umschalten zwischen Variablenauswahl und Variablenliste.
Schaltvariable*      | Zielvariable, die bei hinreichender Bedingung geschalten wird (true).
Schaltvariablen*     | Zielvariablen, welche alle bei hinreichender Bedingung geschalten werden (true).
Bewegungsvariable    | Statusvariable eines Bewegungsmelders (true = Anwesend; false = Abwesend).

(*) abhängig von Auswahl der Geräteanzahl

> Zeitsteuerung ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Zeiteinheit                                      | Bestimmt ob Dauer in Sekunden, Minuten, Stunden oder Uhrzeit (freie Zeitwahl) ausgewertet werden soll.
Einschaltdauer                                   | Zeitdauer, bis das Licht(Aktor) wieder ausgeschaltet werden soll. Wird bei eigner Variable für Einschaltdauer (siehe Erweiterte Einstellungen) als Vorgabewert/Initialwert benutzt.
Zeitplan                                         | Wochenprogram, welches den Lichtautomaten zeitgesteuert aktiviert bzw. deaktiviert.
ZEITPLAN HINZUFÜGEN                              | Button zum Erzeugen und Hinzufügen eines Wochenprogrammes.

> Erweiterte Einstellungen ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Gleichzeitiges Ausführen eines Scriptes          | Auswahl eines Skriptes, welches zusätzlich ausgeführt werden soll (IPS_ExecScript).
Nur Script ausführen - kein Ausschaltvorgang     | Schalter, ob nur das Script ausgeführt werden soll.
Starte Einschaltdauer bei eingeschaltem Licht und Aktivierung über Schaltplan | Schalter, ob nach Aktivierung über Wochenplan der Automat starten soll.
Variable für Einstellung der Einschaltdauer anlegen | Schalter, ob eine Statusvariable für Einschaltdauer angelegt werden soll.
Variable für Aktivierung des Dauerbetriebes anlegen | Schalter, ob eine Statusvariable für Dauerbetrieb angelegt werden soll.

### 5. Statusvariablen und Profile

Die Statusvariablen werden unter Berücksichtigung der erweiterten Einstellungen angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                 | Typ          | Beschreibung
-------------------- | ------------ | ----------------
Dauerbetrieb         | Boolean      | Ein- und Ausschalten des Dauerbetriebes (z.B. bei Besuch oder Party's)
Einschaltdauer       | Integer      | Dauer der Wartezeit in Abhängigkeit der eingestellten Zeiteinheit
Zeitplan             | (Wochenplan) | Einstellen der Zeitpunkte für Aktivieren bzw. Deaktivieren des Automaten

Folgende Profile werden angelegt:

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
TLA.Seconds          | Integer   | Zeitraum von 1 bis 59 Sekunden
TLA.Minutes          | Integer   | Zeitraum von 1 bis 59 Minuten
TLA.Hours            | Integer   | Zeitraum von 1 bis 23 Stunden

### 6. Visualisierung

Es ist keine weitere Steuerung oder gesonderte Darstellung integriert.  
Der Dauerbetrieb kann über die Statusvariable "Dauerbetrieb" in der Visualisierung realsiert werden.  
Die Wartezeit kann auch über die Statusvariable "Einschaltdauer" so realisiert werden.

### 7. PHP-Befehlsreferenz

Ein direkter Aufruf von öffentlichen Funktionen ist nicht notwendig!

### 8. Versionshistorie

v7.2.20241220

* _NEW_: Auswahl ob ein einzelnes oder mehrere Geräte/Lampen geschalten werden sollen
* _FIX_: Alias-Namen gelöscht(Treppenautomat)
* _FIX_: Dokumentation vereinheitlicht

v7.1.20240920

* _FIX_: Modulnamen bzw. Alias-Namen
* _FIX_: Auswertung der verknüpften Variablen/Instanzen korriegiert

v7.0.20240908

* _NEU_: Kompatibilität auf IPS 6.4 hoch gesetzt
* _FIX_: Bibliotheks- bzw. Modulinfos vereinheitlicht
* _FIX_: Namensnennung und Repo vereinheitlicht
* _FIX_: Update Style-Checks
* _FIX_: Übersetzungen überarbeitet und verbessert
* _FIX_: Dokumentation vereinheitlicht

v6.0.20220401

* _NEU_: Kompatibilität auf IPS 6.0 hoch gesetzt
* _NEU_: Konfigurationsdialog überarbeitet (v6 Möglichkeiten genutzt)
* _NEU_: Konfiguration der Zeitsteuerung überarbeitet
* _NEU_: Einschaltdauer kann über eine frei wählbare Uhrzeit eingestellt werden (Kombination von Stunden, Minuten und Sekunden)
* _NEU_: Eine reine boolesche Schaltvariable wird automatisch erkannt
* _NEU_: Referenzieren der Gerätevariablen hinzugefügt (sicheres Löschen)
* _FIX_: Public Funktions `TLA_Trigger`, `TLA_Schedule` und `TLA_CreateSchedule` wegen neuer Prozessverarbeitung entfernt
* _FIX_: Interne Bibliotheken erweitert und vereinheitlicht
* _FIX_: Markdown der Dokumentation überarbeitet

v5.0.20210502

* _NEU_: Umstellung auf Statusvariablen für Einschaltdauer und Dauerbetrieb
* _NEU_: Wartezeit jetzt frei konfigurierbar (Sekunden, Minuten oder Stunden)
* _NEU_: Check ob Licht nach Aktivierung des Automaten über Wochenplan ausgeschalten werden soll
* _FIX_: Funktion "TLA_Duration" entfernt (wegen Nutzung von IPS_SetProperty/ IPS_ApplyChanges)
* _FIX_: Konfigurationsformular vereinheitlicht bzw. vereinfacht
* _FIX_: Interne Bibliotheken überarbeitet

v4.0.20200421

* _NEU_: Zeitplan hinzugefügt
* _NEU_: Unterstützung für die Erstellung eines Wochenplans
* _FIX_: Interne Bibliotheken überarbeitet
* _FIX_: Dokumentation überarbeitet

v3.3.20190818

* _NEU_: Umstellung für Module Store
* _FIX_: Dokumentation überarbeitet

v3.2.20170322

* _FIX_: Anpassungen für IPS Version 5

v3.1.20170120

* _FIX_: Korrekte Auswertung der Schaltvariable.

v3.0.20170109

* _NEU_: Dauerbetrieb miitels hinterlegter booleschen Variable, wenn _true_ wird kein Timer gestartet.
* _NEU_: Modul mit Bewegungsmelder, wenn dieser aktiv ist wird der Timer immer wieder erneuert.
* _NEU_: Über die Funktion _TLA_Duration(id, minuten)_ kann die Wartezeit via Skript (WebFront) gesetzt werden.

v2.0.20170101

* _FIX_: Umstellung auf Nachrichten (RegisterMessage/MessageSink)
* _NEU_: Erweiterung zum Ausführen eines Skriptes

v1.0.20161220

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
