// Run in DDEV with Playwright Firefox and a host serving this package's assets.
const assert = require('node:assert/strict');
const {firefox} = require(process.env.FF_PLAYWRIGHT_MODULE || 'playwright');

(async () => {
    const browser = await firefox.launch({headless: true});
    try {
        const page = await browser.newPage({ignoreHTTPSErrors: true, viewport: {width: 1500, height: 1000}});
        const response = await page.goto(process.env.FF_EXAMPLE_URL || 'https://slothsoft.ddev.site/slothsoft@final-fantasy/i/examples/panels');
        assert.equal(response.status(), 200);
        await page.evaluate(() => document.fonts.ready);
        assert.equal(await page.locator('link[rel="stylesheet"]').count(), 1);
        assert.equal(await page.locator('style, script').count(), 0);
        const tables = page.locator('fieldset.ff-ui');
        for (const [index, scale] of [1, 2].entries()) {
            const table = tables.nth(index);
            const metrics = await table.evaluate(panel => {
                const style = getComputedStyle(panel);
                const portrait = getComputedStyle(panel.querySelector('.ff-portrait'));
                const header = getComputedStyle(panel.querySelector('thead th'));
                return [style.fontSize, style.lineHeight, style.borderTopWidth, portrait.width, portrait.height,
                    header.color, panel.querySelector('legend').getBoundingClientRect().left - panel.getBoundingClientRect().left];
            });
            assert.deepEqual(metrics, [`${8 * scale}px`, `${12 * scale}px`, `${scale}px`, `${16 * scale}px`, `${12 * scale}px`, 'rgb(164, 228, 252)', 0]);
            const form = page.locator('form.ff-ui:not(.ff-row)').nth(index);
            const controls = await form.locator('select').evaluateAll(selects => selects.map(select => [select.getBoundingClientRect().height, getComputedStyle(select).fontFamily]));
            assert.equal(controls.length, 4);
            controls.forEach(([height, font]) => {
                assert.equal(height, 12 * scale);
                assert.match(font, /Final Fantasy I/);
            });
            const equipped = form.locator('input.ff-equipped').first();
            assert.equal(await equipped.evaluate(node => getComputedStyle(node, '::after').content), '"E"');
            await equipped.focus();
            await equipped.press('Space');
            assert.equal(await equipped.evaluate(node => getComputedStyle(node, '::after').opacity), '0.25');
            await equipped.press('Space');
        }
        // Semantic token overrides must apply to table headings as well as their panel.
        const themed = tables.first();
        await themed.evaluate(panel => {
            panel.style.setProperty('--ff-background', 'var(--ff-dark-blue)');
            panel.style.setProperty('--ff-heading', 'var(--ff-gold)');
        });
        assert.deepEqual(await themed.evaluate(panel => [getComputedStyle(panel).backgroundColor, getComputedStyle(panel.querySelector('thead th')).color]), ['rgb(0, 0, 188)', 'rgb(248, 184, 0)']);
        await themed.evaluate(panel => {
            panel.style.removeProperty('--ff-background');
            panel.style.removeProperty('--ff-heading');
        });
        const row = themed.locator('.ff-row');
        await row.evaluate(node => node.hidden = true);
        assert.equal(await row.isVisible(), false);
        await row.evaluate(node => node.hidden = false);
        await themed.evaluate(node => node.hidden = true);
        assert.equal(await themed.isVisible(), false);
        await themed.evaluate(node => node.hidden = false);
        if (process.env.FF_EXAMPLE_SCREENSHOT) {
            await page.screenshot({path: process.env.FF_EXAMPLE_SCREENSHOT});
        }
        await page.setViewportSize({width: 390, height: 1000});
        assert.equal(await page.evaluate(() => document.body.scrollWidth <= innerWidth), true);
        const equipment = page.locator('form:not(.ff-row) .ff-scroll > .ff-grid');
        for (const grid of await equipment.all()) {
            assert.deepEqual(await grid.evaluate(node => {
                const slots = [...node.children].map(child => child.getBoundingClientRect());
                return [new Set(slots.map(slot => slot.left)).size, new Set(slots.map(slot => slot.top)).size];
            }), [2, 2]);
        }
        console.log('Firefox: standalone utilities, both scales, framed legends, native controls, semantic tokens and narrow layouts passed.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
