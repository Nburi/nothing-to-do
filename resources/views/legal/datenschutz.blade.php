{{--
    Datenschutzerklärung — static legal page, publicly reachable with no login
    (see routes/web.php). The sections below describe this app's real data
    processing (accounts, tasks, the shared class Agenda, push notifications,
    the optional MCP/OAuth connector) as accurately as the codebase documents
    it — this is intentionally more than generic boilerplate. What is NOT real
    yet is the operator identity/contact and a few numbers that depend on
    where this is actually hosted. Deliberately not marked with a visible
    placeholder notice in the rendered text (only here) — see impressum.blade.php
    for the same convention.

    [ANZUPASSEN] before going live:
    - Verantwortliche Stelle: echter Name/Firma, Adresse, Kontakt-E-Mail
      (muss mit dem Impressum übereinstimmen).
    - Hosting-Standort (aktuell als "Schweiz/EU" offen gehalten — mit dem
      tatsächlichen Hosting-Anbieter/Rechenzentrumsstandort ersetzen).
    - Aufbewahrungsfristen konkretisieren, falls vom Betreiber anders
      festgelegt (aktuell: "bis zur Löschung des Konto durch die Nutzerin
      oder den Nutzer").
    - Von einer fachkundigen Stelle prüfen lassen, insbesondere falls auch
      Personen aus der EU die App nutzen (DSGVO-Pflichten zusätzlich zum
      revDSG).
--}}
<x-legal-layout
    title="Datenschutzerklärung"
    description="Wie nothing-to-do Daten erhebt, verarbeitet und schützt."
>
    <h1>Datenschutzerklärung</h1>
    <p>
        Diese Erklärung beschreibt, welche Personendaten nothing-to-do
        bearbeitet, zu welchem Zweck, und welche Rechte dir dabei zustehen.
    </p>

    <h2>Verantwortliche Stelle</h2>
    <p>
        nothing-to-do<br>
        [Name / Firma einfügen]<br>
        [Strasse und Hausnummer], [PLZ und Ort], Schweiz<br>
        E-Mail: <a href="mailto:datenschutz@nothing-to-do.ch">datenschutz@nothing-to-do.ch</a>
    </p>

    <h2>Welche Daten wir bearbeiten</h2>
    <p>Beim Erstellen eines Kontos und während der Nutzung werden folgende Daten gespeichert:</p>
    <ul>
        <li><strong>Kontodaten:</strong> Name, E-Mail-Adresse, verschlüsseltes Passwort.</li>
        <li><strong>Inhalte, die du selbst erfasst:</strong> Aufgaben, Projekte, Gruppen, Agenda-Einträge, Notizen, Zeitplan-Einträge und ähnliche Inhalte, die du in der App anlegst.</li>
        <li><strong>Technische Nutzungsdaten:</strong> Zeitzone-Einstellung, zuletzt gesehen/online (nur innerhalb geteilter Klassen-Agenden sichtbar, lässt sich in den Einstellungen deaktivieren), sowie Protokolldaten bei technischen Fehlern.</li>
        <li><strong>Push-Benachrichtigungen (optional):</strong> falls du Browser-Benachrichtigungen aktivierst, wird ein geräte-/browserbezogenes Abonnement gespeichert, über das Erinnerungen zugestellt werden.</li>
    </ul>

    <h2>Zweck der Bearbeitung</h2>
    <p>
        Deine Daten werden ausschliesslich zur Bereitstellung der App-Funktionen
        verwendet: Speichern und Anzeigen deiner Aufgaben und Termine, Senden von
        Erinnerungen, und — falls du einer Klasse beitrittst — das Teilen von
        Hausaufgaben-Einträgen innerhalb dieser Klasse. Es findet keine Analyse
        zu Werbezwecken statt, und Daten werden nicht an Dritte verkauft.
    </p>

    <h2>Geteilte Inhalte</h2>
    <p>
        Die Agenda (Hausaufgaben &amp; Prüfungen) kann mit einer Klasse geteilt
        werden — in diesem Fall sehen alle Mitglieder dieser Klasse die
        geteilten Einträge. Alles andere (Aufgaben, Projekte, Zeitplan,
        Bastelideen) bleibt ausschliesslich für dich sichtbar. Support-Anfragen
        und Feedback, das du über das Hilfe-Center einreichst, ist nur für dich
        und für Administratoren der App einsehbar.
    </p>

    <h2>Weitergabe an Dritte</h2>
    <p>
        Falls du Browser-Push-Benachrichtigungen aktivierst, wird die
        Zustellung technisch über den Push-Dienst deines Browser-Anbieters
        abgewickelt (z. B. Google für Chrome/Edge, Mozilla für Firefox, Apple
        für Safari) — diese Anbieter erhalten dabei nur die für die Zustellung
        nötigen technischen Verbindungsdaten, nicht den Inhalt deiner Aufgaben.
        Eine Weitergabe an weitere Dritte findet nicht statt.
    </p>
    <p>
        Optional kannst du in den Einstellungen einen eigenen Zugangsschlüssel
        (API-Token) oder eine Verbindung zu einem KI-Assistenten (MCP) für dein
        eigenes Konto einrichten. In diesem Fall greift ausschliesslich das von
        dir selbst gewählte, autorisierte Programm auf deine eigenen Daten zu —
        diese Verbindung wird ausschliesslich von dir eingerichtet und kann
        jederzeit in den Einstellungen wieder entzogen werden.
    </p>

    <h2>Cookies und lokale Speicherung</h2>
    <p>
        Die App verwendet ein technisch notwendiges Session-Cookie, um dich
        angemeldet zu halten, sowie ein Cookie zum Schutz vor
        Cross-Site-Request-Forgery. Beide sind für den Betrieb der App
        erforderlich und benötigen keine Einwilligung. Als installierbare
        Web-App speichert nothing-to-do zusätzlich einzelne
        Darstellungs-Einstellungen lokal in deinem Browser; diese Daten
        verlassen dein Gerät nicht.
    </p>

    <h2>Hosting</h2>
    <p>
        Die App wird auf Servern in der Schweiz/EU betrieben. [Hosting-Anbieter
        und genauer Standort einfügen.]
    </p>

    <h2>Aufbewahrungsdauer</h2>
    <p>
        Deine Daten werden gespeichert, solange dein Konto besteht. Löschst du
        dein Konto, werden deine Daten gelöscht bzw. anonymisiert, soweit
        keine gesetzliche Aufbewahrungspflicht entgegensteht.
    </p>

    <h2>Deine Rechte</h2>
    <p>
        Du hast das Recht auf Auskunft über die zu deiner Person gespeicherten
        Daten, auf Berichtigung unrichtiger Daten sowie auf Löschung deines
        Kontos samt Inhalten. Einen vollständigen Export deiner Daten kannst
        du jederzeit selbst in deinem <a href="{{ route('profile.edit') }}">Profil</a>
        herunterladen; dort kannst du dein Konto auch eigenständig löschen.
        Für weitere Anfragen wende dich an die oben genannte Kontaktadresse.
    </p>

    <h2>Änderungen dieser Erklärung</h2>
    <p>
        Wir können diese Datenschutzerklärung anpassen, um sie an geänderte
        Rechtslagen oder neue Funktionen der App anzupassen. Die jeweils
        aktuelle Fassung ist auf dieser Seite abrufbar.
    </p>

    <p>Siehe auch unser <a href="{{ route('impressum') }}">Impressum</a>.</p>
</x-legal-layout>
