<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class DisplayPreferenceService
{
    public const THEMES = ['light', 'dark', 'auto'];
    public const CONTRASTS = ['standard', 'system', 'yellow_black', 'green_black'];
    public const FONT_SCALES = ['standard', 'large'];
    public const MOTIONS = ['system', 'reduce'];

    /** @return array{theme:string,contrast:string,font_scale:string,motion:string} */
    public static function forUser(?int $userId): array
    {
        $defaults = ['theme' => 'auto', 'contrast' => 'standard', 'font_scale' => 'standard', 'motion' => 'system'];
        if (($userId ?? 0) < 1) return $defaults;
        $row = R::getRow('SELECT theme, contrast, font_scale, motion FROM user_display_preference WHERE oauthuser_id = ?', [$userId]);
        if ($row === []) return $defaults;
        foreach ($defaults as $key => $fallback) {
            $allowed = match ($key) {
                'theme' => self::THEMES, 'contrast' => self::CONTRASTS, 'font_scale' => self::FONT_SCALES, default => self::MOTIONS,
            };
            $defaults[$key] = in_array((string) ($row[$key] ?? ''), $allowed, true) ? (string) $row[$key] : $fallback;
        }
        return $defaults;
    }

    /** @return array{theme:string,contrast:string,font_scale:string,motion:string} */
    public static function save(int $userId, array $values): array
    {
        $preference = [
            'theme' => self::value($values['theme'] ?? '', self::THEMES, 'auto'),
            'contrast' => self::value($values['contrast'] ?? '', self::CONTRASTS, 'standard'),
            'font_scale' => self::value($values['font_scale'] ?? '', self::FONT_SCALES, 'standard'),
            'motion' => self::value($values['motion'] ?? '', self::MOTIONS, 'system'),
        ];
        $bean = R::findOne('user_display_preference', ' oauthuser_id = ? ', [$userId]);
        if ($bean === null) {
            $bean = R::dispense('user_display_preference');
            $bean->oauthuser_id = $userId;
        }
        $bean->theme = $preference['theme'];
        $bean->contrast = $preference['contrast'];
        $bean->font_scale = $preference['font_scale'];
        $bean->motion = $preference['motion'];
        $bean->updated_at = date(DATE_ATOM);
        R::store($bean);
        return $preference;
    }

    private static function value(mixed $value, array $allowed, string $fallback): string
    {
        return in_array((string) $value, $allowed, true) ? (string) $value : $fallback;
    }
}
