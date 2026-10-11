# Changelog

## 0.6.0 (2026-10-11)

- **The battle against the Embrollo**, from the class screen: questions of the mission for the whole class, four to
  ten, with or without a time limit. Each right answer takes life from the Embrollo (a mischievous skein of yarn, not a
  monster); a wrong one takes nothing from anybody. If the class wins, saber and gems for everybody who played, and
  more gems for the team that helped most.
- **With the phones of the students**: the game on the phone shows a call by itself when a battle opens; inside, the
  question with big buttons, what happened when the answer is shown, and the prize. The class screen shows who is in
  and how many answered, and shows the answer by itself when time is up or everybody answered.
- **Without devices**: teams answer aloud or with their A, B, C, D cards, and the teacher marks on the class screen the
  teams that got it right.
- Live sessions adapted from Oksigenia Classtools (new tables `local_oksigeniatesape_live` and `_livein`): the state
  is polled, a unique index keeps anybody from answering twice, the server closes each question on time, and the right
  answer never reaches the phones before it is shown. Players always use their Moodle account; the team comes from the
  groups of the course. Answers are in the privacy provider and old battles are deleted by a daily task.

## 0.5.0 (2026-10-11)

- **The class screen** (`board.php`), for the projector or the whiteboard, opened from the teacher panel: the map of
  the mission with the lanterns of the class, the teams with their portraits and the goal of the week, and quick
  touches of saber and gems on students or whole teams. Only good news on it: nothing that takes something away from a
  student in front of the class. It refreshes itself every few seconds, so what the teacher does from the phone shows
  up on the board.
- **The event of the day**: a card drawn when the class starts, that the teacher can change or skip until something
  has come of it. Calm: nobody loses energy today. Fair breeze: activities completed today give double saber. Mysterious
  footprint: a question for the whole class, with its answer and why; if the class gets it right, saber for everybody.
  Exchange: each team gives breath to another team. The card of yesterday is not repeated.
- The pilot mission brings 18 quick questions, three per chapter (version 3 of the mission, updated by itself).

## 0.4.5 (2026-10-11)

- Tone of skin with an art pack: until the student picks one, each character shows as it is painted (the cards of the
  roles keep their variety); once picked, the pack's image in that tone (light, medium or dark), in every stage that
  has it. Before, every character counted as light-skinned by default.

## 0.4.4 (2026-10-11)

- The look of a character only offers what shows. With an art pack, hair and clothes are painted in, so the step of
  the look and the button to change it appear only when the pack has an image for each tone of skin (then with three
  tones); without a pack, the client draws everything, as before.
- The character before it is created is as big as the screen allows, with its name and the buttons still in view.

## 0.4.3 (2026-10-11)

- Choosing a character: in the families of people (story heroes, epic legends, without magic) every role comes as a
  boy and as a girl, eight cards with their own picture and name (Mago, Maga), instead of four cards with both names
  and a step after them to choose. A girl can now be a druid with her own picture: roles with the same name for both
  (Druida) skipped that step. Animals with two names (Oso, Osa) still choose after the role.

## 0.4.2 (2026-10-11)

- Lists show the round portrait of each character instead of a tiny full figure: the team in the game, and the
  class and the challenges to check in the teacher panel. The photo of the student goes at the foot of the
  portrait, only when the student has one: the empty picture of Moodle is no longer shown.

## 0.4.1 (2026-10-10)

- Art packs: the images of characters are looked up by the family of names of the course too (a druid is not a
  dolphin), then by role, lineage, form of the name and stage, each first in the tone of skin the student chose
  (keys such as `character.storyheroes.healer.f.1.t2`), so that a first pack can start with a few images and grow.

## 0.4.0 (2026-10-10)

- **Teams**: the teacher makes them in one go (the size they choose, the roles dealt out so that every team gets
  each one, in a new grouping), or uses the groups of the course. Each week a team fills a bar of knowledge
  together; when it is full, everybody in it gets gems. The ranking of the teams is shown to the students only if
  the teacher wants.
- **Powers**: real privileges of the classroom that cost breath, by role (shield, extra time, wall; breather,
  revive, cheer; hint, notes; lookout, exploration, shortcut) and for everybody (choose your seat, music, one more
  day). A student asks, choosing the teammate when the power is for one; the teacher approves with a tap, or the
  power approves itself if the teacher says so; saying no gives the breath back. Some act in the game: the shield
  takes half of a teammate's loss of energy, the wall keeps the team from losing energy for the day, the breather
  gives energy, revive brings a worn-out teammate back and gives knowledge to the team, cheer gives breath to the
  team. Teachers turn powers on and off, change their cost and level, and make their own privileges.
- **Moodle activities in the game**: any activity can be a challenge, from its own settings (section Tesapé):
  knowledge, gems, steps, the chapter of the mission and the item it gives, once, when it is completed. The pilot
  mission brings its activities (two forums, two assignments and the database of the field guide) and makes them in
  a section per chapter when it is installed, with completion set; they appear in the places of the map and open
  inside the game, without the frame of Moodle around them.
- **Defensa del nido**, the minigame of the forest: animals come down towards the nest of the creature and are caught
  with the net of their group. Its levels follow the steps (vertebrates and invertebrates; the five groups; the
  groups of invertebrates too), with the local examples of the mission. A mode without hurry, one animal at a time;
  pause; keyboard, mouse or touch. The server counts every play again; winning gives the net for the scenes and a
  coat for the creature (fur, feathers or scales), once. The teacher sees who played and won, and the mix-ups.
- **The box of ideas**: students send their teachers ideas for the game or the class, with or without their name.
  Only the teachers of the course read them; nothing leaves Moodle.
- **Art packs**: the art of the game can come as a pack of images (characters, creature, icons, maps), installed
  with `cli/art.php` and served by Moodle itself. Without a pack, the client draws its own placeholder art.
- The night by the lake has a new choice for those who carry the net. The pilot mission is now version 2, and
  courses that have the mission get it by themselves.
- `cli/demo.php` makes the class with the activities of the mission and, with `--weeks`, powers waiting for the
  teacher and ideas in the box.
- Main buttons keep their dark text on every screen.

## 0.3.0 (2026-10-10)

- **Missions** come as packages: a folder in `missions/` with the mission (chapters, texts per story, local examples,
  objectives, pages of the book, items) and its scenes. The pilot mission, *Living things* (ages 9 to 11, texts in
  Spanish), comes with the plugin, with its nine core scenes. Teachers install it in their course choosing the story
  (the valley, or Azara's expedition) and the local examples (Spain or Paraguay), and can change both later without
  losing what the students did. Each chapter brings a classroom challenge.
- **The map of the mission** in the game: six places with their lanterns, the path walked, where the student is, and
  for each place its story, its scenes and its classroom challenge.
- **Decision scenes** played in the game: story, choices of one or several options with their consequences shown one
  by one, things to put in their group, steps to put in order, paths that depend on what was done, items and roles
  of the team that open options, an extra question to win. The server plays every scene again with what the student
  did and decides the path, the points, the result per objective and the ending: a play that does not fit the scene
  is refused. Every play is kept for the teacher.
- **Rewards once per scene**: the first good ending gives its knowledge, gems, items and pages of the book (with the
  weekly cap, and what the cap leaves out counts as given). The first scene hatches the **companion creature**, unique
  to each student, which grows later. Finishing the scenes of a place lights its lantern for the student and gives
  its reward (the garden gives the lantern for the night by the lake); when half of the class has a lantern, it
  lights for the whole class.
- **The book** of the mission in the diary, with the pages found, and the student's items; the creature on the
  character sheet.
- **How the class is doing**, in the teacher panel: per scene, students who played and finished it, plays, the
  result per objective at the first try, and the mix-ups that came up most.
- `cli/demo.php` installs the pilot mission in the made-up class (`--mission=` for none).

## 0.2.1 (2026-10-10)

- On narrow phones (360 px), the lists of the teacher panel no longer stick out on the right.

## 0.2.0 (2026-10-10)

The game can be tried: students make their character and enter their region; teachers run the game from their
phone.

- **The game engine**: knowledge with the weekly cap of the course and the daily cap for what is given by hand;
  gems; energy, with its daily cap of losses and falling worn out at 0 (without losing knowledge or level) until the
  recovery challenge or a rescue; breath for the powers; expedition items without duplicates; pausing a student;
  undoing any entry of the log (it adds the opposite entry and keeps the original, marked).
- **Classroom challenges**: challenges that happen off the screen (a drawing, a model, a talk, a reading), with no
  Moodle activity behind them. The teacher says done, done with a mention (extra gems, never extra knowledge) or not
  yet, for a student or a whole team, with a note; changing their word gives or takes back what the challenge gives.
- **From Moodle to the game**: a completed activity gives the knowledge of its challenge, or the course rule for
  activities that are not challenges (10 unless the teacher changes it); once per activity, and taken back if the
  completion is. A deleted activity stops being a challenge.
- Energy and breath are filled once a day for those who are playing.
- The look of the character is kept as data (parts and colours), ready for the character workshop.
- **The game client**, full screen and without the look of Moodle: the first time is a story (the valley, the role
  in the family of names of the course, the form of the name, the lineage, the look) that ends with the new
  character; then the world with five places: the region, the team (with the energy of the team only), the classroom
  challenges with what the teacher said, the diary of what was won and the character sheet (printable, to change the
  look). What was won since the last visit shows when coming back.
- **The teacher panel**, made for the phone first: turning the game on with the family of names; the class with
  names, photos and characters, by team; giving knowledge, gems or breath to a few or many with one tap, or taking
  energy for a commitment not kept; classroom challenges, and the word on them student by student or for the whole
  team, which waits and is sent later if there is no connection; the sheet of each student with their step, pause and
  recovery; the log with undo; and the settings of the course.
- Light for poor connections: the client and the panel weigh under 200 KB with their fonts, and use no external
  service. Fonts Lexend and Cherry Swash included (SIL Open Font License), declared in `thirdpartylibs.xml`.
- A link to the game in the course bar: the panel for teachers, the game for students once it is on.
- **Web services** for the game client and the teacher panel: the world of a student (character, team, classroom
  challenges, own log), making the character and changing its look; for teachers, the class, the settings of the
  course, giving by hand, the word on classroom challenges, making and deleting them, pausing, recovery, steps, the
  log of the course and undo. Classmates see each other's character and energy inside the team, never why someone
  fell or was paused.

## 0.1.0 (2026-10-10)

First alpha: the ground the game is built on. Nothing is shown to students or teachers yet.

- **Tables** of the game: characters (one per student for the whole site), regions (the game in a course), members,
  teams, challenges, the log of everything that adds and takes away, powers and their requests, items and
  inventories, missions, chapters, scenes and their attempts, and minigame results.
- **Capabilities**: play; set the game up in a course; give by hand; approve powers; pause; see and undo the log;
  run the class screen.
- **Privacy provider**: the character in the student's user context, what they did in each course in the course;
  export and deletion of all of it. Teachers stay in the students' records without their name when their data is
  deleted.
- **Levels**: the knowledge each level asks for (the first ones come soon), the five stages of the character's look
  and the weekly cap per course.
- **A made-up class** for development sites, `cli/demo.php`: a course, a teacher, students with invented names,
  teams with the four roles, steps and, if asked, some weeks of progress within the weekly cap.
