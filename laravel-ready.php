<?php

$env            = environment();
$configurations = configurations();

/** Verification Zone */
if (!file_exists($autoload = __DIR__ . '/vendor/autoload.php')) {
    exit("Please, run \"composer install\" before running this script." . PHP_EOL);
}

if ($env && str_contains($env, 'APP_ENV=production')) {
    exit("For safety, you cannot run this script in production." . PHP_EOL);
}
/** End Verification Zone */

require $autoload;

use GuzzleHttp\Client;

use function Laravel\Prompts\{confirm, error, info, multiselect, select, spin, text};

use Symfony\Component\Process\Process;

/** Default Zone */
$actions = [
    '1-executeEnvironmentPreparation'        => 'Prepare .env with Database Credentials',
    '7-executeSeederPreparation'             => 'Prepare DatabaseSeeder',
    '9-executeAppServiceProviderPreparation' => 'Prepare AppServiceProvider',
    '6-executeLivewirePreparation'           => 'Install Livewire',
    '2-executePintPreparation'               => '[Dev. Tool] Install Laravel Pint',
    '3-executeLaraStanPreparation'           => '[Dev. Tool] Install LaraStan',
    '4-executeLaravelDebugBarPreparation'    => '[Dev. Tool] Install Laravel DebugBar',
    '5-executeIdeHelperPreparation'          => '[Dev. Tool] Install Laravel IDE Helper',
    '8-executeMigrations'                    => 'Run Migrations',
    '10-executeCommentsRemoval'              => 'Remove Unnecessary Laravel Comments',
];

$messages = [
    'executeEnvironmentPreparation'        => 'Preparing Environment',
    'executeLivewirePreparation'           => 'Installing Livewire',
    'executeSeederPreparation'             => 'Preparing DatabaseSeeder',
    'executeAppServiceProviderPreparation' => 'Preparing AppServiceProvider',
    'executePintPreparation'               => 'Installing Laravel Pint',
    'executeLaraStanPreparation'           => 'Installing LaraStan',
    'executeLaravelDebugBarPreparation'    => 'Installing Laravel Debugbar',
    'executeIdeHelperPreparation'          => 'Installing Laravel IDE Helper',
    'executeMigrations'                    => 'Running Migrations',
    'executeValetPreparation'              => 'Preparing Valet',
    'executeCommentsRemoval'               => 'Removing Unnecessary Comments',
];

$steps    = [];
$valet    = null;
$livewire = null;
$format   = false;

$livewireSelector = function () {
    return select('Select Livewire version:', [
        'livewire/livewire:^3.0' => 'Livewire [3.x]',
        'livewire/livewire:^2.0' => 'Livewire [2.x]',
    ]);
};
/** End Default Zone */

/** Execution Zone */
$type = select('What do you want to do?', [
    'packages' => 'Install Dev. Tools Packages',
    'project'  => 'Prepare New Project',
]);

if ($type === 'packages') {
    /** Packages Only */
    $packages = collect($actions)
        ->filter(fn ($key) => str_contains($key, '[Dev. Tool]'))
        ->mapWithKeys(fn ($value, $key) => [$key => str_replace('[Dev. Tool] ', '', $value)])
        ->toArray();

    $selecteds = multiselect('Select the packages:', $packages, scroll: 20, required: true);

    if (in_array('executeLivewirePreparation', array_keys($selecteds))) {
        $livewire    = $livewireSelector();
        $selecteds[] = 'executeAlpineJsPreparation';
    }

    $steps = $selecteds;
} else {
    /** New Project */
    $actions = collect($actions)
        ->map(fn ($value) => str_replace('[Dev. Tool] ', '', $value))
        ->toArray();

    $steps = multiselect('What do you want to do?', $actions, scroll: 20, required: true);

    // Organizating the execution order by the number before hyphen
    $steps = collect($steps)
        ->map(fn ($value) => explode('-', $value))
        ->sortBy(fn ($value) => $value[0])
        ->map(fn ($value) => $value[1])
        ->values()
        ->toArray();

    if (in_array('executeLivewirePreparation', $steps)) {
        $livewire = $livewireSelector();
    }

    $process = new Process(['which', 'valet']);
    $process->run();

    if (filled(trim($process->getOutput())) && confirm('Do you want to generate a Valet link?', false)) {
        $valet = text(
            'Enter the link name',
            required: true,
            validate: function ($value) {
                if (str_contains($value, ' ')) {
                    return 'The link name cannot contain spaces';
                }

                if (str_contains($value, '.test')) {
                    return 'The link name cannot contain .test';
                }

                return null;
            },
            hint: "Use . to current folder. You don't need to add .test"
        );

        $steps[] = 'executeValetPreparation';
    }

    if (in_array('executePintPreparation', $steps)) {
        $format = confirm('Do you want to format the code after the script runs?');
    }
}
/** End Execution Zone */

/** Functions Zone */
function environment(): bool|string
{
    return file_get_contents('.env');
}

function configurations(): ?array
{
    $user = getenv('HOME');

    if (!$user) {
        return null;
    }

    $content = [];

    foreach (explode("\n", file_get_contents("$user/.laravel")) as $value) {
        if (empty($value)) {
            continue;
        }

        [$key, $value] = explode('=', $value);

        $content[$key] = $value;
    }

    return $content;
}

function result(string $message): void
{
    file_put_contents('storage/logs/laravel-ready.log', $message . PHP_EOL, FILE_APPEND);
}

function executeEnvironmentPreparation(): bool|string
{
    global $env, $configurations;

    if (blank($configurations)) {
        return 'Unable to prepare the environment. Please, review the docs.';
    }

    $connection = data_get($configurations, 'DB_CONNECTION');
    $host       = data_get($configurations, 'DB_HOST');
    $port       = data_get($configurations, 'DB_PORT');
    $database   = data_get($configurations, 'DB_DATABASE');
    $username   = data_get($configurations, 'DB_USERNAME');
    $password   = data_get($configurations, 'DB_PASSWORD');

    try {
        $content = str_replace('DB_CONNECTION=mysql', "DB_CONNECTION=$connection", $env);

        if ($host) {
            $content = str_replace('DB_HOST=127.0.0.1', "DB_HOST=$host", $content);
        }

        if ($port) {
            $content = str_replace('DB_PORT=3306', "DB_PORT=$port", $content);
        }

        $content = preg_replace('/^(DB_DATABASE\s*=\s*).*$/m', "DB_DATABASE=$database", $content);

        if ($username) {
            $content = str_replace('DB_USERNAME=root', "DB_USERNAME=$username", $content);
        }

        if ($password) {
            $content = str_replace('DB_PASSWORD=', "DB_PASSWORD=$password", $content);
        }

        file_put_contents('.env', $content);

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executeLivewirePreparation(): bool|string
{
    global $livewire;

    $desired  = str($livewire)->replace('livewire/livewire:', '')->__toString();
    $composer = json_decode(file_get_contents('composer.json'), true);

    if (($installed = data_get($composer, 'require.livewire/livewire')) && $installed !== $desired) {
        return 'Livewire is already installed in a different version.';
    }

    try {
        if (($result = executeCommand("composer require $livewire")) !== true) {
            return $result;
        }

        if ($livewire === 'livewire/livewire:^2.0') {
            return true;
        }

        return executeAlpineJsRemovalPreparation();
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executeSeederPreparation(): bool|string
{
    try {
        $file  = file_get_contents('database/seeders/DatabaseSeeder.php');
        $lines = explode("\n", $file);

        foreach ($lines as $key => $line) {
            if (
                empty($line) ||
                !str_contains($line, '//') ||
                str_contains($line, 'WithoutModelEvents') ||
                str_contains($line, 'factory(10)')
            ) {
                continue;
            }

            $lines[$key] = str_replace('//', '', $line);
        }

        file_put_contents('database/seeders/DatabaseSeeder.php', implode("\n", $lines));

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executeAppServiceProviderPreparation(): bool|string
{
    try {
        $file  = file_get_contents('app/Providers/AppServiceProvider.php');
        $lines = explode("\n", $file);

        foreach ($lines as $key => $line) {
            if (empty($line) || $key !== 21) {
                continue;
            }

            $lines[$key] = str_replace('//', 'auth()->loginUsingId(1);', $line);
        }

        file_put_contents('app/Providers/AppServiceProvider.php', implode("\n", $lines));

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executeAlpineJsRemovalPreparation(): bool|string
{
    try {
        if (($status = executeCommand("npm remove alpinejs")) !== true) {
            return $status;
        }

        unlink(__DIR__.'/resources/js/app.js');
        file_put_contents(__DIR__.'/resources/js/app.js', "import './bootstrap';");

        return executeCommand("npm run build");
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executePintPreparation(): bool|string
{
    global $configurations;

    if (blank($configurations)) {
        return 'Unable to prepare the Laravel Pint. Please, review the docs.';
    }

    try {
        if (($status = executeCommand("composer require laravel/pint --dev")) !== true) {
            return $status;
        }

        $preset   = $configurations['PINT_PRESET'] ?? null;
        $external = filled($preset) && str_contains($preset, 'http');
        $content  = $external ? (new Client())->get($preset) : ['preset' => $preset];

        file_put_contents('pint.json', $external ? $content->getBody()->getContents() : json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $composer                  = json_decode(file_get_contents('composer.json'));
        $composer->scripts->format = './vendor/bin/pint';
        file_put_contents('composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executeLaraStanPreparation(): bool|string
{
    try {
        if (($status = executeCommand("composer require nunomaduro/larastan:^2.0 --dev")) !== true) {
            return $status;
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

        $composer                   = json_decode(file_get_contents('composer.json'));
        $composer->scripts->analyse = './vendor/bin/phpstan analyse --memory-limit=2G';
        file_put_contents('composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executeLaravelDebugBarPreparation(): bool|string
{
    return executeCommand("composer require barryvdh/laravel-debugbar --dev");
}

function executeIdeHelperPreparation(): bool|string
{
    return executeCommand("composer require barryvdh/laravel-ide-helper --dev");
}

function executeMigrations(): bool|string
{
    return executeCommand("php artisan migrate:fresh --seed");
}

function executeValetPreparation(): bool|string
{
    global $valet;

    if (!$valet) {
        return true;
    }

    try {
        if (($status = executeCommand("valet link $valet")) !== true) {
            return $status;
        }

        $env = environment();

        preg_match('/APP_URL=(.*)/', $env, $matches);

        $env = str_replace($matches[0], "APP_URL=http://$valet.test", $env);

        file_put_contents('.env', $env);

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executeCommentsRemoval(): bool|string
{
    try {
        function filesRecursively($directory): array
        {
            $fileList = [];

            $files = glob($directory . '/*');

            foreach ($files as $file) {
                if (is_dir($file)) {
                    $fileList = array_merge($fileList, filesRecursively($file));
                } else {
                    $fileList[] = $file;
                }
            }

            return $fileList;
        }

        $files = array_merge(filesRecursively(__DIR__ . '/app'), filesRecursively(__DIR__ . '/database'));

        foreach ($files as $file) {
            $content = preg_replace('/\/\*(.*?)\*\//s', '', file_get_contents($file));

            file_put_contents($file, $content);
        }

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function executeCommand(string $command): bool|string
{
    try {
        Process::fromShellCommandline("$command")
            ->setTty(false)
            ->setTimeout(null)
            ->run();

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}
/** End Functions Zone */

/** Steps Execution */
foreach ($steps as $step) {
    info($messages[$step] . "...");

    try {
        if (($result = $step()) !== true) {
            throw new Exception($result);
        }

        info($messages[$step] . " ✅") . PHP_EOL;
    } catch (Exception $exception) {
        error($exception->getMessage()) . PHP_EOL;

        result($exception->getMessage());
    }
}
/** End Steps Execution */

/** Extra Steps Zone */
if ($format) {
    info('Formatting Code...');

    try {
        if (($result = executeCommand("composer format")) !== true) {
            throw new Exception($result);
        }

        info("Code Formatted ✅") . PHP_EOL;
    } catch (Exception $exception) {
        error($exception->getMessage()) . PHP_EOL;
    }
}

if (in_array('executePintPreparation', $steps) && in_array('executeLaraStanPreparation', $steps)) {
    $composer = json_decode(file_get_contents('composer.json'));

    $composer->scripts->test = [
        './vendor/bin/pint --test',
        './vendor/bin/phpstan analyse --memory-limit=2G',
    ];

    file_put_contents('composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}
/** End Extra Steps Zone */

/** Final */
info("Your project is ready to be used! 🚀 Deleting script in 3 seconds...");
sleep(3);
unlink(__FILE__);
