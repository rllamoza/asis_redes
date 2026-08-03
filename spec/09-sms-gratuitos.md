# 09 — Autenticación por WhatsApp (reemplaza SMS)

> **Decisión (2026-08-02):** la recuperación por **SMS se reemplazó por WhatsApp vía enlaces `wa.me`** (sin costo ni API): `Notificaciones::enviarWhatsApp()` genera `https://wa.me/<número>?text=<mensaje>` que abre WhatsApp con el mensaje listo para enviar. Este documento conserva la investigación previa de SMS por referencia.

## Implementado en la app

- `core/Notificaciones.php` → `enviarWhatsApp($telefono, $mensaje)`:
  - Destino: número de `WHATSAPP_OPERADOR` (si está configurado) o el número del receptor.
  - Escribe un log en `storage/logs/whatsapp_*.txt` y devuelve el enlace `wa.me`.
  - No requiere API ni costo; el envío es manual (el operador pulsa enviar en WhatsApp).
- Recuperación: `?ruta=recuperar` con `medio=whatsapp` genera el código (6 dígitos, 30 min) y el enlace.
- Herramienta de prueba en `?ruta=servidor` (tarjeta "Herramientas de envío" → "Probar WhatsApp").

> Nota: para envío **automático** a WhatsApp se requeriría la API de negocio de WhatsApp (Meta) con plantillas y verificación, que es de pago.

## Investigación previa: métodos gratuitos para SMS (referencia)

**Conclusión: no hay un servicio de SMS gratis confiable y permanente.**

| Método | Costo | Límites | Fiabilidad | Veredicto |
|---|---|---|---|---|
| **TextBelt** (`textbelt.com/text`) | Clave gratuita `textbelt` → **1 SMS gratis al día**; clave propia ≈ $0.09/SMS | 1/día con clave libre | Media | Usable para pruebas; insuficiente para producción |
| **Email-a-SMS** (gateway del operador) | Gratis (el email normal al gateway) | Depende del operador; no todos envían | Baja/media | Gratis pero depende del operador del receptor |
| **Twilio** | Cuenta de prueba: ~$15 de crédito gratis | Una vez; requiere verificación de número | Alta | Solo para demo |
| **Message Central** | Trial gratuito (unas decenas de SMS) | Una vez, 30 días | Alta | Solo para demo |
| **WhatsApp** (Business API / librerías) | Gratis vía WhatsApp; APIs de pago | El receptor debe tener WhatsApp | Alta | Alternativa gratuita si los usuarios usan WhatsApp |

El método `enviarSMS()` con modos `SMS_METODO` (`dev`, `textbelt`, `email-gateway`, `twilio`) permanece en `Notificaciones.php` pero ya no se usa en los flujos de la app.
