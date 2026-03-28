/**
 * Flussu Scraper - Microservice Entry Point
 *
 * A local HTTP microservice that scrapes web pages using Playwright
 * and returns structured JSON with clean content via Readability.
 *
 * Runs on 127.0.0.1 only (not exposed to the internet).
 */

import Fastify from 'fastify';
import { scrape } from './scraper.js';
import { closeBrowser } from './browser-pool.js';

const PORT = parseInt(process.env.PORT, 10) || 3100;
const HOST = '127.0.0.1';

const fastify = Fastify({
    logger: {
        level: process.env.LOG_LEVEL || 'info'
    },
    bodyLimit: 1048576 // 1MB
});

// Health check
fastify.get('/health', async () => {
    return {
        status: 'ok',
        service: 'flussu-scraper',
        uptime: process.uptime(),
        timestamp: new Date().toISOString()
    };
});

// Main scrape endpoint
fastify.post('/scrape', {
    schema: {
        body: {
            type: 'object',
            required: ['url'],
            properties: {
                url: { type: 'string' },
                timeout: { type: 'integer', default: 30000 },
                extraWait: { type: 'integer', default: 2000 }
            }
        }
    }
}, async (request, reply) => {
    const { url, timeout, extraWait } = request.body;

    // Basic URL validation
    try {
        new URL(url);
    } catch (_) {
        return reply.status(400).send({
            error: 'Invalid URL',
            url
        });
    }

    const result = await scrape(url, { timeout, extraWait });
    return result;
});

// Graceful shutdown
async function shutdown(signal) {
    fastify.log.info(`Received ${signal}, shutting down...`);
    await closeBrowser();
    await fastify.close();
    process.exit(0);
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

// Start server
try {
    await fastify.listen({ port: PORT, host: HOST });
    fastify.log.info(`Flussu Scraper listening on http://${HOST}:${PORT}`);
} catch (err) {
    fastify.log.error(err);
    process.exit(1);
}
