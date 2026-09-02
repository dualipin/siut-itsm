<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Frontend Architecture: Blade + Vue Islands & `useIslandForm`

This project uses an **Islands Architecture** for the public landing pages (`resources/views/landing/**`):
- **Server-rendered Blade views** deliver 100% indexable HTML for optimal SEO, fast First Contentful Paint (FCP), and rich OpenGraph social sharing previews (WhatsApp, Twitter/X, Facebook).
- **Vue 3 Islands** (`resources/js/components/**`) provide reactive interactivity only where needed (forms, dynamic modals, loan simulator, etc.), mounted automatically via `resources/js/island.ts` through `<div data-vue="component/path" data-props="..."></div>`.

### Form Handling in Vue Islands: `useIslandForm`

When creating forms inside Vue islands, **DO NOT** use `@inertiajs/vue3`'s `useForm` (which expects an active Inertia SPA root and will fail on standard Blade pages).

Instead, use the project's standardized composable:
[`resources/js/composables/useIslandForm.ts`](resources/js/composables/useIslandForm.ts)

#### Key Features:
1. **Automatic CSRF Token Injection**: Automatically extracts CSRF token from `<meta name="csrf-token">` or `XSRF-TOKEN` cookie.
2. **Laravel 422 Validation Error Mapping**: Automatically maps validation errors returned by Laravel Form Requests directly to `form.errors[field]`.
3. **Unified Reactive State**: Returns a unified `reactive<IslandForm<T>>` object. Properties like `form.processing` (boolean) and `form.statusMessage` (string) are primitive reactive values that evaluate properly in Vue template conditionals (`v-if="form.processing"` and `v-if="form.statusMessage"`).
4. **Clean HTTP Verbs**: Supports `form.post()`, `form.put()`, `form.patch()`, and `form.delete()`.

#### TypeScript Interface (`IslandForm<T>`):

```ts
export interface IslandForm<T extends Record<string, any>> {
    data: T
    errors: Partial<Record<keyof T, string[]>>
    processing: boolean
    recentlySuccessful: boolean
    statusMessage: string
    statusType: 'success' | 'error' | ''
    clearErrors: () => void
    clearFeedback: () => void
    reset: () => void
    submit: (method: 'post' | 'put' | 'patch' | 'delete', url: string, options?: IslandFormOptions<T>) => Promise<boolean>
    post: (url: string, options?: IslandFormOptions<T>) => Promise<boolean>
    put: (url: string, options?: IslandFormOptions<T>) => Promise<boolean>
    patch: (url: string, options?: IslandFormOptions<T>) => Promise<boolean>
    delete: (url: string, options?: IslandFormOptions<T>) => Promise<boolean>
}
```

> **UX Note**: Calling `form.reset()` clears input fields and validation errors, but **preserves** `form.statusMessage` and `form.recentlySuccessful` so success confirmation screens persist. Call `form.clearFeedback()` when you explicitly want to dismiss the success message or start a fresh form.

#### Usage Example:

```vue
<script setup lang="ts">
import { useIslandForm } from '@/composables/useIslandForm'

const form = useIslandForm({
    name: '',
    email: '',
    message: '',
})

const handleSubmit = async () => {
    await form.post('/contact', {
        onSuccess: (response) => {
            console.log('Enviado:', response)
            form.reset()
        },
        onError: (errors) => {
            console.warn('Errores de validación:', errors)
        },
    })
}
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div>
            <input v-model="form.data.name" type="text" :class="{ 'input-error': form.errors.name }" />
            <p v-if="form.errors.name" class="text-error text-xs">{{ form.errors.name[0] }}</p>
        </div>

        <div>
            <input v-model="form.data.email" type="email" :class="{ 'input-error': form.errors.email }" />
            <p v-if="form.errors.email" class="text-error text-xs">{{ form.errors.email[0] }}</p>
        </div>

        <div>
            <textarea v-model="form.data.message" :class="{ 'textarea-error': form.errors.message }"></textarea>
            <p v-if="form.errors.message" class="text-error text-xs">{{ form.errors.message[0] }}</p>
        </div>

        <button type="submit" :disabled="form.processing">
            <span v-if="form.processing">Enviando...</span>
            <span v-else>Enviar</span>
        </button>

        <!-- Feedback Alert -->
        <div v-if="form.statusMessage" :class="form.statusType === 'success' ? 'alert-success' : 'alert-error'">
            {{ form.statusMessage }}
        </div>
    </form>
</template>
```

#### Mounting the Island in Blade:

```blade
<div data-vue="contact-form" data-props="{{ json_encode(['someProp' => $value]) }}"></div>
```



## Roles y Privilegios

La plataforma gestiona el acceso mediante tres roles definidos en el enum [`App\Enums\UserRole`](app/Enums/UserRole.php):

| Rol | Identificador | Nivel de Privilegios | Descripción |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin` | **Total (Mismo nivel que Líder)** | Acceso administrativo completo al portal (`/portal`), gestión y administración general del sistema, atención de mensajes y dudas ciudadanas, y publicación de contenidos institucionales. |
| **Líder** | `lider` | **Total (Mismo nivel que Administrador)** | **Posee exactamente el mismo nivel de privilegios que el Administrador**. Dispone de las mismas facultades y capacidades de gestión y operación administrativa dentro de la plataforma. |
| **Agremiado** | `agremiado` | Estándar / Miembro | Acceso al portal para consultar su información personal y de perfil, enviar dudas o consultas y comunicarse mediante mensajería interna con líderes y administradores. |

> [!NOTE]
> **Equivalencia de Privilegios**: Tanto `admin` como `lider` tienen el mismo nivel de privilegios en el sistema. Para verificar permisos compartidos a nivel de modelo, políticas y controladores, se utiliza el método de conveniencia [`User::isLeaderOrAdmin()`](app/Models/User.php).

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
