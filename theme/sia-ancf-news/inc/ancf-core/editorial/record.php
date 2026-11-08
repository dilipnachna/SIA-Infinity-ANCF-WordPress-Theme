<?php
declare(strict_types=1);

namespace SIA\ANCF\ThemeEditorial;

/** Editorial data only. This class does not calculate trust, score or authority. */
final class Record
{
    public const QUESTIONS = [
        'who' => ['Who', 'कौन शामिल है या दावा कर रहा है?'],
        'what' => ['What', 'क्या हुआ या घोषित किया गया?'],
        'when' => ['When', 'घटना, बयान और update कब हुआ?'],
        'where' => ['Where', 'कहाँ हुआ; कार्रवाई का दायरा क्या है?'],
        'why' => ['Why', 'कारण पुष्ट है, किसी का दावा है या अज्ञात है?'],
        'which' => ['Which', 'कौन-सा नियम, विभाग, निर्णय या योजना?'],
        'whom' => ['Whom', 'कौन प्रभावित या लाभान्वित है?'],
        'whose' => ['Whose', 'किसकी संपत्ति, अधिकार या जिम्मेदारी?'],
        'whether' => ['Whether', 'पुष्टि हुई, विवाद है या पुष्टि बाकी है?'],
        'how' => ['How', 'घटना या प्रक्रिया कैसे हुई?'],
        'how_much' => ['How much', 'रकम या मात्रा; इकाई और स्रोत क्या हैं?'],
        'how_many' => ['How many', 'कितने; गिनती का समय और दायरा क्या है?'],
        'how_long' => ['How long', 'कितनी अवधि या समयसीमा है?'],
    ];
    public const STATES = [
        'not_assessed' => 'जाँच शुरू नहीं हुई', 'verified' => 'सत्यापित — संपादक का दर्ज किया तथ्य',
        'attributed' => 'स्रोत का दावा', 'disputed' => 'विवादित', 'unknown' => 'पुष्टि बाकी / अज्ञात',
        'not_applicable' => 'लागू नहीं',
    ];
    public const BASIS = [
        'source_based' => 'स्रोतों पर आधारित', 'firsthand' => 'लेखक का वास्तविक अनुभव', 'mixed' => 'दोनों',
    ];

    public static function defaults(): array
    {
        $questions = [];
        foreach (self::QUESTIONS as $key => $_) {
            $questions[$key] = ['answer' => '', 'state' => 'not_assessed', 'source' => '', 'checked_on' => ''];
        }
        return ['language' => 'en', 'basis' => 'source_based',
            'source_log' => '', 'provenance_note' => '',
            'editor_note' => '', 'questions' => $questions];
    }

    /** @throws \InvalidArgumentException for malformed or contradictory entries. */
    public static function normalize(array $input): array
    {
        $out = self::defaults();
        foreach (['article_type', 'ai_assistance'] as $sharedKey) {
            if (array_key_exists($sharedKey, $input)) {
                throw new \InvalidArgumentException('Shared classification must be set in SIA ANCF Core: ' . $sharedKey);
            }
        }
        $choices = ['language' => ['en' => 'English', 'hi' => 'हिन्दी'], 'basis' => self::BASIS];
        foreach ($choices as $key => $options) {
            $value = $input[$key] ?? $out[$key];
            if (!is_string($value) || !array_key_exists($value, $options)) {
                throw new \InvalidArgumentException('Invalid ' . $key);
            }
            $out[$key] = $value;
        }
        foreach (['source_log', 'provenance_note', 'editor_note'] as $key) {
            $out[$key] = self::text($input[$key] ?? '');
        }
        if (isset($input['questions']) && !is_array($input['questions'])) {
            throw new \InvalidArgumentException('Questions must be an object.');
        }
        foreach (self::QUESTIONS as $key => [$label, $_]) {
            $row = $input['questions'][$key] ?? [];
            if (!is_array($row)) {
                throw new \InvalidArgumentException($label . ': invalid question entry.');
            }
            $state = $row['state'] ?? 'not_assessed';
            if (!is_string($state) || !isset(self::STATES[$state])) {
                throw new \InvalidArgumentException($label . ': invalid evidence state.');
            }
            $answer = self::text($row['answer'] ?? '');
            $source = self::text($row['source'] ?? '');
            $date = self::text($row['checked_on'] ?? '');
            if ($state === 'not_applicable' && $answer === '') {
                throw new \InvalidArgumentException($label . ': लागू नहीं होने का कारण दर्ज करें।');
            }
            if (in_array($state, ['verified', 'attributed', 'disputed'], true) && ($answer === '' || $source === '')) {
                throw new \InvalidArgumentException($label . ': उत्तर और स्रोत/attribution दर्ज करें।');
            }
            if ($date !== '') {
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                    throw new \InvalidArgumentException($label . ': invalid check date.');
                }
            }
            $out['questions'][$key] = ['answer' => $answer, 'state' => $state, 'source' => $source, 'checked_on' => $date];
        }
        return $out;
    }

    public static function fingerprint(array $record, array $subject): string
    {
        return hash('sha256', json_encode([$record, $subject], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** Read old data without changing it or guessing a shared classification. */
    public static function editorialFromEnvelope(array $envelope): array
    {
        $input = $envelope['editorial'] ?? self::defaults();
        if (!is_array($input)) {
            throw new \InvalidArgumentException('Invalid stored editorial record.');
        }
        unset($input['article_type'], $input['ai_assistance']);
        return self::normalize($input);
    }

    public static function legacyClassification(array $envelope): ?array
    {
        if (isset($envelope['legacy_classification']) && is_array($envelope['legacy_classification'])) {
            return $envelope['legacy_classification'];
        }
        if (($envelope['schema_version'] ?? 1) === 1 && isset($envelope['editorial']) && is_array($envelope['editorial'])) {
            return ['article_type' => $envelope['editorial']['article_type'] ?? null,
                'ai_assistance' => $envelope['editorial']['ai_assistance'] ?? null,
                'original_schema_version' => 1];
        }
        return null;
    }

    public static function reviewIsCurrent(array $envelope, array $subject): bool
    {
        return isset($envelope['editorial'], $envelope['review']['fingerprint'])
            && hash_equals(self::fingerprint($envelope['editorial'], $subject), $envelope['review']['fingerprint']);
    }

    private static function text(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Text fields must be strings.');
        }
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags($value));
        if ($clean === null) {
            throw new \InvalidArgumentException('Invalid text encoding.');
        }
        return trim($clean);
    }
}
