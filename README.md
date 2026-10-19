# 💡 Lichtautomat (Light Automat)

[![Home](https://img.shields.io/badge/Home-wilkware.de-0b1830.svg?style=flat-square)](https://wilkware.de/module/lichtautomat/)
[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-8.1-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-8.0.20261019-orange.svg?style=flat-square)](https://github.com/Wilkware/LightAutomat)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/LightAutomat/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/LightAutomat/actions)

Das Modul Lichtautomat (Light Automat) überwacht und schaltet das Licht automatisch nach einer bestimmten Zeit wieder aus.

## Inhaltsverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichtung](#user-content-4-einrichtung)
5. [Statusvariablen](#user-content-5-statusvariablen)
6. [Darstellungen](#user-content-6-darstellungen)
7. [Visualisierung](#user-content-7-visualisierung)
8. [Befehlsreferenz](#user-content-8-befehlsreferenz)
9. [Versionshistorie](#user-content-9-versionshistorie)

### 1. Funktionsumfang

* Überwacht ein oder mehrere Lichter (boolesche Schaltvariablen) und schaltet sie nach einer bestimmten Zeit automatisch wieder aus.
* Beim Einschalten einer Schaltvariable wird ein Timer gestartet.
* Nach Ablauf der eingestellten Zeit werden alle noch eingeschalteten Geräte ausgeschaltet.
* Werden alle Geräte vorher manuell ausgeschaltet, wird der Timer deaktiviert.
* Zusätzlich bzw. ausschließlich kann ein Skript ausgeführt werden.
* Manueller Dauerbetrieb über eine schaltbare Statusvariable; ist er aktiv, wird kein Timer gestartet bzw. ein laufender Timer gestoppt.
* Hinterlegung eines Wochenplans zum gezielten Aktivieren bzw. Deaktivieren des Automaten.
* Berücksichtigung eines Bewegungsmelders: Solange Bewegung erkannt wird, wird nicht ausgeschaltet; endet die Bewegung, beginnt die Einschaltdauer neu.
* Steuerung der Einschaltdauer über eine eigene Statusvariable (z. B. über die Visualisierung).
* Optionale Anzeige des Ausschaltzeitpunktes als Statusvariable.
* Start der Einschaltdauer bei Aktivierung des Automaten über den Wochenplan (Übergang inaktiv zu aktiv).
* Diagnose über das Konfigurationsformular: zeigt alle Bedingungen und warum der Automat gerade schaltet bzw. nicht schaltet.

### 2. Voraussetzungen

* Symcon ab Version 8.1

### 3. Installation

* Über den Modul Store das Modul _Lichtautomat_ installieren.
* Alternativ über das Modul Control folgende URL hinzufügen.  
`https://github.com/Wilkware/LightAutomat` oder `git://github.com/Wilkware/LightAutomat.git`

### 4. Einrichtung

* Unter 'Instanz hinzufügen' ist das _Lichtautomat_-Modul unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> 💡 Geräte ...

Name                 | Beschreibung
-------------------- | ---------------------------------
Geräteanzahl         | 'Ein Gerät' oder 'Mehrere Geräte' - Umschalten zwischen Variablenauswahl und Variablenliste.
Schaltvariable*      | Boolesche Zielvariable, die nach Ablauf der Einschaltdauer ausgeschaltet wird.
Schaltvariablen*     | Boolesche Zielvariablen, welche alle nach Ablauf der Einschaltdauer ausgeschaltet werden. Die Spalte 'Zustand' zeigt, ob die Variable schaltbar ist.
Bewegungsvariable    | Boolesche Statusvariable eines Bewegungsmelders (true = Bewegung; false = keine Bewegung).

(*) abhängig von Auswahl der Geräteanzahl

> ⏱️ Zeitsteuerung ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Zeiteinheit                                      | Bestimmt ob die Dauer in Sekunden, Minuten, Stunden oder als Uhrzeit (freie Zeitwahl) ausgewertet werden soll.
Einschaltdauer                                   | Zeitdauer, bis das Licht (Aktor) wieder ausgeschaltet werden soll. Bei eigener Variable für die Einschaltdauer (siehe Erweiterte Einstellungen) wird der Wert beim Anlegen sowie bei jeder Änderung in der Konfiguration in die Variable übernommen; ein über die Visualisierung gesetzter Wert bleibt sonst erhalten.
Zeitplan                                         | Wochenplan, welcher den Lichtautomaten zeitgesteuert aktiviert bzw. deaktiviert.
ZEITPLAN HINZUFÜGEN                              | Button zum Erzeugen und Hinzufügen eines Wochenplans.

> ⚙️ Erweiterte Einstellungen ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Gleichzeitiges Ausführen eines Skriptes          | Auswahl eines Skriptes, welches zusätzlich ausgeführt werden soll. Im Skript stehen `$_IPS['VARIABLE']` (erste eingeschaltete Schaltvariable) und `$_IPS['INSTANCE']` (Lichtautomat) zur Verfügung.
Nur Skript ausführen - kein Ausschaltvorgang     | Schalter, ob nur das Skript ausgeführt werden soll.
Schaltvariablen ohne Aktion direkt setzen        | Schaltvariablen ohne Aktion werden direkt gesetzt (SetValue); nur für Variablen, die zu keiner Instanz gehören.
Starte Einschaltdauer bei eingeschaltetem Licht und Aktivierung über Wochenplan | Schalter, ob nach Aktivierung über den Wochenplan der Automat starten soll.
Variable für Einstellung der Einschaltdauer anlegen | Schalter, ob eine Statusvariable für die Einschaltdauer angelegt werden soll.
Variable für Aktivierung des Dauerbetriebes anlegen | Schalter, ob eine Statusvariable für den Dauerbetrieb angelegt werden soll.
Variable für Anzeige des Ausschaltzeitpunktes anlegen | Schalter, ob eine Statusvariable mit dem Ausschaltzeitpunkt angelegt werden soll.

__Hinweis:__ Bestehende Instanzen, die bisher eine Schaltvariable ohne Aktion verwendet haben, werden beim Update automatisch auf 'Schaltvariablen ohne Aktion direkt setzen' umgestellt.

Aktionsbereich:

> 🧪 Test ...

Aktion          | Beschreibung
--------------- | ---------------------------------
ZUSTAND PRÜFEN  | Zeigt alle Bedingungen auf einen Blick (Geräte, Einschaltdauer, Timer, Dauerbetrieb, Wochenplan, Bewegungsmelder, Skript) und als Ergebnis, was der Automat gerade tun würde bzw. warum nicht. Fehler werden rot, Bedingungen, die den Start verhindern (z. B. Dauerbetrieb oder inaktiver Wochenplan), gelb hinterlegt. Es wird dabei nichts geschaltet.

### 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.  
Die Variablen werden unter Berücksichtigung der erweiterten Einstellungen angelegt.

Ident                | Name                | Typ     | Beschreibung
-------------------- | ------------------- | ------- | ------------------------------
continuous_operation | Dauerbetrieb        | Boolean | Ein- und Ausschalten des Dauerbetriebes (z. B. bei Besuch oder Partys)
duty_cycle           | Einschaltdauer      | Integer | Dauer der Wartezeit in Abhängigkeit der eingestellten Zeiteinheit
switch_off_time      | Ausschaltzeitpunkt  | Integer | Zeitpunkt, zu dem das Licht ausgeschaltet wird (0 = kein Timer aktiv)

_Hinweis:_ Zusätzlich kann über den Button 'Zeitplan hinzufügen' ein Wochenplan (Ident `circuit_diagram`) angelegt werden, über den die Zeitpunkte für Aktivieren bzw. Deaktivieren des Automaten eingestellt werden.

### 6. Darstellungen

Die Darstellungen werden direkt an den Statusvariablen hinterlegt, es werden keine Profile angelegt.

Variable             | Darstellung    | Werte
-------------------- | -------------- | ------------------------------
Dauerbetrieb         | Schalter       | An / Aus
Einschaltdauer       | Schieberegler  | 1 – 59 Sekunden, 1 – 59 Minuten bzw. 1 – 23 Stunden (Schrittweite 1), je nach Zeiteinheit
Einschaltdauer       | Datum/Uhrzeit  | Uhrzeit (Stunden, Minuten, Sekunden), bei Zeiteinheit 'Uhrzeit'
Ausschaltzeitpunkt   | Datum/Uhrzeit  | Uhrzeit (Stunden, Minuten, Sekunden)

### 7. Visualisierung

Es ist keine eigene Kachel integriert, die Instanz wird über die Standard-Kachel mit ihren Statusvariablen dargestellt.  
Der Dauerbetrieb kann über die Statusvariable "Dauerbetrieb", die Wartezeit über die Statusvariable "Einschaltdauer" gesteuert werden.  
Die Statusvariable "Ausschaltzeitpunkt" zeigt an, wann das Licht ausgeschaltet wird.

### 8. Befehlsreferenz

Ein direkter Aufruf von öffentlichen Funktionen ist nicht notwendig!

### 9. Versionshistorie

v8.0.20261019

* _NEU_: Kompatibilität auf Symcon 8.1 angehoben, Code und interne Bibliotheken modernisiert
* _NEU_: Konfigurationsformular überarbeitet und vereinheitlicht (inkl. Versionsanzeige und Statuscodes)
* _NEU_: Option 'Schaltvariablen ohne Aktion direkt setzen' (nur Variablen, die zu keiner Instanz gehören), bestehende Instanzen werden automatisch migriert
* _NEU_: Optionale Statusvariable 'Ausschaltzeitpunkt'
* _NEU_: Bewegungsmelder startet die Einschaltdauer neu, sobald keine Bewegung mehr erkannt wird
* _NEU_: Nach Beenden des Dauerbetriebes startet die Einschaltdauer, wenn noch Licht eingeschaltet ist
* _NEU_: Aktionsbereich mit 'Zustand prüfen' zur Diagnose aller Bedingungen
* _NEU_: Ausgeführtes Skript erhält die auslösende Variable und die Instanz
* _FIX_: Start der Einschaltdauer bei Aktivierung über den Wochenplan funktionierte nicht
* _FIX_: Bei mehreren Geräten wurde der Timer schon beim Ausschalten eines einzelnen Gerätes gestoppt
* _FIX_: Aktivieren des Dauerbetriebes stoppt jetzt einen laufenden Timer
* _FIX_: Dauerbetrieb wird beim Übernehmen bzw. Neustart nicht mehr zurückgesetzt
* _FIX_: Schaltvariablen mit Darstellung statt Profil werden akzeptiert
* _FIX_: Uhrzeit als Einschaltdauer unabhängig von der Zeitzone
* _FIX_: Fehlende Variablen, Wochenpläne und Skripte abgesichert
* _FIX_: Übersetzungen und Dokumentation überarbeitet

v7.2.20241220

* _NEW_: Auswahl ob ein einzelnes oder mehrere Geräte/Lampen geschalten werden sollen
* _FIX_: Alias-Namen gelöscht(Treppenautomat)
* _FIX_: Dokumentation vereinheitlicht

v7.1.20240920

* _FIX_: Modulnamen bzw. Alias-Namen
* _FIX_: Auswertung der verknüpften Variablen/Instanzen korrigiert

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

* _NEU_: Dauerbetrieb mittels hinterlegter booleschen Variable, wenn _true_ wird kein Timer gestartet.
* _NEU_: Modul mit Bewegungsmelder, wenn dieser aktiv ist wird der Timer immer wieder erneuert.
* _NEU_: Über die Funktion _TLA_Duration(id, minuten)_ kann die Wartezeit via Skript (WebFront) gesetzt werden.

v2.0.20170101

* _FIX_: Umstellung auf Nachrichten (RegisterMessage/MessageSink)
* _NEU_: Erweiterung zum Ausführen eines Skriptes

v1.0.20161220

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
