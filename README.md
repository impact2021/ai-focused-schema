# AI Focused Schema

A WordPress plugin for managing JSON-LD schema with support for Google My Business review integration.

## Features

- Upload and manage JSON-LD schema via WordPress admin
- Edit schema fields through a user-friendly interface
- **Google My Business Integration**: Automatically import reviews from your Google My Business profile
- Manual review management with aggregate rating calculation
- Output schema via shortcode `[ai_schema]` for use in Divi footer or anywhere in your site

## Installation

1. Upload the plugin files to `/wp-content/plugins/ai-focused-schema/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > AI Focused Schema to configure

## Usage

### Basic Setup

1. Navigate to **Settings > AI Focused Schema** in your WordPress admin
2. Either upload a complete JSON-LD schema or fill in the individual fields
3. Use the shortcode `[ai_schema]` to output the schema on your site

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

#### Important: Review Limit

**The Google Places API returns a maximum of 5 reviews per location.** This is a hard limit imposed by Google and cannot be changed. The API returns the 5 "most relevant" reviews as determined by Google's algorithm, not necessarily the most recent or highest rated.

**Alternatives for accessing more reviews:**
- **Google Business Profile API** (formerly My Business API): If you are the verified business owner, you can apply for access to this API which allows you to retrieve all reviews with pagination (up to 50 per page). Note that approval can take several weeks.
- **Manual Entry**: You can always add additional reviews manually through the plugin's review management interface.

### Manual Review Management

You can add reviews manually in the **Customer Reviews** section:
- Enter author name, rating (1-5), optional review text, and optional date
- Reviews are immediately added to your schema
- Aggregate rating is calculated automatically

## Shortcodes

- `[ai_schema]` - Outputs the complete JSON-LD schema
- `[ai_entity_profile]` - Backwards compatible shortcode
- `[impact_gbp_schema]` - Backwards compatible shortcode

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- For GMB integration: Google Places API key with Places API enabled
