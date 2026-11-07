# Control de accesos por Rol

En este documento se detallan los niveles de privilegios por cada rol del portal Filament (`/portal`).

> Para fines prácticos el rol `Lider` y `Admin` gozan de los mismos privilegios, con la diferencia de que **solo puede existir un `Lider`**. Las únicas diferencias entre ambos están en la sección [Excepciones documentadas](#3-excepciones-documentadas).

### Roles existentes (de `UserRole.php` — NO modificar ese archivo)
```php
// app\Enums\UserRole.php
case Agremiado = 'agremiado';
case Lider = 'lider';
case Admin = 'admin';
```

- **Solicitan** préstamos: `Agremiado`, `Lider`, `Admin`
- **Revisan / aprueban / validan**: `Admin`, `Lider`

Helpers existentes en `User`: `isAgremiado()`, `isLider()`, `isAdmin()`, `isLeaderOrAdmin()`.

---

## 1. Principios

1. **Deny-by-default.** Todo recurso, página o widget del panel es **solo `Admin`/`Lider`** hasta que se habilite explícitamente para el `Agremiado` en la matriz de este documento.
2. **Una Policy por modelo es la única fuente de verdad.** Los `Resource` de Filament, los controladores y los servicios consultan la policy. Se eliminan los `canAccess()` sueltos y los `->hidden(fn () => isAgremiado())` que duplican reglas.
3. **Defensa en profundidad.** La UI oculta, la policy autoriza, y el servicio vuelve a verificar antes de mutar. Cada capa tiene su propio test.
4. **Sin paquete de permisos.** Solo hay dos niveles reales de privilegio; no se instala Spatie Permission ni Filament Shield.
5. **Cuenta inactiva = sin acceso.** `is_active = false` bloquea el panel (`User::canAccessPanel()`) y toda descarga de archivos.

---

## 2. Matriz de acceso

Leyenda: ✅ permitido · ❌ prohibido · 🔒 solo registros propios

### 2.1 Recursos del panel

| Recurso / Página | Agremiado | Admin / Líder |
|---|---|---|
| Dashboard (`AccountWidget`) | ✅ | ✅ |
| `QrGeneratorWidget` | ❌ | ✅ |
| **Préstamos** (`LoanResource`) | 🔒 ver, crear, enviar y descargar documentos propios | ✅ ver todos, revisar, aprobar, rechazar, lista de espera, desembolsar, regenerar documentos |
| **Solicitudes** (`UserRequestResource`) | 🔒 crear, ver y cancelar propias (solo `Pending`) | ✅ ver todas, editar, cambiar estado, eliminar |
| **Mensajería** (`Messages`) | ✅ solo con `Lider`/`Admin` | ✅ con cualquier usuario |
| **Mi Perfil** (`Profile`) | ✅ campos limitados (ver 2.3) | ✅ |
| `UserResource` (Usuarios) | ❌ | ✅ (con restricciones de 3 y 4) |
| `BenefitResource` | ❌ | ✅ |
| `RequestTypeResource` | ❌ | ✅ |
| `InquiryResource` (Dudas) | ❌ | ✅ |
| `ContactSubmissionResource` | ❌ | ✅ (solo lectura/respuesta) |
| `PostResource` | ❌ | ✅ |
| `ThemeResource` | ❌ | ✅ |
| `TransparencyRecordResource` | ❌ | ✅ |
| `FinancialReportResource` | ❌ | ✅ |
| `AnnualPetitionResource` | ❌ | ✅ |
| `PetitionRequestResource` | ❌ | ✅ (solo lectura, sin navegación) |
| `PostDeployMaintenance` | ❌ | ✅ **solo `Admin`** (excepción) |

> Las noticias y la transparencia las lee el agremiado en el **sitio público**, no en el panel.

### 2.2 Préstamos por estado (agremiado dueño)

| Estado | Acciones del agremiado |
|---|---|
| `Borrador` | editar, firmar, enviar, eliminar |
| `Enviada`, `EnRevision`, `ListaEspera` | solo ver y descargar sus documentos |
| `Aprobada`, `Rechazada`, `Desembolsada` | solo ver y descargar sus documentos |

- No existe estado ni acción de "retirar solicitud". Si el agremiado quiere retirarla, lo pide por mensajería a un líder.
- Los préstamos y solicitudes **eliminados (soft delete)** dejan de ser visibles para el agremiado. Solo `Admin`/`Lider` los ven y pueden restaurarlos.

### 2.3 Perfil del agremiado

| Campo | Agremiado | Admin / Líder |
|---|---|---|
| Nombre, apellidos, teléfono, dirección, foto | ✅ editable | ✅ |
| Contraseña (con contraseña actual) | ✅ | ✅ |
| Documentos de afiliación (subida) | ✅ | ✅ (además valida/rechaza) |
| **Email**, **fecha de nacimiento**, **salario** | ❌ solo lectura | ✅ |
| CURP, categoría, NSS, fecha de contratación, estado de cuenta, rol | ❌ solo lectura | ✅ (rol según sección 4) |

Para cambiar un campo de solo lectura, el agremiado crea una solicitud (`UserRequest`) que un admin valida y aplica.

### 2.4 Archivos y descargas

| Archivo | Quién puede acceder |
|---|---|
| Documentos y evidencias de un préstamo | dueño del préstamo, `Admin`, `Lider` (`LoanPolicy::downloadDocument` / `view`) |
| Documentos de afiliación de un usuario | el propio usuario, `Admin`, `Lider` |
| Adjuntos de mensajería | participantes de la conversación |
| Documentos de transparencia (portal) | `Agremiado`: solo de registros **publicados**. `Admin`/`Lider`: todos (`TransparencyDocumentPolicy`) |

---

## 3. Excepciones documentadas

Son las **únicas** diferencias entre `Admin` y `Lider`, y las únicas restricciones sobre ellos.

1. **`PostDeployMaintenance` es solo `Admin`.** La página ejecuta acciones de sistema; el `Lider` no necesariamente es técnico.
2. **El `Lider` está protegido frente al `Admin`.** Un `Admin` no puede editar, desactivar, eliminar ni cambiar la contraseña del `Lider`. El `Lider` sí puede hacerlo con todos. Evita suplantar al líder cambiándole la contraseña.
3. **Separación de funciones en préstamos.** Nadie puede **aprobar, rechazar, poner en lista de espera, desembolsar ni validar evidencias de su propio préstamo**. Debe hacerlo otro `Admin`/`Lider`.

---

## 4. Gestión de usuarios

### 4.1 Unicidad del Líder
- Solo puede existir **un** usuario `Lider`. Cuenta cualquier usuario no eliminado con ese rol, **aunque esté inactivo**.
- Se valida en una Action dentro de `DB::transaction` con `lockForUpdate()`. No hay índice único en BD (depende del driver).
- Para sustituir al líder, primero se degrada al actual (ver 4.2).

### 4.2 Asignación de roles
- El `Select` de rol en `UserForm` solo ofrece `Agremiado` y `Admin`.
- `Lider` se asigna únicamente con la acción **"Transferir liderazgo"**: la ejecuta solo el líder actual y, en una sola transacción, degrada al líder actual a `Admin` y promueve al nuevo.

### 4.3 Autoprotección
- Un usuario **no puede** cambiar su propio rol, desactivarse ni eliminarse desde `UserResource`.
- El sistema **nunca** debe quedarse sin al menos un `Admin`/`Lider` activo.

### 4.4 Auditoría
Cada acción sensible se registra en el canal de log `audit` con `actor_id`, `target_id`, `action` y, si aplica, valor anterior y nuevo. **Sin PII ni contraseñas.**

Acciones auditadas: cambio de rol, transferencia de liderazgo, activar/desactivar, eliminar/restaurar, restablecer contraseña, validar/rechazar documentos.

Los préstamos y solicitudes ya cuentan con su historial (`LoanStatusHistory`, `UserRequestHistory`).

---

## 5. Autorización en servicios

`LoanService` (`markInReview`, `approve`, `waitlist`, `reject`, `disburse`, `reviewEvidence`) verifica con la policy que `$reviewer` tiene permiso y lanza `AuthorizationException` si no. Esto incluye la regla de no revisar el propio préstamo.

Las acciones de `ViewLoan` usan `->authorize('review')` en lugar de un `if (! isLeaderOrAdmin())` global.

---

## 6. Plan de implementación

| Fase | Alcance | Prioridad |
|---|---|---|
| **F1** | Policies por modelo y deny-by-default. Cerrar `AnnualPetitions`, `FinancialReports`, `Posts`, `Themes`, `TransparencyRecords`, hoy abiertos a cualquier agremiado activo. Test que recorre todos los recursos/páginas/widgets como agremiado y verifica la lista blanca | Alta |
| **F2** | Gestión de usuarios: líder único, transferir liderazgo, autoprotección, protección del líder frente al admin, auditoría | Alta |
| **F3** | Autorización en servicios y regla de autorrevisión | Alta |
| **F4** | Perfil de solo lectura (email, nacimiento, salario), ocultar soft deletes al agremiado, cancelar solicitud propia | Media |
| **F5** | `TransparencyDocumentPolicy` y control de documentos no publicados | Media |

Cada fase se entrega con sus tests en `tests/Feature/Rbac/` (Pest) y con `vendor/bin/pint --dirty --format agent`.

---

## 7. Checklist de tests

- [x] Agremiado recibe 403 en todo recurso/página fuera de la lista blanca (test que itera el panel).
- [x] Un recurso nuevo sin policy ni `canAccess` no es visible para el agremiado.
- [x] Agremiado solo ve y descarga sus propios préstamos y solicitudes; recibe 403/404 con los de otro.
- [x] Agremiado no ve préstamos ni solicitudes eliminados.
- [x] Agremiado solo cancela su solicitud en `Pending`.
- [x] Agremiado no puede editar email, fecha de nacimiento ni salario desde el perfil.
- [x] Admin y Líder revisan préstamos ajenos; ninguno puede revisar el propio (policy **y** servicio).
- [x] `PostDeployMaintenance` permite `Admin` y niega `Lider`.
- [x] No se puede crear ni promover un segundo `Lider` (incluye líder inactivo).
- [x] "Transferir liderazgo" es atómico y solo lo ejecuta el líder actual.
- [x] Un usuario no puede cambiar su rol, desactivarse ni eliminarse; no se puede dejar el sistema sin admin/líder activo.
- [x] Un `Admin` no puede editar, desactivar, eliminar ni cambiar la contraseña del `Lider`.
- [x] Cada acción sensible escribe en el canal `audit` sin PII.
- [x] Agremiado no abre documentos de transparencia de registros no publicados.
- [x] Usuario inactivo no accede al panel ni a descargas.
