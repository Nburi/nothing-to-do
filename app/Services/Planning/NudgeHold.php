<?php

namespace App\Services\Planning;

/**
 * Why tonight's evening nudge is not sent when an arrival asks for it (R2). The value is the
 * stable code in the JSON answer and in the log; message() is the sentence the iPhone Shortcut
 * can show ("Ergebnis anzeigen") so a silent arrival is never a mystery.
 */
enum NudgeHold: string
{
    /** This weekday has no nudge time (or the card was never saved): the user asked for no nudge. */
    case NoTime = 'no_time';

    /** More than EveningNudge::ARRIVAL_EARLY_MINUTES before the nudge time. */
    case TooEarly = 'too_early';

    case AlreadySent = 'already_sent';

    /** Tonight's reset was started or closed. */
    case ResetDone = 'reset_done';

    case Paused = 'paused';

    case InSession = 'in_session';

    public function message(): string
    {
        return match ($this) {
            self::NoTime => 'Für heute ist keine Zeit zum Abschalten eingestellt. Du kannst sie in der App unter Einstellungen › Abschalten am Abend setzen.',
            self::TooEarly => 'Es ist noch zu früh am Tag. Die Mitteilung kommt frühestens '.intdiv(EveningNudge::ARRIVAL_EARLY_MINUTES, 60).' Stunden vor deiner Zeit zum Abschalten.',
            self::AlreadySent => 'Heute Abend wurde schon eine Mitteilung gesendet.',
            self::ResetDone => 'Das Abschalten heute Abend ist schon gestartet oder abgeschlossen.',
            self::Paused => 'Heute ist im Wochenplan pausiert.',
            self::InSession => 'Gerade läuft eine Fokus-Session.',
        };
    }
}
