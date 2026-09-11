SET NAMES utf8mb4;
SET time_zone = '-05:00';

CREATE TABLE IF NOT EXISTS ad_razones_sociales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NULL,
    nombre VARCHAR(180) NOT NULL,
    pais CHAR(2) NOT NULL DEFAULT 'CO',
    nit VARCHAR(40) NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ad_razon_nombre_pais (nombre, pais),
    KEY idx_ad_razon_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_personas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pais CHAR(2) NOT NULL DEFAULT 'CO',
    numero_documento VARCHAR(40) NULL,
    nombre_completo VARCHAR(220) NULL,
    cargo VARCHAR(180) NULL,
    ciudad VARCHAR(120) NULL,
    proceso VARCHAR(180) NULL,
    cliente VARCHAR(180) NULL,
    proyecto VARCHAR(180) NULL,
    razon_social_id BIGINT UNSIGNED NULL,
    estado_laboral ENUM('ACTIVO','RETIRADO','INACTIVO','SIN_VALIDAR') NOT NULL DEFAULT 'SIN_VALIDAR',
    fuente VARCHAR(80) NULL,
    fuente_id VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_persona_razon FOREIGN KEY (razon_social_id) REFERENCES ad_razones_sociales(id),
    UNIQUE KEY uq_ad_persona_pais_documento (pais, numero_documento),
    KEY idx_ad_persona_nombre (nombre_completo),
    KEY idx_ad_persona_estado (estado_laboral)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_proveedores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    razon_social VARCHAR(180) NOT NULL,
    nit VARCHAR(40) NULL,
    pais CHAR(2) NOT NULL DEFAULT 'CO',
    correo_contacto VARCHAR(180) NULL,
    telefono VARCHAR(60) NULL,
    condicion_pago VARCHAR(120) NULL,
    moneda_principal CHAR(3) NOT NULL DEFAULT 'COP',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ad_proveedor_nit (nit),
    KEY idx_ad_proveedor_nombre (razon_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_dominios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dominio VARCHAR(190) NOT NULL,
    razon_social_id BIGINT UNSIGNED NULL,
    proveedor_id BIGINT UNSIGNED NULL,
    fecha_vencimiento DATE NULL,
    valor_renovacion DECIMAL(18,2) NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'COP',
    renovacion_automatica TINYINT(1) NOT NULL DEFAULT 0,
    estado ENUM('ACTIVO','SUSPENDIDO','VENCIDO','PENDIENTE') NOT NULL DEFAULT 'ACTIVO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_dominio_razon FOREIGN KEY (razon_social_id) REFERENCES ad_razones_sociales(id),
    CONSTRAINT fk_ad_dominio_proveedor FOREIGN KEY (proveedor_id) REFERENCES ad_proveedores(id),
    UNIQUE KEY uq_ad_dominio (dominio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_planes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    fabricante VARCHAR(120) NULL,
    categoria ENUM('CORREO','PRODUCTIVIDAD','BI','PROYECTOS','ALMACENAMIENTO','OTRO') NOT NULL DEFAULT 'OTRO',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ad_plan_codigo (codigo),
    UNIQUE KEY uq_ad_plan_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_plan_aliases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    alias VARCHAR(220) NOT NULL,
    alias_normalizado VARCHAR(220) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_alias_plan FOREIGN KEY (plan_id) REFERENCES ad_planes(id),
    UNIQUE KEY uq_ad_alias_normalizado (alias_normalizado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_suscripciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proveedor_id BIGINT UNSIGNED NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    razon_social_id BIGINT UNSIGNED NULL,
    referencia_contrato VARCHAR(120) NULL,
    referencia_sku VARCHAR(120) NULL,
    pais CHAR(2) NULL,
    cantidad_contratada INT UNSIGNED NULL,
    valor_unitario DECIMAL(18,4) NOT NULL DEFAULT 0,
    moneda CHAR(3) NOT NULL DEFAULT 'COP',
    periodicidad ENUM('MENSUAL','ANUAL','UNICO','OTRO') NOT NULL DEFAULT 'MENSUAL',
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    estado ENUM('BORRADOR','VIGENTE','SUSPENDIDA','VENCIDA','FINALIZADA') NOT NULL DEFAULT 'VIGENTE',
    origen ENUM('CONTRATO','MIGRACION_EXCEL','FACTURA','MANUAL') NOT NULL DEFAULT 'MANUAL',
    hash_importacion CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_suscripcion_proveedor FOREIGN KEY (proveedor_id) REFERENCES ad_proveedores(id),
    CONSTRAINT fk_ad_suscripcion_plan FOREIGN KEY (plan_id) REFERENCES ad_planes(id),
    CONSTRAINT fk_ad_suscripcion_razon FOREIGN KEY (razon_social_id) REFERENCES ad_razones_sociales(id),
    UNIQUE KEY uq_ad_suscripcion_hash (hash_importacion),
    KEY idx_ad_suscripcion_estado (estado),
    KEY idx_ad_suscripcion_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_cuentas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    correo VARCHAR(190) NOT NULL,
    correo_normalizado VARCHAR(190) NOT NULL,
    dominio_id BIGINT UNSIGNED NULL,
    tipo ENUM('PERSONAL','GENERICA','COMPARTIDA','SISTEMA','TEMPORAL','CONTRATISTA','PENDIENTE') NOT NULL DEFAULT 'PENDIENTE',
    estado ENUM('ACTIVA','GESTION_DE_BAJA') NOT NULL DEFAULT 'ACTIVA',
    razon_social_id BIGINT UNSIGNED NULL,
    pais CHAR(2) NOT NULL DEFAULT 'CO',
    ciudad VARCHAR(120) NULL,
    centro_costo VARCHAR(120) NULL,
    responsable_funcional VARCHAR(220) NULL,
    observaciones TEXT NULL,
    fecha_creacion DATE NULL,
    fecha_bloqueo DATE NULL,
    fecha_eliminacion DATE NULL,
    legacy_sheet VARCHAR(80) NULL,
    legacy_row INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_cuenta_dominio FOREIGN KEY (dominio_id) REFERENCES ad_dominios(id),
    CONSTRAINT fk_ad_cuenta_razon FOREIGN KEY (razon_social_id) REFERENCES ad_razones_sociales(id),
    UNIQUE KEY uq_ad_cuenta_correo (correo_normalizado),
    KEY idx_ad_cuenta_estado (estado),
    KEY idx_ad_cuenta_pais (pais),
    KEY idx_ad_cuenta_razon (razon_social_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_asignaciones_cuenta (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuenta_id BIGINT UNSIGNED NOT NULL,
    persona_id BIGINT UNSIGNED NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    estado ENUM('ACTIVA','CERRADA','PENDIENTE') NOT NULL DEFAULT 'ACTIVA',
    motivo VARCHAR(220) NULL,
    observaciones TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_asig_cuenta FOREIGN KEY (cuenta_id) REFERENCES ad_cuentas(id),
    CONSTRAINT fk_ad_asig_persona FOREIGN KEY (persona_id) REFERENCES ad_personas(id),
    KEY idx_ad_asig_cuenta_estado (cuenta_id, estado),
    KEY idx_ad_asig_persona_estado (persona_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_asignaciones_licencia (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuenta_id BIGINT UNSIGNED NOT NULL,
    suscripcion_id BIGINT UNSIGNED NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    valor_unitario_asignado DECIMAL(18,4) NOT NULL DEFAULT 0,
    moneda CHAR(3) NOT NULL DEFAULT 'COP',
    estado ENUM('ACTIVA','CERRADA','PENDIENTE') NOT NULL DEFAULT 'ACTIVA',
    motivo VARCHAR(220) NULL,
    observaciones TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_asig_lic_cuenta FOREIGN KEY (cuenta_id) REFERENCES ad_cuentas(id),
    CONSTRAINT fk_ad_asig_lic_sus FOREIGN KEY (suscripcion_id) REFERENCES ad_suscripciones(id),
    KEY idx_ad_asig_lic_estado (suscripcion_id, estado),
    KEY idx_ad_asig_lic_cuenta (cuenta_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_facturas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proveedor_id BIGINT UNSIGNED NOT NULL,
    razon_social_id BIGINT UNSIGNED NULL,
    numero_factura VARCHAR(80) NOT NULL,
    fecha_emision DATE NOT NULL,
    fecha_recepcion DATE NULL,
    fecha_vencimiento DATE NULL,
    periodo_desde DATE NULL,
    periodo_hasta DATE NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'COP',
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    descuentos DECIMAL(18,2) NOT NULL DEFAULT 0,
    impuestos DECIMAL(18,2) NOT NULL DEFAULT 0,
    retenciones DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_pagar DECIMAL(18,2) NOT NULL DEFAULT 0,
    orden_compra VARCHAR(120) NULL,
    cufe VARCHAR(220) NULL,
    metodo_pago VARCHAR(80) NULL,
    estado ENUM('BORRADOR','RECIBIDA','EN_VALIDACION','CON_NOVEDAD','PENDIENTE_APROBACION','APROBADA','CUENTAS_POR_PAGAR','PROGRAMADA','PAGADA','RECHAZADA','DEVUELTA','ANULADA') NOT NULL DEFAULT 'BORRADOR',
    archivo_pdf VARCHAR(500) NULL,
    observaciones TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_factura_proveedor FOREIGN KEY (proveedor_id) REFERENCES ad_proveedores(id),
    CONSTRAINT fk_ad_factura_razon FOREIGN KEY (razon_social_id) REFERENCES ad_razones_sociales(id),
    UNIQUE KEY uq_ad_factura_proveedor_numero (proveedor_id, numero_factura),
    KEY idx_ad_factura_estado (estado),
    KEY idx_ad_factura_vencimiento (fecha_vencimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_factura_detalles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NULL,
    suscripcion_id BIGINT UNSIGNED NULL,
    descripcion VARCHAR(255) NOT NULL,
    descripcion_normalizada VARCHAR(255) NOT NULL,
    cantidad DECIMAL(12,2) NOT NULL DEFAULT 0,
    valor_unitario DECIMAL(18,4) NOT NULL DEFAULT 0,
    descuento DECIMAL(18,2) NOT NULL DEFAULT 0,
    impuesto DECIMAL(18,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    total DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_factura_det_factura FOREIGN KEY (factura_id) REFERENCES ad_facturas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_factura_det_plan FOREIGN KEY (plan_id) REFERENCES ad_planes(id),
    CONSTRAINT fk_ad_factura_det_sus FOREIGN KEY (suscripcion_id) REFERENCES ad_suscripciones(id),
    KEY idx_ad_factura_det_plan (plan_id),
    KEY idx_ad_factura_det_factura (factura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_conciliaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_detalle_id BIGINT UNSIGNED NOT NULL,
    cantidad_facturada DECIMAL(12,2) NOT NULL DEFAULT 0,
    cantidad_contratada DECIMAL(12,2) NULL,
    cantidad_asignada DECIMAL(12,2) NULL,
    diferencia_facturado_asignado DECIMAL(12,2) NULL,
    tarifa_contratada DECIMAL(18,4) NULL,
    tarifa_facturada DECIMAL(18,4) NOT NULL DEFAULT 0,
    diferencia_tarifa DECIMAL(18,4) NULL,
    estado ENUM('OK','POR_JUSTIFICAR','CON_NOVEDAD','APROBADA_EXCEPCION') NOT NULL DEFAULT 'POR_JUSTIFICAR',
    clasificacion VARCHAR(120) NULL,
    justificacion TEXT NULL,
    revisado_por BIGINT UNSIGNED NULL,
    revisado_at DATETIME NULL,
    calculado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_conc_detalle FOREIGN KEY (factura_detalle_id) REFERENCES ad_factura_detalles(id) ON DELETE CASCADE,
    UNIQUE KEY uq_ad_conc_detalle (factura_detalle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_conciliaciones_plan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    cantidad_facturada DECIMAL(12,2) NOT NULL DEFAULT 0,
    cantidad_contratada DECIMAL(12,2) NULL,
    cantidad_asignada DECIMAL(12,2) NOT NULL DEFAULT 0,
    diferencia_facturado_asignado DECIMAL(12,2) NOT NULL DEFAULT 0,
    estado ENUM('OK','POR_JUSTIFICAR','CON_NOVEDAD','APROBADA_EXCEPCION') NOT NULL DEFAULT 'POR_JUSTIFICAR',
    clasificacion VARCHAR(120) NULL,
    justificacion TEXT NULL,
    revisado_por BIGINT UNSIGNED NULL,
    revisado_at DATETIME NULL,
    calculado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_conc_plan_factura FOREIGN KEY (factura_id) REFERENCES ad_facturas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_conc_plan_plan FOREIGN KEY (plan_id) REFERENCES ad_planes(id),
    UNIQUE KEY uq_ad_conc_plan (factura_id, plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_distribuciones_factura (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    factura_detalle_id BIGINT UNSIGNED NOT NULL,
    razon_social_id BIGINT UNSIGNED NULL,
    centro_costo VARCHAR(120) NULL,
    pais CHAR(2) NULL,
    porcentaje DECIMAL(8,4) NULL,
    valor DECIMAL(18,2) NOT NULL DEFAULT 0,
    observaciones VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_dist_detalle FOREIGN KEY (factura_detalle_id) REFERENCES ad_factura_detalles(id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_dist_razon FOREIGN KEY (razon_social_id) REFERENCES ad_razones_sociales(id),
    KEY idx_ad_dist_detalle (factura_detalle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_respaldos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuenta_id BIGINT UNSIGNED NULL,
    servidor VARCHAR(180) NULL,
    carpeta VARCHAR(255) NULL,
    subcarpeta_1 VARCHAR(255) NULL,
    subcarpeta_2 VARCHAR(255) NULL,
    subcarpeta_3 VARCHAR(255) NULL,
    archivo VARCHAR(255) NULL,
    repositorio_cloud VARCHAR(120) NULL,
    fecha_carga DATE NULL,
    tamano_pst VARCHAR(80) NULL,
    tamano_onedrive VARCHAR(80) NULL,
    estado ENUM('REGISTRADO','VALIDADO','PENDIENTE','ELIMINADO') NOT NULL DEFAULT 'REGISTRADO',
    legacy_row INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_respaldo_cuenta FOREIGN KEY (cuenta_id) REFERENCES ad_cuentas(id),
    KEY idx_ad_respaldo_estado (estado),
    UNIQUE KEY uq_ad_respaldo_legacy_row (legacy_row)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_novedades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuenta_id BIGINT UNSIGNED NULL,
    tipo VARCHAR(80) NOT NULL,
    estado ENUM('ABIERTA','EN_GESTION','CERRADA','ANULADA') NOT NULL DEFAULT 'ABIERTA',
    fecha_novedad DATE NULL,
    descripcion TEXT NULL,
    datos_origen JSON NULL,
    legacy_sheet VARCHAR(80) NULL,
    legacy_row INT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_novedad_cuenta FOREIGN KEY (cuenta_id) REFERENCES ad_cuentas(id),
    KEY idx_ad_novedad_estado (estado),
    KEY idx_ad_novedad_cuenta (cuenta_id),
    UNIQUE KEY uq_ad_novedad_legacy (legacy_sheet, legacy_row)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_importaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    archivo VARCHAR(255) NOT NULL,
    hash_archivo CHAR(64) NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    estado ENUM('INICIADA','COMPLETADA','COMPLETADA_CON_ERRORES','FALLIDA') NOT NULL DEFAULT 'INICIADA',
    filas_leidas INT UNSIGNED NOT NULL DEFAULT 0,
    filas_insertadas INT UNSIGNED NOT NULL DEFAULT 0,
    filas_actualizadas INT UNSIGNED NOT NULL DEFAULT 0,
    filas_omitidas INT UNSIGNED NOT NULL DEFAULT 0,
    errores INT UNSIGNED NOT NULL DEFAULT 0,
    resumen JSON NULL,
    ejecutado_por BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    UNIQUE KEY uq_ad_import_hash_tipo (hash_archivo, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_importacion_errores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    importacion_id BIGINT UNSIGNED NOT NULL,
    hoja VARCHAR(80) NULL,
    fila INT UNSIGNED NULL,
    campo VARCHAR(120) NULL,
    mensaje VARCHAR(500) NOT NULL,
    datos JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ad_imp_error_import FOREIGN KEY (importacion_id) REFERENCES ad_importaciones(id) ON DELETE CASCADE,
    KEY idx_ad_imp_error_import (importacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entidad VARCHAR(100) NOT NULL,
    entidad_id BIGINT UNSIGNED NOT NULL,
    accion ENUM('CREAR','ACTUALIZAR','ELIMINAR','APROBAR','RECHAZAR','IMPORTAR','CONCILIAR') NOT NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    usuario_id BIGINT UNSIGNED NULL,
    ip VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ad_audit_entidad (entidad, entidad_id),
    KEY idx_ad_audit_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
