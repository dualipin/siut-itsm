---
name: vue-islands-development
description: Guidelines for building and maintaining Vue 3 components used as interactive Islands in Blade templates with useIslandForm. Use when creating, modifying, or debugging Vue components in resources/js/components, landing pages in resources/views/landing, island mounting via island.ts, or client-side form submissions on non-Inertia pages.
---

# Vue 3 Islands Development with `useIslandForm`

This project uses an **Islands Architecture** for all public-facing pages (`resources/views/landing/**`). Server-rendered Blade views provide maximum SEO indexability and social sharing previews, while Vue 3 components provide reactive interactivity as isolated "islands".

## Core Rules for AI Agents

1. **NO Inertia inside Vue Islands**:
   - Never import `@inertiajs/vue3` (`useForm`, `router`, `usePage`, etc.) inside Vue components intended for Blade islands (`resources/js/components/**`).
   - Blade views do NOT have an Inertia root component, so Inertia helpers will throw `TypeError: Cannot read properties of undefined (reading 'url')`.

2. **Always Use `useIslandForm` for Forms**:
   - Import from `@/composables/useIslandForm` (`resources/js/composables/useIslandForm.ts`).
   - Automatically handles CSRF tokens (`meta[name="csrf-token"]` and `XSRF-TOKEN` cookie).
   - Automatically maps Laravel 422 validation errors to `form.errors[field]`.
   - **Unified Reactive State**: Returns `reactive<IslandForm<T>>`. Properties `form.processing` and `form.statusMessage` are primitive values, preventing truthy `RefImpl` object evaluation bugs in templates (`v-if="form.processing"` and `v-if="form.statusMessage"`).
   - Exposes: `form.data`, `form.errors`, `form.processing`, `form.recentlySuccessful`, `form.statusMessage`, `form.statusType`, `form.clearErrors()`, `form.clearFeedback()`, `form.reset()`, and `form.post() / put() / patch() / delete()`.
   - **UX Standard**: `form.reset()` clears input data without wiping `statusMessage` or `recentlySuccessful`, allowing confirmation cards to remain visible. Use `form.clearFeedback()` when you explicitly need to dismiss the alert or reset the success screen.

3. **Mounting Islands in Blade**:
   ```blade
   <div data-vue="component/name" data-props="{{ json_encode(['propName' => $value]) }}"></div>
   ```
   - Component path is relative to `resources/js/components/` without `.vue`.
   - Props must be valid JSON in `data-props` attribute.

## Form Implementation Pattern

```vue
<script setup lang="ts">
import { useIslandForm } from '@/composables/useIslandForm'

const form = useIslandForm({
    name: '',
    email: '',
    message: '',
})

const submit = async () => {
    await form.post('/api-or-web-route', {
        onSuccess: (response) => {
            console.log('Success:', response)
            form.reset()
        },
        onError: (errors) => {
            console.warn('Validation errors:', errors)
        },
    })
}
</script>

<template>
    <form @submit.prevent="submit">
        <input v-model="form.data.name" type="text" :class="{ 'input-error': form.errors.name }" />
        <span v-if="form.errors.name" class="text-error text-xs">{{ form.errors.name[0] }}</span>

        <button type="submit" :disabled="form.processing">
            <span v-if="form.processing">Enviando...</span>
            <span v-else>Enviar</span>
        </button>

        <div v-if="form.statusMessage" :class="form.statusType === 'success' ? 'alert-success' : 'alert-error'">
            {{ form.statusMessage }}
        </div>
    </form>
</template>
```

## Hydration Strategies (Supported by `island.ts`)

- `data-client="load"` (default): Mounts as soon as DOM is ready.
- `data-client="idle"`: Mounts when browser is idle (`requestIdleCallback`).
- `data-client="visible"`: Mounts only when the element enters viewport (`IntersectionObserver`).
- `data-client="media"`: Mounts only when media query matches (using `data-client-media="(max-width: 768px)"`).
