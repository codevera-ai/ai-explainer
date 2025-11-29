<?php
/**
 * AI Providers Configuration
 * 
 * Single source of truth for all AI provider and model definitions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

return array(
    'openai' => array(
        'name' => 'OpenAI',
        'class' => 'ExplainerPlugin_OpenAI_Provider',
        'api_key_option' => 'explainer_openai_api_key',
        'endpoint' => 'https://api.openai.com/v1/chat/completions',
        'has_region' => false,
        'api_key_prefix' => 'sk-',
        'api_key_min_length' => 20,
        'api_key_max_length' => 200,
        'hide_model_selector' => true,
        'default_model' => 'gpt-5.1',
        'models' => array(
            'gpt-5.1' => array(
                'label' => 'GPT-5.1',
                'max_tokens' => 128000,
                'cost_per_1k_tokens' => 0.005,
                'temperature' => 0.7
            )
        )
    ),
    
    'claude' => array(
        'name' => 'Claude',
        'class' => 'ExplainerPlugin_Claude_Provider',
        'api_key_option' => 'explainer_claude_api_key',
        'endpoint' => 'https://api.anthropic.com/v1/messages',
        'has_region' => false,
        'api_key_prefix' => 'sk-ant-',
        'api_key_min_length' => 20,
        'api_key_max_length' => 200,
        'hide_model_selector' => true,
        'default_model' => 'claude-haiku-4-5',
        'models' => array(
            'claude-haiku-4-5' => array(
                'label' => 'Claude Haiku 4.5',
                'max_tokens' => 200000,
                'cost_per_1k_tokens' => 0.00025,
                'temperature' => 0.7
            )
        )
    ),
    
    'openrouter' => array(
        'name' => 'OpenRouter',
        'class' => 'ExplainerPlugin_OpenRouter_Provider',
        'api_key_option' => 'explainer_openrouter_api_key',
        'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
        'has_region' => false,
        'api_key_prefix' => 'sk-or-',
        'api_key_min_length' => 20,
        'api_key_max_length' => 200,
        'hide_model_selector' => true,
        'default_model' => 'anthropic/claude-3.5-sonnet',
        'models' => array(
            'anthropic/claude-3.5-sonnet' => array(
                'label' => 'Claude 3.5 Sonnet',
                'max_tokens' => 200000,
                'cost_per_1k_tokens' => 0.003,
                'temperature' => 0.7
            )
        )
    ),

    'gemini' => array(
        'name' => 'Google Gemini',
        'class' => 'ExplainerPlugin_Gemini_Provider',
        'api_key_option' => 'explainer_gemini_api_key',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
        'has_region' => false,
        'api_key_prefix' => 'AIza',
        'api_key_min_length' => 39,
        'api_key_max_length' => 39,
        'hide_model_selector' => true,
        'default_model' => 'gemini-2.5-flash',
        'models' => array(
            'gemini-2.5-flash' => array(
                'label' => 'Gemini 2.5 Flash',
                'max_tokens' => 1048576,
                'cost_per_1k_tokens' => 0.00015,
                'temperature' => 0.7
            )
        )
    )
);