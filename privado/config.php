<?php
// Configuración del sitio Mi Vaso. Se edita desde /admin/.
return array (
  'destinatarios' => 
  array (
    0 => 'contacto@mivaso.cl',
  ),
  'cc' => 
  array (
  ),
  'remitente' => 'web@mivaso.cl',
  'remitente_nombre' => 'Web Mi Vaso',
  'copia_cliente' => false,
  'guardar_historial' => true,
  'smtp' => 
  array (
    'activo' => false,
    'host' => '',
    'puerto' => 465,
    'seguridad' => 'ssl',
    'usuario' => '',
    'clave' => '',
  ),
  'admin_hash' => '$2y$10$qqNQeRqvCM57flnhigqU7.uMjctBA5fH5VOn24eH9z75gcDdU1iCG',
  'google' => 
  array (
    'ga4' => 'G-200QXRBRFL',
    'ads' => 
    array (
      0 => 'AW-846443580',
      1 => 'AW-7587623391',
    ),
    'gtm' => '',
    'verificacion' => '',
    'conv_cotizacion' => '',
    'conv_contacto' => '',
    'conv_whatsapp' => '',
  ),
);
