KAMPACHI CMS 2.4.0 — CATERING & PRIVATE EVENTS

This plugin and the Kampachi Native theme now provide a dynamic Catering &
Private Events landing page, backed by editable WordPress event posts.

EDITING
Catering & Events > Landing Page: open the WordPress page editor.
  Section 1: subheading, title, introduction.
  Section 2: automatically shows published Event Posts.
  Section 3: subheading, title, description, image gallery, and unlimited
             reorderable values/description highlights.

Catering & Events > Event Posts: create/edit/publish individual event posts.
  WordPress title = event title.
  Event details panel = badge, subheading, description, CTA label + URL,
                        multiple event photos, and multiple outlet checkboxes.
  Main WordPress content editor = event detail-page content.
  Available outlet checkboxes are read from 'Outlets' posts (kc_outlet).

Catering & Events (Landing Page dashboard): drag event cards into the preferred
order; click Save Event Order. Unpublished events are never shown publicly.

NEW CONTENT
On the first admin load after updating, the module adds the three existing
example event posts ONLY if no catering event posts already exist. They use
existing Kampachi theme photos. No existing posts are overwritten.
It also prepopulates Trust & Value Highlights with the original design's
suggested content until the landing editor saves explicit values.

INSTALLATION
1. Back up the database and wp-content. Test this update in staging first.
2. Replace the kampachi-cms plugin and kampachi-native theme files with the
   accompanying release; do not replace WordPress core or wp-config.php.
3. Keep Kampachi CMS activated and Kampachi Native as the active theme.
4. In WordPress, go to Settings > Permalinks and click Save Changes.
5. Edit and publish event posts, upload images, and select outlet posts.
6. Test the landing page and a detail URL on desktop and mobile.

Data is stored in standard WordPress post/meta tables. Deactivating the plugin
does not delete events or landing-page metadata. This release does not alter
other Kampachi modules or require Advanced Custom Fields.

=== Catering hero media (v2.4.2) ===
Catering & Events > Section 1 now supports any number of mixed image and video
attachments from the WordPress Media Library. Drag to reorder, remove items,
and save Section 1. The same control is available under the WordPress page
editor. Existing introduction content, events and value highlights stay intact.
The public theme patch is required for the hero slider to appear on the site.
Supported playback depends on the visitor's browser; use optimized H.264 MP4.

=== Editorial, Careers & Site Shell (v2.7.0) ===
This release updates the existing WordPress content model; it does not create
separate custom post types for landing pages.

Careers > Careers Page: edit section subtitle, title and introduction using the
standard page editor's "Kampachi Page Introduction" panel.
Careers > All Vacancies: publish any number of kc_job posts; post title is the
job title, the WP content editor holds the job description; the new Vacancy
Details panel contains salary from/to (MYR), Apply URL, email and WhatsApp.
Visitors open the job details with an accordion on /careers/.

About Us: Edit Page Content > Kampachi Page Introduction + Photo Gallery.
Add unlimited photo attachments, remove or drag to rearrange them. The normal
WordPress page editor content is preserved and displayed after the gallery.

Privacy & Policy: Edit Page Content > Kampachi Page Introduction + Content
Sections. Add/remove/reorder title and description sections. Previously saved
page content remains as a fallback until a list of sections is configured.
Have all published legal text reviewed by a qualified team.

Header: choose header logo, show/hide reservation and edit button label.
Navigation links: native Appearance > Menus. Each published Outlet has an
optional Reservation link in its editor for the header dropdown; if not set,
the corresponding outlet details page is used.

Footer: choose a logo, headings, company name, office address, public contact
details, social URLs, enquiry button text and destination. Footer links are
controlled via Appearance > Menus.

Contact > Enquiry Settings: enter one or more comma-separated recipient email
addresses for the Contact Us enquiry form. The form uses validation, a nonce,
spam honeypot and a per-IP rate limit; it sends by WordPress wp_mail without
storing a database copy. Configure WordPress mail/SMTP to ensure delivery.

INSTALLATION: back up files and database; apply the combined plugin/theme patch
as file overwrites, preserving directory paths. Test in staging and clear any
page cache/CDN after updating. Existing content is not deleted or migrated.

MASTER SETTINGS (v3.0.0)
========================

WordPress Admin -> Master Settings provides:
- Menu Style: item gap, font, font size and colour, before/after scroll modes,
  colour/HEX, opacity, transparency and blur.
- Overall Fonts: website font, optional uploaded TTF, H1-H5, paragraphs, spans,
  hyperlink font size, colour and underline.
- SMTP Mail: host, port, encryption, authentication, account and From identity.
- Languages: English master, selected languages, Azure Translator or DeepL
  API selection, optional automatic creation of independent translation drafts.
- GitHub: configured owner/repo/branch, connected user, recent commits and
  a manual, restricted latest-commit source-file synchronisation.

REQUIRED INSTALLATION:
Update BOTH kampachi-cms plugin and kampachi-native theme from the provided
packages. Settings are persisted in kampachi_master_settings; your old
kampachi_cms_settings contact / header / footer settings are left untouched.

OPTIONAL SERVER-ONLY SECRETS (wp-config.php; NEVER git commit these):
  define('KC_SMTP_PASSWORD', 'set-via-environment-or-secret-manager');
  define('KC_AZURE_TRANSLATOR_KEY', 'set-via-secret-manager');
  define('KC_AZURE_TRANSLATOR_REGION', 'southeastasia');
  define('KC_DEEPL_AUTH_KEY', 'set-via-secret-manager');
  define('KC_GITHUB_TOKEN', 'fine-grained-read-only-personal-access-token');
  define('KC_ALLOW_GITHUB_SYNC', true); // optional, disabled by default

GitHub source sync supports only modified/added code files from the latest
commit under wp-content/plugins/kampachi-cms or wp-content/themes/kampachi-native.
The repository must use these exact relative paths. A maximum of 25 changed
allowlisted files is accepted in one sync. It skips removals and ignores uploads.
Apply changes to staging first and back up site files and database before a
production deployment. Latest-commit sync does not replace a CI/CD workflow.

TRANSLATION SCOPE:
With automatic translation enabled and a working API key, new English posts
are translated into separate draft posts for each selected non-English language.
WordPress title, main content, excerpt, and supported CMS text fields are translated. Existing translations
retain text edits. Featured images, hero images, and selected CMS image lists
are inherited/synchronised from English. Translations must be reviewed and
published before appearing in the language menu. Existing content isn't
retroactively translated. Some nested CMS content fields (e.g., custom array
sections and template-coded homepage content) aren't translated automatically
and require separate localized editing / deeper localization integration.


MASTER SETTINGS — TRACKING & PROTECTED CONFIGURATION (v3.1.0)
========================================================
WordPress Admin > Master Settings > Tracking / GTM:
- Enter the GTM container ID (for example GTM-ABC1234), not the copied snippet.
- Enable GTM and save.
- Official JavaScript snippet is placed through wp_head (in <head>).
- No-script iframe is placed through wp_body_open (directly after <body>).
- Remove any other installed GTM snippet to prevent duplicate page views/events.
- GTM does not require changes to wp-config.php. Check consent/CMP requirements.

WordPress Admin > Master Settings > WP Config:
- The entire wp-config.php cannot be freely edited or pasted from the admin.
  A restricted editor changes supported Kampachi integration constants only.
- A super administrator must manually add this line to wp-config.php first:
    define('KC_ALLOW_WP_CONFIG_EDITOR', true);
  Add it before require_once ABSPATH . 'wp-settings.php';
- Editing is blocked by DISALLOW_FILE_MODS or DISALLOW_FILE_EDIT and requires
  filesystem write permissions on wp-config.php and its containing folder.
- Manage SMTP password, Azure Translator credentials, DeepL API key, GitHub PAT,
  and the KC_ALLOW_GITHUB_SYNC flag. Existing values are never displayed.
- The editor writes a clearly marked managed section to wp-config.php before
  the WordPress bootstrap; simple, literal definitions can be migrated.
- A guarded PHP backup is created adjacent to the config before each write.
  Do not share that backup: it contains original WordPress secrets. Keep backups
  outside public reach where possible, and remove obsolete backups securely.
  To restore, remove the protective first PHP line before replacing wp-config.
- After updating, set KC_ALLOW_WP_CONFIG_EDITOR to false or remove its define
  line to lock the editor again. Use staging first; do not commit secrets.

Existing theme already supports wp_head() and wp_body_open(), so no theme
changes are required for this 3.1.0 update. Existing Master Settings values
and content remain untouched.
