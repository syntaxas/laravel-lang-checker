<?php

namespace Syntaxas\LaravelLangChecker\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class LanguageCheckCommand extends Command
{
    public $signature = 'laravel-language:check';

    public $description = 'Check the language files for inconsistencies and missing translations.';

    public function handle(): int
    {
        $langPath = lang_path();

        if (!is_dir($langPath)) {
            $this->error('Language directory does not exist: ' . $langPath);

            return self::FAILURE;
        }

        $localeDirectories = $this->resolveLocaleDirectories($langPath);

        if ($localeDirectories === []) {
            $this->warn('No locale directories found in: ' . $langPath);

            return self::SUCCESS;
        }

        $this->info('Checking locales: ' . implode(', ', array_keys($localeDirectories)));

        $localeFiles = [];

        foreach ($localeDirectories as $locale => $directoryPath) {
            $localeFiles[$locale] = $this->resolveLocaleFiles($directoryPath);
        }

        $errorCount = 0;
        $errorCount += $this->checkMissingFiles($localeFiles);
        $errorCount += $this->checkMissingKeysAndTypeMismatches($localeFiles);

        if ($errorCount > 0) {
            $this->newLine();
            $this->error('Language files check failed with ' . $errorCount . ' issue(s).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Language files are consistent across all locales.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function resolveLocaleDirectories(string $langPath): array
    {
        $directories = [];
        $items = scandir($langPath);

        if ($items === false) {
            return $directories;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $absolutePath = $langPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($absolutePath)) {
                $directories[$item] = $absolutePath;
            }
        }

        ksort($directories);

        return $directories;
    }

    /**
     * @return array<string, string>
     */
    private function resolveLocaleFiles(string $localeDirectoryPath): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localeDirectoryPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $filePath = $fileInfo->getPathname();
            $relativePath = substr((string) $filePath, strlen($localeDirectoryPath) + 1);
            $normalizedPath = str_replace('\\', '/', $relativePath);
            $files[$normalizedPath] = $filePath;
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  array<string, array<string, string>>  $localeFiles
     */
    private function checkMissingFiles(array $localeFiles): int
    {
        $errors = 0;
        $allFiles = [];

        foreach ($localeFiles as $files) {
            $allFiles = array_merge($allFiles, array_keys($files));
        }

        $allFiles = array_values(array_unique($allFiles));
        sort($allFiles);

        foreach ($allFiles as $file) {
            foreach ($localeFiles as $locale => $files) {
                if (!array_key_exists($file, $files)) {
                    $errors++;
                    $this->error(sprintf('Missing file for locale %s: %s', strtoupper($locale), $file));
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, array<string, string>>  $localeFiles
     */
    private function checkMissingKeysAndTypeMismatches(array $localeFiles): int
    {
        $errors = 0;
        $allFiles = [];

        foreach ($localeFiles as $files) {
            $allFiles = array_merge($allFiles, array_keys($files));
        }

        $allFiles = array_values(array_unique($allFiles));
        sort($allFiles);

        foreach ($allFiles as $file) {
            $leafKeysByLocale = [];
            $nodeTypeMapByLocale = [];

            foreach ($localeFiles as $locale => $files) {
                if (!array_key_exists($file, $files)) {
                    continue;
                }

                $translations = $this->loadTranslationFile($files[$file], $locale, $file);

                if ($translations === null) {
                    $errors++;

                    continue;
                }

                $leafKeysByLocale[$locale] = [];
                $nodeTypeMapByLocale[$locale] = [];
                $this->collectTranslationTreeMetadata($translations, '', $leafKeysByLocale[$locale], $nodeTypeMapByLocale[$locale]);
                $this->warnAboutEmptyTranslations($translations, $locale, $file);
            }

            $allLeafKeys = [];

            foreach ($leafKeysByLocale as $leafKeys) {
                $allLeafKeys = array_merge($allLeafKeys, $leafKeys);
            }

            $allLeafKeys = array_values(array_unique($allLeafKeys));
            sort($allLeafKeys);

            foreach ($allLeafKeys as $key) {
                foreach ($leafKeysByLocale as $locale => $keys) {
                    if (!in_array($key, $keys, true)) {
                        $errors++;
                        $this->error(sprintf('%s locale missing %s translation in %s', strtoupper($locale), $key, $file));
                    }
                }
            }

            $allNodePaths = [];

            foreach ($nodeTypeMapByLocale as $nodeTypes) {
                $allNodePaths = array_merge($allNodePaths, array_keys($nodeTypes));
            }

            $allNodePaths = array_values(array_unique($allNodePaths));
            sort($allNodePaths);

            foreach ($allNodePaths as $nodePath) {
                $typesByLocale = [];

                foreach ($nodeTypeMapByLocale as $locale => $nodeTypes) {
                    if (array_key_exists($nodePath, $nodeTypes)) {
                        $typesByLocale[$locale] = $nodeTypes[$nodePath];
                    }
                }

                if (count(array_unique($typesByLocale)) > 1) {
                    $errors++;

                    $details = collect($typesByLocale)
                        ->map(fn (string $type, string $locale): string => strtoupper($locale) . '=' . $type)
                        ->implode(', ');

                    $this->error(sprintf('Type mismatch for key %s in %s (%s)', $nodePath, $file, $details));
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadTranslationFile(string $path, string $locale, string $relativeFilePath): ?array
    {
        try {
            /** @var mixed $translations */
            $translations = require $path;
        } catch (Throwable $throwable) {
            $this->error(sprintf(
                'Could not load %s for locale %s: %s',
                $relativeFilePath,
                strtoupper($locale),
                $throwable->getMessage(),
            ));

            return null;
        }

        if (!is_array($translations)) {
            $this->error(sprintf(
                'Translation file %s for locale %s must return an array.',
                $relativeFilePath,
                strtoupper($locale),
            ));

            return null;
        }

        return $translations;
    }

    /**
     * @param  array<string, mixed>  $translations
     * @param  array<int, string>  $leafKeys
     * @param  array<string, string>  $nodeTypes
     */
    private function collectTranslationTreeMetadata(array $translations, string $prefix, array &$leafKeys, array &$nodeTypes): void
    {
        foreach ($translations as $key => $value) {
            $keySegment = (string) $key;
            $nodePath = $prefix === '' ? $keySegment : $prefix . '.' . $keySegment;

            if (is_array($value)) {
                $nodeTypes[$nodePath] = 'array';
                $this->collectTranslationTreeMetadata($value, $nodePath, $leafKeys, $nodeTypes);

                continue;
            }

            $nodeTypes[$nodePath] = 'value';
            $leafKeys[] = $nodePath;
        }
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private function warnAboutEmptyTranslations(array $translations, string $locale, string $file): void
    {
        $flattened = [];
        $nodeTypes = [];

        $this->collectTranslationTreeMetadata($translations, '', $flattened, $nodeTypes);

        foreach ($flattened as $key) {
            $value = data_get($translations, $key);

            if ($value === '') {
                $this->warn(sprintf('Empty translation in %s (%s): %s', $file, strtoupper($locale), $key));
            }
        }
    }
}
