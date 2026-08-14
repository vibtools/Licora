# Licora UI Design System

## Authority

Licora v5.4.0 migrates the application UI to the **VibTools Web UI v2.1.2 structural system** while preserving Licora's established light blue/purple product identity. The design source of truth is component-first: individual pages compose shared components and may not introduce independent color, spacing, radius, table, form, button, modal or navigation systems.

## Theme contract

The v5.4.0 production target is **light theme only**.

- Page background: light gray/white.
- Surfaces: white/light neutral.
- Primary action: Licora blue.
- Secondary accent: Licora purple.
- Text: dark neutral.
- Success/warning/danger: semantic green/amber/red.
- VibTools dark Sandbox colors are not the Licora production theme.

`admin/assets/css/vibtools/` remains the audited VibTools v2.1.2 foundation. Licora maps those structural tokens through `admin/assets/css/licora/theme/light.css`.

## CSS architecture

```text
VibTools v2.1.2 Foundation
  -> Licora Light semantic theme
  -> Shared application layout
  -> Shared UI components
  -> Feature component extensions
  -> Utility classes
  -> Page composition
```

Primary entrypoint:

```text
admin/assets/css/admin-ui.css
  -> admin/assets/css/licora/licora-ui.css
```

The top-level `admin-ui.css` filename is retained as a compatibility contract for existing pages. `licora-updater.css` is also retained as a compatibility entrypoint but delegates updater visuals to the centralized updater component stylesheet.

### Page-specific CSS rule

New page-specific design stylesheets and page `<style>` blocks are forbidden. A visual requirement must be implemented as one of:

1. an existing shared component;
2. a reusable component variant;
3. a reusable layout/utility primitive.

## Application shell

Authenticated admin pages use:

```text
Fixed left Sidebar
+ Compact utility Topbar
+ Fluid page content
```

Primary navigation is never duplicated in the topbar.

### Sidebar groups

- Main: Dashboard
- License Management: Licenses, Devices
- API & Clients: API Keys, Client Apps, V2 Devices
- Operations: Logs, Updates
- System: Settings, Admins

Existing authorization is preserved. The Updates item remains restricted by the same Super Admin permission contract and retains the live update-available badge hook.

### Responsive behavior

- Desktop: fixed expanded sidebar.
- Tablet/mobile: sidebar becomes an off-canvas drawer.
- Menu button opens the same navigation; no duplicate menu tree is maintained.
- Escape, backdrop click and navigation close the mobile drawer.
- Viewport-level horizontal overflow is an acceptance failure.

## Typography

Licora uses the VibTools v2.1.2 compact type scale and weights:

- body: 13px-equivalent token scale;
- regular: 400;
- medium: 500;
- bold: 600;
- system/Inter-compatible sans stack;
- monospace stack for keys, diagnostics and logs.

No page may invent a new typographic scale without updating this design system.

## Core components

Shared component styling covers:

- Application shell / sidebar / topbar
- Page headers
- Cards and card sections
- Buttons: primary, secondary, success, warning, danger
- Forms: input, select, checkbox, switch, input groups, help text
- Tables, toolbars, pagination and empty states
- Badges and status states
- Alerts and toasts
- Dropdowns and modals
- Loading overlay
- Copyable credentials/code surfaces
- Dashboard statistics
- Backup/export tiles
- Authentication shell
- Installer shell
- Secure updater / live log modal

Existing Bootstrap markup remains a compatibility layer where changing DOM would create unnecessary behavior risk; the visual source of truth is the centralized component stylesheet, not Bootstrap defaults.

## PHP presentation components

The shared shell is implemented under:

```text
admin/includes/ui/
  navigation.php
  sidebar.php
  topbar.php
```

`admin/includes/navbar.php` remains as the historical include name, but now acts only as a compatibility entrypoint that renders the shared sidebar/topbar components. Business logic is not moved into presentation components.

## JavaScript presentation components

The responsive sidebar controller lives in:

```text
admin/assets/js/components/sidebar.js
```

Existing form, table, confirmation, updater and notifier behavior remains in its existing modules. DOM identifiers, form field names, action URLs, CSRF fields and updater hooks are frozen by regression tests.

## Updater UI

The Secure Update Center remains the same signed/resumable updater backend. v5.4.0 only migrates its presentation to the common light component system. The live deployment log viewer continues to provide real updater events, search, level filtering, Copy Logs, Download Diagnostics, pin-to-bottom, progress/stage display and resume behavior.

The VibTools Sandbox-only `AI Diagnostics` control remains intentionally absent because Licora has no AI diagnostics backend.

## Accessibility

- Interactive controls retain visible focus indicators.
- Navigation uses `aria-current` for the active page.
- Mobile sidebar controls expose `aria-controls` and `aria-expanded`.
- Icon-only controls require an accessible label/title.
- Status must not depend on color alone where text/status labels exist.

## Backend freeze boundary

The v5.4.0 UI migration must not change:

- database schema/data;
- API v1/v2 contracts;
- license/device behavior;
- authentication/authorization rules;
- cron behavior;
- updater protocol, signing, migration or job/event semantics;
- existing form field names, CSRF fields, route/action URLs or required JavaScript hooks.

The UI contract tests under `tests/ui_*_contract.php` enforce the major boundaries above.

## v5.4.1 updater UI integrity

The updater follows the same component contract. Runtime JavaScript IDs must exist in `admin/updates.php` and are checked automatically by `tests/updater_dom_contract.php` plus the Node browser-runtime fixture. Install/rollback confirmation uses the shared Licora Light modal rather than browser-native `confirm()` UI. This does not change updater backend semantics.
