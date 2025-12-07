# AI Focused Schema

A WordPress plugin for managing JSON-LD schema with support for Google My Business review integration and page-specific schema overrides.

## Features

- Upload and manage JSON-LD schema via WordPress admin
- Edit schema fields through a user-friendly interface
- **Page-Specific Schema**: Override global schema for individual posts/pages
- **Automatic Output**: Schema automatically added to page `<head>` for SEO tools and search engines
- **Google My Business Integration**: Automatically import reviews from your Google My Business profile
- Manual review management with aggregate rating calculation
- Output schema via shortcode `[ai_schema]` for use in Divi footer or anywhere in your site

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

You can override the global schema for individual posts or pages:

1. Edit any post or page in WordPress
2. Scroll down to the **AI Focused Schema - Page Specific** metabox
3. Check "Enable page-specific schema for this page/post"
4. Enter your custom JSON-LD schema (e.g., Article schema for blog posts)
5. Save/Update the post

When enabled, the page-specific schema replaces the global schema for that page only.

### Google My Business Integration

To automatically import reviews from Google My Business:

1. **Get a Google Places API Key**:
   - Visit the [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
   - Create a new project or select an existing one
   - Enable the **Places API**
   - Create an API key

2. **Find Your Place ID**:
   - Use the [Place ID Finder](https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder)
   - Search for your business and copy the Place ID

3. **Configure the Plugin**:
   - In the **Google My Business Integration** section, enter your API key and Place ID
   - Click **Save GMB Settings**
   - Click **Fetch Reviews from Google** to import reviews

4. **Review Management**:
   - Reviews are automatically deduplicated by author name
   - Aggregate ratings are calculated automatically
   - You can also add manual reviews alongside imported ones

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
- For GMB integration: Google Places API key with Places API enabled
