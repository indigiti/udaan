# Udaan — Future Development Plan after v0.6.0

Baseline entering future work: **v0.6.0 — Distributed Daily Learning Foundation**.

Architectural principle: **Centralize trust, decentralize distribution.**

Product principle: **Make 2–5 minutes in Udaan so useful, interesting and satisfying that a learner voluntarily comes back tomorrow.**

## Current durable boundary

v0.6.0 intentionally remains database-free for prototype / approval / controlled-school testing.

Current persistence:

- approved card bank: JSON;
- signed content packs + manifest: JSON + SHA-256 + Ed25519;
- temporary rooms: Redis or encrypted JSON;
- pseudonymous no-repeat history: Redis or encrypted JSON;
- browser device bank/current sessions: AES-GCM IndexedDB;
- offline event outbox: encrypted JSONL;
- aggregate response counters: encrypted JSON;
- no MariaDB required.

MariaDB is introduced when the platform needs durable production identity, multi-device merge, schools/teachers, permanent badge/streak history, moderation/RBAC, analytics, consent, audit and long-term results.

---

## v0.6.1 — Opportunity & Future Radar

Goal: make Udaan useful beyond quizzes so students feel they cannot afford to miss relevant opportunities.

Build:

- opportunity cards: competitions, scholarships, workshops, internships, admissions, events;
- class/grade, location and interest filters;
- `Why recommended?` explanation;
- deadline emphasis;
- Save / Remind / Official Source;
- verified-source requirement;
- offline cache of saved opportunities;
- future-pathway micro-cards;
- simple interest signals from existing Future cards.

No DB requirement for controlled prototype; use approved JSON + encrypted local state. Do not build complex permanent reminder infrastructure until DB/queue adoption.

---

## v0.6.2 — Explain & Learn Deeper

Goal: AI helps after the learner thinks.

After a wrong/uncertain response offer:

- Explain simply;
- Give another example;
- Ask an easier one;
- Ask a harder one;
- Why does this matter?

Guardrail:

> Student thinks first. AI helps second.

AI responses must be source-grounded for factual card content and must not silently replace the approved canonical answer.

---

## v0.6.3 — Student Contribution Network

Goal: learners become contributors, not only consumers.

Allow:

- suggest question;
- suggest correction;
- suggest source;
- suggest explanation;
- suggest translation.

Pipeline:

```text
Submission
  ↓
Candidate card
  ↓
AI structuring
  ↓
Source / duplicate checks
  ↓
Community review signal
  ↓
Trusted human reviewer
  ↓
Approved
  ↓
Udaan Ed25519 signature
  ↓
Verified distribution network
```

Community voting is evidence, never the final source of factual truth.

---

## v0.6.4 — Teacher Classroom Mode

Goal: teachers become the first large-scale distribution channel.

Build:

- 3-minute class session;
- Daily 9 classroom mode;
- subject/pillar challenge;
- one QR, no whole-class account setup;
- presenter participation analytics;
- anonymous correctness / choice percentages;
- teacher re-run / duplicate session;
- safe downloadable session summary.

No public student profile required.

---

## v0.6.5 — School Participation Network

Goal: school-vs-school engagement without turning Udaan into an exam leaderboard.

Recognize:

- participation;
- consistency;
- Daily 9 completion;
- exploration breadth;
- approved contribution;
- optional correctness as only one factor.

Do not rank schools solely by marks.

Introduce multiple recognition paths:

- Top Learner;
- Curiosity Champion;
- Science Explorer;
- Maths Streak;
- Roots Explorer;
- Future Explorer;
- Consistency Champion;
- Top Contributor;
- Helpful Reviewer.

Persistent school/learner history is a strong signal that MariaDB adoption is approaching.

---

## v0.6.6 — P2P Signed Pack Transfer

Goal: activate decentralized distribution underneath the already-signed content foundation.

Peers may exchange only:

- approved signed card packs;
- manifests;
- pack hashes;
- versions.

Peers must never exchange:

- WhatsApp numbers;
- learner hashes;
- persistent device GUIDs;
- answers;
- learning history;
- private interests;
- commitments.

Transport plan:

- WebRTC data channels;
- short-lived random peer IDs;
- small 3–8 peer swarms;
- origin fallback;
- STUN and optional TURN;
- verify SHA-256 + Ed25519 before IndexedDB acceptance;
- reject unsigned, modified or revoked packs.

The private content-signing key remains only at the Udaan trust core.

---

## v0.6.7 — School Edge Node

Goal: make weak-network schools capable of serving large local populations cheaply.

Possible hosts:

- Raspberry Pi;
- old school PC;
- teacher laptop;
- school server.

Edge node stores approved signed packs and serves them over local Wi-Fi. It has distribution authority only, never content-signing authority.

---

## v0.7.0 — Production Data Platform

Introduce MariaDB + Redis + Supervisor/queues before broad production rollout.

MariaDB becomes authoritative for:

- learner accounts / pseudonymous identities;
- multiple devices per learner;
- permanent seen/answer history;
- streaks and badges;
- schools/classes/teachers;
- challenge history;
- opportunities / saves / reminders;
- source registry;
- content versions;
- contributor/reviewer workflow;
- permanent aggregate analytics;
- audit trail;
- consent/privacy state;
- notifications;
- reports.

Current JSONL events migrate idempotently using `event_id`.

Signed/P2P content distribution remains in place after DB adoption.

---

## v0.7.x — Trusted Contributor / Reviewer Reputation

Trust must come from:

- accuracy history;
- source quality;
- reviewer agreement;
- identity/role verification where appropriate;
- human moderation.

Never base educational authority on followers or popularity.

---

## v0.8.x — National Learning Network

When enough meaningful learners are active:

- Question of India at national scale;
- safe class/grade aggregate trends;
- national curiosity signals;
- school participation network;
- Future-interest trend signals;
- opportunity discovery network.

Only safe aggregates should be public.

Avoid public personal learning profiles, open DMs, unrestricted comments and precise location exposure.

---

# Growth Metric

North star: **Weekly Meaningful Learners (WML)**.

Definition:

> A learner who completes at least 3 Udaan learning sessions during a week.

Track after durable analytics exist:

- first-card start rate;
- Daily 9 completion rate;
- Day-2 return;
- Day-7 return;
- 3+ sessions/week;
- voluntary extra cards;
- Mystery Card opens;
- friend challenges sent;
- challenge join conversion;
- Question of India participation;
- teacher repeat use;
- opportunity opens / saves;
- learner contributions;
- offline sessions;
- sync success/failure.

Do **not** optimize for maximum screen time.
