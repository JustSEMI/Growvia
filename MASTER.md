# Growvia interface system

## Direction
Operational dashboard with paired light/dark themes. Light uses warm monochrome and pale botanical accents; dark uses desaturated green-charcoal surfaces and lighter tonal accents. A useful workspace, not a marketing landing page. Apply to admin, petani, account, authentication, and error views.

## References and selection
UI/UX Pro Max search matched Minimalism & Swiss Style and anonymous Blade components. Use the verified accessibility/form guidance. Discard landing-page CTA patterns and the initial saturated dark-operations palette. Preserve the existing minimal light layout and its paired desaturated dark palette. The user-selected minimalist-ui constraints govern palette, icons, typography, and effects.

## Typography
- UI/body: locally hosted Instrument Sans, system sans fallback. Regular 400, medium 500, semibold 600.
- Editorial heading accent: locally hosted Instrument Serif, Georgia fallback; only welcome/auth/empty-state display headings.
- Metadata: system monospace; do not make the whole interface monospace.
- Body/input 16px; secondary UI 14px; labels/metadata no smaller than 12px.

## Tokens
- Canvas #F7F6F3; white surfaces #FFFFFF; soft surface #FBFBFA.
- Text #202723; secondary #626A64; muted #737773.
- Border #EAEAEA, exactly 1px for cards; form control border may be stronger for accessibility.
- Action #111111 with white text; hover #333333.
- Pale green #EDF3EC / #346538, yellow #FBF3DB / #785411, red #FDEBEC / #9F2F2D, blue #E1F3FE / #1F6C9F.
- Cards 12px radius, controls/buttons 6px; pills only for status badges.
- Spacing: 4/8/12/16/24/32/40/48px. Dashboard density uses 24px card padding, 24px gaps, and 32px between groups rather than marketing-scale vertical gaps.

## Theme behavior
- Offer a labeled custom select-only combobox/listbox: Sistem / Terang / Gelap. Default to OS preference; explicit selection persists in localStorage under `growvia.theme` for the current browser origin, not the user account or database.
- Apply the initial choice in a synchronous, static head script before asset links/body. No user-controlled values are interpolated into this script. Keep startup and module palette metadata consistent. If CSP is introduced, authorize this exact bootstrap with an appropriate nonce/hash rather than enabling arbitrary inline scripts.
- Use semantic CSS variables paired with `light-dark()` and root `color-scheme`. Tailwind custom color utilities reference those same tokens through `@theme inline`; no inversion filters or unthemed white card overrides. This requires modern browser support for CSS `light-dark()`.
- Dark canvas #171B18, surface #202622, soft #252C27; text #EDF1E9, secondary #B5BEB4, muted #A3AEA1; structural border #3A443C. Main actions use #E0E8D8 with #1B241D text.
- Dark semantic pairs: green #ACD0A0 / #2A392E, blue #A7CDE5 / #253744, amber #E2C78F / #3D3524, red #EFB5B8 / #432D31.
- Controls live in the workspace topbar, auth header, and error-page header area. Full keyboard behavior (arrows, Home/End, typeahead, Enter/Space/Tab, Escape), visible focus, 44px minimum height; font size 16px on mobile. Theme icons reflect the selected preference.
- Listen for OS changes only while following Sistem; synchronize localStorage changes between tabs and restore the latest choice on pageshow. Catch storage access failures so current-page switching still works.
- Without JavaScript, CSS follows the OS and the inactive selector stays hidden. A blocked main bundle does not prevent the head bootstrap from applying a saved choice. Stored preferences cannot be read when all JavaScript is disabled.
- Keep a literal color fallback in `::backdrop` because some browser implementations do not inherit custom properties into it. Verify the scrim both with the theme token and with that token unavailable; this fallback does not imply legacy support for the rest of the theme.

## Layout
Shared workspace shell: 240px desktop sidebar, topbar with location/account, page introduction, four-column metrics, asymmetric main grid with a wide trend area and narrow node/control summary. Keep information density balanced and the first viewport useful. One mobile navigation disclosure, no duplicate menus.

At 1024px and below, collapse sidebar into an accessible disclosure. At 768px, main grid stacks. At 480px, metric cards remain readable in two columns or stack when content requires it. No page-level horizontal scroll; tables can have a labeled overflow region. Use skip links, visible focus, safe-area insets, and scroll margins.

## Components
Create reusable Blade primitives for icon, brand, badge, page heading, metric, empty chart, field, alerts, navigation and form panels. All meaningful controls have text or accessible names. Use bold/fill geometric SVGs, not thin-line generic icon libraries. No new JS framework or animation dependency.

## Interaction and truthfulness
- Preserve existing routes, authorization, CSRF, and CRUD behavior.
- No invented sensor values, trend lines, live dots, notifications, or working pump controls. Use a designed empty state with explicit 'Integrasi sensor belum aktif' guidance.
- Show saved configuration as configuration, never as confirmed physical device state.
- Native non-destructive forms and navigation work without JS. Destructive delete stays disabled with a visible explanation until the confirmation handler is registered. Password reveal and submit feedback are progressive enhancements.
- No inline JS attributes containing user data. Confirmation strings come from escaped data attributes and textContent.
- Show all server validation errors with linked summary and field-level messages. Client constraint errors use the same visual language, never native browser validation bubbles while JS is ready. Do not replace error summaries with ephemeral toasts.
- Enhance role/theme/pump selects with the same custom listbox; retain native select name/value for form payload and no-JS fallback. Use body-level positioned menus with viewport clamping and upward placement near the bottom. Avoid role=menu for single-choice fields.
- Keep the custom-styled semantic dialog for destructive confirmation. Never fall back to window.confirm; fail closed with an explanation when the dialog is unavailable.
- Flash success uses custom toast feedback plus a compact persistent inline receipt. Use neutral surface cards, semantic icon tiles, a 44px close button, safe-area spacing, and bounded stacked messages. Success/info expire after 7 seconds except while hovered/focused/backgrounded; warnings/errors remain dismissible. Keep one polite live region and safe textContent rendering. Move the stack away from a keyboard-focused control if its default position would obscure that control. Never request system notification permission for ordinary UI feedback.
- Style notification checkboxes as switches and remember-me as a custom checkbox without changing their native semantics or successful form values.
- Do not implement unrelated backend security/IoT changes under this UI task.

## Account, activity, and language additions
- Keep the existing page composition; the approved design change is Indonesian copy consistency, not a redesign. Use Ringkasan, Ruang kerja, Hak akses, Rumah kaca, and Isi tandon. Preserve database role values `admin`/`user` and technical identifiers.
- User search, role/status filters and sorting reuse existing form fields/custom selects; status toggles use the shared dialog and remain disabled until it is ready. Do not remove native successful form controls.
- Activity history records application actions only, with actor, affected account, changed field names, and explicitly labeled timezone. The existing dashboard activity panel shows real recent records; empty means empty.
- Sessions show browser-reported device information, not verified device identity or IoT nodes. Keep IP and last-use time readable on mobile; never expose session IDs or payloads.
- Sensitive account changes require reauthentication. Password fields stay blank after validation errors; retain durable inline messages with custom toast enhancement.

## Motion and assets
Use CSS transform/opacity and IntersectionObserver for subtle one-shot entry. Content remains visible without JS and with reduced motion. No GSAP/CDN scripts, gradients, heavy shadows, or decorative fake charts. Use locally built fonts and a restrained line illustration on auth/empty states.

## Verification
PHP feature tests for every active page and role, safe user-name confirmation, navigation, error display, and configured values. Build actual Vite assets. Inspect browser screenshots at 375/768/1024/1440px, keyboard/mobile navigation, no-JS/reduced-motion and hostile/long user names. Keep test DB/mail/storage isolated from the actual project data.
