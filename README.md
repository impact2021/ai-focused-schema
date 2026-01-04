# AI Focused Schema

A WordPress plugin for managing JSON-LD schema with support for page-specific schema overrides and manual review management.

## Features

- Upload and manage JSON-LD schema via WordPress admin
- Edit schema fields through a user-friendly interface
- **Page-Specific Schema**: Merge page-specific schema with global schema for individual posts/pages
- **Automatic Output**: Schema automatically added to page `<head>` for SEO tools and search engines (enabled by default)
- **Flexible Embedding**: Choose between automatic output or manual shortcode placement - perfect for hard-to-edit sites
- Manual review management with aggregate rating calculation
- Output schema via shortcode `[ai_schema]` for use in Divi footer or anywhere in your site
- **Documentation Page**: Built-in documentation accessible from the admin menu

## Installation

1. Upload the plugin files to `/wp-content/plugins/ai-focused-schema/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > AI Focused Schema to configure

## Automatic Updates

This plugin supports automatic updates via the [Git Updater](https://git-updater.com/) plugin. If you have Git Updater installed, the plugin will automatically check for updates from the GitHub repository and display "Update now" in your WordPress admin when new versions are available.

To enable automatic updates:
1. Install and activate the [Git Updater](https://git-updater.com/) plugin
2. The plugin will automatically detect updates from https://github.com/impact2021/ai-focused-schema
3. Updates will appear in your WordPress admin under Plugins

## Usage

### Basic Setup

1. Navigate to **AI Schema** in your WordPress admin menu
2. Either upload a complete JSON-LD schema or fill in the individual fields
3. Schema is automatically added to the page `<head>` section (enabled by default)
4. Optionally use the shortcode `[ai_schema]` to manually place schema anywhere on your site

### Schema Output Options

You have two ways to add your schema to your website:

#### Automatic Output (Default)
- Schema is automatically added to every page's `<head>` section
- Perfect for sites that are hard to edit or where adding shortcodes is difficult
- Enabled by default - just configure your schema and it works
- Can be toggled on/off in the **Schema Output Settings** section

#### Manual Shortcode Placement
- Use the `[ai_schema]` shortcode to place schema exactly where you want
- Ideal for custom placements (e.g., Divi footer)
- Can be used alongside or instead of automatic output
- Simply add `[ai_schema]` to any page, post, or widget

**Note:** If you're using the shortcode and don't want duplicate schema output, disable automatic output in the settings.

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

## Schema Best Practices for AI Search

To optimize your schema for AI-powered search engines and large language models, follow these best practices:

### 1. URL Consistency

- Use consistent URLs throughout your schema
- Use `@id` without trailing slash: `"@id": "https://www.example.com"`
- Use `url` with trailing slash: `"url": "https://www.example.com/"`
- Be consistent across all URL references

### 2. Rich Descriptions with Keywords

Include location and service keywords naturally in descriptions:

```json
{
  "description": "Your Business offers custom website design, hosting, and SEO services across [Location] and [Region]."
}
```

AI systems use these keywords to match queries like "[Location] web design" or "[Region] SEO services".

### 3. Use Schema.org Standard Types

Instead of free-text categories, use proper schema.org types:

- `ProfessionalService` - For professional service businesses
- `LocalBusiness` - For local businesses
- Specific subtypes like `WebDesignService`, `Store`, `Restaurant`, etc.

### 4. Multiple Contact Points

Provide separate contact points for different departments:

```json
{
  "contactPoint": [
    {
      "@type": "ContactPoint",
      "telephone": "+1-xxx-xxx-xxxx",
      "contactType": "sales",
      "email": "sales@example.com"
    },
    {
      "@type": "ContactPoint",
      "telephone": "+1-xxx-xxx-xxxx",
      "contactType": "customer support",
      "email": "support@example.com"
    }
  ]
}
```

### 5. Comprehensive Area Served

List both specific locations and broader areas:

```json
{
  "areaServed": [
    {
      "@type": "City",
      "name": "Your City"
    },
    {
      "@type": "Country",
      "name": "Your Country"
    }
  ]
}
```

AI often uses broader areas to match location-based queries.

### 6. Main Entity Reference

Connect your schema to your homepage:

```json
{
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://www.example.com/"
  }
}
```

### 7. Image and Logo Properties

Include dimensions for better rendering in rich results:

```json
{
  "logo": {
    "@type": "ImageObject",
    "url": "https://www.example.com/logo.png",
    "width": "512",
    "height": "512"
  },
  "image": {
    "@type": "ImageObject",
    "url": "https://www.example.com/image.jpg",
    "width": "1200",
    "height": "630"
  }
}
```

### 8. Service Type in Offers

Add `serviceType` to each service for clarity:

```json
{
  "itemOffered": {
    "@type": "Service",
    "name": "Website Design",
    "serviceType": "Web Design",
    "description": "Professional website design services..."
  }
}
```

### 9. Complete Review Ratings

Include `bestRating` and `worstRating` in all review ratings:

```json
{
  "reviewRating": {
    "@type": "Rating",
    "ratingValue": 5,
    "bestRating": 5,
    "worstRating": 1
  }
}
```

### 10. Example Schema

See `example-schema.json` for a complete implementation of all these best practices.

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
