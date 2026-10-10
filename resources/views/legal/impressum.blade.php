{{--
    Impressum — static legal page, publicly reachable with no login (see
    routes/web.php). Structural placeholder content only; the operator-specific
    facts below are NOT real and must be filled in before this app goes live for
    real users on a .ch domain. Deliberately NOT marked with a visible
    "[ANZUPASSEN]"/placeholder notice in the rendered text (only here, in this
    comment) — a real Impressum reads as a finished legal page, never as a draft.

    [ANZUPASSEN] before going live:
    - Betreiber: echter Name/Firma, vollständige Postadresse, Kontakt-E-Mail
      (aktuell ein Platzhalter unten).
    - Handelsregister-Nr. / UID, falls die App als Firma/Einzelunternehmen mit
      Handelsregistereintrag betrieben wird (sonst diesen Punkt entfernen).
    - Verantwortliche Person (falls abweichend vom Betreiber).
    - Den gesamten Text von einer fachkundigen Stelle (Anwalt/Treuhand) prüfen
      lassen — dies ist bewusst nur ein strukturell korrekter Platzhalter, kein
      geprüfter Rechtstext.
--}}
<x-legal-layout title="Impressum" description="Angaben zum Betreiber von nothing-to-do gemäss Art. 3 UWG.">
    <h1>Impressum</h1>
    <p>Angaben gemäss den in der Schweiz geltenden Informationspflichten.</p>

    <h2>Betreiber</h2>
    <p>
        nothing-to-do<br>
        Nino Baur<br>
        Tuchschmidweg 6, 5000 Aarau, Schweiz<br>
    </p>

    <h2>Kontakt</h2>
    <p>
        E-Mail: <a href="mailto:nino.baur@gmx.net">nino.baur@gmx.net</a>
    </p>

    {{-- <h2>Vertretungsberechtigte Person</h2>
    <p>[Name der für den Inhalt verantwortlichen Person]</p> --}}

    <h2>Haftungsausschluss</h2>
    <p>
        Der Betreiber übernimmt keine Gewähr für die Richtigkeit, Genauigkeit,
        Aktualität, Zuverlässigkeit und Vollständigkeit der Informationen auf
        dieser Website. Haftungsansprüche gegen den Betreiber wegen Schäden
        materieller oder immaterieller Art, welche aus dem Zugriff oder der
        Nutzung bzw. Nichtnutzung der veröffentlichten Informationen, durch
        Missbrauch der Verbindung oder durch technische Störungen entstanden
        sind, werden ausgeschlossen.
    </p>
    <p>
        Alle Angebote sind unverbindlich. Der Betreiber behält es sich
        ausdrücklich vor, Teile der Seiten oder das gesamte Angebot ohne
        besondere Ankündigung zu verändern, zu ergänzen, zu löschen oder die
        Veröffentlichung zeitweise oder endgültig einzustellen.
    </p>

    <h2>Haftung für Links</h2>
    <p>
        Verweise und Links auf Webseiten Dritter liegen ausserhalb unseres
        Verantwortungsbereichs. Es wird jegliche Verantwortung für solche
        Webseiten abgelehnt. Der Zugriff und die Nutzung solcher Webseiten
        erfolgen auf eigene Gefahr der Nutzerin oder des Nutzers.
    </p>

    <h2>Urheberrechte</h2>
    <p>
        Die Urheber- und alle anderen Rechte an Inhalten, Bildern, Fotos oder
        anderen Dateien auf dieser Website gehören ausschliesslich dem
        Betreiber dieser Website oder den speziell genannten Rechtsinhabern.
        Für die Reproduktion jeglicher Elemente ist die schriftliche
        Zustimmung des Urheberrechtsträgers im Voraus einzuholen.
    </p>

    <p>Siehe auch unsere <a href="{{ route('datenschutz') }}">Datenschutzerklärung</a>.</p>
</x-legal-layout>
