<?php

function createLangFile(string $langPath, string $locale, string $file, array $translations): void
{
    $dir = $langPath . '/' . $locale;
    $subDir = dirname($file);

    if ($subDir !== '.') {
        $dir .= '/' . $subDir;
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents(
        $langPath . '/' . $locale . '/' . $file,
        "<?php\n\nreturn " . var_export($translations, true) . ";\n",
    );
}

function deletePath(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (!is_dir($path)) {
        unlink($path);

        return;
    }

    foreach (scandir($path) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        deletePath($path . '/' . $item);
    }

    rmdir($path);
}

beforeEach(function () {
    $this->langPath = sys_get_temp_dir() . '/lang-checker-test-' . uniqid();
    mkdir($this->langPath, 0755, true);
    $this->app->useLangPath($this->langPath);
});

afterEach(function () {
    deletePath($this->langPath);
});

it('fails when lang directory does not exist', function () {
    rmdir($this->langPath);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Language directory does not exist')
        ->assertFailed();
});

it('succeeds with warning when no locale directories found', function () {
    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('No locale directories found')
        ->assertSuccessful();
});

it('lists checked locales on start', function () {
    createLangFile($this->langPath, 'en', 'messages.php', ['welcome' => 'Welcome']);
    createLangFile($this->langPath, 'lt', 'messages.php', ['welcome' => 'Sveiki']);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Checking locales: en, lt')
        ->assertSuccessful();
});

it('succeeds when all locales are consistent', function () {
    createLangFile($this->langPath, 'en', 'messages.php', ['welcome' => 'Welcome', 'goodbye' => 'Goodbye']);
    createLangFile($this->langPath, 'lt', 'messages.php', ['welcome' => 'Sveiki', 'goodbye' => 'Viso']);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Language files are consistent across all locales')
        ->assertSuccessful();
});

it('fails when a file is missing in one locale', function () {
    createLangFile($this->langPath, 'en', 'messages.php', ['welcome' => 'Welcome']);
    mkdir($this->langPath . '/lt', 0755, true);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Missing file for locale LT: messages.php')
        ->assertFailed();
});

it('fails when a translation key is missing in one locale', function () {
    createLangFile($this->langPath, 'en', 'messages.php', ['welcome' => 'Welcome', 'goodbye' => 'Goodbye']);
    createLangFile($this->langPath, 'lt', 'messages.php', ['welcome' => 'Sveiki']);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('LT locale missing goodbye translation in messages.php')
        ->assertFailed();
});

it('fails when a nested translation key is missing in one locale', function () {
    createLangFile($this->langPath, 'en', 'messages.php', [
        'nav' => ['home' => 'Home', 'about' => 'About'],
    ]);
    createLangFile($this->langPath, 'lt', 'messages.php', [
        'nav' => ['home' => 'Pagrindinis'],
    ]);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('LT locale missing nav.about translation in messages.php')
        ->assertFailed();
});

it('fails when a key has a type mismatch between locales', function () {
    createLangFile($this->langPath, 'en', 'messages.php', [
        'nav' => ['home' => 'Home', 'about' => 'About'],
    ]);
    createLangFile($this->langPath, 'lt', 'messages.php', [
        'nav' => 'Navigacija',
    ]);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Type mismatch for key nav in messages.php')
        ->assertFailed();
});

it('warns about empty translations but still succeeds', function () {
    createLangFile($this->langPath, 'en', 'messages.php', ['welcome' => '', 'goodbye' => 'Goodbye']);
    createLangFile($this->langPath, 'lt', 'messages.php', ['welcome' => 'Sveiki', 'goodbye' => 'Viso']);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Empty translation in messages.php (EN): welcome')
        ->assertSuccessful();
});

it('reports the total number of issues on failure', function () {
    createLangFile($this->langPath, 'en', 'messages.php', ['welcome' => 'Welcome', 'goodbye' => 'Goodbye']);
    createLangFile($this->langPath, 'lt', 'messages.php', ['welcome' => 'Sveiki']);
    createLangFile($this->langPath, 'en', 'auth.php', ['failed' => 'Failed']);
    // lt locale is missing auth.php entirely

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('2 issue(s)')
        ->assertFailed();
});

it('handles files in subdirectories', function () {
    createLangFile($this->langPath, 'en', 'admin/dashboard.php', ['title' => 'Dashboard', 'users' => 'Users']);
    createLangFile($this->langPath, 'lt', 'admin/dashboard.php', ['title' => 'Skydelis', 'users' => 'Vartotojai']);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Language files are consistent across all locales')
        ->assertSuccessful();
});

it('fails when a subdirectory file is missing in one locale', function () {
    createLangFile($this->langPath, 'en', 'admin/dashboard.php', ['title' => 'Dashboard']);
    mkdir($this->langPath . '/lt/admin', 0755, true);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Missing file for locale LT: admin/dashboard.php')
        ->assertFailed();
});

it('fails when translation file does not return an array', function () {
    mkdir($this->langPath . '/en', 0755, true);
    mkdir($this->langPath . '/lt', 0755, true);
    file_put_contents($this->langPath . '/en/messages.php', "<?php\n\nreturn 'not an array';\n");
    createLangFile($this->langPath, 'lt', 'messages.php', ['welcome' => 'Sveiki']);

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Translation file messages.php for locale EN must return an array')
        ->assertFailed();
});

it('handles multiple locales consistently', function () {
    foreach (['en', 'lt', 'de'] as $locale) {
        createLangFile($this->langPath, $locale, 'messages.php', ['hello' => 'Hello', 'bye' => 'Bye']);
    }

    $this->artisan('laravel-language:check')
        ->expectsOutputToContain('Language files are consistent across all locales')
        ->assertSuccessful();
});
