<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use function Livewire\on;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if (! $user->is_active) {
                return false;
            }

            $target = $arguments[0] ?? null;
            if ($target) {
                $policy = Gate::getPolicyFor($target);
                if ($policy && method_exists($policy, $ability)) {
                    return null;
                }
            }

            if (Gate::has($ability)) {
                return null;
            }

            if ($user->isAgremiado()) {
                return false;
            }

            return null;
        });

        // Prevenir ErrorException "Undefined array key 'children'" en Livewire
        // cuando un snapshot llega sin la clave 'children' en el memo (bug en SupportNestingComponents)
        if (function_exists('Livewire\on')) {
            on('request', function () {
                return function (mixed $payload): mixed {
                    if (! is_array($payload)) {
                        return $payload;
                    }

                    foreach ($payload as &$componentPayload) {
                        if (isset($componentPayload['snapshot']) && is_string($componentPayload['snapshot'])) {
                            $snapshot = json_decode($componentPayload['snapshot'], true);
                            if (is_array($snapshot) && isset($snapshot['memo']) && is_array($snapshot['memo'])) {
                                if (! array_key_exists('children', $snapshot['memo'])) {
                                    $snapshot['memo']['children'] = [];
                                    $componentPayload['snapshot'] = json_encode($snapshot);
                                }
                            }
                        }
                    }

                    return $payload;
                };
            });
        }
    }
}
