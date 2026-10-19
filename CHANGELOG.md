# Changelog

## 0.143.0 (2026-10-19)

- **What the hero wears.** The items of the mission that can be worn go in the hand or on the shoulder, one in each
  place, from the bag of the tablet, where each says what it does so (mission v58: `slot` and `worn` of its items).
  The lantern in the hand lights the bush where the being of the walked encounter hides; the magnifier in the hand
  gives a look more in each minigame; the net on the shoulder catches an animal that slips in the Defence of the nest.
  What an item opens in the scenes it still opens from the bag. Nothing is bought: everything is won (`equipment`,
  service `equip`, field `slot` of the inventory).

## 0.142.0 (2026-10-19)

- **The bestiary of the Embrollo and the spaced practice of the page of the day.** The page the creature asks for
  each day comes, after the questions answered wrong, from the minions of the Embrollo the student has not beaten yet,
  and says which minion brings it. Answered right, its minion is untangled: in the album of the tablet, the bestiary
  shows the minions of the places the student has played, those untangled with the idea that undoes them (`daily`).

## 0.141.0 (2026-10-19)

- **The verbs played on the painting of the place alive.** With the place alive behind a scene, a find, a path and a
  tend are played on the painting of the place itself, which is seen whole above what is read while the hero and the
  creature step aside: what can be touched is on the painting (buttons with their names, as before), and the world
  answers each touch, with sparks where something is found or the drop goes on, and dust where not; what is tended
  changes there, from one state to the next, and grows when it is right. If the painting does not come, or «See as a
  list», they go back to the page with what was done.

## 0.140.0 (2026-10-19)

- **The story of a mission tells some texts of the game in its own way** (`texts` of the mission, by skin, with keys of
  the language pack of the client), when the game speaks the language of the mission. Mission «Los seres vivos» v57: in
  the expedition of Azara, the battle is «The notes before the ship» (the ship to Spain sails at dawn and the Embrollo
  has mixed the notes of the notebook), the candles of the tower are the lanterns of the pier, and the pages, the
  camp and the stall are those of the notebook and the expedition. The class screen paints the battle by turns with the
  place of the battle of the story (`scene.battle.<skin>`) if the art pack has it, or with the tower.

## 0.139.0 (2026-10-19)

- **The free expedition.** The scenes of the past expeditions of a course, which could only be visited, are played
  again for pleasure: alone, from the visit of that expedition, with its memory (what the student did there changes
  them as it did). They are played as quests: they may give gems and pages, and they count as played in their own
  expedition, never knowledge, lanterns, items or anything of the creature, which belong to the expedition of now. The
  visit says so. Class scenes and team sessions still play only the expedition of now (`scenes::free`).
- **The cosmetics of the market on the figures of the game layer.** The paint of the creature colours its puppet or its
  picture in the walked encounter, the scenes and the nest, as the tablet paints it; the aura of the hero goes around
  them: fireflies, soft lights that come and go, or leaves that fall. Without movement, a still light of its colour.

## 0.138.0 (2026-10-19)

- **The museum of the class in the Library.** The entries of the field guide of the whole class (approved, where the
  guide asks for approval) fill the shelves: a shelf for each group of living things of the guide, a book for each
  entry, golden at the foot if it says its place in a food chain; a shelf with three books or more has a light of its
  own. A touch on a book opens its entry under its shelf (how it looks, where it lives, its place in the chain), with no
  name of who made it. On the tablet, the Library is a place of the map (in «Me» without a map), with the sound of the
  Library; on the class screen, the shelves with books, and the book open stays open when the screen is drawn again
  (`museum`).

## 0.137.0 (2026-10-19)

- **The banner of the team** grows with the weeks the team reaches its goal: a plain cloth, then a border (1 week),
  the emblem of the light (2), ribbons (4) and the star on top of the pole (6). It grows as a team and never shrinks.
  With the border, the reserve of the team holds 2 more breaths (12); with the emblem, the first hint of each scene the
  team plays together is free. The class screen shows it beside the name of each team; the tab Team of the tablet,
  with its weeks, what it needs to grow and what it gives; and when it has grown since the tablet last saw it, a moment
  shows it, from how it was to how it is (`banners`).
- The price of a hint on the tablet counts the hint the messengers of another team brought that day, as the server
  already did.

## 0.136.0 (2026-10-19)

- **The trade, a card of the day.** With two teams or more, each team may lend another one of the items its members
  carry until the day ends: on the class screen, it chooses the item and then the team. The team that receives it
  carries it in the scenes of the day (the options that ask for it open), and the team that lends it does not; in the
  backpack of the tablet, «Lent to…» or «Lent by…» says so. Once per team; tomorrow everything is back.

## 0.135.0 (2026-10-19)

- **The rite of ascent.** Once in a mission, when the student has lit the six lanterns or their class has won the final
  battle, the hero may stay in their role or try another one, keeping their level and the talents of each role, without
  the time a change of role waits otherwise. It comes by itself once on each device (the four roles in the hero's stage
  and lineage, theirs marked as theirs; a touch marks one, «Keep…» keeps it) and waits on the sheet while it is open.
  Out of the rite, the teacher changes roles as always (`ascents`, service `ascend`).

## 0.134.0 (2026-10-19)

- Mission «Los seres vivos» v56. «La clave del patio» asks for five questions: the five that look at what does not
  change put each of the six beings in its own box, and with four two of them always ended up together, whichever
  question was missing. The complete key is tried with a being that was not on the table, a bat that falls with the
  cat: the question to add is about what it carries on its body («Does it have wings?»), not about what it does. In the
  chain race of the lake, the scout and the protector have a task in the version without a playground too.

## 0.133.0 (2026-10-19)

- **The Embrollo moves by pieces** on the class screen when the art pack brings its puppet (`rig.boss.embrollo` and
  one for each of its states, with the skeleton `rig.boss`): it takes the blow when a team undoes its knot, throws
  itself at the team it attacks, laughs bouncing when nobody undoes it, gets dizzy as it frays, and from its second
  state on it stays on its pile of threads.

## 0.132.0 (2026-10-19)

- The battle with movement on the class screen follows the board when it moves without being drawn again (another
  width, full screen, a bubble of the Embrollo that takes two lines): the heroes of each team and the Embrollo are put
  in their places at every frame, not only when the board is drawn.

## 0.131.0 (2026-10-19)

- **A scene played by the class on its screen has the place alive behind it**, as the scenes of each student: the
  painting of each moment at full screen and who speaks in full figure (a puppet, if the art pack brings it), with the
  words in the big box of the class screen. Without a hero of its own: the hero is the whole class.

## 0.130.0 (2026-10-19)

- **The battle on the class screen, with movement.** Where the screen draws with WebGL and moves, a clear canvas over
  the board draws the Embrollo in its place, floating and fraying as it loses threads, and the heroes of each team in
  the place of their team. Each turn is seen: the right answers pull threads off it, the knot it throws flies to the team
  it goes for (or to all), a shield stops it, a broken knot bursts, the team it goes for is marked, and at the end it is
  shut in its jar or runs away. The board, its questions and its buttons are the same as before (`app/battleworld.js`).
- **Who speaks in a scene moves by pieces** when the art pack brings their puppet (`rig.figure.<code>`, with the body
  of a speaker or of a bird): they greet when they come, talk while their words are read, nod at what is chosen, show
  surprise and laugh at a good ending.
- **The places inside, walked**: the front layer of each place (`scene.<place>.front`, the hero passes behind it), and
  the traces painted on the ground until they are found (`stage.trace.<n>`); footprints never for a being without legs.

## 0.129.0 (2026-10-19)

- **The puppets take the tone of skin of the student.** A puppet is cut from the picture in the tone it is painted in
  (`meta.rig.skin.own`) and brings the other tones it can take: each piece with skin has a frame
  `<piece>.skin.<tone>`, drawn over it with the same movement. With a tone the puppet does not bring, the picture in
  that tone is shown, as before.

## 0.128.0 (2026-10-19)

- **Defensa del nido, drawn.** Where the device draws with WebGL and moves, the field of the game is drawn by the
  engine: the forest behind, the nest with the creature of the student in it (a puppet, when the art pack brings it),
  and the animals coming down with their own drawing, swaying a little as they come. The net flies from the nest: the
  right one wraps the animal and takes it away, and the creature jumps for joy; a wrong one, or none, and the animal
  slips in, the creature gets a fright and the nest shakes. The name of each animal, the nets and the warmth of the
  nest stay in the page, with the keyboard and the reader as before; with time or without hurry, and the server counts
  the game the same (`app/nestworld.js`).
- An animal is drawn by the name the student sees (`being.<name without accents or spaces>`, as the beings of the
  encounters): the same animal has the same drawing in every game, and each regional variant its own. The nest can be
  painted (`stage.nest`, and `stage.nest.front` for its front edge).
- With the place alive behind a scene, the creature sniffs when its nose strikes out an option, and the hero points
  when a help is opened. The world behind a page draws at most 30 frames a second.

## 0.127.0 (2026-10-19)

- **The voices of the told moments.** Each piece of a told moment plays its recorded voice if the art pack brings it,
  under `voice.line.<key>`, the key being the FNV-1a hash of the piece's text (`T.lineKey`): a text that changes (a
  regional variant, the name of the student's creature) has no voice, never a wrong one. A new moment, or reading aloud,
  stops the voice; who has reading aloud in their adaptations hears that, not the recording.
- With the place alive behind a scene, the paintings that are played on (to find something, a path, the state of what
  is tended) stay in the page; only those that are just a painting go behind.

## 0.126.0 (2026-10-18)

- **The place alive behind the scenes.** Where the device draws with WebGL and moves, a scene is played in its place:
  the painting of each moment at full screen, the hero and the creature of the student standing in it (as puppets when
  the art pack brings them), and who speaks standing in front of them when the pack has their figure
  (`figure.<speaker>`), moving while their words are read. The words go in a box at the bottom; the other moments, in a
  panel over the place. The world answers what is chosen (a light, a shadow, a shake, something that grows) and the
  ending (joy, a warm moment, or the creature that comforts). What is read and decided is the same page as before:
  «See as a list» takes the place alive away, here and in the next moments on that device (`app/sceneworld.js`).
- The hero and the creature of the moments that are played are shared by the encounter and the scenes (`app/cast.js`);
  the engine can draw a world behind a page (`T.stage.backdrop`).
- **A talent or the branch of the creature is kept with a second touch**: a touch marks a card (another one changes
  it) and «Keep…» keeps it, as their text asks to think it over.
- In the encounter as a list, a creature without a name yet is «Your creature» in what it says.

## 0.125.0 (2026-10-18)

- **Puppets in the walked encounter.** The hero and the creature move by pieces when the art pack brings the puppet cut
  from their picture: they walk, breathe, sniff at the traces, point at the hideout, jump in surprise and celebrate. Arms
  and tools take the same posture in every figure, a figure with its tool in the left hand points with its free hand,
  and a shield goes strapped to the forearm. Without a puppet for that picture (another stage or tone of skin), its
  picture, as before (`app/puppet.js`).
- **Voices and the music of the moment.** The hidden being says each clue aloud and the creature speaks (what it says
  is in the box too, in its own words); the music of exploring and of following the traces plays where music may play
  now, with a short piece when the being comes out; the music and the sound of the place step back while someone
  speaks. Only the voices of the being of the day are fetched. As a list, each clue has a button to hear it (nothing
  plays by itself there, so as not to talk over a screen reader).
- **Each place hides and sounds its own way**: books in the Library, ferns in the forest, reeds at the lake and stones
  in the Tower (`stages.<place>.hide`), steps on wood where the floor is wood (`ground`), and the sound of each place
  itself (`sound.ambient.<place>`) on the map, in scenes and in the encounter.
- **Art packs** may bring voices (`voice.*`, as sounds) and puppets (`rig.*` and `anim.*`, JSON read and written again,
  up to 256 KB each).
- The choice of who it is opens at the top, with the hero and the hideout in view.
- Mission «Los seres vivos» v55: the hideout and the floor of each place.

## 0.124.0 (2026-10-18)

- **The encounter of the day, walked.** Where the device draws with WebGL, the encounter of a place opens the place at
  full screen: the hero walks it (touching the ground or with the arrows), the creature runs ahead to each trace and
  sniffs, a hidden voice says the clue, the bush rustles, and next to it the student says who it is. A wrong name is
  crossed out and the being flees to the other bush; the creature finds another clue if there is one left. Found, it
  comes out to meet the creature, with its card. What is read and touched goes in the page over the world; «See as a
  list» plays the same encounter as before, and the device remembers it; who has «no movement» plays it as a list. The
  six places of the mission can be walked (`stages` in the mission: ground, start, traces and hideouts).
- **The stage of the moments that are played with movement** (`app/stage.js`): the engine (Phaser 4.2.1, MIT, in
  `app/engine/`, declared in `thirdpartylibs.xml`) is loaded only when one of these moments opens, and goes with it.
- **The encounter is never lost.** A wrong name is crossed out and the being stays to be found the same day, until only
  its own name is left. With one clue and no miss, a golden card and twice the gems; with no miss, its gems; after a
  miss, no gems. With «Fresh track», the first miss keeps the gems.
- Mission «Los seres vivos» v54: the six places to walk.

## 0.123.0 (2026-10-18)

- **Two printables more, for a class without devices.** On the class screen: a hero sheet of one page for each
  student with a character (portrait, name, role and what it does, level and team, their creature, boxes for their
  energy and their breath, lines for their talents and for what their team carries), and the cards of the knots of the
  Embrollo, four to a page (what it claims, what undoes it and why), for the battle with cards and for the wall.

## 0.122.0 (2026-10-18)

- **The friends of the expedition.** The characters of a mission (`friends`) remember the pages of the Great Book a
  student gave back from what is theirs. With so many of their pages, they tell the student something new and give
  them some gems, once. In the book tab of the tablet, each friend with their pages, what they told and what is still
  to come; the tab glows while someone has something to tell.
- Mission «Los seres vivos» v53: the druid, the librarian, the owl, the gardener and the heron, with two talks each
  (at two and at four of their pages), in both skins.

## 0.121.0 (2026-10-18)

- **What was not known when deciding.** A decision can tell, after its consequences, what was missing when it was
  taken (`reveal_after`, with its picture and who speaks) and how each option picked looks now (`after`); then the
  scene goes on. It is told, it does not change the evidence. Its texts can be changed in the Workshop.
- In a scene played as a team, the hand passes on the decisions of the new verbs (`find`, `use`, `justify`) too.
- Mission «Los seres vivos» v52: in «El caso del lince», after choosing the measures, what was done for real.

## 0.120.0 (2026-10-18)

- **A card to fill in.** A decision of several can be told as a blank card (`layout: card`): as many gaps as pieces go
  in, the pieces below; each one is taken to the card by dragging it or with a touch (or Enter), and goes back with a
  touch. Full, it is handed in, and the world answers for each piece as in any decision of several. On the class
  screen it stays a list.
- Mission «Los seres vivos» v51: the card of «La ficha de la guía» is filled in like that.

## 0.119.0 (2026-10-18)

- **Three new verbs for scenes.** `find`: things to find on a picture, touched one by one; what is not one of them
  says why, once, and touching where there is nothing says so without counting. Each thing is also a button with its
  name. `use`: a decision of what to take out of the backpack, where only what is carried shows (and one option
  without anything, always). `justify`: after a decision, the reasons that support it are marked, those of the answer
  given, with that answer recalled above; they are evidence of the objective of method. The three are checked by the
  server, played by the autoplayer, sent in a team session like the other decisions, and their texts can be changed in
  the Workshop.
- Something touched in a find that is not one of the things counts in «what confuses».
- Mission «Los seres vivos» v50: in «Lo que forma el lago» three living things are looked for on the picture of the
  lake before writing it down; at the pond of «La noche junto al lago», what comes out of the backpack (the net, the
  magnifier, the lantern, or nothing); and in «El caso del lince», the two facts of the report that show what was said.

## 0.118.0 (2026-10-18)

- **The reserve of the team.** A reserve of breath for each team, apart from the breath of each one, up to 10. It fills
  when the whole team keeps its commitments of a place (+2, taken back if they stop being kept) and when the task of a
  place is validated for the team (+1), once each. It is spent together, in the «Team» tab: someone proposes and
  another member confirms it from their tablet, the same day. «A breather for the team» (3): one energy for each one;
  «Get up with the reserve» (4): only who is worn out asks it, and gets up without the healer.

## 0.117.0 (2026-10-18)

- **The rescue for two.** A healer who plays a scene as a team with a worn-out teammate pays no hints in it and, if it
  goes well, brings them back: the teammate gets up and the whole team gets the saber of a rescue, as with «Revive»,
  which stays for the teacher to approve. The healer sees it beside the worn-out teammate, in the «Team» tab and when
  choosing who plays; the worn-out student is told they can do their recovery challenge or play a scene with the healer.

## 0.116.0 (2026-10-18)

- **The council of the teams.** From the remote, in a session, «Council of the teams» shows on the class screen the
  place of today, two minutes and each team «in council». On the tablets, the «Team» tab has «Today's council»: the
  task of the place of today with its parts, who of the team carries each one (by role), and the role that starts the
  hand in the scenes of the team today; once chosen, the class screen shows it by the team. A session sheet can launch
  it as a step (`council`). The choice is of a role, not of a student, and only counts that day.

## 0.115.0 (2026-10-18)

- **Cards of the day for a team.** «A knot for a team»: the Embrollo tangles a sheet of mistakes of the place of today
  for one team alone; only its members see it on the map and can undo it, until the day ends, and undone it counts for
  the strength of the expedition. «Messengers»: one team takes a hint to another team of its choice, and today the first
  hint of each scene is free for that team. The team of the card is drawn at random, and the class screen can send it
  to another one before anything comes of it. Without teams (two, for the messengers) or without a hunt for mistakes in
  the place of today, they do not come out of the deck.
- A mission can give the cards of the day its own names and texts (`cards`).
- Mission «Los seres vivos» v49: «La primera luz», «Lluvia en el bosque» and the mysterious footprint, in both skins.

## 0.114.0 (2026-10-18)

- **The strength of the expedition.** On the class screen, next to the battle, a bar grows with what the class has
  done: the lanterns lit (most of it), the stains cleaned and the pages recovered, and the tasks of the places done by
  at least half of the class. In the final battle the Embrollo starts with up to 30 % fewer threads, according to it
  (not in a rehearsal).
- **Knots the Embrollo knows.** The knots of what the class still mixes up in the scenes (less than 60 % right, with
  four answers or more) are named under the bar, three at most, and the Embrollo keeps them for the last turns of the
  final battle, where the class screen says so. Breaking one in a practice battle takes it off the list until the class
  misses it again.

## 0.113.0 (2026-10-18)

- **A playable prologue.** Who comes in for the first time sees the vignettes of the mission before choosing who to
  be: a lantern of the valley goes out, the Great Book appears stained, the druid puts a seed in their hand. The role is
  chosen as the answer to her question («When something gets tangled, what do you do?»: «I stand in front», «I mend
  what is broken», «I look closely», «I follow the trail»), and the look, in the mirror of the river. A mission without
  a prologue keeps the screens as they were.
- A path takes the proportion of its picture, so that each place stays on what it marks.
- Mission «Los seres vivos» v48: the prologue; the whole plant for the drop's journey and the plants of the basement in
  their states, with the new art (mission art layer, version 2).

## 0.112.0 (2026-10-18)

- **Two new verbs for scenes.** `path`: someone (a drop, an animal) waits on a picture and the student touches the
  place it goes on to; the right one takes it there, a wrong one makes it slip back with what it says. It counts as an
  order. `tend`: the student gives some things to a being, «three days go by» and they see how it is (the first state
  whose condition holds); they may try another round changing something, or go on. Each round counts as an answer; the
  things marked right that were given count for the branches. Both are played by the autoplayer and checked by the
  server, as the other nodes.
- Mission «Los seres vivos» v46: «El viaje de la gota» is played guiding the drop along the plant, and in «El sótano de
  las linternas» the plants are tended and seen after three days.

## 0.111.0 (2026-10-18)

- **Information shared out** (`views` in a decision of a scene: what each role sees). In a team session each tablet
  shows only the piece of its role («Only you see this»), and the team has to say them aloud; the pieces of a role
  nobody in the session has go to the tablet that runs the scene. On one tablet, alone or as a team, all of them show
  together, each with its role. What the answers told so far («What we know») reaches every tablet of the session.
- **Team tasks with parts**: a classroom challenge can have up to four parts, each for a role. Each student sees theirs
  in «Challenges» and marks «My part is done»; the part of a role missing that day, anyone covers («I cover it»); only
  who marked a part takes it back. The teacher writes the parts in the challenge and sees, by team, how many are done
  when validating it. A challenge with parts is for the team.
- Mission «Los seres vivos» v45: the task of each place with a part for each role; what each role sees in «Las
  preguntas al búho», «La clave del patio» and «La barcaza de la expedición»; two facts of their consequences, made
  exact (not every reptile lays eggs; a kitten and a cat are both cats).

## 0.110.0 (2026-10-18)

- **Team sessions: a scene with the tablets of the team.** In a team scene, «With the tablets of the team» opens a
  waiting room; the others get a call on their tablet («Your team plays … on the tablet of Rocío») and join with a
  touch, no codes. «Start» begins the scene with those who are in. The scene runs on the tablet of who opened it; at
  each decision to choose, who has the hand chooses on their own tablet and the others see who decides («Rocío decides.
  Help them.»). The rest (sorting, ordering, several options at once) is done on the tablet that runs it. The play
  counts for those who were in, also for who follows the expedition from far: their own tablet says they are there.
- If the tablet of the hand goes quiet for twenty seconds, the hand passes to the next one who is in; who comes back
  goes on where the team is. A session without signs of its tablet for two minutes is gone; closing the scene closes it
  for everybody.
- **The class screen shows a firefly** by each team with a session open; the teacher can touch it to close it.

## 0.109.0 (2026-10-18)

- **The creature chooses its way** when it reaches its second stage: land (fur), air (feathers) or water (scales), each
  card with how it would look and why that coat is for that medium. Once; it wears the coat of its way from then on, and
  its next stages follow it. It can wait («Later»): the sheet keeps the button.
- **The creature knows the places its student lit**: in the battle by turns, in a lane to choose whose knot is of one of
  them, it strikes out a wrong option by itself on that student's phone (never leaving fewer than two to choose from).
- **In the minigames, from its second stage, the creature sniffs a case once a play**, with the same button as the look
  of the sage.

## 0.108.0 (2026-10-18)

- **Named skills**: on their sheet, each student sees «What you have learnt to do», a medal for each objective of the
  mission («Distinguish the living from the non-living», «Follow a food chain»…). It lights when the first tries of
  their scenes show they master it (three answers or more, and they came well) or when the teacher rates them
  proficient in its competency. No numbers; only the student and their teachers see it. When one lights, a moment
  tells them so on their tablet. The teacher sees the lit ones on the student's sheet, and can turn them off in the
  course (Settings, Advanced, «Named skills»).
- Mission «Los seres vivos» v44: the names of its skills.

## 0.107.0 (2026-10-18)

- **Talents at level 9**, for the minigames and the powers; with them, each role has its three pairs:
  - Protector: «Endurance» (two slips more in the minigames, not one) or «Shared time» (their Extra time is for the
    whole team).
  - Healer: «Second wind» (the first slip comes back after three right in a row, not five) or «Light cheer» (Cheer
    costs 1 breath less).
  - Sage: «Keen eye» (two looks a play, not one) or «Shared notes» (their Notes are for the whole team).
  - Scout: «Far sight» (they see the two things coming, not one; in the hunt for mistakes, the sentences of the next
    two) or «Light shortcut» (Shortcut costs 1 breath less).
- A power made for the whole team by a talent always waits for the teacher, even if it approves itself; the inbox
  says the team, and the breath the student paid.

## 0.106.0 (2026-10-18)

- **Talents at level 6**, for the battle by turns (the choice of level 3 and that of level 6 come one after the other
  for who passed both):
  - Protector: «Quick shield» (the battle shield rests one turn, not two) or «Candle guard» (once a battle, their
    shield saves the candle when the Embrollo goes for everybody).
  - Healer: «Spare candle» (once a battle, their cheer lights another candle even if the class already lit one; two at
    most in a battle) or «Strong cheer» (if the team they cheer undoes its lane, a thread more).
  - Sage: «Quick glance» (rests one turn) or «Knot reading» (their glance tells the team the weak point of the knot,
    whatever its lane).
  - Scout: «Quick trail» (rests one turn) or «Wide trail» (their trail also tells another team, the one they choose,
    where the answer is; what comes next, only their own).
- On a tablet, the buttons to put a verb on a team are only for the shield and the cheer (with a role missing in the
  team they showed for the glance and the trail too).
- In a lane of another engine, the trail shows where the answer is, as in one to choose.

## 0.105.0 (2026-10-18)

- **Talents**: at level 3 each student chooses one of two talents of their role, and keeps it. It can wait («Later»),
  and their teacher can let them choose again (the student's sheet, «Choose again»). Talents help to play, never to get
  it right: they do not touch the answers at the first try, the evidence, the saber of the scenes nor the class
  screen. They are kept by role: with another role, its own; back to the old one, the old ones.
  - Protector: «Long shield» (the shield stops two mistakes in each scene, not one) or «Team guard» (in team scenes,
    the first mistake of each one who decides).
  - Healer: «Double care» (2 breath, not 1, for whoever has least after a scene finished well) or «Light breather»
    (Breather costs 1 breath less, and to a teammate who lacks no energy it gives breath).
  - Sage: «Hints of the house» (the first two hints of each scene cost no breath) or «Wide glance» (in the battle by
    turns, the glance strikes out an option for another team too, the one they choose among those with a lane to
    choose; that team sees where it comes from).
  - Scout: «Fresh track» (missed, a being does not hide: its name is crossed out, one more clue, and they choose again;
    the card is not golden) or «Shared trail» (in team scenes, the options of the scout are there even if the tablet
    is not theirs, and the scout decides them).
- **A course can turn the talents off** (Settings, Advanced, «Talents»): there they neither act nor are chosen, and the
  sheet says so; the character keeps them for their other courses.
- The choice of talent opens by itself once on each device; left for later, it waits on the sheet, with a mark on «Me».
- The moments of coming in (a new level, a talent, the badge…) wait for the end of a battle on the tablet.

## 0.104.1 (2026-10-18)

- The README tells what the game does today; a Behat scenario goes through the workshop (a scene seen as a student,
  with its evidence, and taken out of the game and back).

## 0.104.0 (2026-10-18)

- **Lanes for a knot drafted by the AI of the site**, in the Workshop («Propose lanes with the AI»): from what the
  Embrollo claims and why it is wrong, three questions (the right answer first, the others its mistakes) and two
  boasts. Nothing is kept until the teacher takes it, one by one: a question taken joins the battle questions, in the
  place and objective of the knot, and the lanes of the knot; a boast becomes its laugh. Only with the AI of the site
  and the permission to draft with it.

## 0.103.0 (2026-10-18)

- **The Workshop shows a scene as a student would play it** («See it as a student», with what was changed there) and
  **what evidence it leaves** («See what evidence it leaves»: beside each option, its objective and whether it is
  right; beside each thing to put in a group, its objective and its group). Nothing is kept.
- **A scene can be taken out of the game** in the Workshop («In the game»): off, it is not on the map nor on the class
  screen, it cannot be played, and its chapter is done without it; what was already played stays. The last scene of
  a chapter that is not a quest always stays.
- **A place can be skipped** in the Workshop («Skip this place»): the class passes it by, its lantern is lit without
  playing it and the expedition goes on in the next place; the map says «The class passed it by». It can be undone,
  and the place of the final battle cannot be skipped.
- **The classroom challenge of a place** is chosen in the Workshop, among the classroom challenges of the course (or
  none): the one its students see on the map.
- **A mission of one's own gets «Decision with a consequence»**: two or three ways, none of them right, each telling
  what happens next; it is not marked and leaves no evidence. Before publishing, advice that does not stop it: a scene
  of only questions («Without the text, is it still a game?») and consequences that say little («a change of the world
  or just a colour?»).

## 0.102.0 (2026-10-18)

- **The report tells how the answers came**: beside the share of each objective, a column with right at the first try
  and alone · with help · after the consequence (and ✗ for the same mistake twice); in the PDF, under the share.
- **The PDF of the report tells the class**: after the table, what confuses the class most, and the knots it left
  hanging this week with why they are wrong.

## 0.101.0 (2026-10-18)

- **The lobby of a battle** says how many of the class have joined («18 of 24 have joined»: those with a character
  who are not resting), and in a battle by turns it has the music of the battle, if the music is on.

## 0.100.0 (2026-10-18)

- **Touches with a reason**: the bar of the selected students starts with six reasons («Helped their team · +10
  saber», «Checked before claiming», «Good question», «Explained their idea», «A mention · +1 gem», «Take a
  breath»), which each course changes or extends in the advanced settings; the bare amounts stay below, «No reason».
- The student sees the reason on their tablet the next time they come in, with three soft bells, and in their diary
  («Checked before claiming · From your teacher»). The class screen never shows it: what is individual is celebrated on
  the device of the student.
- `award` keeps a note; the log entries give it, and the settings of the course give its touches.
- **What the class chooses wrong on the class screen counts in what confuses it**, as nobody's try: with phones, how
  many voted each wrong option (the vote now carries its node and the ids of its options); without them, the choice of
  the class, once. It is kept without names, by scene.
- Deleting or resetting a course now also deletes its sessions, the states of its teams and what its class plays
  showed.
- **Evidence of gestures**: each answer in a scene says how it came (right alone at the first try, right with help —
  the lens, a hint or the creature —, right after seeing the consequence of a mistake, the same mistake again). The
  evidence left in the competencies of Moodle says it too.
- **The grid of the competencies** shows, beside the share right at the first try, how the answers came, and in grey
  the level the game suggests from them; «Confirm» (by student) and «Confirm the N suggestions» take them where the
  teacher has not rated yet. Nothing is rated by itself. `rate_competency` takes several ratings.

## 0.99.0 (2026-10-18)

- **The first week, step by step**: turning Tesapé on in a course opens five steps, each with the minutes it takes
  (the mission, with a warning when completion of activities is off; the names of the roles; how the class plays;
  the teams, when nearly everyone has a character; and a rehearsal before the first day). It can be left and taken
  up again from a card in the class tab, which then becomes the list of the first day. Courses that already play
  skip it.
- **Settings in two layers**: what a teacher touches any day comes first; grouping, time zone, teams, caps, saber per
  activity, hidden activities, theme, badge and gradebook are folded under «Advanced».

## 0.98.0 (2026-10-18)

- **Adaptations in one touch**: «Reading support» (read aloud, big text, more hints), «No hurry, no noise» (no hurry,
  no sounds, no movement) and «Sight» (big text, high contrast), in the sheet of a student and in the bar of those
  selected, so that a whole class is adapted without opening each sheet. What a profile marks is added to what each
  one had; `set_adapt` takes a profile and several students.
- The sheet of a student shows at once the adaptations just marked when it is opened again.

## 0.97.0 (2026-10-18)

- **One tray of what waits** at the top of the class tab of the panel: the own names of heroes and creatures, the
  powers asked (with who asks and for whom), the team plays of today, and links to the commitments to mark, the ideas
  not read and a classroom challenge validated by halves. Each one is answered there, and «Approve all» takes several
  names or powers at once.
- **The panel answers at once**: the answers of the tray change only the tray, and saber, gems, breath or energy given
  to students are drawn from the answer of the server (level, energy, breath and state of each one, now in what
  `award` returns), without reading the whole class again; only the card of the teams is read again, quietly.
- `decide_name` takes several names (one that a classmate already has stays waiting) and `decide_power` several
  requests (those already answered stay as they are).

## 0.96.0 (2026-10-18)

- **A battle rehearsal**: on the class screen, «Battle rehearsal» plays three test questions («What colour is the sky
  on a clear day?») to check the phones on the first day, without prizes, without spending questions of the mission,
  and without counting as a battle for who follows from far. Its rounds say «Rehearsal».
- **The battle with the settings of the last one**: the class screen remembers how many questions and how much time,
  so that launching it is one button.

## 0.95.0 (2026-10-18)

Block 4.2 of the plan.

- **«We need help!»**: in their Team tab, a team says how it is doing today: ready, working or asking for help. The
  class screen shows a lantern beside each team (green, amber, or the one asking for help, beating), without names nor
  why, within five seconds; the teacher sees it beside the name of each team in the panel. A team that has finished
  sees who asks for help and can go. Only today counts.

## 0.94.0 (2026-10-18)

- **The Workshop edits the sheets of the sessions**: the title, what to prepare, each step and what is read aloud, the
  key questions with what is expected, the mistakes to watch with how to turn them round, and what to do if time is
  left or short; the entry session with the first place. The guide of the session reads the changes of the course.

## 0.93.0 (2026-10-18)

- **The lantern lit with a rite.** During a session, when half of the class has the lantern of a place, it waits ready
  instead of lighting by itself: the guide of the teacher says so, and «Light the lantern» shows its rite on the class
  screen (the place, its lantern lighting up, the sound; then the map with its new road). Closing the session lights
  what waited ready, and nothing waits more than a day. Without a session, the lantern lights at once, as always.
- The layers the session shows on the class screen have their own colours (they were see-through: they hang outside
  the theme of the page); the commitments, as wide as the screen and under the name of the place they belong to.

## 0.92.0 (2026-10-18)

Phase 4 of the plan, block 4.1: the session.

- **The session is the unit for the teacher.** Each place of the mission has its sessions (two in the pilot mission, and
  an entry before the first place), with its sheet as data of the mission: steps with their minutes, what is read
  aloud and what each step launches, the three key questions with their answer and the two mistakes to watch with how
  to turn them round. The pilot mission, version 43, with its fourteen sheets.
- **The teacher runs it as a director**, from the panel or the phone: «Start the session»; the step of now, with the
  text to read and its buttons; on the class screen, «We enter the place» (the place full screen, its name and the
  letter of the Embrollo line by line), the card of the day, the scene with the class, the battle, a soft timer for
  team work, and «Choose your commitment» with how many have chosen in each team; «Take it off the class screen». A
  discreet clock of the session, only for the teacher.
- **Closing in three steps**: the class challenge and the commitments (by team), and what confused the class today,
  marked to review at the start of the next session. Without a session, everything goes on as before.

## 0.91.0 (2026-10-18)

Block 3.2 of the plan: the Embrollo steals a page.

- **A page of the Great Book stolen**: when the class left a knot hanging in the battles of the last week and that knot
  has its page, the action of the day is the Embrollo taking it, instead of a stain (one at a time, never the same knot
  twice in a week). It shows on the map of each student and under «Today in the valley». To bring it back, the Embrollo
  says again what it claims and asks one of the questions of the knot, another one each day: one try a day for each
  student; the first right answer brings it back to the whole class, with a gem, the page and the why. The teacher
  takes it as brought back by the class with a touch. Nobody loses their page: the theft is of the story, not of the
  album. The pilot mission, version 42, with the page of each knot.
- One gem says «1 gem», not «1 gems», wherever gems are won.

## 0.90.0 (2026-10-18)

Block 3.5 of the plan: the catalog of beings and things.

- **The catalog of the mission** (`things`): one card for each being and thing of the encounters, the defence of the
  nest and Who is alive? (68), alive or not, with its group and, when they are sure for every variant of its name, its
  traits (what covers it, legs, how it breathes, how it is born, what it eats, where it lives). One being, one id and
  one drawing in every game: the seahorse and the bird of the nest are the same as in the encounters and in Who is
  alive?
- **The things of Who is alive? with their drawings** when the art pack brings them (`being.<id>` alive, `thing.<id>`
  not), with their names; until then, the names. `T.thing` draws a being or a thing for any game.
- The pilot mission, version 41.

## 0.89.0 (2026-10-18)

Block 3.2 of the plan: the map changes with the decisions of the class.

- **The decisions of the class as stickers on the map of the class screen**: when at least half of the class takes the
  same way in a dilemma (the frog back to its pond, the crossing among the reeds, Doña Hebra up to the tower), a sticker
  with a few words shows by that place, with its drawing if the art pack brings it (`sticker.<mark>`).
- **Thirty-six beings in the encounters**, six in each place: twelve more, mostly of the groups that had few (spiders and
  their kin, amphibians, fish, reptiles, molluscs, worms); the toad of the garden and the scorpion of the tower only with
  the lantern. The decoy names of each group, checked: never another name for a being of the mission (out, the
  mushroom and the tortoise) nor of another group (out, the caterpillar and the centipede among the worms).
- The pilot mission, version 39, with the stickers of its eleven decisions and the new beings.

## 0.88.0 (2026-10-18)

Block 3.3 of the plan: the creature acts.

- **The creature takes each being in its own way**: in an encounter, recognised, it hops after an insect or a frog,
  steps back from a spider, sniffs a mammal nose to nose, keeps away from a mushroom (better just to look); if the being
  hides, it looks for where it went.
- **Its mood**: asleep on the map after a week away, until a touch wakes it up (never ill, never gone); happy, with a
  little hop, after three good endings in a row.
- **The teacher switches its sniffing per scene** in the workshop (on, off, or as it came), to see how the class does a
  scene without that help; it goes with the other changes of the course, and to another course with them.
- The sniffing line of the encounter, without a word that gives the creature a gender.
- The start of the minigames does not say the rule twice: the mission tells what to do, the line below only the limits
  (seen by Campus); and the nest runs out of warmth, not of shield.

## 0.87.0 (2026-10-18)

- **Each minigame opens with two lines of scene**: what the Embrollo did, in its own voice, and how the creature of
  the student takes it (by its name, once it has hatched); then what to do. In the expedition of Azara, a narrator
  tells the trouble. The pilot mission, version 37, with the lines of its thirteen minigames.

## 0.86.0 (2026-10-18)

- **The streak tightens the game**: a minigame starts gently and goes a notch faster every three right answers in a
  row, up to the speed of the step; two slips in a row, a notch back. The streak shows as sparks, never as a count.
- **A passive for each role in the minigames**, without asking and without a cost: the protector holds one slip more
  (in the nest, the goal comes down with it); the healer gets the first slip back after five right in a row; the sage
  has one look a game (the net that shines, a marked word, the circle of the card, two names out); the scout sees what
  is coming (the next animal, thing or card, the next clue, the trail of the next mistake). The server counts the
  protector's slip and the healer's the same way.
- **Sounds of their own**: the little laugh of the Embrollo at a slip, the rush and the bump of an animal that gets
  into the nest, a catch that sounds, and the creature that chirps when a streak starts. An art pack can bring its own
  (`sound.giggle`, `sound.near`, `sound.chirp`).

## 0.85.0 (2026-10-18)

- **The defence of the nest by traits at the first step**: the nets are what the body is covered with (fur, feathers,
  scales, wet skin) and those without bones; from the second step on, the groups. A game can map each group to its
  cover (`covers`). The pilot mission, version 36, with the nest that loses its warmth instead of its shield.
- **A common frame for the minigames** (`app/minigame.js`): the layer, the frame, the loading, the pause button, the
  saving (kept on the device without a connection, or sent again) and the end where the world answers, shared by the
  five; each one keeps only its mechanics. The defence of the nest now ends like the others, with its gems, its sound
  and its confetti, and with the creature in the middle.
- **Fixed**: in the defence of the nest without hurry, «Continue» did not answer a tap: pressed, the button moved half
  its width and the tap was lost. The minigames test plays the defence of the nest now too.

## 0.84.0 (2026-10-18)

Block 3.5 of the plan: minigames that are seen, not read.

- **No more «9 of 9»** at the end of the minigames: the world answers (what the mission says when it is won or lost),
  with what was mixed up and why; never a count of right answers.
- **The defence of the nest**: the nest of the creature has its warmth, a bar that each animal that gets in empties a
  little, instead of four shields; the animals fall with their own drawing when the art pack paints them (never the
  card of their group, which would give the net away).
- **Beings with their own picture only**: until the art pack paints each being, an encounter shows its shadow and the
  field guide its name, its group and its fact, not the card of its group (an ant with a snail and a ladybird, seen by
  Campus).

## 0.83.0 (2026-10-18)

Block 3.1 of the plan: something to find.

- **Encounters with beings**: in each place the class has opened, once a day for each student, a being of that place
  they do not have yet peeks out (chosen by the day, not by chance alone). Their creature sniffs it and says its group;
  it is recognised with three clues in the first person, opened one by one, choosing its name among four of the same
  group (the creature already said the group). With one clue, a golden card; with more, a card; not recognised, it
  hides until another day, without a cost. Gems, never saber. Some only peek out with the lantern.
- **The beings of the valley** in the field guide of the student (the album): place by place, the cards of those met,
  with their group and something curious, and the gaps of those still missing.
- The pilot mission, version 35: 24 beings, four a place, with their clues and facts, and three decoys of each group.
- **Fixed**: a moment over the game taller than the screen (an encounter, on a phone) scrolls, so that its last button
  is always reachable.

## 0.82.0 (2026-10-18)

- **Fixed**: the table of the stains (0.81.0, which was not installed anywhere) declared three text columns not null with
  an empty default, which Moodle does not take on a fresh install; now without a default.
- **The footprints of the Embrollo** on the map, on the way from the place of today to the next one: one each school day
  since the class lit its last lantern, up to five; they go when the class lights the lantern of the place of today
  (none with the Embrollo off).

## 0.81.0 (2026-10-18)

Block 3.2 of the plan: the Embrollo does something every day.

- **The stain of the day**: every school day (Monday to Friday, decided on the first visit of the day, without a
  scheduled task) the Embrollo stains the place of today with a sheet of the hunt for mistakes of that place. It is a
  blot of ink beside the lantern on the map of every student; whoever cleans it (touches the wrong words) gets a gem and
  the complaint of the Embrollo, and then sees each mistake with what it should say; it is clean for the whole class.
  Under «Today in the valley», on the class screen and in the panel, the teacher can take it as cleaned by the class
  (aloud), and nobody gets a gem for that.
- **The Embrollo in the valley**, a setting of the course: off; calm (the default: one stain at most, which never
  grows); naughty (one more each day it is not cleaned, up to three). It never takes anything from anybody. Who cleaned
  a stain is not kept: the gem is in the ledger.
- The pilot mission, version 34, with the complaints of the Embrollo in its two stories.

## 0.80.0 (2026-10-18)

Block 3.2 of the plan: the Embrollo does something.

- **The Embrollo of each place comes out** when the student lights the lantern of the place with its core scene: the
  screen trembles, it says its letter and its claim, and the student answers it, with the idea that undoes its knot or
  agreeing; it runs off leaving a blot of ink either way, laughing if they agreed. It counts for nothing: it is the
  villain in person, once a place. In the story of Azara, a note in tangled writing that flies out of the window.

What Campus saw trying «The night after»:

- The call to a battle says «Join the battle» (and to a vote, «Join the vote»), not the «Enter» of the scenes.
- At the end of a battle by turns, the tablet says «Turn 1 of 8», not «Question».
- The teacher panel tells, under «Today in the valley», that «The night after» is open until the rematch.

## 0.79.0 (2026-10-18)

What Campus saw trying the battle from the teacher's phone, and the version of 0.78.0, which went out without it (its
commit kept 0.77.0 in `version.php`, so sites would not have upgraded: it was not installed anywhere).

- From the phone, «First turn» waits until somebody is in, with tablets, as on the class screen.
- Lost, the Embrollo rolls away up the tower and stays there, small and faded, laughing from far: the class screen is
  not left with an empty place.

Block 3.3 of the plan: the creature acts and grows.

- **The creature sniffs**: in a scene, once a play and for free, the student can let their creature sniff a question of
  one answer with three options or more; it strikes out one that is clearly not it (the one the scene marks as
  `obvious`, or else the last that is not right). Never in a decision without a right answer, and not in a scene that
  says `sniff: false`. The server plays it again the same way, and the evidence of the competencies says «with the
  creature sniffing», so that the teacher does not read it as a right answer at the first try.
- **It grows in the middle of the mission**: once, with the third lantern of the student or the first minigame they win,
  whichever comes first; and again in the final battle. Up to five stages (a pack that paints four shows the fourth for
  the fifth). Whoever already had three lanterns of a mission sees it grow with the upgrade.
- **It reacts**: at the end of a scene it jumps with joy when it went well, and gets ink on its nose when it is to be
  tried again; on the map, when today's place is another one, it waits a little ahead on the way there.
- **The bubble of the hungry creature** on the map goes where it covers no place nor its name (with no room, a small
  round one beside the creature): a touch on a place always opens the place.

## 0.78.0 (2026-10-18)

The rest of block 2.2 of the plan: losing opens story.

- **«The night after»**, a scene of the pilot mission (version 33) in the clearing that is there only while the
  Embrollo is out after a battle lost, until the class wins the rematch: the druid tells something nobody knew about
  the Embrollo, and the class decides what to do that night (a decision without a right answer). The class screen
  offers it under «Today in the valley», beside the letter that boasts; each student finds it in the clearing, out of
  the fold. A scene says so with `after: lost`, and the mission names it in `battle.lost.scene`.

## 0.77.0 (2026-10-17)

The rest of block 2.3 of the plan: more ways to undo a knot, and the hero in the battle.

- **Lanes to put in order**: the steps, shown in an order that is not theirs, are touched one after the other (one
  player of the team does it, who changes from turn to turn, and the others help aloud); a step touched in the order
  goes back with those after it. The server says whether it undoes the lane (every step in its place; from five steps
  on, two next to each other swapped are forgiven). Once resolved, the tablets show the steps in their order.
- **The pilot mission, version 32**: lanes to put in order in the knots of the root (the way of the water through the
  plant), of the heron (the chain, from the light of the sun to the heron) and of the bubble (the steps of a
  naturalist); to sort who makes their food and who feeds on others, in the knot of the heron; and the mistakes of a
  sheet, in the knot of the root.
- **The figure of the student** at the resolution of a turn, on their tablet: it jumps if their team undid its lane,
  and staggers and stays on its feet if not (still, without movement).
- **Fixed**: the screens knew a battle was by turns only while a turn was played, so the lobby said «First question»
  and the end was told as in a battle of questions: no jar, no escape and none of their sounds (0.75.0 and 0.76.0),
  and, since 0.71.0, the escape with the page of the Great Book and the thread of the class at the end told as the
  team that helped most. The candles of the tower now go from the lobby to the end.
- **Covering a missing role** (from block 2.2): when nobody in a team who came into the battle has a role, anyone in
  the team can use its verb (the shield and the cheer, on a team), once a battle for the team, without saying why the
  role is missing.

## 0.76.0 (2026-10-17)

Block 2.6 of the plan: the weight of the art.

- **Art packs in layers**: a pack says its kind in pack.json, `world` (the default: its map in layers, sounds,
  creature, the Embrollo…), `style` (the heroes) or `mission` (its scenes, album, speakers and maps). The site has one
  world and any number of styles and missions; each key is taken from the mission, then the style, then the world.
  So no zip has to carry everything (each one under 20 MB), and a new mission brings only its own art.
  `cli/art.php` can now take a pack out (`--remove`) and list them (`--list`).
- **Lighter pages**: the art goes in each page with the address of each pack once and, for each image, only the rest
  of it (often just its extension), and the client puts the addresses together again: the list of the art of the valley
  goes from 144 KB to 38 KB before compression. Where Moodle does not use slashes in its file addresses, each image
  goes with its whole address, as before.

Block 2.7: the tests of the phase.

- A class of twenty-five in five teams plays a whole final battle by turns, with every kind of lane, and wins it; on
  every poll, the class screen and a tablet ask the database little, with the whole class in.
- In the browser (Behat), the teacher plays a battle by turns aloud on the class screen, pauses it and goes on.

## 0.75.0 (2026-10-17)

Block 2.4 of the plan: the class screen as the stage of the battle by turns.

- **The tower behind the battle**, and the Embrollo painted by the threads it has left (whole, frayed, down to its last
  thread), with what it means to do over its head while the turn is played (a team, everybody, pulling itself
  together, dazed).
- **The teams in a row with their heroes**, without names, the protector in front; the golden shield over the team it
  saved, and the threads that fly off the teams that undid their lane.
- **The end**: won, the Embrollo in its jar; lost, it rolls away up the tower. Only teams and the class on the class
  screen, never what one student did (the rule of the class screen, in the design).

Block 2.5 of the plan: the remote of the teacher, the music and the results.

- **The battle from the teacher's phone**: in the panel, «The battle, from here» launches it (by turns, with the
  devices of the course), starts the first turn, shows the answer, marks each team aloud and ends it, with the same
  buttons as the class screen at each moment, and how each team is to referee (who it goes for, tangled, undid their
  lane, the verbs used); the class screen follows. «First turn» instead of «First question».
- **A pause**, from the class screen or the phone: the time of the turn stands still and nobody answers or uses a verb
  until it goes on, with the seconds it had left; going on by hand also ends it.
- **The music of the battle** on the class screen: its two pieces (the second once the Embrollo is frayed) with a
  crossfade, and its sounds (the start, the shield, the counter, the moment it frays, the heartbeat of the last five
  seconds, the jar or the escape, and their sting).
- **Music on the devices**, a setting of the teacher: never, only in battles (by default) or always; the students turn
  it on with a button, and the piece is fetched only when it is going to play. The setting goes with the course
  backups, and so does how the class plays (with devices or without). The tablets no longer fetch the sounds of the
  stage of the battle (they are only heard on the class screen): about 250 KB less for each student.
- **The knots left hanging this week**, in the results of the teacher: the ideas of the Embrollo the class did not undo
  in the battles by turns of the last seven days, most first, with why each one is wrong, to go over them again.

## 0.74.0 (2026-10-17)

Block 2.3 of the plan: the lanes of the battle on the tablet.

- **Lanes to sort and with mistakes**, besides those to choose: a knot can give a team a lane where things go into
  their groups (sorted by one player of the team, who changes from turn to turn; the others see who and help aloud) or
  a sheet of the Embrollo where the wrong words are touched (each player; most of the team undoes it). What the tablet
  does goes to the server, which says whether it undoes the lane; once resolved, the tablet shows the solution (each
  thing in its group, each mistake with the right words and why). The words with mistakes look like the others until
  touched. A common way of drawing lanes on tablets, `exercises.js`.
- **The tablet does not draw everything again every second** while a turn runs: the seconds left count down on their
  own, so that a touch never lands on something being redrawn.
- **The battle by turns for everybody** (written first in the design, §9.1): who plays with «no hurry» and does not
  get to answer does not count against their team (and a team in which nobody had to answer is not in play that
  turn); the screen reader hears one sentence at each change of stage; with reading aloud, the knot and the lane are
  read when they come.
- On the map, the bubble of the hungry creature goes above the hero, clear of «Today» over the place (seen on campus);
  the silhouettes of the team go outside the place, so that its name is what it says (and they do not take the
  touch).

## 0.73.0 (2026-10-17)

Block 2.2 of the plan: losing has a rematch (mission v31).

- **The rematch goes on where it stopped**: by turns, when the Embrollo escapes (the tower in the dark, or the turns
  over with threads left), the course keeps how it was; the next battle (normal, or final after a final) starts with
  the threads it escaped with, without the knots the class broke and with those left hanging first. Winning clears it.
- **It boasts**: until the rematch, «Today in the valley» shows the letter the Embrollo writes after escaping, with a
  page of the Great Book under its arm; the class screen announces the rematch, and the end of a battle lost says so.
- Every turn says what happened, also when the Embrollo did not get what it meant to do.

Tested locally first, while the CI could not run (the minutes of GitHub Actions of the month were spent):
PHPUnit (323 tests), moodlecheck and phpcs clean; in the browser, accessibility of every screen, every scene of a
student to an ending, the minigames and a battle by turns with class screen and two tablets; the server walkthrough of
the mission with the final battle. This covers 0.70.0 to 0.73.0; the repository is public since then, and the CI
runs again.

## 0.72.0 (2026-10-17)

Block 2.2 of the plan, more of the battle by turns:

- **A broken knot does not come back**: if the knot planned for a turn was broken already, one left hanging comes
  instead; and in the last thread (a quarter of the threads or less), once, the knot the class could least undo in the
  battle.
- **The untangled thread is for the class**: by turns there is no team that helped most; if the class broke half the
  knots or more, everybody who played takes the object of the battle (or extra gems, if they have it).
- Who came in stays with the battle when it ends, so that the battles a student missed do not depend on answers that
  are cleaned up after some days.

## 0.71.0 (2026-10-17)

Block 2.2 of the plan: the verbs of the roles in the battle by turns.

- **A verb rests two turns** after it is used and comes back (a pair of the same role in a team shares the rest); the
  tablet says how many turns are left. Without turns, it is still once a battle.
- **The shield and the cheer go to a team**: a big button for each team (their own first). The shield stops the blow if
  the Embrollo goes for that team; the cheer untangles it and, once a battle, lights a candle again. The class screen
  says where each one went («Lynx raises a Shield over Heron»).
- **The trail of the scout sees what comes next**: the knot of the next turn and the team it will go for, besides
  where the answer of its lane is. The glance strikes out a wrong option in the lane of the team.

## 0.70.0 (2026-10-17)

Block 2.2 of the plan, its first part: the battle by turns as a turn machine.

- **A lane for each team**: each team plays its own lane of the knot on its tablets (one of the questions of the knot;
  two teams share one if there are more teams than lanes); the class screen shows the lane of each team and, once
  resolved, whether the team undid it.
- **Threads and candles**: each lane undone pulls a thread out of the Embrollo (two with the cheer of the healer); if
  every lane is undone, the knot breaks: three threads more, its whole why on the class screen, and the next turn the
  Embrollo is dazed (it does not attack, and what the class undoes counts double). The five candles of the tower are
  at stake: if they all go out, the Embrollo escapes.
- **What the Embrollo means to do**, announced: go for a team (if it does not undo its lane and no shield is on it, a
  candle goes out and the team is tangled, which shows on its card until it undoes a lane); while frayed, also go for
  everybody (a candle goes out if fewer than half undo theirs) or pull itself together (it gets threads back). A knot
  that does not break is left hanging.
- No more «vosotros» on the class screen: «¡A deshacer la lazada de…!», «A preparar los verbos».

## 0.69.0 (2026-10-17)

- **«Why?» in every option of the core scenes** (mission v30): what happens in the world when it is chosen (`say`),
  and why, folded; also in the choices of several options at once, which showed only the first.
- **Block 2.1 of the plan, the knot of the battle**: each knot says its chapter, its objective, the idea that undoes it
  and its whole why; it can bring lanes, one for each team, with their engine (choice, classify, errors or order; two
  teams share one if there are more teams, and they go round from turn to turn); the battle has candles, sizes and
  phases. A battle that is not the final one plays only the knots of the places the class has reached. The battle of
  today plays the lanes to choose; the others come with the tablet client.
- **The workshop**: the teacher can change the texts of the knots of the battle (with the texts of their place) and
  what the world says in each option.

## 0.68.0 (2026-10-17)

The end of block 1.11 of the plan (mission v29):

- **The questions to the owl, a deduction**: each answer is written down in «What we know» (`clues` of a scene and
  the effect `{clue}`), and the candidates that contradict it are crossed out by themselves (`contradicts`): who asked
  well arrives with one. The questions no longer score; the guess and why it is right (a new node) do. A wrong guess
  does not lead back to the same question: the owl falls asleep, «ask me better tomorrow», and the questions start
  again.
- **A dilemma in each place**, without a right answer and each with its mark and its line of memory: the frog by the
  nest, the order inside the shelves, the course of the barge, Doña Hebra in the cellar, the can on the shore and what
  is asked of Doña Hebra for tomorrow.

## 0.67.0 (2026-10-17)

Block 1.11 of the plan, content (mission v28):

- **The places remember**: twelve marks in the pilot mission (eight new: the seed in the nest, the bacteria under the
  lens, the salamander in the shade, milk for the plants, asking Doña Hebra, the dry high ground, the green branches,
  beside Doña Hebra); each place tells «What happened here» with those of the student.
- **The class screen plays with the marks of the class** (those at least half of it has), so that the memory of a
  scene also shows there; and **the end of the battle remembers** what the expedition did, in the voice of the Great
  Book.
- **Characters who want something**: Doña Hebra counts pages and does not look, dodges the question about the
  handwriting and confesses differently if she was asked (the first dilemma: what the class decided shows in the
  results); the owl takes longer each time and in the tower does not answer; the druid gives the questions back.
- **The skin of Azara, without names of the valley** in the core scenes.
- **No «Play» buttons**: «Go in», «Go in again» and «Try it again».

## 0.66.0 (2026-10-17)

Block 1.6 of the plan: the place and the map.

- **A place is a place**: its panel opens with its painting, and what there is to do in it as spots to touch (the
  scenes, the minigames and the quests) where the mission puts them on the painting (`spots`, mission v26: the nest in
  the twigs of the clearing, the owl in the tree of the forest), each with its lantern once done; what comes next
  glows, with the hero and the creature beside it. The list stays under it, for the keyboard and the screen reader.
- **The map with lanterns, not rings**: the lanterns painted for the art pack, off or lit by the student, lighting in
  front of them when they come back; and fog over the places the class has not opened yet.
- **The hero walks to the place touched**, with the creature, and stays there; on a phone the map comes close to the
  place and the place comes up as a sheet over it (it closes with its grip, its cross or Escape).
- **The team on the map, without names**: small silhouettes beside the lantern of the place where each one last
  played, and «3 of your team here» in the place.
- **The market and the album are places of the map**, two landmarks in its corner (painted by Gaudí, art pack v20)
  with the way back to the map; «Me» keeps the sheet and two rows of big buttons (look, how you see it, names, its
  coat, ideas).

From block 1.11 (content): **«Next time…»** under the letter of the Embrollo of each place, on the class screen and
in the panel of the place: what waits in the next place, in both skins (mission v27); the one of the tower announces
the final battle. **The places remember**: a mission can say, for each place, a line of what happened there for each
mark the student left in it (`memory`), and the panel shows it under «What happened here». **Dilemmas**
(`dilemma: true` on a choice without right answers): a decision of the story that is not evidence of any objective;
the teacher sees in the results what the class decided, not how many got it right.

And the end of block 1.10: on the class screen, the painted lanterns too, and **the creatures of the class dozing by
the lanterns it lit**, without names, breathing slowly.

Block 1.7 of the plan, its two «if there is time»:

- **A smaller header**: the level ring (a button to «Me»), saber, gems and the creature; energy and breath, where they
  are used (breath in the button of the notebook, «2 left», and in the powers; energy in the team). The counters are
  as tall as a finger, and each one says what it is next to it the first time it goes up, once on each device, and
  whenever it is touched.
- **Straight into the valley**: the first mission installed in a course takes the students straight into the game
  from the course page (the cover «Straight into the valley»), unless the teacher had chosen another cover; the
  teacher sees the course with the cover and can change it in the settings.

## 0.65.0 (2026-10-17)

Block 1.10 of the plan: the sheet of a hero.

- **Titles of the story as the hero grows**: Apprentice of the valley (levels 1 to 4), Walker (5), Tracker (12),
  Naturalist (25) and Guardian of the lanterns (40), on the sheet and in the team; and under the bar of the level, what
  comes next («At level 12: you will be Tracker», or the next power).
- **What the role does, seen**: two traits and a sentence on the sheet («Shield 1 · Guard. As a knight, you stop the
  first mistake of each scene: it costs you nothing»).
- **The creature goes with the hero**: beside them on the map, walking with them, and next to each one in the team,
  with its name.

## 0.64.0 (2026-10-17)

- **The core scenes stop sounding like a test** (mission v25): their options are what the group does or says aloud
  («Uncover the window so that the sun comes in», «—It is a snail's»), and the world answers what happened without
  judging, with the why whole.
- **An option of its own for each role in every core scene**: the protector protects, the healer cares,
  the sage remembers and the scout follows a trail; another way to get there, with a gem, never a shortcut. The nest
  begins with one of these decisions, which counts for nothing («Before filling the nest, what do we do?»).
- **Each role sees only its own option**: the options of other roles no longer show locked to everybody (at the class
  screen, those of the roles there are in the class).
- **The Embrollo talks in the battle**: thirty lines in the mission (at the start, when it loses a thread, when it
  pulls itself together, when it is down to half, and at the end, winning or escaping), shown in a bubble over the
  tangle on the class screen.

## 0.63.0 (2026-10-17)

Block 1.9 of the plan: a vertical slice of the battle by turns, «the knots of the tower», over the battle there was.

- **A battle started on the class screen goes by turns**, with the knots of the mission (mission v23 brings eight, each
  with the minion that carries it, what the Embrollo claims and its laugh). Each turn: the Embrollo lets a knot go and
  says which team it goes for (5 s); the roles get ready and use their verbs, in big on their tablets (15 s); the knot
  falls and the class undoes its lazada (the time of the question); and what happened is shown (10 s) before the next
  turn starts by itself. The times are the server's; without a time per question, the teacher leads.
- **The attack falls on the team it goes for**: if that team does not undo the lazada, the Embrollo laughs and pulls
  itself together, unless a protector of that team raised the shield. The class screen trembles when the Embrollo is
  hit, and it laughs when the attack falls; the team it goes for is marked, on the board and on its tablets («It goes
  for your team!»).
- Battles without knots, and those already open, go on as before.

## 0.62.0 (2026-10-17)

Block 1.8 of the plan: three cheap pieces for the teacher.

- **«Today we play»**: one setting for the course, with tablets or without devices, which sets how the scene at the
  class screen and the battle are played (the votes and the battle on the phones, or cards held up and the battle
  aloud). It is in the header of the class screen and in the settings of the panel; the cards of the scene and of the
  battle no longer ask for a mode, and «Play on the class screen» from Today uses it. A teacher who cannot change the
  course changes it for that screen only.
- **«How Tesapé works, in two minutes»**: the six ideas of the first day, in a moment of the panel that opens by itself
  the first time and with the «?» of its header after. Energy and breath in the class list only for who asks to see
  them.
- **Commitments in a batch**: «All kept» for each team, and then who did not is unmarked; `decide_pact` takes a list.

## 0.61.0 (2026-10-17)

Block 1.7 of the plan: the start, the next step and the words of the game.

- **«Your next step»**: one card over the map, one big button, for the one thing to do now: the scene of the student's
  step not done in the place of today, the page the creature asks for, a minigame or a quest of that place, or else
  «Look at the board». The panel of the place opens on the place of today (the same source as «Today in the valley»),
  only what comes next has the main button, and «Challenges» glows only for what the place of today asks for.
- **The creature brings the page of the day**: a bubble «I am hungry!» over the hero on the map, and a moment with its
  face and the question; it munches the page when it is right. The page is no longer the first card of the world.
- **A shorter start**: no prologue screen and no «ready» screen; the cards of the roles without descriptions (one line
  on the card touched); the lineage and the look on one screen, with the button always at hand; and «Ready» goes
  straight into the first scene of the mission, with the map after it (who arrives with the expedition on its way goes
  through the welcome instead). Three touches to the first decision. The teacher guide says it so.
- **The words of the game, not of a platform**: «Go» instead of «Open», a lit lantern instead of «Done», «When your
  teacher says» instead of «To do», «Course tasks» instead of «In Moodle», «Explore», «Go in again», «Book» and «Your
  bag»; no minutes and no «Stage n» for the student; the small labels over titles in lower case; icons of our own for
  sound, voice and close; reading aloud always at hand in a scene; the countdowns of the battle are not read aloud
  every second.

## 0.60.0 (2026-10-17)

Block 1.5 of the plan: what is won is felt.

- **What is won flies to its counter**: back on the map, the saber and the gems won leave the middle of the screen and
  fly to their counters in the header, which beat and count up (also after a minigame, a battle or what the teacher
  gave while the student was away). No more toasts of «+20 Saber».
- **Ceremonies on the tablet of the student**: a new level has its moment, as the badge has (the ring of before, a
  flash of light, the new one; and with a new stage, the hero of before and the one of now), and so has the creature
  when it grows. The creature is born from its seed, touched three times; what it does when it comes out (`born`) is
  read then. Mission v22 tells the birth in two moments.
- **The rule of the chest**, written in the design: what a reward holds is known before, nothing depends on chance,
  and the animation celebrates what was won instead of hiding it.
- **Juice**: a scene or a minigame opens as the light of a lantern from where it was touched; options, groups and
  things sink a little and ripple when touched; a place touched on the map answers and its panel comes in; the
  lanterns lit since the map was last seen light in front of the student; what waits in «Challenges» glows instead of
  a number in brackets. All of it stays still with «no movement» or when the system asks for less motion.
- The layers of the minigames are opaque from the first frame (the map no longer shows through) and show the lamp
  while they load. A retry ending no longer buzzes as a referee would.

## 0.59.0 (2026-10-17)

Block 1.4 of the plan: the role and the team in the scenes.

- **The role at hand in every decision**: a medallion at the bottom of the scene, always in view, with the verb of the
  role. The protector's «Shield ready» (or «used», once it stopped a mistake), the sage's «Notebook» (opens the
  notebook, free the first time as before), the healer's «Cheer» (says what their role does when the scene ends well) and the
  scout's «Trail»: what only the scout sees is not shown until they follow the trail, and then it appears, found.
- **The hand that goes round** in a play as a team on one device: each decision is taken by one of the team in turn,
  starting with who opened it, with their portrait and name; a decision with an option of a role goes to who plays
  that role. The banner says it: one device that passes from hand to hand. Each decision keeps who took it (the server
  keeps it only if they were in the play), and the teacher sees on the card of each student, in the team plays made
  for them, how many of the decisions were theirs.
- **The aids of the first steps** (block 1.3): who is on the first step and plays a scene not written for their step
  (one for everybody, a quest, or a core one where a mission has no first-steps scene) plays it with aids, and is not
  told it is another route: the lens is free, a note of the field notebook under the question says what each group
  has (`aid`), and the hard things of a sorting stay out of the table (`hard`). The server checks the play the same way.
  Mission v21 brings the aids of eight sortings.
- **Looking with a tool** (block 1.3): an option or a thing to sort can bring what is seen of it (`look`), with the
  lens, the net or the lantern. The tool shows next to it if the student carries it (the lens, always); looking costs
  what a hint costs, and what is seen stays as a note of the field notebook. Looking before deciding, and then deciding
  well at the first try, is evidence of the objective of method of the mission (`method`, SV-07 in the pilot). Mission
  v21 brings the first observations, in five scenes. The hints of a decision are now «Open the notebook» (what the
  expedition wrote down to make one think), so that looking is always looking at something.
- **Coming back is not repeating** (block 1.3): a retry ending can leave a note in pencil (`keep`) with what was
  learned, over the question it sends back to; and an option that led back to its own question is gone when the
  question comes back (the owl, the food chain, the rehearsal with the Embrollo). Mission v21 brings the notes of the
  fifteen retry endings.
- What a role or the shield says in a scene was shown under the scene, where nobody saw it: now it shows over
  everything.
- With «more help», the lens was free but its button said it cost 1 breath.

## 0.58.1 (2026-10-17)

- When several options are chosen at once, the world answers for all of them in one view, not one after another.

## 0.58.0 (2026-10-17)

Block 1.3 of the plan: the scenes stop looking like a test.

- **A told moment is a dialogue**, as in a role-playing game: the painting of the place behind, who speaks in big over
  it with their name, the hero and the creature of the student in a corner, and the words in a parchment that goes on
  with a touch, about 45 words at a time (all at once for who has «no movement» or reading aloud).
- **The world answers, not a referee**: no sound or colour of right and wrong after a choice; a moment of waiting, and
  then what happens, in the voice of the place, with its own sound. Options and items can now bring `say` (what the
  world says, with the explanation folded under «Why?»), `image` (the painting changes) and `fx` (light, dark, shake,
  grow): the engine is ready, the texts come with the content of the mission.
- **Sorting is decided all at once**: everything on the table, each thing taken to its place (touch a thing, then its
  place; touched again, it goes back), and then «Confirm»; the world answers for all of them together, and what was not
  in its place goes back to the table for a second trip. The server reads the placements item by item, in any order,
  so plays sent before still count. The first try is what counts, as before.
- **Hints become looking with the lens**: the same cost and the same advantage of the sage, with the lens as icon.
- Two animations shared the name «shake» and the one of the nest moved the things of a scene half their width.

## 0.57.0 (2026-10-17)

- **Suggestions to Oksigenia** (block C0.6 of the plan), off by default: when the administration turns them on
  (Site administration › Plugins › Local plugins › Tesapé), teachers and administrators can send ideas and problems
  about Tesapé to its makers from the box of ideas of the teacher panel. It goes from the server of the site, with the
  text, whether it is a teacher or an administrator, the versions, the language and, only if they write it, an email
  to answer them; three an hour for each person. Declared in the privacy provider. Students never send anything out.
- **The focus goes back to what opened a scene or a minigame** when it closes, as a dialog does (the page under it is
  often drawn again: then to the button of the same scene, or else to the content). Campus saw it.

## 0.56.1 (2026-10-17)

- **A scene or a minigame open is a dialog for the keyboard** (block 1.2 of the plan): Tab goes round inside it, never
  to the page under it, and Escape closes it as its ✕ does (not while a play is being saved).

## 0.56.0 (2026-10-17)

Fixes from a review of the code, the data and the texts (mission version 20):

- **Rules the server now closes**: only the scenes of the mission in course are played (those of past expeditions are
  read); a play starts at a retry node only after that retry; the page of the day keeps the question it showed, even if
  a new chapter is played before answering; who left the course no longer counts for lanterns, footprints, the final
  lantern, results or badges.
- **Races**: two requests creating the player in a course at once; a double tap (or two tabs) spending the same breath
  on two powers, or paying twice in the market; a commitment kept after all now gives back what the shield cost the
  protector, and the shield is ready again.
- **A minigame sent again** from the queue of the device counts once and shows again what it gave (database: `playid`
  in `minigame`).
- **The class screen**: during a battle it is drawn again only when something changes (a tap on «Reveal» was lost now
  and then, and the Embrollo shook at every poll); a double tap on «Play» opens one scene and one vote; the button to
  close a vote is drawn once; the roles of the class open the options of a role at the class screen, on both sides;
  the battle closed stays closed after reloading the page.
- **Scenes**: each new moment takes the focus (keyboard and screen reader no longer start from the page under it);
  a failure while showing the end of a play no longer asks to play it again; a session lost in the middle of a scene
  waits until it is closed to start the page again.
- **Mission data**: every option of a question counts for the objective of the question (some objectives only
  collected mistakes); in «The cellar of the lanterns» the good ending needs the light; the creature born asleep wakes
  up when the nest is made well; the page «The footprints of nobody» is given at the end of the owl; the page count
  is what the role of the student can get; an option id read as «false» broke «The court of life»; the question of the
  cell goes with the library and does not show if the teacher leaves SV-02 without a competency; the goals of
  Defensa del nido match its shield.
- **Smaller**: a student paused during a battle no longer answers or uses tokens; the thread of the Embrollo only in
  the final battle; −2 energy in «Class», as for a commitment not kept;
  Space presses buttons in the minigames; the queue of words given by the teacher sends one at a time; the badge is
  celebrated even after a scene from the welcome; dates for any language of the site; sound unlocked on the first
  click too (iPad); texts that promised a blot more than the rule allows, or one minigame where there are two.

## 0.55.0 (2026-10-17)

- **Who am I?**, a new kind of minigame, in the garden and in the tower (mission version 19): the Embrollo has turned
  parts of the plants, or the living things of the journey, into shadows; each shadow gives a clue, the hardest first,
  and the student guesses who it is among four. Another clue can be asked for; a wrong guess crosses that answer out
  and shows the next clue, and with no clue left the shadow gets away. The fewer clues needed, the more stars. Each
  step has its shadows (20 in all), the first win gives 5 gems, and the teacher sees who is taken for whom. Riddles are
  written as in Classtools: `= answer / also accepted` and a clue per line.

## 0.54.0 (2026-10-17)

- **The two circles**, a new kind of minigame, in the library, the forest and the lake (mission version 18): the
  Embrollo has let loose the cards of two circles of light, and the student takes each one to its circle, or to the
  middle, where they meet, if it belongs to both (the platypus lays eggs and has fur; the boar eats plants and
  animals). A card in the wrong place shows where it goes and why; too many, and the circles get tangled this time.
  Each step plays its pages, with the examples of the place (irupé, yaguareté, ñandú…). Touch the circles or use the
  three buttons (or the keys 1, 2 and 3). The first win gives 5 gems, and the teacher sees which cards go to the wrong
  place most. Pages are written as in Classtools: `Circle: thing, thing`, and `? thing: why`.
- **No page turn at every touch**: in the hunt for mistakes and in the two circles, the page turns only when it is a new
  one; a mistake found or a card put changes it in place, and the keyboard stays where it was.

## 0.53.2 (2026-10-17)

- **Defensa del nido on a phone**: the nets go three to a row from about 360 px wide, with the number in a corner and
  long names in two lines («Invertebrado» went out of its button); the counters fit in one line, with the pause as an
  icon (also in Who is alive?); the animals of the side lanes stay inside the field; and the field is a little lower,
  so that every net shows without scrolling.

## 0.53.1 (2026-10-17)

- **Names that read as they show** (WCAG 2.1, 2.5.3, with axe 4.14): the button of the character at the top reads its
  name and title, then «Me»; the place marked «Today» on the map says «Today» too; a long name of a place in two lines
  reads whole; and the box of ideas of the teacher panel reads «Box of ideas» with the ideas not read yet.

## 0.53.0 (2026-10-17)

- **Who is alive?**, the minigame of the clearing (mission version 17): living things and things that are not come out
  of the holes of the clearing for a moment, and the student touches only what is alive. A thing touched that is not
  alive is a slip, with its why; too many slips lose this time. Each step has its own things, from the snail and the
  stone to the mould of the bread, the coral and the salt crystal (27 in all), and its own speed. Without hurry, one thing
  at a time with two answers, for keyboard and screen reader too. At the end, what was mixed up, with its why. The first
  win gives 5 gems, and the teacher sees what the class takes for alive, or lets go, most.
- **The names of the places on the map**, in two lines when they are long and never out of the map (the student's map
  and the class screen): «Biblioteca de los Reinos» was cut. And the crystal that “grows” of the nest of the clearing
  no longer puts quotation marks inside quotation marks in the letters of the Embrollo.

## 0.52.0 (2026-10-14)

- **The hunt for mistakes of the Embrollo** («The tangled notebook»), a new kind of minigame, in every place of the
  pilot mission (mission version 16): the Embrollo has changed facts in the sheets of the field notebook; the student
  touches what is wrong and is shown what is right and why. A word touched that was right is a blot of ink, and too
  many blots lose this time (five in first steps, four in the core, three further and expert). Each step plays two or
  three sheets, with the mistakes the class confuses most in that chapter (24 sheets, 68 mistakes); the first win gives 5
  gems. The teacher sees which mistakes slip by most. Without connection, the play waits on the device like the others.
- Sheets are written as in the board of Classtools: `[what it says|right/also right|why]`. A minigame of a mission can
  now give gems the first time it is won.

## 0.51.0 (2026-10-14)

- **Reading aloud beyond the scenes**: for who has «Reading aloud», a 🔊 button in the panel of each place, the page of
  the day, each classroom challenge, the welcome and the battles missed from far. It reads all the text that shows (not
  what is folded, nor the buttons of the game), with a voice of the device itself.
- **The panel of a place, simpler**: its scenes and the letter of the Embrollo at sight; minigames, quests, Moodle
  activities and the classroom challenge folded under «More in this place».
- **The menu to leave** says that everything finished is saved, and how many plays wait on the device for the
  connection, if any.
- The README, up to date with what the plugin brings.

## 0.50.0 (2026-10-14)

- **Faster as courses and missions grow**: indexes for the scenes of a chapter, the entries of a request and the plays
  of a student in a course; the map of a student asks for its plays and endings in one query; the names of the
  teammates of the power requests, in one query; and the packages of missions are read from the disk only when one
  changes (kept in a cache of the site).
- **Privacy**: a teacher who asks for their data gets what they did in each course (the rewards they gave, the pauses,
  their decisions on powers, commitments and challenges), without the students it was about, also without a character
  of their own.

## 0.49.1 (2026-10-14)

- **Screen readers**: in the teacher panel, the lists of students far from the expedition and of classroom challenges
  are lists of buttons as screen readers expect them (the button inside each item). An automatic check of accessibility
  (WCAG 2.1 A and AA) finds nothing in the game, a scene, the teacher panel or the class screen.

## 0.49.0 (2026-10-14)

- **The pilot mission, version 15**: what is done and the material of each session, as in the teacher guide, so that
  «Today in the valley» reminds the teacher of everything (the chain race and its kerchiefs in the lake, the plants and
  the carnation with colour in the garden, a card for each student in the library, the photos for the field guide). The
  field guide says that each one adds at least one entry, with their team. Courses with the mission are brought up to
  date when they are opened, keeping what the students did.

## 0.48.1 (2026-10-14)

- **Oksigenia Access inside the game**: if the site has it, its panel comes into the pages of the game and of the
  teacher panel too (they are documents of their own, without the footer of Moodle where it usually comes in), with its
  button on the right edge, out of the way of the answers. Not on the class screen, which is projected.

## 0.48.0 (2026-10-14)

Smoother on modest tablets.

- **A place touched on the map** changes only its panel: the map stays as it is, without a flash, and the fireflies do
  not jump (they are always drawn in the same places). On small screens, eight fireflies instead of sixteen.
- **No blur behind the cards, the header and the tabs** on tablets and phones (it was painted again on every frame over
  a moving map), and the glow of the creature without an animated shadow.
- **The resources at the top explain themselves when touched** (a tablet has no mouse to hover): what saber, gems,
  energy and breath are for.
- With the art pack v18, the sounds of the places are loops of ten seconds in mono (80 KB each, from 386 KB): with what
  0.46.0 already did, each tablet loads some 300 KB of sounds instead of the two megabytes of 0.45.0, and uses much less
  memory.

## 0.47.0 (2026-10-14)

What Enrique decided for the first test in a classroom.

- **The teacher takes back a team play**: on the card of a student, «Team plays in their name» lists the plays a
  classmate made for them on their device; «Take back» removes the play, its evidence in the competencies, the saber
  and gems it gave and the scene done (if they have not played it themselves). What else it gave stays.
- **Who is paused**, for their classmates, has no mark: they cannot be chosen for powers or to play together, and
  nobody sees why. **Who follows from far**: the teacher decides, for each student, whether their team sees it («Their
  team sees it»); they cannot be marked as there in a team scene, and in the list of who is here they show, without a
  box to tick.
- **The role, changed by the teacher** on the card of the student, when they ask; the first screen says so.
- **The student turns on the adaptations of comfort** (big text, reading aloud, no movement, high contrast, colours, no
  sounds) in «Me», «How you see and hear it better»; no hurry and more help, only the teacher. The teacher sees on the
  card which ones the student turned on.
- **Today's place on the map** of each student: the one of «Today in the valley», ringed and with «Today» over it.
- **Who follows from far hears about the battles** they missed when they come in: whether the class won and the
  lantern it lit. The battles are kept a week, with only how the class did; what each one answered still goes after a
  day.
- **Who leaves a course**: what they did there is marked when they leave, stops showing, and is deleted after 180
  days (a setting of the site); if they come back before, it is all theirs again. Their character stays.

## 0.46.0 (2026-10-14)

A review of performance, robustness and privacy, for a whole class at once on a school wifi.

- **Lighter on the server**: the mission and the settings of the course are read once in each request, and what the map
  shows of each scene is kept in a cache instead of reading the 27 scenes whole each time; the team and the classroom
  challenges in one query each. The phones in a battle ask every two seconds and a half (or just when the question
  closes) with half the queries, and, while there is no battle, less and less often, up to every half minute. The class
  screen reloads everything every minute, and what confuses the class most is worked out once every ten minutes.
- **Sounds on demand**: each tablet loads the short sounds at the first touch, and the sound of a place only when the
  student is there (before: all of them, some two megabytes, at once). The sound of a place no longer leaves a sound
  running behind it when the student moves on, and comes back after a call or the tablet locked (iPad).
- **Nothing played is lost**: a call that hangs is given up after twenty seconds, and the play waits on the device. A
  play whose answer was lost on the way is sent again with the same mark and counts once (no second try, no hints paid
  twice). When the session ends or another student comes in on the device, the plays wait instead of being dropped. A
  minigame finished without connection waits too, and goes by itself. On a tablet shared by many students, a play
  makes room for itself (the scenes kept to play without connection, up to twelve per device, go first). If the server
  turns a play down for good, the student is told to play it again; while the server fails, it is tried ten times.
- **Two teammates who finish a team scene at the same moment**, each on their device, both count.
- **The class screen finds its battle or vote again** after a while away (the tab hidden, the computer asleep), and a
  battle nobody has played yet can be called off from its lobby.
- **The welcome for who arrives with the expedition on its way** shows right after creating the character; the little
  living things visit the game of the students too (before, only the class screen); the frog sits by the lake.
- **The card of a student** stays open while the teacher marks their adaptations. **Reading aloud** waits a moment for
  the voices of the device before saying there is none.
- **Privacy**: what the teacher decides about a student (their step, that they follow from far, a commitment not kept)
  is no longer sent to their team; a student who follows from far cannot be marked as there in a team scene. A team play
  a teammate made on their device does not count as the first try of the others, and its evidence in the competencies
  says «as a team». The class screen gets only what it shows (no pictures, notes or adaptations) and does not mark who
  is paused. The pictures of the students never come from Gravatar. What a device keeps of a student goes when another
  one comes in (their plays still waiting stay for a fortnight). Giving rewards (`award`) carries the personal data
  risk. A badge made while the mission is brought up to date in the request of a student is made by the site. The zips
  of missions are measured before unpacking them. Whether a play was a team one is in the privacy export.

## 0.45.0 (2026-10-14)

- **Accessibility for each student**: on the card of a student, the teacher marks what the game adapts for them (never a
  diagnosis; only the student and their teachers see it), and the game adapts itself when they play: big text (the
  whole game, bigger), reading aloud (a 🔊 button reads each scene with a voice of the device itself, so that the text
  goes nowhere else), no hurry (a slower minigame, no countdown in the battles), no movement (no animations, confetti or
  little living things), high contrast, colours for colour blindness (blue and orange instead of green and red, always
  with ✓ and ✗), no sounds, and more help (hints that cost no breath). Saved as soon as it is marked; in the privacy
  export.

## 0.44.0 (2026-10-14)

- **The return of who has been away**: who comes back after a week or more (ill, travelling, in hospital) and who
  arrives with the expedition on its way are welcomed with what the class did meanwhile (the lanterns it lit), who waits
  for them in their team, and a short way to catch up: in each place the class lit and they have not done, the first
  scene of their step, ready to play. Once: the visit is noted at the same time (`lastseen`).
- **Following the expedition from far**: the teacher marks a student who follows it from home or hospital; they see
  that the votes and battles of the class screen come to their device, and their team sees «from far» when playing
  together.
- **For the teacher**: «Far from the expedition», in the class tab: who has not come in for a week or more, and who
  follows from far; and on the card of each student, when they last came in. The new data is in the privacy export.

## 0.43.0 (2026-10-14)

- **A «What if…?» at the end of each scene** of the pilot mission (mission version 14): a question to think about for
  a moment, or to talk about with the team or the whole class («What if in the lake there were no mosquitoes? They are a
  nuisance, but… who would go hungry?»). It shows at a good ending or halfway, also on the class screen (bigger) and at
  the end of a play kept without connection. A scene says it with its field `whatif`; the teacher can change it in the
  workshop, like any other text.
- **The question quoted on the class screen** keeps no full stop of its own inside the quotes.

## 0.42.0 (2026-10-14)

- **Nothing is lost without connection**: a scene opened is kept on the device (the last thirty), so that it can be
  opened again without connection. A play finished without connection is kept on the device («Saved on this device»)
  and goes by itself when the connection comes back: when the game opens, when the connection returns and every minute
  and a half; then what it gives comes («Your play of … has been sent»). On a shared device, each student's plays wait
  under their own account.
- **The Embrollo boasts of what confuses the class most**: on the class screen, its letter of the day ends with a
  postscript with the mistake the class makes most, without names («P.S.: How I love it when, in …, you choose …! That
  is how I grow»), to go over it together.

## 0.41.0 (2026-10-14)

- **Scenes played as a team, for real**: beside «Play», the scenes that can be played as a team have «As a team». The
  student marks who of their team is there; they play on one device, talking about each decision («As a team: talk about
  each decision before choosing»), and the team has what any of them carries. At the end it counts for each of them:
  their play, the evidence of their competencies and what it gives, if they did not have it. Only teammates of the same
  team can be marked. The scenes meant for the team («The night by the lake») say so, and «As a team» goes first.
- **A team game is one game**: the plays of its members share the same number (`teamplay`), and in what the class
  results show (what confuses the class most) it counts once.

## 0.40.1 (2026-10-14)

- **A little living thing touched**: its name, and below it what is true about it, instead of both in one line.

## 0.40.0 (2026-10-14)

- **The little living things of the valley**: fireflies blinking by the lanterns of the map, and now and then a
  visitor: a butterfly, a dragonfly, a bee or a fairy of light that crosses the screen, a ladybird along the edge of a
  card that flies away at the end, a snail with its trail, ants in a row, a spider that comes down on its thread and
  goes up again. Touched, they say what they are and something true about them («It is not an insect: it has eight
  legs»). Few and slow, never during a scene, none with «less motion»; on the class screen, less often.
- **Each world brings its own** in its art pack (`theme.critters`): their pictures (two at most, one after the other),
  how they move, their size and speed, and their name and fact in each language; only what is valid comes in. Without
  them, those of the valley, drawn by the plugin until the painted ones come.

## 0.39.1 (2026-10-14)

- **The sounds of a pack keep their own level**, the one they were made with, instead of going through the lower one of
  the sounds made by the browser; the sound of the places, a little lower still.
- **Loops without a gap**: whatever silence the decoding of a sound leaves at its ends stays out of its loop.

## 0.39.0 (2026-10-14)

- **A world brings its sounds**: an art pack may carry recorded or made sounds (`sound.right`, `sound.drumroll`…),
  the sound of each kind of place (`sound.ambient.meadow`…) and its music (`sound.music`), in MP3, Ogg or M4A. Only
  under keys that start with «sound.», and only files that start as a sound does. The game loads them after the first
  touch and uses them when they are there; without them, the sounds made by the browser, as until now. The music is
  only loaded on the class screen.

## 0.38.2 (2026-10-14)

- **The plan of the next class speaks to the class with «ustedes»**, as all the products of Oksigenia: the AI is told
  so for what is said to the whole class, like the question to open it.

## 0.38.1 (2026-10-14)

- **A music for each world** on the class screen: the valley (as in 0.38.0), the sea (slower, with the swell of the
  waves) and space (open chords that float, far bells). The art pack chooses it in its theme (`"music"`); the valley
  by default.
- **Each moment of a scene comes in like a turned page.**

## 0.38.0 (2026-10-14)

- **The road draws itself**: when a student comes back to the map after reaching a new place, the stretch walked since
  the last look lights up and draws itself, with steps and a chime, and the character walks it to the new place. On the
  class screen, the same with each place the class lights. Once on each device; with «less motion», nothing moves.
- **The creature breathes**, and the hero too, a little, in the «Me» tab and over the page of the day.
- **The moment of the badge**: when a student has the badge of the mission, the game celebrates it once on each device,
  with the medal after a drum roll, applause, confetti and a link to their badges in Moodle.
- **Soft music on the class screen**: a slow round of chords with bells over it, made by the browser as it plays (no
  files), never on the devices of the students. A «♪ Music» button turns it on or off on that screen, remembered.

## 0.37.1 (2026-10-14)

- **Light or dark at a touch in the panel**: a sun (to go to day) or a moon (back to night) at the top of the panel
  changes this device only, like the button of the class screen; both remember it on the device. The settings button
  is now a gear: its old drawing looked like a sun.
- **The theme of the class is saved as soon as it is chosen** in the settings, and seen at once, without the button
  at the end of the form; the device then follows the class again.

## 0.37.0 (2026-10-14)

- **Themes**: the night of the valley, as until now, and the **day**, light, easier to read on a projector and in
  bright classrooms. The teacher chooses it for the whole class in the settings of the panel («Theme of the class»):
  the game, the panel and the class screen. On the class screen a button switches it for that screen only, remembered
  on the device. It goes in the backups of the course.
- **A world brings its colours**: an art pack may say which theme is its own (night or day) and bring its colours over
  either of them, only colours, checked one by one. The valley stays at night.
- **The draft by the AI has its own permission** (`local/oksigeniatesape:aidraft`, editing teachers and managers), apart
  from the plan of the next class: a site can keep one and not the other. The button shows only to who has it.

## 0.36.3 (2026-10-14)

- **The licences of a mission, with a link to read them**, in the mission tab of the panel and on the page of the
  missions of the site. The sample mission is under CC BY-SA 4.0 or GNU GPL v3, to choose from
  (`missions/livingthings/LICENSE.md`); the missions of a teacher are of their author.

## 0.36.2 (2026-10-14)

- **A way out of the game, always at hand**: a button at the top of the game, beside the sound, goes back to the
  course or to the dashboard of the student (only the dashboard when the course goes straight into the game). Before,
  it was only at the end of the «Me» tab.

## 0.36.1 (2026-10-13)

- **The teacher can take the sound away** from the devices of the whole class (settings of the panel: «No sound on the
  devices of the students»); the class screen still sounds. It goes in the backups of the course.

## 0.36.0 (2026-10-13)

- **The game feels alive**: sounds made by the browser itself (Web Audio: no files, nothing from outside) for choosing,
  right and wrong answers, lanterns, levels, the creature being born, the hits and the end of a battle; a drum roll,
  heard and seen, before the ending of a scene; confetti and applause for a good ending, hearts for one halfway,
  sparkles for a lantern; and a soft sound of the place (wind and birds in the meadow and the forest, water at the lake,
  the wind of the mountain).
- **The valley lights up**: the map starts at night and gets clearer with each lantern the class lights, on the phones
  and on the class screen.
- **A button of silence** in the game, remembered on the device. The sounds wait for the first touch, and the movement
  respects «less motion» of the device.

## 0.35.0 (2026-10-13)

- **A draft of a scene with the AI of the site** (if the course has it and the teacher accepted its policy): in the
  scene of the workshop, the teacher tells what they want to happen and the AI writes the narration, one to three
  moments and the endings, cleaned as any scene of the teacher. Always a draft: the teacher reads it and changes it,
  and the checker looks at it like any other. Without AI, nothing changes.

## 0.34.1 (2026-10-13)

- The map in layers ends in the mountain, where the last place is (the meadow at the bottom, forest and lake between),
  and its bands overlap a little, so that no line shows between them. Its art comes with the art pack of the valley,
  version 14 (the four bands and the six landmarks).

## 0.34.0 (2026-10-13)

- **Maps of any size, in layers**: a mission made by a teacher can have from three to fifteen places. Up to six, the
  painted map of the valley; more, a long map that is scrolled with the finger from the bottom up and opens where the
  student is: the places in a zigzag, the road drawn by the game, a landmark of the valley for each place (the oak
  first, the tower last) and the bands of the world as its ground (meadow, forest, lake, mountain), with a drawing of
  the night until the art arrives. The same on the class screen.

## 0.33.0 (2026-10-13)

- **A mission made in the workshop, as a package**: «Download as a package» gives a zip as those of the catalog, with
  a code of its own and the draft inside; any site takes it in «Missions of Tesapé» as one more mission.
- **From a template**: «Load a mission from a file» takes such a package (made here or by another teacher) as a new
  draft of the course, ready to change it and publish it; «Duplicate» copies a draft in the same course.

## 0.32.2 (2026-10-13)

- **Try a scene before publishing** («Try this scene», in the scene of the workshop): the scene opens as a student
  would see it, made from what has been written, and is played to an ending; nothing is kept.
- The places of a mission made by a teacher take the spots and the landscapes of the valley in their order (the
  clearing, the library, the forest, the garden, the lake and the tower), and the mission uses the painted map of the
  valley: place, background and map always match.

## 0.32.1 (2026-10-13)

- **The missions the teacher made go in the backups of the course**: the draft and the mission it became come back in
  the copy, with a code of their own there, and the copy plays it as the original did.

## 0.32.0 (2026-10-13)

- **The workshop, part 2: make a mission from scratch** (panel, «Mission» tab, «Make a mission from scratch»), in six
  steps: the mission (title, topic, ages, beginning and end of the story), what is learnt (objectives, with how each
  looks at the four levels), the places of the map (three to six, with the classroom challenge, commitments and
  sessions), the guided scenes of each place (narration, who speaks, one to three moments: choose one, choose several,
  classify or order, with hints and endings), the questions of the battle, and «Check and publish», with what is still
  missing as a teacher would say it. What is written is saved by itself. Published, it is the mission of the course;
  published again, the students keep what they did. A draft never played can be deleted.
- The text workshop of 0.29 is not offered for a mission the teacher made: that one is changed where it was made.

## 0.31.0 (2026-10-13)

- **The workshop, part 2, the base: a mission from scratch** (the screens come next). A teacher's mission is a draft
  in the course (new table `own`): the mission, its objectives, from three to six places, its guided scenes and the
  questions of the battle. A checker says what is still missing, as a teacher would say it; while something is
  missing, it cannot be played. Made into a mission, it is played as any other: map, lanterns, creature, classroom
  challenges, competencies, badge, final battle and report. Made again, a new version, keeping what the students did.
- **Guided scenes**: a narration, from one to three moments (choose one, choose several, classify or order) and
  three endings; the game makes the nodes and the rules under them.
- A rule of a scene can add up the right answers of several moments; the game of the student and the server count
  the right answers of a classify the same way.

## 0.30.0 (2026-10-13)

- **Missions uploaded to the site**: besides those that come with the plugin, Tesapé reads the missions kept in the
  site data (`local_oksigeniatesape/missions`); of each mission, the newest version wins. A mission bought, or a newer
  version of one (also of the free one, without a new version of the plugin), is uploaded as a zip by the site
  administration, in the new page «Missions of Tesapé» (Site administration › Plugins › Local plugins), which lists
  every mission with its version, licence, origin and last change. Courses with it are brought up to date when they
  are opened, keeping what the students did and the changes of the teacher; an older version is refused.
- **The identity of each package**: its edition, its licence (free or paid), its publisher and what each version
  brought, saying whether it was a fix or an improvement, so that the catalog server can offer the updates each
  licence allows, also of missions uploaded by hand.

## 0.29.2 (2026-10-13)

- **The changes of the workshop, elsewhere**: take them to another course of the teacher with the same mission, or
  download them as a file and load it in another course or another site. The file has only the texts of the teacher,
  never the mission: whoever loads it needs the same mission, and what does not fit their version is left out.

## 0.29.1 (2026-10-13)

- **Local examples for anywhere**: the pilot mission (version 13) brings a third set of local examples, «General, for
  any place», first and chosen by default, with animals, plants and words that are understood in any Spanish-speaking
  country; Spain and Paraguay stay as options. A set for another place is thirty texts, and each teacher can change
  theirs in the workshop. The demo classes use the general examples.

## 0.29.0 (2026-10-13)

- **The workshop, part 1: change the mission** (panel, «Mission» tab, «Change the mission»). The teacher changes, for
  the class of the course, the texts of every scene (title, narration, questions, options, what is said after them,
  hints, endings) and of every place («Today in the valley», the classroom challenge, the commitments), and the
  questions of the battle (change, add and delete them). More than a thousand texts in the pilot mission.
- The changes are kept apart, as a layer over the mission (new table `edit`): a newer version of the mission keeps
  them, a text left empty goes back to the original, and a scene or the whole mission can go back to the original in
  one go. A changed text is used with every story and every set of local examples.
- The changes go in the backups of the course, and away with the course when it is deleted.

## 0.28.6 (2026-10-12)

- The card of the game shows on the page of the course only, not on its participants (Moodle gives that page a type
  that also starts with «course-view»; seen on campus).

## 0.28.5 (2026-10-12)

- **The core completes a chapter for everybody**: a student of first steps who plays the core scenes of a place (with
  the class on the class screen, for instance) has done it, and lights its lantern. Without devices, the class screen
  is enough for every step.
- The pilot mission, version 12: the clearing gives **the powerful magnifier** when it is done; some options of the
  next places asked for it, and nothing gave it (only a mage in the team opened them).

## 0.28.4 (2026-10-12)

- **The team activities follow the teams of the game**: made or made again after the mission is installed, or chosen
  in the settings, the team submissions of its activities use the new teams (before, they kept the first grouping, and
  a student could be in two teams for Moodle).
- **The lantern of the tower, for the whole class**: when the class wins the final battle, whoever was not in class
  that day gets it too (not the prize of the battle), so that missing that day does not leave anyone without the badge.
- **The badge, turned on late**: when the teacher turns on the badge of the mission, it goes at once to whoever has
  already lit every lantern.
- The pilot mission, version 11: the order of the sessions of the garden (the class bets in the cellar before looking
  at the experiment), «Defensa del nido» on each device, and a challenge of the lake named the same everywhere.

## 0.28.3 (2026-10-12)

- The result of a battle shows on the phones for five minutes after it ends: once a student closes it, it does not
  show again when the page is opened again in that time.
- The opening of the mission shows only to whoever has not begun it: a student who has played a scene goes straight
  to the map, also on another tablet of the class.
- A test plays the whole mission with a class of eight, on every change: the scenes, the quests, the final battle, the
  lanterns, the badge, the competencies, the gradebook and the report.

## 0.28.2 (2026-10-12)

- The badge of the mission is made from a copy of its medallion: Moodle deletes the image it is given, and it took the
  one of the plugin, so the next courses would have had badges without an image (found by the tests on Moodle 4.5,
  before the badge reached any site).

## 0.28.1 (2026-10-12)

- The medallion of the badge is a full-colour PNG: Moodle 4.5 could not write the badge into it when it was given.

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
  (`moodle/ai:acceptpolicy`), not only in the panel (seen on campus.oksigenia.com).

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
