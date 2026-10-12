# Changelog

## 0.28.0 (2026-10-12)

- **The gradebook, if the teacher wants it** (off by default, in the settings of the panel): the rating the teacher
  gives in the «Competencies» tab goes to the gradebook of Moodle, one item for each objective, with the scale of its
  competency, in a category of the mission («Tesapé · Los seres vivos»). Turned on, the ratings given before go too;
  turned off, the items stay as they are. Only what the teacher rates: never the behaviour or the resources of the game.
  The option goes in the copies of the course.

## 0.27.0 (2026-10-12)

- **The badge of the mission**: each mission can bring a badge of Moodle (the pilot, «Naturalista del Valle de
  Tesapé», mission version 10). Tesapé makes it in the course with the mission, with its medallion, **turned off**: the
  teacher turns it on if they want it. Then the game gives it to whoever lights the lanterns of every place (the last
  one, in the final battle), and the teacher can give it by hand too. It is found by its name, so a copy of the course
  keeps it. Nothing happens if the site does not use badges in courses.
- The settings of the panel say whether the badge is on, with a link to manage it.

## 0.26.11 (2026-10-12)

- The font rules of the cover follow the style checks of Moodle (one value per line in long lists).

## 0.26.10 (2026-10-12)

- The title of the cover of the course is written in the title font of the game (Cherry Swash, its brand), served by
  the plugin itself, whatever the theme of the site: with a theme of its own fonts, it took them. The font moves to
  `fonts/`, where Moodle serves the fonts of a plugin to its styles; the game takes it from there too.

## 0.26.9 (2026-10-12)

- Accepting the policy of the AI for the plan checks on the server that the teacher may accept it
  (`moodle/ai:acceptpolicy`), not only in the panel (seen by the agent of campus.oksigenia.com).

## 0.26.8 (2026-10-12)

- The plan for the next class: whoever cannot use the AI of the site, such as a public account of a demo site that may
  not accept its policy, still sees the last plan of the class, with a note and without the buttons to accept the policy
  or to ask for another (they gave an error).

## 0.26.7 (2026-10-12)

- The test of joining a class compares the ids of the teams as numbers.

## 0.26.6 (2026-10-12)

- `cli/demo.php --join=USERNAME --course=ID`: brings an account already enrolled in the course of a made-up class into
  it, as one more student, with a character, a place in the smallest team (with the role it lacks most) and some weeks
  of made-up progress with scenes played. For the public accounts of a demo site; it does nothing twice.

## 0.26.5 (2026-10-12)

- **The emblem of Tesapé**, drawn by the art director: in the header of the teacher's panel and of the class screen,
  as the icon of the pages of the game in the browser (favicon, and the icon of a phone's home screen), and as the
  icon of the plugin.

## 0.26.4 (2026-10-12)

- The market on a phone: with the picture of each thing, its button goes to its own line when the name would be
  squeezed, instead of covering the text.

## 0.26.3 (2026-10-12)

- **Pictures for the items, the market and the album**, from the art pack: each item of the mission (`item.<code>`,
  such as `item.lantern`) in the list of items, each thing of the market (`market.<code>`, such as
  `market.paint-gold`), and the picture of its group on each card of the album (`album.<group>`: the name of the group
  in lower case and without accents, such as `album.mamifero`). Without them, everything looks as before.

## 0.26.2 (2026-10-12)

- The moments of a scene without a picture of their own (its endings, for instance) show the picture of the scene's
  first moment, not the background of its chapter: the end of the crossing in the barge stays on the lake.

## 0.26.1 (2026-10-12)

- The paintings of the scenes are shown at 16:9 (1440 × 810 in the pack), never taller than about half the screen,
  so that the text stays in sight on the class screen.
- The opening of the mission, before the student begins it, shows the painting of the valley (`scene.prologue`) if the
  art pack has it.

## 0.26.0 (2026-10-12)

- **Paintings in the scenes**: the art pack can bring a background for each place (`scene.<place>`, such as
  `scene.lago`) and, if a moment needs it, its own picture (`scene.<image>`, such as `scene.lago-red`). The game looks
  for the image of the node, then the place its name starts with, then the place of the scene's chapter; each one first
  in the skin of the mission (`scene.lago.azara`). Without them, it draws its own picture as before.
- **Who speaks, with their portrait**: `speaker.<code>` (round, 256 × 256), or `speaker.<code>.<skin>` when that
  speaker is someone else in another skin (the elder druid is the naturalist in Azara's). The Embrollo uses its battle
  picture if it has no portrait.
- The opening of the mission and the card of the region can show a painting of the valley (`scene.prologue`,
  `scene.region`; one serves both).

## 0.25.2 (2026-10-12)

- The settings page of the plugin says whether the site uses the competencies of Moodle, and where to turn them on if
  it does not (found on campus.oksigenia.com, where they were off). The game works the same without them; if they are
  turned on later, the framework of the mission is made the first time a teacher opens the «Competencies» tab or a
  student plays a scene.
- In Spanish, the page of the site where competencies and hidden activities are turned on goes by its name in Moodle
  («Opciones avanzadas»).

## 0.25.1 (2026-10-12)

- Making the framework of a mission could stop with an error on a site where the libraries of grades were not loaded
  yet (found on dev.redescuela.com): the scale of the framework now loads them. A scale left by a first try that
  stopped half way is used again instead of making a second one.
- The competencies never stop the game: if the evidence of a play cannot be left, the play counts and the trouble is
  told to the developers.

## 0.25.0 (2026-10-12)

- **Competencies of Moodle**: each mission brings its own framework of Tesapé, installed in the site with the mission
  («Tesapé · Los seres vivos»: four general competencies and one concrete competency for each objective, with what is
  seen at each level of a four-level scale: Starting · On the way · Achieves it · Masters it, achieving it being
  proficient). Each objective of the mission is linked to its competency in the course, or to any other the teacher
  chooses, such as those of the school's own framework added to the course. Each scene a student plays leaves evidence
  in the competencies of its objectives («Scene «The nest»: 4 of 5 right, try 1»). The game gives no marks: the teacher
  rates. Nothing happens if the site does not use competencies.
- **A tab of competencies in the panel**: the grid of the class, with the share right at the first try of each student
  in each objective as a clue, to rate each one with a tap (the rating goes to the competencies of the course in
  Moodle), and the competency of each objective.
- The pilot mission, version 9: a decision on what people do in an ecosystem in the route of first steps, the names of
  its objectives as in the framework, and the objectives each scene declares matching the ones it records.

## 0.24.1 (2026-10-12)

- The plan for the next class asks the AI of the site to keep the editorial line of the game: no political or
  institutional agendas, the environment as concrete ecosystems (never climate change or global warming), and people
  for what they did, never framed by their sex.
- The help of the report names no application: a spreadsheet, a gradebook or a PDF.

## 0.24.0 (2026-10-12)

- **The report of the class**, in the mission tab of the panel: one row for each student, to take to a spreadsheet, to
  iDoceo or to a PDF, in Excel, ODS, CSV or PDF. Their team and step, their level and knowledge in the course, the
  scenes and quests done, for each objective of the mission the share of answers right at the first try of each scene
  (empty while there is no evidence), the classroom challenges done, the Moodle activities completed, the pages of the
  day right and their entries in the field guide. The game gives no marks: this is evidence for the teacher's. Nothing
  about behaviour (energy, commitments, powers). The PDF is laid out to be read: landscape, short headings and a legend
  of the objectives.
- The demo class plays its scenes with the memory and the role of each character, so that no play is left out.

## 0.23.0 (2026-10-12)

- **The cover of the course page**: where the game is on, the course page opens with the way into the valley, as each
  teacher chooses in the settings of the panel (each mode with a small drawing): a **big door** with the map of the
  mission, the place the class is in, its lanterns and «Enter the valley»; a **card**, the same but smaller; **straight
  into the valley**, so students opening the course go into the game (and leave it to their dashboard); or **only in
  the menu**. Teachers see the cover with the panel and the class screen; where the game is not on yet, with the way
  to turn it on. A student whose creature is waiting for the page of the day is told so on the cover.
- **The activities of the mission, only inside the valley**: an option of the course that keeps them available but off
  the course page (the places of the mission become hidden sections, which teachers see), so students open them from
  the map of the game. It needs the site to allow activities available but not shown on the course page: the
  settings of the plugin ask the administration for it when Tesapé is installed or upgraded from the web (it is the
  setting of the whole site «allowstealth»; its default is what the site already has, so an install from the command
  line changes nothing), and the panel takes administrators there if it is off.
- **«Tesapé» in the top bar** of the site, for those who play or teach it somewhere: «your world», the courses with
  the game, each with its way in.

## 0.22.1 (2026-10-12)

- The character sheet of a student with nothing to change in their look at their stage stopped with an error, and its
  print button did nothing. Found testing 0.22.0 on campus.

## 0.22.0 (2026-10-12)

- **Quests**: the twelve scenes of extension (the Path of the Mountain) and expert (the Summit) of the pilot mission,
  two in each place, now version 8: under the lens, the court of life, cells, the tree of the kingdoms, the key of the
  schoolyard, family names, travelling seeds, our experiment, the web of the lake, the case of the lynx, whom to trust
  and the naturalist of then and of now. They are open to every student, whatever their step, apart from the scenes
  of their route: they give gems and a new page of the book each (twelve new pages), never saber, lanterns, items or
  the creature, and they do not count to close a chapter. Each has a partial ending that can be made better.

## 0.21.0 (2026-10-12)

- **Expeditions**: when a course starts another mission, the one before stays as an earlier expedition, with the
  lanterns the class lit. Students see the earlier expeditions under the map (the lanterns of the class, and their own
  lanterns and pages) and can visit each map as the class left it, to look, not to play. The class screen lists them
  under the map, and the teacher's panel lists them in the mission tab, with the missions the course has not made yet
  to start another expedition.
- The field of what a character wears from the market can be empty: the definition of 0.20.0 was not accepted by
  Moodle on a new install.

## 0.20.0 (2026-10-12)

- **The market of the valley**, where gems go: three paints for the creature (gold, night and snow, 10 gems) and two
  auras for the hero (fireflies and forest, 15), which classmates see in the team and on the class screen; and the
  lantern of the team's banner (20), the first thing the team pays for together: each member puts in what they want,
  and the lantern shows next to the team's name once it is paid. Bought things can be taken off and worn again for
  nothing. Nothing is bought with money and nothing changes how the game plays. Resetting a course takes the banners
  of its teams, not what each student bought for their character.
- **The album of the field guide**: each entry of the field guide of the class made by the student or their team is a
  card, golden if it says what place the species takes in a food chain. It reads the database of the mission (entries
  waiting for approval do not count yet).

## 0.19.0 (2026-10-12)

- **Today in the valley**, on the class screen and at the top of the teacher's panel: the chapter the class is in and
  what the mission proposes for each of its sessions (what to do, materials, minutes), with a button to play its scene
  on the class screen or to get the final battle ready, the challenge away from the screen and, on the class screen,
  what confuses the class most. In the panel, what is waiting for the teacher (powers, commitments, ideas), one tap
  away.
- **The letters of the Embrollo**: when the class lights the lantern of a place, the Embrollo leaves a letter there.
  It shows in that place of the map of each student and on the class screen (the last one).
- **The class screen asks less of the server**: every 5 seconds it only checks whether a battle or a vote has started
  somewhere else; the rest is reloaded every 30 seconds (it was everything every 5 seconds).

## 0.18.0 (2026-10-12)

- **The page of the day**: each day the creature asks for a page, a quick question from the chapters the student has
  played (those answered wrong come back first). Answered right, the creature eats it and gives 1 breath, never saber;
  answered wrong, it shows why and asks again tomorrow. One a day. Three days in a row right and the creature glows.
- **Names**: the student can rename their creature from a list made for it (the name it was born with comes first),
  and, where the course allows hero names, choose one from a list of their own («Lince de Plata»); a hero name is
  never repeated in a class. Where the course allows own names, the student can write one for each: it waits in the
  teacher's panel («Names to approve»), and nobody else sees it until a teacher approves it. The hero name setting of
  the panel now works.

## 0.17.0 (2026-10-11)

- **Team commitments**: in each chapter, each member of a team chooses one of the commitments the mission brings
  («bring a leaf, a seed or a stone for the inventory»), and can change it until the teacher marks it. The teacher
  marks them kept or not kept in the panel (tab of classroom challenges, with a count of those pending): one not kept
  costs whoever chose it 2 energy (the Wall, the Shield and the day of Calm still stop it); a team that keeps all of
  its own gets 1 breath for each member, once. Taking a mark back undoes what it did. Energy is lost for what one
  chose, never for behaviour. The chapter the class is in is the first one whose lantern the class has not lit.
- **The Spanish texts, revised**: «tu docente» always in the singular, no «vosotros» on the class screen or in the
  events, the same name for the same thing everywhere («poder», not «privilegio»; «Pelaje» for the coat of the
  creature, not the hair of the hero), verb tenses aligned, and clearer help for teachers. The English uses
  «knowledge» everywhere. A locked option that needs a role in the team now reads «Your team needs a Sage».

## 0.16.1 (2026-10-11)

The pilot mission, version 7, rewritten after the design review:

- **Fifteen scenes with a voice that works in Spain and in Paraguay** (no «vosotros»), hints in every decision, an
  option for each role in the core scenes and the scout's own trail in four of them.
- **Partial endings** in the nest, the barge, the cellar and the night by the lake: they count, give less, and the
  scene says it can be made better by playing it again (the map shows «Done, it can be better» until then). Playing it
  again to the good ending gives what was missing.
- **Memory between scenes**: the feather in the nest, the creature born asleep, the good question to the owl and the
  mosquitoes of the lake change later scenes.
- **The arc of the ink tracks**: Doña Hebra, the librarian, confesses she copied pages without checking; the Embrollo
  leaves a letter for each chapter; the last scene is now «The rehearsal» (10 of saber) and the final battle on the
  class screen gives what the end saves (30 of saber, the naturalist's lens, the lantern of the tower, the creature
  grows), with the ending of the mission on the class screen. The lantern of the tower is lit by that battle.
- Three new pages of the Big Book, for the scout's trail; commitments and sessions for each chapter in the mission data
  (used by the next versions).

## 0.16.0 (2026-10-11)

The battle against the Embrollo, after the design review:

- **Battle tokens of the roles**, one each battle, with no approval and announced on the class screen: the glance of
  the sage strikes out a wrong option for the whole team, the trail of the scout tells the team in which place of the
  mission the answer is, the shield of the protector stops the Embrollo's counterattack, and the cheer of the healer
  makes each right answer of the team take double life. The glance as a power, which needed the teacher, is gone.
- **The battle can be lost**: six answers of ten must be right. When most of the class gets a question wrong, the
  Embrollo pulls itself together (a tenth of its life back), and below half its life it tangles itself more: ten
  seconds less for each question. Losing still takes nothing from anybody.
- **One Embrollo, the final battle**: the teacher can mark the battle as the final one of the mission. Won, it gives
  each player, once, what the mission saves for the end (30 of saber, the naturalist's lens, the creature grows) and
  lights the lantern of the tower for the whole class; the class screen reads the ending of the mission.
- «Your team helped most and gets…» in Spanish, without «os».

## 0.15.0 (2026-10-11)

The scene engine for the decisions of the design review (the scenes that use it come with the next version of the
mission):

- **Memory between scenes**: what a character does in one scene can leave a flag that changes another (a branch on it,
  an option that only appears with it).
- **Options of a role**: besides those a teammate's role opens, an option only the own role sees: the trail of the
  scout.
- **Hints in the decisions**: one breath each; the sage gets the first free and pays half for the rest; free on the
  class screen. The breath is paid when the scene ends.
- **Passives of the roles**: the protector's shield stops the points of the first mistake of a play (the teacher still
  sees the mistake), and the healer, finishing a scene well, gives one breath to the teammate who has least.
- **The voice of the place**: texts can have a version for each place («valley.py», with «ustedes»).
- What the mission gives is marked in the ledger, so that the weekly cap neither cuts it nor counts it.

## 0.14.0 (2026-10-11)

First part of the decisions of the design review:

- **The hero changes in the first mission**: the stages of the look start at levels 1, 5, 12, 25 and 40 (they came
  every ten levels, and the pilot mission ended in the first).
- **The weekly cap belongs to the character, in the whole site**: 150 by default, a setting of the plugin. What the
  mission gives (its scenes, its battle, and the activities and classroom challenges of its chapters) goes under no cap:
  it is measured by design. The cap of each course is now an option, off unless the teacher sets it.
- **The goal of the week of a team** is reached when three of every four members (two at least) reach their part (40
  of saber each): someone ill or without a device at home no longer sinks the team.
- **The energy of the teammates**: everybody sees the total of the team; only the healer and the protector, who can
  help, see who is low.
- **The cheap powers (1 or 2 of breath) go through by themselves**; the teacher can take any approved power back from
  the panel, and what it gave is undone and the breath comes back.
- **Breath comes back +2 a day**, up to 5: saving it is worth something.
- **The ranking of the teams is hidden** unless the teacher shows it.

## 0.13.3 (2026-10-11)

- When the AI provider of the site cannot be reached, the teacher sees «The AI of the site could not make the plan»
  instead of the technical error of the connection (which stays in the debugging information).

## 0.13.2 (2026-10-11)

- The Embrollo of the card of the battle on the class screen had no size of its own and filled the whole card.

## 0.13.1 (2026-10-11)

- **The demo class has played**: with weeks of progress, each made-up student plays the scenes of their step in the
  chapters they have reached, at the first try and with the mix-ups the scenes foresee (the mushroom with the plants),
  put right on the way. A demo now shows results by scene and objective, what confuses the class, lanterns of the
  class lit, creatures born, and the plan for the next class has something to go on.
- The tests play every scene of the mission, the new ones too, both well and with mix-ups.

## 0.13.0 (2026-10-11)

- **The time zone of the game, for each course**: the day and the week of the game (caps, the event of the day,
  energy and breath filled again, the weekly goal of the teams) start at midnight in the time zone the teacher
  chooses in the settings, so a school in Asunción on a site in Madrid no longer changes day at seven in the
  evening. By default, the one of the site, as before; it goes with the backup of the course.

## 0.12.0 (2026-10-11)

- **First steps have their own scenes**: the living things, version 6, brings the six scenes of the first step, one
  per chapter, with short texts and examples of each place: who is hungry like the creature (it hatches there for
  those on first steps), animal or plant, the lost coats of the animals, the plant upside down, who eats whom, and
  the card of the field guide. Each one gives what the core scene of its chapter gives (the creature, the lantern, the
  pages of the Great Book), so nobody misses anything for being on another step.
- The Embrollo, the final scene, is for every step.

## 0.11.0 (2026-10-11)

- **The game goes with the backup of a course**: its settings, the mission (installed again from the package of the
  plugin, without making its activities twice), the classroom challenges, the challenge of each activity (tied to the
  activity the restore makes) and the powers of the course. Nothing of the students goes: their characters belong to
  the whole site. Duplicating a course for next year, or moving it to another Moodle, keeps the game set up.
- **A course reset for next year** (one that takes the students out) clears what was played in the course and turns
  the lanterns of the class off again; the game stays set up as it was. A reset that takes nobody out leaves the game
  alone.

## 0.10.0 (2026-10-11)

- **The teacher makes the characters of a class without devices**: in the sheet of a student who has none, the
  teacher picks the role, the form of the names and the lineage (the role suggested is the one the class has least
  of). The student gets everything the class plays from then on, and if they come in one day, they find their
  character ready and can change how it looks.
- **The living things, version 5**: the wrong answers of the final scene and of three quick questions are as long and
  as reasoned as the right one, so the longest is no longer the right one; the owl scene no longer says what the trail
  is before the questions; and the bacteria no longer send to a minigame that does not exist.

## 0.9.1 (2026-10-11)

Fixes from a review of the code:

- **Class screen**: a second tap on Next, Show the answer or Finish, or a second class screen, no longer acts twice
  (Finish gave the rewards of a battle twice; Next skipped a question). The actions of the class screen run one at a
  time for each course, and Next and Show the answer only act on the round the class screen is showing, so the answer
  shown by itself when the time runs out no longer closes the next question. The buttons wait for the answer of the
  server, and so do the buttons that give saber and gems on the class screen and in the teacher panel.
- **Scenes**: a group or a step that is not the right one goes off after one tap, and the server no longer turns down a
  whole scene because a child tapped the same wrong group several times. If the server does not take what was played,
  the scene can be started again instead of sending the same thing again.
- A scene played by the whole class with phones closes its vote only once the scene is played, so it can be tried
  again if something fails.
- When a user account is deleted, their character and everything of theirs in the game go too, and their name goes
  from what they did as a teacher.
- The powers of a course and the update of a mission to a newer version are made once, even when the whole class opens
  the game at the same time.
- Battles and votes only when the game is on in the course; a battle stops with a message if its mission no longer has
  a question; the footprint is not drawn when the mission has no questions; players without a team do not count as the
  team that helped most.
- Undoing saber no longer leaves renown below zero, undoing a recovery leaves the student worn out again, and the
  recovery is saved in one step.
- A grouping of another course is not taken for the teams.
- The teacher panel: the offline queue of validations no longer gets stuck on one the server turns down; the results
  of the mission are read again on each visit; students and challenges in the lists open with the keyboard.
- The phone: the battle layer no longer draws everything again every second and a half during a question with a time
  limit, and the world shows the new saber and gems after a battle won.
- The privacy export tells coming into a battle, answering and glancing apart.

## 0.9.0 (2026-10-11)

- **A plan for the next class, with the AI of the site**: in the mission tab of the teacher panel, one button turns
  what confuses the class into a short plan: what to review first and how to do it in a few minutes, a new example from
  the students' everyday life, a question to open the class and the classroom challenge that fits. It uses the AI
  subsystem of Moodle with whatever provider the site has (from Moodle 4.5); without AI on the site, nothing shows.
  Only counts and the content of the mission are sent, never a name; the teacher accepts the AI policy of the site
  first. The answer is checked and asked for again once if it does not have the right shape.
- The question to open the class shows on the class screen for a few days, and the teacher can hide it.
- The mix-ups in the results of the teacher panel show their question, and their texts follow the story chosen for the
  mission (they always used the one of the valley).

## 0.8.0 (2026-10-11)

- **Glance**, a new power of the sage (the wizard, the owl…): once approved by the teacher, the phone shows the next
  question of the battle before anybody else, between one question and the next, so that the player can think about it
  and help the team. Each glance is used once.
- **An object for the team that helped most** in a battle won, when the mission has one: in the living things, the
  untangled thread (the Embrollo, undone). Missions without an object keep the extra gems. The mission goes to its
  version 4 and updates itself in the courses that play it.
- **Diplomas** in the printable material of the class screen: one page for each student with their portrait, name, role
  and level, the journey of the mission, the course, the date and a line for the signature.
- When a course is deleted, everything of the game in it goes too: the region, the teams, the mission and its scenes,
  the ledger, the powers and their requests, the events, the battles. The characters stay, with what they earned: they
  belong to the students.

## 0.7.0 (2026-10-11)

- **Scenes played by the whole class** on the class screen: the teacher picks a scene of the core step and plays it on
  the board, big enough for the projector. The class decides at each step, with phones (a vote: the board shows how
  many voted and, once closed, the votes of each option) or with the A, B, C, D cards or a show of hands; the teacher
  taps the option the class chose, and does the steps of sorting and ordering with what the class says aloud. Each
  option shows its letter for the cards.
- The class has the items any of its players carries and their roles. At the end the server plays the scene again with
  the decisions of the class, and everybody who played (those who came in with the phone, or the whole class without
  devices) gets what the scene gives that they did not have yet. It is not kept as anybody's own try, so that the
  results of each student in the teacher panel stay theirs.
- On the phone, the same layer as the battles shows the vote: the decision with big buttons and, once closed, the
  votes of the class.

## 0.6.1 (2026-10-11)

- **Printable material** from the class screen, for classes without devices, as A4 pages straight from the browser:
  vote cards A, B, C and D for each team (each letter with its own colour and shape, so that they work in black and
  white and for those who do not tell colours apart), a card for each team with the portraits of its members, and the
  characters of the whole class, six per page.

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
