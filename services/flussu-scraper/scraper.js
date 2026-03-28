/**
 * Flussu Scraper - Core scraping logic
 * Uses Playwright for page rendering and @mozilla/readability for content extraction.
 */

import { Readability } from '@mozilla/readability';
import { parseHTML } from 'linkedom';
import { createPage } from './browser-pool.js';

/**
 * Scrape a URL and return structured JSON.
 * @param {string} url - The URL to scrape
 * @param {object} options - { timeout: number, extraWait: number }
 * @returns {Promise<object>} Structured scrape result
 */
export async function scrape(url, options = {}) {
    const timeout = options.timeout || 30000;
    const extraWait = options.extraWait || 2000;
    const startTime = Date.now();

    let page, context;
    try {
        ({ page, context } = await createPage());
    } catch (err) {
        return errorResult(url, `Browser error: ${err.message}`, startTime);
    }

    try {
        const response = await page.goto(url, {
            waitUntil: 'domcontentloaded',
            timeout
        });

        // Extra wait for dynamic JS rendering
        await page.waitForTimeout(extraWait);

        const status = response ? response.status() : 0;
        const finalUrl = page.url();
        const html = await page.content();

        // Parse with linkedom for Readability
        const { document } = parseHTML(html);

        // Extract with Readability
        const reader = new Readability(document, { charThreshold: 50 });
        const article = reader.parse();

        // Re-parse for metadata extraction (Readability modifies the document)
        const { document: metaDoc } = parseHTML(html);

        const title = article?.title || extractTitle(metaDoc);
        const description = extractDescription(metaDoc);
        const author = article?.byline || extractAuthor(metaDoc);
        const content = article?.textContent?.trim() || '';
        const headings = extractHeadings(metaDoc);
        const links = extractLinks(metaDoc, finalUrl);
        const images = extractImages(metaDoc, finalUrl);
        const metadata = extractMetadata(metaDoc);

        return {
            url: finalUrl,
            status,
            title: title || '',
            description: description || '',
            content,
            author: author || '',
            headings,
            links,
            images,
            metadata,
            scraped_at: new Date().toISOString(),
            elapsed_ms: Date.now() - startTime,
            method: 'playwright'
        };
    } catch (err) {
        return errorResult(url, err.message, startTime);
    } finally {
        try {
            await context.close();
        } catch (_) {
            // ignore
        }
    }
}

function errorResult(url, error, startTime) {
    return {
        url,
        status: 0,
        title: '',
        description: '',
        content: '',
        author: '',
        headings: [],
        links: [],
        images: [],
        metadata: {},
        error,
        scraped_at: new Date().toISOString(),
        elapsed_ms: Date.now() - startTime,
        method: 'playwright'
    };
}

function extractTitle(doc) {
    const ogTitle = doc.querySelector('meta[property="og:title"]');
    if (ogTitle) return ogTitle.getAttribute('content') || '';
    const titleEl = doc.querySelector('title');
    return titleEl?.textContent?.trim() || '';
}

function extractDescription(doc) {
    const ogDesc = doc.querySelector('meta[property="og:description"]');
    if (ogDesc) return ogDesc.getAttribute('content') || '';
    const metaDesc = doc.querySelector('meta[name="description"]');
    return metaDesc?.getAttribute('content') || '';
}

function extractAuthor(doc) {
    const metaAuthor = doc.querySelector('meta[name="author"]');
    if (metaAuthor) return metaAuthor.getAttribute('content') || '';
    const ogAuthor = doc.querySelector('meta[property="article:author"]');
    return ogAuthor?.getAttribute('content') || '';
}

function extractHeadings(doc) {
    const headings = [];
    const els = doc.querySelectorAll('h1, h2, h3, h4, h5, h6');
    for (const el of els) {
        const text = el.textContent?.trim();
        if (text) {
            const level = parseInt(el.tagName.substring(1), 10);
            headings.push({ level, text });
        }
    }
    return headings;
}

function extractLinks(doc, baseUrl) {
    const links = [];
    const seen = new Set();
    const els = doc.querySelectorAll('a[href]');
    for (const el of els) {
        const text = el.textContent?.trim();
        let href = el.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) continue;
        try {
            href = new URL(href, baseUrl).href;
        } catch (_) {
            continue;
        }
        if (seen.has(href)) continue;
        seen.add(href);
        const external = !href.startsWith(new URL(baseUrl).origin);
        links.push({ text: text || '', href, external });
    }
    return links;
}

function extractImages(doc, baseUrl) {
    const images = [];
    const seen = new Set();
    const els = doc.querySelectorAll('img[src]');
    for (const el of els) {
        let src = el.getAttribute('src') || '';
        if (!src) continue;
        try {
            src = new URL(src, baseUrl).href;
        } catch (_) {
            continue;
        }
        if (seen.has(src)) continue;
        seen.add(src);
        const alt = el.getAttribute('alt') || '';
        const title = el.getAttribute('title') || '';
        images.push({ src, alt, title });
    }
    return images;
}

function extractMetadata(doc) {
    const meta = {};
    const tags = doc.querySelectorAll('meta[property], meta[name]');
    for (const tag of tags) {
        const key = tag.getAttribute('property') || tag.getAttribute('name');
        const val = tag.getAttribute('content');
        if (key && val) {
            meta[key] = val;
        }
    }
    const canonical = doc.querySelector('link[rel="canonical"]');
    if (canonical) {
        meta['canonical'] = canonical.getAttribute('href') || '';
    }
    return meta;
}
