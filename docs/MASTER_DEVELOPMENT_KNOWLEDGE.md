# Udaan Master Development Knowledge Base

**Document status:** Active source of truth  
**Last reconciled:** 22 September 2026  
**Current application version:** v0.6.5  
**Current main SHA:** `93f965abaeba30d4dcf8fe4c5cc5722d919285b5`  
**Repository:** `indigiti/udaan`

---

## 1. Purpose of this document

This document is the master development knowledge base for Udaan. It records:

- what Udaan is today;
- what has already been implemented;
- what is certified in the repository;
- what still requires live/server verification;
- the long-term product vision;
- the product psychology and growth strategy;
- the technical architecture we are moving toward;
- the next release sequence;
- the 90-day, 1-year and 10-year roadmap;
- safety, privacy and trust principles;
- release/deployment rules;
- important decisions that should remain stable to reduce rework.

The intention is that future Udaan development starts by checking this file rather than reconstructing project knowledge from past conversations.

---

# 2. North-star product vision

Udaan should evolve from a distributed daily-learning PWA into a **Pan-India personal performance and learning platform**.

The long-term positioning is:

> **Udaan — Learn. Compete. Live Better. Grow Every Day.**

Udaan should help a person improve across four connected dimensions:

1. **Knowledge** — what the person knows.
2. **Performance** — how well the person can apply knowledge under real conditions.
3. **Wellbeing** — how sustainably the person studies, trains, rests and lives.
4. **Growth** — discipline, confidence, values, life skills, purpose and character.

The product should eventually support:

- school students;
- JEE / NEET / CET aspirants;
- UPSC / SSC / banking / railway / defence / state-PSC aspirants;
- general learners;
- fitness and wellbeing users;
- students seeking focus, motivation and life-skill support;
- users interested in Bhagavad Gita / Krishna / spiritual-development programs;
- schools;
- colleges;
- coaching institutes;
- sports academies;
- temples and spiritual institutions;
- NGOs and community organizations.

The central product principle is:

> **Study and self-development should feel like sports training.**

Traditional learning flow:

`Watch → Read → Practice → Test`

Udaan target flow:

`Warm-up → Train → Compete → Replay → Recover → Improve`

---

# 3. Long-term strategic thesis

Udaan should assume that AI explanations, question generation, translation, study plans and generic tutoring become commodities.

Therefore Udaan must not depend on "having AI" as the core differentiator.

AI should become an invisible intelligence layer.

Udaan should own what is harder to commoditize:

- persistent learner identity;
- long-term performance history;
- mastery and revision history;
- personal bests;
- team membership;
- friendships;
- school/coaching/community identity;
- leagues and seasons;
- trusted achievement;
- habit history;
- real-world community;
- verified multilingual content;
- offline Bharat distribution;
- institutional networks.

The model provider must remain replaceable.

The lasting assets should become:

- **Performance Graph**
- **Knowledge Graph**
- **Social Graph**
- **Institution Graph**
- **Habit / Readiness Graph**
- **Trusted Content Graph**

---

# 4. Core product psychology

Udaan should be designed around durable human motivations rather than addictive dark patterns.

| Human need | Student interpretation | Udaan mechanism |
|---|---|---|
| Competence | I want to feel capable | mastery, personal bests |
| Progress | Show me I am improving | before/after performance |
| Belonging | I want my group | teams, houses, communities |
| Identity | This is who I am | Udaan Player identity |
| Recognition | Notice my effort | achievements, awards |
| Autonomy | Let me choose | goals, paths, missions |
| Challenge | Give me something worth beating | matches, leagues |
| Purpose | Why am I doing this | goals, values, reflection |
| Novelty | Give me fresh experiences | seasons, events |
| Ritual | Make this part of my day | Daily Missions |

The preferred motivational loop is:

`Identity → Mastery → Belonging → Purpose → Progress`

Points, XP, badges and streaks support this loop but must never become the product itself.

---

# 5. Product architecture — target Arenas

Udaan should eventually contain these product Arenas:

| Arena | Purpose | Examples |
|---|---|---|
| Learn | academics and knowledge | school, JEE, NEET, UPSC, SSC |
| Compete | sports-style learning | matches, leagues, championships |
| Fit | physical development | walking, running, yoga, mobility |
| Health | everyday wellbeing | sleep, hydration, nutrition education |
| Mind | mental performance | focus, confidence, stress management |
| Life | lifestyle and practical skills | routines, communication, money basics |
| Momentum | behavioural motivation | personal bests, comeback, consistency |
| Reflect | spiritual and inner growth | Gita, meditation, gratitude, values |
| Connect | community and institutions | friends, teams, schools, temples |
| Coach | cross-Arena intelligence | plans, recommendations, interventions |

These should not become ten disconnected applications.

The shared platform underneath should include:

- Player Profile
- Goal Engine
- Mission Engine
- Training Session Engine
- Event Ledger
- Performance Graph
- Rewards / Achievement Engine
- Coach Engine
- Privacy / Consent Engine
- Content Graph
- Localization Layer
- Social / Team Graph
- League / Season Engine
- Offline Sync
- Analytics
- Admin / Content Studio
- Trust & Safety

---

# 6. CURRENT REPOSITORY STATUS

## 6.1 Current certified baseline

**Application:** Udaan Live  
**Version:** v0.6.5  
**main:** `93f965abaeba30d4dcf8fe4c5cc5722d919285b5`

Current certification:

- Udaan Security and Syntax: **PASS**
- Main certification run: `35684101671`
- Certified Release Artifact: **PASS**
- Release run: `35684117298`
- DigiOps artifact name: `digiops-release`
- DigiOps artifact ID: `10676416116`
- Artifact digest: `sha256:6ffaf48ad332397016f97f91827a48069bd2c7f9aaf00d653ec2bd7cb670814d`

The artifact layout was corrected so DigiOps receives the deployable application tree directly rather than a nested tarball/checksum wrapper.

The certified package now requires an application entry point and preserves hidden files such as:

- root `.htaccess`
- `data/.htaccess`
- `ops/.htaccess`

## 6.2 Current repository operating state

At the time of reconciliation:

- open PRs: **0**
- branch protection on `main`: **not enabled**
- several old feature/release branches remain and should be archived/deleted after verification
- the codebase remains PHP + PWA + encrypted JSON/Redis + encrypted browser IndexedDB
- no production relational database is required by the current release
- Redis can be optional or required depending on runtime policy
- content trust uses Ed25519 signatures and SHA-256 integrity
- content trust epoch: **2**

## 6.3 LIVE-PENDING status

Repository certification is complete.

The following are not considered complete until confirmed on the real deployment:

- DigiOps deployment of artifact `10676416116`
- exact deployed source SHA confirmation
- `/ready` HTTP 200
- `/health.php` runtime verification
- Redis required/optional policy verification
- signed content distribution `ready=true`
- correct PWA/static version in live browser
- protected `data/` and `ops/` HTTP access
- live runtime secret/key state
- live runtime security rotation if it has not already been performed
- room / join / answer / completion smoke
- offline pack / reconnect sync smoke
- QOI CSRF flow smoke
- DigiOps deployment success after artifact-layout correction

**Rule:** repository-green is not the same as server-verified.

---

# 7. What has already been implemented

## 7.1 v0.4.x foundations — DONE

The earlier platform established:

- GUID-based card bank
- browser-local QR
- no-repeat learner history
- verified content-bank import
- room/join flow
- UUID room routes
- screen OTP flow
- theme support
- CSRF foundations
- randomized learner journeys

## 7.2 v0.5.x offline foundations — DONE

Implemented:

- encrypted IndexedDB learning vault
- device-install UUID
- server HMAC device identity
- AES-GCM encrypted temporary server storage
- offline reserve
- offline answer queue
- reconnect sync
- learning reflection rail

## 7.3 v0.6.0 distributed daily-learning foundation — DONE

Implemented:

- Daily 9
- one card per pillar
- Friend Challenge
- Question of India
- Mystery Card
- session badges
- anonymous aggregate response signals
- signed content packs
- signed content manifest
- Ed25519 trust authority
- SHA-256 pack integrity
- 1,008-card maximum encrypted device reserve
- room-level unique journey fingerprints
- frozen per-participant option order
- variable-choice cards
- Q&A/info-first journey balance
- automatic signed-pack rebuild after verified import
- rollback if pack publication fails

The architectural principle was frozen as:

> **Centralize trust, decentralize distribution.**

## 7.4 v0.6.1 security recovery — DONE IN REPOSITORY

Implemented:

- removal of tracked runtime secrets/state from the normal repository baseline
- clean security history baseline
- fail-closed app secret generation
- 0600 key permissions
- encrypted-room cleanup fix using `secure_unpack()`
- fail-closed AES-256-GCM sensitive storage
- read-only public content distribution
- trust epoch 2
- controlled Ed25519 signing-key migration
- maintenance mode for live security rotation
- CLI runtime-security rotation command
- CLI signed content rebuild command
- CI secret/runtime-state protection

Important operational note:

live servers still require verification that the coordinated runtime rotation has actually occurred.

## 7.5 v0.6.2 performance and abuse hardening — DONE

Implemented:

- compiled runtime content cache
- no full-bank presenter scan on every poll
- protected presenter state
- Redis-first rate limiting
- file-backed rate-limit fallback
- room-create throttle
- join throttle
- answer throttle
- offline-sync throttle
- QOI throttle
- Content Bank login throttle
- bounded JSON request bodies
- join CSRF
- Content Bank CSRF
- CSP / HSTS / cross-origin security headers
- runtime cache and throttle smoke tests

## 7.6 v0.6.3 production readiness — DONE

Implemented:

- `UDAAN_ENV`
- `UDAAN_TRUST_PROXY_HEADERS`
- `REDIS_REQUIRED`
- public `/ready`
- CLI `ops/preflight.php`
- encrypted-storage readiness
- signing readiness
- key-permission readiness
- compiled-cache readiness
- signed-distribution readiness
- Redis policy readiness
- safe diagnostics
- production runtime documentation

## 7.7 v0.6.4 certified deployment artifact — DONE

Implemented:

- deterministic package builder
- source-SHA traceability
- `RELEASE.json`
- `SHA256SUMS`
- reproducible packaging
- artifact publication only after exact main certification
- runtime secret/state rejection in release package

## 7.8 v0.6.5 hardening — DONE

Implemented:

- QOI same-session CSRF
- existing QOI device/network throttles retained
- offline sync hard rejection above 250 events
- compiled GUID → card lookup
- service-worker cache v0.6.5
- QOI script cache bust
- release CI assertions

## 7.9 DigiOps artifact compatibility fixes — DONE

Two release-pipeline compatibility defects were fixed after v0.6.5:

### Artifact name mismatch

DigiOps expects the exact artifact name:

`digiops-release`

The Udaan release workflow was changed to publish that stable name.

### Artifact layout mismatch

DigiOps extracts the outer GitHub artifact and expects `index.php` or `index.html` in the deployed tree.

The original workflow uploaded a nested tarball/checksum pair, causing:

`ENTRYPOINT_MISSING`

The workflow now uploads the sanitized staged application tree directly.

Permanent CI guards now check:

- stable `digiops-release` artifact contract
- staged deployment path
- hidden-file inclusion
- `index.php` in the certified package
- protected operational directories

---

# 8. What is NOT built yet

The following ideas belong to the approved future vision but are not yet implemented as production product systems.

## Product foundation pending

- persistent Udaan Player Profile
- Goal Engine
- Arena preferences
- structured exam targets
- universal Mission Engine
- unified Event Ledger
- new Performance Graph
- long-term achievement graph
- Daily Home / mission-first dashboard
- Coach memory
- Coach recommendations across Arenas
- persistent team/social graph
- league/season system
- institution accounts
- parent privacy model
- creator ecosystem

## Academic expansion pending

- full JEE Arena
- full NEET Arena
- UPSC Arena
- SSC / Banking / Railways
- NDA/CDS
- state PSCs
- school curriculum graph
- PYQ intelligence
- adaptive revision scheduler
- full diagnostic engine
- exam simulation engine
- answer-writing evaluation
- current-affairs workflow

## Fit / Health pending

- Daily Readiness product
- movement missions
- study-break mobility
- walking/running plans
- yoga programs
- bodyweight fitness
- recovery programs
- sleep routine system
- hydration habit
- nutrition education
- eye-care reminders
- posture education
- age-specific safety controls
- optional fitness-device integrations

## Mind / Lifestyle pending

- Focus Training
- procrastination program
- exam-pressure program
- confidence program
- failure recovery
- digital discipline
- morning/night routines
- habit engine
- communication skills
- money basics
- leadership
- decision-making programs

## Reflect pending

- Daily Gita
- Sanskrit / transliteration / meaning structure
- shloka audio
- Gita reflection
- Gita quiz
- student-focused Gita programs
- gratitude
- journaling
- meditation
- values
- purpose
- service challenges
- temple/community program layer

## Pan-India pending

- multilingual content model in all new domains
- English + Hindi + Marathi first
- additional Indic languages
- voice-first interaction
- deep low-bandwidth UX
- institution nodes
- national league
- creator network
- physical Udaan clubs/events

---

# 9. Decisions already frozen

The following architectural/product decisions should not be repeatedly reopened unless there is strong evidence.

## 9.1 Trust architecture

**Centralize trust, decentralize distribution.**

Only trusted central processes approve/sign educational content.

Delivery may eventually occur through:

- origin
- school edge
- verified peer/device

but delivery must not grant content-authoring authority.

## 9.2 Offline-first

Offline remains a strategic feature, not merely a fallback.

The product should continue supporting:

- encrypted local content
- offline learning
- queued answers
- reconnect sync
- low-bandwidth usage

## 9.3 One Player, many Arenas

Do not create separate identities for:

- exams
- fitness
- health
- mindset
- spirituality

One Udaan Player should hold multiple profiles/permissions.

## 9.4 One Mission Engine

Do not build separate task systems for academics, fitness, habits and Gita.

A mission can represent:

- solve 10 questions
- walk 15 minutes
- complete focus training
- drink planned water
- learn a shloka
- complete revision

## 9.5 AI is model-agnostic

AI providers must remain replaceable.

Udaan should own:

- user state
- event history
- content graph
- performance graph
- social graph
- institution graph

## 9.6 Spirituality is opt-in

Gita / Krishna / spiritual experiences must never be mandatory for academic users.

## 9.7 Score knowledge, not faith

Spiritual learning can track:

- shlokas studied
- chapters explored
- quizzes
- reflection completion

but must not rank "devotion".

## 9.8 Health is educational, not clinical

Udaan should support health habits and wellbeing education but must not claim medical or psychiatric diagnosis.

## 9.9 No pay-to-win

Users must never be able to purchase academic rank, league rank or XP advantage.

## 9.10 Healthy engagement

Do not optimize infinite screen time.

Optimize:

> **Meaningful Training Days**

---

# 10. Target Player architecture

The next-generation Player object should eventually include:

```text
Player
├── identity
├── age_group
├── class
├── language
├── region_scope
├── goals
├── exam_targets
├── interests
├── privacy
├── academic_profile
├── performance_profile
├── readiness_profile
├── fitness_profile
├── lifestyle_profile
├── coach_state
├── teams
├── achievements
└── institution_memberships
```

Onboarding should remain short.

Recommended first questions:

- what are you preparing for?
- class / age range
- preferred language
- available daily time
- main goal

All additional data should be progressively collected.

---

# 11. Mission Engine target

Universal Mission model:

```text
Mission
├── id
├── player_id
├── arena
├── type
├── title
├── objective
├── duration
├── difficulty
├── content_refs
├── completion_rule
├── reward_rule
├── scheduled_at
├── expires_at
└── result
```

Mission examples:

### Learn

`Solve 10 Human Physiology questions`

### Fit

`Complete 10-minute mobility`

### Health

`Complete today's hydration goal`

### Mind

`Complete one 10-minute Focus Sprint`

### Reflect

`Study today's Gita shloka`

---

# 12. Training Session Engine target

Academic and performance sessions should use a sports model:

```text
Warm-up
   ↓
Training
   ↓
Challenge
   ↓
Replay
   ↓
Recovery
   ↓
Personal Best
```

Session types:

- Warm-up
- Drill
- Speed Round
- Accuracy Round
- Match
- Marathon
- Replay
- Recovery
- Personal Best attempt

This engine should be generic across exams and subjects.

---

# 13. Event Ledger target

Every meaningful action should generate a structured event.

Examples:

```text
player_created
goal_created
mission_assigned
mission_started
mission_completed
question_answered
concept_trained
revision_completed
personal_best
match_started
match_completed
team_joined
readiness_submitted
fitness_completed
focus_session_completed
reflection_completed
coach_recommendation_shown
coach_recommendation_accepted
```

Example envelope:

```json
{
  "event": "mission_completed",
  "player_id": "...",
  "arena": "learn",
  "mission_id": "...",
  "occurred_at": "...",
  "metrics": {}
}
```

The Event Ledger becomes the source for:

- analytics
- performance aggregation
- Coach
- achievements
- leagues
- future AI
- retention analysis

---

# 14. Performance Graph target

Do not reduce a person to one number.

Track independent dimensions:

- Knowledge
- Accuracy
- Speed
- Consistency
- Revision
- Focus
- Fitness
- Recovery
- Lifestyle
- Life Skills

The Player Profile should show strengths and development areas.

Do not create a universal "good student score".

---

# 15. Daily Readiness target

Initial Daily Readiness inputs:

- Sleep
- Energy
- Stress
- Focus
- Body

Output:

- Low readiness
- Balanced readiness
- High readiness

This is a performance-planning signal, not a medical score.

Coach examples:

High → hard mock / PB attempt  
Balanced → normal session  
Low → revision + light movement + recovery

---

# 16. Udaan Fit target

Start small.

First version:

- 5-minute study break
- 10-minute mobility
- 15-minute walk
- basic stretching
- basic yoga
- daily movement mission

Track:

- completion
- consistency
- improvement
- personal best

Avoid:

- body comparison
- extreme exercise
- weight-loss leagues
- unsafe calorie targets for minors

---

# 17. Health target

Initial educational modules:

- sleep routine
- hydration
- balanced nutrition education
- eye breaks
- study posture
- outdoor movement
- basic hygiene
- sustainable recovery

Future integrations should remain optional and privacy-controlled.

---

# 18. Udaan Mind target

Initial modules:

1. Focus
2. Procrastination
3. Exam Pressure
4. Confidence
5. Failure Recovery

Preferred session pattern:

`Understand → Practice → Challenge → Reflect`

Example Focus Session:

- 90-second concept
- 1-minute settling/breathing
- 10-minute focus sprint
- distraction count
- reflection
- next goal

---

# 19. Momentum Engine target

Motivation should primarily come from actual progress.

Examples:

- personal best
- weekly consistency
- comeback mission
- improvement milestone
- near-goal prompt
- revision recovery
- team contribution

Avoid punitive streak destruction.

Preferred message:

> Restart today.

Not:

> You lost your 37-day streak.

---

# 20. Udaan Reflect / Gita target

The Gita experience should support:

- Sanskrit text
- transliteration
- word meaning
- translation
- audio
- explanation
- practical student application
- reflection
- quiz

Student-specific programs may later include:

- Gita for exam stress
- Gita for discipline
- Gita for focus
- Gita for failure
- Gita for confidence
- Gita for anger
- Gita for decision-making
- Gita for purpose
- Gita for leadership

Formal ISKCON branding or "official" program naming should require appropriate authorization/partnership.

---

# 21. Competition architecture

Long-term hierarchy:

```text
Player
  ↓
Team
  ↓
Class / Batch
  ↓
School / Coaching Centre
  ↓
City
  ↓
District
  ↓
State
  ↓
Zone
  ↓
National
```

Possible leagues:

- JEE League
- NEET League
- Science League
- Maths League
- Bharat Quiz
- UPSC Current Affairs League
- Fitness Challenge
- Gita Knowledge League

Recognition should include multiple dimensions:

- Top Performer
- Most Improved
- Most Consistent
- Best Comeback
- Team Player
- Best Revision Discipline
- Personal Best award

This prevents the platform from becoming useful only to toppers.

---

# 22. Viral growth architecture

Udaan should generate achievements people naturally want to share.

Primary viral loops:

| Loop | Trigger | Output |
|---|---|---|
| Personal | personal best | share card |
| Friend | challenge | invite opponent |
| Team | team formation | recruit peers |
| League | ranking/event | institutional sharing |
| Creator | creator challenge | followers join |
| Mentor | teacher/coach | cohort joins |
| Championship | event result | public/social visibility |

Udaan should use Instagram, WhatsApp and other networks as distribution channels rather than trying to recreate an infinite social feed.

Examples of shareable achievements:

- Physics accuracy: 58% → 81%
- 30-day comeback
- school team victory
- first 5K
- 1,000 questions completed
- Gita chapter completed
- city league qualification

---

# 23. Multilingual Bharat target

All new content systems should become localization-first.

Preferred initial languages:

1. English
2. Hindi
3. Marathi

Then expand into:

- Gujarati
- Bengali
- Tamil
- Telugu
- Kannada
- Malayalam
- Odia
- Punjabi
- Assamese

Target capability:

Question: English  
Explanation: Hindi  
Audio: Marathi

Do not create separate duplicated content databases per language.

Use one content identity with localized variants.

---

# 24. Universal content model target

Future content should carry common metadata:

```text
content_id
arena
program
subject
chapter
concept
skill
difficulty
age_group
locale
title
body
answer
explanation
audio
source
reviewer
source_type
verification_status
safety_class
version
created_at
verified_at
tags
```

Source labels may include:

- official syllabus
- NCERT aligned
- PYQ
- faculty verified
- expert reviewed
- AI assisted
- last verified

---

# 25. Coach architecture

## Coach v1 — rules first

Do not begin with a fully generative coach.

Examples:

```text
IF concept_accuracy < 60%
AND attempts >= 10
→ recommend recovery drill

IF revision_due = true
→ assign revision mission

IF readiness = low
→ reduce intensity

IF inactive_days >= 2
→ assign comeback mission

IF near_personal_best = true
→ offer PB challenge
```

This allows product validation before expensive AI complexity.

## Coach v2

Add language generation and conversational explanations.

## Coach v3

Persistent planning across:

- academic goals
- readiness
- revision
- fitness
- habits
- schedule
- team/events

## Coach specialization

One intelligence core may surface as:

- JEE Coach
- NEET Coach
- UPSC Mentor
- School Coach
- Fitness Coach
- Focus Coach
- Lifestyle Coach
- Gita Guide

---

# 26. Admin target architecture

A national product will require a much stronger Admin.

Target areas:

- Player Management
- Content Studio
- Exam Manager
- Mission Manager
- Question/PYQ Manager
- Translation Manager
- Fitness Content
- Health Content
- Mind Content
- Gita / Reflect Content
- Coach Rules
- Competition Manager
- Teams
- Institutions
- Creator Management
- Analytics
- Safety
- Moderation
- Content Verification
- Release / Content Pack status
- Audit trail

---

# 27. Data architecture evolution

Current v0.6.5 can remain lightweight for current load.

However, the next product architecture should prepare for durable structured persistence.

Likely future entities:

```text
users
player_profiles
privacy_settings
goals
exam_targets
missions
mission_results
events
content
content_localizations
concepts
questions
attempts
mastery
revision_state
readiness
habits
fitness_activity
teams
team_members
matches
seasons
leaderboards
achievements
coach_recommendations
institutions
institution_memberships
creator_profiles
```

Important:

Do not prematurely rewrite the complete system into microservices.

First introduce domain boundaries and storage interfaces.

Move to stronger database/service infrastructure when product scale makes it necessary.

---

# 28. Privacy architecture

Suggested levels:

## Normal / social

- match scores
- public achievements
- team membership where appropriate

## Private

- readiness
- sleep
- fitness activity
- personal goals

## Highly private

- mental-wellbeing entries
- journal
- spiritual reflection
- sensitive personal notes

Users must clearly know what is:

- private
- parent-visible
- institution-visible
- team-visible
- public

---

# 29. Safety architecture

For minors:

- no calorie-target programs
- no weight-loss competitions
- no body-comparison leaderboard
- no extreme exercise
- no supplement recommendations
- no medical diagnosis
- no psychiatric diagnosis
- strict privacy defaults

For all users:

- wellbeing education must not replace professional healthcare
- serious health/safety situations require appropriate escalation
- spiritual participation remains optional
- no manipulative fear-based motivation
- no humiliation ranking

---

# 30. Commercial target

## Free

- Daily learning
- Daily missions
- Question of India
- Friend Challenge
- basic fitness
- health habits
- basic leagues
- basic Gita
- offline packs

## Pro

- complete exam pathways
- AI Coach
- adaptive revision
- advanced analytics
- full mock series
- personalized planning

## Institution

- school/coaching dashboards
- private leagues
- teacher tools
- institution analytics
- content assignment

## Partner

- expert programs
- temple/spiritual programs
- sports programs
- certifications
- events

Do not put basic spiritual scripture access behind an aggressive paywall.

---

# 31. KPI framework

Do not optimize page views or raw screen time.

## North-star metric

**Meaningful Training Days**

A day counts when a person completes a genuinely useful activity such as:

- study
- practice
- revision
- fitness
- focus
- reflection

## Activation

- first Player setup
- first Mission completed
- first Personal Best

## Retention

- D1
- D7
- D30
- D90
- 12-month identity retention

## Learning

- concept mastery improvement
- accuracy improvement
- revision completion
- mock performance

## Social / viral

- friend challenge rate
- invite conversion
- team creation rate
- team join rate
- organic team formation rate
- share-card conversion

## Behaviour

- mission completion
- comeback rate
- personal-best frequency
- readiness completion
- recovery adherence

## Coach

- recommendations shown
- accepted
- completed
- performance improvement after intervention

---

# 32. Release roadmap — next-generation Udaan

The proposed next release line is:

| Release | Scope | Status |
|---|---|---|
| v0.6.5 | current certified baseline | DONE |
| v0.7.0 | Player Profile + Goals + Event foundation | NEXT |
| v0.8.0 | Universal Mission Engine | PLANNED |
| v0.9.0 | Performance Graph + Personal Best | PLANNED |
| v0.10.0 | Daily Readiness | PLANNED |
| v0.11.0 | Udaan Fit v1 | PLANNED |
| v0.12.0 | Momentum + Habit Engine | PLANNED |
| v0.13.0 | Coach v1 | PLANNED |
| v0.14.0 | Friends + Teams | PLANNED |
| v0.15.0 | Match + League + Season Engine | PLANNED |
| v0.16.0 | Exam Arena foundation | PLANNED |
| v0.17.0 | First deep exam vertical | PLANNED |
| v0.18.0 | Udaan Mind | PLANNED |
| v0.19.0 | Reflect / Gita | PLANNED |
| v0.20.0 | Life / Lifestyle skills | PLANNED |
| v0.21.0 | multilingual expansion | PLANNED |
| v0.22.0 | institution pilot | PLANNED |
| v0.23.0+ | creator / national league / scale | FUTURE |
| v1.0.0 | integrated Udaan Core launch | TARGET |

Version numbers may move if urgent maintenance releases are required, but the dependency order should remain stable.

---

# 33. v0.7.0 — NEXT RELEASE

Recommended branch:

`feature/udaan-core-v0.7-player-foundation`

Goal:

Create the data/identity foundation that every later Arena can share.

Scope:

### Player Profile

- stable Player ID
- age/class band
- preferred language
- core interests
- selected Arena(s)

### Goals

- main goal
- exam target
- target date where applicable
- daily available time

### Privacy

- profile visibility
- parent/institution-sharing foundations
- sensitive-field classifications

### Arena Preferences

Examples:

- Learn
- Fit
- Mind
- Reflect

### Event Foundation

Introduce unified event envelope and append/store abstraction.

### Performance Shell

Create the UI/data structure for future dimensions without inventing fake scores.

### Today/Home shell

Move the product toward:

> What should I do now?

instead of a feature directory.

### Admin visibility

Admin can inspect:

- player count
- goals
- selected Arenas
- first mission/event activity later

### Storage abstraction preparation

Keep current storage operational, but avoid hard-coding the future Player model into one backend.

---

# 34. v0.8.0 — Mission Engine

Build:

- Mission entity
- mission assignment
- schedule
- completion
- result
- duration
- Arena type
- content references
- completion rules
- mission history
- Today's Mission UI

Initial mission types:

- Learn
- Revision
- Daily 9
- Fitness
- Focus
- Reflection

Do not build six separate task frameworks.

---

# 35. v0.9.0 — Performance Graph

Build event aggregation for:

- Knowledge
- Accuracy
- Speed
- Consistency
- Revision
- Mission completion

Introduce:

- Personal Best
- improvement delta
- weekly progress
- performance profile

No single total-student score.

---

# 36. v0.10.0 — Daily Readiness

Add:

- Sleep
- Energy
- Stress
- Focus
- Body

Use simple age-appropriate self-reporting.

Connect to Mission intensity.

Readiness remains non-medical.

---

# 37. v0.11.0 — Udaan Fit v1

Add:

- study-break movement
- mobility
- walking
- stretching
- yoga basics
- completion/consistency tracking

Integrate with Mission Engine and Performance Profile.

---

# 38. v0.12.0 — Momentum / Habits

Add:

- habits
- streak recovery
- comeback
- Personal Best prompts
- weekly goal
- milestones
- achievement cards

Build share cards from real progress.

---

# 39. v0.13.0 — Coach v1

Rules-based first.

Coach inputs:

- goals
- recent attempts
- mastery
- revision due
- readiness
- activity
- available time

Coach output:

- today's recommendations
- recovery recommendation
- next mission
- weekly summary

AI text generation may be layered later.

---

# 40. v0.14.0 — Friends / Teams

Build:

- friend relationship
- invite
- team create/join
- team membership
- team profile
- team challenge

Keep privacy and minors' safety central.

---

# 41. v0.15.0 — Leagues / Seasons

Build:

- match entity
- score rules
- season
- divisions
- leaderboard
- multiple awards
- improvement-based recognition
- team leaderboard

Initial launch can remain small/private before city/state scaling.

---

# 42. v0.16.0 — Exam Arena platform

Create generic exam structure:

```text
Exam
→ Subject
→ Chapter
→ Concept
→ Skill
→ Question
→ Attempt
→ Explanation
→ Revision
→ Assessment
```

Support:

- MCQ
- numerical
- reveal/info
- future written-answer structure

Prepare for:

- syllabus mapping
- source/provenance
- PYQ
- difficulty
- diagnostic
- revision scheduler

---

# 43. v0.17.0 — First deep exam vertical

Decision still required:

**NEET-first** or **JEE-first**.

Recommended pilot if speed of validation matters:

**NEET Biology first**, because it can validate:

- concept graph
- large question volume
- recall
- accuracy
- speed
- revision
- leagues

Then add NEET Physics/Chemistry and/or JEE on the same engine.

This recommendation is not yet a frozen decision.

---

# 44. v0.18.0 — Mind

First programs:

- Focus
- Procrastination
- Exam Pressure
- Confidence
- Failure Recovery

Use short interactive programs, not a passive content library.

---

# 45. v0.19.0 — Reflect / Gita

Add:

- Daily Gita
- shloka content type
- audio
- transliteration
- word meaning
- translation
- explanation
- reflection
- quiz
- mission integration

Spiritual profile remains opt-in.

---

# 46. 90-day development program

## Days 1–30 — Player + Mission foundations

Deliver:

- Player model
- Goals
- Arena preferences
- Privacy base
- Event Ledger
- Today/Home shell
- Mission Engine initial implementation
- first mission completion
- Performance Profile shell
- Admin player visibility

Success condition:

> Player → goal → mission → completion → recorded progress.

## Days 31–60 — Performance + Readiness + Fit

Deliver:

- Performance aggregation
- Personal Best
- Daily Readiness
- Fit v1
- Momentum basics
- weekly progress
- basic Coach rules

Success condition:

> Train → recover → improve.

## Days 61–90 — Social + first exam pilot

Deliver:

- friend challenge integration with Player
- teams
- weekly team challenge
- exam content graph
- diagnostic foundation
- first subject pilot
- shareable Personal Best cards

Success condition:

> Train → improve → compete → invite.

---

# 47. First 12-month roadmap

## Quarter 1

Core Player, Mission, Performance.

## Quarter 2

Readiness, Fit, Momentum, Coach.

## Quarter 3

Friends, Teams, Leagues.

## Quarter 4

Deep exam pilot + multilingual content expansion.

The first year should prove:

- users return for training;
- progress is measurable;
- team mechanics create organic invitations;
- health/fitness features support rather than distract from study;
- Coach recommendations change behaviour.

---

# 48. 10-year roadmap — 2026 to 2036

| Period | Strategic objective | Major outcome |
|---|---|---|
| 2026–27 | Build performance core | Player + Mission + Coach foundation |
| 2027–28 | Prove learning-as-sport | JEE / NEET verticals |
| 2028–29 | Build social graph | teams, school/coaching leagues |
| 2029–30 | Build national identity | Udaan Bharat Championship |
| 2030–31 | Deep adaptive Coach | persistent AI + performance graph |
| 2031–32 | Creator network | verified teachers/mentors/coaches |
| 2032–33 | Institution network | schools/coaching/temples |
| 2033–34 | Bharat scale | Indic languages, offline, voice |
| 2034–35 | Physical ecosystem | clubs, camps, events, finals |
| 2035–36 | International expansion | selected global markets |

---

# 49. National League long-term vision

Potential hierarchy:

`School → City → District → State → Zone → National`

Potential annual events:

- Udaan Science League
- Udaan Maths League
- Udaan JEE League
- Udaan NEET League
- Udaan Bharat Quiz
- Udaan Fitness Challenge
- Udaan Gita Knowledge League

Long-term final events can become physical + streamed cultural properties.

---

# 50. Institutional strategy

## Udaan School

- learning missions
- house competitions
- wellbeing challenges
- fitness
- teacher dashboards

## Udaan Coaching

- exam practice
- mocks
- batch analytics
- coaching leagues

## Udaan College

- competitive exams
- life skills
- fitness
- career-development missions

## Udaan Temple

- Gita
- shloka learning
- youth groups
- Sunday programs
- values programs

## Udaan Sports Academy

- fitness
- education support
- player learning

All remain one Udaan platform with different permissions/configuration.

---

# 51. Technical evolution rules

## Keep modular monolith first

Do not prematurely split into microservices.

Introduce modules:

```text
/core
    Player
    Goals
    Missions
    Events
    Performance
    Coach
    Privacy

/arenas
    Learn
    Fit
    Health
    Mind
    Life
    Reflect

/social
    Friends
    Teams
    Matches
    Leagues

/content
    Graph
    Localization
    Provenance

/offline
    Packs
    Sync

/admin
/analytics
```

Actual folder restructuring should be incremental and backward-compatible.

## Database adoption

Plan for future DB but do not force a rewrite solely for architectural fashion.

Use repository/storage interfaces so entities can move later.

## Redis

Use Redis for:

- hot state
- throttling
- ephemeral queues
- eventually leaderboards/caches

Avoid making Redis the only durable source of truth.

## Browser

Continue:

- PWA
- encrypted IndexedDB
- signed packs
- offline-first

---

# 52. Release discipline

Every release should pass:

1. repository safety
2. PHP syntax
3. JavaScript syntax
4. runtime smoke
5. package smoke
6. readiness contract
7. security-header checks
8. public-content read-only checks
9. release-specific contract checks
10. certified artifact publication
11. DigiOps candidate validation
12. post-deployment server verification

Do not mark a version production-complete from CI alone.

---

# 53. Definition of Done

A feature is not DONE merely because code is merged.

For product features:

- data model complete
- API complete
- UI complete
- authorization/privacy complete
- error states complete
- accessibility considered
- offline impact considered
- telemetry/events added
- tests/CI added
- Admin/operations visibility added
- documentation updated
- deployed
- server/browser smoke passed

---

# 54. Immediate operational backlog before v0.7.0

Status: **LIVE-PENDING / REPOSITORY HYGIENE**

Recommended order:

### D0 — Complete live v0.6.5 deployment verification

Verify DigiOps selects:

- source `93f965ab...`
- artifact `10676416116`

Then verify live:

- `/ready`
- `/health.php`
- version
- Redis state
- signing/distribution
- QOI
- offline
- room flow

### D1 — Confirm live security rotation

If not already completed:

`php ops/rotate-runtime-security.php --confirm-v061-rotation`

Use correct `UDAAN_BASE_PATH` when required.

### D2 — Repository cleanup

Remove merged/stale feature branches after confirming nothing unique remains.

Current non-main branches include older security, performance, ops, storage, release and DigiOps-fix branches.

### D3 — Main branch governance

Consider enabling appropriate branch protection/rules after the current release flow is stable:

- PR required
- security workflow required
- prevent direct accidental force-push
- preserve release automation

Do not enable rules that break the certified artifact workflow without testing.

### D4 — Documentation reconciliation

Update older README wording that still describes the v0.6.4 tarball/checksum upload model now that the DigiOps artifact is the direct staged application tree.

Then begin v0.7.0.

---

# 55. Pending strategic decisions

These are intentionally not frozen yet:

1. Public brand stays **Udaan** or evolves to **Udaan Bharat**.
2. First deep exam vertical: **NEET** or **JEE**.
3. First institutional pilot: school, coaching centre or both.
4. First spiritual institutional partner.
5. Whether fitness integrations initially remain self-reported or include device APIs.
6. Relational database timing.
7. AI-provider orchestration stack.
8. First three additional languages after English/Hindi/Marathi.
9. Parent-dashboard depth.
10. Public profile visibility for minors.

These decisions should be made when the dependent development stage arrives rather than blocking foundational work.

---

# 56. Things we should explicitly avoid

- rebuilding every Arena as a separate application;
- duplicating Mission systems;
- hard-coding Udaan to one AI model vendor;
- overbuilding AI before the Event/Performance foundation;
- making the app an infinite social feed;
- punitive streak mechanics;
- humiliating rankings;
- health diagnosis;
- unsafe diet/fitness programs;
- body-comparison leagues;
- compulsory spiritual content;
- ranking faith/devotion;
- pay-to-win competition;
- exposing sensitive student wellbeing data;
- using raw screen time as success.

---

# 57. Development priority map

## DONE

- trusted card bank
- signed packs
- offline vault
- Daily 9
- Friend Challenge
- QOI
- Mystery Card
- room/journey system
- encrypted temporary state
- runtime security recovery
- trust epoch
- rate limiting
- CSRF hardening
- performance cache
- production readiness
- certified release
- DigiOps artifact contract
- DigiOps deployable artifact layout

## LIVE-PENDING

- artifact `10676416116` production deployment confirmation
- exact live source confirmation
- live readiness/health verification
- live security rotation confirmation
- full production smoke

## NEXT

- Player Profile
- Goals
- Arena Preferences
- Event Ledger
- Privacy foundation
- Today/Home shell

## NEAR TERM

- Mission Engine
- Performance Graph
- Personal Best
- Daily Readiness
- Fit v1
- Momentum
- Coach v1

## MEDIUM TERM

- Friends
- Teams
- Matches
- Leagues
- Exam Arena
- first exam vertical
- Mind
- Reflect/Gita
- Life skills
- multilingual

## LONG TERM

- national leagues
- creators
- institution network
- voice
- deep offline Bharat
- physical clubs/events
- international Udaan

---

# 58. Recommended next development sequence

The next code release should not begin with JEE/NEET content or a large AI assistant.

It should begin with:

> **v0.7.0 — Udaan Player Foundation**

Recommended development order:

```text
Player
  ↓
Goals
  ↓
Privacy
  ↓
Arena Preferences
  ↓
Event Ledger
  ↓
Today/Home
  ↓
Mission Engine
  ↓
Performance Graph
  ↓
Coach
  ↓
Fit / Health / Mind / Reflect
  ↓
Social / Teams
  ↓
Exam Arena
  ↓
National Network
```

This order ensures every future product capability attaches to a stable core rather than becoming another isolated feature.

---

# 59. Final master direction

Udaan should evolve through three stages.

## Stage A — Product

A powerful daily learning and performance app.

## Stage B — Network

Friends, teams, schools, coaching centres, institutions and leagues.

## Stage C — Culture

A national movement around:

> **Train every day. Improve every day.**

The long-term aim is not to beat AI at answering questions.

The aim is to use AI to make a much stronger human system for:

- learning;
- performance;
- health;
- fitness;
- discipline;
- motivation;
- community;
- purpose;
- character.

The target end-state is:

> **Udaan becomes the place where people train their knowledge, mind, body, habits and character throughout their life.**

---

# 60. Working rule for all future Udaan sessions

Before starting a major Udaan feature:

1. Check this document.
2. Confirm current `main` and deployed version.
3. Identify which roadmap stage the feature belongs to.
4. Reuse the shared Player / Mission / Event / Performance architecture.
5. Avoid duplicate domain systems.
6. Add privacy and safety at design time.
7. Add tests and release gates before merge.
8. Deploy only certified artifacts.
9. Verify the real server after deployment.
10. Update this knowledge base when a planned item changes status.

This file should evolve with the codebase and remain Udaan's persistent development source of truth.
