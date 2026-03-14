# HW WP FAQ

A WordPress plugin that stores FAQ entries in a custom database table and displays them via a Gutenberg block. Supports scheduled publication, per-entry active/inactive toggling, category grouping, and rich-text answers with media.

---

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.1 |
| PHP | 7.4 |
| MySQL / MariaDB | 5.7 / 10.3 |

---

## Installation

1. Copy the `hwwpfaq` folder into `wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.  
   On activation the database table `{prefix}_hwwpfaq_items` is created automatically.
3. No build step is required — the Gutenberg block uses WordPress's globally available `wp.*` APIs.

---

## Database schema

Table: `{prefix}_hwwpfaq_items`

| Column | Type | Description |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | Auto-increment primary key |
| `category` | `VARCHAR(100)` | Short label for grouping (e.g. "Shipping") |
| `question` | `TEXT` | The FAQ question (plain text) |
| `answer` | `LONGTEXT` | The answer — may contain HTML (stored via `wp_kses_post`) |
| `pub_date` | `DATETIME` | Scheduled publication date/time |
| `author_id` | `BIGINT UNSIGNED` | FK → `wp_users.ID` |
| `is_active` | `TINYINT(1)` | `1` = active, `0` = inactive |
| `created_at` | `DATETIME` | Row creation timestamp |
| `updated_at` | `DATETIME` | Last-modified timestamp |

---

## Admin UI

Navigate to **Tools → FAQ**.

### List view

- Paginated table of all FAQ entries (20 per page), newest first.
- **Edit** — opens the edit form.
- **Activate / Deactivate** — toggles visibility without deleting the entry.
- **Delete** — permanently removes the entry (confirmation prompt shown).

### Add / Edit form

| Field | Notes |
|---|---|
| **Category** | Plain text, max 100 characters. Leave blank for uncategorised entries. |
| **Question** | Plain text textarea. |
| **Answer** | Full TinyMCE editor with media library access ("Add Media" button). Supports floated images (`alignleft`, `alignright`, `aligncenter`). |
| **Publication Date** | `datetime-local` picker. The entry will not appear on the frontend before this date/time. |
| **Author** | Dropdown of all WordPress users. Defaults to the currently logged-in user. |
| **Active** | Checkbox. Combine with Publication Date for fully scheduled publishing. |

---

## Gutenberg Block

Insert the **HW WP FAQ** block (category: *Widgets*) into any post or page.

### Block attribute

| Attribute | Type | Default | Description |
|---|---|---|---|
| `category` | `string` | `""` | Filter by exact category name. Leave empty to display all categories. |

Set the value in the **FAQ Settings** panel in the block sidebar.

### Display logic

The block only renders entries where **both** conditions are true:
- `is_active = 1`
- `pub_date ≤ current date/time`

Entries are grouped by category (alphabetical), sorted by `pub_date ASC` within each group.

---

## Frontend output / Theme styling

The block produces semantic HTML with BEM class names. All visual styling is intentionally left to the active theme. The plugin ships only structural defaults (margins, float clearfix, image alignment rules).

```html
<div class="hwwpfaq wp-block-hwwpfaq-faq">
  <section class="hwwpfaq__category">
    <h2 class="hwwpfaq__category-title">Shipping</h2>
    <dl class="hwwpfaq__list">
      <div class="hwwpfaq__item">
        <dt class="hwwpfaq__question">How long does delivery take?</dt>
        <dd class="hwwpfaq__answer">Usually 3–5 business days.</dd>
      </div>
    </dl>
  </section>
</div>
```

### Available CSS hooks

| Class | Element | Use |
|---|---|---|
| `.hwwpfaq` | Outer wrapper | Block-level container |
| `.hwwpfaq--empty` | Modifier on wrapper | Shown when no entries match |
| `.hwwpfaq__category` | `<section>` | One per category group |
| `.hwwpfaq__category-title` | `<h2>` | Category heading |
| `.hwwpfaq__list` | `<dl>` | List of items within a category |
| `.hwwpfaq__item` | `<div>` | Wraps one `dt` + `dd` pair |
| `.hwwpfaq__question` | `<dt>` | The question |
| `.hwwpfaq__answer` | `<dd>` | The answer (may contain rich HTML) |

The block also inherits WordPress block support for **color**, **spacing**, and **typography**, so those can be configured directly in the block editor without any custom CSS.

---

## File structure

```
hwwpfaq/
├── hwwpfaq.php                        Plugin bootstrap, constants, block registration
├── uninstall.php                      Drops the DB table on plugin deletion
├── includes/
│   └── class-hwwpfaq-db.php           CRUD + query helpers (static methods)
├── admin/
│   ├── class-hwwpfaq-admin.php        Admin list view + add/edit form + action handlers
│   └── admin.css                      Column widths, status badge styles
└── block/
    ├── block.json                     Block metadata (WP 6.1+ format)
    ├── editor.asset.php               Script dependency manifest (no build step)
    ├── editor.js                      Block editor UI (plain JS, wp.* globals)
    ├── render.php                     Server-side frontend renderer
    └── block.css                      Structural defaults + image alignment rules
```

---

## Uninstall

Deleting the plugin via **Plugins → Delete** runs `uninstall.php`, which drops the `{prefix}_hwwpfaq_items` table and removes all plugin data from the database.

---

## License

GPL-2.0-or-later — see <https://www.gnu.org/licenses/gpl-2.0.html>
