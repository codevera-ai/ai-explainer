# AI Explainer for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.3.30-orange.svg)](https://wpaiexplainer.com)

AI Explainer transforms your WordPress website into an interactive learning platform. Users can select any text and receive instant AI-generated explanations via elegant, customisable tooltips. Perfect for educational sites, technical documentation, content publishers, and any website where users might need extra context or clarification.

## Demo

![AI Explainer Demo](assets/demo.gif)

## Overview

AI Explainer provides comprehensive AI-powered text explanation capabilities **completely free**. All features are included at no cost, including multiple AI providers (OpenAI, Claude, Gemini), bulk processing capabilities, and extensive customisation options. Simply pay for your chosen AI provider's API usage on a pay-as-you-go basis.

## Key Features

### Interactive Text Explanations
- **Smart Text Selection**: Users highlight any text to trigger explanations
- **Elegant Tooltips**: Professional tooltip design with intelligent positioning
- **Viewport Boundary Detection**: Tooltips automatically adjust to stay visible
- **Manual Control**: Users can move tooltips and close them when finished
- **Multi-language Support**: Interface available in 7 languages (English US/UK, Spanish, German, French, Hindi, Chinese)

### Multiple AI Providers (All Free)
- **OpenAI**: GPT-5.1 model for sophisticated explanations
- **Claude (Anthropic)**: Claude Haiku 4.5 for advanced reasoning and cost-effectiveness
- **Google Gemini**: Gemini 2.5 Flash for fast, multilingual support
- **Automatic Model Selection**: Each provider uses an optimised model automatically
- **No Model Configuration**: Models are pre-configured for optimal performance

### Security & Privacy
- **Encrypted API Keys**: WordPress salts-based encryption
- **Secure Proxy Architecture**: API keys never exposed to frontend
- **GDPR Compliant**: Minimal data collection, user-controlled requests
- **No Tracking**: No cookies or permanent storage of user selections
- **Input Sanitisation**: Comprehensive validation and sanitisation

### Advanced Features (All Included)
- **Bulk Post Scanning**: Scan multiple posts for technical terms automatically
- **AI Blog Creation**: Generate complete blog posts from text selections
- **Term Extraction Service**: Automatically identify and extract technical terms
- **Job Queue System**: Background processing for bulk operations
- **Custom Appearance**: Customise tooltip colours, buttons, and styling
- **Smart Caching**: Cache API responses to reduce costs significantly
- **Enhanced Rate Limiting**: Separate limits for logged-in vs anonymous users
- **Popular Selections Tracking**: Monitor most-requested explanations

### Admin Interface
- **Intuitive Settings**: Organised into logical tabs (Basic, Appearance, Content Rules, Performance, Advanced)
- **Real-time Validation**: Instant feedback on configuration changes
- **API Key Testing**: Built-in API connection testing for all providers
- **Debug Tools**: Comprehensive logging for troubleshooting
- **Help Documentation**: Built-in help system with detailed guidance
- **Live Preview**: See appearance changes before saving

### Content Control
- **Text Length Limits**: Set minimum and maximum selection lengths
- **Word Count Limits**: Control the scope of explanations
- **CSS Selector Targeting**: Include or exclude specific page elements
- **Content Exclusions**: Prevent explanations in specific areas
- **Custom Prompts**: Personalise AI behaviour with template variables

### Performance & Accessibility
- **Lightweight**: Less than 100ms page load impact
- **WCAG AA Compliant**: Full accessibility support
- **Keyboard Navigation**: Complete keyboard control
- **Screen Reader Support**: Properly labelled for assistive technology
- **Mobile Optimised**: Touch-friendly interface for all devices
- **Theme Compatible**: Works with any WordPress theme
- **Rate Limiting**: Prevent abuse with configurable request limits

## Perfect For

- **Educational Platforms**: Online courses with bulk term extraction
- **Technical Documentation**: Knowledge bases with comprehensive glossaries
- **Content Publishers**: Magazine sites with AI blog creation
- **Training Platforms**: Corporate training with consistent terminology
- **SaaS Documentation**: Software platforms with extensive help systems
- **Academic Institutions**: Universities requiring multi-language support
- **Healthcare Sites**: Medical terminology simplified for patients
- **Legal Services**: Plain-language explanations of legal jargon
- **E-commerce**: Product descriptions with technical specifications
- **Personal Blogs**: Add helpful explanations for readers

## Requirements

### Minimum Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **HTTPS**: Recommended for security
- **Modern Browser**: JavaScript enabled

### AI Provider API Keys
Choose one or more providers (all supported for free):
- **OpenAI API Key**: From platform.openai.com
- **Claude API Key**: From console.anthropic.com (optional)
- **Gemini API Key**: From ai.google.dev (optional)

## Installation

### From WordPress Admin (When Available)

1. Navigate to **Plugins → Add New**
2. Search for "AI Explainer"
3. Click **Install Now** and then **Activate**
4. Go to **Settings → AI Explainer**
5. Configure your settings:
   - Select AI provider (OpenAI, Claude, or Gemini)
   - Enter your API key
   - Test the API connection (model is automatically selected)
   - Customise appearance
   - Configure content rules
   - Set rate limits
6. Save your settings
7. Test on your site by selecting text

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/ai-explainer/` directory
3. Extract the files
4. Activate through the **Plugins** menu in WordPress
5. Configure as described above

### Getting API Keys

#### OpenAI
1. Visit [platform.openai.com](https://platform.openai.com/)
2. Create an account or sign in
3. Navigate to **API Keys** section
4. Click **Create new secret key**
5. Copy the key (you won't see it again)
6. Paste into plugin settings under **Basic Settings**

#### Claude
1. Visit [console.anthropic.com](https://console.anthropic.com/)
2. Create an account or sign in
3. Navigate to **API Keys**
4. Generate a new API key
5. Copy and paste into plugin settings

#### Google Gemini
1. Visit [ai.google.dev](https://ai.google.dev/)
2. Create an account or sign in
3. Navigate to **API Keys**
4. Create a new API key
5. Copy and paste into plugin settings

## Configuration Guide

### Basic Settings Tab

**Plugin Status**
- Enable or disable the plugin site-wide
- Quick on/off toggle without deactivation

**Language Selection**
- Choose from 7 supported languages
- Changes tooltip interface language
- Supports: English (US/UK), Spanish, German, French, Hindi, Chinese

**AI Provider**
- Choose from OpenAI, Claude, or Gemini
- All providers included for free
- Models pre-configured and optimised:
  - **OpenAI**: GPT-5.1
  - **Claude**: Claude Haiku 4.5
  - **Gemini**: Gemini 2.5 Flash

**API Keys**
- Securely encrypted storage
- Test connection button for validation
- Keys never exposed to frontend

**Custom Prompts**
- Personalise AI behaviour
- Use template variables like `{{selectedtext}}`
- Real-time validation and testing

### Appearance Tab

**Tooltip Styling**
- Background colour customisation
- Text colour customisation
- Footer colour customisation
- Live preview of changes

**Button Styling**
- Toggle button appearance
- Position control
- Custom colours

**Footer Options**
- Custom disclaimers
- Provider attribution
- Footer text customisation

**Real-time Preview**
- Interactive preview system
- See changes before saving
- Test different configurations

### Content Rules Tab

**Text Limits**
- Minimum selection length (prevent accidental triggers)
- Maximum selection length (manage API costs)

**Word Limits**
- Minimum word count
- Maximum word count

**CSS Selectors**
- Include specific elements (e.g., `.content, .post`)
- Exclude specific elements (e.g., `.footer, .sidebar`)
- Advanced targeting with CSS selectors

**Custom Prompts**
- Advanced prompt engineering
- Template validation
- Testing interface

### Performance Tab

**Caching**
- Enable/disable response caching
- Set cache duration
- Automatic cache cleanup
- View cache statistics

**Rate Limiting**
- Separate limits for logged-in vs anonymous users
- Per-minute limits (primary protection)
- Per-hour limits (secondary protection)
- Per-day limits (overall protection)

**Default Limits**
- **Logged-in users**: 20/min, 100/hour, 500/day
- **Anonymous users**: 10/min, 50/hour, 200/day

### Bulk Operations Tab

**Post Scanning**
- Select multiple posts to scan
- Automatic technical term identification
- Progress tracking
- Background processing

**Blog Creation**
- Generate posts from selected content
- Customisable templates
- Batch processing
- Quality validation

**Job Queue Management**
- View active jobs
- Monitor progress
- Retry failed jobs
- View job logs

### Advanced Tab

**Debug Tools**
- Enable debug logging
- View recent logs
- Download log files
- Clear logs

**Security Options**
- Enhanced validation
- Additional sanitisation
- Security settings

**Performance Tuning**
- Fine-tune cache behaviour
- Adjust rate limiting
- Optimise for your site

## Developer Integration

### JavaScript Events

AI Explainer dispatches custom events for integration with analytics, learning management systems, and custom functionality.

#### explainerPopupOnOpen

Fired when any tooltip opens, including loading state.

```javascript
document.addEventListener('explainerPopupOnOpen', function(event) {
    const { selectedText, explanation, position, type } = event.detail;
    console.log('Tooltip opened:', selectedText);
});
```

#### explainerPopupOnClose

Fired when tooltip closes.

```javascript
document.addEventListener('explainerPopupOnClose', function(event) {
    const { selectedText, wasVisible } = event.detail;
    console.log('Tooltip closed:', selectedText);
});
```

#### explainerExplanationLoaded

Fired when explanation has finished loading and is displayed.

```javascript
document.addEventListener('explainerExplanationLoaded', function(event) {
    const data = event.detail;
    console.log('Explanation loaded:', {
        text: data.selectedText,
        provider: data.provider,
        cached: data.cached,
        responseTime: data.apiMetadata.responseTime
    });
});
```

**Event Detail:**
- `selectedText`: The text that was selected
- `explanation`: The AI-generated explanation
- `provider`: AI provider used (openai/claude/openrouter/gemini)
- `position`: Tooltip position coordinates
- `timestamp`: Unix timestamp when explanation loaded
- `cached`: Boolean indicating if response was cached
- `apiMetadata`: Object containing token usage, response time, model, cost
- `metadata`: Object containing selection and explanation metrics

## Troubleshooting

### Common Issues

**"API Key Invalid" Error**
- Verify API key is correct
- Check API key has required permissions
- Ensure API key is active
- Test connection in settings

**"Rate Limit Exceeded" Message**
- Wait 60 seconds and try again
- Check rate limit settings
- Enable caching to reduce API calls
- Consider adjusting limits

**Tooltips Not Appearing**
- Check plugin is enabled
- Verify API key is configured
- Check browser console for errors
- Review CSS selector targeting

**Slow Response Times**
- Enable caching
- Check API provider status
- Review server performance
- Models are automatically optimised

### Debug Mode

Enable debug mode in **Advanced Settings** to:
- View detailed error logs
- Track API requests and responses
- Monitor performance metrics
- Identify configuration issues

### Getting Support

Email: [info@wpaiexplainer.com](mailto:info@wpaiexplainer.com)
- Include WordPress version, PHP version, and steps to reproduce
- Enable debug mode and include relevant log entries

## Privacy & Security

### Data Handling

**What Gets Sent**
- Only user-selected text sent to AI provider
- No personal information transmitted
- No permanent storage of selections
- No tracking cookies

**API Keys**
- Encrypted using WordPress salts
- Never exposed to frontend
- Stored securely in database
- Only accessible to administrators

**GDPR Compliance**
- Users control when explanations are requested
- No automatic data collection
- Clear indication of external service usage
- Complete data removal on uninstall

### Third-Party Services

When you use AI Explainer, selected text is sent to your chosen AI provider:

**OpenAI**
- Privacy Policy: [openai.com/privacy](https://openai.com/privacy/)
- Terms of Service: [openai.com/terms](https://openai.com/terms/)

**Anthropic Claude**
- Privacy Policy: [anthropic.com/privacy](https://www.anthropic.com/privacy)
- Terms of Service: [anthropic.com/terms](https://www.anthropic.com/terms)

**Google Gemini**
- Privacy Policy: [policies.google.com/privacy](https://policies.google.com/privacy)
- Terms of Service: [policies.google.com/terms](https://policies.google.com/terms)

## Changelog

### Version 1.3.30 (Current)
- Converted plugin to fully free version with all features included
- Pre-configured AI providers with optimised models:
  - OpenAI: GPT-5.1
  - Claude: Claude Haiku 4.5
  - Gemini: Gemini 2.5 Flash
- Removed model selector for simplified configuration
- All pro features now available at no cost
- Improved licence management workflow
- Enhanced account page styling
- Version bump for major feature release

### Version 1.3.28
- Interactive subscription management on account page
- Enhanced premium benefits display
- Fixed full-width button styling issues
- Improved alignment between link and button elements
- Updated terminology from "Premium" to "Pro"

### Version 1.2.3
- Optimised release packaging
- Improved release size by excluding development files

### Version 1.2.2
- Enhanced tooltip edge detection
- Improved scrolling behaviour
- Better user experience when scrolling

### Version 1.2.1
- Fixed inconsistent loading text display
- Enhanced localised string fallback system

### Version 1.0.9
- Enhanced error popup styling
- Comprehensive rate limiting documentation
- Added best practices guide

### Version 1.0.8
- Enhanced debug logging
- Improved tab persistence
- Enhanced security

### Version 1.0.0
- Initial release
- Multi-provider support
- Multi-language interface
- Comprehensive admin interface
- Accessibility excellence

## Pricing

### Plugin Cost
- **License**: Free forever
- **All Features**: Included at no cost
- **No Subscriptions**: No recurring charges

### API Costs (Pay-as-you-go)
You only pay your chosen AI provider for API usage:

- **OpenAI (GPT-5.1)**: ~$0.002-0.005 per explanation
- **Claude (Haiku 4.5)**: ~$0.001-0.003 per explanation
- **Gemini (2.5 Flash)**: ~$0.0005-0.002 per explanation

**Cost Management Tips**
- Enable caching to reduce API calls (can reduce costs by 70%+)
- Set conservative rate limits
- Monitor usage in provider dashboard
- Choose cost-effective models (Gemini Flash, Claude Haiku)

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.

## Support & Contact

**General Support**
- Email: [info@wpaiexplainer.com](mailto:info@wpaiexplainer.com)
- Response time: 24-48 hours

**Bug Reports**
- Include WordPress version
- Include PHP version
- Provide detailed steps to reproduce
- Enable debug mode for error logs

**Feature Requests**
- Email suggestions to [info@wpaiexplainer.com](mailto:info@wpaiexplainer.com)
- Describe use case and expected behaviour

## Credits

- **OpenAI**: For GPT API and models
- **Anthropic**: For Claude API and models
- **Google**: For Gemini API and models
- **WordPress Community**: For development standards
- **Beta Testers**: For valuable feedback

## Links

- **Website**: [wpaiexplainer.com](https://wpaiexplainer.com)
- **Support**: [info@wpaiexplainer.com](mailto:info@wpaiexplainer.com)
- **GitHub**: [github.com/codevera-ai/ai-explainer](https://github.com/codevera-ai/ai-explainer)

---

**Made with care for the WordPress community**
