# Learning Reflection Rail — Component Blueprint

**Introduced:** Udaan Live v0.5.1  
**Default duration:** 3 seconds  
**Config:** `config.php → card_reflection_seconds`

## Purpose

The rail adapts the supplied minimal blurred-rainbow footer concept into Udaan's education flow. It is used as a deliberate micro-pause after a learner commits an answer or reveals an explanation.

It must never be used to fake slow loading. Network actions should start immediately. The visual layer may continue while the request is in progress, but it must not delay server work unnecessarily.

## Journey behavior

```text
Learner answers
→ server/local save
→ answer locks
→ explanation/feedback is visible
→ rail starts at 100%
→ 3 → 2 → 1 second status
→ rail reaches 0%
→ next card advances
```

During the interval, swipe/keyboard advancement is disabled. This avoids accidental skipping of educational feedback.

For a 27-card journey, 3 seconds per fresh response contributes roughly 81 seconds of reflection time.

## Implementation

- CSS: `assets/css/app.css` (`.learning-progress*`)
- JS: `assets/js/learning-progress.js`
- Journey integration: `assets/js/journey.js`
- PWA cache: `sw.js`
- Server config: `config.php`
- Health audit: `health.php`

Public JS API:

```js
UdaanProgress.start(3000, 'Working · please wait');
await UdaanProgress.wait(3000, 'Reflect · next card');
UdaanProgress.stop();
```

Forms can opt in without custom JavaScript:

```html
<form data-progress-submit data-progress-label="Verifying card batch">
```

## Visual contract

Spectrum:

- red
- orange
- yellow
- green
- teal
- blue
- violet

The rail is only 2px high and uses blur/glow outside the line. It must remain a restrained accent, not become a dominant rainbow theme across the application.

## Theme behavior

Light mode:

- warm translucent status pill;
- low-contrast border;
- subtle shadow.

Dark mode / Journey:

- near-black translucent status pill;
- white countdown text;
- slightly stronger spectrum glow for contrast.

## Accessibility

When `prefers-reduced-motion: reduce` is active:

- keep the useful countdown duration;
- remove animated width transition;
- reduce/disable blur animation;
- do not rely on color alone to communicate progress.

## Performance budget

- no external library;
- no image asset;
- no SVG animation dependency;
- local JS only;
- tiny runtime DOM insertion;
- cached by PWA service worker.

## Regression checklist

Every release that touches Journey/UI must confirm:

- [ ] rail renders at bottom safe area;
- [ ] countdown is legible in light mode;
- [ ] countdown is legible in dark/journey mode;
- [ ] feedback remains visible for configured interval;
- [ ] swipe cannot bypass active reflection;
- [ ] restored/answered cards are not unnecessarily delayed;
- [ ] offline-save path still advances after reflection;
- [ ] service worker caches `learning-progress.js`;
- [ ] `prefers-reduced-motion` remains respected.
