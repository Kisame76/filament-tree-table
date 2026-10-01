# Security policy

## Reporting a vulnerability

Please do not open a public issue for a security problem. Email
**k.doguc@kisame-labs.com** instead, with enough detail to reproduce it, and you will get
an acknowledgement within a few days.

## Scope worth knowing about

The expanded state of a tree lives in public Livewire properties, so it is client-supplied
by nature, and the tree is ordered with a raw `ORDER BY` expression. Those are the two
areas that get particular care and are the most useful places to look:

- **Which rows a client can reveal.** The tree is built out of the table's own query, so a
  tampered `expandedRows` or `expandableParentKeys` can only choose among rows that query
  already returns. A way to surface a row the table would not otherwise show is a
  vulnerability.
- **What reaches the SQL.** `OrderByIds` inlines only keys that are all digits, cast to
  integers, and binds every other key (ULID, UUID, string) as a parameter. Nothing
  client-controlled is interpolated into the expression.

Reports about either, or about anything else in the package, are appreciated.
