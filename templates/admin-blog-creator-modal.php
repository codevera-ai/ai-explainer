<?php
/**
 * Blog Creator Modal Template
 * 
 * Template for the blog post creation modal
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="blog-creation-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php esc_html_e('Create Blog Post', 'ai-explainer'); ?></h2>
            <span class="close" id="close-blog-modal">&times;</span>
        </div>
        
        <div class="modal-body">
            <!-- Combined source text and explanation display -->
            <div class="selection-and-explanation">
                <div class="selection-preview" id="modal-selection-preview">
                    <!-- Selection text will be populated by JavaScript -->
                </div>
                
                <div class="explanation-section" id="explanation-section" style="display: none;">
                    <hr class="explanation-separator">
                    <div class="explanation-loading" id="explanation-loading" style="display: none;">
                        <div class="spinner is-active" style="float: none; margin-right: 8px; width: 16px; height: 16px;"></div>
                        <?php esc_html_e('Loading explanation...', 'ai-explainer'); ?>
                    </div>
                    <div class="explanation-display" id="explanation-display" style="display: none;">
                        <!-- Explanation text will be populated by JavaScript -->
                    </div>
                    <div class="explanation-error" id="explanation-error" style="display: none; color: #d63638; font-style: italic;">
                        <!-- Error message will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            
            
            <!-- Progress indicator (hidden initially) -->
            <div class="progress-indicator" id="blog-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-text" id="progress-text">
                    <?php esc_html_e('Preparing...', 'ai-explainer'); ?>
                </div>
            </div>
            
            <!-- Generation options -->
            <div class="generation-options" id="generation-options">
                <form id="blog-creation-form">
                    <!-- AI Prompt -->
                    <div class="form-group">
                        <label for="content-prompt"><?php esc_html_e('AI Prompt:', 'ai-explainer'); ?></label>
                        <textarea 
                            name="content_prompt" 
                            id="content-prompt" 
                            rows="20" 
                            placeholder="<?php esc_attr_e('Create a blog post about: {{selection}}', 'ai-explainer'); ?>"
                        ><?php echo esc_textarea('Write a blog post about {{selectedtext}} that feels natural, helpful, and written by a real person — not AI. The post should be humanised, SEO-ready, and suitable for publishing on a WordPress blog. Use {{wplang}} spelling and follow this exact structure:

⸻

1. Intro Paragraph — Answer a User\'s Question
Begin by answering a likely search question someone might ask about {{selectedtext}}. For example:
"What exactly are {{selectedtext}}, and why do they matter?"
Then answer it directly in a natural, friendly way. This helps with AI search (e.g. ChatGPT, Gemini) and SEO discoverability.

⸻

2. Weave in the Definition Naturally
Subtly include this definition in context — do not spotlight it or label it "Definition." Blend it into a sentence or two:
{{explanation}}

It should feel like part of the conversation, not a glossary entry.

⸻

3. Main Body (3–5 short paragraphs)
Explore the topic in a clear, conversational tone. Vary sentence length, use contractions, and imagine explaining this to a curious friend. Include:
    •    Why it matters
    •    Real-life examples or metaphors
    •    Any relevance to health, life, or personal insight
    •    Naturally embedded keywords (no stuffing)

Use clean WordPress-friendly formatting:
    •    Short paragraphs (2–4 lines)
    •    Use <h2> and <h3> headings where helpful
    •    Bullets or numbered lists if needed

⸻

4. Human Takeaway or Closing Insight
End with a short reflection, encouragement, or surprising insight. Make it sound like a thoughtful human signing off, not a corporate summary.

⸻

Constraints:
    •    Create the article in {{wplang}}
    •    Avoid robotic tone or overused phrasing
    •    Do not spotlight the definition — blend it in naturally
    •    Optimise for AI search + SEO by:
    •    Answering a question upfront
    •    Using relevant subheadings
    •    Keeping the tone expressive, not stiff'); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('The selected text and its explanation will be automatically populated in the prompt. Edit the content as needed before creating the blog post.', 'ai-explainer'); ?>
                        </p>
                    </div>
                    
                    <!-- Post Length -->
                    <div class="form-group">
                        <label for="post-length-select"><?php esc_html_e('Post Length:', 'ai-explainer'); ?></label>
                        <select name="post_length" id="post-length-select">
                            <option value="short">
                                <?php esc_html_e('Short (~500 words)', 'ai-explainer'); ?>
                            </option>
                            <option value="medium" selected>
                                <?php esc_html_e('Medium (~1000 words)', 'ai-explainer'); ?>
                            </option>
                            <option value="long">
                                <?php esc_html_e('Long (~2000 words)', 'ai-explainer'); ?>
                            </option>
                        </select>
                    </div>
                    
                    <!-- AI Provider (hidden - uses plugin setting) -->
                    <?php
                    $api_provider = get_option('explainer_api_provider', 'openai');
                    $api_model = get_option('explainer_api_model', 'gpt-3.5-turbo');
                    
                    // Convert to expected format
                    $provider_value = '';
                    if ($api_provider === 'openai') {
                        $provider_value = ($api_model === 'gpt-4') ? 'openai-gpt4' : 'openai-gpt35';
                    } elseif ($api_provider === 'claude') {
                        $provider_value = (strpos($api_model, 'sonnet') !== false) ? 'claude-sonnet' : 'claude-haiku';
                    }
                    ?>
                    <input type="hidden" name="ai_provider" id="ai-provider-hidden" value="<?php echo esc_attr($provider_value); ?>" />
                    
                    <!-- Additional Options -->
                    <div class="form-group additional-options">
                        <h4><?php esc_html_e('Additional Features:', 'ai-explainer'); ?></h4>
                        
                        <div class="form-group image-options">
                            <label for="image-size-select"><?php esc_html_e('Featured Image:', 'ai-explainer'); ?></label>
                            <select name="image_size" id="image-size-select">
                                <option value="none"><?php esc_html_e('No image', 'ai-explainer'); ?></option>
                                <option value="portrait"><?php esc_html_e('Portrait (1024x1792)', 'ai-explainer'); ?></option>
                                <option value="square"><?php esc_html_e('Square (1024x1024)', 'ai-explainer'); ?></option>
                                <option value="wide"><?php esc_html_e('Wide (1792x1024)', 'ai-explainer'); ?></option>
                            </select>
                        </div>
                        
                        <?php
                        // Check if an SEO plugin is active - use main plugin instance methods
                        $plugin_instance = ExplainerPlugin::get_instance();
                        $has_seo_plugin = $plugin_instance->has_active_seo_plugin();
                        $seo_plugin_name = $plugin_instance->get_active_seo_plugin_name();
                        ?>
                        
                        <label class="checkbox-label <?php echo $has_seo_plugin ? 'disabled' : ''; ?>">
                            <input type="checkbox" name="generate_seo" id="generate-seo" <?php echo $has_seo_plugin ? '' : 'checked'; ?> <?php disabled($has_seo_plugin, true); ?> />
                            <span class="checkmark"></span>
                            <?php esc_html_e('Generate SEO metadata', 'ai-explainer'); ?>
                            <?php if ($has_seo_plugin): ?>
                                <span class="seo-disabled-note" style="color: #666; font-size: 12px; font-style: italic;">
                                    <?php 
                                    /* translators: %s is the name of the SEO plugin */
                                    echo sprintf(esc_html__('(Disabled - %s detected)', 'ai-explainer'), esc_html($seo_plugin_name)); 
                                    ?>
                                </span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($has_seo_plugin): ?>
                            <div class="seo-plugin-modal-notice" style="margin-top: 5px; padding: 8px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px; font-size: 12px;">
                                <strong><?php echo esc_html__('SEO Plugin Detected:', 'ai-explainer'); ?></strong> <?php echo esc_html($seo_plugin_name); ?><br>
                                <span style="color: #856404;">
                                    <?php echo esc_html__('SEO metadata generation is disabled because your SEO plugin will handle this automatically.', 'ai-explainer'); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Hidden fields -->
                    <input type="hidden" name="selection_text" id="modal-selection-text" />
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('explainer_admin_nonce')); ?>" />
                </form>
            </div>
            
            <!-- Success/Error Messages -->
            <div class="modal-messages" id="modal-messages" style="display: none;">
                <div class="message-content" id="message-content"></div>
                
                <!-- Post Success Details -->
                <div class="post-success-details" id="post-success-details" style="display: none;">
                    <div class="post-info-card">
                        <div class="post-thumbnail" id="post-thumbnail" style="display: none;">
                            <img src="" alt="" id="post-thumbnail-img" />
                        </div>
                        <div class="post-details">
                            <h3 class="post-title" id="post-title">Blog Post Title</h3>
                            <p class="post-status">Status: <span id="post-status-text">Draft</span></p>
                        </div>
                    </div>
                    <div class="post-actions">
                        <a href="#" class="btn-base btn-primary btn-with-icon edit-post-btn" id="edit-post-link" target="_blank">
                            <span class="dashicons dashicons-edit btn-icon-size"></span>
                            <?php esc_html_e('Edit Post', 'ai-explainer'); ?>
                        </a>
                        <a href="#" class="btn-base btn-secondary btn-with-icon preview-post-btn" id="preview-post-link" target="_blank">
                            <span class="dashicons dashicons-visibility btn-icon-size"></span>
                            <?php esc_html_e('Preview Post', 'ai-explainer'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="message-actions" id="message-actions" style="display: none;">
                    <!-- Legacy action buttons - kept for backwards compatibility -->
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-base btn-secondary" id="cancel-creation">
                <?php esc_html_e('Cancel', 'ai-explainer'); ?>
            </button>
            <button type="button" class="btn-base btn-primary btn-lg" id="create-post-submit">
                <?php esc_html_e('Create Post', 'ai-explainer'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal backdrop -->
<div id="modal-backdrop" class="modal-backdrop" style="display: none;"></div>

<script type="text/javascript">
// Modal functionality will be handled by blog-creator.js
// This script block provides any necessary inline initialization
jQuery(document).ready(function($) {
    // Initialize modal if blog creator JS is loaded
    if (typeof ExplainerBlogCreator !== 'undefined') {
        ExplainerBlogCreator.init();
    }
});
</script>