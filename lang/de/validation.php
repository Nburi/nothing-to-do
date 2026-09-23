<?php

/*
 * German translation of Laravel's own built-in validation messages.
 *
 * Why this file exists: this app's UI copy is entirely hand-written German
 * (config/app.php's `locale` is already 'de'), but Laravel's *built-in*
 * validation vocabulary (required/date_format/exists/…) was never localized
 * — there was no lang/ directory in this project at all before this fix, so
 * every one of these rules silently fell through to Laravel's internal
 * English defaults. Confirmed live in a UX research pass: an empty required
 * field on the Wochenplan "+ Block" form and on Settings' API token form
 * both surfaced raw English sentences ("The event category id field is
 * required.") inside an otherwise fully German app — not a crash or a
 * stack-trace leak (the app's own @error() feedback blocks are correctly
 * styled and positioned), just an untranslated message.
 *
 * Keys and structure mirror Laravel's own default file exactly
 * (vendor/laravel/framework/.../Translation/lang/en/validation.php) so any
 * future Laravel upgrade adding a new rule can be diffed against it directly.
 */

return [

    'accepted' => 'Das Feld :attribute muss akzeptiert werden.',
    'accepted_if' => 'Das Feld :attribute muss akzeptiert werden, wenn :other den Wert :value hat.',
    'active_url' => 'Das Feld :attribute muss eine gültige URL sein.',
    'after' => 'Das Feld :attribute muss ein Datum nach dem :date sein.',
    'after_or_equal' => 'Das Feld :attribute muss ein Datum nach dem oder gleich dem :date sein.',
    'alpha' => 'Das Feld :attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => 'Das Feld :attribute darf nur Buchstaben, Zahlen, Binde- und Unterstriche enthalten.',
    'alpha_num' => 'Das Feld :attribute darf nur Buchstaben und Zahlen enthalten.',
    'any_of' => 'Das Feld :attribute ist ungültig.',
    'array' => 'Das Feld :attribute muss eine Liste (Array) sein.',
    'ascii' => 'Das Feld :attribute darf nur einzelbytige alphanumerische Zeichen und Symbole enthalten.',
    'before' => 'Das Feld :attribute muss ein Datum vor dem :date sein.',
    'before_or_equal' => 'Das Feld :attribute muss ein Datum vor dem oder gleich dem :date sein.',
    'between' => [
        'array' => 'Das Feld :attribute muss zwischen :min und :max Elemente haben.',
        'file' => 'Das Feld :attribute muss zwischen :min und :max Kilobytes gross sein.',
        'numeric' => 'Das Feld :attribute muss zwischen :min und :max liegen.',
        'string' => 'Das Feld :attribute muss zwischen :min und :max Zeichen lang sein.',
    ],
    'boolean' => 'Das Feld :attribute muss entweder wahr oder falsch sein.',
    'can' => 'Das Feld :attribute enthält einen nicht erlaubten Wert.',
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'contains' => 'Dem Feld :attribute fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist falsch.',
    'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
    'date_equals' => 'Das Feld :attribute muss ein Datum gleich dem :date sein.',
    'date_format' => 'Das Feld :attribute entspricht nicht dem gültigen Format für :format.',
    'decimal' => 'Das Feld :attribute muss :decimal Dezimalstellen haben.',
    'declined' => 'Das Feld :attribute muss abgelehnt werden.',
    'declined_if' => 'Das Feld :attribute muss abgelehnt werden, wenn :other den Wert :value hat.',
    'different' => 'Die Felder :attribute und :other müssen sich unterscheiden.',
    'digits' => 'Das Feld :attribute muss :digits Stellen haben.',
    'digits_between' => 'Das Feld :attribute muss zwischen :min und :max Stellen haben.',
    'dimensions' => 'Das Feld :attribute hat ungültige Bildabmessungen.',
    'distinct' => 'Das Feld :attribute beinhaltet einen bereits vorhandenen Wert.',
    'doesnt_contain' => 'Das Feld :attribute darf keinen der folgenden Werte enthalten: :values.',
    'doesnt_end_with' => 'Das Feld :attribute darf nicht mit einem der folgenden Werte enden: :values.',
    'doesnt_start_with' => 'Das Feld :attribute darf nicht mit einem der folgenden Werte beginnen: :values.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'encoding' => 'Das Feld :attribute muss in :encoding kodiert sein.',
    'ends_with' => 'Das Feld :attribute muss mit einem der folgenden Werte enden: :values.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'extensions' => 'Das Feld :attribute muss eine der folgenden Dateiendungen haben: :values.',
    'file' => 'Das Feld :attribute muss eine Datei sein.',
    'filled' => 'Das Feld :attribute muss einen Wert haben.',
    'gt' => [
        'array' => 'Das Feld :attribute muss mehr als :value Elemente haben.',
        'file' => 'Das Feld :attribute muss grösser als :value Kilobytes sein.',
        'numeric' => 'Das Feld :attribute muss grösser als :value sein.',
        'string' => 'Das Feld :attribute muss länger als :value Zeichen sein.',
    ],
    'gte' => [
        'array' => 'Das Feld :attribute muss mindestens :value Elemente haben.',
        'file' => 'Das Feld :attribute muss grösser oder gleich :value Kilobytes sein.',
        'numeric' => 'Das Feld :attribute muss grösser oder gleich :value sein.',
        'string' => 'Das Feld :attribute muss mindestens :value Zeichen lang sein.',
    ],
    'hex_color' => 'Das Feld :attribute muss eine gültige Hexadezimal-Farbe sein.',
    'image' => 'Das Feld :attribute muss ein Bild sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in_array' => 'Das Feld :attribute muss in :other vorhanden sein.',
    'in_array_keys' => 'Das Feld :attribute muss mindestens einen der folgenden Schlüssel enthalten: :values.',
    'integer' => 'Das Feld :attribute muss eine Ganzzahl sein.',
    'ip' => 'Das Feld :attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => 'Das Feld :attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => 'Das Feld :attribute muss eine gültige IPv6-Adresse sein.',
    'json' => 'Das Feld :attribute muss ein gültiger JSON-String sein.',
    'list' => 'Das Feld :attribute muss eine Liste sein.',
    'lowercase' => 'Das Feld :attribute muss in Kleinbuchstaben sein.',
    'lt' => [
        'array' => 'Das Feld :attribute muss weniger als :value Elemente haben.',
        'file' => 'Das Feld :attribute muss kleiner als :value Kilobytes sein.',
        'numeric' => 'Das Feld :attribute muss kleiner als :value sein.',
        'string' => 'Das Feld :attribute muss kürzer als :value Zeichen sein.',
    ],
    'lte' => [
        'array' => 'Das Feld :attribute darf nicht mehr als :value Elemente haben.',
        'file' => 'Das Feld :attribute muss kleiner oder gleich :value Kilobytes sein.',
        'numeric' => 'Das Feld :attribute muss kleiner oder gleich :value sein.',
        'string' => 'Das Feld :attribute darf maximal :value Zeichen lang sein.',
    ],
    'mac_address' => 'Das Feld :attribute muss eine gültige MAC-Adresse sein.',
    'max' => [
        'array' => 'Das Feld :attribute darf maximal :max Elemente haben.',
        'file' => 'Das Feld :attribute darf maximal :max Kilobytes gross sein.',
        'numeric' => 'Das Feld :attribute darf maximal :max sein.',
        'string' => 'Das Feld :attribute darf maximal :max Zeichen lang sein.',
    ],
    'max_digits' => 'Das Feld :attribute darf maximal :max Stellen haben.',
    'mimes' => 'Das Feld :attribute muss eine Datei diesen Typs sein: :values.',
    'mimetypes' => 'Das Feld :attribute muss eine Datei diesen Typs sein: :values.',
    'min' => [
        'array' => 'Das Feld :attribute muss mindestens :min Elemente haben.',
        'file' => 'Das Feld :attribute muss mindestens :min Kilobytes gross sein.',
        'numeric' => 'Das Feld :attribute muss mindestens :min sein.',
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
    ],
    'min_digits' => 'Das Feld :attribute muss mindestens :min Stellen haben.',
    'missing' => 'Das Feld :attribute darf nicht vorhanden sein.',
    'missing_if' => 'Das Feld :attribute darf nicht vorhanden sein, wenn :other den Wert :value hat.',
    'missing_unless' => 'Das Feld :attribute darf nur fehlen, wenn :other den Wert :value hat.',
    'missing_with' => 'Das Feld :attribute darf nicht vorhanden sein, wenn :values vorhanden ist.',
    'missing_with_all' => 'Das Feld :attribute darf nicht vorhanden sein, wenn :values vorhanden sind.',
    'multiple_of' => 'Das Feld :attribute muss ein Vielfaches von :value sein.',
    'not_in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'not_regex' => 'Das Format des Feldes :attribute ist ungültig.',
    'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
    'password' => [
        'letters' => 'Das Feld :attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => 'Das Feld :attribute muss mindestens einen Gross- und einen Kleinbuchstaben enthalten.',
        'numbers' => 'Das Feld :attribute muss mindestens eine Zahl enthalten.',
        'symbols' => 'Das Feld :attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => 'Der eingegebene Wert für :attribute wurde in einem bekannten Datenleck gefunden. Bitte wähle einen anderen Wert für :attribute.',
    ],
    'present' => 'Das Feld :attribute muss vorhanden sein.',
    'present_if' => 'Das Feld :attribute muss vorhanden sein, wenn :other den Wert :value hat.',
    'present_unless' => 'Das Feld :attribute muss vorhanden sein, ausser :other hat den Wert :value.',
    'present_with' => 'Das Feld :attribute muss vorhanden sein, wenn :values vorhanden ist.',
    'present_with_all' => 'Das Feld :attribute muss vorhanden sein, wenn :values vorhanden sind.',
    'prohibited' => 'Das Feld :attribute ist nicht erlaubt.',
    'prohibited_if' => 'Das Feld :attribute ist nicht erlaubt, wenn :other den Wert :value hat.',
    'prohibited_if_accepted' => 'Das Feld :attribute ist nicht erlaubt, wenn :other akzeptiert wurde.',
    'prohibited_if_declined' => 'Das Feld :attribute ist nicht erlaubt, wenn :other abgelehnt wurde.',
    'prohibited_unless' => 'Das Feld :attribute ist nicht erlaubt, ausser :other liegt in :values.',
    'prohibits' => 'Das Feld :attribute verhindert, dass :other vorhanden sein darf.',
    'regex' => 'Das Format des Feldes :attribute ist ungültig.',
    'required' => 'Das Feld :attribute muss ausgefüllt werden.',
    'required_array_keys' => 'Das Feld :attribute muss Einträge für :values enthalten.',
    'required_if' => 'Das Feld :attribute muss ausgefüllt werden, wenn :other den Wert :value hat.',
    'required_if_accepted' => 'Das Feld :attribute muss ausgefüllt werden, wenn :other akzeptiert wurde.',
    'required_if_declined' => 'Das Feld :attribute muss ausgefüllt werden, wenn :other abgelehnt wurde.',
    'required_unless' => 'Das Feld :attribute muss ausgefüllt werden, ausser :other liegt in :values.',
    'required_with' => 'Das Feld :attribute muss ausgefüllt werden, wenn :values vorhanden ist.',
    'required_with_all' => 'Das Feld :attribute muss ausgefüllt werden, wenn :values vorhanden sind.',
    'required_without' => 'Das Feld :attribute muss ausgefüllt werden, wenn :values nicht vorhanden ist.',
    'required_without_all' => 'Das Feld :attribute muss ausgefüllt werden, wenn keines von :values vorhanden ist.',
    'same' => 'Die Felder :attribute und :other müssen übereinstimmen.',
    'size' => [
        'array' => 'Das Feld :attribute muss genau :size Elemente enthalten.',
        'file' => 'Das Feld :attribute muss :size Kilobytes gross sein.',
        'numeric' => 'Das Feld :attribute muss gleich :size sein.',
        'string' => 'Das Feld :attribute muss genau :size Zeichen lang sein.',
    ],
    'starts_with' => 'Das Feld :attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string' => 'Das Feld :attribute muss eine Zeichenkette sein.',
    'timezone' => 'Das Feld :attribute muss eine gültige Zeitzone sein.',
    'unique' => 'Der Wert für :attribute wird bereits verwendet.',
    'uploaded' => 'Das Hochladen von :attribute ist fehlgeschlagen.',
    'uppercase' => 'Das Feld :attribute muss in Grossbuchstaben sein.',
    'url' => 'Das Feld :attribute muss eine gültige URL sein.',
    'ulid' => 'Das Feld :attribute muss eine gültige ULID sein.',
    'uuid' => 'Das Feld :attribute muss eine gültige UUID sein.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | German labels for specific field names, so a message reads e.g. "Das
    | Feld Kategorie muss ausgefüllt werden." instead of falling back to the
    | raw property name ("Das Feld event category id muss ausgefüllt
    | werden."). Deliberately not an exhaustive app-wide sweep of every
    | validated Livewire property — these are the two confirmed in the UX
    | research pass (Wochenplan's category picker, Settings' API token
    | name); add more here as they're found, same convention.
    */

    /*
     | Livewire properties are named like their fields (eventTitle, pauseFrom, ...) and
     | Laravel falls back to the de-camel-cased property name in the message - which
     | reads "Das Feld event title ist erforderlich". Every validated property gets the
     | label the user actually sees next to the field. A test walks the Livewire sources
     | and fails when a validated property is missing here.
     */
    'attributes' => [
        'attrName' => 'Name',
        'attrType' => 'Typ',
        'attrUnit' => 'Einheit',
        'brainstorm' => 'Brainstorming',
        'dailyReminderTime' => 'Uhrzeit',
        'dailyTaskGoal' => 'Tagesziel',
        'dayPreviewNotificationTime' => 'Uhrzeit',
        'deadline' => 'Deadline',
        'deadlinePreviewDays' => 'Vorschau-Tage',
        'dueDate' => 'Wunschtermin',
        'duration' => 'Dauer',
        'editDeadline' => 'Deadline',
        'editDueDate' => 'Wunschtermin',
        'editDuration' => 'Dauer',
        'editGroupId' => 'Gruppe',
        'editList' => 'Liste',
        'editNotes' => 'Notizen',
        'editProjectId' => 'Projekt',
        'editTitle' => 'Titel',
        'eventDate' => 'Datum',
        'eventDays' => 'Wochentage',
        'eventDays.*' => 'Wochentag',
        'eventEnd' => 'Ende',
        'eventKind' => 'Art',
        'eventRecurring' => 'Wiederholung',
        'eventStart' => 'Start',
        'eventTitle' => 'Titel',
        'externalUrl' => 'Link',
        'formDate' => 'Datum',
        'formDescription' => 'Beschreibung',
        'formDuration' => 'Dauer',
        'formExternalLinkLabel' => 'Link-Text',
        'formExternalUrl' => 'Link',
        'formHighlightSelector' => 'Hervorhebung',
        'formLinkType' => 'Verknüpfung',
        'formMessage' => 'Nachricht',
        'formNotes' => 'Notiz',
        'formPrivateNotes' => 'Eigene Notiz',
        'formRelatedModule' => 'Bereich',
        'formSpaceId' => 'Klasse',
        'formSubject' => 'Fach',
        'formTitle' => 'Titel',
        'formType' => 'Typ',
        'groupName' => 'Name',
        'joinCode' => 'Code',
        'newCategoryColor' => 'Farbe',
        'newCategoryName' => 'Name',
        'newSpaceName' => 'Name',
        'newTitle' => 'Titel',
        'newWhereToBegin' => 'Notiz',
        'noteDraft' => 'Notiz',
        'notes' => 'Notizen',
        'pLongBreak' => 'Lange Pause',
        'pLongEvery' => 'Lange Pause nach',
        'pShortBreak' => 'Kurze Pause',
        'pWork' => 'Arbeitsphase',
        'pauseFrom' => 'Von',
        'pauseNote' => 'Notiz',
        'pauseTo' => 'Bis',
        'prepareReminderTime' => 'Uhrzeit',
        'projectDeadline' => 'Deadline',
        'projectName' => 'Name',
        'resetTime' => 'Uhrzeit',
        'standardDate' => 'Tag',
        'standardDuration' => 'Dauer',
        'target' => 'Ziel',
        'timezoneOffset' => 'Zeitzone',
        'title' => 'Titel',
        'whereToBegin' => 'Notiz',
        'eventCategoryId' => 'Kategorie',
        'newTokenName' => 'Name',
        'endpoint' => 'Push-Endpunkt',
        'authToken' => 'Push-Schlüssel',
        'boundsStart' => 'Aufstehzeit',
        'boundsEnd' => 'Schlafenszeit',
        'dayStartTime' => 'Aufstehzeit',
        'dayEndTime' => 'Schlafenszeit',
        'eventBufferBefore' => 'Wegzeit davor',
        'eventBufferAfter' => 'Wegzeit danach',
    ],

];
