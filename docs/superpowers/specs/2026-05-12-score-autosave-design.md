# Score Autosave Design

## Goal
Fix score editing so fast typing does not trigger conflicting saves or overwrite newer values when the API responds slowly.

## Problem
Current score updates are sent directly from the input change path. When a user types quickly, intermediate values like `1` are sent before the intended final value like `12`. If the server takes a second or more to answer, older responses can arrive late and overwrite newer local input.

## Proposed Behavior
Use a local draft model for score cells and separate typing from persistence.

1. User input updates local cell state immediately.
2. Changes are marked dirty and scheduled for autosave.
3. Autosave is batched so multiple rapid edits are sent together.
4. In-flight requests do not block typing.
5. Older responses never overwrite newer edits.
6. Blur or Enter flushes pending changes immediately.

## Architecture

### Local draft state
`ScoreTable` keeps the current edited value locally for each cell. The store does not treat every keystroke as a committed server state.

### Autosave queue
Queue score changes at the game level, not per individual keystroke. The queue should:
- collect recent changes into a batch
- debounce short bursts of typing
- keep pending changes while a request is in flight
- send the latest known value for each cell

### Latest-wins protection
Each cell needs a monotonically increasing revision or timestamp.

When a save is sent:
- attach the revision for that cell
- ignore stale responses
- only apply a response if it matches the latest known revision

## API Shape
Prefer a bulk endpoint over one request per cell.

Suggested request payload:
```json
{
  "updates": [
    { "teamId": "t1", "roundId": "r1", "score": 12, "revision": 3 },
    { "teamId": "t2", "roundId": "r1", "score": 8, "revision": 1 }
  ]
}
```

Suggested response:
- updated game snapshot, or
- list of applied updates plus the refreshed game state

## UI States
Each edited score cell should expose a simple save state:
- `dirty`
- `saving`
- `saved`
- `error`

The UI should not visually jump back to an older value while a request is still pending.

## Error Handling
- If a save fails, keep the local draft value.
- Mark the affected cell or game as error.
- Retry on the next edit or blur.
- Do not discard newer pending edits because an older request failed.

## Files Likely Affected
- `app/components/ScoreTable.vue`
- `app/stores/quiz.ts`
- `server/api/games/[id]/scores*.ts` or a new bulk score route
- `server/utils/quiz.ts`

## Testing
- Fast typing into the same score cell should save only the final value.
- Two rapid edits in different cells should batch into a single request when possible.
- A slow response must not overwrite a newer local edit.
- Blur should flush pending changes immediately.

## Out of Scope
- Changing score normalization rules.
- Changing team/round editing.
- Redesigning the scoreboard layout.
