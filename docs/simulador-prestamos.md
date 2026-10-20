# Simulador de préstamos

Los pasos para simular un préstamo son los mismos que para registrar un préstamo real, pero sin afectar la base de datos. El flujo es idéntico al **Flujo 1** descrito en [prestamos.md](./prestamos.md), pero con la diferencia de que se agrega un paso extra preguntando por la **Tasa de Interés**. No se deberán subir archivos ni firmar documentos. La ruta pública debe ser `/simulador-prestamo`.

## Documentos PDF

Se genera el documento PDF de la corrida financiera y la tabla de amortización, pero no se genera el contrato ni el acta de desembolso. El PDF generado es solo para fines de simulación.