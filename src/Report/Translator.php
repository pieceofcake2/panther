<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use InvalidArgumentException;
use JsonException;

/**
 * The headings and labels of the report in a language.
 *
 * The messages are JSON files named after the language: `en.json` and `ja.json` in `resources/report/locales/`
 * of this package, and the files of `PANTHER_REPORT_LOCALE_DIR`, which add languages or replace messages of the
 * bundled ones. A message that a language does not have is the English one. Placeholders are written as
 * `{name}`, so that a language can put the values in its own order.
 */
final class Translator
{
    /**
     * The directory of the bundled languages
     */
    public const LOCALE_DIR = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'report' . DIRECTORY_SEPARATOR . 'locales';

    /**
     * @var array<string, string>
     */
    private array $messages;

    /**
     * Constructor
     *
     * @param string $language Language, e.g. `en` or `ja`
     * @param string|null $localeDir Directory of additional `<language>.json` files
     * @throws \InvalidArgumentException When neither this package nor `$localeDir` has the language
     */
    public function __construct(private string $language = Config::DEFAULT_LANGUAGE, ?string $localeDir = null)
    {
        if (!preg_match('/\A[A-Za-z0-9_-]+\z/', $language)) {
            throw new InvalidArgumentException(sprintf('Invalid report language "%s".', $language));
        }

        $files = [
            self::LOCALE_DIR . DIRECTORY_SEPARATOR . Config::DEFAULT_LANGUAGE . '.json',
            self::LOCALE_DIR . DIRECTORY_SEPARATOR . $language . '.json',
        ];
        if ($localeDir !== null) {
            $files[] = rtrim($localeDir, '/\\') . DIRECTORY_SEPARATOR . $language . '.json';
        }

        $found = $language === Config::DEFAULT_LANGUAGE;
        $this->messages = [];
        foreach (array_unique($files) as $i => $file) {
            if (!is_file($file)) {
                continue;
            }
            $found = $found || $i > 0;
            $this->messages = array_merge($this->messages, self::load($file));
        }

        if (!$found) {
            throw new InvalidArgumentException(sprintf(
                'No messages for the report language "%s": add %s.json to PANTHER_REPORT_LOCALE_DIR.',
                $language,
                $language,
            ));
        }
    }

    /**
     * The language, e.g. for the `lang` attribute of the HTML
     *
     * @return string
     */
    public function language(): string
    {
        return $this->language;
    }

    /**
     * The message of a key with its placeholders replaced
     *
     * @param string $key Key, e.g. `field.commit`
     * @param array<string, string|int|float> $params Values of the `{name}` placeholders
     * @return string The key itself when no language has it
     */
    public function translate(string $key, array $params = []): string
    {
        $message = $this->messages[$key] ?? $key;
        $replace = [];
        foreach ($params as $name => $value) {
            $replace['{' . $name . '}'] = (string)$value;
        }

        return strtr($message, $replace);
    }

    /**
     * The messages of a JSON file
     *
     * @param string $file Path of the file
     * @return array<string, string>
     * @throws \InvalidArgumentException When the file is not a JSON object of strings
     */
    private static function load(string $file): array
    {
        try {
            $messages = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('Invalid JSON in %s: %s', $file, $e->getMessage()), 0, $e);
        }
        if (!is_array($messages)) {
            throw new InvalidArgumentException(sprintf('%s must be a JSON object of messages.', $file));
        }

        $result = [];
        foreach ($messages as $key => $message) {
            if (is_string($message)) {
                $result[(string)$key] = $message;
            }
        }

        return $result;
    }
}
