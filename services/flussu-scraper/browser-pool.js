/**
 * Flussu Scraper - Browser Pool
 * Manages a persistent Chromium browser instance with page recycling.
 */

import { chromium } from 'playwright';

let browser = null;
let launching = false;
let launchQueue = [];

async function ensureBrowser() {
    if (browser && browser.isConnected()) {
        return browser;
    }

    if (launching) {
        return new Promise((resolve, reject) => {
            launchQueue.push({ resolve, reject });
        });
    }

    launching = true;
    try {
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-extensions',
                '--disable-background-networking',
                '--disable-default-apps',
                '--disable-sync',
                '--no-first-run'
            ]
        });

        browser.on('disconnected', () => {
            browser = null;
        });

        launching = false;
        for (const waiter of launchQueue) {
            waiter.resolve(browser);
        }
        launchQueue = [];
        return browser;
    } catch (err) {
        launching = false;
        for (const waiter of launchQueue) {
            waiter.reject(err);
        }
        launchQueue = [];
        throw err;
    }
}

export async function createPage() {
    const b = await ensureBrowser();
    const context = await b.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        viewport: { width: 1280, height: 720 },
        ignoreHTTPSErrors: true
    });
    const page = await context.newPage();
    return { page, context };
}

export async function closeBrowser() {
    if (browser) {
        try {
            await browser.close();
        } catch (_) {
            // ignore
        }
        browser = null;
    }
}
