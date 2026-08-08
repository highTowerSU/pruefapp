<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class DisplayPreferenceService
{
    public const THEMES = ['light', 'dark', 'auto'];
    public const CONTRASTS = ['standard', 'system', 'white_black', 'yellow_black', 'green_black'];
    public const FONT_SCALES = ['standard', 'large', 'xlarge', 'xxlarge'];
    public const FONT_WEIGHTS = ['standard', 'bold'];
    public const FONT_FAMILIES = ['system', 'sans', 'serif', 'mono'];
    public const MOTIONS = ['system', 'reduce'];

    /** @return array{theme:string,contrast:string,font_scale:string,font_weight:string,font_family:string,motion:string} */
    public static function forUser(?int $userId): array
    {
        $defaults = ['theme' => 'auto', 'contrast' => 'standard', 'font_scale' => 'standard', 'font_weight' => 'standard', 'font_family' => 'system', 'motion' => 'system'];
        if (($userId ?? 0) < 1) return $defaults;
        $bean = R::findOne('userdisplaypreference', ' oauthuser_id = ? ', [$userId]);
        if ($bean === null) return $defaults;
        foreach ($defaults as $key => $fallback) {
            $allowed = match ($key) {
                'theme' => self::THEMES, 'contrast' => self::CONTRASTS, 'font_scale' => self::FONT_SCALES, 'font_weight' => self::FONT_WEIGHTS, 'font_family' => self::FONT_FAMILIES, default => self::MOTIONS,
            };
            $defaults[$key] = in_array((string) ($bean->$key ?? ''), $allowed, true) ? (string) $bean->$key : $fallback;
        }
        return $defaults;
    }

    /** @return array{theme:string,contrast:string,font_scale:string,font_weight:string,font_family:string,motion:string} */
    public static function save(int $userId, array $values): array
    {
        $preference = [
            'theme' => self::value($values['theme'] ?? '', self::THEMES, 'auto'),
            'contrast' => self::value($values['contrast'] ?? '', self::CONTRASTS, 'standard'),
            'font_scale' => self::value($values['font_scale'] ?? '', self::FONT_SCALES, 'standard'),
            'font_weight' => self::value($values['font_weight'] ?? '', self::FONT_WEIGHTS, 'standard'),
            'font_family' => self::value($values['font_family'] ?? '', self::FONT_FAMILIES, 'system'),
            'motion' => self::value($values['motion'] ?? '', self::MOTIONS, 'system'),
        ];
        $bean = R::findOne('userdisplaypreference', ' oauthuser_id = ? ', [$userId]);
        if ($bean === null) {
            $bean = R::dispense('userdisplaypreference');
            $bean->oauthuser_id = $userId;
        }
        $bean->theme = $preference['theme'];
        $bean->contrast = $preference['contrast'];
        $bean->font_scale = $preference['font_scale'];
        $bean->font_weight = $preference['font_weight'];
        $bean->font_family = $preference['font_family'];
        $bean->motion = $preference['motion'];
        $bean->updated_at = date(DATE_ATOM);
        R::store($bean);
        return $preference;
    }

    /** Preserve values written by the short-lived underscore table migration. */
    public static function migrateLegacyRows(): void
    {
        try {
            $rows = R::getAll('SELECT oauthuser_id, theme, contrast, font_scale, motion, updated_at FROM user_display_preference');
        } catch (Throwable) {
            return;
        }
        foreach ($rows as $row) {
            $userId = (int) ($row['oauthuser_id'] ?? 0);
            if ($userId < 1 || R::findOne('userdisplaypreference', ' oauthuser_id = ? ', [$userId]) !== null) continue;
            $bean = R::dispense('userdisplaypreference');
            $bean->oauthuser_id = $userId;
            $bean->theme = self::value($row['theme'] ?? '', self::THEMES, 'auto');
            $bean->contrast = self::value($row['contrast'] ?? '', self::CONTRASTS, 'standard');
            $bean->font_scale = self::value($row['font_scale'] ?? '', self::FONT_SCALES, 'standard');
            $bean->font_weight = 'standard';
            $bean->font_family = 'system';
            $bean->motion = self::value($row['motion'] ?? '', self::MOTIONS, 'system');
            $bean->updated_at = (string) ($row['updated_at'] ?? date(DATE_ATOM));
            R::store($bean);
        }
    }

    private static function value(mixed $value, array $allowed, string $fallback): string
    {
        return in_array((string) $value, $allowed, true) ? (string) $value : $fallback;
    }
}
