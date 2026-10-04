---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Keep the tmpfile() polyfill — production host disables it
Producción corre en hosting compartido cuyo php.ini trae `tmpfile` y `highlight_file` en `disable_functions` (además de exec, shell_exec, proc_open, symlink, pcntl_exec, posix_*). Livewire llama `tmpfile()` en `TemporaryUploadedFile.php:25` y `:129` para cada subida de archivo, así que sin el polyfill que vive en `bootstrap/app.php` NO hay uploads en Filament. No borrar ese bloque. Está guardado con `function_exists('tmpfile')` para volverse no-op si el host lo habilita. Si algún día dan de alta `symlink`, `public/storage` deja de poder crearse con `storage:link`.
