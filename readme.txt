=== AI Explainer ===
Contributors: billypatel
Donate link: https://wpaiexplainer.com/donate
Tags: ai, explanation, tooltip, accessibility, education, learning
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.30
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform your WordPress site into an interactive learning platform with AI-powered text explanations via elegant tooltips. All features included for free.

== Description ==

AI Explainer helps your visitors understand complex content by providing instant AI-generated explanations. Users simply select any text on your site, and they'll receive a clear, helpful explanation in an elegant tooltip.

**Now completely free with all advanced features included** - No subscriptions, no paid tiers. Simply pay for your chosen AI provider's API usage on a pay-as-you-go basis.

**How It Works:**

1. User selects text on your website
2. Tooltip appears with explanation option
3. AI generates a clear, contextual explanation
4. User can close or move the tooltip as needed

**Core Features (All Free):**

* **Multiple AI Providers**: OpenAI (GPT-5.1), Claude (Haiku 4.5), or Gemini (2.5 Flash)
* **Pre-configured Models**: Each provider uses an optimised model automatically - no configuration needed
* **Interactive Text Explanations**: Users highlight any text to trigger AI-powered explanations
* **Intelligent Tooltips**: Smart positioning with manual control and viewport boundary detection
* **Multi-language Support**: Interface available in 7 languages (English US/UK, Spanish, German, French, Hindi, Chinese)
* **Bulk Post Scanning**: Scan multiple posts for technical terms automatically
* **AI Blog Creation**: Generate complete blog posts from text selections
* **Term Extraction**: Automatically identify and extract technical terms
* **Job Queue System**: Background processing for bulk operations
* **Custom Appearance**: Customise tooltip colours, buttons, and styling
* **Smart Caching**: Cache API responses to reduce costs by up to 70%
* **Enhanced Rate Limiting**: Separate limits for logged-in vs anonymous users
* **Popular Selections Tracking**: Monitor most-requested explanations
* **Content Control**: Set text length limits and use CSS selectors to include/exclude specific areas
* **Secure Architecture**: API keys encrypted with WordPress salts and never exposed to frontend
* **Theme Compatible**: Works seamlessly with any WordPress theme
* **Accessibility Compliant**: Full WCAG AA compliance with keyboard navigation and screen reader support
* **Mobile Optimised**: Touch-friendly interface that works perfectly on all devices
* **Privacy Focused**: GDPR compliant with minimal data collection and user-controlled requests

**Perfect For:**

* Educational websites and online courses
* Technical documentation and knowledge bases
* News and magazine sites with complex topics
* E-commerce sites with technical product descriptions
* Healthcare sites simplifying medical terminology
* Legal services explaining legal jargon
* Training platforms with consistent terminology
* SaaS documentation with extensive help systems
* Academic content requiring simplified explanations
* Any website where users might need extra context

**Technical Highlights:**

* Vanilla JavaScript - no framework dependencies
* Lightweight - adds less than 100ms to page load
* WordPress coding standards compliant
* Comprehensive admin interface with tabbed settings
* Real-time API key validation
* Built-in debug tools for troubleshooting
* Secure nonce verification for all AJAX requests
* Input sanitisation and output escaping throughout
* Clean, maintainable code architecture

== Installation ==

**From WordPress Admin:**

1. Go to Plugins → Add New
2. Search for "AI Explainer"
3. Click Install Now and then Activate
4. Go to Settings → AI Explainer
5. Choose your AI provider (OpenAI, Claude, or Gemini)
6. Enter your API key
7. Test your API connection (model is automatically selected)
8. Customise settings as needed
9. Save and test on your site

**Manual Installation:**

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` directory
3. Extract the files
4. Activate through the Plugins menu in WordPress
5. Configure settings as described above

**Getting Your API Key:**

**OpenAI:**
1. Visit https://platform.openai.com/
2. Create an account or sign in
3. Navigate to API Keys section
4. Click Create new secret key
5. Copy the key and paste into plugin settings

**Claude:**
1. Visit https://console.anthropic.com/
2. Create an account or sign in
3. Navigate to API Keys
4. Generate a new API key
5. Copy and paste into plugin settings

**Gemini:**
1. Visit https://ai.google.dev/
2. Create an account or sign in
3. Navigate to API Keys
4. Create a new API key
5. Copy and paste into plugin settings

**First Time Setup:**

After installation, configure these essential settings:

* Enable the plugin site-wide
* Select your language preference
* Choose your AI provider (model is automatically set)
* Enter and test your API key
* Set minimum and maximum text selection lengths
* Configure rate limiting to manage API costs
* Customise the prompt template if desired
* Customise tooltip appearance (all styling options included)

== Frequently Asked Questions ==

= Is the plugin really completely free? =

Yes. All features that were previously pro-only are now included for free. There are no subscriptions or paid tiers. You only pay your chosen AI provider for API usage on a pay-as-you-go basis.

= Do I need an API key? =

Yes, you need an API key from at least one AI provider (OpenAI, Claude, or Gemini). The plugin connects to their APIs to generate explanations. You can get API keys from their respective platforms.

= How much does it cost? =

The plugin is free. API costs vary by provider:
- OpenAI (GPT-5.1): ~$0.002-0.005 per explanation
- Claude (Haiku 4.5): ~$0.001-0.003 per explanation
- Gemini (2.5 Flash): ~$0.0005-0.002 per explanation

Enable caching to reduce costs by up to 70%.

= Can I choose which AI model to use? =

Models are pre-configured and optimised for each provider:
- OpenAI: GPT-5.1
- Claude: Haiku 4.5
- Gemini: 2.5 Flash

This ensures optimal performance without requiring any model configuration.

= Will this slow down my website? =

No. The plugin is lightweight and only loads scripts when needed. It adds less than 100ms to page load time. AI processing happens asynchronously and doesn't block page rendering.

= Does it work with my theme? =

Yes, AI Explainer is designed to work with any WordPress theme. The CSS is carefully structured to avoid conflicts whilst maintaining a professional appearance.

= Is it accessible? =

Absolutely. The plugin is fully WCAG AA compliant with complete keyboard navigation support and proper screen reader labelling. Users can navigate and interact with tooltips using only their keyboard.

= Can I customise the appearance? =

Yes, you can customise tooltip colours (background, text, footer), button appearance, position, and add custom footer text. All customisation features are included for free.

= What about privacy and GDPR? =

The plugin is GDPR compliant. Only user-selected text is sent to your chosen AI provider - no personal information is transmitted. Users control when explanations are requested. No tracking cookies are used, and no data is permanently stored.

= Can I limit usage to prevent high API costs? =

Yes, the plugin includes comprehensive rate limiting. You can set different limits for logged-in users versus anonymous visitors, with per-minute, per-hour, and per-day limits. Caching also significantly reduces API calls.

= Does it work on mobile devices? =

Yes, the plugin is fully optimised for mobile devices with touch-friendly controls and responsive design. It works perfectly on phones and tablets.

= What if the AI provider's API is down? =

The plugin handles API errors gracefully and displays user-friendly error messages instead of breaking. Users can continue browsing your site normally.

= Can I use this on a multisite installation? =

Yes, AI Explainer works with WordPress multisite. Each site can have its own API key and independent settings.

= Does this work with caching plugins? =

Yes, AI Explainer is compatible with popular caching plugins like WP Super Cache, W3 Total Cache, and WP Rocket.

= Can I translate this plugin? =

Yes, the plugin is translation-ready and already includes translations for 7 languages. You can add more translations using standard WordPress translation tools or plugins like WPML and Polylang.

= Can I use custom prompts? =

Yes, you can create custom prompt templates to control how the AI generates explanations. Use variables like {{selectedtext}} to dynamically insert the selected text into your prompt.

= How do I troubleshoot issues? =

Enable debug mode in the Advanced Settings tab to view detailed logs. The plugin includes comprehensive error logging and troubleshooting tools. Check the browser console for JavaScript errors, and review the debug log for API issues.

= What AI models are used? =

The plugin uses pre-configured optimised models for each provider:
- OpenAI: GPT-5.1
- Claude: Haiku 4.5
- Gemini: 2.5 Flash

Models are automatically configured for optimal performance and cost-effectiveness.

= Can I use bulk operations? =

Yes, all bulk operation features are included for free:
- Scan multiple posts for technical terms
- Generate blog posts from content
- Extract terms across your site
- Background job queue processing

== Screenshots ==

1. User selects text and sees the explanation tooltip
2. Basic Settings tab showing AI provider configuration
3. Appearance tab for customising tooltip styling
4. Content Rules tab for controlling where explanations appear
5. Performance tab with caching and rate limiting
6. Bulk Operations tab for post scanning and blog creation
7. Advanced Settings tab with debug tools
8. Tooltip with explanation displayed
9. Mobile experience with touch-friendly interface

== Changelog ==

= 1.3.30 =
* Converted plugin to fully free version with all features included
* Pre-configured AI providers with optimised models:
  - OpenAI: GPT-5.1
  - Claude: Haiku 4.5
  - Gemini: 2.5 Flash
* Removed model selector for simplified configuration
* All pro features now available at no cost
* Improved licence management workflow
* Enhanced account page styling
* Version bump for major feature release

= 1.3.29 =
* Maintenance release with stability improvements
* Enhanced compatibility checks
* Code optimisation and cleanup

= 1.3.28 =
* Interactive subscription management on account page
* Enhanced benefits display
* Account menu repositioned to bottom of submenu
* Fixed full-width button styling issues
* Improved alignment between link and button elements
* Added duplicate menu item detection and cleanup
* Enhanced asset loading for account page
* Streamlined licence activation flow

= 1.3.27 =
* Fixed uninstall tracking to properly collect user feedback
* Moved cleanup logic to Freemius after_uninstall hook
* Uninstall events and user feedback now properly tracked
* Removed uninstall.php to allow Freemius tracking functionality

= 1.3.26 =
* Removed non-working Freemius deployment workflow
* Fixed release packaging to properly include readme.txt
* Excluded playwright.config.minimal.js from plugin releases
* Improved release process and file exclusion management

= 1.3.25 =
* Maintenance release with version bump
* Enhanced codebase maintenance and optimisation
* Continued WordPress 6.8 and PHP 7.4+ support

= 1.2.1 =
* Fixed inconsistent loading text display in tooltips
* Loading tooltips consistently show "Loading explanation..." instead of raw translation keys
* Enhanced localised string fallback system
* Improved tooltip display consistency across all page loads

= 1.1.3 =
* Enhanced branding throughout admin interface
* Professional gradient headers and card layouts
* Fixed intermittent PHP error in formatting.php related to timestamp validation
* Enhanced hover effects and button styling
* Improved timestamp validation and error handling

= 1.1.2 =
* Enhanced readme with improved formatting and clarity
* Added comprehensive development documentation
* Streamlined version control and release process

= 1.1.1 =
* Removed legacy code for cleaner implementation
* Streamlined JavaScript initialisation
* Better integration with WordPress themes

= 1.1.0 =
* Complete defensive CSS overhaul to prevent styling conflicts
* Bulletproof styling using highly specific selectors
* Comprehensive CSS reset for all plugin elements
* Protected colours, button styling, and positioning
* Better plugin compatibility
* Enhanced selector specificity

= 1.0.9 =
* Improved error popup styling with better readability
* Comprehensive rate limiting documentation throughout admin
* Added inline help explaining time windows and cache exclusion
* Detailed rate limiting section in help tab
* Better user education about rate limiting mechanics

= 1.0.8 =
* Enhanced code structure and maintainability
* Strengthened input validation and sanitisation
* Improved provider factory pattern
* Streamlined admin interface rendering
* Enhanced inline documentation
* Refined privacy handling procedures
* Improved settings page organisation
* Enhanced provider reliability and error handling
* Improved template structure and validation
* Streamlined utility functions

= 1.0.6 =
* WordPress Plugin Directory compliance improvements
* Enhanced plugin validation
* Code structure improvements

= 1.0.5 =
* Updated support contact information
* Fixed documentation references

= 1.0.4 =
* Added correct UK English language support
* Improved British English localisation accuracy

= 1.0.3 =
* Major restructure and rename of plugin files
* Improved file organisation and naming conventions
* Enhanced code maintainability

= 1.0.2 =
* Added blocked words filter functionality
* Content filtering and moderation capabilities
* Removed test code and cleaned up codebase

= 1.0.1 =
* Added API usage monitoring
* Automatic plugin disable on quota exceeded
* Enhanced error handling for API limits
* Improved cost management features

= 1.0.0 =
* Initial release
* OpenAI integration with GPT models
* Multi-language interface (7 languages)
* Comprehensive admin interface with tabbed settings
* Custom prompt templates with validation
* Encrypted API key storage
* Provider factory architecture
* Enhanced security with validation and sanitisation
* Intelligent rate limiting
* WCAG AA accessibility compliance
* Mobile-optimised touch interface
* Universal theme compatibility
* GDPR-compliant privacy handling
* Debug tools and logging

== Upgrade Notice ==

= 1.3.30 =
Major update: All features now completely free. Pre-configured optimised models (OpenAI GPT-5.1, Claude Haiku 4.5, Gemini 2.5 Flash). No model configuration needed. Safe to update.

= 1.0.0 =
Initial release of AI Explainer. Install to start providing AI-powered explanations on your WordPress site.

== External Services & Privacy ==

**Third-Party Service Usage:**

This plugin connects to AI provider APIs to generate explanations. When a user selects text and requests an explanation, only the selected text is sent to your chosen provider's servers for processing.

**Supported AI Providers:**

**OpenAI API (api.openai.com)**
- Privacy Policy: https://openai.com/privacy/
- Terms of Service: https://openai.com/terms/
- Data sent: Only user-selected text from your website
- Purpose: Generating contextual explanations for selected text
- Model used: GPT-5.1 (automatically selected)

**Anthropic Claude API (api.anthropic.com)**
- Privacy Policy: https://www.anthropic.com/privacy
- Terms of Service: https://www.anthropic.com/terms
- Data sent: Only user-selected text from your website
- Purpose: Generating contextual explanations for selected text
- Model used: Claude Haiku 4.5 (automatically selected)

**Google Gemini API (generativelanguage.googleapis.com)**
- Privacy Policy: https://policies.google.com/privacy
- Terms of Service: https://policies.google.com/terms
- Data sent: Only user-selected text from your website
- Purpose: Generating contextual explanations for selected text
- Model used: Gemini 2.5 Flash (automatically selected)

**Data Transmission:**
- Only user-selected text is sent to your chosen AI provider
- No personal information or user data is transmitted
- No site content is transmitted beyond what users select
- API keys are encrypted and never exposed to frontend
- No permanent storage of user selections or explanations

**GDPR Compliance:**
- Users control when explanations are requested
- No automatic data collection or tracking
- No cookies are set by this plugin
- Clear indication when external services are used
- Option to disable the service entirely
- Complete data removal on plugin uninstall

**Legal Compliance:**
By using this plugin, you agree to comply with your chosen AI provider's Terms of Service and Privacy Policy. You should inform your users that selected text will be processed by external AI services. No data is stored by the plugin itself.

**Your Responsibilities:**
- Ensure compliance with your chosen provider's terms of service
- Inform users that text selections may be processed by external AI services
- Monitor your API usage and costs via provider's dashboard
- Keep your API key secure and never share it publicly

== Support ==

**Getting Help:**

For support questions, bug reports, or feature requests, please email info@wpaiexplainer.com

When requesting support, please include:
- WordPress version
- PHP version
- Theme name and version
- Active AI provider
- Description of the issue
- Steps to reproduce the problem
- Any error messages from debug mode

**Documentation:**

Comprehensive documentation is available in the plugin's Help tab within your WordPress admin area.

**Response Times:**

We typically respond to support requests within 24-48 hours during business days.

== Technical Requirements ==

**Minimum Requirements:**
* WordPress 5.0 or higher
* PHP 7.4 or higher
* API key from at least one provider (OpenAI, Claude, or Gemini)
* Modern browser with JavaScript enabled

**Recommended:**
* WordPress 6.0 or higher
* PHP 8.0 or higher
* HTTPS/SSL certificate for security
* Stable internet connection for API requests

== Privacy & Security ==

**Data Collection:**
This plugin does not collect or store any personal data. The only data sent externally is user-selected text to your chosen AI provider's API for explanation generation.

**API Key Security:**
- API keys are encrypted using WordPress salts
- Keys are stored securely in the WordPress database
- Keys are never exposed to frontend JavaScript
- Only administrators can access API keys

**User Privacy:**
- No tracking cookies
- No analytics or telemetry
- No user behaviour monitoring
- No data retention beyond active session

**Security Features:**
- WordPress nonces for all AJAX requests
- Capability checks for admin access
- Comprehensive input sanitisation
- Output escaping throughout
- Rate limiting to prevent abuse
- Secure proxy architecture

== Credits ==

* OpenAI for providing the GPT API and models
* Anthropic for providing the Claude API and models
* Google for providing the Gemini API and models
* WordPress community for development standards and best practices
* Beta testers for valuable feedback and testing
* Security researchers for responsible disclosure
* Contributors to the WordPress ecosystem

== Developers ==

**JavaScript Events:**

The plugin dispatches custom events for integration:

* `explainerPopupOnOpen` - Fired when tooltip opens
* `explainerPopupOnClose` - Fired when tooltip closes
* `explainerExplanationLoaded` - Fired when explanation loads

**Example:**
```javascript
document.addEventListener('explainerExplanationLoaded', function(event) {
    console.log('Explanation loaded:', event.detail);
});
```

**Hooks & Filters:**

For advanced customisation and integration, contact info@wpaiexplainer.com for developer documentation.

== Links ==

* Website: https://wpaiexplainer.com
* Support: info@wpaiexplainer.com
* GitHub: https://github.com/codevera-ai/ai-explainer
