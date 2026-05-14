# Trigger Sample Variables — Frontend Guide

## What changed

The `condition-variables` API (`POST /zaplane/v1/condition-variables`) now returns variables for trigger nodes even when the user has never done a real test run. These are populated from a static sample defined in each integration's PHP class.

Each node entry in `data[]` now carries an `is_sample` boolean:

```json
{
  "status": "success",
  "data": [
    {
      "node_id": 1,
      "node_name": "Contact Created",
      "node_event": "contact_created",
      "variables": [
        { "key": "first_name", "type": "string", "sample": "John" },
        { "key": "email",      "type": "email",  "sample": "john.doe@example.com" }
      ],
      "is_sample": true
    }
  ],
  "context": { ... }
}
```

| `is_sample` | Meaning |
|-------------|---------|
| `false` | Variables came from a real test run — values are accurate |
| `true`  | Variables came from static sample data — structure is correct, values are placeholders |

---

## Where the data flows

```
POST condition-variables
        │
        ▼
Redux: conditionVariables.fulfilled
        │
        ▼
state.workflowVariables = action.payload
        │
        ├── workflowVariables.data     → passed as `variables` prop
        └── workflowVariables.context  → passed as `variableContext` prop
```

Both props end up in:
- `ActionFieldRenderer` → `VariableEditor` (lines 78-79)
- `ConditionGroupField` → `VariableEditor`
- `VariablePopover` receives `data` (= `variables`) and `contextData` (= `variableContext`)

---

## What you need to do on the frontend

### 1. Show a "Sample" indicator on trigger accordion headers

In `VariablePopover.js`, the `renderVariableAccordion` function renders one `<Disclosure>` per node.  
When `item.is_sample === true`, add a small badge next to the node name so the user knows the data is placeholder.

```jsx
// VariablePopover.js — inside renderVariableAccordion, in the DisclosureButton

<DisclosureButton className="...">
  <span className="zaplane-label flex-1 font-medium">
    {source === "context" ? item.label : item.node_name}
  </span>

  {/* ADD THIS */}
  {source === "app" && item.is_sample && (
    <span className="mr-2 text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">
      Sample
    </span>
  )}

  <LuChevronDown className={`h-5 w-5 ...`} />
</DisclosureButton>
```

### 2. Show a "Run test to get real data" hint inside the panel (optional but recommended)

When a node has `is_sample: true` and no `variables` yet (empty array), the popover already shows "No fields available". But when sample variables exist, add a subtle hint below the variable list:

```jsx
// Inside DisclosurePanel, after the variable list

{source === "app" && item.is_sample && item.variables?.length > 0 && (
  <p className="px-4 pb-2 text-xs text-gray-400 italic">
    {__("Sample data — run a test to see real values", "zaplane")}
  </p>
)}
```

### 3. Tooltip on individual sample variables (optional)

If you want finer control, each variable object already has a `sample` field showing the placeholder value (e.g. `"John"`). No extra work needed — this already renders in the popover:

```jsx
<span className="text-gray-500 truncate ...">
  {" : "}
  {__(v.sample, "zaplane")}   // ← already shows "John", "john.doe@example.com", etc.
</span>
```

---

## Which triggers support sample data (testable via UI)

A trigger is "testable" if it fires from a real WordPress action the user can trigger manually in the browser. The `is_sample` flag tells you whether sample data was used as a fallback.

### GemCRM triggers

| Trigger | Event slug | Sample data? | Can user fire it manually? |
|---------|-----------|-------------|---------------------------|
| Contact Created | `contact_created` | Yes | No — fires on CRM contact creation; needs actual CRM action |
| Tag Added to Contact | `contact_tag_attached` | Yes | No — fires on tag attachment |
| Tag Removed from Contact | `contact_tag_removed` | Yes | No |
| Contact Added to List | `contact_list_attached` | Yes | No |
| Contact Removed from List | `contact_list_removed` | Yes | No |

**All GemCRM triggers are hook-only** — users cannot fire them from the browser. Sample data is the only way to show variables until a real workflow run happens.

### Academy LMS triggers

| Trigger | Event slug | Sample data? | Can user fire it manually? |
|---------|-----------|-------------|---------------------------|
| User Enrolled in Course | `user_enroll_course` | Yes | Yes — enroll as a test user in a course |
| User Completed Course | `course_complete` | Yes | Yes — complete all lessons as a test user |
| User Completed Lesson | `lesson_complete` | Yes | Yes — mark a lesson complete as test user |
| User Attempted a Quiz | `academy_quiz_course_attempt` | Yes | Yes — submit a quiz attempt |
| User Achieved Target on Quiz | `quiz_target` | Yes | Yes — submit a quiz meeting the target score |

**Academy triggers are testable** — the user can manually fire them through the frontend LMS. Once fired with a real workflow run, `is_sample` becomes `false` and real values are shown.

---

## Adding sample data for a new integration (for backend devs)

In your integration PHP class, add:

```php
public static function get_trigger_sample_output( string $trigger ): array {
    $samples = [
        'my_trigger_slug' => [
            'field_one' => 'Sample String',
            'field_two' => 42,
            'field_three' => 'user@example.com',
        ],
    ];
    return $samples[ $trigger ] ?? [];
}
```

Keys **must** match what `resolve_trigger()` returns for that trigger. The API passes this through `VariableExtractor::extract()` — nested arrays automatically become dot-notation variables (e.g. `creator.name`).

Once added, the trigger's variables appear immediately in the condition builder with `is_sample: true`, even before any test run.
