<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Step 5.1: German and English stay in step. A key that exists in one language and not in the
 * other fails here, and so does a key the code asks for that neither language has.
 *
 * Laravel's own vocabulary (auth, passwords, validation) ships in English with the framework, so
 * lang/en does not repeat those files; lang/de translates them and must not invent keys.
 */
class LangParityTest extends TestCase
{
    private const FRAMEWORK_FILES = ['auth', 'passwords', 'validation'];

    /** Groups that hold names chosen per language rather than copy; they differ by design. */
    private const FREE_KEYS = ['validation.attributes', 'validation.custom'];

    /** @return array<string, array<string, string>> file => [dotted key => text] */
    private function load(string $dir): array
    {
        $files = [];

        foreach (glob($dir.'/*.php') as $path) {
            $files[basename($path, '.php')] = $this->flatten(require $path);
        }

        return $files;
    }

    /** @return array<string, string> */
    private function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];

        foreach ($messages as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = (string) $value;
            }
        }

        return $flat;
    }

    /** @return list<string> */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[A-Za-z_]+/', $text, $matches);
        $found = array_values(array_unique($matches[0]));
        sort($found);

        return $found;
    }

    public function test_every_file_and_key_exists_in_both_languages(): void
    {
        $de = $this->load(lang_path('de'));
        $en = $this->load(lang_path('en'));

        $deOnly = array_diff(array_keys($de), array_keys($en), self::FRAMEWORK_FILES);
        $this->assertSame([], array_values($deOnly), 'lang/de has files that lang/en does not (only the auth, passwords and validation files of Laravel itself may be missing).');
        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($de))), 'lang/en has files that lang/de does not.');

        $problems = [];

        foreach (array_intersect(array_keys($de), array_keys($en)) as $file) {
            foreach (array_diff_key($de[$file], $en[$file]) as $key => $text) {
                $problems[] = "{$file}.{$key} is missing in English";
            }
            foreach (array_diff_key($en[$file], $de[$file]) as $key => $text) {
                $problems[] = "{$file}.{$key} is missing in German";
            }
        }

        $this->assertSame([], $problems, "Keys exist in one language only:\n".implode("\n", $problems));
    }

    public function test_the_german_copies_of_laravels_own_files_match_the_keys_of_the_framework(): void
    {
        $de = $this->load(lang_path('de'));
        $framework = $this->load(base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en'));

        $problems = [];

        foreach (self::FRAMEWORK_FILES as $file) {
            $this->assertArrayHasKey($file, $de, "lang/de/{$file}.php is missing.");
            foreach (array_keys($de[$file]) as $key) {
                if (! $this->isFree($file.'.'.$key) && ! array_key_exists($key, $framework[$file])) {
                    $problems[] = "{$file}.{$key} does not exist in the English file of the framework";
                }
            }
            foreach (array_keys($framework[$file]) as $key) {
                if (! $this->isFree($file.'.'.$key) && ! array_key_exists($key, $de[$file])) {
                    $problems[] = "{$file}.{$key} is not translated into German";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_both_languages_use_the_same_placeholders_for_a_key(): void
    {
        $de = $this->load(lang_path('de'));
        $en = $this->load(lang_path('en'));
        $problems = [];

        foreach (array_intersect(array_keys($de), array_keys($en)) as $file) {
            foreach (array_intersect_key($de[$file], $en[$file]) as $key => $german) {
                if ($this->placeholders($german) !== $this->placeholders($en[$file][$key])) {
                    $problems[] = "{$file}.{$key}: ".implode(' ', $this->placeholders($german)).' (de) vs '.implode(' ', $this->placeholders($en[$file][$key])).' (en)';
                }
            }
        }

        $this->assertSame([], $problems, "Placeholders differ:\n".implode("\n", $problems));
    }

    public function test_every_key_the_code_asks_for_exists_in_both_languages(): void
    {
        $de = $this->load(lang_path('de'));
        $en = $this->load(lang_path('en'));
        $framework = $this->load(base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en'));
        $en += array_intersect_key($framework, array_flip(self::FRAMEWORK_FILES));

        $missing = [];

        foreach ($this->usedKeys() as $key => $where) {
            [$file, $rest] = explode('.', $key, 2);
            if (! isset($de[$file]) && ! isset($en[$file])) {
                continue; // not one of our groups (a plain sentence, a package namespace)
            }
            foreach (['de' => $de, 'en' => $en] as $language => $files) {
                if (! $this->has($files[$file] ?? [], $rest)) {
                    $missing[] = "{$key} ({$where}) is missing in {$language}";
                }
            }
        }

        $this->assertSame([], $missing, "Keys the code uses but a language lacks:\n".implode("\n", $missing));
    }

    private function isFree(string $key): bool
    {
        foreach (self::FREE_KEYS as $free) {
            if ($key === $free || str_starts_with($key, $free.'.')) {
                return true;
            }
        }

        return false;
    }

    /** A leaf key, or a group (the start of leaf keys), exists. */
    private function has(array $flat, string $key): bool
    {
        if (array_key_exists($key, $flat)) {
            return true;
        }

        foreach (array_keys($flat) as $existing) {
            if (str_starts_with($existing, $key.'.')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> literal key => first file that asks for it */
    private function usedKeys(): array
    {
        $roots = [base_path('app'), resource_path('views'), base_path('routes')];
        $used = [];
        $pattern = '/(?:__|trans|trans_choice|@lang|Lang::get)\(\s*[\'"]([A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)+)[\'"](?!\s*\.)/';

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                if (preg_match_all($pattern, file_get_contents($file->getPathname()), $matches)) {
                    foreach ($matches[1] as $key) {
                        $used[$key] ??= str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                    }
                }
            }
        }

        return $used;
    }
}
