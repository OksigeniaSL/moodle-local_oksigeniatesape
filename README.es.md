# Juego de rol para el aula en Moodle (Oksigenia Tesapé)

[![Licencia: GPLv3](https://img.shields.io/badge/licencia-GPLv3+-blue.svg)](LICENSE)
![Moodle](https://img.shields.io/badge/Moodle-4.5%E2%80%935.3-orange)
![Estado](https://img.shields.io/badge/estado-alfa-red)

**Tesapé** es un juego de rol para el aula que se juega sobre Moodle. Cada alumno tiene un personaje para todo el
sitio; cada curso es una región del mundo, con su misión, su equipo y sus poderes. Los alumnos aprenden jugando
escenas de decisión y minijuegos, haciendo las actividades del curso y ayudando a su equipo. El docente configura
el juego en su curso, lo sigue desde su panel y lo lleva en la pantalla de clase.

Moodle es la columna vertebral que el alumno no ve: las cuentas, los cursos, los grupos, la finalización de
actividades, las copias de seguridad y la privacidad vienen de Moodle, y el juego es una aplicación propia a
pantalla completa.

*Tesapé* significa «luz» en guaraní, del verbo iluminar, alumbrar.

[Read in English](README.md)

## Estado

**Alfa, en desarrollo: todavía no es para usarlo con alumnos.** Lo que hay hoy:

- **Una misión para jugar**: la misión piloto, *Los seres vivos* (9 a 11 años, en español), viene con el plugin. Su
  mapa tiene seis lugares con sus linternas; cada lugar tiene sus escenas de decisión y un reto del aula. Las escenas
  se juegan dentro del juego: decisiones con consecuencias, cosas que repartir en grupos, pasos que
  ordenar. El servidor vuelve a jugar cada escena con lo que hizo el alumno y decide él el final, así que el
  resultado no depende del navegador. El primer final bueno da saber, páginas del libro, objetos y, en la primera
  escena, la criatura compañera; las linternas se encienden para el alumno y, cuando las tiene la mitad de la clase,
  para la clase.
- **El cliente del juego**: la primera vez es una historia que termina con el personaje del alumno; después, el
  mapa de la misión, su equipo, los retos del aula, el libro, el diario y su ficha con su criatura. A pantalla
  completa, ligero, sin servicios externos.
- **El panel del docente**, pensado para el móvil: la clase con nombres, fotos y personajes, toques de saber, gemas y
  aliento, la misión del curso (con su historia y sus ejemplos del lugar) y cómo va la clase en cada escena y
  objetivo, con las confusiones más frecuentes; retos del aula (también validados sin conexión), pausa y
  recuperación, el registro con deshacer y los ajustes del curso.
- **Equipos y poderes**: equipos hechos de una vez con los papeles repartidos, una meta semanal por equipo y una
  clasificación opcional; poderes que cuestan aliento, que piden los alumnos y aprueba el docente con un toque, y
  algunos de los cuales actúan en el juego (escudo, muralla, respiro, revivir, ánimo).
- **Actividades de Moodle dentro del juego**: cualquier actividad puede ser un reto desde sus ajustes; la misión
  piloto trae sus foros, sus tareas y la guía de campo, que se abren dentro del juego.
- **Defensa del nido**, el primer minijuego, por escalones y con un modo sin prisa; y **el buzón de ideas** de los
  alumnos para sus docentes.
- **La pantalla de clase**, para el proyector: el mapa de la misión con las linternas de la clase, los equipos con sus
  retratos y toques rápidos de saber y gemas, y **el evento del día** (calma, brisa favorable, huella misteriosa con una
  pregunta para toda la clase, intercambio de aliento entre equipos). Se pone al día sola, así que lo que el docente
  hace desde el móvil aparece en la pizarra.
- **El motor del juego**: saber con topes semanal y diario, gemas, energía y caída, aliento, objetos, deshacer, y las
  actividades completadas de Moodle convertidas en progreso. Proveedor de privacidad desde el primer día.

El juego está preparado para **paquetes de arte** (`cli/art.php`), y el primero está casi terminado. Después llegan
las batallas con los móviles de los alumnos y las escenas votadas por la clase.

## Requisitos

- Moodle 4.5 a 5.3: las ramas que siguen recibiendo parches de seguridad. Se desarrolla sobre 5.3.
- PHP según pida tu Moodle.

## Instalación

```bash
cd /ruta/a/moodle/public/local/   # /ruta/a/moodle/local/ antes de Moodle 5.1
git clone https://github.com/OksigeniaSL/moodle-local_oksigeniatesape.git oksigeniatesape
```

Después entra en *Administración del sitio → Notificaciones* para terminar la instalación.

## Privacidad

El personaje pertenece al contexto de usuario del alumno; lo que pasa en un curso, al curso. El proveedor de
privacidad cubre todas las tablas que guardan algo de un alumno (exportación y borrado), y los docentes que
pusieron en pausa a un alumno, le dieron algo o decidieron sobre un poder siguen en los registros del alumno, sin su
nombre, cuando se borran sus propios datos.

Juegan menores, así que guarda solo lo que el juego necesita: sin servicios externos, sin CDN, sin seguimiento, sin
clasificaciones individuales públicas y sin compras. Las ideas que envían los alumnos se quedan en Moodle, solo para
sus docentes; las imágenes de los paquetes de arte las sirve el propio Moodle.

## Desarrollo

Para probar el juego en un sitio de desarrollo, `cli/demo.php` crea una clase inventada: un curso con la misión y
sus actividades, un docente, alumnos con nombres inventados, equipos, personajes y, con `--weeks`, unas semanas de
progreso, poderes pendientes e ideas en el buzón. Nunca en un sitio con alumnos reales.

```bash
php local/oksigeniatesape/cli/demo.php --run --weeks=3
```

Un paquete de arte es un zip con `pack.json` (código, nombre, versión y `files`: claves como `character.storyheroes.healer`,
`creature.2.feathers`, `icon.saber` o `map.livingthings`, cada una con una imagen del zip en PNG, WebP o JPEG):

```bash
php local/oksigeniatesape/cli/art.php --install=/ruta/al/paquete.zip
```

Código, comentarios y mensajes de commit en inglés; los textos, en los paquetes de idioma (`lang/en`, `lang/es`).
Las pruebas pasan con `moodle-plugin-ci` en GitHub Actions sobre Moodle 4.5, 5.2 y 5.3, y sobre `main` para ver lo
que viene. Ver [CONTRIBUTING.md](CONTRIBUTING.md).

## Licencia

GNU GPL v3 o posterior. © 2026 Oksigenia. Las fuentes Lexend y Cherry Swash, en `app/fonts`, tienen licencia SIL Open
Font License 1.1 (en `thirdpartylibs.xml`).
