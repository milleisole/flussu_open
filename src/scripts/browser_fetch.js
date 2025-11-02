/*
import puppeteer from 'puppeteer';
const url = process.argv[2];
const browser = await puppeteer.launch({ headless: true });
const page = await browser.newPage();
await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
console.log(await page.content());
await browser.close();

---

import puppeteer from 'puppeteer';
(async () => {
  const url = process.argv[2];
  const browser = await puppeteer.launch({
    headless: true,
    executablePath: '/usr/bin/google-chrome',  // <-- percorso corretto
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  const page = await browser.newPage();
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    // poi aspetta un attimo per i JS lenti (Facebook, React, ecc.)
    await page.waitForTimeout(3000);
    const html = await page.evaluate(() => document.documentElement.outerHTML);
    console.log(html);
    await browser.close();
})();


*/


import puppeteer from 'puppeteer';
const url = process.argv[2];
const browser = await puppeteer.launch({
  headless: true,
  executablePath: '/usr/bin/google-chrome',  // <-- percorso corretto
  args: ['--no-sandbox', '--disable-setuid-sandbox']
});
const page = await browser.newPage();
await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
// attesa extra per caricamenti JS dinamici (Facebook, React, ecc.)
await new Promise(resolve => setTimeout(resolve, 3000));
const html = await page.evaluate(() => document.documentElement.outerHTML);
console.log(html);
await browser.close();