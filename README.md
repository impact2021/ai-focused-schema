# AI Focused Schema

A WordPress plugin for managing JSON-LD schema with support for page-specific schema overrides and manual review management.

## Features

- Upload and manage JSON-LD schema via WordPress admin
- Edit schema fields through a user-friendly interface
- **Page-Specific Schema**: Merge page-specific schema with global schema for individual posts/pages
- **Automatic Output**: Schema automatically added to page `<head>` for SEO tools and search engines
- Manual review management with aggregate rating calculation
- Output schema via shortcode `[ai_schema]` for use in Divi footer or anywhere in your site
- **Documentation Page**: Built-in documentation accessible from the admin menu

## Installation

1. Upload the plugin files to `/wp-content/plugins/ai-focused-schema/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > AI Focused Schema to configure

## Usage

### Basic Setup

1. Navigate to **AI Schema** in your WordPress admin menu
2. Either upload a complete JSON-LD schema or fill in the individual fields
3. Schema is automatically added to the page `<head>` section (enabled by default)
4. Optionally use the shortcode `[ai_schema]` to manually place schema anywhere on your site

### Page-Specific Schema

You can add custom schema for individual posts or pages:

1. Edit any post or page in WordPress
2. Scroll down to the **AI Focused Schema - Page Specific** metabox
3. Check "Enable page-specific schema for this page/post"
4. Enter your custom JSON-LD schema (e.g., Service schema for a service page)
5. Save/Update the post

**How It Works:**

When page-specific schema is enabled for a page, **ONLY the page-specific schema** is output for that page. The global schema is NOT included to prevent duplication and schema bloat.

**Why This Approach?**

- **Prevents Duplication**: Your global schema may contain many nested items (reviews, offers, administrative areas, etc.). If we merged it with page-specific schema, you could end up with duplicate Service types and inflated schema item counts.
- **Cleaner Sub-Pages**: Service pages, product pages, and other sub-pages get focused, relevant schema without the overhead of your full business schema.
- **Homepage Gets Full Schema**: Pages WITHOUT page-specific schema (like your homepage) will display the complete global schema with all reviews, offers, and business details.

**Example:**

- **Homepage** (no page-specific schema): Outputs your complete LocalBusiness schema with reviews, services, opening hours, etc.
- **Service Page** (page-specific schema enabled): Outputs ONLY the Service schema you defined for that page.
- **About Page** (no page-specific schema): Outputs your complete LocalBusiness schema.

This approach ensures each page has appropriate, focused schema without unnecessary duplication.

### Manual Review Management

You can add reviews manually in the **Customer Reviews** section:
- Enter author name, rating (1-5), optional review text, and optional date
- Reviews are immediately added to your schema
- Aggregate rating is calculated automatically

## SEOpress & Schema Detection

**Important Note:** SEOpress (and similar SEO plugins) only display schemas they manage directly in their own dashboard. They **do not** show schemas from third-party plugins like AI Focused Schema.

### How to Verify Your Schema is Working:

1. **Check Page Source**: View your page source (right-click → View Page Source) and search for `application/ld+json` - you should see your schema in the `<head>` section
2. **Use Google Rich Results Test**: Visit [Google Rich Results Test](https://search.google.com/test/rich-results), enter your URL, and verify Google can detect your schema
3. **Use Schema Validators**: Tools like [Schema.org Validator](https://validator.schema.org/) or browser extensions can detect all schemas on your page

Your schema **IS** being outputted correctly when:
- Automatic Schema Output is enabled in plugin settings (default: ON)
- You have entered schema data in the plugin
- The schema appears in page source and validates in Google's tools

SEOpress showing "no schema" simply means SEOpress itself isn't managing any schemas - it doesn't mean your page lacks schema markup.

## Shortcodes

- `[ai_schema]` - Outputs the complete JSON-LD schema
- `[ai_entity_profile]` - Backwards compatible shortcode
- `[impact_gbp_schema]` - Backwards compatible shortcode

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
