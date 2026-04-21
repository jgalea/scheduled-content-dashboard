=== Scheduled Content Dashboard ===
Contributors: jeangalea
Tags: scheduled, dashboard, widget, editorial calendar, missed schedule
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Widget del escritorio + calendario editorial con arrastrar y soltar. Autorepara publicaciones fallidas. API REST y resumen por email. Sin bloat.

== Description ==

Scheduled Content Dashboard te ofrece una vista clara de todo lo que tienes preparado para publicarse. Añade un widget al escritorio que agrupa el contenido programado según cuándo se publicará, una página completa de calendario editorial donde puedes arrastrar entradas a otros días, marca las entradas que WordPress no ha podido publicar a tiempo, y las republica en silencio por ti.

La mayoría de plugins de programación añaden redes sociales, opciones enrevesadas y upsells de marketing. Este no. Muestra qué está programado, te deja reordenarlo y mantiene las publicaciones saliendo. Y ya está.

= Características =

* **Página completa de calendario editorial** — cuadrícula mensual a pantalla completa con reprogramación por arrastrar y soltar (se conserva la hora del día)
* **Widget del escritorio** — contenido programado agrupado por Hoy, Mañana, Esta semana, La próxima semana y Más adelante
* **Vista de mini calendario** — cambia el widget a una cuadrícula mensual con puntos en los días con contenido programado
* **Detección de programaciones fallidas** — las entradas atascadas en estado `future` tras su fecha se marcan en rojo
* **Autoreparación de programaciones fallidas** — republica silenciosamente las entradas atascadas al cargar páginas del admin (la mayoría de competidores lo reservan al plan de pago)
* **Botón "Publicar ahora" de un clic** — publica manualmente cualquier entrada fallida desde el widget
* **Contador en la barra de administración** — consulta cuántas entradas están programadas (y si alguna ha fallado) desde cualquier parte del admin
* **Resumen por email opcional** — resumen diario o semanal de las entradas fallidas y próximas a cualquier destinatario
* **API REST** — endpoints para scheduled, missed, counts, publish-now y reschedule
* **Filtros de tipo de contenido + autor** — profundiza dentro del widget sin salir del escritorio
* **Conmutador "Solo las mías"** — los sitios multiautor pueden filtrar el widget para ver solo el contenido del usuario actual
* **Borradores en el widget (opcional)** — muestra borradores junto al contenido programado
* **Página de ajustes** — configura el límite de elementos, qué tipos de contenido incluir, la vista por defecto, la autoreparación y el resumen
* **Todos los tipos de contenido públicos** — entradas, páginas, productos, eventos, tipos de contenido personalizados
* **Respetuoso con la privacidad** — sin seguimiento, sin peticiones externas, sin cookies

= Casos de uso =

* Equipos editoriales que gestionan un calendario de contenido
* Blogueros que programan entradas con antelación
* Agencias que gestionan varios sitios de clientes
* Cualquier persona cansada de que WordPress se salte las horas de publicación programadas

= Privacidad =

Este plugin no recopila datos, no envía datos a servidores externos, no usa cookies y no hace seguimiento de usuarios. Todo lo que se muestra ya está en tu base de datos de WordPress.

= Hooks para desarrolladores =

`scheduled_content_dashboard_query_args` — filtra los argumentos WP_Query usados para la lista de contenido programado.

`scheduled_content_dashboard_auto_fix_missed` — devuelve `false` para desactivar la publicación automática de entradas programadas fallidas.

= API REST =

Namespace base: `scheduled-content-dashboard/v1`. Todos los endpoints requieren un usuario identificado con la capacidad `edit_posts`.

* `GET /scheduled` — lista entradas programadas (parámetros: `post_type`, `author`, `limit`)
* `GET /missed` — lista entradas programadas fallidas
* `GET /counts` — devuelve `{ total, scheduled, missed }`
* `POST /publish/{id}` — publica una entrada programada ahora
* `POST /reschedule/{id}` — cambia la fecha de publicación de una entrada programada (cuerpo: `date` en cualquier formato que entienda `strtotime()`)

== Installation ==

1. Ve a Plugins > Añadir nuevo en tu administración de WordPress
2. Busca "Scheduled Content Dashboard"
3. Pulsa Instalar ahora y luego Activar
4. Visita tu Escritorio para ver el widget

== Frequently Asked Questions ==

= ¿Dónde aparece el widget? =

En el escritorio principal de administración de WordPress. Puedes arrastrarlo para cambiarlo de sitio entre los demás widgets.

= ¿Qué tipos de contenido se muestran? =

Todos los tipos de contenido públicos: entradas, páginas y cualquier tipo de contenido personalizado registrado como público (productos, eventos, carteras, etc.).

= ¿Qué es una "programación fallida"? =

WordPress usa wp-cron para publicar entradas programadas a su hora prevista. Si cron no se dispara (sitios con poco tráfico, problemas de cron del servidor, errores fatales), las entradas se quedan atascadas en estado `future` pasada su fecha de publicación. Este plugin detecta esas entradas, las marca y, por defecto, las publica automáticamente la próxima vez que cargues una página del admin.

= ¿Cómo desactivo la autoreparación? =

Añade esto al `functions.php` de tu tema o a un mu-plugin:

`add_filter( 'scheduled_content_dashboard_auto_fix_missed', '__return_false' );`

Seguirás viendo las entradas fallidas marcadas en el widget con un botón manual "Publicar ahora".

= ¿Cuántos elementos programados se muestran? =

Hasta 50 por grupo, ordenados por fecha programada (los más próximos primero).

= ¿Funciona con Gutenberg / el editor de bloques? =

Sí. El plugin muestra el contenido programado y enlaza con las pantallas de edición estándar — funciona con cualquier editor.

= ¿Funciona con Multisitio? =

Sí. Cada sitio tiene su propio widget que muestra el contenido programado de ese sitio.

= El widget no aparece — ¿qué hago? =

1. Confirma que el plugin está activado
2. Confirma que tienes contenido programado (entradas con fecha de publicación futura)
3. En el Escritorio, pulsa "Opciones de pantalla" y asegúrate de que "Scheduled Content" está marcado

== Screenshots ==

1. El widget Scheduled Content agrupando entradas según cuándo se publicarán
2. Detección de programaciones fallidas con un botón "Publicar ahora" para cada entrada atascada
3. Contador en la barra de administración con el total de programadas

== Changelog ==

= 2.0.0 =
* Añadido: página completa de calendario editorial con reprogramación por arrastrar y soltar (jQuery UI)
* Añadido: menú superior "Scheduled" con los subelementos Calendar y Settings
* Añadido: API REST (`scheduled-content-dashboard/v1`) con endpoints scheduled, missed, counts, publish, reschedule
* Añadido: resumen por email opcional (diario o semanal) a las 9:00 locales, destinatarios configurables
* Añadido: enlace "Open full calendar" en la cabecera del widget
* Cambiado: al desactivar se limpia correctamente el evento cron del resumen

= 1.2.0 =
* Añadido: página de ajustes (Ajustes > Scheduled Content) para límite de elementos, tipos de contenido incluidos, vista por defecto, borradores y autoreparación
* Añadido: vista de mini calendario mensual con preferencia por usuario, puntos para los días con contenido programado, resaltado de días con entradas fallidas y detalle del día
* Añadido: desplegables de filtro de tipo de contenido y autor en el widget (plegables)
* Añadido: grupo opcional de borradores junto al contenido programado
* Añadido: conmutador de vista "Lista / Calendario" por usuario
* Cambiado: la autoreparación ahora respeta también el conmutador de la UI de ajustes además del filtro

= 1.1.0 =
* Añadido: detección de programaciones fallidas con marca roja en el widget
* Añadido: autoreparación de entradas programadas fallidas (cron al cargar el admin, filtrable)
* Añadido: botón "Publicar ahora" de un clic para entradas fallidas
* Añadido: contador en la barra de administración con total y fallidas
* Añadido: conmutador "Solo las mías" para filtrar el widget por el usuario actual
* Añadido: filtro `scheduled_content_dashboard_auto_fix_missed`
* Cambiado: la consulta de elementos programados omite las entradas fallidas (se muestran en su propio grupo)

= 1.0.0 =
* Lanzamiento inicial
* Widget del escritorio con contenido programado agrupado
* Soporte para todos los tipos de contenido públicos

== Upgrade Notice ==

= 2.0.0 =
Versión mayor. Añade calendario editorial completo con arrastrar y soltar, API REST y resumen por email opcional. El plugin crece más allá de "solo widget del escritorio" — pero el widget y todas las funciones de 1.x siguen funcionando igual.

= 1.2.0 =
Añade página de ajustes, vista de mini calendario, filtros de tipo de contenido y autor, y visualización opcional de borradores. Los usuarios existentes no tienen que cambiar nada — los valores por defecto coinciden con el comportamiento anterior.

= 1.1.0 =
Añade detección de programaciones fallidas con autoreparación gratuita, contador en la barra de administración y filtrado por usuario. La autoreparación está activada por defecto — desactívala con el filtro scheduled_content_dashboard_auto_fix_missed si prefieres control manual.
