=== PSP Territorial ===
Contributors: psp-panama
Tags: panama, territorial, provincias, distritos, corregimientos, api
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

División Político Administrativa de la República de Panamá con API REST, panel CRUD y sistema extensible.

== Description ==

**PSP Territorial** proporciona la División Político Administrativa completa de la República de Panamá (provincias, distritos, corregimientos y comunidades) directamente en tu instalación de WordPress.

= Características =

* **13 provincias / comarcas** con toda la jerarquía hasta comunidades (~12 972 entidades)
* **Panel de administración** CRUD: listar, crear, editar y eliminar cualquier entidad territorial
* **REST API pública** con 9 endpoints jerárquicos
* **Importador / exportador** de datos en JSON
* **Hooks y filtros** para integración fácil con otros plugins
* Compatible con WordPress Multisite
* Internacionalización preparada (i18n)

= REST API =

```
GET /wp-json/psp-territorial/v1/provincias
GET /wp-json/psp-territorial/v1/provincias/{id}
GET /wp-json/psp-territorial/v1/distritos
GET /wp-json/psp-territorial/v1/distritos/{id}
GET /wp-json/psp-territorial/v1/corregimientos
GET /wp-json/psp-territorial/v1/corregimientos/{id}
GET /wp-json/psp-territorial/v1/comunidades
GET /wp-json/psp-territorial/v1/comunidades/{id}
GET /wp-json/psp-territorial/v1/jerarquia
```

= Hooks disponibles =

**Acciones:**
* `psp_territorial_before_import` – antes de importar los datos
* `psp_territorial_after_import` – después de importar los datos
* `psp_territorial_entity_created` – cuando se crea una entidad
* `psp_territorial_entity_updated` – cuando se edita una entidad
* `psp_territorial_entity_deleted` – cuando se elimina una entidad

**Filtros:**
* `psp_territorial_provinces_list` – filtrar/modificar la lista de provincias en la API
* `psp_territorial_validate_entity` – añadir validaciones al guardar una entidad

= Uso desde otro plugin =

```php
// Obtener todas las provincias
$provincias = PSP_Database::get_by_type( 'provincia' );

// Obtener distritos de una provincia
$distritos = PSP_Database::get_children( $provincia_id, 'distrito' );

// Reaccionar cuando se crea una entidad
add_action( 'psp_territorial_entity_created', function( $id, $name, $type, $parent_id ) {
    // Tu lógica aquí
}, 10, 4 );
```

== Installation ==

1. Sube la carpeta `psp-territorial` al directorio `/wp-content/plugins/`.
2. Activa el plugin desde el menú **Plugins** de WordPress.
3. Al activar, el plugin crea la tabla en la base de datos e importa todos los datos automáticamente.
4. Accede al menú **Territorial** en el panel de administración.

== Frequently Asked Questions ==

= ¿Se pueden agregar nuevas entidades? =

Sí. Desde **Territorial → Agregar nuevo** puedes agregar provincias, distritos, corregimientos o comunidades.

= ¿Se puede reimportar los datos originales? =

Sí. Desde **Territorial → Ajustes** puedes reimportar los datos originales (borra los datos actuales).

= ¿Qué pasa al desinstalar el plugin? =

Se eliminan la tabla de la base de datos y las opciones del plugin.

== Screenshots ==

1. Panel de administración – listado de entidades
2. Formulario de creación / edición
3. Página de ajustes con estadísticas y exportación

== Changelog ==

= 1.0.0 =
* Versión inicial.
* Importación completa de las 13 provincias/comarcas de Panamá.
* Panel CRUD, REST API, importador/exportador, hooks y filtros.

== Upgrade Notice ==

= 1.0.0 =
Primera versión estable.
