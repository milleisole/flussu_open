<?php
/* --------------------------------------------------------------------*
 * Flussu v.5.0 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Platform Enum - AI Provider Selection
 * --------------------------------------------------------------------*/

namespace Flussu\Controllers;

/**
 * AI Platform enumeration
 * 
 * Defines available AI providers for chat and AI operations
 */
enum Platform: int {
    case INIT = -1;         // Initialization mode
    case CHATGPT = 0;       // OpenAI ChatGPT
    case GROK = 1;          // Grok AI
    case GEMINI = 2;        // Google Gemini
    case DEEPSEEK = 3;      // DeepSeek AI
    case CLAUDE = 4;        // Anthropic Claude
    case MOONSHOT = 5;          // Moonshot-Kimi AI
    case HUGGINGFACE = 9;   // HuggingFace models
}