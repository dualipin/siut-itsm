<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/**
 * Fallback for hosts that list `tmpfile` in `disable_functions`.
 *
 * Disabled functions are removed from the function table, so this declaration
 * does not conflict and unqualified calls inside namespaces still resolve to it.
 * Livewire requires it for every file upload (see TemporaryUploadedFile).
 *
 * The file must stay on disk: Livewire hands the URI to Symfony's UploadedFile,
 * whose constructor rejects a path that fails `is_file()`. Real `tmpfile()` is
 * unlinked by the engine at request shutdown, so the cleanup is done here.
 *
 * @return resource|false
 */
if (! function_exists('tmpfile')) {
    function tmpfile(): mixed
    {
        $path = @tempnam(sys_get_temp_dir(), 'php');

        if ($path === false) {
            return false;
        }

        $handle = @fopen($path, 'w+b');

        if ($handle === false) {
            @unlink($path);

            return false;
        }

        register_shutdown_function(static function () use ($handle, $path): void {
            @fclose($handle);
            @unlink($path);
        });

        return $handle;
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('filament.portal.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
