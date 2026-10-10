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

- El cliente del juego: la primera vez es una historia que termina con el personaje del alumno; después, su región,
  su equipo, los retos del aula, su diario y su ficha. A pantalla completa, ligero, sin servicios externos.
- El panel del docente, pensado para el móvil: la clase con nombres, fotos y personajes, toques de saber, gemas y
  aliento, retos del aula (también validados sin conexión), pausa y recuperación, el registro con deshacer y los
  ajustes del curso.
- El motor del juego: saber con topes semanal y diario, gemas, energía y caída, aliento, objetos, deshacer, y las
  actividades completadas de Moodle convertidas en progreso.

- Las tablas del juego: personajes, cursos (regiones), miembros, equipos, retos, el registro de todo lo que suma y
  resta, poderes y sus peticiones, objetos, misiones, capítulos, escenas y sus intentos, y resultados de minijuegos.
- Las capacidades: el alumno juega; el docente configura el juego, da saber a mano, aprueba poderes, pone en pausa
  a un alumno, ve y deshace el registro y lleva la pantalla de clase.
- El proveedor de privacidad, desde el primer día: exportación y borrado de todo lo que el juego guarda de un
  alumno.
- Los niveles, las etapas del aspecto del personaje y el tope semanal por curso, con sus pruebas.
- Una clase inventada para probarlo en un sitio de desarrollo (ver Desarrollo).

Después llegan las escenas de decisión y el mapa de la misión piloto, equipos y poderes, los minijuegos y la
pantalla de clase.

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
clasificaciones individuales públicas y sin compras.

## Desarrollo

Para probar el juego en un sitio de desarrollo, `cli/demo.php` crea una clase inventada: un curso, un docente,
alumnos con nombres inventados, equipos, personajes y, con `--weeks`, unas semanas de progreso. Nunca en un sitio con
alumnos reales.

```bash
php local/oksigeniatesape/cli/demo.php --run --weeks=3
```

Código, comentarios y mensajes de commit en inglés; los textos, en los paquetes de idioma (`lang/en`, `lang/es`).
Las pruebas pasan con `moodle-plugin-ci` en GitHub Actions sobre Moodle 4.5, 5.2 y 5.3, y sobre `main` para ver lo
que viene. Ver [CONTRIBUTING.md](CONTRIBUTING.md).

## Licencia

GNU GPL v3 o posterior. © 2026 Oksigenia. Las fuentes Lexend y Cherry Swash, en `app/fonts`, tienen licencia SIL Open
Font License 1.1 (en `thirdpartylibs.xml`).
