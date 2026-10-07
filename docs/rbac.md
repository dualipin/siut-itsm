# Control de accesos por Rol

En este documento se detallan los niveles de privilegios por cada rol


> Para fines prácticos el rol `Lider` y `Admin` gozaran de los mismos privilegios 

### Roles existentes (de `UserRole.php` — NO modificar ese archivo)
```php
// app\Enums\UserRole.php
case Agremiado = 'agremiado';
case Lider = 'lider';
case Admin = 'admin';
```
- **Solicitan** préstamos: `Agremiado`, `Lider`, `Admin`
- **Revisan / aprueban / validan**: `Admin`, `Lider`
