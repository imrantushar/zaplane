# Merge tags

A workflow's node configuration can contain `{{ … }}` tags. Before a node runs,
every one of its configured values is resolved against the data the run has
gathered so far — the trigger's payload and each earlier step's output.

```
{{trigger.email}}          a path into the run's data
{{1.first_name}}           step 1's output, addressed by its number
{{1.order_id + 1}}         arithmetic
{{(n + 1) * 2}}            parentheses group
{{n >= 5 && flag}}         comparison and logic
Hi {{1.first_name}}!       a tag inside a sentence
```

A tag standing alone keeps the value's own type — an integer stays an integer.
A tag inside a sentence is turned into text; an array becomes a comma-separated
list, and a missing value becomes an empty string rather than the word `null`.

A path that does not exist resolves to nothing. That is deliberate and long
standing: a merge tag nobody filled in means "nothing here", and a run must not
stop over it. Arithmetic on nothing is also nothing, so a missing tag cannot
quietly become a `0` in a total.

## The grammar is the whole language

Paths, numbers, quoted strings, `true`, `false`, `null`, the operators
`+ - * / %`, `== != === !== < <= > >=`, `&& || !`, and parentheses.

**There is no function call.** `{{a.b(c.d)}}` resolves to nothing.

That is the point of the parser. Tags used to be compiled to PHP and evaluated:
identifiers were rewritten into array lookups and everything else — operators,
semicolons, `$`, braces, backticks — passed through untouched. `{{a.b(c.d)}}`
became `$data["a"]["b"]($data["c"]["d"])`, a call whose function name was read
out of the run's own data. For a webhook-triggered workflow that data is a JSON
body posted by a stranger.

Anything the grammar does not cover resolves to nothing, the same as a path that
is not there.

## Tags an integration owns

`{{contact.*}}`, `{{unsubscribe_link}}` and `{{update_preferences_link}}` belong
to a mailing integration's own per-recipient merge engine and are passed through
untouched, still in their braces, for that integration to fill in later. Add to
that list with the `zaplane_reserved_merge_tag_roots` filter.

## Conditions

Condition and Filter nodes do not put their comparison in a tag. They hold the
left value, the right value and the operator separately, and compare in PHP —
so each side only ever needs to resolve to one value.
