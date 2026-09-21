# Udaan Card Factory — Knowledge & Data Contract

## 1. Purpose of this file

This file defines the authoritative content-bank contract for the Udaan Live v0.4.x swipe-learning application.

The Custom GPT must use this file when converting source material into production cards.

The current application reads:

`data/content/cards.json`

The bank is a JSON object containing a `cards` array. Each card is selected by the randomized no-repeat journey engine.

---

# 2. Current master-bank structure

The current seed bank has this outer structure:

```json
{
  "version": "2026.09.seed1",
  "count": 432,
  "pillars": 9,
  "cards_per_pillar": 48,
  "cards": []
}
```

The live application primarily relies on the actual `cards` array. As the bank grows beyond the original equal 48-per-pillar seed, pillar totals may become uneven.

New generated output should normally be produced as a JSON **array of new card objects** for append/import, rather than rewriting the complete bank.

---

# 3. Nine authoritative pillars

Use these exact values.

| pillar | pillar_name | label |
|---|---|---|
| `think` | `Think & Reason` | `THINK & REASON` |
| `science` | `Science & Discovery` | `SCIENCE & DISCOVERY` |
| `roots` | `Roots & India` | `ROOTS & INDIA` |
| `math` | `Maths & Logic` | `MATHS & LOGIC` |
| `build` | `Build & Create` | `BUILD & CREATE` |
| `future` | `Future & Careers` | `FUTURE & CAREERS` |
| `values` | `Values & Character` | `VALUES & CHARACTER` |
| `world` | `India & World` | `INDIA & WORLD` |
| `life` | `Life Skills & Responsibility` | `LIFE SKILLS` |

Never invent a tenth pillar.

## Pillar guidance

### `think` — Think & Reason

Use for:

- evidence vs opinion
- source reliability
- assumptions
- cause and effect
- probability
- counterexamples
- bias awareness
- trade-offs
- estimation as reasoning
- systems thinking
- better questions
- logical interpretation

### `science` — Science & Discovery

Use for:

- physics
- chemistry
- biology
- earth science
- astronomy
- environment science
- scientific method
- scientific observation
- technology explained through science

### `roots` — Roots & India

Use for:

- Indian history
- documented knowledge traditions
- mathematics/science history in India
- literature
- languages
- architecture
- art
- music
- philosophy
- institutions
- historical people
- crafts and ecological traditions

Maintain the evidence distinction defined in the Instructions.

### `math` — Maths & Logic

Use for:

- arithmetic
- number sense
- algebra
- geometry
- probability
- ratios
- patterns
- estimation
- mathematical puzzles
- logic
- data interpretation

### `build` — Build & Create

Use for:

- coding
- design
- making
- engineering thinking
- prototyping
- decomposition
- iteration
- creativity
- experiments
- project planning

### `future` — Future & Careers

Use for:

- careers
- industries
- skills
- career pathways
- emerging technology roles
- education pathways
- problem areas a learner may wish to solve

### `values` — Values & Character

Use for:

- integrity
- gratitude
- courage
- compassion
- humility
- perseverance
- discipline
- respect
- service
- fairness
- self-control

### `world` — India & World

Use for:

- geography
- public institutions
- constitution/civics at an educational level
- cities
- transport
- trade
- food systems
- public health
- languages and regions
- environment
- global interdependence
- current affairs converted into learning context

Avoid partisan political persuasion.

### `life` — Life Skills & Responsibility

Use for:

- digital security
- media literacy
- basic budgeting
- time management
- clear communication
- teamwork
- conflict resolution
- goal setting
- safe emergency awareness
- sleep
- nutrition basics
- movement/healthy routines

Avoid individualized medical diagnosis/treatment or high-risk instructions.

---

# 4. Supported card kinds

The current UI/API supports exactly:

- `quiz`
- `reveal`
- `choice`
- `action`

No other value may be placed in `kind` for production output.

---

# 5. Core fields required on every card

Every card must contain:

```json
{
  "pillar": "science",
  "pillar_name": "Science & Discovery",
  "label": "SCIENCE & DISCOVERY",
  "topic": "orbit",
  "topic_name": "Orbits and gravity",
  "min_grade": 6,
  "max_grade": 10,
  "language": "en",
  "active": true,
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "kind": "quiz",
  "title": "...",
  "subtitle": "..."
}
```

## Field rules

### `pillar`

One exact key from the nine-pillar table.

### `pillar_name`

Must exactly match the selected pillar.

### `label`

Must exactly match the selected pillar's uppercase label.

### `topic`

Stable concept slug.

Rules:

- lowercase ASCII
- letters, digits, underscore only
- preferably 2–32 characters
- represent the concept, not the interaction wording

Good:

- `orbit`
- `source_reliability`
- `nalanda`
- `compound_interest`
- `passwords`

Bad:

- `question_12`
- `interesting_fact`
- `science-card-new`

### `topic_name`

Short human-readable concept name.

### `min_grade`, `max_grade`

Integers 1–12.

`min_grade <= max_grade`.

### `language`

Default: `en`.

### `active`

Production cards: `true`.

Do not output uncertain content as active production cards.

### `id`

Globally unique standard UUID/GUID string.

The no-repeat engine uses this exact ID.

### `kind`

One of the four supported kinds.

### `title`

Main learner-facing prompt/hook.

### `subtitle`

Short instruction/context.

---

# 6. `quiz` schema

Required additional fields:

```json
{
  "options": [
    {"id":"a","label":"..."},
    {"id":"b","label":"..."},
    {"id":"c","label":"..."}
  ],
  "correct": "a",
  "explain": "..."
}
```

Rules:

- 3–5 options
- option IDs unique within card
- prefer short stable IDs such as `a`, `b`, `c`, `d`, `e`
- exactly one correct option
- `correct` must equal an option ID
- explanation must teach why the answer is correct
- avoid trick wording
- avoid multiple defensible answers

Recommended subtitle:

`Choose quickly, then learn from the explanation.`

Example:

```json
{
  "pillar":"science",
  "pillar_name":"Science & Discovery",
  "label":"SCIENCE & DISCOVERY",
  "topic":"orbit",
  "topic_name":"Orbits and gravity",
  "min_grade":6,
  "max_grade":10,
  "language":"en",
  "active":true,
  "id":"550e8400-e29b-41d4-a716-446655440000",
  "kind":"quiz",
  "title":"What keeps a satellite in orbit around Earth?",
  "subtitle":"Choose quickly, then learn from the explanation.",
  "options":[
    {"id":"a","label":"Forward motion plus gravity"},
    {"id":"b","label":"No gravity at all"},
    {"id":"c","label":"Air holding it up"}
  ],
  "correct":"a",
  "explain":"A satellite is continually falling under gravity while moving sideways fast enough to keep missing Earth."
}
```

---

# 7. `reveal` schema

Required additional field:

```json
{
  "reveal": "..."
}
```

Recommended subtitle:

`Tap to reveal one useful idea.`

Example:

```json
{
  "pillar":"think",
  "pillar_name":"Think & Reason",
  "label":"THINK & REASON",
  "topic":"evidence",
  "topic_name":"Evidence vs opinion",
  "min_grade":6,
  "max_grade":12,
  "language":"en",
  "active":true,
  "id":"6ba7b810-9dad-41d1-80b4-00c04fd430c8",
  "kind":"reveal",
  "title":"What makes evidence different from an opinion?",
  "subtitle":"Tap to reveal one useful idea.",
  "reveal":"Evidence can be checked against observations, records or measurements; an opinion expresses a judgement or preference."
}
```

---

# 8. `choice` schema

Required additional field:

```json
{
  "options": [
    {"id":"a","label":"..."},
    {"id":"b","label":"..."}
  ]
}
```

There is no `correct` field unless the card should actually be a quiz.

Use choice for:

- reflection
- preference
- interest
- open decision
- values scenario without one universally correct answer

## Future presenter aggregation convention

For a Future & Careers interest card that should contribute to the presenter future-interest signal, use:

```json
{
  "pillar":"future",
  "kind":"choice",
  "aggregate":"future_interest",
  "options":[
    {"id":"strong","label":"Yes — this strongly interests me"},
    {"id":"curious","label":"Curious — show me what people do"},
    {"id":"try","label":"I’d try a small activity first"},
    {"id":"unsure","label":"Not sure yet"},
    {"id":"later","label":"Not for me right now"}
  ]
}
```

For v0.6.0 aggregated future-interest cards, prefer the five IDs above. The server aggregates the topic for `strong`, `curious`, or `try`. Legacy `yes`/`maybe` IDs remain backward-compatible.

Choice cards may contain **3–8 options** when the extra choices are genuinely useful.

---

# 9. `action` schema

Required additional field:

```json
{
  "options": [
    {"id":"today","label":"I’ll try it today"},
    {"id":"already","label":"I already do this"},
    {"id":"learn","label":"I want to learn more"}
  ]
}
```

Recommended subtitle:

`A tiny action linked to this idea.`

Actions should be achievable, safe and useful. The live selector permits **maximum one action card per learning session**, so generate action cards sparingly and prioritize quiz/reveal/choice content.

---

# 10. Optional `meta` object

The current UI ignores unknown fields, so the following metadata may safely be stored for future governance and imports:

```json
"meta": {
  "source_title": "",
  "source_section": "",
  "source_reference": "",
  "source_url": "",
  "concept_key": "",
  "educational_value": 92,
  "confidence": 0.98,
  "evidence_class": "modern_science",
  "time_sensitive": false,
  "expires_at": null,
  "generated_from_source": true
}
```

## `evidence_class` recommended values

- `modern_science`
- `historical_documentation`
- `traditional_belief`
- `mythology_or_legend`
- `interpretation`
- `official_notice`
- `general_knowledge`
- `not_applicable`

The evidence classification is metadata. Learner-facing wording must still make important distinctions clear where needed.

## Confidence

Use 0.00–1.00.

Production factual cards should normally be >= 0.90.

## Educational value

Use integer 1–100.

Production cards should normally be >= 75.

---

# 11. No-repeat engine behaviour

Udaan stores a pseudonymous learning identity and remembers:

- previously seen card GUIDs
- previously seen `pillar:topic` combinations

Selection priority is approximately:

1. unseen card
2. unseen topic
3. balanced pillars
4. balanced card kinds
5. smart review only after unseen content is exhausted

Therefore:

- card GUIDs must remain stable once published
- topic slugs must be semantically honest
- duplicate concepts must not be disguised with new topic slugs
- new content naturally receives priority for returning learners

---

# 12. Concept-family strategy

A useful way to expand the bank is:

PILLAR -> TOPIC -> INTERACTION VARIANTS

Example:

```text
Science & Discovery
  -> orbit
     -> quiz
     -> reveal
     -> choice (only if meaningful)
     -> action (only if meaningful)
```

Do not force all four kinds for every topic.

A card variant is worthwhile only if it adds a different educational interaction.

---

# 13. Content style for 2–3 minute journeys

Journeys contain 21–36 cards, often 27.

Most cards should take roughly 3–10 seconds.

Use:

- one idea per card
- short titles
- short options
- immediate feedback
- memorable explanations
- useful actions

Avoid:

- long paragraphs
- multi-part exam questions
- dense tables
- excessive dates/names without context
- trivia that teaches nothing

---

# 14. Editorial safeguards

## Student safety

Do not generate:

- graphic violence
- sexual content
- dangerous challenges
- instructions for wrongdoing
- individualized medical treatment
- gambling
- drug use instructions
- self-harm content

## Political/current-affairs neutrality

Current affairs can be converted into educational context, but avoid partisan advocacy or attempts to shape a student's political beliefs.

Focus on:

- what happened
- relevant factual context
- institutions/processes
- what can be learned

## Traditional values

Values should strengthen responsibility, integrity, service, curiosity and respect without shaming, coercion or ideological messaging.

---

# 15. Direct-import output contract

Default Custom GPT response for production card generation must be a JSON array:

```json
[
  { "...card 1...": "..." },
  { "...card 2...": "..." }
]
```

No code fences and no prose in IMPORT JSON MODE.

To manually append to the existing bank:

1. back up `data/content/cards.json`
2. locate the existing top-level `"cards":[ ... ]`
3. insert the new card objects into that array, separated by commas
4. keep every `id` unique
5. ensure final JSON is valid
6. update the outer `version` string if desired

The application health endpoint calculates the actual total from the `cards` array, so the journey engine is driven by the cards actually present.

For large-scale production, prefer a validated import utility rather than repeated manual editing.

---

# 16. Recommended user prompt for the Custom GPT

```text
IMPORT JSON MODE

Convert the attached/pasted source into Udaan production cards.

Target grades: 6–12 unless the source supports a narrower range.
Use only facts supported by the supplied source.
Use only the 9 Udaan pillars and 4 supported kinds.
Avoid semantic duplicates.
Generate as many high-quality cards as the source genuinely supports.
Preserve source traceability in meta.
Return only a valid JSON array with no Markdown fences or commentary.
```

If an existing bank/export is also attached:

```text
Compare against EXISTING BANK first.
Return only genuinely new concepts/cards.
Do not change existing IDs.
```

---

# 17. Production checklist

Before a generated batch is accepted:

- [ ] valid JSON
- [ ] UUID unique for every card
- [ ] supported pillar only
- [ ] exact pillar_name and label
- [ ] supported kind only
- [ ] topic slug valid
- [ ] age range sensible
- [ ] source supports claim
- [ ] quiz has one correct answer
- [ ] reveal contains useful teaching
- [ ] choice has no fake correct answer
- [ ] future aggregator uses yes/maybe/later IDs
- [ ] action is safe and practical
- [ ] no semantic duplicate
- [ ] concise mobile wording
- [ ] educational_value >= 75
- [ ] factual confidence >= 0.90
