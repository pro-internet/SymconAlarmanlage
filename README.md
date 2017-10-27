# SymconMelder

Dieses Modul dient zur Überwachung von Sensoren.
Sobald einer der Sensoren von false auf true wechselt, wird eine E-Mail gesendet,
Push Benachrichtigungen werden an die jeweiligen Mobilgeräte geschickt und es werden zyklisch
Benachrichtigungen an die Mobilgeräte gesendet, dass ein Alarm ausgelößt wurde.
Alle in Targets Alarm vorhandenen Variablen/Geräte werden auf ihren Maximalwert gesetzt.

Features:
- Automatik An/Aus, um die Überprüfung der Sensoren zu deaktivieren
- Alarm Switch, der gleichzeitig als Indikator für die Aktivierung des Alarms fungiert.
- E-Mail Benachrichtigung An/Aus, um diese zu aktivieren/deaktivieren.
- Push Benachrichtigung An/Aus, um diese zu aktivieren/deaktivieren.
- Benachrichtigung Interval, um das Interval, in dem die Nachricht, 
dass der Alarm ausgelößt wurde, einzustellen. (falls 0s eingestellt werden, werden diese deaktiviert)
- Targets: Hier sind die Sensoren, die zu überprüfen sind, als Links zu plazieren.
- Targets Alarm: Hier sind die Variablen/Geräte als Links zu plazieren, die auf ihren Maximalwert gesetzt werden sollen,
sobald der Alarm aktiviert wird.
