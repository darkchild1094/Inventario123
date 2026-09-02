# Especificación de Roles y Permisos — Inventario123

Sistema de control de activos FEMSA/OXXO. Jerarquía de datos: `negocio → región → plaza → tienda → stock → activo`.

Roles del sistema: `admin`, `coordinador`, `fs` (Field Service), `ati` (Asesor de Tecnología de Información).

## Tabla resumen

| Rol | Ver activos | Crear/editar activos | Eliminar (soft-delete) | Gestionar usuarios | Exportar a Excel | Alcance de datos |
|---|---|---|---|---|---|---|
| **Admin** | Todas las unidades/plazas | Sí | Sí | Sí, todos los usuarios sin restricción | Sí, todo | Todas las unidades de negocio y plazas registradas en la BD, aunque no estén asignadas a él |
| **Coordinador** | De sus plazas asignadas | Sí | Sí | Sí, solo usuarios de sus plazas asignadas (no todos en general) | Sí, con estructura especial (ver detalle) | Sus plazas asignadas |
| **FS** | Solo los activos asignados a él mismo (NO ve los de su bodega) | Sí, solo lo asignado a él mismo | Sí, solo lo asignado a él mismo | Solo puede editar su propio usuario | No puede exportar | Sus plazas asignadas |
| **ATI** | Los activos asignados a él + los que están en bodega de su plaza | Sí, solo lo asignado a él mismo | Sí, solo lo asignado a él mismo | Solo su propio usuario + puede agregar usuarios tipo ATI en toda la región y las plazas de esa región | Sí, con estructura especial (ver detalle) | Su plaza asignada (una sola) |

## Detalle por rol

### Admin
- Ve, crea, edita y elimina activos sin restricción alguna.
- Gestiona todos los usuarios del sistema.
- Exporta a Excel absolutamente todo (todas las unidades de negocio, todas las plazas).
- Su alcance de datos NO depende de asignaciones: tiene visibilidad total de todas las unidades de negocio y plazas registradas en la base de datos, incluso las que no le fueron asignadas directamente.

### Coordinador
- Ve activos de sus plazas asignadas (puede tener varias).
- Crea, edita y elimina (soft-delete) activos dentro de su alcance.
- Gestiona usuarios, pero SOLO los que pertenecen a sus plazas asignadas (no puede administrar usuarios fuera de su alcance, a diferencia del admin).
- Exportación a Excel: genera un archivo con una pestaña por plaza y una pestaña por el stock de cada ingeniero (FS) de esa plaza. Incluye a todos los usuarios en general de su plaza (no solo FS).

### FS (Field Service)
- Solo ve los activos que están asignados directamente a él mismo. NO ve los activos que están en la bodega de su plaza/tienda.
- Crea, edita y elimina (soft-delete) únicamente lo que tiene asignado a él mismo.
- Solo puede editar su propio usuario (sin permisos sobre otros usuarios).
- No tiene permiso de exportación a Excel.
- Alcance de datos: sus plazas asignadas.

### ATI (Asesor de Tecnología de Información)
- Ve los activos asignados a él mismo + los activos que están en bodega de su plaza (a diferencia del FS, si ve la bodega).
- Crea, edita y elimina (soft-delete) únicamente lo que tiene asignado a él mismo.
- Gestión de usuarios: puede editar su propio usuario, y además puede AGREGAR usuarios tipo ATI en toda la región y en todas las plazas que pertenecen a esa región (permiso especial, más amplio que su alcance normal de datos).
- Exportación a Excel: solo puede exportar los activos de la bodega de su propia unidad de negocio y su plaza, además del stock de los FS de su plaza y unidad de negocio.
- Alcance de datos: una sola plaza asignada (no puede tener más de una).

## Notas para implementación

- El alcance de "gestionar usuarios" del ATI es una excepción notable: aunque su alcance de datos normal es una sola plaza, su permiso de creación de usuarios ATI se extiende a nivel región (todas las plazas de esa región).
- La diferencia clave entre FS y ATI en "ver activos": FS NO ve bodega, ATI SÍ ve bodega (de su plaza).
- La exportación de Coordinador y ATI requiere lógica de generación de Excel multi-pestaña (por plaza / por ingeniero), no un export plano.
- Considerar implementar esto como policies/middleware por acción (ver, crear, editar, eliminar, gestionar_usuarios, exportar) con filtros de query scoped por rol, en lugar de checks hardcodeados repetidos.
