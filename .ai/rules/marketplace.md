---
paths:
  - 'app/Services/Marketplace/Ozon*.php'
---

# Marketplace

## Resolve Ozon dictionaries from schema metadata
Treat attributes with dictionary_id > 0 through Ozon's live dictionary. Do not add field-name exclusion lists or automatically choose fuzzy matches. Canonicalize only unambiguous normalized matches, omit unmatched optional values, and report unmatched required values with Ozon suggestions.
