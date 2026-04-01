# PSP Territorial

**API centralizada de datos territoriales de Panamá para WordPress**

PSP Territorial es un plugin WordPress que proporciona una base de datos compartida de las divisiones territoriales de Panamá (provincias, distritos, corregimientos y comunidades). Está diseñado para servir como **fuente única de verdad** para que otros plugins puedan consultar y utilizar estos datos sin duplicarlos.

---

## 📦 Instalación

1. Sube la carpeta `psp-territorial/` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el menú **Plugins** de WordPress
3. Al activarse, el plugin creará automáticamente la tabla de base de datos e importará los datos territoriales de Panamá

---

## 🗃️ Datos incluidos

| Tipo | Cantidad |
|------|----------|
| Provincias | 13 |
| Distritos | 82 |
| Corregimientos | 703 |
| Comunidades | 12,174 |
| **Total** | **12,972** |

---

## 🔌 REST API

Base URL: `/wp-json/psp/v1/`

### Endpoints disponibles

#### Territorios

```
GET /wp-json/psp/v1/territories
  ?type=province|district|corregimiento|community
  &parent_id={id}
  &search={término}
  &limit=50
  &offset=0

GET /wp-json/psp/v1/territories/{id}
```

#### Jerárquico (para formularios dependientes)

```
GET /wp-json/psp/v1/hierarchy/provinces
GET /wp-json/psp/v1/hierarchy/provinces/{province_id}/districts
GET /wp-json/psp/v1/hierarchy/districts/{district_id}/corregimientos
GET /wp-json/psp/v1/hierarchy/corregimientos/{corregimiento_id}/communities
```

#### Búsqueda y navegación

```
GET /wp-json/psp/v1/search?q={término}&type={tipo}
GET /wp-json/psp/v1/path/{type}/{id}
```

---

## 📋 Panel de Administración

Desde **WordPress Admin → PSP Territorial** puedes:

- ✅ **Listar** todos los territorios con filtros y búsqueda
- ✅ **Crear** nuevos territorios con validación de jerarquía
- ✅ **Editar** territorios existentes
- ✅ **Eliminar** (con opción de eliminar hijos en cascada)
- ✅ **Vista de árbol** jerárquico colapsable
- ✅ **Importar** desde CSV o JSON incluido
- ✅ **Exportar** a JSON o CSV
- ✅ **Configuración** de caché y paginación

---

## ⚙️ Requisitos

- WordPress 5.9 o superior
- PHP 7.4 o superior
- MySQL 5.7+ / MariaDB 10.3+ (para soporte de tipo JSON)

---

## 📄 Licencia

GPL v2 o posterior. Ver [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
