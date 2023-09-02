<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\{info, multiselect};

$selecteds = multiselect('Select what you want to do', [
    'prepareEnv' => 'Prepare .env with sqlite',
    'prepareLivewire' => 'Install Livewire',
    'prepareSeeder' => 'Prepare DatabaseSeeder',
    'prepareProvider' => 'Prepare AppServiceProvider',
    'prepareAlpine' => 'Remove AlpineJs',
    'preparePint' => 'Install Laravel Pint',
    'prepareLarastan' => 'Install LaraStan',
    'prepareDebug' => 'Install Laravel Debugbar',
    'prepareIde' => 'Install Laravel IDE Helper',
    'prepareMigration' => 'Run migrations',
], scroll: 20, required: true);

function prepareEnv(): void //OK
{
    $content = file_get_contents('.env');
    $folder = str(__DIR__)->afterLast('/')->value();

    $content = str_replace('DB_CONNECTION=mysql', 'DB_CONNECTION=sqlite', $content);
    $content = str_replace("DB_DATABASE=$folder", 'DB_DATABASE=/Users/aj/database/database.sqlite', $content);

    file_put_contents('.env', $content);
}

function prepareLivewire(): void // +/-
{
    try {
        Process::fromShellCommandline('composer require livewire/livewire')
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    } catch (Exception $e) {
        //TODO: possivelmente marcar cada etapa como erro/sucesso
        //
    }
}

function prepareSeeder(): void //OK
{
    $file = file_get_contents('database/seeders/DatabaseSeeder.php');
    $lines = explode("\n", $file);

    foreach ($lines as $key => $line) {
        //TODO: refactor aqui

        if (empty($line)) continue;

        if (!str_contains($line, '//')) continue;

        if (str_contains($line, 'WithoutModelEvents')) continue;

        if (str_contains($line, 'factory(10)')) continue;

        $lines[$key] = str_replace('//', '', $line);
    }

    file_put_contents('database/seeders/DatabaseSeeder.php', implode("\n", $lines));
}

function prepareProvider(): void // OK
{
    $file = file_get_contents('app/Providers/AppServiceProvider.php');
    $lines = explode("\n", $file);

    foreach ($lines as $key => $line) {
        if (empty($line)) continue;

        if ($key !== 21) continue;

        $lines[$key] = str_replace('//', 'auth()->loginUsingId(1);', $line);
    }

    file_put_contents('app/Providers/AppServiceProvider.php', implode("\n", $lines));
}

function prepareAlpine(): void // OK
{
    try {
        Process::fromShellCommandline('npm remove alpinejs')
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    } catch (Exception $e) {
        //TODO: possivelmente marcar cada etapa como erro/sucesso
        //
    }

    $file = file_get_contents('resources/js/app.js');
    $lines = explode("\n", $file);

    foreach ($lines as $key => $line) {
        $line = strtolower($line);

        if (!str_contains($line, 'alpine')) continue;

        unset($lines[$key]);
    }

    $lines = array_filter($lines);

    file_put_contents('resources/js/app.js', implode("\n", $lines));

    try {
        Process::fromShellCommandline('npm run build')
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    } catch (Exception $e) {
        //TODO: possivelmente marcar cada etapa como erro/sucesso
        //
    }
}

function preparePint(): void // OK
{
    try {
        Process::fromShellCommandline('composer require laravel/pint --dev')
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    } catch (Exception $e) {
        //TODO: possivelmente marcar cada etapa como erro/sucesso
        //
    }

    file_put_contents('pint.json', '');

    $content = (new Client())->get('https://gist.githubusercontent.com/devajmeireles/8c00117a89931c606ba4ebb2b5c58bd3/raw/e193a485029a46ad853aab526a92fd88359c149f/pint.json');

    file_put_contents('pint.json', $content->getBody()->getContents());

    $composer = json_decode(file_get_contents('composer.json'));
    $composer->scripts->format = './vendor/bin/pint';
    file_put_contents('composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function prepareLarastan(): void
{
    try {
        Process::fromShellCommandline('composer require nunomaduro/larastan:^2.0 --dev')
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    } catch (Exception $e) {
        //TODO: possivelmente marcar cada etapa como erro/sucesso
        //
    }

    $content = <<<FILE
includes:
    - ./vendor/nunomaduro/larastan/extension.neon

parameters:

    paths:
        - app/

    # Level 9 is the highest level
    level: 5

#    ignoreErrors:
#        - '#PHPDoc tag @var#'
#
#    excludePaths:
#        - ./*/*/FileToBeExcluded.php
#
#    checkMissingIterableValueType: false
FILE;

    file_put_contents('phpstan.neon', $content);

    $composer = json_decode(file_get_contents('composer.json'));
    $composer->scripts->analyse = './vendor/bin/phpstan analyse --memory-limit=2G';
    file_put_contents('composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function prepareDebug(): void // OK
{
    try {
        Process::fromShellCommandline('composer require barryvdh/laravel-debugbar --dev')
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    } catch (Exception $e) {
        //TODO: possivelmente marcar cada etapa como erro/sucesso
        //
    }
}

function prepareIde(): void // OK
{
    try {
        Process::fromShellCommandline('composer require --dev barryvdh/laravel-ide-helper')
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    } catch (Exception $e) {
        //TODO: possivelmente marcar cada etapa como erro/sucesso
        //
    }
}

function prepareMigration(): void // OK
{
    try {
        Process::fromShellCommandline('php artisan migrate:fresh --seed')
            ->setTty(true)
            ->setTimeout(null)
            ->run();
    } catch (Exception $e) {
        //TODO: possivelmente marcar cada etapa como erro/sucesso
        //
    }
}

foreach ($selecteds as $selected) {
    $selected();
}

//prepareEnv();
//prepareLivewire();
//prepareSeeder();
//prepareProvider();
//prepareAlpine();
//preparePint();
//prepareLarastan();
//prepareDebug();
//prepareIde();
//prepareMigration();

info('Done!');
