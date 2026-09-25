# Zentrale Anmeldung über das CRM

Stand: 25.09.2026

Ziel: Die Anmeldung am Intranet erfolgt über das CRM der Müller Holding AG.
Ein Konto, ein Kennwort, ein zweiter Faktor. Wer aus dem CRM ausscheidet,
verliert damit auch den Zugang zum Intranet.

Grundlage ist der OIDC-Anbieter, den das CRM bereits mitbringt
(`apps/api/src/mhvp/core/auth/oidc.py`, Abschnitt 3.4 des CRM-Masterprompts):
Authorization Code mit verpflichtendem PKCE, Signatur ES256.

## 1. Aufgabenteilung

| | |
| --- | --- |
| CRM | prüft Kennwort und zweiten Faktor, stellt ein signiertes Identitätstoken aus |
| Intranet | prüft dieses Token und ordnet es einem **vorhandenen** Benutzer zu |

Das Intranet legt keine Benutzer an. Wer Zugang zu den Daten der Holding
erhält, entscheidet die Benutzerverwaltung des Intranets. Eine Anmeldung am
CRM allein begründet keinen Zugang.

## 2. Ablauf

1. Der Benutzer wählt auf der Anmeldeseite "Anmelden über das CRM".
2. Das Intranet erzeugt drei Einmalwerte (`state`, `nonce`, `code_verifier`),
   legt sie in der Sitzung ab und leitet zum CRM weiter.
3. Das CRM prüft die Identität einschließlich zweitem Faktor.
4. Das CRM leitet mit einem Anmeldecode an `/anmeldung/crm/rueckkehr` zurück.
5. Das Intranet tauscht den Code über eine **eigene** Verbindung zum CRM gegen
   ein Identitätstoken. Der Browser des Benutzers ist daran nicht beteiligt.
6. Das Intranet prüft Signatur, Aussteller, Empfänger, Laufzeit und
   Einmalzahl, ordnet den Benutzer zu und meldet ihn an.

Beim ersten Mal erfolgt die Zuordnung über die E-Mail-Adresse. Danach wird die
Kennung des CRM (`sub`) in `users.crm_subject` festgehalten und die Zuordnung
läuft darüber. Damit bleibt die Verknüpfung bestehen, wenn sich die
E-Mail-Adresse ändert, und eine später im CRM neu vergebene Adresse führt
nicht versehentlich in ein fremdes Konto.

## 3. Einrichtung

### 3.1 Im CRM

Ein Eintrag in `oidc_client` mit:

| Feld | Wert |
| --- | --- |
| `client_id` | `mhag-intranet` |
| `name` | Müller Holding AG Intranet |
| `redirect_uris` | `https://intern.mueller-holding.ag/anmeldung/crm/rueckkehr` |
| `client_secret_hash` | SHA-256 des Geheimnisses, hexadezimal |
| `active` | true |

Das Geheimnis wird einmal erzeugt und ausschließlich in der `.env.prod` des
Intranets abgelegt. Es steht in keinem Dokument und in keinem Repository.

### 3.2 Im Intranet

In `.env.prod`:

```env
SSO_ENABLED=true
SSO_ISSUER=<Aussteller laut Discovery-Dokument des CRM>
SSO_CLIENT_ID=mhag-intranet
SSO_CLIENT_SECRET=<das erzeugte Geheimnis>
SSO_REDIRECT_URI=https://intern.mueller-holding.ag/anmeldung/crm/rueckkehr
SSO_LOGIN_URL=<Anmeldeseite des CRM, siehe Abschnitt 4>
SSO_TRUST_MFA=true
SSO_ALLOW_LOCAL_LOGIN=true
```

Die Rückkehradresse muss im CRM Zeichen für Zeichen so eingetragen sein.

## 4. Was im CRM noch fehlt

Der Autorisierungsendpunkt `/api/v1/oidc/authorize` verlangt eine bereits
angemeldete Sitzung in Form einer Kopfzeile `Authorization: Bearer`. Eine
Weiterleitung aus dem Browser trägt diese Kopfzeile nicht. Im CRM gibt es
bisher keine Oberfläche, die diesen Schritt führt; der Quelltext verweist
dafür auf einen späteren Arbeitsschritt.

Solange das so ist, funktioniert die zentrale Anmeldung nicht. Nötig ist im
CRM eine Seite, die

1. die Parameter der Anfrage entgegennimmt,
2. die Anmeldung des Benutzers sicherstellt (bestehender Anmeldeweg des CRM
   einschließlich TOTP),
3. den Autorisierungsendpunkt mit dem Zugriffstoken aufruft und
4. auf die zurückgegebene Adresse weiterleitet.

Die Adresse dieser Seite wird im Intranet unter `SSO_LOGIN_URL` eingetragen.
Der Umbau des CRM ist als eigener Arbeitsschritt zu planen und mit den
Regeln des CRM-Repositorys abzustimmen.

Bis dahin bleibt `SSO_ENABLED=false`. In diesem Zustand ändert sich an der
Anmeldung des Intranets nichts.

## 5. Zweiter Faktor

`SSO_TRUST_MFA=true` (Vorgabe) bedeutet: Das Intranet erkennt die Prüfung des
CRM an und verlangt keinen zweiten Faktor mehr. Das ist der Sinn einer
zentralen Anmeldung, das CRM verlangt dafür TOTP.

`SSO_TRUST_MFA=false` bedeutet: Das Intranet verlangt zusätzlich seinen
eigenen zweiten Faktor. Sicherer, aber für den Benutzer zwei Codes je
Anmeldung.

Beide Wege werden in der Prüfspur festgehalten. **Die Entscheidung ist von der
Geschäftsführung zu bestätigen.**

## 6. Notanmeldung

Ist die zentrale Anmeldung eingerichtet, bleibt die Anmeldung mit Kennwort als
Notzugang bestehen, damit die Administration bei einer Störung des CRM
handlungsfähig ist. Sie ist Benutzern mit der Rolle Administrator vorbehalten
und wird als `auth.notanmeldung` gesondert protokolliert. Alle übrigen
Benutzer werden auf das CRM verwiesen.

`SSO_ALLOW_LOCAL_LOGIN=false` schaltet auch diesen Weg ab. Dann ist bei einer
Störung des CRM niemand mehr im Intranet handlungsfähig; das ist nur zu
wählen, wenn ein anderer Weg in den Server besteht.

## 7. Prüfspur

| Eintrag | Bedeutung |
| --- | --- |
| `auth.sso_login` | Anmeldung über das CRM, mit Aussteller und Behandlung des zweiten Faktors |
| `auth.sso_failed` | abgewiesener Versuch, mit Grund |
| `auth.sso_zweiter_faktor` | zusätzlicher zweiter Faktor verlangt |
| `auth.notanmeldung` | örtliche Anmeldung trotz eingerichteter zentraler Anmeldung |
| `auth.notanmeldung_abgelehnt` | örtliche Anmeldung verweigert |

Jeder Versuch wird zusätzlich in `login_attempts` festgehalten.

## 8. Was geprüft wird

Die Prüfungen sind einzeln durch Tests abgesichert
(`tests/Feature/ZentraleAnmeldungTest.php`, 27 Tests). Jede einzelne Prüfung
wurde probeweise entfernt; in jedem Fall schlägt mindestens ein Test fehl.

| Prüfung | Verhindert |
| --- | --- |
| Signatur gegen die Schlüssel des CRM | ein selbst ausgestelltes Token |
| Aussteller | ein Token eines fremden Anbieters |
| Empfänger | ein Token, das für eine andere Anwendung ausgestellt wurde |
| Laufzeit | ein abgelaufenes Token |
| Einmalzahl `nonce` | das Wiedereinspielen eines früheren Tokens |
| `state` | das Unterschieben einer fremden Antwort |
| PKCE `code_verifier` | die Verwendung eines abgefangenen Codes |
| Benutzer vorhanden und aktiv | Zugang ohne Konto im Intranet |
| Kennung stimmt überein | die Übernahme eines fremden Kontos |

## 9. Offene Punkte

| Punkt | Wer entscheidet |
| --- | --- |
| Anmeldeseite im CRM (Abschnitt 4) | Betreiber, eigener Arbeitsschritt im CRM |
| Anerkennung der Zwei-Faktor-Prüfung des CRM | Geschäftsführung |
| Umfang der Notanmeldung | Betreiber |
| Vorgehen, wenn ein Benutzer im CRM ausscheidet | Betreiber, betrifft auch das Intranet |
