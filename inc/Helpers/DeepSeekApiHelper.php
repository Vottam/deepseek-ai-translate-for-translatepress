<?php
namespace hollisho\translatepress\translate\deepseek\inc\Helpers;

use Exception;

class DeepSeekApiHelper {

    const supportedLanguages =  [
        'ar' => 'Arabic',
        'bg' => 'Bulgarian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'de' => 'German',
        'el' => 'Greek',
        'en' => 'English',
        'es' => 'Spanish',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'hu' => 'Hungarian',
        'id' => 'Indonesian',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'nb' => 'Norwegian Bokmål',
        'nl' => 'Dutch',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sv' => 'Swedish',
        'tr' => 'Turkish',
        'uk' => 'Ukrainian',
        'zh-cn' => 'Chinese (simplified)',
        'zh-tw' => 'Chinese (traditional)',

    ];

    /**
     * @param $texts
     * @param $sourceLang
     * @param $targetLang
     * @return string
     * @author Hollis
     *
     * $data = [
     * 'model' => 'deepseek-chat',
     * 'messages' => [
     *     ['role' => 'user', 'content' => $prompt]
     * ],
     * 'temperature' => 0.3,
     * 'max_tokens' => 4000  // 增加token限制以适应长文本
     * ];
     */
    public static function convert($texts, $sourceLang, $targetLang)
    {
        // Build batch prompt WITHOUT numbering to avoid LLM adding "1. " prefix.
        // Use a clear delimiter between items.
        $itemsList = implode("\n---\n", $texts);

        $targetName = self::supportedLanguages[$targetLang] ?? $targetLang;

        if ($sourceLang === 'auto' || empty($sourceLang)) {
            $prompt = "Translate the following content to {$targetName}. "
                . "Maintain a professional tone. "
                . "Return ONLY the translated text. "
                . "Do NOT add numbering, bullets, labels, quotes, markdown, explanations, or comments. "
                . "Separate each translated item with '---' (same delimiter as input). "
                . "Preserve all placeholders (%s, %d, {name}, {{var}}), HTML tags, and shortcodes.\n\n"
                . $itemsList;
        } else {
            $sourceName = self::supportedLanguages[$sourceLang] ?? $sourceLang;
            $prompt = "Translate the following {$sourceName} content to {$targetName}. "
                . "Maintain a professional tone. "
                . "Return ONLY the translated text. "
                . "Do NOT add numbering, bullets, labels, quotes, markdown, explanations, or comments. "
                . "Separate each translated item with '---' (same delimiter as input). "
                . "Preserve all placeholders (%s, %d, {name}, {{var}}), HTML tags, and shortcodes.\n\n"
                . $itemsList;
        }
        return $prompt;
    }



    public static function parseTranslatedItems($content, $expectedCount) {
        // Split by '---' delimiter first (new format), then by newlines.
        $items = [];

        // Try splitting by '---' delimiter (new batch format).
        if (strpos($content, '---') !== false) {
            $parts = preg_split('/\n?---\n?/', $content, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($parts as $part) {
                $item = trim($part);
                // Defensive: remove residual numbering like "1. " or "2. " etc.
                $item = preg_replace('/^\s*\d+\.\s+/', '', $item);
                if (!empty($item)) {
                    $items[] = $item;
                }
            }
            return $items;
        }

        // Fallback: split by newlines.
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            // Defensive: remove residual numbering like "1. " or "2. " etc.
            $line = preg_replace('/^\s*\d+\.\s+/', '', $line);
            if (!empty($line)) {
                $items[] = $line;
            }
        }

        // If only one item and it's the entire content, return it.
        if (empty($items) && !empty($content)) {
            $content = trim($content);
            $content = preg_replace('/^\s*\d+\.\s+/', '', $content);
            if (!empty($content)) {
                $items[] = $content;
            }
        }

        return $items;
    }

}


