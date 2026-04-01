# INTEGRATION.md – Guía de Integración para Otros Plugins

Este documento explica cómo otros plugins de WordPress pueden integrarse con **PSP Territorial** para acceder a los datos territoriales de Panamá.

---

## 1. Verificar que PSP Territorial está disponible

Antes de usar cualquier función, verifica que el plugin está activo:

```php
if ( ! function_exists( 'psp_territorial_available' ) || ! psp_territorial_available() ) {
    // PSP Territorial no está disponible
    return;
}
```

---

## 2. Funciones PHP públicas

### Obtener provincias

```php
$provinces = psp_get_provinces();
// Devuelve: array de objetos con {id, name, slug, code, type, level, ...}

foreach ( $provinces as $province ) {
    echo $province->name; // "Bocas del Toro"
    echo $province->id;   // 1
}
```

### Obtener territorios por tipo

```php
// Obtener todos los distritos
$districts = psp_get_territories( 'district' );

// Con filtros
$search_results = psp_get_territories( 'community', [
    'search' => 'bocas',
    'limit'  => 10,
] );
```

### Obtener hijos de un territorio

```php
// Distritos de una provincia
$districts = psp_get_children( 'province', $province_id );

// Corregimientos de un distrito
$corregimientos = psp_get_children( 'district', $district_id );

// Comunidades de un corregimiento
$communities = psp_get_children( 'corregimiento', $corregimiento_id );
```

### Obtener un territorio específico

```php
// Por ID
$territory = psp_get_territory( 42 );

// Por slug
$territory = psp_get_territory_by_slug( 'bocas-del-toro' );
```

### Obtener la ruta completa (breadcrumb)

```php
$path = psp_get_territory_path( $community_id );
// Devuelve: [Provincia, Distrito, Corregimiento, Comunidad]

foreach ( $path as $step ) {
    echo $step->name . ' (' . $step->type . ')';
}
```

### Verificar existencia

```php
if ( psp_territory_exists( 'community', 'bocas-del-toro' ) ) {
    // La comunidad "bocas-del-toro" existe
}
```

---

## 3. Generar selects HTML para formularios

### Select simple de provincias

```php
echo psp_get_select_html( 'province', [
    'name'        => 'provincia',
    'id'          => 'campo-provincia',
    'class'       => 'mi-select',
    'placeholder' => '— Selecciona una provincia —',
] );
```

### Selects dependientes (dropdown cascading)

Usa los atributos `data-*` para que el JavaScript del formulario sepa cargar los hijos:

```php
// Provincia
echo psp_get_select_html( 'province', [
    'name'  => 'provincia',
    'id'    => 'provincia-select',
    'class' => 'psp-select psp-cascade',
    'data'  => [
        'child-select' => 'distrito-select',
        'child-type'   => 'district',
    ],
] );

// Distrito (se llena via AJAX)
echo '<select name="distrito" id="distrito-select" class="psp-select psp-cascade"
    data-child-select="corregimiento-select" data-child-type="corregimiento">
    <option value="">— Primero selecciona una provincia —</option>
</select>';
```

El select dependiente puede llenarse via REST API:

```javascript
$('#provincia-select').on('change', function() {
    var provinceId = $(this).val();
    $.get('/wp-json/psp/v1/hierarchy/provinces/' + provinceId + '/districts', function(res) {
        var $select = $('#distrito-select').empty().append('<option value="">— Selecciona un distrito —</option>');
        res.data.forEach(function(d) {
            $select.append('<option value="' + d.id + '">' + d.name + '</option>');
        });
    });
});
```

---

## 4. Usar la clase PSP_Territorial_Query directamente

```php
// Obtener provincias
$provinces = PSP_Territorial_Query::get_provinces();

// Obtener hijos
$districts = PSP_Territorial_Query::get_children( 'province', $province_id );

// Ruta completa
$path = PSP_Territorial_Query::get_full_path( $entity_id );

// Por slug
$entity = PSP_Territorial_Query::get_by_slug( 'bocas-del-toro' );

// Verificar existencia
$exists = PSP_Territorial_Query::exists( 'community', 'la-palma' );
```

---

## 5. Metadatos de territorios

Tu plugin puede asociar metadatos propios a cualquier territorio:

```php
// Guardar metadato
psp_update_territory_meta( $territory_id, 'poblacion_estimada', 12500 );
psp_update_territory_meta( $territory_id, 'codigo_postal', '0401' );

// Leer metadato
$poblacion = psp_get_territory_meta( $territory_id, 'poblacion_estimada' );

// Leer todos los metadatos de un territorio
$all_meta = psp_get_territory_meta( $territory_id );
```

---

## 6. Hooks y Filtros

### Acciones (Hooks)

```php
// Antes de importar datos
add_action( 'psp_territorial_before_import', function() {
    // Tu lógica aquí
} );

// Después de importar datos
add_action( 'psp_territorial_after_import', function( $count ) {
    error_log( "PSP: $count territorios importados." );
} );

// Cuando se crea un territorio
add_action( 'psp_territorial_entity_created', function( $entity ) {
    // $entity es el objeto del nuevo territorio
} );

// Cuando se actualiza un territorio
add_action( 'psp_territorial_entity_updated', function( $old_entity, $new_entity ) {
    // Comparar cambios
} );

// Cuando se elimina un territorio
add_action( 'psp_territorial_entity_deleted', function( $entity ) {
    // Limpiar datos relacionados de tu plugin
} );
```

### Filtros

```php
// Modificar datos antes de guardar
add_filter( 'psp_territorial_entity_data', function( $data ) {
    // Ejemplo: forzar que todos sean activos
    $data['is_active'] = 1;
    return $data;
} );

// Personalizar queries de la API
add_filter( 'psp_territorial_api_query', function( $args ) {
    // Ejemplo: limitar resultados a 25
    $args['limit'] = min( $args['limit'], 25 );
    return $args;
} );

// Modificar el formato de respuesta
add_filter( 'psp_territorial_response_format', function( $data ) {
    // Agregar campo extra
    $data['display_name'] = strtoupper( $data['name'] );
    return $data;
} );
```

---

## 7. Ejemplo completo: formulario de dirección

```php
<?php
// En tu template o shortcode:
if ( psp_territorial_available() ) : ?>
    <div class="mi-formulario-direccion">
        <label>Provincia</label>
        <?php echo psp_get_select_html( 'province', [
            'name'        => 'address[province_id]',
            'id'          => 'addr-province',
            'placeholder' => '— Seleccionar provincia —',
        ] ); ?>

        <label>Distrito</label>
        <select name="address[district_id]" id="addr-district">
            <option value="">— Primero selecciona una provincia —</option>
        </select>

        <label>Corregimiento</label>
        <select name="address[corregimiento_id]" id="addr-corregimiento">
            <option value="">— Primero selecciona un distrito —</option>
        </select>

        <label>Comunidad</label>
        <select name="address[community_id]" id="addr-community">
            <option value="">— Primero selecciona un corregimiento —</option>
        </select>
    </div>

    <script>
    jQuery(function($) {
        var restBase = '<?php echo esc_js( rest_url( 'psp/v1' ) ); ?>';

        function loadChildren(endpoint, $target, placeholder) {
            $.get(endpoint, function(res) {
                $target.empty().append('<option value="">' + placeholder + '</option>');
                res.data.forEach(function(item) {
                    $target.append('<option value="' + item.id + '">' + item.name + '</option>');
                });
            });
        }

        $('#addr-province').on('change', function() {
            var id = $(this).val();
            if (!id) return;
            loadChildren(restBase + '/hierarchy/provinces/' + id + '/districts', $('#addr-district'), '— Seleccionar distrito —');
            $('#addr-corregimiento, #addr-community').html('<option value="">—</option>');
        });

        $('#addr-district').on('change', function() {
            var id = $(this).val();
            if (!id) return;
            loadChildren(restBase + '/hierarchy/districts/' + id + '/corregimientos', $('#addr-corregimiento'), '— Seleccionar corregimiento —');
            $('#addr-community').html('<option value="">—</option>');
        });

        $('#addr-corregimiento').on('change', function() {
            var id = $(this).val();
            if (!id) return;
            loadChildren(restBase + '/hierarchy/corregimientos/' + id + '/communities', $('#addr-community'), '— Seleccionar comunidad —');
        });
    });
    </script>
<?php endif; ?>
```

---

## 8. REST API desde JavaScript (sin PHP)

```javascript
const PSP_API = 'https://tu-sitio.com/wp-json/psp/v1';

// Obtener todas las provincias
fetch(`${PSP_API}/hierarchy/provinces`)
  .then(res => res.json())
  .then(data => {
    data.data.forEach(province => console.log(province.name));
  });

// Buscar territorios
fetch(`${PSP_API}/search?q=bocas&type=community`)
  .then(res => res.json())
  .then(data => console.log(data.data));

// Obtener ruta completa de un territorio
fetch(`${PSP_API}/path/community/100042`)
  .then(res => res.json())
  .then(data => {
    const path = data.data.map(t => t.name).join(' > ');
    console.log(path); // "Bocas del Toro > Bocas del Toro > Bocas del Toro > Bocas del Toro"
  });
```

---

## 9. Compatibilidad

- **WordPress:** 5.9+
- **PHP:** 7.4+
- **Conflictos:** Ninguno conocido. Usa prefijo `psp_territorial_` en todas las funciones, opciones y tablas para evitar conflictos.
