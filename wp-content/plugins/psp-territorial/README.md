# PSP Territorial

**Version:** 1.0.0  
**Requires WordPress:** 5.8+  
**Requires PHP:** 7.4+  
**License:** GPL v2 or later

A professional WordPress plugin that manages hierarchical geographical data for Panama — Provinces, Districts, Corregimientos, and Communities — with a full admin UI, REST API, and extensibility hooks for integration with other plugins.

---

## Table of Contents

1. [Installation](#installation)
2. [Admin Panel Guide](#admin-panel-guide)
3. [REST API Reference](#rest-api-reference)
4. [Hooks & Filters (Developer API)](#hooks--filters-developer-api)
5. [Integration Examples](#integration-examples)
6. [Database Schema](#database-schema)
7. [Settings Reference](#settings-reference)

---

## Installation

1. Copy the `psp-territorial` folder into your WordPress installation at `wp-content/plugins/psp-territorial/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. On activation, the plugin automatically:
   - Creates the `{prefix}psp_territorial_items` database table.
   - Imports the bundled Panama data (13 provinces, 82 districts, 699 corregimientos, 12 174 communities).
4. Navigate to **PSP Territorial** in the WordPress admin sidebar.

---

## Admin Panel Guide

The admin panel is accessible at `/wp-admin/admin.php?page=psp-territorial`.

### Hierarchy (main page)

- Displays the complete Province → District → Corregimiento → Community tree.
- **Expandable/collapsible nodes** — children are lazy-loaded on first expand for fast page loads.
- **Add Province** button in the top toolbar.
- Each node exposes **Add Child**, **Edit**, and **Delete** action buttons on hover.
- **Deletion is cascading** — deleting a Province deletes all its Districts, Corregimientos, and Communities. A confirmation dialog is shown before any deletion.
- **Search bar** — searches across all levels via the REST API. Results show type, name, and quick Edit/Delete actions.

### Import / Export

- **Import from CSV** — upload a semicolon-separated CSV with columns `Provincia;Distrito;Corregimiento;Comunidad`. Duplicate items are safely skipped.
- **Export as JSON** — downloads a fully nested hierarchy JSON file.
- **Export as CSV** — downloads a flat semicolon-separated CSV (same format as the original source file).
- **Reset to Default Panama Data** — truncates all data and re-imports the bundled `data/panama_data.json`. Requires confirmation.

### Settings

Navigate to **PSP Territorial → Settings** to configure:

| Option | Default | Description |
|--------|---------|-------------|
| Enable REST API | Yes | Toggles the `/wp-json/psp-territorial/v1/` endpoints. |
| Management Capability | `manage_options` | WordPress capability required for CRUD operations. |
| Cache Duration | 3600 s | How long query results are cached via transients (0 = disabled). |
| Import Batch Size | 500 | Reserved for future paginated import support. |

---

## REST API Reference

Base path: `/wp-json/psp-territorial/v1`

All read (`GET`) endpoints are public. Write endpoints require the configured management capability (default: `manage_options`).

### Provinces

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/provinces` | List all provinces. |
| `GET`  | `/provinces/{id}` | Get a province with its full nested hierarchy. |
| `POST` | `/provinces` | Create a new province. Body: `{ name, description? }` |
| `PUT`  | `/provinces/{id}` | Update a province. |
| `DELETE` | `/provinces/{id}` | Delete a province (cascading). |

### Districts

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET`  | `/districts` | List all districts (optionally filter: `?parent_id=1`). |
| `GET`  | `/districts/{id}` | Get a single district. |
| `POST` | `/districts` | Create. Body: `{ name, type: "district", parent_id }` |
| `PUT`  | `/districts/{id}` | Update. |
| `DELETE` | `/districts/{id}` | Delete (cascading). |

### Corregimientos & Communities

Same pattern as Districts, using `/corregimientos` and `/communities`.

### Search

```
GET /wp-json/psp-territorial/v1/search?q=Bocas&type=district
```

Parameters:
- `q` (required, min 2 chars) — search string.
- `type` (optional) — filter by type: `province`, `district`, `corregimiento`, `community`.

Returns up to 200 matching items.

### Full Hierarchy

```
GET /wp-json/psp-territorial/v1/hierarchy
```

Returns the complete nested tree in a single response (cached for performance).

### Example: Get all provinces

```bash
curl https://yoursite.com/wp-json/psp-territorial/v1/provinces
```

Response:
```json
[
  { "id": 1, "name": "Bocas del Toro", "type": "province", "slug": "bocas-del-toro", ... },
  ...
]
```

### Example: Get a province with nested children

```bash
curl https://yoursite.com/wp-json/psp-territorial/v1/provinces/1
```

Response includes `districts[]`, each with `corregimientos[]`, each with `communities[]`.

### Example: Create a new community (authenticated)

```bash
curl -X POST https://yoursite.com/wp-json/psp-territorial/v1/communities \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{ "name": "Nueva Comunidad", "type": "community", "parent_id": 42 }'
```

---

## Hooks & Filters (Developer API)

### Action Hooks

```php
// Fires after a new item is created.
do_action( 'psp_territorial_item_created', $id, $item_data );

// Fires after an item is updated.
do_action( 'psp_territorial_item_updated', $id, $updated_data );

// Fires after an item is deleted.
do_action( 'psp_territorial_item_deleted', $id, $item_data );

// Fires before any item is saved (create or update).
do_action( 'psp_territorial_before_save', $data, $id_or_null );
```

### Filter Hooks

```php
// Modify item data before it is saved to the database.
$data = apply_filters( 'psp_territorial_item_data', $data, $original_data );

// Modify search query arguments.
$args = apply_filters( 'psp_territorial_query_args', $args );
```

### Usage Example

```php
// Log every time a community is created.
add_action( 'psp_territorial_item_created', function( $id, $data ) {
    if ( 'community' === $data['type'] ) {
        error_log( 'New community created: ' . $data['name'] );
    }
}, 10, 2 );

// Add a custom meta field to every item before saving.
add_filter( 'psp_territorial_item_data', function( $data, $original ) {
    $data['description'] = '[Verified] ' . $data['description'];
    return $data;
}, 10, 2 );
```

---

## Integration Examples

### Get all provinces in a front-end template

```php
$provinces = PSPTerritorial\Database::get_items( 'province' );
foreach ( $provinces as $province ) {
    echo esc_html( $province['name'] );
}
```

### Populate a `<select>` dropdown

```php
$districts = PSPTerritorial\Database::get_items( 'district', $province_id );
echo '<select name="district">';
foreach ( $districts as $district ) {
    printf( '<option value="%d">%s</option>', $district['id'], esc_html( $district['name'] ) );
}
echo '</select>';
```

### Fetch via REST API in JavaScript (Gutenberg / React)

```js
const res = await fetch( '/wp-json/psp-territorial/v1/provinces' );
const provinces = await res.json();
```

### Cascade dropdowns (jQuery)

```js
$( '#province' ).on( 'change', function() {
    const provinceId = $( this ).val();
    $.get( '/wp-json/psp-territorial/v1/districts', { parent_id: provinceId }, function( data ) {
        const $sel = $( '#district' ).empty().append( '<option value="">-- Select District --</option>' );
        data.forEach( d => $sel.append( `<option value="${d.id}">${d.name}</option>` ) );
    } );
} );
```

---

## Database Schema

```sql
CREATE TABLE {prefix}psp_territorial_items (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(255)        NOT NULL DEFAULT '',
    slug         VARCHAR(255)        NOT NULL DEFAULT '',
    type         ENUM('province','district','corregimiento','community') NOT NULL,
    parent_id    BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    description  TEXT                NULL,
    created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY type      (type),
    KEY parent_id (parent_id),
    KEY slug      (slug(191))
);
```

---

## File Structure

```
wp-content/plugins/psp-territorial/
├── psp-territorial.php          # Plugin bootstrap & constants
├── includes/
│   ├── Database.php             # All DB CRUD & query helpers
│   ├── Helpers.php              # Utility functions
│   ├── Api/
│   │   └── LocationController.php  # REST API endpoints
│   ├── Admin/
│   │   └── Dashboard.php           # Admin pages & AJAX handlers
│   └── Importer/
│       └── CsvImporter.php         # CSV/JSON import logic
├── assets/
│   ├── css/admin.css            # Admin styles
│   └── js/admin.js              # Admin JavaScript
├── data/
│   └── panama_data.json         # Bundled Panama hierarchical data
└── README.md
```

---

## Changelog

### 1.0.0
- Initial release.
- Bundled Panama data: 13 provinces, 82 districts, 699 corregimientos, 12 174 communities.
- Full admin UI with collapsible tree, search, add/edit/delete.
- REST API with full CRUD.
- Import from CSV, export to JSON/CSV.
- Hooks & filters for extensibility.
