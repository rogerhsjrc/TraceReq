# TraceReq — Diccionario de datos v1

**Estado:** Decisiones funcionales confirmadas para el MVP  
**Versión:** 1.1  
**Fecha:** 2026-08-06  
**Motor:** PostgreSQL  
**Backend:** Laravel — monolito modular con API REST  
**Frontend:** Vue 3 SPA

## 1. Objetivo

Este documento transforma el modelo de dominio de TraceReq en un modelo lógico listo para implementar mediante migraciones de Laravel. Define tablas, columnas, tipos PostgreSQL, nulabilidad, claves, restricciones, índices y reglas que deben protegerse en la aplicación.

El alcance cubre:

- Identidad y autenticación.
- Organizaciones multiempresa.
- Membresías y roles múltiples.
- Invitaciones.
- Solicitudes de compra y reintegro.
- Un único aprobador activo por solicitud.
- Múltiples ciclos de revisión.
- Ejecución del gasto.
- Adjuntos, comentarios y trazabilidad.

## 2. Convenciones generales

### 2.1 Identificadores

- Las entidades del dominio utilizan ULID almacenados como `CHAR(26)`.
- Los ULID se generan en la aplicación antes de insertar.
- Las claves foráneas deben utilizar el mismo tipo que la clave referenciada.
- Los nombres de claves primarias siguen la convención `id`.

### 2.2 Fechas

- Instantes: `TIMESTAMPTZ`.
- Fechas sin hora: `DATE`.
- Los instantes se almacenan en UTC.
- La API los expone en ISO 8601.
- Vue los presenta en la zona horaria de la organización.
- Las tablas del dominio incluyen `created_at` y `updated_at`, salvo tablas pivote o contadores donde se indique lo contrario.

En Laravel se utilizarán `timestampsTz()` y `timestampTz()`.

### 2.3 Dinero

- Los importes utilizan `NUMERIC(19,4)`.
- Nunca se utilizan `FLOAT`, `REAL` o `DOUBLE PRECISION` para dinero.
- La API representa importes como cadenas.
- La moneda utiliza un código ISO 4217 en `CHAR(3)`.
- Todos los importes de una solicitud comparten la misma moneda en el MVP.

### 2.4 Estados y enumeraciones

Para el MVP se utilizarán columnas `VARCHAR` con restricciones `CHECK`, no tipos `ENUM` nativos de PostgreSQL. Esto facilita modificar valores mediante migraciones y mantiene explícitas las reglas en la base.

Los mismos valores se representarán en PHP mediante enums respaldados:

```php
enum RequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

### 2.5 Multiempresa

- Toda entidad empresarial incluye `organization_id`, incluso cuando pueda deducirse por otra relación.
- Las consultas deben ejecutarse dentro de una organización activa validada.
- Las referencias a membresías deben pertenecer a la misma organización que la entidad afectada.
- Se utilizarán claves foráneas compuestas donde aporten protección real contra cruces entre organizaciones.
- Policies, servicios de dominio y pruebas de aislamiento siguen siendo obligatorios; las claves foráneas no reemplazan la autorización.

### 2.6 Borrado

- Solicitudes, revisiones, ejecuciones y eventos no se eliminan físicamente.
- Usuarios, organizaciones y membresías se desactivan mediante estado.
- Comentarios y adjuntos admiten borrado lógico con `deleted_at`.
- Las claves foráneas críticas utilizan `RESTRICT` o `NO ACTION`.
- `CASCADE` se limita a relaciones dependientes que no tienen sentido sin su padre y cuya eliminación solo es posible antes de contener actividad empresarial.

## 3. Catálogo de tablas

| Módulo | Tabla | Propósito |
|---|---|---|
| Identity | `users` | Identidad global de una persona |
| Organizations | `currencies` | Catálogo de monedas habilitadas |
| Organizations | `organizations` | Empresas alojadas en TraceReq |
| Organizations | `memberships` | Pertenencia de usuarios a organizaciones |
| Organizations | `roles` | Roles asignables |
| Organizations | `membership_roles` | Roles de una membresía |
| Organizations | `organization_invitations` | Invitaciones pendientes |
| Organizations | `organization_invitation_roles` | Roles propuestos en una invitación |
| Requests | `request_sequences` | Correlativos de solicitudes por organización y año |
| Requests | `trace_requests` | Solicitudes y su estado consolidado |
| Requests | `purchase_request_details` | Datos específicos de compras |
| Requests | `reimbursement_request_details` | Datos específicos de reintegros |
| Approvals | `request_reviews` | Ciclos de revisión y decisiones |
| Approvals | `request_executions` | Registro del gasto consumado |
| Requests | `request_attachments` | Documentos asociados |
| Requests | `request_comments` | Comunicación sobre solicitudes |
| Traceability | `request_events` | Línea temporal funcional e inmutable |

## 4. Identity

### 4.1 `users`

Identidad global. No contiene organización ni roles.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `name` | `VARCHAR(120)` | No | — | Nombre visible, no vacío |
| `email` | `VARCHAR(254)` | No | — | Correo normalizado a minúsculas |
| `password` | `VARCHAR(255)` | No | — | Hash generado por Laravel |
| `status` | `VARCHAR(20)` | No | `'active'` | `active`, `suspended` |
| `email_verified_at` | `TIMESTAMPTZ` | Sí | `NULL` | Verificación de correo |
| `last_login_at` | `TIMESTAMPTZ` | Sí | `NULL` | Último acceso correcto |
| `remember_token` | `VARCHAR(100)` | Sí | `NULL` | Compatibilidad Laravel |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Última modificación |

**Restricciones**

- PK: `users_pkey (id)`.
- UNIQUE funcional sobre correo en minúsculas: `UNIQUE (LOWER(email))`.
- CHECK `status IN ('active', 'suspended')`.
- CHECK `BTRIM(name) <> ''`.
- La aplicación siempre persiste `email` normalizado con `mb_strtolower(trim(...))`.

**Índices**

- `users_email_lower_unique` único sobre `LOWER(email)`.
- `users_status_idx (status)` solo si las búsquedas administrativas lo justifican.

## 5. Organizations

### 5.1 `currencies`

Catálogo global de monedas. Se consulta desde el ABM de organizaciones y evita mantener opciones monetarias hardcodeadas en Vue o Laravel.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `code` | `CHAR(3)` | No | — | PK, código ISO 4217 |
| `name` | `VARCHAR(80)` | No | — | Nombre visible en español |
| `symbol` | `VARCHAR(10)` | No | — | Símbolo de presentación; no se usa para identificar |
| `decimal_places` | `SMALLINT` | No | `2` | Decimales usuales de presentación |
| `is_active` | `BOOLEAN` | No | `TRUE` | Disponible para nuevas configuraciones |
| `sort_order` | `SMALLINT` | No | `100` | Orden de presentación en selectores |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK `currencies_pkey (code)`.
- CHECK `code ~ '^[A-Z]{3}$'`.
- CHECK `decimal_places BETWEEN 0 AND 4`.
- CHECK `sort_order >= 0`.
- CHECK `BTRIM(name) <> ''` y `BTRIM(symbol) <> ''`.
- Desactivar una moneda impide seleccionarla en organizaciones nuevas, pero no invalida organizaciones ni solicitudes históricas que ya la referencien.
- La primera versión no expone un ABM de monedas al usuario; la tabla y el seeder preparan esa evolución.

**Índices**

- `currencies_active_sort_idx (is_active, sort_order, name)`.

### 5.2 `organizations`

Tenant o empresa cliente.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `owner_user_id` | `CHAR(26)` | No | — | FK `users.id` |
| `name` | `VARCHAR(120)` | No | — | Nombre comercial |
| `slug` | `VARCHAR(80)` | No | — | Identificador público global |
| `legal_name` | `VARCHAR(180)` | Sí | `NULL` | Razón social |
| `tax_identifier` | `VARCHAR(40)` | Sí | `NULL` | CUIT u otro identificador local |
| `default_currency_code` | `CHAR(3)` | No | `'ARS'` | FK `currencies.code` |
| `timezone` | `VARCHAR(64)` | No | `'UTC'` | Zona IANA, por ejemplo `America/Argentina/San_Juan` |
| `status` | `VARCHAR(20)` | No | `'active'` | `active`, `suspended` |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK: `organizations_pkey (id)`.
- FK `owner_user_id -> users.id` con `ON DELETE RESTRICT`.
- FK `default_currency_code -> currencies.code` con `ON UPDATE CASCADE` y `ON DELETE RESTRICT`.
- UNIQUE `slug` en minúsculas.
- CHECK `status IN ('active', 'suspended')`.
- CHECK `BTRIM(name) <> ''`.
- El owner debe poseer una membresía activa en la organización. Esta invariante se protege en el servicio de dominio porque implica dos tablas y el alta se realiza en una transacción.
- La transferencia de propiedad queda fuera del MVP.

**Índices**

- `organizations_slug_lower_unique` único sobre `LOWER(slug)`.
- `organizations_owner_user_idx (owner_user_id)`.

### 5.3 `memberships`

Relación entre usuario y organización. Es la identidad empresarial usada por solicitudes, revisiones y eventos.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | FK `organizations.id` |
| `user_id` | `CHAR(26)` | No | — | FK `users.id` |
| `status` | `VARCHAR(20)` | No | `'active'` | `active`, `inactive` |
| `joined_at` | `TIMESTAMPTZ` | No | — | Fecha efectiva de incorporación |
| `deactivated_at` | `TIMESTAMPTZ` | Sí | `NULL` | Fecha de desactivación |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK: `memberships_pkey (id)`.
- FK `organization_id -> organizations.id` con `ON DELETE RESTRICT`.
- FK `user_id -> users.id` con `ON DELETE RESTRICT`.
- UNIQUE `(organization_id, user_id)`.
- UNIQUE `(organization_id, id)` para soportar claves foráneas compuestas tenant-safe.
- CHECK `status IN ('active', 'inactive')`.
- CHECK coherente: estado `active` implica `deactivated_at IS NULL`; estado `inactive` implica `deactivated_at IS NOT NULL`.
- La membresía del owner no puede desactivarse.

**Índices**

- `memberships_user_status_idx (user_id, status)` para listar organizaciones disponibles.
- `memberships_organization_status_idx (organization_id, status)` para administrar miembros.

### 5.4 `roles`

Catálogo global y controlado por el sistema.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `SMALLINT GENERATED BY DEFAULT AS IDENTITY` | No | identidad | PK |
| `code` | `VARCHAR(30)` | No | — | `admin`, `approver` |
| `name` | `VARCHAR(60)` | No | — | Nombre visible |
| `description` | `VARCHAR(255)` | Sí | `NULL` | Descripción funcional |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK: `roles_pkey (id)`.
- UNIQUE `code`.
- CHECK `code IN ('admin', 'approver')` durante el MVP.
- Los roles se insertan mediante seeder y no se administran desde la interfaz.
- `member` no es un rol: es la capacidad base de toda membresía activa.
- `owner` no es un rol asignable: se deriva de `organizations.owner_user_id`.

### 5.5 `membership_roles`

Tabla pivote multirol.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `membership_id` | `CHAR(26)` | No | — | FK `memberships.id` |
| `role_id` | `SMALLINT` | No | — | FK `roles.id` |
| `assigned_by_membership_id` | `CHAR(26)` | No | — | Quién asignó el rol |
| `assigned_at` | `TIMESTAMPTZ` | No | — | Momento de asignación |

**Restricciones**

- PK compuesta `(membership_id, role_id)`.
- FK `membership_id -> memberships.id` con `ON DELETE CASCADE`.
- FK `role_id -> roles.id` con `ON DELETE RESTRICT`.
- FK `assigned_by_membership_id -> memberships.id` con `ON DELETE RESTRICT`.
- La aplicación valida que ambas membresías pertenezcan a la misma organización.
- Un usuario no puede escalar sus propios permisos sin una autoridad válida.

**Índices**

- La PK cubre búsquedas por membresía.
- `membership_roles_role_membership_idx (role_id, membership_id)` para encontrar aprobadores o administradores.

### 5.6 `organization_invitations`

Invitación a incorporarse a una organización.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Organización destino |
| `email` | `VARCHAR(254)` | No | — | Correo normalizado |
| `token_hash` | `CHAR(64)` | No | — | SHA-256 del token; nunca se guarda el token plano |
| `invited_by_membership_id` | `CHAR(26)` | No | — | Admin/owner que invita |
| `expires_at` | `TIMESTAMPTZ` | No | — | Vencimiento, siete días por defecto |
| `accepted_at` | `TIMESTAMPTZ` | Sí | `NULL` | Aceptación |
| `revoked_at` | `TIMESTAMPTZ` | Sí | `NULL` | Revocación |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK: `organization_invitations_pkey (id)`.
- FK `organization_id -> organizations.id` con `ON DELETE RESTRICT`.
- FK compuesta `(organization_id, invited_by_membership_id) -> memberships(organization_id, id)`.
- UNIQUE `token_hash`.
- CHECK `expires_at > created_at`.
- CHECK que no estén informados simultáneamente `accepted_at` y `revoked_at`.
- Una invitación aceptada, revocada o vencida no puede utilizarse.
- El correo invitado debe coincidir con el correo verificado del usuario que acepta.

**Índices**

- Índice único parcial para una sola invitación pendiente por organización y correo:

```sql
CREATE UNIQUE INDEX organization_invitations_pending_unique
ON organization_invitations (organization_id, LOWER(email))
WHERE accepted_at IS NULL AND revoked_at IS NULL;
```

- `organization_invitations_email_idx (LOWER(email))` para listar invitaciones de un usuario.
- `organization_invitations_expires_idx (expires_at)` para limpieza o marcado de vencidas.

### 5.7 `organization_invitation_roles`

Roles que se asignarán al aceptar una invitación.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `invitation_id` | `CHAR(26)` | No | — | FK `organization_invitations.id` |
| `role_id` | `SMALLINT` | No | — | FK `roles.id` |

**Restricciones**

- PK compuesta `(invitation_id, role_id)`.
- FK `invitation_id` con `ON DELETE CASCADE`.
- FK `role_id` con `ON DELETE RESTRICT`.

## 6. Requests

### 6.1 `request_sequences`

Contador transaccional utilizado para referencias legibles por organización y año.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `organization_id` | `CHAR(26)` | No | — | FK `organizations.id` |
| `year` | `SMALLINT` | No | — | Año de la referencia |
| `last_value` | `BIGINT` | No | `0` | Último correlativo emitido |
| `updated_at` | `TIMESTAMPTZ` | No | — | Última emisión |

**Restricciones**

- PK compuesta `(organization_id, year)`.
- FK `organization_id -> organizations.id` con `ON DELETE RESTRICT`.
- CHECK `year BETWEEN 2020 AND 9999`.
- CHECK `last_value >= 0`.

**Generación recomendada**

Dentro de la misma transacción que crea la solicitud:

1. Insertar el contador en caso de no existir.
2. Incrementarlo atómicamente con `INSERT ... ON CONFLICT ... DO UPDATE`.
3. Obtener el nuevo valor mediante `RETURNING last_value`.
4. Formatear `REQ-{year}-{value padded a 6}`.
5. Insertar `trace_requests`.

La restricción única de la solicitud continúa siendo la última protección ante duplicados.

### 6.2 `trace_requests`

Raíz del agregado de solicitudes. Contiene estado e importes consolidados.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Tenant |
| `reference` | `VARCHAR(24)` | No | — | Ej. `REQ-2026-000042` |
| `type` | `VARCHAR(20)` | No | — | `purchase`, `reimbursement` |
| `status` | `VARCHAR(30)` | No | `'draft'` | Estado actual |
| `priority` | `VARCHAR(20)` | No | `'normal'` | `low`, `normal`, `high`, `urgent` |
| `title` | `VARCHAR(180)` | No | — | Título visible |
| `description` | `TEXT` | No | — | Descripción |
| `justification` | `TEXT` | No | — | Justificación empresarial |
| `requested_amount` | `NUMERIC(19,4)` | No | — | Importe solicitado |
| `approved_amount` | `NUMERIC(19,4)` | Sí | `NULL` | Importe aprobado consolidado |
| `actual_amount` | `NUMERIC(19,4)` | Sí | `NULL` | Importe consumado consolidado |
| `currency_code` | `CHAR(3)` | No | — | FK `currencies.code`; copia histórica de la moneda elegida |
| `required_at` | `DATE` | Sí | `NULL` | Fecha requerida; puede no aplicar al reintegro |
| `created_by_membership_id` | `CHAR(26)` | No | — | Creador empresarial |
| `submitted_at` | `TIMESTAMPTZ` | Sí | `NULL` | Primer o último envío; ver nota histórica |
| `approved_at` | `TIMESTAMPTZ` | Sí | `NULL` | Aprobación final |
| `completed_at` | `TIMESTAMPTZ` | Sí | `NULL` | Ejecución |
| `cancelled_at` | `TIMESTAMPTZ` | Sí | `NULL` | Cancelación |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK: `trace_requests_pkey (id)`.
- UNIQUE `(organization_id, id)` para referencias compuestas.
- UNIQUE `(organization_id, reference)`.
- FK `organization_id -> organizations.id` con `ON DELETE RESTRICT`.
- FK `currency_code -> currencies.code` con `ON UPDATE CASCADE` y `ON DELETE RESTRICT`.
- FK compuesta `(organization_id, created_by_membership_id) -> memberships(organization_id, id)`.
- CHECK `type IN ('purchase', 'reimbursement')`.
- CHECK `status IN ('draft', 'submitted', 'in_review', 'changes_requested', 'approved', 'rejected', 'completed', 'cancelled')`.
- CHECK `priority IN ('low', 'normal', 'high', 'urgent')`.
- CHECK `requested_amount > 0`.
- CHECK `approved_amount IS NULL OR approved_amount > 0`.
- CHECK `actual_amount IS NULL OR actual_amount > 0`.
- CHECK `BTRIM(title) <> ''`, `BTRIM(description) <> ''` y `BTRIM(justification) <> ''`.
- CHECK de coherencia: estados `approved` y `completed` requieren `approved_amount` y `approved_at`.
- CHECK de coherencia: estado `completed` requiere `actual_amount` y `completed_at`.
- CHECK de coherencia: estado `cancelled` requiere `cancelled_at`.
- La transición de estado nunca se realiza mediante un CRUD genérico.
- `approved_amount` se actualiza en la misma transacción que la revisión aprobada.
- `actual_amount` se actualiza en la misma transacción que `request_executions`.
- `submitted_at` representa el envío más reciente. Todos los envíos históricos se conservan en `request_events`.
- En el MVP, `currency_code` se copia desde `organizations.default_currency_code` al crear el borrador. Una modificación posterior de la moneda predeterminada no altera solicitudes existentes.
- Una solicitud de reintegro aprobada no puede cancelarse: su desenlace válido es completarla. Si no correspondía, debía rechazarse durante la revisión.
- Una solicitud de compra aprobada puede cancelarse excepcionalmente por admin/owner, antes de ejecutarse y con motivo obligatorio.

**Índices**

- `trace_requests_org_status_created_idx (organization_id, status, created_at DESC)`.
- `trace_requests_org_creator_created_idx (organization_id, created_by_membership_id, created_at DESC)`.
- `trace_requests_org_type_created_idx (organization_id, type, created_at DESC)`.
- `trace_requests_org_priority_status_idx (organization_id, priority, status)`.
- `trace_requests_org_required_idx (organization_id, required_at)` con `WHERE required_at IS NOT NULL`.
- La búsqueda textual por título o referencia se incorporará cuando exista el endpoint; inicialmente puede resolverse mediante `ILIKE` y los índices únicos de referencia.

### 6.3 `purchase_request_details`

Extensión uno a uno para solicitudes de compra.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Tenant |
| `trace_request_id` | `CHAR(26)` | No | — | Solicitud tipo compra |
| `item_description` | `VARCHAR(255)` | No | — | Producto o servicio |
| `quantity` | `NUMERIC(12,3)` | No | `1` | Permite unidades fraccionarias |
| `estimated_unit_price` | `NUMERIC(19,4)` | Sí | `NULL` | Precio unitario estimado |
| `suggested_vendor` | `VARCHAR(180)` | Sí | `NULL` | Texto libre durante el MVP |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK `id`.
- UNIQUE `trace_request_id`.
- FK compuesta `(organization_id, trace_request_id) -> trace_requests(organization_id, id)` con `ON DELETE RESTRICT`.
- CHECK `quantity > 0`.
- CHECK `estimated_unit_price IS NULL OR estimated_unit_price > 0`.
- CHECK `BTRIM(item_description) <> ''`.
- La aplicación valida que `trace_requests.type = 'purchase'`.
- Durante el MVP, `requested_amount` no tiene que ser exactamente `quantity × estimated_unit_price`, porque puede incluir impuestos, envío u otros conceptos. Cualquier diferencia debe ser explicable desde la descripción/justificación.

### 6.4 `reimbursement_request_details`

Extensión uno a uno para solicitudes de reintegro.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Tenant |
| `trace_request_id` | `CHAR(26)` | No | — | Solicitud tipo reintegro |
| `expense_date` | `DATE` | No | — | Fecha del gasto |
| `expense_category` | `VARCHAR(50)` | No | — | Código estable de categoría |
| `payment_method` | `VARCHAR(30)` | No | — | `cash`, `debit_card`, `credit_card`, `bank_transfer`, `digital_wallet`, `other` |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK `id`.
- UNIQUE `trace_request_id`.
- FK compuesta `(organization_id, trace_request_id) -> trace_requests(organization_id, id)` con `ON DELETE RESTRICT`.
- CHECK de `payment_method` con los valores definidos.
- CHECK `expense_category IN ('per_diem_mobility', 'meals_representation', 'travel_accommodation', 'office_supplies', 'digital_tools_subscriptions', 'petty_cash_minor_expenses')`.
- La aplicación valida `trace_requests.type = 'reimbursement'`.
- La aplicación valida `expense_date <= CURRENT_DATE` al crear o enviar.
- El envío exige al menos un adjunto activo de categoría `receipt`.

**Categorías visibles del MVP**

| Código | Etiqueta |
|---|---|
| `per_diem_mobility` | Viáticos y movilidad |
| `meals_representation` | Alimentación y gastos de representación |
| `travel_accommodation` | Viajes y alojamiento |
| `office_supplies` | Suministros y oficina |
| `digital_tools_subscriptions` | Suscripciones y herramientas digitales |
| `petty_cash_minor_expenses` | Caja chica y gastos menores |

Los códigos se mantienen en inglés y estables; las etiquetas se presentan en español desde el frontend. Un futuro ABM podrá reemplazar este catálogo fijo por una tabla configurable sin modificar los registros históricos.

## 7. Approvals

### 7.1 `request_reviews`

Cada registro representa un ciclo de revisión. Una solicitud puede tener varios ciclos, pero solo uno puede permanecer abierto.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Tenant |
| `trace_request_id` | `CHAR(26)` | No | — | Solicitud revisada |
| `reviewer_membership_id` | `CHAR(26)` | No | — | Aprobador asignado |
| `outcome` | `VARCHAR(30)` | Sí | `NULL` | Decisión al resolver |
| `reason` | `TEXT` | Sí | `NULL` | Motivo/observación |
| `approved_amount` | `NUMERIC(19,4)` | Sí | `NULL` | Solo cuando `outcome = approved` |
| `started_at` | `TIMESTAMPTZ` | No | — | Inicio de revisión |
| `resolved_at` | `TIMESTAMPTZ` | Sí | `NULL` | Cierre de revisión |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación técnica |

**Restricciones**

- PK `id`.
- FK compuesta `(organization_id, trace_request_id) -> trace_requests(organization_id, id)`.
- FK compuesta `(organization_id, reviewer_membership_id) -> memberships(organization_id, id)`.
- CHECK `outcome IS NULL OR outcome IN ('approved', 'rejected', 'changes_requested', 'released')`.
- CHECK de ciclo abierto: `resolved_at IS NULL` implica `outcome IS NULL`.
- CHECK de ciclo resuelto: `resolved_at IS NOT NULL` implica `outcome IS NOT NULL`.
- CHECK `resolved_at IS NULL OR resolved_at >= started_at`.
- CHECK `outcome = 'approved'` implica `approved_amount > 0`.
- CHECK `outcome <> 'approved'` implica `approved_amount IS NULL`.
- CHECK `outcome IN ('rejected', 'changes_requested')` implica motivo no vacío.
- La aplicación valida que el revisor tenga rol `approver` o sea owner.
- La aplicación valida que el revisor no sea el creador.
- Solo el revisor asignado puede resolver el ciclo.
- Una revisión resuelta es inmutable funcionalmente.

**Índices**

```sql
CREATE UNIQUE INDEX request_reviews_one_open_unique
ON request_reviews (trace_request_id)
WHERE resolved_at IS NULL;
```

- `request_reviews_org_reviewer_open_idx (organization_id, reviewer_membership_id, started_at DESC) WHERE resolved_at IS NULL`.
- `request_reviews_request_started_idx (trace_request_id, started_at)` para historial.

### 7.2 `request_executions`

Registro uno a uno del gasto efectivamente consumado.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Tenant |
| `trace_request_id` | `CHAR(26)` | No | — | Solicitud aprobada |
| `completed_by_membership_id` | `CHAR(26)` | No | — | Admin/owner que registra |
| `actual_amount` | `NUMERIC(19,4)` | No | — | Gasto real |
| `executed_at` | `TIMESTAMPTZ` | No | — | Fecha/hora efectiva |
| `excess_justification` | `TEXT` | Sí | `NULL` | Obligatoria si supera lo aprobado |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación técnica |

**Restricciones**

- PK `id`.
- UNIQUE `trace_request_id`.
- FK compuesta `(organization_id, trace_request_id) -> trace_requests(organization_id, id)`.
- FK compuesta `(organization_id, completed_by_membership_id) -> memberships(organization_id, id)`.
- CHECK `actual_amount > 0`.
- La aplicación valida que la solicitud esté `approved`.
- La aplicación valida que el actor sea `admin` u owner.
- La aplicación exige `excess_justification` cuando `actual_amount > trace_requests.approved_amount`; esta comparación entre tablas se realiza dentro del servicio y la transacción.
- La inserción, la actualización consolidada de `trace_requests` y el evento `request_completed` son atómicos.

**Índices**

- `request_executions_org_executed_idx (organization_id, executed_at DESC)` para reportes.

## 8. Colaboración y documentos

### 8.1 `request_attachments`

Metadatos de archivos. El contenido se almacena fuera de PostgreSQL.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Tenant |
| `trace_request_id` | `CHAR(26)` | No | — | Solicitud |
| `uploaded_by_membership_id` | `CHAR(26)` | No | — | Autor de la carga |
| `category` | `VARCHAR(40)` | No | `'other'` | Propósito del documento |
| `original_name` | `VARCHAR(255)` | No | — | Nombre original saneado para mostrar |
| `storage_disk` | `VARCHAR(40)` | No | — | Disco Laravel, por ejemplo `local` o `s3` |
| `storage_path` | `VARCHAR(500)` | No | — | Ruta interna no pública |
| `mime_type` | `VARCHAR(120)` | No | — | MIME detectado por servidor |
| `size_bytes` | `BIGINT` | No | — | Tamaño |
| `checksum_sha256` | `CHAR(64)` | No | — | Integridad/deduplicación futura |
| `image_width` | `INTEGER` | No | — | Ancho del original en píxeles |
| `image_height` | `INTEGER` | No | — | Alto del original en píxeles |
| `processing_status` | `VARCHAR(20)` | No | `'pending'` | `pending`, `processing`, `ready`, `failed` |
| `optimized_storage_path` | `VARCHAR(500)` | Sí | `NULL` | Derivado WebP para visualización |
| `optimized_mime_type` | `VARCHAR(120)` | Sí | `NULL` | `image/webp` al completar |
| `optimized_size_bytes` | `BIGINT` | Sí | `NULL` | Tamaño del derivado |
| `optimized_width` | `INTEGER` | Sí | `NULL` | Ancho final |
| `optimized_height` | `INTEGER` | Sí | `NULL` | Alto final |
| `processing_error` | `VARCHAR(500)` | Sí | `NULL` | Diagnóstico interno si falla; no se expone sin filtrar |
| `deleted_at` | `TIMESTAMPTZ` | Sí | `NULL` | Borrado lógico |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK `id`.
- FK compuesta a solicitud y membresía dentro de la misma organización.
- UNIQUE `(storage_disk, storage_path)`.
- UNIQUE parcial `(storage_disk, optimized_storage_path) WHERE optimized_storage_path IS NOT NULL`.
- CHECK `category IN ('quotation', 'receipt', 'invoice', 'supporting_document', 'execution_receipt', 'other')`.
- CHECK `size_bytes > 0`.
- CHECK `image_width > 0 AND image_height > 0`.
- CHECK `checksum_sha256 ~ '^[0-9a-f]{64}$'`.
- CHECK `processing_status IN ('pending', 'processing', 'ready', 'failed')`.
- CHECK de consistencia: `processing_status = 'ready'` requiere ruta, MIME, tamaño y dimensiones optimizadas.
- CHECK `optimized_size_bytes IS NULL OR optimized_size_bytes > 0`.
- Los MIME admitidos inicialmente son `image/jpeg`, `image/png` e `image/webp`.
- El tamaño máximo de entrada es 15 MiB por imagen.
- Los permisos, MIME real y tamaño se validan en backend; no se confía en la extensión enviada por el cliente.
- La descarga siempre pasa por una Policy y una respuesta autorizada o URL temporal.
- `storage_path` nunca se entrega en recursos públicos de la API.
- El original se conserva de forma privada para integridad documental y su checksum no cambia.
- Una tarea en cola corrige orientación, elimina EXIF, limita el lado mayor a 2400 px sin ampliar imágenes pequeñas y genera WebP con calidad inicial 82.
- El frontend utiliza el derivado optimizado para previsualizar y ofrece el original solo mediante descarga autorizada.
- Si el procesamiento falla, el original permanece disponible y la interfaz muestra el estado correspondiente.
- HEIC/HEIF queda fuera del MVP para no depender inicialmente de `libheif`; podrá incorporarse como formato de entrada y normalizarse a WebP posteriormente.

**Índices**

- `request_attachments_request_active_idx (trace_request_id, category) WHERE deleted_at IS NULL`.
- `request_attachments_org_created_idx (organization_id, created_at DESC)`.
- `request_attachments_processing_idx (processing_status, created_at) WHERE processing_status IN ('pending', 'processing')` para monitoreo/recuperación de jobs.

### 8.2 `request_comments`

Comentarios humanos asociados a una solicitud.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Tenant |
| `trace_request_id` | `CHAR(26)` | No | — | Solicitud |
| `author_membership_id` | `CHAR(26)` | No | — | Autor |
| `body` | `TEXT` | No | — | Contenido |
| `edited_at` | `TIMESTAMPTZ` | Sí | `NULL` | Última edición explícita |
| `deleted_at` | `TIMESTAMPTZ` | Sí | `NULL` | Borrado lógico |
| `created_at` | `TIMESTAMPTZ` | No | — | Alta |
| `updated_at` | `TIMESTAMPTZ` | No | — | Modificación |

**Restricciones**

- PK `id`.
- FK compuesta a solicitud y membresía dentro de la misma organización.
- CHECK `BTRIM(body) <> ''`.
- CHECK `CHAR_LENGTH(body) <= 5000`.
- La aplicación determina quién puede participar según su relación con la solicitud y sus roles.
- Solo el autor puede editar su comentario y únicamente hasta `created_at + INTERVAL '15 minutes'`.
- Durante esos 15 minutos, Vue muestra una acción de edición y una indicación visual del tiempo restante; vencido el plazo, la API rechaza la edición aunque el cliente conserve la pantalla abierta.
- Si fue modificado, se establece `edited_at` y la interfaz muestra la leyenda «Editado».
- El MVP no conserva versiones anteriores del texto del comentario.
- Un comentario borrado conserva autor, fecha y existencia para auditoría, pero no se muestra su contenido a usuarios comunes.

**Índices**

- `request_comments_request_created_idx (trace_request_id, created_at)`.
- Índice parcial opcional sobre activos: `(trace_request_id, created_at) WHERE deleted_at IS NULL` si el volumen lo justifica.

## 9. Traceability

### 9.1 `request_events`

Línea temporal funcional e inmutable de una solicitud.

| Columna | Tipo | Null | Default | Restricciones / descripción |
|---|---|---:|---|---|
| `id` | `CHAR(26)` | No | — | PK, ULID |
| `organization_id` | `CHAR(26)` | No | — | Tenant |
| `trace_request_id` | `CHAR(26)` | No | — | Solicitud |
| `actor_membership_id` | `CHAR(26)` | Sí | `NULL` | Nulo para acciones del sistema |
| `event_type` | `VARCHAR(50)` | No | — | Tipo funcional |
| `from_status` | `VARCHAR(30)` | Sí | `NULL` | Estado anterior |
| `to_status` | `VARCHAR(30)` | Sí | `NULL` | Estado posterior |
| `metadata` | `JSONB` | No | `'{}'::jsonb` | Contexto mínimo del evento |
| `occurred_at` | `TIMESTAMPTZ` | No | — | Momento funcional |
| `created_at` | `TIMESTAMPTZ` | No | — | Persistencia |

No incluye `updated_at` porque los eventos son inmutables.

**Restricciones**

- PK `id`.
- FK compuesta `(organization_id, trace_request_id) -> trace_requests(organization_id, id)`.
- FK compuesta opcional `(organization_id, actor_membership_id) -> memberships(organization_id, id)`.
- CHECK `event_type IN ('request_created', 'request_updated', 'request_submitted', 'review_started', 'review_released', 'changes_requested', 'request_resubmitted', 'request_approved', 'request_rejected', 'request_cancelled', 'request_completed', 'comment_added', 'attachment_added', 'attachment_removed')`.
- CHECK de estados: si están informados, deben pertenecer al catálogo de estados de solicitud.
- CHECK `jsonb_typeof(metadata) = 'object'`.
- No se permiten actualizaciones o eliminaciones desde la capa de aplicación.
- `metadata` no almacena archivos, contraseñas, tokens ni snapshots completos.
- Cada transición crea el evento dentro de la misma transacción que modifica la solicitud.

**Índices**

- `request_events_request_occurred_idx (trace_request_id, occurred_at, id)` para timeline estable.
- `request_events_org_type_occurred_idx (organization_id, event_type, occurred_at DESC)` para análisis/auditoría.
- No se agrega índice GIN sobre `metadata` hasta existir consultas concretas.

## 10. Tablas provistas por Laravel

Estas tablas se generan o adaptan mediante migraciones del framework:

### 10.1 `password_reset_tokens`

- `email VARCHAR(254)` PK.
- `token VARCHAR(255)`.
- `created_at TIMESTAMPTZ NULL`.

### 10.2 `personal_access_tokens`

Utilizada por Sanctum. Se conservará la estructura recomendada por la versión instalada, adaptando timestamps a zona horaria cuando resulte compatible.

### 10.3 `notifications`

Notificaciones persistentes de Laravel:

- Se utiliza ULID para `id` si la versión/configuración lo permite.
- `notifiable_type` y `notifiable_id` identifican al usuario.
- `data` contiene información mínima para construir la interfaz.
- Las notificaciones no constituyen fuente de verdad.

### 10.4 `jobs`, `job_batches`, `failed_jobs`

Soporte para colas y notificaciones asíncronas. Se utiliza la estructura oficial de la versión de Laravel instalada.

## 11. Reglas entre tablas protegidas por la aplicación

Algunas invariantes no pueden expresarse limpiamente con un `CHECK`, porque PostgreSQL no permite que este consulte otras filas o tablas. Se protegerán mediante servicios de dominio, transacciones, Policies y pruebas.

| Regla | Protección |
|---|---|
| El owner tiene una membresía activa | Transacción de creación + servicio Organization |
| El owner no puede desactivarse | Policy + servicio Membership |
| El revisor posee capacidad de aprobar | Policy + consulta de roles |
| El creador no aprueba su propia solicitud | Policy + servicio Approval |
| El detalle coincide con el tipo de solicitud | Factory/Action de creación + validación |
| Un reintegro enviado tiene comprobante | Action de envío + consulta de adjuntos |
| Solo se ejecutan solicitudes aprobadas | Servicio Execution con bloqueo de fila |
| Justificación obligatoria al exceder aprobado | Servicio Execution |
| Las transiciones respetan la máquina de estados | Servicio Workflow + enum/transiciones |
| El aprobador asignado resuelve la revisión | Servicio Approval + bloqueo de fila |
| Los campos consolidados coinciden con revisión/ejecución | Misma transacción |
| Una solicitud no cruza organizaciones | Tenant context + FK compuestas + Policies |

## 12. Transacciones y concurrencia

Las siguientes operaciones deben realizarse dentro de transacciones PostgreSQL:

### Crear organización

1. Crear `organizations`.
2. Crear membresía del owner.
3. Confirmar ambas entidades.

Como `organizations.owner_user_id` no depende de la membresía, no existe una referencia circular.

### Crear solicitud

1. Incrementar `request_sequences` de forma atómica.
2. Crear `trace_requests`.
3. Crear el detalle específico.
4. Crear `request_created`.

### Tomar revisión

1. Bloquear la solicitud con `SELECT ... FOR UPDATE`.
2. Confirmar estado `submitted`.
3. Confirmar que el revisor sea elegible y distinto del creador.
4. Insertar `request_reviews` abierto.
5. Cambiar solicitud a `in_review`.
6. Crear `review_started`.

El índice parcial único actúa como protección final frente a carreras.

### Resolver revisión

1. Bloquear solicitud y revisión activa.
2. Validar actor, estado y decisión.
3. Resolver `request_reviews`.
4. Actualizar estado e importe consolidado de `trace_requests`.
5. Crear el evento correspondiente.
6. Confirmar.

### Completar solicitud

1. Bloquear solicitud aprobada.
2. Validar actor e importe.
3. Crear `request_executions`.
4. Actualizar `actual_amount`, `status` y `completed_at`.
5. Crear `request_completed`.
6. Confirmar.

Las notificaciones se despachan después del commit para impedir que se anuncie una operación revertida.

## 13. Estrategia de índices

Principios:

- Todos los listados multiempresa comienzan por `organization_id`.
- Los índices responden a consultas conocidas del MVP.
- No se indexan individualmente columnas de baja selectividad sin una consulta concreta.
- Los índices parciales se utilizan para registros activos/abiertos.
- Se verificará el plan real mediante `EXPLAIN (ANALYZE, BUFFERS)` cuando exista volumen representativo.

Consultas cubiertas:

| Consulta | Índice principal |
|---|---|
| Solicitudes por estado | `trace_requests_org_status_created_idx` |
| Mis solicitudes | `trace_requests_org_creator_created_idx` |
| Solicitudes por tipo | `trace_requests_org_type_created_idx` |
| Revisión activa | `request_reviews_one_open_unique` |
| Mis revisiones abiertas | `request_reviews_org_reviewer_open_idx` |
| Timeline | `request_events_request_occurred_idx` |
| Adjuntos activos | `request_attachments_request_active_idx` |
| Miembros activos | `memberships_organization_status_idx` |

## 14. Orden recomendado de migraciones

1. Habilitar/configurar requisitos base de PostgreSQL si fueran necesarios.
2. Crear `users` y tablas de autenticación.
3. Crear `currencies`.
4. Crear `organizations`.
5. Crear `memberships`.
6. Crear `roles`.
7. Crear `membership_roles`.
8. Crear `organization_invitations`.
9. Crear `organization_invitation_roles`.
10. Crear `request_sequences`.
11. Crear `trace_requests`.
12. Crear `purchase_request_details`.
13. Crear `reimbursement_request_details`.
14. Crear `request_reviews`.
15. Crear `request_executions`.
16. Crear `request_attachments`.
17. Crear `request_comments`.
18. Crear `request_events`.
19. Crear tablas de notificaciones y colas.
20. Crear índices funcionales y parciales que requieran SQL específico.
21. Ejecutar seeders de monedas y roles.

## 15. Seed inicial

### Roles

| code | name | description |
|---|---|---|
| `admin` | Administrador | Gestiona miembros, roles y configuración de la organización |
| `approver` | Aprobador | Puede tomar, observar, aprobar o rechazar solicitudes ajenas |

Los seeders deben ser idempotentes mediante `updateOrCreate` o `upsert`.

### Monedas

| code | name | symbol | decimal_places | Alcance |
|---|---|---|---:|---|
| `USD` | Dólar estadounidense | `$` | 2 | Global; también Ecuador |
| `EUR` | Euro | `€` | 2 | Global; también Guayana Francesa |
| `GBP` | Libra esterlina | `£` | 2 | Global |
| `ARS` | Peso argentino | `$` | 2 | Argentina |
| `BOB` | Boliviano | `Bs` | 2 | Bolivia |
| `BRL` | Real brasileño | `R$` | 2 | Brasil |
| `CLP` | Peso chileno | `$` | 0 | Chile |
| `COP` | Peso colombiano | `$` | 2 | Colombia |
| `GYD` | Dólar guyanés | `$` | 2 | Guyana |
| `PYG` | Guaraní paraguayo | `₲` | 0 | Paraguay |
| `PEN` | Sol peruano | `S/` | 2 | Perú |
| `SRD` | Dólar surinamés | `$` | 2 | Surinam |
| `UYU` | Peso uruguayo | `$U` | 2 | Uruguay |
| `VES` | Bolívar venezolano | `Bs.` | 2 | Venezuela |

El código, no el símbolo, identifica la moneda. Los símbolos repetidos son esperables. Todos los registros se crean activos y el selector del ABM de organizaciones consulta `currencies WHERE is_active = TRUE`.

## 16. Decisiones funcionales confirmadas

Las seis decisiones abiertas de la versión 1.0 quedaron resueltas:

1. **Categorías de reintegro:** seis categorías generales con códigos estables y etiquetas en español.
2. **Comprobantes:** imágenes JPEG, PNG o WebP hasta 15 MiB; original privado y derivado WebP optimizado a un máximo de 2400 px y calidad 82.
3. **Edición de comentarios:** ventana de 15 minutos, indicador visual mientras sea editable y leyenda posterior «Editado».
4. **Cancelación:** un reintegro aprobado no puede cancelarse; una compra aprobada solo admite cancelación excepcional por admin/owner antes de ejecutarse y con motivo.
5. **Referencia:** `REQ-{YYYY}-{000000}`, correlativa por organización y reiniciada anualmente.
6. **Monedas:** catálogo persistente `currencies`; el ABM de organizaciones consume monedas activas y define `default_currency_code` sin hardcodear opciones.

## 17. Fuera del MVP

No se incluyen todavía tablas para:

- Departamentos y centros de costo.
- Proveedores normalizados.
- Aprobadores por alcance o importe.
- Flujos multinivel.
- Delegaciones temporales.
- Formularios configurables.
- Conversión y cotización entre monedas.
- ABM de monedas y monedas habilitadas específicamente por organización.
- Suscripciones y facturación SaaS.
- Webhooks e integraciones externas.
- Versionado completo de comentarios o documentos.
- Eliminación permanente de solicitudes.

## 18. Criterio para comenzar implementación

El diccionario queda listo para traducirse a migraciones. Se implementará primero el núcleo mínimo:

1. `users`.
2. `currencies` y su seeder.
3. `organizations`.
4. `memberships`.
5. `roles` y `membership_roles`.
6. Pruebas de aislamiento y permisos básicos.

Las solicitudes se implementarán después de que la identidad multiempresa esté probada.
